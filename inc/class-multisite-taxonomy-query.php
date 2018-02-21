<?php
/**
 * Multisite Taxonomy API: Multisite_Taxonomy_Query class
 *
 * @package multitaxo
 * @since 0.1
 */

/**
 * Class used to implement multisite taxonomy queries for the Multisite Taxonomy API.
 *
 * Used for generating SQL clauses that filter a primary query according to object
 * multisite taxonomy terms.
 *
 * Multisite_Taxonomy_Query is a helper that allows primary query classes, such as WP_Query, to filter
 * their results by object metadata, by generating `JOIN` and `WHERE` subclauses to be
 * attached to the primary SQL query string.
 */
class Multisite_Taxonomy_Query {

	/**
	 * Array of multisite taxonomy queries.
	 *
	 * See Multisite_Taxonomy_Query::__construct() for information on multisite tax query arguments.
	 *
	 * @since 3.1.0
	 * @access public
	 * @var array
	 */
	public $queries = array();

	/**
	 * The relation between the queries. Can be one of 'AND' or 'OR'.
	 *
	 * @since 3.1.0
	 * @access public
	 * @var string
	 */
	public $relation;

	/**
	 * Standard response when the query should not return any rows.
	 *
	 * @since 3.2.0
	 *
	 * @static
	 * @access private
	 * @var string
	 */
	private static $no_results = array(
		'join'  => array( '' ),
		'where' => array( '0 = 1' ),
	);

	/**
	 * A flat list of table aliases used in the JOIN clauses.
	 *
	 * @since 4.1.0
	 * @access protected
	 * @var array
	 */
	protected $table_aliases = array();

	/**
	 * Multisite Terms and multisite taxonomies fetched by this query.
	 *
	 * We store this data in a flat array because they are referenced in a
	 * number of places by WP_Query.
	 *
	 * @since 4.1.0
	 * @access public
	 * @var array
	 */
	public $queried_multisite_terms = array();

	/**
	 * Database table that where the metadata's objects are stored (eg $wpdb->users).
	 *
	 * @since 4.1.0
	 * @access public
	 * @var string
	 */
	public $primary_table;

	/**
	 * Column in 'primary_table' that represents the ID of the object.
	 *
	 * @since 4.1.0
	 * @access public
	 * @var string
	 */
	public $primary_id_column;

	/**
	 * Constructor.
	 *
	 * @since 3.1.0
	 * @since 4.1.0 Added support for `$operator` 'NOT EXISTS' and 'EXISTS' values.
	 * @access public
	 *
	 * @param array $multisite_taxonomy_query {
	 *     Array of multisite taxonomy query clauses.
	 *
	 *     @type string $relation Optional. The MySQL keyword used to join
	 *                            the clauses of the query. Accepts 'AND', or 'OR'. Default 'AND'.
	 *     @type array {
	 *         Optional. An array of first-order clause parameters, or another fully-formed multisite tax query.
	 *
	 *         @type string           $multisite_taxonomy         Multisite Taxonomy being queried. Optional when field=multisite_term_multisite_taxonomy_id.
	 *         @type string|int|array $multisite_terms            Multisite Term or multisite terms to filter by.
	 *         @type string           $field            Field to match $multisite_terms against. Accepts 'multisite_term_id', 'slug',
	 *                                                 'name', or 'multisite_term_multisite_taxonomy_id'. Default: 'multisite_term_id'.
	 *         @type string           $operator         MySQL operator to be used with $multisite_terms in the WHERE clause.
	 *                                                  Accepts 'AND', 'IN', 'NOT IN', 'EXISTS', 'NOT EXISTS'.
	 *                                                  Default: 'IN'.
	 *         @type bool             $include_children Optional. Whether to include child multisite terms.
	 *                                                  Requires a $multisite_taxonomy. Default: true.
	 *     }
	 * }
	 */
	public function __construct( $multisite_taxonomy_query ) {
		if ( isset( $multisite_taxonomy_query['relation'] ) ) {
			$this->relation = $this->sanitize_relation( $multisite_taxonomy_query['relation'] );
		} else {
			$this->relation = 'AND';
		}

		$this->queries = $this->sanitize_query( $multisite_taxonomy_query );
	}

