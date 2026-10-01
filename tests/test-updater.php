<?php
// The self-hosted updater: fixed addresses, checksum before install, fail closed.

function twd_sk_sha( $bytes ) {
	return hash( 'sha256', $bytes );
}
function twd_sk_manifest( $over = array() ) {
	return array_merge( array(
		'version'      => '9.9.9',
		'download_url' => TWD_SK_Updater::ZIP_URL,
		'sha256'       => twd_sk_sha( 'zip-bytes' ),
		'homepage'     => 'https://therapywebdesigns.co.uk/',
		'requires'     => '6.0',
		'tested'       => '6.6',
		'requires_php' => '7.4',
		'last_updated' => '2026-10-01',
		'description'  => 'Pages for therapist sites.',
		'changelog'    => '<h4>9.9.9</h4><ul><li>Something changed.</li></ul>',
	), $over );
}
function twd_sk_serve( $url, $code, $body ) {
	$GLOBALS['twd_stub']['http'][ $url ] = array( 'code' => $code, 'body' => $body );
}
function twd_sk_serve_manifest( $over = array() ) {
	twd_sk_serve( TWD_SK_Updater::JSON_URL, 200, json_encode( twd_sk_manifest( $over ) ) );
}
function twd_sk_serve_zip( $bytes = 'zip-bytes' ) {
	twd_sk_serve( TWD_SK_Updater::ZIP_URL, 200, $bytes );
}
function twd_sk_urls_requested() {
	return array_map( function ( $r ) {
		return $r['url'];
	}, $GLOBALS['twd_stub']['requests'] );
}
function twd_sk_transient( $transient_checked = true ) {
	$t = new stdClass();
	if ( $transient_checked ) {
		$t->checked = array( 'twd-site-kit/twd-site-kit.php' => TWD_SK_VERSION );
	}
	$t->response  = array();
	$t->no_update = array( 'twd-site-kit/twd-site-kit.php' => 'x' );
	return $t;
}

// -- the fixed addresses ---------------------------------------------------------

twd_sk_test( 'updater: the two addresses are fixed https constants on raw.githubusercontent.com for this one repo', function () {
	twd_sk_eq( 'https://raw.githubusercontent.com/thistwistedyouth/twd-site-kit/main/dist/twd-site-kit-update.json', TWD_SK_Updater::JSON_URL );
	twd_sk_eq( 'https://raw.githubusercontent.com/thistwistedyouth/twd-site-kit/main/dist/twd-site-kit-latest.zip', TWD_SK_Updater::ZIP_URL );
} );

twd_sk_test( 'updater: the source holds no other web address than the fixed ones, the repo page and the agency site', function () {
	$src = file_get_contents( ABSPATH . 'includes/class-twd-sk-updater.php' );
	preg_match_all( '#https?://[A-Za-z0-9./_-]+#', $src, $m );
	$allowed = array(
		'https://raw.githubusercontent.com/thistwistedyouth/twd-site-kit/main/dist/',
		'https://github.com/thistwistedyouth/twd-site-kit',
		'https://therapywebdesigns.co.uk/',
		'https://',
	);
	foreach ( array_unique( $m[0] ) as $url ) {
		twd_sk_true( in_array( $url, $allowed, true ), 'allowed address: ' . $url );
	}
	twd_sk_hasnt( 'http://', $src );
} );

twd_sk_test( 'updater: the package address is never read from the update file', function () {
	$src = file_get_contents( ABSPATH . 'includes/class-twd-sk-updater.php' );
	twd_sk_hasnt( "remote['download_url']", $src );
	twd_sk_hasnt( "->download_url", $src );
	twd_sk_has( '$item->package      = self::ZIP_URL;', $src );
	twd_sk_has( '$info->download_link = self::ZIP_URL;', $src );
} );

// -- admin only ---------------------------------------------------------------------

twd_sk_test( 'updater: outside the admin it hooks nothing', function () {
	$GLOBALS['twd_stub']['admin'] = false;
	TWD_SK_Updater::init();
	twd_sk_eq( array(), $GLOBALS['twd_stub']['hooks'] );
} );

twd_sk_test( 'updater: in the admin it hooks the update list, details window, links, download check, folder check and the check action', function () {
	TWD_SK_Updater::init();
	foreach ( array(
		'pre_set_site_transient_update_plugins', 'plugins_api', 'plugin_row_meta', 'plugin_action_links_twd-site-kit/twd-site-kit.php',
		'upgrader_pre_download', 'upgrader_source_selection', 'delete_site_transient_update_plugins', 'upgrader_process_complete',
		'admin_post_twd_sk_check_updates', 'admin_notices',
	) as $hook ) {
		twd_sk_true( isset( $GLOBALS['twd_stub']['hooks'][ $hook ] ), 'hooked: ' . $hook );
	}
} );

