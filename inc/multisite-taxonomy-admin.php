<?php
/**
 * WordPress Taxonomy Administration API.
 *
 * @package WordPress
 * @subpackage Administration
 */

/**
 * Get comma-separated list of terms available to edit for the given post ID.
 *
 * @since 2.8.0
 *
 * @param int    $post_id
 * @param string $taxonomy Optional. The taxonomy for which to retrieve terms. Default 'post_tag'.
 * @return string|bool|WP_Error
 */
function get_terms_to_edit( $post_id, $taxonomy ) {
	$post_id = (int) $post_id;
	if ( !$post_id )
		return false;

	$terms = get_object_term_cache( $post_id, $taxonomy );
	if ( false === $terms ) {
		$terms = wp_get_object_terms( $post_id, $taxonomy );
		wp_cache_add( $post_id, wp_list_pluck( $terms, 'term_id' ), $taxonomy . '_relationships' );
	}

	if ( ! $terms ) {
		return false;
	}
	if ( is_wp_error( $terms ) ) {
		return $terms;
	}
	$term_names = array();
	foreach ( $terms as $term ) {
		$term_names[] = $term->name;
	}

	$terms_to_edit = esc_attr( join( ',', $term_names ) );

	/**
	 * Filters the comma-separated list of terms available to edit.
	 *
	 * @since 2.8.0
	 *
	 * @see get_terms_to_edit()
	 *
	 * @param array  $terms_to_edit An array of terms.
	 * @param string $taxonomy     The taxonomy for which to retrieve terms. Default 'post_tag'.
	 */
	$terms_to_edit = apply_filters( 'terms_to_edit', $terms_to_edit, $taxonomy );

	return $terms_to_edit;
}

/**
 * Add a new term to the database if it does not already exist.
 *
 * @since 2.8.0
 *
 * @param int|string $tag_name
 * @param string $taxonomy Optional. The taxonomy for which to retrieve terms. Default 'post_tag'.
 * @return array|WP_Error
 */
function wp_create_term($tag_name, $taxonomy = 'post_tag') {
	if ( $id = term_exists($tag_name, $taxonomy) )
		return $id;

	return wp_insert_term($tag_name, $taxonomy);
}
