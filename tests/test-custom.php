<?php
// Base, Adapt, Replace (0.15.0, section 20 of the owner's plan): the site's own stylesheet (TWD_SK_Css), custom
// sections replaced by hand or by the AI (TWD_SK_Replace), and the labels clients edit.

function twd_sk_custom_page() {
	twd_sk_rest_setup();
	twd_stub_add_post( 51, 'page', '[twd_page]' );
	$GLOBALS['twd_stub']['caps'] = true;
	$html = '';
	foreach ( array( 'hero', 'text', 'steps', 'cta' ) as $id ) {
		$html .= TWD_SK_Registry::get( $id )['skeleton'] . "\n";
	}
	twd_sk_true( ! is_wp_error( TWD_SK_Store::save( 51, $html ) ) );
	return TWD_SK_Store::get_current( 51 );
}
function twd_sk_custom_section() {
	return '<section class="c-intro twd-sk-tone-surface"><div class="twd-sk-inner"><h2 class="c-intro__title" data-twd-edit="Heading">Three ways we can work</h2><div class="c-intro__row"><div class="c-intro__card"><p data-twd-edit="Card one">In person, in a quiet room.</p></div><div class="c-intro__card"><p data-twd-edit="Card two">Online, from wherever you are.</p></div></div><img class="c-intro__pic" src="/wp-content/uploads/your-image.jpg" alt="A quiet room" width="800" height="600" data-twd-pic="Photo"></div></section>';
}
function twd_sk_custom_css() {
	return ".c-intro { padding: var(--twd-site-section-y) 0; background: var(--twd-site-color-surface); }\n.c-intro__title { font-family: var(--twd-site-font-heading); color: var(--twd-site-color-heading); font-size: var(--twd-site-size-h2); }\n.c-intro__row { display: grid; grid-template-columns: 1fr 1fr; gap: var(--twd-site-gap-md); }\n.c-intro__card { padding: var(--twd-site-card-pad); border-radius: var(--twd-site-radius-card); background: var(--twd-site-color-tint); }\n.c-intro__pic { border-radius: var(--twd-site-radius-image); }\n@media (max-width: 767px) { .c-intro__row { grid-template-columns: 1fr; } }\n";
}

twd_sk_test( 'site css: a stylesheet of tokens and c- classes passes, is scoped under .twd-sk-page, lists its classes and keeps its @media block', function () {
	$check = TWD_SK_Css::check( twd_sk_custom_css() );
	twd_sk_true( $check['ok'], implode( ' | ', $check['errors'] ) );
	twd_sk_eq( array( 'c-intro', 'c-intro__title', 'c-intro__row', 'c-intro__card', 'c-intro__pic' ), $check['classes'] );
	twd_sk_has( '.twd-sk-page .c-intro{padding:var(--twd-site-section-y) 0;background:var(--twd-site-color-surface)}', $check['built'] );
	twd_sk_has( '@media (max-width: 767px){.twd-sk-page .c-intro__row{grid-template-columns:1fr}}', $check['built'] );
	twd_sk_hasnt( '!important', $check['built'] );
	// Every selector is scoped: no rule starts at the top level.
	foreach ( explode( '}', str_replace( '@media (max-width: 767px){', '', $check['built'] ) ) as $rule ) {
		if ( '' !== trim( $rule ) ) {
			twd_sk_true( 0 === strpos( ltrim( $rule, '}' ), '.twd-sk-page ' ), 'scoped: ' . $rule );
		}
	}
	twd_sk_eq( array(), $check['warnings'] );
	// A tidy source comes back for storing.
	twd_sk_has( ".c-intro {\n\tpadding: var(--twd-site-section-y) 0;", $check['source'] );
	twd_sk_true( TWD_SK_Css::check( '' )['ok'], 'an empty stylesheet is fine' );
	twd_sk_true( TWD_SK_Css::check( "/* a note */\n.c-a { color: var(--twd-site-color-body); }" )['ok'], 'comments are allowed' );
} );

