<?php
// Section heights and the preview buttons (12 Nov 2026, from the pilot site's banners): two taller steps for a picture band,
// a Height choice (Tall, Taller) for the hero with a picture and the Picture text layout, the heights as tokens in every pack
// and in the Site tab, wide screens a little taller by media query, and Desktop, Tablet and Phone preview buttons with the
// small frame hidden until asked for. Nothing changes for a section that does not use them.

function twd_sk_hg_css() {
	return file_get_contents( ABSPATH . 'assets/twd-site-kit.css' );
}
function twd_sk_hg_js() {
	return str_replace( "\r", '', file_get_contents( ABSPATH . 'assets/twd-site-kit-inline.js' ) );
}

twd_sk_test( 'heights: the picture band gains Taller and Tallest beside Normal, Tall and Ribbon, only on the band layout, and Normal is still no class', function () {
	$o = TWD_SK_Looks::options( 'image_text' );
	twd_sk_eq( array( '', 'twd-sk-image-text--band-tall', 'twd-sk-image-text--band-taller', 'twd-sk-image-text--band-tallest', 'twd-sk-image-text--band-ribbon' ), array_keys( $o['band_height']['choices'] ) );
	$band = array( 'twd-sk-image-text', 'twd-sk-image-text--band' );
	twd_sk_eq( array( 'twd-sk-image-text', 'twd-sk-image-text--band', 'twd-sk-image-text--band-taller' ), TWD_SK_Looks::classes_for( 'image_text', $band, null, array( 'band_height' => 'twd-sk-image-text--band-taller' ) ) );
	twd_sk_eq( array( 'twd-sk-image-text', 'twd-sk-image-text--band', 'twd-sk-image-text--band-tallest' ), TWD_SK_Looks::classes_for( 'image_text', $band, null, array( 'band_height' => 'twd-sk-image-text--band-tallest' ) ) );
	twd_sk_eq( $band, TWD_SK_Looks::classes_for( 'image_text', array( 'twd-sk-image-text', 'twd-sk-image-text--band', 'twd-sk-image-text--band-taller' ), null, array( 'band_height' => '' ) ), 'Normal takes the class away' );
	twd_sk_eq( array( 'twd-sk-image-text', 'twd-sk-tone-base' ), TWD_SK_Looks::classes_for( 'image_text', array( 'twd-sk-image-text', 'twd-sk-tone-base' ), null, array( 'band_height' => 'twd-sk-image-text--band-taller' ) ), 'not on the other layouts' );
	$html = '<section class="twd-sk-image-text twd-sk-image-text--band twd-sk-image-text--band-tallest"><img class="twd-sk-image-text__img" src="/wp-content/uploads/your-image.jpg" alt="A road"></section>';
	twd_sk_has( 'twd-sk-image-text--band-tallest', TWD_SK_Sanitizer::clean( $html ), 'the sanitiser keeps the class' );
} );

twd_sk_test( 'heights: the hero with a picture and the Picture text layout get a Height choice (Tall, Taller) from shared classes, and only while the picture is there', function () {
	$h = TWD_SK_Looks::options( 'hero' );
	twd_sk_eq( array( '', 'twd-sk-cover-tall', 'twd-sk-cover-taller' ), array_keys( $h['cover_height']['choices'] ) );
	twd_sk_eq( 'twd-sk-hero--image', $h['cover_height']['requires'] );
	$t = TWD_SK_Looks::options( 'text' );
	twd_sk_eq( 'twd-sk-text--picture', $t['cover_height']['requires'] );
	$hero = array( 'twd-sk-hero', 'twd-sk-hero--image' );
	twd_sk_eq( array( 'twd-sk-hero', 'twd-sk-hero--image', 'twd-sk-cover-taller' ), TWD_SK_Looks::classes_for( 'hero', $hero, null, array( 'cover_height' => 'twd-sk-cover-taller' ) ) );
	twd_sk_eq( array( 'twd-sk-hero', 'twd-sk-hero--split' ), TWD_SK_Looks::classes_for( 'hero', array( 'twd-sk-hero', 'twd-sk-hero--split' ), null, array( 'cover_height' => 'twd-sk-cover-tall' ) ), 'a hero with no picture behind it gets nothing' );
	$text = array( 'twd-sk-text', 'twd-sk-text--picture' );
	twd_sk_eq( array( 'twd-sk-text', 'twd-sk-text--picture', 'twd-sk-cover-tall' ), TWD_SK_Looks::classes_for( 'text', $text, null, array( 'cover_height' => 'twd-sk-cover-tall' ) ) );
	twd_sk_eq( array( 'twd-sk-text', 'twd-sk-tone-base' ), TWD_SK_Looks::classes_for( 'text', array( 'twd-sk-text', 'twd-sk-tone-base' ), null, array( 'cover_height' => 'twd-sk-cover-tall' ) ), 'a plain text section gets nothing' );
	twd_sk_true( in_array( 'twd-sk-cover-tall', TWD_SK_Registry::shared_classes(), true ) && in_array( 'twd-sk-cover-taller', TWD_SK_Registry::shared_classes(), true ) );
	twd_sk_has( 'twd-sk-cover-taller', TWD_SK_Sanitizer::clean( '<section class="twd-sk-text twd-sk-text--picture twd-sk-cover-taller"><div class="twd-sk-inner"><h2 class="twd-sk-title">Heading</h2></div></section>' ) );
} );

