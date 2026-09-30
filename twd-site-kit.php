<?php
/**
 * Plugin Name: TWD Site Kit
 * Plugin URI: https://therapywebdesigns.co.uk/
 * Description: Pages for therapist sites built from sanitised HTML and a fixed set of components, with version history. Sibling to the Articles & Resource Production Plugin.
 * Version: 0.1.0
 * Requires at least: 6.0
 * Requires PHP: 7.4
 * Author: Therapy Web Designs
 * Author URI: https://therapywebdesigns.co.uk/
 * License: GPL v2 or later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain: twd-site-kit
 * Update URI: https://github.com/thistwistedyouth/twd-site-kit
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

define( 'TWD_SK_VERSION', '0.1.0' );
define( 'TWD_SK_PATH', plugin_dir_path( __FILE__ ) );

require_once TWD_SK_PATH . 'includes/class-twd-sk-registry.php';
require_once TWD_SK_PATH . 'includes/class-twd-sk-sanitizer.php';
require_once TWD_SK_PATH . 'includes/class-twd-sk-store.php';
require_once TWD_SK_PATH . 'includes/class-twd-sk-page.php';

add_action( 'init', array( 'TWD_SK_Page', 'init' ) );

if ( defined( 'WP_CLI' ) && WP_CLI ) {
	require_once TWD_SK_PATH . 'includes/class-twd-sk-cli.php';
	WP_CLI::add_command( 'twd-sk', 'TWD_SK_CLI' );
}
