<?php
/**
 * TWD_SK_Modules: starts the modules added in 0.4.0.
 *
 * Called from the main plugin file only when safe mode is off. Anything the 0.3.x releases
 * did stays outside this class so it keeps working in safe mode.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class TWD_SK_Modules {

	public static function init() {
		// Modules register here as they are added.
	}
}
