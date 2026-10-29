<?php
// [twd_page] shortcode tests.

twd_sk_test( 'page: init registers the twd_page shortcode and nothing else as a shortcode', function () {
	TWD_SK_Page::init();
	twd_sk_eq( array( 'twd_page' ), array_keys( $GLOBALS['twd_stub']['shortcodes'] ) );
} );

twd_sk_test( 'page: init hooks the Hello title filter and the body class filter', function () {
	TWD_SK_Page::init();
	twd_sk_true( isset( $GLOBALS['twd_stub']['hooks']['hello_elementor_page_title'] ) );
	twd_sk_true( isset( $GLOBALS['twd_stub']['hooks']['body_class'] ) );
} );

twd_sk_test( 'title: the theme page title is hidden when the stored page has a hero h1', function () {
	twd_stub_add_post( 13, 'page' );
	TWD_SK_Store::save( 13, TWD_SK_Registry::get( 'hero' )['skeleton'] );
	$GLOBALS['twd_stub']['queried'] = 13;
	twd_sk_eq( false, TWD_SK_Page::filter_theme_title( true ) );
} );

twd_sk_test( 'title: the theme page title stays when the stored page has no h1', function () {
	twd_stub_add_post( 13, 'page' );
	TWD_SK_Store::save( 13, TWD_SK_Registry::get( 'text' )['skeleton'] );
	$GLOBALS['twd_stub']['queried'] = 13;
	twd_sk_eq( true, TWD_SK_Page::filter_theme_title( true ) );
} );

twd_sk_test( 'title: the theme page title stays on a page with nothing stored', function () {
	twd_stub_add_post( 13, 'page' );
	$GLOBALS['twd_stub']['queried'] = 13;
	twd_sk_eq( true, TWD_SK_Page::filter_theme_title( true ) );
} );

twd_sk_test( 'title: an already hidden title stays hidden, and an h1 that was demoted does not hide the theme title', function () {
	twd_stub_add_post( 13, 'page' );
	TWD_SK_Store::save( 13, '<section class="twd-sk-text"><h1 class="twd-sk-hero__title">Stray</h1></section>' );
	$GLOBALS['twd_stub']['queried'] = 13;
	twd_sk_eq( true, TWD_SK_Page::filter_theme_title( true ), 'demoted h1 means no h1 stored, so the theme title stays' );
	twd_sk_eq( false, TWD_SK_Page::filter_theme_title( false ) );
} );

twd_sk_test( 'title: archives and search results are never touched, even if their queried id matches a page', function () {
	twd_stub_add_post( 13, 'page' );
	TWD_SK_Store::save( 13, TWD_SK_Registry::get( 'hero' )['skeleton'] );
	$GLOBALS['twd_stub']['queried']  = 13;
	$GLOBALS['twd_stub']['singular'] = false;
	twd_sk_eq( true, TWD_SK_Page::filter_theme_title( true ) );
	twd_sk_eq( array( 'a' ), TWD_SK_Page::filter_body_class( array( 'a' ) ) );
} );

twd_sk_test( 'title: the body class twd-sk-has-h1 is added only on a page with a hero h1', function () {
	twd_stub_add_post( 13, 'page' );
	twd_stub_add_post( 14, 'page' );
	TWD_SK_Store::save( 13, TWD_SK_Registry::get( 'hero' )['skeleton'] );
	TWD_SK_Store::save( 14, TWD_SK_Registry::get( 'text' )['skeleton'] );
	$GLOBALS['twd_stub']['queried'] = 13;
	twd_sk_eq( array( 'x', 'twd-sk-has-h1' ), TWD_SK_Page::filter_body_class( array( 'x' ) ) );
	$GLOBALS['twd_stub']['queried'] = 14;
	twd_sk_eq( array( 'x' ), TWD_SK_Page::filter_body_class( array( 'x' ) ) );
	$GLOBALS['twd_stub']['queried'] = 0;
	twd_sk_eq( array( 'x' ), TWD_SK_Page::filter_body_class( array( 'x' ) ) );
} );

