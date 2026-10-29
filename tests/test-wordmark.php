<?php
// The text wordmark header (0.14.0): the logo as live text. On the front page a centred stack (a small mark, the
// name, a second line, the menu below); on every other page a one-row bar (the mark and the name at the left, the
// menu at the right); scrolled, a compact row. Everything is off by default, and with it off every header prints
// exactly as it did before.

function twd_sk_wm_profile( $extra = array() ) {
	$GLOBALS['twd_stub']['attachments'][51] = array( 'url' => '/u/mark.png', 'w' => 120, 'h' => 120, 'is_image' => true );
	$GLOBALS['twd_stub']['attachments'][50] = array( 'url' => '/u/logo.png', 'w' => 640, 'h' => 200, 'is_image' => true );
	return twd_sk_chrome_profile( array_merge( array( 'site_name' => 'Your Name', 'logo_id' => 50, 'logo_mark_id' => 51, 'logo_line' => 'Therapy' ), $extra ) );
}

twd_sk_test( 'wordmark: off by default (setting, scrolled name, marker and second line), and the choices are validated', function () {
	$d = TWD_SK_Chrome::defaults();
	twd_sk_true( false === $d['wordmark'], 'off by default' );
	twd_sk_true( false === $d['wordmark_scrolled_name'], 'the name is not kept when scrolled by default' );
	twd_sk_eq( 'dot', $d['menu_marker'], 'a quiet marker by default' );
	twd_sk_eq( array( 'dot', 'color', 'bar', 'none' ), array_keys( TWD_SK_Chrome::menu_markers() ) );
	twd_sk_eq( '', TWD_SK_Profile::defaults()['logo_line'], 'no second line by default' );
	twd_sk_eq( '', TWD_SK_Profile::starter()['logo_line'], 'the starter leaves it empty, nothing is invented' );
	twd_sk_true( ! is_wp_error( TWD_SK_Chrome::save_settings( array( 'wordmark' => true, 'wordmark_scrolled_name' => true, 'menu_marker' => 'bar' ) ) ) );
	$s = TWD_SK_Chrome::settings();
	twd_sk_eq( array( true, true, 'bar' ), array( $s['wordmark'], $s['wordmark_scrolled_name'], $s['menu_marker'] ) );
	foreach ( array( array( 'menu_marker' => 'underline' ), array( 'menu_marker' => array( 'dot' ) ), array( 'menu_marker' => '' ), array( 'wordmark' => 'yes' ), array( 'wordmark' => 2 ), array( 'wordmark_scrolled_name' => 'name' ) ) as $bad ) {
		twd_sk_is_error( 'twd_sk_bad_chrome', TWD_SK_Chrome::save_settings( $bad ) );
	}
	twd_sk_eq( true, TWD_SK_Chrome::settings()['wordmark'], 'a refused change stores nothing' );
	// The second line is plain text with a limit.
	TWD_SK_Profile::save( array( 'logo_line' => "  <b>Psycho</b>\n therapy \xE2\x80\x94 online " . str_repeat( 'x', 80 ) ) );
	$line = TWD_SK_Profile::get()['logo_line'];
	twd_sk_true( strlen( $line ) <= 40 && false === strpos( $line, '<' ) && false === strpos( $line, "\xE2\x80\x94" ), 'cleaned and cut: ' . $line );
	twd_sk_is_error( 'twd_sk_bad_profile', TWD_SK_Profile::save( array( 'logo_line' => array( 'x' ) ) ) );
} );

twd_sk_test( 'wordmark off: every header layout and option prints none of the wordmark pieces, and the picture logo is as it was', function () {
	twd_sk_chrome_setup( twd_sk_wm_profile(), array( 'header_variant' => 'bar', 'sticky' => false, 'show_strip' => false ) );
	$html = TWD_SK_Chrome::render_header();
	twd_sk_has( '<header class="twd-sk-header twd-sk-header--bar">', $html );
	twd_sk_has( '<a class="twd-sk-header__logo" href="https://example.test/"><img class="twd-sk-header__logo-img" src="/u/logo.png" alt="Your Name" width="640" height="200" decoding="async"></a>', $html );
	foreach ( array( 'bar', 'centered', 'split', 'minimal' ) as $v ) {
		foreach ( array( false, true ) as $front ) {
			$GLOBALS['twd_stub']['front'] = $front;
			TWD_SK_Chrome::save_settings( array( 'header_variant' => $v, 'sticky' => true, 'shrink' => true, 'show_strip' => true, 'front_logo' => true, 'overlay' => true ) );
			$html = TWD_SK_Chrome::render_header();
			twd_sk_has( 'twd-sk-header twd-sk-header--' . $v, $html, $v );
			foreach ( array( 'twd-sk-header--wordmark', 'twd-sk-header--wm-front', 'twd-sk-header--wm-name', 'twd-sk-header--mark-', 'twd-sk-header__mark', 'twd-sk-header__name', 'twd-sk-header__line', 'twd-sk-chrome--wordmark', 'twd-sk-chrome--wm-front', 'twd-sk-chrome--noline' ) as $not ) {
				twd_sk_hasnt( $not, $html, $v . ' prints no ' . $not );
			}
		}
	}
	$GLOBALS['twd_stub']['front'] = false;
	twd_sk_true( false === TWD_SK_Chrome::effective()['wordmark'] );
} );

