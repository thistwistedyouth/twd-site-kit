<?php
// Search wording by the AI, and the featured picture (0.18.0): TWD_SK_SeoWriter drafts a title and description from
// a page's words, the saved facts and keywords and never saves; TWD_SK_Seo keeps a page's featured picture and shares
// it when the page has no picture for sharing.

if ( ! function_exists( 'get_post_thumbnail_id' ) ) {
	function get_post_thumbnail_id( $id ) {
		return isset( $GLOBALS['twd_stub']['thumb'][ (int) $id ] ) ? (int) $GLOBALS['twd_stub']['thumb'][ (int) $id ] : 0;
	}
	function set_post_thumbnail( $id, $att ) {
		$GLOBALS['twd_stub']['thumb'][ (int) $id ] = (int) $att;
		return true;
	}
	function delete_post_thumbnail( $id ) {
		unset( $GLOBALS['twd_stub']['thumb'][ (int) $id ] );
		return true;
	}
}

function twd_sk_sw_page( $id = 330 ) {
	twd_stub_reset();
	twd_stub_add_post( $id, 'page', '[twd_page]' );
	$GLOBALS['twd_stub']['posts'][ $id ]->post_title = 'Anxiety';
	$GLOBALS['twd_stub']['attachments'][55] = array( 'url' => 'https://example.test/u/pic.jpg', 'w' => 1200, 'h' => 630, 'is_image' => true );
	$GLOBALS['twd_stub']['attachments'][56] = array( 'url' => 'https://example.test/u/feat.jpg', 'w' => 1200, 'h' => 630, 'is_image' => true );
	TWD_SK_Store::save( $id, '<section class="twd-sk-hero twd-sk-hero--compact twd-sk-tone-base"><div class="twd-sk-inner"><h1 class="twd-sk-hero__title">Anxiety therapy in the city</h1></div></section><section class="twd-sk-text twd-sk-tone-base"><div class="twd-sk-inner"><h2 class="twd-sk-title">What is anxiety?</h2><p>Worry that will not switch off, a racing heart and sleepless nights.</p></div></section>' );
	TWD_SK_Facts::save( "WHO I AM\nA clinical psychologist.\n\nTHINGS I NEVER SAY OR PROMISE\nNever promise a cure." );
	twd_sk_seo_mode( 'none' );
}

twd_sk_test( 'seo writer: a title and description are drafted from the page, the facts and the keywords, and nothing is saved', function () {
	twd_sk_sw_page();
	twd_sk_ai_provider( json_encode( array( 'title' => 'Anxiety Therapy in the City | The Practice', 'description' => 'Help for worry that will not switch off, a racing heart and sleepless nights. Find out how we work together at a steady pace.' ) ) );
	$r = TWD_SK_SeoWriter::generate( 330, ' anxiety therapy,  panic ' );
	twd_sk_true( is_array( $r ), is_wp_error( $r ) ? $r->get_error_message() : '' );
	twd_sk_eq( 'Anxiety Therapy in the City | The Practice', $r['title'] );
	twd_sk_has( 'racing heart', $r['description'] );
	twd_sk_eq( array(), $r['warnings'] );
	$call = $GLOBALS['twd_stub']['ai_calls'][0];
	twd_sk_has( 'anxiety therapy, panic', $call['message'], 'keywords are cleaned and sent' );
	twd_sk_has( 'Worry that will not switch off', $call['message'], 'the page words are sent' );
	twd_sk_has( 'Never promise a cure', $call['message'], 'the saved facts are sent' );
	twd_sk_has( 'Page title: Anxiety', $call['message'] );
	twd_sk_has( 'Never invent a fact', $call['system'] );
	twd_sk_has( 'never more than 65', $call['system'] );
	twd_sk_eq( 700, $call['tokens'] );
	twd_sk_eq( '', TWD_SK_Seo::details( 330 )['title'], 'nothing was saved' );
	twd_sk_eq( '', TWD_SK_Seo::details( 330 )['description'] );
} );

