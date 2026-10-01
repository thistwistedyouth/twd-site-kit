<?php
// WP-CLI command tests, using a stand-in WP_CLI. The commands only use the store API.

require_once __DIR__ . '/stub-wpcli.php';
require_once dirname( __DIR__ ) . '/includes/class-twd-sk-cli.php';

function twd_sk_tmp_file( $contents ) {
	$path = tempnam( sys_get_temp_dir(), 'twdsk' );
	file_put_contents( $path, $contents );
	return $path;
}
function twd_sk_cli_fails( $fn ) {
	try {
		$fn();
	} catch ( WP_CLI_Stub_Error $e ) {
		return $e->getMessage();
	}
	twd_sk_fail( 'expected the command to fail' );
}

twd_sk_test( 'cli: save reads a file, stores it through the store and reports the version', function () {
	WP_CLI::reset();
	twd_stub_add_post( 12, 'page', '[twd_page]' );
	$file = twd_sk_tmp_file( '<p>From a file</p>' );
	( new TWD_SK_CLI() )->save( array( '12', $file ), array() );
	twd_sk_eq( '<p>From a file</p>', TWD_SK_Store::get_current( 12 ) );
	twd_sk_has( 'Saved page 12 as version 1', WP_CLI::all() );
	unlink( $file );
} );

twd_sk_test( 'cli: save passes --note through', function () {
	WP_CLI::reset();
	twd_stub_add_post( 12, 'page', '[twd_page]' );
	$file = twd_sk_tmp_file( '<p>x</p>' );
	( new TWD_SK_CLI() )->save( array( '12', $file ), array( 'note' => 'first go' ) );
	twd_sk_eq( 'first go', TWD_SK_Store::get_version( 12, 1 )['note'] );
	unlink( $file );
} );

twd_sk_test( 'cli: save --base refuses when the page has moved on', function () {
	WP_CLI::reset();
	twd_stub_add_post( 12, 'page', '[twd_page]' );
	TWD_SK_Store::save( 12, '<p>one</p>' );
	TWD_SK_Store::save( 12, '<p>two</p>' );
	$file = twd_sk_tmp_file( '<p>three</p>' );
	$msg  = twd_sk_cli_fails( function () use ( $file ) {
		( new TWD_SK_CLI() )->save( array( '12', $file ), array( 'base' => '1' ) );
	} );
	twd_sk_has( 'moved on', $msg );
	twd_sk_eq( '<p>two</p>', TWD_SK_Store::get_current( 12 ) );
	unlink( $file );
} );

twd_sk_test( 'cli: save lists what the cleaner removed', function () {
	WP_CLI::reset();
	twd_stub_add_post( 12, 'page', '[twd_page]' );
	$file = twd_sk_tmp_file( '<p onclick="x()" class="nope">Hi</p><script>bad()</script>' );
	( new TWD_SK_CLI() )->save( array( '12', $file ), array() );
	$all = WP_CLI::all();
	twd_sk_has( 'warning: The cleaner removed or changed 3 item(s)', $all );
	twd_sk_has( 'classes (1): nope', $all );
	twd_sk_has( 'tags (1): script', $all );
	unlink( $file );
} );

twd_sk_test( 'cli: save says so when the page has no [twd_page] shortcode yet', function () {
	WP_CLI::reset();
	twd_stub_add_post( 12, 'page', 'nothing here' );
	$file = twd_sk_tmp_file( '<p>x</p>' );
	( new TWD_SK_CLI() )->save( array( '12', $file ), array() );
	twd_sk_has( 'does not seem to contain the [twd_page] shortcode', WP_CLI::all() );
	unlink( $file );
} );

twd_sk_test( 'cli: save does not warn about the shortcode when it is there', function () {
	WP_CLI::reset();
	twd_stub_add_post( 12, 'page', 'Intro [twd_page]' );
	$file = twd_sk_tmp_file( '<p>x</p>' );
	( new TWD_SK_CLI() )->save( array( '12', $file ), array() );
	twd_sk_hasnt( 'does not seem to contain', WP_CLI::all() );
	unlink( $file );
} );

