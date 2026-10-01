<?php
/**
 * TWD_SK_Preview: short-lived previews of a draft page, using the real theme.
 *
 * The editor sends pasted HTML to the REST layer, which cleans it and calls
 * create() here. Nothing is written to the page or its history. The cleaned HTML
 * waits in a transient under a random token for 15 minutes. A request to the
 * page's own address with ?twd_sk_preview=TOKEN renders that draft in place of the
 * stored HTML, but only for the user who made it, who must still be able to edit
 * that page. Anyone else gets a plain refusal.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class TWD_SK_Preview {

	const QUERY_VAR = 'twd_sk_preview';
	const TTL       = 900;  // 15 minutes.
	const MAX_KEPT  = 5;    // Stored previews per user. The oldest is dropped.

	/** Draft for this request, set when a valid preview is being shown. */
	private static $active = null;

	public static function init() {
		add_action( 'template_redirect', array( __CLASS__, 'maybe_start' ), 1 );
	}

	// -- Storage ----------------------------------------------------------

	private static function key( $token ) {
		return 'twd_sk_prev_' . $token;
	}

	private static function index_key( $user_id ) {
		return 'twd_sk_prevs_' . (int) $user_id;
	}

	public static function valid_token( $token ) {
		return is_string( $token ) && 1 === preg_match( '/^[a-f0-9]{32}$/', $token );
	}

	/**
	 * Store cleaned HTML for a page and return its token.
	 */
	public static function create( $user_id, $page_id, $clean_html ) {
		$token = bin2hex( random_bytes( 16 ) );
		set_transient(
			self::key( $token ),
			array(
				'user'    => (int) $user_id,
				'page'    => (int) $page_id,
				'html'    => (string) $clean_html,
				'created' => time(),
			),
			self::TTL
		);

		$index = get_transient( self::index_key( $user_id ) );
		$index = is_array( $index ) ? $index : array();
		$index[] = $token;
		while ( count( $index ) > self::MAX_KEPT ) {
			delete_transient( self::key( array_shift( $index ) ) );
		}
		set_transient( self::index_key( $user_id ), $index, self::TTL );

		return $token;
	}

	/**
	 * Read a preview. Returns the stored array, or null when it is missing,
	 * expired or malformed.
	 */
	public static function read( $token ) {
		if ( ! self::valid_token( $token ) ) {
			return null;
		}
		$data = get_transient( self::key( $token ) );
		if ( ! is_array( $data ) || ! isset( $data['user'], $data['page'], $data['html'], $data['created'] ) ) {
			return null;
		}
		if ( (int) $data['created'] + self::TTL < time() ) {
			delete_transient( self::key( $token ) );
			return null;
		}
		return $data;
	}

	/** Delete a preview, but only the owner's own. */
	public static function discard( $token, $user_id ) {
		$data = self::read( $token );
		if ( ! $data || (int) $data['user'] !== (int) $user_id ) {
			return false;
		}
		delete_transient( self::key( $token ) );
		return true;
	}

	/** The address that shows the draft inside the real page. */
	public static function url( $page_id, $token ) {
		return add_query_arg( array( self::QUERY_VAR => $token ), get_permalink( $page_id ) );
	}

	// -- Showing it -------------------------------------------------------

	/** The token in the current request, or '' when there is none or it is malformed. */
	public static function request_token() {
		if ( ! isset( $_GET[ self::QUERY_VAR ] ) || ! is_string( $_GET[ self::QUERY_VAR ] ) ) {
			return '';
		}
		$token = $_GET[ self::QUERY_VAR ];
		return self::valid_token( $token ) ? $token : '';
	}

	/** True when this request carries a preview token (valid or not). Used to keep the editor out of the frame. */
	public static function is_preview_request() {
		return isset( $_GET[ self::QUERY_VAR ] );
	}

	/** True while a validated preview is being shown. */
	public static function is_active() {
		return null !== self::$active;
	}

	public static function reset() {
		self::$active = null;
	}

	/**
	 * template_redirect: validate the token and, if all is well, swap the page's
	 * HTML for the draft. Every failure is a plain refusal.
	 */
	public static function maybe_start() {
		if ( ! self::is_preview_request() ) {
			return;
		}

		$token = self::request_token();
		$page  = function_exists( 'get_queried_object_id' ) ? (int) get_queried_object_id() : 0;
		$data  = '' !== $token ? self::read( $token ) : null;

		if ( ! is_user_logged_in() || $page <= 0 || ! current_user_can( 'edit_post', $page ) ) {
			self::refuse( 'You need to be signed in as someone who can edit this page to see a preview.' );
			return;
		}
		if ( ! $data ) {
			self::refuse( 'This preview has expired or was discarded. Close this window and press Preview again.' );
			return;
		}
		if ( (int) $data['user'] !== (int) get_current_user_id() || (int) $data['page'] !== $page ) {
			self::refuse( 'This preview belongs to someone else or to another page.' );
			return;
		}

		self::$active = $data;
		nocache_headers();
		if ( ! defined( 'DONOTCACHEPAGE' ) ) {
			define( 'DONOTCACHEPAGE', true );
		}
		add_filter( 'twd_sk_page_html', array( __CLASS__, 'filter_html' ), 10, 2 );
		add_filter( 'wp_robots', array( __CLASS__, 'filter_robots' ) );
		add_action( 'wp_body_open', array( __CLASS__, 'print_bar' ) );
	}

	private static function refuse( $message ) {
		wp_die( esc_html( $message ), 'Preview unavailable', array( 'response' => 403 ) );
	}

	/** Swap in the draft for the page it was made for. */
	public static function filter_html( $html, $post_id ) {
		if ( null !== self::$active && (int) $post_id === (int) self::$active['page'] ) {
			return self::$active['html'];
		}
		return $html;
	}

	public static function filter_robots( $robots ) {
		$robots['noindex']  = true;
		$robots['nofollow'] = true;
		return $robots;
	}

	/** A slim bar so nobody mistakes the preview for the saved page. */
	public static function print_bar() {
		echo '<div class="twd-sk-ed"><div class="twd-sk-ed__bar" role="status">' . esc_html( 'Preview only. Nothing has been saved.' ) . '</div></div>';
	}
}
