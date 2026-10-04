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
/** Every registered endpoint, one entry each (a route with a GET and a POST gives two). */
function twd_sk_rest_routes() {
	$GLOBALS['twd_stub']['routes'] = array();
	TWD_SK_REST::register_routes();
	$out = array();
	foreach ( $GLOBALS['twd_stub']['routes'] as $r ) {
		$list = isset( $r['args']['methods'] ) ? array( $r['args'] ) : $r['args'];
		foreach ( $list as $one ) {
			$out[] = array( 'ns' => $r['ns'], 'route' => $r['route'], 'args' => $one );
		}
	}
	return $out;
}
function twd_sk_status( $e ) {
	$d = $e->get_error_data();
	return is_array( $d ) && isset( $d['status'] ) ? $d['status'] : 0;
}

twd_sk_test( 'rest: the thirty-two routes exist under twd-site-kit/v1 and none is open to everyone', function () {
	$routes = twd_sk_rest_routes();
	twd_sk_eq( 32, count( $routes ) );
	foreach ( $routes as $r ) {
		twd_sk_eq( 'twd-site-kit/v1', $r['ns'] );
		twd_sk_true( is_array( $r['args']['permission_callback'] ) && 'TWD_SK_REST' === $r['args']['permission_callback'][0], 'a real permission callback on ' . $r['route'] );
		twd_sk_true( in_array( $r['args']['methods'], array( 'GET', 'POST' ), true ) );
		if ( 0 === strpos( $r['route'], '/pages/' ) && ! in_array( $r['route'], array( '/pages/generate', '/pages/recipe-prompt' ), true ) ) {
			twd_sk_has( '(?P<id>\\d+)', $r['route'] );
		}
	}
	$reads  = array();
	foreach ( $routes as $r ) {
		if ( 'GET' === $r['args']['methods'] ) {
			$reads[] = $r['route'];
		}
	}
	twd_sk_eq( 11, count( $reads ), 'only prompt, versions, info, the sections, the search details, the site state, the profile, the setup plan, the practice facts, the site brief and the templates are GET' );
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
	twd_sk_rest_setup( array( 'edit_pages', 'edit_post:12', 'publish_pages', 'publish_post:12' ) );
	foreach ( twd_sk_rest_routes() as $r ) {
		if ( false === strpos( $r['route'], '(?P<id>' ) ) {
			continue; // creating a page and the site settings have no page ID; they have their own tests
		}
		$cb = $r['args']['permission_callback'];
		twd_sk_eq( true, call_user_func( $cb, twd_sk_rest_req( array( 'id' => 12 ) ) ), 'allowed on 12 ' . $r['route'] );
		twd_sk_is_error( 'twd_sk_forbidden', call_user_func( $cb, twd_sk_rest_req( array( 'id' => 13 ) ) ), 'refused on 13 ' . $r['route'] );
	}
} );

twd_sk_test( 'rest security: creating a page needs edit_pages, and the nonce and sign-in like every other route', function () {
	twd_sk_rest_setup( array( 'edit_pages' ) );
	twd_sk_eq( true, TWD_SK_REST::can_create_page( twd_sk_rest_req() ) );
	twd_sk_is_error( 'twd_sk_bad_nonce', TWD_SK_REST::can_create_page( twd_sk_rest_req( array(), null ) ) );
	twd_sk_is_error( 'twd_sk_bad_nonce', TWD_SK_REST::can_create_page( twd_sk_rest_req( array(), 'wrong' ) ) );
	$GLOBALS['twd_stub']['user'] = 0;
	twd_sk_is_error( 'twd_sk_not_signed_in', TWD_SK_REST::can_create_page( twd_sk_rest_req() ) );
	$GLOBALS['twd_stub']['user'] = 7;
	foreach ( array( array(), array( 'read' ), array( 'edit_posts' ) ) as $caps ) {
		$GLOBALS['twd_stub']['caps'] = $caps;
		$e = TWD_SK_REST::can_create_page( twd_sk_rest_req() );
		twd_sk_is_error( 'twd_sk_forbidden', $e, implode( ',', $caps ) );
		twd_sk_eq( 403, twd_sk_status( $e ) );
	}
} );

