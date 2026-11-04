<?php
// The administrator's Snag button (0.15.0, section 24): the Snag list kept in one option, what is cleaned and
// scrubbed before anything is stored, the limits, the snags.md export, the routes and who may call them, who is
// given the script, the command line, and the script itself. Separate from Tell us (test-snags.php).

function twd_sk_sb_admin() {
	twd_stub_add_post( 12, 'page', '[twd_page]' );
	$GLOBALS['twd_stub']['caps']     = array( 'edit_pages', 'edit_post:12', 'manage_options' );
	$GLOBALS['twd_stub']['user']     = 1;
	$GLOBALS['twd_stub']['admin']    = false;
	$GLOBALS['twd_stub']['queried']  = 12;
	$GLOBALS['twd_stub']['post_id']  = 12;
	$GLOBALS['twd_stub']['singular'] = true;
	TWD_SK_Preview::reset();
}
function twd_sk_sb_req( $params = array(), $nonce = 'good-nonce' ) {
	$headers = null === $nonce ? array() : array( 'X-WP-Nonce' => $nonce );
	return new WP_REST_Request( $params, $headers );
}
/** The Snag routes as { "route METHOD" => args }. */
function twd_sk_sb_routes() {
	$GLOBALS['twd_stub']['routes'] = array();
	TWD_SK_REST::register_routes();
	$out = array();
	foreach ( $GLOBALS['twd_stub']['routes'] as $r ) {
		if ( 0 !== strpos( $r['route'], '/site/snagbook' ) ) {
			continue;
		}
		$list = isset( $r['args']['methods'] ) ? array( $r['args'] ) : $r['args'];
		foreach ( $list as $a ) {
			$out[ $r['route'] . ' ' . $a['methods'] ] = $a;
		}
	}
	return $out;
}
function twd_sk_sb_call( $route, $method, $params = array() ) {
	$routes = twd_sk_sb_routes();
	$a      = $routes[ $route . ' ' . $method ];
	$req    = twd_sk_sb_req( $params );
	$perm   = call_user_func( $a['permission_callback'], $req );
	if ( true !== $perm ) {
		return $perm;
	}
	return call_user_func( $a['callback'], $req );
}
function twd_sk_sb_js() {
	return file_get_contents( ABSPATH . 'assets/twd-site-kit-editor-snag.js' );
}
function twd_sk_sb_events( $n ) {
	$events = array();
	for ( $i = 1; $i <= $n; $i++ ) {
		$events[] = array( 't' => $i * 1.5, 'k' => 'press', 'w' => 'Button ' . $i );
	}
	return $events;
}

twd_sk_test( 'snagbook: the line is cleaned: tags, long dashes and control characters go, it is one line, and it is cut to the limit', function () {
	$out = TWD_SK_Snagbook::text( "<b>The</b> Look panel \xE2\x80\x94 does not\x07 close\n\nwhen I press Cancel", 500 );
	twd_sk_hasnt( '<b>', $out );
	twd_sk_hasnt( "\xE2\x80\x94", $out );
	twd_sk_hasnt( "\x07", $out );
	twd_sk_hasnt( "\n", $out );
	twd_sk_has( 'Look panel', $out );
	twd_sk_eq( 20, strlen( TWD_SK_Snagbook::text( str_repeat( 'a', 100 ), 20 ) ) );
	twd_sk_eq( '', TWD_SK_Snagbook::text( array( 'x' ), 20 ) );
	twd_sk_eq( 'yes', TWD_SK_Snagbook::text( true, 20 ) );
	twd_sk_has( "\n", TWD_SK_Snagbook::text( "one\ntwo", 50, true ), 'lines kept when asked' );
} );

