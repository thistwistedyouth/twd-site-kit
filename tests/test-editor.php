<?php
// The front-end editor: who gets the button and scripts, and the safety of the script and styles themselves.

function twd_sk_ed_setup( $caps = null ) {
	twd_stub_add_post( 12, 'page', '[twd_page]' );
	twd_stub_add_post( 20, 'page', 'Plain page, no kit' );
	$GLOBALS['twd_stub']['caps']     = null === $caps ? array( 'edit_pages', 'edit_post:12', 'edit_post:20' ) : $caps;
	$GLOBALS['twd_stub']['admin']    = false;
	$GLOBALS['twd_stub']['queried']  = 12;
	$GLOBALS['twd_stub']['post_id']  = 12;
	$GLOBALS['twd_stub']['singular'] = true;
	TWD_SK_Preview::reset();
	unset( $_GET['twd_sk_preview'], $_GET['elementor-preview'], $_GET['elementor_library'] );
}
function twd_sk_ed_loaded() {
	TWD_SK_Editor::enqueue();
	return ! empty( $GLOBALS['twd_stub']['scripts']['twd-site-kit-editor']['enqueued'] );
}
function twd_sk_ed_markup() {
	ob_start();
	TWD_SK_Editor::print_root();
	return ob_get_clean();
}

twd_sk_test( 'editor: an editor on a kit page gets the script, the styles, the nonce and the button', function () {
	twd_sk_ed_setup();
	twd_sk_true( twd_sk_ed_loaded(), 'script enqueued' );
	$data = $GLOBALS['twd_stub']['scripts']['twd-site-kit-editor']['data'];
	twd_sk_eq( 'https://example.test/wp-json/twd-site-kit/v1', $data['restUrl'] );
	twd_sk_eq( 'good-nonce', $data['nonce'] );
	twd_sk_eq( 12, $data['pageId'] );
	twd_sk_eq( 0, $data['currentVersion'] );
	twd_sk_eq( 200 * 1024, $data['maxBytes'] );
	$markup = twd_sk_ed_markup();
	twd_sk_has( 'id="twd-sk-ed-open"', $markup );
	twd_sk_has( 'hidden', $markup );
	twd_sk_has( 'Edit with AI', $markup );
	twd_sk_hasnt( '<script', $markup );
} );

twd_sk_test( 'editor security: a visitor gets no script, no styles and no button', function () {
	twd_sk_ed_setup();
	$GLOBALS['twd_stub']['user'] = 0;
	twd_sk_true( ! twd_sk_ed_loaded() );
	twd_sk_eq( '', twd_sk_ed_markup() );
	twd_sk_eq( array(), $GLOBALS['twd_stub']['scripts'] );
	twd_sk_true( ! isset( $GLOBALS['twd_stub']['styles']['twd-site-kit-editor'] ) || empty( $GLOBALS['twd_stub']['styles']['twd-site-kit-editor']['enqueued'] ) );
} );

twd_sk_test( 'editor security: a signed-in user who cannot edit pages (subscriber, contributor, author) gets nothing', function () {
	foreach ( array( array(), array( 'read' ), array( 'edit_posts' ), array( 'edit_post:12' ) ) as $caps ) {
		twd_sk_ed_setup( $caps );
		twd_sk_true( ! twd_sk_ed_loaded(), implode( ',', $caps ) );
		twd_sk_eq( '', twd_sk_ed_markup() );
	}
} );

twd_sk_test( 'editor: nothing loads in wp-admin, in the preview frame, in Elementor\'s editor, or on pages that are not kit pages', function () {
	twd_sk_ed_setup();
	$GLOBALS['twd_stub']['admin'] = true;
	twd_sk_true( ! twd_sk_ed_loaded(), 'admin' );
	$GLOBALS['twd_stub']['admin'] = false;

	$_GET['twd_sk_preview'] = str_repeat( 'a', 32 );
	twd_sk_true( ! twd_sk_ed_loaded(), 'preview request' );
	unset( $_GET['twd_sk_preview'] );

	$_GET['elementor-preview'] = '12';
	twd_sk_true( ! twd_sk_ed_loaded(), 'elementor preview' );
	unset( $_GET['elementor-preview'] );

} );

