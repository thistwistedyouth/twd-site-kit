<?php
/**
 * TWD_SK_Sets: starter sets per kind of site (0.8.4).
 *
 * A starter set is a data file in starters/sets/ (schema twd-starter-set/1): a name, the site type
 * it suits, a description and a list of pages, each with a page type, a title, a topic when the
 * type needs one, and optionally its own sections. Adding a kind of site means adding a file, not
 * code. Every page goes through the same checks as a brief's page; a file or page that fails is
 * left out and reported (the site report lists it), never half used.
 *
 * Sets hold no client wording: titles and topics are generic, everything else is written later
 * from the facts.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class TWD_SK_Sets {

	const SCHEMA = 'twd-starter-set/1';

	private static $cache  = null;
	private static $errors = array();

	/** The folder of set files. Filter twd_sk_sets_dir to use another (a site's own sets, or tests). */
	public static function dir() {
		$dir = function_exists( 'apply_filters' ) ? apply_filters( 'twd_sk_sets_dir', TWD_SK_PATH . 'starters/sets/' ) : TWD_SK_PATH . 'starters/sets/';
		return rtrim( (string) $dir, '/' ) . '/';
	}

	/** slug => { slug, name, mode, description, pages: [ checked inputs ] }. */
	public static function all() {
		if ( null !== self::$cache ) {
			return self::$cache;
		}
		self::$cache  = array();
		self::$errors = array();
		$files        = glob( self::dir() . '*.json' );
		foreach ( is_array( $files ) ? $files : array() as $file ) {
			$name = basename( $file );
			$data = json_decode( (string) file_get_contents( $file ), true );
			if ( ! is_array( $data ) || ! isset( $data['schema'] ) || self::SCHEMA !== $data['schema'] ) {
				self::$errors[] = $name . ': not a starter set file.';
				continue;
			}
			$slug = isset( $data['slug'] ) && is_string( $data['slug'] ) ? $data['slug'] : '';
			if ( 1 !== preg_match( '/^[a-z][a-z0-9-]{1,40}$/', $slug ) || $slug . '.json' !== $name ) {
				self::$errors[] = $name . ': the slug must match the file name.';
				continue;
			}
			if ( ! isset( $data['mode'] ) || ! is_string( $data['mode'] ) || ! isset( TWD_SK_Mode::modes()[ $data['mode'] ] ) ) {
				self::$errors[] = $name . ': unknown site type.';
				continue;
			}
			$pages = array();
			$bad   = '';
			foreach ( isset( $data['pages'] ) && is_array( $data['pages'] ) ? $data['pages'] : array() as $i => $pg ) {
				$in = is_array( $pg ) ? TWD_SK_Recipes::inputs( $pg ) : new WP_Error( 'twd_sk_bad_input', 'not understood' );
				if ( is_wp_error( $in ) ) {
					$bad = 'page ' . ( $i + 1 ) . ': ' . $in->get_error_message();
					break;
				}
				$in['seo'] = array();
				$pages[]   = $in;
			}
			if ( '' !== $bad || ! $pages || count( $pages ) > TWD_SK_Brief::MAX_PAGES ) {
				self::$errors[] = $name . ': ' . ( '' !== $bad ? $bad : 'needs between 1 and ' . TWD_SK_Brief::MAX_PAGES . ' pages.' );
				continue;
			}
			self::$cache[ $slug ] = array(
				'slug'        => $slug,
				'name'        => isset( $data['name'] ) && is_string( $data['name'] ) ? TWD_SK_Profile::text( $data['name'], 80 ) : $slug,
				'mode'        => $data['mode'],
				'description' => isset( $data['description'] ) && is_string( $data['description'] ) ? TWD_SK_Profile::text( $data['description'], 300 ) : '',
				'pages'       => $pages,
			);
		}
		return self::$cache;
	}

	public static function get( $slug ) {
		$all = self::all();
		return is_string( $slug ) && isset( $all[ $slug ] ) ? $all[ $slug ] : null;
	}

	/** Problems found reading the set files. */
	public static function errors() {
		self::all();
		return self::$errors;
	}

	/** For the browser: slug => { name, mode, description, pages (titles) }. */
	public static function summary() {
		$out = array();
		foreach ( self::all() as $slug => $s ) {
			$out[ $slug ] = array(
				'name'        => $s['name'],
				'mode'        => $s['mode'],
				'description' => $s['description'],
				'pages'       => array_map( function ( $p ) {
					return $p['title'];
				}, $s['pages'] ),
			);
		}
		return $out;
	}

	/** For tests. */
	public static function reset() {
		self::$cache  = null;
		self::$errors = array();
	}
}
