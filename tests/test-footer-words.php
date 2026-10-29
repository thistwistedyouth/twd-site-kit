<?php
// Footer words (session 7, 29 Oct 2026, section 21b): a per-site tick lets clients change the footer text from the
// Simple editor; the route records the change in the Site changes history first, so Undo the last site change takes
// it back; administrators have one clearly named way to the header and footer settings in Your Edits.

twd_sk_test( 'footer words: the tick is a header and footer setting, off by default, on or off only, never on in safe mode', function () {
	twd_stub_reset();
	twd_sk_eq( false, TWD_SK_Chrome::defaults()['client_footer_text'] );
	twd_sk_true( ! TWD_SK_Chrome::footer_text_for_clients() );
	twd_sk_eq( true, TWD_SK_Chrome::validate( array( 'client_footer_text' => '1' ) )['valid']['client_footer_text'] );
	twd_sk_eq( 1, count( TWD_SK_Chrome::validate( array( 'client_footer_text' => 'yes' ) )['errors'] ) );
	TWD_SK_Chrome::save_settings( array( 'client_footer_text' => true ) );
	twd_sk_true( TWD_SK_Chrome::footer_text_for_clients() );
	TWD_SK_Safe::set( true );
	twd_sk_true( ! TWD_SK_Chrome::footer_text_for_clients(), 'safe mode: off' );
	TWD_SK_Safe::set( false );
	// It is part of the site state, so the Site changes history covers it.
	twd_sk_eq( true, TWD_SK_Style::site_state()['chrome']['client_footer_text'] );
} );

twd_sk_test( 'footer words: the route is for administrators always, and for an editor only while the tick is on', function () {
	twd_sk_rest_setup( array( 'edit_pages', 'edit_post:12' ) );
	$e = TWD_SK_REST::can_edit_footer_text( twd_sk_rest_req() );
	twd_sk_is_error( 'twd_sk_forbidden', $e );
	twd_sk_eq( 403, twd_sk_status( $e ) );
	TWD_SK_Chrome::save_settings( array( 'client_footer_text' => true ) );
	twd_sk_eq( true, TWD_SK_REST::can_edit_footer_text( twd_sk_rest_req() ) );
	twd_sk_rest_setup( array( 'read' ) );
	twd_sk_is_error( 'twd_sk_forbidden', TWD_SK_REST::can_edit_footer_text( twd_sk_rest_req() ), 'no edit rights at all' );
	twd_sk_rest_setup( array( 'edit_pages', 'manage_options' ) );
	TWD_SK_Chrome::save_settings( array( 'client_footer_text' => false ) );
	twd_sk_eq( true, TWD_SK_REST::can_edit_footer_text( twd_sk_rest_req() ), 'an administrator whatever the tick' );
	twd_sk_rest_setup( array( 'edit_pages', 'edit_post:12' ) );
	twd_sk_is_error( 'twd_sk_bad_nonce', TWD_SK_REST::can_edit_footer_text( twd_sk_rest_req( array(), null ) ) );
} );

twd_sk_test( 'footer words: a save is recorded in the Site changes history first, validated like the Site details box, and comes back as text and HTML', function () {
	twd_sk_rest_setup( array( 'edit_pages', 'edit_post:12' ) );
	TWD_SK_Profile::save( array( 'footer_text' => 'Calm rooms in town.' ) );
	$out = TWD_SK_REST::post_site_footer_text( twd_sk_rest_req( array( 'text' => "Calm rooms in town.\nOpen <strong>six days</strong> a week.<script>x()</script>" ) ) );
	twd_sk_true( is_array( $out ), is_wp_error( $out ) ? $out->get_error_message() : '' );
	twd_sk_eq( "Calm rooms in town.\nOpen <strong>six days</strong> a week.", $out['text'] );
	twd_sk_eq( 'Calm rooms in town.<br>Open <strong>six days</strong> a week.', $out['html'] );
	twd_sk_eq( false, $out['unchanged'] );
	twd_sk_eq( $out['text'], TWD_SK_Profile::get()['footer_text'] );
	$hist = TWD_SK_Style::site_history();
	twd_sk_eq( 'Footer text changed by the client', $hist[0]['label'] );
	twd_sk_eq( 'Calm rooms in town.', $hist[0]['state']['profile']['footer_text'], 'the words before, so Undo brings them back' );
	// Undo the last site change puts the earlier words back.
	$u = TWD_SK_Style::site_undo();
	twd_sk_true( is_array( $u ), is_wp_error( $u ) ? $u->get_error_message() : '' );
	twd_sk_eq( 'Calm rooms in town.', TWD_SK_Profile::get()['footer_text'] );
	// The same words again: nothing recorded.
	$same = TWD_SK_REST::post_site_footer_text( twd_sk_rest_req( array( 'text' => 'Calm rooms in town.' ) ) );
	twd_sk_eq( true, $same['unchanged'] );
	twd_sk_eq( 400, twd_sk_status( TWD_SK_REST::post_site_footer_text( twd_sk_rest_req( array( 'text' => array( 'no' ) ) ) ) ) );
	// An administrator's save is labelled plainly.
	twd_sk_rest_setup( array( 'edit_pages', 'manage_options' ) );
	TWD_SK_REST::post_site_footer_text( twd_sk_rest_req( array( 'text' => 'New words.' ) ) );
	twd_sk_eq( 'Footer text changed', TWD_SK_Style::site_history()[0]['label'] );
} );

twd_sk_test( 'footer words: the editor offers the box only in the Simple editor on a kit page while the tick is on; the Site tab and Your Edits carry the new pieces', function () {
	$site = file_get_contents( ABSPATH . 'assets/twd-site-kit-editor-site.js' );
	twd_sk_has( "id: 'chrome'", $site, 'a Your Edits entry' );
	twd_sk_has( "'Header and footer'", $site );
	twd_sk_has( "ED.openAt('site', 'twd-sk-chrome-ways')", $site, 'it opens the Header and footer section' );
	twd_sk_has( "twd-sk-ch-clientfooter", $site, 'the tick box' );
	twd_sk_has( "client_footer_text: cui.clientFooter.checked", $site, 'saved with the settings' );
	twd_sk_has( "cui.clientFooter.checked = !!c.settings.client_footer_text", $site, 'filled from them' );
	twd_sk_has( "cui.undoBtn = button('Undo the last site change'", $site, 'Undo in the section' );
	$inline = file_get_contents( ABSPATH . 'assets/twd-site-kit-inline.js' );
	twd_sk_has( "cfg.footerEdit", $inline );
	twd_sk_has( "'/site/footer-text'", $inline );
	twd_sk_has( "toolButton('Page details'", $inline );
	twd_sk_has( "'/pages/' + cfg.pageId + '/seo'", $inline, 'the details are read from the search route' );
	twd_sk_has( "body.details = detailsNow()", $inline, 'and saved with the page' );
	twd_sk_hasnt( 'innerHTML', $inline );
	$editor = file_get_contents( ABSPATH . 'includes/class-twd-sk-editor.php' );
	twd_sk_has( "'footerEdit'     => \$simple && (bool) \$context['is_kit_page'] && TWD_SK_Chrome::footer_text_for_clients()", $editor );
} );
