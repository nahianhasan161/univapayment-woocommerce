<?php
defined( 'ABSPATH' ) || exit;

class UnivaPayment_Gateway extends WC_Payment_Gateway {
	const META_PAYMENT = '_univapayment_id';
	const META_URL     = '_univapayment_checkout_url';
	const META_ATTEMPT = '_univapayment_attempt';

	public function __construct() {
		$this->id                 = 'univapayment';
		$this->method_title       = 'UnivaPayment';
		$this->method_description = __( 'bKash and Nagad payments to your own number, checked from the SMS on your phone.', 'univapayment-woocommerce' );
		$this->has_fields         = false;
		$this->supports           = array( 'products' );

		$this->init_form_fields();
		$this->init_settings();

		$this->title       = $this->get_option( 'title' );
		$this->description = $this->get_option( 'description' );

		add_action( 'woocommerce_update_options_payment_gateways_' . $this->id, array( $this, 'process_admin_options' ) );
		add_action( 'woocommerce_before_thankyou', array( $this, 'on_thankyou' ) );
	}

	public function init_form_fields() {
		$this->form_fields = array(
			'enabled'        => array(
				'title'   => __( 'Enable', 'univapayment-woocommerce' ),
				'type'    => 'checkbox',
				'label'   => __( 'Accept payments with UnivaPayment', 'univapayment-woocommerce' ),
				'default' => 'no',
			),
			'title'          => array(
				'title'   => __( 'Title at checkout', 'univapayment-woocommerce' ),
				'type'    => 'text',
				'default' => 'bKash / Nagad',
			),
			'description'    => array(
				'title'   => __( 'Description at checkout', 'univapayment-woocommerce' ),
				'type'    => 'textarea',
				'default' => 'অর্ডার দেওয়ার পর পেমেন্ট পেজে নিয়ে যাওয়া হবে। সেখানে bKash বা Nagad-এ টাকা পাঠালে অর্ডার নিশ্চিত হবে।',
			),
			'api_key'        => array(
				'title'       => __( 'API key', 'univapayment-woocommerce' ),
				'type'        => 'password',
				'description' => __( 'From Dashboard → Developers. A key starting upk_test_ uses the sandbox, and orders complete without real money.', 'univapayment-woocommerce' ),
			),
			'webhook_secret' => array(
				'title'       => __( 'Webhook secret', 'univapayment-woocommerce' ),
				'type'        => 'password',
				/* translators: %s: webhook address of this site */
				'description' => sprintf( __( 'In Dashboard → Developers, set the webhook URL to %s and paste the secret it gives you here.', 'univapayment-woocommerce' ), '<code>' . esc_html( rest_url( 'univapayment/v1/webhook' ) ) . '</code>' ),
			),
			'server'         => array(
				'title'       => __( 'Server address', 'univapayment-woocommerce' ),
				'type'        => 'text',
				'default'     => UNIVAPAYMENT_DEFAULT_SERVER,
				'description' => __( 'Leave this as it is unless UnivaPayment tells you to change it.', 'univapayment-woocommerce' ),
			),
		);
	}

	public function admin_options() {
		if ( 'BDT' !== get_woocommerce_currency() ) {
			echo '<div class="notice notice-error inline"><p>' . esc_html__( 'UnivaPayment only works when the store currency is Bangladeshi Taka (BDT).', 'univapayment-woocommerce' ) . '</p></div>';
		}
		if ( 0 === strpos( (string) $this->get_option( 'api_key' ), 'upk_test_' ) ) {
			echo '<div class="notice notice-warning inline"><p>' . esc_html__( 'Sandbox key: orders complete from test SMS, with no real money. Use a live key before selling.', 'univapayment-woocommerce' ) . '</p></div>';
		}
		parent::admin_options();
	}

	public function is_available() {
		return parent::is_available() && 'BDT' === get_woocommerce_currency() && '' !== (string) $this->get_option( 'api_key' );
	}

	public function api() {
		$server = (string) $this->get_option( 'server' );
		return new UnivaPayment_Api( '' !== $server ? $server : UNIVAPAYMENT_DEFAULT_SERVER, (string) $this->get_option( 'api_key' ) );
	}

	public function webhook_secret() {
		return (string) $this->get_option( 'webhook_secret' );
	}