twd_sk_test( 'seo writer: too long is cut at a word and said so, long dashes, tags and markers go, and a bad reply is refused', function () {
	twd_sk_sw_page();
	twd_sk_ai_provider( json_encode( array(
		'title'       => '<b>Anxiety therapy ' . "\xE2\x80\x94" . ' a calm and steady way to feel like yourself again in the city and beyond</b> [PLACEHOLDER: x]',
		'description' => str_repeat( 'A plain sentence about the page. ', 9 ),
	) ) );
	$r = TWD_SK_SeoWriter::generate( 330 );
	twd_sk_true( is_array( $r ), is_wp_error( $r ) ? $r->get_error_message() : '' );
	twd_sk_true( strlen( $r['title'] ) <= TWD_SK_SeoWriter::MAX_TITLE, 'title cut to ' . strlen( $r['title'] ) );
	twd_sk_true( strlen( $r['description'] ) <= TWD_SK_SeoWriter::MAX_DESC, 'description cut to ' . strlen( $r['description'] ) );
	twd_sk_hasnt( '<', $r['title'] );
	twd_sk_hasnt( 'PLACEHOLDER', $r['title'] );
	twd_sk_true( 1 !== preg_match( '/[\x{2013}\x{2014}]/u', $r['title'] ), 'no long dash' );
	twd_sk_eq( 2, count( $r['warnings'] ), 'both were cut and the person is told' );
	twd_sk_has( 'cut at a word', $r['warnings'][0] );
	twd_sk_ai_provider( 'Sure, here are some ideas for you.' );
	twd_sk_is_error( 'twd_sk_ai_bad_result', TWD_SK_SeoWriter::generate( 330 ) );
	twd_sk_ai_provider( json_encode( array( 'title' => '', 'description' => 'x' ) ) );
	twd_sk_is_error( 'twd_sk_ai_bad_result', TWD_SK_SeoWriter::generate( 330 ), 'an empty title' );
	twd_sk_ai_provider( json_encode( array( 'title' => 'Only a title' ) ) );
	twd_sk_is_error( 'twd_sk_ai_bad_result', TWD_SK_SeoWriter::generate( 330 ), 'a missing description' );
	twd_sk_ai_provider( new WP_Error( 'twd_ap_ai_failed', 'The service is busy.' ) );
	twd_sk_is_error( 'twd_ap_ai_failed', TWD_SK_SeoWriter::generate( 330 ), 'an error from the service is passed on' );
	twd_sk_ai_provider( null );
	twd_sk_is_error( 'twd_sk_ai_unavailable', TWD_SK_SeoWriter::generate( 330 ), 'no answer' );
} );

twd_sk_test( 'seo writer: no AI, a page that is not a kit page or has no words are refused plainly', function () {
	twd_sk_sw_page();
	twd_sk_ai_provider( '{}', false );
	twd_sk_is_error( 'twd_sk_ai_unavailable', TWD_SK_SeoWriter::generate( 330 ), 'the AI is not set up' );
	twd_sk_ai_provider( json_encode( array( 'title' => 'T', 'description' => 'D' ) ) );
	twd_sk_is_error( 'twd_sk_not_a_page', TWD_SK_SeoWriter::generate( 999999 ), 'not a page' );
	twd_stub_add_post( 331, 'page', '[twd_page]' );
	twd_sk_is_error( 'twd_sk_empty_page', TWD_SK_SeoWriter::generate( 331 ), 'a page with no words yet' );
} );

twd_sk_test( 'seo writer: the pages with no search title or description are listed, with what they have now', function () {
	twd_sk_sw_page();
	TWD_SK_Seo::write( 330, array( 'title' => 'Anxiety | The Practice' ) );
	$list = TWD_SK_SeoWriter::missing();
	twd_sk_eq( 1, count( $list ) );
	twd_sk_eq( 330, $list[0]['id'] );
	twd_sk_true( $list[0]['has_title'] && ! $list[0]['has_description'], 'it says which half is missing' );
	twd_sk_eq( 'Anxiety | The Practice', $list[0]['title_now'] );
	TWD_SK_Seo::write( 330, array( 'description' => 'A description.' ) );
	twd_sk_eq( array(), TWD_SK_SeoWriter::missing(), 'a page with both is not listed' );
} );

