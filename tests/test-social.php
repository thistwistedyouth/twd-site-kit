<?php
// Social buttons (0.16.0): kit button links that carry a network class; the plugin draws the icon when the page is
// shown. The sanitiser is untouched, nothing drawn is stored, and a default page prints the same bytes.

function twd_sk_social_html() {
	return '<section class="twd-sk-social-links twd-sk-tone-base"><div class="twd-sk-inner"><h2 class="twd-sk-title">Find us</h2><div class="twd-sk-social-links__list">'
		. '<a class="twd-sk-btn twd-sk-btn--outline twd-sk-social twd-sk-social--whatsapp" href="https://www.whatsapp.com/channel/examplepage">WhatsApp</a>'
		. '<a class="twd-sk-btn twd-sk-btn--outline twd-sk-social twd-sk-social--facebook" href="https://www.facebook.com/examplepage">Facebook</a>'
		. '<a class="twd-sk-btn twd-sk-btn--outline twd-sk-social twd-sk-social--instagram" href="https://instagram.com/examplepage">Instagram</a>'
		. '<a class="twd-sk-btn twd-sk-btn--outline twd-sk-social twd-sk-social--youtube" href="https://www.youtube.com/@examplepage">YouTube</a>'
		. '</div></div></section>';
}

twd_sk_test( 'social: the component and every class are in the registry, and the sanitiser keeps a social button as typed', function () {
	$reg = TWD_SK_Registry::all();
	twd_sk_true( isset( $reg['social'] ), 'the Social links component' );
	foreach ( array( 'twd-sk-social', 'twd-sk-social--whatsapp', 'twd-sk-social--facebook', 'twd-sk-social--instagram', 'twd-sk-social--youtube', 'twd-sk-social--icon' ) as $c ) {
		twd_sk_true( in_array( $c, TWD_SK_Registry::shared_classes(), true ), $c . ' is a shared class' );
	}
	$html = twd_sk_social_html();
	twd_sk_eq( $html, TWD_SK_Sanitizer::clean( $html ), 'stored exactly as typed' );
	foreach ( array( $reg['social']['skeleton'], $reg['social']['variants']['twd-sk-social-links--icons']['skeleton'] ) as $sk ) {
		twd_sk_eq( $sk, TWD_SK_Sanitizer::clean( $sk ), 'skeleton unchanged' );
	}
} );

twd_sk_test( 'social: the sample links are leftovers, so a page cannot be published with them', function () {
	$reg = TWD_SK_Registry::all();
	twd_sk_true( count( TWD_SK_Sanitizer::find_leftovers( $reg['social']['skeleton'] ) ) > 0, 'found in the layout' );
	twd_sk_true( count( TWD_SK_Sanitizer::find_leftovers( $reg['social']['variants']['twd-sk-social-links--icons']['skeleton'] ) ) > 0, 'found in the icons layout' );
} );

twd_sk_test( 'social: the sanitiser still strips an svg pasted into a page (nothing about it was loosened)', function () {
	$out = TWD_SK_Sanitizer::clean( '<p><svg viewBox="0 0 24 24"><path d="M0 0h24v24H0z"/></svg>Hi</p>' );
	twd_sk_hasnt( '<svg', $out );
	twd_sk_hasnt( '<path', $out );
} );

twd_sk_test( 'social: display adds the icon and wraps the words, for each network, and never stores either', function () {
	$stored = twd_sk_social_html();
	$shown  = TWD_SK_Social::decorate( $stored );
	twd_sk_eq( 4, substr_count( $shown, '<svg ' ), 'four icons' );
	twd_sk_eq( 4, substr_count( $shown, 'class="twd-sk-social__label"' ), 'four wrapped labels' );
	twd_sk_true( 1 === preg_match( '/<a [^>]*twd-sk-social--whatsapp[^>]*><span class="twd-sk-social__icon" aria-hidden="true"><svg [^>]*viewBox="0 0 24 24"[^>]*>.*?<\/svg><\/span><span class="twd-sk-social__label">WhatsApp<\/span><\/a>/s', $shown ), 'icon first, then the words' );
	twd_sk_hasnt( '<svg', $stored, 'the stored page has none' );
	twd_sk_eq( $shown, TWD_SK_Social::decorate( $shown ), 'a second pass changes nothing' );
	twd_sk_hasnt( 'url(', $shown );
	twd_sk_hasnt( 'fill="#', $shown, 'icons take the colour of the words' );
	twd_sk_hasnt( 'stroke="#', $shown );
} );

