<?php
/**
 * Checkout rules for cash on delivery.
 *
 * @package CODWarden
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Hides or refuses cash on delivery when an order looks fake.
 */
class CODWarden_Guard {

	const GATEWAY = 'cod';

	/**
	 * Hooks.
	 *
	 * @return void
	 */
	public static function init() {
		add_filter( 'woocommerce_available_payment_gateways', array( __CLASS__, 'filter_gateways' ), 50 );
		add_action( 'woocommerce_after_checkout_validation', array( __CLASS__, 'validate_classic' ), 20, 2 );
		add_action( 'woocommerce_store_api_checkout_update_order_from_request', array( __CLASS__, 'validate_store_api' ), 20, 2 );
		add_action( 'woocommerce_checkout_order_processed', array( __CLASS__, 'record_classic' ), 20, 3 );
		add_action( 'woocommerce_store_api_checkout_order_processed', array( __CLASS__, 'record_order' ), 20 );
		add_action( 'woocommerce_order_status_refused', array( __CLASS__, 'on_refused' ), 10, 2 );
	}

	/**
	 * Generic message for a refused cash on delivery order.
	 *
	 * @return string
	 */
	public static function message() {
		$custom = (string) CODWarden_Settings::get( 'message' );
		return '' !== $custom ? $custom : __( 'Cash on delivery is not available for this order. Please choose another payment method or contact us.', 'codwarden' );
	}

	/**
	 * Messages per reason.
	 *
	 * @param string $reason Reason.
	 * @return string
	 */
	public static function message_for( $reason ) {
		if ( 'bad_phone' === $reason ) {
			return __( 'Please enter a valid mobile number so the courier can reach you.', 'codwarden' );
		}
		if ( 'duplicate' === $reason ) {
			return __( 'It looks like you placed this same order a few minutes ago. Please check your email, or contact us if you need another one.', 'codwarden' );
		}
		return self::message();
	}

	/**
	 * Logged-in customer with a paid or completed order, or a shop manager.
	 *
	 * @return bool
	 */
	private static function is_trusted() {
		if ( current_user_can( 'manage_woocommerce' ) ) {
			return true;
		}
		if ( ! CODWarden_Settings::on( 'trust_customers' ) || ! is_user_logged_in() ) {
			return false;
		}
		return self::has_completed_order( get_current_user_id() );
	}

	/**
	 * Whether a customer has a completed order.
	 *
	 * @param int $user_id User ID.
	 * @return bool
	 */
	private static function has_completed_order( $user_id ) {
		if ( ! $user_id ) {
			return false;
		}
		$ids = wc_get_orders(
			array(
				'customer_id' => (int) $user_id,
				'status'      => array( 'wc-completed' ),
				'limit'       => 1,
				'return'      => 'ids',
			)
		);
		return ! empty( $ids );
	}

	/**
	 * Fingerprint of products and quantities.
	 *
	 * @param array $lines List of "product:variation:qty".
	 * @return string
	 */
	private static function cart_hash( array $lines ) {
		sort( $lines );
		return $lines ? md5( implode( '|', $lines ) ) : '';
	}

	/**
	 * Fingerprint of the current cart.
	 *
	 * @return string
	 */
	private static function current_cart_hash() {
		if ( ! function_exists( 'WC' ) || ! WC()->cart ) {
			return '';
		}
		$lines = array();
		foreach ( WC()->cart->get_cart() as $item ) {
			$lines[] = (int) $item['product_id'] . ':' . (int) $item['variation_id'] . ':' . (int) $item['quantity'];
		}
		return self::cart_hash( $lines );
	}

	/**
	 * Fingerprint of an order's items.
	 *
	 * @param WC_Order $order Order.
	 * @return string
	 */
	private static function order_cart_hash( $order ) {
		$lines = array();
		foreach ( $order->get_items() as $item ) {
			if ( $item instanceof WC_Order_Item_Product ) {
				$lines[] = (int) $item->get_product_id() . ':' . (int) $item->get_variation_id() . ':' . (int) $item->get_quantity();
			}
		}
		return self::cart_hash( $lines );
	}

	/**
	 * Cart total used for the amount rules.
	 *
	 * @return float
	 */
	private static function cart_total() {
		if ( ! function_exists( 'WC' ) || ! WC()->cart ) {
			return 0.0;
		}
		return (float) WC()->cart->get_total( 'edit' );
	}

