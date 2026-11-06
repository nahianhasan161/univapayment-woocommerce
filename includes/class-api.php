<?php
defined( 'ABSPATH' ) || exit;

/** Talks to the UnivaPayment API. Errors come back as WP_Error, with the API's code. */
class UnivaPayment_Api {
	private $server;
	private $key;

	public function __construct( $server, $key ) {
		$this->server = untrailingslashit( $server );
		$this->key    = $key;
	}

	public function create_payment( array $body, $idempotency_key ) {
		return $this->request( 'POST', '/api/v1/payments', $body, array( 'Idempotency-Key' => $idempotency_key ) );
	}

	public function get_payment( $id ) {
		return $this->request( 'GET', '/api/v1/payments/' . rawurlencode( $id ) );
	}

	private function request( $method, $path, $body = null, array $headers = array() ) {
		$args = array(
			'method'  => $method,
			'timeout' => 20,
			'headers' => array_merge(
				array(
					'Authorization' => 'Bearer ' . $this->key,
					'Accept'        => 'application/json',
				),
				$headers
			),
		);
		if ( null !== $body ) {
			$args['headers']['Content-Type'] = 'application/json';
			$args['body']                    = wp_json_encode( $body );
		}

		$response = wp_remote_request( $this->server . $path, $args );
		if ( is_wp_error( $response ) ) {
			return new WP_Error( 'network', $response->get_error_message() );
		}
		$status = (int) wp_remote_retrieve_response_code( $response );
		$data   = json_decode( wp_remote_retrieve_body( $response ), true );
		if ( $status < 200 || $status >= 300 || ! is_array( $data ) ) {
			$code = isset( $data['error']['code'] ) ? (string) $data['error']['code'] : 'http_' . $status;
			return new WP_Error( $code, isset( $data['error']['message'] ) ? (string) $data['error']['message'] : 'HTTP ' . $status );
		}
		return $data;
	}
}
