<?php
// Plain-English cleaning reports.

twd_sk_test( 'report: nothing removed gives one calm sentence', function () {
	$rep = TWD_SK_Sanitizer::clean_with_report( '<p>Fine.</p>' )['report'];
	twd_sk_eq( array( 'Nothing was removed or changed.' ), TWD_SK_Report::describe( $rep ) );
} );

twd_sk_test( 'report: removals are described in words with the names that were removed', function () {
	$rep   = TWD_SK_Sanitizer::clean_with_report( '<p class="made-up" onclick="x()">Hi [gallery] there</p><script>bad()</script>' )['report'];
	$lines = implode( ' | ', TWD_SK_Report::describe( $rep ) );
	twd_sk_has( 'style name', $lines );
	twd_sk_has( 'made-up', $lines );
	twd_sk_has( 'inline styles or scripts', $lines );
	twd_sk_has( 'shortcode', $lines );
	twd_sk_has( 'script', $lines );
	twd_sk_hasnt( 'Nothing was removed', $lines );
} );

twd_sk_test( 'report: a second h1 is explained as a heading change, not a cryptic tag name', function () {
	$html  = '<section class="twd-sk-hero twd-sk-hero--split"><h1 class="twd-sk-hero__title">One</h1></section><h1>Two</h1>';
	$lines = implode( ' | ', TWD_SK_Report::describe( TWD_SK_Sanitizer::clean_with_report( $html )['report'] ) );
	twd_sk_has( 'extra main heading', $lines );
	twd_sk_has( 'one h1, in the first hero', $lines );
	twd_sk_hasnt( 'demoted', $lines );
} );

twd_sk_test( 'report: long lists are cut to five names', function () {
	$html  = '<p class="a1 a2 a3 a4 a5 a6 a7">x</p>';
	$lines = implode( ' ', TWD_SK_Report::describe( TWD_SK_Sanitizer::clean_with_report( $html )['report'] ) );
	twd_sk_has( 'and more', $lines );
} );

twd_sk_test( 'report: leftovers get a plain meaning and a total', function () {
	$left = array( 'example.com' => 2, 'PHONE_NUMBER' => 1, '[PLACEHOLDER' => 3 );
	$out  = TWD_SK_Report::describe_leftovers( $left );
	twd_sk_eq( 3, count( $out ) );
	twd_sk_eq( 'example.com', $out[0]['marker'] );
	twd_sk_eq( 2, $out[0]['count'] );
	twd_sk_has( 'sample email or web address', $out[0]['meaning'] );
	twd_sk_eq( 6, TWD_SK_Report::leftover_total( $left ) );
	twd_sk_eq( 0, TWD_SK_Report::leftover_total( array() ) );
	twd_sk_eq( array(), TWD_SK_Report::describe_leftovers( 'nope' ) );
	foreach ( TWD_SK_Sanitizer::leftover_markers() as $marker ) {
		$one = TWD_SK_Report::describe_leftovers( array( $marker => 1 ) );
		twd_sk_true( 'example text' !== $one[0]['meaning'], 'a plain meaning exists for ' . $marker );
	}
} );

twd_sk_test( 'report: the wording holds no em or en dashes', function () {
	$src = file_get_contents( ABSPATH . 'includes/class-twd-sk-report.php' );
	twd_sk_true( false === strpos( $src, "\xE2\x80\x94" ) && false === strpos( $src, "\xE2\x80\x93" ) );
} );

twd_sk_test( 'report: leftovers carry their level, and the totals are split by level', function () {
	$left = array( 'example.com' => 2, 'Contact me about a first session' => 1, 'Find out about my services' => 3, '[PLACEHOLDER' => 1 );
	$out  = TWD_SK_Report::describe_leftovers( $left );
	$by   = array();
	foreach ( $out as $item ) {
		$by[ $item['marker'] ] = $item['level'];
	}
	twd_sk_eq( array( 'example.com' => 'must', 'Contact me about a first session' => 'check', 'Find out about my services' => 'check', '[PLACEHOLDER' => 'must' ), $by );
	twd_sk_eq( array( 'must' => 3, 'check' => 4 ), TWD_SK_Report::leftover_levels( $left ) );
	twd_sk_eq( array( 'must' => 0, 'check' => 0 ), TWD_SK_Report::leftover_levels( array() ) );
	twd_sk_eq( array( 'must' => 0, 'check' => 0 ), TWD_SK_Report::leftover_levels( 'nope' ) );
} );
