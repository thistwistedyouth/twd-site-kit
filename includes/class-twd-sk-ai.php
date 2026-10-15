<?php
/**
 * TWD_SK_AI: asks an AI to change part or all of a kit page, and hands back a CANDIDATE page.
 *
 * This plugin never holds an AI key and never contacts any AI service. It asks whichever plugin
 * offers the service, through two filters:
 *
 *   twd_ai_is_configured  ( false )                                       -> true when a key is set up
 *   twd_ai_complete       ( null, $system, $message, $max_tokens )        -> text, or a WP_Error,
 *                                                                           or null when nothing answers
 *
 * Nothing here saves a page. The result is a full candidate page that goes through the same preview
 * and apply steps as a pasted result: the cleaner, the preview, the one write path, the history.
 *
 * Sections not chosen are put back byte for byte. A locked section (a testimonial, the safety notice)
 * keeps its words unless the person unlocked it.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class TWD_SK_AI {

	const MAX_INSTRUCTION = 1500;
	const MAX_FACTS       = 3000;
	const TOKENS_SECTIONS = 6000;
	const TOKENS_PAGE     = 8000;
	// The Articles plugin allows at most 8000 tokens in one reply. So a long page is written a few
	// sections at a time, and any part that still runs out of room is halved and written again (0.9.2).
	const PAGE_CHUNK_OVER  = 6;
	const PAGE_CHUNK_SIZE  = 4;
	const PAGE_SPLIT_DEPTH = 3;

	/** True when a plugin offers an AI service with a key set up, and safe mode is off. */
	public static function available() {
		return ! TWD_SK_Safe::on() && true === (bool) apply_filters( 'twd_ai_is_configured', false );
	}

	/** Rules from the shared prompt that do not fit a one-shot call (they are for a chat). */
	private static function rules() {
		$skip = array( 'Ask a question first', 'Make one change at a time', 'Return the full page HTML' );
		$out  = array();
		foreach ( TWD_SK_Prompt::rules() as $rule ) {
			$drop = false;
			foreach ( $skip as $start ) {
				if ( 0 === strpos( $rule, $start ) ) {
					$drop = true;
				}
			}
			if ( ! $drop ) {
				$out[] = $rule;
			}
		}
		$out[] = 'Change only what the instruction asks for. Keep every other word, link and image as it is.';
		$out[] = 'Use only the facts in the page, the ' . TWD_SK_Mode::w( 'facts' ) . ' and the site details the ' . TWD_SK_Mode::w( 'person' ) . ' supplied. Where you need a fact you do not have, write [PLACEHOLDER: what is needed]. Never invent a ' . TWD_SK_Mode::w( 'invent_list' ) . '.';
		return $out;
	}

	public static function system_prompt( $mode ) {
		$out   = array();
		$out[] = 'You edit part of one page of a ' . TWD_SK_Mode::w( 'persons' ) . ' website. The page is HTML made from a fixed set of components.';
		$out[] = '';
		$out[] = '## Output';
		if ( 'sections' === $mode ) {
			$out[] = 'You are given some sections of the page. Reply with exactly the same number of section elements, in the same order, as raw HTML. Nothing before, nothing after, no code fence, no commentary.';
		} else {
			$out[] = 'Reply with the full page as raw HTML: a list of section elements. Nothing before, nothing after, no code fence, no commentary.';
		}
		$out[] = '';
		$out[] = '## Rules';
		foreach ( self::rules() as $i => $rule ) {
			$out[] = ( $i + 1 ) . '. ' . $rule;
		}
		$out[] = '';
		$out[] = '## Style guide';
		$out[] = TWD_SK_Prompt::style_guide();
		return implode( "\n", $out ) . "\n";
	}

	/** Plain text outline of the page for context, marking the chosen sections. */
	private static function outline( $parts, $chosen ) {
		$lines = array();
		foreach ( $parts['sections'] as $i => $section ) {
			$lines[] = ( $i + 1 ) . '. ' . TWD_SK_Sections::type_of( $section ) . ': ' . TWD_SK_Sections::label_of( $section ) . ( in_array( $i, $chosen, true ) ? '  <- you are changing this one' : '' );
		}
		return implode( "\n", $lines );
	}

	public static function user_message( $mode, $parts, $chosen, $instruction, $facts ) {
		$out   = array();
		$out[] = 'What to change: ' . $instruction;
		$master = TWD_SK_Facts::prompt_block();
		if ( '' !== $master ) {
			$out[] = '';
			$out[] = $master;
		}
		if ( '' !== $facts ) {
			$out[] = '';
			$out[] = 'Extra facts the ' . TWD_SK_Mode::w( 'person' ) . ' supplied for this change (the only other new facts you may use):';
			$out[] = $facts;
		}
		$out[] = '';
		if ( 'sections' === $mode ) {
			$out[] = 'The page, in order (for context only):';
			$out[] = self::outline( $parts, $chosen );
			$out[] = '';
			$out[] = 'The ' . count( $chosen ) . ' section(s) to change, in order:';
			foreach ( $chosen as $n => $i ) {
				$out[] = '';
				$out[] = '--- Section ' . ( $n + 1 ) . ' of ' . count( $chosen ) . ' (' . TWD_SK_Sections::type_of( $parts['sections'][ $i ] ) . ') ---';
				$out[] = $parts['sections'][ $i ];
			}
		} else {
			$out[] = 'The page as it is now:';
			$out[] = implode( "\n", $parts['sections'] );
		}
		return implode( "\n", $out ) . "\n";
	}

	/** Take away a code fence if the AI added one anyway. */
	public static function strip_fence( $text ) {
		$text = trim( (string) $text );
		if ( preg_match( '/^```[a-z]*\s*\n(.*?)\n?```\s*$/is', $text, $m ) ) {
			return trim( $m[1] );
		}
		return $text;
	}

	/** Plain text that keeps line breaks: tags gone, spaces tidied per line, long dashes replaced, cut to a length. */
	private static function lines( $value, $max ) {
		$value = str_replace( array( "\r\n", "\r" ), "\n", wp_strip_all_tags( (string) $value ) );
		$rows  = array();
		foreach ( explode( "\n", $value ) as $row ) {
			$row = trim( preg_replace( '/[ \t]+/u', ' ', preg_replace( '/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F]/', '', $row ) ) );
			if ( '' !== $row ) {
				$rows[] = $row;
			}
		}
		$value = TWD_SK_Sanitizer::strip_dashes( implode( "\n", $rows ) );
		return function_exists( 'mb_substr' ) ? mb_substr( $value, 0, $max ) : substr( $value, 0, $max );
	}

	/** True when the AI stopped because its reply reached the length ceiling (the Articles plugin's error). */
	public static function cut_off( $reply ) {
		return is_wp_error( $reply ) && ( 'twd_ap_ai_cut_off' === $reply->get_error_code() || false !== stripos( $reply->get_error_message(), 'ran out of room' ) );
	}

	/**
	 * Writing a page can take several AI calls. Allow up to five minutes where the host lets a plugin
	 * raise its time limit; never lowers it, and does nothing where there is no limit (the command line).
	 */
	public static function allow_time() {
		$limit = (int) ini_get( 'max_execution_time' );
		if ( $limit > 0 && $limit < 300 && function_exists( 'set_time_limit' ) && false === stripos( (string) ini_get( 'disable_functions' ), 'set_time_limit' ) ) {
			set_time_limit( 300 );
		}
	}

	/** Sections in balanced groups of at most $size, in order. */
	public static function chunk_parts( $parts, $size ) {
		$parts = array_values( (array) $parts );
		$n     = count( $parts );
		$size  = max( 1, (int) $size );
		if ( $n <= $size ) {
			return array( $parts );
		}
		$groups = (int) ceil( $n / $size );
		$out    = array();
		$start  = 0;
		for ( $g = 0; $g < $groups; $g++ ) {
			$len    = (int) ceil( ( $n - $start ) / ( $groups - $g ) );
			$out[]  = array_slice( $parts, $start, $len );
			$start += $len;
		}
		return $out;
	}

	private static function err( $code, $message ) {
		return new WP_Error( $code, $message );
	}

	/**
	 * @param int   $page_id
	 * @param array $in mode (sections|page), indexes, instruction, facts, unlock
	 * @return array|WP_Error { html, report[], kept[], changed[] }
	 */
	public static function remix( $page_id, $in ) {
		if ( ! self::available() ) {
			return self::err( 'twd_sk_ai_unavailable', 'AI is not set up on this site. Use the copy and paste way, or ask your web designer to add a key.' );
		}
		$mode        = isset( $in['mode'] ) && 'page' === $in['mode'] ? 'page' : 'sections';
		$instruction = isset( $in['instruction'] ) && is_string( $in['instruction'] ) ? trim( $in['instruction'] ) : '';
		$facts       = isset( $in['facts'] ) && is_string( $in['facts'] ) ? trim( $in['facts'] ) : '';
		if ( '' === $instruction ) {
			return self::err( 'twd_sk_bad_input', 'Say what you want changed first.' );
		}
		if ( strlen( $instruction ) > self::MAX_INSTRUCTION ) {
			return self::err( 'twd_sk_bad_input', 'That instruction is too long. Keep it under ' . self::MAX_INSTRUCTION . ' characters.' );
		}
		// The planner (0.10.0) passes the whole request as the facts, and its own working copy of the page.
		$max_facts = ! empty( $in['plan'] ) ? TWD_SK_Plan::MAX_REQUEST : self::MAX_FACTS;
		if ( strlen( $facts ) > $max_facts ) {
			return self::err( 'twd_sk_bad_input', 'The facts are too long. Keep them under ' . $max_facts . ' characters.' );
		}
		$instruction = TWD_SK_Profile::text( $instruction, self::MAX_INSTRUCTION );
		$facts       = self::lines( $facts, $max_facts );

		$current = isset( $in['html'] ) && is_string( $in['html'] ) ? $in['html'] : TWD_SK_Store::get_current( (int) $page_id );
		$parts   = TWD_SK_Sections::split( $current );
		$count   = count( $parts['sections'] );
		if ( 0 === $count ) {
			return self::err( 'twd_sk_empty_page', 'This page has no sections to change yet. Paste a page in first.' );
		}

		$chosen = array();
		if ( 'sections' === $mode ) {
			$wanted = isset( $in['indexes'] ) && is_array( $in['indexes'] ) ? $in['indexes'] : array();
			foreach ( $wanted as $i ) {
				if ( ( is_int( $i ) || ( is_string( $i ) && ctype_digit( $i ) ) ) && (int) $i >= 0 && (int) $i < $count ) {
					$chosen[ (int) $i ] = (int) $i;
				}
			}
			$chosen = array_values( $chosen );
			sort( $chosen );
			if ( ! $chosen ) {
				return self::err( 'twd_sk_bad_input', 'Choose at least one section to change.' );
			}
		}
		$unlock = array();
		if ( isset( $in['unlock'] ) && is_array( $in['unlock'] ) ) {
			foreach ( $in['unlock'] as $i ) {
				if ( is_int( $i ) || ( is_string( $i ) && ctype_digit( $i ) ) ) {
					$unlock[ (int) $i ] = true;
				}
			}
		}
		$locked_types = TWD_SK_Sections::locked_types();

		$system  = self::system_prompt( $mode );
		$message = self::user_message( $mode, $parts, $chosen, $instruction, $facts );
		$tokens  = 'sections' === $mode ? self::TOKENS_SECTIONS : self::TOKENS_PAGE;
		$reply   = apply_filters( 'twd_ai_complete', null, $system, $message, $tokens );
		if ( self::cut_off( $reply ) ) {
			return self::err( 'twd_sk_ai_too_long', 'That is more than the AI can rewrite in one go, so nothing was changed. Choose fewer sections (half of them, say) and ask again.' );
		}
		if ( is_wp_error( $reply ) ) {
			return $reply;
		}
		if ( ! is_string( $reply ) || '' === trim( $reply ) ) {
			return self::err( 'twd_sk_ai_unavailable', 'The AI did not answer. Nothing was changed. Try again in a moment.' );
		}
		$reply = self::strip_fence( $reply );
		if ( strlen( $reply ) > TWD_SK_Store::MAX_BYTES ) {
			return self::err( 'twd_sk_ai_bad_result', 'The AI sent back more than a page can hold. Try a smaller change.' );
		}
		$new = TWD_SK_Sections::split( $reply );
		$report  = array();
		$kept    = array();
		$changed = array();

		if ( 'sections' === $mode ) {
			if ( count( $new['sections'] ) !== count( $chosen ) ) {
				return self::err( 'twd_sk_ai_bad_result', 'The AI did not send back the sections as asked. Nothing was changed. Try again.' );
			}
			$sections = $parts['sections'];
			foreach ( $chosen as $n => $i ) {
				$orig = $parts['sections'][ $i ];
				$repl = $new['sections'][ $n ];
				$type = TWD_SK_Sections::type_of( $orig );
				if ( in_array( $type, $locked_types, true ) && empty( $unlock[ $i ] ) && TWD_SK_Sections::text_of( $orig ) !== TWD_SK_Sections::text_of( $repl ) ) {
					$kept[]   = $i;
					$report[] = 'Kept the wording of "' . TWD_SK_Sections::label_of( $orig ) . '" (' . $type . ') as it was. Its words are locked.';
					continue;
				}
				if ( $repl !== $orig ) {
					$sections[ $i ] = $repl;
					$changed[]      = $i;
				}
			}
			$html = TWD_SK_Sections::join( $parts['gaps'], $sections );
		} else {
			if ( ! $new['sections'] ) {
				return self::err( 'twd_sk_ai_bad_result', 'The AI did not send back a page. Nothing was changed. Try again.' );
			}
			$all = ' ' . TWD_SK_Sections::text_of( implode( ' ', $new['sections'] ) ) . ' ';
			foreach ( $parts['sections'] as $i => $orig ) {
				$type = TWD_SK_Sections::type_of( $orig );
				if ( in_array( $type, $locked_types, true ) && empty( $unlock[ $i ] ) && false === strpos( $all, TWD_SK_Sections::text_of( $orig ) ) ) {
					return self::err( 'twd_sk_ai_locked', 'The AI changed words that are locked ("' . TWD_SK_Sections::label_of( $orig ) . '", a ' . $type . '). Nothing was changed. Try again, or change sections one at a time.' );
				}
			}
			$html    = implode( "\n", $new['sections'] );
			$changed = array_keys( $new['sections'] );
		}
		if ( ! $changed ) {
			$report[] = 'The AI made no change.';
		}
		return array( 'html' => $html, 'report' => $report, 'kept' => $kept, 'changed' => $changed );
	}

	// -- Adding a section ---------------------------------------------------------

	/**
	 * A candidate page with one new section added after an existing one. Saves nothing: the result goes
	 * through the same preview and apply steps as any other change.
	 *
	 * Without the AI the new section is the component's outline with [PLACEHOLDER] markers (flagged by
	 * the publish check). With the AI, only the new section is written, from the facts, and it must
	 * come back as the same kind of section.
	 *
	 * @param int   $page_id
	 * @param array $in after (index), component, variant, instruction, facts, use_ai
	 * @return array|WP_Error { html, report[], kept[], changed[] }
	 */
	public static function add_section( $page_id, $in ) {
		$parts = TWD_SK_Sections::split( isset( $in['html'] ) && is_string( $in['html'] ) ? $in['html'] : TWD_SK_Store::get_current( (int) $page_id ) );
		$count = count( $parts['sections'] );
		if ( 0 === $count ) {
			return self::err( 'twd_sk_empty_page', 'This page has no sections yet. Add a page outline first.' );
		}
		$after = isset( $in['after'] ) && ( is_int( $in['after'] ) || ( is_string( $in['after'] ) && ctype_digit( $in['after'] ) ) ) ? (int) $in['after'] : -1;
		if ( $after < 0 || $after >= $count ) {
			return self::err( 'twd_sk_bad_input', 'Choose where the new section goes.' );
		}
		$component = isset( $in['component'] ) && is_string( $in['component'] ) ? $in['component'] : '';
		$variant   = isset( $in['variant'] ) && is_string( $in['variant'] ) ? $in['variant'] : '';
		$c         = TWD_SK_Registry::get( $component );
		if ( ! $c || ( '' !== $variant && ! isset( $c['variants'][ $variant ] ) ) ) {
			return self::err( 'twd_sk_bad_input', 'Choose one of the kit\'s sections and layouts.' );
		}
		$instruction = isset( $in['instruction'] ) && is_string( $in['instruction'] ) ? trim( $in['instruction'] ) : '';
		$facts       = isset( $in['facts'] ) && is_string( $in['facts'] ) ? trim( $in['facts'] ) : '';
		$max_facts = ! empty( $in['plan'] ) ? TWD_SK_Plan::MAX_REQUEST : self::MAX_FACTS;
		if ( strlen( $instruction ) > self::MAX_INSTRUCTION || strlen( $facts ) > $max_facts ) {
			return self::err( 'twd_sk_bad_input', 'That is too long. Keep the instruction under ' . self::MAX_INSTRUCTION . ' characters and the facts under ' . $max_facts . '.' );
		}
		$instruction = TWD_SK_Profile::text( $instruction, self::MAX_INSTRUCTION );
		$facts       = self::lines( $facts, $max_facts );

		$outline  = trim( TWD_SK_Starters::build( array( array( $component, $variant ) ), false ) );
		$sections = $parts['sections'];
		$gaps     = $parts['gaps'];
		$at       = $after + 1;
		array_splice( $sections, $at, 0, array( $outline ) );
		array_splice( $gaps, $at, 0, array( "\n" ) );

		if ( empty( $in['use_ai'] ) ) {
			return array(
				'html'    => TWD_SK_Sections::join( $gaps, $sections ),
				'report'  => array( 'Added a ' . $c['label'] . ' section as section ' . ( $at + 1 ) . '. Its wording is marked [PLACEHOLDER] for you to fill in.' ),
				'kept'    => array(),
				'changed' => array( $at ),
			);
		}
		if ( ! self::available() ) {
			return self::err( 'twd_sk_ai_unavailable', 'AI is not set up on this site. Add the section without AI, or ask your web designer to add a key.' );
		}
		$ask = 'This is a new section. Write its wording from the facts: replace every [PLACEHOLDER] and every sample word, and keep its component, layout and classes. Where the facts do not say what is needed, keep a [PLACEHOLDER: what is needed].' . ( '' !== $instruction ? ' Also: ' . $instruction : '' );
		$reply = apply_filters( 'twd_ai_complete', null, self::system_prompt( 'sections' ), self::user_message( 'sections', array( 'gaps' => $gaps, 'sections' => $sections ), array( $at ), $ask, $facts ), self::TOKENS_SECTIONS );
		if ( self::cut_off( $reply ) ) {
			return self::err( 'twd_sk_ai_too_long', 'That new section is more than the AI can write in one go. Ask for a shorter one.' );
		}
		if ( is_wp_error( $reply ) ) {
			return $reply;
		}
		if ( ! is_string( $reply ) || '' === trim( $reply ) ) {
			return self::err( 'twd_sk_ai_unavailable', 'The AI did not answer. Nothing was changed. Try again in a moment.' );
		}
		$new = TWD_SK_Sections::split( self::strip_fence( $reply ) );
		if ( 1 !== count( $new['sections'] ) || TWD_SK_Sections::type_of( $new['sections'][0] ) !== TWD_SK_Sections::type_of( $outline ) ) {
			return self::err( 'twd_sk_ai_bad_result', 'The AI did not send back one ' . $c['label'] . ' section. Nothing was changed. Try again, or add it without AI.' );
		}
		$sections[ $at ] = $new['sections'][0];
		return array(
			'html'    => TWD_SK_Sections::join( $gaps, $sections ),
			'report'  => array( 'The AI wrote a new ' . $c['label'] . ' section as section ' . ( $at + 1 ) . '. Check every fact before you apply it.' ),
			'kept'    => array(),
			'changed' => array( $at ),
		);
	}

	// -- A new page from the practice facts ------------------------------------

	/**
	 * System text for drafting a whole new page.
	 *
	 * @param bool $with_pictures True when the request lists pictures the AI may place (the setup build).
	 * @param bool $strict        True for the full site set up (0.12.0): only words the facts support, no [PLACEHOLDER]
	 *                            for text, leave out whatever the facts do not support. Pictures stay as they are.
	 */
	public static function page_system_prompt( $with_pictures = false, $strict = false ) {
		$out   = array();
		$out[] = 'You write one new page for a ' . TWD_SK_Mode::w( 'persons' ) . ' website. The page is HTML made from a fixed set of components.';
		$out[] = '';
		$out[] = '## Output';
		$out[] = 'Reply with the full page as raw HTML: a list of section elements that follows the outline you are given. Nothing before, nothing after, no code fence, no commentary.';
		$out[] = '';
		$out[] = '## Rules';
		$rules = self::rules();
		if ( $strict ) {
			// The shared rule that tells the AI to leave a [PLACEHOLDER] where a fact is missing does not apply here.
			$rules = array_values( array_filter( $rules, function ( $rule ) {
				return false === strpos( $rule, 'write [PLACEHOLDER: what is needed]' );
			} ) );
			$rules[] = 'Use only the facts in the page, the ' . TWD_SK_Mode::w( 'facts' ) . ' and the site details the ' . TWD_SK_Mode::w( 'person' ) . ' supplied. Never invent a ' . TWD_SK_Mode::w( 'invent_list' ) . '.';
			$rules[] = 'Follow the outline: keep its components and their order. Replace every [PLACEHOLDER] in the outline with real wording written ONLY from the ' . TWD_SK_Mode::w( 'facts' ) . ' and the site details, in the ' . TWD_SK_Mode::w( 'persons' ) . ' own voice. Never write a [PLACEHOLDER] for any text. If the facts do not support a sentence, a heading, a card or a list item, leave it out and let the page be shorter: delete a whole card or list item rather than inventing it.';
			$rules[] = 'Never invent names, qualifications, registrations, prices, fees, contact details, addresses, opening times, reviews, statistics or claims. Use contact details exactly as given. If there is no phone number, remove the phone button or row completely. If there is no postal address, write where the ' . TWD_SK_Mode::w( 'person' ) . ' works (the area served, or online) in its place.';
		} else {
			$rules[] = 'Follow the outline: keep its components and their order. Replace every [PLACEHOLDER] in the outline with real wording written from the ' . TWD_SK_Mode::w( 'facts' ) . ', in the ' . TWD_SK_Mode::w( 'persons' ) . ' own voice. Where the facts do not say what is needed, keep a [PLACEHOLDER: what is needed] there.';
		}
		if ( $with_pictures ) {
			$rules[] = 'Pictures: where the outline has a picture, you may use one of the pictures listed in the request by writing its address exactly as given (for example pic:front-view), with alt text that says what it shows. Use each picture once at most. Where none fits, keep the outline\'s picture address exactly as it is. Never invent a picture or a file name.';
		} else {
			$rules[] = 'Keep every image address in the outline exactly as it is. The ' . TWD_SK_Mode::w( 'person' ) . ' adds real pictures later. Never invent an image file name.';
		}
		if ( ! $strict ) {
			$rules[] = 'Use the contact details and registration wording exactly as given in the facts and site details. If they are not given, leave a [PLACEHOLDER].';
		} else {
			$rules[] = 'Use registration wording exactly as given in the facts and site details. If none is given, do not mention registration at all.';
		}
		foreach ( $rules as $i => $rule ) {
			$out[] = ( $i + 1 ) . '. ' . $rule;
		}
		$out[] = '';
		$out[] = '## Style guide';
		$out[] = TWD_SK_Prompt::style_guide();
		return implode( "\n", $out ) . "\n";
	}

	/**
	 * The request text for drafting a new page of a type, with an optional list of pictures it may place.
	 *
	 * @param array|null $part For a page written in parts: start (index of the first section here),
	 *                         total (sections on the whole page) and used (picture names already placed).
	 */
	public static function page_message( $in, $pictures_block = '', $part = null ) {
		$r     = TWD_SK_Recipes::get( $in['type'] );
		$out   = array();
		$out[] = 'Write this page: ' . $r['intent'];
		$out[] = 'Page title: ' . $in['title'];
		if ( '' !== $in['topic'] ) {
			$out[] = 'Topic: ' . $in['topic'];
		}
		if ( '' !== $in['notes'] ) {
			$out[] = 'Extra notes from the person asking: ' . $in['notes'];
		}
		$master = TWD_SK_Facts::prompt_block();
		$out[]  = '';
		if ( '' !== $master ) {
			$out[] = $master;
		}
		if ( '' === TWD_SK_Facts::get() ) {
			$out[] = 'No ' . TWD_SK_Mode::w( 'facts' ) . ' have been saved yet. Do not invent any. Keep a [PLACEHOLDER: what is needed] wherever a fact is needed.';
		}
		if ( '' !== $pictures_block ) {
			$out[] = '';
			$out[] = $pictures_block;
		}
		if ( is_array( $part ) ) {
			$count  = count( $in['parts'] );
			$out[]  = '';
			$out[]  = 'This page is written in parts. This request is sections ' . ( $part['start'] + 1 ) . ' to ' . ( $part['start'] + $count ) . ' of ' . (int) $part['total'] . '. The other sections are written separately and joined to these, so write only the sections in the outline below. The notes above describe the whole page; use the parts that fit these sections.';
			if ( $part['start'] > 0 ) {
				$out[] = 'The page\'s main heading (h1) is in an earlier part, so do not write an h1 here.';
			}
			if ( ! empty( $part['used'] ) ) {
				$out[] = 'Pictures already used on this page (do not use them again): ' . implode( ', ', $part['used'] ) . '.';
			}
		}
		$out[]  = '';
		$out[]  = 'The outline to follow (replace the placeholders, keep the components and their order):';
		$out[]  = is_array( $part ) ? TWD_SK_Starters::build( $in['parts'], 0 === (int) $part['start'] ) : TWD_SK_Recipes::skeleton_for( $in );
		return implode( "\n", $out ) . "\n";
	}

	/**
	 * One text the person can paste into an external AI chat to draft the page.
	 * Includes the practice facts only when the caller says the person may see them.
	 */
	public static function page_prompt( $in, $include_facts ) {
		$r = self::page_message( $in );
		if ( ! $include_facts ) {
			$block = TWD_SK_Facts::prompt_block();
			if ( '' !== $block ) {
				$r = str_replace( $block, 'The ' . TWD_SK_Mode::w( 'person' ) . ' will give you the ' . TWD_SK_Mode::w( 'facts' ) . ' in the chat. Ask for them first, and use only what they say.', $r );
			}
		}
		return self::page_system_prompt() . "\n## The request\n" . $r;
	}

	/**
	 * Ask the AI for one page's HTML. Saves nothing: the caller saves it (through the store, so the
	 * sanitiser always runs). The build passes a pictures block; the reply may then hold pic: names,
	 * which TWD_SK_Pictures::place() turns into real addresses before saving.
	 *
	 * A page with more than PAGE_CHUNK_OVER sections is written PAGE_CHUNK_SIZE sections at a time, and
	 * any part whose reply runs out of room is halved and written again, so no page is too long to write.
	 *
	 * @param array  $in             Checked inputs from TWD_SK_Recipes::inputs().
	 * @param string $pictures_block From TWD_SK_Pictures::prompt_block(), or ''.
	 * @return string|WP_Error The page's sections, one after another.
	 */
	public static function draft_page_html( $in, $pictures_block = '' ) {
		if ( ! self::available() ) {
			return self::err( 'twd_sk_ai_unavailable', 'AI is not set up on this site. Use the copy and paste way, or ask your web designer to add a key.' );
		}
		self::allow_time();
		$parts  = ! empty( $in['parts'] ) ? array_values( $in['parts'] ) : TWD_SK_Recipes::get( $in['type'] )['parts'];
		$total  = count( $parts );
		$groups = $total > self::PAGE_CHUNK_OVER ? self::chunk_parts( $parts, self::PAGE_CHUNK_SIZE ) : array( $parts );
		$used   = array();
		$html   = array();
		$start  = 0;
		foreach ( $groups as $group ) {
			$part = self::draft_part( $in, $group, $start, $total, $pictures_block, $used, 0 );
			if ( is_wp_error( $part ) ) {
				return $part;
			}
			$html[] = $part;
			$start += count( $group );
		}
		return implode( "\n", $html );
	}

	/** Write the sections in $group (the page's sections from $start on). Halves the group when the reply runs out of room. */
	private static function draft_part( $in, $group, $start, $total, $pictures_block, &$used, $depth ) {
		$sub          = $in;
		$sub['parts'] = $group;
		$whole        = 0 === $start && count( $group ) === $total;
		$message      = self::page_message( $sub, $pictures_block, $whole ? null : array( 'start' => $start, 'total' => $total, 'used' => array_keys( $used ) ) );
		$reply        = apply_filters( 'twd_ai_complete', null, self::page_system_prompt( '' !== $pictures_block, ! empty( $in['strict'] ) ), $message, self::TOKENS_PAGE );
		if ( self::cut_off( $reply ) ) {
			if ( count( $group ) > 1 && $depth < self::PAGE_SPLIT_DEPTH ) {
				$half  = (int) ceil( count( $group ) / 2 );
				$first = self::draft_part( $in, array_slice( $group, 0, $half ), $start, $total, $pictures_block, $used, $depth + 1 );
				if ( is_wp_error( $first ) ) {
					return $first;
				}
				$second = self::draft_part( $in, array_slice( $group, $half ), $start + $half, $total, $pictures_block, $used, $depth + 1 );
				if ( is_wp_error( $second ) ) {
					return $second;
				}
				return $first . "\n" . $second;
			}
			return self::err( 'twd_sk_ai_too_long', 'The AI ran out of room writing this page, even a section at a time. Shorten the notes for this page, or the facts, and try again.' );
		}
		if ( is_wp_error( $reply ) ) {
			return $reply;
		}
		if ( ! is_string( $reply ) || '' === trim( $reply ) ) {
			return self::err( 'twd_sk_ai_unavailable', 'The AI did not answer. Nothing was saved. Try again in a moment.' );
		}
		$reply = self::strip_fence( $reply );
		if ( strlen( $reply ) > TWD_SK_Store::MAX_BYTES ) {
			return self::err( 'twd_sk_ai_bad_result', 'The AI sent back more than a page can hold. No page was created. Try again with shorter notes.' );
		}
		$sections = TWD_SK_Sections::split( $reply )['sections'];
		if ( ! $sections ) {
			return self::err( 'twd_sk_ai_bad_result', 'The AI did not send back a page. No page was created. Try again.' );
		}
		if ( $start > 0 ) {
			// Only the part that starts the page holds its main heading.
			$sections = preg_replace( array( '#<h1(?=[\s>])#i', '#</h1\s*>#i' ), array( '<h2', '</h2>' ), $sections );
		}
		if ( preg_match_all( '/pic:([a-z0-9]+(?:-[a-z0-9]+)*)/', implode( ' ', $sections ), $m ) ) {
			foreach ( $m[1] as $name ) {
				$used[ $name ] = true;
			}
		}
		return implode( "\n", $sections );
	}

	/**
	 * Draft a new page from the practice facts and save it as a DRAFT page set up for the kit.
	 * The page is created only after the AI has answered with something usable.
	 *
	 * @return array|WP_Error { id, title, url, sections, leftover_count, must_count }
	 */
	public static function generate_page( $raw ) {
		if ( ! self::available() ) {
			return self::err( 'twd_sk_ai_unavailable', 'AI is not set up on this site. Use the copy and paste way, or ask your web designer to add a key.' );
		}
		$in = TWD_SK_Recipes::inputs( $raw );
		if ( is_wp_error( $in ) ) {
			return $in;
		}
		if ( ! empty( $raw['page_id'] ) ) {
			$target = get_post( (int) $raw['page_id'] );
			if ( ! $target || 'page' !== $target->post_type || ! TWD_SK_Page::is_kit_page( (int) $raw['page_id'] ) ) {
				return self::err( 'twd_sk_bad_input', 'That page was not found, or it does not use the kit.' );
			}
		}
		$html = self::draft_page_html( $in );
		if ( is_wp_error( $html ) ) {
			return $html;
		}

		$existing = isset( $raw['page_id'] ) ? (int) $raw['page_id'] : 0;
		$note     = 'AI draft from the ' . TWD_SK_Mode::w( 'facts' ) . ' (' . $in['type'] . ')';
		if ( $existing > 0 ) {
			// Fill a page that already exists (for example an outline made from a site brief) as a new version.
			$id    = $existing;
			$saved = TWD_SK_Store::save( $id, $html, array( 'base_version' => TWD_SK_Store::get_current_version_id( $id ), 'note' => $note ) );
		} else {
			$id = TWD_SK_Template::create_page( $in['title'], 'blank' );
			if ( is_wp_error( $id ) ) {
				return $id;
			}
			$saved = TWD_SK_Store::save( $id, $html, array( 'base_version' => 0, 'note' => $note ) );
		}
		if ( is_wp_error( $saved ) ) {
			return $saved;
		}
		$levels = TWD_SK_Sanitizer::find_leftovers_by_level( TWD_SK_Store::get_current( $id ) );
		return array(
			'id'             => (int) $id,
			'title'          => (string) get_post( $id )->post_title,
			'url'            => get_permalink( $id ),
			'sections'       => count( TWD_SK_Sections::split( TWD_SK_Store::get_current( $id ) )['sections'] ),
			'must_count'     => TWD_SK_Report::leftover_total( $levels['must'] ),
			'check_count'    => TWD_SK_Report::leftover_total( $levels['check'] ),
		);
	}
}
