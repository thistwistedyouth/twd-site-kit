<?php
// The Embed section (0.15.0): code kept apart from the page, administrators only. And the editable line
// shown while the articles grid is empty (0.15.0).

function twd_sk_embed_page( $html ) {
	twd_stub_add_post( 50, 'page', '[twd_page]' );
	TWD_SK_Store::save( 50, $html );
	$GLOBALS['twd_stub']['post_id'] = 50;
}

function twd_sk_embed_ok( $label = 'Booking calendar', $kind = 'shortcode', $code = '[booking_calendar id="2"]' ) {
	$r = TWD_SK_Embed::save( array( 'label' => $label, 'kind' => $kind, 'code' => $code ), 0, 1 );
	twd_sk_true( ! is_wp_error( $r ), 'saved' );
	return $r;
}

twd_sk_test( 'embed: a name, a kind and some code are needed; a shortcode holds no tags; the plugin\'s own shortcodes are refused; size is limited', function () {
	twd_sk_is_error( 'twd_sk_bad_input', TWD_SK_Embed::validate( array( 'label' => '', 'kind' => 'html', 'code' => '<p>x</p>' ) ), 'no name' );
	twd_sk_is_error( 'twd_sk_bad_input', TWD_SK_Embed::validate( array( 'label' => 'x', 'kind' => 'php', 'code' => '<p>x</p>' ) ), 'unknown kind' );
	twd_sk_is_error( 'twd_sk_bad_input', TWD_SK_Embed::validate( array( 'label' => 'x', 'kind' => 'html', 'code' => '   ' ) ), 'no code' );
	twd_sk_is_error( 'twd_sk_bad_input', TWD_SK_Embed::validate( array( 'label' => str_repeat( 'a', 81 ), 'kind' => 'html', 'code' => 'x' ) ), 'name too long' );
	twd_sk_is_error( 'twd_sk_bad_input', TWD_SK_Embed::validate( array( 'label' => 'x', 'kind' => 'html', 'code' => str_repeat( 'a', TWD_SK_Embed::MAX_CODE + 1 ) ) ), 'too long' );
	twd_sk_is_error( 'twd_sk_bad_input', TWD_SK_Embed::validate( array( 'label' => 'x', 'kind' => 'shortcode', 'code' => '[a]<script>x</script>[/a]' ) ), 'a shortcode with a tag' );
	twd_sk_is_error( 'twd_sk_bad_input', TWD_SK_Embed::validate( array( 'label' => 'x', 'kind' => 'shortcode', 'code' => 'just words' ) ), 'not a shortcode' );
	foreach ( array( '[twd_page]', '[twd_embed id="1"]', '[TWD_EMBED id="1"]', '[ twd_page ]' ) as $loop ) {
		twd_sk_is_error( 'twd_sk_bad_input', TWD_SK_Embed::validate( array( 'label' => 'x', 'kind' => 'shortcode', 'code' => $loop ) ), $loop );
	}
	twd_sk_is_error( 'twd_sk_bad_input', TWD_SK_Embed::validate( array( 'label' => 'x', 'kind' => 'html', 'code' => '<p>[twd_embed id="1"]</p>' ) ), 'html cannot hold an embed either' );
	$ok = TWD_SK_Embed::validate( array( 'label' => " Booking <b>calendar</b> ", 'kind' => 'shortcode', 'code' => "[booking_calendar id=\"2\"]\x00" ) );
	twd_sk_eq( array( 'label' => 'Booking calendar', 'kind' => 'shortcode', 'code' => '[booking_calendar id="2"]' ), $ok, 'clean name and code' );
	$h = TWD_SK_Embed::validate( array( 'label' => 'Widget', 'kind' => 'html', 'code' => '<div id="w"></div><script src="https://www.example.com/w.js"></script>' ) );
	twd_sk_true( ! is_wp_error( $h ), 'raw html with a script is kept as given' );
	twd_sk_has( '<script src=', $h['code'] );
} );

