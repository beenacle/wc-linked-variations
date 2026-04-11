<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class WCLV_Ajax {

	public static function init() {
		add_action( 'wp_ajax_wclv_search_products', array( __CLASS__, 'search_products' ) );
		add_action( 'wp_ajax_wclv_get_taxonomy_terms', array( __CLASS__, 'get_taxonomy_terms' ) );
	}

	/**
	 * AJAX: Search WooCommerce products for Select2.
	 */
	public static function search_products() {
		check_ajax_referer( 'wclv_search_products', 'security' );

		if ( ! current_user_can( 'edit_products' ) ) {
			wp_send_json_error();
		}

		$term = isset( $_GET['term'] ) ? sanitize_text_field( wp_unslash( $_GET['term'] ) ) : '';

		$args = array(
			'post_type'      => 'product',
			'post_status'    => 'publish',
			'posts_per_page' => 30,
			's'              => $term,
			'fields'         => 'ids',
		);

		$query   = new WP_Query( $args );
		$results = array();

		foreach ( $query->posts as $pid ) {
			$product = wc_get_product( $pid );
			if ( ! $product ) {
				continue;
			}
			$results[] = array(
				'id'   => $pid,
				'text' => sprintf( '%s (#%d)', $product->get_name(), $pid ),
			);
		}

		wp_send_json( array( 'results' => $results ) );
	}

	/**
	 * AJAX: Get terms for a given taxonomy.
	 */
	public static function get_taxonomy_terms() {
		check_ajax_referer( 'wclv_get_taxonomy_terms', 'security' );

		if ( ! current_user_can( 'edit_products' ) ) {
			wp_send_json_error();
		}

		$taxonomy = isset( $_GET['taxonomy'] ) ? sanitize_text_field( wp_unslash( $_GET['taxonomy'] ) ) : '';

		if ( empty( $taxonomy ) || ! taxonomy_exists( $taxonomy ) ) {
			wp_send_json( array( 'results' => array() ) );
		}

		$search = isset( $_GET['term'] ) ? sanitize_text_field( wp_unslash( $_GET['term'] ) ) : '';

		$terms = get_terms( array(
			'taxonomy'   => $taxonomy,
			'hide_empty' => false,
			'search'     => $search,
			'number'     => 50,
		) );

		$results = array();
		if ( ! is_wp_error( $terms ) ) {
			foreach ( $terms as $term ) {
				$results[] = array(
					'id'   => $term->term_id,
					'text' => $term->name,
				);
			}
		}

		wp_send_json( array( 'results' => $results ) );
	}
}
