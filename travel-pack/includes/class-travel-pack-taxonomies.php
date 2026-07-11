<?php
/**
 * Registers Categories and Tags for Travel Packages.
 *
 * @package TravelPack
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Travel_Pack_Taxonomies {

	public static function init() {
		add_action( 'init', array( __CLASS__, 'register' ) );
	}

	public static function register() {
		// Categories (hierarchical, like default WP categories).
		register_taxonomy(
			TRAVEL_PACK_TAX_CATEGORY,
			TRAVEL_PACK_POST_TYPE,
			array(
				'labels'            => array(
					'name'          => __( 'Package Categories', 'travel-pack' ),
					'singular_name' => __( 'Package Category', 'travel-pack' ),
					'search_items'  => __( 'Search Categories', 'travel-pack' ),
					'all_items'     => __( 'All Categories', 'travel-pack' ),
					'edit_item'     => __( 'Edit Category', 'travel-pack' ),
					'update_item'   => __( 'Update Category', 'travel-pack' ),
					'add_new_item'  => __( 'Add New Category', 'travel-pack' ),
					'new_item_name' => __( 'New Category Name', 'travel-pack' ),
					'menu_name'     => __( 'Categories', 'travel-pack' ),
				),
				'hierarchical'      => true,
				'show_ui'           => true,
				'show_admin_column' => true,
				'query_var'         => true,
				'rewrite'           => array( 'slug' => 'package-category' ),
			)
		);

		// Tags (non-hierarchical, like default WP tags).
		register_taxonomy(
			TRAVEL_PACK_TAX_TAG,
			TRAVEL_PACK_POST_TYPE,
			array(
				'labels'            => array(
					'name'          => __( 'Package Tags', 'travel-pack' ),
					'singular_name' => __( 'Package Tag', 'travel-pack' ),
					'search_items'  => __( 'Search Tags', 'travel-pack' ),
					'all_items'     => __( 'All Tags', 'travel-pack' ),
					'edit_item'     => __( 'Edit Tag', 'travel-pack' ),
					'update_item'   => __( 'Update Tag', 'travel-pack' ),
					'add_new_item'  => __( 'Add New Tag', 'travel-pack' ),
					'new_item_name' => __( 'New Tag Name', 'travel-pack' ),
					'menu_name'     => __( 'Tags', 'travel-pack' ),
				),
				'hierarchical'      => false,
				'show_ui'           => true,
				'show_admin_column' => true,
				'query_var'         => true,
				'rewrite'           => array( 'slug' => 'package-tag' ),
			)
		);
	}
}
