<?php
/**
 * The Shop module, 0.21.0: "UK delivery only" per product (the tick, versions and Undo, the line
 * under the price, the refusal at checkout) and the three-line limit on grid titles.
 * Uses the fake WooCommerce product and helpers in test-shop.php.
 */

class Twd_Fake_Cart {
	public $items = array();
	public function get_cart() {
		return $this->items;
	}
}
class Twd_Fake_Customer {
	public $ship = '';
	public $bill = '';
	public function get_shipping_country() {
		return $this->ship;
	}
	public function get_billing_country() {
		return $this->bill;
	}
}
if ( ! function_exists( 'WC' ) ) {
	function WC() { // phpcs:ignore
		return isset( $GLOBALS['twd_fake_wc'] ) ? $GLOBALS['twd_fake_wc'] : null;
	}
}
if ( ! function_exists( 'wc_print_notice' ) ) {
	function wc_print_notice( $message, $type = 'success' ) {
		echo '<div class="notice-' . $type . '">' . $message . '</div>'; // phpcs:ignore
	}
}

/** The module on, product 42 in the basket (ticked or not), the customer's countries. */
function twd_shop_uk_setup( $ticked = true, $ship = '', $bill = '' ) {
	$p = twd_shop_setup( array( 'name' => 'Victorian garnet brooch & box' ) );
	TWD_SK_Shop_Gate::set( true );
	if ( $ticked ) {
		update_post_meta( 42, TWD_SK_Shop_Product::UK_META, 'yes' );
	} else {
		delete_post_meta( 42, TWD_SK_Shop_Product::UK_META );
	}
	$cart                   = new Twd_Fake_Cart();
	$cart->items            = array( 'k1' => array( 'product_id' => 42, 'data' => $p ) );
	$customer               = new Twd_Fake_Customer();
	$customer->ship         = $ship;
	$customer->bill         = $bill;
	$GLOBALS['twd_fake_wc'] = (object) array( 'cart' => $cart, 'customer' => $customer );
	return $p;
}

twd_sk_test( 'shop uk: the tick is saved, kept in versions, undone, and absent from untouched products', function () {
	$p = twd_shop_setup();
	twd_sk_true( ! array_key_exists( 'uk_only', TWD_SK_Shop_Product::fields( $p ) ), 'no key when not ticked, so old fingerprints still match' );
	TWD_SK_Shop_Rest::save( 42, twd_shop_input( array( 'uk_only' => true ) ), twd_shop_base( $p ) );
	twd_sk_eq( 'yes', get_post_meta( 42, '_twd_sk_shop_uk_only', true ) );
	twd_sk_true( true === TWD_SK_Shop_Product::fields( $p )['uk_only'] );
	twd_sk_eq( 1, $p->saves );
	$v = TWD_SK_Shop_History::all( 42 );
	twd_sk_true( ! isset( $v[0]['fields']['uk_only'] ) && true === $v[1]['fields']['uk_only'], 'before and after kept' );
	$again = TWD_SK_Shop_Rest::save( 42, TWD_SK_Shop_Product::fields( $p ), twd_shop_base( $p ) );
	twd_sk_true( false === $again['saved'], 'saving it ticked again changes nothing' );
	TWD_SK_Shop_Rest::undo( 42 );
	twd_sk_eq( '', get_post_meta( 42, '_twd_sk_shop_uk_only', true ), 'Undo clears it' );
	TWD_SK_Shop_Rest::restore( 42, 2 );
	twd_sk_eq( 'yes', get_post_meta( 42, '_twd_sk_shop_uk_only', true ), 'Restore puts it back' );
	TWD_SK_Shop_Rest::save( 42, twd_shop_input( array( 'uk_only' => 'false' ) ), twd_shop_base( $p ) );
	twd_sk_eq( '', get_post_meta( 42, '_twd_sk_shop_uk_only', true ), 'unticked in the window' );
	$read = TWD_SK_Shop_Product::read( 42 );
	twd_sk_true( ! array_key_exists( 'uk_only', $read['fields'] ) );
} );

twd_sk_test( 'shop uk: the checkout refuses a delivery address outside the UK and names the piece', function () {
	twd_shop_uk_setup( true );
	$errors = new WP_Error();
	TWD_SK_Shop_Uk::classic_check( array( 'billing_country' => 'GB', 'shipping_country' => 'FR' ), $errors );
	twd_sk_eq( array( 'twd_sk_shop_uk_only' ), $errors->get_error_codes() );
	$words = $errors->get_error_message();
	twd_sk_has( '"Victorian garnet brooch &amp; box" can only be delivered to an address in the United Kingdom', $words );
	twd_sk_has( 'remove it from your basket', $words );
	$billing = new WP_Error();
	TWD_SK_Shop_Uk::classic_check( array( 'billing_country' => 'US', 'shipping_country' => '' ), $billing );
	twd_sk_true( $billing->has_errors(), 'no separate delivery address: the billing country counts' );
	twd_shop_uk_setup( true, 'IE' );
	$store = new WP_Error();
	TWD_SK_Shop_Uk::store_check( $store, WC()->cart );
	twd_sk_eq( array( 'twd_sk_shop_uk_only' ), $store->get_error_codes(), 'block checkout' );
	twd_sk_has( '"Victorian garnet brooch & box"', $store->get_error_message(), 'plain text for the block checkout' );
	twd_shop_uk_setup( true, '', 'DE' );
	$bill = new WP_Error();
	TWD_SK_Shop_Uk::store_check( $bill, WC()->cart );
	twd_sk_true( $bill->has_errors(), 'block checkout with only a billing country' );
} );

