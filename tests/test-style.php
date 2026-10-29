<?php
// TWD_SK_Style (0.14.0): style kits, snapshots, apply with a dry run, revert, a layout swap, and the admin-only preview.
// A restyle changes tokens and the header and footer settings only: a page's words, pictures and sections are never touched.

function twd_sk_style_site() {
	twd_stub_reset();
	TWD_SK_Safe::set( false );
	TWD_SK_Style::reset_preview();
	$GLOBALS['twd_stub']['caps']  = true;
	$GLOBALS['twd_stub']['admin'] = false;
	twd_stub_add_post( 41, 'page', '[twd_page]' );
	$html = '<section class="twd-sk-hero twd-sk-hero--split"><div class="twd-sk-hero__grid"><div class="twd-sk-hero__text"><h1 class="twd-sk-hero__title">Walking therapy in an invented valley</h1></div><div class="twd-sk-hero__media"><img class="twd-sk-hero__img" src="/wp-content/uploads/2026/01/valley.jpg" alt="A path through trees"></div></div></section>'
		. '<section class="twd-sk-text"><div class="twd-sk-inner"><div class="twd-sk-text__body"><h2>How it works</h2><p>We walk and we talk.</p></div></div></section>';
	TWD_SK_Store::save( 41, $html );
	return TWD_SK_Store::get_current( 41 );
}

twd_sk_test( 'style: read_kit accepts pack, tokens and chrome (header and footer as plain words), ignores unknown fields with a note, refuses a wrong schema, an unknown pack, a bad token and a bad layout', function () {
	twd_sk_style_site();
	$kit = TWD_SK_Style::read_kit( array(
		'schema' => TWD_SK_Style::SCHEMA,
		'name'   => 'Warm evening',
		'pack'   => 'grove',
		'tokens' => array( 'color-primary' => '#1F5F5B' ),
		'chrome' => array( 'header' => 'centered', 'footer' => 'band', 'placement' => 'elementor', 'sticky' => true ),
		'mood'   => 'calm',
	) );
	twd_sk_true( ! is_wp_error( $kit ), 'kit reads' );
	twd_sk_eq( 'grove', $kit['pack'] );
	twd_sk_eq( 'Warm evening', $kit['name'] );
	twd_sk_eq( array( 'color-primary' => '#1F5F5B' ), $kit['tokens'] );
	twd_sk_eq( 'centered', $kit['chrome']['header_variant'] );
	twd_sk_eq( 'band', $kit['chrome']['footer_variant'] );
	twd_sk_true( ! isset( $kit['chrome']['placement'] ), 'who places the header is never part of a look' );
	twd_sk_true( $kit['chrome']['sticky'] );
	twd_sk_eq( 1, count( $kit['notes'] ) );
	twd_sk_has( '"mood"', $kit['notes'][0] );

	twd_sk_true( is_wp_error( TWD_SK_Style::read_kit( array( 'schema' => 'twd-site-brief/1' ) ) ), 'wrong schema' );
	twd_sk_true( is_wp_error( TWD_SK_Style::read_kit( 'grove' ) ), 'not a set of fields' );
	$e = TWD_SK_Style::read_kit( array( 'pack' => 'neon' ) );
	twd_sk_eq( 'twd_sk_unknown_pack', $e->get_error_code() );
	twd_sk_has( 'ink', $e->get_error_message(), 'lists the real packs' );
	$e = TWD_SK_Style::read_kit( array( 'tokens' => array( 'color-primary' => 'url(x)' ) ) );
	twd_sk_eq( 'twd_sk_bad_tokens', $e->get_error_code() );
	$e = TWD_SK_Style::read_kit( array( 'chrome' => array( 'header' => 'floating' ) ) );
	twd_sk_eq( 'twd_sk_bad_chrome', $e->get_error_code() );
	// An empty kit is fine: it means "keep the pack, change nothing".
	$kit = TWD_SK_Style::read_kit( array() );
	twd_sk_eq( '', $kit['pack'] );
	twd_sk_eq( array(), $kit['tokens'] );
} );

