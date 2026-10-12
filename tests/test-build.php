<?php
// The site setup (0.7.1): pictures, the build engine, its routes, the command and the setup page.

/** Pictures 31 to 35 in the media library; 36 is a file that is not an image. */
function twd_sk_build_media() {
	foreach ( array( 31, 32, 33, 34, 35 ) as $id ) {
		$GLOBALS['twd_stub']['attachments'][ $id ] = array( 'is_image' => true, 'url' => 'https://example.test/wp-content/uploads/pic-' . $id . '.jpg' );
	}
	$GLOBALS['twd_stub']['attachments'][36] = array( 'is_image' => false, 'url' => 'https://example.test/wp-content/uploads/file.pdf' );
	update_post_meta( 33, '_wp_attachment_image_alt', 'A van parked by the sea' );
}
function twd_sk_build_brief( $extra = array() ) {
	return json_encode( array_merge( array(
		'schema' => 'twd-site-brief/1',
		'site'   => array( 'name' => 'Example Hire', 'email' => 'hello@example.com' ),
		'facts'  => "WHO WE ARE\nA small hire company.",
		'pages'  => array(
			array( 'type' => 'home', 'title' => 'Home' ),
			array( 'type' => 'about', 'title' => 'About us' ),
			array( 'type' => 'service', 'title' => 'Weekend hire', 'topic' => 'Weekend hire', 'seo' => array( 'title' => 'Weekend hire', 'description' => 'Hire for a weekend.' ) ),
			array( 'type' => 'contact', 'title' => 'Contact' ),
		),
	), $extra ) );
}
function twd_sk_build_pictures() {
	return array(
		'slots'  => array( 'logo' => array( 'id' => 31 ), 'home_main' => array( 'id' => 32, 'description' => 'The front of the van' ), 'about_photo' => array( 'id' => 33 ) ),
		'custom' => array(
			array( 'name' => 'inside-view', 'id' => 34, 'description' => 'Inside, with benches', 'use_on' => 'any' ),
			array( 'name' => 'weekend', 'id' => 35, 'description' => 'A weekend by the lake', 'use_on' => 'weekend-hire' ),
		),
	);
}
function twd_sk_build_setup() {
	twd_sk_build_media();
	$GLOBALS['twd_stub']['caps'] = true;
	TWD_SK_Build::save_draft( array( 'mode' => 'business', 'pack' => 'grove', 'brief_text' => twd_sk_build_brief(), 'pictures' => twd_sk_build_pictures() ) );
}
function twd_sk_build_all( $ai = false ) {
	$plan = TWD_SK_Build::plan();
	$out  = array();
	foreach ( $plan['steps'] as $s ) {
		$out[] = TWD_SK_Build::run_step( $s['id'], $s['index'], array( 'ai' => $ai ) );
	}
	return $out;
}
function twd_sk_build_page_id( $slug ) {
	$d = TWD_SK_Build::draft();
	return isset( $d['built'][ $slug ] ) ? $d['built'][ $slug ] : 0;
}

// -- Pictures -------------------------------------------------------------------

twd_sk_test( 'pictures: only real images, short unique names, plain descriptions; bad ones are reported, good ones kept', function () {
	twd_sk_build_media();
	$r = TWD_SK_Pictures::validate( array(
		'slots'  => array( 'logo' => 31, 'home_main' => array( 'id' => 36 ), 'nope' => 32, 'about_photo' => array( 'id' => 0 ) ),
		'custom' => array(
			array( 'name' => 'front-view', 'id' => 32, 'description' => '<b>Front</b> view', 'use_on' => 'Weekend Hire' ),
			array( 'name' => 'front-view', 'id' => 33 ),
			array( 'name' => 'Bad Name', 'id' => 34 ),
			array( 'name' => 'file', 'id' => 36 ),
			'junk',
		),
	) );
	twd_sk_eq( array( 'logo' => array( 'id' => 31, 'description' => '' ) ), $r['valid']['slots'] );
	twd_sk_eq( 1, count( $r['valid']['custom'] ) );
	twd_sk_eq( 'Front view', $r['valid']['custom'][0]['description'], 'tags removed' );
	twd_sk_eq( 'weekend-hire', $r['valid']['custom'][0]['use_on'] );
	$all = implode( ' | ', $r['errors'] );
	foreach ( array( 'Home page main picture: that is not a picture', 'no picture slot called "nope"', 'used twice', 'lowercase letters, numbers and dashes', 'Picture 4: that is not a picture', 'Picture 5 was not understood' ) as $needle ) {
		twd_sk_has( $needle, $all );
	}
	$many = array();
	for ( $i = 0; $i < 25; $i++ ) {
		$many[] = array( 'name' => 'p' . $i, 'id' => 31 );
	}
	$r = TWD_SK_Pictures::validate( array( 'custom' => $many ) );
	twd_sk_eq( TWD_SK_Pictures::MAX_CUSTOM, count( $r['valid']['custom'] ) );
	twd_sk_has( 'Only the first 20', implode( ' ', $r['errors'] ) );
	twd_sk_eq( array( 'valid' => TWD_SK_Pictures::empty_set(), 'errors' => array() ), TWD_SK_Pictures::validate( null ) );
} );

