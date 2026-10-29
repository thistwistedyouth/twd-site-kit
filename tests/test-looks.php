<?php
// The look of a section (0.15.0): layout and options chosen in the Visual editor, worked out from the registry.

function twd_sk_looks_classes( $html, $i ) {
	$parts = TWD_SK_Sections::split( $html );
	preg_match( '/^<section\b[^>]*\sclass="([^"]*)"/', $parts['sections'][ $i ], $m );
	return preg_split( '/\s+/', trim( $m[1] ) );
}

function twd_sk_looks_page() {
	$parts = array(
		array( 'hero', 'twd-sk-hero--image' ),
		array( 'services', 'twd-sk-services--cards' ),
		array( 'faq', '' ),
		array( 'image_text', '' ),
		array( 'services', 'twd-sk-services--pills' ),
		array( 'cta', 'twd-sk-cta--strip' ),
		array( 'notice', '' ),
	);
	return TWD_SK_Sanitizer::clean( TWD_SK_Starters::build( $parts ) );
}

twd_sk_test( 'looks: a section moves only between layouts that use exactly the same pieces; layouts that need other structure are not offered', function () {
	// Same pieces: cards and glow; the standard text and its prose layout; the standard faq and its two-column layout.
	twd_sk_eq( array( 'twd-sk-services--cards', 'twd-sk-services--glow' ), TWD_SK_Looks::compatible( 'services', 'twd-sk-services--cards' ) );
	twd_sk_eq( array( '', 'twd-sk-faq--two' ), TWD_SK_Looks::compatible( 'faq', '' ) );
	twd_sk_eq( array( '', 'twd-sk-text--prose' ), TWD_SK_Looks::compatible( 'text', '' ) );
	// Different structure: pills and links have their own pieces; the hero with the picture beside or behind; a slider needs its track.
	twd_sk_eq( array(), TWD_SK_Looks::compatible( 'services', 'twd-sk-services--pills' ), 'pills hold other pieces' );
	twd_sk_eq( array(), TWD_SK_Looks::compatible( 'hero', 'twd-sk-hero--image' ), 'the full-width picture hero has no twin' );
	twd_sk_eq( array(), TWD_SK_Looks::compatible( 'quote', 'twd-sk-quote--slider' ), 'a slider needs its track' );
	twd_sk_eq( array(), TWD_SK_Looks::compatible( 'embed', 'nonsense' ) );
	// The hero's standard layout is the named split, so there is no separate standard entry for it.
	$c = TWD_SK_Looks::config();
	$hero = array_map( function ( $l ) {
		return $l[0];
	}, $c['components']['hero']['layouts'] );
	twd_sk_eq( array( 'twd-sk-hero--split', 'twd-sk-hero--image', 'twd-sk-hero--compact' ), $hero, 'parallax is an option, not a layout' );
} );

twd_sk_test( 'looks: the browser gets small, static data: labels and class names for each component, never page content', function () {
	$c = TWD_SK_Looks::config();
	twd_sk_eq( '_', $c['standard'] );
	twd_sk_true( ! isset( $c['components']['notice'] ), 'the safety notice is left out' );
	twd_sk_true( isset( $c['components']['hero']['options'][0]['choices'] ) );
	twd_sk_eq( array( 'space_top', 'space_bottom', 'background' ), array_map( function ( $o ) { return $o['key']; }, $c['shared'] ), 'the shared options go once' );
	twd_sk_eq( array(), $c['components']['text']['options'], 'a component with no options of its own sends none' );
	$json = json_encode( $c );
	twd_sk_true( strlen( $json ) < 8000, 'light: ' . strlen( $json ) . ' bytes' );
	twd_sk_hasnt( 'PLACEHOLDER', $json );
	// Every class named in it is one the registry allows.
	$allowed = TWD_SK_Registry::allowed_classes();
	preg_match_all( '/twd-sk-[a-z0-9_-]+/', $json, $m );
	foreach ( array_unique( $m[0] ) as $class ) {
		twd_sk_true( isset( $allowed[ $class ] ), 'allowed class: ' . $class );
	}
} );

