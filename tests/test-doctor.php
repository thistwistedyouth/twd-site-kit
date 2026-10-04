<?php
// The site report: plain text, paste-safe, read only.

function twd_sk_doc_setup() {
	twd_stub_reset();
	TWD_SK_Profile::save( array( 'site_name' => 'Calm Practice', 'phone' => '01234 567890', 'email' => 'secret.person@example.com', 'footer_text' => 'SECRET-FOOTER-WORDS', 'person_name' => 'Secret Personname', 'registration' => array( 'REGISTRATION-LINE-SECRET' ), 'menu' => array( array( 'label' => 'Home', 'url' => '/', 'children' => array() ) ), 'address' => array( 'SECRET-ADDRESS-LINE' ) ) );
	TWD_SK_Facts::save( "WHO I AM\nSECRET-FACT-TEXT about a practice." );
}

twd_sk_test( 'doctor: the report is plain text and holds no secret, no personal detail and no fact text', function () {
	twd_sk_doc_setup();
	$GLOBALS['twd_stub']['options']['twd_sk_anthropic_key'] = 'sk-ant-SECRET-KEY';
	$r = TWD_SK_Doctor::report();
	foreach ( array( 'SECRET-FOOTER-WORDS', 'secret.person@example.com', '01234 567890', 'Secret Personname', 'REGISTRATION-LINE-SECRET', 'SECRET-ADDRESS-LINE', 'SECRET-FACT-TEXT', 'sk-ant', 'hunter2' ) as $bad ) {
		twd_sk_hasnt( $bad, $r, $bad );
	}
	twd_sk_hasnt( '<', $r );
	twd_sk_hasnt( "\xE2\x80\x94", $r );
	twd_sk_hasnt( "\xE2\x80\x93", $r );
	twd_sk_true( 1 === preg_match( '/^[\x09\x0A\x20-\x7E]*$/', $r ), 'plain ASCII text' );
	twd_sk_has( 'TWD Site Kit report', $r );
	twd_sk_has( 'It holds no keys, no passwords', $r );
	foreach ( array( 'PLUGIN AND SITE', 'STYLE', 'FRONT PAGE', 'ELEMENTOR THEME BUILDER', 'SITE DETAILS AND PRACTICE FACTS', 'PAGES', 'PICTURES IN THE MEDIA LIBRARY' ) as $h ) {
		twd_sk_has( $h, $r );
	}
} );

twd_sk_test( 'doctor: versions, safe mode, SEO plugin, pack, PHP and WordPress are stated', function () {
	twd_sk_doc_setup();
	$GLOBALS['twd_stub']['bloginfo']['version'] = '6.6.2';
	$r = TWD_SK_Doctor::report();
	twd_sk_has( 'Site Kit version: ' . TWD_SK_VERSION, $r );
	twd_sk_has( 'Safe mode: off', $r );
	twd_sk_has( 'SEO plugin: none', $r );
	twd_sk_has( 'Active pack: ' . TWD_SK_Packs::active_slug(), $r );
	twd_sk_has( 'PHP: ' . PHP_VERSION, $r );
	twd_sk_has( 'WordPress: 6.6.2', $r );
	twd_sk_has( 'Theme: Test Theme 1.2.3', $r );
	twd_sk_has( 'AI available to Site Kit (another plugin offers the two AI filters and has a key saved): no', $r );
	$GLOBALS['twd_stub']['plugins'] = array( 'a/a.php' => array( 'Name' => 'Articles Thing', 'Version' => '1.34.0' ), 'b/b.php' => array( 'Name' => 'Off Plugin', 'Version' => '9' ) );
	update_option( 'active_plugins', array( 'a/a.php' ) );
	twd_sk_has( 'Active plugins: Articles Thing 1.34.0', TWD_SK_Doctor::report() );
	twd_sk_hasnt( 'Off Plugin', TWD_SK_Doctor::report(), 'inactive plugins are not listed' );
	twd_sk_seo_mode( 'yoast', 'Yoast SEO' );
	twd_sk_has( 'SEO plugin: Yoast SEO', TWD_SK_Doctor::report() );
	TWD_SK_Safe::set( true );
	twd_sk_has( 'Safe mode: ON (set by option)', TWD_SK_Doctor::report() );
	TWD_SK_Safe::set( false );
	TWD_SK_Packs::set_overrides( array( 'color-primary' => '#224466' ) );
	twd_sk_has( 'changes on top of it: 1 (color-primary)', TWD_SK_Doctor::report() );
} );

