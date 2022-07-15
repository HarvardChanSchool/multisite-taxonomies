<?php
/**
 * Multisite Taxonomies Settings init class
 *
 * @package multitaxo
 */

/**
 * Settings screens init class.
 */
class Multisite_Taxonomy_Meta_Box {
	/**
	 * __construct function.
	 *
	 * @access public
	 * @return void
	 */
	public function __construct() {
		// We enqueue both the frontend and admin styles and scripts.
		add_action( 'add_meta_boxes', array( $this, 'add_multsite_taxonomy_meta_box' ), 10, 2 );

		// Add the admin scripts to the posts pages.
		add_action( 'admin_enqueue_scripts', array( $this, 'admin_enqueue_styles_and_scripts' ) );

		// register the ajax response for creating new terms.
		add_action( 'wp_ajax_ajax-multisite-tag-search', array( $this, 'wp_ajax_ajax_multisite_terms_search' ) );
		add_action( 'wp_ajax_ajax-get-multisite-term-cloud', array( $this, 'wp_ajax_get_multisite_term_cloud' ) );

		// Save the post Box.
		add_action( 'save_post', array( $this, 'save_multisite_taxonomy' ) );
	}

	/**
	 * Display the metabox container if we should use it.
	 *
	 * @param string  $post_type The WP Post type.
	 * @param WP_Post $post Post object.
	 *
	 * @return void
	 */
	public function add_multsite_taxonomy_meta_box( $post_type, $post ) {
		if ( count( (array) get_object_multisite_taxonomies( $post_type ) ) > 0 && ( current_user_can( 'assign_multisite_terms' ) ) ) {
			add_meta_box( 'multsite_taxonomy_meta_box', esc_html__( 'Multisite Tags', 'multitaxo' ), array( $this, 'multisite_taxonomy_meta_box_callback' ), null, 'advanced', 'default', array( $post, $post_type ) );
		}
	}

	/**
	 * Enqueue scripts and styles for our metabox.
	 *
	 * @param string $hook page hook.
	 *
	 * @return void
	 */
	public function admin_enqueue_styles_and_scripts( $hook ) {
		// We only need the scripts and styles on the edit/new post pages.
		if ( 'post.php' !== $hook && 'post-new.php' !== $hook ) {
			return;
		}

		wp_enqueue_script( 'multisite-taxonomy-suggest', MULTITAXO_ASSETS_URL . '/js/multisite-taxonomy-suggest.js', array( 'jquery', 'jquery-ui-core', 'jquery-ui-autocomplete', 'wp-a11y', 'tags-suggest' ), MULTITAXO_VERSION, 1 );
		wp_localize_script(
			'multisite-taxonomy-suggest',
			'multiTaxL10n',
			array(
				'tagDelimiter' => _x( ',', 'tag delimiter', 'multitaxo' ),
				'removeTerm'   => __( 'Remove term:', 'multitaxo' ),
				'termSelected' => __( 'Term selected.', 'multitaxo' ),
				'termAdded'    => __( 'Term added.', 'multitaxo' ),
				'termRemoved'  => __( 'Term removed.', 'multitaxo' ),
			)
		);

		wp_enqueue_script( 'multisite-taxonomy-box', MULTITAXO_ASSETS_URL . '/js/multisite-taxonomy-box.js', array( 'multisite-taxonomy-suggest', 'jquery-ui-tabs' ), MULTITAXO_VERSION, 1 );
		wp_localize_script(
			'multisite-taxonomy-box',
			'mtaxsecurity',
			array(
				'noncesearch' => wp_create_nonce( 'nonce-multisite-terms-search' ),
				'noncecloud'  => wp_create_nonce( 'nonce-multisite-term-cloud' ),
			)
		);

		wp_enqueue_script( 'hierarchical-multisite-taxonomy-box', MULTITAXO_ASSETS_URL . '/js/multisite-hierarchical-term-box.js', array( 'jquery-ui-tabs' ), MULTITAXO_VERSION, 1 );

		wp_enqueue_style( 'multisite-taxonomy-meta-box', MULTITAXO_ASSETS_URL . '/css/admin.css', array(), MULTITAXO_VERSION );
	}

