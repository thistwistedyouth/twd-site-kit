<?php
// The launch check (0.9.0): read only; publishing only what is ready.

function twd_sk_launch_site( $ready = true ) {
	twd_stub_add_post( 21, 'page', '[twd_page]' );
	$GLOBALS['twd_stub']['posts'][21]->post_title = 'Home';
	twd_stub_add_post( 22, 'page', '[twd_page]' );
	$GLOBALS['twd_stub']['posts'][22]->post_title = 'Contact';
	TWD_SK_Store::save( 21, '<section class="twd-sk-hero"><h1 class="twd-sk-hero__title">Welcome</h1></section>' );
	TWD_SK_Store::save( 22, $ready ? '<section class="twd-sk-hero"><h1 class="twd-sk-hero__title">Contact</h1><p>Call 01234 567890.</p></section>' : '<section class="twd-sk-hero"><h1 class="twd-sk-hero__title">Heading here</h1></section>' );
	update_option( 'show_on_front', 'page' );
	update_option( 'page_on_front', 21 );
	// Example addresses count as sample text, so this site has a phone number only.
	TWD_SK_Profile::save( array( 'site_name' => 'Example Hire', 'phone' => '01234 567890' ) );
	$GLOBALS['twd_stub']['posts'][21]->post_status = 'draft';
	$GLOBALS['twd_stub']['posts'][22]->post_status = 'draft';
	$GLOBALS['twd_stub']['caps'] = true;
}
function twd_sk_launch_item( $check, $id ) {
	foreach ( $check['items'] as $i ) {
		if ( $id === $i['id'] ) {
			return $i;
		}
	}
	return null;
}

twd_sk_test( 'launch check: lists the pages with what is left to replace, and the site-wide checks, changing nothing', function () {
	twd_sk_launch_site( false );
	$before = json_encode( array( $GLOBALS['twd_stub']['options'], $GLOBALS['twd_stub']['meta'] ) );
	$c      = TWD_SK_Launch::check();
	twd_sk_eq( $before, json_encode( array( $GLOBALS['twd_stub']['options'], $GLOBALS['twd_stub']['meta'] ) ), 'read only' );
	twd_sk_eq( 2, count( $c['pages'] ) );
	$contact = $c['pages'][1];
	twd_sk_eq( false, $contact['ready'] );
	twd_sk_has( 'still to replace', $contact['reason'] );
	twd_sk_eq( 'fail', twd_sk_launch_item( $c, 'pages' )['status'] );
	twd_sk_has( '"Contact"', twd_sk_launch_item( $c, 'pages' )['detail'] );
	twd_sk_eq( 'fail', twd_sk_launch_item( $c, 'privacy' )['status'], 'no privacy policy' );
	twd_sk_eq( 'warn', twd_sk_launch_item( $c, 'front' )['status'], 'front page is a draft' );
	twd_sk_eq( 'warn', twd_sk_launch_item( $c, 'search' )['status'], 'no search wording' );
	twd_sk_eq( 'warn', twd_sk_launch_item( $c, 'contact' )['status'], 'contact details not on any page' );
	twd_sk_eq( false, $c['ready'] );
	twd_sk_has( 'Launch check:', implode( "\n", TWD_SK_Launch::lines( $c ) ) );
} );

twd_sk_test( 'launch check: a site with everything in place passes, with the reminders that need a person', function () {
	twd_sk_launch_site( true );
	$GLOBALS['twd_stub']['privacy_url'] = 'https://example.test/privacy-policy/';
	foreach ( array( 21, 22 ) as $id ) {
		TWD_SK_Seo::write( $id, array( 'title' => 'A title', 'description' => 'A description.' ) );
	}
	TWD_SK_Store::save( 22, TWD_SK_Store::get_current( 22 ) . '<section class="twd-sk-enquiry"><div class="twd-sk-inner">[twd_enquiry]</div></section>' );
	update_option( 'admin_email', 'admin@example.net' );
	$c = TWD_SK_Launch::check();
	foreach ( array( 'safe', 'pages', 'quality', 'search', 'privacy', 'details', 'contact', 'visible', 'https', 'schema' ) as $id ) {
		twd_sk_eq( 'ok', twd_sk_launch_item( $c, $id )['status'], $id );
	}
	twd_sk_eq( 'warn', twd_sk_launch_item( $c, 'enquiry' )['status'], 'send a test message' );
	twd_sk_true( $c['ready'] );
	update_option( 'blog_public', '0' );
	twd_sk_eq( 'fail', twd_sk_launch_item( TWD_SK_Launch::check(), 'visible' )['status'] );
	TWD_SK_Profile::save( array( 'site_name' => '[PLACEHOLDER: business name]' ) );
	twd_sk_eq( 'fail', twd_sk_launch_item( TWD_SK_Launch::check(), 'details' )['status'] );
} );

