<?php
/**
 * Run the whole suite:   php tests/run.php
 * Exit code is 0 only if every test passes.
 */

error_reporting( E_ALL );
ini_set( 'display_errors', '1' );

// Turn every PHP notice or warning into a test failure.
set_error_handler(
	function ( $no, $str, $file, $line ) {
		throw new ErrorException( $str, 0, $no, $file, $line );
	}
);

require __DIR__ . '/bootstrap.php';

foreach ( glob( __DIR__ . '/test-*.php' ) as $file ) {
	require $file;
}

$pass   = 0;
$fail   = 0;
$failed = array();

foreach ( $GLOBALS['twd_sk_tests'] as $t ) {
	list( $name, $fn ) = $t;
	twd_stub_reset();
	try {
		$fn();
		$pass++;
		echo 'PASS  ' . $name . "\n";
	} catch ( Throwable $e ) {
		$fail++;
		$failed[] = $name;
		echo 'FAIL  ' . $name . "\n";
		echo '      ' . $e->getMessage() . "\n";
		if ( ! ( $e instanceof TWD_SK_Test_Failure ) ) {
			echo '      at ' . basename( $e->getFile() ) . ':' . $e->getLine() . "\n";
		}
	}
}

echo "\n";
foreach ( $GLOBALS['twd_sk_notes'] as $note ) {
	echo 'NOTE  ' . $note . "\n";
}
echo $pass . ' passed, ' . $fail . ' failed, ' . ( $pass + $fail ) . " total\n";
if ( $fail ) {
	echo "Failed:\n  " . implode( "\n  ", $failed ) . "\n";
	exit( 1 );
}
exit( 0 );
