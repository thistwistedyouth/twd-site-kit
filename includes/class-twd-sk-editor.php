<?php
/**
 * TWD_SK_Editor: loads the front-end editor (the "Edit with AI" button and pop-up).
 *
 * Nothing here is loaded for visitors. The script, the stylesheet and the button
 * only exist for a signed-in user who can edit pages, on the front end. Never in
 * wp-admin, never inside the Elementor editor or the customizer, and never inside the
 * preview frame (a preview gets the stylesheet alone, for its "Preview only" bar).
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class TWD_SK_Editor {

	const HANDLE = 'twd-site-kit-editor';

	public static function init() {
		add_action( 'wp_enqueue_scripts', array( __CLASS__, 'enqueue' ), 20 );
		add_action( 'wp_footer', array( __CLASS__, 'print_root' ) );
	}

	/** True inside Elementor's editor or preview, or the customizer. The editor stays out of those. */
	private static function in_builder_or_customizer() {
		if ( isset( $_GET['elementor-preview'] ) || isset( $_GET['elementor_library'] ) ) {
			return true;
		}
		return function_exists( 'is_customize_preview' ) && is_customize_preview();
	}

	/**
	 * What the editor needs to know about this request, or null when it must not load.
	 *
	 * It loads for any signed-in user who can edit pages, on any front-end page, because the
	 * New page tab works anywhere. The Edit tab needs a kit page they can edit, and the
	 * This page card needs a single page they can edit.
	 */
	public static function context() {
		if ( is_admin() || ! is_user_logged_in() || ! current_user_can( 'edit_pages' ) ) {
			return null;
		}
		if ( TWD_SK_Preview::is_preview_request() || self::in_builder_or_customizer() ) {
			return null;
		}
		$page_id  = TWD_SK_Page::viewed_page_id();
		$can_edit = $page_id > 0 && current_user_can( 'edit_post', $page_id );
		return array(
			'page_id'      => $can_edit ? $page_id : 0,
			'is_kit_page'  => $can_edit && TWD_SK_Page::is_kit_page( $page_id ),
			'can_publish'  => $can_edit && current_user_can( 'publish_pages' ) && current_user_can( 'publish_post', $page_id ),
		);
	}

	public static function enqueue() {
		$ver  = defined( 'TWD_SK_VERSION' ) ? TWD_SK_VERSION : false;
		$base = TWD_SK_URL;

		// A validated preview shows the stylesheet only, for its "Preview only" bar.
		if ( TWD_SK_Preview::is_active() ) {
			wp_enqueue_style( self::HANDLE, $base . 'assets/twd-site-kit-editor.css', array( TWD_SK_Assets::HANDLE ), $ver );
			return;
		}

		$context = self::context();
		if ( null === $context ) {
			return;
		}

		wp_enqueue_style( self::HANDLE, $base . 'assets/twd-site-kit-editor.css', array( TWD_SK_Assets::HANDLE ), $ver );
		wp_register_script( self::HANDLE, $base . 'assets/twd-site-kit-editor.js', array(), $ver, true );
		wp_localize_script( self::HANDLE, 'TWD_SK_EDITOR', array(
			'restUrl'        => esc_url_raw( rest_url( TWD_SK_REST::ROUTE_NS ) ),
			'nonce'          => wp_create_nonce( 'wp_rest' ),
			'pageId'         => (int) $context['page_id'],
			'isKitPage'      => (bool) $context['is_kit_page'],
			'canPublish'     => (bool) $context['can_publish'],
			'currentVersion' => $context['page_id'] ? TWD_SK_Store::get_current_version_id( $context['page_id'] ) : 0,
			'maxBytes'       => TWD_SK_Store::MAX_BYTES,
			'maxTitle'       => TWD_SK_Template::MAX_TITLE,
			'starters'       => TWD_SK_Template::starters(),
		) );
		wp_enqueue_script( self::HANDLE );
	}

	/** The button. It stays hidden until the script runs; the pop-up itself is built by the script. */
	public static function print_root() {
		if ( null === self::context() ) {
			return;
		}
		echo '<div id="twd-sk-ed" class="twd-sk-ed"><button type="button" id="twd-sk-ed-open" class="twd-sk-ed__open" hidden aria-haspopup="dialog">'
			. esc_html__( 'Edit with AI', 'twd-site-kit' )
			. '</button></div>';
	}
}
