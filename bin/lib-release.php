<?php
/**
 * Release checks shared by bin/check-release.php, bin/update-json.php and the
 * tests. Plain PHP, no WordPress. Needs the zip extension (ZipArchive).
 *
 * A release is consistent when all of these agree:
 *  - the plugin header Version, the TWD_SK_VERSION constant and readme.txt Stable tag
 *  - the version in dist/twd-site-kit-update.json
 *  - the sha256 in that JSON and the sha256 of dist/twd-site-kit-latest.zip
 *  - the same version inside the zip, which has one top-level folder twd-site-kit
 */

if ( ! defined( 'TWD_SK_RELEASE_ZIP_URL' ) ) {
	define( 'TWD_SK_RELEASE_ZIP_URL', 'https://raw.githubusercontent.com/thistwistedyouth/twd-site-kit/main/dist/twd-site-kit-latest.zip' );
}

function twd_sk_release_match( $pattern, $text ) {
	return ( 1 === preg_match( $pattern, $text, $m ) ) ? $m[1] : null;
}

/** The three version strings kept in the source files. */
function twd_sk_release_source_versions( $root ) {
	$main   = is_file( $root . '/twd-site-kit.php' ) ? file_get_contents( $root . '/twd-site-kit.php' ) : '';
	$readme = is_file( $root . '/readme.txt' ) ? file_get_contents( $root . '/readme.txt' ) : '';
	return array(
		'header' => twd_sk_release_match( '/^\s*\*\s*Version:\s*(\S+)/m', $main ),
		'const'  => twd_sk_release_match( "/define\(\s*'TWD_SK_VERSION'\s*,\s*'([^']+)'/", $main ),
		'readme' => twd_sk_release_match( '/^Stable tag:\s*(\S+)/m', $readme ),
	);
}

function twd_sk_release_json( $root ) {
	$path = $root . '/dist/twd-site-kit-update.json';
	if ( ! is_file( $path ) ) {
		return null;
	}
	$data = json_decode( (string) file_get_contents( $path ), true );
	return is_array( $data ) ? $data : null;
}

/**
 * What is inside a built zip: its top-level folders, every file name, and the
 * versions found in its own plugin file and readme.
 */
function twd_sk_release_zip_info( $zip_path ) {
	$info = array(
		'error'   => '',
		'folders' => array(),
		'files'   => array(),
		'header'  => null,
		'const'   => null,
		'readme'  => null,
	);
	if ( ! class_exists( 'ZipArchive' ) ) {
		$info['error'] = 'The PHP zip extension (ZipArchive) is not available.';
		return $info;
	}
	$zip = new ZipArchive();
	if ( true !== $zip->open( $zip_path ) ) {
		$info['error'] = 'The zip could not be opened.';
		return $info;
	}
	for ( $i = 0; $i < $zip->numFiles; $i++ ) {
		$name = $zip->getNameIndex( $i );
		if ( '/' !== substr( $name, -1 ) ) {
			$info['files'][] = $name;
		}
		$top = strtok( $name, '/' );
		if ( false !== $top && '' !== $top ) {
			$info['folders'][ $top ] = true;
		}
	}
	$info['folders'] = array_keys( $info['folders'] );
	$main            = $zip->getFromName( 'twd-site-kit/twd-site-kit.php' );
	$readme          = $zip->getFromName( 'twd-site-kit/readme.txt' );
	$zip->close();
	if ( false !== $main ) {
		$info['header'] = twd_sk_release_match( '/^\s*\*\s*Version:\s*(\S+)/m', $main );
		$info['const']  = twd_sk_release_match( "/define\(\s*'TWD_SK_VERSION'\s*,\s*'([^']+)'/", $main );
	}
	if ( false !== $readme ) {
		$info['readme'] = twd_sk_release_match( '/^Stable tag:\s*(\S+)/m', $readme );
	}
	return $info;
}

/**
 * Check a release.
 *
 * @param string $root
 * @param string $json_mode 'strict' (the JSON must match the zip), 'allow-stale'
 *                          (an older JSON version is only a note, used right
 *                          after building and before the JSON is updated), or 'skip'.
 * @return array { problems: list of strings, notes: list of strings }
 */
