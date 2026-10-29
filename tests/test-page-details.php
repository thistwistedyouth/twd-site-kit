<?php
// Page details (session 7, 29 Oct 2026, section 21a): the page title and description changed from the Simple editor's
// Page details box go through the inline route, every version records them, and Undo takes them back when it is safe.

function twd_sk_details_setup() {
	twd_sk_rest_setup( array( 'edit_pages', 'edit_post:12' ) );
	TWD_SK_Store::save( 12, twd_sk_inline_sample() );
	return TWD_SK_Store::get_current_version_id( 12 );
}

twd_sk_test( 'page details: a version records the title and description as they were when it was saved', function () {
	twd_sk_details_setup();
	$v = TWD_SK_Store::list_versions( 12 );
	twd_sk_eq( array( 'title' => '', 'description' => '' ), $v[0]['seo'], 'nothing set yet, still recorded' );
	TWD_SK_Seo::write( 12, array( 'title' => 'Van hire in town', 'description' => 'Vans by the day.' ) );
	TWD_SK_Store::save( 12, twd_sk_inline_sample() . '<p>More</p>' );
	$v = TWD_SK_Store::list_versions( 12 );
	twd_sk_eq( 'Van hire in town', $v[0]['seo']['title'] );
	twd_sk_eq( 'Vans by the day.', $v[0]['seo']['description'] );
} );

twd_sk_test( 'page details: the inline route writes only the title and description, records a version even with the same words, and notes it', function () {
	$base = twd_sk_details_setup();
	$bad  = TWD_SK_REST::post_inline( twd_sk_rest_req( array( 'base_version' => $base, 'details' => array( 'title' => 'x', 'noindex' => true ) ) ) );
	twd_sk_eq( 400, twd_sk_status( $bad ), 'noindex stays in the Search tab' );
	twd_sk_eq( $base, TWD_SK_Store::get_current_version_id( 12 ), 'nothing saved' );
	twd_sk_eq( '', TWD_SK_Seo::details( 12 )['title'], 'nothing written' );

	$out = TWD_SK_REST::post_inline( twd_sk_rest_req( array( 'base_version' => $base, 'details' => array( 'title' => 'Van hire in town', 'description' => 'Vans by the day.' ) ) ) );
	twd_sk_true( is_array( $out ), is_wp_error( $out ) ? $out->get_error_message() : '' );
	twd_sk_eq( $base + 1, (int) $out['version'], 'a new version although the words did not change' );
	twd_sk_eq( 'Changed on the page (page details)', TWD_SK_Store::list_versions( 12 )[0]['note'] );
	twd_sk_eq( 'Van hire in town', TWD_SK_Seo::details( 12 )['title'] );
	twd_sk_eq( twd_sk_inline_sample(), TWD_SK_Store::get_current( 12 ), 'the words are as they were' );

	// Words and details together: one version, one note naming both.
	$out = TWD_SK_REST::post_inline( twd_sk_rest_req( array( 'base_version' => $base + 1, 'texts' => array( array( 'i' => 0, 'html' => 'Van hire' ) ), 'details' => array( 'description' => 'Vans by the day or the week.' ) ) ) );
	twd_sk_true( is_array( $out ), is_wp_error( $out ) ? $out->get_error_message() : '' );
	twd_sk_eq( 'Changed on the page (1 piece, page details)', TWD_SK_Store::list_versions( 12 )[0]['note'] );
	twd_sk_eq( 'Vans by the day or the week.', TWD_SK_Seo::details( 12 )['description'] );
	// The same details again with nothing else: no version is used up.
	$again = TWD_SK_REST::post_inline( twd_sk_rest_req( array( 'base_version' => $base + 2, 'details' => array( 'description' => 'Vans by the day or the week.' ) ) ) );
	twd_sk_true( is_array( $again ) && ! empty( $again['unchanged'] ), 'nothing to record' );
} );

