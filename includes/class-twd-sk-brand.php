<?php
/**
 * TWD_SK_Brand: colours from the logo (0.8.2).
 *
 * Reads the main colours of a picture in the media library (PNG, JPEG, GIF or WebP through GD, or an
 * SVG through the colours written in it) and proposes the style's colour tokens from them: the
 * button, its hover, the band, the headings, the accent and a light tint. Every proposed pair is put
 * through TWD_SK_Contrast and adjusted (made darker or lighter) until text on it is easy to read; a
 * token that still fails is left out. Nothing is saved here: the Site tab previews the proposal and
 * the person presses Save, or the setup build applies it when asked to.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class TWD_SK_Brand {

	const MAX_FILE   = 8 * 1024 * 1024;
	const MAX_SVG    = 500 * 1000;
	const MAX_COLOURS = 6;

	// -- Colour arithmetic ---------------------------------------------------------------

	public static function rgb( $hex ) {
		$c = TWD_SK_Contrast::parse( $hex );
		return $c ? $c : array( 0, 0, 0 );
	}

	public static function hex( $rgb ) {
		return sprintf( '#%02X%02X%02X', max( 0, min( 255, (int) round( $rgb[0] ) ) ), max( 0, min( 255, (int) round( $rgb[1] ) ) ), max( 0, min( 255, (int) round( $rgb[2] ) ) ) );
	}

	/** Mix two colours: $t = 0 gives $a, 1 gives $b. */
	public static function mix( $a, $b, $t ) {
		return array( $a[0] + ( $b[0] - $a[0] ) * $t, $a[1] + ( $b[1] - $a[1] ) * $t, $a[2] + ( $b[2] - $a[2] ) * $t );
	}

	public static function saturation( $rgb ) {
		$max = max( $rgb ) / 255;
		$min = min( $rgb ) / 255;
		$l   = ( $max + $min ) / 2;
		if ( $max === $min ) {
			return 0.0;
		}
		$d = $max - $min;
		return $l > 0.5 ? $d / ( 2 - $max - $min ) : $d / ( $max + $min );
	}

	private static function ratio( $a, $b ) {
		return (float) TWD_SK_Contrast::ratio( self::hex( $a ), self::hex( $b ) );
	}

	/** Move $c towards black (or white) in small steps until it reaches $min contrast against $against. */
	public static function until_readable( $c, $against, $min, $towards_dark = true ) {
		$target = $towards_dark ? array( 0, 0, 0 ) : array( 255, 255, 255 );
		for ( $i = 0; $i <= 20; $i++ ) {
			$try = self::mix( $c, $target, $i * 0.05 );
			if ( self::ratio( $try, $against ) >= $min ) {
				return $try;
			}
		}
		return null;
	}

	// -- Reading the picture -------------------------------------------------------------

	/**
	 * The main colours of a picture, most important first.
	 *
	 * @return array|WP_Error list of { hex, share } (share of the counted area, 0 to 1).
	 */
	public static function colours_of_attachment( $id ) {
		$id = (int) $id;
		if ( $id <= 0 || ! wp_attachment_is_image( $id ) ) {
			return new WP_Error( 'twd_sk_bad_input', 'Choose a logo picture from the media library first.' );
		}
		$file = (string) get_attached_file( $id );
		if ( '' === $file || ! is_readable( $file ) ) {
			return new WP_Error( 'twd_sk_bad_input', 'The logo file could not be read on the server.' );
		}
		return self::colours_of_file( $file );
	}

	public static function colours_of_file( $file ) {
		$size = (int) @filesize( $file );
		if ( $size <= 0 || $size > self::MAX_FILE ) {
			return new WP_Error( 'twd_sk_bad_input', 'The logo file is empty or too large to read.' );
		}
		if ( 'svg' === strtolower( pathinfo( $file, PATHINFO_EXTENSION ) ) ) {
			return self::colours_of_svg( (string) file_get_contents( $file, false, null, 0, self::MAX_SVG ) );
		}
		if ( ! function_exists( 'imagecreatefromstring' ) ) {
			return new WP_Error( 'twd_sk_no_gd', 'This server cannot read pictures (the GD library is missing), so colours cannot be taken from the logo.' );
		}
		$img = @imagecreatefromstring( (string) file_get_contents( $file ) );
		if ( ! $img ) {
			return new WP_Error( 'twd_sk_bad_input', 'The logo file is not a picture this server can read.' );
		}
		return self::colours_of_image( $img );
	}

	/** Colours from GD image data: shrunk to 64 by 64, transparent and near-white pixels skipped, similar colours grouped. */
	public static function colours_of_image( $img ) {
		$w     = imagesx( $img );
		$h     = imagesy( $img );
		$small = imagecreatetruecolor( 64, 64 );
		imagealphablending( $small, false );
		imagesavealpha( $small, true );
		imagefill( $small, 0, 0, imagecolorallocatealpha( $small, 255, 255, 255, 127 ) );
		imagecopyresampled( $small, $img, 0, 0, 0, 0, 64, 64, $w, $h );
		$buckets = array();
		$total   = 0;
		for ( $y = 0; $y < 64; $y++ ) {
			for ( $x = 0; $x < 64; $x++ ) {
				$c = imagecolorat( $small, $x, $y );
				$a = ( $c >> 24 ) & 0x7F;
				if ( $a > 64 ) {
					continue;
				}
				$rgb = array( ( $c >> 16 ) & 0xFF, ( $c >> 8 ) & 0xFF, $c & 0xFF );
				if ( min( $rgb ) > 235 ) {
					continue;
				}
				$key = ( $rgb[0] >> 5 ) . '-' . ( $rgb[1] >> 5 ) . '-' . ( $rgb[2] >> 5 );
				if ( ! isset( $buckets[ $key ] ) ) {
					$buckets[ $key ] = array( 0, 0, 0, 0 );
				}
				$buckets[ $key ][0] += $rgb[0];
				$buckets[ $key ][1] += $rgb[1];
				$buckets[ $key ][2] += $rgb[2];
				$buckets[ $key ][3]++;
				$total++;
			}
		}
		$list = array();
		foreach ( $buckets as $b ) {
			$list[] = array( 'rgb' => array( $b[0] / $b[3], $b[1] / $b[3], $b[2] / $b[3] ), 'count' => $b[3] );
		}
		return self::rank( $list, $total );
	}

	/** Colours written in an SVG (fill, stroke, stop colours, styles), counted by how often they appear. */
	public static function colours_of_svg( $svg ) {
		if ( ! preg_match_all( '/#([0-9a-fA-F]{6}|[0-9a-fA-F]{3})\b/', (string) $svg, $m ) ) {
			return new WP_Error( 'twd_sk_bad_input', 'No colours were found in the logo file.' );
		}
		$counts = array();
		foreach ( $m[0] as $hex ) {
			$rgb = self::rgb( $hex );
			if ( min( $rgb ) > 235 ) {
				continue;
			}
			$key            = self::hex( $rgb );
			$counts[ $key ] = isset( $counts[ $key ] ) ? $counts[ $key ] + 1 : 1;
		}
		$list  = array();
		$total = 0;
		foreach ( $counts as $hex => $n ) {
			$list[] = array( 'rgb' => self::rgb( $hex ), 'count' => $n );
			$total += $n;
		}
		return self::rank( $list, $total );
	}

	/** Merge near-identical colours, then order by how much there is, with colourful ones a little ahead. */
	private static function rank( $list, $total ) {
		if ( ! $list || $total <= 0 ) {
			return new WP_Error( 'twd_sk_bad_input', 'The logo seems to be all white or see-through, so no colours could be taken from it.' );
		}
		usort( $list, function ( $a, $b ) {
			return $b['count'] - $a['count'];
		} );
		$merged = array();
		foreach ( $list as $c ) {
			foreach ( $merged as $i => $m ) {
				$d = abs( $m['rgb'][0] - $c['rgb'][0] ) + abs( $m['rgb'][1] - $c['rgb'][1] ) + abs( $m['rgb'][2] - $c['rgb'][2] );
				if ( $d < 60 ) {
					$merged[ $i ]['count'] += $c['count'];
					continue 2;
				}
			}
			$merged[] = $c;
		}
		usort( $merged, function ( $a, $b ) {
			$sa = $a['count'] * ( 0.4 + TWD_SK_Brand::saturation( $a['rgb'] ) );
			$sb = $b['count'] * ( 0.4 + TWD_SK_Brand::saturation( $b['rgb'] ) );
			return $sb <=> $sa;
		} );
		$out = array();
		foreach ( array_slice( $merged, 0, self::MAX_COLOURS ) as $c ) {
			$out[] = array( 'hex' => self::hex( $c['rgb'] ), 'share' => round( $c['count'] / $total, 3 ) );
		}
		return $out;
	}

	// -- The proposal ------------------------------------------------------------------

	/**
	 * Colour tokens from a list of colours, on top of a pack's tokens (for the page background, the
	 * cards and the text colours, which stay as the pack has them).
	 *
	 * @param array $colours From colours_of_*().
	 * @param array $base    The pack's tokens (with any saved changes).
	 * @return array { tokens: name => hex, dropped: [labels], colours: [hex] }
	 */
	public static function propose( $colours, $base ) {
		$hexes = array_map( function ( $c ) {
			return $c['hex'];
		}, $colours );
		$rgbs  = array_map( array( __CLASS__, 'rgb' ), $hexes );
		$white = array( 255, 255, 255 );
		$bg    = self::rgb( isset( $base['color-bg'] ) ? $base['color-bg'] : '#FFFFFF' );
		$surf  = self::rgb( isset( $base['color-surface'] ) ? $base['color-surface'] : '#FFFFFF' );
		$min   = TWD_SK_Contrast::MIN;

		// The main colour: the first colourful one, else the first one at all (a black or grey logo).
		$main = $rgbs[0];
		foreach ( $rgbs as $c ) {
			if ( self::saturation( $c ) > 0.15 ) {
				$main = $c;
				break;
			}
		}
		$second = $main;
		foreach ( $rgbs as $c ) {
			if ( self::hex( $c ) !== self::hex( $main ) ) {
				$second = $c;
				break;
			}
		}

		$t = array();
		// Buttons take white text: the main colour is darkened only as far as it needs to be.
		$primary = self::until_readable( $main, $white, $min + 0.1 );
		if ( $primary ) {
			$t['color-primary']       = $primary;
			$t['color-on-primary']    = $white;
			$hover                    = self::mix( $primary, array( 0, 0, 0 ), 0.15 );
			$t['color-primary-hover'] = $hover;
			$band                     = self::mix( $primary, array( 0, 0, 0 ), 0.2 );
			$t['color-band']          = self::until_readable( $band, $white, $min + 0.1 );
			$t['color-on-band']       = $white;
			$t['color-accent-on-dark'] = $t['color-band'] ? self::until_readable( self::mix( $second, $white, 0.5 ), $t['color-band'], $min + 0.1, false ) : null;
		}
		// Headings: the main colour, darkened until it reads on both the page and the cards.
		$heading = self::until_readable( $main, $bg, $min + 0.1 );
		if ( $heading && self::ratio( $heading, $surf ) < $min ) {
			$heading = self::until_readable( $heading, $surf, $min + 0.1 );
		}
		$t['color-heading'] = $heading;
		// The accent is decoration; its text form must read on the page.
		$t['color-accent']      = $second;
		$t['color-accent-text'] = self::until_readable( $second, $bg, $min + 0.1 );
		$t['color-eyebrow']     = $t['color-accent-text'];
		// A light tint of the main colour for tinted sections.
		$t['color-tint'] = self::mix( $main, $white, 0.82 );

		$tokens  = array();
		$dropped = array();
		$labels  = array();
		foreach ( TWD_SK_Site::editable() as $e ) {
			$labels[ $e[1] ] = $e[2];
		}
		foreach ( $t as $name => $rgb ) {
			if ( null === $rgb ) {
				$dropped[] = isset( $labels[ $name ] ) ? $labels[ $name ] : $name;
				continue;
			}
			$tokens[ $name ] = self::hex( $rgb );
		}
		// The final word is the same check the Site tab saves with: drop anything that still blocks.
		for ( $i = 0; $i < 5; $i++ ) {
			$check = TWD_SK_Contrast::check( array_merge( $base, $tokens ) );
			if ( ! $check['blocking'] ) {
				break;
			}
			foreach ( $check['blocking'] as $b ) {
				foreach ( TWD_SK_Contrast::pairs() as $p ) {
					if ( $p[0] === $b['id'] ) {
						foreach ( array( $p[3], $p[4] ) as $name ) {
							if ( isset( $tokens[ $name ] ) ) {
								unset( $tokens[ $name ] );
								$dropped[] = isset( $labels[ $name ] ) ? $labels[ $name ] : $name;
							}
						}
					}
				}
			}
		}
		return array( 'tokens' => $tokens, 'dropped' => array_values( array_unique( $dropped ) ), 'colours' => $hexes );
	}

	/** The whole thing for a picture: its colours and the proposed tokens on top of the active style. */
	public static function from_attachment( $id ) {
		$colours = self::colours_of_attachment( $id );
		if ( is_wp_error( $colours ) ) {
			return $colours;
		}
		return self::propose( $colours, TWD_SK_Packs::active_tokens() );
	}
}
