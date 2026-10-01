<?php
// Store tests: versions, cap, restore, undo, conflicts, limits.

function twd_sk_page( $id = 10 ) {
	twd_stub_add_post( $id, 'page', '[twd_page]' );
	return $id;
}
function twd_sk_p( $text ) {
	return '<p>' . $text . '</p>';
}

twd_sk_test( 'store: nothing stored yet gives empty current, version 0 and no versions', function () {
	$id = twd_sk_page();
	twd_sk_eq( '', TWD_SK_Store::get_current( $id ) );
	twd_sk_eq( 0, TWD_SK_Store::get_current_version_id( $id ) );
	twd_sk_eq( array(), TWD_SK_Store::list_versions( $id ) );
	twd_sk_eq( null, TWD_SK_Store::get_version( $id, 1 ) );
} );

twd_sk_test( 'store: save stores sanitised html as version 1 and current', function () {
	$id = twd_sk_page();
	$r  = TWD_SK_Store::save( $id, '<p onclick="x()">Hi</p><script>bad()</script>' );
	twd_sk_eq( 1, $r['version'] );
	twd_sk_eq( false, $r['unchanged'] );
	twd_sk_eq( '<p>Hi</p>', TWD_SK_Store::get_current( $id ) );
	twd_sk_eq( 1, TWD_SK_Store::get_current_version_id( $id ) );
} );

twd_sk_test( 'store: save returns the sanitiser report', function () {
	$id = twd_sk_page();
	$r  = TWD_SK_Store::save( $id, '<p onclick="x()" class="nope">Hi</p>' );
	twd_sk_eq( 2, $r['report']['total'] );
	twd_sk_eq( array( 'nope' ), $r['report']['removed']['classes'] );
} );

twd_sk_test( 'store: only the two documented meta keys are ever written', function () {
	$id = twd_sk_page();
	TWD_SK_Store::save( $id, twd_sk_p( 'a' ) );
	TWD_SK_Store::save( $id, twd_sk_p( 'b' ) );
	$keys = twd_stub_meta_keys( $id );
	sort( $keys );
	twd_sk_eq( array( '_twd_sk_html', '_twd_sk_versions' ), $keys );
} );

twd_sk_test( 'store: the current row always equals the newest version', function () {
	$id = twd_sk_page();
	TWD_SK_Store::save( $id, twd_sk_p( 'a' ) );
	TWD_SK_Store::save( $id, twd_sk_p( 'b' ) );
	twd_sk_eq( TWD_SK_Store::get_current( $id ), TWD_SK_Store::get_version( $id, 2 )['html'] );
} );

twd_sk_test( 'store: version numbers go up by one each time', function () {
	$id = twd_sk_page();
	for ( $i = 1; $i <= 4; $i++ ) {
		twd_sk_eq( $i, TWD_SK_Store::save( $id, twd_sk_p( 'v' . $i ) )['version'] );
	}
} );

twd_sk_test( 'store: saving identical html is a no-op and uses no history slot', function () {
	$id = twd_sk_page();
	TWD_SK_Store::save( $id, twd_sk_p( 'same' ) );
	$r = TWD_SK_Store::save( $id, twd_sk_p( 'same' ) );
	twd_sk_eq( true, $r['unchanged'] );
	twd_sk_eq( 1, $r['version'] );
	twd_sk_eq( 1, count( TWD_SK_Store::list_versions( $id ) ) );
} );

twd_sk_test( 'store: list_versions is newest first and omits the html', function () {
	$id = twd_sk_page();
	TWD_SK_Store::save( $id, twd_sk_p( 'a' ), array( 'note' => 'first' ) );
	TWD_SK_Store::save( $id, twd_sk_p( 'b' ), array( 'note' => 'second' ) );
	$list = TWD_SK_Store::list_versions( $id );
	twd_sk_eq( array( 2, 1 ), array( $list[0]['id'], $list[1]['id'] ) );
	twd_sk_true( ! isset( $list[0]['html'] ) );
	twd_sk_eq( 'second', $list[0]['note'] );
	twd_sk_eq( 7, $list[0]['user'] );
	twd_sk_eq( 'save', $list[0]['kind'] );
	twd_sk_true( $list[0]['bytes'] > 0 );
	twd_sk_true( 1 === preg_match( '/^\d{4}-\d\d-\d\d \d\d:\d\d:\d\d$/', $list[0]['created'] ) );
} );