twd_sk_test( 'site css: typed colours, px font sizes, typed fonts, !important, @import, url() outside the library, expression, scripts, ids, kit classes, unknown tokens, fixed and vw are refused with a plain message, and nothing is applied', function () {
	$cases = array(
		'.c-a { color: #1F5F5B; }'                                 => 'Typed colours are not allowed (#1F5F5B',
		'.c-a { background: rgb(10, 20, 30); }'                    => 'Typed colours are not allowed (rgb()',
		'.c-a { border: 1px solid red; }'                          => 'Typed colours are not allowed ("red"',
		'.c-a { font-size: 18px; }'                                => 'Font sizes in px are not allowed',
		'.c-a { font-family: Georgia, serif; }'                    => 'font-family may only be',
		'.c-a { font: 16px/1.5 Arial; }'                           => 'The font shorthand is not allowed',
		'.c-a { color: var(--twd-site-color-body) !important; }'   => '!important is not allowed',
		'@import url(https://example.com/x.css); .c-a { color: var(--twd-site-color-body); }' => '@import is not allowed',
		'.c-a { background-image: url(https://example.org/x.png); }' => 'url() may point only to a picture in this site',
		'.c-a { width: expression(alert(1)); }'                    => 'expression() is not allowed',
		'.c-a { color: var(--twd-site-color-body); } <script>alert(1)</script>' => 'looks like HTML or a script',
		'#twd-sk-x { color: var(--twd-site-color-body); }'         => 'uses an id (#)',
		'.twd-sk-hero { color: var(--twd-site-color-body); }'      => 'styles the kit\'s own class .twd-sk-hero',
		'p { color: var(--twd-site-color-body); }'                 => 'names no c- class',
		'.c-a { color: var(--twd-site-color-nothing); }'           => 'There is no site token called --twd-site-color-nothing',
		'.c-a { color: var(--my-colour); }'                        => 'Only the site\'s own tokens may be used',
		'.c-a { position: fixed; }'                                => 'position: fixed is not allowed',
		'.c-a { width: 100vw; }'                                   => 'Viewport widths (vw) are not allowed',
		'.c-a { margin-top: -20px; }'                              => 'Negative margins are not allowed',
		'.c-a { behavior: url(x.htc); }'                           => 'behavior is not allowed',
		'.c-a { zoom: 2; }'                                        => 'The property "zoom" is not allowed',
		'.c-a[*] { color: var(--twd-site-color-body); }'           => 'uses an attribute that could not be read',
		'.c-a[data-x="y" i] { color: var(--twd-site-color-body); }' => 'uses an attribute that could not be read',
		'@keyframes spin { to { transform: rotate(1turn); } }'     => '@keyframes is not allowed',
		'@media print { .c-a { display: none; } }'                 => 'The media query "@media print" is not allowed',
		'.c-a { .c-b { color: var(--twd-site-color-body); } }'     => 'holds another rule inside it',
		'.c-a { color: var(--twd-site-color-body); '               => 'missing its closing }',
		'.c-Bad_Name { color: var(--twd-site-color-body); }'       => 'names no c- class',
		'.c-a- { color: var(--twd-site-color-body); }'             => 'is not a valid name',
	);
	foreach ( $cases as $css => $expected ) {
		$check = TWD_SK_Css::check( $css );
		twd_sk_true( ! $check['ok'], 'refused: ' . $css );
		twd_sk_has( $expected, implode( ' | ', $check['errors'] ), $css );
		twd_sk_eq( '', $check['built'], 'nothing built for ' . $css );
	}
	// A refused save changes nothing.
	$e = TWD_SK_Css::save( '.c-a { color: #fff; }' );
	twd_sk_is_error( 'twd_sk_css_invalid', $e );
	twd_sk_has( 'Typed colours', $e->get_error_message() );
	twd_sk_eq( 0, TWD_SK_Css::version() );
	twd_sk_eq( '', TWD_SK_Css::current()['source'] );
} );

twd_sk_test( 'site css: a repeated rule warns, the budget refuses, the warning line starts at 16 KB, and merge() replaces a rule with the same selectors and adds the rest', function () {
	$check = TWD_SK_Css::check( ".c-a { color: var(--twd-site-color-body); }\n.c-a { color: var(--twd-site-color-muted); }" );
	twd_sk_true( $check['ok'] );
	twd_sk_has( 'appears more than once', implode( ' ', $check['warnings'] ) );
	$big = '';
	for ( $i = 0; $i < 400; $i++ ) {
		$big .= '.c-rule-' . $i . ' { padding: var(--twd-site-card-pad); color: var(--twd-site-color-body); }' . "\n";
	}
	$over = TWD_SK_Css::check( $big );
	twd_sk_true( ! $over['ok'] );
	twd_sk_has( 'over its', implode( ' ', $over['errors'] ) );
	$mid = '';
	for ( $i = 0; $i < 220; $i++ ) {
		$mid .= '.c-rule-' . $i . ' { padding: var(--twd-site-card-pad); color: var(--twd-site-color-body); }' . "\n";
	}
	$mid = TWD_SK_Css::check( $mid );
	twd_sk_true( $mid['ok'], implode( ' ', $mid['errors'] ) );
	twd_sk_true( $mid['bytes'] >= TWD_SK_Css::WARN && $mid['bytes'] <= TWD_SK_Css::BUDGET, 'between the warning line and the budget: ' . $mid['bytes'] );
	twd_sk_has( 'of its', implode( ' ', $mid['warnings'] ) );

	$merged = TWD_SK_Css::merge( twd_sk_custom_css(), ".c-intro__card { padding: var(--twd-site-card-pad-sm); }\n.c-new { color: var(--twd-site-color-accent-text); }\n@media (max-width: 767px) { .c-new { display: none; } }" );
	twd_sk_has( "padding: var(--twd-site-card-pad-sm);", $merged );
	twd_sk_hasnt( 'var(--twd-site-card-pad);', $merged, 'the old card rule is replaced' );
	twd_sk_hasnt( 'color: var(--twd-site-color-tint)', $merged, 'replaced as a whole, not merged declaration by declaration' );
	twd_sk_has( '.c-new {', $merged );
	twd_sk_eq( 1, substr_count( $merged, '@media (max-width: 767px)' ), 'one @media block gathers both rules' );
	twd_sk_true( TWD_SK_Css::check( $merged )['ok'] );
} );

