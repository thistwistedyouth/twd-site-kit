<?php
// Picture band, whole picture (13 Nov 2026, from the pilot site's Writing page): a header picture whose top and bottom both matter
// was cropped by the band's fixed height on a computer. A Shape choice shows the whole picture edge to edge, as tall as it needs.

twd_sk_test( 'band whole picture: a Shape choice on the picture band, one shared class the sanitiser keeps, and the other choices are as before', function () {
	$o = TWD_SK_Looks::options( 'image_text' );
	twd_sk_true( isset( $o['band_shape']['choices']['twd-sk-shape-whole'] ) );
	twd_sk_eq( 'twd-sk-image-text--band', $o['band_shape']['requires'] );
	twd_sk_true( in_array( 'twd-sk-shape-whole', TWD_SK_Registry::shared_classes(), true ) );
	$band = array( 'twd-sk-image-text', 'twd-sk-image-text--band', 'twd-sk-image-text--band-taller' );
	$on   = TWD_SK_Looks::classes_for( 'image_text', $band, null, array( 'band_shape' => 'twd-sk-shape-whole' ) );
	twd_sk_true( in_array( 'twd-sk-shape-whole', $on, true ) && in_array( 'twd-sk-image-text--band-taller', $on, true ), 'added, the rest stays' );
	twd_sk_eq( $band, TWD_SK_Looks::classes_for( 'image_text', $on, null, array( 'band_shape' => '' ) ), 'Fixed height takes it away and leaves the rest exactly' );
	$html = '<section class="twd-sk-image-text twd-sk-image-text--band twd-sk-shape-whole"><img class="twd-sk-image-text__img" src="/wp-content/uploads/a.jpg" alt="A mountain"></section>';
	twd_sk_has( 'twd-sk-shape-whole', TWD_SK_Sanitizer::clean( $html ) );
} );

twd_sk_test( 'band whole picture css: no fixed height, no crop, no zoom, no viewport-width units, after the other band rules', function () {
	$src  = file_get_contents( ABSPATH . 'tools/kit.src.css' );
	$at   = strpos( $src, '/* Picture band, whole picture (13 Nov 2026)' );
	twd_sk_true( $at > strpos( $src, '/* Picture band shape and boxed (12 Nov 2026)' ), 'after the shape rules, so it wins' );
	$tail = str_replace( "\r", '', substr( $src, $at ) );
	twd_sk_has( '.twd-sk-shape-whole.twd-sk-image-text--band { height: auto; }', $tail );
	twd_sk_has( '.twd-sk-shape-whole .twd-sk-image-text__img { width: 100%; height: auto; max-height: none; aspect-ratio: auto; animation: none; }', $tail );
	twd_sk_hasnt( 'vw', $tail );
	twd_sk_has( 'twd-sk-shape-whole', file_get_contents( ABSPATH . 'assets/twd-site-kit.css' ) );
} );
