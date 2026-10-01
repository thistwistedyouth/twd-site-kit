<?php
// The gallery: every component and variant, generated from the registry.

require_once ABSPATH . 'bin/build-gallery.php';

function twd_sk_gallery_file() {
	return file_get_contents( ABSPATH . 'starters/_gallery.html' );
}

twd_sk_test( 'gallery: starters/_gallery.html exists and equals what the generator produces from the registry', function () {
	twd_sk_true( is_file( ABSPATH . 'starters/_gallery.html' ) );
	twd_sk_eq( twd_sk_build_gallery(), twd_sk_gallery_file(), 'run: php bin/build-gallery.php' );
} );

twd_sk_test( 'gallery: passes the sanitiser with nothing removed and nothing changed', function () {
	$html = twd_sk_gallery_file();
	$r    = TWD_SK_Sanitizer::clean_with_report( $html );
	twd_sk_eq( 0, $r['report']['total'], 'removed: ' . json_encode( $r['report']['removed'] ) );
	twd_sk_eq( preg_replace( '/>\s+</', '><', trim( $html ) ), preg_replace( '/>\s+</', '><', $r['html'] ) );
} );

twd_sk_test( 'gallery: shows every component and every variant', function () {
	$html = twd_sk_gallery_file();
	foreach ( TWD_SK_Registry::all() as $id => $c ) {
		twd_sk_has( ' / ', $html );
		if ( ! $c['variants'] ) {
			twd_sk_has( '<strong>' . $id . ' / default</strong>', $html, $id );
		}
		foreach ( $c['variants'] as $class => $variant ) {
			twd_sk_has( '<strong>' . $id . ' / ' . $class . '</strong>', $html, $id . ' ' . $class );
			twd_sk_true( substr_count( $html, 'class="' ) > 0 );
			twd_sk_true( 1 === preg_match( '/<section class="[^"]*\b' . preg_quote( $class, '/' ) . '\b/', $html ), 'variant root present: ' . $class );
		}
	}
} );

twd_sk_test( 'gallery: the two bundled placeholder pictures exist, are plain SVG and carry no scripts or links', function () {
	foreach ( array( 'placeholder-image.svg', 'placeholder-badge.svg' ) as $file ) {
		$svg = file_get_contents( ABSPATH . 'assets/' . $file );
		twd_sk_true( 0 === strpos( $svg, '<svg' ), $file );
		foreach ( array( '<script', 'onload', 'href', 'xlink', '<image', '<foreignObject', 'http://www.w3.org/1999/xlink' ) as $bad ) {
			twd_sk_hasnt( $bad, $svg, $file );
		}
		twd_sk_true( filesize( ABSPATH . 'assets/' . $file ) < 2000, $file . ' is small' );
	}
} );

twd_sk_test( 'gallery: also shows the plain image row, which is not a named variant', function () {
	twd_sk_has( '<strong>image_text / default</strong>', twd_sk_gallery_file() );
} );

twd_sk_test( 'gallery: has exactly one h1, in the first hero, and later hero titles are h2', function () {
	$html = twd_sk_gallery_file();
	twd_sk_eq( 1, substr_count( $html, '<h1' ) );
	twd_sk_eq( 3, substr_count( $html, 'class="twd-sk-hero__title"' ), 'three hero titles in all' );
	twd_sk_true( strpos( $html, '<h1' ) < strpos( $html, 'twd-sk-hero--image' ), 'the h1 is in the first hero' );
	twd_sk_eq( 1, substr_count( TWD_SK_Sanitizer::clean( $html ), '<h1' ), 'and the sanitiser keeps it' );
} );

twd_sk_test( 'gallery: every ID is unique and every #anchor link points at an ID that exists', function () {
	$html = twd_sk_gallery_file();
	preg_match_all( '/\sid="([^"]+)"/', $html, $ids );
	twd_sk_eq( count( $ids[1] ), count( array_unique( $ids[1] ) ), 'unique ids' );
	preg_match_all( '/href="#([^"]+)"/', $html, $links );
	foreach ( $links[1] as $target ) {
		twd_sk_true( in_array( $target, $ids[1], true ), 'anchor target exists: #' . $target );
	}
} );

