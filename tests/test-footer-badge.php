<?php
// The registration badge and link (13 Nov 2026, from the pilot site's footer): a Site details picture (the registering body's logo)
// printed under the button in the compact footer, and a registration link that the badge and the registration lines point to. Both
// empty by default: every footer prints the same bytes as before until they are set. Uses the helpers in test-chrome.php.

twd_sk_test( 'registration badge: the two profile fields are plain, validated and empty by default', function () {
	$d = TWD_SK_Profile::defaults();
	twd_sk_eq( 0, $d['registration_logo_id'] );
	twd_sk_eq( '', $d['registration_url'] );
	twd_sk_eq( 0, count( TWD_SK_Profile::validate( array( 'registration_url' => 'https://register.example/person' ) )['errors'] ) );
	twd_sk_eq( 0, count( TWD_SK_Profile::validate( array( 'registration_url' => '' ) )['errors'] ) );
	twd_sk_eq( 1, count( TWD_SK_Profile::validate( array( 'registration_url' => 'http://register.example/person' ) )['errors'] ), 'https only' );
	twd_sk_eq( 1, count( TWD_SK_Profile::validate( array( 'registration_url' => 'javascript:alert(1)' ) )['errors'] ) );
	twd_sk_eq( 1, count( TWD_SK_Profile::validate( array( 'registration_logo_id' => 'x' ) )['errors'] ), 'a bad picture number is refused' );
} );

twd_sk_test( 'registration badge: nothing set, every footer layout prints exactly what it printed before', function () {
	twd_sk_chrome_setup( twd_sk_chrome_profile( array( 'registration' => array( 'Member of an example body' ) ) ) );
	foreach ( array( 'columns', 'band', 'centered', 'simple', 'feature', 'compact' ) as $layout ) {
		TWD_SK_Chrome::save_settings( array( 'footer_variant' => $layout ) );
		$html = TWD_SK_Chrome::render_footer();
		twd_sk_hasnt( 'twd-sk-footer__badge', $html, $layout );
		twd_sk_has( 'Member of an example body', $html, $layout );
		twd_sk_hasnt( 'href="https://register.example', $html, $layout );
	}
} );

twd_sk_test( 'registration badge: a registration link turns the registration lines into links in every layout', function () {
	twd_sk_chrome_setup( twd_sk_chrome_profile( array( 'registration' => array( 'Member of an example body' ), 'registration_url' => 'https://register.example/person' ) ) );
	foreach ( array( 'columns', 'feature', 'compact' ) as $layout ) {
		TWD_SK_Chrome::save_settings( array( 'footer_variant' => $layout ) );
		twd_sk_has( '<a class="twd-sk-footer__link" href="https://register.example/person">Member of an example body</a>', TWD_SK_Chrome::render_footer(), $layout );
	}
} );

twd_sk_test( 'registration badge: in the compact footer the picture sits under the button, linked, and on its own when the button is off', function () {
	$GLOBALS['twd_stub']['attachments'][50] = array( 'url' => 'https://example.test/wp-content/uploads/badge.png', 'w' => 420, 'h' => 228, 'is_image' => true );
	$profile = twd_sk_chrome_profile( array( 'registration' => array( 'Registered' ), 'registration_url' => 'https://register.example/person', 'registration_logo_id' => 50, 'cta_label' => 'Book a call', 'cta_url' => '/contact' ) );
	twd_sk_chrome_setup( $profile, array( 'footer_variant' => 'compact', 'footer_button' => true ) );
	$html = TWD_SK_Chrome::render_footer();
	twd_sk_has( 'twd-sk-footer__badge', $html, 'the badge is printed' );
	twd_sk_true( strpos( $html, 'twd-sk-btn' ) < strpos( $html, 'twd-sk-footer__badge' ), 'the badge comes after the button' );
	twd_sk_has( '<a class="twd-sk-footer__badge" href="https://register.example/person">', $html );
	twd_sk_has( 'alt="Registered"', $html, 'the alt text is the registration line' );
	TWD_SK_Chrome::save_settings( array( 'footer_button' => false ) );
	$off = TWD_SK_Chrome::render_footer();
	twd_sk_has( 'twd-sk-footer__row--cta', $off );
	twd_sk_hasnt( 'twd-sk-btn', $off );
} );

twd_sk_test( 'registration badge css and Site tab: the rules are in the source, and the form carries both fields', function () {
	$src = file_get_contents( ABSPATH . 'tools/kit.src.css' );
	twd_sk_has( '.twd-sk-footer__badge-img { display: block; width: auto; max-width: 160px; max-height: 64px;', $src );
	twd_sk_hasnt( 'vw', substr( $src, strpos( $src, '/* The registration badge in the compact footer (13 Nov 2026)' ) ) );
	$js = file_get_contents( ABSPATH . 'assets/twd-site-kit-editor-site.js' );
	foreach ( array( "pui.regLogo.value = p.registration_logo_id ?", "pui.regUrl.value = p.registration_url || '';", "registration_logo_id: regLogo ?", "registration_url: pui.regUrl.value.trim()," ) as $needle ) {
		twd_sk_has( $needle, $js, $needle );
	}
} );