twd_sk_test( 'shop uk: a UK address, no country yet, or no ticked piece goes ahead', function () {
	twd_shop_uk_setup( true, 'GB' );
	$errors = new WP_Error();
	TWD_SK_Shop_Uk::classic_check( array( 'billing_country' => 'FR', 'shipping_country' => 'GB' ), $errors );
	TWD_SK_Shop_Uk::classic_check( array( 'billing_country' => 'gb' ), $errors );
	TWD_SK_Shop_Uk::store_check( $errors, WC()->cart );
	twd_shop_uk_setup( true, '' );
	TWD_SK_Shop_Uk::store_check( $errors, WC()->cart );
	twd_shop_uk_setup( false, 'FR' );
	TWD_SK_Shop_Uk::classic_check( array( 'shipping_country' => 'FR' ), $errors );
	TWD_SK_Shop_Uk::store_check( $errors, WC()->cart );
	twd_sk_true( ! $errors->has_errors() );
	twd_sk_eq( array(), TWD_SK_Shop_Uk::pieces() );
} );

twd_sk_test( 'shop uk: two pieces are both named; the cart note and the line under the price', function () {
	$p2 = new Twd_Fake_Product( 43, array( 'name' => 'Ring' ) );
	twd_shop_uk_setup( true, 'FR' );
	$GLOBALS['twd_fake_products'][43] = $p2;
	update_post_meta( 43, TWD_SK_Shop_Product::UK_META, 'yes' );
	WC()->cart->items['k2'] = array( 'product_id' => 43, 'data' => $p2 );
	WC()->cart->items['k3'] = array( 'product_id' => 43, 'data' => $p2 );
	twd_sk_eq( array( 'Victorian garnet brooch & box', 'Ring' ), TWD_SK_Shop_Uk::pieces(), 'each piece once' );
	twd_sk_has( '"Victorian garnet brooch &amp; box" and "Ring" can each only be delivered', TWD_SK_Shop_Uk::refusal( TWD_SK_Shop_Uk::pieces(), 'FR' ) );
	ob_start();
	TWD_SK_Shop_Uk::print_note();
	twd_sk_has( 'Please note:', ob_get_clean() );
	global $product;
	$product = $p2;
	ob_start();
	TWD_SK_Shop_Uk::print_line();
	twd_sk_eq( '<p class="twd-sk-shop-uk">UK delivery only</p>', ob_get_clean() );
	$p2->d['stock_status'] = 'outofstock';
	ob_start();
	TWD_SK_Shop_Uk::print_line();
	twd_sk_eq( '', ob_get_clean(), 'not on a sold piece' );
	$product = null;
	twd_sk_has( "array( __CLASS__, 'print_line' ), 11 )", file_get_contents( ABSPATH . 'modules/shop/includes/class-twd-sk-shop-uk.php' ), 'just under the price' );
} );

twd_sk_test( 'shop uk: with the module switched off nothing is refused, noted or printed', function () {
	$p = twd_shop_uk_setup( true, 'FR' );
	TWD_SK_Shop_Gate::set( false );
	$errors = new WP_Error();
	TWD_SK_Shop_Uk::classic_check( array( 'shipping_country' => 'FR' ), $errors );
	TWD_SK_Shop_Uk::store_check( $errors, WC()->cart );
	twd_sk_true( ! $errors->has_errors() );
	ob_start();
	TWD_SK_Shop_Uk::print_note();
	global $product;
	$product = $p;
	TWD_SK_Shop_Uk::print_line();
	$product = null;
	twd_sk_eq( '', ob_get_clean() );
	twd_sk_hasnt( 'Shop_Uk', file_get_contents( ABSPATH . 'includes/class-twd-sk-shop-gate.php' ), 'the hooks are added only by the module loader' );
	twd_sk_has( 'TWD_SK_Shop_Uk::init();', file_get_contents( ABSPATH . 'modules/shop/load.php' ) );
} );

twd_sk_test( 'shop uk: the window has the tick; grid titles stop at three lines', function () {
	$js = file_get_contents( ABSPATH . 'modules/shop/assets/twd-site-kit-shop-editor.js' );
	twd_sk_has( 'draft.uk_only = true', $js );
	twd_sk_has( 'delete draft.uk_only', $js );
	$css = file_get_contents( ABSPATH . 'modules/shop/assets/twd-site-kit-shop.css' );
	preg_match( '#ul\.products li\.product \.woocommerce-loop-product__title \{([^}]*)\}#', $css, $m );
	twd_sk_true( ! empty( $m[1] ) );
	twd_sk_has( '-webkit-line-clamp: 3', $m[1] );
	twd_sk_has( 'overflow: hidden', $m[1] );
} );
