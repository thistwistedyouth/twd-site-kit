<?php
// 0.14.0 layouts: the parallax hero and its options, link rows, timeline steps, two-column questions,
// the logos grid, hours in a card, areas split. Each is a modifier class on the same pieces, so an
// older page is untouched and a restyle can swap between them.

twd_sk_test( 'layouts 0.14: every new layout passes the cleaner unchanged, has no h1 except the hero, and its modifier class is styled', function () {
	$new = array(
		'hero'     => 'twd-sk-hero--parallax',
		'services' => 'twd-sk-services--links',
		'steps'    => 'twd-sk-steps--timeline',
		'faq'      => 'twd-sk-faq--two',
		'logos'    => 'twd-sk-logos--grid',
		'hours'    => 'twd-sk-hours--card',
		'areas'    => 'twd-sk-areas--split',
	);
	$raw = file_get_contents( ABSPATH . 'assets/twd-site-kit.css' );
	foreach ( $new as $id => $class ) {
		$v = TWD_SK_Registry::get( $id )['variants'][ $class ];
		twd_sk_true( is_string( $v['label'] ) && '' !== $v['label'], $class . ' has a label' );
		twd_sk_has( ' ' . $class, $v['skeleton'] );
		twd_sk_eq( $v['skeleton'], TWD_SK_Sanitizer::clean( $v['skeleton'] ), $class . ' survives the cleaner' );
		twd_sk_eq( 'hero' === $id ? 1 : 0, substr_count( $v['skeleton'], '<h1' ), $class . ' h1 count' );
		twd_sk_has( '.' . $class . '.' . $class, $raw );
		twd_sk_true( in_array( $class, TWD_SK_Registry::get( $id )['classes'], true ), $class . ' listed' );
		// Accepted as a page part and offered in the pop-up.
		$parts = TWD_SK_Recipes::clean_parts( 'hero' === $id ? array( array( 'hero', $class ) ) : array( array( 'hero', '' ), array( $id, $class ) ) );
		twd_sk_eq( $class, $parts[ 'hero' === $id ? 0 : 1 ][1], $class . ' as a part' );
	}
} );

twd_sk_test( 'layouts 0.14: the parallax hero is the image hero plus one class; the overlay and left options are extra classes the cleaner keeps and the stylesheet styles', function () {
	$p = TWD_SK_Registry::get( 'hero' )['variants']['twd-sk-hero--parallax']['skeleton'];
	twd_sk_has( 'class="twd-sk-hero twd-sk-hero--image twd-sk-hero--parallax"', $p );
	twd_sk_has( 'twd-sk-hero__bg', $p );
	$html = str_replace( 'twd-sk-hero--parallax', 'twd-sk-hero--parallax twd-sk-hero--overlay-strong twd-sk-hero--left', $p );
	twd_sk_eq( $html, TWD_SK_Sanitizer::clean( $html ), 'the option classes stay' );
	$html = str_replace( 'twd-sk-hero--parallax', 'twd-sk-hero--overlay-light twd-sk-hero--made-up', $p );
	$out  = TWD_SK_Sanitizer::clean( $html );
	twd_sk_has( 'twd-sk-hero--overlay-light', $out );
	twd_sk_hasnt( 'twd-sk-hero--made-up', $out );
	$raw = file_get_contents( ABSPATH . 'assets/twd-site-kit.css' );
	twd_sk_true( 1 === preg_match( '/twd-sk-hero--overlay-light\.twd-sk-hero--overlay-light \.twd-sk-hero__overlay\.twd-sk-hero__overlay \{[^}]*opacity: 0\.55/', $raw ), 'lighter overlay' );
	twd_sk_true( 1 === preg_match( '/twd-sk-hero--overlay-strong\.twd-sk-hero--overlay-strong \.twd-sk-hero__overlay\.twd-sk-hero__overlay::after \{[^}]*background-color: var\(--twd-site-color-overlay-mid\)/', $raw ), 'stronger overlay is a second layer of the pack colour' );
	twd_sk_true( 1 === preg_match( '/twd-sk-hero--left\.twd-sk-hero--left \.twd-sk-hero__title\.twd-sk-hero__title[^{]*\{[^}]*text-align: left/', $raw ), 'words to the left' );
	twd_sk_true( strpos( $raw, '.twd-sk-hero--left.twd-sk-hero--left .twd-sk-hero__title' ) > strpos( $raw, '.twd-sk-hero--image.twd-sk-hero--image .twd-sk-hero__title' ), 'the left rule comes after the centred one, so it wins' );
	twd_sk_true( 1 === preg_match( '/twd-sk-hero--parallax\.twd-sk-hero--parallax \.twd-sk-hero__bg\.twd-sk-hero__bg \{[^}]*transform: translate3d\(0, var\(--twd-sk-parallax, 0px\), 0\)/', $raw ), 'the picture reads one variable' );
	twd_sk_true( 1 === preg_match( '/prefers-reduced-motion: reduce\) \{[^@]*twd-sk-hero--parallax[^}]*transform: none/s', $raw ), 'still for less motion' );
	twd_sk_true( 1 === preg_match( '/max-width: 768px\) \{.*?twd-sk-hero--parallax\.twd-sk-hero--parallax \.twd-sk-hero__bg\.twd-sk-hero__bg \{[^}]*transform: none/s', $raw ), 'still on a phone' );
} );

