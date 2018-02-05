<?php
/**
 * List Table API: Multisite_Terms_List_Table class
 *
 * @package multitaxo
 */

/**
 * Core class used to implement displaying multiste terms in a list table.
 *
 * @access private
 *
 * @see WP_List_Table
 */
class Multisite_Terms_List_Table extends WP_List_Table {
	/**
	 * $callback_args
	 *
	 * @var mixed
	 */
	public $callback_args;

	/**
	 * $level
	 *
	 * @var mixed
	 */
	private $level;

	/**
	 * Constructor.
	 *
	 * @access public
	 *
	 * @see WP_List_Table::__construct() for more information on default arguments.
	 *
	 * @global string $post_type
	 * @global string $multisite_taxonomy
	 * @global string $action
	 * @global object $mu_tax
	 *
	 * @param array $args An associative array of arguments.
	 */
	public function __construct( $args = array() ) {
		global $post_type, $multisite_taxonomy, $action, $mu_tax;

		parent::__construct(
			array(
				'plural'   => 'tags',
				'singular' => 'tag',
				'screen'   => isset( $args['screen'] ) ? $args['screen'] : get_current_screen(),
			)
		);

		$action             = $this->screen->action; // WPCS: override ok.
		$post_type          = $this->screen->post_type; // WPCS: override ok.
		$multisite_taxonomy = $this->screen->taxonomy;

		if ( empty( $multisite_taxonomy ) || ! multisite_taxonomy_exists( $multisite_taxonomy ) ) {
			wp_die( esc_html__( 'Invalid multisite taxonomy.', 'multitaxo' ) );
		}

		$mu_tax = get_multisite_taxonomy( $multisite_taxonomy ); // WPCS: override ok.
	}

	/**
	 * Check if ajax user can manage multiste terms
	 *
	 * @return bool
	 */
	public function ajax_user_can() {
		return current_user_can( get_multisite_taxonomy( $this->screen->taxonomy )->cap->manage_multisite_terms );
	}

	/**
	 * Function that prepare the items.
	 *
	 * @access public
	 */
	public function prepare_items() {
		$tags_per_page = $this->get_items_per_page( 'edit_multisite_tax_per_page' );

		if ( ! empty( $_REQUEST['s'] ) ) { // WPCS: CSRF ok. input var okay.
			$search = trim( sanitize_text_field( wp_unslash( $_REQUEST['s'] ) ) ); // WPCS: CSRF ok. input var okay.
		} else {
			$search = '';
		}

		$args = array(
			'search'   => $search,
			'page'     => $this->get_pagenum(),
			'number'   => $tags_per_page,
			'taxonomy' => $this->screen->taxonomy,
		);

		$page = $args['page'];

		// Set variable because $args['number'] can be subsequently overridden.
		$number = $args['number'];

		$args['offset'] = ( $page - 1 ) * $number;
		$offset         = $args['offset'];
		// Convert it to table rows.
		$count = 0;

		if ( ! empty( $_REQUEST['orderby'] ) ) { // WPCS: CSRF ok. input var okay.
			$args['orderby'] = trim( sanitize_text_field( wp_unslash( $_REQUEST['orderby'] ) ) ); // WPCS: CSRF ok. input var okay.
		}

		if ( ! empty( $_REQUEST['order'] ) ) { // WPCS: CSRF ok. input var okay.
			$args['order'] = trim( sanitize_text_field( wp_unslash( $_REQUEST['order'] ) ) ); // WPCS: CSRF ok. input var okay.
		}

		if ( is_multisite_taxonomy_hierarchical( $this->screen->taxonomy ) && ! isset( $args['orderby'] ) ) {
			// We'll need the full set of multiste terms then.
			$args['offset'] = 0;
		}

		$args = wp_parse_args(
			$args, array(
				'page'       => 1,
				'number'     => 20,
				'search'     => '',
				'hide_empty' => 0,
				'taxonomy'   => $this->screen->taxonomy,
			)
		);

		$this->callback_args = $args;

		$this->items = get_multisite_terms( $args );

		$this->set_pagination_args(
			array(
				'total_items' => count_multisite_terms(
					$this->screen->taxonomy, array(
						'search' => $search,
					)
				),
				'per_page'    => $tags_per_page,
			)
		);
	}

