<?php
// WCAG contrast maths and the token checks behind the Site tab.

twd_sk_test( 'contrast: known ratios (black on white 21, white on white 1, a mid grey)', function () {
	twd_sk_eq( 21.0, round( TWD_SK_Contrast::ratio( '#000000', '#FFFFFF' ), 2 ) );
	twd_sk_eq( 1.0, round( TWD_SK_Contrast::ratio( '#FFFFFF', '#ffffff' ), 2 ) );
	twd_sk_eq( 4.48, round( TWD_SK_Contrast::ratio( '#777777', '#FFFFFF' ), 2 ) );
	twd_sk_eq( TWD_SK_Contrast::ratio( '#112233', '#FFEEDD' ), TWD_SK_Contrast::ratio( '#FFEEDD', '#112233' ), 'order does not matter' );
	twd_sk_eq( TWD_SK_Contrast::ratio( '#abc', '#fff' ), TWD_SK_Contrast::ratio( '#AABBCC', '#FFFFFF' ), 'three-digit hex' );
	twd_sk_eq( TWD_SK_Contrast::ratio( 'rgb(0, 0, 0)', '#fff' ), TWD_SK_Contrast::ratio( '#000', '#fff' ), 'rgb()' );
} );

twd_sk_test( 'contrast: transparency and odd forms cannot be judged and give null', function () {
	foreach ( array( 'transparent', 'rgba(0,0,0,0.5)', '#12345678', 'red', '', null, array(), 'var(--x)' ) as $v ) {
		twd_sk_eq( null, TWD_SK_Contrast::parse( $v ), json_encode( $v ) );
	}
	twd_sk_eq( null, TWD_SK_Contrast::ratio( '#000', 'rgba(0,0,0,.5)' ) );
} );

twd_sk_test( 'contrast: both shipped packs have no blocking pair and no warning', function () {
	foreach ( TWD_SK_Packs::packs() as $slug => $pack ) {
		$c = TWD_SK_Contrast::check( $pack['tokens'] );
		twd_sk_eq( array(), $c['blocking'], $slug . ' blocking' );
		twd_sk_eq( array(), $c['warnings'], $slug . ' warnings' );
	}
} );

twd_sk_test( 'contrast: the pairs list marks the button and band pairs as blocking and the rest as warnings', function () {
	$levels = array();
	foreach ( TWD_SK_Contrast::pairs() as $p ) {
		$levels[ $p[0] ] = $p[1];
	}
	foreach ( array( 'button_text', 'button_hover', 'light_button', 'tint_button', 'band_text', 'band_accent' ) as $id ) {
		twd_sk_eq( 'block', $levels[ $id ], $id );
	}
	foreach ( array( 'body_bg', 'body_surface', 'muted_surface', 'heading_bg', 'heading_surface', 'eyebrow_bg', 'accent_text_bg', 'title_tint', 'primary_bg' ) as $id ) {
		twd_sk_eq( 'warn', $levels[ $id ], $id );
	}
} );

twd_sk_test( 'contrast: every colour token a pair uses can be edited, and every pair token exists in the packs', function () {
	$editable = TWD_SK_Site::editable_names();
	$tokens   = TWD_SK_Packs::packs()['sage']['tokens'];
	foreach ( TWD_SK_Contrast::pairs() as $p ) {
		twd_sk_true( isset( $tokens[ $p[3] ], $tokens[ $p[4] ] ), $p[0] . ' tokens exist' );
		twd_sk_true( in_array( $p[3], $editable, true ) || in_array( $p[4], $editable, true ), $p[0] . ' can be fixed from the Site tab' );
	}
} );

twd_sk_test( 'contrast: unchecked colours are listed, not blocked', function () {
	$tokens = TWD_SK_Packs::packs()['sage']['tokens'];
	$tokens['color-band'] = 'rgba(10,20,30,0.5)';
	$c = TWD_SK_Contrast::check( $tokens );
	twd_sk_eq( array(), $c['blocking'] );
	$ids = array_map( function ( $i ) {
		return $i['id'];
	}, $c['unchecked'] );
	twd_sk_true( in_array( 'band_text', $ids, true ) );
} );

twd_sk_test( 'contrast: the browser script computes the same ratios (skipped when node is not installed)', function () {
	if ( ! function_exists( 'shell_exec' ) || '' === trim( (string) shell_exec( 'command -v node 2>/dev/null' ) ) || ! file_exists( ABSPATH . 'assets/twd-site-kit-editor-site.js' ) ) {
		return;
	}
	$pairs = array( array( '#000000', '#FFFFFF' ), array( '#777777', '#FFFFFF' ), array( '#5F6F54', '#FFFFFF' ), array( '#7A5C1E', '#FFFFFF' ), array( '#112233', '#FFEEDD' ), array( '#abc', '#123' ) );
	$js    = 'const fs=require("fs");const src=fs.readFileSync(' . json_encode( ABSPATH . 'assets/twd-site-kit-editor-site.js' ) . ',"utf8");'
		. 'const m=src.match(/\\/\\* contrast:start \\*\\/([\\s\\S]*?)\\/\\* contrast:end \\*\\//);if(!m){console.log("NOFN");process.exit(0);}'
		. 'const f=new Function(m[1]+";return contrastRatio;")();'
		. 'console.log(JSON.stringify(' . json_encode( $pairs ) . '.map(p=>Math.round(f(p[0],p[1])*100)/100)));';
	$out = trim( (string) shell_exec( 'node -e ' . escapeshellarg( $js ) . ' 2>&1' ) );
	$got = json_decode( $out, true );
	twd_sk_true( is_array( $got ), 'script output: ' . $out );
	foreach ( $pairs as $i => $p ) {
		twd_sk_true( abs( round( TWD_SK_Contrast::ratio( $p[0], $p[1] ), 2 ) - $got[ $i ] ) < 0.001, $p[0] . ' on ' . $p[1] . ': php ' . round( TWD_SK_Contrast::ratio( $p[0], $p[1] ), 2 ) . ' js ' . $got[ $i ] );
	}
} );