twd_sk_test( 'cli: save with an unreadable file, a missing file argument or a bad page id fails cleanly', function () {
	twd_stub_add_post( 12, 'page' );
	$cli = new TWD_SK_CLI();
	twd_sk_has( 'Cannot read', twd_sk_cli_fails( function () use ( $cli ) {
		$cli->save( array( '12', '/no/such/file.html' ), array() );
	} ) );
	twd_sk_has( 'path to an HTML file', twd_sk_cli_fails( function () use ( $cli ) {
		$cli->save( array( '12' ), array() );
	} ) );
	twd_sk_has( 'must be a number', twd_sk_cli_fails( function () use ( $cli ) {
		$cli->save( array( 'abc', 'x.html' ), array() );
	} ) );
	twd_sk_has( 'must be a number', twd_sk_cli_fails( function () use ( $cli ) {
		$cli->get( array( '-3' ), array() );
	} ) );
} );

twd_sk_test( 'cli: save on a page that does not exist reports the store error', function () {
	$file = twd_sk_tmp_file( '<p>x</p>' );
	$msg  = twd_sk_cli_fails( function () use ( $file ) {
		( new TWD_SK_CLI() )->save( array( '777', $file ), array() );
	} );
	twd_sk_has( 'was not found', $msg );
	unlink( $file );
} );

twd_sk_test( 'cli: get prints the current html, and fails when nothing is stored', function () {
	WP_CLI::reset();
	twd_stub_add_post( 12, 'page' );
	$cli = new TWD_SK_CLI();
	twd_sk_has( 'has no stored content', twd_sk_cli_fails( function () use ( $cli ) {
		$cli->get( array( '12' ), array() );
	} ) );
	TWD_SK_Store::save( 12, '<p>Stored</p>' );
	$cli->get( array( '12' ), array() );
	twd_sk_has( 'line: <p>Stored</p>', WP_CLI::all() );
} );

twd_sk_test( 'cli: versions lists them newest first with the current one marked', function () {
	WP_CLI::reset();
	twd_stub_add_post( 12, 'page' );
	TWD_SK_Store::save( 12, '<p>one</p>' );
	TWD_SK_Store::save( 12, '<p>two</p>', array( 'note' => 'second' ) );
	( new TWD_SK_CLI() )->versions( array( '12' ), array() );
	$line = WP_CLI::$log[0];
	twd_sk_has( 'table(table):', $line );
	$data = json_decode( substr( $line, strlen( 'table(table): ' ) ), true );
	twd_sk_eq( array( 'version', 'current', 'created', 'user', 'kind', 'bytes', 'note' ), $data['fields'] );
	twd_sk_eq( 2, $data['rows'][0]['version'] );
	twd_sk_eq( 'yes', $data['rows'][0]['current'] );
	twd_sk_eq( '', $data['rows'][1]['current'] );
	twd_sk_eq( 'second', $data['rows'][0]['note'] );
} );

twd_sk_test( 'cli: versions honours --format and fails when there are none', function () {
	WP_CLI::reset();
	twd_stub_add_post( 12, 'page' );
	$cli = new TWD_SK_CLI();
	twd_sk_has( 'no saved versions', twd_sk_cli_fails( function () use ( $cli ) {
		$cli->versions( array( '12' ), array() );
	} ) );
	TWD_SK_Store::save( 12, '<p>one</p>' );
	$cli->versions( array( '12' ), array( 'format' => 'json' ) );
	twd_sk_has( 'table(json):', WP_CLI::all() );
} );

twd_sk_test( 'cli: undo steps back through the store and reports the new version', function () {
	WP_CLI::reset();
	twd_stub_add_post( 12, 'page' );
	TWD_SK_Store::save( 12, '<p>one</p>' );
	TWD_SK_Store::save( 12, '<p>two</p>' );
	( new TWD_SK_CLI() )->undo( array( '12' ), array() );
	twd_sk_eq( '<p>one</p>', TWD_SK_Store::get_current( 12 ) );
	twd_sk_has( 'Undone page 12 as version 3', WP_CLI::all() );
} );

twd_sk_test( 'cli: undo with nothing to undo fails cleanly', function () {
	twd_stub_add_post( 12, 'page' );
	$cli = new TWD_SK_CLI();
	twd_sk_has( 'no saved versions', twd_sk_cli_fails( function () use ( $cli ) {
		$cli->undo( array( '12' ), array() );
	} ) );
} );

twd_sk_test( 'cli: exposes exactly these commands: save, get, versions, undo, pack, prompt, check, safe_mode', function () {
	$methods = array();
	foreach ( ( new ReflectionClass( 'TWD_SK_CLI' ) )->getMethods( ReflectionMethod::IS_PUBLIC ) as $m ) {
		$methods[] = $m->getName();
	}
	sort( $methods );
	twd_sk_eq( array( 'check', 'get', 'pack', 'prompt', 'safe_mode', 'save', 'undo', 'versions' ), $methods );
} );

