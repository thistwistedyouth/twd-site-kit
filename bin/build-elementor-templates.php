<?php
/**
 * Regenerate starters/elementor/twd-header.json and twd-footer.json from TWD_SK_Elementor.
 * Run from the repo root:  php bin/build-elementor-templates.php
 */
define( 'ABSPATH', dirname( __DIR__ ) . '/' );
require ABSPATH . 'includes/class-twd-sk-elementor.php';

$dir = ABSPATH . 'starters/elementor/';
if ( ! is_dir( $dir ) ) {
	mkdir( $dir, 0755, true );
}
foreach ( TWD_SK_Elementor::files() as $name => $json ) {
	file_put_contents( $dir . $name, $json );
	echo 'Wrote starters/elementor/' . $name . ' (' . strlen( $json ) . " bytes)\n";
}