	/**
	 * Ensure the 'multisite_taxonomy_query' argument passed to the class constructor is well-formed.
	 *
	 * Ensures that each query-level clause has a 'relation' key, and that
	 * each first-order clause contains all the necessary keys from `$defaults`.
	 *
	 * @since 4.1.0
	 * @access public
	 *
	 * @param array $queries Array of queries clauses.
	 * @return array Sanitized array of query clauses.
	 */
	public function sanitize_query( $queries ) {
		$cleaned_query = array();

		$defaults = array(
			'multisite_taxonomy' => '',
			'multisite_terms'    => array(),
			'field'              => 'multisite_term_id',
			'operator'           => 'IN',
			'include_children'   => true,
		);

		foreach ( $queries as $key => $query ) {
			if ( 'relation' === $key ) {
				$cleaned_query['relation'] = $this->sanitize_relation( $query );
			} elseif ( self::is_first_order_clause( $query ) ) { // First-order clause.

				$cleaned_clause                    = array_merge( $defaults, $query );
				$cleaned_clause['multisite_terms'] = (array) $cleaned_clause['multisite_terms'];
				$cleaned_query[]                   = $cleaned_clause;

				/*
				 * Keep a copy of the clause in the flate
				 * $queried_terms array, for use in WP_Query.
				 */
				if ( ! empty( $cleaned_clause['multisite_taxonomy'] ) && 'NOT IN' !== $cleaned_clause['operator'] ) {
					$multisite_taxonomy = $cleaned_clause['multisite_taxonomy'];
					if ( ! isset( $this->queried_multisite_terms[ $multisite_taxonomy ] ) ) {
						$this->queried_multisite_terms[ $multisite_taxonomy ] = array();
					}

					/*
					 * Backward compatibility: Only store the first
					 * 'multisite_terms' and 'field' found for a given multisite taxonomy.
					 */
					if ( ! empty( $cleaned_clause['multisite_terms'] ) && ! isset( $this->queried_multisite_terms[ $multisite_taxonomy ]['multisite_terms'] ) ) {
						$this->queried_multisite_terms[ $multisite_taxonomy ]['multisite_terms'] = $cleaned_clause['multisite_terms'];
					}

					if ( ! empty( $cleaned_clause['field'] ) && ! isset( $this->queried_multisite_terms[ $multisite_taxonomy ]['field'] ) ) {
						$this->queried_multisite_terms[ $multisite_taxonomy ]['field'] = $cleaned_clause['field'];
					}
				}
			} elseif ( is_array( $query ) ) { // Otherwise, it's a nested query, so we recurse.
				$cleaned_subquery = $this->sanitize_query( $query );

				if ( ! empty( $cleaned_subquery ) ) {
					// All queries with children must have a relation.
					if ( ! isset( $cleaned_subquery['relation'] ) ) {
						$cleaned_subquery['relation'] = 'AND';
					}

					$cleaned_query[] = $cleaned_subquery;
				}
			}
		}

		return $cleaned_query;
	}

	/**
	 * Sanitize a 'relation' operator.
	 *
	 * @since 4.1.0
	 * @access public
	 *
	 * @param string $relation Raw relation key from the query argument.
	 * @return string Sanitized relation ('AND' or 'OR').
	 */
	public function sanitize_relation( $relation ) {
		if ( 'OR' === strtoupper( $relation ) ) {
			return 'OR';
		} else {
			return 'AND';
		}
	}

