<?php
// Therapy or business mode (0.7.0): the setting, the word lists, and every place the words reach.

function twd_sk_mode_texts() {
	$parts = TWD_SK_Sections::split( '<section class="twd-sk-text"><p>x</p></section>' );
	$out   = array(
		'chat prompt'     => TWD_SK_Prompt::build( '', 'FACTS' ),
		'ai sections'     => TWD_SK_AI::system_prompt( 'sections' ),
		'ai user message' => TWD_SK_AI::user_message( 'sections', $parts, array( 0 ), 'do it', 'extra' ),
		'page system'     => TWD_SK_AI::page_system_prompt(),
		'facts block'     => TWD_SK_Facts::prompt_block(),
		'facts outline'   => TWD_SK_Facts::template(),
		'interview'       => TWD_SK_Brief::interview_prompt(),
		'header proposal' => TWD_SK_Proposals::system_prompt( 'header' ),
	);
	foreach ( array_keys( TWD_SK_Recipes::types() ) as $t ) {
		$in                       = TWD_SK_Recipes::inputs( array( 'type' => $t, 'topic' => 'Topic' ) );
		$out[ 'page request ' . $t ] = TWD_SK_AI::page_prompt( $in, false );
	}
	return $out;
}

twd_sk_test( 'mode: therapy is the default, business can be set, anything else is refused', function () {
	twd_sk_eq( 'therapy', TWD_SK_Mode::get() );
	twd_sk_eq( 'business', TWD_SK_Mode::set( 'business' ) );
	twd_sk_eq( 'business', TWD_SK_Mode::get() );
	twd_sk_true( TWD_SK_Mode::is( 'business' ) );
	twd_sk_is_error( 'twd_sk_bad_mode', TWD_SK_Mode::set( 'shop' ) );
	twd_sk_is_error( 'twd_sk_bad_mode', TWD_SK_Mode::set( array( 'business' ) ) );
	twd_sk_eq( 'business', TWD_SK_Mode::get(), 'a refused value changes nothing' );
	update_option( 'twd_sk_mode', 'nonsense' );
	twd_sk_eq( 'therapy', TWD_SK_Mode::get(), 'a stored value that is not a mode reads as therapy' );
} );

twd_sk_test( 'mode: safe mode always reads as therapy, and a database fault falls back to therapy', function () {
	TWD_SK_Mode::set( 'business' );
	TWD_SK_Safe::set( true );
	twd_sk_eq( 'therapy', TWD_SK_Mode::get() );
	twd_sk_eq( 'business', TWD_SK_Mode::stored(), 'the saved setting is kept' );
	TWD_SK_Safe::set( false );
	twd_sk_eq( 'business', TWD_SK_Mode::get() );
	$GLOBALS['twd_stub']['option_throws'] = true;
	twd_sk_eq( 'therapy', TWD_SK_Mode::get() );
	$GLOBALS['twd_stub']['option_throws'] = false;
} );

twd_sk_test( 'mode words: every mode has exactly the same keys, with the same kinds of value, and no long dashes', function () {
	$therapy = TWD_SK_Mode::words( 'therapy' );
	$biz     = TWD_SK_Mode::words( 'business' );
	twd_sk_eq( array_keys( $therapy ), array_keys( $biz ) );
	foreach ( $therapy as $k => $v ) {
		twd_sk_eq( gettype( $v ), gettype( $biz[ $k ] ), 'type of ' . $k );
	}
	$all = json_encode( array( $therapy, $biz ), JSON_UNESCAPED_UNICODE );
	twd_sk_hasnt( "\xE2\x80\x94", $all );
	twd_sk_hasnt( "\xE2\x80\x93", $all );
	twd_sk_eq( '', TWD_SK_Mode::w( 'no-such-key' ) );
} );

