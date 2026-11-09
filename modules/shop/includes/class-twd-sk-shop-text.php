<?php
/**
 * TWD_SK_Shop_Text: the words of a product. A product's text has no classes, no pictures and no
 * headings inside a section, so it needs none of the kit's allowlist. Every piece runs through the
 * kit's own sanitiser first (unchanged), then is narrowed here to a short list of tags.
 *
 * The description holds the sections: text before the first heading is "About this piece", and
 * each h2 or h3 starts a section (saved back as h3, which sits under WooCommerce's own Description
 * heading when the module is off).
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class TWD_SK_Shop_Text {

	const MAX_SECTION  = 6000;
	const MAX_SUMMARY  = 600;
	const MAX_SECTIONS = 8;
	const MAX_HEADING  = 80;
	const FIRST        = 'About this piece';

	public static function section_tags() {
		return array( 'p', 'br', 'strong', 'em', 'ul', 'ol', 'li', 'a' );
	}

	public static function summary_tags() {
		return array( 'p', 'br', 'strong', 'em' );
	}

	public static function clean_section( $html ) {
		return self::narrow( TWD_SK_Sanitizer::clean( self::paragraphs( $html ) ), self::section_tags() );
	}

	public static function clean_summary( $html ) {
		return self::narrow( TWD_SK_Sanitizer::clean( self::paragraphs( $html ) ), self::summary_tags() );
	}

	/** Plain words, one line, no tags, no long dashes, cut to a length. */
	public static function plain( $value, $max ) {
		return TWD_SK_Profile::text( is_string( $value ) ? html_entity_decode( $value, ENT_QUOTES, 'UTF-8' ) : '', $max );
	}

	/** How many letters a piece of text has once the tags are gone. */
	public static function length( $html ) {
		$text = html_entity_decode( wp_strip_all_tags( (string) $html ), ENT_QUOTES, 'UTF-8' );
		return function_exists( 'mb_strlen' ) ? mb_strlen( $text ) : strlen( $text );
	}

	/** Text typed in the WooCommerce screen keeps its blank lines as paragraphs. */
	private static function paragraphs( $html ) {
		$html = is_string( $html ) ? str_replace( array( "\r\n", "\r" ), "\n", $html ) : '';
		if ( '' === trim( $html ) ) {
			return '';
		}
		if ( function_exists( 'wpautop' ) ) {
			return wpautop( $html );
		}
		if ( ! preg_match( '#<(p|ul|ol|div|h[1-6])\b#i', $html ) ) {
			$parts = preg_split( "/\n\s*\n/", trim( $html ) );
			return '<p>' . implode( "</p>\n<p>", array_map( 'trim', $parts ) ) . '</p>';
		}
		return $html;
	}

	// -- Narrowing ---------------------------------------------------------------

	private static function narrow( $html, $allowed ) {
		if ( ! is_string( $html ) || '' === trim( $html ) ) {
			return '';
		}
		$previous = libxml_use_internal_errors( true );
		$doc      = new DOMDocument();
		$ok       = $doc->loadHTML( '<!DOCTYPE html><html><head><meta http-equiv="Content-Type" content="text/html; charset=utf-8"></head><body><div data-twd-sk-shop="1">' . $html . '</div></body></html>', LIBXML_NONET | LIBXML_NOERROR | LIBXML_NOWARNING );
		libxml_clear_errors();
		libxml_use_internal_errors( $previous );
		if ( ! $ok ) {
			return '';
		}
		$root = null;
		foreach ( $doc->getElementsByTagName( 'div' ) as $div ) {
			if ( $div->hasAttribute( 'data-twd-sk-shop' ) ) {
				$root = $div;
				break;
			}
		}
		if ( null === $root ) {
			return '';
		}
		$allow = array_flip( $allowed );
		$out   = array();
		$run   = '';
		self::top( $root, $allow, $out, $run );
		self::flush( $out, $run );
		return implode( "\n", $out );
	}

	/** Words between tags: only the characters that would start or end markup are encoded, so an apostrophe stays one. */
	private static function esc( $text ) {
		return htmlspecialchars( $text, ENT_NOQUOTES, 'UTF-8' );
	}

	private static function esc_attr( $text ) {
		return htmlspecialchars( $text, ENT_QUOTES, 'UTF-8' );
	}

	private static function flush( &$out, &$run ) {
		$check = trim( preg_replace( '#<br>#', '', $run ) );
		if ( '' !== $check ) {
			$out[] = '<p>' . trim( $run ) . '</p>';
		}
		$run = '';
	}

	private static function is_block( $name ) {
		return in_array( $name, array( 'p', 'div', 'section', 'article', 'aside', 'header', 'footer', 'blockquote', 'figure', 'figcaption', 'ul', 'ol', 'li', 'dl', 'dt', 'dd', 'h1', 'h2', 'h3', 'h4', 'h5', 'h6', 'details', 'summary', 'hr', 'table', 'tr', 'td', 'th', 'tbody', 'thead' ), true );
	}

	private static function has_block_child( DOMNode $node ) {
		foreach ( $node->childNodes as $c ) {
			if ( XML_ELEMENT_NODE === $c->nodeType && self::is_block( strtolower( $c->nodeName ) ) ) {
				return true;
			}
		}
		return false;
	}

	/** The top level: paragraphs and lists; loose inline text is gathered into paragraphs. */
	private static function top( DOMNode $node, $allow, &$out, &$run ) {
		foreach ( $node->childNodes as $c ) {
			if ( XML_TEXT_NODE === $c->nodeType ) {
				if ( '' !== trim( $c->nodeValue ) || '' !== $run ) {
					$run .= self::esc( $c->nodeValue );
				}
				continue;
			}
			if ( XML_ELEMENT_NODE !== $c->nodeType ) {
				continue;
			}
			$name = strtolower( $c->nodeName );
			if ( 'img' === $name || 'hr' === $name ) {
				continue;
			}
			if ( ( 'ul' === $name || 'ol' === $name ) && isset( $allow[ $name ] ) ) {
				self::flush( $out, $run );
				$items = self::items( $c, $allow );
				if ( '' !== $items ) {
					$out[] = '<' . $name . '>' . $items . '</' . $name . '>';
				}
				continue;
			}
			if ( self::is_block( $name ) ) {
				self::flush( $out, $run );
				if ( self::has_block_child( $c ) ) {
					self::top( $c, $allow, $out, $run );
					self::flush( $out, $run );
				} else {
					$inner = trim( self::inline( $c, $allow ) );
					if ( '' !== trim( str_replace( '<br>', '', $inner ) ) ) {
						$out[] = '<p>' . $inner . '</p>';
					}
				}
				continue;
			}
			$run .= self::inline_one( $c, $allow );
		}
	}

	private static function items( DOMNode $list, $allow ) {
		$html = '';
		foreach ( $list->childNodes as $c ) {
			if ( XML_ELEMENT_NODE === $c->nodeType && 'li' === strtolower( $c->nodeName ) ) {
				$inner = trim( self::inline( $c, $allow ) );
				if ( '' !== $inner ) {
					$html .= '<li>' . $inner . '</li>';
				}
			} elseif ( XML_TEXT_NODE === $c->nodeType && '' !== trim( $c->nodeValue ) ) {
				$html .= '<li>' . self::esc( trim( $c->nodeValue ) ) . '</li>';
			}
		}
		return $html;
	}

	/** Everything inside a paragraph, list item or link: words, bold, italic, links and line breaks. */
	private static function inline( DOMNode $node, $allow, $in_link = false ) {
		$html = '';
		foreach ( $node->childNodes as $c ) {
			$html .= self::inline_one( $c, $allow, $in_link );
		}
		return $html;
	}

	private static function inline_one( DOMNode $c, $allow, $in_link = false ) {
		if ( XML_TEXT_NODE === $c->nodeType ) {
			return self::esc( $c->nodeValue );
		}
		if ( XML_ELEMENT_NODE !== $c->nodeType ) {
			return '';
		}
		$name = strtolower( $c->nodeName );
		$map  = array( 'b' => 'strong', 'i' => 'em' );
		if ( isset( $map[ $name ] ) ) {
			$name = $map[ $name ];
		}
		if ( 'img' === $name ) {
			return '';
		}
		if ( 'br' === $name ) {
			return isset( $allow['br'] ) ? '<br>' : ' ';
		}
		if ( ( 'strong' === $name || 'em' === $name ) && isset( $allow[ $name ] ) ) {
			$inner = self::inline( $c, $allow, $in_link );
			return '' === trim( $inner ) ? $inner : '<' . $name . '>' . $inner . '</' . $name . '>';
		}
		if ( 'a' === $name && isset( $allow['a'] ) && ! $in_link ) {
			$href  = TWD_SK_Sanitizer::safe_url( $c->getAttribute( 'href' ), array( 'http', 'https', 'mailto', 'tel' ) );
			$inner = self::inline( $c, $allow, true );
			if ( ! is_string( $href ) || '' === $href || '' === trim( $inner ) ) {
				return $inner;
			}
			return '<a href="' . self::esc_attr( $href ) . '">' . $inner . '</a>';
		}
		// Anything else (a span, a heading inside a paragraph, a list where none is allowed) keeps its words only.
		$inner = self::inline( $c, $allow, $in_link );
		return self::is_block( $name ) ? ' ' . $inner . ' ' : $inner;
	}

	// -- Sections in the description ---------------------------------------------

	/**
	 * The description as sections: array of { heading, html }. Text before the first h2 or h3 is
	 * "About this piece". An empty description gives no sections.
	 */
	public static function split( $description ) {
		$description = is_string( $description ) ? $description : '';
		$parts       = preg_split( '#<h([23])\b[^>]*>(.*?)</h\1\s*>#is', $description, -1, PREG_SPLIT_DELIM_CAPTURE );
		$out         = array();
		$intro       = self::clean_section( isset( $parts[0] ) ? $parts[0] : '' );
		if ( '' !== $intro ) {
			$out[] = array( 'heading' => self::FIRST, 'html' => $intro );
		}
		for ( $i = 1; $i + 1 < count( $parts ); $i += 3 ) {
			$heading = self::plain( (string) $parts[ $i + 1 ], self::MAX_HEADING );
			$body    = self::clean_section( isset( $parts[ $i + 2 ] ) ? (string) $parts[ $i + 2 ] : '' );
			if ( '' === $heading && '' === $body ) {
				continue;
			}
			$out[] = array( 'heading' => '' !== $heading ? $heading : 'More about this piece', 'html' => $body );
		}
		return $out;
	}

	/**
	 * The description as sections for SHOWING (the product page): the same cut as split(), but each part
	 * is left exactly as written, pictures, embeds and shortcodes included, for WordPress to format.
	 */
	public static function split_raw( $description ) {
		$description = is_string( $description ) ? $description : '';
		$parts       = preg_split( '#<h([23])\b[^>]*>(.*?)</h\1\s*>#is', $description, -1, PREG_SPLIT_DELIM_CAPTURE );
		$out         = array();
		$intro       = isset( $parts[0] ) ? trim( (string) $parts[0] ) : '';
		if ( '' !== $intro && ( '' !== trim( wp_strip_all_tags( $intro ) ) || preg_match( '#<(img|iframe|video)\b|\[#i', $intro ) ) ) {
			$out[] = array( 'heading' => self::FIRST, 'html' => $intro );
		}
		for ( $i = 1; $i + 1 < count( $parts ); $i += 3 ) {
			$heading = self::plain( (string) $parts[ $i + 1 ], self::MAX_HEADING );
			$body    = isset( $parts[ $i + 2 ] ) ? trim( (string) $parts[ $i + 2 ] ) : '';
			if ( '' === $heading && '' === $body ) {
				continue;
			}
			$out[] = array( 'heading' => '' !== $heading ? $heading : 'More about this piece', 'html' => $body );
		}
		return $out;
	}

	/** Sections back into one description. A section with no words is left out. */
	public static function join( $sections ) {
		$html = array();
		foreach ( (array) $sections as $s ) {
			if ( ! is_array( $s ) || ! isset( $s['html'] ) || '' === trim( (string) $s['html'] ) ) {
				continue;
			}
			$html[] = '<h3>' . self::esc( (string) $s['heading'] ) . '</h3>' . "\n" . $s['html'];
		}
		return implode( "\n", $html );
	}

	/** Words still to fill in: TO CONFIRM and [PLACEHOLDER markers, by where they are. */
	public static function markers( $fields ) {
		$found = array();
		$check = function ( $where, $text ) use ( &$found ) {
			if ( false !== stripos( (string) $text, 'TO CONFIRM' ) || false !== stripos( (string) $text, '[PLACEHOLDER' ) ) {
				$found[] = $where;
			}
		};
		$check( 'the title', isset( $fields['title'] ) ? $fields['title'] : '' );
		$check( 'the short summary', isset( $fields['summary'] ) ? $fields['summary'] : '' );
		foreach ( isset( $fields['details'] ) ? (array) $fields['details'] : array() as $row ) {
			$check( 'the detail ' . $row['label'], $row['label'] . ' ' . $row['value'] );
		}
		foreach ( isset( $fields['sections'] ) ? (array) $fields['sections'] : array() as $s ) {
			$check( 'the section ' . $s['heading'], $s['heading'] . ' ' . $s['html'] );
		}
		return $found;
	}
}
