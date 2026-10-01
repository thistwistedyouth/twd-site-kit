<?php
/**
 * TWD_SK_Packs: style packs, design tokens and the bundled fonts.
 *
 * A style pack is a JSON file in packs/ holding design tokens only: colours,
 * two fonts, radii, spacing, button style. A pack never holds CSS rules or
 * markup. Every token value is validated against a strict pattern before it
 * can reach a stylesheet, so a pack (or a per-site override) cannot inject
 * anything but a colour, a length, a number, a known font or a keyword.
 *
 * Tokens become CSS custom properties on :root named --twd-site-<token>. They
 * are inert on their own: every actual style rule lives in
 * assets/twd-site-kit.css and is scoped under .twd-sk-page.
 *
 * Fonts are bundled (assets/fonts, Latin subset, woff2, open licences) so
 * client sites make no requests to Google Fonts. Only the two fonts a pack
 * actually uses are declared with @font-face.
 *
 * Settings (options):
 *  - twd_sk_pack    slug of the active pack (default sage)
 *  - twd_sk_tokens  array of per-site token overrides (validated like a pack)
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class TWD_SK_Packs {

	const OPTION_PACK     = 'twd_sk_pack';
	const OPTION_TOKENS   = 'twd_sk_tokens';
	const DEFAULT_PACK    = 'sage';
	const VAR_PREFIX      = '--twd-site-';
	const LATIN_RANGE     = 'U+0000-00FF,U+0131,U+0152-0153,U+02BB-02BC,U+02C6,U+02DA,U+02DC,U+0304,U+0308,U+0329,U+2000-206F,U+20AC,U+2122,U+2191,U+2193,U+2212,U+2215,U+FEFF,U+FFFD';

	// -- Fonts -------------------------------------------------------------

	/**
	 * Fonts a pack may use. Files with an empty list are system stacks.
	 */
	public static function fonts() {
		return array(
			'Cormorant Garamond' => array(
				'stack' => 'Georgia, "Times New Roman", serif',
				'files' => array(
					array( 'file' => 'cormorant-garamond-latin-wght-normal.woff2', 'style' => 'normal', 'weight' => '300 700' ),
					array( 'file' => 'cormorant-garamond-latin-wght-italic.woff2', 'style' => 'italic', 'weight' => '300 700' ),
				),
			),
			'Lora'               => array(
				'stack' => 'Georgia, "Times New Roman", serif',
				'files' => array(
					array( 'file' => 'lora-latin-wght-normal.woff2', 'style' => 'normal', 'weight' => '400 700' ),
					array( 'file' => 'lora-latin-wght-italic.woff2', 'style' => 'italic', 'weight' => '400 700' ),
				),
			),
			'Jost'               => array(
				'stack' => '"Helvetica Neue", Arial, sans-serif',
				'files' => array(
					array( 'file' => 'jost-latin-wght-normal.woff2', 'style' => 'normal', 'weight' => '100 900' ),
				),
			),
			'Nunito'             => array(
				'stack' => '"Helvetica Neue", Arial, sans-serif',
				'files' => array(
					array( 'file' => 'nunito-latin-wght-normal.woff2', 'style' => 'normal', 'weight' => '200 1000' ),
				),
			),
			'System serif'       => array(
				'stack' => 'Georgia, "Times New Roman", serif',
				'files' => array(),
			),
			'System sans'        => array(
				'stack' => '-apple-system, "Segoe UI", "Helvetica Neue", Arial, sans-serif',
				'files' => array(),
			),
		);
	}

	// -- Token schema ------------------------------------------------------

	/**
	 * Every token, with its type. A pack must define all of them.
	 * Types: color, length, weight, line, font, shadow, position, or an array of
	 * allowed keywords.
	 */
	public static function schema() {
		$s = array();
		foreach ( array(
			'color-bg', 'color-surface', 'color-tint', 'color-band',
			'color-body', 'color-muted', 'color-heading', 'color-title',
			'color-primary', 'color-primary-hover', 'color-on-primary',
			'color-accent', 'color-on-band', 'color-on-image', 'color-border',
			'color-eyebrow', 'color-accent-text', 'color-accent-on-dark',
			'color-overlay-top', 'color-overlay-mid', 'color-overlay-bottom',
			'color-modal-overlay',
		) as $name ) {
			$s[ $name ] = 'color';
		}
		$s['font-heading']  = 'font';
		$s['font-body']     = 'font';
		$s['heading-style'] = array( 'normal', 'italic' );
		$s['title-style']   = array( 'normal', 'italic' );
		$s['heading-align'] = array( 'left', 'center' );
		foreach ( array( 'heading-weight', 'title-weight', 'btn-weight', 'eyebrow-weight' ) as $name ) {
			$s[ $name ] = 'weight';
		}
		$s['line-body']    = 'line';
		$s['line-heading'] = 'line';
		foreach ( array(
			'size-body', 'size-small', 'size-lead',
			'size-h1', 'size-h1-sm', 'size-page-title', 'size-page-title-sm',
			'size-h2', 'size-h2-sm', 'size-h3', 'size-title',
			'btn-size', 'btn-pad-y', 'btn-pad-x', 'btn-spacing',
			'eyebrow-size', 'eyebrow-spacing',
			'radius-btn', 'radius-card', 'radius-image', 'radius-field', 'radius-pill',
			'card-accent-width', 'card-pad', 'card-pad-sm',
			'container', 'container-narrow', 'gutter', 'gutter-sm',
			'section-y', 'section-y-sm', 'gap-lg', 'gap-md', 'gap-sm',
			'hero-min-height', 'hero-min-height-sm',
		) as $name ) {
			$s[ $name ] = 'length';
		}
		$s['shadow-card']    = 'shadow';
		$s['shadow-image']   = 'shadow';
		$s['image-position'] = 'position';
		return $s;
	}

	public static function token_names() {
		return array_keys( self::schema() );
	}

	// -- Validation --------------------------------------------------------

	private static function color_pattern() {
		return '(?:#[0-9a-fA-F]{8}|#[0-9a-fA-F]{6}|#[0-9a-fA-F]{3}|rgba?\(\s*\d{1,3}\s*,\s*\d{1,3}\s*,\s*\d{1,3}\s*(?:,\s*(?:0|1|0?\.\d{1,3}|1\.0+)\s*)?\))';
	}

	private static function valid_color( $v ) {
		if ( 'transparent' === $v ) {
			return true;
		}
		if ( ! preg_match( '/^' . self::color_pattern() . '$/', $v ) ) {
			return false;
		}
		// rgb channels must be 0-255.
		if ( preg_match_all( '/\(\s*(\d{1,3})\s*,\s*(\d{1,3})\s*,\s*(\d{1,3})/', $v, $m ) ) {
			foreach ( array( 1, 2, 3 ) as $i ) {
				if ( (int) $m[ $i ][0] > 255 ) {
					return false;
				}
			}
		}
		return true;
	}

	private static function valid_length( $v ) {
		// No negative values, no viewport units, no functions. Plain lengths only.
		if ( '0' === $v ) {
			return true;
		}
		if ( ! preg_match( '/^(\d{1,4}(?:\.\d{1,3})?)(px|rem|em|%)$/', $v, $m ) ) {
			return false;
		}
		return (float) $m[1] <= 2000;
	}

	private static function valid_shadow( $v ) {
		if ( 'none' === $v ) {
			return true;
		}
		$len = '(?:0|-?\d{1,3}(?:\.\d{1,2})?px)';
		return 1 === preg_match( '/^' . $len . '\s+' . $len . '\s+' . $len . '(?:\s+' . $len . ')?\s+' . self::color_pattern() . '$/', $v );
	}

	private static function valid_position( $v ) {
		$part = '(?:left|center|right|top|bottom|100%|[1-9]?\d%)';
		return 1 === preg_match( '/^' . $part . '(?: ' . $part . ')?$/', $v );
	}

	/**
	 * Text size floors at desktop: body copy and the lead 16px, small text, eyebrows
	 * and buttons 14px. Only px values can be compared; other units pass.
	 */
	public static function meets_min_size( $name, $value ) {
		$floors = array(
			'size-body'    => 16,
			'size-lead'    => 16,
			'size-small'   => 14,
			'eyebrow-size' => 14,
			'btn-size'     => 14,
		);
		if ( ! isset( $floors[ $name ] ) || 1 !== preg_match( '/^(\d+(?:\.\d+)?)px$/', trim( $value ), $m ) ) {
			return true;
		}
		return (float) $m[1] >= $floors[ $name ];
	}

	/**
	 * Validate one token. Returns true or false.
	 */
	public static function valid_token( $name, $value ) {
		$schema = self::schema();
		if ( ! isset( $schema[ $name ] ) || ! is_string( $value ) ) {
			return false;
		}
		$value = trim( $value );
		$type  = $schema[ $name ];

		if ( is_array( $type ) ) {
			return in_array( $value, $type, true );
		}
		switch ( $type ) {
			case 'color':
				return self::valid_color( $value );
			case 'length':
				if ( ! self::valid_length( $value ) ) {
					return false;
				}
				return self::meets_min_size( $name, $value );
			case 'weight':
				return 1 === preg_match( '/^[1-9]00$/', $value ) || '1000' === $value;
			case 'line':
				return 1 === preg_match( '/^(?:1|1\.\d{1,2}|2|2\.[0-5])$/', $value );
			case 'font':
				return isset( self::fonts()[ $value ] );
			case 'shadow':
				return self::valid_shadow( $value );
			case 'position':
				return self::valid_position( $value );
		}
		return false;
	}

	/**
	 * Check a set of tokens.
	 *
	 * @param array $tokens   name => value
	 * @param bool  $complete Require every token in the schema (true for a pack).
	 * @return array { valid: name => value (only the good ones), errors: list of strings }
	 */
	public static function validate_tokens( $tokens, $complete ) {
		$out = array(
			'valid'  => array(),
			'errors' => array(),
		);
		if ( ! is_array( $tokens ) ) {
			$out['errors'][] = 'tokens must be an object of name and value pairs';
			return $out;
		}
		$schema = self::schema();
		foreach ( $tokens as $name => $value ) {
			if ( ! is_string( $name ) || ! isset( $schema[ $name ] ) ) {
				$out['errors'][] = 'unknown token: ' . ( is_string( $name ) ? $name : '(not a name)' );
				continue;
			}
			if ( self::valid_token( $name, $value ) ) {
				$out['valid'][ $name ] = trim( $value );
			} else {
				$out['errors'][] = 'invalid value for ' . $name;
			}
		}
		if ( $complete ) {
			foreach ( array_keys( $schema ) as $name ) {
				if ( ! array_key_exists( $name, $tokens ) ) {
					$out['errors'][] = 'missing token: ' . $name;
				}
			}
		}
		return $out;
	}

	// -- Reading packs -----------------------------------------------------

	private static function packs_dir() {
		$base = defined( 'TWD_SK_PATH' ) ? TWD_SK_PATH : dirname( __DIR__ ) . '/';
		return $base . 'packs/';
	}

	/**
	 * Read and validate one pack file.
	 *
	 * @return array|null array( slug, name, description, tokens ) or null if it is not a valid pack.
	 */
	public static function read_pack_file( $path ) {
		if ( ! is_string( $path ) || ! is_readable( $path ) ) {
			return null;
		}
		$data = json_decode( (string) file_get_contents( $path ), true );
		if ( ! is_array( $data ) ) {
			return null;
		}
		$slug = basename( $path, '.json' );
		if ( ! isset( $data['slug'], $data['name'], $data['tokens'] ) || $data['slug'] !== $slug || ! is_string( $data['name'] ) || ! preg_match( '/^[a-z][a-z0-9-]{1,30}$/', $slug ) ) {
			return null;
		}
		$check = self::validate_tokens( $data['tokens'], true );
		if ( $check['errors'] ) {
			return null;
		}
		// Which header and footer layout this pack prefers. Anything not recognised falls back to the defaults.
		$chrome = array( 'header' => 'bar', 'footer' => 'columns' );
		if ( isset( $data['chrome'] ) && is_array( $data['chrome'] ) ) {
			if ( isset( $data['chrome']['header'] ) && is_string( $data['chrome']['header'] ) && isset( TWD_SK_Chrome::header_variants()[ $data['chrome']['header'] ] ) ) {
				$chrome['header'] = $data['chrome']['header'];
			}
			if ( isset( $data['chrome']['footer'] ) && is_string( $data['chrome']['footer'] ) && isset( TWD_SK_Chrome::footer_variants()[ $data['chrome']['footer'] ] ) ) {
				$chrome['footer'] = $data['chrome']['footer'];
			}
		}
		return array(
			'slug'        => $slug,
			'name'        => $data['name'],
			'description' => isset( $data['description'] ) && is_string( $data['description'] ) ? $data['description'] : '',
			'tokens'      => $check['valid'],
			'chrome'      => $chrome,
		);
	}

	/**
	 * Every valid pack, keyed by slug. A broken pack file is skipped, never fatal.
	 */
	public static function packs() {
		$out = array();
		foreach ( (array) glob( self::packs_dir() . '*.json' ) as $file ) {
			$pack = self::read_pack_file( $file );
			if ( $pack ) {
				$out[ $pack['slug'] ] = $pack;
			}
		}
		ksort( $out );
		return $out;
	}

	/** The active pack's preferred header and footer layouts: array( header, footer ). */
	public static function chrome_defaults() {
		$packs = self::packs();
		$slug  = self::active_slug();
		return isset( $packs[ $slug ]['chrome'] ) ? $packs[ $slug ]['chrome'] : array( 'header' => 'bar', 'footer' => 'columns' );
	}

	public static function active_slug() {
		$slug  = function_exists( 'get_option' ) ? get_option( self::OPTION_PACK, self::DEFAULT_PACK ) : self::DEFAULT_PACK;
		$packs = self::packs();
		if ( is_string( $slug ) && isset( $packs[ $slug ] ) ) {
			return $slug;
		}
		return isset( $packs[ self::DEFAULT_PACK ] ) ? self::DEFAULT_PACK : (string) key( $packs );
	}

	/**
	 * Make a pack the active one.
	 *
	 * @return true|WP_Error
	 */
	public static function set_active( $slug ) {
		$packs = self::packs();
		if ( ! is_string( $slug ) || ! isset( $packs[ $slug ] ) ) {
			return new WP_Error( 'twd_sk_unknown_pack', 'There is no style pack called "' . ( is_string( $slug ) ? $slug : '' ) . '". Available: ' . implode( ', ', array_keys( $packs ) ) . '.' );
		}
		update_option( self::OPTION_PACK, $slug );
		return true;
	}

	/**
	 * The tokens in force: the active pack, with any valid per-site overrides on top.
	 */
	public static function active_tokens() {
		$packs  = self::packs();
		$slug   = self::active_slug();
		$tokens = isset( $packs[ $slug ] ) ? $packs[ $slug ]['tokens'] : array();

		$overrides = function_exists( 'get_option' ) ? get_option( self::OPTION_TOKENS, array() ) : array();
		if ( is_array( $overrides ) && $overrides ) {
			$check  = self::validate_tokens( $overrides, false );
			$tokens = array_merge( $tokens, $check['valid'] );
		}
		return $tokens;
	}

	/** The stored per-site overrides, validated. Unknown or invalid entries are ignored. */
	public static function overrides() {
		$stored = function_exists( 'get_option' ) ? get_option( self::OPTION_TOKENS, array() ) : array();
		if ( ! is_array( $stored ) || ! $stored ) {
			return array();
		}
		return self::validate_tokens( $stored, false )['valid'];
	}

	/**
	 * Replace the per-site overrides. Every value is validated; nothing is stored if any is not.
	 *
	 * @return true|WP_Error
	 */
	public static function set_overrides( $tokens ) {
		$check = self::validate_tokens( $tokens, false );
		if ( $check['errors'] ) {
			return new WP_Error( 'twd_sk_bad_tokens', implode( ' ', $check['errors'] ) );
		}
		update_option( self::OPTION_TOKENS, $check['valid'] );
		return true;
	}

	public static function clear_overrides() {
		update_option( self::OPTION_TOKENS, array() );
	}

	/** @font-face blocks for every bundled font. The Site tab uses it so a pack can be previewed live. */
	public static function all_fonts_css( $base_url ) {
		$css = '';
		foreach ( array_keys( self::fonts() ) as $family ) {
			$css .= self::font_face_css( array( 'font-heading' => $family ), $base_url );
		}
		return $css;
	}

	// -- CSS output --------------------------------------------------------

	/** The CSS value for a font token: the family first (if bundled), then its fallback stack. */
	public static function font_css_value( $name ) {
		$fonts = self::fonts();
		if ( ! isset( $fonts[ $name ] ) ) {
			return '';
		}
		$stack = $fonts[ $name ]['stack'];
		return ( 0 === strpos( $name, 'System' ) ) ? $stack : '"' . $name . '", ' . $stack;
	}

	/**
	 * :root custom properties for a set of (already validated) tokens.
	 */
	public static function tokens_css( $tokens ) {
		$fonts = self::fonts();
		$css   = '';
		foreach ( self::token_names() as $name ) {
			if ( ! isset( $tokens[ $name ] ) || ! self::valid_token( $name, $tokens[ $name ] ) ) {
				continue;
			}
			$value = $tokens[ $name ];
			if ( 'font-heading' === $name || 'font-body' === $name ) {
				$value = self::font_css_value( $value );
			}
			$css .= self::VAR_PREFIX . $name . ':' . $value . ';';
		}
		return ':root{' . $css . '}';
	}

	/**
	 * @font-face blocks for the fonts these tokens use, and no others.
	 *
	 * @param array  $tokens
	 * @param string $base_url URL of the assets/fonts folder, with a trailing slash.
	 */
	public static function font_face_css( $tokens, $base_url ) {
		$fonts = self::fonts();
		$used  = array();
		foreach ( array( 'font-heading', 'font-body' ) as $name ) {
			if ( isset( $tokens[ $name ] ) && isset( $fonts[ $tokens[ $name ] ] ) ) {
				$used[ $tokens[ $name ] ] = true;
			}
		}
		$css = '';
		foreach ( array_keys( $used ) as $family ) {
			foreach ( $fonts[ $family ]['files'] as $f ) {
				$css .= '@font-face{font-family:"' . $family . '";font-style:' . $f['style'] . ';font-weight:' . $f['weight']
					. ';font-display:swap;src:url("' . $base_url . $f['file'] . '") format("woff2");unicode-range:' . self::LATIN_RANGE . ';}';
			}
		}
		return $css;
	}

	/**
	 * Everything printed inline after the kit stylesheet: font faces, then tokens.
	 */
	public static function inline_css( $base_url ) {
		$tokens = self::active_tokens();
		return self::font_face_css( $tokens, $base_url ) . self::tokens_css( $tokens );
	}
}