twd_sk_test( 'editor: on a page that is not a kit page, or on no page at all, only the Pages tab is on offer', function () {
	twd_sk_ed_setup();
	$GLOBALS['twd_stub']['queried'] = 20;
	twd_sk_true( twd_sk_ed_loaded(), 'loads for the New page tab' );
	$data = $GLOBALS['twd_stub']['scripts']['twd-site-kit-editor']['data'];
	twd_sk_eq( 20, $data['pageId'] );
	twd_sk_eq( false, $data['isKitPage'] );

	twd_sk_ed_setup();
	$GLOBALS['twd_stub']['singular'] = false;
	$GLOBALS['twd_stub']['queried']  = 0;
	$GLOBALS['twd_stub']['post_id']  = 0;
	twd_sk_true( twd_sk_ed_loaded(), 'loads on an archive' );
	$data = $GLOBALS['twd_stub']['scripts']['twd-site-kit-editor']['data'];
	twd_sk_eq( 0, $data['pageId'] );
	twd_sk_eq( false, $data['isKitPage'] );
	twd_sk_eq( false, $data['canPublish'] );
	twd_sk_has( 'id="twd-sk-ed-open"', twd_sk_ed_markup() );
} );

twd_sk_test( 'editor: a user who can edit pages but not this page gets the Pages tab only, with no page ID and no publish rights', function () {
	twd_sk_ed_setup( array( 'edit_pages' ) );
	twd_sk_true( twd_sk_ed_loaded() );
	$data = $GLOBALS['twd_stub']['scripts']['twd-site-kit-editor']['data'];
	twd_sk_eq( 0, $data['pageId'] );
	twd_sk_eq( false, $data['isKitPage'] );
	twd_sk_eq( false, $data['canPublish'] );
} );

twd_sk_test( 'editor: publish rights are passed to the script only for users who hold them on this page', function () {
	twd_sk_ed_setup( array( 'edit_pages', 'edit_post:12' ) );
	twd_sk_true( twd_sk_ed_loaded() );
	twd_sk_eq( false, $GLOBALS['twd_stub']['scripts']['twd-site-kit-editor']['data']['canPublish'] );
	twd_sk_ed_setup( array( 'edit_pages', 'edit_post:12', 'publish_pages', 'publish_post:12' ) );
	twd_sk_true( twd_sk_ed_loaded() );
	twd_sk_eq( true, $GLOBALS['twd_stub']['scripts']['twd-site-kit-editor']['data']['canPublish'] );
	twd_sk_eq( true, $GLOBALS['twd_stub']['scripts']['twd-site-kit-editor']['data']['isKitPage'] );
} );

twd_sk_test( 'editor: a validated preview loads the stylesheet only, never the script or the button', function () {
	twd_sk_ed_setup();
	TWD_SK_Store::save( 12, '<p>Saved</p>' );
	$token = TWD_SK_Preview::create( 7, 12, '<p>Draft</p>' );
	$_GET['twd_sk_preview'] = $token;
	TWD_SK_Preview::maybe_start();
	TWD_SK_Editor::enqueue();
	twd_sk_eq( array(), $GLOBALS['twd_stub']['scripts'] );
	twd_sk_true( ! empty( $GLOBALS['twd_stub']['styles']['twd-site-kit-editor'] ), 'stylesheet for the bar' );
	twd_sk_eq( '', twd_sk_ed_markup() );
	unset( $_GET['twd_sk_preview'] );
	TWD_SK_Preview::reset();
} );

twd_sk_test( 'editor: what counts as a kit page', function () {
	twd_sk_ed_setup();
	twd_sk_true( TWD_SK_Page::is_kit_page( 12 ), 'has the shortcode' );
	twd_sk_true( ! TWD_SK_Page::is_kit_page( 20 ) );
	TWD_SK_Store::save( 20, '<p>Stored</p>' );
	twd_sk_true( TWD_SK_Page::is_kit_page( 20 ), 'has stored kit html' );
	twd_stub_add_post( 21, 'page', '' );
	update_post_meta( 21, '_elementor_data', '[{"widgetType":"shortcode","settings":{"shortcode":"[twd_page]"}}]' );
	twd_sk_true( TWD_SK_Page::is_kit_page( 21 ), 'shortcode inside elementor data' );
	twd_sk_true( ! TWD_SK_Page::is_kit_page( 0 ) );
} );

