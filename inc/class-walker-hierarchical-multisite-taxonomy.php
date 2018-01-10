<?php
/**
 * Multisite Taxonomies API: Walker_Hierarchical_Multisite_Taxonomy class
 *
 * @package multitaxo
 */

/**
 * Class used to create an HTML list of hierarchical multisite taxonomies.
 *
 * @see Walker
 */
class Walker_Hierarchical_Multisite_Taxonomy extends Walker {

	/**
	 * What the class handles.
	 *
	 * @since 2.1.0
	 * @var string
	 *
	 * @see Walker::$tree_type
	 */
	public $tree_type = 'category';

	/**
	 * Database fields to use.
	 *
	 * @since 2.1.0
	 * @var array
	 *
	 * @see Walker::$db_fields
	 * @todo Decouple this
	 */
	public $db_fields = array(
		'parent' => 'parent',
		'id'     => 'multisite_term_id',
	);

	/**
	 * Starts the list before the elements are added.
	 *
	 * @since 2.1.0
	 *
	 * @see Walker::start_lvl()
	 *
	 * @param string $output Used to append additional content. Passed by reference.
	 * @param int    $depth  Optional. Depth of hierarchical multisite taxonomy. Used for tab indentation. Default 0.
	 * @param array  $args   Optional. An array of arguments. Will only append content if style argument
	 *                       value is 'list'. Default empty array.
	 */
	public function start_lvl( &$output, $depth = 0, $args = array() ) {
		if ( 'list' !== $args['style'] ) {
			return;
		}
		$indent  = str_repeat( "\t", $depth );
		$output .= "$indent<ul class='children'>\n";
	}

	/**
	 * Ends the list of after the elements are added.
	 *
	 * @since 2.1.0
	 *
	 * @see Walker::end_lvl()
	 *
	 * @param string $output Used to append additional content. Passed by reference.
	 * @param int    $depth  Optional. Depth of hierarchical multisite taxonomy. Used for tab indentation. Default 0.
	 * @param array  $args   Optional. An array of arguments. Will only append content if style argument
	 *                       value is 'list'. Default empty array.
	 */
	public function end_lvl( &$output, $depth = 0, $args = array() ) {
		if ( 'list' !== $args['style'] ) {
			return;
		}
		$indent  = str_repeat( "\t", $depth );
		$output .= "$indent</ul>\n";
	}

