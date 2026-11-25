<?php
/**
 * Plugin Name:       CODWarden: Stop Fake Cash on Delivery Orders for WooCommerce
 * Plugin URI:        https://github.com/hoseniqbal/codwarden
 * Description:       Stops fake and repeat-refused cash on delivery orders: phone checks, order limits, duplicate guard and a refuser list.
 * Version:           1.0.0
 * Requires at least: 6.2
 * Requires PHP:      7.4
 * Requires Plugins:  woocommerce
 * Author:            Iqbal Hossen
 * Author URI:        https://profiles.wordpress.org/iqbal511138/
 * License:           GPL-2.0-or-later
 * License URI:       https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain:       codwarden
 * WC requires at least: 7.6
 * WC tested up to:   11.3
 *
 * @package CODWarden
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

define( 'CODWARDEN_VERSION', '1.0.0' );
define( 'CODWARDEN_DB_VERSION', '1' );
define( 'CODWARDEN_FILE', __FILE__ );
define( 'CODWARDEN_PATH', plugin_dir_path( __FILE__ ) );
define( 'CODWARDEN_URL', plugin_dir_url( __FILE__ ) );

require_once CODWARDEN_PATH . 'includes/class-codwarden-settings.php';
require_once CODWARDEN_PATH . 'includes/class-codwarden-phone.php';
require_once CODWARDEN_PATH . 'includes/class-codwarden-store.php';
require_once CODWARDEN_PATH . 'includes/class-codwarden-guard.php';
require_once CODWARDEN_PATH . 'includes/class-codwarden-admin.php';
require_once CODWARDEN_PATH . 'includes/class-codwarden-plugin.php';

register_activation_hook( __FILE__, array( 'CODWarden_Plugin', 'activate' ) );
register_deactivation_hook( __FILE__, array( 'CODWarden_Plugin', 'deactivate' ) );

add_action(
	'before_woocommerce_init',
	static function () {
		if ( class_exists( '\Automattic\WooCommerce\Utilities\FeaturesUtil' ) ) {
			\Automattic\WooCommerce\Utilities\FeaturesUtil::declare_compatibility( 'custom_order_tables', __FILE__, true );
			\Automattic\WooCommerce\Utilities\FeaturesUtil::declare_compatibility( 'cart_checkout_blocks', __FILE__, true );
		}
	}
);

add_action( 'plugins_loaded', array( 'CODWarden_Plugin', 'init' ) );