twd_sk_test( 'snagbook: anything that looks like a secret is taken out: passwords, keys, tokens, nonces in an address, a sign-in cookie, a long hex string', function () {
	foreach ( array(
		'my password: hunter2 was refused',
		'password=hunter2',
		'api key = sk-abc123 here',
		'the token abc123def was shown',
		'Authorization: Bearer abc.def.ghi',
		'cookie: wordpress_logged_in_x=abc|def',
	) as $in ) {
		$out = TWD_SK_Snagbook::scrub( $in );
		twd_sk_has( '(removed)', $out, $in );
		twd_sk_hasnt( 'hunter2', $out, $in );
		twd_sk_hasnt( 'abc123', $out, $in );
		twd_sk_hasnt( 'abc.def.ghi', $out, $in );
	}
	$addr = TWD_SK_Snagbook::scrub( 'page?twd_sk_preview=aaaabbbbccccdddd&x=1 and /x?_wpnonce=1234abcd' );
	twd_sk_hasnt( 'aaaabbbbccccdddd', $addr );
	twd_sk_hasnt( '1234abcd', $addr );
	twd_sk_has( 'x=1', $addr, 'the rest of the address stays' );
	twd_sk_has( '(removed)', TWD_SK_Snagbook::scrub( 'id ' . str_repeat( 'a1', 20 ) . ' end' ), 'a long hex string' );
	twd_sk_eq( 'Change the heading on the Home page.', TWD_SK_Snagbook::scrub( 'Change the heading on the Home page.' ), 'plain words untouched' );
	// The same scrub runs on every stored text, so the line and the code texts are covered too.
	twd_sk_hasnt( 'hunter2', TWD_SK_Snagbook::text( 'password: hunter2', 100 ) );
	twd_sk_hasnt( 'hunter2', TWD_SK_Snagbook::code_text( '<p>token hunter2</p>', 100 ) );
	twd_sk_hasnt( 'hunter2', TWD_SK_Snagbook::event_text( 'route /x?token=hunter2', 100 ) );
} );

twd_sk_test( 'snagbook: the lead-up record keeps the last 30 events, each short; the context keeps known keys only', function () {
	$events = TWD_SK_Snagbook::clean_events( twd_sk_sb_events( 45 ) );
	twd_sk_eq( 30, count( $events ) );
	twd_sk_eq( 'Button 45', $events[29]['w'], 'the newest are kept' );
	twd_sk_eq( 'Button 16', $events[0]['w'] );
	twd_sk_eq( array(), TWD_SK_Snagbook::clean_events( 'not a list' ) );
	$long = TWD_SK_Snagbook::clean_events( array( array( 't' => 1, 'k' => 'press', 'w' => str_repeat( 'w', 900 ) ), 'x', array( 'k' => '', 'w' => '' ) ) );
	twd_sk_eq( 1, count( $long ), 'an empty event and a stray value are dropped' );
	twd_sk_eq( TWD_SK_Snagbook::MAX_EVENT, strlen( $long[0]['w'] ) );
	$ctx = TWD_SK_Snagbook::clean_context( array( 'page_id' => 12, 'page_title' => 'Home', 'cookie_jar' => 'x', 'width' => 1280, 'errors' => array_fill( 0, 25, 'TypeError: x is not a function' ) ) );
	twd_sk_eq( '12', $ctx['page_id'] );
	twd_sk_true( ! isset( $ctx['cookie_jar'] ), 'an unknown key is dropped' );
	twd_sk_eq( 10, count( $ctx['errors'] ), 'at most ten script errors' );
	twd_sk_eq( array(), TWD_SK_Snagbook::clean_context( 'x' ) );
} );

