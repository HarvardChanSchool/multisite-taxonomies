<?php
/**
 * Multisite Taxonomy API : Template functions
 *
 * @package multitaxo
 */

/**
 * Output an unordered list of checkbox input elements labelled with multisite term names.
 *
 * @param int          $post_id Optional. Post ID. Default 0.
 * @param array|string $args {
 *     Optional. Array or string of arguments for generating a multisite terms checklist. Default empty array.
 *
 *     @type int    $descendants_and_self ID of the multisite terms to output along with its descendants.
 *                                        Default 0.
 *     @type array  $selected_terms        List of multisite terms to mark as checked. Default false.
 *     @type array  $popular_terms         List of multisite terms to receive the "popular-terms" class.
 *                                        Default false.
 *     @type object $walker               Walker object to use to build the output.
 *                                        Default is a Walker_Hierarchical_Multisite_Taxonomy_Checklist instance.
 *     @type string $taxonomy             Multisite Taxonomy to generate the checklist for. Default.
 *     @type bool   $checked_ontop        Whether to move checked items out of the hierarchy and to
 *                                        the top of the list. Default true.
 *     @type bool   $echo                 Whether to echo the generated markup. False to return the markup instead
 *                                        of echoing it. Default true.
 * }
 */
function multisite_terms_checklist( $post_id = 0, $args = array() ) {
	$defaults = array(
		'taxonomy'             => '',
		'descendants_and_self' => 0,
		'popular_terms'        => false,
		'selected_terms'       => false,
		'walker'               => null,
		'checked_ontop'        => true,
		'echo'                 => true,
		'blog_id'              => get_current_blog_id(),
	);

	/**
	 * Filters the multisite taxonomy multisite terms checklist arguments.
	 *
	 * @param array $args    An array of arguments.
	 * @param int   $post_id The post ID.
	 */
	$params = apply_filters( 'multisite_terms_checklist_args', $args, $post_id );

	$r = wp_parse_args( $params, $defaults );

	if ( empty( $r['walker'] ) || ! ( $r['walker'] instanceof Walker ) ) {
		$walker = new Walker_Hierarchical_Multisite_Taxonomy_Checklist();
	} else {
		$walker = $r['walker'];
	}

	$multisite_taxonomy   = $r['taxonomy'];
	$descendants_and_self = (int) $r['descendants_and_self'];

	$args = array( 'taxonomy' => $multisite_taxonomy );

	$multi_tax        = get_multisite_taxonomy( $multisite_taxonomy );
	$args['disabled'] = ! current_user_can( $multi_tax->cap->assign_multisite_terms );

	$args['list_only'] = ! empty( $r['list_only'] );

	if ( is_array( $r['selected_terms'] ) ) {
		$args['selected_terms'] = $r['selected_terms'];
	} elseif ( $post_id ) {
		$args['selected_terms'] = get_object_multisite_terms( $post_id, $multisite_taxonomy, $r['blog_id'], array_merge( $args, array( 'fields' => 'ids' ) ) );
	} else {
		$args['selected_terms'] = array();
	}

	if ( is_array( $r['popular_terms'] ) ) {
		$args['popular_terms'] = $r['popular_terms'];
	} else {
		$args['popular_terms'] = get_multisite_terms(
			array(
				'taxonomy'     => $multisite_taxonomy,
				'fields'       => 'ids',
				'orderby'      => 'count',
				'order'        => 'DESC',
				'number'       => 10,
				'hierarchical' => false,
			)
		);
	}
	if ( $descendants_and_self ) {
		$descendants_args = array(
			'taxonomy'     => $multisite_taxonomy,
			'child_of'     => $descendants_and_self,
			'hierarchical' => 0,
			'hide_empty'   => 0,
		);
		$multisite_terms  = get_multisite_terms( $descendants_args );
		$self             = get_multisite_term( $descendants_and_self, $multisite_taxonomy );
		array_unshift( $multisite_terms, $self );
	} else {
		$multisite_terms = (array) get_multisite_terms(
			array(
				'taxonomy' => $multisite_taxonomy,
				'get'      => 'all',
			)
		);
	}

	$output = '';

	if ( $r['checked_ontop'] ) {
		// Post process $multisite_terms rather than adding an exclude to the get_multisite_terms() query to keep the query the same across all posts (for any query cache).
		$checked_terms = array();
		$keys          = array_keys( $multisite_terms );

		foreach ( $keys as $k ) {
			if ( in_array( $multisite_terms[ $k ]->multisite_term_id, $args['selected_terms'], true ) ) {
				$checked_terms[] = $multisite_terms[ $k ];
				unset( $multisite_terms[ $k ] );
			}
		}

		// Put checked cats on top.
		$output .= call_user_func_array( array( $walker, 'walk' ), array( $checked_terms, 0, $args ) );
	}
	// Then the rest of them.
	$output .= call_user_func_array( array( $walker, 'walk' ), array( $multisite_terms, 0, $args ) );

	if ( $r['echo'] ) {
		echo $output; // phpcs:ignore WordPress.Security.EscapeOutput
	}

	return $output;
}

/**
 * Display or retrieve the HTML dropdown list of multisite taxonomies.
 *
 * The 'hierarchical' argument, which is disabled by default, will override the
 * depth argument, unless it is true. When the argument is false, it will
 * display all of the multisite taxonomies. When it is enabled it will use the value in
 * the 'depth' argument.
 *
 * @param string|array $args {
 *     Optional. Array or string of arguments to generate a multisite taxonomies drop-down element. See Multisite_Term_Query::__construct()
 *     for information on additional accepted arguments.
 *
 *     @type string       $show_option_all   Text to display for showing all multisite taxonomies. Default empty.
 *     @type string       $show_option_none  Text to display for showing no multisite taxonomies. Default empty.
 *     @type string       $option_none_value Value to use when no category is selected. Default empty.
 *     @type string       $orderby           Which column to use for ordering multisite taxonomies. See get_multisite_terms() for a list
 *                                           of accepted values. Default 'id' (multisite_term_id).
 *     @type bool         $pad_counts        See get_multisite_terms() for an argument description. Default false.
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
 *                                           of the option elements. Accepts any valid term field: 'multisite_term_id', 'name',
 *                                           'slug', 'term_group', 'multisite_term_multisite_taxonomy_id', 'taxonomy', 'description',
 *                                           'parent', 'count'. Default 'multisite_term_id'.
 *     @type string|array $taxonomy          Name of the category or categories to retrieve. Default 'category'.
 *     @type bool         $hide_if_empty     True to skip generating markup if no categories are found.
 *                                           Default false (create select element even if no categories are found).
 *     @type bool         $required          Whether the `<select>` element should have the HTML5 'required' attribute.
 *                                           Default false.
 * }
 * @return string HTML content only if 'echo' argument is 0.
 */
