<?php
// The site header and footer: variants, profile use, safety, fallbacks.

function twd_sk_chrome_profile( $extra = array() ) {
	return array_merge( array(
		'site_name'    => 'Calm Practice',
		'menu'         => array(
			array( 'label' => 'Home', 'url' => '/', 'children' => array() ),
			array( 'label' => 'About', 'url' => '/about', 'children' => array() ),
			array( 'label' => 'Services', 'url' => '/services', 'children' => array(
				array( 'label' => 'Anxiety', 'url' => '/services/anxiety' ),
				array( 'label' => 'Grief', 'url' => '/services/grief' ),
			) ),
		),
		'cta_label'    => 'Book a call',
		'cta_url'      => '/contact',
		'phone'        => '01234 567890',
		'email'        => 'hello@calm.example',
		'address'      => array( '1 High Street' ),
		'footer_text'  => 'Gentle, practical therapy.',
		'legal'        => array( array( 'label' => 'Privacy', 'url' => '/privacy' ), array( 'label' => 'Terms', 'url' => '/terms' ) ),
		'registration' => array( 'Member of an example body' ),
	), $extra );
}
function twd_sk_chrome_setup( $profile = null, $settings = array() ) {
	TWD_SK_Profile::save( null === $profile ? twd_sk_chrome_profile() : $profile );
	if ( $settings ) {
		TWD_SK_Chrome::save_settings( $settings );
	}
}
function twd_sk_chrome_classes_in( $html ) {
	preg_match_all( '/class="([^"]*)"/', $html, $m );
	$out = array();
	foreach ( $m[1] as $list ) {
		foreach ( preg_split( '/\s+/', trim( $list ) ) as $c ) {
			$out[ $c ] = true;
		}
	}
	return array_keys( $out );
}

twd_sk_test( 'chrome: the shortcodes are registered by init (and init does not look at safe mode)', function () {
	TWD_SK_Safe::set( true );
	TWD_SK_Chrome::init();
	twd_sk_true( isset( $GLOBALS['twd_stub']['shortcodes']['twd_header'], $GLOBALS['twd_stub']['shortcodes']['twd_footer'] ) );
	$src = file_get_contents( ABSPATH . 'twd-site-kit.php' );
	$pos = strpos( $src, 'TWD_SK_Chrome::init()' );
	twd_sk_true( false !== $pos && $pos < strpos( $src, 'if ( ! TWD_SK_Safe::on() )' ), 'registered before, outside, the safe mode check' );
} );

twd_sk_test( 'chrome: four header and five footer variants, each with a label', function () {
	twd_sk_eq( array( 'bar', 'centered', 'split', 'minimal' ), array_keys( TWD_SK_Chrome::header_variants() ) );
	twd_sk_eq( array( 'columns', 'band', 'centered', 'simple', 'feature', 'compact' ), array_keys( TWD_SK_Chrome::footer_variants() ) );
	foreach ( array_merge( TWD_SK_Chrome::header_variants(), TWD_SK_Chrome::footer_variants() ) as $label ) {
		twd_sk_true( strlen( $label ) > 10 );
	}
} );

twd_sk_test( 'chrome: settings are validated, and an unknown variant, column count or field is refused', function () {
	twd_sk_eq( TWD_SK_Chrome::defaults(), TWD_SK_Chrome::settings() );
	twd_sk_true( ! is_wp_error( TWD_SK_Chrome::save_settings( array( 'header_variant' => 'split', 'footer_variant' => 'band', 'sticky' => true, 'show_strip' => true, 'show_button' => false, 'footer_columns' => 2 ) ) ) );
	$s = TWD_SK_Chrome::settings();
	twd_sk_eq( array( 'split', 'band', true, true, false, 2 ), array( $s['header_variant'], $s['footer_variant'], $s['sticky'], $s['show_strip'], $s['show_button'], $s['footer_columns'] ) );
	foreach ( array( array( 'header_variant' => 'wild' ), array( 'footer_variant' => 'x' ), array( 'footer_columns' => 4 ), array( 'footer_columns' => 0 ), array( 'footer_columns' => 'two' ), array( 'sticky' => 'yes' ), array( 'nope' => 1 ), array( 'header_variant' => array( 'bar' ) ) ) as $bad ) {
		twd_sk_is_error( 'twd_sk_bad_chrome', TWD_SK_Chrome::save_settings( $bad ), json_encode( $bad ) );
	}
	twd_sk_eq( $s, TWD_SK_Chrome::settings(), 'refused changes are not stored' );
	twd_sk_is_error( 'twd_sk_bad_chrome', TWD_SK_Chrome::save_settings( 'x' ) );
} );

twd_sk_test( 'chrome: the pack chooses the variant, a per-site override wins, and the shipped packs name valid ones', function () {
	foreach ( TWD_SK_Packs::packs() as $slug => $pack ) {
		twd_sk_true( isset( TWD_SK_Chrome::header_variants()[ $pack['chrome']['header'] ] ), $slug . ' header' );
		twd_sk_true( isset( TWD_SK_Chrome::footer_variants()[ $pack['chrome']['footer'] ] ), $slug . ' footer' );
	}
	$e = TWD_SK_Chrome::effective();
	twd_sk_eq( array( 'bar', 'columns' ), array( $e['header'], $e['footer'] ), 'sage' );
	TWD_SK_Packs::set_active( 'grove' );
	twd_sk_eq( 'band', TWD_SK_Chrome::effective()['footer'], 'grove' );
	TWD_SK_Chrome::save_settings( array( 'header_variant' => 'minimal', 'footer_variant' => 'simple' ) );
	$e = TWD_SK_Chrome::effective();
	twd_sk_eq( array( 'minimal', 'simple' ), array( $e['header'], $e['footer'] ), 'override wins' );
	TWD_SK_Chrome::save_settings( array( 'header_variant' => '', 'footer_variant' => '' ) );
	twd_sk_eq( 'band', TWD_SK_Chrome::effective()['footer'], 'cleared override falls back to the pack' );
} );

twd_sk_test( 'chrome: a pack file naming an unknown variant falls back to the defaults', function () {
	$dir  = sys_get_temp_dir() . '/twdsk-packchrome-' . getmypid();
	mkdir( $dir );
	$data = json_decode( file_get_contents( ABSPATH . 'packs/sage.json' ), true );
	$data['slug']   = 'tmpchrome';
	$data['chrome'] = array( 'header' => 'wild', 'footer' => array( 'x' ) );
	file_put_contents( $dir . '/tmpchrome.json', json_encode( $data ) );
	$pack = TWD_SK_Packs::read_pack_file( $dir . '/tmpchrome.json' );
	twd_sk_eq( array( 'header' => 'bar', 'footer' => 'columns' ), $pack['chrome'] );
	unset( $data['chrome'] );
	file_put_contents( $dir . '/tmpchrome.json', json_encode( $data ) );
	twd_sk_eq( array( 'header' => 'bar', 'footer' => 'columns' ), TWD_SK_Packs::read_pack_file( $dir . '/tmpchrome.json' )['chrome'] );
	unlink( $dir . '/tmpchrome.json' );
	rmdir( $dir );
} );

twd_sk_test( 'chrome: a sticky header is fixed only for the single-row variants, never the centred one', function () {
	foreach ( array( 'bar', 'split', 'minimal' ) as $v ) {
		TWD_SK_Chrome::save_settings( array( 'header_variant' => $v, 'sticky' => true ) );
		twd_sk_true( TWD_SK_Chrome::effective()['sticky'], $v );
	}
	TWD_SK_Chrome::save_settings( array( 'header_variant' => 'centered', 'sticky' => true ) );
	$e = TWD_SK_Chrome::effective();
	twd_sk_true( ! $e['sticky'] && $e['sticky_note'] );
	TWD_SK_Chrome::save_settings( array( 'header_variant' => 'bar', 'sticky' => false ) );
	twd_sk_true( ! TWD_SK_Chrome::effective()['sticky'], 'not sticky by default' );
	twd_sk_true( ! TWD_SK_Chrome::defaults()['sticky'] );
} );

twd_sk_test( 'chrome: shrink on scroll is off by default, needs the fixed header, and loads its tiny script only then', function () {
	twd_sk_true( ! TWD_SK_Chrome::defaults()['shrink'], 'off by default' );
	twd_sk_chrome_setup( null, array( 'sticky' => false, 'shrink' => true ) );
	twd_sk_true( ! TWD_SK_Chrome::effective()['shrink'], 'not fixed, so no shrink' );
	$GLOBALS['twd_stub']['scripts'] = array();
	$html = TWD_SK_Chrome::render_header();
	twd_sk_hasnt( 'twd-sk-header--shrink', $html );
	twd_sk_true( empty( $GLOBALS['twd_stub']['scripts']['twd-site-kit-header-shrink']['enqueued'] ), 'script not loaded' );
	twd_sk_chrome_setup( null, array( 'sticky' => true, 'shrink' => false ) );
	twd_sk_hasnt( 'twd-sk-header--shrink', TWD_SK_Chrome::render_header(), 'setting off, no class' );
	$GLOBALS['twd_stub']['scripts'] = array();
	twd_sk_chrome_setup( null, array( 'sticky' => true, 'shrink' => true ) );
	$html = TWD_SK_Chrome::render_header();
	twd_sk_has( 'twd-sk-header twd-sk-header--bar twd-sk-header--sticky twd-sk-header--shrink', $html );
	twd_sk_hasnt( 'twd-sk-header--shrunk', $html, 'the script, never the server, adds shrunk' );
	twd_sk_true( ! empty( $GLOBALS['twd_stub']['scripts']['twd-site-kit-header-shrink']['enqueued'] ), 'script loaded' );
	TWD_SK_Chrome::save_settings( array( 'header_variant' => 'centered' ) );
	twd_sk_true( ! TWD_SK_Chrome::effective()['shrink'], 'the centred header is never fixed, so never shrinks' );
	twd_sk_true( is_wp_error( TWD_SK_Chrome::save_settings( array( 'shrink' => 'maybe' ) ) ) );
	TWD_SK_Chrome::save_settings( array( 'header_variant' => 'bar', 'shrink' => false ) );
} );

twd_sk_test( 'chrome shrink script: tiny, vanilla, passive, with a gap between shrink and grow back', function () {
	$js = file_get_contents( ABSPATH . 'assets/twd-site-kit-header-shrink.js' );
	twd_sk_true( strlen( $js ) < 2000, 'tiny' );
	twd_sk_has( 'passive: true', $js );
	twd_sk_has( 'requestAnimationFrame', $js );
	twd_sk_has( 'var SHRINK_AT = 60;', $js );
	twd_sk_has( 'var GROW_AT = 20;', $js );
	twd_sk_hasnt( 'innerHTML', $js );
	twd_sk_hasnt( "\xE2\x80\x94", $js );
	if ( function_exists( 'shell_exec' ) && '' !== trim( (string) shell_exec( 'command -v node 2>/dev/null' ) ) ) {
		twd_sk_eq( '', trim( (string) shell_exec( 'node --check ' . escapeshellarg( ABSPATH . 'assets/twd-site-kit-header-shrink.js' ) . ' 2>&1' ) ) );
	}
	$raw = file_get_contents( ABSPATH . 'assets/twd-site-kit.css' );
	twd_sk_has( '--twd-sk-logo-h: var(--twd-site-logo-height-sm)', $raw );
	twd_sk_true( 1 === preg_match( '/twd-sk-header--shrink[^{]*twd-sk-header__inner[^{]*\{[^}]*transition/', $raw ), 'eased' );
	twd_sk_true( 1 === preg_match( '/prefers-reduced-motion: reduce\)\s*\{[^@]*twd-sk-header--shrink[^{]*\{[^}]*transition: none/', $raw ), 'no motion when asked' );
	twd_sk_true( 1 === preg_match( '/twd-sk-chrome--sticky\.twd-sk-chrome--sticky[^{]*\{[^}]*height: 76px/', $raw ), 'the spacer keeps its full height, so nothing shifts' );
} );

