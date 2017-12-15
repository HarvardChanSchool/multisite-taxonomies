<?php
/**
 * Multisite Taxonomy API
 *
 * @package multitaxo
 */

/**
 * Retrieves a list of registered multisite taxonomy names or objects.
 *
 * @global array $multisite_taxonomies The registered multisite taxonomies.
 *
 * @param array  $args     Optional. An array of `key => value` arguments to match against the multisite taxonomy objects.
 *                         Default empty array.
 * @param string $output   Optional. The type of output to return in the array. Accepts either multisite taxonomy 'names'
 *                         or 'objects'. Default 'names'.
 * @param string $operator Optional. The logical operation to perform. Accepts 'and' or 'or'. 'or' means only
 *                         one element from the array needs to match; 'and' means all elements must match.
 *                         Default 'and'.
 * @return array A list of multisite taxonomy names or objects.
 */
function get_multisite_taxonomies( $args = array(), $output = 'names', $operator = 'and' ) {
	global $multisite_taxonomies;

	$field = ( 'names' === $output ) ? 'name' : false;

	return wp_filter_object_list( $multisite_taxonomies, $args, $operator, $field );
}

/**
 * Return the names or objects of the multisite taxonomies which are registered for the requested object or object type, such as
 * a post object or post type name.
 *
 * Example:
 *
 *     $multisite_taxonomies= get_object_multisite_taxonomies( 'post' );
 *
 * This results in:
 *
 *     Array( 'category', 'post_tag' )
 *
 * @global array $multisite_taxonomies The registered multisite taxonomies.
 *
 * @param array|string|WP_Post $object Name of the type of multisite taxonomy object, or an object (row from posts).
 * @param string               $output Optional. The type of output to return in the array. Accepts either
 *                                     multisite taxonomy 'names' or 'objects'. Default 'names'.
 * @return array The names of all multisite taxonomy of $object_type.
 */
function get_object_multisite_taxonomies( $object, $output = 'names' ) {
	global $multisite_taxonomies;

	if ( is_object( $object ) ) {
		if ( 'attachment' === $object->post_type ) {
			return ''; // Currently don't support attachments.
		}
		$object = $object->post_type;
	}

	$object = (array) $object;

	$taxonomies = array();
	foreach ( (array) $multisite_taxonomies as $multi_tax_name => $multi_tax_obj ) {
		if ( array_intersect( $object, (array) $multi_tax_obj->object_type ) ) {
			if ( 'names' === $output ) {
				$taxonomies[] = $multi_tax_name;
			} else {
				$taxonomies[ $multi_tax_name ] = $multi_tax_obj;
			}
		}
	}

	return $taxonomies;
}

/**
 * Retrieves the multisite taxonomy object of $multisite_taxonomy.
 *
 * The get_multisite_taxonomy function will first check that the parameter string given
 * is a multisite taxonomy object and if it is, it will return it.
 *
 * @global array $multisite_taxonomies The registered multisite taxonomies.
 *
 * @param string $multisite_taxonomy Name of multisite taxonomy object to return.
 * @return Multisite_Taxonomy|false The Multisite Taxonomy Object or false if $multisite_taxonomy doesn't exist.
 */
function get_multisite_taxonomy( $multisite_taxonomy ) {
	global $multisite_taxonomies;

	if ( ! multisite_taxonomy_exists( $multisite_taxonomy ) ) {
		return false;
	}

	return $multisite_taxonomies[ $multisite_taxonomy ];
}

/**
 * Checks that the multisite taxonomy name exists.
 *
 * @global array $multisite_taxonomies The registered multisite taxonomies.
 *
 * @param string $multisite_taxonomy Name of multisite taxonomy object.
 * @return bool Whether the multisite taxonomy exists.
 */
function multisite_taxonomy_exists( $multisite_taxonomy ) {
	global $multisite_taxonomies;

	return isset( $multisite_taxonomies[ $multisite_taxonomy ] );
}

/**
 * Whether the multisite taxonomy object is hierarchical.
 *
 * Checks to make sure that the multisite taxonomy is an object first. Then Gets the
 * object, and finally returns the hierarchical value in the object.
 *
 * A false return value might also mean that the multisite taxonomy does not exist.
 *
 * @param string $multisite_taxonomy Name of multisite taxonomy object.
 * @return bool Whether the multisite taxonomy is hierarchical.
 */
function is_multisite_taxonomy_hierarchical( $multisite_taxonomy ) {
	if ( ! multisite_taxonomy_exists( $multisite_taxonomy ) ) {
		return false;
	}
	$multisite_taxonomy = get_multisite_taxonomy( $multisite_taxonomy );
	return $multisite_taxonomy->hierarchical;
}

/**
 * Creates or modifies a multisite taxonomy object.
 *
 * Note: Do not use before the {@see 'init'} hook.
 *
 * A simple function for creating or modifying a multisite taxonomy object based on the
 * parameters given. The function will accept an array (third optional
 * parameter), along with strings for the multisite taxonomy name and another string for
 * the object type.
 *
 * @global array $multisite_taxonomies Registered multisite taxonomies.
 *
 * @param string       $multisite_taxonomy    Multisite Taxonomy key, must not exceed 32 characters.
 * @param array|string $object_type Object type or array of object types with which the multisite taxonomy should be associated.
 * @param array|string $args        {
 *     Optional. Array or query string of arguments for registering a multisite taxonomy.
 *
 *     @type array         $labels                An array of labels for this multisite taxonomy. By default, Tag labels are
 *                                                used for non-hierarchical multisite taxonomies, and Category labels are used
 *                                                for hierarchical multisite taxonomies. See accepted values in
 *                                                get_multisite_taxonomy_labels(). Default empty array.
 *     @type string        $description           A short descriptive summary of what the multisite taxonomy is for. Default empty.
 *     @type bool          $public                Whether a multisite taxonomy is intended for use publicly either via
 *                                                the admin interface or by front-end users. The default settings
 *                                                of `$publicly_queryable`, `$show_ui`, and `$show_in_nav_menus`
 *                                                are inherited from `$public`.
 *     @type bool          $publicly_queryable    Whether the multisite taxonomy is publicly queryable.
 *                                                If not set, the default is inherited from `$public`
 *     @type bool          $hierarchical          Whether the multisite taxonomy is hierarchical. Default false.
 *     @type bool          $show_ui               Whether to generate and allow a UI for managing terms in this multisite taxonomy in
 *                                                the admin. If not set, the default is inherited from `$public`
 *                                                (default true).
 *     @type bool          $show_in_menu          Whether to show the multisite taxonomy in the admin menu. If true, the multisite taxonomy is
 *                                                shown as a submenu of the object type menu. If false, no menu is shown.
 *                                                `$show_ui` must be true. If not set, default is inherited from `$show_ui`
 *                                                (default true).
 *     @type bool          $show_in_nav_menus     Makes this multisite taxonomy available for selection in navigation menus. If not
 *                                                set, the default is inherited from `$public` (default true).
 *     @type bool          $show_in_rest          Whether to include the multisite taxonomy in the REST API.
 *     @type string        $rest_base             To change the base url of REST API route. Default is $multisite_taxonomy.
 *     @type string        $rest_controller_class REST API Controller class name. Default is 'WP_REST_Terms_Controller'.
 *     @type bool          $show_tagcloud         Whether to list the multisite taxonomy in the Tag Cloud Widget controls. If not set,
 *                                                the default is inherited from `$show_ui` (default true).
 *     @type bool          $show_in_quick_edit    Whether to show the multisite taxonomy in the quick/bulk edit panel. It not set,
 *                                                the default is inherited from `$show_ui` (default true).
 *     @type bool          $show_admin_column     Whether to display a column for the multisite taxonomy on its post type listing
 *                                                screens. Default false.
 *     @type bool|callable $meta_box_cb           Provide a callback function for the meta box display. If not set,
 *                                                post_categories_meta_box() is used for hierarchical taxonomies, and
 *                                                post_tags_meta_box() is used for non-hierarchical. If false, no meta
 *                                                box is shown.
 *     @type array         $capabilities {
 *         Array of capabilities for this multisite taxonomy.
 *
 *         @type string $manage_terms Default 'manage_categories'.
 *         @type string $edit_multisite_terms   Default 'manage_categories'.
 *         @type string $delete_multisite_terms Default 'manage_categories'.
 *         @type string $assign_terms Default 'edit_posts'.
 *     }
 *     @type bool|array    $rewrite {
 *         Triggers the handling of rewrites for this multisite taxonomy. Default true, using $multisite_taxonomy as slug. To prevent
 *         rewrite, set to false. To specify rewrite rules, an array can be passed with any of these keys:
 *
 *         @type string $slug         Customize the permastruct slug. Default `$multisite_taxonomy` key.
 *         @type bool   $with_front   Should the permastruct be prepended with WP_Rewrite::$front. Default true.
 *         @type bool   $hierarchical Either hierarchical rewrite tag or not. Default false.
 *         @type int    $ep_mask      Assign an endpoint mask. Default `EP_NONE`.
 *     }
 *     @type string        $query_var             Sets the query var key for this multisite taxonomy. Default `$multisite_taxonomy` key. If
 *                                                false, a multisite taxonomy cannot be loaded at `?{query_var}={term_slug}`. If a
 *                                                string, the query `?{query_var}={term_slug}` will be valid.
 *     @type callable      $update_count_callback Works much like a hook, in that it will be called when the count is
 *                                                updated. Default _update_post_multisite_term_count() for multisite taxonomies attached
 *                                                to post types, which confirms that the objects are published before
 *                                                counting them. Default _update_generic_multisite_term_count() for taxonomies
 *                                                attached to other object types, such as users.
 *     @type bool          $_builtin              This multisite taxonomy is a "built-in" taxonomy. INTERNAL USE ONLY!
 *                                                Default false.
 * }
 * @return WP_Error|void WP_Error, if errors.
 */
function register_multisite_taxonomy( $multisite_taxonomy, $object_type, $args = array() ) {
	global $multisite_taxonomies;

	if ( ! is_array( $multisite_taxonomies ) ) {
		$multisite_taxonomies = array();
	}

	$args = wp_parse_args( $args );

	if ( empty( $multisite_taxonomy ) || strlen( $multisite_taxonomy ) > 32 ) {
		return new WP_Error( 'multisite_taxonomy_length_invalid', __( 'Multisite taxonomy names must be between 1 and 32 characters in length.', 'multitaxo' ) );
	}

	$multisite_taxonomy_object = new Multisite_Taxonomy( $multisite_taxonomy, $object_type, $args );
	$multisite_taxonomy_object->add_rewrite_rules();

	$multisite_taxonomies[ $multisite_taxonomy ] = $multisite_taxonomy_object;

	$multisite_taxonomy_object->add_hooks();

	/**
	 * Fires after a multisite taxonomy is registered.
	 *
	 * @param string       $multisite_taxonomy    Multisite taxonomy slug.
	 * @param array|string $object_type Object type or array of object types.
	 * @param array        $args        Array of multisite taxonomy registration arguments.
	 */
	do_action( 'registered_multisite_taxonomy', $multisite_taxonomy, $object_type, (array) $multisite_taxonomy_object );
}

/**
 * Unregisters a multisite taxonomy.
 *
 * @global WP    $wp            Current WordPress environment instance.
 * @global array $multisite_taxonomies List of multisite taxonomies.
 *
 * @param string $multisite_taxonomy Multisite taxonomy name.
 * @return bool|WP_Error True on success, WP_Error on failure or if the multisite taxonomy doesn't exist.
 */
function unregister_multisite_taxonomy( $multisite_taxonomy ) {
	if ( ! multisite_taxonomy_exists( $multisite_taxonomy ) ) {
		return new WP_Error( 'invalid_multisite_taxonomy', __( 'Invalid multisite taxonomy.', 'multitaxo' ) );
	}

	$multisite_taxonomy_object = get_multisite_taxonomy( $multisite_taxonomy );

	global $multisite_taxonomies;

	$multisite_taxonomy_object->remove_rewrite_rules();
	$multisite_taxonomy_object->remove_hooks();

	// Remove the taxonomy.
	unset( $multisite_taxonomies[ $multisite_taxonomy ] );

	/**
	 * Fires after a multisite taxonomy is unregistered.
	 *
	 * @param string $multisite_taxonomy Multisite taxonomy name.
	 */
	do_action( 'unregistered_multisite_taxonomy', $multisite_taxonomy );

	return true;
}

/**
 * Builds an object with all multisite taxonomy labels out of a multisite taxonomy object
 *
 * Accepted keys of the label array in the multisite taxonomy object:
 *
 * - name - general name for the multisite taxonomy, usually plural. The same as and overridden by $multisite_taxonomy->label. Default is Tags/Categories
 * - singular_name - name for one object of this taxonomy. Default is Tag/Category
 * - search_items - Default is Search Tags/Search Categories
 * - popular_items - This string isn't used on hierarchical taxonomies. Default is Popular Tags
 * - all_items - Default is All Tags/All Categories
 * - parent_item - This string isn't used on non-hierarchical taxonomies. In hierarchical ones the default is Parent Category
 * - parent_item_colon - The same as `parent_item`, but with colon `:` in the end
 * - edit_item - Default is Edit Tag/Edit Category
 * - view_item - Default is View Tag/View Category
 * - update_item - Default is Update Tag/Update Category
 * - add_new_item - Default is Add New Tag/Add New Category
 * - new_item_name - Default is New Tag Name/New Category Name
 * - separate_items_with_commas - This string isn't used on hierarchical taxonomies. Default is "Separate tags with commas", used in the meta box.
 * - add_or_remove_items - This string isn't used on hierarchical taxonomies. Default is "Add or remove tags", used in the meta box when JavaScript is disabled.
 * - choose_from_most_used - This string isn't used on hierarchical taxonomies. Default is "Choose from the most used tags", used in the meta box.
 * - not_found - Default is "No tags found"/"No categories found", used in the meta box and multisite taxonomy list table.
 * - no_terms - Default is "No tags"/"No categories", used in the posts and media list tables.
 * - items_list_navigation - String for the table pagination hidden heading.
 * - items_list - String for the table hidden heading.
 *
 * Above, the first default value is for non-hierarchical multisite taxonomies (like tags) and the second one is for hierarchical multisite taxonomies (like categories).
 *
 * @param Multisite_Taxonomy $multisite_taxonomy Multisite taxonomy object.
 * @return object object with all the labels as member variables.
 */
function get_multisite_taxonomy_labels( $multisite_taxonomy ) {
	$multisite_taxonomy->labels = (array) $multisite_taxonomy->labels;

	if ( isset( $multisite_taxonomy->helps ) && empty( $multisite_taxonomy->labels['separate_items_with_commas'] ) ) {
		$multisite_taxonomy->labels['separate_items_with_commas'] = $multisite_taxonomy->helps;
	}

	if ( isset( $multisite_taxonomy->no_tagcloud ) && empty( $multisite_taxonomy->labels['not_found'] ) ) {
		$multisite_taxonomy->labels['not_found'] = $multisite_taxonomy->no_tagcloud;
	}

	$nohier_vs_hier_defaults              = array(
		'name'                       => array( _x( 'Tags', 'taxonomy general name', 'multitaxo' ), _x( 'Categories', 'taxonomy general name', 'multitaxo' ) ),
		'singular_name'              => array( _x( 'Tag', 'taxonomy singular name', 'multitaxo' ), _x( 'Category', 'taxonomy singular name', 'multitaxo' ) ),
		'search_items'               => array( __( 'Search Tags', 'multitaxo' ), __( 'Search Categories', 'multitaxo' ) ),
		'popular_items'              => array( __( 'Popular Tags', 'multitaxo' ), null ),
		'all_items'                  => array( __( 'All Tags', 'multitaxo' ), __( 'All Categories', 'multitaxo' ) ),
		'parent_item'                => array( null, __( 'Parent Category', 'multitaxo' ) ),
		'parent_item_colon'          => array( null, __( 'Parent Category:', 'multitaxo' ) ),
		'edit_item'                  => array( __( 'Edit Tag', 'multitaxo' ), __( 'Edit Category', 'multitaxo' ) ),
		'view_item'                  => array( __( 'View Tag', 'multitaxo' ), __( 'View Category', 'multitaxo' ) ),
		'update_item'                => array( __( 'Update Tag', 'multitaxo' ), __( 'Update Category', 'multitaxo' ) ),
		'add_new_item'               => array( __( 'Add New Tag', 'multitaxo' ), __( 'Add New Category', 'multitaxo' ) ),
		'new_item_name'              => array( __( 'New Tag Name', 'multitaxo' ), __( 'New Category Name', 'multitaxo' ) ),
		'separate_items_with_commas' => array( __( 'Separate tags with commas', 'multitaxo' ), null ),
		'add_or_remove_items'        => array( __( 'Add or remove tags', 'multitaxo' ), null ),
		'choose_from_most_used'      => array( __( 'Choose from the most used tags', 'multitaxo' ), null ),
		'not_found'                  => array( __( 'No tags found.', 'multitaxo' ), __( 'No categories found.', 'multitaxo' ) ),
		'no_terms'                   => array( __( 'No tags', 'multitaxo' ), __( 'No categories', 'multitaxo' ) ),
		'items_list_navigation'      => array( __( 'Tags list navigation', 'multitaxo' ), __( 'Categories list navigation', 'multitaxo' ) ),
		'items_list'                 => array( __( 'Tags list', 'multitaxo' ), __( 'Categories list', 'multitaxo' ) ),
	);
	$nohier_vs_hier_defaults['menu_name'] = $nohier_vs_hier_defaults['name'];

	$labels = _get_custom_object_labels( $multisite_taxonomy, $nohier_vs_hier_defaults );

	$multisite_taxonomy = $multisite_taxonomy->name;

	$default_labels = clone $labels;

	/**
	 * Filters the labels of a specific multisite taxonomy.
	 *
	 * The dynamic portion of the hook name, `$multisite_taxonomy`, refers to the multisite taxonomy slug.
	 *
	 * @see get_multisite_taxonomy_labels() for the full list of multisite taxonomy labels.
	 *
	 * @param object $labels Object with labels for the multisite taxonomy as member variables.
	 */
	$labels = apply_filters( "multisite_taxonomy_labels_{$multisite_taxonomy}", $labels );

	// Ensure that the filtered labels contain all required default values.
	$labels = (object) array_merge( (array) $default_labels, (array) $labels );

	return $labels;
}

/**
 * Add an already registered multisite taxonomy to an object type.
 *
 * @global array $multisite_taxonomies The registered multisite taxonomies.
 *
 * @param string $multisite_taxonomy    Name of multisite taxonomy object.
 * @param string $object_type Name of the object type.
 * @return bool True if successful, false if not.
 */
function register_multisite_taxonomy_for_object_type( $multisite_taxonomy, $object_type ) {
	global $multisite_taxonomies;

	if ( ! isset( $multisite_taxonomies[ $multisite_taxonomy ] ) ) {
		return false;
	}

	if ( ! get_post_type_object( $object_type ) ) {
		return false;
	}

	if ( ! in_array( $object_type, $multisite_taxonomies[ $multisite_taxonomy ]->object_type, true ) ) {
		$multisite_taxonomies[ $multisite_taxonomy ]->object_type[] = $object_type;
	}

	// Filter out empties.
	$multisite_taxonomies[ $multisite_taxonomy ]->object_type = array_filter( $multisite_taxonomies[ $multisite_taxonomy ]->object_type );

	return true;
}

/**
 * Remove an already registered multisite taxonomy from an object type.
 *
 * @global array $multisite_taxonomies The registered multisite taxonomies.
 *
 * @param string $multisite_taxonomy    Name of multisite taxonomy object.
 * @param string $object_type Name of the object type.
 * @return bool True if successful, false if not.
 */
function unregister_multisite_taxonomy_for_object_type( $multisite_taxonomy, $object_type ) {
	global $multisite_taxonomies;

	if ( ! isset( $multisite_taxonomies[ $multisite_taxonomy ] ) ) {
		return false;
	}

	if ( ! get_post_type_object( $object_type ) ) {
		return false;
	}

	$key = array_search( $object_type, $multisite_taxonomies[ $multisite_taxonomy ]->object_type, true );
	if ( false === $key ) {
		return false;
	}

	unset( $multisite_taxonomies[ $multisite_taxonomy ]->object_type[ $key ] );
	return true;
}

/**
 * Multisite Term API
 */

/**
 * Retrieve object_ids of valid multisite taxonomy and multisite term.
 *
 * The strings of $multisite_taxonomies must exist before this function will continue. On
 * failure of finding a valid multisite taxonomy, it will return an WP_Error class, kind
 * of like Exceptions in PHP 5, except you can't catch them. Even so, you can
 * still test for the WP_Error class and get the error message.
 *
 * The $multisite_terms aren't checked the same as $multisite_taxonomies, but still need to exist
 * for $object_ids to be returned.
 *
 * It is possible to change the order that object_ids is returned by either
 * using PHP sort family functions or using the database by using $args with
 * either ASC or DESC array. The value should be in the key named 'order'.
 *
 * @global wpdb $wpdb WordPress database abstraction object.
 *
 * @param int|array    $multisite_term_ids   Multisite Term id or array of multisite term ids of multisite terms that will be used.
 * @param string|array $multisite_taxonomies String of multisite taxonomy name or Array of string values of multisite taxonomy names.
 * @param array|string $args       Change the order of the object_ids, either ASC or DESC.
 * @return WP_Error|array If the multisite taxonomy does not exist, then WP_Error will be returned. On success.
 *  the array can be empty meaning that there are no $object_ids found or it will return the $object_ids found.
 */
