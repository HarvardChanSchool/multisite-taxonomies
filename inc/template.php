<?php
/**
 * Template WordPress Administration API.
 *
 * A Big Mess. Also some neat functions that are nicely written.
 *
 * @package WordPress
 * @subpackage Administration
 */

/** Walker_Category_Checklist class */
require_once( ABSPATH . 'wp-admin/includes/class-walker-category-checklist.php' );

//
// Category Checklists
//

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
function wp_category_checklist( $post_id = 0, $descendants_and_self = 0, $selected_cats = false, $popular_cats = false, $walker = null, $checked_ontop = true ) {
	wp_terms_checklist( $post_id, array(
		'taxonomy' => 'category',
		'descendants_and_self' => $descendants_and_self,
		'selected_cats' => $selected_cats,
		'popular_cats' => $popular_cats,
		'walker' => $walker,
		'checked_ontop' => $checked_ontop
	) );
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
function wp_terms_checklist( $post_id = 0, $args = array() ) {
 	$defaults = array(
		'descendants_and_self' => 0,
		'selected_cats' => false,
		'popular_cats' => false,
		'walker' => null,
		'taxonomy' => 'category',
		'checked_ontop' => true,
		'echo' => true,
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
		$walker = new Walker_Category_Checklist;
	} else {
		$walker = $r['walker'];
	}

	$taxonomy = $r['taxonomy'];
	$descendants_and_self = (int) $r['descendants_and_self'];

	$args = array( 'taxonomy' => $taxonomy );

	$tax = get_taxonomy( $taxonomy );
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
		$args['popular_cats'] = get_terms( $taxonomy, array(
			'fields' => 'ids',
			'orderby' => 'count',
			'order' => 'DESC',
			'number' => 10,
			'hierarchical' => false
		) );
	}
	if ( $descendants_and_self ) {
		$categories = (array) get_terms( $taxonomy, array(
			'child_of' => $descendants_and_self,
			'hierarchical' => 0,
			'hide_empty' => 0
		) );
		$self = get_term( $descendants_and_self, $taxonomy );
		array_unshift( $categories, $self );
	} else {
		$categories = (array) get_terms( $taxonomy, array( 'get' => 'all' ) );
	}

	$output = '';

	if ( $r['checked_ontop'] ) {
		// Post process $categories rather than adding an exclude to the get_terms() query to keep the query the same across all posts (for any query cache)
		$checked_categories = array();
		$keys = array_keys( $categories );

		foreach ( $keys as $k ) {
			if ( in_array( $categories[$k]->term_id, $args['selected_cats'] ) ) {
				$checked_categories[] = $categories[$k];
				unset( $categories[$k] );
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
 * @param int $default Not used.
 * @param int $number Number of terms to retrieve. Defaults to 10.
 * @param bool $echo Optionally output the list as well. Defaults to true.
 * @return array List of popular term IDs.
 */
function wp_popular_terms_checklist( $taxonomy, $default = 0, $number = 10, $echo = true ) {
	$post = get_post();

	if ( $post && $post->ID )
		$checked_terms = wp_get_object_terms($post->ID, $taxonomy, array('fields'=>'ids'));
	else
		$checked_terms = array();

	$terms = get_terms( $taxonomy, array( 'orderby' => 'count', 'order' => 'DESC', 'number' => $number, 'hierarchical' => false ) );

	$tax = get_taxonomy($taxonomy);

	$popular_ids = array();
	foreach ( (array) $terms as $term ) {
		$popular_ids[] = $term->term_id;
		if ( !$echo ) // Hack for Ajax use.
			continue;
		$id = "popular-$taxonomy-$term->term_id";
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
function wp_link_category_checklist( $link_id = 0 ) {
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

	$categories = get_terms( 'link_category', array( 'orderby' => 'name', 'hide_empty' => 0 ) );

	if ( empty( $categories ) )
		return;

	foreach ( $categories as $category ) {
		$cat_id = $category->term_id;

		/** This filter is documented in wp-includes/category-template.php */
		$name = esc_html( apply_filters( 'the_category', $category->name ) );
		$checked = in_array( $cat_id, $checked_categories ) ? ' checked="checked"' : '';
		echo '<li id="link-category-', $cat_id, '"><label for="in-link-category-', $cat_id, '" class="selectit"><input value="', $cat_id, '" type="checkbox" name="link_category[]" id="in-link-category-', $cat_id, '"', $checked, '/> ', $name, "</label></li>";
	}
}

/**
 * Adds hidden fields with the data for use in the inline editor for posts and pages.
 *
 * @since 2.7.0
 *
 * @param WP_Post $post Post object.
 */
function get_inline_data($post) {
	$post_type_object = get_post_type_object($post->post_type);
	if ( ! current_user_can( 'edit_post', $post->ID ) )
		return;

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
	foreach ( $taxonomy_names as $taxonomy_name) {
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

			echo '<div class="tags_input" id="'.$taxonomy_name.'_'.$post->ID.'">'
				. esc_html( str_replace( ',', ', ', $terms_to_edit ) ) . '</div>';

		}
	}

	if ( !$post_type_object->hierarchical )
		echo '<div class="sticky">' . (is_sticky($post->ID) ? 'sticky' : '') . '</div>';

	if ( post_type_supports( $post->post_type, 'post-formats' ) )
		echo '<div class="post_format">' . esc_html( get_post_format( $post->ID ) ) . '</div>';

	echo '</div>';
}