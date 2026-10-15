<?php
// The Tell us snag module (0.11.0): notes kept on the site, who may send, see and delete them,
// the limits, the retention, the notification email, privacy tools, safe mode, and the script itself.

function twd_sk_sn_on( $caps = null ) {
	update_option( TWD_SK_Snags::OPT_ON, '1' );
	$GLOBALS['twd_stub']['caps']     = null === $caps ? array( 'edit_pages' ) : $caps;
	$GLOBALS['twd_stub']['admin']    = false;
	$GLOBALS['twd_stub']['users']    = array( 7 => 'Test Editor', 8 => 'Other Editor', 1 => 'Site Owner' );
	twd_stub_add_post( 12, 'page', '[twd_page]' );
	$GLOBALS['twd_stub']['queried']  = 12;
	$GLOBALS['twd_stub']['post_id']  = 12;
	$GLOBALS['twd_stub']['singular'] = true;
	TWD_SK_Preview::reset();
}
function twd_sk_sn_admin() {
	$GLOBALS['twd_stub']['caps'] = array( 'edit_pages', 'manage_options' );
}
function twd_sk_sn_req( $params = array(), $nonce = 'good-nonce', $body = null ) {
	$headers = null === $nonce ? array() : array( 'X-WP-Nonce' => $nonce );
	return new WP_REST_Request( $params, $headers, $body );
}
function twd_sk_sn_routes() {
	$GLOBALS['twd_stub']['routes'] = array();
	TWD_SK_Snags::register_routes();
	$out = array();
	foreach ( $GLOBALS['twd_stub']['routes'] as $r ) {
		$list = isset( $r['args']['methods'] ) ? array( $r['args'] ) : $r['args'];
		foreach ( $list as $a ) {
			$out[ $r['route'] . ' ' . $a['methods'] ] = $a;
		}
	}
	return $out;
}
function twd_sk_sn_call( $route, $method, $params = array(), $nonce = 'good-nonce' ) {
	$routes = twd_sk_sn_routes();
	$a      = $routes[ $route . ' ' . $method ];
	$req    = twd_sk_sn_req( $params, $nonce );
	$perm   = call_user_func( $a['permission_callback'], $req );
	if ( true !== $perm ) {
		return $perm;
	}
	return call_user_func( $a['callback'], $req );
}

twd_sk_test( 'snags: message cleaning removes tags, web addresses, long dashes and control characters, and caps the length', function () {
	$out = TWD_SK_Snags::clean_message( "<b>Change</b> the heading \xE2\x80\x94 see https://example.com/page and www.example.org now\x07." );
	twd_sk_hasnt( '<b>', $out );
	twd_sk_hasnt( 'example.com', $out );
	twd_sk_hasnt( 'example.org', $out );
	twd_sk_has( '(web address removed)', $out );
	twd_sk_hasnt( "\xE2\x80\x94", $out );
	twd_sk_hasnt( "\x07", $out );
	twd_sk_eq( TWD_SK_Snags::MAX_LEN, strlen( TWD_SK_Snags::clean_message( str_repeat( 'a', 5000 ) ) ) );
	twd_sk_eq( '', TWD_SK_Snags::clean_message( array( 'x' ) ) );
} );

twd_sk_test( 'snags: off by default, on only when switched on and never in safe mode', function () {
	twd_sk_true( ! TWD_SK_Snags::enabled() );
	update_option( TWD_SK_Snags::OPT_ON, '1' );
	twd_sk_true( TWD_SK_Snags::enabled() );
	$GLOBALS['twd_stub']['options'][ TWD_SK_Safe::OPTION ] = '1';
	twd_sk_true( ! TWD_SK_Snags::enabled(), 'safe mode switches it off' );
	twd_sk_true( TWD_SK_Snags::settings()['safe_mode'] );
} );