twd_sk_test( 'chrome header: structure, landmarks, menu button and submenu', function () {
	twd_sk_chrome_setup();
	$html = TWD_SK_Chrome::render_header();
	twd_sk_has( '<header class="twd-sk-header twd-sk-header--bar">', $html );
	twd_sk_has( 'class="twd-sk-page twd-sk-chrome"', $html );
	twd_sk_has( '<nav id="twd-sk-nav" class="twd-sk-header__nav" aria-label="Main">', $html );
	twd_sk_has( '<button type="button" class="twd-sk-header__toggle" aria-expanded="false" aria-controls="twd-sk-nav" hidden>', $html );
	twd_sk_has( 'twd-sk-visually-hidden">Menu</span>', $html );
	twd_sk_eq( 3, substr_count( $html, 'twd-sk-header__link"' ) );
	twd_sk_has( 'twd-sk-header__item twd-sk-header__item--parent', $html );
	twd_sk_has( '<ul class="twd-sk-header__sub">', $html );
	twd_sk_eq( 2, substr_count( $html, 'twd-sk-header__sublink"' ) );
	twd_sk_has( 'class="twd-sk-btn twd-sk-btn--primary twd-sk-header__cta" href="/contact">Book a call</a>', $html );
	twd_sk_has( '<span class="twd-sk-header__logo-text">Calm Practice</span>', $html, 'text-only logo fallback' );
	twd_sk_eq( 1, substr_count( $html, '<h1' ) + 1, 'a header holds no h1' );
	twd_sk_hasnt( 'twd-sk-header__strip"', $html, 'no contact strip by default' );
} );

twd_sk_test( 'chrome header: nothing in the output can run script or carry inline styling', function () {
	foreach ( array( 'header', 'footer' ) as $which ) {
		twd_sk_chrome_setup( twd_sk_chrome_profile( array( 'site_name' => '<script>alert(1)</script>"><img src=x onerror=y>', 'footer_text' => '<b onclick="x()">bold</b>' ) ) );
		$html = 'header' === $which ? TWD_SK_Chrome::render_header() : TWD_SK_Chrome::render_footer();
		foreach ( array( '<script', ' onclick', ' onerror', ' onload', 'style=', 'javascript:', '<iframe', '<form', '<input' ) as $bad ) {
			twd_sk_hasnt( $bad, $html, $which );
		}
	}
} );

twd_sk_test( 'chrome header: text and addresses are escaped (a quote in a name cannot break out of an attribute)', function () {
	twd_sk_chrome_setup( twd_sk_chrome_profile( array( 'site_name' => 'Tom & "Jerry" <Co>', 'menu' => array( array( 'label' => 'A & B', 'url' => '/a?x=1&y=2', 'children' => array() ) ) ) ) );
	$html = TWD_SK_Chrome::render_header();
	twd_sk_has( 'Tom &amp; &quot;Jerry&quot;<', $html );
	twd_sk_has( 'A &amp; B', $html );
	twd_sk_has( 'href="/a?x=1&amp;y=2"', $html );
	twd_sk_hasnt( '<Co>', $html );
} );

twd_sk_test( 'chrome header: the page being viewed is marked with aria-current', function () {
	twd_sk_chrome_setup();
	$_SERVER['REQUEST_URI'] = '/about/?utm=1';
	$html = TWD_SK_Chrome::render_header();
	twd_sk_eq( 1, substr_count( $html, 'aria-current="page"' ) );
	twd_sk_has( 'href="/about" aria-current="page">About', $html );
	$_SERVER['REQUEST_URI'] = '/';
	$html = TWD_SK_Chrome::render_header();
	twd_sk_has( 'href="/" aria-current="page">Home', $html );
	twd_sk_eq( 1, substr_count( $html, 'aria-current="page"' ) );
	unset( $_SERVER['REQUEST_URI'] );
} );

twd_sk_test( 'chrome header: an image logo carries width, height and the site name as alt; no logo gives text', function () {
	$GLOBALS['twd_stub']['attachments'][50] = array( 'url' => '/u/logo.png', 'w' => 200, 'h' => 80, 'is_image' => true );
	twd_sk_chrome_setup( twd_sk_chrome_profile( array( 'logo_id' => 50 ) ) );
	$html = TWD_SK_Chrome::render_header();
	twd_sk_has( '<img class="twd-sk-header__logo-img" src="/u/logo.png" alt="Calm Practice" width="200" height="80" decoding="async">', $html );
	twd_sk_hasnt( 'loading="lazy"', $html, 'a header logo is above the fold' );
	twd_sk_hasnt( 'twd-sk-header__logo-text', $html );
} );

twd_sk_test( 'chrome header: the optional button and contact strip', function () {
	twd_sk_chrome_setup( null, array( 'show_button' => false, 'show_strip' => true ) );
	$html = TWD_SK_Chrome::render_header();
	twd_sk_hasnt( 'twd-sk-header__cta', $html );
	twd_sk_has( 'twd-sk-header__strip"', $html );
	twd_sk_has( 'href="tel:01234567890"', $html );
	twd_sk_has( 'href="mailto:hello@calm.example"', $html );
	twd_sk_hasnt( 'twd-sk-chrome--strip', $html, 'strip class only when sticky' );
	TWD_SK_Chrome::save_settings( array( 'sticky' => true ) );
	$html = TWD_SK_Chrome::render_header();
	twd_sk_has( 'twd-sk-chrome twd-sk-chrome--sticky twd-sk-chrome--strip', $html );
	twd_sk_has( 'twd-sk-header twd-sk-header--bar twd-sk-header--sticky', $html );
	twd_sk_chrome_setup( twd_sk_chrome_profile( array( 'phone' => '', 'email' => '' ) ), array( 'show_strip' => true ) );
	twd_sk_hasnt( 'twd-sk-header__strip"', TWD_SK_Chrome::render_header(), 'no strip with nothing to show' );
} );

twd_sk_test( 'chrome header: every variant renders with its own class', function () {
	twd_sk_chrome_setup();
	foreach ( array_keys( TWD_SK_Chrome::header_variants() ) as $v ) {
		TWD_SK_Chrome::save_settings( array( 'header_variant' => $v ) );
		twd_sk_has( 'twd-sk-header twd-sk-header--' . $v, TWD_SK_Chrome::render_header(), $v );
	}
} );

twd_sk_test( 'chrome header: the menu script is enqueued only when the full header prints, not for the plain one', function () {
	twd_sk_chrome_setup();
	TWD_SK_Chrome::render_header();
	twd_sk_true( ! empty( $GLOBALS['twd_stub']['scripts']['twd-site-kit-header']['enqueued'] ) );
	$GLOBALS['twd_stub']['scripts'] = array();
	TWD_SK_Chrome::render_minimal_header();
	twd_sk_eq( array(), $GLOBALS['twd_stub']['scripts'] );
} );

twd_sk_test( 'chrome footer: columns, band, centred, simple, feature and compact', function () {
	twd_sk_chrome_setup();
	foreach ( array( 'columns', 'band', 'centered', 'simple', 'feature', 'compact' ) as $v ) {
		TWD_SK_Chrome::save_settings( array( 'footer_variant' => $v ) );
		$html = TWD_SK_Chrome::render_footer();
		twd_sk_has( 'twd-sk-footer twd-sk-footer--' . $v, $html, $v );
		twd_sk_has( '<footer ', $html );
	}
	TWD_SK_Chrome::save_settings( array( 'footer_variant' => 'band' ) );
	twd_sk_has( 'twd-sk-tone-band', TWD_SK_Chrome::render_footer() );
	TWD_SK_Chrome::save_settings( array( 'footer_variant' => 'columns' ) );
	twd_sk_hasnt( 'twd-sk-tone-band', TWD_SK_Chrome::render_footer() );
	TWD_SK_Chrome::save_settings( array( 'footer_variant' => 'simple' ) );
	$simple = TWD_SK_Chrome::render_footer();
	twd_sk_hasnt( 'twd-sk-footer__main', $simple );
	twd_sk_has( 'twd-sk-footer__bottom', $simple );
	TWD_SK_Chrome::save_settings( array( 'footer_variant' => 'centered', 'footer_columns' => 3 ) );
	twd_sk_has( 'twd-sk-footer--cols-1', TWD_SK_Chrome::render_footer() );
} );

twd_sk_test( 'chrome footer: one, two and three columns', function () {
	twd_sk_chrome_setup();
	foreach ( array( 1, 2, 3 ) as $n ) {
		TWD_SK_Chrome::save_settings( array( 'footer_variant' => 'columns', 'footer_columns' => $n ) );
		$html = TWD_SK_Chrome::render_footer();
		twd_sk_has( 'twd-sk-footer--cols-' . $n, $html );
		twd_sk_eq( $n, substr_count( $html, 'class="twd-sk-footer__col"' ), $n . ' columns' );
	}
	// With little to show, the column count never exceeds the content.
	twd_sk_chrome_setup( twd_sk_chrome_profile( array( 'menu' => array(), 'phone' => '', 'email' => '', 'address' => array() ) ), array( 'footer_columns' => 3 ) );
	$html = TWD_SK_Chrome::render_footer();
	twd_sk_eq( 1, substr_count( $html, 'class="twd-sk-footer__col"' ) );
	twd_sk_has( 'twd-sk-footer--cols-1', $html );
} );

twd_sk_test( 'chrome footer: legal links are always shown, registration lines come from the client, the year is printed by PHP', function () {
	twd_sk_chrome_setup();
	$html = TWD_SK_Chrome::render_footer();
	twd_sk_has( '<nav aria-label="Legal"><ul class="twd-sk-footer__legal">', $html );
	twd_sk_has( 'href="/privacy">Privacy</a>', $html );
	twd_sk_has( 'href="/terms">Terms</a>', $html );
	twd_sk_has( 'twd-sk-footer__reg-line">Member of an example body</p>', $html );
	twd_sk_has( '&copy; ' . gmdate( 'Y' ) . ' Calm Practice', $html );
	// No legal links stored: the row is still there.
	twd_sk_chrome_setup( twd_sk_chrome_profile( array( 'legal' => array() ) ) );
	$html = TWD_SK_Chrome::render_footer();
	twd_sk_has( 'twd-sk-footer__legal', $html );
	twd_sk_has( 'Privacy policy', $html );
	twd_sk_hasnt( 'twd-sk-footer__legal-link', $html, 'no link when WordPress has no privacy page' );
	$GLOBALS['twd_stub']['privacy_url'] = 'https://example.test/privacy-policy/';
	twd_sk_has( 'twd-sk-footer__legal-link" href="https://example.test/privacy-policy/">Privacy policy', TWD_SK_Chrome::render_footer(), 'links to the WordPress privacy page when there is one' );
	// No registration lines: nothing is invented.
	twd_sk_chrome_setup( twd_sk_chrome_profile( array( 'registration' => array() ) ) );
	twd_sk_hasnt( 'twd-sk-footer__reg', TWD_SK_Chrome::render_footer() );
} );