function dropdown_multisite_taxonomy( $args = '' ) {
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
		'value_field'       => 'multisite_term_id',
		'required'          => false,
	);

	$defaults['selected'] = ( is_multitaxo_plugin() ) ? get_query_var( 'taxonomy' ) : 0;

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

	// Avoid clashes with the 'name' param of get_multisite_terms().
	$get_multisite_terms_args = $r;
	unset( $get_multisite_terms_args['name'] );
	$multisite_terms = get_multisite_terms( $get_multisite_terms_args );
	$name            = esc_attr( $r['name'] );
	$class           = esc_attr( $r['class'] );
	$id              = $r['id'] ? esc_attr( $r['id'] ) : $name;
	$required        = $r['required'] ? 'required' : '';

	if ( ! $r['hide_if_empty'] || ! empty( $multisite_terms ) ) {
		$output = "<select $required name='$name' id='$id' class='$class' $tab_index_attribute>\n";
	} else {
		$output = '';
	}
	if ( empty( $multisite_terms ) && ! $r['hide_if_empty'] && ! empty( $r['show_option_none'] ) ) {

		/**
		 * Filters a multisite taxonomy drop-down display element.
		 *
		 * A variety of multisite taxonomy drop-down display elements can be modified
		 * just prior to display via this filter. Filterable arguments include
		 * 'show_option_none', 'show_option_all', and various forms of the
		 * multisite term name.
		 *
		 * @param string       $element  Multisite taxonomy name.
		 * @param Multisite_Term|null $multisite_term The multisite term object, or null if there's no corresponding multisite term.
		 */
		$show_option_none = apply_filters( 'list_multisite_taxonomy', $r['show_option_none'], null );
		$output          .= "\t<option value='" . esc_attr( $option_none_value ) . "' selected='selected'>$show_option_none</option>\n";
	}

	if ( is_array( $multisite_terms ) && ! empty( $multisite_terms ) ) {

		if ( $r['show_option_all'] ) {
			$show_option_all = apply_filters( 'list_multisite_taxonomy', $r['show_option_all'], null );
			$selected        = ( '0' === strval( $r['selected'] ) ) ? " selected='selected'" : '';
			$output         .= "\t<option value='0'$selected>$show_option_all</option>\n";
		}

		if ( $r['show_option_none'] ) {
			$show_option_none = apply_filters( 'list_multisite_taxonomy', $r['show_option_none'], null );
			$selected         = selected( $option_none_value, $r['selected'], false );
			$output          .= "\t<option value='" . esc_attr( $option_none_value ) . "'$selected>$show_option_none</option>\n";
		}

		if ( $r['hierarchical'] ) {
			$depth = $r['depth'];  // Walk the full depth.
		} else {
			$depth = -1; // Flat.
		}
		$output .= walk_hierarchical_multisite_taxonomy_dropdown_tree( $multisite_terms, $depth, $r );
	}

	if ( ! $r['hide_if_empty'] || ! empty( $multisite_terms ) ) {
		$output .= "</select>\n";
	}
	/**
	 * Filters the multisite taxonomy drop-down output.
	 *
	 * @param string $output HTML output.
	 * @param array  $r      Arguments used to build the drop-down.
	 */
	$output = apply_filters( 'dropdown_multisite_taxonomies', $output, $r );

	if ( $r['echo'] ) {

		$dropdown_allowed_html = array(
			'select' => array(
				'class' => array(),
				'id'    => array(),
				'name'  => array(),
			),
			'option' => array(
				'class'    => array(),
				'id'       => array(),
				'name'     => array(),
				'value'    => array(),
				'type'     => array(),
				'selected' => array(),
			),
		);

		echo wp_kses( $output, $dropdown_allowed_html );
	}
	return $output;
}

/**
 * Retrieve a list of the most popular multisite terms from the specified multisite taxonomy.
 *
 * If the $echo argument is true then the elements for a list of checkbox
 * `<input>` elements labelled with the names of the selected multisite terms is output.
 * If the $post_ID global isn't empty then the multisite terms associated with that
 * post will be marked as checked.
 *
 * @param string $multisite_taxonomy Multisite taxonomy to retrieve multisite terms from.
 * @param int    $default Not used.
 * @param int    $number Number of multisite terms to retrieve. Defaults to 10.
 * @param bool   $echo Optionally output the list as well. Defaults to true.
 * @return array List of popular multisite term IDs.
 */
function popular_multisite_terms_checklist( $multisite_taxonomy, $default = 0, $number = 10, $echo = true ) {
	$post = get_post();

	$blog_id = get_current_blog_id();

	if ( $post && $post->ID ) {
		$checked_terms = get_object_multisite_terms( $post->ID, $multisite_taxonomy, $blog_id, array( 'fields' => 'ids' ) );
	} else {
		$checked_terms = array();
	}

	$terms = get_multisite_terms(
		array(
			'orderby'      => 'count',
			'order'        => 'DESC',
			'number'       => $number,
			'hierarchical' => false,
			'taxonomy'     => $multisite_taxonomy,
			'hide_empty'   => false,
		)
	);

	$tax = get_multisite_taxonomy( $multisite_taxonomy );

	$popular_ids = array();
	foreach ( (array) $terms as $term ) {
		$popular_ids[] = $term->multisite_term_id;
		if ( ! $echo ) { // Hack for Ajax use.
			continue;
		}
		$id      = "popular-$multisite_taxonomy-$term->id";
		$checked = in_array( $term->id, $checked_terms, true ) ? 'checked="checked"' : '';
		?>

		<li id="<?php echo esc_attr( $id ); ?>" class="popular-multisite-taxonomy">
			<label class="selectit">
				<input id="in-<?php echo esc_attr( $id ); ?>" type="checkbox" <?php echo $checked; // phpcs:ignore WordPress.Security.EscapeOutput ?> value="<?php echo (int) $term->id; ?>" <?php disabled( ! current_user_can( $tax->cap->assign_multisite_terms ) ); ?> />
				<?php
				/** This filter is documented in wp-includes/category-template.php */
				echo esc_html( apply_filters( 'the_multisite_taxonomy', $term->name ) );
				?>
			</label>
		</li>

		<?php
	}
	return $popular_ids;
}