function get_objects_in_multisite_term( $multisite_term_ids, $multisite_taxonomies, $args = array() ) {
	global $wpdb;

	if ( ! is_array( $multisite_term_ids ) ) {
		$multisite_term_ids = array( $multisite_term_ids );
	}
	if ( ! is_array( $multisite_taxonomies ) ) {
		$multisite_taxonomies = array( $multisite_taxonomies );
	}
	foreach ( (array) $multisite_taxonomies as $multisite_taxonomy ) {
		if ( ! multisite_taxonomy_exists( $multisite_taxonomy ) ) {
			return new WP_Error( 'invalid_taxonomy', __( 'Invalid taxonomy.', 'multitaxo' ) );
		}
	}

	$defaults = array(
		'order' => 'ASC',
	);
	$args     = wp_parse_args( $args, $defaults );

	$order = ( 'desc' === strtolower( $args['order'] ) ) ? 'DESC' : 'ASC';

	$multisite_term_ids = array_map( 'intval', $multisite_term_ids );

	$multisite_taxonomies = "'" . implode( "', '", array_map( 'esc_sql', $multisite_taxonomies ) ) . "'";
	$multisite_term_ids   = "'" . implode( "', '", $multisite_term_ids ) . "'";

	$object_ids = $wpdb->get_col( "SELECT tr.object_id FROM $wpdb->multisite_term_relationships AS tr INNER JOIN $wpdb->multisite_term_multisite_taxonomy AS tt ON tr.multisite_term_multisite_taxonomy_id = tt.multisite_term_multisite_taxonomy_id WHERE tt.multisite_taxonomy IN ($multisite_taxonomies) AND tt.multisite_term_id IN ($multisite_term_ids) ORDER BY tr.object_id $order" ); // WPCS: unprepared SQL ok.

	if ( ! $object_ids ) {
		return array();
	}
	return $object_ids;
}

/**
 * Given a multisite taxonomy query, generates SQL to be appended to a main query.
 *
 * @see Multisite_Tax_Query
 *
 * @param array  $multisite_tax_query A compact multisite tax query.
 * @param string $primary_table The primary table.
 * @param string $primary_id_column The primary id column.
 * @return array
 */
function get_multisite_tax_sql( $multisite_tax_query, $primary_table, $primary_id_column ) {
	$multisite_tax_query_obj = new Multisite_Tax_Query( $multisite_tax_query );
	return $multisite_tax_query_obj->get_sql( $primary_table, $primary_id_column );
}

/**
 * Get all Multisite Term data from database by Multisite term ID.
 *
 * The usage of the get_multisite_term function is to apply filters to a multisite term object. It
 * is possible to get a multisite term object from the database before applying the
 * filters.
 *
 * $multisite_term ID must be part of $multisite_taxonomy, to get from the database. Failure, might
 * be able to be captured by the hooks. Failure would be the same value as $wpdb
 * returns for the get_row method.
 *
 * There are two hooks, one is specifically for each term, named 'get_multisite_term', and
 * the second is for the multisite taxonomy name, 'term_$multisite_taxonomy'. Both hooks gets the
 * multisite term object, and the multisite taxonomy name as parameters. Both hooks are expected to
 * return a Multisite Term object.
 *
 * {@see 'get_multisite_term'} hook - Takes two parameters the multisite term Object and the multisite taxonomy name.
 * Must return multisite term object. Used in get_multisite_term() as a catch-all filter for every
 * $multisite_term.
 *
 * {@see 'get_$multisite_taxonomy'} hook - Takes two parameters the multisite term Object and the multisite taxonomy
 * name. Must return multisite term object. $multisite_taxonomy will be the multisite taxonomy name, so for
 * example, if 'category', it would be 'get_multisite_category' as the filter name. Useful
 * for custom multisite taxonomies or plugging into default multisite taxonomies.
 *
 * @global wpdb $wpdb WordPress database abstraction object.
 * @see sanitize_multisite_term_field() The $context param lists the available values for get_multisite_term_by() $filter param.
 *
 * @param int|Multisite_Term|object $multisite_term If integer, multisite term data will be fetched from the database, or from the cache if
 *                                 available. If stdClass object (as in the results of a database query), will apply
 *                                 filters and return a `Multisite_Term` object corresponding to the `$multisite_term` data. If `Multisite_Term`,
 *                                 will return `$multisite_term`.
 * @param string                    $multisite_taxonomy Optional. Multisite taxonomy name that $multisite_term is part of.
 * @param string                    $output   Optional. The required return type. One of OBJECT, ARRAY_A, or ARRAY_N, which correspond to
 *                             a Multisite_Term object, an associative array, or a numeric array, respectively. Default OBJECT.
 * @param string                    $filter   Optional, default is raw or no WordPress defined filter will applied.
 * @return array|Multisite_Term|WP_Error|null Object of the type specified by `$output` on success. When `$output` is 'OBJECT',
 *                                     a Multisite_Term instance is returned. If multisite taxonomy does not exist, a WP_Error is
 *                                     returned. Returns null for miscellaneous failure.
 */
function get_multisite_term( $multisite_term, $multisite_taxonomy = '', $output = OBJECT, $filter = 'raw' ) {
	if ( empty( $multisite_term ) ) {
		return new WP_Error( 'invalid_term', __( 'Empty Term', 'multitaxo' ) );
	}

	if ( $multisite_taxonomy && ! multisite_taxonomy_exists( $multisite_taxonomy ) ) {
		return new WP_Error( 'invalid_taxonomy', __( 'Invalid taxonomy.', 'multitaxo' ) );
	}

	if ( $multisite_term instanceof Multisite_Term ) {
		$_multisite_term = $multisite_term;
	} elseif ( is_object( $multisite_term ) ) {
		if ( empty( $multisite_term->filter ) || 'raw' === $multisite_term->filter ) {
			$_multisite_term = sanitize_multisite_term( $multisite_term, $multisite_taxonomy, 'raw' );
			$_multisite_term = new Multisite_Term( $_multisite_term );
		} else {
			$_multisite_term = Multisite_Term::get_instance( $multisite_term->multisite_term_id );
		}
	} else {
		$_multisite_term = Multisite_Term::get_instance( $multisite_term, $multisite_taxonomy );
	}

	if ( is_wp_error( $_multisite_term ) ) {
		return $_multisite_term;
	} elseif ( ! $_multisite_term ) {
		return null;
	}

	/**
	 * Filters a multisite term.
	 *
	 * @param int|Multisite_Term $_multisite_term    Multisite Term object or ID.
	 * @param string      $multisite_taxonomy The multisite taxonomy slug.
	 */
	$_multisite_term = apply_filters( 'get_multisite_term', $_multisite_term, $multisite_taxonomy );

	/**
	 * Filters a multisite taxonomy.
	 *
	 * The dynamic portion of the filter name, `$multisite_taxonomy`, refers
	 * to the multisite taxonomy slug.
	 *
	 * @param int|Multisite_Term $_multisite_term    Multisite Term object or ID.
	 * @param string      $multisite_taxonomy The multisite taxonomy slug.
	 */
	$_multisite_term = apply_filters( "get_multisite_{$multisite_taxonomy}", $_multisite_term, $multisite_taxonomy );

	// Bail if a filter callback has changed the type of the `$_multisite_term` object.
	if ( ! ( $_multisite_term instanceof Multisite_Term ) ) {
		return $_multisite_term;
	}

	// Sanitize term, according to the specified filter.
	$_multisite_term->filter( $filter );

	if ( ARRAY_A === $output ) {
		return $_multisite_term->to_array();
	} elseif ( ARRAY_N === $output ) {
		return array_values( $_multisite_term->to_array() );
	}

	return $_multisite_term;
}

/**
 * Get all Multisite Term data from database by Multisite Term field and data.
 *
 * Warning: $value is not escaped for 'name' $field. You must do it yourself, if
 * required.
 *
 * The default $field is 'id', therefore it is possible to also use null for
 * field, but not recommended that you do so.
 *
 * If $value does not exist, the return value will be false. If $multisite_taxonomy exists
 * and $field and $value combinations exist, the Multisite Term will be returned.
 *
 * This function will always return the first term that matches the `$field`-
 * `$value`-`$multisite_taxonomy` combination specified in the parameters. If your query
 * is likely to match more than one term (as is likely to be the case when
 * `$field` is 'name', for example), consider using get_multisite_terms() instead; that
 * way, you will get all matching multisite terms, and can provide your own logic for
 * deciding which one was intended.
 *
 * @global wpdb $wpdb WordPress database abstraction object.
 * @see sanitize_multisite_term_field() The $context param lists the available values for get_multisite_term_by() $filter param.
 *
 * @param string     $field    Either 'slug', 'name', 'id' (multisite_term_id), or 'multisite_term_multisite_taxonomy_id'.
 * @param string|int $value    Search for this multisite term value.
 * @param string     $multisite_taxonomy Multisite Taxonomy name. Optional, if `$field` is 'multisite_term_multisite_taxonomy_id'.
 * @param string     $output   Optional. The required return type. One of OBJECT, ARRAY_A, or ARRAY_N, which correspond to
 *                             a Multisite_Term object, an associative array, or a numeric array, respectively. Default OBJECT.
 * @param string     $filter   Optional, default is raw or no WordPress defined filter will applied.
 * @return Multisite_Term|array|false Multisite_Term instance (or array) on success. Will return false if `$multisite_taxonomy` does not exist
 *                             or `$multisite_term` was not found.
 */
function get_multisite_term_by( $field, $value, $multisite_taxonomy = '', $output = OBJECT, $filter = 'raw' ) {
	global $wpdb;

	// 'multisite_term_multisite_taxonomy_id' lookups don't require multisite taxonomy checks.
	if ( 'multisite_term_multisite_taxonomy_id' !== $field && ! multisite_taxonomy_exists( $multisite_taxonomy ) ) {
		return false;
	}

	$multisite_tax_clause = $wpdb->prepare( 'AND tt.multisite_taxonomy = %s', $multisite_taxonomy );

	if ( 'slug' === $field ) {
		$_field = 't.slug';
		$value  = sanitize_title( $value );
		if ( empty( $value ) ) {
			return false;
		}
	} elseif ( 'name' === $field ) {
		// Assume already escaped.
		$value  = wp_unslash( $value );
		$_field = 't.name';
	} elseif ( 'multisite_term_multisite_taxonomy_id' === $field ) {
		$value  = (int) $value;
		$_field = 'tt.multisite_term_multisite_taxonomy_id';
		// No `multisite_taxonomy` clause when searching by 'multisite_term_multisite_taxonomy_id'.
		$multisite_tax_clause = '';
	} else {
		$multisite_term = get_multisite_term( (int) $value, $multisite_taxonomy, $output, $filter );
		if ( is_wp_error( $multisite_term ) || is_null( $multisite_term ) ) {
			$multisite_term = false;
		}
		return $multisite_term;
	}

	$multisite_term = $wpdb->get_row( $wpdb->prepare( "SELECT t.*, tt.* FROM $wpdb->multisite_terms AS t INNER JOIN $wpdb->multisite_term_multisite_taxonomy AS tt ON t.multisite_term_id = tt.multisite_term_id WHERE $_field = %s", $value ) . " $multisite_tax_clause LIMIT 1" ); // WPCS: unprepared SQL ok.
	if ( ! $multisite_term ) {
		return false;
	}

	// In the case of 'multisite_term_multisite_taxonomy_id', override the provided `$multisite_taxonomy` with whatever we find in the db.
	if ( 'multisite_term_multisite_taxonomy_id' === $field ) {
		$multisite_taxonomy = $multisite_term->multisite_taxonomy;
	}

	wp_cache_add( $multisite_term->multisite_term_id, $multisite_term, 'multisite_terms' );

	return get_multisite_term( $multisite_term, $multisite_taxonomy, $output, $filter );
}

/**
 * Merge all term children into a single array of their IDs.
 *
 * This recursive function will merge all of the children of $multisite_term into the same
 * array of multisite term IDs. Only useful for multisite taxonomies which are hierarchical.
 *
 * Will return an empty array if $multisite_term does not exist in $multisite_taxonomy.
 *
 * @param string $multisite_term_id  ID of Multisite Term to get children.
 * @param string $multisite_taxonomy Multisite Taxonomy Name.
 * @return array|WP_Error List of Multisite term IDs. WP_Error returned if `$multisite_taxonomy` does not exist.
 */
function get_multisite_term_children( $multisite_term_id, $multisite_taxonomy ) {
	if ( ! multisite_taxonomy_exists( $multisite_taxonomy ) ) {
		return new WP_Error( 'invalid_multisite_taxonomy', __( 'Invalid taxonomy.', 'multitaxo' ) );
	}

	$multisite_term_id = intval( $multisite_term_id );

	$multisite_terms = _get_multisite_term_hierarchy( $multisite_taxonomy );

	if ( ! isset( $multisite_terms[ $multisite_term_id ] ) ) {
		return array();
	}

	$children = $multisite_terms[ $multisite_term_id ];

	foreach ( (array) $multisite_terms[ $multisite_term_id ] as $child ) {
		if ( $multisite_term_id === $child ) {
			continue;
		}
		if ( isset( $multisite_terms[ $child ] ) ) {
			$children = array_merge( $children, get_multisite_term_children( $child, $multisite_taxonomy ) );
		}
	}

	return $children;
}

/**
 * Get sanitized Multisite Term field.
 *
 * The function is for contextual reasons and for simplicity of usage.
 *
 * @see sanitize_multisite_term_field()
 *
 * @param string             $field    Multisite Term field to fetch.
 * @param int|Multisite_Term $multisite_term     Multisite term ID or object.
 * @param string             $multisite_taxonomy Optional. Multisite Taxonomy Name. Default empty.
 * @param string             $context  Optional, default is display. Look at sanitize_multisite_term_field() for available options.
 * @return string|int|null|WP_Error Will return an empty string if $multisite_term is not an object or if $field is not set in $multisite_term.
 */
function get_multisite_term_field( $field, $multisite_term, $multisite_taxonomy = '', $context = 'display' ) {
	$multisite_term = get_multisite_term( $multisite_term, $multisite_taxonomy );
	if ( is_wp_error( $multisite_term ) ) {
		return $multisite_term;
	}
	if ( ! is_object( $multisite_term ) ) {
		return '';
	}
	if ( ! isset( $multisite_term->$field ) ) {
		return '';
	}
	return sanitize_multisite_term_field( $field, $multisite_term->$field, $multisite_term->multisite_term_id, $multisite_term->multisite_taxonomy, $context );
}

/**
 * Sanitizes Multisite Term for editing.
 *
 * Return value is sanitize_multisite_term() and usage is for sanitizing the multisite term for
 * editing. Function is for contextual and simplicity.
 *
 * @param int|object $id       Multisite term ID or object.
 * @param string     $multisite_taxonomy Multisite Taxonomy name.
 * @return string|int|null|WP_Error Will return empty string if $multisite_term is not an object.
 */
function get_multisite_term_to_edit( $id, $multisite_taxonomy ) {
	$multisite_term = get_multisite_term( $id, $multisite_taxonomy );

	if ( is_wp_error( $multisite_term ) ) {
		return $multisite_term;
	}
	if ( ! is_object( $multisite_term ) ) {
		return '';
	}
	return sanitize_multisite_term( $multisite_term, $multisite_taxonomy, 'edit' );
}

/**
 * Retrieve the multisite terms in a given multisite taxonomy or list of multisite taxonomies.
 *
 * You can fully inject any customizations to the query before it is sent, as
 * well as control the output with a filter.
 *
 * The {@see 'get_multisite_terms'} filter will be called when the cache has the multisite term and will
 * pass the found multisite term along with the array of $multisite_taxonomies and array of $args.
 * This filter is also called before the array of multisite terms is passed and will pass
 * the array of multisite terms, along with the $multisite_taxonomies and $args.
 *
 * The {@see 'list_multisite_terms_exclusions'} filter passes the compiled exclusions along with
 * the $args.
 *
 * The {@see 'get_multisite_terms_orderby'} filter passes the `ORDER BY` clause for the query
 * along with the $args array.
 *
 * @global wpdb  $wpdb WordPress database abstraction object.
 * @global array $wp_filter
 *
 * @param array|string $args {
 *     Optional. Array or string of arguments to get multisite terms.
 *
 *     @type string|array $multisite_taxonomy     Multisite taxonomy name, or array of multisite taxonomies, to which results should
 *                                                be limited.
 *     @type string       $orderby                Field(s) to order terms by. Accepts multisite term fields ('name', 'slug',
 *                                                'multisite_term_group', 'multisite_term_id', 'id', 'description'), 'count' for multisite term
 *                                                multisite taxonomy count, 'include' to match the 'order' of the $include param,
 *                                                'meta_value', 'meta_value_num', the value of `$meta_key`, the array
 *                                                keys of `$meta_query`, or 'none' to omit the ORDER BY clause.
 *                                                Defaults to 'name'.
 *     @type string       $order                  Whether to order terms in ascending or descending order.
 *                                                Accepts 'ASC' (ascending) or 'DESC' (descending).
 *                                                Default 'ASC'.
 *     @type bool|int     $hide_empty             Whether to hide terms not assigned to any posts. Accepts
 *                                                1|true or 0|false. Default 1|true.
 *     @type array|string $include                Array or comma/space-separated string of multisite term ids to include.
 *                                                Default empty array.
 *     @type array|string $exclude                Array or comma/space-separated string of multisite term ids to exclude.
 *                                                If $include is non-empty, $exclude is ignored.
 *                                                Default empty array.
 *     @type array|string $exclude_tree           Array or comma/space-separated string of multisite term ids to exclude
 *                                                along with all of their descendant multisite terms. If $include is
 *                                                non-empty, $exclude_tree is ignored. Default empty array.
 *     @type int|string   $number                 Maximum number of multisite terms to return. Accepts ''|0 (all) or any
 *                                                positive number. Default ''|0 (all).
 *     @type int          $offset                 The number by which to offset the multisite terms query. Default empty.
 *     @type string       $fields                 Multisite Term fields to query for. Accepts 'all' (returns an array of complete
 *                                                multisite term objects), 'ids' (returns an array of ids), 'id=>parent' (returns
 *                                                an associative array with ids as keys, parent multisite term IDs as values),
 *                                                'names' (returns an array of multisite term names), 'count' (returns the number
 *                                                of matching multisite terms), 'id=>name' (returns an associative array with ids
 *                                                as keys, multisite term names as values), or 'id=>slug' (returns an associative
 *                                                array with ids as keys, multisite term slugs as values). Default 'all'.
 *     @type string|array $name                   Optional. Name or array of names to return multisite term(s) for. Default empty.
 *     @type string|array $slug                   Optional. Slug or array of slugs to return multisite term(s) for. Default empty.
 *     @type bool         $hierarchical           Whether to include multisite terms that have non-empty descendants (even
 *                                                if $hide_empty is set to true). Default true.
 *     @type string       $search                 Search criteria to match multisite terms. Will be SQL-formatted with
 *                                                wildcards before and after. Default empty.
 *     @type string       $name__like             Retrieve multisite terms with criteria by which a multisite term is LIKE $name__like.
 *                                                Default empty.
 *     @type string       $description__like      Retrieve multisite terms where the description is LIKE $description__like.
 *                                                Default empty.
 *     @type bool         $pad_counts             Whether to pad the quantity of a multisite term's children in the quantity
 *                                                of each multisite term's "count" object variable. Default false.
 *     @type string       $get                    Whether to return multisite terms regardless of ancestry or whether the multisite terms
 *                                                are empty. Accepts 'all' or empty (disabled). Default empty.
 *     @type int          $child_of               Multisite term ID to retrieve child multisite terms of. If multiple multisite taxonomies
 *                                                are passed, $child_of is ignored. Default 0.
 *     @type int|string   $parent                 Parent multisite term ID to retrieve direct-child multisite terms of. Default empty.
 *     @type bool         $childless              True to limit results to multisite terms that have no children. This parameter
 *                                                has no effect on non-hierarchical multisite taxonomies. Default false.
 *     @type string       $cache_domain           Unique cache key to be produced when this query is stored in an
 *                                                object cache. Default is 'core'.
 *     @type bool         $update_multisite_term_meta_cache Whether to prime meta caches for matched multisite terms. Default true.
 *     @type array        $meta_query             Meta query clauses to limit retrieved multisite terms by.
 *                                                See `WP_Meta_Query`. Default empty.
 *     @type string       $meta_key               Limit multisite terms to those matching a specific metadata key. Can be used in
 *                                                conjunction with `$meta_value`.
 *     @type string       $meta_value             Limit multisite terms to those matching a specific metadata value. Usually used
 *                                                in conjunction with `$meta_key`.
 * }
 * @return array|int|WP_Error List of Multisite_Term instances and their children. Will return WP_Error, if any of $multisite_taxonomies
 *                            do not exist.
 */