twd_sk_test( 'chrome footer: a phone number with no digits (a starter placeholder) is text, not a broken link', function () {
	twd_sk_chrome_setup( twd_sk_chrome_profile( array( 'phone' => 'PHONE_NUMBER' ) ) );
	$html = TWD_SK_Chrome::render_footer();
	twd_sk_has( '<span>PHONE_NUMBER</span>', $html );
	twd_sk_hasnt( 'href="tel:"', $html );
	twd_sk_hasnt( 'href="tel:PHONE', $html );
} );

twd_sk_test( 'chrome: every class the header and footer print is a known class (so the stylesheet covers it)', function () {
	$known = array_merge( TWD_SK_Chrome::classes(), array( 'twd-sk-page', 'twd-sk-inner', 'twd-sk-btn', 'twd-sk-btn--primary', 'twd-sk-visually-hidden', 'twd-sk-tone-band' ) );
	twd_sk_chrome_setup( null, array( 'show_strip' => true, 'sticky' => true ) );
	foreach ( array_keys( TWD_SK_Chrome::header_variants() ) as $v ) {
		TWD_SK_Chrome::save_settings( array( 'header_variant' => $v ) );
		foreach ( twd_sk_chrome_classes_in( TWD_SK_Chrome::render_header() ) as $c ) {
			twd_sk_true( in_array( $c, $known, true ), 'header ' . $v . ' class: ' . $c );
		}
	}
	foreach ( array_keys( TWD_SK_Chrome::footer_variants() ) as $v ) {
		foreach ( array( 1, 2, 3 ) as $n ) {
			TWD_SK_Chrome::save_settings( array( 'footer_variant' => $v, 'footer_columns' => $n ) );
			foreach ( twd_sk_chrome_classes_in( TWD_SK_Chrome::render_footer() ) as $c ) {
				twd_sk_true( in_array( $c, $known, true ), 'footer ' . $v . ' class: ' . $c );
			}
		}
	}
} );

twd_sk_test( 'chrome fallback: in safe mode, with an empty profile, or when something fails, a plain header and footer print and no shortcode text shows', function () {
	twd_sk_chrome_setup();
	TWD_SK_Safe::set( true );
	$GLOBALS['twd_stub']['bloginfo']['name'] = 'Plain Site';
	foreach ( array( TWD_SK_Chrome::shortcode_header(), TWD_SK_Chrome::shortcode_footer() ) as $html ) {
		twd_sk_has( 'Plain Site', $html );
		twd_sk_hasnt( '[twd_', $html );
		twd_sk_hasnt( 'twd-sk-header__toggle', $html, 'no menu button, no script, in the plain version' );
	}
	twd_sk_has( 'twd-sk-header twd-sk-header--bar', TWD_SK_Chrome::shortcode_header() );
	twd_sk_has( '&copy; ' . gmdate( 'Y' ) . ' Plain Site', TWD_SK_Chrome::shortcode_footer() );
	TWD_SK_Safe::set( false );

	// An empty profile: the plain version too.
	$GLOBALS['twd_stub']['options'][ TWD_SK_Profile::OPTION ] = array();
	twd_sk_has( 'Plain Site', TWD_SK_Chrome::shortcode_header() );
	twd_sk_has( 'Plain Site', TWD_SK_Chrome::shortcode_footer() );

	// Something throws: still no fatal error, still a plain version.
	twd_sk_chrome_setup();
	$GLOBALS['twd_stub']['option_throws'] = true;
	twd_sk_has( 'Plain Site', TWD_SK_Chrome::shortcode_header(), 'header: no fatal, plain version' );
	twd_sk_has( 'Plain Site', TWD_SK_Chrome::shortcode_footer(), 'footer: no fatal, plain version' );
	$GLOBALS['twd_stub']['option_throws'] = false;
} );

twd_sk_test( 'chrome fallback: the plain header lists a few published pages and links them', function () {
	TWD_SK_Safe::set( true );
	twd_stub_add_post( 5, 'page', 'x' );
	get_post( 5 )->post_title = 'About us';
	$html = TWD_SK_Chrome::render_minimal_header();
	twd_sk_has( 'About us', $html );
	twd_sk_has( 'href="https://example.test/?page_id=5"', $html );
} );

twd_sk_test( 'chrome: no inline handlers, no scripts, no network, no em dashes, no client details in the class', function () {
	$src = file_get_contents( ABSPATH . 'includes/class-twd-sk-chrome.php' );
	foreach ( array( 'onclick', 'onload', '<script', 'wp_remote_', 'file_put_contents', 'eval(', 'style="' ) as $bad ) {
		twd_sk_hasnt( $bad, $src );
	}
	twd_sk_hasnt( "\xE2\x80\x94", $src );
} );

twd_sk_test( 'header js: no inline handlers or code injection, Escape closes, aria-expanded is kept, with a no-script fallback', function () {
	$js = file_get_contents( ABSPATH . 'assets/twd-site-kit-header.js' );
	foreach ( array( 'eval(', 'new Function', 'document.write', 'innerHTML', 'outerHTML', 'insertAdjacentHTML', 'XMLHttpRequest', 'fetch(', 'localStorage', 'sendBeacon', 'onclick' ) as $bad ) {
		twd_sk_hasnt( $bad, $js );
	}
	twd_sk_true( 1 !== preg_match( '#https?://#i', $js ) );
	twd_sk_has( "e.key === 'Escape'", $js );
	twd_sk_has( "setAttribute('aria-expanded'", $js );
	twd_sk_has( "toggle.removeAttribute('hidden')", $js );
	twd_sk_has( 'twd-sk-header--js', $js );
	twd_sk_has( 'focusout', $js );
	twd_sk_hasnt( "\xE2\x80\x94", $js );
	if ( function_exists( 'shell_exec' ) && '' !== trim( (string) shell_exec( 'command -v node 2>/dev/null' ) ) ) {
		twd_sk_eq( '', trim( (string) shell_exec( 'node --check ' . escapeshellarg( ABSPATH . 'assets/twd-site-kit-header.js' ) . ' 2>&1' ) ) );
	}
} );

twd_sk_test( 'chrome css: the collapsed menu needs the script (no script, the menu simply shows), the toggle is 48px, text is 14px or more', function () {
	$raw = file_get_contents( ABSPATH . 'assets/twd-site-kit.css' );
	twd_sk_true( 1 === preg_match( '/twd-sk-header--js\.twd-sk-header--js[^{]*twd-sk-header__panel[^{]*\{[^}]*display: none/', $raw ), 'collapsing needs the js class' );
	twd_sk_true( 1 === preg_match( '/twd-sk-header__toggle\.twd-sk-header__toggle\s*\{[^}]*display: none/', $raw ), 'toggle hidden until the script runs' );
	twd_sk_true( 1 === preg_match( '/twd-sk-header__toggle\.twd-sk-header__toggle\s*\{[^}]*min-height: 48px/', $raw ), '48px menu button' );
	twd_sk_true( 1 === preg_match( '/twd-sk-header--sticky\.twd-sk-header--sticky\s*\{[^}]*position: fixed/', $raw ) && false !== strpos( $raw, '--wp-admin--admin-bar--height' ), 'sticky is fixed and clears the admin bar' );
} );

// -- The Theme Builder templates ----------------------------------------------------------

twd_sk_test( 'elementor: each template is one full-width container holding exactly one shortcode widget', function () {
	foreach ( array( 'header' => '[twd_header]', 'footer' => '[twd_footer]' ) as $type => $code ) {
		$t = 'header' === $type ? TWD_SK_Elementor::header_template() : TWD_SK_Elementor::footer_template();
		twd_sk_eq( $type, $t['type'] );
		twd_sk_eq( '0.4', $t['version'] );
		twd_sk_eq( array(), $t['page_settings'] );
		twd_sk_eq( 1, count( $t['content'] ) );
		$c = $t['content'][0];
		twd_sk_eq( 'container', $c['elType'] );
		twd_sk_eq( 'full', $c['settings']['content_width'] );
		twd_sk_eq( '0', $c['settings']['padding']['top'] );
		twd_sk_eq( 1, count( $c['elements'] ) );
		twd_sk_eq( 'widget', $c['elements'][0]['elType'] );
		twd_sk_eq( 'shortcode', $c['elements'][0]['widgetType'] );
		twd_sk_eq( $code, $c['elements'][0]['settings']['shortcode'] );
		twd_sk_true( 1 === preg_match( '/^[a-f0-9]{7}$/', $c['id'] ) && 1 === preg_match( '/^[a-f0-9]{7}$/', $c['elements'][0]['id'] ) && $c['id'] !== $c['elements'][0]['id'] );
	}
	$ids = array( TWD_SK_Elementor::header_template()['content'][0]['id'], TWD_SK_Elementor::footer_template()['content'][0]['id'] );
	twd_sk_true( $ids[0] !== $ids[1], 'ids differ between the two templates' );
} );

twd_sk_test( 'elementor: the committed JSON files are exactly what the generator builds, are valid JSON, and hold no client details', function () {
	foreach ( TWD_SK_Elementor::files() as $name => $json ) {
		$path = ABSPATH . 'starters/elementor/' . $name;
		twd_sk_true( file_exists( $path ), $name . ' exists: run php bin/build-elementor-templates.php' );
		twd_sk_eq( $json, file_get_contents( $path ), $name . ' matches the generator' );
		twd_sk_true( is_array( json_decode( $json, true ) ), $name . ' is valid JSON' );
		twd_sk_hasnt( "\xE2\x80\x94", $json );
		twd_sk_true( 1 !== preg_match( '#https?://#', $json ), 'no web addresses' );
	}
	twd_sk_eq( 2, count( glob( ABSPATH . 'starters/elementor/*.json' ) ), 'only the two templates' );
} );

twd_sk_test( 'chrome: a missing site name uses the WordPress site title and still shows the menu and the footer details', function () {
	twd_sk_chrome_setup( twd_sk_chrome_profile( array( 'site_name' => '', 'footer_text' => 'Gentle therapy.', 'menu' => array( array( 'label' => 'About', 'url' => '/about', 'children' => array() ) ) ) ) );
	$GLOBALS['twd_stub']['bloginfo']['name'] = 'Title From WordPress';
	$h = TWD_SK_Chrome::render_header();
	twd_sk_has( 'Title From WordPress', $h );
	twd_sk_has( 'twd-sk-header__toggle', $h, 'the full header, not the plain one' );
	twd_sk_has( 'href="/about"', $h, 'the typed menu, not a list of pages' );
	$f = TWD_SK_Chrome::render_footer();
	twd_sk_has( 'Gentle therapy.', $f, 'the full footer' );
	twd_sk_has( 'Title From WordPress', $f );
} );

// -- Placement on the Hello theme (0.9.1) ------------------------------------

function twd_sk_chrome_print( $which ) {
	TWD_SK_Chrome::reset_placed();
	ob_start();
	'header' === $which ? TWD_SK_Chrome::print_header() : TWD_SK_Chrome::print_footer();
	return ob_get_clean();
}

