<?php
/**
 * TWD_SK_Css: the site's own stylesheet (0.15.0, section 20 of the owner's plan: Base, Adapt, Replace).
 *
 * A site keeps one extra stylesheet as data (option twd_sk_site_css), with 20 versions. It styles custom
 * sections: HTML an administrator pasted or the AI wrote in place of a kit section. Rules:
 *  - Only tokens. Every colour and font is var(--twd-site-TOKEN). A typed colour (#hex, rgb(), a colour name),
 *    a typed font family, a font size in px, !important, @import, url() to anything but the media library,
 *    expression(), a script and a fixed or sticky position are refused with a plain message. A check that fails
 *    applies nothing.
 *  - Custom classes start with "c-" and are the ONLY classes a rule may name: a rule can never restyle a kit
 *    class (that would change every page). The sanitiser allows a c- class on a page only while this
 *    stylesheet defines it (plus the registry's own classes), so a pasted class name cannot leak styles.
 *  - Every rule is scoped under .twd-sk-page when it is built, like the kit stylesheet, and printed inline
 *    after the kit stylesheet (one request, no file).
 *  - A size budget: the built stylesheet may not pass BUDGET bytes; a warning starts at WARN. unused_rules()
 *    says which rules no page uses.
 *  - Versions: save() appends a version (page, note, who, when). A page version records the stylesheet
 *    version it was saved with; follow_page() brings the stylesheet back with a page's Undo or Restore when
 *    every later stylesheet change came from that same page, so a page never loses the CSS its HTML needs.
 * No capability checks here: the REST layer and WP-CLI decide who may call these.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class TWD_SK_Css {

	const OPTION          = 'twd_sk_site_css';
	const OPTION_VERSIONS = 'twd_sk_site_css_versions';
	const KEEP            = 20;
	const MAX_SOURCE      = 60000; // bytes of source a check will read.
	const BUDGET          = 24000; // built bytes: refused above this.
	const WARN            = 16000; // built bytes: a warning from here.
	const CLASS_PREFIX    = 'c-';
	const CUSTOM_CLASS    = 'twd-sk-custom';
	const FILTER_BUILT    = 'twd_sk_site_css_built';
	const SCOPE           = '.twd-sk-page';

	/** Classes allowed while a candidate stylesheet is being checked (a preview), on top of the saved ones. */
	private static $extra = array();

	public static function init() {
		if ( function_exists( 'add_action' ) ) {
			add_action( 'wp_enqueue_scripts', array( __CLASS__, 'print_styles' ), 20 );
		}
	}

	// -- Reading ------------------------------------------------------------------------

	/** The saved stylesheet: v, source, built, classes, note, user, at. Empty values when there is none. */
	public static function current() {
		$raw = get_option( self::OPTION, array() );
		$out = array( 'v' => 0, 'source' => '', 'built' => '', 'classes' => array(), 'note' => '', 'user' => 0, 'at' => '' );
		if ( ! is_array( $raw ) ) {
			return $out;
		}
		foreach ( $out as $k => $default ) {
			if ( isset( $raw[ $k ] ) && gettype( $raw[ $k ] ) === gettype( $default ) ) {
				$out[ $k ] = $raw[ $k ];
			}
		}
		$out['classes'] = array_values( array_filter( array_map( 'strval', $out['classes'] ), array( __CLASS__, 'valid_class' ) ) );
		return $out;
	}

	public static function version() {
		return (int) self::current()['v'];
	}

	/** The classes the saved stylesheet defines, as class => true (for the sanitiser), plus any preview extras. */
	public static function allowed_classes() {
		$out = array();
		foreach ( self::current()['classes'] as $class ) {
			$out[ $class ] = true;
		}
		foreach ( self::$extra as $class ) {
			if ( self::valid_class( $class ) ) {
				$out[ $class ] = true;
			}
		}
		return $out;
	}

	/** Run $fn with these classes allowed too (a candidate being previewed), then put the allowlist back. */
	public static function with_classes( $classes, $fn ) {
		$was         = self::$extra;
		self::$extra = is_array( $classes ) ? array_values( $classes ) : array();
		try {
			return call_user_func( $fn );
		} finally {
			self::$extra = $was;
		}
	}

	public static function valid_class( $class ) {
		// Lower case letters and digits after "c-", joined by hyphens or underscores (c-intro, c-intro__title, c-intro--wide).
		return is_string( $class ) && 1 === preg_match( '/^c-[a-z0-9]+(?:[-_]+[a-z0-9]+)*$/', $class ) && strlen( $class ) <= 48;
	}

	/** Versions, newest first: v, note, user, at, page, bytes (never the source). */
	public static function versions() {
		$list = get_option( self::OPTION_VERSIONS, array() );
		$out  = array();
		if ( is_array( $list ) ) {
			foreach ( $list as $e ) {
				if ( is_array( $e ) && isset( $e['v'], $e['source'] ) ) {
					$out[] = array(
						'v'     => (int) $e['v'],
						'note'  => isset( $e['note'] ) ? (string) $e['note'] : '',
						'user'  => isset( $e['user'] ) ? (int) $e['user'] : 0,
						'at'    => isset( $e['at'] ) ? (string) $e['at'] : '',
						'page'  => isset( $e['page'] ) ? (int) $e['page'] : 0,
						'bytes' => strlen( (string) $e['source'] ),
					);
				}
			}
		}
		return $out;
	}

	/** One version's source, or null. Version 0 is the empty stylesheet. */
	public static function version_source( $v ) {
		if ( 0 === (int) $v ) {
			return '';
		}
		foreach ( (array) get_option( self::OPTION_VERSIONS, array() ) as $e ) {
			if ( is_array( $e ) && isset( $e['v'], $e['source'] ) && (int) $e['v'] === (int) $v ) {
				return (string) $e['source'];
			}
		}
		return null;
	}

	/** Size, budget and versions in plain terms, for the Site tab, the site report and the command line. */
	public static function status() {
		$cur   = self::current();
		$built = strlen( $cur['built'] );
		return array(
			'version'  => (int) $cur['v'],
			'bytes'    => $built,
			'budget'   => self::BUDGET,
			'warn_at'  => self::WARN,
			'classes'  => $cur['classes'],
			'rules'    => count( self::rules_of( $cur['source'] ) ),
			'summary'  => $built ? sprintf( 'The site stylesheet is %s of its %s budget (%d classes, version %d).', self::kb( $built ), self::kb( self::BUDGET ), count( $cur['classes'] ), (int) $cur['v'] ) : 'This site has no stylesheet of its own yet.',
			'versions' => self::versions(),
		);
	}

	private static function kb( $bytes ) {
		return ( $bytes >= 1024 ? round( $bytes / 1024, 1 ) . ' KB' : $bytes . ' bytes' );
	}

	// -- Checking -------------------------------------------------------------------------

	/**
	 * Read a stylesheet and say what is wrong with it. Nothing is saved.
	 *
	 * @return array { ok, errors[], warnings[], built, classes[], bytes, rules[] (each: media, selectors[], decls[]), source (normalised) }
	 */
	public static function check( $source ) {
		$out = array( 'ok' => false, 'errors' => array(), 'warnings' => array(), 'built' => '', 'classes' => array(), 'bytes' => 0, 'rules' => array(), 'source' => '' );
		if ( ! is_string( $source ) ) {
			$out['errors'][] = 'The stylesheet must be text.';
			return $out;
		}
		$source = str_replace( array( "\r\n", "\r" ), "\n", $source );
		if ( strlen( $source ) > self::MAX_SOURCE ) {
			$out['errors'][] = 'That stylesheet is larger than ' . self::kb( self::MAX_SOURCE ) . '. Keep the rules a site needs and drop the rest.';
			return $out;
		}
		if ( '' === trim( $source ) ) {
			$out['ok'] = true;
			return $out;
		}
		foreach ( self::forbidden_text( $source ) as $e ) {
			$out['errors'][] = $e;
		}
		if ( $out['errors'] ) {
			return $out;
		}
		$rules = self::parse( self::strip_comments( $source ), $out['errors'] );
		if ( $out['errors'] ) {
			return $out;
		}
		$seen = array();
		foreach ( $rules as $i => $rule ) {
			$where = ( '' !== $rule['media'] ? '@media ' . $rule['media'] . ' ' : '' ) . implode( ', ', $rule['selectors'] );
			foreach ( $rule['selectors'] as $sel ) {
				foreach ( self::check_selector( $sel ) as $e ) {
					$out['errors'][] = $e;
				}
			}
			if ( '' !== $rule['media'] && ! self::valid_media( $rule['media'] ) ) {
				$out['errors'][] = 'The media query "@media ' . $rule['media'] . '" is not allowed. Use (min-width: 768px), (max-width: 767px), (prefers-reduced-motion: reduce) or (hover: hover).';
			}
			if ( ! $rule['decls'] ) {
				$out['warnings'][] = 'The rule "' . $where . '" has no declarations.';
			}
			foreach ( $rule['decls'] as $d ) {
				foreach ( self::check_declaration( $d[0], $d[1], $where ) as $e ) {
					$out['errors'][] = $e;
				}
			}
			$key = $rule['media'] . '|' . implode( ',', $rule['selectors'] );
			if ( isset( $seen[ $key ] ) ) {
				$out['warnings'][] = 'The rule "' . $where . '" appears more than once. Merge them into one.';
			}
			$seen[ $key ] = true;
			foreach ( $rule['selectors'] as $sel ) {
				if ( preg_match_all( '/\.(c-[a-z0-9_-]+)/', preg_replace( self::attribute_pattern(), '', $sel ), $m ) ) {
					foreach ( $m[1] as $class ) {
						$out['classes'][ $class ] = $class;
					}
				}
			}
		}
		if ( $out['errors'] ) {
			$out['classes'] = array();
			return $out;
		}
		$out['rules']   = $rules;
		$out['classes'] = array_values( $out['classes'] );
		$out['built']   = self::build( $rules );
		$out['bytes']   = strlen( $out['built'] );
		$out['source']  = self::serialise( $rules );
		if ( $out['bytes'] > self::BUDGET ) {
			$out['errors'][] = 'The site stylesheet would be ' . self::kb( $out['bytes'] ) . ', over its ' . self::kb( self::BUDGET ) . ' budget. Reuse classes that exist, or take out rules no page uses.';
			return $out;
		}
		if ( $out['bytes'] >= self::WARN ) {
			$out['warnings'][] = 'The site stylesheet is ' . self::kb( $out['bytes'] ) . ' of its ' . self::kb( self::BUDGET ) . ' budget. Reuse classes where you can.';
		}
		$out['ok'] = true;
		return $out;
	}

	/** Words that have no place in a stylesheet, found before anything is parsed. */
	private static function forbidden_text( $source ) {
		$errors = array();
		$lower  = strtolower( $source );
		$bad    = array(
			'!important'   => '!important is not allowed: the site stylesheet is scoped to the page and needs none.',
			'@import'      => '@import is not allowed. Fonts and colours come from the site\'s own tokens.',
			'expression('  => 'expression() is not allowed.',
			'javascript:'  => 'javascript: is not allowed.',
			'-moz-binding' => '-moz-binding is not allowed.',
			'behavior:'    => 'behavior is not allowed.',
			'data:'        => 'data: addresses are not allowed. Put the picture in the media library.',
			'<'            => 'The stylesheet holds a "<", so it looks like HTML or a script. Paste only CSS here.',
			'\\'           => 'Backslash escapes are not allowed in the site stylesheet.',
			'@font-face'   => '@font-face is not allowed. The site uses the fonts of its style.',
			'@keyframes'   => '@keyframes is not allowed in the site stylesheet.',
			'@supports'    => '@supports is not allowed in the site stylesheet.',
			'@layer'       => '@layer is not allowed in the site stylesheet.',
			'@container'   => '@container is not allowed in the site stylesheet.',
		);
		foreach ( $bad as $needle => $message ) {
			if ( false !== strpos( $lower, $needle ) ) {
				$errors[] = $message;
			}
		}
		if ( preg_match( '/url\(\s*["\']?\s*([^"\')]*)/i', $source, $m ) ) {
			$target = trim( $m[1] );
			$library = '/wp-content/' . 'uploads/';
			if ( ! preg_match( '#^(?:https://[a-z0-9.-]+)?' . preg_quote( $library, '#' ) . '[^\s"\']+$#i', $target ) ) {
				$errors[] = 'url() may point only to a picture in this site\'s media library (' . $library . '...), not "' . substr( $target, 0, 60 ) . '".';
			}
		}
		return $errors;
	}

	private static function strip_comments( $source ) {
		return preg_replace( '#/\*.*?\*/#s', ' ', $source );
	}

	/**
	 * Cut a stylesheet into rules. Each: media (prelude or ''), selectors (list), decls (list of [name, value]).
	 * @media blocks one level deep are allowed; any other at-rule, deeper nesting and stray text are errors.
	 */
	private static function parse( $css, array &$errors, $media = '' ) {
		$rules = array();
		$len   = strlen( $css );
		$i     = 0;
		while ( $i < $len ) {
			$open = strpos( $css, '{', $i );
			if ( false === $open ) {
				if ( '' !== trim( substr( $css, $i ) ) ) {
					$errors[] = 'Text after the last rule could not be read: "' . self::shorten( substr( $css, $i ) ) . '". Every rule needs { and }.';
				}
				break;
			}
			$prelude = trim( substr( $css, $i, $open - $i ) );
			$close   = self::matching_brace( $css, $open );
			if ( false === $close ) {
				$errors[] = 'A rule is missing its closing }: "' . self::shorten( $prelude ) . '".';
				break;
			}
			$inner = substr( $css, $open + 1, $close - $open - 1 );
			$i     = $close + 1;
			if ( '' === $prelude ) {
				$errors[] = 'A rule has no selector before its {.';
				continue;
			}
			if ( false !== strpos( $prelude, '}' ) || false !== strpos( $prelude, ';' ) ) {
				$errors[] = 'Text before a rule could not be read: "' . self::shorten( $prelude ) . '". Check for a missing } or ;.';
				continue;
			}
			if ( '@' === $prelude[0] ) {
				if ( 0 !== stripos( $prelude, '@media' ) ) {
					$errors[] = 'The rule "' . self::shorten( $prelude ) . '" is not allowed. Only @media is.';
					continue;
				}
				if ( '' !== $media ) {
					$errors[] = 'A @media block inside another @media block is not allowed.';
					continue;
				}
				$query = trim( substr( $prelude, 6 ) );
				$query = preg_replace( '/\s+/', ' ', $query );
				foreach ( self::parse( $inner, $errors, $query ) as $r ) {
					$rules[] = $r;
				}
				continue;
			}
			if ( false !== strpos( $inner, '{' ) ) {
				$errors[] = 'The rule "' . self::shorten( $prelude ) . '" holds another rule inside it. Nested rules are not allowed; write each rule on its own.';
				continue;
			}
			$selectors = array();
			foreach ( explode( ',', $prelude ) as $sel ) {
				$sel = trim( preg_replace( '/\s+/', ' ', $sel ) );
				if ( '' !== $sel ) {
					$selectors[] = $sel;
				}
			}
			$decls = array();
			foreach ( self::split_declarations( $inner ) as $d ) {
				$d = trim( $d );
				if ( '' === $d ) {
					continue;
				}
				$colon = strpos( $d, ':' );
				if ( false === $colon ) {
					$errors[] = 'In "' . self::shorten( $prelude ) . '": "' . self::shorten( $d ) . '" is not a declaration (name: value).';
					continue;
				}
				$name  = strtolower( trim( substr( $d, 0, $colon ) ) );
				$value = trim( preg_replace( '/\s+/', ' ', substr( $d, $colon + 1 ) ) );
				$decls[] = array( $name, $value );
			}
			$rules[] = array( 'media' => $media, 'selectors' => $selectors, 'decls' => $decls );
		}
		return $rules;
	}

	/** The index of the } that closes the { at $open, skipping quoted strings; false if there is none. */
	private static function matching_brace( $css, $open ) {
		$depth = 0;
		$len   = strlen( $css );
		$quote = '';
		for ( $i = $open; $i < $len; $i++ ) {
			$ch = $css[ $i ];
			if ( '' !== $quote ) {
				if ( $ch === $quote ) {
					$quote = '';
				}
				continue;
			}
			if ( '"' === $ch || "'" === $ch ) {
				$quote = $ch;
			} elseif ( '{' === $ch ) {
				$depth++;
			} elseif ( '}' === $ch ) {
				$depth--;
				if ( 0 === $depth ) {
					return $i;
				}
			}
		}
		return false;
	}

	/** Split on ; outside quotes and brackets. */
	private static function split_declarations( $text ) {
		$out   = array();
		$cur   = '';
		$quote = '';
		$depth = 0;
		$len   = strlen( $text );
		for ( $i = 0; $i < $len; $i++ ) {
			$ch = $text[ $i ];
			if ( '' !== $quote ) {
				$cur .= $ch;
				if ( $ch === $quote ) {
					$quote = '';
				}
				continue;
			}
			if ( '"' === $ch || "'" === $ch ) {
				$quote = $ch;
			} elseif ( '(' === $ch ) {
				$depth++;
			} elseif ( ')' === $ch ) {
				$depth--;
			} elseif ( ';' === $ch && $depth <= 0 ) {
				$out[] = $cur;
				$cur   = '';
				continue;
			}
			$cur .= $ch;
		}
		$out[] = $cur;
		return $out;
	}

	private static function shorten( $text ) {
		$text = trim( preg_replace( '/\s+/', ' ', (string) $text ) );
		return strlen( $text ) > 60 ? substr( $text, 0, 57 ) . '...' : $text;
	}

	/** Element names a selector may use: the tags the sanitiser keeps. */
	private static function selector_tags() {
		return array( 'div', 'section', 'article', 'aside', 'header', 'footer', 'p', 'span', 'h1', 'h2', 'h3', 'h4', 'h5', 'h6', 'ul', 'ol', 'li', 'dl', 'dt', 'dd', 'blockquote', 'figure', 'figcaption', 'cite', 'q', 'a', 'img', 'strong', 'em', 'b', 'i', 'u', 'small', 'sub', 'sup', 'mark', 'br', 'hr', 'details', 'summary' );
	}

	/** One selector: c- classes, the allowed tags, a few pseudo-classes and ::before/::after, and combinators. */
	private static function check_selector( $sel ) {
		$errors = array();
		// Section 23f: attribute selectors are allowed in the plain shapes an accordion or a tab needs: [open], [href],
		// [data-x="y"], [aria-expanded="true"] (with ~= |= ^= $= *=). Anything else in brackets is refused. The rest of
		// the checks run on the selector with them taken out (so [href^="#"] is not taken for an id).
		$sel_plain = preg_replace( self::attribute_pattern(), '', $sel );
		if ( false !== strpos( $sel_plain, '[' ) || false !== strpos( $sel_plain, ']' ) ) {
			$errors[] = 'The selector "' . $sel . '" uses an attribute that could not be read. Write it as [name] or [name="value"], for example [open] or [href^="#"].';
			return $errors;
		}
		if ( false !== strpos( $sel_plain, '#' ) ) {
			$errors[] = 'The selector "' . $sel . '" uses an id (#). Use a c- class instead.';
			return $errors;
		}
		if ( false !== strpos( $sel_plain, '*' ) ) {
			$errors[] = 'The selector "' . $sel . '" uses *. Name the elements instead.';
			return $errors;
		}
		if ( preg_match( '/\.(twd-[a-z0-9_-]*)/i', $sel_plain, $m ) ) {
			$errors[] = 'The selector "' . $sel . '" styles the kit\'s own class .' . $m[1] . ', which would change every page. Give the element a c- class and style that.';
			return $errors;
		}
		if ( ! preg_match( '/\.c-[a-z0-9_-]+/', $sel_plain ) ) {
			$errors[] = 'The selector "' . $sel . '" names no c- class. Every rule needs one, so it can only reach the custom section it is for.';
			return $errors;
		}
		// Take the known pieces out; anything left is not allowed.
		// Longer names first: an alternation that tried "focus" before "focus-visible" left "-visible" behind (found by
		// the first real import, session 8).
		$rest = preg_replace( '/::?(-webkit-details-marker|before|after|marker|hover|focus-visible|focus-within|focus|active|visited|first-child|last-child|only-child|first-of-type|last-of-type|empty|checked|disabled|open)\b/', ' ', $sel_plain );
		$rest = preg_replace( '/:nth-(?:last-)?(?:child|of-type)\(\s*(?:odd|even|\d+|\d*n(?:\s*[+-]\s*\d+)?)\s*\)/', ' ', $rest );
		$rest = preg_replace( '/:not\(\s*\.c-[a-z0-9_-]+\s*\)/', ' ', $rest );
		$rest = preg_replace( '/\.c-[a-z0-9_-]+/', ' ', $rest );
		$rest = preg_replace( '/\b(' . implode( '|', self::selector_tags() ) . ')\b/', ' ', $rest );
		$rest = preg_replace( '/[\s>+~]+/', '', $rest );
		if ( '' !== $rest ) {
			$errors[] = 'The selector "' . $sel . '" holds something not allowed ("' . self::shorten( $rest ) . '"). Use c- classes, the usual element names, :hover and the like, ::before and ::after.';
		}
		foreach ( preg_match_all( '/\.(c-[a-z0-9_-]+)/', $sel_plain, $m ) ? $m[1] : array() as $class ) {
			if ( ! self::valid_class( $class ) ) {
				$errors[] = 'The class .' . $class . ' is not a valid name: lower case letters and digits after "c-", joined by hyphens or underscores, at most 48 characters.';
			}
		}
		return $errors;
	}

	/** The attribute selectors a rule may use (section 23f): [name] or [name op "value"], no spaces, no brackets inside. */
	private static function attribute_pattern() {
		return '/\[[a-zA-Z][a-zA-Z0-9_-]*(?:[~|^$*]?=(?:"[^"\[\]]*"|\'[^\'\[\]]*\'|[A-Za-z0-9_-]+))?\]/';
	}

	/**
	 * Section 23b: the nearest real token to a misspelt one, or '' when nothing is close (edit distance over 4).
	 * The comparison is on the name after --twd-site-.
	 */
	public static function nearest_token( $name ) {
		$name = strtolower( (string) $name );
		$best = '';
		$dist = 5;
		foreach ( TWD_SK_Packs::token_names() as $known ) {
			$d = levenshtein( $name, $known );
			if ( $d < $dist ) {
				$dist = $d;
				$best = $known;
			}
		}
		if ( '' === $best ) {
			// A family hint: the same first word (color, size, radius ...) with a close rest.
			$head = strstr( $name, '-', true );
			foreach ( TWD_SK_Packs::token_names() as $known ) {
				if ( false !== $head && 0 === strpos( $known, $head . '-' ) && levenshtein( substr( $name, strlen( $head ) ), substr( $known, strlen( $head ) ) ) <= 6 ) {
					return $known;
				}
			}
		}
		return $best;
	}

	/**
	 * Section 23b: the c- classes whose rules some added CSS would CHANGE: a rule with the same media and selectors
	 * already in $current with different declarations. Identical rules and new rules are not collisions.
	 *
	 * @return array class => the selector list of the colliding rule
	 */
	public static function collisions( $added, $current = null ) {
		$current = null === $current ? self::current()['source'] : $current;
		$have    = array();
		foreach ( self::rules_of( $current ) as $rule ) {
			$have[ self::rule_key( $rule ) ] = $rule;
		}
		$out = array();
		foreach ( self::rules_of( $added ) as $rule ) {
			$key = self::rule_key( $rule );
			if ( ! isset( $have[ $key ] ) || $have[ $key ]['decls'] === $rule['decls'] ) {
				continue;
			}
			foreach ( $rule['selectors'] as $sel ) {
				if ( preg_match_all( '/\.(c-[a-z0-9_-]+)/', $sel, $m ) ) {
					foreach ( $m[1] as $class ) {
						$out[ $class ] = implode( ', ', $rule['selectors'] );
					}
				}
			}
		}
		return $out;
	}

	/** Section 23b: which of these pages use each class (class => list of page ids), from their current HTML. */
	public static function class_pages( $classes, $pages_html ) {
		$out = array();
		foreach ( (array) $pages_html as $pid => $html ) {
			if ( ! preg_match_all( '/class="([^"]*)"/', (string) $html, $m ) ) {
				continue;
			}
			$used = array();
			foreach ( $m[1] as $list ) {
				foreach ( preg_split( '/\s+/', trim( $list ) ) as $c ) {
					$used[ $c ] = true;
				}
			}
			foreach ( (array) $classes as $class ) {
				if ( isset( $used[ $class ] ) ) {
					$out[ $class ][] = (int) $pid;
				}
			}
		}
		return $out;
	}

	private static function valid_media( $query ) {
		$q = strtolower( trim( $query ) );
		if ( in_array( $q, array( '(prefers-reduced-motion: reduce)', '(prefers-reduced-motion:reduce)', '(hover: hover)', '(hover:hover)', '(hover: none)', '(hover:none)' ), true ) ) {
			return true;
		}
		$one = '\(\s*(?:min|max)-width\s*:\s*\d+(?:\.\d+)?(?:px|em|rem)\s*\)';
		return 1 === preg_match( '/^(?:screen\s+and\s+)?' . $one . '(?:\s+and\s+' . $one . ')?$/', $q );
	}

	/** Properties a rule may set. */
	private static function allowed_properties() {
		static $list = null;
		if ( null === $list ) {
			$names = array(
				'display', 'position', 'top', 'right', 'bottom', 'left', 'inset', 'z-index', 'float', 'clear',
				'flex', 'flex-direction', 'flex-wrap', 'flex-flow', 'flex-grow', 'flex-shrink', 'flex-basis',
				'grid', 'grid-template', 'grid-template-columns', 'grid-template-rows', 'grid-template-areas', 'grid-area',
				'grid-column', 'grid-row', 'grid-column-start', 'grid-column-end', 'grid-row-start', 'grid-row-end', 'grid-auto-flow', 'grid-auto-rows', 'grid-auto-columns',
				'gap', 'row-gap', 'column-gap', 'align-items', 'align-content', 'align-self', 'justify-content', 'justify-items', 'justify-self', 'place-items', 'place-content', 'place-self', 'order',
				'width', 'height', 'min-width', 'min-height', 'max-width', 'max-height', 'aspect-ratio', 'object-fit', 'object-position',
				'margin', 'margin-top', 'margin-right', 'margin-bottom', 'margin-left', 'margin-inline', 'margin-block',
				'padding', 'padding-top', 'padding-right', 'padding-bottom', 'padding-left', 'padding-inline', 'padding-block',
				'border', 'border-top', 'border-right', 'border-bottom', 'border-left', 'border-color', 'border-width', 'border-style',
				'border-top-color', 'border-right-color', 'border-bottom-color', 'border-left-color',
				'border-top-width', 'border-right-width', 'border-bottom-width', 'border-left-width',
				'border-radius', 'border-top-left-radius', 'border-top-right-radius', 'border-bottom-left-radius', 'border-bottom-right-radius',
				'outline', 'outline-color', 'outline-width', 'outline-style', 'outline-offset', 'box-shadow', 'text-shadow',
				'background', 'background-color', 'background-image', 'background-size', 'background-position', 'background-repeat', 'background-attachment', 'background-clip', 'background-origin',
				'color', 'font', 'font-family', 'font-size', 'font-weight', 'font-style', 'font-variant', 'font-feature-settings', 'font-variation-settings', 'font-stretch',
				'line-height', 'letter-spacing', 'word-spacing', 'text-align', 'text-transform', 'text-decoration', 'text-decoration-color', 'text-decoration-line', 'text-decoration-style', 'text-decoration-thickness', 'text-underline-offset', 'text-indent', 'text-overflow', 'text-wrap',
				'white-space', 'hyphens', 'overflow-wrap', 'word-break', 'vertical-align', 'list-style', 'list-style-type', 'list-style-position', 'quotes', 'content', 'counter-reset', 'counter-increment',
				'overflow', 'overflow-x', 'overflow-y', 'opacity', 'visibility', 'box-sizing', 'cursor', 'pointer-events', 'user-select',
				'transform', 'transform-origin', 'transition', 'transition-property', 'transition-duration', 'transition-timing-function', 'transition-delay',
				'columns', 'column-count', 'column-width', 'column-rule', 'column-rule-color', 'column-rule-style', 'column-rule-width', 'column-span',
				'filter', 'backdrop-filter', 'mix-blend-mode', 'isolation', 'scroll-margin', 'scroll-margin-top', 'appearance', 'resize', 'table-layout', 'border-collapse', 'border-spacing', 'caption-side', 'accent-color', 'caret-color', 'clip-path', 'shape-outside',
			);
			$list = array_flip( $names );
		}
		return $list;
	}

	/** Properties whose value is (or may hold) a colour, where colour names are checked too. */
	private static function colour_properties() {
		return '/^(color|background|background-color|background-image|border|border-(top|right|bottom|left)|border-color|border-(top|right|bottom|left)-color|outline|outline-color|box-shadow|text-shadow|text-decoration|text-decoration-color|column-rule|column-rule-color|accent-color|caret-color)$/';
	}

	/** The CSS colour keywords (the ones that are not transparent, currentcolor or inherit). */
	private static function colour_names() {
		static $names = null;
		if ( null === $names ) {
			$names = array_flip( explode( ' ', 'aliceblue antiquewhite aqua aquamarine azure beige bisque black blanchedalmond blue blueviolet brown burlywood cadetblue chartreuse chocolate coral cornflowerblue cornsilk crimson cyan darkblue darkcyan darkgoldenrod darkgray darkgreen darkgrey darkkhaki darkmagenta darkolivegreen darkorange darkorchid darkred darksalmon darkseagreen darkslateblue darkslategray darkslategrey darkturquoise darkviolet deeppink deepskyblue dimgray dimgrey dodgerblue firebrick floralwhite forestgreen fuchsia gainsboro ghostwhite gold goldenrod gray green greenyellow grey honeydew hotpink indianred indigo ivory khaki lavender lavenderblush lawngreen lemonchiffon lightblue lightcoral lightcyan lightgoldenrodyellow lightgray lightgreen lightgrey lightpink lightsalmon lightseagreen lightskyblue lightslategray lightslategrey lightsteelblue lightyellow lime limegreen linen magenta maroon mediumaquamarine mediumblue mediumorchid mediumpurple mediumseagreen mediumslateblue mediumspringgreen mediumturquoise mediumvioletred midnightblue mintcream mistyrose moccasin navajowhite navy oldlace olive olivedrab orange orangered orchid palegoldenrod palegreen paleturquoise palevioletred papayawhip peachpuff peru pink plum powderblue purple rebeccapurple red rosybrown royalblue saddlebrown salmon sandybrown seagreen seashell sienna silver skyblue slateblue slategray slategrey snow springgreen steelblue tan teal thistle tomato turquoise violet wheat white whitesmoke yellow yellowgreen' ) );
		}
		return $names;
	}

	/** One declaration against the rules. */
	private static function check_declaration( $name, $value, $where ) {
		$errors = array();
		$in     = ' (in "' . $where . '")';
		if ( ! preg_match( '/^[a-z-]+$/', $name ) || ! isset( self::allowed_properties()[ $name ] ) ) {
			$errors[] = 'The property "' . self::shorten( $name ) . '" is not allowed in the site stylesheet' . $in . '.';
			return $errors;
		}
		if ( '' === $value ) {
			$errors[] = 'The property "' . $name . '" has no value' . $in . '.';
			return $errors;
		}
		$lower = strtolower( $value );
		// Colours: only the site's tokens.
		if ( preg_match( '/#[0-9a-f]{3,8}\b/i', $value, $m ) ) {
			$errors[] = 'Typed colours are not allowed (' . $m[0] . ' in ' . $name . $in . '). Use a site colour such as var(--twd-site-color-heading) or var(--twd-site-color-accent).';
		}
		if ( preg_match( '/\b(rgba?|hsla?|hwb|lab|lch|oklab|oklch|color)\s*\(/i', $value, $m ) ) {
			$errors[] = 'Typed colours are not allowed (' . $m[1] . '() in ' . $name . $in . '). Use a site colour such as var(--twd-site-color-heading).';
		}
		if ( preg_match( self::colour_properties(), $name ) && preg_match_all( '/[a-z]+/', $lower, $words ) ) {
			foreach ( $words[0] as $word ) {
				if ( isset( self::colour_names()[ $word ] ) ) {
					$errors[] = 'Typed colours are not allowed ("' . $word . '" in ' . $name . $in . '). Use a site colour such as var(--twd-site-color-heading).';
					break;
				}
			}
		}
		// Fonts: only the site's two fonts.
		if ( 'font-family' === $name && ! preg_match( '/^(var\(--twd-site-(font-heading|font-body|menu-font)\)|inherit)$/', $lower ) ) {
			$errors[] = 'font-family may only be var(--twd-site-font-heading) or var(--twd-site-font-body)' . $in . '.';
		}
		if ( 'font' === $name ) {
			$errors[] = 'The font shorthand is not allowed' . $in . '. Set font-family (a site font), font-size (a token or rem) and font-weight on their own.';
		}
		if ( ( 'font-size' === $name ) && preg_match( '/\d(px|pt)\b/i', $lower ) ) {
			$errors[] = 'Font sizes in px are not allowed' . $in . '. Use a site size such as var(--twd-site-size-body) or var(--twd-site-size-h3), or rem.';
		}
		if ( preg_match( '/\d(vw|vmin|vmax|svw|lvw|dvw)\b/i', $lower ) ) {
			$errors[] = 'Viewport widths (vw) are not allowed' . $in . ': they cause sideways scrolling.';
		}
		if ( 'position' === $name && preg_match( '/\b(fixed|sticky)\b/', $lower ) ) {
			$errors[] = 'position: ' . $lower . ' is not allowed' . $in . ': a custom section may not pin itself to the window.';
		}
		if ( 0 === strpos( $name, 'margin' ) && preg_match( '/(^|\s)-\d/', $lower ) ) {
			$errors[] = 'Negative margins are not allowed' . $in . '.';
		}
		if ( 'display' === $name && false !== strpos( $lower, 'contents' ) ) {
			$errors[] = 'display: contents is not allowed' . $in . '.';
		}
		// Variables: only the site's own tokens, and real ones.
		if ( preg_match_all( '/var\(\s*(--[a-z0-9-]*)/i', $value, $vars ) ) {
			$known = class_exists( 'TWD_SK_Packs' ) ? array_flip( TWD_SK_Packs::token_names() ) : array();
			foreach ( $vars[1] as $var ) {
				if ( 0 !== strpos( $var, '--twd-site-' ) ) {
					$errors[] = 'Only the site\'s own tokens may be used (' . $var . ' in ' . $name . $in . '). They start with --twd-site-.';
				} elseif ( $known && ! isset( $known[ substr( $var, 11 ) ] ) ) {
					// Section 23b: name the nearest real token, so an invented one is caught and fixed in one step.
					$near     = self::nearest_token( substr( $var, 11 ) );
					$errors[] = 'There is no site token called ' . $var . ' (in ' . $name . $in . ').' . ( '' !== $near ? ' Did you mean --twd-site-' . $near . '?' : ' The colours are --twd-site-color-* and the sizes --twd-site-size-*.' );
				}
			}
		}
		if ( preg_match( '/[^\x20-\x7e]/', $value ) && 'content' !== $name && 'quotes' !== $name ) {
			$errors[] = 'The value of ' . $name . ' holds characters that are not plain text' . $in . '.';
		}
		return $errors;
	}

	// -- Building -------------------------------------------------------------------------

	/** Scope every rule under .twd-sk-page and print it compactly. */
	private static function build( $rules ) {
		$out   = '';
		$media = null;
		foreach ( $rules as $rule ) {
			if ( $rule['media'] !== $media ) {
				if ( null !== $media && '' !== $media ) {
					$out .= '}';
				}
				$media = $rule['media'];
				if ( '' !== $media ) {
					$out .= '@media ' . $media . '{';
				}
			}
			$sels = array();
			foreach ( $rule['selectors'] as $sel ) {
				$sels[] = self::SCOPE . ' ' . $sel;
			}
			$decls = array();
			foreach ( $rule['decls'] as $d ) {
				$decls[] = $d[0] . ':' . $d[1];
			}
			$out .= implode( ',', $sels ) . '{' . implode( ';', $decls ) . '}';
		}
		if ( null !== $media && '' !== $media ) {
			$out .= '}';
		}
		return $out;
	}

	/** The source written out tidily, one rule per block, @media blocks gathered. */
	private static function serialise( $rules ) {
		$out   = '';
		$media = null;
		foreach ( $rules as $rule ) {
			if ( $rule['media'] !== $media ) {
				if ( null !== $media && '' !== $media ) {
					$out .= "}\n";
				}
				$media = $rule['media'];
				if ( '' !== $media ) {
					$out .= '@media ' . $media . " {\n";
				}
			}
			$tab   = '' !== $media ? "\t" : '';
			$out  .= $tab . implode( ', ', $rule['selectors'] ) . " {\n";
			foreach ( $rule['decls'] as $d ) {
				$out .= $tab . "\t" . $d[0] . ': ' . $d[1] . ";\n";
			}
			$out .= $tab . "}\n";
		}
		if ( null !== $media && '' !== $media ) {
			$out .= "}\n";
		}
		return $out;
	}

	/** The rules of some source with no checks (for reports). Empty when it cannot be read. */
	private static function rules_of( $source ) {
		$errors = array();
		$rules  = is_string( $source ) && '' !== trim( $source ) ? self::parse( self::strip_comments( str_replace( array( "\r\n", "\r" ), "\n", $source ) ), $errors ) : array();
		return $errors ? array() : $rules;
	}

	/**
	 * Put added rules into the stylesheet: a rule with the same media and selectors replaces the one there,
	 * a new rule goes at the end. Returns the merged source (unchecked; run check() on it).
	 */
	public static function merge( $current, $added ) {
		$errors = array();
		$base   = self::parse( self::strip_comments( str_replace( array( "\r\n", "\r" ), "\n", (string) $current ) ), $errors );
		$errors = array();
		$extra  = self::parse( self::strip_comments( str_replace( array( "\r\n", "\r" ), "\n", (string) $added ) ), $errors );
		if ( $errors ) {
			// Let check() explain the fault on the added text itself.
			return (string) $current . "\n" . (string) $added;
		}
		foreach ( $extra as $rule ) {
			$key   = self::rule_key( $rule );
			$found = false;
			foreach ( $base as $i => $have ) {
				if ( self::rule_key( $have ) === $key ) {
					$base[ $i ] = $rule;
					$found      = true;
					break;
				}
			}
			if ( ! $found ) {
				$base[] = $rule;
			}
		}
		return self::serialise( self::group_media( $base ) );
	}

	private static function rule_key( $rule ) {
		$sels = array_map( 'strtolower', $rule['selectors'] );
		sort( $sels );
		return strtolower( $rule['media'] ) . '|' . implode( ',', $sels );
	}

	/** Plain rules first in their order, then each @media block's rules together, in order of first appearance. */
	private static function group_media( $rules ) {
		$plain  = array();
		$blocks = array();
		foreach ( $rules as $rule ) {
			if ( '' === $rule['media'] ) {
				$plain[] = $rule;
			} else {
				$blocks[ $rule['media'] ][] = $rule;
			}
		}
		$out = $plain;
		foreach ( $blocks as $list ) {
			foreach ( $list as $rule ) {
				$out[] = $rule;
			}
		}
		return $out;
	}

	/** Rules none of the given pages uses (a rule is used when any of its c- classes is on any page). */
	public static function unused_rules( $pages_html, $source = null ) {
		$source = null === $source ? self::current()['source'] : $source;
		$used   = array();
		foreach ( (array) $pages_html as $html ) {
			if ( preg_match_all( '/class="([^"]*)"/', (string) $html, $m ) ) {
				foreach ( $m[1] as $list ) {
					foreach ( preg_split( '/\s+/', trim( $list ) ) as $class ) {
						$used[ $class ] = true;
					}
				}
			}
		}
		$out = array();
		foreach ( self::rules_of( $source ) as $rule ) {
			$classes = array();
			foreach ( $rule['selectors'] as $sel ) {
				if ( preg_match_all( '/\.(c-[a-z0-9_-]+)/', $sel, $m ) ) {
					foreach ( $m[1] as $c ) {
						$classes[ $c ] = true;
					}
				}
			}
			$hit = false;
			foreach ( array_keys( $classes ) as $c ) {
				if ( isset( $used[ $c ] ) ) {
					$hit = true;
					break;
				}
			}
			if ( ! $hit ) {
				$out[] = ( '' !== $rule['media'] ? '@media ' . $rule['media'] . ' ' : '' ) . implode( ', ', $rule['selectors'] );
			}
		}
		return $out;
	}

	/** The current HTML of every kit page, for unused_rules(). */
	public static function pages_html() {
		$out = array();
		if ( ! class_exists( 'TWD_SK_Launch' ) ) {
			return $out;
		}
		foreach ( TWD_SK_Launch::kit_pages() as $id ) {
			$out[ (int) $id ] = TWD_SK_Store::get_current( (int) $id );
		}
		return $out;
	}

	// -- Writing --------------------------------------------------------------------------

	/**
	 * Check and save a whole stylesheet as a new version. Returns { v, unchanged, warnings, bytes } or a WP_Error
	 * whose data lists the errors. Nothing is saved when a check fails.
	 */
	public static function save( $source, $note = '', $page_id = 0 ) {
		$check = self::check( $source );
		if ( ! $check['ok'] ) {
			return new WP_Error( 'twd_sk_css_invalid', 'The stylesheet was not applied: ' . implode( ' ', array_slice( $check['errors'], 0, 3 ) ), array( 'errors' => $check['errors'] ) );
		}
		$cur = self::current();
		if ( $cur['source'] === $check['source'] ) {
			return array( 'v' => (int) $cur['v'], 'unchanged' => true, 'warnings' => $check['warnings'], 'bytes' => $check['bytes'] );
		}
		return self::commit( $check, $note, $page_id );
	}

	private static function commit( $check, $note, $page_id ) {
		$cur  = self::current();
		$v    = (int) $cur['v'] + 1;
		$list = get_option( self::OPTION_VERSIONS, array() );
		$list = is_array( $list ) ? $list : array();
		// Version numbers never go backwards, even if the newest versions were dropped at the cap.
		foreach ( $list as $e ) {
			if ( is_array( $e ) && isset( $e['v'] ) && (int) $e['v'] >= $v ) {
				$v = (int) $e['v'] + 1;
			}
		}
		$user  = function_exists( 'get_current_user_id' ) ? (int) get_current_user_id() : 0;
		$at    = gmdate( 'Y-m-d H:i:s' );
		$note  = TWD_SK_Sanitizer::strip_dashes( substr( trim( (string) $note ), 0, 200 ) );
		$entry = array( 'v' => $v, 'source' => $check['source'], 'note' => $note, 'user' => $user, 'at' => $at, 'page' => (int) $page_id );
		array_unshift( $list, $entry );
		$list = array_slice( $list, 0, self::KEEP );
		update_option( self::OPTION_VERSIONS, $list, false );
		update_option( self::OPTION, array(
			'v'       => $v,
			'source'  => $check['source'],
			'built'   => $check['built'],
			'classes' => $check['classes'],
			'note'    => $note,
			'user'    => $user,
			'at'      => $at,
		), false );
		return array( 'v' => $v, 'unchanged' => false, 'warnings' => $check['warnings'], 'bytes' => $check['bytes'] );
	}

	/** Bring back a version's source as a new version (0 is the empty stylesheet). */
	public static function revert_to( $v, $note = '', $page_id = 0 ) {
		$source = self::version_source( (int) $v );
		if ( null === $source ) {
			return new WP_Error( 'twd_sk_version_not_found', 'Stylesheet version ' . (int) $v . ' is not kept any more.' );
		}
		return self::save( $source, '' !== $note ? $note : 'back to stylesheet version ' . (int) $v, $page_id );
	}

	/**
	 * A page went back to a version saved with stylesheet version $target_v. Follow it when every stylesheet
	 * change since came from this same page; otherwise leave the stylesheet and say why. Returns a plain note or ''.
	 */
	public static function follow_page( $post_id, $target_v ) {
		$target_v = (int) $target_v;
		$now      = self::version();
		if ( $target_v < 0 || $target_v === $now ) {
			return '';
		}
		$later = array();
		foreach ( self::versions() as $e ) {
			if ( $e['v'] > $target_v ) {
				$later[] = $e;
			}
		}
		if ( $target_v > 0 && null === self::version_source( $target_v ) ) {
			return 'The site stylesheet was left as it is: version ' . $target_v . ' is not kept any more.';
		}
		if ( count( $later ) < $now - $target_v ) {
			// A version in between is gone from the list: play safe.
			return 'The site stylesheet was left as it is: part of its history is no longer kept.';
		}
		foreach ( $later as $e ) {
			if ( (int) $e['page'] !== (int) $post_id ) {
				return 'The site stylesheet was left as it is, because another page changed it since (version ' . $e['v'] . ').';
			}
		}
		$done = self::revert_to( $target_v, 'with page ' . (int) $post_id . ' going back to stylesheet version ' . $target_v, (int) $post_id );
		if ( is_wp_error( $done ) ) {
			return 'The site stylesheet could not follow: ' . $done->get_error_message();
		}
		return $done['unchanged'] ? '' : 'The site stylesheet went back with it (now version ' . $done['v'] . ', the rules of version ' . $target_v . ').';
	}

	// -- Printing -------------------------------------------------------------------------

	/** What visitors get: the built stylesheet, or a preview's candidate through the filter. */
	public static function built_for_output() {
		$built = self::current()['built'];
		return (string) ( function_exists( 'apply_filters' ) ? apply_filters( self::FILTER_BUILT, $built ) : $built );
	}

	public static function print_styles() {
		$css = self::built_for_output();
		if ( '' !== $css && class_exists( 'TWD_SK_Assets' ) ) {
			wp_add_inline_style( TWD_SK_Assets::HANDLE, $css );
		}
	}
}