twd_sk_test( 'snags: modules start it, so safe mode never reaches it', function () {
	$mods = file_get_contents( ABSPATH . 'includes/class-twd-sk-modules.php' );
	twd_sk_has( 'TWD_SK_Snags::init();', $mods );
	$main = file_get_contents( ABSPATH . 'twd-site-kit.php' );
	twd_sk_has( 'if ( ! TWD_SK_Safe::on() ) {', $main );
	twd_sk_hasnt( 'TWD_SK_Snags::init', $main, 'not started outside the modules class' );
} );

twd_sk_test( 'snags: a note is a private post with the message, page, date, type and status; nothing else', function () {
	twd_sk_sn_on();
	$id = TWD_SK_Snags::create( 7, 'Please make the heading bigger.', 12 );
	twd_sk_true( is_int( $id ) && $id > 0 );
	$post = get_post( $id );
	twd_sk_eq( 'twd_sk_snag', $post->post_type );
	$n = TWD_SK_Snags::get( $id );
	twd_sk_eq( 'snag', $n['type'] );
	twd_sk_eq( 'new', $n['status'] );
	twd_sk_eq( 12, $n['page_id'] );
	twd_sk_eq( 'Page 12', $n['page_title'] );
	twd_sk_eq( 7, $n['user_id'] );
	twd_sk_true( $n['time'] > 0 );
	twd_sk_eq( array( '_twd_sk_snag_type', '_twd_sk_snag_status', '_twd_sk_snag_page', '_twd_sk_snag_page_title', '_twd_sk_snag_time' ), twd_stub_meta_keys( $id ), 'no photo, no address, no email' );
	$is_error = TWD_SK_Snags::create( 7, 'hi' );
	twd_sk_is_error( 'twd_sk_snag_empty', $is_error );
} );

twd_sk_test( 'snags: the post type is private (no public address, no screen, no REST, no export)', function () {
	TWD_SK_Snags::register_post_type();
	$a = $GLOBALS['twd_stub']['post_types']['twd_sk_snag'];
	twd_sk_true( false === $a['public'] && false === $a['publicly_queryable'] && false === $a['show_ui'] && false === $a['show_in_rest'] );
	twd_sk_true( false === $a['rewrite'] && false === $a['has_archive'] && false === $a['can_export'] );
} );

twd_sk_test( 'snags: a page the person cannot see is not recorded by name', function () {
	twd_sk_sn_on();
	twd_stub_add_post( 30, 'page', 'draft' );
	$GLOBALS['twd_stub']['posts'][30]->post_status = 'draft';
	$id = TWD_SK_Snags::create( 7, 'About this one please.', 30 );
	twd_sk_eq( 0, TWD_SK_Snags::get( $id )['page_id'] );
	twd_sk_eq( 'Not on a page', TWD_SK_Snags::get( $id )['page_title'] );
} );

twd_sk_test( 'snags rest: every route has a permission check, ten routes exist, and the settings work while the module is off', function () {
	twd_sk_sn_on();
	$routes = twd_sk_sn_routes();
	twd_sk_eq( 7, count( $routes ) );
	foreach ( $routes as $name => $a ) {
		twd_sk_true( isset( $a['permission_callback'] ) && is_callable( $a['permission_callback'] ), $name );
	}
	// Module off: the form routes refuse, the settings still answer an administrator.
	update_option( TWD_SK_Snags::OPT_ON, '' );
	twd_sk_sn_admin();
	twd_sk_is_error( 'twd_sk_snags_off', twd_sk_sn_call( '/snags', 'GET' ) );
	$s = twd_sk_sn_call( '/snags/settings', 'GET' );
	twd_sk_true( is_array( $s ) && false === $s['enabled'] );
} );

