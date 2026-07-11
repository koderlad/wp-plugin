<?php
/**
 * Bookings: DB table, AJAX handler, and admin list screen.
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
	const DB_VERSION        = '1.0.0';

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

		$sql = "CREATE TABLE {$table} (
			id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
			package_id BIGINT UNSIGNED NOT NULL,
			departure_id VARCHAR(32) NOT NULL,
			seats SMALLINT UNSIGNED NOT NULL DEFAULT 1,
			customer_name VARCHAR(191) NOT NULL,
			customer_email VARCHAR(191) NOT NULL,
			customer_phone VARCHAR(64) NOT NULL DEFAULT '',
			message TEXT NOT NULL,
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
	 * Renders the booking form. Called from the frontend template.
	 */
	public static function render_form( $post_id ) {
		$departures = Travel_Pack_Departures::get_upcoming_with_availability( $post_id );
		?>
		<div class="travel-pack-booking" id="travel-pack-booking">
			<h2 class="travel-pack-booking__title"><?php esc_html_e( 'Book This Trip', 'travel-pack' ); ?></h2>

			<?php if ( empty( $departures ) ) : ?>
				<p class="travel-pack-booking__empty"><?php esc_html_e( 'No upcoming departures are open for booking. Please check back later.', 'travel-pack' ); ?></p>
			<?php else : ?>
				<form class="travel-pack-booking__form" method="post">
					<?php wp_nonce_field( 'travel_pack_book_' . $post_id, 'travel_pack_booking_nonce' ); ?>
					<input type="hidden" name="package_id" value="<?php echo esc_attr( $post_id ); ?>" />

					<div class="travel-pack-booking__field">
						<label for="tp_departure"><?php esc_html_e( 'Departure Date', 'travel-pack' ); ?> <span class="required">*</span></label>
						<select id="tp_departure" name="departure_id" required>
							<option value=""><?php esc_html_e( '— Choose a date —', 'travel-pack' ); ?></option>
							<?php foreach ( $departures as $dep ) : ?>
								<option
									value="<?php echo esc_attr( $dep['id'] ); ?>"
									data-remaining="<?php echo esc_attr( $dep['remaining'] ); ?>"
									<?php disabled( $dep['is_full'] ); ?>
								>
									<?php
									echo esc_html( mysql2date( get_option( 'date_format' ), $dep['date'] ) );
									if ( $dep['is_full'] ) {
										echo ' — ' . esc_html__( 'Fully booked', 'travel-pack' );
									} else {
										echo ' — ' . esc_html(
											sprintf(
												/* translators: %d remaining seats */
												_n( '%d seat left', '%d seats left', $dep['remaining'], 'travel-pack' ),
												$dep['remaining']
											)
										);
									}
									?>
								</option>
							<?php endforeach; ?>
						</select>
					</div>

					<div class="travel-pack-booking__row">
						<div class="travel-pack-booking__field">
							<label for="tp_seats"><?php esc_html_e( 'Number of People', 'travel-pack' ); ?> <span class="required">*</span></label>
							<input type="number" id="tp_seats" name="seats" min="1" step="1" value="1" required />
						</div>
						<div class="travel-pack-booking__field">
							<label for="tp_name"><?php esc_html_e( 'Full Name', 'travel-pack' ); ?> <span class="required">*</span></label>
							<input type="text" id="tp_name" name="customer_name" required />
						</div>
					</div>

					<div class="travel-pack-booking__row">
						<div class="travel-pack-booking__field">
							<label for="tp_email"><?php esc_html_e( 'Email', 'travel-pack' ); ?> <span class="required">*</span></label>
							<input type="email" id="tp_email" name="customer_email" required />
						</div>
						<div class="travel-pack-booking__field">
							<label for="tp_phone"><?php esc_html_e( 'Phone', 'travel-pack' ); ?></label>
							<input type="tel" id="tp_phone" name="customer_phone" />
						</div>
					</div>

					<div class="travel-pack-booking__field">
						<label for="tp_message"><?php esc_html_e( 'Special Requests', 'travel-pack' ); ?></label>
						<textarea id="tp_message" name="message" rows="3"></textarea>
					</div>

					<div class="travel-pack-booking__actions">
						<button type="submit" class="travel-pack-booking__submit">
							<?php esc_html_e( 'Book Now', 'travel-pack' ); ?>
						</button>
					</div>

					<div class="travel-pack-booking__status" role="status" aria-live="polite"></div>
				</form>
			<?php endif; ?>
		</div>
		<?php
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

		$name    = isset( $_POST['customer_name'] ) ? sanitize_text_field( wp_unslash( $_POST['customer_name'] ) ) : '';
		$email   = isset( $_POST['customer_email'] ) ? sanitize_email( wp_unslash( $_POST['customer_email'] ) ) : '';
		$phone   = isset( $_POST['customer_phone'] ) ? sanitize_text_field( wp_unslash( $_POST['customer_phone'] ) ) : '';
		$message = isset( $_POST['message'] ) ? sanitize_textarea_field( wp_unslash( $_POST['message'] ) ) : '';

		if ( '' === $name || '' === $email || ! is_email( $email ) ) {
			wp_send_json_error( array( 'message' => __( 'Please provide your name and a valid email.', 'travel-pack' ) ), 400 );
		}
		if ( $seats < 1 ) {
			wp_send_json_error( array( 'message' => __( 'Please choose at least one seat.', 'travel-pack' ) ), 400 );
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

		$already_booked = (int) $wpdb->get_var(
			$wpdb->prepare(
				"SELECT COALESCE(SUM(seats),0) FROM {$table} WHERE package_id = %d AND departure_id = %s AND status = 'confirmed' FOR UPDATE",
				$package_id,
				$departure_id
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
				'departure_id'   => $departure_id,
				'seats'          => $seats,
				'customer_name'  => $name,
				'customer_email' => $email,
				'customer_phone' => $phone,
				'message'        => $message,
				'status'         => 'confirmed',
				'created_at'     => current_time( 'mysql' ),
			),
			array( '%d', '%s', '%d', '%s', '%s', '%s', '%s', '%s', '%s' )
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
						<th><?php esc_html_e( 'Seats', 'travel-pack' ); ?></th>
						<th><?php esc_html_e( 'Customer', 'travel-pack' ); ?></th>
						<th><?php esc_html_e( 'Contact', 'travel-pack' ); ?></th>
						<th><?php esc_html_e( 'Status', 'travel-pack' ); ?></th>
					</tr>
				</thead>
				<tbody>
					<?php if ( empty( $rows ) ) : ?>
						<tr><td colspan="7"><?php esc_html_e( 'No bookings yet.', 'travel-pack' ); ?></td></tr>
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
								<td><?php echo esc_html( $row->customer_name ); ?></td>
								<td>
									<a href="mailto:<?php echo esc_attr( $row->customer_email ); ?>"><?php echo esc_html( $row->customer_email ); ?></a>
									<?php if ( $row->customer_phone ) : ?>
										<br /><?php echo esc_html( $row->customer_phone ); ?>
									<?php endif; ?>
								</td>
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
