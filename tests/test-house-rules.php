<?php
// The wording and address check (0.18.1): TWD_SK_HouseRules reads the rules written in the facts under WORDING CHECKS,
// checks every page's words (never tags or attributes), always checks long dashes and staging addresses, and fixes the
// ones that have a fix, one version per page.

function twd_sk_hr_setup() {
	twd_stub_reset();
	twd_sk_rest_setup( array( 'edit_pages', 'edit_post:340', 'edit_post:341', 'manage_options' ) );
	foreach ( array( 340, 341 ) as $id ) {
		twd_stub_add_post( $id, 'page', '[twd_page]' );
	}
	$GLOBALS['twd_stub']['posts'][340]->post_title = 'About';
	$GLOBALS['twd_stub']['posts'][341]->post_title = 'Contact';
	TWD_SK_Store::save( 340, '<section class="twd-sk-hero twd-sk-hero--compact twd-sk-tone-base"><div class="twd-sk-inner"><h1 class="twd-sk-hero__title">About Dr Smith</h1></div></section><section class="twd-sk-text twd-sk-tone-base"><div class="twd-sk-inner"><p>I am Dr Smith, a psychologist. Dr. Smith works gently. On a journey to healing, we begin.</p><p><a href="https://example.test/contact/">Contact Dr Smith</a></p><img src="https://site.twdstaging.example/wp-content/uploads/a.jpg" alt="Dr Smith in the garden"></div></section>' );
	TWD_SK_Store::save( 341, '<section class="twd-sk-hero twd-sk-hero--compact twd-sk-tone-base"><div class="twd-sk-inner"><h1 class="twd-sk-hero__title">Contact</h1><p>Email us, or phone. The Dr Smithson clinic is next door.</p></div></section>' );
	TWD_SK_Facts::save( "WHO I AM\nA psychologist.\n\nWORDING CHECKS\nnever: Dr Smith -> Dr. Smith\nnever: on a journey to healing\nnever: \n\nTHINGS I NEVER SAY OR PROMISE\nNever promise a cure.\nnever: this line is under another heading" );
}

twd_sk_test( 'house rules: the rules come from the facts under WORDING CHECKS only, with an optional fix after an arrow', function () {
	twd_sk_hr_setup();
	$rules = TWD_SK_HouseRules::rules();
	twd_sk_eq( 2, count( $rules ), 'a blank rule and a line under another heading are not rules' );
	twd_sk_eq( array( 'phrase' => 'Dr Smith', 'replacement' => 'Dr. Smith' ), $rules[0] );
	twd_sk_eq( array( 'phrase' => 'on a journey to healing', 'replacement' => '' ), $rules[1] );
	TWD_SK_Facts::save( "WORDING CHECKS\nnever: " . str_repeat( 'x', 120 ) . "\n" );
	twd_sk_eq( array(), TWD_SK_HouseRules::rules(), 'a rule over 80 characters is ignored' );
	TWD_SK_Facts::save( 'No heading here.' );
	twd_sk_eq( array(), TWD_SK_HouseRules::rules() );
} );

twd_sk_test( 'house rules: a page is checked in its words only, as whole words, and a long dash or a staging address is always found', function () {
	twd_sk_hr_setup();
	$f = TWD_SK_HouseRules::scan_page( TWD_SK_Store::get_current( 340 ) );
	twd_sk_true( isset( $f['never:Dr Smith'] ), 'found' );
	twd_sk_eq( 3, $f['never:Dr Smith']['count'], 'the heading, the sentence and the link words; not the alt text, not "Dr. Smith"' );
	twd_sk_true( $f['never:Dr Smith']['fix'], 'it has a fix' );
	twd_sk_has( 'Dr Smith', $f['never:Dr Smith']['sample'] );
	twd_sk_true( isset( $f['never:on a journey to healing'] ) && ! $f['never:on a journey to healing']['fix'], 'a rule with no arrow is reported only , found whatever its capitals' );
	twd_sk_true( isset( $f['address'] ) && 1 === $f['address']['count'], 'a staging address in a picture' );
	twd_sk_true( $f['address']['fix'] );
	twd_sk_hasnt( 'long_dash', implode( ',', array_keys( $f ) ) );
	// Whole words: "Dr Smithson" is not "Dr Smith".
	$g = TWD_SK_HouseRules::scan_page( TWD_SK_Store::get_current( 341 ) );
	twd_sk_eq( array(), array_keys( $g ), 'Dr Smithson is not Dr Smith' );
	// A long dash in the words is always found (it is put in directly: the sanitiser would remove it from a save).
	$d = TWD_SK_HouseRules::scan_page( '<p>A calm ' . "\xE2\x80\x94" . ' steady way</p><p>x' . "\xE2\x80\x93" . 'y</p>', array() );
	twd_sk_eq( 2, $d['long_dash']['count'] );
	// An attribute is never searched: the same words inside a tag do not count.
	$e = TWD_SK_HouseRules::scan_page( '<p title="Dr Smith">Hello</p>', TWD_SK_HouseRules::rules() );
	twd_sk_eq( array(), array_keys( $e ), 'inside an attribute does not count' );
} );

