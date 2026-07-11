<?php
/**
 * Departure dates metabox and helpers.
 *
 * Each departure has a unique id, a date, and a seat capacity. Seats booked are
 * tracked in the bookings table so remaining seats = capacity - SUM(seats booked).
 *
 * @package TravelPack
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Travel_Pack_Departures {

	const META_DEPARTURES = '_travel_pack_departures';

	public static function init() {
		add_action( 'add_meta_boxes', array( __CLASS__, 'register' ) );
		add_action( 'save_post_' . TRAVEL_PACK_POST_TYPE, array( __CLASS__, 'save' ), 10, 2 );
	}

	public static function register() {
		add_meta_box(
			'travel_pack_departures',
			__( 'Departure Dates', 'travel-pack' ),
			array( __CLASS__, 'render' ),
			TRAVEL_PACK_POST_TYPE,
			'normal',
			'default'
		);
	}

	public static function render( $post ) {
		$departures = self::get_departures( $post->ID );
		?>
		<p class="travel-pack-hint travel-pack-hint--callout">
			<?php esc_html_e( 'Add each departure date and the number of seats available. When someone books through the site, the seat count is reduced automatically. A departure with 0 remaining seats will show as "Fully booked".', 'travel-pack' ); ?>
		</p>
		<div class="travel-pack-repeater travel-pack-departures" data-key="departures">
			<div class="travel-pack-repeater__items">
				<?php
				foreach ( $departures as $dep ) :
					$booked    = self::get_booked_seats( $post->ID, $dep['id'] );
					$remaining = max( 0, (int) $dep['seats'] - $booked );
					?>
					<div class="travel-pack-repeater__item travel-pack-departure">
						<button type="button" class="travel-pack-repeater__remove" aria-label="<?php esc_attr_e( 'Remove departure', 'travel-pack' ); ?>">×</button>
						<input type="hidden" name="travel_pack_departure_id[]" value="<?php echo esc_attr( $dep['id'] ); ?>" />
						<div class="travel-pack-departure__row">
							<div class="travel-pack-departure__field">
								<label><?php esc_html_e( 'Departure Date', 'travel-pack' ); ?></label>
								<input type="date" name="travel_pack_departure_date[]" value="<?php echo esc_attr( $dep['date'] ); ?>" required />
							</div>
							<div class="travel-pack-departure__field">
								<label><?php esc_html_e( 'Total Seats', 'travel-pack' ); ?></label>
								<input type="number" min="0" step="1" name="travel_pack_departure_seats[]" value="<?php echo esc_attr( $dep['seats'] ); ?>" />
							</div>
							<div class="travel-pack-departure__field travel-pack-departure__status">
								<label><?php esc_html_e( 'Status', 'travel-pack' ); ?></label>
								<?php if ( $remaining <= 0 && (int) $dep['seats'] > 0 ) : ?>
									<span class="travel-pack-badge travel-pack-badge--full"><?php esc_html_e( 'Fully booked', 'travel-pack' ); ?></span>
								<?php else : ?>
									<span class="travel-pack-badge travel-pack-badge--ok">
										<?php
										printf(
											/* translators: 1: remaining, 2: total */
											esc_html__( '%1$d / %2$d seats left', 'travel-pack' ),
											(int) $remaining,
											(int) $dep['seats']
										);
										?>
									</span>
								<?php endif; ?>
							</div>
						</div>
					</div>
				<?php endforeach; ?>
			</div>
			<button type="button" class="button button-secondary travel-pack-repeater__add" data-key="departures">
				+ <?php esc_html_e( 'Add Departure', 'travel-pack' ); ?>
			</button>
		</div>
		<?php
	}

	public static function save( $post_id, $post ) {
		if ( ! isset( $_POST['travel_pack_package_nonce'] ) ) {
			return;
		}
		if ( ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['travel_pack_package_nonce'] ) ), 'travel_pack_save_package' ) ) {
			return;
		}
		if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) {
			return;
		}
		if ( ! current_user_can( 'edit_post', $post_id ) ) {
			return;
		}

		$ids   = isset( $_POST['travel_pack_departure_id'] ) ? (array) wp_unslash( $_POST['travel_pack_departure_id'] ) : array();
		$dates = isset( $_POST['travel_pack_departure_date'] ) ? (array) wp_unslash( $_POST['travel_pack_departure_date'] ) : array();
		$seats = isset( $_POST['travel_pack_departure_seats'] ) ? (array) wp_unslash( $_POST['travel_pack_departure_seats'] ) : array();

		$departures = array();
		foreach ( $dates as $i => $date ) {
			$date = sanitize_text_field( $date );
			if ( '' === $date || ! self::is_iso_date( $date ) ) {
				continue;
			}
			$id_raw = isset( $ids[ $i ] ) ? sanitize_key( $ids[ $i ] ) : '';
			// Preserve stored IDs across saves so bookings continue to reference the same departure.
			$id = $id_raw ? $id_raw : self::generate_id();
			$departures[] = array(
				'id'    => $id,
				'date'  => $date,
				'seats' => isset( $seats[ $i ] ) ? max( 0, (int) $seats[ $i ] ) : 0,
			);
		}

		update_post_meta( $post_id, self::META_DEPARTURES, $departures );
	}

	/**
	 * Returns all departures on this package, sorted by date ascending.
	 *
	 * @return array<int,array{id:string,date:string,seats:int}>
	 */
	public static function get_departures( $post_id ) {
		$saved = get_post_meta( $post_id, self::META_DEPARTURES, true );
		if ( ! is_array( $saved ) ) {
			return array();
		}
		usort(
			$saved,
			static function ( $a, $b ) {
				$da = isset( $a['date'] ) ? $a['date'] : '';
				$db = isset( $b['date'] ) ? $b['date'] : '';
				return strcmp( $da, $db );
			}
		);
		return $saved;
	}

	public static function get_departure( $post_id, $departure_id ) {
		foreach ( self::get_departures( $post_id ) as $dep ) {
			if ( $dep['id'] === $departure_id ) {
				return $dep;
			}
		}
		return null;
	}

	/**
	 * Sums seats already booked against this departure. Only 'confirmed' bookings
	 * count towards the total; cancelled/failed bookings free up their seats.
	 */
	public static function get_booked_seats( $post_id, $departure_id ) {
		global $wpdb;
		$table = Travel_Pack_Booking::table_name();
		$sum   = $wpdb->get_var(
			$wpdb->prepare(
				"SELECT COALESCE(SUM(seats),0) FROM {$table} WHERE package_id = %d AND departure_id = %s AND status = 'confirmed'",
				$post_id,
				$departure_id
			)
		);
		return (int) $sum;
	}

	public static function get_remaining_seats( $post_id, $departure_id ) {
		$dep = self::get_departure( $post_id, $departure_id );
		if ( null === $dep ) {
			return 0;
		}
		return max( 0, (int) $dep['seats'] - self::get_booked_seats( $post_id, $departure_id ) );
	}

	/**
	 * Upcoming departures only - past dates are still stored (for reporting) but hidden
	 * from the frontend to avoid users trying to book yesterday's trip.
	 */
	public static function get_upcoming_with_availability( $post_id ) {
		$today  = current_time( 'Y-m-d' );
		$result = array();
		foreach ( self::get_departures( $post_id ) as $dep ) {
			if ( $dep['date'] < $today ) {
				continue;
			}
			$booked    = self::get_booked_seats( $post_id, $dep['id'] );
			$remaining = max( 0, (int) $dep['seats'] - $booked );
			$dep['booked']    = $booked;
			$dep['remaining'] = $remaining;
			$dep['is_full']   = ( (int) $dep['seats'] > 0 && $remaining <= 0 );
			$result[] = $dep;
		}
		return $result;
	}

	private static function generate_id() {
		return 'd_' . wp_generate_password( 10, false, false );
	}

	private static function is_iso_date( $value ) {
		return (bool) preg_match( '/^\d{4}-\d{2}-\d{2}$/', $value );
	}
}
