<?php
// TWD_SK_Guard (0.14.0): publishing from wp-admin is held back while a kit page has must-fix text; the edit screen says where a kit page is edited.

function twd_sk_guard_page( $html ) {
	twd_stub_reset();
	$GLOBALS['twd_stub']['caps'] = true;
	twd_stub_add_post( 31, 'page', '[twd_page]' );
	$GLOBALS['twd_stub']['posts'][31]->post_status = 'draft';
	$GLOBALS['twd_stub']['posts'][31]->post_title  = 'A kit page';
	TWD_SK_Store::save( 31, $html );
	TWD_SK_Guard::$allow = false;
}

twd_sk_test( 'guard: a kit page with must-fix text is kept as a draft when published from wp-admin, with a notice; a clean page publishes', function () {
	twd_sk_guard_page( '<section class="twd-sk-hero"><h1 class="twd-sk-hero__title">[PLACEHOLDER: Q09 registration number]</h1></section>' );
	$data = TWD_SK_Guard::hold_back( array( 'post_type' => 'page', 'post_status' => 'publish' ), array( 'ID' => 31 ) );
	twd_sk_eq( 'draft', $data['post_status'] );
	$held = get_transient( TWD_SK_Guard::TRANSIENT );
	twd_sk_eq( 1, $held[31]['count'] );
	twd_sk_has( '[PLACEHOLDER', $held[31]['what'] );
	twd_sk_eq( 7, $held[31]['user'] );
	ob_start();
	TWD_SK_Guard::notices();
	$out = ob_get_clean();
	twd_sk_has( 'kept as a draft', $out );
	twd_sk_has( 'publish it anyway', $out );
	twd_sk_has( 'action=twd_sk_publish_anyway&amp;post=31', $out );
	twd_sk_eq( false, get_transient( TWD_SK_Guard::TRANSIENT ), 'shown once' );
	// A page with nothing to fix is not touched.
	twd_sk_guard_page( '<section class="twd-sk-hero"><h1 class="twd-sk-hero__title">Welcome to an invented practice</h1></section>' );
	$data = TWD_SK_Guard::hold_back( array( 'post_type' => 'page', 'post_status' => 'publish' ), array( 'ID' => 31 ) );
	twd_sk_eq( 'publish', $data['post_status'] );
} );

twd_sk_test( 'guard: the kit\'s own publish path (the Pages tab, the launch check, WP-CLI) is let through, and ordinary posts, other pages and updates to a live page are untouched', function () {
	twd_sk_guard_page( '<section class="twd-sk-hero"><h1 class="twd-sk-hero__title">Heading here</h1></section>' );
	// Our own route with the explicit override: the flag is set around the update, so the guard stands aside.
	$r = TWD_SK_Template::set_status( 31, 'publish', true );
	twd_sk_true( ! is_wp_error( $r ), 'override publishes' );
	twd_sk_eq( 'publish', get_post( 31 )->post_status );
	twd_sk_true( ! TWD_SK_Guard::$allow, 'the flag is put back' );
	// An update to a page that is already live is not a publish.
	$data = TWD_SK_Guard::hold_back( array( 'post_type' => 'page', 'post_status' => 'publish' ), array( 'ID' => 31 ) );
	twd_sk_eq( 'publish', $data['post_status'] );
	// A post, and a page that does not use the kit.
	twd_stub_add_post( 32, 'post', 'words' );
	twd_sk_eq( 'publish', TWD_SK_Guard::hold_back( array( 'post_type' => 'post', 'post_status' => 'publish' ), array( 'ID' => 32 ) )['post_status'] );
	twd_stub_add_post( 33, 'page', 'An ordinary page with Heading here in it' );
	twd_sk_eq( 'publish', TWD_SK_Guard::hold_back( array( 'post_type' => 'page', 'post_status' => 'publish' ), array( 'ID' => 33 ) )['post_status'] );
	// Without the override our own path still refuses, so nothing changes either way.
	twd_sk_guard_page( '<section class="twd-sk-hero"><h1 class="twd-sk-hero__title">Heading here</h1></section>' );
	twd_sk_is_error( 'twd_sk_leftovers', TWD_SK_Template::set_status( 31, 'publish', false ) );
	twd_sk_eq( 'draft', get_post( 31 )->post_status );
} );

