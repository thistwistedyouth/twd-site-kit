<?php
/**
 * WP-CLI commands. Loaded only when WP_CLI is defined.
 *
 * These go through TWD_SK_Store only. They are not the import endpoint.
 *
 *   wp twd-sk save <page_id> <file>     store a file (or - for stdin) as a new version (--pictures places pic: names first)
 *   wp twd-sk get <page_id>             print the current HTML
 *   wp twd-sk versions <page_id>        list versions, newest first
 *   wp twd-sk undo <page_id> [--allow-placeholders]  step back one change (recorded as a new version; the flag allows the blank starter)
 *   wp twd-sk check <page_id>           list leftover example text still on the page
 *   wp twd-sk prompt <page_id>          print the client AI prompt (rules, style guide, this page's HTML)
 *   wp twd-sk setup [--pack=<slug>] [--skip-front-page]  create Home, About and Contact as drafts, fill a blank profile
 *   wp twd-sk export-templates [--dir=<path>]  write the two Elementor Theme Builder templates (header, footer) as JSON
 *   wp twd-sk safe-mode [on|off|status]  switch the newest features off, or back on
 *   wp twd-sk pack [<slug>]             list style packs, or switch to one
 *   wp twd-sk mode [<mode>]             show the site type (therapy or business), or switch it
 *   wp twd-sk brand [<picture-id>] [--apply]  colours from the logo, checked for contrast; --apply saves them
 *   wp twd-sk launch-check [--publish]  check the whole site before launch; --publish publishes the pages that are ready
 *   wp twd-sk snags [--status=<status>] [--type=<type>] [--format=json|markdown]  print the Tell us notes kept on this site
 *   wp twd-sk snag-status <id> <status>  set a note to new, seen, done or parked
 *   wp twd-sk snags-setting [on|off]     show, or switch, the Tell us module
 *   wp twd-sk upgrade [--apply] [--page=<id>]  bring pages built earlier up to date (lists first; --apply does it)
 *   wp twd-sk embeds [--code]            list the embeds (code kept apart from the pages)
 *   wp twd-sk embed-set --label= --kind= --code= [--id=]   save an embed
 *   wp twd-sk embed-delete <id>          delete an embed
 *   wp twd-sk css [--source] [--unused]  the site's own stylesheet: size against its budget, versions, the rules no page uses (0.15.0)
 *   wp twd-sk css-revert <version>       bring back an earlier stylesheet version (0 is none); pages are not touched
 *   wp twd-sk export <page_id> [--section=<n>] [--format=html|brief] [--out=<file>]  a standalone file (html) or a remix brief (brief) for the page or one section; printed, or written to --out (session 7)
 *   wp twd-sk style status|snapshot|apply|revert|swap   restyle a built site: keep the current look, apply a pack or a kit file (dry run first), go back, change a section's layout (0.14.0)
 *   wp twd-sk build [<brief-file>] [--mode=<mode>] [--set=<slug>] [--pack=<slug>] [--pictures=<file>] [--outlines-only] [--dry-run]
 *                                        set the whole site up from a brief, every page a draft
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
	 * [--pictures=<file>]
	 * : A pictures JSON file (the same format as wp twd-sk build --pictures). Every src="pic:NAME" in the
	 *   file is swapped for that named picture's address and alt text before the page is cleaned, with the
	 *   same engine the build uses (0.14.0). A name not in the file is left as the sample picture and listed.
	 *
	 * [--page-slug=<slug>]
	 * : With --pictures: this page's slug, so pictures marked "use on" that page are offered (default: any page).
	 *
	 * [--page-type=<type>]
	 * : With --pictures: the page type (home, about), so its slot picture fills the first sample picture.
	 *
	 * ## EXAMPLES
	 *
	 *     wp twd-sk save 12 home.html --note="First draft"
	 *     wp twd-sk save 12 home.html --pictures=pictures.json --page-slug=home --page-type=home
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

		if ( isset( $assoc_args['pictures'] ) ) {
			$raw  = is_readable( (string) $assoc_args['pictures'] ) ? file_get_contents( (string) $assoc_args['pictures'] ) : false;
			$pics = false === $raw ? null : json_decode( $raw, true );
			if ( ! is_array( $pics ) ) {
				WP_CLI::error( 'Could not read the pictures file as JSON.' );
				return;
			}
			$set = TWD_SK_Pictures::validate( $pics );
			foreach ( $set['errors'] as $e ) {
				WP_CLI::warning( $e );
			}
			$used   = array();
			$placed = TWD_SK_Pictures::place( $html, $set['valid'], isset( $assoc_args['page-slug'] ) ? (string) $assoc_args['page-slug'] : '', isset( $assoc_args['page-type'] ) ? (string) $assoc_args['page-type'] : '', $used );
			$html   = $placed['html'];
			foreach ( $placed['report'] as $line ) {
				WP_CLI::warning( $line );
			}
			WP_CLI::log( 'Pictures placed: ' . ( $used ? implode( ', ', $used ) : 'none by name' ) . '.' );
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
		$r       = TWD_SK_Store::undo( $page_id, array( 'allow_placeholders' => ! empty( $assoc_args['allow-placeholders'] ) ) );
		if ( is_wp_error( $r ) && 'twd_sk_placeholders' === $r->get_error_code() ) {
			WP_CLI::error( $r->get_error_message() . ' Run it again with --allow-placeholders to go back to it.' );
		}
		if ( ! is_wp_error( $r ) && isset( $r['undid'] ) ) {
			WP_CLI::log( 'Undid: ' . $r['undid'] . '. The page now shows version ' . (int) $r['target'] . ' (' . $r['now'] . ').' );
		}
		self::report( $page_id, $r, 'Undone' );
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
	 * Set the whole site up from a site brief: type, style, details, menu, facts, logo, then each page
	 * (written by AI when a key is available, otherwise an outline), the front page, and a check of
	 * every page. The same engine as Settings > Set up site. Every page is a draft. Safe to run again:
	 * a page this build made gets a new version; a page with the same title made some other way is
	 * left alone.
	 *
	 * ## OPTIONS
	 *
	 * [<brief-file>]
	 * : A site brief JSON file, or - for stdin. Leave out to build Home, About and Contact from what
	 *   the setup page already holds.
	 *
	 * [--mode=<mode>]
	 * : therapy or business.
	 *
	 * [--pack=<slug>]
	 * : Style pack (see wp twd-sk pack).
	 *
	 * [--set=<slug>]
	 * : A starting point from starters/sets (for example mobile-hire or counselling). Used when the brief lists no pages.
	 *
	 * [--pictures=<file>]
	 * : A JSON file: {"slots":{"logo":{"id":12},"home_main":{"id":13,"description":"..."}},
	 *   "custom":[{"name":"front-view","id":14,"description":"...","use_on":"any"}]}. IDs are media library pictures.
	 *
	 * [--outlines-only]
	 * : Make outlines only, even when AI is available.
	 *
	 * [--ai]
	 * : Write the pages with AI when it is available (the default). --no-ai makes outlines only, like --outlines-only.
	 *
	 * [--dry-run]
	 * : Show the plan and stop. Nothing is changed.
	 *
	 * ## EXAMPLES
	 *
	 *     wp twd-sk build brief.json --mode=business --pack=grove --dry-run
	 *     wp twd-sk build brief.json --pictures=pictures.json
	 */
	public function build( $args, $assoc_args ) {
		$in = array();
		if ( isset( $args[0] ) ) {
			$file = (string) $args[0];
			$text = '-' === $file ? stream_get_contents( STDIN ) : ( is_readable( $file ) ? file_get_contents( $file ) : false );
			if ( false === $text ) {
				WP_CLI::error( 'Could not read ' . $file . '.' );
				return;
			}
			$in['brief_text'] = $text;
		}
		foreach ( array( 'mode', 'pack', 'set' ) as $k ) {
			if ( isset( $assoc_args[ $k ] ) ) {
				$in[ $k ] = (string) $assoc_args[ $k ];
			}
		}
		if ( isset( $assoc_args['pictures'] ) ) {
			$raw  = is_readable( (string) $assoc_args['pictures'] ) ? file_get_contents( (string) $assoc_args['pictures'] ) : false;
			$pics = false === $raw ? null : json_decode( $raw, true );
			if ( ! is_array( $pics ) ) {
				WP_CLI::error( 'Could not read the pictures file as JSON.' );
				return;
			}
			$in['pictures'] = $pics;
		}
		if ( $in ) {
			$saved = TWD_SK_Build::save_draft( $in );
			foreach ( $saved['errors'] as $e ) {
				WP_CLI::warning( $e );
			}
			if ( isset( $in['mode'] ) && $saved['draft']['mode'] !== $in['mode'] || isset( $in['pack'] ) && $saved['draft']['pack'] !== $in['pack'] || isset( $in['set'] ) && $saved['draft']['set'] !== $in['set'] ) {
				WP_CLI::error( 'Fix the type, starting point or style first. Nothing was built.' );
				return;
			}
		}
		$plan = TWD_SK_Build::plan();
		foreach ( $plan['errors'] as $e ) {
			WP_CLI::warning( $e );
		}
		foreach ( $plan['ignored'] as $e ) {
			WP_CLI::log( 'Note: ' . $e );
		}
		foreach ( $plan['blocking'] as $b ) {
			WP_CLI::warning( 'Hard to read: ' . $b );
		}
		// Outlines only (0.15.0, SK-013): --outlines-only, or --no-ai, which WP-CLI hands over as ai => false.
		$no_ai = isset( $assoc_args['outlines-only'] ) || isset( $assoc_args['no-ai'] )
			|| ( isset( $assoc_args['ai'] ) && in_array( $assoc_args['ai'], array( false, 'false', '0', 0 ), true ) );
		$ai = $plan['ai'] && ! $no_ai;
		WP_CLI::log( 'Plan (' . ( $ai ? 'pages written by AI' : 'outlines only' ) . '):' );
		foreach ( $plan['steps'] as $s ) {
			WP_CLI::log( '  - ' . $s['label'] );
		}
		if ( isset( $assoc_args['dry-run'] ) ) {
			WP_CLI::success( 'Dry run: nothing was changed.' );
			return;
		}
		$failed = 0;
		foreach ( $plan['steps'] as $s ) {
			$r = TWD_SK_Build::run_step( $s['id'], $s['index'], array( 'ai' => $ai ) );
			if ( is_wp_error( $r ) ) {
				$failed++;
				WP_CLI::warning( $s['label'] . ': ' . $r->get_error_message() );
				if ( 'settings' === $s['id'] ) {
					WP_CLI::error( 'The settings step failed, so no pages were made.' );
					return;
				}
				continue;
			}
			foreach ( $r['report'] as $line ) {
				WP_CLI::log( '    ' . $line );
			}
		}
		if ( $failed ) {
			WP_CLI::warning( $failed . ' step' . ( 1 === $failed ? '' : 's' ) . ' failed. Run the command again; it is safe to repeat.' );
			return;
		}
		WP_CLI::success( 'Built. Every page is a draft. Check each one, replace any [PLACEHOLDER], then publish.' );
	}

	/**
	 * Restyle a built site without touching its words, pictures or structure (0.14.0).
	 *
	 * status: the current look and the kept snapshots. snapshot: keep the current look under a name.
	 * apply: a pack name or a style kit file (twd-style-kit/1: pack, tokens, chrome), with --dry-run to see
	 * what would change and the contrast result first; a snapshot is kept before anything changes. revert:
	 * back to the newest snapshot. swap: change every section of one kind on a page from one layout to
	 * another, only where the new layout can hold every piece; saved as a new version (undo as usual).
	 *
	 * ## OPTIONS
	 *
	 * <action>
	 * : status, snapshot, apply, revert or swap.
	 *
	 * [<what>]
	 * : For apply: a pack name or a kit file (leave it out with --header or --footer to change only the layouts). For snapshot: a name. For swap: the page ID.
	 *
	 * [<component>]
	 * : For swap: the section kind (text, image_text, faq ...).
	 *
	 * [<from>]
	 * : For swap: the layout now ("" or standard for the standard one, else the class such as twd-sk-text--aside).
	 *
	 * [<to>]
	 * : For swap: the layout wanted.
	 *
	 * [--header=<layout>]
	 * : With apply: the header layout (bar, centered, split, minimal).
	 *
	 * [--footer=<layout>]
	 * : With apply: the footer layout (columns, band, centered, simple, feature, compact).
	 *
	 * [--wordmark[=<on|off>]]
	 * : With apply: the text wordmark header (the logo as live text, centred on every page) on or off.
	 *
	 * [--scrolled-name[=<on|off>]]
	 * : With apply: keep the name beside the symbol when the front page's text wordmark header is scrolled.
	 *
	 * [--small-line[=<on|off>]]
	 * : With apply: also show the second line under the name in the smaller text wordmark header (every page, and the scrolled front page).
	 *
	 * [--menu-marker=<marker>]
	 * : With apply: how the page you are on is marked in that header's menu (dot, color, bar, none).
	 *
	 * [--dry-run]
	 * : With apply or swap: say what would change and change nothing.
	 *
	 * ## EXAMPLES
	 *
	 *     wp twd-sk style snapshot "before the demo"
	 *     wp twd-sk style apply grove --header=centered --footer=band --dry-run
	 *     wp twd-sk style apply --footer=feature --dry-run
	 *     wp twd-sk style apply --wordmark --menu-marker=dot --dry-run
	 *     wp twd-sk style apply kit.json
	 *     wp twd-sk style revert
	 *     wp twd-sk style swap 12 text standard twd-sk-text--prose --dry-run
	 */
	public function style( $args, $assoc_args ) {
		$action = isset( $args[0] ) ? strtolower( (string) $args[0] ) : 'status';
		if ( 'status' === $action ) {
			$now = TWD_SK_Style::current();
			WP_CLI::log( 'Pack: ' . $now['pack'] . '; overrides: ' . ( $now['tokens'] ? implode( ', ', array_keys( $now['tokens'] ) ) : 'none' ) . '; header: ' . ( '' !== $now['chrome']['header_variant'] ? $now['chrome']['header_variant'] : 'the style\'s own' ) . '; footer: ' . ( '' !== $now['chrome']['footer_variant'] ? $now['chrome']['footer_variant'] : 'the style\'s own' ) . '.' );
			$snaps = TWD_SK_Style::snapshots();
			WP_CLI::log( $snaps ? count( $snaps ) . ' snapshot(s), newest first:' : 'No snapshots.' );
			foreach ( $snaps as $s ) {
				WP_CLI::log( '  ' . $s['at'] . '  ' . $s['pack'] . ( '' !== $s['name'] ? '  "' . $s['name'] . '"' : '' ) );
			}
			return;
		}
		if ( 'snapshot' === $action ) {
			$s = TWD_SK_Style::snapshot( isset( $args[1] ) ? (string) $args[1] : '' );
			WP_CLI::success( 'Kept the current look (' . $s['pack'] . ') as a snapshot. wp twd-sk style revert brings it back.' );
			return;
		}
		if ( 'revert' === $action ) {
			$s = TWD_SK_Style::revert();
			if ( is_wp_error( $s ) ) {
				WP_CLI::error( $s->get_error_message() );
				return;
			}
			WP_CLI::success( 'Back to the look from ' . $s['at'] . ' (' . $s['pack'] . '). If the site uses a page cache, clear it.' );
			return;
		}
		if ( 'apply' === $action ) {
			$what = isset( $args[1] ) ? (string) $args[1] : '';
			if ( '' === $what && ! array_intersect( array( 'header', 'footer', 'wordmark', 'scrolled-name', 'small-line', 'menu-marker' ), array_keys( $assoc_args ) ) ) {
				WP_CLI::error( 'Give a pack name or a kit file, or one of --header, --footer, --wordmark, --scrolled-name, --small-line or --menu-marker to change only that.' );
				return;
			}
			if ( '' === $what ) {
				$data = array(); // Only the layouts change: the current look stays.
			} elseif ( is_readable( $what ) && ! isset( TWD_SK_Packs::packs()[ $what ] ) ) {
				$data = json_decode( (string) file_get_contents( $what ), true );
				if ( ! is_array( $data ) ) {
					WP_CLI::error( 'Could not read the kit file as JSON.' );
					return;
				}
			} else {
				$data = array( 'pack' => $what );
			}
			$chrome = array();
			foreach ( array( 'header', 'footer' ) as $k ) {
				if ( isset( $assoc_args[ $k ] ) ) {
					$chrome[ $k ] = (string) $assoc_args[ $k ];
				}
			}
			foreach ( array( 'wordmark' => 'wordmark', 'scrolled-name' => 'wordmark_scrolled_name', 'small-line' => 'wordmark_small_line' ) as $flag => $setting ) {
				if ( isset( $assoc_args[ $flag ] ) ) {
					$w                 = $assoc_args[ $flag ];
					$chrome[ $setting ] = ( true === $w || in_array( strtolower( (string) $w ), array( 'on', 'yes', 'true', '1' ), true ) )
						? true
						: ( ( false === $w || in_array( strtolower( (string) $w ), array( 'off', 'no', 'false', '0' ), true ) ) ? false : (string) $w );
				}
			}
			if ( isset( $assoc_args['menu-marker'] ) ) {
				$chrome['menu_marker'] = (string) $assoc_args['menu-marker'];
			}
			if ( $chrome ) {
				$data['chrome'] = array_merge( isset( $data['chrome'] ) && is_array( $data['chrome'] ) ? $data['chrome'] : array(), $chrome );
			}
			$kit = TWD_SK_Style::read_kit( $data );
			if ( is_wp_error( $kit ) ) {
				WP_CLI::error( $kit->get_error_message() );
				return;
			}
			foreach ( $kit['notes'] as $n ) {
				WP_CLI::warning( $n );
			}
			$r = TWD_SK_Style::apply( $kit, array( 'dry_run' => isset( $assoc_args['dry-run'] ) ) );
			if ( is_wp_error( $r ) ) {
				WP_CLI::error( $r->get_error_message() );
				return;
			}
			WP_CLI::log( $r['changes'] ? 'Changes:' : 'Nothing to change.' );
			foreach ( $r['changes'] as $c ) {
				WP_CLI::log( '  - ' . $c );
			}
			foreach ( $r['blocking'] as $b ) {
				WP_CLI::warning( 'Hard to read: ' . $b );
			}
			foreach ( $r['contrast']['warnings'] as $w ) {
				WP_CLI::log( '  look at: ' . $w['label'] . ' (' . $w['ratio'] . ':1)' );
			}
			if ( isset( $assoc_args['dry-run'] ) ) {
				WP_CLI::success( 'Dry run: nothing was changed.' );
				return;
			}
			if ( $r['applied'] ) {
				WP_CLI::success( 'Applied. The look before it is kept as a snapshot: wp twd-sk style revert goes back. Clear any page cache.' );
			} else {
				WP_CLI::success( isset( $r['note'] ) ? $r['note'] : 'Nothing was changed.' );
			}
			return;
		}
		if ( 'swap' === $action ) {
			$page_id = self::page_id( isset( $args[1] ) ? $args[1] : '' );
			$comp    = isset( $args[2] ) ? (string) $args[2] : '';
			$from    = isset( $args[3] ) ? (string) $args[3] : '';
			$to      = isset( $args[4] ) ? (string) $args[4] : '';
			$from    = 'standard' === $from ? '' : $from;
			$to      = 'standard' === $to ? '' : $to;
			$html    = TWD_SK_Store::get_current( $page_id );
			if ( '' === $html ) {
				WP_CLI::error( 'Page ' . $page_id . ' has no stored content.' );
				return;
			}
			$r = TWD_SK_Style::swap( $html, $comp, $from, $to );
			if ( is_wp_error( $r ) ) {
				WP_CLI::error( $r->get_error_message() );
				return;
			}
			foreach ( $r['cannot'] as $c ) {
				WP_CLI::warning( $c );
			}
			WP_CLI::log( $r['changed'] . ' section(s) would change layout.' );
			if ( isset( $assoc_args['dry-run'] ) || 0 === $r['changed'] ) {
				WP_CLI::success( 'Nothing was changed.' );
				return;
			}
			self::report( $page_id, TWD_SK_Store::save( $page_id, $r['html'], array( 'base_version' => TWD_SK_Store::get_current_version_id( $page_id ), 'note' => 'Layout change: ' . $comp . ' to ' . ( '' === $to ? 'standard' : $to ) ) ), 'Saved' );
			return;
		}
		WP_CLI::error( 'Say status, snapshot, apply, revert or swap.' );
	}

	/**
	 * Show the main colours of the logo (or another picture) and the style colours made from them,
	 * each checked so text stays easy to read. Saves nothing unless --apply is given.
	 *
	 * ## OPTIONS
	 *
	 * [<picture-id>]
	 * : A picture in the media library. Default: the logo in the site details.
	 *
	 * [--apply]
	 * : Save the colours on top of the current style.
	 *
	 * ## EXAMPLES
	 *
	 *     wp twd-sk brand
	 *     wp twd-sk brand 42 --apply
	 */
	public function brand( $args, $assoc_args ) {
		$id = isset( $args[0] ) ? (int) $args[0] : (int) TWD_SK_Profile::get()['logo_id'];
		if ( $id <= 0 ) {
			WP_CLI::error( 'There is no logo in the site details. Give a picture ID.' );
			return;
		}
		$out = TWD_SK_Brand::from_attachment( $id );
		if ( is_wp_error( $out ) ) {
			WP_CLI::error( $out->get_error_message() );
			return;
		}
		WP_CLI::log( 'Main colours: ' . implode( ', ', $out['colours'] ) );
		foreach ( $out['tokens'] as $name => $value ) {
			WP_CLI::log( '  ' . $name . ': ' . $value );
		}
		if ( $out['dropped'] ) {
			WP_CLI::log( 'Kept the style\'s own for: ' . implode( ', ', $out['dropped'] ) );
		}
		if ( isset( $assoc_args['apply'] ) ) {
			$r = TWD_SK_Site::apply( null, array_merge( TWD_SK_Packs::overrides(), $out['tokens'] ) );
			if ( is_wp_error( $r ) ) {
				WP_CLI::error( $r->get_error_message() );
				return;
			}
			WP_CLI::success( 'Saved. Reset the style in the Site tab to undo.' );
		}
	}

	/**
	 * Check the whole site before launch: example text still to replace, search wording, the front page,
	 * the privacy policy, the site details, search engine visibility, https and more. Read only, unless
	 * --publish is given, which publishes the pages with nothing left to replace.
	 *
	 * ## OPTIONS
	 *
	 * [--publish]
	 * : Publish every kit page that is ready.
	 *
	 * [--tidy]
	 * : Move WordPress's own "Sample Page" and "Hello world!" post to the bin (not deleted for good).
	 *
	 * ## EXAMPLES
	 *
	 *     wp twd-sk launch-check
	 *     wp twd-sk launch-check --publish
	 *     wp twd-sk launch-check --tidy
	 *
	 * @subcommand launch-check
	 */
	public function launch_check( $args, $assoc_args ) {
		if ( isset( $assoc_args['tidy'] ) && ! get_current_user_id() ) {
			// The command line has no signed-in user unless --user says so; the bin needs one (0.15.0, SK-016).
			WP_CLI::warning( 'Tidy needs a signed-in administrator: run it again with --user=ID (an administrator\'s user ID or login). The check below still runs.' );
			unset( $assoc_args['tidy'] );
		}
		if ( isset( $assoc_args['tidy'] ) ) {
			$t = TWD_SK_Launch::tidy();
			foreach ( $t['trashed'] as $title ) {
				WP_CLI::success( 'Moved "' . $title . '" to the bin.' );
			}
			foreach ( $t['refused'] as $r ) {
				WP_CLI::warning( $r );
			}
			if ( ! $t['trashed'] && ! $t['refused'] ) {
				WP_CLI::log( 'Nothing of WordPress\'s own left to tidy.' );
			}
		}
		$check = TWD_SK_Launch::check();
		foreach ( TWD_SK_Launch::lines( $check ) as $line ) {
			WP_CLI::log( $line );
		}
		foreach ( $check['pages'] as $p ) {
			WP_CLI::log( '  ' . ( $p['ready'] ? 'ready    ' : 'not ready' ) . ' ' . $p['status'] . ' "' . $p['title'] . '"' . ( $p['ready'] ? '' : ': ' . $p['reason'] ) );
		}
		if ( isset( $assoc_args['publish'] ) ) {
			$ready = array();
			foreach ( $check['pages'] as $p ) {
				if ( $p['ready'] && 'publish' !== $p['status'] ) {
					$ready[] = $p['id'];
				}
			}
			if ( ! $ready ) {
				WP_CLI::log( 'Nothing to publish.' );
				return;
			}
			$out = TWD_SK_Launch::publish( $ready );
			foreach ( $out['published'] as $p ) {
				WP_CLI::success( 'Published "' . $p['title'] . '".' );
			}
			foreach ( $out['refused'] as $p ) {
				WP_CLI::warning( '"' . $p['title'] . '" was not published: ' . $p['reason'] );
			}
		}
	}

	/**
	 * Show the site type, or switch it. Therapy is the default. The type changes wording, the AI
	 * instructions, the search details type and which fields show. It never rewrites a page.
	 *
	 * ## OPTIONS
	 *
	 * [<mode>]
	 * : therapy or business. Leave out to show the current type.
	 *
	 * ## EXAMPLES
	 *
	 *     wp twd-sk mode
	 *     wp twd-sk mode business
	 */
	public function mode( $args, $assoc_args ) {
		if ( isset( $args[0] ) ) {
			$saved = TWD_SK_Mode::set( strtolower( (string) $args[0] ) );
			if ( is_wp_error( $saved ) ) {
				WP_CLI::error( $saved->get_error_message() );
				return;
			}
			WP_CLI::success( 'Site type is now ' . $saved . '. Pages already written keep their wording.' );
		}
		$line = 'Site type: ' . TWD_SK_Mode::stored() . '.';
		if ( TWD_SK_Mode::get() !== TWD_SK_Mode::stored() ) {
			$line .= ' Safe mode is on, so therapy wording is used until it is turned off.';
		}
		WP_CLI::log( $line );
	}

	/**
	 * Print the Tell us notes kept on this site, newest first. Read only. Prints no web addresses.
	 * Ideas are included (they are yours); use --type=snag for client notes only.
	 *
	 * ## OPTIONS
	 *
	 * [--status=<status>]
	 * : Only notes with this status: new, seen, done or parked.
	 *
	 * [--type=<type>]
	 * : Only snag (from a client) or idea (yours).
	 *
	 * [--format=<format>]
	 * : json (default) or markdown.
	 *
	 * ## EXAMPLES
	 *
	 *     wp twd-sk snags --status=new --format=markdown
	 *     wp twd-sk snags --format=json > notes.json
	 */
	public function snags( $args, $assoc_args ) {
		$opts = array( 'include_ideas' => true );
		foreach ( array( 'status', 'type' ) as $key ) {
			if ( isset( $assoc_args[ $key ] ) ) {
				$opts[ $key ] = (string) $assoc_args[ $key ];
			}
		}
		if ( isset( $opts['status'] ) && ! isset( TWD_SK_Snags::statuses()[ $opts['status'] ] ) ) {
			WP_CLI::error( 'The status must be new, seen, done or parked.' );
			return;
		}
		if ( isset( $opts['type'] ) && ! in_array( $opts['type'], TWD_SK_Snags::types(), true ) ) {
			WP_CLI::error( 'The type must be snag or idea.' );
			return;
		}
		$notes  = TWD_SK_Snags::find( $opts );
		$format = isset( $assoc_args['format'] ) ? strtolower( (string) $assoc_args['format'] ) : 'json';
		if ( 'markdown' === $format ) {
			WP_CLI::line( rtrim( TWD_SK_Snags::markdown( $notes ) ) );
			return;
		}
		if ( 'json' !== $format ) {
			WP_CLI::error( 'The format must be json or markdown.' );
			return;
		}
		$rows = array();
		foreach ( $notes as $n ) {
			$row            = TWD_SK_Snags::present( $n, false );
			$row['user_id'] = $n['user_id'];
			$rows[]         = $row;
		}
		WP_CLI::line( wp_json_encode( $rows ) );
	}

	/**
	 * Set the status of one Tell us note.
	 *
	 * ## OPTIONS
	 *
	 * <id>
	 * : The note number (the id shown by the snags command).
	 *
	 * <status>
	 * : new, seen, done or parked.
	 *
	 * @subcommand snag-status
	 */
	public function snag_status( $args, $assoc_args ) {
		if ( count( $args ) < 2 ) {
			WP_CLI::error( 'Give the note number and a status: wp twd-sk snag-status 12 done' );
			return;
		}
		$saved = TWD_SK_Snags::set_status( (int) $args[0], strtolower( (string) $args[1] ) );
		if ( is_wp_error( $saved ) ) {
			WP_CLI::error( $saved->get_error_message() );
			return;
		}
		WP_CLI::success( 'Note ' . $saved['id'] . ' is now ' . $saved['status'] . '.' );
	}

	/**
	 * Show, or switch, the Tell us module. It stays off in safe mode whatever this says.
	 *
	 * ## EXAMPLES
	 *
	 *     wp twd-sk snags-setting
	 *     wp twd-sk snags-setting on
	 *
	 * @subcommand snags-setting
	 */
	public function snags_setting( $args, $assoc_args ) {
		if ( isset( $args[0] ) ) {
			$word = strtolower( (string) $args[0] );
			if ( 'on' !== $word && 'off' !== $word ) {
				WP_CLI::error( 'Use on or off.' );
				return;
			}
			$cur   = TWD_SK_Snags::settings();
			$saved = TWD_SK_Snags::save_settings( array(
				'enabled'  => 'on' === $word,
				'months'   => $cur['months'],
				'email_on' => $cur['email_on'],
				'email'    => $cur['email'],
			) );
			if ( is_wp_error( $saved ) ) {
				WP_CLI::error( $saved->get_error_message() );
				return;
			}
		}
		$cur = TWD_SK_Snags::settings();
		WP_CLI::log( 'Tell us: ' . ( $cur['enabled'] ? 'on' : 'off' ) . ( $cur['safe_mode'] ? ' (safe mode is on, so it is switched off for now)' : '' ) . '. Notes are kept for ' . $cur['months'] . ' months. Email notification: ' . ( $cur['email_on'] ? 'on' : 'off' ) . '.' );
	}

	/**
	 * Bring pages built earlier up to date: add what a later version of the kit added to a section (for
	 * example the line a Resources section shows while there are no articles). Lists what could be added
	 * and changes nothing, unless --apply is given. Each page becomes its own version, so Undo puts it back.
	 *
	 * ## OPTIONS
	 *
	 * [--apply]
	 * : Do it.
	 *
	 * [--page=<id>]
	 * : Only this page.
	 *
	 * ## EXAMPLES
	 *
	 *     wp twd-sk upgrade
	 *     wp twd-sk upgrade --apply
	 */
	public function upgrade( $args, $assoc_args ) {
		$only = isset( $assoc_args['page'] ) ? array( (int) $assoc_args['page'] ) : null;
		$scan = TWD_SK_Upgrade::scan();
		if ( null !== $only ) {
			$scan = array_values( array_filter( $scan, function ( $p ) use ( $only ) {
				return in_array( $p['id'], $only, true );
			} ) );
		}
		if ( ! $scan ) {
			WP_CLI::success( 'Every page is up to date.' );
			return;
		}
		foreach ( $scan as $p ) {
			WP_CLI::log( '#' . $p['id'] . ' "' . $p['title'] . '" (' . $p['status'] . ')' );
			foreach ( $p['items'] as $item ) {
				WP_CLI::log( '    ' . $item['label'] . ( $item['count'] > 1 ? ' (' . $item['count'] . ' places)' : '' ) );
			}
		}
		if ( ! isset( $assoc_args['apply'] ) ) {
			WP_CLI::log( 'Nothing was changed. Add --apply to bring these pages up to date.' );
			return;
		}
		$done = TWD_SK_Upgrade::run( $only );
		foreach ( $done['updated'] as $u ) {
			WP_CLI::success( 'Updated "' . $u['title'] . '".' );
		}
		foreach ( $done['failed'] as $f ) {
			WP_CLI::warning( '"' . $f['title'] . '" was not changed: ' . $f['reason'] );
		}
	}

	/**
	 * List the embeds saved on this site (code kept apart from the pages), as JSON. The code is left out
	 * unless --code is given.
	 *
	 * ## OPTIONS
	 *
	 * [--code]
	 * : Include each embed's code.
	 *
	 * ## EXAMPLES
	 *
	 *     wp twd-sk embeds
	 */
	/**
	 * The site's own stylesheet (Base, Adapt, Replace): its size against the budget, the classes it defines,
	 * its versions, and with --unused the rules no page uses. --source prints the stylesheet itself.
	 *
	 * ## OPTIONS
	 *
	 * [--source]
	 * : Print the stylesheet source after the summary.
	 *
	 * [--unused]
	 * : List the rules no kit page uses.
	 */
	public function css( $args, $assoc_args ) {
		$status = TWD_SK_Css::status();
		WP_CLI::line( $status['summary'] );
		if ( $status['classes'] ) {
			WP_CLI::line( 'Classes: ' . implode( ', ', $status['classes'] ) );
		}
		foreach ( $status['versions'] as $v ) {
			WP_CLI::line( sprintf( '  v%d  %s  page %d  %s  %s', $v['v'], $v['at'], $v['page'], $v['bytes'] . ' bytes', $v['note'] ) );
		}
		if ( isset( $assoc_args['unused'] ) ) {
			$unused = TWD_SK_Css::unused_rules( TWD_SK_Css::pages_html() );
			WP_CLI::line( $unused ? 'Rules no page uses: ' . implode( '; ', $unused ) : 'Every rule is used by at least one page.' );
		}
		if ( isset( $assoc_args['source'] ) ) {
			WP_CLI::line( TWD_SK_Css::current()['source'] );
		}
	}

	/**
	 * Export the page, or one of its sections, as a standalone HTML file or a remix brief (markdown). Nothing is changed.
	 *
	 * ## OPTIONS
	 *
	 * <page_id>
	 * : The page.
	 *
	 * [--section=<n>]
	 * : One section only, counted from 1 (as the Visual editor numbers them).
	 *
	 * [--format=<format>]
	 * : html (the default): one file that opens on its own; brief: a markdown file for an AI, with a blank copy.
	 *
	 * [--out=<file>]
	 * : Write the file there instead of printing it. The file holds the client's words: keep it in the private repository.
	 */
	public function export( $args, $assoc_args ) {
		$page_id = self::page_id( isset( $args[0] ) ? $args[0] : '' );
		$section = isset( $assoc_args['section'] ) ? (int) $assoc_args['section'] : 0;
		$format  = isset( $assoc_args['format'] ) ? (string) $assoc_args['format'] : 'html';
		if ( 'brief' === $format ) {
			$out = TWD_SK_Export::brief( $page_id, $section );
		} elseif ( 'html' === $format ) {
			$out = TWD_SK_Export::standalone( $page_id, $section );
		} else {
			WP_CLI::error( 'The format is html or brief.' );
			return;
		}
		if ( is_wp_error( $out ) ) {
			WP_CLI::error( $out->get_error_message() );
			return;
		}
		if ( isset( $assoc_args['out'] ) && '' !== (string) $assoc_args['out'] ) {
			$path = (string) $assoc_args['out'];
			if ( false === file_put_contents( $path, $out['content'] ) ) {
				WP_CLI::error( 'Could not write ' . $path . '.' );
				return;
			}
			WP_CLI::success( 'Wrote ' . $path . ' (' . $out['bytes'] . ' bytes, ' . count( $out['pictures'] ) . ' picture' . ( 1 === count( $out['pictures'] ) ? '' : 's' ) . ' linked from the site). It holds the client\'s words: keep it in the private repository.' );
			return;
		}
		WP_CLI::line( $out['content'] );
	}

	/**
	 * Bring back an earlier version of the site stylesheet as a new version. 0 is the empty stylesheet.
	 * Pages are not touched: a page's own Undo takes its HTML back.
	 *
	 * ## OPTIONS
	 *
	 * <version>
	 * : The stylesheet version number to go back to.
	 *
	 * @subcommand css-revert
	 */
	public function css_revert( $args, $assoc_args ) {
		if ( ! isset( $args[0] ) || ! is_numeric( $args[0] ) ) {
			WP_CLI::error( 'Say which stylesheet version to go back to (wp twd-sk css lists them).' );
			return;
		}
		$out = TWD_SK_Css::revert_to( (int) $args[0], 'back to stylesheet version ' . (int) $args[0] . ' from the command line' );
		if ( is_wp_error( $out ) ) {
			WP_CLI::error( $out->get_error_message() );
			return;
		}
		WP_CLI::success( $out['unchanged'] ? 'The stylesheet already has those rules (version ' . $out['v'] . ').' : 'The stylesheet is version ' . $out['v'] . ' now, with the rules of version ' . (int) $args[0] . '.' );
	}

	public function embeds( $args, $assoc_args ) {
		$rows = array();
		foreach ( TWD_SK_Embed::listing() as $r ) {
			if ( ! isset( $assoc_args['code'] ) ) {
				unset( $r['code'] );
			}
			$rows[] = $r;
		}
		WP_CLI::line( wp_json_encode( $rows ) );
	}

	/**
	 * Save an embed: a shortcode or a piece of HTML code shown in an Embed section. Without --id it
	 * makes a new one and prints its number; with --id it replaces that embed. Add it to a page from
	 * the pop-up (Site tab, Embeds, Add to this page).
	 *
	 * ## OPTIONS
	 *
	 * --label=<label>
	 * : A short name, for example Booking calendar.
	 *
	 * --kind=<kind>
	 * : shortcode or html.
	 *
	 * --code=<code>
	 * : The shortcode or the code, exactly as given.
	 *
	 * [--id=<id>]
	 * : The number of the embed to replace.
	 *
	 * ## EXAMPLES
	 *
	 *     wp twd-sk embed-set --label="Booking calendar" --kind=shortcode --code='[booking_calendar]'
	 *
	 * @subcommand embed-set
	 */
	public function embed_set( $args, $assoc_args ) {
		$saved = TWD_SK_Embed::save( array(
			'label' => isset( $assoc_args['label'] ) ? (string) $assoc_args['label'] : '',
			'kind'  => isset( $assoc_args['kind'] ) ? (string) $assoc_args['kind'] : '',
			'code'  => isset( $assoc_args['code'] ) ? (string) $assoc_args['code'] : '',
		), isset( $assoc_args['id'] ) ? (int) $assoc_args['id'] : 0, 0 );
		if ( is_wp_error( $saved ) ) {
			WP_CLI::error( $saved->get_error_message() );
			return;
		}
		WP_CLI::success( 'Saved embed ' . (int) $saved['id'] . '.' );
	}

	/**
	 * Delete an embed. Pages that show it will have a gap, and cannot be published, until it is replaced.
	 *
	 * ## OPTIONS
	 *
	 * <id>
	 * : The number of the embed (shown by the embeds command).
	 *
	 * @subcommand embed-delete
	 */
	public function embed_delete( $args, $assoc_args ) {
		$id = isset( $args[0] ) ? (int) $args[0] : 0;
		if ( $id <= 0 || ! TWD_SK_Embed::delete( $id ) ) {
			WP_CLI::error( 'There is no embed with that number.' );
			return;
		}
		$use = TWD_SK_Embed::usage();
		WP_CLI::success( 'Deleted embed ' . $id . '.' . ( ! empty( $use[ $id ] ) ? ' ' . count( $use[ $id ] ) . ' page(s) still point at it.' : '' ) );
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
		WP_CLI::log( 'Only needed with Elementor Pro. On the Hello theme Site Kit places the header and footer itself (Site tab, Header and footer).' );
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
		// A page on the kit template draws the stored HTML itself and never holds the shortcode (0.15.0, SK-015).
		if ( class_exists( 'TWD_SK_Template' ) && TWD_SK_Template::uses_template( $page_id ) ) {
			return;
		}
		$content   = (string) get_post_field( 'post_content', $page_id );
		$elementor = get_post_meta( $page_id, '_elementor_data', true );
		if ( is_string( $elementor ) ) {
			$content .= $elementor;
		}
		if ( false === strpos( $content, '[twd_page' ) ) {
			WP_CLI::warning( 'Page ' . $page_id . ' does not seem to contain the [twd_page] shortcode yet, so the content will not show. Add a Shortcode widget with [twd_page] to it, or switch the page to the TWD Kit Page template.' );
		}
	}
}
