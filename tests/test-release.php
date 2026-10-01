<?php
// Release consistency: versions, update JSON, checksum, zip layout.

require_once ABSPATH . 'bin/lib-release.php';

function twd_sk_release_have_zip() {
	if ( ! class_exists( 'ZipArchive' ) ) {
		twd_sk_note( 'Release tests that open the zip were skipped: the PHP zip extension is not available here.' );
		return false;
	}
	return true;
}

/** A throwaway copy of what the release scripts need, so tests can break things safely. */
function twd_sk_release_fixture() {
	$root = sys_get_temp_dir() . '/twdsk-rel-' . getmypid() . '-' . mt_rand( 1000, 9999 );
	mkdir( $root . '/dist', 0777, true );
	mkdir( $root . '/bin', 0777, true );
	foreach ( array( 'twd-site-kit.php', 'readme.txt', 'dist/twd-site-kit-latest.zip', 'dist/twd-site-kit-update.json' ) as $f ) {
		copy( ABSPATH . $f, $root . '/' . $f );
	}
	foreach ( array( 'lib-release.php', 'check-release.php', 'update-json.php' ) as $f ) {
		copy( ABSPATH . 'bin/' . $f, $root . '/bin/' . $f );
	}
	return $root;
}

function twd_sk_release_edit_json( $root, $changes ) {
	$path = $root . '/dist/twd-site-kit-update.json';
	$data = json_decode( file_get_contents( $path ), true );
	foreach ( $changes as $k => $v ) {
		if ( null === $v ) {
			unset( $data[ $k ] );
		} else {
			$data[ $k ] = $v;
		}
	}
	file_put_contents( $path, json_encode( $data ) );
}

function twd_sk_release_run( $root, $script, $args = array() ) {
	$cmd = escapeshellarg( PHP_BINARY ) . ' ' . escapeshellarg( $root . '/bin/' . $script );
	foreach ( $args as $a ) {
		$cmd .= ' ' . escapeshellarg( $a );
	}
	exec( $cmd . ' 2>&1', $out, $code );
	return array( $code, implode( "\n", $out ) );
}

function twd_sk_problems( $root, $mode = 'strict' ) {
	return twd_sk_release_check( $root, $mode );
}

// -- the real release in this repo ----------------------------------------------------------

twd_sk_test( 'release: plugin header, TWD_SK_VERSION, readme.txt Stable tag and the update JSON all give the same version', function () {
	$v    = twd_sk_release_source_versions( ABSPATH );
	$json = twd_sk_release_json( ABSPATH );
	twd_sk_true( null !== $v['header'] && 1 === preg_match( '/^\d+\.\d+\.\d+$/', $v['header'] ), 'a plain version: ' . json_encode( $v ) );
	twd_sk_eq( $v['header'], $v['const'], 'TWD_SK_VERSION' );
	twd_sk_eq( $v['header'], $v['readme'], 'readme.txt Stable tag' );
	twd_sk_eq( $v['header'], $json['version'], 'update JSON version' );
} );

twd_sk_test( 'release: the sha256 in the update JSON matches the built zip', function () {
	$json = twd_sk_release_json( ABSPATH );
	twd_sk_true( 1 === preg_match( '/^[a-f0-9]{64}$/', $json['sha256'] ), 'a 64 character hex sha256' );
	twd_sk_eq( hash_file( 'sha256', ABSPATH . 'dist/twd-site-kit-latest.zip' ), $json['sha256'], 'zip checksum' );
} );

twd_sk_test( 'release: the whole strict release check passes on this repository', function () {
	if ( ! twd_sk_release_have_zip() ) {
		return;
	}
	$r = twd_sk_release_check( ABSPATH, 'strict' );
	twd_sk_eq( array(), $r['problems'], 'problems: ' . implode( ' | ', $r['problems'] ) );
} );

twd_sk_test( 'release: the zip has one top-level folder twd-site-kit with the same version inside, plus the updater and readme', function () {
	if ( ! twd_sk_release_have_zip() ) {
		return;
	}
	$z = twd_sk_release_zip_info( ABSPATH . 'dist/twd-site-kit-latest.zip' );
	$v = twd_sk_release_source_versions( ABSPATH );
	twd_sk_eq( array( 'twd-site-kit' ), $z['folders'] );
	twd_sk_eq( $v['header'], $z['header'] );
	twd_sk_eq( $v['header'], $z['const'] );
	twd_sk_eq( $v['header'], $z['readme'] );
	foreach ( array( 'twd-site-kit/twd-site-kit.php', 'twd-site-kit/readme.txt', 'twd-site-kit/includes/class-twd-sk-updater.php', 'twd-site-kit/assets/twd-site-kit.css', 'twd-site-kit/packs/sage.json', 'twd-site-kit/starters/_gallery.html' ) as $file ) {
		twd_sk_true( in_array( $file, $z['files'], true ), 'in the zip: ' . $file );
	}
	foreach ( $z['files'] as $file ) {
		twd_sk_true( 0 === strpos( $file, 'twd-site-kit/' ), 'inside the one folder: ' . $file );
		twd_sk_true( 0 === preg_match( '#/(tests|bin|dist)/|CLAUDE|HISTORY|\.git#', $file ), 'must not ship: ' . $file );
	}
} );

