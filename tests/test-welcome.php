<?php
// TWD_SK_Welcome (0.22.0, stage 2): the Welcome screen with how-to pop-ups. Read only, off in safe mode, this screen only.

twd_sk_test( 'welcome: a Dashboard page for anyone who can edit pages, started from the modules, hooked in admin only', function () {
	twd_stub_reset();
	twd_sk_has( 'TWD_SK_Welcome::init()', file_get_contents( ABSPATH . 'includes/class-twd-sk-modules.php' ) );
	$GLOBALS['twd_stub']['admin'] = false;
	TWD_SK_Welcome::init();
	twd_sk_true( empty( $GLOBALS['twd_stub']['hooks']['admin_menu'] ), 'nothing on the front end' );
	$GLOBALS['twd_stub']['admin'] = true;
	TWD_SK_Welcome::init();
	twd_sk_true( ! empty( $GLOBALS['twd_stub']['hooks']['admin_menu'] ) );
	TWD_SK_Welcome::admin_menu();
	$p = $GLOBALS['twd_stub']['submenu_pages'][0];
	twd_sk_eq( 'index.php', $p['parent'] );
	twd_sk_eq( 'edit_pages', $p['cap'] );
	twd_sk_eq( 'twd-sk-welcome', $p['slug'] );
	// Safe mode: no menu item.
	twd_stub_reset();
	update_option( 'twd_sk_safe_mode', true );
	TWD_SK_Welcome::admin_menu();
	twd_sk_true( empty( $GLOBALS['twd_stub']['submenu_pages'] ), 'safe mode' );
} );

twd_sk_test( 'welcome: the page prints the buttons, a card and a hidden pop-up for every how-to, all escaped', function () {
	twd_stub_reset();
	$GLOBALS['twd_stub']['caps'] = true;
	ob_start();
	TWD_SK_Welcome::render();
	$out = ob_get_clean();
	twd_sk_has( 'Edit my website', $out );
	twd_sk_has( 'twd_sk_edit=1', $out );
	foreach ( array_keys( TWD_SK_Welcome::howtos() ) as $id ) {
		twd_sk_has( 'data-twd-sk-open="twd-sk-howto-' . $id . '"', $out );
		twd_sk_has( 'id="twd-sk-howto-' . $id . '" role="dialog" aria-modal="true" aria-labelledby="twd-sk-howto-' . $id . '-t" hidden', $out );
	}
	twd_sk_has( 'Set up and tools', $out, 'administrators get the tools button' );
	// Someone who cannot edit pages gets nothing.
	$GLOBALS['twd_stub']['caps'] = false;
	ob_start();
	TWD_SK_Welcome::render();
	twd_sk_eq( '', ob_get_clean() );
} );

twd_sk_test( 'welcome: a how-to added by a filter is kept when well formed, cleaned of odd ids, and never replaces a built-in one', function () {
	twd_stub_reset();
	add_filter( 'twd_sk_welcome_howtos', function () {
		return array(
			'Our Phone!' => array( 'title' => 'Ring us', 'steps' => array( 'Dial.' ) ),
			'words'      => array( 'title' => 'Hijack', 'steps' => array( 'x' ) ),
			'bad'        => array( 'title' => 'No steps' ),
		);
	} );
	$h = TWD_SK_Welcome::howtos();
	twd_sk_true( isset( $h['ourphone'] ) );
	twd_sk_eq( 'Change the words on a page', $h['words']['title'] );
	twd_sk_true( ! isset( $h['bad'] ) );
} );

