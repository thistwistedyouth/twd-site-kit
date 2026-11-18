<?php
// 0.22.0: the gallery component (grid, masonry, film strip, slideshow) and its script.

twd_sk_test( 'gallery 0.22: every layout passes the cleaner unchanged, has no h1, keeps its alt text, and its modifier class is styled and listed', function () {
	$c   = TWD_SK_Registry::get( 'gallery' );
	$raw = file_get_contents( ABSPATH . 'assets/twd-site-kit.css' );
	twd_sk_true( is_array( $c ), 'gallery is registered' );
	$all = array( '' => $c['skeleton'] );
	foreach ( $c['variants'] as $class => $v ) {
		$all[ $class ] = $v['skeleton'];
		twd_sk_true( is_string( $v['label'] ) && '' !== $v['label'], $class . ' has a label' );
		twd_sk_has( ' ' . $class, $v['skeleton'] );
		twd_sk_has( '.' . $class . '.' . $class, $raw );
		twd_sk_true( in_array( $class, $c['classes'], true ), $class . ' listed' );
	}
	twd_sk_eq( array( '', 'twd-sk-gallery--masonry', 'twd-sk-gallery--strip', 'twd-sk-gallery--slideshow' ), array_keys( $all ) );
	foreach ( $all as $class => $html ) {
		twd_sk_eq( $html, TWD_SK_Sanitizer::clean( $html ), 'layout "' . $class . '" survives the cleaner' );
		twd_sk_eq( 0, substr_count( $html, '<h1' ), 'no h1 in "' . $class . '"' );
		twd_sk_has( 'alt="Describe the image"', $html );
		twd_sk_has( 'twd-sk-gallery__track', $html );
	}
} );

twd_sk_test( 'gallery 0.22: a gallery section a person writes by hand keeps its pictures, captions and layout class; a made-up class and a script are removed', function () {
	$html = '<section class="twd-sk-gallery twd-sk-gallery--strip twd-sk-tone-base"><div class="twd-sk-inner"><h2 class="twd-sk-title">Work</h2><div class="twd-sk-gallery__track">'
		. '<figure class="twd-sk-gallery__item"><img class="twd-sk-gallery__img" src="https://example.com/a.jpg" alt="A painting of a hill"><figcaption class="twd-sk-gallery__caption">Hill</figcaption></figure>'
		. '</div></div></section>';
	twd_sk_eq( $html, TWD_SK_Sanitizer::clean( $html ) );
	$bad = str_replace( 'twd-sk-gallery--strip', 'twd-sk-gallery--strip twd-sk-gallery--made-up', $html ) . '<script>alert(1)</script>';
	$out = TWD_SK_Sanitizer::clean( $bad );
	twd_sk_hasnt( 'made-up', $out );
	twd_sk_hasnt( '<script', $out );
	twd_sk_has( 'twd-sk-gallery--strip', $out );
} );

twd_sk_test( 'gallery 0.22: the script exists, is plain ASCII, builds nothing from text, never moves by itself, and the page loads it only for a gallery', function () {
	$js = file_get_contents( ABSPATH . 'assets/twd-site-kit-gallery.js' );
	twd_sk_true( 1 !== preg_match( '/[^\x00-\x7F]/', $js ), 'ASCII only' );
	twd_sk_hasnt( 'innerHTML', $js );
	twd_sk_hasnt( 'setInterval', $js );
	twd_sk_hasnt( 'autoplay', strtolower( $js ) );
	twd_sk_has( 'prefers-reduced-motion', $js );
	twd_sk_has( "'Escape'", $js );
	twd_sk_has( 'ArrowRight', $js );
	$page = file_get_contents( ABSPATH . 'includes/class-twd-sk-page.php' );
	twd_sk_has( "strpos( (string) \$html, 'twd-sk-gallery' )", $page );
	twd_sk_has( 'twd-site-kit-gallery.js', $page );
} );

twd_sk_test( 'gallery 0.22: the open-bigger view is hidden by default and uses tokens only', function () {
	$raw = file_get_contents( ABSPATH . 'assets/twd-site-kit.css' );
	twd_sk_has( '.twd-sk-gallery__box[hidden]', $raw );
	$ed = file_get_contents( ABSPATH . 'includes/class-twd-sk-inline.php' );
	twd_sk_has( "'twd-sk-gallery__item'     => 'twd-sk-gallery__img'", $ed );
} );