	/**
	 * Determine whether a clause is first-order.
	 *
	 * A "first-order" clause is one that contains any of the first-order
	 * clause keys ('multisite_terms', 'multisite_taxonomy', 'include_children', 'field',
	 * 'operator'). An empty clause also counts as a first-order clause,
	 * for backward compatibility. Any clause that doesn't meet this is
	 * determined, by process of elimination, to be a higher-order query.
	 *
	 * @since 4.1.0
	 *
	 * @static
	 * @access protected
	 *
	 * @param array $query Tax query arguments.
	 * @return bool Whether the query clause is a first-order clause.
	 */
	protected static function is_first_order_clause( $query ) {
		return is_array( $query ) && ( empty( $query ) || array_key_exists( 'multisite_terms', $query ) || array_key_exists( 'multisite_taxonomy', $query ) || array_key_exists( 'include_children', $query ) || array_key_exists( 'field', $query ) || array_key_exists( 'operator', $query ) );
	}

	/**
	 * Generates SQL clauses to be appended to a main query.
	 *
	 * @since 3.1.0
	 *
	 * @static
	 * @access public
	 *
	 * @param string $primary_table     Database table where the object being filtered is stored (eg wp_users).
	 * @param string $primary_id_column ID column for the filtered object in $primary_table.
	 * @return array {
	 *     Array containing JOIN and WHERE SQL clauses to append to the main query.
	 *
	 *     @type string $join  SQL fragment to append to the main JOIN clause.
	 *     @type string $where SQL fragment to append to the main WHERE clause.
	 * }
	 */
	public function get_sql( $primary_table, $primary_id_column ) {
		$this->primary_table     = $primary_table;
		$this->primary_id_column = $primary_id_column;

		return $this->get_sql_clauses();
	}

	/**
	 * Generate SQL clauses to be appended to a main query.
	 *
	 * Called by the public Multisite_Taxonomy_Query::get_sql(), this method
	 * is abstracted out to maintain parity with the other Query classes.
	 *
	 * @since 4.1.0
	 * @access protected
	 *
	 * @return array {
	 *     Array containing JOIN and WHERE SQL clauses to append to the main query.
	 *
	 *     @type string $join  SQL fragment to append to the main JOIN clause.
	 *     @type string $where SQL fragment to append to the main WHERE clause.
	 * }
	 */
	protected function get_sql_clauses() {
		/*
		 * $queries are passed by reference to get_sql_for_query() for recursion.
		 * To keep $this->queries unaltered, pass a copy.
		 */
		$queries = $this->queries;
		$sql     = $this->get_sql_for_query( $queries );

		if ( ! empty( $sql['where'] ) ) {
			$sql['where'] = ' AND ' . $sql['where'];
		}

		return $sql;
	}

	/**
	 * Generate SQL clauses for a single query array.
	 *
	 * If nested subqueries are found, this method recurses the tree to
	 * produce the properly nested SQL.
	 *
	 * @since 4.1.0
	 * @access protected
	 *
	 * @param array $query Query to parse, passed by reference.
	 * @param int   $depth Optional. Number of tree levels deep we currently are.
	 *                     Used to calculate indentation. Default 0.
	 * @return array {
	 *     Array containing JOIN and WHERE SQL clauses to append to a single query array.
	 *
	 *     @type string $join  SQL fragment to append to the main JOIN clause.
	 *     @type string $where SQL fragment to append to the main WHERE clause.
	 * }
	 */
	protected function get_sql_for_query( &$query, $depth = 0 ) {
		$sql_chunks = array(
			'join'  => array(),
			'where' => array(),
		);

		$sql = array(
			'join'  => '',
			'where' => '',
		);

		$indent = '';
		for ( $i = 0; $i < $depth; $i++ ) {
			$indent .= '  ';
		}

		foreach ( $query as $key => &$clause ) {
			if ( 'relation' === $key ) {
				$relation = $query['relation'];
			} elseif ( is_array( $clause ) ) {
				// This is a first-order clause.
				if ( $this->is_first_order_clause( $clause ) ) {
					$clause_sql = $this->get_sql_for_clause( $clause, $query );

					$where_count = count( $clause_sql['where'] );
					if ( ! $where_count ) {
						$sql_chunks['where'][] = '';
					} elseif ( 1 === $where_count ) {
						$sql_chunks['where'][] = $clause_sql['where'][0];
					} else {
						$sql_chunks['where'][] = '( ' . implode( ' AND ', $clause_sql['where'] ) . ' )';
					}

					$sql_chunks['join'] = array_merge( $sql_chunks['join'], $clause_sql['join'] );
				} else { // This is a subquery, so we recurse.
					$clause_sql = $this->get_sql_for_query( $clause, $depth + 1 );

					$sql_chunks['where'][] = $clause_sql['where'];
					$sql_chunks['join'][]  = $clause_sql['join'];
				}
			}
		}

		// Filter to remove empties.
		$sql_chunks['join']  = array_filter( $sql_chunks['join'] );
		$sql_chunks['where'] = array_filter( $sql_chunks['where'] );

		if ( empty( $relation ) ) {
			$relation = 'AND';
		}

		// Filter duplicate JOIN clauses and combine into a single string.
		if ( ! empty( $sql_chunks['join'] ) ) {
			$sql['join'] = implode( ' ', array_unique( $sql_chunks['join'] ) );
		}

		// Generate a single WHERE clause with proper brackets and indentation.
		if ( ! empty( $sql_chunks['where'] ) ) {
			$sql['where'] = '( ' . "\n  " . $indent . implode( ' ' . "\n  " . $indent . $relation . ' ' . "\n  " . $indent, $sql_chunks['where'] ) . "\n" . $indent . ')';
		}

		return $sql;
	}

