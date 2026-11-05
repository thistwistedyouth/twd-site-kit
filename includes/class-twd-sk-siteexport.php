<?php
/**
 * TWD_SK_SiteExport: the whole site, one page, or the header and footer, as one download an AI can read.
 *
 * Why: an AI that cannot reach a staging site can still check a site against someone's notes if it is handed the
 * site as files. The export holds the pages (one HTML file each, a comment line before every section giving its
 * number), the header and footer settings, the footer's own HTML when the footer is an embed, the style tokens,
 * the site's own stylesheet, the practice facts, the owner's notes, and a README that tells the AI what each file
 * is, the rules a change must follow, and the one reply format the site can read back (see TWD_SK_SiteImport).
 *
 * It is a zip when the server can make one, and one markdown file otherwise. Administrators only (the REST route
 * and the command line decide). The files hold the client's words and contact details: they are for the builder
 * and the builder's AI, never for this public repository. Nothing here writes anything.
 *
 * @package TWD_Site_Kit
 */

defined( 'ABSPATH' ) || exit;

class TWD_SK_SiteExport {

	const MAX_NOTES = 20000;
	const MAX_PAGES = 300;

	/** The reply format's name, shared with the importer. */
	const REPLY_SCHEMA = 'twd-site-changes/1';

	/** A page's file stem: its address slug, or page-ID. */
	public static function stem( $page_id ) {
		$post = get_post( (int) $page_id );
		$slug = $post && isset( $post->post_name ) ? (string) $post->post_name : '';
		$slug = '' !== $slug ? $slug : sanitize_title( (string) get_the_title( (int) $page_id ) );
		return '' !== $slug ? $slug : 'page-' . (int) $page_id;
	}

	/** The kit pages, for choosing one to export: id, the name a reply uses, title, status. */
	public static function page_list() {
		$out = array();
		foreach ( array_slice( TWD_SK_Launch::kit_pages(), 0, self::MAX_PAGES ) as $id ) {
			$post  = get_post( (int) $id );
			$out[] = array(
				'id'     => (int) $id,
				'page'   => self::stem( $id ),
				'title'  => trim( (string) get_the_title( (int) $id ) ),
				'status' => $post && isset( $post->post_status ) ? (string) $post->post_status : '',
			);
		}
		return $out;
	}

	/** Plain text for the owner's notes: no tags or control characters, line breaks kept, capped. */
	public static function clean_notes( $text ) {
		if ( ! is_string( $text ) ) {
			return '';
		}
		$text = str_replace( array( "\r\n", "\r" ), "\n", wp_strip_all_tags( $text ) );
		$text = preg_replace( '/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F]/', '', $text );
		$text = trim( $text );
		return strlen( $text ) > self::MAX_NOTES ? rtrim( substr( $text, 0, self::MAX_NOTES ) ) : $text;
	}