	/**
	 * Display the meta box content.
	 *
	 * @param string  $post The WP Post type.
	 * @param WP_Post $metabox Post object.
	 *
	 * @return void
	 */
	public function multisite_taxonomy_meta_box_callback( $post, $metabox ) {
		$taxonomies = get_multisite_taxonomies( array(), 'objects' );

		$tabs         = array();
		$tab_contents = array();

		?>
		<div id="multisite-tax-picker">
			<ul>
		<?php

		foreach ( $taxonomies as $tax ) {
			// Are we hierarchical or not?
			$hierarchical = ( true === $tax->hierarchical ) ? 'hierarchical-' : 'flat-';

			// Set up the tab itself.
			?>
			<li><a href="#tabs-<?php echo esc_attr( $hierarchical ) . esc_attr( $tax->name ); ?>"><?php echo esc_html( $tax->labels->name ); ?></a></li>
			<?php
		}

		?>
		</ul>
		<?php

		// and lets do this again for the boxes.
		reset( $taxonomies );

		// loop and loop.
		foreach ( $taxonomies as $tax ) {
			// Are we hierarchical or not?
			$hierarchical = ( true === $tax->hierarchical ) ? 'hierarchical-' : 'flat-';

			?>
			<div id="tabs-<?php echo esc_attr( $hierarchical ) . esc_attr( $tax->name ); ?>" class="multi-taxonomy-tab">
				<h2><?php echo esc_html( $tax->labels->name ); ?></h2>
			<?php

			$args = array(
				'title'    => $tax->labels->name,
				'taxonomy' => $tax->name,
				'args'     => array(),
			);

			// Are we hierarchical-term or not?
			if ( true === $tax->hierarchical ) {
				$this->hierarchical_multisite_taxonomy_meta_box( $post, $args );
			} else {
				$this->multisite_taxonomy_meta_box( $post, $args );
			}

			?>
			</div>
			<?php
		}

		?>
		</div>
		<?php
	}

