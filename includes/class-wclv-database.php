<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class WCLV_Database {

	private static function table_name() {
		global $wpdb;
		return $wpdb->prefix . 'wclv_groups';
	}

	public static function get_group_by_post_id( $post_id ) {
		global $wpdb;
		$table = self::table_name();

		return $wpdb->get_row(
			$wpdb->prepare( "SELECT * FROM {$table} WHERE post_id = %d LIMIT 1", $post_id )
		);
	}

	public static function save( $post_id, $data ) {
		global $wpdb;
		$table    = self::table_name();
		$existing = self::get_group_by_post_id( $post_id );

		$row = array(
			'post_id'        => $post_id,
			'product_source' => isset( $data['product_source'] ) ? sanitize_text_field( $data['product_source'] ) : 'manual',
			'product_ids'    => isset( $data['product_ids'] ) ? maybe_serialize( array_map( 'absint', (array) $data['product_ids'] ) ) : '',
			'taxonomy'       => isset( $data['taxonomy'] ) ? sanitize_text_field( $data['taxonomy'] ) : '',
			'taxonomy_terms' => isset( $data['taxonomy_terms'] ) ? maybe_serialize( array_map( 'absint', (array) $data['taxonomy_terms'] ) ) : '',
			'attributes'     => isset( $data['attributes'] ) ? maybe_serialize( array_map( 'sanitize_text_field', (array) $data['attributes'] ) ) : '',
			'show_image'     => ! empty( $data['show_image'] ) ? 1 : 0,
			'style'          => isset( $data['style'] ) ? sanitize_text_field( $data['style'] ) : 'button',
		);

		$format = array( '%d', '%s', '%s', '%s', '%s', '%s', '%d', '%s' );

		if ( $existing ) {
			$wpdb->update( $table, $row, array( 'post_id' => $post_id ), $format, array( '%d' ) );
		} else {
			$wpdb->insert( $table, $row, $format );
		}
	}

	public static function delete( $post_id ) {
		global $wpdb;
		$table = self::table_name();
		$wpdb->delete( $table, array( 'post_id' => $post_id ), array( '%d' ) );
	}

	/**
	 * Find all groups that contain a given product ID.
	 */
	public static function get_groups_for_product( $product_id ) {
		global $wpdb;
		$table  = self::table_name();
		$groups = array();

		$rows = $wpdb->get_results( "SELECT * FROM {$table}" );
		if ( ! $rows ) {
			return $groups;
		}

		foreach ( $rows as $row ) {
			if ( 'taxonomy' === $row->product_source ) {
				$product_ids = self::resolve_taxonomy_products( $row );
			} else {
				$product_ids = maybe_unserialize( $row->product_ids );
			}

			if ( is_array( $product_ids ) && in_array( (int) $product_id, array_map( 'intval', $product_ids ), true ) ) {
				$row->resolved_product_ids = array_map( 'intval', $product_ids );
				$groups[]                  = $row;
			}
		}

		return $groups;
	}

	/**
	 * Resolve product IDs from taxonomy terms.
	 */
	public static function resolve_taxonomy_products( $group ) {
		$taxonomy = $group->taxonomy;
		$terms    = maybe_unserialize( $group->taxonomy_terms );

		if ( empty( $taxonomy ) || empty( $terms ) || ! is_array( $terms ) ) {
			return array();
		}

		$args = array(
			'post_type'      => 'product',
			'posts_per_page' => -1,
			'fields'         => 'ids',
			'post_status'    => 'publish',
			'tax_query'      => array(
				array(
					'taxonomy' => $taxonomy,
					'field'    => 'term_id',
					'terms'    => $terms,
				),
			),
		);

		return get_posts( $args );
	}
}
