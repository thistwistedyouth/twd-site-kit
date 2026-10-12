<?php
// 0.10.0: glow boxes and the quote slider.

twd_sk_test( 'effects: the glow boxes and the quote slider are kit sections that pass the cleaner unchanged', function () {
	$glow   = TWD_SK_Registry::get( 'services' )['variants']['twd-sk-services--glow']['skeleton'];
	$slider = TWD_SK_Registry::get( 'quote' )['variants']['twd-sk-quote--slider']['skeleton'];
	foreach ( array( $glow, $slider ) as $html ) {
		twd_sk_eq( $html, TWD_SK_Sanitizer::clean( $html ), 'kept as it is' );
	}
	twd_sk_eq( 3, substr_count( $glow, 'twd-sk-services__item' ) );
	twd_sk_hasnt( 'href=', $glow, 'boxes, not links to pages that may not exist' );
	twd_sk_hasnt( ' id=', $glow, 'no id that a second services section would repeat' );
	twd_sk_eq( 3, substr_count( $slider, 'twd-sk-quote__card' ) );
	twd_sk_has( '<div class="twd-sk-quote__track">', $slider );
	twd_sk_has( 'real quotes', TWD_SK_Registry::get( 'quote' )['description'] );
	foreach ( array( 'twd-sk-quote__controls', 'twd-sk-quote__arrow', 'twd-sk-quote__dots', 'twd-sk-quote__dot' ) as $class ) {
		twd_sk_true( isset( TWD_SK_Registry::allowed_classes()[ $class ] ), $class . ' is known (added by the script, styled by the sheet)' );
	}
} );

twd_sk_test( 'effects: the slider script loads only on a page with a slider, and never in safe mode', function () {
	TWD_SK_Page::enqueue_extras( '<section class="twd-sk-quote twd-sk-quote--cards"></section>' );
	twd_sk_true( ! isset( $GLOBALS['twd_stub']['scripts']['twd-site-kit-slider'] ) );
	TWD_SK_Page::enqueue_extras( TWD_SK_Registry::get( 'quote' )['variants']['twd-sk-quote--slider']['skeleton'] );
	twd_sk_true( $GLOBALS['twd_stub']['scripts']['twd-site-kit-slider']['enqueued'] );
	twd_stub_reset();
	TWD_SK_Safe::set( true );
	TWD_SK_Page::enqueue_extras( 'twd-sk-quote--slider' );
	twd_sk_true( ! isset( $GLOBALS['twd_stub']['scripts']['twd-site-kit-slider'] ) );
} );

twd_sk_test( 'effects: the slider script builds nothing from text, never moves on its own, respects less motion, and is plain ASCII', function () {
	$js = file_get_contents( ABSPATH . 'assets/twd-site-kit-slider.js' );
	foreach ( array( 'innerHTML', 'outerHTML', 'insertAdjacentHTML', 'eval(', 'new Function', 'document.write', 'setInterval', 'fetch(', 'XMLHttpRequest', 'http://', 'https://' ) as $bad ) {
		twd_sk_hasnt( $bad, $js );
	}
	twd_sk_has( 'textContent', $js );
	twd_sk_has( "prefers-reduced-motion: reduce", $js );
	twd_sk_has( "'ArrowRight'", $js );
	twd_sk_true( 1 !== preg_match( '/[^\x00-\x7F]/', $js ), 'ASCII only, so it reads the same whatever the server says the encoding is' );
} );
