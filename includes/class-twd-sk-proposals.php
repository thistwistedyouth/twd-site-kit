<?php
/**
 * TWD_SK_Proposals: AI suggestions for the header, the footer and the style, as STRUCTURED values.
 *
 * The AI never writes HTML or CSS here. It answers with a small JSON object of the values the Site
 * tab already edits (menu, button, footer text, legal links, layout choices, colours, fonts, corner
 * sizes). Every value goes through the same validators the Site tab saves with, so a value that is
 * not allowed (a bad link, an unknown font, a size under the readable minimum) is dropped and said
 * so. Colours are also checked for readable text.
 *
 * Nothing is saved here. The pop-up puts the proposed values into the Site tab's own boxes, where
 * the person reviews them and saves with the normal buttons. Never changed, whatever the AI says:
 * phone, email, address, registration lines, the therapist's name and job, links to profiles.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class TWD_SK_Proposals {

	const MAX_INSTRUCTION = 1000;
	const TOKENS          = 3000;

	public static function kinds() {
		return array( 'header', 'footer', 'style' );
	}

	/** Which saved profile fields a kind may change. */
	private static function profile_fields( $kind ) {
		return 'header' === $kind ? array( 'menu', 'cta_label', 'cta_url' ) : ( 'footer' === $kind ? array( 'footer_text', 'legal' ) : array() );
	}

	/** Which header and footer settings a kind may change. */
	private static function chrome_fields( $kind ) {
		return 'header' === $kind ? array( 'header_variant', 'sticky', 'show_button', 'show_strip' ) : ( 'footer' === $kind ? array( 'footer_variant', 'footer_columns' ) : array() );
	}

	public static function labels() {
		return array(
			'menu'           => 'Menu',
			'cta_label'      => 'Header button text',
			'cta_url'        => 'Header button link',
			'header_variant' => 'Header layout',
			'sticky'         => 'Fixed header',
			'show_button'    => 'Show the header button',
			'show_strip'     => 'Contact strip',
			'footer_text'    => 'Footer text',
			'legal'          => 'Legal links',
			'footer_variant' => 'Footer layout',
			'footer_columns' => 'Footer columns',
			'pack'           => 'Style',
		);
	}

	private static function label( $field ) {
		$l = self::labels();
		if ( isset( $l[ $field ] ) ) {
			return $l[ $field ];
		}
		foreach ( TWD_SK_Site::editable() as $e ) {
			if ( $e[1] === $field ) {
				return $e[2];
			}
		}
		return $field;
	}

	/** Current values for a kind (header and footer). */
	public static function current( $kind ) {
		$out = array();
		$p   = TWD_SK_Profile::get();
		foreach ( self::profile_fields( $kind ) as $f ) {
			$out[ $f ] = $p[ $f ];
		}
		$c = TWD_SK_Chrome::settings();
		foreach ( self::chrome_fields( $kind ) as $f ) {
			$out[ $f ] = $c[ $f ];
		}
		return $out;
	}

	/** Published pages as plain "title: /path" lines, the only internal links the AI may use. */
	private static function page_lines() {
		$lines = array();
		$home  = function_exists( 'home_url' ) ? rtrim( (string) home_url( '' ), '/' ) : '';
		if ( function_exists( 'get_pages' ) ) {
			$front = (int) get_option( 'page_on_front' );
			foreach ( (array) get_pages( array( 'number' => 60 ) ) as $page ) {
				$path    = (int) $page->ID === $front ? '/' : (string) str_replace( $home, '', (string) get_permalink( $page->ID ) );
				$lines[] = $page->post_title . ': ' . ( '' === $path ? '/' : $path );
			}
		}
		return $lines;
	}

	public static function system_prompt( $kind ) {
		$out   = array();
		$out[] = 'You suggest changes to part of a therapist\'s website settings. You do not write HTML or CSS. Reply with ONE JSON object and nothing else: no code fence, no commentary.';
		$out[] = 'Include only the fields you change. Leave out every field you leave as it is.';
		$out[] = '';
		$out[] = '## Shape';
		if ( 'header' === $kind ) {
			$out[] = '{"menu":[{"label":"About","url":"/about","children":[{"label":"Sub item","url":"/sub"}]}],"cta_label":"Book a call","cta_url":"/contact","header_variant":"bar","sticky":false,"show_button":true,"show_strip":false}';
			$out[] = 'header_variant is one of: ' . implode( ', ', array_keys( TWD_SK_Chrome::header_variants() ) ) . '. Menu: up to ' . TWD_SK_Profile::MAX_MENU . ' items, one level of children (up to ' . TWD_SK_Profile::MAX_CHILDREN . ' each). A button label is up to 30 characters.';
		} elseif ( 'footer' === $kind ) {
			$out[] = '{"footer_text":"One or two sentences.","legal":[{"label":"Privacy policy","url":"/privacy-policy"}],"footer_variant":"columns","footer_columns":2}';
			$out[] = 'footer_variant is one of: ' . implode( ', ', array_keys( TWD_SK_Chrome::footer_variants() ) ) . '. footer_columns is 1, 2 or 3. Legal links: up to ' . TWD_SK_Profile::MAX_LEGAL . '.';
		} else {
			$out[] = '{"pack":"sage","tokens":{"color-primary":"#5c6f50","font-heading":"Lora","radius-btn":"12px"}}';
			$out[] = 'pack is one of the style names given below, or leave it out to keep the current one. tokens uses only the token names given below. Colours are #rrggbb. Radii are like 12px. Fonts are only from the font list. Keep text easy to read: dark text on light, light text on dark, at least 4.5 to 1 contrast. Never make text smaller.';
		}
		$out[] = '';
		$out[] = '## Rules';
		$rules = array(
			'Change only what the instruction asks for.',
			'Never invent a phone number, email, address, registration, qualification or any other fact. You cannot change those here.',
			'Use only the practice facts and site details given. Do not use em dashes or en dashes.',
		);
		if ( 'style' !== $kind ) {
			$rules[] = 'Links must be pages from the list given, "/" for the home page, a full https address, mailto: or tel:. Do not invent a page.';
			$rules[] = 'Menu and button wording must be plain and meaningful on its own.';
		}
		foreach ( $rules as $i => $r ) {
			$out[] = ( $i + 1 ) . '. ' . $r;
		}
		return implode( "\n", $out ) . "\n";
	}

	public static function user_message( $kind, $instruction ) {
		$out   = array();
		$out[] = 'What to change: ' . $instruction;
		$master = TWD_SK_Facts::prompt_block();
		if ( '' !== $master ) {
			$out[] = '';
			$out[] = $master;
		}
		$out[] = '';
		if ( 'style' === $kind ) {
			$packs = array();
			foreach ( TWD_SK_Site::pack_list() as $p ) {
				$packs[] = $p['slug'] . ': ' . $p['description'];
			}
			$out[] = 'Styles (the current one is "' . TWD_SK_Packs::active_slug() . '"):';
			$out[] = implode( "\n", $packs );
			$out[] = '';
			$out[] = 'Fonts you may use: ' . implode( ', ', array_keys( TWD_SK_Packs::fonts() ) );
			$out[] = '';
			$out[] = 'Current values of the tokens you may change (name, what it is, value):';
			$eff = TWD_SK_Packs::active_tokens();
			foreach ( TWD_SK_Site::editable() as $e ) {
				$out[] = $e[1] . ' (' . $e[2] . '): ' . ( isset( $eff[ $e[1] ] ) ? $eff[ $e[1] ] : '' );
			}
		} else {
			$out[] = 'Pages on the site (title: link):';
			$out[] = implode( "\n", self::page_lines() );
			$out[] = '';
			$out[] = 'Current values (JSON):';
			$out[] = wp_json_encode( self::current( $kind ) );
		}
		return implode( "\n", $out ) . "\n";
	}

	/** Pull the JSON object out of the reply, even if the AI added a fence or a sentence. */
	public static function parse( $reply ) {
		$text  = TWD_SK_AI::strip_fence( $reply );
		$start = strpos( $text, '{' );
		$end   = strrpos( $text, '}' );
		if ( false === $start || false === $end || $end <= $start ) {
			return null;
		}
		$data = json_decode( substr( $text, $start, $end - $start + 1 ), true );
		return is_array( $data ) ? $data : null;
	}

	/**
	 * Ask the AI and validate its answer. Saves nothing.
	 *
	 * @return array|WP_Error { kind, values, changed[{field,label}], errors[], blocking[] }
	 */
	public static function propose( $kind, $instruction ) {
		if ( ! TWD_SK_AI::available() ) {
			return new WP_Error( 'twd_sk_ai_unavailable', 'AI is not set up on this site. Edit the boxes by hand, or ask your web designer to add a key.' );
		}
		if ( ! in_array( $kind, self::kinds(), true ) ) {
			return new WP_Error( 'twd_sk_bad_input', 'Choose the header, the footer or the style.' );
		}
		$instruction = is_string( $instruction ) ? TWD_SK_Profile::text( $instruction, self::MAX_INSTRUCTION ) : '';
		if ( '' === $instruction ) {
			return new WP_Error( 'twd_sk_bad_input', 'Say what you want changed first.' );
		}
		$reply = apply_filters( 'twd_ai_complete', null, self::system_prompt( $kind ), self::user_message( $kind, $instruction ), self::TOKENS );
		if ( is_wp_error( $reply ) ) {
			return $reply;
		}
		if ( ! is_string( $reply ) || '' === trim( $reply ) ) {
			return new WP_Error( 'twd_sk_ai_unavailable', 'The AI did not answer. Nothing was changed. Try again in a moment.' );
		}
		$data = self::parse( $reply );
		if ( null === $data ) {
			return new WP_Error( 'twd_sk_ai_bad_result', 'The AI did not answer in the form expected. Nothing was changed. Try again.' );
		}
		return 'style' === $kind ? self::check_style( $data ) : self::check_chrome( $kind, $data );
	}

	private static function same( $a, $b ) {
		return wp_json_encode( $a ) === wp_json_encode( $b );
	}

	private static function check_chrome( $kind, $data ) {
		$errors  = array();
		$values  = array();
		$current = self::current( $kind );

		$profile_in = array_intersect_key( $data, array_flip( self::profile_fields( $kind ) ) );
		if ( isset( $profile_in['menu'] ) && is_array( $profile_in['menu'] ) ) {
			foreach ( $profile_in['menu'] as $i => $item ) {
				if ( is_array( $item ) && ! isset( $item['children'] ) ) {
					$profile_in['menu'][ $i ]['children'] = array();
				}
			}
		}
		foreach ( $profile_in as $field => $value ) {
			$one = TWD_SK_Profile::validate( array( $field => $value ), false );
			if ( $one['errors'] ) {
				$errors[] = self::label( $field ) . ': ' . implode( ' ', $one['errors'] ) . ' That change was dropped.';
			} elseif ( array_key_exists( $field, $one['valid'] ) ) {
				$values[ $field ] = $one['valid'][ $field ];
			}
		}
		$chrome_in = array_intersect_key( $data, array_flip( self::chrome_fields( $kind ) ) );
		foreach ( $chrome_in as $field => $value ) {
			$one = TWD_SK_Chrome::validate( array( $field => $value ) );
			if ( $one['errors'] ) {
				$errors[] = self::label( $field ) . ': ' . implode( ' ', $one['errors'] ) . ' That change was dropped.';
			} elseif ( array_key_exists( $field, $one['valid'] ) ) {
				$values[ $field ] = $one['valid'][ $field ];
			}
		}
		$ignored = array_diff( array_keys( $data ), self::profile_fields( $kind ), self::chrome_fields( $kind ) );
		if ( $ignored ) {
			$errors[] = 'The AI also tried to change something that cannot be changed here (' . implode( ', ', array_map( function ( $k ) {
				return is_string( $k ) ? $k : '?';
			}, $ignored ) ) . '). That was ignored.';
		}
		$changed = array();
		foreach ( $values as $field => $value ) {
			if ( self::same( $value, $current[ $field ] ) ) {
				unset( $values[ $field ] );
				continue;
			}
			$changed[] = array( 'field' => $field, 'label' => self::label( $field ) );
		}
		return array( 'kind' => $kind, 'values' => (object) $values, 'changed' => $changed, 'errors' => $errors, 'blocking' => array() );
	}

	private static function check_style( $data ) {
		$errors  = array();
		$packs   = TWD_SK_Packs::packs();
		$current = TWD_SK_Packs::active_slug();
		$values  = array();
		$changed = array();

		$target = $current;
		if ( isset( $data['pack'] ) ) {
			if ( is_string( $data['pack'] ) && isset( $packs[ $data['pack'] ] ) ) {
				if ( $data['pack'] !== $current ) {
					$target          = $data['pack'];
					$values['pack']  = $target;
					$changed[]       = array( 'field' => 'pack', 'label' => self::label( 'pack' ) );
				}
			} else {
				$errors[] = 'The AI named a style that does not exist. That change was dropped.';
			}
		}

		$tokens = array();
		if ( isset( $data['tokens'] ) && is_array( $data['tokens'] ) ) {
			$allowed = TWD_SK_Site::editable_names();
			foreach ( $data['tokens'] as $name => $value ) {
				if ( ! is_string( $name ) || ! in_array( $name, $allowed, true ) ) {
					$errors[] = 'The AI tried to change "' . ( is_string( $name ) ? $name : '?' ) . '", which cannot be changed here. That was ignored.';
					continue;
				}
				$one = TWD_SK_Packs::validate_tokens( array( $name => $value ), false );
				if ( $one['errors'] ) {
					$errors[] = self::label( $name ) . ': that value is not allowed (a colour like #5c6f50, a bundled font, a size in pixels, nothing under the readable minimum). That change was dropped.';
					continue;
				}
				$tokens[ $name ] = $one['valid'][ $name ];
			}
		}

		// What the style would look like, for the readability check.
		$base      = $packs[ $target ]['tokens'];
		$overrides = $target !== $current ? array() : TWD_SK_Packs::overrides();
		$effective = array_merge( $base, $overrides );
		$active    = array_merge( $packs[ $current ]['tokens'], TWD_SK_Packs::overrides() );
		foreach ( $tokens as $name => $value ) {
			$before = $target !== $current ? $base[ $name ] : ( isset( $active[ $name ] ) ? $active[ $name ] : '' );
			if ( strtolower( (string) $before ) === strtolower( $value ) ) {
				unset( $tokens[ $name ] );
				continue;
			}
			$effective[ $name ] = $value;
			$changed[]          = array( 'field' => $name, 'label' => self::label( $name ) );
		}
		if ( $tokens ) {
			$values['tokens'] = $tokens;
		}
		$check = TWD_SK_Contrast::check( $effective );
		$block = array_map( function ( $i ) {
			return $i['label'] . ' (' . $i['ratio'] . ':1, needs 4.5:1)';
		}, $check['blocking'] );
		return array( 'kind' => 'style', 'values' => (object) $values, 'changed' => $changed, 'errors' => $errors, 'blocking' => $block );
	}
}