twd_sk_test( 'wordmark on, front page: the stacked lockup, the mark with an empty description, the name once as live text, the second line, in one link', function () {
	twd_sk_chrome_setup( twd_sk_wm_profile(), array( 'wordmark' => true, 'sticky' => false, 'header_variant' => 'split' ) );
	$GLOBALS['twd_stub']['front'] = true;
	$html = TWD_SK_Chrome::render_header();
	twd_sk_has( '<header class="twd-sk-header twd-sk-header--wordmark twd-sk-header--mark-dot twd-sk-header--wm-front">', $html, 'the stack, with the wordmark classes in place of the layout class' );
	twd_sk_hasnt( 'twd-sk-header--split', $html );
	twd_sk_hasnt( 'twd-sk-header--bar', $html, 'the stack is not the bar' );
	twd_sk_has( '<a class="twd-sk-header__logo" href="https://example.test/"><img class="twd-sk-header__mark" src="/u/mark.png" alt="" width="120" height="120" decoding="async"><span class="twd-sk-header__name">Your Name</span><span class="twd-sk-header__line">Therapy</span></a>', $html );
	twd_sk_eq( 1, substr_count( $html, 'Your Name' ), 'the name appears once: the link says the site name once' );
	twd_sk_hasnt( '/u/logo.png', $html, 'the full logo (a picture with its words baked in) is not used' );
	twd_sk_hasnt( 'YOUR NAME', $html, 'capitals come from the stylesheet, so a screen reader reads normal words' );
	foreach ( array( 'twd-sk-header__toggle', 'id="twd-sk-nav"', 'twd-sk-header__cta', 'twd-sk-header__menu' ) as $part ) {
		twd_sk_has( $part, $html, $part );
	}
	twd_sk_has( 'class="twd-sk-skip"', TWD_SK_Chrome::render_header( true ) );
	// No mark: just the name and the line. No line: just the mark and the name. Nothing is invented.
	twd_sk_chrome_setup( twd_sk_wm_profile( array( 'logo_mark_id' => 0 ) ), array( 'wordmark' => true ) );
	twd_sk_has( '<a class="twd-sk-header__logo" href="https://example.test/"><span class="twd-sk-header__name">Your Name</span><span class="twd-sk-header__line">Therapy</span></a>', TWD_SK_Chrome::render_header() );
	twd_sk_chrome_setup( twd_sk_wm_profile( array( 'logo_line' => '' ) ), array( 'wordmark' => true ) );
	$html = TWD_SK_Chrome::render_header();
	twd_sk_hasnt( 'twd-sk-header__line', $html );
	twd_sk_has( '<span class="twd-sk-header__name">Your Name</span></a>', $html );
	// The header button stays its own setting.
	TWD_SK_Chrome::save_settings( array( 'show_button' => false ) );
	twd_sk_hasnt( 'twd-sk-header__cta', TWD_SK_Chrome::render_header() );
	// Escaped, like every other text in the header.
	twd_sk_chrome_setup( twd_sk_wm_profile( array( 'site_name' => 'A & B <i>', 'logo_line' => 'Q "x"' ) ), array( 'wordmark' => true, 'show_button' => true ) );
	twd_sk_hasnt( '<i>', TWD_SK_Chrome::render_header() );
	$GLOBALS['twd_stub']['front'] = false;
} );

twd_sk_test( 'wordmark on, every other page: the one-row bar with the mark and the name at the left, no second line, no stack', function () {
	twd_sk_chrome_setup( twd_sk_wm_profile(), array( 'wordmark' => true, 'sticky' => false, 'header_variant' => 'minimal' ) );
	$GLOBALS['twd_stub']['front'] = false;
	$html = TWD_SK_Chrome::render_header();
	twd_sk_has( '<header class="twd-sk-header twd-sk-header--wordmark twd-sk-header--mark-dot twd-sk-header--bar">', $html, 'the bar, whatever layout was chosen' );
	twd_sk_hasnt( 'wm-front', $html );
	twd_sk_has( '<a class="twd-sk-header__logo" href="https://example.test/"><img class="twd-sk-header__mark" src="/u/mark.png" alt="" width="120" height="120" decoding="async"><span class="twd-sk-header__name">Your Name</span></a>', $html );
	twd_sk_hasnt( 'twd-sk-header__line', $html, 'the second line is only on the front page' );
	twd_sk_hasnt( 'Therapy', $html );
	twd_sk_eq( 1, substr_count( $html, 'Your Name' ) );
	foreach ( array( 'twd-sk-header__toggle', 'twd-sk-header__menu', 'twd-sk-header__cta' ) as $part ) {
		twd_sk_has( $part, $html, $part );
	}
} );

