<?php
// Sections and the AI remix: a candidate page, never a saved one.

function twd_sk_ai_page( $id = 40, $html = null ) {
	twd_stub_reset();
	twd_stub_add_post( $id, 'page', '[twd_page]' );
	$html = null === $html ? file_get_contents( ABSPATH . 'starters/page-home.html' ) : $html;
	$r = TWD_SK_Store::save( $id, $html );
	twd_sk_true( ! is_wp_error( $r ), 'page saved' );
	return TWD_SK_Store::get_current( $id );
}
/** Install a fake AI service. $answer is a string, a WP_Error, null, or a function( system, message ). */
function twd_sk_ai_provider( $answer, $configured = true ) {
	$GLOBALS['twd_stub']['hooks']['twd_ai_is_configured'] = array( function () use ( $configured ) {
		return $configured;
	} );
	$GLOBALS['twd_stub']['ai_calls'] = array();
	$GLOBALS['twd_stub']['hooks']['twd_ai_complete'] = array( function ( $value, $system, $message, $tokens ) use ( $answer ) {
		$GLOBALS['twd_stub']['ai_calls'][] = array( 'system' => $system, 'message' => $message, 'tokens' => $tokens );
		return is_callable( $answer ) ? call_user_func( $answer, $system, $message ) : $answer;
	} );
}

twd_sk_test( 'sections: splitting then joining gives the exact same bytes, for every starter and the gallery', function () {
	foreach ( glob( ABSPATH . 'starters/*.html' ) as $file ) {
		$html  = file_get_contents( $file );
		$parts = TWD_SK_Sections::split( $html );
		twd_sk_eq( $html, TWD_SK_Sections::join( $parts['gaps'], $parts['sections'] ), basename( $file ) );
		twd_sk_eq( count( $parts['sections'] ) + 1, count( $parts['gaps'] ), basename( $file ) . ' gaps' );
	}
	$parts = TWD_SK_Sections::split( "x<section class=\"a\"><section>inner</section></section> y <section>two</section>z" );
	twd_sk_eq( 2, count( $parts['sections'] ), 'a section inside a section stays inside its parent' );
	twd_sk_eq( array(), TWD_SK_Sections::split( '' )['sections'] );
	twd_sk_eq( 0, count( TWD_SK_Sections::split( '</section><p>no sections</p>' )['sections'] ) );
} );

twd_sk_test( 'sections: type, label and locked flag for the Home starter', function () {
	$list = TWD_SK_Sections::describe( file_get_contents( ABSPATH . 'starters/page-home.html' ) );
	$types = array();
	foreach ( $list as $s ) {
		$types[] = $s['type'];
	}
	twd_sk_eq( array( 'hero', 'services', 'image-text', 'quote', 'cta', 'resources' ), $types );
	twd_sk_true( $list[3]['locked'] && ! $list[0]['locked'], 'only the quote is locked' );
	twd_sk_has( 'Main heading here', $list[0]['label'] );
	foreach ( $list as $s ) {
		twd_sk_true( strlen( $s['label'] ) <= 63, 'label length' );
	}
	$long = TWD_SK_Sections::label_of( '<section class="twd-sk-text"><h2>' . str_repeat( 'word ', 30 ) . '</h2></section>' );
	twd_sk_true( strlen( $long ) <= 63 && '...' === substr( $long, -3 ) );
	twd_sk_eq( 'section', TWD_SK_Sections::type_of( '<section>x</section>' ) );
} );

twd_sk_test( 'ai: available only when a plugin says a key is set up, and never in safe mode', function () {
	twd_sk_ai_page();
	twd_sk_true( ! TWD_SK_AI::available(), 'no provider' );
	twd_sk_ai_provider( 'x', false );
	twd_sk_true( ! TWD_SK_AI::available(), 'provider with no key' );
	twd_sk_ai_provider( 'x', true );
	twd_sk_true( TWD_SK_AI::available() );
	TWD_SK_Safe::set( true );
	twd_sk_true( ! TWD_SK_AI::available(), 'safe mode' );
	twd_sk_is_error( 'twd_sk_ai_unavailable', TWD_SK_AI::remix( 40, array( 'mode' => 'page', 'instruction' => 'x' ) ) );
	TWD_SK_Safe::set( false );
} );