twd_sk_test( 'looks: classes_for moves the layout, sets options, and drops an option whose requirement went away', function () {
	$base = array( 'twd-sk-hero', 'twd-sk-hero--image' );
	$new  = TWD_SK_Looks::classes_for( 'hero', $base, null, array( 'overlay' => 'twd-sk-hero--overlay-strong', 'left' => true, 'parallax' => true ) );
	twd_sk_eq( array( 'twd-sk-hero', 'twd-sk-hero--image', 'twd-sk-hero--overlay-strong', 'twd-sk-hero--left', 'twd-sk-hero--parallax' ), $new );
	// Back to normal overlay, and the toggles off.
	$back = TWD_SK_Looks::classes_for( 'hero', $new, null, array( 'overlay' => '_', 'left' => false, 'parallax' => '0' ) );
	twd_sk_eq( $base, $back );
	// The picture options need the picture hero: on a split hero they are ignored.
	$split = TWD_SK_Looks::classes_for( 'hero', array( 'twd-sk-hero', 'twd-sk-hero--split', 'twd-sk-tone-base' ), null, array( 'left' => true, 'overlay' => 'twd-sk-hero--overlay-light' ) );
	twd_sk_eq( array( 'twd-sk-hero', 'twd-sk-hero--split', 'twd-sk-tone-base' ), $split );
	// Layout: modifier goes straight after the root class, the others stay.
	$svc = TWD_SK_Looks::classes_for( 'services', array( 'twd-sk-services', 'twd-sk-services--cards', 'twd-sk-tone-tint' ), 'twd-sk-services--glow', array() );
	twd_sk_eq( array( 'twd-sk-services', 'twd-sk-services--glow', 'twd-sk-tone-tint' ), $svc );
	$std = TWD_SK_Looks::classes_for( 'faq', array( 'twd-sk-faq', 'twd-sk-faq--two', 'twd-sk-tone-surface' ), '_', array() );
	twd_sk_eq( array( 'twd-sk-faq', 'twd-sk-tone-surface' ), $std, 'back to standard' );
	// Background: one of three light tones.
	$bg = TWD_SK_Looks::classes_for( 'faq', array( 'twd-sk-faq', 'twd-sk-tone-surface' ), null, array( 'background' => 'twd-sk-tone-tint' ) );
	twd_sk_eq( array( 'twd-sk-faq', 'twd-sk-tone-tint' ), $bg );
	// A layout that needs other pieces, an unknown option, a choice that is not on the list, and the dark band are all refused.
	twd_sk_is_error( 'twd_sk_bad_input', TWD_SK_Looks::classes_for( 'services', array( 'twd-sk-services', 'twd-sk-services--cards' ), 'twd-sk-services--pills', array() ) );
	twd_sk_is_error( 'twd_sk_bad_input', TWD_SK_Looks::classes_for( 'faq', array( 'twd-sk-faq', 'twd-sk-tone-base' ), null, array( 'colour' => 'red' ) ) );
	twd_sk_is_error( 'twd_sk_bad_input', TWD_SK_Looks::classes_for( 'faq', array( 'twd-sk-faq', 'twd-sk-tone-base' ), null, array( 'background' => 'twd-sk-tone-band' ) ) );
	twd_sk_is_error( 'twd_sk_bad_input', TWD_SK_Looks::classes_for( 'faq', array( 'twd-sk-faq', 'twd-sk-tone-base' ), null, array( 'background' => 'evil' ) ) );
	// A section with no light background (the dark band) has no background choice and keeps its band.
	$band = TWD_SK_Looks::classes_for( 'cta', array( 'twd-sk-cta', 'twd-sk-tone-band' ), null, array( 'background' => 'twd-sk-tone-base' ) );
	twd_sk_eq( array( 'twd-sk-cta', 'twd-sk-tone-band' ), $band );
} );

