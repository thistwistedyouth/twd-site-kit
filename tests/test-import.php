<?php
// Section 23 of the owner's plan (29 Oct 2026, session 8): Import a page (a whole AI reply pasted as it came, into a
// section, a page or a new draft page), the safer remix loop (unknown tokens named with the nearest real one, class
// collisions, in-page ids kept inside a custom section, staging addresses, unlabelled controls, a behaviour check),
// the remix brief's grouped tokens and added rules, the default social picture and the sitemap line, and the three
// faults the owner found pasting a reply (attribute selectors taken for shortcodes, the Plan box's message, the
// full-height window).

function twd_sk_accordion_css() {
	return ".c-faq { padding: var(--twd-site-section-y) 0; }\n.c-faq__title { font-family: var(--twd-site-font-heading); color: var(--twd-site-color-heading); }\n.c-item { border-bottom: 1px solid var(--twd-site-color-border); }\n.c-item[open] { background: var(--twd-site-color-tint); }\n.c-item[open] .c-q { color: var(--twd-site-color-heading); }\n.c-item[open] .c-q::after { content: \"-\"; }\n.c-q::-webkit-details-marker { display: none; }\n.c-q { list-style: none; }\n.c-q:focus-visible, .c-link:focus-within { outline: 2px solid var(--twd-site-color-primary); }\n.c-link[href^=\"#\"] { text-decoration: none; }\n.c-tab[data-on=\"yes\"] { font-weight: 600; }\n";
}
function twd_sk_accordion_section() {
	return '<section class="c-faq twd-sk-tone-surface"><div class="twd-sk-inner"><h2 class="c-faq__title" data-twd-edit="Heading">Questions</h2><details class="c-item"><summary class="c-q" data-twd-edit="Question one">How long is a session?</summary><p data-twd-edit="Answer one">Fifty minutes.</p></details><details class="c-item"><summary class="c-q" data-twd-edit="Question two">Where do we meet?</summary><p data-twd-edit="Answer two">In a quiet room, or online.</p></details></div></section>';
}
function twd_sk_reply_fenced( $note = true ) {
	return "Here is the remix you asked for.\n\n```html\n" . twd_sk_accordion_section() . "\n```\n\n```css\n" . twd_sk_accordion_css() . "```\n" . ( $note ? "\nWhat changed: The questions are now an accordion with a plain marker. Every word is the same.\n" : '' );
}
function twd_sk_import_admin() {
	twd_sk_rest_setup( array( 'edit_pages', 'edit_post:12', 'edit_post:51', 'manage_options', 'publish_pages' ) );
	$GLOBALS['twd_stub']['user'] = 7;
	twd_stub_add_post( 12, 'page', '[twd_page]' );
	$GLOBALS['twd_stub']['posts'][12]->post_title = 'Questions';
	$GLOBALS['twd_stub']['posts'][12]->post_name  = 'questions';
	$html = '';
	foreach ( array( 'hero', 'text', 'faq', 'cta' ) as $id ) {
		// The question groups layout (tabs and three details), so the behaviour check has something to compare.
		$html .= ( 'faq' === $id ? TWD_SK_Registry::get( $id )['variants']['twd-sk-faq--groups']['skeleton'] : TWD_SK_Registry::get( $id )['skeleton'] ) . "\n";
	}
	TWD_SK_Store::save( 12, $html );
	return TWD_SK_Store::get_current( 12 );
}

// -- 23f.1: attribute selectors are CSS, not shortcodes -----------------------------------------

twd_sk_test( 'section 23f: the shortcode cleaner leaves attribute selectors in CSS text alone ([open], [href^="#"], [data-x="y"]) and still removes shortcodes from prose', function () {
	$css = '<p>' . htmlspecialchars( twd_sk_accordion_css() ) . '</p>';
	$out = TWD_SK_Sanitizer::clean_with_report( $css );
	twd_sk_eq( array(), $out['report']['removed']['shortcodes'], 'no shortcode report for CSS text' );
	twd_sk_has( '.c-item[open] .c-q::after', $out['html'] );
	twd_sk_has( '.c-link[href^="#"]', $out['html'] );
	twd_sk_has( '.c-tab[data-on="yes"]', $out['html'] );
	// Prose keeps the old rule: a bracket token glued to a word is still a shortcode when the text is not CSS.
	twd_sk_eq( '<p>ab</p>', TWD_SK_Sanitizer::clean( '<p>a[embed]b</p>' ) );
	twd_sk_eq( '<p>Open  please.</p>', TWD_SK_Sanitizer::clean( '<p>Open [open] please.</p>' ) );
	// Inside CSS text a shortcode with a space is still removed: it is not an attribute selector.
	$mixed = TWD_SK_Sanitizer::clean_with_report( '<p>.c-a { color: red; } [gallery ids="1"] .c-b[open] { }</p>' );
	twd_sk_eq( array( '[gallery ids="1"]' ), $mixed['report']['removed']['shortcodes'] );
	twd_sk_has( '.c-b[open]', $mixed['html'] );
	twd_sk_true( TWD_SK_Sanitizer::is_attribute_selector( '[open]', 'x.c-a[open]', 5 ) );
	twd_sk_true( ! TWD_SK_Sanitizer::is_attribute_selector( '[open]', 'x.c-a [open]', 6 ), 'a space before it' );
	twd_sk_true( ! TWD_SK_Sanitizer::is_attribute_selector( '[gallery ids="1"]', 'a[gallery ids="1"]', 1 ), 'a space inside it' );
} );