twd_sk_test( 'house rules: fixing changes the words that have a fix and the staging addresses to paths, leaves the rest, and saves each page as one version with Undo', function () {
	twd_sk_hr_setup();
	$before_341 = TWD_SK_Store::get_current( 341 );
	$res = TWD_SK_HouseRules::fix( array( 'never:Dr Smith', 'address' ) );
	twd_sk_eq( 1, $res['done'], json_encode( $res ) );
	twd_sk_eq( 0, $res['failed'] );
	twd_sk_eq( 1, count( $res['results'] ), 'only the page with something to fix' );
	$now = TWD_SK_Store::get_current( 340 );
	twd_sk_has( 'About Dr. Smith', $now );
	twd_sk_has( 'I am Dr. Smith, a psychologist', $now );
	twd_sk_has( 'Contact Dr. Smith</a>', $now );
	twd_sk_has( 'alt="Dr Smith in the garden"', $now, 'an attribute is untouched' );
	twd_sk_has( 'src="/wp-content/uploads/a.jpg"', $now, 'the staging address became a path' );
	twd_sk_hasnt( 'twdstaging', $now );
	twd_sk_has( 'On a journey to healing', $now, 'a rule with no fix leaves the words alone' );
	twd_sk_has( 'href="https://example.test/contact/"', $now, 'an address that is not staging is left alone' );
	twd_sk_eq( $before_341, TWD_SK_Store::get_current( 341 ), 'the other page is untouched' );
	twd_sk_eq( array(), array_keys( TWD_SK_HouseRules::scan_page( $now, array() ) ), 'nothing left to fix' );
	$u = TWD_SK_Store::undo( 340 );
	twd_sk_true( ! is_wp_error( $u ), is_wp_error( $u ) ? $u->get_error_message() : '' );
	twd_sk_has( 'I am Dr Smith, a psychologist', TWD_SK_Store::get_current( 340 ), 'Undo takes the whole fix back' );
	// Unknown or unticked rules change nothing.
	twd_sk_eq( 0, TWD_SK_HouseRules::fix( array( 'never:something else' ) )['done'] );
} );

twd_sk_test( 'house rules: the launch check says what is broken and where, or that the rules are met', function () {
	twd_sk_hr_setup();
	$items = array();
	foreach ( TWD_SK_Launch::check()['items'] as $i ) {
		$items[ $i['id'] ] = $i;
	}
	twd_sk_true( isset( $items['wording'] ) && 'warn' === $items['wording']['status'], 'a warning' );
	twd_sk_has( 'Dr Smith', $items['wording']['detail'] );
	twd_sk_has( 'Wording and address check', $items['wording']['detail'], 'it points at the fix' );
	TWD_SK_HouseRules::fix( array( 'never:Dr Smith', 'address' ) );
	TWD_SK_Facts::save( "WORDING CHECKS\nnever: Dr Smith -> Dr. Smith\n" );
	$items = array();
	foreach ( TWD_SK_Launch::check()['items'] as $i ) {
		$items[ $i['id'] ] = $i;
	}
	twd_sk_eq( 'ok', $items['wording']['status'], 'rules met' );
	TWD_SK_Facts::save( 'No rules.' );
	$ids = array();
	foreach ( TWD_SK_Launch::check()['items'] as $i ) {
		$ids[] = $i['id'];
	}
	twd_sk_true( ! in_array( 'wording', $ids, true ), 'no rules and nothing broken: no line' );
} );

twd_sk_test( 'house rules routes and screen: administrators only, the fix needs a ticked rule, and the Site tab section checks then fixes', function () {
	twd_sk_hr_setup();
	$r = TWD_SK_REST::get_site_rules( twd_sk_rest_req() );
	twd_sk_true( isset( $r['rules'], $r['pages'], $r['totals'] ) && count( $r['pages'] ) >= 1, 'the scan' );
	twd_sk_eq( 400, twd_sk_status( TWD_SK_REST::post_site_rules_fix( twd_sk_rest_req( array( 'rules' => array() ) ) ) ) );
	twd_sk_eq( 400, twd_sk_status( TWD_SK_REST::post_site_rules_fix( twd_sk_rest_req( array( 'rules' => 'x' ) ) ) ) );
	$res = TWD_SK_REST::post_site_rules_fix( twd_sk_rest_req( array( 'rules' => array( 'never:Dr Smith' ) ) ) );
	twd_sk_eq( 1, $res['done'] );
	$rest = file_get_contents( ABSPATH . 'includes/class-twd-sk-rest.php' );
	twd_sk_true( 1 === preg_match( "#'/site/rules/fix'.*?can_manage_site#s", $rest ) && 1 === preg_match( "#'/site/rules'.*?can_read_site#s", $rest ), 'permission callbacks' );
	$js = file_get_contents( ABSPATH . 'assets/twd-site-kit-editor-site.js' );
	twd_sk_has( "api('GET', '/site/rules')", $js );
	twd_sk_has( "api('POST', '/site/rules/fix'", $js );
	twd_sk_has( 'function buildWordingSection(', $js );
	twd_sk_has( 'WORDING CHECKS', $js, 'the screen says where the rules are written' );
	twd_sk_has( 'Undo works', $js );
	twd_sk_hasnt( 'innerHTML', $js );
	$src = file_get_contents( ABSPATH . 'includes/class-twd-sk-houserules.php' );
	foreach ( array( 'wp_remote', 'curl_', 'update_option', 'wpdb' ) as $bad ) {
		twd_sk_hasnt( $bad, $src, $bad );
	}
	twd_sk_true( false !== strpos( $src, 'TWD_SK_Store::save(' ), 'a fix is saved through the store' );
} );