twd_sk_test( 'site css: save() versions the stylesheet (20 kept, numbers never reused), revert_to() brings one back, status() and unused_rules() report plainly', function () {
	$one = TWD_SK_Css::save( twd_sk_custom_css(), 'first', 51 );
	twd_sk_eq( 1, $one['v'] );
	twd_sk_eq( false, $one['unchanged'] );
	twd_sk_eq( 1, TWD_SK_Css::version() );
	twd_sk_eq( array( 'c-intro' => true, 'c-intro__title' => true, 'c-intro__row' => true, 'c-intro__card' => true, 'c-intro__pic' => true ), TWD_SK_Css::allowed_classes() );
	$same = TWD_SK_Css::save( twd_sk_custom_css(), 'again', 51 );
	twd_sk_eq( true, $same['unchanged'] );
	twd_sk_eq( 1, TWD_SK_Css::version(), 'the same rules are not a new version' );
	$two = TWD_SK_Css::save( twd_sk_custom_css() . ".c-more { color: var(--twd-site-color-body); }\n", 'second', 52 );
	twd_sk_eq( 2, $two['v'] );
	$versions = TWD_SK_Css::versions();
	twd_sk_eq( array( 2, 1 ), array( $versions[0]['v'], $versions[1]['v'] ), 'newest first' );
	twd_sk_eq( 52, $versions[0]['page'] );
	twd_sk_eq( 'second', $versions[0]['note'] );
	twd_sk_true( ! isset( $versions[0]['source'] ), 'the list carries no source' );
	twd_sk_eq( twd_sk_custom_css(), TWD_SK_Css::version_source( 1 ) === TWD_SK_Css::check( twd_sk_custom_css() )['source'] ? twd_sk_custom_css() : '', 'version 1 holds the first source (normalised)' );
	$back = TWD_SK_Css::revert_to( 1, '', 51 );
	twd_sk_eq( 3, $back['v'] );
	twd_sk_eq( TWD_SK_Css::version_source( 1 ), TWD_SK_Css::current()['source'] );
	twd_sk_is_error( 'twd_sk_version_not_found', TWD_SK_Css::revert_to( 99 ) );
	$none = TWD_SK_Css::revert_to( 0 );
	twd_sk_eq( '', TWD_SK_Css::current()['source'], 'version 0 is the empty stylesheet' );
	twd_sk_eq( 4, $none['v'] );
	twd_sk_eq( 'This site has no stylesheet of its own yet.', TWD_SK_Css::status()['summary'] );
	TWD_SK_Css::save( twd_sk_custom_css(), 'back', 51 );
	twd_sk_has( 'of its 23.4 KB budget (5 classes, version 5)', TWD_SK_Css::status()['summary'] );
	// Unused rules: a rule is used when any of its classes is on any page.
	$unused = TWD_SK_Css::unused_rules( array( 51 => '<section class="c-intro"><p class="c-intro__title">x</p></section>' ) );
	twd_sk_eq( array( '.c-intro__row', '.c-intro__card', '.c-intro__pic', '@media (max-width: 767px) .c-intro__row' ), $unused );
	twd_sk_eq( array(), TWD_SK_Css::unused_rules( array( twd_sk_custom_section() ) ) );
	// The cap: 25 saves keep 20, and numbers go on counting.
	for ( $i = 0; $i < 25; $i++ ) {
		TWD_SK_Css::save( ".c-v { padding: " . ( $i + 1 ) . "px; }", 'v', 51 );
	}
	twd_sk_eq( 20, count( TWD_SK_Css::versions() ) );
	twd_sk_eq( 30, TWD_SK_Css::version() );
} );

twd_sk_test( 'sanitiser: a c- class survives only while the site stylesheet defines it; data-twd-edit and data-twd-pic survive (words on anything but a picture, picture only on a picture); every other data- attribute goes', function () {
	$html = twd_sk_custom_section();
	$before = TWD_SK_Sanitizer::clean_with_report( $html );
	twd_sk_hasnt( 'c-intro', $before['html'], 'no stylesheet: the classes are dropped' );
	twd_sk_has( 'twd-sk-tone-surface', $before['html'] );
	twd_sk_has( 'data-twd-edit="Heading"', $before['html'] );
	twd_sk_has( 'data-twd-pic="Photo"', $before['html'] );
	twd_sk_true( in_array( 'c-intro', $before['report']['removed']['classes'], true ) );
	TWD_SK_Css::save( twd_sk_custom_css(), 'x', 51 );
	$after = TWD_SK_Sanitizer::clean( $html );
	twd_sk_has( 'class="c-intro twd-sk-tone-surface"', $after );
	twd_sk_has( 'class="c-intro__card"', $after );
	// A class named like a custom class but not in the stylesheet still goes; a style attribute and other data- go.
	$odd = TWD_SK_Sanitizer::clean( '<section class="twd-sk-custom c-not-defined"><p class="c-intro__title" style="color:red" data-foo="1" data-twd-edit="Words">Hi</p><img src="/wp-content/uploads/your-image.jpg" alt="" data-twd-edit="Wrong" data-twd-pic="Right"><p data-twd-pic="Wrong">No</p></section>' );
	twd_sk_hasnt( 'c-not-defined', $odd );
	twd_sk_has( 'class="twd-sk-custom"', $odd );
	twd_sk_hasnt( 'style=', $odd );
	twd_sk_hasnt( 'data-foo', $odd );
	twd_sk_has( '<p class="c-intro__title" data-twd-edit="Words">Hi</p>', $odd );
	twd_sk_has( 'data-twd-pic="Right"', $odd );
	twd_sk_hasnt( 'data-twd-edit="Wrong"', $odd, 'a words label on a picture goes' );
	twd_sk_hasnt( 'data-twd-pic="Wrong"', $odd, 'a picture label on words goes' );
	// Labels are plain words.
	$bad = TWD_SK_Sanitizer::clean( '<p data-twd-edit="&lt;b&gt;x">a</p><p data-twd-edit="' . str_repeat( 'a', 70 ) . '">b</p><p data-twd-edit="Card one\'s words">c</p>' );
	twd_sk_hasnt( 'data-twd-edit="<', $bad );
	twd_sk_hasnt( str_repeat( 'a', 70 ), $bad );
	twd_sk_has( 'data-twd-edit="Card one\'s words"', $bad );
	// A preview's candidate classes are allowed only inside with_classes().
	$in = TWD_SK_Css::with_classes( array( 'c-fresh' ), function () {
		return TWD_SK_Sanitizer::clean( '<p class="c-fresh">x</p>' );
	} );
	twd_sk_has( 'class="c-fresh"', $in );
	twd_sk_hasnt( 'c-fresh', TWD_SK_Sanitizer::clean( '<p class="c-fresh">x</p>' ), 'gone again afterwards' );
	twd_sk_true( isset( TWD_SK_Registry::allowed_classes()['twd-sk-custom'] ), 'twd-sk-custom is a registry class' );
} );

