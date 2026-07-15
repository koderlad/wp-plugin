<?php
/**
 * Bookings: DB table, AJAX handler, departures list, booking modal, and admin list screen.
 *
 * We use a dedicated table (not post meta) because bookings need atomic seat
 * decrement under concurrent load - a per-departure row lock is far easier to
 * reason about than juggling serialized post meta arrays.
 *
 * @package TravelPack
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Travel_Pack_Booking {

	const DB_VERSION_OPTION = 'travel_pack_db_version';
	const DB_VERSION        = '1.1.0';

	const ROOM_TYPES = array( 'Single', 'Double', 'Group' );

	public static function init() {
		// Guard against schema drift on already-installed sites where the activation hook was skipped.
		add_action( 'admin_init', array( __CLASS__, 'maybe_upgrade' ) );

		add_action( 'wp_ajax_travel_pack_book',        array( __CLASS__, 'handle_ajax_book' ) );
		add_action( 'wp_ajax_nopriv_travel_pack_book', array( __CLASS__, 'handle_ajax_book' ) );
	}

	public static function table_name() {
		global $wpdb;
		return $wpdb->prefix . 'travel_pack_bookings';
	}

	public static function maybe_upgrade() {
		if ( get_option( self::DB_VERSION_OPTION ) !== self::DB_VERSION ) {
			self::create_table();
		}
	}

	public static function create_table() {
		global $wpdb;

		$table   = self::table_name();
		$charset = $wpdb->get_charset_collate();

		// dbDelta will ADD new columns to an existing table (e.g. room_type on 1.0 -> 1.1
		// upgrades) but will not drop columns removed from this schema. That is fine —
		// stale columns cost nothing.
		$sql = "CREATE TABLE {$table} (
			id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
			package_id BIGINT UNSIGNED NOT NULL,
			departure_id VARCHAR(32) NOT NULL,
			seats SMALLINT UNSIGNED NOT NULL DEFAULT 1,
			room_type VARCHAR(20) NOT NULL DEFAULT '',
			customer_name VARCHAR(191) NOT NULL,
			customer_email VARCHAR(191) NOT NULL,
			status VARCHAR(20) NOT NULL DEFAULT 'confirmed',
			created_at DATETIME NOT NULL,
			PRIMARY KEY  (id),
			KEY package_departure (package_id, departure_id),
			KEY status (status)
		) {$charset};";

		require_once ABSPATH . 'wp-admin/includes/upgrade.php';
		dbDelta( $sql );

		update_option( self::DB_VERSION_OPTION, self::DB_VERSION );
	}

	/**
	 * Renders the departures list + hidden booking modal into the single package template.
	 */
	public static function render_departures_and_modal( $post_id ) {
		self::render_departures_list( $post_id );
		self::render_booking_modal( $post_id );
	}

	private static function render_departures_list( $post_id ) {
		$departures    = Travel_Pack_Departures::get_upcoming_with_availability( $post_id );
		$package_price = get_post_meta( $post_id, Travel_Pack_Metaboxes::META_PRICE, true );
		$duration_days = self::get_package_duration_days( $post_id );

		// Group upcoming departures by YYYY-MM so each month becomes a tab + panel.
		$by_month = array();
		foreach ( $departures as $dep ) {
			$ym = substr( $dep['date'], 0, 7 );
			if ( ! isset( $by_month[ $ym ] ) ) {
				$by_month[ $ym ] = array();
			}
			$by_month[ $ym ][] = $dep;
		}
		ksort( $by_month );

		$first_month = $by_month ? array_key_first( $by_month ) : '';
		?>
		<section class="tp-dates" id="travel-pack-departures">
			<p class="tp-dates__eyebrow"><?php esc_html_e( 'Available Departures', 'travel-pack' ); ?></p>
			<h2 class="tp-dates__title"><?php esc_html_e( 'Dates & Prices', 'travel-pack' ); ?></h2>
			<p class="tp-dates__lead">
				<?php esc_html_e( 'Select a month to view upcoming departures.', 'travel-pack' ); ?>
			</p>

			<?php if ( empty( $by_month ) ) : ?>
				<p class="tp-dates__empty">
					<?php esc_html_e( 'No upcoming departures are open for booking. Please check back later.', 'travel-pack' ); ?>
				</p>
			<?php else : ?>
				<div class="tp-dates__tabs" role="tablist">
					<?php foreach ( $by_month as $ym => $_ ) :
						$is_active = ( $ym === $first_month );
						$ts        = strtotime( $ym . '-01' );
						?>
						<button
							type="button"
							role="tab"
							class="tp-dates__tab<?php echo $is_active ? ' is-active' : ''; ?>"
							data-tp-month="<?php echo esc_attr( $ym ); ?>"
							aria-selected="<?php echo $is_active ? 'true' : 'false'; ?>"
							aria-controls="tp-dates-panel-<?php echo esc_attr( $ym ); ?>"
						>
							<?php echo esc_html( date_i18n( "M 'y", $ts ) ); ?>
						</button>
					<?php endforeach; ?>
				</div>

				<div class="tp-dates__table" role="table">
					<div class="tp-dates__head" role="row">
						<div class="tp-dates__col-date"><?php esc_html_e( 'Start Date', 'travel-pack' ); ?></div>
						<div class="tp-dates__col-duration"><?php esc_html_e( 'Duration', 'travel-pack' ); ?></div>
						<div class="tp-dates__col-avail"><?php esc_html_e( 'Availability', 'travel-pack' ); ?></div>
						<div class="tp-dates__col-price"><?php esc_html_e( 'Price / Person', 'travel-pack' ); ?></div>
						<div class="tp-dates__col-cta" aria-hidden="true"></div>
					</div>

					<?php foreach ( $by_month as $ym => $deps ) :
						$is_active = ( $ym === $first_month );
						?>
						<div
							class="tp-dates__panel<?php echo $is_active ? ' is-active' : ''; ?>"
							id="tp-dates-panel-<?php echo esc_attr( $ym ); ?>"
							data-tp-month="<?php echo esc_attr( $ym ); ?>"
							role="tabpanel"
							<?php echo $is_active ? '' : 'hidden'; ?>
						>
							<?php foreach ( $deps as $dep ) :
								$price      = ! empty( $dep['price'] ) ? $dep['price'] : $package_price;
								$avail      = self::availability_state( $dep );
								$start_ts   = strtotime( $dep['date'] );
								$end_ts     = $duration_days > 0 ? strtotime( '+' . ( $duration_days - 1 ) . ' days', $start_ts ) : 0;
								$start_str  = date_i18n( 'j M Y', $start_ts );
								$end_str    = $end_ts ? date_i18n( 'j M Y', $end_ts ) : '';
								?>
								<div class="tp-dates__row" role="row">
									<div class="tp-dates__cell tp-dates__cell--date">
										<strong class="tp-dates__date-start"><?php echo esc_html( $start_str ); ?></strong>
										<?php if ( $end_str ) : ?>
											<span class="tp-dates__date-end">&rarr; <?php echo esc_html( $end_str ); ?></span>
										<?php endif; ?>
									</div>

									<div class="tp-dates__cell tp-dates__cell--duration">
										<?php if ( $duration_days > 0 ) : ?>
											<strong class="tp-dates__dur-days">
												<?php
												printf(
													/* translators: %d days */
													esc_html( _n( '%d Day', '%d Days', $duration_days, 'travel-pack' ) ),
													(int) $duration_days
												);
												?>
											</strong>
											<?php if ( $duration_days > 1 ) : ?>
												<span class="tp-dates__dur-nights">
													<?php
													$nights = $duration_days - 1;
													printf(
														/* translators: %d nights */
														esc_html( _n( '%d Night', '%d Nights', $nights, 'travel-pack' ) ),
														(int) $nights
													);
													?>
												</span>
											<?php endif; ?>
										<?php else : ?>
											<span class="tp-dates__dur-days">—</span>
										<?php endif; ?>
									</div>

									<div class="tp-dates__cell tp-dates__cell--avail">
										<div class="tp-avail tp-avail--<?php echo esc_attr( $avail['state'] ); ?>">
											<div class="tp-avail__track">
												<div class="tp-avail__bar" style="width: <?php echo esc_attr( $avail['fill'] ); ?>%;"></div>
											</div>
											<span class="tp-avail__label"><?php echo esc_html( $avail['label'] ); ?></span>
										</div>
									</div>

									<div class="tp-dates__cell tp-dates__cell--price">
										<?php if ( '' !== trim( (string) $price ) ) : ?>
											<strong class="tp-dates__price-value"><?php echo esc_html( $price ); ?></strong>
											<span class="tp-dates__price-label"><?php esc_html_e( 'per person', 'travel-pack' ); ?></span>
										<?php else : ?>
											<strong class="tp-dates__price-value">—</strong>
										<?php endif; ?>
									</div>

									<div class="tp-dates__cell tp-dates__cell--cta">
										<button
											type="button"
											class="tp-dates__cta<?php echo $dep['is_full'] ? ' is-disabled' : ''; ?>"
											data-tp-open
											data-departure-id="<?php echo esc_attr( $dep['id'] ); ?>"
											data-date="<?php echo esc_attr( $start_str ); ?>"
											data-price="<?php echo esc_attr( $price ); ?>"
											data-remaining="<?php echo esc_attr( $dep['remaining'] ); ?>"
											data-total="<?php echo esc_attr( $dep['seats'] ); ?>"
											<?php disabled( $dep['is_full'] ); ?>
										>
											<?php echo esc_html( $dep['is_full'] ? __( 'Sold Out', 'travel-pack' ) : __( 'Join Now', 'travel-pack' ) ); ?>
										</button>
									</div>
								</div>
							<?php endforeach; ?>
						</div>
					<?php endforeach; ?>
				</div>
			<?php endif; ?>
		</section>
		<?php
	}

	/**
	 * Package duration in days, summed from the itinerary. Every itinerary entry
	 * carries a days count (default 1), so total days = sum of those counts.
	 */
	private static function get_package_duration_days( $post_id ) {
		$total = 0;
		foreach ( Travel_Pack_Metaboxes::get_itinerary_with_labels( $post_id ) as $item ) {
			$total += (int) $item['days'];
		}
		return $total;
	}

	private static function render_booking_modal( $post_id ) {
		?>
		<div class="travel-pack-modal" id="travel-pack-modal" hidden aria-hidden="true">
			<div class="travel-pack-modal__backdrop" data-tp-close></div>
			<div class="travel-pack-modal__dialog" role="dialog" aria-modal="true" aria-labelledby="travel-pack-modal-title">
				<button type="button" class="travel-pack-modal__close" data-tp-close aria-label="<?php esc_attr_e( 'Close', 'travel-pack' ); ?>">×</button>
				<h2 class="travel-pack-modal__title" id="travel-pack-modal-title">
					<?php esc_html_e( 'Book Your Departure', 'travel-pack' ); ?>
				</h2>

				<div class="travel-pack-modal__summary">
					<div class="travel-pack-modal__summary-item">
						<span class="travel-pack-modal__summary-label"><?php esc_html_e( 'Departure Date', 'travel-pack' ); ?></span>
						<strong class="travel-pack-modal__summary-value" data-tp-summary="date">—</strong>
					</div>
					<div class="travel-pack-modal__summary-item">
						<span class="travel-pack-modal__summary-label"><?php esc_html_e( 'Price per person', 'travel-pack' ); ?></span>
						<strong class="travel-pack-modal__summary-value" data-tp-summary="price">—</strong>
					</div>
					<div class="travel-pack-modal__summary-item">
						<span class="travel-pack-modal__summary-label"><?php esc_html_e( 'Seats Available', 'travel-pack' ); ?></span>
						<strong class="travel-pack-modal__summary-value" data-tp-summary="remaining">—</strong>
					</div>
				</div>

				<form class="travel-pack-booking__form" method="post">
					<?php wp_nonce_field( 'travel_pack_book_' . $post_id, 'travel_pack_booking_nonce' ); ?>
					<input type="hidden" name="package_id" value="<?php echo esc_attr( $post_id ); ?>" />
					<input type="hidden" name="departure_id" value="" data-tp-input="departure_id" />

					<div class="travel-pack-booking__field">
						<label for="tp_name"><?php esc_html_e( 'Full Name', 'travel-pack' ); ?> <span class="required">*</span></label>
						<input type="text" id="tp_name" name="customer_name" required />
					</div>

					<div class="travel-pack-booking__field">
						<label for="tp_email"><?php esc_html_e( 'Email Address', 'travel-pack' ); ?> <span class="required">*</span></label>
						<input type="email" id="tp_email" name="customer_email" required />
					</div>

					<div class="travel-pack-booking__row">
						<div class="travel-pack-booking__field">
							<label for="tp_seats"><?php esc_html_e( 'Number of Travelers', 'travel-pack' ); ?> <span class="required">*</span></label>
							<input type="number" id="tp_seats" name="seats" min="1" step="1" value="1" required data-tp-input="seats" />
						</div>
						<div class="travel-pack-booking__field">
							<label for="tp_room"><?php esc_html_e( 'Room Type', 'travel-pack' ); ?> <span class="required">*</span></label>
							<select id="tp_room" name="room_type" required>
								<option value=""><?php esc_html_e( '— Select —', 'travel-pack' ); ?></option>
								<?php foreach ( self::ROOM_TYPES as $room ) : ?>
									<option value="<?php echo esc_attr( $room ); ?>"><?php echo esc_html( $room ); ?></option>
								<?php endforeach; ?>
							</select>
						</div>
					</div>

					<div class="travel-pack-booking__actions">
						<button type="submit" class="travel-pack-booking__submit">
							<?php esc_html_e( 'Confirm Booking', 'travel-pack' ); ?>
						</button>
					</div>

					<div class="travel-pack-booking__status" role="status" aria-live="polite"></div>
				</form>
			</div>
		</div>
		<?php
	}

	/**
	 * Turns a departure's remaining/is_full into a display state for the frontend list.
	 * Fill is a 0-100 percentage for the availability bar: 100 when the trip is full
	 * (bar solidly red across); otherwise proportional to remaining seats.
	 *
	 * @return array{state:string,label:string,fill:int}
	 */
	private static function availability_state( $dep ) {
		if ( $dep['is_full'] ) {
			return array(
				'state' => 'full',
				'label' => __( 'FULL', 'travel-pack' ),
				'fill'  => 100,
			);
		}
		$remaining = (int) $dep['remaining'];
		$total     = max( 1, (int) $dep['seats'] );
		$fill      = (int) round( min( 100, max( 8, $remaining / $total * 100 ) ) );
		$state     = $remaining <= 3 ? 'low' : 'ok';
		return array(
			'state' => $state,
			/* translators: %d seats remaining */
			'label' => sprintf( _n( '%d LEFT', '%d LEFT', $remaining, 'travel-pack' ), $remaining ),
			'fill'  => $fill,
		);
	}

	public static function handle_ajax_book() {
		$package_id   = isset( $_POST['package_id'] ) ? (int) $_POST['package_id'] : 0;
		$departure_id = isset( $_POST['departure_id'] ) ? sanitize_key( wp_unslash( $_POST['departure_id'] ) ) : '';
		$seats        = isset( $_POST['seats'] ) ? max( 1, (int) $_POST['seats'] ) : 0;
		$nonce        = isset( $_POST['nonce'] ) ? sanitize_text_field( wp_unslash( $_POST['nonce'] ) ) : '';

		if ( ! $package_id || get_post_type( $package_id ) !== TRAVEL_PACK_POST_TYPE ) {
			wp_send_json_error( array( 'message' => __( 'Invalid package.', 'travel-pack' ) ), 400 );
		}
		if ( ! wp_verify_nonce( $nonce, 'travel_pack_book_' . $package_id ) ) {
			wp_send_json_error( array( 'message' => __( 'Security check failed. Please reload the page and try again.', 'travel-pack' ) ), 400 );
		}

		$name      = isset( $_POST['customer_name'] ) ? sanitize_text_field( wp_unslash( $_POST['customer_name'] ) ) : '';
		$email     = isset( $_POST['customer_email'] ) ? sanitize_email( wp_unslash( $_POST['customer_email'] ) ) : '';
		$room_type = isset( $_POST['room_type'] ) ? sanitize_text_field( wp_unslash( $_POST['room_type'] ) ) : '';

		if ( '' === $name || '' === $email || ! is_email( $email ) ) {
			wp_send_json_error( array( 'message' => __( 'Please provide your name and a valid email.', 'travel-pack' ) ), 400 );
		}
		if ( ! in_array( $room_type, self::ROOM_TYPES, true ) ) {
			wp_send_json_error( array( 'message' => __( 'Please choose a room type.', 'travel-pack' ) ), 400 );
		}
		if ( $seats < 1 ) {
			wp_send_json_error( array( 'message' => __( 'Please choose at least one traveler.', 'travel-pack' ) ), 400 );
		}

		$dep = Travel_Pack_Departures::get_departure( $package_id, $departure_id );
		if ( null === $dep ) {
			wp_send_json_error( array( 'message' => __( 'That departure is no longer available.', 'travel-pack' ) ), 400 );
		}

		// Enforce capacity inside a transaction so two concurrent requests cannot both
		// squeeze in past the last seat.
		global $wpdb;
		$table = self::table_name();

		$wpdb->query( 'START TRANSACTION' );

		// Use the canonical stored departure_id (case as stored in meta) for the
		// booking row + the FOR UPDATE query, and match with LOWER() to absorb any
		// historic case-mismatch between meta and existing bookings.
		$canonical_departure_id = $dep['id'];

		$already_booked = (int) $wpdb->get_var(
			$wpdb->prepare(
				"SELECT COALESCE(SUM(seats),0) FROM {$table} WHERE package_id = %d AND LOWER(departure_id) = LOWER(%s) AND status = 'confirmed' FOR UPDATE",
				$package_id,
				$canonical_departure_id
			)
		);
		$remaining = max( 0, (int) $dep['seats'] - $already_booked );

		if ( $seats > $remaining ) {
			$wpdb->query( 'ROLLBACK' );
			wp_send_json_error(
				array(
					'message'   => sprintf(
						/* translators: %d remaining seats */
						_n( 'Only %d seat is left for this departure.', 'Only %d seats are left for this departure.', $remaining, 'travel-pack' ),
						$remaining
					),
					'remaining' => $remaining,
				),
				409
			);
		}

		$inserted = $wpdb->insert(
			$table,
			array(
				'package_id'     => $package_id,
				'departure_id'   => $canonical_departure_id,
				'seats'          => $seats,
				'room_type'      => $room_type,
				'customer_name'  => $name,
				'customer_email' => $email,
				'status'         => 'confirmed',
				'created_at'     => current_time( 'mysql' ),
			),
			array( '%d', '%s', '%d', '%s', '%s', '%s', '%s', '%s' )
		);

		if ( ! $inserted ) {
			$wpdb->query( 'ROLLBACK' );
			wp_send_json_error( array( 'message' => __( 'Something went wrong saving your booking. Please try again.', 'travel-pack' ) ), 500 );
		}

		$wpdb->query( 'COMMIT' );

		$new_remaining = max( 0, $remaining - $seats );

		do_action( 'travel_pack_booking_created', $wpdb->insert_id, $package_id, $departure_id );

		wp_send_json_success(
			array(
				'message'   => __( 'Thanks! Your booking has been received. We will contact you shortly to confirm details.', 'travel-pack' ),
				'remaining' => $new_remaining,
				'is_full'   => ( $new_remaining <= 0 ),
			)
		);
	}

	public static function render_bookings_page() {
		if ( ! current_user_can( 'edit_posts' ) ) {
			return;
		}

		global $wpdb;
		$table = self::table_name();

		$package_filter = isset( $_GET['package_id'] ) ? (int) $_GET['package_id'] : 0;

		$where = '';
		$args  = array();
		if ( $package_filter ) {
			$where  = 'WHERE package_id = %d';
			$args[] = $package_filter;
		}

		$rows = $wpdb->get_results(
			$args
				? $wpdb->prepare( "SELECT * FROM {$table} {$where} ORDER BY created_at DESC LIMIT 200", $args )
				: "SELECT * FROM {$table} ORDER BY created_at DESC LIMIT 200"
		);

		$packages = get_posts(
			array(
				'post_type'      => TRAVEL_PACK_POST_TYPE,
				'posts_per_page' => -1,
				'orderby'        => 'title',
				'order'          => 'ASC',
			)
		);

		?>
		<div class="wrap travel-pack-bookings">
			<h1><?php esc_html_e( 'Travel Pack — Bookings', 'travel-pack' ); ?></h1>

			<form method="get" class="travel-pack-bookings__filter">
				<input type="hidden" name="page" value="travel-pack-bookings" />
				<label>
					<?php esc_html_e( 'Filter by package:', 'travel-pack' ); ?>
					<select name="package_id" onchange="this.form.submit()">
						<option value="0"><?php esc_html_e( 'All packages', 'travel-pack' ); ?></option>
						<?php foreach ( $packages as $pkg ) : ?>
							<option value="<?php echo esc_attr( $pkg->ID ); ?>" <?php selected( $package_filter, $pkg->ID ); ?>>
								<?php echo esc_html( $pkg->post_title ); ?>
							</option>
						<?php endforeach; ?>
					</select>
				</label>
			</form>

			<table class="wp-list-table widefat striped">
				<thead>
					<tr>
						<th><?php esc_html_e( 'Booked', 'travel-pack' ); ?></th>
						<th><?php esc_html_e( 'Package', 'travel-pack' ); ?></th>
						<th><?php esc_html_e( 'Departure', 'travel-pack' ); ?></th>
						<th><?php esc_html_e( 'Travelers', 'travel-pack' ); ?></th>
						<th><?php esc_html_e( 'Room', 'travel-pack' ); ?></th>
						<th><?php esc_html_e( 'Customer', 'travel-pack' ); ?></th>
						<th><?php esc_html_e( 'Email', 'travel-pack' ); ?></th>
						<th><?php esc_html_e( 'Status', 'travel-pack' ); ?></th>
					</tr>
				</thead>
				<tbody>
					<?php if ( empty( $rows ) ) : ?>
						<tr><td colspan="8"><?php esc_html_e( 'No bookings yet.', 'travel-pack' ); ?></td></tr>
					<?php else : ?>
						<?php foreach ( $rows as $row ) :
							$dep     = Travel_Pack_Departures::get_departure( $row->package_id, $row->departure_id );
							$dep_str = $dep ? mysql2date( get_option( 'date_format' ), $dep['date'] ) : __( '(deleted)', 'travel-pack' );
							?>
							<tr>
								<td><?php echo esc_html( mysql2date( get_option( 'date_format' ) . ' ' . get_option( 'time_format' ), $row->created_at ) ); ?></td>
								<td>
									<a href="<?php echo esc_url( get_edit_post_link( $row->package_id ) ); ?>">
										<?php echo esc_html( get_the_title( $row->package_id ) ); ?>
									</a>
								</td>
								<td><?php echo esc_html( $dep_str ); ?></td>
								<td><?php echo (int) $row->seats; ?></td>
								<td><?php echo esc_html( $row->room_type ); ?></td>
								<td><?php echo esc_html( $row->customer_name ); ?></td>
								<td><a href="mailto:<?php echo esc_attr( $row->customer_email ); ?>"><?php echo esc_html( $row->customer_email ); ?></a></td>
								<td><span class="travel-pack-badge travel-pack-badge--<?php echo esc_attr( $row->status ); ?>"><?php echo esc_html( ucfirst( $row->status ) ); ?></span></td>
							</tr>
						<?php endforeach; ?>
					<?php endif; ?>
				</tbody>
			</table>
		</div>
		<?php
	}
}
