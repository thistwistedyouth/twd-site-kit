<?php
/**
 * TWD_SK_Replace: Replace a section (or the page) with custom HTML and CSS, and Adapt a section with the AI
 * (0.15.0, section 20 of the owner's plan: Base, Adapt, Replace).
 *
 * The flow is the kit's usual one: a candidate page is built, cleaned by the sanitiser (with the candidate
 * stylesheet's classes allowed), shown in the normal preview at desktop and phone width, and applied as ONE
 * change: the site stylesheet first (TWD_SK_Css::save), then the page (TWD_SK_Store::save), which records the
 * stylesheet version it was saved with, so Undo takes both back together (TWD_SK_Css::follow_page).
 *
 * A custom section carries the class twd-sk-custom (added here when a top-level section is not a kit component).
 * Inside one, the Visual editor and the Simple editor number only the elements labelled data-twd-edit (words)
 * and data-twd-pic (pictures); prepare() warns when a custom section carries no label at all.
 *
 * Adapt: "Describe a change" sends the section, the site stylesheet, the kit's shared classes and the token
 * names to the AI, which must answer with one JSON object { html, css, what_changed }. The reply goes through the
 * same prepare() as a paste, so the same checks and messages apply. Nothing is saved until Apply.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class TWD_SK_Replace {

	const LABEL_ATTR   = 'data-twd-edit';
	const PIC_ATTR     = 'data-twd-pic';
	const TOKENS_ADAPT = 6000;
	const MAX_CSS_IN_PROMPT = 14000;

	// -- Custom sections ----------------------------------------------------------------

	/** Does this section's class list name a kit component (hero, text, steps ...)? */
	public static function is_kit_section( $section ) {
		$type = TWD_SK_Sections::type_of( $section );
		return 'section' !== $type && null !== TWD_SK_Registry::get( str_replace( '-', '_', $type ) );
	}

	/**
	 * Every top-level section that is not a kit component gets twd-sk-custom as its first class; HTML with no
	 * top-level section at all is wrapped in one custom section.
	 */
	public static function mark_custom( $html ) {
		$html  = trim( (string) $html );
		$parts = TWD_SK_Sections::split( $html );
		if ( ! $parts['sections'] ) {
			return '<section class="' . TWD_SK_Css::CUSTOM_CLASS . '">' . "\n" . $html . "\n" . '</section>';
		}
		$custom = TWD_SK_Css::CUSTOM_CLASS;
		foreach ( $parts['sections'] as $i => $section ) {
			if ( self::is_kit_section( $section ) || preg_match( '/^<section\b[^>]*\sclass\s*=\s*"[^"]*\b' . preg_quote( $custom, '/' ) . '\b/i', $section ) ) {
				continue;
			}
			if ( preg_match( '/^<section\b[^>]*?\sclass\s*=\s*"/i', $section ) ) {
				$section = preg_replace( '/^(<section\b[^>]*?\sclass\s*=\s*")/i', '$1' . $custom . ' ', $section, 1 );
			} else {
				$section = preg_replace( '/^<section\b/i', '<section class="' . $custom . '"', $section, 1 );
			}
			$parts['sections'][ $i ] = $section;
		}
		return TWD_SK_Sections::join( $parts['gaps'], $parts['sections'] );
	}

	/** How many labelled words and pictures some HTML holds. */
	public static function labels( $html ) {
		return array(
			'text' => preg_match_all( '/\s' . self::LABEL_ATTR . '="/', (string) $html ),
			'pics' => preg_match_all( '/\s' . self::PIC_ATTR . '="/', (string) $html ),
		);
	}

	// -- The candidate ---------------------------------------------------------------------

	/**
	 * Build and check a candidate page. Nothing is saved.
	 *
	 * @param int   $page_id
	 * @param array $in target ('section' or 'page'), index (the section to replace), html, css (rules to add or change)
	 * @return array|WP_Error { html (cleaned candidate), css (the merged stylesheet source), check, warnings[], at, count, labels, same }
	 */
	public static function prepare( $page_id, $in ) {
		$html = isset( $in['html'] ) && is_string( $in['html'] ) ? trim( str_replace( array( "\r\n", "\r" ), "\n", $in['html'] ) ) : '';
		$css  = isset( $in['css'] ) && is_string( $in['css'] ) ? $in['css'] : '';
		if ( '' === $html ) {
			return new WP_Error( 'twd_sk_bad_input', 'Paste or write the HTML for the section first.' );
		}
		if ( strlen( $html ) > TWD_SK_Store::MAX_BYTES ) {
			return new WP_Error( 'twd_sk_too_large', 'That HTML is larger than the ' . ( TWD_SK_Store::MAX_BYTES / 1024 ) . ' KB limit for a page.' );
		}
		if ( preg_match( '/<\s*(script|iframe|form|style|object|embed)\b/i', $html, $m ) ) {
			return new WP_Error( 'twd_sk_bad_input', 'The HTML holds a <' . strtolower( $m[1] ) . '>, which a page can never carry. Take it out (an Embed section is the way to show outside content).' );
		}
		$merged = TWD_SK_Css::merge( TWD_SK_Css::current()['source'], $css );
		$check  = TWD_SK_Css::check( $merged );
		if ( ! $check['ok'] ) {
			return new WP_Error( 'twd_sk_css_invalid', 'The CSS was not accepted: ' . implode( ' ', array_slice( $check['errors'], 0, 2 ) ) . ( count( $check['errors'] ) > 2 ? ' (and ' . ( count( $check['errors'] ) - 2 ) . ' more)' : '' ), array( 'errors' => $check['errors'] ) );
		}

		$current = TWD_SK_Store::get_current( (int) $page_id );
		$parts   = TWD_SK_Sections::split( $current );
		$target  = isset( $in['target'] ) && 'page' === $in['target'] ? 'page' : 'section';
		$new     = self::mark_custom( $html );
		$new_parts = TWD_SK_Sections::split( $new );
		$count   = count( $new_parts['sections'] );
		$warnings = array();

		if ( 'page' === $target ) {
			$candidate = $new;
			$at        = 0;
		} else {
			$index = isset( $in['index'] ) && is_numeric( $in['index'] ) ? (int) $in['index'] : -1;
			if ( $index < 0 || ! isset( $parts['sections'][ $index ] ) ) {
				return new WP_Error( 'twd_sk_bad_input', 'Choose the section to replace first (click inside it).' );
			}
			$old = $parts['sections'][ $index ];
			if ( 'notice' === TWD_SK_Sections::type_of( $old ) ) {
				return new WP_Error( 'twd_sk_bad_input', 'The safety notice is kept as it is. It cannot be replaced.' );
			}
			if ( 0 !== substr_count( strtolower( $old ), '<h1' ) && 0 === substr_count( strtolower( $new ), '<h1' ) ) {
				$warnings[] = 'The page\'s main heading (h1) was in this section and the new HTML has none. Put the page title back as an <h1> or the page will have no main heading.';
			}
			$sections = $parts['sections'];
			array_splice( $sections, $index, 1, $new_parts['sections'] );
			$gaps = $parts['gaps'];
			// One gap per new section: the old gap before, then line breaks between the added ones.
			if ( $count > 1 ) {
				array_splice( $gaps, $index + 1, 0, array_fill( 0, $count - 1, "\n" ) );
			}
			$candidate = TWD_SK_Sections::join( $gaps, $sections );
			$at        = $index;
		}
		if ( count( TWD_SK_Sections::split( $candidate )['sections'] ) > TWD_SK_Sections::MAX_SECTIONS ) {
			return new WP_Error( 'twd_sk_too_large', 'That would give the page more than ' . TWD_SK_Sections::MAX_SECTIONS . ' sections.' );
		}

		$result = TWD_SK_Css::with_classes( $check['classes'], function () use ( $candidate ) {
			return TWD_SK_Sanitizer::clean_with_report( $candidate );
		} );
		$clean = $result['html'];
		if ( '' === $clean ) {
			return new WP_Error( 'twd_sk_empty', 'Nothing was left after cleaning, so there is nothing to show.' );
		}
		if ( strlen( $clean ) > TWD_SK_Store::MAX_BYTES ) {
			return new WP_Error( 'twd_sk_too_large', 'The page would be larger than the ' . ( TWD_SK_Store::MAX_BYTES / 1024 ) . ' KB limit.' );
		}

		// What the cleaner took out, in plain words.
		$dropped = array_values( array_unique( array_map( 'strval', (array) $result['report']['removed']['classes'] ) ) );
		if ( $dropped ) {
			$warnings[] = 'The cleaner dropped the class' . ( 1 === count( $dropped ) ? '' : 'es' ) . ' ' . implode( ', ', array_slice( $dropped, 0, 8 ) ) . ( count( $dropped ) > 8 ? ' and more' : '' ) . ': only classes the site stylesheet defines (c-...) and the kit\'s own classes may be used. Add a rule for a class, or take it off the element.';
		}
		$tags = array_values( array_unique( array_map( 'strval', (array) $result['report']['removed']['tags'] ) ) );
		$tags = array_diff( $tags, array( 'comment' ) );
		if ( $tags ) {
			$warnings[] = 'The cleaner removed: ' . implode( ', ', array_slice( $tags, 0, 6 ) ) . '.';
		}
		$attrs = array_values( array_unique( array_map( 'strval', (array) $result['report']['removed']['attributes'] ) ) );
		if ( $attrs ) {
			$warnings[] = 'Attributes removed: ' . implode( ', ', array_slice( $attrs, 0, 6 ) ) . ( count( $attrs ) > 6 ? ' and more' : '' ) . ' (style attributes and anything but class, id, href, src, alt, width, height and the two edit labels go).';
		}

		// The labels clients click on, counted in the new section(s) after cleaning.
		$clean_parts = TWD_SK_Sections::split( $clean );
		$new_clean   = 'page' === $target ? $clean_parts['sections'] : array_slice( $clean_parts['sections'], $at, $count );
		$labels      = self::labels( implode( "\n", $new_clean ) );
		$has_custom  = false !== strpos( implode( "\n", $new_clean ), TWD_SK_Css::CUSTOM_CLASS );
		if ( $has_custom && 0 === $labels['text'] && 0 === $labels['pics'] ) {
			$warnings[] = 'Clients will not be able to change anything here: no words or picture in this section carry a label. Add data-twd-edit="Heading" (or another plain name) to each heading, paragraph or list item they may edit, and data-twd-pic="Photo" to each picture.';
		} elseif ( $has_custom && 0 === $labels['text'] ) {
			$warnings[] = 'No words in this section are labelled for clients (data-twd-edit): they will be able to change only the picture' . ( 1 === $labels['pics'] ? '' : 's' ) . '.';
		}

		// Headings: one h1, no skipped levels; descriptions and links from the quality check.
		$h1s = preg_match_all( '/<h1\b/i', $clean );
		if ( 0 === $h1s ) {
			$warnings[] = 'The page has no main heading (h1). The first section should hold the page title as an <h1>.';
		}
		foreach ( (array) $result['report']['removed']['tags'] as $tag ) {
			if ( 'h1 (demoted to h2)' === $tag ) {
				$warnings[] = 'A second <h1> was made an <h2>: a page has one main heading.';
				break;
			}
		}
		foreach ( TWD_SK_Quality::check( $clean ) as $q ) {
			$warnings[] = $q['label'];
		}
		foreach ( $check['warnings'] as $w ) {
			$warnings[] = $w;
		}
		// Rules no page would use once this is applied.
		$pages = TWD_SK_Css::pages_html();
		$pages[ (int) $page_id ] = $clean;
		$unused = TWD_SK_Css::unused_rules( $pages, $check['source'] );
		if ( $unused ) {
			$warnings[] = count( $unused ) . ' stylesheet rule' . ( 1 === count( $unused ) ? ' is' : 's are' ) . ' used by no page: ' . implode( '; ', array_slice( $unused, 0, 5 ) ) . ( count( $unused ) > 5 ? '; and more' : '' ) . '.';
		}

		return array(
			'html'     => $clean,
			'css'      => $check['source'],
			'check'    => $check,
			'warnings' => array_values( array_unique( $warnings ) ),
			'at'       => $at,
			'count'    => $count,
			'labels'   => $labels,
			'same'     => trim( $clean ) === trim( str_replace( array( "\r\n", "\r" ), "\n", $current ) ) && $check['source'] === TWD_SK_Css::current()['source'],
			'report'   => $result['report'],
		);
	}

	/** A preview of the candidate, with the candidate stylesheet, for the normal preview frame. */
	public static function preview( $page_id, $in, $user_id ) {
		$out = self::prepare( $page_id, $in );
		if ( is_wp_error( $out ) ) {
			return $out;
		}
		$token = TWD_SK_Preview::create( $user_id, $page_id, $out['html'], array( 'css' => $out['check']['built'] ) );
		return array(
			'token'       => $token,
			'preview_url' => TWD_SK_Preview::url( $page_id, $token ),
			'expires_in'  => TWD_SK_Preview::TTL,
			'bytes'       => strlen( $out['html'] ),
			'css_bytes'   => (int) $out['check']['bytes'],
			'css_budget'  => TWD_SK_Css::BUDGET,
			'warnings'    => $out['warnings'],
			'same'        => $out['same'],
			'at'          => $out['at'],
			'count'       => $out['count'],
			'labels'      => $out['labels'],
			'leftovers'   => $out['report']['leftovers'],
		);
	}

	/**
	 * Save the candidate: the stylesheet first, then the page as one version that records the stylesheet
	 * version. If the page cannot be saved the stylesheet goes back, so neither is ever left without the other.
	 */
	public static function apply( $page_id, $in, $base ) {
		if ( (int) $base !== (int) TWD_SK_Store::get_current_version_id( $page_id ) ) {
			return new WP_Error( 'twd_sk_conflict', 'This page changed since you opened it. Reload the page and try again.' );
		}
		$out = self::prepare( $page_id, $in );
		if ( is_wp_error( $out ) ) {
			return $out;
		}
		$target  = isset( $in['target'] ) && 'page' === $in['target'] ? 'page' : 'section';
		$label   = 'page' === $target ? 'Replaced the page with custom HTML' : 'Replaced section ' . ( $out['at'] + 1 ) . ' with custom HTML';
		$was_v   = TWD_SK_Css::version();
		$css_out = TWD_SK_Css::save( $out['css'], $label . ' (page ' . (int) $page_id . ')', (int) $page_id );
		if ( is_wp_error( $css_out ) ) {
			return $css_out;
		}
		$saved = TWD_SK_Store::save( $page_id, $out['html'], array( 'base_version' => (int) $base, 'note' => $label ) );
		if ( is_wp_error( $saved ) ) {
			if ( ! $css_out['unchanged'] ) {
				TWD_SK_Css::revert_to( $was_v, 'the page could not be saved', (int) $page_id );
			}
			return $saved;
		}
		$saved['warnings']  = $out['warnings'];
		$saved['css_v']     = (int) $css_out['v'];
		$saved['css_bytes'] = (int) $css_out['bytes'];
		$saved['at']        = (int) $out['at'];
		return $saved;
	}

	// -- Adapt: describe a change ---------------------------------------------------------

	/** The kit classes a custom section may put on its elements without styling them. */
	public static function shared_kit_classes() {
		return array( 'twd-sk-inner', 'twd-sk-inner--narrow', 'twd-sk-tone-base', 'twd-sk-tone-surface', 'twd-sk-tone-tint', 'twd-sk-tone-band', 'twd-sk-eyebrow', 'twd-sk-title', 'twd-sk-lead', 'twd-sk-accent', 'twd-sk-btn', 'twd-sk-btn--primary', 'twd-sk-btn--secondary', 'twd-sk-btn--outline', 'twd-sk-btn--light', 'twd-sk-card', 'twd-sk-card--accent', 'twd-sk-card__title', 'twd-sk-list', 'twd-sk-visually-hidden' );
	}

	/** The site's tokens by kind, for the AI: name lists it may use as var(--twd-site-NAME). */
	public static function token_guide() {
		$groups = array();
		foreach ( TWD_SK_Packs::schema() as $name => $kind ) {
			$key = is_array( $kind ) ? 'choice' : $kind;
			$groups[ $key ][] = $name;
		}
		$lines = array();
		$label = array( 'color' => 'Colours', 'font' => 'Fonts', 'length' => 'Sizes and spacing', 'weight' => 'Weights', 'line' => 'Line heights', 'shadow' => 'Shadows', 'position' => 'Picture position', 'filter' => 'Picture filter', 'choice' => 'Other' );
		foreach ( array( 'color', 'font', 'length', 'weight', 'line', 'shadow' ) as $kind ) {
			if ( ! empty( $groups[ $kind ] ) ) {
				$lines[] = $label[ $kind ] . ': ' . implode( ', ', $groups[ $kind ] );
			}
		}
		return implode( "\n", $lines );
	}

	public static function system_prompt() {
		$rules = array(
			'You change one section of a page on a small website built with the TWD Site Kit. The owner describes the change; you return the replacement section.',
			'Reply with ONE JSON object and nothing else, no code fence: {"html": "...", "css": "...", "what_changed": "..."}.',
			'html is the whole replacement section: one <section> element holding everything. Keep its first class (such as twd-sk-custom or the kit class it has).',
			'Change only what the instruction asks for. Keep every other word, link and picture address exactly as it is. Never invent a fact, name, price, qualification or claim; where a fact is missing write [PLACEHOLDER: what is needed] as plain text.',
			'Classes: first reuse classes the site stylesheet already defines and the kit classes listed. Add a new class only when you need one, named c-something (lower case letters, digits and hyphens). Never write a rule for a class that starts with twd-sk-.',
			'css holds only the rules to add or change ("" when none). A rule you repeat replaces the one in the stylesheet. Every selector names a c- class. Every colour is var(--twd-site-color-NAME) and every font var(--twd-site-font-heading) or var(--twd-site-font-body); font sizes are a site size token or rem. Never a typed colour (hex, rgb, a colour name), a px font size, !important, @import, url(), position fixed or sticky, an id selector, or anything but plain rules and @media (min-width/max-width) blocks.',
			'Label what the client may edit later: data-twd-edit="Plain label" on every heading, paragraph, list item and caption, and data-twd-pic="Plain label" on every picture. A labelled element holds only words and simple inline markup (strong, em, a, br).',
			'Headings stay in order: keep the heading levels the section has now. A page has one h1; never add one.',
			'No scripts, iframes, forms, style attributes, inline event handlers or data attributes other than the two labels. Pictures only from the site\'s media library, each with alt text. Plain hyphens, never long dashes.',
			'The result must look finished: when list items become cards or buttons set list-style: none and no padding on the list; nothing left over from the old layout.',
			'what_changed: two or three plain sentences a non-technical person understands, saying what changed and what did not.',
		);
		return implode( "\n", $rules );
	}

	public static function message( $section, $instruction ) {
		$css   = TWD_SK_Css::current()['source'];
		$css   = '' === trim( $css ) ? '(the site has no rules of its own yet)' : substr( $css, 0, self::MAX_CSS_IN_PROMPT );
		$parts = array(
			'Instruction from the owner: ' . $instruction,
			"The section as it is now:\n" . $section,
			"The site stylesheet (rules you may reuse; a selector you repeat replaces that rule):\n" . $css,
			'Kit classes you may put on elements without styling them: ' . implode( ', ', self::shared_kit_classes() ) . '.',
			"Site tokens (use as var(--twd-site-NAME)):\n" . self::token_guide(),
			'The site style is "' . TWD_SK_Packs::active_slug() . '". Headings use var(--twd-site-font-heading), body text var(--twd-site-font-body). Sections usually sit in a div.twd-sk-inner for the page column.',
		);
		if ( class_exists( 'TWD_SK_Facts' ) ) {
			$facts = TWD_SK_Facts::prompt_block();
			if ( is_string( $facts ) && '' !== trim( $facts ) ) {
				$parts[] = $facts;
			}
		}
		return implode( "\n\n", $parts );
	}

	/**
	 * Ask the AI to change one section, then run the reply through prepare() and a preview.
	 *
	 * @param array $in index, instruction
	 * @return array|WP_Error the preview payload plus html, css and what_changed
	 */
	public static function adapt( $page_id, $in, $user_id ) {
		if ( ! TWD_SK_AI::available() ) {
			return new WP_Error( 'twd_sk_ai_unavailable', 'AI is not set up on this site. Use Replace this section with HTML, or ask your web designer to add a key.' );
		}
		$instruction = isset( $in['instruction'] ) && is_string( $in['instruction'] ) ? trim( $in['instruction'] ) : '';
		if ( '' === $instruction ) {
			return new WP_Error( 'twd_sk_bad_input', 'Say what you would like changed first.' );
		}
		if ( strlen( $instruction ) > TWD_SK_AI::MAX_INSTRUCTION ) {
			return new WP_Error( 'twd_sk_bad_input', 'That is too long. Keep it under ' . TWD_SK_AI::MAX_INSTRUCTION . ' characters.' );
		}
		$instruction = TWD_SK_Profile::text( $instruction, TWD_SK_AI::MAX_INSTRUCTION );
		$parts       = TWD_SK_Sections::split( TWD_SK_Store::get_current( (int) $page_id ) );
		$index       = isset( $in['index'] ) && is_numeric( $in['index'] ) ? (int) $in['index'] : -1;
		if ( $index < 0 || ! isset( $parts['sections'][ $index ] ) ) {
			return new WP_Error( 'twd_sk_bad_input', 'Click inside the section you want changed first.' );
		}
		$section = $parts['sections'][ $index ];
		if ( 'notice' === TWD_SK_Sections::type_of( $section ) ) {
			return new WP_Error( 'twd_sk_bad_input', 'The safety notice is kept as it is.' );
		}
		TWD_SK_AI::allow_time();
		$reply = apply_filters( 'twd_ai_complete', null, self::system_prompt(), self::message( $section, $instruction ), self::TOKENS_ADAPT );
		if ( TWD_SK_AI::cut_off( $reply ) ) {
			return new WP_Error( 'twd_sk_ai_too_long', 'That is more than the AI can write in one go, so nothing was changed. Ask for a smaller change.' );
		}
		if ( is_wp_error( $reply ) ) {
			return $reply;
		}
		if ( ! is_string( $reply ) || '' === trim( $reply ) ) {
			return new WP_Error( 'twd_sk_ai_unavailable', 'The AI did not answer. Nothing was changed. Try again in a moment.' );
		}
		$data = self::read_reply( $reply );
		if ( ! $data ) {
			return new WP_Error( 'twd_sk_ai_bad_result', 'The AI did not send back a section that could be read. Nothing was changed. Try again, or word the request differently.' );
		}
		$out = self::preview( $page_id, array( 'target' => 'section', 'index' => $index, 'html' => $data['html'], 'css' => $data['css'] ), $user_id );
		if ( is_wp_error( $out ) ) {
			$errors = $out->get_error_data();
			$list   = is_array( $errors ) && isset( $errors['errors'] ) ? $errors['errors'] : array();
			return new WP_Error( $out->get_error_code(), 'The AI\'s reply was not accepted. ' . $out->get_error_message() . ' Nothing was changed. Try again, or open Replace this section to fix the HTML and CSS by hand.', array( 'errors' => $list, 'html' => $data['html'], 'css' => $data['css'] ) );
		}
		$out['html']         = $data['html'];
		$out['css']          = $data['css'];
		$out['what_changed'] = $data['what_changed'];
		return $out;
	}

	/** One JSON object with html (required), css and what_changed, out of whatever the AI wrote around it. */
	public static function read_reply( $reply ) {
		$text  = TWD_SK_AI::strip_fence( (string) $reply );
		$start = strpos( $text, '{' );
		$end   = strrpos( $text, '}' );
		$data  = false !== $start && false !== $end && $end > $start ? json_decode( substr( $text, $start, $end - $start + 1 ), true ) : null;
		if ( ! is_array( $data ) || ! isset( $data['html'] ) || ! is_string( $data['html'] ) || '' === trim( $data['html'] ) ) {
			return null;
		}
		$css  = isset( $data['css'] ) && is_string( $data['css'] ) ? $data['css'] : '';
		$what = isset( $data['what_changed'] ) && is_string( $data['what_changed'] ) ? TWD_SK_Profile::text( $data['what_changed'], 600 ) : '';
		return array( 'html' => trim( $data['html'] ), 'css' => trim( $css ), 'what_changed' => $what );
	}
}
