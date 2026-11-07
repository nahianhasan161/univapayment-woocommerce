<?php
// Runs the gateway and webhook code against small stand-ins for WordPress and WooCommerce.
// It does not replace a test on a real store: the block checkout and WooCommerce itself are not here.
// With a sandbox key it also runs a whole payment against a real server:
//   UNIVA_SERVER=https://... UNIVA_TEST_KEY=upk_test_... UNIVA_DEVICE_TOKEN=... php tests/gateway.php
define( 'ABSPATH', __DIR__ . '/' );
define( 'UNIVAPAYMENT_DEFAULT_SERVER', getenv( 'UNIVA_SERVER' ) ?: 'https://server.invalid' );
define( 'UNIVAPAYMENT_VERSION', 'test' );

class WP_Error {
	private $code, $message;
	public function __construct( $code, $message = '' ) { $this->code = $code; $this->message = $message; }
	public function get_error_code() { return $this->code; }
	public function get_error_message() { return $this->message; }
}
function is_wp_error( $v ) { return $v instanceof WP_Error; }
function __( $t, $d = '' ) { return $t; }
function esc_html__( $t, $d = '' ) { return $t; }
function esc_html( $t ) { return $t; }
function rest_url( $p ) { return 'https://shop.test/wp-json/' . $p; }
function home_url() { return 'https://shop.test'; }
function add_action() {}
function wp_json_encode( $v ) { return json_encode( $v ); }
function esc_url( $v ) { return $v; }
$GLOBALS['scripts'] = array();
function wp_enqueue_script( $h, $src ) { $GLOBALS['scripts'][ $h ] = $src; }
function wp_add_inline_script( $h, $code ) { $GLOBALS['inline'][ $h ] = $code; }
function untrailingslashit( $v ) { return rtrim( $v, '/' ); }
function wc_format_decimal( $v, $d ) { return number_format( (float) $v, $d, '.', '' ); }
function get_woocommerce_currency() { return $GLOBALS['currency']; }
function wc_add_notice( $m, $t ) { $GLOBALS['notices'][] = $m; }
function wc_get_logger() { return new class { public function error( $m, $c ) {} }; }
function wc_get_order( $id ) { return $GLOBALS['orders'][ $id ] ?? false; }

$GLOBALS['fake_http'] = null;
function wp_remote_request( $url, $args ) {
	if ( $GLOBALS['fake_http'] ) {
		return ( $GLOBALS['fake_http'] )( $url, $args );
	}
	$headers = array();
	foreach ( $args['headers'] as $k => $v ) { $headers[] = "$k: $v"; }
	$context = stream_context_create( array( 'http' => array( 'method' => $args['method'], 'header' => $headers, 'content' => $args['body'] ?? '', 'ignore_errors' => true, 'timeout' => 20 ) ) );
	$body    = @file_get_contents( $url, false, $context );
	if ( false === $body ) { return new WP_Error( 'http_request_failed', 'no response' ); }
	preg_match( '#HTTP/\S+ (\d+)#', $http_response_header[0], $m );
	return array( 'code' => (int) $m[1], 'body' => $body );
}
function wp_remote_retrieve_response_code( $r ) { return $r['code']; }
function wp_remote_retrieve_body( $r ) { return $r['body']; }

