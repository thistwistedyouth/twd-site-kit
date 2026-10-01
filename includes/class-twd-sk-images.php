<?php
/**
 * TWD_SK_Images: image attributes, added when a page is displayed (never stored).
 *
 * - Width and height, when the picture is in the media library and the page does not give
 *   them, so the browser can set aside the space before the picture arrives.
 * - The first picture in the page's first section (the hero) is loaded at once, and marked high
 *   priority. It is never lazy.
 * - Every other picture is lazy and decoded off the main thread.
 *
 * Stored page HTML stays clean (the sanitiser removes loading, srcset, sizes and fetchpriority
 * on the way in), so nobody can set these by hand and the rule is the same for every page.
 * Pictures printed by other plugins (the articles grid) are not touched.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class TWD_SK_Images {

	const DIM_TTL = 43200; // Twelve hours.

	/**
	 * @param string $html Page HTML, before any shortcode has run.
	 * @return string
	 */
	public static function process( $html ) {
		if ( ! is_string( $html ) || false === stripos( $html, '<img' ) ) {
			return $html;
		}
		$first_end = stripos( $html, '</section>' );
		$first_end = false === $first_end ? strlen( $html ) : $first_end;

		$index = 0;
		return preg_replace_callback(
			'/<img\b[^>]*>/i',
			function ( $m ) use ( &$index, $first_end, $html ) {
				$tag   = $m[0];
				$above = 0 === $index && strpos( $html, $tag ) < $first_end;
				$index++;
				return self::tag( $tag, $above );
			},
			$html
		);
	}

	/** One <img> tag with the attributes it is missing. */
	public static function tag( $tag, $above_the_fold ) {
		$add = '';
		if ( ! preg_match( '/\sloading\s*=/i', $tag ) ) {
			$add .= $above_the_fold ? ' loading="eager"' : ' loading="lazy"';
		}
		if ( $above_the_fold && ! preg_match( '/\sfetchpriority\s*=/i', $tag ) ) {
			$add .= ' fetchpriority="high"';
		}
		if ( ! preg_match( '/\sdecoding\s*=/i', $tag ) ) {
			$add .= ' decoding="async"';
		}
		if ( ! preg_match( '/\swidth\s*=/i', $tag ) && ! preg_match( '/\sheight\s*=/i', $tag ) && preg_match( '/\ssrc="([^"]+)"/i', $tag, $src ) ) {
			$dim = self::dimensions( html_entity_decode( $src[1], ENT_QUOTES, 'UTF-8' ) );
			if ( $dim ) {
				$add .= ' width="' . (int) $dim[0] . '" height="' . (int) $dim[1] . '"';
			}
		}
		if ( '' === $add ) {
			return $tag;
		}
		return preg_replace( '/\s*\/?>$/', $add . '$0', $tag, 1 );
	}

	/**
	 * Width and height of a picture in this site's media library, or null.
	 * The answer (including "not in the library") is remembered for twelve hours.
	 */
	public static function dimensions( $src ) {
		if ( ! is_string( $src ) || '' === $src || preg_match( '/\.svg(\?|$)/i', $src ) ) {
			return null;
		}
		$url = $src;
		if ( '/' === substr( $url, 0, 1 ) && '/' !== substr( $url, 0, 2 ) ) {
			$url = rtrim( home_url( '/' ), '/' ) . $url;
		}
		if ( 0 !== strpos( $url, rtrim( home_url( '/' ), '/' ) . '/' ) ) {
			return null; // Only this site's own pictures.
		}
		$key    = 'twd_sk_dim_' . md5( $url );
		$cached = get_transient( $key );
		if ( is_array( $cached ) ) {
			return $cached ? $cached : null;
		}
		$found = array();
		$id    = function_exists( 'attachment_url_to_postid' ) ? (int) attachment_url_to_postid( $url ) : 0;
		if ( $id > 0 && function_exists( 'wp_get_attachment_metadata' ) ) {
			$meta = wp_get_attachment_metadata( $id );
			if ( is_array( $meta ) && ! empty( $meta['width'] ) && ! empty( $meta['height'] ) ) {
				$found = array( (int) $meta['width'], (int) $meta['height'] );
				// A resized copy: use that size's own dimensions.
				$file = basename( (string) parse_url( $url, PHP_URL_PATH ) );
				if ( ! empty( $meta['sizes'] ) && is_array( $meta['sizes'] ) ) {
					foreach ( $meta['sizes'] as $size ) {
						if ( isset( $size['file'], $size['width'], $size['height'] ) && $size['file'] === $file ) {
							$found = array( (int) $size['width'], (int) $size['height'] );
						}
					}
				}
			}
		}
		set_transient( $key, $found, $found ? self::DIM_TTL : 3600 );
		return $found ? $found : null;
	}
}