twd_sk_test( 'style: a dry run lists every change (pack, each token that differs, header and footer) and the contrast result, and saves nothing', function () {
	twd_sk_style_site();
	$kit = TWD_SK_Style::read_kit( array( 'pack' => 'grove', 'chrome' => array( 'header' => 'centered', 'footer' => 'band' ) ) );
	$r   = TWD_SK_Style::apply( $kit, array( 'dry_run' => true ) );
	twd_sk_true( ! is_wp_error( $r ), 'dry run' );
	twd_sk_true( ! $r['applied'] );
	twd_sk_eq( 'grove', $r['pack'] );
	twd_sk_has( 'Style pack: sage to grove', implode( "\n", $r['changes'] ) );
	twd_sk_has( 'color-primary: ', implode( "\n", $r['changes'] ) );
	twd_sk_has( 'header variant: ', implode( "\n", $r['changes'] ) );
	twd_sk_has( ' to centered', implode( "\n", $r['changes'] ) );
	twd_sk_has( ' to band', implode( "\n", $r['changes'] ) );
	twd_sk_eq( array(), $r['blocking'], 'grove passes the contrast check' );
	twd_sk_true( isset( $r['contrast']['pairs'] ) || isset( $r['contrast']['blocking'] ), 'contrast result carried' );
	twd_sk_eq( 'sage', TWD_SK_Packs::active_slug(), 'nothing saved' );
	twd_sk_eq( array(), TWD_SK_Style::snapshots(), 'no snapshot on a dry run' );
	twd_sk_eq( array(), get_option( 'twd_sk_chrome', array() ), 'settings untouched' );
} );

twd_sk_test( 'style: apply takes a snapshot first, switches the pack, keeps only tokens that differ from the new pack, saves the layouts, and the page words and pictures are byte for byte the same; revert puts the old look back', function () {
	$before = twd_sk_style_site();
	TWD_SK_Packs::set_overrides( array( 'color-primary' => '#1F5F5B' ) );
	$kit = TWD_SK_Style::read_kit( array(
		'name'   => 'Grove evening',
		'pack'   => 'grove',
		'tokens' => array( 'radius-card' => '4px', 'color-bg' => twd_sk_pack( 'grove' )['tokens']['color-bg'] ),
		'chrome' => array( 'header' => 'centered', 'footer' => 'band' ),
	) );
	$r = TWD_SK_Style::apply( $kit );
	twd_sk_true( ! is_wp_error( $r ), 'applied: ' . ( is_wp_error( $r ) ? $r->get_error_message() : '' ) );
	twd_sk_true( $r['applied'] );
	twd_sk_eq( 'grove', TWD_SK_Packs::active_slug() );
	twd_sk_eq( array( 'radius-card' => '4px' ), TWD_SK_Packs::overrides(), 'the old override is cleared on a pack switch and a value equal to the pack is not stored' );
	$c = TWD_SK_Chrome::settings();
	twd_sk_eq( 'centered', $c['header_variant'] );
	twd_sk_eq( 'band', $c['footer_variant'] );
	// The snapshot holds the look from before.
	$snaps = TWD_SK_Style::snapshots();
	twd_sk_eq( 1, count( $snaps ) );
	twd_sk_eq( 'before Grove evening', $snaps[0]['name'] );
	twd_sk_eq( 'sage', $snaps[0]['pack'] );
	twd_sk_eq( array( 'color-primary' => '#1F5F5B' ), $snaps[0]['tokens'] );
	twd_sk_eq( $snaps[0], $r['snapshot'] );
	// The page is untouched: same HTML, same version count.
	twd_sk_eq( $before, TWD_SK_Store::get_current( 41 ) );
	twd_sk_eq( 1, count( TWD_SK_Store::list_versions( 41 ) ) );
	twd_sk_has( 'valley.jpg', TWD_SK_Store::get_current( 41 ) );
	// Applying the same kit again changes nothing and takes no second snapshot.
	$r2 = TWD_SK_Style::apply( $kit );
	twd_sk_true( ! $r2['applied'] );
	twd_sk_has( 'Nothing to change', $r2['note'] );
	twd_sk_eq( 1, count( TWD_SK_Style::snapshots() ) );
	// Revert.
	$snap = TWD_SK_Style::revert();
	twd_sk_true( ! is_wp_error( $snap ) );
	twd_sk_eq( 'sage', TWD_SK_Packs::active_slug() );
	twd_sk_eq( array( 'color-primary' => '#1F5F5B' ), TWD_SK_Packs::overrides() );
	$c = TWD_SK_Chrome::settings();
	twd_sk_true( 'centered' !== $c['header_variant'], 'header layout back: ' . $c['header_variant'] );
	twd_sk_eq( array(), TWD_SK_Style::snapshots(), 'the used snapshot is forgotten' );
	twd_sk_eq( $before, TWD_SK_Store::get_current( 41 ), 'page still untouched after revert' );
	$e = TWD_SK_Style::revert();
	twd_sk_eq( 'twd_sk_no_snapshot', $e->get_error_code() );
} );

