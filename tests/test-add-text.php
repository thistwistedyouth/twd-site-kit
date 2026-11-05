<?php
// 0.19.0: a new paragraph inside a section, and a copy of a card, entry, step, question or paragraph.

function twd_sk_at_page( $ids = array( 'hero', 'text', 'services', 'faq' ) ) {
	twd_sk_rest_setup();
	twd_stub_add_post( 41, 'page', '[twd_page]' );
	$GLOBALS['twd_stub']['caps'] = true;
	$html = '';
	foreach ( $ids as $id ) {
		$html .= TWD_SK_Registry::get( $id )['skeleton'] . "\n";
	}
	twd_sk_true( ! is_wp_error( TWD_SK_Store::save( 41, $html ) ) );
	return TWD_SK_Store::get_current( 41 );
}

/** The numbers of the pieces of text of one tag, in the page as an editor sees it. */
function twd_sk_at_find( $html, $tag ) {
	preg_match_all( '/<' . $tag . '\b[^>]*data-twd-sk-t="(\d+)"[^>]*>/', TWD_SK_Inline::mark( $html ), $m );
	return $m[1];
}

twd_sk_test( 'add text: a new paragraph goes straight after the paragraph named, as a placeholder, and nothing else changes', function () {
	$html   = twd_sk_at_page();
	$marked = TWD_SK_Inline::mark( $html );
	preg_match( '/<section[^>]*data-twd-sk-sec="1"[^>]*>.*?<p\b[^>]*data-twd-sk-t="(\d+)"/s', $marked, $m );
	$i   = (int) $m[1];
	$out = TWD_SK_Inline::add_text( $html, $i );
	twd_sk_true( ! is_wp_error( $out ), is_wp_error( $out ) ? $out->get_error_message() : '' );
	twd_sk_eq( 'Added a new paragraph', $out['note'] );
	twd_sk_eq( 1, $out['at'], 'it sits in section 1' );
	twd_sk_eq( 1, substr_count( $out['html'], '[PLACEHOLDER: new paragraph]' ), 'one new paragraph' );
	twd_sk_true( $out['piece'] === $i + 1, 'the new text is the next piece: ' . $out['piece'] );
	twd_sk_has( '[PLACEHOLDER: new paragraph]', TWD_SK_Sanitizer::clean( $out['html'] ), 'the cleaner keeps it' );
	twd_sk_true( count( twd_sk_at_find( $out['html'], 'p' ) ) === count( twd_sk_at_find( $html, 'p' ) ) + 1, 'one more piece of text' );
} );

twd_sk_test( 'add text: a list entry gets a list entry, a heading gets a paragraph, and a stale number is refused in plain words', function () {
	$html = twd_sk_at_page( array( 'hero', 'text', 'steps' ) );
	$li   = twd_sk_at_find( $html, 'li' );
	if ( $li ) {
		$out = TWD_SK_Inline::add_text( $html, (int) $li[0] );
		twd_sk_true( ! is_wp_error( $out ) );
		twd_sk_has( '[PLACEHOLDER: new item]', $out['html'] );
		twd_sk_eq( 'Added a new list entry', $out['note'] );
	}
	$h = twd_sk_at_find( $html, 'h2' );
	twd_sk_true( count( $h ) > 0, 'there is a heading' );
	$out = TWD_SK_Inline::add_text( $html, (int) $h[0] );
	twd_sk_true( ! is_wp_error( $out ), is_wp_error( $out ) ? $out->get_error_message() : '' );
	twd_sk_has( '[PLACEHOLDER: new paragraph]', $out['html'] );
	twd_sk_is_error( 'twd_sk_conflict', TWD_SK_Inline::add_text( $html, 9999 ), 'no such piece' );
	twd_sk_is_error( 'twd_sk_conflict', TWD_SK_Inline::add_text( $html, 'x' ), 'not a number' );
} );

twd_sk_test( 'add text: the new paragraph carries the neighbour classes but never its quote line, note box or text styles', function () {
	$html = '<section class="twd-sk-text"><div class="twd-sk-inner"><h2>Title</h2><p class="twd-sk-lead twd-sk-quoteline twd-sk-notebox">First words</p></div></section>';
	$out  = TWD_SK_Inline::add_text( $html, 1 );
	twd_sk_true( ! is_wp_error( $out ), is_wp_error( $out ) ? $out->get_error_message() : '' );
	preg_match( '/<p class="([^"]*)">\[PLACEHOLDER: new paragraph\]/', $out['html'], $m );
	twd_sk_true( isset( $m[1] ), 'a classed paragraph was added: ' . $out['html'] );
	twd_sk_has( 'twd-sk-lead', $m[1] );
	twd_sk_hasnt( 'quoteline', $m[1] );
	twd_sk_hasnt( 'notebox', $m[1] );
} );

