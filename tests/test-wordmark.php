<?php
// The text wordmark header (0.14.0): the logo as live text (a mark, the name, a second line), centred on every page.
// Everything is off by default, and with it off every header prints exactly as it did before.

function twd_sk_wm_profile( $extra = array() ) {
	$GLOBALS['twd_stub']['attachments'][51] = array( 'url' => '/u/mark.png', 'w' => 120, 'h' => 120, 'is_image' => true );
	$GLOBALS['twd_stub']['attachments'][50] = array( 'url' => '/u/logo.png', 'w' => 640, 'h' => 200, 'is_image' => true );
	return twd_sk_chrome_profile( array_merge( array( 'site_name' => 'Your Name', 'logo_id' => 50, 'logo_mark_id' => 51, 'logo_line' => 'Therapy' ), $extra ) );
}

twd_sk_test( 'wordmark: off by default (setting, marker and second line), and the marker choices are validated', function () {
	$d = TWD_SK_Chrome::defaults();
	twd_sk_true( false === $d['wordmark'], 'off by default' );
	twd_sk_eq( 'dot', $d['menu_marker'], 'a quiet marker by default' );
	twd_sk_eq( array( 'dot', 'color', 'bar', 'none' ), array_keys( TWD_SK_Chrome::menu_markers() ) );
	twd_sk_eq( '', TWD_SK_Profile::defaults()['logo_line'], 'no second line by default' );
	twd_sk_eq( '', TWD_SK_Profile::starter()['logo_line'], 'the starter leaves it empty, nothing is invented' );
	twd_sk_true( ! is_wp_error( TWD_SK_Chrome::save_settings( array( 'wordmark' => true, 'menu_marker' => 'bar' ) ) ) );
	twd_sk_eq( array( true, 'bar' ), array( TWD_SK_Chrome::settings()['wordmark'], TWD_SK_Chrome::settings()['menu_marker'] ) );
	foreach ( array( array( 'menu_marker' => 'underline' ), array( 'menu_marker' => array( 'dot' ) ), array( 'menu_marker' => '' ), array( 'wordmark' => 'yes' ), array( 'wordmark' => 2 ) ) as $bad ) {
		twd_sk_is_error( 'twd_sk_bad_chrome', TWD_SK_Chrome::save_settings( $bad ) );
	}
	twd_sk_eq( true, TWD_SK_Chrome::settings()['wordmark'], 'a refused change stores nothing' );
	// The second line is plain text with a limit.
	TWD_SK_Profile::save( array( 'logo_line' => "  <b>Psycho</b>\n therapy \xE2\x80\x94 online " . str_repeat( 'x', 80 ) ) );
	$line = TWD_SK_Profile::get()['logo_line'];
	twd_sk_true( strlen( $line ) <= 40 && false === strpos( $line, '<' ) && false === strpos( $line, "\xE2\x80\x94" ), 'cleaned and cut: ' . $line );
	twd_sk_is_error( 'twd_sk_bad_profile', TWD_SK_Profile::save( array( 'logo_line' => array( 'x' ) ) ) );
} );

twd_sk_test( 'wordmark off: every header layout and option prints byte for byte what it did before the setting existed', function () {
	// A frozen copy of the markup the header printed before 0.14.0 added the wordmark, kept here so a change cannot slip through.
	twd_sk_chrome_setup( twd_sk_wm_profile(), array( 'header_variant' => 'bar', 'sticky' => false, 'show_strip' => false ) );
	$html = TWD_SK_Chrome::render_header();
	twd_sk_has( '<header class="twd-sk-header twd-sk-header--bar">', $html );
	twd_sk_has( '<a class="twd-sk-header__logo" href="https://example.test/"><img class="twd-sk-header__logo-img" src="/u/logo.png" alt="Your Name" width="640" height="200" decoding="async"></a>', $html );
	foreach ( array( 'bar', 'centered', 'split', 'minimal' ) as $v ) {
		TWD_SK_Chrome::save_settings( array( 'header_variant' => $v, 'sticky' => true, 'shrink' => true, 'show_strip' => true, 'front_logo' => true, 'overlay' => true ) );
		$html = TWD_SK_Chrome::render_header();
		twd_sk_has( 'twd-sk-header twd-sk-header--' . $v, $html, $v );
		foreach ( array( 'twd-sk-header--wordmark', 'twd-sk-header--wm-front', 'twd-sk-header--mark-', 'twd-sk-header__mark', 'twd-sk-header__name', 'twd-sk-header__line', 'twd-sk-chrome--wordmark', 'twd-sk-chrome--wm-front', 'twd-sk-chrome--noline' ) as $not ) {
			twd_sk_hasnt( $not, $html, $v . ' prints no ' . $not );
		}
	}
	// The effective settings of an old site: the wordmark is off, the front page logo option still decides.
	$e = TWD_SK_Chrome::effective();
	twd_sk_true( false === $e['wordmark'] );
} );

