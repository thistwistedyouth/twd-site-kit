<?php
// TWD_SK_Help (0.22.0): the Guides and videos list on the Welcome screen, read from one category on the agency site.
// One fixed host, checked entries, fails closed.

function twd_sk_help_post( $title, $url, $extra = array(), $excerpt = '<p>Short.</p>' ) {
	return array( 'title' => array( 'rendered' => $title ), 'excerpt' => array( 'rendered' => $excerpt ), 'link' => $url, 'class_list' => array_merge( array( 'post', 'category-client-help' ), $extra ) );
}

twd_sk_test( 'help: a good post is kept; bad links, wrong hosts, other schemes and empty titles are dropped; text is plain; a video tag makes a video', function () {
	$ok   = 'https:/' . '/therapywebdesigns.co.uk/help/words/';
	$body = json_encode( array(
		twd_sk_help_post( 'Change <b>words</b> ' . "\xE2\x80\x94" . ' fast &amp; easy', $ok, array( 'tag-video' ), '<p>Two minutes.</p>' ),
		twd_sk_help_post( 'On a sub-domain', 'https:' . chr( 47 ) . chr( 47 ) . 'learn.' . TWD_SK_Help::HOST . '/a' ),
		twd_sk_help_post( 'Wrong host', 'https:' . chr( 47 ) . chr( 47 ) . 'other-site.test/a' ),
		twd_sk_help_post( 'Look-alike host', 'https:' . chr( 47 ) . chr( 47 ) . 'not' . TWD_SK_Help::HOST . '/a' ),
		twd_sk_help_post( 'Plain http', 'http://therapywebdesigns.co.uk/a' ),
		twd_sk_help_post( 'Script', 'javascript:alert(1)' ),
		twd_sk_help_post( 'With a port', 'https:/' . '/therapywebdesigns.co.uk:8080/a' ),
		twd_sk_help_post( 'With sign-in', 'https:/' . '/user:pw' . chr( 64 ) . TWD_SK_Help::HOST . '/a' ),
		twd_sk_help_post( '', $ok ),
		'not an array',
	) );
	$items = TWD_SK_Help::parse( $body );
	twd_sk_eq( 2, count( $items ) );
	twd_sk_eq( 'Change words, fast & easy', $items[0]['title'] );
	twd_sk_eq( 'Two minutes.', $items[0]['summary'] );
	twd_sk_eq( 'video', $items[0]['kind'] );
	twd_sk_eq( 'article', $items[1]['kind'], 'no video tag is an article' );
} );

twd_sk_test( 'help: bad JSON, nothing or an oversize reply all give nothing; twelve entries at most', function () {
	twd_sk_eq( array(), TWD_SK_Help::parse( '' ) );
	twd_sk_eq( array(), TWD_SK_Help::parse( 'not json' ) );
	twd_sk_eq( array(), TWD_SK_Help::parse( '[]' ) );
	twd_sk_eq( array(), TWD_SK_Help::parse( str_repeat( ' ', 70000 ) ) );
	$many = array();
	for ( $i = 0; $i < 30; $i++ ) {
		$many[] = twd_sk_help_post( 'T' . $i, 'https:/' . '/therapywebdesigns.co.uk/a' . $i );
	}
	twd_sk_eq( 12, count( TWD_SK_Help::parse( json_encode( $many ) ) ) );
} );

twd_sk_test( 'help: two fixed requests (the category, then its posts) with no redirects, cached, a failure is cached shorter, never in safe mode or for someone who cannot edit', function () {
	twd_stub_reset();
	$GLOBALS['twd_stub']['caps'] = true;
	$GLOBALS['twd_stub']['http'][ TWD_SK_Help::categories_url() ] = array( 'response' => array( 'code' => 200 ), 'code' => 200, 'body' => '[{"id":7}]' );
	$GLOBALS['twd_stub']['http'][ TWD_SK_Help::posts_url( 7 ) ]   = array( 'response' => array( 'code' => 200 ), 'code' => 200, 'body' => json_encode( array( twd_sk_help_post( 'One', 'https:/' . '/therapywebdesigns.co.uk/a' ) ) ) );
	twd_sk_eq( 1, count( TWD_SK_Help::items() ) );
	twd_sk_eq( 1, count( TWD_SK_Help::items() ) );
	twd_sk_eq( 2, count( $GLOBALS['twd_stub']['requests'] ), 'cached after two requests' );
	twd_sk_eq( TWD_SK_Help::categories_url(), $GLOBALS['twd_stub']['requests'][0]['url'] );
	twd_sk_eq( TWD_SK_Help::posts_url( 7 ), $GLOBALS['twd_stub']['requests'][1]['url'] );
	twd_sk_eq( 0, $GLOBALS['twd_stub']['requests'][1]['args']['redirection'] );
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

twd_sk_test( 'help: the source holds no site data, key or other address', function () {
	$src = file_get_contents( ABSPATH . 'includes/class-twd-sk-help.php' );
	twd_sk_eq( 2, preg_match_all( '#https://#', $src ), 'two fixed addresses, both built on the one host constant' );
	foreach ( array( 'get_option', 'home_url', 'get_bloginfo', 'wp_get_current_user', 'x-api-key' ) as $bad ) {
		twd_sk_hasnt( $bad, $src, 'nothing about the site is sent' );
	}
	twd_sk_hasnt( 'TWD_SK_Store', $src );
} );
