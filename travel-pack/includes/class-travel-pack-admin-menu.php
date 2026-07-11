<?php
/**
 * Builds the Travel Pack admin menu (a single top-level menu that hosts the
 * post type, taxonomies, dropdown settings, and bookings) and enqueues admin assets.
 *
 * Everything lives under one menu so the travel agent has a single, obvious place
 * to work from.
 *
 * @package TravelPack
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Travel_Pack_Admin_Menu {

	public static function init() {
		add_action( 'admin_menu', array( __CLASS__, 'register_menu' ), 9 );
		add_action( 'admin_enqueue_scripts', array( __CLASS__, 'enqueue_assets' ) );
	}

	public static function register_menu() {
		add_menu_page(
			__( 'Travel Pack', 'travel-pack' ),
			__( 'Travel Pack', 'travel-pack' ),
			'edit_posts',
			'travel-pack',
			array( __CLASS__, 'render_dashboard' ),
			'dashicons-palmtree',
			20
		);

		add_submenu_page(
			'travel-pack',
			__( 'Dashboard', 'travel-pack' ),
			__( 'Dashboard', 'travel-pack' ),
			'edit_posts',
			'travel-pack',
			array( __CLASS__, 'render_dashboard' )
		);

		// All Packages / Add New come from the CPT registration (show_in_menu=travel-pack).
		// Taxonomies too.

		add_submenu_page(
			'travel-pack',
			__( 'Bookings', 'travel-pack' ),
			__( 'Bookings', 'travel-pack' ),
			'edit_posts',
			'travel-pack-bookings',
			array( 'Travel_Pack_Booking', 'render_bookings_page' )
		);

		add_submenu_page(
			'travel-pack',
			__( 'Dropdown Settings', 'travel-pack' ),
			__( 'Dropdown Settings', 'travel-pack' ),
			'manage_options',
			'travel-pack-settings',
			array( 'Travel_Pack_Settings', 'render_page' )
		);
	}

	public static function render_dashboard() {
		$counts = wp_count_posts( TRAVEL_PACK_POST_TYPE );
		$published = isset( $counts->publish ) ? (int) $counts->publish : 0;
		$drafts    = isset( $counts->draft ) ? (int) $counts->draft : 0;

		global $wpdb;
		$table = Travel_Pack_Booking::table_name();
		$total_bookings = (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$table} WHERE status = 'confirmed'" );
		$total_seats    = (int) $wpdb->get_var( "SELECT COALESCE(SUM(seats),0) FROM {$table} WHERE status = 'confirmed'" );
		?>
		<div class="wrap travel-pack-dashboard">
			<h1><?php esc_html_e( 'Travel Pack', 'travel-pack' ); ?></h1>
			<p class="travel-pack-dashboard__lead">
				<?php esc_html_e( 'Manage travel destinations, departures, itineraries and bookings — all in one place.', 'travel-pack' ); ?>
			</p>

			<div class="travel-pack-cards">
				<div class="travel-pack-card">
					<h2><?php echo esc_html( $published ); ?></h2>
					<p><?php esc_html_e( 'Published Packages', 'travel-pack' ); ?></p>
				</div>
				<div class="travel-pack-card">
					<h2><?php echo esc_html( $drafts ); ?></h2>
					<p><?php esc_html_e( 'Draft Packages', 'travel-pack' ); ?></p>
				</div>
				<div class="travel-pack-card">
					<h2><?php echo esc_html( $total_bookings ); ?></h2>
					<p><?php esc_html_e( 'Total Bookings', 'travel-pack' ); ?></p>
				</div>
				<div class="travel-pack-card">
					<h2><?php echo esc_html( $total_seats ); ?></h2>
					<p><?php esc_html_e( 'Seats Booked', 'travel-pack' ); ?></p>
				</div>
			</div>

			<h2><?php esc_html_e( 'Quick Actions', 'travel-pack' ); ?></h2>
			<div class="travel-pack-actions">
				<a class="button button-primary button-hero" href="<?php echo esc_url( admin_url( 'post-new.php?post_type=' . TRAVEL_PACK_POST_TYPE ) ); ?>">
					+ <?php esc_html_e( 'Create New Package', 'travel-pack' ); ?>
				</a>
				<a class="button button-hero" href="<?php echo esc_url( admin_url( 'edit.php?post_type=' . TRAVEL_PACK_POST_TYPE ) ); ?>">
					<?php esc_html_e( 'Manage Packages', 'travel-pack' ); ?>
				</a>
				<a class="button button-hero" href="<?php echo esc_url( admin_url( 'admin.php?page=travel-pack-bookings' ) ); ?>">
					<?php esc_html_e( 'View Bookings', 'travel-pack' ); ?>
				</a>
				<a class="button button-hero" href="<?php echo esc_url( admin_url( 'admin.php?page=travel-pack-settings' ) ); ?>">
					<?php esc_html_e( 'Dropdown Settings', 'travel-pack' ); ?>
				</a>
			</div>

			<h2><?php esc_html_e( 'Getting Started', 'travel-pack' ); ?></h2>
			<ol class="travel-pack-steps">
				<li>
					<strong><?php esc_html_e( 'Set your dropdown values.', 'travel-pack' ); ?></strong>
					<?php esc_html_e( 'Open Dropdown Settings and choose the Group Size, Season and Difficulty options you use.', 'travel-pack' ); ?>
				</li>
				<li>
					<strong><?php esc_html_e( 'Create a package.', 'travel-pack' ); ?></strong>
					<?php esc_html_e( 'Give it a title, cover photo, description, categories and tags. Fill in Duration, Group Size, Price, Terrain, Season and Difficulty.', 'travel-pack' ); ?>
				</li>
				<li>
					<strong><?php esc_html_e( 'Add itinerary items.', 'travel-pack' ); ?></strong>
					<?php esc_html_e( 'Each item covers 1 or more days. Use the + / − counter to spread an item across multiple days.', 'travel-pack' ); ?>
				</li>
				<li>
					<strong><?php esc_html_e( 'Add Included, Not Included and Important Notes.', 'travel-pack' ); ?></strong>
					<?php esc_html_e( 'Click "Add New Note" in each block for as many entries as you need.', 'travel-pack' ); ?>
				</li>
				<li>
					<strong><?php esc_html_e( 'Set departure dates.', 'travel-pack' ); ?></strong>
					<?php esc_html_e( 'For each date choose how many seats are available. Bookings will reduce this automatically.', 'travel-pack' ); ?>
				</li>
			</ol>
		</div>
		<?php
	}

	public static function enqueue_assets( $hook ) {
		global $post_type;

		$is_our_screen = false;

		if ( in_array( $hook, array( 'post.php', 'post-new.php' ), true ) && TRAVEL_PACK_POST_TYPE === $post_type ) {
			$is_our_screen = true;
		}
		if ( isset( $_GET['page'] ) && in_array( $_GET['page'], array( 'travel-pack', 'travel-pack-bookings', 'travel-pack-settings' ), true ) ) {
			$is_our_screen = true;
		}

		if ( ! $is_our_screen ) {
			return;
		}

		wp_enqueue_style(
			'travel-pack-admin',
			TRAVEL_PACK_URL . 'assets/css/admin.css',
			array(),
			TRAVEL_PACK_VERSION
		);
		wp_enqueue_script(
			'travel-pack-admin',
			TRAVEL_PACK_URL . 'assets/js/admin.js',
			array(),
			TRAVEL_PACK_VERSION,
			true
		);
	}
}
