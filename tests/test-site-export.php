<?php
// The site export (a zip an AI can read) and the AI's reply read back as changes (0.17.0): TWD_SK_SiteExport and
// TWD_SK_SiteImport. Administrators only; the export writes nothing; a change is checked, shown, then applied page
// by page as one version each.

function twd_sk_se_html( $tag = 'one' ) {
	return '<section class="twd-sk-hero twd-sk-hero--compact twd-sk-tone-base"><div class="twd-sk-inner"><h1 class="twd-sk-hero__title">Welcome ' . $tag . '</h1></div></section>' . "\n"
		. '<section class="twd-sk-text twd-sk-tone-base"><div class="twd-sk-inner"><h2 class="twd-sk-title">Our approach</h2><p>We work gently and at your pace.</p><p>Sessions last fifty minutes.</p></div></section>' . "\n"
		. '<section class="twd-sk-text twd-sk-tone-surface"><div class="twd-sk-inner"><h2 class="twd-sk-title">Fees</h2><p>Sessions are one hundred pounds.</p></div></section>';
}

function twd_sk_se_setup() {
	twd_sk_rest_setup( array( 'edit_pages', 'edit_post:301', 'edit_post:302', 'manage_options', 'unfiltered_html' ) );
	foreach ( array( 301, 302 ) as $id ) {
		twd_stub_add_post( $id, 'page', '[twd_page]' );
		$GLOBALS['twd_stub']['posts'][ $id ]->post_name = 1 === $id - 300 ? 'about-me' : 'contact-me';
		TWD_SK_Store::save( $id, twd_sk_se_html( (string) $id ) );
	}
}

function twd_sk_se_cleanup() {
	foreach ( array( 301, 302 ) as $id ) {
		unset( $GLOBALS['twd_stub']['posts'][ $id ] );
	}
}

function twd_sk_se_reply( $changes, $extra = array() ) {
	return json_encode( array_merge( array( 'schema' => TWD_SK_SiteExport::REPLY_SCHEMA, 'what_changed' => 'Test.', 'changes' => $changes ), $extra ) );
}

twd_sk_test( 'site export: the whole site is a README, an index, one file per page with a comment before every section, the header and footer, the style, the stylesheet, the facts and the notes', function () {
	twd_sk_se_setup();
	$set = TWD_SK_SiteExport::files( array( 'scope' => 'all', 'notes' => "Check the <b>fees</b> wording.\r\nSecond line." ) );
	twd_sk_true( is_array( $set ), is_wp_error( $set ) ? $set->get_error_message() : '' );
	foreach ( array( 'README-FOR-AI.md', 'pages/_index.json', 'pages/about-me.html', 'pages/contact-me.html', 'site/header-and-footer.json', 'site/style-tokens.json', 'site/site-stylesheet.css', 'notes/practice-facts.md', 'notes/your-notes.md' ) as $f ) {
		twd_sk_true( isset( $set['files'][ $f ] ), 'has ' . $f );
	}
	$page = $set['files']['pages/about-me.html'];
	twd_sk_has( '<!-- twd-section 1: hero', $page );
	twd_sk_has( '<!-- twd-section 2: text, Our approach -->', $page );
	twd_sk_has( '<!-- twd-section 3: text, Fees -->', $page );
	twd_sk_has( 'Sessions last fifty minutes.', $page );
	twd_sk_hasnt( 'data-twd-sk', $page, 'no editor numbers in the export' );
	$index = json_decode( $set['files']['pages/_index.json'], true );
	twd_sk_eq( 2, count( $index['pages'] ) );
	twd_sk_eq( 'about-me', $index['pages'][0]['page'] );
	twd_sk_eq( 3, $index['pages'][0]['sections'] );
	twd_sk_has( 'Check the fees wording.', $set['files']['notes/your-notes.md'], 'tags are removed from the notes' );
	twd_sk_hasnt( "\r", $set['files']['notes/your-notes.md'] );
	$tokens = json_decode( $set['files']['site/style-tokens.json'], true );
	twd_sk_true( isset( $tokens['tokens']['Colours'] ), 'tokens come in groups' );
	twd_sk_eq( 'README-FOR-AI.md', array_keys( $set['files'] )[0], 'the README is first' );
	twd_sk_hasnt( 'password', strtolower( $set['files']['site/header-and-footer.json'] ), 'no secrets' );
	twd_sk_se_cleanup();
} );

