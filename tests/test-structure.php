<?php
// The shape of the plugin: every class file is loaded by the plugin and by the tests, so nothing
// new can be forgotten in a release or go untested.

twd_sk_test( 'structure: every file in includes/ is loaded by twd-site-kit.php and by the test bootstrap', function () {
	$main = file_get_contents( ABSPATH . 'twd-site-kit.php' );
	$boot = file_get_contents( __DIR__ . '/bootstrap.php' );
	foreach ( glob( ABSPATH . 'includes/*.php' ) as $file ) {
		$name = basename( $file );
		twd_sk_has( "includes/" . $name . "'", $main, 'the plugin loads ' . $name );
		if ( 'class-twd-sk-cli.php' !== $name ) {
			// The CLI file needs WP_CLI, so its tests load it themselves.
			twd_sk_has( "includes/" . $name . "'", $boot, 'the tests load ' . $name );
		}
	}
} );

twd_sk_test( 'structure: the release script reads the file list from the plugin itself', function () {
	$sh = file_get_contents( ABSPATH . 'bin/build-zip.sh' );
	twd_sk_has( 'grep -o "includes/[a-z0-9-]*\\.php" "$ROOT/twd-site-kit.php"', $sh );
} );
