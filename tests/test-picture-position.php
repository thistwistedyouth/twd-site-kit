<?php
// Picture position and Slow zoom (12 Nov 2026, from the pilot site's banners): Look choices for the picture of a banner (the
// picture band, the hero with a picture, the Picture text layout, the call to action band). Position (Top, Middle, Bottom)
// beats a picture's own saved keep-in-view point for one section; the zoom is one slow CSS animation, off by default, with no
// script, stopped for visitors who ask for less motion. Nothing changes for a section that does not use them.

twd_sk_test( 'picture position: Normal, Top, Middle and Bottom on the band, the hero with a picture, the Picture text layout and the call to action band, only while the picture is there', function () {
	$expect = array( '', 'twd-sk-pos-top', 'twd-sk-pos-middle', 'twd-sk-pos-bottom' );
	foreach ( array( 'image_text' => 'twd-sk-image-text--band', 'hero' => 'twd-sk-hero--image', 'text' => 'twd-sk-text--picture', 'cta' => 'twd-sk-cta--band' ) as $component => $requires ) {
		$o = TWD_SK_Looks::options( $component );
		twd_sk_true( isset( $o['picture_position'] ), $component . ' has the choice' );
		twd_sk_eq( $expect, array_keys( $o['picture_position']['choices'] ), $component );
		twd_sk_eq( $requires, $o['picture_position']['requires'], $component . ' needs its picture layout' );
		twd_sk_eq( 'toggle', $o['slow_zoom']['kind'], $component . ' zoom is a switch' );
		twd_sk_eq( 'twd-sk-kenburns', $o['slow_zoom']['class'], $component );
		twd_sk_eq( $requires, $o['slow_zoom']['requires'], $component );
	}
	$band = array( 'twd-sk-image-text', 'twd-sk-image-text--band' );
	twd_sk_eq( array( 'twd-sk-image-text', 'twd-sk-image-text--band', 'twd-sk-pos-bottom' ), TWD_SK_Looks::classes_for( 'image_text', $band, null, array( 'picture_position' => 'twd-sk-pos-bottom' ) ) );
	twd_sk_eq( $band, TWD_SK_Looks::classes_for( 'image_text', array( 'twd-sk-image-text', 'twd-sk-image-text--band', 'twd-sk-pos-top' ), null, array( 'picture_position' => '' ) ), 'Normal takes the class away' );
	twd_sk_eq( array( 'twd-sk-image-text', 'twd-sk-tone-base' ), TWD_SK_Looks::classes_for( 'image_text', array( 'twd-sk-image-text', 'twd-sk-tone-base' ), null, array( 'picture_position' => 'twd-sk-pos-top' ) ), 'not on a layout with no cropped banner picture' );
	$hero = array( 'twd-sk-hero', 'twd-sk-hero--image' );
	twd_sk_eq( array( 'twd-sk-hero', 'twd-sk-hero--image', 'twd-sk-pos-middle' ), TWD_SK_Looks::classes_for( 'hero', $hero, null, array( 'picture_position' => 'twd-sk-pos-middle' ) ) );
	twd_sk_eq( array( 'twd-sk-hero', 'twd-sk-hero--split' ), TWD_SK_Looks::classes_for( 'hero', array( 'twd-sk-hero', 'twd-sk-hero--split' ), null, array( 'picture_position' => 'twd-sk-pos-top', 'slow_zoom' => true ) ), 'a hero with no picture behind it gets neither' );
	$text = array( 'twd-sk-text', 'twd-sk-text--picture' );
	twd_sk_eq( array( 'twd-sk-text', 'twd-sk-text--picture', 'twd-sk-kenburns' ), TWD_SK_Looks::classes_for( 'text', $text, null, array( 'slow_zoom' => true ) ) );
	twd_sk_eq( $text, TWD_SK_Looks::classes_for( 'text', array( 'twd-sk-text', 'twd-sk-text--picture', 'twd-sk-kenburns' ), null, array( 'slow_zoom' => false ) ), 'the switch off takes the class away' );
	twd_sk_eq( array( 'twd-sk-text', 'twd-sk-tone-base' ), TWD_SK_Looks::classes_for( 'text', array( 'twd-sk-text', 'twd-sk-tone-base' ), null, array( 'slow_zoom' => true ) ), 'a plain text section never zooms' );
	$cta = array( 'twd-sk-cta', 'twd-sk-cta--band' );
	twd_sk_eq( array( 'twd-sk-cta', 'twd-sk-cta--band', 'twd-sk-pos-top', 'twd-sk-kenburns' ), TWD_SK_Looks::classes_for( 'cta', $cta, null, array( 'picture_position' => 'twd-sk-pos-top', 'slow_zoom' => true ) ) );
} );