twd_sk_test( 'ai remix, sections: only the chosen section changes and every other byte is kept', function () {
	$before = twd_sk_ai_page();
	$parts  = TWD_SK_Sections::split( $before );
	$new    = str_replace( 'Heading here', 'A warmer heading', $parts['sections'][2] );
	twd_sk_ai_provider( $new );
	$r = TWD_SK_AI::remix( 40, array( 'mode' => 'sections', 'indexes' => array( 2 ), 'instruction' => 'Make it warmer.' ) );
	twd_sk_true( ! is_wp_error( $r ), 'ok' );
	$after = TWD_SK_Sections::split( $r['html'] );
	twd_sk_eq( array( 2 ), $r['changed'] );
	foreach ( $parts['sections'] as $i => $s ) {
		if ( 2 === $i ) {
			twd_sk_has( 'A warmer heading', $after['sections'][ $i ] );
		} else {
			twd_sk_eq( $s, $after['sections'][ $i ], 'section ' . $i . ' untouched' );
		}
	}
	twd_sk_eq( $parts['gaps'], $after['gaps'], 'the gaps are untouched' );
	twd_sk_eq( $before, TWD_SK_Store::get_current( 40 ), 'nothing was saved' );
	$call = $GLOBALS['twd_stub']['ai_calls'][0];
	twd_sk_has( 'Make it warmer.', $call['message'] );
	twd_sk_has( 'you are changing this one', $call['message'] );
	twd_sk_hasnt( 'Quote text here', $call['message'], 'sections not chosen are not sent in full' );
	twd_sk_eq( TWD_SK_AI::TOKENS_SECTIONS, $call['tokens'] );
	twd_sk_has( 'exactly the same number of section elements', $call['system'] );
	twd_sk_hasnt( 'Ask a question first', $call['system'], 'chat-only rules are left out' );
	twd_sk_hasnt( "\xE2\x80\x94", $call['system'] );
} );

twd_sk_test( 'ai remix: a code fence around the answer is removed, and a wrong section count is refused', function () {
	$before = twd_sk_ai_page();
	$parts  = TWD_SK_Sections::split( $before );
	twd_sk_ai_provider( "```html\n" . $parts['sections'][0] . "\n```" );
	$r = TWD_SK_AI::remix( 40, array( 'mode' => 'sections', 'indexes' => array( 0 ), 'instruction' => 'Shorter.' ) );
	twd_sk_true( ! is_wp_error( $r ) );
	twd_sk_eq( array(), $r['changed'], 'identical answer means no change' );
	twd_sk_has( 'The AI made no change', implode( ' ', $r['report'] ) );
	twd_sk_ai_provider( $parts['sections'][0] . $parts['sections'][1] );
	twd_sk_is_error( 'twd_sk_ai_bad_result', TWD_SK_AI::remix( 40, array( 'mode' => 'sections', 'indexes' => array( 0 ), 'instruction' => 'x' ) ) );
	twd_sk_ai_provider( 'Sorry, I cannot help.' );
	twd_sk_is_error( 'twd_sk_ai_bad_result', TWD_SK_AI::remix( 40, array( 'mode' => 'sections', 'indexes' => array( 0 ), 'instruction' => 'x' ) ) );
	twd_sk_is_error( 'twd_sk_ai_bad_result', TWD_SK_AI::remix( 40, array( 'mode' => 'page', 'instruction' => 'x' ) ) );
} );

twd_sk_test( 'ai remix: a locked section keeps its words, but may change layout; unlocking allows new words', function () {
	$before = twd_sk_ai_page();
	$parts  = TWD_SK_Sections::split( $before );
	$quote  = $parts['sections'][3];
	twd_sk_ai_provider( str_replace( 'Quote text here', 'An invented glowing review', $quote ) );
	$r = TWD_SK_AI::remix( 40, array( 'mode' => 'sections', 'indexes' => array( 3 ), 'instruction' => 'Make it punchier.' ) );
	twd_sk_true( ! is_wp_error( $r ) );
	twd_sk_hasnt( 'invented glowing review', $r['html'], 'wording kept' );
	twd_sk_eq( array( 3 ), $r['kept'] );
	twd_sk_has( 'locked', implode( ' ', $r['report'] ) );
	twd_sk_eq( $before, $r['html'], 'page identical' );

	$layout = str_replace( 'twd-sk-quote--cards', 'twd-sk-quote--single', $quote );
	twd_sk_ai_provider( $layout );
	$r = TWD_SK_AI::remix( 40, array( 'mode' => 'sections', 'indexes' => array( 3 ), 'instruction' => 'Single quote layout.' ) );
	twd_sk_eq( array( 3 ), $r['changed'], 'a layout change with the same words is allowed' );

	twd_sk_ai_provider( str_replace( 'Quote text here', 'New words the therapist supplied', $quote ) );
	$r = TWD_SK_AI::remix( 40, array( 'mode' => 'sections', 'indexes' => array( 3 ), 'unlock' => array( 3 ), 'instruction' => 'Use the new words.' ) );
	twd_sk_has( 'New words the therapist supplied', $r['html'], 'unlocked' );
} );

