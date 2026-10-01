<?php
// Safe mode: the switch that turns the newest modules off.

require_once __DIR__ . '/stub-wpcli.php';
require_once dirname( __DIR__ ) . '/includes/class-twd-sk-cli.php';

twd_sk_test( 'safe mode: off by default, on when the option says so, off again when it is cleared', function () {
	twd_sk_true( ! TWD_SK_Safe::on() );
	twd_sk_eq( 'off', TWD_SK_Safe::source() );
	foreach ( array( '1', 1, true, 'on' ) as $on ) {
		$GLOBALS['twd_stub']['options'][ TWD_SK_Safe::OPTION ] = $on;
		twd_sk_true( TWD_SK_Safe::on(), 'on for ' . json_encode( $on ) );
		twd_sk_eq( 'option', TWD_SK_Safe::source() );
	}
	foreach ( array( '0', 0, false, '', 'off', 'no' ) as $off ) {
		$GLOBALS['twd_stub']['options'][ TWD_SK_Safe::OPTION ] = $off;
		twd_sk_true( ! TWD_SK_Safe::on(), 'off for ' . json_encode( $off ) );
	}
} );

twd_sk_test( 'safe mode: set() writes the option', function () {
	TWD_SK_Safe::set( true );
	twd_sk_true( TWD_SK_Safe::on() );
	TWD_SK_Safe::set( false );
	twd_sk_true( ! TWD_SK_Safe::on() );
} );

twd_sk_test( 'safe mode: the wp-config constant switches it on, and wins over the option (checked in a separate process)', function () {
	$code = 'define("ABSPATH","/x/"); require ' . var_export( ABSPATH . 'includes/class-twd-sk-safe.php', true ) . ';'
		. 'function get_option($n,$d=false){return "0";} define("TWD_SK_SAFE_MODE", true);'
		. 'echo json_encode(array(TWD_SK_Safe::on(), TWD_SK_Safe::source()));';
	$out = shell_exec( 'php -r ' . escapeshellarg( $code ) . ' 2>&1' );
	twd_sk_eq( '[true,"constant"]', trim( (string) $out ) );
} );

twd_sk_test( 'safe mode: the notice shows only to administrators and only while it is on', function () {
	ob_start();
	TWD_SK_Safe::print_notice();
	twd_sk_eq( '', ob_get_clean(), 'off: nothing' );
	TWD_SK_Safe::set( true );
	$GLOBALS['twd_stub']['caps'] = false;
	ob_start();
	TWD_SK_Safe::print_notice();
	twd_sk_eq( '', ob_get_clean(), 'not an administrator: nothing' );
	$GLOBALS['twd_stub']['caps'] = true;
	ob_start();
	TWD_SK_Safe::print_notice();
	$out = ob_get_clean();
	twd_sk_has( 'TWD Site Kit safe mode is on', $out );
	twd_sk_has( 'wp twd-sk safe-mode off', $out );
} );

twd_sk_test( 'cli: safe-mode on, off and status', function () {
	WP_CLI::reset();
	( new TWD_SK_CLI() )->safe_mode( array(), array() );
	twd_sk_has( 'Safe mode is OFF', WP_CLI::all() );
	( new TWD_SK_CLI() )->safe_mode( array( 'on' ), array() );
	twd_sk_has( 'Safe mode is ON', WP_CLI::all() );
	twd_sk_true( TWD_SK_Safe::on() );
	( new TWD_SK_CLI() )->safe_mode( array( 'status' ), array() );
	( new TWD_SK_CLI() )->safe_mode( array( 'off' ), array() );
	twd_sk_true( ! TWD_SK_Safe::on() );
	twd_sk_has( 'Safe mode is OFF', WP_CLI::all() );
	twd_sk_has( 'Say on, off or status', twd_sk_cli_fails( function () {
		( new TWD_SK_CLI() )->safe_mode( array( 'maybe' ), array() );
	} ) );
} );

twd_sk_test( 'safe mode: the main file loads the new modules only when safe mode is off', function () {
	$src = file_get_contents( ABSPATH . 'twd-site-kit.php' );
	twd_sk_true( 1 === preg_match( '/if \( ! TWD_SK_Safe::on\(\) \) \{[^}]*TWD_SK_Modules::init\(\)/s', $src ), 'modules are started only outside safe mode' );
	twd_sk_has( 'TWD_SK_Safe::init()', $src );
} );
