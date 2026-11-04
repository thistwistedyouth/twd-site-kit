<?php
// The compact, grouped Your Edits window (4 Nov 2026, step 1 of making it friendlier): small icons with their words kept,
// groups that say what they change, no Advanced fold, one duplicate label gone, compact buttons on a computer and large
// tap targets with words on a phone. The Simple editor and the classic bar are not touched. Uses the helpers in test-editor.php.

function twd_sk_wc_editor() {
	return file_get_contents( ABSPATH . 'assets/twd-site-kit-editor.js' );
}
function twd_sk_wc_inline() {
	return file_get_contents( ABSPATH . 'assets/twd-site-kit-inline.js' );
}
function twd_sk_wc_css() {
	return file_get_contents( dirname( __DIR__ ) . '/tools/editor.src.css' );
}

twd_sk_test( 'window: the server gives the editor the name space its icons are drawn in, and the script holds no web address of its own', function () {
	twd_sk_ed_setup( array( 'edit_pages', 'edit_post:12', 'manage_options' ) );
	twd_sk_true( twd_sk_ed_loaded() );
	$data = $GLOBALS['twd_stub']['scripts']['twd-site-kit-editor']['data'];
	twd_sk_eq( 'http://www.w3.org/2000/svg', $data['svgNs'] );
	foreach ( array( twd_sk_wc_editor(), twd_sk_wc_inline(), file_get_contents( ABSPATH . 'assets/twd-site-kit-editor-snag.js' ) ) as $js ) {
		twd_sk_true( 1 !== preg_match( '#https?://#i', $js ), 'no web address typed in the script' );
	}
	twd_sk_has( 'document.createElementNS(ns, \'svg\')', twd_sk_wc_editor() );
	twd_sk_has( 'var ns = cfg.svgNs;', twd_sk_wc_editor(), 'the name space comes from the server' );
	foreach ( array( 'innerHTML', 'outerHTML', 'insertAdjacentHTML' ) as $bad ) {
		twd_sk_hasnt( $bad, twd_sk_wc_editor() );
	}
} );

twd_sk_test( 'window: an icon button keeps its words, a label and a tooltip, and without the name space it is just the text button as before', function () {
	$js = twd_sk_wc_editor();
	$fn = substr( $js, strpos( $js, 'function iconize(' ) );
	$fn = substr( $fn, 0, strpos( $fn, "\n\t}\n" ) );
	twd_sk_has( "var svg = icon(name);", $fn );
	twd_sk_has( 'if (!svg || !btn) {', str_replace( "\r", '', $fn ), 'no icon, nothing changes' );
	twd_sk_has( "el('span', { className: 'twd-sk-ed__icotext', text: words })", $fn, 'the words stay in a span' );
	twd_sk_has( "btn.setAttribute('aria-label', words)", $fn );
	twd_sk_has( "btn.setAttribute('title', words)", $fn );
	twd_sk_has( "aria-hidden', 'true'", $js, 'the drawing itself is hidden from a screen reader' );
	foreach ( array( 'icon: icon,', 'iconize: iconize,', 'setWords: setWords,' ) as $needle ) {
		twd_sk_has( $needle, $js, $needle );
	}
	// Every icon the buttons ask for exists.
	preg_match( '/var ICONS = \{(.*?)\n\t\};/s', $js, $m );
	preg_match_all( "/^\s+([a-z]+): '/m", $m[1], $names );
	foreach ( array( 'close', 'minimise', 'snag', 'eye', 'window', 'expand', 'up', 'down', 'plus', 'bin', 'link', 'look' ) as $name ) {
		twd_sk_true( in_array( $name, $names[1], true ), 'icon ' . $name );
	}
} );

twd_sk_test( 'window: the header is one row of small icons (Snag, View as Client, Window, Full height, Minimise, Close) and the labels that change keep the icon', function () {
	$js = twd_sk_wc_editor();
	foreach ( array( "iconize(closeBtn, 'close');", "iconize(minBtn, 'minimise');", "iconize(winBtn, 'window');", "iconize(fullBtn, 'expand');", "iconize(viewBtn, 'eye');" ) as $needle ) {
		twd_sk_has( $needle, $js, $needle );
	}
	twd_sk_has( "ED.iconize(b, 'snag');", file_get_contents( ABSPATH . 'assets/twd-site-kit-editor-snag.js' ) );
	// The Window and Full height words change with the window; they go through setWords so the icon is kept.
	twd_sk_has( "setWords(ui.winBtn, mode === 'centre' ? 'Window'", $js );
	twd_sk_has( "setWords(ui.fullBtn, full ? 'Normal height' : 'Full height'", $js );
	twd_sk_hasnt( 'ui.winBtn.textContent', $js );
	twd_sk_hasnt( 'ui.fullBtn.textContent', $js );
	twd_sk_has( "'Window: floating'", $js, 'the words are the same ones' );
} );