twd_sk_test( 'welcome: the script only shows and hides, with Escape and a focus trap; no HTML built, no outside address; no long dashes', function () {
	$js  = file_get_contents( ABSPATH . 'assets/twd-site-kit-welcome.js' );
	$css = file_get_contents( ABSPATH . 'assets/twd-site-kit-welcome.css' );
	foreach ( array( 'eval(', 'new Function', 'document.write', 'innerHTML', 'outerHTML', 'insertAdjacentHTML', 'XMLHttpRequest', 'fetch(', 'localStorage', 'sendBeacon', 'onclick', 'http' ) as $bad ) {
		twd_sk_hasnt( $bad, $js );
	}
	twd_sk_has( 'Escape', $js );
	twd_sk_has( "e.key === 'Tab'", $js );
	twd_sk_eq( 0, preg_match( '/[\x{2013}\x{2014}]/u', $js . $css ) );
	twd_sk_eq( 0, preg_match( '/font-size:\s*(?:[0-9]|1[0-3])px/', $css ), 'no text under 14px' );
	$src = file_get_contents( ABSPATH . 'includes/class-twd-sk-welcome.php' );
	foreach ( array( 'update_post_meta', 'wp_remote', 'TWD_SK_Store' ) as $bad ) {
		twd_sk_hasnt( $bad, $src, 'writes no page' );
	}
} );

twd_sk_test( 'welcome: after sign-in, editors go to the Welcome screen only when switched on; administrators, other addresses and safe mode are left alone', function () {
	twd_stub_reset();
	$editor = (object) array( 'ID' => 5, 'caps' => array( 'edit_pages' ) );
	$admin  = (object) array( 'ID' => 1, 'caps' => array( 'edit_pages', 'manage_options' ) );
	$dash   = admin_url();
	twd_sk_has( 'login_redirect', file_get_contents( ABSPATH . 'includes/class-twd-sk-welcome.php' ) );
	twd_sk_eq( $dash, TWD_SK_Welcome::login_redirect( $dash, $dash, $editor ), 'off by default' );
	update_option( TWD_SK_Welcome::OPT_GO, 'yes' );
	twd_sk_eq( TWD_SK_Welcome::admin_url(), TWD_SK_Welcome::login_redirect( $dash, $dash, $editor ) );
	twd_sk_eq( TWD_SK_Welcome::admin_url(), TWD_SK_Welcome::login_redirect( $dash, '', $editor ) );
	twd_sk_eq( $dash, TWD_SK_Welcome::login_redirect( $dash, $dash, $admin ), 'administrators' );
	$own = 'https://example.test/some-page/';
	twd_sk_eq( $own, TWD_SK_Welcome::login_redirect( $own, $own, $editor ), 'an address they asked for' );
	twd_sk_eq( $dash, TWD_SK_Welcome::login_redirect( $dash, $dash, new WP_Error( 'x' ) ), 'a failed sign-in' );
	update_option( 'twd_sk_safe_mode', true );
	twd_sk_eq( $dash, TWD_SK_Welcome::login_redirect( $dash, $dash, $editor ), 'safe mode' );
} );