twd_sk_test( 'pictures: pic: names become real addresses, unknown names go back to the sample, slots and named pictures fill the sample spots', function () {
	twd_sk_build_media();
	$set  = TWD_SK_Pictures::validate( twd_sk_build_pictures() )['valid'];
	$used = array();
	$html = '<section class="twd-sk-hero"><img class="twd-sk-hero__img" src="/wp-content/uploads/your-image.jpg" alt="[PLACEHOLDER: Describe the image]"></section>'
		. '<section class="twd-sk-text"><img src="pic:weekend" alt=""><img src="pic:nope" alt="x"><img src="/wp-content/uploads/your-image.jpg" alt=""><img src="/wp-content/uploads/your-image.jpg" alt=""></section>';
	$out  = TWD_SK_Pictures::place( $html, $set, 'home', 'home', $used );
	twd_sk_has( 'src="https://example.test/wp-content/uploads/pic-32.jpg" alt="The front of the van"', $out['html'], 'home slot first' );
	twd_sk_has( 'src="/wp-content/uploads/your-image.jpg" alt="x"', $out['html'], 'unknown name back to the sample' );
	twd_sk_hasnt( 'pic-35', $out['html'], 'a picture for another page is not placed here' );
	twd_sk_has( 'pic-34.jpg" alt="Inside, with benches"', $out['html'], 'an any-page picture fills a sample spot' );
	twd_sk_has( 'not on this page\'s list', implode( ' ', $out['report'] ) );
	twd_sk_eq( array( 'inside-view' ), $used );
	twd_sk_eq( 2, substr_count( $out['html'], 'your-image.jpg" alt=""' ), 'the refused name and the last spot stay samples (nothing left to use)' );

	$out2 = TWD_SK_Pictures::place( '<img src="pic:inside-view" alt=""><img src="pic:weekend" alt=""><img src="/wp-content/uploads/your-image.jpg" alt="[PLACEHOLDER: x]">', $set, 'weekend-hire', 'service', $used );
	twd_sk_has( 'src="/wp-content/uploads/your-image.jpg" alt=""', $out2['html'], 'a picture already used elsewhere is not used twice' );
	twd_sk_has( 'pic-35.jpg" alt="A weekend by the lake"', $out2['html'] );

	$used = array();
	$out3 = TWD_SK_Pictures::place( '<img class="a" src="/wp-content/uploads/your-image.jpg" alt="[PLACEHOLDER: photo]">', $set, 'about-us', 'about', $used );
	twd_sk_has( 'pic-33.jpg" alt="A van parked by the sea"', $out3['html'], 'the media library alt text is used when no description was given' );

	$odd  = array( 'slots' => array( 'home_main' => array( 'id' => 32, 'description' => 'Price $1 and "quotes" \\1' ) ), 'custom' => array() );
	$used = array();
	$out4 = TWD_SK_Pictures::place( '<img src="/wp-content/uploads/your-image.jpg" alt="">', $odd, 'home', 'home', $used );
	twd_sk_has( 'alt="Price $1 and &quot;quotes&quot; \\1"', $out4['html'], 'values are written literally' );
} );

twd_sk_test( 'pictures: the AI is told only the pictures for this page', function () {
	twd_sk_build_media();
	$set = TWD_SK_Pictures::validate( twd_sk_build_pictures() )['valid'];
	$b   = TWD_SK_Pictures::prompt_block( $set, 'weekend-hire' );
	twd_sk_has( '- pic:weekend (A weekend by the lake)', $b );
	twd_sk_has( '- pic:inside-view (Inside, with benches)', $b );
	twd_sk_hasnt( 'weekend', TWD_SK_Pictures::prompt_block( $set, 'home' ) );
	twd_sk_eq( '', TWD_SK_Pictures::prompt_block( TWD_SK_Pictures::empty_set(), 'home' ) );
} );

// -- The draft and the plan --------------------------------------------------------