	/**
	 * The files to export.
	 *
	 * @param array $opts scope ('all', 'page', 'chrome'), page (an id, for 'page'), notes (text)
	 * @return array|WP_Error { files: name => content, pages: [ { id, stem, title, status, sections } ], scope }
	 */
	public static function files( $opts = array() ) {
		$scope = isset( $opts['scope'] ) ? (string) $opts['scope'] : 'all';
		if ( ! in_array( $scope, array( 'all', 'page', 'chrome' ), true ) ) {
			return new WP_Error( 'twd_sk_bad_input', 'Choose the whole site, one page, or the header and footer.' );
		}
		$notes = self::clean_notes( isset( $opts['notes'] ) ? $opts['notes'] : '' );

		$ids = array();
		if ( 'all' === $scope ) {
			$ids = array_slice( TWD_SK_Launch::kit_pages(), 0, self::MAX_PAGES );
		} elseif ( 'page' === $scope ) {
			$id = isset( $opts['page'] ) ? (int) $opts['page'] : 0;
			if ( $id <= 0 || ! TWD_SK_Page::is_kit_page( $id ) ) {
				return new WP_Error( 'twd_sk_not_a_page', 'That page was not found, or it is not a Site Kit page.' );
			}
			$ids = array( $id );
		}

		$files  = array();
		$pages  = array();
		$stems  = array();
		$kit_html = '';
		foreach ( $ids as $id ) {
			$html = TWD_SK_Store::get_current( (int) $id );
			if ( '' === trim( (string) $html ) ) {
				continue;
			}
			$stem = self::stem( $id );
			$base = $stem;
			$n    = 2;
			while ( isset( $stems[ $stem ] ) ) {
				$stem = $base . '-' . $n++;
			}
			$stems[ $stem ] = true;
			$post  = get_post( (int) $id );
			$parts = TWD_SK_Sections::split( $html );
			$body  = '';
			foreach ( $parts['sections'] as $i => $section ) {
				$label = TWD_SK_Sections::label_of( $section );
				$body .= '<!-- twd-section ' . ( $i + 1 ) . ': ' . TWD_SK_Sections::type_of( $section ) . ( '' !== $label ? ', ' . str_replace( '--', '- -', preg_replace( '/[\r\n]+/', ' ', $label ) ) : '' ) . " -->\n" . trim( $section ) . "\n\n";
			}
			$files[ 'pages/' . $stem . '.html' ] = $body;
			$kit_html .= $html;
			$live = $post && isset( $post->post_status ) && 'publish' === $post->post_status;
			$path = $live && function_exists( 'wp_parse_url' ) ? (string) wp_parse_url( get_permalink( (int) $id ), PHP_URL_PATH ) : '';
			$pages[] = array(
				'id'       => (int) $id,
				'page'     => $stem,
				'title'    => trim( (string) get_the_title( (int) $id ) ),
				'address'  => $path,
				'status'   => $post && isset( $post->post_status ) ? (string) $post->post_status : '',
				'file'     => 'pages/' . $stem . '.html',
				'sections' => count( $parts['sections'] ),
			);
		}
		if ( 'chrome' !== $scope ) {
			$files['pages/_index.json'] = self::json( array( 'pages' => $pages ) );
		}

		// The site-wide parts: the header and footer for the whole site or the header and footer scope.
		if ( 'page' !== $scope ) {
			$brief = TWD_SK_Brief::export();
			$files['site/header-and-footer.json'] = self::json( array(
				'site'   => $brief['site'],
				'header' => $brief['header'],
				'footer' => $brief['footer'],
			) );
			$footer = TWD_SK_Chrome::footer_embed();
			if ( $footer ) {
				$files['site/footer.html'] = trim( $footer['code'] ) . "\n";
			}
		}
		// Style and stylesheet go with every scope: a page cannot be judged without them.
		$tokens = TWD_SK_Packs::active_tokens();
		$files['site/style-tokens.json'] = self::json( array(
			'style'  => TWD_SK_Packs::active_slug(),
			'tokens' => TWD_SK_Export::token_groups( $tokens ),
		) );
		if ( $kit_html !== '' ) {
			$kit = trim( TWD_SK_Export::kit_rules_for( $kit_html, TWD_SK_Export::kit_css() ) );
			$files['site/kit-stylesheet-used.css'] = "/* The plugin's own rules for the twd-sk- classes these pages use (every rule is scoped under .twd-sk-page). Colours, fonts and sizes are var(--twd-site-NAME): see style-tokens.json for the values. Read-only: the site stylesheet below is where a change to a custom section's look goes. */\n" . $kit . "\n";
		}
		$css = class_exists( 'TWD_SK_Css' ) ? trim( TWD_SK_Css::current()['source'] ) : '';
		$files['site/site-stylesheet.css'] = '' !== $css ? $css . "\n" : "/* The site has no stylesheet of its own yet. */\n";

		$facts = trim( (string) TWD_SK_Facts::get() );
		$files['notes/practice-facts.md'] = '' !== $facts ? $facts . "\n" : "(Nothing is saved on the site for this.)\n";
		$files['notes/your-notes.md']     = '' !== $notes ? $notes . "\n" : "(No notes were added with this export.)\n";

		$files = array_merge( array( 'README-FOR-AI.md' => self::readme( $scope, $pages, '' !== $notes ) ), $files );
		return array( 'files' => $files, 'pages' => $pages, 'scope' => $scope );
	}

	private static function json( $data ) {
		$flags = defined( 'JSON_PRETTY_PRINT' ) ? JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE : 0;
		return json_encode( $data, $flags ) . "\n";
	}

