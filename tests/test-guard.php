<?php
// TWD_SK_Guard (0.14.0): publishing from wp-admin is held back while a kit page has must-fix text; the edit screen says where a kit page is edited.

function twd_sk_guard_page( $html ) {
	twd_stub_reset();
	$GLOBALS['twd_stub']['caps'] = true;
	twd_stub_add_post( 31, 'page', '[twd_page]' );
	$GLOBALS['twd_stub']['posts'][31]->post_status = 'draft';
	$GLOBALS['twd_stub']['posts'][31]->post_title  = 'A kit page';
	TWD_SK_Store::save( 31, $html );
	TWD_SK_Guard::$allow = false;
}

twd_sk_test( 'guard: a kit page with must-fix text is kept as a draft when published from wp-admin, with a notice; a clean page publishes', function () {
	twd_sk_guard_page( '<section class="twd-sk-hero"><h1 class="twd-sk-hero__title">[PLACEHOLDER: Q09 registration number]</h1></section>' );
	$data = TWD_SK_Guard::hold_back( array( 'post_type' => 'page', 'post_status' => 'publish' ), array( 'ID' => 31 ) );
	twd_sk_eq( 'draft', $data['post_status'] );
	$held = get_transient( TWD_SK_Guard::TRANSIENT );
	twd_sk_eq( 1, $held[31]['count'] );
	twd_sk_has( '[PLACEHOLDER', $held[31]['what'] );
	twd_sk_eq( 7, $held[31]['user'] );
	ob_start();
	TWD_SK_Guard::notices();
	$out = ob_get_clean();
	twd_sk_has( 'kept as a draft', $out );
	twd_sk_has( 'publish it anyway', $out );
	twd_sk_has( 'action=twd_sk_publish_anyway&amp;post=31', $out );
	twd_sk_eq( false, get_transient( TWD_SK_Guard::TRANSIENT ), 'shown once' );
	// A page with nothing to fix is not touched.
	twd_sk_guard_page( '<section class="twd-sk-hero"><h1 class="twd-sk-hero__title">Welcome to an invented practice</h1></section>' );
	$data = TWD_SK_Guard::hold_back( array( 'post_type' => 'page', 'post_status' => 'publish' ), array( 'ID' => 31 ) );
	twd_sk_eq( 'publish', $data['post_status'] );
} );

twd_sk_test( 'guard: the kit\'s own publish path (the Pages tab, the launch check, WP-CLI) is let through, and ordinary posts, other pages and updates to a live page are untouched', function () {
	twd_sk_guard_page( '<section class="twd-sk-hero"><h1 class="twd-sk-hero__title">Heading here</h1></section>' );
	// Our own route with the explicit override: the flag is set around the update, so the guard stands aside.
	$r = TWD_SK_Template::set_status( 31, 'publish', true );
	twd_sk_true( ! is_wp_error( $r ), 'override publishes' );
	twd_sk_eq( 'publish', get_post( 31 )->post_status );
	twd_sk_true( ! TWD_SK_Guard::$allow, 'the flag is put back' );
	// An update to a page that is already live is not a publish.
	$data = TWD_SK_Guard::hold_back( array( 'post_type' => 'page', 'post_status' => 'publish' ), array( 'ID' => 31 ) );
	twd_sk_eq( 'publish', $data['post_status'] );
	// A post, and a page that does not use the kit.
	twd_stub_add_post( 32, 'post', 'words' );
	twd_sk_eq( 'publish', TWD_SK_Guard::hold_back( array( 'post_type' => 'post', 'post_status' => 'publish' ), array( 'ID' => 32 ) )['post_status'] );
	twd_stub_add_post( 33, 'page', 'An ordinary page with Heading here in it' );
	twd_sk_eq( 'publish', TWD_SK_Guard::hold_back( array( 'post_type' => 'page', 'post_status' => 'publish' ), array( 'ID' => 33 ) )['post_status'] );
	// Without the override our own path still refuses, so nothing changes either way.
	twd_sk_guard_page( '<section class="twd-sk-hero"><h1 class="twd-sk-hero__title">Heading here</h1></section>' );
	twd_sk_is_error( 'twd_sk_leftovers', TWD_SK_Template::set_status( 31, 'publish', false ) );
	twd_sk_eq( 'draft', get_post( 31 )->post_status );
} );

twd_sk_test( 'guard: the edit screen of a kit page says it is edited on the site, with a link; other screens get nothing', function () {
	twd_sk_guard_page( '<section class="twd-sk-hero"><h1 class="twd-sk-hero__title">Welcome</h1></section>' );
	$GLOBALS['twd_stub']['screen'] = 'post';
	$_GET['post'] = '31';
	ob_start();
	TWD_SK_Guard::notices();
	$out = ob_get_clean();
	twd_sk_has( 'edited on the site itself', $out );
	twd_sk_has( 'Visual editor', $out );
	twd_sk_has( 'href="https://example.test/?page_id=31"', $out );
	$_GET['post'] = '33';
	twd_stub_add_post( 33, 'page', 'ordinary' );
	ob_start();
	TWD_SK_Guard::notices();
	twd_sk_eq( '', ob_get_clean(), 'not a kit page' );
	unset( $_GET['post'] );
	$GLOBALS['twd_stub']['screen'] = 'plugins';
} );

twd_sk_test( 'guard: started from the modules (so off in safe mode) and hooked only in admin', function () {
	twd_sk_has( 'TWD_SK_Guard::init()', file_get_contents( ABSPATH . 'includes/class-twd-sk-modules.php' ) );
	twd_stub_reset();
	$GLOBALS['twd_stub']['admin'] = false;
	TWD_SK_Guard::init();
	twd_sk_true( empty( $GLOBALS['twd_stub']['hooks']['wp_insert_post_data'] ), 'nothing on the front end' );
	$GLOBALS['twd_stub']['admin'] = true;
	TWD_SK_Guard::init();
	twd_sk_true( ! empty( $GLOBALS['twd_stub']['hooks']['wp_insert_post_data'] ) );
} );