twd_sk_test( 'updater: the main plugin file starts the updater', function () {
	twd_sk_has( 'TWD_SK_Updater::init();', file_get_contents( ABSPATH . 'twd-site-kit.php' ) );
	twd_sk_has( "includes/class-twd-sk-updater.php", file_get_contents( ABSPATH . 'twd-site-kit.php' ) );
} );

// -- the update file: parsing and validation ---------------------------------------------

twd_sk_test( 'manifest: a good update file parses and the package address is the fixed one', function () {
	list( $m, $err ) = TWD_SK_Updater::parse_manifest( json_encode( twd_sk_manifest() ) );
	twd_sk_eq( '', $err );
	twd_sk_eq( '9.9.9', $m['version'] );
	twd_sk_eq( TWD_SK_Updater::ZIP_URL, $m['download_url'] );
	twd_sk_eq( twd_sk_sha( 'zip-bytes' ), $m['sha256'] );
	twd_sk_eq( '2026-10-01', $m['last_updated'] );
} );

twd_sk_test( 'manifest: the download address may be left out, it is still the fixed one', function () {
	$data = twd_sk_manifest();
	unset( $data['download_url'] );
	list( $m ) = TWD_SK_Updater::parse_manifest( json_encode( $data ) );
	twd_sk_eq( TWD_SK_Updater::ZIP_URL, $m['download_url'] );
} );

twd_sk_test( 'manifest: a download address that is not exactly the fixed one is rejected, never followed', function () {
	foreach ( array(
		'https://evil.example/twd-site-kit-latest.zip',
		'http://raw.githubusercontent.com/thistwistedyouth/twd-site-kit/main/dist/twd-site-kit-latest.zip',
		'https://' . 'raw.githubusercontent.com/someoneelse/twd-site-kit/main/dist/twd-site-kit-latest.zip',
		'https://raw.githubusercontent.com/thistwistedyouth/twd-site-kit/main/dist/twd-site-kit-latest.zip?x=1',
		'https://raw.githubusercontent.com/thistwistedyouth/twd-site-kit/dev/dist/twd-site-kit-latest.zip',
		'',
		array( 'x' ),
	) as $url ) {
		list( $m, $err ) = TWD_SK_Updater::parse_manifest( json_encode( twd_sk_manifest( array( 'download_url' => $url ) ) ) );
		twd_sk_eq( null, $m, 'rejected: ' . json_encode( $url ) );
		twd_sk_has( 'unexpected address', $err );
	}
} );

twd_sk_test( 'manifest: not JSON, a JSON list, or an empty body is rejected', function () {
	foreach ( array( '', 'not json', '[1,2,3]', '"string"', '12', 'null' ) as $body ) {
		list( $m, $err ) = TWD_SK_Updater::parse_manifest( $body );
		twd_sk_eq( null, $m, json_encode( $body ) );
		twd_sk_true( '' !== $err );
	}
} );

twd_sk_test( 'manifest: the version must be three plain numbers', function () {
	foreach ( array( '', '1', '1.2', '1.2.3.4', 'v1.2.3', '1.2.3-beta', '1.2.x', ' ', '1.2.3; drop', array( 1 ), 123 ) as $v ) {
		list( $m ) = TWD_SK_Updater::parse_manifest( json_encode( twd_sk_manifest( array( 'version' => $v ) ) ) );
		twd_sk_eq( null, $m, 'rejected version: ' . json_encode( $v ) );
	}
	list( $m ) = TWD_SK_Updater::parse_manifest( json_encode( twd_sk_manifest( array( 'version' => '10.20.30' ) ) ) );
	twd_sk_eq( '10.20.30', $m['version'] );
} );

twd_sk_test( 'manifest: with no valid sha256 the update is not offered at all (fails closed)', function () {
	$base = twd_sk_manifest();
	unset( $base['sha256'] );
	list( $m, $err ) = TWD_SK_Updater::parse_manifest( json_encode( $base ) );
	twd_sk_eq( null, $m, 'missing' );
	twd_sk_has( 'no valid checksum', $err );
	foreach ( array( '', 'abc', str_repeat( 'g', 64 ), str_repeat( 'a', 63 ), str_repeat( 'a', 65 ), array( 'x' ), 12345 ) as $sha ) {
		list( $m ) = TWD_SK_Updater::parse_manifest( json_encode( twd_sk_manifest( array( 'sha256' => $sha ) ) ) );
		twd_sk_eq( null, $m, 'rejected sha: ' . json_encode( $sha ) );
	}
} );