	/**
	 * What to download: a zip when ZipArchive works, otherwise one markdown file holding every file in turn.
	 *
	 * @return array|WP_Error { name, base64, bytes, kind, files, pages }
	 */
	public static function build( $opts = array() ) {
		$set = self::files( $opts );
		if ( is_wp_error( $set ) ) {
			return $set;
		}
		$stamp = gmdate( 'Y-m-d' );
		$tag   = 'all' === $set['scope'] ? 'site' : ( 'chrome' === $set['scope'] ? 'header-footer' : ( isset( $set['pages'][0] ) ? $set['pages'][0]['page'] : 'page' ) );
		$bytes = self::zip( $set['files'] );
		if ( '' !== $bytes ) {
			return array( 'name' => 'export-' . $tag . '-' . $stamp . '.zip', 'base64' => base64_encode( $bytes ), 'bytes' => strlen( $bytes ), 'kind' => 'zip', 'files' => array_keys( $set['files'] ), 'pages' => count( $set['pages'] ) );
		}
		$md = self::as_markdown( $set['files'] );
		return array( 'name' => 'export-' . $tag . '-' . $stamp . '.md', 'base64' => base64_encode( $md ), 'bytes' => strlen( $md ), 'kind' => 'markdown', 'files' => array_keys( $set['files'] ), 'pages' => count( $set['pages'] ) );
	}

	/** A zip of the files, or '' when this server cannot make one. */
	public static function zip( $files ) {
		if ( ! class_exists( 'ZipArchive' ) ) {
			return '';
		}
		$tmp = tempnam( sys_get_temp_dir(), 'twdsk' );
		if ( false === $tmp ) {
			return '';
		}
		$zip = new ZipArchive();
		if ( true !== $zip->open( $tmp, ZipArchive::OVERWRITE ) ) {
			@unlink( $tmp );
			return '';
		}
		foreach ( $files as $name => $content ) {
			$zip->addFromString( $name, $content );
		}
		$ok = $zip->close();
		$out = $ok ? (string) file_get_contents( $tmp ) : '';
		@unlink( $tmp );
		return $out;
	}

	/** Every file in one markdown document, each under a heading with its name. */
	public static function as_markdown( $files ) {
		$out = "# Site export\n\nOne file standing for a folder: each part below begins with a line `=== FILE: name ===`. Read README-FOR-AI.md first.\n\n";
		foreach ( $files as $name => $content ) {
			$out .= '=== FILE: ' . $name . " ===\n" . $content . ( "\n" === substr( $content, -1 ) ? '' : "\n" ) . "\n";
		}
		return $out;
	}