function get_multisite_terms( $args = array() ) {
	global $wpdb;

	$multisite_term_query = new Multisite_Term_Query();

	$defaults = array(
		'suppress_filter' => false,
	);

	$args = wp_parse_args( $args, $defaults );
	if ( isset( $args['taxonomy'] ) && null !== $args['taxonomy'] ) {
		$args['taxonomy'] = (array) $args['taxonomy'];
	}

	if ( ! empty( $args['taxonomy'] ) ) {
		foreach ( $args['taxonomy'] as $taxonomy ) {
			if ( ! multisite_taxonomy_exists( $taxonomy ) ) {
				return new WP_Error( 'invalid_taxonomy', __( 'Invalid taxonomy.', 'multitaxo' ) );
			}
		}
	}

	// Don't pass suppress_filter to WP_Term_Query.
	$suppress_filter = $args['suppress_filter'];
	unset( $args['suppress_filter'] );

	$multisite_terms = $multisite_term_query->query( $args );

	// Count queries are not filtered, for legacy reasons.
	if ( ! is_array( $multisite_terms ) ) {
		return $multisite_terms;
	}

	if ( $suppress_filter ) {
		return $multisite_terms;
	}

	/**
	 * Filters the found terms.
	 *
	 * @since 2.3.0
	 * @since 4.6.0 Added the `$term_query` parameter.
	 *
	 * @param array         $terms      Array of found terms.
	 * @param array         $taxonomies An array of taxonomies.
	 * @param array         $args       An array of get_terms() arguments.
	 * @param WP_Term_Query $term_query The WP_Term_Query object.
	 */
	return apply_filters( 'get_terms', $multisite_terms, $multisite_term_query->query_vars['taxonomy'], $multisite_term_query->query_vars, $multisite_term_query );
}

/**
 * Adds metadata to a multisite term.
 *
 * @param int    $multisite_term_id    Multisite term ID.
 * @param string $meta_key   Metadata name.
 * @param mixed  $meta_value Metadata value.
 * @param bool   $unique     Optional. Whether to bail if an entry with the same key is found for the multisite term.
 *                           Default false.
 * @return int|WP_Error|bool Meta ID on success. WP_Error when multisite_term_id is ambiguous between multisite taxonomies.
 *                           False on failure.
 */
function add_multisite_term_meta( $multisite_term_id, $meta_key, $meta_value, $unique = false ) {

	$added = add_metadata( 'multisite_term', $multisite_term_id, $meta_key, $meta_value, $unique );

	// Bust term query cache.
	if ( $added ) {
		wp_cache_set( 'last_changed', microtime(), 'multisite_terms' );
	}

	return $added;
}

/**
 * Removes metadata matching criteria from a multisite term.
 *
 * @param int    $multisite_term_id    Multisite term ID.
 * @param string $meta_key   Metadata name.
 * @param mixed  $meta_value Optional. Metadata value. If provided, rows will only be removed that match the value.
 * @return bool True on success, false on failure.
 */
function delete_multisite_term_meta( $multisite_term_id, $meta_key, $meta_value = '' ) {

	$deleted = delete_metadata( 'multisite_term', $multisite_term_id, $meta_key, $meta_value );

	// Bust multisite term query cache.
	if ( $deleted ) {
		wp_cache_set( 'last_changed', microtime(), 'multisite_terms' );
	}

	return $deleted;
}

/**
 * Retrieves metadata for a multisiteterm.
 *
 * @param int    $multisite_term_id Multisite term ID.
 * @param string $key     Optional. The meta key to retrieve. If no key is provided, fetches all metadata for the multisite term.
 * @param bool   $single  Whether to return a single value. If false, an array of all values matching the
 *                        `$multisite_term_id`/`$key` pair will be returned. Default: false.
 * @return mixed If `$single` is false, an array of metadata values. If `$single` is true, a single metadata value.
 */
function get_multisite_term_meta( $multisite_term_id, $key = '', $single = false ) {
	return get_metadata( 'multisite_term', $multisite_term_id, $key, $single );
}

/**
 * Updates multisite term metadata.
 *
 * Use the `$prev_value` parameter to differentiate between meta fields with the same key and multisite term ID.
 *
 * If the meta field for the multisite term does not exist, it will be added.
 *
 * @param int    $multisite_term_id    Multisite term ID.
 * @param string $meta_key   Metadata key.
 * @param mixed  $meta_value Metadata value.
 * @param mixed  $prev_value Optional. Previous value to check before removing.
 * @return int|WP_Error|bool Meta ID if the key didn't previously exist. True on successful update.
 *                           WP_Error when multisite_term_id is ambiguous between multisite taxonomies. False on failure.
 */
function update_multisite_term_meta( $multisite_term_id, $meta_key, $meta_value, $prev_value = '' ) {

	$updated = update_metadata( 'multisite_term', $multisite_term_id, $meta_key, $meta_value, $prev_value );

	// Bust multisite term query cache.
	if ( $updated ) {
		wp_cache_set( 'last_changed', microtime(), 'multisite_terms' );
	}

	return $updated;
}

/**
 * Updates metadata cache for list of multisite term IDs.
 *
 * Performs SQL query to retrieve all metadata for the multisite terms matching `$multisite_term_ids` and stores them in the cache.
 * Subsequent calls to `get_multisite_term_meta()` will not need to query the database.
 *
 * @param array $multisite_term_ids List of multisite term IDs.
 * @return array|false Returns false if there is nothing to update. Returns an array of metadata on success.
 */
function update_multisite_termmeta_cache( $multisite_term_ids ) {
	return update_meta_cache( 'multisite_term', $multisite_term_ids );
}

/**
 * Check if Multisite Term exists.
 *
 * @global wpdb $wpdb WordPress database abstraction object.
 *
 * @param int|string $multisite_term     The multisite term to check. Accepts multisite term ID, slug, or name.
 * @param string     $multisite_taxonomy The multisite taxonomy name to use.
 * @param int        $parent   Optional. ID of parent multisite term under which to confine the exists search.
 * @return mixed Returns null if the multisite term does not exist. Returns the multisite term ID
 *               if no multisite taxonomy is specified and the multisite term ID exists. Returns
 *               an array of the multisite term ID and the multisite term multisite taxonomy ID the multisite taxonomy
 *               is specified and the pairing exists.
 */
function multisite_term_exists( $multisite_term, $multisite_taxonomy = '', $parent = null ) {
	global $wpdb;

	$select     = "SELECT multisite_term_id FROM $wpdb->multisite_terms as t WHERE ";
	$tax_select = "SELECT tt.multisite_term_id, tt.multisite_term_multisite_taxonomy_id FROM $wpdb->multisite_terms AS t INNER JOIN $wpdb->multisite_term_multisite_taxonomy as tt ON tt.multisite_term_id = t.multisite_term_id WHERE ";

	if ( is_int( $multisite_term ) ) {
		if ( 0 === $multisite_term ) {
			return 0;
		}

		if ( ! empty( $multisite_taxonomy ) ) {
			return $wpdb->get_row( $wpdb->prepare( $tax_select . 't.multisite_term_id = %d AND tt.multisite_taxonomy = %s', $multisite_term, $multisite_taxonomy ), ARRAY_A ); // WPCS: unprepared SQL ok.
		} else {
			return $wpdb->get_var( $wpdb->prepare( $select . 't.multisite_term_id = %d', $multisite_term ) ); // WPCS: unprepared SQL ok.
		}
	}

	$multisite_term = trim( wp_unslash( $multisite_term ) );
	$slug           = sanitize_title( $multisite_term );

	$where             = 't.slug = %s';
	$else_where        = 't.name = %s';
	$where_fields      = array( $slug );
	$else_where_fields = array( $multisite_term );
	$orderby           = 'ORDER BY t.multisite_term_id ASC';
	$limit             = 'LIMIT 1';
	if ( ! empty( $multisite_taxonomy ) ) {
		if ( is_numeric( $parent ) ) {
			$parent              = (int) $parent;
			$where_fields[]      = $parent;
			$else_where_fields[] = $parent;
			$where              .= ' AND tt.parent = %d';
			$else_where         .= ' AND tt.parent = %d';
		}

		$where_fields[]      = $multisite_taxonomy;
		$else_where_fields[] = $multisite_taxonomy;

		$result = $wpdb->get_row( $wpdb->prepare( "SELECT tt.multisite_term_id, tt.multisite_term_multisite_taxonomy_id FROM $wpdb->multisite_terms AS t INNER JOIN $wpdb->multisite_term_multisite_taxonomy as tt ON tt.multisite_term_id = t.multisite_term_id WHERE $where AND tt.multisite_taxonomy = %s $orderby $limit", $where_fields ), ARRAY_A ); // WPCS: unprepared SQL ok.
		if ( $result ) {
			return $result;
		}

		return $wpdb->get_row( $wpdb->prepare( "SELECT tt.multisite_term_id, tt.multisite_term_multisite_taxonomy_id FROM $wpdb->multisite_terms AS t INNER JOIN $wpdb->multisite_term_multisite_taxonomy as tt ON tt.multisite_term_id = t.multisite_term_id WHERE $else_where AND tt.multisite_taxonomy = %s $orderby $limit", $else_where_fields ), ARRAY_A ); // WPCS: unprepared SQL ok.
	}

	// @codingStandardsIgnoreLine
	$result = $wpdb->get_var( $wpdb->prepare( "SELECT multisite_term_id FROM $wpdb->multisite_terms as t WHERE $where $orderby $limit", $where_fields ) ); // WPCS: unprepared SQL ok.

	if ( $result ) {
		return $result;
	}

	// @codingStandardsIgnoreLine
	return $wpdb->get_var( $wpdb->prepare( "SELECT multisite_term_id FROM $wpdb->multisite_terms as t WHERE $else_where $orderby $limit", $else_where_fields ) ); // WPCS: unprepared SQL ok.
}

/**
 * Check if a multisite term is an ancestor of another multisite term.
 *
 * You can use either an id or the multisite term object for both parameters.
 *
 * @param int|object $multisite_term1    ID or object to check if this is the parent multisite term.
 * @param int|object $multisite_term2    The child multisite term.
 * @param string     $multisite_taxonomy Multisite taxonomy name that $multisite_term1 and `$multisite_term2` belong to.
 * @return bool Whether `$multisite_term2` is a child of `$multisite_term1`.
 */
function multisite_term_is_ancestor_of( $multisite_term1, $multisite_term2, $multisite_taxonomy ) {
	if ( ! isset( $multisite_term1->multisite_term_id ) ) {
		$multisite_term1 = get_multisite_term( $multisite_term1, $multisite_taxonomy );
	}
	if ( ! isset( $multisite_term2->parent ) ) {
		$multisite_term2 = get_multisite_term( $multisite_term2, $multisite_taxonomy );
	}
	if ( empty( $multisite_term1->multisite_term_id ) || empty( $multisite_term2->parent ) ) {
		return false;
	}
	if ( $multisite_term2->parent === $multisite_term1->multisite_term_id ) {
		return true;
	}
	return multisite_term_is_ancestor_of( $multisite_term1, get_multisite_term( $multisite_term2->parent, $multisite_taxonomy ), $multisite_taxonomy );
}

/**
 * Sanitize Multisite Term all fields.
 *
 * Relies on sanitize_multisite_term_field() to sanitize the multisite term. The difference is that
 * this function will sanitize <strong>all</strong> fields. The context is based
 * on sanitize_multisite_term_field().
 *
 * The $multisite_term is expected to be either an array or an object.
 *
 * @param array|object $multisite_term     The multisite term to check.
 * @param string       $multisite_taxonomy The multisite taxonomy name to use.
 * @param string       $context  Optional. Context in which to sanitize the multisite term. Accepts 'edit', 'db',
 *                               'display', 'attribute', or 'js'. Default 'display'.
 * @return array|object Multisite Term with all fields sanitized.
 */
function sanitize_multisite_term( $multisite_term, $multisite_taxonomy, $context = 'display' ) {
	$fields = array( 'multisite_term_id', 'name', 'description', 'slug', 'count', 'parent', 'multisite_term_group', 'multisite_term_multisite_taxonomy_id', 'object_id' );

	$do_object = is_object( $multisite_term );

	$multisite_term_id = $do_object ? $multisite_term->multisite_term_id : ( isset( $multisite_term['multisite_term_id'] ) ? $multisite_term['multisite_term_id'] : 0 );

	foreach ( (array) $fields as $field ) {
		if ( $do_object ) {
			if ( isset( $multisite_term->$field ) ) {
				$multisite_term->$field = sanitize_multisite_term_field( $field, $multisite_term->$field, $multisite_term_id, $multisite_taxonomy, $context );
			}
		} else {
			if ( isset( $multisite_term[ $field ] ) ) {
				$multisite_term[ $field ] = sanitize_multisite_term_field( $field, $multisite_term[ $field ], $multisite_term_id, $multisite_taxonomy, $context );
			}
		}
	}

	if ( $do_object ) {
		$multisite_term->filter = $context;
	} else {
		$multisite_term['filter'] = $context;
	}

	return $multisite_term;
}

/**
 * Cleanse the field value in the multisite term based on the context.
 *
 * Passing a multisite term field value through the function should be assumed to have
 * cleansed the value for whatever context the multisite term field is going to be used.
 *
 * If no context or an unsupported context is given, then default filters will
 * be applied.
 *
 * There are enough filters for each context to support a custom filtering
 * without creating your own filter function. Simply create a function that
 * hooks into the filter you need.
 *
 * @param string $field    Multisite Term field to sanitize.
 * @param string $value    Search for this multisite term value.
 * @param int    $multisite_term_id  Multisite term ID.
 * @param string $multisite_taxonomy Multisite Taxonomy Name.
 * @param string $context  Context in which to sanitize the multisite term field. Accepts 'edit', 'db', 'display',
 *                         'attribute', or 'js'.
 * @return mixed Sanitized field.
 */
function sanitize_multisite_term_field( $field, $value, $multisite_term_id, $multisite_taxonomy, $context ) {
	$int_fields = array( 'parent', 'multisite_term_id', 'count', 'multisite_term_group', 'multisite_term_multisite_taxonomy_id', 'object_id' );
	if ( in_array( $field, $int_fields, true ) ) {
		$value = (int) $value;
		if ( $value < 0 ) {
			$value = 0;
		}
	}

	if ( 'raw' === $context ) {
		return $value;
	}

	if ( 'edit' === $context ) {

		/**
		 * Filters a multisite term field to edit before it is sanitized.
		 *
		 * The dynamic portion of the filter name, `$field`, refers to the multisite term field.
		 *
		 * @param mixed $value     Value of the multisite term field.
		 * @param int   $multisite_term_id   Multisite term ID.
		 * @param string $multisite_taxonomy Multisite Taxonomy slug.
		 */
		$value = apply_filters( "edit_multisite_term_{$field}", $value, $multisite_term_id, $multisite_taxonomy );

		/**
		 * Filters the multisite taxonomy field to edit before it is sanitized.
		 *
		 * The dynamic portions of the filter name, `$multisite_taxonomy` and `$field`, refer
		 * to the multisite taxonomy slug and multisite taxonomy field, respectively.
		 *
		 * @param mixed $value   Value of the multisite taxonomy field to edit.
		 * @param int   $multisite_term_id Multisite term ID.
		 */
		$value = apply_filters( "edit_multisite_{$multisite_taxonomy}_{$field}", $value, $multisite_term_id );

		if ( 'description' === $field ) {
			$value = esc_html( $value );
		} else {
			$value = esc_attr( $value );
		}
	} elseif ( 'db' === $context ) {

		/**
		 * Filters a multisite term field value before it is sanitized.
		 *
		 * The dynamic portion of the filter name, `$field`, refers to the multisite term field.
		 *
		 * @param mixed  $value    Value of the multisite term field.
		 * @param string $multisite_taxonomy Multisite taxonomy slug.
		 */
		$value = apply_filters( "pre_multisite_term_{$field}", $value, $multisite_taxonomy );

		/**
		 * Filters a multisite taxonomy field before it is sanitized.
		 *
		 * The dynamic portions of the filter name, `$multisite_taxonomy` and `$field`, refer
		 * to the multisite taxonomy slug and field name, respectively.
		 *
		 * @param mixed $value Value of the multisite taxonomy field.
		 */
		$value = apply_filters( "pre_multisite_{$multisite_taxonomy}_{$field}", $value );

	} elseif ( 'rss' === $context ) {

		/**
		 * Filters the multisite term field for use in RSS.
		 *
		 * The dynamic portion of the filter name, `$field`, refers to the multisite term field.
		 *
		 * @param mixed  $value    Value of the multisite term field.
		 * @param string $multisite_taxonomy Multisite taxonomy slug.
		 */
		$value = apply_filters( "multisite_term_{$field}_rss", $value, $multisite_taxonomy );

		/**
		 * Filters the multisite taxonomy field for use in RSS.
		 *
		 * The dynamic portions of the hook name, `$multisite_taxonomy`, and `$field`, refer
		 * to the multisite taxonomy slug and field name, respectively.
		 *
		 * @param mixed $value Value of the multisite taxonomy field.
		 */
		$value = apply_filters( "multisite_{$multisite_taxonomy}_{$field}_rss", $value );
	} else {
		// Use display filters by default.
		/**
		 * Filters the multisite term field sanitized for display.
		 *
		 * The dynamic portion of the filter name, `$field`, refers to the multisite term field name.
		 *
		 * @param mixed  $value    Value of the multisite term field.
		 * @param int    $multisite_term_id  Multisite term ID.
		 * @param string $multisite_taxonomy Multisite taxonomy slug.
		 * @param string $context  Context to retrieve the multisite term field value.
		 */
		$value = apply_filters( "multisite_term_{$field}", $value, $multisite_term_id, $multisite_taxonomy, $context );

		/**
		 * Filters the multisite taxonomy field sanitized for display.
		 *
		 * The dynamic portions of the filter name, `$multisite_taxonomy`, and `$field`, refer
		 * to the multisite taxonomy slug and multisite taxonomy field, respectively.
		 *
		 * @param mixed  $value   Value of the multisite taxonomy field.
		 * @param int    $multisite_term_id Multisite term ID.
		 * @param string $context Context to retrieve the multisite taxonomy field value.
		 */
		$value = apply_filters( "multisite_{$multisite_taxonomy}_{$field}", $value, $multisite_term_id, $context );
	} // End if().

	if ( 'attribute' === $context ) {
		$value = esc_attr( $value );
	} elseif ( 'js' === $context ) {
		$value = esc_js( $value );
	}
	return $value;
}

/**
 * Count how many multisite terms are in multisite taxonomy.
 *
 * Default $args is 'hide_empty' which can be 'hide_empty=true' or array('hide_empty' => true).
 *
 * @param string       $multisite_taxonomy Multisite taxonomy name.
 * @param array|string $args     Optional. Array of arguments that get passed to get_multisite_terms().
 *                               Default empty array.
 * @return array|int|WP_Error Number of multisite terms in that multisite taxonomy or WP_Error if the multisite taxonomy does not exist.
 */
function count_multisite_terms( $multisite_taxonomy, $args = array() ) {
	$defaults = array(
		'hide_empty' => false,
	);

	$args = wp_parse_args( $args, $defaults );

	$args['fields']   = 'count';
	$args['taxonomy'] = $multisite_taxonomy;

	return get_multisite_terms( $args );
}

/**
 * Will unlink the object from the multisite taxonomy or multisite taxonomies.
 *
 * Will remove all relationships between the object and any multisite terms in
 * a particular multisite taxonomy or multisite taxonomies. Does not remove the multisite term or
 * multisite taxonomy itself.
 *
 * @param int          $object_id  The multisite term Object Id that refers to the multisite term.
 * @param string|array $multisite_taxonomies List of multisite taxonomy names or single multisite taxonomy name.
 */
function delete_object_multisite_term_relationships( $object_id, $multisite_taxonomies ) {
	$object_id = (int) $object_id;

	if ( ! is_array( $multisite_taxonomies ) ) {
		$multisite_taxonomies = array( $multisite_taxonomies );
	}

	foreach ( (array) $multisite_taxonomies as $multisite_taxonomy ) {
		$multisite_term_ids = get_object_multisite_terms(
			$object_id, $multisite_taxonomy, array(
				'fields' => 'ids',
			)
		);
		$multisite_term_ids = array_map( 'intval', $multisite_term_ids );
		remove_object_multisite_terms( $object_id, $multisite_term_ids, $multisite_taxonomy );
	}
}

/**
 * Removes a multisite term from the database.
 *
 * If the multisite term is a parent of other multisite terms, then the children will be updated to
 * that multisite term's parent.
 *
 * Metadata associated with the multisite term will be deleted.
 *
 * @global wpdb $wpdb WordPress database abstraction object.
 *
 * @param int          $multisite_term     Multisite term ID.
 * @param string       $multisite_taxonomy Multisite taxonomy name.
 * @param array|string $args {
 *     Optional. Array of arguments to override the default multisite term ID. Default empty array.
 *
 *     @type int  $default       The multisite term ID to make the default multisite term. This will only override
 *                               the multisite terms found if there is only one term found. Any other and
 *                               the found multisite terms are used.
 *     @type bool $force_default Optional. Whether to force the supplied multisite term as default to be
 *                               assigned even if the object was not going to be multisite term-less.
 *                               Default false.
 * }
 * @return bool|int|WP_Error True on success, false if multisite term does not exist. Zero on attempted
 *                           deletion of default Category. WP_Error if the multisite taxonomy does not exist.
 */