	/**
	 * Text to display when no items
	 *
	 * @return void
	 */
	public function no_items() {
		echo esc_html( get_multisite_taxonomy( $this->screen->taxonomy )->labels->not_found );
	}

	/**
	 * Getting all actions
	 *
	 * @return array $actions An array of actions.
	 */
	protected function get_bulk_actions() {
		$actions = array();

		if ( current_user_can( get_multisite_taxonomy( $this->screen->taxonomy )->cap->delete_multisite_terms ) ) {
			$actions['delete'] = __( 'Delete', 'multitaxo' );
		}

		return $actions;
	}

	/**
	 * The current action.
	 *
	 * @return string
	 */
	public function current_action() {
		if ( isset( $_REQUEST['action'] ) && isset( $_REQUEST['delete_multisite_terms'] ) && ( 'delete' === $_REQUEST['action'] || ( isset( $_REQUEST['action2'] ) && 'delete' === $_REQUEST['action2'] ) ) ) { // WPCS: CSRF ok. input var okay.
			return 'bulk-delete';
		}
		return parent::current_action();
	}

	/**
	 * Get the columns.
	 *
	 * @return array
	 */
	public function get_columns() {
		$columns = array(
			'cb'          => '<input type="checkbox" />',
			'name'        => _x( 'Name', 'multisite term name', 'multitaxo' ),
			'description' => __( 'Description', 'multitaxo' ),
			'slug'        => __( 'Slug', 'multitaxo' ),
		);

		if ( 'link_category' === $this->screen->taxonomy ) {
			$columns['links'] = __( 'Links', 'multitaxo' );
		} else {
			$columns['posts'] = _x( 'Count', 'Number/count of items', 'multitaxo' );
		}

		return $columns;
	}

	/**
	 * Get the sortable columns.
	 *
	 * @return array
	 */
	protected function get_sortable_columns() {
		return array(
			'name'        => 'name',
			'description' => 'description',
			'slug'        => 'slug',
			'posts'       => 'count',
			'links'       => 'count',
		);
	}

	/**
	 * Display either the rows or the empty placeholder.
	 *
	 * @access public
	 */
	public function display_rows_or_placeholder() {
		$multisite_taxonomy = $this->screen->taxonomy;

		$args = wp_parse_args(
			$this->callback_args, array(
				'page'       => 1,
				'number'     => 20,
				'search'     => '',
				'hide_empty' => 0,
				'taxonomy'   => $multisite_taxonomy,
			)
		);

		$page = $args['page'];

		// Set variable because $args['number'] can be subsequently overridden.
		$number = $args['number'];

		$args['offset'] = ( $page - 1 ) * $number;
		$offset         = $args['offset'];
		// Convert it to table rows.
		$count = 0;

		if ( is_multisite_taxonomy_hierarchical( $multisite_taxonomy ) && ! isset( $args['orderby'] ) ) {
			// We'll need the full set of multiste terms then.
			$args['number'] = 0;
			$args['offset'] = 0;
		}

		$multisite_terms = $this->items;

		if ( empty( $multisite_terms ) || ! is_array( $multisite_terms ) ) {
			echo '<tr class="no-items"><td class="colspanchange" colspan="' . esc_attr( $this->get_column_count() ) . '">';
			$this->no_items();
			echo '</td></tr>';
			return;
		}

		if ( is_multisite_taxonomy_hierarchical( $multisite_taxonomy ) && ! isset( $args['orderby'] ) ) {
			if ( ! empty( $args['search'] ) ) { // Ignore children on searches.
				$children = array();
			} else {
				$children = _get_multisite_term_hierarchy( $multisite_taxonomy );
			}
			// Some funky recursion to get the job done( Paging & parents mainly ) is contained within, Skip it for non-hierarchical taxonomies for performance sake.
			$this->_rows( $multisite_taxonomy, $multisite_terms, $children, $offset, $number, $count );
		} else {
			foreach ( $multisite_terms as $multisite_term ) {
				$this->single_row( $multisite_term );
			}
		}
	}

