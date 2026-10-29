<?php
// Export (session 7, 29 Oct 2026, section 21c): a page or one section as a standalone HTML file (kit rules the page
// needs, the site stylesheet, fonts from the site, every token resolved, pictures as full addresses, no scripts) or as
// a remix brief (markdown for an AI, with a blank copy). Administrators only; nothing is written to the site.

function twd_sk_export_page() {
	twd_sk_rest_setup( array( 'edit_pages', 'edit_post:12', 'manage_options' ) );
	$html = '<section class="twd-sk-hero twd-sk-hero--split twd-sk-tone-tint"><div class="twd-sk-inner"><h1 class="twd-sk-hero__title">Weekend van hire</h1><p class="twd-sk-lead">Vans by the day.</p><a class="twd-sk-btn twd-sk-btn--primary" href="/contact/">Ask about a van</a></div></section>'
		. '<section class="twd-sk-image-text"><div class="twd-sk-inner"><img class="twd-sk-image-text__img" src="/wp-content/uploads/2026/10/van-at-dawn.jpg" alt="A white van at dawn"><h2>Clean and ready</h2><p>Every van is checked before it goes out.</p></div></section>'
		. '<section class="twd-sk-resources"><div class="twd-sk-inner"><h2>Reading</h2>[twd_articles count="3"]</div></section>';
	TWD_SK_Store::save( 12, $html );
	return $html;
}

twd_sk_test( 'export: the standalone file holds only the kit rules the page uses, the site stylesheet, fonts from the site, every token resolved, no script', function () {
	twd_sk_export_page();
	TWD_SK_Css::save( '.c-try { padding: var(--twd-site-section-y) 0; color: var(--twd-site-color-body); }', 'test', 12 );
	$out = TWD_SK_Export::standalone( 12 );
	twd_sk_true( is_array( $out ), is_wp_error( $out ) ? $out->get_error_message() : '' );
	$html = $out['content'];
	twd_sk_has( '<!doctype html>', $html );
	twd_sk_has( '<title>', $html );
	twd_sk_has( '<div class="twd-sk-page">', $html );
	twd_sk_has( '.twd-sk-page .twd-sk-hero.twd-sk-hero', $html, 'the hero rules are in' );
	twd_sk_hasnt( '.twd-sk-pricing', $html, 'a section the page does not use is left out' );
	twd_sk_hasnt( 'twd-ap-article-card', $html, 'the articles plugin rules are left out' );
	twd_sk_hasnt( 'var(--twd-site-', $html, 'every token is resolved' );
	twd_sk_hasnt( '<script', $html );
	twd_sk_has( '@font-face', $html, 'the pack uses bundled fonts' );
	twd_sk_has( 'https://example.test/wp-content/plugins/twd-site-kit/assets/fonts/', $html, 'loaded from the site' );
	twd_sk_has( '.twd-sk-page .c-try', $html, 'the site stylesheet is in' );
	twd_sk_hasnt( '.c-try { padding: var(', $html );
	twd_sk_has( 'src="https://example.test/wp-content/uploads/2026/10/van-at-dawn.jpg"', $html, 'pictures are full addresses' );
	twd_sk_has( 'href="https://example.test/contact/"', $html, 'links too' );
	twd_sk_has( 'The list of articles shows here on the site.', $html, 'a shortcode is a plain note' );
	twd_sk_hasnt( '[twd_articles', $html );
	twd_sk_eq( array( array( 'src' => 'https://example.test/wp-content/uploads/2026/10/van-at-dawn.jpg', 'alt' => 'A white van at dawn' ) ), $out['pictures'] );
	twd_sk_true( 0 === strpos( $out['name'], 'page-12-' ) || false !== strpos( $out['name'], '.html' ), $out['name'] );
	twd_sk_eq( strlen( $html ), $out['bytes'] );
	// The kit rules cover @media blocks for the page's classes and leave other blocks out.
	twd_sk_true( false === strpos( $html, '@media (max-width: 768px){}' ), 'no empty media block' );
} );

