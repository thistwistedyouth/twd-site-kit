<?php
/**
 * Check that a release is consistent before it is pushed.
 *
 *   php bin/check-release.php                    strict: the JSON must match the zip
 *   php bin/check-release.php --allow-stale-json used right after building the zip,
 *                                                before the JSON has been updated
 *
 * Checks (see bin/lib-release.php): the plugin header Version, TWD_SK_VERSION and
 * readme.txt Stable tag agree; the zip has one top-level folder twd-site-kit with
 * the same version inside; the JSON version, changelog entry, download address
 * and sha256 match. Exit code 0 only when everything is fine.
 */

require_once __DIR__ . '/lib-release.php';

$mode   = in_array( '--allow-stale-json', $argv, true ) ? 'allow-stale' : 'strict';
$result = twd_sk_release_check( dirname( __DIR__ ), $mode );

foreach ( $result['notes'] as $note ) {
	echo 'NOTE  ' . $note . "\n";
}
if ( $result['problems'] ) {
	foreach ( $result['problems'] as $problem ) {
		echo 'FAIL  ' . $problem . "\n";
	}
	exit( 1 );
}
$v = twd_sk_release_source_versions( dirname( __DIR__ ) );
echo 'OK    release ' . $v['header'] . ' is consistent' . ( 'strict' === $mode ? ' (JSON, zip and sha256 all match).' : ' (JSON not yet checked against this version).' ) . "\n";
exit( 0 );