twd_sk_test( 'heights: every pack defines the eight new tokens, the Site tab lists them, and the older band tokens are unchanged', function () {
	$names = array( 'band-height-taller', 'band-height-taller-sm', 'band-height-tallest', 'band-height-tallest-sm', 'cover-min-height-tall', 'cover-min-height-tall-sm', 'cover-min-height-taller', 'cover-min-height-taller-sm' );
	foreach ( TWD_SK_Packs::packs() as $slug => $pack ) {
		foreach ( $names as $n ) {
			twd_sk_true( isset( $pack['tokens'][ $n ] ) && 1 === preg_match( '/^\d+px$/', $pack['tokens'][ $n ] ), $slug . ' ' . $n );
		}
		twd_sk_eq( '260px', $pack['tokens']['band-height'], $slug . ' band-height as before' );
		twd_sk_eq( '400px', $pack['tokens']['band-height-tall'], $slug . ' band-height-tall as before' );
		twd_sk_eq( '140px', $pack['tokens']['band-height-ribbon'], $slug . ' band-height-ribbon as before' );
		twd_sk_true( isset( $pack['tokens']['hero-min-height'], $pack['tokens']['hero-min-height-sm'] ), $slug . ' still has its own hero heights' );
	}
	foreach ( $names as $n ) {
		twd_sk_true( in_array( $n, TWD_SK_Site::editable_names(), true ), 'editable: ' . $n );
	}
	// The steps climb: tall below taller below tallest, and a phone is always below its computer.
	$p = TWD_SK_Packs::packs();
	$t = $p['sage']['tokens'];
	twd_sk_true( (int) $t['band-height-tall'] < (int) $t['band-height-taller'] && (int) $t['band-height-taller'] < (int) $t['band-height-tallest'] );
	twd_sk_true( (int) $t['cover-min-height-tall'] < (int) $t['cover-min-height-taller'] );
	twd_sk_true( (int) $t['band-height-taller-sm'] < (int) $t['band-height-taller'] && (int) $t['cover-min-height-taller-sm'] < (int) $t['cover-min-height-taller'] );
} );

twd_sk_test( 'heights: the rules come from tokens, only for the new classes, with phone and wide-screen steps and no viewport units', function () {
	$src = file_get_contents( ABSPATH . 'tools/kit.src.css' );
	foreach ( array( 'twd-sk-image-text--band-taller', 'twd-sk-image-text--band-tallest', 'twd-sk-cover-tall', 'twd-sk-cover-taller' ) as $c ) {
		twd_sk_has( '.' . $c . ' {', $src, $c . ' has a rule in the source' );
	}
	$tail = substr( $src, strpos( $src, '/* Heights (12 Nov 2026)' ) );
	twd_sk_has( '@media (max-width: 768px)', $tail, 'a phone step' );
	twd_sk_has( '@media (min-width: 1600px)', $tail, 'a wide screen step' );
	twd_sk_has( '@media (min-width: 2000px)', $tail, 'a very wide screen step' );
	twd_sk_has( '$band-height-taller-sm', $tail );
	twd_sk_has( '$cover-min-height-taller-sm', $tail );
	twd_sk_hasnt( 'vw', preg_replace( '/Media queries.*?never viewport units\./s', '', $tail ), 'no viewport units' );
	// The old rules are untouched: the band and the hero keep their own tokens.
	twd_sk_has( '.twd-sk-image-text--band { height: $band-height; }', $src );
	twd_sk_has( '.twd-sk-hero--image {', $src );
	// And the built stylesheet carries them (checked when the stylesheet has been rebuilt from the source).
	$css = twd_sk_hg_css();
	twd_sk_has( 'var(--twd-site-band-height-tallest)', $css );
	twd_sk_has( 'var(--twd-site-cover-min-height-taller-sm)', $css );
	twd_sk_has( 'calc(var(--twd-site-cover-min-height-tall) * 1.4)', $css );
} );

twd_sk_test( 'preview buttons: Desktop, Tablet and Phone open the full preview in a window, and the small frame inside the panel waits behind Small preview here', function () {
	$js = twd_sk_hg_js();
	$fn = substr( $js, strpos( $js, 'function sizedButtons(' ) );
	$fn = substr( $fn, 0, strpos( $fn, "\n\t}\n" ) );
	twd_sk_has( "openSized(getUrl(), 'Desktop', 1280, 900)", $fn );
	twd_sk_has( "openSized(getUrl(), 'Tablet', 768, 1024)", $fn );
	twd_sk_has( "openSized(getUrl(), 'Phone', 390, 844)", $fn );
	twd_sk_has( "'See the preview as:'", $fn );
	twd_sk_has( "toolButton('Small preview here'", $fn );
	twd_sk_has( 'show(frame, frame.hasAttribute(\'hidden\'));', $fn, 'the toggle shows and hides the frame' );
	// Each of the three panels (Replace, Describe a change, Import) hands its own frame to the buttons, and none shows it by itself.
	foreach ( array( 'rep' => 'S.rep ? S.rep.url', 'ad' => 'S.ad ? S.ad.url', 'imp' => 'S.imp ? S.imp.url' ) as $k => $needle ) {
		twd_sk_has( "ui.{$k}Sizes = sizedButtons(function () {\n\t\t\treturn {$needle} : '';\n\t\t}, ui.{$k}Frame);", $js, $k . ' panel' );
		twd_sk_hasnt( "show(ui.{$k}Frame, true)", $js, $k . ' frame is not shown automatically' );
		twd_sk_has( "show(ui.{$k}Sizes, true)", $js, $k . ' buttons appear with the preview' );
	}
} );