twd_sk_test( 'build draft: choices are checked; a refused value is reported while the rest are kept', function () {
	twd_sk_build_media();
	$d = TWD_SK_Build::draft();
	twd_sk_eq( 'type', $d['step'] );
	twd_sk_eq( 'therapy', $d['mode'] );
	$r = TWD_SK_Build::save_draft( array( 'mode' => 'shop', 'pack' => 'grove', 'step' => 'brief', 'brief_text' => 'notes', 'pictures' => array( 'custom' => array( array( 'name' => 'ok', 'id' => 31 ), array( 'name' => 'bad', 'id' => 36 ) ) ) ) );
	twd_sk_has( 'site types', implode( ' ', $r['errors'] ) );
	twd_sk_eq( 'therapy', $r['draft']['mode'] );
	twd_sk_eq( 'grove', $r['draft']['pack'] );
	twd_sk_eq( 'type', $r['draft']['step'], 'the step does not move on while something was refused' );
	twd_sk_eq( 'brief', TWD_SK_Build::save_draft( array( 'step' => 'brief' ) )['draft']['step'] );
	twd_sk_eq( 1, count( $r['draft']['pictures']['custom'] ) );
	$r = TWD_SK_Build::save_draft( array( 'brief_text' => str_repeat( 'x', TWD_SK_Brief::MAX_BYTES + 1 ) ) );
	twd_sk_has( 'too long', implode( ' ', $r['errors'] ) );
	twd_sk_eq( 'notes', TWD_SK_Build::draft()['brief_text'] );
	TWD_SK_Build::reset_draft();
	twd_sk_eq( '', TWD_SK_Build::draft()['brief_text'] );
} );

twd_sk_test( 'build plan: no brief makes Home, About and Contact; a brief without Home gets one; same titles are left alone; nothing changes', function () {
	$p = TWD_SK_Build::plan();
	twd_sk_eq( array( 'home', 'about', 'contact' ), array_map( function ( $x ) {
		return $x['type'];
	}, $p['pages'] ) );
	twd_sk_eq( 'settings', $p['steps'][0]['id'] );
	twd_sk_eq( 'finish', end( $p['steps'] )['id'] );
	twd_sk_eq( 5, count( $p['steps'] ) );

	twd_stub_add_post( 60, 'page' );
	$GLOBALS['twd_stub']['posts'][60]->post_title = 'Contact';
	TWD_SK_Build::save_draft( array( 'brief_text' => json_encode( array( 'schema' => 'twd-site-brief/1', 'pages' => array( array( 'type' => 'about', 'title' => 'About' ), array( 'type' => 'faq', 'title' => 'About' ), array( 'type' => 'contact', 'title' => 'Contact' ) ) ) ) ) );
	$before = json_encode( $GLOBALS['twd_stub']['options'] );
	$p      = TWD_SK_Build::plan();
	twd_sk_eq( 'home', $p['pages'][0]['type'], 'Home added first' );
	twd_sk_eq( array( 'home', 'about', 'about-2', 'contact' ), array_map( function ( $x ) {
		return $x['slug'];
	}, $p['pages'] ) );
	twd_sk_eq( 'skip', $p['pages'][3]['action'] );
	twd_sk_has( 'already exists', $p['steps'][4]['label'] );
	twd_sk_eq( $before, json_encode( $GLOBALS['twd_stub']['options'] ), 'planning saves nothing' );

	TWD_SK_Build::save_draft( array( 'brief_text' => '{ not json' ) );
	twd_sk_has( 'The brief:', implode( ' ', TWD_SK_Build::plan()['errors'] ) );
} );

// -- Running the build ----------------------------------------------------------------

twd_sk_test( 'build: the settings step saves the type, style, brief details, logo, a menu from the pages and the header button', function () {
	twd_sk_build_setup();
	$r = TWD_SK_Build::run_step( 'settings' );
	twd_sk_true( $r['ok'] );
	twd_sk_eq( 'business', TWD_SK_Mode::get() );
	twd_sk_eq( 'grove', TWD_SK_Packs::active_slug() );
	$p = TWD_SK_Profile::get();
	twd_sk_eq( 'Example Hire', $p['site_name'] );
	twd_sk_eq( 31, (int) $p['logo_id'] );
	twd_sk_eq( array( '/', '/about-us', '/weekend-hire', '/contact' ), array_map( function ( $m ) {
		return $m['url'];
	}, $p['menu'] ) );
	twd_sk_eq( '/contact', $p['cta_url'] );
	twd_sk_has( 'A small hire company.', TWD_SK_Facts::get() );
	twd_sk_has( 'Logo set.', implode( ' ', $r['report'] ) );
	twd_sk_has( 'Build started', implode( ' ', TWD_SK_Build::log_lines() ) );
} );

