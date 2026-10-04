<?php
/**
 * TWD_SK_Starters: the Home, About and Contact starter pages, and the setup that creates them.
 *
 * The layouts are built from the registry's own skeletons, so they use only real components
 * and classes, and every one passes the sanitiser with nothing removed. Every piece of sample
 * text, every picture description and every link wording becomes a visible
 * [PLACEHOLDER: ...] marker, so the leftover check flags all of it and nothing reads like
 * real content. The safety notice keeps its support-line text (it must be verified before a
 * site launches, see HISTORY.md).
 *
 * No client names, wording, labels, numbers or picture paths belong here.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class TWD_SK_Starters {

	const META_KEY = '_twd_sk_starter';

	/**
	 * The starter pages: key => title and the components they are built from
	 * (component id and variant class, or '' for the default).
	 */
	public static function pages() {
		return array(
			'home'    => array(
				'title' => 'Home',
				'parts' => array(
					array( 'hero', 'twd-sk-hero--split' ),
					array( 'services', 'twd-sk-services--cards' ),
					array( 'image_text', '' ),
					array( 'quote', 'twd-sk-quote--cards' ),
					array( 'cta', 'twd-sk-cta--band' ),
					array( 'resources', '' ),
				),
			),
			'about'   => array(
				'title' => 'About',
				'parts' => array(
					array( 'hero', 'twd-sk-hero--compact' ),
					array( 'image_text', '' ),
					array( 'text', '' ),
					array( 'steps', '' ),
					array( 'quote', 'twd-sk-quote--pullout' ),
					array( 'cta', 'twd-sk-cta--strip' ),
				),
			),
			'contact' => array(
				'title' => 'Contact',
				'parts' => array(
					array( 'hero', 'twd-sk-hero--compact' ),
					array( 'contact', 'twd-sk-contact--split' ),
					array( 'faq', '' ),
					array( 'notice', '' ),
				),
			),
		);
	}

	private static function skeleton( $id, $variant ) {
		$c = TWD_SK_Registry::get( $id );
		if ( ! $c ) {
			return '';
		}
		if ( '' !== $variant && isset( $c['variants'][ $variant ] ) ) {
			return $c['variants'][ $variant ]['skeleton'];
		}
		return $c['skeleton'];
	}

	/**
	 * Turn sample wording into visible markers: each piece of text, and each non-empty picture
	 * description, becomes [PLACEHOLDER: the sample wording]. Shortcodes and anything that is
	 * already a marker are left alone.
	 */
	public static function placeholderise( $html ) {
		$html = preg_replace_callback(
			'/>([^<>]+)</',
			function ( $m ) {
				$text = $m[1];
				$core = trim( $text );
				if ( '' === $core || 1 !== preg_match( '/[A-Za-z]/', $core ) || false !== strpos( $core, '[' ) ) {
					return $m[0];
				}
				return '>' . self::marker( $core, $text ) . '<';
			},
			$html
		);
		return preg_replace_callback(
			'/ alt="([^"]+)"/',
			function ( $m ) {
				if ( 0 === strpos( trim( $m[1] ), '[PLACEHOLDER' ) ) {
					return $m[0];
				}
				return ' alt="' . self::marker( trim( $m[1] ), $m[1] ) . '"';
			},
			$html
		);
	}

	/** [PLACEHOLDER: core], keeping the original leading and trailing space. */
	private static function marker( $core, $original ) {
		$inner = trim( str_replace( array( '[', ']' ), '', $core ) );
		if ( strlen( $inner ) > 60 ) {
			$inner = rtrim( substr( $inner, 0, 57 ) ) . '...';
		}
		$lead  = substr( $original, 0, strlen( $original ) - strlen( ltrim( $original ) ) );
		$trail = substr( $original, strlen( rtrim( $original ) ) );
		return $lead . '[PLACEHOLDER: ' . $inner . ']' . $trail;
	}

	/** One starter's HTML. */
	public static function html( $key ) {
		$pages = self::pages();
		if ( ! isset( $pages[ $key ] ) ) {
			return '';
		}
		return self::build( $pages[ $key ]['parts'] );
	}

	/**
	 * A page outline from a list of parts (component id and variant class), with every piece of
	 * sample wording turned into a visible placeholder marker. The first hero's title is the h1.
	 */
	public static function build( $parts ) {
		$out   = array();
		$first = true;
		foreach ( $parts as $part ) {
			$skeleton = self::skeleton( $part[0], $part[1] );
			// The safety notice keeps its real support-line text.
			if ( 'notice' !== $part[0] ) {
				$skeleton = self::placeholderise( $skeleton );
			}
			// A page has one h1: the title of its first hero.
			if ( $first && 'hero' === $part[0] ) {
				$skeleton = preg_replace( '/<h2( class="[^"]*twd-sk-hero__title[^"]*")>(.*?)<\/h2>/s', '<h1$1>$2</h1>', $skeleton, 1 );
			}
			$first = false;
			$out[] = $skeleton;
		}
		return implode( "\n", $out ) . "\n";
	}

	/** file name => HTML for every starter, as written to starters/ by bin/build-starters.php. */
	public static function files() {
		$out = array();
		foreach ( array_keys( self::pages() ) as $key ) {
			$out[ 'page-' . $key . '.html' ] = self::html( $key );
		}
		return $out;
	}
}