twd_sk_test( 'site export: one page leaves out the header and footer; the header and footer scope leaves out the pages; a bad scope or a page that is not a kit page is refused', function () {
	twd_sk_se_setup();
	$one = TWD_SK_SiteExport::files( array( 'scope' => 'page', 'page' => 302 ) );
	twd_sk_true( isset( $one['files']['pages/contact-me.html'] ) && ! isset( $one['files']['pages/about-me.html'] ), 'only that page' );
	twd_sk_true( ! isset( $one['files']['site/header-and-footer.json'] ), 'no header and footer' );
	twd_sk_true( isset( $one['files']['site/site-stylesheet.css'] ) && isset( $one['files']['site/style-tokens.json'] ), 'style goes with a page' );
	$chrome = TWD_SK_SiteExport::files( array( 'scope' => 'chrome' ) );
	twd_sk_true( isset( $chrome['files']['site/header-and-footer.json'] ), 'header and footer' );
	twd_sk_eq( array(), array_values( array_filter( array_keys( $chrome['files'] ), function ( $n ) { return 0 === strpos( $n, 'pages/' ); } ) ), 'no pages' );
	twd_sk_is_error( 'twd_sk_bad_input', TWD_SK_SiteExport::files( array( 'scope' => 'everything' ) ) );
	twd_sk_is_error( 'twd_sk_not_a_page', TWD_SK_SiteExport::files( array( 'scope' => 'page', 'page' => 999999 ) ) );
	twd_sk_se_cleanup();
} );

twd_sk_test( 'site export: build() gives a zip that holds the same files (or one markdown file where zip is missing), and the README names the reply format and every rule', function () {
	twd_sk_se_setup();
	$out = TWD_SK_SiteExport::build( array( 'scope' => 'all', 'notes' => 'Notes here.' ) );
	twd_sk_true( is_array( $out ), is_wp_error( $out ) ? $out->get_error_message() : '' );
	$bytes = base64_decode( $out['base64'] );
	twd_sk_eq( strlen( $bytes ), $out['bytes'] );
	if ( 'zip' === $out['kind'] ) {
		twd_sk_true( 0 === strpos( $bytes, 'PK' ), 'a zip' );
		twd_sk_true( 1 === preg_match( '/^export-site-\d{4}-\d{2}-\d{2}\.zip$/', $out['name'] ), $out['name'] );
		$tmp = tempnam( sys_get_temp_dir(), 'twdt' );
		file_put_contents( $tmp, $bytes );
		$z = new ZipArchive();
		twd_sk_true( true === $z->open( $tmp ), 'opens' );
		twd_sk_eq( count( $out['files'] ), $z->numFiles, 'every file is in it' );
		twd_sk_has( 'Sessions last fifty minutes.', (string) $z->getFromName( 'pages/about-me.html' ) );
		$z->close();
		@unlink( $tmp );
	} else {
		twd_sk_has( '=== FILE: pages/about-me.html ===', $bytes );
	}
	$readme = TWD_SK_SiteExport::readme( 'all', array(), true );
	twd_sk_has( TWD_SK_SiteExport::REPLY_SCHEMA, $readme );
	foreach ( TWD_SK_Export::rules( 'c-page-' ) as $rule ) {
		twd_sk_has( $rule, $readme, 'rule: ' . substr( $rule, 0, 40 ) );
	}
	twd_sk_true( ! preg_match( '/[\x{2013}\x{2014}]/u', $readme ), 'no long dashes' );
	twd_sk_se_cleanup();
} );

twd_sk_test( 'site changes: a reply that is not the site\'s format is refused with a plain sentence', function () {
	twd_sk_is_error( 'twd_sk_bad_input', TWD_SK_SiteImport::read( '' ) );
	twd_sk_is_error( 'twd_sk_bad_input', TWD_SK_SiteImport::read( 'Sure! Here are my thoughts.' ) );
	twd_sk_is_error( 'twd_sk_bad_input', TWD_SK_SiteImport::read( '{ "changes": [] }' ), 'no schema' );
	twd_sk_is_error( 'twd_sk_bad_input', TWD_SK_SiteImport::read( '{ "schema": "twd-site-changes/1" }' ), 'no list' );
	twd_sk_is_error( 'twd_sk_bad_input', TWD_SK_SiteImport::read( '{ "schema": "twd-site-changes/1", "changes": [ ' ), 'cut short' );
	$ok = TWD_SK_SiteImport::read( "Here you go:\n```json\n" . twd_sk_se_reply( array() ) . "\n```\nHope that helps." );
	twd_sk_true( is_array( $ok ) && array() === $ok['changes'], 'a fence and a sentence round it are tolerated' );
} );