twd_sk_test( 'social: a wrong host, a plain http link or a link with no address gets no icon (and is named); wa.me and subdomains fit', function () {
	$bad = array(
		'<a class="twd-sk-btn twd-sk-social twd-sk-social--facebook" href="https://www.example.com/x">Facebook</a>',
		'<a class="twd-sk-btn twd-sk-social twd-sk-social--facebook" href="http://www.facebook.com/x">Facebook</a>',
		'<a class="twd-sk-btn twd-sk-social twd-sk-social--facebook" href="https://facebook.com.evil.example/x">Facebook</a>',
		'<a class="twd-sk-btn twd-sk-social twd-sk-social--facebook" href="' . str_replace( 'www.', 'user' . chr( 64 ), 'https://www.facebook.com/x' ) . '">Facebook</a>',
		'<a class="twd-sk-btn twd-sk-social twd-sk-social--youtube">YouTube</a>',
	);
	foreach ( $bad as $a ) {
		twd_sk_eq( $a, TWD_SK_Social::decorate( $a ), 'untouched: ' . $a );
		twd_sk_true( count( TWD_SK_Social::problems( $a ) ) > 0, 'named: ' . $a );
	}
	twd_sk_true( TWD_SK_Social::link_fits( 'whatsapp', 'https://wa.me/15551234567?text=Hi' ), 'wa.me with text' );
	twd_sk_true( TWD_SK_Social::link_fits( 'whatsapp', 'https://api.whatsapp.com/send?phone=1' ), 'whatsapp subdomain' );
	twd_sk_true( TWD_SK_Social::link_fits( 'youtube', 'https://youtu.be/abc' ), 'youtu.be' );
	twd_sk_true( ! TWD_SK_Social::link_fits( 'youtube', 'https://www.youtube.com.example/abc' ), 'a host that only ends in the same letters is not a match' );
	twd_sk_eq( array(), TWD_SK_Social::problems( twd_sk_social_html() ), 'good links raise nothing' );
	$ids = array();
	foreach ( TWD_SK_Quality::check( '<h1>x</h1>' . $bad[0] ) as $i ) {
		$ids[] = $i['id'];
	}
	twd_sk_true( in_array( 'social_link', $ids, true ), 'the quality check names it' );
} );

twd_sk_test( 'social: a page with no social button prints exactly the same bytes, and the page drawing path draws the icons', function () {
	$plain = '<section class="twd-sk-cta twd-sk-cta--strip"><div class="twd-sk-cta__inner"><p class="twd-sk-cta__text">Hello</p><a class="twd-sk-btn twd-sk-btn--primary" href="https://www.example.com/x">Go</a></div></section>';
	twd_sk_eq( $plain, TWD_SK_Social::decorate( $plain ), 'unchanged' );
	twd_sk_eq( '', TWD_SK_Social::decorate( '' ) );
	$drawn = TWD_SK_Page::render_html( twd_sk_social_html() );
	twd_sk_eq( 4, substr_count( $drawn, '<span class="twd-sk-social__icon"' ), 'four icons through the page drawing' );
} );

twd_sk_test( 'social css: the icon, the label, the icon-only layouts and the section are styled from tokens, with no url() and no colour literal', function () {
	$css = file_get_contents( ABSPATH . 'assets/twd-site-kit.css' );
	foreach ( array( 'twd-sk-social__icon', 'twd-sk-social__label', 'twd-sk-social--icon', 'twd-sk-social-links--icons', 'twd-sk-social-links__list' ) as $c ) {
		twd_sk_has( $c, $css, $c );
	}
	$src = file_get_contents( ABSPATH . 'tools/kit.src.css' );
	$pos = strpos( $src, 'social buttons and the Social links section' );
	$end = strpos( $src, 'areas covered (0.8.0)' );
	$blk = substr( $src, $pos, $end - $pos );
	twd_sk_hasnt( 'url(', $blk );
	twd_sk_true( 0 === preg_match( '/#[0-9a-fA-F]{3,8}\b|rgba?\(/', $blk ), 'no colour literal' );
	twd_sk_true( 1 === preg_match( '/width: 48px; height: 48px/', $blk ), 'a 48px touch target for the icon-only button' );
} );

twd_sk_test( 'social: the icons are fixed strings in the plugin: the class reads no file, no network and no option', function () {
	$src = file_get_contents( ABSPATH . 'includes/class-twd-sk-social.php' );
	foreach ( array( 'file_get_contents', 'wp_remote', 'get_option', 'update_option', 'curl_', 'fopen', 'include', 'require' ) as $bad ) {
		twd_sk_hasnt( $bad, $src, $bad );
	}
} );