twd_sk_test( 'placement: Site Kit places them by default, and the setting only takes kit or elementor', function () {
	twd_sk_eq( 'kit', TWD_SK_Chrome::defaults()['placement'] );
	twd_sk_eq( array( 'kit', 'elementor' ), array_keys( TWD_SK_Chrome::placements() ) );
	twd_sk_true( ! is_wp_error( TWD_SK_Chrome::save_settings( array( 'placement' => 'elementor' ) ) ) );
	twd_sk_eq( 'elementor', TWD_SK_Chrome::settings()['placement'] );
	foreach ( array( 'theme', '', 1, true, array( 'kit' ), null ) as $bad ) {
		twd_sk_is_error( 'twd_sk_bad_chrome', TWD_SK_Chrome::save_settings( array( 'placement' => $bad ) ), json_encode( $bad ) );
	}
	twd_sk_eq( 'elementor', TWD_SK_Chrome::settings()['placement'], 'refused values are not stored' );
	$GLOBALS['twd_stub']['options'][ TWD_SK_Chrome::OPTION ] = array( 'placement' => 'wild' );
	twd_sk_eq( 'kit', TWD_SK_Chrome::settings()['placement'], 'a bad stored value falls back to the default' );
} );

twd_sk_test( 'placement: init hooks Hello\'s header filter, the body open action and the footer action', function () {
	TWD_SK_Chrome::init();
	$hooks = $GLOBALS['twd_stub']['hooks'];
	twd_sk_true( isset( $hooks['hello_elementor_header_footer'], $hooks['wp_body_open'], $hooks['get_footer'] ) );
} );

twd_sk_test( 'placement: on Hello, Hello\'s own header and footer are switched off and Site Kit prints its own once each', function () {
	twd_sk_chrome_setup();
	$GLOBALS['twd_stub']['admin'] = false;
	twd_sk_true( false === TWD_SK_Chrome::filter_hello( true ) );
	twd_sk_true( TWD_SK_Chrome::places_itself() );
	$h = twd_sk_chrome_print( 'header' );
	twd_sk_has( 'twd-sk-header', $h );
	twd_sk_true( 0 === strpos( $h, '<div class="twd-sk-page twd-sk-chrome"><header class="twd-sk-header twd-sk-header--' ), 'inside the styled wrapper' );
	twd_sk_true( strpos( $h, 'class="twd-sk-skip" href="#content"' ) < strpos( $h, 'twd-sk-header__logo' ), 'the skip link comes before the logo and menu' );
	twd_sk_has( 'twd-sk-footer', twd_sk_chrome_print( 'footer' ) );
	// A second call on the same request prints nothing.
	TWD_SK_Chrome::reset_placed();
	ob_start();
	TWD_SK_Chrome::print_header();
	TWD_SK_Chrome::print_footer();
	twd_sk_true( '' !== ob_get_clean() );
	ob_start();
	TWD_SK_Chrome::print_header();
	TWD_SK_Chrome::print_footer();
	twd_sk_eq( '', ob_get_clean() );
} );

twd_sk_test( 'placement: the shortcodes for an Elementor template never carry the skip link (Hello prints its own there)', function () {
	twd_sk_chrome_setup();
	twd_sk_hasnt( 'twd-sk-skip', TWD_SK_Chrome::shortcode_header() );
	twd_sk_hasnt( 'twd-sk-skip', TWD_SK_Chrome::render_minimal_header() );
	twd_sk_has( 'twd-sk-skip', TWD_SK_Chrome::render_minimal_header( true ) );
} );

twd_sk_test( 'placement: Site Kit stands aside in admin, on another theme, when set to elementor, and on the blank Canvas template', function () {
	twd_sk_chrome_setup();
	twd_sk_eq( '', twd_sk_chrome_print( 'header' ), 'admin screens' );
	$GLOBALS['twd_stub']['admin'] = false;

	$GLOBALS['twd_stub']['template'] = 'twentytwentyfive';
	twd_sk_eq( '', twd_sk_chrome_print( 'header' ), 'another theme' );
	twd_sk_eq( '', twd_sk_chrome_print( 'footer' ) );
	twd_sk_true( true === TWD_SK_Chrome::filter_hello( true ), 'the filter passes the value through' );
	$GLOBALS['twd_stub']['template'] = 'hello-elementor';

	TWD_SK_Chrome::save_settings( array( 'placement' => 'elementor' ) );
	twd_sk_eq( '', twd_sk_chrome_print( 'header' ), 'left to Elementor' );
	twd_sk_true( true === TWD_SK_Chrome::filter_hello( true ), 'Hello keeps its own header' );
	TWD_SK_Chrome::save_settings( array( 'placement' => 'kit' ) );

	twd_stub_add_post( 31, 'page' );
	$GLOBALS['twd_stub']['queried'] = 31;
	update_post_meta( 31, '_wp_page_template', 'elementor_canvas' );
	twd_sk_eq( '', twd_sk_chrome_print( 'header' ), 'Canvas pages have no header by design' );
	twd_sk_eq( '', twd_sk_chrome_print( 'footer' ) );
	update_post_meta( 31, '_wp_page_template', 'elementor_header_footer' );
	twd_sk_has( 'twd-sk-header', twd_sk_chrome_print( 'header' ), 'Elementor Full Width keeps the header' );
} );

twd_sk_test( 'placement: an Elementor Pro header or footer on this page wins, each on its own, and a fault there never breaks the page', function () {
	twd_sk_chrome_setup();
	$GLOBALS['twd_stub']['admin'] = false;
	$GLOBALS['twd_stub']['elementor_location'] = array( 'header' => true );
	twd_sk_eq( '', twd_sk_chrome_print( 'header' ) );
	twd_sk_has( 'twd-sk-footer', twd_sk_chrome_print( 'footer' ), 'no footer template, so Site Kit prints the footer' );
	$GLOBALS['twd_stub']['elementor_location'] = array( 'header' => 'throw', 'footer' => true );
	twd_sk_has( 'twd-sk-header', twd_sk_chrome_print( 'header' ) );
	twd_sk_eq( '', twd_sk_chrome_print( 'footer' ) );
} );

twd_sk_test( 'placement: safe mode brings Hello\'s own header and footer back and Site Kit prints nothing', function () {
	twd_sk_chrome_setup();
	$GLOBALS['twd_stub']['admin'] = false;
	TWD_SK_Safe::set( true );
	twd_sk_true( true === TWD_SK_Chrome::filter_hello( true ) );
	twd_sk_eq( '', twd_sk_chrome_print( 'header' ) );
	twd_sk_eq( '', twd_sk_chrome_print( 'footer' ) );
	twd_sk_has( 'safe mode', TWD_SK_Chrome::placement_status() );
} );

twd_sk_test( 'placement: the status line says plainly who places them', function () {
	twd_sk_eq( 'Site Kit, on the Hello theme', TWD_SK_Chrome::placement_status() );
	$GLOBALS['twd_stub']['template'] = 'astra';
	twd_sk_has( 'not Hello', TWD_SK_Chrome::placement_status() );
	TWD_SK_Chrome::save_settings( array( 'placement' => 'elementor' ) );
	twd_sk_has( 'Site Kit stands aside', TWD_SK_Chrome::placement_status() );
} );

twd_sk_test( 'placement: if the settings cannot be read, nothing throws, Site Kit stands aside and Hello keeps its own', function () {
	twd_sk_chrome_setup();
	$GLOBALS['twd_stub']['admin'] = false;
	$GLOBALS['twd_stub']['option_throws'] = true;
	twd_sk_eq( '', twd_sk_chrome_print( 'header' ) );
	twd_sk_eq( '', twd_sk_chrome_print( 'footer' ) );
	twd_sk_true( true === TWD_SK_Chrome::filter_hello( true ) );
	twd_sk_has( 'unknown', TWD_SK_Chrome::placement_status() );
} );

twd_sk_test( 'placement: the site report, the build report and the launch check all say who places them', function () {
	twd_sk_has( 'Header and footer placed by: Site Kit, on the Hello theme', implode( "\n", TWD_SK_Doctor::style() ) );
	$items = TWD_SK_Launch::check()['items'];
	$chrome = array_values( array_filter( $items, function ( $i ) {
		return 'chrome' === $i['id'];
	} ) );
	twd_sk_eq( 1, count( $chrome ) );
	twd_sk_eq( 'ok', $chrome[0]['status'] );
	$GLOBALS['twd_stub']['template'] = 'astra';
	$chrome = array_values( array_filter( TWD_SK_Launch::check()['items'], function ( $i ) {
		return 'chrome' === $i['id'];
	} ) );
	twd_sk_eq( 'warn', $chrome[0]['status'] );
	twd_sk_has( 'Hello', $chrome[0]['detail'] );
} );

// -- 0.14.0: the front page logo and the small mark -------------------------------------------

twd_sk_test( 'chrome front logo (0.14.0): off by default; on, the front page gets the larger stacked logo and the mark, other pages the mark and the name', function () {
	twd_sk_true( ! TWD_SK_Chrome::defaults()['front_logo'], 'off by default' );
	$GLOBALS['twd_stub']['attachments'][50] = array( 'url' => '/u/logo.png', 'w' => 200, 'h' => 80, 'is_image' => true );
	$GLOBALS['twd_stub']['attachments'][51] = array( 'url' => '/u/mark.png', 'w' => 60, 'h' => 60, 'is_image' => true );
	// Off: one logo everywhere, nothing new in the markup even on the front page.
	twd_sk_chrome_setup( twd_sk_chrome_profile( array( 'logo_id' => 50, 'logo_mark_id' => 51 ) ), array( 'front_logo' => false, 'sticky' => true ) );
	$GLOBALS['twd_stub']['front'] = true;
	$html = TWD_SK_Chrome::render_header();
	twd_sk_hasnt( 'twd-sk-header--front', $html );
	twd_sk_hasnt( 'twd-sk-header__logo-mark', $html, 'the mark is only used by the front page option' );
	twd_sk_has( '<img class="twd-sk-header__logo-img" src="/u/logo.png"', $html );
	// On, front page: the --front class, the mark (hidden until shrunk) then the full logo.
	TWD_SK_Chrome::save_settings( array( 'front_logo' => true ) );
	$html = TWD_SK_Chrome::render_header();
	twd_sk_has( ' twd-sk-header--front', $html );
	twd_sk_has( 'twd-sk-chrome--sticky twd-sk-chrome--front', $html, 'the spacer knows the header is taller' );
	twd_sk_true( strpos( $html, 'twd-sk-header__logo-mark' ) < strpos( $html, 'twd-sk-header__logo-img' ), 'mark first, then the full logo' );
	twd_sk_has( '<img class="twd-sk-header__logo-mark" src="/u/mark.png" alt=""', $html );
	// 0.14.1: the site name comes after the full logo, for the shrunk header (the stylesheet hides it until then).
	twd_sk_has( '<span class="twd-sk-header__logo-text twd-sk-header__logo-name">Calm Practice</span>', $html );
	twd_sk_true( strpos( $html, 'twd-sk-header__logo-img' ) < strpos( $html, 'twd-sk-header__logo-name' ), 'name after the full logo, so the sibling rule can hide the logo' );
	// On, another page: the mark and the site name as text, no full logo.
	$GLOBALS['twd_stub']['front'] = false;
	$html = TWD_SK_Chrome::render_header();
	twd_sk_hasnt( 'twd-sk-header--front', $html );
	twd_sk_has( '<img class="twd-sk-header__logo-mark" src="/u/mark.png"', $html );
	twd_sk_has( '<span class="twd-sk-header__logo-text">Calm Practice</span>', $html );
	twd_sk_hasnt( 'twd-sk-header__logo-img', $html );
	// On, no mark set: the full logo everywhere, larger on the front page only.
	twd_sk_chrome_setup( twd_sk_chrome_profile( array( 'logo_id' => 50, 'logo_mark_id' => 0 ) ), array( 'front_logo' => true, 'sticky' => false ) );
	twd_sk_has( '<img class="twd-sk-header__logo-img" src="/u/logo.png"', TWD_SK_Chrome::render_header() );
	twd_sk_hasnt( 'twd-sk-header__logo-mark', TWD_SK_Chrome::render_header() );
	$GLOBALS['twd_stub']['front'] = true;
	$html = TWD_SK_Chrome::render_header();
	twd_sk_has( ' twd-sk-header--front', $html );
	twd_sk_hasnt( 'twd-sk-chrome--front', $html, 'not sticky, so no taller spacer' );
	// A text-only site (no logo) never gets the front class.
	twd_sk_chrome_setup( twd_sk_chrome_profile( array( 'logo_id' => 0 ) ), array( 'front_logo' => true ) );
	twd_sk_hasnt( 'twd-sk-header--front', TWD_SK_Chrome::render_header() );
	$GLOBALS['twd_stub']['front'] = false;
	twd_sk_true( is_wp_error( TWD_SK_Chrome::save_settings( array( 'front_logo' => 'big' ) ) ) );
	TWD_SK_Chrome::save_settings( array( 'front_logo' => false, 'sticky' => false ) );
} );

