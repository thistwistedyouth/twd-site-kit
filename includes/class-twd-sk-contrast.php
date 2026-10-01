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
		);
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
			$ratio = self::ratio( $tokens[ $fg ], $tokens[ $bg ] );
			$item  = array( 'id' => $id, 'label' => $label, 'fg' => $tokens[ $fg ], 'bg' => $tokens[ $bg ], 'ratio' => null === $ratio ? null : round( $ratio, 2 ) );
			if ( null === $ratio ) {
				$out['unchecked'][] = $item;
			} elseif ( $ratio < self::MIN ) {
				$out[ 'block' === $level ? 'blocking' : 'warnings' ][] = $item;
			}
		}
		return $out;
	}
}