twd_sk_test( 'store: cap of 10 versions, oldest dropped first, numbers keep counting', function () {
	$id = twd_sk_page();
	for ( $i = 1; $i <= 13; $i++ ) {
		TWD_SK_Store::save( $id, twd_sk_p( 'v' . $i ) );
	}
	$list = TWD_SK_Store::list_versions( $id );
	twd_sk_eq( 10, count( $list ) );
	twd_sk_eq( 13, $list[0]['id'] );
	twd_sk_eq( 4, $list[9]['id'] );
	twd_sk_eq( null, TWD_SK_Store::get_version( $id, 3 ), 'v3 dropped' );
	twd_sk_eq( twd_sk_p( 'v13' ), TWD_SK_Store::get_current( $id ) );
	twd_sk_eq( 14, TWD_SK_Store::save( $id, twd_sk_p( 'v14' ) )['version'], 'numbers never reused' );
} );

twd_sk_test( 'store: append-only, older versions are never changed by later saves', function () {
	$id = twd_sk_page();
	TWD_SK_Store::save( $id, twd_sk_p( 'one' ) );
	$before = TWD_SK_Store::get_version( $id, 1 );
	TWD_SK_Store::save( $id, twd_sk_p( 'two' ) );
	TWD_SK_Store::restore( $id, 1 );
	TWD_SK_Store::undo( $id );
	twd_sk_eq( $before, TWD_SK_Store::get_version( $id, 1 ) );
} );

twd_sk_test( 'store: restore writes a NEW version with the old html and leaves the old one alone', function () {
	$id = twd_sk_page();
	TWD_SK_Store::save( $id, twd_sk_p( 'one' ) );
	TWD_SK_Store::save( $id, twd_sk_p( 'two' ) );
	$r = TWD_SK_Store::restore( $id, 1 );
	twd_sk_eq( 3, $r['version'] );
	twd_sk_eq( twd_sk_p( 'one' ), TWD_SK_Store::get_current( $id ) );
	twd_sk_eq( twd_sk_p( 'one' ), TWD_SK_Store::get_version( $id, 1 )['html'] );
	twd_sk_eq( twd_sk_p( 'two' ), TWD_SK_Store::get_version( $id, 2 )['html'] );
	$v3 = TWD_SK_Store::get_version( $id, 3 );
	twd_sk_eq( 'restore', $v3['kind'] );
	twd_sk_eq( 1, $v3['restored_from'] );
	twd_sk_eq( 'restore of v1', $v3['note'] );
} );

twd_sk_test( 'store: restoring the version that is already current changes nothing', function () {
	$id = twd_sk_page();
	TWD_SK_Store::save( $id, twd_sk_p( 'one' ) );
	$r = TWD_SK_Store::restore( $id, 1 );
	twd_sk_eq( true, $r['unchanged'] );
	twd_sk_eq( 1, count( TWD_SK_Store::list_versions( $id ) ) );
} );

twd_sk_test( 'store: restoring a version that does not exist is an error', function () {
	$id = twd_sk_page();
	TWD_SK_Store::save( $id, twd_sk_p( 'one' ) );
	twd_sk_is_error( 'twd_sk_version_not_found', TWD_SK_Store::restore( $id, 99 ) );
} );

twd_sk_test( 'store: undo steps back one change and is recorded as a new version', function () {
	$id = twd_sk_page();
	TWD_SK_Store::save( $id, twd_sk_p( 'one' ) );
	TWD_SK_Store::save( $id, twd_sk_p( 'two' ) );
	$r = TWD_SK_Store::undo( $id );
	twd_sk_eq( 3, $r['version'] );
	twd_sk_eq( twd_sk_p( 'one' ), TWD_SK_Store::get_current( $id ) );
	twd_sk_eq( 'undo', TWD_SK_Store::get_version( $id, 3 )['kind'] );
	twd_sk_eq( 'undo to v1', TWD_SK_Store::get_version( $id, 3 )['note'] );
	twd_sk_eq( twd_sk_p( 'two' ), TWD_SK_Store::get_version( $id, 2 )['html'], 'nothing lost' );
} );

twd_sk_test( 'store: undo twice keeps going back instead of flipping between two versions', function () {
	$id = twd_sk_page();
	TWD_SK_Store::save( $id, twd_sk_p( 'one' ) );
	TWD_SK_Store::save( $id, twd_sk_p( 'two' ) );
	TWD_SK_Store::save( $id, twd_sk_p( 'three' ) );
	TWD_SK_Store::undo( $id );
	twd_sk_eq( twd_sk_p( 'two' ), TWD_SK_Store::get_current( $id ) );
	TWD_SK_Store::undo( $id );
	twd_sk_eq( twd_sk_p( 'one' ), TWD_SK_Store::get_current( $id ) );
	twd_sk_is_error( 'twd_sk_nothing_to_undo', TWD_SK_Store::undo( $id ) );
	twd_sk_eq( twd_sk_p( 'one' ), TWD_SK_Store::get_current( $id ), 'unchanged after refused undo' );
} );