twd_sk_test( 'title: page_has_h1 reads only the stored HTML and ignores h1 inside other words', function () {
	twd_stub_add_post( 13, 'page' );
	update_post_meta( 13, '_twd_sk_html', wp_slash( '<p>an h1 is mentioned in this text, and <h10>this</h10></p>' ) );
	twd_sk_eq( false, TWD_SK_Page::page_has_h1( 13 ) );
	twd_sk_eq( false, TWD_SK_Page::page_has_h1( 0 ) );
} );

twd_sk_test( 'page: renders the current page stored html inside one wrapper class', function () {
	twd_stub_add_post( 10, 'page' );
	TWD_SK_Store::save( 10, '<p>Hello</p>' );
	$GLOBALS['twd_stub']['post_id'] = 10;
	twd_sk_eq( '<div class="twd-sk-page"><p>Hello</p></div>', TWD_SK_Page::render() );
} );

twd_sk_test( 'page: needs no id, it uses whichever page it sits on', function () {
	twd_stub_add_post( 10, 'page' );
	twd_stub_add_post( 11, 'page' );
	TWD_SK_Store::save( 10, '<p>Ten</p>' );
	TWD_SK_Store::save( 11, '<p>Eleven</p>' );
	$GLOBALS['twd_stub']['post_id'] = 11;
	twd_sk_has( 'Eleven', TWD_SK_Page::render() );
	twd_sk_hasnt( 'Ten', TWD_SK_Page::render() );
} );

twd_sk_test( 'page: ignores any attributes given to the shortcode', function () {
	twd_stub_add_post( 10, 'page' );
	twd_stub_add_post( 99, 'page' );
	TWD_SK_Store::save( 10, '<p>Ten</p>' );
	TWD_SK_Store::save( 99, '<p>Other</p>' );
	$GLOBALS['twd_stub']['post_id'] = 10;
	twd_sk_hasnt( 'Other', TWD_SK_Page::render( array( 'id' => 99 ) ) );
} );

twd_sk_test( 'page: renders nothing when nothing is stored or there is no current page', function () {
	twd_stub_add_post( 10, 'page' );
	$GLOBALS['twd_stub']['post_id'] = 10;
	twd_sk_eq( '', TWD_SK_Page::render() );
	$GLOBALS['twd_stub']['post_id'] = 0;
	twd_sk_eq( '', TWD_SK_Page::render() );
} );

twd_sk_test( 'page: the wrapper class appears exactly once', function () {
	twd_stub_add_post( 10, 'page' );
	TWD_SK_Store::save( 10, '<section class="twd-sk-text"><p>a</p></section><section class="twd-sk-faq"><p>b</p></section>' );
	$GLOBALS['twd_stub']['post_id'] = 10;
	twd_sk_eq( 1, substr_count( TWD_SK_Page::render(), 'twd-sk-page' ) );
} );

twd_sk_test( 'page: sets the rendered flag only after it has really rendered', function () {
	twd_stub_add_post( 10, 'page' );
	TWD_SK_Store::save( 10, '<p>x</p>' );
	$GLOBALS['twd_stub']['post_id'] = 10;
	TWD_SK_Page::render();
	twd_sk_true( TWD_SK_Page::rendered_on_this_page() );
} );

twd_sk_test( 'page: an allowed shortcode is run', function () {
	$out = TWD_SK_Page::render_html( '<div>[twd_articles count="6" columns="3"]</div>' );
	twd_sk_eq( '<div><RUN:twd_articles count="6" columns="3"></div>', $out );
} );

twd_sk_test( 'page: a shortcode not in the registry is NOT run, only shown as text', function () {
	$out = TWD_SK_Page::render_html( '<p>[gallery ids="1"] [contact-form-7 id="2"]</p>' );
	twd_sk_hasnt( '<RUN:', $out );
	twd_sk_has( '&#91;gallery ids="1"&#93;', $out );
} );

twd_sk_test( 'page: [twd_page] inside stored html cannot recurse', function () {
	$out = TWD_SK_Page::render_html( '<p>[twd_page]</p>' );
	twd_sk_hasnt( '<RUN:', $out );
} );

