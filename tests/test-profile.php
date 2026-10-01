<?php
// The site profile: validation, storage and the leftover check.

function twd_sk_profile_full() {
	return array(
		'site_name'    => 'Calm Practice',
		'logo_id'      => 0,
		'menu'         => array(
			array( 'label' => 'Home', 'url' => '/', 'children' => array() ),
			array( 'label' => 'Services', 'url' => '/services', 'children' => array( array( 'label' => 'Anxiety', 'url' => '/services/anxiety' ) ) ),
		),
		'cta_label'    => 'Book a call',
		'cta_url'      => '/contact',
		'phone'        => '01234 567890',
		'email'        => 'hello@calm.example',
		'address'      => array( '1 High Street', 'Townsville' ),
		'area_served'  => 'Online and in person',
		'footer_text'  => 'Gentle, practical therapy.',
		'legal'        => array( array( 'label' => 'Privacy', 'url' => '/privacy' ) ),
		'registration' => array( 'Member of an example body' ),
		'person_name'  => 'Alex Example',
		'person_job'   => 'Counsellor',
		'same_as'      => array( 'https://profile.example/alex' ),
		'about_page_id' => 0,
	);
}

twd_sk_test( 'profile: a full valid profile is stored and read back unchanged', function () {
	$saved = TWD_SK_Profile::save( twd_sk_profile_full() );
	twd_sk_true( ! is_wp_error( $saved ), is_wp_error( $saved ) ? $saved->get_error_message() : '' );
	twd_sk_eq( twd_sk_profile_full(), TWD_SK_Profile::get() );
} );

twd_sk_test( 'profile: an empty site has an empty, well formed profile', function () {
	twd_sk_eq( TWD_SK_Profile::defaults(), TWD_SK_Profile::get() );
	twd_sk_true( TWD_SK_Profile::is_empty() );
	TWD_SK_Profile::save( array( 'site_name' => 'X' ) );
	twd_sk_true( ! TWD_SK_Profile::is_empty() );
} );

twd_sk_test( 'profile: a part can be saved and the rest is kept', function () {
	TWD_SK_Profile::save( twd_sk_profile_full() );
	TWD_SK_Profile::save( array( 'phone' => '07000 000000' ) );
	$p = TWD_SK_Profile::get();
	twd_sk_eq( '07000 000000', $p['phone'] );
	twd_sk_eq( 'Calm Practice', $p['site_name'] );
} );

twd_sk_test( 'profile: text is plain, tidy, free of long dashes and cut to its limit', function () {
	$long = str_repeat( 'a', 200 );
	TWD_SK_Profile::save( array( 'site_name' => "  <b>Calm</b>\n  Practice \xE2\x80\x94 North  ", 'footer_text' => $long . $long ) );
	$p = TWD_SK_Profile::get();
	twd_sk_eq( 'Calm Practice, North', $p['site_name'] );
	twd_sk_eq( 300, strlen( $p['footer_text'] ) );
	foreach ( array( "\xE2\x80\x94", "\xE2\x80\x93", '<', '>' ) as $bad ) {
		twd_sk_hasnt( $bad, $p['site_name'] );
	}
} );

twd_sk_test( 'profile: wrong types and unknown fields are refused with a plain sentence and nothing is stored', function () {
	foreach ( array(
		array( 'site_name' => array( 'x' ) ),
		array( 'logo_id' => 'abc' ),
		array( 'logo_id' => -1 ),
		array( 'menu' => 'home' ),
		array( 'menu' => array( 'x' ) ),
		array( 'phone' => 12345 ),
		array( 'email' => 'not an email' ),
		array( 'email' => 'a@b' ),
		array( 'cta_url' => 'javascript:alert(1)' ),
		array( 'legal' => array( array( 'label' => 'x' ) ) ),
		array( 'same_as' => array( 'http://plain.example' ) ),
		array( 'same_as' => array( 'javascript:x' ) ),
		array( 'nope' => 'x' ),
		array( 7 => 'x' ),
	) as $bad ) {
		$e = TWD_SK_Profile::save( $bad );
		twd_sk_is_error( 'twd_sk_bad_profile', $e, json_encode( $bad ) );
		twd_sk_true( strlen( $e->get_error_message() ) > 10 );
	}
	twd_sk_true( TWD_SK_Profile::is_empty(), 'nothing stored' );
	twd_sk_is_error( 'twd_sk_bad_profile', TWD_SK_Profile::save( 'x' ) );
} );

