<?php
/**
 * The Shop module (0.20.0): loaded only by TWD_SK_Shop_Gate, when WooCommerce is active and the
 * switch is on. Every file of the module is listed here (a test checks).
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

require_once __DIR__ . '/includes/class-twd-sk-shop-text.php';
require_once __DIR__ . '/includes/class-twd-sk-shop-product.php';
require_once __DIR__ . '/includes/class-twd-sk-shop-history.php';
require_once __DIR__ . '/includes/class-twd-sk-shop-rest.php';
require_once __DIR__ . '/includes/class-twd-sk-shop.php';

class TWD_SK_Shop_Loader {

	public static function init() {
		TWD_SK_Shop::init();
		TWD_SK_Shop_Rest::init();
	}
}
