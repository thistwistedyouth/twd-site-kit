<?php
// The Simple editor (session 5, 29 Oct 2026): a role check on the one editor, not a second editor. Everyone who is not
// an administrator gets it, and an administrator who chose "View as: Client". It only hides tools: saving still runs with
// the person's real capabilities through the same inline route, sanitiser, versions and conflict checks.

function twd_sk_simple_admin_caps() {
	return array( 'edit_pages', 'edit_post:12', 'edit_post:20', 'manage_options', 'upload_files' );
}

twd_sk_test( 'simple editor: who gets it is the role, and an administrator\'s View as choice kept as user meta (Administrator view leaves nothing behind)', function () {
	twd_stub_reset();
	$GLOBALS['twd_stub']['caps'] = array( 'edit_pages', 'edit_post:12' );
	twd_sk_true( TWD_SK_Editor::simple_on(), 'an editor who is not an administrator' );
	twd_sk_eq( 'admin', TWD_SK_Editor::view_as(), 'the meta is admin by default' );
	$GLOBALS['twd_stub']['caps'] = twd_sk_simple_admin_caps();
	twd_sk_true( ! TWD_SK_Editor::simple_on(), 'an administrator gets the full editor' );
	twd_sk_true( TWD_SK_Editor::set_view_as( 'client' ) );
	twd_sk_eq( 'client', TWD_SK_Editor::view_as() );
	twd_sk_true( TWD_SK_Editor::simple_on(), 'viewing as the client' );
	twd_sk_eq( 'client', $GLOBALS['twd_stub']['user_meta'][7]['twd_sk_view_as'] );
	twd_sk_eq( 'admin', TWD_SK_Editor::view_as( 99 ), 'another person is unaffected' );
	TWD_SK_Editor::set_view_as( 'anything else' );
	twd_sk_eq( 'admin', TWD_SK_Editor::view_as() );
	twd_sk_true( empty( $GLOBALS['twd_stub']['user_meta'][7] ), 'Administrator view removes the meta' );
	twd_sk_true( ! TWD_SK_Editor::simple_on() );
	// The tools switch never hides the pen: a client must always find it.
	TWD_SK_Editor::set_tools_off( true );
	twd_sk_true( TWD_SK_Editor::tools_off() && TWD_SK_Editor::launchers_hidden(), 'an administrator: the switch works' );
	TWD_SK_Editor::set_view_as( 'client' );
	twd_sk_true( TWD_SK_Editor::tools_off() && ! TWD_SK_Editor::launchers_hidden(), 'in Client view the launchers stay' );
	TWD_SK_Editor::set_view_as( 'admin' );
	TWD_SK_Editor::set_tools_off( false );
	$GLOBALS['twd_stub']['user'] = 0;
	twd_sk_true( ! TWD_SK_Editor::set_view_as( 'client' ), 'a visitor has nothing to save' );
} );

twd_sk_test( 'simple editor rest: View as is saved by administrators only; an editor is refused (their role decides); anything but client means admin', function () {
	twd_sk_rest_setup( twd_sk_simple_admin_caps() );
	$route = null;
	foreach ( twd_sk_rest_routes() as $r ) {
		if ( '/editor/view-as' === $r['route'] ) {
			$route = $r;
		}
	}
	twd_sk_true( null !== $route, 'the route exists' );
	twd_sk_eq( 'POST', $route['args']['methods'] );
	$cb = $route['args']['permission_callback'];
	twd_sk_eq( true, call_user_func( $cb, twd_sk_rest_req() ) );
	twd_sk_is_error( 'twd_sk_bad_nonce', call_user_func( $cb, twd_sk_rest_req( array(), 'wrong-nonce' ) ) );
	$out = TWD_SK_REST::post_editor_view_as( twd_sk_rest_req( array( 'as' => 'client' ) ) );
	twd_sk_eq( array( 'as' => 'client' ), $out );
	twd_sk_eq( 'client', TWD_SK_Editor::view_as( 7 ) );
	twd_sk_eq( 'admin', TWD_SK_Editor::view_as( 8 ), 'only this person' );
	$out = TWD_SK_REST::post_editor_view_as( twd_sk_rest_req( array( 'as' => 'maybe' ) ) );
	twd_sk_eq( array( 'as' => 'admin' ), $out );
	twd_sk_eq( 'admin', TWD_SK_Editor::view_as( 7 ) );
	// An editor who is not an administrator: the permission check passes (edit_pages) but the handler refuses.
	$GLOBALS['twd_stub']['caps'] = array( 'edit_pages', 'edit_post:12' );
	$out = TWD_SK_REST::post_editor_view_as( twd_sk_rest_req( array( 'as' => 'admin' ) ) );
	twd_sk_is_error( 'twd_sk_forbidden', $out );
	twd_sk_eq( 403, twd_sk_status( $out ) );
	twd_sk_true( empty( $GLOBALS['twd_stub']['user_meta'][7]['twd_sk_view_as'] ), 'nothing saved for them' );
	// Kept in safe mode, like the other personal preferences.
	TWD_SK_Safe::set( true );
	$found = false;
	foreach ( twd_sk_rest_routes() as $r ) {
		$found = $found || '/editor/view-as' === $r['route'];
	}
	TWD_SK_Safe::set( false );
	twd_sk_true( $found, 'registered in safe mode too' );
} );

