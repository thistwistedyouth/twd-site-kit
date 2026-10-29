<?php
/**
 * TWD_SK_REST: the routes the front-end editor talks to (namespace twd-site-kit/v1).
 *
 * Rules for every route:
 *  - A valid X-WP-Nonce header is required (core checks it, and it is checked again
 *    here so a missing or wrong nonce is refused even without core's help).
 *  - The caller must be signed in and hold the capability named on the route.
 *  - Every write goes through TWD_SK_Store, which runs TWD_SK_Sanitizer. Nothing
 *    here writes HTML any other way.
 *  - Request size and request rate are limited per user.
 *  - No AI key, no outbound request: this plugin never calls an AI service.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class TWD_SK_REST {

	const ROUTE_NS      = 'twd-site-kit/v1';
	const MAX_BODY      = 300 * 1024; // 300 KB: the 200 KB page limit plus JSON overhead.
	const NONCE_HEADER  = 'X-WP-Nonce';

	/** bucket => array( requests allowed, window in seconds ). */
	private static function limits() {
		return array(
			'read'    => array( 120, 60 ),
			'preview' => array( 30, 60 ),
			'write'   => array( 30, 60 ),
			'create'  => array( 10, 3600 ),
			'ai'      => array( 10, 600 ),
			// The site setup build: one request per page, each may spend the key once.
			'build'   => array( 60, 600 ),
			// The one-box planner's changes: one request per change, each may spend the key once.
			'plan'    => array( 40, 600 ),
		);
	}

	public static function init() {
		add_action( 'rest_api_init', array( __CLASS__, 'register_routes' ) );
	}

	public static function register_routes() {
		$id = '(?P<id>\d+)';

		register_rest_route( self::ROUTE_NS, '/pages', array(
			'methods'             => 'POST',
			'callback'            => array( __CLASS__, 'post_create_page' ),
			'permission_callback' => array( __CLASS__, 'can_create_page' ),
		) );
		// The editing tools switch (0.14.1): a per-person preference, so it works in safe mode too.
		register_rest_route( self::ROUTE_NS, '/editor/tools', array(
			'methods'             => 'POST',
			'callback'            => array( __CLASS__, 'post_editor_tools' ),
			'permission_callback' => array( __CLASS__, 'can_use_editor' ),
		) );
		// Where the Edit with AI window sits (0.15.0): a per-person preference, like the switch.
		register_rest_route( self::ROUTE_NS, '/editor/window', array(
			'methods'             => 'POST',
			'callback'            => array( __CLASS__, 'post_editor_window' ),
			'permission_callback' => array( __CLASS__, 'can_use_editor' ),
		) );
		if ( ! TWD_SK_Safe::on() ) {
			self::register_site_routes();
		}

		register_rest_route( self::ROUTE_NS, '/pages/' . $id . '/info', array(
			'methods'             => 'GET',
			'callback'            => array( __CLASS__, 'get_info' ),
			'permission_callback' => array( __CLASS__, 'can_read_page' ),
		) );
		if ( ! TWD_SK_Safe::on() ) {
			register_rest_route( self::ROUTE_NS, '/pages/' . $id . '/seo', array(
				array(
					'methods'             => 'GET',
					'callback'            => array( __CLASS__, 'get_seo' ),
					'permission_callback' => array( __CLASS__, 'can_read_page' ),
				),
				array(
					'methods'             => 'POST',
					'callback'            => array( __CLASS__, 'post_seo' ),
					'permission_callback' => array( __CLASS__, 'can_write_page' ),
				),
			) );
		}
		if ( ! TWD_SK_Safe::on() ) {
			register_rest_route( self::ROUTE_NS, '/pages/generate', array(
				'methods'             => 'POST',
				'callback'            => array( __CLASS__, 'post_generate' ),
				'permission_callback' => array( __CLASS__, 'can_generate_page' ),
			) );
			register_rest_route( self::ROUTE_NS, '/pages/recipe-prompt', array(
				'methods'             => 'POST',
				'callback'            => array( __CLASS__, 'post_recipe_prompt' ),
				'permission_callback' => array( __CLASS__, 'can_create_page' ),
			) );
			register_rest_route( self::ROUTE_NS, '/pages/' . $id . '/sections', array(
				'methods'             => 'GET',
				'callback'            => array( __CLASS__, 'get_sections' ),
				'permission_callback' => array( __CLASS__, 'can_read_page' ),
			) );
			register_rest_route( self::ROUTE_NS, '/pages/' . $id . '/add-section', array(
				'methods'             => 'POST',
				'callback'            => array( __CLASS__, 'post_add_section' ),
				'permission_callback' => array( __CLASS__, 'can_add_section' ),
			) );
			register_rest_route( self::ROUTE_NS, '/pages/' . $id . '/remix', array(
				'methods'             => 'POST',
				'callback'            => array( __CLASS__, 'post_remix' ),
				'permission_callback' => array( __CLASS__, 'can_remix_page' ),
			) );
			// Words and pictures changed in place on the page (0.10.0).
			register_rest_route( self::ROUTE_NS, '/pages/' . $id . '/inline', array(
				'methods'             => 'POST',
				'callback'            => array( __CLASS__, 'post_inline' ),
				'permission_callback' => array( __CLASS__, 'can_write_page' ),
			) );
			// Add a section and move a section on the page itself, no AI (0.14.1).
			register_rest_route( self::ROUTE_NS, '/pages/' . $id . '/structure', array(
				'methods'             => 'POST',
				'callback'            => array( __CLASS__, 'post_structure' ),
				'permission_callback' => array( __CLASS__, 'can_write_page' ),
			) );
			// The one-box planner (0.10.0): a plan (one AI call), then one change per request.
			register_rest_route( self::ROUTE_NS, '/pages/' . $id . '/plan', array(
				'methods'             => 'POST',
				'callback'            => array( __CLASS__, 'post_plan' ),
				'permission_callback' => array( __CLASS__, 'can_remix_page' ),
			) );
			register_rest_route( self::ROUTE_NS, '/pages/' . $id . '/plan/step', array(
				'methods'             => 'POST',
				'callback'            => array( __CLASS__, 'post_plan_step' ),
				'permission_callback' => array( __CLASS__, 'can_plan_step' ),
			) );
		}
		register_rest_route( self::ROUTE_NS, '/pages/' . $id . '/status', array(
			'methods'             => 'POST',
			'callback'            => array( __CLASS__, 'post_status' ),
			'permission_callback' => array( __CLASS__, 'can_publish_page' ),
		) );
		register_rest_route( self::ROUTE_NS, '/pages/' . $id . '/template', array(
			'methods'             => 'POST',
			'callback'            => array( __CLASS__, 'post_template' ),
			'permission_callback' => array( __CLASS__, 'can_write_page' ),
		) );
		register_rest_route( self::ROUTE_NS, '/pages/' . $id . '/prompt', array(
			'methods'             => 'GET',
			'callback'            => array( __CLASS__, 'get_prompt' ),
			'permission_callback' => array( __CLASS__, 'can_read_page' ),
		) );
		register_rest_route( self::ROUTE_NS, '/pages/' . $id . '/versions', array(
			'methods'             => 'GET',
			'callback'            => array( __CLASS__, 'get_versions' ),
			'permission_callback' => array( __CLASS__, 'can_read_page' ),
		) );
		register_rest_route( self::ROUTE_NS, '/pages/' . $id . '/preview', array(
			'methods'             => 'POST',
			'callback'            => array( __CLASS__, 'post_preview' ),
			'permission_callback' => array( __CLASS__, 'can_preview_page' ),
		) );
		register_rest_route( self::ROUTE_NS, '/pages/' . $id . '/preview/discard', array(
			'methods'             => 'POST',
			'callback'            => array( __CLASS__, 'post_discard' ),
			'permission_callback' => array( __CLASS__, 'can_preview_page' ),
		) );
		register_rest_route( self::ROUTE_NS, '/pages/' . $id . '/apply', array(
			'methods'             => 'POST',
			'callback'            => array( __CLASS__, 'post_apply' ),
			'permission_callback' => array( __CLASS__, 'can_write_page' ),
		) );
		register_rest_route( self::ROUTE_NS, '/pages/' . $id . '/undo', array(
			'methods'             => 'POST',
			'callback'            => array( __CLASS__, 'post_undo' ),
			'permission_callback' => array( __CLASS__, 'can_write_page' ),
		) );
		register_rest_route( self::ROUTE_NS, '/pages/' . $id . '/restore', array(
			'methods'             => 'POST',
			'callback'            => array( __CLASS__, 'post_restore' ),
			'permission_callback' => array( __CLASS__, 'can_write_page' ),
		) );
	}

	/** Routes for the Site tab (administrators only). Not registered in safe mode. */
	private static function register_site_routes() {
		register_rest_route( self::ROUTE_NS, '/site', array(
			'methods'             => 'GET',
			'callback'            => array( __CLASS__, 'get_site' ),
			'permission_callback' => array( __CLASS__, 'can_read_site' ),
		) );
		register_rest_route( self::ROUTE_NS, '/site/style', array(
			'methods'             => 'POST',
			'callback'            => array( __CLASS__, 'post_site_style' ),
			'permission_callback' => array( __CLASS__, 'can_manage_site' ),
		) );
		register_rest_route( self::ROUTE_NS, '/site/style/reset', array(
			'methods'             => 'POST',
			'callback'            => array( __CLASS__, 'post_site_reset' ),
			'permission_callback' => array( __CLASS__, 'can_manage_site' ),
		) );
		register_rest_route( self::ROUTE_NS, '/site/profile', array(
			array(
				'methods'             => 'GET',
				'callback'            => array( __CLASS__, 'get_site_profile' ),
				'permission_callback' => array( __CLASS__, 'can_read_site' ),
			),
			array(
				'methods'             => 'POST',
				'callback'            => array( __CLASS__, 'post_site_profile' ),
				'permission_callback' => array( __CLASS__, 'can_manage_site' ),
			),
		) );
		register_rest_route( self::ROUTE_NS, '/site/chrome', array(
			'methods'             => 'POST',
			'callback'            => array( __CLASS__, 'post_site_chrome' ),
			'permission_callback' => array( __CLASS__, 'can_manage_site' ),
		) );
		register_rest_route( self::ROUTE_NS, '/site/setup', array(
			array(
				'methods'             => 'GET',
				'callback'            => array( __CLASS__, 'get_site_setup' ),
				'permission_callback' => array( __CLASS__, 'can_read_site' ),
			),
			array(
				'methods'             => 'POST',
				'callback'            => array( __CLASS__, 'post_site_setup' ),
				'permission_callback' => array( __CLASS__, 'can_manage_site' ),
			),
		) );
		register_rest_route( self::ROUTE_NS, '/site/facts', array(
			array(
				'methods'             => 'GET',
				'callback'            => array( __CLASS__, 'get_site_facts' ),
				'permission_callback' => array( __CLASS__, 'can_read_site' ),
			),
			array(
				'methods'             => 'POST',
				'callback'            => array( __CLASS__, 'post_site_facts' ),
				'permission_callback' => array( __CLASS__, 'can_manage_site' ),
			),
		) );
		// Box, Border and Padding for every editor, or administrators only (0.15.0).
		register_rest_route( self::ROUTE_NS, '/site/looks', array(
			array(
				'methods'             => 'GET',
				'callback'            => array( __CLASS__, 'get_site_looks' ),
				'permission_callback' => array( __CLASS__, 'can_read_site' ),
			),
			array(
				'methods'             => 'POST',
				'callback'            => array( __CLASS__, 'post_site_looks' ),
				'permission_callback' => array( __CLASS__, 'can_manage_site' ),
			),
		) );
		register_rest_route( self::ROUTE_NS, '/site/mode', array(
			array(
				'methods'             => 'GET',
				'callback'            => array( __CLASS__, 'get_site_mode' ),
				'permission_callback' => array( __CLASS__, 'can_read_site' ),
			),
			array(
				'methods'             => 'POST',
				'callback'            => array( __CLASS__, 'post_site_mode' ),
				'permission_callback' => array( __CLASS__, 'can_manage_site' ),
			),
		) );
		register_rest_route( self::ROUTE_NS, '/site/launch', array(
			'methods'             => 'GET',
			'callback'            => array( __CLASS__, 'get_site_launch' ),
			'permission_callback' => array( __CLASS__, 'can_read_site' ),
		) );
		// Embeds: code kept apart from the page (0.15.0). Administrators only.
		register_rest_route( self::ROUTE_NS, '/site/embeds', array(
			array(
				'methods'             => 'GET',
				'callback'            => array( __CLASS__, 'get_site_embeds' ),
				'permission_callback' => array( __CLASS__, 'can_read_site' ),
			),
			array(
				'methods'             => 'POST',
				'callback'            => array( __CLASS__, 'post_site_embeds' ),
				'permission_callback' => array( __CLASS__, 'can_manage_site' ),
			),
		) );
		register_rest_route( self::ROUTE_NS, '/site/embeds/(?P<eid>\d+)/delete', array(
			'methods'             => 'POST',
			'callback'            => array( __CLASS__, 'post_site_embed_delete' ),
			'permission_callback' => array( __CLASS__, 'can_manage_site' ),
		) );
		// Bring pages built earlier up to date (0.15.0). Administrators only.
		register_rest_route( self::ROUTE_NS, '/site/upgrades', array(
			'methods'             => 'GET',
			'callback'            => array( __CLASS__, 'get_site_upgrades' ),
			'permission_callback' => array( __CLASS__, 'can_read_site' ),
		) );
		register_rest_route( self::ROUTE_NS, '/site/upgrades/apply', array(
			'methods'             => 'POST',
			'callback'            => array( __CLASS__, 'post_site_upgrades_apply' ),
			'permission_callback' => array( __CLASS__, 'can_manage_site' ),
		) );
		// Site changes (0.15.0): the settings history with Undo and Restore. Administrators only.
		register_rest_route( self::ROUTE_NS, '/site/history', array(
			'methods'             => 'GET',
			'callback'            => array( __CLASS__, 'get_site_history' ),
			'permission_callback' => array( __CLASS__, 'can_read_site' ),
		) );
		register_rest_route( self::ROUTE_NS, '/site/history/undo', array(
			'methods'             => 'POST',
			'callback'            => array( __CLASS__, 'post_site_history_undo' ),
			'permission_callback' => array( __CLASS__, 'can_manage_site' ),
		) );
		register_rest_route( self::ROUTE_NS, '/site/history/restore', array(
			'methods'             => 'POST',
			'callback'            => array( __CLASS__, 'post_site_history_restore' ),
			'permission_callback' => array( __CLASS__, 'can_manage_site' ),
		) );
		register_rest_route( self::ROUTE_NS, '/site/launch/publish', array(
			'methods'             => 'POST',
			'callback'            => array( __CLASS__, 'post_site_launch_publish' ),
			'permission_callback' => array( __CLASS__, 'can_manage_site' ),
		) );
		register_rest_route( self::ROUTE_NS, '/site/launch/tidy', array(
			'methods'             => 'POST',
			'callback'            => array( __CLASS__, 'post_site_launch_tidy' ),
			'permission_callback' => array( __CLASS__, 'can_manage_site' ),
		) );
		register_rest_route( self::ROUTE_NS, '/site/brand', array(
			'methods'             => 'GET',
			'callback'            => array( __CLASS__, 'get_site_brand' ),
			'permission_callback' => array( __CLASS__, 'can_read_site' ),
		) );
		register_rest_route( self::ROUTE_NS, '/site/build', array(
			'methods'             => 'GET',
			'callback'            => array( __CLASS__, 'get_site_build' ),
			'permission_callback' => array( __CLASS__, 'can_read_site' ),
		) );
		register_rest_route( self::ROUTE_NS, '/site/build/draft', array(
			'methods'             => 'POST',
			'callback'            => array( __CLASS__, 'post_site_build_draft' ),
			'permission_callback' => array( __CLASS__, 'can_manage_site' ),
		) );
		register_rest_route( self::ROUTE_NS, '/site/build/reset', array(
			'methods'             => 'POST',
			'callback'            => array( __CLASS__, 'post_site_build_reset' ),
			'permission_callback' => array( __CLASS__, 'can_manage_site' ),
		) );
		register_rest_route( self::ROUTE_NS, '/site/build/notes', array(
			'methods'             => 'POST',
			'callback'            => array( __CLASS__, 'post_site_build_notes' ),
			'permission_callback' => array( __CLASS__, 'can_propose_site' ),
		) );
		// The full site set up (0.12.0): check what is missing, then prepare the brief and the page list.
		register_rest_route( self::ROUTE_NS, '/site/full/check', array(
			'methods'             => 'POST',
			'callback'            => array( __CLASS__, 'post_site_full_check' ),
			'permission_callback' => array( __CLASS__, 'can_propose_site' ),
		) );
		register_rest_route( self::ROUTE_NS, '/site/full/prepare', array(
			'methods'             => 'POST',
			'callback'            => array( __CLASS__, 'post_site_full_prepare' ),
			'permission_callback' => array( __CLASS__, 'can_propose_site' ),
		) );
		register_rest_route( self::ROUTE_NS, '/site/build/step', array(
			'methods'             => 'POST',
			'callback'            => array( __CLASS__, 'post_site_build_step' ),
			'permission_callback' => array( __CLASS__, 'can_build_site' ),
		) );
		register_rest_route( self::ROUTE_NS, '/site/brief', array(
			'methods'             => 'GET',
			'callback'            => array( __CLASS__, 'get_site_brief' ),
			'permission_callback' => array( __CLASS__, 'can_read_site' ),
		) );
		register_rest_route( self::ROUTE_NS, '/site/brief/plan', array(
			'methods'             => 'POST',
			'callback'            => array( __CLASS__, 'post_site_brief_plan' ),
			'permission_callback' => array( __CLASS__, 'can_manage_site' ),
		) );
		register_rest_route( self::ROUTE_NS, '/site/brief/apply', array(
			'methods'             => 'POST',
			'callback'            => array( __CLASS__, 'post_site_brief_apply' ),
			'permission_callback' => array( __CLASS__, 'can_manage_site' ),
		) );
		register_rest_route( self::ROUTE_NS, '/site/propose', array(
			'methods'             => 'POST',
			'callback'            => array( __CLASS__, 'post_site_propose' ),
			'permission_callback' => array( __CLASS__, 'can_propose_site' ),
		) );
		register_rest_route( self::ROUTE_NS, '/site/doctor', array(
			'methods'             => 'GET',
			'callback'            => array( __CLASS__, 'get_site_doctor' ),
			'permission_callback' => array( __CLASS__, 'can_read_site' ),
		) );
		register_rest_route( self::ROUTE_NS, '/site/templates', array(
			'methods'             => 'GET',
			'callback'            => array( __CLASS__, 'get_site_templates' ),
			'permission_callback' => array( __CLASS__, 'can_read_site' ),
		) );
	}

	// -- Permission callbacks ---------------------------------------------

	public static function can_read_page( $request ) {
		return self::guard( $request, 'read' );
	}

	public static function can_preview_page( $request ) {
		return self::guard( $request, 'preview' );
	}

	/** AI suggestions for the header, footer and style: administrators only, and the AI limit. */
	public static function can_propose_site( $request ) {
		return self::guard( $request, 'ai', false, 'manage_options' );
	}

	/** A new AI drafted page spends the key and makes a page, so it passes both limits. */
	public static function can_generate_page( $request ) {
		$ok = self::guard( $request, 'ai', false );
		if ( true !== $ok ) {
			return $ok;
		}
		return self::rate_limit( 'create' );
	}

	/** Asking the AI spends the site's key, so it has its own, tighter limit. */
	public static function can_remix_page( $request ) {
		return self::guard( $request, 'ai' );
	}

	/** One change from a plan: its own limit, since a plan of several changes runs one request each. */
	public static function can_plan_step( $request ) {
		return self::guard( $request, 'plan' );
	}

	/** Adding a section: the AI limit when the AI writes it, otherwise the preview limit. */
	public static function can_add_section( $request ) {
		return self::guard( $request, self::truthy( $request->get_param( 'use_ai' ) ) ? 'ai' : 'preview' );
	}

	public static function can_write_page( $request ) {
		return self::guard( $request, 'write' );
	}

	/** Site settings need manage_options (administrators), and no page ID. */
	public static function can_manage_site( $request ) {
		return self::guard( $request, 'write', false, 'manage_options' );
	}

	/** One build step: administrators only, with its own limit (a step may spend the key once). */
	public static function can_build_site( $request ) {
		return self::guard( $request, 'build', false, 'manage_options' );
	}

	public static function can_read_site( $request ) {
		return self::guard( $request, 'read', false, 'manage_options' );
	}

	/** Anything about the editor itself (not a page) needs only edit_pages: the editing tools switch. */
	public static function can_use_editor( $request ) {
		return self::guard( $request, 'write', false );
	}

	/** New draft pages need only edit_pages, and no page ID. */
	public static function can_create_page( $request ) {
		return self::guard( $request, 'create', false );
	}

	/** Publishing and unpublishing need publish rights on top of edit rights. */
	public static function can_publish_page( $request ) {
		$page_id = (int) $request->get_param( 'id' );
		$ok      = self::guard( $request, 'write' );
		if ( true !== $ok ) {
			return $ok;
		}
		if ( ! current_user_can( 'publish_pages' ) || ! current_user_can( 'publish_post', $page_id ) ) {
			return self::error( 'twd_sk_cannot_publish', 'You do not have permission to publish or unpublish pages.', 403 );
		}
		return true;
	}

	/**
	 * The one gate every route passes: nonce, sign-in, capability on this page,
	 * request size, rate limit. Returns true or a WP_Error with an HTTP status.
	 */
	private static function guard( $request, $bucket, $needs_page = true, $cap = 'edit_pages' ) {
		$nonce = $request->get_header( self::NONCE_HEADER );
		if ( ! is_string( $nonce ) || '' === $nonce || ! wp_verify_nonce( $nonce, 'wp_rest' ) ) {
			return self::error( 'twd_sk_bad_nonce', 'Your sign-in has timed out. Reload the page and try again.', 401 );
		}
		if ( ! is_user_logged_in() ) {
			return self::error( 'twd_sk_not_signed_in', 'You need to be signed in.', 401 );
		}
		if ( ! $needs_page ) {
			if ( ! current_user_can( $cap ) ) {
				return self::error( 'twd_sk_forbidden', 'manage_options' === $cap ? 'Only administrators can change the site settings.' : 'You do not have permission to create pages.', 403 );
			}
		} else {
			$page_id = (int) $request->get_param( 'id' );
			if ( $page_id <= 0 || ! current_user_can( 'edit_pages' ) || ! current_user_can( 'edit_post', $page_id ) ) {
				return self::error( 'twd_sk_forbidden', 'You do not have permission to edit this page.', 403 );
			}
			$post = get_post( $page_id );
			if ( ! $post || 'page' !== $post->post_type ) {
				return self::error( 'twd_sk_not_a_page', 'That page was not found.', 404 );
			}
		}
		if ( strlen( (string) $request->get_body() ) > self::MAX_BODY ) {
			return self::error( 'twd_sk_too_large', 'That request is too large. A page is limited to 200 KB.', 413 );
		}
		return self::rate_limit( $bucket );
	}

	/**
	 * A simple fixed window per user and bucket, kept in a transient.
	 */
	private static function rate_limit( $bucket ) {
		$limits = self::limits();
		list( $max, $window ) = $limits[ $bucket ];
		$key   = 'twd_sk_rl_' . (int) get_current_user_id() . '_' . $bucket;
		$state = get_transient( $key );
		$now   = time();
		if ( ! is_array( $state ) || ! isset( $state['start'], $state['count'] ) || $now - (int) $state['start'] >= $window ) {
			$state = array( 'start' => $now, 'count' => 0 );
		}
		if ( (int) $state['count'] >= $max ) {
			return self::error( 'twd_sk_rate_limited', 'Too many requests. Wait a minute and try again.', 429 );
		}
		$state['count']++;
		set_transient( $key, $state, $window );
		return true;
	}

	private static function error( $code, $message, $status, $extra = array() ) {
		return new WP_Error( $code, $message, array( 'status' => $status ) + $extra );
	}

	/** Store errors carry no HTTP status. Give each one a sensible status. */
	private static function from_store_error( $e ) {
		$map  = array(
			'twd_sk_conflict'      => 409,
			'twd_sk_too_large'     => 413,
			'twd_sk_empty'         => 400,
			'twd_sk_bad_page'      => 404,
			'twd_sk_not_a_page'    => 404,
			'twd_sk_leftovers'     => 409,
			'twd_sk_placeholders'  => 409,
			'twd_sk_other_content' => 409,
			'twd_sk_site_page'     => 409,
			'twd_sk_not_kit_page'  => 400,
			'twd_sk_empty_page'    => 400,
		);
		$code = $e->get_error_code();
		$data = $e->get_error_data();
		return self::error( $code, $e->get_error_message(), isset( $map[ $code ] ) ? $map[ $code ] : 400, is_array( $data ) ? $data : array() );
	}

	// -- Handlers ---------------------------------------------------------

	public static function get_prompt( $request ) {
		$id   = (int) $request->get_param( 'id' );
		$html = TWD_SK_Store::get_current( $id );
		return array(
			'prompt'         => TWD_SK_Prompt::build( $html, current_user_can( 'manage_options' ) ? TWD_SK_Facts::prompt_block() : '' ),
			'version'        => TWD_SK_Store::get_current_version_id( $id ),
			'leftover_count' => TWD_SK_Report::leftover_total( TWD_SK_Sanitizer::find_leftovers( $html ) ),
		);
	}

	/**
	 * Where a page stands: status, template, publish readiness. Read only.
	 */
	/** The editing tools switch (0.14.1): remember, for this person, that the launchers are hidden or shown. */
	public static function post_editor_tools( $request ) {
		$off = self::truthy( $request->get_param( 'off' ) );
		TWD_SK_Editor::set_tools_off( $off );
		return array( 'off' => $off );
	}

	public static function post_editor_window( $request ) {
		$state = $request->get_param( 'state' );
		if ( ! is_array( $state ) ) {
			return self::error( 'twd_sk_bad_input', 'Send the window state as a set of fields.', 400 );
		}
		$saved = TWD_SK_Editor::set_window_state( $state );
		return array( 'state' => false === $saved ? TWD_SK_Editor::window_defaults() : $saved );
	}

	public static function get_info( $request ) {
		return self::info_payload( (int) $request->get_param( 'id' ) );
	}

	private static function info_payload( $id ) {
		$post      = get_post( $id );
		$leftovers = TWD_SK_Sanitizer::find_leftovers( TWD_SK_Store::get_current( $id ) );
		return array(
			'id'            => (int) $id,
			'title'         => $post ? (string) $post->post_title : '',
			'status'        => $post ? (string) $post->post_status : '',
			'is_kit_page'   => TWD_SK_Page::is_kit_page( $id ),
			'uses_template' => TWD_SK_Template::uses_template( $id ),
			'other_content' => TWD_SK_Template::other_elementor_content( $id ),
			'is_site_page'  => TWD_SK_Template::is_site_page( $id ),
			'has_content'   => '' !== TWD_SK_Store::get_current( $id ),
		) + self::leftover_fields( $leftovers );
	}

	/** Create a draft page set up for the kit. Never published, never given content. */
	public static function post_create_page( $request ) {
		$title   = $request->get_param( 'title' );
		$starter = $request->get_param( 'starter' );
		if ( ! is_string( $title ) ) {
			return self::error( 'twd_sk_bad_input', 'Give the page a title.', 400 );
		}
		if ( null !== $starter && ! is_string( $starter ) ) {
			return self::error( 'twd_sk_bad_input', 'Choose one of the starting layouts on offer.', 400 );
		}
		$id = TWD_SK_Template::create_page( $title, is_string( $starter ) && '' !== $starter ? $starter : 'blank' );
		if ( is_wp_error( $id ) ) {
			return self::from_store_error( $id );
		}
		return array(
			'id'     => (int) $id,
			'status' => 'draft',
			'title'  => (string) get_post( $id )->post_title,
			'url'    => get_permalink( $id ),
		);
	}

	/** Publish or unpublish. The server asks for a confirmation and blocks leftover example text. */
	public static function post_status( $request ) {
		$id      = (int) $request->get_param( 'id' );
		$status  = $request->get_param( 'status' );
		$confirm = self::truthy( $request->get_param( 'confirm' ) );
		if ( ! is_string( $status ) || ! in_array( $status, array( 'publish', 'draft' ), true ) ) {
			return self::error( 'twd_sk_bad_input', 'The status must be publish or draft.', 400 );
		}
		if ( ! $confirm ) {
			return self::error( 'twd_sk_confirm_needed', 'Please confirm first.', 400 );
		}
		$result = TWD_SK_Template::set_status( $id, $status, self::truthy( $request->get_param( 'override_placeholders' ) ) );
		if ( is_wp_error( $result ) ) {
			$err = self::from_store_error( $result );
			$data = $result->get_error_data();
			if ( is_array( $data ) && isset( $data['levels'] ) ) {
				$data = array(
					'status'  => 409,
					'leftovers' => TWD_SK_Report::describe_leftovers( $data['levels']['must'] + $data['levels']['check'] ),
				);
				return new WP_Error( $result->get_error_code(), $result->get_error_message(), $data );
			}
			return $err;
		}
		return self::info_payload( $id );
	}

	/** Switch a page to the kit template, or back to the theme's. */
	public static function post_template( $request ) {
		$id  = (int) $request->get_param( 'id' );
		$use = $request->get_param( 'use' );
		if ( ! is_string( $use ) || ! in_array( $use, array( 'kit', 'default' ), true ) ) {
			return self::error( 'twd_sk_bad_input', 'Choose the kit template or the default one.', 400 );
		}
		$result = TWD_SK_Template::switch_template( $id, 'kit' === $use, self::truthy( $request->get_param( 'confirm_other_content' ) ) );
		if ( is_wp_error( $result ) ) {
			return self::from_store_error( $result );
		}
		return self::info_payload( $id );
	}

	// -- The facts and new pages from them -------------------------------

	private static function facts_payload() {
		return array(
			'text'     => TWD_SK_Facts::get(),
			'updated'  => TWD_SK_Facts::updated(),
			'max'      => TWD_SK_Facts::MAX,
			'template' => TWD_SK_Facts::template(),
		);
	}

	public static function get_site_facts( $request ) {
		return self::facts_payload();
	}

	public static function post_site_facts( $request ) {
		$saved = TWD_SK_Facts::save( $request->get_param( 'text' ) );
		if ( is_wp_error( $saved ) ) {
			return self::error( $saved->get_error_code(), $saved->get_error_message(), 400 );
		}
		return self::facts_payload();
	}

	// -- Embeds (0.15.0) ---------------------------------------------------------

	private static function embeds_payload( $extra = array() ) {
		return $extra + array(
			'embeds'    => TWD_SK_Embed::listing(),
			'kinds'     => TWD_SK_Embed::kinds(),
			'can_html'  => TWD_SK_Embed::can_save_kind( 'html' ),
			'max_code'  => TWD_SK_Embed::MAX_CODE,
			'max_items' => TWD_SK_Embed::MAX_ITEMS,
		);
	}

	public static function get_site_embeds( $request ) {
		return self::embeds_payload();
	}

	/** Saves a new embed, or the one named by id. Administrators only; raw HTML also needs unfiltered_html. */
	public static function post_site_embeds( $request ) {
		$kind = $request->get_param( 'kind' );
		if ( 'html' === $kind && ! TWD_SK_Embed::can_save_kind( 'html' ) ) {
			return self::error( 'twd_sk_forbidden', 'Your account is not allowed to save raw HTML on this site. A shortcode can still be saved.', 403 );
		}
		$id    = (int) $request->get_param( 'embed_id' );
		$saved = TWD_SK_Embed::save( array(
			'label' => $request->get_param( 'label' ),
			'kind'  => $kind,
			'code'  => $request->get_param( 'code' ),
		), $id, get_current_user_id() );
		if ( is_wp_error( $saved ) ) {
			return self::error( $saved->get_error_code(), $saved->get_error_message(), 'twd_sk_not_found' === $saved->get_error_code() ? 404 : 400 );
		}
		return self::embeds_payload( array( 'saved' => (int) $saved['id'] ) );
	}

	/** Deletes an embed. One that pages still use needs confirm, because those pages would show a gap. */
	public static function post_site_embed_delete( $request ) {
		$eid = (int) $request->get_param( 'eid' );
		if ( ! TWD_SK_Embed::get( $eid ) ) {
			return self::error( 'twd_sk_not_found', 'That embed was not found. It may already have been deleted.', 404 );
		}
		$use = TWD_SK_Embed::usage();
		if ( ! empty( $use[ $eid ] ) && ! self::truthy( $request->get_param( 'confirm' ) ) ) {
			$names = array_map( function ( $p ) {
				return '"' . $p['title'] . '"';
			}, $use[ $eid ] );
			return self::error( 'twd_sk_in_use', 'This embed is used on ' . implode( ', ', $names ) . '. Those pages would show a gap and could not be published until it is replaced. Delete it anyway?', 409, array( 'used_on' => $use[ $eid ] ) );
		}
		TWD_SK_Embed::delete( $eid );
		return self::embeds_payload();
	}

	// -- Bring pages up to date (0.15.0) -----------------------------------------

	private static function upgrades_payload( $extra = array() ) {
		$scan = TWD_SK_Upgrade::scan();
		return $extra + array(
			'pages' => $scan,
			'total' => TWD_SK_Upgrade::total( $scan ),
		);
	}

	public static function get_site_upgrades( $request ) {
		return self::upgrades_payload();
	}

	public static function get_site_history( $request ) {
		return array( 'history' => TWD_SK_Style::site_history_summary() );
	}

	public static function post_site_history_undo( $request ) {
		$r = TWD_SK_Style::site_undo();
		if ( is_wp_error( $r ) ) {
			return self::from_store_error( $r );
		}
		return $r + array( 'history' => TWD_SK_Style::site_history_summary() );
	}

	public static function post_site_history_restore( $request ) {
		$index = $request->get_param( 'index' );
		$at    = $request->get_param( 'at' );
		if ( ! is_numeric( $index ) || (int) $index < 0 || ! is_string( $at ) ) {
			return self::error( 'twd_sk_bad_input', 'Choose a site change from the list.', 400 );
		}
		$r = TWD_SK_Style::site_restore( (int) $index, $at );
		if ( is_wp_error( $r ) ) {
			return self::from_store_error( $r );
		}
		return $r + array( 'history' => TWD_SK_Style::site_history_summary() );
	}

	/** Adds what is missing to the pages named (or every page that can take it). Needs confirm; each page is its own version. */
	public static function post_site_upgrades_apply( $request ) {
		if ( ! self::truthy( $request->get_param( 'confirm' ) ) ) {
			return self::error( 'twd_sk_confirm', 'Please confirm that you want these pages brought up to date.', 400 );
		}
		$pages = $request->get_param( 'pages' );
		$ids   = null;
		if ( is_array( $pages ) ) {
			$ids = array();
			foreach ( $pages as $pid ) {
				// Only pages this person may edit.
				if ( is_numeric( $pid ) && current_user_can( 'edit_post', (int) $pid ) ) {
					$ids[] = (int) $pid;
				}
			}
		}
		$done = TWD_SK_Upgrade::run( $ids );
		return self::upgrades_payload( array( 'updated' => $done['updated'], 'failed' => $done['failed'] ) );
	}

	// -- Therapy or business mode -------------------------------------------

	private static function mode_payload() {
		return array(
			'mode'  => TWD_SK_Mode::get(),
			'modes' => TWD_SK_Mode::modes(),
			'words' => TWD_SK_Mode::js_words(),
		);
	}

	public static function get_site_mode( $request ) {
		return self::mode_payload();
	}

	public static function get_site_looks( $request ) {
		return array( 'advanced_for_all' => TWD_SK_Looks::advanced_for_all(), 'advanced' => TWD_SK_Looks::advanced_keys() );
	}

	public static function post_site_looks( $request ) {
		TWD_SK_Editor::set_advanced_looks_for_all( self::truthy( $request->get_param( 'advanced_for_all' ) ) );
		return self::get_site_looks( $request );
	}

	/** Changes wording, the search details type and which fields show. Never rewrites a page. */
	public static function post_site_mode( $request ) {
		$saved = TWD_SK_Mode::set( $request->get_param( 'mode' ) );
		if ( is_wp_error( $saved ) ) {
			return self::error( 'twd_sk_bad_input', $saved->get_error_message(), 400 );
		}
		return self::mode_payload();
	}

	// -- The launch check ---------------------------------------------------

	public static function get_site_launch( $request ) {
		return TWD_SK_Launch::check();
	}

	/** Publish the chosen pages that are ready. Needs confirm. Pages with anything to replace are refused. */
	public static function post_site_launch_publish( $request ) {
		if ( ! self::truthy( $request->get_param( 'confirm' ) ) ) {
			return self::error( 'twd_sk_confirm_needed', 'Please confirm first.', 400 );
		}
		$ids = $request->get_param( 'ids' );
		if ( ! is_array( $ids ) || ! $ids ) {
			return self::error( 'twd_sk_bad_input', 'Choose at least one page to publish.', 400 );
		}
		$out          = TWD_SK_Launch::publish( array_slice( $ids, 0, TWD_SK_Launch::MAX_PAGES ) );
		$out['check'] = TWD_SK_Launch::check();
		return $out;
	}

	/** Move WordPress's sample page and first post to the bin (0.14.0). Needs confirm. Nothing is deleted for good. */
	public static function post_site_launch_tidy( $request ) {
		if ( ! self::truthy( $request->get_param( 'confirm' ) ) ) {
			return self::error( 'twd_sk_confirm_needed', 'Please confirm first.', 400 );
		}
		$out          = TWD_SK_Launch::tidy();
		$out['check'] = TWD_SK_Launch::check();
		return $out;
	}

	// -- Colours from the logo -----------------------------------------------

	/** Proposed colours from the logo (or another picture). Nothing is saved: the Site tab previews them. */
	public static function get_site_brand( $request ) {
		$id = (int) $request->get_param( 'picture' );
		if ( $id <= 0 ) {
			$id = (int) TWD_SK_Profile::get()['logo_id'];
		}
		if ( $id <= 0 ) {
			return self::error( 'twd_sk_bad_input', 'There is no logo yet. Choose one in Site details first.', 400 );
		}
		$out = TWD_SK_Brand::from_attachment( $id );
		if ( is_wp_error( $out ) ) {
			return self::error( $out->get_error_code(), $out->get_error_message(), 400 );
		}
		return $out;
	}

	// -- The site setup build ------------------------------------------------

	private static function build_payload( $errors = array() ) {
		$draft = TWD_SK_Build::draft();
		$packs = array();
		foreach ( TWD_SK_Packs::packs() as $slug => $p ) {
			$packs[ $slug ] = array( 'name' => $p['name'], 'description' => $p['description'] );
		}
		$pics = array( 'slots' => array(), 'custom' => array() );
		foreach ( $draft['pictures']['slots'] as $slot => $p ) {
			$pics['slots'][ $slot ] = $p + array( 'url' => TWD_SK_Pictures::url( $p['id'] ) );
		}
		foreach ( $draft['pictures']['custom'] as $p ) {
			$pics['custom'][] = $p + array( 'url' => TWD_SK_Pictures::url( $p['id'] ) );
		}
		$draft['pictures'] = $pics;
		$plan = TWD_SK_Build::plan();
		foreach ( $plan['pages'] as $i => $pg ) {
			$plan['pages'][ $i ]['parts'] = TWD_SK_Recipes::parts_data( $pg['parts'] );
		}
		$recipes = array();
		foreach ( TWD_SK_Recipes::types() as $type => $r ) {
			$recipes[ $type ] = array( 'label' => $r['label'], 'title' => $r['title'], 'needs_topic' => $r['needs_topic'], 'parts' => TWD_SK_Recipes::parts_data( $r['parts'] ) );
		}
		unset( $draft['pages'] );
		return array(
			'draft'    => $draft,
			'plan'     => $plan,
			'components'   => TWD_SK_Recipes::parts_menu(),
			'recipes'      => $recipes,
			'max_sections' => TWD_SK_Recipes::MAX_SECTIONS,
			'max_pages'    => TWD_SK_Brief::MAX_PAGES,
			'errors'   => $errors,
			'modes'    => TWD_SK_Mode::modes(),
			'packs'    => $packs,
			'slots'    => TWD_SK_Pictures::slots(),
			'ai'       => TWD_SK_AI::available(),
			'done'     => (string) get_option( TWD_SK_Build::DONE, '' ),
			'safe'     => TWD_SK_Safe::on(),
			'max_pictures' => TWD_SK_Pictures::MAX_CUSTOM,
			'full'         => array(
				'ai'          => TWD_SK_AI::available(),
				'articles'    => TWD_SK_Full::articles_available(),
				'default_email' => TWD_SK_Full::default_email(),
				'design'      => TWD_SK_Full::design_questions( array() ),
			),
			'sets'         => TWD_SK_Sets::summary(),
			'focal_points' => array_map( function ( $p ) {
				return $p[0];
			}, TWD_SK_Images::focal_points() ),
		);
	}

	public static function get_site_build( $request ) {
		return self::build_payload();
	}

	/** Save some of the setup page's choices. Good values are kept even when another is refused. */
	public static function post_site_build_draft( $request ) {
		$in = array();
		foreach ( array( 'step', 'mode', 'set', 'pack', 'brand', 'brief_text', 'pictures', 'pages', 'reset_pages', 'full', 'full_notes', 'full_answers', 'full_anyway' ) as $k ) {
			$v = $request->get_param( $k );
			if ( null !== $v ) {
				$in[ $k ] = $v;
			}
		}
		$out = TWD_SK_Build::save_draft( $in );
		return self::build_payload( $out['errors'] );
	}

	/** Forget the setup page's choices. Pages already made stay, as drafts. */
	public static function post_site_build_reset( $request ) {
		if ( ! self::truthy( $request->get_param( 'confirm' ) ) ) {
			return self::error( 'twd_sk_confirm_needed', 'Please confirm first.', 400 );
		}
		TWD_SK_Build::reset_draft();
		return self::build_payload();
	}

	/** Turn plain notes into a brief with the AI. Saves nothing: the person checks it first. */
	public static function post_site_build_notes( $request ) {
		$out = TWD_SK_Brief::from_notes( $request->get_param( 'notes' ) );
		if ( is_wp_error( $out ) ) {
			$map    = array( 'twd_sk_bad_brief' => 400, 'twd_sk_ai_unavailable' => 503 );
			$code   = $out->get_error_code();
			$status = isset( $map[ $code ] ) ? $map[ $code ] : 502;
			return self::error( $code, $out->get_error_message(), $status );
		}
		return array( 'json' => $out['json'], 'note' => isset( $out['note'] ) ? $out['note'] : '' );
	}

	/** The full site set up: keep the notes and answers, ask the AI what is missing, keep the result. */
	public static function post_site_full_check( $request ) {
		$in = array();
		foreach ( array( 'full_notes', 'full_answers' ) as $k ) {
			$v = $request->get_param( $k );
			if ( null !== $v ) {
				$in[ $k ] = $v;
			}
		}
		if ( $in ) {
			$saved = TWD_SK_Build::save_draft( $in );
			if ( $saved['errors'] ) {
				return self::error( 'twd_sk_bad_input', implode( ' ', $saved['errors'] ), 400 );
			}
		}
		$check = TWD_SK_Full::check( TWD_SK_Build::draft() );
		if ( is_wp_error( $check ) ) {
			$map    = array( 'twd_sk_bad_input' => 400, 'twd_sk_ai_unavailable' => 503 );
			$code   = $check->get_error_code();
			$status = isset( $map[ $code ] ) ? $map[ $code ] : 502;
			return self::error( $code, $check->get_error_message(), $status );
		}
		TWD_SK_Build::store_full_check( $check );
		return self::build_payload();
	}

	/** The full site set up: turn a ready check into a brief and the list of pages, and move on to the pictures. */
	public static function post_site_full_prepare( $request ) {
		$prepared = TWD_SK_Full::prepare( TWD_SK_Build::draft() );
		if ( is_wp_error( $prepared ) ) {
			$map    = array( 'twd_sk_not_ready' => 409, 'twd_sk_bad_brief' => 400, 'twd_sk_ai_unavailable' => 503 );
			$code   = $prepared->get_error_code();
			$status = isset( $map[ $code ] ) ? $map[ $code ] : 502;
			return self::error( $code, $prepared->get_error_message(), $status );
		}
		$stored = TWD_SK_Build::store_prepared( $prepared );
		if ( is_wp_error( $stored ) ) {
			return self::error( 'twd_sk_bad_input', $stored->get_error_message(), 400 );
		}
		return self::build_payload();
	}

	/** Run one build step. Pages are drafts only. */
	public static function post_site_build_step( $request ) {
		$step = $request->get_param( 'step' );
		$out  = TWD_SK_Build::run_step( is_string( $step ) ? $step : '', (int) $request->get_param( 'index' ), array( 'ai' => null === $request->get_param( 'ai' ) ? true : self::truthy( $request->get_param( 'ai' ) ) ) );
		if ( is_wp_error( $out ) ) {
			$map    = array( 'twd_sk_bad_input' => 400, 'twd_sk_contrast' => 422, 'twd_sk_bad_brief' => 422 );
			$code   = $out->get_error_code();
			$status = isset( $map[ $code ] ) ? $map[ $code ] : 500;
			return self::error( $code, $out->get_error_message(), $status );
		}
		return $out;
	}

	// -- The site brief ---------------------------------------------------

	/** The current site as a brief, the interview prompt, and the format. Read only. */
	public static function get_site_brief( $request ) {
		return array(
			'brief'  => TWD_SK_Brief::export(),
			'prompt' => TWD_SK_Brief::interview_prompt(),
			'schema' => TWD_SK_Brief::SCHEMA,
		);
	}

	/** What a pasted brief would do. Changes nothing. */
	public static function post_site_brief_plan( $request ) {
		$data = TWD_SK_Brief::parse( $request->get_param( 'brief' ) );
		if ( is_wp_error( $data ) ) {
			return self::error( $data->get_error_code(), $data->get_error_message(), 400 );
		}
		return TWD_SK_Brief::preview( $data, self::truthy( $request->get_param( 'overwrite' ) ) );
	}

	/** Apply a pasted brief. Needs confirm. Pages are created as drafts only. */
	public static function post_site_brief_apply( $request ) {
		if ( ! self::truthy( $request->get_param( 'confirm' ) ) ) {
			return self::error( 'twd_sk_confirm_needed', 'Please confirm first.', 400 );
		}
		$data = TWD_SK_Brief::parse( $request->get_param( 'brief' ) );
		if ( is_wp_error( $data ) ) {
			return self::error( $data->get_error_code(), $data->get_error_message(), 400 );
		}
		$out                = TWD_SK_Brief::apply( $data, array( 'overwrite' => self::truthy( $request->get_param( 'overwrite' ) ), 'build_pages' => self::truthy( $request->get_param( 'build_pages' ) ) ) );
		$out['profile_state'] = self::profile_payload();
		return $out;
	}

	/** The plain-text site report. Read only. */
	public static function get_site_doctor( $request ) {
		return array( 'report' => TWD_SK_Doctor::report() );
	}

	/** Ask the AI to suggest header, footer or style values. Saves nothing. */
	public static function post_site_propose( $request ) {
		$result = TWD_SK_Proposals::propose( $request->get_param( 'kind' ), $request->get_param( 'instruction' ) );
		if ( is_wp_error( $result ) ) {
			$map    = array( 'twd_sk_bad_input' => 400, 'twd_sk_ai_unavailable' => 503 );
			$code   = $result->get_error_code();
			$status = isset( $map[ $code ] ) ? $map[ $code ] : 502;
			return self::error( $code, $result->get_error_message(), $status );
		}
		return $result;
	}

	/** Draft a new page from the facts. Creates a DRAFT only. */
	public static function post_generate( $request ) {
		$page_id = $request->get_param( 'page_id' );
		if ( null !== $page_id && '' !== $page_id && ( ! is_numeric( $page_id ) || (int) $page_id <= 0 || ! current_user_can( 'edit_post', (int) $page_id ) ) ) {
			return self::error( 'twd_sk_forbidden', 'You do not have permission to edit that page.', 403 );
		}
		$result = TWD_SK_AI::generate_page( array(
			'page_id' => is_numeric( $page_id ) ? (int) $page_id : 0,
			'type'  => $request->get_param( 'type' ),
			'title' => $request->get_param( 'title' ),
			'topic' => $request->get_param( 'topic' ),
			'notes' => $request->get_param( 'notes' ),
		) );
		if ( is_wp_error( $result ) ) {
			$map    = array( 'twd_sk_bad_input' => 400, 'twd_sk_ai_unavailable' => 503 );
			$code   = $result->get_error_code();
			$status = isset( $map[ $code ] ) ? $map[ $code ] : 502;
			return self::error( $code, $result->get_error_message(), $status );
		}
		return $result;
	}

	/** The text to paste into an external AI to draft a page. Saves nothing. */
	public static function post_recipe_prompt( $request ) {
		$in = TWD_SK_Recipes::inputs( array(
			'type'  => $request->get_param( 'type' ),
			'title' => $request->get_param( 'title' ),
			'topic' => $request->get_param( 'topic' ),
			'notes' => $request->get_param( 'notes' ),
		) );
		if ( is_wp_error( $in ) ) {
			return self::error( $in->get_error_code(), $in->get_error_message(), 400 );
		}
		return array( 'prompt' => TWD_SK_AI::page_prompt( $in, current_user_can( 'manage_options' ) ), 'title' => $in['title'] );
	}

	// -- AI remix ---------------------------------------------------------

	/** The page as a list of sections, and whether AI is available. Read only. */
	public static function get_sections( $request ) {
		$id = (int) $request->get_param( 'id' );
		return array(
			'ai'       => TWD_SK_AI::available(),
			'version'  => TWD_SK_Store::get_current_version_id( $id ),
			'sections' => TWD_SK_Sections::describe( TWD_SK_Store::get_current( $id ) ),
		);
	}

	/**
	 * Ask the AI for a candidate page. Saves nothing: the editor then previews the candidate
	 * and applies it through the normal routes.
	 */
	public static function post_remix( $request ) {
		$id   = (int) $request->get_param( 'id' );
		$base = self::base_version( $request );
		if ( is_wp_error( $base ) ) {
			return $base;
		}
		if ( $base !== (int) TWD_SK_Store::get_current_version_id( $id ) ) {
			return self::error( 'twd_sk_conflict', 'This page changed since you opened the editor. Reload the page and try again.', 409 );
		}
		$result = TWD_SK_AI::remix( $id, array(
			'mode'        => $request->get_param( 'mode' ),
			'indexes'     => $request->get_param( 'indexes' ),
			'unlock'      => $request->get_param( 'unlock' ),
			'instruction' => $request->get_param( 'instruction' ),
			'facts'       => $request->get_param( 'facts' ),
		) );
		if ( is_wp_error( $result ) ) {
			$map    = array( 'twd_sk_bad_input' => 400, 'twd_sk_empty_page' => 400, 'twd_sk_ai_unavailable' => 503 );
			$code   = $result->get_error_code();
			$status = isset( $map[ $code ] ) ? $map[ $code ] : 502;
			return self::error( $code, $result->get_error_message(), $status );
		}
		return $result;
	}

	/** Words and pictures changed on the page itself, put into the stored page and saved as a new version. */
	public static function post_inline( $request ) {
		$id   = (int) $request->get_param( 'id' );
		$base = self::base_version( $request );
		if ( is_wp_error( $base ) ) {
			return $base;
		}
		if ( $base !== (int) TWD_SK_Store::get_current_version_id( $id ) ) {
			return self::error( 'twd_sk_conflict', 'This page changed since you opened it. Reload the page and make the change again.', 409 );
		}
		// The look of sections (layout and options) changes only class names, so it goes first on the section
		// numbers the page was drawn with; words, pictures and removals follow on the result (0.15.0).
		$html  = TWD_SK_Store::get_current( $id );
		$looks = array( 'html' => $html, 'changed' => 0 );
		if ( is_array( $request->get_param( 'looks' ) ) && $request->get_param( 'looks' ) ) {
			$looks = TWD_SK_Looks::apply( $html, $request->get_param( 'looks' ), TWD_SK_Looks::advanced_allowed() );
			if ( is_wp_error( $looks ) ) {
				return self::error( $looks->get_error_code(), $looks->get_error_message(), 'twd_sk_conflict' === $looks->get_error_code() ? 409 : ( 'twd_sk_forbidden' === $looks->get_error_code() ? 403 : 400 ) );
			}
		}
		$others = array( $request->get_param( 'texts' ), $request->get_param( 'pictures' ), $request->get_param( 'spots' ), $request->get_param( 'removes' ) );
		if ( $looks['changed'] && ! array_filter( $others ) ) {
			$out = array( 'html' => $looks['html'], 'changed' => 0, 'removed' => 0 );
		} else {
			$out = TWD_SK_Inline::apply( $looks['html'], $others[0], $others[1], $others[2], $others[3] );
			if ( is_wp_error( $out ) ) {
				return self::error( $out->get_error_code(), $out->get_error_message(), 'twd_sk_conflict' === $out->get_error_code() ? 409 : 400 );
			}
		}
		$parts = array();
		if ( $out['changed'] ) {
			$parts[] = (int) $out['changed'] . ' piece' . ( 1 === (int) $out['changed'] ? '' : 's' );
		}
		if ( ! empty( $out['removed'] ) ) {
			$parts[] = (int) $out['removed'] . ' removed';
		}
		if ( $looks['changed'] ) {
			$parts[] = (int) $looks['changed'] . ' section look' . ( 1 === (int) $looks['changed'] ? '' : 's' );
		}
		$note = 'Changed on the page (' . implode( ', ', $parts ) . ')';
		$saved = TWD_SK_Store::save( $id, $out['html'], array( 'base_version' => $base, 'note' => $note ) );
		$done  = self::after_write( $id, $saved );
		// A section that moved to a layout with a picture behind it now holds a picture spot: say where (0.15.0).
		if ( ! is_wp_error( $done ) && ! empty( $looks['spots'] ) ) {
			$done['resume_at'] = (int) $looks['spots'][0];
		}
		return $done;
	}

	/**
	 * Add a section or move one (0.14.1): the change is made on the stored page, cleaned and saved as one version,
	 * and the new place of the section comes back (`at`) so the page can reload with it in view.
	 */
	public static function post_structure( $request ) {
		$id   = (int) $request->get_param( 'id' );
		$base = self::base_version( $request );
		if ( is_wp_error( $base ) ) {
			return $base;
		}
		if ( $base !== (int) TWD_SK_Store::get_current_version_id( $id ) ) {
			return self::error( 'twd_sk_conflict', 'This page changed since you opened it. Reload the page and try again.', 409 );
		}
		$op = $request->get_param( 'op' );
		if ( 'add' === $op ) {
			$out = TWD_SK_Structure::add( $id, array(
				'after'     => $request->get_param( 'after' ),
				'component' => $request->get_param( 'component' ),
				'variant'   => $request->get_param( 'variant' ),
			) );
		} elseif ( 'move' === $op ) {
			$out = TWD_SK_Structure::move( $id, array( 'index' => $request->get_param( 'index' ), 'dir' => $request->get_param( 'dir' ) ) );
		} else {
			return self::error( 'twd_sk_bad_input', 'Choose to add a section or move one.', 400 );
		}
		if ( is_wp_error( $out ) ) {
			return self::error( $out->get_error_code(), $out->get_error_message(), 'twd_sk_empty_page' === $out->get_error_code() || 'twd_sk_bad_input' === $out->get_error_code() ? 400 : 502 );
		}
		$saved = TWD_SK_Store::save( $id, $out['html'], array( 'base_version' => $base, 'note' => $out['note'] ) );
		$done  = self::after_write( $id, $saved );
		return is_wp_error( $done ) ? $done : $done + array( 'at' => (int) $out['at'] );
	}

	/** The checklist of changes for a request. Nothing is changed. */
	public static function post_plan( $request ) {
		$id   = (int) $request->get_param( 'id' );
		$base = self::base_version( $request );
		if ( is_wp_error( $base ) ) {
			return $base;
		}
		if ( $base !== (int) TWD_SK_Store::get_current_version_id( $id ) ) {
			return self::error( 'twd_sk_conflict', 'This page changed since you opened the editor. Reload the page and try again.', 409 );
		}
		$out = TWD_SK_Plan::plan( $id, $request->get_param( 'request' ) );
		if ( is_wp_error( $out ) ) {
			return self::ai_error( $out );
		}
		return $out;
	}

	/** One change from the checklist, on the working copy. Nothing is saved. */
	public static function post_plan_step( $request ) {
		$id   = (int) $request->get_param( 'id' );
		$base = self::base_version( $request );
		if ( is_wp_error( $base ) ) {
			return $base;
		}
		if ( $base !== (int) TWD_SK_Store::get_current_version_id( $id ) ) {
			return self::error( 'twd_sk_conflict', 'This page changed since you opened the editor. Reload the page and try again.', 409 );
		}
		$html = $request->get_param( 'html' );
		$out  = TWD_SK_Plan::step( $id, is_string( $html ) ? $html : '', $request->get_param( 'order' ), $request->get_param( 'change' ), $request->get_param( 'request' ) );
		if ( is_wp_error( $out ) ) {
			return self::ai_error( $out );
		}
		return $out;
	}

	private static function ai_error( $e ) {
		$map    = array( 'twd_sk_bad_input' => 400, 'twd_sk_empty_page' => 400, 'twd_sk_too_large' => 413, 'twd_sk_ai_unavailable' => 503 );
		$code   = $e->get_error_code();
		$status = isset( $map[ $code ] ) ? $map[ $code ] : 502;
		return self::error( $code, $e->get_error_message(), $status );
	}

	/** A candidate page with one new section. Nothing is saved: it goes to the preview like any change. */
	public static function post_add_section( $request ) {
		$id   = (int) $request->get_param( 'id' );
		$base = self::base_version( $request );
		if ( is_wp_error( $base ) ) {
			return $base;
		}
		if ( $base !== (int) TWD_SK_Store::get_current_version_id( $id ) ) {
			return self::error( 'twd_sk_conflict', 'This page changed since you opened the editor. Reload the page and try again.', 409 );
		}
		$result = TWD_SK_AI::add_section( $id, array(
			'after'       => $request->get_param( 'after' ),
			'component'   => $request->get_param( 'component' ),
			'variant'     => $request->get_param( 'variant' ),
			'instruction' => $request->get_param( 'instruction' ),
			'facts'       => $request->get_param( 'facts' ),
			'use_ai'      => self::truthy( $request->get_param( 'use_ai' ) ),
			'embed'       => $request->get_param( 'embed' ),
		) );
		if ( is_wp_error( $result ) ) {
			$map    = array( 'twd_sk_bad_input' => 400, 'twd_sk_empty_page' => 400, 'twd_sk_ai_unavailable' => 503, 'twd_sk_forbidden' => 403 );
			$code   = $result->get_error_code();
			$status = isset( $map[ $code ] ) ? $map[ $code ] : 502;
			return self::error( $code, $result->get_error_message(), $status );
		}
		return $result;
	}

	// -- Search details ---------------------------------------------------

	public static function get_seo( $request ) {
		return TWD_SK_Seo::read( (int) $request->get_param( 'id' ) );
	}

	public static function post_seo( $request ) {
		$fields = $request->get_param( 'fields' );
		if ( ! is_array( $fields ) ) {
			return self::error( 'twd_sk_bad_input', 'Send the search details as a set of fields.', 400 );
		}
		$result = TWD_SK_Seo::write( (int) $request->get_param( 'id' ), $fields );
		if ( is_wp_error( $result ) ) {
			$map    = array( 'twd_sk_slug_confirm' => 409, 'twd_sk_seo_other_plugin' => 409, 'twd_sk_seo_front_page' => 409 );
			$status = isset( $map[ $result->get_error_code() ] ) ? $map[ $result->get_error_code() ] : 400;
			return self::error( $result->get_error_code(), $result->get_error_message(), $status );
		}
		return $result;
	}

	// -- The Site tab -----------------------------------------------------

	public static function get_site( $request ) {
		return TWD_SK_Site::state();
	}

	/** Switch pack and/or save colour, font and corner changes. Unreadable button or band text is refused. */
	public static function post_site_style( $request ) {
		$pack      = $request->get_param( 'pack' );
		$overrides = $request->get_param( 'tokens' );
		if ( null !== $pack && ! is_string( $pack ) ) {
			return self::error( 'twd_sk_bad_input', 'Choose a style from the list.', 400 );
		}
		if ( null !== $overrides && ! is_array( $overrides ) ) {
			return self::error( 'twd_sk_bad_input', 'The colour and font changes must be a list of names and values.', 400 );
		}
		TWD_SK_Style::record( 'Style saved' );
		$out = TWD_SK_Site::apply( $pack, $overrides );
		if ( is_wp_error( $out ) ) {
			$data = $out->get_error_data();
			return self::error( $out->get_error_code(), $out->get_error_message(), 'twd_sk_contrast' === $out->get_error_code() ? 422 : 400, is_array( $data ) ? $data : array() );
		}
		return $out;
	}

	public static function post_site_reset( $request ) {
		TWD_SK_Style::record( 'Style reset to the pack' );
		return TWD_SK_Site::reset();
	}

	/** The profile, the header and footer settings, and what is still missing or placeholder. */
	private static function profile_payload() {
		$profile = TWD_SK_Profile::get();
		$levels  = TWD_SK_Profile::leftovers( $profile );
		$eff     = TWD_SK_Chrome::effective();
		return array(
			'profile'   => $profile,
			'missing'   => TWD_SK_Profile::missing( $profile ),
			'leftovers' => array(
				'must'  => TWD_SK_Report::describe_leftovers( $levels['must'] ),
				'check' => TWD_SK_Report::describe_leftovers( $levels['check'] ),
			),
			'chrome'    => array(
				'settings'        => TWD_SK_Chrome::settings(),
				'effective'       => $eff,
				'header_variants' => TWD_SK_Chrome::header_variants(),
				'footer_variants' => TWD_SK_Chrome::footer_variants(),
				'menu_markers'    => TWD_SK_Chrome::menu_markers(),
				'pack_defaults'   => TWD_SK_Packs::chrome_defaults(),
				'placements'      => TWD_SK_Chrome::placements(),
				'placed_by'       => TWD_SK_Chrome::placement_status(),
				'hello'           => TWD_SK_Chrome::hello_active(),
			),
		);
	}

	public static function get_site_profile( $request ) {
		return self::profile_payload();
	}

	/** Save some or all profile fields. Every field is validated; nothing is stored if any is not. */
	public static function post_site_profile( $request ) {
		$profile = $request->get_param( 'profile' );
		if ( ! is_array( $profile ) ) {
			return self::error( 'twd_sk_bad_input', 'Send the profile as a set of fields.', 400 );
		}
		TWD_SK_Style::record( 'Site details saved' );
		$saved = TWD_SK_Profile::save( $profile );
		if ( is_wp_error( $saved ) ) {
			return self::error( $saved->get_error_code(), $saved->get_error_message(), 400 );
		}
		return self::profile_payload();
	}

	public static function post_site_chrome( $request ) {
		$settings = $request->get_param( 'settings' );
		if ( ! is_array( $settings ) ) {
			return self::error( 'twd_sk_bad_input', 'Send the header and footer settings as a set of fields.', 400 );
		}
		TWD_SK_Style::record( 'Header and footer saved' );
		$saved = TWD_SK_Chrome::save_settings( $settings );
		if ( is_wp_error( $saved ) ) {
			return self::error( $saved->get_error_code(), $saved->get_error_message(), 400 );
		}
		return self::profile_payload();
	}

	/** What setup would do now, in plain sentences. Changes nothing. */
	public static function get_site_setup( $request ) {
		return array( 'plan' => TWD_SK_Setup::plan( array( 'front_page' => true ) ), 'packs' => array_keys( TWD_SK_Packs::packs() ) );
	}

	/** Create the starter pages (drafts), fill a blank profile, optionally set the pack and the front page. Needs a confirmation. */
	public static function post_site_setup( $request ) {
		if ( ! self::truthy( $request->get_param( 'confirm' ) ) ) {
			return self::error( 'twd_sk_confirm_needed', 'Please confirm first.', 400 );
		}
		$pack = $request->get_param( 'pack' );
		if ( null !== $pack && ! is_string( $pack ) ) {
			return self::error( 'twd_sk_bad_input', 'Choose a style from the list.', 400 );
		}
		$front  = $request->get_param( 'front_page' );
		$report = TWD_SK_Setup::run( array( 'pack' => is_string( $pack ) ? $pack : '', 'front_page' => null === $front ? true : self::truthy( $front ) ) );
		if ( is_wp_error( $report ) ) {
			return self::from_store_error( $report );
		}
		foreach ( array( 'created', 'skipped' ) as $list ) {
			foreach ( $report[ $list ] as $i => $page ) {
				$report[ $list ][ $i ]['url'] = get_permalink( $page['id'] );
			}
		}
		return $report + array( 'profile_state' => self::profile_payload() );
	}

	/** The two Theme Builder templates as JSON text, for the base-site import. */
	public static function get_site_templates( $request ) {
		return array( 'files' => TWD_SK_Elementor::files() );
	}

	private static function truthy( $value ) {
		return true === $value || 1 === $value || '1' === $value || 'true' === $value;
	}

	public static function get_versions( $request ) {
		return self::versions_payload( (int) $request->get_param( 'id' ) );
	}

	/**
	 * Clean pasted HTML and keep it for a preview. Writes nothing to the page.
	 */
	public static function post_preview( $request ) {
		$id   = (int) $request->get_param( 'id' );
		$html = $request->get_param( 'html' );
		if ( ! is_string( $html ) || '' === trim( $html ) ) {
			return self::error( 'twd_sk_bad_input', 'Paste the HTML from the AI first.', 400 );
		}
		if ( strlen( $html ) > TWD_SK_Store::MAX_BYTES ) {
			return self::error( 'twd_sk_too_large', 'That page is larger than the ' . ( TWD_SK_Store::MAX_BYTES / 1024 ) . ' KB limit.', 413 );
		}

		$result = TWD_SK_Sanitizer::clean_with_report( $html );
		if ( '' === $result['html'] ) {
			return self::error( 'twd_sk_empty', 'Nothing was left after cleaning, so there is nothing to preview.', 400 );
		}

		$token = TWD_SK_Preview::create( get_current_user_id(), $id, $result['html'] );
		// What changed (0.15.0), worked out by comparing the cleaned page with the page as it is, so the pop-up
		// can say so in plain words: the changes, the style names the cleaner dropped, and "nothing changed".
		$current = str_replace( array( "\r\n", "\r" ), "\n", (string) TWD_SK_Store::get_current( $id ) );
		$dropped = isset( $result['report']['removed']['classes'] ) ? array_values( array_unique( array_map( 'strval', (array) $result['report']['removed']['classes'] ) ) ) : array();
		return array(
			'token'          => $token,
			'preview_url'    => TWD_SK_Preview::url( $id, $token ),
			'expires_in'     => TWD_SK_Preview::TTL,
			'bytes'          => strlen( $result['html'] ),
			'report'         => TWD_SK_Report::describe( $result['report'] ),
			'removed_total'  => (int) $result['report']['total'],
			'same'           => trim( $result['html'] ) === trim( $current ),
			'changes'        => TWD_SK_Report::changes( $current, $result['html'] ),
			'dropped'        => array_slice( $dropped, 0, 12 ),
		) + self::leftover_fields( $result['report']['leftovers'] );
	}

	public static function post_discard( $request ) {
		$token = $request->get_param( 'token' );
		return array( 'discarded' => TWD_SK_Preview::discard( is_string( $token ) ? $token : '', get_current_user_id() ) );
	}

	public static function post_apply( $request ) {
		$id   = (int) $request->get_param( 'id' );
		$html = $request->get_param( 'html' );
		$base = self::base_version( $request );
		if ( is_wp_error( $base ) ) {
			return $base;
		}
		if ( ! is_string( $html ) || '' === trim( $html ) ) {
			return self::error( 'twd_sk_bad_input', 'There is no HTML to apply.', 400 );
		}
		$note = $request->get_param( 'note' );
		$args = array( 'base_version' => $base, 'note' => is_string( $note ) ? $note : '' );

		$saved = TWD_SK_Store::save( $id, $html, $args );
		return self::after_write( $id, $saved );
	}

	public static function post_undo( $request ) {
		$id   = (int) $request->get_param( 'id' );
		$base = self::base_version( $request );
		if ( is_wp_error( $base ) ) {
			return $base;
		}
		$args = array( 'base_version' => $base, 'allow_placeholders' => self::truthy( $request->get_param( 'allow_placeholders' ) ) );
		return self::after_write( $id, TWD_SK_Store::undo( $id, $args ) );
	}

	public static function post_restore( $request ) {
		$id      = (int) $request->get_param( 'id' );
		$version = $request->get_param( 'version' );
		$base    = self::base_version( $request );
		if ( is_wp_error( $base ) ) {
			return $base;
		}
		if ( ! is_numeric( $version ) || (int) $version <= 0 ) {
			return self::error( 'twd_sk_bad_input', 'Choose a version to restore.', 400 );
		}
		$args = array( 'base_version' => $base, 'allow_placeholders' => self::truthy( $request->get_param( 'allow_placeholders' ) ) );
		return self::after_write( $id, TWD_SK_Store::restore( $id, (int) $version, $args ) );
	}

	// -- Helpers ----------------------------------------------------------

	/** Every write says which version it started from, so a stale tab cannot overwrite a newer change. */
	private static function base_version( $request ) {
		$base = $request->get_param( 'base_version' );
		if ( ! is_numeric( $base ) || (int) $base < 0 ) {
			return self::error( 'twd_sk_bad_input', 'The editor did not say which version it started from. Reload the page and try again.', 400 );
		}
		return (int) $base;
	}

	private static function after_write( $id, $result ) {
		if ( is_wp_error( $result ) ) {
			return self::from_store_error( $result );
		}
		$leftovers = isset( $result['report']['leftovers'] ) ? $result['report']['leftovers'] : array();
		$out       = array(
			'version'        => (int) $result['version'],
			'unchanged'      => ! empty( $result['unchanged'] ),
			'report'         => TWD_SK_Report::describe( $result['report'] ),
		);
		// Undo and Restore say in plain words what they undid and what the page shows now.
		foreach ( array( 'undid', 'now', 'target' ) as $k ) {
			if ( isset( $result[ $k ] ) ) {
				$out[ $k ] = $result[ $k ];
			}
		}
		return $out + self::leftover_fields( $leftovers ) + self::versions_payload( $id );
	}

	/** The leftover fields every payload carries: the list (each item has a level) and the counts. */
	private static function leftover_fields( $leftovers ) {
		$levels = TWD_SK_Report::leftover_levels( $leftovers );
		return array(
			'leftovers'      => TWD_SK_Report::describe_leftovers( $leftovers ),
			'leftover_count' => TWD_SK_Report::leftover_total( $leftovers ),
			'must_count'     => $levels['must'],
			'check_count'    => $levels['check'],
		);
	}

	private static function versions_payload( $id ) {
		$current  = TWD_SK_Store::get_current_version_id( $id );
		$versions = array();
		foreach ( TWD_SK_Store::list_versions( $id ) as $v ) {
			$user       = $v['user'] > 0 && function_exists( 'get_userdata' ) ? get_userdata( $v['user'] ) : false;
			$versions[] = array(
				'id'      => (int) $v['id'],
				'created' => (string) $v['created'],
				'user'    => (int) $v['user'],
				'by'      => $user ? (string) $user->display_name : '',
				'note'    => (string) $v['note'],
				'label'   => TWD_SK_Store::label( $v ),
				'kind'    => (string) $v['kind'],
				'from'    => (int) $v['restored_from'],
				'bytes'   => (int) $v['bytes'],
				'fix'     => (int) $v['fix'],
				'current' => (int) $v['id'] === $current,
			);
		}
		return array( 'current_version' => $current, 'versions' => $versions );
	}
}
