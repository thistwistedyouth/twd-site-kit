<?php
/**
 * TWD_SK_Style (0.14.0): restyle a built site without touching its words, pictures or structure.
 *
 * A style kit is pack-shaped: a set of tokens (the same names and checks as a pack file, any subset)
 * plus, optionally, a pack to start from and the header and footer layout settings. Applying one is
 * level 1: tokens and layout settings only, which is lossless, because every page is sanitised HTML
 * with classes and the look comes from the stylesheet and the tokens.
 *
 *   snapshot()  keeps the current pack, overrides and layout settings under a name (up to KEEP of them).
 *   apply()     with dry_run says what would change and what the contrast check thinks; for real it takes
 *               a snapshot first, then saves the pack, the overrides and the layout settings.
 *   revert()    puts the newest snapshot back and forgets it.
 *   swap()      level 2: changes one section's layout to another of the same component on one page, only
 *               when the new layout can hold every piece the section has (the registry says which classes
 *               each layout uses); anything that cannot map is reported and nothing is written.
 *
 * Preview: an administrator may add ?twd_sk_style=PACK to any page address to see the whole site in that
 * pack without saving anything (no cache, visitors never see it, off in safe mode).
 *
 * Everything goes through the existing validators (TWD_SK_Packs::validate_tokens, TWD_SK_Chrome::validate,
 * TWD_SK_Contrast::check) and the store. Kits live outside the plugin: only generic packs ship in packs/.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class TWD_SK_Style {

	const OPTION_SNAPSHOTS = 'twd_sk_style_snapshots';
	const KEEP             = 5;
	const QUERY_VAR        = 'twd_sk_style';
	const SCHEMA           = 'twd-style-kit/1';

	/** The pack an administrator is previewing on this request, or ''. */
	private static $preview = '';

	public static function init() {
		add_action( 'init', array( __CLASS__, 'read_preview' ), 5 );
	}

	// -- Preview without saving --------------------------------------------------------------

	/** Honour ?twd_sk_style=PACK for a signed-in administrator only, never cached, never for visitors. */
	public static function read_preview() {
		self::$preview = '';
		if ( TWD_SK_Safe::on() || is_admin() || ! isset( $_GET[ self::QUERY_VAR ] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended
			return;
		}
		$slug = sanitize_text_field( (string) $_GET[ self::QUERY_VAR ] ); // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		if ( ! is_user_logged_in() || ! current_user_can( 'manage_options' ) || ! isset( TWD_SK_Packs::packs()[ $slug ] ) ) {
			return;
		}
		self::$preview = $slug;
		if ( function_exists( 'nocache_headers' ) ) {
			nocache_headers();
		}
	}

	public static function preview_slug() {
		return self::$preview;
	}

	/** For tests. */
	public static function reset_preview() {
		self::$preview = '';
	}

	// -- Kits ------------------------------------------------------------------------------------

	/**
	 * Read a kit from data: { schema?, name?, pack?, tokens?, chrome? }. Unknown keys are ignored and reported.
	 *
	 * @return array|WP_Error { name, pack, tokens, chrome, notes[] }
	 */
	public static function read_kit( $data ) {
		if ( ! is_array( $data ) ) {
			return new WP_Error( 'twd_sk_bad_input', 'A style kit is a set of fields: pack, tokens, chrome.' );
		}
		if ( isset( $data['schema'] ) && self::SCHEMA !== $data['schema'] ) {
			return new WP_Error( 'twd_sk_bad_input', 'That file is not a ' . self::SCHEMA . ' style kit.' );
		}
		$notes = array();
		$kit   = array( 'name' => '', 'pack' => '', 'tokens' => array(), 'chrome' => array(), 'notes' => array() );
		foreach ( array_keys( $data ) as $k ) {
			if ( ! in_array( $k, array( 'schema', 'name', 'pack', 'tokens', 'chrome', 'description' ), true ) ) {
				$notes[] = 'Unknown field "' . TWD_SK_Profile::text( (string) $k, 40 ) . '" was ignored.';
			}
		}
		$kit['name'] = isset( $data['name'] ) ? TWD_SK_Profile::text( $data['name'], 80 ) : '';
		if ( isset( $data['pack'] ) && '' !== $data['pack'] ) {
			if ( ! is_string( $data['pack'] ) || ! isset( TWD_SK_Packs::packs()[ $data['pack'] ] ) ) {
				return new WP_Error( 'twd_sk_unknown_pack', 'There is no style pack called "' . TWD_SK_Profile::text( (string) $data['pack'], 40 ) . '". Available: ' . implode( ', ', array_keys( TWD_SK_Packs::packs() ) ) . '.' );
			}
			$kit['pack'] = $data['pack'];
		}
		if ( isset( $data['tokens'] ) ) {
			$check = TWD_SK_Packs::validate_tokens( $data['tokens'], false );
			if ( $check['errors'] ) {
				return new WP_Error( 'twd_sk_bad_tokens', 'Some values in the kit are not allowed: ' . implode( ' ', $check['errors'] ) );
			}
			$kit['tokens'] = $check['valid'];
		}
		if ( isset( $data['chrome'] ) ) {
			$in = is_array( $data['chrome'] ) ? $data['chrome'] : array();
			// A kit says "header" and "footer"; the settings call them header_variant and footer_variant.
			if ( isset( $in['header'] ) ) {
				$in['header_variant'] = $in['header'];
				unset( $in['header'] );
			}
			if ( isset( $in['footer'] ) ) {
				$in['footer_variant'] = $in['footer'];
				unset( $in['footer'] );
			}
			unset( $in['placement'] ); // Who places the header is never part of a look.
			$check = TWD_SK_Chrome::validate( $in );
			if ( $check['errors'] ) {
				return new WP_Error( 'twd_sk_bad_chrome', 'The header and footer settings in the kit: ' . implode( ' ', $check['errors'] ) );
			}
			$kit['chrome'] = $check['valid'];
		}
		$kit['notes'] = $notes;
		return $kit;
	}

	/** The current look as a kit (what a snapshot holds). */
	public static function current() {
		$c = TWD_SK_Chrome::settings();
		return array(
			'pack'   => TWD_SK_Packs::active_slug(),
			'tokens' => TWD_SK_Packs::overrides(),
			'chrome' => array(
				'header_variant' => $c['header_variant'],
				'footer_variant' => $c['footer_variant'],
				'footer_columns' => $c['footer_columns'],
				'sticky'         => $c['sticky'],
				'shrink'         => $c['shrink'],
				'front_logo'     => $c['front_logo'],
				'show_button'    => $c['show_button'],
				'show_strip'     => $c['show_strip'],
			),
		);
	}

	// -- Snapshots ---------------------------------------------------------------------------------

	public static function snapshots() {
		$list = get_option( self::OPTION_SNAPSHOTS, array() );
		return is_array( $list ) ? array_values( array_filter( $list, 'is_array' ) ) : array();
	}

	/** Keep the current look under a name. Newest first, KEEP at most. */
	public static function snapshot( $name = '' ) {
		$snap = self::current();
		$snap['name'] = TWD_SK_Profile::text( $name, 60 );
		$snap['at']   = gmdate( 'Y-m-d H:i:s' );
		$list         = self::snapshots();
		array_unshift( $list, $snap );
		update_option( self::OPTION_SNAPSHOTS, array_slice( $list, 0, self::KEEP ), false );
		return $snap;
	}

	/**
	 * Apply a kit.
	 *
	 * @param array $kit  From read_kit().
	 * @param array $opts dry_run (bool), snapshot_name (string).
	 * @return array|WP_Error { changes[], contrast, blocking[], applied (bool), snapshot? }
	 */
	public static function apply( $kit, $opts = array() ) {
		$dry     = ! empty( $opts['dry_run'] );
		$packs   = TWD_SK_Packs::packs();
		$now     = self::current();
		$target  = '' !== $kit['pack'] ? $kit['pack'] : $now['pack'];
		$changes = array();
		if ( $target !== $now['pack'] ) {
			$changes[] = 'Style pack: ' . $now['pack'] . ' to ' . $target . ' (the old colour changes are cleared).';
		}
		// On a pack switch the kit's tokens stand alone; on the same pack they go on top of the current overrides.
		$overrides = $target === $now['pack'] ? array_merge( $now['tokens'], $kit['tokens'] ) : $kit['tokens'];
		$base      = $packs[ $target ]['tokens'];
		$store     = array();
		foreach ( $overrides as $name => $value ) {
			if ( ! isset( $base[ $name ] ) || strtolower( $base[ $name ] ) !== strtolower( $value ) ) {
				$store[ $name ] = $value;
			}
		}
		$before_eff = array_merge( $packs[ $now['pack'] ]['tokens'], $now['tokens'] );
		$effective  = array_merge( $base, $store );
		foreach ( TWD_SK_Packs::token_names() as $name ) {
			if ( isset( $before_eff[ $name ], $effective[ $name ] ) && strtolower( $before_eff[ $name ] ) !== strtolower( $effective[ $name ] ) ) {
				$changes[] = $name . ': ' . $before_eff[ $name ] . ' to ' . $effective[ $name ];
			}
		}
		foreach ( $kit['chrome'] as $k => $v ) {
			if ( ! array_key_exists( $k, $now['chrome'] ) || $now['chrome'][ $k ] !== $v ) {
				$changes[] = str_replace( '_', ' ', $k ) . ': ' . self::word( isset( $now['chrome'][ $k ] ) ? $now['chrome'][ $k ] : '' ) . ' to ' . self::word( $v );
			}
		}
		$contrast = TWD_SK_Contrast::check( $effective );
		$blocking = array_map( function ( $b ) {
			return $b['label'] . ' (' . $b['ratio'] . ':1, needs 4.5:1)';
		}, $contrast['blocking'] );
		$out = array( 'changes' => $changes, 'contrast' => $contrast, 'blocking' => $blocking, 'applied' => false, 'pack' => $target, 'overrides' => $store );
		if ( $dry ) {
			return $out;
		}
		if ( $blocking ) {
			return new WP_Error( 'twd_sk_contrast', 'Some text would be hard to read, so nothing was changed: ' . implode( '; ', $blocking ) . '.', $out );
		}
		if ( ! $changes ) {
			$out['note'] = 'Nothing to change: the site already looks like this.';
			return $out;
		}
		$out['snapshot'] = self::snapshot( isset( $opts['snapshot_name'] ) ? $opts['snapshot_name'] : 'before ' . ( '' !== $kit['name'] ? $kit['name'] : $target ) );
		if ( $target !== $now['pack'] ) {
			$r = TWD_SK_Packs::set_active( $target );
			if ( is_wp_error( $r ) ) {
				return $r;
			}
		}
		$r = TWD_SK_Packs::set_overrides( $store );
		if ( is_wp_error( $r ) ) {
			return $r;
		}
		if ( $kit['chrome'] ) {
			$r = TWD_SK_Chrome::save_settings( $kit['chrome'] );
			if ( is_wp_error( $r ) ) {
				return $r;
			}
		}
		$out['applied'] = true;
		return $out;
	}

	/** Put the newest snapshot back and forget it. */
	public static function revert() {
		$list = self::snapshots();
		if ( ! $list ) {
			return new WP_Error( 'twd_sk_no_snapshot', 'There is no earlier look to go back to.' );
		}
		$snap = array_shift( $list );
		$r    = TWD_SK_Packs::set_active( $snap['pack'] );
		if ( is_wp_error( $r ) ) {
			return $r;
		}
		$r = TWD_SK_Packs::set_overrides( is_array( $snap['tokens'] ) ? $snap['tokens'] : array() );
		if ( is_wp_error( $r ) ) {
			return $r;
		}
		if ( ! empty( $snap['chrome'] ) ) {
			$r = TWD_SK_Chrome::save_settings( $snap['chrome'] );
			if ( is_wp_error( $r ) ) {
				return $r;
			}
		}
		update_option( self::OPTION_SNAPSHOTS, $list, false );
		return $snap;
	}

	private static function word( $v ) {
		if ( is_bool( $v ) ) {
			return $v ? 'on' : 'off';
		}
		return '' === (string) $v ? 'the style\'s own' : (string) $v;
	}

	// -- Level 2: a section's layout -----------------------------------------------------------------

	/** Every element class a layout's skeleton uses, as a set. */
	private static function layout_classes( $skeleton ) {
		preg_match_all( '/class="([^"]*)"/', $skeleton, $m );
		$out = array();
		foreach ( $m[1] as $list ) {
			foreach ( preg_split( '/\s+/', trim( $list ) ) as $c ) {
				if ( '' !== $c ) {
					$out[ $c ] = true;
				}
			}
		}
		return $out;
	}

	/**
	 * Change the layout of the sections of one component on a page, when the new layout can hold every
	 * piece they have. Content is never rewritten: only the modifier class on the section changes.
	 *
	 * @param string $html      The page.
	 * @param string $component A registry id (text, image_text, ...).
	 * @param string $from      The current modifier class, or '' for the standard layout.
	 * @param string $to        The wanted modifier class, or '' for the standard layout.
	 * @return array|WP_Error { html, changed (int), cannot[] (plain sentences) }
	 */
	public static function swap( $html, $component, $from, $to ) {
		$c = TWD_SK_Registry::get( $component );
		if ( ! $c ) {
			return new WP_Error( 'twd_sk_bad_input', 'There is no section kind called that.' );
		}
		foreach ( array( $from, $to ) as $v ) {
			if ( '' !== $v && ! isset( $c['variants'][ $v ] ) ) {
				return new WP_Error( 'twd_sk_bad_input', $c['label'] . ' has no layout called "' . TWD_SK_Profile::text( $v, 60 ) . '".' );
			}
		}
		$root       = 'twd-sk-' . str_replace( '_', '-', $component );
		$target_ok  = self::layout_classes( '' === $to ? $c['skeleton'] : $c['variants'][ $to ]['skeleton'] );
		$modifiers  = array_keys( $c['variants'] );
		$split      = TWD_SK_Sections::split( $html );
		$changed    = 0;
		$cannot     = array();
		$out        = array();
		foreach ( $split['sections'] as $i => $piece ) {
			if ( ! preg_match( '/^<section\b[^>]*class="([^"]*)"/', $piece, $m ) ) {
				$out[] = $piece;
				continue;
			}
			$classes = preg_split( '/\s+/', trim( $m[1] ) );
			if ( ! in_array( $root, $classes, true ) ) {
				$out[] = $piece;
				continue;
			}
			$current = '';
			foreach ( $classes as $cls ) {
				if ( in_array( $cls, $modifiers, true ) ) {
					$current = $cls;
				}
			}
			if ( $current !== $from ) {
				$out[] = $piece;
				continue;
			}
			// Every element class this section uses must exist in the target layout.
			$used    = self::layout_classes( $piece );
			$missing = array();
			foreach ( array_keys( $used ) as $cls ) {
				if ( in_array( $cls, $modifiers, true ) || 0 !== strpos( $cls, $root . '__' ) ) {
					continue;
				}
				if ( ! isset( $target_ok[ $cls ] ) ) {
					$missing[] = $cls;
				}
			}
			if ( $missing ) {
				$cannot[] = 'Section ' . ( $i + 1 ) . ' keeps its layout: the ' . ( '' === $to ? 'standard' : $to ) . ' layout has no place for ' . implode( ', ', $missing ) . '.';
				$out[]    = $piece;
				continue;
			}
			$new = array_values( array_filter( $classes, function ( $cls ) use ( $modifiers ) {
				return ! in_array( $cls, $modifiers, true );
			} ) );
			if ( '' !== $to ) {
				array_splice( $new, 1, 0, array( $to ) );
			}
			$out[] = preg_replace( '/^(<section\b[^>]*class=")[^"]*(")/', '${1}' . implode( ' ', $new ) . '${2}', $piece, 1 );
			$changed++;
		}
		$joined = '';
		foreach ( $out as $i => $piece ) {
			$joined .= $split['gaps'][ $i ] . $piece;
		}
		$joined .= $split['gaps'][ count( $out ) ];
		return array( 'html' => $joined, 'changed' => $changed, 'cannot' => $cannot );
	}
}
