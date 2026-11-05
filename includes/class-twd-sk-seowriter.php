<?php
/**
 * TWD_SK_SeoWriter: a search title and description drafted by the AI from a page's own words, the facts and a few
 * keywords. It suggests and never saves: the person reads, edits and presses Save (the Search tab, or Search
 * wording in the Site tab). Asks the other plugin through the same two filters as the rest of Site Kit's AI.
 *
 * What it will not do: invent a fact (it is given only the page, the saved facts and the site name), run past the
 * lengths a search engine shows (the result is cut at a word, and the person is told), or use long dashes or
 * markers. Administrators only (the REST route decides).
 *
 * @package TWD_Site_Kit
 */

defined( 'ABSPATH' ) || exit;

class TWD_SK_SeoWriter {

	const TOKENS       = 700;
	const MAX_TITLE    = 70;
	const MAX_DESC     = 175;
	const MAX_KEYWORDS = 300;
	const PAGE_TEXT    = 6000;

	/** Keywords as plain text: no tags, one line, short. */
	public static function clean_keywords( $text ) {
		if ( ! is_string( $text ) ) {
			return '';
		}
		$text = trim( preg_replace( '/\s+/u', ' ', wp_strip_all_tags( $text ) ) );
		return function_exists( 'mb_substr' ) ? mb_substr( $text, 0, self::MAX_KEYWORDS ) : substr( $text, 0, self::MAX_KEYWORDS );
	}

	/** Pages with no search title or no search description: the ones worth writing for. */
	public static function missing() {
		$out = array();
		foreach ( TWD_SK_Launch::kit_pages() as $id ) {
			$d = TWD_SK_Seo::details( (int) $id );
			if ( '' !== trim( $d['title'] ) && '' !== trim( $d['description'] ) ) {
				continue;
			}
			$post  = get_post( (int) $id );
			$out[] = array(
				'id'          => (int) $id,
				'title'       => trim( (string) get_the_title( (int) $id ) ),
				'status'      => $post && isset( $post->post_status ) ? (string) $post->post_status : '',
				'has_title'   => '' !== trim( $d['title'] ),
				'has_description' => '' !== trim( $d['description'] ),
				'title_now'   => $d['title'],
				'description_now' => $d['description'],
			);
		}
		return $out;
	}

	public static function system_prompt() {
		$facts = TWD_SK_Mode::w( 'facts' );
		$l   = array();
		$l[] = 'You write the search title and the search description for one page of a small website. Reply with ONE JSON object and nothing else: { "title": "...", "description": "..." }.';
		$l[] = '';
		$l[] = 'Title: about 50 to 60 characters, never more than 65. Say what the page is about in the words a person would search for, using a given keyword where it fits naturally. End with " | " and the site name only if it fits within the limit; if the ' . $facts . ' or the notes give a title pattern, follow that pattern exactly.';
		$l[] = 'Description: about 140 to 160 characters, never more than 170. One or two plain sentences saying what a person will find on the page and, gently, why to read on. Use a keyword at most once, and only if it reads naturally.';
		$l[] = '';
		$l[] = 'Rules:';
		$l[] = '- Use ONLY what the page text, the ' . $facts . ' and the site name say. Never invent a fact, qualification, fee, result, place or claim, and never promise an outcome.';
		$l[] = '- Follow the spelling, wording and the things never to say that the ' . $facts . ' give. If they say a word or phrase must not be used, do not use it.';
		$l[] = '- Keywords are a hint, not an order. Skip a keyword that does not fit the page. Never repeat a keyword to stuff it in.';
		$l[] = '- Plain text only: no quotation marks around the whole thing, no hashtags, no emoji, no all capitals, no [PLACEHOLDER] markers, no long dashes (use a comma, a full stop or a spaced hyphen).';
		$l[] = '- If the page already has a title or description, you may improve it, but keep what is already right.';
		return implode( "\n", $l );
	}

	/** The words of the page, tidied and cut. */
	public static function page_text( $page_id ) {
		$html  = TWD_SK_Store::get_current( (int) $page_id );
		$parts = TWD_SK_Sections::split( $html );
		$text  = '';
		foreach ( $parts['sections'] as $s ) {
			$text .= TWD_SK_Sections::text_of( $s ) . "\n";
		}
		$text = trim( $text );
		return function_exists( 'mb_substr' ) ? mb_substr( $text, 0, self::PAGE_TEXT ) : substr( $text, 0, self::PAGE_TEXT );
	}