twd_sk_test( 'manifest: an upper-case sha256 is accepted and stored lower-case', function () {
	list( $m ) = TWD_SK_Updater::parse_manifest( json_encode( twd_sk_manifest( array( 'sha256' => strtoupper( twd_sk_sha( 'x' ) ) ) ) ) );
	twd_sk_eq( twd_sk_sha( 'x' ), $m['sha256'] );
} );

twd_sk_test( 'manifest: the changelog is cleaned to simple tags and the description to plain text', function () {
	list( $m ) = TWD_SK_Updater::parse_manifest( json_encode( twd_sk_manifest( array(
		'changelog'   => '<h4>1.0.0</h4><ul><li onclick="x()">Fixed <script>alert(1)</script><strong>it</strong></li></ul><iframe src="x"></iframe>',
		'description' => '<b>Plain</b> <script>x</script>words',
	) ) ) );
	twd_sk_hasnt( '<script', $m['changelog'] );
	twd_sk_hasnt( '<iframe', $m['changelog'] );
	twd_sk_hasnt( 'onclick', $m['changelog'] );
	twd_sk_has( '<strong>it</strong>', $m['changelog'] );
	twd_sk_hasnt( '<', $m['description'] );
} );

twd_sk_test( 'manifest: the homepage must be https, otherwise the agency site is used; odd version fields and dates are blanked', function () {
	list( $m ) = TWD_SK_Updater::parse_manifest( json_encode( twd_sk_manifest( array( 'homepage' => 'javascript:alert(1)', 'requires' => '6.x', 'tested' => 'latest', 'requires_php' => '7.4', 'last_updated' => 'yesterday' ) ) ) );
	twd_sk_eq( 'https://therapywebdesigns.co.uk/', $m['homepage'] );
	twd_sk_eq( '', $m['requires'] );
	twd_sk_eq( '', $m['tested'] );
	twd_sk_eq( '7.4', $m['requires_php'] );
	twd_sk_eq( '', $m['last_updated'] );
} );

// -- fetching and caching --------------------------------------------------------------

twd_sk_test( 'fetch: reads the update file once, caches the cleaned result for 12 hours, then serves the cache', function () {
	twd_sk_serve_manifest();
	$a = TWD_SK_Updater::fetch_manifest();
	$b = TWD_SK_Updater::fetch_manifest();
	twd_sk_eq( '9.9.9', $a['version'] );
	twd_sk_eq( $a, $b );
	twd_sk_eq( 1, count( $GLOBALS['twd_stub']['requests'] ), 'one request only' );
	twd_sk_eq( 43200, $GLOBALS['twd_stub']['transients']['twd_sk_update_check']['seconds'] );
	twd_sk_true( is_array( $GLOBALS['twd_stub']['transients']['twd_sk_update_check']['value'] ), 'the cleaned manifest is cached, not the raw response' );
} );

twd_sk_test( 'fetch: asks only for the fixed JSON address, with a short timeout and no redirects', function () {
	twd_sk_serve_manifest();
	TWD_SK_Updater::fetch_manifest();
	$req = $GLOBALS['twd_stub']['requests'][0];
	twd_sk_eq( TWD_SK_Updater::JSON_URL, $req['url'] );
	twd_sk_eq( 0, $req['args']['redirection'] );
	twd_sk_true( $req['args']['timeout'] <= 10 );
} );

twd_sk_test( 'fetch: a fresh fetch skips the cache and refreshes it', function () {
	twd_sk_serve_manifest();
	TWD_SK_Updater::fetch_manifest();
	twd_sk_serve_manifest( array( 'version' => '9.9.10' ) );
	twd_sk_eq( '9.9.9', TWD_SK_Updater::fetch_manifest()['version'], 'cached' );
	twd_sk_eq( '9.9.10', TWD_SK_Updater::fetch_manifest( true )['version'], 'fresh' );
	twd_sk_eq( '9.9.10', TWD_SK_Updater::fetch_manifest()['version'], 'cache refreshed' );
} );