twd_sk_test( 'wordmark: the scrolled name setting, the marker choice, and the fixed header each print the classes the stylesheet needs', function () {
	twd_sk_chrome_setup( twd_sk_wm_profile(), array( 'wordmark' => true, 'sticky' => true, 'shrink' => true, 'show_strip' => true ) );
	$GLOBALS['twd_stub']['front'] = true;
	$html = TWD_SK_Chrome::render_header();
	twd_sk_has( 'twd-sk-header--wordmark twd-sk-header--mark-dot twd-sk-header--wm-front twd-sk-header--sticky twd-sk-header--shrink', $html );
	twd_sk_hasnt( 'twd-sk-header--wm-name', $html, 'off by default' );
	twd_sk_has( 'class="twd-sk-page twd-sk-chrome twd-sk-chrome--sticky twd-sk-chrome--strip twd-sk-chrome--wordmark twd-sk-chrome--wm-front"', $html, 'the spacer knows the front page is the tall stack' );
	TWD_SK_Chrome::save_settings( array( 'wordmark_scrolled_name' => true ) );
	twd_sk_has( 'twd-sk-header--wm-front twd-sk-header--wm-name twd-sk-header--sticky', TWD_SK_Chrome::render_header() );
	twd_sk_true( TWD_SK_Chrome::effective()['wm_name'] );
	$GLOBALS['twd_stub']['front'] = false;
	$html = TWD_SK_Chrome::render_header();
	twd_sk_hasnt( 'wm-name', $html, 'the setting only matters on the front page' );
	twd_sk_hasnt( 'wm-front', $html );
	twd_sk_has( 'twd-sk-chrome--sticky twd-sk-chrome--strip twd-sk-chrome--wordmark"', $html, 'other pages: the one-row spacer' );
	TWD_SK_Chrome::save_settings( array( 'wordmark_scrolled_name' => false ) );
	foreach ( array( 'dot', 'color', 'bar', 'none' ) as $m ) {
		TWD_SK_Chrome::save_settings( array( 'menu_marker' => $m ) );
		twd_sk_has( 'twd-sk-header--wordmark twd-sk-header--mark-' . $m, TWD_SK_Chrome::render_header(), $m );
	}
	// No second line: the front page spacer drops its height.
	$GLOBALS['twd_stub']['front'] = true;
	twd_sk_chrome_setup( twd_sk_wm_profile( array( 'logo_line' => '' ) ), array( 'wordmark' => true, 'sticky' => true ) );
	twd_sk_has( 'twd-sk-chrome--wordmark twd-sk-chrome--wm-front twd-sk-chrome--noline', TWD_SK_Chrome::render_header() );
	$GLOBALS['twd_stub']['front'] = false;
	twd_sk_hasnt( 'twd-sk-chrome--noline', TWD_SK_Chrome::render_header(), 'only the front page has a second line to leave out' );
	// Not fixed: no spacer classes at all.
	TWD_SK_Chrome::save_settings( array( 'sticky' => false ) );
	twd_sk_hasnt( 'twd-sk-chrome--wordmark', TWD_SK_Chrome::render_header() );
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
	$GLOBALS['twd_stub']['front'] = true;
	$html = TWD_SK_Chrome::render_header();
	twd_sk_has( 'twd-sk-chrome--over', $html );
	twd_sk_has( 'twd-sk-header--wordmark', $html );
	twd_sk_has( 'twd-sk-header--over', $html );
	TWD_SK_Chrome::save_settings( array( 'overlay' => false, 'sticky' => false, 'wordmark' => false ) );
	$GLOBALS['twd_stub']['queried'] = 0;
	$GLOBALS['twd_stub']['front'] = false;
} );

twd_sk_test( 'wordmark: in the site brief (export, plan), a style kit (read, dry run, snapshot), the command line and the site report', function () {
	$export = TWD_SK_Brief::export()['header'];
	foreach ( array( 'wordmark', 'wordmark_scrolled_name', 'menu_marker', 'logo_line' ) as $k ) {
		twd_sk_true( array_key_exists( $k, $export ), 'export has ' . $k );
	}
	$plan = TWD_SK_Brief::plan( array( 'schema' => 'twd-site-brief/1', 'header' => array( 'wordmark' => true, 'wordmark_scrolled_name' => true, 'menu_marker' => 'bar', 'logo_line' => 'Therapy' ) ), false );
	twd_sk_eq( array( 'wordmark' => true, 'wordmark_scrolled_name' => true, 'menu_marker' => 'bar' ), $plan['changes']['chrome'] );
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
	foreach ( array( 'wordmark', 'wordmark_scrolled_name', 'menu_marker', 'overlay' ) as $k ) {
		twd_sk_true( array_key_exists( $k, $snap ), 'a snapshot keeps ' . $k );
	}
	twd_sk_true( false === $snap['wordmark'], 'and holds what it was before' );
	twd_sk_has( 'text wordmark header: yes', TWD_SK_Doctor::report() );
	// The command line.
	WP_CLI::reset();
	( new TWD_SK_CLI() )->style( array( 'apply' ), array( 'wordmark' => 'off', 'menu-marker' => 'dot', 'dry-run' => true ) );
	twd_sk_has( 'wordmark: on to off', WP_CLI::all() );
	twd_sk_true( TWD_SK_Chrome::settings()['wordmark'], 'dry run' );
	( new TWD_SK_CLI() )->style( array( 'apply' ), array( 'wordmark' => 'off' ) );
	twd_sk_true( ! TWD_SK_Chrome::settings()['wordmark'] );
	( new TWD_SK_CLI() )->style( array( 'apply' ), array( 'wordmark' => true, 'scrolled-name' => 'on', 'menu-marker' => 'color' ) );
	twd_sk_true( TWD_SK_Chrome::settings()['wordmark'] && TWD_SK_Chrome::settings()['wordmark_scrolled_name'] );
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
	TWD_SK_Chrome::save_settings( array( 'wordmark' => false, 'wordmark_scrolled_name' => false, 'menu_marker' => 'dot' ) );
} );

twd_sk_test( 'wordmark: the Site tab gets the marker list, and its script has the tick boxes, the marker choice, the second line field and previews the menu font', function () {
	twd_sk_chrome_setup( twd_sk_wm_profile(), array() );
	$payload = TWD_SK_REST::get_site_profile( null );
	twd_sk_eq( array( 'dot', 'color', 'bar', 'none' ), array_keys( $payload['chrome']['menu_markers'] ) );
	twd_sk_true( array_key_exists( 'wordmark', $payload['chrome']['settings'] ) && array_key_exists( 'wordmark_scrolled_name', $payload['chrome']['settings'] ) );
	twd_sk_eq( 'Therapy', $payload['profile']['logo_line'] );
	$js = file_get_contents( ABSPATH . 'assets/twd-site-kit-editor-site.js' );
	foreach ( array( 'twd-sk-ch-wordmark', 'twd-sk-ch-wmname', 'twd-sk-ch-marker', 'twd-sk-pf-logoline', 'wordmark: cui.wordmark.checked', 'wordmark_scrolled_name: cui.wmname.checked', 'menu_marker: cui.marker.value', 'logo_line: pui.logoLine.value', "name === 'menu-font'" ) as $needle ) {
		twd_sk_has( $needle, $js, $needle );
	}
} );