twd_sk_test( 'looks: apply changes only the class of the sections named, keeps every word, and the sanitiser keeps the result', function () {
	$html = twd_sk_looks_page();
	$out  = TWD_SK_Looks::apply( $html, array(
		array( 'i' => 0, 'options' => array( 'overlay' => 'twd-sk-hero--overlay-light', 'left' => true ) ),
		array( 'i' => 1, 'layout' => 'twd-sk-services--glow', 'options' => array( 'background' => 'twd-sk-tone-surface' ) ),
		array( 'i' => 2, 'layout' => 'twd-sk-faq--two' ),
	) );
	twd_sk_true( ! is_wp_error( $out ), is_wp_error( $out ) ? $out->get_error_message() : '' );
	twd_sk_eq( 3, $out['changed'] );
	twd_sk_has( 'twd-sk-hero--overlay-light', implode( ' ', twd_sk_looks_classes( $out['html'], 0 ) ) );
	twd_sk_eq( array( 'twd-sk-services', 'twd-sk-services--glow', 'twd-sk-tone-surface' ), twd_sk_looks_classes( $out['html'], 1 ) );
	twd_sk_eq( array( 'twd-sk-faq', 'twd-sk-faq--two', 'twd-sk-tone-surface' ), twd_sk_looks_classes( $out['html'], 2 ) );
	twd_sk_eq( TWD_SK_Sections::text_of( $html ), TWD_SK_Sections::text_of( $out['html'] ), 'every word as it was' );
	twd_sk_eq( $out['html'], TWD_SK_Sanitizer::clean( $out['html'] ), 'the sanitiser keeps it as it is' );
	// Sections that were not named are byte for byte the same.
	$before = TWD_SK_Sections::split( $html );
	$after  = TWD_SK_Sections::split( $out['html'] );
	foreach ( array( 3, 4, 5, 6 ) as $i ) {
		twd_sk_eq( $before['sections'][ $i ], $after['sections'][ $i ], 'section ' . $i . ' untouched' );
	}
	twd_sk_eq( $before['gaps'], $after['gaps'], 'the gaps between them too' );
	// Asking for what is already there changes nothing.
	$same = TWD_SK_Looks::apply( $html, array( array( 'i' => 2, 'layout' => '_' ), array( 'i' => 1, 'options' => array( 'background' => 'twd-sk-tone-tint' ) ) ) );
	twd_sk_eq( 0, $same['changed'] );
	twd_sk_eq( $html, $same['html'] );
} );

twd_sk_test( 'looks: apply refuses a section that is not there, one whose look is not changed here, a layout that does not fit, and too many', function () {
	$html = twd_sk_looks_page();
	$c    = TWD_SK_Looks::apply( $html, array( array( 'i' => 40, 'layout' => '_' ) ) );
	twd_sk_is_error( 'twd_sk_conflict', $c );
	twd_sk_is_error( 'twd_sk_bad_input', TWD_SK_Looks::apply( $html, array( array( 'i' => 6, 'options' => array( 'background' => 'twd-sk-tone-base' ) ) ) ), 'the safety notice is kept as it is' );
	twd_sk_is_error( 'twd_sk_bad_input', TWD_SK_Looks::apply( $html, array( array( 'i' => 4, 'layout' => 'twd-sk-services--glow' ) ) ), 'a layout from another kind of section' );
	twd_sk_is_error( 'twd_sk_bad_input', TWD_SK_Looks::apply( $html, array( 'x' ) ) );
	twd_sk_is_error( 'twd_sk_bad_input', TWD_SK_Looks::apply( $html, array( array( 'layout' => '_' ) ) ) );
	twd_sk_is_error( 'twd_sk_bad_input', TWD_SK_Looks::apply( $html, array_fill( 0, TWD_SK_Looks::MAX_LOOKS + 1, array( 'i' => 1 ) ) ) );
	$e = TWD_SK_Looks::apply( $html, array( array( 'i' => 4, 'layout' => 'twd-sk-services--glow' ) ) );
	twd_sk_has( 'Section 5', $e->get_error_message() );
	twd_sk_eq( array( 'html' => $html, 'changed' => 0 ), TWD_SK_Looks::apply( $html, array() ) );
} );

