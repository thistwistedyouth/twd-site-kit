<?php
/**
 * TWD_SK_Shop_Reviews_Show (0.21.0): the reviews for visitors.
 *
 * [twd_reviews] (style strip, the default), [twd_reviews style="wall"] and
 * [twd_reviews style="single"]; and, when the setting is on, one short favourite under Ask about
 * this piece on product pages. On a Site Kit page the shortcode goes in an Embed section.
 *
 * Drawn on the server with no visitor script: the wall's platform filter and Show more are plain
 * links, and a long review folds with details and summary. No structured data of any kind (a shop
 * marking up its own reviews is penalised by search engines). One small stylesheet, every rule
 * under .twd-sk-reviews, coloured from the shop's own variables.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class TWD_SK_Shop_Reviews_Show {

	const SHORTCODE = 'twd_reviews';
	const HANDLE    = 'twd-site-kit-reviews';
	const PER       = 24;
	const LONG      = 360;
	const CUT       = 280;
	const SHORT     = 240;
	const Q_PLATFORM = 'twd_reviews_platform';
	const Q_SHOW     = 'twd_reviews_show';

	public static function init() {
		add_shortcode( self::SHORTCODE, array( __CLASS__, 'shortcode' ) );
		add_action( 'wp_enqueue_scripts', array( __CLASS__, 'maybe_enqueue' ), 26 );
		add_action( 'woocommerce_single_product_summary', array( __CLASS__, 'product_review' ), 36 );
	}

	// -- The stylesheet ---------------------------------------------------------------------------

	/** True when this page shows reviews: a product page with the setting on, or a page using the shortcode. */
	public static function page_needs_style() {
		if ( function_exists( 'is_product' ) && is_product() ) {
			return self::product_setting_on();
		}
		if ( ! function_exists( 'is_singular' ) || ! is_singular() ) {
			return false;
		}
		$id = (int) get_queried_object_id();
		return $id > 0 && self::page_uses( $id );
	}

	/** The page's own content, or a Site Kit page's HTML or one of its embeds, holds [twd_reviews. */
	public static function page_uses( $id ) {
		return TWD_SK_Shop_Layout::page_uses( $id, self::SHORTCODE );
	}

	public static function maybe_enqueue() {
		if ( self::page_needs_style() ) {
			self::enqueue();
		}
	}

	/** Load the stylesheet (in the head when called early; in the footer when only the shortcode found it). */
	public static function enqueue() {
		if ( ! function_exists( 'wp_enqueue_style' ) || ( function_exists( 'wp_style_is' ) && wp_style_is( self::HANDLE, 'enqueued' ) ) ) {
			return;
		}
		$ver = defined( 'TWD_SK_VERSION' ) ? TWD_SK_VERSION : false;
		wp_enqueue_style( self::HANDLE, TWD_SK_URL . 'modules/shop/assets/twd-site-kit-reviews.css', array(), $ver );
		wp_add_inline_style( self::HANDLE, self::inline_css( TWD_SK_URL . 'assets/fonts/' ) );
	}

	/** The serif and the Shop colour, for pages outside the shop (the product page already has both). */
	public static function inline_css( $fonts ) {
		return TWD_SK_Shop_Layout::font_css( $fonts ) . '.twd-sk-reviews{--twd-sk-rv-ink:' . TWD_SK_Shop_Layout::colour() . '}';
	}

	// -- Choosing ---------------------------------------------------------------------------------

	public static function product_setting_on() {
		return 'no' !== get_option( TWD_SK_Shop_Reviews::OPT_PRODUCT, 'yes' );
	}

	private static function length( $text ) {
		return function_exists( 'mb_strlen' ) ? mb_strlen( $text, 'UTF-8' ) : strlen( $text );
	}

	/** Up to $count favourites, newest first; with none ticked, the newest reviews. */
	public static function strip_reviews( $count = 3, $platform = '' ) {
		$count   = max( 1, min( 4, (int) $count ) );
		$visible = array_values( array_filter( TWD_SK_Shop_Reviews::visible( $platform ), array( 'TWD_SK_Shop_Reviews', 'praise' ) ) );
		$favs    = array_values( array_filter( $visible, function ( $r ) {
			return $r['fav'];
		} ) );
		return array_slice( $favs ? $favs : $visible, 0, $count );
	}

	/** One short favourite; $seed (a product number) picks which, so pieces differ and pages cache. */
	public static function single_review( $seed = 0 ) {
		$self  = __CLASS__;
		$short = array_values( array_filter( TWD_SK_Shop_Reviews::visible(), function ( $r ) use ( $self ) {
			return $r['fav'] && TWD_SK_Shop_Reviews::praise( $r ) && $self::fits( $r['text'] );
		} ) );
		if ( ! $short ) {
			return null;
		}
		return $short[ abs( (int) $seed ) % count( $short ) ];
	}

	public static function fits( $text ) {
		return self::length( trim( $text ) ) <= self::SHORT;
	}

	// -- Drawing ----------------------------------------------------------------------------------

	/** Plain words as paragraphs: a blank line starts a new one, a single line break stays. Escaped. */
	public static function paragraphs( $text ) {
		$out = '';
		foreach ( preg_split( '/\R\s*\R/u', trim( $text ) ) as $p ) {
			if ( '' !== trim( $p ) ) {
				$out .= '<p>' . preg_replace( '/\R/u', '<br>', esc_html( trim( $p ) ) ) . '</p>';
			}
		}
		return $out;
	}

	/** The words, with a long review's rest folded away. The two parts joined are the review exactly. */
	public static function words_html( $text ) {
		$text = trim( $text );
		if ( self::length( $text ) <= self::LONG ) {
			return self::paragraphs( $text );
		}
		$head = function_exists( 'mb_substr' ) ? mb_substr( $text, 0, self::CUT, 'UTF-8' ) : substr( $text, 0, self::CUT );
		$at   = max( strrpos( $head, ' ' ), strrpos( $head, "\n" ) );
		if ( false !== $at && $at > 0 ) {
			$head = substr( $head, 0, $at );
		}
		$rest = substr( $text, strlen( $head ) );
		return self::paragraphs( $head ) . '<details class="twd-sk-reviews__more"><summary>Read more</summary>' . self::paragraphs( $rest ) . '</details>';
	}

	public static function stars_html( $n ) {
		$n   = max( 1, min( 5, (int) $n ) );
		$out = '<p class="twd-sk-reviews__stars" role="img" aria-label="' . esc_attr( $n . ' out of 5 stars' ) . '">';
		$out .= '<span class="twd-sk-reviews__on" aria-hidden="true">' . str_repeat( "\u{2605}", $n ) . '</span>';
		if ( $n < 5 ) {
			$out .= '<span class="twd-sk-reviews__off" aria-hidden="true">' . str_repeat( "\u{2605}", 5 - $n ) . '</span>';
		}
		return $out . '</p>';
	}

	public static function card( $r, $id = '' ) {
		$platforms = TWD_SK_Shop_Reviews::platforms();
		$label     = 'other' !== $r['platform'] && isset( $platforms[ $r['platform'] ] ) ? $platforms[ $r['platform'] ] : '';
		$out       = '<figure class="twd-sk-reviews__card"' . ( '' !== $id ? ' id="' . esc_attr( $id ) . '"' : '' ) . '>';
		$ratings   = TWD_SK_Shop_Reviews::ratings();
		$out      .= '' !== $r['rating'] && isset( $ratings[ $r['rating'] ] )
			? '<p class="twd-sk-reviews__rating">' . esc_html( $ratings[ $r['rating'] ] ) . '</p>'
			: self::stars_html( $r['stars'] );
		$out      .= '<blockquote class="twd-sk-reviews__text">' . self::words_html( $r['text'] ) . '</blockquote>';
		$out      .= '<figcaption class="twd-sk-reviews__by"><span class="twd-sk-reviews__name">' . esc_html( TWD_SK_Shop_Reviews::shown_name( $r['name'] ) ) . '</span>';
		$when      = TWD_SK_Shop_Reviews::month_label( $r['month'] );
		if ( '' !== $when ) {
			$out .= '<span class="twd-sk-reviews__when">' . esc_html( $when ) . '</span>';
		}
		if ( '' !== $label ) {
			$out .= '<span class="twd-sk-reviews__platform">' . esc_html( $label ) . '</span>';
		}
		$out .= '</figcaption>';
		if ( '' !== $r['item'] ) {
			$out .= '<p class="twd-sk-reviews__item">' . esc_html( $r['item'] ) . '</p>';
		}
		$link = TWD_SK_Shop_Reviews::link( $r['link'] );
		if ( is_string( $link ) && '' !== $link ) {
			$out .= '<p class="twd-sk-reviews__source"><a href="' . esc_url( $link ) . '" rel="nofollow noopener">' . esc_html( '' !== $label ? 'See it on ' . $label : 'See the review' ) . '</a></p>';
		}
		return $out . '</figure>';
	}

	/** "312 five-star reviews on Etsy and eBay", from the owner's typed totals and links only. '' when none typed. */
	public static function summary_html() {
		$parts = array();
		$sum   = 0;
		foreach ( array( 'etsy' => 'Etsy', 'ebay' => 'eBay' ) as $k => $label ) {
			$total = trim( (string) get_option( 'twd_sk_shop_reviews_' . $k . '_total', '' ) );
			if ( ! ctype_digit( $total ) || (int) $total <= 0 ) {
				continue;
			}
			$sum    += (int) $total;
			$link    = TWD_SK_Shop_Reviews::link( get_option( 'twd_sk_shop_reviews_' . $k . '_link', '' ) );
			$parts[] = is_string( $link ) && '' !== $link ? '<a href="' . esc_url( $link ) . '" rel="nofollow noopener">' . esc_html( $label ) . '</a>' : esc_html( $label );
		}
		if ( ! $parts ) {
			return '';
		}
		$words = trim( TWD_SK_Shop_Reviews::strip_html( (string) get_option( TWD_SK_Shop_Reviews::OPT_WORDS, '' ) ) );
		$noun  = 1 === $sum ? 'review' : 'reviews';
		$where = 2 === count( $parts ) ? $parts[0] . ' and ' . $parts[1] : $parts[0];
		return '<p class="twd-sk-reviews__summary"><strong>' . esc_html( number_format( $sum ) ) . '</strong> ' . ( '' !== $words ? esc_html( $words ) . ' ' : '' ) . $noun . ' on ' . $where . '</p>';
	}

	private static function all_link() {
		$page = (int) get_option( TWD_SK_Shop_Reviews::OPT_PAGE, 0 );
		if ( $page <= 0 || 'publish' !== get_post_status( $page ) || ( function_exists( 'get_queried_object_id' ) && (int) get_queried_object_id() === $page ) ) {
			return '';
		}
		return '<p class="twd-sk-reviews__all"><a href="' . esc_url( get_permalink( $page ) ) . '">Read all reviews</a></p>';
	}

	public static function strip_html( $count = 3, $summary = true ) {
		$reviews = self::strip_reviews( $count );
		if ( ! $reviews ) {
			return '';
		}
		$out = '<div class="twd-sk-reviews twd-sk-reviews--strip">' . ( $summary ? self::summary_html() : '' ) . '<div class="twd-sk-reviews__grid">';
		foreach ( $reviews as $r ) {
			$out .= self::card( $r );
		}
		return $out . '</div>' . self::all_link() . '</div>';
	}

	public static function single_html( $seed = 0 ) {
		$r = self::single_review( $seed );
		if ( ! $r ) {
			return '';
		}
		return '<div class="twd-sk-reviews twd-sk-reviews--single">' . self::card( $r ) . self::all_link() . '</div>';
	}

	/** The wall. $platform and $show come from the address (checked here); $base is this page's address. */
	public static function wall_html( $platform, $show, $base, $summary = true ) {
		$platforms = TWD_SK_Shop_Reviews::platforms();
		$platform  = isset( $platforms[ $platform ] ) ? $platform : '';
		$all       = TWD_SK_Shop_Reviews::visible();
		if ( ! $all ) {
			return '';
		}
		$counts = array();
		foreach ( $all as $r ) {
			$counts[ $r['platform'] ] = ( isset( $counts[ $r['platform'] ] ) ? $counts[ $r['platform'] ] : 0 ) + 1;
		}
		$list  = '' === $platform ? $all : array_values( array_filter( $all, function ( $r ) use ( $platform ) {
			return $platform === $r['platform'];
		} ) );
		$total = count( $list );
		$show  = max( self::PER, min( $total, (int) ceil( max( 1, (int) $show ) / self::PER ) * self::PER ) );

		$out = '<div class="twd-sk-reviews twd-sk-reviews--wall" id="twd-sk-reviews-wall">' . ( $summary ? self::summary_html() : '' );
		if ( count( $counts ) > 1 ) {
			$out .= '<nav class="twd-sk-reviews__filter" aria-label="Show reviews from">';
			$opts = array( '' => 'All (' . count( $all ) . ')' );
			foreach ( $platforms as $k => $label ) {
				if ( isset( $counts[ $k ] ) ) {
					$opts[ $k ] = $label . ' (' . $counts[ $k ] . ')';
				}
			}
			foreach ( $opts as $k => $label ) {
				$href = '' === $k ? $base : add_query_arg( array( self::Q_PLATFORM => $k ), $base );
				$out .= '<a class="twd-sk-reviews__pick" href="' . esc_url( $href . '#twd-sk-reviews-wall' ) . '"' . ( $k === $platform ? ' aria-current="true"' : '' ) . '>' . esc_html( $label ) . '</a>';
			}
			$out .= '</nav>';
		}
		$out .= '<div class="twd-sk-reviews__grid">';
		foreach ( array_slice( $list, 0, $show ) as $i => $r ) {
			$out .= self::card( $r, 'twd-sk-review-' . ( $i + 1 ) );
		}
		$out .= '</div>';
		if ( $total > $show ) {
			$args = array( self::Q_SHOW => $show + self::PER );
			if ( '' !== $platform ) {
				$args[ self::Q_PLATFORM ] = $platform;
			}
			$out .= '<p class="twd-sk-reviews__shown">Showing ' . (int) $show . ' of ' . (int) $total . '</p>';
			$out .= '<p class="twd-sk-reviews__next"><a href="' . esc_url( add_query_arg( $args, $base ) . '#twd-sk-review-' . ( $show + 1 ) ) . '">Show more</a></p>';
		}
		return $out . '</div>';
	}

	// -- Hooks ------------------------------------------------------------------------------------

	public static function shortcode( $atts ) {
		$atts  = is_array( $atts ) ? $atts : array();
		$style = isset( $atts['style'] ) ? strtolower( (string) $atts['style'] ) : 'strip';
		$sum   = ! isset( $atts['summary'] ) || 'no' !== strtolower( (string) $atts['summary'] );
		self::enqueue();
		if ( 'wall' === $style ) {
			$platform = isset( $_GET[ self::Q_PLATFORM ] ) ? sanitize_key( wp_unslash( $_GET[ self::Q_PLATFORM ] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification -- a display choice.
			$show     = isset( $_GET[ self::Q_SHOW ] ) ? absint( $_GET[ self::Q_SHOW ] ) : self::PER; // phpcs:ignore WordPress.Security.NonceVerification
			$id       = function_exists( 'get_queried_object_id' ) ? (int) get_queried_object_id() : 0;
			$base     = $id > 0 ? get_permalink( $id ) : home_url( '/' );
			return self::wall_html( $platform, $show, (string) $base, $sum );
		}
		if ( 'single' === $style ) {
			$id = function_exists( 'get_queried_object_id' ) ? (int) get_queried_object_id() : 0;
			return self::single_html( $id );
		}
		return self::strip_html( isset( $atts['count'] ) ? (int) $atts['count'] : 3, $sum );
	}

	public static function product_review() {
		global $product;
		if ( ! self::product_setting_on() || ! is_object( $product ) ) {
			return;
		}
		echo self::single_html( (int) $product->get_id() ); // phpcs:ignore WordPress.Security.EscapeOutput -- built escaped.
	}
}