twd_sk_test( 'section 23f: the site stylesheet accepts [open], [open] .c-q, [open] .c-q::after, [href^="#"], [data-x="y"] and ::-webkit-details-marker, scopes them, and refuses an attribute it cannot read', function () {
	$check = TWD_SK_Css::check( twd_sk_accordion_css() );
	twd_sk_true( $check['ok'], implode( ' | ', $check['errors'] ) );
	twd_sk_has( '.twd-sk-page .c-item[open]{background:var(--twd-site-color-tint)}', $check['built'] );
	twd_sk_has( '.twd-sk-page .c-item[open] .c-q{color:var(--twd-site-color-heading)}', $check['built'] );
	twd_sk_has( '.twd-sk-page .c-item[open] .c-q::after{content:"-"}', $check['built'] );
	twd_sk_has( '.twd-sk-page .c-q::-webkit-details-marker{display:none}', $check['built'] );
	twd_sk_has( '.twd-sk-page .c-link[href^="#"]{text-decoration:none}', $check['built'] );
	twd_sk_eq( array( 'c-faq', 'c-faq__title', 'c-item', 'c-q', 'c-link', 'c-tab' ), $check['classes'] );
	foreach ( array( '.c-a[*] { color: var(--twd-site-color-body); }', '.c-a[data-x="y" i] { color: var(--twd-site-color-body); }', '.c-a[on click] { color: var(--twd-site-color-body); }' ) as $bad ) {
		$c = TWD_SK_Css::check( $bad );
		twd_sk_true( ! $c['ok'], 'refused: ' . $bad );
	}
	// A class named only inside an attribute's value does not count as a defined class.
	$c = TWD_SK_Css::check( '.c-a[data-x="c-b"] { color: var(--twd-site-color-body); }' );
	twd_sk_true( $c['ok'] );
	twd_sk_eq( array( 'c-a' ), $c['classes'] );
} );

twd_sk_test( 'section 23f: the accordion rule survives to the stylesheet through Replace, Import and the AI route', function () {
	twd_sk_custom_page();
	$GLOBALS['twd_stub']['user'] = 7;
	// Replace by hand.
	$in  = array( 'target' => 'section', 'index' => 2, 'html' => twd_sk_accordion_section(), 'css' => twd_sk_accordion_css() );
	$out = TWD_SK_Replace::prepare( 51, $in );
	twd_sk_true( ! is_wp_error( $out ), is_wp_error( $out ) ? $out->get_error_message() : '' );
	twd_sk_has( '.c-item[open] .c-q::after', $out['check']['built'] );
	twd_sk_has( 'class="c-item"', $out['html'] );
	// Import: the same through a pasted reply.
	$read = TWD_SK_Replace::read_paste( twd_sk_reply_fenced() );
	twd_sk_true( ! is_wp_error( $read ) );
	twd_sk_has( '.c-item[open]', $read['css'] );
	$imp = TWD_SK_Replace::prepare( 51, array( 'target' => 'section', 'index' => 2, 'html' => $read['html'], 'css' => $read['css'] ) );
	twd_sk_has( '.c-item[open] .c-q::after', $imp['check']['built'] );
	// The AI route.
	$GLOBALS['twd_stub']['hooks']['twd_ai_is_configured'] = array( function () {
		return true;
	} );
	$GLOBALS['twd_stub']['hooks']['twd_ai_complete'] = array( function () {
		return wp_json_encode( array( 'html' => twd_sk_accordion_section(), 'css' => twd_sk_accordion_css(), 'what_changed' => 'An accordion now.' ) );
	} );
	$ai = TWD_SK_Replace::adapt( 51, array( 'index' => 2, 'instruction' => 'Make it an accordion' ), 7 );
	twd_sk_true( ! is_wp_error( $ai ), is_wp_error( $ai ) ? $ai->get_error_message() : '' );
	twd_sk_has( '.c-item[open]', $ai['css'] );
	// Applied: the built stylesheet on the site holds the rule.
	$saved = TWD_SK_Replace::apply( 51, $in, TWD_SK_Store::get_current_version_id( 51 ) );
	twd_sk_true( ! is_wp_error( $saved ), is_wp_error( $saved ) ? $saved->get_error_message() : '' );
	twd_sk_has( '.twd-sk-page .c-item[open] .c-q::after{content:"-"}', TWD_SK_Css::current()['built'] );
	twd_sk_has( '<details class="c-item">', TWD_SK_Store::get_current( 51 ) );
} );

// -- 23a: a whole reply -----------------------------------------------------------------------------

