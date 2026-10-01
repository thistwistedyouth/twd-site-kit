<?php
// Image attributes added at display time: size, lazy loading, hero priority.

twd_sk_test( 'images: the first picture in the first section is eager and high priority, every other picture is lazy', function () {
	$html = '<section class="twd-sk-hero"><img class="a" src="/u/hero.jpg" alt="Hero"><img class="b" src="/u/two.jpg" alt="Two"></section><section class="twd-sk-text"><img class="c" src="/u/three.jpg" alt="Three"></section>';
	$out  = TWD_SK_Images::process( $html );
	twd_sk_has( '<img class="a" src="/u/hero.jpg" alt="Hero" loading="eager" fetchpriority="high" decoding="async">', $out );
	twd_sk_has( '<img class="b" src="/u/two.jpg" alt="Two" loading="lazy" decoding="async">', $out );
	twd_sk_has( '<img class="c" src="/u/three.jpg" alt="Three" loading="lazy" decoding="async">', $out );
	twd_sk_eq( 1, substr_count( $out, 'fetchpriority="high"' ), 'only one high-priority picture' );
	twd_sk_eq( 1, substr_count( $out, 'loading="eager"' ) );
	twd_sk_eq( 2, substr_count( $out, 'loading="lazy"' ) );
} );

twd_sk_test( 'images: a first picture that is not in the first section is lazy too (nothing is guessed to be the hero)', function () {
	$html = '<section class="twd-sk-text"><p>No picture here</p></section><section class="twd-sk-hero"><img src="/u/a.jpg" alt="A"></section>';
	$out  = TWD_SK_Images::process( $html );
	twd_sk_hasnt( 'eager', $out );
	twd_sk_hasnt( 'fetchpriority', $out );
	twd_sk_has( 'loading="lazy"', $out );
} );

twd_sk_test( 'images: a page that is one section, or has no section at all, still treats its first picture as the hero', function () {
	twd_sk_has( 'loading="eager" fetchpriority="high"', TWD_SK_Images::process( '<img src="/u/a.jpg" alt="A"><p>x</p>' ) );
	twd_sk_has( 'loading="eager" fetchpriority="high"', TWD_SK_Images::process( '<section><img src="/u/a.jpg" alt="A"></section>' ) );
} );

twd_sk_test( 'images: the hero is never lazy, in any of the shipped hero layouts', function () {
	foreach ( TWD_SK_Registry::get( 'hero' )['variants'] as $class => $v ) {
		$out = TWD_SK_Images::process( $v['skeleton'] );
		if ( false === strpos( $v['skeleton'], '<img' ) ) {
			continue;
		}
		preg_match( '/<img[^>]*>/', $out, $m );
		twd_sk_hasnt( 'loading="lazy"', $m[0], $class );
		twd_sk_has( 'fetchpriority="high"', $m[0], $class );
	}
	$default = TWD_SK_Images::process( TWD_SK_Registry::get( 'hero' )['skeleton'] );
	preg_match( '/<img[^>]*>/', $default, $m );
	twd_sk_has( 'loading="eager"', $m[0] );
} );

twd_sk_test( 'images: nothing changes when there is no picture, and attributes already there are not repeated', function () {
	twd_sk_eq( '<p>No pictures</p>', TWD_SK_Images::process( '<p>No pictures</p>' ) );
	twd_sk_eq( '', TWD_SK_Images::process( '' ) );
	twd_sk_eq( null, TWD_SK_Images::process( null ) );
	$tag = '<img src="/u/a.jpg" alt="A" loading="lazy" decoding="sync" width="10" height="5" fetchpriority="low">';
	twd_sk_eq( $tag, TWD_SK_Images::tag( $tag, false ) );
	$out = TWD_SK_Images::tag( '<img src="/u/a.jpg" alt="A" loading="lazy">', true );
	twd_sk_eq( 1, substr_count( $out, 'loading=' ), 'an existing loading value stays' );
	twd_sk_has( 'fetchpriority="high"', $out );
} );

twd_sk_test( 'images: a self-closing tag keeps its closing slash', function () {
	twd_sk_eq( '<img src="/u/a.jpg" alt="A" loading="lazy" decoding="async" />', TWD_SK_Images::tag( '<img src="/u/a.jpg" alt="A" />', false ) );
} );

twd_sk_test( 'images: width and height come from the media library for this site\'s own pictures, including a resized copy', function () {
	$GLOBALS['twd_stub']['media_urls']['https://example.test/wp-content/uploads/hero.jpg']       = 90;
	$GLOBALS['twd_stub']['media_urls']['https://example.test/wp-content/uploads/hero-300x200.jpg'] = 90;
	$GLOBALS['twd_stub']['media_meta'][90] = array( 'width' => 1200, 'height' => 800, 'sizes' => array( 'medium' => array( 'file' => 'hero-300x200.jpg', 'width' => 300, 'height' => 200 ) ) );
	$out = TWD_SK_Images::tag( '<img src="/wp-content/uploads/hero.jpg" alt="A">', false );
	twd_sk_has( ' width="1200" height="800"', $out );
	$out = TWD_SK_Images::tag( '<img src="https://example.test/wp-content/uploads/hero-300x200.jpg" alt="A">', false );
	twd_sk_has( ' width="300" height="200"', $out );
} );

