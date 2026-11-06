<?php
defined( 'ABSPATH' ) || exit;

class UnivaPayment_Webhook {
	public static function register() {
		add_action(
			'rest_api_init',
			static function () {
				register_rest_route(
					'univapayment/v1',
					'/webhook',
					array(
						'methods'             => 'POST',
						// Anyone can call this; the signature in handle() is what is trusted.
						'permission_callback' => '__return_true',
						'callback'            => array( __CLASS__, 'handle' ),
					)
				);
			}
		);
	}

	public static function handle( WP_REST_Request $request ) {
		$gateways = WC()->payment_gateways()->payment_gateways();
		$gateway  = isset( $gateways['univapayment'] ) ? $gateways['univapayment'] : null;
		if ( ! $gateway ) {
			return new WP_REST_Response( array( 'error' => 'gateway_missing' ), 503 );
		}

		$body = $request->get_body();
		if ( ! UnivaPayment_Signature::is_valid( $request->get_header( 'x-signature' ), $body, $gateway->webhook_secret(), time() ) ) {
			return new WP_REST_Response( array( 'error' => 'bad_signature' ), 401 );
		}

		$event = json_decode( $body, true );
		$data  = is_array( $event ) && isset( $event['data'] ) && is_array( $event['data'] ) ? $event['data'] : array();
		$id    = isset( $data['id'] ) ? (string) $data['id'] : '';
		$wc_id = isset( $data['metadata']['wc_order_id'] ) ? (int) $data['metadata']['wc_order_id'] : 0;
		$order = $wc_id ? wc_get_order( $wc_id ) : false;

		// The merchant's other sites can share this webhook, so a stranger's payment is not an error.
		if ( ! $order || $order->get_meta( UnivaPayment_Gateway::META_PAYMENT ) !== $id ) {
			return new WP_REST_Response( array( 'ignored' => true ), 200 );
		}

		// The API has the final word on the status, and an order can only follow its own payment.
		$payment = $gateway->api()->get_payment( $id );
		if ( is_wp_error( $payment ) ) {
			return new WP_REST_Response( array( 'error' => 'try_again' ), 502 );
		}
		UnivaPayment_Gateway::apply_payment( $order, $payment );
		return new WP_REST_Response( array( 'ok' => true ), 200 );
	}
}
