<?php
/**
 * TWD_SK_Inline: changing words and pictures in place on the live page (0.10.0).
 *
 * When someone who can edit a kit page views it, mark() numbers every piece of text they may change
 * (data-twd-sk-t) and every picture (data-twd-sk-p) in the page as drawn, in document order. Nothing
 * is stored: the numbers exist only in what that person's browser receives. When they save, apply()
 * numbers the stored page the same way and puts each change in its place, so the rest of the page is
 * never sent back from the browser. The result is saved through TWD_SK_Store like any other change,
 * so the sanitiser runs, it needs the version the page was drawn from, and Undo goes back.
 *
 * What counts as text: a heading, paragraph, list item, caption, question, quote line, label or
 * button whose contents are only words and simple inline markup (bold, italic, links, line breaks).
 * The safety notice is never editable here (its support lines are kept as they are), and nothing
 * that holds a shortcode (the enquiry form) is touched.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class TWD_SK_Inline {

	const TEXT_ATTR = 'data-twd-sk-t';
	const PIC_ATTR  = 'data-twd-sk-p';
	const MAX_EDITS = 400;
	const MAX_TEXT  = 6000;
	const MAX_ALT   = 200;

	/** Tags that may hold changeable words. span, div, a and small also need a twd-sk- class. */
	private static function text_tags() {
		return array( 'h1', 'h2', 'h3', 'h4', 'h5', 'h6', 'p', 'li', 'figcaption', 'summary', 'dt', 'dd', 'blockquote', 'cite', 'span', 'div', 'a', 'small' );
	}

	/** Markup allowed inside changeable words. */
	private static function inline_tags() {
		return array( 'strong', 'em', 'b', 'i', 'a', 'br', 'span', 'small', 'u', 'mark', 'sub', 'sup' );
	}

	/** Section types whose words are never changed here. */
	private static function locked_types() {
		return array( 'notice' );
	}

	// -- Reading and writing HTML (the same way the sanitiser does) -----------------

	private static function load( $html ) {
		$previous = libxml_use_internal_errors( true );
		$doc      = new DOMDocument();
		$wrapped  = '<!DOCTYPE html><html><head><meta http-equiv="Content-Type" content="text/html; charset=utf-8"></head><body><div data-twd-sk-root="1">'
			. $html
			. '</div></body></html>';
		$ok = $doc->loadHTML( $wrapped, LIBXML_NONET | LIBXML_NOERROR | LIBXML_NOWARNING );
		libxml_clear_errors();
		libxml_use_internal_errors( $previous );
		if ( ! $ok ) {
			return null;
		}
		$root = null;
		foreach ( $doc->getElementsByTagName( 'div' ) as $div ) {
			if ( '1' === $div->getAttribute( 'data-twd-sk-root' ) ) {
				$root = $div;
				break;
			}
		}
		return $root ? array( $doc, $root ) : null;
	}

	private static function save( DOMDocument $doc, DOMElement $root ) {
		$out = '';
		foreach ( $root->childNodes as $child ) {
			$out .= $doc->saveHTML( $child );
		}
		return $out;
	}

	private static function has_kit_class( DOMElement $el ) {
		return false !== strpos( ' ' . $el->getAttribute( 'class' ), ' twd-sk-' );
	}

	private static function is_button( DOMElement $el ) {
		return 'a' === strtolower( $el->nodeName ) && 1 === preg_match( '/(^|\s)twd-sk-btn(\s|$)/', $el->getAttribute( 'class' ) );
	}

	/** True when this element holds only words and simple inline markup, and is worth changing. */
	private static function is_text( DOMElement $el ) {
		$tag = strtolower( $el->nodeName );
		if ( ! in_array( $tag, self::text_tags(), true ) ) {
			return false;
		}
		if ( in_array( $tag, array( 'span', 'div', 'a', 'small' ), true ) && ! self::has_kit_class( $el ) ) {
			return false;
		}
		if ( 'a' === $tag && ! self::is_button( $el ) ) {
			return false;
		}
		$text = (string) $el->textContent;
		if ( '' === trim( $text ) ) {
			return false;
		}
		// Anything holding a shortcode is drawn by the plugin (the enquiry form): never edited in place.
		$codes = array_keys( TWD_SK_Registry::allowed_shortcodes() );
		if ( $codes && 1 === preg_match( '/\[(?:' . implode( '|', array_map( 'preg_quote', $codes ) ) . ')[\s\]]/i', $text ) ) {
			return false;
		}
		$inline = self::inline_tags();
		foreach ( $el->getElementsByTagName( '*' ) as $child ) {
			if ( ! in_array( strtolower( $child->nodeName ), $inline, true ) || self::is_button( $child ) ) {
				return false;
			}
		}
		return true;
	}

	private static function section_locked( DOMElement $section ) {
		$classes = preg_split( '/\s+/', trim( $section->getAttribute( 'class' ) ) );
		foreach ( self::locked_types() as $type ) {
			if ( in_array( 'twd-sk-' . $type, $classes, true ) ) {
				return true;
			}
		}
		return false;
	}

	private static function walk( DOMNode $node, array &$found ) {
		foreach ( $node->childNodes as $child ) {
			if ( ! $child instanceof DOMElement ) {
				continue;
			}
			$tag = strtolower( $child->nodeName );
			if ( 'section' === $tag && self::section_locked( $child ) ) {
				continue;
			}
			if ( 'img' === $tag ) {
				$found['pics'][] = $child;
				continue;
			}
			if ( self::is_text( $child ) ) {
				$found['text'][] = $child;
				continue;
			}
			self::walk( $child, $found );
		}
	}

	/** The changeable text and pictures of a page, in document order. */
	private static function targets( DOMElement $root ) {
		$found = array( 'text' => array(), 'pics' => array() );
		self::walk( $root, $found );
		return $found;
	}

	// -- Drawing the page for someone who can edit it ---------------------------------

	/** May the person viewing this page change it in place? */
	public static function allowed( $post_id ) {
		if ( TWD_SK_Safe::on() || ! class_exists( 'TWD_SK_Editor' ) ) {
			return false;
		}
		$context = TWD_SK_Editor::context();
		return null !== $context && $context['is_kit_page'] && (int) $context['page_id'] === (int) $post_id;
	}

	/** The page with every changeable piece of text and every picture numbered. Unchanged if it cannot be read. */
	public static function mark( $html ) {
		if ( ! is_string( $html ) || '' === trim( $html ) ) {
			return $html;
		}
		$loaded = self::load( $html );
		if ( ! $loaded ) {
			return $html;
		}
		list( $doc, $root ) = $loaded;
		$found = self::targets( $root );
		foreach ( $found['text'] as $i => $el ) {
			$el->setAttribute( self::TEXT_ATTR, (string) $i );
		}
		foreach ( $found['pics'] as $i => $el ) {
			$el->setAttribute( self::PIC_ATTR, (string) $i );
		}
		return self::save( $doc, $root );
	}

	/** How many pieces of text and pictures can be changed (for the editor and the tests). */
	public static function count( $html ) {
		$loaded = self::load( (string) $html );
		if ( ! $loaded ) {
			return array( 'text' => 0, 'pics' => 0 );
		}
		$found = self::targets( $loaded[1] );
		return array( 'text' => count( $found['text'] ), 'pics' => count( $found['pics'] ) );
	}

	// -- Saving the changes ------------------------------------------------------------

	/**
	 * Put changed words and pictures into the stored page. Saves nothing.
	 *
	 * @param string $html  The stored page (the version the browser drew).
	 * @param array  $texts List of { i, html, href? }.
	 * @param array  $pics  List of { i, id, alt }.
	 * @return array|WP_Error { html, changed }
	 */
	public static function apply( $html, $texts, $pics ) {
		$texts = is_array( $texts ) ? array_values( $texts ) : array();
		$pics  = is_array( $pics ) ? array_values( $pics ) : array();
		if ( ! $texts && ! $pics ) {
			return new WP_Error( 'twd_sk_bad_input', 'There are no changes to save.' );
		}
		if ( count( $texts ) + count( $pics ) > self::MAX_EDITS ) {
			return new WP_Error( 'twd_sk_bad_input', 'That is too many changes to save at once. Save some, then carry on.' );
		}
		$loaded = self::load( (string) $html );
		if ( ! $loaded ) {
			return new WP_Error( 'twd_sk_bad_input', 'This page could not be read. Reload it and try again.' );
		}
		list( $doc, $root ) = $loaded;
		$found   = self::targets( $root );
		$changed = 0;

		foreach ( $texts as $edit ) {
			if ( ! is_array( $edit ) || ! isset( $edit['i'], $edit['html'] ) || ! is_numeric( $edit['i'] ) || ! is_string( $edit['html'] ) ) {
				return new WP_Error( 'twd_sk_bad_input', 'A change could not be read. Reload the page and try again.' );
			}
			$i = (int) $edit['i'];
			if ( ! isset( $found['text'][ $i ] ) ) {
				return new WP_Error( 'twd_sk_conflict', 'The page changed since it was opened, so the words could not be matched. Reload the page and make the change again.' );
			}
			if ( strlen( $edit['html'] ) > self::MAX_TEXT ) {
				return new WP_Error( 'twd_sk_bad_input', 'One piece of text is too long. Keep each one under ' . self::MAX_TEXT . ' characters.' );
			}
			$el = $found['text'][ $i ];
			while ( $el->firstChild ) {
				$el->removeChild( $el->firstChild );
			}
			if ( self::is_button( $el ) ) {
				$label = trim( preg_replace( '/\s+/u', ' ', html_entity_decode( strip_tags( str_replace( array( '<br>', '<br/>', '<br />' ), ' ', $edit['html'] ) ), ENT_QUOTES, 'UTF-8' ) ) );
				$el->appendChild( $doc->createTextNode( self::tidy( $label ) ) );
				if ( isset( $edit['href'] ) && is_string( $edit['href'] ) && '' !== trim( $edit['href'] ) ) {
					$href = TWD_SK_Sanitizer::safe_url( trim( $edit['href'] ), array( 'http', 'https', 'mailto', 'tel' ) );
					if ( false === $href ) {
						return new WP_Error( 'twd_sk_bad_input', 'That button link is not allowed. Use a page on this site, https, mailto or tel.' );
					}
					$el->setAttribute( 'href', $href );
				}
			} else {
				self::fill( $doc, $el, $edit['html'] );
			}
			$changed++;
		}

		foreach ( $pics as $pic ) {
			if ( ! is_array( $pic ) || ! isset( $pic['i'], $pic['id'] ) || ! is_numeric( $pic['i'] ) || ! is_numeric( $pic['id'] ) ) {
				return new WP_Error( 'twd_sk_bad_input', 'A picture change could not be read. Reload the page and try again.' );
			}
			$i = (int) $pic['i'];
			if ( ! isset( $found['pics'][ $i ] ) ) {
				return new WP_Error( 'twd_sk_conflict', 'The page changed since it was opened, so the picture could not be matched. Reload the page and choose it again.' );
			}
			$id = (int) $pic['id'];
			if ( $id <= 0 || ! function_exists( 'wp_attachment_is_image' ) || ! wp_attachment_is_image( $id ) ) {
				return new WP_Error( 'twd_sk_bad_input', 'That is not a picture in the media library.' );
			}
			$src = function_exists( 'wp_get_attachment_image_src' ) ? wp_get_attachment_image_src( $id, 'large' ) : false;
			$url = is_array( $src ) && ! empty( $src[0] ) ? $src[0] : ( function_exists( 'wp_get_attachment_url' ) ? wp_get_attachment_url( $id ) : '' );
			if ( ! is_string( $url ) || '' === $url ) {
				return new WP_Error( 'twd_sk_bad_input', 'That picture has no address. Choose another.' );
			}
			$img = $found['pics'][ $i ];
			$img->setAttribute( 'src', $url );
			// The size is added again when the page is drawn, from the new picture.
			$img->removeAttribute( 'width' );
			$img->removeAttribute( 'height' );
			$alt = isset( $pic['alt'] ) && is_string( $pic['alt'] ) ? TWD_SK_Profile::text( $pic['alt'], self::MAX_ALT ) : '';
			$img->setAttribute( 'alt', $alt );
			$changed++;
		}
		return array( 'html' => self::save( $doc, $root ), 'changed' => $changed );
	}

	/** Non-breaking spaces from typing in the browser become plain spaces. */
	private static function tidy( $text ) {
		return str_replace( "\xC2\xA0", ' ', (string) $text );
	}

	/** Replace an element's contents with the words and simple markup from the browser. Anything else is unwrapped. */
	private static function fill( DOMDocument $doc, DOMElement $target, $html ) {
		$loaded = self::load( $html );
		if ( ! $loaded ) {
			$target->appendChild( $doc->createTextNode( self::tidy( strip_tags( $html ) ) ) );
			return;
		}
		self::copy_inline( $doc, $loaded[1], $target );
	}

	private static function copy_inline( DOMDocument $doc, DOMNode $from, DOMNode $to ) {
		$map = array( 'b' => 'strong', 'i' => 'em' );
		foreach ( $from->childNodes as $node ) {
			if ( $node instanceof DOMText ) {
				$to->appendChild( $doc->createTextNode( self::tidy( $node->nodeValue ) ) );
				continue;
			}
			if ( ! $node instanceof DOMElement ) {
				continue;
			}
			$tag = strtolower( $node->nodeName );
			$tag = isset( $map[ $tag ] ) ? $map[ $tag ] : $tag;
			// Code and embedded things go entirely, contents and all, as the sanitiser does.
			if ( in_array( $tag, array( 'script', 'style', 'iframe', 'object', 'embed', 'svg', 'math', 'noscript', 'template', 'form', 'input', 'button', 'textarea', 'select', 'video', 'audio', 'canvas' ), true ) ) {
				continue;
			}
			if ( 'br' === $tag ) {
				$to->appendChild( $doc->createElement( 'br' ) );
				continue;
			}
			if ( in_array( $tag, array( 'strong', 'em', 'a', 'span', 'small', 'u', 'mark', 'sub', 'sup' ), true ) ) {
				$el = $doc->createElement( $tag );
				if ( 'a' === $tag ) {
					$href = TWD_SK_Sanitizer::safe_url( (string) $node->getAttribute( 'href' ), array( 'http', 'https', 'mailto', 'tel' ) );
					if ( false === $href ) {
						// A link that is not allowed keeps its words, without the link.
						self::copy_inline( $doc, $node, $to );
						continue;
					}
					$el->setAttribute( 'href', $href );
				}
				// Kit classes only (the accent words in a heading, say). The sanitiser checks them again.
				$classes = array_filter( preg_split( '/\s+/', trim( (string) $node->getAttribute( 'class' ) ) ), function ( $c ) {
					return 0 === strpos( $c, 'twd-sk-' );
				} );
				if ( $classes ) {
					$el->setAttribute( 'class', implode( ' ', $classes ) );
				}
				$to->appendChild( $el );
				self::copy_inline( $doc, $node, $el );
				continue;
			}
			// div, p, font and the like: keep the words, drop the wrapper.
			self::copy_inline( $doc, $node, $to );
		}
	}
}
