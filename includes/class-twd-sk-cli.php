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
	 * List example text still on a page (example.com, PHONE_NUMBER, "Heading here" and so
	 * on, or any [PLACEHOLDER). Nothing is changed. Run it before a page goes live.
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
		$found = TWD_SK_Store::get_leftovers( $page_id );
		if ( ! $found ) {
			WP_CLI::success( 'Page ' . $page_id . ' has no leftover example text.' );
			return;
		}
		WP_CLI::warning( 'Page ' . $page_id . ' still has example text. Replace it before the page goes live:' );
		foreach ( $found as $marker => $count ) {
			WP_CLI::log( '  ' . $marker . ' (' . $count . ')' );
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