twd_sk_test( 'site changes: a find and replace is checked against the section (exactly once), a section is replaced, unknown pages and sections and the safety notice are named, and nothing is written by plan()', function () {
	twd_sk_se_setup();
	$v = TWD_SK_Store::get_current_version_id( 301 );
	$reply = TWD_SK_SiteImport::read( twd_sk_se_reply( array(
		array( 'page' => 'about-me', 'section' => 3, 'find' => 'one hundred pounds', 'replace' => 'ninety pounds', 'why' => 'Matches the notes.' ),
		array( 'page' => 'about-me', 'section' => 2, 'find' => '<p>', 'replace' => '<p class="x">' ),
		array( 'page' => 'about-me', 'section' => 2, 'find' => 'no such words', 'replace' => 'x' ),
		array( 'page' => 'about-me', 'section' => 9, 'html' => '<section><p>x</p></section>' ),
		array( 'page' => 'nowhere', 'section' => 1, 'html' => '<section><p>x</p></section>' ),
		array( 'page' => 'about-me' ),
	) ) );
	$plan = TWD_SK_SiteImport::plan( $reply );
	$items = $plan['items'];
	twd_sk_true( $items[0]['ok'], $items[0]['error'] );
	twd_sk_eq( 'one hundred pounds', $items[0]['before'] );
	twd_sk_eq( 'ninety pounds', $items[0]['after'] );
	twd_sk_eq( 'Matches the notes.', $items[0]['why'] );
	twd_sk_true( ! $items[1]['ok'] && false !== strpos( $items[1]['error'], 'appear' ), 'a word that appears twice is not clear: ' . $items[1]['error'] );
	twd_sk_true( ! $items[2]['ok'] && false !== strpos( $items[2]['error'], 'not in section 2' ), $items[2]['error'] );
	twd_sk_true( ! $items[3]['ok'] && false !== strpos( $items[3]['error'], 'there is no section 9' ), $items[3]['error'] );
	twd_sk_true( ! $items[4]['ok'] && false !== strpos( $items[4]['error'], 'no page called' ), $items[4]['error'] );
	twd_sk_true( ! $items[5]['ok'], 'a change with nothing to do' );
	twd_sk_eq( $v, TWD_SK_Store::get_current_version_id( 301 ), 'plan writes nothing' );
	twd_sk_true( $plan['pages'][0]['ok'], 'the page with one good change can go ahead' );
	twd_sk_hasnt( 'html', implode( ',', array_keys( TWD_SK_SiteImport::public_plan( $plan )['pages'][0] ) ), 'the candidate stays on the server' );
	twd_sk_se_cleanup();
} );

twd_sk_test( 'site changes: several changes to one page are made on the page as exported (section numbers never drift), as one version; another page is untouched; a stale page is refused', function () {
	twd_sk_se_setup();
	$reply = TWD_SK_SiteImport::read( twd_sk_se_reply( array(
		array( 'page' => 'about-me', 'section' => 3, 'find' => 'one hundred pounds', 'replace' => 'ninety pounds' ),
		array( 'page' => 'about-me', 'section' => 2, 'html' => '<section class="twd-sk-text twd-sk-tone-base"><div class="twd-sk-inner"><h2 class="twd-sk-title">A softer way</h2><p>Two sections now.</p></div></section><section class="twd-sk-text twd-sk-tone-tint"><div class="twd-sk-inner"><h2 class="twd-sk-title">Extra</h2><p>Added.</p></div></section>' ),
		array( 'page' => 'about-me', 'section' => 1, 'find' => 'Welcome 301', 'replace' => 'Hello 301' ),
	) ) );
	$plan = TWD_SK_SiteImport::plan( $reply );
	twd_sk_true( $plan['pages'][0]['ok'], $plan['pages'][0]['error'] );
	$before_other = TWD_SK_Store::get_current( 302 );
	$versions_before = count( TWD_SK_Store::list_versions( 301 ) );
	$base = TWD_SK_Store::get_current_version_id( 301 );
	$res = TWD_SK_SiteImport::apply( $reply, array( 0, 1, 2 ), array( 301 => $base ), 1 );
	twd_sk_eq( 1, $res['done'], json_encode( $res ) );
	twd_sk_eq( 0, $res['failed'] );
	$now = TWD_SK_Store::get_current( 301 );
	twd_sk_has( 'ninety pounds', $now );
	twd_sk_has( 'A softer way', $now );
	twd_sk_has( 'Added.', $now );
	twd_sk_has( 'Hello 301', $now );
	twd_sk_hasnt( 'We work gently', $now, 'the replaced section is gone' );
	twd_sk_eq( $before_other, TWD_SK_Store::get_current( 302 ), 'the other page is untouched' );
	twd_sk_eq( $versions_before + 1, count( TWD_SK_Store::list_versions( 301 ) ), 'one version for the page' );
	// A page that moved on since the plan is refused.
	$again = TWD_SK_SiteImport::read( twd_sk_se_reply( array( array( 'page' => 'about-me', 'section' => 1, 'find' => 'Hello 301', 'replace' => 'Hi 301' ) ) ) );
	$stale = TWD_SK_SiteImport::apply( $again, array( 0 ), array( 301 => $base ), 1 );
	twd_sk_eq( 0, $stale['done'] );
	twd_sk_eq( 1, $stale['failed'] );
	twd_sk_has( 'changed', $stale['results'][0]['message'] );
	// Undo takes the whole change back.
	$undo = TWD_SK_Store::undo( 301 );
	twd_sk_true( ! is_wp_error( $undo ), is_wp_error( $undo ) ? $undo->get_error_message() : '' );
	twd_sk_has( 'one hundred pounds', TWD_SK_Store::get_current( 301 ) );
	twd_sk_se_cleanup();
} );