twd_sk_test( 'guard: the edit screen of a kit page says it is edited on the site, with a link; other screens get nothing', function () {
	twd_sk_guard_page( '<section class="twd-sk-hero"><h1 class="twd-sk-hero__title">Welcome</h1></section>' );
	$GLOBALS['twd_stub']['screen'] = 'post';
	$_GET['post'] = '31';
	ob_start();
	TWD_SK_Guard::notices();
	$out = ob_get_clean();
	twd_sk_has( 'edited on the site itself', $out );
	twd_sk_has( 'Visual editor', $out );
	twd_sk_has( 'href="https://example.test/?page_id=31"', $out );
	$_GET['post'] = '33';
	twd_stub_add_post( 33, 'page', 'ordinary' );
	ob_start();
	TWD_SK_Guard::notices();
	twd_sk_eq( '', ob_get_clean(), 'not a kit page' );
	unset( $_GET['post'] );
	$GLOBALS['twd_stub']['screen'] = 'plugins';
} );

twd_sk_test( 'guard: started from the modules (so off in safe mode) and hooked only in admin', function () {
	twd_sk_has( 'TWD_SK_Guard::init()', file_get_contents( ABSPATH . 'includes/class-twd-sk-modules.php' ) );
	twd_stub_reset();
	$GLOBALS['twd_stub']['admin'] = false;
	TWD_SK_Guard::init();
	twd_sk_true( empty( $GLOBALS['twd_stub']['hooks']['wp_insert_post_data'] ), 'nothing on the front end' );
	$GLOBALS['twd_stub']['admin'] = true;
	TWD_SK_Guard::init();
	twd_sk_true( ! empty( $GLOBALS['twd_stub']['hooks']['wp_insert_post_data'] ) );
} );

// ---- Elementor and a kit page (0.14.1) ----

function twd_sk_guard_elementor_page() {
	twd_sk_guard_page( '<section class="twd-sk-hero"><h1 class="twd-sk-hero__title">Welcome</h1></section>' );
	twd_stub_add_post( 33, 'page', 'An ordinary page with nothing from the kit' );
	$GLOBALS['twd_stub']['caps']   = array( 'edit_pages', 'edit_post:31', 'edit_post:33' );
	$_REQUEST = array( 'post' => '31', 'action' => 'elementor' );
	$_GET     = array( 'post' => '31', 'action' => 'elementor' );
}
function twd_sk_guard_elementor_door() {
	try {
		TWD_SK_Guard::block_elementor_editor();
	} catch ( TWD_SK_Stub_Die $e ) {
		return $e->getMessage();
	}
	return null;
}

twd_sk_test( 'guard elementor: the editor door shows a plain card on a kit page, with a way back and no way on for an editor', function () {
	twd_sk_guard_elementor_page();
	$card = twd_sk_guard_elementor_door();
	twd_sk_true( null !== $card, 'Elementor\'s editor is replaced' );
	twd_sk_has( 'This page is edited on the page itself', $card );
	twd_sk_has( 'press Visual editor', $card );
	twd_sk_has( 'TWD Kit Page template', $card );
	twd_sk_has( 'href="https://example.test/?page_id=31"', $card, 'a link back to the page' );
	twd_sk_hasnt( 'open Elementor anyway', $card, 'an editor cannot carry on' );
	twd_sk_hasnt( 'twd_sk_elementor_ok', $card );
	twd_sk_eq( 200, $GLOBALS['twd_stub']['die_args']['response'] );
	twd_sk_true( 1 !== preg_match( '/[\x{2013}\x{2014}]/u', $card ), 'no em or en dashes' );
	// Even with the administrator's link and a good nonce, an editor is stopped.
	$_GET['twd_sk_elementor_ok'] = '1';
	$_GET['_wpnonce']            = 'good-nonce';
	$GLOBALS['twd_stub']['nonce_actions'] = array( 'twd_sk_elementor_ok_31' );
	twd_sk_true( null !== twd_sk_guard_elementor_door(), 'the override needs manage_options' );
} );

