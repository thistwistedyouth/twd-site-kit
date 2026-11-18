<?php
// The enquiry form's own settings (0.22.0): where it goes, a copy, the thank-you, the honeypot, the speed check, the limit
// per visitor, and Cloudflare Turnstile. Uses the helpers in test-enquiry.php.

function twd_sk_es_keys() {
	return array( 'captcha' => 'turnstile', 'turnstile_site' => '0x4AAAAAAAexample_site', 'turnstile_secret' => '0x4AAAAAAAexample_secret_key' );
}

twd_sk_test( 'enquiry settings: a site that never opened the panel behaves exactly as before (honeypot on, 3 seconds, 5 an hour, no captcha)', function () {
	twd_sk_enq_setup();
	$s = TWD_SK_Enquiry_Settings::get();
	twd_sk_eq( true, $s['honeypot'] );
	twd_sk_eq( 3, $s['min_seconds'] );
	twd_sk_eq( 5, $s['per_visitor'] );
	twd_sk_eq( 'none', $s['captcha'] );
	twd_sk_eq( false, TWD_SK_Enquiry_Settings::turnstile_on() );
	twd_sk_eq( 'owner@example.org', TWD_SK_Enquiry::recipient() );
	twd_sk_eq( TWD_SK_Enquiry::messages()['sent'], TWD_SK_Enquiry::message( 'sent' ) );
} );

twd_sk_test( 'enquiry settings: the address, the copy and the thank-you are used; a bad address is refused with a plain message and nothing is saved', function () {
	twd_sk_enq_setup();
	$out = TWD_SK_Enquiry_Settings::save( array( 'to' => 'johnny@example.org', 'cc' => 'copy@example.org', 'thanks' => 'Thank you, I will be in touch.' ) );
	twd_sk_true( ! is_wp_error( $out ), is_wp_error( $out ) ? $out->get_error_message() : '' );
	twd_sk_eq( 'johnny@example.org', TWD_SK_Enquiry::recipient() );
	twd_sk_eq( 'Thank you, I will be in touch.', TWD_SK_Enquiry::message( 'sent' ) );
	list( $post, $now ) = twd_sk_enq_post();
	TWD_SK_Enquiry::process( $post, '203.0.113.5', $now );
	$m = $GLOBALS['twd_stub']['mails'][0];
	twd_sk_eq( 'johnny@example.org', $m['to'] );
	twd_sk_true( in_array( 'Cc: copy@example.org', $m['headers'], true ), 'the copy is a Cc header' );
	$crlf = chr( 13 ) . chr( 10 );
	foreach ( array( 'not an address', 'a@example.org' . $crlf . 'Bcc: x@example.org', 'a@example.org,b@example.org' ) as $bad ) {
		$e = TWD_SK_Enquiry_Settings::save( array( 'to' => $bad ) );
		twd_sk_true( is_wp_error( $e ), 'refused: ' . json_encode( $bad ) );
	}
	twd_sk_eq( 'johnny@example.org', TWD_SK_Enquiry::recipient(), 'the saved address stays' );
	twd_sk_true( is_wp_error( TWD_SK_Enquiry_Settings::save( array( 'thanks' => str_repeat( 'x', 301 ) ) ) ) );
	twd_sk_true( is_wp_error( TWD_SK_Enquiry_Settings::save( array( 'min_seconds' => 99 ) ) ) );
	twd_sk_true( is_wp_error( TWD_SK_Enquiry_Settings::save( array( 'per_visitor' => 0 ) ) ) );
} );

twd_sk_test( 'enquiry settings: the honeypot can be switched off (the field is not printed and a filled one no longer traps), and the speed check follows the settings', function () {
	twd_sk_enq_setup();
	$GLOBALS['twd_stub']['post_id'] = 12;
	TWD_SK_Enquiry::reset();
	twd_sk_has( 'twd_sk_website', TWD_SK_Enquiry::render() );
	TWD_SK_Enquiry_Settings::save( array( 'honeypot' => false ) );
	TWD_SK_Enquiry::reset();
	twd_sk_hasnt( 'twd_sk_website', TWD_SK_Enquiry::render() );
	list( $post, $now ) = twd_sk_enq_post( array( 'twd_sk_website' => 'spam.example' ) );
	TWD_SK_Enquiry::process( $post, '203.0.113.6', $now );
	twd_sk_eq( 1, count( $GLOBALS['twd_stub']['mails'] ), 'with the honeypot off a filled field is not a trap' );
	TWD_SK_Enquiry_Settings::save( array( 'honeypot' => true, 'min_seconds' => 10 ) );
	$now = 1700000000;
	twd_sk_eq( 'too_fast', TWD_SK_Enquiry::check_token( TWD_SK_Enquiry::token( 12, $now - 5 ), 12, $now ) );
	twd_sk_eq( '', TWD_SK_Enquiry::check_token( TWD_SK_Enquiry::token( 12, $now - 11 ), 12, $now ) );
} );