twd_sk_test( 'mode words: therapy keeps the wording used before modes existed', function () {
	twd_sk_eq( 'WHO I AM, WHO I HELP, WHAT I OFFER, MY APPROACH, QUALIFICATIONS, REGISTRATIONS AND MEMBERSHIPS, FEES, SESSIONS AND HOW IT WORKS, WHERE AND WHEN, HOW TO CONTACT ME, QUESTIONS PEOPLE ASK, WORDS AND PHRASES I LIKE, THINGS I NEVER SAY OR PROMISE', implode( ', ', TWD_SK_Mode::facts_headings( 'therapy' ) ) );
	$t = twd_sk_mode_texts();
	twd_sk_has( 'You are helping a therapist edit one page of their website.', $t['chat prompt'] );
	twd_sk_has( 'Keep client confidentiality. Use composites only.', $t['chat prompt'] );
	twd_sk_has( 'Stay within the advertising guidance of the therapist\'s professional body.', $t['chat prompt'] );
	twd_sk_has( 'Keep the therapist\'s own voice.', $t['chat prompt'] );
	twd_sk_has( '## Facts about the therapist', $t['chat prompt'] );
	twd_sk_has( 'You edit part of one page of a therapist\'s website.', $t['ai sections'] );
	twd_sk_has( 'Never invent a qualification, registration, fee, policy, contact detail, testimonial or claim.', $t['ai sections'] );
	twd_sk_has( 'You write one new page for a therapist\'s website.', $t['page system'] );
	twd_sk_has( 'Never promise outcomes, never make clinical or health claims, never diagnose.', $t['page request service'] );
	TWD_SK_Facts::save( 'Some facts.' );
	twd_sk_has( 'The therapist will give you the practice facts in the chat.', TWD_SK_AI::page_prompt( TWD_SK_Recipes::inputs( array( 'type' => 'about' ) ), false ) );
	twd_sk_has( 'build a therapist\'s website', $t['interview'] );
	twd_sk_has( 'Nothing about the people they treat.', $t['interview'] );
	twd_sk_eq( 'Service or topic page (for example Working with anxiety)', TWD_SK_Recipes::labels()['service'] );
} );

twd_sk_test( 'mode words: business mode never says therapist, client or practice facts to the AI or the person', function () {
	TWD_SK_Mode::set( 'business' );
	TWD_SK_Facts::save( 'WHO WE ARE' . "\n" . 'A small hire company.' );
	TWD_SK_Profile::save( array( 'site_name' => 'Example Hire', 'person_name' => 'Alex Example', 'area_served' => 'The county' ) );
	// The component examples and page outlines hold sample wording that the publish check flags as
	// leftovers; they are checked separately. This test is about the instructions.
	$samples = array( TWD_SK_Prompt::style_guide() );
	foreach ( array_keys( TWD_SK_Recipes::types() ) as $t ) {
		$samples[] = TWD_SK_Recipes::skeleton( $t );
	}
	foreach ( twd_sk_mode_texts() as $name => $text ) {
		$text = str_replace( $samples, '', $text );
		$text = str_replace( '- mode: therapy, business.', '', $text );
		foreach ( array( 'therapist', 'therapy', 'practice facts', 'client', 'first session', 'counsell', 'psychother' ) as $bad ) {
			twd_sk_true( false === stripos( $text, $bad ), $name . ' must not say "' . $bad . '"' );
		}
	}
	$t = twd_sk_mode_texts();
	twd_sk_has( 'You are helping a business owner edit one page of their website.', $t['chat prompt'] );
	twd_sk_has( 'Respect customer privacy.', $t['chat prompt'] );
	twd_sk_has( 'Business facts (written by the business owner', $t['facts block'] );
	twd_sk_has( 'Business name: Example Hire', $t['facts block'] );
	twd_sk_has( 'Area covered: The county', $t['facts block'] );
	twd_sk_has( 'WHO WE ARE', $t['facts outline'] );
	twd_sk_has( 'PRICES AND BOOKING', $t['interview'] );
	twd_sk_has( 'build a small business website', $t['interview'] );
	twd_sk_eq( 'Product or service page (for example Weekend hire)', TWD_SK_Recipes::labels()['service'] );
	twd_sk_is_error( 'twd_sk_bad_input', TWD_SK_Recipes::inputs( array( 'type' => 'service' ) ) );
	twd_sk_has( 'Weekend hire', TWD_SK_Recipes::inputs( array( 'type' => 'service' ) )->get_error_message() );
} );

