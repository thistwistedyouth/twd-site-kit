<?php
// The editor's REST routes: who may call them, what they do, and that every write goes through the store.

function twd_sk_rest_setup( $caps = null ) {
	twd_stub_add_post( 12, 'page', '[twd_page]' );
	twd_stub_add_post( 13, 'page', '[twd_page]' );
	twd_stub_add_post( 14, 'post', 'a post' );
	$GLOBALS['twd_stub']['caps'] = null === $caps ? array( 'edit_pages', 'edit_post:12', 'edit_post:14' ) : $caps;
}
function twd_sk_rest_req( $params = array(), $nonce = 'good-nonce', $body = null ) {
	$headers = null === $nonce ? array() : array( 'X-WP-Nonce' => $nonce );
	return new WP_REST_Request( $params + array( 'id' => 12 ), $headers, $body );
}
function twd_sk_rest_routes() {
	$GLOBALS['twd_stub']['routes'] = array();
	TWD_SK_REST::register_routes();
	return $GLOBALS['twd_stub']['routes'];
}
function twd_sk_status( $e ) {
	$d = $e->get_error_data();
	return is_array( $d ) && isset( $d['status'] ) ? $d['status'] : 0;
}

twd_sk_test( 'rest: the seven routes exist under twd-site-kit/v1 and none is open to everyone', function () {
	$routes = twd_sk_rest_routes();
	twd_sk_eq( 7, count( $routes ) );
	foreach ( $routes as $r ) {
		twd_sk_eq( 'twd-site-kit/v1', $r['ns'] );
		twd_sk_true( is_array( $r['args']['permission_callback'] ) && 'TWD_SK_REST' === $r['args']['permission_callback'][0], 'a real permission callback on ' . $r['route'] );
		twd_sk_true( in_array( $r['args']['methods'], array( 'GET', 'POST' ), true ) );
		twd_sk_has( '(?P<id>\\d+)', $r['route'] );
	}
	$reads  = array();
	foreach ( $routes as $r ) {
		if ( 'GET' === $r['args']['methods'] ) {
			$reads[] = $r['route'];
		}
	}
	twd_sk_eq( 2, count( $reads ), 'only prompt and versions are GET' );
} );

twd_sk_test( 'rest security: a visitor, a missing nonce and a wrong nonce are refused on every route (401)', function () {
	twd_sk_rest_setup();
	foreach ( twd_sk_rest_routes() as $r ) {
		$cb = $r['args']['permission_callback'];

		$GLOBALS['twd_stub']['user'] = 0;
		$e = call_user_func( $cb, twd_sk_rest_req() );
		twd_sk_is_error( 'twd_sk_not_signed_in', $e, 'visitor on ' . $r['route'] );
		twd_sk_eq( 401, twd_sk_status( $e ) );

		$GLOBALS['twd_stub']['user'] = 7;
		twd_sk_is_error( 'twd_sk_bad_nonce', call_user_func( $cb, twd_sk_rest_req( array(), null ) ), 'no nonce on ' . $r['route'] );
		twd_sk_is_error( 'twd_sk_bad_nonce', call_user_func( $cb, twd_sk_rest_req( array(), 'wrong' ) ), 'wrong nonce on ' . $r['route'] );
		twd_sk_is_error( 'twd_sk_bad_nonce', call_user_func( $cb, twd_sk_rest_req( array(), '' ) ), 'empty nonce on ' . $r['route'] );
	}
} );

twd_sk_test( 'rest security: a signed-in user without edit rights (subscriber, contributor, author) is refused on every route (403)', function () {
	foreach ( array( array(), array( 'read' ), array( 'edit_posts' ), array( 'edit_posts', 'edit_post:12' ), array( 'edit_post:12' ) ) as $caps ) {
		twd_sk_rest_setup( $caps );
		foreach ( twd_sk_rest_routes() as $r ) {
			$e = call_user_func( $r['args']['permission_callback'], twd_sk_rest_req() );
			twd_sk_is_error( 'twd_sk_forbidden', $e, 'caps ' . implode( ',', $caps ) . ' on ' . $r['route'] );
			twd_sk_eq( 403, twd_sk_status( $e ) );
		}
	}
} );