	/**
	 * Generate SQL JOIN and WHERE clauses for a "first-order" query clause.
	 *
	 * @since 4.1.0
	 * @access public
	 *
	 * @global wpdb $wpdb The WordPress database abstraction object.
	 *
	 * @param array $clause       Query clause, passed by reference.
	 * @param array $parent_query Parent query array.
	 * @return array {
	 *     Array containing JOIN and WHERE SQL clauses to append to a first-order query.
	 *
	 *     @type string $join  SQL fragment to append to the main JOIN clause.
	 *     @type string $where SQL fragment to append to the main WHERE clause.
	 * }
	 */
	public function get_sql_for_clause( &$clause, $parent_query ) {
		global $wpdb;

		$sql = array(
			'where' => array(),
			'join'  => array(),
		);

		$join  = '';
		$where = '';

		$this->clean_query( $clause );

		if ( is_wp_error( $clause ) ) {
			return self::$no_results;
		}

		$multisite_terms = $clause['multisite_terms'];
		$operator        = strtoupper( $clause['operator'] );

		if ( 'IN' === $operator ) {

			if ( empty( $multisite_terms ) ) {
				return self::$no_results;
			}

			$multisite_terms = implode( ',', $multisite_terms );

			/*
			 * Before creating another table join, see if this clause has a
			 * sibling with an existing join that can be shared.
			 */
			$alias = $this->find_compatible_table_alias( $clause, $parent_query );
			if ( false === $alias ) {
				$i     = count( $this->table_aliases );
				$alias = $i ? 'tt' . $i : $wpdb->multisite_term_relationships;

				// Store the alias as part of a flat array to build future iterators.
				$this->table_aliases[] = $alias;

				// Store the alias with this clause, so later siblings can use it.
				$clause['alias'] = $alias;

				$join .= " LEFT JOIN $wpdb->multisite_term_relationships";
				$join .= $i ? " AS $alias" : '';
				$join .= " ON ($this->primary_table.$this->primary_id_column = $alias.object_id)";
			}

			$where = "$alias.multisite_term_multisite_taxonomy_id $operator ($multisite_terms)";

		} elseif ( 'NOT IN' === $operator ) {

			if ( empty( $multisite_terms ) ) {
				return $sql;
			}

			$multisite_terms = implode( ',', $multisite_terms );

			$where = "$this->primary_table.$this->primary_id_column NOT IN (
				SELECT object_id
				FROM $wpdb->multisite_term_relationships
				WHERE multisite_term_multisite_taxonomy_id IN ($multisite_terms)
			)";

		} elseif ( 'AND' === $operator ) {

			if ( empty( $multisite_terms ) ) {
				return $sql;
			}

			$num_terms = count( $multisite_terms );

			$multisite_terms = implode( ',', $multisite_terms );

			$where = "(
				SELECT COUNT(1)
				FROM $wpdb->multisite_term_relationships
				WHERE multisite_term_multisite_taxonomy_id IN ($multisite_terms)
				AND object_id = $this->primary_table.$this->primary_id_column
			) = $num_terms";

		} elseif ( 'NOT EXISTS' === $operator || 'EXISTS' === $operator ) {

			$where = $wpdb->prepare(
				"%s (
				SELECT 1
				FROM $wpdb->multisite_term_relationships
				INNER JOIN $wpdb->multisite_term_multisite_taxonomy
				ON $wpdb->multisite_term_multisite_taxonomy.multisite_term_multisite_taxonomy_id = $wpdb->multisite_term_relationships.multisite_term_multisite_taxonomy_id
				WHERE $wpdb->multisite_term_multisite_taxonomy.multisite_taxonomy = %s
				AND $wpdb->multisite_term_relationships.object_id = %d
			)", $operator, $clause['multisite_taxonomy'], $this->primary_table . $this->primary_id_column
			);

		}

		$sql['join'][]  = $join;
		$sql['where'][] = $where;
		return $sql;
	}

	/**
	 * Identify an existing table alias that is compatible with the current query clause.
	 *
	 * We avoid unnecessary table joins by allowing each clause to look for
	 * an existing table alias that is compatible with the query that it
	 * needs to perform.
	 *
	 * An existing alias is compatible if (a) it is a sibling of `$clause`
	 * (ie, it's under the scope of the same relation), and (b) the combination
	 * of operator and relation between the clauses allows for a shared table
	 * join. In the case of Multisite_Taxonomy_Query, this only applies to 'IN'
	 * clauses that are connected by the relation 'OR'.
	 *
	 * @since 4.1.0
	 * @access protected
	 *
	 * @param array $clause       Query clause.
	 * @param array $parent_query Parent query of $clause.
	 * @return string|false Table alias if found, otherwise false.
	 */
	protected function find_compatible_table_alias( $clause, $parent_query ) {
		$alias = false;

		// Sanity check. Only IN queries use the JOIN syntax .
		if ( ! isset( $clause['operator'] ) || 'IN' !== $clause['operator'] ) {
			return $alias;
		}

		// Since we're only checking IN queries, we're only concerned with OR relations.
		if ( ! isset( $parent_query['relation'] ) || 'OR' !== $parent_query['relation'] ) {
			return $alias;
		}

		$compatible_operators = array( 'IN' );

		foreach ( $parent_query as $sibling ) {
			if ( ! is_array( $sibling ) || ! $this->is_first_order_clause( $sibling ) ) {
				continue;
			}

			if ( empty( $sibling['alias'] ) || empty( $sibling['operator'] ) ) {
				continue;
			}

			// The sibling must both have compatible operator to share its alias.
			if ( in_array( strtoupper( $sibling['operator'] ), $compatible_operators, true ) ) {
				$alias = $sibling['alias'];
				break;
			}
		}

		return $alias;
	}

	/**
	 * Validates a single query.
	 *
	 * @since 3.2.0
	 * @access private
	 *
	 * @param array $query The single query. Passed by reference.
	 */
	private function clean_query( &$query ) {
		if ( empty( $query['multisite_taxonomy'] ) ) {
			if ( 'multisite_term_multisite_taxonomy_id' !== $query['field'] ) {
				$query = new WP_Error( 'invalid_multisite_taxonomy', __( 'Invalid multisite taxonomy.', 'multitaxo' ) );
				return;
			}

			// So long as there are shared multisite terms, include_children requires that a multisite taxonomy is set.
			$query['include_children'] = false;
		} elseif ( ! multisite_taxonomy_exists( $query['multisite_taxonomy'] ) ) {
			$query = new WP_Error( 'invalid_multisite_taxonomy', __( 'Invalid taxonomy.', 'multitaxo' ) );
			return;
		}

		$query['multisite_terms'] = array_unique( (array) $query['multisite_terms'] );

		if ( is_multisite_taxonomy_hierarchical( $query['multisite_taxonomy'] ) && $query['include_children'] ) {
			$this->transform_query( $query, 'multisite_term_id' );

			if ( is_wp_error( $query ) ) {
				return;
			}

			$children = array();
			foreach ( $query['multisite_terms'] as $multisite_term ) {
				$children   = array_merge( $children, get_multisite_term_children( $multisite_term, $query['multisite_taxonomy'] ) );
				$children[] = $multisite_term;
			}
			$query['multisite_terms'] = $children;
		}

		$this->transform_query( $query, 'multisite_term_multisite_taxonomy_id' );
	}

	/**
	 * Transforms a single query, from one field to another.
	 *
	 * @since 3.2.0
	 *
	 * @global wpdb $wpdb The WordPress database abstraction object.
	 *
	 * @param array  $query           The single query. Passed by reference.
	 * @param string $resulting_field The resulting field. Accepts 'slug', 'name', 'multisite_term_multisite_taxonomy_id',
	 *                                or 'multisite_term_id'. Default 'multisite_term_id'.
	 */
	public function transform_query( &$query, $resulting_field ) {
		global $wpdb;

		if ( empty( $query['multisite_terms'] ) ) {
			return;
		}
		if ( $query['field'] === $resulting_field ) {
			return;
		}
		$resulting_field = sanitize_key( $resulting_field );

		switch ( $query['field'] ) {
			case 'slug':
			case 'name':
				foreach ( $query['multisite_terms'] as &$multisite_term ) {
					/*
					 * 0 is the $multisite_term_id parameter. We don't have a multisite term ID yet, but it doesn't
					 * matter because `sanitize_multisite_term_field()` ignores the $multisite_term_id param when the
					 * context is 'db'.
					 */
					$multisite_term = "'" . esc_sql( sanitize_multisite_term_field( $query['field'], $multisite_term, 0, $query['multisite_taxonomy'], 'db' ) ) . "'";
				}

				$multisite_terms = implode( ',', $query['multisite_terms'] );

				$multisite_terms = $wpdb->get_col(
					$wpdb->prepare(
						"
					SELECT %s
					FROM $wpdb->multisite_term_multisite_taxonomy
					INNER JOIN $wpdb->multisite_terms USING (multisite_term_id)
					WHERE multisite_taxonomy = '{%s}'
					AND $wpdb->multisite_terms.{%s} IN (%s)
				", $wpdb->multisite_term_multisite_taxonomy . $resulting_field, $query['multisite_taxonomy'], $query['field'], $multisite_terms
					)
				);
				break;
			case 'multisite_term_multisite_taxonomy_id':
				$multisite_terms = implode( ',', array_map( 'intval', $query['multisite_terms'] ) );
				$multisite_terms = $wpdb->get_col(
					// @codingStandardsIgnoreStart
					$wpdb->prepare(
						"
					SELECT %s
					FROM $wpdb->multisite_term_multisite_taxonomy
					WHERE multisite_term_multisite_taxonomy_id IN (%s)
				"
					), $resulting_field, $multisite_terms
					// @codingStandardsIgnoreEnd
				);
				break;
			default:
				$multisite_terms = implode( ',', array_map( 'intval', $query['multisite_terms'] ) );
				$multisite_terms = $wpdb->get_col(
					$wpdb->prepare(
						"
					SELECT %s
					FROM $wpdb->multisite_term_multisite_taxonomy
					WHERE multisite_taxonomy = '{%s}'
					AND multisite_term_id IN (%s)
				", $resulting_field, $query['multisite_taxonomy'], $multisite_terms
					)
				);
		}

		if ( 'AND' === $query['operator'] && count( $multisite_terms ) < count( $query['multisite_terms'] ) ) {
			$query = new WP_Error( 'inexistent_multisite_terms', __( 'Inexistent multisite terms.', 'multitaxo' ) );
			return;
		}

		$query['multisite_terms'] = $multisite_terms;
		$query['field']           = $resulting_field;
	}
}
