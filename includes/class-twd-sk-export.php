<?php
/**
 * TWD_SK_Export: a page or one section as a standalone file, or as a remix brief (session 7, 29 Oct 2026, section 21c).
 *
 * Standalone: one HTML file that opens in a browser as the page looks on the site, with no plugin: the kit rules the
 * page's classes need, the site's own stylesheet, the fonts the style uses (loaded from the site's plugin folder,
 * with system fonts behind them), and every var(--twd-site-...) resolved to the site's actual value. Pictures stay as
 * full web addresses of the media library files, listed with the file. No scripts; the shortcodes (the articles
 * list, the enquiry form, an embed) are a plain note where they sit.
 *
 * Remix brief: one markdown file to give to an AI or keep as a saved look: the HTML, the site stylesheet, the token
 * names with their values, the kit classes, the rules a reply must follow, what a reply must contain, and a second,
 * blank copy with every word and picture a placeholder, so the structure can be reused on another site. A reply goes
 * back in through Replace this section, like any paste.
 *
 * Administrators only (the REST route and the command line decide). The files hold the client's words, so they live
 * in the private clients repository or on the builder's computer, never in this one. Nothing here writes anything.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class TWD_SK_Export {

	const MAX_PASSES = 6;

	// -- The source ----------------------------------------------------------------------

	/**
	 * The stored page, or one of its top-level sections (1-based), with a title and a file name stem.
	 *
	 * @return array|WP_Error { html, title, stem, what }
	 */
	public static function source( $page_id, $section = 0 ) {
		$page_id = (int) $page_id;
		$post    = get_post( $page_id );
		if ( ! $post || 'page' !== $post->post_type ) {
			return new WP_Error( 'twd_sk_not_a_page', 'That page was not found.' );
		}
		$html = TWD_SK_Store::get_current( $page_id );
		if ( '' === $html ) {
			return new WP_Error( 'twd_sk_empty_page', 'This page has nothing stored to export.' );
		}
		$title = trim( (string) get_the_title( $page_id ) );
		$stem  = sanitize_title( $title );
		$stem  = '' !== $stem ? $stem : 'page-' . $page_id;
		$what  = 'the whole page';
		$section = (int) $section;
		if ( $section > 0 ) {
			$parts = TWD_SK_Sections::split( $html );
			if ( ! isset( $parts['sections'][ $section - 1 ] ) ) {
				return new WP_Error( 'twd_sk_bad_input', 'This page has ' . count( $parts['sections'] ) . ' section' . ( 1 === count( $parts['sections'] ) ? '' : 's' ) . ', so there is no section ' . $section . '.' );
			}
			$html  = $parts['sections'][ $section - 1 ];
			$label = TWD_SK_Sections::label_of( $html );
			$what  = 'section ' . $section . ( '' !== $label ? ' (' . $label . ')' : '' );
			$stem .= '-section-' . $section;
		}
		return array( 'html' => $html, 'title' => $title, 'stem' => $stem, 'what' => $what, 'page_id' => $page_id );
	}

	// -- The standalone file -------------------------------------------------------------

	/**
	 * @return array|WP_Error { name, content, pictures, bytes }
	 */
	public static function standalone( $page_id, $section = 0 ) {
		$src = self::source( $page_id, $section );
		if ( is_wp_error( $src ) ) {
			return $src;
		}
		$abs    = self::absolute( self::shortcodes_out( $src['html'] ) );
		$tokens = TWD_SK_Packs::active_tokens();
		$kit    = self::kit_rules_for( $abs['html'], self::kit_css() );
		$site   = class_exists( 'TWD_SK_Css' ) ? TWD_SK_Css::current()['built'] : '';
		$fonts  = TWD_SK_Packs::font_face_css( $tokens, TWD_SK_URL . 'assets/fonts/' );
		$css    = self::resolve_tokens( $fonts . $kit . $site, $tokens );
		$bg     = isset( $tokens['color-bg'] ) ? $tokens['color-bg'] : '#ffffff';
		$name   = function_exists( 'get_bloginfo' ) ? (string) get_bloginfo( 'name' ) : '';
		$lang   = function_exists( 'get_bloginfo' ) ? (string) get_bloginfo( 'language' ) : '';
		$out    = "<!doctype html>\n<html lang=\"" . esc_attr( '' !== $lang ? $lang : 'en' ) . "\">\n<head>\n<meta charset=\"utf-8\">\n<meta name=\"viewport\" content=\"width=device-width, initial-scale=1\">\n"
			. '<title>' . esc_html( '' !== $src['title'] ? $src['title'] : 'Exported page' ) . "</title>\n"
			. "<!-- Exported from " . esc_html( $name ) . ' by TWD Site Kit on ' . gmdate( 'Y-m-d' ) . ': ' . esc_html( $src['what'] ) . ". Opens on its own; the fonts and pictures are loaded from the site. -->\n"
			. "<style>\nbody{margin:0;background:" . $bg . ";}\n" . $css . "\n</style>\n</head>\n<body>\n<div class=\"twd-sk-page\">\n" . $abs['html'] . "\n</div>\n</body>\n</html>\n";
		return array(
			'name'     => $src['stem'] . '-' . gmdate( 'Y-m-d' ) . '.html',
			'content'  => $out,
			'pictures' => $abs['pictures'],
			'bytes'    => strlen( $out ),
		);
	}

	/** The generated kit stylesheet, comments removed. */
	public static function kit_css() {
		$path = ( defined( 'TWD_SK_PATH' ) ? TWD_SK_PATH : ABSPATH ) . 'assets/twd-site-kit.css';
		$css  = is_readable( $path ) ? (string) file_get_contents( $path ) : '';
		return (string) preg_replace( '#/\*.*?\*/#s', '', $css );
	}

	/**
	 * Only the kit rules this HTML can use: a selector is kept when every class it names is on the page (or is the
	 * wrapper); a :not(...) part is never a requirement. @media and @supports blocks keep only their matching rules,
	 * @keyframes blocks stay whole, anything else at the top level goes.
	 */
	public static function kit_rules_for( $html, $css ) {
		$have = array( 'twd-sk-page' => true );
		if ( preg_match_all( '/class\s*=\s*"([^"]*)"/i', $html, $m ) ) {
			foreach ( $m[1] as $list ) {
				foreach ( preg_split( '/\s+/', trim( $list ) ) as $c ) {
					if ( '' !== $c ) {
						$have[ $c ] = true;
					}
				}
			}
		}
		return self::filter_block( $css, $have );
	}

	private static function filter_block( $css, $have ) {
		$out = '';
		$len = strlen( $css );
		$pos = 0;
		while ( $pos < $len ) {
			$open = strpos( $css, '{', $pos );
			if ( false === $open ) {
				break;
			}
			$head  = trim( substr( $css, $pos, $open - $pos ) );
			$close = self::matching_brace( $css, $open );
			if ( $close < 0 ) {
				break;
			}
			$body = substr( $css, $open + 1, $close - $open - 1 );
			$pos  = $close + 1;
			if ( '' === $head ) {
				continue;
			}
			if ( '@' === $head[0] ) {
				if ( 0 === stripos( $head, '@keyframes' ) ) {
					$out .= $head . '{' . $body . '}';
				} elseif ( 0 === stripos( $head, '@media' ) || 0 === stripos( $head, '@supports' ) ) {
					$inner = self::filter_block( $body, $have );
					if ( '' !== $inner ) {
						$out .= $head . '{' . $inner . '}';
					}
				}
				continue;
			}
			$kept = array();
			foreach ( self::split_selectors( $head ) as $sel ) {
				if ( self::selector_fits( $sel, $have ) ) {
					$kept[] = $sel;
				}
			}
			if ( $kept ) {
				$out .= implode( ',', $kept ) . '{' . trim( $body ) . '}';
			}
		}
		return $out;
	}

	private static function matching_brace( $css, $open ) {
		$depth = 0;
		$len   = strlen( $css );
		for ( $i = $open; $i < $len; $i++ ) {
			if ( '{' === $css[ $i ] ) {
				$depth++;
			} elseif ( '}' === $css[ $i ] ) {
				$depth--;
				if ( 0 === $depth ) {
					return $i;
				}
			}
		}
		return -1;
	}

	/** Split a selector list on the commas outside brackets. */
	private static function split_selectors( $list ) {
		$out   = array();
		$depth = 0;
		$cur   = '';
		$len   = strlen( $list );
		for ( $i = 0; $i < $len; $i++ ) {
			$ch = $list[ $i ];
			if ( '(' === $ch || '[' === $ch ) {
				$depth++;
			} elseif ( ')' === $ch || ']' === $ch ) {
				$depth--;
			}
			if ( ',' === $ch && 0 === $depth ) {
				$out[] = trim( $cur );
				$cur   = '';
				continue;
			}
			$cur .= $ch;
		}
		if ( '' !== trim( $cur ) ) {
			$out[] = trim( $cur );
		}
		return $out;
	}

	private static function selector_fits( $sel, $have ) {
		$test = preg_replace( '/:not\((?:[^()]|\([^()]*\))*\)/', '', $sel );
		if ( preg_match_all( '/\.([A-Za-z0-9_-]+)/', $test, $m ) ) {
			foreach ( $m[1] as $class ) {
				if ( ! isset( $have[ $class ] ) ) {
					return false;
				}
			}
		}
		return true;
	}

	/**
	 * Every var(--twd-site-NAME) and var(--twd-site-NAME, fallback) becomes the site's value (fonts as their CSS
	 * stack). A name the site does not know keeps its fallback, or is left as it is.
	 */
	public static function resolve_tokens( $css, $tokens ) {
		$map = array();
		foreach ( $tokens as $name => $value ) {
			if ( ! is_string( $value ) || ! TWD_SK_Packs::valid_token( $name, $value ) ) {
				continue;
			}
			if ( 'font-heading' === $name || 'font-body' === $name || 'menu-font' === $name ) {
				$value = TWD_SK_Packs::font_css_value( $value );
			}
			$map[ '--twd-site-' . $name ] = $value;
		}
		$pattern = '/var\(\s*(--twd-site-[a-z0-9-]+)\s*(?:,\s*((?:[^()]|\((?:[^()]|\([^()]*\))*\))*))?\)/i';
		for ( $pass = 0; $pass < self::MAX_PASSES; $pass++ ) {
			$changed = 0;
			$css     = preg_replace_callback( $pattern, function ( $m ) use ( $map, &$changed ) {
				$name = strtolower( $m[1] );
				if ( isset( $map[ $name ] ) && '' !== $map[ $name ] ) {
					$changed++;
					return $map[ $name ];
				}
				if ( isset( $m[2] ) && '' !== trim( $m[2] ) ) {
					$changed++;
					return trim( $m[2] );
				}
				return $m[0];
			}, $css );
			if ( ! $changed ) {
				break;
			}
		}
		return $css;
	}

	/** Relative picture and link addresses made full, and the list of pictures (address and description). */
	public static function absolute( $html ) {
		$home     = function_exists( 'home_url' ) ? rtrim( (string) home_url( '/' ), '/' ) : '';
		$pictures = array();
		$html     = preg_replace_callback( '/<img\b[^>]*>/i', function ( $m ) use ( $home, &$pictures ) {
			$tag = $m[0];
			if ( preg_match( '/\bsrc\s*=\s*"([^"]*)"/i', $tag, $s ) ) {
				$src = $s[1];
				if ( '/' === substr( $src, 0, 1 ) && '/' !== substr( $src, 1, 1 ) && '' !== $home ) {
					$src = $home . $src;
					$tag = str_replace( $s[0], 'src="' . $src . '"', $tag );
				}
				$alt = preg_match( '/\balt\s*=\s*"([^"]*)"/i', $tag, $a ) ? $a[1] : '';
				$pictures[] = array( 'src' => $src, 'alt' => html_entity_decode( $alt, ENT_QUOTES, 'UTF-8' ) );
			}
			return $tag;
		}, $html );
		$html = preg_replace_callback( '/\bhref\s*=\s*"(\/(?!\/)[^"]*)"/i', function ( $m ) use ( $home ) {
			return '' !== $home ? 'href="' . $home . $m[1] . '"' : $m[0];
		}, $html );
		return array( 'html' => $html, 'pictures' => $pictures );
	}

	/** The registry shortcodes become a plain note where they sit; every other bracket is left to read as text. */
	public static function shortcodes_out( $html ) {
		$notes = array(
			'twd_articles' => 'The list of articles shows here on the site.',
			'twd_enquiry'  => 'The enquiry form shows here on the site.',
			'twd_embed'    => 'An embed (code kept apart from the page) shows here on the site.',
		);
		return (string) preg_replace_callback( '/\[(twd_[a-z_]+)[^\]]*\]/', function ( $m ) use ( $notes ) {
			return isset( $notes[ $m[1] ] ) ? '<p class="twd-sk-export-note"><em>' . $notes[ $m[1] ] . '</em></p>' : $m[0];
		}, $html );
	}

	// -- The remix brief ------------------------------------------------------------------

	/**
	 * @return array|WP_Error { name, content, pictures, bytes }
	 */
	public static function brief( $page_id, $section = 0 ) {
		$src = self::source( $page_id, $section );
		if ( is_wp_error( $src ) ) {
			return $src;
		}
		$abs    = self::absolute( $src['html'] );
		$tokens = TWD_SK_Packs::active_tokens();
		$site   = class_exists( 'TWD_SK_Css' ) ? trim( TWD_SK_Css::current()['source'] ) : '';
		$name   = function_exists( 'get_bloginfo' ) ? (string) get_bloginfo( 'name' ) : '';
		$lines  = array();
		$lines[] = '# Remix brief: ' . ( '' !== $src['title'] ? $src['title'] : 'page ' . $src['page_id'] ) . ', ' . $src['what'];
		$lines[] = '';
		$lines[] = 'Exported from ' . ( '' !== $name ? $name : 'the site' ) . ' by TWD Site Kit on ' . gmdate( 'Y-m-d' ) . '. Give this file to an AI with what you would like changed, or keep it as a saved look. The reply goes back into the site through Replace this section (paste the HTML and the CSS, Preview, Apply), where the same checks run as for anything else.';
		$lines[] = '';
		$lines[] = '## What a reply must contain';
		$lines[] = '';
		$lines[] = '1. The replacement HTML in one fenced block marked `html`: ' . ( $section > 0 ? 'one `<section>` element holding everything.' : 'a list of top-level `<section>` elements, the first holding the page\'s one `<h1>`.' );
		$lines[] = '2. The CSS to add or change in one fenced block marked `css`, or the word none. A rule you repeat replaces the one in the site stylesheet.';
		$lines[] = '3. A line starting "What changed:" with two or three plain sentences a non-technical person understands.';
		$lines[] = '';
		$lines[] = '## Rules';
		$lines[] = '';
		foreach ( self::rules() as $rule ) {
			$lines[] = '- ' . $rule;
		}
		$lines[] = '';
		$lines[] = '## The HTML as it is now';
		$lines[] = '';
		$lines[] = '```html';
		$lines[] = trim( $abs['html'] );
		$lines[] = '```';
		$lines[] = '';
		$lines[] = '## The site stylesheet (rules you may reuse)';
		$lines[] = '';
		$lines[] = '```css';
		$lines[] = '' !== $site ? $site : '/* the site has no rules of its own yet */';
		$lines[] = '```';
		$lines[] = '';
		$lines[] = '## Site tokens (write them as var(--twd-site-NAME), never the value)';
		$lines[] = '';
		$lines[] = 'The style is "' . TWD_SK_Packs::active_slug() . '". Headings use var(--twd-site-font-heading), body text var(--twd-site-font-body).';
		$lines[] = '';
		$lines[] = '| Token | Value now |';
		$lines[] = '| --- | --- |';
		foreach ( TWD_SK_Packs::token_names() as $tname ) {
			if ( isset( $tokens[ $tname ] ) && is_string( $tokens[ $tname ] ) ) {
				$lines[] = '| ' . $tname . ' | ' . str_replace( '|', '\\|', $tokens[ $tname ] ) . ' |';
			}
		}
		$lines[] = '';
		$lines[] = '## Kit classes you may put on elements without styling them';
		$lines[] = '';
		$lines[] = implode( ', ', TWD_SK_Replace::shared_kit_classes() ) . '. Sections usually sit in a div.twd-sk-inner for the page column. Never write a rule for a class that starts with twd-sk-.';
		$lines[] = '';
		$lines[] = '## Pictures';
		$lines[] = '';
		if ( $abs['pictures'] ) {
			foreach ( $abs['pictures'] as $p ) {
				$lines[] = '- ' . $p['src'] . ( '' !== $p['alt'] ? ' (' . $p['alt'] . ')' : ' (no description)' );
			}
		} else {
			$lines[] = 'None.';
		}
		$lines[] = '';
		$lines[] = '## A blank copy';
		$lines[] = '';
		$lines[] = 'The same structure with every word, picture and link a placeholder, for reuse on another site: fill the placeholders, never copy the words above to another site.';
		$lines[] = '';
		$lines[] = '```html';
		$lines[] = trim( self::blank( $src['html'] ) );
		$lines[] = '```';
		$lines[] = '';
		$out = implode( "\n", $lines );
		return array(
			'name'     => $src['stem'] . '-remix-brief-' . gmdate( 'Y-m-d' ) . '.md',
			'content'  => $out,
			'pictures' => $abs['pictures'],
			'bytes'    => strlen( $out ),
		);
	}

	/** The rules a reply must follow: the same ones Describe a change gives the AI, as plain sentences. */
	public static function rules() {
		return array(
			'Change only what is asked for. Keep every other word, link and picture address exactly as it is. Never invent a fact, name, price, qualification or claim; where a fact is missing write [PLACEHOLDER: what is needed] as plain text.',
			'Classes: first reuse the classes the site stylesheet already defines and the kit classes listed below. A new class is named c-something (lower case letters, digits and hyphens). Never style a class that starts with twd-sk-.',
			'Every selector names a c- class. Every colour is var(--twd-site-color-NAME) and every font var(--twd-site-font-heading) or var(--twd-site-font-body); font sizes are a site size token or rem. Never a typed colour (hex, rgb, a colour name), a px font size, !important, @import, @font-face, url() to anything but the site\'s media library, position fixed or sticky, an id selector, or anything but plain rules and @media (min-width/max-width) blocks.',
			'Label what the client may edit later: data-twd-edit="Plain label" on every heading, paragraph, list item and caption, and data-twd-pic="Plain label" on every picture. A labelled element holds only words and simple inline markup (strong, em, a, br).',
			'One h1 per page, never more; heading levels in order, none skipped.',
			'No scripts, iframes, forms, style attributes, inline event handlers or data attributes other than the two labels. Every picture has alt text. Plain hyphens, never long dashes.',
			'The result must look finished: nothing left over from the old layout.',
		);
	}

	/**
	 * The structure with every word a placeholder: headings, paragraphs, list items, link words, captions and quotes
	 * are named for what they are; picture addresses become the sample address and descriptions a placeholder; links
	 * go to #. Kit shortcodes and [PLACEHOLDER] markers already there are kept. Labels (data-twd-edit, data-twd-pic)
	 * and classes stay, because they are the structure.
	 */
	public static function blank( $html ) {
		$parts = preg_split( '/(<[^>]+>)/', (string) $html, -1, PREG_SPLIT_DELIM_CAPTURE | PREG_SPLIT_NO_EMPTY );
		$stack = array();
		$out   = '';
		$names = array( 'h1' => 'heading', 'h2' => 'heading', 'h3' => 'heading', 'h4' => 'heading', 'h5' => 'heading', 'h6' => 'heading', 'a' => 'link words', 'li' => 'list item', 'figcaption' => 'caption', 'blockquote' => 'quote', 'q' => 'quote', 'cite' => 'who said it', 'p' => 'paragraph', 'summary' => 'question', 'dt' => 'term', 'dd' => 'detail', 'th' => 'heading', 'td' => 'cell', 'label' => 'label', 'small' => 'small print', 'em' => 'words', 'strong' => 'words', 'span' => 'words' );
		foreach ( $parts as $part ) {
			if ( '<' === $part[0] ) {
				if ( preg_match( '#^<(/?)([a-zA-Z0-9]+)#', $part, $m ) ) {
					$tag = strtolower( $m[2] );
					if ( '/' === $m[1] ) {
						// Close down to the tag.
						while ( $stack ) {
							$top = array_pop( $stack );
							if ( $top === $tag ) {
								break;
							}
						}
					} elseif ( 'img' === $tag ) {
						$part = preg_replace( '/\bsrc\s*=\s*"[^"]*"/i', 'src="/wp-content/uploads/your-image.jpg"', $part );
						$part = preg_replace( '/\balt\s*=\s*"[^"]*"/i', 'alt="[PLACEHOLDER: picture description]"', $part );
					} elseif ( ! in_array( $tag, array( 'br', 'hr', 'img', 'input', 'source', 'wbr' ), true ) && '/' !== substr( rtrim( $part, '>' ), -1 ) ) {
						if ( 'a' === $tag ) {
							$part = preg_replace_callback( '/\bhref\s*=\s*"([^"]*)"/i', function ( $h ) {
								return '#' === substr( $h[1], 0, 1 ) ? $h[0] : 'href="#"';
							}, $part );
							$part = preg_replace( '/\s+title\s*=\s*"[^"]*"/i', '', $part );
						}
						$stack[] = $tag;
					}
				}
				$out .= $part;
				continue;
			}
			if ( '' === trim( $part ) ) {
				$out .= $part;
				continue;
			}
			// A kit shortcode or a marker already there stays.
			if ( preg_match( '/^\s*\[(twd_[a-z_]+[^\]]*|PLACEHOLDER[^\]]*)\]\s*$/', $part ) ) {
				$out .= $part;
				continue;
			}
			$what = 'words';
			for ( $i = count( $stack ) - 1; $i >= 0; $i-- ) {
				if ( isset( $names[ $stack[ $i ] ] ) ) {
					$what = $names[ $stack[ $i ] ];
					break;
				}
			}
			$lead  = preg_match( '/^\s+/', $part, $l ) ? $l[0] : '';
			$trail = preg_match( '/\s+$/', $part, $t ) ? $t[0] : '';
			$out  .= $lead . '[PLACEHOLDER: ' . $what . ']' . $trail;
		}
		return $out;
	}
}