twd_sk_test( 'embed: saving, changing and deleting; a number is never used again', function () {
	twd_sk_eq( array(), TWD_SK_Embed::all() );
	$a = twd_sk_embed_ok( 'One' );
	$b = twd_sk_embed_ok( 'Two', 'html', '<p>Hello</p>' );
	twd_sk_eq( 1, $a['id'] );
	twd_sk_eq( 2, $b['id'] );
	twd_sk_eq( 'Two', TWD_SK_Embed::get( 2 )['label'] );
	$c = TWD_SK_Embed::save( array( 'label' => 'One again', 'kind' => 'html', 'code' => '<p>New</p>' ), 1, 9 );
	twd_sk_eq( 1, $c['id'] );
	twd_sk_eq( 'One again', TWD_SK_Embed::get( 1 )['label'] );
	twd_sk_eq( 9, TWD_SK_Embed::get( 1 )['by'] );
	twd_sk_is_error( 'twd_sk_not_found', TWD_SK_Embed::save( array( 'label' => 'x', 'kind' => 'html', 'code' => 'y' ), 77, 1 ), 'a number that is not there' );
	twd_sk_true( TWD_SK_Embed::delete( 2 ) );
	twd_sk_true( ! TWD_SK_Embed::delete( 2 ), 'already gone' );
	twd_sk_eq( null, TWD_SK_Embed::get( 2 ) );
	twd_sk_eq( 3, twd_sk_embed_ok( 'Three' )['id'], 'number 2 is not reused' );
	// Damaged storage reads as nothing, never as a fault.
	$GLOBALS['twd_stub']['options'][ TWD_SK_Embed::OPTION ] = 'broken';
	twd_sk_eq( array(), TWD_SK_Embed::all() );
	$GLOBALS['twd_stub']['options'][ TWD_SK_Embed::OPTION ] = array( 1 => array( 'kind' => 'php', 'code' => 'x' ), 0 => array( 'kind' => 'html', 'code' => 'x' ), 5 => array( 'kind' => 'html', 'code' => '' ) );
	twd_sk_eq( array(), TWD_SK_Embed::all(), 'unreadable records are left out' );
} );

twd_sk_test( 'embed: at most forty', function () {
	for ( $i = 0; $i < TWD_SK_Embed::MAX_ITEMS; $i++ ) {
		twd_sk_embed_ok( 'E' . $i, 'html', '<p>' . $i . '</p>' );
	}
	twd_sk_is_error( 'twd_sk_bad_input', TWD_SK_Embed::save( array( 'label' => 'more', 'kind' => 'html', 'code' => 'x' ), 0, 1 ) );
	twd_sk_true( ! is_wp_error( TWD_SK_Embed::save( array( 'label' => 'changed', 'kind' => 'html', 'code' => 'x' ), 5, 1 ) ), 'changing one is still fine' );
} );

twd_sk_test( 'embed: the section is in the registry, its shortcode keeps a validated number, and the sample is flagged until a real embed is chosen', function () {
	$c = TWD_SK_Registry::get( 'embed' );
	twd_sk_true( null !== $c );
	twd_sk_has( '[twd_embed id="0"]', $c['skeleton'] );
	twd_sk_has( '[twd_embed id="0"]', $c['variants']['twd-sk-embed--bare']['skeleton'] );
	twd_sk_hasnt( 'Heading here', $c['variants']['twd-sk-embed--bare']['skeleton'], 'the bare layout has no heading' );
	$r = TWD_SK_Sanitizer::clean_with_report( '<section class="twd-sk-embed"><div class="twd-sk-inner">[twd_embed id="12" onload="x" code="y"]</div></section>' );
	twd_sk_has( '[twd_embed id="12"]', $r['html'] );
	twd_sk_hasnt( 'onload', $r['html'] );
	twd_sk_hasnt( 'code=', $r['html'] );
	$bad = TWD_SK_Sanitizer::clean( '<section class="twd-sk-embed"><div>[twd_embed id="abc"]</div></section>' );
	twd_sk_hasnt( 'abc', $bad );
	foreach ( array( $c['skeleton'], $c['variants']['twd-sk-embed--bare']['skeleton'] ) as $sk ) {
		$left = TWD_SK_Sanitizer::find_leftovers( $sk );
		twd_sk_true( isset( $left['[twd_embed id="0"]'] ), 'the sample number is flagged' );
		twd_sk_eq( 'must', TWD_SK_Sanitizer::leftover_level( '[twd_embed id="0"]' ) );
		twd_sk_eq( $sk, TWD_SK_Sanitizer::clean( $sk ), 'the skeleton survives the cleaner untouched' );
	}
	twd_sk_true( ! isset( TWD_SK_Sanitizer::find_leftovers( '<p>[twd_embed id="3"]</p>' )['[twd_embed id="0"]'] ), 'a real number is not flagged' );
	twd_sk_true( isset( TWD_SK_Sanitizer::find_leftovers( '<p>[twd_embed id="00"]</p>' )['[twd_embed id="0"]'] ), 'a row of zeros is still the sample' );
} );

