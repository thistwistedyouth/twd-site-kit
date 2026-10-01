<?php
// Privacy guard. This repository is public. Nothing client-specific may ever be in it.
//
// Two layers:
//  1. Generic patterns that always run: real-looking email addresses, phone
//     numbers, web addresses, uploaded-file paths and long digit runs. The test
//     files themselves are fixtures full of deliberately fake hostile data, so
//     for tests/ only the email and web address checks apply, and they accept
//     the reserved .example domain too.
//  2. An optional private list of client words, kept OUTSIDE the repository so
//     the names themselves never enter it. Point TWD_SK_PRIVACY_DENYLIST at a
//     text file (one word or phrase per line), or put the file at
//     ~/.twd-sk-privacy-denylist.txt. If neither exists only layer 1 runs.

function twd_sk_privacy_files() {
	$root  = realpath( ABSPATH );
	$files = array();
	$iter  = new RecursiveIteratorIterator( new RecursiveDirectoryIterator( $root, FilesystemIterator::SKIP_DOTS ) );
	foreach ( $iter as $file ) {
		$path = $file->getPathname();
		$rel  = substr( $path, strlen( $root ) + 1 );
		if ( 0 === strpos( $rel, '.git/' ) || 0 === strpos( $rel, 'dist/' ) || 0 === strpos( $rel, 'assets/fonts/' ) ) {
			continue;
		}
		if ( preg_match( '/\.(php|md|txt|json|css|html|sh|yml|yaml)$/', $rel ) || '.gitignore' === $rel ) {
			$files[ $rel ] = $path;
		}
	}
	ksort( $files );
	return $files;
}

function twd_sk_privacy_is_test( $rel ) {
	return 0 === strpos( $rel, 'tests/' );
}

function twd_sk_privacy_denylist() {
	$path = getenv( 'TWD_SK_PRIVACY_DENYLIST' );
	if ( ! $path ) {
		$home = getenv( 'HOME' );
		$path = $home ? $home . '/.twd-sk-privacy-denylist.txt' : '';
	}
	if ( ! $path || ! is_readable( $path ) ) {
		return null;
	}
	$words = array();
	foreach ( file( $path, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES ) as $line ) {
		$line = trim( $line );
		if ( '' !== $line && '#' !== $line[0] ) {
			$words[] = $line;
		}
	}
	return $words;
}

twd_sk_test( 'privacy: the repo has files to scan, and the dist zip and fonts are the only things left out', function () {
	$files = twd_sk_privacy_files();
	twd_sk_true( count( $files ) > 20, 'files scanned: ' . count( $files ) );
	twd_sk_true( isset( $files['CLAUDE.md'] ) && isset( $files['HISTORY.md'] ) && isset( $files['starters/_gallery.html'] ) && isset( $files['packs/sage.json'] ) );
} );

twd_sk_test( 'privacy: every email address in the repo is an example address', function () {
	foreach ( twd_sk_privacy_files() as $rel => $path ) {
		preg_match_all( '/[A-Za-z0-9._%+-]+@[A-Za-z0-9.-]+\.[A-Za-z]{2,}/', file_get_contents( $path ), $m );
		foreach ( $m[0] as $email ) {
			$ok = ( 1 === preg_match( '/@example\.(com|org|net)$/', $email ) ) || ( twd_sk_privacy_is_test( $rel ) && 1 === preg_match( '/\.example$/', $email ) );
			twd_sk_true( $ok, $rel . ' has a real-looking email: ' . $email );
		}
	}
} );

twd_sk_test( 'privacy: no phone numbers, apart from the national helplines', function () {
	$allowed = array( '0800 58 58 58', '0800585858' );
	foreach ( twd_sk_privacy_files() as $rel => $path ) {
		if ( twd_sk_privacy_is_test( $rel ) ) {
			continue;
		}
		preg_match_all( '/(?<![\d.])(?:\+44|0)[\d \-()]{8,14}\d/', file_get_contents( $path ), $m );
		foreach ( $m[0] as $number ) {
			twd_sk_true( in_array( trim( $number ), $allowed, true ), $rel . ' has a phone-number-like string: ' . $number );
		}
	}
} );

twd_sk_test( 'privacy: no long digit runs (registration or membership numbers), apart from the helpline numbers', function () {
	$allowed = array( '116123', '0800585858' );
	foreach ( twd_sk_privacy_files() as $rel => $path ) {
		if ( twd_sk_privacy_is_test( $rel ) ) {
			continue;
		}
		$text = file_get_contents( $path );
		// Hex colours and version-like strings do not count: only whole-word digit runs.
		preg_match_all( '/(?<![\w#.])\d{6,}(?![\w])/', $text, $m );
		foreach ( $m[0] as $run ) {
			twd_sk_true( in_array( $run, $allowed, true ), $rel . ' has a long number: ' . $run );
		}
	}
} );