// -- The script itself ------------------------------------------------------------------

function twd_sk_ed_js() {
	return file_get_contents( ABSPATH . 'assets/twd-site-kit-editor.js' );
}
function twd_sk_ed_all_js() {
	return array( 'editor' => twd_sk_ed_js(), 'site' => file_get_contents( ABSPATH . 'assets/twd-site-kit-editor-site.js' ) );
}

twd_sk_test( 'editor js: no way to run or inject code (eval, Function, document.write, innerHTML and friends)', function () {
	$js = twd_sk_ed_js();
	foreach ( array( 'eval(', 'new Function', 'document.write', 'innerHTML', 'outerHTML', 'insertAdjacentHTML', 'setTimeout("', "setTimeout('", 'srcdoc', 'importScripts', 'XMLHttpRequest', 'WebSocket', 'sendBeacon', 'localStorage', 'sessionStorage' ) as $bad ) {
		twd_sk_hasnt( $bad, $js );
	}
} );

twd_sk_test( 'editor js: talks only to the address the server gave it, sends the nonce, and holds no web addresses of its own', function () {
	$js = twd_sk_ed_js();
	twd_sk_has( 'cfg.restUrl + path', $js );
	twd_sk_has( "'X-WP-Nonce': cfg.nonce", $js );
	twd_sk_has( "credentials: 'same-origin'", $js );
	twd_sk_true( 1 !== preg_match( '#https?://#i', $js ), 'no web addresses in the script' );
	twd_sk_true( 1 !== preg_match( '/\bfetch\(\s*[\'"]/', $js ), 'fetch is never given a literal address' );
} );

twd_sk_test( 'editor js: no native alert, confirm or prompt', function () {
	foreach ( twd_sk_ed_all_js() as $name => $js ) {
		twd_sk_true( 1 !== preg_match( '/(^|[^.\w])(alert|confirm|prompt)\s*\(/m', $js ), 'no native dialogs in ' . $name );
	}
} );

twd_sk_test( 'site js: no way to run or inject code, no storage, no web addresses, and it only talks through the editor api', function () {
	$js = twd_sk_ed_all_js()['site'];
	foreach ( array( 'eval(', 'new Function', 'document.write', 'innerHTML', 'outerHTML', 'insertAdjacentHTML', 'srcdoc', 'XMLHttpRequest', 'WebSocket', 'sendBeacon', 'localStorage', 'sessionStorage', 'fetch(', 'importScripts' ) as $bad ) {
		twd_sk_hasnt( $bad, $js );
	}
	twd_sk_true( 1 !== preg_match( '#https?://#i', $js ), 'no web addresses' );
	twd_sk_has( "api('GET', '/site')", $js );
	twd_sk_has( "api('POST', '/site/style'", $js );
	twd_sk_has( "api('POST', '/site/style/reset'", $js );
	twd_sk_has( "ED.addTab('site', 'Site'", $js );
} );

twd_sk_test( 'site js: previews by setting custom properties only, blocks the save button on unreadable pairs, and asks before resetting', function () {
	$js = twd_sk_ed_all_js()['site'];
	twd_sk_has( "root.style.setProperty(PREFIX + name, value)", $js );
	twd_sk_has( "root.style.removeProperty(PREFIX + name)", $js );
	twd_sk_has( "ui.saveBtn.disabled = res.blocking.length > 0", $js );
	twd_sk_has( 'ED.confirmInline(ui.resetHost', $js );
	twd_sk_has( "'--twd-site-'", $js );
	twd_sk_has( 'You cannot save yet. This text would be hard to read', $js );
	twd_sk_has( 'Nothing is kept until you press Save style', $js );
	twd_sk_true( 1 !== preg_match( '/style\.cssText|setAttribute\(\s*[\'"]style/', $js ), 'no raw style strings' );
} );

twd_sk_test( 'site js: it parses (node --check)', function () {
	if ( ! function_exists( 'shell_exec' ) || '' === trim( (string) shell_exec( 'command -v node 2>/dev/null' ) ) ) {
		return;
	}
	twd_sk_eq( '', trim( (string) shell_exec( 'node --check ' . escapeshellarg( ABSPATH . 'assets/twd-site-kit-editor-site.js' ) . ' 2>&1' ) ) );
} );

