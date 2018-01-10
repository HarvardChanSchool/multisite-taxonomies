<?php
/**
 * Multisite Taxonomies Settings init class
 *
 * @package multitaxo
 */

/**
 * Settings screens init class.
 */
class Multisite_Tags_Meta_Box {
	/**
	 * __construct function.
	 *
	 * @access public
	 * @return void
	 */
	public function __construct() {
		// We enqueue both the frontend and admin styles and scripts.
		add_action( 'add_meta_boxes', array( $this, 'add_multsite_tags_meta_box' ), 10, 2 );

		// Add the admin scripts to the posts pages.
		add_action( 'admin_enqueue_scripts', array( $this, 'load_wp_admin_scripts' ) );

		// register the ajax response for creating new tags.
		add_action( 'wp_ajax_ajax-multisite-tag-search', array( $this, 'wp_ajax_ajax_multisite_tag_search' ) );
		add_action( 'wp_ajax_ajax-get-multisite-tagcloud', array( $this, 'wp_ajax_get_multisite_tagcloud' ) );
	}

	/**
	 * Display the metabox container if we should use it.
	 *
	 * @param string  $post_type The WP Post type.
	 * @param WP_Post $post Post object.
	 *
	 * @return void
	 */
	public function add_multsite_tags_meta_box( $post_type, $post ) {
		if ( count( (array) get_object_multisite_taxonomies( $post_type ) ) > 0 ) {
			add_meta_box( 'multisite_tax_meta_box', esc_html__( 'Multisite Tags', 'multitaxo' ), array( $this, 'multsite_tags_meta_box_callback' ), null, 'advanced', 'default', array( $post, $post_type ) );
		}
	}

	/**
	 * Display the metabox container if we should use it.
	 *
	 * @param string $hook page hook.
	 *
	 * @return void
	 */
	public function load_wp_admin_scripts( $hook ) {

		wp_enqueue_script( 'multisite-tags-suggest', MULTITAXO_PLUGIN_URL . '/assets/js/multisite-tags-suggest.js', array( 'jquery', 'jquery-ui-core', 'jquery-ui-autocomplete', 'wp-a11y' ), false, 1 );
		wp_localize_script(
			'multisite-tags-suggest', 'tagsSuggestL10n', array(
				'tagDelimiter' => _x( ',', 'tag delimiter', 'multitaxo' ),
				'removeTerm'   => __( 'Remove term:', 'multitaxo' ),
				'termSelected' => __( 'Term selected.', 'multitaxo' ),
				'termAdded'    => __( 'Term added.', 'multitaxo' ),
				'termRemoved'  => __( 'Term removed.', 'multitaxo' ),
			)
		);

		wp_enqueue_script( 'multisite-tags-box', MULTITAXO_PLUGIN_URL . '/assets/js/multisite-tags-box.js', array( 'multisite-tags-suggest', 'jquery-ui-tabs' ), false, 1 );
	}

	/**
	 * Display the meta box content.
	 *
	 * @param string  $post The WP Post type.
	 * @param WP_Post $metabox Post object.
	 *
	 * @return void
	 */
	public function multsite_tags_meta_box_callback( $post, $metabox ) {
		$taxonomies = get_multisite_taxonomies( array(), 'objects' );

		$tabs         = array();
		$tab_contents = array();

		?>
		<style>
.ui-tabs-vertical {
	clear: both;
	overflow: hidden;
}

.ui-tabs-vertical .ui-tabs-nav {
	padding: .2em 1% .2em 1%;
	float: left;
	width: 15%;
	margin-left: 2%;
	margin-right: -2%;
}

.ui-tabs-vertical .ui-tabs-nav li {
	clear: left;
	margin: 5px 0 5px 0;
	border: solid 1px #ddd;
	background-color: #fdfdfd;
	padding: 7px 0 7px 7px;
	z-index: 9;
}

.ui-tabs-vertical .ui-tabs-nav li a {
	display: block;
}

.ui-tabs-vertical .ui-tabs-nav li.ui-tabs-active {
	background-color: #fff;
	border-right-color: #fff;
}

.ui-tabs-vertical .ui-tabs-panel {
	padding: 1%;
	float: right;
	width: 80%;
	border-left: solid 1px #ddd;

}

/*------------------------------------------------------------------------------
  13.0 - Tags
------------------------------------------------------------------------------*/

#poststuff .multitagsdiv .howto {
	margin: 0 0 6px 0;
}

.ajaxtag .newmultitag {
	position: relative;
}

.multitagsdiv .newmultitag {
	width: 180px;
}

.multitagsdiv .the-multitags {
	display: block;
	height: 60px;
	margin: 0 auto;
	overflow: auto;
	width: 260px;
}

#post-body-content .multitagsdiv .the-multitags {
	margin: 0 5px;
}