twd_sk_test( 'fetch: a network error, a non-200 or a bad file returns false and records a plain reason', function () {
	$GLOBALS['twd_stub']['http'][ TWD_SK_Updater::JSON_URL ] = new WP_Error( 'http_request_failed', 'cURL error 6: could not resolve host' );
	twd_sk_eq( false, TWD_SK_Updater::fetch_manifest() );
	twd_sk_has( 'could not resolve host', TWD_SK_Updater::last_error() );

	twd_sk_serve( TWD_SK_Updater::JSON_URL, 404, 'nope' );
	twd_sk_eq( false, TWD_SK_Updater::fetch_manifest() );
	twd_sk_eq( 'GitHub returned HTTP 404.', TWD_SK_Updater::last_error() );

	twd_sk_serve( TWD_SK_Updater::JSON_URL, 302, '' );
	twd_sk_eq( false, TWD_SK_Updater::fetch_manifest(), 'a redirect is not followed and counts as a failure' );

	twd_sk_serve( TWD_SK_Updater::JSON_URL, 200, '{"version":"1.0.0"}' );
	twd_sk_eq( false, TWD_SK_Updater::fetch_manifest() );
	twd_sk_has( 'no valid checksum', TWD_SK_Updater::last_error() );
	twd_sk_true( false === get_transient( 'twd_sk_update_check' ), 'nothing cached after a failure' );
} );

twd_sk_test( 'fetch: a good fetch clears an earlier recorded error', function () {
	set_transient( 'twd_sk_update_check_error', 'old problem', 60 );
	twd_sk_serve_manifest();
	TWD_SK_Updater::fetch_manifest();
	twd_sk_eq( '', TWD_SK_Updater::last_error() );
} );

twd_sk_test( 'fetch: a cached value of the wrong shape is ignored and replaced', function () {
	set_transient( 'twd_sk_update_check', array( 'version' => '9.9.9' ), 60 );
	twd_sk_serve_manifest( array( 'version' => '8.8.8' ) );
	twd_sk_eq( '8.8.8', TWD_SK_Updater::fetch_manifest()['version'] );
} );

twd_sk_test( 'fetch: clear_cache removes both the cache and the recorded error', function () {
	set_transient( 'twd_sk_update_check', array( 'x' ), 60 );
	set_transient( 'twd_sk_update_check_error', 'e', 60 );
	TWD_SK_Updater::clear_cache();
	twd_sk_eq( array(), $GLOBALS['twd_stub']['transients'] );
} );

// -- the update row ------------------------------------------------------------------------

twd_sk_test( 'update row: a newer version is offered with the fixed package, versions and requirements', function () {
	twd_sk_serve_manifest();
	$t    = TWD_SK_Updater::check_for_update( twd_sk_transient() );
	$item = $t->response['twd-site-kit/twd-site-kit.php'];
	twd_sk_eq( '9.9.9', $item->new_version );
	twd_sk_eq( TWD_SK_Updater::ZIP_URL, $item->package );
	twd_sk_eq( 'twd-site-kit', $item->slug );
	twd_sk_eq( '7.4', $item->requires_php );
	twd_sk_eq( '6.6', $item->tested );
	twd_sk_true( ! isset( $t->no_update['twd-site-kit/twd-site-kit.php'] ), 'removed from the no-update list' );
} );

twd_sk_test( 'update row: the same or an older version offers nothing and clears any stale offer', function () {
	foreach ( array( TWD_SK_VERSION, '0.0.1' ) as $version ) {
		twd_sk_serve_manifest( array( 'version' => $version ) );
		TWD_SK_Updater::clear_cache();
		$t = twd_sk_transient();
		$t->response['twd-site-kit/twd-site-kit.php'] = 'stale';
		$t = TWD_SK_Updater::check_for_update( $t );
		twd_sk_true( ! isset( $t->response['twd-site-kit/twd-site-kit.php'] ), $version );
	}
} );

twd_sk_test( 'update row: an update file with no checksum offers nothing', function () {
	$data = twd_sk_manifest();
	unset( $data['sha256'] );
	twd_sk_serve( TWD_SK_Updater::JSON_URL, 200, json_encode( $data ) );
	$t = TWD_SK_Updater::check_for_update( twd_sk_transient() );
	twd_sk_eq( array(), $t->response );
} );

twd_sk_test( 'update row: an update file naming another package address offers nothing', function () {
	twd_sk_serve_manifest( array( 'download_url' => 'https://evil.example/x.zip' ) );
	$t = TWD_SK_Updater::check_for_update( twd_sk_transient() );
	twd_sk_eq( array(), $t->response );
} );