	/**
	 * Display multiple rows at once
	 *
	 * @param string $multisite_taxonomy The multisite taxonomy.
	 * @param array  $multisite_terms The multisite terms.
	 * @param array  $children The multisite terms children.
	 * @param int    $start The start arg for pagination.
	 * @param int    $per_page The num,ber of terms per page.
	 * @param int    $count How many total terms do we have.
	 * @param int    $parent The parent taxonomy.
	 * @param int    $level The current level of hierarchy.
	 */
	private function _rows( $multisite_taxonomy, $multisite_terms, &$children, $start, $per_page, &$count, $parent = 0, $level = 0 ) { // phpcs:ignore Generic,PSR2

		$end = $start + $per_page;

		foreach ( $multisite_terms as $key => $multisite_term ) {

			if ( (int) $count >= (int) $end ) {
				break;
			}

			if ( $multisite_term->parent !== $parent && empty( $_REQUEST['s'] ) ) { // WPCS: CSRF ok. input var okay.
				continue;
			}

			// If the page starts in a subtree, print the parents.
			if ( (int) $count === (int) $start && $multisite_term->parent > 0 && empty( $_REQUEST['s'] ) ) { // WPCS: CSRF ok. input var okay.
				$my_parents = array();
				$parent_ids = array();
				$p          = $multisite_term->parent;
				while ( $p ) {
					$my_parent    = get_multisite_term( $p, $multisite_taxonomy );
					$my_parents[] = $my_parent;
					$p            = $my_parent->parent;
					if ( in_array( $p, $parent_ids, true ) ) { // Prevent parent loops.
						break;
					}
					$parent_ids[] = $p;
				}
				unset( $parent_ids );

				$num_parents = count( $my_parents );
				while ( $my_parents ) {
					$my_parent = array_pop( $my_parents );
					echo "\t";
					$this->single_row( $my_parent, $level - $num_parents );
					$num_parents--;
				}
			}

			if ( $count >= $start ) {
				echo "\t";
				$this->single_row( $multisite_term, $level );
			}

			++$count;

			unset( $multisite_terms[ $key ] );

			if ( isset( $children[ $multisite_term->multisite_term_id ] ) && empty( $_REQUEST['s'] ) ) { // WPCS: CSRF ok. input var okay.
				$this->_rows( $multisite_taxonomy, $multisite_terms, $children, $start, $per_page, $count, $multisite_term->multisite_term_id, $level + 1 );
			}
		} // End foreach().
	}

	/**
	 * Display one row.
	 *
	 * @global string        $multisite_taxonomy
	 * @param Multisite_Term $multisite_term Multisite term object.
	 * @param int            $level The levele of the hierachy.
	 * @return void
	 */
	public function single_row( $multisite_term, $level = 0 ) {
		$multisite_term = sanitize_multisite_term( $multisite_term, $this->taxonomy );

		$this->level = $level;

		echo '<tr id="tag-' . esc_attr( $multisite_term->multisite_term_id ) . '">';
		$this->single_row_columns( $multisite_term );
		echo '</tr>';
	}

