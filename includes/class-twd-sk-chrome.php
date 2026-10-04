<?php
/**
 * TWD_SK_Chrome: the site header and footer, printed by the [twd_header] and [twd_footer] shortcodes.
 *
 * An Elementor Theme Builder template holds one Shortcode widget with each. The look comes from
 * the active style pack (which header and footer variant it prefers) and a per-site override;
 * the words and links come from the site profile (TWD_SK_Profile). Variants live here, in the
 * plugin, so a new site needs no layout work.
 *
 * Header variants: bar, centered, split, minimal. Footer variants: columns, band, centered, simple.
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
	const NAV_ID     = 'twd-sk-nav';

	public static function init() {
		add_shortcode( 'twd_header', array( __CLASS__, 'shortcode_header' ) );
		add_shortcode( 'twd_footer', array( __CLASS__, 'shortcode_footer' ) );
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
		);
	}

	/** Every CSS class this class prints. The stylesheet tests check each one is styled. */
	public static function classes() {
		$classes = array(
			'twd-sk-chrome', 'twd-sk-chrome--sticky', 'twd-sk-chrome--strip',
			'twd-sk-header', 'twd-sk-header--sticky', 'twd-sk-header--js', 'twd-sk-header--open',
			'twd-sk-header__strip', 'twd-sk-header__strip-inner', 'twd-sk-header__strip-link', 'twd-sk-header__inner',
			'twd-sk-header__logo', 'twd-sk-header__logo-img', 'twd-sk-header__logo-text', 'twd-sk-header__toggle', 'twd-sk-header__bars',
			'twd-sk-header__panel', 'twd-sk-header__nav', 'twd-sk-header__menu', 'twd-sk-header__item', 'twd-sk-header__item--parent',
			'twd-sk-header__link', 'twd-sk-header__sub', 'twd-sk-header__sublink', 'twd-sk-header__cta',
			'twd-sk-footer', 'twd-sk-footer--cols-1', 'twd-sk-footer--cols-2', 'twd-sk-footer--cols-3',
			'twd-sk-footer__main', 'twd-sk-footer__col', 'twd-sk-footer__logo-img', 'twd-sk-footer__name', 'twd-sk-footer__text',
			'twd-sk-footer__heading', 'twd-sk-footer__links', 'twd-sk-footer__link', 'twd-sk-footer__contact',
			'twd-sk-footer__reg', 'twd-sk-footer__reg-line', 'twd-sk-footer__bottom', 'twd-sk-footer__bottom-inner',
			'twd-sk-footer__copy', 'twd-sk-footer__legal', 'twd-sk-footer__legal-link',
		);
		foreach ( array_keys( self::header_variants() ) as $v ) {
			$classes[] = 'twd-sk-header--' . $v;
		}
		foreach ( array_keys( self::footer_variants() ) as $v ) {
			$classes[] = 'twd-sk-footer--' . $v;
		}
		return $classes;
	}

	public static function defaults() {
		return array(
			'header_variant' => '',     // '' means the pack's own choice
			'footer_variant' => '',
			'sticky'         => false,
			'show_button'    => true,
			'show_strip'     => false,
			'footer_columns' => 3,
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
				case 'show_button':
				case 'show_strip':
					if ( is_bool( $value ) || 0 === $value || 1 === $value || '0' === $value || '1' === $value ) {
						$out['valid'][ $key ] = (bool) $value;
					} else {
						$out['errors'][] = 'The ' . str_replace( '_', ' ', $key ) . ' setting must be on or off.';
					}
					break;
				case 'footer_columns':
					if ( ( is_int( $value ) || ( is_string( $value ) && ctype_digit( $value ) ) ) && (int) $value >= 1 && (int) $value <= 3 ) {
						$out['valid'][ $key ] = (int) $value;
					} else {
						$out['errors'][] = 'The footer can have one, two or three columns.';
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
		// A centred header has two rows, so it cannot be fixed to the top at a known height.
		$sticky = (bool) $s['sticky'] && 'centered' !== $h;
		return array(
			'header'      => $h,
			'footer'      => $f,
			'sticky'      => $sticky,
			'button'      => (bool) $s['show_button'],
			'strip'       => (bool) $s['show_strip'],
			'columns'     => (int) $s['footer_columns'],
			'sticky_note' => (bool) $s['sticky'] && 'centered' === $h,
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

	private static function logo_html( $profile, $img_class, $text_class, $lazy = false ) {
		$name = self::site_name( $profile );
		if ( $profile['logo_id'] && function_exists( 'wp_get_attachment_image_src' ) ) {
			$src = wp_get_attachment_image_src( (int) $profile['logo_id'], 'medium' );
			if ( is_array( $src ) && ! empty( $src[0] ) ) {
				$w = ! empty( $src[1] ) ? ' width="' . (int) $src[1] . '"' : '';
				$h = ! empty( $src[2] ) ? ' height="' . (int) $src[2] . '"' : '';
				return '<img class="' . $img_class . '" src="' . self::url( $src[0] ) . '" alt="' . esc_attr( $name ) . '"' . $w . $h . ( $lazy ? ' loading="lazy"' : '' ) . ' decoding="async">';
			}
		}
		return '<span class="' . $text_class . '">' . self::esc( $name ) . '</span>';
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

	// -- The header -------------------------------------------------------

	public static function render_header() {
		$profile = TWD_SK_Profile::get();
		// The site name falls back to the WordPress site title, so a missing name never drops the menu.
		if ( TWD_SK_Safe::on() || ! $profile['menu'] ) {
			return self::render_minimal_header();
		}
		$eff   = self::effective();
		$strip = $eff['strip'] && ( '' !== $profile['phone'] || '' !== $profile['email'] );
		$wrap  = 'twd-sk-page twd-sk-chrome' . ( $eff['sticky'] ? ' twd-sk-chrome--sticky' : '' ) . ( $eff['sticky'] && $strip ? ' twd-sk-chrome--strip' : '' );
		$cls   = 'twd-sk-header twd-sk-header--' . $eff['header'] . ( $eff['sticky'] ? ' twd-sk-header--sticky' : '' );

		$out  = '<div class="' . $wrap . '"><header class="' . $cls . '">';
		if ( $strip ) {
			$out .= '<div class="twd-sk-header__strip"><div class="twd-sk-inner twd-sk-header__strip-inner">'
				. self::phone_html( $profile['phone'], 'twd-sk-header__strip-link' ) . self::email_html( $profile['email'], 'twd-sk-header__strip-link' )
				. '</div></div>';
		}
		$out .= '<div class="twd-sk-inner twd-sk-header__inner">';
		$out .= '<a class="twd-sk-header__logo" href="' . self::url( self::home() ) . '">' . self::logo_html( $profile, 'twd-sk-header__logo-img', 'twd-sk-header__logo-text' ) . '</a>';
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
		return $out;
	}

	/** Plain and always safe: the site name and a few pages. Used in safe mode and whenever the profile is empty. */
	public static function render_minimal_header() {
		$name  = function_exists( 'get_bloginfo' ) ? (string) get_bloginfo( 'name' ) : '';
		$items = '';
		if ( function_exists( 'get_pages' ) ) {
			$pages = get_pages( array( 'sort_column' => 'menu_order,post_title', 'parent' => 0, 'number' => 6 ) );
			foreach ( is_array( $pages ) ? $pages : array() as $page ) {
				$items .= '<li class="twd-sk-header__item"><a class="twd-sk-header__link" href="' . self::url( get_permalink( $page->ID ) ) . '">' . self::esc( $page->post_title ) . '</a></li>';
			}
		}
		return '<div class="twd-sk-page twd-sk-chrome"><header class="twd-sk-header twd-sk-header--bar"><div class="twd-sk-inner twd-sk-header__inner">'
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
				$brand .= '<p class="twd-sk-footer__text">' . self::esc( $profile['footer_text'] ) . '</p>';
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

	public static function render_minimal_footer() {
		$name   = function_exists( 'get_bloginfo' ) ? (string) get_bloginfo( 'name' ) : '';
		$policy = function_exists( 'get_privacy_policy_url' ) ? (string) get_privacy_policy_url() : '';
		$legal  = '' !== $policy ? '<a class="twd-sk-footer__legal-link" href="' . self::url( $policy ) . '">Privacy policy</a>' : '<span>Privacy policy</span>';
		return '<div class="twd-sk-page twd-sk-chrome"><footer class="twd-sk-footer twd-sk-footer--simple twd-sk-footer--cols-1"><div class="twd-sk-footer__bottom"><div class="twd-sk-inner twd-sk-footer__bottom-inner">'
			. '<p class="twd-sk-footer__copy">&copy; ' . self::esc( gmdate( 'Y' ) ) . ' ' . self::esc( $name ) . '</p><nav aria-label="Legal"><ul class="twd-sk-footer__legal"><li>' . $legal . '</li></ul></nav>'
			. '</div></div></footer></div>';
	}
}
