<?php
// Draft previews: created through the REST layer, shown only to their owner.

function twd_sk_prev_setup() {
	twd_stub_add_post( 12, 'page', '[twd_page]' );
	twd_stub_add_post( 13, 'page', '[twd_page]' );
	$GLOBALS['twd_stub']['caps']    = array( 'edit_pages', 'edit_post:12' );
	$GLOBALS['twd_stub']['queried'] = 12;
	$GLOBALS['twd_stub']['post_id'] = 12;
	TWD_SK_Preview::reset();
	unset( $_GET['twd_sk_preview'] );
}
function twd_sk_prev_refused( $token, $message_part ) {
	$_GET['twd_sk_preview'] = $token;
	try {
		TWD_SK_Preview::maybe_start();
	} catch ( TWD_SK_Stub_Die $e ) {
		twd_sk_has( $message_part, $e->getMessage() );
		twd_sk_eq( 403, $GLOBALS['twd_stub']['die_args']['response'] );
		twd_sk_true( ! TWD_SK_Preview::is_active() );
		return;
	}
	twd_sk_fail( 'expected a refusal containing: ' . $message_part );
}

twd_sk_test( 'preview: create stores cleaned HTML under a random token and read gives it back', function () {
	twd_sk_prev_setup();
	$token = TWD_SK_Preview::create( 7, 12, '<p>Draft</p>' );
	twd_sk_true( TWD_SK_Preview::valid_token( $token ) );
	$data = TWD_SK_Preview::read( $token );
	twd_sk_eq( 7, $data['user'] );
	twd_sk_eq( 12, $data['page'] );
	twd_sk_eq( '<p>Draft</p>', $data['html'] );
	twd_sk_eq( TWD_SK_Preview::TTL, $GLOBALS['twd_stub']['transients']['twd_sk_prev_' . $token]['seconds'] );
	twd_sk_eq( 900, TWD_SK_Preview::TTL );
	twd_sk_true( $token !== TWD_SK_Preview::create( 7, 12, '<p>Draft</p>' ), 'tokens are unique' );
} );

twd_sk_test( 'preview: tokens must be 32 lowercase hex characters', function () {
	foreach ( array( '', 'abc', str_repeat( 'g', 32 ), str_repeat( 'A', 32 ), str_repeat( 'a', 31 ), str_repeat( 'a', 33 ), '../../etc', array( 'x' ), null ) as $bad ) {
		twd_sk_true( ! TWD_SK_Preview::valid_token( $bad ) );
		twd_sk_eq( null, TWD_SK_Preview::read( $bad ) );
	}
	twd_sk_true( TWD_SK_Preview::valid_token( str_repeat( 'a', 32 ) ) );
} );

twd_sk_test( 'preview: an expired token is gone, and an unknown one is null', function () {
	twd_sk_prev_setup();
	$token = TWD_SK_Preview::create( 7, 12, '<p>Draft</p>' );
	$GLOBALS['twd_stub']['transients']['twd_sk_prev_' . $token]['value']['created'] = time() - 901;
	twd_sk_eq( null, TWD_SK_Preview::read( $token ) );
	twd_sk_true( ! isset( $GLOBALS['twd_stub']['transients']['twd_sk_prev_' . $token] ), 'expired entry is deleted' );
	twd_sk_eq( null, TWD_SK_Preview::read( str_repeat( 'b', 32 ) ) );
} );

twd_sk_test( 'preview: a user keeps at most five previews, the oldest is dropped', function () {
	twd_sk_prev_setup();
	$tokens = array();
	for ( $i = 0; $i < 7; $i++ ) {
		$tokens[] = TWD_SK_Preview::create( 7, 12, '<p>Draft ' . $i . '</p>' );
	}
	twd_sk_eq( null, TWD_SK_Preview::read( $tokens[0] ) );
	twd_sk_eq( null, TWD_SK_Preview::read( $tokens[1] ) );
	for ( $i = 2; $i < 7; $i++ ) {
		twd_sk_true( null !== TWD_SK_Preview::read( $tokens[ $i ] ), 'kept ' . $i );
	}
	$other = TWD_SK_Preview::create( 8, 12, '<p>Other</p>' );
	twd_sk_true( null !== TWD_SK_Preview::read( $other ) && null !== TWD_SK_Preview::read( $tokens[6] ), 'another user is unaffected' );
} );

twd_sk_test( 'preview: only the owner can discard', function () {
	twd_sk_prev_setup();
	$token = TWD_SK_Preview::create( 7, 12, '<p>Draft</p>' );
	twd_sk_true( ! TWD_SK_Preview::discard( $token, 8 ) );
	twd_sk_true( TWD_SK_Preview::discard( $token, 7 ) );
	twd_sk_eq( null, TWD_SK_Preview::read( $token ) );
} );

twd_sk_test( 'preview: the address is the page\'s own address plus the token', function () {
	twd_sk_prev_setup();
	$token = str_repeat( 'a', 32 );
	twd_sk_eq( 'https://example.test/?page_id=12&twd_sk_preview=' . $token, TWD_SK_Preview::url( 12, $token ) );
} );

