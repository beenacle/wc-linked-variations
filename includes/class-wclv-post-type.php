<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class WCLV_Post_Type {

	const POST_TYPE = 'wc_linked_vars';

	public static function init() {
		add_action( 'init', array( __CLASS__, 'register' ) );
		add_filter( 'manage_' . self::POST_TYPE . '_posts_columns', array( __CLASS__, 'admin_columns' ) );
		add_action( 'manage_' . self::POST_TYPE . '_posts_custom_column', array( __CLASS__, 'admin_column_content' ), 10, 2 );
		add_action( 'before_delete_post', array( __CLASS__, 'delete_group_data' ) );
	}

	/**
	 * Remove a group's stored data when its post is permanently deleted.
	 *
	 * Wired to before_delete_post, which fires on permanent deletion but not
	 * on trashing, so custom-table rows follow the post lifecycle instead of
	 * orphaning. Trashed posts keep their row so a restore stays lossless.
	 * Guarded to this post type because the hook fires for every post.
	 *
	 * @param int $post_id The post being deleted.
	 */
	public static function delete_group_data( $post_id ) {
		if ( self::POST_TYPE !== get_post_type( $post_id ) ) {
			return;
		}

		WCLV_Database::delete( $post_id );
	}

	public static function register() {
		$labels = array(
			'name'               => __( 'Linked Variations', 'wc-linked-variations' ),
			'singular_name'      => __( 'Linked Variation Group', 'wc-linked-variations' ),
			'add_new'            => __( 'Add New', 'wc-linked-variations' ),
			'add_new_item'       => __( 'Add New Group', 'wc-linked-variations' ),
			'edit_item'          => __( 'Edit Group', 'wc-linked-variations' ),
			'new_item'           => __( 'New Group', 'wc-linked-variations' ),
			'view_item'          => __( 'View Group', 'wc-linked-variations' ),
			'search_items'       => __( 'Search Groups', 'wc-linked-variations' ),
			'not_found'          => __( 'No linked variation groups found.', 'wc-linked-variations' ),
			'not_found_in_trash' => __( 'No linked variation groups found in Trash.', 'wc-linked-variations' ),
			'menu_name'          => __( 'Linked Variations', 'wc-linked-variations' ),
		);

		$args = array(
			'labels'             => $labels,
			'public'             => false,
			'show_ui'            => true,
			'show_in_menu'       => 'edit.php?post_type=product',
			'capability_type'    => 'post',
			'supports'           => array( 'title' ),
			'has_archive'        => false,
			'rewrite'            => false,
			'query_var'          => false,
		);

		register_post_type( self::POST_TYPE, $args );
	}

	public static function admin_columns( $columns ) {
		$new = array();
		foreach ( $columns as $key => $label ) {
			$new[ $key ] = $label;
			if ( 'title' === $key ) {
				$new['wclv_products']   = __( 'Products', 'wc-linked-variations' );
				$new['wclv_attributes'] = __( 'Attributes', 'wc-linked-variations' );
				$new['wclv_style']      = __( 'Style', 'wc-linked-variations' );
			}
		}
		return $new;
	}

	public static function admin_column_content( $column, $post_id ) {
		$group = WCLV_Database::get_group_by_post_id( $post_id );
		if ( ! $group ) {
			echo '&mdash;';
			return;
		}

		switch ( $column ) {
			case 'wclv_products':
				if ( 'taxonomy' === $group->product_source ) {
					$terms = maybe_unserialize( $group->taxonomy_terms );
					/* translators: 1: taxonomy slug wrapped in <code>, 2: number of terms */
					printf(
						__( 'Via %1$s (%2$d terms)', 'wc-linked-variations' ),
						'<code>' . esc_html( $group->taxonomy ) . '</code>',
						is_array( $terms ) ? count( $terms ) : 0
					);
				} else {
					$ids = maybe_unserialize( $group->product_ids );
					/* translators: %d: number of products in the group */
					printf( _n( '%d product', '%d products', is_array( $ids ) ? count( $ids ) : 0, 'wc-linked-variations' ), is_array( $ids ) ? count( $ids ) : 0 );
				}
				break;

			case 'wclv_attributes':
				$attrs = maybe_unserialize( $group->attributes );
				if ( is_array( $attrs ) ) {
					$names = array_map( function ( $slug ) {
						$tax = get_taxonomy( $slug );
						return $tax ? $tax->labels->singular_name : $slug;
					}, $attrs );
					echo esc_html( implode( ', ', $names ) );
				} else {
					echo '&mdash;';
				}
				break;

			case 'wclv_style':
				echo esc_html( ucfirst( $group->style ) );
				break;
		}
	}
}
