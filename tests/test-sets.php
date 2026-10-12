<?php
// Starter sets (0.8.4): data files, checked like a brief, used when the brief lists no pages.

twd_sk_test( 'sets: every bundled set is valid, suits a real site type, and holds no client wording', function () {
	TWD_SK_Sets::reset();
	twd_sk_eq( array(), TWD_SK_Sets::errors() );
	$all = TWD_SK_Sets::all();
	twd_sk_eq( array( 'coaching', 'counselling', 'mobile-hire', 'studio-classes', 'trades' ), array_keys( $all ) );
	foreach ( $all as $slug => $s ) {
		twd_sk_true( isset( TWD_SK_Mode::modes()[ $s['mode'] ] ), $slug . ' mode' );
		twd_sk_true( count( $s['pages'] ) >= 3, $slug . ' has pages' );
		$types = array_map( function ( $p ) {
			return $p['type'];
		}, $s['pages'] );
		twd_sk_eq( 'home', $types[0], $slug . ' starts with Home' );
		twd_sk_true( in_array( 'contact', $types, true ), $slug . ' has a contact page' );
		foreach ( $s['pages'] as $p ) {
			twd_sk_eq( 'hero', $p['parts'][0][0], $slug . ' ' . $p['title'] . ' starts with a hero' );
		}
		$raw = file_get_contents( ABSPATH . 'starters/sets/' . $slug . '.json' );
		twd_sk_hasnt( "\xE2\x80\x94", $raw );
		twd_sk_true( 1 !== preg_match( '/@|https?:|\d{5,}/', $raw ), $slug . ' holds no contact details' );
	}
	foreach ( array( 'mobile-hire', 'trades', 'studio-classes' ) as $slug ) {
		twd_sk_eq( 'business', $all[ $slug ]['mode'] );
	}
} );

twd_sk_test( 'sets: a bad file is left out and reported, never half used; another folder can be used', function () {
	$dir = sys_get_temp_dir() . '/twdsk-sets-' . getmypid();
	@mkdir( $dir );
	file_put_contents( $dir . '/good.json', json_encode( array( 'schema' => 'twd-starter-set/1', 'slug' => 'good', 'name' => 'Good', 'mode' => 'business', 'pages' => array( array( 'type' => 'home', 'title' => 'Home' ) ) ) ) );
	file_put_contents( $dir . '/wrongname.json', json_encode( array( 'schema' => 'twd-starter-set/1', 'slug' => 'other', 'mode' => 'business', 'pages' => array( array( 'type' => 'home' ) ) ) ) );
	file_put_contents( $dir . '/badpage.json', json_encode( array( 'schema' => 'twd-starter-set/1', 'slug' => 'badpage', 'mode' => 'business', 'pages' => array( array( 'type' => 'home' ), array( 'type' => 'about', 'sections' => array( 'text' ) ) ) ) ) );
	file_put_contents( $dir . '/badmode.json', json_encode( array( 'schema' => 'twd-starter-set/1', 'slug' => 'badmode', 'mode' => 'shop', 'pages' => array( array( 'type' => 'home' ) ) ) ) );
	file_put_contents( $dir . '/junk.json', 'not json' );
	add_filter( 'twd_sk_sets_dir', function () use ( $dir ) {
		return $dir;
	} );
	TWD_SK_Sets::reset();
	twd_sk_eq( array( 'good' ), array_keys( TWD_SK_Sets::all() ) );
	$e = implode( ' | ', TWD_SK_Sets::errors() );
	foreach ( array( 'wrongname.json: the slug must match', 'badpage.json: page 2: A page starts with a hero', 'badmode.json: unknown site type', 'junk.json: not a starter set file' ) as $needle ) {
		twd_sk_has( $needle, $e );
	}
	twd_sk_has( 'set files with problems', TWD_SK_Doctor::report() );
	foreach ( glob( $dir . '/*' ) as $f ) {
		unlink( $f );
	}
	rmdir( $dir );
	$GLOBALS['twd_stub']['hooks'] = array();
	TWD_SK_Sets::reset();
} );

twd_sk_test( 'sets in the build: chosen on the setup page, used when the brief lists no pages, and a brief or an edited list wins', function () {
	TWD_SK_Sets::reset();
	$r = TWD_SK_Build::save_draft( array( 'mode' => 'business', 'set' => 'mobile-hire' ) );
	twd_sk_eq( array(), $r['errors'] );
	$p = TWD_SK_Build::plan();
	twd_sk_eq( 'set', $p['source'] );
	twd_sk_eq( array( 'Home', 'Hire and prices', 'About us', 'Questions and answers', 'Book or ask' ), array_map( function ( $x ) {
		return $x['title'];
	}, $p['pages'] ) );
	twd_sk_eq( 'pricing', $p['pages'][1]['parts'][2][0] );
	TWD_SK_Build::save_draft( array( 'brief_text' => json_encode( array( 'schema' => 'twd-site-brief/1', 'pages' => array( array( 'type' => 'about', 'title' => 'Our story' ) ) ) ) ) );
	twd_sk_eq( 'brief', TWD_SK_Build::plan()['source'], 'the brief\'s pages win' );
	TWD_SK_Build::save_draft( array( 'brief_text' => '' ) );
	TWD_SK_Build::save_draft( array( 'pages' => array( array( 'type' => 'home', 'title' => 'Home' ) ) ) );
	twd_sk_eq( 'edited', TWD_SK_Build::plan()['source'], 'an edited list wins' );
	twd_sk_has( 'starting points on offer', implode( ' ', TWD_SK_Build::save_draft( array( 'set' => 'nope' ) )['errors'] ) );
	twd_sk_eq( 'mobile-hire', TWD_SK_Build::draft()['set'] );
	TWD_SK_Build::save_draft( array( 'set' => '' ) );
	twd_sk_eq( '', TWD_SK_Build::draft()['set'] );
	twd_sk_site_caps();
	$s = TWD_SK_REST::get_site_build( twd_sk_rest_req() );
	twd_sk_eq( 'business', $s['sets']['trades']['mode'] );
	twd_sk_true( in_array( 'Get a quote', $s['sets']['trades']['pages'], true ) );
	$js = file_get_contents( ABSPATH . 'assets/twd-site-kit-setup.js' );
	twd_sk_has( "set: ui.set ? ui.set.value : ''", $js );
	twd_sk_has( 'sets[slug].mode === mode', $js );
} );

twd_sk_test( 'sets cli: wp twd-sk build --set uses the set; an unknown set stops before anything is built', function () {
	require_once __DIR__ . '/stub-wpcli.php';
	TWD_SK_Sets::reset();
	$GLOBALS['twd_stub']['caps'] = true;
	$cli = new TWD_SK_CLI();
	WP_CLI::reset();
	$cli->build( array(), array( 'mode' => 'business', 'set' => 'trades', 'dry-run' => true ) );
	twd_sk_has( 'Make the "Get a quote" page', WP_CLI::all() );
	twd_sk_has( 'Fix the type, starting point or style first', twd_sk_cli_fails( function () use ( $cli ) {
		$cli->build( array(), array( 'set' => 'nope' ) );
	} ) );
} );