twd_sk_test( 'launch publish: only ready pages are published, a page with anything to replace is refused, and it needs publish rights', function () {
	twd_sk_launch_site( false );
	$out = TWD_SK_Launch::publish( array( 21, 22 ) );
	twd_sk_eq( array( 'Home' ), array_map( function ( $p ) {
		return $p['title'];
	}, $out['published'] ) );
	twd_sk_eq( 'publish', get_post( 21 )->post_status );
	twd_sk_eq( 'Contact', $out['refused'][0]['title'] );
	twd_sk_has( 'example text', $out['refused'][0]['reason'] );
	twd_sk_true( 'publish' !== get_post( 22 )->post_status );
	$GLOBALS['twd_stub']['caps'] = array( 'edit_pages' );
	$out = TWD_SK_Launch::publish( array( 22 ) );
	twd_sk_has( 'You cannot publish', $out['refused'][0]['reason'] );
} );

twd_sk_test( 'launch rest and cli: needs confirm and a list; the command publishes only ready pages', function () {
	twd_sk_launch_site( true );
	twd_sk_site_caps();
	$GLOBALS['twd_stub']['caps'] = true;
	twd_sk_is_error( 'twd_sk_confirm_needed', TWD_SK_REST::post_site_launch_publish( twd_sk_rest_req( array( 'ids' => array( 21 ) ) ) ) );
	twd_sk_is_error( 'twd_sk_bad_input', TWD_SK_REST::post_site_launch_publish( twd_sk_rest_req( array( 'confirm' => true ) ) ) );
	$r = TWD_SK_REST::post_site_launch_publish( twd_sk_rest_req( array( 'confirm' => true, 'ids' => array( 21 ) ) ) );
	twd_sk_eq( 1, count( $r['published'] ) );
	twd_sk_true( isset( $r['check']['items'] ) );
	require_once __DIR__ . '/stub-wpcli.php';
	WP_CLI::reset();
	( new TWD_SK_CLI() )->launch_check( array(), array() );
	twd_sk_has( 'Launch check:', WP_CLI::all() );
	twd_sk_true( 'publish' !== get_post( 22 )->post_status, 'read only without --publish' );
	( new TWD_SK_CLI() )->launch_check( array(), array( 'publish' => true ) );
	twd_sk_has( 'Published "Contact"', WP_CLI::all() );
	twd_sk_has( 'LAUNCH CHECK', TWD_SK_Doctor::report() );
	$js = file_get_contents( ABSPATH . 'assets/twd-site-kit-setup.js' );
	twd_sk_has( "api('POST', '/site/launch/publish', { ids: ids, confirm: true })", $js );
	twd_sk_has( "confirmInline(host, 'Publish '", $js );
	twd_sk_has( "api('GET', '/site/launch')", file_get_contents( ABSPATH . 'assets/twd-site-kit-editor-site.js' ) );
} );

// -- 0.14.0: menu links, WordPress leftovers, tidy ----------------------------------------------

twd_sk_test( 'launch check: every menu link must reach a page; a draft target only warns; home, anchors, mailto and tel are not checked', function () {
	twd_sk_launch_site( true );
	$GLOBALS['twd_stub']['posts'][22]->post_name = 'contact';
	TWD_SK_Profile::save( array( 'menu' => array(
		array( 'label' => 'Home', 'url' => '/', 'children' => array() ),
		array( 'label' => 'Contact', 'url' => '/contact', 'children' => array( array( 'label' => 'Call', 'url' => 'tel:01234567890' ) ) ),
		array( 'label' => 'Top', 'url' => '#twd-sk-top', 'children' => array() ),
	) ) );
	$m = TWD_SK_Launch::menu_links( TWD_SK_Profile::get()['menu'] );
	twd_sk_eq( 1, $m['checked'], 'only the contact link needs checking' );
	twd_sk_eq( array(), $m['missing'] );
	twd_sk_eq( array( 'Contact' ), $m['drafts'] );
	twd_sk_eq( 'warn', twd_sk_launch_item( TWD_SK_Launch::check(), 'menu' )['status'] );
	$GLOBALS['twd_stub']['posts'][22]->post_status = 'publish';
	twd_sk_eq( 'ok', twd_sk_launch_item( TWD_SK_Launch::check(), 'menu' )['status'] );
	// Two links to pages that were never made: the real case from a client build.
	TWD_SK_Profile::save( array( 'menu' => array( array( 'label' => 'Events', 'url' => '/events', 'children' => array() ), array( 'label' => 'Our story', 'url' => '/our-story', 'children' => array() ) ) ) );
	$c = TWD_SK_Launch::check();
	$it = twd_sk_launch_item( $c, 'menu' );
	twd_sk_eq( 'fail', $it['status'] );
	twd_sk_has( 'Events (/events)', $it['detail'] );
	twd_sk_has( 'Our story (/our-story)', $it['detail'] );
	twd_sk_true( ! $c['ready'] );
	// An address elsewhere is asked for once; a 404 is a missing link, a 200 is fine.
	$GLOBALS['twd_stub']['http']['https://elsewhere.example/gone'] = array( 'code' => 404, 'body' => '' );
	$GLOBALS['twd_stub']['http']['https://elsewhere.example/fine'] = array( 'code' => 200, 'body' => '' );
	$m = TWD_SK_Launch::menu_links( array( array( 'label' => 'Gone', 'url' => 'https://elsewhere.example/gone', 'children' => array() ), array( 'label' => 'Fine', 'url' => 'https://elsewhere.example/fine', 'children' => array() ) ) );
	twd_sk_eq( array( 'Gone (https://elsewhere.example/gone)' ), $m['missing'] );
} );

