<?php
// Delete section as one click in the administrator's section row (4 Nov 2026, at the owner's request): where the button
// lives, when it shows, that it still asks first and goes through the same remove and Put back paths, and that the server
// only lets a section go that is not the one with the page's main heading and not the safety notice.
// Uses the helpers in test-inline.php.

function twd_sk_ds_js() {
	return file_get_contents( ABSPATH . 'assets/twd-site-kit-inline.js' );
}
function twd_sk_ds_fn( $js, $name ) {
	$body = substr( $js, strpos( $js, 'function ' . $name . '(' ) );
	return substr( $body, 0, strpos( $body, "\n\t}\n" ) );
}

twd_sk_test( 'delete section: the administrator\'s section row holds it directly, after Import a page and before Advanced, and Advanced no longer repeats it', function () {
	$js = twd_sk_ds_js();
	twd_sk_has( 'sectionRow.push(ui.replace, ui.importBtn, ui.remove, ui.advanced);', $js );
	twd_sk_has( 'var advancedTools = CUSTOM ? sectionTools.filter(function (b) {', $js );
	twd_sk_has( 'return b !== ui.remove;', $js );
	twd_sk_has( "'Design tools for this section' }, advancedTools.concat([ui.exportBtn]))", $js );
	twd_sk_hasnt( 'Add section after, Delete section, Export', $js, 'the Advanced button\'s help no longer lists it' );
	// It is the same button as before, so the label and the ask-first card are the ones the owner already knows.
	twd_sk_has( "toolButton('Delete section', 'Delete the section the cursor is in (it asks first; Put back or Undo brings it back)', deleteSection)", $js );
} );

twd_sk_test( 'delete section: shown only for a section the server numbers as removable, hidden before anything is clicked, and still asks once before it deletes', function () {
	$js = twd_sk_ds_js();
	twd_sk_has( "show(ui.remove, !!(sec && sec.hasAttribute('data-twd-sk-r')));", $js );
	twd_sk_has( "if (CUSTOM && ui.remove) {", $js );
	// Hidden when the bar is first built (updateContext shows it for the right section).
	twd_sk_has( "if (CUSTOM) {\n\t\t\tshow(ui.remove, false);", str_replace( "\r", '', $js ) );
	$fn = twd_sk_ds_fn( str_replace( "\r", '', $js ), 'deleteSection' );
	twd_sk_has( "'Delete it'", $fn, 'asks first' );
	twd_sk_has( "'Keep it'", $fn );
	twd_sk_has( 'removeThis();', $fn, 'goes through the Remove path, so Put back, Cancel, Save and Undo work' );
	twd_sk_hasnt( 'parentNode.removeChild', $fn );
	twd_sk_has( 'The page title cannot be deleted: every page keeps its main heading.', $fn, 'and still explains itself if reached for the title section' );
} );

twd_sk_test( 'delete section: nobody else gets a different bar: the classic bar and the Simple editor are as they were, and the Simple editor has no delete at all', function () {
	$js = twd_sk_ds_js();
	twd_sk_has( '[ui.look, ui.moveUp, ui.moveDown, ui.addSec, ui.remove]', $js, 'the compact one-window row for a non-administrator in the one window' );
	twd_sk_has( '[ui.bold, ui.italic, ui.link, ui.quote, ui.note, ui.heading, ui.look, ui.remove, ui.putBack]', $js, 'the classic bar' );
	$simple = twd_sk_ds_fn( str_replace( "\r", '', $js ), 'simpleKids' );
	foreach ( array( 'ui.remove', 'deleteSection', 'deleteText', 'removeThis', 'Delete' ) as $bad ) {
		twd_sk_hasnt( $bad, $simple, 'the Simple editor never offers ' . $bad );
	}
	// The administrator row is built only when the editor was given the custom switch (administrators, kit page, not safe mode, not the Simple editor).
	$editor = file_get_contents( ABSPATH . 'includes/class-twd-sk-editor.php' );
	twd_sk_has( "'custom'         => (bool) \$context['is_kit_page'] && ! TWD_SK_Safe::on() && ! \$simple && \$can_manage", $editor );
} );

twd_sk_test( 'delete section: the server lets an ordinary section go and keeps the section with the main heading and the safety notice; one version, and Undo brings it back', function () {
	twd_sk_rest_setup( array( 'edit_pages', 'edit_post:12' ) );
	TWD_SK_Store::save( 12, twd_sk_inline_sample() );
	$stored = TWD_SK_Store::get_current( 12 );
	$marked = TWD_SK_Inline::mark( $stored );
	// The numbered sections (data-twd-sk-sec) are the pencils' sections; the removable ones carry data-twd-sk-r too.
	twd_sk_true( 1 !== preg_match( '/<section class="twd-sk-hero[^"]*"[^>]*data-twd-sk-r=/', $marked ), 'the hero with the h1 has no delete number' );
	twd_sk_true( 1 !== preg_match( '/<section class="twd-sk-notice[^"]*"[^>]*data-twd-sk-r=/', $marked ), 'the safety notice has none' );
	twd_sk_true( 1 === preg_match( '/<section class="twd-sk-services[^"]*"[^>]*data-twd-sk-r="1"/', $marked ), 'an ordinary section has one' );
	$base = TWD_SK_Store::get_current_version_id( 12 );
	$out  = TWD_SK_REST::post_inline( twd_sk_rest_req( array( 'base_version' => $base, 'removes' => array( 1 ) ) ) );
	twd_sk_true( is_array( $out ), is_wp_error( $out ) ? $out->get_error_message() : '' );
	$now = TWD_SK_Store::get_current( 12 );
	twd_sk_hasnt( 'twd-sk-services', $now, 'the section is gone' );
	twd_sk_has( 'twd-sk-hero__title', $now, 'the section with the main heading stays' );
	twd_sk_has( 'twd-sk-notice', $now, 'and so does the safety notice' );
	twd_sk_eq( 'Changed on the page (1 removed)', TWD_SK_Store::list_versions( 12 )[0]['note'] );
	TWD_SK_Store::undo( 12, array( 'base_version' => TWD_SK_Store::get_current_version_id( 12 ) ) );
	twd_sk_has( 'twd-sk-services', TWD_SK_Store::get_current( 12 ), 'Undo brings it back' );
} );