twd_sk_test( 'build: without AI every page is a draft outline with the pictures in place; running again makes new versions, not new pages', function () {
	twd_sk_build_setup();
	$results = twd_sk_build_all( false );
	foreach ( $results as $r ) {
		twd_sk_true( ! is_wp_error( $r ) && $r['ok'], 'step ok' );
	}
	$home = twd_sk_build_page_id( 'home' );
	twd_sk_true( $home > 0 );
	twd_sk_eq( 'draft', get_post( $home )->post_status );
	twd_sk_eq( 'home', get_post_meta( $home, '_twd_sk_build_slug', true ) );
	twd_sk_has( 'pic-32.jpg', TWD_SK_Store::get_current( $home ), 'home picture in the saved page' );
	$weekend = twd_sk_build_page_id( 'weekend-hire' );
	twd_sk_eq( 'Hire for a weekend.', TWD_SK_Seo::read( $weekend )['description'] );
	$names = array_map( function ( $a ) {
		return isset( $a['post_name'] ) ? $a['post_name'] : '';
	}, $GLOBALS['twd_stub']['inserted'] );
	twd_sk_true( in_array( 'weekend-hire', $names, true ), 'the page address matches the menu link' );
	twd_sk_eq( 'page', get_option( 'show_on_front' ) );
	twd_sk_eq( $home, (int) get_option( 'page_on_front' ) );
	twd_sk_true( TWD_SK_Build::is_set_up() );
	$finish = end( $results );
	twd_sk_eq( 4, count( $finish['pages'] ) );
	twd_sk_true( $finish['pages'][0]['must'] > 0, 'outlines still have placeholders to fix' );

	$count = count( $GLOBALS['twd_stub']['posts'] );
	twd_sk_build_all( false );
	twd_sk_eq( $count, count( $GLOBALS['twd_stub']['posts'] ), 'no new pages the second time' );
	twd_sk_eq( $home, twd_sk_build_page_id( 'home' ) );
	twd_sk_eq( 1, count( TWD_SK_Store::list_versions( $home ) ), 'the same outline again is not saved twice' );
} );

twd_sk_test( 'build: with AI the page is written by the AI, its pic: names placed, and an AI failure falls back to the outline', function () {
	twd_sk_build_setup();
	twd_sk_ai_provider( function ( $system, $message ) {
		if ( false !== strpos( $message, 'Weekend hire' ) ) {
			return new WP_Error( 'twd_ap_ai_bad_key', 'The key was refused.' );
		}
		return '<section class="twd-sk-hero"><h1 class="twd-sk-hero__title">Hello</h1><img class="twd-sk-hero__img" src="pic:inside-view" alt="Inside"></section>';
	} );
	TWD_SK_Build::run_step( 'settings' );
	$r = TWD_SK_Build::run_step( 'page', 0, array( 'ai' => true ) );
	twd_sk_true( $r['page']['ai'] );
	$call = $GLOBALS['twd_stub']['ai_calls'][0];
	twd_sk_has( 'you may use one of the pictures listed in the request', $call['system'] );
	twd_sk_has( '- pic:inside-view (Inside, with benches)', $call['message'] );
	twd_sk_hasnt( 'pic:weekend', $call['message'], 'the weekend picture is only for its own page' );
	$html = TWD_SK_Store::get_current( twd_sk_build_page_id( 'home' ) );
	twd_sk_has( 'src="https://example.test/wp-content/uploads/pic-34.jpg"', $html );
	twd_sk_hasnt( 'pic:', $html );
	$r = TWD_SK_Build::run_step( 'page', 2, array( 'ai' => true ) );
	twd_sk_true( $r['ok'] );
	twd_sk_eq( false, $r['page']['ai'] );
	twd_sk_has( 'the AI could not write it (The key was refused.) The outline was saved instead', implode( ' ', $r['report'] ) );
	twd_sk_true( '' !== TWD_SK_Store::get_current( twd_sk_build_page_id( 'weekend-hire' ) ), 'the outline was saved' );
} );

twd_sk_test( 'build: a page with the same title made some other way is never touched; a published front page is never replaced', function () {
	twd_sk_build_setup();
	twd_stub_add_post( 70, 'page', 'Their own contact page' );
	$GLOBALS['twd_stub']['posts'][70]->post_title = 'Contact';
	twd_stub_add_post( 71, 'page' );
	$GLOBALS['twd_stub']['posts'][71]->post_status = 'publish';
	update_option( 'show_on_front', 'page' );
	update_option( 'page_on_front', 71 );
	$results = twd_sk_build_all( false );
	twd_sk_eq( 'Their own contact page', get_post_field( 'post_content', 70 ) );
	twd_sk_eq( '', TWD_SK_Store::get_current( 70 ) );
	twd_sk_has( 'already exists, so it was left alone', implode( ' ', $results[4]['report'] ) );
	twd_sk_eq( 71, (int) get_option( 'page_on_front' ) );
	twd_sk_has( 'A published front page is already set', implode( ' ', end( $results )['report'] ) );
} );

