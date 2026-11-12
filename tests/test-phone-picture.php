<?php
// A picture for phones in a picture band (12 Nov 2026, from the pilot site): a photo with small lettering in it was fine on a
// desktop and too small to read on a phone. A band can now hold a second picture (class twd-sk-phone-img, with twd-sk-has-phone on
// the section) that only phones show; everyone else keeps the main one. Set, changed and removed from the Look panel through the
// structure route. Uses the helpers in test-custom.php.

function twd_sk_pp_band() {
	return '<section class="twd-sk-image-text twd-sk-image-text--band twd-sk-shape-natural"><img class="twd-sk-image-text__img" src="https://example.test/wp-content/uploads/2026/11/road.jpg" alt="A desert road with a wing mirror"></section>';
}
function twd_sk_pp_page() {
	return TWD_SK_Registry::get( 'hero' )['skeleton'] . "\n" . twd_sk_pp_band() . "\n" . TWD_SK_Registry::get( 'text' )['skeleton'];
}
function twd_sk_pp_attachments() {
	$GLOBALS['twd_stub']['attachments'][301] = array( 'is_image' => true, 'url' => 'https://example.test/wp-content/uploads/2026/11/mirror-crop-1024x768.jpg', 'w' => 1024, 'h' => 768 );
	$GLOBALS['twd_stub']['attachments'][302] = array( 'is_image' => true, 'url' => 'https://example.test/wp-content/uploads/2026/11/mirror-crop-2-1024x768.jpg', 'w' => 1024, 'h' => 768 );
	$GLOBALS['twd_stub']['attachments'][303] = array( 'is_image' => false, 'url' => 'https://example.test/wp-content/uploads/2026/11/notes.pdf' );
}

twd_sk_test( 'phone picture: a second picture is added after the main one, the section is marked, the page passes the sanitiser unchanged, and the main picture and its description stay', function () {
	twd_stub_reset();
	twd_sk_custom_page();
	twd_sk_pp_attachments();
	twd_sk_true( ! is_wp_error( TWD_SK_Store::save( 51, twd_sk_pp_page() ) ) );
	$out = TWD_SK_Structure::phone_picture( 51, array( 'index' => 1, 'picture' => 301 ) );
	twd_sk_true( ! is_wp_error( $out ), is_wp_error( $out ) ? $out->get_error_message() : '' );
	twd_sk_eq( 1, $out['at'] );
	$parts = TWD_SK_Sections::split( $out['html'] );
	$band  = $parts['sections'][1];
	twd_sk_has( 'class="twd-sk-image-text twd-sk-image-text--band twd-sk-shape-natural twd-sk-has-phone"', $band, 'the section is marked and keeps its other classes' );
	twd_sk_has( '<img class="twd-sk-image-text__img" src="https://example.test/wp-content/uploads/2026/11/road.jpg" alt="A desert road with a wing mirror"><img class="twd-sk-image-text__img twd-sk-phone-img" src="https://example.test/wp-content/uploads/2026/11/mirror-crop-1024x768.jpg" alt="A desert road with a wing mirror">', $band, 'the main picture first and untouched, the phone picture after it with the same description' );
	twd_sk_eq( 2, substr_count( $band, '<img' ) );
	twd_sk_eq( $out['html'], TWD_SK_Sanitizer::clean( $out['html'] ), 'passes the sanitiser unchanged' );
	twd_sk_eq( TWD_SK_Sections::split( twd_sk_pp_page() )['sections'][0], $parts['sections'][0], 'the other sections are untouched' );
	twd_sk_eq( TWD_SK_Sections::split( twd_sk_pp_page() )['sections'][2], $parts['sections'][2] );
	twd_sk_eq( 'image-text', TWD_SK_Sections::type_of( $band ), 'still a picture and text section, so the Look choices stay' );
} );