twd_sk_test( 'wordmark on: the mark with an empty description, the name once as live text, the second line, in one link, in the order mark, name, line', function () {
	twd_sk_chrome_setup( twd_sk_wm_profile(), array( 'wordmark' => true, 'sticky' => false, 'header_variant' => 'bar' ) );
	$html = TWD_SK_Chrome::render_header();
	twd_sk_has( '<header class="twd-sk-header twd-sk-header--wordmark twd-sk-header--mark-dot">', $html, 'no layout class: the wordmark has its own' );
	twd_sk_hasnt( 'twd-sk-header--bar', $html );
	twd_sk_has( '<a class="twd-sk-header__logo" href="https://example.test/"><img class="twd-sk-header__mark" src="/u/mark.png" alt="" width="120" height="120" decoding="async"><span class="twd-sk-header__name">Your Name</span><span class="twd-sk-header__line">Therapy</span></a>', $html );
	twd_sk_eq( 1, substr_count( $html, 'Your Name' ), 'the name appears once: the link says the site name once' );
	twd_sk_hasnt( '/u/logo.png', $html, 'the full logo (a picture with its words baked in) is not used' );
	// Not capitals in the markup: the stylesheet sets them, so a screen reader reads normal words.
	twd_sk_hasnt( 'YOUR NAME', $html );
	// The menu, the button, the toggle and the skip link are the ones every header has.
	foreach ( array( 'twd-sk-header__toggle', 'id="twd-sk-nav"', 'twd-sk-header__cta', 'twd-sk-header__menu' ) as $part ) {
		twd_sk_has( $part, $html, $part );
	}
	twd_sk_has( 'class="twd-sk-skip"', TWD_SK_Chrome::render_header( true ) );
	// No mark: just the name and the line. No line: just the mark and the name. Nothing is invented.
	twd_sk_chrome_setup( twd_sk_wm_profile( array( 'logo_mark_id' => 0 ) ), array( 'wordmark' => true ) );
	$html = TWD_SK_Chrome::render_header();
	twd_sk_has( '<a class="twd-sk-header__logo" href="https://example.test/"><span class="twd-sk-header__name">Your Name</span><span class="twd-sk-header__line">Therapy</span></a>', $html );
	twd_sk_chrome_setup( twd_sk_wm_profile( array( 'logo_line' => '' ) ), array( 'wordmark' => true ) );
	$html = TWD_SK_Chrome::render_header();
	twd_sk_hasnt( 'twd-sk-header__line', $html );
	twd_sk_has( '<span class="twd-sk-header__name">Your Name</span></a>', $html );
	// The header button stays its own setting.
	TWD_SK_Chrome::save_settings( array( 'show_button' => false ) );
	twd_sk_hasnt( 'twd-sk-header__cta', TWD_SK_Chrome::render_header() );
	// Escaped, like every other text in the header.
	twd_sk_chrome_setup( twd_sk_wm_profile( array( 'site_name' => 'A & B <i>', 'logo_line' => 'Q "x"' ) ), array( 'wordmark' => true, 'show_button' => true ) );
	$html = TWD_SK_Chrome::render_header();
	twd_sk_hasnt( '<i>', $html );
} );