	/**
	 * Display post tags form fields.
	 *
	 * @since 2.6.0
	 *
	 * @todo Create taxonomy-agnostic wrapper for this.
	 *
	 * @param WP_Post $post Post object.
	 * @param array   $args {
	 *     Tags meta box arguments.
	 *
	 *     @type string   $taxonomy Taxonomy corresponding.
	 *     @type string   $title    Meta box title.
	 *     @type array    $args {
	 *         Extra meta box arguments.
	 *     }
	 * }
	 */
	public function multisite_taxonomy_meta_box( $post, $args ) {
		if ( ! isset( $args['taxonomy'] ) ) {
			return false;
		}

		$defaults              = array();
		$r                     = wp_parse_args( $args, $defaults );
		$tax_name              = esc_attr( $r['taxonomy'] );
		$taxonomy              = get_multisite_taxonomy( $r['taxonomy'] );
		$user_can_assign_terms = current_user_can( $taxonomy->cap->assign_multisite_terms );
		$comma                 = _x( ',', 'tag delimiter', 'multitaxo' );
		$terms_to_edit         = get_multisite_terms_to_edit( $post->ID, $tax_name );

		if ( ! is_string( $terms_to_edit ) ) {
			$terms_to_edit = '';
		}

		// Add an nonce field so we can check for it later.
		wp_nonce_field( 'multisite_taxonomy_meta_box', 'multisite_taxonomy_meta_box_nonce' );
		?>
	<div class="multitaxonomydiv" id="multi-taxonomy-<?php echo esc_attr( $tax_name ); ?>">
		<div class="ajaxtaxonomy">
		<div class="nojs-taxonomy hide-if-js">
			<label for="multi-tax-input-<?php echo esc_attr( $tax_name ); ?>"><?php echo esc_html( $taxonomy->labels->add_or_remove_items ); ?></label>
			<p><textarea name="<?php echo esc_attr( "multi_tax_input[$tax_name]" ); ?>" rows="3" cols="20" class="the-multi-taxonomy" id="multi-tax-input-<?php echo esc_attr( $tax_name ); ?>" <?php disabled( ! $user_can_assign_terms ); ?> aria-describedby="new-taxonomy-<?php echo esc_attr( $tax_name ); ?>-desc"><?php echo esc_textarea( str_replace( ',', $comma . ' ', $terms_to_edit ) ); ?></textarea></p>
		</div>
		<?php if ( $user_can_assign_terms ) : ?>
		<div class="ajaxmultitaxonomy hide-if-no-js">
			<label class="screen-reader-text" for="new-multi-taxonomy-<?php echo esc_attr( $tax_name ); ?>"><?php echo esc_html( $taxonomy->labels->add_new_item ); ?></label>
			<p><input data-multi-taxonomy="<?php echo esc_attr( $tax_name ); ?>" type="text" id="new-multi-taxonomy-<?php echo esc_attr( $tax_name ); ?>" name="new_multi_taxonomy[<?php echo esc_attr( $tax_name ); ?>]" class="newmultiterm form-input-tip" size="16" autocomplete="off" aria-describedby="new-multi-taxonomy-<?php echo esc_attr( $tax_name ); ?>-desc" value="" />
			<input type="button" class="button multitermadd" value="<?php esc_attr_e( 'Add', 'multitaxo' ); ?>" /></p>
		</div>
		<p class="howto" id="new-multi-taxonomy-<?php echo esc_attr( $tax_name ); ?>-desc"><?php echo esc_html( $taxonomy->labels->separate_items_with_commas ); ?></p>
		<?php elseif ( empty( $terms_to_edit ) ) : ?>
			<p><?php echo esc_html( $taxonomy->labels->no_terms ); ?></p>
		<?php endif; ?>
		</div>
		<ul class="multitaxonomychecklist" role="list"></ul>
	</div>
		<?php if ( $user_can_assign_terms ) : ?>
	<p class="hide-if-no-js"><button type="button" class="button-link multitaxonomycloud-link" id="link-<?php echo esc_attr( $tax_name ); ?>" aria-expanded="false"><?php echo esc_html( $taxonomy->labels->choose_from_most_used ); ?></button></p>
	<?php endif; ?>
		<?php
	}

