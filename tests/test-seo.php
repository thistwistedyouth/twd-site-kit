<?php
// Search details of a kit page: fields, plugin modes, printing, slug, quality checks.

function twd_sk_seo_mode( $mode, $name = '' ) {
	$GLOBALS['twd_stub']['hooks']['twd_sk_seo_plugin'] = array( function () use ( $mode, $name ) {
		return array( 'mode' => $mode, 'name' => $name );
	} );
}
function twd_sk_seo_page( $id = 30 ) {
	twd_stub_reset();
	twd_stub_add_post( $id, 'page', '[twd_page]' );
	$GLOBALS['twd_stub']['attachments'][55] = array( 'url' => 'https://example.test/u/pic.jpg', 'w' => 1200, 'h' => 630, 'is_image' => true );
	twd_sk_seo_mode( 'none' );
}
function twd_sk_seo_head( $id ) {
	$GLOBALS['twd_stub']['queried'] = $id;
	ob_start();
	TWD_SK_Seo::print_head();
	return ob_get_clean();
}

twd_sk_test( 'seo: with no SEO plugin the fields are saved to the kit own meta and read back', function () {
	twd_sk_seo_page();
	$r = TWD_SK_Seo::write( 30, array( 'title' => 'Anxiety help in Leeds', 'description' => 'Gentle talking therapy.', 'image_id' => 55, 'noindex' => true ) );
	twd_sk_true( ! is_wp_error( $r ) );
	twd_sk_eq( 'none', $r['mode'] );
	twd_sk_eq( 'Anxiety help in Leeds', $r['title'] );
	twd_sk_eq( 55, $r['image_id'] );
	twd_sk_true( $r['noindex'] );
	twd_sk_eq( 'Anxiety help in Leeds', get_post_meta( 30, '_twd_sk_seo_title', true ) );
	twd_sk_eq( '', get_post_meta( 30, '_yoast_wpseo_title', true ), 'nothing written to Yoast keys' );
} );

twd_sk_test( 'seo: Yoast mode writes Yoast keys and re-saves the page', function () {
	twd_sk_seo_page();
	twd_sk_seo_mode( 'yoast', 'Yoast SEO' );
	$r = TWD_SK_Seo::write( 30, array( 'title' => 'T', 'description' => 'D', 'image_id' => 55, 'noindex' => false ) );
	twd_sk_true( ! is_wp_error( $r ) );
	twd_sk_eq( 'T', get_post_meta( 30, '_yoast_wpseo_title', true ) );
	twd_sk_eq( 'D', get_post_meta( 30, '_yoast_wpseo_metadesc', true ) );
	twd_sk_eq( '55', get_post_meta( 30, '_yoast_wpseo_opengraph-image-id', true ) );
	twd_sk_eq( 'https://example.test/u/pic.jpg', get_post_meta( 30, '_yoast_wpseo_twitter-image', true ) );
	twd_sk_eq( '0', get_post_meta( 30, '_yoast_wpseo_meta-robots-noindex', true ) );
	twd_sk_eq( '', get_post_meta( 30, '_twd_sk_seo_title', true ) );
	twd_sk_true( ! empty( $GLOBALS['twd_stub']['updated'] ), 'page re-saved' );
	twd_sk_eq( '', twd_sk_seo_head( 30 ), 'the kit prints nothing when Yoast is in charge' );
} );

twd_sk_test( 'seo: another SEO plugin refuses the writes and prints nothing', function () {
	twd_sk_seo_page();
	twd_sk_seo_mode( 'other', 'Rank Math' );
	twd_sk_is_error( 'twd_sk_seo_other_plugin', TWD_SK_Seo::write( 30, array( 'title' => 'X' ) ) );
	twd_sk_eq( '', get_post_meta( 30, '_twd_sk_seo_title', true ) );
	twd_sk_eq( '', twd_sk_seo_head( 30 ) );
	$r = TWD_SK_Seo::write( 30, array( 'slug' => 'new-address', 'confirm_slug_change' => true ) );
	twd_sk_true( ! is_wp_error( $r ), 'the slug can still be changed' );
} );