twd_sk_test( 'release: the shipped update JSON is accepted by the updater itself, with the fixed package address and checksum', function () {
	$body = file_get_contents( ABSPATH . 'dist/twd-site-kit-update.json' );
	list( $m, $err ) = TWD_SK_Updater::parse_manifest( $body );
	twd_sk_eq( '', $err );
	twd_sk_eq( TWD_SK_Updater::ZIP_URL, $m['download_url'] );
	twd_sk_eq( json_decode( $body, true )['sha256'], $m['sha256'] );
	twd_sk_eq( TWD_SK_Updater::ZIP_URL, TWD_SK_RELEASE_ZIP_URL, 'the release scripts and the updater agree on the address' );
} );

twd_sk_test( 'release: the JSON changelog has an entry for the current version and no dashes', function () {
	$json = twd_sk_release_json( ABSPATH );
	$v    = twd_sk_release_source_versions( ABSPATH );
	twd_sk_has( '<h4>' . $v['header'] . '</h4>', $json['changelog'] );
	$raw = file_get_contents( ABSPATH . 'dist/twd-site-kit-update.json' );
	twd_sk_true( false === strpos( $raw, "\xE2\x80\x94" ) && false === strpos( $raw, "\xE2\x80\x93" ) );
} );

twd_sk_test( 'release: readme.txt has the standard headers and they agree with the plugin header', function () {
	$readme = file_get_contents( ABSPATH . 'readme.txt' );
	$main   = file_get_contents( ABSPATH . 'twd-site-kit.php' );
	twd_sk_true( 0 === strpos( $readme, '=== TWD Site Kit ===' ) );
	foreach ( array( 'Requires at least', 'Requires PHP' ) as $key ) {
		preg_match( '/^' . $key . ':\s*(\S+)/m', $readme, $a );
		preg_match( '/' . $key . ':\s*(\S+)/', $main, $b );
		twd_sk_eq( $b[1], $a[1], $key . ' matches the plugin header' );
	}
	foreach ( array( 'Tested up to:', 'Stable tag:', 'License:', '== Description ==', '== Changelog ==' ) as $needle ) {
		twd_sk_has( $needle, $readme );
	}
	preg_match( '/^Stable tag:\s*(\S+)/m', $readme, $tag );
	twd_sk_has( '= ' . $tag[1] . ' =', $readme, 'the readme changelog has an entry for the stable tag' );
} );

twd_sk_test( 'release: bin/build-zip.sh ships readme.txt and runs the release check', function () {
	$sh = file_get_contents( ABSPATH . 'bin/build-zip.sh' );
	twd_sk_has( 'readme.txt', $sh );
	twd_sk_has( 'bin/check-release.php', $sh );
	twd_sk_has( 'sha256sum', $sh );
	twd_sk_has( 'twd-site-kit/readme.txt', file_get_contents( ABSPATH . 'tests/test-release.php' ) );
} );

// -- the checker catches every kind of mismatch (on a throwaway copy) -------------------------------

twd_sk_test( 'release check: a copy of the real release passes', function () {
	if ( ! twd_sk_release_have_zip() ) {
		return;
	}
	twd_sk_eq( array(), twd_sk_problems( twd_sk_release_fixture() )['problems'] );
} );

twd_sk_test( 'release check: a plugin header that disagrees with TWD_SK_VERSION is caught', function () {
	$root = twd_sk_release_fixture();
	file_put_contents( $root . '/twd-site-kit.php', str_replace( "define( 'TWD_SK_VERSION', '0.2.3' )", "define( 'TWD_SK_VERSION', '0.2.9' )", file_get_contents( $root . '/twd-site-kit.php' ) ) );
	$p = twd_sk_problems( $root, 'skip' )['problems'];
	twd_sk_true( 1 <= count( $p ) && false !== strpos( implode( ' ', $p ), 'Versions disagree' ), implode( ' | ', $p ) );
} );