twd_sk_test( 'site changes: only the ticked changes are applied; a whole-page change cannot share a page with others; the safety notice is refused; scripts in a change are refused', function () {
	twd_sk_se_setup();
	$reply = TWD_SK_SiteImport::read( twd_sk_se_reply( array(
		array( 'page' => 'contact-me', 'section' => 3, 'find' => 'one hundred pounds', 'replace' => 'eighty pounds' ),
		array( 'page' => 'contact-me', 'section' => 2, 'find' => 'fifty minutes', 'replace' => 'sixty minutes' ),
	) ) );
	$base = TWD_SK_Store::get_current_version_id( 302 );
	$res  = TWD_SK_SiteImport::apply( $reply, array( 1 ), array( 302 => $base ), 1 );
	twd_sk_eq( 1, $res['done'], json_encode( $res ) );
	$now = TWD_SK_Store::get_current( 302 );
	twd_sk_has( 'sixty minutes', $now );
	twd_sk_has( 'one hundred pounds', $now, 'the unticked change was not made' );
	$mixed = TWD_SK_SiteImport::plan( TWD_SK_SiteImport::read( twd_sk_se_reply( array(
		array( 'page' => 'about-me', 'html' => twd_sk_se_html( 'rebuilt' ) ),
		array( 'page' => 'about-me', 'section' => 2, 'find' => 'gently', 'replace' => 'kindly' ),
	) ) ) );
	twd_sk_true( ! $mixed['items'][0]['ok'] && false !== strpos( $mixed['items'][0]['error'], 'cannot be combined' ), $mixed['items'][0]['error'] );
	$script = TWD_SK_SiteImport::plan( TWD_SK_SiteImport::read( twd_sk_se_reply( array(
		array( 'page' => 'about-me', 'section' => 2, 'html' => '<section class="twd-sk-text"><div class="twd-sk-inner"><script>alert(1)</script></div></section>' ),
	) ) ) );
	twd_sk_true( ! $script['items'][0]['ok'] && false !== strpos( $script['items'][0]['error'], 'script' ), $script['items'][0]['error'] );
	$notice = TWD_SK_Registry::get( 'notice' )['skeleton'];
	TWD_SK_Store::save( 301, twd_sk_se_html( 'n' ) . "\n" . $notice );
	$n = TWD_SK_SiteImport::plan( TWD_SK_SiteImport::read( twd_sk_se_reply( array(
		array( 'page' => 'about-me', 'section' => 4, 'html' => '<section class="twd-sk-text"><div class="twd-sk-inner"><p>x</p></div></section>' ),
	) ) ) );
	twd_sk_true( ! $n['items'][0]['ok'] && false !== strpos( $n['items'][0]['error'], 'safety notice' ), $n['items'][0]['error'] );
	twd_sk_se_cleanup();
} );

