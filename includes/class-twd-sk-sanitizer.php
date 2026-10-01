<?php
/**
 * TWD_SK_Sanitizer: the only path page HTML takes before it is stored.
 *
 * Fully independent of the articles plugin: its own dash stripping and no calls
 * into that plugin's code. No WordPress functions are used either, so it runs
 * in the plain-PHP test suite.
 *
 * Method: parse with DOMDocument, walk every node, rebuild the output.
 *  - scripts, styles, iframes, forms and similar are removed with their contents
 *  - unknown tags are unwrapped (children kept). A page may have one h1: the first
 *    hero title (class twd-sk-hero__title inside .twd-sk-hero). Any other h1 becomes h2
 *  - only a short list of attributes survives, per tag
 *  - classes are checked against the registry allowlist
 *  - links and images: http, https, mailto, tel, relative paths and #anchors only
 *    (protocol-relative URLs are refused)
 *  - shortcodes: everything removed except those the registry allows, rebuilt
 *    with validated attributes only
 *  - em and en dashes removed from text
 *
 * clean() returns the HTML. clean_with_report() also says what was removed.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class TWD_SK_Sanitizer {

	const URL_REPORT_LENGTH = 80;

	/** Removed together with everything inside them. */
	private static function remove_with_contents() {
		return array_flip( array(
			'script', 'style', 'iframe', 'frame', 'frameset', 'object', 'embed', 'applet',
			'form', 'input', 'button', 'textarea', 'select', 'option', 'optgroup',
			'svg', 'math', 'noscript', 'template', 'link', 'meta', 'base', 'title',
			'head', 'audio', 'video', 'source', 'track', 'canvas', 'dialog',
		) );
	}

	/** Kept (after attribute cleaning). Anything else is unwrapped. */
	private static function allowed_tags() {
		return array_flip( array(
			'div', 'section', 'article', 'aside', 'header', 'footer',
			'p', 'span', 'h2', 'h3', 'h4', 'h5', 'h6',
			'ul', 'ol', 'li', 'dl', 'dt', 'dd',
			'blockquote', 'figure', 'figcaption', 'cite', 'q',
			'a', 'img', 'strong', 'em', 'b', 'i', 'u', 'small', 'sub', 'sup', 'mark',
			'br', 'hr', 'details', 'summary',
		) );
	}

	/** Attributes kept, per tag. '*' applies to every tag. */
	private static function allowed_attributes( $tag ) {
		$map = array(
			'*'       => array( 'class', 'id' ),
			'a'       => array( 'href', 'target', 'rel', 'title' ),
			'img'     => array( 'src', 'alt', 'width', 'height', 'title' ),
			'details' => array( 'open' ),
		);
		$out = $map['*'];
		if ( isset( $map[ $tag ] ) ) {
			$out = array_merge( $out, $map[ $tag ] );
		}
		return $out;
	}

	private static function allowed_rel_tokens() {
		return array( 'nofollow', 'noopener', 'noreferrer', 'sponsor', 'ugc' );
	}

	// -- Public API -------------------------------------------------------

	public static function clean( $html ) {
		$result = self::clean_with_report( $html );
		return $result['html'];
	}

	/**
	 * @return array {
	 *     html:   the cleaned HTML
	 *     report: { total, counts: {classes,tags,attributes,urls,shortcodes,dashes}, removed: {same keys => list of strings} }
	 * }
	 */
	public static function clean_with_report( $html ) {
		$ctx = array(
			'removed' => array(
				'classes'    => array(),
				'tags'       => array(),
				'attributes' => array(),
				'urls'       => array(),
				'shortcodes' => array(),
				'dashes'     => array(),
			),
			// The one h1 a page may have is used up by the first accepted hero title.
			'h1_used' => false,
		);

		if ( ! is_string( $html ) ) {
			return self::result( '', $ctx['removed'] );
		}
		$html = self::ensure_utf8( $html );
		if ( '' === trim( $html ) ) {
			return self::result( '', $ctx['removed'] );
		}

		$doc = self::load( $html );
		if ( null === $doc ) {
			return self::result( '', $ctx['removed'] );
		}
		$body = $doc->getElementsByTagName( 'body' )->item( 0 );
		if ( ! $body ) {
			return self::result( '', $ctx['removed'] );
		}

		$root = null;
		foreach ( $body->childNodes as $child ) {
			if ( XML_ELEMENT_NODE === $child->nodeType && $child->hasAttribute( 'data-twd-sk-root' ) ) {
				$root = $child;
				break;
			}
		}
		if ( null === $root ) {
			return self::result( '', $ctx['removed'] );
		}

		self::walk_children( $doc, $body, $root, $ctx );
		self::wrap_loose_text( $doc, $body );

		$out = '';
		foreach ( $body->childNodes as $child ) {
			$out .= $doc->saveHTML( $child );
		}

		return self::result( trim( $out ), $ctx['removed'] );
	}

	/**
	 * Example text from the registry skeletons that should not go live. A leftover is a
	 * warning only: the HTML is never changed because of it.
	 */
	public static function leftover_markers() {
		return array_keys( self::leftover_patterns() );
	}

	/**
	 * marker => regex, or null for plain text matched without regard to case. The
	 * regex form is for markers that must not catch real addresses: only the sample
	 * "/service-1" style links count, not a real page called /service-anxiety.
	 */
	private static function leftover_patterns() {
		return array(
			'example.com'                       => null,
			'example.org'                       => null,
			'example.net'                       => null,
			'PHONE_NUMBER'                      => null,
			'Short label here'                  => null,
			'Heading here'                      => null,
			'Paragraph text here'               => null,
			'Describe the image'                => null,
			'Quote text here'                   => null,
			'Another quote here'                => null,
			'Name and context'                  => null,
			'Question here'                     => null,
			'Answer here'                       => null,
			'Topic one'                         => null,
			'Short description here'            => null,
			'Short introduction here'           => null,
			'Service name here'                 => null,
			'A short statement in your own words' => null,
			'your-image'                        => null,
			'your-badge'                        => null,
			'/service-N'                        => '#href\s*=\s*["\']/service-\d+["\'/?\#]#i',
			'[PLACEHOLDER'                      => null,
			// The example link texts in the style guide. Real links need the therapist's own words.
			'Contact me about a first session'  => null,
			'Find out about my services'        => null,
			'Read more about this approach'     => null,
			'Call me to arrange a first session' => null,
		);
	}

	/**
	 * The "check" level: wording that is an example but may be right for a real page
	 * (the example button and link texts). It warns and never blocks publishing. Every
	 * other marker is "must fix".
	 */
	private static function check_level_markers() {
		return array(
			'Contact me about a first session',
			'Find out about my services',
			'Read more about this approach',
			'Call me to arrange a first session',
		);
	}

	/** 'must' or 'check' for a marker. */
	public static function leftover_level( $marker ) {
		return in_array( $marker, self::check_level_markers(), true ) ? 'check' : 'must';
	}

	/**
	 * Leftovers split by level.
	 *
	 * @return array { must: marker => count, check: marker => count }
	 */
	public static function find_leftovers_by_level( $html ) {
		$out = array( 'must' => array(), 'check' => array() );
		foreach ( self::find_leftovers( $html ) as $marker => $count ) {
			$out[ self::leftover_level( $marker ) ][ $marker ] = $count;
		}
		return $out;
	}

	/**
	 * Find leftover example text in some HTML.
	 *
	 * @return array marker => number of times it appears. Empty when there are none.
	 */
	public static function find_leftovers( $html ) {
		$found = array();
		if ( ! is_string( $html ) || '' === $html ) {
			return $found;
		}
		$lower = strtolower( $html );
		foreach ( self::leftover_patterns() as $marker => $regex ) {
			$n = null === $regex ? substr_count( $lower, strtolower( $marker ) ) : (int) preg_match_all( $regex, $html );
			if ( $n > 0 ) {
				$found[ $marker ] = $n;
			}
		}
		return $found;
	}

	/**
	 * Dash stripping. This is this plugin's own copy of the behaviour, on
	 * purpose: no dependency on the articles plugin.
	 *
	 * An em or en dash (or its entity form) right before closing punctuation is
	 * dropped. Anywhere else it becomes a comma and a single space.
	 */
	public static function strip_dashes( $text ) {
		if ( ! is_string( $text ) || '' === $text ) {
			return $text;
		}
		$dash = self::dash_pattern();

		$text = preg_replace( '/\s*' . $dash . '\s*(?=[.,;:!?])/u', '', $text );
		$text = preg_replace( '/\s*' . $dash . '\s*/u', ', ', $text );
		$text = preg_replace( '/,\s*,/u', ',', $text );

		return $text;
	}

	/**
	 * URL check. Returns the URL if it is acceptable, or false.
	 *
	 * @param string $url     Attribute value (already entity-decoded by the parser).
	 * @param array  $schemes Lowercase schemes allowed, for example array( 'http', 'https' ).
	 */
	public static function safe_url( $url, $schemes ) {
		if ( ! is_string( $url ) ) {
			return false;
		}
		$url = trim( $url );

		// Browsers ignore tabs, newlines and other control characters anywhere
		// in a URL, so check the URL with all of them removed.
		$probe = preg_replace( '/[\x00-\x20\x7f]+/', '', $url );
		if ( '' === $probe ) {
			return false;
		}
		// Square brackets can only be shortcodes or IPv6 hosts here. Refuse both.
		if ( false !== strpos( $probe, '[' ) || false !== strpos( $probe, ']' ) ) {
			return false;
		}

		$first = $probe[0];

		// Backslash start: browsers treat it like a slash, so it can mean //host.
		if ( '\\' === $first ) {
			return false;
		}
		// Absolute path. Refuse protocol-relative (//host, /\host).
		if ( '/' === $first ) {
			$second = isset( $probe[1] ) ? $probe[1] : '';
			if ( '/' === $second || '\\' === $second ) {
				return false;
			}
			return $url;
		}
		// Fragment or query only.
		if ( '#' === $first || '?' === $first ) {
			return $url;
		}

		if ( preg_match( '/^([A-Za-z][A-Za-z0-9+.\-]*):/', $probe, $m ) ) {
			$scheme = strtolower( $m[1] );
			if ( ! in_array( $scheme, $schemes, true ) ) {
				return false;
			}
			$rest = substr( $probe, strlen( $m[0] ) );
			if ( 'http' === $scheme || 'https' === $scheme ) {
				if ( ! preg_match( '#^//([^/?\#]+)#', $rest, $h ) ) {
					return false;
				}
				$authority = $h[1];
				if ( false !== strpos( $authority, '@' ) || false !== strpos( $authority, '\\' ) || ':' === $authority[0] ) {
					return false;
				}
				return $url;
			}
			// mailto and tel need something after the colon.
			return '' === $rest ? false : $url;
		}

		// No scheme. A colon before the first / ? or # means a malformed or
		// disguised scheme, so refuse it. Otherwise it is a relative path.
		$cut = strcspn( $probe, '/?#' );
		if ( false !== strpos( substr( $probe, 0, $cut ), ':' ) ) {
			return false;
		}
		return $url;
	}

	// -- Internals --------------------------------------------------------

	private static function result( $html, $removed ) {
		$warnings = self::find_leftovers( $html );
		$counts = array();
		$total  = 0;
		foreach ( $removed as $key => $list ) {
			$counts[ $key ] = count( $list );
			$total         += $counts[ $key ];
		}
		return array(
			'html'   => $html,
			'report' => array(
				'total'   => $total,
				'counts'  => $counts,
				'removed' => $removed,
				// Leftover example text, marker => count. Warnings only; nothing is removed.
				'leftovers' => $warnings,
			),
		);
	}

	private static function dash_pattern() {
		$em = '(?:\x{2014}|&mdash;|&#0*8212;|&#[xX]0*2014;)';
		$en = '(?:\x{2013}|&ndash;|&#0*8211;|&#[xX]0*2013;)';
		return '(?:' . $em . '|' . $en . ')';
	}

	private static function ensure_utf8( $s ) {
		if ( ! preg_match( '//u', $s ) ) {
			if ( function_exists( 'mb_convert_encoding' ) ) {
				$s = mb_convert_encoding( $s, 'UTF-8', 'UTF-8' );
			} elseif ( function_exists( 'iconv' ) ) {
				$s = (string) iconv( 'UTF-8', 'UTF-8//IGNORE', $s );
			} else {
				return '';
			}
		}
		if ( 0 === strpos( $s, "\xEF\xBB\xBF" ) ) {
			$s = substr( $s, 3 );
		}
		return preg_replace( '/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F]/', '', $s );
	}

	private static function load( $html ) {
		$previous = libxml_use_internal_errors( true );
		$doc      = new DOMDocument();
		// The meta tag is what makes libxml read the input as UTF-8. Without it
		// accented letters and emoji come out mangled.
		$wrapped = '<!DOCTYPE html><html><head><meta http-equiv="Content-Type" content="text/html; charset=utf-8"></head><body><div data-twd-sk-root="1">'
			. $html
			. '</div></body></html>';
		$ok      = $doc->loadHTML( $wrapped, LIBXML_NONET | LIBXML_NOERROR | LIBXML_NOWARNING );
		libxml_clear_errors();
		libxml_use_internal_errors( $previous );
		return $ok ? $doc : null;
	}

	/**
	 * Loose text and inline markup sitting directly in the page (not inside any
	 * element) cannot be styled by CSS, so each run of it is wrapped in a paragraph.
	 * A run with no loose text of its own (a lone link, whitespace, a line break) is left alone.
	 */
	private static function wrap_loose_text( DOMDocument $doc, DOMNode $body ) {
		$inline = array_flip( array( 'a', 'span', 'strong', 'em', 'b', 'i', 'u', 'small', 'sub', 'sup', 'mark', 'br', 'q', 'cite' ) );
		$runs   = array();
		$run    = array();
		foreach ( iterator_to_array( $body->childNodes ) as $child ) {
			$is_text   = XML_TEXT_NODE === $child->nodeType;
			$is_inline = XML_ELEMENT_NODE === $child->nodeType && isset( $inline[ strtolower( $child->nodeName ) ] );
			if ( $is_text || $is_inline ) {
				$run[] = $child;
				continue;
			}
			if ( $run ) {
				$runs[] = $run;
				$run    = array();
			}
		}
		if ( $run ) {
			$runs[] = $run;
		}
		foreach ( $runs as $nodes ) {
			$loose = '';
			foreach ( $nodes as $n ) {
				if ( XML_TEXT_NODE === $n->nodeType ) {
					$loose .= $n->textContent;
				}
			}
			if ( '' === trim( str_replace( "\xC2\xA0", ' ', $loose ) ) ) {
				continue;
			}
			$p = $doc->createElement( 'p' );
			$nodes[0]->parentNode->insertBefore( $p, $nodes[0] );
			foreach ( $nodes as $n ) {
				$p->appendChild( $n );
			}
		}
	}

	private static function walk_children( DOMDocument $doc, DOMNode $parent, DOMNode $root, array &$ctx ) {
		$children = array();
		foreach ( $parent->childNodes as $child ) {
			$children[] = $child;
		}
		foreach ( $children as $child ) {
			self::process_node( $doc, $child, $root, $ctx );
		}
	}

	private static function process_node( DOMDocument $doc, DOMNode $node, DOMNode $root, array &$ctx ) {
		$type = $node->nodeType;

		if ( XML_TEXT_NODE === $type ) {
			self::clean_text_node( $node, $ctx );
			return;
		}
		if ( XML_ELEMENT_NODE !== $type ) {
			// Comments, CDATA, processing instructions.
			$ctx['removed']['tags'][] = ( XML_COMMENT_NODE === $type ) ? 'comment' : 'non-element node';
			$node->parentNode->removeChild( $node );
			return;
		}

		if ( $node->isSameNode( $root ) ) {
			self::walk_children( $doc, $node, $root, $ctx );
			self::unwrap( $node );
			return;
		}

		$tag = strtolower( $node->nodeName );

		if ( isset( self::remove_with_contents()[ $tag ] ) ) {
			$ctx['removed']['tags'][] = $tag;
			$node->parentNode->removeChild( $node );
			return;
		}

		if ( 'h1' === $tag ) {
			// A page may have exactly one h1: the first hero title. Any other h1
			// becomes an h2. Attributes are cleaned as for an h2 (class and id).
			$keep = self::clean_attributes( $node, 'h2', $ctx );
			if ( ! $ctx['h1_used'] && self::is_hero_title( $node, $keep ) ) {
				$ctx['h1_used'] = true;
				$final          = 'h1';
			} else {
				$ctx['removed']['tags'][] = 'h1 (demoted to h2)';
				$final                    = 'h2';
			}
			$node = self::rebuild( $doc, $node, $final, $keep );
			self::walk_children( $doc, $node, $root, $ctx );
			return;
		}

		if ( ! isset( self::allowed_tags()[ $tag ] ) ) {
			$ctx['removed']['tags'][] = $tag;
			self::walk_children( $doc, $node, $root, $ctx );
			self::unwrap( $node );
			return;
		}

		$keep = self::clean_attributes( $node, $tag, $ctx );
		if ( false === $keep ) {
			$node->parentNode->removeChild( $node );
			return;
		}

		$node = self::rebuild( $doc, $node, $tag, $keep );

		self::walk_children( $doc, $node, $root, $ctx );
	}

	/**
	 * An h1 is acceptable only when it carries the hero title class (after class
	 * filtering) and sits inside an element carrying the hero class. Ancestors
	 * have already been rebuilt from their cleaned attributes by the time their
	 * children are processed, so their classes can be trusted here.
	 */
	private static function is_hero_title( DOMElement $node, array $keep ) {
		if ( ! isset( $keep['class'] ) || ! in_array( 'twd-sk-hero__title', explode( ' ', $keep['class'] ), true ) ) {
			return false;
		}
		for ( $parent = $node->parentNode; $parent instanceof DOMElement; $parent = $parent->parentNode ) {
			if ( in_array( 'twd-sk-hero', explode( ' ', $parent->getAttribute( 'class' ) ), true ) ) {
				return true;
			}
		}
		return false;
	}

	private static function unwrap( DOMNode $node ) {
		$parent = $node->parentNode;
		while ( $node->firstChild ) {
			$parent->insertBefore( $node->firstChild, $node );
		}
		$parent->removeChild( $node );
	}

	/**
	 * Decide which attributes survive. Returns the survivors as name => value,
	 * or false if the element should be dropped entirely (an image left with no
	 * usable src). The element itself is not touched: the caller builds a fresh
	 * one from the survivors, because removeAttribute() cannot remove attributes
	 * with a colon in the name (xmlns:xlink, xlink:href).
	 */
	private static function clean_attributes( DOMElement $el, $tag, array &$ctx ) {
		$allowed = self::allowed_attributes( $tag );
		$keep    = array();

		foreach ( $el->attributes as $attr ) {
			$lname = strtolower( $attr->nodeName );
			$value = $attr->value;

			if ( ! in_array( $lname, $allowed, true ) ) {
				$ctx['removed']['attributes'][] = $lname . ' on <' . $tag . '>';
				continue;
			}

			switch ( $lname ) {
				case 'class':
					$classes = array();
					$list    = self::class_allowlist();
					foreach ( preg_split( '/\s+/', trim( $value ), -1, PREG_SPLIT_NO_EMPTY ) as $class ) {
						if ( isset( $list[ $class ] ) ) {
							$classes[ $class ] = $class;
						} else {
							$ctx['removed']['classes'][] = $class;
						}
					}
					if ( $classes ) {
						$keep['class'] = implode( ' ', $classes );
					}
					break;

				case 'id':
					if ( preg_match( '/^twd-sk-[a-z0-9-]{1,48}$/', $value ) ) {
						$keep['id'] = $value;
					} else {
						$ctx['removed']['attributes'][] = 'id on <' . $tag . '>';
					}
					break;

				case 'href':
					$safe = self::safe_url( $value, array( 'http', 'https', 'mailto', 'tel' ) );
					if ( false === $safe ) {
						$ctx['removed']['urls'][] = 'href: ' . self::shorten( $value );
					} else {
						$keep['href'] = $safe;
					}
					break;

				case 'src':
					$safe = self::safe_url( $value, array( 'http', 'https' ) );
					if ( false === $safe ) {
						$ctx['removed']['urls'][] = 'src: ' . self::shorten( $value );
					} else {
						$keep['src'] = $safe;
					}
					break;

				case 'target':
					if ( '_blank' === $value ) {
						$keep['target'] = '_blank';
					} else {
						$ctx['removed']['attributes'][] = 'target on <' . $tag . '>';
					}
					break;

				case 'rel':
					$tokens = array();
					foreach ( preg_split( '/\s+/', strtolower( trim( $value ) ), -1, PREG_SPLIT_NO_EMPTY ) as $token ) {
						if ( in_array( $token, self::allowed_rel_tokens(), true ) ) {
							$tokens[ $token ] = $token;
						} else {
							$ctx['removed']['attributes'][] = 'rel token ' . $token;
						}
					}
					if ( $tokens ) {
						$keep['rel'] = implode( ' ', $tokens );
					}
					break;

				case 'width':
				case 'height':
					if ( preg_match( '/^\d{1,4}$/', $value ) ) {
						$keep[ $lname ] = $value;
					} else {
						$ctx['removed']['attributes'][] = $lname . ' on <' . $tag . '>';
					}
					break;

				case 'alt':
				case 'title':
					$keep[ $lname ] = self::clean_text( $value, $ctx );
					break;

				case 'open':
					$keep['open'] = '';
					break;
			}
		}

		if ( 'a' === $tag && isset( $keep['target'] ) ) {
			$tokens = array( 'noopener', 'noreferrer' );
			if ( isset( $keep['rel'] ) ) {
				foreach ( explode( ' ', $keep['rel'] ) as $token ) {
					$tokens[] = $token;
				}
			}
			$keep['rel'] = implode( ' ', array_unique( $tokens ) );
		}

		if ( 'img' === $tag && ! isset( $keep['src'] ) ) {
			$ctx['removed']['tags'][] = 'img (no valid src)';
			return false;
		}

		return $keep;
	}

	/** A fresh element with exactly the given attributes, taking over the old one's children. */
	private static function rebuild( DOMDocument $doc, DOMElement $old, $tag, array $attributes ) {
		$new = $doc->createElement( $tag );
		foreach ( $attributes as $name => $value ) {
			$new->setAttribute( $name, $value );
		}
		while ( $old->firstChild ) {
			$new->appendChild( $old->firstChild );
		}
		$old->parentNode->replaceChild( $new, $old );
		return $new;
	}

	private static function class_allowlist() {
		return TWD_SK_Registry::allowed_classes();
	}

	private static function shorten( $value ) {
		$value = preg_replace( '/\s+/', ' ', trim( (string) $value ) );
		if ( strlen( $value ) > self::URL_REPORT_LENGTH ) {
			$value = substr( $value, 0, self::URL_REPORT_LENGTH ) . '...';
		}
		return $value;
	}

	private static function clean_text_node( DOMNode $node, array &$ctx ) {
		$old = $node->data;
		$new = self::clean_text( $old, $ctx );
		if ( $new !== $old ) {
			$node->data = $new;
		}
	}

	/** Shortcode removal, then dash stripping. Used for text nodes and alt/title. */
	private static function clean_text( $text, array &$ctx ) {
		if ( '' === $text ) {
			return $text;
		}
		$text = self::clean_shortcodes( $text, $ctx );

		$dash = self::dash_pattern();
		if ( preg_match_all( '/.{0,12}' . $dash . '.{0,12}/us', $text, $found ) ) {
			foreach ( $found[0] as $snippet ) {
				$ctx['removed']['dashes'][] = trim( preg_replace( '/\s+/', ' ', $snippet ) );
			}
		}
		return self::strip_dashes( $text );
	}

	/**
	 * Remove every shortcode except those the registry allows. An allowed
	 * shortcode is rebuilt from scratch with only its validated attributes.
	 */
	private static function clean_shortcodes( $text, array &$ctx ) {
		if ( false === strpos( $text, '[' ) ) {
			return $text;
		}
		$allowed = TWD_SK_Registry::allowed_shortcodes();

		// [[escaped]] form first, then opening, self-closing and closing tags.
		$pattern = '/\[\[[A-Za-z_][\w-]*[^\[\]]*\]\]|\[\/?[A-Za-z_][\w-]*(?:[\s=\/][^\[\]]*)?\]/';

		return preg_replace_callback(
			$pattern,
			function ( $m ) use ( $allowed, &$ctx ) {
				$token = $m[0];

				// A visible stand-in for a detail the client has not supplied, for example
				// [PLACEHOLDER] or [PLACEHOLDER: registration number]. It is plain text, never a shortcode.
				if ( preg_match( '/^\[PLACEHOLDER(?::[ \t][^\[\]]{1,60})?\]$/', $token ) ) {
					return $token;
				}

				if ( 0 === strpos( $token, '[[' ) || 0 === strpos( $token, '[/' ) ) {
					$ctx['removed']['shortcodes'][] = self::shorten( $token );
					return '';
				}
				if ( ! preg_match( '/^\[([A-Za-z_][\w-]*)(.*)\]$/s', $token, $parts ) ) {
					$ctx['removed']['shortcodes'][] = self::shorten( $token );
					return '';
				}
				$tag = $parts[1];
				if ( ! isset( $allowed[ $tag ] ) ) {
					$ctx['removed']['shortcodes'][] = self::shorten( $token );
					return '';
				}

				$spec  = $allowed[ $tag ];
				$found = array();
				preg_match_all(
					'/([A-Za-z_][\w-]*)\s*=\s*(?:"([^"]*)"|\'([^\']*)\'|([^\s"\'\]\/]+))/',
					$parts[2],
					$pairs,
					PREG_SET_ORDER
				);
				foreach ( $pairs as $pair ) {
					$name  = $pair[1];
					$value = '';
					if ( isset( $pair[4] ) && '' !== $pair[4] ) {
						$value = $pair[4];
					} elseif ( isset( $pair[3] ) && '' !== $pair[3] ) {
						$value = $pair[3];
					} elseif ( isset( $pair[2] ) ) {
						$value = $pair[2];
					}
					if ( isset( $spec[ $name ] ) && preg_match( $spec[ $name ], $value ) ) {
						$found[ $name ] = $value;
					} else {
						$ctx['removed']['shortcodes'][] = $tag . ' attribute ' . $name;
					}
				}

				$out = '[' . $tag;
				foreach ( array_keys( $spec ) as $name ) {
					if ( isset( $found[ $name ] ) ) {
						$out .= ' ' . $name . '="' . $found[ $name ] . '"';
					}
				}
				return $out . ']';
			},
			$text
		);
	}
}