	/**
	 * Starts the element output.
	 *
	 * @since 2.1.0
	 *
	 * @see Walker::start_el()
	 *
	 * @param string $output             Used to append additional content (passed by reference).
	 * @param object $multisite_taxonomy Hierarchical multisite taxonomy data object.
	 * @param int    $depth              Optional. Depth of hierarchical multisite taxonomy in reference to parents. Default 0.
	 * @param array  $args               Optional. An array of arguments. Default empty array.
	 * @param int    $id                 Optional. ID of the current hierarchical multisite taxonomy. Default 0.
	 */
	public function start_el( &$output, $multisite_taxonomy, $depth = 0, $args = array(), $id = 0 ) {
		/** This filter is documented in wp-includes/category-template.php */
		$cat_name = apply_filters(
			'list_cats',
			esc_attr( $multisite_taxonomy->name ),
			$multisite_taxonomy
		);

		// Don't generate an element if the hierarchical multisite taxonomy name is empty.
		if ( ! $cat_name ) {
			return;
		}

		$link = '<a href="' . esc_url( get_multisite_term_link( $multisite_taxonomy ) ) . '" ';
		if ( $args['use_desc_for_title'] && ! empty( $multisite_taxonomy->description ) ) {
			/**
			 * Filters the hierarchical multisite taxonomy description for display.
			 *
			 * @since 1.2.0
			 *
			 * @param string $description hierarchical multisite taxonomy description.
			 * @param object $multisite_taxonomy    Hierarchical multisite taxonomy object.
			 */
			$link .= 'title="' . esc_attr( strip_tags( apply_filters( 'category_description', $multisite_taxonomy->description, $multisite_taxonomy ) ) ) . '"';
		}

		$link .= '>';
		$link .= $cat_name . '</a>';

		if ( ! empty( $args['feed_image'] ) || ! empty( $args['feed'] ) ) {
			$link .= ' ';

			if ( empty( $args['feed_image'] ) ) {
				$link .= '(';
			}

			$link .= '<a href="' . esc_url( get_multisite_term_feed_link( $multisite_taxonomy->multisite_term_id, $multisite_taxonomy->taxonomy, $args['feed_type'] ) ) . '"';

			if ( empty( $args['feed'] ) ) {
				/* translators: %s: multisite term name */
				$alt = ' alt="' . sprintf( __( 'Feed for all posts filed under %s', 'multitaxo' ), $cat_name ) . '"';
			} else {
				$alt   = ' alt="' . $args['feed'] . '"';
				$name  = $args['feed'];
				$link .= empty( $args['title'] ) ? '' : $args['title'];
			}

			$link .= '>';

			if ( empty( $args['feed_image'] ) ) {
				$link .= $name;
			} else {
				$link .= "<img src='" . $args['feed_image'] . "'$alt" . ' />';
			}
			$link .= '</a>';

			if ( empty( $args['feed_image'] ) ) {
				$link .= ')';
			}
		}

		if ( ! empty( $args['show_count'] ) ) {
			$link .= ' (' . number_format_i18n( $multisite_taxonomy->count ) . ')';
		}
		if ( 'list' === $args['style'] ) {
			$output     .= "\t<li";
			$css_classes = array(
				'cat-item',
				'cat-item-' . $multisite_taxonomy->multisite_term_id,
			);

			if ( ! empty( $args['current_category'] ) ) {
				// 'current_category' can be an array, so we use `get_multisite_terms()`.
				$_current_terms = get_multisite_terms(
					$multisite_taxonomy->taxonomy, array(
						'include'    => $args['current_category'],
						'hide_empty' => false,
					)
				);

				foreach ( $_current_terms as $_current_term ) {
					if ( $multisite_taxonomy->multisite_term_id === $_current_term->multisite_term_id ) {
						$css_classes[] = 'current-cat';
					} elseif ( $multisite_taxonomy->multisite_term_id === $_current_term->parent ) {
						$css_classes[] = 'current-cat-parent';
					}
					while ( $_current_term->parent ) {
						if ( $multisite_taxonomy->multisite_term_id === $_current_term->parent ) {
							$css_classes[] = 'current-cat-ancestor';
							break;
						}
						$_current_term = get_multisite_term( $_current_term->parent, $multisite_taxonomy->taxonomy );
					}
				}
			}

			/**
			 * Filters the list of CSS classes to include with each category in the list.
			 *
			 * @since 4.2.0
			 *
			 * @see wp_list_categories()
			 *
			 * @param array  $css_classes An array of CSS classes to be applied to each list item.
			 * @param object $multisite_taxonomy    hierarchical mulitisite taxonomy data object.
			 * @param int    $depth       Depth of page, used for padding.
			 * @param array  $args        An array of wp_list_categories() arguments.
			 */
			$css_classes = implode( ' ', apply_filters( 'category_css_class', $css_classes, $multisite_taxonomy, $depth, $args ) );

			$output .= ' class="' . $css_classes . '"';
			$output .= ">$link\n";
		} elseif ( isset( $args['separator'] ) ) {
			$output .= "\t$link" . $args['separator'] . "\n";
		} else {
			$output .= "\t$link<br />\n";
		}
	}

	/**
	 * Ends the element output, if needed.
	 *
	 * @since 2.1.0
	 *
	 * @see Walker::end_el()
	 *
	 * @param string $output Used to append additional content (passed by reference).
	 * @param object $page   Not used.
	 * @param int    $depth  Optional. Depth of category. Not used.
	 * @param array  $args   Optional. An array of arguments. Only uses 'list' for whether should append
	 *                       to output. See wp_list_categories(). Default empty array.
	 */
	public function end_el( &$output, $page, $depth = 0, $args = array() ) {
		if ( 'list' !== $args['style'] ) {
			return;
		}

		$output .= "</li>\n";
	}

}