twd_sk_test( 'site changes: stylesheet rules are checked (bad rules are named and the page changes are checked without them); the footer HTML is replaced only when the footer is a saved HTML embed', function () {
	twd_sk_se_setup();
	$bad = TWD_SK_SiteImport::plan( TWD_SK_SiteImport::read( twd_sk_se_reply( array(
		array( 'page' => 'about-me', 'section' => 2, 'find' => 'gently', 'replace' => 'kindly' ),
	), array( 'css' => '.c-x { color: #ff0000; }' ) ) ) );
	twd_sk_true( ! $bad['css']['ok'] && '' !== $bad['css']['error'], 'a typed colour is refused' );
	twd_sk_true( $bad['items'][0]['ok'], 'the wording change itself is fine' );
	// Footer: not an embed yet.
	$none = TWD_SK_SiteImport::plan( TWD_SK_SiteImport::read( twd_sk_se_reply( array( array( 'footer_html' => '<footer class="x">New</footer>' ) ) ) ) );
	twd_sk_true( ! $none['items'][0]['ok'] && false !== strpos( $none['items'][0]['error'], 'not a saved HTML embed' ), $none['items'][0]['error'] );
	$saved = TWD_SK_Embed::save( array( 'label' => 'Footer', 'kind' => 'html', 'code' => '<footer class="x">Old footer</footer>' ), 0, 1 );
	twd_sk_true( ! is_wp_error( $saved ), is_wp_error( $saved ) ? $saved->get_error_message() : '' );
	$chosen = TWD_SK_Chrome::save_settings( array( 'footer_embed' => (int) $saved['id'] ) );
	twd_sk_true( ! is_wp_error( $chosen ), is_wp_error( $chosen ) ? $chosen->get_error_message() : '' );
	$reply = TWD_SK_SiteImport::read( twd_sk_se_reply( array( array( 'footer_html' => '<footer class="x">New footer</footer>', 'why' => 'Matches the notes.' ) ) ) );
	$plan  = TWD_SK_SiteImport::plan( $reply );
	twd_sk_true( $plan['items'][0]['ok'], $plan['items'][0]['error'] );
	twd_sk_eq( 'Old footer', $plan['items'][0]['before'] );
	twd_sk_eq( 'Old footer', TWD_SK_Embed::get( $saved['id'] )['code'] === '<footer class="x">Old footer</footer>' ? 'Old footer' : 'changed', 'plan writes nothing' );
	$res = TWD_SK_SiteImport::apply( $reply, array( 0 ), array(), 1 );
	twd_sk_eq( 1, $res['done'], json_encode( $res ) );
	twd_sk_has( 'New footer', TWD_SK_Embed::get( $saved['id'] )['code'] );
	TWD_SK_Chrome::save_settings( array( 'footer_embed' => 0 ) );
	TWD_SK_Embed::delete( $saved['id'] );
	twd_sk_se_cleanup();
} );

twd_sk_test( 'site export and changes: administrators only, every write through the store, no outside request, the routes exist outside safe mode only', function () {
	$rest = file_get_contents( ABSPATH . 'includes/class-twd-sk-rest.php' );
	foreach ( array( '/site/export-site', '/site/changes/plan', '/site/changes/apply' ) as $route ) {
		twd_sk_has( $route, $rest, $route );
	}
	twd_sk_true( 1 === preg_match( "#'/site/export-site'.*?can_read_site_post#s", $rest ) && 1 === preg_match( "#'/site/changes/apply'.*?can_manage_site#s", $rest ), 'permission callbacks' );
	twd_sk_true( 1 === preg_match( '/function can_read_site_post.*?manage_options/s', $rest ), 'administrators' );
	foreach ( array( 'class-twd-sk-siteexport.php', 'class-twd-sk-siteimport.php' ) as $file ) {
		$src = file_get_contents( ABSPATH . 'includes/' . $file );
		foreach ( array( 'wp_remote', 'curl_', 'update_post_meta', 'update_option', 'wpdb' ) as $bad ) {
			twd_sk_hasnt( $bad, $src, $file . ' ' . $bad );
		}
	}
} );

twd_sk_test( 'site tab js (0.17.0): Export for an AI downloads from the export route, Changes from an AI checks then applies the ticked ones, and the Footer HTML window only exists for a saved HTML footer', function () {
	$js = file_get_contents( ABSPATH . 'assets/twd-site-kit-editor-site.js' );
	twd_sk_has( "api('POST', '/site/export-site'", $js );
	twd_sk_has( "api('GET', '/site/export-site')", $js );
	twd_sk_has( "api('POST', '/site/changes/plan'", $js );
	twd_sk_has( "api('POST', '/site/changes/apply'", $js );
	twd_sk_has( 'Nothing is saved until you press Apply.', $js, 'the plan says so' );
	twd_sk_has( 'Untick any you do not want.', $js );
	twd_sk_has( 'bases[p.page_id] = p.base', $js, 'a page that moved on is refused by version' );
	twd_sk_has( 'atob(', $js );
	twd_sk_has( 'createObjectURL', $js );
	twd_sk_has( 'function buildFooterHtmlSection(', $js );
	twd_sk_true( 1 === preg_match( '/function buildFooterHtmlSection\(panel\) \{\s*if \( ?!ED\.cfg \|\| !ED\.cfg\.footerEmbed/', str_replace( array( "\r\n" ), "\n", $js ) ) || false !== strpos( $js, "if (!ED.cfg || !ED.cfg.footerEmbed || !ED.cfg.footerEmbed.id)" ), 'the window is built only when the footer is an embed' );
	twd_sk_has( "api('POST', '/site/embeds', { embed_id: FH.id, label: FH.label, kind: 'html', code: code })", $js, 'saved through the embeds route' );
	twd_sk_has( "field: 'twd-sk-fh-code'", $js, 'the pill over the embed footer opens it' );
	twd_sk_has( 'Copy prompt and code for an AI', $js );
	twd_sk_has( 'Put back the footer before this save', $js );
	twd_sk_hasnt( 'srcdoc', $js, 'no frame is fed code' );
	twd_sk_hasnt( 'innerHTML', $js );
	$ed = file_get_contents( ABSPATH . 'includes/class-twd-sk-editor.php' );
	twd_sk_has( "'footerEmbed'", $ed );
	twd_sk_true( 1 === preg_match( "/'footerEmbed'[^\n]*can_save_kind\( 'html' \)[^\n]*footer_embed\(\)/", $ed ), 'only for someone who may save HTML, and only when the footer is an embed' );
	twd_sk_hasnt( "['code']", substr( $ed, strpos( $ed, "'footerEmbed'" ), 400 ), 'never the code' );
} );