function twd_sk_release_check( $root, $json_mode = 'strict' ) {
	$problems = array();
	$notes    = array();
	$zip_path = $root . '/dist/twd-site-kit-latest.zip';

	// 1. The three source versions agree.
	$v = twd_sk_release_source_versions( $root );
	foreach ( $v as $name => $value ) {
		if ( null === $value ) {
			$problems[] = 'Could not read the version from the ' . $name . '.';
		}
	}
	$version = $v['header'];
	if ( null !== $v['header'] && null !== $v['const'] && null !== $v['readme'] && 1 !== count( array_unique( array_values( $v ) ) ) ) {
		$problems[] = 'Versions disagree: plugin header ' . $v['header'] . ', TWD_SK_VERSION ' . $v['const'] . ', readme.txt Stable tag ' . $v['readme'] . '.';
	}

	// 2. The zip: one top-level folder, the same version inside, nothing that must not ship.
	$sha = null;
	if ( ! is_file( $zip_path ) ) {
		$problems[] = 'dist/twd-site-kit-latest.zip does not exist. Run bin/build-zip.sh.';
	} else {
		$sha = hash_file( 'sha256', $zip_path );
		$z   = twd_sk_release_zip_info( $zip_path );
		if ( '' !== $z['error'] ) {
			$problems[] = $z['error'];
		} else {
			if ( array( 'twd-site-kit' ) !== $z['folders'] ) {
				$problems[] = 'The zip must have exactly one top-level folder, twd-site-kit. It has: ' . implode( ', ', $z['folders'] ) . '.';
			}
			foreach ( array( 'header' => 'plugin header', 'const' => 'TWD_SK_VERSION', 'readme' => 'readme.txt Stable tag' ) as $key => $label ) {
				if ( $z[ $key ] !== $version ) {
					$problems[] = 'The zip holds ' . $label . ' ' . ( null === $z[ $key ] ? '(missing)' : $z[ $key ] ) . ' but the source is ' . $version . '. Rebuild the zip.';
				}
			}
			foreach ( $z['files'] as $file ) {
				if ( 1 === preg_match( '#(^|/)(claude|history)[^/]*$|/tests/|/bin/|/dist/|(^|/)\.git|readme\.md$#i', $file ) ) {
					$problems[] = 'The zip contains a file that must not ship: ' . $file;
				}
			}
		}
	}

	// 3. The update JSON.
	if ( 'skip' !== $json_mode ) {
		$json = twd_sk_release_json( $root );
		if ( null === $json ) {
			$problems[] = 'dist/twd-site-kit-update.json is missing or is not valid JSON.';
		} else {
			$json_version = isset( $json['version'] ) && is_string( $json['version'] ) ? $json['version'] : '';
			if ( isset( $json['download_url'] ) && TWD_SK_RELEASE_ZIP_URL !== $json['download_url'] ) {
				$problems[] = 'The JSON download_url is not the fixed zip address.';
			}
			if ( $json_version !== $version ) {
				if ( 'allow-stale' === $json_mode && version_compare( $json_version, (string) $version, '<' ) ) {
					$notes[] = 'The update JSON is still on ' . $json_version . '. Next step: php bin/update-json.php --changelog="..." (then re-run bin/check-release.php).';
				} else {
					$problems[] = 'The update JSON version is ' . ( '' === $json_version ? '(missing)' : $json_version ) . ' but the plugin is ' . $version . '.';
				}
			} else {
				$json_sha = isset( $json['sha256'] ) && is_string( $json['sha256'] ) ? strtolower( $json['sha256'] ) : '';
				if ( 1 !== preg_match( '/^[a-f0-9]{64}$/', $json_sha ) ) {
					$problems[] = 'The JSON sha256 is missing or not 64 hex characters.';
				} elseif ( null !== $sha && $json_sha !== $sha ) {
					$problems[] = 'The JSON sha256 (' . substr( $json_sha, 0, 12 ) . '...) does not match the built zip (' . substr( $sha, 0, 12 ) . '...). Run php bin/update-json.php.';
				}
				if ( empty( $json['changelog'] ) || false === strpos( (string) $json['changelog'], '<h4>' . $version . '</h4>' ) ) {
					$problems[] = 'The JSON changelog has no entry for ' . $version . '.';
				}
			}
		}
	}

	return array( 'problems' => $problems, 'notes' => $notes );
}
