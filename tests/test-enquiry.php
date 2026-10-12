<?php
// The enquiry form (0.8.1): built at display time, signed, trapped, limited, emailed, never stored.

function twd_sk_enq_post( $extra = array(), $age = 60 ) {
	$now = 1700000000;
	return array( array_merge( array(
		'action'         => 'twd_sk_enquiry',
		'twd_sk_page'    => '12',
		'twd_sk_form'    => 'twd-sk-enquiry',
		'twd_sk_t'       => TWD_SK_Enquiry::token( 12, $now - $age ),
		'twd_sk_website' => '',
		'twd_sk_name'    => 'Alex Example',
		'twd_sk_email'   => 'alex@example.com',
		'twd_sk_phone'   => '01234 567 890',
		'twd_sk_message' => "Hello,\nCan I book a weekend?",
		'twd_sk_consent' => '1',
	), $extra ), $now );
}
function twd_sk_enq_setup() {
	twd_stub_add_post( 12, 'page', '[twd_page]' );
	TWD_SK_Profile::save( array( 'email' => 'owner@example.org' ) );
	update_option( 'admin_email', 'admin@example.net' );
	$GLOBALS['twd_stub']['mails'] = array();
}

twd_sk_test( 'enquiry token: good after three seconds, refused when too quick, too old, altered or for another page', function () {
	$now = 1700000000;
	twd_sk_eq( '', TWD_SK_Enquiry::check_token( TWD_SK_Enquiry::token( 12, $now - 10 ), 12, $now ) );
	twd_sk_eq( 'too_fast', TWD_SK_Enquiry::check_token( TWD_SK_Enquiry::token( 12, $now - 1 ), 12, $now ) );
	twd_sk_eq( 'expired', TWD_SK_Enquiry::check_token( TWD_SK_Enquiry::token( 12, $now - TWD_SK_Enquiry::MAX_AGE - 1 ), 12, $now ) );
	twd_sk_eq( 'expired', TWD_SK_Enquiry::check_token( TWD_SK_Enquiry::token( 13, $now - 10 ), 12, $now ), 'another page' );
	$t = TWD_SK_Enquiry::token( 12, $now - 10 );
	twd_sk_eq( 'expired', TWD_SK_Enquiry::check_token( ( $now - 20 ) . substr( $t, strpos( $t, '.' ) ), 12, $now ), 'time changed' );
	twd_sk_eq( 'expired', TWD_SK_Enquiry::check_token( 'nonsense', 12, $now ) );
	twd_sk_eq( 'expired', TWD_SK_Enquiry::check_token( array(), 12, $now ) );
} );

twd_sk_test( 'enquiry: a good message is emailed to the site details address with a safe reply-to, and nothing is stored', function () {
	twd_sk_enq_setup();
	$before = $GLOBALS['twd_stub']['options'];
	list( $post, $now ) = twd_sk_enq_post( array( 'twd_sk_name' => "Alex\r\nBcc: victim@example.com <b>X</b>", 'twd_sk_message' => "Line one\n<script>x</script>Line two" ) );
	$url = TWD_SK_Enquiry::process( $post, '203.0.113.5', $now );
	twd_sk_has( 'twd_sk_sent=1', $url );
	twd_sk_has( '#twd-sk-enquiry', $url );
	twd_sk_eq( 1, count( $GLOBALS['twd_stub']['mails'] ) );
	$m = $GLOBALS['twd_stub']['mails'][0];
	twd_sk_eq( 'owner@example.org', $m['to'] );
	twd_sk_hasnt( "\n", $m['subject'] );
	twd_sk_hasnt( "\r", implode( '', $m['headers'] ) );
	twd_sk_hasnt( "\n", implode( '', $m['headers'] ) );
	twd_sk_eq( array( 'Reply-To: "Alex Bcc: victim@example.com X" <alex@example.com>' ), $m['headers'] );
	twd_sk_has( "Line one\nxLine two", $m['message'], 'tags removed, line breaks kept' );
	twd_sk_has( 'Phone: 01234 567 890', $m['message'] );
	$after = $GLOBALS['twd_stub']['options'];
	unset( $after[ TWD_SK_Enquiry::STATS ] );
	twd_sk_eq( json_encode( $before ), json_encode( $after ), 'only the counts are stored' );
	twd_sk_hasnt( 'Alex', json_encode( get_option( TWD_SK_Enquiry::STATS ) ) );
	twd_sk_hasnt( 'Alex', json_encode( $GLOBALS['twd_stub']['transients'] ) );
} );

