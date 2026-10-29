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
 *  - At most MAX_VERSIONS are kept. When one must go, a copy goes first (an
 *    undo or restore whose HTML another kept version still holds), then the
 *    oldest, so a version holding real words is kept as long as possible.
 *  - Each version records how many must-fix placeholders it holds ('fix'), so
 *    Undo and Restore can refuse to land on the blank starter page unless the
 *    caller says allow_placeholders (0.15.0).
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
	const MAX_VERSIONS  = 20;
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
		$levels = TWD_SK_Sanitizer::find_leftovers_by_level( self::get_current( $post_id ) );
		// The SEO fields add their own example text to the same check, so the publish block covers them too.
		return function_exists( 'apply_filters' ) ? apply_filters( 'twd_sk_leftover_levels', $levels, (int) $post_id ) : $levels;
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
			// Page details (session 7, 29 Oct 2026): the title and description were changed with this save, so a
			// version is recorded even when the words on the page are the same.
			'details'       => ! empty( $args['details'] ),
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
		// A copy that was dropped at the cap (session 5, 29 Oct 2026): the version that still holds the same words
		// stands in for it, so a number an editor remembers (the Simple editor's Undo of a save) keeps working.
		if ( null === $source ) {
			foreach ( $store['items'] as $item ) {
				if ( in_array( (int) $version_id, $item['also'], true ) ) {
					$source = $item;
					break;
				}
			}
		}
		if ( null === $source ) {
			return new WP_Error( 'twd_sk_version_not_found', 'That version does not exist (it may have been dropped once more than ' . self::MAX_VERSIONS . ' were saved).' );
		}

		$head  = $store['items'] ? end( $store['items'] ) : null;
		$guard = self::placeholder_guard( $source, $head, $args );
		if ( is_wp_error( $guard ) ) {
			return $guard;
		}

		// The site stylesheet goes back with the page when it can (0.15.0): before the clean, so the classes that
		// stylesheet defines survive it, and before the version is recorded, so it names the stylesheet it sits on.
		$css_note = self::follow_css( $post_id, $source );
		// The page title and description go back with it when they can (session 7).
		$seo_note = self::follow_seo( $post_id, $source, $head );

		// Re-run the sanitiser so a restore can never bring back anything the
		// current rules would refuse.
		$result = TWD_SK_Sanitizer::clean_with_report( $source['html'] );
		$clean  = $result['html'];
		if ( '' === $clean ) {
			return new WP_Error( 'twd_sk_empty', 'Nothing was left after cleaning that version, so nothing was restored.' );
		}

		$out      = self::commit( $post_id, $store, $clean, array(
			'kind'          => 'restore',
			'note'          => self::note( $args, 'restore of v' . (int) $source['id'] ),
			'restored_from' => (int) $source['id'],
			'details'       => self::details_differ( $source, $head ),
		), $result['report'] );
		if ( ! is_wp_error( $out ) ) {
			$out['undid']    = $head ? self::label( $head ) : '';
			$out['target']   = (int) $source['id'];
			$out['now']      = self::label( $source );
			$out['css_note'] = $css_note;
			$out['seo_note'] = $seo_note;
		}
		return $out;
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
			// A version whose words are the same but whose page details differ is a change too (session 7).
			if ( (int) $item['id'] < $from && ( $item['html'] !== $head['html'] || self::details_differ( $item, $head ) ) ) {
				$target = $item;
				break;
			}
		}
		if ( null === $target ) {
			return new WP_Error( 'twd_sk_nothing_to_undo', 'There is no earlier version to go back to.' );
		}
		$guard = self::placeholder_guard( $target, $head, $args );
		if ( is_wp_error( $guard ) ) {
			return $guard;
		}

		// The site stylesheet goes back with the page when it can (0.15.0), before the clean (see restore()).
		$css_note = self::follow_css( $post_id, $target );
		$seo_note = self::follow_seo( $post_id, $target, $head );
		$result   = TWD_SK_Sanitizer::clean_with_report( $target['html'] );
		$clean    = $result['html'];
		if ( '' === $clean ) {
			return new WP_Error( 'twd_sk_empty', 'Nothing was left after cleaning that version, so nothing was changed.' );
		}

		// What was undone: the change the page showed before this step (the version undo started from).
		$undone = $head;
		foreach ( $items as $item ) {
			if ( (int) $item['id'] === $from ) {
				$undone = $item;
			}
		}
		$out = self::commit( $post_id, $store, $clean, array(
			'kind'          => 'undo',
			'note'          => self::note( $args, 'undo to v' . (int) $target['id'] ),
			'restored_from' => (int) $target['id'],
			'details'       => self::details_differ( $target, $head ),
		), $result['report'] );
		if ( ! is_wp_error( $out ) ) {
			$out['undid']    = self::label( $undone );
			$out['target']   = (int) $target['id'];
			$out['now']      = self::label( $target );
			$out['css_note'] = $css_note;
			$out['seo_note'] = $seo_note;
		}
		return $out;
	}

	/** The page title and description as they are now, or null when the search module is not loaded (session 7). */
	private static function details_now( $post_id ) {
		return class_exists( 'TWD_SK_Seo' ) ? TWD_SK_Seo::details( (int) $post_id ) : null;
	}

	/** Whether two versions recorded different page details (only when both recorded them). */
	private static function details_differ( $a, $b ) {
		return is_array( $a ) && is_array( $b ) && isset( $a['seo'], $b['seo'] ) && is_array( $a['seo'] ) && is_array( $b['seo'] ) && $a['seo'] != $b['seo'];
	}

	/**
	 * Going back to a version: take the page title and description back with it when it is safe to (session 7, 29 Oct
	 * 2026, section 21a). Safe means both versions recorded them, they differ, and the live values still equal what the
	 * current version recorded (so a change made in the Search tab since, which records no version, is never undone
	 * by accident). Returns a plain sentence, or '' when there was nothing to do.
	 */
	private static function follow_seo( $post_id, $target, $head ) {
		if ( ! class_exists( 'TWD_SK_Seo' ) || ! self::details_differ( $target, $head ) ) {
			return '';
		}
		$live = TWD_SK_Seo::details( (int) $post_id );
		if ( $live != $head['seo'] ) {
			return 'The page title and description were left as they are, because they were changed in the Search tab since.';
		}
		$r = TWD_SK_Seo::write( (int) $post_id, $target['seo'] );
		if ( is_wp_error( $r ) ) {
			return 'The page title and description could not go back: ' . $r->get_error_message();
		}
		return 'The page title and description went back with it.';
	}

	/** Going back to a version: take the site stylesheet back with it when every later change was this page's (0.15.0). */
	private static function follow_css( $post_id, $target ) {
		if ( ! class_exists( 'TWD_SK_Css' ) || ! isset( $target['css'] ) ) {
			return '';
		}
		return (string) TWD_SK_Css::follow_page( (int) $post_id, (int) $target['css'] );
	}

	// -- Internals --------------------------------------------------------

	/**
	 * Keep at most MAX_VERSIONS. A copy goes first: the oldest undo or restore whose
	 * HTML another kept version still holds (so no words are lost). Only then the
	 * oldest version. The newest version is never dropped.
	 */
	private static function trim( $items ) {
		while ( count( $items ) > self::MAX_VERSIONS ) {
			$drop = null;
			$last = count( $items ) - 1;
			foreach ( $items as $i => $item ) {
				if ( $i === $last || ! in_array( $item['kind'], array( 'undo', 'restore' ), true ) ) {
					continue;
				}
				foreach ( $items as $j => $other ) {
					if ( $j !== $i && $other['html'] === $item['html'] ) {
						$drop = $i;
						$items[ $j ]['also'] = array_values( array_unique( array_merge( $items[ $j ]['also'], array( (int) $item['id'] ), $item['also'] ) ) );
						break 2;
					}
				}
			}
			if ( null === $drop ) {
				$drop = 0;
			}
			array_splice( $items, $drop, 1 );
		}
		return array_values( $items );
	}

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
					'fix'           => isset( $item['fix'] ) ? (int) $item['fix'] : self::must_fix_count( (string) $item['html'] ),
					// The numbers of dropped copies whose words this version still holds (session 5).
					'also'          => isset( $item['also'] ) && is_array( $item['also'] ) ? array_values( array_map( 'intval', $item['also'] ) ) : array(),
					// The site stylesheet version this version was saved with (0.15.0, Base, Adapt, Replace); 0 before any.
					'css'           => isset( $item['css'] ) ? (int) $item['css'] : 0,
					// The page title and description at the time (session 7); null for a version saved before then.
					'seo'           => ( isset( $item['seo'] ) && is_array( $item['seo'] ) ) ? array(
						'title'       => isset( $item['seo']['title'] ) ? (string) $item['seo']['title'] : '',
						'description' => isset( $item['seo']['description'] ) ? (string) $item['seo']['description'] : '',
					) : null,
				);
			}
		}
		return $store;
	}

	/** How many must-fix placeholders some HTML holds (the ones that block publishing). */
	public static function must_fix_count( $html ) {
		$levels = TWD_SK_Sanitizer::find_leftovers_by_level( $html );
		return (int) array_sum( $levels['must'] );
	}

	/**
	 * A plain label for a version, for the History list and the Undo message.
	 * Examples: "Changed on the page (3 pieces)", "Undo: back to version 4", "Restored version 2", "Saved".
	 */
	public static function label( $item ) {
		$kind = isset( $item['kind'] ) ? (string) $item['kind'] : 'save';
		$note = isset( $item['note'] ) ? trim( (string) $item['note'] ) : '';
		$from = isset( $item['restored_from'] ) ? (int) $item['restored_from'] : 0;
		if ( 'undo' === $kind ) {
			return 'Undo: back to version ' . $from;
		}
		if ( 'restore' === $kind ) {
			return 'Restored version ' . $from;
		}
		return '' !== $note ? $note : 'Saved';
	}

	/**
	 * The guard Undo and Restore share: refuse to land on a version that still has
	 * must-fix placeholders when the page now has none, unless allow_placeholders is set.
	 */
	private static function placeholder_guard( $target, $head, $args ) {
		if ( ! empty( $args['allow_placeholders'] ) ) {
			return true;
		}
		if ( $target['fix'] > 0 && ( null === $head || (int) $head['fix'] === 0 ) ) {
			return new WP_Error( 'twd_sk_placeholders', sprintf(
				'Version %d still has placeholder text in %d place%s (it looks like the blank starter page). Nothing was changed. Go back to it anyway?',
				(int) $target['id'], (int) $target['fix'], 1 === (int) $target['fix'] ? '' : 's'
			), array( 'target' => (int) $target['id'], 'label' => self::label( $target ), 'fix' => (int) $target['fix'] ) );
		}
		return true;
	}

	private static function commit( $post_id, $store, $clean, $meta, $report ) {
		$items = $store['items'];
		$head  = $items ? end( $items ) : null;

		// Same HTML as now: nothing to record, and no history slot is used up. Unless the page details changed with
		// this write (session 7): then the version is the record of that change, so Undo can take it back.
		$seo = self::details_now( $post_id );
		if ( $head && $head['html'] === $clean && ! ( ! empty( $meta['details'] ) && $head['seo'] != $seo ) ) {
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
			'fix'           => self::must_fix_count( $clean ),
			'also'          => array(),
			'css'           => class_exists( 'TWD_SK_Css' ) ? TWD_SK_Css::version() : 0,
			'seo'           => $seo,
		);
		$items = self::trim( $items );

		// update_post_meta() strips slashes, so slash first or backslashes in
		// the HTML would be lost.
		update_post_meta( $post_id, self::META_VERSIONS, wp_slash( array( 'seq' => $id, 'items' => $items ) ) );
		update_post_meta( $post_id, self::META_HTML, wp_slash( $clean ) );

		// Other parts of the plugin (the search mirror) follow every new version.
		if ( function_exists( 'do_action' ) ) {
			do_action( 'twd_sk_committed', (int) $post_id, $clean );
		}

		return array(
			'version'   => $id,
			'unchanged' => false,
			'report'    => $report,
		);
	}
}