twd_sk_test( 'enquiry settings: Turnstile needs both keys, the secret key is never handed back, a blank secret keeps the saved one, and it can be cleared', function () {
	twd_sk_enq_setup();
	twd_sk_true( is_wp_error( TWD_SK_Enquiry_Settings::save( array( 'captcha' => 'turnstile', 'turnstile_site' => '0x4AAAAAAAexample_site' ) ) ), 'the secret key is missing' );
	twd_sk_true( is_wp_error( TWD_SK_Enquiry_Settings::save( array( 'captcha' => 'turnstile', 'turnstile_site' => 'bad key!', 'turnstile_secret' => 'x' ) ) ), 'keys with odd characters are refused' );
	$ok = TWD_SK_Enquiry_Settings::save( twd_sk_es_keys() );
	twd_sk_true( ! is_wp_error( $ok ) );
	twd_sk_eq( true, $ok['secret_set'] );
	twd_sk_true( ! array_key_exists( 'turnstile_secret', $ok ), 'the panel never gets the secret key' );
	twd_sk_hasnt( 'example_secret_key', json_encode( TWD_SK_Enquiry_Settings::for_panel() ) );
	twd_sk_eq( true, TWD_SK_Enquiry_Settings::turnstile_on() );
	TWD_SK_Enquiry_Settings::save( array( 'turnstile_site' => '0x4AAAAAAAnewsitekey1', 'turnstile_secret' => '' ) );
	twd_sk_eq( '0x4AAAAAAAexample_secret_key', TWD_SK_Enquiry_Settings::get()['turnstile_secret'], 'blank keeps the saved secret' );
	TWD_SK_Enquiry_Settings::save( array( 'captcha' => 'none', 'clear_secret' => true ) );
	twd_sk_eq( '', TWD_SK_Enquiry_Settings::get()['turnstile_secret'] );
	twd_sk_eq( false, TWD_SK_Enquiry_Settings::turnstile_on() );
} );

twd_sk_test( 'enquiry settings: with Turnstile on the form carries the widget and loads the Cloudflare script; off, nothing of it appears', function () {
	twd_sk_enq_setup();
	$GLOBALS['twd_stub']['post_id'] = 12;
	TWD_SK_Enquiry::reset();
	twd_sk_hasnt( 'cf-turnstile', TWD_SK_Enquiry::render() );
	TWD_SK_Enquiry_Settings::save( twd_sk_es_keys() );
	TWD_SK_Enquiry::reset();
	$html = TWD_SK_Enquiry::render();
	twd_sk_has( 'class="cf-turnstile" data-sitekey="0x4AAAAAAAexample_site"', $html );
	twd_sk_hasnt( 'example_secret_key', $html );
	twd_sk_true( ! empty( $GLOBALS['twd_stub']['scripts']['twd-sk-turnstile']['enqueued'] ) );
	twd_sk_eq( 'https://challenges.cloudflare.com/turnstile/v0/api.js', $GLOBALS['twd_stub']['scripts']['twd-sk-turnstile']['src'] );
} );

twd_sk_test( 'enquiry settings: with Turnstile on, a message with no answer or a failed one is refused and sends nothing; a good one sends; Cloudflare being unreachable lets it through and is counted', function () {
	twd_sk_enq_setup();
	TWD_SK_Enquiry_Settings::save( twd_sk_es_keys() );
	$GLOBALS['twd_sk_es_answer'] = 'ok';
	add_filter( 'twd_sk_turnstile_verify', function ( $pre, $token ) {
		return '' === $token ? 'fail' : $GLOBALS['twd_sk_es_answer'];
	}, 10, 2 );
	list( $post, $now ) = twd_sk_enq_post();
	twd_sk_has( 'twd_sk_error=captcha', TWD_SK_Enquiry::process( $post, '203.0.113.7', $now ), 'no answer' );
	twd_sk_eq( 0, count( $GLOBALS['twd_stub']['mails'] ) );
	list( $post, $now ) = twd_sk_enq_post( array( 'cf-turnstile-response' => 'tok' ) );
	$GLOBALS['twd_sk_es_answer'] = 'fail';
	twd_sk_has( 'twd_sk_error=captcha', TWD_SK_Enquiry::process( $post, '203.0.113.8', $now ) );
	twd_sk_eq( 0, count( $GLOBALS['twd_stub']['mails'] ) );
	$GLOBALS['twd_sk_es_answer'] = 'ok';
	twd_sk_has( 'twd_sk_sent=1', TWD_SK_Enquiry::process( $post, '203.0.113.9', $now ) );
	twd_sk_eq( 1, count( $GLOBALS['twd_stub']['mails'] ) );
	$GLOBALS['twd_sk_es_answer'] = 'unreachable';
	twd_sk_has( 'twd_sk_sent=1', TWD_SK_Enquiry::process( $post, '203.0.113.10', $now ) );
	twd_sk_eq( 2, count( $GLOBALS['twd_stub']['mails'] ) );
	twd_sk_eq( 1, TWD_SK_Enquiry::stats()['captcha_unreachable'] );
	twd_sk_eq( 'fail', TWD_SK_Enquiry_Settings::verify( '', '203.0.113.1' ), 'an empty answer never reaches Cloudflare' );
} );

twd_sk_test( 'enquiry settings: the route is for administrators, and the editor shows Form settings with a password-type secret field, sending keys only to its own route', function () {
	$rest = preg_replace( '/\s+/', ' ', file_get_contents( ABSPATH . 'includes/class-twd-sk-rest.php' ) );
	twd_sk_has( "'/site/enquiry'", $rest );
	twd_sk_has( "'callback' => array( __CLASS__, 'post_site_enquiry' ), 'permission_callback' => array( __CLASS__, 'can_manage_site' )", $rest );
	$js = file_get_contents( ABSPATH . 'assets/twd-site-kit-inline.js' );
	twd_sk_has( 'drawFormSettings', $js );
	twd_sk_has( "'/site/enquiry'", $js );
	twd_sk_has( "type: 'password'", $js );
} );
