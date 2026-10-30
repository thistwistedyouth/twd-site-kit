<?php
// TWD_SK_Structure (0.14.1): add a section and move a section on the page itself, with no AI.

function twd_sk_struct_page() {
	twd_sk_rest_setup();
	twd_stub_add_post( 41, 'page', '[twd_page]' );
	$GLOBALS['twd_stub']['caps'] = true;
	$html = '';
	foreach ( array( 'hero', 'text', 'cta', 'faq' ) as $id ) {
		$html .= TWD_SK_Registry::get( $id )['skeleton'] . "\n";
	}
	twd_sk_true( ! is_wp_error( TWD_SK_Store::save( 41, $html ) ) );
	return TWD_SK_Store::get_current( 41 );
}
function twd_sk_struct_types( $html ) {
	return array_map( array( 'TWD_SK_Sections', 'type_of' ), TWD_SK_Sections::split( $html )['sections'] );
}

twd_sk_test( 'structure: the editor numbers each top-level section from 0, in the page as drawn for an editor only', function () {
	$html   = twd_sk_struct_page();
	$marked = TWD_SK_Inline::mark( $html );
	preg_match_all( '/<section\b[^>]*data-twd-sk-sec="(\d+)"/', $marked, $m );
	twd_sk_eq( array( '0', '1', '2', '3' ), $m[1], 'four sections, numbered in order' );
	twd_sk_eq( 4, count( TWD_SK_Sections::split( $html )['sections'] ), 'the same count TWD_SK_Sections gives' );
	twd_sk_hasnt( 'data-twd-sk-sec', TWD_SK_Store::get_current( 41 ), 'never stored' );
	// A section inside another section is not a top-level section.
	$nested = TWD_SK_Inline::mark( '<section class="twd-sk-text"><div><section class="twd-sk-text"><p>inner</p></section></div></section><section class="twd-sk-cta"><p>two</p></section>' );
	preg_match_all( '/data-twd-sk-sec="(\d+)"/', $nested, $n );
	twd_sk_eq( array( '0', '1' ), $n[1], 'only the two top-level sections are counted' );
} );

twd_sk_test( 'structure add: a chosen section goes in after the one named, as its outline with placeholder words, and nothing else changes', function () {
	$html   = twd_sk_struct_page();
	$before = TWD_SK_Sections::split( $html );
	$out    = TWD_SK_Structure::add( 41, array( 'after' => 1, 'component' => 'services', 'variant' => '' ) );
	twd_sk_true( ! is_wp_error( $out ), is_wp_error( $out ) ? $out->get_error_message() : '' );
	twd_sk_eq( 2, $out['at'], 'it is section 2 now' );
	twd_sk_eq( 'Added a ' . strtolower( TWD_SK_Registry::get( 'services' )['label'] ) . ' section', $out['note'] );
	twd_sk_eq( array( 'hero', 'text', 'services', 'cta', 'faq' ), twd_sk_struct_types( $out['html'] ) );
	twd_sk_has( '[PLACEHOLDER', TWD_SK_Sections::split( $out['html'] )['sections'][2], 'its words are markers to fill in' );
	$after = TWD_SK_Sections::split( $out['html'] );
	foreach ( array( 0 => 0, 1 => 1, 2 => 3, 3 => 4 ) as $was => $now ) {
		twd_sk_eq( $before['sections'][ $was ], $after['sections'][ $now ], 'section ' . $was . ' is untouched' );
	}
	// A variant (another layout) of the section works too.
	$v = TWD_SK_Structure::add( 41, array( 'after' => 3, 'component' => 'services', 'variant' => 'twd-sk-services--links' ) );
	twd_sk_true( ! is_wp_error( $v ) );
	twd_sk_has( 'twd-sk-services--links', TWD_SK_Sections::split( $v['html'] )['sections'][4] );
	twd_sk_eq( 4, $v['at'] );
} );