twd_sk_test( 'wordmark css: the stack, the bar, the compact row, the menu from its own tokens, the marker choices, the phone rules, the spacer worked out, no new colour literal', function () {
	$raw = file_get_contents( ABSPATH . 'assets/twd-site-kit.css' );
	$has = function ( $pattern, $why ) use ( $raw ) {
		twd_sk_true( 1 === preg_match( $pattern, $raw ), $why );
	};
	$w  = 'twd-sk-header--wordmark\.twd-sk-header--wordmark';
	$f  = 'twd-sk-header--wm-front\.twd-sk-header--wm-front';
	$fs = $f . '\.twd-sk-header--shrunk\.twd-sk-header--shrunk';
	$fn = $f . '\.twd-sk-header--wm-name\.twd-sk-header--wm-name\.twd-sk-header--shrunk\.twd-sk-header--shrunk';
	$m  = '\.twd-sk-header__mark\.twd-sk-header__mark';
	$n  = '\.twd-sk-header__name\.twd-sk-header__name';
	// The words.
	$has( '/' . $w . ' ' . $n . ' \{[^}]*font-family: var\(--twd-site-font-heading\)[^}]*font-size: var\(--twd-site-wordmark-name\)[^}]*letter-spacing: var\(--twd-site-wordmark-name-spacing\)[^}]*text-transform: uppercase[^}]*color: var\(--twd-site-color-heading\)/', 'the name: heading font and ink, capitals, wide spacing, from tokens' );
	$has( '/' . $f . ' ' . $n . ' \{[^}]*font-size: var\(--twd-site-wordmark-name-front\)/', 'the front page name is the large one' );
	$has( '/' . $w . ' \.twd-sk-header__line\.twd-sk-header__line \{[^}]*font-family: var\(--twd-site-font-body\)[^}]*font-size: var\(--twd-site-wordmark-line-front\)[^}]*letter-spacing: var\(--twd-site-wordmark-line-spacing\)[^}]*text-transform: uppercase/', 'the second line: body font, small, very wide spacing, capitals' );
	$has( '/' . $w . ' ' . $m . ' \{[^}]*height: var\(--twd-site-wordmark-symbol\)/', 'the bar symbol is a token' );
	$has( '/' . $f . ' ' . $m . ' \{[^}]*height: var\(--twd-site-wordmark-symbol-front\)/', 'the front page symbol is a token' );
	// The stack and the bar.
	$has( '/' . $f . ' \.twd-sk-header__inner\.twd-sk-header__inner \{[^}]*flex-direction: column/', 'the front page stacks' );
	$has( '/' . $f . ' \.twd-sk-header__logo\.twd-sk-header__logo \{[^}]*flex-direction: column/', 'the lockup is a column' );
	$has( '/' . $w . '\.twd-sk-header--bar\.twd-sk-header--bar \.twd-sk-header__inner\.twd-sk-header__inner \{[^}]*flex-wrap: wrap/', 'the bar wraps rather than overflows' );
	$has( '/' . $w . ' \.twd-sk-header__logo\.twd-sk-header__logo \{[^}]*flex: 0 1 auto[^}]*min-width: 0/', 'a long name wraps instead of pushing the menu button off a narrow screen' );
	// The menu: its own font, size, weight, spacing and capitals, never the body size.
	$has( '/' . $w . ' \.twd-sk-header__link\.twd-sk-header__link \{[^}]*font-family: var\(--twd-site-menu-font\)[^}]*font-size: var\(--twd-site-menu-size\)[^}]*font-weight: var\(--twd-site-menu-weight\)[^}]*letter-spacing: var\(--twd-site-menu-spacing\)[^}]*text-transform: var\(--twd-site-menu-transform\)/', 'the menu has its own look' );
	$has( '/' . $w . ' \.twd-sk-header__menu\.twd-sk-header__menu \{[^}]*gap: 4px var\(--twd-site-menu-gap\)/', 'the menu gap is a token' );
	$has( '/' . $w . ' \.twd-sk-header__cta\.twd-sk-header__cta \{[^}]*padding: 8px 18px !important[^}]*font-size: var\(--twd-site-menu-size\)/', 'the header button never makes the menu row taller than a link, so the fixed spacer is exact with or without it' );
	$has( '/' . $w . ' \.twd-sk-header__sublink\.twd-sk-header__sublink \{[^}]*font-family: var\(--twd-site-menu-font\)[^}]*font-size: var\(--twd-site-menu-size\)[^}]*text-transform: var\(--twd-site-menu-transform\)/', 'sub-menu links take the menu look too' );
	$has( '/@media \(max-width: 900px\)\s*\{[^@]*twd-sk-header--js\.twd-sk-header--js\.twd-sk-header--sticky\.twd-sk-header--sticky\.twd-sk-header--wm-front\.twd-sk-header--wm-front \.twd-sk-header__panel\.twd-sk-header__panel \{[^}]*margin-top: 0 !important/', 'the fixed header\'s drop-down menu on a phone sits right under the header, with no gap' );
	// The page you are on: no underline in this header, a marker by class.
	$has( '/' . $w . ' \.twd-sk-header__link\.twd-sk-header__link\[aria-current="page"\] \{[^}]*color: inherit !important[^}]*text-decoration: none !important/', 'no underline on the current page' );
	$has( '/twd-sk-header--mark-dot\.twd-sk-header--mark-dot \.twd-sk-header__link\.twd-sk-header__link\[aria-current="page"\]::after[^{]*\{[^}]*border-radius: 50%/', 'the dot' );
	$has( '/twd-sk-header--mark-bar\.twd-sk-header--mark-bar \.twd-sk-header__link\.twd-sk-header__link\[aria-current="page"\]::after[^{]*\{[^}]*width: 18px/', 'the bar marker' );
	$has( '/twd-sk-header--mark-color\.twd-sk-header--mark-color \.twd-sk-header__link\.twd-sk-header__link\[aria-current="page"\] \{[^}]*color: var\(--twd-site-color-accent-text\)/', 'colour and weight' );
	// The front page scrolled: one row, the small symbol, the name out of sight but not out of reach, unless kept.
	$has( '/' . $fs . ' \.twd-sk-header__inner\.twd-sk-header__inner \{[^}]*flex-direction: row[^}]*justify-content: center/', 'scrolled: one centred row' );
	$has( '/' . $fs . ' ' . $m . ' \{[^}]*height: var\(--twd-site-wordmark-symbol\)/', 'scrolled: the small symbol' );
	$has( '/' . $fs . ' ' . $n . ' \{[^}]*clip-path: inset\(50%\)/', 'scrolled: the name is clipped away, not removed, so a screen reader still has it' );
	$has( '/' . $fs . ' \.twd-sk-header__line\.twd-sk-header__line \{[^}]*display: none/', 'scrolled: the second line is hidden' );
	$has( '/' . $fn . ' ' . $n . ' \{[^}]*position: static[^}]*clip-path: none/', 'the setting brings the name back' );
	$has( '/' . $fn . ' \.twd-sk-header__inner\.twd-sk-header__inner \{[^}]*justify-content: space-between/', 'and puts the menu at the right, like the other pages' );
	// Phones.
	$has( '/@media \(max-width: 900px\)\s*\{[^@]*' . $w . ' ' . $m . ' \{[^}]*height: var\(--twd-site-wordmark-symbol-sm\)/', 'phones: the bar symbol size' );
	$has( '/@media \(max-width: 900px\)\s*\{[^@]*' . $w . ' ' . $n . ' \{[^}]*font-size: var\(--twd-site-wordmark-name-sm\)/', 'phones: the bar name size' );
	$has( '/@media \(max-width: 900px\)\s*\{[^@]*' . $f . ' ' . $m . ' \{[^}]*height: var\(--twd-site-wordmark-symbol-front-sm\)/', 'phones: the stacked symbol size' );
	$has( '/@media \(max-width: 900px\)\s*\{[^@]*' . $f . ' ' . $n . ' \{[^}]*font-size: var\(--twd-site-wordmark-name-front-sm\)/', 'phones: the stacked name size' );
	$has( '/twd-sk-header--js\.twd-sk-header--js\.twd-sk-header--wm-front\.twd-sk-header--wm-front \.twd-sk-header__toggle\.twd-sk-header__toggle \{[^}]*position: absolute/', 'phones: the menu button at the corner, the stack stays centred' );
	// The fixed header's spacer: the one-row bar, and the front page stack worked out from the same tokens.
	$has( '/twd-sk-chrome--sticky\.twd-sk-chrome--sticky\.twd-sk-chrome--wordmark\.twd-sk-chrome--wordmark \{[^}]*height: calc\(max\(var\(--twd-site-wordmark-symbol\), var\(--twd-site-menu-size\) \* 1\.3 \+ 20px\) \+ 29px \+ var\(--twd-sk-wm-strip\)\)/', 'the bar spacer' );
	$has( '/twd-sk-chrome--sticky\.twd-sk-chrome--sticky\.twd-sk-chrome--wordmark\.twd-sk-chrome--wordmark\.twd-sk-chrome--wm-front\.twd-sk-chrome--wm-front \{[^}]*height: calc\(var\(--twd-sk-wm-s\) \+ var\(--twd-sk-wm-n\) \* 1\.2 \+ var\(--twd-sk-wm-l\) \* 1\.3 \+ var\(--twd-site-menu-size\) \* 1\.3 \+ var\(--twd-sk-wm-pad\) \+ var\(--twd-sk-wm-strip\)\)/', 'the front page spacer' );
	$has( '/twd-sk-chrome--wordmark\.twd-sk-chrome--wordmark \{[^}]*--twd-sk-wm-s: var\(--twd-site-wordmark-symbol-front\)/', 'the front page spacer uses the front sizes' );
	$has( '/twd-sk-chrome--noline\.twd-sk-chrome--noline \{[^}]*--twd-sk-wm-l: 0px/', 'no second line' );
	twd_sk_true( false !== strpos( $raw, 'twd-sk-chrome--sticky.twd-sk-chrome--sticky.twd-sk-chrome--wordmark.twd-sk-chrome--wordmark.twd-sk-chrome--over.twd-sk-chrome--over' ), 'over a picture the wrapper has no height, for the wordmark header too' );
	// Less motion: the rule that cancels the scroll transition comes after the transition it cancels.
	$last_motion = strrpos( $raw, '@media (prefers-reduced-motion: reduce)' );
	$transition  = strrpos( $raw, 'transition: padding .2s ease' );
	twd_sk_true( false !== $last_motion && false !== $transition && $last_motion > $transition, 'reduced motion wins the tie' );
	foreach ( array( 'twd-sk-header__mark', 'twd-sk-header__name', 'twd-sk-header__line', 'twd-sk-header--wordmark', 'twd-sk-header--wm-front', 'twd-sk-header--wm-name', 'twd-sk-header--mark-none', 'twd-sk-chrome--noline' ) as $class ) {
		twd_sk_has( '.' . $class . '.' . $class, $raw, $class );
	}
} );

