<?php
// The editing tools switch (0.14.1): one switch hides the floating buttons, the Edit header and footer pills and the
// version label, and brings them back. The choice is kept per person on the server, never in the browser.

twd_sk_test( 'tools switch: kept per person as user meta, on by default, and switching on leaves nothing behind', function () {
	twd_stub_reset();
	twd_sk_true( ! TWD_SK_Editor::tools_off(), 'the tools are on by default' );
	twd_sk_true( TWD_SK_Editor::set_tools_off( true ) );
	twd_sk_true( TWD_SK_Editor::tools_off() );
	twd_sk_eq( '1', $GLOBALS['twd_stub']['user_meta'][7]['twd_sk_tools_off'] );
	twd_sk_true( ! TWD_SK_Editor::tools_off( 99 ), 'another person is unaffected' );
	TWD_SK_Editor::set_tools_off( false );
	twd_sk_true( ! TWD_SK_Editor::tools_off() );
	twd_sk_true( empty( $GLOBALS['twd_stub']['user_meta'][7] ), 'switching on removes the meta' );
	$GLOBALS['twd_stub']['user'] = 0;
	twd_sk_true( ! TWD_SK_Editor::set_tools_off( true ), 'a visitor has nothing to save' );
	twd_sk_true( ! TWD_SK_Editor::tools_off() );
} );

twd_sk_test( 'tools switch: the script gets the saved state, and the button wrapper is marked off before any script runs', function () {
	twd_sk_ed_setup();
	twd_sk_true( twd_sk_ed_loaded() );
	twd_sk_true( false === $GLOBALS['twd_stub']['scripts']['twd-site-kit-editor']['data']['toolsOff'], 'on: toolsOff is false' );
	twd_sk_hasnt( 'data-twd-sk-tools', twd_sk_ed_markup() );
	TWD_SK_Editor::set_tools_off( true );
	twd_sk_ed_setup();
	twd_sk_true( twd_sk_ed_loaded() );
	twd_sk_true( true === $GLOBALS['twd_stub']['scripts']['twd-site-kit-editor']['data']['toolsOff'], 'off: toolsOff is true' );
	twd_sk_has( '<div id="twd-sk-ed" class="twd-sk-ed" data-twd-sk-tools="off">', twd_sk_ed_markup(), 'the version label hides at once, not after the script loads' );
	$snags = file_get_contents( ABSPATH . 'includes/class-twd-sk-snags.php' );
	twd_sk_has( "TWD_SK_Editor::tools_off() ? ' data-twd-sk-tools=\"off\"' : ''", $snags, 'the Tell us wrapper follows the same state' );
	TWD_SK_Editor::set_tools_off( false );
} );

twd_sk_test( 'tools switch rest: any editor may set it for themselves, a visitor or a wrong nonce may not, and it works in safe mode', function () {
	twd_sk_rest_setup();
	$route = null;
	foreach ( twd_sk_rest_routes() as $r ) {
		if ( '/editor/tools' === $r['route'] ) {
			$route = $r;
		}
	}
	twd_sk_true( null !== $route, 'the route exists' );
	twd_sk_eq( 'POST', $route['args']['methods'] );
	$cb = $route['args']['permission_callback'];
	twd_sk_eq( true, call_user_func( $cb, twd_sk_rest_req() ) );
	twd_sk_is_error( 'twd_sk_bad_nonce', call_user_func( $cb, twd_sk_rest_req( array(), 'wrong-nonce' ) ) );
	twd_sk_is_error( 'twd_sk_bad_nonce', call_user_func( $cb, twd_sk_rest_req( array(), null ) ) );
	$GLOBALS['twd_stub']['caps'] = array( 'read' );
	twd_sk_is_error( 'twd_sk_forbidden', call_user_func( $cb, twd_sk_rest_req() ), 'a subscriber may not' );
	twd_sk_rest_setup();
	$GLOBALS['twd_stub']['user'] = 0;
	twd_sk_is_error( 'twd_sk_not_signed_in', call_user_func( $cb, twd_sk_rest_req() ) );
	$GLOBALS['twd_stub']['user'] = 7;

	// The handler saves for the caller only, and a string that is not a plain yes means no.
	$out = TWD_SK_REST::post_editor_tools( twd_sk_rest_req( array( 'off' => true ) ) );
	twd_sk_eq( array( 'off' => true ), $out );
	twd_sk_true( TWD_SK_Editor::tools_off( 7 ) );
	twd_sk_true( ! TWD_SK_Editor::tools_off( 8 ), 'only this person' );
	$out = TWD_SK_REST::post_editor_tools( twd_sk_rest_req( array( 'off' => 'maybe' ) ) );
	twd_sk_eq( array( 'off' => false ), $out );
	twd_sk_true( ! TWD_SK_Editor::tools_off( 7 ) );

	// Safe mode keeps the route (it is a personal preference, not a site setting).
	TWD_SK_Safe::set( true );
	$found = false;
	foreach ( twd_sk_rest_routes() as $r ) {
		$found = $found || '/editor/tools' === $r['route'];
	}
	TWD_SK_Safe::set( false );
	twd_sk_true( $found, 'registered in safe mode too' );
} );

