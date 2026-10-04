<?php
// AI suggestions for the header, footer and style: structured values, validated, never saved.

function twd_sk_prop_setup() {
	twd_stub_reset();
	TWD_SK_Profile::save( array( 'site_name' => 'Calm Practice', 'phone' => '01234 567890', 'email' => 'hello@example.com', 'footer_text' => 'Old footer.', 'menu' => array( array( 'label' => 'Home', 'url' => '/', 'children' => array() ) ), 'cta_label' => 'Book', 'cta_url' => '/contact', 'legal' => array( array( 'label' => 'Privacy', 'url' => '/privacy' ) ) ) );
	twd_stub_add_post( 31, 'page', '' );
	twd_stub_add_post( 32, 'page', '' );
	$GLOBALS['twd_stub']['posts'][31]->post_title = 'About';
	$GLOBALS['twd_stub']['posts'][32]->post_title = 'Contact';
}
function twd_sk_prop_answer( $data ) {
	twd_sk_ai_provider( is_string( $data ) ? $data : json_encode( $data ) );
}

twd_sk_test( 'proposals header: valid menu and button values come back as values, with what changed, and nothing is saved', function () {
	twd_sk_prop_setup();
	$before = TWD_SK_Profile::get();
	twd_sk_prop_answer( array( 'menu' => array( array( 'label' => 'Home', 'url' => '/' ), array( 'label' => 'About', 'url' => '/about' ), array( 'label' => 'Contact', 'url' => '/contact' ) ), 'cta_label' => 'Get in touch', 'sticky' => true ) );
	$r = TWD_SK_Proposals::propose( 'header', 'Add About and Contact, and fix the header.' );
	twd_sk_true( ! is_wp_error( $r ) );
	twd_sk_eq( 'header', $r['kind'] );
	$v = (array) $r['values'];
	twd_sk_eq( 3, count( $v['menu'] ) );
	twd_sk_eq( 'About', $v['menu'][1]['label'] );
	twd_sk_eq( 'Get in touch', $v['cta_label'] );
	twd_sk_eq( true, $v['sticky'] );
	twd_sk_true( ! isset( $v['cta_url'] ), 'unchanged fields are not returned' );
	$labels = array_map( function ( $c ) { return $c['label']; }, $r['changed'] );
	twd_sk_eq( array( 'Menu', 'Header button text', 'Fixed header' ), $labels );
	twd_sk_eq( $before, TWD_SK_Profile::get(), 'the profile is untouched' );
	twd_sk_eq( false, TWD_SK_Chrome::settings()['sticky'], 'the settings are untouched' );
	$m = $GLOBALS['twd_stub']['ai_calls'][0]['message'];
	twd_sk_has( 'About: ', $m, 'the page list is sent' );
	twd_sk_has( 'Calm Practice', $m );
	twd_sk_hasnt( '"phone"', $m, 'contact fields are not offered for change' );
	twd_sk_has( 'JSON object', $GLOBALS['twd_stub']['ai_calls'][0]['system'] );
	twd_sk_hasnt( "\xE2\x80\x94", $GLOBALS['twd_stub']['ai_calls'][0]['system'] . $m );
	twd_sk_eq( TWD_SK_Proposals::TOKENS, $GLOBALS['twd_stub']['ai_calls'][0]['tokens'] );
} );

twd_sk_test( 'proposals: a fence or a spoken sentence around the JSON is tolerated, other answers are refused', function () {
	twd_sk_prop_setup();
	twd_sk_prop_answer( "Sure, here you go:\n```json\n" . json_encode( array( 'cta_label' => 'Call me' ) ) . "\n```" );
	$r = TWD_SK_Proposals::propose( 'header', 'Change the button.' );
	twd_sk_eq( 'Call me', ( (array) $r['values'] )['cta_label'] );
	twd_sk_prop_answer( 'I cannot do that.' );
	twd_sk_is_error( 'twd_sk_ai_bad_result', TWD_SK_Proposals::propose( 'header', 'x' ) );
	twd_sk_prop_answer( '[1,2,3]' );
	twd_sk_is_error( 'twd_sk_ai_bad_result', TWD_SK_Proposals::propose( 'header', 'x' ) );
	twd_sk_ai_provider( new WP_Error( 'twd_ap_ai_bad_key', 'Rejected.' ) );
	twd_sk_is_error( 'twd_ap_ai_bad_key', TWD_SK_Proposals::propose( 'header', 'x' ) );
	twd_sk_ai_provider( null );
	twd_sk_is_error( 'twd_sk_ai_unavailable', TWD_SK_Proposals::propose( 'header', 'x' ) );
	twd_sk_ai_provider( 'x', false );
	twd_sk_is_error( 'twd_sk_ai_unavailable', TWD_SK_Proposals::propose( 'header', 'x' ) );
	twd_sk_ai_provider( '{}' );
	twd_sk_is_error( 'twd_sk_bad_input', TWD_SK_Proposals::propose( 'header', '   ' ) );
	twd_sk_is_error( 'twd_sk_bad_input', TWD_SK_Proposals::propose( 'body', 'x' ) );
} );

