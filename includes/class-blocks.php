<?php
defined( 'ABSPATH' ) || exit;

use Automattic\WooCommerce\Blocks\Payments\Integrations\AbstractPaymentMethodType;

/** The block checkout, which is WooCommerce's default, needs this to list the method. */
class UnivaPayment_Blocks extends AbstractPaymentMethodType {
	protected $name = 'univapayment';

	public function initialize() {
		$this->settings = get_option( 'woocommerce_univapayment_settings', array() );
	}

	public function is_active() {
		$gateways = WC()->payment_gateways()->payment_gateways();
		return isset( $gateways['univapayment'] ) && 'yes' === $gateways['univapayment']->enabled;
	}

	public function get_payment_method_script_handles() {
		wp_register_script(
			'univapayment-blocks',
			plugins_url( 'assets/blocks.js', UNIVAPAYMENT_FILE ),
			array( 'wc-blocks-registry', 'wc-settings', 'wp-element', 'wp-html-entities' ),
			UNIVAPAYMENT_VERSION,
			true
		);
		return array( 'univapayment-blocks' );
	}

	public function get_payment_method_data() {
		return array(
			'title'       => $this->get_setting( 'title' ),
			'description' => $this->get_setting( 'description' ),
			'supports'    => array( 'products' ),
		);
	}
}