function delete_multisite_term( $multisite_term, $multisite_taxonomy, $args = array() ) {
	global $wpdb;

	$multisite_term = (int) $multisite_term;
	$ids            = multisite_term_exists( $multisite_term, $multisite_taxonomy );
	if ( ! $ids ) {
		return false;
	}
	if ( is_wp_error( $ids ) ) {
		return $ids;
	}
	$mtmt_id = $ids['multisite_term_multisite_taxonomy_id'];

	$defaults = array();

	$args = wp_parse_args( $args, $defaults );

	if ( isset( $args['default'] ) ) {
		$default = (int) $args['default'];
		if ( ! multisite_term_exists( $default, $multisite_taxonomy ) ) {
			unset( $default );
		}
	}

	if ( isset( $args['force_default'] ) ) {
		$force_default = $args['force_default'];
	}

	/**
	 * Fires when deleting a multisite term, before any modifications are made to posts or multisite terms.
	 *
	 * @param int    $multisite_term     Multisite term ID.
	 * @param string $multisite_taxonomy Multisite taxonomy name.
	 */
	do_action( 'pre_delete_multisite_term', $multisite_term, $multisite_taxonomy );

	// Update children to point to new parent.
	if ( is_multisite_taxonomy_hierarchical( $multisite_taxonomy ) ) {
		$multisite_term_obj = get_multisite_term( $multisite_term, $multisite_taxonomy );
		if ( is_wp_error( $multisite_term_obj ) ) {
			return $multisite_term_obj;
		}
		$parent = $multisite_term_obj->parent;

		$edit_ids      = $wpdb->get_results( $wpdb->prepare( "SELECT multisite_term_id, multisite_term_multisite_taxonomy_id FROM $wpdb->multisite_term_multisite_taxonomy WHERE `parent` = %d", (int) $multisite_term_obj->multisite_term_id ) );
		$edit_mtmt_ids = wp_list_pluck( $edit_ids, 'multisite_term_multisite_taxonomy_id' );

		/**
		 * Fires immediately before a multisite term to delete's children are reassigned a parent.
		 *
		 * @param array $edit_mtmt_ids An array of multisite term multisite taxonomy IDs for the given multisite term.
		 */
		do_action( 'edit_multisite_term_multisite_taxonomies', $edit_mtmt_ids );

		$wpdb->update(
			$wpdb->multisite_term_multisite_taxonomy, compact( 'parent' ), array(
				'parent' => $multisite_term_obj->multisite_term_id,
			) + compact( 'multisite_taxonomy' )
		);

		// Clean the cache for all child multisite terms.
		$edit_multisite_term_ids = wp_list_pluck( $edit_ids, 'multisite_term_id' );
		clean_multisite_term_cache( $edit_multisite_term_ids, $multisite_taxonomy );

		/**
		 * Fires immediately after a multisite term to delete's children are reassigned a parent.
		 *
		 * @param array $edit_mtmt_ids An array of multisite term multisite taxonomy IDs for the given multisite term.
		 */
		do_action( 'edited_multisite_term_multisite_taxonomies', $edit_mtmt_ids );
	}

	// Get the multisite term before deleting it or its multisite term relationships so we can pass to actions below.
	$deleted_multisite_term = get_multisite_term( $multisite_term, $multisite_taxonomy );

	$object_ids = (array) $wpdb->get_col( $wpdb->prepare( "SELECT object_id FROM $wpdb->multisite_term_relationships WHERE multisite_term_multisite_taxonomy_id = %d", $mtmt_id ) );

	foreach ( $object_ids as $object_id ) {
		$multisite_terms = get_object_multisite_terms(
			$object_id, $multisite_taxonomy, array(
				'fields'  => 'ids',
				'orderby' => 'none',
			)
		);
		if ( 1 === count( $multisite_terms ) && isset( $default ) ) {
			$multisite_terms = array( $default );
		} else {
			$multisite_terms = array_diff( $multisite_terms, array( $multisite_term ) );
			if ( isset( $default ) && isset( $force_default ) && $force_default ) {
				$multisite_terms = array_merge( $multisite_terms, array( $default ) );
			}
		}
		$multisite_terms = array_map( 'intval', $multisite_terms );
		set_object_multisite_terms( $object_id, $multisite_terms, $multisite_taxonomy );
	}

	// Clean the relationship caches for all object types using this multisite term.
	$multisite_tax_object = get_multisite_taxonomy( $multisite_taxonomy );
	foreach ( $multisite_tax_object->object_type as $object_type ) {
		clean_object_multisite_term_cache( $object_ids, $object_type );
	}
	$multisite_term_meta_ids = $wpdb->get_col( $wpdb->prepare( "SELECT meta_id FROM $wpdb->multisite_termmeta WHERE multisite_term_id = %d ", $multisite_term ) );
	foreach ( $multisite_term_meta_ids as $mid ) {
		delete_metadata_by_mid( 'multisite_term', $mid );
	}

	/**
	 * Fires immediately before a multisite term multisite taxonomy ID is deleted.
	 *
	 * @param int $mtmt_id Multisite term multisite taxonomy ID.
	 */
	do_action( 'delete_multisite_term_multisite_taxonomy', $mtmt_id );
	$wpdb->delete(
		$wpdb->multisite_term_multisite_taxonomy, array(
			'multisite_term_multisite_taxonomy_id' => $mtmt_id,
		)
	);

	/**
	 * Fires immediately after a multisite term multisite taxonomy ID is deleted.
	 *
	 * @param int $mtmt_id Multisite term multisite taxonomy ID.
	 */
	do_action( 'deleted_multisite_term_multisite_taxonomy', $mtmt_id );

	// Delete the multisite term if no multisite taxonomies use it.
	if ( ! $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM $wpdb->multisite_term_multisite_taxonomy WHERE multisite_term_id = %d", $multisite_term ) ) ) {
		$wpdb->delete(
			$wpdb->multisite_terms, array(
				'multisite_term_id' => $multisite_term,
			)
		);
	}
	clean_multisite_term_cache( $multisite_term, $multisite_taxonomy );

	/**
	 * Fires after a multisite term is deleted from the database and the cache is cleaned.
	 *
	 * @param int     $multisite_term         Multisite term ID.
	 * @param int     $mtmt_id        Multisite term multisite taxonomy ID.
	 * @param string  $multisite_taxonomy    Multisite taxonomy slug.
	 * @param mixed   $deleted_multisite_term Copy of the already-deleted multisite term, in the form specified
	 *                              by the parent function. WP_Error otherwise.
	 * @param array   $object_ids   List of multisite term object IDs.
	 */
	do_action( 'delete_multisite_term', $multisite_term, $mtmt_id, $multisite_taxonomy, $deleted_multisite_term, $object_ids );

	/**
	 * Fires after a multisite term in a specific multisite taxonomy is deleted.
	 *
	 * The dynamic portion of the hook name, `$multisite_taxonomy`, refers to the specific
	 * multisite taxonomy the multisite term belonged to.
	 *
	 * @param int     $multisite_term         Multisite term ID.
	 * @param int     $mtmt_id        Multisite term multisite taxonomy ID.
	 * @param mixed   $deleted_multisite_term Copy of the already-deleted multisite term, in the form specified
	 *                              by the parent function. WP_Error otherwise.
	 * @param array   $object_ids   List of multisite term object IDs.
	 */
	do_action( "delete_multisite_{$multisite_taxonomy}", $multisite_term, $mtmt_id, $deleted_multisite_term, $object_ids );

	return true;
}

/**
 * Retrieves the multisite terms associated with the given object(s), in the supplied multisite taxonomies.
 *
 * @global wpdb $wpdb WordPress database abstraction object.
 *
 * @param int|array    $object_ids The ID(s) of the object(s) to retrieve.
 * @param string|array $multisite_taxonomies The multisite taxonomies to retrieve multisite terms from.
 * @param array|string $args       See Multisite_Term_Query::__construct() for supported arguments.
 * @return array|WP_Error The requested multisite term data or empty array if no multisite terms found.
 *                        WP_Error if any of the $multisite_taxonomies don't exist.
 */
function get_object_multisite_terms( $object_ids, $multisite_taxonomies, $args = array() ) {
	global $wpdb;

	if ( empty( $object_ids ) || empty( $multisite_taxonomies ) ) {
		return array();
	}

	if ( ! is_array( $multisite_taxonomies ) ) {
		$multisite_taxonomies = array( $multisite_taxonomies );
	}

	foreach ( $multisite_taxonomies as $multisite_taxonomy ) {
		if ( ! multisite_taxonomy_exists( $multisite_taxonomy ) ) {
			return new WP_Error( 'invalid_multisite_taxonomy', __( 'Invalid multisite taxonomy.', 'multitaxo' ) );
		}
	}

	if ( ! is_array( $object_ids ) ) {
		$object_ids = array( $object_ids );
	}

	$object_ids = array_map( 'intval', $object_ids );

	$args = wp_parse_args( $args );

	$args['taxonomy']   = $multisite_taxonomies;
	$args['object_ids'] = $object_ids;

	$multisite_terms = get_multisite_terms( $args );

	/**
	 * Filters the multisite terms for a given object or objects.
	 *
	 * @param array $multisite_terms      An array of multisite terms for the given object or objects.
	 * @param array $object_ids Array of object IDs for which `$multisite_terms` were retrieved.
	 * @param array $multisite_taxonomies Array of multisite taxonomies from which `$multisite_terms` were retrieved.
	 * @param array $args       An array of arguments for retrieving multisite terms for the given
	 *                          object(s). See get_object_multisite_terms() for details.
	 */
	$multisite_terms = apply_filters( 'get_object_multisite_terms', $multisite_terms, $object_ids, $multisite_taxonomies, $args );

	$object_ids           = implode( ',', $object_ids );
	$multisite_taxonomies = "'" . implode( "', '", array_map( 'esc_sql', $multisite_taxonomies ) ) . "'";

	/**
	 * Filters the multisite terms for a given object or objects.
	 *
	 * The `$multisite_taxonomies` parameter passed to this filter is formatted as a SQL fragment. The
	 * {@see 'get_object_multisite_terms'} filter is recommended as an alternative.
	 *
	 * @param array     $multisite_terms      An array of multisite terms for the given object or objects.
	 * @param int|array $object_ids Object ID or array of IDs.
	 * @param string    $multisite_taxonomies SQL-formatted (comma-separated and quoted) list of multisite taxonomy names.
	 * @param array     $args       An array of arguments for retrieving multisite terms for the given object(s).
	 *                              See get_object_multisite_terms() for details.
	 */
	return apply_filters( 'get_object_multisite_terms', $multisite_terms, $object_ids, $multisite_taxonomies, $args );
}

/**
 * Add a new multisite term to the database.
 *
 * A non-existent multisite term is inserted in the following sequence:
 * 1. The multisite term is added to the multisite term table, then related to the multisite taxonomy.
 * 2. If everything is correct, several actions are fired.
 * 3. The 'multisite_term_id_filter' is evaluated.
 * 4. The multisite term cache is cleaned.
 * 5. Several more actions are fired.
 * 6. An array is returned containing the multisite term_id and multisite_term_multisite_taxonomy_id.
 *
 * If the 'slug' argument is not empty, then it is checked to see if the multisite term
 * is invalid. If it is not a valid, existing multisite term, it is added and the multisite term_id
 * is given.
 *
 * If the multisite taxonomy is hierarchical, and the 'parent' argument is not empty,
 * the multisite term is inserted and the multisite term_id will be given.
 *
 * Error handling:
 * If $multisite_taxonomy does not exist or $multisite_term is empty,
 * a WP_Error object will be returned.
 *
 * If the multisite term already exists on the same hierarchical level,
 * or the multisite term slug and name are not unique, a WP_Error object will be returned.
 *
 * @global wpdb $wpdb WordPress database abstraction object.
 *
 * @param string       $multisite_term     The multisite term to add or update.
 * @param string       $multisite_taxonomy The multisite taxonomy to which to add the multisite term.
 * @param array|string $args {
 *     Optional. Array or string of arguments for inserting a multisite term.
 *
 *     @type string $alias_of    Slug of the multisite term to make this multisite term an alias of.
 *                               Default empty string. Accepts a multisite term slug.
 *     @type string $description The multisite term description. Default empty string.
 *     @type int    $parent      The id of the parent multisite term. Default 0.
 *     @type string $slug        The multisite term slug to use. Default empty string.
 * }
 * @return array|WP_Error An array containing the `multisite_term_id` and `multisite_term_multisite_taxonomy_id`,
 *                        WP_Error otherwise.
 */
function insert_multisite_term( $multisite_term, $multisite_taxonomy, $args = array() ) {
	global $wpdb;

	if ( ! multisite_taxonomy_exists( $multisite_taxonomy ) ) {
		return new WP_Error( 'invalid_taxonomy', __( 'Invalid taxonomy.', 'multitaxo' ) );
	}
	/**
	 * Filters a multisite term before it is sanitized and inserted into the database.
	 *
	 * @param string $multisite_term     The multisite term to add or update.
	 * @param string $multisite_taxonomy Multisite taxonomy slug.
	 */
	$multisite_term = apply_filters( 'pre_insert_multisite_term', $multisite_term, $multisite_taxonomy );
	if ( is_wp_error( $multisite_term ) ) {
		return $multisite_term;
	}
	if ( is_int( $multisite_term ) && 0 === $multisite_term ) {
		return new WP_Error( 'invalid_multisite_term_id', __( 'Invalid multisite term ID.', 'multitaxo' ) );
	}
	if ( '' === trim( $multisite_term ) ) {
		return new WP_Error( 'empty_multisite_term_name', __( 'A name is required for this multisite term.', 'multitaxo' ) );
	}
	$defaults = array(
		'alias_of'    => '',
		'description' => '',
		'parent'      => 0,
		'slug'        => '',
	);
	$args     = wp_parse_args( $args, $defaults );

	if ( $args['parent'] > 0 && ! multisite_term_exists( (int) $args['parent'] ) ) {
		return new WP_Error( 'missing_parent', __( 'Parent multisite term does not exist.', 'multitaxo' ) );
	}

	$args['name']               = $multisite_term;
	$args['multisite_taxonomy'] = $multisite_taxonomy;

	// Coerce null description to strings, to avoid database errors.
	$args['description'] = (string) $args['description'];

	$args = sanitize_multisite_term( $args, $multisite_taxonomy, 'db' );

	$name        = wp_unslash( $args['name'] );
	$description = wp_unslash( $args['description'] );
	$parent      = (int) $args['parent'];

	$slug_provided = ! empty( $args['slug'] );
	if ( ! $slug_provided ) {
		$slug = sanitize_title( $name );
	} else {
		$slug = $args['slug'];
	}

	$multisite_term_group = 0;
	if ( $args['alias_of'] ) {
		$alias = get_multisite_term_by( 'slug', $args['alias_of'], $multisite_taxonomy );
		if ( ! empty( $alias->multisite_term_group ) ) {
			// The alias we want is already in a group, so let's use that one.
			$multisite_term_group = $alias->multisite_term_group;
		} elseif ( ! empty( $alias->multisite_term_id ) ) {
			/*
			 * The alias is not in a group, so we create a new one
			 * and add the alias to it.
			 */
			$multisite_term_group = $wpdb->get_var( "SELECT MAX(multisite_term_group) FROM $wpdb->multisite_terms" ) + 1;

			update_multisite_term(
				$alias->multisite_term_id, $multisite_taxonomy, array(
					'multisite_term_group' => $multisite_term_group,
				)
			);
		}
	}

	/*
	 * Prevent the creation of multisite terms with duplicate names at the same level of a multisite taxonomy hierarchy,
	 * unless a unique slug has been explicitly provided.
	 */
	$name_matches = get_multisite_terms(
		array(
			'name'       => $name,
			'hide_empty' => false,
			'taxonomy'   => $multisite_taxonomy,
		)
	);

	/*
	 * The `name` match in `get_multisite_terms()` doesn't differentiate accented characters,
	 * so we do a stricter comparison here.
	 */
	$name_match = null;
	if ( $name_matches ) {
		foreach ( $name_matches as $_match ) {
			if ( strtolower( $name ) === strtolower( $_match->name ) ) {
				$name_match = $_match;
				break;
			}
		}
	}

	if ( $name_match ) {
		$slug_match = get_multisite_term_by( 'slug', $slug, $multisite_taxonomy );
		if ( ! $slug_provided || $name_match->slug === $slug || $slug_match ) {
			if ( is_multisite_taxonomy_hierarchical( $multisite_taxonomy ) ) {
				$siblings = get_multisite_terms(
					array(
						'get'      => 'all',
						'parent'   => $parent,
						'taxonomy' => $multisite_taxonomy,
					)
				);

				$existing_multisite_term = null;
				if ( $name_match->slug === $slug && in_array( $name, wp_list_pluck( $siblings, 'name' ), true ) ) {
					$existing_multisite_term = $name_match;
				} elseif ( $slug_match && in_array( $slug, wp_list_pluck( $siblings, 'slug' ), true ) ) {
					$existing_multisite_term = $slug_match;
				}

				if ( $existing_multisite_term ) {
					return new WP_Error( 'multisite_term_exists', __( 'A term with the name provided already exists with this parent.', 'multitaxo' ), $existing_multisite_term->multisite_term_id );
				}
			} else {
				return new WP_Error( 'multisite_term_exists', __( 'A term with the name provided already exists in this taxonomy.', 'multitaxo' ), $name_match->multisite_term_id );
			}
		}
	}

	$slug = unique_multisite_term_slug( $slug, (object) $args );

	$data = compact( 'name', 'slug', 'multisite_term_group' );

	/**
	 * Filters multisite term data before it is inserted into the database.
	 *
	 * @param array  $data     Multisite term data to be inserted.
	 * @param string $multisite_taxonomy Multisite taxonomy slug.
	 * @param array  $args     Arguments passed to insert_multisite_term().
	 */
	$data = apply_filters( 'insert_multisite_term_data', $data, $multisite_taxonomy, $args );

	if ( false === $wpdb->insert( $wpdb->multisite_terms, $data ) ) {
		return new WP_Error( 'db_insert_error', __( 'Could not insert term into the database', 'multitaxo' ), $wpdb->last_error );
	}

	$multisite_term_id = (int) $wpdb->insert_id;

	// Seems unreachable, However, Is used in the case that a multisite term name is provided, which sanitizes to an empty string.
	if ( empty( $slug ) ) {
		$slug = sanitize_title( $slug, $multisite_term_id );

		/** This action is documented in wp-includes/taxonomy.php */
		do_action( 'edit_multisite_terms', $multisite_term_id, $multisite_taxonomy );
		$wpdb->update( $wpdb->multisite_terms, compact( 'slug' ), compact( 'multisite_term_id' ) );

		/** This action is documented in wp-includes/taxonomy.php */
		do_action( 'edited_multisite_terms', $multisite_term_id, $multisite_taxonomy );
	}

	$mtmt_id = $wpdb->get_var( $wpdb->prepare( "SELECT tt.multisite_term_multisite_taxonomy_id FROM $wpdb->multisite_term_multisite_taxonomy AS tt INNER JOIN $wpdb->multisite_terms AS t ON tt.multisite_term_id = t.multisite_term_id WHERE tt.multisite_taxonomy = %s AND t.multisite_term_id = %d", $multisite_taxonomy, $multisite_term_id ) );

	if ( ! empty( $mtmt_id ) ) {
		return array(
			'multisite_term_id'                    => $multisite_term_id,
			'multisite_term_multisite_taxonomy_id' => $mtmt_id,
		);
	}
	$wpdb->insert(
		$wpdb->multisite_term_multisite_taxonomy, compact( 'multisite_term_id', 'multisite_taxonomy', 'description', 'parent' ) + array(
			'count' => 0,
		)
	);
	$mtmt_id = (int) $wpdb->insert_id;

	/*
	 * Sanity check: if we just created a multisite term with the same parent + multisite taxonomy + slug but a higher multisite_term_id than
	 * an existing multisite term, then we have unwittingly created a duplicate multisite term. Delete the dupe, and use the multisite_term_id
	 * and multisite_term_multisite_taxonomy_id of the older multisite term instead. Then return out of the function so that the "create" hooks
	 * are not fired.
	 */
	$duplicate_multisite_term = $wpdb->get_row( $wpdb->prepare( "SELECT t.multisite_term_id, tt.multisite_term_multisite_taxonomy_id FROM $wpdb->multisite_terms t INNER JOIN $wpdb->multisite_term_multisite_taxonomy tt ON ( tt.multisite_term_id = t.multisite_term_id ) WHERE t.slug = %s AND tt.parent = %d AND tt.multisite_taxonomy = %s AND t.multisite_term_id < %d AND tt.multisite_term_multisite_taxonomy_id != %d", $slug, $parent, $multisite_taxonomy, $multisite_term_id, $mtmt_id ) );
	if ( $duplicate_multisite_term ) {
		$wpdb->delete(
			$wpdb->multisite_terms, array(
				'multisite_term_id' => $multisite_term_id,
			)
		);
		$wpdb->delete(
			$wpdb->multisite_term_multisite_taxonomy, array(
				'multisite_term_multisite_taxonomy_id' => $mtmt_id,
			)
		);

		$multisite_term_id = (int) $duplicate_multisite_term->multisite_term_id;
		$mtmt_id           = (int) $duplicate_multisite_term->multisite_term_multisite_taxonomy_id;

		clean_multisite_term_cache( $multisite_term_id, $multisite_taxonomy );
		return array(
			'multisite_term_id'                    => $multisite_term_id,
			'multisite_term_multisite_taxonomy_id' => $mtmt_id,
		);
	}

	/**
	 * Fires immediately after a new multisite term is created, before the multisite term cache is cleaned.
	 *
	 * @param int    $multisite_term_id  Multisite term ID.
	 * @param int    $mtmt_id    Multisite term multisite taxonomy ID.
	 * @param string $multisite_taxonomy Multisite taxonomy slug.
	 */
	do_action( 'create_multisite_term', $multisite_term_id, $mtmt_id, $multisite_taxonomy );

	/**
	 * Fires after a new multisite term is created for a specific taxonomy.
	 *
	 * The dynamic portion of the hook name, `$multisite_taxonomy`, refers
	 * to the slug of the multisite taxonomy the multisite term was created for.
	 *
	 * @param int $multisite_term_id Multisite term ID.
	 * @param int $mtmt_id   Multisite term multisite taxonomy ID.
	 */
	do_action( "create_multisite_{$multisite_taxonomy}", $multisite_term_id, $mtmt_id );

	/**
	 * Filters the multisite term ID after a new multisite term is created.
	 *
	 * @param int $multisite_term_id Multisite term ID.
	 * @param int $mtmt_id   Taxonomy term ID.
	 */
	$multisite_term_id = apply_filters( 'multisite_term_id_filter', $multisite_term_id, $mtmt_id );

	clean_multisite_term_cache( $multisite_term_id, $multisite_taxonomy );

	/**
	 * Fires after a new multisite term is created, and after the multisite term cache has been cleaned.
	 *
	 * @param int    $multisite_term_id  Multisite term ID.
	 * @param int    $mtmt_id    Multisite term multisite taxonomy ID.
	 * @param string $multisite_taxonomy Multisite taxonomy slug.
	 */
	do_action( 'created_multisite_term', $multisite_term_id, $mtmt_id, $multisite_taxonomy );

	/**
	 * Fires after a new multisite term in a specific multisite taxonomy is created, and after the multisite term
	 * cache has been cleaned.
	 *
	 * The dynamic portion of the hook name, `$multisite_taxonomy`, refers to the multisite taxonomy slug.
	 *
	 * @param int $multisite_term_id Multisite term ID.
	 * @param int $mtmt_id   Multisite term multisite taxonomy ID.
	 */
	do_action( "created_multisite_{$multisite_taxonomy}", $multisite_term_id, $mtmt_id );

	return array(
		'multisite_term_id'                    => $multisite_term_id,
		'multisite_term_multisite_taxonomy_id' => $mtmt_id,
	);
}