twd_sk_test( 'snags rest: bad nonce, signed out, no edit_pages and a settings call by a non-administrator are refused', function () {
	twd_sk_sn_on();
	twd_sk_is_error( 'twd_sk_bad_nonce', twd_sk_sn_call( '/snags', 'GET', array(), 'wrong' ) );
	$GLOBALS['twd_stub']['user'] = 0;
	twd_sk_is_error( 'twd_sk_not_signed_in', twd_sk_sn_call( '/snags', 'GET' ) );
	$GLOBALS['twd_stub']['user'] = 7;
	$GLOBALS['twd_stub']['caps'] = array();
	twd_sk_is_error( 'twd_sk_forbidden', twd_sk_sn_call( '/snags', 'GET' ) );
	$GLOBALS['twd_stub']['caps'] = array( 'edit_pages' );
	twd_sk_is_error( 'twd_sk_forbidden', twd_sk_sn_call( '/snags/settings', 'GET' ), 'client cannot read settings' );
	twd_sk_is_error( 'twd_sk_forbidden', twd_sk_sn_call( '/snags/markdown', 'GET' ), 'client cannot export' );
} );

twd_sk_test( 'snags rest: a body over the size limit is refused', function () {
	twd_sk_sn_on();
	$routes = twd_sk_sn_routes();
	$req    = twd_sk_sn_req( array( 'message' => 'x' ), 'good-nonce', str_repeat( 'x', 20000 ) );
	twd_sk_is_error( 'twd_sk_too_large', call_user_func( $routes['/snags POST']['permission_callback'], $req ) );
} );

twd_sk_test( 'snags rest: a client sends a note; the notification email never carries the message', function () {
	twd_sk_sn_on();
	update_option( TWD_SK_Snags::OPT_EMAIL_ON, '1' );
	update_option( TWD_SK_Snags::OPT_EMAIL, 'owner@example.com' );
	$out = twd_sk_sn_call( '/snags', 'POST', array( 'message' => 'The phone number in the footer is wrong.', 'page_id' => 12 ) );
	twd_sk_true( is_array( $out ) && $out['id'] > 0 );
	twd_sk_eq( 1, count( $GLOBALS['twd_stub']['mails'] ) );
	$mail = $GLOBALS['twd_stub']['mails'][0];
	twd_sk_eq( 'owner@example.com', $mail['to'] );
	twd_sk_hasnt( 'phone number', $mail['subject'] . $mail['message'], 'no message text in the email' );
	twd_sk_has( 'Page 12', $mail['message'] );
} );

twd_sk_test( 'snags rest: email is off by default and sends nothing for an idea', function () {
	twd_sk_sn_on();
	twd_sk_sn_call( '/snags', 'POST', array( 'message' => 'A note with no email set up.' ) );
	twd_sk_true( empty( $GLOBALS['twd_stub']['mails'] ), 'nothing sent by default' );
	update_option( TWD_SK_Snags::OPT_EMAIL_ON, '1' );
	update_option( TWD_SK_Snags::OPT_EMAIL, 'owner@example.com' );
	twd_sk_sn_admin();
	twd_sk_sn_call( '/snags', 'POST', array( 'message' => 'My own idea for later.', 'type' => 'idea' ) );
	twd_sk_true( empty( $GLOBALS['twd_stub']['mails'] ), 'ideas send no email' );
} );

twd_sk_test( 'snags rest: only an administrator can add an idea', function () {
	twd_sk_sn_on();
	twd_sk_is_error( 'twd_sk_forbidden', twd_sk_sn_call( '/snags', 'POST', array( 'message' => 'Sneaky idea please.', 'type' => 'idea' ) ) );
	twd_sk_sn_admin();
	$out = twd_sk_sn_call( '/snags', 'POST', array( 'message' => 'A proper idea for the site.', 'type' => 'idea' ) );
	twd_sk_eq( 'idea', TWD_SK_Snags::get( $out['id'] )['type'] );
} );

