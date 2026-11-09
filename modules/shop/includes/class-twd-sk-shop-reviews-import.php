<?php
/**
 * TWD_SK_Shop_Reviews_Import (0.21.0): reads reviews from a CSV file (the blank template, or a
 * file with similar headings), checks every row with TWD_SK_Shop_Reviews::check(), never adds a
 * review that is already saved, and reports on every row. Check only saves nothing.
 *
 * Etsy's own reviews file: its exact column names are not known here, so it is not claimed. The
 * other headings below may already match it.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class TWD_SK_Shop_Reviews_Import {

	const MAX_BYTES = 2 * 1024 * 1024;
	const MAX_ROWS  = 3000;

	/** The template's columns, in order. */
	public static function columns() {
		return array( 'Platform', 'Name', 'Date', 'Stars', 'Review', 'Item', 'Link' );
	}

	/** The blank template: a header row only (with a byte-order mark so Excel keeps accents). */
	public static function template() {
		return "\xEF\xBB\xBF" . implode( ',', self::columns() ) . "\r\n";
	}

	/** Headings read for each field (lower case, spaces and punctuation evened out). */
	public static function aliases() {
		return array(
			'platform' => array( 'platform', 'site', 'source', 'marketplace' ),
			'name'     => array( 'name', 'reviewer', 'reviewer name', 'buyer', 'buyer name', 'customer', 'customer name', 'author' ),
			'date'     => array( 'date', 'date reviewed', 'review date', 'reviewed', 'date posted', 'created' ),
			'stars'    => array( 'stars', 'star rating', 'rating', 'score' ),
			'text'     => array( 'review', 'review text', 'message', 'comment', 'comments', 'feedback', 'text' ),
			'item'     => array( 'item', 'item name', 'listing', 'listing title', 'product' ),
			'link'     => array( 'link', 'url', 'review link', 'review url' ),
			'fav'      => array( 'favourite', 'favorite' ),
			'hidden'   => array( 'hidden', 'hide' ),
		);
	}

	private static function heading( $h ) {
		$h = strtolower( trim( (string) $h ) );
		$h = preg_replace( '/[^a-z0-9]+/', ' ', $h );
		return trim( $h );
	}

	/**
	 * Read the file into rows of fields. Returns array( 'rows' => list of array( row, fields ) )
	 * or array( 'error' => plain sentence ). Row numbers match the spreadsheet (the header is row 1).
	 */
	public static function parse( $csv ) {
		$csv = (string) $csv;
		if ( strlen( $csv ) > self::MAX_BYTES ) {
			return array( 'error' => 'The file is larger than 2 MB. Split it into smaller files.' );
		}
		$csv = TWD_SK_Shop_Reviews::utf8( $csv );
		if ( 0 === strpos( $csv, "\xEF\xBB\xBF" ) ) {
			$csv = substr( $csv, 3 );
		}
		if ( '' === trim( $csv ) ) {
			return array( 'error' => 'The file is empty.' );
		}
		$first = strtok( $csv, "\n" );
		$sep   = ',';
		foreach ( array( ';', "\t" ) as $other ) {
			if ( substr_count( $first, $other ) > substr_count( $first, ',' ) ) {
				$sep = $other;
			}
		}
		$fh = fopen( 'php://temp', 'r+' );
		fwrite( $fh, $csv );
		rewind( $fh );
		$escape = version_compare( PHP_VERSION, '7.4', '>=' ) ? '' : '\\';
		$head   = fgetcsv( $fh, 0, $sep, '"', $escape );
		$map    = array();
		$alias  = self::aliases();
		foreach ( (array) $head as $i => $h ) {
			$h = self::heading( $h );
			foreach ( $alias as $field => $words ) {
				if ( ! isset( $map[ $field ] ) && in_array( $h, $words, true ) ) {
					$map[ $field ] = $i;
				}
			}
		}
		$missing = array();
		foreach ( array( 'name' => 'Name', 'date' => 'Date', 'stars' => 'Stars', 'text' => 'Review' ) as $field => $label ) {
			if ( ! isset( $map[ $field ] ) ) {
				$missing[] = $label;
			}
		}
		if ( $missing ) {
			fclose( $fh );
			$found = array_values( array_filter( array_map( 'trim', array_map( 'strval', (array) $head ) ) ) );
			return array( 'error' => 'These columns were not found: ' . implode( ', ', $missing ) . '. The first row has: ' . ( $found ? implode( ', ', array_slice( $found, 0, 12 ) ) : 'nothing' ) . '. Use the template\'s headings: ' . implode( ', ', self::columns() ) . '.' );
		}
		$rows = array();
		$n    = 1;
		while ( false !== ( $cells = fgetcsv( $fh, 0, $sep, '"', $escape ) ) ) {
			$n++;
			if ( null === $cells || array( null ) === $cells || '' === trim( implode( '', array_map( 'strval', $cells ) ) ) ) {
				continue;
			}
			if ( count( $rows ) >= self::MAX_ROWS ) {
				fclose( $fh );
				return array( 'error' => 'The file has more than ' . self::MAX_ROWS . ' reviews. Split it into smaller files.' );
			}
			$fields = array();
			foreach ( $map as $field => $i ) {
				$fields[ $field ] = isset( $cells[ $i ] ) ? (string) $cells[ $i ] : '';
			}
			$rows[] = array( $n, $fields );
		}
		fclose( $fh );
		if ( ! $rows ) {
			return array( 'error' => 'The file has headings but no reviews.' );
		}
		return array( 'rows' => $rows );
	}

	/**
	 * Check (and unless $dry, save) every row. $platform is used for rows with no platform of
	 * their own ('' leaves them as Other). Returns array( 'error' ) or a report:
	 * array( 'counts' => array( added, updated, there, refused ), 'rows' => list of array( row, status, name, words ) ).
	 * Status: added (or ready, when only checking), updated (an undated review given its date;
	 * ready-date when only checking), there, refused.
	 */
	public static function run( $csv, $dry = true, $platform = '' ) {
		$parsed = self::parse( $csv );
		if ( isset( $parsed['error'] ) ) {
			return $parsed;
		}
		$known  = TWD_SK_Shop_Reviews::keys();
		$loose  = TWD_SK_Shop_Reviews::loose_index();
		$counts = array( 'added' => 0, 'updated' => 0, 'there' => 0, 'refused' => 0 );
		$report = array();
		foreach ( $parsed['rows'] as $row ) {
			list( $n, $fields ) = $row;
			if ( '' !== $platform && ( ! isset( $fields['platform'] ) || '' === trim( $fields['platform'] ) ) ) {
				$fields['platform'] = $platform;
			}
			$checked = TWD_SK_Shop_Reviews::check( $fields );
			$name    = isset( $fields['name'] ) ? trim( TWD_SK_Shop_Reviews::strip_html( $fields['name'] ) ) : '';
			if ( isset( $checked['error'] ) ) {
				$counts['refused']++;
				$report[] = array( $n, 'refused', $name, $checked['error'] );
				continue;
			}
			$r  = $checked['review'];
			$lk = TWD_SK_Shop_Reviews::loose_key( $r['platform'], $r['name'], $r['text'] );
			if ( isset( $known[ $r['key'] ] ) || ( $r['undated'] && isset( $loose[ $lk ] ) ) ) {
				$counts['there']++;
				$report[] = array( $n, 'there', $name, 'Already there, so skipped.' );
				continue;
			}
			if ( ! $r['undated'] && ! empty( $loose[ $lk ]['undated'] ) ) {
				// The same review was kept undated: a later file found its date, so fill it in.
				$id = (int) $loose[ $lk ]['undated'];
				$counts['updated']++;
				if ( ! $dry ) {
					TWD_SK_Shop_Reviews::fill_date( $id, $r['month'] );
				}
				$loose[ $lk ]          = array( 'undated' => 0, 'dated' => true );
				$known[ $r['key'] ]    = $id;
				$report[] = array( $n, $dry ? 'ready-date' : 'updated', $name, ( $dry ? 'Ready to fill in the date on the undated review already kept: ' : 'Date filled in on the undated review already kept: ' ) . TWD_SK_Shop_Reviews::month_label( $r['month'] ) . '.' );
				continue;
			}
			$loose[ $lk ] = $r['undated'] ? array( 'undated' => -1, 'dated' => false ) : array( 'undated' => 0, 'dated' => true );
			$words = implode( ' ', $checked['notes'] );
			if ( $dry ) {
				$known[ $r['key'] ] = 0;
				$counts['added']++;
				$report[] = array( $n, 'ready', $name, trim( 'Ready to add. ' . $words ) );
				continue;
			}
			$id = TWD_SK_Shop_Reviews::save( $r );
			if ( $id <= 0 ) {
				$counts['refused']++;
				$report[] = array( $n, 'refused', $name, 'WordPress could not save it.' );
				continue;
			}
			$known[ $r['key'] ] = $id;
			if ( $r['undated'] ) {
				$loose[ $lk ]['undated'] = $id;
			}
			$counts['added']++;
			$report[] = array( $n, 'added', $name, trim( 'Added. ' . $words ) );
		}
		return array( 'counts' => $counts, 'rows' => $report, 'dry' => (bool) $dry );
	}
}