twd_sk_test( 'store: undo after a fresh save goes back to what was there before that save', function () {
	$id = twd_sk_page();
	TWD_SK_Store::save( $id, twd_sk_p( 'one' ) );
	TWD_SK_Store::save( $id, twd_sk_p( 'two' ) );
	TWD_SK_Store::undo( $id );                          // back to one (v3)
	TWD_SK_Store::save( $id, twd_sk_p( 'three' ) );     // v4
	TWD_SK_Store::undo( $id );                          // should be one again
	twd_sk_eq( twd_sk_p( 'one' ), TWD_SK_Store::get_current( $id ) );
} );

twd_sk_test( 'store: undo with nothing saved, or only one version, is an error', function () {
	$id = twd_sk_page();
	twd_sk_is_error( 'twd_sk_nothing_to_undo', TWD_SK_Store::undo( $id ) );
	TWD_SK_Store::save( $id, twd_sk_p( 'one' ) );
	twd_sk_is_error( 'twd_sk_nothing_to_undo', TWD_SK_Store::undo( $id ) );
} );

twd_sk_test( 'store: base_version is refused when the page has moved on', function () {
	$id = twd_sk_page();
	TWD_SK_Store::save( $id, twd_sk_p( 'one' ) );
	TWD_SK_Store::save( $id, twd_sk_p( 'two' ) );
	$r = TWD_SK_Store::save( $id, twd_sk_p( 'three' ), array( 'base_version' => 1 ) );
	twd_sk_is_error( 'twd_sk_conflict', $r );
	twd_sk_eq( array( 'current_version' => 2 ), $r->get_error_data() );
	twd_sk_eq( twd_sk_p( 'two' ), TWD_SK_Store::get_current( $id ), 'nothing written' );
} );

twd_sk_test( 'store: base_version matching the current version is accepted', function () {
	$id = twd_sk_page();
	TWD_SK_Store::save( $id, twd_sk_p( 'one' ) );
	$r = TWD_SK_Store::save( $id, twd_sk_p( 'two' ), array( 'base_version' => 1 ) );
	twd_sk_eq( 2, $r['version'] );
} );

twd_sk_test( 'store: base_version 0 means "I expect nothing saved yet"', function () {
	$id = twd_sk_page();
	twd_sk_eq( 1, TWD_SK_Store::save( $id, twd_sk_p( 'one' ), array( 'base_version' => 0 ) )['version'] );
	twd_sk_is_error( 'twd_sk_conflict', TWD_SK_Store::save( $id, twd_sk_p( 'two' ), array( 'base_version' => 0 ) ) );
} );

twd_sk_test( 'store: restore and undo also honour base_version', function () {
	$id = twd_sk_page();
	TWD_SK_Store::save( $id, twd_sk_p( 'one' ) );
	TWD_SK_Store::save( $id, twd_sk_p( 'two' ) );
	twd_sk_is_error( 'twd_sk_conflict', TWD_SK_Store::restore( $id, 1, array( 'base_version' => 1 ) ) );
	twd_sk_is_error( 'twd_sk_conflict', TWD_SK_Store::undo( $id, array( 'base_version' => 1 ) ) );
} );

twd_sk_test( 'store: input over 200 KB is refused before cleaning', function () {
	$id = twd_sk_page();
	$r  = TWD_SK_Store::save( $id, '<p>' . str_repeat( 'a', TWD_SK_Store::MAX_BYTES ) . '</p>' );
	twd_sk_is_error( 'twd_sk_too_large', $r );
	twd_sk_eq( '', TWD_SK_Store::get_current( $id ) );
} );

twd_sk_test( 'store: input just under 200 KB is accepted', function () {
	$id = twd_sk_page();
	$r  = TWD_SK_Store::save( $id, '<p>' . str_repeat( 'a', TWD_SK_Store::MAX_BYTES - 800 ) . '</p>' );
	twd_sk_eq( 1, $r['version'] );
} );

twd_sk_test( 'store: input that cleans to nothing is refused, so a page cannot be blanked by accident', function () {
	$id = twd_sk_page();
	TWD_SK_Store::save( $id, twd_sk_p( 'keep me' ) );
	twd_sk_is_error( 'twd_sk_empty', TWD_SK_Store::save( $id, '<script>alert(1)</script>' ) );
	twd_sk_is_error( 'twd_sk_empty', TWD_SK_Store::save( $id, '' ) );
	twd_sk_eq( twd_sk_p( 'keep me' ), TWD_SK_Store::get_current( $id ) );
} );

