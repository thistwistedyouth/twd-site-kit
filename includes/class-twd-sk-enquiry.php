<?php
/**
 * TWD_SK_Enquiry: the [twd_enquiry] form (0.8.1). Name, email, phone (optional), message and consent,
 * sent by email to the site's own address. Nothing is stored: no submissions table, no copies.
 *
 * The form is rendered by this class at display time; page HTML only ever holds the shortcode, so
 * the sanitiser's ban on forms still stands for everything a person or an AI writes.
 *
 * Spam and abuse protection, without JavaScript and without anything a page cache would break:
 *  - a signed token (time and page, HMAC with the site's salt): refused if it does not match, if the
 *    form was sent within 3 seconds of the page being built, or if the page is older than 30 days;
 *  - a hidden honeypot field that people never fill in;
 *  - a limit per visitor (hashed address) and a daily limit for the whole site;
 *  - every field is checked and cleaned; line breaks never reach an email header.
 *
 * In safe mode the shortcode prints a plain sentence instead of the form and the handler refuses.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class TWD_SK_Enquiry {

	const SHORTCODE   = 'twd_enquiry';
	const ACTION      = 'twd_sk_enquiry';
	const MIN_SECONDS = 3;
	const MAX_AGE     = 30 * 24 * 3600;
	const PER_VISITOR = 5;    // per hour
	const PER_DAY     = 50;   // for the whole site
	const STATS       = 'twd_sk_enquiry_stats';
	const MAX_MESSAGE = 3000;

	private static $count = 0;

	public static function init() {
		add_shortcode( self::SHORTCODE, array( __CLASS__, 'render' ) );
		add_action( 'admin_post_nopriv_' . self::ACTION, array( __CLASS__, 'handle' ) );
		add_action( 'admin_post_' . self::ACTION, array( __CLASS__, 'handle' ) );
	}

	/** Plain-language messages for each result, keyed by the code put in the address. */
	public static function messages() {
		return array(
			'sent'     => 'Thank you. Your message has been sent, and you will hear back soon.',
			'missing'  => 'Please fill in your name, a valid email address and your message, and tick the box to agree. Then send it again.',
			'consent'  => 'Please tick the box to agree to your details being used to reply to you.',
			'expired'  => 'The form had been open for a long time. Please reload the page and send your message again.',
			'too_fast' => 'That was sent very quickly. Please wait a moment and send it again.',
			'limit'    => 'Too many messages have been sent from here. Please try again later, or use the contact details on this page.',
			'failed'   => 'Sorry, your message could not be sent. Please use the contact details on this page instead.',
			'off'      => 'The form is switched off at the moment. Please use the contact details on this page.',
		);
	}

	// -- The token ---------------------------------------------------------------------

	public static function token( $page_id, $time = null ) {
		$time = null === $time ? time() : (int) $time;
		return $time . '.' . self::sign( $time, (int) $page_id );
	}

	private static function sign( $time, $page_id ) {
		$key = function_exists( 'wp_salt' ) ? wp_salt( 'nonce' ) : 'twd-sk';
		return substr( hash_hmac( 'sha256', 'twd_sk_enquiry|' . (int) $time . '|' . (int) $page_id, $key ), 0, 32 );
	}

	/** @return string '' when the token is good, otherwise a result code. */
	public static function check_token( $token, $page_id, $now = null ) {
		$now = null === $now ? time() : (int) $now;
		if ( ! is_string( $token ) || 1 !== preg_match( '/^(\d{9,11})\.([a-f0-9]{32})$/', $token, $m ) ) {
			return 'expired';
		}
		if ( ! hash_equals( self::sign( (int) $m[1], (int) $page_id ), $m[2] ) ) {
			return 'expired';
		}
		$age = $now - (int) $m[1];
		if ( $age < self::MIN_SECONDS ) {
			return 'too_fast';
		}
		if ( $age > self::MAX_AGE ) {
			return 'expired';
		}
		return '';
	}

	// -- The form ----------------------------------------------------------------------

	private static function page_id() {
		$id = function_exists( 'get_the_ID' ) ? (int) get_the_ID() : 0;
		if ( $id <= 0 && function_exists( 'get_queried_object_id' ) ) {
			$id = (int) get_queried_object_id();
		}
		return $id;
	}

	private static function result_code() {
		if ( isset( $_GET['twd_sk_sent'] ) ) {
			return 'sent';
		}
		$code = isset( $_GET['twd_sk_error'] ) ? (string) wp_unslash( $_GET['twd_sk_error'] ) : '';
		return isset( self::messages()[ $code ] ) && 'sent' !== $code ? $code : '';
	}

	public static function render() {
		if ( TWD_SK_Safe::on() ) {
			return '<p class="twd-sk-form__status">' . esc_html( self::messages()['off'] ) . '</p>';
		}
		$page_id = self::page_id();
		if ( $page_id <= 0 ) {
			return '';
		}
		self::$count++;
		$n      = self::$count;
		$id     = 'twd-sk-enquiry' . ( 1 === $n ? '' : '-' . $n );
		$f      = function ( $name ) use ( $n ) {
			return 'twd-sk-f' . $n . '-' . $name;
		};
		$out    = array();
		$out[]  = '<form class="twd-sk-form" id="' . esc_attr( $id ) . '" method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '">';
		$code   = 1 === $n ? self::result_code() : '';
		if ( '' !== $code ) {
			$out[] = '<p class="twd-sk-form__status twd-sk-form__status--' . ( 'sent' === $code ? 'ok' : 'error' ) . '" role="' . ( 'sent' === $code ? 'status' : 'alert' ) . '">' . esc_html( self::messages()[ $code ] ) . '</p>';
		}
		$out[] = '<input type="hidden" name="action" value="' . esc_attr( self::ACTION ) . '">';
		$out[] = '<input type="hidden" name="twd_sk_page" value="' . (int) $page_id . '">';
		$out[] = '<input type="hidden" name="twd_sk_form" value="' . esc_attr( $id ) . '">';
		$out[] = '<input type="hidden" name="twd_sk_t" value="' . esc_attr( self::token( $page_id ) ) . '">';
		$out[] = '<div class="twd-sk-form__hp" aria-hidden="true"><label for="' . esc_attr( $f( 'website' ) ) . '">Leave this empty</label><input type="text" id="' . esc_attr( $f( 'website' ) ) . '" name="twd_sk_website" tabindex="-1" autocomplete="off"></div>';
		$out[] = self::field( $f( 'name' ), 'twd_sk_name', 'Your name', 'text', true, 'name', 100 );
		$out[] = self::field( $f( 'email' ), 'twd_sk_email', 'Your email address', 'email', true, 'email', 120 );
		$out[] = self::field( $f( 'phone' ), 'twd_sk_phone', 'Your phone number (optional)', 'tel', false, 'tel', 40 );
		$out[] = '<div class="twd-sk-form__field"><label class="twd-sk-form__label" for="' . esc_attr( $f( 'message' ) ) . '">Your message</label><textarea class="twd-sk-form__textarea" id="' . esc_attr( $f( 'message' ) ) . '" name="twd_sk_message" rows="6" maxlength="' . self::MAX_MESSAGE . '" required></textarea></div>';
		$privacy = function_exists( 'get_privacy_policy_url' ) ? (string) get_privacy_policy_url() : '';
		$out[]   = '<div class="twd-sk-form__field twd-sk-form__check"><input type="checkbox" id="' . esc_attr( $f( 'consent' ) ) . '" name="twd_sk_consent" value="1" required><label for="' . esc_attr( $f( 'consent' ) ) . '">I agree to my details being used to reply to me.' . ( '' !== $privacy ? ' <a href="' . esc_url( $privacy ) . '">Privacy policy</a>' : '' ) . '</label></div>';
		$out[]   = '<div class="twd-sk-form__actions"><button type="submit" class="twd-sk-btn twd-sk-btn--primary">Send message</button></div>';
		$out[]   = '<p class="twd-sk-form__help">' . esc_html( TWD_SK_Mode::w( 'form_help' ) ) . '</p>';
		$out[]   = '</form>';
		return implode( "\n", $out );
	}

	private static function field( $id, $name, $label, $type, $required, $autocomplete, $max ) {
		return '<div class="twd-sk-form__field"><label class="twd-sk-form__label" for="' . esc_attr( $id ) . '">' . esc_html( $label ) . '</label>'
			. '<input class="twd-sk-form__input" type="' . esc_attr( $type ) . '" id="' . esc_attr( $id ) . '" name="' . esc_attr( $name ) . '" autocomplete="' . esc_attr( $autocomplete ) . '" maxlength="' . (int) $max . '"' . ( $required ? ' required' : '' ) . '></div>';
	}

	// -- Sending -------------------------------------------------------------------------

	/** One line of plain text: no tags, no line breaks, no control characters, capped. */
	private static function line( $value, $max ) {
		$v = is_string( $value ) ? wp_unslash( $value ) : '';
		$v = preg_replace( '/[\x00-\x1F\x7F]+/', ' ', wp_strip_all_tags( $v ) );
		$v = trim( preg_replace( '/\s+/u', ' ', $v ) );
		return function_exists( 'mb_substr' ) ? mb_substr( $v, 0, $max, 'UTF-8' ) : substr( $v, 0, $max );
	}

	/**
	 * Check a submission. Pure: no sending, no redirect.
	 *
	 * @param array $post The posted fields.
	 * @param int   $now
	 * @return array { code: '' when good or a result code, fields }
	 */
	public static function check( $post, $now = null ) {
		$post    = is_array( $post ) ? $post : array();
		$page_id = isset( $post['twd_sk_page'] ) ? (int) $post['twd_sk_page'] : 0;
		$fields  = array(
			'page'    => $page_id,
			'name'    => self::line( isset( $post['twd_sk_name'] ) ? $post['twd_sk_name'] : '', 100 ),
			'email'   => self::line( isset( $post['twd_sk_email'] ) ? $post['twd_sk_email'] : '', 120 ),
			'phone'   => self::line( isset( $post['twd_sk_phone'] ) ? $post['twd_sk_phone'] : '', 40 ),
			'message' => '',
		);
		$msg = isset( $post['twd_sk_message'] ) && is_string( $post['twd_sk_message'] ) ? wp_unslash( $post['twd_sk_message'] ) : '';
		$msg = str_replace( array( "\r\n", "\r" ), "\n", wp_strip_all_tags( $msg ) );
		$msg = trim( preg_replace( '/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F]/', '', $msg ) );
		$fields['message'] = function_exists( 'mb_substr' ) ? mb_substr( $msg, 0, self::MAX_MESSAGE, 'UTF-8' ) : substr( $msg, 0, self::MAX_MESSAGE );

		// A filled honeypot is treated as sent, so a robot learns nothing.
		if ( isset( $post['twd_sk_website'] ) && '' !== trim( (string) $post['twd_sk_website'] ) ) {
			return array( 'code' => 'trap', 'fields' => $fields );
		}
		$bad = self::check_token( isset( $post['twd_sk_t'] ) ? (string) $post['twd_sk_t'] : '', $page_id, $now );
		if ( '' !== $bad ) {
			return array( 'code' => $bad, 'fields' => $fields );
		}
		if ( '' === $fields['name'] || '' === $fields['message'] || ! is_email( $fields['email'] ) ) {
			return array( 'code' => 'missing', 'fields' => $fields );
		}
		if ( '' !== $fields['phone'] && 1 !== preg_match( '/^[0-9 +()\-.]{5,40}$/', $fields['phone'] ) ) {
			$fields['phone'] = '';
		}
		if ( empty( $post['twd_sk_consent'] ) ) {
			return array( 'code' => 'consent', 'fields' => $fields );
		}
		return array( 'code' => '', 'fields' => $fields );
	}

	/** True when this visitor and the whole site are under their limits. Counts this attempt. */
	public static function within_limits( $ip ) {
		$day   = 'twd_sk_enq_day';
		$who   = 'twd_sk_enq_' . substr( hash( 'sha256', (string) $ip . ( function_exists( 'wp_salt' ) ? wp_salt( 'nonce' ) : '' ) ), 0, 20 );
		$count = (int) get_transient( $who );
		$all   = (int) get_transient( $day );
		if ( $count >= self::PER_VISITOR || $all >= self::PER_DAY ) {
			return false;
		}
		set_transient( $who, $count + 1, 3600 );
		set_transient( $day, $all + 1, 86400 );
		return true;
	}

	/** Where enquiries go: the site details email when it is real, otherwise the WordPress admin email. */
	public static function recipient() {
		$p     = TWD_SK_Profile::get();
		$email = is_string( $p['email'] ) ? trim( $p['email'] ) : '';
		if ( '' === $email || false !== stripos( $email, 'example.com' ) || ! is_email( $email ) ) {
			$email = (string) get_option( 'admin_email', '' );
		}
		$email = (string) apply_filters( 'twd_sk_enquiry_recipient', $email );
		return is_email( $email ) ? $email : '';
	}

	/** Send a checked enquiry. @return bool */
	public static function send( $fields ) {
		$to = self::recipient();
		if ( '' === $to ) {
			return false;
		}
		$title   = function_exists( 'get_the_title' ) ? self::line( get_the_title( $fields['page'] ), 120 ) : '';
		$subject = 'Website enquiry from ' . $fields['name'];
		$body    = array(
			'A new enquiry from the website' . ( '' !== $title ? ' (page: ' . $title . ')' : '' ) . '.',
			'',
			'Name: ' . $fields['name'],
			'Email: ' . $fields['email'],
			'Phone: ' . ( '' !== $fields['phone'] ? $fields['phone'] : 'not given' ),
			'',
			'Message:',
			$fields['message'],
			'',
			'They agreed to their details being used to reply to them. This message is not stored on the website.',
		);
		$name    = str_replace( array( '"', '<', '>', ',', ';' ), '', $fields['name'] );
		$headers = array( 'Reply-To: "' . $name . '" <' . $fields['email'] . '>' );
		return (bool) wp_mail( $to, $subject, implode( "\n", $body ), $headers );
	}

	private static function stat( $key ) {
		$s         = get_option( self::STATS, array() );
		$s         = is_array( $s ) ? $s : array();
		$s[ $key ] = isset( $s[ $key ] ) ? (int) $s[ $key ] + 1 : 1;
		$s['last'] = gmdate( 'Y-m-d H:i' );
		update_option( self::STATS, $s, false );
	}

	/** Counts only (sent, failed, refused), never a name or a message. For the site report. */
	public static function stats() {
		$s = get_option( self::STATS, array() );
		return is_array( $s ) ? $s : array();
	}

	/**
	 * The whole handling of one post. Returns where to send the person back to. Separate from
	 * handle() so it can be tested without exiting.
	 */
	public static function process( $post, $ip, $now = null ) {
		$page_id = isset( $post['twd_sk_page'] ) ? (int) $post['twd_sk_page'] : 0;
		$form    = isset( $post['twd_sk_form'] ) && is_string( $post['twd_sk_form'] ) && 1 === preg_match( '/^twd-sk-enquiry(-\d{1,2})?$/', $post['twd_sk_form'] ) ? $post['twd_sk_form'] : 'twd-sk-enquiry';
		$base    = $page_id > 0 && get_post( $page_id ) ? (string) get_permalink( $page_id ) : (string) home_url( '/' );
		if ( TWD_SK_Safe::on() ) {
			$code = 'off';
		} else {
			$r    = self::check( $post, $now );
			$code = $r['code'];
			if ( 'trap' === $code ) {
				self::stat( 'refused' );
				$code = 'sent';
			} elseif ( '' === $code ) {
				if ( ! self::within_limits( $ip ) ) {
					$code = 'limit';
				} elseif ( self::send( $r['fields'] ) ) {
					self::stat( 'sent' );
					$code = 'sent';
				} else {
					self::stat( 'failed' );
					$code = 'failed';
				}
			} else {
				self::stat( 'refused' );
			}
		}
		$args = 'sent' === $code ? array( 'twd_sk_sent' => '1' ) : array( 'twd_sk_error' => $code );
		return add_query_arg( $args, $base ) . '#' . $form;
	}

	/** For tests: forget how many forms this request has printed. */
	public static function reset() {
		self::$count = 0;
	}

	public static function handle() {
		$ip = isset( $_SERVER['REMOTE_ADDR'] ) ? (string) $_SERVER['REMOTE_ADDR'] : '';
		wp_safe_redirect( self::process( $_POST, $ip ) );
		exit;
	}
}
