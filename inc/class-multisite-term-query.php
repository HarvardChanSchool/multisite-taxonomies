<?php
/**
 * Taxonomy API: Multisite_Term_Query class.
 *
 * @package multitaxo
 * @since 0.1
 */

/**
 * Class used for querying terms.
 *
 * @see Multisite_Term_Query::__construct() for accepted arguments.
 */
class Multisite_Term_Query {

	/**
	 * SQL string used to perform database query.
	 *
	 * @access public
	 * @var string
	 */
	public $request;

	/**
	 * Metadata query container.
	 *
	 * @access public
	 * @var object WP_Meta_Query
	 */
	public $meta_query = false;

	/**
	 * Metadata query clauses.
	 *
	 * @access protected
	 * @var array
	 */
	protected $meta_query_clauses;

	/**
	 * SQL query clauses.
	 *
	 * @access protected
	 * @var array
	 */
	protected $sql_clauses = array(
		'select'  => '',
		'from'    => '',
		'where'   => array(),
		'orderby' => '',
		'limits'  => '',
	);

	/**
	 * Query vars set by the user.
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
	 * List of multisite terms located by the query.
	 *
	 * @access public
	 * @var array
	 */
	public $multisite_terms;

	/**
	 * Constructor.
	 *
	 * Sets up the multisite term query, based on the query vars passed.
	 *
	 * @access public
	 *
	 * @param string|array $query {
	 *     Optional. Array or query string of multisite term query parameters. Default empty.
	 *
	 *     @type string|array $multisite_taxonomy     Multsite taxonomy name, or array of multisite taxonomies, to which results should
	 *                                                be limited.
	 *     @type int|array    $object_ids             Optional. Object ID, or array of object IDs. Results will be
	 *                                                limited to terms associated with these objects.
	 *     @type string       $orderby                Field(s) to order multisite terms by. Accepts multisite term fields ('name',
	 *                                                'slug', 'multisite_term_group', 'multisite_term_id', 'id', 'description'),
	 *                                                'count' for multisite term multisite taxonomy count, 'include' to match the
	 *                                                'order' of the $include param, 'meta_value', 'meta_value_num',
	 *                                                the value of `$meta_key`, the array keys of `$meta_query`, or
	 *                                                'none' to omit the ORDER BY clause. Defaults to 'name'.
	 *     @type string       $order                  Whether to order multisite terms in ascending or descending order.
	 *                                                Accepts 'ASC' (ascending) or 'DESC' (descending).
	 *                                                Default 'ASC'.
	 *     @type bool|int     $hide_empty             Whether to hide multisite terms not assigned to any posts. Accepts
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
	 *     @type string       $fields                 Multisite term fields to query for. Accepts 'all' (returns an array of
	 *                                                complete multisite term objects), 'all_with_object_id' (returns an
	 *                                                array of multisite term objects with the 'object_id' param; only works
	 *                                                when the `$fields` parameter is 'object_ids' ), 'ids'
	 *                                                (returns an array of ids), 'mtmt_ids' (returns an array of
	 *                                                multisite term multisite taxonomy ids), 'id=>parent' (returns an associative
	 *                                                array with ids as keys, parent multisite term IDs as values), 'names'
	 *                                                (returns an array of multisite term names), 'count' (returns the number
	 *                                                of matching multisite terms), 'id=>name' (returns an associative array
	 *                                                with ids as keys, multisite term names as values), or 'id=>slug'
	 *                                                (returns an associative array with ids as keys, multisite term slugs
	 *                                                as values). Default 'all'.
	 *     @type bool         $count                  Whether to return a multisite term count (true) or array of multisite term objects
	 *                                                (false). Will take precedence over `$fields` if true.
	 *                                                Default false.
	 *     @type string|array $name                   Optional. Name or array of names to return multisite term(s) for.
	 *                                                Default empty.
	 *     @type string|array $slug                   Optional. Slug or array of slugs to return multisite term(s) for.
	 *                                                Default empty.
	 *     @type int|array    $multisite_term_multisite_taxonomy_id       Optional. Multisite term multisite taxonomy ID, or array of multisite term multisite taxonomy IDs,
	 *                                                to match when querying multisite terms.
	 *     @type bool         $hierarchical           Whether to include multisite terms that have non-empty descendants (even
	 *                                                if $hide_empty is set to true). Default true.
	 *     @type string       $search                 Search criteria to match multisite terms. Will be SQL-formatted with
	 *                                                wildcards before and after. Default empty.
	 *     @type string       $name__like             Retrieve multisite terms with criteria by which a multisite term is LIKE
	 *                                                `$name__like`. Default empty.
	 *     @type string       $description__like      Retrieve multisite terms where the description is LIKE
	 *                                                `$description__like`. Default empty.
	 *     @type bool         $pad_counts             Whether to pad the quantity of a multisite term's children in the
	 *                                                quantity of each multisite term's "count" object variable.
	 *                                                Default false.
	 *     @type string       $get                    Whether to return multisite terms regardless of ancestry or whether the
	 *                                                multisite terms are empty. Accepts 'all' or empty (disabled).
	 *                                                Default empty.
	 *     @type int          $child_of               Multisite term ID to retrieve child multisite terms of. If multiple multisite taxonomies
	 *                                                are passed, $child_of is ignored. Default 0.
	 *     @type int|string   $parent                 Parent multisite term ID to retrieve direct-child multisite terms of.
	 *                                                Default empty.
	 *     @type bool         $childless              True to limit results to multisite terms that have no children.
	 *                                                This parameter has no effect on non-hierarchical multisite taxonomies.
	 *                                                Default false.
	 *     @type string       $cache_domain           Unique cache key to be produced when this query is stored in
	 *                                                an object cache. Default is 'core'.
	 *     @type bool         $update_multisite_term_meta_cache Whether to prime meta caches for matched multisite terms. Default true.
	 *     @type array        $meta_query             Optional. Meta query clauses to limit retrieved multisite terms by.
	 *                                                See `WP_Meta_Query`. Default empty.
	 *     @type string       $meta_key               Limit terms to those matching a specific metadata key.
	 *                                                Can be used in conjunction with `$meta_value`.
	 *     @type string       $meta_value             Limit multisite terms to those matching a specific metadata value.
	 *                                                Usually used in conjunction with `$meta_key`.
	 * }
	 */
	public function __construct( $query = '' ) {
		$this->query_var_defaults = array(
			'multisite_taxonomy'     => null,
			'object_ids'             => null,
			'orderby'                => 'name',
			'order'                  => 'ASC',
			'hide_empty'             => true,
			'include'                => array(),
			'exclude'                => array(),
			'exclude_tree'           => array(),
			'number'                 => '',
			'offset'                 => '',
			'fields'                 => 'all',
			'count'                  => false,
			'name'                   => '',
			'slug'                   => '',
			'multisite_term_multisite_multisite_taxonomy_id'       => '',
			'hierarchical'           => true,
			'search'                 => '',
			'name__like'             => '',
			'description__like'      => '',
			'pad_counts'             => false,
			'get'                    => '',
			'child_of'               => 0,
			'parent'                 => '',
			'childless'              => false,
			'cache_domain'           => 'core',
			'update_multisite_term_meta_cache' => true,
			'meta_query'             => '', // WPCS: tax_query ok.
			'meta_key'               => '', // WPCS: tax_query ok.
			'meta_value'             => '', // WPCS: tax_query ok.
			'meta_type'              => '',
			'meta_compare'           => '',
		);

		if ( ! empty( $query ) ) {
			$this->query( $query );
		}
	}

