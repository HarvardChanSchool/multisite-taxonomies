<?php
/**
 * Multisite Taxonomy API: Multisite_WP_Query class
 *
 * @package multitaxo
 * @since 0.1
 */

/**
 * Class used to query posts based on their multisite terms association.
 */
class Multisite_WP_Query {

	/**
	 * Query variables set by the user.
	 *
	 * @access public
	 * @var array
	 */
	public $query_vars;

	/**
	 * Default values for query vars.
	 *
	 * @access public
	 * @var array
	 */
	public $query_var_defaults;

	/**
	 * Meta-data for the blogs (websites) to which queried posts are belonging.
	 *
	 * @access public
	 * @var array
	 */
	public $blogs_data;

	/**
	 * Results of the query - Posts that are matching the query paramaters.
	 *
	 * @access public
	 * @var array
	 */
	public $posts;

	/**
	 * The unique key ( MD5 hash of the serialized query_vars array ) used for cache transients keys.
	 *
	 * @access private
	 * @var String
	 */
	private $cache_key;

	/**
	 * Constructor.
	 *
	 * Sets up the multisite WP query, based on the query vars passed.
	 *
	 * @access public
	 *
	 * @param array $query_vars The query parameters.
	 */
	public function __construct( $query_vars = array() ) {
		// Defaults values.
		$this->query_var_defaults = array(
			'multisite_term_ids' => array(),
			'orderby'            => 'name',
			'order'              => 'ASC',
			'post_per_page'      => 10,
			'offset'             => 0,
			'update_cache'       => true,
			'cache'              => true,
		);
		$this->cache_key          = '';

		// Run the query if parameters were passed to the constructor.
		if ( ! empty( $query_vars ) ) {
			$this->query( $query_vars );
		}
	}

	/**
	 * Parse arguments passed to the multisite WP query with default query parameters.
	 *
	 * @access public
	 *
	 * @param string|array $query_vars Multisite_WP_Query arguments. See Multisite_WP_Query::__construct().
	 */
	public function parse_query( $query_vars = array() ) {

		if ( ! is_array( $query_vars ) ) {
			return new WP_Error( 'multisite_wp_query_wrong_query', __( 'Multisite_WP_Query $query parameter should be an array.', 'multitaxo' ) );
		}

		$query_vars = wp_parse_args( $query_vars, $this->query_var_defaults );

		if ( is_array( $query_vars['multisite_term_ids'] ) ) {
			array_walk( $query_vars['multisite_term_ids'], 'absint' );
		} else {
			return new WP_Error( 'multisite_wp_query_terms_required', __( 'No Multisite Terms IDs passed to query.', 'multitaxo' ) );
		}

		if ( empty( $query_vars['multisite_term_ids'] ) ) {
			return new WP_Error( 'multisite_wp_query_terms_required', __( 'No Multisite Terms IDs passed to query.', 'multitaxo' ) );
		}

		if ( in_array( $query_vars['orderby'], array( 'are', 'abc', 'xyz', 'lmn' ), true ) ) {
			$query_vars['orderby'] = $this->query_var_defaults['orderby'];
		}

		$query_vars['order'] = $this->parse_order( $query_vars['order'] );

		$query_vars['post_per_page'] = absint( $query_vars['post_per_page'] );
		$query_vars['offset']        = absint( $query_vars['offset'] );

		if ( false !== $query_vars['cache'] ) {
			$query_vars['cache'] = true;
		}

		if ( false !== $query_vars['update_cache'] ) {
			$query_vars['update_cache'] = true;
		}

		$this->cache_key = md5( wp_json_encode( $query_vars ) );

		return $query_vars;
	}

