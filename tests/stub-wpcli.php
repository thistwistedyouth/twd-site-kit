<?php
// Stand-in for WP-CLI, used only by the CLI tests.

namespace {
	class WP_CLI_Stub_Error extends Exception {}

	class WP_CLI {
		public static $log = array();

		public static function reset() {
			self::$log = array();
		}
		public static function success( $m ) {
			self::$log[] = 'success: ' . $m;
		}
		public static function warning( $m ) {
			self::$log[] = 'warning: ' . $m;
		}
		public static function log( $m ) {
			self::$log[] = 'log: ' . $m;
		}
		public static function line( $m ) {
			self::$log[] = 'line: ' . $m;
		}
		public static function error( $m ) {
			throw new WP_CLI_Stub_Error( $m );
		}
		public static function all() {
			return implode( "\n", self::$log );
		}
	}
}

namespace WP_CLI\Utils {
	function format_items( $format, $items, $fields ) {
		\WP_CLI::$log[] = 'table(' . $format . '): ' . json_encode( array( 'fields' => $fields, 'rows' => $items ) );
	}
}
