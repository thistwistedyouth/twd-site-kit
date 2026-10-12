<?php
// 0.10.0: the one-box planner.

/** A kit page 40 with hero, services, image and text, quote (locked) and cta; and a stand-in AI. */
function twd_sk_plan_setup( $plan_json ) {
	twd_stub_add_post( 40, 'page', '[twd_page]' );
	$GLOBALS['twd_stub']['caps'] = array( 'edit_pages', 'edit_post:40' );
	$html = TWD_SK_Starters::build( array( array( 'hero', 'twd-sk-hero--split' ), array( 'services', 'twd-sk-services--cards' ), array( 'image_text', '' ), array( 'quote', 'twd-sk-quote--cards' ), array( 'cta', 'twd-sk-cta--band' ) ) );
	TWD_SK_Store::save( 40, $html );
	twd_sk_ai_provider( function ( $system, $message ) use ( $plan_json ) {
		if ( 0 === strpos( $system, 'You plan changes' ) ) {
			return $plan_json;
		}
		// Rewriting sections: send back the sections it was given, with their markers filled in.
		$parts = explode( '--- Section ', $message );
		array_shift( $parts );
		$out = array();
		foreach ( $parts as $p ) {
			$out[] = preg_replace( '/\[PLACEHOLDER:[^\]]*\]/', 'Written by the AI', substr( $p, strpos( $p, "\n" ) + 1 ) );
		}
		return implode( "\n", $out );
	} );
	return TWD_SK_Store::get_current( 40 );
}
function twd_sk_plan_types( $html ) {
	return array_map( function ( $s ) {
		return $s['type'] . ( false !== strpos( TWD_SK_Sections::split( $GLOBALS['twd_sk_plan_html'] )['sections'][ $s['index'] ], '--glow' ) ? '-glow' : '' ) . ( false !== strpos( TWD_SK_Sections::split( $GLOBALS['twd_sk_plan_html'] )['sections'][ $s['index'] ], '--slider' ) ? '-slider' : '' );
	}, TWD_SK_Sections::describe( $GLOBALS['twd_sk_plan_html'] = $html ) );
}
/** Run every change of a plan through the step route logic, as the browser does. */
function twd_sk_plan_run( $changes, $request ) {
	$html  = '';
	$order = null;
	$out   = array();
	foreach ( $changes as $c ) {
		$r = TWD_SK_Plan::step( 40, $html, $order, $c, $request );
		twd_sk_true( ! is_wp_error( $r ), is_wp_error( $r ) ? $r->get_error_message() : '' );
		$html  = $r['html'];
		$order = $r['order'];
		$out[] = $r;
	}
	return array( 'html' => $html, 'order' => $order, 'steps' => $out );
}

twd_sk_test( 'plan: the prompt lists the page sections, every component that may be added (with the glow boxes and the slider), and the request', function () {
	$html = twd_sk_plan_setup( '{}' );
	$msg  = TWD_SK_Plan::message( $html, 'Add three glowing boxes' );
	twd_sk_has( '1. hero: ', $msg );
	twd_sk_has( '4. quote: ', $msg );
	twd_sk_has( 'its words are locked', $msg );
	twd_sk_has( '"twd-sk-services--glow"', $msg );
	twd_sk_has( '"twd-sk-quote--slider"', $msg );
	twd_sk_hasnt( '- hero:', $msg, 'a second hero is never offered' );
	twd_sk_has( "The request:\nAdd three glowing boxes", $msg );
	$sys = TWD_SK_Plan::system_prompt();
	foreach ( array( 'word for word', 'Never invent', '"cannot"', 'Never remove section 1', 'Site tab, under Style' ) as $needle ) {
		twd_sk_has( $needle, $sys );
	}
} );

