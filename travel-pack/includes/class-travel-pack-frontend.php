<?php
/**
 * Loads the frontend template for single Travel Packages and enqueues assets.
 *
 * @package TravelPack
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Travel_Pack_Frontend {

	public static function init() {
		add_filter( 'single_template', array( __CLASS__, 'load_single_template' ) );
		add_action( 'wp_enqueue_scripts', array( __CLASS__, 'enqueue_assets' ) );
	}

	/**
	 * Overrides the single template only when the theme does not provide one for
	 * our post type. Themes can still override with single-travel_package.php.
	 */
	public static function load_single_template( $template ) {
		if ( is_singular( TRAVEL_PACK_POST_TYPE ) ) {
			$theme_template = locate_template( array( 'single-' . TRAVEL_PACK_POST_TYPE . '.php' ) );
			if ( $theme_template ) {
				return $theme_template;
			}
			$plugin_template = TRAVEL_PACK_DIR . 'templates/single-travel_package.php';
			if ( file_exists( $plugin_template ) ) {
				return $plugin_template;
			}
		}
		return $template;
	}

	public static function enqueue_assets() {
		if ( ! is_singular( TRAVEL_PACK_POST_TYPE ) ) {
			return;
		}

		// The single template uses dashicons for the fact strip icons. Themes usually don't
		// enqueue dashicons on the frontend, so pull it in ourselves.
		wp_enqueue_style( 'dashicons' );

		wp_enqueue_style(
			'travel-pack-frontend',
			TRAVEL_PACK_URL . 'assets/css/frontend.css',
			array( 'dashicons' ),
			TRAVEL_PACK_VERSION
		);

		wp_enqueue_script(
			'travel-pack-frontend',
			TRAVEL_PACK_URL . 'assets/js/frontend.js',
			array(),
			TRAVEL_PACK_VERSION,
			true
		);

		wp_localize_script(
			'travel-pack-frontend',
			'TravelPackFrontend',
			array(
				'ajaxUrl' => admin_url( 'admin-ajax.php' ),
			)
		);
	}
}
