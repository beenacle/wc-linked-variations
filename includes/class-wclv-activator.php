<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class WCLV_Activator {

	public static function activate() {
		self::create_table();
		flush_rewrite_rules();
	}

	private static function create_table() {
		global $wpdb;

		$table_name      = $wpdb->prefix . 'wclv_groups';
		$charset_collate = $wpdb->get_charset_collate();

		$sql = "CREATE TABLE {$table_name} (
			id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
			post_id bigint(20) unsigned NOT NULL,
			product_source varchar(20) NOT NULL DEFAULT 'manual',
			product_ids longtext,
			taxonomy varchar(100) DEFAULT '',
			taxonomy_terms longtext,
			attributes longtext,
			show_image tinyint(1) NOT NULL DEFAULT 0,
			style varchar(20) NOT NULL DEFAULT 'button',
			PRIMARY KEY  (id),
			KEY post_id (post_id)
		) {$charset_collate};";

		require_once ABSPATH . 'wp-admin/includes/upgrade.php';
		dbDelta( $sql );
	}
}