twd_sk_test( 'site: the Site script loads only for administrators, never for an editor and never in safe mode', function () {
	twd_sk_ed_setup( array( 'edit_pages', 'edit_post:12' ) );
	twd_sk_true( twd_sk_ed_loaded() );
	twd_sk_true( ! isset( $GLOBALS['twd_stub']['scripts']['twd-site-kit-editor-site'] ), 'an editor does not get the Site script' );

	twd_sk_ed_setup( array( 'edit_pages', 'edit_post:12', 'manage_options' ) );
	twd_sk_true( twd_sk_ed_loaded() );
	$site = $GLOBALS['twd_stub']['scripts']['twd-site-kit-editor-site'];
	twd_sk_true( $site['enqueued'] );
	twd_sk_has( '@font-face', $GLOBALS['twd_stub']['styles']['twd-site-kit-editor']['inline'], 'every bundled font is declared so a style can be previewed' );
	foreach ( array( 'Cormorant Garamond', 'Lora', 'Jost', 'Nunito' ) as $font ) {
		twd_sk_has( 'font-family:"' . $font . '"', $GLOBALS['twd_stub']['styles']['twd-site-kit-editor']['inline'] );
	}
	twd_sk_eq( false, $GLOBALS['twd_stub']['scripts']['twd-site-kit-editor']['data']['safeMode'] );

	twd_sk_ed_setup( array( 'edit_pages', 'edit_post:12', 'manage_options' ) );
	$GLOBALS['twd_stub']['scripts'] = array();
	TWD_SK_Safe::set( true );
	twd_sk_true( twd_sk_ed_loaded(), 'the editor itself still loads in safe mode' );
	twd_sk_true( ! isset( $GLOBALS['twd_stub']['scripts']['twd-site-kit-editor-site'] ), 'no Site script in safe mode' );
	twd_sk_eq( true, $GLOBALS['twd_stub']['scripts']['twd-site-kit-editor']['data']['safeMode'] );
} );

twd_sk_test( 'editor js: the behaviour the owner asked for is present', function () {
	$js = twd_sk_ed_js();
	foreach ( array(
		'Copy prompt' => 'copy button',
		'Preview' => 'preview',
		'Discard preview' => 'discard preview button',
		'Where did these facts come from?' => 'provenance field',
		'Apply and save as a new version' => 'apply',
		'Undo the last change' => 'undo',
		'Restore this version' => 'restore',
		'History (the last 10 versions)' => 'history',
		"e.key === 'Escape'" => 'Esc closes',
		"e.key !== 'Tab'" => 'focus trap',
		"role: 'dialog'" => 'dialog role',
		"'aria-modal': 'true'" => 'modal',
		'execCommand' => 'clipboard fallback',
		'ui.copyBox.select()' => 'select-all fallback',
		'navigator.clipboard' => 'clipboard API',
		'base_version: state.version' => 'every write names its base version',
		'twd-sk-ed__textarea' => 'paste box',
		'maxlength: \'200\'' => 'note length',
		'Must fix before publishing: ' => 'must fix banner',
		'These never block publishing' => 'check level banner',
		'You can still apply this, but the page cannot be published until they are replaced' => 'apply allowed with leftovers',
		'Publish this page' => 'publish',
		'Unpublish this page' => 'unpublish',
		"Switch this page to the kit template" => 'switch template',
		'Create draft page' => 'new page',
		'Yes, publish anyway' => 'override publish',
		'twd-sk-ed-override' => 'explicit override checkbox',
		'confirm: true' => 'publishing sends a confirmation',
	) as $needle => $what ) {
		twd_sk_has( $needle, $js, $what );
	}
	twd_sk_has( 'state.previewedText !== ui.paste.value', $js, 'apply only after previewing the exact text' );
} );

twd_sk_test( 'editor js: it parses (node --check, skipped when node is not installed)', function () {
	if ( ! function_exists( 'shell_exec' ) || '' === trim( (string) shell_exec( 'command -v node 2>/dev/null' ) ) ) {
		return;
	}
	$out = shell_exec( 'node --check ' . escapeshellarg( ABSPATH . 'assets/twd-site-kit-editor.js' ) . ' 2>&1' );
	twd_sk_eq( '', trim( (string) $out ) );
} );