twd_sk_test( 'export: one section by its number, and plain errors for a missing page, an empty page or a section that is not there', function () {
	twd_sk_export_page();
	$out = TWD_SK_Export::standalone( 12, 2 );
	twd_sk_has( 'Clean and ready', $out['content'] );
	twd_sk_hasnt( 'Weekend van hire', $out['content'], 'only that section' );
	twd_sk_hasnt( '.twd-sk-hero.twd-sk-hero', $out['content'], 'and only its rules' );
	twd_sk_has( '-section-2-', $out['name'] );
	twd_sk_is_error( 'twd_sk_bad_input', TWD_SK_Export::standalone( 12, 9 ) );
	twd_sk_has( 'has 3 sections', TWD_SK_Export::standalone( 12, 9 )->get_error_message() );
	twd_sk_is_error( 'twd_sk_not_a_page', TWD_SK_Export::standalone( 999 ) );
	twd_stub_add_post( 13, 'page', '' );
	twd_sk_is_error( 'twd_sk_empty_page', TWD_SK_Export::standalone( 13 ) );
} );

twd_sk_test( 'export: resolving tokens handles fallbacks, nested fallbacks, fonts as stacks and unknown names', function () {
	$tokens = TWD_SK_Packs::active_tokens();
	$css    = 'a{color:var(--twd-site-color-body);font-family:var(--twd-site-font-heading);margin:var(--twd-site-nope, 4px);padding:var(--twd-site-nope2, var(--twd-site-section-y));gap:var(--twd-site-nothing)}';
	$out    = TWD_SK_Export::resolve_tokens( $css, $tokens );
	twd_sk_has( 'color:' . $tokens['color-body'], $out );
	twd_sk_has( 'font-family:"' . $tokens['font-heading'] . '"', $out );
	twd_sk_has( 'margin:4px', $out, 'an unknown name keeps its fallback' );
	twd_sk_has( 'padding:' . $tokens['section-y'], $out, 'a nested fallback resolves' );
	twd_sk_has( 'gap:var(--twd-site-nothing)', $out, 'an unknown name with no fallback is left as it is' );
} );

twd_sk_test( 'export: the kit rule filter keeps a selector when its classes are on the page, drops the rest, ignores :not() and keeps keyframes', function () {
	$css  = '.twd-sk-page .twd-sk-a.twd-sk-a{x:1}.twd-sk-page .twd-sk-b.twd-sk-b{x:2}.twd-sk-page .twd-sk-a.twd-sk-a:not(.twd-sk-zz){x:3}.twd-sk-page img{x:4}.twd-sk-page .twd-sk-a.twd-sk-a,.twd-sk-page .twd-sk-b.twd-sk-b{x:5}@media (max-width: 768px){.twd-sk-page .twd-sk-b.twd-sk-b{x:6}}@media (min-width: 769px){.twd-sk-page .twd-sk-a.twd-sk-a{x:7}}@keyframes twd-sk-glow{from{x:8}to{x:9}}';
	$html = '<section class="twd-sk-a"><p>Hi</p></section>';
	$out  = TWD_SK_Export::kit_rules_for( $html, $css );
	twd_sk_eq( '.twd-sk-page .twd-sk-a.twd-sk-a{x:1}.twd-sk-page .twd-sk-a.twd-sk-a:not(.twd-sk-zz){x:3}.twd-sk-page img{x:4}.twd-sk-page .twd-sk-a.twd-sk-a{x:5}@media (min-width: 769px){.twd-sk-page .twd-sk-a.twd-sk-a{x:7}}@keyframes twd-sk-glow{from{x:8}to{x:9}}', $out );
} );