twd_sk_test( 'embed: a page that points at a deleted embed cannot be published, one that points at a saved embed can', function () {
	TWD_SK_Embed::init();
	$e = twd_sk_embed_ok();
	twd_sk_embed_page( '<section class="twd-sk-embed twd-sk-embed--bare"><div class="twd-sk-inner">[twd_embed id="' . $e['id'] . '"]</div></section>' );
	twd_sk_eq( array(), TWD_SK_Store::get_leftover_levels( 50 )['must'], 'a saved embed is fine' );
	TWD_SK_Embed::delete( $e['id'] );
	$must = TWD_SK_Store::get_leftover_levels( 50 )['must'];
	twd_sk_eq( array( 'embed missing' => 1 ), $must );
	twd_sk_eq( 'must', TWD_SK_Sanitizer::leftover_level( 'embed missing' ) );
	$report = TWD_SK_Launch::page_report( 50 );
	twd_sk_true( ! $report['ready'], 'the launch check does not call it ready' );
	$meaning = TWD_SK_Report::describe_leftovers( $must );
	twd_sk_has( 'deleted', $meaning[0]['meaning'] );
} );

twd_sk_test( 'embed: a shortcode is run and a piece of HTML is printed as saved, inside a wrapper, on the page', function () {
	$a = twd_sk_embed_ok( 'Cal', 'shortcode', '[booking_calendar id="2"]' );
	$b = twd_sk_embed_ok( 'Widget', 'html', '<div class="w">[1] and [2]</div><script>var a=[1,2];</script>' );
	twd_sk_embed_page( '<section class="twd-sk-embed"><div class="twd-sk-inner"><h2 class="twd-sk-title">Book</h2>[twd_embed id="' . $a['id'] . '"]</div></section><section class="twd-sk-embed twd-sk-embed--bare"><div class="twd-sk-inner">[twd_embed id="' . $b['id'] . '"]</div></section>' );
	// Through the page: the number is a real shortcode that runs (the test stand-in marks every shortcode it runs).
	$out = TWD_SK_Page::render();
	twd_sk_has( '<RUN:twd_embed id="' . $a['id'] . '">', $out, 'the page runs the embed shortcode' );
	twd_sk_has( '<RUN:twd_embed id="' . $b['id'] . '">', $out );
	twd_sk_has( '<h2 class="twd-sk-title">Book</h2>', $out );
	// What the shortcode prints.
	twd_sk_eq( '<div class="twd-sk-embed__content"><RUN:booking_calendar id="2"></div>', TWD_SK_Embed::render( array( 'id' => (string) $a['id'] ) ), 'the shortcode ran' );
	twd_sk_eq( '<div class="twd-sk-embed__content"><div class="w">[1] and [2]</div><script>var a=[1,2];</script></div>', TWD_SK_Embed::render( array( 'id' => (string) $b['id'] ) ), 'the html is exactly as saved, brackets and all' );
	// The page draws it after turning every other bracket into text, so the html's own brackets survive.
	$slots = TWD_SK_Page::render_html( '<p>[x]</p>[twd_embed id="' . $b['id'] . '"]' );
	twd_sk_has( '<p>&#91;x&#93;</p>', $slots );
} );