twd_sk_test( 'wordmark: the shrink script waits for the spacer to scroll up to the compact row (no blank band), and every other header still shrinks after 60px', function () {
	$js = file_get_contents( ABSPATH . 'assets/twd-site-kit-header-shrink.js' );
	twd_sk_has( 'var SHRINK_AT = 60;', $js );
	twd_sk_has( 'var GROW_AT = 20;', $js );
	twd_sk_has( "wrap.classList.contains('twd-sk-chrome--wordmark')", $js );
	twd_sk_has( 'Math.max(SHRINK_AT, wrap.offsetHeight - compact())', $js );
	twd_sk_has( "'--twd-site-wordmark-symbol' + name", $js, 'a larger symbol gives a taller compact row' );
	twd_sk_has( 'var COMPACT = 56;', $js, 'and the standard symbol still gives the 56px it always did' );
	twd_sk_has( 'if (!shrunk && y > shrinkAt()) {', $js );
	twd_sk_hasnt( 'innerHTML', $js );
} );

twd_sk_test( 'wordmark tokens: in every pack and as defaults, sized as asked, valid, the menu font follows the pack, and an older pack still reads', function () {
	$want = array(
		'wordmark-symbol-front' => '72px', 'wordmark-symbol-front-sm' => '44px', 'wordmark-symbol' => '36px', 'wordmark-symbol-sm' => '32px',
		'wordmark-name-front' => '26px', 'wordmark-name-front-sm' => '20px', 'wordmark-name' => '18px', 'wordmark-name-sm' => '18px',
		'wordmark-line-front' => '12px', 'wordmark-line-front-sm' => '12px',
		'wordmark-name-spacing' => '0.18em', 'wordmark-line-spacing' => '0.32em',
		'menu-size' => '13px', 'menu-weight' => '400', 'menu-spacing' => '0.12em', 'menu-gap' => '26px', 'menu-transform' => 'uppercase',
	);
	foreach ( TWD_SK_Packs::packs() as $slug => $pack ) {
		$t = $pack['tokens'];
		foreach ( $want as $name => $value ) {
			twd_sk_eq( $value, $t[ $name ], $slug . ' ' . $name );
		}
		twd_sk_eq( $t['font-body'], $t['menu-font'], $slug . ' the menu is in the pack\'s body font' );
		twd_sk_true( (int) $t['wordmark-name-sm'] >= 18 && (int) $t['wordmark-name-front-sm'] >= 18, $slug . ' the phone name is readable' );
		twd_sk_true( (int) $t['wordmark-name-front'] > (int) $t['menu-size'] * 1.5 && (int) $t['wordmark-name'] > (int) $t['menu-size'], $slug . ' the name is clearly larger than the menu' );
		twd_sk_true( TWD_SK_Contrast::ratio( $t['color-heading'], $t['color-surface'] ) >= 4.5, $slug . ' heading ink on the header' );
	}
	foreach ( $want as $name => $value ) {
		twd_sk_eq( $value, TWD_SK_Packs::defaults()[ $name ], 'default ' . $name );
		twd_sk_true( TWD_SK_Packs::valid_token( $name, $value ), $name . ' is a valid value' );
	}
	foreach ( array( array( 'menu-weight', '450' ), array( 'menu-weight', 'bold' ), array( 'menu-size', '-1px' ), array( 'wordmark-name', '2vw' ), array( 'wordmark-name-spacing', 'wide' ), array( 'menu-transform', 'lowercase' ), array( 'menu-transform', 'capitalize' ), array( 'menu-font', 'Comic Sans' ) ) as $bad ) {
		twd_sk_true( ! TWD_SK_Packs::valid_token( $bad[0], $bad[1] ), $bad[0] . ' ' . $bad[1] . ' is refused' );
	}
	twd_sk_true( TWD_SK_Packs::valid_token( 'menu-transform', 'none' ) && TWD_SK_Packs::valid_token( 'menu-font', 'Lora' ), 'the other menu look: not capitals, in a serif' );
	// The menu font is written as a real font stack, and its file is loaded even when it is neither of the pack's two fonts.
	$tokens = TWD_SK_Packs::packs()['sage']['tokens'];
	twd_sk_has( '--twd-site-menu-font:"Jost", "Helvetica Neue", Arial, sans-serif;', TWD_SK_Packs::tokens_css( $tokens ) );
	$tokens['menu-font'] = 'Lora';
	twd_sk_has( '--twd-site-menu-font:"Lora", Georgia', TWD_SK_Packs::tokens_css( $tokens ) );
	twd_sk_has( 'font-family:"Lora";font-style:normal', TWD_SK_Packs::font_face_css( $tokens, 'https://example.test/fonts/' ) );
	// A pack from before the wordmark still reads, and its menu takes its own body font.
	$old = json_decode( file_get_contents( ABSPATH . 'packs/sage.json' ), true );
	$old['slug'] = 'older2';
	foreach ( array_merge( array_keys( $want ), array( 'menu-font' ) ) as $name ) {
		unset( $old['tokens'][ $name ] );
	}
	$pack = TWD_SK_Packs::read_pack_file( twd_sk_write_pack( 'older2', $old ) );
	twd_sk_true( is_array( $pack ), 'a pack from before the wordmark still reads' );
	twd_sk_eq( '18px', $pack['tokens']['wordmark-name'] );
	twd_sk_eq( 'Jost', $pack['tokens']['menu-font'], 'its menu is in its body font' );
	twd_sk_eq( count( TWD_SK_Packs::token_names() ), count( $pack['tokens'] ) );
} );