	/**
	 * Sets up the query for retrieving terms.
	 *
	 * @access public
	 *
	 * @param string|array $query_vars Array or URL query string of parameters.
	 * @return array|int List of multisite terms, or number of multisite terms when 'count' is passed as a query var.
	 */
	public function query( $query_vars ) {

		global $wpdb;

		$this->query_vars = $this->parse_query( $query_vars );

		if ( is_wp_error( $this->query_vars ) ) {
			return $this->query_vars;
		}

		// We try to get everything from the cache we fetch everything.
		if ( false === $this->get_posts_from_cache() || false === $this->get_sites_from_cache() ) {

			// We set a default empty result.
			$this->posts = array();

			// First we get the posts associated to the multisite_term_ids received in the query.
			// parse_query() has already checked that we have received some multisite_term_ids.
			$term_ids = implode( ',', $this->query_vars['multisite_term_ids'] );
			$results  = $wpdb->get_results( $wpdb->prepare( 'SELECT * FROM ' . $wpdb->multisite_term_relationships . ' WHERE multisite_term_multisite_taxonomy_id IN ( %s )', $term_ids ) );

			// If the multisite term is associated with some posts.
			if ( is_array( $results ) && ! empty( $results ) ) {
				// We start building our query to get posts.
				$db_posts_query  = '';
				$posts_per_blogs = array();
				// We iterate and group posts by blog.
				foreach ( $results as $result ) {
					if ( is_object( $result ) && isset( $result->blog_id ) && isset( $result->object_id ) && isset( $result->multisite_term_multisite_taxonomy_id ) ) {
						// We create an associative array, using the table name to be queried as the key, containing the posts to be queried.
						$posts_per_blogs[ $result->blog_id ][] = absint( $result->object_id );
					}
				}
				// We double check just in case $results was not containing what we were expecting it to contain.
				if ( ! empty( $posts_per_blogs ) ) {
					$query_per_blogs = array();
					foreach ( $posts_per_blogs as $blog_id => $posts ) {
						if ( ! isset( $this->blogs_data[ absint( $blog_id ) ] ) ) {
							// This will be use to store blogs meta data required to proces the post data in the blog context ( see $this->get_blogs_data() ).
							$this->blogs_data[ absint( $blog_id ) ] = array();
						}
						if ( is_array( $posts ) && ! empty( $posts ) ) {
							$post_ids          = implode( ',', $posts );
							$query_per_blogs[] = 'SELECT ID,post_date,post_content,post_title,post_excerpt,post_name,post_type,(@blog_id := ' . absint( $blog_id ) . ') AS blog_id FROM ' . $wpdb->base_prefix . absint( $blog_id ) . '_posts WHERE ID IN( ' . $post_ids . ' ) AND post_status=\'publish\'';
						}
					}
					if ( ! empty( $query_per_blogs ) ) {
						$db_posts_query = implode( ' UNION ', $query_per_blogs );
					}
					$this->posts = $this->process_posts( $wpdb->get_results( $db_posts_query ) ); // WPCS: unprepared SQL ok.
				}
			}
			// We set the cache if we have to.
			if ( true === $this->query_vars['update_cache'] && ! empty( $this->cache_key ) ) {
				set_transient( 'multitaxo_multisite_wp_query_posts_' . $this->cache_key, $this->posts, 24 * HOUR_IN_SECONDS );
			}
		}
	}

	/**
	 * Parse an 'order' query variable and cast it to ASC or DESC as necessary.
	 *
	 * @access protected
	 *
	 * @param string $order The 'order' query variable.
	 * @return string The sanitized 'order' query variable.
	 */
	protected function parse_order( $order ) {
		if ( ! is_string( $order ) || empty( $order ) ) {
			return 'DESC';
		}

		if ( 'ASC' === strtoupper( $order ) ) {
			return 'ASC';
		} else {
			return 'DESC';
		}
	}