	/**
	 * Parse arguments passed to the multisite term query with default query parameters.
	 *
	 * @access public
	 *
	 * @param string|array $query Multisite_Term_Query arguments. See Multisite_Term_Query::__construct().
	 */
	public function parse_query( $query = '' ) {
		if ( empty( $query ) ) {
			$query = $this->query_vars;
		}

		$multisite_taxonomies = isset( $query['multisite_taxonomy'] ) ? (array) $query['multisite_taxonomy'] : null;

		/**
		 * Filters the multisite terms query default arguments.
		 *
		 * Use {@see 'get_multisite_terms_args'} to filter the passed arguments.
		 *
		 * @param array $defaults   An array of default get_multisite_terms() arguments.
		 * @param array $multisite_taxonomies An array of taxonomies.
		 */
		$this->query_var_defaults = apply_filters( 'get_multisite_terms_defaults', $this->query_var_defaults, $multisite_taxonomies );

		$query = wp_parse_args( $query, $this->query_var_defaults );

		$query['number'] = absint( $query['number'] );
		$query['offset'] = absint( $query['offset'] );

		// 'parent' overrides 'child_of'.
		if ( 0 < intval( $query['parent'] ) ) {
			$query['child_of'] = false;
		}

		if ( 'all' === $query['get'] ) {
			$query['childless'] = false;
			$query['child_of'] = 0;
			$query['hide_empty'] = 0;
			$query['hierarchical'] = false;
			$query['pad_counts'] = false;
		}

		$query['multisite_taxonomy'] = $multisite_taxonomies;

		$this->query_vars = $query;

		/**
		 * Fires after multisite term query vars have been parsed.
		 *
		 * @param Multisite_Term_Query $this Current instance of Multisite_Term_Query.
		 */
		do_action( 'parse_multisite_term_query', $this );
	}