twd_sk_test( 'welcome: administrators save the switches and two plain lines; others and a bad nonce are refused; words are cleaned and cut', function () {
	twd_stub_reset();
	$GLOBALS['twd_stub']['caps'] = true;
	$GLOBALS['twd_stub']['referer_ok'] = true;
	$_POST = array( 'login' => '1', 'go' => '1', 'note' => '<b>Hello</b> there ' . "\xE2\x80\x94" . ' friend', 'contact' => str_repeat( 'a', 300 ) );
	try {
		TWD_SK_Welcome::save();
	} catch ( TWD_SK_Stub_Redirect $r ) {
		twd_sk_has( 'twd_sk_saved=1', $r->getMessage() );
	}
	twd_sk_eq( true, TWD_SK_Login::on() );
	twd_sk_eq( 'yes', get_option( TWD_SK_Welcome::OPT_GO ) );
	twd_sk_eq( 'Hello there, friend', TWD_SK_Welcome::note() );
	twd_sk_eq( 200, strlen( TWD_SK_Welcome::contact() ) );
	// An editor who is not an administrator, and a bad nonce.
	$GLOBALS['twd_stub']['caps'] = array( 'edit_pages' );
	$_POST = array( 'note' => 'changed' );
	try {
		TWD_SK_Welcome::save();
		twd_sk_fail( 'should refuse' );
	} catch ( TWD_SK_Stub_Die $e ) {
		twd_sk_true( true );
	}
	twd_sk_eq( 'Hello there, friend', TWD_SK_Welcome::note() );
	$GLOBALS['twd_stub']['caps'] = true;
	$GLOBALS['twd_stub']['referer_ok'] = false;
	try {
		TWD_SK_Welcome::save();
		twd_sk_fail( 'should refuse' );
	} catch ( TWD_SK_Stub_Die $e ) {
		twd_sk_true( true );
	}
	$GLOBALS['twd_stub']['referer_ok'] = true;
	$_POST = array();
	// The settings form is shown to administrators only, with a nonce.
	ob_start();
	TWD_SK_Welcome::render();
	$out = ob_get_clean();
	twd_sk_has( 'name="action" value="twd_sk_welcome_save"', $out );
	twd_sk_has( 'NONCE_twd_sk_welcome_save', $out );
	twd_sk_has( 'Hello there, friend', $out );
	$GLOBALS['twd_stub']['caps'] = array( 'edit_pages' );
	ob_start();
	TWD_SK_Welcome::render();
	twd_sk_hasnt( 'twd_sk_welcome_save', ob_get_clean(), 'not for an editor' );
} );

twd_sk_test( 'welcome: the logo and the site name head the page, and the style tokens and fonts are printed with the stylesheet for this screen only', function () {
	twd_stub_reset();
	$GLOBALS['twd_stub']['caps'] = true;
	$GLOBALS['twd_stub']['attachments'][71] = array( 'is_image' => true, 'url' => 'https://example.test/wp-content/uploads/logo.png' );
	TWD_SK_Profile::save( array( 'site_name' => 'A Test Practice', 'logo_id' => 71 ) );
	ob_start();
	TWD_SK_Welcome::render();
	$out = ob_get_clean();
	twd_sk_has( 'class="twd-sk-welcome__logo" src="https://example.test/wp-content/uploads/logo.png" alt="A Test Practice"', $out );
	twd_sk_has( '<h1>Welcome</h1>', $out, 'no signed-in name, just Welcome' );
	twd_sk_has( 'Pick a button to start, or open a short guide.', $out );
	$GLOBALS['twd_stub']['user'] = array( 'ID' => 5, 'display_name' => 'Sam Example' );
	ob_start();
	TWD_SK_Welcome::render();
	twd_sk_has( '<h1>Welcome Sam</h1>', ob_get_clean(), 'the first name of the person signed in' );
	unset( $GLOBALS['twd_stub']['user'] );
	TWD_SK_Profile::save( array( 'site_name' => 'A Test Practice', 'logo_id' => 0 ) );
	ob_start();
	TWD_SK_Welcome::render();
	twd_sk_hasnt( 'twd-sk-welcome__logo', ob_get_clean(), 'no logo, no picture' );
	TWD_SK_Welcome::admin_assets( 'index.php' );
	twd_sk_true( empty( $GLOBALS['twd_stub']['styles'] ), 'other screens get nothing' );
	TWD_SK_Welcome::admin_assets( 'dashboard_page_twd-sk-welcome' );
	twd_sk_has( '--twd-site-color-primary', $GLOBALS['twd_stub']['styles']['twd-site-kit-welcome']['inline'] );
	twd_sk_has( '@font-face', $GLOBALS['twd_stub']['styles']['twd-site-kit-welcome']['inline'] );
	$css = file_get_contents( ABSPATH . 'assets/twd-site-kit-welcome.css' );
	twd_sk_has( 'var(--twd-site-color-primary', $css );
	twd_sk_has( 'var(--twd-site-font-heading', $css );
} );