twd_sk_test( 'snags rest: a person can send ten notes an hour, the eleventh is refused; the site has a daily ceiling', function () {
	twd_sk_sn_on();
	for ( $i = 1; $i <= 10; $i++ ) {
		$out = twd_sk_sn_call( '/snags', 'POST', array( 'message' => 'Note number ' . $i . ' please.' ) );
		twd_sk_true( is_array( $out ), 'note ' . $i );
	}
	twd_sk_is_error( 'twd_sk_rate_limited', twd_sk_sn_call( '/snags', 'POST', array( 'message' => 'One too many for this hour.' ) ) );
	// A different person on a busy day.
	$GLOBALS['twd_stub']['user'] = 8;
	set_transient( 'twd_sk_snag_day_' . gmdate( 'Ymd' ), TWD_SK_Snags::SITE_PER_DAY, 86400 );
	twd_sk_is_error( 'twd_sk_rate_limited', twd_sk_sn_call( '/snags', 'POST', array( 'message' => 'Busy day on the site.' ) ) );
} );

twd_sk_test( 'snags rest: a client sees only their own snags; an administrator sees everything with who sent it', function () {
	twd_sk_sn_on();
	TWD_SK_Snags::create( 7, 'Mine, from the first editor.', 12 );
	TWD_SK_Snags::create( 8, 'Theirs, from the second editor.', 12 );
	TWD_SK_Snags::create( 1, 'An idea only the owner may see.', 12, 'idea' );
	$mine = twd_sk_sn_call( '/snags', 'GET' );
	twd_sk_eq( 1, count( $mine['items'] ) );
	twd_sk_eq( 'Mine, from the first editor.', $mine['items'][0]['message'] );
	twd_sk_true( ! isset( $mine['items'][0]['from'] ), 'no names for a client' );
	twd_sk_true( false === $mine['is_admin'] );
	twd_sk_sn_admin();
	$all = twd_sk_sn_call( '/snags', 'GET' );
	twd_sk_eq( 3, count( $all['items'] ) );
	$types = array_column( $all['items'], 'type' );
	sort( $types );
	twd_sk_eq( array( 'idea', 'snag', 'snag' ), $types );
	twd_sk_has( 'Other Editor', json_encode( $all['items'] ) );
	twd_sk_has( 'months, then it is deleted', $all['privacy'] );
} );

twd_sk_test( 'snags rest: a client deletes only their own snag; an administrator deletes any note', function () {
	twd_sk_sn_on();
	$mine   = TWD_SK_Snags::create( 7, 'Mine to delete if I like.' );
	$theirs = TWD_SK_Snags::create( 8, 'Theirs and not for me to delete.' );
	$idea   = TWD_SK_Snags::create( 1, 'An idea of the owner.', 0, 'idea' );
	twd_sk_is_error( 'twd_sk_forbidden', twd_sk_sn_call( '/snags/(?P<id>\d+)/delete', 'POST', array( 'id' => $theirs ) ) );
	twd_sk_is_error( 'twd_sk_forbidden', twd_sk_sn_call( '/snags/(?P<id>\d+)/delete', 'POST', array( 'id' => $idea ) ) );
	$out = twd_sk_sn_call( '/snags/(?P<id>\d+)/delete', 'POST', array( 'id' => $mine ) );
	twd_sk_eq( $mine, $out['deleted'] );
	twd_sk_true( null === TWD_SK_Snags::get( $mine ) );
	twd_sk_sn_admin();
	twd_sk_true( is_array( twd_sk_sn_call( '/snags/(?P<id>\d+)/delete', 'POST', array( 'id' => $theirs ) ) ) );
	twd_sk_is_error( 'twd_sk_snag_missing', twd_sk_sn_call( '/snags/(?P<id>\d+)/delete', 'POST', array( 'id' => 9999 ) ) );
} );

