<?php
/**
 * TWD_SK_Build: set a whole site up from a brief and pictures, one step at a time.
 *
 * One engine, three ways in: the setup page (Settings > Set up site), the REST routes it calls,
 * and `wp twd-sk build`. Everything it writes goes through the paths the rest of the plugin uses
 * (the brief's apply, the profile, the store and so the sanitiser), so it can never do anything a
 * person could not do by hand.
 *
 * The work is split into steps so no single request runs long: "settings" (type, style, details,
 * menu, facts, logo), then one "page" step per page, then "finish" (front page, checks). Each step
 * can be run again: a page this build made is given a new version, never made twice; a page with
 * the same title that something else made is left alone and listed.
 *
 * Every page is a DRAFT. Nothing is published here.
 *
 * State lives in three options: the draft (the choices on the setup page, so leaving and coming
 * back resumes), the done marker (hides the front-page button), and a short log of what each build
 * did, which the site report prints so a problem can be spotted afterwards.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class TWD_SK_Build {

	const DRAFT     = 'twd_sk_setup_draft';
	const DONE      = 'twd_sk_setup_done';
	const LOG       = 'twd_sk_build_log';
	const MAX_LOG   = 200;
	const META_SLUG = '_twd_sk_build_slug';
	const PAGE_SLUG = 'twd-sk-setup';
	const HANDLE    = 'twd-site-kit-setup';

	public static function steps() {
		return array( 'type', 'style', 'brief', 'pages', 'full', 'pictures', 'build', 'launch' );
	}

	public static function init() {
		add_action( 'admin_menu', array( __CLASS__, 'admin_menu' ) );
		add_action( 'admin_enqueue_scripts', array( __CLASS__, 'admin_assets' ) );
		add_action( 'wp_footer', array( __CLASS__, 'front_button' ) );
	}

	// -- The draft (the choices on the setup page) -------------------------------

	public static function draft() {
		$d = get_option( self::DRAFT, array() );
		$d = is_array( $d ) ? $d : array();
		$packs = TWD_SK_Packs::packs();
		$built = array();
		foreach ( isset( $d['built'] ) && is_array( $d['built'] ) ? $d['built'] : array() as $slug => $id ) {
			if ( is_string( $slug ) && (int) $id > 0 ) {
				$built[ $slug ] = (int) $id;
			}
		}
		return array(
			'step'       => isset( $d['step'] ) && in_array( $d['step'], self::steps(), true ) ? $d['step'] : 'type',
			'mode'       => isset( $d['mode'] ) && is_string( $d['mode'] ) && isset( TWD_SK_Mode::modes()[ $d['mode'] ] ) ? $d['mode'] : TWD_SK_Mode::stored(),
			'pack'       => isset( $d['pack'] ) && is_string( $d['pack'] ) && isset( $packs[ $d['pack'] ] ) ? $d['pack'] : TWD_SK_Packs::active_slug(),
			'brief_text' => isset( $d['brief_text'] ) && is_string( $d['brief_text'] ) ? $d['brief_text'] : '',
			'pictures'   => TWD_SK_Pictures::validate( isset( $d['pictures'] ) ? $d['pictures'] : array() )['valid'],
			'built'      => $built,
			'used'       => isset( $d['used'] ) && is_array( $d['used'] ) ? array_values( array_filter( $d['used'], 'is_string' ) ) : array(),
			'brand'      => ! empty( $d['brand'] ),
			'set'        => isset( $d['set'] ) && is_string( $d['set'] ) && TWD_SK_Sets::get( $d['set'] ) ? $d['set'] : '',
			// The page list from the Pages step, or null to follow the brief.
			'pages'      => isset( $d['pages'] ) && is_array( $d['pages'] ) ? $d['pages'] : null,
			// Fingerprints of the menu and header button this setup filled in itself (0.9.3).
			'auto'       => isset( $d['auto'] ) && is_array( $d['auto'] ) ? array_filter( $d['auto'], 'is_string' ) : array(),
			// The full site set up (0.12.0, a test option): the notes, the answers to the questions, the last check.
			'full'          => ! empty( $d['full'] ),
			'full_notes'    => isset( $d['full_notes'] ) && is_string( $d['full_notes'] ) ? $d['full_notes'] : '',
			'full_answers'  => TWD_SK_Full::clean_answers( isset( $d['full_answers'] ) ? $d['full_answers'] : array() ),
			'full_check'    => isset( $d['full_check'] ) && is_array( $d['full_check'] ) ? $d['full_check'] : null,
			'full_prepared' => ! empty( $d['full_prepared'] ),
			// Chosen with the "Photos?" question: pages are built with layouts that have no picture spots.
			'no_photos'     => ! empty( $d['no_photos'] ),
			// What each page of the last full build still needs a look at, by page slug.
			'flags'         => isset( $d['flags'] ) && is_array( $d['flags'] ) ? $d['flags'] : array(),
			'updated'    => isset( $d['updated'] ) && is_string( $d['updated'] ) ? $d['updated'] : '',
		);
	}

	private static function write_draft( $d ) {
		$d['updated'] = gmdate( 'Y-m-d H:i:s' );
		update_option( self::DRAFT, $d, false );
		return $d;
	}

	/**
	 * Save some of the choices. Unknown keys are ignored. A bad value is refused with a sentence;
	 * the good values in the same request are still saved.
	 *
	 * @return array { draft, errors[] }
	 */
	public static function save_draft( $in ) {
		$d      = self::draft();
		$errors = array();
		$in     = is_array( $in ) ? $in : array();
		if ( isset( $in['mode'] ) ) {
			if ( is_string( $in['mode'] ) && isset( TWD_SK_Mode::modes()[ $in['mode'] ] ) ) {
				$d['mode'] = $in['mode'];
			} else {
				$errors[] = 'Choose one of the site types on offer.';
			}
		}
		if ( isset( $in['pack'] ) ) {
			if ( is_string( $in['pack'] ) && isset( TWD_SK_Packs::packs()[ $in['pack'] ] ) ) {
				$d['pack'] = $in['pack'];
			} else {
				$errors[] = 'Choose one of the styles on offer.';
			}
		}
		if ( isset( $in['brief_text'] ) ) {
			if ( ! is_string( $in['brief_text'] ) ) {
				$errors[] = 'The brief must be text.';
			} elseif ( strlen( $in['brief_text'] ) > TWD_SK_Brief::MAX_BYTES ) {
				$errors[] = 'That brief is too long to keep (' . (int) ( TWD_SK_Brief::MAX_BYTES / 1000 ) . ' KB at most).';
			} else {
				$d['brief_text'] = $in['brief_text'];
			}
		}
		if ( isset( $in['set'] ) ) {
			if ( '' === $in['set'] || ( is_string( $in['set'] ) && TWD_SK_Sets::get( $in['set'] ) ) ) {
				$d['set'] = $in['set'];
			} else {
				$errors[] = 'Choose one of the starting points on offer.';
			}
		}
		if ( array_key_exists( 'brand', $in ) ) {
			$d['brand'] = (bool) $in['brand'] && 'false' !== $in['brand'] && '0' !== $in['brand'];
		}
		if ( ! empty( $in['reset_pages'] ) ) {
			$d['pages'] = null;
		} elseif ( array_key_exists( 'pages', $in ) && null !== $in['pages'] ) {
			$pages = self::clean_pages( $in['pages'] );
			if ( is_wp_error( $pages ) ) {
				$errors[] = $pages->get_error_message();
			} else {
				$d['pages'] = $pages;
			}
		}
		if ( array_key_exists( 'pictures', $in ) ) {
			$p               = TWD_SK_Pictures::validate( $in['pictures'] );
			$d['pictures']   = $p['valid'];
			$errors          = array_merge( $errors, $p['errors'] );
		}
		// The full site set up (0.12.0). Changing the notes or an answer means the last check no longer stands.
		$changed_full = false;
		if ( array_key_exists( 'full', $in ) ) {
			$on = (bool) $in['full'] && 'false' !== $in['full'] && '0' !== $in['full'];
			if ( $on !== $d['full'] ) {
				$d['full']    = $on;
				$changed_full = true;
			}
		}
		if ( isset( $in['full_notes'] ) ) {
			if ( ! is_string( $in['full_notes'] ) ) {
				$errors[] = 'The notes must be text.';
			} elseif ( strlen( $in['full_notes'] ) > TWD_SK_Brief::MAX_NOTES ) {
				$errors[] = 'Those notes are too long (' . (int) ( TWD_SK_Brief::MAX_NOTES / 1000 ) . ' thousand characters at most).';
			} else {
				$clean = TWD_SK_Full::clean_text( $in['full_notes'], TWD_SK_Brief::MAX_NOTES );
				if ( $clean !== $d['full_notes'] ) {
					$d['full_notes'] = $clean;
					$changed_full    = true;
				}
			}
		}
		if ( array_key_exists( 'full_answers', $in ) ) {
			$answers = TWD_SK_Full::clean_answers( $in['full_answers'] );
			if ( $answers !== $d['full_answers'] ) {
				$d['full_answers'] = $answers;
				$changed_full      = true;
			}
		}
		if ( $changed_full ) {
			$packs              = TWD_SK_Packs::packs();
			$d['full_prepared'] = false;
			$d['no_photos']     = isset( $d['full_answers']['photos'] ) && 'none' === $d['full_answers']['photos'];
			if ( isset( $d['full_answers']['style'] ) && isset( $packs[ $d['full_answers']['style'] ] ) ) {
				$d['pack'] = $d['full_answers']['style'];
			}
		}
		// The step moves on only when everything else was accepted, so a reload lands where the problem is.
		if ( isset( $in['step'] ) && ! $errors && in_array( $in['step'], self::steps(), true ) ) {
			$d['step'] = $in['step'];
		}
		return array( 'draft' => self::write_draft( $d ), 'errors' => $errors );
	}

	/**
	 * Check an edited page list (the Pages step). Each page goes through the same checks as a brief's.
	 *
	 * @return array|WP_Error
	 */
	public static function clean_pages( $list ) {
		if ( ! is_array( $list ) || ! $list ) {
			return new WP_Error( 'twd_sk_bad_input', 'The site needs at least one page.' );
		}
		if ( count( $list ) > TWD_SK_Brief::MAX_PAGES ) {
			return new WP_Error( 'twd_sk_bad_input', 'A site can be built with up to ' . TWD_SK_Brief::MAX_PAGES . ' pages at a time.' );
		}
		$out = array();
		foreach ( array_values( $list ) as $i => $pg ) {
			if ( ! is_array( $pg ) ) {
				return new WP_Error( 'twd_sk_bad_input', 'Page ' . ( $i + 1 ) . ' was not understood.' );
			}
			$in = TWD_SK_Recipes::inputs( $pg );
			if ( is_wp_error( $in ) ) {
				return new WP_Error( 'twd_sk_bad_input', 'Page ' . ( $i + 1 ) . ': ' . $in->get_error_message() );
			}
			$seo = array();
			if ( isset( $pg['seo'] ) && is_array( $pg['seo'] ) ) {
				foreach ( array( 'title' => TWD_SK_Seo::MAX_TITLE, 'description' => TWD_SK_Seo::MAX_DESC ) as $k => $max ) {
					if ( isset( $pg['seo'][ $k ] ) ) {
						$seo[ $k ] = TWD_SK_Profile::text( $pg['seo'][ $k ], $max );
					}
				}
			}
			$in['seo'] = array_filter( $seo, 'strlen' );
			$out[]     = $in;
		}
		return $out;
	}

	/** Start again: forget the choices (pages already made stay, as drafts). */
	public static function reset_draft() {
		update_option( self::DRAFT, array(), false );
		return self::draft();
	}

	// -- The full site set up (0.12.0) ----------------------------------------------------

	/** Keep the result of a check. The notes and answers it was made from are already in the draft. */
	public static function store_full_check( $check ) {
		$d                  = self::draft();
		$d['full_check']    = $check;
		$d['full_prepared'] = false;
		return self::write_draft( $d );
	}

	/** Keep the brief and the page list prepared from a ready check, and move on to the pictures. */
	public static function store_prepared( $prepared ) {
		$d = self::draft();
		$pages = self::clean_pages( $prepared['pages'] );
		if ( is_wp_error( $pages ) ) {
			return $pages;
		}
		$flags = defined( 'JSON_UNESCAPED_SLASHES' ) ? JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE : 0;
		$d['brief_text']    = function_exists( 'wp_json_encode' ) ? wp_json_encode( $prepared['brief'], $flags ) : json_encode( $prepared['brief'], $flags );
		$d['pages']         = $pages;
		$d['full_prepared'] = true;
		$d['flags']         = array();
		$d['step']          = 'pictures';
		return self::write_draft( $d );
	}

	// -- The plan -------------------------------------------------------------------

	/** The brief as data: null when there is none, a WP_Error when it cannot be read. */
	private static function brief_data( $draft ) {
		if ( '' === trim( $draft['brief_text'] ) ) {
			return null;
		}
		return TWD_SK_Brief::parse( $draft['brief_text'] );
	}

	/** The brief with the setup page's own choices on top (they always win). */
	private static function effective_brief( $draft, &$errors ) {
		$data = self::brief_data( $draft );
		if ( is_wp_error( $data ) ) {
			$errors[] = 'The brief: ' . $data->get_error_message();
			$data     = null;
		}
		$data          = $data ? $data : array( 'schema' => TWD_SK_Brief::SCHEMA );
		$data['mode']  = $draft['mode'];
		$style         = isset( $data['style'] ) && is_array( $data['style'] ) ? $data['style'] : array();
		$style['pack'] = $draft['pack'];
		$data['style'] = $style;
		return $data;
	}

	/** A unique slug for each planned page. */
	private static function unique_slug( $title, &$taken ) {
		$base = TWD_SK_Pictures::slug( $title );
		$base = '' === $base ? 'page' : $base;
		$slug = $base;
		$n    = 2;
		while ( isset( $taken[ $slug ] ) ) {
			$slug = $base . '-' . $n++;
		}
		$taken[ $slug ] = true;
		return $slug;
	}

	/**
	 * What a build would do now. Changes nothing.
	 *
	 * @return array { pages[], steps[], items[], errors[], ignored[], blocking[], ai, pictures }
	 */
	public static function plan( $draft = null ) {
		$draft  = null === $draft ? self::draft() : $draft;
		$errors = array();
		$data   = self::effective_brief( $draft, $errors );

		// Brief::plan reads labels from the mode in the brief, so the plan reads in the chosen type.
		$bp     = TWD_SK_Brief::plan( $data, false );
		$errors = array_merge( $errors, $bp['errors'] );
		$pages  = $bp['changes']['pages'];
		$source = 'brief';
		// No pages in the brief: the chosen starter set's pages, if one was chosen.
		if ( ! $pages && '' !== $draft['set'] ) {
			$set    = TWD_SK_Sets::get( $draft['set'] );
			$pages  = $set ? $set['pages'] : array();
			$source = $set ? 'set' : 'brief';
		}
		if ( null !== $draft['pages'] ) {
			// The Pages step's list wins over the brief's, checked again in case the rules changed.
			$edited = self::clean_pages( $draft['pages'] );
			if ( is_wp_error( $edited ) ) {
				$errors[] = 'The page list: ' . $edited->get_error_message() . ' The brief\'s pages are used instead.';
			} else {
				$pages  = $edited;
				$source = 'edited';
			}
		}
		if ( ! $pages ) {
			foreach ( array( 'home', 'about', 'contact' ) as $type ) {
				$in = TWD_SK_Recipes::inputs( array( 'type' => $type ) );
				if ( ! is_wp_error( $in ) ) {
					$in['seo'] = array();
					$pages[]   = $in;
				}
			}
		}
		$has_home = false;
		foreach ( $pages as $pg ) {
			$has_home = $has_home || 'home' === $pg['type'];
		}
		if ( ! $has_home ) {
			$home        = TWD_SK_Recipes::inputs( array( 'type' => 'home' ) );
			$home['seo'] = array();
			array_unshift( $pages, $home );
		}

		$taken = array();
		$out   = array();
		foreach ( $pages as $pg ) {
			$slug   = self::unique_slug( $pg['title'], $taken );
			$mine   = isset( $draft['built'][ $slug ] ) && self::live( $draft['built'][ $slug ] ) ? $draft['built'][ $slug ] : 0;
			$other  = $mine ? 0 : self::find_page( $pg['title'] );
			$out[]  = array(
				'type'   => $pg['type'],
				'title'  => $pg['title'],
				'topic'  => $pg['topic'],
				'notes'  => $pg['notes'],
				'parts'  => isset( $pg['parts'] ) ? $pg['parts'] : TWD_SK_Recipes::get( $pg['type'] )['parts'],
				'seo'    => isset( $pg['seo'] ) ? $pg['seo'] : array(),
				'slug'   => $slug,
				'id'     => $mine ? $mine : $other,
				'action' => $mine ? 'rebuild' : ( $other ? 'skip' : 'create' ),
			);
		}

		$ai    = TWD_SK_AI::available();
		$steps = array( array( 'id' => 'settings', 'index' => 0, 'label' => 'Save the site type, style, details, menu and facts' ) );
		foreach ( $out as $i => $pg ) {
			$label = 'skip' === $pg['action'] ? 'Leave "' . $pg['title'] . '" alone (a page with that title already exists)' : ( 'rebuild' === $pg['action'] ? 'Make "' . $pg['title'] . '" again (a new version)' : 'Make the "' . $pg['title'] . '" page' );
			$steps[] = array( 'id' => 'page', 'index' => $i, 'label' => $label );
		}
		$steps[] = array( 'id' => 'finish', 'index' => 0, 'label' => 'Set the front page and check every page' );

		$raw = self::brief_data( $draft );
		return array(
			'pages'       => $out,
			// Set when there is brief text that cannot be read: the build will not run until it is fixed.
			'brief_error' => is_wp_error( $raw ) ? $raw->get_error_message() : '',
			'source'   => $source,
			'steps'    => $steps,
			'items'    => $bp['items'],
			'errors'   => $errors,
			'ignored'  => $bp['ignored'],
			'blocking' => $bp['blocking'],
			'ai'       => $ai,
			'pictures' => array( 'slots' => count( $draft['pictures']['slots'] ), 'custom' => count( $draft['pictures']['custom'] ) ),
			'full'     => (bool) $draft['full'],
			'full_ready' => ! $draft['full'] || $draft['full_prepared'],
		);
	}

	/** True when the post exists and is not in the bin. */
	private static function live( $id ) {
		$post = $id ? get_post( (int) $id ) : null;
		return $post && 'trash' !== $post->post_status;
	}

	/** A page with this title (any status), or 0. */
	private static function find_page( $title ) {
		$ids = get_posts( array( 'post_type' => 'page', 'post_status' => 'any', 'numberposts' => 300, 'fields' => 'ids' ) );
		foreach ( (array) $ids as $id ) {
			$post = get_post( $id );
			if ( $post && 0 === strcasecmp( trim( (string) $post->post_title ), trim( (string) $title ) ) ) {
				return (int) $id;
			}
		}
		return 0;
	}

	// -- Running a step -------------------------------------------------------------

	/**
	 * Run one step.
	 *
	 * @param string $step  settings, page or finish.
	 * @param int    $index The page's place in the plan (page steps only).
	 * @param array  $opts  ai (bool, default true): write pages with the AI when it is available.
	 * @return array|WP_Error { step, ok, report[], page? , pages? }
	 */
	public static function run_step( $step, $index = 0, $opts = array() ) {
		$draft = self::draft();
		$plan  = self::plan( $draft );
		if ( '' !== $plan['brief_error'] && in_array( $step, array( 'settings', 'page', 'finish' ), true ) ) {
			return new WP_Error( 'twd_sk_bad_brief', 'The brief could not be read, so nothing was changed. Fix it on the Brief step (Choose the brief file is the safest way), or empty the brief box to build without one. ' . $plan['brief_error'] );
		}
		// The full site set up never builds until the questions are answered and the notes prepared (0.12.0).
		if ( $draft['full'] && ! $draft['full_prepared'] && in_array( $step, array( 'settings', 'page', 'finish' ), true ) ) {
			return new WP_Error( 'twd_sk_not_ready', 'The full site set up is not ready. Answer the questions on the Full set up step first; nothing was changed.' );
		}
		if ( $draft['full'] && ! TWD_SK_AI::available() && in_array( $step, array( 'settings', 'page' ), true ) ) {
			return new WP_Error( 'twd_sk_ai_unavailable', 'The full site set up writes every page with the AI, and the AI is not available now. Nothing was changed.' );
		}
		try {
			if ( 'settings' === $step ) {
				$out = self::step_settings( $draft, $plan );
			} elseif ( 'page' === $step ) {
				$out = self::step_page( $draft, $plan, (int) $index, ! isset( $opts['ai'] ) || $opts['ai'] );
			} elseif ( 'finish' === $step ) {
				$out = self::step_finish( $plan );
			} else {
				return new WP_Error( 'twd_sk_bad_input', 'There is no build step called that.' );
			}
		} catch ( \Throwable $e ) {
			self::log( 'Step ' . $step . ' stopped with an error: ' . TWD_SK_Profile::text( $e->getMessage(), 200 ) );
			return new WP_Error( 'twd_sk_build_failed', 'That step stopped with an error, so it may be half done. Run it again; it is safe to repeat.' );
		}
		if ( is_wp_error( $out ) ) {
			self::log( 'Step ' . $step . ( 'page' === $step ? ' ' . (int) $index : '' ) . ' failed: ' . $out->get_error_message() );
			return $out;
		}
		foreach ( $out['report'] as $line ) {
			self::log( $line );
		}
		return $out;
	}

	private static function fingerprint( $value ) {
		return md5( (string) json_encode( $value ) );
	}

	/**
	 * True when this profile field still holds what the setup filled in itself. A fingerprint is kept
	 * from 0.9.3 on. Before that, a menu counts when every link is a page this setup made, labelled with
	 * its title (or the home page), and a header button when it is the "Get in touch" one it adds.
	 */
	private static function setup_filled( $field, $profile, $draft ) {
		$value = $profile[ $field ];
		if ( 'menu' === $field ? ! $value : '' === trim( (string) $value ) ) {
			return false;
		}
		if ( isset( $draft['auto'][ $field ] ) ) {
			return self::fingerprint( $value ) === $draft['auto'][ $field ];
		}
		$built = $draft['built'];
		if ( ! $built ) {
			return false;
		}
		if ( 'menu' === $field ) {
			$titles = array();
			foreach ( $built as $id ) {
				$post = get_post( $id );
				if ( $post ) {
					$titles[ trim( (string) $post->post_title ) ] = true;
				}
			}
			foreach ( $value as $item ) {
				if ( ! empty( $item['children'] ) ) {
					return false;
				}
				$label = trim( (string) $item['label'] );
				if ( '/' === $item['url'] ) {
					// The home link the setup adds is labelled with its home page's title.
					if ( ! isset( $titles[ $label ] ) ) {
						return false;
					}
					continue;
				}
				$slug = substr( (string) $item['url'], 1 );
				$post = isset( $built[ $slug ] ) ? get_post( $built[ $slug ] ) : null;
				if ( ! $post || 0 !== strcmp( trim( (string) $post->post_title ), $label ) ) {
					return false;
				}
			}
			return true;
		}
		$url = (string) $profile['cta_url'];
		return 'Get in touch' === $profile['cta_label'] && '/' === substr( $url, 0, 1 ) && isset( $built[ substr( $url, 1 ) ] );
	}

	private static function step_settings( $draft, $plan ) {
		if ( $plan['blocking'] ) {
			return new WP_Error( 'twd_sk_contrast', 'The style in the brief would make some text hard to read, so nothing was saved: ' . implode( '; ', $plan['blocking'] ) . '.' );
		}
		$errors = array();
		$data   = self::effective_brief( $draft, $errors );
		// The menu and header button this setup filled in itself, unchanged since, are filled in again,
		// so building after a change to the brief or the page list keeps them right. Anything a person
		// typed or changed in the Site tab is never touched.
		$before  = TWD_SK_Profile::get();
		$refresh = array();
		if ( self::setup_filled( 'menu', $before, $draft ) ) {
			$refresh['menu'] = array();
		}
		// The button's text and link go together: if a person changed either, both are kept.
		if ( self::setup_filled( 'cta_label', $before, $draft ) && self::setup_filled( 'cta_url', $before, $draft ) ) {
			$refresh['cta_label'] = '';
			$refresh['cta_url']   = '';
		}
		if ( $refresh ) {
			TWD_SK_Profile::save( $refresh );
			$before = TWD_SK_Profile::get();
		}
		$r      = TWD_SK_Brief::apply( $data, array( 'overwrite' => false, 'build_pages' => false ) );
		$report = array_merge( $r['applied'], $r['skipped'], $r['errors'], $errors );

		$profile = TWD_SK_Profile::get();
		$save    = array();
		if ( isset( $draft['pictures']['slots']['logo'] ) ) {
			$save['logo_id'] = $draft['pictures']['slots']['logo']['id'];
		}
		// A basic menu from the planned pages when there is none yet.
		if ( ! $profile['menu'] ) {
			$menu = array();
			foreach ( $plan['pages'] as $pg ) {
				if ( 'skip' === $pg['action'] || count( $menu ) >= TWD_SK_Profile::MAX_MENU ) {
					continue;
				}
				$menu[] = array( 'label' => $pg['title'], 'url' => 'home' === $pg['type'] ? '/' : '/' . $pg['slug'], 'children' => array() );
			}
			if ( $menu ) {
				$save['menu'] = $menu;
			}
		}
		if ( '' === trim( (string) $profile['cta_url'] ) ) {
			foreach ( $plan['pages'] as $pg ) {
				if ( 'contact' === $pg['type'] && 'skip' !== $pg['action'] ) {
					$save['cta_label'] = 'Get in touch';
					$save['cta_url']   = '/' . $pg['slug'];
					break;
				}
			}
		}
		// Colours from the logo, when asked for and there is a logo. The contrast checks still decide.
		if ( $draft['brand'] && isset( $draft['pictures']['slots']['logo'] ) ) {
			$brand = TWD_SK_Brand::from_attachment( $draft['pictures']['slots']['logo']['id'] );
			if ( is_wp_error( $brand ) ) {
				$report[] = 'Colours from the logo: ' . $brand->get_error_message();
			} elseif ( $brand['tokens'] ) {
				$applied = TWD_SK_Site::apply( null, array_merge( TWD_SK_Packs::overrides(), $brand['tokens'] ) );
				$report[] = is_wp_error( $applied ) ? 'Colours from the logo were not used: ' . $applied->get_error_message() : 'Colours taken from the logo (' . count( $brand['tokens'] ) . ' colours).' . ( $brand['dropped'] ? ' Kept the style\'s own for: ' . implode( ', ', $brand['dropped'] ) . ', to keep text easy to read.' : '' );
			}
		}
		if ( $save ) {
			$s = TWD_SK_Profile::save( $save );
			if ( is_wp_error( $s ) ) {
				$report[] = 'The logo, menu or header button could not be saved: ' . $s->get_error_message();
			} else {
				if ( isset( $save['logo_id'] ) ) {
					$report[] = 'Logo set.';
				}
				if ( isset( $save['menu'] ) ) {
					$report[] = 'Menu made from the pages (' . count( $save['menu'] ) . ' links).';
				}
				if ( isset( $save['cta_url'] ) ) {
					$report[] = 'Header button set to the contact page.';
				}
			}
		}
		$focals = TWD_SK_Pictures::save_focals( $draft['pictures'] );
		if ( $focals ) {
			$report[] = 'Saved where to keep ' . $focals . ' picture' . ( 1 === $focals ? '' : 's' ) . ' in view.';
		}
		$after = TWD_SK_Profile::get();
		foreach ( array( 'menu', 'cta_label', 'cta_url' ) as $field ) {
			$empty_before = 'menu' === $field ? ! $before[ $field ] : '' === trim( (string) $before[ $field ] );
			$filled_now   = 'menu' === $field ? (bool) $after[ $field ] : '' !== trim( (string) $after[ $field ] );
			if ( $empty_before && $filled_now ) {
				$draft['auto'][ $field ] = self::fingerprint( $after[ $field ] );
			}
		}
		if ( isset( $refresh['menu'] ) && $after['menu'] ) {
			$report[] = 'Menu brought up to date with the pages.';
		}
		$draft['used'] = array();
		self::write_draft( $draft );
		self::log( '--- Build started (' . $draft['mode'] . ', ' . $draft['pack'] . ', ' . count( $plan['pages'] ) . ' pages) ---' );
		$report   = $report ? $report : array( 'Settings saved.' );
		$report[] = 'Header and footer placed by: ' . TWD_SK_Chrome::placement_status() . '.';
		return array( 'step' => 'settings', 'ok' => true, 'report' => $report );
	}

	private static function step_page( $draft, $plan, $index, $use_ai ) {
		if ( ! isset( $plan['pages'][ $index ] ) ) {
			return new WP_Error( 'twd_sk_bad_input', 'That page is not in the plan any more. Reload the setup page.' );
		}
		$pg = $plan['pages'][ $index ];
		if ( 'skip' === $pg['action'] ) {
			return array( 'step' => 'page', 'ok' => true, 'report' => array( '"' . $pg['title'] . '" already exists, so it was left alone.' ), 'page' => self::page_summary( $pg['id'], false ) );
		}
		$in     = array( 'type' => $pg['type'], 'title' => $pg['title'], 'topic' => $pg['topic'], 'notes' => $pg['notes'], 'parts' => $pg['parts'] );
		$full   = ! empty( $draft['full'] );
		if ( $full ) {
			// Only the supplied facts, and no [PLACEHOLDER] for text (0.12.0).
			$in['strict'] = true;
			$use_ai       = true;
		}
		$report = array();
		$ai     = $use_ai && TWD_SK_AI::available();
		$html   = '';
		if ( $ai ) {
			$html = TWD_SK_AI::draft_page_html( $in, TWD_SK_Pictures::prompt_block( $draft['pictures'], $pg['slug'] ) );
			if ( is_wp_error( $html ) ) {
				if ( $full ) {
					// An outline full of placeholders is exactly what this option exists to avoid.
					return new WP_Error( 'twd_sk_ai_failed', '"' . $pg['title'] . '": the AI could not write it (' . $html->get_error_message() . ') Nothing was saved for this page. Try again.' );
				}
				$report[] = '"' . $pg['title'] . '": the AI could not write it (' . $html->get_error_message() . ') The outline was saved instead; use Fill with AI on the page later.';
				$ai       = false;
			}
		}
		if ( ! $ai ) {
			$html = TWD_SK_Recipes::skeleton_for( $in );
		}
		$used   = $draft['used'];
		$placed = TWD_SK_Pictures::place( $html, $draft['pictures'], $pg['slug'], $pg['type'], $used );
		$report = array_merge( $report, $placed['report'] );
		$flags  = array();
		if ( $full ) {
			// Picture spots get a marker that says what photo is wanted; the page is checked for anything the facts do not back up.
			$fin            = TWD_SK_Full::finalise( $placed['html'], $pg, $draft );
			$placed['html'] = $fin['html'];
			$flags          = $fin['flags'];
			if ( $fin['spots'] ) {
				$report[] = '"' . $pg['title'] . '": ' . $fin['spots'] . ' place' . ( 1 === $fin['spots'] ? '' : 's' ) . ' for a photo, each marked with what is wanted and a Pexels search.';
			}
			foreach ( $flags as $flag ) {
				$report[] = '"' . $pg['title'] . '" needs a look: ' . $flag;
			}
		}

		$id = 'rebuild' === $pg['action'] ? (int) $pg['id'] : 0;
		if ( ! $id ) {
			$id = TWD_SK_Template::create_page( $pg['title'], 'blank', $pg['slug'] );
			if ( is_wp_error( $id ) ) {
				return $id;
			}
			update_post_meta( $id, self::META_SLUG, $pg['slug'] );
			// Recorded at once, so running the step again updates this page instead of making another.
			$draft['built'][ $pg['slug'] ] = (int) $id;
			self::write_draft( $draft );
		}
		$saved = TWD_SK_Store::save( $id, $placed['html'], array(
			'base_version' => TWD_SK_Store::get_current_version_id( $id ),
			'note'         => ( $ai ? 'Written by AI from the ' . TWD_SK_Mode::w( 'facts' ) : 'Outline' ) . ' (site setup, ' . $pg['type'] . ')',
		) );
		if ( is_wp_error( $saved ) ) {
			return $saved;
		}
		if ( $pg['seo'] ) {
			$s = TWD_SK_Seo::write( $id, $pg['seo'] );
			if ( is_wp_error( $s ) ) {
				$report[] = 'Search wording for "' . $pg['title'] . '" was not saved: ' . $s->get_error_message();
			}
		}
		$draft['used'] = $used;
		if ( $full ) {
			$draft['flags'][ $pg['slug'] ] = $flags;
		}
		self::write_draft( $draft );
		$summary  = self::page_summary( $id, $ai );
		$summary['flags'] = $flags;
		$report[] = '"' . $pg['title'] . '" ' . ( 'rebuild' === $pg['action'] ? 'made again' : 'made' ) . ( $ai ? ' with AI' : ' as an outline' ) . ' (draft, ID ' . (int) $id . '): ' . $summary['must'] . ' to fix, ' . $summary['check'] . ' to check.';
		return array( 'step' => 'page', 'ok' => true, 'report' => $report, 'page' => $summary );
	}

	private static function page_summary( $id, $ai ) {
		$id     = (int) $id;
		$levels = TWD_SK_Sanitizer::find_leftovers_by_level( TWD_SK_Store::get_current( $id ) );
		$post   = get_post( $id );
		return array(
			'id'    => $id,
			'title' => $post ? (string) $post->post_title : '',
			'url'   => (string) get_permalink( $id ),
			'ai'    => (bool) $ai,
			'must'  => TWD_SK_Report::leftover_total( $levels['must'] ),
			'check' => TWD_SK_Report::leftover_total( $levels['check'] ),
		);
	}

	private static function step_finish( $plan ) {
		$draft  = self::draft();
		$report = array();
		$pages  = array();
		$home   = 0;
		$about  = 0;
		foreach ( $plan['pages'] as $pg ) {
			$id = isset( $draft['built'][ $pg['slug'] ] ) ? $draft['built'][ $pg['slug'] ] : 0;
			if ( ! $id || ! self::live( $id ) ) {
				continue;
			}
			if ( 'home' === $pg['type'] && ! $home ) {
				$home = $id;
			}
			if ( 'about' === $pg['type'] && ! $about ) {
				$about = $id;
			}
			$pages[] = self::page_summary( $id, false );
		}
		if ( $home ) {
			if ( TWD_SK_Setup::front_page_blocked() ) {
				$report[] = 'A published front page is already set, so it was not changed.';
			} else {
				update_option( 'show_on_front', 'page' );
				update_option( 'page_on_front', $home );
				$report[] = 'Home is now the front page. It is a draft, so visitors see nothing there until it is published.';
			}
		}
		// Pages an earlier build made that are no longer in the plan: listed, never deleted.
		$planned = array();
		foreach ( $plan['pages'] as $pg ) {
			$planned[ $pg['slug'] ] = true;
		}
		$stale = array();
		foreach ( $draft['built'] as $slug => $id ) {
			if ( ! isset( $planned[ $slug ] ) && self::live( $id ) ) {
				$stale[ (int) $id ] = (string) get_post( $id )->post_title;
			}
		}
		$current_about = (int) TWD_SK_Profile::get()['about_page_id'];
		if ( $about && ( ! $current_about || isset( $stale[ $current_about ] ) || ! self::live( $current_about ) ) ) {
			TWD_SK_Profile::save( array( 'about_page_id' => $about ) );
		}
		update_option( self::DONE, gmdate( 'Y-m-d H:i:s' ) );
		$must     = 0;
		foreach ( $pages as $p ) {
			$must += $p['must'];
		}
		$report[] = count( $pages ) . ' draft page' . ( 1 === count( $pages ) ? '' : 's' ) . ' ready. ' . ( $must ? $must . ' thing' . ( 1 === $must ? '' : 's' ) . ' still to fix before they can be published (missing details marked [PLACEHOLDER]).' : 'Nothing is marked as missing.' );
		if ( ! empty( $draft['full'] ) ) {
			$looks = 0;
			foreach ( $draft['flags'] as $list ) {
				$looks += is_array( $list ) ? count( $list ) : 0;
			}
			$report[] = $looks ? $looks . ' thing' . ( 1 === $looks ? '' : 's' ) . ' on the pages the facts did not back up (listed above). Read every page before you publish it.' : 'Nothing on the pages went beyond the facts you supplied. Still read every page before you publish it.';
			if ( ! empty( $draft['full_check']['left_out'] ) ) {
				$report[] = 'Left out because nothing was supplied: ' . implode( ' ', array_map( function ( $l ) {
					return rtrim( $l, '.' ) . '.';
				}, $draft['full_check']['left_out'] ) );
			}
			if ( ! empty( $draft['no_photos'] ) ) {
				$report[] = 'No photo spots were made. If you add photos later, Pexels searches that suit a site like this: ' . implode( ', ', TWD_SK_Full::photo_ideas( $draft['mode'] ) ) . '.';
			} else {
				$report[] = 'Open each page, press Visual editor and click a marked photo spot to upload your photo. The marker says what is wanted and gives a Pexels search to start from. A page with a photo spot cannot be published until the spot is filled or removed.';
			}
		}
		if ( $stale ) {
			$report[] = 'An earlier build also made these pages, which are no longer in the plan: "' . implode( '", "', $stale ) . '". They are still drafts and were not changed. Move them to the bin in Pages if you do not need them.';
		}
		$report[] = '--- Build finished ---';
		return array( 'step' => 'finish', 'ok' => true, 'report' => $report, 'pages' => $pages );
	}

	// -- The log ----------------------------------------------------------------------

	public static function log( $line ) {
		$log   = get_option( self::LOG, array() );
		$log   = is_array( $log ) ? $log : array();
		$log[] = gmdate( 'Y-m-d H:i' ) . ' ' . TWD_SK_Profile::text( $line, 300 );
		update_option( self::LOG, array_slice( $log, -self::MAX_LOG ), false );
	}

	/** The newest lines of the build log. */
	public static function log_lines( $n = 30 ) {
		$log = get_option( self::LOG, array() );
		return is_array( $log ) ? array_slice( $log, -$n ) : array();
	}

	// -- Is the site set up? -------------------------------------------------------------

	/** True once a build finished, or when the site already has kit pages (an older site). */
	public static function is_set_up() {
		if ( get_option( self::DONE, '' ) ) {
			return true;
		}
		$cached = get_transient( 'twd_sk_has_kit_pages' );
		if ( 'yes' === $cached || 'no' === $cached ) {
			return 'yes' === $cached;
		}
		$found = false;
		foreach ( (array) get_posts( array( 'post_type' => 'page', 'post_status' => 'any', 'numberposts' => 300, 'fields' => 'ids' ) ) as $id ) {
			if ( TWD_SK_Page::is_kit_page( (int) $id ) ) {
				$found = true;
				break;
			}
		}
		set_transient( 'twd_sk_has_kit_pages', $found ? 'yes' : 'no', 600 );
		return $found;
	}

	// -- The admin page and the front-page button ---------------------------------------------

	public static function admin_url() {
		return function_exists( 'admin_url' ) ? admin_url( 'options-general.php?page=' . self::PAGE_SLUG ) : '';
	}

	public static function admin_menu() {
		if ( TWD_SK_Safe::on() ) {
			return;
		}
		add_options_page( 'Set up site', 'Set up site', 'manage_options', self::PAGE_SLUG, array( __CLASS__, 'render_admin_page' ) );
	}

	public static function render_admin_page() {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}
		echo '<div class="wrap twd-sk-setup-wrap"><h1>' . esc_html__( 'Set up site', 'twd-site-kit' ) . '</h1>'
			. '<div id="twd-sk-setup" class="twd-sk-setup"><p>' . esc_html__( 'Loading...', 'twd-site-kit' ) . '</p></div>'
			. '<noscript><p>' . esc_html__( 'The setup page needs JavaScript. You can also use wp twd-sk build on the command line.', 'twd-site-kit' ) . '</p></noscript></div>';
	}

	public static function admin_assets( $hook ) {
		if ( 'settings_page_' . self::PAGE_SLUG !== $hook || TWD_SK_Safe::on() || ! current_user_can( 'manage_options' ) ) {
			return;
		}
		$ver = defined( 'TWD_SK_VERSION' ) ? TWD_SK_VERSION : false;
		if ( function_exists( 'wp_enqueue_media' ) ) {
			wp_enqueue_media();
		}
		wp_enqueue_style( self::HANDLE, TWD_SK_URL . 'assets/twd-site-kit-setup.css', array(), $ver );
		wp_register_script( self::HANDLE, TWD_SK_URL . 'assets/twd-site-kit-setup.js', array(), $ver, true );
		wp_localize_script( self::HANDLE, 'TWD_SK_SETUP', array(
			'restUrl' => esc_url_raw( rest_url( TWD_SK_REST::ROUTE_NS ) ),
			'nonce'   => wp_create_nonce( 'wp_rest' ),
			'homeUrl' => esc_url_raw( home_url( '/' ) ),
		) );
		wp_enqueue_script( self::HANDLE );
	}

	/** A small "Set this site up" link on the front end, for administrators only, until the site is set up. */
	public static function front_button() {
		if ( is_admin() || TWD_SK_Safe::on() || ! is_user_logged_in() || ! current_user_can( 'manage_options' ) || self::is_set_up() ) {
			return;
		}
		echo '<style>.twd-sk-setup-link{position:fixed;right:24px;bottom:24px;z-index:99990;padding:12px 20px;border-radius:999px;background:#1d2327;color:#fff;font:600 15px/1.2 system-ui,sans-serif;text-decoration:none;box-shadow:0 4px 16px rgba(0,0,0,.25)}.twd-sk-setup-link:hover,.twd-sk-setup-link:focus-visible{background:#2271b1;color:#fff;outline:2px solid #fff;outline-offset:2px}</style>'
			. '<a class="twd-sk-setup-link" href="' . esc_url( self::admin_url() ) . '">' . esc_html__( 'Set this site up', 'twd-site-kit' ) . '</a>';
	}
}