twd_sk_test( 'embed: the code never enters the page, its history, its text copy, the prompt or the AI', function () {
	$a = twd_sk_embed_ok( 'Secret widget', 'html', '<div data-key="UNIQUE-EMBED-CODE-4821">x</div>' );
	twd_sk_embed_page( '<section class="twd-sk-embed twd-sk-embed--bare"><div class="twd-sk-inner">[twd_embed id="' . $a['id'] . '"]</div></section>' );
	foreach ( $GLOBALS['twd_stub']['meta'] as $meta ) {
		twd_sk_hasnt( 'UNIQUE-EMBED-CODE-4821', json_encode( $meta ), 'not in any page meta (the page, its versions, its text copy)' );
	}
	twd_sk_hasnt( 'UNIQUE-EMBED-CODE-4821', TWD_SK_Prompt::build( TWD_SK_Store::get_current( 50 ) ), 'not in the prompt for an outside AI' );
	twd_sk_hasnt( 'UNIQUE-EMBED-CODE-4821', json_encode( TWD_SK_Embed::summaries() ), 'not in the list the pop-up gets' );
	twd_sk_hasnt( 'UNIQUE-EMBED-CODE-4821', TWD_SK_Doctor::report(), 'not in the site report' );
	twd_sk_hasnt( 'Secret widget', TWD_SK_Doctor::report(), 'nor its name' );
	twd_sk_has( 'Embed 1: html', TWD_SK_Doctor::report() );
} );

twd_sk_test( 'embed: safe mode prints nothing; a missing embed prints nothing for visitors and a short note for someone who can edit', function () {
	$a = twd_sk_embed_ok( 'Cal', 'html', '<p>Hello</p>' );
	twd_sk_has( '<p>Hello</p>', TWD_SK_Embed::render( array( 'id' => (string) $a['id'] ) ) );
	TWD_SK_Safe::set( true );
	twd_sk_eq( '', TWD_SK_Embed::render( array( 'id' => (string) $a['id'] ) ), 'safe mode' );
	TWD_SK_Safe::set( false );
	$GLOBALS['twd_stub']['caps'] = false;
	twd_sk_eq( '', TWD_SK_Embed::render( array( 'id' => '99' ) ), 'a visitor sees no gap note' );
	twd_sk_eq( '', TWD_SK_Embed::render( array() ), 'no number' );
	$GLOBALS['twd_stub']['caps'] = array( 'edit_pages' );
	twd_sk_has( 'twd-sk-embed__missing', TWD_SK_Embed::render( array( 'id' => '99' ) ) );
	twd_sk_has( 'has not been found', TWD_SK_Embed::render( array( 'id' => '99' ) ) );
	twd_sk_has( 'No embed has been chosen', TWD_SK_Embed::render( array( 'id' => '0' ) ) );
} );

twd_sk_test( 'embed: an embed cannot show itself (a hand-edited store cannot loop)', function () {
	$GLOBALS['twd_stub']['options'][ TWD_SK_Embed::OPTION ] = array( 1 => array( 'label' => 'Loop', 'kind' => 'shortcode', 'code' => '[twd_embed id="1"]' ) );
	$depth = new ReflectionProperty( 'TWD_SK_Embed', 'depth' );
	$depth->setAccessible( true );
	$depth->setValue( null, 1 );
	$inner = TWD_SK_Embed::render( array( 'id' => '1' ) );
	$depth->setValue( null, 0 );
	twd_sk_eq( '', $inner, 'drawn while another embed is being drawn: nothing' );
	twd_sk_has( 'twd-sk-embed__content', TWD_SK_Embed::render( array( 'id' => '1' ) ), 'on its own it draws once' );
	twd_sk_eq( 0, $depth->getValue(), 'the guard is put back' );
} );

