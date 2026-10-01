<?php
// The plain-text mirror of a kit page, for search and SEO tools.

function twd_sk_mirror_page( $id, $content = '', $template = false ) {
	twd_stub_add_post( $id, 'page', $content );
	if ( $template ) {
		update_post_meta( $id, '_wp_page_template', 'twd-site-kit-page.php' );
	}
	TWD_SK_Mirror::init();
}

twd_sk_test( 'mirror: text keeps headings, paragraphs and list items on their own lines and drops all markup and shortcodes', function () {
	$html = '<section class="twd-sk-text"><h2 class="twd-sk-title">How I work</h2><p>Gentle, practical  therapy &amp; support.</p><ul><li>Anxiety</li><li>Grief</li></ul><p>Line one<br>line two</p>[twd_articles count="3" columns="3"]</section><img src="/u/a.jpg" alt="A calm room"><div hidden>x</div><script>bad()</script>';
	$text = TWD_SK_Mirror::text( $html );
	twd_sk_eq( "How I work\nGentle, practical therapy & support.\nAnxiety\nGrief\nLine one\nline two\nA calm room\nx", $text );
	foreach ( array( '<', '>', '[twd_', 'bad()', 'class=' ) as $bad ) {
		twd_sk_hasnt( $bad, $text );
	}
	twd_sk_eq( '', TWD_SK_Mirror::text( '' ) );
	twd_sk_eq( '', TWD_SK_Mirror::text( null ) );
	twd_sk_eq( '', TWD_SK_Mirror::text( '<div></div><img src="/x.jpg" alt="">' ) );
} );

twd_sk_test( 'mirror: the text is cut at 100 KB', function () {
	$text = TWD_SK_Mirror::text( '<p>' . str_repeat( 'word ', 40000 ) . '</p>' );
	twd_sk_true( strlen( $text ) <= 100 * 1024 );
	twd_sk_true( strlen( $text ) > 90 * 1024 );
} );

twd_sk_test( 'mirror: every new version writes the text to meta, and an unchanged save does nothing', function () {
	twd_sk_mirror_page( 12, '[twd_page]' );
	TWD_SK_Store::save( 12, '<h2 class="twd-sk-title">Hello</h2><p>World</p>' );
	twd_sk_eq( "Hello\nWorld", get_post_meta( 12, '_twd_sk_text', true ) );
	TWD_SK_Store::save( 12, '<p>Second version</p>' );
	twd_sk_eq( 'Second version', get_post_meta( 12, '_twd_sk_text', true ) );
	$GLOBALS['twd_stub']['meta'][12]['_twd_sk_text'] = serialize( 'edited by hand' );
	TWD_SK_Store::save( 12, '<p>Second version</p>' );
	twd_sk_eq( 'edited by hand', get_post_meta( 12, '_twd_sk_text', true ), 'same HTML is a no-op, the hook does not run' );
	TWD_SK_Store::undo( 12 );
	twd_sk_eq( "Hello\nWorld", get_post_meta( 12, '_twd_sk_text', true ), 'undo refreshes it too' );
} );

twd_sk_test( 'mirror: a page on the kit template also gets the text as its content (so core search, feeds, excerpts and Yoast see it)', function () {
	twd_sk_mirror_page( 13, '', true );
	TWD_SK_Store::save( 13, '<h2 class="twd-sk-title">About me</h2><p>I am a counsellor.</p>' );
	twd_sk_eq( "About me\nI am a counsellor.", get_post( 13 )->post_content );
	twd_sk_eq( 'twd-sk-page-ignored', 'twd-sk-page-ignored' );
	$updates = $GLOBALS['twd_stub']['updated'];
	twd_sk_eq( 1, count( $updates ) );
	twd_sk_eq( array( 'ID', 'post_content' ), array_keys( $updates[0] ), 'only the content is written, never the status' );
	TWD_SK_Store::save( 13, '<p>Changed words</p>' );
	twd_sk_eq( 'Changed words', get_post( 13 )->post_content );
	twd_sk_eq( 2, count( $GLOBALS['twd_stub']['updated'] ) );
	TWD_SK_Mirror::sync( 13 );
	twd_sk_eq( 2, count( $GLOBALS['twd_stub']['updated'] ), 'already in step: no rewrite' );
} );

twd_sk_test( 'mirror: a [twd_page] page or an Elementor layout keeps its own content untouched', function () {
	twd_sk_mirror_page( 14, '[twd_page]' );
	TWD_SK_Store::save( 14, '<p>Words</p>' );
	twd_sk_eq( '[twd_page]', get_post( 14 )->post_content );
	twd_sk_eq( 'Words', get_post_meta( 14, '_twd_sk_text', true ), 'but the meta copy is kept' );
	twd_sk_mirror_page( 15, '<div class="elementor">rendered</div>' );
	update_post_meta( 15, '_elementor_data', '[{"id":"1"}]' );
	TWD_SK_Store::save( 15, '<p>Words</p>' );
	twd_sk_eq( '<div class="elementor">rendered</div>', get_post( 15 )->post_content );
	twd_sk_eq( array(), $GLOBALS['twd_stub']['updated'], 'nothing rewritten' );
} );

