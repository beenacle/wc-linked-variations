<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class WCLV_Shortcode {

	public static function init() {
		add_shortcode( 'wclv_links', array( __CLASS__, 'render' ) );
	}

	/**
	 * [wclv_links] shortcode.
	 *
	 * Attributes:
	 *   product_id  – override the current product (default: current global $product)
	 *   group_id    – render a specific group only (default: all groups for the product)
	 */
	public static function render( $atts ) {
		$atts = shortcode_atts( array(
			'product_id' => 0,
			'group_id'   => 0,
		), $atts, 'wclv_links' );

		$product_id = absint( $atts['product_id'] );

		if ( ! $product_id ) {
			global $product;
			if ( $product instanceof WC_Product ) {
				$product_id = $product->get_id();
			}
		}

		if ( ! $product_id ) {
			return '';
		}

		$wc_product = wc_get_product( $product_id );
		if ( ! $wc_product ) {
			return '';
		}

		$group_id = absint( $atts['group_id'] );

		if ( $group_id ) {
			$group = WCLV_Database::get_group_by_post_id( $group_id );
			if ( ! $group ) {
				return '';
			}

			if ( 'taxonomy' === $group->product_source ) {
				$group->resolved_product_ids = array_map( 'intval', WCLV_Database::resolve_taxonomy_products( $group ) );
			} else {
				$group->resolved_product_ids = array_map( 'intval', maybe_unserialize( $group->product_ids ) );
			}
			$groups = array( $group );
		} else {
			$groups = WCLV_Database::get_groups_for_product( $product_id );
		}

		if ( empty( $groups ) ) {
			return '';
		}

		wp_enqueue_style( 'wclv-frontend', WCLV_PLUGIN_URL . 'assets/css/frontend.css', array(), WCLV_VERSION );
		wp_enqueue_script( 'wclv-frontend', WCLV_PLUGIN_URL . 'assets/js/frontend.js', array( 'jquery' ), WCLV_VERSION, true );

		ob_start();
		foreach ( $groups as $g ) {
			WCLV_Frontend::render_group( $g, $wc_product );
		}
		return ob_get_clean();
	}
}