twd_sk_test( 'embed: only an administrator saves; raw HTML also needs unfiltered_html (rest)', function () {
	twd_sk_site_caps( false );
	$e = TWD_SK_REST::can_manage_site( twd_sk_rest_req() );
	twd_sk_is_error( 'twd_sk_forbidden', $e, 'an editor cannot' );
	twd_sk_eq( 403, twd_sk_status( $e ) );
	twd_sk_is_error( 'twd_sk_forbidden', TWD_SK_REST::can_read_site( twd_sk_rest_req() ), 'nor read the list' );
	twd_sk_site_caps( true );
	twd_sk_true( true === TWD_SK_REST::can_manage_site( twd_sk_rest_req() ) );
	// Administrator without unfiltered_html: a shortcode yes, raw HTML no.
	$GLOBALS['twd_stub']['caps'] = array( 'edit_pages', 'manage_options' );
	$no = TWD_SK_REST::post_site_embeds( twd_sk_rest_req( array( 'label' => 'W', 'kind' => 'html', 'code' => '<p>x</p>' ) ) );
	twd_sk_is_error( 'twd_sk_forbidden', $no );
	twd_sk_eq( 403, twd_sk_status( $no ) );
	twd_sk_eq( array(), TWD_SK_Embed::all(), 'nothing saved' );
	$yes = TWD_SK_REST::post_site_embeds( twd_sk_rest_req( array( 'label' => 'Cal', 'kind' => 'shortcode', 'code' => '[cal]' ) ) );
	twd_sk_true( is_array( $yes ), 'a shortcode is fine' );
	twd_sk_eq( 1, $yes['saved'] );
	twd_sk_true( false === $yes['can_html'] );
	$GLOBALS['twd_stub']['caps'] = array( 'edit_pages', 'manage_options', 'unfiltered_html' );
	$html = TWD_SK_REST::post_site_embeds( twd_sk_rest_req( array( 'label' => 'W', 'kind' => 'html', 'code' => '<p>x</p>' ) ) );
	twd_sk_eq( 2, $html['saved'] );
	twd_sk_true( true === $html['can_html'] );
	twd_sk_eq( 2, count( TWD_SK_REST::get_site_embeds( twd_sk_rest_req() )['embeds'] ) );
	$bad = TWD_SK_REST::post_site_embeds( twd_sk_rest_req( array( 'label' => '', 'kind' => 'html', 'code' => '<p>x</p>' ) ) );
	twd_sk_is_error( 'twd_sk_bad_input', $bad );
	twd_sk_eq( 400, twd_sk_status( $bad ) );
	$gone = TWD_SK_REST::post_site_embeds( twd_sk_rest_req( array( 'embed_id' => 55, 'label' => 'x', 'kind' => 'html', 'code' => 'y' ) ) );
	twd_sk_eq( 404, twd_sk_status( $gone ) );
	$chg = TWD_SK_REST::post_site_embeds( twd_sk_rest_req( array( 'embed_id' => 1, 'label' => 'Cal 2', 'kind' => 'shortcode', 'code' => '[cal2]' ) ) );
	twd_sk_eq( 'Cal 2', TWD_SK_Embed::get( 1 )['label'] );
	twd_sk_eq( 1, $chg['saved'] );
} );

twd_sk_test( 'embed: deleting one that a page uses needs a yes first (rest)', function () {
	twd_sk_site_caps( true );
	$a = twd_sk_embed_ok();
	twd_sk_embed_page( '<section class="twd-sk-embed twd-sk-embed--bare"><div class="twd-sk-inner">[twd_embed id="' . $a['id'] . '"]</div></section>' );
	$GLOBALS['twd_stub']['posts'][50]->post_title = 'Bookings';
	$e = TWD_SK_REST::post_site_embed_delete( twd_sk_rest_req( array( 'eid' => $a['id'] ) ) );
	twd_sk_is_error( 'twd_sk_in_use', $e );
	twd_sk_eq( 409, twd_sk_status( $e ) );
	twd_sk_true( null !== TWD_SK_Embed::get( $a['id'] ), 'still there' );
	$ok = TWD_SK_REST::post_site_embed_delete( twd_sk_rest_req( array( 'eid' => $a['id'], 'confirm' => true ) ) );
	twd_sk_true( is_array( $ok ) );
	twd_sk_eq( null, TWD_SK_Embed::get( $a['id'] ) );
	twd_sk_eq( 404, twd_sk_status( TWD_SK_REST::post_site_embed_delete( twd_sk_rest_req( array( 'eid' => $a['id'] ) ) ) ) );
	$b = twd_sk_embed_ok( 'Unused' );
	twd_sk_true( is_array( TWD_SK_REST::post_site_embed_delete( twd_sk_rest_req( array( 'eid' => $b['id'] ) ) ) ), 'an unused one goes without asking' );
} );