twd_sk_test( 'structure add: a banner, an unknown section or layout, a place that is not there, and too many sections are refused with plain words', function () {
	twd_sk_struct_page();
	$hero = TWD_SK_Structure::add( 41, array( 'after' => 1, 'component' => 'hero', 'variant' => '' ) );
	twd_sk_is_error( 'twd_sk_bad_input', $hero );
	twd_sk_has( 'one main banner', $hero->get_error_message() );
	$GLOBALS['twd_stub']['caps'] = true;
	$emb = TWD_SK_Structure::add( 41, array( 'after' => 1, 'component' => 'embed', 'variant' => '' ) );
	twd_sk_is_error( 'twd_sk_bad_input', $emb );
	twd_sk_has( 'Site tab', $emb->get_error_message() );
	twd_sk_eq( 4, count( TWD_SK_Sections::split( TWD_SK_Store::get_current( 41 ) )['sections'] ), 'nothing was added' );
	twd_sk_is_error( 'twd_sk_bad_input', TWD_SK_Structure::add( 41, array( 'after' => 1, 'component' => 'made-up' ) ) );
	twd_sk_is_error( 'twd_sk_bad_input', TWD_SK_Structure::add( 41, array( 'after' => 1, 'component' => 'text', 'variant' => 'twd-sk-text--made-up' ) ) );
	foreach ( array( 9, -1, 'x', null ) as $after ) {
		twd_sk_is_error( 'twd_sk_bad_input', TWD_SK_Structure::add( 41, array( 'after' => $after, 'component' => 'text' ) ), 'after ' . var_export( $after, true ) );
	}
	// Section 23i: a page with no sections takes its first one (after -1 as the editor sends it); a hero is still refused.
	twd_stub_add_post( 42, 'page', '[twd_page]' );
	$first = TWD_SK_Structure::add( 42, array( 'after' => -1, 'component' => 'text' ) );
	twd_sk_true( ! is_wp_error( $first ), is_wp_error( $first ) ? $first->get_error_message() : 'a page with no sections takes its first' );
	twd_sk_eq( 0, $first['at'] );
	twd_sk_eq( 'Added a text section', $first['note'] );
	twd_sk_has( '<section class="twd-sk-text', trim( $first['html'] ) );
	twd_sk_is_error( 'twd_sk_bad_input', TWD_SK_Structure::add( 42, array( 'after' => -1, 'component' => 'hero' ) ) );
	twd_sk_is_error( 'twd_sk_bad_input', TWD_SK_Structure::add( 41, 'not an array' ) );
	// Sixty sections is the ceiling.
	$many = str_repeat( TWD_SK_Registry::get( 'text' )['skeleton'], TWD_SK_Sections::MAX_SECTIONS );
	twd_sk_true( ! is_wp_error( TWD_SK_Store::save( 41, $many ) ), 'sixty sections fit in a page' );
	twd_sk_has( '60 sections', TWD_SK_Structure::add( 41, array( 'after' => 1, 'component' => 'text' ) )->get_error_message() );
} );

twd_sk_test( 'structure move: a section swaps with its neighbour, the gaps stay put, and the section keeps its words', function () {
	$html   = twd_sk_struct_page();
	$before = TWD_SK_Sections::split( $html );
	$down   = TWD_SK_Structure::move( 41, array( 'index' => 1, 'dir' => 'down' ) );
	twd_sk_true( ! is_wp_error( $down ), is_wp_error( $down ) ? $down->get_error_message() : '' );
	twd_sk_eq( 2, $down['at'] );
	twd_sk_eq( array( 'hero', 'cta', 'text', 'faq' ), twd_sk_struct_types( $down['html'] ) );
	$after = TWD_SK_Sections::split( $down['html'] );
	twd_sk_eq( $before['gaps'], $after['gaps'], 'the whitespace between sections is where it was' );
	twd_sk_eq( $before['sections'][1], $after['sections'][2], 'the moved section is byte for byte the same' );
	twd_sk_eq( $before['sections'][2], $after['sections'][1] );
	twd_sk_has( 'Moved a text section down', $down['note'] );
	// And back up (the number may arrive as a string from a request).
	$up = TWD_SK_Structure::move( 41, array( 'index' => '3', 'dir' => 'up' ) );
	twd_sk_eq( array( 'hero', 'text', 'faq', 'cta' ), twd_sk_struct_types( $up['html'] ) );
	twd_sk_eq( 2, $up['at'] );
} );

