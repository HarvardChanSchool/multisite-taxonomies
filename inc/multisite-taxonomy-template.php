<?php
/**
 * Multisite Taxonomy API
 *
 * @package multitaxo
 */

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
		'blog_id'              => get_current_blog_id(),
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

	$tax              = get_multisite_taxonomy( $taxonomy );
	$args['disabled'] = ! current_user_can( $tax->cap->assign_multisite_terms );

	$args['list_only'] = ! empty( $r['list_only'] );

	if ( is_array( $r['selected_cats'] ) ) {
		$args['selected_cats'] = $r['selected_cats'];
	} elseif ( $post_id ) {
		$args['selected_cats'] = get_object_multisite_terms( $post_id, $taxonomy, $r['blog_id'], array_merge( $args, array( 'fields' => 'ids' ) ) );
	} else {
		$args['selected_cats'] = array();
	}
	if ( is_array( $r['popular_cats'] ) ) {
		$args['popular_cats'] = $r['popular_cats'];
	} else {
		$args['popular_cats'] = get_multisite_terms(
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
		$categories = (array) get_multisite_terms(
			$taxonomy, array(
				'child_of'     => $descendants_and_self,
				'hierarchical' => 0,
				'hide_empty'   => 0,
			)
		);
		$self       = get_multisite_term( $descendants_and_self, $taxonomy );
		array_unshift( $categories, $self );
	} else {
		$categories = (array) get_multisite_terms( $taxonomy, array( 'get' => 'all' ) );
	}

	$output = '';

	if ( $r['checked_ontop'] ) {
		// Post process $categories rather than adding an exclude to the get_terms() query to keep the query the same across all posts (for any query cache).
		$checked_categories = array();
		$keys               = array_keys( $categories );

		foreach ( $keys as $k ) {
			if ( in_array( $categories[ $k ]->term_id, $args['selected_cats'], true ) ) {
				$checked_categories[] = $categories[ $k ];
				unset( $categories[ $k ] );
			}
		}

		// Put checked cats on top.
		$output .= call_user_func_array( array( $walker, 'walk' ), array( $checked_categories, 0, $args ) );
	}
	// Then the rest of them.
	$output .= call_user_func_array( array( $walker, 'walk' ), array( $categories, 0, $args ) );

	if ( $r['echo'] ) {
		echo $output; // WPCS: XSS ok.
	}

	return $output;
}

/**
 * Display or retrieve the HTML dropdown list of categories.
 *
 * The 'hierarchical' argument, which is disabled by default, will override the
 * depth argument, unless it is true. When the argument is false, it will
 * display all of the categories. When it is enabled it will use the value in
 * the 'depth' argument.
 *
 * @since 2.1.0
 * @since 4.2.0 Introduced the `value_field` argument.
 * @since 4.6.0 Introduced the `required` argument.
 *
 * @param string|array $args {
 *     Optional. Array or string of arguments to generate a categories drop-down element. See WP_Term_Query::__construct()
 *     for information on additional accepted arguments.
 *
 *     @type string       $show_option_all   Text to display for showing all categories. Default empty.
 *     @type string       $show_option_none  Text to display for showing no categories. Default empty.
 *     @type string       $option_none_value Value to use when no category is selected. Default empty.
 *     @type string       $orderby           Which column to use for ordering categories. See get_terms() for a list
 *                                           of accepted values. Default 'id' (term_id).
 *     @type bool         $pad_counts        See get_terms() for an argument description. Default false.
 *     @type bool|int     $show_count        Whether to include post counts. Accepts 0, 1, or their bool equivalents.
 *                                           Default 0.
 *     @type bool|int     $echo              Whether to echo or return the generated markup. Accepts 0, 1, or their
 *                                           bool equivalents. Default 1.
 *     @type bool|int     $hierarchical      Whether to traverse the taxonomy hierarchy. Accepts 0, 1, or their bool
 *                                           equivalents. Default 0.
 *     @type int          $depth             Maximum depth. Default 0.
 *     @type int          $tab_index         Tab index for the select element. Default 0 (no tabindex).
 *     @type string       $name              Value for the 'name' attribute of the select element. Default 'cat'.
 *     @type string       $id                Value for the 'id' attribute of the select element. Defaults to the value
 *                                           of `$name`.
 *     @type string       $class             Value for the 'class' attribute of the select element. Default 'postform'.
 *     @type int|string   $selected          Value of the option that should be selected. Default 0.
 *     @type string       $value_field       Term field that should be used to populate the 'value' attribute
 *                                           of the option elements. Accepts any valid term field: 'term_id', 'name',
 *                                           'slug', 'term_group', 'term_taxonomy_id', 'taxonomy', 'description',
 *                                           'parent', 'count'. Default 'term_id'.
 *     @type string|array $taxonomy          Name of the category or categories to retrieve. Default 'category'.
 *     @type bool         $hide_if_empty     True to skip generating markup if no categories are found.
 *                                           Default false (create select element even if no categories are found).
 *     @type bool         $required          Whether the `<select>` element should have the HTML5 'required' attribute.
 *                                           Default false.
 * }
 * @return string HTML content only if 'echo' argument is 0.
 */