twd_sk_test( 'profile security: menu, button and legal links accept only safe addresses', function () {
	foreach ( array( 'javascript:alert(1)', 'data:text/html,x', 'vbscript:x', '//evil.example', '/\\evil.example', 'file:///etc/passwd', 'java\tscript:x', 'http://user:pw@evil.example', '[twd_page]' ) as $bad ) {
		twd_sk_is_error( 'twd_sk_bad_profile', TWD_SK_Profile::save( array( 'menu' => array( array( 'label' => 'x', 'url' => $bad ) ) ) ), $bad );
		twd_sk_is_error( 'twd_sk_bad_profile', TWD_SK_Profile::save( array( 'cta_url' => $bad ) ), $bad );
		twd_sk_is_error( 'twd_sk_bad_profile', TWD_SK_Profile::save( array( 'legal' => array( array( 'label' => 'x', 'url' => $bad ) ) ) ), $bad );
	}
	foreach ( array( '/about', '/', '#top', 'https://ok.example/x', 'mailto:a@b.example', 'tel:01234567890' ) as $good ) {
		twd_sk_true( ! is_wp_error( TWD_SK_Profile::save( array( 'menu' => array( array( 'label' => 'x', 'url' => $good ) ) ) ) ), $good );
	}
} );

twd_sk_test( 'profile: size limits (8 menu items, 6 sub links, 6 legal, 4 registration, 3 address, 4 profile links)', function () {
	$item = array( 'label' => 'x', 'url' => '/x' );
	twd_sk_true( ! is_wp_error( TWD_SK_Profile::save( array( 'menu' => array_fill( 0, 8, $item ) ) ) ) );
	twd_sk_is_error( 'twd_sk_bad_profile', TWD_SK_Profile::save( array( 'menu' => array_fill( 0, 9, $item ) ) ) );
	twd_sk_is_error( 'twd_sk_bad_profile', TWD_SK_Profile::save( array( 'menu' => array( array( 'label' => 'x', 'url' => '/x', 'children' => array_fill( 0, 7, $item ) ) ) ) ) );
	twd_sk_is_error( 'twd_sk_bad_profile', TWD_SK_Profile::save( array( 'legal' => array_fill( 0, 7, $item ) ) ) );
	twd_sk_is_error( 'twd_sk_bad_profile', TWD_SK_Profile::save( array( 'registration' => array_fill( 0, 5, 'x' ) ) ) );
	twd_sk_is_error( 'twd_sk_bad_profile', TWD_SK_Profile::save( array( 'address' => array_fill( 0, 4, 'x' ) ) ) );
	twd_sk_is_error( 'twd_sk_bad_profile', TWD_SK_Profile::save( array( 'same_as' => array_fill( 0, 5, 'https://p.example/x' ) ) ) );
} );