twd_sk_test( 'style: tokens on the same pack go on top of the current overrides; at most five snapshots are kept, newest first', function () {
	twd_sk_style_site();
	TWD_SK_Packs::set_overrides( array( 'color-primary' => '#1F5F5B' ) );
	$kit = TWD_SK_Style::read_kit( array( 'tokens' => array( 'radius-card' => '4px' ) ) );
	$r   = TWD_SK_Style::apply( $kit, array( 'snapshot_name' => 'first' ) );
	twd_sk_true( $r['applied'] );
	twd_sk_eq( array( 'color-primary' => '#1F5F5B', 'radius-card' => '4px' ), TWD_SK_Packs::overrides() );
	twd_sk_eq( 'sage', TWD_SK_Packs::active_slug() );
	for ( $i = 2; $i <= 7; $i++ ) {
		TWD_SK_Style::snapshot( 'snap ' . $i );
	}
	$snaps = TWD_SK_Style::snapshots();
	twd_sk_eq( TWD_SK_Style::KEEP, count( $snaps ) );
	twd_sk_eq( 'snap 7', $snaps[0]['name'] );
	twd_sk_eq( 'snap 3', $snaps[4]['name'] );
} );

twd_sk_test( 'style: a kit whose look would be hard to read is refused with the pairs named, and nothing is changed', function () {
	twd_sk_style_site();
	$kit = TWD_SK_Style::read_kit( array( 'tokens' => array( 'color-primary' => '#FFFFFF' ) ) );
	$r   = TWD_SK_Style::apply( $kit );
	twd_sk_true( is_wp_error( $r ), 'refused' );
	twd_sk_eq( 'twd_sk_contrast', $r->get_error_code() );
	twd_sk_has( 'nothing was changed', $r->get_error_message() );
	twd_sk_has( 'needs 4.5:1', $r->get_error_message() );
	twd_sk_eq( array(), TWD_SK_Packs::overrides() );
	twd_sk_eq( array(), TWD_SK_Style::snapshots() );
	// The dry run says the same without an error.
	$d = TWD_SK_Style::apply( $kit, array( 'dry_run' => true ) );
	twd_sk_true( count( $d['blocking'] ) > 0 );
} );

twd_sk_test( 'style: swap changes only the layout class of matching sections, keeps every word and picture, and reports a section the new layout cannot hold', function () {
	$html = twd_sk_style_site();
	// Standard text to prose: the body div exists in both layouts.
	$r = TWD_SK_Style::swap( $html, 'text', '', 'twd-sk-text--prose' );
	twd_sk_true( ! is_wp_error( $r ) );
	twd_sk_eq( 1, $r['changed'] );
	twd_sk_eq( array(), $r['cannot'] );
	twd_sk_has( '<section class="twd-sk-text twd-sk-text--prose">', $r['html'] );
	twd_sk_has( '<p>We walk and we talk.</p>', $r['html'] );
	twd_sk_has( 'valley.jpg', $r['html'] );
	twd_sk_eq( strip_tags( $html ), strip_tags( $r['html'] ), 'the words are the same' );
	twd_sk_eq( TWD_SK_Sanitizer::clean( $r['html'] ), $r['html'], 'still clean' );
	// And back again.
	$b = TWD_SK_Style::swap( $r['html'], 'text', 'twd-sk-text--prose', '' );
	twd_sk_eq( $html, $b['html'] );
	// A hero with a picture cannot become the compact layout (no place for the picture): it keeps its layout and says so.
	$r = TWD_SK_Style::swap( $html, 'hero', 'twd-sk-hero--split', 'twd-sk-hero--compact' );
	twd_sk_eq( 0, $r['changed'] );
	twd_sk_eq( $html, $r['html'] );
	twd_sk_eq( 1, count( $r['cannot'] ) );
	twd_sk_has( 'Section 1 keeps its layout', $r['cannot'][0] );
	twd_sk_has( 'twd-sk-hero__media', $r['cannot'][0] );
	// Sections with another layout are left alone.
	$r = TWD_SK_Style::swap( $html, 'text', 'twd-sk-text--aside', 'twd-sk-text--prose' );
	twd_sk_eq( 0, $r['changed'] );
	twd_sk_eq( $html, $r['html'] );
	// Unknown component or layout.
	twd_sk_true( is_wp_error( TWD_SK_Style::swap( $html, 'carousel', '', '' ) ) );
	twd_sk_true( is_wp_error( TWD_SK_Style::swap( $html, 'text', '', 'twd-sk-text--huge' ) ) );
} );