twd_sk_test( 'page details: Undo takes the title and description back with the version, step by step, and says so', function () {
	$base = twd_sk_details_setup();
	TWD_SK_REST::post_inline( twd_sk_rest_req( array( 'base_version' => $base, 'details' => array( 'title' => 'First title', 'description' => 'First words.' ) ) ) );
	TWD_SK_REST::post_inline( twd_sk_rest_req( array( 'base_version' => $base + 1, 'details' => array( 'title' => 'Second title' ) ) ) );
	twd_sk_eq( 'Second title', TWD_SK_Seo::details( 12 )['title'] );

	$r = TWD_SK_Store::undo( 12, array( 'base_version' => $base + 2 ) );
	twd_sk_true( is_array( $r ), is_wp_error( $r ) ? $r->get_error_message() : '' );
	twd_sk_eq( $base + 1, (int) $r['target'], 'the details-only version is a step of its own, not skipped for having the same words' );
	twd_sk_eq( 'The page title and description went back with it.', $r['seo_note'] );
	twd_sk_eq( 'First title', TWD_SK_Seo::details( 12 )['title'] );
	twd_sk_eq( $base + 3, TWD_SK_Store::get_current_version_id( 12 ), 'the undo is recorded although the words are the same' );

	$r = TWD_SK_Store::undo( 12, array( 'base_version' => $base + 3 ) );
	twd_sk_eq( $base, (int) $r['target'] );
	twd_sk_eq( array( 'title' => '', 'description' => '' ), TWD_SK_Seo::details( 12 ), 'back to nothing set' );

	// The route passes the note on.
	TWD_SK_REST::post_inline( twd_sk_rest_req( array( 'base_version' => $base + 4, 'details' => array( 'title' => 'Third title' ) ) ) );
	$out = TWD_SK_REST::post_undo( twd_sk_rest_req( array( 'base_version' => $base + 5 ) ) );
	twd_sk_true( is_array( $out ), is_wp_error( $out ) ? $out->get_error_message() : '' );
	twd_sk_eq( 'The page title and description went back with it.', $out['seo_note'] );
} );

twd_sk_test( 'page details: a change made in the Search tab since (no version) is never undone by accident; versions from before say nothing', function () {
	$base = twd_sk_details_setup();
	TWD_SK_REST::post_inline( twd_sk_rest_req( array( 'base_version' => $base, 'details' => array( 'title' => 'First title' ) ) ) );
	TWD_SK_REST::post_inline( twd_sk_rest_req( array( 'base_version' => $base + 1, 'texts' => array( array( 'i' => 0, 'html' => 'Van hire' ) ), 'details' => array( 'title' => 'Second title' ) ) ) );
	// The administrator types a better title in the Search tab (no version is recorded there).
	TWD_SK_Seo::write( 12, array( 'title' => 'Hand written title' ) );
	$r = TWD_SK_Store::undo( 12, array( 'base_version' => $base + 2 ) );
	twd_sk_has( 'Weekend van hire', TWD_SK_Store::get_current( 12 ), 'the words went back' );
	twd_sk_eq( 'The page title and description were left as they are, because they were changed in the Search tab since.', $r['seo_note'] );
	twd_sk_eq( 'Hand written title', TWD_SK_Seo::details( 12 )['title'], 'kept' );

	// A version saved before any details were recorded: nothing follows and nothing is said.
	twd_sk_rest_setup( array( 'edit_pages', 'edit_post:12' ) );
	TWD_SK_Store::save( 12, '<p>One</p>' );
	TWD_SK_Store::save( 12, '<p>Two</p>' );
	$raw = get_post_meta( 12, TWD_SK_Store::META_VERSIONS, true );
	foreach ( $raw['items'] as &$item ) {
		unset( $item['seo'] );
	}
	unset( $item );
	update_post_meta( 12, TWD_SK_Store::META_VERSIONS, $raw );
	TWD_SK_Seo::write( 12, array( 'title' => 'Set later' ) );
	$r = TWD_SK_Store::undo( 12, array( 'base_version' => TWD_SK_Store::get_current_version_id( 12 ) ) );
	twd_sk_true( is_array( $r ), is_wp_error( $r ) ? $r->get_error_message() : '' );
	twd_sk_eq( '', $r['seo_note'] );
	twd_sk_eq( 'Set later', TWD_SK_Seo::details( 12 )['title'] );
} );

twd_sk_test( 'page details: with another SEO plugin in charge the route refuses with a plain message and saves nothing', function () {
	$base = twd_sk_details_setup();
	add_filter( 'twd_sk_seo_plugin', function () {
		return array( 'mode' => 'other', 'name' => 'Rank Math' );
	} );
	$out = TWD_SK_REST::post_inline( twd_sk_rest_req( array( 'base_version' => $base, 'details' => array( 'title' => 'Nope' ), 'texts' => array( array( 'i' => 0, 'html' => 'Van hire' ) ) ) ) );
	twd_sk_eq( 409, twd_sk_status( $out ) );
	twd_sk_has( 'Rank Math', $out->get_error_message() );
	twd_sk_eq( $base, TWD_SK_Store::get_current_version_id( 12 ), 'the words were not saved either' );
} );