twd_sk_test( 'export: the remix brief holds the HTML, the stylesheet source, the tokens with values, the kit classes, the rules, the reply format, the pictures and a blank copy with none of the words', function () {
	twd_sk_export_page();
	TWD_SK_Css::save( '.c-try { color: var(--twd-site-color-body); }', 'test', 12 );
	$out = TWD_SK_Export::brief( 12 );
	twd_sk_true( is_array( $out ), is_wp_error( $out ) ? $out->get_error_message() : '' );
	$md = $out['content'];
	foreach ( array( '# Remix brief:', '## What a reply must contain', '## Rules', '## The HTML as it is now', '## The site stylesheet', '## Site tokens', '## Kit classes', '## Pictures', '## A blank copy', '```html', '```css', '"What changed:"', 'data-twd-edit', 'data-twd-pic', 'starts with the prefix c-', '| color-body | ' . TWD_SK_Packs::active_tokens()['color-body'] . ' |', ".c-try {\n\tcolor: var(--twd-site-color-body);\n}", 'twd-sk-inner, twd-sk-inner--narrow', 'https://example.test/wp-content/uploads/2026/10/van-at-dawn.jpg (A white van at dawn)', 'Replace this section' ) as $needle ) {
		twd_sk_has( $needle, $md );
	}
	twd_sk_has( '.md', $out['name'] );
	twd_sk_has( 'remix-brief', $out['name'] );
	// The blank copy: the same tags, none of the words, pictures and links as placeholders, the shortcode kept.
	$blank = substr( $md, strpos( $md, '## A blank copy' ) );
	foreach ( array( 'Weekend van hire', 'Vans by the day', 'Ask about a van', 'Clean and ready', 'checked before', 'van-at-dawn', 'A white van at dawn', '/contact/' ) as $word ) {
		twd_sk_hasnt( $word, $blank, $word );
	}
	twd_sk_has( '<h1 class="twd-sk-hero__title">[PLACEHOLDER: heading]</h1>', $blank );
	twd_sk_has( '<p class="twd-sk-lead">[PLACEHOLDER: paragraph]</p>', $blank );
	twd_sk_has( 'href="#">[PLACEHOLDER: link words]</a>', $blank );
	twd_sk_has( 'src="/wp-content/uploads/your-image.jpg" alt="[PLACEHOLDER: picture description]"', $blank );
	twd_sk_has( '[twd_articles count="3"]', $blank, 'the shortcode is structure' );
	// A section on its own says which one and how a reply is shaped.
	$one = TWD_SK_Export::brief( 12, 1 );
	twd_sk_has( 'section 1 (Weekend van hire)', $one['content'] );
	twd_sk_has( 'one `<section>` element', $one['content'] );
	twd_sk_hasnt( 'Clean and ready', $one['content'] );
	// The blank copy passes the sanitiser as it is, so it can be pasted into Replace this section.
	$clean = TWD_SK_Sanitizer::clean_with_report( TWD_SK_Export::blank( TWD_SK_Store::get_current( 12 ) ) );
	twd_sk_eq( 0, $clean['report']['total'], 'nothing removed' );
} );

twd_sk_test( 'export: a blank copy keeps markers and labels, names list items, captions and quotes, and leaves nested marks in place', function () {
	$html  = '<section class="twd-sk-custom c-box"><h2 data-twd-edit="Heading">Real <em>words</em> here</h2><ul class="twd-sk-list"><li>One thing</li><li>Two things</li></ul><figure><img src="/a.jpg" alt="x" data-twd-pic="Photo"><figcaption>A caption</figcaption></figure><blockquote><p>Said this</p><cite>Someone</cite></blockquote><p>[PLACEHOLDER: a price]</p><a href="#twd-sk-notice">Jump</a></section>';
	$blank = TWD_SK_Export::blank( $html );
	twd_sk_has( '<h2 data-twd-edit="Heading">[PLACEHOLDER: heading] <em>[PLACEHOLDER: words]</em> [PLACEHOLDER: heading]</h2>', $blank, 'spaces kept, a mark inside is just words' );
	twd_sk_has( '<li>[PLACEHOLDER: list item]</li><li>[PLACEHOLDER: list item]</li>', $blank );
	twd_sk_has( '<figcaption>[PLACEHOLDER: caption]</figcaption>', $blank );
	twd_sk_has( '<blockquote><p>[PLACEHOLDER: paragraph]</p><cite>[PLACEHOLDER: who said it]</cite></blockquote>', $blank );
	twd_sk_has( '<p>[PLACEHOLDER: a price]</p>', $blank, 'a marker already there stays' );
	twd_sk_has( '<a href="#twd-sk-notice">[PLACEHOLDER: link words]</a>', $blank, 'an anchor link keeps its target' );
	twd_sk_has( 'data-twd-pic="Photo"', $blank );
	twd_sk_has( 'class="twd-sk-custom c-box"', $blank );
} );