// The second line in the smaller header (0.15.0). A setting, off by default; with it off every header prints the same bytes as before.

twd_sk_test( 'second line in the smaller header: off by default and validated; on, it brings the scrolled name with it', function () {
	$d = TWD_SK_Chrome::defaults();
	twd_sk_true( false === $d['wordmark_small_line'], 'off by default' );
	twd_sk_is_error( 'twd_sk_bad_chrome', TWD_SK_Chrome::save_settings( array( 'wordmark_small_line' => 'yes' ) ) );
	twd_sk_chrome_setup( twd_sk_wm_profile(), array( 'wordmark' => true, 'wordmark_scrolled_name' => false, 'wordmark_small_line' => false ) );
	$e = TWD_SK_Chrome::effective();
	twd_sk_true( ! $e['wm_line'] && ! $e['wm_name'], 'off: neither' );
	TWD_SK_Chrome::save_settings( array( 'wordmark_small_line' => true ) );
	$e = TWD_SK_Chrome::effective();
	twd_sk_true( $e['wm_line'] && $e['wm_name'], 'on: the line, and the name beside the symbol when the front page is scrolled' );
	TWD_SK_Chrome::save_settings( array( 'wordmark' => false ) );
	$e = TWD_SK_Chrome::effective();
	twd_sk_true( ! $e['wm_line'] && ! $e['wm_name'], 'it does nothing without the text wordmark' );
	TWD_SK_Chrome::save_settings( array( 'wordmark' => false, 'wordmark_small_line' => false ) );
} );

