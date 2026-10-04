<?php
// Practice facts, page recipes, and drafting a new page from them.

function twd_sk_facts_reset() {
	twd_stub_reset();
	TWD_SK_Profile::save( array( 'site_name' => 'Calm Practice', 'person_name' => 'Alex Example', 'email' => 'hello@example.com', 'registration' => array( 'Registered member, wording supplied' ) ) );
}

twd_sk_test( 'facts: saved as plain text with line breaks, tags and long dashes removed, and capped', function () {
	twd_sk_facts_reset();
	twd_sk_eq( '', TWD_SK_Facts::get() );
	$r = TWD_SK_Facts::save( "WHO I AM\n<b>Alex</b> works with men \xE2\x80\x94 and boys.\n\n\n\n<script>x()</script>\nQUESTIONS" );
	twd_sk_true( ! is_wp_error( $r ) );
	twd_sk_eq( "WHO I AM\nAlex works with men, and boys.\n\nx()\nQUESTIONS", TWD_SK_Facts::get() );
	twd_sk_hasnt( '<', TWD_SK_Facts::get() );
	twd_sk_hasnt( "\xE2\x80\x94", TWD_SK_Facts::get() );
	twd_sk_is_error( 'twd_sk_bad_facts', TWD_SK_Facts::save( array( 'x' ) ) );
	twd_sk_is_error( 'twd_sk_bad_facts', TWD_SK_Facts::save( str_repeat( 'a', 20001 ) ) );
	twd_sk_eq( "WHO I AM\nAlex works with men, and boys.\n\nx()\nQUESTIONS", TWD_SK_Facts::get(), 'a refused save changes nothing' );
	twd_sk_true( ! is_wp_error( TWD_SK_Facts::save( str_repeat( 'a', 20000 ) ) ), 'exactly the limit is fine' );
	twd_sk_true( ! is_wp_error( TWD_SK_Facts::save( '' ) ) );
	twd_sk_eq( '', TWD_SK_Facts::get(), 'it can be cleared' );
} );

twd_sk_test( 'facts: the template names the headings and holds no sample facts or long dashes', function () {
	$t = TWD_SK_Facts::template();
	foreach ( array( 'WHO I AM', 'WHO I HELP', 'WHAT I OFFER', 'MY APPROACH', 'QUALIFICATIONS, REGISTRATIONS AND MEMBERSHIPS', 'FEES, SESSIONS AND HOW IT WORKS', 'WHERE AND WHEN', 'QUESTIONS PEOPLE ASK', 'THINGS I NEVER SAY OR PROMISE' ) as $h ) {
		twd_sk_has( $h, $t );
	}
	twd_sk_hasnt( "\xE2\x80\x94", $t );
	twd_sk_hasnt( "\xE2\x80\x93", $t );
	twd_sk_hasnt( '@', $t );
	twd_sk_hasnt( '[PLACEHOLDER', $t );
} );

twd_sk_test( 'facts: the AI block holds the facts and the real site details, never a placeholder, and nothing when empty', function () {
	twd_stub_reset();
	twd_sk_eq( '', TWD_SK_Facts::prompt_block() );
	twd_sk_facts_reset();
	twd_sk_has( 'Practice name: Calm Practice', TWD_SK_Facts::prompt_block() );
	twd_sk_has( 'Registration or membership line (use exactly): Registered member, wording supplied', TWD_SK_Facts::prompt_block() );
	TWD_SK_Facts::save( 'WHO I AM' . "\n" . 'A counsellor.' );
	twd_sk_has( "Practice facts (written by the therapist, the only source for new claims):\nWHO I AM\nA counsellor.", TWD_SK_Facts::prompt_block() );
	TWD_SK_Profile::save( TWD_SK_Profile::starter() );
	twd_sk_hasnt( 'PLACEHOLDER', TWD_SK_Facts::prompt_block(), 'a starter profile adds no placeholders' );
} );

twd_sk_test( 'facts: never printed to visitors, in the header, footer, structured data or search copy', function () {
	twd_sk_facts_reset();
	TWD_SK_Facts::save( 'SECRET-FACT-MARKER the therapist wrote this for the AI only' );
	foreach ( array( TWD_SK_Chrome::render_header(), TWD_SK_Chrome::render_footer(), TWD_SK_Chrome::render_minimal_header(), TWD_SK_Chrome::render_minimal_footer(), json_encode( TWD_SK_Schema::nodes() ), TWD_SK_Mirror::text( '<p>page</p>' ) ) as $out ) {
		twd_sk_hasnt( 'SECRET-FACT-MARKER', $out );
	}
	$page = TWD_SK_Page::render_html( 12 );
	twd_sk_hasnt( 'SECRET-FACT-MARKER', (string) $page );
} );