twd_sk_test( 'window: the everyday text and section tools are icons in the one window only; each group says what it changes; Advanced is no longer in the row', function () {
	$js = str_replace( "\r", '', twd_sk_wc_inline() );
	twd_sk_has( "var TOOL_ICONS = { 'Look': 'look', 'Move up': 'up', 'Move down': 'down', 'Add section after': 'plus', 'Delete section': 'bin', 'Delete this text': 'bin', 'Link': 'link' };", $js );
	twd_sk_has( 'if (oneWindow && !SIMPLE && TOOL_ICONS[label] && ED.iconize) {', $js, 'never in the classic bar or the Simple editor' );
	twd_sk_has( "text: 'This text'", $js );
	twd_sk_has( "text: 'This section'", $js );
	twd_sk_has( "text: 'This page'", $js );
	twd_sk_hasnt( "text: 'Page:'", $js );
	// The words that stay words: the AI and replace actions, the type styles, Put back, Undo.
	twd_sk_has( "toolButton('Describe a change'", $js );
	twd_sk_has( "toolButton('Replace this section'", $js );
	twd_sk_has( "toolButton('Quote line'", $js );
	twd_sk_has( "toolButton('Put back'", $js );
	// The one place two buttons shared a label: the section's one now says where it goes.
	twd_sk_has( "toolButton('Import into this section'", $js );
	twd_sk_has( "toolButton('Import a page'", $js, 'the Page row keeps the plain label' );
	twd_sk_has( "toolButton('Export section'", $js );
	twd_sk_hasnt( 'ui.advanced);', $js, 'the Advanced button is no longer put in the row' );
	// The Simple editor builds none of it.
	$simple = substr( $js, strpos( $js, 'function simpleKids(' ) );
	$simple = substr( $simple, 0, strpos( $simple, "\n\t}\n" ) );
	foreach ( array( 'TOOL_ICONS', 'iconize', 'grouplabel' ) as $bad ) {
		twd_sk_hasnt( $bad, $simple, 'the Simple editor never builds ' . $bad );
	}
} );

twd_sk_test( 'window css: the words of an icon button are hidden only on a computer; a phone keeps every tap target large and shows the words beside the icons; compact rules are for a computer', function () {
	$css = twd_sk_wc_css();
	twd_sk_has( '.twd-sk-ed__grouplabel', $css );
	twd_sk_has( '@media (min-width: 701px) {', $css );
	$block = substr( $css, strpos( $css, '@media (min-width: 701px) {' ) );
	$block = substr( $block, 0, strpos( $block, "\n}" ) );
	foreach ( array( '.twd-sk-ed__icobtn .twd-sk-ed__icotext', '.twd-sk-ed__dialog--compact .twd-sk-ed__tool', '.twd-sk-ed__dialog--compact .twd-sk-ed__close', '.twd-sk-ed__dialog--compact .twd-sk-ed__stylerow .twd-sk-ed__styleselect', '.twd-sk-ed__dialog--compact .twd-sk-ed__inlineactions .twd-sk-ed__btn' ) as $needle ) {
		twd_sk_has( $needle, $block, 'only for a computer: ' . $needle );
	}
	// Outside that block (so on a phone too) only the header's own words are hidden: its icons are 48px tap targets with labels.
	$outside = str_replace( $block, '', $css );
	twd_sk_has( '.twd-sk-ed__header .twd-sk-ed__icobtn .twd-sk-ed__icotext', $outside );
	twd_sk_hasnt( '.twd-sk-ed__tool--ico .twd-sk-ed__icotext', $outside, 'a phone shows the tool words' );
	twd_sk_true( 1 !== preg_match( '/margin:\s*-/', $css ), 'no negative margins' );
	$built = file_get_contents( ABSPATH . 'assets/twd-site-kit-editor.css' );
	twd_sk_has( '.twd-sk-ed .twd-sk-ed__grouplabel.twd-sk-ed__grouplabel', $built, 'the built stylesheet has it, doubled' );
} );