twd_sk_test( 'snagbook: a snag is saved with its line, category, build, site name, user and cleaned record; a line under three letters is refused', function () {
	$snag = TWD_SK_Snagbook::create( array(
		'text'     => 'The Look panel does not close.',
		'category' => 'fault',
		'events'   => twd_sk_sb_events( 3 ),
		'context'  => array( 'page_id' => 12, 'page_title' => 'Home', 'section' => 2, 'section_kind' => 'text', 'page_version' => 4, 'view' => 'Administrator' ),
	), 1 );
	twd_sk_true( is_array( $snag ) );
	twd_sk_eq( 1, $snag['id'] );
	twd_sk_eq( 'open', $snag['status'] );
	twd_sk_eq( 'fault', $snag['category'] );
	twd_sk_eq( 1, $snag['user_id'] );
	twd_sk_eq( 0, $snag['exported'] );
	twd_sk_eq( 'example.test', $snag['host'], 'the site name only, never a full address' );
	twd_sk_eq( TWD_SK_VERSION, $snag['build'] );
	twd_sk_eq( 3, count( $snag['events'] ) );
	twd_sk_true( ! isset( $snag['preview_html'] ), 'no included texts unless asked' );
	twd_sk_eq( $snag, TWD_SK_Snagbook::get( 1 ) );
	twd_sk_is_error( 'twd_sk_snag_empty', TWD_SK_Snagbook::create( array( 'text' => 'hi' ), 1 ) );
	twd_sk_is_error( 'twd_sk_snag_empty', TWD_SK_Snagbook::create( array(), 1 ) );
	twd_sk_eq( 2, TWD_SK_Snagbook::create( array( 'text' => 'Wanted: a way to reorder the menu.', 'category' => 'nonsense' ), 1 )['id'] );
	twd_sk_eq( 'fault', TWD_SK_Snagbook::get( 2 )['category'], 'an unknown category becomes a fault' );
	twd_sk_eq( 'wanted', TWD_SK_Snagbook::create( array( 'text' => 'Please add a Redo button.', 'category' => 'WANTED' ), 1 )['category'] );
	twd_sk_eq( TWD_SK_Snagbook::MAX_LINE, strlen( TWD_SK_Snagbook::create( array( 'text' => str_repeat( 'a', 2000 ) ), 1 )['text'] ) );
} );

twd_sk_test( 'snagbook: the last Preview and the What changed note are stored only when the person ticks the box, and are scrubbed', function () {
	$in = array( 'text' => 'The preview shows the wrong heading.', 'preview_html' => '<section><p>token hunter2</p></section>', 'preview_css' => '.c-a { color: red; }', 'what_changed' => 'Heading reworded.' );
	$without = TWD_SK_Snagbook::create( $in, 1 );
	twd_sk_true( ! isset( $without['preview_html'] ) && ! isset( $without['preview_css'] ) && ! isset( $without['what_changed'] ) );
	$with = TWD_SK_Snagbook::create( $in + array( 'include' => true ), 1 );
	twd_sk_has( '<section>', $with['preview_html'] );
	twd_sk_hasnt( 'hunter2', $with['preview_html'] );
	twd_sk_eq( 'Heading reworded.', $with['what_changed'] );
	$huge = TWD_SK_Snagbook::create( array( 'text' => 'A huge preview.', 'include' => true, 'preview_html' => str_repeat( 'x', 50000 ) ), 1 );
	twd_sk_eq( TWD_SK_Snagbook::MAX_TEXT, strlen( $huge['preview_html'] ) );
} );

twd_sk_test( 'snagbook: the list is capped at 200, newest first; numbers are never reused', function () {
	for ( $i = 1; $i <= 205; $i++ ) {
		TWD_SK_Snagbook::create( array( 'text' => 'Snag number ' . $i ), 1 );
	}
	$all = TWD_SK_Snagbook::find();
	twd_sk_eq( 200, count( $all ) );
	twd_sk_eq( 205, $all[0]['id'], 'newest first' );
	twd_sk_eq( 6, $all[199]['id'], 'the oldest five went' );
	twd_sk_true( null === TWD_SK_Snagbook::get( 5 ) );
	twd_sk_eq( 206, TWD_SK_Snagbook::create( array( 'text' => 'Another one.' ), 1 )['id'] );
	twd_sk_eq( 200, TWD_SK_Snagbook::counts()['total'] );
} );

