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
 * Version:     0.0.2
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

$multitaxo_data = get_file_data( __FILE__, array( 'Version' => 'Version' ), 'plugin' );

define( 'MULTITAXO_VERSION', $multitaxo_data['Version'] );

if ( ! class_exists( 'WP_List_Table' ) ) {
	require_once ABSPATH . 'wp-admin/includes/class-wp-list-table.php';
}

// Loading Classes.
require_once MULTITAXO_PLUGIN_DIR . 'inc/class-multitaxo-plugin.php';
require_once MULTITAXO_PLUGIN_DIR . 'inc/class-multisite-taxonomy.php';
require_once MULTITAXO_PLUGIN_DIR . 'inc/class-multisite-taxonomy-query.php';
require_once MULTITAXO_PLUGIN_DIR . 'inc/class-multisite-term.php';
require_once MULTITAXO_PLUGIN_DIR . 'inc/class-multisite-term-query.php';
require_once MULTITAXO_PLUGIN_DIR . 'inc/class-multisite-wp-query.php';
require_once MULTITAXO_PLUGIN_DIR . 'inc/class-multisite-terms-list-table.php';
require_once MULTITAXO_PLUGIN_DIR . 'inc/class-multisite-taxonomy-meta-box.php';
require_once MULTITAXO_PLUGIN_DIR . 'inc/class-walker-hierarchical-multisite-taxonomy.php';
require_once MULTITAXO_PLUGIN_DIR . 'inc/class-walker-hierarchical-multisite-taxonomy-checklist.php';
require_once MULTITAXO_PLUGIN_DIR . 'inc/class-walker-hierarchical-multisite-taxonomy-dropdown.php';
// Loading multisite taxonomy and multisite term API.
require_once MULTITAXO_PLUGIN_DIR . 'inc/multisite-taxonomy.php';
require_once MULTITAXO_PLUGIN_DIR . 'inc/multisite-taxonomy-template.php';


// Plugin init.
$multitaxo   = new Multitaxo_Plugin();
$multi_admin = new Multisite_Taxonomy_Meta_Box();
