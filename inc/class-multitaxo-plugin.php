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
	 * List table class.
	 *
	 * @access private
	 * @var object
	 */
	private $list_table;

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

		// Add the editing tags screen.
		add_action( 'network_admin_menu', array( $this, 'add_network_menu_terms' ) );
		add_filter( 'set-screen-option', array( $this, 'multisite_set_screen_option' ), 10, 3 );

		// Hide menu items we dont want to make visible to the world but want to leave behind.
		add_action( 'admin_head', array( $this, 'hide_network_menu_terms' ), 1 );

		// register our tables to WPDB.
		add_action( 'init', array( $this, 'register_database_tables' ), 1 );
		add_action( 'switch_blog', array( $this, 'register_database_tables' ) );

		// register the ajax response for creating new tags.
		add_action( 'wp_ajax_add-multisite-tag', array( $this, 'ajax_add_multisite_tag' ) );
		add_action( 'wp_ajax_inline-save-multisite-tax', array( $this, 'ajax_inline_save_multisite_tag' ) );

		// register action hooks for specific actions.
		add_action( 'before_delete_post', array( $this, 'before_delete_post_action_hook' ) );
	}

	/**
	 * Register the Multisite Taxonomies database tables for use with $wpdb.
	 *
	 * @global wpdb $wpdb The WordPress database abstraction object.
	 *
	 * @access public
	 * @return void
	 */
	public function register_database_tables() {
		global $wpdb;

		$wpdb->multisite_termmeta                = $wpdb->base_prefix . 'multisite_termmeta';
		$wpdb->multisite_terms                   = $wpdb->base_prefix . 'multisite_terms';
		$wpdb->multisite_term_relationships      = $wpdb->base_prefix . 'multisite_term_relationships';
		$wpdb->multisite_term_multisite_taxonomy = $wpdb->base_prefix . 'multisite_term_multisite_taxonomy';

		if ( false === get_site_option( 'multitaxo_tables_created' ) ) {
			$this->create_database_tables();
		}
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
		wp_register_script( 'admin-multisite-tags', MULTITAXO_PLUGIN_URL . '/assets/js/admin-multisite-tags.js', array( 'jquery', 'wp-ajax-response' ), MULTITAXO_VERSION, true );
		wp_localize_script(
			'admin-multisite-tags',
			'tagsl10n',
			array(
				'noPerm' => esc_html__( 'Sorry, you are not allowed to do that.', 'multitaxo' ),
				'broken' => esc_html__( 'An unidentified error has occurred.', 'multitaxo' ),
			)
		);

		wp_register_script( 'inline-edit-multisite-tax', MULTITAXO_PLUGIN_URL . '/assets/js/inline-edit-multisite-tax.js', array( 'jquery', 'wp-a11y' ), MULTITAXO_VERSION, true );
		wp_localize_script(
			'inline-edit-multisite-tax',
			'inlineEditL10n',
			array(
				'error' => esc_html__( 'Error while saving the changes.', 'multitaxo' ),
				'saved' => esc_html__( 'Changes saved.', 'multitaxo' ),
			)
		);
	}

	/**
	 * Plugin activation hook callback.
	 *
	 * @access public
	 * @return void
	 */
	public function activation_hook() {
		$this->register_database_tables();
	}

	/**
	 * Plugin deactivation hook callback.
	 *
	 * @access public
	 * @return void
	 */
	public function deactivation_hook() {
		$this->delete_database_tables();
	}

	/**
	 * Create our custom database tables.
	 *
	 * @global wpdb $wpdb The WordPress database abstraction object.
	 *
	 * @access public
	 * @return void
	 */
	public function create_database_tables() {
		global $wpdb;
		// Load the db delta scripts.
		require_once ABSPATH . 'wp-admin/includes/upgrade.php';

		// Get characterset of the server.
		$charset_collate = $wpdb->get_charset_collate();

		/*
		 * Indexes have a maximum size of 767 bytes. Historically, we haven't need to be concerned about that.
		 * As of 4.2, however, we moved to utf8mb4, which uses 4 bytes per character. This means that an index which
		 * used to have room for floor(767/3) = 255 characters, now only has room for floor(767/4) = 191 characters.
		 */

		$max_index_length = 191;

		// Table structure for table `wp_multisite_termmeta`.
		$multisite_termmeta_sql = 'CREATE TABLE IF NOT EXISTS `' . $wpdb->multisite_termmeta . '` (
			meta_id bigint(20) unsigned NOT NULL auto_increment,
			multisite_term_id bigint(20) unsigned NOT NULL default "0",
			meta_key varchar(255) default NULL,
			meta_value longtext,
			PRIMARY KEY  (meta_id),
			KEY multisite_term_id (multisite_term_id),
			KEY meta_key (meta_key(' . $max_index_length . '))
		) ' . $charset_collate . ';';

		//dbDelta( $multisite_termmeta_sql );
		$wpdb->query( $multisite_termmeta_sql );

		// Table structure for table `wp_multisite_terms`.
		$multisite_terms_sql = 'CREATE TABLE IF NOT EXISTS `' . $wpdb->multisite_terms . '` (
			multisite_term_id bigint(20) unsigned NOT NULL auto_increment,
			name varchar(200) NOT NULL default "",
			slug varchar(200) NOT NULL default "",
			multisite_term_group bigint(10) NOT NULL default 0,
			PRIMARY KEY  (multisite_term_id),
			KEY slug (slug(' . $max_index_length . ')),
			KEY name (name(' . $max_index_length . '))
		) ' . $charset_collate . ';';

		//dbDelta( $multisite_terms_sql );
		$wpdb->query( $multisite_terms_sql );

		// Table structure for table `wp_multisite_term_relationships`.
		$multisite_term_relationships_sql = 'CREATE TABLE IF NOT EXISTS `' . $wpdb->multisite_term_relationships . '` (
			blog_id bigint(20) unsigned NOT NULL default 0,
			object_id bigint(20) unsigned NOT NULL default 0,
			multisite_term_multisite_taxonomy_id bigint(20) unsigned NOT NULL default 0,
			multisite_term_order int(11) NOT NULL default 0,
			PRIMARY KEY  (blog_id,object_id,multisite_term_multisite_taxonomy_id),
			KEY multisite_term_multisite_taxonomy_id (multisite_term_multisite_taxonomy_id)
		) ' . $charset_collate . ';';

		//dbDelta( $multisite_term_relationships_sql );
		$wpdb->query( $multisite_term_relationships_sql );

		// Table structure for table `wp_multisite_term_multisite_taxonomy`.
		$multisite_term_multisite_taxonomy_sql = 'CREATE TABLE IF NOT EXISTS `' . $wpdb->multisite_term_multisite_taxonomy . '` (
			multisite_term_multisite_taxonomy_id bigint(20) unsigned NOT NULL auto_increment,
			multisite_term_id bigint(20) unsigned NOT NULL default 0,
			multisite_taxonomy varchar(32) NOT NULL default "",
			description longtext NOT NULL,
			parent bigint(20) unsigned NOT NULL default 0,
			count bigint(20) NOT NULL default 0,
			PRIMARY KEY  (multisite_term_multisite_taxonomy_id),
			UNIQUE KEY multisite_term_id_multisite_taxonomy (multisite_term_id,multisite_taxonomy),
			KEY multisite_taxonomy (multisite_taxonomy)
		) ' . $charset_collate . ';';

		//dbDelta( $multisite_term_multisite_taxonomy_sql );
		$wpdb->query( $multisite_term_multisite_taxonomy_sql );

		update_site_option( 'multitaxo_tables_created', 1 );

	}

	/**
	 * Remove our custom database tables on plugin deactivation.
	 *
	 * @access public
	 * @return void
	 */
	public function delete_database_tables() {
		// Silence.
	}

	/**
	 * Add the metowrk admin menu to the terms page.
	 *
	 * @access public
	 * @return void
	 */
	public function add_network_menu_terms() {
		$screen = add_menu_page( esc_html__( 'Multisite Taxonomies', 'multitaxo' ), esc_html__( 'Taxonomies', 'multitaxo' ), 'manage_multisite_terms', 'multisite_term_list', array( $this, 'display_multisite_taxonomy_list' ), 'dashicons-tag', 22 );

		add_submenu_page( 'multisite_term_list', esc_html__( 'Edit Tag', 'multitaxo' ), esc_html__( 'Edit Tag', 'multitaxo' ), 'manage_multisite_terms', 'multisite_term_edit', array( $this, 'display_multisite_taxonomy_edit_screen' ) );

		$taxonomies = get_multisite_taxonomies( array(), 'objects' );

		foreach ( $taxonomies as $tax_slug => $tax ) {
			$screen_hook = add_submenu_page( 'multisite_term_list', $tax->label, $tax->label, 'manage_multisite_terms', 'multisite_term_list_' . $tax_slug, array( $this, 'display_multisite_taxonomy' ) );
			add_action( 'load-' . $screen_hook, array( $this, 'load_multisite_taxonomy' ) );
		}
	}

	/**
	 * Hide netowrk Menu Items we dont want to be seen.
	 *
	 * @access public
	 * @return void
	 */
	public function hide_network_menu_terms() {
		remove_submenu_page( 'multisite_term_list', 'multisite_term_edit' );
	}

	/**
	 * Save the screen options hook.
	 *
	 * @access public
	 *
	 * @param string $status Set the screen option value.
	 * @param string $option The option to check.
	 * @param mixed  $value The value of hte option to use.
	 *
	 * @return mixed option value.
	 */
	public function multisite_set_screen_option( $status, $option, $value ) {
		if ( 'edit_multisite_tax_per_page' === $option ) {
			return $value;
		}
	}

	/**
	 * Display the list table screen in the network.
	 *
	 * @access public
	 * @return void
	 */
	public function load_multisite_taxonomy() {
		$page = ( isset( $_GET['page'] ) ) ? sanitize_key( wp_unslash( $_GET['page'] ) ) : null; // phpcs:ignore WordPress.Security.NonceVerification

		$tax_slug = str_replace( 'multisite_term_list_', '', $page );

		// Check that we have something.
		if ( empty( $tax_slug ) ) {
			wp_die( esc_html__( 'Invalid taxonomy.', 'multitaxo' ) );
		}

		$mulsite_taxonomy = get_multisite_taxonomy( $tax_slug );

		if ( ! is_a( $mulsite_taxonomy, 'Multisite_Taxonomy' ) ) {
			wp_die( esc_html__( 'Invalid multisite taxonomy.', 'multitaxo' ) );
		}

		if ( ! in_array( $mulsite_taxonomy->name, get_multisite_taxonomies( array( 'show_ui' => true ) ), true ) ) {
			wp_die( esc_html__( 'Sorry, you are not allowed to edit multisite terms in this multisite taxonomy.', 'multitaxo' ) );
		}

		if ( ! current_user_can( $mulsite_taxonomy->cap->manage_multisite_terms ) ) {
			wp_die(
				'<h1>' . esc_html__( 'Cheatin&#8217; uh?', 'multitaxo' ) . '</h1>' .
				'<p>' . esc_html__( 'Sorry, you are not allowed to manage multisite terms in this multisite taxonomy.', 'multitaxo' ) . '</p>',
				403
			);
		}

		$screen = get_current_screen();

		// well this is dumb we are setting the multisite tex only to get it again.
		$screen->taxonomy = $tax_slug;

		/**
		 * $post_type is set when the WP_Terms_List_Table instance is created
		 *
		 * @global string $post_type
		 */
		$this->list_table = new Multisite_Terms_List_Table();

		$pagenum = $this->list_table->get_pagenum();
		$title   = $mulsite_taxonomy->labels->name;

		add_screen_option(
			'per_page',
			array(
				'default' => 20,
				'option'  => 'edit_multisite_tax_per_page',
			)
		);

		get_current_screen()->set_screen_reader_content(
			array(
				'heading_pagination' => $mulsite_taxonomy->labels->items_list_navigation,
				'heading_list'       => $mulsite_taxonomy->labels->items_list,
			)
		);

		$location = false;

		$referer = wp_get_referer();

		if ( ! $referer ) { // For POST requests.
			if ( isset( $_SERVER['REQUEST_URI'] ) ) {
				$referer = sanitize_text_field( wp_unslash( $_SERVER['REQUEST_URI'] ) );
			} else {
				$referer = '/';
			}
		}

		$referer = remove_query_arg( array( '_wp_http_referer', '_wpnonce', 'error', 'message', 'paged' ), $referer );

		switch ( $this->list_table->current_action() ) {

			case 'add-tag':
				check_admin_referer( 'add-multisite-tag', '_wpnonce_add-multisite-tag' );

				if ( ! current_user_can( $mulsite_taxonomy->cap->edit_multisite_terms ) ) {
					wp_die(
						'<h1>' . esc_html__( 'Cheatin&#8217; uh?', 'multitaxo' ) . '</h1>' .
						'<p>' . esc_html__( 'Sorry, you are not allowed to create terms in this taxonomy.', 'multitaxo' ) . '</p>',
						403
					);
				}

				if ( isset( $_POST['tag-name'] ) ) {
					$tag = insert_multisite_term( sanitize_text_field( wp_unslash( $_POST['tag-name'] ) ), $tax->name, $_POST );
				}

				if ( $ret && ! is_wp_error( $ret ) ) {
					$location = add_query_arg( 'message', 1, $referer );
				} else {
					$location = add_query_arg(
						array(
							'error'   => true,
							'message' => 4,
						),
						$referer
					);
				}

				break;
			case 'delete':
				if ( ! isset( $_REQUEST['multisite_term_id'] ) ) {
					break;
				}

				$tag_id = (int) absint( wp_unslash( $_REQUEST['multisite_term_id'] ) );

				check_admin_referer( 'delete-multisite_term_' . $tag_id );

				if ( ! current_user_can( 'delete_multisite_term', $tag_id ) ) {
					wp_die(
						'<h1>' . esc_html__( 'Cheatin&#8217; uh?', 'multitaxo' ) . '</h1>' .
						'<p>' . esc_html__( 'Sorry, you are not allowed to delete this item.', 'multitaxo' ) . '</p>',
						403
					);
				}

				delete_multisite_term( $tag_id, $mulsite_taxonomy->name );

				$location = add_query_arg( 'message', 2, $referer );

				// When deleting a term, prevent the action from redirecting back to a term that no longer exists.
				$location = remove_query_arg( array( 'multisite_term_id', 'action', 'page' ), $location );

				$location = add_query_arg( 'page', 'multisite_term_list_' . $mulsite_taxonomy->name, $location );

				break;
			case 'bulk-delete':
				check_admin_referer( 'bulk-tags' );

				if ( ! current_user_can( $mulsite_taxonomy->cap->delete_multisite_terms ) ) {
					wp_die(
						'<h1>' . esc_html__( 'Cheatin&#8217; uh?', 'multitaxo' ) . '</h1>' .
						'<p>' . esc_html__( 'Sorry, you are not allowed to delete these items.', 'multitaxo' ) . '</p>',
						403
					);
				}

				if ( isset( $_REQUEST['delete_multisite_terms'] ) && is_array( wp_unslash( $_REQUEST['delete_multisite_terms'] ) ) ) { // phpcs:ignore WordPress.Security.ValidatedSanitizedInput
					$multisite_terms = array_map( 'absint', wp_unslash( $_REQUEST['delete_multisite_terms'] ) );
					foreach ( $multisite_terms as $multisite_terms_id ) {
						delete_multisite_term( $multisite_terms_id, $mulsite_taxonomy->name );
					}
				}

				$location = add_query_arg( 'message', 6, $referer );

				break;
			case 'edit':
				if ( ! isset( $_REQUEST['multisite_term_id'] ) ) {
					break;
				}

				$multisite_term_id = (int) absint( wp_unslash( $_POST['multisite_term_id'] ) ); // phpcs:ignore WordPress.Security.ValidatedSanitizedInput
				$term              = get_multisite_term( $multisite_term_id );

				if ( ! $term instanceof WP_Term ) {
					wp_die( esc_html__( 'You attempted to edit an item that doesn&#8217;t exist. Perhaps it was deleted?', 'multitaxo' ) );
				}

				wp_safe_redirect( esc_url_raw( get_multisite_edit_term_link( $multisite_term_id, $mulsite_taxonomy->name ) ) );

				exit;
			case 'editedtag':
				if ( ! isset( $_REQUEST['multisite_term_id'] ) ) {
					break;
				}

				$tag_id = (int) absint( wp_unslash( $_POST['multisite_term_id'] ) ); // phpcs:ignore WordPress.Security.ValidatedSanitizedInput

				check_admin_referer( 'update-multisite-term_' . $tag_id );

				if ( ! current_user_can( 'edit_multisite_terms', $tag_id ) ) {
					wp_die(
						'<h1>' . esc_html__( 'Cheatin&#8217; uh?', 'multitaxo' ) . '</h1>' .
						'<p>' . esc_html__( 'Sorry, you are not allowed to edit this item.', 'multitaxo' ) . '</p>',
						403
					);
				}

				$tag = get_multisite_term( $tag_id, $mulsite_taxonomy->name );

				if ( ! $tag ) {
					wp_die( esc_html__( 'You attempted to edit an item that doesn&#8217;t exist. Perhaps it was deleted?', 'multitaxo' ) );
				}

				$ret = update_multisite_term( $tag_id, $mulsite_taxonomy->name, $_POST );

				if ( $ret && ! is_wp_error( $ret ) ) {
					$location = add_query_arg( 'message', 3, $referer );
				} else {
					$location = add_query_arg(
						array(
							'error'   => true,
							'message' => 5,
						),
						$referer
					);
				}

				break;
			default:
				if ( ! $this->list_table->current_action() || ! isset( $_REQUEST['delete_tags'] ) ) {
					break;
				}

				check_admin_referer( 'bulk-tags' );

				// Good idea to make sure things are set before using them.
				$tags = isset( $_REQUEST['delete_tags'] ) ? (array) wp_unslash( $_REQUEST['delete_tags'] ) : array(); // phpcs:ignore WordPress.Security.ValidatedSanitizedInput

				// Sanitize the tags.
				$tags = array_map( 'sanitize_text_field', $tags );

				/** This action is documented in wp-admin/edit-comments.php */
				$location = apply_filters( 'handle_bulk_actions_' . get_current_screen()->id, $location, $this->list_table->current_action(), $tags );

				break;
		}

		if ( ! $location && ! empty( $_REQUEST['_wp_http_referer'] ) ) {
			$location = remove_query_arg( array( '_wp_http_referer', '_wpnonce' ), sanitize_text_field( wp_unslash( $_SERVER['REQUEST_URI'] ) ) );
		}

		if ( $location ) {
			if ( $pagenum > 1 ) {
				$location = add_query_arg( 'paged', $pagenum, $location ); // $pagenum takes care of $total_pages.
			}
			/**
			 * Filters the taxonomy redirect destination URL.
			 *
			 * @since 4.6.0
			 *
			 * @param string $location The destination URL.
			 * @param object $mulsite_taxonomy The taxonomy object.
			 */
			wp_safe_redirect( apply_filters( 'redirect_term_location', $location, $mulsite_taxonomy ) );
			exit;
		}

		$this->list_table->prepare_items();

		$total_pages = $this->list_table->get_pagination_arg( 'total_pages' );

		if ( $pagenum > $total_pages && $total_pages > 0 ) {
			wp_safe_redirect( add_query_arg( 'paged', $total_pages ) );
			exit;
		}
	}

	/**
	 * Add a new multsite term to the database via ajax if it does not already exist.
	 *
	 * @return void
	 */
	public function ajax_add_multisite_tag() {
		check_ajax_referer( 'add-multisite-tag', 'nonce-add-multisite-tag' );

		$taxonomy = ( ! empty( sanitize_key( wp_unslash( $_POST['multisite_taxonomy'] ) ) ) ) ? sanitize_key( wp_unslash( $_POST['multisite_taxonomy'] ) ) : null;

		if ( empty( $taxonomy ) ) {
			esc_html_e( 'Invalid multisite taxonomy. -2', 'multitaxo' );
			die();
		}

		$tax = get_multisite_taxonomy( $taxonomy );

		if ( ! current_user_can( $tax->cap->manage_multisite_terms ) ) {
			wp_die( -1 );
		}

		$x = new WP_Ajax_Response();

		if ( isset( $_POST['tag-name'] ) ) {
			$tag = insert_multisite_term( sanitize_text_field( wp_unslash( $_POST['tag-name'] ) ), $taxonomy, $_POST );
		}

		if ( ! $tag || is_wp_error( $tag ) ) {
			$message = esc_html__( 'An error has occurred. Please reload the page and try again.', 'multitaxo' );
			if ( is_wp_error( $tag ) && $tag->get_error_message() ) {
				$message = $tag->get_error_message();
			}

			$x->add(
				array(
					'what' => 'multisite_taxonomy',
					'data' => new WP_Error( 'error', $message ),
				)
			);
			$x->send();
		}

		$tag = get_multisite_term( $tag['multisite_term_id'], $taxonomy );

		if ( ! $tag || is_wp_error( $tag ) ) {
			$message = esc_html__( 'An error has occurred. Please reload the page and try again.', 'multitaxo' );
			if ( is_wp_error( $tag ) && $tag->get_error_message() ) {
				$message = $tag->get_error_message();
			}

			$x->add(
				array(
					'what' => 'multisite_taxonomy',
					'data' => new WP_Error( 'error', $message ),
				)
			);
			$x->send();
		}

		$args = array();

		if ( isset( $_POST['screen'] ) ) {
			$args['screen'] = convert_to_screen( sanitize_key( wp_unslash( $_POST['screen'] ) ) );
		} elseif ( isset( $GLOBALS['hook_suffix'] ) ) {
			$args['screen'] = get_current_screen();
		} else {
			$args['screen'] = null;
		}

		if ( null !== $args['screen'] ) {
			$args['screen']->taxonomy = $taxonomy;
		}

		$tax_list_table = new Multisite_Terms_List_Table( $args );

		$level = 0;

		if ( is_multisite_taxonomy_hierarchical( $taxonomy ) ) {
			$level = count( get_ancestors( $tag->term_id, $taxonomy, 'taxonomy' ) );
			ob_start();
			$tax_list_table->single_row( $tag, $level );
			$noparents = ob_get_clean();
		}

		ob_start();
		$tax_list_table->single_row( $tag );
		$parents = ob_get_clean();

		$x->add(
			array(
				'what'         => 'taxonomy',
				'supplemental' => compact( 'parents', 'noparents' ),
			)
		);
		$x->add(
			array(
				'what'         => 'term',
				'position'     => $level,
				'supplemental' => (array) $tag,
			)
		);
		$x->send();
	}

	/**
	 * Add a Update Multisite term in database.
	 *
	 * @return void
	 */
	public function ajax_inline_save_multisite_tag() {
		check_ajax_referer( 'ajax_edit_multisite_tax', 'nonce_multisite_inline_edit' );

		if ( isset( $_POST['taxonomy'] ) ) {
			$taxonomy = sanitize_key( wp_unslash( $_POST['taxonomy'] ) );
		} else {
			$taxonomy = null;
		}

		$tax = get_multisite_taxonomy( $taxonomy );

		if ( ! $tax ) {
			wp_die( 0 );
		}

		if ( ! isset( $_POST['tax_id'] ) ) {
			wp_die( -1 );
		}

		$id = absint( wp_unslash( $_POST['tax_id'] ) );

		if ( ! current_user_can( 'edit_multisite_term', $id ) ) {
			wp_die( -1 );
		}

		$args = array();

		if ( isset( $_POST['screen'] ) ) {
			$args['screen'] = convert_to_screen( sanitize_key( wp_unslash( $_POST['screen'] ) ) );
		} elseif ( isset( $GLOBALS['hook_suffix'] ) ) {
			$args['screen'] = get_current_screen();
		} else {
			$args['screen'] = null;
		}

		if ( null !== $args['screen'] ) {
			$args['screen']->taxonomy = $taxonomy;
		}

		$tax_list_table = new Multisite_Terms_List_Table( $args );

		$tag                  = get_multisite_term( $id, $taxonomy );
		$_POST['description'] = $tag->description;

		$updated = update_multisite_term( $id, $taxonomy, $_POST );

		if ( $updated && ! is_wp_error( $updated ) ) {
			$tag = get_multisite_term( $updated['multisite_term_id'], $taxonomy );
			if ( ! $tag || is_wp_error( $tag ) ) {
				if ( is_wp_error( $tag ) && $tag->get_error_message() ) {
					wp_die( esc_html( $tag->get_error_message() ) );
				}
				wp_die( esc_html__( 'Item not updated.', 'multitaxo' ) );
			}
		} else {
			if ( is_wp_error( $updated ) && $updated->get_error_message() ) {
				wp_die( esc_html( $updated->get_error_message() ) );
			}
			wp_die( esc_html__( 'Item not updated.', 'multitaxo' ) );
		}
		$level  = 0;
		$parent = $tag->parent;
		while ( $parent > 0 ) {
			$parent_tag = get_multisite_term( $parent, $taxonomy );
			$parent     = $parent_tag->parent;
			$level++;
		}
		$tax_list_table->single_row( $tag, $level );
		wp_die();
	}

	/**
	 * Display the list table screen in the network.
	 *
	 * @access public
	 * @return void
	 */
	public function display_multisite_taxonomy_list() {
		?>
		<div class="wrap">
		<h1><?php echo esc_html__( 'Multisite Taxonomies', 'multitaxo' ); ?></h1>
		<ul>
		<?php

		$taxonomies = get_multisite_taxonomies( array(), 'objects' );

		if ( count( $taxonomies ) === 0 ) {
			esc_html_e( 'No Multisite Taxonomies exist.', 'multitaxo' );
		}

		foreach ( $taxonomies as $tax_slug => $tax ) {
			echo '<li><a href="' . esc_url( 'admin.php?page=multisite_term_list_' . $tax_slug ) . '">' . esc_html( $tax->label ) . '</a></li>';
		}

		echo '</ul>
		</div>';
	}

	/**
	 * Display the list table screen in the network.
	 *
	 * @access public
	 * @return void
	 */
	public function display_multisite_taxonomy() {
		$current_screen = get_current_screen();

		if ( empty( $current_screen->taxonomy ) ) {
			wp_die( esc_html__( 'Invalid taxonomy.', 'multitaxo' ) );
		}

		$tax = get_multisite_taxonomy( $current_screen->taxonomy );

		if ( ! $tax ) {
			wp_die( esc_html__( 'Invalid taxonomy.', 'multitaxo' ) );
		}

		if ( ! in_array( $tax->name, get_multisite_taxonomies( array( 'show_ui' => true ) ), true ) ) {
			wp_die( esc_html__( 'Sorry, you are not allowed to edit terms in this taxonomy.', 'multitaxo' ) );
		}

		if ( ! current_user_can( $tax->cap->manage_multisite_terms ) ) {
			wp_die(
				'<h1>' . esc_html__( 'Cheatin&#8217; uh?', 'multitaxo' ) . '</h1>' .
				'<p>' . esc_html__( 'Sorry, you are not allowed to manage terms in this taxonomy.', 'multitaxo' ) . '</p>',
				403
			);
		}

		$pagenum = $this->list_table->get_pagenum();
		$title   = $tax->labels->name;
		$message = $this->get_update_message();
		$class   = ( isset( $_REQUEST['error'] ) ) ? 'error' : 'updated'; // phpcs:ignore WordPress.Security.NonceVerification

		wp_enqueue_script( 'admin-multisite-tags' );
		if ( current_user_can( $tax->cap->edit_multisite_terms ) ) {
			wp_enqueue_script( 'inline-edit-multisite-tax' );
		}
		?>

		<div class="wrap nosubsub">
		<h1 class="wp-heading-inline"><?php echo esc_html( $title ); ?></h1>

		<?php
		if ( isset( $_REQUEST['s'] ) && strlen( sanitize_text_field( wp_unslash( $_REQUEST['s'] ) ) ) ) { // phpcs:ignore WordPress.Security.NonceVerification
			/* translators: %s: search keywords */
			echo '<span class="subtitle">' . esc_html( sprintf( __( 'Search results for &#8220;%s&#8221;', 'multitaxo' ), sanitize_text_field( wp_unslash( $_REQUEST['s'] ) ) ) ) . '</span>'; // phpcs:ignore WordPress.Security.NonceVerification
		}
		?>

		<hr class="wp-header-end">

		<?php if ( $message ) : ?>
		<div id="message" class="<?php echo esc_attr( $class ); ?> notice is-dismissible"><p><?php echo esc_html( $message ); ?></p></div>
			<?php
			if ( isset( $_SERVER['REQUEST_URI'] ) ) {
				$_SERVER['REQUEST_URI'] = remove_query_arg( array( 'message', 'error' ), sanitize_text_field( wp_unslash( $_SERVER['REQUEST_URI'] ) ) );
			} else {
				$_SERVER['REQUEST_URI'] = '/';
			}
		endif;
		?>
		<div id="ajax-response"></div>

		<form class="search-form wp-clearfix" method="get">
		<input type="hidden" name="page" value="multisite_term_list_<?php echo esc_attr( $tax->name ); ?>" />
		<input type="hidden" name="multisite_taxonomy" value="<?php echo esc_attr( $tax->name ); ?>" />

		<?php $this->list_table->search_box( $tax->labels->search_items, 'tag' ); ?>

		</form>

		<div id="col-container" class="wp-clearfix">

		<div id="col-left">
		<div class="col-wrap">

		<?php
		if ( current_user_can( $tax->cap->edit_multisite_terms ) ) {
			/**
			 * Fires before the Add Term form for all taxonomies.
			 *
			 * The dynamic portion of the hook name, `$tax->name`, refers to the taxonomy slug.
			 *
			 * @since 3.0.0
			 *
			 * @param string $tax->name The taxonomy slug.
			 */
			do_action( "{$tax->name}_pre_add_form", $tax->name );
			?>

		<div class="form-wrap">
		<h2><?php echo esc_html( $tax->labels->add_new_item ); ?></h2>
		<form id="addtag" method="post" action="admin.php" class="validate"
			<?php
			/**
			 * Fires inside the Add Tag form tag.
			 *
			 * The dynamic portion of the hook name, `$tax->name`, refers to the taxonomy slug.
			 *
			 * @since 3.7.0
			 */
			do_action( "{$tax->name}_term_new_form_tag" );
			?>
		>
		<input type="hidden" name="action" value="add-multisite-tag" />
		<input type="hidden" name="page" value="multisite_term_list_<?php echo esc_attr( $tax->name ); ?>" />
		<input type="hidden" name="screen" value="<?php echo esc_attr( $current_screen->id ); ?>" />
		<input type="hidden" name="multisite_taxonomy" value="<?php echo esc_attr( $tax->name ); ?>" />
			<?php wp_nonce_field( 'add-multisite-tag', 'nonce-add-multisite-tag' ); ?>

		<div class="form-field form-required term-name-wrap">
			<label for="tag-name"><?php esc_html_x( 'Name', 'term name', 'multitaxo' ); ?></label>
			<input name="tag-name" id="tag-name" type="text" value="" size="40" aria-required="true" />
			<p><?php esc_html_e( 'The name is how it appears on your site.', 'multitaxo' ); ?></p>
		</div>
		<div class="form-field term-slug-wrap">
			<label for="tag-slug"><?php esc_html_e( 'Slug', 'multitaxo' ); ?></label>
			<input name="slug" id="tag-slug" type="text" value="" size="40" />
			<p><?php esc_html_e( 'The &#8220;slug&#8221; is the URL-friendly version of the name. It is usually all lowercase and contains only letters, numbers, and hyphens.', 'multitaxo' ); ?></p>
		</div>
			<?php if ( is_multisite_taxonomy_hierarchical( $tax->name ) ) : ?>
		<div class="form-field term-parent-wrap">
			<label for="parent"><?php echo esc_html( $tax->labels->parent_item ); ?></label>
				<?php
				$dropdown_args = array(
					'hide_empty'       => 0,
					'hide_if_empty'    => false,
					'taxonomy'         => $tax->name,
					'name'             => 'parent',
					'orderby'          => 'name',
					'hierarchical'     => true,
					'show_option_none' => esc_html__( 'None', 'multitaxo' ),
				);
				/**
				 * Filters the taxonomy parent drop-down on the Edit Term page.
				 *
				 * @since 3.7.0
				 * @since 4.2.0 Added `$context` parameter.
				 *
				 * @param array  $dropdown_args {
				 *     An array of taxonomy parent drop-down arguments.
				 *
				 *     @type int|bool $hide_empty       Whether to hide terms not attached to any posts. Default 0|false.
				 *     @type bool     $hide_if_empty    Whether to hide the drop-down if no terms exist. Default false.
				 *     @type string   $tax->name         The taxonomy slug.
				 *     @type string   $name             Value of the name attribute to use for the drop-down select element.
				 *                                      Default 'parent'.
				 *     @type string   $orderby          The field to order by. Default 'name'.
				 *     @type bool     $hierarchical     Whether the taxonomy is hierarchical. Default true.
				 *     @type string   $show_option_none Label to display if there are no terms. Default 'None'.
				 * }
				 * @param string $tax->name The taxonomy slug.
				 * @param string $context  Filter context. Accepts 'new' or 'edit'.
				 */
				$dropdown_args = apply_filters( 'taxonomy_parent_dropdown_args', $dropdown_args, $tax->name, 'new' );
				dropdown_multisite_taxonomy( $dropdown_args );
				?>
				<?php if ( 'category' === $tax->name ) : ?>
				<p><?php esc_html_e( 'Categories, unlike tags, can have a hierarchy. You might have a Jazz category, and under that have children categories for Bebop and Big Band. Totally optional.', 'multitaxo' ); ?></p>
			<?php else : ?>
				<p><?php esc_html_e( 'Assign a parent term to create a hierarchy. The term Jazz, for example, would be the parent of Bebop and Big Band.', 'multitaxo' ); ?></p>
			<?php endif; ?>
		</div>
		<?php endif; // End if: is multisite taxonomy hierarchical. ?>
		<div class="form-field term-description-wrap">
			<label for="tag-description"><?php esc_html_e( 'Description', 'multitaxo' ); ?></label>
			<textarea name="description" id="tag-description" rows="5" cols="40"></textarea>
			<p><?php esc_html_e( 'The description is not prominent by default; however, some themes may show it.', 'multitaxo' ); ?></p>
		</div>

			<?php
			if ( ! is_multisite_taxonomy_hierarchical( $tax->name ) ) {
				/**
				 * Fires after the Add Tag form fields for non-hierarchical taxonomies.
				 *
				 * @since 3.0.0
				 *
				 * @param string $tax->name The taxonomy slug.
				 */
				do_action( 'add_tag_form_fields', $tax->name );
			}
			/**
			 * Fires after the Add Term form fields.
			 *
			 * The dynamic portion of the hook name, `$tax->name`, refers to the taxonomy slug.
			 *
			 * @since 3.0.0
			 *
			 * @param string $tax->name The taxonomy slug.
			 */
			do_action( "{$tax->name}_add_form_fields", $tax->name );
			submit_button( $tax->labels->add_new_item );

			/**
			 * Fires at the end of the Add Term form for all taxonomies.
			 *
			 * The dynamic portion of the hook name, `$tax->name`, refers to the taxonomy slug.
			 *
			 * @since 3.0.0
			 *
			 * @param string $tax->name The taxonomy slug.
			 */
			do_action( "{$tax->name}_add_form", $tax->name );
			?>
		</form></div>
		<?php } ?>

		</div>
		</div><!-- /col-left -->

		<div id="col-right">
		<div class="col-wrap">

		<?php $this->list_table->views(); ?>

		<form id="posts-filter" method="post">
		<input type="hidden" name="taxonomy" value="<?php echo esc_attr( $tax->name ); ?>" />

		<?php $this->list_table->display(); ?>

		</form>

		<?php
		/**
		 * Fires after the taxonomy list table.
		 *
		 * The dynamic portion of the hook name, `$tax->name`, refers to the taxonomy slug.
		 *
		 * @since 3.0.0
		 *
		 * @param string $tax->name The taxonomy name.
		 */
		do_action( "after_multisite_{$tax->name}_table", $tax->name );
		?>

		</div>
		</div><!-- /col-right -->

		</div><!-- /col-container -->
		</div><!-- /wrap -->

		<?php if ( ! wp_is_mobile() ) : ?>
		<script type="text/javascript">
		try{document.forms.addtag['tag-name'].focus();}catch(e){}
		</script>
			<?php
		endif;

		$this->list_table->inline_edit();
	}

	/**
	 * Messages for tag updates.
	 *
	 * @access public
	 * @return string Message to return.
	 */
	public function get_update_message() {
		// 0 = unused. Messages start at index 1.
		$messages = array(
			0 => '',
			1 => esc_html__( 'Multisite term added.', 'multitaxo' ),
			2 => esc_html__( 'Multisite term deleted.', 'multitaxo' ),
			3 => esc_html__( 'Multisite term updated.', 'multitaxo' ),
			4 => esc_html__( 'Multisite term not added.', 'multitaxo' ),
			5 => esc_html__( 'Multisite term not updated.', 'multitaxo' ),
			6 => esc_html__( 'Multisite term deleted.', 'multitaxo' ),
		);

		// Filters the messages displayed when a tag is updated.
		$messages = apply_filters( 'multisite_term_updated_messages', $messages );

		$message = false;
		if ( isset( $_REQUEST['message'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification
			$msg = (int) absint( wp_unslash( $_REQUEST['message'] ) ); // phpcs:ignore WordPress.Security.NonceVerification
			if ( isset( $messages[ $msg ] ) ) {
				$message = $messages[ $msg ];
			}
		}

		return $message;
	}

	/**
	 * Display the edit screen for a tag.
	 *
	 * @access public
	 * @return void
	 */
	public function display_multisite_taxonomy_edit_screen() {
		$taxonomy = ( isset( $_GET['multisite_taxonomy'] ) ) ? sanitize_key( wp_unslash( $_GET['multisite_taxonomy'] ) ) : null; // phpcs:ignore WordPress.Security.NonceVerification

		// Check that we have something.
		if ( empty( $taxonomy ) ) {
			wp_die( esc_html__( 'Invalid taxonomy.', 'multitaxo' ) );
		}

		$tax = get_multisite_taxonomy( $taxonomy );

		if ( ! $tax ) {
			wp_die( esc_html__( 'Invalid taxonomy.', 'multitaxo' ) );
		}

		if ( ! in_array( $tax->name, get_multisite_taxonomies( array( 'show_ui' => true ) ), true ) ) {
			wp_die( esc_html__( 'Sorry, you are not allowed to edit terms in this taxonomy.', 'multitaxo' ) );
		}

		if ( ! current_user_can( $tax->cap->manage_multisite_terms ) ) {
			wp_die(
				'<h1>' . esc_html__( 'Cheatin&#8217; uh?', 'multitaxo' ) . '</h1>' .
				'<p>' . esc_html__( 'Sorry, you are not allowed to manage terms in this taxonomy.', 'multitaxo' ) . '</p>',
				403
			);
		}

		$multisite_term_id = ( isset( $_GET['multisite_term_id'] ) ) ? sanitize_key( wp_unslash( $_GET['multisite_term_id'] ) ) : null; // phpcs:ignore WordPress.Security.NonceVerification

		$term = get_multisite_term( $multisite_term_id, $taxonomy );

		if ( ! $term || is_wp_error( $term ) ) {
			wp_die( esc_html__( 'Invalid term.', 'multitaxo' ) );
		}

		$title   = $tax->labels->name;
		$message = $this->get_update_message();
		$class   = ( isset( $_REQUEST['error'] ) ) ? 'error' : 'updated'; // phpcs:ignore WordPress.Security.NonceVerification

		$args = array(
			'page' => 'multisite_term_list_' . $taxonomy,
		);

		$return_url = add_query_arg( $args, get_admin_url( null, 'network/admin.php' ) );

		/**
		 * Fires before the Edit Term form for all taxonomies.
		 *
		 * The dynamic portion of the hook name, `$taxonomy`, refers to
		 * the taxonomy slug.
		 *
		 * @since 3.0.0
		 *
		 * @param object $tag      Current taxonomy term object.
		 * @param string $taxonomy Current $taxonomy slug.
		 */
		do_action( "{$taxonomy}_multisite_pre_edit_form", $term, $tax );
		?>

		<div class="wrap">
		<h1><?php echo esc_html( $tax->labels->edit_item ); ?></h1>

		<?php if ( $message ) : ?>
		<div id="message" class="updated">
			<p><strong><?php echo esc_html( $message ); ?></strong></p>
			<p><a href="<?php echo esc_url( $return_url ); ?>">
			<?php
			/* translators: %s: taxonomy name */
			echo esc_html( sprintf( _x( '&larr; Back to %s', 'admin screen', 'multitaxo' ), $tax->labels->name ) );
			?>
			</a></p>
		</div>
		<?php endif; ?>

		<div id="ajax-response"></div>

		<form name="edittag" id="edittag" method="post" action="<?php echo esc_url( 'admin.php?page=multisite_term_list_' . $taxonomy ); ?>" class="validate"
		<?php
		/**
		 * Fires inside the Edit Term form tag.
		 *
		 * The dynamic portion of the hook name, `$taxonomy`, refers to the taxonomy slug.
		 *
		 * @since 3.7.0
		 */
		do_action( "{$taxonomy}_multisite_term_edit_form_tag" );
		?>
		>
		<input type="hidden" name="action" value="editedtag"/>
		<input type="hidden" name="page" value="multisite_term_list_<?php echo esc_attr( $taxonomy ); ?>"/>
		<input type="hidden" name="multisite_term_id" value="<?php echo esc_attr( $term->multisite_term_id ); ?>"/>
		<input type="hidden" name="multisite_taxonomy" value="<?php echo esc_attr( $taxonomy ); ?>"/>
		<?php
		wp_original_referer_field( true, 'previous' );
		wp_nonce_field( 'update-multisite-term_' . $term->multisite_term_id );

		/**
		 * Fires at the beginning of the Edit Term form.
		 *
		 * At this point, the required hidden fields and nonces have already been output.
		 *
		 * The dynamic portion of the hook name, `$taxonomy`, refers to the taxonomy slug.
		 *
		 * @since 4.5.0
		 *
		 * @param object $tag      Current taxonomy term object.
		 * @param string $taxonomy Current $taxonomy slug.
		 */
		do_action( "{$taxonomy}_multisite_term_edit_form_top", $term, $tax );
		?>
			<table class="form-table">
				<tr class="form-field form-required term-name-wrap">
					<th scope="row"><label for="name"><?php echo esc_html_x( 'Name', 'term name', 'multitaxo' ); ?></label></th>
					<?php
					if ( isset( $term->name ) ) {
						$term_name = $term->name;
					} else {
						$term_name = '';
					}
					?>
					<td><input name="name" id="name" type="text" value="<?php echo esc_attr( $term_name ); ?>" size="40" aria-required="true" />
					<p class="description"><?php esc_html_e( 'The name is how it appears on your site.', 'multitaxo' ); ?></p></td>
				</tr>
				<tr class="form-field term-slug-wrap">
					<th scope="row"><label for="slug"><?php esc_html_e( 'Slug', 'multitaxo' ); ?></label></th>
					<?php
					/**
					 * Filters the editable slug.
					 *
					 * Note: This is a multi-use hook in that it is leveraged both for editable
					 * post URIs and term slugs.
					 *
					 * @since 2.6.0
					 * @since 4.4.0 The `$tag` parameter was added.
					 *
					 * @param string         $slug The editable slug. Will be either a term slug or post URI depending
					 *                             upon the context in which it is evaluated.
					 * @param object|WP_Post $tag  Term or WP_Post object.
					 */
					$slug = isset( $term->slug ) ? apply_filters( 'editable_slug', $term->slug, $term ) : '';
					?>
					<td><input name="slug" id="slug" type="text" value="<?php echo esc_attr( $slug ); ?>" size="40" />
					<p class="description"><?php esc_html_e( 'The &#8220;slug&#8221; is the URL-friendly version of the name. It is usually all lowercase and contains only letters, numbers, and hyphens.', 'multitaxo' ); ?></p></td>
				</tr>
		<?php if ( is_multisite_taxonomy_hierarchical( $taxonomy ) ) : ?>
				<tr class="form-field term-parent-wrap">
					<th scope="row"><label for="parent"><?php echo esc_html( $tax->labels->parent_item ); ?></label></th>
					<td>
						<?php
						$dropdown_args = array(
							'hide_empty'       => 0,
							'hide_if_empty'    => false,
							'taxonomy'         => $taxonomy,
							'name'             => 'parent',
							'orderby'          => 'name',
							'selected'         => $term->parent,
							'exclude_tree'     => $term->multisite_term_id,
							'hierarchical'     => true,
							'show_option_none' => esc_html__( 'None', 'multitaxo' ),
						);

						/** This filter is documented in wp-admin/edit-tags.php */
						$dropdown_args = apply_filters( 'taxonomy_parent_dropdown_args', $dropdown_args, $taxonomy, 'edit' );
						dropdown_multisite_taxonomy( $dropdown_args );
						?>
						<?php if ( 'category' === $taxonomy ) : ?>
							<p class="description"><?php esc_html_e( 'Categories, unlike tags, can have a hierarchy. You might have a Jazz category, and under that have children categories for Bebop and Big Band. Totally optional.', 'multitaxo' ); ?></p>
						<?php else : ?>
							<p class="description"><?php esc_html_e( 'Assign a parent term to create a hierarchy. The term Jazz, for example, would be the parent of Bebop and Big Band.', 'multitaxo' ); ?></p>
						<?php endif; ?>
					</td>
				</tr>
		<?php endif; // End if : is taxonomy hierarchical. ?>
				<tr class="form-field term-description-wrap">
					<th scope="row"><label for="description"><?php esc_html_e( 'Description', 'multitaxo' ); ?></label></th>
					<td><textarea name="description" id="description" rows="5" cols="50" class="large-text"><?php echo esc_textarea( $term->description ); // textarea_escaped. ?></textarea>
					<p class="description"><?php esc_html_e( 'The description is not prominent by default; however, some themes may show it.', 'multitaxo' ); ?></p></td>
				</tr>
				<?php
				/**
				 * Fires after the Edit Term form fields are displayed.
				 *
				 * The dynamic portion of the hook name, `$taxonomy`, refers to
				 * the taxonomy slug.
				 *
				 * @since 3.0.0
				 *
				 * @param object $tag      Current taxonomy term object.
				 * @param string $taxonomy Current taxonomy slug.
				 */
				do_action( "{$taxonomy}_multisite_edit_form_fields", $term, $tax );
				?>
			</table>
		<?php

		/**
		 * Fires at the end of the Edit Term form for all taxonomies.
		 *
		 * The dynamic portion of the hook name, `$taxonomy`, refers to the taxonomy slug.
		 *
		 * @since 3.0.0
		 *
		 * @param object $tag      Current taxonomy term object.
		 * @param string $taxonomy Current taxonomy slug.
		 */
		do_action( "{$taxonomy}_multisite_edit_form", $term, $taxonomy );
		?>

		<div class="edit-tag-actions">

			<?php submit_button( esc_html__( 'Update', 'multitaxo' ), 'primary', null, false ); ?>

			<?php if ( current_user_can( 'delete_multisite_term', $term->multisite_term_id ) ) : ?>
				<span id="delete-link">
					<a class="delete" href="
					<?php
					echo esc_url(
						wp_nonce_url(
							add_query_arg(
								array(
									'page'               => 'multisite_term_list_' . $taxonomy,
									'action'             => 'delete',
									'multisite_taxonomy' => $taxonomy,
									'multisite_term_id'  => $term->multisite_term_id,
								),
								'admin.php'
							),
							'delete-multisite_term_' . $term->multisite_term_id
						)
					);
					?>
					"><?php esc_html_e( 'Delete', 'multitaxo' ); ?></a>
				</span>
			<?php endif; ?>

		</div>

		</form>
		</div>

		<?php if ( ! wp_is_mobile() ) : ?>
		<script type="text/javascript">
		try{document.forms.edittag.name.focus();}catch(e){}
		</script>
			<?php
		endif;
	}

	/**
	 * Allow us to perform multisite taxonomy or multisite term related actions when the before_delete_post action hook is triggered.
	 *
	 * @param int $post_id The deleted post ID.
	 * @return void
	 */
	public function before_delete_post_action_hook( $post_id ) {
		$post_id = absint( $post_id );

		$post = get_post( $post_id, OBJECT );

		if ( is_a( $post, 'WP_Post' ) ) {
			// When a post is deleted we want tp delete the multisite term relationships to avoid orphans records.
			delete_object_multisite_term_relationships( $post_id, get_object_multisite_taxonomies( $post ), get_current_blog_id() );
		}
	}
}