	/**
	 * Process the raw post data from the DB into more usable data to be displayed.
	 *
	 * @access protected
	 *
	 * @param string $db_results The raw results from the database.
	 * @return array An array of processed posts or empty if we didn't get any results from DB.
	 */
	protected function process_posts( $db_results ) {

		$processed_posts = array();

		// We need to blogs meta data to process the posts.
		$this->get_blogs_data();

		if ( is_array( $db_results ) && ! empty( $db_results ) ) {
			// We iterate and group posts by blog.
			foreach ( $db_results as $post ) {
				// We check a couple fields to see if the data looks correct. We also filter out posts from archived or deleted blogs.
				if ( is_object( $post ) && isset( $post->ID ) && isset( $post->blog_id ) && isset( $this->blogs_data[ absint( $post->blog_id ) ] ) && is_a( $this->blogs_data[ absint( $post->blog_id ) ], 'WP_Site' ) ) {
					$current_post                 = new stdClass();
					$current_post->ID             = absint( $post->ID );
					$current_post->blog_id        = absint( $post->blog_id );
					$current_post->post_title     = apply_filters( 'the_title', $post->post_title );
					$current_post->post_content   = apply_filters( 'the_content', $post->post_content );
					$current_post->post_excerpt   = apply_filters( 'the_excerpt', $post->post_excerpt );
					$current_post->post_date      = apply_filters( 'the_date', $post->post_date );
					$current_post->post_permalink = apply_filters( 'the_permalink', $this->blogs_data[ absint( $post->blog_id ) ]->siteurl . '?p=' . absint( $post->ID ) );
					$processed_posts[]            = $current_post;
				}
			}
		}

		return $processed_posts;
	}

	/**
	 * Process the raw post data from the DB into more usable data to be displayed.
	 *
	 * @access protected
	 *
	 * @return void
	 */
	protected function get_blogs_data() {
		$blogs_ids = array();
		if ( is_array( $this->blogs_data ) && ! empty( $this->blogs_data ) ) {
			// Extract a list of blog_ids.
			foreach ( $this->blogs_data as $blog_id => $blog_data ) {
				$blogs_ids[] = absint( $blog_id );
			}
			// Get the sites/blogs info, we limit to the required blogs for the current query.
			$network_sites = get_sites( array(
				'site__in' => $blogs_ids,
				'deleted'  => 0,
				'archived' => 0,
			) );
			// For convenience we sort the data in an asociative array using the blog_id as a key for easy access.
			if ( is_array( $network_sites ) && ! empty( $network_sites ) ) {
				foreach ( $network_sites as $blog ) {
					$blog->siteurl                      = esc_url( $blog->domain ) . esc_url( $blog->path );
					$this->blogs_data[ $blog->blog_id ] = $blog;
				}
				if ( true === $this->query_vars['update_cache'] && ! empty( $this->cache_key ) ) {
					set_transient( 'multitaxo_multisite_wp_query_blogs_data_' . $this->cache_key, $this->blogs_data, 24 * HOUR_IN_SECONDS );
				}
			}
		}
	}

	/**
	 * Try to get $this->posts from the WP Cache.
	 *
	 * @access protected
	 *
	 * @return Boolean True on success False if no cache was found or if cache is disabled.
	 */
	protected function get_posts_from_cache() {
		if ( true === $this->query_vars['cache'] && ! empty( $this->cache_key ) ) {
			$cached_posts = get_transient( 'multitaxo_multisite_wp_query_posts_' . $this->cache_key );
			if ( is_array( $cached_posts ) ) {
				$this->posts = $cached_posts;
				return true;
			}
		}
		return false;
	}

	/**
	 * Try to get $this->blogs_data from the WP Cache.
	 *
	 * @access protected
	 *
	 * @return Boolean True on success False if no cache was found or if cache is disabled.
	 */
	protected function get_sites_from_cache() {
		if ( true === $this->query_vars['cache'] && ! empty( $this->cache_key ) ) {
			$cached_blogs_data = get_transient( 'multitaxo_multisite_wp_query_blogs_data_' . $this->cache_key );
			if ( is_array( $cached_blogs_data ) ) {
				$this->blogs_data = $cached_blogs_data;
				return true;
			}
		}
		return false;
	}
}