twd_sk_test( 'wordmark on: the front page, other pages, the marker choice and the fixed header each print the classes the stylesheet needs, and the front page logo option is not used with it', function () {
	twd_sk_chrome_setup( twd_sk_wm_profile(), array( 'wordmark' => true, 'front_logo' => true, 'sticky' => true, 'shrink' => true, 'show_strip' => true ) );
	$GLOBALS['twd_stub']['front'] = true;
	$html = TWD_SK_Chrome::render_header();
	twd_sk_has( 'twd-sk-header--wordmark twd-sk-header--mark-dot twd-sk-header--wm-front twd-sk-header--sticky twd-sk-header--shrink', $html );
	twd_sk_hasnt( 'twd-sk-header--front', $html, 'the bigger front logo is not combined with the wordmark' );
	twd_sk_hasnt( 'twd-sk-chrome--front', $html );
	twd_sk_has( 'class="twd-sk-page twd-sk-chrome twd-sk-chrome--sticky twd-sk-chrome--strip twd-sk-chrome--wordmark twd-sk-chrome--wm-front"', $html, 'the spacer knows the size' );
	$GLOBALS['twd_stub']['front'] = false;
	$html = TWD_SK_Chrome::render_header();
	twd_sk_hasnt( 'wm-front', $html );
	twd_sk_has( 'twd-sk-chrome--sticky twd-sk-chrome--strip twd-sk-chrome--wordmark"', $html );
	foreach ( array( 'dot', 'color', 'bar', 'none' ) as $m ) {
		TWD_SK_Chrome::save_settings( array( 'menu_marker' => $m ) );
		twd_sk_has( 'twd-sk-header--wordmark twd-sk-header--mark-' . $m, TWD_SK_Chrome::render_header(), $m );
	}
	// No second line: the spacer drops its height.
	twd_sk_chrome_setup( twd_sk_wm_profile( array( 'logo_line' => '' ) ), array( 'wordmark' => true, 'sticky' => true ) );
	twd_sk_has( 'twd-sk-chrome--wordmark twd-sk-chrome--noline', TWD_SK_Chrome::render_header() );
	// Not fixed: no spacer classes at all.
	TWD_SK_Chrome::save_settings( array( 'sticky' => false ) );
	twd_sk_hasnt( 'twd-sk-chrome--wordmark', TWD_SK_Chrome::render_header() );
	twd_sk_hasnt( 'twd-sk-chrome--noline', TWD_SK_Chrome::render_header() );
	// The centred layout cannot be fixed because it has no known height; the wordmark can, so its setting is not ignored.
	TWD_SK_Chrome::save_settings( array( 'header_variant' => 'centered', 'sticky' => true, 'wordmark' => false ) );
	$e = TWD_SK_Chrome::effective();
	twd_sk_true( ! $e['sticky'] && $e['sticky_note'], 'a plain centred header still cannot be fixed' );
	TWD_SK_Chrome::save_settings( array( 'wordmark' => true ) );
	$e = TWD_SK_Chrome::effective();
	twd_sk_true( $e['sticky'] && ! $e['sticky_note'], 'the wordmark header can' );
	TWD_SK_Chrome::save_settings( array( 'header_variant' => '', 'sticky' => false ) );
} );

twd_sk_test( 'wordmark: safe mode, an empty menu and the transparent header over a picture behave as before', function () {
	twd_sk_chrome_setup( twd_sk_wm_profile(), array( 'wordmark' => true ) );
	TWD_SK_Safe::set( true );
	$html = TWD_SK_Chrome::shortcode_header();
	TWD_SK_Safe::set( false );
	twd_sk_hasnt( 'wordmark', $html, 'safe mode prints the plain header' );
	twd_sk_hasnt( 'twd-sk-header__mark', $html );
	twd_sk_chrome_setup( twd_sk_wm_profile( array( 'menu' => array() ) ), array( 'wordmark' => true ) );
	twd_sk_hasnt( 'wordmark', TWD_SK_Chrome::render_header(), 'with no menu the plain header prints, as it always did' );
	// Over a picture: the same wrapper and header classes as always, plus the wordmark's.
	twd_sk_chrome_setup( twd_sk_wm_profile(), array( 'wordmark' => true, 'overlay' => true, 'sticky' => true ) );
	twd_stub_add_post( 70, 'page', '[twd_page]' );
	TWD_SK_Store::save( 70, TWD_SK_Registry::get( 'hero' )['variants']['twd-sk-hero--image']['skeleton'] . TWD_SK_Registry::get( 'text' )['skeleton'] );
	$GLOBALS['twd_stub']['queried'] = 70;
	$html = TWD_SK_Chrome::render_header();
	twd_sk_has( 'twd-sk-chrome--over', $html );
	twd_sk_has( 'twd-sk-header--wordmark', $html );
	twd_sk_has( 'twd-sk-header--over', $html );
	TWD_SK_Chrome::save_settings( array( 'overlay' => false, 'sticky' => false, 'wordmark' => false ) );
	$GLOBALS['twd_stub']['queried'] = 0;
} );