twd_sk_test( 'second line in the smaller header: off prints exactly what it did, on prints the words block and the classes the stylesheet needs', function () {
	twd_sk_chrome_setup( twd_sk_wm_profile(), array( 'wordmark' => true, 'sticky' => true, 'shrink' => true, 'wordmark_small_line' => false ) );
	$off = array();
	foreach ( array( false, true ) as $front ) {
		$GLOBALS['twd_stub']['front'] = $front;
		$off[ (int) $front ] = TWD_SK_Chrome::render_header();
		twd_sk_hasnt( 'wm-line', $off[ (int) $front ] );
		twd_sk_hasnt( 'twd-sk-header__words', $off[ (int) $front ] );
	}
	twd_sk_hasnt( 'twd-sk-header__line', $off[0], 'off: no second line on other pages, as before' );
	// On, any page but the front: the bar, the name and the line together in one block beside the symbol, in one link.
	TWD_SK_Chrome::save_settings( array( 'wordmark_small_line' => true ) );
	$GLOBALS['twd_stub']['front'] = false;
	$html = TWD_SK_Chrome::render_header();
	twd_sk_has( 'twd-sk-header--wordmark twd-sk-header--mark-dot twd-sk-header--bar twd-sk-header--wm-line twd-sk-header--sticky twd-sk-header--shrink', $html );
	twd_sk_has( '<a class="twd-sk-header__logo" href="https://example.test/"><img class="twd-sk-header__mark" src="/u/mark.png" alt="" width="120" height="120" decoding="async"><span class="twd-sk-header__words"><span class="twd-sk-header__name">Your Name</span><span class="twd-sk-header__line">Therapy</span></span></a>', $html );
	twd_sk_has( 'twd-sk-chrome--sticky twd-sk-chrome--wordmark twd-sk-chrome--wm-line"', $html, 'the spacer knows the bar holds two lines of words' );
	twd_sk_eq( 1, substr_count( $html, 'Your Name' ), 'the name is still said once' );
	twd_sk_eq( 1, substr_count( $html, 'Therapy' ) );
	// The front page: the same block inside the stack, and the scrolled name comes with it.
	$GLOBALS['twd_stub']['front'] = true;
	$html = TWD_SK_Chrome::render_header();
	twd_sk_has( 'twd-sk-header--wm-front twd-sk-header--wm-name twd-sk-header--wm-line', $html );
	twd_sk_has( '<span class="twd-sk-header__words"><span class="twd-sk-header__name">Your Name</span><span class="twd-sk-header__line">Therapy</span></span>', $html );
	twd_sk_has( 'twd-sk-chrome--wordmark twd-sk-chrome--wm-front twd-sk-chrome--wm-line"', $html );
	// No second line to show: nothing changes, so there is no block and no class for an empty line.
	twd_sk_chrome_setup( twd_sk_wm_profile( array( 'logo_line' => '' ) ), array( 'wordmark' => true, 'sticky' => true, 'wordmark_small_line' => true ) );
	foreach ( array( false, true ) as $front ) {
		$GLOBALS['twd_stub']['front'] = $front;
		$html = TWD_SK_Chrome::render_header();
		twd_sk_hasnt( 'wm-line', $html );
		twd_sk_hasnt( 'twd-sk-header__words', $html );
		twd_sk_hasnt( 'twd-sk-header__line', $html );
	}
	// Escaped like every other text in the header.
	twd_sk_chrome_setup( twd_sk_wm_profile( array( 'logo_line' => 'Q <b>"x"</b>' ) ), array( 'wordmark' => true, 'wordmark_small_line' => true ) );
	$GLOBALS['twd_stub']['front'] = false;
	twd_sk_hasnt( '<b>', TWD_SK_Chrome::render_header() );
	TWD_SK_Chrome::save_settings( array( 'wordmark' => false, 'wordmark_small_line' => false, 'sticky' => false, 'shrink' => false ) );
	$GLOBALS['twd_stub']['front'] = false;
} );