	/** The README an AI reads first. */
	public static function readme( $scope, $pages, $has_notes ) {
		$name  = function_exists( 'get_bloginfo' ) ? trim( (string) get_bloginfo( 'name' ) ) : '';
		$l     = array();
		$l[]   = '# Read this first';
		$l[]   = '';
		$l[]   = 'This is an export of ' . ( '' !== $name ? $name : 'a website' ) . ' made on ' . gmdate( 'Y-m-d' ) . ' by TWD Site Kit (a WordPress plugin). You cannot see the live site, so these files stand for it. Your job is to read them, compare them with the notes, and reply with changes the site can apply exactly as you write them.';
		$l[]   = '';
		$l[]   = '## The files';
		$l[]   = '';
		$l[]   = '- `notes/your-notes.md`: ' . ( $has_notes ? 'what the owner wants checked or changed. Start here.' : 'empty on this export.' );
		$l[]   = '- `notes/practice-facts.md`: the facts about the person and practice. These are the ONLY facts you may use.';
		if ( 'chrome' === $scope ) {
			$l[] = '- This export holds no pages, only the header and footer.';
		} else {
			$l[] = '- `pages/_index.json`: every page with the name to use in a reply (`page`), its title, address, status and number of sections.';
			$l[] = '- `pages/NAME.html`: one page. A comment line `<!-- twd-section N: type, label -->` comes before each top-level section. N is the section number to use in a reply. Those comment lines are only for you: never put one in a reply.';
		}
		if ( 'page' !== $scope ) {
			$l[] = '- `site/header-and-footer.json`: the site details, menu, header button, footer words and legal links, and the header and footer settings.';
			$l[] = '- `site/footer.html`: the footer\'s own HTML, only when the footer is a saved embed.';
		}
		$l[] = '- `site/style-tokens.json`: the colours, fonts and sizes the design uses, with their values. A stylesheet refers to them as `var(--twd-site-NAME)`.';
		$l[] = '- `site/site-stylesheet.css`: the site\'s own rules, for sections that are custom HTML (classes starting `c-`).';
		$l[] = '- `site/kit-stylesheet-used.css`: the plugin\'s own rules for the kit classes (starting `twd-sk-`) these pages use, so you can read how a kit section looks. Do not write rules for those classes.';
		$l[] = '';
		$l[] = '## What you can and cannot judge';
		$l[] = '';
		$l[] = '- You are reading code, not looking at a rendered page. You can judge wording, structure, headings, links and where they go, labels, alt text, what a control does (tabs, accordions, buttons) and, from the stylesheets and tokens, colours, fonts, sizes, spacing and how a layout will behave on a phone. Say plainly when something can only be judged by looking at it.';
		$l[] = '- The header is not exported as HTML: `site/header-and-footer.json` gives its menu, button and layout settings.';
		$l[] = '- Pages marked `draft` in `pages/_index.json` are not live. Ignore them unless the notes ask about them.';
		$l[] = '- Many pages are ONE large custom section (class `twd-sk-custom`), so a section change on them replaces the whole page. For wording, prefer a find and replace, and keep every `c-` class and every `data-twd-edit` label as it is.';
		$l[] = '';
		$l[] = '## How to answer';
		$l[] = '';
		$l[] = 'Reply with ONE JSON object and nothing else (a fenced json block is fine). Plain sentences for a person go in `what_changed` and in each change\'s `why`.';
		$l[] = '';
		$l[] = '```json';
		$l[] = '{';
		$l[] = '  "schema": "' . self::REPLY_SCHEMA . '",';
		$l[] = '  "what_changed": "Two or three plain sentences.",';
		$l[] = '  "css": "optional: rules to add or change, or leave this out",';
		$l[] = '  "changes": [';
		$l[] = '    { "page": "about", "section": 3, "find": "the exact old words", "replace": "the new words", "why": "short reason" },';
		$l[] = '    { "page": "about", "section": 2, "html": "<section>...the whole new section...</section>", "why": "short reason" },';
		$l[] = '    { "page": "contact", "html": "<section>...every section of the page...</section>", "why": "only when the page is rebuilt" },';
		$l[] = '    { "footer_html": "<footer>...the whole new footer...</footer>", "why": "short reason" }';
		$l[] = '  ]';
		$l[] = '}';
		$l[] = '```';
		$l[] = '';
		$l[] = '- A **find and replace** change is for a wording fix. `find` must appear exactly once inside that section\'s HTML (copy it from the file, including any tags it sits among). Prefer this for small corrections: it cannot damage anything around it.';
		$l[] = '- A **section** change replaces section N with the HTML you give (one or more complete `<section>` elements).';
		$l[] = '- A **page** change (no `section`) replaces the whole page. Use it rarely.';
		$l[] = '- A **footer_html** change replaces the footer, only if `site/footer.html` exists.';
		$l[] = '- `page` is the name from `pages/_index.json`. Section numbers refer to the files as exported. Several changes to one page are fine: they are all applied to the page as exported.';
		$l[] = '- Do not change the site details, menu or settings with this format: write those as a plain list for a person to apply.';
		$l[] = '';
		$l[] = '## Rules every change must follow';
		$l[] = '';
		foreach ( TWD_SK_Export::rules( 'c-page-' ) as $rule ) {
			$l[] = '- ' . $rule;
		}
		$l[] = '- When you change a kit section (its classes start with twd-sk-), keep its structure and classes and change only the words, links and picture descriptions, unless the notes ask for a new layout.';
		$l[] = '- Never use the practice\'s facts for anything but what they say. If the notes ask for something the facts do not support, put `[PLACEHOLDER: what is needed]` in the text and say so in `why`.';
		$l[] = '- The site shows what you write after a person has previewed it and agreed, so be exact. If you are unsure, say so in `what_changed` and leave the change out.';
		$l[] = '';
		$l[] = 'These files hold a client\'s words and contact details. Keep them private.';
		return implode( "\n", $l ) . "\n";
	}
}
