<?php
// The one-h1 rule: a page has exactly one h1, the first hero title.

function twd_sk_hero_with( $heading_html ) {
	return '<section class="twd-sk-hero twd-sk-hero--compact"><div class="twd-sk-inner">' . $heading_html . '</div></section>';
}

twd_sk_test( 'h1: a hero title h1 inside the hero is kept', function () {
	$in = twd_sk_hero_with( '<h1 class="twd-sk-hero__title">Page heading</h1>' );
	$r  = TWD_SK_Sanitizer::clean_with_report( $in );
	twd_sk_eq( $in, $r['html'] );
	twd_sk_eq( 0, $r['report']['total'] );
} );

twd_sk_test( 'h1: the hero title is kept in every hero variant', function () {
	foreach ( TWD_SK_Registry::get( 'hero' )['variants'] as $class => $variant ) {
		$out = TWD_SK_Sanitizer::clean( $variant['skeleton'] );
		twd_sk_has( '<h1 class="twd-sk-hero__title">', $out, $class );
	}
} );

twd_sk_test( 'h1: an h1 outside the hero is demoted to h2', function () {
	$out = TWD_SK_Sanitizer::clean( '<section class="twd-sk-text"><h1 class="twd-sk-hero__title">Nope</h1></section>' );
	twd_sk_has( '<h2 class="twd-sk-hero__title">Nope</h2>', $out );
	twd_sk_hasnt( '<h1', $out );
} );

twd_sk_test( 'h1: an h1 inside the hero without the hero title class is demoted', function () {
	$out = TWD_SK_Sanitizer::clean( twd_sk_hero_with( '<h1 class="twd-sk-title">Heading</h1>' ) );
	twd_sk_has( '<h2 class="twd-sk-title">Heading</h2>', $out );
	twd_sk_hasnt( '<h1', $out );
	$out = TWD_SK_Sanitizer::clean( twd_sk_hero_with( '<h1>Heading</h1>' ) );
	twd_sk_hasnt( '<h1', $out );
} );

twd_sk_test( 'h1: a made-up class on the h1 cannot earn it the hero title status', function () {
	$out = TWD_SK_Sanitizer::clean( twd_sk_hero_with( '<h1 class="hero-title twd-sk-hero__title-fake">Heading</h1>' ) );
	twd_sk_hasnt( '<h1', $out );
} );

twd_sk_test( 'h1: a hero-looking element that is not a registry class does not count as the hero', function () {
	$out = TWD_SK_Sanitizer::clean( '<section class="twd-sk-hero-fake"><h1 class="twd-sk-hero__title">Heading</h1></section>' );
	twd_sk_hasnt( '<h1', $out );
} );

twd_sk_test( 'h1: only the first hero title survives, a second hero h1 becomes h2 and is reported', function () {
	$hero = twd_sk_hero_with( '<h1 class="twd-sk-hero__title">Heading</h1>' );
	$r    = TWD_SK_Sanitizer::clean_with_report( $hero . $hero );
	twd_sk_eq( 1, substr_count( $r['html'], '<h1' ) );
	twd_sk_eq( 1, substr_count( $r['html'], '<h2 class="twd-sk-hero__title">' ) );
	twd_sk_eq( array( 'h1 (demoted to h2)' ), $r['report']['removed']['tags'] );
} );

twd_sk_test( 'h1: an invalid h1 earlier in the page does not use up the one allowed h1', function () {
	$in  = '<section class="twd-sk-text"><h1>Stray</h1></section>' . twd_sk_hero_with( '<h1 class="twd-sk-hero__title">Real</h1>' );
	$out = TWD_SK_Sanitizer::clean( $in );
	twd_sk_eq( 1, substr_count( $out, '<h1' ) );
	twd_sk_has( '<h1 class="twd-sk-hero__title">Real</h1>', $out );
} );

twd_sk_test( 'h1: the allowance resets for every clean() call, nothing leaks between pages', function () {
	$in = twd_sk_hero_with( '<h1 class="twd-sk-hero__title">Heading</h1>' );
	twd_sk_has( '<h1', TWD_SK_Sanitizer::clean( $in ) );
	twd_sk_has( '<h1', TWD_SK_Sanitizer::clean( $in ) );
	twd_sk_has( '<h1', TWD_SK_Sanitizer::clean( $in ) );
} );

twd_sk_test( 'h1: a hostile hero h1 still has its bad attributes and links stripped', function () {
	$out = TWD_SK_Sanitizer::clean( twd_sk_hero_with( '<h1 class="twd-sk-hero__title" onclick="x()" style="color:red" id="bad id">Heading <a href="javascript:alert(1)">x</a></h1>' ) );
	twd_sk_has( '<h1 class="twd-sk-hero__title">', $out );
	twd_sk_hasnt( 'onclick', $out );
	twd_sk_hasnt( 'style', $out );
	twd_sk_hasnt( 'javascript', $out );
} );

twd_sk_test( 'h1: an h1 nested deeper inside the hero is still accepted', function () {
	$in = '<section class="twd-sk-hero twd-sk-hero--split"><div class="twd-sk-inner"><div class="twd-sk-hero__grid"><div class="twd-sk-hero__text"><h1 class="twd-sk-hero__title">Deep</h1></div></div></div></section>';
	twd_sk_has( '<h1 class="twd-sk-hero__title">Deep</h1>', TWD_SK_Sanitizer::clean( $in ) );
} );

twd_sk_test( 'h1: an h1 inside an unknown wrapper tag inside the hero is still accepted (the wrapper is unwrapped)', function () {
	$in = '<section class="twd-sk-hero"><center><h1 class="twd-sk-hero__title">Heading</h1></center></section>';
	twd_sk_has( '<h1 class="twd-sk-hero__title">', TWD_SK_Sanitizer::clean( $in ) );
} );

twd_sk_test( 'h1: cleaning is idempotent with a kept h1', function () {
	$once = TWD_SK_Sanitizer::clean( TWD_SK_Registry::get( 'hero' )['skeleton'] . TWD_SK_Registry::get( 'text' )['skeleton'] );
	twd_sk_eq( $once, TWD_SK_Sanitizer::clean( $once ) );
} );

twd_sk_test( 'h1: a whole realistic page keeps exactly one h1', function () {
	$page = '';
	foreach ( TWD_SK_Registry::all() as $c ) {
		$page .= $c['skeleton'] . "\n";
	}
	$out = TWD_SK_Sanitizer::clean( $page );
	twd_sk_eq( 1, substr_count( $out, '<h1' ) );
	twd_sk_eq( 0, TWD_SK_Sanitizer::clean_with_report( $page )['report']['total'] );
} );
