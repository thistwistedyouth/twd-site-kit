<?php
/**
 * TWD_SK_Shop_Reviews (0.21.0): the shop's reviews from other places (Etsy, eBay), kept on the
 * site so no review plugin or outside request is needed. Design: docs/shop-module-design.md, 12.
 *
 * A non-public post type, twd_sk_review, every field in post meta so WordPress's content filters
 * never touch the words. This class holds the fields, the checks shared by the import and the edit
 * screen, and reading the reviews back. The import, the wp-admin screens and the display are in
 * their own classes.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class TWD_SK_Shop_Reviews {

	const TYPE     = 'twd_sk_review';
	const META     = '_twd_sk_rv_';
	const MAX_TEXT = 5000;
	const MAX_NAME = 80;
	const MAX_ITEM = 200;
	const MAX_LINK = 500;

	// Settings (WooCommerce > Settings > Products > Site Kit shop tools).
	const OPT_PAGE    = 'twd_sk_shop_reviews_page';
	const OPT_NAMES   = 'twd_sk_shop_reviews_names';
	const OPT_PRODUCT = 'twd_sk_shop_reviews_product';
	const OPT_WORDS   = 'twd_sk_shop_reviews_words';

	/** The fields kept for each review, in meta as _twd_sk_rv_<field>. */
	public static function fields() {
		return array( 'platform', 'name', 'month', 'undated', 'stars', 'rating', 'text', 'item', 'link', 'fav', 'hidden', 'key' );
	}

	public static function init() {
		add_action( 'init', array( __CLASS__, 'register' ) );
	}

	public static function register() {
		if ( ! function_exists( 'register_post_type' ) ) {
			return;
		}
		register_post_type( self::TYPE, array(
			'labels'              => array(
				'name'               => 'Shop reviews',
				'singular_name'      => 'Shop review',
				'menu_name'          => 'Shop reviews',
				'all_items'          => 'Shop reviews',
				'add_new'            => 'Add a review',
				'add_new_item'       => 'Add a review',
				'edit_item'          => 'Edit review',
				'search_items'       => 'Search reviews',
				'not_found'          => 'No reviews yet. Use Import reviews, or Add a review.',
				'not_found_in_trash' => 'No reviews in the bin.',
			),
			'public'              => false,
			'publicly_queryable'  => false,
			'exclude_from_search' => true,
			'show_ui'             => true,
			'show_in_menu'        => 'edit.php?post_type=product',
			'show_in_nav_menus'   => false,
			'show_in_admin_bar'   => false,
			'show_in_rest'        => false,
			'has_archive'         => false,
			'rewrite'             => false,
			'query_var'           => false,
			'capability_type'     => 'product',
			'map_meta_cap'        => true,
			'supports'            => false,
		) );
	}

	// -- Checking one review (the import and the edit screen) --------------------------------

	public static function platforms() {
		return array( 'etsy' => 'Etsy', 'ebay' => 'eBay', 'other' => 'Other' );
	}

	/**
	 * Feedback rated in words, not stars (eBay): kept as its own kind and never turned into a
	 * number of stars, which would misstate it. Only positive feedback can be a favourite, in the
	 * strip or the single review.
	 */
	public static function ratings() {
		return array( 'positive' => 'Positive feedback', 'neutral' => 'Neutral feedback', 'negative' => 'Negative feedback' );
	}

	/** positive, neutral or negative from "Positive" or "Positive feedback", else ''. */
	public static function rating( $value ) {
		$v = strtolower( trim( preg_replace( '/\s+/', ' ', (string) $value ) ) );
		$v = preg_replace( '/ feedback$/', '', $v );
		return isset( self::ratings()[ $v ] ) ? $v : '';
	}

	/** True for a review the strip, the single review and favourites may use: stars, or positive feedback. */
	public static function praise( $r ) {
		return '' === $r['rating'] || 'positive' === $r['rating'];
	}

	/** Words in UTF-8: a file saved by Excel in Windows encoding is converted, no letter changed. */
	public static function utf8( $text ) {
		$text = (string) $text;
		if ( function_exists( 'mb_check_encoding' ) && ! mb_check_encoding( $text, 'UTF-8' ) && function_exists( 'mb_convert_encoding' ) ) {
			$text = mb_convert_encoding( $text, 'UTF-8', 'Windows-1252' );
		}
		return $text;
	}

	/**
	 * The cleaner: removes HTML tags (and the insides of script and style) and does nothing else.
	 * A heart typed as <3 or "a < b" is kept: only real tags go. Shown escaped, always.
	 */
	public static function strip_html( $text ) {
		$text = self::utf8( $text );
		$text = preg_replace( '#<!--.*?-->#s', '', $text );
		$text = preg_replace( '#<(script|style)\b[^>]*>.*?</\1\s*>#is', '', $text );
		$text = preg_replace( '#</?[a-zA-Z][^<>]*>#', '', $text );
		return (string) $text;
	}

	private static function length( $text ) {
		return function_exists( 'mb_strlen' ) ? mb_strlen( $text, 'UTF-8' ) : strlen( $text );
	}

	/** etsy, ebay or other, and whether the word given was something else. */
	public static function platform( $value ) {
		$v = strtolower( trim( (string) $value ) );
		if ( false !== strpos( $v, 'etsy' ) ) {
			return array( 'etsy', false );
		}
		if ( false !== strpos( $v, 'ebay' ) ) {
			return array( 'ebay', false );
		}
		return array( 'other', '' !== $v && 'other' !== $v );
	}

	private static function month_number( $word ) {
		$w = strtolower( rtrim( (string) $word, '.' ) );
		$names = array( 'january', 'february', 'march', 'april', 'may', 'june', 'july', 'august', 'september', 'october', 'november', 'december' );
		foreach ( $names as $i => $full ) {
			// The full name, its first three letters, or "sept".
			if ( $w === $full || $w === substr( $full, 0, 3 ) || ( 8 === $i && 'sept' === $w ) ) {
				return $i + 1;
			}
		}
		return 0;
	}

	/**
	 * A date as month and year, "YYYY-MM", or '' when it cannot be read. Day-first for dates with
	 * slashes (UK): 03/04/2025 is April.
	 */
	public static function month( $value ) {
		$v = trim( preg_replace( '/\s+/u', ' ', (string) $value ) );
		// A year on its own (feedback that only says "past year"): kept and shown as the year.
		if ( preg_match( '/^(\d{4})$/', $v, $x ) ) {
			return ( (int) $x[1] >= 1990 && (int) $x[1] <= 2100 ) ? $x[1] : '';
		}
		$y = 0;
		$m = 0;
		$d = 1;
		if ( preg_match( '/^(\d{4})-(\d{1,2})(?:-(\d{1,2}))?(?:[ T].*)?$/', $v, $x ) ) {
			list( $y, $m, $d ) = array( (int) $x[1], (int) $x[2], isset( $x[3] ) && '' !== $x[3] ? (int) $x[3] : 1 );
		} elseif ( preg_match( '#^(\d{1,2})[/.\-](\d{1,2})[/.\-](\d{4})$#', $v, $x ) ) {
			list( $d, $m, $y ) = array( (int) $x[1], (int) $x[2], (int) $x[3] );
		} elseif ( preg_match( '#^(\d{1,2})[/.\-](\d{4})$#', $v, $x ) ) {
			list( $m, $y ) = array( (int) $x[1], (int) $x[2] );
		} elseif ( preg_match( '/^(?:(\d{1,2})(?:st|nd|rd|th)? )?([A-Za-z]+\.?),? (\d{4})$/', $v, $x ) ) {
			list( $d, $m, $y ) = array( '' !== $x[1] ? (int) $x[1] : 1, self::month_number( $x[2] ), (int) $x[3] );
		} elseif ( preg_match( '/^([A-Za-z]+\.?) (\d{1,2})(?:st|nd|rd|th)?,? (\d{4})$/', $v, $x ) ) {
			list( $m, $d, $y ) = array( self::month_number( $x[1] ), (int) $x[2], (int) $x[3] );
		}
		if ( $y < 1990 || $y > 2100 || $m < 1 || $m > 12 || $d < 1 || $d > 31 ) {
			return '';
		}
		return sprintf( '%04d-%02d', $y, $m );
	}

	/** "March 2025" from "2025-03"; "2026" from a year on its own. */
	public static function month_label( $month ) {
		if ( preg_match( '/^\d{4}$/', (string) $month ) ) {
			return (string) $month;
		}
		if ( ! preg_match( '/^(\d{4})-(\d{2})$/', (string) $month, $x ) ) {
			return '';
		}
		return date( 'F', mktime( 0, 0, 0, (int) $x[2], 1, 2000 ) ) . ' ' . $x[1];
	}

	/** A whole number of stars, 1 to 5, or 0 when it cannot be read. Never guessed. */
	public static function stars( $value ) {
		$v = trim( (string) $value );
		if ( preg_match( '/^([1-5])(?:\.0+)?(?:\s*(?:\/\s*5|out of 5|stars?))?(?:\s*stars?)?$/i', $v, $x ) ) {
			return (int) $x[1];
		}
		if ( '' !== $v && preg_match( '/^[\x{2605}\x{2606}\s]+$/u', $v ) ) {
			$n = preg_match_all( '/\x{2605}/u', $v );
			return ( $n >= 1 && $n <= 5 ) ? $n : 0;
		}
		return 0;
	}

	/** A link to the review where it was written: http or https only, '' for none, false when bad. */
	public static function link( $value ) {
		$v = trim( (string) $value );
		if ( '' === $v ) {
			return '';
		}
		if ( strlen( $v ) > self::MAX_LINK || ! preg_match( '#^https?://[^\s<>"\'`]+$#i', $v ) ) {
			return false;
		}
		return $v;
	}

	/**
	 * The duplicate key: platform, name, month and the text with its spacing evened out. $item is
	 * given only for undated reviews (0.21.0-test6): the same buyer can leave the same words for
	 * two pieces, and with no date the item is what tells them apart. No item, the key as before.
	 */
	public static function key( $platform, $name, $month, $text, $item = '' ) {
		$lower = function ( $s ) {
			$s = trim( preg_replace( '/\s+/u', ' ', (string) $s ) );
			return function_exists( 'mb_strtolower' ) ? mb_strtolower( $s, 'UTF-8' ) : strtolower( $s );
		};
		$name = function_exists( 'mb_strtolower' ) ? mb_strtolower( trim( $name ), 'UTF-8' ) : strtolower( trim( $name ) );
		$text = trim( preg_replace( '/\s+/u', ' ', (string) $text ) );
		$base = $platform . '|' . $name . '|' . $month . '|' . sha1( $text );
		$item = $lower( $item );
		return sha1( '' === $item ? $base : $base . '|item|' . sha1( $item ) );
	}

	/**
	 * The same review whatever its date: platform, name and text. Used only to find an undated
	 * review when a later file gives it a date (0.21.0-test5).
	 */
	public static function loose_key( $platform, $name, $text, $item = '' ) {
		return self::key( $platform, $name, 'any date', $text, $item );
	}

	/** True for the date word that means "the platform gives no date and nobody can say". */
	public static function is_undated_word( $value ) {
		return 'undated' === strtolower( trim( (string) $value ) );
	}

	/**
	 * Check one review given as words (platform, name, date, stars, text, item, link, fav, hidden).
	 * Returns array( 'review' => fields, 'notes' => list ) or array( 'error' => one plain sentence ).
	 */
	public static function check( $in ) {
		$in   = is_array( $in ) ? $in : array();
		$get  = function ( $k ) use ( $in ) {
			return isset( $in[ $k ] ) && is_scalar( $in[ $k ] ) ? (string) $in[ $k ] : '';
		};
		$name = trim( self::strip_html( $get( 'name' ) ) );
		$text = self::strip_html( $get( 'text' ) );
		$item = trim( self::strip_html( $get( 'item' ) ) );
		$problems = array();
		if ( '' === $name ) {
			$problems[] = 'no name';
		}
		$undated = self::is_undated_word( $get( 'date' ) );
		$month   = $undated ? '' : self::month( $get( 'date' ) );
		if ( $undated ) {
			// Kept with no date, shown with none, sorted after every dated review.
		} elseif ( '' === trim( $get( 'date' ) ) ) {
			$problems[] = 'no date (write Undated when the platform gives none)';
		} elseif ( false !== stripos( $get( 'date' ), 'TO CONFIRM' ) ) {
			$problems[] = 'the date says TO CONFIRM, so the owner needs to fill it in first (a year such as 2026 is enough)';
		} elseif ( '' === $month ) {
			$problems[] = 'the date "' . trim( $get( 'date' ) ) . '" could not be read';
		}
		if ( '' === trim( $text ) ) {
			$problems[] = 'no review text';
		}
		$stars  = self::stars( $get( 'stars' ) );
		$rating = 0 === $stars ? self::rating( $get( 'stars' ) ) : '';
		if ( 0 === $stars && '' === $rating ) {
			$problems[] = '' === trim( $get( 'stars' ) ) ? 'no stars or rating' : 'the stars "' . trim( $get( 'stars' ) ) . '" are not a whole number from 1 to 5, or Positive, Neutral or Negative';
		}
		$link = self::link( $get( 'link' ) );
		if ( false === $link ) {
			$problems[] = 'the link is not a web address starting with http or https';
		}
		if ( self::length( $name ) > self::MAX_NAME ) {
			$problems[] = 'the name is longer than ' . self::MAX_NAME . ' letters';
		}
		if ( self::length( $text ) > self::MAX_TEXT ) {
			$problems[] = 'the review is longer than ' . self::MAX_TEXT . ' letters';
		}
		if ( self::length( $item ) > self::MAX_ITEM ) {
			$problems[] = 'the item is longer than ' . self::MAX_ITEM . ' letters';
		}
		if ( $problems ) {
			return array( 'error' => 'Refused: ' . implode( ', ', $problems ) . '.' );
		}
		list( $platform, $other ) = self::platform( $get( 'platform' ) );
		$notes = $other ? array( 'Platform "' . trim( $get( 'platform' ) ) . '" kept as Other.' ) : array();
		$yes   = function ( $k ) use ( $in ) {
			return ! empty( $in[ $k ] ) && ! in_array( strtolower( (string) $in[ $k ] ), array( 'no', 'false', '0' ), true );
		};
		return array(
			'review' => array(
				'platform' => $platform,
				'name'     => $name,
				'month'    => $month,
				'undated'  => $undated,
				'stars'    => $stars,
				'rating'   => $rating,
				'text'     => $text,
				'item'     => $item,
				'link'     => (string) $link,
				'fav'      => $yes( 'fav' ),
				'hidden'   => $yes( 'hidden' ),
				'key'      => self::key( $platform, $name, $month, $text, $undated ? $item : '' ),
			),
			'notes'  => $notes,
		);
	}

	// -- Saving and reading ----------------------------------------------------------------

	/** Write a checked review (a new one when $id is 0). Returns the id, or 0. */
	public static function save( $review, $id = 0 ) {
		$id    = (int) $id;
		$title = $review['name'];
		if ( $id <= 0 ) {
			$id = (int) wp_insert_post( array( 'post_type' => self::TYPE, 'post_status' => 'publish', 'post_title' => $title, 'post_content' => '', 'post_author' => (int) get_current_user_id() ) );
			if ( $id <= 0 ) {
				return 0;
			}
		}
		foreach ( self::fields() as $f ) {
			$v = $review[ $f ];
			if ( 'fav' === $f || 'hidden' === $f || 'undated' === $f ) {
				if ( $v ) {
					update_post_meta( $id, self::META . $f, 'yes' );
				} else {
					delete_post_meta( $id, self::META . $f );
				}
				continue;
			}
			update_post_meta( $id, self::META . $f, wp_slash( 'stars' === $f ? (string) (int) $v : (string) $v ) );
		}
		return $id;
	}

	/** One review as saved, or null. */
	public static function read( $id ) {
		$id = (int) $id;
		$r  = array( 'id' => $id );
		foreach ( self::fields() as $f ) {
			$v       = get_post_meta( $id, self::META . $f, true );
			$r[ $f ] = is_string( $v ) ? $v : '';
		}
		if ( '' === $r['name'] || '' === $r['text'] ) {
			return null;
		}
		$r['stars']  = (int) $r['stars'];
		$r['rating'] = isset( self::ratings()[ $r['rating'] ] ) ? $r['rating'] : '';
		if ( '' === $r['rating'] && ( $r['stars'] < 1 || $r['stars'] > 5 ) ) {
			return null;
		}
		$r['fav']     = 'yes' === $r['fav'];
		$r['hidden']  = 'yes' === $r['hidden'];
		$r['undated'] = 'yes' === $r['undated'];
		return $r;
	}

	/** Give an undated review the date a later file found for it. Nothing else changes. */
	public static function fill_date( $id, $month ) {
		$r = self::read( $id );
		if ( ! $r || ! $r['undated'] || '' === (string) $month ) {
			return false;
		}
		update_post_meta( (int) $id, self::META . 'month', (string) $month );
		delete_post_meta( (int) $id, self::META . 'undated' );
		update_post_meta( (int) $id, self::META . 'key', self::key( $r['platform'], $r['name'], (string) $month, $r['text'] ) );
		return true;
	}

	/**
	 * Every saved review by its loose key: array( 'undated' => id of an undated one or 0,
	 * 'dated' => true when a dated one exists ).
	 */
	public static function loose_index() {
		$out = array();
		foreach ( self::all() as $r ) {
			$k = self::loose_key( $r['platform'], $r['name'], $r['text'], $r['item'] );
			if ( ! isset( $out[ $k ] ) ) {
				$out[ $k ] = array( 'undated' => 0, 'dated' => false );
			}
			if ( $r['undated'] ) {
				$out[ $k ]['undated'] = $out[ $k ]['undated'] ? $out[ $k ]['undated'] : $r['id'];
			} else {
				$out[ $k ]['dated'] = true;
			}
		}
		return $out;
	}

	/** Every saved review (published only), newest month first; undated ones last ('' sorts last). */
	public static function all() {
		if ( ! function_exists( 'get_posts' ) ) {
			return array();
		}
		$ids = get_posts( array(
			'post_type'        => self::TYPE,
			'post_status'      => 'publish',
			'numberposts'      => 5000,
			'fields'           => 'ids',
			'orderby'          => 'ID',
			'order'            => 'DESC',
			'suppress_filters' => true,
		) );
		$ids = array_map( 'intval', (array) $ids );
		if ( $ids && function_exists( 'update_meta_cache' ) ) {
			update_meta_cache( 'post', $ids );
		}
		$out = array();
		foreach ( $ids as $id ) {
			$r = self::read( $id );
			if ( $r ) {
				$out[] = $r;
			}
		}
		usort( $out, function ( $a, $b ) {
			$c = strcmp( $b['month'], $a['month'] );
			return 0 !== $c ? $c : $b['id'] - $a['id'];
		} );
		return $out;
	}

	/** The reviews a visitor may see, newest first, optionally on one platform. */
	public static function visible( $platform = '' ) {
		return array_values( array_filter( self::all(), function ( $r ) use ( $platform ) {
			return ! $r['hidden'] && ( '' === $platform || $platform === $r['platform'] );
		} ) );
	}

	/** Every duplicate key already saved, as a lookup. */
	public static function keys() {
		$out = array();
		foreach ( self::all() as $r ) {
			if ( '' !== $r['key'] ) {
				$out[ $r['key'] ] = $r['id'];
			}
		}
		return $out;
	}

	/** The name as the setting asks: as given, or "Sarah K." for initials. */
	public static function shown_name( $name ) {
		$name = trim( (string) $name );
		if ( 'initials' !== get_option( self::OPT_NAMES, '' ) ) {
			return $name;
		}
		$parts = preg_split( '/\s+/u', $name );
		if ( count( $parts ) < 2 ) {
			return $name;
		}
		$last    = end( $parts );
		$initial = function_exists( 'mb_substr' ) ? mb_strtoupper( mb_substr( $last, 0, 1, 'UTF-8' ), 'UTF-8' ) : strtoupper( substr( $last, 0, 1 ) );
		return $parts[0] . ' ' . $initial . '.';
	}
}
