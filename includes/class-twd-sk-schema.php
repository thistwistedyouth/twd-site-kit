<?php
/**
 * TWD_SK_Schema: structured data (JSON-LD) for the front page, built from the site profile.
 *
 * It describes the practice (ProfessionalService) and the therapist (Person). Only real values
 * are used: anything empty, anything still holding a [PLACEHOLDER marker, an email that is not an
 * email, a link that is not a web address, is left out. Registration lines are never printed
 * here. Nothing is guessed.
 *
 * To avoid printing the same thing twice:
 * - With Yoast SEO active, the two items are added to Yoast's own graph through its filter and
 *   this plugin prints nothing itself.
 * - With another SEO plugin active, nothing is added.
 * - With no SEO plugin, one script block is printed on the front page, once.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class TWD_SK_Schema {

	private static $printed = false;

	public static function init() {
		add_action( 'wp_head', array( __CLASS__, 'print_head' ), 5 );
		add_filter( 'wpseo_schema_graph', array( __CLASS__, 'filter_yoast_graph' ), 20 );
	}

	private static function real( $value ) {
		return is_string( $value ) && '' !== trim( $value ) && false === stripos( $value, '[PLACEHOLDER' );
	}

	private static function web_url( $url ) {
		return is_string( $url ) && 1 === preg_match( '#^https?://[^\s<>"\']+$#i', $url ) && false === stripos( $url, '[PLACEHOLDER' );
	}

	/**
	 * The graph nodes for a profile. No @context. Returns an empty array when there is nothing real to say.
	 */
	public static function nodes( $profile = null ) {
		$p    = null === $profile ? TWD_SK_Profile::get() : $profile;
		$home = function_exists( 'home_url' ) ? (string) home_url( '/' ) : '';
		$org  = array( '@type' => 'ProfessionalService', '@id' => $home . '#practice' );

		if ( self::real( $p['site_name'] ) ) {
			$org['name'] = $p['site_name'];
		}
		if ( '' !== $home ) {
			$org['url'] = $home;
		}
		if ( self::real( $p['phone'] ) && 1 === preg_match( '/\d{5,}/', preg_replace( '/[^0-9]/', '', $p['phone'] ) ) ) {
			$org['telephone'] = $p['phone'];
		}
		if ( self::real( $p['email'] ) && false !== strpos( $p['email'], '@' ) ) {
			$org['email'] = $p['email'];
		}
		$lines = array();
		foreach ( (array) $p['address'] as $line ) {
			if ( self::real( $line ) ) {
				$lines[] = $line;
			}
		}
		if ( $lines ) {
			$org['address'] = array( '@type' => 'PostalAddress', 'streetAddress' => implode( ', ', $lines ) );
		}
		if ( self::real( $p['area_served'] ) ) {
			$org['areaServed'] = $p['area_served'];
		}
		if ( $p['logo_id'] && function_exists( 'wp_get_attachment_image_url' ) ) {
			$logo = wp_get_attachment_image_url( (int) $p['logo_id'], 'full' );
			if ( is_string( $logo ) && self::web_url( $logo ) ) {
				$org['logo'] = $logo;
			}
		}
		$same = array();
		foreach ( (array) $p['same_as'] as $link ) {
			if ( self::web_url( $link ) ) {
				$same[] = $link;
			}
		}
		if ( $same ) {
			$org['sameAs'] = $same;
		}

		$nodes = array();
		// A practice node needs a name to be worth printing.
		if ( isset( $org['name'] ) ) {
			$nodes[] = $org;
		}
		if ( self::real( $p['person_name'] ) ) {
			$person = array( '@type' => 'Person', '@id' => $home . '#therapist', 'name' => $p['person_name'] );
			if ( self::real( $p['person_job'] ) ) {
				$person['jobTitle'] = $p['person_job'];
			}
			if ( isset( $org['name'] ) ) {
				$person['worksFor'] = array( '@id' => $org['@id'] );
			}
			if ( $same ) {
				$person['sameAs'] = $same;
			}
			$nodes[] = $person;
		}
		return $nodes;
	}

	/** A script-safe JSON string: angle brackets, ampersands and quotes cannot end the block. */
	public static function json( $data ) {
		$flags = JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE;
		return function_exists( 'wp_json_encode' ) ? wp_json_encode( $data, $flags ) : json_encode( $data, $flags );
	}

	private static function on_front_page() {
		return function_exists( 'is_front_page' ) && is_front_page();
	}

	public static function print_head() {
		if ( self::$printed || TWD_SK_Safe::on() || ! self::on_front_page() ) {
			return;
		}
		if ( 'none' !== TWD_SK_Seo::detect()['mode'] ) {
			return;
		}
		$nodes = self::nodes();
		if ( ! $nodes ) {
			return;
		}
		self::$printed = true;
		echo '<script type="application/ld+json">' . self::json( array( '@context' => 'https://schema.org', '@graph' => $nodes ) ) . "</script>\n";
	}

	/** Adds the practice and the therapist to Yoast's graph on the front page, once. */
	public static function filter_yoast_graph( $graph ) {
		if ( TWD_SK_Safe::on() || ! self::on_front_page() || ! is_array( $graph ) ) {
			return $graph;
		}
		foreach ( $graph as $piece ) {
			if ( is_array( $piece ) && isset( $piece['@id'] ) && false !== strpos( (string) $piece['@id'], '#practice' ) ) {
				return $graph;
			}
		}
		foreach ( self::nodes() as $node ) {
			$graph[] = $node;
		}
		return $graph;
	}

	/** For tests. */
	public static function reset() {
		self::$printed = false;
	}
}
