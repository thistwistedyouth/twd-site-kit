<?php
/**
 * TWD_SK_Page: the [twd_page] shortcode.
 *
 * No attributes and no id. It renders the stored HTML of the page it sits on,
 * inside one wrapper class. Elementor keeps the header, footer and page setup.
 *
 * Only shortcodes the registry allows are run. Everything else in the stored
 * HTML is turned into inert text, so even hand-edited meta cannot run an
 * arbitrary shortcode (including [twd_page] itself).
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class TWD_SK_Page {

	const SHORTCODE     = 'twd_page';
	const WRAPPER_CLASS = 'twd-sk-page';

	private static $rendered = false;

	public static function init() {
		add_shortcode( self::SHORTCODE, array( __CLASS__, 'render' ) );
		// A page with a hero h1 does not also show the theme's own page title.
		add_filter( 'hello_elementor_page_title', array( __CLASS__, 'filter_theme_title' ) );
		add_filter( 'body_class', array( __CLASS__, 'filter_body_class' ) );
	}

	/**
	 * The page being viewed, if it is a single WordPress page. Archives and
	 * search results return 0 (their queried object id is not a post id).
	 */
	private static function viewed_page_id() {
		if ( function_exists( 'is_singular' ) && ! is_singular( 'page' ) ) {
			return 0;
		}
		$post_id = function_exists( 'get_queried_object_id' ) ? (int) get_queried_object_id() : 0;
		if ( $post_id <= 0 && function_exists( 'get_the_ID' ) ) {
			$post_id = (int) get_the_ID();
		}
		return $post_id;
	}

	/** True when the page's stored kit HTML contains an h1 (the hero title). */
	public static function page_has_h1( $post_id ) {
		$post_id = (int) $post_id;
		if ( $post_id <= 0 ) {
			return false;
		}
		$html = TWD_SK_Store::get_current( $post_id );
		return '' !== $html && 1 === preg_match( '/<h1[\s>]/i', $html );
	}

	/**
	 * Hello theme filter (hello_elementor_page_title): return false to hide the
	 * theme's page title. Only hidden when the kit page has its own h1, so a
	 * page never ends up with no h1 and never with two.
	 */
	public static function filter_theme_title( $show ) {
		if ( ! $show ) {
			return $show;
		}
		$post_id = self::viewed_page_id();
		return ( $post_id > 0 && self::page_has_h1( $post_id ) ) ? false : $show;
	}

	/**
	 * Body class for themes other than Hello: the stylesheet hides the title area
	 * under this class. Added only on a page whose kit HTML has its own h1.
	 */
	public static function filter_body_class( $classes ) {
		$post_id = self::viewed_page_id();
		if ( $post_id > 0 && self::page_has_h1( $post_id ) ) {
			$classes[] = 'twd-sk-has-h1';
		}
		return $classes;
	}

	/** True once a [twd_page] has rendered on this request. */
	public static function rendered_on_this_page() {
		return self::$rendered;
	}

	public static function render() {
		$post_id = function_exists( 'get_the_ID' ) ? (int) get_the_ID() : 0;
		if ( $post_id <= 0 && function_exists( 'get_queried_object_id' ) ) {
			$post_id = (int) get_queried_object_id();
		}
		if ( $post_id <= 0 ) {
			return '';
		}

		$html = TWD_SK_Store::get_current( $post_id );
		if ( '' === $html ) {
			return '';
		}

		self::$rendered = true;

		return '<div class="' . self::WRAPPER_CLASS . '">' . self::render_html( $html ) . '</div>';
	}

	/**
	 * Run the allowed shortcodes in stored HTML and neutralise the rest.
	 */
	public static function render_html( $html ) {
		$allowed = array_keys( TWD_SK_Registry::allowed_shortcodes() );
		$slots   = array();
		$salt    = substr( md5( uniqid( '', true ) ), 0, 8 );

		if ( $allowed ) {
			$names = implode( '|', array_map( 'preg_quote', $allowed ) );
			$html  = preg_replace_callback(
				'/\[(?:' . $names . ')(?:\s[^\[\]]*)?\]/',
				function ( $m ) use ( &$slots, $salt ) {
					$key           = '%%twd_sk_' . $salt . '_' . count( $slots ) . '%%';
					$slots[ $key ] = $m[0];
					return $key;
				},
				$html
			);
		}

		// Every remaining bracket becomes an entity, so do_shortcode() finds
		// nothing else to run anywhere in the HTML, attributes included.
		$html = str_replace( array( '[', ']' ), array( '&#91;', '&#93;' ), $html );

		foreach ( $slots as $key => $shortcode ) {
			$html = str_replace( $key, do_shortcode( $shortcode ), $html );
		}

		return $html;
	}
}