	/**
	 * Sets up the query for retrieving terms.
	 *
	 * @access public
	 *
	 * @param string|array $query Array or URL query string of parameters.
	 * @return array|int List of multisite terms, or number of multisite terms when 'count' is passed as a query var.
	 */
	public function query( $query ) {
		$this->query_vars = wp_parse_args( $query );
		return $this->get_multisite_terms();
	}

	/**
	 * Get multisite terms, based on query_vars.
	 *
	 * @access public
	 *
	 * @global wpdb $wpdb WordPress database abstraction object.
	 *
	 * @return array
	 */
	public function get_multisite_terms() {
		global $wpdb;

		$this->parse_query( $this->query_vars );
		$args = $this->query_vars;

		// Set up meta_query so it's available to 'pre_get_multisite_terms'.
		$this->meta_query = new WP_Meta_Query();
		$this->meta_query->parse_query_vars( $args );

		/**
		 * Fires before multisite terms are retrieved.
		 *
		 * @param Multisite_Term_Query $this Current instance of Multisite_Term_Query.
		 */
		do_action( 'pre_get_multisite_terms', $this );

		$multisite_taxonomies = $args['multisite_taxonomy'];

		// Save queries by not crawling the tree in the case of multiple taxes or a flat tax.
		$has_hierarchical_multisite_tax = false;
		if ( $multisite_taxonomies ) {
			foreach ( $multisite_taxonomies as $_tax ) {
				if ( is_multisite_taxonomy_hierarchical( $_tax ) ) {
					$has_hierarchical_multisite_tax = true;
				}
			}
		}

		if ( ! $has_hierarchical_multisite_tax ) {
			$args['hierarchical'] = false;
			$args['pad_counts'] = false;
		}

		// 'parent' overrides 'child_of'.
		if ( 0 < intval( $args['parent'] ) ) {
			$args['child_of'] = false;
		}

		if ( 'all' === $args['get'] ) {
			$args['childless'] = false;
			$args['child_of'] = 0;
			$args['hide_empty'] = 0;
			$args['hierarchical'] = false;
			$args['pad_counts'] = false;
		}

		/**
		 * Filters the multisite terms query arguments.
		 *
		 * @param array $args       An array of get_multisite_terms() arguments.
		 * @param array $multisite_taxonomies An array of multisite taxonomies.
		 */
		$args = apply_filters( 'get_multisite_terms_args', $args, $multisite_taxonomies );

		// Avoid the query if the queried parent/child_of multisite term has no descendants.
		$child_of = $args['child_of'];
		$parent   = $args['parent'];

		if ( $child_of ) {
			$_parent = $child_of;
		} elseif ( $parent ) {
			$_parent = $parent;
		} else {
			$_parent = false;
		}

		if ( $_parent ) {
			$in_hierarchy = false;
			foreach ( $multisite_taxonomies as $_tax ) {
				$hierarchy = _get_multisite_term_hierarchy( $_tax );

				if ( isset( $hierarchy[ $_parent ] ) ) {
					$in_hierarchy = true;
				}
			}

			if ( ! $in_hierarchy ) {
				return array();
			}
		}

		// 'multisite_term_order' is a legal sort order only when joining the relationship table.
		$_orderby = $this->query_vars['orderby'];
		if ( 'multisite_term_order' === $_orderby && empty( $this->query_vars['object_ids'] ) ) {
			$_orderby = 'multisite_term_id';
		}
		$orderby = $this->parse_orderby( $_orderby );

		if ( $orderby ) {
			$orderby = "ORDER BY $orderby";
		}

		$order = $this->parse_order( $this->query_vars['order'] );

		if ( $multisite_taxonomies ) {
			$this->sql_clauses['where']['multisite_taxonomy'] = "tt.multisite_taxonomy IN ('" . implode( "', '", array_map( 'esc_sql', $multisite_taxonomies ) ) . "')";
		}

		$exclude      = $args['exclude'];
		$exclude_tree = $args['exclude_tree'];
		$include      = $args['include'];

		$inclusions = '';
		if ( ! empty( $include ) ) {
			$exclude = '';
			$exclude_tree = '';
			$inclusions = implode( ',', wp_parse_id_list( $include ) );
		}

		if ( ! empty( $inclusions ) ) {
			$this->sql_clauses['where']['inclusions'] = 't.multisite_term_id IN ( ' . $inclusions . ' )';
		}

		$exclusions = array();
		if ( ! empty( $exclude_tree ) ) {
			$exclude_tree = wp_parse_id_list( $exclude_tree );
			$excluded_children = $exclude_tree;
			foreach ( $exclude_tree as $extrunk ) {
				$excluded_children = array_merge(
					$excluded_children,
					(array) get_multisite_terms( $multisite_taxonomies[0], array(
						'child_of' => intval( $extrunk ),
						'fields' => 'ids',
						'hide_empty' => 0,
					) )
				);
			}
			$exclusions = array_merge( $excluded_children, $exclusions );
		}

		if ( ! empty( $exclude ) ) {
			$exclusions = array_merge( wp_parse_id_list( $exclude ), $exclusions );
		}

		// 'childless' terms are those without an entry in the flattened multisite term hierarchy.
		$childless = (bool) $args['childless'];
		if ( $childless ) {
			foreach ( $multisite_taxonomies as $_tax ) {
				$multisite_term_hierarchy = _get_multisite_term_hierarchy( $_tax );
				$exclusions = array_merge( array_keys( $multisite_term_hierarchy ), $exclusions );
			}
		}

		if ( ! empty( $exclusions ) ) {
			$exclusions = 't.multisite_term_id NOT IN (' . implode( ',', array_map( 'intval', $exclusions ) ) . ')';
		} else {
			$exclusions = '';
		}

		/**
		 * Filters the multisite terms to exclude from the multisite terms query.
		 *
		 * @param string $exclusions `NOT IN` clause of the multisite terms query.
		 * @param array  $args       An array of multisite terms query arguments.
		 * @param array  $multisite_taxonomies An array of multisite taxonomies.
		 */
		$exclusions = apply_filters( 'list_multisite_terms_exclusions', $exclusions, $args, $multisite_taxonomies );

		if ( ! empty( $exclusions ) ) {
			// Must do string manipulation here for backward compatibility with filter.
			$this->sql_clauses['where']['exclusions'] = preg_replace( '/^\s*AND\s*/', '', $exclusions );
		}

		if ( ! empty( $args['name'] ) ) {
			$names = (array) $args['name'];
			foreach ( $names as &$_name ) {
				// `sanitize_multisite_term_field()` returns slashed data.
				$_name = stripslashes( sanitize_multisite_term_field( 'name', $_name, 0, reset( $multisite_taxonomies ), 'db' ) );
			}

			$this->sql_clauses['where']['name'] = "t.name IN ('" . implode( "', '", array_map( 'esc_sql', $names ) ) . "')";
		}

		if ( ! empty( $args['slug'] ) ) {
			if ( is_array( $args['slug'] ) ) {
				$slug = array_map( 'sanitize_title', $args['slug'] );
				$this->sql_clauses['where']['slug'] = "t.slug IN ('" . implode( "', '", $slug ) . "')";
			} else {
				$slug = sanitize_title( $args['slug'] );
				$this->sql_clauses['where']['slug'] = "t.slug = '$slug'";
			}
		}

		if ( ! empty( $args['multisite_term_multisite_taxonomy_id'] ) ) {
			if ( is_array( $args['multisite_term_multisite_taxonomy_id'] ) ) {
				$mtmt_ids = implode( ',', array_map( 'intval', $args['multisite_term_multisite_taxonomy_id'] ) );
				$this->sql_clauses['where']['multisite_term_multisite_taxonomy_id'] = "tt.multisite_term_multisite_taxonomy_id IN ({$mtmt_ids})";
			} else {
				$this->sql_clauses['where']['multisite_term_multisite_taxonomy_id'] = $wpdb->prepare( 'tt.multisite_term_multisite_taxonomy_id = %d', $args['multisite_term_multisite_taxonomy_id'] );
			}
		}

		if ( ! empty( $args['name__like'] ) ) {
			$this->sql_clauses['where']['name__like'] = $wpdb->prepare( 't.name LIKE %s', '%' . $wpdb->esc_like( $args['name__like'] ) . '%' );
		}

		if ( ! empty( $args['description__like'] ) ) {
			$this->sql_clauses['where']['description__like'] = $wpdb->prepare( 'tt.description LIKE %s', '%' . $wpdb->esc_like( $args['description__like'] ) . '%' );
		}

		if ( ! empty( $args['object_ids'] ) ) {
			$object_ids = $args['object_ids'];
			if ( ! is_array( $object_ids ) ) {
				$object_ids = array( $object_ids );
			}

			$object_ids = implode( ', ', array_map( 'intval', $object_ids ) );
			$this->sql_clauses['where']['object_ids'] = "tr.object_id IN ($object_ids)";
		}

		/*
		 * When querying for object relationships, the 'count > 0' check
		 * added by 'hide_empty' is superfluous.
		 */
		if ( ! empty( $args['object_ids'] ) ) {
			$args['hide_empty'] = false;
		}

		if ( '' !== $parent ) {
			$parent = (int) $parent;
			$this->sql_clauses['where']['parent'] = "tt.parent = '$parent'";
		}

		$hierarchical = $args['hierarchical'];
		if ( 'count' === $args['fields'] ) {
			$hierarchical = false;
		}
		if ( $args['hide_empty'] && ! $hierarchical ) {
			$this->sql_clauses['where']['count'] = 'tt.count > 0';
		}

		$number = $args['number'];
		$offset = $args['offset'];

		// Don't limit the query results when we have to descend the family tree.
		if ( $number && ! $hierarchical && ! $child_of && '' === $parent ) {
			if ( $offset ) {
				$limits = 'LIMIT ' . $offset . ',' . $number;
			} else {
				$limits = 'LIMIT ' . $number;
			}
		} else {
			$limits = '';
		}

		if ( ! empty( $args['search'] ) ) {
			$this->sql_clauses['where']['search'] = $this->get_search_sql( $args['search'] );
		}

		// Meta query support.
		$join = '';
		$distinct = '';

		// Reparse meta_query query_vars, in case they were modified in a 'pre_get_multisite_terms' callback.
		$this->meta_query->parse_query_vars( $this->query_vars );
		$mq_sql = $this->meta_query->get_sql( 'multisite_term', 't', 'multisite_term_id' );
		$meta_clauses = $this->meta_query->get_clauses();

		if ( ! empty( $meta_clauses ) ) {
			$join .= $mq_sql['join'];
			$this->sql_clauses['where']['meta_query'] = preg_replace( '/^\s*AND\s*/', '', $mq_sql['where'] ); // WPCS: tax_query ok.
			$distinct .= 'DISTINCT';
		}

		$selects = array();
		switch ( $args['fields'] ) {
			case 'all':
			case 'all_with_object_id' :
			case 'mtmt_ids' :
			case 'slugs' :
				$selects = array( 't.*', 'tt.*' );
				if ( 'all_with_object_id' === $args['fields'] && ! empty( $args['object_ids'] ) ) {
					$selects[] = 'tr.object_id';
				}
				break;
			case 'ids':
			case 'id=>parent':
				$selects = array( 't.multisite_term_id', 'tt.parent', 'tt.count', 'tt.multisite_taxonomy' );
				break;
			case 'names':
				$selects = array( 't.multisite_term_id', 'tt.parent', 'tt.count', 't.name', 'tt.multisite_taxonomy' );
				break;
			case 'count':
				$orderby = '';
				$order = '';
				$selects = array( 'COUNT(*)' );
				break;
			case 'id=>name':
				$selects = array( 't.multisite_term_id', 't.name', 'tt.count', 'tt.multisite_taxonomy' );
				break;
			case 'id=>slug':
				$selects = array( 't.multisite_term_id', 't.slug', 'tt.count', 'tt.multisite_taxonomy' );
				break;
		}

		$_fields = $args['fields'];

		/**
		 * Filters the fields to select in the multisite terms query.
		 *
		 * Field lists modified using this filter will only modify the multisite term fields returned
		 * by the function when the `$fields` parameter set to 'count' or 'all'. In all other
		 * cases, the multisite term fields in the results array will be determined by the `$fields`
		 * parameter alone.
		 *
		 * Use of this filter can result in unpredictable behavior, and is not recommended.
		 *
		 * @param array $selects    An array of fields to select for the multisite terms query.
		 * @param array $args       An array of multisite term query arguments.
		 * @param array $multisite_taxonomies An array of taxonomies.
		 */
		$fields = implode( ', ', apply_filters( 'get_multisite_terms_fields', $selects, $args, $multisite_taxonomies ) );

		$join .= " INNER JOIN $wpdb->multisite_term_multisite_taxonomy AS tt ON t.multisite_term_id = tt.multisite_term_id";

		if ( ! empty( $this->query_vars['object_ids'] ) ) {
			$join .= " INNER JOIN {$wpdb->multisite_term_relationships} AS tr ON tr.multisite_term_multisite_taxonomy_id = tt.multisite_term_multisite_taxonomy_id";
		}

		$where = implode( ' AND ', $this->sql_clauses['where'] );

		/**
		 * Filters the multisite terms query SQL clauses.
		 *
		 * @param array $pieces     Terms query SQL clauses.
		 * @param array $multisite_taxonomies An array of taxonomies.
		 * @param array $args       An array of multisite terms query arguments.
		 */
		$clauses = apply_filters( 'terms_clauses', compact( 'fields', 'join', 'where', 'distinct', 'orderby', 'order', 'limits' ), $multisite_taxonomies, $args );

		$fields = isset( $clauses['fields'] ) ? $clauses['fields'] : '';
		$join = isset( $clauses['join'] ) ? $clauses['join'] : '';
		$where = isset( $clauses['where'] ) ? $clauses['where'] : '';
		$distinct = isset( $clauses['distinct'] ) ? $clauses['distinct'] : '';
		$orderby = isset( $clauses['orderby'] ) ? $clauses['orderby'] : '';
		$order = isset( $clauses['order'] ) ? $clauses['order'] : '';
		$limits = isset( $clauses['limits'] ) ? $clauses['limits'] : '';

		if ( $where ) {
			$where = "WHERE $where";
		}

		$this->sql_clauses['select']  = "SELECT $distinct $fields";
		$this->sql_clauses['from']    = "FROM $wpdb->multisite_terms AS t $join";
		$this->sql_clauses['orderby'] = $orderby ? "$orderby $order" : '';
		$this->sql_clauses['limits']  = $limits;

		$this->request = "{$this->sql_clauses['select']} {$this->sql_clauses['from']} {$where} {$this->sql_clauses['orderby']} {$this->sql_clauses['limits']}";

		// $args can be anything. Only use the args defined in defaults to compute the key.
		$key = md5( serialize( wp_array_slice_assoc( $args, array_keys( $this->query_var_defaults ) ) ) . serialize( $multisite_taxonomies ) . $this->request ); // @codingStandardsIgnoreLine - serialize is safe in this context.
		$last_changed = wp_cache_get_last_changed( 'multisite_terms' );
		$cache_key = "get_multisite_terms:$key:$last_changed";
		$cache = wp_cache_get( $cache_key, 'multisite_terms' );
		if ( false !== $cache ) {
			if ( 'all' === $_fields ) {
				$cache = array_map( 'get_multisite_term', $cache );
			}

			$this->multisite_terms = $cache;
			return $this->multisite_terms;
		}

		if ( 'count' === $_fields ) {
			$count = $wpdb->get_var( $this->request ); // WPCS: unprepared SQL ok.
			wp_cache_set( $cache_key, $count, 'multisite_terms' );
			return $count;
		}

		$multisite_terms = $wpdb->get_results( $this->request ); // WPCS: unprepared SQL ok.
		if ( 'all' === $_fields || 'all_with_object_id' === $_fields ) {
			update_multisite_term_cache( $multisite_terms );
		}

		// Prime termmeta cache.
		if ( $args['update_multisite_term_meta_cache'] ) {
			$multisite_term_ids = wp_list_pluck( $multisite_terms, 'multisite_term_id' );
			update_multisite_termmeta_cache( $multisite_term_ids );
		}

		if ( empty( $multisite_terms ) ) {
			wp_cache_add( $cache_key, array(), 'multisite_terms', DAY_IN_SECONDS );
			return array();
		}

		if ( $child_of ) {
			foreach ( $multisite_taxonomies as $_tax ) {
				$children = _get_multisite_term_hierarchy( $_tax );
				if ( ! empty( $children ) ) {
					$multisite_terms = _get_multisite_term_children( $child_of, $multisite_terms, $_tax );
				}
			}
		}

		// Update term counts to include children.
		if ( $args['pad_counts'] && 'all' === $_fields ) {
			foreach ( $multisite_taxonomies as $_tax ) {
				_pad_multisite_term_counts( $multisite_terms, $_tax );
			}
		}

		// Make sure we show empty categories that have children.
		if ( $hierarchical && $args['hide_empty'] && is_array( $multisite_terms ) ) {
			foreach ( $multisite_terms as $k => $multisite_term ) {
				if ( ! $multisite_term->count ) {
					$children = get_multisite_term_children( $multisite_term->multisite_term_id, $multisite_term->multisite_taxonomy );
					if ( is_array( $children ) ) {
						foreach ( $children as $child_id ) {
							$child = get_multisite_term( $child_id, $multisite_term->multisite_taxonomy );
							if ( $child->count ) {
								continue 2;
							}
						}
					}

					// It really is empty.
					unset( $multisite_terms[ $k ] );
				}
			}
		}

		/*
		 * When querying for terms connected to objects, we may get
		 * duplicate results. The duplicates should be preserved if
		 * `$fields` is 'all_with_object_id', but should otherwise be
		 * removed.
		 */
		if ( ! empty( $args['object_ids'] ) && 'all_with_object_id' !== $_fields ) {
			$_multisite_mtmt_ids = array();
			$_multisite_terms = array();
			foreach ( $multisite_terms as $multisite_term ) {
				if ( isset( $_multisite_mtmt_ids[ $multisite_term->multisite_term_id ] ) ) {
					continue;
				}

				$_multisite_mtmt_ids[ $multisite_term->multisite_term_id ] = 1;
				$_multisite_terms[] = $multisite_term;
			}

			$multisite_terms = $_multisite_terms;
		}

		$_multisite_terms = array();
		if ( 'id=>parent' === $_fields ) {
			foreach ( $multisite_terms as $multisite_term ) {
				$_multisite_terms[ $multisite_term->multisite_term_id ] = $multisite_term->parent;
			}
		} elseif ( 'ids' === $_fields ) {
			foreach ( $multisite_terms as $multisite_term ) {
				$_multisite_terms[] = (int) $multisite_term->multisite_term_id;
			}
		} elseif ( 'mtmt_ids' === $_fields ) {
			foreach ( $multisite_terms as $multisite_terms ) {
				$_multisite_terms[] = (int) $multisite_terms->multisite_term_multisite_taxonomy_id;
			}
		} elseif ( 'names' === $_fields ) {
			foreach ( $multisite_terms as $multisite_terms ) {
				$_multisite_terms[] = $multisite_terms->name;
			}
		} elseif ( 'slugs' === $_fields ) {
			foreach ( $multisite_terms as $multisite_terms ) {
				$_multisite_terms[] = $multisite_terms->slug;
			}
		} elseif ( 'id=>name' === $_fields ) {
			foreach ( $multisite_terms as $multisite_terms ) {
				$_multisite_terms[ $multisite_terms->multisite_term_id ] = $multisite_terms->name;
			}
		} elseif ( 'id=>slug' === $_fields ) {
			foreach ( $multisite_terms as $multisite_terms ) {
				$_multisite_terms[ $multisite_terms->multisite_term_id ] = $multisite_terms->slug;
			}
		}

		if ( ! empty( $_multisite_terms ) ) {
			$multisite_terms = $_multisite_terms;
		}

		// Hierarchical queries are not limited, so 'offset' and 'number' must be handled now.
		if ( $hierarchical && $number && is_array( $multisite_terms ) ) {
			if ( $offset >= count( $multisite_terms ) ) {
				$multisite_terms = array();
			} else {
				$multisite_terms = array_slice( $multisite_terms, $offset, $number, true );
			}
		}

		wp_cache_add( $cache_key, $multisite_terms, 'multisite_terms', DAY_IN_SECONDS );

		if ( 'all' === $_fields || 'all_with_object_id' === $_fields ) {
			$multisite_terms = array_map( 'get_multisite_term', $multisite_terms );
		}

		$this->multisite_terms = $multisite_terms;
		return $this->multisite_terms;
	}