twd_sk_test( 'images: no size is added for another site\'s picture, a picture not in the library, an svg, or when the page gives one', function () {
	$GLOBALS['twd_stub']['media_urls']['https://example.test/wp-content/uploads/a.jpg'] = 91;
	$GLOBALS['twd_stub']['media_meta'][91] = array( 'width' => 100, 'height' => 50 );
	foreach ( array( '<img src="https://example.com/a.jpg" alt="x">', '<img src="/wp-content/uploads/unknown.jpg" alt="x">', '<img src="/wp-content/uploads/logo.svg" alt="x">' ) as $tag ) {
		twd_sk_hasnt( 'width=', TWD_SK_Images::tag( $tag, false ), $tag );
	}
	$given = '<img src="/wp-content/uploads/a.jpg" alt="x" width="40" height="20">';
	twd_sk_eq( 1, substr_count( TWD_SK_Images::tag( $given, false ), 'width=' ) );
	twd_sk_has( 'width="40"', TWD_SK_Images::tag( $given, false ) );
} );

twd_sk_test( 'images: the size lookup is remembered, including "not in the library"', function () {
	$GLOBALS['twd_stub']['media_urls']['https://example.test/wp-content/uploads/a.jpg'] = 92;
	$GLOBALS['twd_stub']['media_meta'][92] = array( 'width' => 100, 'height' => 50 );
	twd_sk_eq( array( 100, 50 ), TWD_SK_Images::dimensions( '/wp-content/uploads/a.jpg' ) );
	$key = 'twd_sk_dim_' . md5( 'https://example.test/wp-content/uploads/a.jpg' );
	twd_sk_eq( TWD_SK_Images::DIM_TTL, $GLOBALS['twd_stub']['transients'][ $key ]['seconds'] );
	unset( $GLOBALS['twd_stub']['media_meta'][92] );
	twd_sk_eq( array( 100, 50 ), TWD_SK_Images::dimensions( '/wp-content/uploads/a.jpg' ), 'second call uses the memory' );
	twd_sk_eq( null, TWD_SK_Images::dimensions( '/wp-content/uploads/nope.jpg' ) );
	$nope = 'twd_sk_dim_' . md5( 'https://example.test/wp-content/uploads/nope.jpg' );
	twd_sk_eq( 3600, $GLOBALS['twd_stub']['transients'][ $nope ]['seconds'], 'a miss is remembered for an hour' );
	twd_sk_eq( null, TWD_SK_Images::dimensions( '' ) );
	twd_sk_eq( null, TWD_SK_Images::dimensions( array() ) );
} );

twd_sk_test( 'images: the page render applies it, pictures from the articles grid are left alone, and safe mode switches it off', function () {
	twd_stub_add_post( 12, 'page', '[twd_page]' );
	$GLOBALS['twd_stub']['post_id'] = 12;
	TWD_SK_Store::save( 12, '<section class="twd-sk-hero"><img src="/u/hero.jpg" alt="Hero"></section><section class="twd-sk-text"><img src="/u/b.jpg" alt="B"></section>[twd_articles count="3"]' );
	$out = TWD_SK_Page::render();
	twd_sk_has( 'src="/u/hero.jpg" alt="Hero" loading="eager" fetchpriority="high"', $out );
	twd_sk_has( 'src="/u/b.jpg" alt="B" loading="lazy"', $out );
	TWD_SK_Safe::set( true );
	$out = TWD_SK_Page::render();
	twd_sk_hasnt( 'loading=', $out, 'safe mode: the page is exactly as stored' );
	twd_sk_hasnt( 'fetchpriority', $out );
} );

twd_sk_test( 'images: the stored HTML never holds these attributes (the sanitiser removes them), so they cannot be set by hand', function () {
	$out = TWD_SK_Sanitizer::clean( '<img src="/u/a.jpg" alt="A" loading="eager" fetchpriority="low" decoding="sync" srcset="x 1x" sizes="100vw" referrerpolicy="unsafe-url">' );
	foreach ( array( 'loading', 'fetchpriority', 'decoding', 'srcset', 'sizes', 'referrerpolicy' ) as $attr ) {
		twd_sk_hasnt( $attr, $out );
	}
} );

twd_sk_test( 'images: the class makes no network call and holds no em dashes', function () {
	$src = file_get_contents( ABSPATH . 'includes/class-twd-sk-images.php' );
	foreach ( array( 'wp_remote_', 'file_get_contents', 'curl_', 'eval(' ) as $bad ) {
		twd_sk_hasnt( $bad, $src );
	}
	twd_sk_hasnt( "\xE2\x80\x94", $src );
} );
