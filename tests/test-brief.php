<?php
// The site brief: export, read, check, apply.

function twd_sk_brief_example() {
	return json_decode( file_get_contents( ABSPATH . 'docs/site-brief.example.json' ), true );
}
function twd_sk_brief_reset() {
	twd_stub_reset();
}

twd_sk_test( 'brief: the documented example is valid, passes every check with no error and no ignored key', function () {
	twd_sk_brief_reset();
	$p = TWD_SK_Brief::plan( twd_sk_brief_example(), false );
	twd_sk_eq( array(), $p['errors'], 'no errors' );
	twd_sk_eq( array(), $p['ignored'], 'nothing ignored' );
	twd_sk_eq( array(), $p['blocking'] );
	$labels = array_map( function ( $i ) { return $i['label']; }, $p['items'] );
	foreach ( array( 'Site name', 'Menu', 'Footer text', 'Legal links', 'Practice facts' ) as $l ) {
		twd_sk_true( in_array( $l, $labels, true ), $l . ' would be set' );
	}
	twd_sk_eq( 3, count( $p['pages'] ) );
	twd_sk_eq( 'Working with anxiety', $p['pages'][2]['title'], 'a topic page takes its title from the topic' );
	foreach ( $p['items'] as $i ) {
		twd_sk_true( in_array( $i['action'], array( 'set', 'unchanged' ), true ), $i['label'] );
	}
} );

twd_sk_test( 'brief: parse tolerates a fence and a sentence, and refuses everything else with a plain message', function () {
	$json = file_get_contents( ABSPATH . 'docs/site-brief.example.json' );
	twd_sk_true( is_array( TWD_SK_Brief::parse( "Here is the file:\n```json\n" . $json . "\n```\nThanks" ) ) );
	twd_sk_is_error( 'twd_sk_bad_brief', TWD_SK_Brief::parse( '' ) );
	twd_sk_is_error( 'twd_sk_bad_brief', TWD_SK_Brief::parse( 'no json here' ) );
	twd_sk_is_error( 'twd_sk_bad_brief', TWD_SK_Brief::parse( '{ "schema": "twd-site-brief/1", ' ) );
	twd_sk_is_error( 'twd_sk_bad_brief', TWD_SK_Brief::parse( '{ "hello": 1 }' ) );
	twd_sk_is_error( 'twd_sk_bad_brief', TWD_SK_Brief::parse( '{ "schema": "something-else/9" }' ) );
	twd_sk_is_error( 'twd_sk_bad_brief', TWD_SK_Brief::parse( str_repeat( 'a', 200001 ) ) );
	twd_sk_is_error( 'twd_sk_bad_brief', TWD_SK_Brief::parse( array() ) );
} );

twd_sk_test( 'brief: applying sets the profile, header and footer settings, style and facts, and saves nothing else', function () {
	twd_sk_brief_reset();
	$r = TWD_SK_Brief::apply( twd_sk_brief_example(), array() );
	twd_sk_eq( array(), $r['errors'] );
	twd_sk_eq( array(), $r['pages'], 'no pages unless asked' );
	$p = TWD_SK_Profile::get();
	twd_sk_eq( 'Example Practice', $p['site_name'] );
	twd_sk_eq( 'hello@example.com', $p['email'] );
	twd_sk_eq( 4, count( $p['menu'] ) );
	twd_sk_eq( 'Counselling for adults, online.', $p['footer_text'] );
	twd_sk_eq( 'bar', TWD_SK_Chrome::settings()['header_variant'] );
	twd_sk_eq( 2, TWD_SK_Chrome::settings()['footer_columns'] );
	twd_sk_has( 'WHO I AM', TWD_SK_Facts::get() );
	twd_sk_eq( 'sage', TWD_SK_Packs::active_slug() );
	twd_sk_eq( 0, count( $GLOBALS['twd_stub']['inserted'] ), 'no page was created' );
} );