twd_sk_test( 'chrome front logo css: larger logo and a column layout on the front page, a row again once shrunk, the mark swapped in, phones use the small size, reduced motion respected', function () {
	$raw = file_get_contents( ABSPATH . 'assets/twd-site-kit.css' );
	twd_sk_true( 1 === preg_match( '/twd-sk-header--front\.twd-sk-header--front \.twd-sk-header__inner\.twd-sk-header__inner \{[^}]*flex-direction: column/', $raw ), 'front page stacks the logo above the menu' );
	twd_sk_true( 1 === preg_match( '/twd-sk-header--front\.twd-sk-header--front \.twd-sk-header__logo-img\.twd-sk-header__logo-img \{[^}]*max-height: var\(--twd-site-logo-height-front\)/', $raw ), 'the front page logo height is a token' );
	twd_sk_true( 1 === preg_match( '/twd-sk-header--front\.twd-sk-header--front\.twd-sk-header--shrunk\.twd-sk-header--shrunk \.twd-sk-header__inner\.twd-sk-header__inner \{[^}]*flex-direction: row/', $raw ), 'shrunk, it is a row again' );
	twd_sk_true( 1 === preg_match( '/twd-sk-header--shrunk\.twd-sk-header--shrunk \.twd-sk-header__logo-mark\.twd-sk-header__logo-mark ~ \.twd-sk-header__logo-img\.twd-sk-header__logo-img \{[^}]*display: none/', $raw ), 'shrunk, the mark replaces the full logo' );
	twd_sk_true( 1 === preg_match( '/twd-sk-header--front\.twd-sk-header--front \.twd-sk-header__logo-name\.twd-sk-header__logo-name \{[^}]*display: none/', $raw ), '0.14.1: the name beside the mark is hidden on the front page until shrunk' );
	twd_sk_true( 1 === preg_match( '/twd-sk-header--front\.twd-sk-header--front\.twd-sk-header--shrunk\.twd-sk-header--shrunk \.twd-sk-header__logo-name\.twd-sk-header__logo-name \{[^}]*display: block/', $raw ), '0.14.1: and shown once shrunk' );
	twd_sk_true( 1 === preg_match( '/twd-sk-chrome--sticky\.twd-sk-chrome--sticky\.twd-sk-chrome--front\.twd-sk-chrome--front \{[^}]*height: calc\(var\(--twd-site-logo-height-front\)/', $raw ), 'the spacer grows with the logo' );
	twd_sk_true( 1 === preg_match( '/prefers-reduced-motion: reduce\)\s*\{[^@]*twd-sk-header__logo-mark[^{]*\{[^}]*transition: none/', $raw ), 'no motion when asked' );
	twd_sk_true( 1 === preg_match( '/@media \(max-width: 900px\)[^@]*twd-sk-header__logo-img\.twd-sk-header__logo-img \{[^}]*logo-height-sm/', $raw ), 'phones use the small logo height' );
} );

twd_sk_test( 'chrome front logo: in the site brief as header.front_logo, in the export, in the site report, and the logo mark picture slot reaches the profile', function () {
	twd_sk_true( array_key_exists( 'front_logo', TWD_SK_Brief::export()['header'] ) );
	$plan = TWD_SK_Brief::plan( array( 'schema' => 'twd-site-brief/1', 'header' => array( 'front_logo' => true ) ), false );
	twd_sk_eq( array( 'front_logo' => true ), $plan['changes']['chrome'] );
	twd_sk_true( isset( TWD_SK_Pictures::slots()['logo_mark'] ) );
	twd_sk_has( 'bigger logo on the front page', TWD_SK_Doctor::report() );
	twd_sk_true( is_string( TWD_SK_Profile::validate( array( 'logo_mark_id' => 'x' ) )['errors'][0] ), 'a bad mark id is refused' );
} );

twd_sk_test( 'chrome (0.14.0): one skip link: Hello\'s own is switched off through its filter only while Site Kit places the header', function () {
	twd_stub_reset();
	TWD_SK_Chrome::init();
	twd_sk_true( isset( $GLOBALS['twd_stub']['hooks']['hello_elementor_enable_skip_link'] ), 'the filter is hooked' );
	$GLOBALS['twd_stub']['template'] = 'hello-elementor';
	TWD_SK_Chrome::save_settings( array( 'placement' => 'kit' ) );
	twd_sk_eq( false, apply_filters( 'hello_elementor_enable_skip_link', true ), 'placing: Hello prints no skip link, ours is in the header' );
	twd_sk_has( 'class="twd-sk-skip" href="#content"', TWD_SK_Chrome::render_header( true ) );
	TWD_SK_Chrome::save_settings( array( 'placement' => 'elementor' ) );
	twd_sk_eq( true, apply_filters( 'hello_elementor_enable_skip_link', true ), 'standing aside: Hello keeps its own' );
	TWD_SK_Chrome::save_settings( array( 'placement' => 'kit' ) );
	TWD_SK_Safe::set( true );
	twd_sk_eq( true, apply_filters( 'hello_elementor_enable_skip_link', true ), 'safe mode: Hello keeps its own' );
	TWD_SK_Safe::set( false );
} );

// -- The feature footer (0.14.0) --------------------------------------------------------------

/** The pieces of a feature footer, cut at the markers the markup puts between them. */
function twd_sk_feature_parts( $html ) {
	$marker    = '<div class="twd-sk-footer__col">';
	$brand_at  = strpos( $html, $marker );
	$links_at  = strpos( $html, $marker, $brand_at + 1 );
	$bottom_at = strpos( $html, '<div class="twd-sk-footer__bottom">' );
	return array(
		'brand'  => substr( $html, $brand_at, ( false === $links_at ? $bottom_at : $links_at ) - $brand_at ),
		'links'  => false === $links_at ? '' : substr( $html, $links_at, $bottom_at - $links_at ),
		'bottom' => substr( $html, $bottom_at ),
	);
}

twd_sk_test( 'chrome footer feature: the logo large, the footer text as a heading, Contact with the email on the left, the page links in two columns with no label on the right, and the registration in the dark strip', function () {
	$GLOBALS['twd_stub']['attachments'][50] = array( 'url' => '/u/logo.png', 'w' => 600, 'h' => 200, 'is_image' => true );
	twd_sk_chrome_setup( twd_sk_chrome_profile( array( 'logo_id' => 50 ) ), array( 'footer_variant' => 'feature', 'footer_columns' => 3 ) );
	$html  = TWD_SK_Chrome::render_footer();
	$parts = twd_sk_feature_parts( $html );
	twd_sk_has( '<footer class="twd-sk-footer twd-sk-footer--feature twd-sk-footer--cols-2">', $html, 'two blocks, whatever the columns setting says' );
	twd_sk_hasnt( 'twd-sk-tone-band', $html, 'only the strip uses the band colour, through the stylesheet' );
	twd_sk_eq( 2, substr_count( $html, 'class="twd-sk-footer__col"' ), 'a left and a right block' );

	$b = $parts['brand'];
	twd_sk_has( '<img class="twd-sk-footer__logo-img" src="/u/logo.png" alt="Calm Practice"', $b );
	twd_sk_eq( 'large', $GLOBALS['twd_stub']['image_size_asked'][50], 'a bigger size of the picture, so a large logo stays sharp' );
	$order = array( '<img class="twd-sk-footer__logo-img"', '<p class="twd-sk-footer__tagline">Gentle, practical therapy.</p>', 'twd-sk-footer__contact-group', '<p class="twd-sk-footer__heading">Contact</p>', 'hello@calm.example', '01234 567890', '1 High Street' );
	$last  = -1;
	foreach ( $order as $needle ) {
		$at = strpos( $b, $needle );
		twd_sk_true( false !== $at && $at > $last, 'left block order: ' . $needle );
		$last = (int) $at;
	}
	twd_sk_hasnt( 'Member of an example body', $b, '0.15.0: the registration is no longer in the main area' );
	twd_sk_hasnt( 'twd-sk-footer__regs', $html );
	twd_sk_eq( 1, substr_count( $html, 'twd-sk-footer__heading' ), 'one small label, Contact, and nothing like Explore above the links' );
	twd_sk_hasnt( 'Explore', $html );

	$l = $parts['links'];
	twd_sk_has( '<nav aria-label="Footer"><ul class="twd-sk-footer__links twd-sk-footer__links--two">', $l );
	foreach ( array( 'Home', 'About', 'Services' ) as $label ) {
		twd_sk_has( '>' . $label . '</a>', $l );
	}
	twd_sk_hasnt( 'Anxiety', $l, 'the same top level links the columns footer shows' );
	twd_sk_hasnt( 'twd-sk-footer__heading', $l );

	$s = $parts['bottom'];
	twd_sk_has( '&copy; ' . gmdate( 'Y' ) . ' Calm Practice', $s );
	twd_sk_has( 'href="/privacy">Privacy</a>', $s );
	twd_sk_has( '<div class="twd-sk-footer__meta"><span class="twd-sk-footer__reg-line">Member of an example body</span></div>', $s, '0.15.0: the registration lines are in the strip' );
	$copy = strpos( $s, 'twd-sk-footer__copy' );
	$reg  = strpos( $s, 'twd-sk-footer__reg-line' );
	$legal = strpos( $s, 'twd-sk-footer__legal' );
	twd_sk_true( $copy < $reg && $reg < $legal, 'the strip reads: copyright, registration, legal links' );
	foreach ( array( 'hello@calm.example', '01234', 'Contact', '1 High Street' ) as $not ) {
		twd_sk_hasnt( $not, $s, 'the strip holds no contact details: ' . $not );
	}

	$known = array_merge( TWD_SK_Chrome::classes(), array( 'twd-sk-page', 'twd-sk-inner' ) );
	foreach ( twd_sk_chrome_classes_in( $html ) as $c ) {
		twd_sk_true( in_array( $c, $known, true ), 'class: ' . $c );
	}
} );

