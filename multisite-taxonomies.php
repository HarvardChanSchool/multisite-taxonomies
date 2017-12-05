<?php
/**
 * Multisite Taxonomies
 *
 * @package multitaxo
 */

/**
 * Plugin Name: Multisite Taxonomies
 * Plugin URI:  https://github.com/HarvardChanSchool/multisite-taxonomies
 * Description: Multisite Taxonomies brings the ability to register custom taxonomies, accessible on an entire multisite network, to WordPress.
 * Version:     0.0.1
 * Author:      Harvard Chan Webteam
 * Author URI:  http://www.hsph.harvard.edu/information-technology/
 * Text Domain: multitaxo
 * Network:     true
 */

// If this file is called directly, abort.
if ( ! defined( 'WPINC' ) ) {
	die;
}

// Define plugin constants.
define( 'MULTITAXO_PLUGIN_DIR', plugin_dir_path( __FILE__ ) );
define( 'MULTITAXO_PLUGIN_URL', plugins_url( '', __FILE__ ) );
define( 'MULTITAXO_ASSETS_URL', plugins_url( '/assets', __FILE__ ) );

if ( ! class_exists( 'WP_List_Table' ) ) {
	require_once ABSPATH . 'wp-admin/includes/class-wp-list-table.php';
}

// Loading Classes.
require_once plugin_dir_path( __FILE__ ) . 'inc/class-multitaxo-plugin.php';
require_once plugin_dir_path( __FILE__ ) . 'inc/class-multisite-taxonomy.php';
require_once plugin_dir_path( __FILE__ ) . 'inc/class-multisite-tax-query.php';
require_once plugin_dir_path( __FILE__ ) . 'inc/class-multisite-term.php';
require_once plugin_dir_path( __FILE__ ) . 'inc/class-multisite-term-query.php';
require_once plugin_dir_path( __FILE__ ) . 'inc/class-multisite-terms-list-table.php';
require_once plugin_dir_path( __FILE__ ) . 'inc/class-multisite-terms-list-table.php';
// Loading multisite taxonomy and multisite term API.
require_once plugin_dir_path( __FILE__ ) . 'inc/multisite-taxonomy.php';

// Plugin init.
$multitaxo = new Multitaxo_Plugin();

/**
 * Load in a testing tax.
 *
 * @return void
 */
function testing_custom_taxonomy() {

	$labels = array(
		'name'                       => _x( 'Taxonomies', 'Taxonomy General Name', 'multitaxo' ),
		'singular_name'              => _x( 'Taxonomy', 'Taxonomy Singular Name', 'multitaxo' ),
		'menu_name'                  => __( 'Taxonomy', 'multitaxo' ),
		'all_items'                  => __( 'All Items', 'multitaxo' ),
		'parent_item'                => __( 'Parent Item', 'multitaxo' ),
		'parent_item_colon'          => __( 'Parent Item:', 'multitaxo' ),
		'new_item_name'              => __( 'New Item Name', 'multitaxo' ),
		'add_new_item'               => __( 'Add New Item', 'multitaxo' ),
		'edit_item'                  => __( 'Edit Item', 'multitaxo' ),
		'update_item'                => __( 'Update Item', 'multitaxo' ),
		'view_item'                  => __( 'View Item', 'multitaxo' ),
		'separate_items_with_commas' => __( 'Separate items with commas', 'multitaxo' ),
		'add_or_remove_items'        => __( 'Add or remove items', 'multitaxo' ),
		'choose_from_most_used'      => __( 'Choose from the most used', 'multitaxo' ),
		'popular_items'              => __( 'Popular Items', 'multitaxo' ),
		'search_items'               => __( 'Search Items', 'multitaxo' ),
		'not_found'                  => __( 'Not Found', 'multitaxo' ),
		'no_terms'                   => __( 'No items', 'multitaxo' ),
		'items_list'                 => __( 'Items list', 'multitaxo' ),
		'items_list_navigation'      => __( 'Items list navigation', 'multitaxo' ),
	);
	$args   = array(
		'labels'            => $labels,
		'hierarchical'      => false,
		'public'            => true,
		'show_ui'           => true,
		'show_admin_column' => true,
		'show_in_nav_menus' => true,
		'show_tagcloud'     => true,
	);
	register_multisite_taxonomy( 'taxonomy', array( 'post' ), $args );

}
add_action( 'init', 'testing_custom_taxonomy', 0 );