twd_sk_test( 'update row: when GitHub cannot be reached the update list is left untouched', function () {
	$GLOBALS['twd_stub']['http'][ TWD_SK_Updater::JSON_URL ] = new WP_Error( 'x', 'down' );
	$t = twd_sk_transient();
	twd_sk_eq( $t, TWD_SK_Updater::check_for_update( $t ) );
} );

twd_sk_test( 'update row: a transient that has not checked anything yet is left alone and costs no request', function () {
	twd_sk_serve_manifest();
	$t = twd_sk_transient( false );
	twd_sk_eq( $t, TWD_SK_Updater::check_for_update( $t ) );
	twd_sk_eq( 0, count( $GLOBALS['twd_stub']['requests'] ) );
	twd_sk_eq( 'x', 'x' );
} );

twd_sk_test( 'details window: answers for this plugin only, with the changelog, and the fixed download link', function () {
	twd_sk_serve_manifest();
	$info = TWD_SK_Updater::plugin_info( false, 'plugin_information', (object) array( 'slug' => 'twd-site-kit' ) );
	twd_sk_eq( 'TWD Site Kit', $info->name );
	twd_sk_eq( '9.9.9', $info->version );
	twd_sk_eq( TWD_SK_Updater::ZIP_URL, $info->download_link );
	twd_sk_has( '<h4>9.9.9</h4>', $info->sections['changelog'] );
	twd_sk_eq( false, TWD_SK_Updater::plugin_info( false, 'plugin_information', (object) array( 'slug' => 'other-plugin' ) ) );
	twd_sk_eq( 'x', TWD_SK_Updater::plugin_info( 'x', 'query_plugins', (object) array( 'slug' => 'twd-site-kit' ) ) );
} );

// -- install: checksum before anything is unpacked ------------------------------------------

twd_sk_test( 'install: another plugin package is left completely alone', function () {
	twd_sk_eq( false, TWD_SK_Updater::verify_download( false, 'https://downloads.example/other.zip' ) );
	twd_sk_eq( 'already', TWD_SK_Updater::verify_download( 'already', 'https://downloads.example/other.zip' ) );
	twd_sk_eq( 0, count( $GLOBALS['twd_stub']['requests'] ) );
} );

twd_sk_test( 'install: a matching checksum returns the verified downloaded file', function () {
	twd_sk_serve_manifest();
	twd_sk_serve_zip( 'zip-bytes' );
	$path = TWD_SK_Updater::verify_download( false, TWD_SK_Updater::ZIP_URL );
	twd_sk_true( is_string( $path ) && is_file( $path ), 'a file path comes back' );
	twd_sk_eq( 'zip-bytes', file_get_contents( $path ) );
	unlink( $path );
} );

twd_sk_test( 'install: only the two fixed addresses are requested, the zip with no redirects and a download to a file', function () {
	twd_sk_serve_manifest();
	twd_sk_serve_zip();
	$path = TWD_SK_Updater::verify_download( false, TWD_SK_Updater::ZIP_URL );
	unlink( $path );
	twd_sk_eq( array( TWD_SK_Updater::JSON_URL, TWD_SK_Updater::ZIP_URL ), twd_sk_urls_requested() );
	foreach ( $GLOBALS['twd_stub']['requests'] as $req ) {
		twd_sk_true( 0 === strpos( $req['url'], 'https://raw.githubusercontent.com/thistwistedyouth/twd-site-kit/main/dist/' ), 'fixed https address' );
		twd_sk_eq( 0, $req['args']['redirection'], 'no redirects' );
	}
	twd_sk_true( ! empty( $GLOBALS['twd_stub']['requests'][1]['args']['stream'] ) );
} );

twd_sk_test( 'install: the update file is fetched fresh at install time, not trusted from a 12 hour old cache', function () {
	twd_sk_serve_manifest( array( 'version' => '9.9.8' ) );
	TWD_SK_Updater::fetch_manifest();
	twd_sk_serve_manifest( array( 'version' => '9.9.9', 'sha256' => twd_sk_sha( 'newer-bytes' ) ) );
	twd_sk_serve_zip( 'newer-bytes' );
	$path = TWD_SK_Updater::verify_download( false, TWD_SK_Updater::ZIP_URL );
	twd_sk_true( is_string( $path ), 'verified against the fresh checksum' );
	unlink( $path );
} );