twd_sk_test( 'seo: a featured picture is kept, validated, read back, shared when there is no sharing picture, and the sharing picture wins', function () {
	twd_sk_sw_page();
	$r = TWD_SK_Seo::write( 330, array( 'featured_id' => 56 ) );
	twd_sk_true( ! is_wp_error( $r ), is_wp_error( $r ) ? $r->get_error_message() : '' );
	twd_sk_eq( 56, $r['featured_id'] );
	twd_sk_has( 'feat.jpg', $r['featured_url'] );
	twd_sk_eq( 56, TWD_SK_Seo::featured_id( 330 ) );
	twd_sk_is_error( 'twd_sk_bad_seo', TWD_SK_Seo::write( 330, array( 'featured_id' => 'abc' ) ), 'not a number' );
	twd_sk_is_error( 'twd_sk_bad_seo', TWD_SK_Seo::write( 330, array( 'featured_id' => 9999 ) ), 'not a picture' );
	// Shared when the page has no sharing picture of its own.
	$head = twd_sk_seo_head( 330 );
	twd_sk_has( 'og:image" content="https://example.test/u/feat.jpg"', $head, 'the featured picture is the share picture' );
	TWD_SK_Seo::write( 330, array( 'image_id' => 55 ) );
	$head = twd_sk_seo_head( 330 );
	twd_sk_has( 'og:image" content="https://example.test/u/pic.jpg"', $head, 'the sharing picture wins' );
	twd_sk_hasnt( 'feat.jpg', $head );
	$r = TWD_SK_Seo::write( 330, array( 'featured_id' => 0 ) );
	twd_sk_eq( 0, $r['featured_id'], 'removed' );
	twd_sk_eq( 55, $r['image_id'], 'the sharing picture is untouched' );
	// Another SEO plugin looks after title and picture, but the featured picture is WordPress's own and still works.
	twd_sk_seo_mode( 'other' );
	twd_sk_true( ! is_wp_error( TWD_SK_Seo::write( 330, array( 'featured_id' => 56 ) ) ), 'featured works whoever prints the tags' );
	twd_sk_is_error( 'twd_sk_seo_other_plugin', TWD_SK_Seo::write( 330, array( 'image_id' => 55 ) ) );
	twd_sk_seo_mode( 'none' );
} );

twd_sk_test( 'seo writer routes: administrators only, one page at a time with the seo limit, and the draft never saves', function () {
	twd_sk_sw_page();
	twd_sk_rest_setup( array( 'edit_pages', 'edit_post:330' ) );
	twd_sk_is_error( 'twd_sk_forbidden', TWD_SK_REST::can_generate_seo( twd_sk_rest_req( array( 'id' => 330 ) ) ), 'an editor is refused' );
	twd_sk_rest_setup( array( 'edit_pages', 'edit_post:330', 'manage_options' ) );
	twd_sk_eq( true, TWD_SK_REST::can_generate_seo( twd_sk_rest_req( array( 'id' => 330 ) ) ), 'an administrator may' );
	twd_sk_ai_provider( json_encode( array( 'title' => 'Anxiety | The Practice', 'description' => 'A plain description of the anxiety page for a person who is searching.' ) ) );
	$out = TWD_SK_REST::post_seo_generate( twd_sk_rest_req( array( 'id' => 330, 'keywords' => 'anxiety' ) ) );
	twd_sk_eq( 'Anxiety | The Practice', $out['title'] );
	twd_sk_eq( '', TWD_SK_Seo::details( 330 )['title'], 'not saved' );
	twd_sk_eq( 400, twd_sk_status( TWD_SK_REST::post_seo_generate( twd_sk_rest_req( array( 'id' => 999999 ) ) ) ) === 404 ? 400 : 0, 'an unknown page is a 404' );
	$missing = TWD_SK_REST::get_site_seo_missing( twd_sk_rest_req() );
	twd_sk_true( isset( $missing['pages'], $missing['ai'] ), 'the list answers with pages and whether the AI is on' );
	$rest = file_get_contents( ABSPATH . 'includes/class-twd-sk-rest.php' );
	twd_sk_has( "'seo'     => array( 40, 600 )", $rest, 'its own limit' );
} );