twd_sk_test( 'site export: the kit rules the pages use come with it, a draft has no address, and the README says what an AI can and cannot judge', function () {
	twd_sk_se_setup();
	$GLOBALS['twd_stub']['posts'][302]->post_status = 'draft';
	$set = TWD_SK_SiteExport::files( array( 'scope' => 'all' ) );
	twd_sk_true( isset( $set['files']['site/kit-stylesheet-used.css'] ), 'the kit rules file' );
	$css = $set['files']['site/kit-stylesheet-used.css'];
	twd_sk_has( '.twd-sk-page .twd-sk-hero', $css, 'the hero rules the pages use' );
	twd_sk_hasnt( '.twd-sk-pricing', $css, 'a section no page uses is left out' );
	twd_sk_has( 'var(--twd-site-', $css, 'tokens stay as names' );
	twd_sk_hasnt( '/*', substr( $css, strpos( $css, '*/' ) + 2 ), 'no other comments' );
	$index = json_decode( $set['files']['pages/_index.json'], true );
	$by = array();
	foreach ( $index['pages'] as $p ) {
		$by[ $p['page'] ] = $p;
	}
	twd_sk_eq( '', $by['contact-me']['address'], 'a draft has no address' );
	twd_sk_eq( 'draft', $by['contact-me']['status'] );
	$readme = $set['files']['README-FOR-AI.md'];
	twd_sk_has( 'What you can and cannot judge', $readme );
	twd_sk_has( 'Pages marked `draft`', $readme );
	twd_sk_has( 'ONE large custom section', $readme );
	twd_sk_has( 'kit-stylesheet-used.css', $readme );
	$chrome = TWD_SK_SiteExport::files( array( 'scope' => 'chrome' ) );
	twd_sk_true( ! isset( $chrome['files']['site/kit-stylesheet-used.css'] ), 'no pages, no kit rules' );
	$GLOBALS['twd_stub']['posts'][302]->post_status = 'publish';
	twd_sk_se_cleanup();
} );

twd_sk_test( 'site export (0.17.2): the index carries each page\'s search title and description, the kit guide covers only the sections used, and a harmless worked example comes from the site\'s own words', function () {
	twd_sk_se_setup();
	update_post_meta( 301, TWD_SK_Seo::K_TITLE, 'About me | The Practice' );
	update_post_meta( 301, TWD_SK_Seo::K_DESC, 'A short description of the page.' );
	$set   = TWD_SK_SiteExport::files( array( 'scope' => 'all' ) );
	$index = json_decode( $set['files']['pages/_index.json'], true );
	$by    = array();
	foreach ( $index['pages'] as $p ) {
		$by[ $p['page'] ] = $p;
	}
	twd_sk_eq( 'About me | The Practice', $by['about-me']['seo_title'] );
	twd_sk_eq( 'A short description of the page.', $by['about-me']['seo_description'] );
	twd_sk_eq( '', $by['contact-me']['seo_title'], 'a page with none says so with an empty string' );
	// The guide: only hero and text are used on these pages.
	twd_sk_true( isset( $set['files']['site/kit-sections-guide.md'] ), 'the guide' );
	$guide = $set['files']['site/kit-sections-guide.md'];
	twd_sk_has( '(hero)', $guide );
	twd_sk_has( '(text)', $guide );
	twd_sk_hasnt( '(pricing)', $guide, 'a section no page uses is left out' );
	twd_sk_hasnt( '(embed)', $guide );
	// The worked example is readable by the importer and changes nothing.
	twd_sk_true( isset( $set['files']['reply-example.json'] ), 'the example' );
	$read = TWD_SK_SiteImport::read( $set['files']['reply-example.json'] );
	twd_sk_true( is_array( $read ), is_wp_error( $read ) ? $read->get_error_message() : '' );
	twd_sk_eq( 1, count( $read['changes'] ) );
	twd_sk_eq( $read['changes'][0]['find'], $read['changes'][0]['replace'], 'the new words equal the old' );
	$plan = TWD_SK_SiteImport::plan( $read );
	twd_sk_true( $plan['items'][0]['ok'], $plan['items'][0]['error'] );
	twd_sk_true( $plan['pages'][0]['same'], 'applying it leaves the page as it is' );
	twd_sk_has( 'EXAMPLE ONLY', $set['files']['reply-example.json'] );
	// README: the new sections.
	$readme = $set['files']['README-FOR-AI.md'];
	foreach ( array( 'Never touch these', 'Before you reply, check', 'LEAVE ALONE', 'at most about 25 changes', 'needs a person to look', 'reply-example.json', 'kit-sections-guide.md', 'seo_title' ) as $needle ) {
		twd_sk_has( $needle, $readme, $needle );
	}
	// Header and footer only: no guide (no pages), no example.
	$chrome = TWD_SK_SiteExport::files( array( 'scope' => 'chrome' ) );
	twd_sk_true( ! isset( $chrome['files']['site/kit-sections-guide.md'] ) && ! isset( $chrome['files']['reply-example.json'] ), 'nothing about pages' );
	twd_sk_se_cleanup();
} );