twd_sk_test( 'install: a checksum mismatch refuses, deletes the download and says so in plain words', function () {
	twd_sk_serve_manifest();
	twd_sk_serve_zip( 'TAMPERED-bytes' );
	$r = TWD_SK_Updater::verify_download( false, TWD_SK_Updater::ZIP_URL );
	twd_sk_is_error( 'twd_sk_update_checksum', $r );
	twd_sk_has( 'did not match its checksum', $r->get_error_message() );
	twd_sk_has( 'Nothing was changed', $r->get_error_message() );
	twd_sk_eq( 1, count( $GLOBALS['twd_stub']['deleted_files'] ), 'the bad download was deleted' );
	twd_sk_true( ! is_file( $GLOBALS['twd_stub']['deleted_files'][0] ) );
} );

twd_sk_test( 'install: if the checksum cannot be fetched, nothing is downloaded and nothing is installed', function () {
	$GLOBALS['twd_stub']['http'][ TWD_SK_Updater::JSON_URL ] = new WP_Error( 'x', 'down' );
	twd_sk_serve_zip();
	$r = TWD_SK_Updater::verify_download( false, TWD_SK_Updater::ZIP_URL );
	twd_sk_is_error( 'twd_sk_update_unverified', $r );
	twd_sk_has( 'Nothing was changed', $r->get_error_message() );
	twd_sk_eq( array( TWD_SK_Updater::JSON_URL ), twd_sk_urls_requested(), 'the zip was never requested' );
} );

twd_sk_test( 'install: an update file with no checksum refuses to install', function () {
	$data = twd_sk_manifest();
	unset( $data['sha256'] );
	twd_sk_serve( TWD_SK_Updater::JSON_URL, 200, json_encode( $data ) );
	twd_sk_serve_zip();
	twd_sk_is_error( 'twd_sk_update_unverified', TWD_SK_Updater::verify_download( false, TWD_SK_Updater::ZIP_URL ) );
} );

twd_sk_test( 'install: a version that is not newer than the installed one is refused (no silent downgrade)', function () {
	foreach ( array( TWD_SK_VERSION, '0.0.1' ) as $version ) {
		twd_sk_serve_manifest( array( 'version' => $version ) );
		twd_sk_serve_zip();
		$r = TWD_SK_Updater::verify_download( false, TWD_SK_Updater::ZIP_URL );
		twd_sk_is_error( 'twd_sk_update_not_newer', $r, $version );
	}
	twd_sk_true( ! in_array( TWD_SK_Updater::ZIP_URL, twd_sk_urls_requested(), true ), 'the zip was never fetched' );
} );

twd_sk_test( 'install: a failed, redirected or error download is refused and the temp file removed', function () {
	twd_sk_serve_manifest();
	foreach ( array( 404, 302, 500 ) as $code ) {
		twd_sk_serve( TWD_SK_Updater::ZIP_URL, $code, 'body' );
		twd_sk_is_error( 'twd_sk_update_download', TWD_SK_Updater::verify_download( false, TWD_SK_Updater::ZIP_URL ), (string) $code );
	}
	$GLOBALS['twd_stub']['http'][ TWD_SK_Updater::ZIP_URL ] = new WP_Error( 'x', 'timeout' );
	twd_sk_is_error( 'twd_sk_update_download', TWD_SK_Updater::verify_download( false, TWD_SK_Updater::ZIP_URL ) );
	twd_sk_eq( 4, count( $GLOBALS['twd_stub']['deleted_files'] ) );
} );

twd_sk_test( 'install: an empty download is refused even if its hash were somehow listed', function () {
	twd_sk_serve_manifest( array( 'sha256' => twd_sk_sha( '' ) ) );
	twd_sk_serve_zip( '' );
	twd_sk_is_error( 'twd_sk_update_size', TWD_SK_Updater::verify_download( false, TWD_SK_Updater::ZIP_URL ) );
} );

twd_sk_test( 'install: an oversize download is refused', function () {
	$big = str_repeat( 'a', TWD_SK_Updater::MAX_ZIP_BYTES + 1 );
	twd_sk_serve_manifest( array( 'sha256' => twd_sk_sha( $big ) ) );
	twd_sk_serve_zip( $big );
	twd_sk_is_error( 'twd_sk_update_size', TWD_SK_Updater::verify_download( false, TWD_SK_Updater::ZIP_URL ) );
} );

