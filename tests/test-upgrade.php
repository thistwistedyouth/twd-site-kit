<?php
// Bring pages up to date (0.15.0): add what a later version added to sections built earlier.

/** A Resources section as 0.14 built it: the grid, and no line for an empty grid. */
function twd_sk_up_old_resources( $strip = false ) {
	$c  = TWD_SK_Registry::get( 'resources' );
	$sk = $strip ? $c['variants']['twd-sk-resources--strip']['skeleton'] : $c['skeleton'];
	return str_replace( "\n" . TWD_SK_Registry::NO_ARTICLES_LINE, '', $sk );
}

function twd_sk_up_site() {
	$hero = '<section class="twd-sk-hero"><div class="twd-sk-inner"><h1 class="twd-sk-hero__title">Welcome</h1></div></section>';
	twd_stub_add_post( 61, 'page', '[twd_page]' );
	$GLOBALS['twd_stub']['posts'][61]->post_title = 'Resources';
	twd_stub_add_post( 62, 'page', '[twd_page]' );
	$GLOBALS['twd_stub']['posts'][62]->post_title = 'About';
	twd_stub_add_post( 63, 'page', '[twd_page]' );
	$GLOBALS['twd_stub']['posts'][63]->post_title = 'Home';
	TWD_SK_Store::save( 61, $hero . "\n" . twd_sk_up_old_resources( false ) );
	TWD_SK_Store::save( 62, $hero . '<section class="twd-sk-text"><div class="twd-sk-inner"><p>Real words.</p></div></section>' );
	TWD_SK_Store::save( 63, $hero . "\n" . twd_sk_up_old_resources( true ) . "\n" . twd_sk_up_old_resources( false ) );
	$GLOBALS['twd_stub']['caps'] = true;
}

twd_sk_test( 'upgrade: an older Resources section gets the line straight after its grid, in both layouts, and nothing else changes', function () {
	foreach ( array( false, true ) as $strip ) {
		$old = twd_sk_up_old_resources( $strip );
		twd_sk_hasnt( 'twd-sk-resources__none', $old );
		$av = TWD_SK_Upgrade::available( $old );
		twd_sk_eq( 1, count( $av ) );
		twd_sk_eq( 'resources_none', $av[0]['id'] );
		twd_sk_eq( 1, $av[0]['count'] );
		$r = TWD_SK_Upgrade::apply( $old );
		$c   = TWD_SK_Registry::get( 'resources' );
		$new = $strip ? $c['variants']['twd-sk-resources--strip']['skeleton'] : $c['skeleton'];
		twd_sk_eq( $new, $r['html'], 'the result is exactly what a new section looks like' );
		twd_sk_eq( $r['html'], TWD_SK_Sanitizer::clean( $r['html'] ), 'the sanitiser keeps it as it is' );
	}
	// The strip keeps the line inside its grid column.
	$r = TWD_SK_Upgrade::apply( twd_sk_up_old_resources( true ) );
	twd_sk_true( 1 === preg_match( '#<div class="twd-sk-resources__grid">\s*\[twd_articles[^\]]*\]\s*<p class="twd-sk-resources__none">[^<]*</p>\s*</div>#', $r['html'] ), 'inside the grid column' );
} );

twd_sk_test( 'upgrade: running it twice does nothing the second time; a section that already has the line, has no grid, or is another kind is left alone byte for byte', function () {
	$old   = twd_sk_up_old_resources( false );
	$once  = TWD_SK_Upgrade::apply( $old );
	$twice = TWD_SK_Upgrade::apply( $once['html'] );
	twd_sk_eq( $once['html'], $twice['html'] );
	twd_sk_eq( array(), $twice['applied'] );
	twd_sk_eq( array(), TWD_SK_Upgrade::available( $once['html'] ) );
	$same = array(
		'<section class="twd-sk-resources twd-sk-tone-base"><div class="twd-sk-inner"><h2 class="twd-sk-title">Reading</h2><p>No grid here.</p></div></section>',
		'<section class="twd-sk-text"><div class="twd-sk-inner"><p>[twd_articles count="3"] mentioned in a text section</p></div></section>',
		'',
		'<p>Loose text</p>',
	);
	foreach ( $same as $html ) {
		twd_sk_eq( array(), TWD_SK_Upgrade::available( $html ) );
		twd_sk_eq( $html, TWD_SK_Upgrade::apply( $html )['html'] );
	}
	// Words the person wrote around the grid stay exactly as they are.
	$mine = str_replace( 'Latest articles', 'My own heading', $old );
	twd_sk_has( 'My own heading', TWD_SK_Upgrade::apply( $mine )['html'] );
	twd_sk_eq( $mine, str_replace( "\n" . TWD_SK_Registry::NO_ARTICLES_LINE, '', TWD_SK_Upgrade::apply( $mine )['html'] ) );
	twd_sk_eq( array(), TWD_SK_Upgrade::apply( $old, array( 'something_else' ) )['applied'], 'asking for an upgrade that is not on the list does nothing' );
} );

