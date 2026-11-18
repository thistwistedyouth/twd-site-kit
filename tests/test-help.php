<?php
// TWD_SK_Help (0.22.0): the tutorial list on the Welcome screen. One fixed address, checked entries, fails closed.

function twd_sk_help_feed( $items ) {
	return json_encode( array( 'schema' => 'twd-site-help/1', 'items' => $items ) );
}

twd_sk_test( 'help: a good entry is kept; bad links, wrong hosts, other schemes and empty titles are dropped; text is plain', function () {
	$body = twd_sk_help_feed( array(
		array( 'title' => 'Change <b>words</b> ' . "\xE2\x80\x94" . ' fast', 'summary' => 'Two minutes.', 'url' => 'https:/' . '/therapywebdesigns.co.uk/help/words/', 'kind' => 'video' ),
		array( 'title' => 'On a sub-domain', 'url' => 'https:' . chr( 47 ) . chr( 47 ) . 'learn.' . TWD_SK_Help::HOST . '/a', 'kind' => 'other' ),
		array( 'title' => 'Wrong host', 'url' => 'https:' . chr( 47 ) . chr( 47 ) . 'other-site.test/a' ),
		array( 'title' => 'Look-alike host', 'url' => 'https:' . chr( 47 ) . chr( 47 ) . 'not' . TWD_SK_Help::HOST . '/a' ),
		array( 'title' => 'Plain http', 'url' => 'http://therapywebdesigns.co.uk/a' ),
		array( 'title' => 'Script', 'url' => 'javascript:alert(1)' ),
		array( 'title' => 'With a port', 'url' => 'https:/' . '/therapywebdesigns.co.uk:8080/a' ),
		array( 'title' => 'With sign-in', 'url' => 'https:/' . '/user:pw' . chr( 64 ) . TWD_SK_Help::HOST . '/a' ),
		array( 'title' => '', 'url' => 'https:/' . '/therapywebdesigns.co.uk/a' ),
		'not an array',
	) );
	$items = TWD_SK_Help::parse( $body );
	twd_sk_eq( 2, count( $items ) );
	twd_sk_eq( 'Change words, fast', $items[0]['title'] );
	twd_sk_eq( 'video', $items[0]['kind'] );
	twd_sk_eq( 'article', $items[1]['kind'], 'an unknown kind is an article' );
} );

twd_sk_test( 'help: a wrong schema, bad JSON, no items or an oversize file all give nothing; twelve entries at most', function () {
	twd_sk_eq( array(), TWD_SK_Help::parse( '' ) );
	twd_sk_eq( array(), TWD_SK_Help::parse( 'not json' ) );
	twd_sk_eq( array(), TWD_SK_Help::parse( json_encode( array( 'schema' => 'other/1', 'items' => array( array( 'title' => 'x', 'url' => 'https:/' . '/therapywebdesigns.co.uk/a' ) ) ) ) ) );
	twd_sk_eq( array(), TWD_SK_Help::parse( twd_sk_help_feed( array() ) ) );
	twd_sk_eq( array(), TWD_SK_Help::parse( str_repeat( ' ', 60000 ) ) );
	$many = array();
	for ( $i = 0; $i < 30; $i++ ) {
		$many[] = array( 'title' => 'T' . $i, 'url' => 'https:/' . '/therapywebdesigns.co.uk/a' . $i );
	}
	twd_sk_eq( 12, count( TWD_SK_Help::parse( twd_sk_help_feed( $many ) ) ) );
} );