twd_sk_test( 'chrome footer feature: whatever was not supplied is left out, and nothing is invented', function () {
	twd_sk_chrome_setup( twd_sk_chrome_profile( array( 'logo_id' => 0, 'footer_text' => '', 'phone' => '', 'email' => '', 'address' => array(), 'registration' => array(), 'menu' => array() ) ), array( 'footer_variant' => 'feature' ) );
	$html = TWD_SK_Chrome::render_footer();
	twd_sk_has( 'twd-sk-footer--feature twd-sk-footer--cols-1', $html, 'no links, so one block' );
	twd_sk_has( '<p class="twd-sk-footer__name">Calm Practice</p>', $html, 'no logo: the name as text' );
	foreach ( array( 'twd-sk-footer__tagline', 'twd-sk-footer__contact-group', 'twd-sk-footer__reg-line', 'twd-sk-footer__meta', '<nav aria-label="Footer">', 'Contact' ) as $not ) {
		twd_sk_hasnt( $not, $html, $not );
	}
	twd_sk_eq( 1, substr_count( $html, 'class="twd-sk-footer__col"' ), 'one block' );
	twd_sk_has( 'twd-sk-footer__bottom', $html, 'the strip is always there' );
	// Only an email: Contact shows it, with no phone line and no address line.
	twd_sk_chrome_setup( twd_sk_chrome_profile( array( 'phone' => '', 'address' => array() ) ), array( 'footer_variant' => 'feature' ) );
	$brand = twd_sk_feature_parts( TWD_SK_Chrome::render_footer() )['brand'];
	twd_sk_has( 'Contact</p><p class="twd-sk-footer__contact"><a class="twd-sk-footer__link" href="mailto:hello@calm.example">hello@calm.example</a></p>', $brand );
	twd_sk_eq( 1, substr_count( $brand, 'class="twd-sk-footer__contact"' ) );
} );

twd_sk_test( 'chrome footer feature: the other four layouts print none of the feature pieces, and safe mode still prints the plain footer', function () {
	foreach ( array( 'columns', 'band', 'centered', 'simple' ) as $v ) {
		twd_sk_chrome_setup( null, array( 'footer_variant' => $v, 'footer_columns' => 3 ) );
		$html = TWD_SK_Chrome::render_footer();
		foreach ( array( 'twd-sk-footer--feature', 'twd-sk-footer__tagline', 'twd-sk-footer__contact-group', 'twd-sk-footer__regs', 'twd-sk-footer__links--two' ) as $not ) {
			twd_sk_hasnt( $not, $html, $v . ' has no ' . $not );
		}
	}
	twd_sk_chrome_setup( null, array( 'footer_variant' => 'columns' ) );
	twd_sk_has( '<p class="twd-sk-footer__heading">Explore</p>', TWD_SK_Chrome::render_footer(), 'the columns footer keeps its label' );
	twd_sk_has( '<div class="twd-sk-inner twd-sk-footer__reg">', TWD_SK_Chrome::render_footer(), 'and its registration row' );
	twd_sk_chrome_setup( null, array( 'footer_variant' => 'feature' ) );
	TWD_SK_Safe::set( true );
	$plain = TWD_SK_Chrome::shortcode_footer();
	TWD_SK_Safe::set( false );
	twd_sk_hasnt( 'twd-sk-footer--feature', $plain, 'safe mode prints the minimal footer' );
	twd_sk_has( 'twd-sk-footer--simple', $plain );
	TWD_SK_Chrome::save_settings( array( 'footer_variant' => '' ) );
} );

twd_sk_test( 'chrome footer feature: selectable in settings, in a pack file, in the site brief and in a style kit', function () {
	twd_sk_true( ! is_wp_error( TWD_SK_Chrome::save_settings( array( 'footer_variant' => 'feature' ) ) ) );
	twd_sk_eq( 'feature', TWD_SK_Chrome::effective()['footer'] );
	TWD_SK_Chrome::save_settings( array( 'footer_variant' => '' ) );
	$dir  = sys_get_temp_dir() . '/twdsk-packfeature-' . getmypid();
	mkdir( $dir );
	$data = json_decode( file_get_contents( ABSPATH . 'packs/sage.json' ), true );
	$data['slug']   = 'tmpfeature';
	$data['chrome'] = array( 'header' => 'bar', 'footer' => 'feature' );
	file_put_contents( $dir . '/tmpfeature.json', json_encode( $data ) );
	twd_sk_eq( array( 'header' => 'bar', 'footer' => 'feature' ), TWD_SK_Packs::read_pack_file( $dir . '/tmpfeature.json' )['chrome'] );
	unlink( $dir . '/tmpfeature.json' );
	rmdir( $dir );
	$plan = TWD_SK_Brief::plan( array( 'schema' => 'twd-site-brief/1', 'footer' => array( 'layout' => 'feature' ) ), false );
	twd_sk_eq( array( 'footer_variant' => 'feature' ), $plan['changes']['chrome'] );
	twd_sk_has( 'feature', TWD_SK_Brief::interview_prompt(), 'the interview prompt lists it' );
	$kit = TWD_SK_Style::read_kit( array( 'chrome' => array( 'footer' => 'feature' ) ) );
	twd_sk_true( ! is_wp_error( $kit ) );
	twd_sk_eq( 'feature', $kit['chrome']['footer_variant'] );
	$dry = TWD_SK_Style::apply( $kit, array( 'dry_run' => true ) );
	twd_sk_true( ! $dry['applied'] );
	twd_sk_has( 'footer variant', implode( ' ', $dry['changes'] ) );
	twd_sk_true( is_wp_error( TWD_SK_Chrome::save_settings( array( 'footer_variant' => 'features' ) ) ), 'a misspelt layout is still refused' );
} );

twd_sk_test( 'chrome footer feature css: only existing colour tokens, the band colour on the strip, the large logo from its own tokens, two link columns, stacked on small screens', function () {
	$raw     = file_get_contents( ABSPATH . 'assets/twd-site-kit.css' );
	$allowed = array( 'var(--twd-site-color-title)', 'var(--twd-site-color-accent-text)', 'var(--twd-site-color-band)', 'var(--twd-site-color-on-band)', 'var(--twd-site-color-accent-on-dark)', 'var(--twd-site-color-muted)' );
	$found   = 0;
	foreach ( twd_sk_css_rules() as $rule ) {
		if ( false === strpos( $rule['selectors'][0], 'twd-sk-footer--feature' ) ) {
			continue;
		}
		$found++;
		foreach ( $rule['decls'] as $d ) {
			if ( in_array( $d['prop'], array( 'color', 'background-color' ), true ) ) {
				twd_sk_true( in_array( $d['value'], $allowed, true ), 'no new colour: ' . $d['value'] );
			}
		}
	}
	twd_sk_true( $found >= 10, 'the feature rules are there' );
	twd_sk_true( 1 === preg_match( '/twd-sk-footer--feature\.twd-sk-footer--feature \.twd-sk-footer__bottom\.twd-sk-footer__bottom \{[^}]*background-color: var\(--twd-site-color-band\) !important/', $raw ), 'the strip is the band colour' );
	twd_sk_true( 1 === preg_match( '/twd-sk-footer--feature\.twd-sk-footer--feature \.twd-sk-footer__copy\.twd-sk-footer__copy,[^{]*\{[^}]*color: var\(--twd-site-color-on-band\)/', $raw ), 'lighter text for the copyright and the legal row' );
	twd_sk_true( 1 === preg_match( '/twd-sk-footer--feature\.twd-sk-footer--feature \.twd-sk-footer__legal-link\.twd-sk-footer__legal-link \{[^}]*color: var\(--twd-site-color-accent-on-dark\)/', $raw ), 'and the legal links' );
	twd_sk_true( 1 === preg_match( '/twd-sk-footer--feature\.twd-sk-footer--feature \.twd-sk-footer__logo-img\.twd-sk-footer__logo-img \{[^}]*height: var\(--twd-site-footer-logo-height-feature\)/', $raw ), 'the large logo height is a token' );
	twd_sk_true( 1 === preg_match( '/@media \(max-width: 900px\)[^@]*twd-sk-footer--feature\.twd-sk-footer--feature \.twd-sk-footer__logo-img\.twd-sk-footer__logo-img \{[^}]*height: var\(--twd-site-footer-logo-height-feature-sm\)/', $raw ), 'phones use the phone logo height' );
	twd_sk_true( 1 === preg_match( '/twd-sk-footer__links--two\.twd-sk-footer__links--two \{[^}]*columns: 140px 2/', $raw ), 'two columns when the width allows' );
	twd_sk_true( 1 === preg_match( '/@media \(max-width: 900px\)[^@]*twd-sk-footer--feature\.twd-sk-footer--feature \.twd-sk-footer__main\.twd-sk-footer__main \{[^}]*grid-template-columns: 1fr;/', $raw ), 'the blocks stack' );
	twd_sk_true( 1 === preg_match( '/@media \(max-width: 900px\)[^@]*twd-sk-footer--feature\.twd-sk-footer--feature \.twd-sk-footer__tagline\.twd-sk-footer__tagline \{[^}]*font-size: var\(--twd-site-size-h2-sm\)/', $raw ), 'the heading keeps a large step on phones' );
} );

twd_sk_test( 'chrome footer feature tokens: a large logo on desktop, 150px or more on phones, in every pack, editable, and the older packs get the defaults', function () {
	foreach ( TWD_SK_Packs::packs() as $slug => $pack ) {
		$t = $pack['tokens'];
		$d = (int) $t['footer-logo-height-feature'];
		twd_sk_true( $d >= 120 && $d <= 160, $slug . ' desktop logo ' . $d );
		twd_sk_true( (int) $t['footer-logo-height-feature-sm'] >= 150, $slug . ' phone logo' );
		// The strip: text and links on the band colour, and the page part above it on the surface colour.
		twd_sk_true( TWD_SK_Contrast::ratio( $t['color-on-band'], $t['color-band'] ) >= 4.5, $slug . ' strip text' );
		twd_sk_true( TWD_SK_Contrast::ratio( $t['color-accent-on-dark'], $t['color-band'] ) >= 4.5, $slug . ' strip links' );
		foreach ( array( 'color-title', 'color-body', 'color-accent-text' ) as $name ) {
			twd_sk_true( TWD_SK_Contrast::ratio( $t[ $name ], $t['color-surface'] ) >= 4.5, $slug . ' ' . $name . ' on the surface' );
		}
	}
	twd_sk_true( in_array( 'footer-logo-height-feature', TWD_SK_Site::editable_names(), true ) && in_array( 'footer-logo-height-feature-sm', TWD_SK_Site::editable_names(), true ), 'editable in the Site tab' );
	twd_sk_eq( '140px', TWD_SK_Packs::defaults()['footer-logo-height-feature'] );
	twd_sk_eq( '150px', TWD_SK_Packs::defaults()['footer-logo-height-feature-sm'] );
	// A site override that made the strip unreadable is refused by the same check as every other band colour.
	$tokens = TWD_SK_Packs::packs()['sage']['tokens'];
	$tokens['color-band'] = '#EEEEEE';
	$ids = array_map( function ( $b ) {
		return $b['id'];
	}, TWD_SK_Contrast::check( $tokens )['blocking'] );
	twd_sk_true( in_array( 'band_text', $ids, true ) && in_array( 'band_accent', $ids, true ) );
} );

// ---- The compact footer (0.14.1) ----