twd_sk_test( 'seo: values are plain text, cut to the limits, and bad input is refused', function () {
	twd_sk_seo_page();
	$r = TWD_SK_Seo::write( 30, array( 'title' => '<b>Hi</b> ' . str_repeat( 'a', 300 ), 'description' => str_repeat( 'd', 900 ) ) );
	twd_sk_true( ! is_wp_error( $r ) );
	twd_sk_hasnt( '<', $r['title'] );
	twd_sk_true( strlen( $r['title'] ) <= 120 );
	twd_sk_true( strlen( $r['description'] ) <= 300 );
	twd_sk_is_error( 'twd_sk_bad_seo', TWD_SK_Seo::write( 30, array( 'title' => array( 'x' ) ) ) );
	twd_sk_is_error( 'twd_sk_bad_seo', TWD_SK_Seo::write( 30, array( 'image_id' => 999 ) ), 'not a media library picture' );
	twd_sk_is_error( 'twd_sk_bad_seo', TWD_SK_Seo::write( 30, array( 'image_id' => '12abc' ) ) );
	twd_sk_is_error( 'twd_sk_bad_seo', TWD_SK_Seo::write( 30, array( 'noindex' => 'maybe' ) ) );
	twd_sk_is_error( 'twd_sk_bad_seo', TWD_SK_Seo::write( 30, array( 'surprise' => 'x' ) ) );
	twd_sk_is_error( 'twd_sk_bad_seo', TWD_SK_Seo::write( 30, array( 'slug' => '!!!' ) ) );
	twd_sk_is_error( 'twd_sk_bad_seo', TWD_SK_Seo::write( 30, 'text' ) );
	$r = TWD_SK_Seo::write( 30, array( 'image_id' => 0 ) );
	twd_sk_eq( 0, $r['image_id'], 'clearing the picture works' );
} );

twd_sk_test( 'seo: changing the address of a live page needs confirmation, a front page cannot change it', function () {
	twd_sk_seo_page();
	$e = TWD_SK_Seo::write( 30, array( 'slug' => 'Anxiety Help' ) );
	twd_sk_is_error( 'twd_sk_slug_confirm', $e );
	twd_sk_eq( 'page-30', get_post( 30 )->post_name, 'unchanged without confirmation' );
	$r = TWD_SK_Seo::write( 30, array( 'slug' => 'Anxiety Help', 'confirm_slug_change' => true ) );
	twd_sk_true( ! is_wp_error( $r ) );
	twd_sk_eq( 'anxiety-help', $r['slug'] );
	get_post( 30 )->post_status = 'draft';
	$r = TWD_SK_Seo::write( 30, array( 'slug' => 'draft-address' ) );
	twd_sk_true( ! is_wp_error( $r ), 'a draft needs no confirmation' );
	update_option( 'page_on_front', 30 );
	twd_sk_is_error( 'twd_sk_seo_front_page', TWD_SK_Seo::write( 30, array( 'slug' => 'other' ) ) );
} );

twd_sk_test( 'seo: with no plugin the tags are printed, escaped, once, and only for kit-owned values', function () {
	twd_sk_seo_page();
	twd_sk_eq( '', twd_sk_seo_head( 30 ), 'nothing set, nothing printed' );
	TWD_SK_Seo::write( 30, array( 'title' => 'A "quoted" <title>', 'description' => 'Short & sweet', 'image_id' => 55 ) );
	$h = twd_sk_seo_head( 30 );
	twd_sk_has( '<meta name="description" content="Short &amp; sweet">', $h );
	twd_sk_has( 'property="og:title"', $h );
	twd_sk_has( 'property="og:image" content="https://example.test/u/pic.jpg"', $h );
	twd_sk_has( 'twitter:card" content="summary_large_image"', $h );
	twd_sk_hasnt( '<title>', $h );
	twd_sk_eq( 1, substr_count( $h, 'name="description"' ) );
		$GLOBALS['twd_stub']['queried'] = 30;
	twd_sk_hasnt( '<', TWD_SK_Seo::filter_title( 'x' ) );
	TWD_SK_Seo::write( 30, array( 'noindex' => true ) );
	$robots = TWD_SK_Seo::filter_robots( array() );
	twd_sk_true( ! empty( $robots['noindex'] ) );
} );

twd_sk_test( 'seo: safe mode prints nothing', function () {
	twd_sk_seo_page();
	TWD_SK_Seo::write( 30, array( 'title' => 'T', 'description' => 'D' ) );
	TWD_SK_Safe::set( true );
	twd_sk_eq( '', twd_sk_seo_head( 30 ) );
	TWD_SK_Safe::set( false );
} );

twd_sk_test( 'seo: example text in the title or description blocks publishing through the leftover levels', function () {
	twd_sk_seo_page();
	$levels = array( 'must' => array(), 'check' => array() );
	twd_sk_eq( $levels, TWD_SK_Seo::filter_levels( $levels, 30 ) );
	TWD_SK_Seo::write( 30, array( 'title' => '[PLACEHOLDER: your title]' ) );
	$out = TWD_SK_Seo::filter_levels( $levels, 30 );
	twd_sk_eq( 1, $out['must']['[PLACEHOLDER'], 'a placeholder in the title is a must-fix' );
	$r = TWD_SK_Seo::read( 30 );
	twd_sk_true( count( $r['leftovers'] ) > 0, 'the SEO tab lists it' );
} );