twd_sk_test( 'import: read_paste finds the html block, the css block and the What changed note, with labelled or unlabelled fences, with no fences, from JSON, and says so plainly when it cannot', function () {
	$r = TWD_SK_Replace::read_paste( twd_sk_reply_fenced() );
	twd_sk_true( ! is_wp_error( $r ) );
	twd_sk_eq( twd_sk_accordion_section(), $r['html'] );
	twd_sk_eq( trim( twd_sk_accordion_css() ), $r['css'] );
	twd_sk_eq( 'The questions are now an accordion with a plain marker. Every word is the same.', $r['note'] );
	twd_sk_eq( 'fenced', $r['how'] );
	// Unlabelled fences are told apart by their content; "none" is no CSS; Windows line endings are fine.
	$u = TWD_SK_Replace::read_paste( "```\n" . twd_sk_accordion_section() . "\n```\r\n```\nnone\n```\r\nWhat changed: Nothing in the words." );
	twd_sk_eq( twd_sk_accordion_section(), $u['html'] );
	twd_sk_eq( '', $u['css'] );
	twd_sk_eq( 'Nothing in the words.', $u['note'] );
	// No fences at all: the HTML runs from the first tag to the last, the CSS follows, the note after.
	$b = TWD_SK_Replace::read_paste( "Sure.\n" . twd_sk_accordion_section() . "\n\n" . twd_sk_accordion_css() . "\nWhat changed: An accordion.\n" );
	twd_sk_eq( twd_sk_accordion_section(), $b['html'] );
	twd_sk_has( '.c-item[open] .c-q::after', $b['css'] );
	twd_sk_eq( 'An accordion.', $b['note'] );
	twd_sk_eq( 'bare', $b['how'] );
	// One JSON object, as Describe a change returns.
	$j = TWD_SK_Replace::read_paste( wp_json_encode( array( 'html' => '<section class="c-x"><p data-twd-edit="Words">Hi</p></section>', 'css' => '', 'what_changed' => 'Said hi.' ) ) );
	twd_sk_eq( 'json', $j['how'] );
	twd_sk_eq( 'Said hi.', $j['note'] );
	// Nothing readable.
	twd_sk_is_error( 'twd_sk_bad_input', TWD_SK_Replace::read_paste( 'Thanks, here is my thinking about your page.' ) );
	twd_sk_is_error( 'twd_sk_bad_input', TWD_SK_Replace::read_paste( '' ) );
	twd_sk_is_error( 'twd_sk_too_large', TWD_SK_Replace::read_paste( str_repeat( '<p>x</p>', 60000 ) ) );
	twd_sk_has( 'one html block, one css block', TWD_SK_Replace::read_paste( 'no html here' )->get_error_message() );
} );

twd_sk_test( 'import route: a pasted reply previews into this section or the page with the parts it found, applies as one version with the stylesheet, and the editor is refused', function () {
	twd_sk_import_admin();
	$out = TWD_SK_REST::post_import( twd_sk_rest_req( array( 'mode' => 'preview', 'target' => 'section', 'index' => 2, 'reply' => twd_sk_reply_fenced() ) ) );
	twd_sk_true( ! is_wp_error( $out ), is_wp_error( $out ) ? $out->get_error_message() : '' );
	twd_sk_eq( strlen( twd_sk_accordion_section() ), $out['found']['html_bytes'] );
	twd_sk_true( $out['found']['css_bytes'] > 100 );
	twd_sk_has( 'accordion with a plain marker', $out['found']['note'] );
	twd_sk_true( TWD_SK_Preview::valid_token( $out['token'] ) );
	twd_sk_eq( 2, $out['at'] );
	twd_sk_eq( 0, TWD_SK_Css::version(), 'nothing saved by a preview' );
	// The behaviour line: the kit's question groups had tabs; this accordion has none, only details.
	$w = implode( ' | ', $out['warnings'] );
	twd_sk_has( 'fewer things a visitor can open or follow', $w );
	// Apply to the whole page.
	$v   = TWD_SK_Store::get_current_version_id( 12 );
	$all = TWD_SK_REST::post_import( twd_sk_rest_req( array( 'mode' => 'apply', 'target' => 'page', 'reply' => twd_sk_reply_fenced(), 'base_version' => $v ) ) );
	twd_sk_true( ! is_wp_error( $all ), is_wp_error( $all ) ? $all->get_error_message() : '' );
	twd_sk_eq( $v + 1, $all['version'] );
	twd_sk_eq( 1, $all['css_v'] );
	twd_sk_eq( 0, $all['at'] );
	twd_sk_has( 'The page has no main heading (h1)', implode( ' | ', $all['warnings'] ) );
	twd_sk_has( 'class="twd-sk-custom c-faq twd-sk-tone-surface"', TWD_SK_Store::get_current( 12 ) );
	twd_sk_has( '.twd-sk-page .c-item[open]', TWD_SK_Css::current()['built'] );
	// A stale version is refused; an unreadable reply is a plain 400; an editor is refused.
	twd_sk_is_error( 'twd_sk_conflict', TWD_SK_REST::post_import( twd_sk_rest_req( array( 'mode' => 'apply', 'target' => 'page', 'reply' => twd_sk_reply_fenced(), 'base_version' => $v ) ) ) );
	twd_sk_is_error( 'twd_sk_bad_input', TWD_SK_REST::post_import( twd_sk_rest_req( array( 'mode' => 'preview', 'target' => 'page', 'reply' => 'just words' ) ) ) );
	$GLOBALS['twd_stub']['caps'] = array( 'edit_pages', 'edit_post:12' );
	twd_sk_is_error( 'twd_sk_forbidden', TWD_SK_REST::can_import_page( twd_sk_rest_req( array( 'mode' => 'preview', 'target' => 'page' ) ) ) );
	$GLOBALS['twd_stub']['caps'] = array( 'edit_pages', 'edit_post:12', 'manage_options' );
	twd_sk_eq( true, TWD_SK_REST::can_import_page( twd_sk_rest_req( array( 'mode' => 'apply', 'target' => 'new' ) ) ) );
} );