twd_sk_test( 'layouts 0.14: the hero script loads only on a page with a parallax hero, never in safe mode, builds nothing from text, respects less motion and narrow screens, and is plain ASCII', function () {
	twd_stub_reset();
	TWD_SK_Safe::set( false );
	TWD_SK_Page::enqueue_extras( TWD_SK_Registry::get( 'hero' )['variants']['twd-sk-hero--image']['skeleton'] );
	twd_sk_true( ! isset( $GLOBALS['twd_stub']['scripts']['twd-site-kit-hero'] ), 'not for the plain image hero' );
	TWD_SK_Page::enqueue_extras( TWD_SK_Registry::get( 'hero' )['variants']['twd-sk-hero--parallax']['skeleton'] );
	twd_sk_true( $GLOBALS['twd_stub']['scripts']['twd-site-kit-hero']['enqueued'] );
	twd_stub_reset();
	TWD_SK_Safe::set( true );
	TWD_SK_Page::enqueue_extras( 'twd-sk-hero--parallax' );
	twd_sk_true( ! isset( $GLOBALS['twd_stub']['scripts']['twd-site-kit-hero'] ) );
	TWD_SK_Safe::set( false );
	$js = file_get_contents( ABSPATH . 'assets/twd-site-kit-hero.js' );
	foreach ( array( 'innerHTML', 'outerHTML', 'insertAdjacentHTML', 'eval(', 'new Function', 'document.write', 'setInterval', 'fetch(', 'XMLHttpRequest', 'http://', 'https://' ) as $bad ) {
		twd_sk_hasnt( $bad, $js );
	}
	twd_sk_has( 'prefers-reduced-motion: reduce', $js );
	twd_sk_has( 'min-width: 769px', $js );
	twd_sk_has( "'--twd-sk-parallax'", $js );
	twd_sk_has( 'requestAnimationFrame', $js );
	twd_sk_true( 1 !== preg_match( '/[^\x00-\x7F]/', $js ), 'ASCII only' );
	twd_sk_true( false === strpos( $js, "\xE2\x80\x94" ) && false === strpos( $js, "\xE2\x80\x93" ) );
} );

