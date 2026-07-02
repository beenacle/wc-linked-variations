<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class WCLV_Frontend {

	public static function init() {
		$priority = apply_filters( 'wclv_display_priority', 25 );
		add_action( 'woocommerce_single_product_summary', array( __CLASS__, 'output' ), $priority );
		add_action( 'wp_enqueue_scripts', array( __CLASS__, 'enqueue_assets' ) );
	}

	public static function enqueue_assets() {
		if ( ! is_product() ) {
			return;
		}

		wp_enqueue_style(
			'wclv-frontend',
			WCLV_PLUGIN_URL . 'assets/css/frontend.css',
			array(),
			WCLV_VERSION
		);

		wp_enqueue_script(
			'wclv-frontend',
			WCLV_PLUGIN_URL . 'assets/js/frontend.js',
			array( 'jquery' ),
			WCLV_VERSION,
			true
		);
	}

	/**
	 * Main output on the single product page.
	 */
	public static function output() {
		global $product;

		if ( ! $product instanceof WC_Product ) {
			return;
		}

		$product_id = $product->get_id();
		$groups     = WCLV_Database::get_groups_for_product( $product_id );

		if ( empty( $groups ) ) {
			return;
		}

		foreach ( $groups as $group ) {
			self::render_group( $group, $product );
		}
	}

	/**
	 * Render a single linked-variation group for the current product.
	 */
	public static function render_group( $group, $current_product ) {
		$attributes  = maybe_unserialize( $group->attributes );
		$product_ids = isset( $group->resolved_product_ids )
			? $group->resolved_product_ids
			: maybe_unserialize( $group->product_ids );

		if ( empty( $attributes ) || ! is_array( $attributes ) || empty( $product_ids ) ) {
			return;
		}

		$products = array();
		foreach ( $product_ids as $pid ) {
			$p = wc_get_product( $pid );
			if ( $p && self::is_displayable( $p ) ) {
				$products[ $pid ] = $p;
			}
		}

		if ( count( $products ) < 2 ) {
			return;
		}

		$current_id      = $current_product->get_id();
		$current_attrs   = self::get_product_attribute_values( $current_product, $attributes );
		$style           = $group->style ?: 'button';
		$show_image      = (bool) $group->show_image;

		do_action( 'wclv_before_render', $group, $current_product );

		echo '<div class="wclv-linked-variations">';

		foreach ( $attributes as $i => $attribute_slug ) {
			$taxonomy = get_taxonomy( $attribute_slug );
			$label    = $taxonomy ? $taxonomy->labels->singular_name : $attribute_slug;

			$options     = self::build_options( $attribute_slug, $attributes, $current_attrs, $products, $current_id );
			$use_images  = $show_image && 0 === $i;

			if ( empty( $options ) ) {
				continue;
			}

			echo '<div class="wclv-row">';
			echo '<label class="wclv-label">' . esc_html( $label ) . '</label>';

			switch ( $style ) {
				case 'dropdown':
					self::render_dropdown( $options, $use_images );
					break;
				case 'image':
					self::render_images( $options );
					break;
				default:
					self::render_buttons( $options, $use_images );
					break;
			}

			echo '</div>';
		}

		echo '</div>';

		do_action( 'wclv_after_render', $group, $current_product );
	}

	/**
	 * Build the array of selectable options for one attribute row.
	 *
	 * Each option:
	 *   term_name, term_slug, product_id, url, is_active, in_stock, thumbnail_url
	 */
	private static function build_options( $attribute_slug, $all_attributes, $current_attrs, $products, $current_id ) {
		$seen    = array();
		$options = array();

		foreach ( $products as $pid => $product ) {
			$terms = wp_get_post_terms( $pid, $attribute_slug );
			if ( is_wp_error( $terms ) || empty( $terms ) ) {
				continue;
			}

			$term = $terms[0];

			if ( isset( $seen[ $term->slug ] ) ) {
				continue;
			}

			$is_active = ( (int) $pid === (int) $current_id );

			if ( ! $is_active ) {
				$target = self::find_matching_product( $attribute_slug, $term->slug, $all_attributes, $current_attrs, $products );
				if ( ! $target ) {
					continue;
				}
				$pid     = $target->get_id();
				$product = $target;
			}

			$url       = apply_filters( 'wclv_product_link', get_permalink( $pid ), $pid, $attribute_slug );
			$in_stock  = $product->is_in_stock();
			$thumb_url = get_the_post_thumbnail_url( $pid, 'woocommerce_gallery_thumbnail' );

			$option_label = apply_filters( 'wclv_option_label', $term->name, $term, $attribute_slug );

			$options[] = array(
				'term_name'     => $option_label,
				'term_slug'     => $term->slug,
				'product_id'    => $pid,
				'url'           => $url,
				'is_active'     => $is_active,
				'in_stock'      => $in_stock,
				'thumbnail_url' => $thumb_url ?: '',
			);

			$seen[ $term->slug ] = true;
		}

		return apply_filters( 'wclv_group_products', $options, $attribute_slug );
	}

	/**
	 * Given the attribute we're switching, find the product that matches
	 * all the *other* current attribute values but has the desired term
	 * for the switching attribute.
	 */
	private static function find_matching_product( $switching_attr, $desired_term_slug, $all_attributes, $current_attrs, $products ) {
		foreach ( $products as $pid => $product ) {
			$p_terms = wp_get_post_terms( $pid, $switching_attr );
			if ( is_wp_error( $p_terms ) || empty( $p_terms ) ) {
				continue;
			}

			$has_desired = false;
			foreach ( $p_terms as $t ) {
				if ( $t->slug === $desired_term_slug ) {
					$has_desired = true;
					break;
				}
			}
			if ( ! $has_desired ) {
				continue;
			}

			$matches_others = true;
			foreach ( $all_attributes as $attr_slug ) {
				if ( $attr_slug === $switching_attr ) {
					continue;
				}
				$other_terms = wp_get_post_terms( $pid, $attr_slug );
				if ( is_wp_error( $other_terms ) || empty( $other_terms ) ) {
					$matches_others = false;
					break;
				}
				$other_slugs = wp_list_pluck( $other_terms, 'slug' );
				if ( ! isset( $current_attrs[ $attr_slug ] ) || ! in_array( $current_attrs[ $attr_slug ], $other_slugs, true ) ) {
					$matches_others = false;
					break;
				}
			}

			if ( $matches_others ) {
				return $product;
			}
		}

		return null;
	}

	/**
	 * Whether a linked product may be surfaced on the storefront.
	 *
	 * Only published products are shown to visitors, matching the taxonomy
	 * resolver (WCLV_Database::resolve_taxonomy_products) which queries
	 * post_status => 'publish'. This keeps unpublished products — drafts,
	 * private, scheduled — out of the switcher even when an admin selected
	 * them in the backend, or when a previously-published product was later
	 * unpublished.
	 */
	private static function is_displayable( $product ) {
		$displayable = ( 'publish' === get_post_status( $product->get_id() ) );

		/**
		 * Filter whether a linked product is shown on the storefront.
		 *
		 * @param bool       $displayable Whether the product should render.
		 * @param WC_Product $product     The linked product.
		 */
		return (bool) apply_filters( 'wclv_is_product_displayable', $displayable, $product );
	}

	/**
	 * Get current product's term slug for each linked attribute.
	 */
	private static function get_product_attribute_values( $product, $attributes ) {
		$values = array();
		foreach ( $attributes as $attr_slug ) {
			$terms = wp_get_post_terms( $product->get_id(), $attr_slug );
			if ( ! is_wp_error( $terms ) && ! empty( $terms ) ) {
				$values[ $attr_slug ] = $terms[0]->slug;
			}
		}
		return $values;
	}

	/* ── Render helpers ────────────────────────────────────────── */

	private static function render_buttons( $options, $use_images ) {
		echo '<div class="wclv-options wclv-options--buttons">';
		foreach ( $options as $opt ) {
			$classes = array( 'wclv-btn' );
			if ( $opt['is_active'] ) {
				$classes[] = 'wclv-btn--active';
			}
			if ( ! $opt['in_stock'] ) {
				$classes[] = 'wclv-btn--disabled';
			}

			$html = '';
			if ( $opt['is_active'] || ! $opt['in_stock'] ) {
				$html .= '<span class="' . esc_attr( implode( ' ', $classes ) ) . '">';
			} else {
				$html .= '<a href="' . esc_url( $opt['url'] ) . '" class="' . esc_attr( implode( ' ', $classes ) ) . '">';
			}

			if ( $use_images && $opt['thumbnail_url'] ) {
				$html .= '<img src="' . esc_url( $opt['thumbnail_url'] ) . '" alt="' . esc_attr( $opt['term_name'] ) . '" class="wclv-btn__img">';
			}

			$html .= '<span class="wclv-btn__label">' . esc_html( $opt['term_name'] ) . '</span>';

			if ( $opt['is_active'] || ! $opt['in_stock'] ) {
				$html .= '</span>';
			} else {
				$html .= '</a>';
			}

			echo apply_filters( 'wclv_option_html', $html, $opt );
		}
		echo '</div>';
	}

	private static function render_dropdown( $options, $use_images ) {
		echo '<div class="wclv-options wclv-options--dropdown">';
		echo '<select class="wclv-select">';
		foreach ( $options as $opt ) {
			$disabled = ! $opt['in_stock'] ? ' disabled' : '';
			$selected = $opt['is_active'] ? ' selected' : '';
			$value    = $opt['is_active'] ? '' : esc_url( $opt['url'] );
			$label    = $opt['term_name'];
			if ( ! $opt['in_stock'] ) {
				$label .= ' (' . __( 'Out of stock', 'wc-linked-variations' ) . ')';
			}
			printf(
				'<option value="%s"%s%s>%s</option>',
				esc_attr( $value ),
				$selected,
				$disabled,
				esc_html( $label )
			);
		}
		echo '</select>';
		echo '</div>';
	}

	private static function render_images( $options ) {
		echo '<div class="wclv-options wclv-options--images">';
		foreach ( $options as $opt ) {
			$classes = array( 'wclv-img-swatch' );
			if ( $opt['is_active'] ) {
				$classes[] = 'wclv-img-swatch--active';
			}
			if ( ! $opt['in_stock'] ) {
				$classes[] = 'wclv-img-swatch--disabled';
			}

			$tag = ( $opt['is_active'] || ! $opt['in_stock'] ) ? 'span' : 'a';
			$href = ( 'a' === $tag ) ? ' href="' . esc_url( $opt['url'] ) . '"' : '';

			$html  = '<' . $tag . $href . ' class="' . esc_attr( implode( ' ', $classes ) ) . '" title="' . esc_attr( $opt['term_name'] ) . '">';
			if ( $opt['thumbnail_url'] ) {
				$html .= '<img src="' . esc_url( $opt['thumbnail_url'] ) . '" alt="' . esc_attr( $opt['term_name'] ) . '">';
			} else {
				$html .= '<span class="wclv-img-swatch__label">' . esc_html( $opt['term_name'] ) . '</span>';
			}
			$html .= '</' . $tag . '>';

			echo apply_filters( 'wclv_option_html', $html, $opt );
		}
		echo '</div>';
	}
}
