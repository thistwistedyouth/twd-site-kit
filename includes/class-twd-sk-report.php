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
			'Question here'       => 'a sample question',
			'Answer here'         => 'a sample answer',
			'Topic one'           => 'a sample topic',
			'Short description here' => 'a sample description',
			'Short introduction here' => 'a sample introduction',
			'Service name here'   => 'a sample service name',
			'Name and context'    => 'a sample name under a testimonial',
			'your-image'          => 'the sample picture address',
			'your-badge'          => 'the sample badge picture address',
			'/service-N'          => 'a sample link to a service page (such as /service-1)',
			'Contact me about a first session'   => 'the sample wording of a button or link',
			'Find out about my services'         => 'the sample wording of a button or link',
			'Read more about this approach'      => 'the sample wording of a button or link',
			'Call me to arrange a first session' => 'the sample wording of a button or link',
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
}
