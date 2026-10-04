<?php
/**
 * TWD_SK_Facts: the practice facts, one plain-text master document about the person and their practice.
 *
 * It is the single source the AI may draw on for anything new it writes: who they are, who they
 * help, what they offer, their approach, qualifications and registrations in exactly the wording
 * the person supplied, fees, where and when, questions people ask, words they like, things they
 * never say. It is stored in one option on the client's own site, edited by administrators only,
 * and is never printed to visitors, never put in a page, the search copy or the structured data.
 *
 * It is plain text, with line breaks kept. Tags are removed, long dashes are replaced, control
 * characters are removed, and it is capped. Nothing about the people a practitioner treats belongs
 * here, only facts about the practice.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class TWD_SK_Facts {

	const OPTION = 'twd_sk_facts';
	const MAX    = 20000;

	/** The saved text, or an empty string. */
	public static function get() {
		$stored = function_exists( 'get_option' ) ? get_option( self::OPTION, array() ) : array();
		if ( is_array( $stored ) && isset( $stored['text'] ) && is_string( $stored['text'] ) ) {
			return $stored['text'];
		}
		return '';
	}

	public static function updated() {
		$stored = function_exists( 'get_option' ) ? get_option( self::OPTION, array() ) : array();
		return is_array( $stored ) && isset( $stored['updated'] ) ? (string) $stored['updated'] : '';
	}

	/** Plain text that keeps line breaks. Returns the cleaned text. */
	public static function clean( $value ) {
		$value = str_replace( array( "\r\n", "\r" ), "\n", wp_strip_all_tags( (string) $value ) );
		$value = preg_replace( '/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F]/', '', $value );
		$rows  = array();
		$blank = 0;
		foreach ( explode( "\n", $value ) as $row ) {
			$row = rtrim( preg_replace( '/[ \t]+/u', ' ', $row ) );
			if ( '' === trim( $row ) ) {
				$blank++;
				if ( $blank > 1 ) {
					continue;
				}
				$rows[] = '';
				continue;
			}
			$blank  = 0;
			$rows[] = $row;
		}
		return trim( TWD_SK_Sanitizer::strip_dashes( implode( "\n", $rows ) ) );
	}

	/**
	 * Save the practice facts.
	 *
	 * @return array|WP_Error { text, length, updated }
	 */
	public static function save( $text ) {
		if ( ! is_string( $text ) ) {
			return new WP_Error( 'twd_sk_bad_facts', 'The practice facts must be text.' );
		}
		$clean = self::clean( $text );
		$len   = function_exists( 'mb_strlen' ) ? mb_strlen( $clean, 'UTF-8' ) : strlen( $clean );
		if ( $len > self::MAX ) {
			return new WP_Error( 'twd_sk_bad_facts', 'The practice facts are too long (' . $len . ' characters). Keep them under ' . self::MAX . '.' );
		}
		$now = function_exists( 'current_time' ) ? (string) current_time( 'mysql', true ) : gmdate( 'Y-m-d H:i:s' );
		update_option( self::OPTION, array( 'text' => $clean, 'updated' => $now ), false );
		return array( 'text' => $clean, 'length' => $len, 'updated' => $now );
	}

	/** The outline offered to start from. Plain words, no sample facts. */
	public static function template() {
		return implode( "\n", array(
			'WHO I AM',
			'Your name, how you like to be described, and your story in a few lines.',
			'',
			'WHO I HELP',
			'The people you work with, and what they usually come to you for.',
			'',
			'WHAT I OFFER',
			'Each kind of work you do, one line each: therapy, coaching, training, groups, anything else.',
			'',
			'MY APPROACH',
			'How you work, in your own words.',
			'',
			'QUALIFICATIONS, REGISTRATIONS AND MEMBERSHIPS',
			'Write exactly what you are entitled to say, in the wording you want used. Leave this empty if there is nothing.',
			'',
			'FEES, SESSIONS AND HOW IT WORKS',
			'Session length, fees, how a first session goes, cancellations. Only what you want stated.',
			'',
			'WHERE AND WHEN',
			'Where you work, online or in person, and your usual days and times.',
			'',
			'HOW TO CONTACT ME',
			'How people should get in touch.',
			'',
			'QUESTIONS PEOPLE ASK',
			'The questions you hear most, with your answers.',
			'',
			'WORDS AND PHRASES I LIKE',
			'Wording that sounds like you.',
			'',
			'THINGS I NEVER SAY OR PROMISE',
			'Claims, words or promises that must not appear.',
		) ) . "\n";
	}

	/**
	 * The block handed to the AI: the master document plus the structured site details that are real
	 * (no placeholders). Empty string when there is nothing to say.
	 */
	public static function prompt_block() {
		$parts = array();
		$text  = self::get();
		if ( '' !== $text ) {
			$parts[] = "Practice facts (written by the therapist, the only source for new claims):\n" . $text;
		}
		$p     = TWD_SK_Profile::get();
		$lines = array();
		$map   = array(
			'site_name'   => 'Practice name',
			'person_name' => 'Therapist name',
			'person_job'  => 'Job title',
			'phone'       => 'Phone',
			'email'       => 'Email',
			'area_served' => 'Where they work',
		);
		foreach ( $map as $key => $label ) {
			if ( is_string( $p[ $key ] ) && '' !== trim( $p[ $key ] ) && false === stripos( $p[ $key ], '[PLACEHOLDER' ) ) {
				$lines[] = $label . ': ' . $p[ $key ];
			}
		}
		foreach ( (array) $p['address'] as $a ) {
			if ( is_string( $a ) && '' !== trim( $a ) && false === stripos( $a, '[PLACEHOLDER' ) ) {
				$lines[] = 'Address: ' . $a;
			}
		}
		foreach ( (array) $p['registration'] as $r ) {
			if ( is_string( $r ) && '' !== trim( $r ) && false === stripos( $r, '[PLACEHOLDER' ) ) {
				$lines[] = 'Registration or membership line (use exactly): ' . $r;
			}
		}
		if ( $lines ) {
			$parts[] = "Site details (real, supplied by the therapist):\n" . implode( "\n", $lines );
		}
		return implode( "\n\n", $parts );
	}
}