twd_sk_test( 'plan: the AI checklist is checked against the page and the kit; bad items are left out with a reason', function () {
	$json = json_encode( array(
		'changes' => array(
			array( 'do' => 'edit', 'sections' => array( 1 ), 'what' => 'Change the first paragraph to: We bring the van to you.' ),
			array( 'do' => 'add', 'after' => 2, 'component' => 'services', 'variant' => 'twd-sk-services--glow', 'what' => 'Three boxes: garden parties, weddings, festivals' ),
			array( 'do' => 'add', 'after' => 5, 'component' => 'quote', 'variant' => 'twd-sk-quote--slider', 'what' => 'The two testimonials given, word for word' ),
			array( 'do' => 'remove', 'sections' => array( 1 ) ),
			array( 'do' => 'add', 'after' => 2, 'component' => 'video', 'what' => 'A video' ),
			array( 'do' => 'edit', 'sections' => array( 99 ), 'what' => 'x' ),
			array( 'do' => 'dance' ),
		),
		'cannot'  => array( array( 'what' => 'A video of the van', 'nearest' => 'A picture with a caption' ) ),
	) );
	twd_sk_plan_setup( "```json\n" . $json . "\n```" );
	$p = TWD_SK_Plan::plan( 40, 'Change the first paragraph...' );
	twd_sk_true( is_array( $p ), is_wp_error( $p ) ? $p->get_error_message() : '' );
	twd_sk_eq( 3, count( $p['changes'] ) );
	twd_sk_eq( array( 0 ), $p['changes'][0]['sections'], '1-based from the AI, 0-based here' );
	twd_sk_eq( 1, $p['changes'][1]['after'] );
	twd_sk_eq( 'twd-sk-services--glow', $p['changes'][1]['variant'] );
	twd_sk_has( 'Change section 1 (hero', $p['changes'][0]['label'] );
	twd_sk_has( 'Add a new Services list (Glow) after section 2 (services', $p['changes'][1]['label'] );
	twd_sk_has( 'Add a new Quote or testimonial (Slider) after section 5 (cta', $p['changes'][2]['label'] );
	twd_sk_eq( 4, count( $p['dropped'] ) );
	twd_sk_has( 'cannot be removed', implode( ' ', $p['dropped'] ) );
	twd_sk_eq( 'A picture with a caption', $p['cannot'][0]['nearest'] );
	twd_sk_eq( 3000, $GLOBALS['twd_stub']['ai_calls'][0]['tokens'] );
} );

twd_sk_test( 'plan: refusals are plain (no AI, nothing asked, too long, unreadable, nothing found, ran out of room)', function () {
	twd_sk_plan_setup( 'Sorry, no.' );
	twd_sk_is_error( 'twd_sk_bad_input', TWD_SK_Plan::plan( 40, '  ' ) );
	twd_sk_is_error( 'twd_sk_bad_input', TWD_SK_Plan::plan( 40, str_repeat( 'x', TWD_SK_Plan::MAX_REQUEST + 1 ) ) );
	twd_sk_is_error( 'twd_sk_ai_bad_result', TWD_SK_Plan::plan( 40, 'Make it nicer' ) );
	twd_sk_plan_setup( '{"changes":[],"cannot":[]}' );
	twd_sk_is_error( 'twd_sk_ai_bad_result', TWD_SK_Plan::plan( 40, 'Make it nicer' ) );
	twd_sk_ai_provider( new WP_Error( 'twd_ap_ai_cut_off', 'The AI ran out of room before finishing.' ) );
	twd_sk_is_error( 'twd_sk_ai_too_long', TWD_SK_Plan::plan( 40, 'Lots' ) );
	twd_sk_ai_provider( null, false );
	twd_sk_is_error( 'twd_sk_ai_unavailable', TWD_SK_Plan::plan( 40, 'Make it nicer' ) );
} );

twd_sk_test( 'plan: the changes run one at a time on a working copy, in place, with the request as the only new facts', function () {
	$saved   = twd_sk_plan_setup( '{}' );
	$request = "1. Change the first paragraph\n2. Add three glowing boxes\n3. Add these testimonials as a slider at the bottom: \"Brilliant\" Sam";
	$changes = array(
		array( 'do' => 'edit', 'sections' => array( 0 ), 'after' => -1, 'what' => 'Change the first paragraph' ),
		array( 'do' => 'add', 'sections' => array(), 'after' => 1, 'component' => 'services', 'variant' => 'twd-sk-services--glow', 'what' => 'Three boxes' ),
		array( 'do' => 'add', 'sections' => array(), 'after' => 4, 'component' => 'quote', 'variant' => 'twd-sk-quote--slider', 'what' => 'The testimonial "Brilliant" Sam, word for word' ),
	);
	$run = twd_sk_plan_run( $changes, $request );
	twd_sk_eq( array( 'hero', 'services', 'services-glow', 'image-text', 'quote', 'cta', 'quote-slider' ), twd_sk_plan_types( $run['html'] ) );
	twd_sk_eq( array( 0, 1, 'n1', 2, 3, 4, 'n2' ), $run['order'] );
	twd_sk_eq( $saved, TWD_SK_Store::get_current( 40 ), 'nothing saved' );
	foreach ( $GLOBALS['twd_stub']['ai_calls'] as $call ) {
		twd_sk_has( '"Brilliant" Sam', $call['message'], 'the request goes along as the supplied facts' );
	}
	twd_sk_eq( 1, substr_count( $run['html'], '<h1' ) );
} );