twd_sk_test( 'site tab js (0.17.2): Copy the problems for the AI appears after a check that refused something and puts the reasons in a ready-to-paste message', function () {
	$js = file_get_contents( ABSPATH . 'assets/twd-site-kit-editor-site.js' );
	twd_sk_has( 'Copy the problems for the AI', $js );
	twd_sk_has( 'function problemsText(plan)', $js );
	twd_sk_has( 'could not use these changes', $js );
	twd_sk_has( 'Reply again with the complete JSON object', $js );
	twd_sk_has( "show(xui.problemsBtn, problemsText(plan) !== '')", $js, 'only when something was refused' );
	twd_sk_hasnt( 'innerHTML', $js );
} );

if ( ! function_exists( 'wp_upload_dir' ) ) {
	function wp_upload_dir() {
		return array( 'baseurl' => 'https://example.test/wp-content/uploads', 'basedir' => sys_get_temp_dir() . '/twdsk-uploads-test' );
	}
}

twd_sk_test( 'site export (0.17.3): the picture list says where each picture is used, its description, size and whether the file is there, and names problems; small copies come only when asked', function () {
	$dir = sys_get_temp_dir() . '/twdsk-uploads-test/2026/11';
	@mkdir( $dir, 0777, true );
	$png = base64_decode( 'iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mNkYPhfDwAChwGA60e6kgAAAABJRU5ErkJggg==' );
	file_put_contents( $dir . '/here.png', $png );
	twd_sk_se_setup();
	$html = TWD_SK_Store::get_current( 301 )
		. "\n" . '<section class="twd-sk-text twd-sk-tone-base"><div class="twd-sk-inner">'
		. '<img src="https://example.test/wp-content/uploads/2026/11/here.png" alt="A white square" width="1" height="1">'
		. '<img src="https://example.test/wp-content/uploads/2026/11/gone.png" alt="">'
		. '<img src="https://example.org/x.jpg" alt="Elsewhere">'
		. '<img src="/wp-content/uploads/2026/11/here.png" alt="A white square">'
		. '</div></section>';
	TWD_SK_Store::save( 301, $html );
	$set  = TWD_SK_SiteExport::files( array( 'scope' => 'all' ) );
	$pics = json_decode( $set['files']['site/pictures.json'], true );
	$by   = array();
	foreach ( $pics['pictures'] as $p ) {
		$by[ $p['file'] ] = $p;
	}
	twd_sk_true( isset( $by['here.png'], $by['gone.png'], $by['x.jpg'] ), 'every picture is listed once: ' . implode( ',', array_keys( $by ) ) );
	twd_sk_eq( 3, count( $pics['pictures'] ), 'the same picture used twice is one entry' );
	twd_sk_true( $by['here.png']['file_found'] && $by['here.png']['in_media_library'], 'found in the media library' );
	twd_sk_eq( '1x1', $by['here.png']['pixels'] );
	twd_sk_eq( 'A white square', $by['here.png']['alt'] );
	twd_sk_true( false !== strpos( implode( ' ', $by['here.png']['used_in'] ), 'about-me section 4' ), 'where it is used' );
	twd_sk_eq( array(), $by['here.png']['issues'], 'no problem for a good picture' );
	twd_sk_true( false === $by['gone.png']['file_found'] && false !== strpos( implode( ' ', $by['gone.png']['issues'] ), 'not on the server' ), 'a missing file is named' );
	twd_sk_true( false !== strpos( implode( ' ', $by['gone.png']['issues'] ), 'Empty description' ), 'an empty description is named' );
	twd_sk_true( false === $by['x.jpg']['in_media_library'] && null === $by['x.jpg']['file_found'], 'a picture from another address cannot be checked' );
	twd_sk_true( ! isset( $set['files']['pictures/here.png'] ), 'no copies unless asked' );
	$with = TWD_SK_SiteExport::files( array( 'scope' => 'all', 'pictures' => true ) );
	twd_sk_true( isset( $with['files']['pictures/here.png'] ) && strlen( $with['files']['pictures/here.png'] ) > 0, 'a small copy of a picture that exists' );
	twd_sk_true( ! isset( $with['files']['pictures/gone.png'] ), 'none for a missing file' );
	twd_sk_has( 'pictures/', TWD_SK_SiteExport::readme( 'all', array(), false ) );
	$md = TWD_SK_SiteExport::as_markdown( $with['files'] );
	twd_sk_hasnt( '=== FILE: pictures/', $md, 'binary copies stay out of the markdown fallback' );
	twd_sk_se_cleanup();
	@unlink( $dir . '/here.png' );
} );