/**
 * Create multisite term and multisite taxonomy relationships.
 *
 * Relates an object (post) to a multisite term and multisite taxonomy type. Creates the
 * multisite term and multisite taxonomy relationship if it doesn't already exist. Creates a multisite term if
 * it doesn't exist (using the slug).
 *
 * A relationship means that the multisite term is grouped in or belongs to the multisite taxonomy.
 * A multisite term has no meaning until it is given context by defining which multisite taxonomy it
 * exists under.
 *
 * @global wpdb $wpdb The WordPress database abstraction object.
 *
 * @param int              $object_id The object to relate to.
 * @param array|int|string $multisite_terms     A single multisite term slug, single multisite term id, or array
 *                                              of either multisite term slugs or ids.
 *                                    Will replace all existing related multisite terms in this multisite taxonomy.
 * @param string           $multisite_taxonomy  The context in which to relate the multisite term to the object.
 * @param bool             $append    Optional. If false will delete difference of multisite terms. Default false.
 * @return array|WP_Error Multisite term multisite taxonomy IDs of the affected multisite terms.
 */
function set_object_multisite_terms( $object_id, $multisite_terms, $multisite_taxonomy, $append = false ) {
	global $wpdb;

	$object_id = (int) $object_id;

	if ( ! multisite_taxonomy_exists( $multisite_taxonomy ) ) {
		return new WP_Error( 'invalid_multisite_taxonomy', __( 'Invalid multisite taxonomy.', 'multitaxo' ) );
	}

	if ( ! is_array( $multisite_terms ) ) {
		$multisite_terms = array( $multisite_term );
	}
	if ( ! $append ) {
		$old_mtmt_ids = get_object_multisite_terms(
			$object_id, $multisite_taxonomy, array(
				'fields'  => 'mtmt_ids',
				'orderby' => 'none',
			)
		);
	} else {
		$old_mtmt_ids = array();
	}
	$mtmt_ids           = array();
	$multisite_term_ids = array();
	$new_mtmt_ids       = array();

	foreach ( (array) $multisite_terms as $multisite_term ) {
		if ( ! strlen( trim( $multisite_term ) ) ) {
			continue;
		}
		$multisite_term_info = multisite_term_exists( $multisite_term, $multisite_taxonomy );
		if ( ! $multisite_term_info ) {
			// Skip if a non-existent term ID is passed.
			if ( is_int( $multisite_term ) ) {
				continue;
			}
			$multisite_term_info = insert_multisite_term( $multisite_term, $multisite_taxonomy );
		}
		if ( is_wp_error( $multisite_term_info ) ) {
			return $multisite_term_info;
		}
		$multisite_term_ids[] = $multisite_term_info['multisite_term_id'];
		$mtmt_id              = $multisite_term_info['multisite_term_multisite_taxonomy_id'];
		$mtmt_ids[]           = $mtmt_id;

		if ( $wpdb->get_var( $wpdb->prepare( "SELECT multisite_term_multisite_taxonomy_id FROM $wpdb->multisite_term_relationships WHERE object_id = %d AND multisite_term_multisite_taxonomy_id = %d", $object_id, $mtmt_id ) ) ) {
			continue;
		}

		/**
		 * Fires immediately before an object-multisite_term relationship is added.
		 *
		 * @param int    $object_id Object ID.
		 * @param int    $mtmt_id     Multisite term multisite taxonomy ID.
		 * @param string $multisite_taxonomy Multisite taxonomy slug.
		 */
		do_action( 'add_multisite_term_relationship', $object_id, $mtmt_id, $multisite_taxonomy );
		$wpdb->insert(
			$wpdb->multisite_term_relationships, array(
				'object_id'                            => $object_id,
				'multisite_term_multisite_taxonomy_id' => $mtmt_id,
			)
		);

		/**
		 * Fires immediately after an object-multisite_term relationship is added.
		 *
		 * @param int    $object_id Object ID.
		 * @param int    $mtmt_id     Multisite term multisite taxonomy ID.
		 * @param string $multisite_taxonomy  Multisite taxonomy slug.
		 */
		do_action( 'added_multisite_term_relationship', $object_id, $mtmt_id, $multisite_taxonomy );
		$new_mtmt_ids[] = $mtmt_id;
	} // End foreach().

	if ( $new_mtmt_ids ) {
		update_multisite_term_count( $new_mtmt_ids, $multisite_taxonomy );
	}
	if ( ! $append ) {
		$delete_mtmt_ids = array_diff( $old_mtmt_ids, $mtmt_ids );

		if ( $delete_mtmt_ids ) {
			$in_delete_mtmt_ids        = "'" . implode( "', '", $delete_mtmt_ids ) . "'";
			$delete_multisite_term_ids = $wpdb->get_col( $wpdb->prepare( "SELECT tt.multisite_term_id FROM $wpdb->multisite_term_multisite_taxonomy AS tt WHERE tt.multisite_taxonomy = %s AND tt.multisite_term_multisite_taxonomy_id IN ($in_delete_mtmt_ids)", $multisite_taxonomy ) ); // WPCS: unprepared SQL ok.
			$delete_multisite_term_ids = array_map( 'intval', $delete_multisite_term_ids );

			$remove = remove_object_multisite_terms( $object_id, $delete_multisite_term_ids, $multisite_taxonomy );
			if ( is_wp_error( $remove ) ) {
				return $remove;
			}
		}
	}

	$t = get_multisite_taxonomy( $multisite_taxonomy );
	if ( ! $append && isset( $t->sort ) && $t->sort ) {
		$values               = array();
		$multisite_term_order = 0;
		$final_mtmt_ids       = get_object_multisite_terms(
			$object_id, $multisite_taxonomy, array(
				'fields' => 'mtmt_ids',
			)
		);
		foreach ( $mtmt_ids as $mtmt_id ) {
			if ( in_array( $mtmt_id, $final_mtmt_ids, true ) ) {
				$values[] = $wpdb->prepare( '(%d, %d, %d)', $object_id, $mtmt_id, ++$multisite_term_order );
			}
		}
		if ( $values ) {
			if ( false === $wpdb->query( "INSERT INTO $wpdb->multisite_term_relationships (object_id, multisite_term_multisite_taxonomy_id, multisite_term_order) VALUES " . join( ',', $values ) . ' ON DUPLICATE KEY UPDATE multisite_term_order = VALUES(multisite_term_order)' ) ) { // WPCS: unprepared SQL ok.
				return new WP_Error( 'db_insert_error', __( 'Could not insert multisite term relationship into the database', 'multitaxo' ), $wpdb->last_error );
			}
		}
	}

	wp_cache_delete( $object_id, $multisite_taxonomy . '_multisite_relationships' );
	wp_cache_delete( 'last_changed', 'multisite_terms' );

	/**
	 * Fires after an multisite object's terms have been set.
	 *
	 * @param int    $object_id  Object ID.
	 * @param array  $multisite_terms      An array of object multisite terms.
	 * @param array  $mtmt_ids     An array of multisite term multisite taxonomy IDs.
	 * @param string $multisite_taxonomy   Multisite taxonomy slug.
	 * @param bool   $append     Whether to append new multisite terms to the old multisite terms.
	 * @param array  $old_mtmt_ids Old array of multisite term multisite taxonomy IDs.
	 */
	do_action( 'set_object_multisite_terms', $object_id, $multisite_terms, $mtmt_ids, $multisite_taxonomy, $append, $old_mtmt_ids );
	return $mtmt_ids;
}

/**
 * Add multisite term(s) associated with a given object.
 *
 * @param int              $object_id The ID of the object to which the multisite terms will be added.
 * @param array|int|string $multisite_terms     The slug(s) or ID(s) of the multisite term(s) to add.
 * @param array|string     $multisite_taxonomy  Multisite taxonomy name.
 * @return array|WP_Error Multisite term multisite taxonomy IDs of the affected multisite terms.
 */
function add_object_multisite_terms( $object_id, $multisite_terms, $multisite_taxonomy ) {
	return set_object_multisite_terms( $object_id, $multisite_terms, $multisite_taxonomy, true );
}

/**
 * Remove multisite term(s) associated with a given object.
 *
 * @global wpdb $wpdb WordPress database abstraction object.
 *
 * @param int              $object_id The ID of the object from which the multisite terms will be removed.
 * @param array|int|string $multisite_terms     The slug(s) or ID(s) of the multisite term(s) to remove.
 * @param array|string     $multisite_taxonomy  Multisite taxonomy name.
 * @return bool|WP_Error True on success, false or WP_Error on failure.
 */
function remove_object_multisite_terms( $object_id, $multisite_terms, $multisite_taxonomy ) {
	global $wpdb;

	$object_id = (int) $object_id;

	if ( ! multisite_taxonomy_exists( $multisite_taxonomy ) ) {
		return new WP_Error( 'invalid_multisite_taxonomy', __( 'Invalid taxonomy.', 'multitaxo' ) );
	}

	if ( ! is_array( $multisite_terms ) ) {
		$multisite_terms = array( $multisite_terms );
	}

	$mtmt_ids = array();

	foreach ( (array) $multisite_terms as $multisite_term ) {
		if ( ! strlen( trim( $multisite_term ) ) ) {
			continue;
		}

		$multisite_term_info = multisite_term_exists( $multisite_term, $multisite_taxonomy );
		if ( ! $multisite_term_info ) {
			// Skip if a non-existent multisite term ID is passed.
			if ( is_int( $multisite_term ) ) {
				continue;
			}
		}

		if ( is_wp_error( $multisite_term_info ) ) {
			return $multisite_term_info;
		}

		$mtmt_ids[] = $multisite_term_info['multisite_term_multisite_taxonomy_id'];
	}

	if ( $mtmt_ids ) {
		$in_mtmt_ids = "'" . implode( "', '", $mtmt_ids ) . "'";

		/**
		 * Fires immediately before an object-multisite_term relationship is deleted.
		 *
		 * @param int   $object_id Object ID.
		 * @param array $mtmt_ids    An array of multisite term multisite taxonomy IDs.
		 * @param string $multisite_taxonomy  Multisite taxonomy slug.
		 */
		do_action( 'delete_multisite_term_relationships', $object_id, $mtmt_ids, $multisite_taxonomy );
		$deleted = $wpdb->query( $wpdb->prepare( "DELETE FROM $wpdb->multisite_term_relationships WHERE object_id = %d AND multisite_term_multisite_taxonomy_id IN ($in_mtmt_ids)", $object_id ) ); // WPCS: unprepared SQL ok.

		wp_cache_delete( $object_id, $multisite_taxonomy . '_multisite_relationships' );
		wp_cache_delete( 'last_changed', 'multisite_terms' );

		/**
		 * Fires immediately after an object-multisite_term relationship is deleted.
		 *
		 * @param int    $object_id Object ID.
		 * @param array  $mtmt_ids    An array of multisite term multisite taxonomy IDs.
		 * @param string $multisite_taxonomy  Multisite taxonomy slug.
		 */
		do_action( 'deleted_multisite_term_relationships', $object_id, $mtmt_ids, $multisite_taxonomy );

		update_multisite_term_count( $mtmt_ids, $multisite_taxonomy );

		return (bool) $deleted;
	}

	return false;
}

/**
 * Will make slug unique, if it isn't already.
 *
 * The `$slug` has to be unique global to every multisite taxonomy, meaning that one
 * multisite taxonomy multisite term can't have a matching slug with another multisite taxonomy multisite term. Each
 * slug has to be globally unique for every multisite taxonomy.
 *
 * The way this works is that if the multisite taxonomy that the multisite term belongs to is
 * hierarchical and has a parent, it will append that parent to the $slug.
 *
 * If that still doesn't return an unique slug, then it try to append a number
 * until it finds a number that is truly unique.
 *
 * The only purpose for `$multisite_term` is for appending a parent, if one exists.
 *
 * @global wpdb $wpdb WordPress database abstraction object.
 *
 * @param string $slug The string that will be tried for a unique slug.
 * @param object $multisite_term The multisite term object that the `$slug` will belong to.
 * @return string Will return a true unique slug.
 */
function unique_multisite_term_slug( $slug, $multisite_term ) {
	global $wpdb;

	$needs_suffix  = true;
	$original_slug = $slug;

	// As of 4.1, duplicate slugs are allowed as long as they're in different taxonomies.
	if ( ! multisite_term_exists( $slug ) || get_option( 'db_version' ) >= 30133 && ! get_multisite_term_by( 'slug', $slug, $multisite_term->multisite_taxonomy ) ) {
		$needs_suffix = false;
	}

	/*
	 * If the multisite taxonomy supports hierarchy and the multisite term has a parent, make the slug unique
	 * by incorporating parent slugs.
	 */
	$parent_suffix = '';
	if ( $needs_suffix && is_multisite_taxonomy_hierarchical( $multisite_term->multisite_taxonomy ) && ! empty( $multisite_term->parent ) ) {
		$the_parent = $multisite_term->parent;
		while ( ! empty( $the_parent ) ) {
			$parent_multisite_term = get_multisite_term( $the_parent, $multisite_term->multisite_taxonomy );
			if ( is_wp_error( $parent_multisite_term ) || empty( $parent_multisite_term ) ) {
				break;
			}
			$parent_suffix .= '-' . $parent_multisite_term->slug;
			if ( ! multisite_term_exists( $slug . $parent_suffix ) ) {
				break;
			}
			if ( empty( $parent_multisite_term->parent ) ) {
				break;
			}
			$the_parent = $parent_multisite_term->parent;
		}
	}

	// If we didn't get a unique slug, try appending a number to make it unique.
	/**
	 * Filters whether the proposed unique multisite term slug is bad.
	 *
	 * @param bool   $needs_suffix Whether the slug needs to be made unique with a suffix.
	 * @param string $slug         The slug.
	 * @param object $multisite_term         Multisite term object.
	 */
	if ( apply_filters( 'unique_multisite_term_slug_is_bad_slug', $needs_suffix, $slug, $multisite_term ) ) {
		if ( $parent_suffix ) {
			$slug .= $parent_suffix;
		} else {
			if ( ! empty( $multisite_term->multisite_term_id ) ) {
				$query = $wpdb->prepare( "SELECT slug FROM $wpdb->multisite_terms WHERE slug = %s AND multisite_term_id != %d", $slug, $multisite_term->multisite_term_id );
			} else {
				$query = $wpdb->prepare( "SELECT slug FROM $wpdb->multisite_terms WHERE slug = %s", $slug );
			}
			if ( $wpdb->get_var( $query ) ) { // WPCS: unprepared SQL ok.
				$num = 2;
				do {
					$alt_slug = $slug . "-$num";
					$num++;
					$slug_check = $wpdb->get_var( $wpdb->prepare( "SELECT slug FROM $wpdb->multisite_terms WHERE slug = %s", $alt_slug ) );
				} while ( $slug_check );
				$slug = $alt_slug;
			}
		}
	}

	/**
	 * Filters the unique multisite term slug.
	 *
	 * @param string $slug          Unique multisite term slug.
	 * @param object $multisite_term          Multisite term object.
	 * @param string $original_slug Slug originally passed to the function for testing.
	 */
	return apply_filters( 'unique_multisite_term_slug', $slug, $multisite_term, $original_slug );
}

/**
 * Update multisite term based on arguments provided.
 *
 * The $args will indiscriminately override all values with the same field name.
 * Care must be taken to not override important information need to update or
 * update will fail (or perhaps create a new multisite term, neither would be acceptable).
 *
 * Defaults will set 'alias_of', 'description', 'parent', and 'slug' if not
 * defined in $args already.
 *
 * 'alias_of' will create a multisite term group, if it doesn't already exist, and update
 * it for the $multisite_term.
 *
 * If the 'slug' argument in $args is missing, then the 'name' in $args will be
 * used. It should also be noted that if you set 'slug' and it isn't unique then
 * a WP_Error will be passed back. If you don't pass any slug, then a unique one
 * will be created for you.
 *
 * For what can be overrode in `$args`, check the multisite term scheme can contain and stay
 * away from the multisite term keys.
 *
 * @global wpdb $wpdb WordPress database abstraction object.
 *
 * @param int          $multisite_term_id  The ID of the multisite term.
 * @param string       $multisite_taxonomy The context in which to relate the multisite term to the object.
 * @param array|string $args     Optional. Array of get_multisite_terms() arguments. Default empty array.
 * @return array|WP_Error Returns Multisite term ID and multisite taxonomy multisite term ID
 */