twd_sk_test( 'doctor: front page setting, including a front page that is not published', function () {
	twd_sk_doc_setup();
	twd_sk_has( 'Reading setting: your latest posts', TWD_SK_Doctor::report() );
	twd_stub_add_post( 61, 'page', '' );
	$GLOBALS['twd_stub']['posts'][61]->post_title = 'Home';
	update_option( 'show_on_front', 'page' );
	update_option( 'page_on_front', 61 );
	$r = TWD_SK_Doctor::report();
	twd_sk_has( 'Front page: ID 61, "Home", publish', $r );
	twd_sk_has( 'Posts page: not chosen', $r );
	twd_sk_hasnt( 'WARNING', $r );
	$GLOBALS['twd_stub']['posts'][61]->post_status = 'draft';
	twd_sk_has( 'WARNING: the front page is not published', TWD_SK_Doctor::report() );
} );

twd_sk_test( 'doctor: Theme Builder is not checked without Elementor, then header and footer templates and conditions are read', function () {
	twd_sk_doc_setup();
	twd_sk_has( 'Elementor is not active, so the Theme Builder was not checked.', TWD_SK_Doctor::report() );
	if ( ! defined( 'ELEMENTOR_VERSION' ) ) {
		define( 'ELEMENTOR_VERSION', '3.99.0' );
	}
	twd_sk_has( 'Header template: none found', TWD_SK_Doctor::report() );
	twd_stub_add_post( 71, 'elementor_library', '' );
	$GLOBALS['twd_stub']['posts'][71]->post_title = 'Header';
	update_post_meta( 71, '_elementor_template_type', 'header' );
	update_post_meta( 71, '_elementor_conditions', array( 'include/general' ) );
	update_post_meta( 71, '_elementor_data', '[{"widgetType":"shortcode","settings":{"shortcode":"[twd_header]"}}]' );
	twd_stub_add_post( 72, 'elementor_library', '' );
	$GLOBALS['twd_stub']['posts'][72]->post_title = 'Footer';
	update_post_meta( 72, '_elementor_template_type', 'footer' );
	update_post_meta( 72, '_elementor_data', '[]' );
	twd_stub_add_post( 73, 'elementor_library', '' );
	update_post_meta( 73, '_elementor_template_type', 'section' );
	$r = TWD_SK_Doctor::report();
	twd_sk_has( 'Elementor: 3.99.0', $r );
	twd_sk_has( 'Header template: ID 71, "Header", publish; display conditions: include/general; holds [twd_header]: yes', $r );
	twd_sk_has( 'Footer template: ID 72, "Footer", publish; display conditions: none set; holds [twd_footer]: no', $r );
	twd_sk_hasnt( 'ID 73', $r, 'other template types are not listed' );
} );

twd_sk_test( 'doctor: details are filled, empty or placeholder, never their contents; facts say only their length', function () {
	twd_sk_doc_setup();
	$r = TWD_SK_Doctor::report();
	twd_sk_has( 'Site name: filled', $r );
	twd_sk_has( 'Phone: filled', $r );
	twd_sk_has( 'Email: filled', $r );
	twd_sk_has( 'Registration lines: 1 item', $r );
	twd_sk_has( 'Menu: 1 item', $r );
	twd_sk_has( 'Header button text: empty', $r );
	twd_sk_has( 'Logo picture: not set', $r );
	twd_sk_true( 1 === preg_match( '/Practice facts: filled \(\d+ characters/', $r ) );
	TWD_SK_Facts::save( '' );
	twd_sk_has( 'Practice facts: empty', TWD_SK_Doctor::report() );
	TWD_SK_Profile::save( TWD_SK_Profile::starter() );
	$r = TWD_SK_Doctor::report();
	twd_sk_has( 'Site name: placeholder text still there', $r );
	twd_sk_true( 1 === preg_match( '/Example text still in the site details: must fix [1-9]/', $r ), 'placeholders counted as must fix' );
} );

