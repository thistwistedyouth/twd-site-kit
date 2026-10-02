<?php
/**
 * TWD_SK_Sections: a kit page as a list of top-level sections.
 *
 * Every kit page is a stack of top-level section elements (hero, services, quote and so on),
 * with only whitespace between them. This cuts the stored HTML into those sections and the
 * gaps around them by plain string offsets, so putting the parts back together gives the exact
 * same bytes. Nothing here changes content. It only describes, splits and joins.
 *
 * Some sections hold words that are not the therapist's to rewrite: a client's testimonial or the
 * safety notice with its helpline numbers. Those types are "locked": a remix may change their
 * layout but never their words, unless the person says they are supplying new wording.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class TWD_SK_Sections {

	const MAX_SECTIONS = 60;

	/** Section types whose wording a remix may not change unless asked. */
	public static function locked_types() {
		$types = apply_filters( 'twd_sk_locked_section_types', array( 'quote', 'notice' ) );
		return is_array( $types ) ? $types : array( 'quote', 'notice' );
	}

	/**
	 * Cut HTML into top-level sections.
	 *
	 * @return array { gaps: string[], sections: string[] } with one more gap than sections.
	 */
	public static function split( $html ) {
		$html = is_string( $html ) ? $html : '';
		$gaps = array();
		$secs = array();
		$last = 0;
		$depth = 0;
		$start = 0;
		if ( preg_match_all( '#<(/?)section\b[^>]*>#i', $html, $m, PREG_OFFSET_CAPTURE ) ) {
			foreach ( $m[0] as $i => $tag ) {
				$closing = '/' === $m[1][ $i ][0];
				$offset  = $tag[1];
				if ( ! $closing ) {
					if ( 0 === $depth ) {
						$start = $offset;
					}
					$depth++;
				} elseif ( $depth > 0 ) {
					$depth--;
					if ( 0 === $depth ) {
						$end    = $offset + strlen( $tag[0] );
						$gaps[] = substr( $html, $last, $start - $last );
						$secs[] = substr( $html, $start, $end - $start );
						$last   = $end;
					}
				}
			}
		}
		$gaps[] = substr( $html, $last );
		return array( 'gaps' => $gaps, 'sections' => $secs );
	}

	/** Put the parts back together. */
	public static function join( $gaps, $sections ) {
		$out = '';
		foreach ( $sections as $i => $section ) {
			$out .= ( isset( $gaps[ $i ] ) ? $gaps[ $i ] : '' ) . $section;
		}
		return $out . ( isset( $gaps[ count( $sections ) ] ) ? $gaps[ count( $sections ) ] : '' );
	}

	/** The component type from the section's first kit class, e.g. "hero" or "image-text". */
	public static function type_of( $section ) {
		if ( preg_match( '/^<section\b[^>]*\sclass\s*=\s*"([^"]*)"/i', $section, $m ) ) {
			foreach ( preg_split( '/\s+/', trim( $m[1] ) ) as $class ) {
				if ( 0 === strpos( $class, 'twd-sk-' ) && false === strpos( $class, '--' ) && 0 !== strpos( $class, 'twd-sk-tone-' ) ) {
					return substr( $class, 7 );
				}
			}
		}
		return 'section';
	}

	/** Visible words of a section, tidied for comparing. */
	public static function text_of( $section ) {
		$text = preg_replace( '#<(script|style)\b.*?</\1>#is', ' ', (string) $section );
		$text = preg_replace( '/<[^>]*>/', ' ', $text );
		$text = html_entity_decode( $text, ENT_QUOTES, 'UTF-8' );
		return trim( preg_replace( '/\s+/u', ' ', $text ) );
	}

	/** A short human label: the first heading, else the first paragraph, else the type. */
	public static function label_of( $section ) {
		$label = '';
		if ( preg_match( '#<h[1-6]\b[^>]*>(.*?)</h[1-6]>#is', $section, $m ) ) {
			$label = self::text_of( $m[1] );
		} elseif ( preg_match( '#<p\b[^>]*>(.*?)</p>#is', $section, $m ) ) {
			$label = self::text_of( $m[1] );
		}
		if ( '' === $label ) {
			return self::type_of( $section );
		}
		$has_mb = function_exists( 'mb_strlen' ) && function_exists( 'mb_substr' );
		$len    = $has_mb ? mb_strlen( $label, 'UTF-8' ) : strlen( $label );
		if ( $len > 60 ) {
			return ( $has_mb ? mb_substr( $label, 0, 57, 'UTF-8' ) : substr( $label, 0, 57 ) ) . '...';
		}
		return $label;
	}

	/**
	 * The list the pop-up shows.
	 *
	 * @return array[] each: index, type, label, locked, bytes
	 */
	public static function describe( $html ) {
		$parts  = self::split( $html );
		$locked = self::locked_types();
		$out    = array();
		foreach ( $parts['sections'] as $i => $section ) {
			$type  = self::type_of( $section );
			$out[] = array(
				'index'  => $i,
				'type'   => $type,
				'label'  => self::label_of( $section ),
				'locked' => in_array( $type, $locked, true ),
				'bytes'  => strlen( $section ),
			);
		}
		return $out;
	}
}
