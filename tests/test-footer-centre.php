<?php
// The compact footer centred on phones (13 Nov 2026, from the pilot site's phone screenshot): a Site tab tick box that adds one class
// to the compact footer; on a phone the logo, button, links and small print are centred. Off by default: every footer prints the same
// bytes as before until it is on. Uses the helpers in test-chrome.php.

twd_sk_test( 'footer centre: off by default, no footer prints the class, and the setting is a plain on or off', function () {
	twd_sk_chrome_setup( twd_sk_chrome_profile( array( 'footer_text' => 'Quiet words.' ) ) );
	twd_sk_eq( false, TWD_SK_Chrome::effective()['footer_phone_center'] );
	foreach ( array( 'columns', 'band', 'centered', 'simple', 'feature', 'compact' ) as $layout ) {
		TWD_SK_Chrome::save_settings( array( 'footer_variant' => $layout ) );
		twd_sk_hasnt( 'phone-center', TWD_SK_Chrome::render_footer(), $layout );
	}
	twd_sk_eq( 0, count( TWD_SK_Chrome::validate( array( 'footer_phone_center' => true ) )['errors'] ) );
	twd_sk_eq( 0, count( TWD_SK_Chrome::validate( array( 'footer_phone_center' => '1' ) )['errors'] ) );
	twd_sk_eq( 1, count( TWD_SK_Chrome::validate( array( 'footer_phone_center' => 'maybe' ) )['errors'] ), 'anything else is refused' );
} );

twd_sk_test( 'footer centre: on, only the compact footer gets the class and the rest of its bytes are unchanged', function () {
	twd_sk_chrome_setup( twd_sk_chrome_profile( array( 'footer_text' => 'Quiet words.' ) ), array( 'footer_variant' => 'compact' ) );
	$off = TWD_SK_Chrome::render_footer();
	TWD_SK_Chrome::save_settings( array( 'footer_phone_center' => true ) );
	$on = TWD_SK_Chrome::render_footer();
	twd_sk_has( 'twd-sk-footer twd-sk-footer--compact twd-sk-footer--cols-1 twd-sk-footer--phone-center"', $on );
	twd_sk_eq( $off, str_replace( ' twd-sk-footer--phone-center', '', $on ), 'only the class differs' );
	TWD_SK_Chrome::save_settings( array( 'footer_variant' => 'feature' ) );
	twd_sk_hasnt( 'phone-center', TWD_SK_Chrome::render_footer(), 'the other layouts are left alone' );
} );

twd_sk_test( 'footer centre css: phones only, the links in one centred row, the small print centred, nothing above the phone width', function () {
	$src  = file_get_contents( ABSPATH . 'tools/kit.src.css' );
	$at   = strpos( $src, '/* The compact footer, centred on phones (13 Nov 2026)' );
	twd_sk_true( $at > strpos( $src, '/* Picture and text, small picture under the words on phones (12 Nov 2026)' ), 'at the end, so it wins' );
	$tail = str_replace( "\r", '', substr( $src, $at ) );
	twd_sk_has( "@media (max-width: 768px) {\n  .twd-sk-footer--phone-center .twd-sk-footer__row { align-items: center; text-align: center;", $tail );
	twd_sk_has( '.twd-sk-footer--phone-center .twd-sk-footer__links--row { display: flex; flex-wrap: wrap; justify-content: center;', $tail );
	twd_sk_has( '.twd-sk-footer--phone-center .twd-sk-footer__meta { justify-content: center;', $tail );
	twd_sk_hasnt( 'vw', $tail, 'no viewport-width units' );
	twd_sk_hasnt( '@media (min-width', $tail );
	twd_sk_has( 'twd-sk-footer--phone-center', file_get_contents( ABSPATH . 'assets/twd-site-kit.css' ) );
} );

twd_sk_test( 'footer centre: carried by the style snapshot, the site brief and the Site tab', function () {
	twd_sk_chrome_setup( twd_sk_chrome_profile(), array( 'footer_phone_center' => true ) );
	twd_sk_eq( true, TWD_SK_Brief::export()['footer']['footer_phone_center'] );
	twd_sk_has( "'footer_phone_center' => array( 'chrome', 'footer_phone_center'", file_get_contents( ABSPATH . 'includes/class-twd-sk-brief.php' ) );
	twd_sk_has( "'footer_phone_center' => \$c['footer_phone_center']", file_get_contents( ABSPATH . 'includes/class-twd-sk-style.php' ) );
	$js = file_get_contents( ABSPATH . 'assets/twd-site-kit-editor-site.js' );
	foreach ( array( "cui.footerCentre.checked = !!c.settings.footer_phone_center;", "footer_phone_center: cui.footerCentre.checked,", "cui.footerCentre = fcentre.input;", "fcentre.label," ) as $needle ) {
		twd_sk_has( $needle, $js, $needle );
	}
} );