function dropdown_multisite_categories( $args = '' ) {
	$defaults = array(
		'show_option_all'   => '',
		'show_option_none'  => '',
		'orderby'           => 'id',
		'order'             => 'ASC',
		'show_count'        => 0,
		'hide_empty'        => 1,
		'child_of'          => 0,
		'exclude'           => '',
		'echo'              => 1,
		'selected'          => 0,
		'hierarchical'      => 0,
		'name'              => 'cat',
		'id'                => '',
		'class'             => 'postform',
		'depth'             => 0,
		'tab_index'         => 0,
		'taxonomy'          => 'category',
		'hide_if_empty'     => false,
		'option_none_value' => -1,
		'value_field'       => 'term_id',
		'required'          => false,
	);

	$defaults['selected'] = ( is_category() ) ? get_query_var( 'cat' ) : 0;

	// Back compat.
	if ( isset( $args['type'] ) && 'link' === $args['type'] ) {
		_deprecated_argument(
			__FUNCTION__, '3.0.0',
			sprintf(
				/* translators: 1: code block type link, 2: code block link category" */
				wp_kses_post( __( '%1$s is deprecated. Use %2$s instead.', 'multitaxo' ) ),
				'<code>type => link</code>',
				'<code>taxonomy => link_category</code>'
			)
		);
		$args['taxonomy'] = 'link_category';
	}

	$r                 = wp_parse_args( $args, $defaults );
	$option_none_value = $r['option_none_value'];

	if ( ! isset( $r['pad_counts'] ) && $r['show_count'] && $r['hierarchical'] ) {
		$r['pad_counts'] = true;
	}

	$tab_index = $r['tab_index'];

	$tab_index_attribute = '';
	if ( (int) $tab_index > 0 ) {
		$tab_index_attribute = " tabindex=\"$tab_index\"";
	}

	// Avoid clashes with the 'name' param of get_terms().
	$get_terms_args = $r;
	unset( $get_terms_args['name'] );
	$categories = get_multisite_terms( $get_terms_args );
	$name       = esc_attr( $r['name'] );
	$class      = esc_attr( $r['class'] );
	$id         = $r['id'] ? esc_attr( $r['id'] ) : $name;
	$required   = $r['required'] ? 'required' : '';

	if ( ! $r['hide_if_empty'] || ! empty( $categories ) ) {
		$output = "<select $required name='$name' id='$id' class='$class' $tab_index_attribute>\n";
	} else {
		$output = '';
	}
	if ( empty( $categories ) && ! $r['hide_if_empty'] && ! empty( $r['show_option_none'] ) ) {

		/**
		 * Filters a taxonomy drop-down display element.
		 *
		 * A variety of taxonomy drop-down display elements can be modified
		 * just prior to display via this filter. Filterable arguments include
		 * 'show_option_none', 'show_option_all', and various forms of the
		 * term name.
		 *
		 * @since 1.2.0
		 *
		 * @see wp_dropdown_categories()
		 *
		 * @param string       $element  Category name.
		 * @param WP_Term|null $category The category object, or null if there's no corresponding category.
		 */
		$show_option_none = apply_filters( 'list_cats', $r['show_option_none'], null );
		$output          .= "\t<option value='" . esc_attr( $option_none_value ) . "' selected='selected'>$show_option_none</option>\n";
	}

	if ( is_array( $categories ) && ! empty( $categories ) ) {

		if ( $r['show_option_all'] ) {

			/** This filter is documented in wp-includes/category-template.php */
			$show_option_all = apply_filters( 'list_cats', $r['show_option_all'], null );
			$selected        = ( '0' === strval( $r['selected'] ) ) ? " selected='selected'" : '';
			$output         .= "\t<option value='0'$selected>$show_option_all</option>\n";
		}

		if ( $r['show_option_none'] ) {

			/** This filter is documented in wp-includes/category-template.php */
			$show_option_none = apply_filters( 'list_cats', $r['show_option_none'], null );
			$selected         = selected( $option_none_value, $r['selected'], false );
			$output          .= "\t<option value='" . esc_attr( $option_none_value ) . "'$selected>$show_option_none</option>\n";
		}

		if ( $r['hierarchical'] ) {
			$depth = $r['depth'];  // Walk the full depth.
		} else {
			$depth = -1; // Flat.
		}
		$output .= walk_category_dropdown_tree( $categories, $depth, $r );
	}

	if ( ! $r['hide_if_empty'] || ! empty( $categories ) ) {
		$output .= "</select>\n";
	}
	/**
	 * Filters the taxonomy drop-down output.
	 *
	 * @since 2.1.0
	 *
	 * @param string $output HTML output.
	 * @param array  $r      Arguments used to build the drop-down.
	 */
	$output = apply_filters( 'wp_dropdown_cats', $output, $r );

	if ( $r['echo'] ) {

		$dropdown_allowed_html = array(
			'select' => array( 'class', 'id', 'name' ),
			'option' => array( 'class', 'id', 'name', 'value', 'type', 'selected' ),
		);

		echo wp_kses( $output, $dropdown_allowed_html );
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

	$blog_id = get_current_blog_id();

	if ( $post && $post->ID ) {
		$checked_terms = get_object_multisite_terms( $post->ID, $taxonomy, $blog_id, array( 'fields' => 'ids' ) );
	} else {
		$checked_terms = array();
	}

	$terms = get_multisite_terms(
		array(
			'orderby'      => 'count',
			'order'        => 'DESC',
			'number'       => $number,
			'hierarchical' => false,
			'taxonomy'     => $taxonomy,
			'hide_empty'   => false,
		)
	);

	$tax = get_multisite_taxonomy( $taxonomy );

	$popular_ids = array();
	foreach ( (array) $terms as $term ) {
		$popular_ids[] = $term->id;
		if ( ! $echo ) { // Hack for Ajax use.
			continue;
		}
		$id      = "popular-$taxonomy-$term->id";
		$checked = in_array( $term->id, $checked_terms, true ) ? 'checked="checked"' : '';
		?>

		<li id="<?php echo esc_attr( $id ); ?>" class="popular-category">
			<label class="selectit">
				<input id="in-<?php echo esc_attr( $id ); ?>" type="checkbox" <?php echo $checked; // WPCS: XSS ok. ?> value="<?php echo (int) $term->term_id; ?>" <?php disabled( ! current_user_can( $tax->cap->assign_multisite_terms ) ); ?> />
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
 * @param int $link_id Link ID for category.
 */
function link_multisite_category_checklist( $link_id = 0 ) {
	$default = 1;

	$checked_categories = array();

	if ( $link_id ) {
		$checked_categories = wp_get_link_cats( $link_id );
		// No selected categories, strange.
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
		$checked = in_array( $cat_id, $checked_categories, true ) ? ' checked="checked"' : '';
		echo '<li id="link-category-' . esc_attr( $cat_id ) . '"><label for="in-link-category-' . esc_attr( $cat_id ) . '" class="selectit"><input value="' . esc_attr( $cat_id ) . '" type="checkbox" name="link_category[]" id="in-link-category-' . esc_attr( $cat_id ) . '"' . $checked . '/> ' . esc_html( $name ) . '</label></li>'; // WPCS: XSS ok.
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
<div class="hidden" id="inline_' . esc_attr( $post->ID ) . '">
	<div class="post_title">' . esc_html( $title ) . '</div>' .
	/** This filter is documented in wp-admin/edit-tag-form.php */
	'<div class="post_name">' . esc_html( apply_filters( 'editable_slug', $post->post_name, $post ) ) . '</div>
	<div class="post_author">' . esc_html( $post->post_author ) . '</div>
	<div class="comment_status">' . esc_html( $post->comment_status ) . '</div>
	<div class="ping_status">' . esc_html( $post->ping_status ) . '</div>
	<div class="_status">' . esc_html( $post->post_status ) . '</div>
	<div class="jj">' . esc_html( mysql2date( 'd', $post->post_date, false ) ) . '</div>
	<div class="mm">' . esc_html( mysql2date( 'm', $post->post_date, false ) ) . '</div>
	<div class="aa">' . esc_html( mysql2date( 'Y', $post->post_date, false ) ) . '</div>
	<div class="hh">' . esc_html( mysql2date( 'H', $post->post_date, false ) ) . '</div>
	<div class="mn">' . esc_html( mysql2date( 'i', $post->post_date, false ) ) . '</div>
	<div class="ss">' . esc_html( mysql2date( 's', $post->post_date, false ) ) . '</div>
	<div class="post_password">' . esc_html( $post->post_password ) . '</div>';

	if ( $post_type_object->hierarchical ) {
		echo '<div class="post_parent">' . esc_html( $post->post_parent ) . '</div>';
	}

	echo '<div class="page_template">' . ( $post->page_template ? esc_html( $post->page_template ) : 'default' ) . '</div>'; // WPCS: XSS ok.

	if ( post_type_supports( $post->post_type, 'page-attributes' ) ) {
		echo '<div class="menu_order">' . esc_html( $post->menu_order ) . '</div>';
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

			echo '<div class="post_category" id="' . esc_attr( $taxonomy_name ) . '_' . esc_attr( $post->ID ) . '">' . esc_html( implode( ',', $term_ids ) ) . '</div>';

		} elseif ( $taxonomy->show_ui ) {

			$terms_to_edit = get_terms_to_edit( $post->ID, $taxonomy_name );
			if ( ! is_string( $terms_to_edit ) ) {
				$terms_to_edit = '';
			}

			echo '<div class="tags_input" id="' . esc_attr( $taxonomy_name ) . '_' . esc_attr( $post->ID ) . '">'
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

/**
 * Display or retrieve the HTML list of categories.
 *
 * @since 2.1.0
 * @since 4.4.0 Introduced the `hide_title_if_empty` and `separator` arguments. The `current_category` argument was modified to
 *              optionally accept an array of values.
 *
 * @param string|array $args {
 *     Array of optional arguments.
 *
 *     @type int          $child_of              Term ID to retrieve child terms of. See get_terms(). Default 0.
 *     @type int|array    $current_category      ID of category, or array of IDs of categories, that should get the
 *                                               'current-cat' class. Default 0.
 *     @type int          $depth                 Category depth. Used for tab indentation. Default 0.
 *     @type bool|int     $echo                  True to echo markup, false to return it. Default 1.
 *     @type array|string $exclude               Array or comma/space-separated string of term IDs to exclude.
 *                                               If `$hierarchical` is true, descendants of `$exclude` terms will also
 *                                               be excluded; see `$exclude_tree`. See get_terms().
 *                                               Default empty string.
 *     @type array|string $exclude_tree          Array or comma/space-separated string of term IDs to exclude, along
 *                                               with their descendants. See get_terms(). Default empty string.
 *     @type string       $feed                  Text to use for the feed link. Default 'Feed for all posts filed
 *                                               under [cat name]'.
 *     @type string       $feed_image            URL of an image to use for the feed link. Default empty string.
 *     @type string       $feed_type             Feed type. Used to build feed link. See get_term_feed_link().
 *                                               Default empty string (default feed).
 *     @type bool|int     $hide_empty            Whether to hide categories that don't have any posts attached to them.
 *                                               Default 1.
 *     @type bool         $hide_title_if_empty   Whether to hide the `$title_li` element if there are no terms in
 *                                               the list. Default false (title will always be shown).
 *     @type bool         $hierarchical          Whether to include terms that have non-empty descendants.
 *                                               See get_terms(). Default true.
 *     @type string       $order                 Which direction to order categories. Accepts 'ASC' or 'DESC'.
 *                                               Default 'ASC'.
 *     @type string       $orderby               The column to use for ordering categories. Default 'name'.
 *     @type string       $separator             Separator between links. Default '<br />'.
 *     @type bool|int     $show_count            Whether to show how many posts are in the category. Default 0.
 *     @type string       $show_option_all       Text to display for showing all categories. Default empty string.
 *     @type string       $show_option_none      Text to display for the 'no categories' option.
 *                                               Default 'No categories'.
 *     @type string       $style                 The style used to display the categories list. If 'list', categories
 *                                               will be output as an unordered list. If left empty or another value,
 *                                               categories will be output separated by `<br>` tags. Default 'list'.
 *     @type string       $taxonomy              Taxonomy name. Default 'category'.
 *     @type string       $title_li              Text to use for the list title `<li>` element. Pass an empty string
 *                                               to disable. Default 'Categories'.
 *     @type bool|int     $use_desc_for_title    Whether to use the category description as the title attribute.
 *                                               Default 1.
 * }
 * @return false|string HTML content only if 'echo' argument is 0.
 */
function multisite_list_categories( $args = '' ) {
	$defaults = array(
		'child_of'            => 0,
		'current_category'    => 0,
		'depth'               => 0,
		'echo'                => 1,
		'exclude'             => '',
		'exclude_tree'        => '',
		'feed'                => '',
		'feed_image'          => '',
		'feed_type'           => '',
		'hide_empty'          => 1,
		'hide_title_if_empty' => false,
		'hierarchical'        => true,
		'order'               => 'ASC',
		'orderby'             => 'name',
		'separator'           => '<br />',
		'show_count'          => 0,
		'show_option_all'     => '',
		'show_option_none'    => __( 'No categories' ),
		'style'               => 'list',
		'taxonomy'            => 'category',
		'title_li'            => __( 'Categories' ),
		'use_desc_for_title'  => 1,
	);

	$r = wp_parse_args( $args, $defaults );

	if ( ! isset( $r['pad_counts'] ) && $r['show_count'] && $r['hierarchical'] ) {
		$r['pad_counts'] = true;
	}

	// Descendants of exclusions should be excluded too.
	if ( true == $r['hierarchical'] ) {
		$exclude_tree = array();

		if ( $r['exclude_tree'] ) {
			$exclude_tree = array_merge( $exclude_tree, wp_parse_id_list( $r['exclude_tree'] ) );
		}

		if ( $r['exclude'] ) {
			$exclude_tree = array_merge( $exclude_tree, wp_parse_id_list( $r['exclude'] ) );
		}

		$r['exclude_tree'] = $exclude_tree;
		$r['exclude']      = '';
	}

	if ( ! isset( $r['class'] ) ) {
		$r['class'] = ( 'category' == $r['taxonomy'] ) ? 'categories' : $r['taxonomy'];
	}

	if ( ! taxonomy_exists( $r['taxonomy'] ) ) {
		return false;
	}

	$show_option_all  = $r['show_option_all'];
	$show_option_none = $r['show_option_none'];

	$categories = get_categories( $r );

	$output = '';
	if ( $r['title_li'] && 'list' == $r['style'] && ( ! empty( $categories ) || ! $r['hide_title_if_empty'] ) ) {
		$output = '<li class="' . esc_attr( $r['class'] ) . '">' . $r['title_li'] . '<ul>';
	}
	if ( empty( $categories ) ) {
		if ( ! empty( $show_option_none ) ) {
			if ( 'list' == $r['style'] ) {
				$output .= '<li class="cat-item-none">' . $show_option_none . '</li>';
			} else {
				$output .= $show_option_none;
			}
		}
	} else {
		if ( ! empty( $show_option_all ) ) {

			$posts_page = '';

			// For taxonomies that belong only to custom post types, point to a valid archive.
			$taxonomy_object = get_taxonomy( $r['taxonomy'] );
			if ( ! in_array( 'post', $taxonomy_object->object_type ) && ! in_array( 'page', $taxonomy_object->object_type ) ) {
				foreach ( $taxonomy_object->object_type as $object_type ) {
					$_object_type = get_post_type_object( $object_type );

					// Grab the first one.
					if ( ! empty( $_object_type->has_archive ) ) {
						$posts_page = get_post_type_archive_link( $object_type );
						break;
					}
				}
			}

			// Fallback for the 'All' link is the posts page.
			if ( ! $posts_page ) {
				if ( 'page' == get_option( 'show_on_front' ) && get_option( 'page_for_posts' ) ) {
					$posts_page = get_permalink( get_option( 'page_for_posts' ) );
				} else {
					$posts_page = home_url( '/' );
				}
			}

			$posts_page = esc_url( $posts_page );
			if ( 'list' == $r['style'] ) {
				$output .= "<li class='cat-item-all'><a href='$posts_page'>$show_option_all</a></li>";
			} else {
				$output .= "<a href='$posts_page'>$show_option_all</a>";
			}
		}

		if ( empty( $r['current_category'] ) && ( is_category() || is_tax() || is_tag() ) ) {
			$current_term_object = get_queried_object();
			if ( $current_term_object && $r['taxonomy'] === $current_term_object->taxonomy ) {
				$r['current_category'] = get_queried_object_id();
			}
		}

		if ( $r['hierarchical'] ) {
			$depth = $r['depth'];
		} else {
			$depth = -1; // Flat.
		}
		$output .= walk_category_tree( $categories, $depth, $r );
	}

	if ( $r['title_li'] && 'list' == $r['style'] && ( ! empty( $categories ) || ! $r['hide_title_if_empty'] ) ) {
		$output .= '</ul></li>';
	}

	/**
	 * Filters the HTML output of a taxonomy list.
	 *
	 * @since 2.1.0
	 *
	 * @param string $output HTML output.
	 * @param array  $args   An array of taxonomy-listing arguments.
	 */
	$html = apply_filters( 'wp_list_categories', $output, $args );

	if ( $r['echo'] ) {
		echo $html;
	} else {
		return $html;
	}
}

/**
 * Display tag cloud.
 *
 * The text size is set by the 'smallest' and 'largest' arguments, which will
 * use the 'unit' argument value for the CSS text size unit. The 'format'
 * argument can be 'flat' (default), 'list', or 'array'. The flat value for the
 * 'format' argument will separate tags with spaces. The list value for the
 * 'format' argument will format the tags in a UL HTML list. The array value for
 * the 'format' argument will return in PHP array type format.
 *
 * The 'orderby' argument will accept 'name' or 'count' and defaults to 'name'.
 * The 'order' is the direction to sort, defaults to 'ASC' and can be 'DESC'.
 *
 * The 'number' argument is how many tags to return. By default, the limit will
 * be to return the top 45 tags in the tag cloud list.
 *
 * The 'topic_count_text' argument is a nooped plural from _n_noop() to generate the
 * text for the tag link count.
 *
 * The 'topic_count_text_callback' argument is a function, which given the count
 * of the posts with that tag returns a text for the tag link count.
 *
 * The 'post_type' argument is used only when 'link' is set to 'edit'. It determines the post_type
 * passed to edit.php for the popular tags edit links.
 *
 * The 'exclude' and 'include' arguments are used for the get_tags() function. Only one
 * should be used, because only one will be used and the other ignored, if they are both set.
 *
 * @since 2.3.0
 * @since 4.8.0 Added the `show_count` argument.
 *
 * @param array|string|null $args Optional. Override default arguments.
 * @return void|array Generated tag cloud, only if no failures and 'array' is set for the 'format' argument.
 *                    Otherwise, this function outputs the tag cloud.
 */
function multisite_tag_cloud( $args = '' ) {
	$defaults = array(
		'smallest'   => 8,
		'largest'    => 22,
		'unit'       => 'pt',
		'number'     => 45,
		'format'     => 'flat',
		'separator'  => "\n",
		'orderby'    => 'name',
		'order'      => 'ASC',
		'exclude'    => '',
		'include'    => '',
		'link'       => 'view',
		'taxonomy'   => 'post_tag',
		'post_type'  => '',
		'echo'       => true,
		'show_count' => 0,
	);
	$args     = wp_parse_args( $args, $defaults );

	$tags = get_terms(
		$args['taxonomy'], array_merge(
			$args, array(
				'orderby' => 'count',
				'order'   => 'DESC',
			)
		)
	); // Always query top tags.

	if ( empty( $tags ) || is_wp_error( $tags ) ) {
		return;
	}

	foreach ( $tags as $key => $tag ) {
		if ( 'edit' == $args['link'] ) {
			$link = get_edit_term_link( $tag->term_id, $tag->taxonomy, $args['post_type'] );
		} else {
			$link = get_term_link( intval( $tag->term_id ), $tag->taxonomy );
		}
		if ( is_wp_error( $link ) ) {
			return;
		}

		$tags[ $key ]->link = $link;
		$tags[ $key ]->id   = $tag->term_id;
	}

	$return = generate_multisite_tag_cloud( $tags, $args ); // Here's where those top tags get sorted according to $args

	/**
	 * Filters the tag cloud output.
	 *
	 * @since 2.3.0
	 *
	 * @param string $return HTML output of the tag cloud.
	 * @param array  $args   An array of tag cloud arguments.
	 */
	$return = apply_filters( 'wp_tag_cloud', $return, $args );

	if ( 'array' == $args['format'] || empty( $args['echo'] ) ) {
		return $return;
	}

	echo $return;
}

/**
 * Generates a tag cloud (heatmap) from provided data.
 *
 * @todo Complete functionality.
 * @since 2.3.0
 * @since 4.8.0 Added the `show_count` argument.
 *
 * @param array        $tags List of tags.
 * @param string|array $args {
 *     Optional. Array of string of arguments for generating a tag cloud.
 *
 *     @type int      $smallest                   Smallest font size used to display tags. Paired
 *                                                with the value of `$unit`, to determine CSS text
 *                                                size unit. Default 8 (pt).
 *     @type int      $largest                    Largest font size used to display tags. Paired
 *                                                with the value of `$unit`, to determine CSS text
 *                                                size unit. Default 22 (pt).
 *     @type string   $unit                       CSS text size unit to use with the `$smallest`
 *                                                and `$largest` values. Accepts any valid CSS text
 *                                                size unit. Default 'pt'.
 *     @type int      $number                     The number of tags to return. Accepts any
 *                                                positive integer or zero to return all.
 *                                                Default 0.
 *     @type string   $format                     Format to display the tag cloud in. Accepts 'flat'
 *                                                (tags separated with spaces), 'list' (tags displayed
 *                                                in an unordered list), or 'array' (returns an array).
 *                                                Default 'flat'.
 *     @type string   $separator                  HTML or text to separate the tags. Default "\n" (newline).
 *     @type string   $orderby                    Value to order tags by. Accepts 'name' or 'count'.
 *                                                Default 'name'. The {@see 'tag_cloud_sort'} filter
 *                                                can also affect how tags are sorted.
 *     @type string   $order                      How to order the tags. Accepts 'ASC' (ascending),
 *                                                'DESC' (descending), or 'RAND' (random). Default 'ASC'.
 *     @type int|bool $filter                     Whether to enable filtering of the final output
 *                                                via {@see 'wp_generate_tag_cloud'}. Default 1|true.
 *     @type string   $topic_count_text           Nooped plural text from _n_noop() to supply to
 *                                                tag counts. Default null.
 *     @type callable $topic_count_text_callback  Callback used to generate nooped plural text for
 *                                                tag counts based on the count. Default null.
 *     @type callable $topic_count_scale_callback Callback used to determine the tag count scaling
 *                                                value. Default default_topic_count_scale().
 *     @type bool|int $show_count                 Whether to display the tag counts. Default 0. Accepts
 *                                                0, 1, or their bool equivalents.
 * }
 * @return string|array Tag cloud as a string or an array, depending on 'format' argument.
 */
function generate_multisite_tag_cloud( $tags, $args = '' ) {
	$defaults = array(
		'smallest'                   => 8,
		'largest'                    => 22,
		'unit'                       => 'pt',
		'number'                     => 0,
		'format'                     => 'flat',
		'separator'                  => "\n",
		'orderby'                    => 'name',
		'order'                      => 'ASC',
		'topic_count_text'           => null,
		'topic_count_text_callback'  => null,
		'topic_count_scale_callback' => 'default_topic_count_scale',
		'filter'                     => 1,
		'show_count'                 => 0,
	);

	$args = wp_parse_args( $args, $defaults );

	$return = ( 'array' === $args['format'] ) ? array() : '';

	if ( empty( $tags ) ) {
		return $return;
	}

	// Juggle topic counts.
	if ( isset( $args['topic_count_text'] ) ) {
		// First look for nooped plural support via topic_count_text.
		$translate_nooped_plural = $args['topic_count_text'];
	} elseif ( ! empty( $args['topic_count_text_callback'] ) ) {
		// Look for the alternative callback style. Ignore the previous default.
		if ( $args['topic_count_text_callback'] === 'default_topic_count_text' ) {
			$translate_nooped_plural = _n_noop( '%s item', '%s items' );
		} else {
			$translate_nooped_plural = false;
		}
	} elseif ( isset( $args['single_text'] ) && isset( $args['multiple_text'] ) ) {
		// If no callback exists, look for the old-style single_text and multiple_text arguments.
		$translate_nooped_plural = _n_noop( $args['single_text'], $args['multiple_text'] );
	} else {
		// This is the default for when no callback, plural, or argument is passed in.
		$translate_nooped_plural = _n_noop( '%s item', '%s items' );
	}

	/**
	 * Filters how the items in a tag cloud are sorted.
	 *
	 * @since 2.8.0
	 *
	 * @param array $tags Ordered array of terms.
	 * @param array $args An array of tag cloud arguments.
	 */
	$tags_sorted = apply_filters( 'tag_cloud_sort', $tags, $args );
	if ( empty( $tags_sorted ) ) {
		return $return;
	}

	if ( $tags_sorted !== $tags ) {
		$tags = $tags_sorted;
		unset( $tags_sorted );
	} else {
		if ( 'RAND' === $args['order'] ) {
			shuffle( $tags );
		} else {
			// SQL cannot save you; this is a second (potentially different) sort on a subset of data.
			if ( 'name' === $args['orderby'] ) {
				uasort( $tags, '_wp_object_name_sort_cb' );
			} else {
				uasort( $tags, '_wp_object_count_sort_cb' );
			}

			if ( 'DESC' === $args['order'] ) {
				$tags = array_reverse( $tags, true );
			}
		}
	}

	if ( $args['number'] > 0 ) {
		$tags = array_slice( $tags, 0, $args['number'] );
	}

	$counts      = array();
	$real_counts = array(); // For the alt tag
	foreach ( (array) $tags as $key => $tag ) {
		$real_counts[ $key ] = $tag->count;
		$counts[ $key ]      = call_user_func( $args['topic_count_scale_callback'], $tag->count );
	}

	$min_count = min( $counts );
	$spread    = max( $counts ) - $min_count;
	if ( $spread <= 0 ) {
		$spread = 1;
	}
	$font_spread = $args['largest'] - $args['smallest'];
	if ( $font_spread < 0 ) {
		$font_spread = 1;
	}
	$font_step = $font_spread / $spread;

	$aria_label = false;
	/*
	 * Determine whether to output an 'aria-label' attribute with the tag name and count.
	 * When tags have a different font size, they visually convey an important information
	 * that should be available to assistive technologies too. On the other hand, sometimes
	 * themes set up the Tag Cloud to display all tags with the same font size (setting
	 * the 'smallest' and 'largest' arguments to the same value).
	 * In order to always serve the same content to all users, the 'aria-label' gets printed out:
	 * - when tags have a different size
	 * - when the tag count is displayed (for example when users check the checkbox in the
	 *   Tag Cloud widget), regardless of the tags font size
	 */
	if ( $args['show_count'] || 0 !== $font_spread ) {
		$aria_label = true;
	}

	// Assemble the data that will be used to generate the tag cloud markup.
	$tags_data = array();
	foreach ( $tags as $key => $tag ) {
		$tag_id = isset( $tag->id ) ? $tag->id : $key;

		$count      = $counts[ $key ];
		$real_count = $real_counts[ $key ];

		if ( $translate_nooped_plural ) {
			$formatted_count = sprintf( translate_nooped_plural( $translate_nooped_plural, $real_count ), number_format_i18n( $real_count ) );
		} else {
			$formatted_count = call_user_func( $args['topic_count_text_callback'], $real_count, $tag, $args );
		}

		$tags_data[] = array(
			'id'              => $tag_id,
			'url'             => '#' != $tag->link ? $tag->link : '#',
			'role'            => '#' != $tag->link ? '' : ' role="button"',
			'name'            => $tag->name,
			'formatted_count' => $formatted_count,
			'slug'            => $tag->slug,
			'real_count'      => $real_count,
			'class'           => 'tag-cloud-link tag-link-' . $tag_id,
			'font_size'       => $args['smallest'] + ( $count - $min_count ) * $font_step,
			'aria_label'      => $aria_label ? sprintf( ' aria-label="%1$s (%2$s)"', esc_attr( $tag->name ), esc_attr( $formatted_count ) ) : '',
			'show_count'      => $args['show_count'] ? '<span class="tag-link-count"> (' . $real_count . ')</span>' : '',
		);
	}

	/**
	 * Filters the data used to generate the tag cloud.
	 *
	 * @since 4.3.0
	 *
	 * @param array $tags_data An array of term data for term used to generate the tag cloud.
	 */
	$tags_data = apply_filters( 'wp_generate_tag_cloud_data', $tags_data );

	$a = array();

	// Generate the output links array.
	foreach ( $tags_data as $key => $tag_data ) {
		$class = $tag_data['class'] . ' tag-link-position-' . ( $key + 1 );
		$a[]   = sprintf(
			'<a href="%1$s"%2$s class="%3$s" style="font-size: %4$s;"%5$s>%6$s%7$s</a>',
			esc_url( $tag_data['url'] ),
			$tag_data['role'],
			esc_attr( $class ),
			esc_attr( str_replace( ',', '.', $tag_data['font_size'] ) . $args['unit'] ),
			$tag_data['aria_label'],
			esc_html( $tag_data['name'] ),
			$tag_data['show_count']
		);
	}

	switch ( $args['format'] ) {
		case 'array':
			$return =& $a;
			break;
		case 'list':
			/*
			 * Force role="list", as some browsers (sic: Safari 10) don't expose to assistive
			 * technologies the default role when the list is styled with `list-style: none`.
			 * Note: this is redundant but doesn't harm.
			 */
			$return  = "<ul class='wp-tag-cloud' role='list'>\n\t<li>";
			$return .= join( "</li>\n\t<li>", $a );
			$return .= "</li>\n</ul>\n";
			break;
		default:
			$return = join( $args['separator'], $a );
			break;
	}

	if ( $args['filter'] ) {
		/**
		 * Filters the generated output of a tag cloud.
		 *
		 * The filter is only evaluated if a true value is passed
		 * to the $filter argument in wp_generate_tag_cloud().
		 *
		 * @since 2.3.0
		 *
		 * @see wp_generate_tag_cloud()
		 *
		 * @param array|string $return String containing the generated HTML tag cloud output
		 *                             or an array of tag links if the 'format' argument
		 *                             equals 'array'.
		 * @param array        $tags   An array of terms used in the tag cloud.
		 * @param array        $args   An array of wp_generate_tag_cloud() arguments.
		 */
		return apply_filters( 'wp_generate_tag_cloud', $return, $tags, $args );
	} else {
		return $return;
	}
}

//
// Helper functions
//
/**
 * Retrieve HTML list content for category list.
 *
 * @uses Walker_Category to create HTML list content.
 * @since 2.1.0
 * @see Walker_Category::walk() for parameters and return description.
 * @return string
 */
function walk_multisite_category_tree() {
	$args = func_get_args();
	// the user's options are the third parameter.
	if ( empty( $args[2]['walker'] ) || ! ( $args[2]['walker'] instanceof Walker ) ) {
		$walker = new Walker_Multisite_Category();
	} else {
		$walker = $args[2]['walker'];
	}
	return call_user_func_array( array( $walker, 'walk' ), $args );
}

/**
 * Retrieve HTML dropdown (select) content for category list.
 *
 * @uses Walker_CategoryDropdown to create HTML dropdown content.
 * @since 2.1.0
 * @see Walker_CategoryDropdown::walk() for parameters and return description.
 * @return string
 */
function walk_multisite_category_dropdown_tree() {
	$args = func_get_args();
	// the user's options are the third parameter.
	if ( empty( $args[2]['walker'] ) || ! ( $args[2]['walker'] instanceof Walker ) ) {
		$walker = new Walker_Multisite_CategoryDropdown();
	} else {
		$walker = $args[2]['walker'];
	}
	return call_user_func_array( array( $walker, 'walk' ), $args );
}

/**
 * Retrieve term description.
 *
 * @since 2.8.0
 *
 * @param int    $term Optional. Term ID. Will use global term ID by default.
 * @param string $taxonomy Optional taxonomy name. Defaults to 'post_tag'.
 * @return string Term description, available.
 */
function multisite_term_description( $term = 0, $taxonomy = 'post_tag' ) {
	if ( ! $term && ( is_tax() || is_tag() || is_category() ) ) {
		$term = get_queried_object();
		if ( $term ) {
			$taxonomy = $term->taxonomy;
			$term     = $term->term_id;
		}
	}
	$description = get_term_field( 'description', $term, $taxonomy );
	return is_wp_error( $description ) ? '' : $description;
}

/**
 * Retrieve the terms of the taxonomy that are attached to the post.
 *
 * @since 2.5.0
 *
 * @param int|object $post Post ID or object.
 * @param string     $taxonomy Taxonomy name.
 * @return array|false|WP_Error Array of WP_Term objects on success, false if there are no terms
 *                              or the post does not exist, WP_Error on failure.
 */
function get_the_multisite_terms( $post, $taxonomy ) {
	if ( ! $post = get_post( $post ) ) {
		return false;
	}

	$terms = get_object_term_cache( $post->ID, $taxonomy );
	if ( false === $terms ) {
		$terms = wp_get_object_terms( $post->ID, $taxonomy );
		if ( ! is_wp_error( $terms ) ) {
			$term_ids = wp_list_pluck( $terms, 'term_id' );
			wp_cache_add( $post->ID, $term_ids, $taxonomy . '_relationships' );
		}
	}

	/**
	 * Filters the list of terms attached to the given post.
	 *
	 * @since 3.1.0
	 *
	 * @param array|WP_Error $terms    List of attached terms, or WP_Error on failure.
	 * @param int            $post_id  Post ID.
	 * @param string         $taxonomy Name of the taxonomy.
	 */
	$terms = apply_filters( 'get_the_terms', $terms, $post->ID, $taxonomy );

	if ( empty( $terms ) ) {
		return false;
	}

	return $terms;
}

/**
 * Retrieve a post's terms as a list with specified format.
 *
 * @since 2.5.0
 *
 * @param int    $id Post ID.
 * @param string $taxonomy Taxonomy name.
 * @param string $before Optional. Before list.
 * @param string $sep Optional. Separate items using this.
 * @param string $after Optional. After list.
 * @return string|false|WP_Error A list of terms on success, false if there are no terms, WP_Error on failure.
 */
function get_the_multisite_term_list( $id, $taxonomy, $before = '', $sep = '', $after = '' ) {
	$terms = get_the_terms( $id, $taxonomy );

	if ( is_wp_error( $terms ) ) {
		return $terms;
	}

	if ( empty( $terms ) ) {
		return false;
	}

	$links = array();

	foreach ( $terms as $term ) {
		$link = get_term_link( $term, $taxonomy );
		if ( is_wp_error( $link ) ) {
			return $link;
		}
		$links[] = '<a href="' . esc_url( $link ) . '" rel="tag">' . $term->name . '</a>';
	}

	/**
	 * Filters the term links for a given taxonomy.
	 *
	 * The dynamic portion of the filter name, `$taxonomy`, refers
	 * to the taxonomy slug.
	 *
	 * @since 2.5.0
	 *
	 * @param array $links An array of term links.
	 */
	$term_links = apply_filters( "term_links-{$taxonomy}", $links );

	return $before . join( $sep, $term_links ) . $after;
}

/**
 * Retrieve term parents with separator.
 *
 * @since 4.8.0
 *
 * @param int          $term_id  Term ID.
 * @param string       $taxonomy Taxonomy name.
 * @param string|array $args {
 *     Array of optional arguments.
 *
 *     @type string $format    Use term names or slugs for display. Accepts 'name' or 'slug'.
 *                             Default 'name'.
 *     @type string $separator Separator for between the terms. Default '/'.
 *     @type bool   $link      Whether to format as a link. Default true.
 *     @type bool   $inclusive Include the term to get the parents for. Default true.
 * }
 * @return string|WP_Error A list of term parents on success, WP_Error or empty string on failure.
 */
function get_multisite_term_parents_list( $term_id, $taxonomy, $args = array() ) {
	$list = '';
	$term = get_term( $term_id, $taxonomy );

	if ( is_wp_error( $term ) ) {
		return $term;
	}

	if ( ! $term ) {
		return $list;
	}

	$term_id = $term->term_id;

	$defaults = array(
		'format'    => 'name',
		'separator' => '/',
		'link'      => true,
		'inclusive' => true,
	);

	$args = wp_parse_args( $args, $defaults );

	foreach ( array( 'link', 'inclusive' ) as $bool ) {
		$args[ $bool ] = wp_validate_boolean( $args[ $bool ] );
	}

	$parents = get_ancestors( $term_id, $taxonomy, 'taxonomy' );

	if ( $args['inclusive'] ) {
		array_unshift( $parents, $term_id );
	}

	foreach ( array_reverse( $parents ) as $term_id ) {
		$parent = get_term( $term_id, $taxonomy );
		$name   = ( 'slug' === $args['format'] ) ? $parent->slug : $parent->name;

		if ( $args['link'] ) {
			$list .= '<a href="' . esc_url( get_term_link( $parent->term_id, $taxonomy ) ) . '">' . $name . '</a>' . $args['separator'];
		} else {
			$list .= $name . $args['separator'];
		}
	}

	return $list;
}

/**
 * Display the terms in a list.
 *
 * @since 2.5.0
 *
 * @param int    $id Post ID.
 * @param string $taxonomy Taxonomy name.
 * @param string $before Optional. Before list.
 * @param string $sep Optional. Separate items using this.
 * @param string $after Optional. After list.
 * @return false|void False on WordPress error.
 */
function the_multisite_terms( $id, $taxonomy, $before = '', $sep = ', ', $after = '' ) {
	$term_list = get_the_term_list( $id, $taxonomy, $before, $sep, $after );

	if ( is_wp_error( $term_list ) ) {
		return false;
	}

	/**
	 * Filters the list of terms to display.
	 *
	 * @since 2.9.0
	 *
	 * @param array  $term_list List of terms to display.
	 * @param string $taxonomy  The taxonomy name.
	 * @param string $before    String to use before the terms.
	 * @param string $sep       String to use between the terms.
	 * @param string $after     String to use after the terms.
	 */
	echo apply_filters( 'the_terms', $term_list, $taxonomy, $before, $sep, $after );
}

/**
 * Check if the current post has any of given terms.
 *
 * The given terms are checked against the post's terms' term_ids, names and slugs.
 * Terms given as integers will only be checked against the post's terms' term_ids.
 * If no terms are given, determines if post has any terms.
 *
 * @since 3.1.0
 *
 * @param string|int|array $term Optional. The term name/term_id/slug or array of them to check for.
 * @param string           $taxonomy Taxonomy name
 * @param int|object       $post Optional. Post to check instead of the current post.
 * @return bool True if the current post has any of the given tags (or any tag, if no tag specified).
 */
function has_multistite_term( $term = '', $taxonomy = '', $post = null ) {
	$post = get_post( $post );

	if ( ! $post ) {
		return false;
	}

	$r = is_object_in_term( $post->ID, $taxonomy, $term );
	if ( is_wp_error( $r ) ) {
		return false;
	}

	return $r;
}
