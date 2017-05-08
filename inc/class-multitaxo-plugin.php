<?php
/**
 * Multisite Taxonomies Plugin init class
 *
 * @package multitaxo
 */

/**
 * Plugin Init class.
 */
class Multitaxo_Plugin {
	/**
	 * __construct function.
	 *
	 * @access public
	 * @return void
	 */
	public function __construct() {
		// We enqueue both the frontend and admin styles and scripts.
		add_action( 'wp_enqueue_scripts', array( $this, 'enqueue_styles_and_scripts' ) );
		add_action( 'admin_enqueue_scripts', array( $this, 'admin_enqueue_styles_and_scripts' ) );

		// Register an activation/deactivation hooks.
		add_action( 'activate_multisite-taxonomies/multisite-taxonomies.php', array( $this, 'activation_hook' ) );
		add_action( 'deactivate_multisite-taxonomies/multisite-taxonomies.php', array( $this, 'deactivation_hook' ) );
	}

	/**
	 * Enqueue the frontend styles and scripts.
	 *
	 * @access public
	 * @return void
	 */
	public function enqueue_styles_and_scripts() {
	}

	/**
	 * Enqueue the admin styles and scripts.
	 *
	 * @access public
	 * @return void
	 */
	public function admin_enqueue_styles_and_scripts() {
	}

	/**
	 * Plugin activation hook callback.
	 *
	 * @access public
	 * @return void
	 */
	public function activation_hook() {
	}

	/**
	 * Plugin deactivation hook callback.
	 *
	 * @access public
	 * @return void
	 */
	public function deactivation_hook() {
		global $wpdb;
		// Load the db delta scripts.
		require_once( ABSPATH . 'wp-admin/includes/upgrade.php' );

		// Get characterset of the server.
		$charset_collate = $wpdb->get_charset_collate();

		/*
         * Indexes have a maximum size of 767 bytes. Historically, we haven't need to be concerned about that.
         * As of 4.2, however, we moved to utf8mb4, which uses 4 bytes per character. This means that an index which
         * used to have room for floor(767/3) = 255 characters, now only has room for floor(767/4) = 191 characters.
         */

        $max_index_length = 191;

		// Table structure for table `wp_termmeta`.
		$termmeta_table = $wpdb->prefix . 'termmeta';

		$termmeta_sql = 'CREATE TABLE IF NOT EXISTS `' . $termmeta_table . '` (
			meta_id bigint(20) unsigned NOT NULL auto_increment,
			term_id bigint(20) unsigned NOT NULL default "0",
			meta_key varchar(255) default NULL,
			meta_value longtext,
			PRIMARY KEY  (meta_id),
			KEY term_id (term_id),
			KEY meta_key (meta_key(' . $max_index_length . '))
		) ' . $charset_collate . ';';

		dbDelta( $termmeta_sql );

		// Table structure for table `wp_terms`.
		$terms_table = $wpdb->prefix . 'terms';

		$terms_sql = 'CREATE TABLE IF NOT EXISTS `' . $terms_table . '` (
			term_id bigint(20) unsigned NOT NULL auto_increment,
			name varchar(200) NOT NULL default '',
			slug varchar(200) NOT NULL default '',
			term_group bigint(10) NOT NULL default 0,
			PRIMARY KEY  (term_id),
			KEY slug (slug(' . $max_index_length . ')),
			KEY name (name(' . $max_index_length . '))
		) ' . $charset_collate . ';';

		dbDelta( $terms_sql );

		// Table structure for table `wp_term_relationships`.
		$term_relationships_table = $wpdb->prefix . 'term_relationships';

		$termmeta_sql = 'CREATE TABLE IF NOT EXISTS `' . $term_relationships_table . '` (
			object_id bigint(20) unsigned NOT NULL default 0,
			term_taxonomy_id bigint(20) unsigned NOT NULL default 0,
			term_order int(11) NOT NULL default 0,
			PRIMARY KEY  (object_id,term_taxonomy_id),
			KEY term_taxonomy_id (term_taxonomy_id)
		) ' . $charset_collate . ';';

		dbDelta( $term_relationships_sql );

		// Table structure for table `wp_termmeta`.
		$term_taxonomy_table = $wpdb->prefix . 'term_taxonomy';

		$term_taxonomy_sql = 'CREATE TABLE IF NOT EXISTS `' . $term_taxonomy_table . '` (
			term_taxonomy_id bigint(20) unsigned NOT NULL auto_increment,
			term_id bigint(20) unsigned NOT NULL default 0,
			taxonomy varchar(32) NOT NULL default '',
			description longtext NOT NULL,
			parent bigint(20) unsigned NOT NULL default 0,
			count bigint(20) NOT NULL default 0,
			PRIMARY KEY  (term_taxonomy_id),
			UNIQUE KEY term_id_taxonomy (term_id,taxonomy),
			KEY taxonomy (taxonomy)
		) ' . $charset_collate . ';';

		dbDelta( $termmeta_sql );

	}
}