twd_sk_test( 'simple editor: an editor gets the inline script and the media picker but no AI, Look, sections, Site tab, Search tab or embeds; the script is told, and never told about the switch', function () {
	twd_sk_ed_setup( array( 'edit_pages', 'edit_post:12', 'edit_post:20', 'upload_files' ) );
	twd_sk_ai_provider( 'x', true );
	$GLOBALS['twd_stub']['media_enqueued'] = false;
	twd_sk_true( twd_sk_ed_loaded() );
	$data = $GLOBALS['twd_stub']['scripts']['twd-site-kit-editor']['data'];
	twd_sk_eq( true, $data['simple'] );
	twd_sk_eq( '', $data['viewAs'], 'a real client has no view' );
	twd_sk_eq( false, $data['canSwitchView'], 'and no switch' );
	twd_sk_eq( false, $data['canManage'] );
	twd_sk_eq( false, $data['ai'] );
	twd_sk_eq( false, $data['aiAvailable'] );
	twd_sk_eq( false, $data['classic'] );
	twd_sk_eq( false, $data['toolsOff'] );
	twd_sk_eq( array(), $data['look'], 'no Look panel' );
	twd_sk_eq( array(), $data['components'], 'no Add section' );
	twd_sk_eq( array(), $data['embeds'] );
	twd_sk_eq( '', $data['setupUrl'] );
	twd_sk_eq( true, $data['inline'], 'words and pictures in place' );
	twd_sk_true( ! empty( $GLOBALS['twd_stub']['scripts']['twd-site-kit-inline']['enqueued'] ) );
	twd_sk_true( ! empty( $GLOBALS['twd_stub']['media_enqueued'] ), 'the media library picker loads for pictures' );
	twd_sk_true( ! isset( $GLOBALS['twd_stub']['scripts']['twd-site-kit-editor-seo'] ), 'no Search tab' );
	twd_sk_true( ! isset( $GLOBALS['twd_stub']['scripts']['twd-site-kit-editor-site'] ), 'no Site tab' );
	twd_sk_ai_provider( 'x', false );
} );

