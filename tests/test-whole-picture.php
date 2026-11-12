<?php
// Whole picture on phones and sharper wide pictures (12 Nov 2026, from the pilot site): a banner's fixed height cropped the sides of
// a wide picture on a phone, and a picture saved at a resized address (name-1024x430.jpg, which the media window stores) had no list
// of sizes so a wide screen was sent that one small copy. A Look toggle shows the whole picture on phones; a full-width band now
// lets the browser choose from the picture's larger copies; a resized address is matched to its library picture for the list.

// A stand-in for the WordPress function that builds the sizes list. It answers only while the test switches it on, so the other
// tests (which ran without it) are not changed.
if ( ! function_exists( 'wp_calculate_image_srcset' ) ) {
	function wp_calculate_image_srcset( $size, $src, $meta, $id = 0 ) {
		if ( empty( $GLOBALS['twd_stub']['srcset_on'] ) ) {
			return false;
		}
		$base = 'https://example.test/wp-content/uploads/2026/11/';
		return $base . 'sofa-e1-1024x430.jpg 1024w, ' . $base . 'sofa-e1-1536x645.jpg 1536w, ' . $base . 'sofa-e1.jpg 2000w';
	}
}

function twd_sk_wp_band( $extra = '', $src = 'https://example.test/wp-content/uploads/2026/11/sofa-e1-1024x430.jpg' ) {
	return '<section class="twd-sk-image-text twd-sk-image-text--band' . $extra . '"><img class="twd-sk-image-text__img" src="' . $src . '" alt="A sofa on a road"></section>';
}

twd_sk_test( 'whole picture on phones: a Look toggle on the picture band only, a shared class the sanitiser keeps, and changing other choices keeps it', function () {
	$o = TWD_SK_Looks::options( 'image_text' );
	twd_sk_true( isset( $o['phone_whole'] ) );
	twd_sk_eq( 'toggle', $o['phone_whole']['kind'] );
	twd_sk_eq( 'twd-sk-phone-whole', $o['phone_whole']['class'] );
	twd_sk_eq( 'twd-sk-image-text--band', $o['phone_whole']['requires'] );
	twd_sk_true( in_array( 'twd-sk-phone-whole', TWD_SK_Registry::shared_classes(), true ) );
	$band = array( 'twd-sk-image-text', 'twd-sk-image-text--band' );
	$on   = TWD_SK_Looks::classes_for( 'image_text', $band, null, array( 'phone_whole' => true ) );
	twd_sk_true( in_array( 'twd-sk-phone-whole', $on, true ), 'switched on' );
	$more = TWD_SK_Looks::classes_for( 'image_text', $on, null, array( 'band_shape' => 'twd-sk-shape-wide' ) );
	twd_sk_true( in_array( 'twd-sk-phone-whole', $more, true ) && in_array( 'twd-sk-shape-wide', $more, true ), 'other choices keep it' );
	twd_sk_true( ! in_array( 'twd-sk-phone-whole', TWD_SK_Looks::classes_for( 'image_text', $on, null, array( 'phone_whole' => false ) ), true ), 'switched off' );
	$plain = array( 'twd-sk-image-text', 'twd-sk-tone-base' );
	twd_sk_eq( $plain, TWD_SK_Looks::classes_for( 'image_text', $plain, null, array( 'phone_whole' => true ) ), 'not on the other picture and text layouts' );
	$html = twd_sk_wp_band( ' twd-sk-phone-whole' );
	twd_sk_has( 'twd-sk-phone-whole', TWD_SK_Sanitizer::clean( $html ) );
} );

twd_sk_test( 'whole picture on phones css: only on a phone, the band takes the picture\'s own shape, nothing changes above the phone width', function () {
	$src  = file_get_contents( ABSPATH . 'tools/kit.src.css' );
	$at   = strpos( $src, '/* Picture band, whole picture on phones (12 Nov 2026)' );
	twd_sk_true( $at > strpos( $src, '/* Picture band phone picture (12 Nov 2026)' ), 'after the other band rules, so it wins' );
	$tail = substr( $src, $at );
	twd_sk_has( "@media (max-width: 768px) {\n  .twd-sk-phone-whole.twd-sk-image-text--band { height: auto; }", str_replace( "\r", '', $tail ), 'the section loses its set height, on a phone only' );
	twd_sk_has( '.twd-sk-phone-whole .twd-sk-image-text__img { width: 100%; height: auto; aspect-ratio: auto; max-height: 80vh; animation: none; }', $tail );
	twd_sk_hasnt( 'vw', $tail, 'no viewport-width units' );
	twd_sk_hasnt( '@media (min-width', $tail );
	twd_sk_has( 'twd-sk-phone-whole', file_get_contents( ABSPATH . 'assets/twd-site-kit.css' ) );
} );

