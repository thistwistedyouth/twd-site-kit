<?php
/**
 * TWD_SK_Plan: the one-box way to change a page (0.10.0).
 *
 * The person writes what they want in one box: one line or a list ("change the first paragraph to
 * ...", "add three boxes with glowing borders for ...", "add these testimonials as a slider at the
 * bottom: ..."). plan() asks the AI once to turn that into a short checklist of changes tied to the
 * page's sections: edit some sections, add a kit section after one, remove one, move one, or say what
 * the kit cannot do and the nearest thing it can. Every item is checked here against the page and the
 * registry before the person sees it.
 *
 * The person unticks anything they do not want, then each change runs on its own request (step()),
 * on a working copy of the page, so no single AI reply has to hold the whole page. Changes refer to
 * the sections as they were when planned; the working copy carries an order list that says where
 * each of those sections is now. Nothing is saved: the result goes to the usual preview, and Apply
 * saves it as one version with one Undo.
 *
 * Edits and new sections use the same code as Ask the AI and Add a section (TWD_SK_AI), with the
 * whole request passed as the facts the person supplied, so quotes and names they typed are used
 * word for word and nothing else new is allowed.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class TWD_SK_Plan {

	const MAX_REQUEST = 6000;
	const MAX_CHANGES = 12;
	const MAX_WHAT    = 1500;
	const TOKENS_PLAN = 3000;

	public static function system_prompt() {
		$out   = array();
		$out[] = 'You plan changes to one page of a ' . TWD_SK_Mode::w( 'persons' ) . ' website. The page is a list of numbered sections, each one of a fixed set of components. Read the request (one line, or a list) and turn it into a short list of changes. You do not write the page: each change is carried out separately afterwards.';
		$out[] = '';
		$out[] = '## Output';
		$out[] = 'Reply with one JSON object only, no code fence, no commentary:';
		$out[] = '{"changes":[{"do":"edit","sections":[2],"what":"..."},{"do":"add","after":3,"component":"services","variant":"twd-sk-services--glow","what":"..."},{"do":"remove","sections":[5]},{"do":"move","sections":[4],"after":1}],"cannot":[{"what":"...","nearest":"..."}]}';
		$out[] = '';
		$out[] = '## Rules';
		$out[] = '1. "edit" changes the words (and, if asked, the layout) inside sections that are already there. List every section number it touches. Put everything asked about the same section in one edit.';
		$out[] = '2. "add" puts one new section after section number "after". Use only a component and layout from the list given. Choose the closest layout to what was asked. Section 1 is always the hero, so a new section goes after 1 at the earliest. "At the bottom" means after the last section.';
		$out[] = '3. "remove" takes whole sections away. Never remove section 1.';
		$out[] = '4. "move" moves one section (never section 1) to after another section.';
		$out[] = '5. "what" says exactly what to write or change, clearly enough for someone who has not seen the request. Copy any wording, quotes, names, prices or details the person gave into it word for word. Never invent facts, quotes, reviews, names, prices or promises.';
		$out[] = '6. Anything the kit cannot do goes in "cannot", with the nearest thing it can do in "nearest" (or "" if nothing is close). For example: videos, maps embedded in the page, custom fonts, colours or sizes for one part of a page, pop-ups, code, forms other than the enquiry form, content from other websites. Colours and fonts for the whole site are changed in the Site tab, under Style.';
		$out[] = '7. At most ' . self::MAX_CHANGES . ' changes, in the order the person asked for them.';
		$out[] = '8. UK spelling. Never use em dashes or en dashes.';
		return implode( "\n", $out ) . "\n";
	}

	/** The page outline, the components that may be added, and the request. */
	public static function message( $html, $request ) {
		$out   = array();
		$out[] = 'The page, section by section (number. component: its heading or first words):';
		foreach ( TWD_SK_Sections::describe( $html ) as $s ) {
			$out[] = ( $s['index'] + 1 ) . '. ' . $s['type'] . ': ' . $s['label'] . ( $s['locked'] ? ' (its words are locked: the AI keeps them as they are)' : '' );
		}
		$out[] = '';
		$out[] = 'Components that may be added (id: what it is; layouts as "class": label):';
		foreach ( TWD_SK_Recipes::parts_menu() as $id => $c ) {
			if ( 'hero' === $id ) {
				continue;
			}
			$layouts = array();
			foreach ( $c['variants'] as $class => $label ) {
				$layouts[] = '"' . $class . '": ' . $label;
			}
			$out[] = '- ' . $id . ': ' . $c['description'] . ' Layouts: ' . implode( '; ', $layouts );
		}
		$out[] = '';
		$out[] = 'The request:';
		$out[] = $request;
		return implode( "\n", $out ) . "\n";
	}

	/** Where a pasted page or a reply from another AI goes (section 23f): not this box. */
	const WHERE_TO_PASTE = 'To paste a whole page or a reply from another AI, use Your Edits, a pencil, then Import a page or Replace this section.';

	/** Section 23f: does this text look like HTML (it starts with a tag, or holds a <section) rather than a request? */
	public static function looks_like_html( $text ) {
		$text = is_string( $text ) ? ltrim( $text ) : '';
		return '' !== $text && ( 1 === preg_match( '/^(?:```[a-z]*\s*)?<[a-zA-Z!]/', $text ) || false !== stripos( $text, '<section' ) );
	}

	private static function clean_request( $request ) {
		if ( ! is_string( $request ) || '' === trim( $request ) ) {
			return new WP_Error( 'twd_sk_bad_input', 'Say what you want changed first.' );
		}
		if ( self::looks_like_html( $request ) ) {
			return new WP_Error( 'twd_sk_bad_input', 'That looks like a page or HTML, not a request. ' . self::WHERE_TO_PASTE );
		}
		if ( strlen( $request ) > self::MAX_REQUEST ) {
			return new WP_Error( 'twd_sk_bad_input', 'That is too long. Keep it under ' . self::MAX_REQUEST . ' characters, or split it into two goes. ' . self::WHERE_TO_PASTE );
		}
		return TWD_SK_Facts::clean( $request );
	}

	/**
	 * Ask the AI for the checklist. Saves nothing.
	 *
	 * @return array|WP_Error { changes[], cannot[], dropped[], sections }
	 */
	public static function plan( $page_id, $request ) {
		if ( ! TWD_SK_AI::available() ) {
			return new WP_Error( 'twd_sk_ai_unavailable', 'AI is not set up on this site. Use the copy and paste way, or ask your web designer to add a key.' );
		}
		$request = self::clean_request( $request );
		if ( is_wp_error( $request ) ) {
			return $request;
		}
		$html  = TWD_SK_Store::get_current( (int) $page_id );
		$count = count( TWD_SK_Sections::split( $html )['sections'] );
		if ( 0 === $count ) {
			return new WP_Error( 'twd_sk_empty_page', 'This page has no sections yet. Add a page outline first.' );
		}
		$reply = apply_filters( 'twd_ai_complete', null, self::system_prompt(), self::message( $html, $request ), self::TOKENS_PLAN );
		if ( TWD_SK_AI::cut_off( $reply ) ) {
			return new WP_Error( 'twd_sk_ai_too_long', 'That is a lot to plan in one go. Split it into two shorter lists.' );
		}
		if ( is_wp_error( $reply ) ) {
			return $reply;
		}
		if ( ! is_string( $reply ) || '' === trim( $reply ) ) {
			return new WP_Error( 'twd_sk_ai_unavailable', 'The AI did not answer. Nothing was changed. Try again in a moment.' );
		}
		$text  = TWD_SK_AI::strip_fence( $reply );
		$start = strpos( $text, '{' );
		$end   = strrpos( $text, '}' );
		$data  = false !== $start && false !== $end && $end > $start ? json_decode( substr( $text, $start, $end - $start + 1 ), true ) : null;
		if ( ! is_array( $data ) ) {
			return new WP_Error( 'twd_sk_ai_bad_result', 'The AI did not send back a plan that could be read. Try again, or word the request differently.' );
		}
		$out = self::validate( $data, $html );
		if ( ! $out['changes'] && ! $out['cannot'] ) {
			return new WP_Error( 'twd_sk_ai_bad_result', 'The AI did not find anything it could change from that. Try saying which part of the page you mean.' );
		}
		return $out;
	}

	private static function ints( $value, $count, $min ) {
		$out = array();
		foreach ( is_array( $value ) ? $value : array( $value ) as $n ) {
			if ( ( is_int( $n ) || ( is_string( $n ) && ctype_digit( $n ) ) ) && (int) $n >= $min && (int) $n <= $count ) {
				$out[ (int) $n - 1 ] = (int) $n - 1;
			}
		}
		ksort( $out );
		return array_values( $out );
	}

	private static function text( $value, $max ) {
		return is_string( $value ) ? TWD_SK_Profile::text( $value, $max ) : '';
	}

	/**
	 * Check a plan against the page and the registry. Sections in the result are 0-based.
	 *
	 * @return array { changes[], cannot[], dropped[], sections }
	 */
	public static function validate( $data, $html ) {
		$described = TWD_SK_Sections::describe( $html );
		$count     = count( $described );
		$changes   = array();
		$dropped   = array();
		$list      = isset( $data['changes'] ) && is_array( $data['changes'] ) ? array_values( $data['changes'] ) : array();
		foreach ( $list as $n => $c ) {
			if ( count( $changes ) >= self::MAX_CHANGES ) {
				$dropped[] = 'Only the first ' . self::MAX_CHANGES . ' changes were kept.';
				break;
			}
			$c = self::check_change( $c, $count );
			if ( is_wp_error( $c ) ) {
				$dropped[] = 'Change ' . ( $n + 1 ) . ' was left out: ' . $c->get_error_message();
				continue;
			}
			$c['label'] = self::describe( $c, $described );
			$changes[]  = $c;
		}
		$cannot = array();
		foreach ( isset( $data['cannot'] ) && is_array( $data['cannot'] ) ? $data['cannot'] : array() as $c ) {
			$what = is_array( $c ) && isset( $c['what'] ) ? self::text( $c['what'], 300 ) : '';
			if ( '' !== $what && count( $cannot ) < self::MAX_CHANGES ) {
				$cannot[] = array( 'what' => $what, 'nearest' => is_array( $c ) && isset( $c['nearest'] ) ? self::text( $c['nearest'], 300 ) : '' );
			}
		}
		return array( 'changes' => $changes, 'cannot' => $cannot, 'dropped' => $dropped, 'sections' => $count );
	}

	/**
	 * One change, checked. Sections are 1-based going in and 0-based coming out.
	 *
	 * @return array|WP_Error { do, sections[], after, component, variant, what }
	 */
	public static function check_change( $c, $count, $zero_based = false ) {
		if ( ! is_array( $c ) || ! isset( $c['do'] ) || ! in_array( $c['do'], array( 'edit', 'add', 'remove', 'move' ), true ) ) {
			return new WP_Error( 'twd_sk_bad_input', 'it is not a kind of change the kit can make.' );
		}
		$shift = $zero_based ? 1 : 0;
		$do    = $c['do'];
		$what  = isset( $c['what'] ) ? self::text( $c['what'], self::MAX_WHAT ) : '';
		$secs  = isset( $c['sections'] ) ? self::ints( $zero_based ? array_map( function ( $v ) {
			return is_numeric( $v ) ? (int) $v + 1 : $v;
		}, (array) $c['sections'] ) : $c['sections'], $count, 'edit' === $do ? 1 : 2 ) : array();
		$after = isset( $c['after'] ) && is_numeric( $c['after'] ) ? (int) $c['after'] + $shift : 0;
		$out   = array( 'do' => $do, 'sections' => array(), 'after' => -1, 'component' => '', 'variant' => '', 'what' => $what );
		if ( 'edit' === $do ) {
			if ( ! $secs || '' === $what ) {
				return new WP_Error( 'twd_sk_bad_input', 'it did not say which section to change, or how.' );
			}
			$out['sections'] = $secs;
		} elseif ( 'add' === $do ) {
			$component = isset( $c['component'] ) && is_string( $c['component'] ) ? $c['component'] : '';
			$variant   = isset( $c['variant'] ) && is_string( $c['variant'] ) ? $c['variant'] : '';
			$reg       = TWD_SK_Registry::get( $component );
			if ( ! $reg || 'hero' === $component ) {
				return new WP_Error( 'twd_sk_bad_input', 'it asked for a kind of section the kit does not have.' );
			}
			if ( in_array( $component, TWD_SK_Recipes::admin_only_components(), true ) ) {
				return new WP_Error( 'twd_sk_bad_input', 'it asked for a section only an administrator can add by hand (an embed).' );
			}
			if ( '' !== $variant && ! isset( $reg['variants'][ $variant ] ) ) {
				$variant = '';
			}
			if ( $after < 1 || $after > $count ) {
				$after = $count;
			}
			$out['component'] = $component;
			$out['variant']   = $variant;
			$out['after']     = $after - 1;
		} elseif ( 'remove' === $do ) {
			if ( ! $secs ) {
				return new WP_Error( 'twd_sk_bad_input', 'the hero (section 1) cannot be removed, and no other section was named.' );
			}
			$out['sections'] = $secs;
		} else {
			if ( 1 !== count( $secs ) || $after < 1 || $after > $count || $after - 1 === $secs[0] ) {
				return new WP_Error( 'twd_sk_bad_input', 'it did not say clearly which section to move, or where.' );
			}
			$out['sections'] = $secs;
			$out['after']    = $after - 1;
		}
		return $out;
	}

	private static function name( $i, $described ) {
		if ( ! isset( $described[ $i ] ) ) {
			return 'section ' . ( $i + 1 );
		}
		$label = $described[ $i ]['label'];
		return 'section ' . ( $i + 1 ) . ' (' . $described[ $i ]['type'] . ( '' !== $label ? ': ' . $label : '' ) . ')';
	}

	/** One plain sentence for the checklist. */
	public static function describe( $c, $described ) {
		$names = array();
		foreach ( $c['sections'] as $i ) {
			$names[] = self::name( $i, $described );
		}
		switch ( $c['do'] ) {
			case 'edit':
				return 'Change ' . implode( ' and ', $names ) . ': ' . $c['what'];
			case 'add':
				$reg    = TWD_SK_Registry::get( $c['component'] );
				$layout = '' !== $c['variant'] ? ' (' . strtok( $reg['variants'][ $c['variant'] ]['label'], ':' ) . ')' : '';
				return 'Add a new ' . $reg['label'] . $layout . ' after ' . self::name( $c['after'], $described ) . ( '' !== $c['what'] ? ': ' . $c['what'] : '' );
			case 'remove':
				return 'Remove ' . implode( ' and ', $names );
			default:
				return 'Move ' . $names[0] . ' to after ' . self::name( $c['after'], $described );
		}
	}

	// -- Running one change -------------------------------------------------------------

	/**
	 * Run one change on the working copy. Saves nothing.
	 *
	 * @param int        $page_id
	 * @param string     $html    The working copy, or '' to start from the saved page.
	 * @param array|null $order   Where each planned-for section is now: its 0-based number, or "nN" for a new one.
	 * @param array      $change  One change, as plan() returned it (0-based).
	 * @param string     $request The whole request, used as the facts the person supplied.
	 * @return array|WP_Error { html, order, report[] }
	 */
	public static function step( $page_id, $html, $order, $change, $request ) {
		$request = self::clean_request( $request );
		if ( is_wp_error( $request ) ) {
			return $request;
		}
		$saved = TWD_SK_Store::get_current( (int) $page_id );
		$total = count( TWD_SK_Sections::split( $saved )['sections'] );
		if ( 0 === $total ) {
			return new WP_Error( 'twd_sk_empty_page', 'This page has no sections yet.' );
		}
		$change = self::check_change( $change, $total, true );
		if ( is_wp_error( $change ) ) {
			return new WP_Error( 'twd_sk_bad_input', 'That change could not be made: ' . $change->get_error_message() );
		}
		if ( ! is_string( $html ) || '' === trim( $html ) ) {
			$html  = $saved;
			$order = range( 0, $total - 1 );
		} else {
			if ( strlen( $html ) > TWD_SK_Store::MAX_BYTES ) {
				return new WP_Error( 'twd_sk_too_large', 'The page has grown larger than a page can be. Apply what you have, then carry on.' );
			}
			$html  = TWD_SK_Sanitizer::clean( $html );
			$order = self::check_order( $order, $total, count( TWD_SK_Sections::split( $html )['sections'] ) );
			if ( is_wp_error( $order ) ) {
				return $order;
			}
		}

		$at = array();
		foreach ( $change['sections'] as $i ) {
			$pos = array_search( $i, $order, true );
			if ( false === $pos ) {
				return array( 'html' => $html, 'order' => $order, 'report' => array( 'Skipped: section ' . ( $i + 1 ) . ' was removed by an earlier change.' ) );
			}
			$at[] = (int) $pos;
		}
		$after = -1;
		if ( $change['after'] >= 0 ) {
			$after = array_search( $change['after'], $order, true );
			if ( false === $after ) {
				// The section it was to follow has gone: put it at the end instead.
				$after = count( $order ) - 1;
			}
		}

		switch ( $change['do'] ) {
			case 'edit':
				$r = TWD_SK_AI::remix( $page_id, array( 'html' => $html, 'plan' => true, 'mode' => 'sections', 'indexes' => $at, 'instruction' => $change['what'], 'facts' => $request ) );
				if ( is_wp_error( $r ) ) {
					return $r;
				}
				return array( 'html' => $r['html'], 'order' => $order, 'report' => $r['report'] ? $r['report'] : array( 'Done.' ) );
			case 'add':
				$r = TWD_SK_AI::add_section( $page_id, array( 'html' => $html, 'plan' => true, 'after' => (int) $after, 'component' => $change['component'], 'variant' => $change['variant'], 'instruction' => $change['what'], 'facts' => $request, 'use_ai' => true ) );
				if ( is_wp_error( $r ) ) {
					return $r;
				}
				$new = 0;
				foreach ( $order as $token ) {
					if ( is_string( $token ) ) {
						$new = max( $new, (int) substr( $token, 1 ) );
					}
				}
				array_splice( $order, (int) $after + 1, 0, array( 'n' . ( $new + 1 ) ) );
				return array( 'html' => $r['html'], 'order' => $order, 'report' => $r['report'] );
			case 'remove':
				$parts = TWD_SK_Sections::split( $html );
				rsort( $at );
				foreach ( $at as $pos ) {
					if ( 0 === $pos ) {
						continue;
					}
					array_splice( $parts['sections'], $pos, 1 );
					array_splice( $parts['gaps'], $pos + 1, 1 );
					array_splice( $order, $pos, 1 );
				}
				return array( 'html' => TWD_SK_Sections::join( $parts['gaps'], $parts['sections'] ), 'order' => $order, 'report' => array( 'Removed.' ) );
			default:
				$parts = TWD_SK_Sections::split( $html );
				$from  = $at[0];
				if ( 0 === $from ) {
					return new WP_Error( 'twd_sk_bad_input', 'The hero stays at the top.' );
				}
				$section = $parts['sections'][ $from ];
				$token   = $order[ $from ];
				array_splice( $parts['sections'], $from, 1 );
				array_splice( $order, $from, 1 );
				$to = (int) $after < $from ? (int) $after + 1 : (int) $after;
				array_splice( $parts['sections'], $to, 0, array( $section ) );
				array_splice( $order, $to, 0, array( $token ) );
				$gaps = array_fill( 0, count( $parts['sections'] ) + 1, "\n" );
				return array( 'html' => TWD_SK_Sections::join( $gaps, $parts['sections'] ), 'order' => $order, 'report' => array( 'Moved.' ) );
		}
	}

	/** The order list from the browser, checked: one entry per section, each original section at most once. */
	private static function check_order( $order, $total, $sections ) {
		if ( ! is_array( $order ) || count( $order ) !== $sections ) {
			return new WP_Error( 'twd_sk_bad_input', 'The working copy of the page is out of step. Start the changes again.' );
		}
		$out  = array();
		$seen = array();
		foreach ( array_values( $order ) as $token ) {
			if ( ( is_int( $token ) || ( is_string( $token ) && ctype_digit( $token ) ) ) && (int) $token >= 0 && (int) $token < $total && ! isset( $seen[ (int) $token ] ) ) {
				$seen[ (int) $token ] = true;
				$out[]                = (int) $token;
			} elseif ( is_string( $token ) && 1 === preg_match( '/^n[1-9][0-9]{0,2}$/', $token ) ) {
				$out[] = $token;
			} else {
				return new WP_Error( 'twd_sk_bad_input', 'The working copy of the page is out of step. Start the changes again.' );
			}
		}
		return $out;
	}
}