twd_sk_test( 'wordmark: in the site brief (export, plan), a style kit (read, dry run, snapshot), the command line and the site report', function () {
	$export = TWD_SK_Brief::export()['header'];
	foreach ( array( 'wordmark', 'menu_marker', 'logo_line' ) as $k ) {
		twd_sk_true( array_key_exists( $k, $export ), 'export has ' . $k );
	}
	$plan = TWD_SK_Brief::plan( array( 'schema' => 'twd-site-brief/1', 'header' => array( 'wordmark' => true, 'menu_marker' => 'bar', 'logo_line' => 'Therapy' ) ), false );
	twd_sk_eq( array( 'wordmark' => true, 'menu_marker' => 'bar' ), $plan['changes']['chrome'] );
	twd_sk_eq( array( 'logo_line' => 'Therapy' ), $plan['changes']['profile'] );
	$kit = TWD_SK_Style::read_kit( array( 'chrome' => array( 'wordmark' => true, 'menu_marker' => 'none' ) ) );
	twd_sk_true( ! is_wp_error( $kit ) );
	twd_sk_eq( array( 'wordmark' => true, 'menu_marker' => 'none' ), $kit['chrome'] );
	twd_sk_true( is_wp_error( TWD_SK_Style::read_kit( array( 'chrome' => array( 'menu_marker' => 'underline' ) ) ) ) );
	$dry = TWD_SK_Style::apply( $kit, array( 'dry_run' => true ) );
	$txt = implode( ' | ', $dry['changes'] );
	twd_sk_has( 'wordmark: off to on', $txt );
	twd_sk_has( 'menu marker: dot to none', $txt );
	twd_sk_true( ! TWD_SK_Chrome::settings()['wordmark'], 'a dry run saves nothing' );
	$applied = TWD_SK_Style::apply( $kit );
	twd_sk_true( $applied['applied'] );
	twd_sk_true( TWD_SK_Chrome::settings()['wordmark'] );
	$snap = TWD_SK_Style::snapshots()[0]['chrome'];
	twd_sk_true( array_key_exists( 'wordmark', $snap ) && array_key_exists( 'menu_marker', $snap ), 'a snapshot keeps both' );
	twd_sk_true( false === $snap['wordmark'], 'and holds what it was before' );
	twd_sk_has( 'text wordmark header: yes', TWD_SK_Doctor::report() );
	// The command line.
	WP_CLI::reset();
	( new TWD_SK_CLI() )->style( array( 'apply' ), array( 'wordmark' => 'off', 'menu-marker' => 'dot', 'dry-run' => true ) );
	twd_sk_has( 'wordmark: on to off', WP_CLI::all() );
	twd_sk_true( TWD_SK_Chrome::settings()['wordmark'], 'dry run' );
	( new TWD_SK_CLI() )->style( array( 'apply' ), array( 'wordmark' => 'off' ) );
	twd_sk_true( ! TWD_SK_Chrome::settings()['wordmark'] );
	( new TWD_SK_CLI() )->style( array( 'apply' ), array( 'wordmark' => true, 'menu-marker' => 'color' ) );
	twd_sk_true( TWD_SK_Chrome::settings()['wordmark'] );
	twd_sk_eq( 'color', TWD_SK_Chrome::settings()['menu_marker'] );
	$cli = new TWD_SK_CLI();
	$msg = twd_sk_cli_fails( function () use ( $cli ) {
		$cli->style( array( 'apply' ), array( 'wordmark' => 'maybe' ) );
	} );
	twd_sk_has( 'on or off', $msg );
	$msg = twd_sk_cli_fails( function () use ( $cli ) {
		$cli->style( array( 'apply' ), array( 'menu-marker' => 'underline' ) );
	} );
	twd_sk_has( 'marked', $msg );
	TWD_SK_Chrome::save_settings( array( 'wordmark' => false, 'menu_marker' => 'dot' ) );
} );

