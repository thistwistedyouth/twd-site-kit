<?php
// Turn a picture-only custom section into a picture band (12 Nov 2026, from the pilot site): a custom section has no Look choices,
// so a banner made that way could not have its height changed. One button in the Look panel now turns a custom section that
// holds only a picture into the kit's picture band (which has Band height, Picture position and Slow zoom), keeping the picture
// and its description. Anything else is refused with the reason. Uses the helpers in test-custom.php.

function twd_sk_tb_css() {
	return ".c-explore { background: var(--twd-site-color-surface); }\n.c-explore__bg { border-radius: var(--twd-site-radius-image); }\n.c-explore__inner { padding: var(--twd-site-card-pad); }\n.c-explore__title { color: var(--twd-site-color-heading); }\n";
}
function twd_sk_tb_section( $src = 'https://example.test/wp-content/uploads/2026/10/road-1024x677.jpg' ) {
	return '<section class="twd-sk-custom c-explore twd-sk-tone-surface"><img class="c-explore__bg" src="' . $src . '" alt="A desert road" data-twd-pic="Background photo"></section>';
}

twd_sk_test( 'to band: a custom section holding only a picture becomes the picture band, keeping the picture and its description; a resized library address goes back to full size', function () {
	twd_stub_reset();
	twd_sk_custom_page();
	TWD_SK_Css::save( twd_sk_tb_css(), 'x', 51 );
	$sec  = TWD_SK_Sanitizer::clean( twd_sk_tb_section() );
	$band = TWD_SK_Structure::picture_band_from( $sec );
	twd_sk_eq( '<section class="twd-sk-image-text twd-sk-image-text--band"><img class="twd-sk-image-text__img" src="https://example.test/wp-content/uploads/2026/10/road-1024x677.jpg" alt="A desert road"></section>', $band, 'not a library picture: the address is kept as it is' );
	$GLOBALS['twd_stub']['media_urls']['https://example.test/wp-content/uploads/2026/10/road.jpg'] = 77;
	$full = TWD_SK_Structure::picture_band_from( $sec );
	twd_sk_has( 'src="https://example.test/wp-content/uploads/2026/10/road.jpg"', $full, 'a library picture goes back to its full size' );
	twd_sk_has( 'alt="A desert road"', $full );
	twd_sk_hasnt( 'data-twd-pic', $full );
	twd_sk_hasnt( 'c-explore', $full );
	twd_sk_eq( $full, TWD_SK_Sanitizer::clean( $full ), 'the band passes the sanitiser unchanged' );
	twd_sk_eq( 'image-text', TWD_SK_Sections::type_of( $full ), 'it reads as a picture and text section, which has the band choices' );
} );

twd_sk_test( 'to band: anything that is more than a picture is refused with the reason, and nothing is changed', function () {
	twd_stub_reset();
	twd_sk_custom_page();
	TWD_SK_Css::save( twd_sk_tb_css(), 'x', 51 );
	$ok = 'twd_sk_bad_input';
	twd_sk_is_error( $ok, TWD_SK_Structure::picture_band_from( TWD_SK_Registry::get( 'text' )['skeleton'] ), 'a kit section' );
	$words = TWD_SK_Sanitizer::clean( '<section class="twd-sk-custom c-explore"><img class="c-explore__bg" src="/wp-content/uploads/a.jpg" alt="x"><div class="c-explore__inner"><h2 class="c-explore__title" data-twd-edit="Heading">Words here</h2></div></section>' );
	twd_sk_is_error( $ok, TWD_SK_Structure::picture_band_from( $words ), 'a custom section with words' );
	$two = TWD_SK_Sanitizer::clean( '<section class="twd-sk-custom c-explore"><img class="c-explore__bg" src="/wp-content/uploads/a.jpg" alt="x"><img class="c-explore__bg" src="/wp-content/uploads/b.jpg" alt="y"></section>' );
	twd_sk_is_error( $ok, TWD_SK_Structure::picture_band_from( $two ), 'two pictures' );
	$none = TWD_SK_Sanitizer::clean( '<section class="twd-sk-custom c-explore"><div class="c-explore__inner"></div></section>' );
	twd_sk_is_error( $ok, TWD_SK_Structure::picture_band_from( $none ), 'no picture' );
	$code = TWD_SK_Sanitizer::clean( '<section class="twd-sk-custom c-explore"><img class="c-explore__bg" src="/wp-content/uploads/a.jpg" alt="x">[twd_articles count="3"]</section>' );
	twd_sk_is_error( $ok, TWD_SK_Structure::picture_band_from( $code ), 'a shortcode counts as more than a picture' );
	$msg = TWD_SK_Structure::picture_band_from( $words );
	twd_sk_has( 'Replace this section', $msg->get_error_message(), 'the reason says what to do instead' );
} );

