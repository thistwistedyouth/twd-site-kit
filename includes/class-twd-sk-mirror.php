<?php
/**
 * TWD_SK_Mirror: a plain-text copy of each kit page, so search and SEO tools can see it.
 *
 * The page HTML lives in post meta, which WordPress search, Yoast's analysis and sitemap tools
 * do not read. On every new version this keeps two copies of the page's TEXT (no markup, no
 * shortcodes):
 *
 *  - always, in the meta field _twd_sk_text;
 *  - for a page that uses the kit template, also in the page's own content. The template never
 *    prints the content, so nothing shows twice, and core search, feeds, excerpts and Yoast
 *    all find the words. If the page is later switched to another template the text simply shows,
 *    plain but readable, instead of a blank page.
 *
 * Content is only ever replaced when it is empty or is the plugin's own earlier text (checked by a
 * hash). A page that holds the [twd_page] shortcode, a layout saved by Elementor, or words a person
 * typed keeps its content exactly as it is and only gets the meta copy; to make such a page
 * searchable, clear its content once after switching it to the kit template.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class TWD_SK_Mirror {

	const META_TEXT = '_twd_sk_text';
	const META_HASH = '_twd_sk_mirror_hash';
	const MAX_BYTES = 100 * 1024;

	private static $busy = false;

	public static function init() {
		add_action( 'twd_sk_committed', array( __CLASS__, 'on_commit' ), 10, 2 );
		// Yoast's image sitemap (a no-op when Yoast is not installed).
		add_filter( 'wpseo_sitemap_urlimages', array( __CLASS__, 'filter_sitemap_images' ), 10, 2 );
	}

	/**
	 * Plain text of some page HTML: headings, paragraphs and list items on their own lines,
	 * picture descriptions included, shortcodes and tags removed.
	 */
	public static function text( $html ) {
		if ( ! is_string( $html ) || '' === $html ) {
			return '';
		}
		// Picture descriptions are page text too.
		$html = preg_replace( '/<img\b[^>]*\balt="([^"]*)"[^>]*>/i', "\n$1\n", $html );
		$html = preg_replace( '/<(?:script|style)\b.*?<\/(?:script|style)>/is', '', $html );
		$html = preg_replace( '/<br\s*\/?>/i', "\n", $html );
		$html = preg_replace( '/<\/(?:p|h[1-6]|li|div|section|header|footer|aside|article|blockquote|figcaption|figure|dt|dd|summary|details|ul|ol|dl|cite|q)>/i', "\n", $html );
		$html = preg_replace( '/\[\/?twd_[a-z_]+[^\]]*\]/i', '', $html );
		$text = html_entity_decode( strip_tags( $html ), ENT_QUOTES | ENT_HTML5, 'UTF-8' );
		$text = str_replace( "\xC2\xA0", ' ', $text );
		$text = preg_replace( '/[ \t]+/', ' ', $text );
		$text = preg_replace( '/ *\n */', "\n", $text );
		$text = preg_replace( '/\n{2,}/', "\n", $text );
		$text = trim( $text );
		if ( strlen( $text ) > self::MAX_BYTES ) {
			$text = rtrim( substr( $text, 0, self::MAX_BYTES ) );
		}
		return $text;
	}

	/** After a new version: refresh the mirror for that page. */
	public static function on_commit( $post_id, $clean_html ) {
		self::write( (int) $post_id, (string) $clean_html );
	}

	/** Refresh the mirror from what is stored now (used after a template switch). */
	public static function sync( $post_id ) {
		self::write( (int) $post_id, TWD_SK_Store::get_current( (int) $post_id ) );
	}

	private static function write( $post_id, $html ) {
		if ( $post_id <= 0 || self::$busy ) {
			return;
		}
		$text = self::text( $html );
		update_post_meta( $post_id, self::META_TEXT, wp_slash( $text ) );

		// Only a page on the kit template, whose content is never printed, gets the page content too.
		if ( ! TWD_SK_Template::uses_template( $post_id ) ) {
			return;
		}
		$post = get_post( $post_id );
		if ( ! $post || 'page' !== $post->post_type || $post->post_content === $text ) {
			return;
		}
		// Only ever replace content that is empty or is our own earlier text. Anything else (the
		// [twd_page] shortcode, what Elementor saved, words a person typed) is left exactly as it is.
		$ours = get_post_meta( $post_id, self::META_HASH, true );
		if ( '' !== $post->post_content && ( ! is_string( $ours ) || md5( $post->post_content ) !== $ours ) ) {
			return;
		}
		self::$busy = true;
		wp_update_post( array( 'ID' => $post_id, 'post_content' => wp_slash( $text ) ) );
		self::$busy = false;
		update_post_meta( $post_id, self::META_HASH, md5( $text ) );
	}

	/** Images in a kit page, as absolute addresses (for the Yoast image sitemap). */
	public static function image_urls( $html ) {
		$urls = array();
		if ( preg_match_all( '/<img\b[^>]*\bsrc="([^"]+)"/i', (string) $html, $m ) ) {
			foreach ( $m[1] as $src ) {
				$src = html_entity_decode( $src, ENT_QUOTES, 'UTF-8' );
				if ( '/' === substr( $src, 0, 1 ) && '/' !== substr( $src, 0, 2 ) ) {
					$src = rtrim( home_url( '/' ), '/' ) . $src;
				}
				if ( 1 === preg_match( '#^https?://#i', $src ) && ! preg_match( '/your-(image|badge)/i', $src ) ) {
					$urls[ $src ] = true;
				}
			}
		}
		return array_keys( $urls );
	}

	/** Yoast's image sitemap asks for a page's images; add the kit page's pictures. */
	public static function filter_sitemap_images( $images, $post_id ) {
		$images = is_array( $images ) ? $images : array();
		$have   = array();
		foreach ( $images as $img ) {
			if ( is_array( $img ) && isset( $img['src'] ) ) {
				$have[ $img['src'] ] = true;
			}
		}
		foreach ( self::image_urls( TWD_SK_Store::get_current( (int) $post_id ) ) as $url ) {
			if ( ! isset( $have[ $url ] ) ) {
				$images[] = array( 'src' => $url );
			}
		}
		return $images;
	}
}