twd_sk_test( 'rest security: an editor is allowed on the page they can edit, refused on another page', function () {
	twd_sk_rest_setup();
	foreach ( twd_sk_rest_routes() as $r ) {
		$cb = $r['args']['permission_callback'];
		twd_sk_eq( true, call_user_func( $cb, twd_sk_rest_req( array( 'id' => 12 ) ) ), 'allowed on 12 ' . $r['route'] );
		twd_sk_is_error( 'twd_sk_forbidden', call_user_func( $cb, twd_sk_rest_req( array( 'id' => 13 ) ) ), 'refused on 13 ' . $r['route'] );
	}
} );

twd_sk_test( 'rest security: only WordPress pages are allowed (a post id is refused with 404)', function () {
	twd_sk_rest_setup();
	$e = TWD_SK_REST::can_write_page( twd_sk_rest_req( array( 'id' => 14 ) ) );
	twd_sk_is_error( 'twd_sk_not_a_page', $e );
	twd_sk_eq( 404, twd_sk_status( $e ) );
	twd_sk_is_error( 'twd_sk_forbidden', TWD_SK_REST::can_write_page( twd_sk_rest_req( array( 'id' => 0 ) ) ) );
} );

twd_sk_test( 'rest limits: a body over 300 KB is refused with 413 before anything else', function () {
	twd_sk_rest_setup();
	$e = TWD_SK_REST::can_preview_page( twd_sk_rest_req( array(), 'good-nonce', str_repeat( 'a', 300 * 1024 + 1 ) ) );
	twd_sk_is_error( 'twd_sk_too_large', $e );
	twd_sk_eq( 413, twd_sk_status( $e ) );
	twd_sk_eq( true, TWD_SK_REST::can_preview_page( twd_sk_rest_req( array(), 'good-nonce', str_repeat( 'a', 1000 ) ) ) );
} );

twd_sk_test( 'rest limits: write and preview calls are limited per user per minute (429), and the window resets', function () {
	twd_sk_rest_setup();
	for ( $i = 0; $i < 30; $i++ ) {
		twd_sk_eq( true, TWD_SK_REST::can_write_page( twd_sk_rest_req() ), 'call ' . ( $i + 1 ) );
	}
	$e = TWD_SK_REST::can_write_page( twd_sk_rest_req() );
	twd_sk_is_error( 'twd_sk_rate_limited', $e );
	twd_sk_eq( 429, twd_sk_status( $e ) );
	// A different bucket and a different user are not affected.
	twd_sk_eq( true, TWD_SK_REST::can_preview_page( twd_sk_rest_req() ) );
	$GLOBALS['twd_stub']['user'] = 8;
	twd_sk_eq( true, TWD_SK_REST::can_write_page( twd_sk_rest_req() ) );
	$GLOBALS['twd_stub']['user'] = 7;
	// An old window is forgotten.
	$GLOBALS['twd_stub']['transients']['twd_sk_rl_7_write']['value']['start'] = time() - 61;
	twd_sk_eq( true, TWD_SK_REST::can_write_page( twd_sk_rest_req() ) );
} );

twd_sk_test( 'rest: prompt returns the same prompt as the CLI, with the stored page in it', function () {
	twd_sk_rest_setup();
	TWD_SK_Store::save( 12, '<p>Stored words here</p>' );
	$out = TWD_SK_REST::get_prompt( twd_sk_rest_req() );
	twd_sk_eq( TWD_SK_Prompt::build( '<p>Stored words here</p>' ), $out['prompt'] );
	twd_sk_has( 'tell them to ask their web designer', $out['prompt'] );
	twd_sk_eq( 1, $out['version'] );
	$empty = TWD_SK_REST::get_prompt( twd_sk_rest_req( array( 'id' => 12 ) ) );
	twd_sk_true( is_string( $empty['prompt'] ) );
} );

