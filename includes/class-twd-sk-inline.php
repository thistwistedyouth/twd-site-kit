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
 *
 * Picture spots (0.10.1): where the AI wrote a marker such as [PLACEHOLDER: image needed] instead of
 * a picture, the marker is numbered as a spot (data-twd-sk-s). Choosing a picture for it puts a real
 * picture there, with the same class as the section's other pictures.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class TWD_SK_Inline {

	const TEXT_ATTR = 'data-twd-sk-t';
	const PIC_ATTR  = 'data-twd-sk-p';
	const SPOT_ATTR = 'data-twd-sk-s';
	// 0.14.0: things that can be removed (a section, a repeated item such as a list entry or card, a button).
	const REMOVE_ATTR  = 'data-twd-sk-r';
	// 0.14.1: each top-level section's number, counted as TWD_SK_Sections counts, so Add, Move and Look name a section.
	const SECTION_ATTR = 'data-twd-sk-sec';
	// 0.15.0: the layouts that section can move to, for the Look tool.
	const LAYOUT_ATTR  = 'data-twd-sk-lay';
	const MAX_REMOVES  = 50;
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

	/** A marker that stands where a picture should go. Not one about a caption or a description. */
	public static function is_spot_text( $text ) {
		$text = trim( (string) $text );
		return '' !== $text
			&& 1 === preg_match( '/^\[PLACEHOLDER:?[^\]]*\b(?:image|picture|photo|photograph|pic)s?\b[^\]]*\]$/i', $text )
			&& 1 !== preg_match( '/\b(?:caption|alt|description|describe|credit|title|heading)\b/i', $text );
	}

	/** An element that holds nothing but a picture marker. */
	private static function is_spot_element( DOMElement $el ) {
		if ( ! in_array( strtolower( $el->nodeName ), array( 'p', 'div', 'span', 'figure' ), true ) ) {
			return false;
		}
		foreach ( $el->childNodes as $child ) {
			if ( $child instanceof DOMElement ) {
				return false;
			}
		}
		return self::is_spot_text( $el->textContent );
	}

	/** A figure, or a box with a kit class, keeps its place and gets the picture inside. Anything else is replaced by it. */
	private static function spot_fills( DOMNode $node ) {
		if ( ! $node instanceof DOMElement ) {
			return false;
		}
		$tag = strtolower( $node->nodeName );
		return 'figure' === $tag || ( 'div' === $tag && self::has_kit_class( $node ) );
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
	/** A question group's tab link (0.15.0): its words can change, its address (the group it opens) never does. */
	private static function is_tab_link( DOMElement $el ) {
		return 'a' === strtolower( $el->nodeName ) && 1 === preg_match( '/(^|\s)twd-sk-faq__tablink(\s|$)/', $el->getAttribute( 'class' ) );
	}

	private static function is_text( DOMElement $el ) {
		$tag = strtolower( $el->nodeName );
		if ( ! in_array( $tag, self::text_tags(), true ) ) {
			return false;
		}
		// A group tab's list entry holds only its link: the link is the piece of text, never the entry (0.15.0),
		// or typing over all of it would take the link away.
		if ( 'li' === $tag && 1 === preg_match( '/(^|\s)twd-sk-faq__tab(\s|$)/', $el->getAttribute( 'class' ) ) ) {
			return false;
		}
		if ( in_array( $tag, array( 'span', 'div', 'a', 'small' ), true ) && ! self::has_kit_class( $el ) ) {
			return false;
		}
		if ( 'a' === $tag && ! self::is_button( $el ) && ! self::is_tab_link( $el ) ) {
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
		$parent = strtolower( $node->nodeName );
		foreach ( $node->childNodes as $child ) {
			// A bare marker in a figure or box (no paragraph round it) is a spot too.
			if ( $child instanceof DOMText && in_array( $parent, array( 'div', 'figure', 'section' ), true ) && self::is_spot_text( $child->nodeValue ) ) {
				$found['spots'][] = $child;
				continue;
			}
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
			if ( self::is_spot_element( $child ) ) {
				$found['spots'][] = $child;
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
		$found = array( 'text' => array(), 'pics' => array(), 'spots' => array() );
		self::walk( $root, $found );
		$found['removes'] = self::removables( $root );
		return $found;
	}

	// -- Things that can be removed (0.14.0) --------------------------------------------

	/** Elements with a brother or sister of the same tag and classes under the same parent (one of several alike). */
	private static function has_like_sibling( DOMElement $el ) {
		$tag   = strtolower( $el->nodeName );
		$class = trim( $el->getAttribute( 'class' ) );
		foreach ( $el->parentNode->childNodes as $other ) {
			if ( $other !== $el && $other instanceof DOMElement && strtolower( $other->nodeName ) === $tag && trim( $other->getAttribute( 'class' ) ) === $class ) {
				return true;
			}
		}
		return false;
	}

	/**
	 * What may be removed, in document order: a top-level section (not the one holding the page's h1, not a
	 * locked one), any button, and any element that is one of several alike (a list entry, a card, a row, a
	 * question, a picture in a grid). Nothing inside a piece of text, a locked section or a shortcode holder.
	 */
	private static function removables( DOMElement $root ) {
		$list = array();
		foreach ( $root->childNodes as $child ) {
			if ( ! $child instanceof DOMElement ) {
				continue;
			}
			if ( 'section' === strtolower( $child->nodeName ) ) {
				if ( self::section_locked( $child ) ) {
					continue;
				}
				if ( 0 === $child->getElementsByTagName( 'h1' )->length ) {
					$list[] = $child;
				}
			}
			self::walk_removables( $child, $list );
		}
		return $list;
	}

	private static function walk_removables( DOMElement $node, array &$list ) {
		foreach ( $node->childNodes as $child ) {
			if ( ! $child instanceof DOMElement ) {
				continue;
			}
			$tag = strtolower( $child->nodeName );
			if ( 'section' === $tag && self::section_locked( $child ) ) {
				continue;
			}
			$text = self::is_text( $child );
			if ( ( self::is_button( $child ) || ( 'img' !== $tag && 'section' !== $tag && self::has_like_sibling( $child ) ) ) && ! self::holds_shortcode( $child ) ) {
				$list[] = $child;
			}
			// Inside a piece of text (a paragraph, a button, a list entry) nothing smaller is removed on its own.
			if ( ! $text ) {
				self::walk_removables( $child, $list );
			}
		}
	}

	private static function holds_shortcode( DOMElement $el ) {
		$codes = array_keys( TWD_SK_Registry::allowed_shortcodes() );
		return $codes && 1 === preg_match( '/\[(?:' . implode( '|', array_map( 'preg_quote', $codes ) ) . ')[\s\]]/i', (string) $el->textContent );
	}

	private static function has_class( DOMElement $el, $class ) {
		return false !== strpos( ' ' . $el->getAttribute( 'class' ) . ' ', ' ' . $class . ' ' );
	}

	/** The other half of a question group (0.15.0): a group's tab, or a tab's group. Null when there is none. */
	private static function group_partner( DOMElement $el ) {
		$xp = new DOMXPath( $el->ownerDocument );
		if ( self::has_class( $el, 'twd-sk-faq__tab' ) ) {
			foreach ( $el->getElementsByTagName( 'a' ) as $a ) {
				if ( 1 === preg_match( '/^#([\w-]+)$/', $a->getAttribute( 'href' ), $m ) ) {
					$hit = $xp->query( '//*[@id="' . $m[1] . '"]' );
					return $hit && $hit->length ? $hit->item( 0 ) : null;
				}
			}
			return null;
		}
		if ( self::has_class( $el, 'twd-sk-faq__group' ) && 1 === preg_match( '/^[\w-]+$/', $el->getAttribute( 'id' ) ) ) {
			$hit = $xp->query( '//a[@href="#' . $el->getAttribute( 'id' ) . '"]' );
			if ( $hit && $hit->length ) {
				for ( $n = $hit->item( 0 ); $n; $n = $n->parentNode ) {
					if ( $n instanceof DOMElement && self::has_class( $n, 'twd-sk-faq__tab' ) ) {
						return $n;
					}
				}
			}
		}
		return null;
	}

	/** The box that holds question groups, when this element is one of them. */
	private static function group_holder( DOMElement $el ) {
		return self::has_class( $el, 'twd-sk-faq__group' ) && $el->parentNode instanceof DOMElement ? $el->parentNode : null;
	}

	/** After a group goes, one of the groups that are left is the one shown first. */
	private static function ensure_default_group( DOMElement $holder ) {
		if ( ! $holder->parentNode ) {
			return;
		}
		$first = null;
		foreach ( $holder->childNodes as $child ) {
			if ( ! $child instanceof DOMElement || ! self::has_class( $child, 'twd-sk-faq__group' ) ) {
				continue;
			}
			if ( self::has_class( $child, 'twd-sk-faq__group--default' ) ) {
				return;
			}
			if ( null === $first ) {
				$first = $child;
			}
		}
		if ( $first ) {
			$first->setAttribute( 'class', trim( $first->getAttribute( 'class' ) ) . ' twd-sk-faq__group--default' );
		}
	}

	/** Take an element out, then any wrapper it leaves empty, up to (never including) its section. */
	private static function remove_node( DOMElement $el ) {
		$parent = $el->parentNode;
		$parent->removeChild( $el );
		while ( $parent instanceof DOMElement && 'section' !== strtolower( $parent->nodeName ) && '' === trim( (string) $parent->textContent ) && 0 === $parent->getElementsByTagName( 'img' )->length && $parent->parentNode instanceof DOMElement && 'body' !== strtolower( $parent->parentNode->nodeName ) ) {
			$up = $parent->parentNode;
			$up->removeChild( $parent );
			$parent = $up;
		}
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
		foreach ( $found['removes'] as $i => $el ) {
			$el->setAttribute( self::REMOVE_ATTR, (string) $i );
		}
		// Top-level sections, from 0 in page order (a section inside another section is not counted).
		$n = 0;
		foreach ( iterator_to_array( $root->getElementsByTagName( 'section' ) ) as $sec ) {
			$nested = false;
			for ( $up = $sec->parentNode; $up instanceof DOMElement && $up !== $root; $up = $up->parentNode ) {
				if ( 'section' === strtolower( $up->nodeName ) ) {
					$nested = true;
					break;
				}
			}
			if ( ! $nested ) {
				$sec->setAttribute( self::SECTION_ATTR, (string) $n++ );
				// The layouts this section can move to (0.15.0), from the same registry rules the server applies.
				$classes = array_values( array_filter( preg_split( '/\s+/', trim( $sec->getAttribute( 'class' ) ) ), 'strlen' ) );
				$lay     = TWD_SK_Looks::layouts_attr( $classes );
				if ( '' !== $lay ) {
					$sec->setAttribute( self::LAYOUT_ATTR, $lay );
				}
			}
		}
		foreach ( $found['spots'] as $i => $node ) {
			if ( $node instanceof DOMText ) {
				// Only in what this person's browser receives: the stored page is unchanged.
				$span = $doc->createElement( 'span' );
				$node->parentNode->replaceChild( $span, $node );
				$span->appendChild( $node );
				$node = $span;
			}
			$node->setAttribute( self::SPOT_ATTR, (string) $i );
		}
		return self::save( $doc, $root );
	}

	/** How many pieces of text, pictures and picture spots can be changed (for the editor and the tests). */
	public static function count( $html ) {
		$loaded = self::load( (string) $html );
		if ( ! $loaded ) {
			return array( 'text' => 0, 'pics' => 0, 'spots' => 0, 'removes' => 0 );
		}
		$found = self::targets( $loaded[1] );
		return array( 'text' => count( $found['text'] ), 'pics' => count( $found['pics'] ), 'spots' => count( $found['spots'] ), 'removes' => count( $found['removes'] ) );
	}

	// -- Saving the changes ------------------------------------------------------------

	/**
	 * Put changed words and pictures into the stored page. Saves nothing.
	 *
	 * @param string $html  The stored page (the version the browser drew).
	 * @param array  $texts List of { i, html, href? }.
	 * @param array  $pics  List of { i, id, alt }. An id of 0 keeps the picture and changes only its description.
	 * @param array  $spots List of { i, id, alt }: a picture for a spot.
	 * @param array  $removes List of numbers of things to remove (0.14.0), as the page was drawn.
	 * @return array|WP_Error { html, changed, removed }
	 */
	public static function apply( $html, $texts, $pics, $spots = array(), $removes = array() ) {
		$texts   = is_array( $texts ) ? array_values( $texts ) : array();
		$pics    = is_array( $pics ) ? array_values( $pics ) : array();
		$spots   = is_array( $spots ) ? array_values( $spots ) : array();
		$removes = is_array( $removes ) ? array_values( $removes ) : array();
		if ( ! $texts && ! $pics && ! $spots && ! $removes ) {
			return new WP_Error( 'twd_sk_bad_input', 'There are no changes to save.' );
		}
		if ( count( $removes ) > self::MAX_REMOVES ) {
			return new WP_Error( 'twd_sk_bad_input', 'That is too many things to remove at once. Save some, then carry on.' );
		}
		foreach ( $removes as $r ) {
			if ( ! is_numeric( $r ) ) {
				return new WP_Error( 'twd_sk_bad_input', 'A removal could not be read. Reload the page and try again.' );
			}
		}
		if ( count( $texts ) + count( $pics ) + count( $spots ) > self::MAX_EDITS ) {
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
			// Quote line (0.15.0): a shared class on the paragraph itself (true adds it, false takes it off).
			if ( array_key_exists( 'quote', $edit ) ) {
				if ( 'p' !== strtolower( $el->tagName ) ) {
					return new WP_Error( 'twd_sk_bad_input', 'A quote line goes on a paragraph.' );
				}
				$classes = preg_split( '/\s+/', trim( (string) $el->getAttribute( 'class' ) ), -1, PREG_SPLIT_NO_EMPTY );
				$classes = array_values( array_diff( $classes, array( 'twd-sk-quoteline' ) ) );
				if ( $edit['quote'] ) {
					$classes[] = 'twd-sk-quoteline';
				}
				if ( $classes ) {
					$el->setAttribute( 'class', implode( ' ', $classes ) );
				} else {
					$el->removeAttribute( 'class' );
				}
			}
			while ( $el->firstChild ) {
				$el->removeChild( $el->firstChild );
			}
			if ( self::is_button( $el ) || self::is_tab_link( $el ) ) {
				if ( self::is_tab_link( $el ) && isset( $edit['href'] ) && is_string( $edit['href'] ) && '' !== trim( $edit['href'] ) ) {
					return new WP_Error( 'twd_sk_bad_input', 'A group tab always opens its own group, so its address cannot be changed here.' );
				}
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
			$img = $found['pics'][ $i ];
			$alt = isset( $pic['alt'] ) && is_string( $pic['alt'] ) ? TWD_SK_Profile::text( $pic['alt'], self::MAX_ALT ) : '';
			if ( 0 === (int) $pic['id'] ) {
				// The same picture, with a new description.
				if ( ! isset( $pic['alt'] ) || ! is_string( $pic['alt'] ) ) {
					return new WP_Error( 'twd_sk_bad_input', 'A picture change could not be read. Reload the page and try again.' );
				}
				$img->setAttribute( 'alt', $alt );
				$changed++;
				continue;
			}
			$url = self::picture_url( (int) $pic['id'] );
			if ( is_wp_error( $url ) ) {
				return $url;
			}
			$img->setAttribute( 'src', $url );
			// The size is added again when the page is drawn, from the new picture.
			$img->removeAttribute( 'width' );
			$img->removeAttribute( 'height' );
			$img->setAttribute( 'alt', $alt );
			$changed++;
		}

		foreach ( $spots as $spot ) {
			if ( ! is_array( $spot ) || ! isset( $spot['i'], $spot['id'] ) || ! is_numeric( $spot['i'] ) || ! is_numeric( $spot['id'] ) ) {
				return new WP_Error( 'twd_sk_bad_input', 'A picture change could not be read. Reload the page and try again.' );
			}
			$i = (int) $spot['i'];
			if ( ! isset( $found['spots'][ $i ] ) ) {
				return new WP_Error( 'twd_sk_conflict', 'The page changed since it was opened, so the place for the picture could not be matched. Reload the page and choose it again.' );
			}
			$url = self::picture_url( (int) $spot['id'] );
			if ( is_wp_error( $url ) ) {
				return $url;
			}
			$node  = $found['spots'][ $i ];
			$img   = $doc->createElement( 'img' );
			$class = self::picture_class( $node );
			if ( '' !== $class ) {
				$img->setAttribute( 'class', $class );
			}
			$img->setAttribute( 'src', $url );
			$img->setAttribute( 'alt', isset( $spot['alt'] ) && is_string( $spot['alt'] ) ? TWD_SK_Profile::text( $spot['alt'], self::MAX_ALT ) : '' );
			if ( self::spot_fills( $node ) ) {
				while ( $node->firstChild ) {
					$node->removeChild( $node->firstChild );
				}
				$node->appendChild( $img );
			} else {
				$node->parentNode->replaceChild( $img, $node );
			}
			$changed++;
		}

		// Removals last, biggest number first, so an item inside a section that also goes is simply gone.
		$removed = 0;
		$numbers = array_unique( array_map( 'intval', $removes ) );
		rsort( $numbers );
		foreach ( $numbers as $i ) {
			if ( ! isset( $found['removes'][ $i ] ) ) {
				return new WP_Error( 'twd_sk_conflict', 'The page changed since it was opened, so the thing to remove could not be matched. Reload the page and try again.' );
			}
			$el = $found['removes'][ $i ];
			if ( ! $el->parentNode ) {
				continue; // Already gone with its section.
			}
			// A question group goes with its tab and a tab with its group, and the groups keep one that shows first.
			$partner = self::group_partner( $el );
			$holder  = self::group_holder( $el );
			if ( null === $holder && $partner ) {
				$holder = self::group_holder( $partner );
			}
			self::remove_node( $el );
			if ( $partner && $partner->parentNode ) {
				self::remove_node( $partner );
			}
			if ( $holder ) {
				self::ensure_default_group( $holder );
			}
			$removed++;
		}
		if ( $removed && 0 === $root->getElementsByTagName( 'section' )->length ) {
			return new WP_Error( 'twd_sk_bad_input', 'A page cannot lose every section.' );
		}
		return array( 'html' => self::save( $doc, $root ), 'changed' => $changed, 'removed' => $removed );
	}

	/** The address of a picture in the media library, at the large size when there is one. */
	private static function picture_url( $id ) {
		if ( $id <= 0 || ! function_exists( 'wp_attachment_is_image' ) || ! wp_attachment_is_image( $id ) ) {
			return new WP_Error( 'twd_sk_bad_input', 'That is not a picture in the media library.' );
		}
		$src = function_exists( 'wp_get_attachment_image_src' ) ? wp_get_attachment_image_src( $id, 'large' ) : false;
		$url = is_array( $src ) && ! empty( $src[0] ) ? $src[0] : ( function_exists( 'wp_get_attachment_url' ) ? wp_get_attachment_url( $id ) : '' );
		if ( ! is_string( $url ) || '' === $url ) {
			return new WP_Error( 'twd_sk_bad_input', 'That picture has no address. Choose another.' );
		}
		return $url;
	}

	/**
	 * The class a picture needs in this place: from the box it sits in, or the section it belongs to,
	 * or else copied from another picture in the same section. '' when none fits.
	 */
	private static function picture_class( DOMNode $node ) {
		$map = array(
			'twd-sk-image-text__media' => 'twd-sk-image-text__img',
			'twd-sk-hero__media'       => 'twd-sk-hero__img',
			'twd-sk-contact__media'    => 'twd-sk-contact__img',
			'twd-sk-pictures__item'    => 'twd-sk-pictures__img',
			'twd-sk-logos__item'       => 'twd-sk-logos__img',
			'twd-sk-pictures'          => 'twd-sk-pictures__img',
			'twd-sk-image-text'        => 'twd-sk-image-text__img',
			'twd-sk-logos'             => 'twd-sk-logos__img',
		);
		$section = null;
		for ( $n = $node instanceof DOMElement ? $node : $node->parentNode; $n instanceof DOMElement; $n = $n->parentNode ) {
			foreach ( preg_split( '/\s+/', trim( $n->getAttribute( 'class' ) ) ) as $c ) {
				if ( isset( $map[ $c ] ) ) {
					return $map[ $c ];
				}
			}
			if ( 'section' === strtolower( $n->nodeName ) ) {
				$section = $n;
				break;
			}
		}
		if ( $section ) {
			foreach ( $section->getElementsByTagName( 'img' ) as $other ) {
				foreach ( preg_split( '/\s+/', trim( $other->getAttribute( 'class' ) ) ) as $c ) {
					if ( 0 === strpos( $c, 'twd-sk-' ) && '__img' === substr( $c, -5 ) ) {
						return $c;
					}
				}
			}
		}
		return '';
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