twd_sk_test( 'duplicate: an item is copied straight after itself with no id repeated, and a section or a question group is refused', function () {
	$html   = twd_sk_at_page( array( 'hero', 'services', 'faq' ) );
	$marked = TWD_SK_Inline::mark( $html );
	preg_match_all( '/<(\w+)\b[^>]*data-twd-sk-r="(\d+)"/', $marked, $m, PREG_SET_ORDER );
	twd_sk_true( count( $m ) > 3, 'there are removable things' );
	$copied = 0;
	foreach ( $m as $row ) {
		$out = TWD_SK_Inline::duplicate( $html, (int) $row[2] );
		if ( 'section' === $row[1] ) {
			twd_sk_is_error( 'twd_sk_bad_input', $out, 'a section is not copied here' );
			continue;
		}
		if ( is_wp_error( $out ) ) {
			twd_sk_is_error( 'twd_sk_bad_input', $out, 'a group is not copied' );
			continue;
		}
		$copied++;
		twd_sk_true( strlen( $out['html'] ) > strlen( $html ), 'the page grew' );
		preg_match_all( '/\bid="([^"]+)"/', $out['html'], $ids );
		twd_sk_eq( count( $ids[1] ), count( array_unique( $ids[1] ) ), 'no id appears twice' );
		twd_sk_eq( 'Copied an item (to be changed)', $out['note'] );
		twd_sk_true( strlen( TWD_SK_Sanitizer::clean( $out['html'] ) ) > strlen( TWD_SK_Sanitizer::clean( $html ) ), 'the cleaner keeps the copy' );
	}
	twd_sk_true( $copied > 0, 'at least one item could be copied' );
	twd_sk_is_error( 'twd_sk_conflict', TWD_SK_Inline::duplicate( $html, 9999 ) );
	twd_sk_is_error( 'twd_sk_conflict', TWD_SK_Inline::duplicate( $html, 'x' ) );
} );

twd_sk_test( 'add text and duplicate through the page route: one version each, the new piece named, a stale page and a missing piece refused', function () {
	$html   = twd_sk_at_page( array( 'hero', 'text', 'services' ) );
	$ver    = TWD_SK_Store::get_current_version_id( 41 );
	$pieces = twd_sk_at_find( $html, 'p' );
	$out    = TWD_SK_REST::post_structure( twd_sk_rest_req( array( 'id' => 41, 'base_version' => $ver, 'op' => 'add_text', 'piece' => (int) $pieces[0] ) ) );
	twd_sk_true( ! is_wp_error( $out ), is_wp_error( $out ) ? $out->get_error_message() : '' );
	twd_sk_true( isset( $out['piece'] ) && $out['piece'] >= 0, 'the new piece comes back' );
	twd_sk_true( isset( $out['at'] ) );
	twd_sk_true( TWD_SK_Store::get_current_version_id( 41 ) !== $ver, 'a new version' );
	twd_sk_has( '[PLACEHOLDER: new paragraph]', TWD_SK_Store::get_current( 41 ) );
	$stale = TWD_SK_REST::post_structure( twd_sk_rest_req( array( 'id' => 41, 'base_version' => $ver, 'op' => 'duplicate', 'item' => 1 ) ) );
	twd_sk_true( is_wp_error( $stale ), 'a stale page is refused' );
	$bad = TWD_SK_REST::post_structure( twd_sk_rest_req( array( 'id' => 41, 'base_version' => TWD_SK_Store::get_current_version_id( 41 ), 'op' => 'add_text', 'piece' => 9999 ) ) );
	twd_sk_true( is_wp_error( $bad ), 'a missing piece is refused' );
} );

twd_sk_test( 'the AI is told an addition inside a section is an edit, and the new buttons and the reload resume are in the editor script', function () {
	twd_sk_has( 'INSIDE a section is an edit', TWD_SK_Plan::system_prompt() );
	twd_sk_has( 'add a new paragraph, sentence, list entry', TWD_SK_AI::system_prompt( 'sections' ) );
	$js = file_get_contents( TWD_SK_PATH . 'assets/twd-site-kit-inline.js' );
	foreach ( array( "toolButton('Add paragraph below'", "toolButton('Duplicate'", "op: 'add_text'", "op: 'duplicate'", 'twd_sk_p=' ) as $needle ) {
		twd_sk_has( $needle, $js, $needle );
	}
} );

twd_sk_test( 'add text inside a custom section: the new paragraph carries the neighbour label so it can be written in place, and it is always numbered', function () {
	$html = '<section class="twd-sk-custom c-band"><div class="c-wrap"><h2 class="c-title" data-twd-edit="title">Title</h2><p class="c-lead" data-twd-edit="intro">First words</p></div></section>';
	$out  = TWD_SK_Inline::add_text( $html, 1 );
	twd_sk_true( ! is_wp_error( $out ), is_wp_error( $out ) ? $out->get_error_message() : '' );
	twd_sk_has( 'data-twd-edit="intro"', substr( $out['html'], strpos( $out['html'], 'First words' ) ), 'the new paragraph is labelled like the one above' );
	twd_sk_has( 'c-lead', $out['html'] );
	twd_sk_true( $out['piece'] >= 0, 'numbered' );
	// A heading in a custom section: the new paragraph borrows its neighbour paragraph, so it is labelled too.
	$h = TWD_SK_Inline::add_text( $html, 0 );
	twd_sk_true( ! is_wp_error( $h ), is_wp_error( $h ) ? $h->get_error_message() : '' );
	twd_sk_true( $h['piece'] >= 0 );
} );