twd_sk_test( 'layouts 0.14: a restyle can swap steps to the timeline and back, and questions to two columns, with the words unchanged', function () {
	$steps = TWD_SK_Registry::get( 'steps' )['skeleton'];
	$r     = TWD_SK_Style::swap( $steps, 'steps', '', 'twd-sk-steps--timeline' );
	twd_sk_eq( 1, $r['changed'] );
	twd_sk_eq( TWD_SK_Registry::get( 'steps' )['variants']['twd-sk-steps--timeline']['skeleton'], $r['html'] );
	twd_sk_eq( $steps, TWD_SK_Style::swap( $r['html'], 'steps', 'twd-sk-steps--timeline', '' )['html'] );
	$faq = TWD_SK_Registry::get( 'faq' )['skeleton'];
	$r   = TWD_SK_Style::swap( $faq, 'faq', '', 'twd-sk-faq--two' );
	twd_sk_eq( 1, $r['changed'] );
	twd_sk_eq( strip_tags( $faq ), strip_tags( $r['html'] ) );
	// The groups layout holds pieces the plain and two-column layouts have no place for.
	$g = TWD_SK_Registry::get( 'faq' )['variants']['twd-sk-faq--groups']['skeleton'];
	$r = TWD_SK_Style::swap( $g, 'faq', 'twd-sk-faq--groups', 'twd-sk-faq--two' );
	twd_sk_eq( 0, $r['changed'] );
	twd_sk_eq( 1, count( $r['cannot'] ) );
} );

twd_sk_test( 'layouts 0.14: the links layout is a list of linked rows, each with the sample link and wording flagged', function () {
	$v = TWD_SK_Registry::get( 'services' )['variants']['twd-sk-services--links']['skeleton'];
	twd_sk_eq( 4, substr_count( $v, '<li class="twd-sk-services__row"><a class="twd-sk-services__link" href="/service-' ) );
	$left = TWD_SK_Sanitizer::find_leftovers( $v );
	twd_sk_true( isset( $left['/service-N'] ) && isset( $left['Service name here'] ) && isset( $left['Short description here'] ) );
	$raw = file_get_contents( ABSPATH . 'assets/twd-site-kit.css' );
	twd_sk_true( 1 === preg_match( '/twd-sk-services__link\.twd-sk-services__link::after \{[^}]*content: "\\\\2192"/', $raw ), 'an arrow drawn by the stylesheet, not typed into the page' );
} );