twd_sk_test( 'import route: Make a new draft page from this previews in this page\'s place, then makes a DRAFT with the kit template, the stylesheet first and the page as version 1, and never publishes', function () {
	twd_sk_import_admin();
	$before = count( $GLOBALS['twd_stub']['posts'] );
	$pre    = TWD_SK_REST::post_import( twd_sk_rest_req( array( 'mode' => 'preview', 'target' => 'new', 'reply' => twd_sk_reply_fenced(), 'title' => 'Second look' ) ) );
	twd_sk_true( ! is_wp_error( $pre ), is_wp_error( $pre ) ? $pre->get_error_message() : '' );
	twd_sk_true( TWD_SK_Preview::valid_token( $pre['token'] ), 'shown in this page\'s place' );
	twd_sk_eq( $before, count( $GLOBALS['twd_stub']['posts'] ), 'a preview makes no page' );
	twd_sk_eq( 0, TWD_SK_Css::version() );
	// No title: refused, nothing made.
	twd_sk_is_error( 'twd_sk_bad_input', TWD_SK_REST::post_import( twd_sk_rest_req( array( 'mode' => 'apply', 'target' => 'new', 'reply' => twd_sk_reply_fenced(), 'title' => '' ) ) ) );
	twd_sk_eq( $before, count( $GLOBALS['twd_stub']['posts'] ) );
	$out = TWD_SK_REST::post_import( twd_sk_rest_req( array( 'mode' => 'apply', 'target' => 'new', 'reply' => twd_sk_reply_fenced(), 'title' => 'Second look' ) ) );
	twd_sk_true( ! is_wp_error( $out ), is_wp_error( $out ) ? $out->get_error_message() : '' );
	$id = $out['page_id'];
	twd_sk_true( $id > 12 );
	$post = get_post( $id );
	twd_sk_eq( 'draft', $post->post_status );
	twd_sk_eq( 'Second look', $post->post_title );
	twd_sk_eq( TWD_SK_Template::SLUG, get_post_meta( $id, '_wp_page_template', true ) );
	twd_sk_eq( 1, $out['version'] );
	twd_sk_eq( 1, $out['css_v'] );
	twd_sk_has( 'twd_sk_edit=1', $out['edit_url'] );
	twd_sk_has( 'accordion', $out['found']['note'] );
	twd_sk_has( '<details class="c-item">', TWD_SK_Store::get_current( $id ) );
	twd_sk_eq( $id, TWD_SK_Css::versions()[0]['page'], 'the stylesheet version names the new page' );
	twd_sk_true( TWD_SK_Store::get_current( 12 ) !== TWD_SK_Store::get_current( $id ), 'this page untouched' );
	// Page 12 itself was not changed.
	twd_sk_has( 'twd-sk-hero', TWD_SK_Store::get_current( 12 ) );
	// The command line does the same from a file.
	$file = tempnam( sys_get_temp_dir(), 'twd-sk-reply' );
	file_put_contents( $file, twd_sk_reply_fenced() );
	$cli = new TWD_SK_CLI();
	WP_CLI::reset();
	$cli->import( array( $file ), array( 'new' => 'Third look', 'dry-run' => true ) );
	twd_sk_has( 'Dry run: a new page "Third look" would be made', WP_CLI::all() );
	twd_sk_has( 'Found HTML (', WP_CLI::all() );
	WP_CLI::reset();
	$cli->import( array( $file ), array( 'new' => 'Third look' ) );
	twd_sk_has( 'Made the draft page "Third look"', WP_CLI::all() );
	WP_CLI::reset();
	$cli->import( array( $file ), array( 'page' => '12', 'section' => '3' ) );
	twd_sk_has( 'Replaced section 3 of page 12', WP_CLI::all() );
	twd_sk_has( 'class="twd-sk-custom c-faq', TWD_SK_Store::get_current( 12 ) );
	WP_CLI::reset();
	try {
		$cli->import( array( $file ), array() );
		twd_sk_fail( 'no target should be refused' );
	} catch ( WP_CLI_Stub_Error $e ) {
		twd_sk_has( '--page=ID', $e->getMessage() );
	}
	unlink( $file );
} );

// -- 23b: the safer remix loop ----------------------------------------------------------------------