	/**
	 * Column check boxes content.
	 *
	 * @param Multisite_Term $multisite_term Multisite term object.
	 * @return string
	 */
	public function column_cb( $multisite_term ) {
		if ( current_user_can( 'delete_multisite_term', $multisite_term->multisite_term_id ) ) {
			/* translators: %s: multisite term name */
			return '<label class="screen-reader-text" for="cb-select-' . esc_attr( $multisite_term->multisite_term_id ) . '">' . sprintf( __( 'Select %s', 'multitaxo' ), $multisite_term->name ) . '</label>'
				. '<input type="checkbox" name="delete_multisite_terms[]" value="' . esc_attr( $multisite_term->multisite_term_id ) . '" id="cb-select-' . esc_attr( $multisite_term->multisite_term_id ) . '" />';
		}

		return '&nbsp;';
	}

	/**
	 * Return column's name content
	 *
	 * @param Multisite_Term $multisite_term Multisite term object.
	 * @return string
	 */
	public function column_name( $multisite_term ) {
		$multisite_taxonomy = $this->screen->taxonomy;

		$pad = str_repeat( '&#8212; ', max( 0, $this->level ) );

		/**
		 * Filters display of the multisite term name in the multiste terms list table.
		 *
		 * The default output may include padding due to the multisite term's
		 * current level in the term hierarchy.
		 *
		 * @see Multisite_Terms_List_Table::column_name()
		 *
		 * @param string $pad_tag_name The multisite term name, padded if not top-level.
		 * @param Multisite_Term $multisite_term         Multisite term object.
		 */
		$name = apply_filters( 'multisite_term_name', $pad . ' ' . $multisite_term->name, $multisite_term );

		$qe_data = get_multisite_term( $multisite_term->multisite_term_id, $multisite_taxonomy, OBJECT, 'edit' );

		if ( wp_doing_ajax() ) {
			$uri = wp_get_referer();
		} else {
			if ( isset( $_SERVER['REQUEST_URI'] ) ) { // WPCS: CSRF ok. input var okay.
				$uri = $_SERVER['REQUEST_URI']; // WPCS: sanitization ok. input var okay.
			}
		}

		$edit_link = add_query_arg(
			'wp_http_referer',
			rawurlencode( wp_unslash( $uri ) ),
			get_edit_multisite_term_link( $multisite_term->multisite_term_id, $multisite_taxonomy, $this->screen->post_type )
		);

		$out = sprintf(
			'<strong><a class="row-title" href="%s" aria-label="%s">%s</a></strong><br />',
			esc_url( $edit_link ),
			/* translators: %s: multisite term name */
			esc_attr( sprintf( __( '&#8220;%s&#8221; (Edit)', 'multitaxo' ), $multisite_term->name ) ),
			$name
		);

		$out .= '<div class="hidden" id="inline_' . esc_attr( $qe_data->multisite_term_id ) . '">';
		$out .= '<div class="name">' . esc_html( $qe_data->name ) . '</div>';

		/** This filter is documented in wp-admin/edit-tag-form.php */
		$out .= '<div class="slug">' . apply_filters( 'editable_slug', $qe_data->slug, $qe_data ) . '</div>';
		$out .= '<div class="parent">' . $qe_data->parent . '</div></div>';

		return $out;
	}

	/**
	 * Gets the name of the default primary column.
	 *
	 * @access protected
	 *
	 * @return string Name of the default primary column, in this case, 'name'.
	 */
	protected function get_default_primary_column_name() {
		return 'name';
	}

