<?php
// Adding a section (0.7.3): a candidate page with one new section, through the same preview and apply.

function twd_sk_add_page() {
	twd_stub_add_post( 40, 'page', '[twd_page]' );
	TWD_SK_Store::save( 40, file_get_contents( ABSPATH . 'starters/page-about.html' ) );
	return TWD_SK_Sections::split( TWD_SK_Store::get_current( 40 ) );
}

twd_sk_test( 'add section: without AI the outline goes in after the chosen section, with no second h1 and nothing saved', function () {
	$before = twd_sk_add_page();
	$v      = TWD_SK_Store::get_current_version_id( 40 );
	$r      = TWD_SK_AI::add_section( 40, array( 'after' => 1, 'component' => 'faq', 'variant' => '' ) );
	$after  = TWD_SK_Sections::split( $r['html'] );
	twd_sk_eq( count( $before['sections'] ) + 1, count( $after['sections'] ) );
	twd_sk_eq( 'faq', TWD_SK_Sections::type_of( $after['sections'][2] ) );
	twd_sk_eq( $before['sections'][2], $after['sections'][3], 'the rest of the page is untouched' );
	twd_sk_eq( $before['sections'][0], $after['sections'][0] );
	twd_sk_eq( array( 2 ), $r['changed'] );
	twd_sk_has( '[PLACEHOLDER', $after['sections'][2] );
	twd_sk_eq( 1, substr_count( $r['html'], '<h1' ), 'still one h1' );
	twd_sk_has( 'as section 3', implode( ' ', $r['report'] ) );
	twd_sk_eq( $v, TWD_SK_Store::get_current_version_id( 40 ), 'nothing saved' );
	$hero = TWD_SK_AI::add_section( 40, array( 'after' => 0, 'component' => 'hero', 'variant' => 'twd-sk-hero--compact' ) );
	twd_sk_eq( 1, substr_count( $hero['html'], '<h1' ), 'an added hero has an h2 title, not a second h1' );
} );

twd_sk_test( 'add section: a bad place, kind or layout is refused; an empty page is refused', function () {
	$parts = twd_sk_add_page();
	twd_sk_is_error( 'twd_sk_bad_input', TWD_SK_AI::add_section( 40, array( 'after' => count( $parts['sections'] ), 'component' => 'faq' ) ) );
	twd_sk_is_error( 'twd_sk_bad_input', TWD_SK_AI::add_section( 40, array( 'after' => -1, 'component' => 'faq' ) ) );
	twd_sk_is_error( 'twd_sk_bad_input', TWD_SK_AI::add_section( 40, array( 'after' => 'x', 'component' => 'faq' ) ) );
	twd_sk_is_error( 'twd_sk_bad_input', TWD_SK_AI::add_section( 40, array( 'after' => 0, 'component' => 'carousel' ) ) );
	twd_sk_is_error( 'twd_sk_bad_input', TWD_SK_AI::add_section( 40, array( 'after' => 0, 'component' => 'faq', 'variant' => 'twd-sk-made-up' ) ) );
	twd_stub_add_post( 41, 'page', '[twd_page]' );
	twd_sk_is_error( 'twd_sk_empty_page', TWD_SK_AI::add_section( 41, array( 'after' => 0, 'component' => 'faq' ) ) );
} );

