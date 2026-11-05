<?php
/**
 * TWD_SK_SiteImport: an AI's reply to a site export, read back as changes, checked, shown, and applied.
 *
 * The reply is ONE JSON object (see TWD_SK_SiteExport::readme()):
 *   { "schema": "twd-site-changes/1", "what_changed": "...", "css": "...",
 *     "changes": [ { "page", "section", "find", "replace", "why" }, { "page", "section", "html" },
 *                  { "page", "html" }, { "footer_html" } ] }
 *
 * plan() changes nothing: it reads the reply, works out for each page what it would become (all of a page's changes
 * are made on the page as exported, so section numbers never drift), runs each page through the same checks as
 * Replace this section (TWD_SK_Replace::prepare: the cleaner, the stylesheet checks, the warnings), and returns what
 * each change would do in plain words. apply() makes the ticked changes: each page through TWD_SK_Replace::apply as
 * ONE version (so Undo works page by page), the stylesheet with the first page, the footer through the embed save.
 * A page that moved on since the plan is refused, never overwritten.
 *
 * Administrators only (the REST route decides). Everything written goes through the store and the sanitiser.
 *
 * @package TWD_Site_Kit
 */

defined( 'ABSPATH' ) || exit;

class TWD_SK_SiteImport {

	const MAX_CHANGES = 200;
	const EXCERPT     = 160;

	// -- Reading -------------------------------------------------------------------------

	/** The reply text to { what_changed, css, changes[] }, or a plain error. */
	public static function read( $text ) {
		if ( ! is_string( $text ) || '' === trim( $text ) ) {
			return new WP_Error( 'twd_sk_bad_input', 'Paste the AI\'s whole reply first.' );
		}
		if ( strlen( $text ) > TWD_SK_Store::MAX_BYTES + TWD_SK_Css::MAX_SOURCE ) {
			return new WP_Error( 'twd_sk_too_large', 'That reply is larger than the site can take in one go. Ask the AI for the changes in two parts.' );
		}
		$text  = TWD_SK_AI::strip_fence( str_replace( array( "\r\n", "\r" ), "\n", $text ) );
		$start = strpos( $text, '{' );
		$end   = strrpos( $text, '}' );
		if ( false === $start || false === $end || $end <= $start ) {
			return new WP_Error( 'twd_sk_bad_input', 'That does not look like the reply the site reads. It should be one JSON object starting with {. Ask the AI to follow the README in the export.' );
		}
		$data = json_decode( substr( $text, $start, $end - $start + 1 ), true );
		if ( ! is_array( $data ) ) {
			return new WP_Error( 'twd_sk_bad_input', 'The reply could not be read as JSON (a quote or bracket is out of place, or it was cut short when copied). Ask the AI to send it again.' );
		}
		if ( ! isset( $data['schema'] ) || TWD_SK_SiteExport::REPLY_SCHEMA !== $data['schema'] ) {
			return new WP_Error( 'twd_sk_bad_input', 'The reply does not say it follows the site\'s format ("schema": "' . TWD_SK_SiteExport::REPLY_SCHEMA . '"). Ask the AI to follow the README in the export.' );
		}
		if ( ! isset( $data['changes'] ) || ! is_array( $data['changes'] ) ) {
			return new WP_Error( 'twd_sk_bad_input', 'The reply has no list of changes.' );
		}
		if ( count( $data['changes'] ) > self::MAX_CHANGES ) {
			return new WP_Error( 'twd_sk_too_large', 'That is more than ' . self::MAX_CHANGES . ' changes at once. Ask for the biggest ones first.' );
		}
		return array(
			'what_changed' => isset( $data['what_changed'] ) && is_string( $data['what_changed'] ) ? trim( $data['what_changed'] ) : '',
			'css'          => isset( $data['css'] ) && is_string( $data['css'] ) ? trim( $data['css'] ) : '',
			'changes'      => array_values( $data['changes'] ),
		);
	}

	/** A kit page's id from what the reply calls it (its export name, its id, or its title). 0 when not found. */
	public static function resolve_page( $ref ) {
		if ( is_int( $ref ) || ( is_string( $ref ) && ctype_digit( $ref ) ) ) {
			$id = (int) $ref;
			return $id > 0 && TWD_SK_Page::is_kit_page( $id ) ? $id : 0;
		}
		if ( ! is_string( $ref ) || '' === trim( $ref ) ) {
			return 0;
		}
		$ref = strtolower( trim( $ref ) );
		foreach ( TWD_SK_Launch::kit_pages() as $id ) {
			if ( strtolower( TWD_SK_SiteExport::stem( $id ) ) === $ref || strtolower( trim( (string) get_the_title( $id ) ) ) === $ref ) {
				return (int) $id;
			}
		}
		return 0;
	}