twd_sk_test( 'simple editor: an administrator viewing as the client gets exactly the same, plus the way back; in Administrator view everything, plus the switch', function () {
	twd_sk_ed_setup( twd_sk_simple_admin_caps() );
	twd_sk_ai_provider( 'x', true );
	TWD_SK_Editor::set_view_as( 'client' );
	twd_sk_true( twd_sk_ed_loaded() );
	$data = $GLOBALS['twd_stub']['scripts']['twd-site-kit-editor']['data'];
	twd_sk_eq( true, $data['simple'] );
	twd_sk_eq( 'client', $data['viewAs'] );
	twd_sk_eq( true, $data['canSwitchView'], 'the way back' );
	foreach ( array( 'canManage', 'ai', 'aiAvailable', 'classic', 'toolsOff' ) as $k ) {
		twd_sk_eq( false, $data[ $k ], $k . ' is off in Client view' );
	}
	twd_sk_eq( array(), $data['look'] );
	twd_sk_eq( array(), $data['components'] );
	twd_sk_eq( array(), $data['embeds'] );
	twd_sk_eq( '', $data['setupUrl'] );
	twd_sk_true( ! isset( $GLOBALS['twd_stub']['scripts']['twd-site-kit-editor-site'] ), 'no Site tab script in Client view' );
	twd_sk_true( ! isset( $GLOBALS['twd_stub']['scripts']['twd-site-kit-editor-seo'] ), 'no Search tab script in Client view' );
	// Tell us: the notes panel is an administrator's tool, so Client view hides it too (the list route says so).
	twd_sk_eq( 2, substr_count( file_get_contents( ABSPATH . 'includes/class-twd-sk-snags.php' ), "current_user_can( 'manage_options' ) && ! TWD_SK_Editor::simple_on()" ) );
	twd_sk_sn_on( twd_sk_simple_admin_caps() );
	TWD_SK_Editor::set_view_as( 'client' );
	TWD_SK_Snags::create( 1, 'An idea only the owner may see.', 12, 'idea' );
	$list = twd_sk_sn_call( '/snags', 'GET' );
	twd_sk_true( false === $list['is_admin'], 'in Client view the notes panel is the client\'s' );
	twd_sk_eq( 0, count( $list['items'] ), 'no ideas listed' );
	TWD_SK_Editor::set_view_as( 'admin' );
	$list = twd_sk_sn_call( '/snags', 'GET' );
	twd_sk_true( true === $list['is_admin'] );
	twd_sk_ed_setup( twd_sk_simple_admin_caps() );
	TWD_SK_Editor::set_view_as( 'client' );

	twd_sk_ed_setup( twd_sk_simple_admin_caps() );
	TWD_SK_Editor::set_view_as( 'admin' );
	twd_sk_true( twd_sk_ed_loaded() );
	$data = $GLOBALS['twd_stub']['scripts']['twd-site-kit-editor']['data'];
	twd_sk_eq( false, $data['simple'] );
	twd_sk_eq( 'admin', $data['viewAs'] );
	twd_sk_eq( true, $data['canSwitchView'] );
	twd_sk_eq( true, $data['canManage'] );
	twd_sk_eq( true, $data['ai'] );
	twd_sk_true( ! empty( $data['look'] ), 'the Look panel is back' );
	twd_sk_true( ! empty( $GLOBALS['twd_stub']['scripts']['twd-site-kit-editor-site']['enqueued'] ), 'the Site tab is back' );
	twd_sk_ai_provider( 'x', false );
} );