	/**
	 * Reason an amount rule removes cash on delivery, or empty.
	 *
	 * @param float $total Order total.
	 * @return string
	 */
	private static function amount_reason( $total ) {
		$max = (float) CODWarden_Settings::get( 'max_total' );
		if ( $max > 0 && $total > $max ) {
			return 'over_total';
		}
		$new_max = (float) CODWarden_Settings::get( 'new_max_total' );
		if ( $new_max > 0 && $total > $new_max && ! self::has_completed_order( get_current_user_id() ) ) {
			return 'new_over_total';
		}
		return '';
	}

	/**
	 * Hide cash on delivery when a rule already says no.
	 *
	 * @param array $gateways Available gateways.
	 * @return array
	 */
	public static function filter_gateways( $gateways ) {
		if ( ! is_array( $gateways ) || ! isset( $gateways[ self::GATEWAY ] ) || ! CODWarden_Settings::on( 'enabled' ) ) {
			return $gateways;
		}
		if ( is_admin() && ! wp_doing_ajax() ) {
			return $gateways;
		}
		if ( self::is_trusted() ) {
			return $gateways;
		}
		if ( '' !== self::amount_reason( self::cart_total() ) ) {
			unset( $gateways[ self::GATEWAY ] );
			return $gateways;
		}
		if ( CODWarden_Settings::on( 'strikes_enabled' ) && function_exists( 'WC' ) && WC()->customer ) {
			$phone = CODWarden_Phone::normalize( WC()->customer->get_billing_phone(), WC()->customer->get_billing_country() );
			if ( '' !== $phone && CODWarden_Store::is_blocked( CODWarden_Phone::hash( $phone ) ) ) {
				unset( $gateways[ self::GATEWAY ] );
			}
		}
		return $gateways;
	}

	/**
	 * Run every rule. Returns a reason code, or empty when the order may go ahead.
	 *
	 * @param string $phone_raw Billing phone.
	 * @param string $country   Billing country.
	 * @param float  $total     Order total.
	 * @param string $cart_hash Cart fingerprint.
	 * @param array  $phone_out Filled with hash and masked label.
	 * @return string
	 */
	public static function check( $phone_raw, $country, $total, $cart_hash, &$phone_out ) {
		$phone_out = array(
			'hash'   => '',
			'masked' => '',
		);
		if ( ! CODWarden_Settings::on( 'enabled' ) || self::is_trusted() ) {
			return '';
		}
		$country = '' !== (string) $country ? $country : WC()->countries->get_base_country();
		$phone   = CODWarden_Phone::normalize( $phone_raw, $country );
		if ( '' !== $phone ) {
			$phone_out = array(
				'hash'   => CODWarden_Phone::hash( $phone ),
				'masked' => CODWarden_Phone::mask( $phone ),
			);
		}

		if ( CODWarden_Settings::on( 'phone_check' ) && '' === $phone ) {
			return 'bad_phone';
		}
		if ( CODWarden_Settings::on( 'strikes_enabled' ) && '' !== $phone_out['hash'] && CODWarden_Store::is_blocked( $phone_out['hash'] ) ) {
			return 'blocked_phone';
		}
		$amount = self::amount_reason( $total );
		if ( '' !== $amount ) {
			return $amount;
		}
		if ( CODWarden_Settings::on( 'duplicate_guard' ) && CODWarden_Store::is_duplicate( $phone_out['hash'], $cart_hash, (int) CODWarden_Settings::get( 'duplicate_mins' ) ) ) {
			return 'duplicate';
		}
		if ( CODWarden_Settings::on( 'limit_enabled' ) ) {
			$limit = (int) CODWarden_Settings::get( 'limit_orders' );
			$hours = (int) CODWarden_Settings::get( 'limit_hours' );
			if ( CODWarden_Store::count_placed( 'phone_hash', $phone_out['hash'], $hours ) >= $limit ) {
				return 'limit_phone';
			}
			if ( CODWarden_Store::count_placed( 'ip_hash', CODWarden_Store::ip_hash( CODWarden_Store::ip() ), $hours ) >= $limit ) {
				return 'limit_ip';
			}
		}
		return '';
	}

