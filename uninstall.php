<?php
/**
 * Fired when the plugin is uninstalled via the WordPress admin.
 */

if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
	exit;
}

global $wpdb;

$table = $wpdb->prefix . 'wclv_groups';
$wpdb->query( "DROP TABLE IF EXISTS {$table}" );

$posts = get_posts( array(
	'post_type'      => 'wc_linked_vars',
	'posts_per_page' => -1,
	'fields'         => 'ids',
	'post_status'    => 'any',
) );

foreach ( $posts as $post_id ) {
	wp_delete_post( $post_id, true );
}
