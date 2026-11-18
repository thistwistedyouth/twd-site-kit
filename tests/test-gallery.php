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
	twd_sk_eq( array( '', 'twd-sk-gallery--masonry', 'twd-sk-gallery--strip', 'twd-sk-gallery--slideshow', 'twd-sk-gallery--groups' ), array_keys( $all ) );
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

twd_sk_test( 'gallery 0.22: categories are plain groups with a heading each (all visible without the script), the filter row is built by the script and skips a gallery with one group', function () {
	$v = TWD_SK_Registry::get( 'gallery' )['variants']['twd-sk-gallery--groups']['skeleton'];
	twd_sk_eq( 2, substr_count( $v, 'class="twd-sk-gallery__group"' ) );
	twd_sk_eq( 2, substr_count( $v, 'twd-sk-gallery__groupname' ) );
	twd_sk_hasnt( '<button', $v );
	twd_sk_hasnt( 'hidden', $v );
	twd_sk_eq( $v, TWD_SK_Sanitizer::clean( $v ) );
	$js = file_get_contents( ABSPATH . 'assets/twd-site-kit-gallery.js' );
	twd_sk_has( 'groups.length < 2', $js );
	twd_sk_has( 'twd-sk-gallery__filter', $js );
	twd_sk_has( "closest('[hidden]')", $js );
} );

// Adding pictures to a gallery from the editor (0.22.0). Uses the helpers in test-custom.php and test-phone-picture.php.
function twd_sk_ga_page( $layout = '' ) {
	$c = TWD_SK_Registry::get( 'gallery' );
	return TWD_SK_Registry::get( 'hero' )['skeleton'] . "\n" . ( '' === $layout ? $c['skeleton'] : $c['variants'][ $layout ]['skeleton'] ) . "\n" . TWD_SK_Registry::get( 'text' )['skeleton'];
}

twd_sk_test( 'gallery add: chosen library pictures join the end of the gallery, in order, the page passes the sanitiser unchanged and nothing else moves', function () {
	twd_stub_reset();
	twd_sk_custom_page();
	twd_sk_pp_attachments();
	twd_sk_true( ! is_wp_error( TWD_SK_Store::save( 51, twd_sk_ga_page() ) ) );
	$before = TWD_SK_Sections::split( TWD_SK_Store::get_current( 51 ) );
	$out    = TWD_SK_Structure::gallery_add( 51, array( 'index' => 1, 'pictures' => array( 301, '302' ) ) );
	twd_sk_true( ! is_wp_error( $out ), is_wp_error( $out ) ? $out->get_error_message() : '' );
	twd_sk_eq( 1, $out['at'] );
	twd_sk_eq( 2, $out['noalt'], 'both have no description in the library, so the editor can say so' );
	$parts = TWD_SK_Sections::split( $out['html'] );
	twd_sk_eq( substr_count( $before['sections'][1], '<img' ) + 2, substr_count( $parts['sections'][1], '<img' ) );
	twd_sk_true( strpos( $parts['sections'][1], 'mirror-crop-1024x768.jpg' ) < strpos( $parts['sections'][1], 'mirror-crop-2-1024x768.jpg' ), 'in the order chosen' );
	twd_sk_true( strpos( $parts['sections'][1], 'mirror-crop-2-1024x768.jpg' ) > strpos( $parts['sections'][1], 'your-image.jpg' ), 'after the pictures already there' );
	twd_sk_eq( $out['html'], TWD_SK_Sanitizer::clean( $out['html'] ) );
	twd_sk_eq( $before['sections'][0], $parts['sections'][0] );
	twd_sk_eq( $before['sections'][2], $parts['sections'][2] );
} );

twd_sk_test( 'gallery add: in a gallery with categories the pictures go to the chosen category, and a missing or wrong category is refused', function () {
	twd_stub_reset();
	twd_sk_custom_page();
	twd_sk_pp_attachments();
	twd_sk_true( ! is_wp_error( TWD_SK_Store::save( 51, twd_sk_ga_page( 'twd-sk-gallery--groups' ) ) ) );
	$out = TWD_SK_Structure::gallery_add( 51, array( 'index' => 1, 'pictures' => array( 301 ), 'group' => 1 ) );
	twd_sk_true( ! is_wp_error( $out ), is_wp_error( $out ) ? $out->get_error_message() : '' );
	$sec    = TWD_SK_Sections::split( $out['html'] )['sections'][1];
	$groups = explode( 'class="twd-sk-gallery__group"', $sec );
	twd_sk_eq( 3, count( $groups ), 'still two categories' );
	twd_sk_hasnt( 'mirror-crop-1024x768.jpg', $groups[1], 'not in the first' );
	twd_sk_has( 'mirror-crop-1024x768.jpg', $groups[2], 'in the second' );
	foreach ( array( array(), array( 'group' => 2 ), array( 'group' => -1 ) ) as $extra ) {
		$bad = TWD_SK_Structure::gallery_add( 51, array( 'index' => 1, 'pictures' => array( 301 ) ) + $extra );
		twd_sk_true( is_wp_error( $bad ), 'a gallery with two categories needs a valid one' );
	}
} );

twd_sk_test( 'gallery add: only a gallery, only pictures in the media library, and at least one', function () {
	twd_stub_reset();
	twd_sk_custom_page();
	twd_sk_pp_attachments();
	twd_sk_true( ! is_wp_error( TWD_SK_Store::save( 51, twd_sk_ga_page() ) ) );
	twd_sk_true( is_wp_error( TWD_SK_Structure::gallery_add( 51, array( 'index' => 0, 'pictures' => array( 301 ) ) ) ), 'the hero is not a gallery' );
	twd_sk_true( is_wp_error( TWD_SK_Structure::gallery_add( 51, array( 'index' => 9, 'pictures' => array( 301 ) ) ) ), 'no such section' );
	twd_sk_true( is_wp_error( TWD_SK_Structure::gallery_add( 51, array( 'index' => 1, 'pictures' => array() ) ) ), 'no pictures' );
	twd_sk_true( is_wp_error( TWD_SK_Structure::gallery_add( 51, array( 'index' => 1, 'pictures' => array( 303 ) ) ) ), 'a pdf is not a picture' );
	twd_sk_true( is_wp_error( TWD_SK_Structure::gallery_add( 51, array( 'index' => 1, 'pictures' => array( 'x' ) ) ) ), 'not an id' );
} );

twd_sk_test( 'gallery add: the editor offers Add pictures in the Look panel of a gallery and sends only ids and a category through the structure route', function () {
	$js = file_get_contents( ABSPATH . 'assets/twd-site-kit-inline.js' );
	twd_sk_has( 'drawGalleryPictures', $js );
	twd_sk_has( "op: 'gallery_add'", $js );
	twd_sk_has( "multiple: 'add'", $js );
	$rest = file_get_contents( ABSPATH . 'includes/class-twd-sk-rest.php' );
	twd_sk_has( "'gallery_add' === \$op", $rest );
} );
