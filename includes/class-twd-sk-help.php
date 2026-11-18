<?php
/**
 * TWD_SK_Help: the tutorial list on the Welcome screen (0.22.0).
 *
 * One small public JSON file in this plugin's repo (help/twd-site-help.json) lists the guides and videos, which
 * live on the agency's own site. A signed-in editor's Welcome screen fetches it, the way the updater fetches its
 * file: one fixed https address, no redirects, a short wait, a size limit, cached for 12 hours, and nothing about
 * the site or the person is sent. Every entry is checked; a link must be https on the agency's own domain. Any
 * doubt means the section is simply not shown. Nothing is ever fetched for a visitor, in safe mode, or outside
 * wp-admin's Welcome screen. Changing the list needs no plugin release.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class TWD_SK_Help {

	const FEED_URL  = 'https://raw.githubusercontent.com/thistwistedyouth/twd-site-kit/main/help/twd-site-help.json';
	const SCHEMA    = 'twd-site-help/1';
	const HOST      = 'therapywebdesigns.co.uk';
	const TRANSIENT = 'twd_sk_help_feed';
	const TTL       = 43200; // 12 hours
	const FAIL_TTL  = 3600;  // 1 hour: a failed fetch is not retried on every page view
	const MAX_BYTES = 50000;
	const MAX_ITEMS = 12;

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

	private static function fetch() {
		$response = wp_safe_remote_get( self::FEED_URL, array( 'timeout' => 5, 'redirection' => 0, 'limit_response_size' => self::MAX_BYTES ) );
		if ( is_wp_error( $response ) || 200 !== (int) wp_remote_retrieve_response_code( $response ) ) {
			return array();
		}
		return self::parse( (string) wp_remote_retrieve_body( $response ) );
	}

	/** Read the file's text. Pure, so a test can feed it anything. */
	public static function parse( $body ) {
		if ( '' === $body || strlen( $body ) > self::MAX_BYTES ) {
			return array();
		}
		$data = json_decode( $body, true );
		if ( ! is_array( $data ) || ! isset( $data['schema'] ) || self::SCHEMA !== $data['schema'] || empty( $data['items'] ) || ! is_array( $data['items'] ) ) {
			return array();
		}
		$out = array();
		foreach ( $data['items'] as $row ) {
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
		$title   = self::text( isset( $row['title'] ) ? $row['title'] : '', 80 );
		$summary = self::text( isset( $row['summary'] ) ? $row['summary'] : '', 200 );
		$url     = self::safe_url( isset( $row['url'] ) ? $row['url'] : '' );
		$kind    = ( isset( $row['kind'] ) && 'video' === $row['kind'] ) ? 'video' : 'article';
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
		$text = trim( preg_replace( '/\s+/', ' ', strip_tags( $text ) ) );
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