twd_sk_test( 'build: an unreadable style stops the settings step before anything is saved; a bad step name is refused; a crash is caught and logged', function () {
	twd_sk_build_setup();
	TWD_SK_Build::save_draft( array( 'brief_text' => twd_sk_build_brief( array( 'style' => array( 'tokens' => array( 'color-on-primary' => '#5F6F54' ) ) ) ) ) );
	$r = TWD_SK_Build::run_step( 'settings' );
	twd_sk_is_error( 'twd_sk_contrast', $r );
	twd_sk_eq( 'therapy', TWD_SK_Mode::get(), 'nothing saved' );
	twd_sk_is_error( 'twd_sk_bad_input', TWD_SK_Build::run_step( 'dance' ) );
	twd_sk_is_error( 'twd_sk_bad_input', TWD_SK_Build::run_step( 'page', 99 ) );
	TWD_SK_Build::save_draft( array( 'brief_text' => '' ) );
	$GLOBALS['twd_stub']['insert_fails'] = true;
	$e = TWD_SK_Build::run_step( 'page', 0, array( 'ai' => false ) );
	twd_sk_is_error( 'insert_failed', $e );
	twd_sk_has( 'failed', implode( ' ', TWD_SK_Build::log_lines() ) );
} );

twd_sk_test( 'build log: kept short, and shown in the site report with the setup state', function () {
	for ( $i = 0; $i < TWD_SK_Build::MAX_LOG + 20; $i++ ) {
		TWD_SK_Build::log( 'line ' . $i );
	}
	twd_sk_eq( TWD_SK_Build::MAX_LOG, count( get_option( TWD_SK_Build::LOG ) ) );
	$r = TWD_SK_Doctor::report();
	twd_sk_has( 'SITE SETUP', $r );
	twd_sk_has( 'Set up: not yet', $r );
	twd_sk_has( 'line ' . ( TWD_SK_Build::MAX_LOG + 19 ), $r );
} );

twd_sk_test( 'build: the site counts as set up after a build, or when it already has kit pages', function () {
	twd_sk_true( ! TWD_SK_Build::is_set_up() );
	update_option( TWD_SK_Build::DONE, '2026-10-12 10:00:00' );
	twd_sk_true( TWD_SK_Build::is_set_up() );
	twd_stub_reset();
	twd_stub_add_post( 80, 'page', '[twd_page]' );
	TWD_SK_Store::save( 80, '<p>Hi</p>' );
	twd_sk_true( TWD_SK_Build::is_set_up() );
} );

// -- The setup page and the front-page link ------------------------------------------

twd_sk_test( 'setup page: under Settings for administrators, not in safe mode; the script loads only on that page', function () {
	TWD_SK_Build::admin_menu();
	twd_sk_eq( 'manage_options', $GLOBALS['twd_stub']['options_pages'][0]['cap'] );
	twd_sk_eq( 'twd-sk-setup', $GLOBALS['twd_stub']['options_pages'][0]['slug'] );
	$GLOBALS['twd_stub']['options_pages'] = array();
	TWD_SK_Safe::set( true );
	TWD_SK_Build::admin_menu();
	twd_sk_eq( array(), $GLOBALS['twd_stub']['options_pages'] );
	TWD_SK_Safe::set( false );
	TWD_SK_Build::admin_assets( 'index.php' );
	twd_sk_true( ! isset( $GLOBALS['twd_stub']['scripts']['twd-site-kit-setup'] ), 'not on other admin pages' );
	TWD_SK_Build::admin_assets( 'settings_page_twd-sk-setup' );
	twd_sk_true( isset( $GLOBALS['twd_stub']['scripts']['twd-site-kit-setup'] ) );
	ob_start();
	TWD_SK_Build::render_admin_page();
	twd_sk_has( 'id="twd-sk-setup"', ob_get_clean() );
} );

twd_sk_test( 'front link: only for an administrator, on the front end, outside safe mode, until the site is set up', function () {
	$GLOBALS['twd_stub']['admin'] = false;
	ob_start();
	TWD_SK_Build::front_button();
	twd_sk_has( 'Set this site up', ob_get_clean() );
	foreach ( array( 'caps' => false, 'admin' => true ) as $k => $v ) {
		$keep                       = $GLOBALS['twd_stub'][ $k ];
		$GLOBALS['twd_stub'][ $k ] = $v;
		ob_start();
		TWD_SK_Build::front_button();
		twd_sk_eq( '', ob_get_clean(), $k );
		$GLOBALS['twd_stub'][ $k ] = $keep;
	}
	update_option( TWD_SK_Build::DONE, 'x' );
	ob_start();
	TWD_SK_Build::front_button();
	twd_sk_eq( '', ob_get_clean(), 'gone once set up' );
} );