twd_sk_test( 'style: ?twd_sk_style=PACK previews a pack for a signed-in administrator only, never cached, and is ignored for visitors, in wp-admin, for an unknown pack and in safe mode', function () {
	twd_sk_style_site();
	$_GET['twd_sk_style'] = 'grove';
	TWD_SK_Style::read_preview();
	twd_sk_eq( 'grove', TWD_SK_Style::preview_slug() );
	twd_sk_eq( 'grove', TWD_SK_Packs::active_slug(), 'the pack in use for this request' );
	twd_sk_true( in_array( 'nocache', $GLOBALS['twd_stub']['headers'], true ), 'not cached' );
	twd_sk_eq( 'sage', get_option( 'twd_sk_pack', 'sage' ), 'nothing saved' );
	// Not an administrator.
	$GLOBALS['twd_stub']['caps'] = array( 'edit_pages' );
	TWD_SK_Style::read_preview();
	twd_sk_eq( '', TWD_SK_Style::preview_slug() );
	twd_sk_eq( 'sage', TWD_SK_Packs::active_slug() );
	// A visitor.
	$GLOBALS['twd_stub']['caps'] = true;
	$GLOBALS['twd_stub']['user'] = 0;
	TWD_SK_Style::read_preview();
	twd_sk_eq( '', TWD_SK_Style::preview_slug() );
	$GLOBALS['twd_stub']['user'] = 7;
	// In wp-admin.
	$GLOBALS['twd_stub']['admin'] = true;
	TWD_SK_Style::read_preview();
	twd_sk_eq( '', TWD_SK_Style::preview_slug() );
	$GLOBALS['twd_stub']['admin'] = false;
	// An unknown pack.
	$_GET['twd_sk_style'] = 'neon';
	TWD_SK_Style::read_preview();
	twd_sk_eq( '', TWD_SK_Style::preview_slug() );
	// Safe mode.
	$_GET['twd_sk_style'] = 'grove';
	TWD_SK_Safe::set( true );
	TWD_SK_Style::read_preview();
	twd_sk_eq( '', TWD_SK_Style::preview_slug() );
	TWD_SK_Safe::set( false );
	unset( $_GET['twd_sk_style'] );
	TWD_SK_Style::reset_preview();
} );

twd_sk_test( 'style: started from the modules (so safe mode switches it off), and the style class never touches page meta', function () {
	$src = file_get_contents( ABSPATH . 'includes/class-twd-sk-modules.php' );
	twd_sk_has( 'TWD_SK_Style::init()', $src );
	$style = file_get_contents( ABSPATH . 'includes/class-twd-sk-style.php' );
	twd_sk_hasnt( 'post_meta', $style );
	twd_sk_hasnt( 'TWD_SK_Store', $style );
	twd_sk_hasnt( 'wp_remote', $style );
} );

