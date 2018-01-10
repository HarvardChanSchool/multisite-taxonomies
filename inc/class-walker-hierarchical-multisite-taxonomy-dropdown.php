<?php
/**
 * Multisite Taxonomies API: Walker_Hierarchical_Multisite_Taxonomy_Dropdown class
 *
 * @package multitaxo
 */

/**
 * Class used to create an HTML dropdown list of hirechical multisite taxonomies.
 *
 * @see Walker
 */
class Walker_Hierarchical_Multisite_Taxonomy_Dropdown extends Walker {

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
	 * @todo Decouple this
	 * @var array
	 *
	 * @see Walker::$db_fields
	 */
	public $db_fields = array(
		'parent' => 'parent',
		'id'     => 'multisite_term_id',
	);

	/**
	 * Starts the element output.
	 *
	 * @since 2.1.0
	 *
	 * @see Walker::start_el()
	 *
	 * @param string $output   Used to append additional content (passed by reference).
	 * @param object $multisite_taxonomy hierarchical mulitisite taxonomy data object.
	 * @param int    $depth    Depth of mulitisite taxonomy. Used for padding.
	 * @param array  $args     Uses 'selected', 'show_count', and 'value_field' keys, if they exist.
	 * @param int    $id       Optional. ID of the current category. Default 0 (unused).
	 */
	public function start_el( &$output, $multisite_taxonomy, $depth = 0, $args = array(), $id = 0 ) {
		$pad = str_repeat( '&nbsp;', $depth * 3 );

		/** This filter is documented in wp-includes/category-template.php */
		$cat_name = apply_filters( 'list_cats', $multisite_taxonomy->name, $multisite_taxonomy );

		if ( isset( $args['value_field'] ) && isset( $multisite_taxonomy->{$args['value_field']} ) ) {
			$value_field = $args['value_field'];
		} else {
			$value_field = 'multisite_term_id';
		}

		$output .= "\t<option class=\"level-$depth\" value=\"" . esc_attr( $multisite_taxonomy->{$value_field} ) . '"';

		// Type-juggling causes false matches, so we force everything to a string.
		if ( (string) $multisite_taxonomy->{$value_field} === (string) $args['selected'] ) {
			$output .= ' selected="selected"';
		}
		$output .= '>';
		$output .= $pad . $cat_name;
		if ( $args['show_count'] ) {
			$output .= '&nbsp;&nbsp;(' . number_format_i18n( $multisite_taxonomy->count ) . ')';
		}
		$output .= "</option>\n";
	}
}
