<?php
/**
 * TWD_SK_HouseRules: a check of every page's words and addresses against the site's own rules, and a fix for the
 * mechanical ones.
 *
 * Rules come from two places. Built in, for every site: long dashes (the site never uses them) and absolute
 * addresses to a staging site (they would carry over to the live site). And written by the person, in the saved
 * facts, under the heading WORDING CHECKS, one per line:
 *
 *     never: on a journey to healing
 *     never: Dr Smith -> Dr. Smith
 *
 * A line with an arrow can be fixed in one go (every match in a page's words becomes the part after the arrow);
 * a line without one is only reported. Matches are found in a page's words only, never inside a tag or an attribute,
 * and as whole words. Nothing here writes unless fix() is called, and fix() saves each page through the store as one
 * version with a note, so the sanitiser runs and Undo works page by page.
 *
 * Administrators only (the REST routes decide).
 *
 * @package TWD_Site_Kit
 */

defined( 'ABSPATH' ) || exit;

class TWD_SK_HouseRules {

	const HEADING    = 'WORDING CHECKS';
	const MAX_RULES  = 40;
	const MAX_PHRASE = 80;

	/**
	 * The rules written in the facts under WORDING CHECKS.
	 *
	 * @return array[] each { phrase, replacement ('' when there is no fix) }
	 */
	public static function rules() {
		$out = array();
		$in  = false;
		foreach ( preg_split( '/\R/u', (string) TWD_SK_Facts::get() ) as $line ) {
			$t = trim( $line );
			if ( '' === $t ) {
				continue;
			}
			if ( self::is_heading( $t ) ) {
				$in = ( strtoupper( $t ) === self::HEADING );
				continue;
			}
			if ( ! $in || 1 !== preg_match( '/^never:\s*(.+)$/iu', $t, $m ) ) {
				continue;
			}
			$phrase = trim( $m[1] );
			$fix    = '';
			if ( false !== strpos( $phrase, ' -> ' ) ) {
				$bits   = explode( ' -> ', $phrase, 2 );
				$phrase = trim( $bits[0] );
				$fix    = trim( $bits[1] );
			}
			if ( '' === $phrase || ( function_exists( 'mb_strlen' ) ? mb_strlen( $phrase ) : strlen( $phrase ) ) > self::MAX_PHRASE ) {
				continue;
			}
			$out[] = array( 'phrase' => $phrase, 'replacement' => $fix );
			if ( count( $out ) >= self::MAX_RULES ) {
				break;
			}
		}
		return $out;
	}

	/** A heading line in the facts: capitals only, with letters, and no colon. */
	private static function is_heading( $t ) {
		return strtoupper( $t ) === $t && 1 === preg_match( '/[A-Z]/', $t ) && false === strpos( $t, ':' ) && strlen( $t ) <= 90;
	}

	/** Run a function over the words of some HTML (the text between tags), never a tag or attribute. */
	private static function map_text( $html, $fn ) {
		return preg_replace_callback( '/>([^<]+)</', function ( $m ) use ( $fn ) {
			return '>' . $fn( $m[1] ) . '<';
		}, (string) $html );
	}

	/** The pattern for a phrase as whole words, whatever the capitals, or as a plain match when it starts or ends with a mark. */
	private static function pattern( $phrase ) {
		$q = preg_quote( $phrase, '/' );
		$a = 1 === preg_match( '/^[\p{L}\p{N}_]/u', $phrase ) ? '(?<![\p{L}\p{N}_])' : '';
		$b = 1 === preg_match( '/[\p{L}\p{N}_]$/u', $phrase ) ? '(?![\p{L}\p{N}_])' : '';
		return '/' . $a . $q . $b . '/iu';
	}

	/** Is this address one on a staging site (any host with the word staging, or one in the staging list)? */
	public static function is_staging( $host ) {
		$host = strtolower( (string) $host );
		if ( '' === $host ) {
			return false;
		}
		if ( false !== strpos( $host, 'staging' ) ) {
			return true;
		}
		if ( class_exists( 'TWD_SK_Replace' ) ) {
			foreach ( TWD_SK_Replace::staging_hosts() as $h ) {
				$h = strtolower( trim( $h ) );
				if ( '' !== $h && ( $host === $h || substr( $host, -strlen( '.' . $h ) ) === '.' . $h ) ) {
					return true;
				}
			}
		}
		return false;
	}

	/** The staging addresses in the src and href attributes of some HTML: full address => its path. */
	private static function staging_attrs( $html ) {
		$out = array();
		if ( preg_match_all( '/\s(?:src|href)\s*=\s*"(https?:\/\/([^\/"?#]+)([^"]*))"/i', (string) $html, $m, PREG_SET_ORDER ) ) {
			foreach ( $m as $hit ) {
				if ( self::is_staging( $hit[2] ) ) {
					$path = $hit[3];
					if ( '' === $path || '?' === $path[0] || '#' === $path[0] ) {
						$path = '/' . $path;
					}
					$out[ $hit[1] ] = $path;
				}
			}
		}
		return $out;
	}

