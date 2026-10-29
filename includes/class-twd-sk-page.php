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
	public static function viewed_page_id() {
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
		$html = self::current_html( $post_id );
		return '' !== $html && 1 === preg_match( '/<h1[\s>]/i', $html );
	}

	/**
	 * The HTML to show for a page: the stored HTML, unless a filter swaps it. The
	 * only user of the filter is a validated editor preview (TWD_SK_Preview).
	 */
	public static function current_html( $post_id ) {
		return (string) apply_filters( 'twd_sk_page_html', TWD_SK_Store::get_current( (int) $post_id ), (int) $post_id );
	}

	/**
	 * True for a page the kit renders: it has stored kit HTML, uses the kit template, or holds the
	 * [twd_page] shortcode in its content or in Elementor's data.
	 */
	public static function is_kit_page( $post_id ) {
		$post_id = (int) $post_id;
		if ( $post_id <= 0 ) {
			return false;
		}
		if ( '' !== TWD_SK_Store::get_current( $post_id ) || TWD_SK_Template::uses_template( $post_id ) ) {
			return true;
		}
		$content = (string) get_post_field( 'post_content', $post_id );
		$data    = get_post_meta( $post_id, '_elementor_data', true );
		if ( is_string( $data ) ) {
			$content .= $data;
		}
		return false !== strpos( $content, '[' . self::SHORTCODE );
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

		$html = self::current_html( $post_id );
		if ( '' === $html ) {
			return '';
		}

		self::$rendered = true;
		self::enqueue_extras( $html );
		// For someone who can edit this page: number the words and pictures they may change in place.
		if ( class_exists( 'TWD_SK_Inline' ) && TWD_SK_Inline::allowed( $post_id ) ) {
			$html = TWD_SK_Inline::mark( $html );
		}

		// "On this page" (0.15.0, idea 8): built from the h2 headings now, inside the section that asks for it; never stored.
		if ( false !== strpos( $html, 'twd-sk-toc' ) ) {
			$html = self::add_toc( $html );
		}

		return '<div class="' . self::WRAPPER_CLASS . '">' . self::render_html( $html ) . '</div>';
	}

	/**
	 * Give every h2 an id (twd-sk-h-N, unless it has one) and put a list of links to them into the first section that
	 * carries twd-sk-toc, just inside the end of its column. Nothing is stored: the stored page has no ids and no list.
	 */
	public static function add_toc( $html ) {
		if ( 1 !== preg_match( '/<section\b[^>]*\sclass="[^"]*\btwd-sk-toc\b/', $html ) ) {
			return $html;
		}
		$n     = 0;
		$items = array();
		$html  = preg_replace_callback( '/<h2\b([^>]*)>(.*?)<\/h2>/is', function ( $m ) use ( &$n, &$items ) {
			$n++;
			$attrs = $m[1];
			$text  = trim( preg_replace( '/\s+/', ' ', html_entity_decode( strip_tags( $m[2] ), ENT_QUOTES, 'UTF-8' ) ) );
			if ( '' === $text ) {
				return $m[0];
			}
			if ( preg_match( '/\sid="([^"]+)"/', $attrs, $id ) ) {
				$anchor = $id[1];
			} else {
				$anchor = 'twd-sk-h-' . $n;
				$attrs .= ' id="' . $anchor . '"';
			}
			if ( preg_match( '/\sclass="([^"]*)"/', $attrs, $c ) ) {
				$attrs = str_replace( $c[0], ' class="' . $c[1] . ' twd-sk-toc-target"', $attrs );
			} else {
				$attrs .= ' class="twd-sk-toc-target"';
			}
			$items[] = '<li><a class="twd-sk-toc__link" href="#' . esc_attr( $anchor ) . '">' . esc_html( $text ) . '</a></li>';
			return '<h2' . $attrs . '>' . $m[2] . '</h2>';
		}, $html );
		if ( count( $items ) < 2 ) {
			return $html;
		}
		$nav   = '<nav class="twd-sk-toc__box" aria-label="On this page"><p class="twd-sk-toc__title">On this page</p><ul class="twd-sk-toc__list">' . implode( '', $items ) . '</ul></nav>';
		$parts = TWD_SK_Sections::split( $html );
		foreach ( $parts['sections'] as $i => $piece ) {
			if ( 1 === preg_match( '/^<section\b[^>]*\sclass="[^"]*\btwd-sk-toc\b/', $piece ) ) {
				$done = preg_replace( '/(<\/div>\s*<\/section>)$/', $nav . '$1', $piece, 1, $count );
				$parts['sections'][ $i ] = $count ? $done : preg_replace( '/<\/section>$/', $nav . '</section>', $piece, 1 );
				return TWD_SK_Sections::join( $parts['gaps'], $parts['sections'] );
			}
		}
		return $html;
	}

	/** Small scripts only some sections need, loaded only on a page that has them. */
	public static function enqueue_extras( $html ) {
		if ( TWD_SK_Safe::on() || ! function_exists( 'wp_enqueue_script' ) || ! defined( 'TWD_SK_URL' ) ) {
			return;
		}
		// The quote slider still swipes without its script; the script adds arrows, dots and keys.
		if ( false !== strpos( (string) $html, 'twd-sk-quote--slider' ) ) {
			wp_enqueue_script( 'twd-site-kit-slider', TWD_SK_URL . 'assets/twd-site-kit-slider.js', array(), defined( 'TWD_SK_VERSION' ) ? TWD_SK_VERSION : false, true );
		}
		// Question groups (0.15.0): without the script a tab is a link to its group (the page scrolls there); the script
		// opens the group where it is, marks the tab, and sets the address without the jump.
		if ( false !== strpos( (string) $html, 'twd-sk-faq--groups' ) ) {
			wp_enqueue_script( 'twd-site-kit-faq', TWD_SK_URL . 'assets/twd-site-kit-faq.js', array(), defined( 'TWD_SK_VERSION' ) ? TWD_SK_VERSION : false, true );
		}
		// Reading controls (0.15.0, idea 6): a visitor's own text size, line spacing and contrast, kept in their browser.
		if ( false !== strpos( (string) $html, 'twd-sk-reading' ) ) {
			wp_enqueue_script( 'twd-site-kit-reading', TWD_SK_URL . 'assets/twd-site-kit-reading.js', array(), defined( 'TWD_SK_VERSION' ) ? TWD_SK_VERSION : false, true );
		}
		// The parallax hero still shows its picture without the script; the script moves it a little (0.14.0).
		if ( false !== strpos( (string) $html, 'twd-sk-hero--parallax' ) ) {
			wp_enqueue_script( 'twd-site-kit-hero', TWD_SK_URL . 'assets/twd-site-kit-hero.js', array(), defined( 'TWD_SK_VERSION' ) ? TWD_SK_VERSION : false, true );
		}
	}

	/**
	 * Run the allowed shortcodes in stored HTML and neutralise the rest.
	 */
	public static function render_html( $html ) {
		// Picture attributes (size, lazy loading, hero priority) are added now, never stored.
		// Done before any shortcode runs, so other plugins' pictures are left alone.
		if ( ! TWD_SK_Safe::on() && class_exists( 'TWD_SK_Images' ) ) {
			$html = TWD_SK_Images::process( $html );
		}
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
