<?php
// A menu heading with no link (0.23.1): it only opens its sub-menu links. Uses the helpers in test-chrome.php.

function twd_sk_heading_menu() {
	return array(
		array( 'label' => 'Home', 'url' => '/', 'children' => array() ),
		array( 'label' => 'Therapy', 'url' => '', 'children' => array(
			array( 'label' => 'Therapy Sessions', 'url' => '/therapy' ),
			array( 'label' => 'Extras', 'url' => '/therapy#extras' ),
		) ),
		array( 'label' => 'Contact', 'url' => '/contact', 'children' => array() ),
	);
}

twd_sk_test( 'menu heading: a menu item with no link is kept when it has sub-menu links, and refused when it has none', function () {
	$ok = TWD_SK_Profile::validate( array( 'menu' => twd_sk_heading_menu() ) );
	twd_sk_eq( array(), $ok['errors'] );
	twd_sk_eq( '', $ok['valid']['menu'][1]['url'] );
	twd_sk_eq( 2, count( $ok['valid']['menu'][1]['children'] ) );
	foreach ( array(
		array( array( 'label' => 'Lonely', 'url' => '', 'children' => array() ) ),
		array( array( 'label' => 'Lonely', 'url' => '   ' ) ),
		array( array( 'label' => '', 'url' => '', 'children' => array( array( 'label' => 'A', 'url' => '/a' ) ) ) ),
		array( array( 'label' => 'Bad', 'url' => 'javascript:alert(1)', 'children' => array( array( 'label' => 'A', 'url' => '/a' ) ) ) ),
	) as $menu ) {
		$r = TWD_SK_Profile::validate( array( 'menu' => $menu ) );
		twd_sk_true( count( $r['errors'] ) > 0, 'refused: ' . json_encode( $menu ) );
	}
	// An item with a link is exactly as before, and a blank line is still skipped.
	$r = TWD_SK_Profile::validate( array( 'menu' => array( array( 'label' => 'About', 'url' => '/about', 'children' => array() ), array( 'label' => '', 'url' => '', 'children' => array() ) ) ) );
	twd_sk_eq( array(), $r['errors'] );
	twd_sk_eq( 1, count( $r['valid']['menu'] ) );
} );

twd_sk_test( 'menu heading: the header prints a focusable label, not a link, and its sub-menu links are as before', function () {
	twd_sk_chrome_setup( twd_sk_chrome_profile( array( 'menu' => twd_sk_heading_menu() ) ) );
	$html = TWD_SK_Chrome::render_header();
	twd_sk_has( '<li class="twd-sk-header__item twd-sk-header__item--parent"><span class="twd-sk-header__link twd-sk-header__link--heading" tabindex="0">Therapy</span><ul class="twd-sk-header__sub">', $html );
	twd_sk_has( '<a class="twd-sk-header__sublink" href="/therapy">Therapy Sessions</a>', $html );
	twd_sk_has( 'href="/therapy#extras">Extras</a>', $html );
	twd_sk_hasnt( '<a class="twd-sk-header__link" href="">', $html, 'no empty link' );
	twd_sk_hasnt( 'href=""', $html );
	twd_sk_eq( 2, substr_count( $html, '<a class="twd-sk-header__link"' ), 'Home and Contact stay links' );
} );

twd_sk_test( 'menu heading: every footer layout links the heading to its first sub-menu link', function () {
	$printed = 0;
	foreach ( array( 'columns', 'band', 'centered', 'simple', 'feature', 'compact' ) as $layout ) {
		twd_sk_chrome_setup( twd_sk_chrome_profile( array( 'menu' => twd_sk_heading_menu() ) ), array( 'footer_variant' => $layout ) );
		$html = TWD_SK_Chrome::render_footer();
		twd_sk_hasnt( 'href="">', $html, $layout );
		twd_sk_hasnt( 'href="#"', $html, $layout );
		if ( false === strpos( $html, 'footer__link" href="/contact"' ) ) {
			continue; // This layout prints no menu links at all (the simple footer), so there is nothing to link.
		}
		twd_sk_has( '<a class="twd-sk-footer__link" href="/therapy">Therapy</a>', $html, $layout );
		$printed++;
	}
	twd_sk_true( $printed >= 4, 'the layouts that print menu links all link the heading (' . $printed . ')' );
} );