twd_sk_test( 'brief: filled-in details are kept unless overwrite is ticked, and the plan says so', function () {
	twd_sk_brief_reset();
	TWD_SK_Profile::save( array( 'site_name' => 'Old Name', 'footer_text' => 'Old footer.' ) );
	TWD_SK_Facts::save( 'OLD FACTS' );
	$plan = TWD_SK_Brief::plan( twd_sk_brief_example(), false );
	$by   = array();
	foreach ( $plan['items'] as $i ) {
		$by[ $i['label'] ] = $i['action'];
	}
	twd_sk_eq( 'skip_filled', $by['Site name'] );
	twd_sk_eq( 'skip_filled', $by['Footer text'] );
	twd_sk_eq( 'skip_filled', $by['Practice facts'] );
	twd_sk_eq( 'set', $by['Email'], 'an empty field is filled' );
	$r = TWD_SK_Brief::apply( twd_sk_brief_example(), array() );
	twd_sk_eq( 'Old Name', TWD_SK_Profile::get()['site_name'] );
	twd_sk_eq( 'OLD FACTS', TWD_SK_Facts::get() );
	twd_sk_has( 'was already filled in', implode( ' ', $r['skipped'] ) );
	TWD_SK_Brief::apply( twd_sk_brief_example(), array( 'overwrite' => true ) );
	twd_sk_eq( 'Example Practice', TWD_SK_Profile::get()['site_name'] );
	twd_sk_has( 'WHO I AM', TWD_SK_Facts::get() );
} );

twd_sk_test( 'brief: bad and unknown values are dropped or ignored with plain sentences, never stored', function () {
	twd_sk_brief_reset();
	$data = twd_sk_brief_example();
	$data['site']['email']          = 'not-an-email';
	$data['site']['surprise']       = 'x';
	$data['header']['menu']         = array( array( 'label' => 'Bad', 'url' => 'javascript:alert(1)' ) );
	$data['header']['layout']       = 'wild';
	$data['footer']['columns']      = 9;
	$data['style']['pack']          = 'ghost';
	$data['style']['tokens']        = array( 'color-primary' => 'red; background:url(x)', 'color-body' => '#333333', 'not-a-token' => '#000000' );
	$data['pages']                  = array( array( 'type' => 'nope' ), array( 'type' => 'service' ), 'text' );
	$data['credentials']            = array( 'password' => 'hunter2' );
	$data['facts']                  = array( 'not text' );
	$p = TWD_SK_Brief::plan( $data, false );
	$e = implode( ' ', $p['errors'] );
	foreach ( array( 'Email:', 'Menu:', 'Header layout:', 'Footer columns:', 'style pack does not exist', 'Page 1:', 'Page 2:', 'Page 3', 'Practice facts must be text' ) as $needle ) {
		twd_sk_has( $needle, $e );
	}
	$ig = implode( ' ', $p['ignored'] );
	twd_sk_has( 'site.surprise', $ig );
	twd_sk_has( 'not-a-token', $ig );
	twd_sk_has( 'credentials', $ig );
	$r = TWD_SK_Brief::apply( $data, array( 'build_pages' => true ) );
	$all = json_encode( array( TWD_SK_Profile::get(), TWD_SK_Chrome::settings(), TWD_SK_Packs::overrides(), TWD_SK_Facts::get() ) );
	foreach ( array( 'not-an-email', 'javascript:', 'hunter2', 'url(x)' ) as $bad ) {
		twd_sk_hasnt( $bad, $all );
	}
	twd_sk_eq( '#333333', TWD_SK_Packs::overrides()['color-body'] ?? '#333333', 'a valid colour alongside bad ones is still fine' );
	twd_sk_eq( 0, count( $r['pages'] ), 'no valid page, none created' );
} );

twd_sk_test( 'brief: unreadable colours are reported and the style is not saved', function () {
	twd_sk_brief_reset();
	$data = array( 'schema' => TWD_SK_Brief::SCHEMA, 'style' => array( 'tokens' => array( 'color-primary' => '#111111', 'color-on-primary' => '#222222' ) ) );
	$p = TWD_SK_Brief::plan( $data, false );
	twd_sk_true( count( $p['blocking'] ) > 0 );
	$r = TWD_SK_Brief::apply( $data, array() );
	twd_sk_has( 'Style:', implode( ' ', $r['errors'] ) );
	twd_sk_eq( array(), TWD_SK_Packs::overrides() );
} );

