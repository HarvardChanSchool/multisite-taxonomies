<?php
/**
 * Multisite_Taxonomy class
 *
 * @package multitaxo
 * @since 0.1
 */

/**
 * Core class used for interacting with multisite taxonomies.
 */
class Multisite_Taxonomy {
	/**
	 * Multisite taxonomy key.
	 *
	 * @access public
	 * @var string
	 */
	public $name;

	/**
	 * Name of the multisite taxonomy shown in the menu. Usually plural.
	 *
	 * @access public
	 * @var string
	 */
	public $label;

	/**
	 * An array of labels for this multisite taxonomy.
	 *
	 * @access public
	 * @var object
	 */
	public $labels = array();

	/**
	 * A short descriptive summary of what the multisite taxonomy is for.
	 *
	 * @access public
	 * @var string
	 */
	public $description = '';

	/**
	 * Whether a multisite taxonomy is intended for use publicly either via the admin interface or by front-end users.
	 *
	 * @access public
	 * @var bool
	 */
	public $public = true;

	/**
	 * Whether the multisite taxonomy is publicly queryable.
	 *
	 * @access public
	 * @var bool
	 */
	public $publicly_queryable = true;

	/**
	 * Whether the multisite taxonomy is hierarchical.
	 *
	 * @access public
	 * @var bool
	 */
	public $hierarchical = false;

	/**
	 * Whether to generate and allow a UI for managing terms in this multisite taxonomy in the admin.
	 *
	 * @access public
	 * @var bool
	 */
	public $show_ui = true;

	/**
	 * Whether to show the multisite taxonomy in the admin menu.
	 *
	 * If true, the multisite taxonomy is shown as a submenu of the object type menu. If false, no menu is shown.
	 *
	 * @access public
	 * @var bool
	 */
	public $show_in_menu = true;

	/**
	 * Whether the multisite taxonomy is available for selection in navigation menus.
	 *
	 * @access public
	 * @var bool
	 */
	public $show_in_nav_menus = true;

	/**
	 * Whether to list the multisite taxonomy in the tag cloud widget controls.
	 *
	 * @access public
	 * @var bool
	 */
	public $show_multisite_terms_cloud = true;

	/**
	 * Whether to show the multisite taxonomy in the quick/bulk edit panel.
	 *
	 * @access public
	 * @var bool
	 */
	public $show_in_quick_edit = true;

	/**
	 * Whether to display a column for the multisite taxonomy on its post type listing screens.
	 *
	 * @access public
	 * @var bool
	 */
	public $show_admin_column = false;

	/**
	 * The callback function for the meta box display.
	 *
	 * @access public
	 * @var bool|callable
	 */
	public $meta_box_cb = null;

	/**
	 * An array of object types this multisite taxonomy is registered for.
	 *
	 * @access public
	 * @var array
	 */
	public $object_type = null;

	/**
	 * Capabilities for this multisite taxonomy.
	 *
	 * @access public
	 * @var array
	 */
	public $cap;

	/**
	 * Rewrites information for this multisite taxonomy.
	 *
	 * @access public
	 * @var array|false
	 */
	public $rewrite;

	/**
	 * Query var string for this multisite taxonomy.
	 *
	 * @access public
	 * @var string|false
	 */
	public $query_var;

	/**
	 * Function that will be called when the count is updated.
	 *
	 * @access public
	 * @var callable
	 */
	public $update_count_callback;

	/**
	 * Whether it is a built-in multisite taxonomy.
	 *
	 * @access public
	 * @var bool
	 */
	public $_builtin;

	/**
	 * Constructor.
	 *
	 * @access public
	 *
	 * @global WP $wp WP instance.
	 *
	 * @param string       $multisite_taxonomy    Multisite taxonomy key, must not exceed 32 characters.
	 * @param array|string $object_type Name of the object type for the multisite taxonomy object.
	 * @param array|string $args        Optional. Array or query string of arguments for registering a multisite taxonomy.
	 *                                  Default empty array.
	 */
	public function __construct( $multisite_taxonomy, $object_type, $args = array() ) {
		$this->name = $multisite_taxonomy;

		$this->set_props( $object_type, $args );

		// callback for adding a MS term.
		add_action( 'wp_ajax_add-multisite-hierarchical-term-' . $this->name, 'ajax_add_multisite_hierarchical_term' );
	}