	/**
	 * Classic checkout.
	 *
	 * @param array    $data   Posted data.
	 * @param WP_Error $errors Errors.
	 * @return void
	 */
	public static function validate_classic( $data, $errors ) {
		if ( ! $errors instanceof WP_Error || $errors->has_errors() ) {
			return;
		}
		if ( ! isset( $data['payment_method'] ) || self::GATEWAY !== $data['payment_method'] ) {
			return;
		}
		$phone  = array();
		$reason = self::check(
			isset( $data['billing_phone'] ) ? $data['billing_phone'] : '',
			isset( $data['billing_country'] ) ? $data['billing_country'] : '',
			self::cart_total(),
			self::current_cart_hash(),
			$phone
		);
		if ( '' !== $reason ) {
			CODWarden_Store::log( 'stopped', $reason, 'classic', $phone['hash'], $phone['masked'] );
			$errors->add( 'codwarden_' . $reason, self::message_for( $reason ) );
		}
	}

	/**
	 * Block checkout (Store API).
	 *
	 * @param WC_Order        $order   Order being built.
	 * @param WP_REST_Request $request Request.
	 * @return void
	 * @throws \Automattic\WooCommerce\StoreApi\Exceptions\RouteException When the order is refused.
	 */
	public static function validate_store_api( $order, $request = null ) {
		if ( ! $order instanceof WC_Order ) {
			return;
		}
		$method = ( $request instanceof WP_REST_Request ) ? (string) $request->get_param( 'payment_method' ) : $order->get_payment_method();
		if ( self::GATEWAY !== $method ) {
			return;
		}
		$phone  = array();
		$reason = self::check( $order->get_billing_phone(), $order->get_billing_country(), (float) $order->get_total( 'edit' ), self::order_cart_hash( $order ), $phone );
		if ( '' !== $reason ) {
			CODWarden_Store::log( 'stopped', $reason, 'store_api', $phone['hash'], $phone['masked'] );
			throw new \Automattic\WooCommerce\StoreApi\Exceptions\RouteException( 'codwarden_' . esc_attr( $reason ), esc_html( self::message_for( $reason ) ), 400 );
		}
	}

	/**
	 * Classic checkout finished.
	 *
	 * @param int      $order_id Order ID.
	 * @param array    $posted   Posted data.
	 * @param WC_Order $order    Order.
	 * @return void
	 */
	public static function record_classic( $order_id, $posted = array(), $order = null ) {
		self::record_order( $order instanceof WC_Order ? $order : wc_get_order( $order_id ) );
	}

	/**
	 * Remember a placed cash on delivery order for limits and duplicates.
	 *
	 * @param WC_Order $order Order.
	 * @return void
	 */
	public static function record_order( $order ) {
		if ( ! $order instanceof WC_Order || self::GATEWAY !== $order->get_payment_method() ) {
			return;
		}
		$country = $order->get_billing_country() ? $order->get_billing_country() : WC()->countries->get_base_country();
		$phone   = CODWarden_Phone::normalize( $order->get_billing_phone(), $country );
		CODWarden_Store::log(
			'placed',
			'',
			did_action( 'woocommerce_store_api_checkout_order_processed' ) ? 'store_api' : 'classic',
			'' !== $phone ? CODWarden_Phone::hash( $phone ) : '',
			'' !== $phone ? CODWarden_Phone::mask( $phone ) : '',
			self::order_cart_hash( $order )
		);
	}

	/**
	 * Order marked Refused: one strike for that phone.
	 *
	 * @param int      $order_id Order ID.
	 * @param WC_Order $order    Order.
	 * @return void
	 */
	public static function on_refused( $order_id, $order = null ) {
		if ( ! CODWarden_Settings::on( 'strikes_enabled' ) ) {
			return;
		}
		$order = $order instanceof WC_Order ? $order : wc_get_order( $order_id );
		if ( ! $order ) {
			return;
		}
		$country = $order->get_billing_country() ? $order->get_billing_country() : WC()->countries->get_base_country();
		$phone   = CODWarden_Phone::normalize( $order->get_billing_phone(), $country );
		if ( '' === $phone ) {
			return;
		}
		CODWarden_Store::strike( CODWarden_Phone::hash( $phone ), CODWarden_Phone::mask( $phone ) );
		$order->add_order_note( __( 'CODWarden: one refusal strike added to this phone number.', 'codwarden' ) );
	}
}
