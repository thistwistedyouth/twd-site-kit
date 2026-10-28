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
 *  - Elementor and a kit page (0.14.1): a kit page keeps its words in this plugin and its template never prints
 *    the page's normal content, so changes made in Elementor would not show, and Elementor's page layout
 *    setting can swap the kit template out and leave the page blank. So opening Elementor's editor on a kit page
 *    (post.php?action=elementor, the one door every route into it passes through) shows a plain card instead,
 *    with a link to the page; an administrator can carry on with one more click (a nonce, per page). The
 *    "Edit with Elementor" row action and the big button on the classic edit screen are hidden from anyone who
 *    is not an administrator. Checked against Elementor 4.3.5: the `admin_action_elementor` hook, the row action
 *    key `edit_with_elementor` (added at priority 11) and `Plugin::$instance->admin->print_switch_mode_button`.
 *    A page that is not a kit page is never touched. Elementor's own admin bar item is not needed: it only lists
 *    documents Elementor drew on the page, and the kit template draws none.
 *
 * Started from TWD_SK_Modules, so safe mode switches all of it off. Nothing here writes page HTML.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class TWD_SK_Guard {

	const TRANSIENT = 'twd_sk_held_back';
	const ACTION    = 'twd_sk_publish_anyway';

	/** Query argument and nonce action for an administrator who has read the Elementor warning and carries on. */
	const ELEMENTOR_OK = 'twd_sk_elementor_ok';

	/** True while the kit's own publish path runs, so the guard lets it through. */
	public static $allow = false;

	public static function init() {
		if ( ! is_admin() ) {
			return;
		}
		add_filter( 'wp_insert_post_data', array( __CLASS__, 'hold_back' ), 10, 2 );
		add_action( 'admin_notices', array( __CLASS__, 'notices' ) );
		add_action( 'admin_post_' . self::ACTION, array( __CLASS__, 'publish_anyway' ) );
		// Elementor and kit pages (0.14.1). Priority 1 on the editor door runs before Elementor's own handler (10);
		// 12 on the row actions runs after Elementor adds its link (11).
		add_action( 'admin_action_elementor', array( __CLASS__, 'block_elementor_editor' ), 1 );
		add_filter( 'page_row_actions', array( __CLASS__, 'row_actions' ), 12, 2 );
		add_action( 'edit_form_after_title', array( __CLASS__, 'hide_elementor_button' ), 1 );
	}

	/**
	 * Elementor's editor is opened by post.php?post=ID&action=elementor. On a kit page it is replaced by a plain
	 * card, unless an administrator has used the one-click "open it anyway" link (a nonce for that page).
	 */
	public static function block_elementor_editor() {
		$id = isset( $_REQUEST['post'] ) ? absint( $_REQUEST['post'] ) : 0; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		if ( $id <= 0 || ! TWD_SK_Page::is_kit_page( $id ) ) {
			return;
		}
		$ok    = isset( $_GET[ self::ELEMENTOR_OK ] ) && '1' === (string) $_GET[ self::ELEMENTOR_OK ]; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		$nonce = isset( $_GET['_wpnonce'] ) ? (string) $_GET['_wpnonce'] : ''; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		if ( $ok && current_user_can( 'manage_options' ) && wp_verify_nonce( $nonce, self::ELEMENTOR_OK . '_' . $id ) ) {
			return;
		}
		wp_die( self::elementor_card( $id ), esc_html__( 'Edited on the page itself', 'twd-site-kit' ), array( 'response' => 200 ) );
	}

	/** The card shown in place of Elementor's editor, in plain words. Administrators get the one-click way on. */
	public static function elementor_card( $id ) {
		$out  = '<h1>' . esc_html__( 'This page is edited on the page itself', 'twd-site-kit' ) . '</h1>';
		$out .= '<p>' . esc_html__( 'It is built with Site Kit, which keeps its words and pictures itself. Open the page while signed in and press Visual editor to change them. Elementor does not hold this page\'s content, so changes made there would not show, and its page layout setting can leave the page blank until the TWD Kit Page template is chosen again.', 'twd-site-kit' ) . '</p>';
		$out .= '<p><a class="button button-primary" href="' . esc_url( get_permalink( $id ) ) . '">' . esc_html__( 'Open the page', 'twd-site-kit' ) . '</a></p>';
		if ( current_user_can( 'manage_options' ) ) {
			$url  = wp_nonce_url( add_query_arg( array( 'post' => (int) $id, 'action' => 'elementor', self::ELEMENTOR_OK => '1' ), admin_url( 'post.php' ) ), self::ELEMENTOR_OK . '_' . (int) $id );
			$out .= '<p>' . esc_html__( 'Administrators only:', 'twd-site-kit' ) . ' <a href="' . esc_url( $url ) . '">' . esc_html__( 'open Elementor anyway', 'twd-site-kit' ) . '</a>.</p>';
		}
		return $out;
	}

	/** The "Edit with Elementor" link in the pages list is hidden on kit pages from anyone but an administrator. */
	public static function row_actions( $actions, $post = null ) {
		if ( is_array( $actions ) && isset( $actions['edit_with_elementor'] ) && is_object( $post ) && ! current_user_can( 'manage_options' ) && TWD_SK_Page::is_kit_page( (int) $post->ID ) ) {
			unset( $actions['edit_with_elementor'] );
		}
		return $actions;
	}

	/** The big "Edit with Elementor" button on the classic edit screen, hidden on kit pages from anyone but an administrator. */
	public static function hide_elementor_button( $post = null ) {
		if ( ! is_object( $post ) || current_user_can( 'manage_options' ) || ! TWD_SK_Page::is_kit_page( (int) $post->ID ) ) {
			return;
		}
		if ( class_exists( '\Elementor\Plugin' ) && isset( \Elementor\Plugin::$instance->admin ) ) {
			remove_action( 'edit_form_after_title', array( \Elementor\Plugin::$instance->admin, 'print_switch_mode_button' ) );
		}
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