twd_sk_test( 'sanitiser: the first h1 inside a custom section is kept (a page replaced with custom HTML keeps its title); a second h1 anywhere still becomes an h2', function () {
	$clean = TWD_SK_Sanitizer::clean( '<section class="twd-sk-custom"><h1 data-twd-edit="Title">Welcome</h1></section><section class="twd-sk-custom"><h1>Second</h1></section>' );
	twd_sk_has( '<h1 data-twd-edit="Title">Welcome</h1>', $clean );
	twd_sk_has( '<h2>Second</h2>', $clean );
	twd_sk_has( '<h2>Loose</h2>', TWD_SK_Sanitizer::clean( '<section class="twd-sk-text"><h1>Loose</h1></section>' ), 'outside a hero or a custom section an h1 is still demoted' );
} );

twd_sk_test( 'inline: in a custom section only labelled words and pictures are numbered (the Simple editor offers exactly those); kit sections are numbered as before; c- classes on inner spans survive a save', function () {
	TWD_SK_Css::save( twd_sk_custom_css(), 'x', 51 );
	$page = TWD_SK_Sanitizer::clean( TWD_SK_Registry::get( 'text' )['skeleton'] . twd_sk_custom_section() . '<section class="twd-sk-custom"><h2>Unlabelled</h2><p>Not editable</p><img src="/wp-content/uploads/your-image.jpg" alt=""><p data-twd-edit="Yes">Editable <span class="twd-sk-accent">words</span></p><p data-twd-edit="Nope"><div>block inside</div></p></section>' );
	$marked = TWD_SK_Inline::mark( $page );
	preg_match_all( '/data-twd-sk-t="(\d+)"[^>]*>([^<]*)/', $marked, $m );
	$texts = TWD_SK_Inline::count( $page );
	// The kit text section's pieces, then exactly the four labelled pieces of the two custom sections.
	$kit = TWD_SK_Inline::count( TWD_SK_Sanitizer::clean( TWD_SK_Registry::get( 'text' )['skeleton'] ) );
	twd_sk_eq( $kit['text'] + 4, $texts['text'], 'heading, two cards, and "Yes"' );
	twd_sk_eq( 1, $texts['pics'], 'only the labelled picture' );
	twd_sk_has( 'data-twd-edit="Heading" data-twd-sk-t=', $marked );
	twd_sk_hasnt( '<h2 data-twd-sk-t', preg_replace( '/<h2 class="[^"]*" data-twd-edit="Heading"[^>]*>/', '', $marked ), 'the unlabelled h2 is not numbered' );
	twd_sk_true( 1 === preg_match( '/<p>Not editable<\/p>/', $marked ), 'the unlabelled paragraph is untouched' );
	twd_sk_true( 1 === preg_match( '/<img src="[^"]*" alt="" width="\d+" height="\d+"( data-twd-sk-p="\d+")?>|<img src="[^"]*" alt="">/', $marked ) );
	twd_sk_hasnt( 'data-twd-sk-s=', $marked, 'no picture spots in a custom section' );
	// A save through apply(): the labelled piece's words change and its c- class and label stay.
	preg_match( '/data-twd-edit="Card one" data-twd-sk-t="(\d+)"/', $marked, $n );
	$out = TWD_SK_Inline::apply( $page, array( array( 'i' => (int) $n[1], 'html' => 'In person, <span class="c-intro__hl">face to face</span>.' ) ), array() );
	twd_sk_true( ! is_wp_error( $out ), is_wp_error( $out ) ? $out->get_error_message() : '' );
	twd_sk_has( '<p data-twd-edit="Card one">In person, <span class="c-intro__hl">face to face</span>.</p>', $out['html'] );
	twd_sk_eq( 'custom', TWD_SK_Sections::type_of( TWD_SK_Replace::mark_custom( twd_sk_custom_section() ) ), 'a custom section reads as type custom once marked' );
} );

twd_sk_test( 'replace: mark_custom() puts twd-sk-custom first on every non-kit section and wraps bare HTML; kit sections are left alone', function () {
	$marked = TWD_SK_Replace::mark_custom( twd_sk_custom_section() . "\n" . TWD_SK_Registry::get( 'cta' )['skeleton'] . '<section><p>plain</p></section>' );
	twd_sk_has( '<section class="twd-sk-custom c-intro twd-sk-tone-surface">', $marked );
	twd_sk_has( '<section class="twd-sk-custom"><p>plain</p></section>', $marked );
	twd_sk_eq( 1, substr_count( $marked, 'twd-sk-custom' ) - 1, 'the kit section is not marked (two custom sections, one kit)' );
	twd_sk_eq( TWD_SK_Registry::get( 'cta' )['skeleton'], TWD_SK_Replace::mark_custom( TWD_SK_Registry::get( 'cta' )['skeleton'] ), 'a kit section is left exactly as it is' );
	twd_sk_eq( '<section class="twd-sk-custom">' . "\n" . '<div class="c-x"><p>bare</p></div>' . "\n" . '</section>', TWD_SK_Replace::mark_custom( '<div class="c-x"><p>bare</p></div>' ) );
	$once = TWD_SK_Replace::mark_custom( twd_sk_custom_section() );
	twd_sk_eq( $once, TWD_SK_Replace::mark_custom( $once ), 'marking twice adds nothing' );
} );