	/**
	 * Generates and displays row action links.
	 *
	 * @access protected
	 *
	 * @param Multisite_Term $multisite_term  Multisite term being acted upon.
	 * @param string         $column_name     Current column name.
	 * @param string         $primary         Primary column name.
	 * @return string Row actions output for multiste terms.
	 */
	protected function handle_row_actions( $multisite_term, $column_name, $primary ) {
		if ( $primary !== $column_name ) {
			return '';
		}

		$multisite_taxonomy = $this->screen->taxonomy;
		$mu_tax             = get_multisite_taxonomy( $multisite_taxonomy );
		if ( wp_doing_ajax() ) {
			$uri = wp_get_referer();
		} else {
			if ( isset( $_SERVER['REQUEST_URI'] ) ) { // WPCS: CSRF ok. input var okay.
				$uri = $_SERVER['REQUEST_URI']; // WPCS: sanitization ok. input var okay.
			}
		}

		$edit_link = add_query_arg(
			'wp_http_referer',
			rawurlencode( wp_unslash( $uri ) ),
			get_edit_multisite_term_link( $multisite_term->multisite_term_id, $multisite_taxonomy )
		);

		$actions = array();
		if ( current_user_can( 'edit_multisite_term', $multisite_term->multisite_term_id ) ) {
			$actions['edit'] = sprintf(
				'<a href="%s" aria-label="%s">%s</a>',
				esc_url( $edit_link ),
				/* translators: %s: multisite term name */
				esc_attr( sprintf( __( 'Edit &#8220;%s&#8221;', 'multitaxo' ), $multisite_term->name ) ),
				__( 'Edit', 'multitaxo' )
			);
			$actions['inline hide-if-no-js'] = sprintf(
				'<a href="#" class="editinline aria-button-if-js" aria-label="%s">%s</a>',
				/* translators: %s: multisite term name */
				esc_attr( sprintf( __( 'Quick edit &#8220;%s&#8221; inline', 'multitaxo' ), $multisite_term->name ) ),
				__( 'Quick&nbsp;Edit', 'multitaxo' )
			);
		}
		if ( current_user_can( 'delete_multisite_term', $multisite_term->multisite_term_id ) ) {
			$actions['delete'] = sprintf(
				'<a href="%s" class="delete-multisite-term aria-button-if-js" aria-label="%s">%s</a>',
				wp_nonce_url(
					add_query_arg(
						array(
							'action'            => 'delete',
							'multisite_term_id' => $multisite_term->multisite_term_id,
						)
					), 'delete-multisite_term_' . $multisite_term->multisite_term_id
				),
				/* translators: %s: multisite term name */
				esc_attr( sprintf( __( 'Delete &#8220;%s&#8221;', 'multitaxo' ), $multisite_term->name ) ),
				__( 'Delete', 'multitaxo' )
			);
		}

		/**
		 * Filters the action links displayed for each multisite term in the multiste terms list table.
		 *
		 * The dynamic portion of the hook name, `$multisite_taxonomy`, refers to the taxonomy slug.
		 *
		 * @param array  $actions An array of action links to be displayed. Default
		 *                        'Edit', 'Quick Edit', 'Delete', and 'View'.
		 * @param Multisite_Term $multisite_term    Term object.
		 */
		$actions = apply_filters( "multitiste_taxonomy_{$multisite_taxonomy}_row_actions", $actions, $multisite_term );

		return $this->row_actions( $actions );
	}

	/**
	 * Get column description content.
	 *
	 * @param Multisite_Term $multisite_term Multisite term object.
	 * @return string
	 */
	public function column_description( $multisite_term ) {
		return $multisite_term->description;
	}

	/**
	 * Get the slug column content.
	 *
	 * @param Multisite_Term $multisite_term Multisite term object.
	 * @return string
	 */
	public function column_slug( $multisite_term ) {
		return apply_filters( 'editable_slug', $multisite_term->slug, $multisite_term );
	}

	/**
	 * Get post count column content.
	 *
	 * @param Multisite_Term $multisite_term Multisite term object.
	 * @return string
	 */
	public function column_posts( $multisite_term ) {
		$count = number_format_i18n( $multisite_term->count );

		$mu_tax = get_multisite_taxonomy( $this->screen->taxonomy );

		if ( $mu_tax->query_var ) {
			$args = array(
				$mu_tax->query_var => $multisite_term->slug,
			);
		} else {
			$args = array(
				'multisite_taxonomy' => $mu_tax->name,
				'multisite_term'     => $multisite_term->slug,
			);
		}

		return "<a href='" . esc_url( add_query_arg( $args, 'edit.php' ) ) . "'>$count</a>";
	}