twd_sk_test( 'enquiry: missing fields, a bad email or no consent send nothing and say why; a bad phone is dropped', function () {
	twd_sk_enq_setup();
	foreach ( array( 'twd_sk_name' => '', 'twd_sk_message' => '  ', 'twd_sk_email' => 'not-an-email' ) as $k => $v ) {
		list( $post, $now ) = twd_sk_enq_post( array( $k => $v ) );
		twd_sk_has( 'twd_sk_error=missing', TWD_SK_Enquiry::process( $post, '203.0.113.5', $now ), $k );
	}
	list( $post, $now ) = twd_sk_enq_post( array( 'twd_sk_consent' => '' ) );
	twd_sk_has( 'twd_sk_error=consent', TWD_SK_Enquiry::process( $post, '203.0.113.5', $now ) );
	list( $post, $now ) = twd_sk_enq_post( array(), 1 );
	twd_sk_has( 'twd_sk_error=too_fast', TWD_SK_Enquiry::process( $post, '203.0.113.5', $now ) );
	twd_sk_eq( 0, count( $GLOBALS['twd_stub']['mails'] ) );
	list( $post, $now ) = twd_sk_enq_post( array( 'twd_sk_phone' => 'call me maybe' ) );
	twd_sk_eq( '', TWD_SK_Enquiry::check( $post, $now )['fields']['phone'] );
	twd_sk_eq( 3 + 1 + 1, get_option( TWD_SK_Enquiry::STATS )['refused'] );
} );

twd_sk_test( 'enquiry: the honeypot looks like success to a robot but sends nothing', function () {
	twd_sk_enq_setup();
	list( $post, $now ) = twd_sk_enq_post( array( 'twd_sk_website' => 'buy now' ) );
	twd_sk_has( 'twd_sk_sent=1', TWD_SK_Enquiry::process( $post, '203.0.113.5', $now ) );
	twd_sk_eq( 0, count( $GLOBALS['twd_stub']['mails'] ) );
} );

twd_sk_test( 'enquiry: five an hour from one visitor, fifty a day for the site; a failed email says so', function () {
	twd_sk_enq_setup();
	for ( $i = 0; $i < TWD_SK_Enquiry::PER_VISITOR; $i++ ) {
		list( $post, $now ) = twd_sk_enq_post();
		twd_sk_has( 'twd_sk_sent=1', TWD_SK_Enquiry::process( $post, '203.0.113.5', $now ) );
	}
	list( $post, $now ) = twd_sk_enq_post();
	twd_sk_has( 'twd_sk_error=limit', TWD_SK_Enquiry::process( $post, '203.0.113.5', $now ) );
	twd_sk_has( 'twd_sk_sent=1', TWD_SK_Enquiry::process( $post, '198.51.100.7', $now ), 'another visitor is fine' );
	set_transient( 'twd_sk_enq_day', TWD_SK_Enquiry::PER_DAY, 86400 );
	twd_sk_has( 'twd_sk_error=limit', TWD_SK_Enquiry::process( $post, '192.0.2.9', $now ), 'the site limit' );
	delete_transient( 'twd_sk_enq_day' );
	$GLOBALS['twd_stub']['mail_fails'] = true;
	twd_sk_has( 'twd_sk_error=failed', TWD_SK_Enquiry::process( $post, '192.0.2.10', $now ) );
	$GLOBALS['twd_stub']['mail_fails'] = false;
	twd_sk_eq( 1, get_option( TWD_SK_Enquiry::STATS )['failed'] );
} );

twd_sk_test( 'enquiry: a placeholder or sample site email falls back to the admin email; the address can be filtered; safe mode refuses', function () {
	twd_sk_enq_setup();
	TWD_SK_Profile::save( array( 'email' => 'you@example.com' ) );
	twd_sk_eq( 'admin@example.net', TWD_SK_Enquiry::recipient() );
	add_filter( 'twd_sk_enquiry_recipient', function () {
		return 'desk@example.org';
	} );
	twd_sk_eq( 'desk@example.org', TWD_SK_Enquiry::recipient() );
	TWD_SK_Safe::set( true );
	list( $post, $now ) = twd_sk_enq_post();
	twd_sk_has( 'twd_sk_error=off', TWD_SK_Enquiry::process( $post, '203.0.113.5', $now ) );
	twd_sk_eq( 0, count( $GLOBALS['twd_stub']['mails'] ) );
	twd_sk_has( 'switched off', TWD_SK_Enquiry::render() );
	twd_sk_hasnt( '<form', TWD_SK_Enquiry::render() );
	TWD_SK_Safe::set( false );
	list( $post, $now ) = twd_sk_enq_post( array( 'twd_sk_page' => '999', 'twd_sk_form' => 'evil"><script>' ) );
	$url = TWD_SK_Enquiry::process( $post, '203.0.113.5', $now );
	twd_sk_true( 0 === strpos( $url, 'https://example.test/' ), 'an unknown page goes back home' );
	twd_sk_has( '#twd-sk-enquiry', $url );
	twd_sk_hasnt( 'script', $url );
} );