twd_sk_test( 'replace prepare: one section swapped for custom HTML with its CSS, cleaned with the candidate classes allowed, with plain warnings for labels, headings and dropped classes; the notice and a stray script are refused', function () {
	$html  = twd_sk_custom_page();
	$out   = TWD_SK_Replace::prepare( 51, array( 'target' => 'section', 'index' => 2, 'html' => twd_sk_custom_section(), 'css' => twd_sk_custom_css() ) );
	twd_sk_true( ! is_wp_error( $out ), is_wp_error( $out ) ? $out->get_error_message() : '' );
	$types = array_map( array( 'TWD_SK_Sections', 'type_of' ), TWD_SK_Sections::split( $out['html'] )['sections'] );
	twd_sk_eq( array( 'hero', 'text', 'custom', 'cta' ), $types );
	twd_sk_eq( 2, $out['at'] );
	twd_sk_eq( 1, $out['count'] );
	twd_sk_has( 'class="twd-sk-custom c-intro twd-sk-tone-surface"', $out['html'] );
	twd_sk_has( 'class="c-intro__card"', $out['html'], 'the candidate classes are allowed before the stylesheet is saved' );
	twd_sk_eq( 0, TWD_SK_Css::version(), 'nothing saved' );
	twd_sk_eq( array( 'text' => 3, 'pics' => 1 ), $out['labels'] );
	twd_sk_eq( false, $out['same'] );
	// The hero skeleton's sample button points at #twd-sk-services and no section on this page has that id (section 23b).
	twd_sk_eq( 1, count( $out['warnings'] ), implode( ' | ', $out['warnings'] ) );
	twd_sk_has( 'This link goes nowhere: #twd-sk-services', $out['warnings'][0] );
	// Other sections untouched.
	$before = TWD_SK_Sections::split( $html )['sections'];
	$after  = TWD_SK_Sections::split( $out['html'] )['sections'];
	twd_sk_eq( $before[0], $after[0] );
	twd_sk_eq( $before[3], $after[3] );
	// No labels: a plain warning. Dropped classes: named. A second h1: said.
	$plain = TWD_SK_Replace::prepare( 51, array( 'target' => 'section', 'index' => 1, 'html' => '<section class="c-x c-not-styled"><h1>Another title</h1><h4>Jump</h4><p>Words</p></section>', 'css' => '.c-x { color: var(--twd-site-color-body); }' ) );
	$w = implode( ' | ', $plain['warnings'] );
	twd_sk_has( 'Clients will not be able to change anything here', $w );
	twd_sk_has( 'dropped the class c-not-styled', $w );
	twd_sk_has( 'A second <h1> was made an <h2>', $w );
	twd_sk_has( 'Headings skip a level', $w );
	// Replacing the hero's section with HTML that has no h1 warns; replacing the page needs its own h1.
	$no_h1 = TWD_SK_Replace::prepare( 51, array( 'target' => 'section', 'index' => 0, 'html' => '<section><p data-twd-edit="Words">Hi</p></section>', 'css' => '' ) );
	twd_sk_has( 'main heading (h1) was in this section', implode( ' ', $no_h1['warnings'] ) );
	$page = TWD_SK_Replace::prepare( 51, array( 'target' => 'page', 'html' => '<section class="c-top"><h1 data-twd-edit="Title">New page</h1></section><section class="c-top"><p data-twd-edit="Words">Hi</p></section>', 'css' => '.c-top { padding: var(--twd-site-section-y) 0; }' ) );
	twd_sk_eq( array( 'custom', 'custom' ), array_map( array( 'TWD_SK_Sections', 'type_of' ), TWD_SK_Sections::split( $page['html'] )['sections'] ) );
	twd_sk_has( '<h1 data-twd-edit="Title">New page</h1>', $page['html'] );
	twd_sk_eq( 0, $page['at'] );
	// Refusals.
	twd_sk_is_error( 'twd_sk_bad_input', TWD_SK_Replace::prepare( 51, array( 'target' => 'section', 'index' => 1, 'html' => '<section><script>x</script></section>' ) ) );
	twd_sk_is_error( 'twd_sk_bad_input', TWD_SK_Replace::prepare( 51, array( 'target' => 'section', 'index' => 9, 'html' => '<section><p>x</p></section>' ) ) );
	twd_sk_is_error( 'twd_sk_bad_input', TWD_SK_Replace::prepare( 51, array( 'target' => 'section', 'index' => 1, 'html' => '' ) ) );
	$css_bad = TWD_SK_Replace::prepare( 51, array( 'target' => 'section', 'index' => 1, 'html' => '<section><p>x</p></section>', 'css' => '.c-a { color: #fff; }' ) );
	twd_sk_is_error( 'twd_sk_css_invalid', $css_bad );
	twd_sk_has( 'Typed colours', implode( ' ', $css_bad->get_error_data()['errors'] ) );
	TWD_SK_Store::save( 51, $html . TWD_SK_Registry::get( 'notice' )['skeleton'] );
	twd_sk_is_error( 'twd_sk_bad_input', TWD_SK_Replace::prepare( 51, array( 'target' => 'section', 'index' => 4, 'html' => '<section><p>x</p></section>' ) ) );
} );