twd_sk_test( 'release check: a readme.txt Stable tag that disagrees is caught', function () {
	$root = twd_sk_release_fixture();
	file_put_contents( $root . '/readme.txt', str_replace( 'Stable tag: 0.2.3', 'Stable tag: 0.2.0', file_get_contents( $root . '/readme.txt' ) ) );
	twd_sk_has( 'Versions disagree', implode( ' ', twd_sk_problems( $root, 'skip' )['problems'] ) );
} );

twd_sk_test( 'release check: a JSON sha256 that does not match the zip is caught', function () {
	if ( ! twd_sk_release_have_zip() ) {
		return;
	}
	$root = twd_sk_release_fixture();
	twd_sk_release_edit_json( $root, array( 'sha256' => str_repeat( 'a', 64 ) ) );
	twd_sk_has( 'does not match the built zip', implode( ' ', twd_sk_problems( $root )['problems'] ) );
} );

twd_sk_test( 'release check: a missing or malformed JSON sha256 is caught', function () {
	if ( ! twd_sk_release_have_zip() ) {
		return;
	}
	$root = twd_sk_release_fixture();
	twd_sk_release_edit_json( $root, array( 'sha256' => null ) );
	twd_sk_has( 'sha256 is missing', implode( ' ', twd_sk_problems( $root )['problems'] ) );
	twd_sk_release_edit_json( $root, array( 'sha256' => 'abc' ) );
	twd_sk_has( 'sha256 is missing or not 64', implode( ' ', twd_sk_problems( $root )['problems'] ) );
} );

twd_sk_test( 'release check: a stale JSON version is a failure when strict, and only a note right after building', function () {
	if ( ! twd_sk_release_have_zip() ) {
		return;
	}
	$root = twd_sk_release_fixture();
	twd_sk_release_edit_json( $root, array( 'version' => '0.1.0' ) );
	twd_sk_has( 'update JSON version is 0.1.0', implode( ' ', twd_sk_problems( $root, 'strict' )['problems'] ) );
	$r = twd_sk_problems( $root, 'allow-stale' );
	twd_sk_eq( array(), $r['problems'] );
	twd_sk_has( 'still on 0.1.0', implode( ' ', $r['notes'] ) );
	twd_sk_release_edit_json( $root, array( 'version' => '9.0.0' ) );
	twd_sk_true( 0 < count( twd_sk_problems( $root, 'allow-stale' )['problems'] ), 'a JSON version AHEAD of the plugin is always a failure' );
} );

twd_sk_test( 'release check: a JSON that names another download address is caught', function () {
	$root = twd_sk_release_fixture();
	twd_sk_release_edit_json( $root, array( 'download_url' => 'https://evil.example/x.zip' ) );
	twd_sk_has( 'not the fixed zip address', implode( ' ', twd_sk_problems( $root )['problems'] ) );
} );

twd_sk_test( 'release check: a JSON changelog with no entry for this version is caught', function () {
	$root = twd_sk_release_fixture();
	twd_sk_release_edit_json( $root, array( 'changelog' => '<h4>0.0.1</h4><ul><li>old</li></ul>' ) );
	twd_sk_has( 'no entry for', implode( ' ', twd_sk_problems( $root )['problems'] ) );
} );

twd_sk_test( 'release check: a missing JSON or a missing zip is caught', function () {
	$root = twd_sk_release_fixture();
	unlink( $root . '/dist/twd-site-kit-update.json' );
	twd_sk_has( 'missing or is not valid JSON', implode( ' ', twd_sk_problems( $root )['problems'] ) );
	unlink( $root . '/dist/twd-site-kit-latest.zip' );
	twd_sk_has( 'does not exist', implode( ' ', twd_sk_problems( $root, 'skip' )['problems'] ) );
} );

twd_sk_test( 'release check: a zip built for another version is caught and the fix is named', function () {
	if ( ! twd_sk_release_have_zip() ) {
		return;
	}
	$root = twd_sk_release_fixture();
	file_put_contents( $root . '/twd-site-kit.php', str_replace( '0.2.3', '0.2.4', file_get_contents( $root . '/twd-site-kit.php' ) ) );
	file_put_contents( $root . '/readme.txt', str_replace( '0.2.3', '0.2.4', file_get_contents( $root . '/readme.txt' ) ) );
	$p = implode( ' ', twd_sk_problems( $root, 'skip' )['problems'] );
	twd_sk_has( 'The zip holds', $p );
	twd_sk_has( 'Rebuild the zip', $p );
} );

function twd_sk_make_zip( $path, $entries ) {
	$zip = new ZipArchive();
	$zip->open( $path, ZipArchive::CREATE | ZipArchive::OVERWRITE );
	foreach ( $entries as $name => $content ) {
		$zip->addFromString( $name, $content );
	}
	$zip->close();
}