#[AllowDynamicProperties]
class WC_Payment_Gateway {
	public $id, $settings = array(), $form_fields = array(), $enabled = 'yes';
	public function init_settings() { $this->settings = $GLOBALS['settings']; }
	public function get_option( $k ) { return $this->settings[ $k ] ?? ''; }
	public function is_available() { return 'yes' === $this->enabled; }
	public function admin_options() {}
}
class WC_Order {
	public $meta = array(), $status = 'pending', $notes = array(), $trx = null, $paid = false, $total, $id;
	public function __construct( $id, $total ) { $this->id = $id; $this->total = $total; }
	public function get_id() { return $this->id; }
	public function get_order_number() { return (string) $this->id; }
	public function get_total() { return $this->total; }
	public function get_meta( $k ) { return $this->meta[ $k ] ?? ''; }
	public function update_meta_data( $k, $v ) { $this->meta[ $k ] = $v; }
	public function set_transaction_id( $v ) {}
	public function save() {}
	public function add_order_note( $n ) { $this->notes[] = $n; }
	public function is_paid() { return $this->paid; }
	public function has_status( $s ) { return $this->status === $s; }
	public function needs_payment() { return ! $this->paid; }
	public function update_status( $s, $n = '' ) { $this->status = $s; $this->notes[] = $n; }
	public function payment_complete( $t ) { $this->paid = true; $this->trx = $t; $this->status = 'processing'; }
	public function get_checkout_order_received_url() { return 'https://shop.test/thanks'; }
	public function get_checkout_payment_url( $on_checkout = false ) { return 'https://shop.test/pay-order/' . $this->id; }
	public function get_payment_method() { return 'univapayment'; }
}
class WP_REST_Response {
	public $data, $status;
	public function __construct( $d, $s ) { $this->data = $d; $this->status = $s; }
}
class WP_REST_Request {
	public $body, $sig;
	public function __construct( $b, $s ) { $this->body = $b; $this->sig = $s; }
	public function get_body() { return $this->body; }
	public function get_header( $h ) { return $this->sig; }
}
function WC() {
	return new class {
		public function payment_gateways() {
			return new class { public function payment_gateways() { return array( 'univapayment' => $GLOBALS['gateway'] ); } };
		}
	};
}

require __DIR__ . '/../includes/class-signature.php';
require __DIR__ . '/../includes/class-api.php';
require __DIR__ . '/../includes/class-gateway.php';
require __DIR__ . '/../includes/class-webhook.php';

$failed = 0;
function check( $name, $ok ) {
	global $failed;
	echo ( $ok ? 'PASS  ' : 'FAIL  ' ) . $name . PHP_EOL;
	if ( ! $ok ) { $failed++; }
}
$pay = function ( $status, $extra = array() ) {
	return array_merge( array( 'id' => 'pay_1', 'status' => $status, 'mode' => 'live', 'amount' => '150.00', 'provider' => 'nagad', 'trx_id' => 'TRX1', 'checkout_url' => 'https://u.test/pay/x' ), $extra );
};
$order_for = function ( $id = 1, $total = '150' ) {
	$o = new WC_Order( $id, $total );
	$o->meta[ UnivaPayment_Gateway::META_PAYMENT ] = 'pay_1';
	$GLOBALS['orders'][ $id ] = $o;
	return $o;
};

$GLOBALS['currency'] = 'BDT';
$GLOBALS['settings'] = array( 'title' => 'x', 'description' => 'x', 'api_key' => 'upk_live_x', 'webhook_secret' => 'whsec_x', 'server' => '' );
$gateway             = new UnivaPayment_Gateway();
$GLOBALS['gateway']  = $gateway;

// Availability.
check( 'available with a key in taka', $gateway->is_available() );
$GLOBALS['currency'] = 'USD';
check( 'not available in another currency', ! $gateway->is_available() );
$GLOBALS['currency'] = 'BDT';

// Order status follows the payment.
$o = $order_for();
UnivaPayment_Gateway::apply_payment( $o, $pay( 'review' ) );
check( 'review puts the order on hold', 'on-hold' === $o->status );
UnivaPayment_Gateway::apply_payment( $o, $pay( 'completed' ) );
check( 'approval pays the held order', $o->paid && 'TRX1' === $o->trx );
$notes = count( $o->notes );
UnivaPayment_Gateway::apply_payment( $o, $pay( 'completed' ) );
check( 'a repeated completion changes nothing', count( $o->notes ) === $notes );
UnivaPayment_Gateway::apply_payment( $o, $pay( 'refunded' ) );
check( 'a refund reaches the order', 'refunded' === $o->status );

$o = $order_for();
check( 'a payment for another order is refused', false === UnivaPayment_Gateway::apply_payment( $o, $pay( 'completed', array( 'id' => 'pay_other' ) ) ) && ! $o->paid );
$o = $order_for( 1, '999' );
UnivaPayment_Gateway::apply_payment( $o, $pay( 'completed' ) );
check( 'a different total holds the order instead of paying it', ! $o->paid && 'on-hold' === $o->status );
$o = $order_for();
UnivaPayment_Gateway::apply_payment( $o, $pay( 'expired' ) );
check( 'an expired payment fails the order', 'failed' === $o->status );
$o = $order_for();
UnivaPayment_Gateway::apply_payment( $o, $pay( 'rejected' ) );
check( 'a rejected payment cancels the order', 'cancelled' === $o->status );
$o = $order_for();
UnivaPayment_Gateway::apply_payment( $o, $pay( 'pending' ) );
check( 'a payment still being checked holds the order, so stock is not released', 'on-hold' === $o->status );