twd_sk_test( 'snagbook: status, exported marks, find filters, counts and delete', function () {
	foreach ( array( 'First snag here.', 'Second snag here.', 'Third snag here.' ) as $t ) {
		TWD_SK_Snagbook::create( array( 'text' => $t ), 1 );
	}
	twd_sk_eq( 'done', TWD_SK_Snagbook::set_status( 1, 'done' )['status'] );
	twd_sk_is_error( 'twd_sk_snag_bad_status', TWD_SK_Snagbook::set_status( 1, 'parked' ) );
	twd_sk_is_error( 'twd_sk_snag_missing', TWD_SK_Snagbook::set_status( 99, 'done' ) );
	twd_sk_eq( array( 3, 2 ), array_map( function ( $s ) {
		return $s['id'];
	}, TWD_SK_Snagbook::find( array( 'status' => 'open' ) ) ) );
	twd_sk_eq( 1, count( TWD_SK_Snagbook::find( array( 'status' => 'done' ) ) ) );
	twd_sk_eq( 2, TWD_SK_Snagbook::mark_exported( array( 2, 3, 99 ) ), 'two marked, a missing number ignored' );
	twd_sk_eq( 0, TWD_SK_Snagbook::mark_exported( array( 2, 3 ) ), 'marking twice changes nothing' );
	twd_sk_eq( array( 1 ), array_map( function ( $s ) {
		return $s['id'];
	}, TWD_SK_Snagbook::find( array( 'unexported' => true ) ) ) );
	twd_sk_eq( array( 'open' => 2, 'unexported' => 1, 'total' => 3, 'cap' => 200 ), TWD_SK_Snagbook::counts() );
	twd_sk_true( true === TWD_SK_Snagbook::delete( 2 ) );
	twd_sk_true( false === TWD_SK_Snagbook::delete( 2 ), 'a second delete finds nothing' );
	twd_sk_eq( 2, TWD_SK_Snagbook::counts()['total'] );
} );

twd_sk_test( 'snagbook: the list lives in one option that is not loaded on every page, and a damaged option reads as an empty list', function () {
	$src = file_get_contents( ABSPATH . 'includes/class-twd-sk-snagbook.php' );
	twd_sk_has( "update_option( self::OPT, \$list, 'no' )", $src );
	twd_sk_has( "update_option( self::OPT_NEXT, \$next, 'no' )", $src );
	$GLOBALS['twd_stub']['options'][ TWD_SK_Snagbook::OPT ] = 'garbage';
	twd_sk_eq( array(), TWD_SK_Snagbook::all() );
	$GLOBALS['twd_stub']['options'][ TWD_SK_Snagbook::OPT ] = array( 'x', array( 'id' => 1 ) );
	twd_sk_eq( 1, count( TWD_SK_Snagbook::all() ), 'a stray value is skipped' );
} );

twd_sk_test( 'snagbook: the snags.md line has the shape "- DATE | PAGE | Category: text (snag N, build, page version, section)"; a pipe in the words cannot break it', function () {
	$s = TWD_SK_Snagbook::create( array(
		'text'    => 'Cancel | Throw away leaves the outline.',
		'context' => array( 'page_title' => 'Home | main', 'page_version' => 7, 'section' => 3, 'section_kind' => 'hero' ),
	), 1 );
	$line = TWD_SK_Snagbook::line( $s );
	twd_sk_eq( 1, preg_match( '/^- \d{4}-\d{2}-\d{2} \| Home \/ main \| Fault: Cancel \/ Throw away leaves the outline\. \(snag 1, build ' . preg_quote( TWD_SK_VERSION, '/' ) . ', page version 7, section 3 hero\)$/', $line ), $line );
	twd_sk_eq( 2, substr_count( $line, ' | ' ), 'exactly the two separators, none from the words' );
} );