twd_sk_test( 'chrome footer compact: the tagline on top, the logo or name then the menu links, and one bar for the small print; no headings, no columns', function () {
	twd_sk_chrome_setup( twd_sk_chrome_profile( array( 'registration' => array( '[PLACEHOLDER: Q09 registration number]' ) ) ), array( 'footer_variant' => 'compact', 'footer_columns' => 3 ) );
	$html = TWD_SK_Chrome::render_footer();
	twd_sk_has( 'twd-sk-footer twd-sk-footer--compact twd-sk-footer--cols-1', $html );
	twd_sk_has( '<p class="twd-sk-footer__tagline">Gentle, practical therapy.</p>', $html );
	twd_sk_hasnt( 'twd-sk-footer__heading', $html, 'no Explore or Contact headings' );
	twd_sk_hasnt( 'Explore', $html );
	twd_sk_hasnt( 'twd-sk-footer__col', $html, 'no columns' );
	$order = array( 'twd-sk-footer__top', 'twd-sk-footer__row', 'twd-sk-footer__brand', 'twd-sk-footer__links--row', 'twd-sk-footer__bottom--bar' );
	$last  = -1;
	foreach ( $order as $c ) {
		$at = strpos( $html, $c );
		twd_sk_true( false !== $at && $at > $last, $c . ' comes after the one before it' );
		$last = (int) $at;
	}
	// The top level of the menu only, as links; the sub menu stays out of the footer.
	twd_sk_has( '<a class="twd-sk-footer__link" href="/about">About</a>', $html );
	twd_sk_has( '<a class="twd-sk-footer__link" href="/services">Services</a>', $html );
	twd_sk_hasnt( 'Anxiety', $html );
	// The bar: copyright, registration, phone, email, address, legal links.
	$bar = substr( $html, strpos( $html, 'twd-sk-footer__bottom--bar' ) );
	twd_sk_has( '&copy; ' . gmdate( 'Y' ) . ' Calm Practice', $bar );
	twd_sk_has( '<span class="twd-sk-footer__reg-line">[PLACEHOLDER: Q09 registration number]</span>', $bar, 'the registration marker is still visible, so the publish check still holds the page' );
	twd_sk_has( 'href="mailto:hello@calm.example"', $bar );
	twd_sk_has( 'href="tel:01234567890"', $bar );
	twd_sk_has( '1 High Street', $bar );
	twd_sk_has( '>Privacy</a>', $bar );
	twd_sk_hasnt( 'twd-sk-footer__reg"', $html, 'the separate registration block is not used' );
} );

twd_sk_test( 'chrome footer compact: with no tagline, no menu or no logo it still prints something sensible, and empty details leave nothing behind', function () {
	twd_sk_chrome_setup( twd_sk_chrome_profile( array( 'footer_text' => '', 'phone' => '', 'email' => '', 'address' => array(), 'registration' => array() ) ), array( 'footer_variant' => 'compact' ) );
	$html = TWD_SK_Chrome::render_footer();
	twd_sk_hasnt( 'twd-sk-footer__top', $html, 'no tagline, no top block' );
	twd_sk_hasnt( 'twd-sk-footer__tagline', $html );
	twd_sk_hasnt( 'twd-sk-footer__meta', $html, 'nothing for the small print but the copyright and legal links' );
	twd_sk_has( '<span class="twd-sk-footer__name">Calm Practice</span>', $html, 'the name stands in for a logo' );
	$GLOBALS['twd_stub']['attachments'][50] = array( 'url' => '/u/logo.png', 'w' => 200, 'h' => 80, 'is_image' => true );
	twd_sk_chrome_setup( twd_sk_chrome_profile( array( 'logo_id' => 50 ) ), array( 'footer_variant' => 'compact' ) );
	twd_sk_has( '<img class="twd-sk-footer__logo-img" src="/u/logo.png"', TWD_SK_Chrome::render_footer(), 'the logo, lazy-loaded' );
	twd_sk_has( 'loading="lazy"', TWD_SK_Chrome::render_footer() );
	// Safe mode and an empty profile still give the plain footer, never the compact one.
	TWD_SK_Safe::set( true );
	twd_sk_hasnt( 'twd-sk-footer--compact', TWD_SK_Chrome::render_footer() );
	TWD_SK_Safe::set( false );
	TWD_SK_Chrome::save_settings( array( 'footer_variant' => 'columns' ) );
} );

twd_sk_test( 'chrome footer compact css: a dark bar from the band colour, the links in two or more columns of three, the tagline centred, stacked on a phone; the ink pack prefers it', function () {
	$raw = file_get_contents( ABSPATH . 'assets/twd-site-kit.css' );
	twd_sk_true( 1 === preg_match( '/twd-sk-footer--compact\.twd-sk-footer--compact \.twd-sk-footer__bottom--bar\.twd-sk-footer__bottom--bar \{[^}]*background-color: var\(--twd-site-color-band\)/', $raw ), 'the bar uses the band colour' );
	twd_sk_true( 1 === preg_match( '/twd-sk-footer__links--row\.twd-sk-footer__links--row \{[^}]*grid-auto-flow: column;[^}]*grid-template-rows: repeat\(3, auto\)/', $raw ), 'links fill down in rows of three' );
	twd_sk_true( 1 === preg_match( '/twd-sk-footer--compact\.twd-sk-footer--compact \.twd-sk-footer__tagline\.twd-sk-footer__tagline \{[^}]*text-align: center/', $raw ), 'the tagline is centred, in the compact footer only' );
	preg_match_all( '/\.twd-sk-footer__tagline\.twd-sk-footer__tagline \{[^}]*\}/', $raw, $all );
	foreach ( $all[0] as $rule ) {
		twd_sk_true( false !== strpos( $raw, 'twd-sk-footer--compact.twd-sk-footer--compact ' . $rule ) || false === strpos( $rule, 'text-align' ), 'no unscoped tagline rule centres the feature footer heading' );
	}
	twd_sk_true( 1 === preg_match( '/@media \(max-width: 768px\) \{\s*\.twd-sk-page \.twd-sk-footer__row\.twd-sk-footer__row \{[^}]*flex-direction: column/s', $raw ), 'stacked on a phone' );
	twd_sk_eq( 'compact', TWD_SK_Packs::packs()['ink']['chrome']['footer'], 'the text-led pack prefers the compact footer' );
	foreach ( array( 'sage', 'grove', 'ember', 'midnight' ) as $slug ) {
		twd_sk_true( 'compact' !== TWD_SK_Packs::packs()[ $slug ]['chrome']['footer'], $slug . ' keeps its own footer' );
	}
} );

twd_sk_test( 'chrome footer feature css (0.15.0): the links sit on the footer text row (the left block lets its pieces take the grid rows), the registration is styled in the strip, and a phone stacks as before', function () {
	$raw = file_get_contents( ABSPATH . 'assets/twd-site-kit.css' );
	twd_sk_true( 1 === preg_match( '/twd-sk-footer--feature\.twd-sk-footer--feature \.twd-sk-footer__main\.twd-sk-footer__main > \.twd-sk-footer__col\.twd-sk-footer__col:first-child \{[^}]*display: contents/', $raw ), 'the left block is not a box of its own' );
	twd_sk_true( 1 === preg_match( '/twd-sk-footer__col\.twd-sk-footer__col \+ \.twd-sk-footer__col\.twd-sk-footer__col \{[^}]*grid-column: 2;[^}]*grid-row: 2 \/ span 2;[^}]*margin-top: calc\(28px \+ \(var\(--twd-site-size-h2\) \* var\(--twd-site-line-heading\) - var\(--twd-site-size-body\) \* var\(--twd-site-line-body\)\) \/ 2\)/', $raw ), 'the links start on the footer text row, lined up with its first line, from tokens' );
	twd_sk_true( 1 === preg_match( '/twd-sk-footer--feature\.twd-sk-footer--feature \.twd-sk-footer__reg-line\.twd-sk-footer__reg-line \{[^}]*color: var\(--twd-site-color-on-band\)/', $raw ), 'the registration is readable on the band colour' );
	twd_sk_hasnt( 'twd-sk-footer__regs', $raw, 'the old registration block is no longer styled' );
	// On a phone the left block is a box again and the links column returns to normal flow (the same stack as before).
	twd_sk_true( 1 === preg_match( '/@media \(max-width: 768px\) \{.*twd-sk-footer--feature\.twd-sk-footer--feature \.twd-sk-footer__main\.twd-sk-footer__main > \.twd-sk-footer__col\.twd-sk-footer__col:first-child \{[^}]*display: block/s', $raw ), 'phone: the left block is a box again' );
	twd_sk_true( 1 === preg_match( '/@media \(max-width: 768px\) \{.*twd-sk-footer__col\.twd-sk-footer__col \+ \.twd-sk-footer__col\.twd-sk-footer__col \{[^}]*grid-column: auto;[^}]*grid-row: auto;[^}]*margin-top: 0/s', $raw ), 'phone: the links column returns to normal flow' );
} );

twd_sk_test( 'chrome footer feature (0.15.0): the other layouts print the same bytes with or without a registration line change, and the compact footer still holds the registration in its bar', function () {
	foreach ( array( 'columns', 'band', 'centered', 'simple', 'compact' ) as $v ) {
		twd_sk_chrome_setup( twd_sk_chrome_profile( array( 'registration' => array( 'Member of an example body' ) ) ), array( 'footer_variant' => $v, 'footer_columns' => 2 ) );
		$html = TWD_SK_Chrome::render_footer();
		twd_sk_has( 'Member of an example body', $html, $v . ' still prints the registration' );
		twd_sk_hasnt( 'twd-sk-footer--feature', $html, $v . ' is not the feature footer' );
	}
	twd_sk_chrome_setup( null, array( 'footer_variant' => 'columns' ) );
	twd_sk_has( '<div class="twd-sk-inner twd-sk-footer__reg">', TWD_SK_Chrome::render_footer(), 'the columns footer keeps its own registration row, as before' );
	TWD_SK_Chrome::save_settings( array( 'footer_variant' => '' ) );
} );