twd_sk_test( 'gallery: placeholder text only, no real names, details or addresses', function () {
	$html = twd_sk_gallery_file();
	preg_match_all( '/[A-Za-z0-9._%+-]+@[A-Za-z0-9.-]+/', $html, $emails );
	foreach ( array_unique( $emails[0] ) as $email ) {
		twd_sk_eq( 'you@example.com', $email );
	}
	preg_match_all( '#https?://[^"\s<]+#', $html, $urls );
	foreach ( $urls[0] as $url ) {
		twd_sk_true( 1 === preg_match( '#^https?://www\.example\.com#', $url ), 'example URL only: ' . $url );
	}
	preg_match_all( '#src="([^"]+)"#', $html, $srcs );
	foreach ( $srcs[1] as $src ) {
		twd_sk_true( 1 === preg_match( '#^/wp-content/plugins/twd-site-kit/assets/placeholder-(image|badge)\.svg$#', $src ), 'bundled placeholder picture only: ' . $src );
	}
	twd_sk_hasnt( 'your-image', $html );
	twd_sk_hasnt( '[', preg_replace( '/\[twd_articles [^\]]*\]/', '', $html ), 'no square brackets except the articles shortcode' );
} );

twd_sk_test( 'gallery: holds the articles shortcode once and no script, form, button or inline style', function () {
	$html = twd_sk_gallery_file();
	twd_sk_eq( 1, substr_count( $html, '[twd_articles' ) );
	foreach ( array( '<script', '<form', '<button', '<iframe', 'style=', 'onclick', 'javascript:', 'sms:' ) as $bad ) {
		twd_sk_hasnt( $bad, $html );
	}
} );

twd_sk_test( 'gallery: every class used in it is a registry class', function () {
	$html = twd_sk_gallery_file();
	preg_match_all( '/class="([^"]+)"/', $html, $m );
	$allowed = TWD_SK_Registry::allowed_classes();
	foreach ( $m[1] as $attr ) {
		foreach ( preg_split( '/\s+/', $attr ) as $class ) {
			twd_sk_true( isset( $allowed[ $class ] ), 'registered: ' . $class );
		}
	}
} );

twd_sk_test( 'gallery: stored through the store it comes back unchanged and reports nothing removed', function () {
	twd_stub_add_post( 13, 'page' );
	$r = TWD_SK_Store::save( 13, twd_sk_gallery_file(), array( 'note' => 'gallery' ) );
	twd_sk_eq( 1, $r['version'] );
	twd_sk_eq( 0, $r['report']['total'] );
	twd_sk_true( strlen( TWD_SK_Store::get_current( 13 ) ) < TWD_SK_Store::MAX_BYTES, 'within the page size limit' );
	twd_sk_true( TWD_SK_Page::page_has_h1( 13 ) );
} );

twd_sk_test( 'gallery: renders through [twd_page] with the articles shortcode as the only one run', function () {
	twd_stub_add_post( 13, 'page' );
	TWD_SK_Store::save( 13, twd_sk_gallery_file() );
	$GLOBALS['twd_stub']['post_id'] = 13;
	$out = TWD_SK_Page::render();
	twd_sk_has( '<RUN:twd_articles count="6" columns="3">', $out );
	twd_sk_eq( 1, substr_count( $out, '<RUN:' ) );
	twd_sk_true( 0 === strpos( $out, '<div class="twd-sk-page">' ) );
} );

twd_sk_test( 'gallery: the generator script is dash-free PHP 7.4 style and has a --stdout mode', function () {
	$src = file_get_contents( ABSPATH . 'bin/build-gallery.php' );
	twd_sk_has( '--stdout', $src );
	twd_sk_true( false === strpos( $src, "\xE2\x80\x94" ) && false === strpos( $src, "\xE2\x80\x93" ) );
} );