p.popular-multitags {
	border: none;
	line-height: 2em;
	padding: 8px 12px 12px;
	text-align: justify;
}

p.popular-multitags a {
	padding: 0 3px;
}

.multitagcloud {
	width: 97%;
	margin: 0 0 40px;
	text-align: justify;
}

.multitagcloud h2 {
	margin: 2px 0 12px;
}

.the-multitagcloud ul {
	margin: 0;
}

.the-multitagcloud ul li {
	display: inline-block;
}
		</style>
		<div id="multisite-tax-picker">
			<ul>
		<?php

		foreach ( $taxonomies as $tax ) {
			// Set up the tab itself.
			?>
			<li><a href="#tabs-<?php echo esc_attr( $tax->name ); ?>"><?php echo esc_html( $tax->labels->name ); ?></a></li>
			<?php
		}

		?>
		</ul>
		<?php

		// and lets do this again for the boxes.
		reset( $taxonomies );

		// loop and loop.
		foreach ( $taxonomies as $tax ) {
			?>
			<div id="tabs-<?php echo esc_attr( $tax->name ); ?>">
				<h2><?php echo esc_html( $tax->labels->name ); ?></h2>
			<?php

			$args = array(
				'title'    => $tax->labels->name,
				'taxonomy' => $tax->name,
				'args'     => array(),
			);

			// Are we heirarchical or not?
			if ( true === $tax->hierarchical ) {
				$this->multisite_categories_meta_box( $post, $args );
			} else {
				$this->multisite_tags_meta_box( $post, $args );
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
	public function multisite_tags_meta_box( $post, $args ) {
		$defaults              = array( 'taxonomy' => 'tag' );
		$r                     = wp_parse_args( $args, $defaults );
		$tax_name              = esc_attr( $r['taxonomy'] );
		$taxonomy              = get_multisite_taxonomy( $r['taxonomy'] );
		$user_can_assign_terms = current_user_can( $taxonomy->cap->assign_multisite_terms );
		$comma                 = _x( ',', 'tag delimiter', 'multitaxo' );
		$terms_to_edit         = get_multisite_terms_to_edit( $post->ID, $tax_name );
		if ( ! is_string( $terms_to_edit ) ) {
			$terms_to_edit = '';
		}
	?>
	<div class="multitagsdiv" id="<?php echo esc_attr( $tax_name ); ?>">
		<div class="jaxtag">
		<div class="nojs-tags hide-if-js">
			<label for="multi-tax-input-<?php echo esc_attr( $tax_name ); ?>"><?php echo esc_html( $taxonomy->labels->add_or_remove_items ); ?></label>
			<p><textarea name="<?php echo esc_attr( "multi_tax_input[$tax_name]" ); ?>" rows="3" cols="20" class="the-multi-tags" id="multi-tax-input-<?php echo esc_attr( $tax_name ); ?>" <?php disabled( ! $user_can_assign_terms ); ?> aria-describedby="new-tag-<?php echo esc_attr( $tax_name ); ?>-desc"><?php echo esc_textarea( str_replace( ',', $comma . ' ', $terms_to_edit ) ); ?></textarea></p>
		</div>
		<?php if ( $user_can_assign_terms ) : ?>
		<div class="ajaxmultitag hide-if-no-js">
			<label class="screen-reader-text" for="new-multi-tag-<?php echo esc_attr( $tax_name ); ?>"><?php echo esc_html( $taxonomy->labels->add_new_item ); ?></label>
			<p><input data-multi-taxonomy="<?php echo esc_attr( $tax_name ); ?>" type="text" id="new-multi-tag-<?php echo esc_attr( $tax_name ); ?>" name="new_multi_tag[<?php echo esc_attr( $tax_name ); ?>]" class="newmultitag form-input-tip" size="16" autocomplete="off" aria-describedby="new-multi-tag-<?php echo esc_attr( $tax_name ); ?>-desc" value="" />
			<input type="button" class="button multitagadd" value="<?php esc_attr_e( 'Add', 'multitaxo' ); ?>" /></p>
		</div>
		<p class="howto" id="new-multi-tag-<?php echo esc_attr( $tax_name ); ?>-desc"><?php echo esc_html( $taxonomy->labels->separate_items_with_commas ); ?></p>
		<?php elseif ( empty( $terms_to_edit ) ) : ?>
			<p><?php echo esc_html( $taxonomy->labels->no_terms ); ?></p>
		<?php endif; ?>
		</div>
		<ul class="multitagchecklist" role="list"></ul>
	</div>
	<?php if ( $user_can_assign_terms ) : ?>
	<p class="hide-if-no-js"><button type="button" class="button-link multitagcloud-link" id="link-<?php echo esc_attr( $tax_name ); ?>" aria-expanded="false"><?php echo esc_html( $taxonomy->labels->choose_from_most_used ); ?></button></p>
	<?php endif; ?>
	<?php
	}

	/**
	 * Display post categories form fields.
	 *
	 * @since 2.6.0
	 *
	 * @todo Create taxonomy-agnostic wrapper for this.
	 *
	 * @param WP_Post $post Post object.
	 * @param array   $args {
	 *     Categories meta box arguments.
	 *
	 *     @type string   $id       Meta box 'id' attribute.
	 *     @type string   $title    Meta box title.
	 *     @type callable $callback Meta box display callback.
	 *     @type array    $args {
	 *         Extra meta box arguments.
	 *
	 *         @type string $taxonomy Taxonomy. Default 'category'.
	 *     }
	 * }
	 */
	public function multisite_categories_meta_box( $post, $args ) {
		$defaults = array( 'taxonomy' => 'category' );
		$r        = wp_parse_args( $args, $defaults );
		$tax_name = esc_attr( $r['taxonomy'] );
		$taxonomy = get_multisite_taxonomy( $r['taxonomy'] );
		?>
		<div id="taxonomy-<?php echo esc_attr( $tax_name ); ?>" class="categorydiv">
			<ul id="<?php echo esc_attr( $tax_name ); ?>-tabs" class="category-tabs">
				<li class="tabs"><a href="#<?php echo esc_attr( $tax_name ); ?>-all"><?php echo esc_html( $taxonomy->labels->all_items ); ?></a></li>
				<li class="hide-if-no-js"><a href="#<?php echo esc_attr( $tax_name ); ?>-pop"><?php echo esc_html( $taxonomy->labels->most_used ); ?></a></li>
			</ul>

			<div id="<?php echo esc_attr( $tax_name ); ?>-pop" class="tabs-panel" style="display: none;">
				<ul id="<?php echo esc_attr( $tax_name ); ?>checklist-pop" class="categorychecklist form-no-clear" >
					<?php $popular_ids = popular_multisite_terms_checklist( $tax_name ); ?>
				</ul>
			</div>

			<div id="<?php echo esc_attr( $tax_name ); ?>-all" class="tabs-panel">
				<?php
				$name = ( 'category' === $tax_name ) ? 'post_category' : 'tax_input[' . $tax_name . ']';
				echo '<input type="hidden" name="' . esc_attr( $name ) . '[]" value="0" />'; // Allows for an empty term set to be sent. 0 is an invalid Term ID and will be ignored by empty() checks.
				?>
				<ul id="<?php echo esc_attr( $tax_name ); ?>checklist" data-wp-lists="list:<?php echo esc_attr( $tax_name ); ?>" class="categorychecklist form-no-clear">
					<?php
					multisite_terms_checklist(
						$post->ID, array(
							'taxonomy'     => $tax_name,
							'popular_cats' => $popular_ids,
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
					<p id="<?php echo esc_attr( $tax_name ); ?>-add" class="multisite-category-add wp-hidden-child">
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
						 *                                      categories are found. Default 0.
						 *     @type string   $name             Value for the 'name' attribute
						 *                                      of the select element.
						 *                                      Default "new{$tax_name}_parent".
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
						$parent_dropdown_args = apply_filters( 'post_edit_category_parent_dropdown_args', $parent_dropdown_args );

						dropdown_multisite_categories( $parent_dropdown_args );
						?>
						<input type="button" id="<?php echo esc_attr( $tax_name ); ?>-add-submit" data-wp-lists="add:<?php echo esc_attr( $tax_name ); ?>checklist:<?php echo esc_attr( $tax_name ); ?>-add" class="button multisite-category-add-submit" value="<?php echo esc_attr( $taxonomy->labels->add_new_item ); ?>" />
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
	public function wp_ajax_ajax_multisite_tag_search() {
		check_ajax_referer( 'add-multisite-tag', 'nonce-add-multisite-tag' );

		if ( ! isset( $_GET['tax'] ) ) {
			wp_die( 0 );
		}

		$taxonomy = sanitize_key( wp_unslash( $_GET['tax'] ) );
		$tax      = get_multisite_taxonomy( $taxonomy );
		if ( ! $tax ) {
			wp_die( 0 );
		}

		if ( ! current_user_can( $tax->cap->assign_multisite_terms ) ) {
			wp_die( -1 );
		}

		$s = wp_unslash( $_GET['q'] );

		$comma = _x( ',', 'tag delimiter' );
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
			$taxonomy, array(
				'name__like' => $s,
				'fields'     => 'names',
				'hide_empty' => false,
			)
		);

		echo join( esc_html( $results ), "\n" );
		wp_die();
	}

	/**
	 * Ajax Get the tag cloud.
	 *
	 * @return void
	 */
	public function wp_ajax_get_multisite_tagcloud() {
		check_ajax_referer( 'add-multisite-tag', 'nonce-add-multisite-tag' );

		if ( ! isset( $_POST['tax'] ) ) {
			wp_die( 0 );
		}

		$taxonomy = sanitize_key( $_POST['tax'] );
		$tax      = get_multisite_taxonomy( $taxonomy );
		if ( ! $tax ) {
			wp_die( 0 );
		}

		if ( ! current_user_can( $tax->cap->assign_terms ) ) {
			wp_die( -1 );
		}

		$tags = get_multisite_terms(
			$taxonomy, array(
				'number'  => 45,
				'orderby' => 'count',
				'order'   => 'DESC',
			)
		);

		if ( empty( $tags ) ) {
			wp_die( esc_html( $tax->labels->not_found ) );
		}

		if ( is_wp_error( $tags ) ) {
			wp_die( esc_html( $tags->get_error_message() ) );
		}

		foreach ( $tags as $key => $tag ) {
			$tags[ $key ]->link = '#';
			$tags[ $key ]->id   = $tag->term_id;
		}

		// We need raw tag names here, so don't filter the output.
		$return = generate_multisite_tag_cloud(
			$tags, array(
				'filter' => 0,
				'format' => 'list',
			)
		);

		if ( empty( $return ) ) {
			wp_die( 0 );
		}

		echo $return;

		wp_die();
	}
}
