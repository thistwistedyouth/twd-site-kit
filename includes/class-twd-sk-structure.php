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
}