function update_multisite_term( $multisite_term_id, $multisite_taxonomy, $args = array() ) {
	global $wpdb;

	if ( ! multisite_taxonomy_exists( $multisite_taxonomy ) ) {
		return new WP_Error( 'invalid_multisite_taxonomy', __( 'Invalid multisite taxonomy.', 'multitaxo' ) );
	}

	$multisite_term_id = (int) $multisite_term_id;

	// First, get all of the original args.
	$multisite_term = get_multisite_term( $multisite_term_id, $multisite_taxonomy );

	if ( is_wp_error( $multisite_term ) ) {
		return $multisite_term;
	}

	if ( ! $multisite_term ) {
		return new WP_Error( 'invalid_multisite_term', __( 'Empty multisite term', 'multitaxo' ) );
	}

	$multisite_term = (array) $multisite_term->data;

	// Escape data pulled from DB.
	$multisite_term = wp_slash( $multisite_term );

	// Merge old and new args with new args overwriting old ones.
	$args = array_merge( $multisite_term, $args );

	$defaults    = array(
		'alias_of'    => '',
		'description' => '',
		'parent'      => 0,
		'slug'        => '',
	);
	$args        = wp_parse_args( $args, $defaults );
	$args        = sanitize_multisite_term( $args, $multisite_taxonomy, 'db' );
	$parsed_args = $args;

	$name        = wp_unslash( $args['name'] );
	$description = wp_unslash( $args['description'] );

	$parsed_args['name']        = $name;
	$parsed_args['description'] = $description;

	if ( '' === trim( $name ) ) {
		return new WP_Error( 'empty_multisite_term_name', __( 'A name is required for this multisite term.', 'multitaxo' ) );
	}

	if ( $parsed_args['parent'] > 0 && ! multisite_term_exists( (int) $parsed_args['parent'] ) ) {
		return new WP_Error( 'missing_multisite_parent', __( 'Parent missing multisite term does not exist.', 'multitaxo' ) );
	}

	$empty_slug = false;
	if ( empty( $args['slug'] ) ) {
		$empty_slug = true;
		$slug       = sanitize_title( $name );
	} else {
		$slug = $args['slug'];
	}

	$parsed_args['slug'] = $slug;

	$multisite_term_group = isset( $parsed_args['multisite_term_group'] ) ? $parsed_args['multisite_term_group'] : 0;
	if ( $args['alias_of'] ) {
		$alias = get_multisite_term_by( 'slug', $args['alias_of'], $multisite_taxonomy );
		if ( ! empty( $alias->multisite_term_group ) ) {
			// The alias we want is already in a group, so let's use that one.
			$multisite_term_group = $alias->multisite_term_group;
		} elseif ( ! empty( $alias->multisite_term_id ) ) {
			/*
			 * The alias is not in a group, so we create a new one
			 * and add the alias to it.
			 */
			$multisite_term_group = $wpdb->get_var( "SELECT MAX(multisite_term_group) FROM $wpdb->multisite_terms" ) + 1;

			update_multisite_term(
				$alias->multisite_term_id, $multisite_taxonomy, array(
					'multisite_term_group' => $multisite_term_group,
				)
			);
		}

		$parsed_args['multisite_term_group'] = $multisite_term_group;
	}

	/**
	 * Filters the multisite term parent.
	 *
	 * Hook to this filter to see if it will cause a hierarchy loop.
	 *
	 * @param int    $parent      ID of the parent multisite term.
	 * @param int    $multisite_term_id     Multisite term ID.
	 * @param string $multisite_taxonomy    Multisite taxonomy slug.
	 * @param array  $parsed_args An array of potentially altered update arguments for the given multisite term.
	 * @param array  $args        An array of update arguments for the given multisite term.
	 */
	$parent = apply_filters( 'update_multisite_term_parent', $args['parent'], $multisite_term_id, $multisite_taxonomy, $parsed_args, $args );

	// Check for duplicate slug.
	$duplicate = get_multisite_term_by( 'slug', $slug, $multisite_taxonomy );
	if ( $duplicate && $duplicate->multisite_term_id !== $multisite_term_id ) {
		// If an empty slug was passed or the parent changed, reset the slug to something unique.
		// Otherwise, bail.
		if ( $empty_slug || ( $parent !== $multisite_term['parent'] ) ) {
			$slug = unique_multisite_term_slug( $slug, (object) $args );
		} else {
			/* translators: 1: Multisite taxonomy multisite term slug */
			return new WP_Error( 'duplicate_multisite_term_slug', sprintf( __( 'The slug &#8220;%s&#8221; is already in use by another multisite term', 'multitaxo' ), $slug ) );
		}
	}

	$mtmt_id = (int) $wpdb->get_var( $wpdb->prepare( "SELECT tt.multisite_term_multisite_taxonomy_id FROM $wpdb->multisite_term_multisite_taxonomy AS tt INNER JOIN $wpdb->multisite_terms AS t ON tt.multisite_term_id = t.multisite_term_id WHERE tt.multisite_taxonomy = %s AND t.multisite_term_id = %d", $multisite_taxonomy, $multisite_term_id ) );

	/**
	 * Fires immediately before the given terms are edited.
	 *
	 * @param int    $multisite_term_id  Multisite term ID.
	 * @param string $multisite_taxonomy Multisite taxonomy slug.
	 */
	do_action( 'edit_multisite_terms', $multisite_term_id, $multisite_taxonomy );

	$data = compact( 'name', 'slug', 'multisite_term_group' );

	/**
	 * Filters multisite term data before it is updated in the database.
	 *
	 * @param array  $data     Multisite term data to be updated.
	 * @param int    $multisite_term_id  Multisite term ID.
	 * @param string $multisite_taxonomy Multisite taxonomy slug.
	 * @param array  $args     Arguments passed to update_multisite_term().
	 */
	$data = apply_filters( 'update_multisite_term_data', $data, $multisite_term_id, $multisite_taxonomy, $args );

	$wpdb->update( $wpdb->multisite_terms, $data, compact( 'multisite_term_id' ) );
	if ( empty( $slug ) ) {
		$slug = sanitize_title( $name, $multisite_term_id );
		$wpdb->update( $wpdb->multisite_terms, compact( 'slug' ), compact( 'multisite_term_id' ) );
	}

	/**
	 * Fires immediately after the given terms are edited.
	 *
	 * @param int    $multisite_term_id  Multisite term ID.
	 * @param string $multisite_taxonomy Multisite taxonomy slug.
	 */
	do_action( 'edited_multisite_terms', $multisite_term_id, $multisite_taxonomy );

	/**
	 * Fires immediate before a multisite term-taxonomy relationship is updated.
	 *
	 * @param int    $mtmt_id    Multisite term multisite taxonomy ID.
	 * @param string $multisite_taxonomy Multisite taxonomy slug.
	 */
	do_action( 'edit_multisite_term_multisite_taxonomy', $mtmt_id, $multisite_taxonomy );

	$wpdb->update(
		$wpdb->multisite_term_multisite_taxonomy, compact( 'multisite_term_id', 'multisite_taxonomy', 'description', 'parent' ), array(
			'multisite_term_multisite_taxonomy_id' => $mtmt_id,
		)
	);

	/**
	 * Fires immediately after a multisite term-taxonomy relationship is updated.
	 *
	 * @param int    $mtmt_id    Multisite term multisite taxonomy ID.
	 * @param string $multisite_taxonomy Multisite taxonomy slug.
	 */
	do_action( 'edited_multisite_term_multisite_taxonomy', $mtmt_id, $multisite_taxonomy );

	/**
	 * Fires after a multisite term has been updated, but before the multisite term cache has been cleaned.
	 *
	 * @param int    $multisite_term_id  Multisite term ID.
	 * @param int    $mtmt_id    Multisite term multisite taxonomy ID.
	 * @param string $multisite_taxonomy Multisite taxonomy slug.
	 */
	do_action( 'edit_multisite_term', $multisite_term_id, $mtmt_id, $multisite_taxonomy );

	/**
	 * Fires after a multisite term in a specific multisite taxonomy has been updated, but before the multisite term
	 * cache has been cleaned.
	 *
	 * The dynamic portion of the hook name, `$multisite_taxonomy`, refers to the multisite taxonomy slug.
	 *
	 * @param int $multisite_term_id Multisite term ID.
	 * @param int $mtmt_id   Multisite term multisite taxonomy ID.
	 */
	do_action( "edit_multisite_{$multisite_taxonomy}", $multisite_term_id, $mtmt_id );

	$multisite_term_id = apply_filters( 'multisite_term_id_filter', $multisite_term_id, $mtmt_id );

	clean_multisite_term_cache( $multisite_term_id, $multisite_taxonomy );

	/**
	 * Fires after a multisite term has been updated, and the multisite term cache has been cleaned.
	 *
	 * @param int    $multisite_term_id  Multisite term ID.
	 * @param int    $mtmt_id    Multisite term multisite taxonomy ID.
	 * @param string $multisite_taxonomy Multisite taxonomy slug.
	 */
	do_action( 'edited_multisite_term', $multisite_term_id, $mtmt_id, $multisite_taxonomy );

	/**
	 * Fires after a multisite term for a specific multisite taxonomy has been updated, and the multisite term
	 * cache has been cleaned.
	 *
	 * The dynamic portion of the hook name, `$multisite_taxonomy`, refers to the multisite taxonomy slug.
	 *
	 * @param int $multisite_term_id Multisite term ID.
	 * @param int $mtmt_id   Multisite term multisite taxonomy ID.
	 */
	do_action( "edited_multisite_{$multisite_taxonomy}", $multisite_term_id, $mtmt_id );

	return array(
		'multisite_term_id'                    => $multisite_term_id,
		'multisite_term_multisite_taxonomy_id' => $mtmt_id,
	);
}

/**
 * Enable or disable multisite term counting.
 *
 * @staticvar bool $_defer
 *
 * @param bool $defer Optional. Enable if true, disable if false.
 * @return bool Whether multisite term counting is enabled or disabled.
 */
function defer_multisite_term_counting( $defer = null ) {
	static $_defer = false;

	if ( is_bool( $defer ) ) {
		$_defer = $defer;
		// Flush any deferred counts.
		if ( ! $defer ) {
			update_multisite_term_count( null, null, true );
		}
	}
	return $_defer;
}

/**
 * Updates the amount of multisite terms in multisite taxonomy.
 *
 * If there is a multisite taxonomy callback applied, then it will be called for updating
 * the count.
 *
 * The default action is to count what the amount of multisite terms have the relationship
 * of multisite term ID. Once that is done, then update the database.
 *
 * @staticvar array $_deferred
 *
 * @param int|array $multisite_terms       The multisite_term_multisite_taxonomy_id of the multisite terms.
 * @param string    $multisite_taxonomy    The context of the multisite term.
 * @param bool      $do_deferred Whether to flush the deferred multisite term counts too. Default false.
 * @return bool If no terms will return false, and if successful will return true.
 */
function update_multisite_term_count( $multisite_terms, $multisite_taxonomy, $do_deferred = false ) {
	static $_deferred = array();

	if ( $do_deferred ) {
		foreach ( (array) array_keys( $_deferred ) as $multisite_taxonomy ) {
			update_multisite_term_count_now( $_deferred[ $tax ], $multisite_taxonomy );
			unset( $_deferred[ $tax ] );
		}
	}

	if ( empty( $multisite_terms ) ) {
		return false;
	}

	if ( ! is_array( $multisite_terms ) ) {
		$multisite_terms = array( $multisite_terms );
	}
	if ( defer_multisite_term_counting() ) {
		if ( ! isset( $_deferred[ $multisite_taxonomy ] ) ) {
			$_deferred[ $multisite_taxonomy ] = array();
		}
		$_deferred[ $multisite_taxonomy ] = array_unique( array_merge( $_deferred[ $multisite_taxonomy ], $multisite_terms ) );
		return true;
	}

	return update_multisite_term_count_now( $multisite_terms, $multisite_taxonomy );
}

/**
 * Perform multisite term count update immediately.
 *
 * @param array  $multisite_terms    The multisite_term_multisite_taxonomy_id of multisite terms to update.
 * @param string $multisite_taxonomy The context of the multisite term.
 * @return true Always true when complete.
 */
function update_multisite_term_count_now( $multisite_terms, $multisite_taxonomy ) {
	$multisite_terms = array_map( 'intval', $multisite_terms );

	$multisite_taxonomy = get_multisite_taxonomy( $multisite_taxonomy );
	if ( ! empty( $multisite_taxonomy->update_count_callback ) ) {
		call_user_func( $multisite_taxonomy->update_count_callback, $multisite_terms, $multisite_taxonomy );
	} else {
		$object_types = (array) $multisite_taxonomy->object_type;
		foreach ( $object_types as &$object_type ) {
			if ( 0 === strpos( $object_type, 'attachment:' ) ) {
				list( $object_type ) = explode( ':', $object_type );
			}
		}

		if ( array_filter( $object_types, 'post_type_exists' ) === $object_types ) {
			// Only post types are attached to this multisite taxonomy.
			_update_post_multisite_term_count( $multisite_terms, $multisite_taxonomy );
		} else {
			// Default count updater.
			_update_generic_multisite_term_count( $multisite_terms, $multisite_taxonomy );
		}
	}

	clean_multisite_term_cache( $multisite_terms, '', false );

	return true;
}

/*
 * Cache
 */

/**
 * Removes the multisite taxonomy relationship to multisite terms from the cache.
 *
 * Will remove the entire multisite taxonomy relationship containing multisite term `$object_id`. The
 * multisite term IDs have to exist within the multisite taxonomy `$object_type` for the deletion to
 * take place.
 *
 * @global bool $_wp_suspend_cache_invalidation
 *
 * @see get_object_multisite_taxonomies() for more on $object_type.
 *
 * @param int|array    $object_ids  Single or list of multisite term object ID(s).
 * @param array|string $object_type The multisite taxonomy object type.
 */
function clean_object_multisite_term_cache( $object_ids, $object_type ) {
	global $_wp_suspend_cache_invalidation;

	if ( ! empty( $_wp_suspend_cache_invalidation ) ) {
		return;
	}

	if ( ! is_array( $object_ids ) ) {
		$object_ids = array( $object_id );
	}
	$multisite_taxonomies = get_object_multisite_taxonomies( $object_type );

	foreach ( $object_ids as $id ) {
		foreach ( $multisite_taxonomies as $multisite_taxonomy ) {
			wp_cache_delete( $id, "{$multisite_taxonomy}_multisite_relationships" );
		}
	}

	/**
	 * Fires after the object multisite term cache has been cleaned.
	 *
	 * @param array  $object_ids An array of object IDs.
	 * @param string $objet_type Object type.
	 */
	do_action( 'clean_object_multisite_term_cache', $object_ids, $object_type );
}

/**
 * Will remove all of the multisite term ids from the cache.
 *
 * @global wpdb $wpdb WordPress database abstraction object.
 * @global bool $_wp_suspend_cache_invalidation
 *
 * @param int|array $ids            Single or list of multisite term IDs.
 * @param string    $multisite_taxonomy       Optional. Can be empty and will assume `mtmt_ids`, else will use for context.
 *                                  Default empty.
 * @param bool      $clean_taxonomy Optional. Whether to clean multisite taxonomy wide caches (true), or just individual
 *                                  multisite term object caches (false). Default true.
 */
function clean_multisite_term_cache( $ids, $multisite_taxonomy = '', $clean_taxonomy = true ) {
	global $wpdb, $_wp_suspend_cache_invalidation;

	if ( ! empty( $_wp_suspend_cache_invalidation ) ) {
		return;
	}

	if ( ! is_array( $ids ) ) {
		$ids = array( $ids );
	}

	$multisite_taxonomies = array();
	// If no multisite taxonomy, assume mtmt_ids.
	if ( empty( $multisite_taxonomy ) ) {
		$mtmt_ids        = array_map( 'intval', $ids );
		$mtmt_ids        = implode( ', ', $mtmt_ids );
		$multisite_terms = $wpdb->get_results( $wpdb->prepare( "SELECT multisite_term_id, multisite_taxonomy FROM $wpdb->multisite_term_multisite_taxonomy WHERE multisite_term_multisite_taxonomy_id IN ($mtmt_ids)" ) ); // WPCS: unprepared SQL ok.
		$ids             = array();
		foreach ( (array) $multisite_terms as $multisite_term ) {
			$multisite_taxonomies[] = $multisite_term->multisite_taxonomy;
			$ids[]                  = $multisite_term->multisite_term_id;
			wp_cache_delete( $multisite_term->multisite_term_id, 'multisite_terms' );
		}
		$multisite_taxonomies = array_unique( $multisite_taxonomies );
	} else {
		$multisite_taxonomies = array( $multisite_taxonomy );
		foreach ( $multisite_taxonomies as $multisite_taxonomy ) {
			foreach ( $ids as $id ) {
				wp_cache_delete( $id, 'multisite_terms' );
			}
		}
	}

	foreach ( $multisite_taxonomies as $multisite_taxonomy ) {
		if ( $clean_taxonomy ) {
			wp_cache_delete( 'all_ids', $multisite_taxonomy );
			wp_cache_delete( 'get', $multisite_taxonomy );
			delete_option( "multisite_{$multisite_taxonomy}_children" );
			// Regenerate multisite_{$multisite_taxonomy}_children.
			_get_multisite_term_hierarchy( $multisite_taxonomy );
		}

		/**
		 * Fires once after each taxonomy's term cache has been cleaned.
		 *
		 * @param array  $ids            An array of multisite term IDs.
		 * @param string $multisite_taxonomy       Multisite taxonomy slug.
		 * @param bool   $clean_taxonomy Whether or not to clean taxonomy-wide caches.
		 */
		do_action( 'clean_multisite_term_cache', $ids, $multisite_taxonomy, $clean_taxonomy );
	}

	wp_cache_set( 'last_changed', microtime(), 'multisite_terms' );
}

/**
 * Retrieves the multisite taxonomy relationship to the multisite term object id.
 *
 * Upstream functions (like get_the_multisite_terms() and is_object_in_multisite_term()) are
 * responsible for populating the object-term relationship cache. The current
 * function only fetches relationship data that is already in the cache.
 *
 * @param int    $id       Multisite term object ID.
 * @param string $multisite_taxonomy Multisite taxonomy name.
 * @return bool|array|WP_Error Array of `Multisite_Term` objects, if cached.
 *                             False if cache is empty for `$multisite_taxonomy` and `$id`.
 *                             WP_Error if get_multisite_term() returns an error object for any multisite term.
 */
function get_object_multisite_term_cache( $id, $multisite_taxonomy ) {
	$_multisite_term_ids = wp_cache_get( $id, "{$multisite_taxonomy}_multisite_relationships" );

	// We leave the priming of relationship caches to upstream functions.
	if ( false === $_multisite_term_ids ) {
		return false;
	}

	// Backward compatibility for if a plugin is putting objects into the cache, rather than IDs.
	$multisite_term_ids = array();
	foreach ( $_multisite_term_ids as $multisite_term_id ) {
		if ( is_numeric( $multisite_term_id ) ) {
			$multisite_term_ids[] = intval( $multisite_term_id );
		} elseif ( isset( $multisite_term_id->multisite_term_id ) ) {
			$multisite_term_ids[] = intval( $multisite_term_id->multisite_term_id );
		}
	}

	// Fill the multisite term objects.
	_prime_multisite_term_caches( $multisite_term_ids );

	$multisite_terms = array();
	foreach ( $multisite_term_ids as $multisite_term_id ) {
		$multisite_term = get_multisite_term( $multisite_term_id, $multisite_taxonomy );
		if ( is_wp_error( $multisite_term ) ) {
			return $multisite_term;
		}

		$multisite_terms[] = $multisite_term;
	}

	return $multisite_terms;
}

/**
 * Updates the cache for the given multisite term object ID(s).
 *
 * Note: Due to performance concerns, great care should be taken to only update
 * multisite term caches when necessary. Processing time can increase exponentially depending
 * on both the number of passed multisite term IDs and the number of multisite taxonomies those multisite terms
 * belong to.
 *
 * Caches will only be updated for multisite terms not already cached.
 *
 * @param string|array $object_ids  Comma-separated list or array of multisite term object IDs.
 * @param array|string $object_type The multisite taxonomy object type.
 * @return void|false False if all of the multisite terms in `$object_ids` are already cached.
 */
function update_object_multisite_term_cache( $object_ids, $object_type ) {
	if ( empty( $object_ids ) ) {
		return;
	}
	if ( ! is_array( $object_ids ) ) {
		$object_ids = explode( ',', $object_ids );
	}
	$object_ids = array_map( 'intval', $object_ids );

	$multisite_taxonomies = get_object_multisite_taxonomies( $object_type );

	$ids = array();
	foreach ( (array) $object_ids as $id ) {
		foreach ( $multisite_taxonomies as $multisite_taxonomy ) {
			if ( false === wp_cache_get( $id, "{$multisite_taxonomy}_multisite_relationships" ) ) {
				$ids[] = $id;
				break;
			}
		}
	}

	if ( empty( $ids ) ) {
		return false;
	}
	$multisite_terms = get_object_multisite_terms(
		$ids, $multisite_taxonomies, array(
			'fields'                           => 'all_with_object_id',
			'orderby'                          => 'name',
			'update_multisite_term_meta_cache' => false,
		)
	);

	$object_multisite_terms = array();
	foreach ( (array) $multisite_terms as $multisite_term ) {
		$object_multisite_terms[ $multisite_term->object_id ][ $multisite_term->multisite_taxonomy ][] = $multisite_term->multisite_term_id;
	}

	foreach ( $ids as $id ) {
		foreach ( $multisite_taxonomies as $multisite_taxonomy ) {
			if ( ! isset( $object_multisite_terms[ $id ][ $multisite_taxonomy ] ) ) {
				if ( ! isset( $object_multisite_terms[ $id ] ) ) {
					$object_multisite_terms[ $id ] = array();
				}
				$object_multisite_terms[ $id ][ $multisite_taxonomy ] = array();
			}
		}
	}

	foreach ( $object_multisite_terms as $id => $value ) {
		foreach ( $value as $multisite_taxonomy => $multisite_terms ) {
			wp_cache_add( $id, $multisite_terms, "{$multisite_taxonomy}_multisite_relationships" );
		}
	}
}

/**
 * Updates multisite terms to multisite taxonomy in cache.
 *
 * @param array  $multisite_terms    List of multisite term objects to change.
 * @param string $multisite_taxonomy Optional. Update multisite term to this multisite taxonomy in cache. Default empty.
 */
function update_multisite_term_cache( $multisite_terms, $multisite_taxonomy = '' ) {
	foreach ( (array) $multisite_terms as $multisite_term ) {
		// Create a copy in case the array was passed by reference.
		$_multisite_term = clone $multisite_term;

		// Object ID should not be cached.
		unset( $_multisite_term->object_id );

		wp_cache_add( $multisite_term->multisite_term_id, $_multisite_term, 'multisite_terms' );
	}
}

/*
 * Private.
 */

/**
 * Retrieves children of multisite taxonomy as multisite term IDs.
 *
 * @param string $multisite_taxonomy Multisite taxonomy name.
 * @return array Empty if $multisite_taxonomy isn't hierarchical or returns children as multisite term IDs.
 */
function _get_multisite_term_hierarchy( $multisite_taxonomy ) {
	if ( ! is_multisite_taxonomy_hierarchical( $multisite_taxonomy ) ) {
		return array();
	}
	$children = get_option( "multisite_{$multisite_taxonomy}_children" );

	if ( is_array( $children ) ) {
		return $children;
	}
	$children        = array();
	$multisite_terms = get_multisite_terms(
		array(
			'get'      => 'all',
			'orderby'  => 'id',
			'fields'   => 'id=>parent',
			'taxonomy' => $multisite_taxonomy,
		)
	);
	foreach ( $multisite_terms as $multisite_term_id => $parent ) {
		if ( $parent > 0 ) {
			$children[ $parent ][] = $multisite_term_id;
		}
	}
	update_option( "multisite_{$multisite_taxonomy}_children", $children );

	return $children;
}