twd_sk_test( 'doctor: pages are counted and listed with status, template and the must fix and check counts for kit pages', function () {
	twd_sk_doc_setup();
	twd_stub_add_post( 81, 'page', '[twd_page]' );
	$GLOBALS['twd_stub']['posts'][81]->post_title = 'Kit page';
	TWD_SK_Store::save( 81, '<section class="twd-sk-text"><h2 class="twd-sk-title">Heading here</h2><a class="twd-sk-btn twd-sk-btn--primary" href="/c">Contact me about a first session</a></section>' );
	update_post_meta( 81, '_wp_page_template', 'twd-site-kit-page.php' );
	twd_stub_add_post( 82, 'page', 'Plain words' );
	$GLOBALS['twd_stub']['posts'][82]->post_title = 'Plain page';
	twd_stub_add_post( 83, 'page', '[twd_page]' );
	$GLOBALS['twd_stub']['posts'][83]->post_status = 'draft';
	$GLOBALS['twd_stub']['posts'][83]->post_title = 'Draft kit';
	TWD_SK_Store::save( 83, '<section class="twd-sk-text"><h2 class="twd-sk-title">A real heading</h2></section>' );
	$r = TWD_SK_Doctor::report();
	twd_sk_has( 'Pages: 3 (2 use the kit, 1 do not); published 2, drafts 1, other 0', $r );
	twd_sk_has( 'ID 81 | publish | kit template | must fix 1 | check 1 | "Kit page"', $r );
	twd_sk_has( 'ID 82 | publish | not a kit page | "Plain page"', $r );
	twd_sk_has( 'ID 83 | draft | kit content, theme template | must fix 0 | check 0 | "Draft kit"', $r );
} );

twd_sk_test( 'doctor: a long page list is cut and says so, and odd titles cannot break the layout', function () {
	twd_sk_doc_setup();
	for ( $i = 100; $i < 190; $i++ ) {
		twd_stub_add_post( $i, 'page', 'x' );
	}
	$GLOBALS['twd_stub']['posts'][100]->post_title = "Evil\ntitle <script>alert(1)</script> \xE2\x80\x94 " . str_repeat( 'long', 50 );
	$r = TWD_SK_Doctor::report();
	twd_sk_has( 'more pages not listed.', $r );
	twd_sk_hasnt( '<script>', $r );
	twd_sk_true( 1 === preg_match( '/ID 100 \| publish \| not a kit page \| "Evil title alert\(1\), long/', $r ), 'tags, newlines and long dashes cleaned' );
	foreach ( explode( "\n", $r ) as $line ) {
		twd_sk_true( strlen( $line ) < 400, 'no runaway line' );
	}
} );

twd_sk_test( 'doctor: pictures list name, address, alt text, size and file size, and count missing alt text', function () {
	twd_sk_doc_setup();
	$dir = sys_get_temp_dir() . '/twdsk-doc-' . bin2hex( random_bytes( 4 ) );
	mkdir( $dir );
	file_put_contents( $dir . '/portrait.jpg', str_repeat( 'a', 150000 ) );
	$GLOBALS['twd_stub']['attachments'][91] = array( 'url' => 'https://example.test/u/portrait.jpg', 'w' => 1200, 'h' => 800, 'is_image' => true, 'file' => $dir . '/portrait.jpg' );
	$GLOBALS['twd_stub']['attachments'][92] = array( 'url' => 'https://example.test/u/no-file.png', 'w' => 0, 'h' => 0, 'is_image' => true, 'file' => $dir . '/gone.png' );
	twd_stub_add_post( 91, 'attachment', '' );
	twd_stub_add_post( 92, 'attachment', '' );
	update_post_meta( 91, '_wp_attachment_image_alt', 'A portrait' );
	$r = TWD_SK_Doctor::report();
	twd_sk_has( '2 pictures; alt text present on 1, missing on 1.', $r );
	twd_sk_has( 'ID 91 | portrait.jpg | alt text: present | 1200x800 | 146 KB | https://example.test/u/portrait.jpg', $r );
	twd_sk_has( 'ID 92 | gone.png | alt text: missing | size unknown | file size unknown | https://example.test/u/no-file.png', $r );
	twd_sk_hasnt( 'A portrait', $r, 'the alt text itself is not printed' );
	unlink( $dir . '/portrait.jpg' );
	rmdir( $dir );
	twd_stub_reset();
	twd_sk_has( 'No pictures in the Media Library.', TWD_SK_Doctor::report() );
} );

