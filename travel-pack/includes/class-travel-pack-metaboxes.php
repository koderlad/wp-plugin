<?php
/**
 * Package metaboxes: fixed fields, Included / Not Included / Important Notes lists, and Itinerary.
 *
 * @package TravelPack
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Travel_Pack_Metaboxes {

	const META_DURATION      = '_travel_pack_duration';
	const META_GROUP_SIZE    = '_travel_pack_group_size';
	const META_PRICE         = '_travel_pack_price';
	const META_TERRAIN       = '_travel_pack_terrain';
	const META_SEASON        = '_travel_pack_season';
	const META_DIFFICULTY    = '_travel_pack_difficulty';
	const META_INCLUDED      = '_travel_pack_included';
	const META_NOT_INCLUDED  = '_travel_pack_not_included';
	const META_IMPORTANT     = '_travel_pack_important_notes';
	const META_ITINERARY     = '_travel_pack_itinerary';

	public static function init() {
		add_action( 'add_meta_boxes', array( __CLASS__, 'register' ) );
		add_action( 'save_post_' . TRAVEL_PACK_POST_TYPE, array( __CLASS__, 'save' ), 10, 2 );
	}

	public static function register() {
		add_meta_box(
			'travel_pack_details',
			__( 'Package Details', 'travel-pack' ),
			array( __CLASS__, 'render_details' ),
			TRAVEL_PACK_POST_TYPE,
			'normal',
			'high'
		);

		add_meta_box(
			'travel_pack_included',
			__( 'What\'s Included', 'travel-pack' ),
			array( __CLASS__, 'render_included' ),
			TRAVEL_PACK_POST_TYPE,
			'normal',
			'default'
		);

		add_meta_box(
			'travel_pack_not_included',
			__( 'What\'s Not Included', 'travel-pack' ),
			array( __CLASS__, 'render_not_included' ),
			TRAVEL_PACK_POST_TYPE,
			'normal',
			'default'
		);

		add_meta_box(
			'travel_pack_important',
			__( 'Important Notes', 'travel-pack' ),
			array( __CLASS__, 'render_important' ),
			TRAVEL_PACK_POST_TYPE,
			'normal',
			'default'
		);

		add_meta_box(
			'travel_pack_itinerary',
			__( 'Itinerary', 'travel-pack' ),
			array( __CLASS__, 'render_itinerary' ),
			TRAVEL_PACK_POST_TYPE,
			'normal',
			'default'
		);
	}

	public static function render_details( $post ) {
		wp_nonce_field( 'travel_pack_save_package', 'travel_pack_package_nonce' );

		$duration   = get_post_meta( $post->ID, self::META_DURATION, true );
		$group_size = get_post_meta( $post->ID, self::META_GROUP_SIZE, true );
		$price      = get_post_meta( $post->ID, self::META_PRICE, true );
		$terrain    = get_post_meta( $post->ID, self::META_TERRAIN, true );
		$season     = get_post_meta( $post->ID, self::META_SEASON, true );
		$difficulty = get_post_meta( $post->ID, self::META_DIFFICULTY, true );

		$group_sizes  = Travel_Pack_Settings::get_group_sizes();
		$seasons      = Travel_Pack_Settings::get_seasons();
		$difficulties = Travel_Pack_Settings::get_difficulties();
		?>
		<div class="travel-pack-details-grid">
			<p>
				<label for="travel_pack_duration"><strong><?php esc_html_e( 'Duration', 'travel-pack' ); ?></strong></label>
				<input type="text" id="travel_pack_duration" name="travel_pack_duration" class="widefat" value="<?php echo esc_attr( $duration ); ?>" placeholder="<?php esc_attr_e( 'e.g. 14 Days / 13 Nights', 'travel-pack' ); ?>" />
			</p>

			<p>
				<label for="travel_pack_group_size"><strong><?php esc_html_e( 'Group Size', 'travel-pack' ); ?></strong></label>
				<?php self::render_select( 'travel_pack_group_size', $group_sizes, $group_size ); ?>
				<span class="travel-pack-hint">
					<?php
					printf(
						/* translators: %s link */
						wp_kses_post( __( 'Manage options in <a href="%s">Settings</a>.', 'travel-pack' ) ),
						esc_url( admin_url( 'admin.php?page=travel-pack-settings' ) )
					);
					?>
				</span>
			</p>

			<p>
				<label for="travel_pack_price"><strong><?php esc_html_e( 'Price', 'travel-pack' ); ?></strong></label>
				<input type="text" id="travel_pack_price" name="travel_pack_price" class="widefat" value="<?php echo esc_attr( $price ); ?>" placeholder="<?php esc_attr_e( 'e.g. $1,499 per person', 'travel-pack' ); ?>" />
			</p>

			<p>
				<label for="travel_pack_terrain"><strong><?php esc_html_e( 'Terrain', 'travel-pack' ); ?></strong></label>
				<input type="text" id="travel_pack_terrain" name="travel_pack_terrain" class="widefat" value="<?php echo esc_attr( $terrain ); ?>" placeholder="<?php esc_attr_e( 'e.g. Mountains, Glaciers, Alpine forests', 'travel-pack' ); ?>" />
			</p>

			<p>
				<label for="travel_pack_season"><strong><?php esc_html_e( 'Season', 'travel-pack' ); ?></strong></label>
				<?php self::render_select( 'travel_pack_season', $seasons, $season ); ?>
			</p>

			<p>
				<label for="travel_pack_difficulty"><strong><?php esc_html_e( 'Difficulty', 'travel-pack' ); ?></strong></label>
				<?php self::render_select( 'travel_pack_difficulty', $difficulties, $difficulty ); ?>
			</p>
		</div>
		<?php
	}

	private static function render_select( $name, $options, $current ) {
		echo '<select id="' . esc_attr( $name ) . '" name="' . esc_attr( $name ) . '" class="widefat">';
		echo '<option value="">' . esc_html__( '— Select —', 'travel-pack' ) . '</option>';
		foreach ( $options as $option ) {
			printf(
				'<option value="%1$s" %2$s>%1$s</option>',
				esc_attr( $option ),
				selected( $current, $option, false )
			);
		}
		echo '</select>';
	}

	public static function render_included( $post ) {
		$items = get_post_meta( $post->ID, self::META_INCLUDED, true );
		self::render_notes_list( 'included', $items, __( 'Add New Note', 'travel-pack' ), __( 'Airport transfers, all meals, accommodation…', 'travel-pack' ) );
	}

	public static function render_not_included( $post ) {
		$items = get_post_meta( $post->ID, self::META_NOT_INCLUDED, true );
		self::render_notes_list( 'not_included', $items, __( 'Add New Note', 'travel-pack' ), __( 'International flights, tips, personal expenses…', 'travel-pack' ) );
	}

	public static function render_important( $post ) {
		$items = get_post_meta( $post->ID, self::META_IMPORTANT, true );
		self::render_notes_list( 'important', $items, __( 'Add New Note', 'travel-pack' ), __( 'A note that travellers should read carefully…', 'travel-pack' ) );
	}

	private static function render_notes_list( $key, $items, $button_label, $placeholder ) {
		$items = is_array( $items ) ? $items : array();
		?>
		<div class="travel-pack-repeater" data-key="<?php echo esc_attr( $key ); ?>">
			<div class="travel-pack-repeater__items">
				<?php foreach ( $items as $item ) : ?>
					<div class="travel-pack-repeater__item">
						<button type="button" class="travel-pack-repeater__remove" aria-label="<?php esc_attr_e( 'Remove note', 'travel-pack' ); ?>">×</button>
						<textarea name="travel_pack_<?php echo esc_attr( $key ); ?>[]" rows="2" placeholder="<?php echo esc_attr( $placeholder ); ?>"><?php echo esc_textarea( $item ); ?></textarea>
					</div>
				<?php endforeach; ?>
			</div>
			<button type="button" class="button button-secondary travel-pack-repeater__add" data-key="<?php echo esc_attr( $key ); ?>" data-placeholder="<?php echo esc_attr( $placeholder ); ?>">
				+ <?php echo esc_html( $button_label ); ?>
			</button>
		</div>
		<?php
	}

	public static function render_itinerary( $post ) {
		$items = get_post_meta( $post->ID, self::META_ITINERARY, true );
		$items = is_array( $items ) ? $items : array();
		?>
		<p class="travel-pack-hint travel-pack-hint--callout">
			<?php esc_html_e( 'Each itinerary entry maps to one or more days. The counter is how many days that entry covers. Example: entry 1 has counter 3 (Day 1-3), entry 2 has counter 1 (Day 4), and so on.', 'travel-pack' ); ?>
		</p>
		<div class="travel-pack-repeater travel-pack-itinerary" data-key="itinerary">
			<div class="travel-pack-repeater__items">
				<?php
				foreach ( $items as $item ) :
					$text = isset( $item['text'] ) ? $item['text'] : '';
					$days = isset( $item['days'] ) ? max( 1, (int) $item['days'] ) : 1;
					?>
					<div class="travel-pack-repeater__item travel-pack-itinerary__item">
						<button type="button" class="travel-pack-repeater__remove" aria-label="<?php esc_attr_e( 'Remove itinerary item', 'travel-pack' ); ?>">×</button>
						<div class="travel-pack-itinerary__row">
							<div class="travel-pack-counter">
								<label><?php esc_html_e( 'Days', 'travel-pack' ); ?></label>
								<div class="travel-pack-counter__control">
									<button type="button" class="travel-pack-counter__btn" data-action="decrement">−</button>
									<input type="number" name="travel_pack_itinerary_days[]" min="1" step="1" value="<?php echo esc_attr( $days ); ?>" />
									<button type="button" class="travel-pack-counter__btn" data-action="increment">+</button>
								</div>
							</div>
							<div class="travel-pack-itinerary__text">
								<label><?php esc_html_e( 'Description', 'travel-pack' ); ?></label>
								<textarea name="travel_pack_itinerary_text[]" rows="3" placeholder="<?php esc_attr_e( 'What happens on these days…', 'travel-pack' ); ?>"><?php echo esc_textarea( $text ); ?></textarea>
							</div>
						</div>
					</div>
				<?php endforeach; ?>
			</div>
			<button type="button" class="button button-secondary travel-pack-repeater__add" data-key="itinerary">
				+ <?php esc_html_e( 'Add New Itinerary', 'travel-pack' ); ?>
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

		// Fixed text fields.
		$text_map = array(
			'travel_pack_duration' => self::META_DURATION,
			'travel_pack_price'    => self::META_PRICE,
			'travel_pack_terrain'  => self::META_TERRAIN,
		);
		foreach ( $text_map as $post_key => $meta_key ) {
			$value = isset( $_POST[ $post_key ] ) ? sanitize_text_field( wp_unslash( $_POST[ $post_key ] ) ) : '';
			update_post_meta( $post_id, $meta_key, $value );
		}

		// Dropdowns - only accept values that exist in settings so tampered POSTs cannot
		// inject arbitrary strings.
		$dropdown_map = array(
			'travel_pack_group_size' => array( self::META_GROUP_SIZE, Travel_Pack_Settings::get_group_sizes() ),
			'travel_pack_season'     => array( self::META_SEASON, Travel_Pack_Settings::get_seasons() ),
			'travel_pack_difficulty' => array( self::META_DIFFICULTY, Travel_Pack_Settings::get_difficulties() ),
		);
		foreach ( $dropdown_map as $post_key => $conf ) {
			list( $meta_key, $allowed ) = $conf;
			$value = isset( $_POST[ $post_key ] ) ? sanitize_text_field( wp_unslash( $_POST[ $post_key ] ) ) : '';
			if ( '' !== $value && ! in_array( $value, $allowed, true ) ) {
				$value = '';
			}
			update_post_meta( $post_id, $meta_key, $value );
		}

		// Notes lists.
		$list_map = array(
			'travel_pack_included'     => self::META_INCLUDED,
			'travel_pack_not_included' => self::META_NOT_INCLUDED,
			'travel_pack_important'    => self::META_IMPORTANT,
		);
		foreach ( $list_map as $post_key => $meta_key ) {
			$raw   = isset( $_POST[ $post_key ] ) ? (array) wp_unslash( $_POST[ $post_key ] ) : array();
			$clean = array();
			foreach ( $raw as $note ) {
				$note = trim( wp_kses_post( $note ) );
				if ( '' !== $note ) {
					$clean[] = $note;
				}
			}
			update_post_meta( $post_id, $meta_key, $clean );
		}

		// Itinerary - text[] and days[] arrays are matched by index.
		$texts = isset( $_POST['travel_pack_itinerary_text'] ) ? (array) wp_unslash( $_POST['travel_pack_itinerary_text'] ) : array();
		$days  = isset( $_POST['travel_pack_itinerary_days'] ) ? (array) wp_unslash( $_POST['travel_pack_itinerary_days'] ) : array();
		$itinerary = array();
		foreach ( $texts as $i => $text ) {
			$text = trim( wp_kses_post( $text ) );
			if ( '' === $text ) {
				continue;
			}
			$day_count = isset( $days[ $i ] ) ? max( 1, (int) $days[ $i ] ) : 1;
			$itinerary[] = array(
				'text' => $text,
				'days' => $day_count,
			);
		}
		update_post_meta( $post_id, self::META_ITINERARY, $itinerary );
	}

	/**
	 * Reads the itinerary meta and adds computed day range labels ("Day 1", "Day 4-6").
	 * The stored counter is the number of days the entry covers; ranges are recalculated
	 * on read so that adjusting an earlier entry does not require rewriting the whole list.
	 *
	 * @return array<int,array{text:string,days:int,label:string,start:int,end:int}>
	 */
	public static function get_itinerary_with_labels( $post_id ) {
		$items = get_post_meta( $post_id, self::META_ITINERARY, true );
		if ( ! is_array( $items ) ) {
			return array();
		}
		$cursor = 1;
		$result = array();
		foreach ( $items as $item ) {
			$days = isset( $item['days'] ) ? max( 1, (int) $item['days'] ) : 1;
			$text = isset( $item['text'] ) ? (string) $item['text'] : '';
			$start = $cursor;
			$end   = $cursor + $days - 1;
			$label = ( $days === 1 )
				? sprintf( __( 'Day %d', 'travel-pack' ), $start )
				: sprintf( __( 'Day %1$d-%2$d', 'travel-pack' ), $start, $end );
			$result[] = array(
				'text'  => $text,
				'days'  => $days,
				'label' => $label,
				'start' => $start,
				'end'   => $end,
			);
			$cursor = $end + 1;
		}
		return $result;
	}
}