/**
 * Get the subset of $multisite_terms that are descendants of $multisite_term_id.
 *
 * If `$multisite_terms` is an array of objects, then _get_multisite_term_children() returns an array of objects.
 * If `$multisite_terms` is an array of IDs, then _get_multisite_term_children() returns an array of IDs.
 *
 * @param int    $multisite_term_id   The ancestor multisite term: all returned multisite terms should be descendants of `$multisite_term_id`.
 * @param array  $multisite_terms     The set of multisite terms - either an array of multisite term objects or multisite term IDs - from which those that
 *                          are descendants of $multisite_term_id will be chosen.
 * @param string $multisite_taxonomy  The multisite taxonomy which determines the hierarchy of the multisite terms.
 * @param array  $ancestors Optional. Multisite Term ancestors that have already been identified. Passed by reference, to keep
 *                          track of found multisite terms when recursing the hierarchy. The array of located ancestors is used
 *                          to prevent infinite recursion loops. For performance, `multisite_term_ids` are used as array keys,
 *                          with 1 as value. Default empty array.
 * @return array|WP_Error The subset of $multisite_terms that are descendants of $multisite_term_id.
 */
function _get_multisite_term_children( $multisite_term_id, $multisite_terms, $multisite_taxonomy, &$ancestors = array() ) {
	$empty_array = array();
	if ( empty( $multisite_terms ) ) {
		return $empty_array;
	}
	$multisite_term_list = array();
	$has_children        = _get_multisite_term_hierarchy( $multisite_taxonomy );

	if ( ( 0 !== $multisite_term_id ) && ! isset( $has_children[ $multisite_term_id ] ) ) {
		return $empty_array;
	}
	// Include the multisite term itself in the ancestors array, so we can properly detect when a loop has occurred.
	if ( empty( $ancestors ) ) {
		$ancestors[ $multisite_term_id ] = 1;
	}

	foreach ( (array) $multisite_terms as $multisite_term ) {
		$use_id = false;
		if ( ! is_object( $multisite_term ) ) {
			$multisite_term = get_multisite_term( $multisite_term, $multisite_taxonomy );
			if ( is_wp_error( $multisite_term ) ) {
				return $multisite_term;
			}
			$use_id = true;
		}

		// Don't recurse if we've already identified the multisite term as a child - this indicates a loop.
		if ( isset( $ancestors[ $multisite_term->multisite_term_id ] ) ) {
			continue;
		}

		if ( $multisite_term->parent === $multisite_term_id ) {
			if ( $use_id ) {
				$multisite_term_list[] = $multisite_term->multisite_term_id;
			} else {
				$multisite_term_list[] = $multisite_term;
			}
			if ( ! isset( $has_children[ $multisite_term->multisite_term_id ] ) ) {
				continue;
			}
			$ancestors[ $multisite_term->multisite_term_id ] = 1;

			$children = _get_multisite_term_children( $multisite_term->multisite_term_id, $multisite_terms, $multisite_taxonomy, $ancestors );
			if ( $children ) {
				$multisite_term_list = array_merge( $multisite_term_list, $children );
			}
		}
	}

	return $multisite_term_list;
}

/**
 * Add count of children to parent count.
 *
 * Recalculates multisite term counts by including items from child multisite terms. Assumes all
 * relevant children are already in the $multisite_terms argument.
 *
 * @global wpdb $wpdb WordPress database abstraction object.
 *
 * @param array  $multisite_terms    List of multisite term objects, passed by reference.
 * @param string $multisite_taxonomy Multisite term context.
 */
function _pad_multisite_term_counts( &$multisite_terms, $multisite_taxonomy ) {
	global $wpdb;

	// This function only works for hierarchical multisite taxonomies.
	if ( ! is_multisite_taxonomy_hierarchical( $multisite_taxonomy ) ) {
		return;
	}
	$multisite_term_hier = _get_multisite_term_hierarchy( $multisite_taxonomy );

	if ( empty( $multisite_term_hier ) ) {
		return;
	}

	$multisite_term_items  = array();
	$multisite_terms_by_id = array();
	$multisite_term_ids    = array();

	foreach ( (array) $multisite_terms as $key => $multisite_term ) {
		$multisite_terms_by_id[ $multisite_term->multisite_term_id ]                 = & $multisite_terms[ $key ];
		$multisite_term_ids[ $multisite_term->multisite_term_multisite_taxonomy_id ] = $multisite_term->multisite_term_id;
	}

	// Get the object and multisite term ids and stick them in a lookup table.
	$multi_tax_obj = get_multisite_taxonomy( $multisite_taxonomy );
	$object_types  = esc_sql( $multi_tax_obj->object_type );
	$results       = $wpdb->get_results( "SELECT object_id, multisite_term_multisite_taxonomy_id FROM $wpdb->multisite_term_relationships INNER JOIN $wpdb->posts ON object_id = ID WHERE multisite_term_multisite_taxonomy_id IN (" . implode( ',', array_keys( $multisite_term_ids ) ) . ") AND post_type IN ('" . implode( "', '", $object_types ) . "') AND post_status = 'publish'" ); // WPCS: unprepared SQL ok.
	foreach ( $results as $row ) {
		$id = $multisite_term_ids[ $row->multisite_term_multisite_taxonomy_id ];
		$multisite_term_items[ $id ][ $row->object_id ] = isset( $multisite_term_items[ $id ][ $row->object_id ] ) ? ++$multisite_term_items[ $id ][ $row->object_id ] : 1;
	}

	// Touch every ancestor's lookup row for each post in each term.
	foreach ( $multisite_term_ids as $multisite_term_id ) {
		$child     = $multisite_term_id;
		$ancestors = array();
		while ( ! empty( $multisite_terms_by_id[ $child ] ) && $multisite_terms_by_id[ $child ]->parent ) {
			$parent      = $multisite_terms_by_id[ $child ]->parent;
			$ancestors[] = $child;
			if ( ! empty( $multisite_term_items[ $multisite_term_id ] ) ) {
				foreach ( $multisite_term_items[ $multisite_term_id ] as $item_id => $touches ) {
					$multisite_term_items[ $parent ][ $item_id ] = isset( $multisite_term_items[ $parent ][ $item_id ] ) ? ++$multisite_term_items[ $parent ][ $item_id ] : 1;
				}
			}
			$child = $parent;

			if ( in_array( $parent, $ancestors, true ) ) {
				break;
			}
		}
	}

	// Transfer the touched cells.
	foreach ( (array) $multisite_term_items as $id => $items ) {
		if ( isset( $multisite_terms_by_id[ $id ] ) ) {
			$multisite_terms_by_id[ $id ]->count = count( $items );
		}
	}
}

/**
 * Adds any multisite terms from the given IDs to the cache that do not already exist in cache.
 *
 * @global wpdb $wpdb WordPress database abstraction object.
 *
 * @param array $multisite_term_ids          Array of multisite term IDs.
 * @param bool  $update_meta_cache Optional. Whether to update the meta cache. Default true.
 */
function _prime_multisite_term_caches( $multisite_term_ids, $update_meta_cache = true ) {
	global $wpdb;

	$non_cached_ids = _get_non_cached_ids( $multisite_term_ids, 'multisite_terms' );
	if ( ! empty( $non_cached_ids ) ) {
		$fresh_multisite_terms = $wpdb->get_results( sprintf( "SELECT t.*, tt.* FROM $wpdb->multisite_terms AS t INNER JOIN $wpdb->multisite_term_multisite_taxonomy AS tt ON t.multisite_term_id = tt.multisite_term_id WHERE t.multisite_term_id IN (%s)", join( ',', array_map( 'intval', $non_cached_ids ) ) ) ); // WPCS: unprepared SQL ok.

		update_multisite_term_cache( $fresh_multisite_terms, $update_meta_cache );

		if ( $update_meta_cache ) {
			update_multisite_termmeta_cache( $non_cached_ids );
		}
	}
}

/*
 * Default callbacks.
 */

/**
 * Will update multisite term count based on object types of the current multisite taxonomy.
 *
 * @global wpdb $wpdb WordPress database abstraction object.
 *
 * @param array  $multisite_terms    List of multisite term multisite taxonomy IDs.
 * @param object $multisite_taxonomy Current multisite taxonomy object of multisite terms.
 */
function _update_post_multisite_term_count( $multisite_terms, $multisite_taxonomy ) {
	global $wpdb;

	$object_types = (array) $multisite_taxonomy->object_type;

	foreach ( $object_types as &$object_type ) {
		list( $object_type ) = explode( ':', $object_type );
	}
	$object_types = array_unique( $object_types );

	$check_attachments = array_search( 'attachment', $object_types, true );
	if ( false !== $check_attachments ) {
		unset( $object_types[ $check_attachments ] );
		$check_attachments = true;
	}

	if ( $object_types ) {
		$object_types = esc_sql( array_filter( $object_types, 'post_type_exists' ) );
	}

	foreach ( (array) $multisite_terms as $multisite_term ) {
		$count = 0;

		// Attachments can be 'inherit' status, we need to base count off the parent's status if so.
		if ( $check_attachments ) {
			$count += (int) $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM $wpdb->multisite_term_relationships, $wpdb->posts p1 WHERE p1.ID = $wpdb->multisite_term_relationships.object_id AND ( post_status = 'publish' OR ( post_status = 'inherit' AND post_parent > 0 AND ( SELECT post_status FROM $wpdb->posts WHERE ID = p1.post_parent ) = 'publish' ) ) AND post_type = 'attachment' AND multisite_term_multisite_taxonomy_id = %d", $multisite_term ) );
		}
		if ( $object_types ) {
			// @codingStandardsIgnoreLine
			$count += (int) $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM $wpdb->multisite_term_relationships, $wpdb->posts WHERE $wpdb->posts.ID = $wpdb->multisite_term_relationships.object_id AND post_status = 'publish' AND post_type IN ('" . implode( "', '", $object_types ) . "') AND multisite_term_multisite_taxonomy_id = %d", $multisite_term ) ); // WPCS: unprepared SQL ok.
		}

		do_action( 'edit_multisite_term_multisite_taxonomy', $multisite_term, $multisite_taxonomy->name );
		$wpdb->update(
			$wpdb->multisite_term_multisite_taxonomy, compact( 'count' ), array(
				'multisite_term_multisite_taxonomy_id' => $multisite_term,
			)
		);

		do_action( 'edited_multisite_term_multisite_taxonomy', $multisite_term, $multisite_taxonomy->name );
	}
}

/**
 * Will update multisite term count based on number of objects.
 *
 * @global wpdb $wpdb WordPress database abstraction object.
 *
 * @param array  $multisite_terms    List of multisite term multisite taxonomy IDs.
 * @param object $multisite_taxonomy Current multisite taxonomy object of multisite terms.
 */
function _update_generic_multisite_term_count( $multisite_terms, $multisite_taxonomy ) {
	global $wpdb;

	foreach ( (array) $multisite_terms as $multisite_term ) {
		$count = $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM $wpdb->multisite_term_relationships WHERE multisite_term_multisite_taxonomy_id = %d", $multisite_term ) );

		do_action( 'edit_multisite_term_multisite_taxonomy', $multisite_term, $multisite_taxonomy->name );
		$wpdb->update(
			$wpdb->multisite_term_multisite_taxonomy, compact( 'count' ), array(
				'multisite_term_multisite_taxonomy_id' => $multisite_term,
			)
		);

		do_action( 'edited_multisite_term_multisite_taxonomy', $multisite_term, $multisite_taxonomy->name );
	}
}

/**
 * Generate a permalink for a multisite taxonomy multisite term archive.
 *
 * @global WP_Rewrite $wp_rewrite
 *
 * @param object|int|string $multisite_term     The multisite term object, ID, or slug whose link will be retrieved.
 * @param string            $multisite_taxonomy Optional. Multisite taxonomy. Default empty.
 * @return string|WP_Error HTML link to multisite taxonomy multisite term archive on success, WP_Error if multisite term does not exist.
 */
function get_multisite_term_link( $multisite_term, $multisite_taxonomy = '' ) {
	global $wp_rewrite;

	if ( ! is_object( $multisite_term ) ) {
		if ( is_int( $multisite_term ) ) {
			$multisite_term = get_multisite_term( $multisite_term, $multisite_taxonomy );
		} else {
			$multisite_term = get_multisite_term_by( 'slug', $multisite_term, $multisite_taxonomy );
		}
	}

	if ( ! is_object( $multisite_term ) ) {
		$multisite_term = new WP_Error( 'invalid_multisite_term', __( 'Empty Multisite Term', 'multitaxo' ) );
	}
	if ( is_wp_error( $multisite_term ) ) {
		return $multisite_term;
	}
	$multisite_taxonomy = $multisite_term->multisite_taxonomy;

	$multisite_termlink = $wp_rewrite->get_extra_permastruct( $multisite_taxonomy );

	$slug = $multisite_term->slug;
	$mt   = get_multisite_taxonomy( $multisite_taxonomy );

	if ( empty( $multisite_termlink ) ) {
		if ( 'category' === $multisite_taxonomy ) {
			$multisite_termlink = '?cat=' . $multisite_term->multisite_term_id;
		} elseif ( $mt->query_var ) {
			$multisite_termlink = "?$mt->query_var=$slug";
		} else {
			$multisite_termlink = "?multisite_taxonomy=$multisite_taxonomy&multisite_term=$slug";
		}
		$multisite_termlink = home_url( $multisite_termlink );
	} else {
		if ( $mt->rewrite['hierarchical'] ) {
			$hierarchical_slugs = array();
			$ancestors          = get_multisite_ancestors( $multisite_term->multisite_term_id, $multisite_taxonomy, 'multisite_taxonomy' );
			foreach ( (array) $ancestors as $ancestor ) {
				$ancestor_multisite_term = get_multisite_term( $ancestor, $multisite_taxonomy );
				$hierarchical_slugs[]    = $ancestor_multisite_term->slug;
			}
			$hierarchical_slugs   = array_reverse( $hierarchical_slugs );
			$hierarchical_slugs[] = $slug;
			$multisite_termlink   = str_replace( "%$multisite_taxonomy%", implode( '/', $hierarchical_slugs ), $multisite_termlink );
		} else {
			$multisite_termlink = str_replace( "%$multisite_taxonomy%", $slug, $multisite_termlink );
		}
		$multisite_termlink = home_url( user_trailingslashit( $multisite_termlink, 'category' ) );
	}

	/**
	 * Filters the multisite term link.
	 *
	 * @param string $multisite_termlink Multisite term link URL.
	 * @param object $multisite_term     Multisite term object.
	 * @param string $multisite_taxonomy Multisite taxonomy slug.
	 */
	return apply_filters( 'multisite_term_link', $multisite_termlink, $multisite_term, $multisite_taxonomy );
}


/**
 * Displays or retrieves the edit term link with formatting.
 *
 * @since 3.1.0
 *
 * @param integer $term_id Term ID for display.
 * @param string  $taxonomy Term taxonomy for display.
 * @return string|void HTML content.
 */
function get_edit_multisite_term_link( $term_id, $taxonomy ) {
	$tax = get_multisite_taxonomy( $taxonomy );
	if ( ! $tax || ! current_user_can( 'edit_multisite_term', $term_id ) ) {
		return;
	}

	$term = get_multisite_term( $term_id, $taxonomy );
	if ( ! $term || is_wp_error( $term ) ) {
		return;
	}

	$args = array(
		'page'               => 'multisite_term_edit',
		'multisite_taxonomy' => $taxonomy,
		'multisite_term_id'  => $term_id,
	);

	if ( $tax->show_ui ) {
		$location = add_query_arg( $args, get_admin_url( null, 'network/admin.php' ) );
	} else {
		$location = '';
	}

	/**
	 * Filters the edit link for a term.
	 *
	 * @since 3.1.0
	 *
	 * @param string $location    The edit link.
	 * @param int    $term_id     Term ID.
	 * @param string $taxonomy    Taxonomy name.
	 * @param string $object_type The object type (eg. the post type).
	 */
	return apply_filters( 'get_edit_multisite_term_link', $location, $term_id, $taxonomy );
}

/**
 * Display the multisite taxonomies of a post with available options.
 *
 * This function can be used within the loop to display the multisite taxonomies for a
 * post without specifying the Post ID. You can also use it outside the Loop to
 * display the multisite taxonomies for a specific post.
 *
 * @param array $args {
 *     Arguments about which post to use and how to format the output. Shares all of the arguments
 *     supported by get_the_multisite_taxonomies(), in addition to the following.
 *
 *     @type  int|WP_Post $post   Post ID or object to get multisite taxonomies of. Default current post.
 *     @type  string      $before Displays before the multisite taxonomies. Default empty string.
 *     @type  string      $sep    Separates each multisite taxonomy. Default is a space.
 *     @type  string      $after  Displays after the multisite taxonomies. Default empty string.
 * }
 */
function the_multisite_taxonomies( $args = array() ) {
	$defaults = array(
		'post'   => 0,
		'before' => '',
		'sep'    => ' ',
		'after'  => '',
	);

	$r = wp_parse_args( $args, $defaults );

	echo $r['before'] . join( $r['sep'], get_the_multisite_taxonomies( $r['post'], $r ) ) . $r['after']; // WPCS: XSS ok.
}

/**
 * Retrieve all multisite taxonomies associated with a post.
 *
 * This function can be used within the loop. It will also return an array of
 * the multisite taxonomies with links to the multisite taxonomy and name.
 *
 * @param int|WP_Post $post Optional. Post ID or WP_Post object. Default is global $post.
 * @param array       $args {
 *     Optional. Arguments about how to format the list of multisite taxonomies. Default empty array.
 *
 *     @type string $template      Template for displaying a multisite taxonomy label and list of multisite terms.
 *                                 Default is "Label: Multisite Terms."
 *     @type string $multisite_term_template Template for displaying a single multisite term in the list. Default is the multisite term name
 *                                 linked to its archive.
 * }
 * @return array List of multisite taxonomies.
 */
function get_the_multisite_taxonomies( $post = 0, $args = array() ) {
	$post = get_post( $post );

	$args = wp_parse_args(
		$args, array(
			/* translators: %s: multisite taxonomy label, %l: list of multisite terms formatted as per $multisite_term_template */
			'template'                => __( '%s: %l.', 'multitaxo' ),
			'multisite_term_template' => '<a href="%1$s">%2$s</a>',
		)
	);

	$multisite_taxonomies = array();

	if ( ! $post ) {
		return $multisite_taxonomies;
	}

	foreach ( get_object_multisite_taxonomies( $post ) as $multisite_taxonomy ) {
		$t = (array) get_multisite_taxonomy( $multisite_taxonomy );
		if ( empty( $t['label'] ) ) {
			$t['label'] = $multisite_taxonomy;
		}
		if ( empty( $t['args'] ) ) {
			$t['args'] = array();
		}
		if ( empty( $t['template'] ) ) {
			$t['template'] = $args['template'];
		}
		if ( empty( $t['multisite_term_template'] ) ) {
			$t['multisite_term_template'] = $args['multisite_term_template'];
		}

		$multisite_terms = get_object_multisite_term_cache( $post->ID, $multisite_taxonomy );
		if ( false === $multisite_terms ) {
			$multisite_terms = get_object_multisite_terms( $post->ID, $multisite_taxonomy, $t['args'] );
		}
		$links = array();

		foreach ( $multisite_terms as $multisite_term ) {
			$links[] = wp_sprintf( $t['multisite_term_template'], esc_attr( get_multisite_term_link( $multisite_term ) ), $multisite_term->name );
		}
		if ( $links ) {
			$multisite_taxonomies[ $multisite_taxonomy ] = wp_sprintf( $t['template'], $t['label'], $links, $multisite_terms );
		}
	}
	return $multisite_taxonomies;
}

/**
 * Retrieve all multisite taxonomies of a post with just the names.
 *
 * @param int|WP_Post $post Optional. Post ID or WP_Post object. Default is global $post.
 * @return array
 */
function get_post_multisite_taxonomies( $post = 0 ) {
	$post = get_post( $post );

	return get_object_multisite_taxonomies( $post );
}

/**
 * Determine if the given object is associated with any of the given multisite terms.
 *
 * The given multisite terms are checked against the object's multisite_term_ids, names and slugs.
 * Multisite terms given as integers will only be checked against the object's multisite_term_ids.
 * If no multisite terms are given, determines if object is associated with any multisite terms in the given multisite taxonomy.
 *
 * @param int              $object_id ID of the object (post ID, link ID, ...).
 * @param string           $multisite_taxonomy  Single multisite taxonomy name.
 * @param int|string|array $multisite_terms     Optional. Multisite term multisite_term_id, name, slug or array of said. Default null.
 * @return bool|WP_Error WP_Error on input error.
 */