twd_sk_test( 'embed: an administrator adds a saved embed to a page from Add a section; nobody else, never the AI', function () {
	$about = file_get_contents( ABSPATH . 'starters/page-about.html' );
	twd_stub_add_post( 40, 'page', '[twd_page]' );
	TWD_SK_Store::save( 40, $about );
	$e = twd_sk_embed_ok( 'Booking calendar' );
	$v = TWD_SK_Store::get_current_version_id( 40 );
	$r = TWD_SK_AI::add_section( 40, array( 'after' => 1, 'component' => 'embed', 'variant' => '', 'embed' => $e['id'] ) );
	twd_sk_true( ! is_wp_error( $r ), 'added' );
	$secs = TWD_SK_Sections::split( $r['html'] );
	twd_sk_eq( 'embed', TWD_SK_Sections::type_of( $secs['sections'][2] ) );
	twd_sk_has( '[twd_embed id="' . $e['id'] . '"]', $secs['sections'][2] );
	twd_sk_hasnt( 'id="0"', $secs['sections'][2] );
	twd_sk_has( '[PLACEHOLDER: Heading here]', $secs['sections'][2], 'the heading still has to be written' );
	twd_sk_has( 'Booking calendar', implode( ' ', $r['report'] ) );
	twd_sk_eq( $v, TWD_SK_Store::get_current_version_id( 40 ), 'nothing saved' );
	$bare = TWD_SK_AI::add_section( 40, array( 'after' => 0, 'component' => 'embed', 'variant' => 'twd-sk-embed--bare', 'embed' => (string) $e['id'] ) );
	$bsec = TWD_SK_Sections::split( $bare['html'] )['sections'][1];
	twd_sk_has( '[twd_embed id="' . $e['id'] . '"]', $bsec );
	twd_sk_hasnt( 'Heading here', $bsec, 'the bare layout has no heading to write' );
	// Refusals.
	twd_sk_is_error( 'twd_sk_bad_input', TWD_SK_AI::add_section( 40, array( 'after' => 1, 'component' => 'embed' ) ), 'no embed chosen' );
	twd_sk_is_error( 'twd_sk_bad_input', TWD_SK_AI::add_section( 40, array( 'after' => 1, 'component' => 'embed', 'embed' => 99 ) ), 'an embed that is not saved' );
	twd_sk_is_error( 'twd_sk_bad_input', TWD_SK_AI::add_section( 40, array( 'after' => 1, 'component' => 'embed', 'embed' => $e['id'], 'use_ai' => true ) ), 'the AI does not write one' );
	twd_sk_is_error( 'twd_sk_bad_input', TWD_SK_AI::add_section( 40, array( 'after' => 1, 'component' => 'embed', 'embed' => $e['id'], 'plan' => true ) ), 'nor does the planner' );
	$GLOBALS['twd_stub']['caps'] = array( 'edit_pages', 'edit_post:40' );
	twd_sk_is_error( 'twd_sk_forbidden', TWD_SK_AI::add_section( 40, array( 'after' => 1, 'component' => 'embed', 'embed' => $e['id'] ) ), 'an editor cannot' );
} );