twd_sk_test( 'snags rest: status can be set by an administrator only, and only to a known status', function () {
	twd_sk_sn_on();
	$id = TWD_SK_Snags::create( 7, 'A note to move along.' );
	twd_sk_is_error( 'twd_sk_forbidden', twd_sk_sn_call( '/snags/(?P<id>\d+)/status', 'POST', array( 'id' => $id, 'status' => 'done' ) ) );
	twd_sk_sn_admin();
	$out = twd_sk_sn_call( '/snags/(?P<id>\d+)/status', 'POST', array( 'id' => $id, 'status' => 'done' ) );
	twd_sk_eq( 'done', $out['item']['status'] );
	twd_sk_is_error( 'twd_sk_snag_bad_status', twd_sk_sn_call( '/snags/(?P<id>\d+)/status', 'POST', array( 'id' => $id, 'status' => 'bogus' ) ) );
} );

twd_sk_test( 'snags: markdown lists the new notes with no web addresses and no email addresses', function () {
	twd_sk_sn_on();
	$a = TWD_SK_Snags::create( 7, 'First: fix the opening hours, see https://example.com/hours please.', 12 );
	$b = TWD_SK_Snags::create( 7, 'Second note, already dealt with.', 12 );
	TWD_SK_Snags::set_status( $b, 'done' );
	twd_sk_sn_admin();
	$out = twd_sk_sn_call( '/snags/markdown', 'GET' );
	twd_sk_eq( 1, $out['count'] );
	twd_sk_has( '# Tell us notes', $out['markdown'] );
	twd_sk_has( 'First: fix the opening hours', $out['markdown'] );
	twd_sk_hasnt( 'Second note', $out['markdown'] );
	twd_sk_hasnt( 'example.com', $out['markdown'] );
	twd_sk_true( 1 !== preg_match( '#https?://|www\.|@#i', $out['markdown'] ), 'no addresses of any kind' );
	twd_sk_hasnt( 'Nothing new', $out['markdown'] );
	twd_sk_has( 'Nothing new', TWD_SK_Snags::markdown( array() ) );
} );

twd_sk_test( 'snags: the retention sweep deletes old notes, ideas included, keeps new ones, and runs once a day', function () {
	twd_sk_sn_on();
	$old   = TWD_SK_Snags::create( 7, 'An old note from long ago.' );
	$new   = TWD_SK_Snags::create( 7, 'A fresh note from today.' );
	$idea  = TWD_SK_Snags::create( 1, 'An old idea too.', 0, 'idea' );
	update_post_meta( $old, '_twd_sk_snag_time', time() - 400 * 86400 );
	update_post_meta( $idea, '_twd_sk_snag_time', time() - 400 * 86400 );
	TWD_SK_Snags::maybe_sweep();
	twd_sk_true( null === TWD_SK_Snags::get( $old ) && null === TWD_SK_Snags::get( $idea ) );
	twd_sk_true( null !== TWD_SK_Snags::get( $new ) );
	// A second call the same day does nothing, even for a note that has just aged.
	$later = TWD_SK_Snags::create( 7, 'Aged after the sweep.' );
	update_post_meta( $later, '_twd_sk_snag_time', time() - 400 * 86400 );
	TWD_SK_Snags::maybe_sweep();
	twd_sk_true( null !== TWD_SK_Snags::get( $later ), 'once a day' );
} );

twd_sk_test( 'snags: the retention follows the setting, defaults to 12 months, and the sweep runs even with the module off', function () {
	twd_sk_sn_on();
	twd_sk_eq( 12, TWD_SK_Snags::months() );
	update_option( TWD_SK_Snags::OPT_MONTHS, 3 );
	twd_sk_eq( 3, TWD_SK_Snags::months() );
	$id = TWD_SK_Snags::create( 7, 'Four months old.' );
	update_post_meta( $id, '_twd_sk_snag_time', time() - 120 * 86400 );
	update_option( TWD_SK_Snags::OPT_ON, '' );
	twd_sk_eq( 1, TWD_SK_Snags::sweep() );
	update_option( TWD_SK_Snags::OPT_MONTHS, 999 );
	twd_sk_eq( 12, TWD_SK_Snags::months(), 'an odd value falls back' );
} );

