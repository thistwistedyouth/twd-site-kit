<?php
// Edit pills for the header and footer, and the unsaved changes prompt.

twd_sk_test( 'pills: the server never prints them, so a visitor can never see one', function () {
	twd_stub_reset();
	TWD_SK_Profile::save( array( 'site_name' => 'Calm Practice' ) );
	foreach ( array( TWD_SK_Chrome::render_header(), TWD_SK_Chrome::render_footer(), TWD_SK_Chrome::render_minimal_header(), TWD_SK_Chrome::render_minimal_footer() ) as $html ) {
		twd_sk_hasnt( 'twd-sk-ed__pill', $html );
		twd_sk_hasnt( 'Edit header', $html );
		twd_sk_hasnt( 'Edit footer', $html );
	}
} );

twd_sk_test( 'pills js: made only after the pop-up is opened, kept out of the header and footer, no inline handlers', function () {
	$js = file_get_contents( ABSPATH . 'assets/twd-site-kit-editor-site.js' );
	twd_sk_has( 'ED.onOpen.push(addPills)', $js );
	twd_sk_has( "document.getElementById('twd-sk-ed').appendChild(pill.node)", $js );
	twd_sk_has( "ED.openAt('site', pill.field)", $js );
	twd_sk_has( '.twd-sk-chrome .twd-sk-header', $js );
	twd_sk_has( '.twd-sk-chrome .twd-sk-footer', $js );
	twd_sk_hasnt( 'onclick', $js );
	foreach ( array( 'twd-sk-pf-menu', 'twd-sk-pf-footertext' ) as $id ) {
		twd_sk_has( "'" . $id . "'", $js, 'a pill points at a real field: ' . $id );
	}
} );

twd_sk_test( 'pills css: the pill is styled in the editor sheet, readable, and below the pop-up', function () {
	$css = file_get_contents( ABSPATH . 'assets/twd-site-kit-editor.css' );
	twd_sk_has( '.twd-sk-ed__pill', $css );
	twd_sk_true( 1 === preg_match( '/\.twd-sk-ed__pill\s*\{[^}]*z-index:\s*99980/', $css ), 'z-index under the overlay' );
	twd_sk_true( 1 === preg_match( '/\.twd-sk-ed__overlay\s*\{[^}]*z-index:\s*99999/', $css ), 'overlay above' );
} );

twd_sk_test( 'unsaved prompt js: every way to close goes through the guard, with inline buttons and no native dialogs', function () {
	$js = file_get_contents( ABSPATH . 'assets/twd-site-kit-editor.js' );
	twd_sk_has( "closeBtn.addEventListener('click', requestClose)", $js );
	twd_sk_has( 'requestClose();', $js );
	twd_sk_true( 1 !== preg_match( '/addEventListener\(\s*[\'"]click[\'"]\s*,\s*close\s*\)/', $js ), 'the close button does not skip the guard' );
	twd_sk_has( 'Keep editing', $js );
	twd_sk_has( 'Close and lose these changes', $js );
	twd_sk_has( 'role: \'alertdialog\'', $js );
	twd_sk_true( 1 !== preg_match( '/(^|[^.\w])(alert|confirm|prompt)\s*\(/m', $js ), 'no native dialogs' );
	twd_sk_hasnt( 'beforeunload', $js, 'no browser leave-page dialog' );
} );

twd_sk_test( 'unsaved prompt js: each tab with its own form registers a check and a way to put things back', function () {
	foreach ( array( 'twd-site-kit-editor-site.js' => 5, 'twd-site-kit-editor-seo.js' => 1 ) as $file => $count ) {
		$js = file_get_contents( ABSPATH . 'assets/' . $file );
		twd_sk_eq( $count, substr_count( $js, 'ED.addDirtyCheck(' ), $file . ' registers its checks' );
	}
	$main = file_get_contents( ABSPATH . 'assets/twd-site-kit-editor.js' );
	twd_sk_has( 'page text that you pasted but have not applied', $main );
	twd_sk_has( 'discardUnsaved', $main );
	if ( function_exists( 'shell_exec' ) && '' !== trim( (string) shell_exec( 'command -v node 2>/dev/null' ) ) ) {
		foreach ( array( 'editor', 'editor-site', 'editor-seo' ) as $name ) {
			twd_sk_eq( '', trim( (string) shell_exec( 'node --check ' . escapeshellarg( ABSPATH . 'assets/twd-site-kit-' . $name . '.js' ) . ' 2>&1' ) ), $name . ' parses' );
		}
	}
} );

twd_sk_test( 'fold-outs: the Site tab sections, history, extras and the external AI are closed fold-outs that open for keyboard and for edit pills', function () {
	$main = file_get_contents( ABSPATH . 'assets/twd-site-kit-editor.js' );
	$site = file_get_contents( ABSPATH . 'assets/twd-site-kit-editor-site.js' );
	twd_sk_has( "'aria-expanded', 'false'", $main );
	twd_sk_has( "'aria-controls': id", $main );
	twd_sk_has( 'function foldSection(', $main );
	twd_sk_has( 'function revealFold(', $main );
	twd_sk_has( 'revealFold(target);', $main, 'a pill or any focus request opens the fold-out first' );
	twd_sk_has( "foldSection(hist, { summary: 'Undo, or go back to an earlier version' })", $main );
	twd_sk_has( 'Use an external AI instead', $main );
	twd_sk_has( 'ED.foldSection(sec', $site );
	foreach ( array( 'Pick a look, colours, fonts and corners', 'The master document the AI writes from' ) as $s ) {
		twd_sk_has( $s, $site );
	}
	$css = file_get_contents( ABSPATH . 'assets/twd-site-kit-editor.css' );
	twd_sk_has( '.twd-sk-ed__fold', $css );
	twd_sk_true( 1 === preg_match( '/\.twd-sk-ed__fold\s*\{[^}]*min-height:\s*56px/', $css ), 'a big enough target' );
} );