twd_sk_test( 'cli: pack with no name lists the packs and marks the active one', function () {
	WP_CLI::reset();
	( new TWD_SK_CLI() )->pack( array(), array() );
	$line = WP_CLI::$log[0];
	twd_sk_has( 'table(table):', $line );
	$data = json_decode( substr( $line, strlen( 'table(table): ' ) ), true );
	twd_sk_eq( array( 'pack', 'active', 'name', 'description' ), $data['fields'] );
	$by = array();
	foreach ( $data['rows'] as $row ) {
		$by[ $row['pack'] ] = $row['active'];
	}
	twd_sk_eq( array( 'grove' => '', 'sage' => 'yes' ), $by );
} );

twd_sk_test( 'cli: pack with a name switches the active pack and says what to do about caches', function () {
	WP_CLI::reset();
	( new TWD_SK_CLI() )->pack( array( 'grove' ), array() );
	twd_sk_eq( 'grove', TWD_SK_Packs::active_slug() );
	twd_sk_has( 'Style pack is now "grove"', WP_CLI::all() );
	twd_sk_has( 'clear it', WP_CLI::all() );
	( new TWD_SK_CLI() )->pack( array( 'sage' ), array() );
	twd_sk_eq( 'sage', TWD_SK_Packs::active_slug() );
} );

twd_sk_test( 'cli: pack with an unknown name fails, lists the choices and changes nothing', function () {
	$cli = new TWD_SK_CLI();
	$msg = twd_sk_cli_fails( function () use ( $cli ) {
		$cli->pack( array( 'nonsense' ), array() );
	} );
	twd_sk_has( 'no style pack called "nonsense"', $msg );
	twd_sk_has( 'grove, sage', $msg );
	twd_sk_eq( 'sage', TWD_SK_Packs::active_slug() );
} );

twd_sk_test( 'cli: commands only use the store API, never the meta keys directly', function () {
	$src = file_get_contents( dirname( __DIR__ ) . '/includes/class-twd-sk-cli.php' );
	twd_sk_hasnt( '_twd_sk_html', $src );
	twd_sk_hasnt( '_twd_sk_versions', $src );
	twd_sk_hasnt( 'update_post_meta', $src );
	twd_sk_hasnt( 'TWD_SK_Sanitizer', $src, 'cleaning happens inside the store' );
} );

twd_sk_test( 'cli: the main plugin file registers the commands only when WP_CLI is defined', function () {
	$src = file_get_contents( dirname( __DIR__ ) . '/twd-site-kit.php' );
	twd_sk_true( 1 === preg_match( "/if \( defined\( 'WP_CLI' \) && WP_CLI \) \{[^}]*class-twd-sk-cli\.php[^}]*add_command\( 'twd-sk'/s", $src ), 'guarded require and add_command' );
} );

twd_sk_test( 'plugin: header declares PHP 7.4, WordPress 6.0 and the public update URI', function () {
	$src = file_get_contents( dirname( __DIR__ ) . '/twd-site-kit.php' );
	twd_sk_has( 'Requires PHP: 7.4', $src );
	twd_sk_has( 'Requires at least: 6.0', $src );
	twd_sk_has( 'Update URI: https://github.com/thistwistedyouth/twd-site-kit', $src );
} );

twd_sk_test( 'plugin: no PHP 8-only syntax or functions in the plugin code', function () {
	foreach ( array_merge( array( dirname( __DIR__ ) . '/twd-site-kit.php' ), glob( dirname( __DIR__ ) . '/includes/*.php' ) ) as $file ) {
		$src = file_get_contents( $file );
		foreach ( array( 'str_contains(', 'str_starts_with(', 'str_ends_with(', '?->', 'mixed ', 'array_is_list(', 'enum ', 'readonly ' ) as $bad ) {
			twd_sk_true( false === strpos( $src, $bad ), basename( $file ) . ' uses ' . $bad );
		}
		twd_sk_true( 0 === preg_match( '/(?<![A-Za-z_])match\s*\(/', $src ), basename( $file ) . ' uses a match expression' );
	}
} );

