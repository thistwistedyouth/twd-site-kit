<?php
/**
 * TWD_SK_Guard (0.14.0): two small protections in wp-admin for pages that use the kit.
 *
 *  - Publishing from WordPress's own screens (the page editor, Quick Edit, bulk actions) is held back
 *    while a kit page still has example text or [PLACEHOLDER] markers: the page stays a draft and a notice
 *    says what is left and offers "Publish anyway" (one click, with a nonce). The kit's own routes (the
 *    Pages tab, the launch check, WP-CLI) already refuse or ask, so they are not touched: they publish
 *    through TWD_SK_Template::set_status, which sets a flag while it runs.
 *  - A notice on a kit page's edit screen says the page is edited on the site itself with the Visual
 *    editor, with a link, because the content box there only holds a plain-text copy.
 *
 * Started from TWD_SK_Modules, so safe mode switches both off. Nothing here writes page HTML.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class TWD_SK_Guard {

	const TRANSIENT = 'twd_sk_held_back';
	const ACTION    = 'twd_sk_publish_anyway';

	/** True while the kit's own publish path runs, so the guard lets it through. */
	public static $allow = false;

	public static function init() {
		if ( ! is_admin() ) {
			return;
		}
		add_filter( 'wp_insert_post_data', array( __CLASS__, 'hold_back' ), 10, 2 );
		add_action( 'admin_notices', array( __CLASS__, 'notices' ) );
		add_action( 'admin_post_' . self::ACTION, array( __CLASS__, 'publish_anyway' ) );
	}

	/**
	 * A kit page being set to publish with "must fix" text left is kept as a draft, and the reason is
	 * remembered for the notice. Our own publish path (TWD_SK_Template::set_status) is let through.
	 */
	public static function hold_back( $data, $postarr ) {
		if ( self::$allow || ! is_array( $data ) || ! isset( $data['post_type'], $data['post_status'] ) || 'page' !== $data['post_type'] || 'publish' !== $data['post_status'] ) {
			return $data;
		}
		$id = isset( $postarr['ID'] ) ? (int) $postarr['ID'] : 0;
		if ( $id <= 0 || ! TWD_SK_Page::is_kit_page( $id ) ) {
			return $data;
		}
		$post = get_post( $id );
		if ( $post && 'publish' === $post->post_status ) {
			return $data; // Already live: an ordinary update, not a publish.
		}
		$levels = TWD_SK_Store::get_leftover_levels( $id );
		$count  = TWD_SK_Report::leftover_total( $levels['must'] );
		if ( 0 === $count ) {
			return $data;
		}
		$data['post_status'] = 'draft';
		$held                = get_transient( self::TRANSIENT );
		$held                = is_array( $held ) ? $held : array();
		$held[ $id ]         = array( 'count' => $count, 'what' => implode( ', ', array_map( 'strval', array_keys( $levels['must'] ) ) ), 'user' => (int) get_current_user_id() );
		set_transient( self::TRANSIENT, $held, 10 * MINUTE_IN_SECONDS );
		return $data;
	}

	/** The notices: a held-back publish, and where a kit page is edited. */
	public static function notices() {
		$screen = function_exists( 'get_current_screen' ) ? get_current_screen() : null;
		$id     = isset( $_GET['post'] ) ? (int) $_GET['post'] : 0; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		$held   = get_transient( self::TRANSIENT );
		if ( is_array( $held ) && $held ) {
			$user = (int) get_current_user_id();
			foreach ( $held as $pid => $h ) {
				if ( (int) $h['user'] !== $user ) {
					continue;
				}
				$post = get_post( (int) $pid );
				if ( ! $post ) {
					continue;
				}
				$url = wp_nonce_url( admin_url( 'admin-post.php?action=' . self::ACTION . '&post=' . (int) $pid ), self::ACTION . '_' . (int) $pid );
				echo '<div class="notice notice-warning"><p>' . esc_html( sprintf( '"%s" was kept as a draft: %d thing%s still to replace (%s). Fix them on the page with the Visual editor, or ', (string) $post->post_title, (int) $h['count'], 1 === (int) $h['count'] ? '' : 's', (string) $h['what'] ) )
					. '<a href="' . esc_url( $url ) . '">' . esc_html__( 'publish it anyway', 'twd-site-kit' ) . '</a>.</p></div>';
			}
			$rest = array_filter( $held, function ( $h ) use ( $user ) {
				return (int) $h['user'] !== $user;
			} );
			if ( $rest ) {
				set_transient( self::TRANSIENT, $rest, 10 * MINUTE_IN_SECONDS );
			} else {
				delete_transient( self::TRANSIENT );
			}
		}
		if ( $screen && 'post' === $screen->id && $id > 0 && TWD_SK_Page::is_kit_page( $id ) ) {
			echo '<div class="notice notice-info"><p>' . esc_html__( 'This page is built with Site Kit. Its words and pictures are edited on the site itself: open the page and press Visual editor (or Edit with AI). The content box below only holds a plain-text copy for search, and changes typed here do not show to visitors.', 'twd-site-kit' )
				. ' <a href="' . esc_url( get_permalink( $id ) ) . '">' . esc_html__( 'Open the page', 'twd-site-kit' ) . '</a></p></div>';
		}
	}

	/** The "publish anyway" link: the same explicit override the Pages tab offers. */
	public static function publish_anyway() {
		$id = isset( $_GET['post'] ) ? (int) $_GET['post'] : 0; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		if ( $id <= 0 || ! check_admin_referer( self::ACTION . '_' . $id ) || ! current_user_can( 'publish_pages' ) || ! current_user_can( 'publish_post', $id ) ) {
			wp_die( esc_html__( 'You cannot publish this page.', 'twd-site-kit' ) );
		}
		$r = TWD_SK_Template::set_status( $id, 'publish', true );
		if ( is_wp_error( $r ) ) {
			wp_die( esc_html( $r->get_error_message() ) );
		}
		wp_safe_redirect( admin_url( 'post.php?post=' . $id . '&action=edit' ) );
		exit;
	}

	/** The guard's view of a page: how many things must be fixed before it may go live, in plain words. */
	public static function describe( $post_id ) {
		$levels = TWD_SK_Store::get_leftover_levels( (int) $post_id );
		return array( 'count' => TWD_SK_Report::leftover_total( $levels['must'] ), 'what' => implode( ', ', array_map( 'strval', array_keys( $levels['must'] ) ) ) );
	}
}
