<?php
// The Your Edits window follows what was clicked (4 Nov 2026, step 2 of making it friendlier). With nothing clicked it shows
// the page row, the hint and the list of sections; once words, a picture or a section are clicked those step aside for
// that thing's own tools, a Page tools link brings them back, and the wordy section actions wait behind More while words are
// the thing clicked. Only the one window (an administrator's) is touched: the Simple editor and the classic bar are not.
// Uses the helpers in test-window-compact.php.

function twd_sk_wf_inline() {
	return str_replace( "\r", '', file_get_contents( ABSPATH . 'assets/twd-site-kit-inline.js' ) );
}
function twd_sk_wf_update_context() {
	$js = twd_sk_wf_inline();
	$fn = substr( $js, strpos( $js, 'function updateContext() {' ) );
	return substr( $fn, 0, strpos( $fn, "\n\t}\n" ) );
}

twd_sk_test( 'window follows: the bar starts with the page tools showing and the Page tools link exists only in the one window', function () {
	$js = twd_sk_wf_inline();
	twd_sk_has( "'data-twd-sk-ctx': 'none', 'data-twd-sk-page': 'on'", $js, 'nothing clicked: the page tools show' );
	twd_sk_has( "ui.pageToggle = el('button', { type: 'button', className: 'twd-sk-ed__allsections twd-sk-ed__pagetoggle', text: 'Page tools'", $js );
	twd_sk_has( "'aria-expanded': 'false', hidden: '' });", $js, 'a real button, hidden until something is clicked' );
	twd_sk_has( 'oneWindow ? [ui.ctxText, ui.pageToggle, ui.allSections] : [ui.ctxText, ui.allSections]', $js, 'the classic bar never gets it' );
	twd_sk_has( 'pageTools: false, ctxKey: \'\'', $js );
} );

twd_sk_test( 'window follows: updateContext decides, once, whether the page row and the list of sections show', function () {
	$fn = twd_sk_wf_update_context();
	twd_sk_true( '' !== $fn, 'found updateContext' );
	// The Simple editor returns before any of it.
	twd_sk_true( strpos( $fn, 'if (SIMPLE) {' ) < strpos( $fn, 'S.pageTools' ), 'the Simple editor leaves before the page tools are decided' );
	twd_sk_has( "var ctxKey = kind + ':' + (sec ? sec.getAttribute('data-twd-sk-sec') : '');", $fn );
	twd_sk_has( 'if (ctxKey !== S.ctxKey) {', $fn, 'a new place clicked puts the page tools away' );
	twd_sk_has( 'S.pageTools = false;', $fn );
	twd_sk_has( "var pageOn = kind === 'none' || S.pageTools;", $fn, 'shown with nothing clicked, or when asked for' );
	twd_sk_has( "ui.bar.setAttribute('data-twd-sk-page', pageOn ? 'on' : 'off');", $fn );
	twd_sk_has( "show(ui.pageToggle, !!(oneWindow && kind !== 'none' && !S.view));", $fn, 'the link shows once something is clicked, not inside a pencil view' );
	twd_sk_has( "S.pageTools ? 'Hide page tools' : 'Page tools'", $fn );
	twd_sk_has( "ui.pageToggle.setAttribute('aria-expanded', S.pageTools ? 'true' : 'false');", $fn );
	twd_sk_has( 'if (oneWindow && ED && typeof ED.showOutline === \'function\') {', $fn, 'the classic bar never touches the outline' );
	twd_sk_has( 'ED.showOutline(!S.view && pageOn);', $fn, 'the list of sections and the long hint follow the same switch; a pencil view always hides them' );
	// The toggle itself.
	$js = twd_sk_wf_inline();
	twd_sk_has( "ui.pageToggle.addEventListener('click', function () {\n\t\t\tS.pageTools = !S.pageTools;\n\t\t\tupdateContext();", $js );
} );

twd_sk_test( 'window follows: All sections (the pencil view\'s way back) brings the page tools and the list back', function () {
	$js = twd_sk_wf_inline();
	$fn = substr( $js, strpos( $js, 'ED.sectionView = function (on, index) {' ) );
	$fn = substr( $fn, 0, strpos( $fn, "\n\t};\n" ) );
	$off = substr( $fn, strpos( $fn, 'if (!S.view) {' ) );
	twd_sk_has( 'S.pageTools = true;', substr( $off, 0, 300 ), 'leaving the one-section view shows the page tools' );
	twd_sk_has( 'updateContext();', $fn, 'and the window is redrawn after' );
} );

twd_sk_test( 'window follows css: the page row hides when something is clicked, the wordy section actions wait behind More for words, and the built stylesheet has both', function () {
	$css = twd_sk_wc_css();
	twd_sk_has( '.twd-sk-ed__inlinebar--inwin[data-twd-sk-page="off"] .twd-sk-ed__inlinetools--page { display: none; }', $css );
	twd_sk_has( '.twd-sk-ed__inlinebar--inwin[data-twd-sk-ctx="words"]:not([data-twd-sk-more="on"]) .twd-sk-ed__inlinetools--actions { display: none; }', $css );
	twd_sk_has( '.twd-sk-ed__pagetoggle { margin-left: auto; }', $css );
	// Not inside a media query: a phone follows the same rules (it has the least room to spare).
	$block = substr( $css, strpos( $css, '@media (min-width: 701px) {' ) );
	$block = substr( $block, 0, strpos( $block, "\n}" ) );
	twd_sk_hasnt( 'data-twd-sk-page', $block, 'not in the computer-only block' );
	$narrow = substr( $css, strpos( $css, '@media (max-width: 700px) {' ) );
	$narrow = substr( $narrow, 0, strpos( $narrow, "\n}" ) );
	twd_sk_hasnt( 'data-twd-sk-page', $narrow, 'not in the phone-only block' );
	$built = file_get_contents( ABSPATH . 'assets/twd-site-kit-editor.css' );
	twd_sk_has( 'data-twd-sk-page="off"', $built );
	twd_sk_has( '.twd-sk-ed__inlinetools--actions', $built );
	twd_sk_true( 1 !== preg_match( '/margin:\s*-/', $css ), 'no negative margins' );
} );

twd_sk_test( 'window follows: the Simple editor and the classic bar do not build the page tools link', function () {
	$js = twd_sk_wf_inline();
	$simple = substr( $js, strpos( $js, 'function simpleKids(' ) );
	$simple = substr( $simple, 0, strpos( $simple, "\n\t}\n" ) );
	foreach ( array( 'pageToggle', 'pageTools', 'data-twd-sk-page' ) as $bad ) {
		twd_sk_hasnt( $bad, $simple, 'the Simple editor never builds ' . $bad );
	}
	// The Simple editor's bar is not in the one window, so the css rules (scoped to --inwin) never reach it.
	twd_sk_hasnt( 'twd-sk-ed__simple[data-twd-sk-page', twd_sk_wc_css() );
} );
