<?php
/**
 * TWD_SK_Site: the site-wide style settings behind the Site tab.
 *
 * Which pack is active, which tokens a person may override, and the rules for saving
 * an override. Everything is validated by TWD_SK_Packs (strict patterns, bundled fonts
 * only, size floors) and checked by TWD_SK_Contrast before anything is stored.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class TWD_SK_Site {

	/** The tokens the Site tab lets a person change, in display order. group, token, label, kind. */
	public static function editable() {
		return array(
			array( 'colours', 'color-primary', 'Button colour', 'color' ),
			array( 'colours', 'color-primary-hover', 'Button hover colour', 'color' ),
			array( 'colours', 'color-on-primary', 'Button text', 'color' ),
			array( 'colours', 'color-band', 'Band colour', 'color' ),
			array( 'colours', 'color-on-band', 'Text on bands', 'color' ),
			array( 'colours', 'color-accent-on-dark', 'Accent on bands', 'color' ),
			array( 'colours', 'color-title', 'Titles and dark text', 'color' ),
			array( 'colours', 'color-heading', 'Headings', 'color' ),
			array( 'colours', 'color-body', 'Body text', 'color' ),
			array( 'colours', 'color-bg', 'Page background', 'color' ),
			array( 'colours', 'color-surface', 'Card background', 'color' ),
			array( 'colours', 'color-tint', 'Tinted section background', 'color' ),
			array( 'colours', 'color-accent', 'Accent', 'color' ),
			array( 'colours', 'color-eyebrow', 'Small labels', 'color' ),
			array( 'colours', 'color-accent-text', 'Link and accent text', 'color' ),
			array( 'fonts', 'font-heading', 'Heading font', 'font' ),
			array( 'fonts', 'font-body', 'Body font', 'font' ),
			array( 'shape', 'radius-btn', 'Button corners', 'radius' ),
			array( 'shape', 'radius-card', 'Card corners', 'radius' ),
			array( 'shape', 'radius-image', 'Image corners', 'radius' ),
		);
	}

	public static function editable_names() {
		$names = array();
		foreach ( self::editable() as $e ) {
			$names[] = $e[1];
		}
		return $names;
	}

	/** Every pack with its full token set, so the pop-up can preview a pack without a round trip. */
	public static function pack_list() {
		$out = array();
		foreach ( TWD_SK_Packs::packs() as $slug => $pack ) {
			$out[] = array( 'slug' => $slug, 'name' => $pack['name'], 'description' => $pack['description'], 'tokens' => $pack['tokens'] );
		}
		return $out;
	}

	/** Font name => the CSS font-family value, so the pop-up can preview a font. */
	private static function font_css_map() {
		$map = array();
		foreach ( array_keys( TWD_SK_Packs::fonts() ) as $name ) {
			$map[ $name ] = TWD_SK_Packs::font_css_value( $name );
		}
		return $map;
	}

	/** Everything the Site tab needs to draw itself. */
	public static function state() {
		$active    = TWD_SK_Packs::active_slug();
		$effective = TWD_SK_Packs::active_tokens();
		return array(
			'active'    => $active,
			'packs'     => self::pack_list(),
			'overrides' => (object) TWD_SK_Packs::overrides(),
			'effective' => $effective,
			'editable'  => array_map( function ( $e ) {
				return array( 'group' => $e[0], 'token' => $e[1], 'label' => $e[2], 'kind' => $e[3] );
			}, self::editable() ),
			'fonts'     => array_keys( TWD_SK_Packs::fonts() ),
			'font_css'  => self::font_css_map(),
			'pairs'     => array_map( function ( $p ) {
				return array( 'id' => $p[0], 'level' => $p[1], 'label' => $p[2], 'fg' => $p[3], 'bg' => $p[4] );
			}, TWD_SK_Contrast::pairs() ),
			'contrast'  => TWD_SK_Contrast::check( $effective ),
		);
	}

	/**
	 * Apply a style change: optionally a different pack, and a full set of overrides.
	 *
	 * @param string|null $pack      Pack slug to switch to, or null to keep the active one.
	 * @param array|null  $overrides Full override map to store, or null to keep (or, on a pack switch, clear).
	 * @return array|WP_Error The new state, plus any warnings.
	 */
	public static function apply( $pack, $overrides ) {
		$packs   = TWD_SK_Packs::packs();
		$current = TWD_SK_Packs::active_slug();
		$target  = ( null === $pack || '' === $pack ) ? $current : $pack;
		if ( ! is_string( $target ) || ! isset( $packs[ $target ] ) ) {
			return new WP_Error( 'twd_sk_unknown_pack', 'That style pack does not exist.' );
		}

		// Switching pack starts from the new pack's own look unless overrides are sent with it.
		if ( null === $overrides ) {
			$overrides = ( $target !== $current ) ? array() : TWD_SK_Packs::overrides();
		}
		if ( ! is_array( $overrides ) ) {
			return new WP_Error( 'twd_sk_bad_tokens', 'The colour and font changes must be a list of names and values.' );
		}

		$allowed = self::editable_names();
		foreach ( array_keys( $overrides ) as $name ) {
			if ( ! is_string( $name ) || ! in_array( $name, $allowed, true ) ) {
				return new WP_Error( 'twd_sk_bad_tokens', 'You cannot change "' . ( is_string( $name ) ? $name : '?' ) . '" from here.' );
			}
		}
		$check = TWD_SK_Packs::validate_tokens( $overrides, false );
		if ( $check['errors'] ) {
			return new WP_Error( 'twd_sk_bad_tokens', 'Some values are not allowed: ' . implode( ' ', $check['errors'] ) . ' Text sizes cannot go below the readable minimums.' );
		}

		// Only keep values that differ from the pack, so Reset and a pack switch stay clean.
		$base  = $packs[ $target ]['tokens'];
		$store = array();
		foreach ( $check['valid'] as $name => $value ) {
			if ( ! isset( $base[ $name ] ) || strtolower( $base[ $name ] ) !== strtolower( $value ) ) {
				$store[ $name ] = $value;
			}
		}

		$effective = array_merge( $base, $store );
		$contrast  = TWD_SK_Contrast::check( $effective );
		if ( $contrast['blocking'] ) {
			return new WP_Error( 'twd_sk_contrast', 'Some text would be hard to read, so nothing was saved: ' . implode( '; ', array_map( function ( $i ) {
				return $i['label'] . ' (' . $i['ratio'] . ':1, needs 4.5:1)';
			}, $contrast['blocking'] ) ) . '.', array( 'contrast' => $contrast ) );
		}

		if ( $target !== $current ) {
			$set = TWD_SK_Packs::set_active( $target );
			if ( is_wp_error( $set ) ) {
				return $set;
			}
		}
		$saved = TWD_SK_Packs::set_overrides( $store );
		if ( is_wp_error( $saved ) ) {
			return $saved;
		}
		return self::state();
	}

	/** Remove every override. The pack stays. */
	public static function reset() {
		TWD_SK_Packs::clear_overrides();
		return self::state();
	}
}