twd_sk_test( 'preview: with no token in the request nothing happens', function () {
	twd_sk_prev_setup();
	TWD_SK_Preview::maybe_start();
	twd_sk_true( ! TWD_SK_Preview::is_active() );
	twd_sk_true( ! TWD_SK_Preview::is_preview_request() );
	twd_sk_eq( '', TWD_SK_Preview::request_token() );
} );

twd_sk_test( 'preview: the owner with edit rights sees the draft in place of the stored HTML, for that page only', function () {
	twd_sk_prev_setup();
	TWD_SK_Store::save( 12, '<p>Saved words</p>' );
	$token = TWD_SK_Preview::create( 7, 12, '<p>Draft words</p>' );
	$_GET['twd_sk_preview'] = $token;
	TWD_SK_Preview::maybe_start();
	twd_sk_true( TWD_SK_Preview::is_active() );
	twd_sk_has( 'nocache', implode( ',', $GLOBALS['twd_stub']['headers'] ) );
	twd_sk_eq( '<p>Draft words</p>', TWD_SK_Page::current_html( 12 ) );
	twd_sk_has( 'Draft words', TWD_SK_Page::render() );
	twd_sk_hasnt( 'Saved words', TWD_SK_Page::render() );
	TWD_SK_Store::save( 13, '<p>Other page</p>' );
	twd_sk_eq( '<p>Other page</p>', TWD_SK_Page::current_html( 13 ), 'another page is untouched' );
	// Nothing was written.
	twd_sk_eq( '<p>Saved words</p>', TWD_SK_Store::get_current( 12 ) );
	twd_sk_eq( 1, TWD_SK_Store::get_current_version_id( 12 ) );
	// Robots and the bar.
	$robots = TWD_SK_Preview::filter_robots( array( 'max-image-preview' => 'large' ) );
	twd_sk_eq( true, $robots['noindex'] );
	twd_sk_eq( true, $robots['nofollow'] );
	ob_start();
	TWD_SK_Preview::print_bar();
	$bar = ob_get_clean();
	twd_sk_has( 'Preview only. Nothing has been saved.', $bar );
	twd_sk_has( 'class="twd-sk-ed"', $bar );
	unset( $_GET['twd_sk_preview'] );
	TWD_SK_Preview::reset();
} );

twd_sk_test( 'preview: the draft decides whether the page has an h1 (title hiding follows the preview)', function () {
	twd_sk_prev_setup();
	TWD_SK_Store::save( 12, '<p>No heading</p>' );
	twd_sk_true( ! TWD_SK_Page::page_has_h1( 12 ) );
	$token = TWD_SK_Preview::create( 7, 12, '<section class="twd-sk-hero twd-sk-hero--split"><h1 class="twd-sk-hero__title">Hi</h1></section>' );
	$_GET['twd_sk_preview'] = $token;
	TWD_SK_Preview::maybe_start();
	twd_sk_true( TWD_SK_Page::page_has_h1( 12 ) );
	unset( $_GET['twd_sk_preview'] );
	TWD_SK_Preview::reset();
} );

twd_sk_test( 'preview security: a visitor, a user who cannot edit the page, an expired, malformed, other user\'s or other page\'s token are all refused with 403', function () {
	twd_sk_prev_setup();
	$token = TWD_SK_Preview::create( 7, 12, '<p>Draft</p>' );

	$GLOBALS['twd_stub']['user'] = 0;
	twd_sk_prev_refused( $token, 'signed in' );
	$GLOBALS['twd_stub']['user'] = 7;

	$GLOBALS['twd_stub']['caps'] = array( 'read' );
	twd_sk_prev_refused( $token, 'signed in' );
	$GLOBALS['twd_stub']['caps'] = array( 'edit_pages', 'edit_post:12' );

	twd_sk_prev_refused( 'not-a-token', 'expired or was discarded' );
	twd_sk_prev_refused( str_repeat( 'c', 32 ), 'expired or was discarded' );

	$GLOBALS['twd_stub']['transients']['twd_sk_prev_' . $token]['value']['created'] = time() - 1000;
	twd_sk_prev_refused( $token, 'expired or was discarded' );

	$token2 = TWD_SK_Preview::create( 8, 12, '<p>Theirs</p>' );
	twd_sk_prev_refused( $token2, 'belongs to someone else' );

	$token3 = TWD_SK_Preview::create( 7, 13, '<p>Page 13</p>' );
	twd_sk_prev_refused( $token3, 'belongs to someone else or to another page' );

	$GLOBALS['twd_stub']['queried'] = 0;
	twd_sk_prev_refused( $token, 'signed in' );
	unset( $_GET['twd_sk_preview'] );
} );

twd_sk_test( 'preview: the preview class only reads and writes transients, never the page or its history', function () {
	$src = file_get_contents( ABSPATH . 'includes/class-twd-sk-preview.php' );
	foreach ( array( 'update_post_meta', 'add_post_meta', 'wp_insert_post', 'wp_update_post', 'update_option', 'TWD_SK_Store::save', 'wp_remote_' ) as $bad ) {
		twd_sk_hasnt( $bad, $src );
	}
	twd_sk_hasnt( "\xE2\x80\x94", $src );
} );