twd_sk_test( 'tools switch css (0.15.0, the Your Edits menu): the switch hides the pills (the only launchers still floating), never the pop-up or the editing card; the floating menu button shows without an admin bar or on a phone', function () {
	$raw = file_get_contents( ABSPATH . 'assets/twd-site-kit-editor.css' );
	twd_sk_true( 1 === preg_match( '/\.twd-sk-ed\[data-twd-sk-tools="off"\] \.twd-sk-ed__pill\.twd-sk-ed__pill \{\s*display: none;/', $raw ), 'the pills are hidden when the tools are off' );
	foreach ( array( 'twd-sk-ed__dialog', 'twd-sk-ed__inlinebar', 'twd-sk-ed__card', 'twd-sk-ed__menu' ) as $c ) {
		twd_sk_true( 1 !== preg_match( '/data-twd-sk-tools="off"\][^{]*' . $c . '[.\s{,]/', $raw ), $c . ' is never hidden by the switch' );
	}
	twd_sk_true( 1 === preg_match( '/\.twd-sk-ed__menu\.twd-sk-ed__menu \{[^}]*min-height: 48px/', $raw ), 'a 48px tap target' );
	twd_sk_true( 1 === preg_match( '/\.twd-sk-ed\[data-twd-sk-ab\] \.twd-sk-ed__menu\.twd-sk-ed__menu,\s*\.twd-sk-ed\[data-twd-sk-ab\] \.twd-sk-ed__menupop\.twd-sk-ed__menupop \{\s*display: none;/', $raw ), 'with the admin bar menu the floating button stays away' );
	twd_sk_true( 1 === preg_match( '/@media \(max-width: 782px\) \{\s*\.twd-sk-ed\[data-twd-sk-ab\] \.twd-sk-ed__menu\.twd-sk-ed__menu \{\s*display: inline-block;/', $raw ), 'but returns on a phone, where WordPress hides most admin bar items' );
} );

twd_sk_test( 'tools switch js: built with DOM calls into the admin bar after the WordPress logo, saved through the server, announced as a switch, and the Visual editor admin item follows it', function () {
	$js     = file_get_contents( ABSPATH . 'assets/twd-site-kit-editor.js' );
	$inline = file_get_contents( ABSPATH . 'assets/twd-site-kit-inline.js' );
	twd_sk_has( "wp-admin-bar-twd-sk-edits", $js );
	twd_sk_has( "list.insertBefore(menu.bar, list.children.length > 1 ? list.children[1] : null)", $js, 'after the WordPress logo, top left' );
	twd_sk_has( "api('POST', '/editor/tools', { off: tools.off })", $js, 'kept on the server' );
	twd_sk_has( "node.setAttribute('role', 'switch')", $js );
	twd_sk_has( "'aria-checked'", $js );
	twd_sk_has( 'toolsOff: function', $js );
	twd_sk_has( 'onToolsChange: function', $js );
	twd_sk_hasnt( 'localStorage', $js, 'no browser storage' );
	twd_sk_has( 'ED.onToolsChange(adminBar)', $inline );
	twd_sk_has( "ui.abMain.li.style.display = S.on ? '' : 'none';", $inline, 'hidden unless an editing session is open (the menu offers Visual editor)' );
	foreach ( array( $js, $inline ) as $src ) {
		twd_sk_hasnt( 'alert(', $src );
		twd_sk_hasnt( 'confirm(', $src );
		twd_sk_hasnt( 'innerHTML', $src );
	}
} );
