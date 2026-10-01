<?php
/**
 * TWD_SK_Safe: the safe-mode switch.
 *
 * Safe mode turns off the modules added in 0.4.0 (Site tab, header and footer, site profile,
 * starter pages, SEO fields, search mirror, structured data, image attributes) and keeps
 * everything from 0.3.1 and before working: the Edit tab, preview, apply, history, the page
 * template and the Pages tab, packs, the updater and the WP-CLI commands.
 *
 * The header and footer shortcodes stay registered in safe mode and print a minimal,
 * plain version, so a Theme Builder header never shows a raw shortcode.
 *
 * Two ways to switch it on, so it can be done even when wp-admin is unreachable:
 *   define( 'TWD_SK_SAFE_MODE', true );   in wp-config.php
 *   wp twd-sk safe-mode on                or: wp option update twd_sk_safe_mode 1
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class TWD_SK_Safe {

	const OPTION = 'twd_sk_safe_mode';

	/** True when safe mode is on, by constant or by option. */
	public static function on() {
		if ( defined( 'TWD_SK_SAFE_MODE' ) && TWD_SK_SAFE_MODE ) {
			return true;
		}
		$value = function_exists( 'get_option' ) ? get_option( self::OPTION, '' ) : '';
		return true === $value || 1 === $value || '1' === $value || 'on' === $value;
	}

	/** 'constant', 'option' or 'off'. */
	public static function source() {
		if ( defined( 'TWD_SK_SAFE_MODE' ) && TWD_SK_SAFE_MODE ) {
			return 'constant';
		}
		return self::on() ? 'option' : 'off';
	}

	/** Switch the option on or off. The constant, if set, still wins. */
	public static function set( $on ) {
		return update_option( self::OPTION, $on ? '1' : '0' );
	}

	/** A notice for administrators, so nobody forgets it is on. */
	public static function print_notice() {
		if ( ! self::on() || ! current_user_can( 'manage_options' ) ) {
			return;
		}
		$how = 'constant' === self::source()
			? 'It is switched on by TWD_SK_SAFE_MODE in wp-config.php. Remove that line to switch it off.'
			: 'Switch it off with: wp twd-sk safe-mode off';
		echo '<div class="notice notice-warning"><p><strong>TWD Site Kit safe mode is on.</strong> The newest features are switched off (the Site tab, header and footer, starters, SEO fields and image changes). Pages and the Edit with AI tab still work. ' . esc_html( $how ) . '</p></div>';
	}

	public static function init() {
		add_action( 'admin_notices', array( __CLASS__, 'print_notice' ) );
	}
}