twd_sk_test( 'snagbook: a short snag exports as a line with its detail indented under it; a long one gets a pointer and its own file', function () {
	$short = TWD_SK_Snagbook::create( array( 'text' => 'A short one.', 'events' => twd_sk_sb_events( 4 ), 'context' => array( 'page_title' => 'Home' ) ), 1 );
	$e     = TWD_SK_Snagbook::export_entry( $short, 'Client-Name' );
	twd_sk_true( false === $e['long'] );
	twd_sk_eq( '', $e['file'] );
	twd_sk_has( "  - Page: Home\n", $e['block'] );
	twd_sk_has( '  - Lead-up: -', $e['block'] );
	$long = TWD_SK_Snagbook::create( array( 'text' => 'A long one.', 'events' => twd_sk_sb_events( 25 ), 'context' => array( 'page_title' => 'Home' ), 'include' => true, 'preview_html' => '<p>```</p>', 'what_changed' => 'A note.' ), 1 );
	$e2    = TWD_SK_Snagbook::export_entry( $long, 'Client Name!' );
	twd_sk_true( true === $e2['long'] );
	twd_sk_eq( 'clients/clientname/snags/' . $long['id'] . '.md', $e2['path'], 'the site name is reduced to letters, digits and hyphens' );
	twd_sk_has( '(detail: clients/clientname/snags/' . $long['id'] . '.md)', $e2['line'] );
	twd_sk_has( '# Snag ' . $long['id'], $e2['file'] );
	twd_sk_has( 'Lead-up (seconds before Snag was pressed)', $e2['file'] );
	twd_sk_hasnt( "<p>```</p>", $e2['file'], 'a code fence inside the text cannot close the fence early' );
	$md = TWD_SK_Snagbook::export_markdown( array( $long, $short ), 'client-name' );
	twd_sk_true( strpos( $md, 'A long one.' ) < strpos( $md, 'A short one.' ), 'the snags keep the order they were given' );
	twd_sk_has( '<!-- snag-file clients/client-name/snags/' . $long['id'] . '.md -->', $md );
	twd_sk_true( 1 !== preg_match( '#https?://#i', $md ), 'no web address in the export' );
} );

twd_sk_test( 'snagbook rest: three endpoints, each with a permission check; a visitor, a missing nonce and an editor are refused; an administrator is let in', function () {
	twd_sk_sb_admin();
	$routes = twd_sk_sb_routes();
	twd_sk_eq( array( '/site/snagbook GET', '/site/snagbook POST', '/site/snagbook/(?P<sid>\\d+)/status POST' ), array_keys( $routes ) );
	foreach ( $routes as $name => $a ) {
		twd_sk_true( isset( $a['permission_callback'] ) && is_callable( $a['permission_callback'] ), $name );
		$GLOBALS['twd_stub']['user'] = 0;
		twd_sk_is_error( 'twd_sk_not_signed_in', call_user_func( $a['permission_callback'], twd_sk_sb_req() ), $name );
		$GLOBALS['twd_stub']['user'] = 7;
		twd_sk_is_error( 'twd_sk_bad_nonce', call_user_func( $a['permission_callback'], twd_sk_sb_req( array(), null ) ), $name );
		$GLOBALS['twd_stub']['caps'] = array( 'edit_pages', 'edit_post:12', 'publish_pages' );
		twd_sk_is_error( 'twd_sk_forbidden', call_user_func( $a['permission_callback'], twd_sk_sb_req() ), 'an editor on ' . $name );
		$GLOBALS['twd_stub']['caps'] = array( 'edit_pages', 'edit_post:12', 'manage_options' );
		twd_sk_eq( true, call_user_func( $a['permission_callback'], twd_sk_sb_req() ), 'an administrator on ' . $name );
	}
	TWD_SK_Safe::set( true );
	twd_sk_eq( array(), twd_sk_sb_routes(), 'safe mode: no Snag routes' );
} );