twd_sk_test( 'recipes: five page types, each outline passes the sanitiser unchanged, has one h1, and starts with placeholders', function () {
	$types = TWD_SK_Recipes::types();
	twd_sk_eq( array( 'about', 'contact', 'home', 'service', 'faq' ), array_keys( $types ) );
	foreach ( $types as $key => $r ) {
		$html = TWD_SK_Recipes::skeleton( $key );
		$res  = TWD_SK_Sanitizer::clean_with_report( $html );
		twd_sk_eq( 0, $res['report']['total'], $key . ': nothing removed or changed' );
		twd_sk_eq( 1, substr_count( $html, '<h1' ), $key . ': one h1' );
		twd_sk_has( '[PLACEHOLDER', $html );
		twd_sk_true( strlen( $r['intent'] ) > 40, $key . ' says what it is for' );
		twd_sk_hasnt( "\xE2\x80\x94", $r['intent'] . $r['label'] );
	}
	twd_sk_eq( TWD_SK_Starters::html( 'about' ), TWD_SK_Recipes::skeleton( 'about' ), 'the about recipe is the about starter' );
	twd_sk_true( $types['service']['needs_topic'] && ! $types['about']['needs_topic'] );
} );

twd_sk_test( 'recipes: inputs are cleaned, a topic page needs a topic, the title defaults sensibly', function () {
	$r = TWD_SK_Recipes::inputs( array( 'type' => 'about' ) );
	twd_sk_eq( 'About', $r['title'] );
	$r = TWD_SK_Recipes::inputs( array( 'type' => 'service', 'topic' => '<b>Working with anxiety</b>', 'notes' => "Mention <i>CBT</i> \xE2\x80\x94 gently" ) );
	twd_sk_eq( 'Working with anxiety', $r['topic'] );
	twd_sk_eq( 'Working with anxiety', $r['title'] );
	twd_sk_hasnt( '<', $r['notes'] );
	twd_sk_hasnt( "\xE2\x80\x94", $r['notes'] );
	twd_sk_is_error( 'twd_sk_bad_input', TWD_SK_Recipes::inputs( array( 'type' => 'service' ) ) );
	twd_sk_is_error( 'twd_sk_bad_input', TWD_SK_Recipes::inputs( array( 'type' => 'nope' ) ) );
	twd_sk_is_error( 'twd_sk_bad_input', TWD_SK_Recipes::inputs( array() ) );
	twd_sk_eq( 'My own title', TWD_SK_Recipes::inputs( array( 'type' => 'about', 'title' => 'My own title' ) )['title'] );
} );

twd_sk_test( 'new page by AI: a draft with the kit template, saved as version 1, nothing published, placeholders flagged', function () {
	twd_sk_facts_reset();
	TWD_SK_Facts::save( 'WHO I AM' . "\n" . 'A counsellor in Testville.' );
	$answer = TWD_SK_Starters::html( 'about' );
	$answer = str_replace( '[PLACEHOLDER: Heading here]', 'About Alex', $answer );
	twd_sk_ai_provider( "```html\n" . $answer . "```" );
	$r = TWD_SK_AI::generate_page( array( 'type' => 'about' ) );
	twd_sk_true( ! is_wp_error( $r ), 'ok' );
	twd_sk_eq( 'About', $r['title'] );
	twd_sk_eq( 'draft', get_post( $r['id'] )->post_status, 'a draft' );
	twd_sk_true( TWD_SK_Template::uses_template( $r['id'] ), 'the kit template' );
	twd_sk_true( TWD_SK_Page::is_kit_page( $r['id'] ) );
	twd_sk_true( $r['sections'] >= 5 );
	twd_sk_true( $r['must_count'] > 0, 'placeholders left are flagged and block publishing' );
	twd_sk_eq( 1, TWD_SK_Store::get_current_version_id( $r['id'] ) );
	twd_sk_has( 'AI draft from the practice facts (about)', json_encode( TWD_SK_Store::list_versions( $r['id'] ) ) );
	$call = $GLOBALS['twd_stub']['ai_calls'][0];
	twd_sk_has( 'A counsellor in Testville.', $call['message'] );
	twd_sk_has( 'Practice name: Calm Practice', $call['message'] );
	twd_sk_has( 'twd-sk-hero--compact', $call['message'], 'the outline is sent' );
	twd_sk_has( 'keep a [PLACEHOLDER: what is needed]', $call['system'] );
	twd_sk_hasnt( "\xE2\x80\x94", $call['system'] . $call['message'] );
	twd_sk_eq( TWD_SK_AI::TOKENS_PAGE, $call['tokens'] );
} );