twd_sk_test( 'mode: only the mode file holds therapist wording in strings (the schema id aside)', function () {
	foreach ( glob( ABSPATH . 'includes/*.php' ) as $file ) {
		if ( 'class-twd-sk-mode.php' === basename( $file ) ) {
			continue;
		}
		foreach ( token_get_all( file_get_contents( $file ) ) as $tok ) {
			if ( is_array( $tok ) && T_CONSTANT_ENCAPSED_STRING === $tok[0] && "'#therapist'" !== $tok[1] ) {
				twd_sk_true( 1 !== preg_match( '/therapist|counsell|psychother|practice facts/i', $tok[1] ), basename( $file ) . ' hard-codes ' . substr( $tok[1], 0, 60 ) );
			}
		}
	}
} );

twd_sk_test( 'mode schema: therapy is ProfessionalService with the person, business is LocalBusiness on its own', function () {
	$p = array_merge( TWD_SK_Profile::get(), array( 'site_name' => 'Example', 'person_name' => 'Alex Example', 'person_job' => 'Owner' ) );
	$t = TWD_SK_Schema::nodes( $p );
	twd_sk_eq( 'ProfessionalService', $t[0]['@type'] );
	twd_sk_eq( 'Person', $t[1]['@type'] );
	TWD_SK_Mode::set( 'business' );
	$b = TWD_SK_Schema::nodes( $p );
	twd_sk_eq( 1, count( $b ) );
	twd_sk_eq( 'LocalBusiness', $b[0]['@type'] );
	twd_sk_eq( 'Example', $b[0]['name'] );
} );

twd_sk_test( 'mode profile: a fresh profile uses the mode words for its placeholders', function () {
	twd_sk_eq( '[PLACEHOLDER: practice name]', TWD_SK_Profile::starter()['site_name'] );
	TWD_SK_Mode::set( 'business' );
	$s = TWD_SK_Profile::starter();
	twd_sk_eq( '[PLACEHOLDER: business name]', $s['site_name'] );
	twd_sk_has( 'owner or contact name', $s['person_name'] );
	twd_sk_true( 0 === strpos( $s['footer_text'], '[PLACEHOLDER' ), 'still a placeholder the check flags' );
} );

twd_sk_test( 'mode brief: a brief can set the site type, a bad one is reported, export includes it, labels follow it', function () {
	$plan = TWD_SK_Brief::plan( array( 'schema' => 'twd-site-brief/1', 'mode' => 'business', 'site' => array( 'person_name' => 'Alex Example' ), 'facts' => 'WHO WE ARE' ), false );
	twd_sk_eq( 'business', $plan['changes']['mode'] );
	$labels = array_map( function ( $i ) {
		return $i['label'];
	}, $plan['items'] );
	twd_sk_true( in_array( 'Site type', $labels, true ) );
	twd_sk_true( in_array( 'Owner or contact name', $labels, true ), 'labels use the new mode' );
	twd_sk_true( in_array( 'Business facts', $labels, true ) );
	twd_sk_eq( 'therapy', TWD_SK_Mode::get(), 'planning changes nothing' );
	$r = TWD_SK_Brief::apply( array( 'schema' => 'twd-site-brief/1', 'mode' => 'business' ), array() );
	twd_sk_has( 'Site type is now Business.', implode( ' ', $r['applied'] ) );
	twd_sk_eq( 'business', TWD_SK_Mode::get() );
	twd_sk_eq( 'business', TWD_SK_Brief::export()['mode'] );
	$bad = TWD_SK_Brief::plan( array( 'schema' => 'twd-site-brief/1', 'mode' => 'shop' ), false );
	twd_sk_has( 'Site type: choose one of therapy, business', implode( ' ', $bad['errors'] ) );
	twd_sk_eq( null, $bad['changes']['mode'] );
	twd_sk_has( '"mode": "business"', TWD_SK_Brief::schema_text() );
} );

twd_sk_test( 'mode rest: an administrator reads and sets the site type; a bad one is refused; an editor cannot', function () {
	twd_sk_site_caps();
	twd_sk_eq( 'therapy', TWD_SK_REST::get_site_mode( twd_sk_rest_req() )['mode'] );
	$out = TWD_SK_REST::post_site_mode( twd_sk_rest_req( array( 'mode' => 'business' ) ) );
	twd_sk_eq( 'business', $out['mode'] );
	twd_sk_eq( 'business owner', $out['words']['person'] );
	twd_sk_eq( false, $out['words']['show_person_job'] );
	$e = TWD_SK_REST::post_site_mode( twd_sk_rest_req( array( 'mode' => 'shop' ) ) );
	twd_sk_is_error( 'twd_sk_bad_input', $e );
	twd_sk_eq( 400, $e->get_error_data()['status'] );
	twd_sk_site_caps( false );
	foreach ( twd_sk_site_routes() as $r ) {
		if ( '/site/mode' === $r['route'] ) {
			twd_sk_is_error( 'twd_sk_forbidden', call_user_func( $r['args']['permission_callback'], twd_sk_rest_req() ) );
		}
	}
} );

