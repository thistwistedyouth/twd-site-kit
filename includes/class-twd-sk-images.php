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

	const DIM_TTL    = 43200; // Twelve hours.
	const META_FOCAL = '_twd_sk_focal';
	const VER_OPTION = 'twd_sk_img_ver';

	/** The "keep in view" choices (0.8.3): key => label and the object-position it gives. */
	public static function focal_points() {
		return array(
			'center'       => array( 'Centre', '' ),
			'top'          => array( 'Top', '50% 15%' ),
			'bottom'       => array( 'Bottom', '50% 85%' ),
			'left'         => array( 'Left', '15% 50%' ),
			'right'        => array( 'Right', '85% 50%' ),
			'top-left'     => array( 'Top left', '15% 15%' ),
			'top-right'    => array( 'Top right', '85% 15%' ),
			'bottom-left'  => array( 'Bottom left', '15% 85%' ),
			'bottom-right' => array( 'Bottom right', '85% 85%' ),
		);
	}

	/** The media library's edit screen gets a "Keep in view" choice for each picture. */
	public static function init() {
		add_filter( 'attachment_fields_to_edit', array( __CLASS__, 'focal_field' ), 10, 2 );
		add_filter( 'attachment_fields_to_save', array( __CLASS__, 'save_focal_field' ), 10, 2 );
	}

	/** A free point (0.15.0): "X% Y%" with whole numbers from 0 to 100, as well as the named places. */
	public static function is_free_point( $v ) {
		return is_string( $v ) && 1 === preg_match( '/^(100|[1-9]?\d)% (100|[1-9]?\d)%$/', $v );
	}

	/** The object-position a stored choice gives ('' for the centre). */
	public static function focal_position( $key ) {
		if ( self::is_free_point( $key ) ) {
			return $key;
		}
		return isset( self::focal_points()[ $key ] ) ? self::focal_points()[ $key ][1] : '';
	}

	public static function get_focal( $id ) {
		$v = get_post_meta( (int) $id, self::META_FOCAL, true );
		return is_string( $v ) && ( isset( self::focal_points()[ $v ] ) || self::is_free_point( $v ) ) ? $v : 'center';
	}

	/** @return bool|WP_Error */
	public static function set_focal( $id, $key ) {
		if ( ! is_string( $key ) || ( ! isset( self::focal_points()[ $key ] ) && ! self::is_free_point( $key ) ) ) {
			return new WP_Error( 'twd_sk_bad_input', 'Choose one of the places to keep in view, or an exact point as two percentages.' );
		}
		update_post_meta( (int) $id, self::META_FOCAL, $key );
		// Pages read the choice through a cache; a new version number makes them read it again.
		update_option( self::VER_OPTION, (int) get_option( self::VER_OPTION, 0 ) + 1 );
		return true;
	}

	public static function focal_field( $fields, $post ) {
		if ( ! $post || ! wp_attachment_is_image( $post->ID ) ) {
			return $fields;
		}
		$current = self::get_focal( $post->ID );
		$free    = self::is_free_point( $current );
		$name    = 'attachments[' . (int) $post->ID . '][twd_sk_focal]';
		$html    = '<select name="' . esc_attr( $name ) . '" id="' . esc_attr( 'attachments-' . (int) $post->ID . '-twd_sk_focal' ) . '">';
		foreach ( self::focal_points() as $key => $p ) {
			$html .= '<option value="' . esc_attr( $key ) . '"' . ( $key === $current ? ' selected' : '' ) . '>' . esc_html( $p[0] ) . '</option>';
		}
		$html .= '<option value="free"' . ( $free ? ' selected' : '' ) . '>Exact point (percentages below)</option>';
		$html .= '</select>';
		// 0.15.0: an exact point, as two percentages from the left and from the top.
		$xy = $free ? explode( ' ', str_replace( '%', '', $current ) ) : array( '50', '50' );
		$html .= ' <label>From the left <input type="number" min="0" max="100" step="1" size="3" name="' . esc_attr( 'attachments[' . (int) $post->ID . '][twd_sk_focal_x]' ) . '" value="' . esc_attr( $xy[0] ) . '">%</label>';
		$html .= ' <label>From the top <input type="number" min="0" max="100" step="1" size="3" name="' . esc_attr( 'attachments[' . (int) $post->ID . '][twd_sk_focal_y]' ) . '" value="' . esc_attr( $xy[1] ) . '">%</label>';
		$fields['twd_sk_focal'] = array(
			'label' => 'Keep in view',
			'input' => 'html',
			'html'  => $html,
			'helps' => 'When a kit page crops this picture, this part stays in view (for example Top for a face). Choose Exact point to give the place as percentages.',
		);
		return $fields;
	}

	public static function save_focal_field( $post, $attachment ) {
		if ( isset( $attachment['twd_sk_focal'] ) && isset( $post['ID'] ) && current_user_can( 'edit_post', (int) $post['ID'] ) ) {
			$key = (string) $attachment['twd_sk_focal'];
			if ( 'free' === $key ) {
				$x   = isset( $attachment['twd_sk_focal_x'] ) ? (int) $attachment['twd_sk_focal_x'] : 50;
				$y   = isset( $attachment['twd_sk_focal_y'] ) ? (int) $attachment['twd_sk_focal_y'] : 50;
				$key = max( 0, min( 100, $x ) ) . '% ' . max( 0, min( 100, $y ) ) . '%';
			}
			self::set_focal( (int) $post['ID'], $key );
		}
		return $post;
	}

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
		$info = preg_match( '/\ssrc="([^"]+)"/i', $tag, $src ) ? self::info( html_entity_decode( $src[1], ENT_QUOTES, 'UTF-8' ) ) : null;
		if ( $info && $info['dim'] && ! preg_match( '/\swidth\s*=/i', $tag ) && ! preg_match( '/\sheight\s*=/i', $tag ) ) {
			$add .= ' width="' . (int) $info['dim'][0] . '" height="' . (int) $info['dim'][1] . '"';
		}
		// The "keep in view" choice, added at display time only (stored HTML never holds a style).
		if ( $info && '' !== $info['focal'] && ! preg_match( '/\sstyle\s*=/i', $tag ) ) {
			$add .= ' style="object-position: ' . $info['focal'] . '"';
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
		$info = self::info( $src );
		return $info ? $info['dim'] : null;
	}

	/**
	 * What the media library knows about one of this site's pictures: its size (null for SVG or
	 * when not known) and its "keep in view" position ('' for the centre). Null when it is not a
	 * picture in this site's library. Remembered for twelve hours (one hour for "not found").
	 */
	public static function info( $src ) {
		if ( ! is_string( $src ) || '' === $src ) {
			return null;
		}
		$url = $src;
		if ( '/' === substr( $url, 0, 1 ) && '/' !== substr( $url, 0, 2 ) ) {
			$url = rtrim( home_url( '/' ), '/' ) . $url;
		}
		if ( 0 !== strpos( $url, rtrim( home_url( '/' ), '/' ) . '/' ) ) {
			return null; // Only this site's own pictures.
		}
		$key    = 'twd_sk_img_' . md5( $url . '|' . (int) get_option( self::VER_OPTION, 0 ) );
		$cached = get_transient( $key );
		if ( is_array( $cached ) && array_key_exists( 'dim', $cached ) ) {
			return $cached['id'] ? $cached : null;
		}
		$found = array();
		$id    = function_exists( 'attachment_url_to_postid' ) ? (int) attachment_url_to_postid( $url ) : 0;
		$svg   = 1 === preg_match( '/\.svg(\?|$)/i', $url );
		if ( $id > 0 && ! $svg && function_exists( 'wp_get_attachment_metadata' ) ) {
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
		$focal = $id > 0 ? self::focal_position( self::get_focal( $id ) ) : '';
		$info  = array( 'id' => $id, 'dim' => $found ? $found : null, 'focal' => $focal );
		set_transient( $key, $info, $id ? self::DIM_TTL : 3600 );
		return $id ? $info : null;
	}
}