twd_sk_test( 'replace apply: the stylesheet is saved first and the page records its version; Undo brings the page AND the stylesheet back, Restore brings both forward; another page\'s later change holds the stylesheet', function () {
	twd_sk_custom_page();
	$base = TWD_SK_Store::get_current_version_id( 51 );
	$out  = TWD_SK_Replace::apply( 51, array( 'target' => 'section', 'index' => 2, 'html' => twd_sk_custom_section(), 'css' => twd_sk_custom_css() ), $base );
	twd_sk_true( ! is_wp_error( $out ), is_wp_error( $out ) ? $out->get_error_message() : '' );
	twd_sk_eq( 1, $out['css_v'] );
	twd_sk_eq( 2, $out['at'] );
	twd_sk_eq( 1, TWD_SK_Css::version() );
	twd_sk_has( 'class="twd-sk-custom c-intro twd-sk-tone-surface"', TWD_SK_Store::get_current( 51 ) );
	$v = TWD_SK_Store::list_versions( 51 );
	twd_sk_eq( 1, $v[0]['css'], 'the page version names the stylesheet version' );
	twd_sk_eq( 0, $v[1]['css'] );
	twd_sk_eq( 'Replaced section 3 with custom HTML', $v[0]['note'] );
	// A stale base is refused and saves nothing.
	twd_sk_is_error( 'twd_sk_conflict', TWD_SK_Replace::apply( 51, array( 'target' => 'section', 'index' => 2, 'html' => '<section><p>x</p></section>' ), $base ) );
	twd_sk_eq( 1, TWD_SK_Css::version() );
	// Undo: the page goes back and the stylesheet with it (every later change was this page's).
	$undo = TWD_SK_Store::undo( 51 );
	twd_sk_true( ! is_wp_error( $undo ) );
	twd_sk_hasnt( 'c-intro', TWD_SK_Store::get_current( 51 ) );
	twd_sk_eq( '', TWD_SK_Css::current()['source'], 'the stylesheet is empty again' );
	twd_sk_eq( 2, TWD_SK_Css::version(), 'as a new stylesheet version' );
	twd_sk_has( 'The site stylesheet went back with it', $undo['css_note'] );
	twd_sk_eq( 2, TWD_SK_Store::list_versions( 51 )[0]['css'], 'the undo version sits on the reverted stylesheet' );
	// Restore forward: both come back.
	$restore = TWD_SK_Store::restore( 51, $out['version'] );
	twd_sk_true( ! is_wp_error( $restore ) );
	twd_sk_has( 'c-intro__card', TWD_SK_Store::get_current( 51 ) );
	twd_sk_eq( TWD_SK_Css::version_source( 1 ), TWD_SK_Css::current()['source'] );
	twd_sk_eq( 3, TWD_SK_Css::version() );
	// Another page changes the stylesheet: this page's Undo leaves it and says so.
	twd_stub_add_post( 52, 'page', '[twd_page]' );
	TWD_SK_Store::save( 52, TWD_SK_Registry::get( 'hero' )['skeleton'] . TWD_SK_Registry::get( 'text' )['skeleton'] );
	$other = TWD_SK_Replace::apply( 52, array( 'target' => 'section', 'index' => 1, 'html' => '<section class="c-other"><p data-twd-edit="Words">Other</p></section>', 'css' => '.c-other { padding: var(--twd-site-gap-md); }' ), TWD_SK_Store::get_current_version_id( 52 ) );
	twd_sk_true( ! is_wp_error( $other ) );
	twd_sk_eq( 4, TWD_SK_Css::version() );
	$undo2 = TWD_SK_Store::undo( 51 );
	twd_sk_true( ! is_wp_error( $undo2 ) );
	twd_sk_has( 'left as it is, because another page changed it since (version 4)', $undo2['css_note'] );
	twd_sk_eq( 4, TWD_SK_Css::version() );
	twd_sk_has( '.c-intro', TWD_SK_Css::current()['source'], 'page 52 still has every rule it may need' );
} );

twd_sk_test( 'replace preview: the candidate stylesheet travels with the preview and is printed in place of the saved one for that preview only', function () {
	twd_sk_custom_page();
	TWD_SK_Css::save( '.c-old { color: var(--twd-site-color-body); }', 'old', 51 );
	$GLOBALS['twd_stub']['user'] = 7;
	$out = TWD_SK_Replace::preview( 51, array( 'target' => 'section', 'index' => 1, 'html' => twd_sk_custom_section(), 'css' => twd_sk_custom_css() ), 7 );
	twd_sk_true( ! is_wp_error( $out ), is_wp_error( $out ) ? $out->get_error_message() : '' );
	twd_sk_true( TWD_SK_Preview::valid_token( $out['token'] ) );
	twd_sk_has( 'twd_sk_preview=' . $out['token'], $out['preview_url'] );
	twd_sk_eq( array( 'text' => 3, 'pics' => 1 ), $out['labels'] );
	twd_sk_true( $out['css_bytes'] > 0 && $out['css_budget'] === TWD_SK_Css::BUDGET );
	$data = TWD_SK_Preview::read( $out['token'] );
	twd_sk_has( '.twd-sk-page .c-intro{', $data['css'] );
	twd_sk_has( '.c-old{', $data['css'], 'the saved rules are kept in the candidate' );
	twd_sk_has( 'class="twd-sk-custom c-intro', $data['html'] );
	twd_sk_eq( 1, TWD_SK_Css::version(), 'nothing saved by a preview' );
	// Printed: the saved stylesheet normally, the candidate through the filter.
	twd_sk_has( '.twd-sk-page .c-old{', TWD_SK_Css::built_for_output() );
	twd_sk_hasnt( 'c-intro', TWD_SK_Css::built_for_output() );
	$GLOBALS['twd_stub']['hooks'][ TWD_SK_Css::FILTER_BUILT ] = array( function ( $css ) use ( $data ) {
		return TWD_SK_Preview::filter_css( $css ) === $css ? $data['css'] : $css;
	} );
	twd_sk_has( '.twd-sk-page .c-intro{', TWD_SK_Css::built_for_output() );
	unset( $GLOBALS['twd_stub']['hooks'][ TWD_SK_Css::FILTER_BUILT ] );
	// The stylesheet prints inline after the kit stylesheet.
	TWD_SK_Css::print_styles();
	twd_sk_has( '.twd-sk-page .c-old{', (string) $GLOBALS['twd_stub']['styles']['twd-site-kit']['inline'], 'printed inline after the kit stylesheet' );
} );