twd_sk_test( 'rest: preview cleans through the sanitiser, writes nothing to the page, and returns plain English', function () {
	twd_sk_rest_setup();
	$out = TWD_SK_REST::post_preview( twd_sk_rest_req( array( 'html' => '<p class="nope" onclick="x()">Heading here</p><script>bad()</script>' ) ) );
	twd_sk_true( is_array( $out ), 'no error' );
	twd_sk_true( 1 === preg_match( '/^[a-f0-9]{32}$/', $out['token'] ) );
	twd_sk_has( 'twd_sk_preview=' . $out['token'], $out['preview_url'] );
	twd_sk_true( $out['removed_total'] >= 3 );
	twd_sk_has( 'style name', implode( ' ', $out['report'] ) );
	twd_sk_eq( 1, $out['leftover_count'] );
	twd_sk_eq( 'Heading here', $out['leftovers'][0]['marker'] );
	// Nothing saved: no versions, no stored html.
	twd_sk_eq( '', TWD_SK_Store::get_current( 12 ) );
	twd_sk_eq( 0, TWD_SK_Store::get_current_version_id( 12 ) );
	twd_sk_eq( array(), twd_stub_meta_keys( 12 ) );
	// The stored draft is the cleaned HTML, never the raw paste.
	$draft = TWD_SK_Preview::read( $out['token'] );
	twd_sk_hasnt( 'script', $draft['html'] );
	twd_sk_hasnt( 'onclick', $draft['html'] );
} );

twd_sk_test( 'rest: preview refuses empty, non-string and oversize input, and input that cleans to nothing', function () {
	twd_sk_rest_setup();
	twd_sk_is_error( 'twd_sk_bad_input', TWD_SK_REST::post_preview( twd_sk_rest_req( array( 'html' => '   ' ) ) ) );
	twd_sk_is_error( 'twd_sk_bad_input', TWD_SK_REST::post_preview( twd_sk_rest_req( array( 'html' => array( 'x' ) ) ) ) );
	twd_sk_is_error( 'twd_sk_bad_input', TWD_SK_REST::post_preview( twd_sk_rest_req( array() ) ) );
	$e = TWD_SK_REST::post_preview( twd_sk_rest_req( array( 'html' => str_repeat( 'a', 200 * 1024 + 1 ) ) ) );
	twd_sk_is_error( 'twd_sk_too_large', $e );
	twd_sk_eq( 413, twd_sk_status( $e ) );
	twd_sk_is_error( 'twd_sk_empty', TWD_SK_REST::post_preview( twd_sk_rest_req( array( 'html' => '<script>x()</script>' ) ) ) );
} );

twd_sk_test( 'rest: discard removes only the caller\'s own preview', function () {
	twd_sk_rest_setup();
	$out = TWD_SK_REST::post_preview( twd_sk_rest_req( array( 'html' => '<p>Real words.</p>' ) ) );
	$GLOBALS['twd_stub']['user'] = 8;
	twd_sk_eq( array( 'discarded' => false ), TWD_SK_REST::post_discard( twd_sk_rest_req( array( 'token' => $out['token'] ) ) ) );
	twd_sk_true( null !== TWD_SK_Preview::read( $out['token'] ) );
	$GLOBALS['twd_stub']['user'] = 7;
	twd_sk_eq( array( 'discarded' => true ), TWD_SK_REST::post_discard( twd_sk_rest_req( array( 'token' => $out['token'] ) ) ) );
	twd_sk_eq( null, TWD_SK_Preview::read( $out['token'] ) );
	twd_sk_eq( array( 'discarded' => false ), TWD_SK_REST::post_discard( twd_sk_rest_req( array( 'token' => 'nope' ) ) ) );
} );

