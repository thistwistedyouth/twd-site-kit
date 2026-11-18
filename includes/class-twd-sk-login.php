<?php
/**
 * TWD_SK_Login: a branded sign-in page (0.22.0, stage 1 of the client and editor welcome).
 *
 * Restyle only. WordPress's own login form, nonce, password reset and any two-step plugin are untouched.
 * Off by default (option twd_sk_login = yes turns it on), off in safe mode (started from TWD_SK_Modules).
 * It prints a small inline stylesheet on wp-login.php only, from the active style's tokens, so a visitor's
 * page is never heavier. The logo and name come from Site details.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class TWD_SK_Login {

	const OPTION = 'twd_sk_login';

	public static function init() {
		if ( ! self::on() ) {
			return;
		}
		add_action( 'login_enqueue_scripts', array( __CLASS__, 'print_styles' ) );
		add_filter( 'login_headerurl', array( __CLASS__, 'header_url' ) );
		add_filter( 'login_headertext', array( __CLASS__, 'header_text' ) );
		add_filter( 'login_message', array( __CLASS__, 'message' ) );
		add_filter( 'login_body_class', array( __CLASS__, 'body_class' ) );
	}

	/** Is the branded sign-in page switched on? Never in safe mode. */
	public static function on() {
		if ( TWD_SK_Safe::on() ) {
			return false;
		}
		return 'yes' === get_option( self::OPTION, 'no' );
	}

	public static function set( $on ) {
		update_option( self::OPTION, $on ? 'yes' : 'no' );
		return (bool) $on;
	}

	public static function body_class( $classes ) {
		$classes   = is_array( $classes ) ? $classes : array();
		$classes[] = 'twd-sk-login';
		return $classes;
	}

	public static function header_url() {
		return home_url( '/' );
	}

	/** The site's own name, from Site details when set (a leftover marker is never shown). */
	public static function site_name() {
		$p    = TWD_SK_Profile::get();
		$name = isset( $p['site_name'] ) ? trim( (string) $p['site_name'] ) : '';
		if ( '' === $name || false !== strpos( $name, '[PLACEHOLDER' ) ) {
			$name = (string) get_bloginfo( 'name' );
		}
		return $name;
	}

	public static function header_text() {
		return self::site_name();
	}

	/** The friendly line above the form, and a way back to the site below it. */
	public static function message( $message ) {
		$html  = '<p class="twd-sk-login__welcome">' . esc_html( TWD_SK_Mode::w( 'login_welcome' ) ) . '</p>';
		$html .= '<p class="twd-sk-login__back"><a href="' . esc_url( home_url( '/' ) ) . '">' . esc_html( TWD_SK_Mode::w( 'login_back' ) ) . '</a></p>';
		return $html . $message;
	}

	/** The logo picture's address (medium size), or an empty string. */
	public static function logo_url() {
		$p  = TWD_SK_Profile::get();
		$id = isset( $p['logo_id'] ) ? (int) $p['logo_id'] : 0;
		if ( $id < 1 ) {
			return '';
		}
		$url = wp_get_attachment_image_url( $id, 'medium' );
		return is_string( $url ) ? $url : '';
	}

	/** The CSS: the active tokens, the fonts the style uses, and the rules. Pure, so a test can read it. */
	public static function css( $base_url = '' ) {
		$tokens = TWD_SK_Packs::active_tokens();
		$css    = TWD_SK_Packs::tokens_css( $tokens );
		if ( '' !== $base_url ) {
			$css = TWD_SK_Packs::font_face_css( $tokens, $base_url ) . $css;
		}
		$css .= self::rules();
		$logo = self::logo_url();
		if ( '' !== $logo ) {
			$css .= 'body.twd-sk-login #login h1 a{background-image:url("' . str_replace( array( '"', '\\', "\n", "\r" ), '', esc_url_raw( $logo ) ) . '")!important;background-size:contain!important;background-position:center!important;width:100%!important;height:84px!important;}';
		} else {
			// No logo: the name is shown as words instead of WordPress's own mark.
			$css .= 'body.twd-sk-login #login h1 a{background-image:none!important;text-indent:0!important;width:auto!important;height:auto!important;font-size:28px!important;line-height:1.2!important;color:var(--twd-site-color-heading)!important;font-family:var(--twd-site-font-heading)!important;}';
		}
		return $css;
	}

	private static function rules() {
		$v = 'var(--twd-site-';
		return 'body.twd-sk-login{background:' . $v . 'color-bg)!important;color:' . $v . 'color-body)!important;font-family:' . $v . 'font-body)!important;}'
			. 'body.twd-sk-login #login{padding-top:5%;}'
			. 'body.twd-sk-login #login h1 a{outline-offset:4px;}'
			. 'body.twd-sk-login .twd-sk-login__welcome{text-align:center;font-size:16px;line-height:1.5;margin:0 0 18px;color:' . $v . 'color-title);}'
			. 'body.twd-sk-login .twd-sk-login__back{text-align:center;margin:-8px 0 18px;font-size:14px;}'
			. 'body.twd-sk-login .twd-sk-login__back a,body.twd-sk-login #nav a,body.twd-sk-login #backtoblog a{color:' . $v . 'color-accent-text)!important;}'
			. 'body.twd-sk-login .login form{background:' . $v . 'color-surface)!important;border:1px solid ' . $v . 'color-border)!important;border-radius:' . $v . 'radius-card)!important;box-shadow:none!important;padding:26px 24px!important;}'
			. 'body.twd-sk-login .login form label{color:' . $v . 'color-title)!important;font-size:14px;}'
			. 'body.twd-sk-login .login form input[type=text],body.twd-sk-login .login form input[type=password],body.twd-sk-login .login form input[type=email]{border:1px solid ' . $v . 'color-border)!important;border-radius:' . $v . 'radius-field)!important;font-size:16px;padding:6px 10px;}'
			. 'body.twd-sk-login .login form input:focus{border-color:' . $v . 'color-primary)!important;box-shadow:0 0 0 1px ' . $v . 'color-primary)!important;outline:2px solid transparent;}'
			. 'body.twd-sk-login .login .button-primary{background:' . $v . 'color-primary)!important;border:0!important;color:' . $v . 'color-on-primary)!important;border-radius:' . $v . 'radius-btn)!important;font-size:16px!important;padding:4px 22px!important;min-height:40px;text-shadow:none!important;box-shadow:none!important;}'
			. 'body.twd-sk-login .login .button-primary:hover,body.twd-sk-login .login .button-primary:focus{background:' . $v . 'color-primary-hover)!important;}'
			. 'body.twd-sk-login .login .message,body.twd-sk-login .login .notice,body.twd-sk-login #login_error{border-radius:' . $v . 'radius-field);}'
			. 'body.twd-sk-login #nav,body.twd-sk-login #backtoblog{text-align:center;font-size:14px;}'
			. 'body.twd-sk-login .privacy-policy-page-link{text-align:center;}';
	}

	public static function print_styles() {
		$base = defined( 'TWD_SK_URL' ) ? TWD_SK_URL . 'assets/fonts/' : '';
		echo '<style id="twd-site-kit-login">' . self::css( $base ) . '</style>' . "\n"; // phpcs:ignore -- built from validated tokens only
	}
}