// Starting a payment.
$calls = array();
$GLOBALS['fake_http'] = function ( $url, $args ) use ( &$calls, $pay ) {
	$calls[] = array( $url, $args );
	return array( 'code' => 201, 'body' => json_encode( $pay( 'initiated', array( 'id' => 'pay_new' ) ) ) );
};
$o = new WC_Order( 7, '150' );
$GLOBALS['orders'][7] = $o;
$r = $gateway->process_payment( 7 );
$sent = json_decode( $calls[0][1]['body'], true );
check( 'sends the amount with two decimals', '150.00' === $sent['amount'] );
check( 'sends the order id for the webhook', 7 === $sent['metadata']['wc_order_id'] );
check( 'redirects to the hosted checkout', 'https://u.test/pay/x' === $r['redirect'] && 'pay_new' === $o->get_meta( UnivaPayment_Gateway::META_PAYMENT ) );

$GLOBALS['fake_http'] = function () use ( $pay ) {
	return array( 'code' => 200, 'body' => json_encode( $pay( 'expired', array( 'id' => 'pay_new' ) ) ) );
};
$first = $calls[0][1]['headers']['Idempotency-Key'];
$calls = array();
$GLOBALS['fake_http'] = function ( $url, $args ) use ( &$calls, $pay ) {
	$calls[] = array( $url, $args );
	return 'GET' === $args['method']
		? array( 'code' => 200, 'body' => json_encode( $pay( 'expired', array( 'id' => 'pay_new' ) ) ) )
		: array( 'code' => 201, 'body' => json_encode( $pay( 'initiated', array( 'id' => 'pay_two' ) ) ) );
};
$gateway->process_payment( 7 );
check( 'paying again after expiry starts a new payment', 'pay_two' === $o->get_meta( UnivaPayment_Gateway::META_PAYMENT ) && $calls[1][1]['headers']['Idempotency-Key'] !== $first );

// Popup mode: the order's own payment page opens the popup.
$GLOBALS['settings']['checkout_mode'] = 'popup';
$popup_gateway = new UnivaPayment_Gateway();
$GLOBALS['fake_http'] = function () use ( $pay ) {
	return array( 'code' => 201, 'body' => json_encode( $pay( 'initiated', array( 'id' => 'pay_pop' ) ) ) );
};
$o = new WC_Order( 9, '150' );
$GLOBALS['orders'][9] = $o;
$r = $popup_gateway->process_payment( 9 );
check( 'popup mode stays on the shop', 'https://shop.test/pay-order/9' === $r['redirect'] );
ob_start();
$popup_gateway->receipt_page( 9 );
$html = ob_get_clean();
check( 'the payment page loads the popup script from the server', isset( $GLOBALS['scripts']['univapayment-checkout'] ) && str_ends_with( $GLOBALS['scripts']['univapayment-checkout'], '/js/checkout.js' ) );
check( 'the popup opens the payment, with a button as a fallback', str_contains( $GLOBALS['inline']['univapayment-checkout'], '"https:\\/\\/u.test\\/pay\\/x"' ) && str_contains( $html, 'data-univapayment href="https://u.test/pay/x"' ) );
$o->paid = true;
ob_start();
$popup_gateway->receipt_page( 9 );
check( 'a paid order shows no popup', '' === ob_get_clean() );
$GLOBALS['settings']['checkout_mode'] = '';

$calls = array();
$GLOBALS['fake_http'] = function ( $url, $args ) use ( &$calls, $pay ) {
	$calls[] = $args['method'];
	return array( 'code' => 200, 'body' => json_encode( $pay( 'pending', array( 'id' => 'pay_two' ) ) ) );
};
$r = $gateway->process_payment( 7 );
check( 'an open payment is reused', array( 'GET' ) === $calls && 'success' === $r['result'] );

$GLOBALS['notices']   = array();
$GLOBALS['fake_http'] = function () {
	return array( 'code' => 403, 'body' => json_encode( array( 'error' => array( 'code' => 'not_live', 'message' => 'x' ) ) ) );
};
$o2 = new WC_Order( 8, '150' );
$GLOBALS['orders'][8] = $o2;
$r = $gateway->process_payment( 8 );
check( 'an API refusal shows a notice and stays on the page', 'failure' === $r['result'] && 1 === count( $GLOBALS['notices'] ) );