	/**
	 * Display post hierarchical-term form fields.
	 *
	 * @since 2.6.0
	 *
	 * @todo Create taxonomy-agnostic wrapper for this.
	 *
	 * @param WP_Post $post Post object.
	 * @param array   $args {
	 *     hierarchical-term meta box arguments.
	 *
	 *     @type string   $id       Meta box 'id' attribute.
	 *     @type string   $title    Meta box title.
	 *     @type callable $callback Meta box display callback.
	 *     @type array    $args {
	 *         Extra meta box arguments.
	 *
	 *         @type string $taxonomy Taxonomy. Default 'hierarchical-term'.
	 *     }
	 * }
	 */
	public function hierarchical_multisite_taxonomy_meta_box( $post, $args ) {
		if ( ! isset( $args['taxonomy'] ) ) {
			return false;
		}

		$defaults = array();
		$r        = wp_parse_args( $args, $defaults );
		$tax_name = esc_attr( $r['taxonomy'] );
		$taxonomy = get_multisite_taxonomy( $r['taxonomy'] );
		?>
		<div id="taxonomy-<?php echo esc_attr( $tax_name ); ?>" class="multisite-hierarchical-taxonomy-div">
			<ul id="<?php echo esc_attr( $tax_name ); ?>-tabs" class="hierarchical-term-tabs">
				<li class="tabs"><a href="#<?php echo esc_attr( $tax_name ); ?>-all"><?php echo esc_html( $taxonomy->labels->all_items ); ?></a></li>
				<li class="hide-if-no-js"><a href="#<?php echo esc_attr( $tax_name ); ?>-pop"><?php echo esc_html( $taxonomy->labels->most_used ); ?></a></li>
			</ul>

			<div id="<?php echo esc_attr( $tax_name ); ?>-pop" class="tabs-panel" style="display: none;">
				<ul id="<?php echo esc_attr( $tax_name ); ?>checklist-pop" class="hierarchical-term-checklist form-no-clear" >
					<?php $popular_ids = popular_multisite_terms_checklist( $tax_name ); ?>
				</ul>
			</div>

			<div id="<?php echo esc_attr( $tax_name ); ?>-all" class="tabs-panel">
				<?php
				echo '<input type="hidden" name="multi_tax_input[' . esc_attr( $tax_name ) . '][]" value="0" />'; // Allows for an empty term set to be sent. 0 is an invalid Term ID and will be ignored by empty() checks.
				?>
				<ul id="<?php echo esc_attr( $tax_name ); ?>checklist" data-wp-lists="list:<?php echo esc_attr( $tax_name ); ?>" class="hierarchical-term-checklist form-no-clear">
					<?php
					multisite_terms_checklist(
						$post->ID,
						array(
							'taxonomy'      => $tax_name,
							'popular_terms' => $popular_ids,
						)
					);
					?>
				</ul>
			</div>
		<?php if ( current_user_can( $taxonomy->cap->edit_multisite_terms ) ) : ?>
				<div id="<?php echo esc_attr( $tax_name ); ?>-adder" class="wp-hidden-children">
					<a id="<?php echo esc_attr( $tax_name ); ?>-add-toggle" href="#<?php echo esc_attr( $tax_name ); ?>-add" class="hide-if-no-js taxonomy-add-new">
						<?php
							/* translators: %s: add new taxonomy label */
							printf( esc_html__( '+ %s', 'multitaxo' ), esc_html( $taxonomy->labels->add_new_item ) );
						?>
					</a>
					<p id="<?php echo esc_attr( $tax_name ); ?>-add" class="multisite-hierarchical-term-add wp-hidden-child">
						<label class="screen-reader-text" for="new_multisite_<?php echo esc_attr( $tax_name ); ?>"><?php echo esc_html( $taxonomy->labels->add_new_item ); ?></label>
						<input type="text" name="new_multisite_<?php echo esc_attr( $tax_name ); ?>" id="new_multisite_<?php echo esc_attr( $tax_name ); ?>" class="form-required form-input-tip" value="<?php echo esc_attr( $taxonomy->labels->new_item_name ); ?>" aria-required="true"/>
						<label class="screen-reader-text" for="new_multisite_<?php echo esc_attr( $tax_name ); ?>_parent">
							<?php echo esc_html( $taxonomy->labels->parent_item_colon ); ?>
						</label>
						<?php
						$parent_dropdown_args = array(
							'taxonomy'         => $tax_name,
							'hide_empty'       => 0,
							'name'             => 'new_multisite_' . $tax_name . '_parent',
							'orderby'          => 'name',
							'hierarchical'     => 1,
							'show_option_none' => '&mdash; ' . $taxonomy->labels->parent_item . ' &mdash;',
						);

						/**
						 * Filters the arguments for the taxonomy parent dropdown on the Post Edit page.
						 *
						 * @since 4.4.0
						 *
						 * @param array $parent_dropdown_args {
						 *     Optional. Array of arguments to generate parent dropdown.
						 *
						 *     @type string   $taxonomy         Name of the taxonomy to retrieve.
						 *     @type bool     $hide_if_empty    True to skip generating markup if no
						 *                                      tags are found. Default 0.
						 *     @type string   $name             Value for the 'name' attribute
						 *                                      of the select element.
						 *                                      Default "new_multisite_{$tax_name}_parent".
						 *     @type string   $orderby          Which column to use for ordering
						 *                                      terms. Default 'name'.
						 *     @type bool|int $hierarchical     Whether to traverse the taxonomy
						 *                                      hierarchy. Default 1.
						 *     @type string   $show_option_none Text to display for the "none" option.
						 *                                      Default "&mdash; {$parent} &mdash;",
						 *                                      where `$parent` is 'parent_item'
						 *                                      taxonomy label.
						 * }
						 */
						$parent_dropdown_args = apply_filters( 'edit_multisite_hierarchical_term_parent_dropdown_args', $parent_dropdown_args );

						dropdown_multisite_taxonomy( $parent_dropdown_args );
						?>
						<input type="button" id="<?php echo esc_attr( $tax_name ); ?>-add-submit" data-wp-lists="add:<?php echo esc_attr( $tax_name ); ?>checklist:<?php echo esc_attr( $tax_name ); ?>-add" class="button multisite-hierarchical-term-add-submit" value="<?php echo esc_attr( $taxonomy->labels->add_new_item ); ?>" />
						<?php wp_nonce_field( 'add-multisite-' . $tax_name, '_ajax_nonce-add-' . $tax_name, false ); ?>
						<span id="<?php echo esc_attr( $tax_name ); ?>-ajax-response"></span>
					</p>
				</div>
			<?php endif; ?>
		</div>
		<?php
	}

