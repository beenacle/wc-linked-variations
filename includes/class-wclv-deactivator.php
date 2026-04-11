<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class WCLV_Deactivator {

	public static function deactivate() {
		flush_rewrite_rules();
	}
}