twd_sk_test( 'picture position: the four classes are shared registry classes, the sanitiser keeps them, and a value that is not on the list is refused', function () {
	$shared = TWD_SK_Registry::shared_classes();
	foreach ( array( 'twd-sk-pos-top', 'twd-sk-pos-middle', 'twd-sk-pos-bottom', 'twd-sk-kenburns' ) as $c ) {
		twd_sk_true( in_array( $c, $shared, true ), $c );
	}
	$html = '<section class="twd-sk-text twd-sk-text--picture twd-sk-pos-bottom twd-sk-kenburns"><img class="twd-sk-text__bg" src="/wp-content/uploads/your-image.jpg" alt="A road"><div class="twd-sk-text__overlay"></div><div class="twd-sk-inner"><h2 class="twd-sk-title">Heading</h2></div></section>';
	$out  = TWD_SK_Sanitizer::clean( $html );
	twd_sk_has( 'twd-sk-pos-bottom', $out );
	twd_sk_has( 'twd-sk-kenburns', $out );
	$text = array( 'twd-sk-text', 'twd-sk-text--picture' );
	$bad  = TWD_SK_Looks::classes_for( 'text', $text, null, array( 'picture_position' => 'twd-sk-pos-sideways' ) );
	twd_sk_true( is_wp_error( $bad ) || $bad === $text, 'an unknown choice adds nothing' );
} );

twd_sk_test( 'picture position and zoom css: position wins over a saved keep-in-view point on the four banner pictures; the zoom is one keyframe animation with no script, stopped for less motion and for the scrolling-picture hero', function () {
	$src  = file_get_contents( ABSPATH . 'tools/kit.src.css' );
	$tail = substr( $src, strpos( $src, '/* Picture position and Slow zoom (12 Nov 2026)' ) );
	foreach ( array( 'top' => 'center top', 'middle' => 'center center', 'bottom' => 'center bottom' ) as $name => $pos ) {
		foreach ( array( 'twd-sk-hero__bg', 'twd-sk-text__bg', 'twd-sk-cta__bg', 'twd-sk-image-text__img' ) as $pic ) {
			twd_sk_has( '.twd-sk-pos-' . $name . ' .' . $pic, $tail, $name . ' ' . $pic );
		}
		twd_sk_has( 'object-position: ' . $pos . ' !important;', $tail, $name . ' beats an inline keep-in-view point' );
	}
	twd_sk_has( '@keyframes twd-sk-kenburns', $tail );
	twd_sk_has( 'animation: twd-sk-kenburns 30s ease-in-out infinite alternate;', $tail );
	twd_sk_has( 'transform: scale(1.08)', $tail, 'a slow, small zoom' );
	twd_sk_has( '.twd-sk-image-text--band.twd-sk-kenburns { overflow: clip; }', $tail, 'a band is clipped only when it zooms' );
	twd_sk_has( '.twd-sk-hero--parallax.twd-sk-kenburns .twd-sk-hero__bg { animation: none; }', $tail, 'the scrolling-picture hero keeps its own movement' );
	$rm = substr( $tail, strpos( $tail, '@media (prefers-reduced-motion: reduce)' ) );
	twd_sk_has( 'animation: none;', $rm, 'stopped for visitors who ask for less motion' );
	// No script is added for it, and the stylesheet stays under its ceiling.
	$js = file_get_contents( ABSPATH . 'assets/twd-site-kit-inline.js' ) . file_get_contents( ABSPATH . 'assets/twd-site-kit-hero.js' );
	twd_sk_hasnt( 'kenburns', $js, 'no script for the zoom' );
	$css = file_get_contents( ABSPATH . 'assets/twd-site-kit.css' );
	twd_sk_has( '@keyframes twd-sk-kenburns', $css, 'in the built stylesheet' );
	twd_sk_has( 'object-position: center bottom !important', $css );
} );