	/**
	 * Search through the multisite tags.
	 *
	 * @return void
	 */
	public function wp_ajax_ajax_multisite_terms_search() {
		check_ajax_referer( 'nonce-multisite-terms-search', 'security' );

		if ( ! isset( $_GET['tax'] ) ) { // WPCS: input var ok.
			wp_die( 0 );
		}

		$taxonomy = sanitize_key( wp_unslash( $_GET['tax'] ) ); // WPCS: input var ok.
		$tax      = get_multisite_taxonomy( $taxonomy );
		if ( ! $tax ) {
			wp_die( 0 );
		}

		if ( ! current_user_can( $tax->cap->assign_multisite_terms ) ) {
			wp_die( -1 );
		}

		if ( isset( $_GET['q'] ) ) { // WPCS: input var ok.
			$s = sanitize_text_field( wp_unslash( $_GET['q'] ) ); // WPCS: input var ok.
		} else {
			$s = '';
		}

		$comma = _x( ',', 'tag delimiter', 'multitaxo' );
		if ( ',' !== $comma ) {
			$s = str_replace( $comma, ',', $s );
		}
		if ( false !== strpos( $s, ',' ) ) {
			$s = explode( ',', $s );
			$s = $s[ count( $s ) - 1 ];
		}
		$s = trim( $s );

		/**
		 * Filters the minimum number of characters required to fire a tag search via Ajax.
		 *
		 * @since 4.0.0
		 *
		 * @param int                $characters The minimum number of characters required. Default 2.
		 * @param Multisite_Taxonomy $tax        The taxonomy object.
		 * @param string             $s          The search term.
		 */
		$term_search_min_chars = (int) apply_filters( 'term_search_min_chars', 2, $tax, $s );

		/*
		* Require $term_search_min_chars chars for matching (default: 2)
		* ensure it's a non-negative, non-zero integer.
		*/
		if ( ( 0 === $term_search_min_chars ) || ( strlen( $s ) < $term_search_min_chars ) ) {
			wp_die();
		}

		$results = get_multisite_terms(
			array(
				'taxonomy'   => $taxonomy,
				'name__like' => $s,
				'fields'     => 'names',
				'hide_empty' => false,
			)
		);

		echo implode( "\n", $results ); // phpcs:ignore WordPress.Security.EscapeOutput
		wp_die();
	}