twd_sk_test( 'to band through the page route: one version with a plain note, the other sections untouched, Undo brings the custom section back, a stale page and a wrong number are refused', function () {
	twd_stub_reset();
	twd_sk_custom_page();
	TWD_SK_Css::save( twd_sk_tb_css(), 'x', 51 );
	$hero = TWD_SK_Registry::get( 'hero' )['skeleton'];
	$page = $hero . "\n" . twd_sk_tb_section() . "\n" . TWD_SK_Registry::get( 'text' )['skeleton'];
	twd_sk_true( ! is_wp_error( TWD_SK_Store::save( 51, $page ) ) );
	$ver = TWD_SK_Store::get_current_version_id( 51 );
	$out = TWD_SK_REST::post_structure( twd_sk_rest_req( array( 'id' => 51, 'base_version' => $ver, 'op' => 'to_band', 'index' => 1 ) ) );
	twd_sk_true( ! is_wp_error( $out ), is_wp_error( $out ) ? $out->get_error_message() : '' );
	twd_sk_eq( 1, $out['at'], 'it reloads at the same section' );
	$now = TWD_SK_Store::get_current( 51 );
	twd_sk_has( 'twd-sk-image-text--band', $now );
	twd_sk_hasnt( 'twd-sk-custom', $now );
	twd_sk_has( 'twd-sk-hero__title', $now, 'the page title is still there' );
	twd_sk_eq( 'Turned a custom section into a picture band', TWD_SK_Store::list_versions( 51 )[0]['note'] );
	// A band has the height, position and zoom choices a custom section lacks.
	$o = TWD_SK_Looks::options( 'image_text' );
	twd_sk_true( isset( $o['band_height'], $o['picture_position'], $o['slow_zoom'] ) );
	$undo = TWD_SK_Store::undo( 51 );
	twd_sk_true( ! is_wp_error( $undo ), is_wp_error( $undo ) ? $undo->get_error_message() : '' );
	twd_sk_has( 'c-explore', TWD_SK_Store::get_current( 51 ), 'Undo puts the custom section back' );
	$stale = TWD_SK_REST::post_structure( twd_sk_rest_req( array( 'id' => 51, 'base_version' => $ver, 'op' => 'to_band', 'index' => 1 ) ) );
	twd_sk_true( is_wp_error( $stale ), 'a stale page is refused' );
	$cur = TWD_SK_Store::get_current_version_id( 51 );
	$bad = TWD_SK_REST::post_structure( twd_sk_rest_req( array( 'id' => 51, 'base_version' => $cur, 'op' => 'to_band', 'index' => 9 ) ) );
	twd_sk_true( is_wp_error( $bad ), 'a number past the end is refused' );
	$hero_only = TWD_SK_REST::post_structure( twd_sk_rest_req( array( 'id' => 51, 'base_version' => $cur, 'op' => 'to_band', 'index' => 0 ) ) );
	twd_sk_true( is_wp_error( $hero_only ), 'a kit section is refused' );
} );

twd_sk_test( 'to band: the Look panel explains a picture-only custom section and offers the button, for administrators only, and the server is the one that decides', function () {
	$js = str_replace( "\r", '', file_get_contents( ABSPATH . 'assets/twd-site-kit-inline.js' ) );
	twd_sk_has( "return CUSTOM && !!sec && sec.classList.contains('twd-sk-custom') && !!sec.querySelector('[data-twd-sk-p]') && !sec.querySelector('[data-twd-sk-t]');", $js, 'only a custom section with a picture and no words, for the custom tools' );
	twd_sk_has( "ED.button('Turn into a picture band', 'primary'", $js );
	twd_sk_has( "structure({ op: 'to_band', index: parseInt(sec.getAttribute('data-twd-sk-sec'), 10) }, 'Turning it into a picture band...', 'Turned into a picture band.');", $js, 'saved through the same route as Add and Move, so it is one version that reloads at the section' );
	twd_sk_has( 'if (pictureOnlyCustom(sec)) {', $js );
	twd_sk_has( "status('This section has nothing to choose here.');", $js, 'any other section without choices says so as before' );
	$rest = file_get_contents( ABSPATH . 'includes/class-twd-sk-rest.php' );
	twd_sk_has( "'to_band' === \$op", $rest );
} );