twd_sk_test( 'brief: pages are created as placeholder DRAFTS from the outline, only when asked, never twice, with search wording', function () {
	twd_sk_brief_reset();
	twd_stub_add_post( 5, 'page', 'old' );
	$GLOBALS['twd_stub']['posts'][5]->post_title = 'Contact';
	$r = TWD_SK_Brief::apply( twd_sk_brief_example(), array( 'build_pages' => true ) );
	twd_sk_eq( array(), $r['errors'] );
	twd_sk_eq( 2, count( $r['pages'] ), 'About and the topic page; Contact already existed' );
	twd_sk_has( 'already exists', implode( ' ', $r['skipped'] ) );
	foreach ( $r['pages'] as $pg ) {
		twd_sk_eq( 'draft', get_post( $pg['id'] )->post_status );
		twd_sk_true( TWD_SK_Template::uses_template( $pg['id'] ) );
		twd_sk_has( '[PLACEHOLDER', TWD_SK_Store::get_current( $pg['id'] ) );
		twd_sk_true( TWD_SK_Store::get_leftovers( $pg['id'] ) !== array(), 'flagged as unfinished' );
	}
	twd_sk_eq( 'About', $r['pages'][0]['title'] );
	twd_sk_eq( 'Working with anxiety', $r['pages'][1]['title'] );
	twd_sk_eq( 'Keep it gentle.', $r['pages'][1]['notes'] );
	twd_sk_eq( 'About Alex Example', get_post_meta( $r['pages'][0]['id'], '_twd_sk_seo_title', true ), 'search wording saved' );
	twd_sk_eq( 'old', get_post( 5 )->post_content, 'the existing page was not touched' );
	$again = TWD_SK_Brief::apply( twd_sk_brief_example(), array( 'build_pages' => true ) );
	twd_sk_eq( 0, count( $again['pages'] ), 'a second run creates nothing' );
} );

twd_sk_test( 'brief: export gives a brief that round-trips, and holds no key, no password and no HTML', function () {
	twd_sk_brief_reset();
	TWD_SK_Brief::apply( twd_sk_brief_example(), array() );
	$ex = TWD_SK_Brief::export();
	twd_sk_eq( TWD_SK_Brief::SCHEMA, $ex['schema'] );
	$plan = TWD_SK_Brief::plan( json_decode( json_encode( $ex ), true ), false );
	twd_sk_eq( array(), $plan['errors'], 'the export reads back cleanly' );
	foreach ( $plan['items'] as $i ) {
		twd_sk_eq( 'unchanged', $i['action'], $i['label'] . ' is unchanged on a round trip' );
	}
	$json = json_encode( $ex );
	foreach ( array( 'password', 'api_key', 'anthropic', 'sk-ant', '<script' ) as $bad ) {
		twd_sk_hasnt( $bad, $json );
	}
} );

twd_sk_test( 'brief: the interview prompt covers the topics, forbids inventing, and shows the exact format with the real allowed values', function () {
	$t = TWD_SK_Brief::interview_prompt();
	foreach ( array( 'Never invent anything', 'qualifications, registrations and memberships', 'ONE JSON object', 'Never use em dashes', TWD_SK_Brief::SCHEMA, 'bar, centered, split, minimal', 'columns, band, centered, simple', 'about, contact, home, service, faq', 'WHO I AM' ) as $needle ) {
		twd_sk_has( $needle, $t );
	}
	twd_sk_has( implode( ', ', array_keys( TWD_SK_Packs::packs() ) ), $t );
	twd_sk_hasnt( "\xE2\x80\x94", $t );
	twd_sk_hasnt( "\xE2\x80\x93", $t );
} );