twd_sk_test( 'quality: reports missing alt, skipped headings, vague and empty links, no h1; says nothing for a clean page', function () {
	$clean = '<h1>Hi</h1><h2>Two</h2><img src="/a.jpg" alt=""><img src="/b.jpg" alt="A room"><a href="/contact">Contact David</a>';
	twd_sk_eq( array(), TWD_SK_Quality::check( $clean ) );
	twd_sk_eq( array(), TWD_SK_Quality::check( '' ) );
	$bad = '<h2>Top</h2><h4>Skip</h4><img src="/a.jpg"><a href="/x">Click here</a><a href="/y">Read more.</a><a href="/z"></a><a href="/w"><img src="/i.jpg" alt="Logo"></a>';
	$ids = array();
	foreach ( TWD_SK_Quality::check( $bad ) as $q ) {
		$ids[ $q['id'] ] = $q['count'];
	}
	twd_sk_eq( 1, $ids['no_alt'] );
	twd_sk_eq( 1, $ids['heading_skip'] );
	twd_sk_eq( 2, $ids['vague_link'] );
	twd_sk_eq( 1, $ids['empty_link'] );
	twd_sk_eq( 1, $ids['no_h1'] );
} );

twd_sk_test( 'seo: routes are guarded and the SEO endpoints are missing in safe mode', function () {
	twd_sk_seo_page();
	$seo = array_values( array_filter( twd_sk_rest_routes(), function ( $r ) { return false !== strpos( $r['route'], '/seo' ); } ) );
	twd_sk_eq( 2, count( $seo ) );
	$post = twd_sk_rest_req( array( 'id' => 30, 'fields' => array( 'title' => 'Hello' ) ) );
	$r = TWD_SK_REST::post_seo( $post );
	twd_sk_eq( 'Hello', $r['title'] );
	twd_sk_eq( 400, twd_sk_status( TWD_SK_REST::post_seo( twd_sk_rest_req( array( 'id' => 30, 'fields' => 'x' ) ) ) ) );
	twd_sk_eq( 400, twd_sk_status( TWD_SK_REST::post_seo( twd_sk_rest_req( array( 'id' => 30, 'fields' => array( 'bogus' => 1 ) ) ) ) ) );
	twd_sk_eq( 409, twd_sk_status( TWD_SK_REST::post_seo( twd_sk_rest_req( array( 'id' => 30, 'fields' => array( 'slug' => 'new-one' ) ) ) ) ) );
	TWD_SK_Safe::set( true );
	$left = array_filter( twd_sk_rest_routes(), function ( $r ) { return false !== strpos( $r['route'], '/seo' ); } );
	twd_sk_eq( 0, count( $left ) );
	TWD_SK_Safe::set( false );
} );

twd_sk_test( 'seo js: no code injection, no storage, no web addresses, no native dialogs, talks only through the editor api, parses', function () {
	$js = file_get_contents( ABSPATH . 'assets/twd-site-kit-editor-seo.js' );
	foreach ( array( 'eval(', 'new Function', 'document.write', 'innerHTML', 'outerHTML', 'insertAdjacentHTML', 'srcdoc', 'XMLHttpRequest', 'WebSocket', 'sendBeacon', 'localStorage', 'sessionStorage', 'fetch(', 'importScripts' ) as $bad ) {
		twd_sk_hasnt( $bad, $js );
	}
	twd_sk_true( 1 !== preg_match( '#https?://#i', $js ), 'no web addresses' );
	twd_sk_true( 1 !== preg_match( '/(^|[^.\w])(alert|confirm|prompt)\s*\(/m', $js ), 'no native dialogs' );
	twd_sk_has( "api('GET', path)", $js );
	twd_sk_has( "api('POST', path", $js );
	twd_sk_has( 'ED.confirmInline(', $js );
	twd_sk_has( "ED.addTab('seo', 'Search'", $js );
	if ( function_exists( 'shell_exec' ) && '' !== trim( (string) shell_exec( 'command -v node 2>/dev/null' ) ) ) {
		twd_sk_eq( '', trim( (string) shell_exec( 'node --check ' . escapeshellarg( ABSPATH . 'assets/twd-site-kit-editor-seo.js' ) . ' 2>&1' ) ) );
	}
} );

twd_sk_test( 'seo: the Search script loads for an editor on a kit page and not in safe mode', function () {
	twd_sk_ed_setup( array( 'edit_pages', 'edit_post:12' ) );
	twd_sk_true( twd_sk_ed_loaded() );
	twd_sk_true( ! empty( $GLOBALS['twd_stub']['scripts']['twd-site-kit-editor-seo']['enqueued'] ) );
	twd_sk_ed_setup( array( 'edit_pages', 'edit_post:12' ) );
	$GLOBALS['twd_stub']['scripts'] = array();
	TWD_SK_Safe::set( true );
	twd_sk_true( twd_sk_ed_loaded() );
	twd_sk_true( ! isset( $GLOBALS['twd_stub']['scripts']['twd-site-kit-editor-seo'] ), 'not in safe mode' );
	TWD_SK_Safe::set( false );
} );