twd_sk_test( 'snags: settings are checked before anything is saved', function () {
	twd_sk_is_error( 'twd_sk_bad_email', TWD_SK_Snags::save_settings( array( 'enabled' => true, 'months' => 12, 'email_on' => true, 'email' => 'nonsense' ) ) );
	twd_sk_is_error( 'twd_sk_bad_email', TWD_SK_Snags::save_settings( array( 'enabled' => true, 'months' => 12, 'email_on' => true, 'email' => '' ) ) );
	twd_sk_is_error( 'twd_sk_bad_months', TWD_SK_Snags::save_settings( array( 'enabled' => true, 'months' => 7 ) ) );
	twd_sk_true( ! TWD_SK_Snags::enabled(), 'nothing was saved by the refused calls' );
	$saved = TWD_SK_Snags::save_settings( array( 'enabled' => true, 'months' => 24, 'email_on' => true, 'email' => 'owner@example.com' ) );
	twd_sk_true( true === $saved['enabled'] && 24 === $saved['months'] && true === $saved['email_on'] );
	twd_sk_true( TWD_SK_Snags::email_on() );
	$saved = TWD_SK_Snags::save_settings( array( 'enabled' => false, 'months' => 24, 'email_on' => false, 'email' => '' ) );
	twd_sk_true( false === $saved['enabled'] && ! TWD_SK_Snags::email_on() );
} );

twd_sk_test( 'snags: the privacy line names what is kept and for how long, and follows the setting', function () {
	$line = TWD_SK_Snags::privacy_line();
	twd_sk_has( '12 months', $line );
	twd_sk_has( 'your message, the page, the date', $line );
	twd_sk_has( 'delete your note at any time', $line );
	update_option( TWD_SK_Snags::OPT_MONTHS, 24 );
	twd_sk_has( '24 months', TWD_SK_Snags::privacy_line() );
} );

twd_sk_test( 'snags: WordPress personal data export and erase cover the notes a person sent', function () {
	twd_sk_sn_on();
	$GLOBALS['twd_stub']['user_emails'] = array( 'them@example.com' => 8 );
	TWD_SK_Snags::create( 8, 'Their private note.' );
	TWD_SK_Snags::create( 7, 'Not theirs at all.' );
	TWD_SK_Snags::init();
	$ex = apply_filters( 'wp_privacy_personal_data_exporters', array() );
	$out = call_user_func( $ex['twd-site-kit-snags']['callback'], 'them@example.com' );
	twd_sk_eq( 1, count( $out['data'] ) );
	twd_sk_has( 'Their private note.', json_encode( $out ) );
	twd_sk_hasnt( 'Not theirs', json_encode( $out ) );
	$er = apply_filters( 'wp_privacy_personal_data_erasers', array() );
	$res = call_user_func( $er['twd-site-kit-snags']['callback'], 'them@example.com' );
	twd_sk_true( $res['items_removed'] && $res['done'] );
	twd_sk_eq( 1, count( TWD_SK_Snags::find() ) );
	$res = call_user_func( $er['twd-site-kit-snags']['callback'], 'nobody@example.com' );
	twd_sk_true( ! $res['items_removed'] );
} );