twd_sk_test( 'cli: check lists leftover example text and changes nothing', function () {
	WP_CLI::reset();
	twd_stub_add_post( 12, 'page', '[twd_page]' );
	TWD_SK_Store::save( 12, '<p>Heading here</p><a href="mailto:you@example.com">mail</a><p>[PLACEHOLDER: fee]</p>' );
	$before = TWD_SK_Store::get_current( 12 );
	( new TWD_SK_CLI() )->check( array( '12' ), array() );
	$all = WP_CLI::all();
	twd_sk_has( 'still has example text', $all );
	twd_sk_has( 'Heading here (1)', $all );
	twd_sk_has( 'example.com (1)', $all );
	twd_sk_has( '[PLACEHOLDER (1)', $all );
	twd_sk_eq( $before, TWD_SK_Store::get_current( 12 ) );
	twd_sk_eq( 1, count( TWD_SK_Store::list_versions( 12 ) ) );
} );

twd_sk_test( 'cli: check says so when the page is clean, and needs content and a numeric id', function () {
	WP_CLI::reset();
	twd_stub_add_post( 12, 'page', '[twd_page]' );
	TWD_SK_Store::save( 12, '<p>Real words only.</p>' );
	( new TWD_SK_CLI() )->check( array( '12' ), array() );
	twd_sk_has( 'no leftover example text', WP_CLI::all() );
	twd_stub_add_post( 13, 'page', '[twd_page]' );
	twd_sk_has( 'no stored content', twd_sk_cli_fails( function () {
		( new TWD_SK_CLI() )->check( array( '13' ), array() );
	} ) );
	twd_sk_has( 'page ID must be a number', twd_sk_cli_fails( function () {
		( new TWD_SK_CLI() )->check( array( 'x' ), array() );
	} ) );
} );

twd_sk_test( 'cli: save warns about leftover example text without removing it', function () {
	WP_CLI::reset();
	twd_stub_add_post( 12, 'page', '[twd_page]' );
	$file = twd_sk_tmp_file( '<p>Paragraph text here</p>' );
	( new TWD_SK_CLI() )->save( array( '12', $file ), array() );
	twd_sk_has( 'Example text is still on the page: Paragraph text here (1)', WP_CLI::all() );
	twd_sk_has( 'wp twd-sk check 12', WP_CLI::all() );
	twd_sk_eq( '<p>Paragraph text here</p>', TWD_SK_Store::get_current( 12 ) );
	unlink( $file );
} );

twd_sk_test( 'cli: check lists the newer markers too (picture names, sample service links, sample link wording)', function () {
	WP_CLI::reset();
	twd_stub_add_post( 12, 'page', '[twd_page]' );
	TWD_SK_Store::save( 12, '<img src="/wp-content/uploads/your-badge.png" alt="x"><a href="/service-1">x</a><a href="/contact">Contact me about a first session</a>' );
	( new TWD_SK_CLI() )->check( array( '12' ), array() );
	$all = WP_CLI::all();
	twd_sk_has( 'your-badge (1)', $all );
	twd_sk_has( '/service-N (1)', $all );
	twd_sk_has( 'Contact me about a first session (1)', $all );
} );

twd_sk_test( 'cli: check shows the two levels, must fix first and check marked as never blocking', function () {
	WP_CLI::reset();
	twd_stub_add_post( 12, 'page', '[twd_page]' );
	TWD_SK_Store::save( 12, '<p>Heading here</p><a href="/c">Contact me about a first session</a>' );
	( new TWD_SK_CLI() )->check( array( '12' ), array() );
	$all = WP_CLI::all();
	twd_sk_has( 'still has example text', $all );
	twd_sk_has( 'Must fix before publishing:', $all );
	twd_sk_has( 'Heading here (1)', $all );
	twd_sk_has( 'Check (never blocks publishing):', $all );
	twd_sk_has( 'Contact me about a first session (1)', $all );
	twd_sk_true( strpos( $all, 'Must fix before publishing:' ) < strpos( $all, 'Check (never blocks publishing):' ), 'must fix listed first' );
	twd_sk_true( strpos( $all, 'Heading here (1)' ) < strpos( $all, 'Check (never blocks' ), 'must item under its own heading' );
} );

twd_sk_test( 'cli: check with only check-level wording warns gently and says it never blocks', function () {
	WP_CLI::reset();
	twd_stub_add_post( 12, 'page', '[twd_page]' );
	TWD_SK_Store::save( 12, '<a href="/c">Find out about my services</a>' );
	( new TWD_SK_CLI() )->check( array( '12' ), array() );
	$all = WP_CLI::all();
	twd_sk_has( 'has example wording to check. It never blocks publishing', $all );
	twd_sk_hasnt( 'Must fix before publishing', $all );
	twd_sk_hasnt( 'still has example text', $all );
	twd_sk_has( 'Find out about my services (1)', $all );
} );
