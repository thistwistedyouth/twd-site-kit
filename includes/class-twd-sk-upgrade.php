<?php
/**
 * TWD_SK_Upgrade: bring pages built earlier up to date (0.15.0).
 *
 * A page is stored HTML, so when a later version of the kit adds a small piece to a section (for
 * example the line a Resources section shows while there are no articles), pages built earlier do not
 * have it. This class holds a short list of upgrades. Each one has a plain label, a way to count the
 * places on a page that could take it, and a way to add it. Adding a future upgrade means adding one
 * entry here.
 *
 * Rules:
 *  - Read only until apply() or run() is called. scan() and available() change nothing.
 *  - An upgrade only ADDS what is missing. It never changes a word the person wrote, and running it
 *    twice does nothing the second time.
 *  - Every page is written through TWD_SK_Store::save(), so the sanitiser has the last word, each page
 *    gets its own version with a note, and Undo puts it back.
 *  - The class checks nobody (like the page store). The REST routes and the Site tab need an
 *    administrator.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class TWD_SK_Upgrade {

	const MAX_NOTE = 190;

	/**
	 * The upgrades, in the order they run.
	 *
	 * @return array id => array( label (plain words), since (version that introduced it), find (callable), apply (callable) )
	 */
	public static function upgrades() {
		return array(
			'resources_none' => array(
				'label' => 'Resources: add the line that shows when there are no articles yet (its words can then be changed in the Visual editor)',
				'since' => '0.15.0',
				'find'  => array( __CLASS__, 'find_resources_none' ),
				'apply' => array( __CLASS__, 'apply_resources_none' ),
			),
		);
	}

	// -- Finding and adding, one page's HTML --------------------------------------------

	/** The Resources sections that hold the articles grid but not the line. Their positions, not their text. */
	private static function resources_missing( $html ) {
		$out   = array();
		$parts = TWD_SK_Sections::split( $html );
		foreach ( $parts['sections'] as $i => $sec ) {
			if ( 'resources' === TWD_SK_Sections::type_of( $sec ) && false !== strpos( $sec, '[twd_articles' ) && false === strpos( $sec, 'twd-sk-resources__none' ) ) {
				$out[] = $i;
			}
		}
		return $out;
	}

	public static function find_resources_none( $html ) {
		return count( self::resources_missing( $html ) );
	}

	/** Puts the line straight after the grid's shortcode, in the same container (so the strip keeps it in its grid column). */
	public static function apply_resources_none( $html ) {
		$missing = self::resources_missing( $html );
		if ( ! $missing ) {
			return $html;
		}
		$parts = TWD_SK_Sections::split( $html );
		foreach ( $missing as $i ) {
			$parts['sections'][ $i ] = preg_replace( '/(\[twd_articles[^\]]*\])/', '$1' . "\n" . TWD_SK_Registry::NO_ARTICLES_LINE, $parts['sections'][ $i ], 1 );
		}
		return TWD_SK_Sections::join( $parts['gaps'], $parts['sections'] );
	}

	// -- One page's HTML ----------------------------------------------------------------

	/**
	 * What could be added to this HTML. Read only.
	 *
	 * @return array list of array( id, label, count )
	 */
	public static function available( $html ) {
		$out = array();
		if ( ! is_string( $html ) || '' === $html ) {
			return $out;
		}
		foreach ( self::upgrades() as $id => $u ) {
			$n = (int) call_user_func( $u['find'], $html );
			if ( $n > 0 ) {
				$out[] = array( 'id' => $id, 'label' => $u['label'], 'count' => $n );
			}
		}
		return $out;
	}

	/**
	 * Add what is missing to some HTML. Returns the new HTML and what was added; nothing is saved.
	 *
	 * @param string     $html
	 * @param array|null $only Upgrade ids to run, or null for all.
	 * @return array { html, applied: list of { id, label, count } }
	 */
	public static function apply( $html, $only = null ) {
		$applied = array();
		if ( ! is_string( $html ) || '' === $html ) {
			return array( 'html' => (string) $html, 'applied' => $applied );
		}
		foreach ( self::upgrades() as $id => $u ) {
			if ( is_array( $only ) && ! in_array( $id, $only, true ) ) {
				continue;
			}
			$n = (int) call_user_func( $u['find'], $html );
			if ( $n > 0 ) {
				$html      = (string) call_user_func( $u['apply'], $html );
				$applied[] = array( 'id' => $id, 'label' => $u['label'], 'count' => $n );
			}
		}
		return array( 'html' => $html, 'applied' => $applied );
	}

	// -- The whole site -----------------------------------------------------------------

	/**
	 * Every kit page that could take an upgrade. Read only.
	 *
	 * @return array list of array( id, title, status, items: list of { id, label, count } )
	 */
	public static function scan() {
		$out = array();
		foreach ( TWD_SK_Launch::kit_pages() as $page_id ) {
			$items = self::available( TWD_SK_Store::get_current( $page_id ) );
			if ( $items ) {
				$post  = get_post( $page_id );
				$out[] = array(
					'id'     => (int) $page_id,
					'title'  => $post ? (string) $post->post_title : '',
					'status' => $post ? (string) $post->post_status : '',
					'items'  => $items,
				);
			}
		}
		return $out;
	}

	/** The total number of places across a scan. */
	public static function total( $scan ) {
		$n = 0;
		foreach ( (array) $scan as $page ) {
			foreach ( $page['items'] as $item ) {
				$n += (int) $item['count'];
			}
		}
		return $n;
	}

	/**
	 * Bring pages up to date. Each page is its own version with a note, so Undo puts it back.
	 *
	 * @param array|null $page_ids Pages to do, or null for every page that can take an upgrade.
	 * @return array { updated: list of { id, title, applied }, failed: list of { id, title, reason } }
	 */
	public static function run( $page_ids = null ) {
		$done = array( 'updated' => array(), 'failed' => array() );
		foreach ( self::scan() as $page ) {
			if ( is_array( $page_ids ) && ! in_array( $page['id'], array_map( 'intval', $page_ids ), true ) ) {
				continue;
			}
			$id     = $page['id'];
			$result = self::apply( TWD_SK_Store::get_current( $id ) );
			if ( ! $result['applied'] ) {
				continue;
			}
			$names = array();
			foreach ( $result['applied'] as $a ) {
				$names[] = $a['id'] . ( $a['count'] > 1 ? ' x' . $a['count'] : '' );
			}
			$saved = TWD_SK_Store::save( $id, $result['html'], array(
				'note'         => substr( 'Brought up to date: ' . implode( ', ', $names ), 0, self::MAX_NOTE ),
				'base_version' => TWD_SK_Store::get_current_version_id( $id ),
			) );
			if ( is_wp_error( $saved ) ) {
				$done['failed'][] = array( 'id' => $id, 'title' => $page['title'], 'reason' => $saved->get_error_message() );
			} else {
				$done['updated'][] = array( 'id' => $id, 'title' => $page['title'], 'applied' => $result['applied'] );
			}
		}
		return $done;
	}
}
