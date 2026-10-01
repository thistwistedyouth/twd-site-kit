<?php
// The client AI prompt and its WP-CLI command.

require_once __DIR__ . '/stub-wpcli.php';
require_once dirname( __DIR__ ) . '/includes/class-twd-sk-cli.php';

twd_sk_test( 'prompt: every registry class, component, variant and shared class appears in the style guide', function () {
	$prompt = TWD_SK_Prompt::build( '' );
	foreach ( array_keys( TWD_SK_Registry::allowed_classes() ) as $class ) {
		if ( 'twd-sk-gallery-label' === $class ) {
			continue; // gallery file only, deliberately left out of the prompt
		}
		twd_sk_true( false !== strpos( $prompt, $class ), 'class missing from the prompt: ' . $class );
	}
	foreach ( TWD_SK_Registry::all() as $id => $c ) {
		twd_sk_has( '(' . $id . ')', $prompt );
		foreach ( $c['variants'] as $class => $v ) {
			twd_sk_has( 'Variant ' . $class, $prompt );
		}
	}
} );

twd_sk_test( 'prompt: the current page HTML is included in full, in a code block', function () {
	$html   = '<section class="twd-sk-text twd-sk-tone-surface"><div class="twd-sk-inner"><p>Hello & welcome</p></div></section>';
	$prompt = TWD_SK_Prompt::build( $html );
	twd_sk_has( "```html\n" . $html . "\n```", $prompt );
} );

twd_sk_test( 'prompt: an empty page says so and has no code block', function () {
	$prompt = TWD_SK_Prompt::build( '' );
	twd_sk_has( 'The page is empty so far.', $prompt );
	twd_sk_hasnt( '```html', $prompt );
} );

twd_sk_test( 'prompt: every fixed rule the owner asked for is present', function () {
	$prompt = TWD_SK_Prompt::build( '' );
	foreach ( array(
		'only if the request is unclear or would remove content',
		'say in one line what changed',
		'one change at a time',
		'full page HTML in one code block',
		'exactly one h1, in the first hero',
		'headings stay in order',
		'No scripts, no inline styles, no forms and no iframes',
		'tell them to ask their web designer',
		'keep every existing image address exactly as it is',
		'[PLACEHOLDER: image needed]',
		'Never invent a file name',
		'descriptive alt text',
		'empty alt text only for a purely decorative image',
		'Link text must be meaningful',
		'Never invent credentials, registration numbers, fees, testimonials or contact details',
		'[PLACEHOLDER]',
		'Never promise outcomes and never make health claims',
		"advertising guidance of the therapist's professional body",
		'client confidentiality',
		'composites only',
		'Keep any safety notice',
		'Never change the helpline numbers or their wording',
		"therapist's own voice",
		'Do not use em dashes',
	) as $needle ) {
		twd_sk_has( $needle, $prompt );
	}
	twd_sk_hasnt( 'added separately in the page builder', $prompt );
	twd_sk_hasnt( 'Ask before changing anything', $prompt );
} );

twd_sk_test( 'prompt: the gallery label class is not offered, and a variant identical to its default is not printed twice', function () {
	$prompt = TWD_SK_Prompt::build( '' );
	twd_sk_hasnt( 'twd-sk-gallery-label', $prompt );
	$seen = 0;
	foreach ( TWD_SK_Registry::all() as $c ) {
		twd_sk_eq( 1, substr_count( $prompt, "Example:\n" . $c['skeleton'] . "\n" ), $c['id'] . ' default example printed once' );
		foreach ( $c['variants'] as $class => $v ) {
			if ( $v['skeleton'] === $c['skeleton'] ) {
				$seen++;
				twd_sk_has( 'Variant ' . $class . ' (' . $v['label'] . '): same as the example above.', $prompt );
				twd_sk_eq( 1, substr_count( $prompt, $v['skeleton'] ), $class . ' skeleton appears once' );
			}
		}
	}
	twd_sk_true( $seen >= 1, 'the registry has variants that equal their default' );
} );

twd_sk_test( 'prompt: the text holds no em or en dashes, and its placeholder survives the sanitiser', function () {
	$prompt = TWD_SK_Prompt::build( '' );
	twd_sk_true( false === strpos( $prompt, "\xE2\x80\x94" ) && false === strpos( $prompt, "\xE2\x80\x93" ) );
	$clean = TWD_SK_Sanitizer::clean( '<p>Fee: [PLACEHOLDER] and [PLACEHOLDER: registration number]</p>' );
	twd_sk_eq( '<p>Fee: [PLACEHOLDER] and [PLACEHOLDER: registration number]</p>', $clean );
} );

twd_sk_test( 'prompt: the examples in the style guide are themselves clean under the sanitiser', function () {
	foreach ( TWD_SK_Registry::style_guide_data()['components'] as $c ) {
		twd_sk_eq( $c['skeleton'], TWD_SK_Sanitizer::clean( $c['skeleton'] ), $c['id'] );
	}
} );

twd_sk_test( 'cli: prompt prints the rules, the guide and the stored page HTML', function () {
	WP_CLI::reset();
	twd_stub_add_post( 12, 'page', '[twd_page]' );
	TWD_SK_Store::save( 12, '<p>Stored words here</p>' );
	( new TWD_SK_CLI() )->prompt( array( '12' ), array() );
	$all = WP_CLI::all();
	twd_sk_has( '## Rules', $all );
	twd_sk_has( 'twd-sk-hero__title', $all );
	twd_sk_has( '<p>Stored words here</p>', $all );
} );

twd_sk_test( 'cli: prompt needs a numeric page id', function () {
	WP_CLI::reset();
	$msg = twd_sk_cli_fails( function () {
		( new TWD_SK_CLI() )->prompt( array( 'abc' ), array() );
	} );
	twd_sk_has( 'page ID must be a number', $msg );
} );