/**
 * Display or retrieve the HTML list of multisite taxonomies.
 *
 * @param string|array $args {
 *     Array of optional arguments.
 *
 *     @type int          $child_of              Multisite term ID to retrieve child multisite terms of. See get_multisite_terms(). Default 0.
 *     @type int|array    $current_multisite_taxonomy      ID of multisite taxonomy, or array of IDs of multisite taxonomies, that should get the
 *                                               'current-cat' class. Default 0.
 *     @type int          $depth                 Multisite Taxonomy depth. Used for tab indentation. Default 0.
 *     @type bool|int     $echo                  True to echo markup, false to return it. Default 1.
 *     @type array|string $exclude               Array or comma/space-separated string of multisite term IDs to exclude.
 *                                               If `$hierarchical` is true, descendants of `$exclude` multisite terms will also
 *                                               be excluded; see `$exclude_tree`. See get_multisite_terms().
 *                                               Default empty string.
 *     @type array|string $exclude_tree          Array or comma/space-separated string of multisite term IDs to exclude, along
 *                                               with their descendants. See get_multisite_terms(). Default empty string.
 *     @type string       $feed                  Text to use for the feed link. Default 'Feed for all posts filed
 *                                               under [taxonomy name]'.
 *     @type string       $feed_image            URL of an image to use for the feed link. Default empty string.
 *     @type string       $feed_type             Feed type. Used to build feed link. See get_multisite_term_feed_link().
 *                                               Default empty string (default feed).
 *     @type bool|int     $hide_empty            Whether to hide multisite taxonomies that don't have any posts attached to them.
 *                                               Default 1.
 *     @type bool         $hide_title_if_empty   Whether to hide the `$title_li` element if there are no multisite terms in
 *                                               the list. Default false (title will always be shown).
 *     @type bool         $hierarchical          Whether to include multisite terms that have non-empty descendants.
 *                                               See get_multisite_terms(). Default true.
 *     @type string       $order                 Which direction to order  multisite taxonomies. Accepts 'ASC' or 'DESC'.
 *                                               Default 'ASC'.
 *     @type string       $orderby               The column to use for ordering multisite taxonomies. Default 'name'.
 *     @type string       $separator             Separator between links. Default '<br />'.
 *     @type bool|int     $show_count            Whether to show how many posts are in the multisite taxonomy. Default 0.
 *     @type string       $show_option_all       Text to display for showing all multisite taxonomies. Default empty string.
 *     @type string       $show_option_none      Text to display for the 'no multisite taxonomy' option.
 *                                               Default 'No multisite taxonomy'.
 *     @type string       $style                 The style used to display the  multisite taxonomies list. If 'list', multisite taxonomies
 *                                               will be output as an unordered list. If left empty or another value,
 *                                               multisite taxonomies will be output separated by `<br>` tags. Default 'list'.
 *     @type string       $taxonomy              Multisite taxonomy name.
 *     @type string       $title_li              Text to use for the list title `<li>` element. Pass an empty string
 *                                               to disable. Default 'Multisite taxonomies'.
 *     @type bool|int     $use_desc_for_title    Whether to use the multisite taxonomy description as the title attribute.
 *                                               Default 1.
 * }
 * @return false|string HTML content only if 'echo' argument is 0.
 */
