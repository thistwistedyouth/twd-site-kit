<?php
/**
 * TWD_SK_Brief: the site brief, one JSON file that describes a site, exported from a site or imported into one.
 *
 * The builder gathers the information in a conversation (the interview prompt below), pastes the JSON
 * into the Site tab, checks what it would do, and applies it. The same file can be exported later and
 * added to. The brief holds practice facts and settings only: no credentials, no keys, no HTML, no
 * pictures (pictures live in the media library and are a checklist, not data).
 *
 * Nothing in a brief is trusted. Every value goes through the validators the Site tab saves with, an
 * unknown key is reported and ignored, and a value that is invalid is dropped with a plain sentence.
 * Content that is already filled in is never overwritten unless the person ticks overwrite.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class TWD_SK_Brief {

	const SCHEMA    = 'twd-site-brief/1';
	const MAX_BYTES = 200 * 1000;
	const MAX_PAGES = 20;

	private static $top = array( 'schema', 'mode', 'site', 'header', 'footer', 'style', 'facts', 'pages', 'note' );

	// -- Export ---------------------------------------------------------------

	public static function export() {
		$p = TWD_SK_Profile::get();
		$c = TWD_SK_Chrome::settings();
		return array(
			'schema' => self::SCHEMA,
			'mode'   => TWD_SK_Mode::get(),
			'site'   => array(
				'name'         => $p['site_name'],
				'person_name'  => $p['person_name'],
				'person_job'   => $p['person_job'],
				'phone'        => $p['phone'],
				'email'        => $p['email'],
				'address'      => $p['address'],
				'area_served'  => $p['area_served'],
				'registration' => $p['registration'],
				'same_as'      => $p['same_as'],
			),
			'header' => array(
				'menu'        => $p['menu'],
				'cta_label'   => $p['cta_label'],
				'cta_url'     => $p['cta_url'],
				'layout'      => $c['header_variant'],
				'sticky'      => (bool) $c['sticky'],
				'shrink'      => (bool) $c['shrink'],
				'front_logo'  => (bool) $c['front_logo'],
				'overlay'     => (bool) $c['overlay'],
				'wordmark'    => (bool) $c['wordmark'],
				'wordmark_scrolled_name' => (bool) $c['wordmark_scrolled_name'],
				'menu_marker' => $c['menu_marker'],
				'logo_line'   => $p['logo_line'],
				'show_button' => (bool) $c['show_button'],
				'show_strip'  => (bool) $c['show_strip'],
			),
			'footer' => array(
				'text'    => $p['footer_text'],
				'legal'   => $p['legal'],
				'layout'  => $c['footer_variant'],
				'columns' => (int) $c['footer_columns'],
			),
			'style'  => array(
				'pack'   => TWD_SK_Packs::active_slug(),
				'tokens' => (object) TWD_SK_Packs::overrides(),
			),
			'facts'  => TWD_SK_Facts::get(),
			'pages'  => array(),
		);
	}

	// -- Reading a brief --------------------------------------------------------

	/** Text (maybe fenced, maybe with a sentence around it) to an array. */
	public static function parse( $text ) {
		if ( ! is_string( $text ) || '' === trim( $text ) ) {
			return new WP_Error( 'twd_sk_bad_brief', 'Paste the site brief first.' );
		}
		if ( strlen( $text ) > self::MAX_BYTES ) {
			return new WP_Error( 'twd_sk_bad_brief', 'That is larger than a site brief can be (' . (int) ( self::MAX_BYTES / 1000 ) . ' KB).' );
		}
		$text  = TWD_SK_AI::strip_fence( $text );
		$start = strpos( $text, '{' );
		$end   = strrpos( $text, '}' );
		if ( false === $start || false === $end || $end <= $start ) {
			return new WP_Error( 'twd_sk_bad_brief', 'That does not look like a site brief. It should be one JSON object.' );
		}
		$json = substr( $text, $start, $end - $start + 1 );
		$data = json_decode( $json, true );
		if ( ! is_array( $data ) ) {
			return new WP_Error( 'twd_sk_bad_brief', 'The site brief is not valid JSON. ' . self::json_problem( substr( $text, $start ) ) );
		}
		if ( ! isset( $data['schema'] ) || self::SCHEMA !== $data['schema'] ) {
			return new WP_Error( 'twd_sk_bad_brief', 'This is not a site brief file (the "schema" line should say ' . self::SCHEMA . ').' );
		}
		// The facts may be a list of lines (0.9.3): easier to read, edit and copy than one very long line.
		if ( isset( $data['facts'] ) && is_array( $data['facts'] ) && array_values( $data['facts'] ) === $data['facts'] ) {
			$lines = array();
			foreach ( $data['facts'] as $line ) {
				if ( ! is_string( $line ) ) {
					$lines = null;
					break;
				}
				$lines[] = $line;
			}
			if ( null !== $lines ) {
				$data['facts'] = implode( "\n", $lines );
			}
		}
		return $data;
	}

	/**
	 * Where a brief that is not valid JSON goes wrong, in plain words: the line and the text there,
	 * or that it ends early (only part of it was pasted). A small JSON reader that stops at the first fault.
	 */
	public static function json_problem( $text ) {
		$at = self::json_fault( $text );
		if ( null === $at ) {
			return 'Check for a missing comma or quote, or ask the AI to produce it again.';
		}
		if ( $at >= strlen( rtrim( $text ) ) ) {
			return 'It ends too early, so only part of it was pasted. Copy the whole file again, or use Choose the brief file.';
		}
		$line   = substr_count( substr( $text, 0, $at ), "\n" ) + 1;
		$near   = preg_replace( '/\s+/', ' ', substr( $text, max( 0, $at - 30 ), 60 ) );
		$curly  = 1 === preg_match( '/\x{201C}|\x{201D}|\x{2018}|\x{2019}/u', substr( $text, max( 0, $at - 4 ), 8 ) );
		$prefix = 'The problem is on line ' . $line . ', near: ' . TWD_SK_Profile::text( $near, 80 ) . '. ';
		if ( $curly ) {
			return $prefix . 'It has curly quotes there (from Word or a phone). Use straight quotes, or use Choose the brief file.';
		}
		return $prefix . 'Check for a missing comma or quote there. If the brief was copied, use Choose the brief file instead.';
	}

	/** Byte offset of the first fault in $s read as JSON, or null when it reads cleanly. */
	private static function json_fault( $s ) {
		$n  = strlen( $s );
		$i  = 0;
		$ws = function () use ( &$i, $s, $n ) {
			while ( $i < $n && false !== strpos( " \t\r\n", $s[ $i ] ) ) {
				$i++;
			}
		};
		$str = function () use ( &$i, $s, $n ) {
			if ( $i >= $n || '"' !== $s[ $i ] ) {
				return false;
			}
			$i++;
			while ( $i < $n ) {
				$c = $s[ $i ];
				if ( '"' === $c ) {
					$i++;
					return true;
				}
				if ( '\\' === $c ) {
					$i += 2;
					continue;
				}
				if ( ord( $c ) < 0x20 ) {
					return false;
				}
				$i++;
			}
			return false;
		};
		$value = null;
		$value = function ( $depth ) use ( &$value, &$i, $s, $n, $ws, $str ) {
			$ws();
			if ( $i >= $n || $depth > 64 ) {
				return false;
			}
			$c = $s[ $i ];
			if ( '{' === $c || '[' === $c ) {
				$close = '{' === $c ? '}' : ']';
				$i++;
				$ws();
				if ( $i < $n && $close === $s[ $i ] ) {
					$i++;
					return true;
				}
				while ( true ) {
					if ( '}' === $close ) {
						$ws();
						if ( ! $str() ) {
							return false;
						}
						$ws();
						if ( $i >= $n || ':' !== $s[ $i ] ) {
							return false;
						}
						$i++;
					}
					if ( ! $value( $depth + 1 ) ) {
						return false;
					}
					$ws();
					if ( $i < $n && ',' === $s[ $i ] ) {
						$i++;
						continue;
					}
					if ( $i < $n && $close === $s[ $i ] ) {
						$i++;
						return true;
					}
					return false;
				}
			}
			if ( '"' === $c ) {
				return $str();
			}
			if ( preg_match( '/\G(?:-?\d+(?:\.\d+)?(?:[eE][+-]?\d+)?|true|false|null)/', $s, $m, 0, $i ) ) {
				$i += strlen( $m[0] );
				return true;
			}
			return false;
		};
		$ok = $value( 0 );
		$ws();
		return $ok && $i >= $n ? null : $i;
	}

	private static function is_filled( $value ) {
		return is_array( $value ) ? (bool) $value : ( is_string( $value ) && '' !== trim( $value ) );
	}

	private static function describe( $value ) {
		if ( is_array( $value ) ) {
			return count( $value ) . ' item' . ( 1 === count( $value ) ? '' : 's' );
		}
		if ( is_bool( $value ) ) {
			return $value ? 'on' : 'off';
		}
		$s = (string) $value;
		return strlen( $s ) > 60 ? substr( $s, 0, 57 ) . '...' : $s;
	}

	/**
	 * Check a brief against the validators and work out what would change.
	 *
	 * @return array { ok, items[], errors[], ignored[], pages[], blocking[], changes }
	 *   items: each { group, field, label, action (set|skip_filled|unchanged), before, after }
	 *   changes: the cleaned values to write (profile, chrome, style, facts, pages)
	 */
	public static function plan( $data, $overwrite ) {
		$items   = array();
		$errors  = array();
		$ignored = array();
		$changes = array( 'mode' => null, 'profile' => array(), 'chrome' => array(), 'style' => null, 'facts' => null, 'pages' => array() );
		$profile = TWD_SK_Profile::get();
		$chrome  = TWD_SK_Chrome::settings();

		foreach ( array_keys( $data ) as $k ) {
			if ( ! in_array( $k, self::$top, true ) ) {
				$ignored[] = 'Unknown section "' . ( is_string( $k ) ? $k : '?' ) . '" was ignored.';
			}
		}

		// The mode comes first: it decides the wording of everything after it. It is a setting, so it is always applied.
		$mode = TWD_SK_Mode::get();
		if ( isset( $data['mode'] ) ) {
			if ( is_string( $data['mode'] ) && isset( TWD_SK_Mode::modes()[ $data['mode'] ] ) ) {
				if ( $data['mode'] !== TWD_SK_Mode::stored() ) {
					$changes['mode'] = $data['mode'];
					$items[]         = array( 'group' => 'mode', 'field' => 'mode', 'label' => 'Site type', 'before' => TWD_SK_Mode::stored(), 'after' => $data['mode'], 'action' => 'set' );
				}
				$mode = $data['mode'];
			} else {
				$errors[] = 'Site type: choose one of ' . implode( ', ', array_keys( TWD_SK_Mode::modes() ) ) . '. It was dropped.';
			}
		}
		$facts_label = TWD_SK_Mode::w( 'Facts', $mode );

		// Site, header and footer fields map onto the saved profile and header and footer settings.
		$map = array(
			'site'   => array( 'name' => array( 'profile', 'site_name', 'Site name' ), 'person_name' => array( 'profile', 'person_name', TWD_SK_Mode::w( 'person_name_label', $mode ) ), 'person_job' => array( 'profile', 'person_job', TWD_SK_Mode::w( 'person_job_label', $mode ) ), 'phone' => array( 'profile', 'phone', 'Phone' ), 'email' => array( 'profile', 'email', 'Email' ), 'address' => array( 'profile', 'address', 'Address lines' ), 'area_served' => array( 'profile', 'area_served', TWD_SK_Mode::w( 'area_label', $mode ) ), 'registration' => array( 'profile', 'registration', TWD_SK_Mode::w( 'registration_label', $mode ) ), 'same_as' => array( 'profile', 'same_as', 'Profile links' ) ),
			'header' => array( 'menu' => array( 'profile', 'menu', 'Menu' ), 'cta_label' => array( 'profile', 'cta_label', 'Header button text' ), 'cta_url' => array( 'profile', 'cta_url', 'Header button link' ), 'layout' => array( 'chrome', 'header_variant', 'Header layout' ), 'sticky' => array( 'chrome', 'sticky', 'Fixed header' ), 'shrink' => array( 'chrome', 'shrink', 'Shrink the fixed header on scroll' ), 'front_logo' => array( 'chrome', 'front_logo', 'Bigger logo on the front page' ), 'overlay' => array( 'chrome', 'overlay', 'Transparent header over the picture' ), 'wordmark' => array( 'chrome', 'wordmark', 'Text wordmark header' ), 'wordmark_scrolled_name' => array( 'chrome', 'wordmark_scrolled_name', 'Keep the name when the front page header is scrolled' ), 'menu_marker' => array( 'chrome', 'menu_marker', 'How the page you are on is marked in the menu' ), 'logo_line' => array( 'profile', 'logo_line', 'Second line under the name' ), 'show_button' => array( 'chrome', 'show_button', 'Show the header button' ), 'show_strip' => array( 'chrome', 'show_strip', 'Contact strip' ) ),
			'footer' => array( 'text' => array( 'profile', 'footer_text', 'Footer text' ), 'legal' => array( 'profile', 'legal', 'Legal links' ), 'layout' => array( 'chrome', 'footer_variant', 'Footer layout' ), 'columns' => array( 'chrome', 'footer_columns', 'Footer columns' ) ),
		);
		foreach ( $map as $group => $fields ) {
			if ( ! isset( $data[ $group ] ) ) {
				continue;
			}
			if ( ! is_array( $data[ $group ] ) ) {
				$errors[] = 'The "' . $group . '" section must be an object.';
				continue;
			}
			foreach ( $data[ $group ] as $key => $value ) {
				if ( ! is_string( $key ) || ! isset( $fields[ $key ] ) ) {
					$ignored[] = 'Unknown field "' . $group . '.' . ( is_string( $key ) ? $key : '?' ) . '" was ignored.';
					continue;
				}
				list( $store, $field, $label ) = $fields[ $key ];
				if ( 'menu' === $field && is_array( $value ) ) {
					foreach ( $value as $i => $item ) {
						if ( is_array( $item ) && ! isset( $item['children'] ) ) {
							$value[ $i ]['children'] = array();
						}
					}
				}
				$check = 'profile' === $store ? TWD_SK_Profile::validate( array( $field => $value ), false ) : TWD_SK_Chrome::validate( array( $field => $value ) );
				if ( $check['errors'] ) {
					$errors[] = $label . ': ' . implode( ' ', $check['errors'] ) . ' That value was dropped.';
					continue;
				}
				if ( ! array_key_exists( $field, $check['valid'] ) ) {
					continue;
				}
				$new    = $check['valid'][ $field ];
				$before = 'profile' === $store ? $profile[ $field ] : $chrome[ $field ];
				$item   = array( 'group' => $group, 'field' => $field, 'label' => $label, 'before' => self::describe( $before ), 'after' => self::describe( $new ) );
				if ( wp_json_encode( $before ) === wp_json_encode( $new ) ) {
					$item['action'] = 'unchanged';
				} elseif ( 'profile' === $store && self::is_filled( $before ) && ! $overwrite ) {
					$item['action'] = 'skip_filled';
				} else {
					$item['action'] = 'set';
					$changes[ $store ][ $field ] = $new;
				}
				$items[] = $item;
			}
		}

		// Style.
		if ( isset( $data['style'] ) ) {
			if ( ! is_array( $data['style'] ) ) {
				$errors[] = 'The "style" section must be an object.';
			} else {
				$packs  = TWD_SK_Packs::packs();
				$active = TWD_SK_Packs::active_slug();
				$target = $active;
				if ( isset( $data['style']['pack'] ) && '' !== $data['style']['pack'] ) {
					if ( is_string( $data['style']['pack'] ) && isset( $packs[ $data['style']['pack'] ] ) ) {
						$target = $data['style']['pack'];
					} else {
						$errors[] = 'Style: that style pack does not exist. It was dropped.';
					}
				}
				$tokens = array();
				if ( isset( $data['style']['tokens'] ) && is_array( $data['style']['tokens'] ) ) {
					$allowed = TWD_SK_Site::editable_names();
					foreach ( $data['style']['tokens'] as $name => $value ) {
						if ( ! is_string( $name ) || ! in_array( $name, $allowed, true ) ) {
							$ignored[] = 'Style token "' . ( is_string( $name ) ? $name : '?' ) . '" cannot be set here and was ignored.';
							continue;
						}
						$one = TWD_SK_Packs::validate_tokens( array( $name => $value ), false );
						if ( $one['errors'] ) {
							$errors[] = 'Style: the value for ' . $name . ' is not allowed. It was dropped.';
							continue;
						}
						$tokens[ $name ] = $one['valid'][ $name ];
					}
				}
				if ( $target !== $active || $tokens ) {
					$changes['style'] = array( 'pack' => $target, 'tokens' => $tokens );
					$items[]          = array( 'group' => 'style', 'field' => 'style', 'label' => 'Style', 'before' => $active, 'after' => $target . ( $tokens ? ' and ' . count( $tokens ) . ' change' . ( 1 === count( $tokens ) ? '' : 's' ) : '' ), 'action' => 'set' );
				}
			}
		}

		// The facts.
		if ( isset( $data['facts'] ) ) {
			if ( ! is_string( $data['facts'] ) ) {
				$errors[] = $facts_label . ' must be text.';
			} else {
				$clean = TWD_SK_Facts::clean( $data['facts'] );
				$len   = function_exists( 'mb_strlen' ) ? mb_strlen( $clean, 'UTF-8' ) : strlen( $clean );
				$have  = TWD_SK_Facts::get();
				if ( $len > TWD_SK_Facts::MAX ) {
					$errors[] = $facts_label . ' are too long (' . $len . ' characters, the limit is ' . TWD_SK_Facts::MAX . '). They were dropped.';
				} elseif ( $clean !== $have && '' !== $clean ) {
					$item = array( 'group' => 'facts', 'field' => 'facts', 'label' => $facts_label, 'before' => '' === $have ? 'empty' : strlen( $have ) . ' characters', 'after' => $len . ' characters' );
					if ( '' !== $have && ! $overwrite ) {
						$item['action'] = 'skip_filled';
					} else {
						$item['action']   = 'set';
						$changes['facts'] = $clean;
					}
					$items[] = $item;
				}
			}
		}

		// Pages to create.
		$pages = array();
		if ( isset( $data['pages'] ) ) {
			if ( ! is_array( $data['pages'] ) ) {
				$errors[] = 'The "pages" section must be a list.';
			} else {
				foreach ( array_slice( $data['pages'], 0, self::MAX_PAGES ) as $i => $pg ) {
					if ( ! is_array( $pg ) ) {
						$errors[] = 'Page ' . ( $i + 1 ) . ' must be an object. It was dropped.';
						continue;
					}
					$in = TWD_SK_Recipes::inputs( $pg );
					foreach ( TWD_SK_Recipes::warnings() as $w ) {
						$ignored[] = 'Page ' . ( $i + 1 ) . ': ' . $w;
					}
					if ( is_wp_error( $in ) ) {
						$errors[] = 'Page ' . ( $i + 1 ) . ': ' . $in->get_error_message() . ' It was dropped.';
						continue;
					}
					$seo = array();
					if ( isset( $pg['seo'] ) && is_array( $pg['seo'] ) ) {
						if ( isset( $pg['seo']['title'] ) ) {
							$seo['title'] = TWD_SK_Profile::text( $pg['seo']['title'], TWD_SK_Seo::MAX_TITLE );
						}
						if ( isset( $pg['seo']['description'] ) ) {
							$seo['description'] = TWD_SK_Profile::text( $pg['seo']['description'], TWD_SK_Seo::MAX_DESC );
						}
					}
					$in['seo']    = array_filter( $seo, 'strlen' );
					$in['exists'] = self::find_page( $in['title'] );
					$pages[]      = $in;
				}
				if ( count( $data['pages'] ) > self::MAX_PAGES ) {
					$ignored[] = 'Only the first ' . self::MAX_PAGES . ' pages were read.';
				}
			}
		}
		$changes['pages'] = $pages;

		// Would the new style be readable?
		$blocking = array();
		if ( $changes['style'] ) {
			$packs     = TWD_SK_Packs::packs();
			$s         = $changes['style'];
			$active    = TWD_SK_Packs::active_slug();
			$overrides = $s['pack'] !== $active ? array() : TWD_SK_Packs::overrides();
			$eff       = array_merge( $packs[ $s['pack'] ]['tokens'], $overrides, $s['tokens'] );
			foreach ( TWD_SK_Contrast::check( $eff )['blocking'] as $b ) {
				$blocking[] = $b['label'] . ' (' . $b['ratio'] . ':1, needs 4.5:1)';
			}
		}

		return array(
			'ok'       => true,
			'items'    => $items,
			'errors'   => $errors,
			'ignored'  => $ignored,
			'pages'    => array_map( function ( $pg ) {
				return array( 'type' => $pg['type'], 'title' => $pg['title'], 'exists' => (int) $pg['exists'] );
			}, $pages ),
			'blocking' => $blocking,
			'changes'  => $changes,
		);
	}

	/** The ID of a page with this title (any status), or 0. */
	private static function find_page( $title ) {
		$ids = function_exists( 'get_posts' ) ? get_posts( array( 'post_type' => 'page', 'post_status' => 'any', 'numberposts' => 300, 'fields' => 'ids' ) ) : array();
		foreach ( (array) $ids as $id ) {
			$post = get_post( $id );
			if ( $post && 0 === strcasecmp( trim( (string) $post->post_title ), trim( (string) $title ) ) ) {
				return (int) $id;
			}
		}
		return 0;
	}

	/** The plan without the cleaned values, for the pop-up. */
	public static function preview( $data, $overwrite ) {
		$p = self::plan( $data, $overwrite );
		unset( $p['changes'] );
		return $p;
	}

	// -- Applying a brief -------------------------------------------------------

	/**
	 * Write what the plan says, then optionally create the pages as placeholder DRAFTS.
	 *
	 * @param array $opts overwrite (bool), build_pages (bool)
	 * @return array { applied[], skipped[], errors[], ignored[], pages[ {id,title,url,type,topic,notes,status} ] }
	 */
	public static function apply( $data, $opts ) {
		$overwrite = ! empty( $opts['overwrite'] );
		$plan      = self::plan( $data, $overwrite );
		$ch        = $plan['changes'];
		$applied   = array();
		$skipped   = array();
		$errors    = $plan['errors'];

		if ( $ch['mode'] ) {
			$r = TWD_SK_Mode::set( $ch['mode'] );
			if ( is_wp_error( $r ) ) {
				$errors[] = 'Site type: ' . $r->get_error_message();
			} else {
				$applied[] = 'Site type is now ' . TWD_SK_Mode::modes()[ $r ]['label'] . '.';
			}
		}

		foreach ( $plan['items'] as $it ) {
			if ( 'skip_filled' === $it['action'] ) {
				$skipped[] = $it['label'] . ' was already filled in, so it was kept as it is.';
			}
		}

		if ( $ch['profile'] ) {
			$r = TWD_SK_Profile::save( $ch['profile'] );
			if ( is_wp_error( $r ) ) {
				$errors[] = 'Site details: ' . $r->get_error_message();
			} else {
				$applied[] = 'Site details: ' . count( $ch['profile'] ) . ' field' . ( 1 === count( $ch['profile'] ) ? '' : 's' ) . ' saved.';
			}
		}
		if ( $ch['chrome'] ) {
			$r = TWD_SK_Chrome::save_settings( $ch['chrome'] );
			if ( is_wp_error( $r ) ) {
				$errors[] = 'Header and footer settings: ' . $r->get_error_message();
			} else {
				$applied[] = 'Header and footer settings saved.';
			}
		}
		if ( $ch['facts'] ) {
			$r = TWD_SK_Facts::save( $ch['facts'] );
			if ( is_wp_error( $r ) ) {
				$errors[] = TWD_SK_Mode::w( 'Facts' ) . ': ' . $r->get_error_message();
			} else {
				$applied[] = TWD_SK_Mode::w( 'Facts' ) . ' saved.';
			}
		}
		if ( $ch['style'] ) {
			$s         = $ch['style'];
			$overrides = $s['pack'] !== TWD_SK_Packs::active_slug() ? array() : TWD_SK_Packs::overrides();
			$r         = TWD_SK_Site::apply( $s['pack'], array_merge( $overrides, $s['tokens'] ) );
			if ( is_wp_error( $r ) ) {
				$errors[] = 'Style: ' . $r->get_error_message();
			} else {
				$applied[] = 'Style saved.';
			}
		}

		$made = array();
		if ( ! empty( $opts['build_pages'] ) ) {
			foreach ( $ch['pages'] as $pg ) {
				if ( $pg['exists'] ) {
					$skipped[] = 'The page "' . $pg['title'] . '" already exists, so it was not created again.';
					continue;
				}
				$id = TWD_SK_Template::create_page( $pg['title'], 'blank' );
				if ( is_wp_error( $id ) ) {
					$errors[] = 'Page "' . $pg['title'] . '": ' . $id->get_error_message();
					continue;
				}
				$saved = TWD_SK_Store::save( $id, TWD_SK_Recipes::skeleton_for( $pg ), array( 'base_version' => 0, 'note' => 'Outline from the site brief (' . $pg['type'] . ')' ) );
				if ( is_wp_error( $saved ) ) {
					$errors[] = 'Page "' . $pg['title'] . '": ' . $saved->get_error_message();
				}
				if ( $pg['seo'] ) {
					$r = TWD_SK_Seo::write( $id, $pg['seo'] );
					if ( is_wp_error( $r ) ) {
						$skipped[] = 'Search wording for "' . $pg['title'] . '" was not saved: ' . $r->get_error_message();
					}
				}
				$made[] = array( 'id' => (int) $id, 'title' => (string) get_post( $id )->post_title, 'url' => get_permalink( $id ), 'type' => $pg['type'], 'topic' => $pg['topic'], 'notes' => $pg['notes'] );
			}
			if ( $made ) {
				$applied[] = count( $made ) . ' draft page' . ( 1 === count( $made ) ? '' : 's' ) . ' created from the outlines. None is published.';
			}
		}

		return array( 'applied' => $applied, 'skipped' => $skipped, 'errors' => $errors, 'ignored' => $plan['ignored'], 'pages' => $made );
	}

	// -- A brief from plain notes (needs the AI) ---------------------------------

	const MAX_NOTES  = 30 * 1000;
	const TOKENS_NOTES = 8000; // the most the Articles plugin allows in one reply
	// Notes longer than this are kept as the facts word for word, so the AI writes only the rest of the
	// brief and its reply stays well inside that ceiling (0.9.2).
	const NOTES_AS_FACTS = 6000;

	/** System text for turning plain notes into a brief. Built from the same schema text as the interview. */
	public static function notes_system_prompt( $keep_notes_as_facts = false ) {
		$out   = array();
		$out[] = 'You turn a web designer\'s notes about a ' . TWD_SK_Mode::w( 'practice' ) . ' into ONE site brief JSON object. Reply with the JSON object only: no code fence, no commentary.';
		$out[] = '';
		$out[] = '## Rules';
		$out[] = '- Use only what the notes say. Never invent anything: no names, qualifications, registrations, prices, fees, policies, contact details, reviews or testimonials that are not in the notes. Leave anything unknown as an empty string or an empty list.';
		$out[] = '- Phone numbers, email addresses, postal addresses and registration lines are copied exactly as written in the notes, or left empty.';
		if ( $keep_notes_as_facts ) {
			$out[] = '- The notes are long, so leave "facts" as an empty string. The notes themselves are saved as the facts, word for word.';
		} else {
			$out[] = '- The facts are written in plain sentences under the headings below, in the owner\'s own words where the notes give them.';
		}
		$out[] = '- If the notes do not list pages, plan Home, About and Contact, plus one page for each main ' . TWD_SK_Mode::w( 'service_kind' ) . ' the notes describe (up to six), and a questions and answers page if the notes give questions.';
		$out[] = '- Draft a short search title and description for each page from the notes. Never use em dashes or en dashes. UK spelling.';
		$out[] = '- ' . trim( substr( TWD_SK_Mode::w( 'interview_privacy' ), 1 ) );
		$out[] = '';
		$out[] = '## The shape';
		$out[] = self::schema_text();
		return implode( "\n", $out ) . "\n";
	}

	/**
	 * Turn plain notes into a brief with the AI. Saves nothing: the person checks the result first.
	 * A site brief pasted in by mistake is checked as it is, with no AI. Long notes are kept as the facts.
	 *
	 * @return array|WP_Error { brief (array), json (pretty text), note (plain sentence, may be empty) }
	 */
	public static function from_notes( $notes ) {
		if ( ! is_string( $notes ) || '' === trim( $notes ) ) {
			return new WP_Error( 'twd_sk_bad_brief', 'Write or paste some notes first.' );
		}
		$flags = JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE;
		// Already a site brief: no AI needed, and asking it to retype the whole brief would run out of room.
		if ( '{' === substr( ltrim( TWD_SK_AI::strip_fence( $notes ) ), 0, 1 ) ) {
			$data = self::parse( $notes );
			if ( is_wp_error( $data ) ) {
				return new WP_Error( 'twd_sk_bad_brief', 'That looks like a site brief, but it could not be read (' . $data->get_error_message() . ') Check it was copied in full.' );
			}
			return array( 'brief' => $data, 'json' => function_exists( 'wp_json_encode' ) ? wp_json_encode( $data, $flags ) : json_encode( $data, $flags ), 'note' => 'That is already a site brief, so the AI was not needed.' );
		}
		if ( ! TWD_SK_AI::available() ) {
			return new WP_Error( 'twd_sk_ai_unavailable', 'AI is not set up on this site, so notes cannot be turned into a brief here. Use the interview prompt in a Claude conversation, then paste the JSON it gives you.' );
		}
		if ( strlen( $notes ) > self::MAX_NOTES ) {
			return new WP_Error( 'twd_sk_bad_brief', 'Those notes are too long (the limit is ' . (int) ( self::MAX_NOTES / 1000 ) . ' thousand characters). Shorten them and try again.' );
		}
		$clean = TWD_SK_Facts::clean( $notes );
		$keep  = strlen( $clean ) > self::NOTES_AS_FACTS;
		$reply = apply_filters( 'twd_ai_complete', null, self::notes_system_prompt( $keep ), "The notes:\n" . $clean . "\n", self::TOKENS_NOTES );
		if ( TWD_SK_AI::cut_off( $reply ) ) {
			return new WP_Error( 'twd_sk_ai_too_long', 'The AI ran out of room writing the brief, so nothing was changed. Split the notes in two and turn each into a brief, or use the interview prompt in a Claude conversation.' );
		}
		if ( is_wp_error( $reply ) ) {
			return $reply;
		}
		if ( ! is_string( $reply ) || '' === trim( $reply ) ) {
			return new WP_Error( 'twd_sk_ai_unavailable', 'The AI did not answer. Nothing was saved. Try again in a moment.' );
		}
		$data = self::parse( $reply );
		if ( is_wp_error( $data ) ) {
			return new WP_Error( 'twd_sk_ai_bad_result', 'The AI did not send back a brief that could be read. Try again, or shorten the notes. (' . $data->get_error_message() . ')' );
		}
		$note = '';
		if ( $keep ) {
			$len           = function_exists( 'mb_strlen' ) ? mb_strlen( $clean, 'UTF-8' ) : strlen( $clean );
			$data['facts'] = $len > TWD_SK_Facts::MAX ? ( function_exists( 'mb_substr' ) ? mb_substr( $clean, 0, TWD_SK_Facts::MAX, 'UTF-8' ) : substr( $clean, 0, TWD_SK_Facts::MAX ) ) : $clean;
			$note          = 'Your notes are kept as the ' . TWD_SK_Mode::w( 'facts' ) . ' word for word.' . ( $len > TWD_SK_Facts::MAX ? ' Only the first ' . number_format( TWD_SK_Facts::MAX ) . ' characters fit.' : '' );
		}
		return array( 'brief' => $data, 'json' => function_exists( 'wp_json_encode' ) ? wp_json_encode( $data, $flags ) : json_encode( $data, $flags ), 'note' => $note );
	}

	// -- The interview prompt -----------------------------------------------------

	public static function schema_text() {
		$packs  = implode( ', ', array_keys( TWD_SK_Packs::packs() ) );
		$hv     = implode( ', ', array_keys( TWD_SK_Chrome::header_variants() ) );
		$fv     = implode( ', ', array_keys( TWD_SK_Chrome::footer_variants() ) );
		$types  = implode( ', ', array_keys( TWD_SK_Recipes::types() ) );
		$out    = array();
		$out[]  = '{';
		$out[]  = '  "schema": "' . self::SCHEMA . '",';
		$out[]  = '  "mode": "' . TWD_SK_Mode::get() . '",';
		$out[]  = '  "site": { "name": "", "person_name": "", "person_job": "", "phone": "", "email": "", "address": [], "area_served": "", "registration": [], "same_as": [] },';
		$out[]  = '  "header": { "menu": [ { "label": "", "url": "/", "children": [] } ], "cta_label": "", "cta_url": "", "layout": "", "sticky": false, "shrink": false, "show_button": true, "show_strip": false },';
		$out[]  = '  "footer": { "text": "", "legal": [ { "label": "Privacy policy", "url": "/privacy-policy" } ], "layout": "", "columns": 2 },';
		$out[]  = '  "style": { "pack": "", "tokens": {} },';
		$out[]  = '  "facts": "",';
		$out[]  = '  "pages": [ { "type": "about", "title": "About", "topic": "", "notes": "", "seo": { "title": "", "description": "" } } ]';
		$out[]  = '}';
		$out[]  = '';
		$out[]  = 'Allowed values:';
		$out[]  = '- header.layout: ' . $hv . ' (or empty to use the style\'s own).';
		$out[]  = '- footer.layout: ' . $fv . ' (or empty). footer.columns: 1, 2 or 3.';
		$out[]  = '- style.pack: ' . $packs . ' (or empty). Leave style.tokens empty unless the person asked for specific colours.';
		$out[]  = '- mode: ' . implode( ', ', array_keys( TWD_SK_Mode::modes() ) ) . '. Keep it as given.';
		$out[]  = '- pages[].type: ' . $types . '. A "service" page needs a topic, for example "' . TWD_SK_Mode::w( 'topic_example' ) . '".';
		$out[]  = '- pages[].sections: optional. The sections in order, each { "component": "", "variant": "" }, the first a hero. Leave it out to use the page type\'s own. Components: ' . implode( ', ', TWD_SK_Registry::ids() ) . '.';
		$out[]  = '- menu: up to ' . TWD_SK_Profile::MAX_MENU . ' items, one level of children. Links are page paths such as "/about", "/" for home, or full https addresses.';
		$out[]  = '- facts: plain text with these headings in capitals, each followed by the person\'s own words: ' . implode( ', ', TWD_SK_Mode::facts_headings() ) . '.';
		$out[]  = '- Leave anything unknown as an empty string or empty list. Do not guess.';
		return implode( "\n", $out );
	}

	public static function interview_prompt() {
		$out   = array();
		$out[] = TWD_SK_Mode::w( 'interview_intro' );
		$out[] = '';
		$out[] = '## How to run the session';
		$out[] = '- Ask in small groups of three or four questions, one topic at a time, in plain English. Wait for the answers before moving on.';
		$out[] = TWD_SK_Mode::w( 'interview_topics' );
		$out[] = TWD_SK_Mode::w( 'interview_invent' );
		$out[] = TWD_SK_Mode::w( 'interview_privacy' );
		$out[] = '- Never use em dashes or en dashes. Use a comma, a full stop or a hyphen. UK spelling.';
		$out[] = '- When you have everything, read back a short summary and ask what to correct.';
		$out[] = '- After they confirm, reply with ONE JSON object in a code block, and nothing else, in exactly this shape:';
		$out[] = '';
		$out[] = self::schema_text();
		return implode( "\n", $out ) . "\n";
	}
}