twd_sk_test( 'setup js: talks only to the address it was given, no injection, no native dialogs, inline confirm for starting again', function () {
	$js = file_get_contents( ABSPATH . 'assets/twd-site-kit-setup.js' );
	foreach ( array( 'eval(', 'new Function', 'document.write', 'innerHTML', 'outerHTML', 'insertAdjacentHTML', 'XMLHttpRequest', 'localStorage', 'sendBeacon', 'onclick' ) as $bad ) {
		twd_sk_hasnt( $bad, $js );
	}
	twd_sk_true( 1 !== preg_match( '/(^|[^.\w])(alert|confirm|prompt)\s*\(/m', $js ), 'no native dialogs' );
	twd_sk_true( 1 !== preg_match( '#https?://#i', $js ), 'no web addresses' );
	twd_sk_has( 'fetch(cfg.restUrl + path', $js );
	twd_sk_has( "'X-WP-Nonce': cfg.nonce", $js );
	foreach ( array( "'/site/build'", "'/site/build/draft'", "'/site/build/notes'", "'/site/build/step'", "'/site/build/reset', { confirm: true }", 'window.wp.media' ) as $needle ) {
		twd_sk_has( $needle, $js );
	}
	twd_sk_has( 'confirmInline(body,', $js );
} );

// -- Routes --------------------------------------------------------------------------

twd_sk_test( 'build rest: the state, saving choices, starting again, the build steps and the notes route', function () {
	twd_sk_site_caps();
	twd_sk_build_media();
	$s = TWD_SK_REST::get_site_build( twd_sk_rest_req() );
	foreach ( array( 'draft', 'plan', 'modes', 'packs', 'slots', 'ai', 'done' ) as $k ) {
		twd_sk_true( array_key_exists( $k, $s ), $k );
	}
	$s = TWD_SK_REST::post_site_build_draft( twd_sk_rest_req( array( 'mode' => 'business', 'pictures' => array( 'slots' => array( 'logo' => array( 'id' => 31 ) ) ) ) ) );
	twd_sk_eq( 'business', $s['draft']['mode'] );
	twd_sk_eq( 'https://example.test/wp-content/uploads/pic-31.jpg', $s['draft']['pictures']['slots']['logo']['url'] );
	twd_sk_is_error( 'twd_sk_confirm_needed', TWD_SK_REST::post_site_build_reset( twd_sk_rest_req() ) );
	twd_sk_eq( 'therapy', TWD_SK_REST::post_site_build_reset( twd_sk_rest_req( array( 'confirm' => true ) ) )['draft']['mode'] );
	$e = TWD_SK_REST::post_site_build_step( twd_sk_rest_req( array( 'step' => 'dance' ) ) );
	twd_sk_eq( 400, $e->get_error_data()['status'] );
	$ok = TWD_SK_REST::post_site_build_step( twd_sk_rest_req( array( 'step' => 'settings' ) ) );
	twd_sk_true( $ok['ok'] );
	$e = TWD_SK_REST::post_site_build_notes( twd_sk_rest_req( array( 'notes' => 'We hire vans.' ) ) );
	twd_sk_eq( 503, $e->get_error_data()['status'], 'no AI: a clear refusal' );
	twd_sk_site_caps( false );
	foreach ( twd_sk_site_routes() as $r ) {
		if ( 0 === strpos( $r['route'], '/site/build' ) ) {
			twd_sk_is_error( 'twd_sk_forbidden', call_user_func( $r['args']['permission_callback'], twd_sk_rest_req() ), 'an editor on ' . $r['route'] );
		}
	}
} );

twd_sk_test( 'brief from notes: the AI turns notes into a brief that is only shown, never saved; bad answers are refused', function () {
	twd_sk_ai_provider( "```json\n{\"schema\":\"twd-site-brief/1\",\"site\":{\"name\":\"Example Hire\"}}\n```" );
	$r = TWD_SK_Brief::from_notes( 'We hire vans in the county.' );
	twd_sk_has( '"name": "Example Hire"', $r['json'] );
	twd_sk_has( 'We hire vans in the county.', $GLOBALS['twd_stub']['ai_calls'][0]['message'] );
	twd_sk_has( 'Never invent anything', $GLOBALS['twd_stub']['ai_calls'][0]['system'] );
	twd_sk_has( '"schema": "twd-site-brief/1"', $GLOBALS['twd_stub']['ai_calls'][0]['system'] );
	twd_sk_eq( '', TWD_SK_Facts::get(), 'nothing saved' );
	twd_sk_ai_provider( 'Sorry, I cannot.' );
	twd_sk_is_error( 'twd_sk_ai_bad_result', TWD_SK_Brief::from_notes( 'x' ) );
	twd_sk_is_error( 'twd_sk_bad_brief', TWD_SK_Brief::from_notes( '  ' ) );
	twd_sk_is_error( 'twd_sk_bad_brief', TWD_SK_Brief::from_notes( str_repeat( 'x', TWD_SK_Brief::MAX_NOTES + 1 ) ) );
} );