	/**
	 * What is wrong in one page's HTML.
	 *
	 * @return array[] key => { key, label, count, sample, fix (bool) }
	 */
	public static function scan_page( $html, $rules = null ) {
		$rules = null === $rules ? self::rules() : $rules;
		$found = array();
		$dash  = 0;
		self::map_text( $html, function ( $t ) use ( &$dash ) {
			$dash += preg_match_all( '/[\x{2013}\x{2014}]/u', $t );
			return $t;
		} );
		if ( $dash ) {
			$found['long_dash'] = array( 'key' => 'long_dash', 'label' => 'Long dashes (the site uses commas, full stops or a spaced hyphen)', 'count' => $dash, 'sample' => '', 'fix' => true );
		}
		$addr = self::staging_attrs( $html );
		if ( $addr ) {
			$first = array_keys( $addr )[0];
			$found['address'] = array( 'key' => 'address', 'label' => 'Addresses that name a staging site (they would carry over to the live site)', 'count' => count( $addr ), 'sample' => strlen( $first ) > 70 ? substr( $first, 0, 67 ) . '...' : $first, 'fix' => true );
		}
		foreach ( $rules as $r ) {
			$n      = 0;
			$sample = '';
			$re     = self::pattern( $r['phrase'] );
			self::map_text( $html, function ( $t ) use ( $re, &$n, &$sample ) {
				if ( preg_match_all( $re, $t, $mm, PREG_OFFSET_CAPTURE ) ) {
					$n += count( $mm[0] );
					if ( '' === $sample ) {
						$at     = (int) $mm[0][0][1];
						$sample = trim( preg_replace( '/\s+/u', ' ', ( function_exists( 'mb_substr' ) ? mb_substr( $t, max( 0, $at - 20 ), 70 ) : substr( $t, max( 0, $at - 20 ), 70 ) ) ) );
					}
				}
				return $t;
			} );
			if ( $n ) {
				$key = 'never:' . $r['phrase'];
				$found[ $key ] = array( 'key' => $key, 'label' => 'Never: "' . $r['phrase'] . '"' . ( '' !== $r['replacement'] ? ' (fix: "' . $r['replacement'] . '")' : '' ), 'count' => $n, 'sample' => $sample, 'fix' => '' !== $r['replacement'] );
			}
		}
		return $found;
	}

	/** Every kit page, with what is wrong on each, and the totals per rule. */
	public static function scan() {
		$rules  = self::rules();
		$pages  = array();
		$totals = array();
		foreach ( TWD_SK_Launch::kit_pages() as $id ) {
			$f = self::scan_page( TWD_SK_Store::get_current( (int) $id ), $rules );
			if ( ! $f ) {
				continue;
			}
			$pages[] = array( 'id' => (int) $id, 'title' => trim( (string) get_the_title( (int) $id ) ), 'findings' => array_values( $f ) );
			foreach ( $f as $key => $item ) {
				if ( ! isset( $totals[ $key ] ) ) {
					$totals[ $key ] = array( 'key' => $key, 'label' => $item['label'], 'count' => 0, 'pages' => 0, 'fix' => $item['fix'] );
				}
				$totals[ $key ]['count'] += $item['count'];
				$totals[ $key ]['pages']++;
			}
		}
		return array( 'rules' => $rules, 'pages' => $pages, 'totals' => array_values( $totals ) );
	}

	/** Apply the fixes for the chosen rule keys to some HTML. */
	public static function fix_html( $html, $keys, $rules = null ) {
		$rules = null === $rules ? self::rules() : $rules;
		$keys  = (array) $keys;
		if ( in_array( 'address', $keys, true ) ) {
			foreach ( self::staging_attrs( $html ) as $full => $path ) {
				$html = str_replace( '"' . $full . '"', '"' . $path . '"', $html );
			}
		}
		if ( in_array( 'long_dash', $keys, true ) ) {
			$html = self::map_text( $html, function ( $t ) {
				return preg_replace( '/\s*[\x{2013}\x{2014}]\s*/u', ', ', $t );
			} );
		}
		foreach ( $rules as $r ) {
			if ( '' === $r['replacement'] || ! in_array( 'never:' . $r['phrase'], $keys, true ) ) {
				continue;
			}
			$re   = self::pattern( $r['phrase'] );
			$with = $r['replacement'];
			$html = self::map_text( $html, function ( $t ) use ( $re, $with ) {
				return preg_replace_callback( $re, function () use ( $with ) {
					return $with;
				}, $t );
			} );
		}
		return $html;
	}

	/**
	 * Fix the chosen rules on the chosen pages (all pages with something to fix when none is named). Each page is one
	 * version with a note; a page the person may not edit, or that moved on, is reported and left alone.
	 *
	 * @return array { results[] { id, title, ok, message }, done, failed }
	 */
	public static function fix( $keys, $page_ids = null ) {
		$keys    = array_values( array_filter( array_map( 'strval', (array) $keys ) ) );
		$rules   = self::rules();
		$results = array();
		$done    = 0;
		$failed  = 0;
		$ids     = is_array( $page_ids ) && $page_ids ? array_map( 'intval', $page_ids ) : TWD_SK_Launch::kit_pages();
		foreach ( $ids as $id ) {
			$was = TWD_SK_Store::get_current( $id );
			$new = self::fix_html( $was, $keys, $rules );
			if ( $new === $was ) {
				continue;
			}
			$row = array( 'id' => $id, 'title' => trim( (string) get_the_title( $id ) ), 'ok' => false, 'message' => '' );
			if ( ! current_user_can( 'edit_post', $id ) ) {
				$row['message'] = 'You cannot edit this page.';
			} else {
				$r = TWD_SK_Store::save( $id, $new, array( 'base_version' => (int) TWD_SK_Store::get_current_version_id( $id ), 'note' => 'Wording and address fixes (' . count( $keys ) . ' rule' . ( 1 === count( $keys ) ? '' : 's' ) . ')' ) );
				if ( is_wp_error( $r ) ) {
					$row['message'] = $r->get_error_message();
				} else {
					$row['ok']      = true;
					$row['message'] = 'Saved as a new version. Undo on that page takes it back.';
				}
			}
			$row['ok'] ? $done++ : $failed++;
			$results[] = $row;
		}
		return array( 'results' => $results, 'done' => $done, 'failed' => $failed );
	}
}