twd_sk_test( 'section 23b: an unknown token is refused naming the nearest real one; a class another page uses warns with a prefix to use instead; identical rules are not collisions', function () {
	$c = TWD_SK_Css::check( '.c-a { color: var(--twd-site-color-primery); }' );
	twd_sk_true( ! $c['ok'] );
	twd_sk_has( 'There is no site token called --twd-site-color-primery', $c['errors'][0] );
	twd_sk_has( 'Did you mean --twd-site-color-primary?', $c['errors'][0] );
	twd_sk_eq( 'size-h2', TWD_SK_Css::nearest_token( 'size-h2x' ) );
	twd_sk_eq( 'radius-card', TWD_SK_Css::nearest_token( 'radius-cards' ) );
	twd_sk_eq( '', TWD_SK_Css::nearest_token( 'zzzzzzzzzzzzzz' ) );
	// Collisions.
	twd_sk_import_admin();
	twd_stub_add_post( 13, 'page', '[twd_page]' );
	$GLOBALS['twd_stub']['posts'][13]->post_title = 'About';
	TWD_SK_Css::save( ".c-box { padding: var(--twd-site-card-pad); }\n.c-note { color: var(--twd-site-color-muted); }", 'test', 13 );
	TWD_SK_Store::save( 13, '<section class="twd-sk-custom c-about"><div class="c-box"><p data-twd-edit="Words">About us.</p></div></section>' );
	twd_sk_eq( array( 'c-box' => '.c-box' ), TWD_SK_Css::collisions( '.c-box { padding: var(--twd-site-gap-md); }' ) );
	twd_sk_eq( array(), TWD_SK_Css::collisions( '.c-box { padding: var(--twd-site-card-pad); }' ), 'the same rule again is no collision' );
	twd_sk_eq( array(), TWD_SK_Css::collisions( '.c-new { padding: var(--twd-site-gap-md); }' ), 'a new rule is no collision' );
	$out = TWD_SK_Replace::prepare( 12, array( 'target' => 'section', 'index' => 1, 'html' => '<section class="c-q"><div class="c-box"><p data-twd-edit="Words">Hello</p></div></section>', 'css' => '.c-box { padding: var(--twd-site-gap-md); }' ) );
	twd_sk_true( ! is_wp_error( $out ), is_wp_error( $out ) ? $out->get_error_message() : '' );
	$w = implode( ' | ', $out['warnings'] );
	twd_sk_has( '.c-box already styles "About"; this rule would change it there too', $w );
	twd_sk_has( 'such as c-questions-', $w );
	// A rule the same page uses alone is no collision.
	$own = TWD_SK_Replace::prepare( 13, array( 'target' => 'section', 'index' => 0, 'html' => '<section class="c-about"><div class="c-box"><p data-twd-edit="Words">About us.</p></div></section>', 'css' => '.c-box { padding: var(--twd-site-gap-md); }' ) );
	twd_sk_hasnt( 'already styles', implode( ' | ', $own['warnings'] ) );
	twd_sk_eq( 'c-questions-', TWD_SK_Replace::prefix_for( 12 ) );
	twd_sk_eq( 'c-new-', TWD_SK_Replace::prefix_for( 0 ) );
} );

twd_sk_test( 'section 23b: an in-page link\'s target keeps its id inside a custom section (once, never a clobbering name, never outside one), and a link to nowhere warns at preview', function () {
	$kept = TWD_SK_Sanitizer::clean( '<section class="twd-sk-custom c-faq"><ul><li><a href="#faq-fees">Fees</a></li></ul><h3 id="faq-fees">Fees</h3><h3 id="faq-fees">Again</h3><h3 id="faq-times">Times</h3><h3 id="location">Where</h3></section>' );
	twd_sk_has( '<h3 id="faq-fees">Fees</h3>', $kept );
	twd_sk_has( '<h3>Again</h3>', $kept, 'an id is kept once' );
	twd_sk_has( '<h3>Times</h3>', $kept, 'no link points at it' );
	twd_sk_has( '<h3>Where</h3>', $kept, 'a name a script reads is never an id' );
	$kit = TWD_SK_Sanitizer::clean( '<section class="twd-sk-text"><a href="#faq-fees">Fees</a><h3 id="faq-fees">Fees</h3></section>' );
	twd_sk_has( '<h3>Fees</h3>', $kit, 'outside a custom section the twd-sk- rule stands' );
	twd_sk_has( '<div id="twd-sk-faq">x</div>', TWD_SK_Sanitizer::clean( '<div id="twd-sk-faq">x</div>' ) );
	twd_sk_eq( array(), TWD_SK_Replace::link_warnings( '<section class="twd-sk-custom c-a"><a href="#faq-fees">Fees</a><h3 id="faq-fees">Fees</h3><a href="#">Top</a><a href="#twd-sk-helplines">Help</a></section>' ) );
	$w = TWD_SK_Replace::link_warnings( '<section class="twd-sk-custom c-a"><a href="#faq-fees">Fees</a><a href="#faq-times">Times</a><a href="#faq-times">Times again</a></section>' );
	twd_sk_eq( 1, count( $w ) );
	twd_sk_has( '2 links go nowhere: #faq-fees, #faq-times', $w[0] );
	// Through prepare: the kept id makes the link whole.
	twd_sk_import_admin();
	$out = TWD_SK_Replace::prepare( 12, array( 'target' => 'section', 'index' => 2, 'html' => '<section class="c-faq"><p data-twd-edit="Intro"><a href="#fees">Fees</a></p><h3 id="fees" data-twd-edit="Heading">Fees</h3></section>', 'css' => '' ) );
	twd_sk_has( 'id="fees"', $out['html'] );
	twd_sk_hasnt( '#fees', implode( ' | ', $out['warnings'] ), 'the kept id makes the link whole' );
} );

