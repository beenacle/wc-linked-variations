<?php
/**
 * Plugin Name: WC Linked Variations
 * Plugin URI:  https://github.com/beenacle/wc-linked-variations
 * Description: Link separate WooCommerce products together by shared attributes and display them as variable-product-style switchers.
 * Version:     1.1.1
 * Author:      Beenacle
 * Author URI:  https://beenacle.com
 * License:     GPL-2.0-or-later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain: wc-linked-variations
 * Domain Path: /languages
 * Requires at least: 5.8
 * Requires PHP: 7.4
 * WC requires at least: 6.0
 * WC tested up to: 10.6.2
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

define( 'WCLV_VERSION', '1.1.1' );
define( 'WCLV_PLUGIN_FILE', __FILE__ );
define( 'WCLV_PLUGIN_DIR', plugin_dir_path( __FILE__ ) );
define( 'WCLV_PLUGIN_URL', plugin_dir_url( __FILE__ ) );
define( 'WCLV_PLUGIN_BASENAME', plugin_basename( __FILE__ ) );

require_once WCLV_PLUGIN_DIR . 'vendor/yahnis-elsts/plugin-update-checker/load-v5p6.php';

use YahnisElsts\PluginUpdateChecker\v5p6\PucFactory;

$wclvUpdateChecker = PucFactory::buildUpdateChecker(
	'https://github.com/beenacle/wc-linked-variations/',
	__FILE__,
	'wc-linked-variations'
);
$wclvUpdateChecker->setBranch( 'main' );
$wclvUpdateChecker->getVcsApi()->enableReleaseAssets();

/**
 * Check that WooCommerce is active before bootstrapping.
 */
function wclv_check_woocommerce() {
	if ( ! class_exists( 'WooCommerce' ) ) {
		add_action( 'admin_notices', function () {
			echo '<div class="notice notice-error"><p>';
			esc_html_e( 'WC Linked Variations requires WooCommerce to be installed and active.', 'wc-linked-variations' );
			echo '</p></div>';
		} );
		return false;
	}
	return true;
}

/**
 * Autoload plugin classes from the includes/ directory.
 */
spl_autoload_register( function ( $class ) {
	$prefix = 'WCLV_';
	if ( 0 !== strpos( $class, $prefix ) ) {
		return;
	}

	$relative = substr( $class, strlen( $prefix ) );
	$file     = WCLV_PLUGIN_DIR . 'includes/class-wclv-' . strtolower( str_replace( '_', '-', $relative ) ) . '.php';

	if ( file_exists( $file ) ) {
		require_once $file;
	}
} );

add_action( 'before_woocommerce_init', function () {
	if ( class_exists( '\Automattic\WooCommerce\Utilities\FeaturesUtil' ) ) {
		\Automattic\WooCommerce\Utilities\FeaturesUtil::declare_compatibility( 'custom_order_tables', __FILE__, true );
	}
} );

register_activation_hook( __FILE__, array( 'WCLV_Activator', 'activate' ) );
register_deactivation_hook( __FILE__, array( 'WCLV_Deactivator', 'deactivate' ) );

add_action( 'plugins_loaded', function () {
	if ( ! wclv_check_woocommerce() ) {
		return;
	}

	WCLV_Post_Type::init();
	WCLV_Admin_Meta::init();
	WCLV_Ajax::init();
	WCLV_Frontend::init();
	WCLV_Shortcode::init();

	if ( is_admin() ) {
		WCLV_Import::init();
	}
} );