twd_sk_test( 'structure move: the section with the main heading never changes place and nothing moves into it; the ends and bad input are refused in plain words', function () {
	twd_sk_struct_page();
	$m = function ( $index, $dir ) {
		return TWD_SK_Structure::move( 41, array( 'index' => $index, 'dir' => $dir ) );
	};
	twd_sk_has( 'already first', $m( 0, 'up' )->get_error_message() );
	twd_sk_has( 'main heading stays at the top', $m( 0, 'down' )->get_error_message(), 'the heading section cannot move' );
	twd_sk_has( 'main heading stays at the top', $m( 1, 'up' )->get_error_message(), 'nothing moves into its place' );
	twd_sk_has( 'already last', $m( 3, 'down' )->get_error_message() );
	foreach ( array( array( 9, 'up' ), array( -1, 'up' ), array( 'x', 'up' ), array( 1, 'sideways' ), array( 1, null ), array( null, 'up' ), array( 1.5, 'up' ) ) as $bad ) {
		twd_sk_is_error( 'twd_sk_bad_input', $m( $bad[0], $bad[1] ), var_export( $bad, true ) );
	}
	twd_sk_is_error( 'twd_sk_bad_input', TWD_SK_Structure::move( 41, 'nope' ) );
	// A page with no heading section can move its first section.
	twd_sk_true( ! is_wp_error( TWD_SK_Store::save( 41, TWD_SK_Registry::get( 'text' )['skeleton'] . TWD_SK_Registry::get( 'cta' )['skeleton'] ) ) );
	twd_sk_true( ! is_wp_error( $m( 0, 'down' ) ) );
} );

twd_sk_test( 'structure rest: add and move are saved as one version each through the store, name the section\'s new place, and refuse a stale page, a bad action and a bad move', function () {
	twd_sk_struct_page();
	$ver = TWD_SK_Store::get_current_version_id( 41 );
	$req = function ( $params, $base = null ) use ( &$ver ) {
		return twd_sk_rest_req( $params + array( 'id' => 41, 'base_version' => null === $base ? $ver : $base ) );
	};
	$out = TWD_SK_REST::post_structure( $req( array( 'op' => 'add', 'after' => 1, 'component' => 'pricing', 'variant' => '' ) ) );
	twd_sk_true( ! is_wp_error( $out ), is_wp_error( $out ) ? $out->get_error_message() : '' );
	twd_sk_eq( 2, $out['at'] );
	twd_sk_true( $out['version'] > $ver, 'a new version' );
	twd_sk_eq( array( 'hero', 'text', 'pricing', 'cta', 'faq' ), twd_sk_struct_types( TWD_SK_Store::get_current( 41 ) ) );
	$ver = TWD_SK_Store::get_current_version_id( 41 );
	$mv  = TWD_SK_REST::post_structure( $req( array( 'op' => 'move', 'index' => 2, 'dir' => 'down' ) ) );
	twd_sk_true( ! is_wp_error( $mv ) );
	twd_sk_eq( 3, $mv['at'] );
	twd_sk_eq( array( 'hero', 'text', 'cta', 'pricing', 'faq' ), twd_sk_struct_types( TWD_SK_Store::get_current( 41 ) ) );
	$notes = array_map( function ( $v ) {
		return $v['note'];
	}, TWD_SK_Store::list_versions( 41 ) );
	twd_sk_true( in_array( 'Moved a pricing section down', $notes, true ), 'the history says what happened: ' . implode( ' | ', $notes ) );
	// A page that moved on since it was drawn is refused and nothing changes.
	$e = TWD_SK_REST::post_structure( $req( array( 'op' => 'move', 'index' => 1, 'dir' => 'down' ), 1 ) );
	twd_sk_is_error( 'twd_sk_conflict', $e );
	twd_sk_eq( 409, twd_sk_status( $e ) );
	$ver = TWD_SK_Store::get_current_version_id( 41 );
	foreach ( array( 'delete', '', null, 'AI' ) as $op ) {
		$e = TWD_SK_REST::post_structure( $req( array( 'op' => $op ) ) );
		twd_sk_is_error( 'twd_sk_bad_input', $e, 'op ' . var_export( $op, true ) );
		twd_sk_eq( 400, twd_sk_status( $e ) );
	}
	$e = TWD_SK_REST::post_structure( $req( array( 'op' => 'move', 'index' => 0, 'dir' => 'down' ) ) );
	twd_sk_is_error( 'twd_sk_bad_input', $e );
	twd_sk_eq( 400, twd_sk_status( $e ) );
	twd_sk_eq( $ver, TWD_SK_Store::get_current_version_id( 41 ), 'refused moves save nothing' );
	$e = TWD_SK_REST::post_structure( twd_sk_rest_req( array( 'id' => 41, 'op' => 'add', 'after' => 0, 'component' => 'text' ) ) );
	twd_sk_is_error( 'twd_sk_bad_input', $e, 'no base version' );
} );