twd_sk_test( 'chrome: the contact strip thickness is a token (default 8px, so nothing changes), the spacers follow it, and the header script measures the real height', function () {
	twd_sk_eq( 'length', TWD_SK_Packs::schema()['strip-padding'] );
	twd_sk_eq( '8px', TWD_SK_Packs::defaults()['strip-padding'] );
	foreach ( TWD_SK_Packs::packs() as $slug => $pack ) {
		twd_sk_eq( '8px', $pack['tokens']['strip-padding'], $slug );
	}
	$editable = array_filter( TWD_SK_Site::editable(), function ( $row ) {
		return 'strip-padding' === $row[1];
	} );
	twd_sk_eq( 1, count( $editable ), 'editable in the Site tab' );
	$css = file_get_contents( ABSPATH . 'assets/twd-site-kit.css' );
	twd_sk_has( "padding-top: var(--twd-site-strip-padding) !important;
  padding-bottom: var(--twd-site-strip-padding) !important;", $css );
	twd_sk_has( 'height: calc(96px + var(--twd-site-strip-padding) * 2);', $css );
	twd_sk_has( '--twd-sk-wm-strip: calc(20px + var(--twd-site-strip-padding) * 2);', $css );
	$js = file_get_contents( ABSPATH . 'assets/twd-site-kit-header.js' );
	twd_sk_has( "wrap.style.height = h + 'px';", $js );
	twd_sk_has( "header.classList.contains('twd-sk-header--shrunk')", $js );
	twd_sk_has( "document.fonts.ready", $js );
	twd_sk_true( strlen( $js ) < 4000, 'still small' );
} );

twd_sk_test( 'footer text (0.15.0): up to three lines with bold, italic and a safe link; everything else goes; an old one-line footer prints the same bytes; the first-line style is a choice', function () {
	// rich(): marks kept, scripts and attributes gone, b and i become strong and em, br is a line break, at most three lines.
	twd_sk_eq( 'Gentle, practical therapy.', TWD_SK_Profile::rich( 'Gentle, practical therapy.', 300 ), 'plain words unchanged' );
	twd_sk_eq( "Depth psychotherapy in London.\n<em>Explore</em> new ways forward.", TWD_SK_Profile::rich( "Depth psychotherapy in London.\r\n<i>Explore</i> new ways forward.", 300 ) );
	twd_sk_eq( "One\nTwo\nThree", TWD_SK_Profile::rich( "One<br>Two<br/>Three<br>Four\nFive", 300 ), 'three lines at most' );
	twd_sk_eq( '<strong>Bold</strong> and <a href="https://example.test/x">a link</a>', TWD_SK_Profile::rich( '<b onclick="x()">Bold</b> and <a href="https://example.test/x" target="_blank" style="color:red">a link</a><script>bad()</script>', 300 ) );
	twd_sk_eq( 'Click here', TWD_SK_Profile::rich( '<a href="javascript:alert(1)">Click here</a>', 300 ), 'an unsafe link loses its link' );
	twd_sk_eq( '<strong>Open and <em>nested</em></strong> then closed', TWD_SK_Profile::rich( '<strong>Open and <em>nested</strong> then closed', 300 ), 'marks are balanced' );
	twd_sk_eq( 'Tom &amp; Jerry &gt; co', TWD_SK_Profile::rich_html( 'Tom & Jerry > co' ), 'plain words escape like esc_html' );
	twd_sk_eq( 'Tom &amp;', TWD_SK_Profile::rich_html( 'Tom & <Co>' ), 'an unknown tag goes, as text() always did' );
	twd_sk_eq( 'A line<br><strong>B</strong> &amp; C', TWD_SK_Profile::rich_html( "A line\n<strong>B</strong> &amp; C" ) );
	twd_sk_eq( 'A line B and C', TWD_SK_Profile::rich_plain( "A line\n<strong>B</strong> and C" ) );
	twd_sk_true( mb_strlen( TWD_SK_Profile::rich( str_repeat( 'word ', 100 ), 300 ) ) <= 300, 'cut to the limit' );
	$check = TWD_SK_Profile::validate( array( 'footer_text' => "Line one\n<b>Line two</b>" ) );
	twd_sk_eq( "Line one\n<strong>Line two</strong>", $check['valid']['footer_text'] );
	// Every footer layout prints the lines with br and the marks, and nothing that can run.
	foreach ( array( 'columns', 'band', 'centered', 'feature', 'compact' ) as $layout ) {
		twd_sk_chrome_setup( twd_sk_chrome_profile( array( 'footer_text' => "Depth psychotherapy in London.\n<strong>Explore</strong> <a href=\"/about\">new ways</a><script>x()</script>" ) ), array( 'footer_variant' => $layout ) );
		$html = TWD_SK_Chrome::render_footer();
		twd_sk_has( 'Depth psychotherapy in London.<br><strong>Explore</strong> <a href="/about">new ways</a>', $html, $layout );
		twd_sk_hasnt( '<script', $html, $layout );
		twd_sk_hasnt( '--lead', $html, $layout . ' plain by default' );
	}
	// The old one-line footer prints the same bytes as before (no class, no br).
	twd_sk_chrome_setup( twd_sk_chrome_profile( array( 'footer_text' => 'Gentle, practical therapy.' ) ), array( 'footer_variant' => 'columns' ) );
	$before = TWD_SK_Chrome::render_footer();
	twd_sk_has( '<p class="twd-sk-footer__text">Gentle, practical therapy.</p>', $before );
	// The style choice: a setting, a class on the text, in the snapshot and the brief, styled from tokens.
	twd_sk_eq( 'plain', TWD_SK_Chrome::defaults()['footer_text_style'] );
	twd_sk_eq( array( 'footer_text_style' => 'lead' ), TWD_SK_Chrome::validate( array( 'footer_text_style' => 'lead' ) )['valid'] );
	twd_sk_true( count( TWD_SK_Chrome::validate( array( 'footer_text_style' => 'huge' ) )['errors'] ) === 1 );
	twd_sk_chrome_setup( twd_sk_chrome_profile( array( 'footer_text' => "Depth psychotherapy in London.\nExplore new ways forward." ) ), array( 'footer_text_style' => 'lead' ) );
	twd_sk_has( '<p class="twd-sk-footer__text twd-sk-footer__text--lead">Depth psychotherapy in London.<br>Explore new ways forward.</p>', TWD_SK_Chrome::render_footer() );
	twd_sk_chrome_setup( twd_sk_chrome_profile( array( 'footer_text' => "A\nB" ) ), array( 'footer_variant' => 'feature', 'footer_text_style' => 'lead' ) );
	twd_sk_has( 'twd-sk-footer__tagline twd-sk-footer__tagline--lead', TWD_SK_Chrome::render_footer() );
	twd_sk_eq( 'lead', TWD_SK_Chrome::effective()['text_style'] );
	twd_sk_true( isset( TWD_SK_Style::current()['chrome']['footer_text_style'] ) );
	twd_sk_true( in_array( 'twd-sk-footer__text--lead', TWD_SK_Chrome::classes(), true ) );
	$css = file_get_contents( ABSPATH . 'assets/twd-site-kit.css' );
	twd_sk_has( 'twd-sk-footer__text--lead::first-line', $css );
	twd_sk_has( 'twd-sk-footer__tagline--lead::first-line', $css );
	// The Site tab: a small toolbar above the box, the style choice, and a live preview that builds nodes (never innerHTML).
	$js = file_get_contents( ABSPATH . 'assets/twd-site-kit-editor-site.js' );
	foreach ( array( "'New line'", 'footer_text_style', 'function richNodes', 'function previewFooterText', 'cui.textStyle' ) as $needle ) {
		twd_sk_has( $needle, $js );
	}
} );

twd_sk_test( 'SK-017 (0.15.0): the split header lets its menu use the spare width with a tighter gap, and the Site tab says when six or more items would run out of room', function () {
	$css = file_get_contents( ABSPATH . 'assets/twd-site-kit.css' );
	twd_sk_true( 1 === preg_match( '/twd-sk-header--split\.twd-sk-header--split \.twd-sk-header__inner\.twd-sk-header__inner \{[^}]*grid-template-columns: minmax\(0, 1fr\) auto minmax\(0, 1fr\)/', $css ), 'the side columns may shrink and grow' );
	twd_sk_true( 1 === preg_match( '/twd-sk-header--split\.twd-sk-header--split \.twd-sk-header__menu\.twd-sk-header__menu \{[^}]*gap: calc\(var\(--twd-site-menu-gap\) \* 0\.6\)/', $css ), 'a tighter gap from the menu gap token' );
	$js = file_get_contents( ABSPATH . 'assets/twd-site-kit-editor-site.js' );
	twd_sk_has( 'function splitNote', $js );
	twd_sk_has( 'the bar header fits them', $js );
} );

twd_sk_test( 'Tier C ideas 7 and 10 (0.15.0): a phone contact bar from the site details and a Leave quickly link, both off by default (the footers print the same bytes), on through the chrome settings', function () {
	twd_sk_chrome_setup( twd_sk_chrome_profile( array( 'phone' => '01234 567890', 'email' => 'hello@calm.example' ) ) );
	$before = TWD_SK_Chrome::render_footer();
	twd_sk_hasnt( 'twd-sk-contactbar', $before );
	twd_sk_hasnt( 'twd-sk-exit', $before );
	twd_sk_eq( false, TWD_SK_Chrome::effective()['contact_bar'] );
	twd_sk_eq( false, TWD_SK_Chrome::effective()['exit'] );
	// The contact bar: Email and Call from the site details, inside the footer, and the footer marked to keep room.
	TWD_SK_Chrome::save_settings( array( 'contact_bar' => true ) );
	$html = TWD_SK_Chrome::render_footer();
	twd_sk_has( '<nav class="twd-sk-contactbar" aria-label="Contact"><a class="twd-sk-contactbar__link" href="mailto:hello@calm.example">Email</a><a class="twd-sk-contactbar__link" href="tel:01234567890">Call</a></nav></footer></div>', $html );
	twd_sk_has( '<footer class="twd-sk-footer twd-sk-footer--contactbar ', $html );
	foreach ( array( 'band', 'centered', 'simple', 'feature', 'compact' ) as $layout ) {
		TWD_SK_Chrome::save_settings( array( 'footer_variant' => $layout ) );
		twd_sk_has( 'twd-sk-contactbar__link', TWD_SK_Chrome::render_footer(), $layout );
	}
	// No real email or phone: no bar at all (and no room kept).
	twd_sk_chrome_setup( twd_sk_chrome_profile( array( 'phone' => 'PHONE_NUMBER', 'email' => 'you@example.com' ) ), array( 'contact_bar' => true ) );
	$none = TWD_SK_Chrome::render_footer();
	twd_sk_true( false === strpos( $none, 'twd-sk-contactbar' ) || false !== strpos( $none, 'mailto:you@example.com' ), 'sample details: checked next' );
	twd_sk_chrome_setup( twd_sk_chrome_profile( array( 'phone' => '', 'email' => '' ) ), array( 'contact_bar' => true ) );
	twd_sk_hasnt( 'twd-sk-contactbar', TWD_SK_Chrome::render_footer() );
	// Leave quickly: needs the switch and a full https address; anything else is refused or prints nothing.
	twd_sk_chrome_setup( twd_sk_chrome_profile(), array( 'quick_exit' => true ) );
	twd_sk_hasnt( 'twd-sk-exit', TWD_SK_Chrome::render_footer(), 'no address, no link' );
	twd_sk_true( count( TWD_SK_Chrome::validate( array( 'quick_exit_url' => 'http://example.test/' ) )['errors'] ) === 1, 'http is refused' );
	twd_sk_true( count( TWD_SK_Chrome::validate( array( 'quick_exit_url' => 'javascript:alert(1)' ) )['errors'] ) === 1 );
	twd_sk_eq( '', TWD_SK_Chrome::validate( array( 'quick_exit_url' => '  ' ) )['valid']['quick_exit_url'] );
	TWD_SK_Chrome::save_settings( array( 'quick_exit_url' => 'https://example.test/weather' ) );
	$exit = TWD_SK_Chrome::render_footer();
	twd_sk_has( '<a class="twd-sk-exit" href="https://example.test/weather" rel="noopener">Leave quickly</a></footer></div>', $exit );
	twd_sk_eq( 'https://example.test/weather', TWD_SK_Chrome::effective()['exit_url'] );
	// In the stylesheet (fixed on a phone, the footer keeps room), the snapshot, the brief and the Site tab.
	$css = file_get_contents( ABSPATH . 'assets/twd-site-kit.css' );
	foreach ( array( 'twd-sk-contactbar__link', 'twd-sk-footer--contactbar', 'twd-sk-exit' ) as $cls ) {
		twd_sk_has( $cls, $css );
		twd_sk_true( in_array( $cls, TWD_SK_Chrome::classes(), true ), $cls );
	}
	foreach ( array( 'contact_bar', 'quick_exit', 'quick_exit_url' ) as $k ) {
		twd_sk_true( array_key_exists( $k, TWD_SK_Style::current()['chrome'] ), $k . ' in the snapshot' );
	}
	$js = file_get_contents( ABSPATH . 'assets/twd-site-kit-editor-site.js' );
	twd_sk_has( 'twd-sk-ch-contactbar', $js );
	twd_sk_has( 'quick_exit_url: cui.exitUrl.value.trim()', $js );
	twd_sk_has( 'twd-sk-ed--contactbar', file_get_contents( ABSPATH . 'assets/twd-site-kit-editor.js' ) );
} );
