<?php
// The footer's call to action (12 Nov 2026, from the pilot site's review): a tick box that shows the header button (its own text
// and link) as a button in the footer of every page, so a site can have one call to action at the end of every page instead of
// a section to keep on each. Off by default: every footer prints the same bytes as before until it is on. Uses the helpers in
// test-chrome.php.

twd_sk_test( 'footer button: off by default, every footer layout prints exactly what it printed before, and the setting is a plain on or off', function () {
	twd_sk_chrome_setup( twd_sk_chrome_profile() );
	twd_sk_eq( false, TWD_SK_Chrome::effective()['footer_button'] );
	foreach ( array( 'columns', 'band', 'centered', 'simple', 'feature', 'compact' ) as $layout ) {
		TWD_SK_Chrome::save_settings( array( 'footer_variant' => $layout ) );
		$html = TWD_SK_Chrome::render_footer();
		twd_sk_hasnt( 'twd-sk-footer__cta', $html, $layout );
	}
	twd_sk_eq( 0, count( TWD_SK_Chrome::validate( array( 'footer_button' => true ) )['errors'] ) );
	twd_sk_eq( 0, count( TWD_SK_Chrome::validate( array( 'footer_button' => '1' ) )['errors'] ) );
	twd_sk_eq( 1, count( TWD_SK_Chrome::validate( array( 'footer_button' => 'maybe' ) )['errors'] ), 'anything else is refused' );
} );

twd_sk_test( 'footer button: on, the header button text and link print as a button; in the compact footer on the logo row between the logo and the links, in every other layout at the top of the footer', function () {
	twd_sk_chrome_setup( twd_sk_chrome_profile( array( 'footer_text' => 'Quiet words.' ) ), array( 'footer_button' => true, 'footer_variant' => 'compact' ) );
	twd_sk_eq( true, TWD_SK_Chrome::effective()['footer_button'] );
	$html = TWD_SK_Chrome::render_footer();
	twd_sk_has( '<a class="twd-sk-btn twd-sk-btn--primary" href="/contact">Book a call</a>', $html );
	$tag   = strpos( $html, 'twd-sk-footer__tagline' );
	$row   = strpos( $html, 'twd-sk-footer__row' );
	$brand = strpos( $html, 'twd-sk-footer__brand' );
	$cta   = strpos( $html, '<div class="twd-sk-footer__cta twd-sk-footer__cta--row">' );
	$nav   = strpos( $html, '<nav aria-label="Footer">' );
	twd_sk_true( false !== $tag && false !== $row && false !== $brand && false !== $cta && false !== $nav && $tag < $row && $row < $brand && $brand < $cta && $cta < $nav, 'compact: tagline, then one row holding the logo, the button and the links, in that order' );
	twd_sk_eq( 1, substr_count( $html, '<div class="twd-sk-footer__cta' ), 'once' );
	twd_sk_hasnt( 'twd-sk-inner twd-sk-footer__cta', $html, 'no block of its own in the compact footer' );
	foreach ( array( 'columns', 'band', 'centered', 'simple', 'feature' ) as $layout ) {
		TWD_SK_Chrome::save_settings( array( 'footer_variant' => $layout ) );
		$out = TWD_SK_Chrome::render_footer();
		twd_sk_eq( 1, substr_count( $out, '<div class="twd-sk-inner twd-sk-footer__cta">' ), $layout . ' has one button' );
		twd_sk_hasnt( 'twd-sk-footer__cta--row', $out, $layout . ' has no row version' );
		twd_sk_true( 1 === preg_match( '/<footer class="twd-sk-footer [^>]*><div class="twd-sk-inner twd-sk-footer__cta">/', $out ), $layout . ': at the top of the footer' );
		twd_sk_true( '</footer></div>' === substr( $out, -15 ), $layout . ' still closes as it did' );
	}
} );

twd_sk_test( 'footer button: nothing when the header button has no text or no link, or is still a marker; the contact bar and the button live together', function () {
	foreach ( array( array( 'cta_label' => '' ), array( 'cta_url' => '' ), array( 'cta_label' => '[PLACEHOLDER: button]' ), array( 'cta_url' => '[PLACEHOLDER: link]' ) ) as $gap ) {
		twd_sk_chrome_setup( twd_sk_chrome_profile( $gap ), array( 'footer_button' => true, 'footer_variant' => 'compact' ) );
		$out = TWD_SK_Chrome::render_footer();
		twd_sk_hasnt( 'twd-sk-footer__cta', $out, json_encode( $gap ) );
	}
	twd_sk_chrome_setup( twd_sk_chrome_profile( array( 'phone' => '01234 567890', 'email' => 'hello@calm.example' ) ), array( 'footer_button' => true, 'contact_bar' => true, 'footer_variant' => 'compact' ) );
	$both = TWD_SK_Chrome::render_footer();
	twd_sk_has( 'twd-sk-footer__cta', $both );
	twd_sk_has( 'twd-sk-contactbar__link', $both );
	twd_sk_has( 'twd-sk-footer--contactbar', $both );
} );

twd_sk_test( 'footer button: carried by the style snapshot, the site brief (export and import) and the Site tab', function () {
	twd_sk_chrome_setup( twd_sk_chrome_profile(), array( 'footer_button' => true ) );
	$brief = TWD_SK_Brief::export();
	twd_sk_eq( true, $brief['footer']['footer_button'] );
	twd_sk_has( "'footer_button' => array( 'chrome', 'footer_button'", file_get_contents( ABSPATH . 'includes/class-twd-sk-brief.php' ) );
	twd_sk_has( "'footer_button'  => \$c['footer_button']", file_get_contents( ABSPATH . 'includes/class-twd-sk-style.php' ) );
	$js = file_get_contents( ABSPATH . 'assets/twd-site-kit-editor-site.js' );
	foreach ( array( "cui.footerBtn.checked = !!c.settings.footer_button;", "footer_button: cui.footerBtn.checked,", "cui.footerBtn = fbtn.input;", "fbtn.label," ) as $needle ) {
		twd_sk_has( $needle, $js, $needle );
	}
	twd_sk_has( 'twd-sk-footer__cta', file_get_contents( ABSPATH . 'tools/kit.src.css' ), 'the rule is in the stylesheet source' );
} );