	public function process_payment( $order_id ) {
		$order  = wc_get_order( $order_id );
		$amount = wc_format_decimal( $order->get_total(), 2 );
		$api    = $this->api();

		// Coming back to pay again: keep using the payment that is still open.
		$existing = $order->get_meta( self::META_PAYMENT );
		if ( $existing ) {
			$open = $api->get_payment( $existing );
			if ( ! is_wp_error( $open ) && in_array( $open['status'], array( 'initiated', 'pending' ), true ) && $open['amount'] === $amount ) {
				return array(
					'result'   => 'success',
					'redirect' => $open['checkout_url'],
				);
			}
		}

		$attempt = (int) $order->get_meta( self::META_ATTEMPT ) + 1;
		$payment = $api->create_payment(
			array(
				'amount'     => $amount,
				'currency'   => 'BDT',
				'order_id'   => (string) $order->get_order_number(),
				'return_url' => $order->get_checkout_order_received_url(),
				'metadata'   => array(
					'wc_order_id' => (int) $order->get_id(),
					'source'      => 'woocommerce',
				),
			),
			sprintf( 'wc-%s-%d-%d', substr( md5( home_url() ), 0, 10 ), $order->get_id(), $attempt )
		);

		if ( is_wp_error( $payment ) ) {
			wc_get_logger()->error( $payment->get_error_code() . ': ' . $payment->get_error_message(), array( 'source' => 'univapayment' ) );
			$message = 'plan_limit_reached' === $payment->get_error_code() || 'not_live' === $payment->get_error_code()
				? __( 'This payment method is not available right now. Please choose another.', 'univapayment-woocommerce' )
				: __( 'The payment could not be started. Please try again.', 'univapayment-woocommerce' );
			wc_add_notice( $message, 'error' );
			return array( 'result' => 'failure' );
		}

		$order->update_meta_data( self::META_ATTEMPT, $attempt );
		$order->update_meta_data( self::META_PAYMENT, $payment['id'] );
		$order->update_meta_data( self::META_URL, $payment['checkout_url'] );
		$order->set_transaction_id( '' );
		$order->save();
		if ( 'test' === $payment['mode'] ) {
			$order->add_order_note( __( 'Sandbox payment: no real money will be received for this order.', 'univapayment-woocommerce' ) );
		}

		return array(
			'result'   => 'success',
			'redirect' => $payment['checkout_url'],
		);
	}

	/** Brings the order in line with the payment. Safe to repeat for the same status. */
	public static function apply_payment( WC_Order $order, array $payment ) {
		if ( $order->get_meta( self::META_PAYMENT ) !== $payment['id'] ) {
			return false;
		}
		$status = $payment['status'];

		if ( 'completed' === $status ) {
			if ( $order->is_paid() ) {
				return true;
			}
			if ( wc_format_decimal( $order->get_total(), 2 ) !== $payment['amount'] ) {
				/* translators: 1: amount paid, 2: order total */
				$order->update_status( 'on-hold', sprintf( __( 'UnivaPayment completed ৳%1$s, but the order total is ৳%2$s. Check this order by hand.', 'univapayment-woocommerce' ), $payment['amount'], wc_format_decimal( $order->get_total(), 2 ) ) );
				return true;
			}
			$provider = isset( $payment['provider'] ) ? (string) $payment['provider'] : '';
			$trx      = isset( $payment['trx_id'] ) ? (string) $payment['trx_id'] : '';
			$order->payment_complete( $trx );
			/* translators: 1: bKash or Nagad, 2: transaction ID */
			$order->add_order_note( sprintf( __( 'UnivaPayment confirmed the payment. Method: %1$s. TrxID: %2$s.', 'univapayment-woocommerce' ), $provider, '' !== $trx ? $trx : '-' ) );
		} elseif ( 'review' === $status ) {
			if ( ! $order->is_paid() && ! $order->has_status( 'on-hold' ) ) {
				$order->update_status( 'on-hold', __( 'UnivaPayment could not match this automatically. Approve or reject it in your UnivaPayment dashboard.', 'univapayment-woocommerce' ) );
			}
		} elseif ( 'rejected' === $status ) {
			if ( ! $order->has_status( 'cancelled' ) ) {
				$order->update_status( 'cancelled', __( 'The payment was rejected in UnivaPayment.', 'univapayment-woocommerce' ) );
			}
		} elseif ( 'expired' === $status ) {
			if ( $order->needs_payment() && ! $order->has_status( 'failed' ) ) {
				$order->update_status( 'failed', __( 'The UnivaPayment payment expired before it was paid.', 'univapayment-woocommerce' ) );
			}
		} elseif ( 'refunded' === $status ) {
			if ( ! $order->has_status( 'refunded' ) ) {
				$order->update_status( 'refunded', __( 'Marked as refunded in UnivaPayment.', 'univapayment-woocommerce' ) );
			}
		}
		return true;
	}

	/** The customer can arrive here before the webhook, or a webhook may never have been set up. */
	public function on_thankyou( $order_id ) {
		$order = wc_get_order( $order_id );
		if ( ! $order || $order->get_payment_method() !== $this->id || ! $order->get_meta( self::META_PAYMENT ) ) {
			return;
		}
		if ( ! $order->is_paid() ) {
			$payment = $this->api()->get_payment( $order->get_meta( self::META_PAYMENT ) );
			if ( ! is_wp_error( $payment ) ) {
				self::apply_payment( $order, $payment );
				$order = wc_get_order( $order_id );
			}
		}
		if ( $order->has_status( 'on-hold' ) ) {
			echo '<p class="woocommerce-info">' . esc_html__( 'আপনার পেমেন্ট যাচাই করতে একটু সময় লাগবে। দোকান যাচাই করলেই অর্ডার নিশ্চিত হবে।', 'univapayment-woocommerce' ) . '</p>';
		}
	}
}