function list_multisite_taxonomies( $args = '' ) {
	$defaults = array(
		'child_of'                   => 0,
		'current_multisite_taxonomy' => 0,
		'depth'                      => 0,
		'echo'                       => 1,
		'exclude'                    => '',
		'exclude_tree'               => '',
		'feed'                       => '',
		'feed_image'                 => '',
		'feed_type'                  => '',
		'hide_empty'                 => 1,
		'hide_title_if_empty'        => false,
		'hierarchical'               => true,
		'order'                      => 'ASC',
		'orderby'                    => 'name',
		'separator'                  => '<br />',
		'show_count'                 => 0,
		'show_option_all'            => '',
		'show_option_none'           => __( 'No multisite hierarchical taxonomy', 'multitaxo' ),
		'style'                      => 'list',
		'taxonomy'                   => '',
		'title_li'                   => __( 'Multisite hierarchical Taxonomy', 'multitaxo' ),
		'use_desc_for_title'         => 1,
	);

	$r = wp_parse_args( $args, $defaults );

	if ( ! isset( $r['pad_counts'] ) && $r['show_count'] && $r['hierarchical'] ) {
		$r['pad_counts'] = true;
	}

	// Descendants of exclusions should be excluded too.
	if ( true === $r['hierarchical'] ) {
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
		$r['class'] = $r['taxonomy'];
	}

	if ( ! multisite_taxonomy_exists( $r['taxonomy'] ) ) {
		return false;
	}

	$show_option_all  = $r['show_option_all'];
	$show_option_none = $r['show_option_none'];

	$multisite_taxonomies = get_multisite_taxonomies( $r );

	$output = '';
	if ( $r['title_li'] && 'list' === $r['style'] && ( ! empty( $multisite_taxonomies ) || ! $r['hide_title_if_empty'] ) ) {
		$output = '<li class="' . esc_attr( $r['class'] ) . '">' . $r['title_li'] . '<ul>';
	}
	if ( empty( $multisite_taxonomies ) ) {
		if ( ! empty( $show_option_none ) ) {
			if ( 'list' === $r['style'] ) {
				$output .= '<li class="tax-item-none">' . $show_option_none . '</li>';
			} else {
				$output .= $show_option_none;
			}
		}
	} else {
		if ( ! empty( $show_option_all ) ) {

			$posts_page = '';

			// For taxonomies that belong only to custom post types, point to a valid archive.
			$multisite_taxonomy_object = get_multisite_taxonomy( $r['taxonomy'] );
			if ( ! in_array( 'post', $multisite_taxonomy_object->object_type, true ) && ! in_array( 'page', $multisite_taxonomy_object->object_type, true ) ) {
				foreach ( $multisite_taxonomy_object->object_type as $object_type ) {
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
				if ( 'page' === get_option( 'show_on_front' ) && get_option( 'page_for_posts' ) ) {
					$posts_page = get_permalink( get_option( 'page_for_posts' ) );
				} else {
					$posts_page = home_url( '/' );
				}
			}

			$posts_page = esc_url( $posts_page );
			if ( 'list' === $r['style'] ) {
				$output .= "<li class='tax-item-all'><a href='$posts_page'>$show_option_all</a></li>";
			} else {
				$output .= "<a href='$posts_page'>$show_option_all</a>";
			}
		}

		if ( empty( $r['current_multisite_taxonomy'] ) && ( is_category() || is_tax() || is_tag() ) ) {
			$current_term_object = get_queried_object();
			if ( $current_term_object && $r['taxonomy'] === $current_term_object->taxonomy ) {
				$r['current_multisite_taxonomy'] = get_queried_object_id();
			}
		}

		if ( $r['hierarchical'] ) {
			$depth = $r['depth'];
		} else {
			$depth = -1; // Flat.
		}
		$output .= walk_hierarchical_multisite_taxonomy_tree( $multisite_taxonomies, $depth, $r );
	}

	if ( $r['title_li'] && 'list' === $r['style'] && ( ! empty( $multisite_taxonomies ) || ! $r['hide_title_if_empty'] ) ) {
		$output .= '</ul></li>';
	}

	/**
	 * Filters the HTML output of a multisite taxonomy list.
	 *
	 * @since 2.1.0
	 *
	 * @param string $output HTML output.
	 * @param array  $args   An array of multisite taxonomy-listing arguments.
	 */
	$html = apply_filters( 'list_multisite_taxonomies', $output, $args );

	if ( $r['echo'] ) {
		echo $html; // phpcs:ignore WordPress.Security.EscapeOutput
	} else {
		return $html;
	}
}

/**
 * Display multisite term cloud.
 *
 * The text size is set by the 'smallest' and 'largest' arguments, which will
 * use the 'unit' argument value for the CSS text size unit. The 'format'
 * argument can be 'flat' (default), 'list', or 'array'. The flat value for the
 * 'format' argument will separate multisite terms with spaces. The list value for the
 * 'format' argument will format the multisite terms in a UL HTML list. The array value for
 * the 'format' argument will return in PHP array type format.
 *
 * The 'orderby' argument will accept 'name' or 'count' and defaults to 'name'.
 * The 'order' is the direction to sort, defaults to 'ASC' and can be 'DESC'.
 *
 * The 'number' argument is how many multisite terms to return. By default, the limit will
 * be to return the top 45 multisite terms in the multisite term cloud list.
 *
 * The 'topic_count_text' argument is a nooped plural from _n_noop() to generate the
 * text for the multisite term link count.
 *
 * The 'topic_count_text_callback' argument is a function, which given the count
 * of the posts with that multisite term returns a text for the multisite term link count.
 *
 * The 'post_type' argument is used only when 'link' is set to 'edit'. It determines the post_type
 * passed to edit.php for the popular multisite terms edit links.
 *
 * The 'exclude' and 'include' arguments are used for the get_multisite_terms() function. Only one
 * should be used, because only one will be used and the other ignored, if they are both set.
 *
 * @since 2.3.0
 * @since 4.8.0 Added the `show_count` argument.
 *
 * @param array|string|null $args Optional. Override default arguments.
 * @return void|array Generated multisite term cloud, only if no failures and 'array' is set for the 'format' argument.
 *                    Otherwise, this function outputs the multisite term cloud.
 */
function multisite_term_cloud( $args = '' ) {
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
		'taxonomy'   => '',
		'post_type'  => '',
		'echo'       => true,
		'show_count' => 0,
	);
	$args     = wp_parse_args( $args, $defaults );

	$multisite_terms = get_multisite_terms(
		$args['taxonomy'],
		array_merge(
			$args,
			array(
				'orderby' => 'count',
				'order'   => 'DESC',
			)
		)
	); // Always query top multisite terms.

	if ( empty( $multisite_terms ) || is_wp_error( $multisite_terms ) ) {
		return;
	}

	foreach ( $multisite_terms as $key => $multisite_term ) {
		if ( 'edit' === $args['link'] ) {
			$link = get_edit_multisite_term_link( $multisite_term->multisite_term_id, $multisite_term->taxonomy, $args['post_type'] );
		} else {
			$link = get_multisite_term_link( intval( $multisite_term->multisite_term_id ), $multisite_term->taxonomy );
		}
		if ( is_wp_error( $link ) ) {
			return;
		}

		$multisite_terms[ $key ]->link = $link;
		$multisite_terms[ $key ]->id   = $multisite_term->multisite_term_id;
	}

	$return = generate_multisite_term_cloud( $multisite_terms, $args ); // Here's where those top multisite terms get sorted according to $args .

	/**
	 * Filters the multisite term cloud output.
	 *
	 * @since 2.3.0
	 *
	 * @param string $return HTML output of the multisite term cloud.
	 * @param array  $args   An array of multisite term cloud arguments.
	 */
	$return = apply_filters( 'multisite_term_cloud', $return, $args );

	if ( 'array' === $args['format'] || empty( $args['echo'] ) ) {
		return $return;
	}

	echo $return; // phpcs:ignore WordPress.Security.EscapeOutput
}