twd_sk_test( 'wordmark: the Site tab gets the marker list, and its script has the tick box, the marker choice and the second line field', function () {
	twd_sk_chrome_setup( twd_sk_wm_profile(), array() );
	$payload = TWD_SK_REST::get_site_profile( null );
	twd_sk_eq( array( 'dot', 'color', 'bar', 'none' ), array_keys( $payload['chrome']['menu_markers'] ) );
	twd_sk_true( array_key_exists( 'wordmark', $payload['chrome']['settings'] ) );
	twd_sk_eq( 'Therapy', $payload['profile']['logo_line'] );
	$js = file_get_contents( ABSPATH . 'assets/twd-site-kit-editor-site.js' );
	foreach ( array( 'twd-sk-ch-wordmark', 'twd-sk-ch-marker', 'twd-sk-pf-logoline', 'wordmark: cui.wordmark.checked', 'menu_marker: cui.marker.value', 'logo_line: pui.logoLine.value' ) as $needle ) {
		twd_sk_has( $needle, $js, $needle );
	}
} );

twd_sk_test( 'wordmark css: sizes and menu from tokens (not the body size), capitals and spacing by CSS, the marker choices, scrolled row, phone rules, the spacer worked out, no new colour literal', function () {
	$raw = file_get_contents( ABSPATH . 'assets/twd-site-kit.css' );
	$has = function ( $pattern, $why ) use ( $raw ) {
		twd_sk_true( 1 === preg_match( $pattern, $raw ), $why );
	};
	$w = 'twd-sk-header--wordmark\.twd-sk-header--wordmark';
	$has( '/' . $w . ' \.twd-sk-header__name\.twd-sk-header__name \{[^}]*font-size: var\(--twd-site-wordmark-name\)[^}]*letter-spacing: var\(--twd-site-wordmark-name-spacing\)[^}]*text-transform: uppercase[^}]*color: var\(--twd-site-color-heading\)/', 'the name: heading font and ink, capitals, wide spacing, from tokens' );
	$has( '/' . $w . ' \.twd-sk-header__name\.twd-sk-header__name \{[^}]*font-family: var\(--twd-site-font-heading\)/', 'the name is in the heading font' );
	$has( '/' . $w . ' \.twd-sk-header__line\.twd-sk-header__line \{[^}]*font-family: var\(--twd-site-font-body\)[^}]*font-size: var\(--twd-site-wordmark-line\)[^}]*letter-spacing: var\(--twd-site-wordmark-line-spacing\)[^}]*text-transform: uppercase/', 'the second line: body font, small, very wide spacing, capitals' );
	$has( '/' . $w . ' \.twd-sk-header__mark\.twd-sk-header__mark \{[^}]*height: var\(--twd-site-wordmark-symbol\)/', 'the symbol height is a token' );
	$has( '/' . $w . ' \.twd-sk-header__link\.twd-sk-header__link \{[^}]*font-size: var\(--twd-site-menu-size\)[^}]*font-weight: var\(--twd-site-menu-weight\)[^}]*letter-spacing: var\(--twd-site-menu-spacing\)/', 'the menu has its own size, weight and spacing' );
	$has( '/' . $w . ' \.twd-sk-header__menu\.twd-sk-header__menu \{[^}]*gap: 4px var\(--twd-site-menu-gap\)/', 'the menu gap is a token' );
	$has( '/twd-sk-header--wm-front\.twd-sk-header--wm-front \.twd-sk-header__mark\.twd-sk-header__mark \{[^}]*height: var\(--twd-site-wordmark-symbol-front\)/', 'the front page is larger, from tokens' );
	$has( '/' . $w . '\.twd-sk-header--shrunk\.twd-sk-header--shrunk \.twd-sk-header__mark\.twd-sk-header__mark \{[^}]*height: var\(--twd-site-wordmark-symbol-compact\)/', 'scrolled: the small symbol' );
	$has( '/' . $w . '\.twd-sk-header--shrunk\.twd-sk-header--shrunk \.twd-sk-header__line\.twd-sk-header__line \{[^}]*display: none/', 'scrolled: the second line is hidden' );
	$has( '/' . $w . '\.twd-sk-header--shrunk\.twd-sk-header--shrunk \.twd-sk-header__inner\.twd-sk-header__inner \{[^}]*flex-direction: row/', 'scrolled: one row' );
	$has( '/@media \(max-width: 900px\)\s*\{[^@]*' . $w . ' \.twd-sk-header__mark\.twd-sk-header__mark \{[^}]*height: var\(--twd-site-wordmark-symbol-sm\)/', 'phones: the phone symbol size' );
	$has( '/@media \(max-width: 900px\)\s*\{[^@]*' . $w . ' \.twd-sk-header__name\.twd-sk-header__name \{[^}]*font-size: var\(--twd-site-wordmark-name-sm\)/', 'phones: the phone name size' );
	$has( '/twd-sk-header--js\.twd-sk-header--js\.twd-sk-header--wordmark\.twd-sk-header--wordmark \.twd-sk-header__toggle\.twd-sk-header__toggle \{[^}]*position: absolute/', 'phones: the menu button at the corner, the stack stays centred' );
	$has( '/' . $w . ' \.twd-sk-header__cta\.twd-sk-header__cta \{[^}]*padding-top: 8px !important[^}]*font-size: var\(--twd-site-menu-size\)/', 'the header button never makes the menu row taller than a link, so the fixed spacer is exact with or without it' );
	$has( '/' . $w . ' \.twd-sk-header__logo\.twd-sk-header__logo \{[^}]*flex: 0 1 auto[^}]*min-width: 0/', 'a long name wraps instead of pushing the menu button off a narrow screen' );
	// The page you are on: no underline in this header, a marker by class.
	$has( '/twd-sk-header--wordmark\.twd-sk-header--wordmark \.twd-sk-header__link\.twd-sk-header__link\[aria-current="page"\] \{[^}]*color: inherit !important[^}]*text-decoration: none !important/', 'no underline on the current page' );
	$has( '/twd-sk-header--mark-dot\.twd-sk-header--mark-dot \.twd-sk-header__link\.twd-sk-header__link\[aria-current="page"\]::after[^{]*\{[^}]*border-radius: 50%/', 'the dot' );
	$has( '/twd-sk-header--mark-bar\.twd-sk-header--mark-bar \.twd-sk-header__link\.twd-sk-header__link\[aria-current="page"\]::after[^{]*\{[^}]*width: 18px/', 'the bar' );
	$has( '/twd-sk-header--mark-color\.twd-sk-header--mark-color \.twd-sk-header__link\.twd-sk-header__link\[aria-current="page"\] \{[^}]*color: var\(--twd-site-color-accent-text\)/', 'colour and weight' );
	// The fixed header's spacer is worked out from the same tokens, for the front page, no second line, a strip and phones.
	$has( '/twd-sk-chrome--sticky\.twd-sk-chrome--sticky\.twd-sk-chrome--wordmark\.twd-sk-chrome--wordmark \{[^}]*height: calc\(var\(--twd-sk-wm-s\) \+ var\(--twd-sk-wm-n\) \* 1\.2 \+ var\(--twd-sk-wm-l\) \* 1\.3 \+ var\(--twd-site-menu-size\) \* 1\.3 \+ var\(--twd-sk-wm-pad\) \+ var\(--twd-sk-wm-strip\)\)/', 'the spacer height' );
	$has( '/twd-sk-chrome--wm-front\.twd-sk-chrome--wm-front \{[^}]*--twd-sk-wm-s: var\(--twd-site-wordmark-symbol-front\)/', 'the front page spacer' );
	$has( '/twd-sk-chrome--noline\.twd-sk-chrome--noline \{[^}]*--twd-sk-wm-l: 0px/', 'no second line' );
	$has( '/twd-sk-chrome--over\.twd-sk-chrome--over[^{]*\{[^}]*height: 0/', 'over a picture the wrapper has no height' );
	twd_sk_true( false !== strpos( $raw, 'twd-sk-chrome--sticky.twd-sk-chrome--sticky.twd-sk-chrome--wordmark.twd-sk-chrome--wordmark.twd-sk-chrome--over.twd-sk-chrome--over' ), 'and that holds for the wordmark header too' );
	// Less motion: the rule that cancels the scroll transition comes after the transition it cancels.
	$last_motion = strrpos( $raw, '@media (prefers-reduced-motion: reduce)' );
	$transition  = strrpos( $raw, 'transition: padding .2s ease' );
	twd_sk_true( false !== $last_motion && false !== $transition && $last_motion > $transition, 'reduced motion wins the tie' );
	// The marker and the wordmark classes are all styled (the general class test also checks this).
	foreach ( array( 'twd-sk-header__mark', 'twd-sk-header__name', 'twd-sk-header__line', 'twd-sk-header--wordmark', 'twd-sk-header--wm-front', 'twd-sk-header--mark-none', 'twd-sk-chrome--noline' ) as $class ) {
		twd_sk_has( '.' . $class . '.' . $class, $raw, $class );
	}
} );

