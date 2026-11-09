<?php
/**
 * TWD_SK_Shop_Uk: "UK delivery only" (0.21.0), a tick per product in the Edit product window's
 * Basics tab, for pieces that may not be sent abroad (for example under export rules for some
 * materials). Stored as product meta _twd_sk_shop_uk_only = 'yes' (TWD_SK_Shop_Product::UK_META).
 *
 * When ticked:
 * - the product page says "UK delivery only" under the price (with or without the layout tick);
 * - the classic cart and checkout pages carry a note naming the piece;
 * - the checkout refuses a delivery country other than the United Kingdom (GB), through
 *   WooCommerce's own checks: woocommerce_after_checkout_validation for the classic checkout and
 *   woocommerce_store_api_cart_errors for the block checkout.
 *
 * Loaded only with the module, and each check asks the gate again, so with the switch off
 * nothing here acts.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class TWD_SK_Shop_Uk {

	const COUNTRY = 'GB';
	const LINE    = 'UK delivery only';

	public static function init() {
		add_action( 'woocommerce_single_product_summary', array( __CLASS__, 'print_line' ), 11 );
		add_action( 'woocommerce_before_cart', array( __CLASS__, 'print_note' ) );
		add_action( 'woocommerce_before_checkout_form', array( __CLASS__, 'print_note' ), 5 );
		add_action( 'woocommerce_after_checkout_validation', array( __CLASS__, 'classic_check' ), 10, 2 );
		add_action( 'woocommerce_store_api_cart_errors', array( __CLASS__, 'store_check' ), 10, 2 );
	}

	/** The names of the UK-only pieces in a cart (WooCommerce's cart object), in basket order. */
	public static function pieces( $cart = null ) {
		if ( null === $cart && function_exists( 'WC' ) && is_object( WC() ) && isset( WC()->cart ) ) {
			$cart = WC()->cart;
		}
		if ( ! is_object( $cart ) || ! method_exists( $cart, 'get_cart' ) ) {
			return array();
		}
		$names = array();
		foreach ( (array) $cart->get_cart() as $item ) {
			$pid = isset( $item['product_id'] ) ? (int) $item['product_id'] : 0;
			if ( $pid <= 0 || isset( $names[ $pid ] ) || ! TWD_SK_Shop_Product::uk_only( $pid ) ) {
				continue;
			}
			$product       = isset( $item['data'] ) && is_object( $item['data'] ) ? $item['data'] : TWD_SK_Shop_Product::get( $pid );
			$name          = $product ? html_entity_decode( wp_strip_all_tags( (string) $product->get_name() ), ENT_QUOTES, 'UTF-8' ) : '';
			$names[ $pid ] = '' !== trim( $name ) ? trim( $name ) : 'One piece in your basket';
		}
		return array_values( $names );
	}

	/** "A", "A" and "B", "A", "B" and "C": each name in quotes, escaped for HTML when asked. */
	private static function list_names( $names, $html ) {
		$q = array();
		foreach ( $names as $n ) {
			$q[] = '"' . ( $html ? esc_html( $n ) : $n ) . '"';
		}
		$last = array_pop( $q );
		return $q ? implode( ', ', $q ) . ' and ' . $last : $last;
	}

	/**
	 * The refusal for these pieces and this delivery country, or '' when the order may go ahead
	 * (no UK-only piece, a UK address, no country given yet, or the module switched off).
	 */
	public static function refusal( $names, $country, $html = true ) {
		if ( ! TWD_SK_Shop_Gate::on() || ! $names ) {
			return '';
		}
		$country = strtoupper( trim( (string) $country ) );
		if ( '' === $country || self::COUNTRY === $country ) {
			return '';
		}
		$one = 1 === count( $names );
		return 'Sorry, ' . self::list_names( $names, $html ) . ( $one ? ' can' : ' can each' ) . ' only be delivered to an address in the United Kingdom. Please choose a UK delivery address, or remove '
			. ( $one ? 'it' : 'them' ) . ' from your basket.';
	}

	/** The note on the classic cart and checkout pages, or ''. */
	public static function note( $names ) {
		if ( ! TWD_SK_Shop_Gate::on() || ! $names ) {
			return '';
		}
		return 'Please note: ' . self::list_names( $names, true ) . ( 1 === count( $names ) ? ' can' : ' can each' ) . ' only be delivered to an address in the United Kingdom.';
	}

	// -- Hooks ----------------------------------------------------------------------------

	public static function print_line() {
		global $product;
		if ( ! TWD_SK_Shop_Gate::on() || ! is_object( $product ) || ! TWD_SK_Shop_Product::uk_only( $product->get_id() ) ) {
			return;
		}
		if ( 'outofstock' === $product->get_stock_status() ) {
			return; // Sold: nothing to deliver.
		}
		echo '<p class="twd-sk-shop-uk">' . esc_html( self::LINE ) . '</p>';
	}

	public static function print_note() {
		$note = self::note( self::pieces() );
		if ( '' !== $note && function_exists( 'wc_print_notice' ) ) {
			wc_print_notice( $note, 'notice' );
		}
	}

	/** Classic checkout: $data is WooCommerce's posted data, $errors its WP_Error. */
	public static function classic_check( $data, $errors ) {
		if ( ! is_object( $errors ) || ! method_exists( $errors, 'add' ) ) {
			return;
		}
		$data    = is_array( $data ) ? $data : array();
		$country = ! empty( $data['shipping_country'] ) ? $data['shipping_country'] : ( isset( $data['billing_country'] ) ? $data['billing_country'] : '' );
		$words   = self::refusal( self::pieces(), $country, true );
		if ( '' !== $words ) {
			$errors->add( 'twd_sk_shop_uk_only', $words );
		}
	}

	/** Block checkout (WooCommerce's Store API): the customer's delivery country is already set from the form. */
	public static function store_check( $errors, $cart = null ) {
		if ( ! is_object( $errors ) || ! method_exists( $errors, 'add' ) ) {
			return;
		}
		$country = '';
		if ( function_exists( 'WC' ) && is_object( WC() ) && isset( WC()->customer ) && is_object( WC()->customer ) ) {
			$country = (string) WC()->customer->get_shipping_country();
			if ( '' === $country ) {
				$country = (string) WC()->customer->get_billing_country();
			}
		}
		$words = self::refusal( self::pieces( $cart ), $country, false );
		if ( '' !== $words ) {
			$errors->add( 'twd_sk_shop_uk_only', $words );
		}
	}
}
