<?php
/**
 * Plugin Name: TWD Site Kit
 * Plugin URI: https://therapywebdesigns.co.uk/
 * Description: Pages for therapist sites built from sanitised HTML and a fixed set of components, with version history, style packs and bundled fonts. Sibling to the Articles & Resource Production Plugin.
 * Version: 0.6.1
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

define( 'TWD_SK_VERSION', '0.6.1' );
define( 'TWD_SK_PATH', plugin_dir_path( __FILE__ ) );
define( 'TWD_SK_URL', plugin_dir_url( __FILE__ ) );

require_once TWD_SK_PATH . 'includes/class-twd-sk-safe.php';
require_once TWD_SK_PATH . 'includes/class-twd-sk-registry.php';
require_once TWD_SK_PATH . 'includes/class-twd-sk-sanitizer.php';
require_once TWD_SK_PATH . 'includes/class-twd-sk-store.php';
require_once TWD_SK_PATH . 'includes/class-twd-sk-prompt.php';
require_once TWD_SK_PATH . 'includes/class-twd-sk-page.php';
require_once TWD_SK_PATH . 'includes/class-twd-sk-packs.php';
require_once TWD_SK_PATH . 'includes/class-twd-sk-contrast.php';
require_once TWD_SK_PATH . 'includes/class-twd-sk-site.php';
require_once TWD_SK_PATH . 'includes/class-twd-sk-profile.php';
require_once TWD_SK_PATH . 'includes/class-twd-sk-chrome.php';
require_once TWD_SK_PATH . 'includes/class-twd-sk-elementor.php';
require_once TWD_SK_PATH . 'includes/class-twd-sk-starters.php';
require_once TWD_SK_PATH . 'includes/class-twd-sk-setup.php';
require_once TWD_SK_PATH . 'includes/class-twd-sk-mirror.php';
require_once TWD_SK_PATH . 'includes/class-twd-sk-images.php';
require_once TWD_SK_PATH . 'includes/class-twd-sk-quality.php';
require_once TWD_SK_PATH . 'includes/class-twd-sk-seo.php';
require_once TWD_SK_PATH . 'includes/class-twd-sk-schema.php';
require_once TWD_SK_PATH . 'includes/class-twd-sk-sections.php';
require_once TWD_SK_PATH . 'includes/class-twd-sk-facts.php';
require_once TWD_SK_PATH . 'includes/class-twd-sk-recipes.php';
require_once TWD_SK_PATH . 'includes/class-twd-sk-ai.php';
require_once TWD_SK_PATH . 'includes/class-twd-sk-proposals.php';
require_once TWD_SK_PATH . 'includes/class-twd-sk-brief.php';
require_once TWD_SK_PATH . 'includes/class-twd-sk-assets.php';
require_once TWD_SK_PATH . 'includes/class-twd-sk-updater.php';
require_once TWD_SK_PATH . 'includes/class-twd-sk-report.php';
require_once TWD_SK_PATH . 'includes/class-twd-sk-template.php';
require_once TWD_SK_PATH . 'includes/class-twd-sk-preview.php';
require_once TWD_SK_PATH . 'includes/class-twd-sk-rest.php';
require_once TWD_SK_PATH . 'includes/class-twd-sk-editor.php';
require_once TWD_SK_PATH . 'includes/class-twd-sk-modules.php';

add_action( 'init', array( 'TWD_SK_Page', 'init' ) );
TWD_SK_Assets::init();
TWD_SK_Template::init();
TWD_SK_Preview::init();
TWD_SK_REST::init();
TWD_SK_Editor::init();
TWD_SK_Updater::init();
TWD_SK_Safe::init();
// The header and footer shortcodes stay registered in safe mode (they print a plain version).
TWD_SK_Chrome::init();

// The newest modules (0.4.0) start only outside safe mode.
if ( ! TWD_SK_Safe::on() ) {
	TWD_SK_Modules::init();
}

if ( defined( 'WP_CLI' ) && WP_CLI ) {
	require_once TWD_SK_PATH . 'includes/class-twd-sk-cli.php';
	WP_CLI::add_command( 'twd-sk', 'TWD_SK_CLI' );
}
