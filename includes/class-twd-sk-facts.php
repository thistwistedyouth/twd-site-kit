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
			return new WP_Error( 'twd_sk_bad_facts', 'The ' . TWD_SK_Mode::w( 'facts' ) . ' must be text.' );
		}
		$clean = self::clean( $text );
		$len   = function_exists( 'mb_strlen' ) ? mb_strlen( $clean, 'UTF-8' ) : strlen( $clean );
		if ( $len > self::MAX ) {
			return new WP_Error( 'twd_sk_bad_facts', 'The ' . TWD_SK_Mode::w( 'facts' ) . ' are too long (' . $len . ' characters). Keep them under ' . self::MAX . '.' );
		}
		$now = function_exists( 'current_time' ) ? (string) current_time( 'mysql', true ) : gmdate( 'Y-m-d H:i:s' );
		update_option( self::OPTION, array( 'text' => $clean, 'updated' => $now ), false );
		return array( 'text' => $clean, 'length' => $len, 'updated' => $now );
	}

	/** The outline offered to start from. Plain words, no sample facts. */
	public static function template() {
		return implode( "\n", TWD_SK_Mode::w( 'facts_template' ) ) . "\n";
	}

	/**
	 * The block handed to the AI: the master document plus the structured site details that are real
	 * (no placeholders). Empty string when there is nothing to say.
	 */
	public static function prompt_block() {
		$parts = array();
		$text  = self::get();
		if ( '' !== $text ) {
			$parts[] = TWD_SK_Mode::w( 'Facts' ) . ' (written by the ' . TWD_SK_Mode::w( 'person' ) . ", the only source for new claims):\n" . $text;
		}
		$p     = TWD_SK_Profile::get();
		$lines = array();
		$map   = array(
			'site_name'   => TWD_SK_Mode::w( 'site_name_label' ),
			'person_name' => TWD_SK_Mode::w( 'person_name_label' ),
			'person_job'  => 'Job title',
			'phone'       => 'Phone',
			'email'       => 'Email',
			'area_served' => TWD_SK_Mode::w( 'area_label' ),
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
				$lines[] = TWD_SK_Mode::w( 'registration_line' ) . $r;
			}
		}
		if ( $lines ) {
			$parts[] = 'Site details (real, supplied by the ' . TWD_SK_Mode::w( 'person' ) . "):\n" . implode( "\n", $lines );
		}
		return implode( "\n\n", $parts );
	}
}