twd_sk_test( 'mirror: switching a page with empty content to the kit template fills it at once; a [twd_page] page keeps its shortcode so it can be switched back; safe mode leaves everything alone', function () {
	twd_sk_mirror_page( 16, '' );
	update_post_meta( 16, '_elementor_data', '[{"id":"1"}]' );
	TWD_SK_Store::save( 16, '<h2 class="twd-sk-title">Title</h2><p>Body</p>' );
	twd_sk_eq( '', get_post( 16 )->post_content, 'not on the template yet: untouched' );
	TWD_SK_Template::switch_template( 16, true, true );
	twd_sk_eq( "Title\nBody", get_post( 16 )->post_content );

	twd_sk_mirror_page( 19, '[twd_page]' );
	TWD_SK_Store::save( 19, '<p>Body</p>' );
	TWD_SK_Template::switch_template( 19, true, true );
	twd_sk_eq( '[twd_page]', get_post( 19 )->post_content, 'the shortcode stays, so switching back still works' );
	$back = TWD_SK_Template::switch_template( 19, false, false );
	twd_sk_true( ! is_wp_error( $back ) );
	twd_sk_eq( 'Body', get_post_meta( 19, '_twd_sk_text', true ) );

	twd_sk_mirror_page( 17, '' );
	TWD_SK_Store::save( 17, '<p>Body</p>' );
	TWD_SK_Safe::set( true );
	TWD_SK_Template::switch_template( 17, true, true );
	twd_sk_eq( '', get_post( 17 )->post_content, 'safe mode: no rewrite' );
} );

twd_sk_test( 'mirror: content that is not the plugin\'s own text is never replaced, even on a kit template page', function () {
	twd_sk_mirror_page( 20, 'Words a person typed in the editor', true );
	TWD_SK_Store::save( 20, '<p>Kit words</p>' );
	twd_sk_eq( 'Words a person typed in the editor', get_post( 20 )->post_content );
	twd_sk_eq( 'Kit words', get_post_meta( 20, '_twd_sk_text', true ) );
	twd_sk_eq( array(), $GLOBALS['twd_stub']['updated'] );
	// Our own earlier text may be replaced; text changed afterwards by a person may not.
	twd_sk_mirror_page( 21, '', true );
	TWD_SK_Store::save( 21, '<p>First</p>' );
	TWD_SK_Store::save( 21, '<p>Second</p>' );
	twd_sk_eq( 'Second', get_post( 21 )->post_content );
	get_post( 21 )->post_content = 'Edited by hand afterwards';
	TWD_SK_Store::save( 21, '<p>Third</p>' );
	twd_sk_eq( 'Edited by hand afterwards', get_post( 21 )->post_content );
	twd_sk_eq( 'Third', get_post_meta( 21, '_twd_sk_text', true ) );
} );

twd_sk_test( 'mirror: the module starts only outside safe mode (it is started from TWD_SK_Modules)', function () {
	$mods = file_get_contents( ABSPATH . 'includes/class-twd-sk-modules.php' );
	twd_sk_has( 'TWD_SK_Mirror::init()', $mods );
	$GLOBALS['twd_stub']['hooks'] = array();
	TWD_SK_Mirror::init();
	twd_sk_true( ! empty( $GLOBALS['twd_stub']['hooks']['twd_sk_committed'] ) );
	twd_sk_true( ! empty( $GLOBALS['twd_stub']['hooks']['wpseo_sitemap_urlimages'] ) );
} );

twd_sk_test( 'mirror: Yoast sitemap images get the kit page pictures, once each, and never the sample picture names', function () {
	twd_sk_mirror_page( 18, '[twd_page]' );
	TWD_SK_Store::save( 18, '<img src="/wp-content/uploads/a.jpg" alt="x"><img src="https://example.com/b.png" alt="y"><img src="/wp-content/uploads/a.jpg" alt="z"><img src="/wp-content/uploads/your-image.jpg" alt="sample">' );
	$out = TWD_SK_Mirror::filter_sitemap_images( array( array( 'src' => 'https://example.test/wp-content/uploads/a.jpg' ) ), 18 );
	$srcs = array_map( function ( $i ) {
		return $i['src'];
	}, $out );
	twd_sk_eq( array( 'https://example.test/wp-content/uploads/a.jpg', 'https://example.com/b.png' ), $srcs );
	twd_sk_eq( array(), TWD_SK_Mirror::filter_sitemap_images( 'nope', 99 ) );
} );

twd_sk_test( 'mirror: it writes only the content field and the text meta, and never makes a network call', function () {
	$src = file_get_contents( ABSPATH . 'includes/class-twd-sk-mirror.php' );
	foreach ( array( 'wp_remote_', 'file_put_contents', 'eval(', 'post_status', "'post_title'", 'update_option' ) as $bad ) {
		twd_sk_hasnt( $bad, $src );
	}
	twd_sk_hasnt( "\xE2\x80\x94", $src );
} );
