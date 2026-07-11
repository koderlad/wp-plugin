<?php
/**
 * Manages dropdown value settings (Group Size, Season, Difficulty).
 *
 * These live as options in wp_options rather than as taxonomies because they are
 * simple flat lists that travel agents will manage from a single settings screen.
 *
 * @package TravelPack
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Travel_Pack_Settings {

	const OPTION_GROUP_SIZE = 'travel_pack_group_sizes';
	const OPTION_SEASON     = 'travel_pack_seasons';
	const OPTION_DIFFICULTY = 'travel_pack_difficulties';

	public static function init() {
		add_action( 'admin_init', array( __CLASS__, 'handle_save' ) );
	}

	/**
	 * Populates the dropdowns with sensible defaults on first activation
	 * so the agent isn't looking at an empty select before they've configured anything.
	 */
	public static function seed_defaults() {
		if ( false === get_option( self::OPTION_GROUP_SIZE, false ) ) {
			update_option( self::OPTION_GROUP_SIZE, array( '1-2', '3-5', '6-10', '11-15', '16+' ) );
		}
		if ( false === get_option( self::OPTION_SEASON, false ) ) {
			update_option( self::OPTION_SEASON, array( 'Spring', 'Summer', 'Autumn', 'Winter', 'All Year' ) );
		}
		if ( false === get_option( self::OPTION_DIFFICULTY, false ) ) {
			update_option( self::OPTION_DIFFICULTY, array( 'Easy', 'Moderate', 'Challenging', 'Strenuous', 'Extreme' ) );
		}
	}

	public static function get_group_sizes() {
		$values = get_option( self::OPTION_GROUP_SIZE, array() );
		return is_array( $values ) ? $values : array();
	}

	public static function get_seasons() {
		$values = get_option( self::OPTION_SEASON, array() );
		return is_array( $values ) ? $values : array();
	}

	public static function get_difficulties() {
		$values = get_option( self::OPTION_DIFFICULTY, array() );
		return is_array( $values ) ? $values : array();
	}

	/**
	 * Processes the settings form submission. Sanitizes and dedupes every value.
	 */
	public static function handle_save() {
		if ( ! isset( $_POST['travel_pack_settings_nonce'] ) ) {
			return;
		}
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}
		if ( ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['travel_pack_settings_nonce'] ) ), 'travel_pack_save_settings' ) ) {
			return;
		}

		$fields = array(
			'group_sizes'  => self::OPTION_GROUP_SIZE,
			'seasons'      => self::OPTION_SEASON,
			'difficulties' => self::OPTION_DIFFICULTY,
		);

		foreach ( $fields as $post_key => $option_name ) {
			$raw = isset( $_POST[ $post_key ] ) ? wp_unslash( $_POST[ $post_key ] ) : array();
			if ( ! is_array( $raw ) ) {
				$raw = array();
			}
			$clean = array();
			foreach ( $raw as $value ) {
				$value = sanitize_text_field( $value );
				if ( '' !== $value && ! in_array( $value, $clean, true ) ) {
					$clean[] = $value;
				}
			}
			update_option( $option_name, $clean );
		}

		add_settings_error(
			'travel_pack_settings',
			'travel_pack_saved',
			__( 'Dropdown values saved.', 'travel-pack' ),
			'updated'
		);
	}

	/**
	 * Renders the Settings screen.
	 */
	public static function render_page() {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}

		$group_sizes  = self::get_group_sizes();
		$seasons      = self::get_seasons();
		$difficulties = self::get_difficulties();

		?>
		<div class="wrap travel-pack-settings">
			<h1><?php esc_html_e( 'Travel Pack — Dropdown Values', 'travel-pack' ); ?></h1>
			<p class="description">
				<?php esc_html_e( 'Manage the options that appear in the Group Size, Season, and Difficulty dropdowns when editing a package. Add or remove values as needed.', 'travel-pack' ); ?>
			</p>
			<?php settings_errors( 'travel_pack_settings' ); ?>
			<form method="post" action="">
				<?php wp_nonce_field( 'travel_pack_save_settings', 'travel_pack_settings_nonce' ); ?>

				<div class="travel-pack-settings-grid">
					<?php
					self::render_list_editor( __( 'Group Size Options', 'travel-pack' ), 'group_sizes', $group_sizes, __( 'e.g. 1-2, 3-5, 6-10', 'travel-pack' ) );
					self::render_list_editor( __( 'Season Options', 'travel-pack' ), 'seasons', $seasons, __( 'e.g. Spring, Summer, Autumn, Winter', 'travel-pack' ) );
					self::render_list_editor( __( 'Difficulty Options', 'travel-pack' ), 'difficulties', $difficulties, __( 'e.g. Easy, Moderate, Challenging', 'travel-pack' ) );
					?>
				</div>

				<p class="submit">
					<button type="submit" class="button button-primary button-hero">
						<?php esc_html_e( 'Save Changes', 'travel-pack' ); ?>
					</button>
				</p>
			</form>
		</div>
		<?php
	}

	private static function render_list_editor( $title, $field_key, $values, $placeholder ) {
		?>
		<div class="travel-pack-list-editor" data-field="<?php echo esc_attr( $field_key ); ?>">
			<h2><?php echo esc_html( $title ); ?></h2>
			<ul class="travel-pack-list-editor__items">
				<?php foreach ( $values as $value ) : ?>
					<li class="travel-pack-list-editor__item">
						<span class="travel-pack-drag" aria-hidden="true">☰</span>
						<input type="text" name="<?php echo esc_attr( $field_key ); ?>[]" value="<?php echo esc_attr( $value ); ?>" placeholder="<?php echo esc_attr( $placeholder ); ?>" />
						<button type="button" class="button-link-delete travel-pack-remove-row" aria-label="<?php esc_attr_e( 'Remove', 'travel-pack' ); ?>">×</button>
					</li>
				<?php endforeach; ?>
			</ul>
			<button type="button" class="button travel-pack-add-row" data-field="<?php echo esc_attr( $field_key ); ?>" data-placeholder="<?php echo esc_attr( $placeholder ); ?>">
				+ <?php esc_html_e( 'Add value', 'travel-pack' ); ?>
			</button>
		</div>
		<?php
	}
}
