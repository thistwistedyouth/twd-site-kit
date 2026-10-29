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

twd_sk_test( 'tools switch: the script gets the saved state, and the button wrapper is marked off before any script runs (never in the Simple editor)', function () {
	$admin = array( 'edit_pages', 'edit_post:12', 'edit_post:20', 'manage_options' );
	twd_sk_ed_setup( $admin );
	twd_sk_true( twd_sk_ed_loaded() );
	twd_sk_true( false === $GLOBALS['twd_stub']['scripts']['twd-site-kit-editor']['data']['toolsOff'], 'on: toolsOff is false' );
	twd_sk_hasnt( 'data-twd-sk-tools', twd_sk_ed_markup() );
	TWD_SK_Editor::set_tools_off( true );
	twd_sk_ed_setup( $admin );
	twd_sk_true( twd_sk_ed_loaded() );
	twd_sk_true( true === $GLOBALS['twd_stub']['scripts']['twd-site-kit-editor']['data']['toolsOff'], 'off: toolsOff is true' );
	twd_sk_has( '<div id="twd-sk-ed" class="twd-sk-ed" data-twd-sk-tools="off">', twd_sk_ed_markup(), 'the version label hides at once, not after the script loads' );
	// Session 5: the Simple editor ignores the switch (a client must always find the pen), for an editor and in Client view.
	twd_sk_ed_setup();
	twd_sk_true( twd_sk_ed_loaded() );
	twd_sk_true( false === $GLOBALS['twd_stub']['scripts']['twd-site-kit-editor']['data']['toolsOff'], 'an editor: the pen always shows' );
	twd_sk_hasnt( 'data-twd-sk-tools', twd_sk_ed_markup() );
	twd_sk_true( TWD_SK_Editor::tools_off(), 'the meta itself is kept' );
	twd_sk_ed_setup( $admin );
	$snags = file_get_contents( ABSPATH . 'includes/class-twd-sk-snags.php' );
	twd_sk_has( "TWD_SK_Editor::launchers_hidden() ? ' data-twd-sk-tools=\"off\"' : ''", $snags, 'the Tell us wrapper follows the same state' );
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

twd_sk_test( 'window (0.15.0): where the Edit with AI window sits is kept per person as checked user meta, the default leaves nothing behind, and the route saves it', function () {
	twd_stub_reset();
	$d = TWD_SK_Editor::window_defaults();
	twd_sk_eq( 'centre', $d['mode'] );
	twd_sk_eq( $d, TWD_SK_Editor::window_state(), 'the default is the centred window as before' );
	// Checked: unknown keys dropped, bad words fall back, numbers bounded and whole.
	$c = TWD_SK_Editor::clean_window( array( 'mode' => 'left', 'x' => '12.7', 'y' => -40, 'w' => 99999, 'h' => 600, 'mx' => 30, 'my' => 'abc', 'esc' => 'minimise', 'evil' => '<script>' ) );
	twd_sk_eq( array( 'mode' => 'left', 'x' => 12, 'y' => 0, 'w' => 20000, 'h' => 600, 'mx' => 30, 'my' => -1, 'esc' => 'minimise', 'full' => false ), $c );
	twd_sk_eq( 'centre', TWD_SK_Editor::clean_window( array( 'mode' => 'popout' ) )['mode'] );
	twd_sk_eq( 'close', TWD_SK_Editor::clean_window( array( 'esc' => 'reload' ) )['esc'] );
	twd_sk_eq( $d, TWD_SK_Editor::clean_window( 'nonsense' ) );
	// Saved for this person only; the default removes the meta.
	$saved = TWD_SK_Editor::set_window_state( array( 'mode' => 'right', 'w' => 520 ) );
	twd_sk_eq( 'right', $saved['mode'] );
	twd_sk_eq( 'right', TWD_SK_Editor::window_state()['mode'] );
	twd_sk_eq( 520, TWD_SK_Editor::window_state()['w'] );
	twd_sk_eq( 'centre', TWD_SK_Editor::window_state( 99 )['mode'], 'another person is unaffected' );
	TWD_SK_Editor::set_window_state( array() );
	twd_sk_true( empty( $GLOBALS['twd_stub']['user_meta'][7]['twd_sk_window'] ), 'the default removes the meta' );
	$GLOBALS['twd_stub']['user'] = 0;
	twd_sk_true( false === TWD_SK_Editor::set_window_state( array( 'mode' => 'left' ) ), 'a visitor has nothing to save' );
	// The route, and the state handed to the script.
	twd_stub_reset();
	$out = TWD_SK_REST::post_editor_window( twd_sk_rest_req( array( 'state' => array( 'mode' => 'free', 'x' => 100, 'y' => 80, 'w' => 700, 'h' => 500, 'esc' => 'minimise' ) ) ) );
	twd_sk_eq( 'free', $out['state']['mode'] );
	twd_sk_eq( 'minimise', $out['state']['esc'] );
	twd_sk_is_error( 'twd_sk_bad_input', TWD_SK_REST::post_editor_window( twd_sk_rest_req( array( 'state' => 'left' ) ) ) );
	twd_sk_ed_setup();
	twd_sk_true( twd_sk_ed_loaded() );
	twd_sk_eq( 'free', $GLOBALS['twd_stub']['scripts']['twd-site-kit-editor']['data']['window']['mode'] );
	$rest = file_get_contents( ABSPATH . 'includes/class-twd-sk-rest.php' );
	twd_sk_has( "'/editor/window'", $rest );
	// The script: drag, resize, dock, minimise to a pencil that restores the window as it was; nothing in browser storage.
	$js = file_get_contents( ABSPATH . 'assets/twd-site-kit-editor.js' );
	foreach ( array( "'/editor/window'", 'function minimise(', 'function restoreWindow(', 'function dockWindow(', 'twd-sk-ed__mini', 'Open Edit with AI, minimised', 'pointerdown', 'cfg.window' ) as $needle ) {
		twd_sk_has( $needle, $js );
	}
	twd_sk_hasnt( 'localStorage', $js );
	$css = file_get_contents( ABSPATH . 'assets/twd-site-kit-editor.css' );
	foreach ( array( 'twd-sk-ed__mini', 'twd-sk-ed__overlay--left', 'twd-sk-ed__overlay--right', 'twd-sk-ed__overlay--free', 'twd-sk-ed__resize', '@keyframes twd-sk-ed-glow', 'prefers-reduced-motion' ) as $needle ) {
		twd_sk_has( $needle, $css );
	}
} );

twd_sk_test( 'one editing window (0.15.0, phase 1): the classic editor is a per-person switch kept as user meta (off by default), saved by its route, handed to the script; the shell has three tabs, opens undocked and content-sized, hosts the Visual editor, and the classic pop-up stays behind the switch', function () {
	twd_stub_reset();
	twd_sk_true( ! TWD_SK_Editor::classic_on(), 'the one window by default' );
	twd_sk_true( TWD_SK_Editor::set_classic( true ) );
	twd_sk_true( TWD_SK_Editor::classic_on() );
	twd_sk_true( ! TWD_SK_Editor::classic_on( 99 ), 'another person is unaffected' );
	TWD_SK_Editor::set_classic( false );
	twd_sk_true( empty( $GLOBALS['twd_stub']['user_meta'][7]['twd_sk_classic'] ), 'the one window removes the meta' );
	twd_sk_eq( true, TWD_SK_REST::post_editor_classic( twd_sk_rest_req( array( 'on' => true ) ) )['on'] );
	twd_sk_true( TWD_SK_Editor::classic_on() );
	twd_sk_eq( false, TWD_SK_REST::post_editor_classic( twd_sk_rest_req( array( 'on' => '0' ) ) )['on'] );
	twd_sk_ed_setup();
	twd_sk_true( twd_sk_ed_loaded() );
	twd_sk_true( false === $GLOBALS['twd_stub']['scripts']['twd-site-kit-editor']['data']['classic'] );
	$js = file_get_contents( ABSPATH . 'assets/twd-site-kit-editor.js' );
	foreach ( array( "var oneWindow = !cfg.classic", "'On page edits'", "'AI assisted edits'", "'Settings'", 'twd-sk-ed__dialog--compact', "'/editor/classic'", 'Use the classic editor', 'inlineHost:', 'twd-sk-ed__outline', "Click what you want to change on the page" ) as $needle ) {
		twd_sk_has( $needle, $js );
	}
	// 29 Oct 2026 (the owner's two fixes): the one window always opens undocked at its compact size and place, whatever
	// was saved (a drag holds only while the page is open), and always on On page edits unless the caller asks for a tab.
	$setup = substr( $js, strpos( $js, 'function setUpWindow()' ) );
	$setup = substr( $setup, 0, strpos( $setup, 'function clamp(' ) );
	twd_sk_has( "win.mode = 'free';\n\t\t\twin.placed = false;\n\t\t\twin.sized = false;", $setup );
	$open = substr( $js, strpos( $js, 'function open() {' ) );
	$open = substr( $open, 0, strpos( $open, 'function unsavedList()' ) );
	twd_sk_has( "var wasHidden = ui.overlay.hasAttribute('hidden');", $open );
	twd_sk_has( "if (oneWindow && wasHidden && ui.tabs.page && state.tab !== 'page') {\n\t\t\tselectTab('page');", $open );
	twd_sk_true( strpos( $open, "show(ui.overlay, true);" ) < strpos( $open, "selectTab('page');" ), 'the tab is chosen after the window shows, so the Visual editor starts' );
	$inline = file_get_contents( ABSPATH . 'assets/twd-site-kit-inline.js' );
	foreach ( array( 'data-twd-sk-ctx', 'twd-sk-ed__inlinebar--inwin', 'ED.focusSection = ', 'ED.stopInline = ', 'function updateContext' ) as $needle ) {
		twd_sk_has( $needle, $inline );
	}
	// The saving, undo and cancel paths are the same functions as before (the window only hosts them).
	foreach ( array( 'function save() {', 'function cancel() {', 'function throwAway() {', "ED.api('POST', '/pages/' + cfg.pageId + '/inline', body)", "ED.api('POST', '/pages/' + cfg.pageId + '/undo'" ) as $needle ) {
		twd_sk_has( $needle, $inline );
	}
	$css = file_get_contents( ABSPATH . 'assets/twd-site-kit-editor.css' );
	foreach ( array( 'twd-sk-ed__dialog--compact', 'twd-sk-ed__inlinebar--inwin', 'twd-sk-ed__overlay--sheet', 'max-height: 70%', 'twd-sk-ed__subtabs', 'data-twd-sk-ctx="none"' ) as $needle ) {
		twd_sk_has( $needle, $css );
	}
} );

twd_sk_test( 'one editing window (0.15.0, phase 2): the AI tab is one box headed Tell us what you would like, pencils carry their number and pre-fill the section, a request that cannot be done offers Do this instead, and the What changed notes stay', function () {
	$js = file_get_contents( ABSPATH . 'assets/twd-site-kit-editor.js' );
	foreach ( array( "'Tell us what you would like'", 'function prefillSection(', "'In section ' + (index + 1) + ' (' + name + '): '", "'Do this instead'", 'twd-sk-ed__cannotcard', 'twd-sk-ed__pencilnum', "'Change section ' + (index + 1) + ': ' + name", 'None of that can be done on this page as asked', "el('strong', { text: 'What changed' })", "el('strong', { text: 'Nothing changed' })" ) as $needle ) {
		twd_sk_has( $needle, $js );
	}
	twd_sk_has( 'ED.openLookFor = ', file_get_contents( ABSPATH . 'assets/twd-site-kit-inline.js' ) );
	$css = file_get_contents( ABSPATH . 'assets/twd-site-kit-editor.css' );
	foreach ( array( 'twd-sk-ed__pencilnum', 'twd-sk-ed__cannotcard', 'twd-sk-ed__aisec' ) as $cls ) {
		twd_sk_has( $cls, $css );
	}
	// The planner still tells the browser what cannot be done and the nearest thing that can (the buttons are built from it).
	$plan = file_get_contents( ABSPATH . 'includes/class-twd-sk-plan.php' );
	twd_sk_has( "'cannot'", $plan );
	twd_sk_has( 'nearest', $plan );
} );

twd_sk_test( 'one editing window (18c and 18d, 29 Oct 2026): a pencil opens one section view with Look, Move up, Move down, Add section after and Delete section; Delete this text and Delete section go through the existing remove and Put back paths; a compact row with More', function () {
	$inline = file_get_contents( ABSPATH . 'assets/twd-site-kit-inline.js' );
	foreach ( array( "'Delete section'", "'Delete this text'", "'Add section after'", "toolButton('More'", 'function toggleMore()', "'data-twd-sk-more'", 'ED.sectionView = ', "'All sections'", 'Click any words in this section to edit them.', 'twd-sk-ed__secview', 'twd-sk-ed__inlinetools--more', 'function deleteText()', 'function deleteSection()', 'You can bring it back with Put back before saving, or with Undo after.' ) as $needle ) {
		twd_sk_has( $needle, $inline );
	}
	// Both deletes end in removeThis(), the Remove tool's own function: the removal is the same S.removes entry the
	// inline route saves as removes, Put back returns it, Cancel and Undo revert it.
	foreach ( array( 'function deleteText()', 'function deleteSection()' ) as $fn ) {
		$body = substr( $inline, strpos( $inline, $fn ) );
		$body = substr( $body, 0, strpos( $body, "\n\t}\n" ) );
		twd_sk_has( 'removeThis();', $body, $fn . ' reuses Remove' );
		twd_sk_hasnt( 'parentNode.removeChild', $body, $fn . ' never deletes a node itself' );
	}
	twd_sk_has( "removes: ", $inline );
	twd_sk_has( "'removes'", file_get_contents( ABSPATH . 'includes/class-twd-sk-rest.php' ) );
	// The compact row: B, I, Link for words; the rest behind More. The section row holds Look, the moves, Add after, Delete.
	twd_sk_has( "[ui.bold, ui.italic, ui.link])", $inline );
	twd_sk_has( "[ui.quote, ui.note, ui.heading, ui.deleteText]", $inline );
	twd_sk_has( "[ui.look, ui.moveUp, ui.moveDown, ui.addSec, ui.remove]", $inline );
	// Put back stands on its own, shown whenever something was deleted (after a section goes there is nothing in it to click).
	twd_sk_has( "twd-sk-ed__inlinetools--putback' }, [ui.putBack]", $inline );
	twd_sk_has( 'function syncPutBack()', $inline );
	// The classic bar is as it was.
	twd_sk_has( "[ui.bold, ui.italic, ui.link, ui.quote, ui.note, ui.heading, ui.look, ui.remove, ui.putBack]", $inline );
	$js = file_get_contents( ABSPATH . 'assets/twd-site-kit-editor.js' );
	twd_sk_has( 'ED.sectionView(true, index)', $js );
	twd_sk_has( 'ED.sectionView = function (on, index)', $inline );
	twd_sk_has( 'function dropCursor(node)', $inline );
	twd_sk_has( 'showOutline: function (on)', $js );
	$css = file_get_contents( ABSPATH . 'assets/twd-site-kit-editor.css' );
	foreach ( array( ':not([data-twd-sk-more="on"]) .twd-sk-ed__inlinetools--more', 'twd-sk-ed__inlinetools--morebtn', 'twd-sk-ed__allsections', 'twd-sk-ed__viewhint' ) as $needle ) {
		twd_sk_has( $needle, $css );
	}
	twd_sk_has( 'section.twd-sk-ed__secview', file_get_contents( ABSPATH . 'assets/twd-site-kit-inline.css' ) );
} );
