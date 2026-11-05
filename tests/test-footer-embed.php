<?php
// The footer from a saved embed (0.15.0): a Site tab choice, administrators who may add raw HTML only,
// printed outside the page wrapper, off in safe mode, and its links are checked by the launch check.

function twd_sk_fe_site() {
	$GLOBALS['twd_stub']['caps'] = true;
	TWD_SK_Profile::save( array( 'site_name' => 'Calm Rooms', 'email' => 'hello@example.com', 'phone' => '020 7946 0000', 'footer_text' => 'Kit footer words' ) );
	return TWD_SK_Embed::save( array( 'label' => 'Big footer', 'kind' => 'html', 'code' => '<footer class="fx"><a href="/about">About</a> <a href="/gone-page">Gone</a><a href="mailto:a@example.com">Mail</a><a href="#top">Top</a></footer>' ), 0, 1 );
}

twd_sk_test( 'footer embed: off by default, so the kit footer prints; chosen, the embed prints instead, outside the page wrapper', function () {
	$e = twd_sk_fe_site();
	twd_sk_eq( 0, TWD_SK_Chrome::settings()['footer_embed'] );
	twd_sk_has( 'Kit footer words', TWD_SK_Chrome::render_footer() );
	twd_sk_true( ! is_wp_error( TWD_SK_Chrome::save_settings( array( 'footer_embed' => $e['id'] ) ) ) );
	$html = TWD_SK_Chrome::render_footer();
	twd_sk_has( '<footer class="fx">', $html );
	twd_sk_hasnt( 'Kit footer words', $html, 'the kit footer is not printed as well' );
	twd_sk_hasnt( 'twd-sk-page', $html, 'outside the page wrapper, so it can be the full width of the window' );
	twd_sk_true( 0 === strpos( $html, '<div class="twd-sk-footer-embed">' ) );
	TWD_SK_Chrome::save_settings( array( 'footer_embed' => 0 ) );
	twd_sk_has( 'Kit footer words', TWD_SK_Chrome::render_footer() );
} );

twd_sk_test( 'footer embed: only an HTML embed that exists can be chosen', function () {
	$e = twd_sk_fe_site();
	$s = TWD_SK_Embed::save( array( 'label' => 'Booking', 'kind' => 'shortcode', 'code' => '[booking_calendar id="2"]' ), 0, 1 );
	twd_sk_is_error( 'twd_sk_bad_chrome', TWD_SK_Chrome::save_settings( array( 'footer_embed' => $s['id'] ) ), 'a shortcode embed is refused' );
	twd_sk_is_error( 'twd_sk_bad_chrome', TWD_SK_Chrome::save_settings( array( 'footer_embed' => 99 ) ), 'no such embed' );
	twd_sk_is_error( 'twd_sk_bad_chrome', TWD_SK_Chrome::save_settings( array( 'footer_embed' => 'x' ) ), 'not a number' );
	twd_sk_eq( array( $e['id'] => 'Big footer' ), TWD_SK_Chrome::footer_embed_choices(), 'only html embeds are offered' );
} );

twd_sk_test( 'footer embed: choosing one needs the right to add raw HTML; going back to the kit footer does not', function () {
	$e = twd_sk_fe_site();
	$GLOBALS['twd_stub']['caps'] = array( 'manage_options' );
	twd_sk_is_error( 'twd_sk_bad_chrome', TWD_SK_Chrome::save_settings( array( 'footer_embed' => $e['id'] ) ), 'no unfiltered_html' );
	$GLOBALS['twd_stub']['caps'] = true;
	TWD_SK_Chrome::save_settings( array( 'footer_embed' => $e['id'] ) );
	$GLOBALS['twd_stub']['caps'] = array( 'manage_options' );
	twd_sk_true( ! is_wp_error( TWD_SK_Chrome::save_settings( array( 'footer_embed' => 0 ) ) ), 'back to the kit footer' );
	$GLOBALS['twd_stub']['caps'] = true;
} );

twd_sk_test( 'footer embed: safe mode and a deleted embed both fall back to the kit footer', function () {
	$e = twd_sk_fe_site();
	TWD_SK_Chrome::save_settings( array( 'footer_embed' => $e['id'] ) );
	TWD_SK_Safe::set( true );
	twd_sk_hasnt( '<footer class="fx">', TWD_SK_Chrome::render_footer(), 'safe mode' );
	TWD_SK_Safe::set( false );
	TWD_SK_Embed::delete( $e['id'] );
	twd_sk_has( 'Kit footer words', TWD_SK_Chrome::render_footer(), 'deleted' );
	twd_sk_eq( 0, TWD_SK_Chrome::settings()['footer_embed'] );
} );

twd_sk_test( 'footer embed: the launch check reads the links in the embed (not mail, phone or in-page ones)', function () {
	$e     = twd_sk_fe_site();
	$links = TWD_SK_Launch::embed_links( $e ? TWD_SK_Embed::get( $e['id'] )['code'] : '' );
	twd_sk_eq( array( '/about', '/gone-page', 'mailto:a@example.com', '#top' ), array_column( $links, 'url' ) );
	twd_sk_eq( 'About', $links[0]['label'] );
	$m = TWD_SK_Launch::menu_links( $links );
	twd_sk_eq( 2, $m['checked'], 'only the two page links are checked' );
	twd_sk_eq( 2, count( $m['missing'] ), 'the stub site has no such pages, so both are named' );
} );