twd_sk_test( 'launch check: the live sample page, the first post and the suggested privacy text are flagged, and tidy moves the first two to the bin, never deleting', function () {
	twd_sk_launch_site( true );
	twd_sk_eq( 'ok', twd_sk_launch_item( TWD_SK_Launch::check(), 'wp_leftovers' )['status'] );
	twd_stub_add_post( 2, 'page', 'Sample page content' );
	$GLOBALS['twd_stub']['posts'][2]->post_name  = 'sample-page';
	$GLOBALS['twd_stub']['posts'][2]->post_title = 'Sample Page';
	twd_stub_add_post( 1, 'post', 'Welcome to WordPress.' );
	$GLOBALS['twd_stub']['posts'][1]->post_name  = 'hello-world';
	$GLOBALS['twd_stub']['posts'][1]->post_title = 'Hello world!';
	twd_stub_add_post( 3, 'page', '<h2>Suggested text:</h2><p>Our website address is...</p>' );
	update_option( 'wp_page_for_privacy_policy', 3 );
	$c = TWD_SK_Launch::check();
	$it = twd_sk_launch_item( $c, 'wp_leftovers' );
	twd_sk_eq( 'fail', $it['status'] );
	twd_sk_has( 'Sample Page', $it['label'] );
	twd_sk_has( 'Hello world', $it['label'] );
	twd_sk_eq( 'fail', twd_sk_launch_item( $c, 'privacy_text' )['status'] );
	$t = TWD_SK_Launch::tidy();
	twd_sk_eq( array( 'Sample Page', 'Hello world!' ), $t['trashed'] );
	twd_sk_eq( array(), $t['refused'] );
	twd_sk_eq( 'trash', get_post( 2 )->post_status, 'in the bin, not deleted' );
	twd_sk_true( null !== get_post( 1 ), 'the post still exists' );
	twd_sk_eq( 'ok', twd_sk_launch_item( TWD_SK_Launch::check(), 'wp_leftovers' )['status'] );
	// Without delete rights nothing moves.
	twd_stub_add_post( 4, 'page', 'x' );
	$GLOBALS['twd_stub']['posts'][4]->post_name = 'sample-page';
	$GLOBALS['twd_stub']['caps'] = array( 'edit_pages' );
	$t = TWD_SK_Launch::tidy();
	twd_sk_eq( array(), $t['trashed'] );
	twd_sk_eq( 1, count( $t['refused'] ) );
	twd_sk_eq( 'publish', get_post( 4 )->post_status );
	$GLOBALS['twd_stub']['caps'] = true;
	update_option( 'wp_page_for_privacy_policy', 0 );
} );

twd_sk_test( 'rest and cli: the tidy route needs confirm and administrator rights; launch-check --tidy does the same', function () {
	twd_sk_launch_site( true );
	twd_stub_add_post( 2, 'page', 'Sample' );
	$GLOBALS['twd_stub']['posts'][2]->post_name  = 'sample-page';
	$GLOBALS['twd_stub']['posts'][2]->post_title = 'Sample Page';
	twd_sk_is_error( 'twd_sk_confirm_needed', TWD_SK_REST::post_site_launch_tidy( twd_sk_rest_req( array() ) ) );
	twd_sk_eq( 'publish', get_post( 2 )->post_status );
	$out = TWD_SK_REST::post_site_launch_tidy( twd_sk_rest_req( array( 'confirm' => true ) ) );
	twd_sk_eq( array( 'Sample Page' ), $out['trashed'] );
	twd_sk_true( isset( $out['check']['items'] ) );
	$GLOBALS['twd_stub']['routes'] = array();
	TWD_SK_REST::register_routes();
	$routes = array_filter( $GLOBALS['twd_stub']['routes'], function ( $r ) {
		return '/site/launch/tidy' === $r['route'];
	} );
	twd_sk_true( 1 === count( $routes ) );
	$r = array_values( $routes )[0];
	twd_sk_eq( array( 'TWD_SK_REST', 'can_manage_site' ), $r['args']['permission_callback'] );
} );
