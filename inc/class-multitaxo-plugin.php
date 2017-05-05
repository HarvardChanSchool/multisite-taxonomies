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
		// load the db delta scripts
		require_once( ABSPATH . 'wp-admin/includes/upgrade.php' );

		// get characterset of the server
		$charset_collate = $wpdb->get_charset_collate();

		// Table structure for table `wp_termmeta`.
		$termmeta_table = $wpdb->prefix . 'termmeta';

		$termmeta_sql = 'CREATE TABLE IF NOT EXISTS `' . $termmeta_table . '` (
			`meta_id` bigint(20) unsigned NOT NULL,
			`term_id` bigint(20) unsigned NOT NULL DEFAULT "0",
			`meta_key` varchar(255) DEFAULT NULL,
			`meta_value` longtext
		) ' . $charset_collate . ';';

		dbDelta( $termmeta_sql );

		// Table structure for table `wp_terms`.
		$terms_table = $wpdb->prefix . 'terms';

		$terms_sql = 'CREATE TABLE IF NOT EXISTS `' . $terms_table . '` (
			`term_id` bigint(20) unsigned NOT NULL,
			`name` varchar(200) NOT NULL DEFAULT "",
			`slug` varchar(200) NOT NULL DEFAULT "",
			`term_group` bigint(10) NOT NULL DEFAULT "0"
		) ' . $charset_collate . ';';

		dbDelta( $terms_sql );

		// Table structure for table `wp_term_relationships`.
		$term_relationships_table = $wpdb->prefix . 'term_relationships';

		$termmeta_sql = 'CREATE TABLE IF NOT EXISTS `' . $term_relationships_table . '` (
			`object_id` bigint(20) unsigned NOT NULL DEFAULT "0",
			`term_taxonomy_id` bigint(20) unsigned NOT NULL DEFAULT "0",
			`term_order` int(11) NOT NULL DEFAULT "0"
		) ' . $charset_collate . ';';

		dbDelta( $term_relationships_sql );

		// Table structure for table `wp_termmeta`.
		$term_taxonomy_table = $wpdb->prefix . 'term_taxonomy';

		$term_taxonomy_sql = 'CREATE TABLE IF NOT EXISTS `' . $term_taxonomy_table . '` (
			`term_taxonomy_id` bigint(20) unsigned NOT NULL,
			`term_id` bigint(20) unsigned NOT NULL DEFAULT "0",
			`taxonomy` varchar(32) NOT NULL DEFAULT '',
			`description` longtext NOT NULL,
			`parent` bigint(20) unsigned NOT NULL DEFAULT "0",
			`count` bigint(20) NOT NULL DEFAULT "0"
		) ' . $charset_collate . ';';

		dbDelta( $termmeta_sql );

		/*
			--
			-- Indexes for table `grjn2wkfr_termmeta`
			--
			ALTER TABLE `grjn2wkfr_termmeta`
			  ADD PRIMARY KEY (`meta_id`), ADD KEY `term_id` (`term_id`), ADD KEY `meta_key` (`meta_key`(191));

			--
			-- Indexes for table `grjn2wkfr_terms`
			--
			ALTER TABLE `grjn2wkfr_terms`
			  ADD PRIMARY KEY (`term_id`), ADD KEY `slug` (`slug`(191)), ADD KEY `name` (`name`(191));

			--
			-- Indexes for table `grjn2wkfr_term_relationships`
			--
			ALTER TABLE `grjn2wkfr_term_relationships`
			  ADD PRIMARY KEY (`object_id`,`term_taxonomy_id`), ADD KEY `term_taxonomy_id` (`term_taxonomy_id`);

			--
			-- Indexes for table `grjn2wkfr_term_taxonomy`
			--
			ALTER TABLE `grjn2wkfr_term_taxonomy`
			  ADD PRIMARY KEY (`term_taxonomy_id`), ADD UNIQUE KEY `term_id_taxonomy` (`term_id`,`taxonomy`), ADD KEY `taxonomy` (`taxonomy`);
		*/
	}
}
