<?php
// The TWD Kit Page template, creating pages, switching templates, publishing rules.

function twd_sk_tpl_setup() {
	twd_stub_add_post( 12, 'page', '[twd_page]' );
	$GLOBALS['twd_stub']['queried']  = 12;
	$GLOBALS['twd_stub']['post_id']  = 12;
	$GLOBALS['twd_stub']['singular'] = true;
}
function twd_sk_tpl_render() {
	$GLOBALS['twd_stub']['loop_reset'] = true;
	ob_start();
	include ABSPATH . 'templates/kit-page.php';
	return ob_get_clean();
}

twd_sk_test( 'template: it is added to the page template dropdown for pages only, keeping any other templates', function () {
	$out = TWD_SK_Template::register_template( array( 'other.php' => 'Other' ), null, null, 'page' );
	twd_sk_eq( array( 'other.php' => 'Other', 'twd-site-kit-page.php' => 'TWD Kit Page' ), $out );
	twd_sk_eq( array(), TWD_SK_Template::register_template( array(), null, null, 'post' ) );
	twd_sk_true( isset( TWD_SK_Template::register_template( array(), null, null, null )['twd-site-kit-page.php'] ) );
} );

twd_sk_test( 'template: init hooks the dropdown and template_include late (99), so a theme builder template cannot take over', function () {
	$src = file_get_contents( ABSPATH . 'includes/class-twd-sk-template.php' );
	twd_sk_has( "add_filter( 'template_include', array( __CLASS__, 'maybe_use_template' ), 99 )", $src );
	twd_sk_has( "add_filter( 'theme_page_templates'", $src );
	TWD_SK_Template::init();
	twd_sk_true( ! empty( $GLOBALS['twd_stub']['hooks']['template_include'] ) );
	twd_sk_true( ! empty( $GLOBALS['twd_stub']['hooks']['theme_page_templates'] ) );
} );

twd_sk_test( 'template: our file is served only for a single page that chose the template', function () {
	twd_sk_tpl_setup();
	twd_sk_eq( '/theme/page.php', TWD_SK_Template::maybe_use_template( '/theme/page.php' ), 'default template untouched' );
	update_post_meta( 12, '_wp_page_template', 'twd-site-kit-page.php' );
	twd_sk_eq( ABSPATH . 'templates/kit-page.php', TWD_SK_Template::maybe_use_template( '/theme/page.php' ) );
	update_post_meta( 12, '_wp_page_template', 'some-theme-template.php' );
	twd_sk_eq( '/theme/page.php', TWD_SK_Template::maybe_use_template( '/theme/page.php' ) );
	update_post_meta( 12, '_wp_page_template', 'twd-site-kit-page.php' );
	$GLOBALS['twd_stub']['singular'] = false;
	twd_sk_eq( '/theme/archive.php', TWD_SK_Template::maybe_use_template( '/theme/archive.php' ), 'not on archives' );
	$GLOBALS['twd_stub']['singular'] = true;
	$GLOBALS['twd_stub']['queried']  = 0;
	twd_sk_eq( '/theme/page.php', TWD_SK_Template::maybe_use_template( '/theme/page.php' ), 'no page being viewed' );
} );

twd_sk_test( 'template file: it calls get_header and get_footer, so the theme header and footer (and an Elementor Theme Builder one) apply', function () {
	twd_sk_tpl_setup();
	TWD_SK_Store::save( 12, '<section class="twd-sk-hero twd-sk-hero--split twd-sk-tone-base"><h1 class="twd-sk-hero__title">Welcome</h1></section>' );
	$out = twd_sk_tpl_render();
	twd_sk_true( 0 === strpos( $out, '[HEADER]' ), 'header first' );
	twd_sk_true( substr_count( $out, '[FOOTER]' ) === 1 && '[FOOTER]' === substr( trim( $out ), -8 ), 'footer last' );
	twd_sk_has( '<main id="content">', $out );
	twd_sk_has( 'class="twd-sk-page"', $out );
	twd_sk_has( 'Welcome', $out );
	$src = file_get_contents( ABSPATH . 'templates/kit-page.php' );
	twd_sk_has( 'get_header();', $src );
	twd_sk_has( 'get_footer();', $src );
	$code = preg_replace( '#/\*.*?\*/#s', '', $src ); // the code, not its comments
	foreach ( array( 'the_content', 'elementor', 'Elementor', 'site-main', 'page-content', 'page-header' ) as $bad ) {
		twd_sk_hasnt( $bad, $code );
	}
} );

