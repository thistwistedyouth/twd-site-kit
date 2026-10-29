<?php
/**
 * TWD_SK_Contrast: WCAG contrast checks on a set of design tokens.
 *
 * Pure functions. The Site tab uses it to block a save that would make text on a button or
 * a band unreadable, and to warn about weaker pairs. Colours with transparency cannot be
 * judged without knowing what is behind them, so they are listed as "not checked".
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class TWD_SK_Contrast {

	const MIN = 4.5;

	/**
	 * Parse a colour token into array( r, g, b ), or null when it cannot be judged
	 * (transparency, or a form that is not a plain colour).
	 */
	public static function parse( $value ) {
		if ( ! is_string( $value ) ) {
			return null;
		}
		$value = trim( $value );
		if ( preg_match( '/^#([0-9a-fA-F]{3})$/', $value, $m ) ) {
			$h = $m[1];
			return array( hexdec( $h[0] . $h[0] ), hexdec( $h[1] . $h[1] ), hexdec( $h[2] . $h[2] ) );
		}
		if ( preg_match( '/^#([0-9a-fA-F]{6})$/', $value, $m ) ) {
			return array( hexdec( substr( $m[1], 0, 2 ) ), hexdec( substr( $m[1], 2, 2 ) ), hexdec( substr( $m[1], 4, 2 ) ) );
		}
		if ( preg_match( '/^rgb\(\s*(\d{1,3})\s*,\s*(\d{1,3})\s*,\s*(\d{1,3})\s*\)$/', $value, $m ) ) {
			return array( min( 255, (int) $m[1] ), min( 255, (int) $m[2] ), min( 255, (int) $m[3] ) );
		}
		return null;
	}

	/**
	 * A colour with transparency laid over an opaque one (0.15.0): rgba( r, g, b, a ) or an 8-digit hex becomes
	 * the plain colour a visitor sees. An opaque colour comes back as it is; anything else is null.
	 */
	public static function flatten( $top, $under ) {
		$u = self::parse( $under );
		if ( null === $u ) {
			return null;
		}
		$t = self::parse( $top );
		if ( null !== $t ) {
			return sprintf( 'rgb(%d,%d,%d)', $t[0], $t[1], $t[2] );
		}
		$top = trim( (string) $top );
		$a   = null;
		if ( preg_match( '/^rgba\(\s*(\d{1,3})\s*,\s*(\d{1,3})\s*,\s*(\d{1,3})\s*,\s*(0|1|0?\.\d{1,3}|1\.0+)\s*\)$/', $top, $m ) ) {
			$t = array( min( 255, (int) $m[1] ), min( 255, (int) $m[2] ), min( 255, (int) $m[3] ) );
			$a = (float) $m[4];
		} elseif ( preg_match( '/^#([0-9a-fA-F]{8})$/', $top, $m ) ) {
			$t = array( hexdec( substr( $m[1], 0, 2 ) ), hexdec( substr( $m[1], 2, 2 ) ), hexdec( substr( $m[1], 4, 2 ) ) );
			$a = hexdec( substr( $m[1], 6, 2 ) ) / 255;
		} elseif ( 'transparent' === $top ) {
			$t = array( 0, 0, 0 );
			$a = 0;
		}
		if ( null === $a ) {
			return null;
		}
		$out = array();
		foreach ( array( 0, 1, 2 ) as $i ) {
			$out[] = (int) round( $t[ $i ] * $a + $u[ $i ] * ( 1 - $a ) );
		}
		return sprintf( 'rgb(%d,%d,%d)', $out[0], $out[1], $out[2] );
	}

	/** A reference mid grey standing in for a photograph behind an overlay (0.15.0). */
	const PHOTO = '#808080';

	public static function luminance( $rgb ) {
		$c = array();
		foreach ( $rgb as $v ) {
			$v   = $v / 255;
			$c[] = ( $v <= 0.03928 ) ? $v / 12.92 : pow( ( $v + 0.055 ) / 1.055, 2.4 );
		}
		return 0.2126 * $c[0] + 0.7152 * $c[1] + 0.0722 * $c[2];
	}

	/** Contrast ratio between two colour values, or null if either cannot be judged. */
	public static function ratio( $a, $b ) {
		$x = self::parse( $a );
		$y = self::parse( $b );
		if ( null === $x || null === $y ) {
			return null;
		}
		$lx = self::luminance( $x );
		$ly = self::luminance( $y );
		if ( $lx < $ly ) {
			$t  = $lx;
			$lx = $ly;
			$ly = $t;
		}
		return ( $lx + 0.05 ) / ( $ly + 0.05 );
	}

	/**
	 * The pairs that matter. "block" pairs stop a save; "warn" pairs only warn.
	 * Each: id, level, label, fg token, bg token.
	 */
	public static function pairs() {
		return array(
			array( 'button_text', 'block', 'Button text on the button colour', 'color-on-primary', 'color-primary' ),
			array( 'button_hover', 'block', 'Button text on the button hover colour', 'color-on-primary', 'color-primary-hover' ),
			array( 'light_button', 'block', 'Dark text on light buttons', 'color-title', 'color-surface' ),
			array( 'tint_button', 'block', 'Button text on tinted sections', 'color-surface', 'color-title' ),
			array( 'band_text', 'block', 'Text on bands', 'color-on-band', 'color-band' ),
			array( 'band_accent', 'block', 'Accent text on bands', 'color-accent-on-dark', 'color-band' ),
			array( 'body_bg', 'warn', 'Body text on the page background', 'color-body', 'color-bg' ),
			array( 'body_surface', 'warn', 'Body text on cards', 'color-body', 'color-surface' ),
			array( 'muted_surface', 'warn', 'Small grey text on cards', 'color-muted', 'color-surface' ),
			array( 'heading_bg', 'warn', 'Headings on the page background', 'color-heading', 'color-bg' ),
			array( 'heading_surface', 'warn', 'Headings on cards', 'color-heading', 'color-surface' ),
			array( 'eyebrow_bg', 'warn', 'Small labels on the page background', 'color-eyebrow', 'color-bg' ),
			array( 'accent_text_bg', 'warn', 'Link and accent text on the page background', 'color-accent-text', 'color-bg' ),
			array( 'title_tint', 'warn', 'Titles on tinted sections', 'color-title', 'color-tint' ),
			array( 'primary_bg', 'warn', 'Button colour against the page background', 'color-primary', 'color-bg' ),
			// 0.15.0: words in the frame over a picture. The frame tint lies over the overlay over a mid grey (a photograph
			// cannot be known), so the pair carries the layers under its background, from the bottom up.
			array( 'frame_text', 'warn', 'Words in the frame over a picture', 'color-on-image', 'color-frame', array( self::PHOTO, 'color-overlay-mid' ) ),
			// 18a (29 Oct 2026): the box styles choose their colours by tone; these are the pairs the tones add.
			array( 'title_surface', 'warn', 'Titles in a box on a tinted section', 'color-title', 'color-surface' ),
			array( 'box_band', 'warn', 'Words in a box on a band', 'color-on-band', 'color-frame', array( 'color-band' ) ),
			array( 'box_picture', 'warn', 'Words in a box in the frame over a picture', 'color-on-image', 'color-frame', array( self::PHOTO, 'color-overlay-mid', 'color-frame' ) ),
		);
	}

	/**
	 * Every box, border, line and frame style on every section tone (18a, 29 Oct 2026): the words' colour, the
	 * background the words sit on, and the layers under it, exactly as the stylesheet draws them. A test runs each
	 * on every pack at 4.5:1, and checks the stylesheet carries the rules, so an unreadable box cannot come back.
	 *
	 * @return array of array( style class, tone class, fg token, bg token or literal, layers under the bg (bottom up) )
	 */
	public static function style_pairs() {
		$photo = array( self::PHOTO, 'color-overlay-mid' );
		$out   = array();
		// Boxes with a background of their own: the note box on a paragraph, the Soft card round a section's words.
		foreach ( array( 'twd-sk-notebox', 'twd-sk-box-card' ) as $style ) {
			$out[] = array( $style, 'twd-sk-tone-base', 'color-body', 'color-surface', array() );
			$out[] = array( $style, 'twd-sk-tone-base', 'color-title', 'color-surface', array() );
			$out[] = array( $style, 'twd-sk-tone-surface', 'color-body', 'color-bg', array() );
			$out[] = array( $style, 'twd-sk-tone-surface', 'color-title', 'color-bg', array() );
			$out[] = array( $style, 'twd-sk-tone-tint', 'color-title', 'color-surface', array() );
			$out[] = array( $style, 'twd-sk-tone-band', 'color-on-band', 'color-frame', array( 'color-band' ) );
			$out[] = array( $style, 'twd-sk-text--picture', 'color-on-image', 'color-frame', array_merge( $photo, array( 'color-frame' ) ) );
		}
		// Lines and borders: no background of their own, the words sit on the tone itself.
		foreach ( array( 'twd-sk-box-outline', 'twd-sk-border-thin', 'twd-sk-border-accent', 'twd-sk-quoteline' ) as $style ) {
			$out[] = array( $style, 'twd-sk-tone-base', 'color-body', 'color-bg', array() );
			$out[] = array( $style, 'twd-sk-tone-surface', 'color-body', 'color-surface', array() );
			$out[] = array( $style, 'twd-sk-tone-tint', 'color-title', 'color-tint', array() );
			$out[] = array( $style, 'twd-sk-tone-band', 'color-on-band', 'color-band', array() );
			$out[] = array( $style, 'twd-sk-text--picture', 'color-on-image', 'color-frame', $photo );
		}
		// The frame over a picture at its three depths (Lighter is the frame tint at 60 percent; Stronger is it twice).
		$out[] = array( 'twd-sk-frame', 'twd-sk-text--picture', 'color-on-image', 'color-frame', $photo );
		$out[] = array( 'twd-sk-frame--light', 'twd-sk-text--picture', 'color-on-image', self::fade( 'color-frame', 0.6 ), $photo );
		$out[] = array( 'twd-sk-frame--strong', 'twd-sk-text--picture', 'color-on-image', 'color-frame', array_merge( $photo, array( 'color-frame' ) ) );
		return $out;
	}

	/** A token name or colour marked to be read at a fraction of its opacity: resolved by ratio_for(). */
	public static function fade( $value, $factor ) {
		return array( 'fade', $value, (float) $factor );
	}

	/** The ratio of one style pair against a pack's tokens (null when a colour cannot be read). */
	public static function ratio_for( $pair, $tokens ) {
		list( , , $fg, $bg, $layers ) = $pair;
		$under = empty( $layers ) ? null : self::stack( $layers, $tokens );
		if ( is_array( $bg ) && 'fade' === $bg[0] ) {
			$value = isset( $tokens[ $bg[1] ] ) ? $tokens[ $bg[1] ] : $bg[1];
			$bg    = self::with_alpha( $value, $bg[2] );
		} else {
			$bg = isset( $tokens[ $bg ] ) ? $tokens[ $bg ] : $bg;
		}
		if ( ! isset( $tokens[ $fg ] ) || null === $bg ) {
			return null;
		}
		if ( null === $under && null === self::parse( $bg ) ) {
			return null; // A see-through colour with nothing known under it cannot be judged.
		}
		$back = null === $under ? $bg : self::flatten( $bg, $under );
		return null === $back ? null : self::ratio( $tokens[ $fg ], $back );
	}

	/** A colour's alpha multiplied by a factor (an opaque colour becomes rgba at that factor); null when unreadable. */
	public static function with_alpha( $value, $factor ) {
		$value = trim( (string) $value );
		$t     = self::parse( $value );
		$a     = 1.0;
		if ( null === $t ) {
			if ( preg_match( '/^rgba\(\s*(\d{1,3})\s*,\s*(\d{1,3})\s*,\s*(\d{1,3})\s*,\s*(0|1|0?\.\d{1,3}|1\.0+)\s*\)$/', $value, $m ) ) {
				$t = array( min( 255, (int) $m[1] ), min( 255, (int) $m[2] ), min( 255, (int) $m[3] ) );
				$a = (float) $m[4];
			} elseif ( preg_match( '/^#([0-9a-fA-F]{8})$/', $value, $m ) ) {
				$t = array( hexdec( substr( $m[1], 0, 2 ) ), hexdec( substr( $m[1], 2, 2 ) ), hexdec( substr( $m[1], 4, 2 ) ) );
				$a = hexdec( substr( $m[1], 6, 2 ) ) / 255;
			} else {
				return null;
			}
		}
		return sprintf( 'rgba(%d,%d,%d,%s)', $t[0], $t[1], $t[2], rtrim( rtrim( number_format( $a * $factor, 3, '.', '' ), '0' ), '.' ) );
	}

	/** Layers from the bottom up (a literal colour or a token name each) flattened to one opaque colour, or null. */
	public static function stack( $layers, $tokens ) {
		$base = null;
		foreach ( (array) $layers as $layer ) {
			$value = isset( $tokens[ $layer ] ) ? $tokens[ $layer ] : $layer;
			$base  = null === $base ? self::flatten( $value, '#000000' ) : self::flatten( $value, $base );
			if ( null === $base ) {
				return null;
			}
		}
		return $base;
	}

	/**
	 * Check a full set of tokens.
	 *
	 * @return array { blocking: [], warnings: [], unchecked: [] } each item: id, label, fg, bg, ratio
	 */
	public static function check( $tokens ) {
		$out = array( 'blocking' => array(), 'warnings' => array(), 'unchecked' => array() );
		foreach ( self::pairs() as $p ) {
			list( $id, $level, $label, $fg, $bg ) = $p;
			if ( ! isset( $tokens[ $fg ], $tokens[ $bg ] ) ) {
				continue;
			}
			$under = isset( $p[5] ) ? self::stack( $p[5], $tokens ) : null;
			$back  = null === $under ? $tokens[ $bg ] : self::flatten( $tokens[ $bg ], $under );
			$ratio = null === $back ? null : self::ratio( $tokens[ $fg ], $back );
			$item  = array( 'id' => $id, 'label' => $label, 'fg' => $tokens[ $fg ], 'bg' => null === $back ? $tokens[ $bg ] : $back, 'ratio' => null === $ratio ? null : round( $ratio, 2 ) );
			if ( null === $ratio ) {
				$out['unchecked'][] = $item;
			} elseif ( $ratio < self::MIN ) {
				$out[ 'block' === $level ? 'blocking' : 'warnings' ][] = $item;
			}
		}
		return $out;
	}
}