twd_sk_test( 'snagbook rest: save a snag, list them, read one with its included texts, change its status', function () {
	twd_sk_sb_admin();
	$out = twd_sk_sb_call( '/site/snagbook', 'POST', array( 'text' => 'The Look panel does not close.', 'category' => 'fault', 'events' => twd_sk_sb_events( 5 ), 'context' => array( 'page_id' => 12, 'page_title' => 'Home' ), 'include' => true, 'preview_html' => '<p>Hi</p>', 'what_changed' => 'A note.' ) );
	twd_sk_eq( 1, $out['saved']['id'] );
	twd_sk_eq( 1, $out['counts']['open'] );
	twd_sk_true( true === $out['saved']['has_texts'] );
	twd_sk_true( ! isset( $out['saved']['preview_html'] ), 'the answer to a save carries no texts' );
	twd_sk_eq( 1, TWD_SK_Snagbook::get( 1 )['user_id'], 'the signed-in user is recorded' );
	$err = twd_sk_sb_call( '/site/snagbook', 'POST', array( 'text' => 'no' ) );
	twd_sk_is_error( 'twd_sk_snag_empty', $err );
	twd_sk_eq( 400, twd_sk_status( $err ) );
	twd_sk_sb_call( '/site/snagbook', 'POST', array( 'text' => 'A second snag here.' ) );
	$list = twd_sk_sb_call( '/site/snagbook', 'GET' );
	twd_sk_eq( 2, count( $list['snags'] ) );
	twd_sk_eq( 2, $list['snags'][0]['id'], 'newest first' );
	twd_sk_true( ! isset( $list['snags'][1]['preview_html'] ), 'texts are left out of the list' );
	twd_sk_eq( array( 'fault', 'wanted', 'question' ), array_keys( $list['categories'] ) );
	$one = twd_sk_sb_call( '/site/snagbook', 'GET', array( 'id' => 1 ) );
	$row = $one['snags'][1];
	twd_sk_eq( '<p>Hi</p>', $row['preview_html'], 'the one named by id comes with its texts' );
	twd_sk_eq( 5, count( $row['record'] ) );
	$done = twd_sk_sb_call( '/site/snagbook/(?P<sid>\\d+)/status', 'POST', array( 'sid' => 1, 'status' => 'done' ) );
	twd_sk_eq( 'done', $done['saved']['status'] );
	twd_sk_eq( 1, $done['counts']['open'] );
	$bad = twd_sk_sb_call( '/site/snagbook/(?P<sid>\\d+)/status', 'POST', array( 'sid' => 1, 'status' => 'parked' ) );
	twd_sk_is_error( 'twd_sk_snag_bad_status', $bad );
	twd_sk_eq( 400, twd_sk_status( $bad ) );
	$gone = twd_sk_sb_call( '/site/snagbook/(?P<sid>\\d+)/status', 'POST', array( 'sid' => 99, 'status' => 'done' ) );
	twd_sk_is_error( 'twd_sk_snag_missing', $gone );
	twd_sk_eq( 404, twd_sk_status( $gone ) );
} );

twd_sk_test( 'snagbook: the script is given to administrators only, never to an editor or a visitor, and never in safe mode', function () {
	twd_sk_sb_admin();
	TWD_SK_Editor::enqueue();
	twd_sk_true( ! empty( $GLOBALS['twd_stub']['scripts']['twd-site-kit-editor-snag']['enqueued'] ), 'administrator' );
	$data = $GLOBALS['twd_stub']['scripts']['twd-site-kit-editor-snag']['data'];
	twd_sk_eq( TWD_SK_Snagbook::MAX_LINE, $data['maxLine'] );
	twd_sk_eq( 'Alt+Shift+S', $data['shortcut'] );
	twd_sk_hasnt( 'good-nonce', json_encode( $data ), 'the nonce comes from the editor script, not from this one' );
	twd_stub_reset();
	twd_sk_sb_admin();
	$GLOBALS['twd_stub']['caps'] = array( 'edit_pages', 'edit_post:12' );
	TWD_SK_Editor::enqueue();
	twd_sk_true( empty( $GLOBALS['twd_stub']['scripts']['twd-site-kit-editor-snag']['enqueued'] ), 'an editor' );
	twd_stub_reset();
	twd_sk_sb_admin();
	TWD_SK_Safe::set( true );
	TWD_SK_Editor::enqueue();
	twd_sk_true( empty( $GLOBALS['twd_stub']['scripts']['twd-site-kit-editor-snag']['enqueued'] ), 'safe mode' );
} );

