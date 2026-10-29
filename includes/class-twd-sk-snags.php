<?php
/**
 * TWD_SK_Snags: the "Tell us" module (0.11.0).
 *
 * A signed-in user who can edit pages tells the site owner, from the front of their own site, what
 * they would like changed. The note is kept ON THIS SITE as a private post (a custom post type with
 * no public address): the message, the page, the date, a status and who sent it. Nothing leaves the
 * site except an optional email that says a note arrived, never what it says. There is no outside
 * service and no token.
 *
 * Rules:
 *  - Started from TWD_SK_Modules, so safe mode switches it off. Notes already saved stay in the
 *    database and come back when safe mode is turned off.
 *  - Off by default (option twd_sk_snags). Retention is 12 months unless changed (twd_sk_snags_months).
 *  - Text only. Web addresses are removed from a message and never printed by this module.
 *  - Ideas are the owner's own notes (type idea). Clients never see them, never list them, never
 *    delete them. Only administrators can add an idea or see anyone's notes.
 *  - Every route needs a valid REST nonce, a signed-in user and edit_pages, and is rate limited.
 *  - Personal data tools (export and erase) cover the notes a person sent.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class TWD_SK_Snags {

	const POST_TYPE     = 'twd_sk_snag';
	const OPT_ON        = 'twd_sk_snags';
	const OPT_MONTHS    = 'twd_sk_snags_months';
	const OPT_EMAIL_ON  = 'twd_sk_snags_email_on';
	const OPT_EMAIL     = 'twd_sk_snags_email';
	const OPT_SWEPT     = 'twd_sk_snags_swept';
	const MAX_LEN       = 2000;
	const MIN_LEN       = 3;
	const USER_PER_HOUR = 10;
	const SITE_PER_DAY  = 100;
	const LIST_CAP      = 200;
	const DEFAULT_MONTHS = 12;
	const ROUTE_NS      = 'twd-site-kit/v1';
	const NONCE_HEADER  = 'X-WP-Nonce';
	const MAX_BODY      = 16384;
	const HANDLE        = 'twd-site-kit-snags';

	/** The months a site may keep notes for. */
	public static function month_choices() {
		return array( 3, 6, 12, 24, 36 );
	}

	public static function types() {
		return array( 'snag', 'idea' );
	}

	public static function statuses() {
		return array(
			'new'    => 'New',
			'seen'   => 'Seen',
			'done'   => 'Done',
			'parked' => 'Parked',
		);
	}

	public static function init() {
		add_action( 'init', array( __CLASS__, 'register_post_type' ) );
		add_action( 'init', array( __CLASS__, 'maybe_sweep' ), 30 );
		add_action( 'rest_api_init', array( __CLASS__, 'register_routes' ) );
		add_action( 'wp_enqueue_scripts', array( __CLASS__, 'enqueue' ), 25 );
		add_action( 'wp_footer', array( __CLASS__, 'print_root' ) );
		add_filter( 'wp_privacy_personal_data_exporters', array( __CLASS__, 'add_exporter' ) );
		add_filter( 'wp_privacy_personal_data_erasers', array( __CLASS__, 'add_eraser' ) );
	}

	public static function register_post_type() {
		if ( ! function_exists( 'register_post_type' ) ) {
			return;
		}
		register_post_type( self::POST_TYPE, array(
			'labels'              => array( 'name' => 'Tell us notes', 'singular_name' => 'Tell us note' ),
			'public'              => false,
			'publicly_queryable'  => false,
			'exclude_from_search' => true,
			'show_ui'             => false,
			'show_in_menu'        => false,
			'show_in_rest'        => false,
			'has_archive'         => false,
			'rewrite'             => false,
			'query_var'           => false,
			'supports'            => array( 'title', 'editor', 'author' ),
			'can_export'          => false,
		) );
	}

	// -- Settings -----------------------------------------------------------

	/** The module is on only outside safe mode and only when the owner has switched it on. */
	public static function enabled() {
		return ! TWD_SK_Safe::on() && '1' === (string) get_option( self::OPT_ON, '' );
	}

	public static function months() {
		$m = (int) get_option( self::OPT_MONTHS, self::DEFAULT_MONTHS );
		return in_array( $m, self::month_choices(), true ) ? $m : self::DEFAULT_MONTHS;
	}

	public static function email_on() {
		return '1' === (string) get_option( self::OPT_EMAIL_ON, '' ) && '' !== self::email_to();
	}

	public static function email_to() {
		$e = (string) get_option( self::OPT_EMAIL, '' );
		return ( '' !== $e && is_email( $e ) ) ? $e : '';
	}

	/** The settings as the Site tab shows them. */
	public static function settings() {
		return array(
			'enabled'      => '1' === (string) get_option( self::OPT_ON, '' ),
			'months'       => self::months(),
			'month_choices' => self::month_choices(),
			'email_on'     => '1' === (string) get_option( self::OPT_EMAIL_ON, '' ),
			'email'        => (string) get_option( self::OPT_EMAIL, '' ),
			'safe_mode'    => TWD_SK_Safe::on(),
		);
	}

	/** Checks and saves the settings. Returns the saved settings or a WP_Error that changes nothing. */
	public static function save_settings( $input ) {
		$input = is_array( $input ) ? $input : array();
		$email = isset( $input['email'] ) && is_string( $input['email'] ) ? trim( $input['email'] ) : '';
		if ( '' !== $email && ! is_email( $email ) ) {
			return new WP_Error( 'twd_sk_bad_email', 'That does not look like an email address.' );
		}
		$months = isset( $input['months'] ) ? (int) $input['months'] : self::months();
		if ( ! in_array( $months, self::month_choices(), true ) ) {
			return new WP_Error( 'twd_sk_bad_months', 'Choose one of the lengths of time on the list.' );
		}
		$email_on = ! empty( $input['email_on'] ) && '' !== $email;
		if ( ! empty( $input['email_on'] ) && '' === $email ) {
			return new WP_Error( 'twd_sk_bad_email', 'Add an email address to switch the notification on.' );
		}
		update_option( self::OPT_ON, ! empty( $input['enabled'] ) ? '1' : '' );
		update_option( self::OPT_MONTHS, $months );
		update_option( self::OPT_EMAIL, $email );
		update_option( self::OPT_EMAIL_ON, $email_on ? '1' : '' );
		return self::settings();
	}

	/** The privacy line shown on the form. */
	public static function privacy_line() {
		$m = self::months();
		return 'We keep your note on this site for ' . $m . ' months, then it is deleted. It records your message, the page, the date and that it came from you. Please do not include web addresses, they are removed. You can delete your note at any time.';
	}

	// -- Cleaning -----------------------------------------------------------

	/** A message cleaned for storage: plain text, no web addresses, no long dashes, capped. */
	public static function clean_message( $text ) {
		if ( ! is_string( $text ) ) {
			return '';
		}
		$text = wp_strip_all_tags( $text );
		$text = preg_replace( '/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F]/', '', $text );
		$text = preg_replace( '/\r\n?/', "\n", $text );
		$text = preg_replace( '#\b(?:[a-z][a-z0-9+.\-]*://|www\.)[^\s<>"\']+#i', '(web address removed)', $text );
		$text = TWD_SK_Sanitizer::strip_dashes( $text );
		$text = preg_replace( "/[ \t]+\n/", "\n", $text );
		$text = preg_replace( "/\n{3,}/", "\n\n", $text );
		$text = trim( $text );
		if ( function_exists( 'mb_substr' ) ) {
			$text = mb_substr( $text, 0, self::MAX_LEN );
		} else {
			$text = substr( $text, 0, self::MAX_LEN );
		}
		return trim( $text );
	}

	// -- The records --------------------------------------------------------

	/**
	 * Saves a note. $type is snag, or idea (the caller has already checked the person is an administrator).
	 * Returns the new ID or a WP_Error. Does not check capabilities: the route and the caller do.
	 */
	public static function create( $user_id, $message, $page_id = 0, $type = 'snag' ) {
		$message = self::clean_message( $message );
		if ( strlen( $message ) < self::MIN_LEN ) {
			return new WP_Error( 'twd_sk_snag_empty', 'Please write a few words about what you would like changed.' );
		}
		$type = in_array( $type, self::types(), true ) ? $type : 'snag';

		$page_id    = (int) $page_id;
		$page_title = '';
		if ( $page_id > 0 ) {
			$post = get_post( $page_id );
			if ( $post && ( 'publish' === $post->post_status || current_user_can( 'edit_post', $page_id ) ) ) {
				$page_title = (string) get_the_title( $page_id );
			} else {
				$page_id = 0;
			}
		}
		$page_title = TWD_SK_Sanitizer::strip_dashes( wp_strip_all_tags( $page_title ) );
		$page_title = function_exists( 'mb_substr' ) ? mb_substr( $page_title, 0, 120 ) : substr( $page_title, 0, 120 );

		$id = wp_insert_post( array(
			'post_type'    => self::POST_TYPE,
			'post_status'  => 'publish',
			'post_title'   => 'Note',
			'post_content' => $message,
			'post_author'  => (int) $user_id,
		), true );
		if ( is_wp_error( $id ) || ! $id ) {
			return new WP_Error( 'twd_sk_snag_failed', 'We could not save your note. Please try again.' );
		}
		update_post_meta( $id, '_twd_sk_snag_type', $type );
		update_post_meta( $id, '_twd_sk_snag_status', 'new' );
		update_post_meta( $id, '_twd_sk_snag_page', $page_id );
		update_post_meta( $id, '_twd_sk_snag_page_title', $page_title );
		update_post_meta( $id, '_twd_sk_snag_time', time() );
		return (int) $id;
	}

	/** One record as plain data, or null when it is not a note. */
	public static function get( $id ) {
		$post = get_post( (int) $id );
		if ( ! $post || self::POST_TYPE !== $post->post_type ) {
			return null;
		}
		$type   = get_post_meta( $post->ID, '_twd_sk_snag_type', true );
		$status = get_post_meta( $post->ID, '_twd_sk_snag_status', true );
		$time   = (int) get_post_meta( $post->ID, '_twd_sk_snag_time', true );
		$page   = (int) get_post_meta( $post->ID, '_twd_sk_snag_page', true );
		$title  = (string) get_post_meta( $post->ID, '_twd_sk_snag_page_title', true );
		return array(
			'id'         => (int) $post->ID,
			'type'       => in_array( $type, self::types(), true ) ? $type : 'snag',
			'status'     => isset( self::statuses()[ $status ] ) ? $status : 'new',
			'message'    => (string) $post->post_content,
			'page_id'    => $page,
			'page_title' => '' !== $title ? $title : ( $page > 0 ? 'Page ' . $page : 'Not on a page' ),
			'time'       => $time,
			'user_id'    => (int) $post->post_author,
		);
	}

	/**
	 * Notes, newest first. Options: user_id (only that person's), type, status, include_ideas (default false).
	 * Never more than LIST_CAP.
	 */
	public static function find( $opts = array() ) {
		$ids = get_posts( array(
			'post_type'   => self::POST_TYPE,
			'post_status' => 'any',
			'numberposts' => 1000,
			'fields'      => 'ids',
		) );
		$out = array();
		foreach ( (array) $ids as $id ) {
			$n = self::get( $id );
			if ( ! $n ) {
				continue;
			}
			if ( isset( $opts['user_id'] ) && $n['user_id'] !== (int) $opts['user_id'] ) {
				continue;
			}
			if ( empty( $opts['include_ideas'] ) && 'idea' === $n['type'] ) {
				continue;
			}
			if ( ! empty( $opts['type'] ) && $n['type'] !== $opts['type'] ) {
				continue;
			}
			if ( ! empty( $opts['status'] ) && $n['status'] !== $opts['status'] ) {
				continue;
			}
			$out[] = $n;
		}
		usort( $out, function ( $a, $b ) {
			return $a['time'] === $b['time'] ? $b['id'] - $a['id'] : $b['time'] - $a['time'];
		} );
		return array_slice( $out, 0, self::LIST_CAP );
	}

	public static function set_status( $id, $status ) {
		$n = self::get( $id );
		if ( ! $n ) {
			return new WP_Error( 'twd_sk_snag_missing', 'That note was not found.' );
		}
		if ( ! isset( self::statuses()[ $status ] ) ) {
			return new WP_Error( 'twd_sk_snag_bad_status', 'That is not a status we know.' );
		}
		update_post_meta( $id, '_twd_sk_snag_status', $status );
		return self::get( $id );
	}

	/** Deletes a note for good. */
	public static function delete( $id ) {
		if ( ! self::get( $id ) ) {
			return false;
		}
		return (bool) wp_delete_post( (int) $id, true );
	}

	/** Counts of snags still new, for the panel and the CLI. */
	public static function count_new() {
		return count( self::find( array( 'status' => 'new', 'type' => 'snag' ) ) );
	}

	// -- Retention ----------------------------------------------------------

	/** Once a day, on a normal page load: delete notes older than the retention. No wp-cron. */
	public static function maybe_sweep() {
		$today = gmdate( 'Ymd' );
		if ( (string) get_option( self::OPT_SWEPT, '' ) === $today ) {
			return;
		}
		update_option( self::OPT_SWEPT, $today );
		self::sweep();
	}

	/** Deletes notes older than the retention, ideas included. Returns how many went. */
	public static function sweep() {
		$limit = time() - ( self::months() * 30 * 86400 );
		$count = 0;
		$ids   = get_posts( array(
			'post_type'   => self::POST_TYPE,
			'post_status' => 'any',
			'numberposts' => 1000,
			'fields'      => 'ids',
		) );
		foreach ( (array) $ids as $id ) {
			$n = self::get( $id );
			if ( $n && $n['time'] > 0 && $n['time'] < $limit && self::delete( $id ) ) {
				$count++;
			}
		}
		return $count;
	}

	// -- Output -------------------------------------------------------------

	/** The new notes (or any status) as plain markdown. No web addresses, no email addresses. */
	public static function markdown( $notes ) {
		$count = count( $notes );
		$out   = '# Tell us notes' . "\n\n";
		if ( ! $count ) {
			return $out . "Nothing new.\n";
		}
		$out .= $count . ( 1 === $count ? ' note' : ' notes' ) . "\n";
		$i = 0;
		foreach ( $notes as $n ) {
			$i++;
			$out .= "\n" . '## ' . $i . '. ' . ( 'idea' === $n['type'] ? 'Idea' : 'Snag' ) . ': ' . $n['page_title'] . "\n\n";
			$out .= '- Note: ' . $n['id'] . "\n";
			$out .= '- Page: ' . ( $n['page_id'] > 0 ? 'ID ' . $n['page_id'] : 'none' ) . "\n";
			$out .= '- Date: ' . ( $n['time'] > 0 ? gmdate( 'j M Y', $n['time'] ) : 'unknown' ) . "\n";
			$out .= '- Status: ' . $n['status'] . "\n";
			$out .= '- From: ' . ( 'idea' === $n['type'] ? 'the site owner' : 'user ' . $n['user_id'] ) . "\n\n";
			$out .= $n['message'] . "\n";
		}
		return $out;
	}

	/** One note as the panel and the CLI show it. $admin adds who it came from. */
	public static function present( $n, $admin = false ) {
		$row = array(
			'id'         => $n['id'],
			'type'       => $n['type'],
			'status'     => $n['status'],
			'message'    => $n['message'],
			'page_id'    => $n['page_id'],
			'page_title' => $n['page_title'],
			'date'       => $n['time'] > 0 ? gmdate( 'j M Y', $n['time'] ) : '',
		);
		if ( $admin ) {
			$u = 'idea' === $n['type'] ? false : get_userdata( $n['user_id'] );
			$row['from'] = 'idea' === $n['type'] ? 'You (idea)' : ( $u && ! empty( $u->display_name ) ? wp_strip_all_tags( (string) $u->display_name ) : 'User ' . $n['user_id'] );
		}
		return $row;
	}

	// -- The email (a notification only) -------------------------------------

	/** Says a note arrived. Never contains the message. Returns true when a mail was handed over. */
	public static function notify( $note_id ) {
		if ( ! self::email_on() ) {
			return false;
		}
		$n = self::get( $note_id );
		if ( ! $n || 'snag' !== $n['type'] ) {
			return false;
		}
		$site = wp_strip_all_tags( (string) get_bloginfo( 'name' ) );
		$body = "A new Tell us note has arrived on your site.\n\nPage: " . $n['page_title'] . "\n\nOpen the Tell us panel on the site, or run the snags command, to read it.\n";
		return (bool) wp_mail( self::email_to(), 'A new Tell us note' . ( '' !== $site ? ' on ' . $site : '' ), $body );
	}

	// -- Limits ---------------------------------------------------------------

	/** True when this person may send another note now. Counts the attempt when it allows it. */
	private static function allow_send( $user_id ) {
		$now   = time();
		$ukey  = 'twd_sk_snag_rl_' . (int) $user_id;
		$state = get_transient( $ukey );
		if ( ! is_array( $state ) || ! isset( $state['start'], $state['count'] ) || $now - (int) $state['start'] >= 3600 ) {
			$state = array( 'start' => $now, 'count' => 0 );
		}
		if ( (int) $state['count'] >= self::USER_PER_HOUR ) {
			return 'user';
		}
		$skey = 'twd_sk_snag_day_' . gmdate( 'Ymd' );
		$day  = (int) get_transient( $skey );
		if ( $day >= self::SITE_PER_DAY ) {
			return 'site';
		}
		$state['count']++;
		set_transient( $ukey, $state, 3600 );
		set_transient( $skey, $day + 1, 86400 );
		return '';
	}

	/** The reads and the other routes share a generous limit so a stuck script cannot hammer the site. */
	private static function allow_read( $user_id ) {
		$now   = time();
		$key   = 'twd_sk_snag_rd_' . (int) $user_id;
		$state = get_transient( $key );
		if ( ! is_array( $state ) || ! isset( $state['start'], $state['count'] ) || $now - (int) $state['start'] >= 60 ) {
			$state = array( 'start' => $now, 'count' => 0 );
		}
		if ( (int) $state['count'] >= 60 ) {
			return false;
		}
		$state['count']++;
		set_transient( $key, $state, 60 );
		return true;
	}

	// -- REST ---------------------------------------------------------------

	private static function error( $code, $message, $status ) {
		return new WP_Error( $code, $message, array( 'status' => $status ) );
	}

	/** The gate every route passes: nonce, sign-in, capability, size, rate. Returns true or a WP_Error. */
	private static function guard( $request, $admin_only = false, $needs_module = true ) {
		$nonce = $request->get_header( self::NONCE_HEADER );
		if ( ! is_string( $nonce ) || '' === $nonce || ! wp_verify_nonce( $nonce, 'wp_rest' ) ) {
			return self::error( 'twd_sk_bad_nonce', 'Your sign-in has timed out. Reload the page and try again.', 401 );
		}
		if ( ! is_user_logged_in() ) {
			return self::error( 'twd_sk_not_signed_in', 'You need to be signed in.', 401 );
		}
		$cap = $admin_only ? 'manage_options' : 'edit_pages';
		if ( ! current_user_can( $cap ) ) {
			return self::error( 'twd_sk_forbidden', $admin_only ? 'Only administrators can do that.' : 'You do not have permission to do that.', 403 );
		}
		if ( $needs_module && ! self::enabled() ) {
			return self::error( 'twd_sk_snags_off', 'Tell us is switched off on this site.', 403 );
		}
		if ( strlen( (string) $request->get_body() ) > self::MAX_BODY ) {
			return self::error( 'twd_sk_too_large', 'That is too long. Please keep it shorter.', 413 );
		}
		if ( ! self::allow_read( get_current_user_id() ) ) {
			return self::error( 'twd_sk_rate_limited', 'Too many requests. Wait a minute and try again.', 429 );
		}
		return true;
	}

	public static function can_use( $request ) {
		return self::guard( $request );
	}

	public static function can_admin( $request ) {
		return self::guard( $request, true );
	}

	/** The settings work even while the module is off, or no one could switch it on. */
	public static function can_admin_settings( $request ) {
		return self::guard( $request, true, false );
	}

	public static function register_routes() {
		$id = '(?P<id>\d+)';
		register_rest_route( self::ROUTE_NS, '/snags', array(
			array(
				'methods'             => 'GET',
				'callback'            => array( __CLASS__, 'get_list' ),
				'permission_callback' => array( __CLASS__, 'can_use' ),
			),
			array(
				'methods'             => 'POST',
				'callback'            => array( __CLASS__, 'post_create' ),
				'permission_callback' => array( __CLASS__, 'can_use' ),
			),
		) );
		register_rest_route( self::ROUTE_NS, '/snags/settings', array(
			array(
				'methods'             => 'GET',
				'callback'            => array( __CLASS__, 'get_settings' ),
				'permission_callback' => array( __CLASS__, 'can_admin_settings' ),
			),
			array(
				'methods'             => 'POST',
				'callback'            => array( __CLASS__, 'post_settings' ),
				'permission_callback' => array( __CLASS__, 'can_admin_settings' ),
			),
		) );
		register_rest_route( self::ROUTE_NS, '/snags/markdown', array(
			'methods'             => 'GET',
			'callback'            => array( __CLASS__, 'get_markdown' ),
			'permission_callback' => array( __CLASS__, 'can_admin' ),
		) );
		register_rest_route( self::ROUTE_NS, '/snags/' . $id . '/status', array(
			'methods'             => 'POST',
			'callback'            => array( __CLASS__, 'post_status' ),
			'permission_callback' => array( __CLASS__, 'can_admin' ),
		) );
		register_rest_route( self::ROUTE_NS, '/snags/' . $id . '/delete', array(
			'methods'             => 'POST',
			'callback'            => array( __CLASS__, 'post_delete' ),
			'permission_callback' => array( __CLASS__, 'can_use' ),
		) );
	}

	public static function get_list( $request ) {
		// The notes panel is an administrator's tool: an administrator viewing as the client (session 5) sees the list as
		// a client does (their own notes, no names, no ideas). Changing a status or deleting still checks the real capability.
		$admin = current_user_can( 'manage_options' ) && ! TWD_SK_Editor::simple_on();
		$notes = $admin
			? self::find( array( 'include_ideas' => true ) )
			: self::find( array( 'user_id' => get_current_user_id() ) );
		$items = array();
		foreach ( $notes as $n ) {
			$items[] = self::present( $n, $admin );
		}
		return array(
			'items'    => $items,
			'is_admin' => $admin,
			'new'      => $admin ? self::count_new() : 0,
			'statuses' => self::statuses(),
			'privacy'  => self::privacy_line(),
			'max'      => self::MAX_LEN,
			'version'  => defined( 'TWD_SK_VERSION' ) ? TWD_SK_VERSION : '',
		);
	}

	public static function post_create( $request ) {
		$user_id = get_current_user_id();
		$type    = 'snag';
		if ( 'idea' === $request->get_param( 'type' ) ) {
			if ( ! current_user_can( 'manage_options' ) ) {
				return self::error( 'twd_sk_forbidden', 'Only administrators can add ideas.', 403 );
			}
			$type = 'idea';
		}
		if ( 'snag' === $type ) {
			$blocked = self::allow_send( $user_id );
			if ( 'user' === $blocked ) {
				return self::error( 'twd_sk_rate_limited', 'You have sent a lot of notes in the last hour. Please try again later.', 429 );
			}
			if ( 'site' === $blocked ) {
				return self::error( 'twd_sk_rate_limited', 'Too many notes today. Please try again tomorrow.', 429 );
			}
		}
		$id = self::create( $user_id, (string) $request->get_param( 'message' ), (int) $request->get_param( 'page_id' ), $type );
		if ( is_wp_error( $id ) ) {
			return self::error( $id->get_error_code(), $id->get_error_message(), 400 );
		}
		if ( 'snag' === $type ) {
			self::notify( $id );
		}
		return array(
			'id'      => $id,
			'message' => 'idea' === $type ? 'Idea saved.' : 'Thank you. We have your note.',
		);
	}

	public static function get_markdown( $request ) {
		$notes = self::find( array( 'status' => 'new', 'include_ideas' => true ) );
		return array( 'markdown' => self::markdown( $notes ), 'count' => count( $notes ) );
	}

	public static function post_status( $request ) {
		$saved = self::set_status( (int) $request->get_param( 'id' ), (string) $request->get_param( 'status' ) );
		if ( is_wp_error( $saved ) ) {
			return self::error( $saved->get_error_code(), $saved->get_error_message(), 'twd_sk_snag_missing' === $saved->get_error_code() ? 404 : 400 );
		}
		return array( 'item' => self::present( $saved, true ) );
	}

	/** A client may delete only their own snag. An administrator may delete any note. */
	public static function post_delete( $request ) {
		$n = self::get( (int) $request->get_param( 'id' ) );
		if ( ! $n ) {
			return self::error( 'twd_sk_snag_missing', 'That note was not found.', 404 );
		}
		if ( ! current_user_can( 'manage_options' ) ) {
			if ( 'snag' !== $n['type'] || $n['user_id'] !== (int) get_current_user_id() ) {
				return self::error( 'twd_sk_forbidden', 'You can only delete your own notes.', 403 );
			}
		}
		self::delete( $n['id'] );
		return array( 'deleted' => $n['id'] );
	}

	public static function get_settings( $request ) {
		return self::settings();
	}

	public static function post_settings( $request ) {
		$saved = self::save_settings( array(
			'enabled'  => $request->get_param( 'enabled' ),
			'months'   => $request->get_param( 'months' ),
			'email_on' => $request->get_param( 'email_on' ),
			'email'    => $request->get_param( 'email' ),
		) );
		if ( is_wp_error( $saved ) ) {
			return self::error( $saved->get_error_code(), $saved->get_error_message(), 400 );
		}
		return $saved;
	}

	// -- The button and its script ------------------------------------------

	/** The page the person is on (0 when it is not a single page or post). */
	private static function viewed_id() {
		$id = TWD_SK_Page::viewed_page_id();
		if ( $id <= 0 && function_exists( 'is_singular' ) && is_singular() && function_exists( 'get_queried_object_id' ) ) {
			$id = (int) get_queried_object_id();
		}
		return max( 0, (int) $id );
	}

	/** True when the button and script should load on this request. */
	private static function wanted() {
		return self::enabled() && null !== TWD_SK_Editor::context();
	}

	public static function enqueue() {
		if ( ! self::wanted() ) {
			return;
		}
		$ver = defined( 'TWD_SK_VERSION' ) ? TWD_SK_VERSION : false;
		wp_register_script( self::HANDLE, TWD_SK_URL . 'assets/twd-site-kit-snags.js', array( TWD_SK_Editor::HANDLE ), $ver, true );
		$page_id = self::viewed_id();
		wp_localize_script( self::HANDLE, 'TWD_SK_SNAGS', array(
			'restUrl'   => esc_url_raw( rest_url( TWD_SK_REST::ROUTE_NS ) ),
			'nonce'     => wp_create_nonce( 'wp_rest' ),
			'pageId'    => $page_id,
			'pageTitle' => $page_id > 0 ? TWD_SK_Sanitizer::strip_dashes( wp_strip_all_tags( (string) get_the_title( $page_id ) ) ) : '',
			'version'   => defined( 'TWD_SK_VERSION' ) ? TWD_SK_VERSION : '',
			// The notes panel is an administrator's tool: never in the Simple editor (session 5), so Client view hides it too.
			'isAdmin'   => current_user_can( 'manage_options' ) && ! TWD_SK_Editor::simple_on(),
			'privacy'   => self::privacy_line(),
			'max'       => self::MAX_LEN,
		) );
		wp_enqueue_script( self::HANDLE );
	}

	/** The button. Hidden until the script runs; the pop-up itself is built by the script. */
	public static function print_root() {
		if ( ! self::wanted() ) {
			return;
		}
		echo '<div id="twd-sk-tellus" class="twd-sk-ed"' . ( TWD_SK_Editor::launchers_hidden() ? ' data-twd-sk-tools="off"' : '' ) . '><button type="button" id="twd-sk-tellus-open" class="twd-sk-ed__open twd-sk-ed__open--tellus" hidden aria-haspopup="dialog">'
			. esc_html__( 'Tell us', 'twd-site-kit' )
			. '</button></div>';
	}

	// -- Privacy tools --------------------------------------------------------

	public static function add_exporter( $exporters ) {
		$exporters['twd-site-kit-snags'] = array(
			'exporter_friendly_name' => 'Tell us notes',
			'callback'               => array( __CLASS__, 'export_for_email' ),
		);
		return $exporters;
	}

	public static function add_eraser( $erasers ) {
		$erasers['twd-site-kit-snags'] = array(
			'eraser_friendly_name' => 'Tell us notes',
			'callback'             => array( __CLASS__, 'erase_for_email' ),
		);
		return $erasers;
	}

	private static function user_id_for_email( $email ) {
		if ( ! is_string( $email ) || '' === $email || ! function_exists( 'get_user_by' ) ) {
			return 0;
		}
		$user = get_user_by( 'email', $email );
		return $user ? (int) $user->ID : 0;
	}

	public static function export_for_email( $email, $page = 1 ) {
		$data = array();
		$uid  = self::user_id_for_email( $email );
		if ( $uid > 0 ) {
			foreach ( self::find( array( 'user_id' => $uid ) ) as $n ) {
				$data[] = array(
					'group_id'    => 'twd-sk-snags',
					'group_label' => 'Tell us notes',
					'item_id'     => 'twd-sk-snag-' . $n['id'],
					'data'        => array(
						array( 'name' => 'Message', 'value' => $n['message'] ),
						array( 'name' => 'Page', 'value' => $n['page_title'] ),
						array( 'name' => 'Date', 'value' => $n['time'] > 0 ? gmdate( 'j M Y', $n['time'] ) : '' ),
						array( 'name' => 'Status', 'value' => $n['status'] ),
					),
				);
			}
		}
		return array( 'data' => $data, 'done' => true );
	}

	public static function erase_for_email( $email, $page = 1 ) {
		$removed = 0;
		$uid     = self::user_id_for_email( $email );
		if ( $uid > 0 ) {
			foreach ( self::find( array( 'user_id' => $uid ) ) as $n ) {
				if ( self::delete( $n['id'] ) ) {
					$removed++;
				}
			}
		}
		return array(
			'items_removed'  => $removed > 0,
			'items_retained' => false,
			'messages'       => array(),
			'done'           => true,
		);
	}
}