twd_sk_test( 'section 23b: staging addresses are listed at preview and in the launch check (hosts with "staging" in them, plus the filter\'s list); unlabelled links and buttons are named; the behaviour check says when a section lost its tabs or opening items', function () {
	twd_sk_eq( array( 'https://staging.example/about/' ), TWD_SK_Replace::staging_addresses( '<a href="https://staging.example/about/">x</a><a href="https://example.test/about/">y</a><a href="/contact/">z</a>' ) );
	$GLOBALS['twd_stub']['hooks']['twd_sk_staging_hosts'] = array( function () {
		return array( 'preview.example' );
	} );
	twd_sk_eq( array( 'https://www.preview.example/x' ), TWD_SK_Replace::staging_addresses( '<img src="https://www.preview.example/x">' ) );
	// The site's own media library pictures are never listed (the Visual editor writes them so, and a migration
	// rewrites them); a link to the site's own staging host by hand is.
	$GLOBALS['twd_stub']['hooks']['twd_sk_staging_hosts'] = array( function () {
		return array( 'example.test' );
	} );
	twd_sk_eq( array( 'https://example.test/contact/' ), TWD_SK_Replace::staging_addresses( '<img src="https://example.test/wp-content/' . 'uploads/2026/10/sofa.jpg"><a href="https://example.test/contact/">Contact</a>' ) );
	unset( $GLOBALS['twd_stub']['hooks']['twd_sk_staging_hosts'] );
	twd_sk_import_admin();
	$out = TWD_SK_Replace::prepare( 12, array( 'target' => 'section', 'index' => 1, 'html' => '<section class="c-a"><p data-twd-edit="Words">See <a href="https://staging.example/about/">about</a>.</p><a class="twd-sk-btn" href="/contact/">Book now</a><a href="https://staging.example/fees/">Fees</a></section>', 'css' => '' ) );
	$w = implode( ' | ', $out['warnings'] );
	twd_sk_has( 'The HTML holds 2 staging addresses (https://staging.example/about/, https://staging.example/fees/)', $w );
	twd_sk_has( '2 links or buttons have no label (data-twd-edit), so the client cannot change their words: "Book now", "Fees"', $w );
	twd_sk_hasnt( '"about"', $w, 'a link inside a labelled paragraph is editable' );
	// Launch check.
	TWD_SK_Store::save( 12, $out['html'] );
	update_option( 'show_on_front', 'page' );
	update_option( 'page_on_front', 12 );
	$c    = TWD_SK_Launch::check();
	$item = null;
	foreach ( $c['items'] as $i ) {
		if ( 'staging' === $i['id'] ) {
			$item = $i;
		}
	}
	twd_sk_true( null !== $item, 'a staging item' );
	twd_sk_eq( 'warn', $item['status'] );
	twd_sk_has( '2 staging addresses are written into the pages', $item['label'] );
	twd_sk_has( 'https://staging.example/about/', $item['detail'] );
	// Behaviour: the kit's question groups (tabs and details) replaced by in-page links and fewer details.
	$groups = TWD_SK_Registry::get( 'faq' )['variants']['twd-sk-faq--groups']['skeleton'];
	$w2 = TWD_SK_Replace::behaviour_warnings( $groups, '<section class="c-faq"><a href="#a">A</a><a href="#b">B</a><details class="c-item"><summary>Q</summary><p>A</p></details></section>' );
	twd_sk_has( 'question group tabs switched which group shows', implode( ' | ', $w2 ) );
	twd_sk_has( 'fewer things a visitor can open or follow', implode( ' | ', $w2 ) );
	twd_sk_eq( array(), TWD_SK_Replace::behaviour_warnings( '<section class="twd-sk-text"><p>Words</p></section>', '<section class="c-x"><p>Words</p><a href="/more/">More</a></section>' ), 'more is fine' );
	twd_sk_eq( array( 'details' => 2, 'inpage' => 1, 'links' => 1, 'tabs' => 0 ), TWD_SK_Replace::interactive( '<details></details><details></details><a href="#x">a</a><a href="/y/">b</a>' ) );
} );

// -- 23c: the brief ----------------------------------------------------------------------------------