function is_object_in_multsite_term( $object_id, $multisite_taxonomy, $multisite_terms = null ) {
	$object_id = (int) $object_id;
	if ( ! $object_id ) {
		return new WP_Error( 'invalid_object', __( 'Invalid object ID', 'multitaxo' ) );
	}
	$object_multisite_terms = get_object_multisite_term_cache( $object_id, $multisite_taxonomy );
	if ( false === $object_multisite_terms ) {
		$object_multisite_terms = get_object_multisite_terms(
			$object_id, $multisite_taxonomy, array(
				'update_multisite_term_meta_cache' => false,
			)
		);
		if ( is_wp_error( $object_multisite_terms ) ) {
			return $object_multisite_terms;
		}

		wp_cache_set( $object_id, wp_list_pluck( $object_multisite_terms, 'multisite_term_id' ), "{$multisite_taxonomy}_multisite_relationships" );
	}

	if ( is_wp_error( $object_multisite_terms ) ) {
		return $object_multisite_terms;
	}
	if ( empty( $object_multisite_terms ) ) {
		return false;
	}
	if ( empty( $multisite_terms ) ) {
		return ( ! empty( $object_multisite_terms ) );
	}

	$multisite_terms = (array) $multisite_terms;

	$ints = array_filter( $multisite_terms, 'is_int' );
	if ( $ints ) {
		$strs = array_diff( $multisite_terms, $ints );
	} else {
		$strs =& $multisite_terms;
	}

	foreach ( $object_multisite_terms as $object_multisite_term ) {
		// If multisite term is an int, check against multisite_term_ids only.
		if ( $ints && in_array( $object_multisite_term->multisite_term_id, $ints, true ) ) {
			return true;
		}

		if ( $strs ) {
			// Only check numeric strings against multisite_term_id, to avoid false matches due to type juggling.
			$numeric_strs = array_map( 'intval', array_filter( $strs, 'is_numeric' ) );
			if ( in_array( $object_multisite_term->multisite_term_id, $numeric_strs, true ) ) {
				return true;
			}

			if ( in_array( $object_multisite_term->name, $strs, true ) ) {
				return true;
			}
			if ( in_array( $object_multisite_term->slug, $strs, true ) ) {
				return true;
			}
		}
	}

	return false;
}

/**
 * Determine if the given object type is associated with the given multisite taxonomy.
 *
 * @param string $object_type Object type string.
 * @param string $multisite_taxonomy Single multisite taxonomy name.
 * @return bool True if object is associated with the multisite taxonomy, otherwise false.
 */
function is_object_in_multisite_taxonomy( $object_type, $multisite_taxonomy ) {
	$multisite_taxonomies = get_object_multisite_taxonomies( $object_type );
	if ( empty( $multisite_taxonomies ) ) {
		return false;
	}
	return in_array( $multisite_taxonomy, $multisite_taxonomies, true );
}

/**
 * Get an array of ancestor IDs for a given object.
 *
 * @param int    $object_id     Optional. The ID of the object. Default 0.
 * @param string $object_type   Optional. The type of object for which we'll be retrieving
 *                              ancestors. Accepts a post type or a multisite taxonomy name. Default empty.
 * @param string $resource_type Optional. Type of resource $object_type is. Accepts 'post_type'
 *                              or 'multisite_taxonomy'. Default empty.
 * @return array An array of ancestors from lowest to highest in the hierarchy.
 */
function get_multisite_ancestors( $object_id = 0, $object_type = '', $resource_type = '' ) {
	$object_id = (int) $object_id;

	$ancestors = array();

	if ( empty( $object_id ) ) {
		return apply_filters( 'get_multisite_ancestors', $ancestors, $object_id, $object_type, $resource_type );
	}

	if ( ! $resource_type ) {
		if ( is_multisite_taxonomy_hierarchical( $object_type ) ) {
			$resource_type = 'multisite_taxonomy';
		} elseif ( post_type_exists( $object_type ) ) {
			$resource_type = 'post_type';
		}
	}

	if ( 'multisite_taxonomy' === $resource_type ) {
		$multisite_term = get_multisite_term( $object_id, $object_type );
		while ( ! is_wp_error( $multisite_term ) && ! empty( $multisite_term->parent ) && ! in_array( $multisite_term->parent, $ancestors, true ) ) {
			$ancestors[]    = (int) $multisite_term->parent;
			$multisite_term = get_multisite_term( $multisite_term->parent, $object_type );
		}
	} elseif ( 'post_type' === $resource_type ) {
		$ancestors = get_post_ancestors( $object_id );
	}

	/**
	 * Filters a given object's ancestors.
	 *
	 * @param array  $ancestors     An array of object ancestors.
	 * @param int    $object_id     Object ID.
	 * @param string $object_type   Type of object.
	 * @param string $resource_type Type of resource $object_type is.
	 */
	return apply_filters( 'get_multisite_ancestors', $ancestors, $object_id, $object_type, $resource_type );
}

/**
 * Returns the multisite term's parent's multisite_term_id.
 *
 * @param int    $multisite_term_id  Multisite term ID.
 * @param string $multisite_taxonomy Multisite taxonomy name.
 * @return int|false False on error.
 */
function get_multisite_term_multisite_taxonomy_parent_id( $multisite_term_id, $multisite_taxonomy ) {
	$multisite_term = get_multisite_term( $multisite_term_id, $multisite_taxonomy );
	if ( ! $multisite_term || is_wp_error( $multisite_term ) ) {
		return false;
	}
	return (int) $multisite_term->parent;
}

/**
 * Checks the given subset of the multisite term hierarchy for hierarchy loops.
 * Prevents loops from forming and breaks those that it finds.
 *
 * @param int    $parent   `multisite_term_id` of the parent for the multisite term we're checking.
 * @param int    $multisite_term_id  The multisite term we're checking.
 * @param string $multisite_taxonomy The multisite taxonomy of the multisite term we're checking.
 *
 * @return int The new parent for the multisite term.
 */
function check_multisite_term_hierarchy_for_loops( $parent, $multisite_term_id, $multisite_taxonomy ) {
	// Nothing fancy here - bail.
	if ( ! $parent ) {
		return 0;
	}

	// Can't be its own parent.
	if ( (int) $parent === (int) $multisite_term_id ) {
		return 0;
	}
	// Now look for larger loops.
	$loop = wp_find_hierarchy_loop( 'get_multisite_term_multisite_taxonomy_parent_id', $multisite_term_id, $parent, array( $multisite_taxonomy ) );
	if ( ! $loop ) {
		return $parent; // No loop.
	}
	// Setting $parent to the given value causes a loop.
	if ( isset( $loop[ $multisite_term_id ] ) ) {
		return 0;
	}
	// There's a loop, but it doesn't contain $multisite_term_id. Break the loop.
	foreach ( array_keys( $loop ) as $loop_member ) {
		update_multisite_term(
			$loop_member, $multisite_taxonomy, array(
				'parent' => 0,
			)
		);
	}
	return $parent;
}

/**
 * Get comma-separated list of multisite terms available to edit for the given post ID.
 *
 * @param int    $post_id The post ID.
 * @param string $multisite_taxonomy Optional. The taxonomy for which to retrieve terms. Default 'post_tag'.
 * @return string|bool|WP_Error
 */
function get_multisite_terms_to_edit( $post_id, $multisite_taxonomy ) {
	$post_id = (int) $post_id;
	if ( ! $post_id ) {
		return false;
	}

	$multisite_terms = get_object_multisite_term_cache( $post_id, $multisite_taxonomy );
	if ( false === $multisite_terms ) {
		$multisite_terms = get_object_multisite_terms( $post_id, $multisite_taxonomy );
		wp_cache_add( $post_id, wp_list_pluck( $multisite_terms, 'multisite_term_id' ), $multisite_taxonomy . '_relationships' );
	}

	if ( ! $multisite_terms ) {
		return false;
	}
	if ( is_wp_error( $multisite_terms ) ) {
		return $multisite_terms;
	}
	$multisite_term_names = array();
	foreach ( $multisite_terms as $multisite_term ) {
		$multisite_term_names[] = $multisite_term->name;
	}

	$multisite_terms_to_edit = esc_attr( join( ',', $multisite_term_names ) );

	/**
	 * Filters the comma-separated list of multisite terms available to edit.
	 *
	 * @see get_multisite_terms_to_edit()
	 *
	 * @param array  $multisite_terms_to_edit An array of multisite terms.
	 * @param string $multisite_taxonomy     The multisite taxonomy for which to retrieve multisite terms.
	 */
	$multisite_terms_to_edit = apply_filters( 'multisite_terms_to_edit', $multisite_terms_to_edit, $multisite_taxonomy );

	return $multisite_terms_to_edit;
}

/**
 * Add a new multsite term to the database if it does not already exist.
 *
 * @param int|string $multisite_term_name Multisite term name.
 * @param string     $multisite_taxonomy Optional. The multisite taxonomy for which to retrieve multisite terms.
 * @return array|WP_Error
 */
function create_multisite_term( $multisite_term_name, $multisite_taxonomy ) {
	$id = multisite_term_exists( $multisite_term_name, $multisite_taxonomy );
	if ( is_numeric( $id ) ) {
		return $id;
	}
	return insert_multisite_term( $multisite_term_name, $multisite_taxonomy );
}

/**
 * Output an unordered list of checkbox input elements labeled with category names.
 *
 * @since 2.5.1
 *
 * @see wp_terms_checklist()
 *
 * @param int    $post_id              Optional. Post to generate a categories checklist for. Default 0.
 *                                     $selected_cats must not be an array. Default 0.
 * @param int    $descendants_and_self Optional. ID of the category to output along with its descendants.
 *                                     Default 0.
 * @param array  $selected_cats        Optional. List of categories to mark as checked. Default false.
 * @param array  $popular_cats         Optional. List of categories to receive the "popular-category" class.
 *                                     Default false.
 * @param object $walker               Optional. Walker object to use to build the output.
 *                                     Default is a Walker_Category_Checklist instance.
 * @param bool   $checked_ontop        Optional. Whether to move checked items out of the hierarchy and to
 *                                     the top of the list. Default true.
 */
function multisite_category_checklist( $post_id = 0, $descendants_and_self = 0, $selected_cats = false, $popular_cats = false, $walker = null, $checked_ontop = true ) {
	wp_terms_checklist(
		$post_id, array(
			'taxonomy'             => 'category',
			'descendants_and_self' => $descendants_and_self,
			'selected_cats'        => $selected_cats,
			'popular_cats'         => $popular_cats,
			'walker'               => $walker,
			'checked_ontop'        => $checked_ontop,
		)
	);
}

/**
 * Output an unordered list of checkbox input elements labelled with term names.
 *
 * Taxonomy-independent version of wp_category_checklist().
 *
 * @since 3.0.0
 * @since 4.4.0 Introduced the `$echo` argument.
 *
 * @param int          $post_id Optional. Post ID. Default 0.
 * @param array|string $args {
 *     Optional. Array or string of arguments for generating a terms checklist. Default empty array.
 *
 *     @type int    $descendants_and_self ID of the category to output along with its descendants.
 *                                        Default 0.
 *     @type array  $selected_cats        List of categories to mark as checked. Default false.
 *     @type array  $popular_cats         List of categories to receive the "popular-category" class.
 *                                        Default false.
 *     @type object $walker               Walker object to use to build the output.
 *                                        Default is a Walker_Category_Checklist instance.
 *     @type string $taxonomy             Taxonomy to generate the checklist for. Default 'category'.
 *     @type bool   $checked_ontop        Whether to move checked items out of the hierarchy and to
 *                                        the top of the list. Default true.
 *     @type bool   $echo                 Whether to echo the generated markup. False to return the markup instead
 *                                        of echoing it. Default true.
 * }
 */
function multisite_terms_checklist( $post_id = 0, $args = array() ) {
	 $defaults = array(
		 'descendants_and_self' => 0,
		 'selected_cats'        => false,
		 'popular_cats'         => false,
		 'walker'               => null,
		 'taxonomy'             => 'category',
		 'checked_ontop'        => true,
		 'echo'                 => true,
	 );

	/**
	 * Filters the taxonomy terms checklist arguments.
	 *
	 * @since 3.4.0
	 *
	 * @see wp_terms_checklist()
	 *
	 * @param array $args    An array of arguments.
	 * @param int   $post_id The post ID.
	 */
	$params = apply_filters( 'wp_terms_checklist_args', $args, $post_id );

	$r = wp_parse_args( $params, $defaults );

	if ( empty( $r['walker'] ) || ! ( $r['walker'] instanceof Walker ) ) {
		$walker = new Walker_Multisite_Category_Checklist();
	} else {
		$walker = $r['walker'];
	}

	$taxonomy             = $r['taxonomy'];
	$descendants_and_self = (int) $r['descendants_and_self'];

	$args = array( 'taxonomy' => $taxonomy );

	$tax              = get_taxonomy( $taxonomy );
	$args['disabled'] = ! current_user_can( $tax->cap->assign_terms );

	$args['list_only'] = ! empty( $r['list_only'] );

	if ( is_array( $r['selected_cats'] ) ) {
		$args['selected_cats'] = $r['selected_cats'];
	} elseif ( $post_id ) {
		$args['selected_cats'] = wp_get_object_terms( $post_id, $taxonomy, array_merge( $args, array( 'fields' => 'ids' ) ) );
	} else {
		$args['selected_cats'] = array();
	}
	if ( is_array( $r['popular_cats'] ) ) {
		$args['popular_cats'] = $r['popular_cats'];
	} else {
		$args['popular_cats'] = get_terms(
			$taxonomy, array(
				'fields'       => 'ids',
				'orderby'      => 'count',
				'order'        => 'DESC',
				'number'       => 10,
				'hierarchical' => false,
			)
		);
	}
	if ( $descendants_and_self ) {
		$categories = (array) get_terms(
			$taxonomy, array(
				'child_of'     => $descendants_and_self,
				'hierarchical' => 0,
				'hide_empty'   => 0,
			)
		);
		$self       = get_term( $descendants_and_self, $taxonomy );
		array_unshift( $categories, $self );
	} else {
		$categories = (array) get_terms( $taxonomy, array( 'get' => 'all' ) );
	}

	$output = '';

	if ( $r['checked_ontop'] ) {
		// Post process $categories rather than adding an exclude to the get_terms() query to keep the query the same across all posts (for any query cache)
		$checked_categories = array();
		$keys               = array_keys( $categories );

		foreach ( $keys as $k ) {
			if ( in_array( $categories[ $k ]->term_id, $args['selected_cats'] ) ) {
				$checked_categories[] = $categories[ $k ];
				unset( $categories[ $k ] );
			}
		}

		// Put checked cats on top
		$output .= call_user_func_array( array( $walker, 'walk' ), array( $checked_categories, 0, $args ) );
	}
	// Then the rest of them
	$output .= call_user_func_array( array( $walker, 'walk' ), array( $categories, 0, $args ) );

	if ( $r['echo'] ) {
		echo $output;
	}

	return $output;
}

/**
 * Retrieve a list of the most popular terms from the specified taxonomy.
 *
 * If the $echo argument is true then the elements for a list of checkbox
 * `<input>` elements labelled with the names of the selected terms is output.
 * If the $post_ID global isn't empty then the terms associated with that
 * post will be marked as checked.
 *
 * @since 2.5.0
 *
 * @param string $taxonomy Taxonomy to retrieve terms from.
 * @param int    $default Not used.
 * @param int    $number Number of terms to retrieve. Defaults to 10.
 * @param bool   $echo Optionally output the list as well. Defaults to true.
 * @return array List of popular term IDs.
 */
function popular_multisite_terms_checklist( $taxonomy, $default = 0, $number = 10, $echo = true ) {
	$post = get_post();

	if ( $post && $post->ID ) {
		$checked_terms = get_object_multisite_terms( $post->ID, $taxonomy, array( 'fields' => 'ids' ) );
	} else {
		$checked_terms = array();
	}

	$terms = get_multisite_terms(
		$taxonomy, array(
			'orderby'      => 'count',
			'order'        => 'DESC',
			'number'       => $number,
			'hierarchical' => false,
		)
	);

	$tax = get_multisite_taxonomy( $taxonomy );

	$popular_ids = array();
	foreach ( (array) $terms as $term ) {
		$popular_ids[] = $term->term_id;
		if ( ! $echo ) { // Hack for Ajax use.
			continue;
		}
		$id      = "popular-$taxonomy-$term->term_id";
		$checked = in_array( $term->term_id, $checked_terms ) ? 'checked="checked"' : '';
		?>

		<li id="<?php echo $id; ?>" class="popular-category">
			<label class="selectit">
				<input id="in-<?php echo $id; ?>" type="checkbox" <?php echo $checked; ?> value="<?php echo (int) $term->term_id; ?>" <?php disabled( ! current_user_can( $tax->cap->assign_terms ) ); ?> />
				<?php
				/** This filter is documented in wp-includes/category-template.php */
				echo esc_html( apply_filters( 'the_category', $term->name ) );
				?>
			</label>
		</li>

		<?php
	}
	return $popular_ids;
}

/**
 * Outputs a link category checklist element.
 *
 * @since 2.5.1
 *
 * @param int $link_id
 */
function link_multisite_category_checklist( $link_id = 0 ) {
	$default = 1;

	$checked_categories = array();

	if ( $link_id ) {
		$checked_categories = wp_get_link_cats( $link_id );
		// No selected categories, strange
		if ( ! count( $checked_categories ) ) {
			$checked_categories[] = $default;
		}
	} else {
		$checked_categories[] = $default;
	}

	$categories = get_terms(
		'link_category', array(
			'orderby'    => 'name',
			'hide_empty' => 0,
		)
	);

	if ( empty( $categories ) ) {
		return;
	}

	foreach ( $categories as $category ) {
		$cat_id = $category->term_id;

		/** This filter is documented in wp-includes/category-template.php */
		$name    = esc_html( apply_filters( 'the_category', $category->name ) );
		$checked = in_array( $cat_id, $checked_categories ) ? ' checked="checked"' : '';
		echo '<li id="link-category-', $cat_id, '"><label for="in-link-category-', $cat_id, '" class="selectit"><input value="', $cat_id, '" type="checkbox" name="link_category[]" id="in-link-category-', $cat_id, '"', $checked, '/> ', $name, '</label></li>';
	}
}

/**
 * Adds hidden fields with the data for use in the inline editor for posts and pages.
 *
 * @since 2.7.0
 *
 * @param WP_Post $post Post object.
 */
function get_multisite_inline_data( $post ) {
	$post_type_object = get_post_type_object( $post->post_type );
	if ( ! current_user_can( 'edit_post', $post->ID ) ) {
		return;
	}

	$title = esc_textarea( trim( $post->post_title ) );

	/** This filter is documented in wp-admin/edit-tag-form.php */
	echo '
<div class="hidden" id="inline_' . $post->ID . '">
	<div class="post_title">' . $title . '</div>' .
	/** This filter is documented in wp-admin/edit-tag-form.php */
	'<div class="post_name">' . apply_filters( 'editable_slug', $post->post_name, $post ) . '</div>
	<div class="post_author">' . $post->post_author . '</div>
	<div class="comment_status">' . esc_html( $post->comment_status ) . '</div>
	<div class="ping_status">' . esc_html( $post->ping_status ) . '</div>
	<div class="_status">' . esc_html( $post->post_status ) . '</div>
	<div class="jj">' . mysql2date( 'd', $post->post_date, false ) . '</div>
	<div class="mm">' . mysql2date( 'm', $post->post_date, false ) . '</div>
	<div class="aa">' . mysql2date( 'Y', $post->post_date, false ) . '</div>
	<div class="hh">' . mysql2date( 'H', $post->post_date, false ) . '</div>
	<div class="mn">' . mysql2date( 'i', $post->post_date, false ) . '</div>
	<div class="ss">' . mysql2date( 's', $post->post_date, false ) . '</div>
	<div class="post_password">' . esc_html( $post->post_password ) . '</div>';

	if ( $post_type_object->hierarchical ) {
		echo '<div class="post_parent">' . $post->post_parent . '</div>';
	}

	echo '<div class="page_template">' . ( $post->page_template ? esc_html( $post->page_template ) : 'default' ) . '</div>';

	if ( post_type_supports( $post->post_type, 'page-attributes' ) ) {
		echo '<div class="menu_order">' . $post->menu_order . '</div>';
	}

	$taxonomy_names = get_object_taxonomies( $post->post_type );
	foreach ( $taxonomy_names as $taxonomy_name ) {
		$taxonomy = get_taxonomy( $taxonomy_name );

		if ( $taxonomy->hierarchical && $taxonomy->show_ui ) {

			$terms = get_object_term_cache( $post->ID, $taxonomy_name );
			if ( false === $terms ) {
				$terms = wp_get_object_terms( $post->ID, $taxonomy_name );
				wp_cache_add( $post->ID, wp_list_pluck( $terms, 'term_id' ), $taxonomy_name . '_relationships' );
			}
			$term_ids = empty( $terms ) ? array() : wp_list_pluck( $terms, 'term_id' );

			echo '<div class="post_category" id="' . $taxonomy_name . '_' . $post->ID . '">' . implode( ',', $term_ids ) . '</div>';

		} elseif ( $taxonomy->show_ui ) {

			$terms_to_edit = get_terms_to_edit( $post->ID, $taxonomy_name );
			if ( ! is_string( $terms_to_edit ) ) {
				$terms_to_edit = '';
			}

			echo '<div class="tags_input" id="' . $taxonomy_name . '_' . $post->ID . '">'
				. esc_html( str_replace( ',', ', ', $terms_to_edit ) ) . '</div>';

		}
	}

	if ( ! $post_type_object->hierarchical ) {
		echo '<div class="sticky">' . ( is_sticky( $post->ID ) ? 'sticky' : '' ) . '</div>';
	}

	if ( post_type_supports( $post->post_type, 'post-formats' ) ) {
		echo '<div class="post_format">' . esc_html( get_post_format( $post->ID ) ) . '</div>';
	}

	echo '</div>';
}