twd_sk_test( 'new page by AI: with no facts it says so and never invents; a topic page uses the topic', function () {
	twd_sk_facts_reset();
	twd_sk_ai_provider( TWD_SK_Recipes::skeleton( 'service' ) );
	$r = TWD_SK_AI::generate_page( array( 'type' => 'service', 'topic' => 'Working with anxiety', 'notes' => 'Keep it gentle' ) );
	twd_sk_true( ! is_wp_error( $r ) );
	twd_sk_eq( 'Working with anxiety', $r['title'] );
	$m = $GLOBALS['twd_stub']['ai_calls'][0]['message'];
	twd_sk_has( 'No practice facts have been saved yet. Do not invent any.', $m );
	twd_sk_has( 'Topic: Working with anxiety', $m );
	twd_sk_has( 'Keep it gentle', $m );
} );

twd_sk_test( 'new page by AI: no page is created if the AI fails, says nothing, or returns no sections', function () {
	twd_sk_facts_reset();
	$before = count( $GLOBALS['twd_stub']['posts'] );
	twd_sk_ai_provider( new WP_Error( 'twd_ap_ai_bad_key', 'Rejected.' ) );
	twd_sk_is_error( 'twd_ap_ai_bad_key', TWD_SK_AI::generate_page( array( 'type' => 'about' ) ) );
	twd_sk_ai_provider( null );
	twd_sk_is_error( 'twd_sk_ai_unavailable', TWD_SK_AI::generate_page( array( 'type' => 'about' ) ) );
	twd_sk_ai_provider( 'I am sorry, I cannot do that.' );
	twd_sk_is_error( 'twd_sk_ai_bad_result', TWD_SK_AI::generate_page( array( 'type' => 'about' ) ) );
	twd_sk_ai_provider( 'x', false );
	twd_sk_is_error( 'twd_sk_ai_unavailable', TWD_SK_AI::generate_page( array( 'type' => 'about' ) ) );
	twd_sk_ai_provider( 'x' );
	twd_sk_is_error( 'twd_sk_bad_input', TWD_SK_AI::generate_page( array( 'type' => 'service' ) ) );
	twd_sk_eq( $before, count( $GLOBALS['twd_stub']['posts'] ), 'no page was created by any failure' );
} );

twd_sk_test( 'practice facts reach every AI request: a remix gets them too', function () {
	twd_sk_ai_page();
	TWD_SK_Facts::save( 'WHO I HELP' . "\n" . 'Men and boys.' );
	$parts = TWD_SK_Sections::split( TWD_SK_Store::get_current( 40 ) );
	twd_sk_ai_provider( $parts['sections'][0] );
	TWD_SK_AI::remix( 40, array( 'mode' => 'sections', 'indexes' => array( 0 ), 'instruction' => 'Update this from my practice facts.' ) );
	twd_sk_has( 'Men and boys.', $GLOBALS['twd_stub']['ai_calls'][0]['message'] );
} );

twd_sk_test( 'external prompt: the practice facts go in only for an administrator', function () {
	twd_sk_facts_reset();
	TWD_SK_Facts::save( 'WHO I AM' . "\n" . 'FACT-FOR-ADMINS' );
	$GLOBALS['twd_stub']['caps'] = array( 'edit_pages', 'edit_post:12' );
	$GLOBALS['twd_stub']['user'] = 7;
	twd_stub_add_post( 12, 'page', '[twd_page]' );
	$p = TWD_SK_REST::get_prompt( twd_sk_rest_req() );
	twd_sk_hasnt( 'FACT-FOR-ADMINS', $p['prompt'], 'an editor does not get them' );
	$q = TWD_SK_REST::post_recipe_prompt( twd_sk_rest_req( array( 'type' => 'about' ) ) );
	twd_sk_hasnt( 'FACT-FOR-ADMINS', $q['prompt'] );
	twd_sk_has( 'The therapist will give you the practice facts in the chat', $q['prompt'] );
	$GLOBALS['twd_stub']['caps'] = array( 'edit_pages', 'edit_post:12', 'manage_options' );
	$p = TWD_SK_REST::get_prompt( twd_sk_rest_req() );
	twd_sk_has( 'FACT-FOR-ADMINS', $p['prompt'], 'an administrator does' );
	$q = TWD_SK_REST::post_recipe_prompt( twd_sk_rest_req( array( 'type' => 'about' ) ) );
	twd_sk_has( 'FACT-FOR-ADMINS', $q['prompt'] );
	twd_sk_has( 'Page title: About', $q['prompt'] );
	twd_sk_eq( 400, twd_sk_status( TWD_SK_REST::post_recipe_prompt( twd_sk_rest_req( array( 'type' => 'x' ) ) ) ) );
} );

