<?php
/**
 * TWD_SK_Snagbook: the administrator's Snag button (0.15.0, section 24 of the owner's plan).
 *
 * An administrator presses Snag at the moment something goes wrong (or when they want something) and
 * writes one line. The editor scripts attach what led up to it: the last 30 editor events (a button
 * pressed, a tab opened, a section picked, a route called with its status code, a refusal shown) and
 * the context (page, section, versions, build, view, window, browser, the editor's own script errors).
 * Nothing is sent until Snag is pressed, and nothing leaves the site: the list lives in the options
 * table, newest first, capped at 200. A session on the owner's laptop exports it with
 * `wp twd-sk snags export` into the client's private snags.md, then marks the snags exported.
 *
 * Rules:
 *  - Administrators only (the routes check manage_options; the scripts load for administrators only).
 *    Clients keep Tell us (TWD_SK_Snags); this is a separate list and a separate class.
 *  - Never a key, password, login or nonce: every stored string goes through scrub(). Never the typed
 *    words of the page: the scripts record button labels, route paths and status codes, and the sizes
 *    of the HTML and CSS boxes, not their contents. The last Preview and What changed note are stored
 *    only when the person ticks the box.
 *  - Never in the public repository: the export is for the private clients repository.
 *  - This class checks nobody (like the page store); the REST layer and WP-CLI decide who may call it.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class TWD_SK_Snagbook {

	const OPT          = 'twd_sk_snagbook';
	const OPT_NEXT     = 'twd_sk_snagbook_next';
	const CAP          = 200;
	const MAX_LINE     = 500;
	const MAX_EVENTS   = 30;
	const MAX_EVENT    = 200;
	const MAX_ERRORS   = 10;
	const MAX_VALUE    = 160;
	const MAX_TEXT     = 20000;
	const SHORT_DETAIL = 14;

	public static function categories() {
		return array(
			'fault'    => 'Fault',
			'wanted'   => 'Wanted',
			'question' => 'Question',
		);
	}

	public static function statuses() {
		return array(
			'open'   => 'Open',
			'done'   => 'Done',
			'wontdo' => "Won't do",
		);
	}

	/** The context keys a snag may carry, each a short plain value. */
	public static function context_keys() {
		return array( 'page_id', 'page_title', 'section', 'section_kind', 'page_version', 'view', 'width', 'height', 'browser', 'window', 'url_path', 'client_time' );
	}

	// -- Cleaning ---------------------------------------------------------------------------

	/** Plain text: no tags, no control characters, no long dashes, one line unless $lines, cut to $max. */
	public static function text( $value, $max, $lines = false ) {
		if ( is_bool( $value ) ) {
			$value = $value ? 'yes' : 'no';
		}
		if ( ! is_scalar( $value ) ) {
			return '';
		}
		$value = wp_strip_all_tags( (string) $value );
		$value = preg_replace( '/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F]/', '', $value );
		$value = preg_replace( '/\r\n?/', "\n", $value );
		if ( ! $lines ) {
			$value = preg_replace( '/\s+/', ' ', $value );
		}
		$value = TWD_SK_Sanitizer::strip_dashes( $value );
		$value = self::scrub( $value );
		$value = trim( $value );
		if ( function_exists( 'mb_substr' ) ) {
			return mb_substr( $value, 0, $max );
		}
		return substr( $value, 0, $max );
	}

	/** One event's words: a label, a route, a status line. Tags are kept as typed (a message may name one); one line, scrubbed, cut. */
	public static function event_text( $value, $max ) {
		$value = self::code_text( $value, $max * 4 );
		$value = preg_replace( '/\s+/', ' ', $value );
		$value = TWD_SK_Sanitizer::strip_dashes( $value );
		$value = trim( $value );
		if ( function_exists( 'mb_substr' ) ) {
			return mb_substr( $value, 0, $max );
		}
		return substr( $value, 0, $max );
	}

	/** A code text (the last Preview's HTML or CSS, the note): tags kept, control characters out, scrubbed, cut to $max. */
	public static function code_text( $value, $max ) {
		if ( ! is_scalar( $value ) ) {
			return '';
		}
		$value = preg_replace( '/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F]/', '', (string) $value );
		$value = preg_replace( '/\r\n?/', "\n", $value );
		$value = self::scrub( $value );
		$value = trim( $value );
		if ( function_exists( 'mb_substr' ) ) {
			return mb_substr( $value, 0, $max );
		}
		return substr( $value, 0, $max );
	}

	/**
	 * Takes out anything that looks like a secret: a password, key, token, secret or nonce given as
	 * "name: value" or "name=value", a preview token or nonce in an address, and a sign-in cookie.
	 */
	public static function scrub( $value ) {
		$value = (string) $value;
		$value = preg_replace( '/\b(pass(?:word|wd|phrase)?|pwd|api[_ -]?key|secret|token|nonce|bearer|authorization|cookie)\b(\s*[:=]\s*|\s+)\S+/i', '$1: (removed)', $value );
		$value = preg_replace( '/([?&](?:twd_sk_preview|_wpnonce|nonce|token|key)=)[^&\s"\']+/i', '$1(removed)', $value );
		$value = preg_replace( '/\b(?=[0-9a-f]*\d)(?=[0-9a-f]*[a-f])[0-9a-f]{32,}\b/i', '(removed)', $value );
		return $value;
	}

	/** The lead-up record cleaned: at most MAX_EVENTS entries of { t, k, w }, each short. */
	public static function clean_events( $events ) {
		if ( ! is_array( $events ) ) {
			return array();
		}
		$out = array();
		foreach ( $events as $e ) {
			if ( ! is_array( $e ) ) {
				continue;
			}
			$k = isset( $e['k'] ) ? self::text( $e['k'], 20 ) : '';
			$w = isset( $e['w'] ) ? self::event_text( $e['w'], self::MAX_EVENT ) : '';
			if ( '' === $k && '' === $w ) {
				continue;
			}
			$t = isset( $e['t'] ) && is_numeric( $e['t'] ) ? (float) $e['t'] : 0;
			$out[] = array( 't' => round( $t, 1 ), 'k' => $k, 'w' => $w );
		}
		return array_slice( $out, -self::MAX_EVENTS );
	}

	/** The context cleaned: known keys only, short plain values; the script errors as a short list. */
	public static function clean_context( $context ) {
		$out = array();
		if ( ! is_array( $context ) ) {
			return $out;
		}
		foreach ( self::context_keys() as $key ) {
			if ( isset( $context[ $key ] ) ) {
				$v = self::text( $context[ $key ], self::MAX_VALUE );
				if ( '' !== $v ) {
					$out[ $key ] = $v;
				}
			}
		}
		if ( isset( $context['errors'] ) && is_array( $context['errors'] ) ) {
			$errors = array();
			foreach ( $context['errors'] as $line ) {
				$line = self::text( $line, self::MAX_EVENT );
				if ( '' !== $line ) {
					$errors[] = $line;
				}
			}
			if ( $errors ) {
				$out['errors'] = array_slice( $errors, -self::MAX_ERRORS );
			}
		}
		return $out;
	}

	// -- The list ---------------------------------------------------------------------------

	public static function all() {
		$list = get_option( self::OPT, array() );
		return is_array( $list ) ? array_values( array_filter( $list, 'is_array' ) ) : array();
	}

	private static function write( $list ) {
		$list = array_values( $list );
		usort( $list, function ( $a, $b ) {
			return $b['id'] - $a['id'];
		} );
		if ( count( $list ) > self::CAP ) {
			$list = array_slice( $list, 0, self::CAP );
		}
		update_option( self::OPT, $list, 'no' );
	}

	private static function next_id() {
		$next = (int) get_option( self::OPT_NEXT, 0 );
		$next = max( $next, 0 ) + 1;
		update_option( self::OPT_NEXT, $next, 'no' );
		return $next;
	}

	/**
	 * Saves a snag. $in: text (required), category, events, context, include (bool), preview_html,
	 * preview_css, what_changed. Returns the saved snag or a WP_Error. Checks nobody.
	 */
	public static function create( $in, $user_id = 0 ) {
		$in   = is_array( $in ) ? $in : array();
		$text = isset( $in['text'] ) ? self::text( $in['text'], self::MAX_LINE ) : '';
		if ( strlen( $text ) < 3 ) {
			return new WP_Error( 'twd_sk_snag_empty', 'Write a line about what went wrong or what you want.' );
		}
		$cat = isset( $in['category'] ) && is_string( $in['category'] ) ? strtolower( trim( $in['category'] ) ) : 'fault';
		if ( ! isset( self::categories()[ $cat ] ) ) {
			$cat = 'fault';
		}
		$host = '';
		if ( function_exists( 'home_url' ) ) {
			$parts = function_exists( 'wp_parse_url' ) ? wp_parse_url( home_url( '/' ) ) : parse_url( home_url( '/' ) );
			$host  = is_array( $parts ) && isset( $parts['host'] ) ? (string) $parts['host'] : '';
		}
		$css_v = 0;
		if ( class_exists( 'TWD_SK_Css' ) ) {
			$st    = TWD_SK_Css::status();
			$css_v = isset( $st['version'] ) ? (int) $st['version'] : 0;
		}
		$snag = array(
			'id'          => self::next_id(),
			'time'        => time(),
			'status'      => 'open',
			'category'    => $cat,
			'text'        => $text,
			'host'        => self::text( $host, 120 ),
			'build'       => self::text( defined( 'TWD_SK_VERSION' ) ? TWD_SK_VERSION : '', 40 ),
			'css_version' => $css_v,
			'user_id'     => (int) $user_id,
			'exported'    => 0,
			'events'      => self::clean_events( isset( $in['events'] ) ? $in['events'] : array() ),
			'context'     => self::clean_context( isset( $in['context'] ) ? $in['context'] : array() ),
		);
		if ( ! empty( $in['include'] ) ) {
			foreach ( array( 'preview_html', 'preview_css', 'what_changed' ) as $key ) {
				$v = isset( $in[ $key ] ) ? self::code_text( $in[ $key ], self::MAX_TEXT ) : '';
				if ( '' !== $v ) {
					$snag[ $key ] = $v;
				}
			}
		}
		$list   = self::all();
		$list[] = $snag;
		self::write( $list );
		return $snag;
	}

	public static function get( $id ) {
		$id = (int) $id;
		foreach ( self::all() as $s ) {
			if ( isset( $s['id'] ) && (int) $s['id'] === $id ) {
				return $s;
			}
		}
		return null;
	}

	/** Snags newest first. Options: status (open, done, wontdo or all; default all), unexported (true: only those not yet exported). */
	public static function find( $opts = array() ) {
		$status = isset( $opts['status'] ) ? (string) $opts['status'] : 'all';
		$out    = array();
		foreach ( self::all() as $s ) {
			if ( 'all' !== $status && $s['status'] !== $status ) {
				continue;
			}
			if ( ! empty( $opts['unexported'] ) && ! empty( $s['exported'] ) ) {
				continue;
			}
			$out[] = $s;
		}
		usort( $out, function ( $a, $b ) {
			return $b['id'] - $a['id'];
		} );
		return $out;
	}

	public static function set_status( $id, $status ) {
		if ( ! isset( self::statuses()[ $status ] ) ) {
			return new WP_Error( 'twd_sk_snag_bad_status', 'The status must be open, done or wontdo.' );
		}
		$list  = self::all();
		$found = null;
		foreach ( $list as $i => $s ) {
			if ( (int) $s['id'] === (int) $id ) {
				$list[ $i ]['status'] = $status;
				$found                = $list[ $i ];
			}
		}
		if ( null === $found ) {
			return new WP_Error( 'twd_sk_snag_missing', 'Snag ' . (int) $id . ' was not found.' );
		}
		self::write( $list );
		return $found;
	}

	/** Marks the given snags exported (the time). Returns how many changed. */
	public static function mark_exported( $ids ) {
		$ids   = array_map( 'intval', (array) $ids );
		$list  = self::all();
		$count = 0;
		foreach ( $list as $i => $s ) {
			if ( in_array( (int) $s['id'], $ids, true ) && empty( $s['exported'] ) ) {
				$list[ $i ]['exported'] = time();
				$count++;
			}
		}
		if ( $count ) {
			self::write( $list );
		}
		return $count;
	}

	public static function delete( $id ) {
		$list = self::all();
		$kept = array();
		foreach ( $list as $s ) {
			if ( (int) $s['id'] !== (int) $id ) {
				$kept[] = $s;
			}
		}
		if ( count( $kept ) === count( $list ) ) {
			return false;
		}
		self::write( $kept );
		return true;
	}

	public static function counts() {
		$open = 0;
		$unex = 0;
		foreach ( self::all() as $s ) {
			if ( 'open' === $s['status'] ) {
				$open++;
			}
			if ( empty( $s['exported'] ) ) {
				$unex++;
			}
		}
		return array( 'open' => $open, 'unexported' => $unex, 'total' => count( self::all() ), 'cap' => self::CAP );
	}

	// -- Output -----------------------------------------------------------------------------

	/**
	 * One snag as the panel and the CLI show it. $full adds the lead-up record, the context and the markdown;
	 * the included texts (which can be large and hold the candidate page) come only with $with_texts.
	 */
	public static function present( $s, $full = false, $with_texts = true ) {
		$row = array(
			'id'          => (int) $s['id'],
			'date'        => $s['time'] > 0 ? gmdate( 'Y-m-d', $s['time'] ) : '',
			'time'        => $s['time'] > 0 ? gmdate( 'Y-m-d H:i', $s['time'] ) . ' UTC' : '',
			'status'      => $s['status'],
			'category'    => $s['category'],
			'text'        => $s['text'],
			'page_id'     => isset( $s['context']['page_id'] ) ? (int) $s['context']['page_id'] : 0,
			'page_title'  => isset( $s['context']['page_title'] ) ? $s['context']['page_title'] : '',
			'build'       => $s['build'],
			'exported'    => ! empty( $s['exported'] ),
			'has_texts'   => ! empty( $s['preview_html'] ) || ! empty( $s['preview_css'] ) || ! empty( $s['what_changed'] ),
			'events'      => count( $s['events'] ),
		);
		if ( $full ) {
			$row['host']        = $s['host'];
			$row['css_version'] = (int) $s['css_version'];
			$row['context']     = $s['context'];
			$row['record']      = $s['events'];
			$row['user_id']     = (int) $s['user_id'];
			foreach ( array( 'preview_html', 'preview_css', 'what_changed' ) as $key ) {
				if ( $with_texts && ! empty( $s[ $key ] ) ) {
					$row[ $key ] = $s[ $key ];
				}
			}
			$row['line']   = self::line( $s );
			$row['detail'] = self::detail( $s, $with_texts );
			$row['long']   = self::is_long( $s );
		}
		return $row;
	}

	/** The page a snag was on, as the snags.md line names it. */
	private static function page_name( $s ) {
		$title = isset( $s['context']['page_title'] ) ? trim( $s['context']['page_title'] ) : '';
		if ( '' !== $title ) {
			return $title;
		}
		return isset( $s['context']['page_id'] ) && (int) $s['context']['page_id'] > 0 ? 'Page ' . (int) $s['context']['page_id'] : 'Not on a page';
	}

	/**
	 * The one line in the exact shape of a client's snags.md entry: "- DATE | PAGE | text".
	 * The category opens the text; the snag number and build close it, so the detail can be found.
	 */
	public static function line( $s ) {
		$cats = self::categories();
		$cat  = isset( $cats[ $s['category'] ] ) ? $cats[ $s['category'] ] : 'Fault';
		$tail = array( 'snag ' . (int) $s['id'] );
		if ( '' !== $s['build'] ) {
			$tail[] = 'build ' . $s['build'];
		}
		if ( isset( $s['context']['page_version'] ) && '' !== $s['context']['page_version'] ) {
			$tail[] = 'page version ' . $s['context']['page_version'];
		}
		if ( isset( $s['context']['section'] ) && '' !== $s['context']['section'] ) {
			$tail[] = 'section ' . $s['context']['section'] . ( isset( $s['context']['section_kind'] ) && '' !== $s['context']['section_kind'] ? ' ' . $s['context']['section_kind'] : '' );
		}
		$date = $s['time'] > 0 ? gmdate( 'Y-m-d', $s['time'] ) : 'undated';
		$page = str_replace( '|', '/', self::page_name( $s ) );
		$text = str_replace( array( "\n", '|' ), array( ' ', '/' ), $s['text'] );
		return '- ' . $date . ' | ' . $page . ' | ' . $cat . ': ' . $text . ' (' . implode( ', ', $tail ) . ')';
	}

	/** True when the detail is better kept in its own file: included texts, or a long record. */
	public static function is_long( $s ) {
		if ( ! empty( $s['preview_html'] ) || ! empty( $s['preview_css'] ) || ! empty( $s['what_changed'] ) ) {
			return true;
		}
		return count( $s['events'] ) + ( isset( $s['context']['errors'] ) ? count( $s['context']['errors'] ) : 0 ) > self::SHORT_DETAIL;
	}

	/** The context as plain "Name: value" lines. */
	public static function context_lines( $s ) {
		$c     = $s['context'];
		$lines = array();
		$lines[] = 'When: ' . ( $s['time'] > 0 ? gmdate( 'Y-m-d H:i', $s['time'] ) . ' UTC' : 'unknown' ) . ( isset( $c['client_time'] ) ? ' (browser ' . $c['client_time'] . ')' : '' );
		if ( '' !== $s['host'] ) {
			$lines[] = 'Site: ' . $s['host'] . ( isset( $c['url_path'] ) ? $c['url_path'] : '' );
		}
		$lines[] = 'Page: ' . self::page_name( $s ) . ( isset( $c['page_id'] ) ? ' (ID ' . (int) $c['page_id'] . ')' : '' ) . ( isset( $c['page_version'] ) ? ', version ' . $c['page_version'] : '' );
		if ( isset( $c['section'] ) ) {
			$lines[] = 'Section: ' . $c['section'] . ( isset( $c['section_kind'] ) ? ' (' . $c['section_kind'] . ')' : '' );
		}
		$lines[] = 'Build: ' . ( '' !== $s['build'] ? $s['build'] : 'unknown' ) . ', site stylesheet version ' . (int) $s['css_version'];
		$lines[] = 'View: ' . ( isset( $c['view'] ) ? $c['view'] : 'unknown' ) . ( isset( $c['window'] ) ? ', window ' . $c['window'] : '' );
		if ( isset( $c['width'] ) || isset( $c['browser'] ) ) {
			$lines[] = 'Browser: ' . ( isset( $c['browser'] ) ? $c['browser'] : 'unknown' ) . ( isset( $c['width'] ) ? ', ' . $c['width'] . ' by ' . ( isset( $c['height'] ) ? $c['height'] : '?' ) . ' px' : '' );
		}
		if ( ! empty( $c['errors'] ) ) {
			$lines[] = 'Script errors: ' . implode( '; ', $c['errors'] );
		}
		return $lines;
	}

	/** The lead-up record as plain lines, oldest first, each "+12.3s kind: what" (seconds before the snag). */
	public static function record_lines( $s ) {
		$lines = array();
		$n     = count( $s['events'] );
		$end   = $n ? (float) $s['events'][ $n - 1 ]['t'] : 0;
		foreach ( $s['events'] as $e ) {
			$ago     = $end - (float) $e['t'];
			$lines[] = '-' . number_format( $ago, 1 ) . 's ' . $e['k'] . ( '' !== $e['w'] ? ': ' . $e['w'] : '' );
		}
		return $lines;
	}

	/** The detail block as markdown (the context, the lead-up record, and the included texts unless $with_texts is false). */
	public static function detail( $s, $with_texts = true ) {
		$out = "Context\n";
		foreach ( self::context_lines( $s ) as $line ) {
			$out .= '- ' . $line . "\n";
		}
		$out .= "\nLead-up (seconds before Snag was pressed)\n";
		$rec  = self::record_lines( $s );
		if ( ! $rec ) {
			$out .= "- Nothing recorded.\n";
		}
		foreach ( $rec as $line ) {
			$out .= '- ' . $line . "\n";
		}
		if ( ! $with_texts ) {
			if ( ! empty( $s['preview_html'] ) || ! empty( $s['preview_css'] ) || ! empty( $s['what_changed'] ) ) {
				$out .= "\nThe last Preview and the What changed note are kept with this snag (wp twd-sk snags show " . (int) $s['id'] . ").\n";
			}
			return $out;
		}
		if ( ! empty( $s['what_changed'] ) ) {
			$out .= "\nWhat changed (the note)\n" . $s['what_changed'] . "\n";
		}
		if ( ! empty( $s['preview_html'] ) ) {
			$out .= "\nThe last Preview, HTML\n```html\n" . str_replace( '```', "'''", $s['preview_html'] ) . "\n```\n";
		}
		if ( ! empty( $s['preview_css'] ) ) {
			$out .= "\nThe last Preview, CSS\n```css\n" . str_replace( '```', "'''", $s['preview_css'] ) . "\n```\n";
		}
		return $out;
	}

	/**
	 * One snag as the pieces a laptop session places in the client's folder: the snags.md line (with a
	 * pointer when the detail is long), the indented block that goes under the line when it is short, and
	 * for a long one the path and the whole text of clients/SITE/snags/ID.md.
	 */
	public static function export_entry( $s, $site = '' ) {
		$site = preg_replace( '/[^a-z0-9\-]/', '', strtolower( (string) $site ) );
		$dir  = 'clients/' . ( '' !== $site ? $site : 'NAME' ) . '/snags/';
		$e    = array( 'id' => (int) $s['id'], 'status' => $s['status'], 'long' => self::is_long( $s ), 'line' => self::line( $s ), 'block' => '', 'path' => '', 'file' => '' );
		if ( $e['long'] ) {
			$e['path']  = $dir . (int) $s['id'] . '.md';
			$e['line'] .= ' (detail: ' . $e['path'] . ')';
			$e['file']  = '# Snag ' . (int) $s['id'] . "\n\n" . self::line( $s ) . "\n\n" . self::detail( $s );
			return $e;
		}
		foreach ( self::context_lines( $s ) as $line ) {
			$e['block'] .= '  - ' . $line . "\n";
		}
		$rec = self::record_lines( $s );
		if ( $rec ) {
			$e['block'] .= '  - Lead-up: ' . implode( '; ', $rec ) . "\n";
		}
		return $e;
	}

	/**
	 * The export for a client's snags.md as one text: one line per snag, newest first, then the detail
	 * indented under it when short, or a pointer to clients/SITE/snags/ID.md when long. The long details
	 * follow, each after a marker line a person can split on.
	 */
	public static function export_markdown( $snags, $site = '' ) {
		$out  = '';
		$long = array();
		foreach ( $snags as $s ) {
			$e    = self::export_entry( $s, $site );
			$out .= $e['line'] . "\n" . $e['block'];
			if ( $e['long'] ) {
				$long[] = $e;
			}
		}
		foreach ( $long as $e ) {
			$out .= "\n<!-- snag-file " . $e['path'] . " -->\n" . $e['file'];
		}
		return $out;
	}
}
