<?php
// Structured data for the front page, from the site profile.

function twd_sk_schema_setup( $profile ) {
	twd_stub_reset();
	TWD_SK_Schema::reset();
	$GLOBALS['twd_stub']['front'] = true;
	$GLOBALS['twd_stub']['attachments'][70] = array( 'url' => 'https://example.test/u/logo.png', 'w' => 200, 'h' => 80, 'is_image' => true );
	twd_sk_seo_mode( 'none' );
	if ( $profile ) {
		$r = TWD_SK_Profile::save( $profile );
		twd_sk_true( ! is_wp_error( $r ), 'profile saved: ' . ( is_wp_error( $r ) ? $r->get_error_message() : '' ) );
	}
}
function twd_sk_schema_head() {
	ob_start();
	TWD_SK_Schema::print_head();
	return ob_get_clean();
}
function twd_sk_schema_real() {
	return array(
		'site_name' => 'Calm Practice', 'logo_id' => 70, 'phone' => '01234 567890', 'email' => 'hello@example.com',
		'address' => array( '1 Example Street', 'Testville' ), 'area_served' => 'Testville and online',
		'registration' => array( 'Registered member, number 000000' ),
		'person_name' => 'Alex Example', 'person_job' => 'Counsellor', 'same_as' => array( 'https://example.test/alex' ),
	);
}

twd_sk_test( 'schema: real profile values become a practice and a person, registration lines are never printed', function () {
	twd_sk_schema_setup( twd_sk_schema_real() );
	$nodes = TWD_SK_Schema::nodes();
	twd_sk_eq( 2, count( $nodes ) );
	twd_sk_eq( 'ProfessionalService', $nodes[0]['@type'] );
	twd_sk_eq( 'Calm Practice', $nodes[0]['name'] );
	twd_sk_eq( '01234 567890', $nodes[0]['telephone'] );
	twd_sk_eq( '1 Example Street, Testville', $nodes[0]['address']['streetAddress'] );
	twd_sk_eq( 'https://example.test/u/logo.png', $nodes[0]['logo'] );
	twd_sk_eq( 'Person', $nodes[1]['@type'] );
	twd_sk_eq( 'Counsellor', $nodes[1]['jobTitle'] );
	twd_sk_eq( $nodes[0]['@id'], $nodes[1]['worksFor']['@id'] );
	twd_sk_hasnt( '000000', json_encode( $nodes ), 'no registration line' );
} );

twd_sk_test( 'schema: placeholders, empty values and bad contact details are left out, nothing is invented', function () {
	twd_sk_schema_setup( TWD_SK_Profile::starter() );
	twd_sk_eq( array(), TWD_SK_Schema::nodes(), 'a starter profile prints nothing' );
	twd_sk_eq( '', twd_sk_schema_head() );
	twd_sk_schema_setup( array( 'site_name' => 'Calm Practice', 'phone' => '[PLACEHOLDER: phone]', 'same_as' => array( 'https://example.test/x' ) ) );
	$n = TWD_SK_Schema::nodes();
	twd_sk_eq( 1, count( $n ) );
	$hand = TWD_SK_Schema::nodes( array_merge( TWD_SK_Profile::defaults(), array( 'site_name' => 'Calm Practice', 'email' => 'not-an-email', 'phone' => '12', 'same_as' => array( 'javascript:alert(1)' ) ) ) );
	twd_sk_true( ! isset( $hand[0]['email'] ) && ! isset( $hand[0]['telephone'] ) && ! isset( $hand[0]['sameAs'] ), 'a hand-edited bad email, short phone and non-web link are dropped' );
	foreach ( array( 'telephone', 'email', 'address', 'areaServed', 'logo' ) as $key ) {
		twd_sk_true( ! isset( $n[0][ $key ] ), $key . ' left out' );
	}
	twd_sk_hasnt( 'PLACEHOLDER', json_encode( $n ) );
	twd_sk_schema_setup( array() );
	twd_sk_eq( array(), TWD_SK_Schema::nodes(), 'an empty profile prints nothing' );
} );

twd_sk_test( 'schema: with no SEO plugin one block is printed on the front page, once, as safe JSON', function () {
	$p = twd_sk_schema_real();
	$p['site_name'] = 'Calm </script><b>Practice & "Co"';
	twd_sk_schema_setup( $p );
	$h = twd_sk_schema_head();
	twd_sk_has( '<script type="application/ld+json">', $h );
	twd_sk_eq( 1, substr_count( $h, '</script>' ), 'the name cannot close the block' );
	twd_sk_hasnt( '<b>', $h );
	$data = json_decode( str_replace( array( '<script type="application/ld+json">', '</script>' ), '', $h ), true );
	twd_sk_true( is_array( $data ) && 'https://schema.org' === $data['@context'], 'valid JSON' );
	twd_sk_eq( 2, count( $data['@graph'] ) );
	twd_sk_eq( '', twd_sk_schema_head(), 'a second call prints nothing' );
} );

twd_sk_test( 'schema: nothing is printed away from the front page, in safe mode, or with another SEO plugin', function () {
	twd_sk_schema_setup( twd_sk_schema_real() );
	$GLOBALS['twd_stub']['front'] = false;
	twd_sk_eq( '', twd_sk_schema_head() );
	$GLOBALS['twd_stub']['front'] = true;
	TWD_SK_Safe::set( true );
	twd_sk_eq( '', twd_sk_schema_head() );
	TWD_SK_Safe::set( false );
	twd_sk_seo_mode( 'other', 'Rank Math' );
	twd_sk_eq( '', twd_sk_schema_head() );
	twd_sk_seo_mode( 'yoast', 'Yoast SEO' );
	twd_sk_eq( '', twd_sk_schema_head(), 'Yoast prints its own graph, so the kit prints nothing' );
} );

twd_sk_test( 'schema: Yoast graph gets the two items once, only on the front page', function () {
	twd_sk_schema_setup( twd_sk_schema_real() );
	$graph = array( array( '@type' => 'WebSite', '@id' => 'https://example.test/#website' ) );
	$out   = TWD_SK_Schema::filter_yoast_graph( $graph );
	twd_sk_eq( 3, count( $out ) );
	twd_sk_eq( $out, TWD_SK_Schema::filter_yoast_graph( $out ), 'a second pass adds nothing' );
	$GLOBALS['twd_stub']['front'] = false;
	twd_sk_eq( $graph, TWD_SK_Schema::filter_yoast_graph( $graph ) );
	$GLOBALS['twd_stub']['front'] = true;
	twd_sk_eq( 'x', TWD_SK_Schema::filter_yoast_graph( 'x' ), 'a non-array is passed through' );
} );

twd_sk_test( 'chrome: only the footer logo is lazy loaded', function () {
	twd_sk_schema_setup( twd_sk_schema_real() );
	$footer = TWD_SK_Chrome::render_footer();
	twd_sk_has( 'loading="lazy"', $footer );
	twd_sk_hasnt( 'loading="lazy"', TWD_SK_Chrome::render_header() );
} );