twd_sk_test( 'simple editor js: the pen, one plain admin bar item, no pencils and no window; the View as switch in the menu and the window header, saved through the server; the step list for Undo and Redo, Save without a reload, History with Go back to this, and the Client view tag', function () {
	$js     = file_get_contents( ABSPATH . 'assets/twd-site-kit-editor.js' );
	$inline = file_get_contents( ABSPATH . 'assets/twd-site-kit-inline.js' );
	// The main script: the role check, the launchers and the switch.
	twd_sk_has( 'var simple = !!cfg.simple;', $js );
	twd_sk_has( "openBtn.className = 'twd-sk-ed__mini twd-sk-ed__pen';", $js, 'the pen is the existing button, in the glowing pencil look' );
	twd_sk_has( "openBtn.setAttribute('aria-label', 'Edit this page');", $js );
	twd_sk_has( "var a = el('a', { className: 'ab-item', href: '#', text: 'Edit this page' });", $js, 'one plain item in the admin bar for a client' );
	twd_sk_has( "addLauncher({ id: 'viewas', order: 85, label: function () { return 'Viewing as Client - switch back'; }", $js, 'the way back, administrators only' );
	twd_sk_has( "label: function () { return 'View as Client (see what the client sees)'; }", $js, 'the switch in the Your Edits menu' );
	twd_sk_has( "text: 'View as Client', title: 'See this page with the Simple editor, exactly as the client gets it'", $js, 'and in the window header' );
	twd_sk_has( "api('POST', '/editor/view-as', { as: as })", $js, 'remembered on the server' );
	twd_sk_has( "if (!cfg.canSwitchView) {\n\t\t\treturn;\n\t\t}\n\t\tif (state.built", $js, 'never for anyone else' );
	twd_sk_has( "function buildPencils() {\n\t\tif (simple) {\n\t\t\treturn;\n\t\t}", $js, 'no pencils' );
	twd_sk_has( "function open() {\n\t\tif (simple) {", $js, 'the pen starts the page tools, never the window' );
	foreach ( array( 'hasLauncher: function (id)', 'runLauncher: function (id)', 'switchView: switchView', 'setVersion: function (v)', 'formatDate: formatDate' ) as $needle ) {
		twd_sk_has( $needle, $js );
	}
	// The Visual editor: the panel, the step list, the save, History.
	twd_sk_has( 'var SIMPLE = !!cfg.simple;', $inline );
	twd_sk_has( 'var oneWindow = !cfg.classic && !SIMPLE;', $inline );
	twd_sk_has( "(SIMPLE ? ' twd-sk-ed__simple' : '')", $inline );
	twd_sk_has( "'Click on any words or a picture to change them.'", $inline );
	twd_sk_has( "ui.done = ED.button('Done', 'secondary', cancel);", $inline );
	twd_sk_has( "el('div', { className: 'twd-sk-ed__steps' }, [ui.save, ui.undoStep, ui.redoStep, ui.history])", $inline, 'Save, Undo, Redo, History' );
	twd_sk_has( "[ui.bold, ui.link]", $inline, 'Bold and Link only' );
	twd_sk_has( "text: 'Need something else changed? '", $inline );
	twd_sk_has( "ED.runLauncher('tellus')", $inline );
	twd_sk_has( "el('span', { text: 'Client view' })", $inline );
	twd_sk_has( "text: 'Switch back to Administrator view'", $inline );
	twd_sk_has( "if (!list || SIMPLE) {", $inline, 'no Save changes or Cancel in the admin bar for a client' );
	foreach ( array( 'function noteTyping(t)', 'function commitTyping()', 'function recordText(t, label, fn)', 'function recordPic(target, label, fn)', 'function pushStep(step)', 'function undoStep()', 'function redoStep()', 'function restoreTo(version, allow, done)', 'function openHistory()', 'function goBack(id, when)' ) as $needle ) {
		twd_sk_has( $needle, $inline );
	}
	twd_sk_has( "pushStep({ kind: 'server', from: from, to: data.version, label: 'the save' });", $inline, 'a save is a step' );
	twd_sk_has( "holdStatus('Saved at ' + timeNow() + '.');", $inline );
	twd_sk_has( "status(SIMPLE ? 'Nothing to save.' : 'Nothing has changed yet.');", $inline );
	twd_sk_has( "ED.api('POST', '/pages/' + cfg.pageId + '/restore', { version: version, base_version: cfg.currentVersion, allow_placeholders: !!allow })", $inline, 'the normal restore route, with the conflict check' );
	twd_sk_has( "err.status === 409 && err.code === 'twd_sk_placeholders'", $inline, 'the blank starter asks once' );
	twd_sk_has( "ED.button('Go back to this', 'secondary'", $inline );
	twd_sk_has( "'Blank starter: ' : 'Unfinished: '", $inline );
	twd_sk_has( "if (S.on && SIMPLE) {", $inline, 'a redraw restarts the session on the fresh words' );
	twd_sk_has( "'You have ' + n + ' change' + (n === 1 ? '' : 's') + ' not saved. Leave anyway? '", $inline, 'leaving asks once' );
	foreach ( array( $js, $inline ) as $src ) {
		twd_sk_hasnt( 'alert(', $src );
		twd_sk_hasnt( 'confirm(', $src );
		twd_sk_hasnt( 'innerHTML', $src );
		twd_sk_hasnt( 'localStorage', $src );
	}
	// The styles: the pen shows (the old launcher rule hides every .twd-sk-ed__open), the panel sits bottom right, a
	// phone gets a slim bar, and the panel holds back its tools until the person clicks on words or changes something.
	$css = file_get_contents( ABSPATH . 'assets/twd-site-kit-editor.css' );
	twd_sk_true( 1 === preg_match( '/\.twd-sk-ed \.twd-sk-ed__pen\.twd-sk-ed__pen \{\s*display: inline-flex;/', $css ), 'the pen shows' );
	twd_sk_true( strpos( $css, '.twd-sk-ed .twd-sk-ed__pen.twd-sk-ed__pen {' ) > strpos( $css, '.twd-sk-ed .twd-sk-ed__open.twd-sk-ed__open,' ), 'after the rule that hides the old launchers' );
	twd_sk_true( 1 === preg_match( '/\.twd-sk-ed \.twd-sk-ed__simple\.twd-sk-ed__simple \{\s*left: auto;\s*right: 24px/', $css ), 'bottom right' );
	twd_sk_has( '.twd-sk-ed .twd-sk-ed__simple.twd-sk-ed__simple[data-twd-sk-engaged="off"] .twd-sk-ed__steps.twd-sk-ed__steps', $css );
	twd_sk_has( '.twd-sk-ed .twd-sk-ed__clienttag.twd-sk-ed__clienttag {', $css );
	twd_sk_true( 1 === preg_match( '/@media \(max-width: 700px\) \{\s*\.twd-sk-ed \.twd-sk-ed__simple\.twd-sk-ed__simple \{\s*max-height: 70%/', $css ), 'a slim sheet on a phone' );
	twd_sk_has( ".twd-sk-page:not(.twd-sk-reading__box)", $js, 'the redraw ignores the Reading box (a script made it, so the fresh page has none)' );
	twd_sk_has( "show: function () { return !oneWindow && !SIMPLE && !S.on", $inline, 'no Visual editor item in the menu' );
} );