	/**
	 * Get links column.
	 *
	 * @param Multisite_Term $multisite_term Multisite term object.
	 * @return string
	 */
	public function column_links( $multisite_term ) {
		$count = number_format_i18n( $multisite_term->count );
		if ( $count ) {
			$count = "<a href='link-manager.php?cat_id=$multisite_term->multisite_term_id'>$count</a>";
		}
		return $count;
	}

	/**
	 * Default template for costum column added by plugins.
	 *
	 * @param Multisite_Term $multisite_term Multisite term object.
	 * @param string         $column_name The column name.
	 * @return string
	 */
	public function column_default( $multisite_term, $column_name ) {
		/**
		 * Filters the displayed columns in the multiste terms list table.
		 *
		 * The dynamic portion of the hook name, `$this->screen->taxonomy`,
		 * refers to the slug of the current taxonomy.
		 *
		 * @param string $string      Blank string.
		 * @param string $column_name Name of the column.
		 * @param int    $multisite_term_id     Term ID.
		 */
		return apply_filters( "manage_{$this->screen->taxonomy}_custom_column", '', $column_name, $multisite_term->multisite_term_id );
	}

	/**
	 * Outputs the hidden row displayed when inline editing.
	 */
	public function inline_edit() {
		$mu_tax = get_multisite_taxonomy( $this->screen->taxonomy );

		if ( ! current_user_can( $mu_tax->cap->edit_multisite_terms ) ) {
			return;
		}
		?>

		<form method="get"><table style="display: none"><tbody id="inlineedit">
			<tr id="inline-edit" class="inline-edit-row" style="display: none"><td colspan="<?php echo esc_attr( $this->get_column_count() ); ?>" class="colspanchange">

				<fieldset>
					<legend class="inline-edit-legend"><?php esc_html_e( 'Quick Edit', 'multitaxo' ); ?></legend>
					<div class="inline-edit-col">
						<label>
							<span class="title"><?php echo esc_html_x( 'Name', 'term name', 'multitaxo' ); ?></span>
							<span class="input-text-wrap"><input type="text" name="name" class="ptitle" value="" /></span>
						</label>
						<label>
							<span class="title"><?php esc_html_e( 'Slug', 'multitaxo' ); ?></span>
							<span class="input-text-wrap"><input type="text" name="slug" class="ptitle" value="" /></span>
						</label>
					</div>
				</fieldset>
		<?php

		$core_columns = array(
			'cb'          => true,
			'description' => true,
			'name'        => true,
			'slug'        => true,
			'posts'       => true,
		);

		list( $columns ) = $this->get_column_info();
		foreach ( $columns as $column_name => $column_display_name ) {
			if ( isset( $core_columns[ $column_name ] ) ) {
				continue;
			}
			do_action( 'quick_edit_custom_box', $column_name, 'edit-multisite-terms', $this->screen->taxonomy );
		}

		?>

		<p class="inline-edit-save submit">
			<button type="button" class="cancel button alignleft"><?php esc_html_e( 'Cancel', 'multitaxo' ); ?></button>
			<button type="button" class="save button button-primary alignright"><?php echo esc_html( $mu_tax->labels->update_item ); ?></button>
			<span class="spinner"></span>
			<span class="error" style="display:none;"></span>
			<?php wp_nonce_field( 'ajax_edit_multisite_tax', 'nonce_multisite_inline_edit', false ); ?>
			<input type="hidden" name="taxonomy" value="<?php echo esc_attr( $this->screen->taxonomy ); ?>" />
			<input type="hidden" name="screen" value="<?php echo esc_attr( $this->screen->id ); ?>" />
			<br class="clear" />
		</p>
		</td></tr>
		</tbody></table></form>
	<?php
	}
}