twd_sk_test( 'snagbook: the class is loaded by the main file and the test bootstrap, and is not started by anything (it only answers calls)', function () {
	$main = file_get_contents( ABSPATH . 'twd-site-kit.php' );
	twd_sk_has( "includes/class-twd-sk-snagbook.php'", $main );
	twd_sk_hasnt( 'TWD_SK_Snagbook::init', $main );
	$src = file_get_contents( ABSPATH . 'includes/class-twd-sk-snagbook.php' );
	foreach ( array( 'add_action', 'add_filter', 'register_rest_route', 'current_user_can', 'wp_remote_', 'wp_mail', 'file_put_contents', 'curl_' ) as $bad ) {
		twd_sk_hasnt( $bad, $src, 'the class never ' . $bad );
	}
	twd_sk_true( 1 !== preg_match( '#https?://#i', $src ), 'no web address in the class' );
	twd_sk_true( 1 !== preg_match( '/\xE2\x80[\x93\x94]/', $src ), 'no long dashes in the class' );
} );

twd_sk_test( 'snagbook cli: list, show, set a status, export in the snags.md shape and mark exported; a wrong first word is refused', function () {
	require_once __DIR__ . '/stub-wpcli.php';
	TWD_SK_Snagbook::create( array( 'text' => 'First snag on the Home page.', 'context' => array( 'page_title' => 'Home' ), 'events' => twd_sk_sb_events( 2 ) ), 1 );
	TWD_SK_Snagbook::create( array( 'text' => 'Second snag, handled later.' ), 1 );
	WP_CLI::reset();
	( new TWD_SK_CLI() )->snags( array( 'list' ), array() );
	$rows = json_decode( preg_replace( '/^line: /', '', WP_CLI::all() ), true );
	twd_sk_eq( 2, count( $rows['snags'] ) );
	twd_sk_eq( 2, $rows['counts']['open'] );
	WP_CLI::reset();
	( new TWD_SK_CLI() )->snags( array( 'show', '1' ), array( 'format' => 'md' ) );
	twd_sk_has( '# Snag 1', WP_CLI::all() );
	twd_sk_has( 'Lead-up (seconds before Snag was pressed)', WP_CLI::all() );
	WP_CLI::reset();
	( new TWD_SK_CLI() )->snags( array( 'done', '2' ), array() );
	twd_sk_eq( 'done', TWD_SK_Snagbook::get( 2 )['status'] );
	WP_CLI::reset();
	( new TWD_SK_CLI() )->snags( array( 'export' ), array( 'site' => 'client-name', 'mark' => true ) );
	twd_sk_has( 'Home | Fault: First snag on the Home page.', WP_CLI::all() );
	twd_sk_hasnt( 'Second snag', WP_CLI::all(), 'a done snag is not exported by default' );
	twd_sk_has( '1 snag marked exported', WP_CLI::all() );
	twd_sk_true( ! empty( TWD_SK_Snagbook::get( 1 )['exported'] ) );
	WP_CLI::reset();
	( new TWD_SK_CLI() )->snags( array( 'export' ), array() );
	twd_sk_has( 'Nothing to export.', WP_CLI::all(), 'already exported, so nothing again' );
	WP_CLI::reset();
	( new TWD_SK_CLI() )->snags( array( 'export' ), array( 'all' => true, 'format' => 'json', 'site' => 'client-name' ) );
	$exp = json_decode( preg_replace( '/^line: /', '', WP_CLI::all() ), true );
	twd_sk_eq( 'client-name', $exp['site'] );
	twd_sk_eq( 1, count( $exp['snags'] ) );
	twd_sk_cli_fails( function () {
		( new TWD_SK_CLI() )->snags( array( 'frobnicate' ), array() );
	} );
	twd_sk_cli_fails( function () {
		( new TWD_SK_CLI() )->snags( array( 'show' ), array() );
	} );
	twd_sk_cli_fails( function () {
		( new TWD_SK_CLI() )->snags( array( 'show', '99' ), array() );
	} );
	twd_sk_cli_fails( function () {
		( new TWD_SK_CLI() )->snags( array( 'list' ), array( 'status' => 'bogus' ) );
	} );
} );