twd_sk_test( 'facts rest: administrators only, saved and read back, refused text is a 400, and the new routes are guarded', function () {
	twd_sk_facts_reset();
	$GLOBALS['twd_stub']['caps'] = array( 'edit_pages', 'manage_options' );
	$GLOBALS['twd_stub']['user'] = 7;
	$r = TWD_SK_REST::post_site_facts( twd_sk_rest_req( array( 'text' => "WHO I AM\nHello" ) ) );
	twd_sk_eq( "WHO I AM\nHello", $r['text'] );
	twd_sk_eq( $r['text'], TWD_SK_REST::get_site_facts( twd_sk_rest_req() )['text'] );
	twd_sk_has( 'WHO I AM', $r['template'] );
	twd_sk_eq( 20000, $r['max'] );
	twd_sk_eq( 400, twd_sk_status( TWD_SK_REST::post_site_facts( twd_sk_rest_req( array( 'text' => str_repeat( 'a', 20001 ) ) ) ) ) );
	$GLOBALS['twd_stub']['caps'] = array( 'edit_pages' );
	twd_sk_eq( 403, twd_sk_status( TWD_SK_REST::can_manage_site( twd_sk_rest_req() ) ), 'an editor cannot write them' );
	twd_sk_eq( 403, twd_sk_status( TWD_SK_REST::can_read_site( twd_sk_rest_req() ) ), 'or read them' );
	// The generate route needs edit_pages, a nonce, and passes the AI and create limits.
	$GLOBALS['twd_stub']['caps'] = array();
	twd_sk_eq( 403, twd_sk_status( TWD_SK_REST::can_generate_page( twd_sk_rest_req() ) ) );
	$GLOBALS['twd_stub']['caps'] = array( 'edit_pages' );
	twd_sk_eq( 401, twd_sk_status( TWD_SK_REST::can_generate_page( twd_sk_rest_req( array(), null ) ) ) );
	twd_sk_eq( true, TWD_SK_REST::can_generate_page( twd_sk_rest_req() ) );
} );

twd_sk_test( 'facts: nothing in the new classes calls out to the web, and no long dashes appear in the sources', function () {
	foreach ( array( 'class-twd-sk-facts.php', 'class-twd-sk-recipes.php' ) as $file ) {
		$php = file_get_contents( ABSPATH . 'includes/' . $file );
		foreach ( array( 'wp_remote_', 'curl_', 'api.anthropic.com', 'x-api-key', 'sk-ant' ) as $bad ) {
			twd_sk_hasnt( $bad, $php, $file );
		}
	}
} );

twd_sk_test( 'facts js: the editor sends only through its api, offers the outline, warns when none are saved, and holds no addresses or injection', function () {
	$main = file_get_contents( ABSPATH . 'assets/twd-site-kit-editor.js' );
	$site = file_get_contents( ABSPATH . 'assets/twd-site-kit-editor-site.js' );
	twd_sk_has( "api('POST', '/pages/generate', body)", $main );
	twd_sk_has( "api('POST', '/pages/recipe-prompt', body)", $main );
	twd_sk_has( 'No practice facts are saved yet', $main );
	twd_sk_has( 'Create a draft with AI', $main );
	twd_sk_has( 'Update from my practice facts', $main );
	twd_sk_has( "api('GET', '/site/facts')", $site );
	twd_sk_has( "api('POST', '/site/facts'", $site );
	twd_sk_has( 'Start from the outline', $site );
	twd_sk_has( 'Only administrators can see it. It is never shown on the site.', $site );
	foreach ( array( $main, $site ) as $js ) {
		twd_sk_hasnt( 'innerHTML', $js );
		twd_sk_hasnt( 'eval(', $js );
		twd_sk_true( 1 !== preg_match( '#https?://#i', $js ), 'no web addresses' );
		twd_sk_true( 1 !== preg_match( '/(^|[^.\w])(alert|confirm|prompt)\s*\(/m', $js ), 'no native dialogs' );
	}
} );

twd_sk_test( 'facts: the editor is told whether facts are saved (never their text), what types exist, and whether the user can manage', function () {
	twd_sk_ed_setup( array( 'edit_pages', 'edit_post:12', 'manage_options' ) );
	$GLOBALS['twd_stub']['hooks'] = array();
	TWD_SK_Facts::save( 'SECRET-FACT-MARKER' );
	twd_sk_true( twd_sk_ed_loaded() );
	$d = $GLOBALS['twd_stub']['scripts']['twd-site-kit-editor']['data'];
	twd_sk_eq( true, $d['factsSaved'] );
	twd_sk_eq( true, $d['canManage'] );
	twd_sk_eq( array( 'about', 'contact', 'home', 'service', 'faq' ), array_keys( $d['recipes'] ) );
	twd_sk_hasnt( 'SECRET-FACT-MARKER', json_encode( $d ), 'the text is never handed to the browser through the page' );
	twd_sk_ed_setup( array( 'edit_pages', 'edit_post:12' ) );
	twd_sk_ed_loaded();
	twd_sk_eq( false, $GLOBALS['twd_stub']['scripts']['twd-site-kit-editor']['data']['canManage'] );
} );