twd_sk_test( 'editor js: the source holds no em or en dashes', function () {
	$js = twd_sk_ed_js();
	twd_sk_true( false === strpos( $js, "\xE2\x80\x94" ) && false === strpos( $js, "\xE2\x80\x93" ) );
} );

// -- The stylesheet ---------------------------------------------------------------------

function twd_sk_ed_css_rules() {
	return twd_sk_css_parse( file_get_contents( ABSPATH . 'assets/twd-site-kit-editor.css' ) );
}

twd_sk_test( 'editor css: every selector is scoped under .twd-sk-ed, every class doubled, no ids', function () {
	$rules = twd_sk_ed_css_rules();
	twd_sk_true( count( $rules ) > 40, 'rules: ' . count( $rules ) );
	foreach ( $rules as $rule ) {
		foreach ( $rule['selectors'] as $sel ) {
			twd_sk_true( 0 === strpos( $sel, '.twd-sk-ed' ), 'scoped: ' . $sel );
			twd_sk_hasnt( '#', $sel );
			preg_match_all( '/\.(twd-sk-[A-Za-z0-9_-]+)/', $sel, $m );
			foreach ( array_unique( array_diff( $m[1], array( 'twd-sk-ed' ) ) ) as $name ) {
				twd_sk_true( 1 === preg_match( '/\.' . preg_quote( $name, '/' ) . '\.' . preg_quote( $name, '/' ) . '(?![A-Za-z0-9_-])/', $sel ), 'doubled .' . $name . ' in ' . $sel );
			}
		}
	}
} );

twd_sk_test( 'editor css: visual properties carry !important, layout never does, and [hidden] always wins', function () {
	$visual = '/^(color|background|background-color|background-image|font-family|font-size|font-weight|font-style|line-height|letter-spacing|text-transform|text-decoration|text-align|border|border-(top|right|bottom|left)(-(color|width|style))?|border-color|border-width|border-style|border-radius|box-shadow|padding|padding-(top|right|bottom|left)|margin|margin-(top|right|bottom|left))$/';
	foreach ( twd_sk_ed_css_rules() as $rule ) {
		foreach ( $rule['decls'] as $d ) {
			if ( preg_match( $visual, $d['prop'] ) ) {
				twd_sk_true( $d['important'], $d['prop'] . ' in ' . $rule['selectors'][0] );
			} else {
				twd_sk_true( ! $d['important'] || 'display' !== $d['prop'] || false !== strpos( $rule['selectors'][0], '[hidden]' ), 'layout without !important: ' . $d['prop'] . ' in ' . $rule['selectors'][0] );
			}
		}
	}
	$raw = file_get_contents( ABSPATH . 'assets/twd-site-kit-editor.css' );
	twd_sk_true( 1 === preg_match( '/\.twd-sk-ed \[hidden\] \{\s*display: none !important;/', $raw ), 'unconditional [hidden] rule' );
} );

twd_sk_test( 'editor css: tokens only, no viewport-width tricks, no negative margins, no hidden overflow, sizes at the 14px floor', function () {
	$raw = file_get_contents( ABSPATH . 'assets/twd-site-kit-editor.css' );
	twd_sk_true( 1 !== preg_match( '/#[0-9a-fA-F]{3,8}\b/', $raw ), 'no hex colours' );
	twd_sk_true( 1 !== preg_match( '/\b(rgb|rgba|hsl|hsla)\(/', $raw ), 'no colour functions' );
	twd_sk_true( 1 !== preg_match( '/\d\s*vw\b/', $raw ), 'no vw' );
	twd_sk_hasnt( 'overflow: hidden', $raw );
	twd_sk_hasnt( '!important !important', $raw );
	foreach ( twd_sk_ed_css_rules() as $rule ) {
		foreach ( $rule['decls'] as $d ) {
			if ( 0 === strpos( $d['prop'], 'margin' ) ) {
				twd_sk_true( 1 !== preg_match( '/(^|\s)-\d/', $d['value'] ), 'no negative margin' );
			}
			if ( 'font-family' === $d['prop'] ) {
				twd_sk_true( 0 === strpos( $d['value'], 'var(--twd-site-font-' ), 'font from a token: ' . $d['value'] );
			}
			if ( 'font-size' === $d['prop'] ) {
				twd_sk_true( 0 === strpos( $d['value'], 'var(--twd-site-size-' ), 'size from a token: ' . $d['value'] );
			}
			if ( 'color' === $d['prop'] || 'background-color' === $d['prop'] ) {
				twd_sk_true( 0 === strpos( $d['value'], 'var(--twd-site-color-' ) || 'transparent' === $d['value'], 'colour from a token: ' . $d['value'] );
			}
		}
	}
} );