twd_sk_test( 'embed: the AI, a brief, a set and the planner never add one; the menu offers it to administrators only; the section is locked; the prompt says leave it', function () {
	twd_sk_is_error( 'twd_sk_bad_input', TWD_SK_Recipes::clean_parts( array( 'hero', 'embed' ) ) );
	twd_sk_true( ! isset( TWD_SK_Recipes::parts_menu()['embed'] ) );
	twd_sk_true( isset( TWD_SK_Recipes::parts_menu( true )['embed'] ) );
	twd_sk_eq( array( 'embed' ), TWD_SK_Recipes::admin_only_components() );
	$e = TWD_SK_Plan::check_change( array( 'do' => 'add', 'component' => 'embed', 'what' => 'x' ), 3 );
	twd_sk_is_error( 'twd_sk_bad_input', $e );
	twd_sk_has( 'administrator', $e->get_error_message() );
	twd_sk_true( is_array( TWD_SK_Plan::check_change( array( 'do' => 'add', 'component' => 'faq', 'what' => 'x' ), 3 ) ), 'other sections still can' );
	twd_sk_true( in_array( 'embed', TWD_SK_Sections::locked_types(), true ), 'locked' );
	twd_sk_has( 'Never add one', implode( "\n", TWD_SK_Prompt::rules() ) );
	twd_sk_hasnt( '- embed:', TWD_SK_Plan::message( '<section class="twd-sk-hero"><h1 class="twd-sk-hero__title">x</h1></section>', 'x' ), 'the planner is not offered it' );
} );

twd_sk_test( 'embed: the site report counts embeds and warns about a missing one, never printing code or names', function () {
	twd_sk_eq( array( 'No embeds saved.' ), TWD_SK_Doctor::embeds() );
	$e = twd_sk_embed_ok( 'Named', 'html', '<p>code</p>' );
	twd_sk_embed_page( '<p>[twd_embed id="' . $e['id'] . '"] [twd_embed id="9"]</p>' );
	$lines = implode( "\n", TWD_SK_Doctor::embeds() );
	twd_sk_has( '1 saved', $lines );
	twd_sk_has( 'Embed 1: html, 11 characters, on 1 page(s).', $lines );
	twd_sk_has( 'embed number 9', $lines );
	twd_sk_hasnt( 'Named', $lines );
	twd_sk_hasnt( '<p>code</p>', $lines );
} );

twd_sk_test( 'embed: the Site tab script has the Embeds section, builds it with DOM calls, and hands a new section to the normal preview', function () {
	$js = file_get_contents( ABSPATH . 'assets/twd-site-kit-editor-site.js' );
	foreach ( array( "'/site/embeds'", "buildEmbedsSection(panel)", "'Add to this page'", "component: 'embed'", "twd-sk-embed--bare", "leave out the heading", 'ED.proposeHtml(', "'Embeds'" ) as $needle ) {
		twd_sk_has( $needle, $js );
	}
	twd_sk_hasnt( 'innerHTML', $js );
	$main = file_get_contents( ABSPATH . 'assets/twd-site-kit-editor.js' );
	twd_sk_has( 'proposeHtml: function', $main );
	twd_sk_has( 'doPreview();', $main );
	$php = file_get_contents( ABSPATH . 'includes/class-twd-sk-editor.php' );
	twd_sk_has( "'embeds'         =>", $php );
	twd_sk_has( 'TWD_SK_Embed::summaries()', $php );
} );

twd_sk_test( 'embed: the class holds no network calls and no eval, and the stylesheet gives the section room', function () {
	$src = file_get_contents( ABSPATH . 'includes/class-twd-sk-embed.php' );
	foreach ( array( 'wp_remote_', 'curl_', 'file_get_contents', 'eval(', 'include ', 'require ' ) as $bad ) {
		twd_sk_hasnt( $bad, $src, $bad );
	}
	$css = file_get_contents( ABSPATH . 'assets/twd-site-kit.css' );
	foreach ( array( 'twd-sk-embed.twd-sk-embed', 'twd-sk-embed__content.twd-sk-embed__content', 'twd-sk-embed__missing.twd-sk-embed__missing' ) as $needle ) {
		twd_sk_has( $needle, $css );
	}
} );

// -- The line shown while the articles grid is empty ----------------------------------------------------