twd_sk_test( 'template file: a page whose kit HTML has a hero h1 shows no second title, so there is exactly one h1', function () {
	twd_sk_tpl_setup();
	TWD_SK_Store::save( 12, '<section class="twd-sk-hero twd-sk-hero--split twd-sk-tone-base"><h1 class="twd-sk-hero__title">Welcome</h1></section>' );
	$out = twd_sk_tpl_render();
	twd_sk_eq( 1, preg_match_all( '/<h1\b/', $out ) );
	twd_sk_hasnt( 'Page 12', $out );
} );

twd_sk_test( 'template file: a page with no h1 in its kit HTML (or no content yet) shows the page title as its one h1', function () {
	twd_sk_tpl_setup();
	twd_stub_add_post( 12, 'page', '' );
	get_post( 12 )->post_title = 'My new page <b>&</b>';
	TWD_SK_Store::save( 12, '<section class="twd-sk-text twd-sk-tone-surface"><div class="twd-sk-inner"><h2 class="twd-sk-title">Hello</h2></div></section>' );
	$out = twd_sk_tpl_render();
	twd_sk_eq( 1, preg_match_all( '/<h1\b/', $out ) );
	twd_sk_has( 'twd-sk-hero__title">My new page &lt;b&gt;&amp;&lt;/b&gt;</h1>', $out, 'the title is escaped' );
	twd_stub_reset();
	twd_sk_tpl_setup();
	$out = twd_sk_tpl_render();
	twd_sk_eq( 1, preg_match_all( '/<h1\b/', $out ), 'an empty page still has one h1' );
	twd_sk_has( '<main id="content">', $out );
} );

twd_sk_test( 'template file: the preview draft is what the template shows while previewing', function () {
	twd_sk_tpl_setup();
	$GLOBALS['twd_stub']['caps'] = array( 'edit_pages', 'edit_post:12' );
	TWD_SK_Store::save( 12, '<p>Saved words</p>' );
	TWD_SK_Preview::reset();
	$token = TWD_SK_Preview::create( 7, 12, '<p>Draft words</p>' );
	$_GET['twd_sk_preview'] = $token;
	TWD_SK_Preview::maybe_start();
	$out = twd_sk_tpl_render();
	twd_sk_has( 'Draft words', $out );
	twd_sk_hasnt( 'Saved words', $out );
	unset( $_GET['twd_sk_preview'] );
	TWD_SK_Preview::reset();
} );

twd_sk_test( 'template: a page using the kit template counts as a kit page even before it has content', function () {
	twd_sk_tpl_setup();
	twd_stub_add_post( 40, 'page', '' );
	twd_sk_true( ! TWD_SK_Page::is_kit_page( 40 ) );
	update_post_meta( 40, '_wp_page_template', 'twd-site-kit-page.php' );
	twd_sk_true( TWD_SK_Page::is_kit_page( 40 ) );
} );

twd_sk_test( 'template: create_page makes a draft page with the template, a clean title, and never any content', function () {
	twd_sk_tpl_setup();
	$id = TWD_SK_Template::create_page( "  My   <i>new</i>\npage ", 'blank' );
	twd_sk_true( is_int( $id ) );
	$post = get_post( $id );
	twd_sk_eq( 'My new page', $post->post_title );
	twd_sk_eq( 'draft', $post->post_status );
	twd_sk_eq( '', $post->post_content );
	twd_sk_true( TWD_SK_Template::uses_template( $id ) );
	twd_sk_eq( 'twd-site-kit-page.php', $GLOBALS['twd_stub']['inserted'][0]['page_template'] );
	twd_sk_eq( 'draft', $GLOBALS['twd_stub']['inserted'][0]['post_status'] );
	twd_sk_eq( array( 'blank' => 'Blank page' ), TWD_SK_Template::starters() );
	twd_sk_eq( 0, count( $GLOBALS['twd_stub']['updated'] ), 'never published or updated' );
	twd_sk_eq( '', TWD_SK_Store::get_current( $id ) );
} );

twd_sk_test( 'template: create_page refuses what it should', function () {
	twd_sk_tpl_setup();
	twd_sk_is_error( 'twd_sk_bad_input', TWD_SK_Template::create_page( '', 'blank' ) );
	twd_sk_is_error( 'twd_sk_bad_input', TWD_SK_Template::create_page( '<b></b>', 'blank' ) );
	twd_sk_is_error( 'twd_sk_bad_input', TWD_SK_Template::create_page( str_repeat( 'a', 121 ), 'blank' ) );
	twd_sk_is_error( 'twd_sk_bad_input', TWD_SK_Template::create_page( 'Fine', 'home' ) );
	twd_sk_true( is_int( TWD_SK_Template::create_page( str_repeat( 'a', 120 ), 'blank' ) ), 'exactly 120 is fine' );
	twd_sk_eq( 1, count( $GLOBALS['twd_stub']['inserted'] ) );
} );

