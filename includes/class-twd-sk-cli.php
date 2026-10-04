<?php
/**
 * WP-CLI commands. Loaded only when WP_CLI is defined.
 *
 * These go through TWD_SK_Store only. They are not the import endpoint.
 *
 *   wp twd-sk save <page_id> <file>     store a file (or - for stdin) as a new version
 *   wp twd-sk get <page_id>             print the current HTML
 *   wp twd-sk versions <page_id>        list versions, newest first
 *   wp twd-sk undo <page_id>            step back one change (recorded as a new version)
 *   wp twd-sk check <page_id>           list leftover example text still on the page
 *   wp twd-sk prompt <page_id>          print the client AI prompt (rules, style guide, this page's HTML)
 *   wp twd-sk setup [--pack=<slug>] [--skip-front-page]  create Home, About and Contact as drafts, fill a blank profile
 *   wp twd-sk export-templates [--dir=<path>]  write the two Elementor Theme Builder templates (header, footer) as JSON
 *   wp twd-sk safe-mode [on|off|status]  switch the newest features off, or back on
 *   wp twd-sk pack [<slug>]             list style packs, or switch to one
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class TWD_SK_CLI {

	/**
	 * Save an HTML file as a new version of a page.
	 *
	 * The HTML is cleaned first. Anything removed is listed.
	 *
	 * ## OPTIONS
	 *
	 * <page_id>
	 * : ID of the WordPress page that holds the [twd_page] shortcode.
	 *
	 * <file>
	 * : Path to an HTML file, or - to read from stdin.
	 *
	 * [--note=<note>]
	 * : Short note stored with the version.
	 *
	 * [--base=<version>]
	 * : Refuse to save if the page is no longer on this version.
	 *
	 * ## EXAMPLES
	 *
	 *     wp twd-sk save 12 home.html --note="First draft"
	 */
	public function save( $args, $assoc_args ) {
		$page_id = self::page_id( isset( $args[0] ) ? $args[0] : '' );
		$file    = isset( $args[1] ) ? (string) $args[1] : '';
		if ( '' === $file ) {
			WP_CLI::error( 'Give the path to an HTML file, or - to read from stdin.' );
			return;
		}

		if ( '-' === $file ) {
			$html = file_get_contents( 'php://stdin' );
		} else {
			if ( ! is_readable( $file ) ) {
				WP_CLI::error( 'Cannot read the file: ' . $file );
				return;
			}
			$html = file_get_contents( $file );
		}
		if ( false === $html ) {
			WP_CLI::error( 'Could not read the HTML.' );
			return;
		}

		$opts = array();
		if ( isset( $assoc_args['note'] ) ) {
			$opts['note'] = (string) $assoc_args['note'];
		}
		if ( isset( $assoc_args['base'] ) ) {
			$opts['base_version'] = (int) $assoc_args['base'];
		}

		self::report( $page_id, TWD_SK_Store::save( $page_id, $html, $opts ), 'Saved' );
		self::warn_if_no_shortcode( $page_id );
	}

	/**
	 * Print the current HTML of a page.
	 *
	 * ## OPTIONS
	 *
	 * <page_id>
	 * : ID of the WordPress page.
	 */
	public function get( $args, $assoc_args ) {
		$page_id = self::page_id( isset( $args[0] ) ? $args[0] : '' );
		$html    = TWD_SK_Store::get_current( $page_id );
		if ( '' === $html ) {
			WP_CLI::error( 'Page ' . $page_id . ' has no stored content.' );
			return;
		}
		WP_CLI::line( $html );
	}

	/**
	 * List the saved versions of a page, newest first.
	 *
	 * ## OPTIONS
	 *
	 * <page_id>
	 * : ID of the WordPress page.
	 *
	 * [--format=<format>]
	 * : table, json, csv or yaml. Default table.
	 */
	public function versions( $args, $assoc_args ) {
		$page_id = self::page_id( isset( $args[0] ) ? $args[0] : '' );
		$items   = TWD_SK_Store::list_versions( $page_id );
		if ( ! $items ) {
			WP_CLI::error( 'Page ' . $page_id . ' has no saved versions.' );
			return;
		}

		$current = TWD_SK_Store::get_current_version_id( $page_id );
		$rows    = array();
		foreach ( $items as $item ) {
			$rows[] = array(
				'version' => $item['id'],
				'current' => ( (int) $item['id'] === $current ) ? 'yes' : '',
				'created' => $item['created'],
				'user'    => $item['user'],
				'kind'    => $item['kind'],
				'bytes'   => $item['bytes'],
				'note'    => $item['note'],
			);
		}
		$format = isset( $assoc_args['format'] ) ? (string) $assoc_args['format'] : 'table';
		\WP_CLI\Utils\format_items( $format, $rows, array( 'version', 'current', 'created', 'user', 'kind', 'bytes', 'note' ) );
	}

	/**
	 * Step back one change. Recorded as a new version, so nothing is lost.
	 *
	 * ## OPTIONS
	 *
	 * <page_id>
	 * : ID of the WordPress page.
	 */
	public function undo( $args, $assoc_args ) {
		$page_id = self::page_id( isset( $args[0] ) ? $args[0] : '' );
		self::report( $page_id, TWD_SK_Store::undo( $page_id ), 'Undone' );
	}

	/**
	 * List the style packs, or switch to one.
	 *
	 * With no name it lists the packs and marks the active one. With a name it
	 * makes that pack active. The change shows on the site once any page cache
	 * has been cleared.
	 *
	 * ## OPTIONS
	 *
	 * [<slug>]
	 * : The pack to switch to, for example sage or grove.
	 *
	 * ## EXAMPLES
	 *
	 *     wp twd-sk pack
	 *     wp twd-sk pack grove
	 */
	public function pack( $args, $assoc_args ) {
		if ( empty( $args[0] ) ) {
			$active = TWD_SK_Packs::active_slug();
			$rows   = array();
			foreach ( TWD_SK_Packs::packs() as $slug => $pack ) {
				$rows[] = array(
					'pack'        => $slug,
					'active'      => ( $slug === $active ) ? 'yes' : '',
					'name'        => $pack['name'],
					'description' => $pack['description'],
				);
			}
			\WP_CLI\Utils\format_items( 'table', $rows, array( 'pack', 'active', 'name', 'description' ) );
			return;
		}

		$result = TWD_SK_Packs::set_active( (string) $args[0] );
		if ( is_wp_error( $result ) ) {
			WP_CLI::error( $result->get_error_message() );
			return;
		}
		WP_CLI::success( 'Style pack is now "' . $args[0] . '". If the site uses a page cache, clear it to see the change.' );
	}

	/**
	 * List example text still on a page, in two levels: "must fix" (blocks publishing:
	 * example.com, PHONE_NUMBER, "Heading here", any [PLACEHOLDER and so on) and "check"
	 * (the example button wording, which only warns). Nothing is changed. Run it before a
	 * page goes live.
	 *
	 * ## OPTIONS
	 *
	 * <page_id>
	 * : ID of the WordPress page that holds the [twd_page] shortcode.
	 */
	public function check( $args, $assoc_args ) {
		$page_id = self::page_id( isset( $args[0] ) ? $args[0] : '' );
		$html    = TWD_SK_Store::get_current( $page_id );
		if ( '' === $html ) {
			WP_CLI::error( 'Page ' . $page_id . ' has no stored content.' );
			return;
		}
		$levels = TWD_SK_Store::get_leftover_levels( $page_id );
		if ( ! $levels['must'] && ! $levels['check'] ) {
			WP_CLI::success( 'Page ' . $page_id . ' has no leftover example text.' );
			return;
		}
		if ( $levels['must'] ) {
			WP_CLI::warning( 'Page ' . $page_id . ' still has example text. Replace it before the page goes live:' );
			WP_CLI::log( 'Must fix before publishing:' );
			foreach ( $levels['must'] as $marker => $count ) {
				WP_CLI::log( '  ' . $marker . ' (' . $count . ')' );
			}
		} else {
			WP_CLI::warning( 'Page ' . $page_id . ' has example wording to check. It never blocks publishing:' );
		}
		if ( $levels['check'] ) {
			WP_CLI::log( 'Check (never blocks publishing):' );
			foreach ( $levels['check'] as $marker => $count ) {
				WP_CLI::log( '  ' . $marker . ' (' . $count . ')' );
			}
		}
	}

	/**
	 * Print the client AI prompt for a page: fixed rules, a style guide generated from
	 * the registry, and the page's current stored HTML. Paste it into an AI chat.
	 *
	 * ## OPTIONS
	 *
	 * <page_id>
	 * : ID of the WordPress page that holds the [twd_page] shortcode.
	 *
	 * ## EXAMPLES
	 *
	 *     wp twd-sk prompt 12 > prompt.txt
	 */
	public function prompt( $args, $assoc_args ) {
		$page_id = self::page_id( isset( $args[0] ) ? $args[0] : '' );
		WP_CLI::line( rtrim( TWD_SK_Prompt::build( TWD_SK_Store::get_current( $page_id ) ) ) );
	}

	/**
	 * Safe mode turns off the newest features (the 0.4.0 modules) and keeps the rest working.
	 * Use it if something breaks after an update. The constant TWD_SK_SAFE_MODE in wp-config.php
	 * does the same and wins over this setting.
	 *
	 * ## OPTIONS
	 *
	 * [<state>]
	 * : on, off or status (default status).
	 *
	 * ## EXAMPLES
	 *
	 *     wp twd-sk safe-mode on
	 *     wp twd-sk safe-mode status
	 */
	public function safe_mode( $args, $assoc_args ) {
		$state = isset( $args[0] ) ? strtolower( (string) $args[0] ) : 'status';
		if ( ! in_array( $state, array( 'on', 'off', 'status' ), true ) ) {
			WP_CLI::error( 'Say on, off or status.' );
			return;
		}
		if ( 'status' !== $state ) {
			TWD_SK_Safe::set( 'on' === $state );
		}
		$source = TWD_SK_Safe::source();
		if ( TWD_SK_Safe::on() ) {
			WP_CLI::success( 'Safe mode is ON' . ( 'constant' === $source ? ' (set by TWD_SK_SAFE_MODE in wp-config.php; remove that line to turn it off).' : '.' ) );
		} else {
			WP_CLI::success( 'Safe mode is OFF.' );
		}
	}

	/**
	 * Print a plain-text report of the site's state, safe to paste into a message: versions, safe mode,
	 * style, front page, Theme Builder header and footer templates and their display conditions, which
	 * pages use the kit and what is left to fix, which details are filled in (never their contents), and
	 * the pictures in the Media Library with their alt text. Read only. Works in safe mode.
	 *
	 * ## EXAMPLES
	 *
	 *     wp twd-sk doctor
	 *     wp twd-sk doctor > report.txt
	 */
	public function doctor( $args, $assoc_args ) {
		WP_CLI::line( rtrim( TWD_SK_Doctor::report() ) );
	}

	/**
	 * Write the two Elementor Theme Builder templates (a header and a footer, each one Shortcode
	 * widget) as JSON files for importing into a base site. After importing, set each template's
	 * display condition to the entire site.
	 *
	 * ## OPTIONS
	 *
	 * [--dir=<path>]
	 * : Folder to write into. Default: the current folder.
	 *
	 * ## EXAMPLES
	 *
	 *     wp twd-sk export-templates --dir=/tmp
	 */
	public function export_templates( $args, $assoc_args ) {
		$dir = isset( $assoc_args['dir'] ) ? rtrim( (string) $assoc_args['dir'], '/\\' ) : getcwd();
		if ( ! is_dir( $dir ) || ! is_writable( $dir ) ) {
			WP_CLI::error( 'Cannot write to that folder: ' . $dir );
			return;
		}
		foreach ( TWD_SK_Elementor::files() as $name => $json ) {
			file_put_contents( $dir . '/' . $name, $json );
			WP_CLI::success( 'Wrote ' . $dir . '/' . $name );
		}
		WP_CLI::log( 'In Elementor: Templates, Theme Builder, import each file, then set its display condition to Entire Site.' );
	}

	/**
	 * Set a new site up from the starters: create Home, About and Contact as DRAFT pages ready
	 * for the kit, fill an empty site profile with placeholders, optionally switch the style
	 * pack, and make Home the front page. Safe to run twice. Nothing is published.
	 *
	 * ## OPTIONS
	 *
	 * [--pack=<slug>]
	 * : Style pack to switch to (see wp twd-sk pack).
	 *
	 * [--skip-front-page]
	 * : Do not change the front page setting.
	 *
	 * ## EXAMPLES
	 *
	 *     wp twd-sk setup --pack=sage
	 */
	public function setup( $args, $assoc_args ) {
		$options = array(
			'pack'       => isset( $assoc_args['pack'] ) ? (string) $assoc_args['pack'] : '',
			'front_page' => ! isset( $assoc_args['skip-front-page'] ),
		);
		$report = TWD_SK_Setup::run( $options );
		if ( is_wp_error( $report ) ) {
			WP_CLI::error( $report->get_error_message() );
			return;
		}
		foreach ( $report['created'] as $p ) {
			WP_CLI::success( 'Created draft page "' . $p['title'] . '" (ID ' . $p['id'] . ').' );
		}
		foreach ( $report['skipped'] as $p ) {
			WP_CLI::log( 'Skipped "' . $p['title'] . '": it already exists (ID ' . $p['id'] . ').' );
		}
		if ( '' !== $report['pack'] ) {
			WP_CLI::success( 'Style pack is now ' . $report['pack'] . '.' );
		}
		if ( 'filled' === $report['profile'] ) {
			WP_CLI::success( 'Filled the empty site details with placeholders.' );
		}
		foreach ( $report['notes'] as $note ) {
			WP_CLI::warning( $note );
		}
		WP_CLI::log( 'Next: replace every [PLACEHOLDER], then run wp twd-sk check PAGE_ID before publishing.' );
	}

	// -- Helpers ----------------------------------------------------------

	private static function page_id( $value ) {
		if ( ! ctype_digit( (string) $value ) || (int) $value <= 0 ) {
			WP_CLI::error( 'The page ID must be a number, for example 12.' );
		}
		return (int) $value;
	}

	private static function report( $page_id, $result, $verb ) {
		if ( is_wp_error( $result ) ) {
			WP_CLI::error( $result->get_error_message() );
			return;
		}

		if ( ! empty( $result['unchanged'] ) ) {
			WP_CLI::success( 'No change. Page ' . $page_id . ' is already on version ' . $result['version'] . '.' );
		} else {
			WP_CLI::success( $verb . ' page ' . $page_id . ' as version ' . $result['version'] . '.' );
		}

		$report = $result['report'];
		if ( ! empty( $report['leftovers'] ) ) {
			$parts = array();
			foreach ( $report['leftovers'] as $marker => $count ) {
				$parts[] = $marker . ' (' . $count . ')';
			}
			WP_CLI::warning( 'Example text is still on the page: ' . implode( ', ', $parts ) . '. Run wp twd-sk check ' . $page_id . ' before it goes live.' );
		}
		if ( ! empty( $report['total'] ) ) {
			WP_CLI::warning( 'The cleaner removed or changed ' . $report['total'] . ' item(s):' );
			foreach ( $report['removed'] as $kind => $list ) {
				if ( $list ) {
					WP_CLI::log( '  ' . $kind . ' (' . count( $list ) . '): ' . implode( ' | ', array_slice( $list, 0, 10 ) ) . ( count( $list ) > 10 ? ' ...' : '' ) );
				}
			}
		}
	}

	private static function warn_if_no_shortcode( $page_id ) {
		$content   = (string) get_post_field( 'post_content', $page_id );
		$elementor = get_post_meta( $page_id, '_elementor_data', true );
		if ( is_string( $elementor ) ) {
			$content .= $elementor;
		}
		if ( false === strpos( $content, '[twd_page' ) ) {
			WP_CLI::warning( 'Page ' . $page_id . ' does not seem to contain the [twd_page] shortcode yet, so the content will not show. Add a Shortcode widget with [twd_page] to it.' );
		}
	}
}
