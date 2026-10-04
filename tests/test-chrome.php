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

twd_sk_test( 'chrome: four header and four footer variants, each with a label', function () {
	twd_sk_eq( array( 'bar', 'centered', 'split', 'minimal' ), array_keys( TWD_SK_Chrome::header_variants() ) );
	twd_sk_eq( array( 'columns', 'band', 'centered', 'simple' ), array_keys( TWD_SK_Chrome::footer_variants() ) );
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

twd_sk_test( 'chrome footer: columns, band, centred and simple', function () {
	twd_sk_chrome_setup();
	foreach ( array( 'columns', 'band', 'centered', 'simple' ) as $v ) {
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
