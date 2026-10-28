<?php
/**
 * TWD_SK_Embed: the Embed section (0.15.0). A place in a page for something this plugin does not
 * build itself: a shortcode from another plugin (a booking calendar, a map, a form), or a piece of
 * HTML code from a service (a widget snippet).
 *
 * The code is kept APART from the page. The page's stored HTML holds only [twd_embed id="N"], so the
 * sanitiser's bans on scripts, frames and forms stand for everything a person or an AI writes, the
 * code never enters a page's history, the plain-text search copy, the prompt or the AI, and the Visual
 * editor cannot touch it. The code itself lives in one option, written only here.
 *
 * Who can do what:
 *  - Only an administrator (manage_options) creates, changes or deletes an embed. Raw HTML also needs
 *    the unfiltered_html capability, which WordPress itself withholds on multisite and wherever
 *    DISALLOW_UNFILTERED_HTML is set. The class does not check this (like the page store); the REST
 *    routes and the Site tab do, through can_manage().
 *  - Anyone who can edit a page may leave an existing embed section where it is, but the AI is told
 *    never to write or change one, and the brief, the starter sets and the one-box planner refuse to add one.
 *
 * Kinds:
 *  - shortcode: only shortcodes, no tags at all. Run with do_shortcode() when the page is drawn.
 *  - html: printed exactly as saved.
 *
 * In safe mode nothing is printed (the code of a third party is the first thing to switch off).
 * An embed that does not exist prints nothing for visitors, and a short note for someone who can edit pages.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class TWD_SK_Embed {

	const SHORTCODE  = 'twd_embed';
	const OPTION     = 'twd_sk_embeds';
	const OPT_NEXT   = 'twd_sk_embeds_next';
	const MAX_ITEMS  = 40;
	const MAX_CODE   = 51200;
	const MAX_LABEL  = 80;

	private static $depth = 0;

	public static function init() {
		add_shortcode( self::SHORTCODE, array( __CLASS__, 'render' ) );
		// A page that points at an embed that has been deleted cannot be published: it would show a blank hole.
		add_filter( 'twd_sk_leftover_levels', array( __CLASS__, 'filter_levels' ), 10, 2 );
	}

	/** The words used in the leftover list for an embed that no longer exists. */
	const MISSING_MARKER = 'embed missing';

	/** Adds a must-fix item for each embed number in the page that is not saved (0, the sample, is already flagged). */
	public static function filter_levels( $levels, $post_id ) {
		$all = self::all();
		foreach ( self::ids_in( TWD_SK_Store::get_current( (int) $post_id ) ) as $eid ) {
			if ( $eid > 0 && ! isset( $all[ $eid ] ) ) {
				$levels['must'][ self::MISSING_MARKER ] = ( isset( $levels['must'][ self::MISSING_MARKER ] ) ? $levels['must'][ self::MISSING_MARKER ] : 0 ) + 1;
			}
		}
		return $levels;
	}

	/** kind => plain label. */
	public static function kinds() {
		return array(
			'shortcode' => 'A shortcode (for example from a booking or form plugin)',
			'html'      => 'HTML code (for example a widget snippet from a service)',
		);
	}

	// -- Who may -------------------------------------------------------------------------

	/** Administrators only. */
	public static function can_manage() {
		return current_user_can( 'manage_options' );
	}

	/** Whether the current user may save this kind: raw HTML also needs unfiltered_html. */
	public static function can_save_kind( $kind ) {
		if ( ! self::can_manage() ) {
			return false;
		}
		return 'html' !== $kind || current_user_can( 'unfiltered_html' );
	}

	// -- Reading -------------------------------------------------------------------------

	/** Every saved embed, id => record, ordered by id. Anything unreadable is left out. */
	public static function all() {
		$raw = get_option( self::OPTION, array() );
		if ( ! is_array( $raw ) ) {
			return array();
		}
		$out = array();
		foreach ( $raw as $id => $r ) {
			$id = (int) $id;
			if ( $id <= 0 || ! is_array( $r ) || ! isset( $r['kind'], $r['code'] ) || ! isset( self::kinds()[ $r['kind'] ] ) || ! is_string( $r['code'] ) || '' === $r['code'] ) {
				continue;
			}
			$out[ $id ] = array(
				'id'    => $id,
				'label' => isset( $r['label'] ) && is_string( $r['label'] ) ? $r['label'] : 'Embed ' . $id,
				'kind'  => $r['kind'],
				'code'  => $r['code'],
				'by'    => isset( $r['by'] ) ? (int) $r['by'] : 0,
				'time'  => isset( $r['time'] ) ? (int) $r['time'] : 0,
			);
		}
		ksort( $out );
		return $out;
	}

	public static function get( $id ) {
		$id  = (int) $id;
		$all = self::all();
		return isset( $all[ $id ] ) ? $all[ $id ] : null;
	}

	/** The ids named by [twd_embed id="N"] in some page HTML, in order, with 0 for a sample. */
	public static function ids_in( $html ) {
		$out = array();
		if ( is_string( $html ) && preg_match_all( '/\[' . self::SHORTCODE . '\s+id="(\d{1,9})"\]/', $html, $m ) ) {
			foreach ( $m[1] as $n ) {
				$out[] = (int) $n;
			}
		}
		return $out;
	}

	/** Pages (kit pages) that use each embed: id => list of array( id, title ). Read only. */
	public static function usage() {
		$use = array();
		if ( ! class_exists( 'TWD_SK_Launch' ) ) {
			return $use;
		}
		foreach ( TWD_SK_Launch::kit_pages() as $page_id ) {
			foreach ( array_unique( self::ids_in( TWD_SK_Store::get_current( $page_id ) ) ) as $eid ) {
				$use[ $eid ][] = array( 'id' => (int) $page_id, 'title' => (string) get_the_title( $page_id ) );
			}
		}
		return $use;
	}

	// -- Checking and saving -------------------------------------------------------------

	/**
	 * Check a label, a kind and some code.
	 *
	 * @return array|WP_Error array( label, kind, code ).
	 */
	public static function validate( $input ) {
		$input = is_array( $input ) ? $input : array();
		$label = isset( $input['label'] ) && is_string( $input['label'] ) ? trim( preg_replace( '/\s+/', ' ', sanitize_text_field( $input['label'] ) ) ) : '';
		$kind  = isset( $input['kind'] ) && is_string( $input['kind'] ) ? $input['kind'] : '';
		$code  = isset( $input['code'] ) && is_string( $input['code'] ) ? $input['code'] : '';

		if ( '' === $label ) {
			return new WP_Error( 'twd_sk_bad_input', 'Give the embed a short name so you can tell it apart, for example "Booking calendar".' );
		}
		if ( strlen( $label ) > self::MAX_LABEL ) {
			return new WP_Error( 'twd_sk_bad_input', 'Keep the name under ' . self::MAX_LABEL . ' characters.' );
		}
		if ( ! isset( self::kinds()[ $kind ] ) ) {
			return new WP_Error( 'twd_sk_bad_input', 'Choose whether this is a shortcode or HTML code.' );
		}
		// No control characters (a tab, a line break and a carriage return are fine).
		$code = trim( preg_replace( '/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F]/', '', $code ) );
		if ( '' === $code ) {
			return new WP_Error( 'twd_sk_bad_input', 'Paste the shortcode or the code to show.' );
		}
		if ( strlen( $code ) > self::MAX_CODE ) {
			return new WP_Error( 'twd_sk_bad_input', 'That is too long. An embed can hold up to ' . round( self::MAX_CODE / 1024 ) . ' KB.' );
		}
		// An embed can never run the page itself or another embed (no loops).
		if ( 1 === preg_match( '/\[\/?\s*(?:twd_page|' . self::SHORTCODE . ')\b/i', $code ) ) {
			return new WP_Error( 'twd_sk_bad_input', 'An embed cannot contain this plugin\'s own page or embed shortcodes.' );
		}
		if ( 'shortcode' === $kind ) {
			if ( false !== strpos( $code, '<' ) || false !== strpos( $code, '>' ) ) {
				return new WP_Error( 'twd_sk_bad_input', 'A shortcode holds no tags. If you have HTML code, choose "HTML code" instead.' );
			}
			if ( 1 !== preg_match( '/^\[[A-Za-z_][\w-]*[^\[\]]*\]/', $code ) ) {
				return new WP_Error( 'twd_sk_bad_input', 'A shortcode starts with a square bracket and a name, for example [booking_calendar].' );
			}
		}
		return array( 'label' => $label, 'kind' => $kind, 'code' => $code );
	}

	/**
	 * Save a new embed (id 0) or replace an existing one. No capability check here: the callers decide.
	 *
	 * @return array|WP_Error The saved record.
	 */
	public static function save( $input, $id = 0, $user_id = 0 ) {
		$ok = self::validate( $input );
		if ( is_wp_error( $ok ) ) {
			return $ok;
		}
		$id  = (int) $id;
		$all = self::all();
		if ( $id > 0 && ! isset( $all[ $id ] ) ) {
			return new WP_Error( 'twd_sk_not_found', 'That embed was not found. It may have been deleted.' );
		}
		if ( 0 === $id ) {
			if ( count( $all ) >= self::MAX_ITEMS ) {
				return new WP_Error( 'twd_sk_bad_input', 'A site can hold up to ' . self::MAX_ITEMS . ' embeds. Delete one you no longer use first.' );
			}
			$next = max( (int) get_option( self::OPT_NEXT, 1 ), $all ? max( array_keys( $all ) ) + 1 : 1 );
			$id   = $next;
			update_option( self::OPT_NEXT, $next + 1, false );
		}
		$rec = array(
			'label' => $ok['label'],
			'kind'  => $ok['kind'],
			'code'  => $ok['code'],
			'by'    => (int) $user_id,
			'time'  => time(),
		);
		$stored        = array();
		foreach ( $all as $eid => $r ) {
			$stored[ $eid ] = array( 'label' => $r['label'], 'kind' => $r['kind'], 'code' => $r['code'], 'by' => $r['by'], 'time' => $r['time'] );
		}
		$stored[ $id ] = $rec;
		ksort( $stored );
		update_option( self::OPTION, $stored, false );
		return array( 'id' => $id ) + $rec;
	}

	/** @return bool True when the embed existed and is gone. */
	public static function delete( $id ) {
		$id  = (int) $id;
		$all = self::all();
		if ( ! isset( $all[ $id ] ) ) {
			return false;
		}
		unset( $all[ $id ] );
		$stored = array();
		foreach ( $all as $eid => $r ) {
			$stored[ $eid ] = array( 'label' => $r['label'], 'kind' => $r['kind'], 'code' => $r['code'], 'by' => $r['by'], 'time' => $r['time'] );
		}
		update_option( self::OPTION, $stored, false );
		return true;
	}

	/** Id, name and kind of every embed, never the code: what the Add a section list needs. */
	public static function summaries() {
		$out = array();
		foreach ( self::all() as $id => $r ) {
			$out[] = array( 'id' => $id, 'label' => $r['label'], 'kind' => $r['kind'] );
		}
		return $out;
	}

	/** What the Site tab and the command line show: every embed with its code and where it is used. */
	public static function listing() {
		$use = self::usage();
		$out = array();
		foreach ( self::all() as $id => $r ) {
			$out[] = $r + array( 'used_on' => isset( $use[ $id ] ) ? $use[ $id ] : array() );
		}
		return $out;
	}

	// -- Drawing it ----------------------------------------------------------------------

	/** The note an editor sees in place of an embed that is not there. Visitors get nothing. */
	private static function missing_note( $id ) {
		if ( ! function_exists( 'current_user_can' ) || ! current_user_can( 'edit_pages' ) ) {
			return '';
		}
		$what = $id > 0 ? 'Embed ' . (int) $id . ' has not been found. It may have been deleted.' : 'No embed has been chosen for this section yet.';
		return '<p class="twd-sk-embed__missing">' . esc_html( $what . ' An administrator can set one up under Site, Embeds, and add it to the page from Add a section.' ) . '</p>';
	}

	/** The shortcode [twd_embed id="N"]. */
	public static function render( $atts = array() ) {
		if ( TWD_SK_Safe::on() || self::$depth > 0 ) {
			return '';
		}
		$id  = is_array( $atts ) && isset( $atts['id'] ) ? (int) $atts['id'] : 0;
		$rec = $id > 0 ? self::get( $id ) : null;
		if ( ! $rec ) {
			return self::missing_note( $id );
		}
		self::$depth++;
		try {
			$body = 'shortcode' === $rec['kind'] ? do_shortcode( $rec['code'] ) : $rec['code'];
		} catch ( Throwable $e ) {
			$body = '';
		}
		self::$depth--;
		return '<div class="twd-sk-embed__content">' . $body . '</div>';
	}
}
