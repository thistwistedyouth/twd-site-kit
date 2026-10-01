<?php
/**
 * Write dist/twd-site-kit-update.json for the version in the plugin header.
 * Run after bin/build-zip.sh. It sets the version, the fixed download address,
 * the sha256 of the built zip and today's date, and adds the changelog entry.
 *
 *   php bin/update-json.php --changelog="<h4>0.2.2</h4><ul><li>What changed.</li></ul>"
 *
 * The changelog is needed whenever the version is new. Running it again for the
 * same version (for example after rebuilding the zip) only refreshes the sha256
 * and date. Then run: php bin/check-release.php
 */

require_once __DIR__ . '/lib-release.php';

$root = dirname( __DIR__ );
$args = array();
foreach ( array_slice( $argv, 1 ) as $a ) {
	if ( preg_match( '/^--([a-z-]+)=(.*)$/s', $a, $m ) ) {
		$args[ $m[1] ] = $m[2];
	}
}

$v       = twd_sk_release_source_versions( $root );
$version = $v['header'];
$zip     = $root . '/dist/twd-site-kit-latest.zip';
if ( ! $version || ! is_file( $zip ) ) {
	fwrite( STDERR, "Cannot find the plugin version or dist/twd-site-kit-latest.zip. Run bin/build-zip.sh first.\n" );
	exit( 1 );
}

$path = $root . '/dist/twd-site-kit-update.json';
$data = twd_sk_release_json( $root );
$data = is_array( $data ) ? $data : array();
$old  = isset( $data['version'] ) ? $data['version'] : '';

$changelog = isset( $data['changelog'] ) ? (string) $data['changelog'] : '';
if ( isset( $args['changelog'] ) ) {
	if ( false === strpos( $args['changelog'], '<h4>' . $version . '</h4>' ) ) {
		fwrite( STDERR, 'The changelog must start with <h4>' . $version . "</h4>.\n" );
		exit( 1 );
	}
	// Replace an existing entry for this version, then put the new one first.
	$changelog = preg_replace( '#<h4>' . preg_quote( $version, '#' ) . '</h4>\s*<ul>.*?</ul>#s', '', $changelog );
	$changelog = trim( $args['changelog'] ) . trim( $changelog );
} elseif ( $old !== $version ) {
	fwrite( STDERR, 'This is a new version (' . $version . '). Give --changelog="<h4>' . $version . "</h4><ul><li>...</li></ul>\".\n" );
	exit( 1 );
}

$out = array(
	'version'      => $version,
	'download_url' => TWD_SK_RELEASE_ZIP_URL,
	'sha256'       => hash_file( 'sha256', $zip ),
	'homepage'     => isset( $data['homepage'] ) ? $data['homepage'] : 'https://therapywebdesigns.co.uk/',
	'requires'     => isset( $data['requires'] ) ? $data['requires'] : '6.0',
	'tested'       => isset( $data['tested'] ) ? $data['tested'] : '6.6',
	'requires_php' => isset( $data['requires_php'] ) ? $data['requires_php'] : '7.4',
	'last_updated' => gmdate( 'Y-m-d' ),
	'description'  => isset( $data['description'] ) ? $data['description'] : 'Pages for therapist sites built from sanitised HTML and a fixed set of components, with version history, style packs and bundled fonts.',
	'changelog'    => $changelog,
);

file_put_contents( $path, json_encode( $out, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE ) . "\n" );
echo 'Wrote dist/twd-site-kit-update.json for ' . $version . ' (sha256 ' . substr( $out['sha256'], 0, 16 ) . "...)\n";

$result = twd_sk_release_check( $root, 'strict' );
foreach ( $result['problems'] as $problem ) {
	echo 'FAIL  ' . $problem . "\n";
}
exit( $result['problems'] ? 1 : 0 );
