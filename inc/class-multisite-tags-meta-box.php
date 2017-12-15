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
	 * @param string  $post_type The WP Post type.
	 * @param WP_Post $post Post object.
	 *
	 * @return void
	 */
	public function load_wp_admin_scripts( $hook ) {
		if ( 'post.php' !== $hook ) {
			return;
		}

		wp_enqueue_script( 'multisite-tags-box', MULTITAXO_PLUGIN_URL . '/assets/js/multisite-tags-box.js', array( 'jquery', 'multisite-tags-suggest', 'jquery-ui-core', 'jquery-ui-tabs' ), false, 1 );

		wp_enqueue_script( 'multisite-tags-suggest', MULTITAXO_PLUGIN_URL . '/assets/js/multisite-tags-suggest.js', array( 'jquery-ui-autocomplete', 'wp-a11y' ), false, 1 );
		wp_localize_script(
			'multisite-tags-suggest', 'tagsSuggestL10n', array(
				'tagDelimiter' => _x( ',', 'tag delimiter' ),
				'removeTerm'   => __( 'Remove term:' ),
				'termSelected' => __( 'Term selected.' ),
				'termAdded'    => __( 'Term added.' ),
				'termRemoved'  => __( 'Term removed.' ),
			)
		);
	}

	/**
	 * display the meta box content.
	 *
	 * @param string  $post_type The WP Post type.
	 * @param WP_Post $post Post object.
	 *
	 * @return void
	 */
	public function multsite_tags_meta_box_callback( $post, $metabox ) {
		$taxonomies = get_object_multisite_taxonomies( $post, 'object' );

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
	 * @param array   $box {
	 *     Tags meta box arguments.
	 *
	 *     @type string   $taxonomy Taxonomy corresponding.
	 *     @type string   $title    Meta box title.
	 *     @type array    $args {
	 *         Extra meta box arguments.
	 *     }
	 * }
	 */
	function multisite_tags_meta_box( $post, $args ) {
		$defaults              = array( 'taxonomy' => 'tag' );
		$r                     = wp_parse_args( $args, $defaults );
		$tax_name              = esc_attr( $r['taxonomy'] );
		$taxonomy              = get_multisite_taxonomy( $r['taxonomy'] );
		$user_can_assign_terms = current_user_can( $taxonomy->cap->assign_multisite_terms );
		$comma                 = _x( ',', 'tag delimiter' );
		$terms_to_edit         = get_multisite_terms_to_edit( $post->ID, $tax_name );
		if ( ! is_string( $terms_to_edit ) ) {
			$terms_to_edit = '';
		}
	?>
	<div class="multitagsdiv" id="<?php echo $tax_name; ?>">
		<div class="jaxtag">
		<div class="nojs-tags hide-if-js">
			<label for="multi-tax-input-<?php echo $tax_name; ?>"><?php echo $taxonomy->labels->add_or_remove_items; ?></label>
			<p><textarea name="<?php echo "multi_tax_input[$tax_name]"; ?>" rows="3" cols="20" class="the-tags" id="multi-tax-input-<?php echo $tax_name; ?>" <?php disabled( ! $user_can_assign_terms ); ?> aria-describedby="new-tag-<?php echo $tax_name; ?>-desc"><?php echo str_replace( ',', $comma . ' ', $terms_to_edit ); // textarea_escaped by esc_attr() ?></textarea></p>
		</div>
		<?php if ( $user_can_assign_terms ) : ?>
		<div class="ajaxtag hide-if-no-js">
			<label class="screen-reader-text" for="new-multi-tag-<?php echo $tax_name; ?>"><?php echo $taxonomy->labels->add_new_item; ?></label>
			<p><input data-multi-taxonomy="<?php echo $tax_name; ?>" type="text" id="new-multi-tag-<?php echo $tax_name; ?>" name="new_multi_tag[<?php echo $tax_name; ?>]" class="newtag form-input-tip" size="16" autocomplete="off" aria-describedby="new-multi-tag-<?php echo $tax_name; ?>-desc" value="" />
			<input type="button" class="button tagadd" value="<?php esc_attr_e( 'Add' ); ?>" /></p>
		</div>
		<p class="howto" id="new-multi-tag-<?php echo $tax_name; ?>-desc"><?php echo $taxonomy->labels->separate_items_with_commas; ?></p>
		<?php elseif ( empty( $terms_to_edit ) ) : ?>
			<p><?php echo $taxonomy->labels->no_terms; ?></p>
		<?php endif; ?>
		</div>
		<ul class="tagchecklist" role="list"></ul>
	</div>
	<?php if ( $user_can_assign_terms ) : ?>
	<p class="hide-if-no-js"><button type="button" class="button-link tagcloud-link" id="link-<?php echo $tax_name; ?>" aria-expanded="false"><?php echo $taxonomy->labels->choose_from_most_used; ?></button></p>
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
	 * @param array   $box {
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
	function multisite_categories_meta_box( $post, $args ) {
		$defaults = array( 'taxonomy' => 'category' );
		$r        = wp_parse_args( $args, $defaults );
		$tax_name = esc_attr( $r['taxonomy'] );
		$taxonomy = get_multisite_taxonomy( $r['taxonomy'] );
		?>
		<div id="taxonomy-<?php echo $tax_name; ?>" class="categorydiv">
			<ul id="<?php echo $tax_name; ?>-tabs" class="category-tabs">
				<li class="tabs"><a href="#<?php echo $tax_name; ?>-all"><?php echo $taxonomy->labels->all_items; ?></a></li>
				<li class="hide-if-no-js"><a href="#<?php echo $tax_name; ?>-pop"><?php echo esc_html( $taxonomy->labels->most_used ); ?></a></li>
			</ul>

			<div id="<?php echo $tax_name; ?>-pop" class="tabs-panel" style="display: none;">
				<ul id="<?php echo $tax_name; ?>checklist-pop" class="categorychecklist form-no-clear" >
					<?php $popular_ids = popular_multisite_terms_checklist( $tax_name ); ?>
				</ul>
			</div>

			<div id="<?php echo $tax_name; ?>-all" class="tabs-panel">
				<?php
				$name = ( $tax_name == 'category' ) ? 'post_category' : 'tax_input[' . $tax_name . ']';
				echo "<input type='hidden' name='{$name}[]' value='0' />"; // Allows for an empty term set to be sent. 0 is an invalid Term ID and will be ignored by empty() checks.
				?>
				<ul id="<?php echo $tax_name; ?>checklist" data-wp-lists="list:<?php echo $tax_name; ?>" class="categorychecklist form-no-clear">
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
				<div id="<?php echo $tax_name; ?>-adder" class="wp-hidden-children">
					<a id="<?php echo $tax_name; ?>-add-toggle" href="#<?php echo $tax_name; ?>-add" class="hide-if-no-js taxonomy-add-new">
						<?php
							/* translators: %s: add new taxonomy label */
							printf( __( '+ %s' ), $taxonomy->labels->add_new_item );
						?>
					</a>
					<p id="<?php echo $tax_name; ?>-add" class="category-add wp-hidden-child">
						<label class="screen-reader-text" for="new<?php echo $tax_name; ?>"><?php echo $taxonomy->labels->add_new_item; ?></label>
						<input type="text" name="new<?php echo $tax_name; ?>" id="new<?php echo $tax_name; ?>" class="form-required form-input-tip" value="<?php echo esc_attr( $taxonomy->labels->new_item_name ); ?>" aria-required="true"/>
						<label class="screen-reader-text" for="new<?php echo $tax_name; ?>_parent">
							<?php echo $taxonomy->labels->parent_item_colon; ?>
						</label>
						<?php
						$parent_dropdown_args = array(
							'taxonomy'         => $tax_name,
							'hide_empty'       => 0,
							'name'             => 'new' . $tax_name . '_parent',
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

						wp_dropdown_categories( $parent_dropdown_args );
						?>
						<input type="button" id="<?php echo $tax_name; ?>-add-submit" data-wp-lists="add:<?php echo $tax_name; ?>checklist:<?php echo $tax_name; ?>-add" class="button category-add-submit" value="<?php echo esc_attr( $taxonomy->labels->add_new_item ); ?>" />
						<?php wp_nonce_field( 'add-' . $tax_name, '_ajax_nonce-add-' . $tax_name, false ); ?>
						<span id="<?php echo $tax_name; ?>-ajax-response"></span>
					</p>
				</div>
			<?php endif; ?>
		</div>
		<?php
	}
}