twd_sk_test( 'phone picture: changing it leaves one phone picture, removing it takes the picture and the mark away, and the stored band is exactly as it was before', function () {
	twd_stub_reset();
	twd_sk_custom_page();
	twd_sk_pp_attachments();
	twd_sk_true( ! is_wp_error( TWD_SK_Store::save( 51, twd_sk_pp_page() ) ) );
	$one = TWD_SK_Structure::phone_picture( 51, array( 'index' => 1, 'picture' => 301 ) );
	twd_sk_true( ! is_wp_error( TWD_SK_Store::save( 51, $one['html'] ) ) );
	$two = TWD_SK_Structure::phone_picture( 51, array( 'index' => 1, 'picture' => '302' ) );
	twd_sk_true( ! is_wp_error( $two ) );
	twd_sk_eq( 1, substr_count( $two['html'], 'twd-sk-phone-img' ), 'one phone picture' );
	twd_sk_eq( 1, substr_count( $two['html'], 'twd-sk-has-phone' ), 'one mark' );
	twd_sk_has( 'mirror-crop-2-1024x768.jpg', $two['html'] );
	twd_sk_hasnt( 'mirror-crop-1024x768.jpg', $two['html'], 'the earlier one is gone' );
	twd_sk_true( ! is_wp_error( TWD_SK_Store::save( 51, $two['html'] ) ) );
	$gone = TWD_SK_Structure::phone_picture( 51, array( 'index' => 1, 'remove' => true ) );
	twd_sk_true( ! is_wp_error( $gone ), is_wp_error( $gone ) ? $gone->get_error_message() : '' );
	twd_sk_eq( twd_sk_pp_page(), $gone['html'], 'byte for byte the page it was before' );
	twd_sk_true( ! is_wp_error( TWD_SK_Store::save( 51, $gone['html'] ) ) );
	twd_sk_is_error( 'twd_sk_bad_input', TWD_SK_Structure::phone_picture( 51, array( 'index' => 1, 'remove' => true ) ), 'nothing to remove says so' );
} );

twd_sk_test( 'phone picture: refused with the reason for a section that is not a picture band, a bad number, something that is not a library picture, and a band with no picture', function () {
	twd_stub_reset();
	twd_sk_custom_page();
	twd_sk_pp_attachments();
	twd_sk_true( ! is_wp_error( TWD_SK_Store::save( 51, twd_sk_pp_page() ) ) );
	$ok = 'twd_sk_bad_input';
	twd_sk_is_error( $ok, TWD_SK_Structure::phone_picture( 51, array( 'index' => 0, 'picture' => 301 ) ), 'the hero is not a picture band' );
	twd_sk_is_error( $ok, TWD_SK_Structure::phone_picture( 51, array( 'index' => 2, 'picture' => 301 ) ), 'a text section is not a picture band' );
	twd_sk_is_error( $ok, TWD_SK_Structure::phone_picture( 51, array( 'index' => 9, 'picture' => 301 ) ), 'past the end' );
	twd_sk_is_error( $ok, TWD_SK_Structure::phone_picture( 51, array( 'index' => 'x', 'picture' => 301 ) ), 'not a number' );
	twd_sk_is_error( $ok, TWD_SK_Structure::phone_picture( 51, array( 'index' => 1, 'picture' => 303 ) ), 'a file that is not a picture' );
	twd_sk_is_error( $ok, TWD_SK_Structure::phone_picture( 51, array( 'index' => 1, 'picture' => 999 ) ), 'not in the library' );
	twd_sk_is_error( $ok, TWD_SK_Structure::phone_picture( 51, array( 'index' => 1 ) ), 'no picture chosen' );
	$empty = '<section class="twd-sk-image-text twd-sk-image-text--band"></section>';
	TWD_SK_Store::save( 51, TWD_SK_Registry::get( 'hero' )['skeleton'] . "\n" . $empty );
	$msg = TWD_SK_Structure::phone_picture( 51, array( 'index' => 1, 'picture' => 301 ) );
	twd_sk_is_error( $ok, $msg, 'a band with no picture' );
	twd_sk_has( 'Choose its picture first', $msg->get_error_message() );
} );

twd_sk_test( 'phone picture through the page route: one version with a plain note, Undo brings the old band back, a stale page is refused', function () {
	twd_stub_reset();
	twd_sk_custom_page();
	twd_sk_pp_attachments();
	twd_sk_true( ! is_wp_error( TWD_SK_Store::save( 51, twd_sk_pp_page() ) ) );
	$ver = TWD_SK_Store::get_current_version_id( 51 );
	$out = TWD_SK_REST::post_structure( twd_sk_rest_req( array( 'id' => 51, 'base_version' => $ver, 'op' => 'phone_picture', 'index' => 1, 'picture' => 301 ) ) );
	twd_sk_true( ! is_wp_error( $out ), is_wp_error( $out ) ? $out->get_error_message() : '' );
	twd_sk_eq( 1, $out['at'], 'it reloads at the same section' );
	twd_sk_has( 'twd-sk-phone-img', TWD_SK_Store::get_current( 51 ) );
	twd_sk_eq( 'Added a phone picture to a picture band', TWD_SK_Store::list_versions( 51 )[0]['note'] );
	$undo = TWD_SK_Store::undo( 51 );
	twd_sk_true( ! is_wp_error( $undo ) );
	twd_sk_eq( twd_sk_pp_page(), TWD_SK_Store::get_current( 51 ), 'Undo puts the band back exactly' );
	$stale = TWD_SK_REST::post_structure( twd_sk_rest_req( array( 'id' => 51, 'base_version' => $ver, 'op' => 'phone_picture', 'index' => 1, 'picture' => 301 ) ) );
	twd_sk_true( is_wp_error( $stale ), 'a stale page is refused' );
	$cur  = TWD_SK_Store::get_current_version_id( 51 );
	$gone = TWD_SK_REST::post_structure( twd_sk_rest_req( array( 'id' => 51, 'base_version' => $cur, 'op' => 'phone_picture', 'index' => 1, 'remove' => true ) ) );
	twd_sk_true( is_wp_error( $gone ), 'removing what is not there is refused' );
} );