	/**
	 * Parse and sanitize 'orderby' keys passed to the multisite term query.
	 *
	 * @access protected
	 *
	 * @global wpdb $wpdb WordPress database abstraction object.
	 *
	 * @param string $orderby_raw Alias for the field to order by.
	 * @return string|false Value to used in the ORDER clause. False otherwise.
	 */
	protected function parse_orderby( $orderby_raw ) {
		$_orderby = strtolower( $orderby_raw );
		$maybe_orderby_meta = false;

		if ( in_array( $_orderby, array( 'multisite_term_id', 'name', 'slug', 'multisite_term_group' ), true ) ) {
			$orderby = "t.$_orderby";
		} elseif ( in_array( $_orderby, array( 'count', 'parent', 'multisite_taxonomy', 'multisite_term_multisite_taxonomy_id', 'description' ), true ) ) {
			$orderby = "tt.$_orderby";
		} elseif ( 'multisite_term_order' === $_orderby ) {
			$orderby = 'tr.multisite_term_order';
		} elseif ( 'include' === $_orderby && ! empty( $this->query_vars['include'] ) ) {
			$include = implode( ',', wp_parse_id_list( $this->query_vars['include'] ) );
			$orderby = "FIELD( t.multisite_term_id, $include )";
		} elseif ( 'none' === $_orderby ) {
			$orderby = '';
		} elseif ( empty( $_orderby ) || 'id' === $_orderby || 'multisite_term_id' === $_orderby ) {
			$orderby = 't.multisite_term_id';
		} else {
			$orderby = 't.name';

			// This may be a value of orderby related to meta.
			$maybe_orderby_meta = true;
		}

		/**
		 * Filters the ORDERBY clause of the multisite terms query.
		 *
		 * @param string $orderby    `ORDERBY` clause of the multisite terms query.
		 * @param array  $args       An array of multisite terms query arguments.
		 * @param array  $multisite_taxonomies An array of multisite taxonomies.
		 */
		$orderby = apply_filters( 'get_multisite_terms_orderby', $orderby, $this->query_vars, $this->query_vars['multisite_taxonomy'] );

		// Run after the 'get_multisite_terms_orderby' filter for backward compatibility.
		if ( $maybe_orderby_meta ) {
			$maybe_orderby_meta = $this->parse_orderby_meta( $_orderby );
			if ( $maybe_orderby_meta ) {
				$orderby = $maybe_orderby_meta;
			}
		}

		return $orderby;
	}

