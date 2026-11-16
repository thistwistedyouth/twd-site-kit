<?php
// Picture band Shape and Boxed (12 Nov 2026, from the pilot site's review): a band's height was a fixed number of pixels, so a wide
// screen cropped the picture far more than a phone ("perfect on phone, too zoomed on desktop"). Shape lets the height follow the width
// (the picture's own shape, 16:9 or 2:1, never taller than 80 percent of the screen); Boxed puts the picture in the page column with
// rounded corners. Both are Look choices on the picture band; no class is exactly as built.

twd_sk_test( 'band shape and boxed: Look offers Shape (fixed height, match the picture, wide, cinema) and Boxed or full width, on the band layout only', function () {
	$o = TWD_SK_Looks::options( 'image_text' );
	twd_sk_eq( array( '', 'twd-sk-shape-natural', 'twd-sk-shape-wide', 'twd-sk-shape-cinema', 'twd-sk-shape-whole' ), array_keys( $o['band_shape']['choices'] ) );
	twd_sk_eq( array( '', 'twd-sk-band-boxed', 'twd-sk-band-boxed-narrow' ), array_keys( $o['band_frame']['choices'] ) );
	twd_sk_eq( 'twd-sk-image-text--band', $o['band_shape']['requires'] );
	twd_sk_eq( 'twd-sk-image-text--band', $o['band_frame']['requires'] );
	twd_sk_eq( 'Fixed height', $o['band_shape']['choices'][''] );
	twd_sk_eq( 'Full width', $o['band_frame']['choices'][''] );
	$band = array( 'twd-sk-image-text', 'twd-sk-image-text--band' );
	twd_sk_eq( array( 'twd-sk-image-text', 'twd-sk-image-text--band', 'twd-sk-shape-wide' ), TWD_SK_Looks::classes_for( 'image_text', $band, null, array( 'band_shape' => 'twd-sk-shape-wide' ) ) );
	twd_sk_eq( array( 'twd-sk-image-text', 'twd-sk-image-text--band', 'twd-sk-band-boxed-narrow' ), TWD_SK_Looks::classes_for( 'image_text', $band, null, array( 'band_frame' => 'twd-sk-band-boxed-narrow' ) ) );
	twd_sk_eq( $band, TWD_SK_Looks::classes_for( 'image_text', array_merge( $band, array( 'twd-sk-shape-cinema', 'twd-sk-band-boxed' ) ), null, array( 'band_shape' => '', 'band_frame' => '' ) ), 'fixed height and full width take both classes away' );
	twd_sk_eq( array( 'twd-sk-image-text', 'twd-sk-tone-base' ), TWD_SK_Looks::classes_for( 'image_text', array( 'twd-sk-image-text', 'twd-sk-tone-base' ), null, array( 'band_shape' => 'twd-sk-shape-wide', 'band_frame' => 'twd-sk-band-boxed' ) ), 'not on the other picture and text layouts' );
	$both = TWD_SK_Looks::classes_for( 'image_text', $band, null, array( 'band_shape' => 'twd-sk-shape-natural', 'band_frame' => 'twd-sk-band-boxed' ) );
	twd_sk_true( in_array( 'twd-sk-shape-natural', $both, true ) && in_array( 'twd-sk-band-boxed', $both, true ), 'the two can be used together' );
} );

twd_sk_test( 'band shape and boxed: five shared registry classes that the sanitiser keeps', function () {
	$shared = TWD_SK_Registry::shared_classes();
	foreach ( array( 'twd-sk-shape-natural', 'twd-sk-shape-wide', 'twd-sk-shape-cinema', 'twd-sk-band-boxed', 'twd-sk-band-boxed-narrow' ) as $c ) {
		twd_sk_true( in_array( $c, $shared, true ), $c );
	}
	$html = '<section class="twd-sk-image-text twd-sk-image-text--band twd-sk-shape-wide twd-sk-band-boxed"><img class="twd-sk-image-text__img" src="/wp-content/uploads/your-image.jpg" alt="A road"></section>';
	$out  = TWD_SK_Sanitizer::clean( $html );
	twd_sk_has( 'twd-sk-shape-wide', $out );
	twd_sk_has( 'twd-sk-band-boxed', $out );
} );

twd_sk_test( 'band shape and boxed css: the height follows the width, capped at 80 percent of the screen, boxed uses the container tokens and rounded corners, no viewport-width units, and the old band rules are untouched', function () {
	$src  = file_get_contents( ABSPATH . 'tools/kit.src.css' );
	$tail = substr( $src, strpos( $src, '/* Picture band shape and boxed (12 Nov 2026)' ) );
	foreach ( array( 'twd-sk-shape-natural', 'twd-sk-shape-wide', 'twd-sk-shape-cinema', 'twd-sk-band-boxed', 'twd-sk-band-boxed-narrow' ) as $c ) {
		twd_sk_has( '.' . $c . '.twd-sk-image-text--band', $tail, $c . ' frees the section from its fixed height' );
		twd_sk_has( '.' . $c . ' .twd-sk-image-text__img', $tail, $c . ' acts on the picture' );
	}
	twd_sk_has( '{ height: auto; max-height: 80vh; }', $tail, 'the picture is as tall as its shape needs, never over 80 percent of the screen' );
	twd_sk_has( '.twd-sk-shape-wide .twd-sk-image-text__img { aspect-ratio: 16 / 9; width: 100%; }', $tail, 'a shape fills the width of its box' );
	twd_sk_has( '.twd-sk-shape-cinema .twd-sk-image-text__img { aspect-ratio: 2 / 1; width: 100%; }', $tail );
	twd_sk_has( 'padding: $gap-md $gutter;', $tail, 'boxed has gutters from the tokens' );
	twd_sk_has( '.twd-sk-band-boxed .twd-sk-image-text__img { max-width: min(100%, $container); }', $tail, 'boxed is at most the container, and never wider than the screen' );
	twd_sk_has( '{ width: auto; display: block; margin: 0 auto;', $tail, 'a boxed picture keeps its own shape and centres' );
	twd_sk_has( '.twd-sk-band-boxed-narrow .twd-sk-image-text__img { max-width: min(100%, $container-narrow); }', $tail );
	twd_sk_has( 'border-radius: $radius-image; animation: none; }', $tail, 'rounded corners, and the slow zoom is off for a boxed picture' );
	twd_sk_hasnt( 'vw', $tail, 'no viewport-width units' );
	twd_sk_true( strpos( $tail, '.twd-sk-band-boxed' ) > 0 && strpos( $src, $tail ) > strpos( $src, '.twd-sk-image-text--band-ribbon { height: $band-height-ribbon; }' ), 'after the band height rules' );
	twd_sk_has( '.twd-sk-image-text--band { height: $band-height; }', $src, 'the band height rule is as it was' );
	$css = file_get_contents( ABSPATH . 'assets/twd-site-kit.css' );
	twd_sk_has( 'aspect-ratio: 16 / 9', $css );
	twd_sk_has( 'max-height: 80vh', $css );
	twd_sk_has( 'max-width: min(100%, var(--twd-site-container-narrow))', $css );
} );