twd_sk_test( 'phone picture: two shared registry classes, the Look choices keep the mark, and the hidden picture is lazy so it is never downloaded', function () {
	$shared = TWD_SK_Registry::shared_classes();
	foreach ( array( 'twd-sk-phone-img', 'twd-sk-has-phone' ) as $c ) {
		twd_sk_true( in_array( $c, $shared, true ), $c );
	}
	$band = array( 'twd-sk-image-text', 'twd-sk-image-text--band', 'twd-sk-has-phone' );
	$now  = TWD_SK_Looks::classes_for( 'image_text', $band, null, array( 'band_shape' => 'twd-sk-shape-wide' ) );
	twd_sk_true( in_array( 'twd-sk-has-phone', $now, true ) && in_array( 'twd-sk-shape-wide', $now, true ), 'changing a Look choice keeps the phone picture mark' );
	$tag = TWD_SK_Images::tag( '<img class="twd-sk-image-text__img twd-sk-phone-img" src="https://example.test/wp-content/uploads/a.jpg" alt="x">', false );
	twd_sk_has( 'loading="lazy"', $tag, 'a picture below the first section is lazy, so a browser that hides it never fetches it' );
} );

twd_sk_test( 'phone picture css and Look panel: the phone picture is hidden except on phones, the main one is hidden on phones only when there is one, no viewport-width units, and the Look panel offers Choose, Change and Remove through the structure route', function () {
	$src  = file_get_contents( ABSPATH . 'tools/kit.src.css' );
	$tail = substr( $src, strpos( $src, '/* Picture band phone picture (12 Nov 2026)' ) );
	twd_sk_true( strpos( $src, '/* Picture band phone picture (12 Nov 2026)' ) > strpos( $src, '/* Picture band shape and boxed (12 Nov 2026)' ), 'after the band rules, so it wins' );
	twd_sk_has( '.twd-sk-image-text__img.twd-sk-phone-img { display: none; }', $tail, 'hidden everywhere by default' );
	twd_sk_has( '@media (max-width: 768px) {', $tail, 'the kit\'s own phone width' );
	twd_sk_has( '.twd-sk-has-phone .twd-sk-image-text__img { display: none; }', $tail, 'the main picture steps aside on a phone, only when the band has a phone picture' );
	twd_sk_has( '.twd-sk-has-phone .twd-sk-image-text__img.twd-sk-phone-img { display: block;', $tail );
	twd_sk_has( '.twd-sk-has-phone.twd-sk-image-text--band { height: auto; }', $tail, 'the band takes the phone picture\'s own shape' );
	twd_sk_hasnt( 'vw', $tail, 'no viewport-width units' );
	$css = file_get_contents( ABSPATH . 'assets/twd-site-kit.css' );
	twd_sk_has( 'twd-sk-has-phone', $css );
	$js = str_replace( "\r", '', file_get_contents( ABSPATH . 'assets/twd-site-kit-inline.js' ) );
	twd_sk_has( "if (classes.indexOf('twd-sk-image-text--band') > -1) {\n\t\t\tdrawPhonePicture(sec, classes);", $js, 'only on a picture band' );
	twd_sk_has( "ED.button(has ? 'Change the phone picture' : 'Choose a phone picture', 'secondary'", $js );
	twd_sk_has( "ED.button('Remove the phone picture', 'secondary'", $js );
	twd_sk_has( "var body = { op: 'phone_picture', index: parseInt(sec.getAttribute('data-twd-sk-sec'), 10) };", $js, 'saved through the same route as Add and Move' );
	twd_sk_has( "window.wp.media({ title: 'Choose the picture for phones'", $js, 'the media library picks it' );
	$rest = file_get_contents( ABSPATH . 'includes/class-twd-sk-rest.php' );
	twd_sk_has( "'phone_picture' === \$op", $rest );
} );