twd_sk_test( 'second line in the smaller header: every printed class is styled, the line is one row, the spacer counts it, and a larger symbol is a Site tab choice', function () {
	$raw = file_get_contents( ABSPATH . 'assets/twd-site-kit.css' );
	foreach ( array( 'twd-sk-header--wm-line', 'twd-sk-header__words', 'twd-sk-chrome--wm-line' ) as $class ) {
		twd_sk_has( '.' . $class . '.' . $class, $raw, $class );
		twd_sk_true( in_array( $class, TWD_SK_Chrome::classes(), true ), $class . ' is on the list the stylesheet tests check' );
	}
	$line = 'twd-sk-header--wm-line\.twd-sk-header--wm-line\.twd-sk-header--bar\.twd-sk-header--bar \.twd-sk-header__line\.twd-sk-header__line';
	twd_sk_true( 1 === preg_match( '/' . $line . '[^{]*\{[^}]*margin: 2px 0 0 !important[^}]*white-space: nowrap[^}]*overflow: clip/', $raw ), 'the line stays on one row, so the fixed height holds' );
	twd_sk_true( 1 === preg_match( '/twd-sk-header--wm-front\.twd-sk-header--wm-front\.twd-sk-header--wm-line\.twd-sk-header--wm-line\.twd-sk-header--shrunk\.twd-sk-header--shrunk \.twd-sk-header__line\.twd-sk-header__line/', $raw ), 'and so does the scrolled front page' );
	// The spacer: the bar is the taller of the symbol, a menu link and the name with the line; the front page keeps its own formula (its rule comes later).
	$bar   = strpos( $raw, 'twd-sk-chrome--wordmark.twd-sk-chrome--wordmark.twd-sk-chrome--wm-line.twd-sk-chrome--wm-line {' );
	$front = strpos( $raw, 'twd-sk-chrome--wordmark.twd-sk-chrome--wordmark.twd-sk-chrome--wm-front.twd-sk-chrome--wm-front {' );
	twd_sk_true( false !== $bar && false !== $front && $bar < $front, 'the front page spacer rule comes after, so it wins the tie' );
	twd_sk_true( 1 === preg_match( '/twd-sk-chrome--wm-line\.twd-sk-chrome--wm-line \{\s*height: calc\(max\(var\(--twd-site-wordmark-symbol\), var\(--twd-site-menu-size\) \* 1\.3 \+ 20px, var\(--twd-site-wordmark-name\) \* 1\.2 \+ 2px \+ var\(--twd-site-wordmark-line-front\) \* 1\.3\) \+ 29px/', $raw ), 'the bar spacer counts the name and line' );
	// A phone: the spacer follows the phone symbol, never less than the 48px menu button it always allowed for.
	twd_sk_true( 1 === preg_match( '/@media \(max-width: 900px\)\s*\{[^@]*height: calc\(max\(48px, var\(--twd-site-wordmark-symbol-sm\)\) \+ 29px \+ var\(--twd-sk-wm-strip\)\)/', $raw ), 'phones: 77px with the standard symbol, more with a larger one' );
	// The symbol sizes are Site tab choices (no pack changes: every site keeps its look until someone chooses).
	$names = TWD_SK_Site::editable_names();
	twd_sk_true( in_array( 'wordmark-symbol', $names, true ) && in_array( 'wordmark-symbol-sm', $names, true ), 'the symbol heights are editable' );
	twd_sk_eq( '36px', TWD_SK_Packs::defaults()['wordmark-symbol'], 'and the standard size is unchanged' );
	$res = TWD_SK_Site::apply( null, array( 'wordmark-symbol' => '54px', 'wordmark-symbol-sm' => '48px' ) );
	twd_sk_true( ! is_wp_error( $res ), 'a larger symbol saves' );
	twd_sk_has( '--twd-site-wordmark-symbol:54px;', TWD_SK_Packs::tokens_css( TWD_SK_Packs::active_tokens() ) );
	TWD_SK_Site::reset();
	twd_sk_hasnt( '--twd-site-wordmark-symbol:54px;', TWD_SK_Packs::tokens_css( TWD_SK_Packs::active_tokens() ) );
} );

twd_sk_test( 'second line in the smaller header: in the site brief, a style snapshot, the command line, the site report and the Site tab script', function () {
	twd_sk_true( array_key_exists( 'wordmark_small_line', TWD_SK_Brief::export()['header'] ), 'the brief export has it' );
	$plan = TWD_SK_Brief::plan( array( 'schema' => 'twd-site-brief/1', 'header' => array( 'wordmark' => true, 'wordmark_small_line' => true ) ), false );
	twd_sk_eq( array( 'wordmark' => true, 'wordmark_small_line' => true ), $plan['changes']['chrome'] );
	twd_sk_true( array_key_exists( 'wordmark_small_line', TWD_SK_Style::current()['chrome'] ), 'a snapshot keeps it' );
	WP_CLI::reset();
	( new TWD_SK_CLI() )->style( array( 'apply' ), array( 'wordmark' => true, 'small-line' => 'on' ) );
	twd_sk_true( TWD_SK_Chrome::settings()['wordmark'] && TWD_SK_Chrome::settings()['wordmark_small_line'] );
	twd_sk_has( 'second line in the smaller header: yes', TWD_SK_Doctor::report() );
	( new TWD_SK_CLI() )->style( array( 'apply' ), array( 'small-line' => 'off' ) );
	twd_sk_true( ! TWD_SK_Chrome::settings()['wordmark_small_line'] );
	TWD_SK_Chrome::save_settings( array( 'wordmark' => false, 'wordmark_small_line' => false ) );
	$js = file_get_contents( ABSPATH . 'assets/twd-site-kit-editor-site.js' );
	foreach ( array( 'twd-sk-ch-wmline', 'wordmark_small_line: cui.wmline.checked', 'cui.wmline.checked = !!c.settings.wordmark_small_line' ) as $needle ) {
		twd_sk_has( $needle, $js, $needle );
	}
	twd_sk_has( 'cui.wordmark.checked, cui.wmname.checked, cui.wmline.checked, cui.clientFooter.checked, cui.marker.value', $js, 'an unsaved tick counts as an unsaved change' );
} );
