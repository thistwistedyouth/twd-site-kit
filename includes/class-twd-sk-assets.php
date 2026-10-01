<?php
/**
 * TWD_SK_Assets: loads the kit stylesheet and prints the active pack's tokens.
 *
 * The stylesheet is enqueued on every front-end page, not only pages that
 * contain [twd_page]. A shortcode inside an Elementor widget cannot be detected
 * ahead of time (Elementor keeps its content in JSON), so gating on it would
 * miss pages. The stylesheet is small. Every rule in it is scoped under
 * .twd-sk-page.
 *
 * The pack's @font-face blocks and :root tokens are attached to the stylesheet
 * as inline CSS, so they print together with it.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class TWD_SK_Assets {

	const HANDLE = 'twd-site-kit';

	public static function init() {
		add_action( 'wp_enqueue_scripts', array( __CLASS__, 'enqueue' ) );
	}

	public static function enqueue() {
		$base = TWD_SK_URL;
		wp_register_style( self::HANDLE, $base . 'assets/twd-site-kit.css', array(), defined( 'TWD_SK_VERSION' ) ? TWD_SK_VERSION : false );
		wp_enqueue_style( self::HANDLE );
		wp_add_inline_style( self::HANDLE, TWD_SK_Packs::inline_css( $base . 'assets/fonts/' ) );
	}
}