twd_sk_test( 'add section: with AI only the new section is written, from the facts, and must come back as the same kind', function () {
	twd_sk_add_page();
	twd_sk_ai_provider( '<section class="twd-sk-faq"><h2 class="twd-sk-title">Questions</h2></section>' );
	$r = TWD_SK_AI::add_section( 40, array( 'after' => 0, 'component' => 'faq', 'use_ai' => true, 'instruction' => 'About booking', 'facts' => 'We open at nine.' ) );
	twd_sk_has( 'Questions', TWD_SK_Sections::split( $r['html'] )['sections'][1] );
	$call = $GLOBALS['twd_stub']['ai_calls'][0];
	twd_sk_has( 'This is a new section.', $call['message'] );
	twd_sk_has( 'Also: About booking', $call['message'] );
	twd_sk_has( 'We open at nine.', $call['message'] );
	twd_sk_has( '<- you are changing this one', $call['message'] );
	twd_sk_has( 'Reply with exactly the same number of section elements', $call['system'] );
	twd_sk_ai_provider( '<section class="twd-sk-text"><p>Wrong kind</p></section>' );
	twd_sk_is_error( 'twd_sk_ai_bad_result', TWD_SK_AI::add_section( 40, array( 'after' => 0, 'component' => 'faq', 'use_ai' => true ) ) );
	twd_sk_ai_provider( '<section class="twd-sk-faq"></section><section class="twd-sk-faq"></section>' );
	twd_sk_is_error( 'twd_sk_ai_bad_result', TWD_SK_AI::add_section( 40, array( 'after' => 0, 'component' => 'faq', 'use_ai' => true ) ) );
	twd_sk_ai_provider( 'x', false );
	twd_sk_is_error( 'twd_sk_ai_unavailable', TWD_SK_AI::add_section( 40, array( 'after' => 0, 'component' => 'faq', 'use_ai' => true ) ) );
} );

twd_sk_test( 'add section rest: refused when the page moved on; the AI limit applies only when the AI writes it', function () {
	twd_sk_rest_setup( array( 'edit_pages', 'edit_post:12' ) );
	TWD_SK_Store::save( 12, file_get_contents( ABSPATH . 'starters/page-about.html' ) );
	$v = TWD_SK_Store::get_current_version_id( 12 );
	$e = TWD_SK_REST::post_add_section( twd_sk_rest_req( array( 'base_version' => $v + 5, 'after' => 0, 'component' => 'faq' ) ) );
	twd_sk_eq( 409, $e->get_error_data()['status'] );
	$ok = TWD_SK_REST::post_add_section( twd_sk_rest_req( array( 'base_version' => $v, 'after' => 0, 'component' => 'faq' ) ) );
	twd_sk_eq( array( 1 ), $ok['changed'] );
	$e = TWD_SK_REST::post_add_section( twd_sk_rest_req( array( 'base_version' => $v, 'after' => 0, 'component' => 'faq', 'use_ai' => true ) ) );
	twd_sk_eq( 503, $e->get_error_data()['status'] );
	for ( $i = 0; $i < 10; $i++ ) {
		twd_sk_true( true === TWD_SK_REST::can_add_section( twd_sk_rest_req( array( 'use_ai' => true ) ) ) );
	}
	twd_sk_is_error( 'twd_sk_rate_limited', TWD_SK_REST::can_add_section( twd_sk_rest_req( array( 'use_ai' => true ) ) ) );
	twd_sk_true( true === TWD_SK_REST::can_add_section( twd_sk_rest_req() ), 'without AI it uses the preview limit' );
	twd_sk_is_error( 'twd_sk_forbidden', TWD_SK_REST::can_add_section( twd_sk_rest_req( array( 'id' => 13 ) ) ), 'not on a page they cannot edit' );
} );

twd_sk_test( 'planner js (0.10.0): one box replaces Add a section; the plan and each change use their own routes, and the result goes through the preview', function () {
	$js = file_get_contents( ABSPATH . 'assets/twd-site-kit-editor.js' );
	twd_sk_has( "api('POST', pagePath('/plan'), { base_version: state.version, request: request })", $js );
	twd_sk_has( "api('POST', pagePath('/plan/step'), { base_version: state.version, request: state.planRequest, change: item.change, html: work.html, order: work.order })", $js );
	twd_sk_has( 'buildPlanStep(panel);', $js );
	twd_sk_hasnt( 'buildAddSection', $js );
	$start = strpos( $js, 'function runPlan' );
	twd_sk_has( 'doPreview();', substr( $js, $start, 4000 ) );
	twd_sk_has( "maxlength: '6000'", $js );
	twd_sk_has( 'Not possible on a page: ', $js );
	$php = file_get_contents( ABSPATH . 'includes/class-twd-sk-editor.php' );
	twd_sk_has( "'components'     => (bool) \$context['is_kit_page'] ? TWD_SK_Recipes::parts_menu() : array(),", $php );
} );