twd_sk_test( 'guard elementor: an administrator sees the same card with one more click, and only that exact link (this page, a good nonce) lets Elementor open', function () {
	twd_sk_guard_elementor_page();
	$GLOBALS['twd_stub']['caps'] = true;
	$card = twd_sk_guard_elementor_door();
	twd_sk_true( null !== $card );
	twd_sk_has( 'Administrators only:', $card );
	twd_sk_has( 'open Elementor anyway', $card );
	twd_sk_has( 'post.php?post=31&amp;action=elementor&amp;twd_sk_elementor_ok=1&amp;_wpnonce=NONCE_twd_sk_elementor_ok_31', $card, 'the link names this page and carries its own nonce action' );
	// The link is followed.
	$GLOBALS['twd_stub']['nonce_actions'] = array( 'twd_sk_elementor_ok_31' );
	$_GET['twd_sk_elementor_ok'] = '1';
	$_GET['_wpnonce']            = 'good-nonce';
	twd_sk_eq( null, twd_sk_guard_elementor_door(), 'Elementor may open' );
	// A wrong nonce, no nonce, a nonce for another page, and ok=0 all still show the card.
	$_GET['_wpnonce'] = 'bad';
	twd_sk_true( null !== twd_sk_guard_elementor_door(), 'wrong nonce' );
	unset( $_GET['_wpnonce'] );
	twd_sk_true( null !== twd_sk_guard_elementor_door(), 'no nonce' );
	$_GET['_wpnonce'] = 'good-nonce';
	$GLOBALS['twd_stub']['nonce_actions'] = array( 'twd_sk_elementor_ok_99' );
	twd_sk_true( null !== twd_sk_guard_elementor_door(), 'a nonce for another page' );
	$GLOBALS['twd_stub']['nonce_actions'] = array( 'twd_sk_elementor_ok_31' );
	$_GET['twd_sk_elementor_ok'] = '0';
	twd_sk_true( null !== twd_sk_guard_elementor_door(), 'ok is not 1' );
	$GLOBALS['twd_stub']['nonce_actions'] = array();
} );

twd_sk_test( 'guard elementor: a page that is not a kit page, a missing page and a request with no page are never touched', function () {
	twd_sk_guard_elementor_page();
	$GLOBALS['twd_stub']['caps'] = true;
	$_REQUEST['post'] = '33';
	twd_sk_eq( null, twd_sk_guard_elementor_door(), 'an ordinary page opens in Elementor as usual' );
	$_REQUEST['post'] = '999';
	twd_sk_eq( null, twd_sk_guard_elementor_door(), 'a page that does not exist' );
	unset( $_REQUEST['post'] );
	twd_sk_eq( null, twd_sk_guard_elementor_door(), 'no page given' );
	$_REQUEST['post'] = '-31';
	twd_sk_true( null !== twd_sk_guard_elementor_door(), 'a negative number is made positive first (absint), so it can only ever mean page 31, never skip the check' );
	$_REQUEST = array();
	$_GET     = array();
} );