twd_sk_test( 'section 23c: the remix brief lists every token in groups with its value, the reply format is one html block, one css block and one What changed note, and the rules cover behaviour, addresses, labels, reduced motion, the Safari marker, ids and a prefix', function () {
	twd_sk_export_page();
	$GLOBALS['twd_stub']['posts'][12]->post_name = 'van-hire';
	$out = TWD_SK_Export::brief( 12 );
	twd_sk_true( is_array( $out ), is_wp_error( $out ) ? $out->get_error_message() : '' );
	$md = $out['content'];
	foreach ( array( '### Colours', '### Type sizes', '### Spacing', '### Corners and edges', '### Band, frame and picture heights', '### Phone variants', '| color-body | ', '| size-body | ', '| radius-card | ', '| band-height-sm | ', 'Exactly three things', 'ONE fenced block marked `html`', 'ONE fenced block marked `css`', 'One short note on a line starting "What changed:"', 'Keep the behaviour the same unless asked', 'never a staging address', 'on every button or link whose words they might change', 'prefers-reduced-motion: reduce', 'summary::-webkit-details-marker', 'starts with the prefix c-van-hire-', 'In-page links (href="#name") need an element with that id', 'Only the token names listed below exist', 'Import a page' ) as $needle ) {
		twd_sk_has( $needle, $md );
	}
	// Every token is in exactly one group.
	$groups = TWD_SK_Export::token_groups( TWD_SK_Packs::active_tokens() );
	$seen   = array();
	foreach ( $groups as $rows ) {
		foreach ( $rows as $name => $value ) {
			twd_sk_true( ! isset( $seen[ $name ] ), 'once: ' . $name );
			$seen[ $name ] = true;
		}
	}
	twd_sk_eq( count( TWD_SK_Packs::token_names() ), count( $seen ) );
	twd_sk_true( isset( $groups['Phone variants (used under 768px; the same names with -sm)']['size-h1-sm'] ) );
	twd_sk_true( isset( $groups['Colours']['color-frame'] ) );
	twd_sk_true( isset( $groups['Logo and menu']['menu-size'] ) || isset( $groups['Type sizes']['menu-size'] ) );
} );

// -- 23d: the two audit follow-ups ----------------------------------------------------------------

twd_sk_test( 'section 23d: a site-wide social picture (Site details, or the logo) is printed as og:image and twitter:image on a kit page without its own; a page with its own keeps it; nothing is printed with none', function () {
	twd_stub_reset();
	twd_stub_add_post( 30, 'page', '[twd_page]' );
	$GLOBALS['twd_stub']['attachments'][55] = array( 'url' => 'https://example.test/u/pic.jpg', 'w' => 1200, 'h' => 630, 'is_image' => true );
	$GLOBALS['twd_stub']['attachments'][56] = array( 'url' => 'https://example.test/u/logo.png', 'w' => 600, 'h' => 600, 'is_image' => true );
	$GLOBALS['twd_stub']['attachments'][57] = array( 'url' => 'https://example.test/u/share.jpg', 'w' => 1200, 'h' => 630, 'is_image' => true );
	$GLOBALS['twd_stub']['hooks']['twd_sk_seo_plugin'] = array( function () {
		return array( 'mode' => 'none', 'name' => '' );
	} );
	$head = function () {
		$GLOBALS['twd_stub']['queried'] = 30;
		ob_start();
		TWD_SK_Seo::print_head();
		return ob_get_clean();
	};
	twd_sk_eq( 0, TWD_SK_Seo::default_image_id() );
	twd_sk_eq( '', $head(), 'nothing set anywhere, nothing printed' );
	TWD_SK_Profile::save( array( 'site_name' => 'Calm', 'logo_id' => 56 ) );
	twd_sk_eq( 56, TWD_SK_Seo::default_image_id(), 'the logo stands in' );
	$h = $head();
	twd_sk_has( 'property="og:image" content="https://example.test/u/logo.png"', $h );
	twd_sk_has( 'name="twitter:image" content="https://example.test/u/logo.png"', $h );
	twd_sk_has( 'twitter:card" content="summary_large_image"', $h );
	twd_sk_eq( 1, substr_count( $h, 'og:image' ) );
	// A chosen picture wins over the logo; the field is checked like the logo.
	$saved = TWD_SK_Profile::save( array( 'social_image_id' => 57 ) );
	twd_sk_true( ! is_wp_error( $saved ), is_wp_error( $saved ) ? $saved->get_error_message() : '' );
	twd_sk_eq( 57, TWD_SK_Seo::default_image_id() );
	twd_sk_has( 'content="https://example.test/u/share.jpg"', $head() );
	twd_sk_is_error( 'twd_sk_bad_profile', TWD_SK_Profile::save( array( 'social_image_id' => 999 ) ) );
	twd_sk_is_error( 'twd_sk_bad_profile', TWD_SK_Profile::save( array( 'social_image_id' => 'logo' ) ) );
	// The page's own picture wins.
	TWD_SK_Seo::write( 30, array( 'image_id' => 55 ) );
	twd_sk_has( 'content="https://example.test/u/pic.jpg"', $head() );
	twd_sk_hasnt( 'share.jpg', $head() );
	// The starter profile has the field, empty.
	twd_sk_eq( 0, TWD_SK_Profile::starter()['social_image_id'] );
} );