twd_sk_test( 'upgrade: every section of a page is dealt with (two in one page), only the ones that need it', function () {
	$hero = '<section class="twd-sk-hero"><div class="twd-sk-inner"><h1 class="twd-sk-hero__title">Welcome</h1></div></section>';
	$page = $hero . "\n" . twd_sk_up_old_resources( true ) . "\n" . TWD_SK_Registry::get( 'resources' )['skeleton'] . "\n" . twd_sk_up_old_resources( false );
	twd_sk_eq( 2, TWD_SK_Upgrade::available( $page )[0]['count'] );
	$r = TWD_SK_Upgrade::apply( $page );
	twd_sk_eq( 3, substr_count( $r['html'], '<p class="twd-sk-resources__none">' ) );
	twd_sk_eq( 2, $r['applied'][0]['count'] );
	twd_sk_has( $hero, $r['html'] );
} );

twd_sk_test( 'upgrade: the scan lists only pages that need it and changes nothing', function () {
	twd_sk_up_site();
	$before = json_encode( array( $GLOBALS['twd_stub']['options'], $GLOBALS['twd_stub']['meta'] ) );
	$scan   = TWD_SK_Upgrade::scan();
	twd_sk_eq( $before, json_encode( array( $GLOBALS['twd_stub']['options'], $GLOBALS['twd_stub']['meta'] ) ), 'read only' );
	twd_sk_eq( array( 61, 63 ), array_map( function ( $p ) {
		return $p['id'];
	}, $scan ) );
	twd_sk_eq( 'Resources', $scan[0]['title'] );
	twd_sk_eq( 3, TWD_SK_Upgrade::total( $scan ) );
} );

twd_sk_test( 'upgrade: run writes each page as its own version with a note, once, and Undo puts the old page back', function () {
	twd_sk_up_site();
	$v61 = count( TWD_SK_Store::list_versions( 61 ) );
	$v62 = count( TWD_SK_Store::list_versions( 62 ) );
	$old = TWD_SK_Store::get_current( 61 );
	$out = TWD_SK_Upgrade::run();
	twd_sk_eq( array(), $out['failed'] );
	twd_sk_eq( array( 61, 63 ), array_map( function ( $p ) {
		return $p['id'];
	}, $out['updated'] ) );
	twd_sk_eq( $v61 + 1, count( TWD_SK_Store::list_versions( 61 ) ) );
	twd_sk_eq( $v62, count( TWD_SK_Store::list_versions( 62 ) ), 'a page that did not need it is not touched' );
	twd_sk_has( 'Brought up to date: resources_none', TWD_SK_Store::list_versions( 61 )[0]['note'] );
	twd_sk_has( 'twd-sk-resources__none', TWD_SK_Store::get_current( 61 ) );
	twd_sk_eq( array(), TWD_SK_Upgrade::scan(), 'nothing left to do' );
	twd_sk_eq( array(), TWD_SK_Upgrade::run()['updated'], 'the second run changes nothing' );
	twd_sk_eq( $v61 + 1, count( TWD_SK_Store::list_versions( 61 ) ) );
	$u = TWD_SK_Store::undo( 61 );
	twd_sk_true( ! is_wp_error( $u ), 'undo works' );
	twd_sk_eq( $old, TWD_SK_Store::get_current( 61 ), 'the old page is back exactly' );
} );