twd_sk_test( 'search wording js (0.18.0): the Site tab section, the Search tab button and the Simple editor pictures', function () {
	$site = file_get_contents( ABSPATH . 'assets/twd-site-kit-editor-site.js' );
	twd_sk_has( "api('GET', '/site/seo-missing')", $site );
	twd_sk_has( "'/seo/generate'", $site );
	twd_sk_has( 'Nothing is saved yet.', $site, 'the draft is not saved' );
	twd_sk_has( 'Save the ticked pages', $site );
	twd_sk_has( 'Replace a title or description a page already has', $site, 'existing wording is left alone by default' );
	twd_sk_has( "if (replace || !r.p.has_title)", $site );
	twd_sk_has( 'function buildSeoWriterSection(', $site );
	$seo = file_get_contents( ABSPATH . 'assets/twd-site-kit-editor-seo.js' );
	twd_sk_has( "path + '/generate'", $seo );
	twd_sk_has( 'ED.cfg.canManage && ED.cfg.aiAvailable', $seo, 'only administrators on a site with an AI' );
	twd_sk_has( 'Write the title and description with AI', $seo );
	twd_sk_has( 'twd-sk-seo-featured', $seo );
	twd_sk_has( 'featured_id', $seo );
	$inline = file_get_contents( ABSPATH . 'assets/twd-site-kit-inline.js' );
	twd_sk_has( "picRow('image_id'", $inline );
	twd_sk_has( "picRow('featured_id'", $inline );
	twd_sk_has( "'/pages/' + cfg.pageId + '/seo'", $inline, 'saved through the search route' );
	twd_sk_has( 'not part of Undo', $inline, 'the person is told' );
	foreach ( array( $site, $seo, $inline ) as $js ) {
		twd_sk_hasnt( 'innerHTML', $js );
		twd_sk_hasnt( 'srcdoc', $js );
	}
} );

twd_sk_test( 'seo (0.18.3): the picture is shared at full size with its width and height, falls back to the large size for a file over 4 MB, and the twitter image matches', function () {
	twd_sk_sw_page();
	TWD_SK_Seo::write( 330, array( 'image_id' => 55 ) );
	$head = twd_sk_seo_head( 330 );
	twd_sk_has( 'og:image" content="https://example.test/u/pic.jpg"', $head );
	twd_sk_has( 'og:image:width" content="1200"', $head );
	twd_sk_has( 'og:image:height" content="630"', $head );
	twd_sk_has( 'twitter:image" content="https://example.test/u/pic.jpg"', $head );
	twd_sk_has( 'summary_large_image', $head );
	twd_sk_eq( 'full', $GLOBALS['twd_stub']['image_size_asked'][55], 'the full size was asked for' );
	$share = TWD_SK_Seo::share_image( 55 );
	twd_sk_eq( array( 'url' => 'https://example.test/u/pic.jpg', 'w' => 1200, 'h' => 630 ), $share );
	twd_sk_eq( array(), TWD_SK_Seo::share_image( 0 ), 'no picture, no share image' );
	twd_sk_eq( array(), TWD_SK_Seo::share_image( 9999 ), 'a picture that does not exist' );
	// A file over about 4 MB is shared at the large size instead.
	$tmp = sys_get_temp_dir() . '/twdsk-bigshare.bin';
	file_put_contents( $tmp, str_repeat( 'a', 4 * 1000 * 1000 + 10 ) );
	$GLOBALS['twd_stub']['attachments'][55]['file'] = $tmp;
	TWD_SK_Seo::share_image( 55 );
	twd_sk_eq( 'large', $GLOBALS['twd_stub']['image_size_asked'][55], 'a big file is shared at the large size' );
	unlink( $tmp );
	// A small file keeps the full size.
	file_put_contents( $tmp, 'small' );
	$GLOBALS['twd_stub']['attachments'][55]['file'] = $tmp;
	TWD_SK_Seo::share_image( 55 );
	twd_sk_eq( 'full', $GLOBALS['twd_stub']['image_size_asked'][55] );
	unlink( $tmp );
} );
