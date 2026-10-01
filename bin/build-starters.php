<?php
/**
 * Regenerate starters/page-home.html, page-about.html and page-contact.html from the registry.
 * Run from the repo root:  php bin/build-starters.php
 */
define( 'ABSPATH', dirname( __DIR__ ) . '/' );
require ABSPATH . 'includes/class-twd-sk-registry.php';
require ABSPATH . 'includes/class-twd-sk-starters.php';

foreach ( TWD_SK_Starters::files() as $name => $html ) {
	file_put_contents( ABSPATH . 'starters/' . $name, $html );
	echo 'Wrote starters/' . $name . ' (' . strlen( $html ) . " bytes)\n";
}
