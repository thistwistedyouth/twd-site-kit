<?php
// [twd_page] shortcode tests.

twd_sk_test( 'page: init registers the twd_page shortcode and nothing else', function () {
	TWD_SK_Page::init();
	twd_sk_eq( array( 'twd_page' ), array_keys( $GLOBALS['twd_stub']['shortcodes'] ) );
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