twd_sk_test( 'page: shortcodes hidden in attributes are not run', function () {
	$out = TWD_SK_Page::render_html( '<a href="/x?y=[gallery]" title="[twd_page]">x</a>' );
	twd_sk_hasnt( '<RUN:', $out );
} );

twd_sk_test( 'page: hand-edited meta with a bad shortcode is still neutralised at render time', function () {
	twd_stub_add_post( 10, 'page' );
	update_post_meta( 10, '_twd_sk_html', wp_slash( '<p>[evil cmd="x"] and [twd_articles count="3"]</p>' ) );
	$GLOBALS['twd_stub']['post_id'] = 10;
	$out = TWD_SK_Page::render();
	twd_sk_hasnt( '<RUN:evil', $out );
	twd_sk_has( '<RUN:twd_articles count="3">', $out );
} );

twd_sk_test( 'page: html with no shortcodes comes back untouched', function () {
	$in = '<p class="twd-sk-text__body">Plain text</p>';
	twd_sk_eq( $in, TWD_SK_Page::render_html( $in ) );
} );

twd_sk_test( 'Tier C ideas 6 and 8 (0.15.0): an On this page list built from the h2 headings when the page is drawn (never stored), and reading controls as a tiny script loaded only where asked', function () {
	// The list: ids on every h2, the list inside the section that asks for it, three links at least, nothing stored.
	// A section titled with the page's own title ("Essay" again, in any case) is left out of the list (29 Oct 2026).
	$page = '<section class="twd-sk-hero twd-sk-hero--compact twd-sk-toc twd-sk-tone-base"><div class="twd-sk-inner"><h1 class="twd-sk-hero__title">Essay</h1></div></section>'
		. "\n" . '<section class="twd-sk-text twd-sk-tone-base"><div class="twd-sk-inner"><h2 class="twd-sk-title">First &amp; foremost</h2><div class="twd-sk-text__body"><p>A.</p><h2 class="twd-sk-text__subheading">Second</h2><p>B.</p></div></div></section>'
		. "\n" . '<section class="twd-sk-faq twd-sk-tone-base"><div class="twd-sk-inner"><h2 class="twd-sk-title"> ESSAY </h2><p>Q.</p></div></section>'
		. "\n" . '<section class="twd-sk-text twd-sk-tone-surface"><div class="twd-sk-inner"><h2 class="twd-sk-title">Third</h2><p>C.</p></div></section>'
		. "\n" . '<section class="twd-sk-cta twd-sk-cta--strip twd-sk-tone-band"><div class="twd-sk-inner"><p class="twd-sk-cta__text">Go.</p></div></section>';
	$out = TWD_SK_Page::add_toc( $page );
	twd_sk_has( '<h2 class="twd-sk-title twd-sk-toc-target" id="twd-sk-h-1">First &amp; foremost</h2>', $out );
	twd_sk_has( '<h2 class="twd-sk-text__subheading twd-sk-toc-target" id="twd-sk-h-2">Second</h2>', $out );
	twd_sk_has( '<h2 class="twd-sk-title"> ESSAY </h2>', $out, 'the page title repeated as a section title gets no id and no link' );
	twd_sk_has( '<h2 class="twd-sk-title twd-sk-toc-target" id="twd-sk-h-4">Third</h2>', $out );
	twd_sk_has( '<nav class="twd-sk-toc__box" aria-label="On this page"><p class="twd-sk-toc__title">On this page</p><ul class="twd-sk-toc__list"><li><a class="twd-sk-toc__link" href="#twd-sk-h-1">First &amp; foremost</a></li><li><a class="twd-sk-toc__link" href="#twd-sk-h-2">Second</a></li><li><a class="twd-sk-toc__link" href="#twd-sk-h-4">Third</a></li></ul></nav></div></section>', $out );
	twd_sk_true( 0 === strpos( $out, '<section class="twd-sk-hero twd-sk-hero--compact twd-sk-toc twd-sk-tone-base"><div class="twd-sk-inner"><h1 class="twd-sk-hero__title">Essay</h1><nav class="twd-sk-toc__box"' ), 'the list sits inside the section that asked for it' );
	twd_sk_eq( 1, substr_count( $out, 'twd-sk-toc__list' ) );
	twd_sk_hasnt( '>Essay</a>', $out );
	// Fewer than three links: no list (the Questions page showed "Explore Further" and its own title). No section asks: nothing changes.
	$two = str_replace( '<section class="twd-sk-text twd-sk-tone-surface"><div class="twd-sk-inner"><h2 class="twd-sk-title">Third</h2><p>C.</p></div></section>', '', $page );
	twd_sk_hasnt( 'twd-sk-toc__list', TWD_SK_Page::add_toc( $two ), 'two links (and the title again) are not enough' );
	twd_sk_eq( 3, TWD_SK_Page::TOC_MIN );
	twd_sk_hasnt( 'twd-sk-toc__list', TWD_SK_Page::add_toc( '<section class="twd-sk-text twd-sk-toc twd-sk-tone-base"><div class="twd-sk-inner"><h2 class="twd-sk-title">Only</h2></div></section>' ) );
	// The title section cannot be deleted; the Visual editor says so plainly and points at the Look setting for the list.
	$inline = file_get_contents( ABSPATH . 'assets/twd-site-kit-inline.js' );
	twd_sk_has( "'The page title cannot be deleted: every page keeps its main heading.'", $inline );
	twd_sk_has( "sec.classList.contains('twd-sk-toc') ? ' The On this page list here is a Look setting: press Look and switch off \"On this page list here\" to remove it.' : ''", $inline );
	$plain = str_replace( ' twd-sk-toc', '', $page );
	twd_sk_eq( $plain, TWD_SK_Page::add_toc( $plain ) );
	// The sanitiser would not keep the drawn ids and list from a paste either way, but they are never offered to it.
	twd_sk_true( in_array( 'twd-sk-toc', TWD_SK_Registry::shared_classes(), true ) );
	$css = file_get_contents( ABSPATH . 'assets/twd-site-kit.css' );
	foreach ( array( 'twd-sk-toc__link', 'twd-sk-toc-target', 'twd-sk-reading__btn', 'twd-sk-reading__ctl', 'twd-sk-read-xl', 'twd-sk-read-contrast' ) as $cls ) {
		twd_sk_has( $cls, $css );
	}
	// The toggles: On this page on the hero and text sections; reading controls on text sections.
	twd_sk_eq( 'twd-sk-toc', TWD_SK_Looks::options( 'hero' )['toc']['class'] );
	twd_sk_eq( 'twd-sk-toc', TWD_SK_Looks::options( 'text' )['toc']['class'] );
	twd_sk_eq( 'twd-sk-reading', TWD_SK_Looks::options( 'text' )['reading']['class'] );
	// The reading script: small, ASCII, no addresses, builds with DOM calls, keeps the choice in the visitor's browser only, stands aside while editing.
	$js = file_get_contents( ABSPATH . 'assets/twd-site-kit-reading.js' );
	twd_sk_true( strlen( $js ) < 6144, 'small: ' . strlen( $js ) );
	twd_sk_true( 1 !== preg_match( '/[^\x00-\x7F]/', $js ), 'ASCII only' );
	twd_sk_hasnt( 'http', $js );
	twd_sk_hasnt( 'innerHTML', $js );
	twd_sk_hasnt( 'fetch(', $js );
	twd_sk_hasnt( 'document.cookie', $js );
	foreach ( array( 'twd_sk_reading', 'localStorage', 'twd-sk-read-lg', 'twd-sk-read-xl', 'twd-sk-read-loose', 'twd-sk-read-contrast', 'aria-pressed', 'twd-sk-inline-on', "'Escape'" ) as $needle ) {
		twd_sk_has( $needle, $js );
	}
	$page_php = file_get_contents( ABSPATH . 'includes/class-twd-sk-page.php' );
	twd_sk_has( "'twd-sk-reading'", $page_php );
	twd_sk_has( 'twd-site-kit-reading.js', $page_php );
} );