twd_sk_test( 'ai remix, whole page: a candidate page, and locked wording must survive', function () {
	$before = twd_sk_ai_page();
	$parts  = TWD_SK_Sections::split( $before );
	$ok     = $parts['sections'];
	$ok[0]  = str_replace( 'Main heading here', 'A friendlier heading', $ok[0] );
	twd_sk_ai_provider( "Here you go:\n" . implode( "\n", $ok ) );
	$r = TWD_SK_AI::remix( 40, array( 'mode' => 'page', 'instruction' => 'Friendlier.' ) );
	twd_sk_true( ! is_wp_error( $r ) );
	twd_sk_has( 'A friendlier heading', $r['html'] );
	twd_sk_hasnt( 'Here you go', $r['html'], 'commentary outside sections is dropped' );
	twd_sk_eq( TWD_SK_AI::TOKENS_PAGE, $GLOBALS['twd_stub']['ai_calls'][0]['tokens'] );

	$bad = $parts['sections'];
	$bad[3] = str_replace( 'Quote text here', 'Something invented', $bad[3] );
	twd_sk_ai_provider( implode( "\n", $bad ) );
	twd_sk_is_error( 'twd_sk_ai_locked', TWD_SK_AI::remix( 40, array( 'mode' => 'page', 'instruction' => 'x' ) ) );
	twd_sk_eq( $before, TWD_SK_Store::get_current( 40 ), 'nothing saved' );
} );

twd_sk_test( 'ai remix: bad input, a provider error and a silent provider are all plain errors', function () {
	twd_sk_ai_page();
	twd_sk_ai_provider( 'x' );
	twd_sk_is_error( 'twd_sk_bad_input', TWD_SK_AI::remix( 40, array( 'mode' => 'page', 'instruction' => '   ' ) ) );
	twd_sk_is_error( 'twd_sk_bad_input', TWD_SK_AI::remix( 40, array( 'mode' => 'page', 'instruction' => str_repeat( 'a', 1600 ) ) ) );
	twd_sk_is_error( 'twd_sk_bad_input', TWD_SK_AI::remix( 40, array( 'mode' => 'page', 'instruction' => 'x', 'facts' => str_repeat( 'a', 3100 ) ) ) );
	twd_sk_is_error( 'twd_sk_bad_input', TWD_SK_AI::remix( 40, array( 'mode' => 'sections', 'indexes' => array(), 'instruction' => 'x' ) ), 'no section chosen' );
	twd_sk_is_error( 'twd_sk_bad_input', TWD_SK_AI::remix( 40, array( 'mode' => 'sections', 'indexes' => array( 99, -1, 'x' ), 'instruction' => 'x' ) ), 'out of range indexes' );
	twd_sk_ai_provider( new WP_Error( 'twd_ap_ai_bad_key', 'The API key on this site was rejected.' ) );
	twd_sk_is_error( 'twd_ap_ai_bad_key', TWD_SK_AI::remix( 40, array( 'mode' => 'page', 'instruction' => 'x' ) ) );
	twd_sk_ai_provider( null );
	twd_sk_is_error( 'twd_sk_ai_unavailable', TWD_SK_AI::remix( 40, array( 'mode' => 'page', 'instruction' => 'x' ) ) );
	twd_stub_add_post( 42, 'page', '' );
	twd_sk_ai_provider( 'x' );
	twd_sk_is_error( 'twd_sk_empty_page', TWD_SK_AI::remix( 42, array( 'mode' => 'page', 'instruction' => 'x' ) ) );
} );

twd_sk_test( 'ai remix: facts keep their line breaks, lose tags and long dashes, and are sent only as facts', function () {
	$before = twd_sk_ai_page();
	$parts  = TWD_SK_Sections::split( $before );
	twd_sk_ai_provider( $parts['sections'][0] );
	TWD_SK_AI::remix( 40, array( 'mode' => 'sections', 'indexes' => array( 0 ), 'instruction' => 'Use <b>the facts</b> please', 'facts' => "Works with men\n<script>x</script>and boys \xE2\x80\x94 online" ) );
	$m = $GLOBALS['twd_stub']['ai_calls'][0]['message'];
	twd_sk_has( "Works with men\nxand boys", str_replace( array( '  ', 'x ' ), array( ' ', 'x' ), $m ) );
	twd_sk_hasnt( '<script>', $m );
	twd_sk_hasnt( '<b>', $m );
	twd_sk_hasnt( "\xE2\x80\x94", $m );
	twd_sk_has( 'the only new facts you may use', $m );
} );