twd_sk_test( 'export: the route is administrators only, reads only, refuses a bad format, and the command line prints or writes the file', function () {
	twd_sk_export_page();
	$before = TWD_SK_Store::get_current_version_id( 12 );
	twd_sk_eq( true, TWD_SK_REST::can_export_page( twd_sk_rest_req() ) );
	$out = TWD_SK_REST::get_export( twd_sk_rest_req( array( 'format' => 'brief', 'section' => 1 ) ) );
	twd_sk_true( is_array( $out ) && isset( $out['content'], $out['name'], $out['pictures'], $out['bytes'] ) );
	twd_sk_has( '# Remix brief:', $out['content'] );
	$html = TWD_SK_REST::get_export( twd_sk_rest_req( array() ) );
	twd_sk_has( '<!doctype html>', $html['content'], 'html is the default' );
	twd_sk_eq( 400, twd_sk_status( TWD_SK_REST::get_export( twd_sk_rest_req( array( 'format' => 'pdf' ) ) ) ) );
	twd_sk_eq( 400, twd_sk_status( TWD_SK_REST::get_export( twd_sk_rest_req( array( 'section' => 7 ) ) ) ) );
	twd_sk_eq( $before, TWD_SK_Store::get_current_version_id( 12 ), 'nothing written' );
	twd_sk_rest_setup( array( 'edit_pages', 'edit_post:12' ) );
	$e = TWD_SK_REST::can_export_page( twd_sk_rest_req() );
	twd_sk_is_error( 'twd_sk_forbidden', $e );
	twd_sk_has( 'administrators', $e->get_error_message() );
	// Not registered in safe mode.
	twd_sk_rest_setup( array( 'edit_pages', 'edit_post:12', 'manage_options' ) );
	$routes = array_map( function ( $r ) {
		return $r['route'];
	}, twd_sk_rest_routes() );
	twd_sk_true( in_array( '/pages/(?P<id>\d+)/export', $routes, true ), 'registered: ' . implode( ' ', $routes ) );
	TWD_SK_Safe::set( true );
	twd_sk_true( ! in_array( '/pages/(?P<id>\d+)/export', array_map( function ( $r ) {
		return $r['route'];
	}, twd_sk_rest_routes() ), true ), 'not in safe mode' );
	TWD_SK_Safe::set( false );

	// The command line.
	twd_sk_export_page();
	$cli = new TWD_SK_CLI();
	WP_CLI::reset();
	$cli->export( array( '12' ), array( 'format' => 'brief', 'section' => '2' ) );
	twd_sk_has( '# Remix brief:', WP_CLI::all() );
	twd_sk_has( 'Clean and ready', WP_CLI::all() );
	$file = tempnam( sys_get_temp_dir(), 'twdsk' );
	WP_CLI::reset();
	$cli->export( array( '12' ), array( 'out' => $file ) );
	twd_sk_has( 'success: Wrote ' . $file, WP_CLI::all() );
	twd_sk_has( '<!doctype html>', file_get_contents( $file ) );
	unlink( $file );
	twd_sk_cli_fails( function () use ( $cli ) {
		$cli->export( array( '12' ), array( 'format' => 'pdf' ) );
	} );
	twd_sk_cli_fails( function () use ( $cli ) {
		$cli->export( array( '12' ), array( 'section' => '9' ) );
	} );
	twd_sk_has( 'wp twd-sk export <page_id>', file_get_contents( ABSPATH . 'includes/class-twd-sk-cli.php' ), 'listed in the header comment' );
} );