twd_sk_test( 'release check: a zip whose top-level folder is not exactly twd-site-kit is caught', function () {
	if ( ! twd_sk_release_have_zip() ) {
		return;
	}
	$root = twd_sk_release_fixture();
	$main = file_get_contents( ABSPATH . 'twd-site-kit.php' );
	twd_sk_make_zip( $root . '/dist/twd-site-kit-latest.zip', array( 'twd-site-kit-0.2.3/twd-site-kit.php' => $main ) );
	twd_sk_has( 'exactly one top-level folder', implode( ' ', twd_sk_problems( $root, 'skip' )['problems'] ) );
	twd_sk_make_zip( $root . '/dist/twd-site-kit-latest.zip', array( 'twd-site-kit.php' => $main, 'readme.txt' => 'x' ) );
	twd_sk_true( 0 < count( twd_sk_problems( $root, 'skip' )['problems'] ), 'a zip of the folder contents, with no folder at all' );
	twd_sk_make_zip( $root . '/dist/twd-site-kit-latest.zip', array( 'twd-site-kit/twd-site-kit.php' => $main, 'other-folder/x.php' => 'x' ) );
	twd_sk_has( 'exactly one top-level folder', implode( ' ', twd_sk_problems( $root, 'skip' )['problems'] ) );
} );

twd_sk_test( 'release check: a zip that contains tests, docs or git files is caught', function () {
	if ( ! twd_sk_release_have_zip() ) {
		return;
	}
	$root = twd_sk_release_fixture();
	$main = file_get_contents( ABSPATH . 'twd-site-kit.php' );
	foreach ( array( 'twd-site-kit/tests/x.php', 'twd-site-kit/CLAUDE.md', 'twd-site-kit/HISTORY.md', 'twd-site-kit/.gitignore', 'twd-site-kit/bin/x.php', 'twd-site-kit/dist/x.zip', 'twd-site-kit/readme.md' ) as $bad ) {
		twd_sk_make_zip( $root . '/dist/twd-site-kit-latest.zip', array( 'twd-site-kit/twd-site-kit.php' => $main, $bad => 'x' ) );
		twd_sk_has( 'must not ship', implode( ' ', twd_sk_problems( $root, 'skip' )['problems'] ), $bad );
	}
} );

// -- the release scripts themselves ------------------------------------------------------------------

twd_sk_test( 'scripts: check-release.php exits 0 on a good release and 1 on a bad one', function () {
	if ( ! twd_sk_release_have_zip() ) {
		return;
	}
	$root = twd_sk_release_fixture();
	list( $code, $out ) = twd_sk_release_run( $root, 'check-release.php' );
	twd_sk_eq( 0, $code, $out );
	twd_sk_has( 'OK', $out );
	twd_sk_release_edit_json( $root, array( 'sha256' => str_repeat( 'b', 64 ) ) );
	list( $code, $out ) = twd_sk_release_run( $root, 'check-release.php' );
	twd_sk_eq( 1, $code );
	twd_sk_has( 'FAIL', $out );
} );

twd_sk_test( 'scripts: update-json.php refuses a new version with no changelog, then writes JSON that passes the strict check', function () {
	if ( ! twd_sk_release_have_zip() ) {
		return;
	}
	$root = twd_sk_release_fixture();
	// Pretend the version moved on: edit the JSON back one version so this one is "new".
	twd_sk_release_edit_json( $root, array( 'version' => '0.2.0', 'sha256' => str_repeat( 'c', 64 ) ) );
	list( $code, $out ) = twd_sk_release_run( $root, 'update-json.php' );
	twd_sk_eq( 1, $code, $out );
	twd_sk_has( '--changelog', $out );

	list( $code, $out ) = twd_sk_release_run( $root, 'update-json.php', array( '--changelog=<h4>0.2.3</h4><ul><li>A test entry.</li></ul>' ) );
	twd_sk_eq( 0, $code, $out );
	$json = json_decode( file_get_contents( $root . '/dist/twd-site-kit-update.json' ), true );
	twd_sk_eq( '0.2.3', $json['version'] );
	twd_sk_eq( hash_file( 'sha256', $root . '/dist/twd-site-kit-latest.zip' ), $json['sha256'] );
	twd_sk_eq( TWD_SK_RELEASE_ZIP_URL, $json['download_url'] );
	twd_sk_eq( gmdate( 'Y-m-d' ), $json['last_updated'] );
	twd_sk_true( 0 === strpos( $json['changelog'], '<h4>0.2.3</h4><ul><li>A test entry.</li></ul>' ), 'new entry first' );
	twd_sk_has( '<h4>0.2.0</h4>', $json['changelog'], 'older entries kept' );
	twd_sk_eq( 1, substr_count( $json['changelog'], '<h4>0.2.3</h4>' ), 'one entry per version' );
} );

