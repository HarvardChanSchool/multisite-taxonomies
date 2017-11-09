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
		// We first create our custom database tables.
		$this->create_database_tables();
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
		$multisite_termmeta_table = $wpdb->prefix . 'multisite_termmeta';

		$multisite_termmeta_sql = 'CREATE TABLE IF NOT EXISTS `' . $multisite_termmeta_table . '` (
			meta_id bigint(20) unsigned NOT NULL auto_increment,
			multisite_term_id bigint(20) unsigned NOT NULL default "0",
			meta_key varchar(255) default NULL,
			meta_value longtext,
			PRIMARY KEY  (meta_id),
			KEY multisite_term_id (multisite_term_id),
			KEY meta_key (meta_key(' . $max_index_length . '))
		) ' . $charset_collate . ';';

		dbDelta( $multisite_termmeta_sql );

		// Table structure for table `wp_multisite_terms`.
		$multisite_terms_table = $wpdb->prefix . 'multisite_terms';

		$multisite_terms_sql = 'CREATE TABLE IF NOT EXISTS `' . $multisite_terms_table . '` (
			multisite_term_id bigint(20) unsigned NOT NULL auto_increment,
			name varchar(200) NOT NULL default "",
			slug varchar(200) NOT NULL default "",
			multisite_term_group bigint(10) NOT NULL default 0,
			PRIMARY KEY  (multisite_term_id),
			KEY slug (slug(' . $max_index_length . ')),
			KEY name (name(' . $max_index_length . '))
		) ' . $charset_collate . ';';

		dbDelta( $multisite_terms_sql );

		// Table structure for table `wp_multisite_term_relationships`.
		$multisite_term_relationships_table = $wpdb->prefix . 'multisite_term_relationships';

		$multisite_term_relationships_sql = 'CREATE TABLE IF NOT EXISTS `' . $multisite_term_relationships_table . '` (
			blog_id bigint(20) unsigned NOT NULL default 0,
			object_id bigint(20) unsigned NOT NULL default 0,
			multisite_term_multisite_taxonomy_id bigint(20) unsigned NOT NULL default 0,
			multisite_term_order int(11) NOT NULL default 0,
			PRIMARY KEY (blog_id,object_id,multisite_term_multisite_taxonomy_id),
			KEY multisite_term_multisite_taxonomy_id (multisite_term_multisite_taxonomy_id)
		) ' . $charset_collate . ';';

		dbDelta( $multisite_term_relationships_sql );

		// Table structure for table `wp_multisite_term_multisite_taxonomy`.
		$multisite_term_multisite_taxonomy_table = $wpdb->prefix . 'multisite_term_multisite_taxonomy';

		$multisite_term_multisite_taxonomy_sql = 'CREATE TABLE IF NOT EXISTS `' . $multisite_term_multisite_taxonomy_table . '` (
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

		dbDelta( $multisite_term_multisite_taxonomy_sql );
	}

	/**
	 * Remove our custom database tables on plugin deactivation.
	 *
	 * @access public
	 * @return void
	 */
	public function delete_database_tables() {
	}

	/**
	 * Add the metowrk admin menu to the terms page.
	 *
	 * @access public
	 * @return void
	 */
	public function add_network_menu_terms() {
		$screen = add_menu_page( esc_html__( 'Multisite Tags', 'multitaxo' ), esc_html__( 'Multisite Tags', 'multitaxo' ), 'manage_network_options', 'multisite_tags_list', array( $this, 'display_multisite_network_tax' ), 'dashicons-tag', 22 );
		add_action( 'load-' . $screen, array( $this, 'load_multisite_network_tax' ) );

		$taxonomies = get_multisite_taxonomies( array(), 'objects' );

		foreach ( $taxonomies as $tax_slug => $tax ) {
			$screen_hook = add_submenu_page( 'multisite_tags_list', $tax->label, $tax->label, 'manage_network_options', 'multisite_tags_list&mtax=' . $tax_slug, '__return_null' );
			add_action( 'load-' . $screen_hook, array( $this, 'load_multisite_network_tax' ) );
		}
	}

	/**
	 * Display the list table screen in the network.
	 *
	 * @access public
	 * @return void
	 */
	public function load_multisite_network_tax() {
		$taxnow = ( isset( $_GET['mtax'] ) ) ? sanitize_key( wp_unslash( $_GET['mtax'] ) ) : null;

		if ( empty( $taxnow ) ) {
			wp_die( esc_html__( 'Invalid taxonomy.', 'multitaxo' ) );
		}

		$tax = get_multisite_taxonomy( $taxnow );

		if ( ! $tax ) {
			wp_die( esc_html__( 'Invalid taxonomy.', 'multitaxo' ) );
		}

		if ( ! in_array( $tax->name, get_multisite_taxonomies( array( 'show_ui' => true ) ) ) ) {
			wp_die( esc_html__( 'Sorry, you are not allowed to edit terms in this taxonomy.', 'multitaxo' ) );
		}

		if ( ! current_user_can( $tax->cap->manage_multisite_terms ) ) {
			wp_die(
				'<h1>' . esc_html__( 'Cheatin&#8217; uh?', 'multitaxo' ) . '</h1>' .
				'<p>' . esc_html__( 'Sorry, you are not allowed to manage terms in this taxonomy.', 'multitaxo' ) . '</p>',
				403
			);
		}

		/**
		 * $post_type is set when the WP_Terms_List_Table instance is created
		 *
		 * @global string $post_type
		 */
		$this->list_table = new Multisite_Terms_List_Table();

		$pagenum          = $this->list_table->get_pagenum();
		$title            = $tax->labels->name;

		$location = false;

		$referer = wp_get_referer();

		if ( ! $referer ) { // For POST requests.
			$referer = wp_unslash( $_SERVER['REQUEST_URI'] );
		}

		$referer = remove_query_arg( array( '_wp_http_referer', '_wpnonce', 'error', 'message', 'paged' ), $referer );

		switch ( $this->list_table->current_action() ) {

			case 'add-tag':
				check_admin_referer( 'add-tag', '_wpnonce_add-tag' );
				if ( ! current_user_can( $tax->cap->edit_terms ) ) {
					wp_die(
						'<h1>' . esc_html__( 'Cheatin&#8217; uh?', 'multitaxo' ) . '</h1>' .
						'<p>' . esc_html__( 'Sorry, you are not allowed to create terms in this taxonomy.', 'multitaxo' ) . '</p>',
						403
					);
				}

				$ret = wp_insert_multisite_term( $_POST['tag-name'], $tax->name, $_POST );

				if ( $ret && ! is_wp_error( $ret ) ) {
					$location = add_query_arg( 'message', 1, $referer );
				} else {
						$location = add_query_arg(
							array(
								'error'   => true,
								'message' => 4,
							), $referer
						);
				}

				break;
			case 'delete':
				if ( ! isset( $_REQUEST['tag_ID'] ) ) {
					break;
				}

				$tag_ID = (int) $_REQUEST['tag_ID'];

				check_admin_referer( 'delete-tag_' . $tag_ID );

				if ( ! current_user_can( 'delete_term', $tag_ID ) ) {
					wp_die(
						'<h1>' . esc_html__( 'Cheatin&#8217; uh?', 'multitaxo' ) . '</h1>' .
						'<p>' . esc_html__( 'Sorry, you are not allowed to delete this item.', 'multitaxo' ) . '</p>',
						403
					);
				}

				wp_delete_multisite_term( $tag_ID, $tax->name );

				$location = add_query_arg( 'message', 2, $referer );

				// When deleting a term, prevent the action from redirecting back to a term that no longer exists.
				$location = remove_query_arg( array( 'tag_ID', 'action' ), $location );

				break;
			case 'bulk-delete':
				check_admin_referer( 'bulk-tags' );

				if ( ! current_user_can( $tax->cap->delete_terms ) ) {
					wp_die(
						'<h1>' . esc_html__( 'Cheatin&#8217; uh?', 'multitaxo' ) . '</h1>' .
						'<p>' . esc_html__( 'Sorry, you are not allowed to delete these items.', 'multitaxo' ) . '</p>',
						403
					);
				}

				$tags = (array) $_REQUEST['delete_tags'];

				foreach ( $tags as $tag_ID ) {
					wp_delete_multisite_term( $tag_ID, $tax->name );
				}

				$location = add_query_arg( 'message', 6, $referer );

				break;
			case 'edit':
				if ( ! isset( $_REQUEST['tag_ID'] ) ) {
					break;
				}

				$term_id = (int) $_REQUEST['tag_ID'];
				$term    = get_term( $term_id );

				if ( ! $term instanceof WP_Term ) {
					wp_die( esc_html__( 'You attempted to edit an item that doesn&#8217;t exist. Perhaps it was deleted?', 'multitaxo' ) );
				}

				wp_redirect( esc_url_raw( get_multisite_edit_term_link( $term_id, $tax->name, $post_type ) ) );

				exit;
			case 'editedtag':
				$tag_ID = (int) $_POST['tag_ID'];

				check_admin_referer( 'update-tag_' . $tag_ID );

				if ( ! current_user_can( 'edit_term', $tag_ID ) ) {
					wp_die(
						'<h1>' . esc_html__( 'Cheatin&#8217; uh?', 'multitaxo' ) . '</h1>' .
						'<p>' . esc_html__( 'Sorry, you are not allowed to edit this item.', 'multitaxo' ) . '</p>',
						403
					);
				}

				$tag = get_term( $tag_ID, $tax->name );

				if ( ! $tag ) {
					wp_die( esc_html__( 'You attempted to edit an item that doesn&#8217;t exist. Perhaps it was deleted?', 'multitaxo' ) );
				}

				$ret = wp_update_multisite_term( $tag_ID, $tax->name, $_POST );

				if ( $ret && ! is_wp_error( $ret ) ) {
					$location = add_query_arg( 'message', 3, $referer );
				} else {
					$location = add_query_arg(
						array(
							'error'   => true,
							'message' => 5,
						), $referer
					);
				}

				break;
			default:
				if ( ! $this->list_table->current_action() || ! isset( $_REQUEST['delete_tags'] ) ) {
					break;
				}

				check_admin_referer( 'bulk-tags' );
				$tags = (array) $_REQUEST['delete_tags'];

				/** This action is documented in wp-admin/edit-comments.php */
				$location = apply_filters( 'handle_bulk_actions-' . get_current_screen()->id, $location, $this->list_table->current_action(), $tags );
				break;
		}

		if ( ! $location && ! empty( $_REQUEST['_wp_http_referer'] ) ) {
			$location = remove_query_arg( array( '_wp_http_referer', '_wpnonce' ), wp_unslash( $_SERVER['REQUEST_URI'] ) );
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
			 * @param object $tax      The taxonomy object.
			 */
			wp_redirect( apply_filters( 'redirect_term_location', $location, $tax ) );
			exit;
		}

		$this->list_table->prepare_items();

		$total_pages = $this->list_table->get_pagination_arg( 'total_pages' );

		if ( $pagenum > $total_pages && $total_pages > 0 ) {
			wp_redirect( add_query_arg( 'paged', $total_pages ) );
			exit;
		}

		wp_enqueue_script( 'admin-tags' );
		if ( current_user_can( $tax->cap->edit_multisite_terms ) ) {
			wp_enqueue_script( 'inline-edit-tax' );
		}
	}

	/**
	 * Display the list table screen in the network.
	 *
	 * @access public
	 * @return void
	 */
	public function display_multisite_network_tax() {
		$taxnow = ( isset( $_GET['mtax'] ) ) ? sanitize_key( wp_unslash( $_GET['mtax'] ) ) : null;

		if ( empty( $taxnow ) ) {
			wp_die( esc_html__( 'Invalid taxonomy.', 'multitaxo' ) );
		}

		$tax = get_multisite_taxonomy( $taxnow );

		if ( ! $tax ) {
			wp_die( esc_html__( 'Invalid taxonomy.', 'multitaxo' ) );
		}

		if ( ! in_array( $tax->name, get_multisite_taxonomies( array( 'show_ui' => true ) ) ) ) {
			wp_die( esc_html__( 'Sorry, you are not allowed to edit terms in this taxonomy.', 'multitaxo' ) );
		}

		if ( ! current_user_can( $tax->cap->manage_multisite_terms ) ) {
			wp_die(
				'<h1>' . esc_html__( 'Cheatin&#8217; uh?', 'multitaxo' ) . '</h1>' .
				'<p>' . esc_html__( 'Sorry, you are not allowed to manage terms in this taxonomy.', 'multitaxo' ) . '</p>',
				403
			);
		}

		/**
		 * $post_type is set when the WP_Terms_List_Table instance is created
		 *
		 * @global string $post_type
		 */
		$pagenum          = $this->list_table->get_pagenum();
		$title            = $tax->labels->name;

		add_screen_option(
			'per_page', array(
				'default' => 20,
				'option'  => 'edit_multi_' . $tax->name . '_per_page',
			)
		);

		get_current_screen()->set_screen_reader_content(
			array(
				'heading_pagination' => $tax->labels->items_list_navigation,
				'heading_list'       => $tax->labels->items_list,
			)
		);

		/** Also used by the Edit Tag  form */
		require_once ABSPATH . 'wp-admin/includes/edit-tag-messages.php';

		$class = ( isset( $_REQUEST['error'] ) ) ? 'error' : 'updated';
		?>

		<div class="wrap nosubsub">
		<h1 class="wp-heading-inline"><?php echo esc_html( $title ); ?></h1>

		<?php
		if ( isset( $_REQUEST['s'] ) && strlen( $_REQUEST['s'] ) ) {
			/* translators: %s: search keywords */
			printf( '<span class="subtitle">' . esc_html__( 'Search results for &#8220;%s&#8221;', 'multitaxo' ) . '</span>', esc_html( wp_unslash( $_REQUEST['s'] ) ) );
		}
		?>

		<hr class="wp-header-end">

		<?php if ( $message ) : ?>
		<div id="message" class="<?php echo $class; ?> notice is-dismissible"><p><?php echo $message; ?></p></div>
		<?php
		$_SERVER['REQUEST_URI'] = remove_query_arg( array( 'message', 'error' ), $_SERVER['REQUEST_URI'] );
		endif;
		?>
		<div id="ajax-response"></div>

		<form class="search-form wp-clearfix" method="get">
		<input type="hidden" name="taxonomy" value="<?php echo esc_attr( $tax->name ); ?>" />
		<input type="hidden" name="post_type" value="<?php echo esc_attr( $post_type ); ?>" />

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
		<h2><?php echo $tax->labels->add_new_item; ?></h2>
		<form id="addtag" method="post" action="edit-tags.php" class="validate"
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
		<input type="hidden" name="action" value="add-tag" />
		<input type="hidden" name="screen" value="<?php echo esc_attr( $current_screen->id ); ?>" />
		<input type="hidden" name="taxonomy" value="<?php echo esc_attr( $tax->name ); ?>" />
		<input type="hidden" name="post_type" value="<?php echo esc_attr( $post_type ); ?>" />
		<?php wp_nonce_field( 'add-tag', '_wpnonce_add-tag' ); ?>

		<div class="form-field form-required term-name-wrap">
			<label for="tag-name"><?php _ex( 'Name', 'term name' ); ?></label>
			<input name="tag-name" id="tag-name" type="text" value="" size="40" aria-required="true" />
			<p><?php _e( 'The name is how it appears on your site.' ); ?></p>
		</div>
		<?php if ( ! global_terms_enabled() ) : ?>
		<div class="form-field term-slug-wrap">
			<label for="tag-slug"><?php _e( 'Slug' ); ?></label>
			<input name="slug" id="tag-slug" type="text" value="" size="40" />
			<p><?php _e( 'The &#8220;slug&#8221; is the URL-friendly version of the name. It is usually all lowercase and contains only letters, numbers, and hyphens.' ); ?></p>
		</div>
		<?php endif; // global_terms_enabled() ?>
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
				'show_option_none' => __( 'None' ),
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
			wp_dropdown_categories( $dropdown_args );
			?>
			<?php if ( 'category' == $tax->name ) : ?>
				<p><?php _e( 'Categories, unlike tags, can have a hierarchy. You might have a Jazz category, and under that have children categories for Bebop and Big Band. Totally optional.' ); ?></p>
			<?php else : ?>
				<p><?php _e( 'Assign a parent term to create a hierarchy. The term Jazz, for example, would be the parent of Bebop and Big Band.' ); ?></p>
			<?php endif; ?>
		</div>
		<?php endif; // is_taxonomy_hierarchical() ?>
		<div class="form-field term-description-wrap">
			<label for="tag-description"><?php _e( 'Description' ); ?></label>
			<textarea name="description" id="tag-description" rows="5" cols="40"></textarea>
			<p><?php _e( 'The description is not prominent by default; however, some themes may show it.' ); ?></p>
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
		<input type="hidden" name="post_type" value="<?php echo esc_attr( $post_type ); ?>" />

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
		do_action( "after-{$tax->name}-table", $tax->name );
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
}