twd_sk_test( 'site changes: every Site tab save records the settings before it, undo steps back one change at a time, restore goes forward again', function () {
	twd_stub_reset();
	TWD_SK_Profile::save( array( 'site_name' => 'First name' ) );
	twd_sk_eq( array(), TWD_SK_Style::site_history_summary(), 'nothing recorded yet' );
	// Save 1: the footer text changes.
	TWD_SK_Style::record( 'Site details saved' );
	TWD_SK_Profile::save( array( 'footer_text' => 'Footer one' ) );
	// Save 2: the style changes.
	TWD_SK_Style::record( 'Style saved' );
	TWD_SK_Packs::set_overrides( array( 'color-band' => '#123456' ) );
	// Save 3: a header setting (whichever way the contact strip is now, flip it).
	$strip = TWD_SK_Chrome::settings()['show_strip'];
	TWD_SK_Style::record( 'Header and footer saved' );
	TWD_SK_Chrome::save_settings( array( 'show_strip' => ! $strip ) );
	twd_sk_eq( ! $strip, TWD_SK_Chrome::settings()['show_strip'] );
	$list = TWD_SK_Style::site_history_summary();
	twd_sk_eq( 3, count( $list ) );
	twd_sk_eq( 'Header and footer saved', $list[0]['label'] );
	twd_sk_true( ! $list[0]['same'] );
	// Undo 1: only the header setting goes back; the style and footer stay.
	$r = TWD_SK_Style::site_undo();
	twd_sk_eq( 'Header and footer saved', $r['undid'] );
	twd_sk_eq( $strip, TWD_SK_Chrome::settings()['show_strip'] );
	twd_sk_eq( '#123456', TWD_SK_Packs::overrides()['color-band'] );
	twd_sk_eq( 'Footer one', TWD_SK_Profile::get()['footer_text'] );
	twd_sk_eq( 'undo', TWD_SK_Style::site_history_summary()[0]['kind'] );
	// Undo 2: the style goes back, the footer stays.
	$r = TWD_SK_Style::site_undo();
	twd_sk_eq( 'Style saved', $r['undid'] );
	twd_sk_eq( array(), TWD_SK_Packs::overrides() );
	twd_sk_eq( 'Footer one', TWD_SK_Profile::get()['footer_text'] );
	// Undo 3: the footer goes back.
	$r = TWD_SK_Style::site_undo();
	twd_sk_eq( 'Site details saved', $r['undid'] );
	twd_sk_true( 'Footer one' !== TWD_SK_Profile::get()['footer_text'] );
	twd_sk_is_error( 'twd_sk_nothing_to_undo', TWD_SK_Style::site_undo() );
	// Going forward again: restore the newest "before undo" entry (the state with everything).
	$list = TWD_SK_Style::site_history_summary();
	twd_sk_eq( 'undo', $list[0]['kind'] );
	$r = TWD_SK_Style::site_restore( 0, $list[0]['at'] );
	twd_sk_true( empty( $r['unchanged'] ) );
	twd_sk_eq( 'Footer one', TWD_SK_Profile::get()['footer_text'] );
	twd_sk_is_error( 'twd_sk_version_not_found', TWD_SK_Style::site_restore( 0, 'wrong time' ) );
	twd_sk_true( count( TWD_SK_Style::site_history_summary() ) <= TWD_SK_Style::KEEP_HISTORY );
} );

twd_sk_test( 'site changes: the REST routes record before each save, and undo and restore answer with the list', function () {
	twd_stub_reset();
	twd_sk_rest_setup();
	$strip = TWD_SK_Chrome::settings()['show_strip'];
	TWD_SK_REST::post_site_profile( twd_sk_rest_req( array( 'profile' => array( 'footer_text' => 'Hello footer' ) ) ) );
	TWD_SK_REST::post_site_chrome( twd_sk_rest_req( array( 'settings' => array( 'show_strip' => ! $strip ) ) ) );
	$out = TWD_SK_REST::get_site_history( twd_sk_rest_req() );
	twd_sk_eq( 2, count( $out['history'] ) );
	twd_sk_eq( 'Header and footer saved', $out['history'][0]['label'] );
	$out = TWD_SK_REST::post_site_history_undo( twd_sk_rest_req() );
	twd_sk_eq( 'Header and footer saved', $out['undid'] );
	twd_sk_eq( 'Hello footer', TWD_SK_Profile::get()['footer_text'] );
	twd_sk_eq( $strip, TWD_SK_Chrome::settings()['show_strip'] );
	$e = TWD_SK_REST::post_site_history_restore( twd_sk_rest_req( array( 'index' => 0, 'at' => 'nope' ) ) );
	twd_sk_is_error( 'twd_sk_version_not_found', $e );
	$out = TWD_SK_REST::post_site_history_restore( twd_sk_rest_req( array( 'index' => 0, 'at' => $out['history'][0]['at'] ) ) );
	twd_sk_eq( ! $strip, TWD_SK_Chrome::settings()['show_strip'] );
} );
