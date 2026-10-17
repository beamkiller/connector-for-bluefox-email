<?php
/**
 * Plugin Name:          Connector for bluefox.email – Newsletter and WooCommerce Emails
 * Plugin URI:           https://wordpress.org/plugins/connector-for-bluefox-email/
 * Description:          Connect WordPress and WooCommerce to bluefox.email: signup forms with double opt-in, checkout opt-in, welcome coupons, contact sync, WooCommerce emails and wp_mail delivery.
 * Version:              1.0.0
 * Requires at least:    6.7
 * Requires PHP:         8.1
 * Author:               Peter Kulcsár - KREMONT s.r.o.
 * Author URI:           https://www.linkedin.com/in/peterkulcsar/
 * License:              GPL-2.0-or-later
 * License URI:          https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain:          connector-for-bluefox-email
 * Domain Path:          /languages
 * WC requires at least: 9.0
 * WC tested up to:      11.2
 *
 * @package CFBE
 */

defined( 'ABSPATH' ) || exit;

define( 'CFBE_VERSION', '1.0.0' );
define( 'CFBE_FILE', __FILE__ );
define( 'CFBE_DIR', plugin_dir_path( __FILE__ ) );
define( 'CFBE_URL', plugin_dir_url( __FILE__ ) );

require_once CFBE_DIR . 'includes/class-autoloader.php';
CFBE\Autoloader::register( CFBE_DIR );

register_activation_hook( __FILE__, array( 'CFBE\Plugin', 'activate' ) );
register_deactivation_hook( __FILE__, array( 'CFBE\Plugin', 'deactivate' ) );

add_action(
	'before_woocommerce_init',
	static function () {
		if ( class_exists( \Automattic\WooCommerce\Utilities\FeaturesUtil::class ) ) {
			\Automattic\WooCommerce\Utilities\FeaturesUtil::declare_compatibility( 'custom_order_tables', __FILE__, true );
			\Automattic\WooCommerce\Utilities\FeaturesUtil::declare_compatibility( 'cart_checkout_blocks', __FILE__, true );
		}
	}
);

CFBE\Plugin::boot();
