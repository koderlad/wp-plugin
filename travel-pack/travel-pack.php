<?php
/**
 * Plugin Name:       Travel Pack
 * Plugin URI:        https://example.com/travel-pack
 * Description:       Manage travel packages (destinations) with categories, tags, departures, itineraries, and a booking form. Built for travel agents.
 * Version:           1.0.0
 * Requires at least: 5.8
 * Requires PHP:      7.4
 * Author:            Travel Pack
 * License:           GPL-2.0-or-later
 * License URI:       https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain:       travel-pack
 * Domain Path:       /languages
 *
 * @package TravelPack
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

define( 'TRAVEL_PACK_VERSION', '1.0.0' );
define( 'TRAVEL_PACK_FILE', __FILE__ );
define( 'TRAVEL_PACK_DIR', plugin_dir_path( __FILE__ ) );
define( 'TRAVEL_PACK_URL', plugin_dir_url( __FILE__ ) );
define( 'TRAVEL_PACK_POST_TYPE', 'travel_package' );
define( 'TRAVEL_PACK_TAX_CATEGORY', 'travel_package_category' );
define( 'TRAVEL_PACK_TAX_TAG', 'travel_package_tag' );

require_once TRAVEL_PACK_DIR . 'includes/class-travel-pack-post-type.php';
require_once TRAVEL_PACK_DIR . 'includes/class-travel-pack-taxonomies.php';
require_once TRAVEL_PACK_DIR . 'includes/class-travel-pack-settings.php';
require_once TRAVEL_PACK_DIR . 'includes/class-travel-pack-metaboxes.php';
require_once TRAVEL_PACK_DIR . 'includes/class-travel-pack-departures.php';
require_once TRAVEL_PACK_DIR . 'includes/class-travel-pack-booking.php';
require_once TRAVEL_PACK_DIR . 'includes/class-travel-pack-frontend.php';
require_once TRAVEL_PACK_DIR . 'includes/class-travel-pack-admin-menu.php';

/**
 * Bootstraps the plugin by initializing every module.
 */
function travel_pack_bootstrap() {
	Travel_Pack_Post_Type::init();
	Travel_Pack_Taxonomies::init();
	Travel_Pack_Settings::init();
	Travel_Pack_Metaboxes::init();
	Travel_Pack_Departures::init();
	Travel_Pack_Booking::init();
	Travel_Pack_Frontend::init();
	Travel_Pack_Admin_Menu::init();
}
add_action( 'plugins_loaded', 'travel_pack_bootstrap' );

/**
 * Runs on activation. Registers the post type/taxonomies so rewrite rules can be flushed,
 * seeds default option values for the dropdowns, and creates the bookings table.
 */
function travel_pack_activate() {
	require_once TRAVEL_PACK_DIR . 'includes/class-travel-pack-post-type.php';
	require_once TRAVEL_PACK_DIR . 'includes/class-travel-pack-taxonomies.php';
	require_once TRAVEL_PACK_DIR . 'includes/class-travel-pack-settings.php';
	require_once TRAVEL_PACK_DIR . 'includes/class-travel-pack-booking.php';

	Travel_Pack_Post_Type::register();
	Travel_Pack_Taxonomies::register();
	Travel_Pack_Settings::seed_defaults();
	Travel_Pack_Booking::create_table();

	flush_rewrite_rules();
}
register_activation_hook( __FILE__, 'travel_pack_activate' );

/**
 * Runs on deactivation - only flushes rewrite rules. We intentionally do NOT drop
 * data on deactivation so an accidental deactivation does not destroy the agent's work.
 */
function travel_pack_deactivate() {
	flush_rewrite_rules();
}
register_deactivation_hook( __FILE__, 'travel_pack_deactivate' );