twd_sk_test( 'guard elementor: the Edit with Elementor link in the pages list and the classic edit screen button are hidden on kit pages from an editor, and kept for an administrator and on other pages', function () {
	twd_sk_guard_elementor_page();
	$kit   = get_post( 31 );
	$plain = get_post( 33 );
	$acts  = array( 'edit' => '<a>Edit</a>', 'edit_with_elementor' => '<a>Edit with Elementor</a>', 'trash' => '<a>Trash</a>' );
	$out   = TWD_SK_Guard::row_actions( $acts, $kit );
	twd_sk_true( ! isset( $out['edit_with_elementor'] ), 'hidden from an editor on a kit page' );
	twd_sk_eq( array( 'edit', 'trash' ), array_keys( $out ), 'nothing else is touched' );
	twd_sk_true( isset( TWD_SK_Guard::row_actions( $acts, $plain )['edit_with_elementor'] ), 'kept on another page' );
	$only = array( 'edit' => '<a>Edit</a>' );
	twd_sk_eq( $only, TWD_SK_Guard::row_actions( $only, $kit ), 'no link to remove, no change' );
	$GLOBALS['twd_stub']['caps'] = true;
	twd_sk_true( isset( TWD_SK_Guard::row_actions( $acts, $kit )['edit_with_elementor'] ), 'kept for an administrator' );

	// The big button: removed from Elementor's own admin object, only for an editor on a kit page.
	$GLOBALS['twd_stub']['caps']            = array( 'edit_pages', 'edit_post:31', 'edit_post:33' );
	$GLOBALS['twd_stub']['removed_actions'] = array();
	TWD_SK_Guard::hide_elementor_button( $kit );
	twd_sk_eq( array(), $GLOBALS['twd_stub']['removed_actions'], 'with no Elementor active there is nothing to remove' );
	if ( ! class_exists( 'Elementor\Plugin' ) ) {
		eval( 'namespace Elementor { class Plugin { public static $instance; } class StubAdmin { public function print_switch_mode_button() {} } }' ); // phpcs:ignore Squiz.PHP.Eval.Discouraged
	}
	\Elementor\Plugin::$instance        = new stdClass();
	\Elementor\Plugin::$instance->admin = new \Elementor\StubAdmin();
	TWD_SK_Guard::hide_elementor_button( $kit );
	$removed = $GLOBALS['twd_stub']['removed_actions'];
	twd_sk_eq( 1, count( $removed ) );
	twd_sk_eq( 'edit_form_after_title', $removed[0]['hook'] );
	twd_sk_eq( 'print_switch_mode_button', $removed[0]['callback'][1] );
	twd_sk_eq( 10, $removed[0]['priority'], 'Elementor adds it at the default priority' );
	$GLOBALS['twd_stub']['removed_actions'] = array();
	TWD_SK_Guard::hide_elementor_button( $plain );
	twd_sk_eq( array(), $GLOBALS['twd_stub']['removed_actions'], 'not on another page' );
	$GLOBALS['twd_stub']['caps'] = true;
	TWD_SK_Guard::hide_elementor_button( $kit );
	twd_sk_eq( array(), $GLOBALS['twd_stub']['removed_actions'], 'not for an administrator' );
	\Elementor\Plugin::$instance = null;
} );

twd_sk_test( 'guard elementor: hooked on the editor door at priority 1 (before Elementor\'s 10), the row actions at 12 (after its 11) and the edit screen at 1, only in admin', function () {
	twd_stub_reset();
	$GLOBALS['twd_stub']['admin'] = false;
	TWD_SK_Guard::init();
	twd_sk_true( empty( $GLOBALS['twd_stub']['hooks']['admin_action_elementor'] ), 'nothing on the front end' );
	$GLOBALS['twd_stub']['admin'] = true;
	TWD_SK_Guard::init();
	foreach ( array( 'admin_action_elementor' => 1, 'page_row_actions' => 12, 'edit_form_after_title' => 1 ) as $hook => $priority ) {
		twd_sk_true( ! empty( $GLOBALS['twd_stub']['hooks'][ $hook ] ), $hook . ' is hooked' );
		twd_sk_eq( $priority, $GLOBALS['twd_stub']['hook_priority'][ $hook ][0], $hook . ' priority' );
	}
	$src = file_get_contents( ABSPATH . 'includes/class-twd-sk-guard.php' );
	twd_sk_hasnt( 'wp_redirect', $src, 'no redirect to be followed or abused' );
	twd_sk_hasnt( "\$_REQUEST['post']\n", $src );
	twd_sk_has( 'absint( $_REQUEST[\'post\'] )', $src, 'the page number is a whole number' );
} );