twd_sk_test( 'menu heading: a menu with no heading prints the same header and footer bytes as before', function () {
	twd_sk_chrome_setup();
	$_SERVER['REQUEST_URI'] = '/';
	$header = TWD_SK_Chrome::render_header();
	$footer = TWD_SK_Chrome::render_footer();
	twd_sk_hasnt( 'twd-sk-header__link--heading', $header );
	twd_sk_eq( 3, substr_count( $header, '<a class="twd-sk-header__link"' ) );
	twd_sk_has( '<li class="twd-sk-header__item twd-sk-header__item--parent"><a class="twd-sk-header__link" href="/services">Services</a><ul class="twd-sk-header__sub">', $header );
	twd_sk_has( '<li><a class="twd-sk-footer__link" href="/about">About</a></li>', $footer );
} );

twd_sk_test( 'menu heading: the class is registered, styled from the source, and the heading is safe in the launch check and the brief', function () {
	twd_sk_true( in_array( 'twd-sk-header__link--heading', TWD_SK_Chrome::classes(), true ) );
	$css = file_get_contents( ABSPATH . 'tools/kit.src.css' );
	twd_sk_has( '.twd-sk-header__link--heading { cursor: default; }', $css );
	$built = file_get_contents( ABSPATH . 'assets/twd-site-kit.css' );
	twd_sk_has( 'twd-sk-header__link--heading', $built );
	$links = TWD_SK_Launch::menu_links( twd_sk_heading_menu() );
	foreach ( $links['missing'] as $line ) {
		twd_sk_true( 0 !== strpos( $line, 'Therapy (' ), 'the heading itself is never reported as a missing link: ' . $line );
	}
	twd_sk_eq( 3, $links['checked'], 'Contact and the two sub-menu links are checked (Home is skipped), the heading is not' );
} );

twd_sk_test( 'menu heading: the Site tab menu box writes and reads a heading as "Label |" with sub-menu lines under it', function () {
	$js = file_get_contents( ABSPATH . 'assets/twd-site-kit-editor-site.js' );
	twd_sk_has( "var headingOk = withChildren && !child;", $js );
	twd_sk_has( '(!line.substr(bar + 1).trim() && !headingOk)', $js );
	twd_sk_has( 'A line with nothing after the bar (Label |) is a heading with no link', $js );
	// The same parser logic, run in node, when node is there.
	$node = trim( (string) @shell_exec( 'node -v 2>&1' ) );
	if ( 0 === strpos( $node, 'v' ) ) {
		preg_match( '/function parseLinks\(text, withChildren\) \{.*?\n\t\}\n/s', $js, $m );
		twd_sk_true( ! empty( $m[0] ), 'parseLinks found' );
		$script = $m[0] . "\n" . 'var a = parseLinks("Home | /\nTherapy |\n- Sessions | /t\nContact | /c", true);'
			. ' var b = parseLinks("Therapy |", false); var c = parseLinks("Home | /\n- Sub |\n", true);'
			. ' console.log(JSON.stringify([a, b.error !== "", c.error !== ""]));';
		$file = sys_get_temp_dir() . '/twdsk-heading-' . getmypid() . '.js';
		file_put_contents( $file, $script );
		$out = trim( (string) shell_exec( 'node ' . escapeshellarg( $file ) . ' 2>&1' ) );
		@unlink( $file );
		$got = json_decode( $out, true );
		twd_sk_true( is_array( $got ), 'node ran: ' . $out );
		twd_sk_eq( '', $got[0]['error'] );
		twd_sk_eq( '', $got[0]['list'][1]['url'] );
		twd_sk_eq( 1, count( $got[0]['list'][1]['children'] ) );
		twd_sk_true( $got[1], 'a heading line with no sub-menu is only allowed in the menu (the server then checks it has sub-menu links)' );
		twd_sk_true( $got[2], 'a sub-menu line must still have a link' );
	}
} );
