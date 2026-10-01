<?php
/**
 * TWD_SK_Profile: the site profile, the facts the header, footer and structured data are built from.
 *
 * Stored in one option (twd_sk_profile). Every field is validated and cleaned before it is
 * stored: plain text with length limits, addresses checked by TWD_SK_Sanitizer::safe_url(),
 * an email checked as an email, a logo that must be an image in the media library.
 *
 * Nothing here is ever invented. A field the client has not supplied stays empty (or holds a
 * visible [PLACEHOLDER: ...] marker in a starter), and the leftover check lists the markers.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class TWD_SK_Profile {

	const OPTION = 'twd_sk_profile';

	const MAX_MENU     = 8;
	const MAX_CHILDREN = 6;
	const MAX_LEGAL    = 6;
	const MAX_REG      = 4;
	const MAX_SAMEAS   = 4;
	const MAX_ADDRESS  = 3;

	/** An empty profile: every field present, nothing filled in. */
	public static function defaults() {
		return array(
			'site_name'    => '',
			'logo_id'      => 0,
			'menu'         => array(),
			'cta_label'    => '',
			'cta_url'      => '',
			'phone'        => '',
			'email'        => '',
			'address'      => array(),
			'area_served'  => '',
			'footer_text'  => '',
			'legal'        => array(),
			'registration' => array(),
			'person_name'  => '',
			'person_job'   => '',
			'same_as'      => array(),
			'about_page_id' => 0,
		);
	}

	/** The stored profile, always well formed. */
	public static function get() {
		$stored = function_exists( 'get_option' ) ? get_option( self::OPTION, array() ) : array();
		$out    = self::defaults();
		if ( ! is_array( $stored ) ) {
			return $out;
		}
		// Stored values were validated on the way in; run them through again so a hand-edited option is safe too.
		$check = self::validate( $stored, true );
		return array_merge( $out, $check['valid'] );
	}

	public static function is_empty() {
		$p = self::get();
		return '' === $p['site_name'] && ! $p['menu'] && '' === $p['phone'] && '' === $p['email'] && ! $p['logo_id'];
	}

	/**
	 * Clean text: tags gone, whitespace collapsed, long dashes replaced, cut to a length.
	 * Returns '' for anything that is not a string.
	 */
	public static function text( $value, $max ) {
		if ( ! is_string( $value ) ) {
			return '';
		}
		$value = trim( preg_replace( '/\s+/u', ' ', wp_strip_all_tags( $value ) ) );
		$value = TWD_SK_Sanitizer::strip_dashes( $value );
		$value = preg_replace( '/[\x00-\x1F\x7F]/', '', $value );
		if ( function_exists( 'mb_substr' ) ) {
			return mb_substr( $value, 0, $max );
		}
		return substr( $value, 0, $max );
	}

	private static function url( $value ) {
		if ( ! is_string( $value ) || '' === trim( $value ) ) {
			return '';
		}
		$ok = TWD_SK_Sanitizer::safe_url( trim( $value ), array( 'http', 'https', 'mailto', 'tel' ) );
		return false === $ok ? false : (string) $ok;
	}

	/**
	 * Validate a profile (or part of one).
	 *
	 * @param array $input
	 * @param bool  $quiet Drop what is invalid instead of reporting it.
	 * @return array { valid: field => cleaned value, errors: list of plain sentences }
	 */
	public static function validate( $input, $quiet = false ) {
		$out = array( 'valid' => array(), 'errors' => array() );
		if ( ! is_array( $input ) ) {
			$out['errors'][] = 'The profile must be a set of fields.';
			return $out;
		}
		$known = self::defaults();
		foreach ( $input as $key => $value ) {
			if ( ! is_string( $key ) || ! array_key_exists( $key, $known ) ) {
				if ( ! $quiet ) {
					$out['errors'][] = 'Unknown field: ' . ( is_string( $key ) ? $key : '?' ) . '.';
				}
				continue;
			}
			$result = self::validate_field( $key, $value );
			if ( is_string( $result ) ) {
				if ( ! $quiet ) {
					$out['errors'][] = $result;
				}
				continue;
			}
			$out['valid'][ $key ] = $result[0];
		}
		return $out;
	}

	/** One field: array( cleaned ) on success, or a plain sentence on failure. */
	private static function validate_field( $key, $value ) {
		switch ( $key ) {
			case 'site_name':
				return is_string( $value ) ? array( self::text( $value, 80 ) ) : 'The site name must be text.';
			case 'footer_text':
				return is_string( $value ) ? array( self::text( $value, 300 ) ) : 'The footer text must be text.';
			case 'phone':
				return is_string( $value ) ? array( self::text( $value, 40 ) ) : 'The phone number must be text.';
			case 'area_served':
				return is_string( $value ) ? array( self::text( $value, 120 ) ) : 'Where you work must be text.';
			case 'person_name':
			case 'person_job':
				return is_string( $value ) ? array( self::text( $value, 80 ) ) : 'That field must be text.';
			case 'cta_label':
				return is_string( $value ) ? array( self::text( $value, 30 ) ) : 'The button text must be text.';
			case 'email':
				if ( ! is_string( $value ) ) {
					return 'The email address must be text.';
				}
				$value = trim( $value );
				if ( '' === $value ) {
					return array( '' );
				}
				return ( strlen( $value ) <= 100 && false !== filter_var( $value, FILTER_VALIDATE_EMAIL ) ) ? array( $value ) : 'That email address does not look right.';
			case 'cta_url':
				$u = self::url( $value );
				return false === $u ? 'The button link is not allowed. Use a page on this site, #anchor, https, mailto or tel.' : array( $u );
			case 'logo_id':
			case 'about_page_id':
				if ( ! is_int( $value ) && ! ( is_string( $value ) && ctype_digit( $value ) ) ) {
					return 'logo_id' === $key ? 'The logo must be the number of a picture in the media library.' : 'That must be a page number.';
				}
				if ( (int) $value < 0 ) {
					return 'logo_id' === $key ? 'The logo must be the number of a picture in the media library.' : 'That must be a page number.';
				}
				$value = (int) $value;
				if ( 'logo_id' === $key && $value > 0 && ! self::is_image( $value ) ) {
					return 'That logo number is not a picture in the media library.';
				}
				return array( max( 0, $value ) );
			case 'menu':
				return self::validate_menu( $value );
			case 'legal':
				return self::validate_links( $value, self::MAX_LEGAL, 'legal links' );
			case 'registration':
				return self::validate_lines( $value, self::MAX_REG, 160, 'registration lines' );
			case 'address':
				return self::validate_lines( $value, self::MAX_ADDRESS, 80, 'address lines' );
			case 'same_as':
				return self::validate_same_as( $value );
		}
		return 'Unknown field.';
	}

	private static function is_image( $id ) {
		return function_exists( 'wp_attachment_is_image' ) && wp_attachment_is_image( $id );
	}

	private static function validate_lines( $value, $max, $len, $what ) {
		if ( ! is_array( $value ) ) {
			return 'The ' . $what . ' must be a list.';
		}
		if ( count( $value ) > $max ) {
			return 'Use at most ' . $max . ' ' . $what . '.';
		}
		$out = array();
		foreach ( $value as $line ) {
			if ( ! is_string( $line ) ) {
				return 'Each of the ' . $what . ' must be text.';
			}
			$line = self::text( $line, $len );
			if ( '' !== $line ) {
				$out[] = $line;
			}
		}
		return array( $out );
	}

	private static function validate_links( $value, $max, $what ) {
		if ( ! is_array( $value ) ) {
			return 'The ' . $what . ' must be a list.';
		}
		if ( count( $value ) > $max ) {
			return 'Use at most ' . $max . ' ' . $what . '.';
		}
		$out = array();
		foreach ( $value as $item ) {
			if ( ! is_array( $item ) || ! isset( $item['label'], $item['url'] ) || ! is_string( $item['label'] ) || ! is_string( $item['url'] ) ) {
				return 'Each of the ' . $what . ' needs a label and a link.';
			}
			$label = self::text( $item['label'], 40 );
			$url   = self::url( $item['url'] );
			if ( '' === $label && '' === trim( $item['url'] ) ) {
				continue;
			}
			if ( '' === $label || '' === $url || false === $url ) {
				return 'The link "' . $label . '" in the ' . $what . ' is missing a label or has a link that is not allowed.';
			}
			$out[] = array( 'label' => $label, 'url' => $url );
		}
		return array( $out );
	}

	private static function validate_menu( $value ) {
		if ( ! is_array( $value ) ) {
			return 'The menu must be a list.';
		}
		if ( count( $value ) > self::MAX_MENU ) {
			return 'Use at most ' . self::MAX_MENU . ' menu items.';
		}
		$out = array();
		foreach ( $value as $item ) {
			if ( ! is_array( $item ) || ! isset( $item['label'], $item['url'] ) || ! is_string( $item['label'] ) || ! is_string( $item['url'] ) ) {
				return 'Each menu item needs a label and a link.';
			}
			$label = self::text( $item['label'], 40 );
			$url   = self::url( $item['url'] );
			if ( '' === $label && '' === trim( $item['url'] ) && empty( $item['children'] ) ) {
				continue;
			}
			if ( '' === $label || '' === $url || false === $url ) {
				return 'The menu item "' . $label . '" is missing a label or has a link that is not allowed.';
			}
			$children = array();
			if ( isset( $item['children'] ) ) {
				$kids = self::validate_links( $item['children'], self::MAX_CHILDREN, 'sub-menu links' );
				if ( is_string( $kids ) ) {
					return $kids;
				}
				$children = $kids[0];
			}
			$out[] = array( 'label' => $label, 'url' => $url, 'children' => $children );
		}
		return array( $out );
	}

	private static function validate_same_as( $value ) {
		if ( ! is_array( $value ) ) {
			return 'The profile links must be a list.';
		}
		if ( count( $value ) > self::MAX_SAMEAS ) {
			return 'Use at most ' . self::MAX_SAMEAS . ' profile links.';
		}
		$out = array();
		foreach ( $value as $u ) {
			if ( ! is_string( $u ) ) {
				return 'Each profile link must be a web address.';
			}
			if ( '' === trim( $u ) ) {
				continue;
			}
			$clean = self::url( $u );
			if ( false === $clean || 1 !== preg_match( '#^https://#i', $clean ) ) {
				return 'Profile links must be full https:// addresses.';
			}
			$out[] = $clean;
		}
		return array( $out );
	}

	/**
	 * Store a validated profile (a full set or a part of one merged into the stored one).
	 *
	 * @return array|WP_Error The stored profile.
	 */
	public static function save( $input ) {
		$check = self::validate( $input, false );
		if ( $check['errors'] ) {
			return new WP_Error( 'twd_sk_bad_profile', implode( ' ', $check['errors'] ) );
		}
		$merged = array_merge( self::get(), $check['valid'] );
		update_option( self::OPTION, $merged );
		return $merged;
	}

	// -- Checks ---------------------------------------------------------

	/** Every text and address in a profile, one string, so the leftover check can read it. */
	public static function flatten( $profile ) {
		$parts = array();
		$walk  = function ( $v ) use ( &$walk, &$parts ) {
			if ( is_array( $v ) ) {
				foreach ( $v as $x ) {
					$walk( $x );
				}
			} elseif ( is_string( $v ) && '' !== $v ) {
				$parts[] = $v;
			}
		};
		$walk( $profile );
		return implode( "\n", $parts );
	}

	/** Leftover example text in the profile, split by level like a page's. */
	public static function leftovers( $profile = null ) {
		$profile = null === $profile ? self::get() : $profile;
		return TWD_SK_Sanitizer::find_leftovers_by_level( self::flatten( $profile ) );
	}

	/** Facts a safe, real header and footer need. Returns a list of plain sentences about what is missing. */
	public static function missing( $profile = null ) {
		$p       = null === $profile ? self::get() : $profile;
		$missing = array();
		if ( '' === $p['site_name'] ) {
			$missing[] = 'The site name is empty, so the header shows the WordPress site title.';
		}
		if ( ! $p['menu'] ) {
			$missing[] = 'There are no menu items.';
		}
		if ( '' === $p['phone'] && '' === $p['email'] ) {
			$missing[] = 'There is no phone number or email address.';
		}
		return $missing;
	}

	/**
	 * A starter profile: every field a placeholder, so nothing is invented. The leftover check flags all of it.
	 */
	public static function starter() {
		return array(
			'site_name'    => '[PLACEHOLDER: practice name]',
			'logo_id'      => 0,
			'menu'         => array(
				array( 'label' => 'Home', 'url' => '/', 'children' => array() ),
				array( 'label' => 'About', 'url' => '/about', 'children' => array() ),
				array( 'label' => 'Contact', 'url' => '/contact', 'children' => array() ),
			),
			'cta_label'    => 'Get in touch',
			'cta_url'      => '/contact',
			'phone'        => 'PHONE_NUMBER',
			'email'        => 'you@example.com',
			'address'      => array( '[PLACEHOLDER: address line, or delete for an online practice]' ),
			'area_served'  => '[PLACEHOLDER: where you work, or Online]',
			'footer_text'  => '[PLACEHOLDER: one or two sentences about your practice]',
			'legal'        => array(
				array( 'label' => '[PLACEHOLDER: privacy policy]', 'url' => '/privacy-policy' ),
			),
			'registration' => array( '[PLACEHOLDER: registration or membership line, supplied by the therapist]' ),
			'person_name'  => '[PLACEHOLDER: therapist name]',
			'person_job'   => '[PLACEHOLDER: job title]',
			'same_as'      => array(),
			'about_page_id' => 0,
		);
	}
}