twd_sk_test( 'ai rest: sections and remix routes are guarded, tied to the page version, and absent in safe mode', function () {
	twd_sk_ai_page();
	twd_sk_ai_provider( 'x' );
	$routes = array_values( array_filter( twd_sk_rest_routes(), function ( $r ) {
		return false !== strpos( $r['route'], '/sections' ) || false !== strpos( $r['route'], '/remix' );
	} ) );
	twd_sk_eq( 2, count( $routes ) );
	$got = TWD_SK_REST::get_sections( twd_sk_rest_req( array( 'id' => 40 ) ) );
	twd_sk_eq( true, $got['ai'] );
	twd_sk_eq( 6, count( $got['sections'] ) );
	$ver = TWD_SK_Store::get_current_version_id( 40 );
	$parts = TWD_SK_Sections::split( TWD_SK_Store::get_current( 40 ) );
	twd_sk_ai_provider( $parts['sections'][0] );
	$ok = TWD_SK_REST::post_remix( twd_sk_rest_req( array( 'id' => 40, 'base_version' => $ver, 'mode' => 'sections', 'indexes' => array( 0 ), 'instruction' => 'Shorter.' ) ) );
	twd_sk_true( is_array( $ok ) && isset( $ok['html'] ), 'remix returns a candidate' );
	twd_sk_eq( 409, twd_sk_status( TWD_SK_REST::post_remix( twd_sk_rest_req( array( 'id' => 40, 'base_version' => $ver + 5, 'mode' => 'page', 'instruction' => 'x' ) ) ) ), 'stale version' );
	twd_sk_eq( 400, twd_sk_status( TWD_SK_REST::post_remix( twd_sk_rest_req( array( 'id' => 40, 'mode' => 'page', 'instruction' => 'x' ) ) ) ), 'no base version' );
	twd_sk_eq( 400, twd_sk_status( TWD_SK_REST::post_remix( twd_sk_rest_req( array( 'id' => 40, 'base_version' => $ver, 'mode' => 'page', 'instruction' => '' ) ) ) ) );
	twd_sk_ai_provider( new WP_Error( 'twd_ap_ai_bad_key', 'Rejected.' ) );
	twd_sk_eq( 502, twd_sk_status( TWD_SK_REST::post_remix( twd_sk_rest_req( array( 'id' => 40, 'base_version' => $ver, 'mode' => 'page', 'instruction' => 'x' ) ) ) ) );
	twd_sk_ai_provider( null, false );
	twd_sk_eq( 503, twd_sk_status( TWD_SK_REST::post_remix( twd_sk_rest_req( array( 'id' => 40, 'base_version' => $ver, 'mode' => 'page', 'instruction' => 'x' ) ) ) ) );
	TWD_SK_Safe::set( true );
	$left = array_filter( twd_sk_rest_routes(), function ( $r ) {
		return false !== strpos( $r['route'], '/sections' ) || false !== strpos( $r['route'], '/remix' );
	} );
	twd_sk_eq( 0, count( $left ) );
	TWD_SK_Safe::set( false );
} );

twd_sk_test( 'ai: this plugin holds no key and makes no web request of its own to an AI service', function () {
	foreach ( array( 'class-twd-sk-ai.php', 'class-twd-sk-sections.php' ) as $file ) {
		$php = file_get_contents( ABSPATH . 'includes/' . $file );
		foreach ( array( 'wp_remote_', 'curl_', 'file_get_contents( \'http', 'api.anthropic.com', 'x-api-key', 'sk-ant' ) as $bad ) {
			twd_sk_hasnt( $bad, $php, $file );
		}
	}
} );

twd_sk_test( 'ai rest: asking the AI is limited to 10 every 10 minutes per user (429), apart from other limits', function () {
	twd_sk_rest_setup( array( 'edit_pages', 'edit_post:12' ) );
	for ( $i = 0; $i < 10; $i++ ) {
		twd_sk_eq( true, TWD_SK_REST::can_remix_page( twd_sk_rest_req() ), 'call ' . ( $i + 1 ) );
	}
	$e = TWD_SK_REST::can_remix_page( twd_sk_rest_req() );
	twd_sk_is_error( 'twd_sk_rate_limited', $e );
	twd_sk_eq( 429, twd_sk_status( $e ) );
	twd_sk_eq( true, TWD_SK_REST::can_write_page( twd_sk_rest_req() ), 'normal writes are not affected' );
} );