twd_sk_test( 'transparent header (0.14.0): off by default; on, it prints only on a page that opens with a full-width picture hero; the fixed header keeps its position rule; the setting is validated', function () {
	twd_stub_reset();
	TWD_SK_Safe::set( false );
	twd_sk_true( ! TWD_SK_Chrome::defaults()['overlay'], 'off by default' );
	$GLOBALS['twd_stub']['attachments'][50] = array( 'url' => '/u/logo.png', 'w' => 200, 'h' => 80, 'is_image' => true );
	twd_sk_chrome_setup( twd_sk_chrome_profile( array( 'logo_id' => 50 ) ), array( 'overlay' => false, 'sticky' => false ) );
	twd_stub_add_post( 61, 'page', '[twd_page]' );
	TWD_SK_Store::save( 61, TWD_SK_Registry::get( 'hero' )['variants']['twd-sk-hero--image']['skeleton'] . TWD_SK_Registry::get( 'text' )['skeleton'] );
	twd_stub_add_post( 62, 'page', '[twd_page]' );
	TWD_SK_Store::save( 62, TWD_SK_Registry::get( 'hero' )['skeleton'] );
	$GLOBALS['twd_stub']['queried'] = 61;
	twd_sk_true( TWD_SK_Chrome::opens_with_picture() );
	twd_sk_hasnt( 'twd-sk-header--over', TWD_SK_Chrome::render_header(), 'off: nothing new' );
	TWD_SK_Chrome::save_settings( array( 'overlay' => true ) );
	$html = TWD_SK_Chrome::render_header();
	twd_sk_has( ' twd-sk-header--over', $html );
	twd_sk_has( 'twd-sk-chrome twd-sk-chrome--over', $html, 'the wrapper has no height' );
	// A page that opens with the split hero, or with no kit page at all, gets the normal header.
	$GLOBALS['twd_stub']['queried'] = 62;
	twd_sk_true( ! TWD_SK_Chrome::opens_with_picture() );
	twd_sk_hasnt( 'twd-sk-header--over', TWD_SK_Chrome::render_header() );
	$GLOBALS['twd_stub']['queried'] = 0;
	$GLOBALS['twd_stub']['post_id'] = 0;
	twd_sk_hasnt( 'twd-sk-header--over', TWD_SK_Chrome::render_header() );
	// The parallax hero counts as a picture hero. With the fixed header both classes print.
	TWD_SK_Store::save( 62, TWD_SK_Registry::get( 'hero' )['variants']['twd-sk-hero--parallax']['skeleton'] );
	$GLOBALS['twd_stub']['queried'] = 62;
	TWD_SK_Chrome::save_settings( array( 'sticky' => true ) );
	$html = TWD_SK_Chrome::render_header();
	twd_sk_has( 'twd-sk-header--sticky twd-sk-header--over', $html );
	twd_sk_has( 'twd-sk-chrome--sticky twd-sk-chrome--over', $html );
	twd_sk_true( is_wp_error( TWD_SK_Chrome::save_settings( array( 'overlay' => 'yes' ) ) ) );
	twd_sk_true( in_array( 'twd-sk-header--over', TWD_SK_Chrome::classes(), true ) && in_array( 'twd-sk-chrome--over', TWD_SK_Chrome::classes(), true ) );
	// Stylesheet: absolute (never a third fixed rule), on-image colour, solid again once shrunk, a solid phone panel.
	$raw = file_get_contents( ABSPATH . 'assets/twd-site-kit.css' );
	twd_sk_true( 1 === preg_match( '/\.twd-sk-page \.twd-sk-header--over\.twd-sk-header--over \{[^}]*position: absolute;[^}]*background-color: transparent !important;[^}]*color: var\(--twd-site-color-on-image\)/', $raw ), 'floats over the picture in the on-image colour' );
	twd_sk_true( strpos( $raw, '.twd-sk-page .twd-sk-header--over.twd-sk-header--over {' ) < strpos( $raw, '.twd-sk-page .twd-sk-header--sticky.twd-sk-header--sticky {' ), 'the fixed rule comes later, so it wins when both are set' );
	twd_sk_true( 1 === preg_match( '/twd-sk-header--over\.twd-sk-header--over\.twd-sk-header--shrunk\.twd-sk-header--shrunk \{[^}]*background-color: var\(--twd-site-color-surface\)/', $raw ), 'solid again when shrunk' );
	twd_sk_true( 1 === preg_match( '/twd-sk-chrome--over\.twd-sk-chrome--over \{[^}]*height: 0/', $raw ), 'no spacer' );
	twd_sk_has( '.twd-sk-page .twd-sk-header--over.twd-sk-header--over.twd-sk-header--js.twd-sk-header--js .twd-sk-header__panel.twd-sk-header__panel {', $raw );
	// The brief, the site report and the Site tab know the setting.
	twd_sk_true( TWD_SK_Brief::export()['header']['overlay'] );
	twd_sk_has( 'transparent over the picture: yes', implode( "\n", TWD_SK_Doctor::style() ) );
	$js = file_get_contents( ABSPATH . 'assets/twd-site-kit-editor-site.js' );
	twd_sk_has( 'overlay: cui.over.checked', $js );
	twd_sk_has( "'twd-sk-ch-over'", $js );
	TWD_SK_Chrome::save_settings( array( 'overlay' => false, 'sticky' => false ) );
} );

