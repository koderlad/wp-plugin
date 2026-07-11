<?php
/**
 * Registers the Travel Package custom post type.
 *
 * @package TravelPack
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Travel_Pack_Post_Type {

	public static function init() {
		add_action( 'init', array( __CLASS__, 'register' ) );
	}

	public static function register() {
		$labels = array(
			'name'                  => _x( 'Packages', 'Post type general name', 'travel-pack' ),
			'singular_name'         => _x( 'Package', 'Post type singular name', 'travel-pack' ),
			'menu_name'             => _x( 'Travel Pack', 'Admin Menu text', 'travel-pack' ),
			'name_admin_bar'        => _x( 'Package', 'Add New on Toolbar', 'travel-pack' ),
			'add_new'               => __( 'Add New', 'travel-pack' ),
			'add_new_item'          => __( 'Add New Package', 'travel-pack' ),
			'new_item'              => __( 'New Package', 'travel-pack' ),
			'edit_item'             => __( 'Edit Package', 'travel-pack' ),
			'view_item'             => __( 'View Package', 'travel-pack' ),
			'all_items'             => __( 'All Packages', 'travel-pack' ),
			'search_items'          => __( 'Search Packages', 'travel-pack' ),
			'not_found'             => __( 'No packages found.', 'travel-pack' ),
			'not_found_in_trash'    => __( 'No packages found in Trash.', 'travel-pack' ),
			'featured_image'        => __( 'Package Featured Image', 'travel-pack' ),
			'set_featured_image'    => __( 'Set featured image', 'travel-pack' ),
			'remove_featured_image' => __( 'Remove featured image', 'travel-pack' ),
			'use_featured_image'    => __( 'Use as featured image', 'travel-pack' ),
			'archives'              => __( 'Package archives', 'travel-pack' ),
			'insert_into_item'      => __( 'Insert into package', 'travel-pack' ),
			'uploaded_to_this_item' => __( 'Uploaded to this package', 'travel-pack' ),
			'filter_items_list'     => __( 'Filter packages list', 'travel-pack' ),
			'items_list_navigation' => __( 'Packages list navigation', 'travel-pack' ),
			'items_list'            => __( 'Packages list', 'travel-pack' ),
		);

		$args = array(
			'labels'             => $labels,
			'public'             => true,
			'publicly_queryable' => true,
			'show_ui'            => true,
			'show_in_menu'       => 'travel-pack',
			'query_var'          => true,
			'rewrite'            => array( 'slug' => 'travel-package' ),
			'capability_type'    => 'post',
			'has_archive'        => 'travel-packages',
			'hierarchical'       => false,
			'menu_position'      => 20,
			'menu_icon'          => 'dashicons-palmtree',
			'supports'           => array( 'title', 'editor', 'thumbnail', 'excerpt' ),
			'show_in_rest'       => false,
		);

		register_post_type( TRAVEL_PACK_POST_TYPE, $args );
	}
}