twd_sk_test( 'structure: the route is a POST on the page, registered outside safe mode, and the editor learns whether the page is live', function () {
	twd_sk_struct_page();
	$found = null;
	foreach ( twd_sk_rest_routes() as $r ) {
		if ( '/pages/(?P<id>\d+)/structure' === $r['route'] ) {
			$found = $r;
		}
	}
	twd_sk_true( null !== $found );
	twd_sk_eq( 'POST', $found['args']['methods'] );
	twd_sk_eq( 'can_write_page', $found['args']['permission_callback'][1], 'the same gate as saving words in place' );
	TWD_SK_Safe::set( true );
	foreach ( twd_sk_rest_routes() as $r ) {
		twd_sk_true( '/pages/(?P<id>\d+)/structure' !== $r['route'], 'not in safe mode' );
	}
	TWD_SK_Safe::set( false );
	// pagePublished
	twd_sk_ed_setup();
	$GLOBALS['twd_stub']['posts'][12]->post_status = 'draft';
	twd_sk_true( twd_sk_ed_loaded() );
	twd_sk_true( false === $GLOBALS['twd_stub']['scripts']['twd-site-kit-editor']['data']['pagePublished'], 'a draft' );
	twd_sk_ed_setup();
	$GLOBALS['twd_stub']['posts'][12]->post_status = 'publish';
	twd_sk_true( twd_sk_ed_loaded() );
	twd_sk_true( true === $GLOBALS['twd_stub']['scripts']['twd-site-kit-editor']['data']['pagePublished'], 'live' );
} );

twd_sk_test( 'structure js: Add section, Move up and Move down in the toolbar, saved through the structure route, reload back into the editor at the section, with a warning on a live page and no native dialogs', function () {
	$js = file_get_contents( ABSPATH . 'assets/twd-site-kit-inline.js' );
	twd_sk_has( "toolButton(oneWindow ? 'Add section after' : 'Add section'", $js );
	twd_sk_has( "toolButton('Move up'", $js );
	twd_sk_has( "toolButton('Move down'", $js );
	// Undo (0.15.0): steps back the last saved change through the page history and reloads at the same section.
	twd_sk_has( "toolButton('Undo'", $js );
	twd_sk_has( "'/undo', { base_version: cfg.currentVersion, allow_placeholders: !!allow }", $js );
	twd_sk_has( "err.code === 'twd_sk_placeholders'", $js );
	twd_sk_has( "Nothing to put back: Put back only returns things you removed with Remove. Use Undo", $js );
	// One revert routine for every way out: the pop-up's close prompt counts unsaved Visual editor changes and reverts them.
	twd_sk_has( "ED.addDirtyCheck(function () {", $js );
	twd_sk_has( "ED.onRedraw(function () {", $js );
	// 0.15.0 fault found in the browser: two functions were both called putBack (the one that restores a piece's
	// words and the toolbar's Put back), so the later one shadowed the first and Cancel never put the words back.
	twd_sk_has( "restoreChildren(t, S.orig[i]);", $js );
	twd_sk_eq( 1, substr_count( $js, 'function putBack(' ), 'one function of that name' );
	twd_sk_has( "'/pages/' + cfg.pageId + '/structure'", $js );
	twd_sk_has( "twd_sk_edit=1&twd_sk_at=", $js, 'the page reloads into the editor at that section' );
	twd_sk_has( 'cfg.pagePublished', $js, 'a live page is warned' );
	twd_sk_has( 'The new section shows its placeholder words to visitors until you replace them', $js );
	twd_sk_has( "return id !== 'hero' && id !== 'embed';", $js, 'a banner and an embed are not offered' );
	twd_sk_has( 'Save or cancel your', $js, 'unsaved changes are never lost to a reload' );
	twd_sk_has( 'resume();', $js );
	twd_sk_has( 'function plainSearch()', $js, 'the resume parameters are removed by splitting the query string, which keeps the rest (a draft page is ?page_id=12)' );
	twd_sk_hasnt( "replace(/([?&])twd_sk_", $js, 'not by one regex that swallows the next separator' );
	foreach ( array( 'alert(', 'confirm(', 'prompt(', 'innerHTML', 'outerHTML', 'insertAdjacentHTML', 'localStorage', 'sessionStorage', 'eval(' ) as $bad ) {
		twd_sk_hasnt( $bad, $js );
	}
	twd_sk_eq( '', trim( (string) shell_exec( 'node --check ' . escapeshellarg( ABSPATH . 'assets/twd-site-kit-inline.js' ) . ' 2>&1' ) ) );
} );