twd_sk_test( 'looks: the editor\'s numbering marks each top-level section and the layouts it can move to; the stored page never holds the marks', function () {
	$html   = twd_sk_looks_page();
	$marked = TWD_SK_Inline::mark( $html );
	foreach ( array( 0, 1, 2, 3, 4, 5, 6 ) as $i ) {
		twd_sk_true( false !== strpos( $marked, 'data-twd-sk-sec="' . $i . '"' ), 'section ' . $i . ' is numbered' );
	}
	twd_sk_true( 1 === preg_match( '/<section class="twd-sk-services twd-sk-services--cards[^"]*" id="twd-sk-services"[^>]*data-twd-sk-lay="twd-sk-services--cards twd-sk-services--glow"|<section[^>]*data-twd-sk-sec="1"[^>]*data-twd-sk-lay="twd-sk-services--cards twd-sk-services--glow"/', $marked ), 'cards and glow can swap' );
	twd_sk_true( 1 === preg_match( '/<section[^>]*data-twd-sk-sec="2"[^>]*data-twd-sk-lay="_ twd-sk-faq--two"/', $marked ), 'the standard faq can become two columns' );
	twd_sk_true( 1 !== preg_match( '/<section[^>]*data-twd-sk-sec="4"[^>]*data-twd-sk-lay/', $marked ), 'pills have no twin' );
	twd_sk_true( 1 !== preg_match( '/<section[^>]*data-twd-sk-sec="6"[^>]*data-twd-sk-lay/', $marked ), 'the notice has none' );
	// The numbers match the way the page is cut for the server (so a save names the same section).
	twd_sk_eq( 7, count( TWD_SK_Sections::split( $html )['sections'] ) );
	// A section inside another section is not numbered on its own.
	$nested = '<section class="twd-sk-text"><div class="twd-sk-inner"><section class="twd-sk-text"><p>Inner</p></section></div></section><section class="twd-sk-faq"><p>x</p></section>';
	$m = TWD_SK_Inline::mark( $nested );
	twd_sk_eq( 2, substr_count( $m, 'data-twd-sk-sec=' ) );
	twd_sk_true( false !== strpos( $m, 'data-twd-sk-sec="1"' ) );
	// Nothing about it is stored: the sanitiser removes every data attribute.
	twd_sk_hasnt( 'data-twd-sk', TWD_SK_Sanitizer::clean( $marked ) );
} );

twd_sk_test( 'looks rest: a save with only a look is a version of its own; with words as well it is one version; a stale page is refused', function () {
	twd_sk_rest_setup( array( 'edit_pages', 'edit_post:12' ) );
	$html = twd_sk_looks_page();
	TWD_SK_Store::save( 12, $html );
	$v   = TWD_SK_Store::get_current_version_id( 12 );
	$out = TWD_SK_REST::post_inline( twd_sk_rest_req( array( 'base_version' => $v, 'looks' => array( array( 'i' => 2, 'layout' => 'twd-sk-faq--two' ) ) ) ) );
	twd_sk_true( is_array( $out ), is_wp_error( $out ) ? $out->get_error_message() : 'saved' );
	twd_sk_eq( array( 'twd-sk-faq', 'twd-sk-faq--two', 'twd-sk-tone-surface' ), twd_sk_looks_classes( TWD_SK_Store::get_current( 12 ), 2 ) );
	$notes = TWD_SK_Store::list_versions( 12 );
	twd_sk_has( '1 section look', $notes[0]['note'] );
	// Words and a look together.
	$v2   = TWD_SK_Store::get_current_version_id( 12 );
	$page = TWD_SK_Inline::mark( TWD_SK_Store::get_current( 12 ) );
	preg_match( '/data-twd-sk-t="(\d+)"/', $page, $t );
	$both = TWD_SK_REST::post_inline( twd_sk_rest_req( array(
		'base_version' => $v2,
		'texts'        => array( array( 'i' => (int) $t[1], 'html' => 'New words' ) ),
		'looks'        => array( array( 'i' => 3, 'layout' => 'twd-sk-image-text--reverse' ) ),
	) ) );
	twd_sk_true( is_array( $both ) );
	twd_sk_has( 'New words', TWD_SK_Store::get_current( 12 ) );
	twd_sk_has( 'twd-sk-image-text--reverse', TWD_SK_Store::get_current( 12 ) );
	twd_sk_has( '1 piece', TWD_SK_Store::list_versions( 12 )[0]['note'] );
	twd_sk_has( '1 section look', TWD_SK_Store::list_versions( 12 )[0]['note'] );
	// A stale tab.
	$stale = TWD_SK_REST::post_inline( twd_sk_rest_req( array( 'base_version' => $v, 'looks' => array( array( 'i' => 2, 'layout' => '_' ) ) ) ) );
	twd_sk_is_error( 'twd_sk_conflict', $stale );
	twd_sk_eq( 409, twd_sk_status( $stale ) );
	// A look the server refuses is a plain 400 and nothing is saved.
	$vv  = TWD_SK_Store::get_current_version_id( 12 );
	$bad = TWD_SK_REST::post_inline( twd_sk_rest_req( array( 'base_version' => $vv, 'looks' => array( array( 'i' => 1, 'layout' => 'twd-sk-services--pills' ) ) ) ) );
	twd_sk_is_error( 'twd_sk_bad_input', $bad );
	twd_sk_eq( 400, twd_sk_status( $bad ) );
	twd_sk_eq( $vv, TWD_SK_Store::get_current_version_id( 12 ), 'nothing saved' );
	// Nothing at all to save is still refused.
	twd_sk_is_error( 'twd_sk_bad_input', TWD_SK_REST::post_inline( twd_sk_rest_req( array( 'base_version' => $vv ) ) ) );
} );