twd_sk_test( 'proposals: invalid values are dropped with a plain sentence, and things that cannot change here are ignored and said so', function () {
	twd_sk_prop_setup();
	twd_sk_prop_answer( array(
		'menu'           => array( array( 'label' => 'Bad', 'url' => 'javascript:alert(1)' ) ),
		'cta_label'      => 'Fine label',
		'header_variant' => 'wild',
		'phone'          => '07000 000000',
		'email'          => 'invented@example.com',
	) );
	$r = TWD_SK_Proposals::propose( 'header', 'Do things.' );
	$v = (array) $r['values'];
	twd_sk_eq( array( 'cta_label' ), array_keys( $v ), 'only the valid field survives' );
	$e = implode( ' ', $r['errors'] );
	twd_sk_has( 'Menu:', $e );
	twd_sk_has( 'That change was dropped', $e );
	twd_sk_has( 'Header layout:', $e );
	twd_sk_has( 'phone', $e );
	twd_sk_has( 'email', $e );
	twd_sk_has( 'ignored', $e );
	$all = json_encode( $r );
	twd_sk_hasnt( '07000 000000', $all );
	twd_sk_hasnt( 'invented@example.com', $all );
	twd_sk_hasnt( 'javascript:', $all );
} );

twd_sk_test( 'proposals footer: text, legal links and layout, validated like the Site tab', function () {
	twd_sk_prop_setup();
	twd_sk_prop_answer( array( 'footer_text' => "Gentle <b>therapy</b> \xE2\x80\x94 for men.", 'legal' => array( array( 'label' => 'Privacy policy', 'url' => '/privacy-policy' ), array( 'label' => 'Terms', 'url' => '/terms' ) ), 'footer_variant' => 'band', 'footer_columns' => 2 ) );
	$r = TWD_SK_Proposals::propose( 'footer', 'Tidy the footer.' );
	$v = (array) $r['values'];
	twd_sk_hasnt( '<', $v['footer_text'] );
	twd_sk_hasnt( "\xE2\x80\x94", $v['footer_text'] );
	twd_sk_eq( 2, count( $v['legal'] ) );
	twd_sk_eq( 'band', $v['footer_variant'] );
	twd_sk_eq( 2, $v['footer_columns'] );
	twd_sk_prop_answer( array( 'footer_columns' => 9, 'menu' => array( array( 'label' => 'x', 'url' => '/x' ) ) ) );
	$r = TWD_SK_Proposals::propose( 'footer', 'x' );
	twd_sk_eq( array(), (array) $r['values'] );
	twd_sk_has( 'Footer columns:', implode( ' ', $r['errors'] ) );
	twd_sk_has( 'menu', implode( ' ', $r['errors'] ), 'a header field is not accepted for a footer proposal' );
	twd_sk_eq( array(), $r['changed'] );
} );

twd_sk_test( 'proposals style: colours, fonts, corners and a pack are validated, the readable check can block, nothing is saved', function () {
	twd_sk_prop_setup();
	$active = TWD_SK_Packs::active_slug();
	$other  = 'sage' === $active ? 'grove' : 'sage';
	twd_sk_prop_answer( array( 'pack' => $other, 'tokens' => array( 'color-primary' => '#224466', 'radius-btn' => '23px', 'font-heading' => 'Not A Font', 'color-nope' => '#000000' ) ) );
	$r = TWD_SK_Proposals::propose( 'style', 'Cooler and sharper.' );
	twd_sk_true( ! is_wp_error( $r ) );
	$v = (array) $r['values'];
	twd_sk_eq( $other, $v['pack'] );
	twd_sk_eq( '#224466', $v['tokens']['color-primary'] );
	twd_sk_eq( '23px', $v['tokens']['radius-btn'] );
	twd_sk_true( ! isset( $v['tokens']['font-heading'] ), 'an unknown font is dropped' );
	$e = implode( ' ', $r['errors'] );
	twd_sk_has( 'Heading font:', $e );
	twd_sk_has( 'color-nope', $e );
	twd_sk_eq( $active, TWD_SK_Packs::active_slug(), 'the active style is untouched' );
	twd_sk_eq( array(), TWD_SK_Packs::overrides(), 'no overrides were stored' );
	twd_sk_eq( array(), $r['blocking'] );

	// Dark button text on a dark button is hard to read: reported, never silently saved.
	twd_sk_prop_answer( array( 'tokens' => array( 'color-primary' => '#111111', 'color-on-primary' => '#222222' ) ) );
	$r = TWD_SK_Proposals::propose( 'style', 'Make the buttons dark.' );
	twd_sk_true( count( $r['blocking'] ) > 0, 'unreadable button text is reported' );
	twd_sk_has( 'needs 4.5:1', $r['blocking'][0] );
	twd_sk_eq( array(), TWD_SK_Packs::overrides() );

	twd_sk_prop_answer( array( 'pack' => 'ghost' ) );
	$r = TWD_SK_Proposals::propose( 'style', 'x' );
	twd_sk_eq( array(), $r['changed'] );
	twd_sk_has( 'does not exist', implode( ' ', $r['errors'] ) );
	$m = $GLOBALS['twd_stub']['ai_calls'][0]['message'];
	twd_sk_has( 'Fonts you may use:', $m );
	twd_sk_has( 'color-primary (Button colour):', $m );
} );