	/**
	 * Sets multisite taxonomy properties.
	 *
	 * @access public
	 *
	 * @param array|string $object_type Name of the object type for the multisite taxonomy object.
	 * @param array|string $args        Array or query string of arguments for registering a multisite taxonomy.
	 */
	public function set_props( $object_type, $args ) {
		$args = wp_parse_args( $args );

		/**
		 * Filters the arguments for registering a multisite taxonomy.
		 *
		 * @param array  $args        Array of arguments for registering a multisite taxonomy.
		 * @param string $multisite_taxonomy    Multisite taxonomy key.
		 * @param array  $object_type Array of names of object types for the multisite taxonomy.
		 */
		$args = apply_filters( 'register_multisite_taxonomy_args', $args, $this->name, (array) $object_type );

		$defaults = array(
			'labels'                     => array(),
			'description'                => '',
			'public'                     => true,
			'publicly_queryable'         => null,
			'hierarchical'               => false,
			'show_ui'                    => null,
			'show_in_menu'               => null,
			'show_in_nav_menus'          => null,
			'show_multisite_terms_cloud' => null,
			'show_in_quick_edit'         => null,
			'show_admin_column'          => false,
			'meta_box_cb'                => null,
			'capabilities'               => array(),
			'rewrite'                    => true,
			'query_var'                  => $this->name,
			'update_count_callback'      => '',
			'show_in_rest'               => false,
			'rest_base'                  => false,
			'rest_controller_class'      => false,
			'_builtin'                   => false,
		);

		$args = array_merge( $defaults, $args );

		// If not set, default to the setting for public.
		if ( null === $args['publicly_queryable'] ) {
			$args['publicly_queryable'] = $args['public'];
		}

		if ( false !== $args['query_var'] && ( is_admin() || false !== $args['publicly_queryable'] ) ) {
			if ( true === $args['query_var'] ) {
				$args['query_var'] = $this->name;
			} else {
				$args['query_var'] = sanitize_title_with_dashes( $args['query_var'] );
			}
		} else {
			// Force query_var to false for non-public taxonomies.
			$args['query_var'] = false;
		}

		if ( false !== $args['rewrite'] && ( is_admin() || '' !== get_option( 'permalink_structure' ) ) ) {
			$args['rewrite'] = wp_parse_args(
				$args['rewrite'], array(
					// With Front needs to be false to avoid prefixing it with blog/.
					'with_front'   => false,
					'hierarchical' => false,
					'ep_mask'      => EP_NONE,
				)
			);

			if ( empty( $args['rewrite']['slug'] ) ) {
				$args['rewrite']['slug'] = sanitize_title_with_dashes( $this->name );
			}
		}

		// If not set, default to the setting for public.
		if ( null === $args['show_ui'] ) {
			$args['show_ui'] = $args['public'];
		}

		// If not set, default to the setting for show_ui.
		if ( null === $args['show_in_menu'] || ! $args['show_ui'] ) {
			$args['show_in_menu'] = $args['show_ui'];
		}

		// If not set, default to the setting for public.
		if ( null === $args['show_in_nav_menus'] ) {
			$args['show_in_nav_menus'] = $args['public'];
		}

		// If not set, default to the setting for show_ui.
		if ( null === $args['show_multisite_terms_cloud'] ) {
			$args['show_multisite_terms_cloud'] = $args['show_ui'];
		}

		// If not set, default to the setting for show_ui.
		if ( null === $args['show_in_quick_edit'] ) {
			$args['show_in_quick_edit'] = $args['show_ui'];
		}

		$default_caps = array(
			'manage_multisite_terms' => 'manage_categories',
			'edit_multisite_terms'   => 'manage_categories',
			'delete_multisite_terms' => 'manage_categories',
			'assign_multisite_terms' => 'edit_posts',
		);

		$args['cap'] = (object) array_merge( $default_caps, $args['capabilities'] );
		unset( $args['capabilities'] );

		$args['object_type'] = array_unique( (array) $object_type );

		// If not set, use the default meta box.
		if ( null === $args['meta_box_cb'] ) {
			if ( $args['hierarchical'] ) {
				$args['meta_box_cb'] = 'post_multisite_term_hierarchical_meta_box';
			} else {
				$args['meta_box_cb'] = 'post_multisite_term_meta_box';
			}
		}

		$args['name'] = $this->name;

		foreach ( $args as $property_name => $property_value ) {
			$this->$property_name = $property_value;
		}

		$this->labels = get_multisite_taxonomy_labels( $this );
		$this->label  = $this->labels->name;
	}

	/**
	 * Adds the necessary rewrite rules for the multisite taxonomy.
	 *
	 * @access public
	 *
	 * @global WP $wp Current WordPress environment instance.
	 */
	public function add_rewrite_rules() {
		global $wp;

		// Our base rewrite for all multisite tax plugins.
		$base_rewrite = apply_filters( 'multisite_taxonomy_base_url_slug', 'multitaxo' );

		// This cannot be empty.
		if ( empty( $base_rewrite ) ) {
			$base_rewrite = 'multitaxo';
		}

		// Non-publicly queryable taxonomies should not register query vars, except in the admin.
		if ( false !== $this->query_var && $wp ) {
			$wp->add_query_var( $this->query_var );
		}

		if ( false !== $this->rewrite && ( is_admin() || '' !== get_option( 'permalink_structure' ) ) ) {
			if ( $this->hierarchical && $this->rewrite['hierarchical'] ) {
				$tag = '(.+?)';
			} else {
				$tag = '([^/]+)';
			}

			add_rewrite_tag( "%$this->name%", $tag, $this->query_var ? "{$this->query_var}=" : "multisite_taxonomy=$this->name&multisite_term=" );
			add_permastruct( $this->name, "{$base_rewrite}/{$this->rewrite['slug']}/%$this->name%", $this->rewrite );
		}
	}

	/**
	 * Removes any rewrite rules, permastructs, and rules for the multisite taxonomy.
	 *
	 * @access public
	 *
	 * @global WP $wp Current WordPress environment instance.
	 */
	public function remove_rewrite_rules() {
		global $wp;

		// Remove query var.
		if ( false !== $this->query_var ) {
			$wp->remove_query_var( $this->query_var );
		}

		// Remove rewrite tags and permastructs.
		if ( false !== $this->rewrite ) {
			remove_rewrite_tag( "%$this->name%" );
			remove_permastruct( $this->name );
		}
	}

	/**
	 * Registers the ajax callback for the meta box.
	 *
	 * @access public
	 */
	public function add_hooks() {
		add_filter( 'wp_ajax_add-' . $this->name, '_wp_ajax_add_hierarchical_multisite_term' );
	}

	/**
	 * Removes the ajax callback for the meta box.
	 *
	 * @access public
	 */
	public function remove_hooks() {
		remove_filter( 'wp_ajax_add-' . $this->name, '_wp_ajax_add_hierarchical_multisite_term' );
	}
}