twd_sk_test( 'store: non-string input is an error', function () {
	$id = twd_sk_page();
	twd_sk_is_error( 'twd_sk_bad_input', TWD_SK_Store::save( $id, array( 'x' ) ) );
	twd_sk_is_error( 'twd_sk_bad_input', TWD_SK_Store::save( $id, null ) );
} );

twd_sk_test( 'store: unknown page, non-page post and bad ids are errors', function () {
	twd_stub_add_post( 20, 'post' );
	twd_sk_is_error( 'twd_sk_bad_page', TWD_SK_Store::save( 999, '<p>x</p>' ) );
	twd_sk_is_error( 'twd_sk_bad_page', TWD_SK_Store::save( 20, '<p>x</p>' ) );
	twd_sk_is_error( 'twd_sk_bad_page', TWD_SK_Store::save( 0, '<p>x</p>' ) );
	twd_sk_is_error( 'twd_sk_bad_page', TWD_SK_Store::save( -5, '<p>x</p>' ) );
	twd_sk_is_error( 'twd_sk_bad_page', TWD_SK_Store::restore( 999, 1 ) );
	twd_sk_is_error( 'twd_sk_bad_page', TWD_SK_Store::undo( 999 ) );
} );

twd_sk_test( 'store: every write goes through the sanitiser, restore included', function () {
	$id = twd_sk_page();
	TWD_SK_Store::save( $id, twd_sk_p( 'one' ) );
	// Plant a bad old version directly, as if someone edited the meta by hand.
	$raw = get_post_meta( $id, '_twd_sk_versions', true );
	$raw['items'][0]['html'] = '<p onclick="x()">planted</p><script>bad()</script>';
	update_post_meta( $id, '_twd_sk_versions', wp_slash( $raw ) );
	TWD_SK_Store::save( $id, twd_sk_p( 'two' ) );
	TWD_SK_Store::restore( $id, 1 );
	twd_sk_eq( '<p>planted</p>', TWD_SK_Store::get_current( $id ) );
} );

twd_sk_test( 'store: backslashes, quotes and unicode survive a save and read back byte for byte', function () {
	$id   = twd_sk_page();
	$html = "<p>C:\\temp \\\\server \"quoted\" 'single' \\n \u{201C}curly\u{201D} \u{00A3}5 caf\u{00E9} \u{1F600}</p>";
	TWD_SK_Store::save( $id, $html );
	$got = TWD_SK_Store::get_current( $id );
	twd_sk_has( 'C:\\temp \\\\server', $got, 'backslashes' );
	twd_sk_has( "\u{201C}curly\u{201D} \u{00A3}5 caf\u{00E9} \u{1F600}", $got, 'unicode' );
	twd_sk_eq( $got, TWD_SK_Store::get_version( $id, 1 )['html'], 'current row and version agree' );
} );

twd_sk_test( 'store: a note is stored, trimmed, dash-stripped and length-limited', function () {
	$id = twd_sk_page();
	TWD_SK_Store::save( $id, twd_sk_p( 'a' ), array( 'note' => "  fix \u{2014} typo  " ) );
	twd_sk_eq( 'fix, typo', TWD_SK_Store::get_version( $id, 1 )['note'] );
	TWD_SK_Store::save( $id, twd_sk_p( 'b' ), array( 'note' => str_repeat( 'n', 500 ) ) );
	twd_sk_eq( 200, strlen( TWD_SK_Store::get_version( $id, 2 )['note'] ) );
} );

twd_sk_test( 'store: a corrupt or missing version row is read as empty, not a crash', function () {
	$id = twd_sk_page();
	update_post_meta( $id, '_twd_sk_versions', 'not an array' );
	twd_sk_eq( array(), TWD_SK_Store::list_versions( $id ) );
	twd_sk_eq( 1, TWD_SK_Store::save( $id, twd_sk_p( 'fresh' ) )['version'] );
} );

twd_sk_test( 'store: pages are independent of each other', function () {
	$a = twd_sk_page( 10 );
	$b = twd_sk_page( 11 );
	TWD_SK_Store::save( $a, twd_sk_p( 'A' ) );
	TWD_SK_Store::save( $b, twd_sk_p( 'B' ) );
	twd_sk_eq( twd_sk_p( 'A' ), TWD_SK_Store::get_current( $a ) );
	twd_sk_eq( twd_sk_p( 'B' ), TWD_SK_Store::get_current( $b ) );
	twd_sk_eq( 1, TWD_SK_Store::get_current_version_id( $b ) );
} );