	/**
	 * Ajax Get the tag cloud.
	 *
	 * @return void
	 */
	public function wp_ajax_get_multisite_term_cloud() {
		check_ajax_referer( 'nonce-multisite-term-cloud', 'security' );

		if ( ! isset( $_POST['tax'] ) ) { // WPCS: input var ok.
			wp_die( 0 );
		}

		$taxonomy = sanitize_key( wp_unslash( $_POST['tax'] ) ); // WPCS: input var ok.
		$tax      = get_multisite_taxonomy( $taxonomy );
		if ( ! $tax ) {
			wp_die( 0 );
		}

		if ( ! current_user_can( $tax->cap->assign_multisite_terms ) ) {
			wp_die( -1 );
		}

		$term_args = array(
			'taxonomy'   => $taxonomy,
			'number'     => 45,
			'orderby'    => 'count',
			'order'      => 'DESC',
			'hide_empty' => false,
		);

		// Make the multisite term cloud defaults editable.
		$term_args = apply_filters( 'multisite_taxonomy_term_cloud_args', $term_args );

		// Get the terms for the clould.
		$terms = get_multisite_terms( $term_args );

		if ( empty( $terms ) ) {
			wp_die( esc_html( $tax->labels->not_found ) );
		}

		if ( is_wp_error( $terms ) ) {
			wp_die( esc_html( $terms->get_error_message() ) );
		}

		foreach ( $terms as $key => $term ) {
			$terms[ $key ]->link = '#';
			$terms[ $key ]->id   = $term->multisite_term_id;
		}

		// We need raw tag names here, so don't filter the output.
		$return = generate_multisite_term_cloud(
			$terms,
			array(
				'filter' => 0,
				'format' => 'list',
			)
		);

		if ( empty( $return ) ) {
			wp_die( 0 );
		}

		echo $return; // phpcs:ignore WordPress.Security.EscapeOutput

		wp_die();
	}

	/**
	 * Save the custom Twaxonomy box.
	 *
	 * @access public
	 * @param integer $post_id The post id being edited.
	 * @return mixed Void if successful or post_id if not.
	 */
	public function save_multisite_taxonomy( $post_id ) {
		/*
		* We need to verify this came from the our screen and with proper authorization,
		* because save_post can be triggered at other times.
		*/

		// Check if our nonce is set.
		if ( ! isset( $_POST['multisite_taxonomy_meta_box_nonce'] ) ) { // WPCS: input var okay.
			return $post_id;
		}

		// Verify that the nonce is valid.
		if ( ! wp_verify_nonce( sanitize_key( wp_unslash( $_POST['multisite_taxonomy_meta_box_nonce'] ) ), 'multisite_taxonomy_meta_box' ) ) { // WPCS: input var okay.
			return $post_id;
		}

		// If this is an autosave, our form has not been submitted, so we don't want to do anything.
		if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) {
			return $post_id;
		}

		if ( ! current_user_can( 'assign_multisite_terms', $post_id ) ) {
			return $post_id;
		}

		$post = get_post( $post_id );

		if ( count( (array) get_object_multisite_taxonomies( $post->post_type ) ) <= 0 ) {
			return $post_id;
		}

		if ( isset( $_POST['multi_tax_input'] ) ) { // WPCS: Input var OK.
			$multi_tax_input = sanitize_multisite_taxonomy_save_data( wp_unslash( $_POST['multi_tax_input'] ) ); // phpcs:ignore WordPress.Security.ValidatedSanitizedInput
		}

		/* OK, its safe for us to save the data now. */
		$blog_id = get_current_blog_id();

		// New-style support for all custom taxonomies.
		if ( ! empty( $multi_tax_input ) && is_array( $multi_tax_input ) ) {
			foreach ( $multi_tax_input as $taxonomy => $terms ) {
				$taxonomy_obj = get_multisite_taxonomy( $taxonomy );

				if ( ! $taxonomy_obj ) {
					/* translators: %s: taxonomy name */
					_doing_it_wrong( __FUNCTION__, esc_html( sprintf( __( 'Invalid taxonomy: %s.', 'multitaxo' ), $taxonomy ) ), '4.4.0' );
					continue;
				}

				// array = hierarchical, string = non-hierarchical.
				if ( is_array( $terms ) ) {
					$terms = array_filter( $terms );
				}

				if ( current_user_can( $taxonomy_obj->cap->assign_multisite_terms ) ) {
					set_post_multisite_terms( $post_id, $terms, $taxonomy, $blog_id );
				}
			}
		}
	}
}