/**
 * Generates a none hierachical multisite terms cloud (heatmap) from provided data.
 *
 * @param array        $multisite_terms List of none hierachical multisite terms.
 * @param string|array $args {
 *     Optional. Array of string of arguments for generating a none hierachical multisite terms cloud.
 *
 *     @type int      $smallest                   Smallest font size used to display none hierachical multisite terms. Paired
 *                                                with the value of `$unit`, to determine CSS text
 *                                                size unit. Default 8 (pt).
 *     @type int      $largest                    Largest font size used to display none hierachical multisite terms. Paired
 *                                                with the value of `$unit`, to determine CSS text
 *                                                size unit. Default 22 (pt).
 *     @type string   $unit                       CSS text size unit to use with the `$smallest`
 *                                                and `$largest` values. Accepts any valid CSS text
 *                                                size unit. Default 'pt'.
 *     @type int      $number                     The number of none hierachical multisite terms to return. Accepts any
 *                                                positive integer or zero to return all.
 *                                                Default 0.
 *     @type string   $format                     Format to display the multisite term cloud in. Accepts 'flat'
 *                                                (none hierachical multisite terms separated with spaces), 'list' (none hierachical multisite terms displayed
 *                                                in an unordered list), or 'array' (returns an array).
 *                                                Default 'flat'.
 *     @type string   $separator                  HTML or text to separate the none hierachical multisite terms. Default "\n" (newline).
 *     @type string   $orderby                    Value to order none hierachical multisite terms by. Accepts 'name' or 'count'.
 *                                                Default 'name'. The {@see 'tag_cloud_sort'} filter
 *                                                can also affect how none hierachical multisite terms are sorted.
 *     @type string   $order                      How to order the none hierachical multisite terms. Accepts 'ASC' (ascending),
 *                                                'DESC' (descending), or 'RAND' (random). Default 'ASC'.
 *     @type int|bool $filter                     Whether to enable filtering of the final output
 *                                                via {@see 'generate_multisite_term_cloud'}. Default 1|true.
 *     @type string   $topic_count_text           Nooped plural text from _n_noop() to supply to
 *                                                multisite term counts. Default null.
 *     @type callable $topic_count_text_callback  Callback used to generate nooped plural text for
 *                                                multisite term counts based on the count. Default null.
 *     @type callable $topic_count_scale_callback Callback used to determine the multisite term count scaling
 *                                                value. Default default_topic_count_scale().
 *     @type bool|int $show_count                 Whether to display the multisite term counts. Default 0. Accepts
 *                                                0, 1, or their bool equivalents.
 * }
 * @return string|array None hierachical multisite terms cloud as a string or an array, depending on 'format' argument.
 */