twd_sk_test( 'snags: the button and script load only when switched on, for someone who can edit pages, and print the version', function () {
	twd_sk_sn_on();
	update_option( TWD_SK_Snags::OPT_ON, '' );
	$GLOBALS['twd_stub']['caps'] = array( 'edit_pages', 'edit_post:12' );
	TWD_SK_Snags::enqueue();
	twd_sk_true( empty( $GLOBALS['twd_stub']['scripts']['twd-site-kit-snags'] ), 'off: no script' );
	ob_start();
	TWD_SK_Snags::print_root();
	twd_sk_eq( '', ob_get_clean(), 'off: no button' );
	update_option( TWD_SK_Snags::OPT_ON, '1' );
	TWD_SK_Snags::enqueue();
	twd_sk_true( ! empty( $GLOBALS['twd_stub']['scripts']['twd-site-kit-snags']['enqueued'] ) );
	$data = $GLOBALS['twd_stub']['scripts']['twd-site-kit-snags']['data'];
	twd_sk_eq( 12, $data['pageId'] );
	twd_sk_eq( TWD_SK_VERSION, $data['version'] );
	twd_sk_eq( 'good-nonce', $data['nonce'] );
	twd_sk_true( ! $data['isAdmin'] );
	ob_start();
	TWD_SK_Snags::print_root();
	$html = ob_get_clean();
	twd_sk_has( '>Tell us</button>', $html );
	$GLOBALS['twd_stub']['user'] = 0;
	ob_start();
	TWD_SK_Snags::print_root();
	twd_sk_eq( '', ob_get_clean(), 'signed out: no button' );
} );

twd_sk_test( 'snags: no button in safe mode', function () {
	twd_sk_sn_on();
	$GLOBALS['twd_stub']['options'][ TWD_SK_Safe::OPTION ] = '1';
	ob_start();
	TWD_SK_Snags::print_root();
	twd_sk_eq( '', ob_get_clean() );
} );

twd_sk_test( 'snags js: builds no HTML from text, no native dialogs, no web addresses, one way to talk to the server, valid syntax', function () {
	$js = file_get_contents( ABSPATH . 'assets/twd-site-kit-snags.js' );
	foreach ( array( 'innerHTML', 'outerHTML', 'insertAdjacentHTML', 'eval(', 'new Function', 'document.write', 'XMLHttpRequest', 'localStorage', 'sendBeacon', 'onclick' ) as $bad ) {
		twd_sk_hasnt( $bad, $js );
	}
	twd_sk_true( 1 !== preg_match( '/(^|[^.\w])(alert|confirm|prompt)\s*\(/m', $js ), 'no native dialogs' );
	twd_sk_true( 1 !== preg_match( '#https?://#i', $js ), 'no web addresses' );
	twd_sk_true( 1 !== preg_match( '/[^\x00-\x7F]/', $js ), 'ASCII only' );
	twd_sk_eq( 1, substr_count( $js, 'fetch(' ), 'one request function' );
	twd_sk_has( "'X-WP-Nonce': cfg.nonce", $js );
	twd_sk_has( 'What would you like changed?', $js );
	twd_sk_has( 'Copy new notes as markdown', $js );
	twd_sk_has( 'Sure? Delete', $js, 'two steps to delete' );
	twd_sk_has( "e.key === 'Escape'", $js );
	twd_sk_has( 'Version', $js, 'the version star' );
	twd_sk_eq( '', trim( (string) shell_exec( 'node --check ' . escapeshellarg( ABSPATH . 'assets/twd-site-kit-snags.js' ) . ' 2>&1' ) ) );
} );

twd_sk_test( 'snags: the Site tab has the settings section, and the Visual editor rename is complete', function () {
	$site = file_get_contents( ABSPATH . 'assets/twd-site-kit-editor-site.js' );
	twd_sk_has( "api('POST', '/snags/settings', current())", $site );
	twd_sk_has( "'Tell us notes'", $site );
	$inline = file_get_contents( ABSPATH . 'assets/twd-site-kit-inline.js' );
	twd_sk_has( "text: 'Visual editor'", $inline );
	twd_sk_has( ": 'Visual editor';", $inline, 'the admin bar' );
	twd_sk_hasnt( "'Edit text'", $inline );
	twd_sk_hasnt( 'Edit text', file_get_contents( ABSPATH . 'assets/twd-site-kit-editor.js' ) );
} );