twd_sk_test( 'rest: apply saves through the store, cleaned, with the note, and returns the history', function () {
	twd_sk_rest_setup();
	$out = TWD_SK_REST::post_apply( twd_sk_rest_req( array(
		'html'         => '<p onclick="x()">Real words.</p><script>bad()</script>',
		'base_version' => 0,
		'note'         => 'From the therapist on our call',
	) ) );
	twd_sk_true( is_array( $out ), 'no error' );
	twd_sk_eq( 1, $out['version'] );
	twd_sk_eq( false, $out['unchanged'] );
	twd_sk_eq( 1, $out['current_version'] );
	twd_sk_eq( '<p>Real words.</p>', TWD_SK_Store::get_current( 12 ) );
	twd_sk_eq( 'From the therapist on our call', TWD_SK_Store::get_version( 12, 1 )['note'] );
	twd_sk_eq( 7, TWD_SK_Store::get_version( 12, 1 )['user'], 'the user id is recorded' );
	twd_sk_eq( 'Test Editor', $out['versions'][0]['by'] );
	twd_sk_eq( true, $out['versions'][0]['current'] );
	twd_sk_eq( 'From the therapist on our call', $out['versions'][0]['note'] );
	twd_sk_true( $out['report'] !== array() );
} );

twd_sk_test( 'rest: apply is allowed with leftover placeholders and reports their count', function () {
	twd_sk_rest_setup();
	$out = TWD_SK_REST::post_apply( twd_sk_rest_req( array( 'html' => '<p>Heading here and [PLACEHOLDER: fee]</p>', 'base_version' => 0 ) ) );
	twd_sk_true( is_array( $out ), 'saved, not refused' );
	twd_sk_eq( 2, $out['leftover_count'] );
	twd_sk_eq( '<p>Heading here and [PLACEHOLDER: fee]</p>', TWD_SK_Store::get_current( 12 ) );
} );

twd_sk_test( 'rest: apply needs a base version, and refuses a stale one with 409 without saving', function () {
	twd_sk_rest_setup();
	TWD_SK_Store::save( 12, '<p>One</p>' );
	TWD_SK_Store::save( 12, '<p>Two</p>' );
	twd_sk_is_error( 'twd_sk_bad_input', TWD_SK_REST::post_apply( twd_sk_rest_req( array( 'html' => '<p>Three</p>' ) ) ) );
	twd_sk_is_error( 'twd_sk_bad_input', TWD_SK_REST::post_apply( twd_sk_rest_req( array( 'html' => '<p>Three</p>', 'base_version' => 'abc' ) ) ) );
	$e = TWD_SK_REST::post_apply( twd_sk_rest_req( array( 'html' => '<p>Three</p>', 'base_version' => 1 ) ) );
	twd_sk_is_error( 'twd_sk_conflict', $e );
	twd_sk_eq( 409, twd_sk_status( $e ) );
	twd_sk_eq( '<p>Two</p>', TWD_SK_Store::get_current( 12 ) );
	twd_sk_is_error( 'twd_sk_bad_input', TWD_SK_REST::post_apply( twd_sk_rest_req( array( 'html' => '', 'base_version' => 2 ) ) ) );
} );

twd_sk_test( 'rest: apply refuses oversize and nothing-left input with a status', function () {
	twd_sk_rest_setup();
	$e = TWD_SK_REST::post_apply( twd_sk_rest_req( array( 'html' => str_repeat( 'a', 200 * 1024 + 1 ), 'base_version' => 0 ) ) );
	twd_sk_is_error( 'twd_sk_too_large', $e );
	twd_sk_eq( 413, twd_sk_status( $e ) );
	$e = TWD_SK_REST::post_apply( twd_sk_rest_req( array( 'html' => '<script>x()</script>', 'base_version' => 0 ) ) );
	twd_sk_is_error( 'twd_sk_empty', $e );
	twd_sk_eq( 400, twd_sk_status( $e ) );
	twd_sk_eq( 0, TWD_SK_Store::get_current_version_id( 12 ) );
} );

