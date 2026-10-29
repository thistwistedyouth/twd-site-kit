<?php
/**
 * TWD_SK_Chrome: the site header and footer, printed by the [twd_header] and [twd_footer] shortcodes.
 *
 * An Elementor Theme Builder template holds one Shortcode widget with each. The look comes from
 * the active style pack (which header and footer variant it prefers) and a per-site override;
 * the words and links come from the site profile (TWD_SK_Profile). Variants live here, in the
 * plugin, so a new site needs no layout work.
 *
 * Header variants: bar, centered, split, minimal. Footer variants: columns, band, centered, simple, feature, compact (0.14.1).
 *
 * Placement (0.9.1): on the Hello theme, Site Kit places the header and footer itself, with no
 * Elementor Pro needed. It switches off Hello's own basic header and footer (the filter
 * hello_elementor_header_footer), prints the header at wp_body_open with a skip link first, and
 * the footer when the theme asks for its footer (get_footer). It stands aside on any page where an
 * Elementor Pro Theme Builder header or footer applies, on Elementor's blank Canvas template, on any
 * other theme, in safe mode (Hello's own header and footer come back), and when the setting says
 * to leave it to Elementor or the theme. The shortcodes keep working for an Elementor template.
 *
 * Safe by default: if safe mode is on, the profile is empty, or anything goes wrong, a plain
 * minimal header and footer print instead (the site name, a few pages, a copyright line) so
 * a Theme Builder template never shows a raw shortcode or an empty gap.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class TWD_SK_Chrome {

	const OPTION     = 'twd_sk_chrome';
	const HANDLE_JS  = 'twd-site-kit-header';
	const HANDLE_SHRINK = 'twd-site-kit-header-shrink';
	const NAV_ID     = 'twd-sk-nav';

	private static $placed = array( 'header' => false, 'footer' => false );

	public static function init() {
		add_shortcode( 'twd_header', array( __CLASS__, 'shortcode_header' ) );
		add_shortcode( 'twd_footer', array( __CLASS__, 'shortcode_footer' ) );
		// Placing them on the Hello theme. Each callback decides for itself, so safe mode simply turns it off.
		add_filter( 'hello_elementor_header_footer', array( __CLASS__, 'filter_hello' ), 20 );
		// One skip link (0.14.0): ours prints inside the header, so Hello's own is switched off while Site Kit places it.
		add_filter( 'hello_elementor_enable_skip_link', array( __CLASS__, 'filter_hello' ), 20 );
		add_action( 'wp_body_open', array( __CLASS__, 'print_header' ), 5 );
		add_action( 'get_footer', array( __CLASS__, 'print_footer' ), 5 );
	}

	// -- Placement on the Hello theme (0.9.1) ---------------------------------

	/** Who places the header and footer: placement key => label. */
	public static function placements() {
		return array(
			'kit'       => 'Site Kit places them (Hello theme, no Elementor Pro needed)',
			'elementor' => 'Leave them to Elementor Pro templates or the theme',
		);
	}

	public static function hello_active() {
		return function_exists( 'get_template' ) && 'hello-elementor' === get_template();
	}

	/** True when an Elementor Pro Theme Builder template covers this location on this page. */
	public static function elementor_has( $location ) {
		if ( ! function_exists( 'elementor_location_exits' ) ) {
			return false;
		}
		try {
			return (bool) elementor_location_exits( $location, true );
		} catch ( \Throwable $e ) {
			return false;
		}
	}

	/**
	 * The setting allows it, the theme is Hello and safe mode is off. Page-level checks come on top.
	 * Never throws: if the settings cannot be read, Site Kit stands aside and Hello's own header shows.
	 */
	public static function places_itself() {
		try {
			return ! TWD_SK_Safe::on() && 'kit' === self::settings()['placement'] && self::hello_active();
		} catch ( \Throwable $e ) {
			return false;
		}
	}

	private static function on_blank_template() {
		return function_exists( 'get_page_template_slug' ) && in_array( (string) get_page_template_slug(), array( 'elementor_canvas' ), true );
	}

	/** Hello prints its own basic header and footer only when Site Kit does not. */
	public static function filter_hello( $show ) {
		return self::places_itself() ? false : $show;
	}

	public static function print_header() {
		if ( is_admin() || self::$placed['header'] || ! self::places_itself() || self::on_blank_template() || self::elementor_has( 'header' ) ) {
			return;
		}
		self::$placed['header'] = true;
		// A skip link comes first, inside the header, so a keyboard user can jump past the menu.
		try {
			$html = self::render_header( true );
		} catch ( \Throwable $e ) {
			$html = self::render_minimal_header( true );
		}
		echo $html; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- built and escaped by render_header().
	}

	public static function print_footer() {
		if ( is_admin() || self::$placed['footer'] || ! self::places_itself() || self::on_blank_template() || self::elementor_has( 'footer' ) ) {
			return;
		}
		self::$placed['footer'] = true;
		echo self::shortcode_footer(); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- built and escaped by render_footer().
	}

	/** What is placing the header and footer, in plain words, for the site report and the launch check. */
	public static function placement_status() {
		try {
			$s = self::settings()['placement'];
		} catch ( \Throwable $e ) {
			return 'unknown (the settings could not be read)';
		}
		if ( 'kit' === $s && self::hello_active() ) {
			return TWD_SK_Safe::on() ? 'the Hello theme (safe mode is on, so its own basic header and footer show)' : 'Site Kit, on the Hello theme';
		}
		if ( 'kit' === $s ) {
			return 'nothing yet: Site Kit is set to place them, but the active theme is not Hello';
		}
		return 'Elementor Pro templates or the theme (Site Kit stands aside)';
	}

	/** For tests: forget what has been printed on this request. */
	public static function reset_placed() {
		self::$placed = array( 'header' => false, 'footer' => false );
	}

	// -- Variants and settings -----------------------------------------------

	public static function header_variants() {
		return array(
			'bar'      => 'Logo on the left, menu and button on the right',
			'centered' => 'Logo centred, menu centred below it',
			'split'    => 'Menu on the left, logo in the middle, button on the right',
			'minimal'  => 'Logo and a menu button at every width',
		);
	}

	public static function footer_variants() {
		return array(
			'columns'  => 'One to three columns on a light background',
			'band'     => 'One to three columns on the band colour',
			'centered' => 'One centred column',
			'simple'   => 'Just the copyright, legal links and registration lines',
			// 0.14.0: the large logo, footer text as a heading, contact, links in two columns level with the footer text, a dark strip below holding the registration (moved there 0.15.0).
			'feature'  => 'Large logo and contact on the left, links in two columns level with the footer text, registration and copyright in a dark strip below',
			'compact'  => 'Tagline on top, logo left, links right, and a dark bar for the small print',
		);
	}

	/** How the page you are on is marked in the menu of the text wordmark header: key => label. */
	public static function menu_markers() {
		return array(
			'dot'   => 'A small dot under the page you are on',
			'color' => 'The page you are on in the accent colour and a little bolder',
			'bar'   => 'A short bar under the page you are on',
			'none'  => 'No marker',
		);
	}

	/** Every CSS class this class prints. The stylesheet tests check each one is styled. */
	public static function classes() {
		$classes = array(
			'twd-sk-chrome', 'twd-sk-chrome--sticky', 'twd-sk-chrome--strip',
			'twd-sk-header', 'twd-sk-header--sticky', 'twd-sk-header--shrink', 'twd-sk-header--shrunk', 'twd-sk-header--js', 'twd-sk-header--open',
			'twd-sk-header__strip', 'twd-sk-header__strip-inner', 'twd-sk-header__strip-link', 'twd-sk-header__inner',
			'twd-sk-header__logo', 'twd-sk-header__logo-img', 'twd-sk-header__logo-text', 'twd-sk-header__toggle', 'twd-sk-header__bars',
			// 0.14.0: the front page's larger stacked logo, and the small mark used elsewhere.
			'twd-sk-header--front', 'twd-sk-header__logo-mark', 'twd-sk-header__logo-name', 'twd-sk-chrome--front',
			// 0.14.0: the header laid transparent over a page's opening full-width picture.
			'twd-sk-header--over', 'twd-sk-chrome--over',
			'twd-sk-header__panel', 'twd-sk-header__nav', 'twd-sk-header__menu', 'twd-sk-header__item', 'twd-sk-header__item--parent',
			'twd-sk-header__link', 'twd-sk-header__sub', 'twd-sk-header__sublink', 'twd-sk-header__cta',
			'twd-sk-footer', 'twd-sk-footer--cols-1', 'twd-sk-footer--cols-2', 'twd-sk-footer--cols-3',
			'twd-sk-footer__main', 'twd-sk-footer__col', 'twd-sk-footer__logo-img', 'twd-sk-footer__name', 'twd-sk-footer__text',
			'twd-sk-footer__heading', 'twd-sk-footer__links', 'twd-sk-footer__link', 'twd-sk-footer__contact',
			'twd-sk-footer__reg', 'twd-sk-footer__reg-line', 'twd-sk-footer__bottom', 'twd-sk-footer__bottom-inner',
			'twd-sk-footer__copy', 'twd-sk-footer__legal', 'twd-sk-footer__legal-link', 'twd-sk-skip',
			// 0.14.0: the feature footer's own pieces (the footer text as a heading, the contact and registration groups, the two-column list).
			'twd-sk-footer__tagline', 'twd-sk-footer__contact-group', 'twd-sk-footer__links--two',
			// 0.15.0: the footer text with its first line larger and the rest quieter.
			'twd-sk-footer__text--lead', 'twd-sk-footer__tagline--lead',
			// 0.14.0: the text wordmark header (a mark, the name and a second line as live text, centred on every page).
			'twd-sk-header--wordmark', 'twd-sk-header--wm-front', 'twd-sk-header--wm-name', 'twd-sk-header__mark', 'twd-sk-header__name', 'twd-sk-header__line',
			'twd-sk-chrome--wordmark', 'twd-sk-chrome--wm-front', 'twd-sk-chrome--noline',
			// 0.15.0: the second line under the name in the smaller header (the words block, the header and the spacer know about it).
			'twd-sk-header--wm-line', 'twd-sk-header__words', 'twd-sk-chrome--wm-line',
			// 0.14.1: the compact footer (tagline on top, logo left, links right, a bar for the small print).
			'twd-sk-footer__top', 'twd-sk-footer__tagline', 'twd-sk-footer__row', 'twd-sk-footer__brand', 'twd-sk-footer__links--row',
			'twd-sk-footer__bottom--bar', 'twd-sk-footer__meta',
		);
		foreach ( array_keys( self::header_variants() ) as $v ) {
			$classes[] = 'twd-sk-header--' . $v;
		}
		foreach ( array_keys( self::footer_variants() ) as $v ) {
			$classes[] = 'twd-sk-footer--' . $v;
		}
		foreach ( array_keys( self::menu_markers() ) as $m ) {
			$classes[] = 'twd-sk-header--mark-' . $m;
		}
		return $classes;
	}

	public static function defaults() {
		return array(
			'header_variant' => '',     // '' means the pack's own choice
			'footer_variant' => '',
			'sticky'         => false,
			'shrink'         => false,   // 0.13.0: a fixed header gets smaller after a small scroll
			'front_logo'     => false,   // 0.14.0: the full logo, larger and centred above the menu, on the front page only
			'overlay'        => false,   // 0.14.0: transparent over the picture, on a page that opens with a full-width picture hero
			'wordmark'       => false,   // 0.14.0: the logo as live text: a stacked lockup on the front page, a symbol and the name in a one-row bar elsewhere, with its own menu look
			'wordmark_scrolled_name' => false, // 0.14.0: keep the name beside the symbol when the front page header is scrolled (otherwise just the symbol beside the menu)
			'wordmark_small_line' => false, // 0.15.0: the second line under the name in the smaller header too (every page but the large front page, and the scrolled front page)
			'menu_marker'    => 'dot',   // 0.14.0: how the current page is marked in that header's menu
			'show_button'    => true,
			'show_strip'     => false,
			'footer_columns' => 3,
			'footer_text_style' => 'plain', // 0.15.0: the footer text as before, or its first line larger and the rest quieter
			'placement'      => 'kit',
		);
	}

	/** The stored per-site settings, validated. */
	public static function settings() {
		$stored = function_exists( 'get_option' ) ? get_option( self::OPTION, array() ) : array();
		$out    = self::defaults();
		if ( is_array( $stored ) ) {
			$check = self::validate( $stored );
			$out   = array_merge( $out, $check['valid'] );
		}
		return $out;
	}

	/**
	 * @return array { valid: ..., errors: [] }
	 */
	public static function validate( $input ) {
		$out = array( 'valid' => array(), 'errors' => array() );
		if ( ! is_array( $input ) ) {
			$out['errors'][] = 'The header and footer settings must be a set of fields.';
			return $out;
		}
		foreach ( $input as $key => $value ) {
			switch ( $key ) {
				case 'header_variant':
					if ( '' === $value || ( is_string( $value ) && isset( self::header_variants()[ $value ] ) ) ) {
						$out['valid'][ $key ] = $value;
					} else {
						$out['errors'][] = 'That header layout does not exist.';
					}
					break;
				case 'footer_variant':
					if ( '' === $value || ( is_string( $value ) && isset( self::footer_variants()[ $value ] ) ) ) {
						$out['valid'][ $key ] = $value;
					} else {
						$out['errors'][] = 'That footer layout does not exist.';
					}
					break;
				case 'sticky':
				case 'shrink':
				case 'front_logo':
				case 'overlay':
				case 'wordmark':
				case 'wordmark_scrolled_name':
				case 'wordmark_small_line':
				case 'show_button':
				case 'show_strip':
					if ( is_bool( $value ) || 0 === $value || 1 === $value || '0' === $value || '1' === $value ) {
						$out['valid'][ $key ] = (bool) $value;
					} else {
						$out['errors'][] = 'The ' . str_replace( '_', ' ', $key ) . ' setting must be on or off.';
					}
					break;
				case 'menu_marker':
					if ( is_string( $value ) && isset( self::menu_markers()[ $value ] ) ) {
						$out['valid'][ $key ] = $value;
					} else {
						$out['errors'][] = 'Choose how the page you are on is marked in the menu: ' . implode( ', ', array_keys( self::menu_markers() ) ) . '.';
					}
					break;
				case 'placement':
					if ( is_string( $value ) && isset( self::placements()[ $value ] ) ) {
						$out['valid'][ $key ] = $value;
					} else {
						$out['errors'][] = 'Choose who places the header and footer.';
					}
					break;
				case 'footer_columns':
					if ( ( is_int( $value ) || ( is_string( $value ) && ctype_digit( $value ) ) ) && (int) $value >= 1 && (int) $value <= 3 ) {
						$out['valid'][ $key ] = (int) $value;
					} else {
						$out['errors'][] = 'The footer can have one, two or three columns.';
					}
					break;
				case 'footer_text_style':
					if ( is_string( $value ) && isset( self::text_styles()[ $value ] ) ) {
						$out['valid'][ $key ] = $value;
					} else {
						$out['errors'][] = 'Choose how the footer text is set: ' . implode( ', ', array_keys( self::text_styles() ) ) . '.';
					}
					break;
				default:
					$out['errors'][] = 'Unknown setting: ' . ( is_string( $key ) ? $key : '?' ) . '.';
			}
		}
		return $out;
	}

	public static function save_settings( $input ) {
		$check = self::validate( $input );
		if ( $check['errors'] ) {
			return new WP_Error( 'twd_sk_bad_chrome', implode( ' ', $check['errors'] ) );
		}
		$merged = array_merge( self::settings(), $check['valid'] );
		update_option( self::OPTION, $merged );
		return $merged;
	}

	/** The variant in force: the per-site override, else the pack's choice, else the default. */
	public static function effective() {
		$s    = self::settings();
		$pack = TWD_SK_Packs::chrome_defaults();
		$h    = '' !== $s['header_variant'] ? $s['header_variant'] : $pack['header'];
		$f    = '' !== $s['footer_variant'] ? $s['footer_variant'] : $pack['footer'];
		// A centred header has two rows, so it cannot be fixed to the top at a known height. The text wordmark
		// header can: its height is worked out from its size tokens (see the stylesheet).
		$wm     = (bool) $s['wordmark'];
		$sticky = (bool) $s['sticky'] && ( $wm || 'centered' !== $h );
		return array(
			'header'      => $h,
			'footer'      => $f,
			'sticky'      => $sticky,
			'shrink'      => $sticky && (bool) $s['shrink'],
			'front_logo'  => (bool) $s['front_logo'],
			'overlay'     => (bool) $s['overlay'],
			'wordmark'    => $wm,
			// The second line in the smaller header needs the name beside the symbol when the front page is scrolled, so it brings that with it.
			'wm_name'     => $wm && ( (bool) $s['wordmark_scrolled_name'] || (bool) $s['wordmark_small_line'] ),
			'wm_line'     => $wm && (bool) $s['wordmark_small_line'],
			'menu_marker' => $s['menu_marker'],
			'button'      => (bool) $s['show_button'],
			'strip'       => (bool) $s['show_strip'],
			'columns'     => (int) $s['footer_columns'],
			'text_style'  => $s['footer_text_style'],
			'sticky_note' => (bool) $s['sticky'] && ! $wm && 'centered' === $h,
		);
	}

	// -- Shortcodes -------------------------------------------------------

	public static function shortcode_header() {
		try {
			return self::render_header();
		} catch ( \Throwable $e ) {
			return self::render_minimal_header();
		}
	}

	public static function shortcode_footer() {
		try {
			return self::render_footer();
		} catch ( \Throwable $e ) {
			return self::render_minimal_footer();
		}
	}

	// -- Small helpers ----------------------------------------------------

	private static function esc( $text ) {
		return esc_html( (string) $text );
	}

	/** The footer text's style choices (0.15.0): as before, or the first line larger with the rest quieter. */
	public static function text_styles() {
		return array( 'plain' => 'As before', 'lead' => 'First line larger, the rest quieter' );
	}

	/** The extra class the footer text gets for its style, '' for plain (so the old footers print the same bytes). */
	private static function text_style_class( $piece ) {
		$s = self::settings();
		return 'lead' === $s['footer_text_style'] ? ' twd-sk-footer' . $piece . '--lead' : '';
	}

	private static function url( $url ) {
		return esc_url( (string) $url );
	}

	private static function home() {
		return function_exists( 'home_url' ) ? home_url( '/' ) : '/';
	}

	private static function site_name( $profile ) {
		if ( '' !== $profile['site_name'] ) {
			return $profile['site_name'];
		}
		return function_exists( 'get_bloginfo' ) ? (string) get_bloginfo( 'name' ) : '';
	}

	/** True when a menu link points at the page being viewed. */
	private static function is_current( $url ) {
		$request = isset( $_SERVER['REQUEST_URI'] ) && is_string( $_SERVER['REQUEST_URI'] ) ? (string) parse_url( $_SERVER['REQUEST_URI'], PHP_URL_PATH ) : '';
		$path    = (string) parse_url( (string) $url, PHP_URL_PATH );
		$host    = (string) parse_url( (string) $url, PHP_URL_HOST );
		if ( '' !== $host ) {
			$home = (string) parse_url( self::home(), PHP_URL_HOST );
			if ( strtolower( $host ) !== strtolower( $home ) ) {
				return false;
			}
		}
		if ( '' === $path || '' === $request ) {
			return false;
		}
		return rtrim( $path, '/' ) === rtrim( $request, '/' ) || ( '/' === $path && '/' === $request );
	}

	private static function logo_html( $profile, $img_class, $text_class, $lazy = false, $size = 'medium' ) {
		$name = self::site_name( $profile );
		$img  = self::logo_img( $profile['logo_id'], $img_class, $name, $lazy, $size );
		return '' !== $img ? $img : '<span class="' . $text_class . '">' . self::esc( $name ) . '</span>';
	}

	/** One logo picture tag, or '' when the id is not a usable picture. */
	private static function logo_img( $id, $img_class, $alt, $lazy = false, $size = 'medium' ) {
		if ( $id && function_exists( 'wp_get_attachment_image_src' ) ) {
			$src = wp_get_attachment_image_src( (int) $id, $size );
			if ( is_array( $src ) && ! empty( $src[0] ) ) {
				$w = ! empty( $src[1] ) ? ' width="' . (int) $src[1] . '"' : '';
				$h = ! empty( $src[2] ) ? ' height="' . (int) $src[2] . '"' : '';
				return '<img class="' . $img_class . '" src="' . self::url( $src[0] ) . '" alt="' . esc_attr( $alt ) . '"' . $w . $h . ( $lazy ? ' loading="lazy"' : '' ) . ' decoding="async">';
			}
		}
		return '';
	}

	/**
	 * The header logo (0.14.0). With the front page logo option on and this the front page, the full
	 * logo prints large; a small mark, when one is set, prints beside it (hidden until the header shrinks,
	 * when it takes the full logo's place). On other pages the mark prints with the site name as text;
	 * with no mark the full logo prints as always.
	 */
	private static function header_logo_html( $profile, $front, $option_on ) {
		$name = self::site_name( $profile );
		$mark = $option_on ? self::logo_img( $profile['logo_mark_id'], 'twd-sk-header__logo-mark', '' ) : '';
		if ( $front ) {
			$full = self::logo_img( $profile['logo_id'], 'twd-sk-header__logo-img', $name );
			if ( '' !== $full ) {
				// 0.14.1: the site name beside the mark, shown only once the header shrinks (the stylesheet
				// hides it and the mark on the front page until then), as on every other page.
				$label = '' !== $mark ? '<span class="twd-sk-header__logo-text twd-sk-header__logo-name">' . self::esc( $name ) . '</span>' : '';
				return $mark . $full . $label;
			}
		} elseif ( '' !== $mark ) {
			return $mark . '<span class="twd-sk-header__logo-text">' . self::esc( $name ) . '</span>';
		}
		return self::logo_html( $profile, 'twd-sk-header__logo-img', 'twd-sk-header__logo-text' );
	}

	/**
	 * The text wordmark (0.14.0): the small mark picture if one is set (an empty description, because the name
	 * is right beside it as text), the site name as live text, and, on the front page's stacked lockup only, the
	 * second line if there is one. The link's name is the site name once (plus the second line on the front
	 * page). Capitals and spacing are the stylesheet's; scrolled, the stylesheet may hide the name from the eye
	 * but never from a screen reader.
	 */
	private static function wordmark_html( $profile, $stacked = false, $small_line = false ) {
		$name = self::site_name( $profile );
		$mark = self::logo_img( $profile['logo_mark_id'], 'twd-sk-header__mark', '' !== $name ? '' : 'Home' );
		$name_html = '<span class="twd-sk-header__name">' . self::esc( $name ) . '</span>';
		// 0.15.0: with the "second line in the smaller header" setting the line is printed on every page and the name and line sit
		// together in one block beside the symbol. Off, the markup is exactly what it was.
		if ( $small_line && '' !== $profile['logo_line'] ) {
			return $mark . '<span class="twd-sk-header__words">' . $name_html . '<span class="twd-sk-header__line">' . self::esc( $profile['logo_line'] ) . '</span></span>';
		}
		$line = $stacked ? $profile['logo_line'] : '';
		return $mark . $name_html . ( '' !== $line ? '<span class="twd-sk-header__line">' . self::esc( $line ) . '</span>' : '' );
	}

	/** True on the site's front page (the home page), where the larger logo may show. */
	private static function is_front() {
		try {
			return function_exists( 'is_front_page' ) && is_front_page();
		} catch ( \Throwable $e ) {
			return false;
		}
	}

	/**
	 * True when the page being shown opens with a full-width picture hero (0.14.0), the only place the
	 * transparent header is readable: white words need the picture's overlay behind them. On any other
	 * page the normal header prints, so the option can never leave a menu unreadable.
	 */
	public static function opens_with_picture() {
		try {
			$id = function_exists( 'get_queried_object_id' ) ? (int) get_queried_object_id() : 0;
			if ( $id <= 0 && function_exists( 'get_the_ID' ) ) {
				$id = (int) get_the_ID();
			}
			if ( $id <= 0 || ! class_exists( 'TWD_SK_Page' ) ) {
				return false;
			}
			$html = ltrim( TWD_SK_Page::current_html( $id ) );
			return 1 === preg_match( '/^<section\b[^>]*class="[^"]*\btwd-sk-hero--image\b/', $html );
		} catch ( \Throwable $e ) {
			return false;
		}
	}

	/** A phone number as a link when it has digits, else as plain text (a starter holds a placeholder). */
	private static function phone_html( $phone, $class ) {
		$digits = preg_replace( '/[^0-9+]/', '', (string) $phone );
		if ( '' === $phone ) {
			return '';
		}
		if ( 1 === preg_match( '/\d{5,}/', $digits ) ) {
			return '<a class="' . $class . '" href="tel:' . esc_attr( $digits ) . '">' . self::esc( $phone ) . '</a>';
		}
		return '<span>' . self::esc( $phone ) . '</span>';
	}

	private static function email_html( $email, $class ) {
		return '' === $email ? '' : '<a class="' . $class . '" href="mailto:' . esc_attr( $email ) . '">' . self::esc( $email ) . '</a>';
	}

	private static function enqueue_script() {
		if ( function_exists( 'wp_enqueue_script' ) && defined( 'TWD_SK_URL' ) ) {
			wp_enqueue_script( self::HANDLE_JS, TWD_SK_URL . 'assets/twd-site-kit-header.js', array(), defined( 'TWD_SK_VERSION' ) ? TWD_SK_VERSION : false, true );
		}
	}

	/** Only printed when the header is fixed AND the setting is on (never in safe mode: render_header returns early there). */
	private static function enqueue_shrink_script() {
		if ( function_exists( 'wp_enqueue_script' ) && defined( 'TWD_SK_URL' ) ) {
			wp_enqueue_script( self::HANDLE_SHRINK, TWD_SK_URL . 'assets/twd-site-kit-header-shrink.js', array(), defined( 'TWD_SK_VERSION' ) ? TWD_SK_VERSION : false, true );
		}
	}

	// -- The header -------------------------------------------------------

	/** Hidden until focused. Hello's main content area has the id content. */
	private static function skip_link() {
		return '<a class="twd-sk-skip" href="#content">Skip to content</a>';
	}

	public static function render_header( $skip = false ) {
		$profile = TWD_SK_Profile::get();
		// The site name falls back to the WordPress site title, so a missing name never drops the menu.
		if ( TWD_SK_Safe::on() || ! $profile['menu'] ) {
			return self::render_minimal_header( $skip );
		}
		$eff   = self::effective();
		$strip = $eff['strip'] && ( '' !== $profile['phone'] || '' !== $profile['email'] );
		$wm       = $eff['wordmark'];
		$wm_front = $wm && self::is_front();
		// The text wordmark replaces the bigger front page logo (it has its own larger front page size).
		$front = ! $wm && $eff['front_logo'] && $profile['logo_id'] && self::is_front();
		$over  = $eff['overlay'] && self::opens_with_picture();
		// 0.15.0: the second line in the smaller header (only with the wordmark, and only when there is a line to show).
		$small_line = $wm && $eff['wm_line'] && '' !== $profile['logo_line'];
		$wrap  = 'twd-sk-page twd-sk-chrome' . ( $eff['sticky'] ? ' twd-sk-chrome--sticky' : '' ) . ( $eff['sticky'] && $strip ? ' twd-sk-chrome--strip' : '' ) . ( $eff['sticky'] && $front ? ' twd-sk-chrome--front' : '' ) . ( $over ? ' twd-sk-chrome--over' : '' )
			. ( $eff['sticky'] && $wm ? ' twd-sk-chrome--wordmark' . ( $wm_front ? ' twd-sk-chrome--wm-front' . ( '' === $profile['logo_line'] ? ' twd-sk-chrome--noline' : '' ) : '' ) . ( $small_line ? ' twd-sk-chrome--wm-line' : '' ) : '' );
		// The text wordmark: the stacked lockup on the front page, the one-row bar on every other page.
		$cls   = 'twd-sk-header' . ( $wm ? ' twd-sk-header--wordmark twd-sk-header--mark-' . $eff['menu_marker'] . ( $wm_front ? ' twd-sk-header--wm-front' . ( $eff['wm_name'] ? ' twd-sk-header--wm-name' : '' ) : ' twd-sk-header--bar' ) . ( $small_line ? ' twd-sk-header--wm-line' : '' ) : ' twd-sk-header--' . $eff['header'] )
			. ( $eff['sticky'] ? ' twd-sk-header--sticky' : '' ) . ( $eff['shrink'] ? ' twd-sk-header--shrink' : '' ) . ( $front ? ' twd-sk-header--front' : '' ) . ( $over ? ' twd-sk-header--over' : '' );

		$out  = '<div class="' . $wrap . '"><header class="' . $cls . '">' . ( $skip ? self::skip_link() : '' );
		if ( $strip ) {
			$out .= '<div class="twd-sk-header__strip"><div class="twd-sk-inner twd-sk-header__strip-inner">'
				. self::phone_html( $profile['phone'], 'twd-sk-header__strip-link' ) . self::email_html( $profile['email'], 'twd-sk-header__strip-link' )
				. '</div></div>';
		}
		$out .= '<div class="twd-sk-inner twd-sk-header__inner">';
		$out .= '<a class="twd-sk-header__logo" href="' . self::url( self::home() ) . '">' . ( $wm ? self::wordmark_html( $profile, $wm_front, $small_line ) : self::header_logo_html( $profile, $front, $eff['front_logo'] && $profile['logo_id'] ) ) . '</a>';
		$out .= '<button type="button" class="twd-sk-header__toggle" aria-expanded="false" aria-controls="' . self::NAV_ID . '" hidden>'
			. '<span class="twd-sk-visually-hidden">Menu</span><span class="twd-sk-header__bars" aria-hidden="true"></span></button>';
		$out .= '<div class="twd-sk-header__panel"><nav id="' . self::NAV_ID . '" class="twd-sk-header__nav" aria-label="Main"><ul class="twd-sk-header__menu">';
		foreach ( $profile['menu'] as $item ) {
			$parent = ! empty( $item['children'] );
			$cur    = self::is_current( $item['url'] ) ? ' aria-current="page"' : '';
			$out   .= '<li class="twd-sk-header__item' . ( $parent ? ' twd-sk-header__item--parent' : '' ) . '"><a class="twd-sk-header__link" href="' . self::url( $item['url'] ) . '"' . $cur . '>' . self::esc( $item['label'] ) . '</a>';
			if ( $parent ) {
				$out .= '<ul class="twd-sk-header__sub">';
				foreach ( $item['children'] as $child ) {
					$ccur = self::is_current( $child['url'] ) ? ' aria-current="page"' : '';
					$out .= '<li><a class="twd-sk-header__sublink" href="' . self::url( $child['url'] ) . '"' . $ccur . '>' . self::esc( $child['label'] ) . '</a></li>';
				}
				$out .= '</ul>';
			}
			$out .= '</li>';
		}
		$out .= '</ul></nav>';
		if ( $eff['button'] && '' !== $profile['cta_label'] && '' !== $profile['cta_url'] ) {
			$out .= '<a class="twd-sk-btn twd-sk-btn--primary twd-sk-header__cta" href="' . self::url( $profile['cta_url'] ) . '">' . self::esc( $profile['cta_label'] ) . '</a>';
		}
		$out .= '</div></div></header></div>';

		self::enqueue_script();
		if ( $eff['shrink'] ) {
			self::enqueue_shrink_script();
		}
		return $out;
	}

	/** Plain and always safe: the site name and a few pages. Used in safe mode and whenever the profile is empty. */
	public static function render_minimal_header( $skip = false ) {
		$name  = function_exists( 'get_bloginfo' ) ? (string) get_bloginfo( 'name' ) : '';
		$items = '';
		if ( function_exists( 'get_pages' ) ) {
			$pages = get_pages( array( 'sort_column' => 'menu_order,post_title', 'parent' => 0, 'number' => 6 ) );
			foreach ( is_array( $pages ) ? $pages : array() as $page ) {
				$items .= '<li class="twd-sk-header__item"><a class="twd-sk-header__link" href="' . self::url( get_permalink( $page->ID ) ) . '">' . self::esc( $page->post_title ) . '</a></li>';
			}
		}
		return '<div class="twd-sk-page twd-sk-chrome"><header class="twd-sk-header twd-sk-header--bar">' . ( $skip ? self::skip_link() : '' ) . '<div class="twd-sk-inner twd-sk-header__inner">'
			. '<a class="twd-sk-header__logo" href="' . self::url( self::home() ) . '"><span class="twd-sk-header__logo-text">' . self::esc( $name ) . '</span></a>'
			. ( '' !== $items ? '<div class="twd-sk-header__panel"><nav class="twd-sk-header__nav" aria-label="Main"><ul class="twd-sk-header__menu">' . $items . '</ul></nav></div>' : '' )
			. '</div></header></div>';
	}

	// -- The footer -------------------------------------------------------

	private static function legal_links( $profile ) {
		$links = $profile['legal'];
		if ( ! $links ) {
			// Always show a privacy line. Link it only to the page WordPress itself knows about.
			$policy = function_exists( 'get_privacy_policy_url' ) ? (string) get_privacy_policy_url() : '';
			$links  = array( array( 'label' => 'Privacy policy', 'url' => $policy ) );
		}
		$out = '';
		foreach ( $links as $l ) {
			$out .= '<li>' . ( '' !== $l['url'] ? '<a class="twd-sk-footer__legal-link" href="' . self::url( $l['url'] ) . '">' . self::esc( $l['label'] ) . '</a>' : '<span>' . self::esc( $l['label'] ) . '</span>' ) . '</li>';
		}
		return '<nav aria-label="Legal"><ul class="twd-sk-footer__legal">' . $out . '</ul></nav>';
	}

	private static function copyright( $profile ) {
		return '<p class="twd-sk-footer__copy">&copy; ' . self::esc( gmdate( 'Y' ) ) . ' ' . self::esc( self::site_name( $profile ) ) . '</p>';
	}

	public static function render_footer() {
		$profile = TWD_SK_Profile::get();
		if ( TWD_SK_Safe::on() || TWD_SK_Profile::is_empty() ) {
			return self::render_minimal_footer();
		}
		$eff     = self::effective();
		$variant = $eff['footer'];
		if ( 'compact' === $variant ) {
			return self::render_compact_footer( $profile );
		}

		if ( 'feature' === $variant ) {
			return self::render_footer_feature( $profile );
		}
		$bottom = '<div class="twd-sk-footer__bottom"><div class="twd-sk-inner twd-sk-footer__bottom-inner">' . self::copyright( $profile ) . self::legal_links( $profile ) . '</div></div>';
		$reg    = '';
		if ( $profile['registration'] ) {
			$reg = '<div class="twd-sk-inner twd-sk-footer__reg">';
			foreach ( $profile['registration'] as $line ) {
				$reg .= '<p class="twd-sk-footer__reg-line">' . self::esc( $line ) . '</p>';
			}
			$reg .= '</div>';
		}

		$main = '';
		if ( 'simple' !== $variant ) {
			$cols = 'centered' === $variant ? 1 : max( 1, min( 3, $eff['columns'] ) );

			$brand = ( $profile['logo_id'] ? '<div>' . self::logo_html( $profile, 'twd-sk-footer__logo-img', 'twd-sk-footer__name', true ) . '</div>' : '<p class="twd-sk-footer__name">' . self::esc( self::site_name( $profile ) ) . '</p>' );
			if ( '' !== $profile['footer_text'] ) {
				$brand .= '<p class="twd-sk-footer__text' . self::text_style_class( '__text' ) . '">' . TWD_SK_Profile::rich_html( $profile['footer_text'] ) . '</p>';
			}

			$links = '';
			if ( $profile['menu'] ) {
				$links = '<p class="twd-sk-footer__heading">Explore</p><nav aria-label="Footer"><ul class="twd-sk-footer__links">';
				foreach ( $profile['menu'] as $item ) {
					$links .= '<li><a class="twd-sk-footer__link" href="' . self::url( $item['url'] ) . '">' . self::esc( $item['label'] ) . '</a></li>';
				}
				$links .= '</ul></nav>';
			}

			$contact = '';
			$lines   = array();
			if ( '' !== $profile['phone'] ) {
				$lines[] = '<p class="twd-sk-footer__contact">' . self::phone_html( $profile['phone'], 'twd-sk-footer__link' ) . '</p>';
			}
			if ( '' !== $profile['email'] ) {
				$lines[] = '<p class="twd-sk-footer__contact">' . self::email_html( $profile['email'], 'twd-sk-footer__link' ) . '</p>';
			}
			foreach ( $profile['address'] as $line ) {
				$lines[] = '<p class="twd-sk-footer__contact">' . self::esc( $line ) . '</p>';
			}
			if ( $lines ) {
				$contact = '<p class="twd-sk-footer__heading">Contact</p>' . implode( '', $lines );
			}

			$blocks = array_values( array_filter( array( $brand, $links, $contact ), 'strlen' ) );
			if ( 1 === $cols ) {
				$groups = array( implode( '', $blocks ) );
			} elseif ( 2 === $cols ) {
				$groups = array( isset( $blocks[0] ) ? $blocks[0] : '', implode( '', array_slice( $blocks, 1 ) ) );
			} else {
				$groups = $blocks;
			}
			$cols = max( 1, min( 3, count( array_filter( $groups, 'strlen' ) ) ) );
			$main = '<div class="twd-sk-inner twd-sk-footer__main">';
			foreach ( $groups as $g ) {
				if ( '' !== $g ) {
					$main .= '<div class="twd-sk-footer__col">' . $g . '</div>';
				}
			}
			$main .= '</div>';
		} else {
			$cols = 1;
		}

		$tone = 'band' === $variant ? ' twd-sk-tone-band' : '';
		return '<div class="twd-sk-page twd-sk-chrome"><footer class="twd-sk-footer twd-sk-footer--' . $variant . ' twd-sk-footer--cols-' . $cols . $tone . '">'
			. $main . $reg . $bottom . '</footer></div>';
	}

	/**
	 * The feature footer (0.14.0). Left block: the logo large (a bigger size of the picture, so it stays sharp),
	 * the footer text set as a heading, then a small Contact label with the email, phone and address lines.
	 * Right block: the page links in two columns, with no label above them, level with the footer text (the
	 * stylesheet places them on that row). Below: the dark strip with the copyright, the registration lines
	 * (moved here from the main area in 0.15.0, at the owner's request) and the legal links. The email stays in
	 * the main area. Anything not supplied leaves its piece out; nothing is invented.
	 */
	private static function render_footer_feature( $profile ) {
		$brand = $profile['logo_id']
			? '<div>' . self::logo_html( $profile, 'twd-sk-footer__logo-img', 'twd-sk-footer__name', true, 'large' ) . '</div>'
			: '<p class="twd-sk-footer__name">' . self::esc( self::site_name( $profile ) ) . '</p>';
		if ( '' !== $profile['footer_text'] ) {
			$brand .= '<p class="twd-sk-footer__tagline' . self::text_style_class( '__tagline' ) . '">' . TWD_SK_Profile::rich_html( $profile['footer_text'] ) . '</p>';
		}

		$lines = array();
		if ( '' !== $profile['email'] ) {
			$lines[] = '<p class="twd-sk-footer__contact">' . self::email_html( $profile['email'], 'twd-sk-footer__link' ) . '</p>';
		}
		if ( '' !== $profile['phone'] ) {
			$lines[] = '<p class="twd-sk-footer__contact">' . self::phone_html( $profile['phone'], 'twd-sk-footer__link' ) . '</p>';
		}
		foreach ( $profile['address'] as $line ) {
			$lines[] = '<p class="twd-sk-footer__contact">' . self::esc( $line ) . '</p>';
		}
		if ( $lines ) {
			$brand .= '<div class="twd-sk-footer__contact-group"><p class="twd-sk-footer__heading">Contact</p>' . implode( '', $lines ) . '</div>';
		}
		$regs = '';
		foreach ( $profile['registration'] as $line ) {
			$regs .= '<span class="twd-sk-footer__reg-line">' . self::esc( $line ) . '</span>';
		}
		$bottom = '<div class="twd-sk-footer__bottom"><div class="twd-sk-inner twd-sk-footer__bottom-inner">' . self::copyright( $profile )
			. ( '' !== $regs ? '<div class="twd-sk-footer__meta">' . $regs . '</div>' : '' )
			. self::legal_links( $profile ) . '</div></div>';

		$links = '';
		if ( $profile['menu'] ) {
			$links = '<nav aria-label="Footer"><ul class="twd-sk-footer__links twd-sk-footer__links--two">';
			foreach ( $profile['menu'] as $item ) {
				$links .= '<li><a class="twd-sk-footer__link" href="' . self::url( $item['url'] ) . '">' . self::esc( $item['label'] ) . '</a></li>';
			}
			$links .= '</ul></nav>';
		}

		$main = '<div class="twd-sk-inner twd-sk-footer__main"><div class="twd-sk-footer__col">' . $brand . '</div>'
			. ( '' !== $links ? '<div class="twd-sk-footer__col">' . $links . '</div>' : '' ) . '</div>';

		return '<div class="twd-sk-page twd-sk-chrome"><footer class="twd-sk-footer twd-sk-footer--feature twd-sk-footer--cols-' . ( '' !== $links ? 2 : 1 ) . '">'
			. $main . $bottom . '</footer></div>';
	}

	/**
	 * The compact footer (0.14.1): the tagline centred on top, the logo (or the name) on the left with the menu
	 * links beside it on the right, then one dark bar for the small print: the copyright, the registration
	 * lines, the phone, email and address when the profile has them, and the legal links. No headings.
	 */
	private static function render_compact_footer( $profile ) {
		$out = '<div class="twd-sk-page twd-sk-chrome"><footer class="twd-sk-footer twd-sk-footer--compact twd-sk-footer--cols-1">';
		if ( '' !== $profile['footer_text'] ) {
			$out .= '<div class="twd-sk-inner twd-sk-footer__top"><p class="twd-sk-footer__tagline' . self::text_style_class( '__tagline' ) . '">' . TWD_SK_Profile::rich_html( $profile['footer_text'] ) . '</p></div>';
		}
		$out .= '<div class="twd-sk-inner twd-sk-footer__row"><div class="twd-sk-footer__brand">'
			. self::logo_html( $profile, 'twd-sk-footer__logo-img', 'twd-sk-footer__name', true ) . '</div>';
		if ( $profile['menu'] ) {
			$out .= '<nav aria-label="Footer"><ul class="twd-sk-footer__links twd-sk-footer__links--row">';
			foreach ( $profile['menu'] as $item ) {
				$out .= '<li><a class="twd-sk-footer__link" href="' . self::url( $item['url'] ) . '">' . self::esc( $item['label'] ) . '</a></li>';
			}
			$out .= '</ul></nav>';
		}
		$out .= '</div>';

		$meta = array();
		foreach ( $profile['registration'] as $line ) {
			$meta[] = '<span class="twd-sk-footer__reg-line">' . self::esc( $line ) . '</span>';
		}
		if ( '' !== $profile['phone'] ) {
			$meta[] = '<span class="twd-sk-footer__contact">' . self::phone_html( $profile['phone'], 'twd-sk-footer__link' ) . '</span>';
		}
		if ( '' !== $profile['email'] ) {
			$meta[] = '<span class="twd-sk-footer__contact">' . self::email_html( $profile['email'], 'twd-sk-footer__link' ) . '</span>';
		}
		if ( $profile['address'] ) {
			$meta[] = '<span class="twd-sk-footer__contact">' . self::esc( implode( ', ', $profile['address'] ) ) . '</span>';
		}
		$out .= '<div class="twd-sk-footer__bottom twd-sk-footer__bottom--bar"><div class="twd-sk-inner twd-sk-footer__bottom-inner">'
			. self::copyright( $profile )
			. ( $meta ? '<div class="twd-sk-footer__meta">' . implode( '', $meta ) . '</div>' : '' )
			. self::legal_links( $profile )
			. '</div></div></footer></div>';
		return $out;
	}

	public static function render_minimal_footer() {
		$name   = function_exists( 'get_bloginfo' ) ? (string) get_bloginfo( 'name' ) : '';
		$policy = function_exists( 'get_privacy_policy_url' ) ? (string) get_privacy_policy_url() : '';
		$legal  = '' !== $policy ? '<a class="twd-sk-footer__legal-link" href="' . self::url( $policy ) . '">Privacy policy</a>' : '<span>Privacy policy</span>';
		return '<div class="twd-sk-page twd-sk-chrome"><footer class="twd-sk-footer twd-sk-footer--simple twd-sk-footer--cols-1"><div class="twd-sk-footer__bottom"><div class="twd-sk-inner twd-sk-footer__bottom-inner">'
			. '<p class="twd-sk-footer__copy">&copy; ' . self::esc( gmdate( 'Y' ) ) . ' ' . self::esc( $name ) . '</p><nav aria-label="Legal"><ul class="twd-sk-footer__legal"><li>' . $legal . '</li></ul></nav>'
			. '</div></div></footer></div>';
	}
}