twd_sk_test( 'wordmark: the shrink script waits for the spacer to scroll up to the compact row (no blank band), and every other header still shrinks after 60px', function () {
	$js = file_get_contents( ABSPATH . 'assets/twd-site-kit-header-shrink.js' );
	twd_sk_has( 'var SHRINK_AT = 60;', $js );
	twd_sk_has( 'var GROW_AT = 20;', $js );
	twd_sk_has( "wrap.classList.contains('twd-sk-chrome--wordmark')", $js );
	twd_sk_has( 'Math.max(SHRINK_AT, wrap.offsetHeight - COMPACT)', $js );
	twd_sk_has( 'if (!shrunk && y > shrinkAt()) {', $js );
	twd_sk_hasnt( 'innerHTML', $js );
} );

twd_sk_test( 'wordmark tokens: in every pack and as defaults, sized as asked, valid, and an older pack still reads', function () {
	$want = array(
		'wordmark-symbol-front' => '72px', 'wordmark-symbol' => '48px', 'wordmark-symbol-sm' => '44px', 'wordmark-symbol-compact' => '32px',
		'wordmark-name-front' => '24px', 'wordmark-name' => '20px', 'wordmark-name-sm' => '18px',
		'wordmark-line-front' => '12px', 'wordmark-line' => '11px', 'wordmark-line-sm' => '12px',
		'wordmark-name-spacing' => '0.18em', 'wordmark-line-spacing' => '0.3em',
		'menu-size' => '15px', 'menu-weight' => '400', 'menu-spacing' => '0.02em', 'menu-gap' => '28px',
	);
	foreach ( TWD_SK_Packs::packs() as $slug => $pack ) {
		foreach ( $want as $name => $value ) {
			twd_sk_eq( $value, $pack['tokens'][ $name ], $slug . ' ' . $name );
		}
		twd_sk_true( (int) $pack['tokens']['wordmark-name-sm'] >= 18, $slug . ' the phone name is readable' );
		twd_sk_true( (int) $pack['tokens']['wordmark-symbol-sm'] >= 44, $slug . ' the phone symbol' );
		// The name and second line are in the heading ink on the header's background.
		twd_sk_true( TWD_SK_Contrast::ratio( $pack['tokens']['color-heading'], $pack['tokens']['color-surface'] ) >= 4.5, $slug . ' heading ink on the header' );
	}
	foreach ( $want as $name => $value ) {
		twd_sk_eq( $value, TWD_SK_Packs::defaults()[ $name ], 'default ' . $name );
		twd_sk_true( TWD_SK_Packs::valid_token( $name, $value ), $name . ' is a valid value' );
	}
	foreach ( array( array( 'menu-weight', '450' ), array( 'menu-weight', 'bold' ), array( 'menu-size', '-1px' ), array( 'wordmark-name', '2vw' ), array( 'wordmark-name-spacing', 'wide' ) ) as $bad ) {
		twd_sk_true( ! TWD_SK_Packs::valid_token( $bad[0], $bad[1] ), $bad[0] . ' ' . $bad[1] . ' is refused' );
	}
	$old = json_decode( file_get_contents( ABSPATH . 'packs/sage.json' ), true );
	$old['slug'] = 'older2';
	foreach ( array_keys( $want ) as $name ) {
		unset( $old['tokens'][ $name ] );
	}
	$pack = TWD_SK_Packs::read_pack_file( twd_sk_write_pack( 'older2', $old ) );
	twd_sk_true( is_array( $pack ), 'a pack from before the wordmark still reads' );
	twd_sk_eq( '20px', $pack['tokens']['wordmark-name'] );
	twd_sk_eq( count( TWD_SK_Packs::token_names() ), count( $pack['tokens'] ) );
} );