twd_sk_test( 'rest: undo and restore go through the store, are recorded as new versions, and need the base version', function () {
	twd_sk_rest_setup();
	TWD_SK_Store::save( 12, '<p>One</p>' );
	TWD_SK_Store::save( 12, '<p>Two</p>' );
	twd_sk_is_error( 'twd_sk_bad_input', TWD_SK_REST::post_undo( twd_sk_rest_req() ) );
	$out = TWD_SK_REST::post_undo( twd_sk_rest_req( array( 'base_version' => 2 ) ) );
	twd_sk_eq( 3, $out['version'] );
	twd_sk_eq( '<p>One</p>', TWD_SK_Store::get_current( 12 ) );
	twd_sk_eq( 'undo', $out['versions'][0]['kind'] );

	$out = TWD_SK_REST::post_restore( twd_sk_rest_req( array( 'version' => 2, 'base_version' => 3 ) ) );
	twd_sk_eq( 4, $out['version'] );
	twd_sk_eq( '<p>Two</p>', TWD_SK_Store::get_current( 12 ) );
	twd_sk_is_error( 'twd_sk_bad_input', TWD_SK_REST::post_restore( twd_sk_rest_req( array( 'base_version' => 4 ) ) ) );
	twd_sk_is_error( 'twd_sk_bad_input', TWD_SK_REST::post_restore( twd_sk_rest_req( array( 'version' => 0, 'base_version' => 4 ) ) ) );
	$e = TWD_SK_REST::post_restore( twd_sk_rest_req( array( 'version' => 99, 'base_version' => 4 ) ) );
	twd_sk_is_error( 'twd_sk_version_not_found', $e );
	twd_sk_eq( 400, twd_sk_status( $e ) );
	$e = TWD_SK_REST::post_restore( twd_sk_rest_req( array( 'version' => 1, 'base_version' => 1 ) ) );
	twd_sk_eq( 409, twd_sk_status( $e ) );
} );

twd_sk_test( 'rest: the history lists the last 10 versions newest first with who made them', function () {
	twd_sk_rest_setup();
	for ( $i = 1; $i <= 12; $i++ ) {
		TWD_SK_Store::save( 12, '<p>Version ' . $i . '</p>', array( 'note' => 'n' . $i ) );
	}
	$out = TWD_SK_REST::get_versions( twd_sk_rest_req() );
	twd_sk_eq( 12, $out['current_version'] );
	twd_sk_eq( 10, count( $out['versions'] ) );
	twd_sk_eq( 12, $out['versions'][0]['id'] );
	twd_sk_eq( 'n12', $out['versions'][0]['note'] );
	twd_sk_eq( 'Test Editor', $out['versions'][0]['by'] );
	twd_sk_eq( 3, $out['versions'][9]['id'] );
} );

twd_sk_test( 'rest: the REST class writes only through the store and calls nothing outside the site', function () {
	$src = file_get_contents( ABSPATH . 'includes/class-twd-sk-rest.php' );
	foreach ( array( 'update_post_meta', 'add_post_meta', 'delete_post_meta', 'wp_insert_post', 'wp_update_post', 'update_option', 'file_put_contents', 'wp_remote_', 'curl_', 'file_get_contents', '__return_true', '_twd_sk_html', '_twd_sk_versions' ) as $bad ) {
		twd_sk_hasnt( $bad, $src );
	}
	twd_sk_has( 'TWD_SK_Store::save', $src );
	twd_sk_has( 'TWD_SK_Store::undo', $src );
	twd_sk_has( 'TWD_SK_Store::restore', $src );
	twd_sk_hasnt( "\xE2\x80\x94", $src );
	twd_sk_hasnt( "\xE2\x80\x93", $src );
} );

twd_sk_test( 'rest: the preview banner data includes the newer markers with a plain meaning', function () {
	twd_sk_rest_setup();
	$out = TWD_SK_REST::post_preview( twd_sk_rest_req( array( 'html' => '<a href="/service-1">Read more about this approach</a><img src="/u/your-image.jpg" alt="x">' ) ) );
	twd_sk_eq( 3, $out['leftover_count'] );
	$markers = array();
	foreach ( $out['leftovers'] as $l ) {
		$markers[ $l['marker'] ] = $l['meaning'];
	}
	twd_sk_true( isset( $markers['/service-N'], $markers['Read more about this approach'], $markers['your-image'] ) );
	twd_sk_has( 'sample link to a service page', $markers['/service-N'] );
} );