twd_sk_test( 'snags css: the button sits above the Visual editor button and drops down when that button is missing', function () {
	$css = file_get_contents( ABSPATH . 'assets/twd-site-kit-editor.css' );
	twd_sk_has( 'twd-sk-ed__open--tellus', $css );
	twd_sk_has( 'twd-sk-tu__list', $css );
} );

twd_sk_test( 'snags: the class source has no web addresses and no long dashes', function () {
	$src = file_get_contents( ABSPATH . 'includes/class-twd-sk-snags.php' );
	twd_sk_true( 1 !== preg_match( '#https?://#i', $src ), 'no web addresses' );
	twd_sk_true( 1 !== preg_match( '/\xE2\x80[\x93\x94]/', $src ), 'no long dashes' );
	twd_sk_hasnt( 'wp_remote', $src, 'no outside request' );
	twd_sk_hasnt( 'GITHUB', strtoupper( $src ), 'no outside service' );
} );

twd_sk_test( 'snags cli: lists notes as json or markdown, sets a status, and switches the module', function () {
	require_once __DIR__ . '/stub-wpcli.php';
	twd_sk_sn_on();
	TWD_SK_Snags::create( 7, 'Please change the heading on this page.', 12 );
	$id = TWD_SK_Snags::create( 7, 'Another one, already handled.', 12 );
	TWD_SK_Snags::set_status( $id, 'done' );
	WP_CLI::reset();
	( new TWD_SK_CLI() )->snags( array(), array( 'status' => 'new' ) );
	$rows = json_decode( preg_replace( '/^line: /', '', WP_CLI::all() ), true );
	twd_sk_eq( 1, count( $rows ) );
	twd_sk_eq( 7, $rows[0]['user_id'] );
	twd_sk_true( 1 !== preg_match( '#https?://#i', WP_CLI::all() ), 'no web addresses' );
	WP_CLI::reset();
	( new TWD_SK_CLI() )->snags( array(), array( 'format' => 'markdown' ) );
	twd_sk_has( '# Tell us notes', WP_CLI::all() );
	twd_sk_has( 'Please change the heading', WP_CLI::all() );
	WP_CLI::reset();
	( new TWD_SK_CLI() )->snag_status( array( (string) $id, 'parked' ), array() );
	twd_sk_eq( 'parked', TWD_SK_Snags::get( $id )['status'] );
	twd_sk_cli_fails( function () use ( $id ) {
		( new TWD_SK_CLI() )->snag_status( array( (string) $id, 'bogus' ), array() );
	} );
	twd_sk_cli_fails( function () {
		( new TWD_SK_CLI() )->snags( array(), array( 'status' => 'bogus' ) );
	} );
	WP_CLI::reset();
	( new TWD_SK_CLI() )->snags_setting( array( 'off' ), array() );
	twd_sk_true( ! TWD_SK_Snags::enabled() );
	twd_sk_has( 'Tell us: off', WP_CLI::all() );
	( new TWD_SK_CLI() )->snags_setting( array( 'on' ), array() );
	twd_sk_true( TWD_SK_Snags::enabled() );
} );

twd_sk_test( 'version label: signed-in editors get a small version label in the bottom left; visitors get nothing', function () {
	twd_sk_sn_on();
	ob_start();
	TWD_SK_Editor::print_root();
	$html = ob_get_clean();
	twd_sk_has( '<span class="twd-sk-ed__version" title="TWD Site Kit version">v' . TWD_SK_VERSION . '</span>', $html );
	$GLOBALS['twd_stub']['user'] = 0;
	ob_start();
	TWD_SK_Editor::print_root();
	twd_sk_eq( '', ob_get_clean(), 'visitors see no label' );
	$css = file_get_contents( ABSPATH . 'assets/twd-site-kit-editor.css' );
	twd_sk_has( '.twd-sk-ed__version', $css );
	twd_sk_has( 'pointer-events: none', $css, 'never in the way of a tap' );
} );
