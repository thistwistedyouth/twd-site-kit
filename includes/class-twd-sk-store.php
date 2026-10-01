<?php
/**
 * TWD_SK_Store: where a page's HTML and its version history live.
 *
 * Both live in post meta on the WordPress page that holds [twd_page]:
 *  - _twd_sk_html      the current sanitised HTML (so rendering never loads history)
 *  - _twd_sk_versions  array( seq => last version number, items => list of versions )
 *
 * Rules:
 *  - Every write goes through TWD_SK_Sanitizer::clean_with_report(). This class
 *    is the only thing that writes those two meta keys.
 *  - Append-only. A version is never edited. Restore and undo write a NEW
 *    version that copies the old HTML.
 *  - At most MAX_VERSIONS are kept. The oldest is dropped first.
 *  - No separate draft and published rows. The human gate (Preview, then Apply,
 *    with Undo) is built on top of this in a later slice. Callers only use the
 *    public methods below, so a draft concept can be added later without
 *    touching them.
 *  - No capability checks here. The REST layer (later slice) and WP-CLI decide
 *    who may call these.
 *
 * Every public method returns either data or a WP_Error.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class TWD_SK_Store {

	const META_HTML     = '_twd_sk_html';
	const META_VERSIONS = '_twd_sk_versions';
	const MAX_VERSIONS  = 10;
	const MAX_BYTES     = 200 * 1024; // 200 KB per page, before and after cleaning.

	// -- Reading ----------------------------------------------------------

	/** Current HTML, or an empty string if nothing is stored. */
	public static function get_current( $post_id ) {
		$html = get_post_meta( (int) $post_id, self::META_HTML, true );
		return is_string( $html ) ? $html : '';
	}

	/** Number of the newest version, or 0 if there are none. */
	/**
	 * Leftover example text in the page's current HTML: marker => count. Read only.
	 */
	public static function get_leftovers( $post_id ) {
		return TWD_SK_Sanitizer::find_leftovers( self::get_current( $post_id ) );
	}

	/** Leftover example text split by level: array( must => ..., check => ... ). */
	public static function get_leftover_levels( $post_id ) {
		return TWD_SK_Sanitizer::find_leftovers_by_level( self::get_current( $post_id ) );
	}

	public static function get_current_version_id( $post_id ) {
		$items = self::read( (int) $post_id )['items'];
		return $items ? (int) end( $items )['id'] : 0;
	}

	/**
	 * Version summaries, newest first, without the HTML.
	 */
	public static function list_versions( $post_id ) {
		$out = array();
		foreach ( array_reverse( self::read( (int) $post_id )['items'] ) as $item ) {
			$summary = $item;
			unset( $summary['html'] );
			$out[] = $summary;
		}
		return $out;
	}

	/** One full version (including html), or null. */
	public static function get_version( $post_id, $version_id ) {
		foreach ( self::read( (int) $post_id )['items'] as $item ) {
			if ( (int) $item['id'] === (int) $version_id ) {
				return $item;
			}
		}
		return null;
	}

	// -- Writing ----------------------------------------------------------

	/**
	 * Sanitise and store new HTML as a new version.
	 *
	 * @param int    $post_id
	 * @param string $html
	 * @param array  $args note (string), base_version (int): refuse if the page has moved on.
	 * @return array|WP_Error array( version, unchanged, report )
	 */
	public static function save( $post_id, $html, $args = array() ) {
		$post_id = (int) $post_id;
		$page    = self::check_page( $post_id );
		if ( is_wp_error( $page ) ) {
			return $page;
		}
		if ( ! is_string( $html ) ) {
			return new WP_Error( 'twd_sk_bad_input', 'The page content must be a string of HTML.' );
		}
		if ( strlen( $html ) > self::MAX_BYTES ) {
			return self::too_large();
		}

		$store = self::read( $post_id );
		$base  = self::check_base( $store, $args );
		if ( is_wp_error( $base ) ) {
			return $base;
		}

		$result = TWD_SK_Sanitizer::clean_with_report( $html );
		$clean  = $result['html'];
		if ( '' === $clean ) {
			return new WP_Error( 'twd_sk_empty', 'Nothing was left after cleaning, so nothing was saved.' );
		}
		if ( strlen( $clean ) > self::MAX_BYTES ) {
			return self::too_large();
		}

		return self::commit( $post_id, $store, $clean, array(
			'kind'          => 'save',
			'note'          => self::note( $args, '' ),
			'restored_from' => 0,
		), $result['report'] );
	}

	/**
	 * Write a new version that copies an older one.
	 */
	public static function restore( $post_id, $version_id, $args = array() ) {
		$post_id = (int) $post_id;
		$page    = self::check_page( $post_id );
		if ( is_wp_error( $page ) ) {
			return $page;
		}
		$store = self::read( $post_id );
		$base  = self::check_base( $store, $args );
		if ( is_wp_error( $base ) ) {
			return $base;
		}

		$source = null;
		foreach ( $store['items'] as $item ) {
			if ( (int) $item['id'] === (int) $version_id ) {
				$source = $item;
				break;
			}
		}
		if ( null === $source ) {
			return new WP_Error( 'twd_sk_version_not_found', 'That version does not exist (it may have been dropped once more than ' . self::MAX_VERSIONS . ' were saved).' );
		}

		// Re-run the sanitiser so a restore can never bring back anything the
		// current rules would refuse.
		$result = TWD_SK_Sanitizer::clean_with_report( $source['html'] );
		$clean  = $result['html'];
		if ( '' === $clean ) {
			return new WP_Error( 'twd_sk_empty', 'Nothing was left after cleaning that version, so nothing was restored.' );
		}

		return self::commit( $post_id, $store, $clean, array(
			'kind'          => 'restore',
			'note'          => self::note( $args, 'restore of v' . (int) $source['id'] ),
			'restored_from' => (int) $source['id'],
		), $result['report'] );
	}

	/**
	 * Step back through the page's history.
	 *
	 * Walks the timeline backwards from the current version, skipping versions
	 * with the same HTML as now. Calling it again after an undo keeps going
	 * further back rather than flipping between two versions. Like every other
	 * write it is recorded as a new version (kind 'undo').
	 */
	public static function undo( $post_id, $args = array() ) {
		$post_id = (int) $post_id;
		$page    = self::check_page( $post_id );
		if ( is_wp_error( $page ) ) {
			return $page;
		}
		$store = self::read( $post_id );
		$base  = self::check_base( $store, $args );
		if ( is_wp_error( $base ) ) {
			return $base;
		}

		$items = $store['items'];
		if ( ! $items ) {
			return new WP_Error( 'twd_sk_nothing_to_undo', 'This page has no saved versions yet.' );
		}
		$head = end( $items );

		// After an undo, carry on from the version that undo went back to.
		$from = ( 'undo' === $head['kind'] && $head['restored_from'] > 0 ) ? (int) $head['restored_from'] : (int) $head['id'];

		$target = null;
		foreach ( array_reverse( $items ) as $item ) {
			if ( (int) $item['id'] < $from && $item['html'] !== $head['html'] ) {
				$target = $item;
				break;
			}
		}
		if ( null === $target ) {
			return new WP_Error( 'twd_sk_nothing_to_undo', 'There is no earlier version to go back to.' );
		}

		$result = TWD_SK_Sanitizer::clean_with_report( $target['html'] );
		$clean  = $result['html'];
		if ( '' === $clean ) {
			return new WP_Error( 'twd_sk_empty', 'Nothing was left after cleaning that version, so nothing was changed.' );
		}

		return self::commit( $post_id, $store, $clean, array(
			'kind'          => 'undo',
			'note'          => self::note( $args, 'undo to v' . (int) $target['id'] ),
			'restored_from' => (int) $target['id'],
		), $result['report'] );
	}

	// -- Internals --------------------------------------------------------

	private static function too_large() {
		return new WP_Error(
			'twd_sk_too_large',
			'That page is larger than the ' . ( self::MAX_BYTES / 1024 ) . ' KB limit.'
		);
	}

	private static function note( $args, $default ) {
		if ( is_array( $args ) && isset( $args['note'] ) && is_string( $args['note'] ) && '' !== trim( $args['note'] ) ) {
			return TWD_SK_Sanitizer::strip_dashes( substr( trim( $args['note'] ), 0, 200 ) );
		}
		return $default;
	}

	private static function check_page( $post_id ) {
		if ( $post_id <= 0 ) {
			return new WP_Error( 'twd_sk_bad_page', 'That is not a valid page ID.' );
		}
		$post = get_post( $post_id );
		if ( ! $post || 'page' !== $post->post_type ) {
			return new WP_Error( 'twd_sk_bad_page', 'Page ' . $post_id . ' was not found (it must be a WordPress page).' );
		}
		return $post;
	}

	/** Refuse if the caller says which version it started from and the page has moved on. */
	private static function check_base( $store, $args ) {
		if ( ! is_array( $args ) || ! isset( $args['base_version'] ) || null === $args['base_version'] ) {
			return true;
		}
		$head = $store['items'] ? (int) end( $store['items'] )['id'] : 0;
		if ( (int) $args['base_version'] !== $head ) {
			return new WP_Error(
				'twd_sk_conflict',
				'The page has moved on since you started (you were on version ' . (int) $args['base_version'] . ', it is now on ' . $head . ').',
				array( 'current_version' => $head )
			);
		}
		return true;
	}

	/** Read the version store, always returning a well-formed array. */
	private static function read( $post_id ) {
		$raw   = get_post_meta( $post_id, self::META_VERSIONS, true );
		$store = array( 'seq' => 0, 'items' => array() );
		if ( ! is_array( $raw ) ) {
			return $store;
		}
		$store['seq'] = isset( $raw['seq'] ) ? (int) $raw['seq'] : 0;
		if ( isset( $raw['items'] ) && is_array( $raw['items'] ) ) {
			foreach ( $raw['items'] as $item ) {
				if ( ! is_array( $item ) || ! isset( $item['id'], $item['html'] ) ) {
					continue;
				}
				$store['items'][] = array(
					'id'            => (int) $item['id'],
					'html'          => (string) $item['html'],
					'created'       => isset( $item['created'] ) ? (string) $item['created'] : '',
					'user'          => isset( $item['user'] ) ? (int) $item['user'] : 0,
					'note'          => isset( $item['note'] ) ? (string) $item['note'] : '',
					'kind'          => isset( $item['kind'] ) ? (string) $item['kind'] : 'save',
					'restored_from' => isset( $item['restored_from'] ) ? (int) $item['restored_from'] : 0,
					'bytes'         => strlen( (string) $item['html'] ),
				);
			}
		}
		return $store;
	}

	private static function commit( $post_id, $store, $clean, $meta, $report ) {
		$items = $store['items'];
		$head  = $items ? end( $items ) : null;

		// Same HTML as now: nothing to record, and no history slot is used up.
		if ( $head && $head['html'] === $clean ) {
			return array(
				'version'   => (int) $head['id'],
				'unchanged' => true,
				'report'    => $report,
			);
		}

		$id      = $store['seq'] + 1;
		$items[] = array(
			'id'            => $id,
			'html'          => $clean,
			'created'       => gmdate( 'Y-m-d H:i:s' ),
			'user'          => function_exists( 'get_current_user_id' ) ? (int) get_current_user_id() : 0,
			'note'          => $meta['note'],
			'kind'          => $meta['kind'],
			'restored_from' => $meta['restored_from'],
			'bytes'         => strlen( $clean ),
		);
		while ( count( $items ) > self::MAX_VERSIONS ) {
			array_shift( $items );
		}

		// update_post_meta() strips slashes, so slash first or backslashes in
		// the HTML would be lost.
		update_post_meta( $post_id, self::META_VERSIONS, wp_slash( array( 'seq' => $id, 'items' => $items ) ) );
		update_post_meta( $post_id, self::META_HTML, wp_slash( $clean ) );

		return array(
			'version'   => $id,
			'unchanged' => false,
			'report'    => $report,
		);
	}
}
