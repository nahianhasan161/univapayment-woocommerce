<?php
/**
 * Plugin Name:       UnivaPayment for WooCommerce
 * Plugin URI:        https://univapayment-web.univapayment.workers.dev
 * Description:       Take bKash and Nagad payments on your own number. UnivaPayment checks each payment from the SMS on your phone and confirms the order.
 * Version:           1.0.1
 * Requires at least: 6.5
 * Requires PHP:      7.4
 * Requires Plugins:  woocommerce
 * WC requires at least: 8.0
 * Author:            UnivaPayment
 * License:           GPL-2.0-or-later
 * License URI:       https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain:       univapayment-for-woocommerce
 */

defined( 'ABSPATH' ) || exit;

define( 'UNIVAPAYMENT_VERSION', '1.0.1' );
define( 'UNIVAPAYMENT_FILE', __FILE__ );
define( 'UNIVAPAYMENT_DEFAULT_SERVER', 'https://univapayment-web.univapayment.workers.dev' );

require_once __DIR__ . '/includes/class-signature.php';

add_action(
	'before_woocommerce_init',
	static function () {
		if ( class_exists( '\Automattic\WooCommerce\Utilities\FeaturesUtil' ) ) {
			\Automattic\WooCommerce\Utilities\FeaturesUtil::declare_compatibility( 'custom_order_tables', __FILE__, true );
			\Automattic\WooCommerce\Utilities\FeaturesUtil::declare_compatibility( 'cart_checkout_blocks', __FILE__, true );
		}
	}
);

add_action(
	'plugins_loaded',
	static function () {
		if ( ! class_exists( 'WC_Payment_Gateway' ) ) {
			return;
		}
		require_once __DIR__ . '/includes/class-api.php';
		require_once __DIR__ . '/includes/class-gateway.php';
		require_once __DIR__ . '/includes/class-webhook.php';

		add_filter(
			'woocommerce_payment_gateways',
			static function ( $gateways ) {
				$gateways[] = 'UnivaPayment_Gateway';
				return $gateways;
			}
		);
		UnivaPayment_Webhook::register();
	}
);

add_action(
	'woocommerce_blocks_payment_method_type_registration',
	static function ( $registry ) {
		require_once __DIR__ . '/includes/class-blocks.php';
		$registry->register( new UnivaPayment_Blocks() );
	}
);

add_filter(
	'plugin_action_links_' . plugin_basename( __FILE__ ),
	static function ( $links ) {
		$url = admin_url( 'admin.php?page=wc-settings&tab=checkout&section=univapayment' );
		array_unshift( $links, '<a href="' . esc_url( $url ) . '">' . esc_html__( 'Settings', 'univapayment-for-woocommerce' ) . '</a>' );
		return $links;
	}
);
