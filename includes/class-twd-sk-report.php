<?php
/**
 * TWD_SK_Report: turns the sanitiser's cleaning report into plain English.
 *
 * Pure functions. Used by the front-end editor so a therapist can see what the
 * cleaner changed without reading a list of class names.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class TWD_SK_Report {

	/** What each leftover marker means, in words a therapist will recognise. */
	private static function meanings() {
		return array(
			'example.com'         => 'a sample email or web address',
			'example.org'         => 'a sample email or web address',
			'example.net'         => 'a sample email or web address',
			'PHONE_NUMBER'        => 'a sample phone number',
			'Short label here'    => 'sample label text',
			'Heading here'        => 'a sample heading',
			'Paragraph text here' => 'sample paragraph text',
			'Describe the image'  => 'a sample picture description',
			'Quote text here'     => 'a sample testimonial (never use an invented one)',
			'Another quote here'  => 'a sample testimonial (never use an invented one)',
			'A short statement in your own words' => 'a sample pull-out statement',
			'A short passage from this page, set large' => 'a sample pull line (use a passage from this page)',
			'Question here'       => 'a sample question',
			'Answer here'         => 'a sample answer',
			'Topic one'           => 'a sample topic',
			'Short description here' => 'a sample description',
			'Short introduction here' => 'a sample introduction',
			'Service name here'   => 'a sample service name',
			'Name and context'    => 'a sample name under a testimonial',
			'your-image'          => 'the sample picture address',
			'your-badge'          => 'the sample badge picture address',
			'your-logo'           => 'the sample logo picture address',
			'Describe the logo'   => 'a sample logo description',
			'Caption here'        => 'a sample picture caption',
			'Price here'          => 'a sample price (never use an invented one)',
			'Short note here'     => 'a sample note',
			'Day here'            => 'a sample day',
			'Times here'          => 'sample opening times',
			'Area name here'      => 'a sample area name',
			'your-topic'          => 'the sample article category in an articles strip',
			'[twd_embed id="0"]'  => 'an Embed section with no embed chosen yet',
			'embed missing'       => 'an Embed section whose embed has been deleted',
			'/service-N'          => 'a sample link to a service page (such as /service-1)',
			'PLACEHOLDER fragment' => 'half a placeholder marker left after an edit (the word PLACEHOLDER without its brackets)',
			'stray ]'             => 'a closing square bracket left over from a placeholder marker',
			'TO CONFIRM'          => 'a detail still to confirm with the client',
			'Contact me about a first session'   => 'the sample wording of a button or link',
			'Find out about my services'         => 'the sample wording of a button or link',
			'Read more about this approach'      => 'the sample wording of a button or link',
			'Call me to arrange a first session' => 'the sample wording of a button or link',
			'Ask about this'                     => 'the sample wording of a button or link',
			'See the area on a map'              => 'the sample wording of a button or link',
			'[PLACEHOLDER'        => 'a [PLACEHOLDER] marking a detail that is still missing',
		);
	}

	/**
	 * The cleaning report as a list of plain sentences.
	 *
	 * @param array $report The report from TWD_SK_Sanitizer::clean_with_report().
	 * @return string[]
	 */
	public static function describe( $report ) {
		$lines   = array();
		$removed = isset( $report['removed'] ) && is_array( $report['removed'] ) ? $report['removed'] : array();

		$labels = array(
			'classes'    => array( 'Removed %d style name(s) that are not part of the kit', true ),
			'tags'       => array( 'Removed or simplified %d element(s) the kit does not allow', true ),
			'attributes' => array( 'Removed %d extra setting(s) on elements (such as inline styles or scripts)', true ),
			'urls'       => array( 'Removed %d link or picture address(es) that were not safe or valid', true ),
			'shortcodes' => array( 'Removed %d shortcode(s) that are not allowed', true ),
			'dashes'     => array( 'Replaced %d long dash(es) with commas', false ),
		);

		foreach ( $labels as $kind => $info ) {
			$list = isset( $removed[ $kind ] ) && is_array( $removed[ $kind ] ) ? $removed[ $kind ] : array();
			if ( ! $list ) {
				continue;
			}
			if ( 'tags' === $kind ) {
				$demoted = array_values( array_filter( $list, function ( $t ) {
					return 0 === strpos( (string) $t, 'h1 (demoted' );
				} ) );
				if ( $demoted ) {
					$lines[] = 'Changed ' . count( $demoted ) . ' extra main heading(s) (h1) to second-level headings (h2). A page has one h1, in the first hero.';
					$list    = array_values( array_diff( $list, $demoted ) );
					if ( ! $list ) {
						continue;
					}
				}
			}
			$line = sprintf( $info[0], count( $list ) );
			if ( $info[1] ) {
				$shown = array_slice( array_map( 'strval', $list ), 0, 5 );
				$line .= ': ' . implode( ', ', $shown ) . ( count( $list ) > 5 ? ', and more' : '' );
			}
			$lines[] = $line . '.';
		}

		if ( ! $lines ) {
			$lines[] = 'Nothing was removed or changed.';
		}
		return $lines;
	}

	/**
	 * Leftover example text as a list of items for display.
	 *
	 * @param array $leftovers marker => count (from the cleaning report).
	 * @return array[] each: marker, count, meaning, level ('must' blocks publishing, 'check' only warns)
	 */
	public static function describe_leftovers( $leftovers ) {
		$out = array();
		if ( ! is_array( $leftovers ) ) {
			return $out;
		}
		$meanings = self::meanings();
		foreach ( $leftovers as $marker => $count ) {
			$out[] = array(
				'marker'  => (string) $marker,
				'count'   => (int) $count,
				'meaning' => isset( $meanings[ $marker ] ) ? $meanings[ $marker ] : 'example text',
				'level'   => TWD_SK_Sanitizer::leftover_level( (string) $marker ),
			);
		}
		return $out;
	}

	/** Totals by level: array( must, check ). */
	public static function leftover_levels( $leftovers ) {
		$totals = array( 'must' => 0, 'check' => 0 );
		if ( is_array( $leftovers ) ) {
			foreach ( $leftovers as $marker => $count ) {
				$totals[ TWD_SK_Sanitizer::leftover_level( (string) $marker ) ] += (int) $count;
			}
		}
		return $totals;
	}

	/** Total number of leftover items. */
	public static function leftover_total( $leftovers ) {
		return is_array( $leftovers ) ? (int) array_sum( array_map( 'intval', $leftovers ) ) : 0;
	}

	/**
	 * What changed between two pages, in plain words (0.15.0), worked out by comparing them section by
	 * section, so it is true by construction (the AI never writes it). One line per section that changed,
	 * naming added or removed headings, paragraphs reworded, pictures and buttons changed, and a layout or
	 * look change. Empty when the pages are the same.
	 *
	 * @return string[]
	 */
	public static function changes( $old, $new ) {
		$a = TWD_SK_Sections::split( str_replace( array( "\r\n", "\r" ), "\n", (string) $old ) );
		$b = TWD_SK_Sections::split( str_replace( array( "\r\n", "\r" ), "\n", (string) $new ) );
		$out = array();
		if ( $a['sections'] === $b['sections'] ) {
			return $out;
		}
		$old_s = $a['sections'];
		$new_s = $b['sections'];
		// Sections that moved in or out: those not found byte for byte on the other side, when the counts differ.
		if ( count( $old_s ) !== count( $new_s ) ) {
			$gone  = array_diff( $old_s, $new_s );
			$fresh = array_diff( $new_s, $old_s );
			foreach ( $fresh as $i => $sec ) {
				$out[] = 'Added section ' . ( $i + 1 ) . ' (' . self::name_of( $sec ) . ').';
			}
			foreach ( $gone as $i => $sec ) {
				$out[] = 'Removed section ' . ( $i + 1 ) . ' (' . self::name_of( $sec ) . ').';
			}
			if ( ! $gone && ! $fresh ) {
				$out[] = 'The sections were put in a different order.';
			}
			// Sections kept in both are compared by position among those kept.
			$old_s = array_values( array_intersect( $old_s, $new_s ) );
			$new_s = array_values( array_intersect( $new_s, $old_s ) );
		}
		foreach ( $new_s as $i => $sec ) {
			if ( ! isset( $old_s[ $i ] ) || $old_s[ $i ] === $sec ) {
				continue;
			}
			$bits = self::compare_section( $old_s[ $i ], $sec );
			if ( $bits ) {
				$out[] = 'Section ' . ( $i + 1 ) . ' (' . self::name_of( $sec ) . '): ' . implode( ', ', $bits ) . '.';
			}
		}
		return $out;
	}

	private static function name_of( $sec ) {
		$type = TWD_SK_Sections::type_of( $sec );
		$c    = TWD_SK_Registry::get( str_replace( '-', '_', $type ) );
		return $c ? $c['label'] : ( '' !== $type ? $type : 'section' );
	}

	/** The pieces of one section: headings, paragraphs, pictures, buttons and the classes on its element. */
	private static function pieces( $sec ) {
		$p = array( 'h' => array(), 'p' => array(), 'img' => array(), 'btn' => array(), 'classes' => array() );
		if ( preg_match_all( '/<h([2-6])\b[^>]*>(.*?)<\/h\1>/is', $sec, $m ) ) {
			$p['h'] = array_map( array( __CLASS__, 'words' ), $m[2] );
		}
		if ( preg_match_all( '/<(?:p|li)\b[^>]*>(.*?)<\/(?:p|li)>/is', $sec, $m ) ) {
			$p['p'] = array_map( array( __CLASS__, 'words' ), $m[1] );
		}
		if ( preg_match_all( '/<img\b[^>]*\bsrc="([^"]*)"/i', $sec, $m ) ) {
			$p['img'] = $m[1];
		}
		if ( preg_match_all( '/<a\b[^>]*\bclass="[^"]*\btwd-sk-btn\b[^"]*"[^>]*>(.*?)<\/a>/is', $sec, $m ) ) {
			$p['btn'] = array_map( array( __CLASS__, 'words' ), $m[1] );
		}
		if ( preg_match( '/^<section\b[^>]*\bclass="([^"]*)"/i', $sec, $m ) ) {
			$p['classes'] = preg_split( '/\s+/', trim( $m[1] ), -1, PREG_SPLIT_NO_EMPTY );
		}
		return $p;
	}

	private static function words( $html ) {
		return trim( preg_replace( '/\s+/u', ' ', html_entity_decode( strip_tags( (string) $html ), ENT_QUOTES, 'UTF-8' ) ) );
	}

	private static function plural( $n, $one, $many ) {
		return $n . ' ' . ( 1 === $n ? $one : $many );
	}

	private static function compare_section( $old, $new ) {
		$a    = self::pieces( $old );
		$b    = self::pieces( $new );
		$bits = array();
		$dh   = count( $b['h'] ) - count( $a['h'] );
		if ( $dh > 0 ) {
			$bits[] = 'added ' . self::plural( $dh, 'heading', 'headings' );
		} elseif ( $dh < 0 ) {
			$bits[] = 'removed ' . self::plural( -$dh, 'heading', 'headings' );
		}
		$reworded_h = count( array_diff( array_intersect_key( $b['h'], $a['h'] ), $a['h'] ) );
		if ( $reworded_h > 0 && 0 === $dh ) {
			$bits[] = 'reworded ' . self::plural( $reworded_h, 'heading', 'headings' );
		}
		$dp = count( $b['p'] ) - count( $a['p'] );
		if ( $dp > 0 ) {
			$bits[] = 'added ' . self::plural( $dp, 'paragraph', 'paragraphs' );
		} elseif ( $dp < 0 ) {
			$bits[] = 'removed ' . self::plural( -$dp, 'paragraph', 'paragraphs' );
		}
		$reworded = count( array_diff( $b['p'], $a['p'] ) ) - max( 0, $dp );
		if ( $reworded > 0 ) {
			$bits[] = 'reworded ' . self::plural( $reworded, 'paragraph', 'paragraphs' );
		}
		if ( $a['img'] !== $b['img'] ) {
			$bits[] = count( $b['img'] ) > count( $a['img'] ) ? 'added a picture' : ( count( $b['img'] ) < count( $a['img'] ) ? 'removed a picture' : 'changed a picture' );
		}
		if ( $a['btn'] !== $b['btn'] ) {
			$bits[] = count( $b['btn'] ) === count( $a['btn'] ) ? 'changed a button' : 'changed the buttons';
		}
		$added   = array_diff( $b['classes'], $a['classes'] );
		$dropped = array_diff( $a['classes'], $b['classes'] );
		if ( $added || $dropped ) {
			$names = array();
			foreach ( $added as $cls ) {
				$names[] = self::class_name( $cls );
			}
			$bits[] = $names ? 'changed the look to ' . implode( ' and ', $names ) : 'changed the look';
		}
		if ( ! $bits ) {
			$bits[] = 'changed the markup (the words read the same)';
		}
		return $bits;
	}

	/** A plain name for a layout or option class: the registry's label, or the class as it is. */
	private static function class_name( $cls ) {
		foreach ( TWD_SK_Registry::all() as $c ) {
			if ( isset( $c['variants'][ $cls ] ) ) {
				return '"' . trim( strtok( $c['variants'][ $cls ]['label'], ':' ) ) . '"';
			}
		}
		foreach ( TWD_SK_Looks::tones() as $tone => $label ) {
			if ( $tone === $cls ) {
				return '"' . $label . '" background';
			}
		}
		return $cls;
	}
}