twd_sk_test( 'help: fetched once from the one fixed address with no redirects, cached, a failure is cached shorter, never in safe mode or for someone who cannot edit', function () {
	twd_stub_reset();
	$GLOBALS['twd_stub']['caps'] = true;
	$GLOBALS['twd_stub']['http'][ TWD_SK_Help::FEED_URL ] = array( 'response' => array( 'code' => 200 ), 'code' => 200, 'body' => twd_sk_help_feed( array( array( 'title' => 'One', 'url' => 'https:/' . '/therapywebdesigns.co.uk/a' ) ) ) );
	twd_sk_eq( 1, count( TWD_SK_Help::items() ) );
	twd_sk_eq( 1, count( TWD_SK_Help::items() ) );
	twd_sk_eq( 1, count( $GLOBALS['twd_stub']['requests'] ), 'cached' );
	twd_sk_eq( TWD_SK_Help::FEED_URL, $GLOBALS['twd_stub']['requests'][0]['url'] );
	twd_sk_eq( 0, $GLOBALS['twd_stub']['requests'][0]['args']['redirection'] );
	// A failure shows nothing and is not retried at once.
	twd_stub_reset();
	$GLOBALS['twd_stub']['caps'] = true;
	twd_sk_eq( array(), TWD_SK_Help::items() );
	twd_sk_eq( array(), TWD_SK_Help::items() );
	twd_sk_eq( 1, count( $GLOBALS['twd_stub']['requests'] ), 'a failure is cached too' );
	// Safe mode, and someone who cannot edit pages: no request at all.
	twd_stub_reset();
	update_option( 'twd_sk_safe_mode', true );
	$GLOBALS['twd_stub']['caps'] = true;
	twd_sk_eq( array(), TWD_SK_Help::items() );
	twd_stub_reset();
	$GLOBALS['twd_stub']['caps'] = false;
	twd_sk_eq( array(), TWD_SK_Help::items() );
	twd_sk_true( empty( $GLOBALS['twd_stub']['requests'] ), 'no request' );
} );

twd_sk_test( 'help: the Welcome screen shows the entries under the how-tos, escaped, opening in a new tab; with none there is no heading', function () {
	twd_stub_reset();
	$GLOBALS['twd_stub']['caps'] = true;
	set_transient( TWD_SK_Help::TRANSIENT, array( 'items' => array( array( 'title' => 'Words <i>', 'summary' => 'Short.', 'url' => 'https:/' . '/therapywebdesigns.co.uk/a', 'kind' => 'video' ) ) ) );
	ob_start();
	TWD_SK_Welcome::render();
	$out = ob_get_clean();
	twd_sk_has( 'Guides and videos', $out );
	twd_sk_has( 'target="_blank" rel="noopener noreferrer"', $out );
	twd_sk_has( 'Words &lt;i&gt;', $out );
	twd_sk_has( '>Video<', $out );
	twd_sk_true( strpos( $out, 'Guides and videos' ) > strpos( $out, 'How do I' ), 'under the how-tos' );
	set_transient( TWD_SK_Help::TRANSIENT, array( 'items' => array() ) );
	ob_start();
	TWD_SK_Welcome::render();
	twd_sk_hasnt( 'Guides and videos', ob_get_clean() );
} );

twd_sk_test( 'help: the committed list file is valid for the schema, and the source holds no site data, key or other address', function () {
	$json = file_get_contents( ABSPATH . 'help/twd-site-help.json' );
	$data = json_decode( $json, true );
	twd_sk_eq( 'twd-site-help/1', $data['schema'] );
	twd_sk_true( is_array( $data['items'] ) );
	twd_sk_eq( count( $data['items'] ), count( TWD_SK_Help::parse( $json ) ) + ( empty( $data['items'] ) ? 0 : 0 ), 'every committed entry passes the checks' );
	$src = file_get_contents( ABSPATH . 'includes/class-twd-sk-help.php' );
	twd_sk_eq( 1, preg_match_all( '#https://#', $src ), 'one fixed address in the class' );
	foreach ( array( 'get_option', 'home_url', 'get_bloginfo', 'wp_get_current_user', 'x-api-key' ) as $bad ) {
		twd_sk_hasnt( $bad, $src, 'nothing about the site is sent' );
	}
	twd_sk_hasnt( 'TWD_SK_Store', $src );
} );