	public static function message( $page_id, $keywords ) {
		$name  = function_exists( 'get_bloginfo' ) ? trim( (string) get_bloginfo( 'name' ) ) : '';
		$d     = TWD_SK_Seo::details( (int) $page_id );
		$l     = array();
		$l[]   = 'Site name: ' . ( '' !== $name ? $name : '(not set)' );
		$l[]   = 'Page title: ' . trim( (string) get_the_title( (int) $page_id ) );
		$l[]   = 'Keywords to work in where they fit: ' . ( '' !== $keywords ? $keywords : '(none given)' );
		if ( '' !== trim( $d['title'] ) || '' !== trim( $d['description'] ) ) {
			$l[] = 'Current search title: ' . ( '' !== trim( $d['title'] ) ? $d['title'] : '(none)' );
			$l[] = 'Current search description: ' . ( '' !== trim( $d['description'] ) ? $d['description'] : '(none)' );
		}
		$block = TWD_SK_Facts::prompt_block();
		if ( '' !== $block ) {
			$l[] = '';
			$l[] = $block;
		}
		$l[] = '';
		$l[] = 'The words on the page:';
		$l[] = self::page_text( $page_id );
		return implode( "\n", $l ) . "\n";
	}

	/** Cut a string to a length at a word, without a trailing mark. */
	private static function fit( $text, $max ) {
		$len = function_exists( 'mb_strlen' ) ? mb_strlen( $text ) : strlen( $text );
		if ( $len <= $max ) {
			return array( $text, false );
		}
		$cut  = function_exists( 'mb_substr' ) ? mb_substr( $text, 0, $max ) : substr( $text, 0, $max );
		$last = strrpos( $cut, ' ' );
		if ( false !== $last && $last > $max * 0.6 ) {
			$cut = substr( $cut, 0, $last );
		}
		return array( rtrim( $cut, " ,;:-|" ), true );
	}

	/** One plain line: no tags, no long dashes, no markers. */
	private static function line( $text ) {
		$text = html_entity_decode( wp_strip_all_tags( (string) $text ), ENT_QUOTES, 'UTF-8' );
		$text = preg_replace( '/\s*[\x{2013}\x{2014}]\s*/u', ' - ', $text );
		$text = preg_replace( '/\[PLACEHOLDER[^\]]*\]/i', '', $text );
		return trim( preg_replace( '/\s+/u', ' ', $text ), " \"'" );
	}

	/**
	 * Draft a title and description for one page. Nothing is saved.
	 *
	 * @return array|WP_Error { title, description, warnings[] }
	 */
	public static function generate( $page_id, $keywords = '' ) {
		$page_id = (int) $page_id;
		if ( ! TWD_SK_AI::available() ) {
			return new WP_Error( 'twd_sk_ai_unavailable', 'AI is not set up on this site. Write the title and description by hand, or ask your web designer to add a key.' );
		}
		if ( $page_id <= 0 || ! TWD_SK_Page::is_kit_page( $page_id ) ) {
			return new WP_Error( 'twd_sk_not_a_page', 'That page was not found, or it is not a Site Kit page.' );
		}
		if ( '' === trim( (string) TWD_SK_Store::get_current( $page_id ) ) ) {
			return new WP_Error( 'twd_sk_empty_page', 'This page has no words yet, so there is nothing to write about.' );
		}
		$keywords = self::clean_keywords( $keywords );
		$reply    = apply_filters( 'twd_ai_complete', null, self::system_prompt(), self::message( $page_id, $keywords ), self::TOKENS );
		if ( is_wp_error( $reply ) ) {
			return $reply;
		}
		if ( ! is_string( $reply ) || '' === trim( $reply ) ) {
			return new WP_Error( 'twd_sk_ai_unavailable', 'The AI did not answer. Nothing was changed. Try again in a moment.' );
		}
		$text  = TWD_SK_AI::strip_fence( $reply );
		$start = strpos( $text, '{' );
		$end   = strrpos( $text, '}' );
		$data  = ( false !== $start && false !== $end && $end > $start ) ? json_decode( substr( $text, $start, $end - $start + 1 ), true ) : null;
		if ( ! is_array( $data ) || ! isset( $data['title'], $data['description'] ) || ! is_string( $data['title'] ) || ! is_string( $data['description'] ) ) {
			return new WP_Error( 'twd_sk_ai_bad_result', 'The AI did not answer in the form expected. Nothing was changed. Try again.' );
		}
		$title = self::line( $data['title'] );
		$desc  = self::line( $data['description'] );
		if ( '' === $title || '' === $desc ) {
			return new WP_Error( 'twd_sk_ai_bad_result', 'The AI sent an empty title or description. Nothing was changed. Try again.' );
		}
		$warnings = array();
		list( $title, $cut_t ) = self::fit( $title, self::MAX_TITLE );
		list( $desc, $cut_d )  = self::fit( $desc, self::MAX_DESC );
		if ( $cut_t ) {
			$warnings[] = 'The title was too long and was cut at a word. Check that it still reads well.';
		}
		if ( $cut_d ) {
			$warnings[] = 'The description was too long and was cut at a word. Check that it still reads well.';
		}
		return array( 'title' => $title, 'description' => $desc, 'warnings' => $warnings );
	}
}