twd_sk_test( 'install: the hash is compared with hash_equals and the failure messages say nothing was changed', function () {
	$src = file_get_contents( ABSPATH . 'includes/class-twd-sk-updater.php' );
	twd_sk_has( 'hash_equals( $manifest[\'sha256\'], $actual )', $src );
	twd_sk_has( "hash_file( 'sha256', \$tmp )", $src );
	preg_match_all( '/new WP_Error\( \'twd_sk_[a-z_]+\', __\( \'([^\']+)\'/', $src, $m );
	twd_sk_true( count( $m[1] ) >= 6, 'error messages found' );
	foreach ( $m[1] as $message ) {
		twd_sk_has( 'Nothing was changed', $message );
	}
} );

// -- the unpacked package -------------------------------------------------------------------

twd_sk_test( 'folder check: another plugin is passed through untouched', function () {
	twd_sk_eq( '/tmp/whatever/', TWD_SK_Updater::check_source( '/tmp/whatever/', '/tmp', null, array( 'plugin' => 'other/other.php' ) ) );
	twd_sk_eq( '/tmp/whatever/', TWD_SK_Updater::check_source( '/tmp/whatever/', '/tmp', null, array() ) );
} );

twd_sk_test( 'folder check: our package must unpack to a single twd-site-kit folder holding the plugin file', function () {
	$base = sys_get_temp_dir() . '/twdsk-src-' . getmypid();
	mkdir( $base . '/twd-site-kit', 0777, true );
	file_put_contents( $base . '/twd-site-kit/twd-site-kit.php', '<?php' );
	$extra = array( 'plugin' => 'twd-site-kit/twd-site-kit.php' );
	twd_sk_eq( $base . '/twd-site-kit/', TWD_SK_Updater::check_source( $base . '/twd-site-kit/', $base, null, $extra ) );
	twd_sk_eq( $base . '/twd-site-kit', TWD_SK_Updater::check_source( $base . '/twd-site-kit', $base, null, $extra ), 'no trailing slash' );

	mkdir( $base . '/twd-site-kit-0.2.2', 0777, true );
	file_put_contents( $base . '/twd-site-kit-0.2.2/twd-site-kit.php', '<?php' );
	twd_sk_is_error( 'twd_sk_update_layout', TWD_SK_Updater::check_source( $base . '/twd-site-kit-0.2.2/', $base, null, $extra ), 'wrong folder name' );

	mkdir( $base . '/empty/twd-site-kit', 0777, true );
	twd_sk_is_error( 'twd_sk_update_layout', TWD_SK_Updater::check_source( $base . '/empty/twd-site-kit/', $base, null, $extra ), 'no plugin file' );
	twd_sk_has( 'Nothing was changed', TWD_SK_Updater::check_source( $base . '/empty/twd-site-kit/', $base, null, $extra )->get_error_message() );
} );

// -- "Check for updates" -----------------------------------------------------------------------

twd_sk_test( 'check link: the plugin row gets a Check for updates link with a nonce that runs the check action', function () {
	$links = TWD_SK_Updater::action_links( array( '<a>Deactivate</a>' ) );
	twd_sk_eq( 2, count( $links ) );
	twd_sk_has( 'Check for updates', $links[1] );
	twd_sk_has( 'admin-post.php?action=twd_sk_check_updates', $links[1] );
	twd_sk_has( '_wpnonce=NONCE_twd_sk_check_updates', $links[1] );
} );

twd_sk_test( 'check link: a View source link is added on this plugin row only', function () {
	$mine  = TWD_SK_Updater::row_meta( array(), 'twd-site-kit/twd-site-kit.php' );
	$other = TWD_SK_Updater::row_meta( array(), 'other/other.php' );
	twd_sk_has( 'https://github.com/thistwistedyouth/twd-site-kit', $mine[0] );
	twd_sk_eq( array(), $other );
} );

twd_sk_test( 'check link: the check needs the update_plugins permission and a valid nonce', function () {
	$GLOBALS['twd_stub']['caps'] = false;
	$threw = false;
	try {
		TWD_SK_Updater::handle_manual_check();
	} catch ( TWD_SK_Stub_Die $e ) {
		$threw = true;
	}
	twd_sk_true( $threw, 'refused without permission' );
	$GLOBALS['twd_stub']['caps']       = true;
	$GLOBALS['twd_stub']['referer_ok'] = false;
	$threw = false;
	try {
		TWD_SK_Updater::handle_manual_check();
	} catch ( TWD_SK_Stub_Die $e ) {
		$threw = true;
	}
	twd_sk_true( $threw, 'refused without a valid nonce' );
	twd_sk_eq( 0, count( $GLOBALS['twd_stub']['requests'] ), 'no request was made' );
} );

