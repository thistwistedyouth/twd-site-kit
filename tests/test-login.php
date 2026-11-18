<?php
// TWD_SK_Login (0.22.0, stage 1): a branded sign-in page. Restyle only, off by default, off in safe mode.

twd_sk_test( 'login: off by default, hooks nothing; on, it hooks the login page only; safe mode switches it off', function () {
	twd_stub_reset();
	twd_sk_eq( false, TWD_SK_Login::on() );
	TWD_SK_Login::init();
	twd_sk_true( empty( $GLOBALS['twd_stub']['hooks']['login_enqueue_scripts'] ), 'nothing while off' );
	TWD_SK_Login::set( true );
	twd_sk_eq( true, TWD_SK_Login::on() );
	TWD_SK_Login::init();
	twd_sk_true( ! empty( $GLOBALS['twd_stub']['hooks']['login_enqueue_scripts'] ) );
	twd_sk_true( ! empty( $GLOBALS['twd_stub']['hooks']['login_message'] ) );
	twd_sk_true( empty( $GLOBALS['twd_stub']['hooks']['wp_enqueue_scripts'] ), 'no visitor page is touched' );
	update_option( 'twd_sk_safe_mode', true );
	twd_sk_eq( false, TWD_SK_Login::on(), 'safe mode' );
	twd_sk_has( 'TWD_SK_Login::init()', file_get_contents( ABSPATH . 'includes/class-twd-sk-modules.php' ) );
} );

twd_sk_test( 'login: the stylesheet uses the style tokens only, never a colour or font literal, and never touches the sign-in form itself', function () {
	twd_stub_reset();
	$css = TWD_SK_Login::css();
	twd_sk_has( ':root{', $css );
	twd_sk_has( 'var(--twd-site-color-primary)', $css );
	twd_sk_has( 'var(--twd-site-font-body)', $css );
	$rules = substr( $css, strpos( $css, '}' ) + 1 );
	twd_sk_eq( 0, preg_match( '/#[0-9a-fA-F]{3,6}\b|rgba?\(|font-family:\s*["A-Za-z]/', preg_replace( '/var\([^)]*\)/', '', $rules ) ), 'no literals in the rules' );
	twd_sk_eq( 0, preg_match( '/\d\s*(vw|vh)\b/', $css ), 'no viewport units' );
	// Text sizes stay readable.
	twd_sk_eq( 0, preg_match( '/font-size:\s*(?:[0-9]|1[0-3])px/', $css ), 'no text under 14px' );
} );

twd_sk_test( 'login: the logo comes from Site details; with none the site name is shown as words; a leftover marker is never shown', function () {
	twd_stub_reset();
	$GLOBALS['twd_stub']['attachments'][70] = array( 'is_image' => true, 'url' => 'https://example.test/wp-content/uploads/logo.png' );
	TWD_SK_Profile::save( array( 'site_name' => 'A Test Practice', 'logo_id' => 70 ) );
	twd_sk_eq( 'A Test Practice', TWD_SK_Login::header_text() );
	twd_sk_has( 'url("https://example.test/wp-content/uploads/logo.png")', TWD_SK_Login::css() );
	TWD_SK_Profile::save( array( 'site_name' => '[PLACEHOLDER: practice name]', 'logo_id' => 0 ) );
	twd_sk_eq( 'Test Site', TWD_SK_Login::header_text(), 'falls back to the WordPress site name' );
	twd_sk_hasnt( 'url(', TWD_SK_Login::css() );
	twd_sk_has( 'background-image:none', TWD_SK_Login::css() );
} );

twd_sk_test( 'login: the welcome line and the way back to the site come from the mode words, and the existing message is kept', function () {
	twd_stub_reset();
	$out = TWD_SK_Login::message( '<p class="message">Logged out.</p>' );
	twd_sk_has( 'Welcome back', $out );
	twd_sk_has( 'href="https://example.test/"', $out );
	twd_sk_has( 'Logged out.', $out );
	twd_sk_eq( 'https://example.test/', TWD_SK_Login::header_url() );
	twd_sk_true( in_array( 'twd-sk-login', TWD_SK_Login::body_class( array( 'login' ) ), true ) );
} );

twd_sk_test( 'login: the source holds no authentication code, no outside request and no stored secret', function () {
	$src = file_get_contents( ABSPATH . 'includes/class-twd-sk-login.php' );
	foreach ( array( 'wp_signon', 'wp_authenticate', 'wp_remote', 'wp_set_auth_cookie', 'update_user_meta', '$_POST' ) as $bad ) {
		twd_sk_hasnt( $bad, $src );
	}
} );