twd_sk_test( 'proposals rest: administrators only, tied to the AI limit, errors become plain statuses', function () {
	twd_sk_prop_setup();
	$GLOBALS['twd_stub']['user'] = 7;
	$GLOBALS['twd_stub']['caps'] = array( 'edit_pages' );
	twd_sk_eq( 403, twd_sk_status( TWD_SK_REST::can_propose_site( twd_sk_rest_req() ) ), 'an editor is refused' );
	$GLOBALS['twd_stub']['caps'] = array( 'edit_pages', 'manage_options' );
	twd_sk_eq( 401, twd_sk_status( TWD_SK_REST::can_propose_site( twd_sk_rest_req( array(), null ) ) ) );
	twd_sk_eq( true, TWD_SK_REST::can_propose_site( twd_sk_rest_req() ) );
	twd_sk_prop_answer( array( 'cta_label' => 'Hello' ) );
	$ok = TWD_SK_REST::post_site_propose( twd_sk_rest_req( array( 'kind' => 'header', 'instruction' => 'Change the button.' ) ) );
	twd_sk_eq( 'header', $ok['kind'] );
	twd_sk_eq( 400, twd_sk_status( TWD_SK_REST::post_site_propose( twd_sk_rest_req( array( 'kind' => 'header', 'instruction' => '' ) ) ) ) );
	twd_sk_ai_provider( new WP_Error( 'twd_ap_ai_bad_key', 'Rejected.' ) );
	twd_sk_eq( 502, twd_sk_status( TWD_SK_REST::post_site_propose( twd_sk_rest_req( array( 'kind' => 'header', 'instruction' => 'x' ) ) ) ) );
	twd_sk_ai_provider( null, false );
	twd_sk_eq( 503, twd_sk_status( TWD_SK_REST::post_site_propose( twd_sk_rest_req( array( 'kind' => 'style', 'instruction' => 'x' ) ) ) ) );
	for ( $i = 0; $i < 12; $i++ ) {
		$gate = TWD_SK_REST::can_propose_site( twd_sk_rest_req() );
	}
	twd_sk_is_error( 'twd_sk_rate_limited', $gate );
} );

twd_sk_test( 'proposals: no key, no web call, and no code or CSS is ever accepted from the AI', function () {
	$php = file_get_contents( ABSPATH . 'includes/class-twd-sk-proposals.php' );
	foreach ( array( 'wp_remote_', 'curl_', 'api.anthropic.com', 'x-api-key', 'sk-ant', 'update_option', 'update_post_meta', 'wp_insert_post' ) as $bad ) {
		twd_sk_hasnt( $bad, $php );
	}
} );

twd_sk_test( 'proposals js: only through the editor api, fills the existing boxes, no addresses, no injection, only when AI is available', function () {
	$js = file_get_contents( ABSPATH . 'assets/twd-site-kit-editor-site.js' );
	twd_sk_has( "api('POST', '/site/propose'", $js );
	twd_sk_has( 'ED.cfg.aiAvailable', $js );
	twd_sk_has( 'Ask the AI to change the header', $js );
	twd_sk_has( 'Ask the AI to change the footer', $js );
	twd_sk_has( 'Ask the AI to change the style', $js );
	twd_sk_has( 'Check every word before you save', $js );
	twd_sk_has( 'It cannot be saved as it stands', $js );
	twd_sk_hasnt( 'innerHTML', $js );
	twd_sk_hasnt( 'fetch(', $js );
	twd_sk_true( 1 !== preg_match( '#https?://#i', $js ), 'no web addresses' );
	twd_sk_true( 1 !== preg_match( '/(^|[^.\w])(alert|confirm|prompt)\s*\(/m', $js ), 'no native dialogs' );
	// A proposal fills form boxes and the style preview; it never posts to a save route by itself.
	twd_sk_true( 1 !== preg_match( "/propose[^;]*\\.then\\([^)]*api\\('POST', '\\/site\\/(profile|chrome|style)/s", $js ) );
} );