twd_sk_test( 'adapt: the AI gets the section, the site stylesheet, the kit classes and the token names, must answer one JSON object, and its reply goes through the same checks; a bad colour is refused with the reply kept for fixing by hand', function () {
	twd_sk_custom_page();
	$GLOBALS['twd_stub']['user'] = 7;
	$GLOBALS['twd_stub']['hooks']['twd_ai_is_configured'] = array( function () {
		return true;
	} );
	$seen = array();
	$GLOBALS['twd_stub']['hooks']['twd_ai_complete'] = array( function ( $value, $system, $message, $tokens ) use ( &$seen ) {
		$seen = array( 'system' => $system, 'message' => $message, 'tokens' => $tokens );
		return "Here you are:\n```json\n" . wp_json_encode( array( 'html' => twd_sk_custom_section(), 'css' => twd_sk_custom_css(), 'what_changed' => 'The steps became three cards. The words are the same.' ) ) . "\n```";
	} );
	$out = TWD_SK_Replace::adapt( 51, array( 'index' => 2, 'instruction' => 'Make the steps a row of cards' ), 7 );
	twd_sk_true( ! is_wp_error( $out ), is_wp_error( $out ) ? $out->get_error_message() : '' );
	twd_sk_eq( 'The steps became three cards. The words are the same.', $out['what_changed'] );
	twd_sk_eq( twd_sk_custom_section(), $out['html'] );
	twd_sk_has( '.c-intro {', $out['css'] );
	twd_sk_true( TWD_SK_Preview::valid_token( $out['token'] ) );
	twd_sk_eq( 0, TWD_SK_Css::version(), 'nothing saved' );
	twd_sk_has( 'Reply with ONE JSON object', $seen['system'] );
	twd_sk_has( 'data-twd-edit=', $seen['system'] );
	twd_sk_has( 'Never a typed colour', $seen['system'] );
	twd_sk_has( 'Make the steps a row of cards', $seen['message'] );
	twd_sk_has( 'twd-sk-steps', $seen['message'], 'the section itself' );
	twd_sk_has( 'twd-sk-inner, twd-sk-inner--narrow', $seen['message'] );
	twd_sk_has( 'Colours: color-bg, color-surface', $seen['message'] );
	twd_sk_has( 'Fonts: font-heading, font-body', $seen['message'] );
	twd_sk_eq( TWD_SK_Replace::TOKENS_ADAPT, $seen['tokens'] );
	// The AI writes a typed colour: refused, the reply kept so it can be fixed by hand.
	$GLOBALS['twd_stub']['hooks']['twd_ai_complete'] = array( function () {
		return wp_json_encode( array( 'html' => '<section class="c-a"><p data-twd-edit="Words">x</p></section>', 'css' => '.c-a { color: #333; font-size: 18px; }' ) );
	} );
	$bad = TWD_SK_Replace::adapt( 51, array( 'index' => 2, 'instruction' => 'Make it grey' ), 7 );
	twd_sk_is_error( 'twd_sk_css_invalid', $bad );
	twd_sk_has( 'The AI\'s reply was not accepted. The CSS was not accepted: Typed colours are not allowed (#333', $bad->get_error_message() );
	twd_sk_has( 'Font sizes in px are not allowed', implode( ' ', $bad->get_error_data()['errors'] ) );
	twd_sk_eq( '<section class="c-a"><p data-twd-edit="Words">x</p></section>', $bad->get_error_data()['html'] );
	// Not JSON, no AI, no words, the notice.
	$GLOBALS['twd_stub']['hooks']['twd_ai_complete'] = array( function () {
		return 'Sorry, I cannot.';
	} );
	twd_sk_is_error( 'twd_sk_ai_bad_result', TWD_SK_Replace::adapt( 51, array( 'index' => 2, 'instruction' => 'x' ), 7 ) );
	twd_sk_is_error( 'twd_sk_bad_input', TWD_SK_Replace::adapt( 51, array( 'index' => 2, 'instruction' => '' ), 7 ) );
	twd_sk_is_error( 'twd_sk_bad_input', TWD_SK_Replace::adapt( 51, array( 'index' => 9, 'instruction' => 'x' ), 7 ) );
	$GLOBALS['twd_stub']['hooks']['twd_ai_is_configured'] = array();
	twd_sk_is_error( 'twd_sk_ai_unavailable', TWD_SK_Replace::adapt( 51, array( 'index' => 2, 'instruction' => 'x' ), 7 ) );
	twd_sk_eq( null, TWD_SK_Replace::read_reply( '{"css": ".c-a{}"}' ), 'html is required' );
} );

