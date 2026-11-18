<?php
/**
 * TWD_SK_Structure (0.14.1): add a section to a page and move a section up or down, on the page itself, with
 * no AI and no copy and paste.
 *
 * Each action returns the page as it would be, and the REST route saves it through TWD_SK_Store (so the cleaner
 * always runs, the change is a version, and Undo or the history puts it back). Nothing here touches the database.
 *
 *  - Add: the chosen kit section as its outline, its words marked [PLACEHOLDER] for the person to fill in with
 *    the Visual editor (the publish check holds a page back until they do). It uses the same engine as Add a
 *    section in the pop-up (TWD_SK_AI::add_section with no AI). A banner (hero) is not offered: a page has one
 *    main heading, in its first hero.
 *  - Move: a section swaps places with its neighbour. The section holding the page's main heading (the h1) keeps
 *    its place, and nothing may move into it, so the heading never leaves the top.
 *
 * A section is counted the way TWD_SK_Sections counts: top-level <section> parts, from 0, in page order.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class TWD_SK_Structure {

	/**
	 * The page with one new section after section $in['after'].
	 *
	 * @param int   $page_id
	 * @param array $in after (index), component, variant
	 * @return array|WP_Error { html, at, note } where at is the new section's number
	 */
	public static function add( $page_id, $in ) {
		$in        = is_array( $in ) ? $in : array();
		$component = isset( $in['component'] ) && is_string( $in['component'] ) ? $in['component'] : '';
		$variant   = isset( $in['variant'] ) && is_string( $in['variant'] ) ? $in['variant'] : '';
		if ( 'hero' === $component ) {
			return new WP_Error( 'twd_sk_bad_input', 'A page has one main banner, at the top. Choose another kind of section.' );
		}
		if ( 'embed' === $component ) {
			return new WP_Error( 'twd_sk_bad_input', 'An embed is added from the Site tab (Embeds, Add to this page), because it needs one of the saved embeds. Choose another kind of section here.' );
		}
		$html  = TWD_SK_Store::get_current( (int) $page_id );
		$count = count( TWD_SK_Sections::split( $html )['sections'] );
		if ( $count >= TWD_SK_Sections::MAX_SECTIONS ) {
			return new WP_Error( 'twd_sk_bad_input', 'A page can have up to ' . TWD_SK_Sections::MAX_SECTIONS . ' sections.' );
		}
		$result = TWD_SK_AI::add_section( (int) $page_id, array(
			'html'      => $html,
			'after'     => isset( $in['after'] ) ? $in['after'] : null,
			'component' => $component,
			'variant'   => $variant,
			'use_ai'    => false,
		) );
		if ( is_wp_error( $result ) ) {
			return $result;
		}
		$c = TWD_SK_Registry::get( $component );
		return array(
			'html' => $result['html'],
			'at'   => isset( $result['changed'][0] ) ? (int) $result['changed'][0] : 0,
			'note' => 'Added a ' . ( $c ? strtolower( $c['label'] ) : 'new' ) . ' section',
		);
	}

	/**
	 * The page with a new paragraph after text piece $in['piece'] (op add_text) or a copy of item $in['item'] (op duplicate).
	 *
	 * @return array|WP_Error { html, at, piece, note }
	 */
	public static function inside( $page_id, $op, $in ) {
		$in   = is_array( $in ) ? $in : array();
		$html = TWD_SK_Store::get_current( (int) $page_id );
		if ( 'add_text' === $op ) {
			return TWD_SK_Inline::add_text( $html, isset( $in['piece'] ) ? $in['piece'] : null );
		}
		return TWD_SK_Inline::duplicate( $html, isset( $in['item'] ) ? $in['item'] : null, isset( $in['piece'] ) ? $in['piece'] : null, isset( $in['level'] ) && 'text' === $in['level'] ? 'text' : 'box' );
	}

	/**
	 * The page with section $in['index'] moved one place, $in['dir'] being up or down.
	 *
	 * @param int   $page_id
	 * @param array $in index, dir
	 * @return array|WP_Error { html, at, note } where at is the section's new number
	 */
	public static function move( $page_id, $in ) {
		$in    = is_array( $in ) ? $in : array();
		$parts = TWD_SK_Sections::split( TWD_SK_Store::get_current( (int) $page_id ) );
		$n     = count( $parts['sections'] );
		$index = isset( $in['index'] ) && ( is_int( $in['index'] ) || ( is_string( $in['index'] ) && ctype_digit( $in['index'] ) ) ) ? (int) $in['index'] : -1;
		$dir   = isset( $in['dir'] ) && is_string( $in['dir'] ) ? $in['dir'] : '';
		if ( $index < 0 || $index >= $n || ! in_array( $dir, array( 'up', 'down' ), true ) ) {
			return new WP_Error( 'twd_sk_bad_input', 'Click inside the section you want to move first.' );
		}
		$to = 'up' === $dir ? $index - 1 : $index + 1;
		if ( $to < 0 ) {
			return new WP_Error( 'twd_sk_bad_input', 'That section is already first.' );
		}
		if ( $to >= $n ) {
			return new WP_Error( 'twd_sk_bad_input', 'That section is already last.' );
		}
		// The section with the main heading never changes place, and nothing moves into its place.
		$h1 = -1;
		foreach ( $parts['sections'] as $i => $section ) {
			if ( false !== stripos( $section, '<h1' ) ) {
				$h1 = $i;
				break;
			}
		}
		if ( $h1 >= 0 && ( $index === $h1 || $to === $h1 ) ) {
			return new WP_Error( 'twd_sk_bad_input', 'The section with the main heading stays at the top of the page.' );
		}
		$type     = TWD_SK_Sections::type_of( $parts['sections'][ $index ] );
		$sections = $parts['sections'];
		$swap     = $sections[ $to ];
		$sections[ $to ]    = $sections[ $index ];
		$sections[ $index ] = $swap;
		return array(
			'html' => TWD_SK_Sections::join( $parts['gaps'], $sections ),
			'at'   => $to,
			'note' => 'Moved a ' . str_replace( '-', ' ', $type ) . ' section ' . $dir,
		);
	}

	/**
	 * A custom section that holds only a picture, turned into the kit's picture band (12 Nov 2026), which has Band height, Picture
	 * position and Slow zoom in the Look panel (a custom section has no Look choices: its look belongs to the site's own stylesheet).
	 * The picture and its description are kept; a resized media library address goes back to the full size, so a wide screen gets
	 * a sharp copy. Anything else is refused with the reason, and nothing changes.
	 *
	 * @param int   $page_id
	 * @param array $in index (the section's number)
	 * @return array|WP_Error { html, at, note }
	 */
	public static function to_band( $page_id, $in ) {
		$in    = is_array( $in ) ? $in : array();
		$parts = TWD_SK_Sections::split( TWD_SK_Store::get_current( (int) $page_id ) );
		$n     = count( $parts['sections'] );
		$index = isset( $in['index'] ) && ( is_int( $in['index'] ) || ( is_string( $in['index'] ) && ctype_digit( $in['index'] ) ) ) ? (int) $in['index'] : -1;
		if ( $index < 0 || $index >= $n ) {
			return new WP_Error( 'twd_sk_bad_input', 'Click inside the section you want to change first.' );
		}
		$band = self::picture_band_from( $parts['sections'][ $index ] );
		if ( is_wp_error( $band ) ) {
			return $band;
		}
		$sections           = $parts['sections'];
		$sections[ $index ] = $band;
		return array(
			'html' => TWD_SK_Sections::join( $parts['gaps'], $sections ),
			'at'   => $index,
			'note' => 'Turned a custom section into a picture band',
		);
	}

	/**
	 * A second picture for phones in a picture band (12 Nov 2026, from the pilot site: a photo with small lettering in it was fine
	 * on a desktop and too small to read on a phone). `picture` (a media library number) adds or replaces it, `remove` takes it
	 * away. The band keeps its main picture and gets a sibling picture with the class twd-sk-phone-img, and the section the class
	 * twd-sk-has-phone; the stylesheet shows only one of the two (phones the second, everyone else the first), so the other is
	 * never downloaded. Nothing else in the page changes.
	 *
	 * @param int   $page_id
	 * @param array $in index (the section's number), then picture (a media library number) or remove (true)
	 * @return array|WP_Error { html, at, note }
	 */
	public static function phone_picture( $page_id, $in ) {
		$in    = is_array( $in ) ? $in : array();
		$parts = TWD_SK_Sections::split( TWD_SK_Store::get_current( (int) $page_id ) );
		$n     = count( $parts['sections'] );
		$index = isset( $in['index'] ) && ( is_int( $in['index'] ) || ( is_string( $in['index'] ) && ctype_digit( $in['index'] ) ) ) ? (int) $in['index'] : -1;
		if ( $index < 0 || $index >= $n ) {
			return new WP_Error( 'twd_sk_bad_input', 'Click inside the picture band you want to change first.' );
		}
		$section = $parts['sections'][ $index ];
		if ( ! self::section_has_class( $section, 'twd-sk-image-text--band' ) ) {
			return new WP_Error( 'twd_sk_bad_input', 'A picture for phones can be added to a picture band only.' );
		}
		// Whatever phone picture is there goes first (a change is a remove and an add), so there is never more than one.
		$plain = preg_replace( '/\s*<img\b(?=[^>]*\sclass\s*=\s*"(?:[^"]*\s)?twd-sk-phone-img(?:\s[^"]*)?")[^>]*>/i', '', $section );
		if ( ! empty( $in['remove'] ) ) {
			if ( $plain === $section && ! self::section_has_class( $section, 'twd-sk-has-phone' ) ) {
				return new WP_Error( 'twd_sk_bad_input', 'This picture band has no phone picture to remove.' );
			}
			$sections           = $parts['sections'];
			$sections[ $index ] = self::section_class( $plain, 'twd-sk-has-phone', false );
			return array(
				'html' => TWD_SK_Sections::join( $parts['gaps'], $sections ),
				'at'   => $index,
				'note' => 'Removed the phone picture from a picture band',
			);
		}
		$id  = isset( $in['picture'] ) && ( is_int( $in['picture'] ) || ( is_string( $in['picture'] ) && ctype_digit( $in['picture'] ) ) ) ? (int) $in['picture'] : 0;
		$url = TWD_SK_Inline::picture_url( $id );
		if ( is_wp_error( $url ) ) {
			return $url;
		}
		if ( 1 !== preg_match( '/<img\b[^>]*>/i', $plain, $main, PREG_OFFSET_CAPTURE ) ) {
			return new WP_Error( 'twd_sk_bad_input', 'This picture band has no picture yet. Choose its picture first.' );
		}
		// The phone picture is described like the main one; the media library's own description is the fallback.
		$alt = 1 === preg_match( '/\salt\s*=\s*"([^"]*)"/i', $main[0][0], $a ) ? $a[1] : '';
		if ( '' === trim( $alt ) && function_exists( 'get_post_meta' ) ) {
			$alt = esc_attr( (string) get_post_meta( $id, '_wp_attachment_image_alt', true ) );
		}
		$tag     = '<img class="twd-sk-image-text__img twd-sk-phone-img" src="' . esc_attr( $url ) . '" alt="' . $alt . '">';
		$added   = substr( $plain, 0, $main[0][1] + strlen( $main[0][0] ) ) . $tag . substr( $plain, $main[0][1] + strlen( $main[0][0] ) );
		$sections           = $parts['sections'];
		$sections[ $index ] = self::section_class( $added, 'twd-sk-has-phone', true );
		return array(
			'html' => TWD_SK_Sections::join( $parts['gaps'], $sections ),
			'at'   => $index,
			'note' => 'Added a phone picture to a picture band',
		);
	}

	/**
	 * Add pictures to a gallery section (0.22.0). `index` is the section, `pictures` are media library ids (at most 40),
	 * `group` is which category when the gallery has more than one (0 when it has one). Each picture is added at the end of
	 * that group, described by the media library's own description (empty when it has none; the editor says so). Nothing else
	 * in the section changes. Saved as one version by the caller.
	 */
	public static function gallery_add( $page_id, $in ) {
		$in    = is_array( $in ) ? $in : array();
		$parts = TWD_SK_Sections::split( TWD_SK_Store::get_current( (int) $page_id ) );
		$n     = count( $parts['sections'] );
		$index = isset( $in['index'] ) && ( is_int( $in['index'] ) || ( is_string( $in['index'] ) && ctype_digit( $in['index'] ) ) ) ? (int) $in['index'] : -1;
		if ( $index < 0 || $index >= $n ) {
			return new WP_Error( 'twd_sk_bad_input', 'Click inside the gallery you want to add pictures to first.' );
		}
		$section = $parts['sections'][ $index ];
		if ( ! self::section_has_class( $section, 'twd-sk-gallery' ) ) {
			return new WP_Error( 'twd_sk_bad_input', 'Pictures can be added to a gallery section only.' );
		}
		$ids = array();
		if ( isset( $in['pictures'] ) && is_array( $in['pictures'] ) ) {
			foreach ( $in['pictures'] as $one ) {
				if ( is_int( $one ) || ( is_string( $one ) && ctype_digit( $one ) ) ) {
					$ids[] = (int) $one;
				}
			}
		}
		$ids = array_slice( array_values( array_unique( $ids ) ), 0, 40 );
		if ( ! $ids ) {
			return new WP_Error( 'twd_sk_bad_input', 'Choose at least one picture from the media library.' );
		}
		$found = preg_match_all( '/<div\b[^>]*\sclass\s*=\s*"(?:[^"]*\s)?twd-sk-gallery__track(?:\s[^"]*)?"[^>]*>/i', $section, $tracks, PREG_OFFSET_CAPTURE );
		if ( ! $found ) {
			return new WP_Error( 'twd_sk_bad_input', 'This gallery has no place for pictures. Use Add section to make a new gallery.' );
		}
		$group = isset( $in['group'] ) && ( is_int( $in['group'] ) || ( is_string( $in['group'] ) && ctype_digit( $in['group'] ) ) ) ? (int) $in['group'] : ( 1 === $found ? 0 : -1 );
		if ( $group < 0 || $group >= $found ) {
			return new WP_Error( 'twd_sk_bad_input', 'Choose which category the pictures go in.' );
		}
		$figures = '';
		$noalt   = 0;
		foreach ( $ids as $id ) {
			$url = TWD_SK_Inline::picture_url( $id );
			if ( is_wp_error( $url ) ) {
				return $url;
			}
			$alt = function_exists( 'get_post_meta' ) ? trim( (string) get_post_meta( $id, '_wp_attachment_image_alt', true ) ) : '';
			if ( '' === $alt ) {
				$noalt++;
			}
			$figures .= '<figure class="twd-sk-gallery__item"><img class="twd-sk-gallery__img" src="' . esc_attr( $url ) . '" alt="' . esc_attr( $alt ) . '"></figure>' . "\n";
		}
		$start = $tracks[0][ $group ][1] + strlen( $tracks[0][ $group ][0] );
		$close = strpos( $section, '</div>', $start );
		if ( false === $close ) {
			return new WP_Error( 'twd_sk_bad_input', 'This gallery could not be read. Use Undo, or Edit with AI.' );
		}
		$sections           = $parts['sections'];
		$sections[ $index ] = substr( $section, 0, $close ) . $figures . substr( $section, $close );
		return array(
			'html'  => TWD_SK_Sections::join( $parts['gaps'], $sections ),
			'at'    => $index,
			'noalt' => $noalt,
			'note'  => 'Added ' . count( $ids ) . ( 1 === count( $ids ) ? ' picture' : ' pictures' ) . ' to a gallery',
		);
	}

	/** Whether the section's own opening tag carries a class. */
	private static function section_has_class( $section, $class ) {
		if ( 1 !== preg_match( '/^<section\b[^>]*\sclass\s*=\s*"([^"]*)"/i', $section, $m ) ) {
			return false;
		}
		return in_array( $class, preg_split( '/\s+/', trim( $m[1] ) ), true );
	}

	/** The section with a class on its own opening tag added or taken away. */
	private static function section_class( $section, $class, $on ) {
		return preg_replace_callback( '/^(<section\b[^>]*\sclass\s*=\s*")([^"]*)(")/i', function ( $m ) use ( $class, $on ) {
			$tokens = array_values( array_filter( preg_split( '/\s+/', trim( $m[2] ) ), function ( $c ) use ( $class ) {
				return '' !== $c && $c !== $class;
			} ) );
			if ( $on ) {
				$tokens[] = $class;
			}
			return $m[1] . implode( ' ', $tokens ) . $m[3];
		}, $section, 1 );
	}

	/** The picture band for one section's HTML, or an error saying why it cannot be made. */
	public static function picture_band_from( $section ) {
		if ( 'custom' !== TWD_SK_Sections::type_of( $section ) ) {
			return new WP_Error( 'twd_sk_bad_input', 'Only a custom section (one made with Replace this section) can be turned into a picture band.' );
		}
		$more = 'This custom section holds more than a picture, so it cannot be turned into a picture band. Use Replace this section to change it.';
		if ( '' !== TWD_SK_Sections::text_of( $section ) || 1 !== preg_match_all( '/<img\b[^>]*>/i', $section, $m ) || false !== stripos( $section, '<h1' ) ) {
			return new WP_Error( 'twd_sk_bad_input', $more );
		}
		if ( 1 !== preg_match( '/\ssrc\s*=\s*"([^"]*)"/i', $m[0][0], $src ) || '' === trim( $src[1] ) ) {
			return new WP_Error( 'twd_sk_bad_input', 'This custom section\'s picture has no address, so it cannot be turned into a picture band.' );
		}
		$alt = 1 === preg_match( '/\salt\s*=\s*"([^"]*)"/i', $m[0][0], $a ) ? $a[1] : '';
		$url = html_entity_decode( $src[1], ENT_QUOTES, 'UTF-8' );
		// A media library picture saved at a resized address (name-1024x677.jpg) goes back to its full size when that file is a library picture.
		if ( 1 === preg_match( '/^(.*)-\d+x\d+(\.[A-Za-z0-9]+)$/', $url, $sized ) && function_exists( 'attachment_url_to_postid' ) && (int) attachment_url_to_postid( $sized[1] . $sized[2] ) > 0 ) {
			$url = $sized[1] . $sized[2];
		}
		return '<section class="twd-sk-image-text twd-sk-image-text--band"><img class="twd-sk-image-text__img" src="' . esc_attr( $url ) . '" alt="' . $alt . '"></section>';
	}
}
