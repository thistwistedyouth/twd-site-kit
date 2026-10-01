<?php
/**
 * TWD_SK_Quality: a few checks on a page's HTML that matter for search and accessibility.
 *
 * Pure functions. They only report; they never change the page. The SEO tab lists them.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class TWD_SK_Quality {

	/** Link wording that says nothing about where the link goes. */
	public static function vague_link_texts() {
		return array( 'click here', 'here', 'read more', 'more', 'learn more', 'find out more', 'link', 'this', 'this link', 'click', 'go' );
	}

	/**
	 * @param string $html Cleaned page HTML.
	 * @return array[] each: id, label (plain English), count
	 */
	public static function check( $html ) {
		$out = array();
		if ( ! is_string( $html ) || '' === trim( $html ) ) {
			return $out;
		}

		// Pictures with no alt attribute at all (an empty one is fine: it marks a decorative picture).
		$no_alt = 0;
		if ( preg_match_all( '/<img\b[^>]*>/i', $html, $imgs ) ) {
			foreach ( $imgs[0] as $img ) {
				if ( ! preg_match( '/\salt\s*=/i', $img ) ) {
					$no_alt++;
				}
			}
		}
		if ( $no_alt ) {
			$out[] = array( 'id' => 'no_alt', 'count' => $no_alt, 'label' => $no_alt . ( 1 === $no_alt ? ' picture has' : ' pictures have' ) . ' no description. Describe what it shows, or leave the description empty only if it is purely decorative.' );
		}

		// Heading levels that jump (an h2 followed by an h4).
		$skips = array();
		if ( preg_match_all( '/<h([1-6])\b/i', $html, $hs ) ) {
			$prev = 0;
			foreach ( $hs[1] as $level ) {
				$level = (int) $level;
				if ( $prev && $level > $prev + 1 ) {
					$skips[] = 'h' . $prev . ' to h' . $level;
				}
				$prev = $level;
			}
		}
		if ( $skips ) {
			$out[] = array( 'id' => 'heading_skip', 'count' => count( $skips ), 'label' => 'Headings skip a level (' . implode( ', ', array_slice( $skips, 0, 3 ) ) . '). Keep them in order.' );
		}

		// Links whose words tell nobody where they go, and links with no words at all.
		$vague = 0;
		$empty = 0;
		if ( preg_match_all( '/<a\b[^>]*>(.*?)<\/a>/is', $html, $links ) ) {
			foreach ( $links[1] as $inner ) {
				$text = strtolower( trim( preg_replace( '/\s+/', ' ', html_entity_decode( strip_tags( $inner ), ENT_QUOTES, 'UTF-8' ) ) ) );
				$alt  = preg_match( '/<img\b[^>]*\salt="([^"]+)"/i', $inner ) ? 'x' : '';
				if ( '' === $text && '' === $alt ) {
					$empty++;
				} elseif ( in_array( rtrim( $text, '.!' ), self::vague_link_texts(), true ) ) {
					$vague++;
				}
			}
		}
		if ( $vague ) {
			$out[] = array( 'id' => 'vague_link', 'count' => $vague, 'label' => $vague . ( 1 === $vague ? ' link says' : ' links say' ) . ' things like "click here" or "read more". Say where the link goes.' );
		}
		if ( $empty ) {
			$out[] = array( 'id' => 'empty_link', 'count' => $empty, 'label' => $empty . ( 1 === $empty ? ' link has' : ' links have' ) . ' no words, so a screen reader cannot say what it is.' );
		}

		if ( 0 === preg_match( '/<h1\b/i', $html ) ) {
			$out[] = array( 'id' => 'no_h1', 'count' => 1, 'label' => 'The page has no main heading (h1). Use a hero at the top.' );
		}
		return $out;
	}
}