twd_sk_test( 'looks: Undo puts the old look back', function () {
	twd_sk_rest_setup( array( 'edit_pages', 'edit_post:12' ) );
	$html = twd_sk_looks_page();
	TWD_SK_Store::save( 12, $html );
	TWD_SK_Looks::apply( $html, array( array( 'i' => 2, 'layout' => 'twd-sk-faq--two' ) ) );
	TWD_SK_REST::post_inline( twd_sk_rest_req( array( 'base_version' => TWD_SK_Store::get_current_version_id( 12 ), 'looks' => array( array( 'i' => 2, 'layout' => 'twd-sk-faq--two' ) ) ) ) );
	twd_sk_true( ! is_wp_error( TWD_SK_Store::undo( 12 ) ) );
	twd_sk_eq( $html, TWD_SK_Store::get_current( 12 ) );
} );

twd_sk_test( 'looks: the pop-up gets the choices only on a kit page outside safe mode, and the script has the Look tool, builds with DOM calls and sends the wanted state', function () {
	$php = file_get_contents( ABSPATH . 'includes/class-twd-sk-editor.php' );
	twd_sk_has( "'look'           => ( (bool) \$context['is_kit_page'] && ! TWD_SK_Safe::on() ) ? TWD_SK_Looks::config() : array(),", $php );
	$js = file_get_contents( ABSPATH . 'assets/twd-site-kit-inline.js' );
	foreach ( array( "toolButton('Look'", 'cfg.look', 'data-twd-sk-sec', 'data-twd-sk-lay', 'body.looks = ', 'function lookClasses(', 'S.origLook', "'Look of this section: '" ) as $needle ) {
		twd_sk_has( $needle, $js );
	}
	twd_sk_hasnt( 'innerHTML', $js );
	twd_sk_true( 1 !== preg_match( '/[^\x00-\x7F]/', $js ), 'ASCII only' );
	$css = file_get_contents( ABSPATH . 'assets/twd-site-kit-inline.css' );
	twd_sk_has( 'body.twd-sk-inline-on section[data-twd-sk-looked]', $css );
	$src = file_get_contents( ABSPATH . 'includes/class-twd-sk-looks.php' );
	foreach ( array( 'wp_remote_', 'curl_', 'file_get_contents', 'eval(', 'update_post_meta', 'update_option' ) as $bad ) {
		twd_sk_hasnt( $bad, $src, $bad );
	}
} );

twd_sk_test( 'looks: the JavaScript copy of the rules gives the same classes as the PHP rules (run in node)', function () {
	if ( ! function_exists( 'shell_exec' ) || '' === trim( (string) shell_exec( 'command -v node 2>/dev/null' ) ) ) {
		return;
	}
	$js = file_get_contents( ABSPATH . 'assets/twd-site-kit-inline.js' );
	preg_match( '/function lookMet\(.*?\n\t}\n\n\tfunction layoutOf\(.*?\n\t}\n\n\t\/\/ The same rules.*?\n\t}\n/s', $js, $m );
	twd_sk_true( ! empty( $m[0] ), 'the rule functions can be found' );
	$cfg   = TWD_SK_Looks::config();
	$cases = array(
		array( 'hero', array( 'twd-sk-hero', 'twd-sk-hero--image' ), null, array( 'overlay' => 'twd-sk-hero--overlay-strong', 'left' => true, 'parallax' => true ) ),
		array( 'hero', array( 'twd-sk-hero', 'twd-sk-hero--image', 'twd-sk-hero--left', 'twd-sk-hero--parallax' ), null, array( 'left' => false, 'overlay' => '_' ) ),
		array( 'hero', array( 'twd-sk-hero', 'twd-sk-hero--split', 'twd-sk-tone-base' ), null, array( 'left' => true ) ),
		array( 'services', array( 'twd-sk-services', 'twd-sk-services--cards', 'twd-sk-tone-tint' ), 'twd-sk-services--glow', array( 'background' => 'twd-sk-tone-surface' ) ),
		array( 'faq', array( 'twd-sk-faq', 'twd-sk-faq--two', 'twd-sk-tone-surface' ), '_', array() ),
		array( 'faq', array( 'twd-sk-faq', 'twd-sk-tone-base' ), 'twd-sk-faq--two', array( 'background' => 'twd-sk-tone-tint' ) ),
		array( 'cta', array( 'twd-sk-cta', 'twd-sk-tone-band' ), null, array( 'background' => 'twd-sk-tone-base' ) ),
	);
	$script = 'var cfg = { look: ' . json_encode( $cfg ) . ' };' . "\n" . $m[0] . "\n"
		. 'var cases = ' . json_encode( $cases ) . ';' . "\n"
		. 'var LOOK = {}; Object.keys(cfg.look.components).forEach(function (id) { var s = cfg.look.components[id]; LOOK[id] = { label: s.label, layouts: s.layouts, options: (s.options || []).concat(cfg.look.shared || []) }; });' . "
"
		. 'console.log(JSON.stringify(cases.map(function (c) { return lookClasses(LOOK[c[0]], c[1], c[2], c[3]); })));';
	$file = sys_get_temp_dir() . '/twd-sk-looks-' . getmypid() . '.js';
	file_put_contents( $file, $script );
	$out = json_decode( (string) shell_exec( 'node ' . escapeshellarg( $file ) . ' 2>&1' ), true );
	@unlink( $file );
	twd_sk_true( is_array( $out ), 'node ran' );
	foreach ( $cases as $i => $c ) {
		$php = TWD_SK_Looks::classes_for( $c[0], $c[1], $c[2], $c[3] );
		twd_sk_eq( $php, $out[ $i ], 'case ' . $i . ' (' . $c[0] . ')' );
	}
} );

