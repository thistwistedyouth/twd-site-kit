<?php
/**
 * TWD_SK_Shop: loads the Edit product window for people who can edit products.
 *
 * On a single product page (a draft's preview too) it offers Edit product; on the shop page and a
 * product category page it offers Add a product. Never for visitors, never in wp-admin, the kit's
 * preview frame, Elementor's editor or the customizer. The product's data is fetched when the window
 * opens, never printed into the page.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class TWD_SK_Shop {

	const HANDLE = 'twd-site-kit-shop-editor';

	public static function init() {
		add_action( 'wp_enqueue_scripts', array( __CLASS__, 'enqueue' ), 30 );
	}

	private static function in_builder_or_preview() {
		if ( isset( $_GET['elementor-preview'] ) || isset( $_GET['elementor_library'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification
			return true;
		}
		if ( class_exists( 'TWD_SK_Preview' ) && TWD_SK_Preview::is_preview_request() ) {
			return true;
		}
		return function_exists( 'is_customize_preview' ) && is_customize_preview();
	}

	/** What the window needs to know about this request, or null when it must not load. */
	public static function context() {
		if ( is_admin() || ! is_user_logged_in() || ! current_user_can( 'edit_products' ) || self::in_builder_or_preview() ) {
			return null;
		}
		if ( function_exists( 'is_product' ) && is_product() ) {
			$id = (int) get_queried_object_id();
			if ( $id > 0 && current_user_can( 'edit_post', $id ) ) {
				return array( 'mode' => 'edit', 'product' => $id, 'category' => 0 );
			}
			return null;
		}
		$is_cat = function_exists( 'is_product_category' ) && is_product_category();
		if ( $is_cat || ( function_exists( 'is_shop' ) && is_shop() ) ) {
			return array( 'mode' => 'add', 'product' => 0, 'category' => $is_cat ? (int) get_queried_object_id() : 0 );
		}
		return null;
	}

	public static function enqueue() {
		$ctx = self::context();
		if ( null === $ctx ) {
			return;
		}
		$ver  = defined( 'TWD_SK_VERSION' ) ? TWD_SK_VERSION : false;
		$base = TWD_SK_URL . 'modules/shop/assets/';
		wp_enqueue_style( self::HANDLE, $base . 'twd-site-kit-shop-editor.css', array(), $ver );
		wp_register_script( self::HANDLE, $base . 'twd-site-kit-shop-editor.js', array(), $ver, true );
		wp_localize_script( self::HANDLE, 'TWD_SK_SHOP', array(
			'rest'     => esc_url_raw( rest_url( TWD_SK_REST::ROUTE_NS . '/shop/' ) ),
			'nonce'    => wp_create_nonce( 'wp_rest' ),
			'mode'     => $ctx['mode'],
			'product'  => $ctx['product'],
			'category' => $ctx['category'],
		) );
		wp_enqueue_script( self::HANDLE );
	}
}