twd_sk_test( 'rest: /replace previews and applies for an administrator (422 with every message when the CSS fails), /adapt is the AI bucket, /site/css reports, /site/css/revert goes back; the AI limit message says how long', function () {
	twd_sk_custom_page();
	twd_sk_site_caps();
	$GLOBALS['twd_stub']['caps'] = true;
	$GLOBALS['twd_stub']['user'] = 7;
	twd_stub_add_post( 12, 'page', '[twd_page]' );
	TWD_SK_Store::save( 12, TWD_SK_Store::get_current( 51 ) );
	$base = TWD_SK_Store::get_current_version_id( 12 );
	$req  = twd_sk_rest_req( array( 'id' => 12, 'mode' => 'preview', 'target' => 'section', 'index' => 2, 'html' => twd_sk_custom_section(), 'css' => twd_sk_custom_css() ) );
	twd_sk_eq( true, TWD_SK_REST::can_replace_section( $req ) );
	$prev = TWD_SK_REST::post_replace( $req );
	twd_sk_true( ! is_wp_error( $prev ), is_wp_error( $prev ) ? $prev->get_error_message() : '' );
	twd_sk_true( isset( $prev['preview_url'], $prev['warnings'], $prev['css_bytes'], $prev['leftovers'] ) );
	$bad = TWD_SK_REST::post_replace( twd_sk_rest_req( array( 'id' => 12, 'mode' => 'preview', 'target' => 'section', 'index' => 2, 'html' => '<section><p>x</p></section>', 'css' => '.c-a { color: #fff; } .c-b { font-size: 12px; }' ) ) );
	twd_sk_is_error( 'twd_sk_css_invalid', $bad );
	twd_sk_eq( 422, twd_sk_status( $bad ) );
	twd_sk_eq( 2, count( $bad->get_error_data()['errors'] ) );
	$apply = TWD_SK_REST::post_replace( twd_sk_rest_req( array( 'id' => 12, 'mode' => 'apply', 'target' => 'section', 'index' => 2, 'html' => twd_sk_custom_section(), 'css' => twd_sk_custom_css(), 'base_version' => $base ) ) );
	twd_sk_true( ! is_wp_error( $apply ), is_wp_error( $apply ) ? $apply->get_error_message() : '' );
	twd_sk_eq( $base + 1, $apply['version'] );
	twd_sk_eq( 1, $apply['css_v'] );
	twd_sk_eq( 2, $apply['at'] );
	twd_sk_true( isset( $apply['versions'] ) || isset( $apply['current_version'] ) );
	twd_sk_is_error( 'twd_sk_bad_input', TWD_SK_REST::post_replace( twd_sk_rest_req( array( 'id' => 12, 'mode' => 'apply', 'target' => 'section', 'index' => 2, 'html' => 'x' ) ) ), 'apply needs base_version' );
	// The site stylesheet route and revert.
	$status = TWD_SK_REST::get_site_css( twd_sk_rest_req() );
	twd_sk_eq( 1, $status['version'] );
	twd_sk_true( isset( $status['unused'], $status['source'], $status['summary'] ) );
	$back = TWD_SK_REST::post_site_css_revert( twd_sk_rest_req( array( 'version' => 0 ) ) );
	twd_sk_eq( 2, $back['v'] );
	twd_sk_eq( '', TWD_SK_Css::current()['source'] );
	twd_sk_eq( 404, twd_sk_status( TWD_SK_REST::post_site_css_revert( twd_sk_rest_req( array( 'version' => 77 ) ) ) ) );
	// Adapt: the AI bucket, and its limit says how long.
	$GLOBALS['twd_stub']['hooks']['twd_ai_is_configured'] = array( function () {
		return true;
	} );
	$GLOBALS['twd_stub']['hooks']['twd_ai_complete'] = array( function () {
		return wp_json_encode( array( 'html' => '<section class="c-z"><p data-twd-edit="Words">z</p></section>', 'css' => '.c-z { padding: var(--twd-site-gap-sm); }', 'what_changed' => 'Padding.' ) );
	} );
	$ad = TWD_SK_REST::post_adapt( twd_sk_rest_req( array( 'id' => 12, 'index' => 1, 'instruction' => 'tighter', 'base_version' => TWD_SK_Store::get_current_version_id( 12 ) ) ) );
	twd_sk_true( ! is_wp_error( $ad ), is_wp_error( $ad ) ? $ad->get_error_message() : '' );
	twd_sk_eq( 'Padding.', $ad['what_changed'] );
	twd_sk_is_error( 'twd_sk_conflict', TWD_SK_REST::post_adapt( twd_sk_rest_req( array( 'id' => 12, 'index' => 1, 'instruction' => 'tighter', 'base_version' => 1 ) ) ) );
	for ( $i = 0; $i < 10; $i++ ) {
		twd_sk_eq( true, TWD_SK_REST::can_adapt_section( twd_sk_rest_req( array( 'id' => 12 ) ) ), 'call ' . $i );
	}
	$limit = TWD_SK_REST::can_adapt_section( twd_sk_rest_req( array( 'id' => 12 ) ) );
	twd_sk_is_error( 'twd_sk_rate_limited', $limit );
	twd_sk_eq( 429, twd_sk_status( $limit ) );
	twd_sk_has( 'The AI can be asked 10 times in 10 minutes', $limit->get_error_message() );
	twd_sk_true( $limit->get_error_data()['retry_after'] > 0 );
	// Not an administrator: refused on both (another user, whose AI allowance is untouched).
	$GLOBALS['twd_stub']['user'] = 8;
	$GLOBALS['twd_stub']['caps'] = array( 'edit_pages', 'edit_post:12' );
	twd_sk_is_error( 'twd_sk_forbidden', TWD_SK_REST::can_replace_section( twd_sk_rest_req( array( 'id' => 12 ) ) ) );
	twd_sk_is_error( 'twd_sk_forbidden', TWD_SK_REST::can_adapt_section( twd_sk_rest_req( array( 'id' => 12 ) ) ) );
} );

twd_sk_test( 'editor: the page data says custom only for an administrator on a kit page outside safe mode and the Simple editor; the inline script has the panels and the Advanced fold, and the Simple editor path is untouched', function () {
	$js = file_get_contents( ABSPATH . 'assets/twd-site-kit-inline.js' );
	twd_sk_has( "var CUSTOM = !!cfg.custom && oneWindow;", $js );
	foreach ( array( "toolButton('Describe a change'", "toolButton('Replace this section'", "toolButton('Advanced'", "'/pages/' + cfg.pageId + '/replace'", "'/pages/' + cfg.pageId + '/adapt'", "twd-sk-ed__inlinetools--advanced", "data-twd-sk-adv", "openSized(getUrl(), 'Phone', 390, 844)", "openSized(getUrl(), 'Desktop', 1280, 900)" ) as $needle ) {
		twd_sk_has( $needle, $js );
	}
	// The Simple editor's panel is built by simpleKids() and never sees the custom tools.
	$simple = substr( $js, strpos( $js, 'function simpleKids()' ), strpos( $js, 'function engaged()' ) - strpos( $js, 'function simpleKids()' ) );
	twd_sk_hasnt( 'ui.replace', $simple );
	twd_sk_hasnt( 'ui.describe', $simple );
	twd_sk_hasnt( 'ui.advanced', $simple );
	twd_sk_hasnt( 'innerHTML', $js );
	$css = file_get_contents( ABSPATH . 'assets/twd-site-kit-editor.css' );
	twd_sk_has( ':not([data-twd-sk-adv="on"]) .twd-sk-ed__inlinetools--advanced', $css );
	$php = file_get_contents( ABSPATH . 'includes/class-twd-sk-editor.php' );
	twd_sk_has( "'custom'         => (bool) \$context['is_kit_page'] && ! TWD_SK_Safe::on() && ! \$simple && \$can_manage,", $php );
	$ed = file_get_contents( ABSPATH . 'assets/twd-site-kit-editor.js' );
	twd_sk_has( "err.data = data && data.data && typeof data.data === 'object' ? data.data : null;", $ed );
} );