twd_sk_test( 'scripts: running update-json.php again for the same version only refreshes the checksum and keeps the changelog', function () {
	if ( ! twd_sk_release_have_zip() ) {
		return;
	}
	$root = twd_sk_release_fixture();
	$before = json_decode( file_get_contents( $root . '/dist/twd-site-kit-update.json' ), true );
	twd_sk_release_edit_json( $root, array( 'sha256' => str_repeat( 'd', 64 ) ) );
	list( $code ) = twd_sk_release_run( $root, 'update-json.php' );
	twd_sk_eq( 0, $code );
	$after = json_decode( file_get_contents( $root . '/dist/twd-site-kit-update.json' ), true );
	twd_sk_eq( $before['sha256'], $after['sha256'] );
	twd_sk_eq( $before['changelog'], $after['changelog'] );
} );

twd_sk_test( 'scripts: update-json.php rejects a changelog that does not start with the current version heading', function () {
	if ( ! twd_sk_release_have_zip() ) {
		return;
	}
	$root = twd_sk_release_fixture();
	list( $code, $out ) = twd_sk_release_run( $root, 'update-json.php', array( '--changelog=<h4>9.9.9</h4><ul><li>wrong version</li></ul>' ) );
	twd_sk_eq( 1, $code );
	twd_sk_has( 'must start with', $out );
} );

// -- the real zip through the real updater code (WordPress itself stubbed) -------------------------------

twd_sk_test( 'end to end: the real built zip and real JSON pass the updater checksum check and unpack to the right folder', function () {
	if ( ! twd_sk_release_have_zip() ) {
		return;
	}
	$zip_bytes = file_get_contents( ABSPATH . 'dist/twd-site-kit-latest.zip' );
	$json      = json_decode( file_get_contents( ABSPATH . 'dist/twd-site-kit-update.json' ), true );
	// The installed version in tests is 1.0.0, so present the shipped file as a newer release
	// (everything else, including the real checksum, is untouched).
	$served = $json;
	$served['version'] = '9.9.9';
	twd_sk_serve( TWD_SK_Updater::JSON_URL, 200, json_encode( $served ) );
	twd_sk_serve( TWD_SK_Updater::ZIP_URL, 200, $zip_bytes );

	$path = TWD_SK_Updater::verify_download( false, TWD_SK_Updater::ZIP_URL );
	twd_sk_true( is_string( $path ) && is_file( $path ), 'the real zip is accepted' );
	twd_sk_eq( $json['sha256'], hash_file( 'sha256', $path ) );

	// Unpack it the way WordPress does and run the folder check on the result.
	$dest = sys_get_temp_dir() . '/twdsk-unpack-' . getmypid();
	$zip  = new ZipArchive();
	twd_sk_true( true === $zip->open( $path ) );
	$zip->extractTo( $dest );
	$zip->close();
	unlink( $path );
	$source = $dest . '/twd-site-kit/';
	twd_sk_eq( $source, TWD_SK_Updater::check_source( $source, $dest, null, array( 'plugin' => 'twd-site-kit/twd-site-kit.php' ) ) );
	twd_sk_true( is_file( $source . 'twd-site-kit.php' ) && is_file( $source . 'includes/class-twd-sk-updater.php' ) && is_file( $source . 'assets/fonts/jost-latin-wght-normal.woff2' ) );
} );

twd_sk_test( 'end to end: the same real zip with a single changed byte is refused by the updater', function () {
	if ( ! twd_sk_release_have_zip() ) {
		return;
	}
	$zip_bytes = file_get_contents( ABSPATH . 'dist/twd-site-kit-latest.zip' );
	$json      = json_decode( file_get_contents( ABSPATH . 'dist/twd-site-kit-update.json' ), true );
	$json['version'] = '9.9.9';
	twd_sk_serve( TWD_SK_Updater::JSON_URL, 200, json_encode( $json ) );
	$zip_bytes[ 100 ] = ( 'A' === $zip_bytes[ 100 ] ) ? 'B' : 'A';
	twd_sk_serve( TWD_SK_Updater::ZIP_URL, 200, $zip_bytes );
	twd_sk_is_error( 'twd_sk_update_checksum', TWD_SK_Updater::verify_download( false, TWD_SK_Updater::ZIP_URL ) );
} );