twd_sk_test( 'mode cli: shows the site type, switches it, refuses a bad one, and says when safe mode overrides it', function () {
	require_once __DIR__ . '/stub-wpcli.php';
	WP_CLI::reset();
	$cli = new TWD_SK_CLI();
	$cli->mode( array(), array() );
	twd_sk_has( 'Site type: therapy.', WP_CLI::all() );
	WP_CLI::reset();
	$cli->mode( array( 'Business' ), array() );
	twd_sk_has( 'Site type is now business.', WP_CLI::all() );
	twd_sk_eq( 'business', TWD_SK_Mode::get() );
	twd_sk_has( 'Choose one of', twd_sk_cli_fails( function () use ( $cli ) {
		$cli->mode( array( 'shop' ), array() );
	} ) );
	TWD_SK_Safe::set( true );
	WP_CLI::reset();
	$cli->mode( array(), array() );
	twd_sk_has( 'Safe mode is on, so therapy wording is used', WP_CLI::all() );
	TWD_SK_Safe::set( false );
} );

twd_sk_test( 'mode filters: a third mode can be added as data, starting from business words; therapy cannot be removed', function () {
	add_filter( 'twd_sk_modes', function ( $modes ) {
		$modes['coach']   = array( 'label' => 'Coaching', 'help' => 'For coaches.' );
		$modes['Bad One'] = array( 'label' => 'Bad' );
		return $modes;
	} );
	add_filter( 'twd_sk_mode_words', function ( $words, $mode ) {
		return 'coach' === $mode ? array( 'person' => 'coach', 'schema_person' => 'not a bool', 'unknown' => 'x' ) : $words;
	} );
	twd_sk_true( isset( TWD_SK_Mode::modes()['coach'] ) );
	twd_sk_true( ! isset( TWD_SK_Mode::modes()['Bad One'] ), 'a bad slug is dropped' );
	TWD_SK_Mode::set( 'coach' );
	twd_sk_eq( 'coach', TWD_SK_Mode::w( 'person' ) );
	twd_sk_eq( 'customers', TWD_SK_Mode::w( 'people' ), 'unset keys come from business' );
	twd_sk_eq( false, TWD_SK_Mode::w( 'schema_person' ), 'a value of the wrong type is ignored' );
	twd_sk_eq( '', TWD_SK_Mode::w( 'unknown' ) );
	$GLOBALS['twd_stub']['hooks'] = array();
	add_filter( 'twd_sk_modes', function () {
		return array( 'business' => array( 'label' => 'Business' ) );
	} );
	twd_sk_true( isset( TWD_SK_Mode::modes()['therapy'] ), 'therapy always stays' );
} );

twd_sk_test( 'mode editor: the words reach the browser, the Site tab offers the switch, the doctor shows it', function () {
	$js = TWD_SK_Mode::js_words();
	foreach ( array( 'mode', 'modes', 'person', 'facts', 'Facts', 'show_person_job', 'topic_example', 'facts_help' ) as $k ) {
		twd_sk_true( array_key_exists( $k, $js ), 'js word ' . $k );
	}
	$site = file_get_contents( ABSPATH . 'assets/twd-site-kit-editor-site.js' );
	twd_sk_has( "api('POST', '/site/mode', { mode: chosen })", $site );
	twd_sk_has( 'Pages already written keep their wording.', $site );
	twd_sk_has( 'show(pui.personJobField, WORDS.show_person_job !== false)', $site );
	$php = file_get_contents( ABSPATH . 'includes/class-twd-sk-editor.php' );
	twd_sk_has( "'words'          => TWD_SK_Mode::js_words(),", $php );
	TWD_SK_Mode::set( 'business' );
	twd_sk_has( 'Site type: business', TWD_SK_Doctor::report() );
	twd_sk_has( 'Business facts: empty', TWD_SK_Doctor::report() );
} );
