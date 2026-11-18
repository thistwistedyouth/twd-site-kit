<?php
/**
 * TWD_SK_Help: the guides and videos on the Welcome screen (0.22.0).
 *
 * They are ordinary posts on the agency's own site, in one category (client-help), written there with the normal
 * WordPress editor; a post tagged video is shown as a video. A signed-in editor's Welcome screen reads that category
 * from the agency site's public WordPress feed: two fixed https addresses on one fixed host, no redirects, a short
 * wait, a size limit, cached for 12 hours, and nothing about the site or the person is sent. Every entry is checked;
 * a link must be https on the agency's own domain. Any doubt means the section is simply not shown. Nothing is ever
 * fetched for a visitor, in safe mode, or outside wp-admin's Welcome screen. Adding a guide needs no plugin release.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class TWD_SK_Help {

	const HOST      = 'therapywebdesigns.co.uk';
	const CATEGORY  = 'client-help';
	const VIDEO_TAG = 'video';
	const TRANSIENT = 'twd_sk_help_feed';
	const TTL       = 43200; // 12 hours
	const FAIL_TTL  = 3600;  // 1 hour: a failed fetch is not retried on every page view
	const MAX_BYTES = 60000;
	const MAX_ITEMS = 12;

	/** The two fixed addresses (the category's id, then its posts). */
	public static function categories_url() {
		return 'https://' . self::HOST . '/wp-json/wp/v2/categories?slug=' . self::CATEGORY . '&_fields=id';
	}

	public static function posts_url( $id ) {
		return 'https://' . self::HOST . '/wp-json/wp/v2/posts?categories=' . (int) $id . '&per_page=' . self::MAX_ITEMS . '&_fields=title,excerpt,link,class_list';
	}

	/** The checked entries: each title, summary, url, kind (article or video). Empty on any doubt. */
	public static function items() {
		if ( TWD_SK_Safe::on() || ! current_user_can( 'edit_pages' ) || ! apply_filters( 'twd_sk_help_feed_on', true ) ) {
			return array();
		}
		$cached = get_transient( self::TRANSIENT );
		if ( is_array( $cached ) ) {
			return isset( $cached['items'] ) && is_array( $cached['items'] ) ? $cached['items'] : array();
		}
		$items = self::fetch();
		set_transient( self::TRANSIENT, array( 'items' => $items ), $items ? self::TTL : self::FAIL_TTL );
		return $items;
	}

	private static function get( $url ) {
		$response = wp_safe_remote_get( $url, array( 'timeout' => 5, 'redirection' => 0, 'limit_response_size' => self::MAX_BYTES ) );
		if ( is_wp_error( $response ) || 200 !== (int) wp_remote_retrieve_response_code( $response ) ) {
			return '';
		}
		return (string) wp_remote_retrieve_body( $response );
	}

	private static function fetch() {
		$cat = json_decode( self::get( self::categories_url() ), true );
		$id  = ( is_array( $cat ) && isset( $cat[0]['id'] ) ) ? (int) $cat[0]['id'] : 0;
		if ( $id < 1 ) {
			return array();
		}
		return self::parse( self::get( self::posts_url( $id ) ) );
	}

	/** Read the posts list. Pure, so a test can feed it anything. */
	public static function parse( $body ) {
		if ( '' === $body || strlen( $body ) > self::MAX_BYTES ) {
			return array();
		}
		$data = json_decode( $body, true );
		if ( ! is_array( $data ) ) {
			return array();
		}
		$out = array();
		foreach ( $data as $row ) {
			$item = self::clean_item( $row );
			if ( $item ) {
				$out[] = $item;
			}
			if ( count( $out ) >= self::MAX_ITEMS ) {
				break;
			}
		}
		return $out;
	}

	public static function clean_item( $row ) {
		if ( ! is_array( $row ) ) {
			return null;
		}
		$title   = self::text( isset( $row['title']['rendered'] ) ? $row['title']['rendered'] : '', 80 );
		$summary = self::text( isset( $row['excerpt']['rendered'] ) ? $row['excerpt']['rendered'] : '', 200 );
		$url     = self::safe_url( isset( $row['link'] ) ? $row['link'] : '' );
		$classes = ( isset( $row['class_list'] ) && is_array( $row['class_list'] ) ) ? $row['class_list'] : array();
		$kind    = in_array( 'tag-' . self::VIDEO_TAG, $classes, true ) ? 'video' : 'article';
		if ( '' === $title || '' === $url ) {
			return null;
		}
		return array( 'title' => $title, 'summary' => $summary, 'url' => $url, 'kind' => $kind );
	}
	/** Plain text: no tags, no long dashes, cut to a length. */
	public static function text( $text, $max ) {
		if ( ! is_string( $text ) ) {
			return '';
		}
		$text = trim( preg_replace( '/\s+/', ' ', html_entity_decode( strip_tags( $text ), ENT_QUOTES, 'UTF-8' ) ) );
		$text = preg_replace( '/\s*[\x{2013}\x{2014}]\s*/u', ', ', $text );
		return function_exists( 'mb_substr' ) ? mb_substr( $text, 0, $max ) : substr( $text, 0, $max );
	}

	/** https only, no sign-in details, no port, and only the agency's own domain (or one of its sub-domains). */
	public static function safe_url( $url ) {
		if ( ! is_string( $url ) || strlen( $url ) > 300 ) {
			return '';
		}
		$p = parse_url( $url );
		if ( ! is_array( $p ) || ! isset( $p['scheme'], $p['host'] ) || 'https' !== strtolower( $p['scheme'] ) || isset( $p['user'] ) || isset( $p['pass'] ) || isset( $p['port'] ) ) {
			return '';
		}
		$host = strtolower( $p['host'] );
		if ( self::HOST !== $host && '.' . self::HOST !== substr( $host, -strlen( self::HOST ) - 1 ) ) {
			return '';
		}
		return preg_match( '/[\s<>"\']/', $url ) ? '' : $url;
	}
}