// -- The command --------------------------------------------------------------------

twd_sk_test( 'build cli: a dry run changes nothing; a real run makes the drafts; a bad type stops before anything is built', function () {
	require_once __DIR__ . '/stub-wpcli.php';
	twd_sk_build_media();
	$GLOBALS['twd_stub']['caps'] = true;
	$brief = twd_sk_tmp_file( twd_sk_build_brief() );
	$pics  = twd_sk_tmp_file( json_encode( twd_sk_build_pictures() ) );
	$cli   = new TWD_SK_CLI();
	WP_CLI::reset();
	$cli->build( array( $brief ), array( 'mode' => 'business', 'dry-run' => true ) );
	twd_sk_has( 'Dry run: nothing was changed.', WP_CLI::all() );
	twd_sk_has( 'Make the "Weekend hire" page', WP_CLI::all() );
	twd_sk_eq( 'therapy', TWD_SK_Mode::get() );
	twd_sk_eq( 0, count( TWD_SK_Build::draft()['built'] ) );
	WP_CLI::reset();
	$cli->build( array( $brief ), array( 'mode' => 'business', 'pictures' => $pics, 'no-ai' => true ) );
	twd_sk_has( 'Built. Every page is a draft.', WP_CLI::all() );
	twd_sk_eq( 4, count( TWD_SK_Build::draft()['built'] ) );
	twd_sk_eq( 'business', TWD_SK_Mode::get() );
	twd_sk_has( 'Fix the type or style first', twd_sk_cli_fails( function () use ( $cli, $brief ) {
		$cli->build( array( $brief ), array( 'mode' => 'shop' ) );
	} ) );
	twd_sk_has( 'Could not read', twd_sk_cli_fails( function () use ( $cli ) {
		$cli->build( array( '/no/such/file.json' ), array() );
	} ) );
} );

// -- The Pages step (0.7.2): the page list and the sections on each --------------------

twd_sk_test( 'sections: a page outline is a list of real sections, starting with a hero, up to twelve', function () {
	$ok = TWD_SK_Recipes::clean_parts( array( array( 'hero', 'twd-sk-hero--compact' ), array( 'component' => 'faq', 'variant' => '' ), 'text' ) );
	twd_sk_eq( array( array( 'hero', 'twd-sk-hero--compact' ), array( 'faq', '' ), array( 'text', '' ) ), $ok );
	twd_sk_has( 'starts with a hero', TWD_SK_Recipes::clean_parts( array( 'text', 'hero' ) )->get_error_message() );
	twd_sk_has( 'not one of the kit', TWD_SK_Recipes::clean_parts( array( 'hero', 'carousel' ) )->get_error_message() );
	twd_sk_has( 'does not have that layout', TWD_SK_Recipes::clean_parts( array( 'hero', array( 'text', 'twd-sk-made-up' ) ) )->get_error_message() );
	twd_sk_has( 'up to 12', TWD_SK_Recipes::clean_parts( array_merge( array( 'hero' ), array_fill( 0, 12, 'text' ) ) )->get_error_message() );
	twd_sk_is_error( 'twd_sk_bad_input', TWD_SK_Recipes::clean_parts( array() ) );
	$menu = TWD_SK_Recipes::parts_menu();
	twd_sk_eq( TWD_SK_Registry::ids(), array_keys( $menu ) );
	twd_sk_eq( 'Standard', $menu['hero']['variants'][''] );
} );

twd_sk_test( 'sections: a brief page can name its sections; the outline and the AI request follow them; the page type gives the default', function () {
	$in = TWD_SK_Recipes::inputs( array( 'type' => 'about', 'sections' => array( array( 'component' => 'hero' ), array( 'component' => 'faq' ) ) ) );
	twd_sk_eq( array( array( 'hero', '' ), array( 'faq', '' ) ), $in['parts'] );
	$html = TWD_SK_Recipes::skeleton_for( $in );
	twd_sk_has( 'twd-sk-faq', $html );
	twd_sk_hasnt( 'twd-sk-steps', $html );
	twd_sk_has( '<h1', $html, 'the hero title is the one h1' );
	twd_sk_has( 'twd-sk-faq', TWD_SK_AI::page_message( $in ) );
	twd_sk_eq( TWD_SK_Recipes::get( 'about' )['parts'], TWD_SK_Recipes::inputs( array( 'type' => 'about' ) )['parts'] );
	twd_sk_is_error( 'twd_sk_bad_input', TWD_SK_Recipes::inputs( array( 'type' => 'about', 'sections' => array( 'faq' ) ) ) );
	twd_sk_has( 'pages[].sections: optional', TWD_SK_Brief::schema_text() );
	$p = TWD_SK_Brief::plan( array( 'schema' => 'twd-site-brief/1', 'pages' => array( array( 'type' => 'about', 'title' => 'About', 'sections' => array( 'text' ) ) ) ), false );
	twd_sk_has( 'Page 1: A page starts with a hero', implode( ' ', $p['errors'] ) );
} );