twd_sk_test( 'picture treatment tokens (0.14.0): image-filter and image-tint have defaults so an older pack still reads, are validated strictly, and reach the stylesheet', function () {
	twd_sk_eq( 'none', TWD_SK_Packs::defaults()['image-filter'] );
	twd_sk_eq( 'transparent', TWD_SK_Packs::defaults()['image-tint'] );
	foreach ( array( 'none', 'saturate(0.9)', 'grayscale(1)', 'sepia(0.08) saturate(0.85)', 'contrast(105%) brightness(0.98) saturate(1)' ) as $v ) {
		twd_sk_true( TWD_SK_Packs::valid_token( 'image-filter', $v ), $v );
	}
	foreach ( array( '', 'blur(4px)', 'url(x)', 'saturate(0.9);', 'saturate(3)', 'saturate(0.9) saturate(0.9) saturate(0.9) saturate(0.9)', 'drop-shadow(0 0 1px red)', 'SATURATE(1)' ) as $v ) {
		twd_sk_true( ! TWD_SK_Packs::valid_token( 'image-filter', $v ), 'refused: ' . $v );
	}
	twd_sk_true( TWD_SK_Packs::valid_token( 'image-tint', 'rgba(154,74,50,0.1)' ) );
	twd_sk_true( TWD_SK_Packs::valid_token( 'image-tint', 'transparent' ) );
	twd_sk_true( ! TWD_SK_Packs::valid_token( 'image-tint', 'url(x)' ) );
	// An older pack file without them reads with the defaults.
	$old = json_decode( file_get_contents( ABSPATH . 'packs/sage.json' ), true );
	unset( $old['tokens']['image-filter'], $old['tokens']['image-tint'] );
	$dir = sys_get_temp_dir() . '/twdsk-pack-' . uniqid();
	mkdir( $dir );
	file_put_contents( $dir . '/old.json', json_encode( array_merge( $old, array( 'slug' => 'old' ) ) ) );
	$read = TWD_SK_Packs::read_pack_file( $dir . '/old.json' );
	twd_sk_eq( 'none', $read['tokens']['image-filter'] );
	twd_sk_eq( 'transparent', $read['tokens']['image-tint'] );
	unlink( $dir . '/old.json' );
	rmdir( $dir );
	$raw = file_get_contents( ABSPATH . 'assets/twd-site-kit.css' );
	twd_sk_has( 'filter: var(--twd-site-image-filter)', $raw );
	twd_sk_true( 1 === preg_match( '/twd-sk-hero__media\.twd-sk-hero__media::after[^{]*\{[^}]*background-color: var\(--twd-site-image-tint\)/', $raw ), 'a tint laid over the picture' );
	twd_sk_has( '--twd-site-image-filter:none', TWD_SK_Packs::tokens_css( TWD_SK_Packs::packs()['sage']['tokens'] ) );
} );

twd_sk_test( 'packs ember and slate (0.14.0): warm editorial and bold modern, bundled fonts only, every pair readable, valid layouts, ember tints its pictures', function () {
	$e = TWD_SK_Packs::packs()['ember'];
	$s = TWD_SK_Packs::packs()['slate'];
	twd_sk_eq( 'Fraunces', $e['tokens']['font-heading'] );
	twd_sk_eq( 'Work Sans', $e['tokens']['font-body'] );
	twd_sk_eq( 'DM Sans', $s['tokens']['font-heading'] );
	twd_sk_eq( 'Nunito Sans', $s['tokens']['font-body'] );
	twd_sk_eq( 'saturate(0.9)', $e['tokens']['image-filter'] );
	twd_sk_has( 'rgba(154,74,50', $e['tokens']['image-tint'] );
	twd_sk_eq( 'none', $s['tokens']['image-filter'] );
	twd_sk_eq( '700', $s['tokens']['heading-weight'] );
	foreach ( array( $e, $s ) as $pack ) {
		$c = TWD_SK_Contrast::check( $pack['tokens'] );
		twd_sk_eq( array(), $c['blocking'], $pack['slug'] . ' blocking' );
		twd_sk_eq( array(), $c['warnings'], $pack['slug'] . ' warnings' );
		twd_sk_true( isset( TWD_SK_Chrome::header_variants()[ $pack['chrome']['header'] ] ), $pack['slug'] . ' header layout' );
		twd_sk_true( isset( TWD_SK_Chrome::footer_variants()[ $pack['chrome']['footer'] ] ), $pack['slug'] . ' footer layout' );
	}
	twd_sk_eq( 'split', $e['chrome']['header'] );
	twd_sk_eq( 'centered', $s['chrome']['footer'] );
	// The site stylesheet declares only the two fonts the active pack uses.
	$css = TWD_SK_Packs::font_face_css( $e['tokens'], 'https://example.test/fonts/' );
	twd_sk_eq( 4, substr_count( $css, '@font-face' ) );
	twd_sk_has( 'fraunces-latin-wght-italic.woff2', $css );
	twd_sk_hasnt( 'Inter', $css );
} );
