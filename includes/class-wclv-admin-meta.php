<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class WCLV_Admin_Meta {

	public static function init() {
		add_action( 'add_meta_boxes', array( __CLASS__, 'register_meta_boxes' ) );
		add_action( 'save_post_' . WCLV_Post_Type::POST_TYPE, array( __CLASS__, 'save' ), 10, 2 );
		add_action( 'admin_enqueue_scripts', array( __CLASS__, 'enqueue_assets' ) );
	}

	public static function enqueue_assets( $hook ) {
		global $post_type;

		if ( WCLV_Post_Type::POST_TYPE !== $post_type ) {
			return;
		}

		if ( ! in_array( $hook, array( 'post.php', 'post-new.php' ), true ) ) {
			return;
		}

		wp_enqueue_style(
			'wclv-admin',
			WCLV_PLUGIN_URL . 'assets/css/admin.css',
			array(),
			WCLV_VERSION
		);

		wp_enqueue_script( 'jquery-ui-sortable' );

		wp_enqueue_script(
			'wclv-admin',
			WCLV_PLUGIN_URL . 'assets/js/admin.js',
			array( 'jquery', 'select2', 'jquery-ui-sortable' ),
			WCLV_VERSION,
			true
		);

		wp_localize_script( 'wclv-admin', 'wclv_admin', array(
			'ajax_url'            => admin_url( 'admin-ajax.php' ),
			'search_nonce'        => wp_create_nonce( 'wclv_search_products' ),
			'taxonomy_nonce'      => wp_create_nonce( 'wclv_get_taxonomy_terms' ),
			'search_placeholder'  => __( 'Search for products…', 'wc-linked-variations' ),
			'terms_placeholder'   => __( 'Select terms…', 'wc-linked-variations' ),
		) );
	}

	public static function register_meta_boxes() {
		add_meta_box(
			'wclv_products',
			__( 'Product Selection', 'wc-linked-variations' ),
			array( __CLASS__, 'render_products_box' ),
			WCLV_Post_Type::POST_TYPE,
			'normal',
			'high'
		);

		add_meta_box(
			'wclv_attributes',
			__( 'Linking Attributes', 'wc-linked-variations' ),
			array( __CLASS__, 'render_attributes_box' ),
			WCLV_Post_Type::POST_TYPE,
			'normal',
			'high'
		);

		add_meta_box(
			'wclv_display',
			__( 'Display Settings', 'wc-linked-variations' ),
			array( __CLASS__, 'render_display_box' ),
			WCLV_Post_Type::POST_TYPE,
			'side',
			'default'
		);
	}

	/* ── Product Selection Meta Box ────────────────────────────────── */

	public static function render_products_box( $post ) {
		$group = WCLV_Database::get_group_by_post_id( $post->ID );

		$source        = $group ? $group->product_source : 'manual';
		$product_ids   = $group ? maybe_unserialize( $group->product_ids ) : array();
		$taxonomy      = $group ? $group->taxonomy : '';
		$taxonomy_terms = $group ? maybe_unserialize( $group->taxonomy_terms ) : array();

		if ( ! is_array( $product_ids ) ) {
			$product_ids = array();
		}
		if ( ! is_array( $taxonomy_terms ) ) {
			$taxonomy_terms = array();
		}

		wp_nonce_field( 'wclv_save_meta', 'wclv_meta_nonce' );
		?>
		<div class="wclv-source-toggle">
			<label>
				<input type="radio" name="wclv_product_source" value="manual" <?php checked( $source, 'manual' ); ?>>
				<?php esc_html_e( 'Select Products Manually', 'wc-linked-variations' ); ?>
			</label>
			<label>
				<input type="radio" name="wclv_product_source" value="taxonomy" <?php checked( $source, 'taxonomy' ); ?>>
				<?php esc_html_e( 'Select by Taxonomy', 'wc-linked-variations' ); ?>
			</label>
		</div>

		<!-- Manual product selection -->
		<div class="wclv-source-panel" data-source="manual" <?php echo 'taxonomy' === $source ? 'style="display:none"' : ''; ?>>
			<p class="description"><?php esc_html_e( 'Search and select the products to link together.', 'wc-linked-variations' ); ?></p>
			<select id="wclv_product_ids" name="wclv_product_ids[]" class="wclv-product-search" multiple="multiple" style="width:100%">
				<?php
				foreach ( $product_ids as $pid ) {
					$product = wc_get_product( $pid );
					if ( $product ) {
						printf(
							'<option value="%d" selected>%s (#%d)</option>',
							esc_attr( $pid ),
							esc_html( $product->get_name() ),
							esc_html( $pid )
						);
					}
				}
				?>
			</select>
		</div>

		<!-- Taxonomy-based selection -->
		<div class="wclv-source-panel" data-source="taxonomy" <?php echo 'manual' === $source ? 'style="display:none"' : ''; ?>>
			<p class="description"><?php esc_html_e( 'Products will be resolved dynamically from the selected taxonomy terms.', 'wc-linked-variations' ); ?></p>

			<p>
				<label for="wclv_taxonomy"><strong><?php esc_html_e( 'Taxonomy', 'wc-linked-variations' ); ?></strong></label><br>
				<select id="wclv_taxonomy" name="wclv_taxonomy" style="width:100%">
					<option value=""><?php esc_html_e( '— Select taxonomy —', 'wc-linked-variations' ); ?></option>
					<?php
					$taxonomies = get_object_taxonomies( 'product', 'objects' );
					foreach ( $taxonomies as $tax ) {
						if ( 'product_type' === $tax->name || 'product_visibility' === $tax->name ) {
							continue;
						}
						printf(
							'<option value="%s" %s>%s</option>',
							esc_attr( $tax->name ),
							selected( $taxonomy, $tax->name, false ),
							esc_html( $tax->labels->name )
						);
					}
					?>
				</select>
			</p>

			<p>
				<label for="wclv_taxonomy_terms"><strong><?php esc_html_e( 'Terms', 'wc-linked-variations' ); ?></strong></label><br>
				<select id="wclv_taxonomy_terms" name="wclv_taxonomy_terms[]" class="wclv-taxonomy-terms" multiple="multiple" style="width:100%">
					<?php
					if ( $taxonomy && ! empty( $taxonomy_terms ) ) {
						foreach ( $taxonomy_terms as $term_id ) {
							$term = get_term( $term_id, $taxonomy );
							if ( $term && ! is_wp_error( $term ) ) {
								printf(
									'<option value="%d" selected>%s</option>',
									esc_attr( $term_id ),
									esc_html( $term->name )
								);
							}
						}
					}
					?>
				</select>
			</p>
		</div>
		<?php
	}

	/* ── Attribute Selection Meta Box ──────────────────────────────── */

	public static function render_attributes_box( $post ) {
		$group      = WCLV_Database::get_group_by_post_id( $post->ID );
		$saved_attrs = $group ? maybe_unserialize( $group->attributes ) : array();
		if ( ! is_array( $saved_attrs ) ) {
			$saved_attrs = array();
		}

		$attribute_taxonomies = wc_get_attribute_taxonomies();
		if ( empty( $attribute_taxonomies ) ) {
			echo '<p>' . esc_html__( 'No product attributes found. Create attributes under Products > Attributes first.', 'wc-linked-variations' ) . '</p>';
			return;
		}
		?>
		<p class="description"><?php esc_html_e( 'Select which attributes link the products together. Drag to reorder.', 'wc-linked-variations' ); ?></p>
		<ul id="wclv-attribute-list" class="wclv-sortable">
			<?php
			$all_slugs = array();
			foreach ( $attribute_taxonomies as $attr ) {
				$all_slugs[] = wc_attribute_taxonomy_name( $attr->attribute_name );
			}

			$ordered = array_merge(
				array_intersect( $saved_attrs, $all_slugs ),
				array_diff( $all_slugs, $saved_attrs )
			);

			foreach ( $ordered as $slug ) :
				$attr_obj = null;
				foreach ( $attribute_taxonomies as $at ) {
					if ( wc_attribute_taxonomy_name( $at->attribute_name ) === $slug ) {
						$attr_obj = $at;
						break;
					}
				}
				if ( ! $attr_obj ) {
					continue;
				}
				$checked = in_array( $slug, $saved_attrs, true );
				?>
				<li class="wclv-attribute-item">
					<span class="wclv-drag-handle dashicons dashicons-menu"></span>
					<label>
						<input type="checkbox" name="wclv_attributes[]" value="<?php echo esc_attr( $slug ); ?>" <?php checked( $checked ); ?>>
						<?php echo esc_html( $attr_obj->attribute_label ); ?>
					</label>
				</li>
			<?php endforeach; ?>
		</ul>
		<?php
	}

	/* ── Display Settings Meta Box ─────────────────────────────────── */

	public static function render_display_box( $post ) {
		$group      = WCLV_Database::get_group_by_post_id( $post->ID );
		$style      = $group ? $group->style : 'button';
		$show_image = $group ? (bool) $group->show_image : false;
		?>
		<p>
			<label for="wclv_style"><strong><?php esc_html_e( 'Style', 'wc-linked-variations' ); ?></strong></label><br>
			<select id="wclv_style" name="wclv_style" style="width:100%">
				<option value="button" <?php selected( $style, 'button' ); ?>><?php esc_html_e( 'Buttons', 'wc-linked-variations' ); ?></option>
				<option value="dropdown" <?php selected( $style, 'dropdown' ); ?>><?php esc_html_e( 'Dropdown', 'wc-linked-variations' ); ?></option>
				<option value="image" <?php selected( $style, 'image' ); ?>><?php esc_html_e( 'Image Thumbnails', 'wc-linked-variations' ); ?></option>
			</select>
		</p>
		<p>
			<label>
				<input type="checkbox" name="wclv_show_image" value="1" <?php checked( $show_image ); ?>>
				<?php esc_html_e( 'Show product image for first attribute', 'wc-linked-variations' ); ?>
			</label>
		</p>
		<?php
	}

	/* ── Save ──────────────────────────────────────────────────────── */

	public static function save( $post_id, $post ) {
		if ( ! isset( $_POST['wclv_meta_nonce'] ) || ! wp_verify_nonce( $_POST['wclv_meta_nonce'], 'wclv_save_meta' ) ) {
			return;
		}

		if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) {
			return;
		}

		if ( ! current_user_can( 'edit_post', $post_id ) ) {
			return;
		}

		$data = array(
			'product_source' => isset( $_POST['wclv_product_source'] ) ? $_POST['wclv_product_source'] : 'manual',
			'product_ids'    => isset( $_POST['wclv_product_ids'] ) ? $_POST['wclv_product_ids'] : array(),
			'taxonomy'       => isset( $_POST['wclv_taxonomy'] ) ? $_POST['wclv_taxonomy'] : '',
			'taxonomy_terms' => isset( $_POST['wclv_taxonomy_terms'] ) ? $_POST['wclv_taxonomy_terms'] : array(),
			'attributes'     => isset( $_POST['wclv_attributes'] ) ? $_POST['wclv_attributes'] : array(),
			'show_image'     => isset( $_POST['wclv_show_image'] ) ? 1 : 0,
			'style'          => isset( $_POST['wclv_style'] ) ? $_POST['wclv_style'] : 'button',
		);

		WCLV_Database::save( $post_id, $data );
	}
}