twd_sk_test( 'check link: clicking clears the cache, rechecks at once and returns to the Plugins screen with the result', function () {
	twd_sk_serve_manifest( array( 'version' => '8.8.8' ) );
	TWD_SK_Updater::fetch_manifest();
	twd_sk_serve_manifest( array( 'version' => '9.9.9' ) );
	$url = '';
	try {
		TWD_SK_Updater::handle_manual_check();
	} catch ( TWD_SK_Stub_Redirect $e ) {
		$url = $e->getMessage();
	}
	twd_sk_has( 'wp-admin/plugins.php', $url );
	twd_sk_has( 'twd_sk_checked=1', $url );
	twd_sk_has( 'twd_sk_latest=9.9.9', $url );
	twd_sk_eq( 1, $GLOBALS['twd_stub']['update_plugins_called'], 'core recheck forced' );
	twd_sk_true( in_array( 'update_plugins', $GLOBALS['twd_stub']['site_transients_deleted'], true ), 'core update list cleared' );
} );

twd_sk_test( 'check link: if GitHub cannot be reached the result carries no version', function () {
	$GLOBALS['twd_stub']['http'][ TWD_SK_Updater::JSON_URL ] = new WP_Error( 'x', 'down' );
	$url = '';
	try {
		TWD_SK_Updater::handle_manual_check();
	} catch ( TWD_SK_Stub_Redirect $e ) {
		$url = $e->getMessage();
	}
	twd_sk_has( 'twd_sk_latest=', $url );
	twd_sk_hasnt( 'twd_sk_latest=9', $url );
} );

function twd_sk_notice( $get ) {
	$_GET = $get;
	ob_start();
	TWD_SK_Updater::render_checked_notice();
	$out = ob_get_clean();
	$_GET = array();
	return $out;
}

twd_sk_test( 'notice: nothing is shown unless the check was just run', function () {
	twd_sk_eq( '', twd_sk_notice( array() ) );
} );

twd_sk_test( 'notice: a newer version, the latest version and an unreachable server each show a plain message', function () {
	twd_sk_has( 'A newer version of TWD Site Kit is available: 9.9.9 (you have ' . TWD_SK_VERSION . ')', twd_sk_notice( array( 'twd_sk_checked' => '1', 'twd_sk_latest' => '9.9.9' ) ) );
	twd_sk_has( 'on the latest version', twd_sk_notice( array( 'twd_sk_checked' => '1', 'twd_sk_latest' => TWD_SK_VERSION ) ) );
	set_transient( 'twd_sk_update_check_error', 'GitHub returned HTTP 404.', 60 );
	$out = twd_sk_notice( array( 'twd_sk_checked' => '1', 'twd_sk_latest' => '' ) );
	twd_sk_has( 'Could not reach the update server just now.', $out );
	twd_sk_has( 'GitHub returned HTTP 404.', $out );
} );

twd_sk_test( 'notice: a version in the address that is not three numbers is treated as no result, never printed raw', function () {
	$out = twd_sk_notice( array( 'twd_sk_checked' => '1', 'twd_sk_latest' => '<script>alert(1)</script>' ) );
	twd_sk_hasnt( '<script', $out );
	twd_sk_has( 'Could not reach', $out );
} );

twd_sk_test( 'notice: shown only on the Plugins screen', function () {
	$_GET = array( 'twd_sk_checked' => '1', 'twd_sk_latest' => '9.9.9' );
	$GLOBALS['twd_stub']['screen'] = 'dashboard';
	ob_start();
	TWD_SK_Updater::render_plugins_screen_notice();
	twd_sk_eq( '', ob_get_clean() );
	$GLOBALS['twd_stub']['screen'] = 'plugins';
	ob_start();
	TWD_SK_Updater::render_plugins_screen_notice();
	twd_sk_true( '' !== ob_get_clean() );
	$_GET = array();
} );

twd_sk_test( 'updater: the source has no PHP 8-only features and no em or en dashes', function () {
	$src = file_get_contents( ABSPATH . 'includes/class-twd-sk-updater.php' );
	twd_sk_true( false === strpos( $src, "\xE2\x80\x94" ) && false === strpos( $src, "\xE2\x80\x93" ) );
	foreach ( array( 'str_contains(', 'str_starts_with(', '?->' ) as $bad ) {
		twd_sk_hasnt( $bad, $src );
	}
} );