function generate_multisite_term_cloud( $multisite_terms, $args = '' ) {
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

	if ( empty( $multisite_terms ) ) {
		return $return;
	}

	// Juggle topic counts.
	if ( isset( $args['topic_count_text'] ) ) {
		// First look for nooped plural support via topic_count_text.
		$translate_nooped_plural = $args['topic_count_text'];
	} elseif ( ! empty( $args['topic_count_text_callback'] ) ) {
		// Look for the alternative callback style. Ignore the previous default.
		if ( 'default_topic_count_text' === $args['topic_count_text_callback'] ) {
			$translate_nooped_plural = _n_noop( '%s term', '%s terms', 'multitaxo' ); // phpcs:ignore WordPress.WP.I18n
		} else {
			$translate_nooped_plural = false;
		}
	} elseif ( isset( $args['single_text'] ) && isset( $args['multiple_text'] ) ) {
		// If no callback exists, look for the old-style single_text and multiple_text arguments.
		$translate_nooped_plural = _n_noop( $args['single_text'], $args['multiple_text'], 'multitaxo' ); // phpcs:ignore WordPress.WP.I18n
	} else {
		// This is the default for when no callback, plural, or argument is passed in.
		$translate_nooped_plural = _n_noop( '%s multisite term', '%s multisite terms', 'multitaxo' ); // phpcs:ignore WordPress.WP.I18n
	}

	/**
	 * Filters how the items in a multisite term cloud are sorted.
	 *
	 * @since 2.8.0
	 *
	 * @param array $multisite_terms Ordered array of terms.
	 * @param array $args An array of multisite term cloud arguments.
	 */
	$multisite_terms_sorted = apply_filters( 'multisite_term_cloud_sort', $multisite_terms, $args );
	if ( empty( $multisite_terms_sorted ) ) {
		return $return;
	}

	if ( $multisite_terms_sorted !== $multisite_terms ) {
		$multisite_terms = $multisite_terms_sorted;
		unset( $multisite_terms_sorted );
	} else {
		if ( 'RAND' === $args['order'] ) {
			shuffle( $multisite_terms );
		} else {
			// SQL cannot save you; this is a second (potentially different) sort on a subset of data.
			if ( 'name' === $args['orderby'] ) {
				uasort( $multisite_terms, '_wp_object_name_sort_cb' );
			} else {
				uasort( $multisite_terms, '_wp_object_count_sort_cb' );
			}

			if ( 'DESC' === $args['order'] ) {
				$multisite_terms = array_reverse( $multisite_terms, true );
			}
		}
	}

	if ( $args['number'] > 0 ) {
		$multisite_terms = array_slice( $multisite_terms, 0, $args['number'] );
	}

	$counts      = array();
	$real_counts = array(); // For the alt multisite term.
	foreach ( (array) $multisite_terms as $key => $multisite_term ) {
		$real_counts[ $key ] = $multisite_term->count;
		$counts[ $key ]      = call_user_func( $args['topic_count_scale_callback'], $multisite_term->count );
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
	 * Determine whether to output an 'aria-label' attribute with the multisite term name and count.
	 * When none hierachical multisite terms have a different font size, they visually convey an important information
	 * that should be available to assistive technologies too. On the other hand, sometimes
	 * themes set up the multisite term cloud to display all none hierachical multisite terms with the same font size (setting
	 * the 'smallest' and 'largest' arguments to the same value).
	 * In order to always serve the same content to all users, the 'aria-label' gets printed out:
	 * - when none hierachical multisite terms have a different size
	 * - when the multisite term count is displayed (for example when users check the checkbox in the
	 *   Multisite Term Cloud widget), regardless of the none hierachical multisite terms font size
	 */
	if ( $args['show_count'] || 0 !== $font_spread ) {
		$aria_label = true;
	}

	// Assemble the data that will be used to generate the multisite term cloud markup.
	$multisite_terms_data = array();
	foreach ( $multisite_terms as $key => $multisite_term ) {
		$multisite_term_id = isset( $multisite_term->id ) ? $multisite_term->id : $key;

		$count      = $counts[ $key ];
		$real_count = $real_counts[ $key ];

		if ( $translate_nooped_plural ) {
			$formatted_count = sprintf( translate_nooped_plural( $translate_nooped_plural, $real_count ), number_format_i18n( $real_count ) );
		} else {
			$formatted_count = call_user_func( $args['topic_count_text_callback'], $real_count, $multisite_term, $args );
		}

		$multisite_terms_data[] = array(
			'id'              => $multisite_term_id,
			'url'             => '#' !== $multisite_term->link ? $multisite_term->link : '#',
			'role'            => '#' !== $multisite_term->link ? '' : ' role="button"',
			'name'            => $multisite_term->name,
			'formatted_count' => $formatted_count,
			'slug'            => $multisite_term->slug,
			'real_count'      => $real_count,
			'class'           => 'multisite-term-cloud-link multisite-term-link-' . $multisite_term_id,
			'font_size'       => $args['smallest'] + ( $count - $min_count ) * $font_step,
			'aria_label'      => $aria_label ? sprintf( ' aria-label="%1$s (%2$s)"', esc_attr( $multisite_term->name ), esc_attr( $formatted_count ) ) : '',
			'show_count'      => $args['show_count'] ? '<span class="multisite-term-link-count"> (' . $real_count . ')</span>' : '',
		);
	}

	/**
	 * Filters the data used to generate the multisite term cloud.
	 *
	 * @since 4.3.0
	 *
	 * @param array $multisite_terms_data An array of multisite term data for multisite term used to generate the multisite term cloud.
	 */
	$multisite_terms_data = apply_filters( 'generate_multisite_term_cloud_data', $multisite_terms_data );

	$a = array();

	// Generate the output links array.
	foreach ( $multisite_terms_data as $key => $multisite_term_data ) {
		$class = $multisite_term_data['class'] . ' multisite-term-link-position-' . ( $key + 1 );
		$a[]   = sprintf(
			'<a href="%1$s"%2$s class="%3$s" style="font-size: %4$s;"%5$s>%6$s%7$s</a>',
			esc_url( $multisite_term_data['url'] ),
			$multisite_term_data['role'],
			esc_attr( $class ),
			esc_attr( str_replace( ',', '.', $multisite_term_data['font_size'] ) . $args['unit'] ),
			$multisite_term_data['aria_label'],
			esc_html( $multisite_term_data['name'] ),
			$multisite_term_data['show_count']
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
			$return  = "<ul class='wp-multisite-term-cloud' role='list'>\n\t<li>";
			$return .= join( "</li>\n\t<li>", $a );
			$return .= "</li>\n</ul>\n";
			break;
		default:
			$return = join( $args['separator'], $a );
			break;
	}

	if ( $args['filter'] ) {
		/**
		 * Filters the generated output of a multisite term cloud.
		 *
		 * The filter is only evaluated if a true value is passed
		 * to the $filter argument in generate_multisite_term_cloud().
		 *
		 * @see generate_multisite_term_cloud()
		 *
		 * @param array|string $return String containing the generated HTML multisite term cloud output
		 *                             or an array of multisite term links if the 'format' argument
		 *                             equals 'array'.
		 * @param array        $multisite_terms   An array of multisite terms used in the multisite term cloud.
		 * @param array        $args   An array of generate_multisite_term_cloud() arguments.
		 */
		return apply_filters( 'generate_multisite_term_cloud', $return, $multisite_terms, $args );
	} else {
		return $return;
	}
}

/**
 * Retrieve HTML list content for hierarchical multisite taxonomy list.
 *
 * @uses Walker_Multisite_Hierarchical_Taxonomy to create HTML list content.
 * @see Walker_Multisite_Hierarchical_Taxonomy::walk() for parameters and return description.
 * @return string
 */
function walk_hierarchical_multisite_taxonomy_tree() {
	$args = func_get_args();
	// the user's options are the third parameter.
	if ( empty( $args[2]['walker'] ) || ! ( $args[2]['walker'] instanceof Walker ) ) {
		$walker = new Walker_Multisite_Hierarchical_Taxonomy();
	} else {
		$walker = $args[2]['walker'];
	}
	return call_user_func_array( array( $walker, 'walk' ), $args );
}

/**
 * Retrieve HTML dropdown (select) content for hierarchical multisite taxonomy list.
 *
 * @uses Walker_Hierarchical_Multisite_Taxonomy_Dropdown to create HTML dropdown content.
 * @see Walker_Hierarchical_Multisite_Taxonomy_Dropdown::walk() for parameters and return description.
 * @return string
 */
function walk_hierarchical_multisite_taxonomy_dropdown_tree() {
	$args = func_get_args();
	// the user's options are the third parameter.
	if ( empty( $args[2]['walker'] ) || ! ( $args[2]['walker'] instanceof Walker ) ) {
		$walker = new Walker_Hierarchical_Multisite_Taxonomy_Dropdown();
	} else {
		$walker = $args[2]['walker'];
	}
	return call_user_func_array( array( $walker, 'walk' ), $args );
}

/**
 * Retrieve multisite terms description.
 *
 * @param int    $multisite_term Optional. Multisite Term ID. Will use global multisite term ID by default.
 * @param string $multisite_taxonomy Optional multisite taxonomy name. Defaults to 'post_tag'.
 * @return string Multisite Term description, available.
 */
function multisite_term_description( $multisite_term = 0, $multisite_taxonomy = 'post_tag' ) {
	if ( ! $multisite_term && ( is_multitaxo_plugin() ) ) {
		$multisite_term = get_queried_object();
		if ( $multisite_term ) {
			$multisite_taxonomy = $multisite_term->multisite_taxonomy;
			$multisite_term     = $multisite_term->multisite_term_id;
		}
	}
	$description = get_multisite_term_field( 'description', $multisite_term, $multisite_taxonomy );
	return is_wp_error( $description ) ? '' : $description;
}

/**
 * Retrieve the multisite terms of the multisite taxonomy that are attached to the post.
 *
 * @param int|object $post Post ID or object.
 * @param string     $multisite_taxonomy Multisite Taxonomy name.
 * @return array|false|WP_Error Array of Multisite_Term objects on success, false if there are no multisite terms
 *                              or the post does not exist, WP_Error on failure.
 */
function get_the_multisite_terms( $post, $multisite_taxonomy ) {
	$post = get_post( $post );
	if ( is_null( $post ) ) {
		return false;
	}

	$multisite_terms = get_object_multisite_term_cache( $post->ID, $multisite_taxonomy );

	if ( false === $multisite_terms ) {
		$multisite_terms = get_object_multisite_terms( $post->ID, $multisite_taxonomy );
		if ( ! is_wp_error( $multisite_terms ) ) {
			$multisite_term_ids = wp_list_pluck( $multisite_terms, 'multisite_term_id' );
			wp_cache_add( $post->ID, $multisite_term_ids, $multisite_taxonomy . '_relationships' );
		}
	}

	/**
	 * Filters the list of multisite terms attached to the given post.
	 *
	 * @since 3.1.0
	 *
	 * @param array|WP_Error $multisite_terms    List of attached multisite terms, or WP_Error on failure.
	 * @param int            $post_id  Post ID.
	 * @param string         $multisite_taxonomy Name of the taxonomy.
	 */
	$multisite_terms = apply_filters( 'get_the_multisite_terms', $multisite_terms, $post->ID, $multisite_taxonomy );

	if ( empty( $multisite_terms ) ) {
		return false;
	}

	return $multisite_terms;
}

/**
 * Retrieve a post's multisite terms as a list with specified format.
 *
 * @param int    $id Post ID.
 * @param string $multisite_taxonomy Multisite Taxonomy name.
 * @param string $before Optional. Before list.
 * @param string $sep Optional. Separate items using this.
 * @param string $after Optional. After list.
 * @return string|false|WP_Error A list of multisite terms on success, false if there are no terms, WP_Error on failure.
 */
function get_the_multisite_term_list( $id, $multisite_taxonomy, $before = '', $sep = '', $after = '' ) {
	$multisite_terms = get_the_multisite_terms( $id, $multisite_taxonomy );

	if ( is_wp_error( $multisite_terms ) ) {
		return $multisite_terms;
	}

	if ( empty( $multisite_terms ) ) {
		return false;
	}

	$links = array();

	foreach ( $multisite_terms as $multisite_term ) {
		$link = get_multisite_term_link( $multisite_term, $multisite_taxonomy );
		if ( is_wp_error( $link ) ) {
			return $link;
		}
		$links[] = '<a href="' . esc_url( $link ) . '" rel="tag">' . $multisite_term->name . '</a>';
	}

	/**
	 * Filters the multisite term links for a given multisite taxonomy.
	 *
	 * The dynamic portion of the filter name, `$multisite_taxonomy`, refers
	 * to the multisite taxonomy slug.
	 *
	 * @since 2.5.0
	 *
	 * @param array $links An array of multisite term links.
	 */
	$multisite_term_links = apply_filters( "multisite_term_links-{$multisite_taxonomy}", $links ); // phpcs:ignore WordPress.NamingConventions.ValidHookName

	return $before . join( $sep, $multisite_term_links ) . $after;
}

/**
 * Retrieve multisite term parents with separator.
 *
 * @param int          $multisite_term_id  Multisite Term ID.
 * @param string       $multisite_taxonomy Multisite Taxonomy Name.
 * @param string|array $args {
 *     Array of optional arguments.
 *
 *     @type string $format    Use multisite term names or slugs for display. Accepts 'name' or 'slug'.
 *                             Default 'name'.
 *     @type string $separator Separator for between the multisite terms. Default '/'.
 *     @type bool   $link      Whether to format as a link. Default true.
 *     @type bool   $inclusive Include the multisite term to get the parents for. Default true.
 * }
 * @return string|WP_Error A list of multisite term parents on success, WP_Error or empty string on failure.
 */
function get_multisite_term_parents_list( $multisite_term_id, $multisite_taxonomy, $args = array() ) {
	$list           = '';
	$multisite_term = get_multisite_term( $multisite_term_id, $multisite_taxonomy );

	if ( is_wp_error( $multisite_term ) ) {
		return $term;
	}

	if ( ! $multisite_term ) {
		return $list;
	}

	$multisite_term_id = $multisite_term->multisite_term_id;

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

	$parents = get_ancestors( $multisite_term_id, $multisite_taxonomy, 'taxonomy' );

	if ( $args['inclusive'] ) {
		array_unshift( $parents, $multisite_term_id );
	}

	foreach ( array_reverse( $parents ) as $multisite_term_id ) {
		$parent = get_multisite_term( $multisite_term_id, $multisite_taxonomy );
		$name   = ( 'slug' === $args['format'] ) ? $parent->slug : $parent->name;

		if ( $args['link'] ) {
			$list .= '<a href="' . esc_url( get_multisite_term_link( $parent->multisite_term_id, $multisite_taxonomy ) ) . '">' . $name . '</a>' . $args['separator'];
		} else {
			$list .= $name . $args['separator'];
		}
	}

	return $list;
}

/**
 * Display the multisite terms in a list.
 *
 * @param int    $id Post ID.
 * @param string $multisite_taxonomy Multisite taxonomy name.
 * @param string $before Optional. Before list.
 * @param string $sep Optional. Separate items using this.
 * @param string $after Optional. After list.
 * @return false|void False on WordPress error.
 */
function the_multisite_terms( $id, $multisite_taxonomy, $before = '', $sep = ', ', $after = '' ) {
	$multisite_term_list = get_the_multisite_term_list( $id, $multisite_taxonomy, $before, $sep, $after );

	if ( is_wp_error( $multisite_term_list ) ) {
		return false;
	}

	/**
	 * Filters the list of multisite terms to display.
	 *
	 * @param array  $multisite_term_list List of multisite terms to display.
	 * @param string $multisite_taxonomy  The multisite taxonomy name.
	 * @param string $before    String to use before the multisite terms.
	 * @param string $sep       String to use between the multisite terms.
	 * @param string $after     String to use after the multisite terms.
	 */
	echo apply_filters( 'the_multisite_terms', $multisite_term_list, $multisite_taxonomy, $before, $sep, $after ); // phpcs:ignore WordPress.Security.EscapeOutput
}

/**
 * Check if the current post has any of given terms.
 *
 * The given terms are checked against the post's terms' multisite_term_ids, names and slugs.
 * Terms given as integers will only be checked against the post's terms' multisite_term_ids.
 * If no terms are given, determines if post has any terms.
 *
 * @param string|int|array $multisite_term Optional. The multisite term name/multisite_term_id/slug or array of them to check for.
 * @param string           $multisite_taxonomy Multisite Taxonomy name.
 * @param int|object       $post Optional. Post to check instead of the current post.
 * @return bool True if the current post has any of the given none hierachical multisite terms (or any tag, if no tag specified).
 */
function has_multistite_term( $multisite_term = '', $multisite_taxonomy = '', $post = null ) {
	$post = get_post( $post );

	if ( ! $post ) {
		return false;
	}

	$r = is_object_in_multisite_term( $post->ID, $multisite_taxonomy, $multisite_term );
	if ( is_wp_error( $r ) ) {
		return false;
	}

	return $r;
}

/**
 * Is the query for an existing multisite taxonomy archive page?
 *
 * @param mixed $multisite_taxonomy Optional. Multisite Taxonomy slug or slugs.
 * @param mixed $multisite_term     Optional. Multisite Term ID, name, slug or array of Multisite Term IDs, names, and slugs.
 * @return bool True for multisite taxonomy archive pages.
 */
function is_multitaxo_plugin( $multisite_taxonomy = '', $multisite_term = '' ) {
	global $wp_query;
	if ( ! isset( $wp_query ) ) {
		_doing_it_wrong( __FUNCTION__, esc_attr__( 'Conditional query tags do not work before the query is run. Before then, they always return false.', 'multitaxo' ), '3.1.0' );
		return false;
	}
	// TO DO: actually check if it is.
	return true;
}

/**
 * Echo and escape the return value of get_multitaxo_the_ID().
 *
 * @param int $post_id The post ID.
 * @return void
 */
function multitaxo_the_id( $post_id ) {
	echo absint( multitaxo_get_the_id( $post_id ) );
}

/**
 * Return a post ID in the multisite query context.
 * Yes this function is useless, it's here for constistency with other template fumctions.
 *
 * @param int $post_id The post ID.
 * @return int The filtered content.
 */
function multitaxo_get_the_id( $post_id ) {
	return $post_id;
}

/**
 * Echo and escape the return value of multitaxo_get_the_permalink().
 *
 * @param int $permalink The post permalink.
 * @return void
 */
function multitaxo_the_permalink( $permalink ) {
	echo esc_url( multitaxo_get_the_permalink( $permalink ) );
}

/**
 * Filter and return the post permalink in the multisite query context.
 *
 * @param string $permalink The post permalink.
 * @return string The filtered post permalink.
 */
function multitaxo_get_the_permalink( $permalink ) {
	$permalink = apply_filters( 'multitaxo_the_permalink', $permalink );
	return $permalink;
}

/**
 * Echo and escape the return value of multitaxo_get_the_title().
 *
 * @param int $post_title The post title.
 * @return void
 */
function multitaxo_the_title( $post_title ) {
	echo esc_html( multitaxo_get_the_title( $post_title ) );
}

/**
 * Filter and return the post title in the multisite query context.
 *
 * @param string $post_title The post title.
 * @return string The filtered post title.
 */
function multitaxo_get_the_title( $post_title ) {
	$post_title = apply_filters( 'multitaxo_the_title', $post_title );
	return $post_title;
}

/**
 * Echo and escape the return value of multitaxo_get_the_excerpt().
 *
 * @param object $post The post object. We need the post to check if excerpt is set and if not use the content.
 * @return void
 */
function multitaxo_the_excerpt( $post ) {
	echo wp_kses_post( multitaxo_get_the_excerpt( $post ) );
}

/**
 * Filter and return the post excerpt in the multisite query context.
 *
 * @param object $post The post object. We need the post to check if excerpt is set and if not use the content.
 * @return string The filtered post excerpt.
 */
function multitaxo_get_the_excerpt( $post ) {
	// We use the excerpt or the post content if no excerpt was entered.
	if ( empty( $post->post_excerpt ) ) {
		$excerpt = $post->post_content;
	} else {
		$excerpt = $post->post_excerpt;
	}
	// The lenght of the excerpt, 42 words by default but filterable.
	$excerpt_length = 42;
	$excerpt_length = apply_filters( 'multitaxo_excerpt_length', $excerpt_length );
	// The troncate symbol for the excerpt, '&hellip;' by default but filterable.
	$excerpt_more = '&hellip;';
	$excerpt_more = apply_filters( 'multitaxo_excerpt_more', $excerpt_more );
	$excerpt      = wpautop( wp_trim_words( do_shortcode( $excerpt ), $excerpt_length, $excerpt_more ) );
	$excerpt      = apply_filters( 'multitaxo_the_excerpt', $excerpt );
	return $excerpt;
}

/**
 * Return a filtered wp-content folder url.
 *
 * @return string The wp-content folder url.
 */
function multitaxo_content_url() {
	return apply_filters( 'multitaxo_wp_content_url', content_url() );
}

/**
 * Display the multisite post object.
 *
 * @access public
 * @param object $post The multiste post object.
 * @return void Outputs the post thumbnail.
 */
function multitaxo_the_post_thumbnail( $post ) {
	// this isnt right, or we dont have enough data: return.
	if ( ! is_object( $post ) || ! is_array( $post->post_thumbnail ) || empty( $post->post_thumbnail['url'] ) ) {
		return;
	}
	?>
	<div class="page-image-thumbnail">
		<img src="<?php echo esc_url( $post->post_thumbnail['url'] ); ?>" width="<?php echo esc_attr( $post->post_thumbnail['width'] ); ?>" height="<?php echo esc_attr( $post->post_thumbnail['height'] ); ?>" style="width: <?php echo esc_attr( $post->post_thumbnail['width'] ); ?>px; height: <?php echo esc_attr( $post->post_thumbnail['height'] ); ?>px" class="attachment-post-thumbnail wp-post-image" scale="0">
	</div><!-- .page-image-thumbnail -->
	<?php
}
