<?php
/**
 * TWD_SK_Enquiry_Settings: the enquiry form's own settings (0.22.0), kept on this site only.
 *
 * Where enquiries go (and an optional copy), the thank-you wording, the honeypot, the speed check, the limit per
 * visitor, and an optional Cloudflare Turnstile check. The Turnstile site key and secret key are typed in by an
 * administrator in the editor; they are stored in this site's options, never in a page, a repository or a file,
 * and the secret key is never sent back to the browser (the panel only learns that one is set).
 *
 * Every value is checked and cleaned on the way in. Nothing here can run script or print a key.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class TWD_SK_Enquiry_Settings {

	const OPTION = 'twd_sk_enquiry_settings';
	const VERIFY = 'https://challenges.cloudflare.com/turnstile/v0/siteverify';
	const SCRIPT = 'https://challenges.cloudflare.com/turnstile/v0/api.js';

	/** What a site that never opened the panel has. These are exactly the behaviours before 0.22.0. */
	public static function defaults() {
		return array(
			'to'                => '',
			'cc'                => '',
			'thanks'            => '',
			'honeypot'          => true,
			'min_seconds'       => 3,
			'per_visitor'       => 5,
			'captcha'           => 'none',
			'turnstile_site'    => '',
			'turnstile_secret'  => '',
		);
	}

	/** The saved settings merged over the defaults. Includes the secret key: for the server only. */
	public static function get() {
		$saved = get_option( self::OPTION, array() );
		$saved = is_array( $saved ) ? $saved : array();
		$out   = self::defaults();
		foreach ( $out as $k => $v ) {
			if ( array_key_exists( $k, $saved ) ) {
				$out[ $k ] = $saved[ $k ];
			}
		}
		return self::clean( $out, false );
	}

	/** The settings as the browser may see them: no secret key, only whether one is set. */
	public static function for_panel() {
		$s               = self::get();
		$s['secret_set'] = '' !== $s['turnstile_secret'];
		$s['active']     = self::turnstile_on( $s );
		unset( $s['turnstile_secret'] );
		return $s;
	}

	/** True when Turnstile is chosen and both keys are present. */
	public static function turnstile_on( $s = null ) {
		$s = null === $s ? self::get() : $s;
		return 'turnstile' === $s['captcha'] && '' !== $s['turnstile_site'] && '' !== $s['turnstile_secret'];
	}

	/**
	 * Check a set of values. Returns { ok, errors[], settings }. Nothing is saved here.
	 * $strict is true for what a person typed (a bad value is an error), false for what was stored (a bad value is dropped).
	 */
	public static function check( $in ) {
		$in   = is_array( $in ) ? $in : array();
		$errs = array();
		$out  = self::clean( array_merge( self::defaults(), $in ), true, $errs );
		return array( 'ok' => ! $errs, 'errors' => $errs, 'settings' => $out );
	}

	private static function clean( $v, $strict, &$errs = array() ) {
		$d   = self::defaults();
		$out = $d;
		foreach ( array( 'to', 'cc' ) as $k ) {
			$e = isset( $v[ $k ] ) && is_string( $v[ $k ] ) ? trim( $v[ $k ] ) : '';
			if ( '' !== $e && ( ! is_email( $e ) || preg_match( '/[\r\n,;<>"]/', $e ) ) ) {
				if ( $strict ) {
					$errs[] = ( 'to' === $k ? 'The address enquiries go to' : 'The copy address' ) . ' does not look like an email address.';
				}
				$e = '';
			}
			$out[ $k ] = $e;
		}
		$t = isset( $v['thanks'] ) && is_string( $v['thanks'] ) ? wp_strip_all_tags( $v['thanks'] ) : '';
		$t = trim( preg_replace( '/\s+/u', ' ', $t ) );
		if ( strlen( $t ) > 300 ) {
			if ( $strict ) {
				$errs[] = 'The thank-you message is too long (300 characters at most).';
			}
			$t = '';
		}
		$out['thanks']   = $t;
		$out['honeypot'] = ! array_key_exists( 'honeypot', $v ) || ! empty( $v['honeypot'] );
		foreach ( array( 'min_seconds' => array( 0, 30, 'The speed check' ), 'per_visitor' => array( 1, 30, 'The limit per visitor' ) ) as $k => $r ) {
			$n = isset( $v[ $k ] ) && is_numeric( $v[ $k ] ) ? (int) $v[ $k ] : $d[ $k ];
			if ( $n < $r[0] || $n > $r[1] ) {
				if ( $strict ) {
					$errs[] = $r[2] . ' must be between ' . $r[0] . ' and ' . $r[1] . '.';
				}
				$n = $d[ $k ];
			}
			$out[ $k ] = $n;
		}
		$out['captcha'] = isset( $v['captcha'] ) && 'turnstile' === $v['captcha'] ? 'turnstile' : 'none';
		foreach ( array( 'turnstile_site' => 'The Turnstile site key', 'turnstile_secret' => 'The Turnstile secret key' ) as $k => $label ) {
			$key = isset( $v[ $k ] ) && is_string( $v[ $k ] ) ? trim( $v[ $k ] ) : '';
			if ( '' !== $key && 1 !== preg_match( '/^[0-9A-Za-z_\-]{10,80}$/', $key ) ) {
				if ( $strict ) {
					$errs[] = $label . ' does not look right. Copy it again from Cloudflare, with nothing before or after it.';
				}
				$key = '';
			}
			$out[ $k ] = $key;
		}
		return $out;
	}

	/**
	 * Save what an administrator typed. `turnstile_secret` left out or empty keeps the saved one; `clear_secret` removes it.
	 * Returns the settings for the panel, or a WP_Error naming the first problems.
	 */
	public static function save( $in ) {
		$in  = is_array( $in ) ? $in : array();
		$cur = self::get();
		if ( ! empty( $in['clear_secret'] ) ) {
			$in['turnstile_secret'] = '';
			$cur['turnstile_secret'] = '';
		} elseif ( ! isset( $in['turnstile_secret'] ) || '' === trim( (string) $in['turnstile_secret'] ) ) {
			$in['turnstile_secret'] = $cur['turnstile_secret'];
		}
		unset( $in['clear_secret'] );
		$r = self::check( array_merge( $cur, $in ) );
		if ( ! $r['ok'] ) {
			return new WP_Error( 'twd_sk_bad_input', implode( ' ', array_slice( $r['errors'], 0, 3 ) ) );
		}
		$s = $r['settings'];
		if ( 'turnstile' === $s['captcha'] && ( '' === $s['turnstile_site'] || '' === $s['turnstile_secret'] ) ) {
			return new WP_Error( 'twd_sk_bad_input', 'To use Turnstile, enter both the site key and the secret key from Cloudflare.' );
		}
		update_option( self::OPTION, $s, false );
		return self::for_panel();
	}

	/**
	 * Ask Cloudflare whether a visitor's Turnstile answer is good. Returns 'ok', 'fail' (refuse), or 'unreachable'
	 * (Cloudflare could not be asked: the caller lets the message through and counts it, so a real person is never
	 * locked out by an outage). A filter lets tests answer without the network.
	 */
	public static function verify( $token, $ip ) {
		$token = is_string( $token ) ? trim( $token ) : '';
		if ( '' === $token || strlen( $token ) > 2048 ) {
			return 'fail';
		}
		$pre = apply_filters( 'twd_sk_turnstile_verify', null, $token, $ip );
		if ( null !== $pre ) {
			return in_array( $pre, array( 'ok', 'fail', 'unreachable' ), true ) ? $pre : 'fail';
		}
		if ( ! function_exists( 'wp_remote_post' ) ) {
			return 'unreachable';
		}
		$s    = self::get();
		$body = array( 'secret' => $s['turnstile_secret'], 'response' => $token );
		if ( is_string( $ip ) && '' !== $ip ) {
			$body['remoteip'] = $ip;
		}
		$res = wp_remote_post( self::VERIFY, array( 'timeout' => 8, 'body' => $body ) );
		if ( is_wp_error( $res ) || 200 !== (int) wp_remote_retrieve_response_code( $res ) ) {
			return 'unreachable';
		}
		$data = json_decode( (string) wp_remote_retrieve_body( $res ), true );
		if ( ! is_array( $data ) ) {
			return 'unreachable';
		}
		return ! empty( $data['success'] ) ? 'ok' : 'fail';
	}
}