twd_sk_test( 'wide pictures: a full-width band asks for the picture at the full screen width, a boxed band, a picture elsewhere and a band without a list keep the usual cap', function () {
	twd_stub_reset();
	$GLOBALS['twd_stub']['srcset_on'] = true;
	$GLOBALS['twd_stub']['media_urls']['https://example.test/wp-content/uploads/2026/11/sofa-e1.jpg'] = 222;
	$GLOBALS['twd_stub']['media_meta'][222] = array( 'width' => 2000, 'height' => 840, 'file' => '2026/11/sofa-e1.jpg', 'sizes' => array( 'large' => array( 'file' => 'sofa-e1-1024x430.jpg', 'width' => 1024, 'height' => 430 ) ) );
	$hero = TWD_SK_Registry::get( 'hero' )['skeleton'];
	$page = $hero . "\n" . twd_sk_wp_band() . "\n" . twd_sk_wp_band( ' twd-sk-band-boxed' ) . "\n" . TWD_SK_Registry::get( 'image_text' )['skeleton'];
	$out  = TWD_SK_Images::process( $page );
	preg_match_all( '/<img\b[^>]*>/i', $out, $m );
	$tags = $m[0];
	$band = null;
	$boxed = null;
	foreach ( $tags as $t ) {
		if ( false !== strpos( $t, 'sofa-e1-1024x430.jpg' ) ) {
			if ( null === $band ) {
				$band = $t;
			} else {
				$boxed = $t;
			}
		}
	}
	twd_sk_true( null !== $band && null !== $boxed, 'both bands have their picture' );
	twd_sk_has( 'sizes="100vw"', $band, 'a full-width band: the whole screen width' );
	twd_sk_has( 'sizes="(max-width: 1100px) 100vw, 1100px"', $boxed, 'a boxed band keeps the usual cap' );
	twd_sk_has( 'srcset="', $band );
	twd_sk_has( 'sofa-e1.jpg 2000w', $band, 'the full-size copy is on offer' );
	$GLOBALS['twd_stub']['srcset_on'] = false;
} );

twd_sk_test( 'wide pictures: a resized library address gets a list of sizes built from its full-size picture, and keeps its own width, height and position as before', function () {
	twd_stub_reset();
	$GLOBALS['twd_stub']['srcset_on'] = true;
	$GLOBALS['twd_stub']['media_urls']['https://example.test/wp-content/uploads/2026/11/sofa-e1.jpg'] = 222;
	$GLOBALS['twd_stub']['media_meta'][222] = array( 'width' => 2000, 'height' => 840, 'file' => '2026/11/sofa-e1.jpg', 'sizes' => array( 'large' => array( 'file' => 'sofa-e1-1024x430.jpg', 'width' => 1024, 'height' => 430 ) ) );
	$tag = TWD_SK_Images::tag( '<img class="x" src="https://example.test/wp-content/uploads/2026/11/sofa-e1-1024x430.jpg" alt="A">', false );
	twd_sk_has( 'srcset="https://example.test/wp-content/uploads/2026/11/sofa-e1-1024x430.jpg 1024w', $tag, 'a list of sizes' );
	twd_sk_has( 'sizes="(max-width: 1100px) 100vw, 1100px"', $tag, 'the usual cap when no band says otherwise' );
	twd_sk_hasnt( 'width="', $tag, 'no width and height added: an old page does not change shape' );
	twd_sk_hasnt( 'object-position', $tag );
	// A picture that is not in the library, or that already has its own list, is left alone.
	$other = TWD_SK_Images::tag( '<img src="https://example.test/wp-content/uploads/2026/11/elsewhere-1024x430.jpg" alt="A">', false );
	twd_sk_hasnt( 'srcset', $other, 'not a library picture' );
	$own = TWD_SK_Images::tag( '<img src="https://example.test/wp-content/uploads/2026/11/sofa-e1-1024x430.jpg" srcset="a 1x" alt="A">', false );
	twd_sk_eq( 1, substr_count( $own, 'srcset=' ), 'a list that is already there is not doubled' );
	// With WordPress's own functions absent (as for most of the suite) nothing is added.
	$GLOBALS['twd_stub']['srcset_on'] = false;
	twd_sk_hasnt( 'srcset', TWD_SK_Images::tag( '<img src="https://example.test/wp-content/uploads/2026/11/sofa-e1-1024x430.jpg" alt="A">', false ), 'no list when WordPress gives none' );
} );