twd_sk_test( 'privacy: every web address points at an example domain or a known project, standards or licence host', function () {
	$hosts = array(
		'example.com', 'www.example.com', 'example.org', 'example.net', 'example.test',
		'therapywebdesigns.co.uk',
		'github.com', 'raw.githubusercontent.com',
		'www.gnu.org', 'www.w3.org', 'schema.org', 'scripts.sil.org', 'openfontlicense.org',
	);
	foreach ( twd_sk_privacy_files() as $rel => $path ) {
		preg_match_all( '#https?://(?:[^/@\s"\'<>]*@)?([A-Za-z0-9.-]+)(/[^\s"\'<>)`]*)?#', file_get_contents( $path ), $m, PREG_SET_ORDER );
		foreach ( $m as $hit ) {
			$host = strtolower( $hit[1] );
			$ok   = in_array( $host, $hosts, true ) || ( twd_sk_privacy_is_test( $rel ) && 1 === preg_match( '/\.example$/', $host ) );
			twd_sk_true( $ok, $rel . ' links to ' . $host );
			if ( 'github.com' === $host || 'raw.githubusercontent.com' === $host ) {
				twd_sk_true( 1 === preg_match( '#^/thistwistedyouth/twd-site-kit(/|$)#', isset( $hit[2] ) ? $hit[2] : '' ), $rel . ' links to another GitHub repo: ' . $hit[0] );
			}
		}
	}
} );

twd_sk_test( 'privacy: uploaded-file paths are placeholders only (your-image style names)', function () {
	foreach ( twd_sk_privacy_files() as $rel => $path ) {
		if ( twd_sk_privacy_is_test( $rel ) ) {
			continue;
		}
		preg_match_all( '#wp-content/' . 'uploads/([^\s"\'<>)`]*)#', file_get_contents( $path ), $m );
		foreach ( $m[1] as $file ) {
			twd_sk_true( '' === $file || 0 === strpos( $file, 'your-' ), $rel . ' has an upload path: ' . $file );
		}
	}
} );

twd_sk_test( 'privacy: no other GitHub repo of the agency is named by URL, and no client site repo is named at all', function () {
	foreach ( twd_sk_privacy_files() as $rel => $path ) {
		twd_sk_true( 0 === preg_match( '#thistwistedyouth/(?!twd-site-kit)[\w-]*-site\b#', file_get_contents( $path ) ), $rel . ' names a client site repo' );
		twd_sk_hasnt( 'twd-starter' . '-sources', file_get_contents( $path ), $rel . ' names the private sources repo' );
	}
} );

twd_sk_test( 'privacy: no word from the private client list appears anywhere in the repo (checked when the list is present)', function () {
	$words = twd_sk_privacy_denylist();
	if ( null === $words ) {
		twd_sk_note( 'Privacy: no private client list found, so only the generic checks ran. Set TWD_SK_PRIVACY_DENYLIST to a file of client words to run the full check.' );
		return;
	}
	twd_sk_true( count( $words ) > 0, 'the private list is not empty' );
	twd_sk_note( 'Privacy: checked against a private list of ' . count( $words ) . ' client words (list is outside the repo).' );
	foreach ( twd_sk_privacy_files() as $rel => $path ) {
		$text = strtolower( file_get_contents( $path ) );
		foreach ( $words as $word ) {
			twd_sk_true( false === strpos( $text, strtolower( $word ) ), $rel . ' contains a client word (line ' . ( array_search( $word, $words, true ) + 1 ) . ' of the private list)' );
		}
	}
	// Also check file and folder names.
	foreach ( array_keys( twd_sk_privacy_files() ) as $rel ) {
		foreach ( $words as $word ) {
			twd_sk_true( false === strpos( strtolower( $rel ), strtolower( $word ) ), 'file name ' . $rel . ' contains a client word' );
		}
	}
} );

twd_sk_test( 'privacy: the guard itself catches what it should (self-test on sample strings)', function () {
	$bad = array(
		// Built from pieces so this file does not trip the very checks it tests.
		'email'  => 'contact me at jane' . '@realpractice.co.uk',
		'phone'  => 'call 01234' . ' 567 890 today',
		'number' => 'membership 123' . '456',
	);
	twd_sk_true( 1 === preg_match( '/[A-Za-z0-9._%+-]+@[A-Za-z0-9.-]+\.[A-Za-z]{2,}/', $bad['email'] ) );
	twd_sk_true( 1 === preg_match( '/(?<![\d.])(?:\+44|0)[\d \-()]{8,14}\d/', $bad['phone'] ) );
	twd_sk_true( 1 === preg_match( '/(?<![\w#.])\d{6,}(?![\w])/', $bad['number'] ) );
	twd_sk_true( 0 === preg_match( '/(?<![\w#.])\d{6,}(?![\w])/', 'colour #8A9A7E and rgba(58,31,46,0.55)' ) );
} );