twd_sk_test( 'template: other Elementor content is listed, ignoring the shortcode widget and spacers', function () {
	twd_sk_tpl_setup();
	twd_sk_eq( array(), TWD_SK_Template::other_elementor_content( 12 ), 'no data' );
	update_post_meta( 12, '_elementor_data', 'not json' );
	twd_sk_eq( array(), TWD_SK_Template::other_elementor_content( 12 ) );
	update_post_meta( 12, '_elementor_data', json_encode( array( array( 'elType' => 'container', 'elements' => array(
		array( 'elType' => 'widget', 'widgetType' => 'shortcode' ),
		array( 'elType' => 'container', 'elements' => array( array( 'elType' => 'widget', 'widgetType' => 'html' ), array( 'elType' => 'widget', 'widgetType' => 'html' ) ) ),
	) ) ) ) );
	twd_sk_eq( array( 'html' ), TWD_SK_Template::other_elementor_content( 12 ) );
} );

twd_sk_test( 'template: switching refuses a page that does not use the kit and changes nothing', function () {
	twd_sk_tpl_setup();
	twd_stub_add_post( 41, 'page', 'No kit' );
	twd_sk_is_error( 'twd_sk_not_kit_page', TWD_SK_Template::switch_template( 41, true, true ) );
	twd_sk_eq( '', get_post_meta( 41, '_wp_page_template', true ) );
	$ok = TWD_SK_Template::switch_template( 12, true, false );
	twd_sk_eq( true, $ok['uses_template'] );
	// Switching back, and switching back when already default, are both fine.
	$back = TWD_SK_Template::switch_template( 12, false, false );
	twd_sk_eq( false, $back['uses_template'] );
	twd_sk_eq( false, TWD_SK_Template::switch_template( 12, false, false )['uses_template'] );
} );

twd_sk_test( 'template: publish rules (empty page, must fix, check only, override)', function () {
	twd_sk_tpl_setup();
	get_post( 12 )->post_status = 'draft';
	twd_sk_is_error( 'twd_sk_empty_page', TWD_SK_Template::set_status( 12, 'publish', true ) );
	TWD_SK_Store::save( 12, '<p>Phone PHONE_NUMBER</p>' );
	$e = TWD_SK_Template::set_status( 12, 'publish', false );
	twd_sk_is_error( 'twd_sk_leftovers', $e );
	twd_sk_eq( array( 'PHONE_NUMBER' => 1 ), $e->get_error_data()['levels']['must'] );
	twd_sk_eq( 'draft', get_post( 12 )->post_status );
	$ok = TWD_SK_Template::set_status( 12, 'publish', true );
	twd_sk_eq( 'publish', $ok['status'] );
	get_post( 12 )->post_status = 'draft';
	TWD_SK_Store::save( 12, '<a href="/c">Contact me about a first session</a>' );
	$ok = TWD_SK_Template::set_status( 12, 'publish', false );
	twd_sk_eq( array( 'Contact me about a first session' => 1 ), $ok['levels']['check'] );
	twd_sk_eq( array(), $ok['levels']['must'] );
	twd_sk_is_error( 'twd_sk_bad_input', TWD_SK_Template::set_status( 12, 'private', false ) );
	$updates = count( $GLOBALS['twd_stub']['updated'] );
	TWD_SK_Template::set_status( 12, 'publish', false );
	twd_sk_eq( $updates, count( $GLOBALS['twd_stub']['updated'] ), 'no change, no update' );
} );

twd_sk_test( 'template: the posts page and front page cannot be unpublished from here', function () {
	twd_sk_tpl_setup();
	TWD_SK_Store::save( 12, '<p>Real.</p>' );
	twd_sk_true( ! TWD_SK_Template::is_site_page( 12 ) );
	$GLOBALS['twd_stub']['options']['page_for_posts'] = 12;
	twd_sk_true( TWD_SK_Template::is_site_page( 12 ) );
	twd_sk_is_error( 'twd_sk_site_page', TWD_SK_Template::set_status( 12, 'draft', false ) );
	twd_sk_eq( 'publish', get_post( 12 )->post_status );
} );

twd_sk_test( 'template: the class never writes page HTML, options or the network', function () {
	$src = file_get_contents( ABSPATH . 'includes/class-twd-sk-template.php' );
	foreach ( array( '_twd_sk_html', '_twd_sk_versions', 'TWD_SK_Store::save', 'update_option', 'wp_remote_', 'file_put_contents', 'curl_' ) as $bad ) {
		twd_sk_hasnt( $bad, $src );
	}
	twd_sk_hasnt( "\xE2\x80\x94", $src );
	twd_sk_hasnt( "\xE2\x80\x94", file_get_contents( ABSPATH . 'templates/kit-page.php' ) );
} );