twd_sk_test( 'enquiry form: built at display time with labels, a signed token, a hidden trap, required fields and the result message', function () {
	twd_sk_enq_setup();
	$GLOBALS['twd_stub']['post_id'] = 12;
	TWD_SK_Enquiry::reset();
	$html = TWD_SK_Enquiry::render();
	foreach ( array( 'action="https://example.test/wp-admin/admin-post.php"', 'name="action" value="twd_sk_enquiry"', 'name="twd_sk_t" value="', 'class="twd-sk-form__hp" aria-hidden="true"', 'tabindex="-1"', 'type="email"', 'name="twd_sk_consent" value="1" required', 'autocomplete="name"', 'Send message' ) as $needle ) {
		twd_sk_has( $needle, $html );
	}
	preg_match_all( '/<label class="twd-sk-form__label" for="([^"]+)"/', $html, $labels );
	foreach ( $labels[1] as $for ) {
		twd_sk_has( 'id="' . $for . '"', $html, 'label ' . $for . ' has its field' );
	}
	twd_sk_has( 'call 999', $html, 'therapy help line' );
	TWD_SK_Enquiry::reset();
	$_GET['twd_sk_sent'] = '1';
	twd_sk_has( 'role="status">Thank you.', TWD_SK_Enquiry::render() );
	TWD_SK_Enquiry::reset();
	unset( $_GET['twd_sk_sent'] );
	$_GET['twd_sk_error'] = '<script>';
	twd_sk_hasnt( 'role="alert"', TWD_SK_Enquiry::render(), 'an unknown code shows nothing' );
	TWD_SK_Enquiry::reset();
	$_GET['twd_sk_error'] = 'consent';
	twd_sk_has( 'role="alert">Please tick the box', TWD_SK_Enquiry::render() );
	unset( $_GET['twd_sk_error'] );
	twd_sk_has( 'id="twd-sk-enquiry-', TWD_SK_Enquiry::render(), 'a second form on a page gets its own id' );
	TWD_SK_Mode::set( 'business' );
	twd_sk_has( 'We will reply as soon as we can.', TWD_SK_Enquiry::render() );
} );

twd_sk_test( 'enquiry in pages: the shortcode survives cleaning with no attributes; a pasted form is still removed; the report shows counts only', function () {
	$r = TWD_SK_Sanitizer::clean_with_report( '<section class="twd-sk-enquiry"><div class="twd-sk-inner">[twd_enquiry to="spam@example.com"]<form action="x"><input name="a"></form></div></section>' );
	twd_sk_has( '[twd_enquiry]', $r['html'] );
	twd_sk_hasnt( 'spam@', $r['html'] );
	twd_sk_hasnt( '<form', $r['html'] );
	twd_sk_enq_setup();
	list( $post, $now ) = twd_sk_enq_post();
	TWD_SK_Enquiry::process( $post, '203.0.113.5', $now );
	$rep = TWD_SK_Doctor::report();
	twd_sk_has( 'Enquiry form emails go to: the email in the site details; sent 1, failed 0, refused 0', $rep );
	twd_sk_hasnt( 'owner@example.org', $rep );
	twd_sk_hasnt( 'alex@example.com', $rep );
	twd_sk_hasnt( 'Can I book', $rep );
	$hooks = $GLOBALS['twd_stub']['hooks'];
	$GLOBALS['twd_stub']['hooks'] = array();
	TWD_SK_Enquiry::init();
	twd_sk_true( isset( $GLOBALS['twd_stub']['hooks']['admin_post_nopriv_twd_sk_enquiry'], $GLOBALS['twd_stub']['hooks']['admin_post_twd_sk_enquiry'] ) );
	twd_sk_true( isset( $GLOBALS['twd_stub']['shortcodes']['twd_enquiry'] ) );
	$GLOBALS['twd_stub']['hooks'] = $hooks;
} );