// Webhook.
$hook = function ( $id, $wc_id, $secret = 'whsec_x', $t = null ) {
	$t    = $t ?? time();
	$body = json_encode( array( 'event' => 'payment.completed', 'data' => array( 'id' => $id, 'metadata' => array( 'wc_order_id' => $wc_id ) ) ) );
	return UnivaPayment_Webhook::handle( new WP_REST_Request( $body, 't=' . $t . ',v1=' . hash_hmac( 'sha256', $t . '.' . $body, $secret ) ) );
};
$GLOBALS['fake_http'] = function () use ( $pay ) {
	return array( 'code' => 200, 'body' => json_encode( $pay( 'completed' ) ) );
};
$o = $order_for();
check( 'a forged webhook is refused', 401 === $hook( 'pay_1', 1, 'wrong' )->status && ! $o->paid );
check( 'an old webhook is refused', 401 === $hook( 'pay_1', 1, 'whsec_x', time() - 600 )->status && ! $o->paid );
$r = $hook( 'pay_other', 1 );
check( 'a signed webhook for another payment changes nothing', 200 === $r->status && ! $o->paid );
check( 'a signed webhook for an unknown order changes nothing', 200 === $hook( 'pay_1', 999 )->status );
$GLOBALS['fake_http'] = function () { return new WP_Error( 'x', 'down' ); };
check( 'asks for a retry when the API cannot confirm', 502 === $hook( 'pay_1', 1 )->status && ! $o->paid );
$GLOBALS['fake_http'] = function () use ( $pay ) {
	return array( 'code' => 200, 'body' => json_encode( $pay( 'pending' ) ) );
};
$hook( 'pay_1', 1 );
check( 'the API has the last word over the webhook', ! $o->paid );
$GLOBALS['fake_http'] = function () use ( $pay ) {
	return array( 'code' => 200, 'body' => json_encode( $pay( 'completed' ) ) );
};
check( 'a good webhook pays the order', 200 === $hook( 'pay_1', 1 )->status && $o->paid );

// A whole sandbox payment on a real server.
$key = getenv( 'UNIVA_TEST_KEY' );
if ( $key ) {
	$GLOBALS['fake_http'] = null;
	$GLOBALS['settings']['api_key'] = $key;
	$gateway = new UnivaPayment_Gateway();
	$GLOBALS['gateway'] = $gateway;
	$o = new WC_Order( 100 + random_int( 0, 100000 ), '150.5' );
	$GLOBALS['orders'][ $o->id ] = $o;
	$r = $gateway->process_payment( $o->id );
	check( 'real server: payment starts', 'success' === $r['result'] && false !== strpos( $r['redirect'], '/pay/' ) );
	$server = getenv( 'UNIVA_SERVER' );
	$token  = substr( $r['redirect'], strrpos( $r['redirect'], '/' ) + 1 );
	$post   = function ( $path, $body, $bearer = null ) use ( $server ) {
		return json_decode( wp_remote_request( $server . $path, array( 'method' => 'POST', 'headers' => array_filter( array( 'Content-Type' => 'application/json', 'Authorization' => $bearer ? "Bearer $bearer" : null ) ), 'body' => json_encode( $body ) ) )['body'], true );
	};
	$view = $post( "/api/v1/checkout/$token/payer", array( 'provider' => 'nagad', 'number' => '01911111111' ) );
	check( 'real server: Nagad is offered', '01800000000' === $view['walletNumber'] );
	$sent = $post( '/api/v1/device/sms', array( 'messages' => array( array( 'device_sms_id' => bin2hex( random_bytes( 8 ) ), 'sender' => '01911111111', 'body' => $view['testMessage'], 'sms_time' => gmdate( 'Y-m-d\TH:i:s\Z' ) ) ) ), getenv( 'UNIVA_DEVICE_TOKEN' ) );
	check( 'real server: test SMS completes it', 'completed' === $sent['results'][0]['result'] );
	$gateway->on_thankyou( $o->id );
	check( 'real server: the thank-you page pays the order', $o->paid && 0 === strpos( (string) $o->trx, 'TEST-' ) );
}

exit( $failed ? 1 : 0 );