twd_sk_test( 'no articles line: both Resources layouts hold it as their own paragraph after the grid, and the cleaner keeps it', function () {
	$c = TWD_SK_Registry::get( 'resources' );
	$line = '<p class="twd-sk-resources__none">No articles yet. Please check back soon.</p>';
	foreach ( array( $c['skeleton'], $c['variants']['twd-sk-resources--strip']['skeleton'] ) as $sk ) {
		twd_sk_has( $line, $sk );
		twd_sk_true( strpos( $sk, '[twd_articles' ) < strpos( $sk, $line ), 'after the grid' );
		twd_sk_eq( $sk, TWD_SK_Sanitizer::clean( $sk ), 'survives the cleaner untouched' );
	}
	twd_sk_true( in_array( 'twd-sk-resources__none', $c['classes'], true ) );
	twd_sk_hasnt( '__none', TWD_SK_Starters::build( array( array( 'text', '' ) ) ), 'other sections are unchanged' );
	twd_sk_has( $line, TWD_SK_Starters::build( array( array( 'resources', '' ) ) ), 'a new Resources section keeps its plain wording, not a marker' );
	twd_sk_eq( array(), array_intersect_key( TWD_SK_Sanitizer::find_leftovers( $line ), array( 'No articles' => 1 ) ) );
} );

twd_sk_test( 'no articles line: hidden by default, shown only when the grid says it is empty and then in place of the plugin\'s own line; each :has() rule stands alone', function () {
	$css = file_get_contents( ABSPATH . 'assets/twd-site-kit.css' );
	preg_match( '/\.twd-sk-page \.twd-sk-resources__none\.twd-sk-resources__none \{([^}]*)\}/', $css, $m );
	twd_sk_has( 'display: none;', $m[1], 'hidden by default' );
	twd_sk_has( '.twd-ap-articles-none.twd-ap-articles-none:not([hidden])) .twd-sk-resources__none.twd-sk-resources__none {', $css, 'shown when the plugin line is not hidden' );
	twd_sk_has( ':has(.twd-sk-resources__none.twd-sk-resources__none) .twd-ap-articles-none.twd-ap-articles-none {', $css, 'the plugin line steps aside only where ours exists' );
	// A rule that mixes :has() with other selectors would be dropped whole by an older browser.
	foreach ( explode( "\n", $css ) as $line ) {
		if ( false !== strpos( $line, ':has(' ) && false !== strpos( $line, 'resources' ) ) {
			twd_sk_true( false === strpos( $line, ',' ), 'a lone selector: ' . $line );
		}
	}
	$inline = file_get_contents( ABSPATH . 'assets/twd-site-kit-inline.css' );
	twd_sk_has( 'body.twd-sk-inline-on .twd-sk-resources__none[data-twd-sk-t] { display: block !important; }', $inline, 'always reachable while editing' );
} );

twd_sk_test( 'no articles line: the Visual editor numbers it as a piece of text, so it can be changed in place', function () {
	$sk   = TWD_SK_Registry::get( 'resources' )['skeleton'];
	$html = TWD_SK_Inline::mark( TWD_SK_Starters::build( array( array( 'hero', '' ), array( 'resources', '' ) ) ) );
	twd_sk_true( 1 === preg_match( '/<p class="twd-sk-resources__none" data-twd-sk-t="\d+">No articles yet\. Please check back soon\.<\/p>/', $html ), 'numbered' );
	// A save of new words replaces them like any other piece of text.
	$stored = TWD_SK_Starters::build( array( array( 'hero', '' ), array( 'resources', '' ) ) );
	preg_match( '/<p class="twd-sk-resources__none" data-twd-sk-t="(\d+)"/', $html, $n );
	$new = TWD_SK_Inline::apply( $stored, array( array( 'i' => (int) $n[1], 'html' => 'Articles are on the way.' ) ), array() );
	twd_sk_true( ! is_wp_error( $new ), is_wp_error( $new ) ? $new->get_error_message() : '' );
	twd_sk_has( '<p class="twd-sk-resources__none">Articles are on the way.</p>', $new['html'] );
} );