	/** The words of some HTML, shortened, for showing what a change does. */
	public static function words( $html, $max = self::EXCERPT ) {
		$text = trim( preg_replace( '/\s+/u', ' ', html_entity_decode( wp_strip_all_tags( (string) $html ), ENT_QUOTES, 'UTF-8' ) ) );
		if ( function_exists( 'mb_strlen' ) && mb_strlen( $text ) > $max ) {
			return rtrim( mb_substr( $text, 0, $max ) ) . '...';
		}
		return strlen( $text ) > $max ? rtrim( substr( $text, 0, $max ) ) . '...' : $text;
	}

	// -- Planning --------------------------------------------------------------------------

	/**
	 * Work out what a reply would do. Nothing is saved.
	 *
	 * @param array      $reply from read()
	 * @param array|null $only  item numbers to include (null: all)
	 * @return array { what_changed, css, items[], pages[], footer }
	 */
	public static function plan( $reply, $only = null ) {
		$items = array();
		$by    = array(); // page id => list of item numbers
		$footer_item = null;

		foreach ( $reply['changes'] as $n => $c ) {
			if ( null !== $only && ! in_array( $n, $only, true ) ) {
				continue;
			}
			$item = array( 'n' => $n, 'kind' => '', 'page' => '', 'page_id' => 0, 'section' => 0, 'why' => '', 'ok' => false, 'error' => '', 'before' => '', 'after' => '', 'what' => '' );
			if ( ! is_array( $c ) ) {
				$item['error'] = 'This change could not be read.';
				$items[ $n ] = $item;
				continue;
			}
			$item['why'] = isset( $c['why'] ) && is_string( $c['why'] ) ? self::words( $c['why'], 300 ) : '';

			if ( isset( $c['footer_html'] ) ) {
				$item['kind'] = 'footer';
				$item['what'] = 'The footer';
				$item['page'] = 'footer';
				$fe = TWD_SK_Chrome::footer_embed();
				if ( ! is_string( $c['footer_html'] ) || '' === trim( $c['footer_html'] ) ) {
					$item['error'] = 'The new footer is empty.';
				} elseif ( ! $fe ) {
					$item['error'] = 'The site\'s footer is not a saved HTML embed, so it cannot be replaced this way.';
				} elseif ( ! TWD_SK_Embed::can_save_kind( 'html' ) ) {
					$item['error'] = 'Only an administrator who may add HTML (unfiltered_html) can change the footer\'s HTML.';
				} else {
					$v = TWD_SK_Embed::validate( array( 'label' => $fe['label'], 'kind' => 'html', 'code' => $c['footer_html'] ) );
					if ( is_wp_error( $v ) ) {
						$item['error'] = $v->get_error_message();
					} else {
						$item['ok']     = true;
						$item['before'] = self::words( $fe['code'] );
						$item['after']  = self::words( $c['footer_html'] );
					}
				}
				$footer_item = $n;
				$items[ $n ] = $item;
				continue;
			}

			$ref = isset( $c['page'] ) ? $c['page'] : '';
			$id  = self::resolve_page( $ref );
			$item['page'] = is_string( $ref ) || is_int( $ref ) ? (string) $ref : '';
			if ( 0 === $id ) {
				$item['error'] = 'There is no page called "' . self::words( $item['page'], 60 ) . '". Use a name from pages/_index.json.';
				$items[ $n ] = $item;
				continue;
			}
			$item['page_id'] = $id;
			$item['page']    = TWD_SK_SiteExport::stem( $id );
			$has_section     = isset( $c['section'] ) && is_numeric( $c['section'] );
			$item['section'] = $has_section ? (int) $c['section'] : 0;
			if ( $has_section && isset( $c['find'] ) ) {
				$item['kind'] = 'words';
			} elseif ( $has_section && isset( $c['html'] ) ) {
				$item['kind'] = 'section';
			} elseif ( ! $has_section && isset( $c['html'] ) ) {
				$item['kind'] = 'page';
			} else {
				$item['error'] = 'This change needs a section number and "find" and "replace", or "html" (and a section number unless it replaces the whole page).';
				$items[ $n ] = $item;
				continue;
			}
			$item['what'] = ( 'page' === $item['kind'] ? 'The whole page "' : 'Section ' . $item['section'] . ' of "' ) . $item['page'] . '"';
			$items[ $n ] = $item;
			$by[ $id ][] = $n;
		}

		// One candidate per page: every change made on the page as it was exported.
		$pages = array();
		$css_in = isset( $reply['css'] ) ? $reply['css'] : '';
		$css_state = array( 'given' => '' !== $css_in, 'ok' => true, 'error' => '', 'bytes' => 0, 'budget' => TWD_SK_Css::BUDGET );
		$css_use = '';
		if ( '' !== $css_in ) {
			$merged = TWD_SK_Css::merge( TWD_SK_Css::current()['source'], $css_in );
			$check  = TWD_SK_Css::check( $merged );
			if ( $check['ok'] ) {
				$css_use = $css_in;
				$css_state['bytes'] = (int) $check['bytes'];
			} else {
				$css_state['ok']    = false;
				$css_state['error'] = 'The stylesheet rules were not accepted: ' . implode( ' ', array_slice( $check['errors'], 0, 2 ) ) . ' The page changes below were checked without them.';
			}
		}

		foreach ( $by as $id => $nums ) {
			$current = TWD_SK_Store::get_current( (int) $id );
			$parts   = TWD_SK_Sections::split( $current );
			$slots   = $parts['sections'];
			$page_level = null;
			$bad        = false;
			foreach ( $nums as $n ) {
				$c    = $reply['changes'][ $n ];
				$item = $items[ $n ];
				if ( 'page' === $item['kind'] ) {
					if ( count( $nums ) > 1 ) {
						$items[ $n ]['error'] = 'A change to the whole page cannot be combined with other changes to the same page. Send them separately.';
						$bad = true;
						continue;
					}
					if ( ! is_string( $c['html'] ) || '' === trim( $c['html'] ) ) {
						$items[ $n ]['error'] = 'The new page is empty.';
						$bad = true;
						continue;
					}
					$page_level = $c['html'];
					$items[ $n ]['before'] = self::words( $current );
					$items[ $n ]['after']  = self::words( $c['html'] );
					continue;
				}
				$i = $item['section'] - 1;
				if ( $i < 0 || ! isset( $slots[ $i ] ) ) {
					$items[ $n ]['error'] = 'This page has ' . count( $parts['sections'] ) . ' section' . ( 1 === count( $parts['sections'] ) ? '' : 's' ) . ', so there is no section ' . $item['section'] . '.';
					$bad = true;
					continue;
				}
				if ( 'notice' === TWD_SK_Sections::type_of( $parts['sections'][ $i ] ) ) {
					$items[ $n ]['error'] = 'The safety notice is kept as it is. It cannot be changed this way.';
					$bad = true;
					continue;
				}
				if ( 'words' === $item['kind'] ) {
					if ( ! is_string( $c['find'] ) || '' === $c['find'] || ! isset( $c['replace'] ) || ! is_string( $c['replace'] ) ) {
						$items[ $n ]['error'] = 'A find and replace needs the exact old words and the new words.';
						$bad = true;
						continue;
					}
					$found = substr_count( $slots[ $i ], $c['find'] );
					if ( 1 !== $found ) {
						$items[ $n ]['error'] = 0 === $found
							? 'The old words "' . self::words( $c['find'], 60 ) . '" are not in section ' . $item['section'] . ' exactly as written, so nothing was changed. Ask the AI to copy them from the file.'
							: 'The old words "' . self::words( $c['find'], 60 ) . '" appear ' . $found . ' times in section ' . $item['section'] . ', so the change is not clear. Ask the AI for more words around them.';
						$bad = true;
						continue;
					}
					$slots[ $i ] = str_replace( $c['find'], $c['replace'], $slots[ $i ] );
					$items[ $n ]['before'] = self::words( $c['find'], 200 );
					$items[ $n ]['after']  = self::words( $c['replace'], 200 );
				} else {
					if ( ! is_string( $c['html'] ) || '' === trim( $c['html'] ) ) {
						$items[ $n ]['error'] = 'The new section is empty.';
						$bad = true;
						continue;
					}
					$items[ $n ]['before'] = self::words( $parts['sections'][ $i ] );
					$items[ $n ]['after']  = self::words( $c['html'] );
					$slots[ $i ] = trim( $c['html'] );
				}
			}
			$ok_nums = array();
			foreach ( $nums as $n ) {
				if ( '' === $items[ $n ]['error'] ) {
					$ok_nums[] = $n;
				}
			}
			$page = array( 'page_id' => (int) $id, 'page' => TWD_SK_SiteExport::stem( $id ), 'title' => trim( (string) get_the_title( $id ) ), 'base' => (int) TWD_SK_Store::get_current_version_id( $id ), 'changes' => $nums, 'ok' => false, 'error' => '', 'warnings' => array(), 'same' => false, 'html' => '' );
			if ( ! $ok_nums ) {
				$page['error'] = 'None of the changes to this page could be used.';
				$pages[ $id ] = $page;
				continue;
			}
			$candidate = null !== $page_level ? $page_level : TWD_SK_Sections::join( $parts['gaps'], $slots );
			$out = TWD_SK_Replace::prepare( $id, array( 'target' => 'page', 'html' => $candidate, 'css' => $css_use ) );
			if ( is_wp_error( $out ) ) {
				$page['error'] = $out->get_error_message();
				foreach ( $ok_nums as $n ) {
					$items[ $n ]['error'] = $out->get_error_message();
				}
				$pages[ $id ] = $page;
				continue;
			}
			$page['ok']       = true;
			$page['html']     = $candidate;
			$page['warnings'] = $out['warnings'];
			$page['same']     = ! empty( $out['same'] );
			foreach ( $ok_nums as $n ) {
				$items[ $n ]['ok'] = true;
			}
			if ( $page['same'] ) {
				$page['warnings'][] = 'These changes leave the page exactly as it is.';
			}
			$pages[ $id ] = $page;
		}

		return array(
			'what_changed' => isset( $reply['what_changed'] ) ? $reply['what_changed'] : '',
			'css'          => $css_state,
			'css_source'   => $css_use,
			'items'        => array_values( $items ),
			'pages'        => array_values( $pages ),
			'footer'       => $footer_item,
		);
	}