twd_sk_test( 'snagbook js: builds no HTML from text, no native dialogs, no web addresses, no storage in the browser, and talks only through the editor api', function () {
	$js = twd_sk_sb_js();
	foreach ( array( 'eval(', 'new Function', 'document.write', 'innerHTML', 'outerHTML', 'insertAdjacentHTML', 'setTimeout("', "setTimeout('", 'srcdoc', 'importScripts', 'XMLHttpRequest', 'WebSocket', 'sendBeacon', 'localStorage', 'sessionStorage', 'alert(', 'confirm(', 'prompt(', 'fetch(' ) as $bad ) {
		twd_sk_hasnt( $bad, $js, $bad );
	}
	twd_sk_true( 1 !== preg_match( '#https?://#i', $js ), 'no web addresses in the script' );
	twd_sk_true( 1 !== preg_match( '/\xE2\x80[\x93\x94]/', $js ), 'no long dashes in the script' );
	twd_sk_has( "api('POST', '/site/snagbook'", $js );
	twd_sk_has( "api('GET', '/site/snagbook'", $js );
	twd_sk_has( '/site/snagbook/', $js, 'the status route' );
	twd_sk_has( 'window.location.pathname', $js, 'the path only' );
	twd_sk_hasnt( 'location.search', $js, 'never the query string, which could hold a preview token' );
	twd_sk_hasnt( 'location.href', $js, 'never the whole address' );
} );

twd_sk_test( 'snagbook js: every editor function the script relies on is really offered by the editor script, and the editor keeps the lead-up record', function () {
	$js     = twd_sk_sb_js();
	$editor = file_get_contents( ABSPATH . 'assets/twd-site-kit-editor.js' );
	preg_match_all( '/\bED\.([A-Za-z]+)/', $js, $m );
	$used = array_values( array_unique( $m[1] ) );
	twd_sk_true( count( $used ) >= 10, 'the script uses the editor api' );
	foreach ( $used as $name ) {
		if ( 'currentSection' === $name ) {
			twd_sk_has( 'ED.currentSection = {', file_get_contents( ABSPATH . 'assets/twd-site-kit-inline.js' ), 'the Visual editor sets the section a snag is about' );
			continue;
		}
		twd_sk_eq( 1, preg_match( '/^\s+' . preg_quote( $name, '/' ) . '\s*:/m', $editor ), 'ED.' . $name . ' is offered by the editor script' );
	}
	foreach ( array( 'function logEvent(', 'log: logEvent', "ED.addLauncher({ id: 'snag'" ) as $needle ) {
		if ( 0 === strpos( $needle, 'ED.addLauncher' ) ) {
			twd_sk_has( $needle, $js, 'the Your Edits menu has a Snag item' );
		} else {
			twd_sk_has( $needle, $editor, $needle );
		}
	}
} );

twd_sk_test( 'snagbook js: the script parses (skipped, with a note, where node is not installed)', function () {
	$probe = trim( (string) shell_exec( 'node --version 2>&1' ) );
	if ( 1 !== preg_match( '/^v\d+\./', $probe ) ) {
		twd_sk_note( 'Snag script syntax not checked: node is not installed here.' );
		return;
	}
	twd_sk_eq( '', trim( (string) shell_exec( 'node --check ' . escapeshellarg( ABSPATH . 'assets/twd-site-kit-editor-snag.js' ) . ' 2>&1' ) ) );
} );

twd_sk_test( 'snagbook css: the category radios are styled from the sources (the build test keeps the generated file in step)', function () {
	$src = file_get_contents( dirname( __DIR__ ) . '/tools/editor.src.css' );
	twd_sk_has( '.twd-sk-ed__radios', $src );
	$css = file_get_contents( ABSPATH . 'assets/twd-site-kit-editor.css' );
	twd_sk_has( '.twd-sk-ed .twd-sk-ed__radios.twd-sk-ed__radios', $css );
} );