	/**
	 * Generate the ORDER BY clause for an 'orderby' param that is potentially related to a meta query.
	 *
	 * @access public
	 *
	 * @param string $orderby_raw Raw 'orderby' value passed to Multisite_Term_Query.
	 * @return string
	 */
	protected function parse_orderby_meta( $orderby_raw ) {
		$orderby = '';

		// Tell the meta query to generate its SQL, so we have access to table aliases.
		$this->meta_query->get_sql( 'multisite_term', 't', 'multisite_term_id' );
		$meta_clauses = $this->meta_query->get_clauses();
		if ( ! $meta_clauses || ! $orderby_raw ) {
			return $orderby;
		}

		$allowed_keys = array();
		$primary_meta_key = null;
		$primary_meta_query = reset( $meta_clauses );
		if ( ! empty( $primary_meta_query['key'] ) ) {
			$primary_meta_key = $primary_meta_query['key'];
			$allowed_keys[] = $primary_meta_key;
		}
		$allowed_keys[] = 'meta_value';
		$allowed_keys[] = 'meta_value_num';
		$allowed_keys   = array_merge( $allowed_keys, array_keys( $meta_clauses ) );

		if ( ! in_array( $orderby_raw, $allowed_keys, true ) ) {
			return $orderby;
		}

		switch ( $orderby_raw ) {
			case $primary_meta_key:
			case 'meta_value':
				if ( ! empty( $primary_meta_query['type'] ) ) {
					$orderby = "CAST({$primary_meta_query['alias']}.meta_value AS {$primary_meta_query['cast']})";
				} else {
					$orderby = "{$primary_meta_query['alias']}.meta_value";
				}
				break;

			case 'meta_value_num':
				$orderby = "{$primary_meta_query['alias']}.meta_value+0";
				break;

			default:
				if ( array_key_exists( $orderby_raw, $meta_clauses ) ) {
					// $orderby corresponds to a meta_query clause.
					$meta_clause = $meta_clauses[ $orderby_raw ];
					$orderby = "CAST({$meta_clause['alias']}.meta_value AS {$meta_clause['cast']})";
				}
				break;
		}

		return $orderby;
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
	 * Used internally to generate a SQL string related to the 'search' parameter.
	 *
	 * @access protected
	 *
	 * @global wpdb $wpdb WordPress database abstraction object.
	 *
	 * @param string $string The string to be searched.
	 * @return string
	 */
	protected function get_search_sql( $string ) {
		global $wpdb;

		$like = '%' . $wpdb->esc_like( $string ) . '%';

		return $wpdb->prepare( '((t.name LIKE %s) OR (t.slug LIKE %s))', $like, $like );
	}
}
