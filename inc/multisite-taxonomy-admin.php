<?php
/**
 * Multisite Taxonomy Administration API.
 *
 * @package multitaxo
 */

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
		$multisite_terms = wp_get_object_multisite_terms( $post_id, $multisite_taxonomy );
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
function wp_create_multisite_term( $multisite_term_name, $multisite_taxonomy ) {
	$id = multisite_term_exists( $multisite_term_name, $multisite_taxonomy );
	if ( is_numeric( $id ) ) {
		return $id;
	}
	return wp_insert_multisite_term( $multisite_term_name, $multisite_taxonomy );
}