twd_sk_test( 'plan: remove and move need no AI, later changes still find their sections, and the hero never moves', function () {
	twd_sk_plan_setup( '{}' );
	$run = twd_sk_plan_run( array(
		array( 'do' => 'remove', 'sections' => array( 3 ), 'after' => -1 ),
		array( 'do' => 'move', 'sections' => array( 4 ), 'after' => 0 ),
	), 'Remove the quotes and put the call to action under the hero' );
	twd_sk_eq( 0, count( $GLOBALS['twd_stub']['ai_calls'] ) );
	twd_sk_eq( array( 'hero', 'cta', 'services', 'image-text' ), twd_sk_plan_types( $run['html'] ) );
	twd_sk_eq( array( 0, 4, 1, 2 ), $run['order'] );
	$r = TWD_SK_Plan::step( 40, $run['html'], $run['order'], array( 'do' => 'edit', 'sections' => array( 3 ), 'after' => -1, 'what' => 'x' ), 'x' );
	twd_sk_has( 'removed by an earlier change', $r['report'][0] );
	twd_sk_is_error( 'twd_sk_bad_input', TWD_SK_Plan::step( 40, '', null, array( 'do' => 'move', 'sections' => array( 0 ), 'after' => 2 ), 'x' ) );
	twd_sk_is_error( 'twd_sk_bad_input', TWD_SK_Plan::step( 40, '', null, array( 'do' => 'remove', 'sections' => array( 0 ) ), 'x' ) );
} );

twd_sk_test( 'plan: a working copy from the browser is cleaned, and an order list that does not fit it is refused', function () {
	twd_sk_plan_setup( '{}' );
	$first = TWD_SK_Plan::step( 40, '', null, array( 'do' => 'remove', 'sections' => array( 4 ) ), 'x' );
	$dirty = str_replace( '</section>', '<script>alert(1)</script></section>', $first['html'] );
	$r     = TWD_SK_Plan::step( 40, $dirty, $first['order'], array( 'do' => 'remove', 'sections' => array( 3 ) ), 'x' );
	twd_sk_hasnt( '<script', $r['html'] );
	foreach ( array( array( 0, 1 ), array( 0, 0, 1, 2 ), array( 0, 1, 2, 'x' ), 'nope', array( 0, 1, 2, 9 ) ) as $bad ) {
		twd_sk_is_error( 'twd_sk_bad_input', TWD_SK_Plan::step( 40, $first['html'], $bad, array( 'do' => 'remove', 'sections' => array( 3 ) ), 'x' ), json_encode( $bad ) );
	}
} );

twd_sk_test( 'plan: the routes need the version the editor opened, and the steps have their own limit', function () {
	twd_sk_rest_setup( array( 'edit_pages', 'edit_post:12' ) );
	TWD_SK_Store::save( 12, TWD_SK_Starters::build( array( array( 'hero', '' ), array( 'text', '' ) ) ) );
	twd_sk_ai_provider( '{"changes":[{"do":"edit","sections":[2],"what":"Shorter"}]}' );
	$stale = TWD_SK_REST::post_plan( twd_sk_rest_req( array( 'base_version' => 999, 'request' => 'Shorter' ) ) );
	twd_sk_eq( 409, twd_sk_status( $stale ) );
	$ok = TWD_SK_REST::post_plan( twd_sk_rest_req( array( 'base_version' => TWD_SK_Store::get_current_version_id( 12 ), 'request' => 'Shorter' ) ) );
	twd_sk_eq( 'edit', $ok['changes'][0]['do'] );
	$GLOBALS['twd_stub']['transients'] = array();
	for ( $i = 0; $i < 40; $i++ ) {
		twd_sk_true( true === TWD_SK_REST::can_plan_step( twd_sk_rest_req() ) );
	}
	twd_sk_eq( 429, twd_sk_status( TWD_SK_REST::can_plan_step( twd_sk_rest_req() ) ) );
} );