twd_sk_test( 'doctor: one section that cannot be read never stops the rest', function () {
	twd_sk_doc_setup();
	$GLOBALS['twd_stub']['option_throws'] = true;
	$r = TWD_SK_Doctor::report();
	$GLOBALS['twd_stub']['option_throws'] = false;
	twd_sk_has( 'Could not read this section', $r );
	twd_sk_has( 'TWD Site Kit report', $r );
	twd_sk_has( 'PICTURES IN THE MEDIA LIBRARY', $r );
} );

twd_sk_test( 'doctor: it is read only and calls nothing outside the site', function () {
	twd_sk_doc_setup();
	$GLOBALS['twd_stub']['updated'] = array();
	$GLOBALS['twd_stub']['inserted'] = array();
	$before = json_encode( array( $GLOBALS['twd_stub']['options'], $GLOBALS['twd_stub']['meta'] ) );
	TWD_SK_Doctor::report();
	twd_sk_eq( $before, json_encode( array( $GLOBALS['twd_stub']['options'], $GLOBALS['twd_stub']['meta'] ) ), 'no option or meta changed' );
	twd_sk_eq( array(), $GLOBALS['twd_stub']['updated'] );
	$php = file_get_contents( ABSPATH . 'includes/class-twd-sk-doctor.php' );
	foreach ( array( 'wp_remote_', 'curl_', 'update_option', 'update_post_meta', 'wp_insert_post', 'wp_update_post', 'delete_', 'file_put_contents', 'unlink' ) as $bad ) {
		twd_sk_hasnt( $bad, $php );
	}
} );

twd_sk_test( 'doctor cli: prints the report, and works in safe mode', function () {
	require_once __DIR__ . '/stub-wpcli.php';
	twd_sk_doc_setup();
	WP_CLI::reset();
	( new TWD_SK_CLI() )->doctor( array(), array() );
	twd_sk_has( 'TWD Site Kit report', WP_CLI::all() );
	twd_sk_has( 'Safe mode: off', WP_CLI::all() );
	TWD_SK_Safe::set( true );
	WP_CLI::reset();
	( new TWD_SK_CLI() )->doctor( array(), array() );
	twd_sk_has( 'Safe mode: ON', WP_CLI::all() );
	TWD_SK_Safe::set( false );
} );

twd_sk_test( 'doctor rest: administrators only, read only, not registered in safe mode', function () {
	twd_sk_doc_setup();
	$GLOBALS['twd_stub']['user'] = 7;
	$GLOBALS['twd_stub']['caps'] = array( 'edit_pages' );
	twd_sk_eq( 403, twd_sk_status( TWD_SK_REST::can_read_site( twd_sk_rest_req() ) ) );
	$GLOBALS['twd_stub']['caps'] = array( 'edit_pages', 'manage_options' );
	twd_sk_eq( true, TWD_SK_REST::can_read_site( twd_sk_rest_req() ) );
	twd_sk_has( 'TWD Site Kit report', TWD_SK_REST::get_site_doctor( twd_sk_rest_req() )['report'] );
	$has = function () {
		foreach ( twd_sk_rest_routes() as $r ) {
			if ( '/site/doctor' === $r['route'] && 'GET' === $r['args']['methods'] ) {
				return true;
			}
		}
		return false;
	};
	twd_sk_true( $has() );
	TWD_SK_Safe::set( true );
	twd_sk_true( ! $has(), 'the Site tab button is off in safe mode (the command line report still works)' );
	TWD_SK_Safe::set( false );
} );

twd_sk_test( 'doctor js: the Site tab button reads the report through the editor api only, shows it read only, and offers a copy', function () {
	$js = file_get_contents( ABSPATH . 'assets/twd-site-kit-editor-site.js' );
	twd_sk_has( "api('GET', '/site/doctor')", $js );
	twd_sk_has( 'Show the site report', $js );
	twd_sk_has( 'Copy the report', $js );
	twd_sk_has( "readonly: ''", $js );
	twd_sk_has( 'It holds no keys or passwords', $js );
	twd_sk_hasnt( 'innerHTML', $js );
	twd_sk_true( 1 !== preg_match( '#https?://#i', $js ), 'no web addresses' );
} );