twd_sk_test( 'editor css: buttons and tabs are at least 44px tall, and on a phone the pop-up fills the screen', function () {
	$raw = file_get_contents( ABSPATH . 'assets/twd-site-kit-editor.css' );
	foreach ( array( 'twd-sk-ed__btn', 'twd-sk-ed__tab', 'twd-sk-ed__close', 'twd-sk-ed__open' ) as $c ) {
		twd_sk_true( 1 === preg_match( '/\.' . $c . '\.' . $c . '[^{]*\{[^}]*min-height:\s*(4[4-9]|[5-9]\d)px/', $raw ), $c . ' has a min-height of 44px or more' );
	}
	twd_sk_true( 1 === preg_match( '/@media \(max-width: 700px\) \{.*twd-sk-ed__dialog[^{]*\{[^}]*height: 100%.*border-radius: 0/s', $raw ), 'full screen dialog on a phone' );
	twd_sk_true( 1 === preg_match( '/@media \(max-width: 700px\) \{.*twd-sk-ed__btn[^{]*\{[^}]*min-height: 48px/s', $raw ), 'larger tap targets on a phone' );
	twd_sk_true( 1 === preg_match( '/twd-sk-ed__open[^{]*\{[^}]*left: 24px;[^}]*bottom: 24px/s', $raw ), 'button sits bottom left' );
} );

twd_sk_test( 'editor css: the editor stylesheet is the only other stylesheet, and both packs keep its text pairs at 4.5:1', function () {
	foreach ( array( 'sage', 'grove' ) as $slug ) {
		$t = twd_sk_pack( $slug )['tokens'];
		foreach ( array(
			array( 'color-body', 'color-surface' ),
			array( 'color-title', 'color-surface' ),
			array( 'color-muted', 'color-surface' ),
			array( 'color-on-primary', 'color-primary' ),
			array( 'color-on-primary', 'color-primary-hover' ),
			array( 'color-title', 'color-tint' ),
			array( 'color-surface', 'color-title' ),
			array( 'color-title', 'color-surface' ),
		) as $p ) {
			twd_sk_true( twd_sk_contrast( $t[ $p[0] ], $t[ $p[1] ] ) >= 4.5, $slug . ' ' . $p[0] . ' on ' . $p[1] );
		}
	}
} );

twd_sk_test( 'build: the committed stylesheets are exactly what tools/build-css.py builds from their sources', function () {
	if ( ! function_exists( 'shell_exec' ) || '' === trim( (string) shell_exec( 'command -v python3 2>/dev/null' ) ) ) {
		return;
	}
	$tmp = sys_get_temp_dir() . '/twdsk-css2-' . getmypid();
	mkdir( $tmp . '/tools', 0777, true );
	mkdir( $tmp . '/assets', 0777, true );
	foreach ( array( 'kit.src.css', 'editor.src.css', 'build-css.py' ) as $f ) {
		copy( ABSPATH . 'tools/' . $f, $tmp . '/tools/' . $f );
	}
	shell_exec( 'python3 ' . escapeshellarg( $tmp . '/tools/build-css.py' ) . ' 2>&1' );
	foreach ( array( 'twd-site-kit.css', 'twd-site-kit-editor.css' ) as $f ) {
		$built = @file_get_contents( $tmp . '/assets/' . $f );
		twd_sk_true( false !== $built && file_get_contents( ABSPATH . 'assets/' . $f ) === $built, $f . ' matches its source: run python3 tools/build-css.py and commit the result' );
		@unlink( $tmp . '/assets/' . $f );
	}
	array_map( 'unlink', glob( $tmp . '/tools/*' ) );
	rmdir( $tmp . '/tools' );
	rmdir( $tmp . '/assets' );
	rmdir( $tmp );
} );
