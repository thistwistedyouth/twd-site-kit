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
			register_rest_route( self::ROUTE_NS, '/pages/' . $id . '/remix', array(
				'methods'             => 'POST',
				'callback'            => array( __CLASS__, 'post_remix' ),
				'permission_callback' => array( __CLASS__, 'can_remix_page' ),
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

	public static function can_write_page( $request ) {
		return self::guard( $request, 'write' );
	}

	/** Site settings need manage_options (administrators), and no page ID. */
	public static function can_manage_site( $request ) {
		return self::guard( $request, 'write', false, 'manage_options' );
	}

	public static function can_read_site( $request ) {
		return self::guard( $request, 'read', false, 'manage_options' );
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

	// -- Practice facts and new pages from them -------------------------------

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

	/** Draft a new page from the practice facts. Creates a DRAFT only. */
	public static function post_generate( $request ) {
		$result = TWD_SK_AI::generate_page( array(
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
		$out = TWD_SK_Site::apply( $pack, $overrides );
		if ( is_wp_error( $out ) ) {
			$data = $out->get_error_data();
			return self::error( $out->get_error_code(), $out->get_error_message(), 'twd_sk_contrast' === $out->get_error_code() ? 422 : 400, is_array( $data ) ? $data : array() );
		}
		return $out;
	}

	public static function post_site_reset( $request ) {
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
				'pack_defaults'   => TWD_SK_Packs::chrome_defaults(),
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
		return array(
			'token'          => $token,
			'preview_url'    => TWD_SK_Preview::url( $id, $token ),
			'expires_in'     => TWD_SK_Preview::TTL,
			'bytes'          => strlen( $result['html'] ),
			'report'         => TWD_SK_Report::describe( $result['report'] ),
			'removed_total'  => (int) $result['report']['total'],
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
		return self::after_write( $id, TWD_SK_Store::undo( $id, array( 'base_version' => $base ) ) );
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
		return self::after_write( $id, TWD_SK_Store::restore( $id, (int) $version, array( 'base_version' => $base ) ) );
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
		return array(
			'version'        => (int) $result['version'],
			'unchanged'      => ! empty( $result['unchanged'] ),
			'report'         => TWD_SK_Report::describe( $result['report'] ),
		) + self::leftover_fields( $leftovers ) + self::versions_payload( $id );
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
				'kind'    => (string) $v['kind'],
				'bytes'   => (int) $v['bytes'],
				'current' => (int) $v['id'] === $current,
			);
		}
		return array( 'current_version' => $current, 'versions' => $versions );
	}
}
