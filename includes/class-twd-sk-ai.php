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
		$out[] = 'Use only the facts in the page and in the facts the therapist supplied. Where the instruction needs a fact you do not have, write [PLACEHOLDER: what is needed].';
		return $out;
	}

	public static function system_prompt( $mode ) {
		$out   = array();
		$out[] = 'You edit part of one page of a therapist\'s website. The page is HTML made from a fixed set of components.';
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
		if ( '' !== $facts ) {
			$out[] = '';
			$out[] = 'Facts the therapist supplied (the only new facts you may use):';
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
		if ( strlen( $facts ) > self::MAX_FACTS ) {
			return self::err( 'twd_sk_bad_input', 'The facts are too long. Keep them under ' . self::MAX_FACTS . ' characters.' );
		}
		$instruction = TWD_SK_Profile::text( $instruction, self::MAX_INSTRUCTION );
		$facts       = self::lines( $facts, self::MAX_FACTS );

		$current = TWD_SK_Store::get_current( (int) $page_id );
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
}