twd_sk_test( 'profile: the logo must be a picture in the media library', function () {
	$GLOBALS['twd_stub']['attachments'][50] = array( 'url' => '/u/logo.png', 'w' => 200, 'h' => 80, 'is_image' => true );
	$GLOBALS['twd_stub']['attachments'][51] = array( 'url' => '/u/doc.pdf', 'w' => 0, 'h' => 0, 'is_image' => false );
	twd_sk_true( ! is_wp_error( TWD_SK_Profile::save( array( 'logo_id' => 50 ) ) ) );
	twd_sk_eq( 50, TWD_SK_Profile::get()['logo_id'] );
	twd_sk_true( ! is_wp_error( TWD_SK_Profile::save( array( 'logo_id' => '50' ) ) ) );
	twd_sk_is_error( 'twd_sk_bad_profile', TWD_SK_Profile::save( array( 'logo_id' => 51 ) ) );
	twd_sk_is_error( 'twd_sk_bad_profile', TWD_SK_Profile::save( array( 'logo_id' => 999 ) ) );
	twd_sk_true( ! is_wp_error( TWD_SK_Profile::save( array( 'logo_id' => 0 ) ) ), 'no logo is fine' );
} );

twd_sk_test( 'profile: a hand-edited option is cleaned again on the way out', function () {
	$GLOBALS['twd_stub']['options'][ TWD_SK_Profile::OPTION ] = array(
		'site_name' => '<script>x()</script>Name',
		'menu'      => array( array( 'label' => 'Bad', 'url' => 'javascript:x' ) ),
		'phone'     => 123,
		'extra'     => 'ignored',
	);
	$p = TWD_SK_Profile::get();
	twd_sk_eq( 'x()Name', $p['site_name'] );
	twd_sk_eq( array(), $p['menu'] );
	twd_sk_eq( '', $p['phone'] );
	twd_sk_true( ! isset( $p['extra'] ) );
} );

twd_sk_test( 'profile: the leftover check reads the profile and splits by level', function () {
	twd_sk_eq( array( 'must' => array(), 'check' => array() ), TWD_SK_Profile::leftovers( twd_sk_profile_full() ) );
	$by = TWD_SK_Profile::leftovers( TWD_SK_Profile::starter() );
	twd_sk_true( isset( $by['must']['[PLACEHOLDER'], $by['must']['PHONE_NUMBER'], $by['must']['example.com'] ), 'starter is flagged' );
} );

twd_sk_test( 'profile: the starter profile is valid, invents nothing, and every text field in it is flagged', function () {
	$starter = TWD_SK_Profile::starter();
	$check   = TWD_SK_Profile::validate( $starter );
	twd_sk_eq( array(), $check['errors'] );
	twd_sk_eq( $starter, array_merge( TWD_SK_Profile::defaults(), $check['valid'] ) );
	foreach ( array( 'site_name', 'footer_text', 'person_name', 'person_job', 'area_served', 'phone', 'email' ) as $field ) {
		twd_sk_true( TWD_SK_Sanitizer::find_leftovers( $starter[ $field ] ) !== array(), $field . ' is flagged' );
	}
	foreach ( array( 'address', 'registration' ) as $field ) {
		foreach ( $starter[ $field ] as $line ) {
			twd_sk_true( TWD_SK_Sanitizer::find_leftovers( $line ) !== array(), $field . ' line is flagged' );
		}
	}
	foreach ( $starter['legal'] as $l ) {
		twd_sk_true( TWD_SK_Sanitizer::find_leftovers( $l['label'] ) !== array(), 'legal label is flagged' );
	}
	twd_sk_eq( array(), $starter['same_as'], 'no invented profile links' );
	twd_sk_eq( 0, $starter['logo_id'] );
} );

twd_sk_test( 'profile: missing() lists what a real header needs', function () {
	twd_sk_eq( 3, count( TWD_SK_Profile::missing( TWD_SK_Profile::defaults() ) ) );
	twd_sk_eq( array(), TWD_SK_Profile::missing( twd_sk_profile_full() ) );
} );

twd_sk_test( 'profile: the class holds no client details, no network and no em dashes', function () {
	$src = file_get_contents( ABSPATH . 'includes/class-twd-sk-profile.php' );
	foreach ( array( 'wp_remote_', 'file_put_contents', 'TWD_SK_Store', 'eval(' ) as $bad ) {
		twd_sk_hasnt( $bad, $src );
	}
	twd_sk_hasnt( "\xE2\x80\x94", $src );
} );