twd_sk_test( 'pages step: an edited page list replaces the brief\'s until reset; a bad list is refused and nothing changes', function () {
	twd_sk_build_setup();
	$edited = array(
		array( 'type' => 'home', 'title' => 'Home', 'parts' => array( array( 'component' => 'hero', 'variant' => 'twd-sk-hero--split' ), array( 'component' => 'faq', 'variant' => '' ) ) ),
		array( 'type' => 'contact', 'title' => 'Book', 'parts' => array( array( 'component' => 'hero', 'variant' => '' ), array( 'component' => 'contact', 'variant' => '' ) ) ),
	);
	$r = TWD_SK_Build::save_draft( array( 'pages' => $edited ) );
	twd_sk_eq( array(), $r['errors'] );
	$p = TWD_SK_Build::plan();
	twd_sk_eq( 'edited', $p['source'] );
	twd_sk_eq( array( 'Home', 'Book' ), array_map( function ( $x ) {
		return $x['title'];
	}, $p['pages'] ) );
	$bad = TWD_SK_Build::save_draft( array( 'pages' => array( array( 'type' => 'about', 'title' => 'About', 'parts' => array( array( 'component' => 'text' ) ) ) ) ) );
	twd_sk_has( 'Page 1: A page starts with a hero', implode( ' ', $bad['errors'] ) );
	twd_sk_eq( 'edited', TWD_SK_Build::plan()['source'] );
	twd_sk_eq( 2, count( TWD_SK_Build::plan()['pages'] ), 'the good list is kept' );
	twd_sk_has( 'at least one page', implode( ' ', TWD_SK_Build::save_draft( array( 'pages' => array() ) )['errors'] ) );

	$results = twd_sk_build_all( false );
	$home    = TWD_SK_Store::get_current( twd_sk_build_page_id( 'home' ) );
	twd_sk_has( 'twd-sk-hero--split', $home );
	twd_sk_has( 'twd-sk-faq', $home );
	twd_sk_hasnt( 'twd-sk-quote', $home, 'built from the edited sections, not the home default' );
	twd_sk_eq( '/book', TWD_SK_Profile::get()['cta_url'] );

	TWD_SK_Build::save_draft( array( 'reset_pages' => true ) );
	twd_sk_eq( 'brief', TWD_SK_Build::plan()['source'] );
	twd_sk_eq( 4, count( TWD_SK_Build::plan()['pages'] ) );
} );

twd_sk_test( 'pages step rest: the browser gets the sections, the page types and the limits, never the raw saved list', function () {
	twd_sk_site_caps();
	$s = TWD_SK_REST::get_site_build( twd_sk_rest_req() );
	twd_sk_eq( TWD_SK_Recipes::MAX_SECTIONS, $s['max_sections'] );
	twd_sk_true( isset( $s['components']['hero']['variants'] ) );
	twd_sk_true( isset( $s['recipes']['service']['needs_topic'] ) && $s['recipes']['service']['needs_topic'] );
	twd_sk_eq( array( 'component' => 'hero', 'variant' => 'twd-sk-hero--split' ), $s['plan']['pages'][0]['parts'][0] );
	twd_sk_true( ! array_key_exists( 'pages', $s['draft'] ) );
	$s = TWD_SK_REST::post_site_build_draft( twd_sk_rest_req( array( 'pages' => array( array( 'type' => 'about', 'title' => 'Our story' ) ) ) ) );
	twd_sk_eq( 'edited', $s['plan']['source'] );
	twd_sk_eq( 'Home', $s['plan']['pages'][0]['title'], 'Home is still added first' );
	$s = TWD_SK_REST::post_site_build_draft( twd_sk_rest_req( array( 'reset_pages' => true ) ) );
	twd_sk_eq( 'brief', $s['plan']['source'] );
	$js = file_get_contents( ABSPATH . 'assets/twd-site-kit-setup.js' );
	twd_sk_has( "{ id: 'pages', label: 'Pages' }", $js );
	twd_sk_has( 'return { pages: ui.pages };', $js );
	twd_sk_has( 'save({ reset_pages: true })', $js );
} );