twd_sk_test( 'rest security: publishing and unpublishing need publish rights on top of edit rights (403 for an author-level user)', function () {
	twd_sk_rest_setup( array( 'edit_pages', 'edit_post:12' ) );
	$e = TWD_SK_REST::can_publish_page( twd_sk_rest_req() );
	twd_sk_is_error( 'twd_sk_cannot_publish', $e );
	twd_sk_eq( 403, twd_sk_status( $e ) );
	twd_sk_rest_setup( array( 'edit_pages', 'edit_post:12', 'publish_pages' ) );
	twd_sk_is_error( 'twd_sk_cannot_publish', TWD_SK_REST::can_publish_page( twd_sk_rest_req() ) );
	twd_sk_rest_setup( array( 'edit_pages', 'edit_post:12', 'publish_pages', 'publish_post:12' ) );
	twd_sk_eq( true, TWD_SK_REST::can_publish_page( twd_sk_rest_req() ) );
	$GLOBALS['twd_stub']['user'] = 0;
	twd_sk_is_error( 'twd_sk_not_signed_in', TWD_SK_REST::can_publish_page( twd_sk_rest_req() ) );
	$GLOBALS['twd_stub']['user'] = 7;
	twd_sk_is_error( 'twd_sk_bad_nonce', TWD_SK_REST::can_publish_page( twd_sk_rest_req( array(), 'nope' ) ) );
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

// -- 0.3.1: new pages, publishing, template, the two leftover levels ----------------------

function twd_sk_status_caps() {
	twd_sk_rest_setup( array( 'edit_pages', 'edit_post:12', 'publish_pages', 'publish_post:12' ) );
}

twd_sk_test( 'rest: creating a page makes a DRAFT with the kit template and no content, whatever else is asked', function () {
	twd_sk_rest_setup( array( 'edit_pages' ) );
	$out = TWD_SK_REST::post_create_page( twd_sk_rest_req( array( 'title' => 'About me', 'starter' => 'blank', 'status' => 'publish', 'post_status' => 'publish' ) ) );
	twd_sk_true( is_array( $out ), 'created' );
	twd_sk_eq( 'draft', $out['status'] );
	twd_sk_eq( 'About me', $out['title'] );
	twd_sk_has( 'page_id=', $out['url'] );
	$post = get_post( $out['id'] );
	twd_sk_eq( 'draft', $post->post_status );
	twd_sk_eq( 'page', $post->post_type );
	twd_sk_eq( '', $post->post_content );
	twd_sk_eq( 7, $post->post_author );
	twd_sk_eq( 'twd-site-kit-page.php', get_post_meta( $out['id'], '_wp_page_template', true ) );
	twd_sk_eq( '', TWD_SK_Store::get_current( $out['id'] ), 'no kit html yet' );
	twd_sk_eq( 'draft', $GLOBALS['twd_stub']['inserted'][0]['post_status'] );
} );

twd_sk_test( 'rest: creating a page refuses a missing, empty, too long or non-text title and an unknown starter', function () {
	twd_sk_rest_setup( array( 'edit_pages' ) );
	foreach ( array( array(), array( 'title' => '' ), array( 'title' => '   ' ), array( 'title' => '<b></b>' ), array( 'title' => array( 'x' ) ), array( 'title' => str_repeat( 'a', 121 ) ), array( 'title' => 'Ok', 'starter' => 'home' ), array( 'title' => 'Ok', 'starter' => array( 'blank' ) ) ) as $params ) {
		$e = TWD_SK_REST::post_create_page( twd_sk_rest_req( $params ) );
		twd_sk_is_error( 'twd_sk_bad_input', $e, json_encode( $params ) );
		twd_sk_eq( 400, twd_sk_status( $e ) );
	}
	twd_sk_eq( array(), $GLOBALS['twd_stub']['inserted'], 'nothing was created' );
} );

twd_sk_test( 'rest: a page title is plain text, long dashes are removed, and a failed insert is reported', function () {
	twd_sk_rest_setup( array( 'edit_pages' ) );
	$out = TWD_SK_REST::post_create_page( twd_sk_rest_req( array( 'title' => '<script>x()</script>Hello <b>there</b> ' . "\xE2\x80\x94" . ' friend' ) ) );
	twd_sk_hasnt( '<', $out['title'] );
	twd_sk_hasnt( "\xE2\x80\x94", $out['title'] );
	$GLOBALS['twd_stub']['insert_fails'] = true;
	$e = TWD_SK_REST::post_create_page( twd_sk_rest_req( array( 'title' => 'Fine' ) ) );
	twd_sk_is_error( 'insert_failed', $e );
} );

twd_sk_test( 'rest: creating pages is limited to 10 an hour per user (429)', function () {
	twd_sk_rest_setup( array( 'edit_pages' ) );
	for ( $i = 0; $i < 10; $i++ ) {
		twd_sk_eq( true, TWD_SK_REST::can_create_page( twd_sk_rest_req() ), 'call ' . ( $i + 1 ) );
	}
	$e = TWD_SK_REST::can_create_page( twd_sk_rest_req() );
	twd_sk_is_error( 'twd_sk_rate_limited', $e );
	twd_sk_eq( 429, twd_sk_status( $e ) );
} );

twd_sk_test( 'rest: info reports status, template, readiness and the leftovers by level', function () {
	twd_sk_status_caps();
	TWD_SK_Store::save( 12, '<p>Heading here</p><a href="/c">Contact me about a first session</a>' );
	$out = TWD_SK_REST::get_info( twd_sk_rest_req() );
	twd_sk_eq( 12, $out['id'] );
	twd_sk_eq( 'publish', $out['status'] );
	twd_sk_eq( true, $out['is_kit_page'] );
	twd_sk_eq( false, $out['uses_template'] );
	twd_sk_eq( true, $out['has_content'] );
	twd_sk_eq( 1, $out['must_count'] );
	twd_sk_eq( 1, $out['check_count'] );
	twd_sk_eq( 2, $out['leftover_count'] );
	$levels = array();
	foreach ( $out['leftovers'] as $l ) {
		$levels[ $l['marker'] ] = $l['level'];
	}
	twd_sk_eq( array( 'Heading here' => 'must', 'Contact me about a first session' => 'check' ), $levels );
} );

twd_sk_test( 'rest: publishing needs a confirmation and a valid status', function () {
	twd_sk_status_caps();
	TWD_SK_Store::save( 12, '<p>Real words only.</p>' );
	get_post( 12 )->post_status = 'draft';
	twd_sk_is_error( 'twd_sk_confirm_needed', TWD_SK_REST::post_status( twd_sk_rest_req( array( 'status' => 'publish' ) ) ) );
	twd_sk_is_error( 'twd_sk_confirm_needed', TWD_SK_REST::post_status( twd_sk_rest_req( array( 'status' => 'publish', 'confirm' => false ) ) ) );
	twd_sk_is_error( 'twd_sk_confirm_needed', TWD_SK_REST::post_status( twd_sk_rest_req( array( 'status' => 'publish', 'confirm' => 'no' ) ) ) );
	foreach ( array( null, 'pending', 'private', 'trash', array( 'publish' ), '' ) as $bad ) {
		twd_sk_is_error( 'twd_sk_bad_input', TWD_SK_REST::post_status( twd_sk_rest_req( array( 'status' => $bad, 'confirm' => true ) ) ), json_encode( $bad ) );
	}
	twd_sk_eq( 'draft', get_post( 12 )->post_status, 'nothing changed' );
	$out = TWD_SK_REST::post_status( twd_sk_rest_req( array( 'status' => 'publish', 'confirm' => true ) ) );
	twd_sk_eq( 'publish', $out['status'] );
	twd_sk_eq( 'publish', get_post( 12 )->post_status );
} );

twd_sk_test( 'rest: publishing is BLOCKED while must-fix example text remains, unless explicitly overridden', function () {
	twd_sk_status_caps();
	TWD_SK_Store::save( 12, '<p>Heading here</p><img src="/u/your-image.jpg" alt="x">' );
	get_post( 12 )->post_status = 'draft';
	$e = TWD_SK_REST::post_status( twd_sk_rest_req( array( 'status' => 'publish', 'confirm' => true ) ) );
	twd_sk_is_error( 'twd_sk_leftovers', $e );
	twd_sk_eq( 409, twd_sk_status( $e ) );
	$markers = array();
	foreach ( $e->get_error_data()['leftovers'] as $l ) {
		$markers[] = $l['marker'];
	}
	twd_sk_true( in_array( 'Heading here', $markers, true ) && in_array( 'your-image', $markers, true ) );
	twd_sk_eq( 'draft', get_post( 12 )->post_status, 'still a draft' );
	// Anything but a clear yes is not an override.
	foreach ( array( false, 0, '0', 'no', 'false', null, '' ) as $no ) {
		twd_sk_is_error( 'twd_sk_leftovers', TWD_SK_REST::post_status( twd_sk_rest_req( array( 'status' => 'publish', 'confirm' => true, 'override_placeholders' => $no ) ) ), json_encode( $no ) );
	}
	twd_sk_eq( 'draft', get_post( 12 )->post_status );
	$out = TWD_SK_REST::post_status( twd_sk_rest_req( array( 'status' => 'publish', 'confirm' => true, 'override_placeholders' => true ) ) );
	twd_sk_eq( 'publish', $out['status'] );
} );

twd_sk_test( 'rest: every kind of must-fix marker blocks publishing, and the sample link wording never does', function () {
	twd_sk_status_caps();
	foreach ( TWD_SK_Sanitizer::leftover_markers() as $marker ) {
		$html = '/service-N' === $marker ? '<a href="/service-1">x</a>' : '<p>' . $marker . '</p>';
		TWD_SK_Store::save( 12, $html );
		get_post( 12 )->post_status = 'draft';
		$e = TWD_SK_REST::post_status( twd_sk_rest_req( array( 'status' => 'publish', 'confirm' => true ) ) );
		if ( 'check' === TWD_SK_Sanitizer::leftover_level( $marker ) ) {
			twd_sk_true( ! is_wp_error( $e ), 'check level must not block: ' . $marker );
			twd_sk_eq( 'publish', get_post( 12 )->post_status );
		} else {
			twd_sk_is_error( 'twd_sk_leftovers', $e, 'must fix blocks: ' . $marker );
			twd_sk_eq( 'draft', get_post( 12 )->post_status );
		}
	}
} );

twd_sk_test( 'rest: an empty page, a page that does not use the kit, and the front page are refused', function () {
	twd_sk_status_caps();
	get_post( 12 )->post_status = 'draft';
	twd_sk_is_error( 'twd_sk_empty_page', TWD_SK_REST::post_status( twd_sk_rest_req( array( 'status' => 'publish', 'confirm' => true ) ) ) );
	twd_stub_add_post( 30, 'page', 'Plain page' );
	$GLOBALS['twd_stub']['caps'] = array( 'edit_pages', 'edit_post:30', 'publish_pages', 'publish_post:30' );
	twd_sk_is_error( 'twd_sk_not_kit_page', TWD_SK_REST::post_status( twd_sk_rest_req( array( 'id' => 30, 'status' => 'publish', 'confirm' => true ) ) ) );
	twd_sk_status_caps();
	TWD_SK_Store::save( 12, '<p>Real words.</p>' );
	$GLOBALS['twd_stub']['options']['page_on_front'] = 12;
	$e = TWD_SK_REST::post_status( twd_sk_rest_req( array( 'status' => 'draft', 'confirm' => true ) ) );
	twd_sk_is_error( 'twd_sk_site_page', $e );
	twd_sk_eq( 409, twd_sk_status( $e ) );
	twd_sk_eq( 'publish', get_post( 12 )->post_status );
} );

twd_sk_test( 'rest: unpublishing sends a published kit page back to draft, with a confirmation', function () {
	twd_sk_status_caps();
	TWD_SK_Store::save( 12, '<p>Real words.</p>' );
	twd_sk_is_error( 'twd_sk_confirm_needed', TWD_SK_REST::post_status( twd_sk_rest_req( array( 'status' => 'draft' ) ) ) );
	$out = TWD_SK_REST::post_status( twd_sk_rest_req( array( 'status' => 'draft', 'confirm' => true ) ) );
	twd_sk_eq( 'draft', $out['status'] );
	twd_sk_eq( 'draft', get_post( 12 )->post_status );
	$updates = $GLOBALS['twd_stub']['updated'];
	twd_sk_eq( array( 'ID' => 12, 'post_status' => 'draft' ), $updates[0], 'only the status is changed' );
} );

twd_sk_test( 'rest: switching to the kit template needs a confirmation when other Elementor content would stop showing', function () {
	twd_sk_rest_setup();
	update_post_meta( 12, '_elementor_data', json_encode( array( array( 'elType' => 'section', 'elements' => array( array( 'elType' => 'column', 'elements' => array(
		array( 'elType' => 'widget', 'widgetType' => 'shortcode', 'settings' => array( 'shortcode' => '[twd_page]' ) ),
		array( 'elType' => 'widget', 'widgetType' => 'google_maps' ),
		array( 'elType' => 'widget', 'widgetType' => 'form' ),
		array( 'elType' => 'widget', 'widgetType' => 'spacer' ),
	) ) ) ) ) ) );
	$e = TWD_SK_REST::post_template( twd_sk_rest_req( array( 'use' => 'kit' ) ) );
	twd_sk_is_error( 'twd_sk_other_content', $e );
	twd_sk_eq( 409, twd_sk_status( $e ) );
	twd_sk_eq( array( 'google_maps', 'form' ), $e->get_error_data()['other_content'] );
	twd_sk_has( 'google_maps', $e->get_error_message() );
	twd_sk_eq( false, TWD_SK_Template::uses_template( 12 ), 'not switched' );
	$out = TWD_SK_REST::post_template( twd_sk_rest_req( array( 'use' => 'kit', 'confirm_other_content' => true ) ) );
	twd_sk_eq( true, $out['uses_template'] );
	twd_sk_eq( true, TWD_SK_Template::uses_template( 12 ) );
} );

twd_sk_test( 'rest: a plain [twd_page] page switches to the kit template and back, and bad requests are refused', function () {
	twd_sk_rest_setup();
	$out = TWD_SK_REST::post_template( twd_sk_rest_req( array( 'use' => 'kit' ) ) );
	twd_sk_eq( true, $out['uses_template'] );
	twd_sk_eq( 'twd-site-kit-page.php', get_post_meta( 12, '_wp_page_template', true ) );
	$out = TWD_SK_REST::post_template( twd_sk_rest_req( array( 'use' => 'default' ) ) );
	twd_sk_eq( false, $out['uses_template'] );
	twd_sk_eq( 'default', get_post_meta( 12, '_wp_page_template', true ) );
	foreach ( array( null, 'other', '', array( 'kit' ) ) as $bad ) {
		twd_sk_is_error( 'twd_sk_bad_input', TWD_SK_REST::post_template( twd_sk_rest_req( array( 'use' => $bad ) ) ) );
	}
	twd_stub_add_post( 31, 'page', 'No kit here' );
	$GLOBALS['twd_stub']['caps'] = array( 'edit_pages', 'edit_post:31' );
	$e = TWD_SK_REST::post_template( twd_sk_rest_req( array( 'id' => 31, 'use' => 'kit' ) ) );
	twd_sk_is_error( 'twd_sk_not_kit_page', $e );
	twd_sk_eq( 400, twd_sk_status( $e ) );
	twd_sk_eq( '', get_post_meta( 31, '_wp_page_template', true ) );
} );

twd_sk_test( 'rest: the preview and apply payloads carry the must and check counts', function () {
	twd_sk_rest_setup();
	$out = TWD_SK_REST::post_preview( twd_sk_rest_req( array( 'html' => '<p>Heading here</p><a href="/c">Find out about my services</a><a href="/d">Read more about this approach</a>' ) ) );
	twd_sk_eq( 3, $out['leftover_count'] );
	twd_sk_eq( 1, $out['must_count'] );
	twd_sk_eq( 2, $out['check_count'] );
	$app = TWD_SK_REST::post_apply( twd_sk_rest_req( array( 'html' => '<p>Heading here</p><a href="/c">Find out about my services</a>', 'base_version' => 0 ) ) );
	twd_sk_eq( 1, $app['must_count'] );
	twd_sk_eq( 1, $app['check_count'] );
} );

// -- 0.4.0: the Site tab routes ---------------------------------------------------------

function twd_sk_site_caps( $admin = true ) {
	twd_sk_rest_setup( $admin ? array( 'edit_pages', 'manage_options' ) : array( 'edit_pages', 'edit_post:12', 'publish_pages', 'publish_post:12' ) );
}
function twd_sk_site_routes() {
	$out = array();
	foreach ( twd_sk_rest_routes() as $r ) {
		if ( 0 === strpos( $r['route'], '/site' ) ) {
			$out[] = $r;
		}
	}
	return $out;
}

twd_sk_test( 'rest site: the site routes, each with a real permission callback, none registered in safe mode', function () {
	twd_sk_site_caps();
	$routes = twd_sk_site_routes();
	twd_sk_eq( 15, count( $routes ) );
	twd_sk_eq( array( '/site', '/site/style', '/site/style/reset', '/site/profile', '/site/profile', '/site/chrome', '/site/setup', '/site/setup', '/site/facts', '/site/facts', '/site/brief', '/site/brief/plan', '/site/brief/apply', '/site/propose', '/site/templates' ), array_map( function ( $r ) {
		return $r['route'];
	}, $routes ) );
	TWD_SK_Safe::set( true );
	twd_sk_eq( 0, count( twd_sk_site_routes() ), 'safe mode: no site routes' );
	twd_sk_true( count( twd_sk_rest_routes() ) >= 11, 'the 0.3.x routes stay' );
} );

twd_sk_test( 'rest site security: visitor, missing or wrong nonce are refused on every site route (401)', function () {
	twd_sk_site_caps();
	foreach ( twd_sk_site_routes() as $r ) {
		$cb = $r['args']['permission_callback'];
		$GLOBALS['twd_stub']['user'] = 0;
		twd_sk_is_error( 'twd_sk_not_signed_in', call_user_func( $cb, twd_sk_rest_req() ), $r['route'] );
		$GLOBALS['twd_stub']['user'] = 7;
		twd_sk_is_error( 'twd_sk_bad_nonce', call_user_func( $cb, twd_sk_rest_req( array(), null ) ), $r['route'] );
		twd_sk_is_error( 'twd_sk_bad_nonce', call_user_func( $cb, twd_sk_rest_req( array(), 'wrong' ) ), $r['route'] );
	}
} );

twd_sk_test( 'rest site security: an editor without manage_options is refused on every site route (403)', function () {
	foreach ( array( array(), array( 'read' ), array( 'edit_pages' ), array( 'edit_pages', 'publish_pages', 'edit_post:12', 'publish_post:12' ), array( 'edit_posts' ) ) as $caps ) {
		twd_sk_rest_setup( $caps );
		foreach ( twd_sk_site_routes() as $r ) {
			$e = call_user_func( $r['args']['permission_callback'], twd_sk_rest_req() );
			twd_sk_is_error( 'twd_sk_forbidden', $e, implode( ',', $caps ) . ' on ' . $r['route'] );
			twd_sk_eq( 403, twd_sk_status( $e ) );
			twd_sk_has( 'administrators', $e->get_error_message() );
		}
	}
	twd_sk_site_caps();
	foreach ( twd_sk_site_routes() as $r ) {
		twd_sk_eq( true, call_user_func( $r['args']['permission_callback'], twd_sk_rest_req() ), 'admin allowed on ' . $r['route'] );
	}
} );

twd_sk_test( 'rest site: writes are rate limited per user, and an oversize body is refused', function () {
	twd_sk_site_caps();
	for ( $i = 0; $i < 30; $i++ ) {
		twd_sk_eq( true, TWD_SK_REST::can_manage_site( twd_sk_rest_req() ) );
	}
	twd_sk_is_error( 'twd_sk_rate_limited', TWD_SK_REST::can_manage_site( twd_sk_rest_req() ) );
	twd_sk_site_caps();
	$GLOBALS['twd_stub']['transients'] = array();
	twd_sk_is_error( 'twd_sk_too_large', TWD_SK_REST::can_manage_site( twd_sk_rest_req( array(), 'good-nonce', str_repeat( 'a', 300 * 1024 + 1 ) ) ) );
} );

twd_sk_test( 'rest site: the state lists every pack with its tokens, the editable controls, and the contrast result', function () {
	twd_sk_site_caps();
	$out = TWD_SK_REST::get_site( twd_sk_rest_req() );
	twd_sk_eq( 'sage', $out['active'] );
	$slugs = array_map( function ( $p ) {
		return $p['slug'];
	}, $out['packs'] );
	twd_sk_eq( array( 'grove', 'sage' ), $slugs );
	twd_sk_true( isset( $out['packs'][0]['tokens']['color-primary'] ) );
	twd_sk_true( count( $out['editable'] ) >= 15 );
	twd_sk_true( in_array( 'Lora', $out['fonts'], true ) );
	twd_sk_eq( array(), $out['contrast']['blocking'], 'the shipped packs pass' );
	twd_sk_eq( array(), $out['contrast']['warnings'] );
} );

twd_sk_test( 'rest site: a pack switch applies the new pack and clears the old overrides', function () {
	twd_sk_site_caps();
	TWD_SK_REST::post_site_style( twd_sk_rest_req( array( 'tokens' => array( 'color-accent' => '#112233' ) ) ) );
	twd_sk_eq( array( 'color-accent' => '#112233' ), TWD_SK_Packs::overrides() );
	$out = TWD_SK_REST::post_site_style( twd_sk_rest_req( array( 'pack' => 'grove' ) ) );
	twd_sk_eq( 'grove', $out['active'] );
	twd_sk_eq( 'grove', TWD_SK_Packs::active_slug() );
	twd_sk_eq( array(), TWD_SK_Packs::overrides() );
	twd_sk_is_error( 'twd_sk_unknown_pack', TWD_SK_REST::post_site_style( twd_sk_rest_req( array( 'pack' => 'nope' ) ) ) );
	twd_sk_is_error( 'twd_sk_bad_input', TWD_SK_REST::post_site_style( twd_sk_rest_req( array( 'pack' => array( 'sage' ) ) ) ) );
	twd_sk_eq( 'grove', TWD_SK_Packs::active_slug(), 'a refused change changes nothing' );
} );

twd_sk_test( 'rest site: overrides are validated, limited to the editable tokens, and only values that differ from the pack are kept', function () {
	twd_sk_site_caps();
	$out = TWD_SK_REST::post_site_style( twd_sk_rest_req( array( 'tokens' => array( 'color-accent' => '#112233', 'radius-btn' => '4px', 'color-title' => '#3A3A3A' ) ) ) );
	twd_sk_true( ! is_wp_error( $out ) );
	$stored = TWD_SK_Packs::overrides();
	twd_sk_eq( '#112233', $stored['color-accent'] );
	twd_sk_eq( '4px', $stored['radius-btn'] );
	twd_sk_true( ! isset( $stored['color-title'] ), 'same as the pack, not stored' );
	twd_sk_eq( '#112233', $out['effective']['color-accent'] );
	foreach ( array(
		array( 'size-body' => '10px' ),
		array( 'color-accent' => 'red;background:url(x)' ),
		array( 'color-accent' => 'url(javascript:x)' ),
		array( 'radius-btn' => '-5px' ),
		array( 'radius-btn' => '50vw' ),
		array( 'font-body' => 'Comic Sans' ),
		array( 'color-nope' => '#fff' ),
		array( 'container' => '2000px' ),
	) as $bad ) {
		$before = TWD_SK_Packs::overrides();
		$e      = TWD_SK_REST::post_site_style( twd_sk_rest_req( array( 'tokens' => $bad ) ) );
		twd_sk_is_error( 'twd_sk_bad_tokens', $e, json_encode( $bad ) );
		twd_sk_eq( 400, twd_sk_status( $e ) );
		twd_sk_eq( $before, TWD_SK_Packs::overrides(), 'nothing saved for ' . json_encode( $bad ) );
	}
	twd_sk_is_error( 'twd_sk_bad_input', TWD_SK_REST::post_site_style( twd_sk_rest_req( array( 'tokens' => 'x' ) ) ) );
} );

twd_sk_test( 'rest site: a save that makes button or band text unreadable is BLOCKED (422) and nothing is stored', function () {
	twd_sk_site_caps();
	foreach ( array(
		array( 'color-on-primary' => '#CCCCCC' ),
		array( 'color-primary' => '#EEEEEE' ),
		array( 'color-primary-hover' => '#FFFFFF' ),
		array( 'color-on-band' => '#777777' ),
		array( 'color-band' => '#EEEEEE' ),
		array( 'color-accent-on-dark' => '#555555' ),
		array( 'color-title' => '#F0F0F0' ),
		array( 'color-surface' => '#222222' ),
	) as $bad ) {
		$e = TWD_SK_REST::post_site_style( twd_sk_rest_req( array( 'tokens' => $bad ) ) );
		twd_sk_is_error( 'twd_sk_contrast', $e, json_encode( $bad ) );
		twd_sk_eq( 422, twd_sk_status( $e ) );
		twd_sk_true( ! empty( $e->get_error_data()['contrast']['blocking'] ) );
		twd_sk_has( '4.5:1', $e->get_error_message() );
		twd_sk_eq( array(), TWD_SK_Packs::overrides(), 'nothing stored for ' . json_encode( $bad ) );
	}
} );

twd_sk_test( 'rest site: other weak pairs only warn and the save goes through', function () {
	twd_sk_site_caps();
	$out = TWD_SK_REST::post_site_style( twd_sk_rest_req( array( 'tokens' => array( 'color-body' => '#BBBBBB', 'color-eyebrow' => '#DDDDDD' ) ) ) );
	twd_sk_true( ! is_wp_error( $out ), 'saved' );
	twd_sk_eq( array(), $out['contrast']['blocking'] );
	$ids = array_map( function ( $w ) {
		return $w['id'];
	}, $out['contrast']['warnings'] );
	twd_sk_true( in_array( 'body_bg', $ids, true ) && in_array( 'eyebrow_bg', $ids, true ) );
	twd_sk_eq( '#BBBBBB', TWD_SK_Packs::overrides()['color-body'] );
} );

twd_sk_test( 'rest site: reset clears every override and keeps the pack', function () {
	twd_sk_site_caps();
	TWD_SK_REST::post_site_style( twd_sk_rest_req( array( 'pack' => 'grove' ) ) );
	TWD_SK_REST::post_site_style( twd_sk_rest_req( array( 'tokens' => array( 'color-accent' => '#112233' ) ) ) );
	$out = TWD_SK_REST::post_site_reset( twd_sk_rest_req() );
	twd_sk_eq( array(), TWD_SK_Packs::overrides() );
	twd_sk_eq( 'grove', $out['active'] );
	twd_sk_eq( array(), (array) $out['overrides'] );
	twd_sk_eq( TWD_SK_Packs::packs()['grove']['tokens']['color-accent'], $out['effective']['color-accent'] );
} );

twd_sk_test( 'rest site: the Site classes never touch page HTML, the page store, or the network', function () {
	foreach ( array( 'class-twd-sk-site.php', 'class-twd-sk-contrast.php' ) as $f ) {
		$src = file_get_contents( ABSPATH . 'includes/' . $f );
		foreach ( array( 'TWD_SK_Store', '_twd_sk_html', 'wp_remote_', 'file_put_contents', 'curl_', 'eval(' ) as $bad ) {
			twd_sk_hasnt( $bad, $src, $f );
		}
		twd_sk_hasnt( "\xE2\x80\x94", $src );
	}
} );

// -- 0.4.0: profile, header and footer routes ---------------------------------------------

twd_sk_test( 'rest site: profile, chrome and templates routes exist, admin only, and are not registered in safe mode', function () {
	twd_sk_site_caps();
	$routes = twd_sk_site_routes();
	$names  = array_map( function ( $r ) {
		return $r['route'];
	}, $routes );
	foreach ( array( '/site/profile', '/site/chrome', '/site/templates' ) as $r ) {
		twd_sk_true( in_array( $r, $names, true ), $r );
	}
	foreach ( $routes as $r ) {
		$GLOBALS['twd_stub']['caps'] = array( 'edit_pages', 'edit_post:12' );
		twd_sk_is_error( 'twd_sk_forbidden', call_user_func( $r['args']['permission_callback'], twd_sk_rest_req() ), $r['route'] );
	}
	TWD_SK_Safe::set( true );
	twd_sk_eq( 0, count( twd_sk_site_routes() ) );
} );

twd_sk_test( 'rest site: the profile saves through validation and returns the leftover check and what is missing', function () {
	twd_sk_site_caps();
	$out = TWD_SK_REST::get_site_profile( twd_sk_rest_req() );
	twd_sk_eq( 3, count( $out['missing'] ) );
	twd_sk_eq( 'bar', $out['chrome']['effective']['header'] );
	twd_sk_eq( array( 'bar', 'columns' ), array_values( $out['chrome']['pack_defaults'] ) );
	$out = TWD_SK_REST::post_site_profile( twd_sk_rest_req( array( 'profile' => TWD_SK_Profile::starter() ) ) );
	twd_sk_true( ! is_wp_error( $out ) );
	twd_sk_true( count( $out['leftovers']['must'] ) >= 3, 'the starter is flagged' );
	twd_sk_eq( '[PLACEHOLDER: practice name]', $out['profile']['site_name'] );
	$markers = array_map( function ( $l ) {
		return $l['marker'];
	}, $out['leftovers']['must'] );
	twd_sk_true( in_array( '[PLACEHOLDER', $markers, true ) && in_array( 'PHONE_NUMBER', $markers, true ) );
} );

twd_sk_test( 'rest site: a bad profile is refused with 400 and nothing changes', function () {
	twd_sk_site_caps();
	TWD_SK_REST::post_site_profile( twd_sk_rest_req( array( 'profile' => array( 'site_name' => 'Keep me' ) ) ) );
	foreach ( array( array( 'menu' => array( array( 'label' => 'x', 'url' => 'javascript:alert(1)' ) ) ), array( 'email' => 'nope' ), array( 'unknown' => 'x' ) ) as $bad ) {
		$e = TWD_SK_REST::post_site_profile( twd_sk_rest_req( array( 'profile' => $bad ) ) );
		twd_sk_is_error( 'twd_sk_bad_profile', $e, json_encode( $bad ) );
		twd_sk_eq( 400, twd_sk_status( $e ) );
	}
	twd_sk_eq( 'Keep me', TWD_SK_Profile::get()['site_name'] );
	twd_sk_is_error( 'twd_sk_bad_input', TWD_SK_REST::post_site_profile( twd_sk_rest_req( array( 'profile' => 'x' ) ) ) );
	twd_sk_is_error( 'twd_sk_bad_input', TWD_SK_REST::post_site_profile( twd_sk_rest_req( array() ) ) );
} );

twd_sk_test( 'rest site: header and footer settings are validated and applied', function () {
	twd_sk_site_caps();
	$out = TWD_SK_REST::post_site_chrome( twd_sk_rest_req( array( 'settings' => array( 'header_variant' => 'split', 'footer_variant' => 'band', 'sticky' => true, 'footer_columns' => 2 ) ) ) );
	twd_sk_eq( 'split', $out['chrome']['effective']['header'] );
	twd_sk_eq( true, $out['chrome']['effective']['sticky'] );
	twd_sk_eq( 2, $out['chrome']['effective']['columns'] );
	$e = TWD_SK_REST::post_site_chrome( twd_sk_rest_req( array( 'settings' => array( 'header_variant' => 'wild' ) ) ) );
	twd_sk_is_error( 'twd_sk_bad_chrome', $e );
	twd_sk_eq( 400, twd_sk_status( $e ) );
	twd_sk_is_error( 'twd_sk_bad_input', TWD_SK_REST::post_site_chrome( twd_sk_rest_req( array( 'settings' => 'x' ) ) ) );
} );

twd_sk_test( 'rest site: the Theme Builder templates are served as JSON text', function () {
	twd_sk_site_caps();
	$out = TWD_SK_REST::get_site_templates( twd_sk_rest_req() );
	twd_sk_eq( array( 'twd-header.json', 'twd-footer.json' ), array_keys( $out['files'] ) );
	twd_sk_has( '[twd_header]', $out['files']['twd-header.json'] );
	twd_sk_has( '[twd_footer]', $out['files']['twd-footer.json'] );
} );