twd_sk_test( 'upgrade: run can be limited to some pages', function () {
	twd_sk_up_site();
	$out = TWD_SK_Upgrade::run( array( 63 ) );
	twd_sk_eq( array( 63 ), array_map( function ( $p ) {
		return $p['id'];
	}, $out['updated'] ) );
	twd_sk_eq( array( 61 ), array_map( function ( $p ) {
		return $p['id'];
	}, TWD_SK_Upgrade::scan() ) );
} );

twd_sk_test( 'upgrade rest: administrators list and apply; apply needs a yes; only pages the person may edit are changed', function () {
	twd_sk_site_caps( true );
	twd_sk_up_site();
	$l = TWD_SK_REST::get_site_upgrades( twd_sk_rest_req() );
	twd_sk_eq( 3, $l['total'] );
	twd_sk_eq( 2, count( $l['pages'] ) );
	$e = TWD_SK_REST::post_site_upgrades_apply( twd_sk_rest_req( array() ) );
	twd_sk_is_error( 'twd_sk_confirm', $e );
	twd_sk_eq( 400, twd_sk_status( $e ) );
	twd_sk_eq( 3, TWD_SK_REST::get_site_upgrades( twd_sk_rest_req() )['total'], 'nothing changed without a yes' );
	// Someone who may edit page 61 only.
	$GLOBALS['twd_stub']['caps'] = array( 'edit_pages', 'manage_options', 'edit_post:61' );
	$o = TWD_SK_REST::post_site_upgrades_apply( twd_sk_rest_req( array( 'confirm' => true, 'pages' => array( 61, 63, 'x' ) ) ) );
	twd_sk_eq( array( 61 ), array_map( function ( $p ) {
		return $p['id'];
	}, $o['updated'] ) );
	twd_sk_eq( 2, $o['total'], 'page 63 still has its two places' );
	$GLOBALS['twd_stub']['caps'] = true;
	$all = TWD_SK_REST::post_site_upgrades_apply( twd_sk_rest_req( array( 'confirm' => '1' ) ) );
	twd_sk_eq( 0, $all['total'] );
	twd_sk_eq( array( 63 ), array_map( function ( $p ) {
		return $p['id'];
	}, $all['updated'] ) );
} );

twd_sk_test( 'upgrade: the launch check says so once, in plain words, and says nothing when every page is current', function () {
	twd_sk_up_site();
	$none = array_filter( TWD_SK_Launch::check()['items'], function ( $i ) {
		return 'upgrades' === $i['id'];
	} );
	twd_sk_eq( 1, count( $none ) );
	$item = array_values( $none )[0];
	twd_sk_eq( 'warn', $item['status'] );
	twd_sk_has( '2 pages can be brought up to date', $item['label'] );
	twd_sk_has( 'wp twd-sk upgrade --apply', $item['detail'] );
	TWD_SK_Upgrade::run();
	foreach ( TWD_SK_Launch::check()['items'] as $i ) {
		twd_sk_true( 'upgrades' !== $i['id'], 'no item once every page is current' );
	}
} );

twd_sk_test( 'upgrade: the Site tab has the section, built with DOM calls, asking before it changes anything; the class holds no network calls', function () {
	$js = file_get_contents( ABSPATH . 'assets/twd-site-kit-editor-site.js' );
	foreach ( array( "'/site/upgrades'", "'/site/upgrades/apply'", 'buildUpgradesSection(panel)', "'Bring pages up to date'", 'Every page is up to date.', 'ED.confirmInline(upui.list' ) as $needle ) {
		twd_sk_has( $needle, $js );
	}
	twd_sk_hasnt( 'innerHTML', $js );
	$src = file_get_contents( ABSPATH . 'includes/class-twd-sk-upgrade.php' );
	foreach ( array( 'wp_remote_', 'curl_', 'file_get_contents', 'eval(', 'update_post_meta', 'update_option' ) as $bad ) {
		twd_sk_hasnt( $bad, $src, $bad );
	}
	twd_sk_has( 'TWD_SK_Store::save(', $src, 'every write goes through the store' );
} );
