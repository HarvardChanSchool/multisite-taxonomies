<?php
/**
 * Multisite_Term class
 *
 * @package multitaxo
 * @since 0.1
 */

/**
 * Implement the Multisite_Term object.
 */
class Multisite_Term {

	/**
	 * Multisite term ID.
	 *
	 * @access public
	 * @var int
	 */
	public $multisite_term_id;

	/**
	 * The multisite term's name.
	 *
	 * @access public
	 * @var string
	 */
	public $name = '';

	/**
	 * The multisite term's slug.
	 *
	 * @access public
	 * @var string
	 */
	public $slug = '';

	/**
	 * The multisite term's multisite_term_group.
	 *
	 * @access public
	 * @var string
	 */
	public $multisite_term_group = '';

	/**
	 * Multisite Term Multisite Taxonomy ID.
	 *
	 * @access public
	 * @var int
	 */
	public $multisite_term_multisite_taxonomy_id = 0;

	/**
	 * The multisite term's multisite taxonomy name.
	 *
	 * @access public
	 * @var string
	 */
	public $multisite_taxonomy = '';

	/**
	 * The multisite term's description.
	 *
	 * @access public
	 * @var string
	 */
	public $description = '';

	/**
	 * ID of a multisite term's parent multisite term.
	 *
	 * @access public
	 * @var int
	 */
	public $parent = 0;

	/**
	 * Cached object count for this multisite term.
	 *
	 * @access public
	 * @var int
	 */
	public $count = 0;

	/**
	 * Stores the multisite term object's sanitization level.
	 *
	 * Does not correspond to a database field.
	 *
	 * @access public
	 * @var string
	 */
	public $filter = 'raw';

	/**
	 * Retrieve Multisite_Term instance.
	 *
	 * @access public
	 * @static
	 *
	 * @global wpdb $wpdb WordPress database abstraction object.
	 *
	 * @param int    $multisite_term_id  Multisite term ID.
	 * @param string $multisite_taxonomy Optional. Limit matched multisite terms to those matching `$multisite_taxonomy`. Only used for
	 *                         disambiguating potentially shared multisite terms.
	 * @return Multisite_Term|WP_Error|false Term object, if found. WP_Error if `$multisite_term_id` is shared between multiste taxonomies and
	 *                                there's insufficient data to distinguish which multisite term is intended.
	 *                                False for other failures.
	 */
	public static function get_instance( $multisite_term_id, $multisite_taxonomy = null ) {
		global $wpdb;

		$multisite_term_id = (int) $multisite_term_id;
		if ( ! $multisite_term_id ) {
			return false;
		}

		$_multisite_term = wp_cache_get( $multisite_term_id, 'multisite_terms' );

		// If there isn't a cached version, hit the database.
		if ( ! $_multisite_term || ( $multisite_taxonomy && $multisite_taxonomy !== $_multisite_term->multisite_taxonomy ) ) {
			// Grab all matching multisite terms, in case any are shared between multisite taxonomies.
			$multisite_terms = $wpdb->get_results( $wpdb->prepare( "SELECT t.*, tt.* FROM $wpdb->multisite_terms AS t INNER JOIN $wpdb->multisite_term_taxonomy AS tt ON t.multisite_term_id = tt.multisite_term_id WHERE t.multisite_term_id = %d", $multisite_term_id ) );
			if ( ! $multisite_terms ) {
				return false;
			}

			// If a taxonomy was specified, find a match.
			if ( $multisite_taxonomy ) {
				foreach ( $multisite_terms as $match ) {
					if ( $multisite_taxonomy === $match->multisite_taxonomy ) {
						$_multisite_term = $match;
						break;
					}
				}
			} elseif ( 1 === count( $multisite_terms ) ) { // If only one match was found, it's the one we want.
				$_multisite_term = reset( $multisite_terms );
			} else { // Otherwise, the multisite term must be shared between taxonomies.
				// If the multisite term is shared only with invalid taxonomies, return the one valid multisite term.
				foreach ( $multisite_terms as $t ) {
					if ( ! multisite_taxonomy_exists( $t->multisite_taxonomy ) ) {
						continue;
					}

					// Only hit if we've already identified a multisite term in a valid taxonomy.
					if ( $_multisite_term ) {
						return new WP_Error( 'ambiguous_multisite_term_id', __( 'Term ID is shared between multiple multisite taxonomies', 'multitaxo' ), $multisite_term_id );
					}

					$_multisite_term = $t;
				}
			}

			if ( ! $_multisite_term ) {
				return false;
			}

			// Don't return terms from invalid taxonomies.
			if ( ! multisite_taxonomy_exists( $_multisite_term->multisite_taxonomy ) ) {
				return new WP_Error( 'invalid_multisite_taxonomy', __( 'Invalid multisite taxonomy.', 'multitaxo' ) );
			}

			$_multisite_term = sanitize_multisite_term( $_multisite_term, $_multisite_term->multisite_taxonomy, 'raw' );

			// Don't cache terms that are shared between taxonomies.
			if ( 1 === count( $multisite_terms ) ) {
				wp_cache_add( $multisite_term_id, $_multisite_term, 'multisite_terms' );
			}
		} // End if().

		$multisite_term_obj = new WP_Multisite_Term( $_multisite_term );
		$multisite_term_obj->filter( $multisite_term_obj->filter );

		return $multisite_term_obj;
	}

	/**
	 * Constructor.
	 *
	 * @access public
	 *
	 * @param WP_Multisite_Term|object $multisite_term Multisite Term object.
	 */
	public function __construct( $multisite_term ) {
		foreach ( get_object_vars( $multisite_term ) as $key => $value ) {
			$this->$key = $value;
		}
	}

	/**
	 * Sanitizes multisite term fields, according to the filter type provided.
	 *
	 * @access public
	 *
	 * @param string $filter Filter context. Accepts 'edit', 'db', 'display', 'attribute', 'js', 'raw'.
	 */
	public function filter( $filter ) {
		sanitize_multisite_term( $this, $this->multisite_taxonomy, $filter );
	}

	/**
	 * Converts an object to array.
	 *
	 * @access public
	 *
	 * @return array Object as array.
	 */
	public function to_array() {
		return get_object_vars( $this );
	}

	/**
	 * Getter.
	 *
	 * @access public
	 *
	 * @param string $key Property to get.
	 * @return mixed Property value.
	 */
	public function __get( $key ) {
		switch ( $key ) {
			case 'data' :
				$data = new stdClass();
				$columns = array( 'multisite_term_id', 'name', 'slug', 'multisite_term_group', 'multisite_term_multisite_taxonomy_id', 'multisite_taxonomy', 'description', 'parent', 'count' );
				foreach ( $columns as $column ) {
					$data->{$column} = isset( $this->{$column} ) ? $this->{$column} : null;
				}

				return sanitize_multisite_term( $data, $data->multisite_taxonomy, 'raw' );
		}
	}
}