	/** What the screen needs from a plan: no candidate HTML (it is rebuilt on apply from the same reply). */
	public static function public_plan( $plan ) {
		foreach ( $plan['pages'] as $i => $p ) {
			unset( $plan['pages'][ $i ]['html'] );
		}
		unset( $plan['css_source'] );
		return $plan;
	}

	// -- Applying --------------------------------------------------------------------------

	/**
	 * Make the ticked changes. Each page is one version (Undo works per page); a page that changed since the plan
	 * is refused.
	 *
	 * @param array $reply from read()
	 * @param array $picked item numbers to apply
	 * @param array $bases  page id => the version the plan was made against
	 * @param int   $user_id
	 * @return array { results[], done, failed }
	 */
	public static function apply( $reply, $picked, $bases, $user_id = 0 ) {
		$picked  = array_values( array_unique( array_map( 'intval', (array) $picked ) ) );
		$plan    = self::plan( $reply, $picked );
		$results = array();
		$done    = 0;
		$failed  = 0;
		foreach ( $plan['pages'] as $p ) {
			$row = array( 'page' => $p['page'], 'title' => $p['title'], 'ok' => false, 'message' => '' );
			if ( ! $p['ok'] ) {
				$row['message'] = $p['error'];
			} else {
				$base = isset( $bases[ $p['page_id'] ] ) ? (int) $bases[ $p['page_id'] ] : $p['base'];
				$note = 'Changes from an AI (' . count( $p['changes'] ) . ')';
				$r    = TWD_SK_Replace::apply( $p['page_id'], array( 'target' => 'page', 'html' => $p['html'], 'css' => $plan['css_source'], 'note' => $note ), $base );
				if ( is_wp_error( $r ) ) {
					$row['message'] = $r->get_error_message();
				} else {
					$row['ok']      = true;
					$row['message'] = 'Saved as a new version. Undo on that page takes it back.';
				}
			}
			$row['ok'] ? $done++ : $failed++;
			$results[] = $row;
		}
		foreach ( $plan['items'] as $it ) {
			if ( 'footer' !== $it['kind'] ) {
				continue;
			}
			$row = array( 'page' => 'footer', 'title' => 'The footer', 'ok' => false, 'message' => $it['error'] );
			if ( $it['ok'] ) {
				$fe = TWD_SK_Chrome::footer_embed();
				$c  = $reply['changes'][ $it['n'] ];
				$r  = $fe ? TWD_SK_Embed::save( array( 'label' => $fe['label'], 'kind' => 'html', 'code' => $c['footer_html'] ), $fe['id'], (int) $user_id ) : new WP_Error( 'twd_sk_bad_input', 'The footer is no longer a saved HTML embed.' );
				if ( is_wp_error( $r ) ) {
					$row['message'] = $r->get_error_message();
				} else {
					$row['ok']      = true;
					$row['message'] = 'The footer was changed. The old footer HTML is not kept: copy it first if you may want it back.';
				}
			}
			$row['ok'] ? $done++ : $failed++;
			$results[] = $row;
		}
		return array( 'results' => $results, 'done' => $done, 'failed' => $failed );
	}
}