twd_sk_test( 'looks: every section offers Space above and Space below (none, small, normal, large) as shared classes, saved like any option', function () {
	foreach ( array( 'text', 'hero', 'faq', 'cta', 'resources' ) as $c ) {
		$all = TWD_SK_Looks::all_options( $c );
		twd_sk_true( isset( $all['space_top'], $all['space_bottom'] ), $c );
		twd_sk_eq( array( '', 'twd-sk-space-top-0', 'twd-sk-space-top-sm', 'twd-sk-space-top-lg' ), array_keys( $all['space_top']['choices'] ) );
	}
	$shared = TWD_SK_Registry::shared_classes();
	foreach ( array( 'twd-sk-space-top-0', 'twd-sk-space-top-sm', 'twd-sk-space-top-lg', 'twd-sk-space-bottom-0', 'twd-sk-space-bottom-sm', 'twd-sk-space-bottom-lg' ) as $cls ) {
		twd_sk_true( in_array( $cls, $shared, true ), $cls );
	}
	$r = TWD_SK_Looks::classes_for( 'text', array( 'twd-sk-text', 'twd-sk-tone-base' ), null, array( 'space_top' => 'twd-sk-space-top-0', 'space_bottom' => 'twd-sk-space-bottom-lg' ) );
	twd_sk_true( in_array( 'twd-sk-space-top-0', $r, true ) && in_array( 'twd-sk-space-bottom-lg', $r, true ) );
	$back = TWD_SK_Looks::classes_for( 'text', $r, null, array( 'space_top' => '', 'space_bottom' => '' ) );
	twd_sk_eq( array( 'twd-sk-text', 'twd-sk-tone-base' ), array_values( $back ), 'Normal takes the classes off again' );
	twd_sk_is_error( 'twd_sk_bad_input', TWD_SK_Looks::classes_for( 'text', array( 'twd-sk-text' ), null, array( 'space_top' => 'twd-sk-space-top-huge' ) ) );
	// The sanitiser keeps the classes on a section, and a page without them prints exactly as before.
	$html  = '<section class="twd-sk-text twd-sk-tone-base twd-sk-space-top-0"><div class="twd-sk-inner"><p>Hi</p></div></section>';
	twd_sk_eq( $html, TWD_SK_Sanitizer::clean( $html ) );
	$css = file_get_contents( ABSPATH . 'assets/twd-site-kit.css' );
	twd_sk_has( '.twd-sk-page .twd-sk-space-top-0.twd-sk-space-top-0 {', $css );
	twd_sk_has( '.twd-sk-page .twd-sk-image-text--band.twd-sk-image-text--band .twd-sk-image-text__img.twd-sk-image-text__img {', $css );
	twd_sk_true( false !== strpos( $css, "min-height: 0;\n  border-radius: 0 !important;" ), 'the band picture never grows past the band (the phone overlap fix)' );
} );