twd_sk_test( 'section 23d: the launch check says the sitemap appears once search engines are allowed, and names it when they are', function () {
	twd_stub_add_post( 21, 'page', '[twd_page]' );
	TWD_SK_Store::save( 21, '<section class="twd-sk-hero"><h1 class="twd-sk-hero__title">Welcome</h1></section>' );
	$GLOBALS['twd_stub']['caps'] = true;
	update_option( 'blog_public', '0' );
	$find = function ( $c, $id ) {
		foreach ( $c['items'] as $i ) {
			if ( $id === $i['id'] ) {
				return $i;
			}
		}
		return null;
	};
	$c = TWD_SK_Launch::check();
	twd_sk_eq( 'warn', $find( $c, 'sitemap' )['status'] );
	twd_sk_has( 'The sitemap appears once search engines are allowed', $find( $c, 'sitemap' )['label'] );
	twd_sk_has( '/wp-sitemap.xml', $find( $c, 'sitemap' )['detail'] );
	twd_sk_true( null === $find( $c, 'staging' ), 'no staging address, no item' );
	update_option( 'blog_public', '1' );
	$c = TWD_SK_Launch::check();
	twd_sk_eq( 'ok', $find( $c, 'sitemap' )['status'] );
	twd_sk_has( '/wp-sitemap.xml', $find( $c, 'sitemap' )['label'] );
} );

// -- 23f.2 and 23f.3: the Plan box and the window ----------------------------------------------------

twd_sk_test( 'section 23f: the Plan box refuses a pasted page with a line saying where it goes, the too-long message says so too, and the scripts carry the move button, Import a page and the full-height window', function () {
	twd_sk_true( TWD_SK_Plan::looks_like_html( '<section class="twd-sk-hero">' ) );
	twd_sk_true( TWD_SK_Plan::looks_like_html( "```html\n<section>" ) );
	twd_sk_true( TWD_SK_Plan::looks_like_html( 'Here it is <section class="c-a">' ) );
	twd_sk_true( ! TWD_SK_Plan::looks_like_html( 'Make the heading shorter and add a <strong> word' ) );
	twd_sk_true( ! TWD_SK_Plan::looks_like_html( '' ) );
	twd_sk_rest_setup();
	$GLOBALS['twd_stub']['hooks']['twd_ai_is_configured'] = array( function () {
		return true;
	} );
	$e = TWD_SK_Plan::plan( 12, '<section class="c-a"><p>Pasted</p></section>' );
	twd_sk_is_error( 'twd_sk_bad_input', $e );
	twd_sk_has( 'Import a page or Replace this section', $e->get_error_message() );
	$long = TWD_SK_Plan::plan( 12, str_repeat( 'words ', 1300 ) );
	twd_sk_is_error( 'twd_sk_bad_input', $long );
	twd_sk_has( 'Keep it under 6000 characters', $long->get_error_message() );
	twd_sk_has( 'Import a page or Replace this section', $long->get_error_message() );
	$editor = file_get_contents( dirname( __DIR__ ) . '/assets/twd-site-kit-editor.js' );
	foreach ( array( 'function looksLikeHtml(', "'Move it to Import a page'", 'ED.openImport(text)', "text: 'Full height'", 'function toggleFull()', "twd-sk-ed__resize-b", "'twd-sk-ed__dialog--tall'", "'twd-sk-ed__dialog--full'", 'full: win.full', "startResize(e, 'height')" ) as $needle ) {
		twd_sk_has( $needle, $editor );
	}
	$inline = file_get_contents( dirname( __DIR__ ) . '/assets/twd-site-kit-inline.js' );
	foreach ( array( "toolButton('Import a page'", "'/pages/' + cfg.pageId + '/import'", "text: 'Make a new draft page from this'", "text: 'Replace this page'", 'ED.openImport = CUSTOM ?', 'function importEdit()', 'ui.impPanel', "'Edit the HTML first'" ) as $needle ) {
		twd_sk_has( $needle, $inline );
	}
	$css = file_get_contents( dirname( __DIR__ ) . '/assets/twd-site-kit-editor.css' );
	twd_sk_has( '.twd-sk-ed__overlay--free.twd-sk-ed__overlay--free .twd-sk-ed__dialog--tall.twd-sk-ed__dialog--tall', $css );
	twd_sk_has( 'ns-resize', $css );
	// The window state remembers full height; anything else is false.
	twd_sk_eq( false, TWD_SK_Editor::window_defaults()['full'] );
	twd_sk_eq( true, TWD_SK_Editor::clean_window( array( 'mode' => 'free', 'full' => true ) )['full'] );
	twd_sk_eq( true, TWD_SK_Editor::clean_window( array( 'mode' => 'free', 'full' => '1' ) )['full'] );
	twd_sk_eq( false, TWD_SK_Editor::clean_window( array( 'mode' => 'free', 'full' => 'yes please' ) )['full'] );
	twd_sk_eq( false, TWD_SK_Editor::clean_window( array( 'mode' => 'free' ) )['full'] );
	// The Site tab script has the default social picture field.
	$site = file_get_contents( dirname( __DIR__ ) . '/assets/twd-site-kit-editor-site.js' );
	twd_sk_has( 'social_image_id:', $site );
	twd_sk_has( "field('Default social picture number'", $site );
} );