twd_sk_test( 'site export (0.17.3): seo.json holds the site-wide search settings, each page carries its social picture and noindex, and site-checks.md holds the launch check', function () {
	twd_sk_se_setup();
	update_post_meta( 302, TWD_SK_Seo::K_NOINDEX, '1' );
	$set = TWD_SK_SiteExport::files( array( 'scope' => 'all' ) );
	$seo = json_decode( $set['files']['site/seo.json'], true );
	foreach ( array( 'search_tags_printed_by', 'search_engines_allowed', 'search_engines_note', 'site_name', 'default_social_picture', 'structured_data_on_the_front_page', 'per_page' ) as $k ) {
		twd_sk_true( array_key_exists( $k, $seo ), 'seo.json has ' . $k );
	}
	twd_sk_true( is_bool( $seo['search_engines_allowed'] ) );
	$index = json_decode( $set['files']['pages/_index.json'], true );
	$by    = array();
	foreach ( $index['pages'] as $p ) {
		$by[ $p['page'] ] = $p;
	}
	twd_sk_true( true === $by['contact-me']['seo_noindex'] && false === $by['about-me']['seo_noindex'], 'noindex per page' );
	twd_sk_true( array_key_exists( 'seo_social_picture', $by['about-me'] ), 'social picture per page' );
	$checks = $set['files']['site/site-checks.md'];
	twd_sk_has( '# What the site\'s own checks say', $checks );
	twd_sk_true( 1 === preg_match( '/^- (FIX|LOOK|OK): /m', $checks ), 'items with a status' );
	// One page: no site-wide search settings file, but the page's own checks and pictures stay.
	$one = TWD_SK_SiteExport::files( array( 'scope' => 'page', 'page' => 301 ) );
	twd_sk_true( ! isset( $one['files']['site/seo.json'] ) && isset( $one['files']['site/pictures.json'] ) && isset( $one['files']['site/site-checks.md'] ), 'a page export' );
	// Header and footer only: no pictures list, no page files.
	$chrome = TWD_SK_SiteExport::files( array( 'scope' => 'chrome' ) );
	twd_sk_true( ! isset( $chrome['files']['site/pictures.json'] ) && isset( $chrome['files']['site/seo.json'] ), 'header and footer scope' );
	foreach ( array( 'site/seo.json', 'site/pictures.json', 'site/site-checks.md', 'seo_social_picture', 'seo_noindex' ) as $needle ) {
		twd_sk_has( $needle, $set['files']['README-FOR-AI.md'], 'README names ' . $needle );
	}
	update_post_meta( 302, TWD_SK_Seo::K_NOINDEX, '' );
	twd_sk_se_cleanup();
} );

twd_sk_test( 'site tab js and routes (0.17.3): the pictures tick is sent to the export route and the command line has --pictures', function () {
	$js = file_get_contents( ABSPATH . 'assets/twd-site-kit-editor-site.js' );
	twd_sk_has( 'pictures: !!xui.pics.checked', $js );
	twd_sk_has( 'Include small copies of the pictures', $js );
	$rest = file_get_contents( ABSPATH . 'includes/class-twd-sk-rest.php' );
	twd_sk_has( "'pictures' => self::truthy( \$request->get_param( 'pictures' ) )", $rest );
	$cli = file_get_contents( ABSPATH . 'includes/class-twd-sk-cli.php' );
	twd_sk_has( '[--pictures]', $cli );
} );