twd_sk_test( 'brief rest: administrators only, check changes nothing, apply needs confirm, errors are plain', function () {
	twd_sk_brief_reset();
	$GLOBALS['twd_stub']['user'] = 7;
	$GLOBALS['twd_stub']['caps'] = array( 'edit_pages' );
	twd_sk_eq( 403, twd_sk_status( TWD_SK_REST::can_manage_site( twd_sk_rest_req() ) ), 'an editor cannot' );
	twd_sk_eq( 403, twd_sk_status( TWD_SK_REST::can_read_site( twd_sk_rest_req() ) ) );
	$GLOBALS['twd_stub']['caps'] = array( 'edit_pages', 'manage_options' );
	$json = file_get_contents( ABSPATH . 'docs/site-brief.example.json' );
	$p = TWD_SK_REST::post_site_brief_plan( twd_sk_rest_req( array( 'brief' => $json ) ) );
	twd_sk_true( isset( $p['items'] ) && ! isset( $p['changes'] ), 'the plan hides the cleaned internals' );
	twd_sk_eq( '', TWD_SK_Profile::get()['site_name'], 'checking saved nothing' );
	twd_sk_eq( 400, twd_sk_status( TWD_SK_REST::post_site_brief_apply( twd_sk_rest_req( array( 'brief' => $json ) ) ) ), 'no confirm' );
	twd_sk_eq( '', TWD_SK_Profile::get()['site_name'] );
	twd_sk_eq( 400, twd_sk_status( TWD_SK_REST::post_site_brief_plan( twd_sk_rest_req( array( 'brief' => 'nonsense' ) ) ) ) );
	$a = TWD_SK_REST::post_site_brief_apply( twd_sk_rest_req( array( 'brief' => $json, 'confirm' => true, 'build_pages' => true ) ) );
	twd_sk_eq( 3 - 0, count( $a['pages'] ) );
	twd_sk_eq( 'Example Practice', TWD_SK_Profile::get()['site_name'] );
	twd_sk_true( isset( $a['profile_state']['profile'] ), 'the new state comes back' );
	$g = TWD_SK_REST::get_site_brief( twd_sk_rest_req() );
	twd_sk_eq( TWD_SK_Brief::SCHEMA, $g['schema'] );
	twd_sk_has( 'ONE JSON object', $g['prompt'] );
} );

twd_sk_test( 'brief: fill an existing outline page with AI as a new version, and refuse a page that is not a kit page', function () {
	twd_sk_brief_reset();
	TWD_SK_Facts::save( 'WHO I AM' . "\n" . 'A counsellor.' );
	$r = TWD_SK_Brief::apply( twd_sk_brief_example(), array( 'build_pages' => true ) );
	$id = $r['pages'][0]['id'];
	$before = TWD_SK_Store::get_current_version_id( $id );
	twd_sk_ai_provider( str_replace( '[PLACEHOLDER: Heading here]', 'About Alex', TWD_SK_Recipes::skeleton( 'about' ) ) );
	$f = TWD_SK_AI::generate_page( array( 'page_id' => $id, 'type' => 'about', 'title' => 'About' ) );
	twd_sk_true( ! is_wp_error( $f ), 'ok' );
	twd_sk_eq( $id, $f['id'], 'the same page' );
	twd_sk_eq( $before + 1, TWD_SK_Store::get_current_version_id( $id ), 'a new version, history kept' );
	twd_sk_eq( 'draft', get_post( $id )->post_status );
	twd_stub_add_post( 50, 'page', 'Plain page' );
	twd_sk_is_error( 'twd_sk_bad_input', TWD_SK_AI::generate_page( array( 'page_id' => 50, 'type' => 'about' ) ) );
	twd_sk_is_error( 'twd_sk_bad_input', TWD_SK_AI::generate_page( array( 'page_id' => 9999, 'type' => 'about' ) ) );
	$GLOBALS['twd_stub']['caps'] = array( 'edit_pages' );
	twd_sk_eq( 403, twd_sk_status( TWD_SK_REST::post_generate( twd_sk_rest_req( array( 'page_id' => $id, 'type' => 'about' ) ) ) ), 'a page the user cannot edit' );
} );

twd_sk_test( 'brief: the new classes hold no key and no web call', function () {
	$php = file_get_contents( ABSPATH . 'includes/class-twd-sk-brief.php' );
	foreach ( array( 'wp_remote_', 'curl_', 'api.anthropic.com', 'x-api-key', 'sk-ant' ) as $bad ) {
		twd_sk_hasnt( $bad, $php );
	}
} );
