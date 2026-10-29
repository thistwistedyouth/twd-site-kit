<?php
/**
 * TWD_SK_Editor: loads the front-end editor (the "Edit with AI" button and pop-up).
 *
 * Nothing here is loaded for visitors. The script, the stylesheet and the button
 * only exist for a signed-in user who can edit pages, on the front end. Never in
 * wp-admin, never inside the Elementor editor or the customizer, and never inside the
 * preview frame (a preview gets the stylesheet alone, for its "Preview only" bar).
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class TWD_SK_Editor {

	const HANDLE      = 'twd-site-kit-editor';
	const HANDLE_SITE = 'twd-site-kit-editor-site';
	const HANDLE_SEO  = 'twd-site-kit-editor-seo';
	const HANDLE_INLINE = 'twd-site-kit-inline';

	/** User meta: set to 1 when this person has switched the editing tools off (0.14.1). */
	const META_TOOLS = 'twd_sk_tools_off';
	// 0.15.0: where the Edit with AI window sits for a person (docked, free or centred, its size, the minimised pencil's place).
	const META_WINDOW = 'twd_sk_window';
	// 0.15.0: the site switch that offers Box, Border and Padding in the Look panel to every editor (administrators always have them).
	const OPTION_ADVANCED_LOOKS = 'twd_sk_looks_advanced';
	// 0.15.0: a person's switch back to the classic editor (the full-screen pop-up and the separate Visual editor card)
	// while the one editing window is new. Off by default: the one window.
	const META_CLASSIC = 'twd_sk_classic';
	// Session 5 (29 Oct 2026): an administrator's "View as" choice. 'client' shows them the Simple editor, exactly as a
	// person who is not an administrator gets it; anything else (or no meta) is the full editor.
	const META_VIEW = 'twd_sk_view_as';

	/**
	 * True when the signed-in person has switched the editing tools off: the floating buttons, the Edit header and
	 * footer pills and the version label stay hidden until they switch them on again. It hides launchers only and is
	 * kept per person on the server, never in the browser.
	 */
	public static function tools_off( $user_id = 0 ) {
		$uid = $user_id > 0 ? (int) $user_id : ( function_exists( 'get_current_user_id' ) ? (int) get_current_user_id() : 0 );
		return $uid > 0 && '1' === (string) get_user_meta( $uid, self::META_TOOLS, true );
	}

	/** Remember the switch for a person. Switching on removes the meta, so nothing is left behind. */
	public static function set_tools_off( $off, $user_id = 0 ) {
		$uid = $user_id > 0 ? (int) $user_id : ( function_exists( 'get_current_user_id' ) ? (int) get_current_user_id() : 0 );
		if ( $uid <= 0 ) {
			return false;
		}
		if ( $off ) {
			update_user_meta( $uid, self::META_TOOLS, '1' );
		} else {
			delete_user_meta( $uid, self::META_TOOLS );
		}
		return true;
	}

	/**
	 * The Edit with AI window's place for a person (0.15.0): mode (centre, left, right, free), its size and place
	 * when free, the minimised pencil's place, and whether Escape minimises instead of closing. Always a complete,
	 * checked set, so the browser never reads a bad value; the default is the centred window as before.
	 */
	public static function window_defaults() {
		return array( 'mode' => 'centre', 'x' => 0, 'y' => 0, 'w' => 0, 'h' => 0, 'mx' => -1, 'my' => -1, 'esc' => 'close' );
	}

	/** A checked window state: unknown keys dropped, numbers bounded, words from the list; anything else is the default. */
	public static function clean_window( $state ) {
		$out = self::window_defaults();
		if ( ! is_array( $state ) ) {
			return $out;
		}
		if ( isset( $state['mode'] ) && in_array( $state['mode'], array( 'centre', 'left', 'right', 'free' ), true ) ) {
			$out['mode'] = $state['mode'];
		}
		foreach ( array( 'x', 'y', 'w', 'h' ) as $k ) {
			if ( isset( $state[ $k ] ) && is_numeric( $state[ $k ] ) ) {
				$out[ $k ] = max( 0, min( 20000, (int) $state[ $k ] ) );
			}
		}
		foreach ( array( 'mx', 'my' ) as $k ) {
			if ( isset( $state[ $k ] ) && is_numeric( $state[ $k ] ) ) {
				$out[ $k ] = max( -1, min( 20000, (int) $state[ $k ] ) );
			}
		}
		if ( isset( $state['esc'] ) && in_array( $state['esc'], array( 'close', 'minimise' ), true ) ) {
			$out['esc'] = $state['esc'];
		}
		return $out;
	}

	public static function window_state( $user_id = 0 ) {
		$uid = $user_id > 0 ? (int) $user_id : ( function_exists( 'get_current_user_id' ) ? (int) get_current_user_id() : 0 );
		$raw = $uid > 0 ? get_user_meta( $uid, self::META_WINDOW, true ) : '';
		return self::clean_window( is_array( $raw ) ? $raw : array() );
	}

	/** Remember the window for a person. The default state removes the meta, so nothing is left behind. */
	public static function set_window_state( $state, $user_id = 0 ) {
		$uid = $user_id > 0 ? (int) $user_id : ( function_exists( 'get_current_user_id' ) ? (int) get_current_user_id() : 0 );
		if ( $uid <= 0 ) {
			return false;
		}
		$clean = self::clean_window( $state );
		if ( $clean === self::window_defaults() ) {
			delete_user_meta( $uid, self::META_WINDOW );
		} else {
			update_user_meta( $uid, self::META_WINDOW, $clean );
		}
		return $clean;
	}

	public static function classic_on( $user_id = 0 ) {
		$uid = $user_id > 0 ? (int) $user_id : ( function_exists( 'get_current_user_id' ) ? (int) get_current_user_id() : 0 );
		return $uid > 0 && '1' === (string) get_user_meta( $uid, self::META_CLASSIC, true );
	}

	/** Remember the classic choice for a person. Choosing the one window removes the meta, so nothing is left behind. */
	public static function set_classic( $on, $user_id = 0 ) {
		$uid = $user_id > 0 ? (int) $user_id : ( function_exists( 'get_current_user_id' ) ? (int) get_current_user_id() : 0 );
		if ( $uid <= 0 ) {
			return false;
		}
		if ( $on ) {
			update_user_meta( $uid, self::META_CLASSIC, '1' );
		} else {
			delete_user_meta( $uid, self::META_CLASSIC );
		}
		return true;
	}

	/**
	 * The Simple editor (session 5, 29 Oct 2026): everyone who is not an administrator gets it, and an administrator
	 * who chose "View as: Client". It is a role check on the one editor, not a second editor: the scripts get fewer
	 * tools and the admin-only scripts are not loaded. It only hides tools: saving still runs with the person's real
	 * capabilities and goes through the same sanitiser, versions and conflict checks.
	 */
	public static function simple_on() {
		return ! current_user_can( 'manage_options' ) || 'client' === self::view_as();
	}

	/** 'client' or 'admin': an administrator's View as choice. A person who is not an administrator is always 'admin' here (the role decides for them). */
	public static function view_as( $user_id = 0 ) {
		$uid = $user_id > 0 ? (int) $user_id : ( function_exists( 'get_current_user_id' ) ? (int) get_current_user_id() : 0 );
		return $uid > 0 && 'client' === (string) get_user_meta( $uid, self::META_VIEW, true ) ? 'client' : 'admin';
	}

	/** Remember the View as choice for a person. Administrator view removes the meta, so nothing is left behind. */
	public static function set_view_as( $as, $user_id = 0 ) {
		$uid = $user_id > 0 ? (int) $user_id : ( function_exists( 'get_current_user_id' ) ? (int) get_current_user_id() : 0 );
		if ( $uid <= 0 ) {
			return false;
		}
		if ( 'client' === $as ) {
			update_user_meta( $uid, self::META_VIEW, 'client' );
		} else {
			delete_user_meta( $uid, self::META_VIEW );
		}
		return true;
	}

	/** The launchers are hidden by the tools switch, never in the Simple editor (a client must always find the pen). */
	public static function launchers_hidden() {
		return self::tools_off() && ! self::simple_on();
	}

	public static function advanced_looks_for_all() {
		return function_exists( 'get_option' ) && '1' === (string) get_option( self::OPTION_ADVANCED_LOOKS, '' );
	}

	public static function set_advanced_looks_for_all( $on ) {
		update_option( self::OPTION_ADVANCED_LOOKS, $on ? '1' : '0' );
		return (bool) $on;
	}

	public static function init() {
		add_action( 'wp_enqueue_scripts', array( __CLASS__, 'enqueue' ), 20 );
		add_action( 'wp_footer', array( __CLASS__, 'print_root' ) );
	}

	/** True inside Elementor's editor or preview, or the customizer. The editor stays out of those. */
	private static function in_builder_or_customizer() {
		if ( isset( $_GET['elementor-preview'] ) || isset( $_GET['elementor_library'] ) ) {
			return true;
		}
		return function_exists( 'is_customize_preview' ) && is_customize_preview();
	}

	/**
	 * What the editor needs to know about this request, or null when it must not load.
	 *
	 * It loads for any signed-in user who can edit pages, on any front-end page, because the
	 * New page tab works anywhere. The Edit tab needs a kit page they can edit, and the
	 * This page card needs a single page they can edit.
	 */
	public static function context() {
		if ( is_admin() || ! is_user_logged_in() || ! current_user_can( 'edit_pages' ) ) {
			return null;
		}
		if ( TWD_SK_Preview::is_preview_request() || self::in_builder_or_customizer() ) {
			return null;
		}
		$page_id  = TWD_SK_Page::viewed_page_id();
		$can_edit = $page_id > 0 && current_user_can( 'edit_post', $page_id );
		return array(
			'page_id'      => $can_edit ? $page_id : 0,
			'is_kit_page'  => $can_edit && TWD_SK_Page::is_kit_page( $page_id ),
			'can_publish'  => $can_edit && current_user_can( 'publish_pages' ) && current_user_can( 'publish_post', $page_id ),
		);
	}

	public static function enqueue() {
		$ver  = defined( 'TWD_SK_VERSION' ) ? TWD_SK_VERSION : false;
		$base = TWD_SK_URL;

		// A validated preview shows the stylesheet only, for its "Preview only" bar.
		if ( TWD_SK_Preview::is_active() ) {
			wp_enqueue_style( self::HANDLE, $base . 'assets/twd-site-kit-editor.css', array( TWD_SK_Assets::HANDLE ), $ver );
			return;
		}

		$context = self::context();
		if ( null === $context ) {
			return;
		}

		// The Simple editor (session 5): a person who is not an administrator, or an administrator viewing as the client.
		// They get words, pictures, Bold, Link, Save, Undo, Redo and History: no AI, no Look, no sections, no Site tab.
		$simple     = self::simple_on();
		$can_manage = current_user_can( 'manage_options' );

		wp_enqueue_style( self::HANDLE, $base . 'assets/twd-site-kit-editor.css', array( TWD_SK_Assets::HANDLE ), $ver );
		wp_register_script( self::HANDLE, $base . 'assets/twd-site-kit-editor.js', array(), $ver, true );
		wp_localize_script( self::HANDLE, 'TWD_SK_EDITOR', array(
			'restUrl'        => esc_url_raw( rest_url( TWD_SK_REST::ROUTE_NS ) ),
			'nonce'          => wp_create_nonce( 'wp_rest' ),
			'pageId'         => (int) $context['page_id'],
			'isKitPage'      => (bool) $context['is_kit_page'],
			'canPublish'     => (bool) $context['can_publish'],
			'currentVersion' => $context['page_id'] ? TWD_SK_Store::get_current_version_id( $context['page_id'] ) : 0,
			'maxBytes'       => TWD_SK_Store::MAX_BYTES,
			'maxTitle'       => TWD_SK_Template::MAX_TITLE,
			'starters'       => TWD_SK_Template::starters(),
			'safeMode'       => TWD_SK_Safe::on(),
			'ai'             => ! $simple && (bool) $context['is_kit_page'] && TWD_SK_AI::available(),
			'aiAvailable'    => ! $simple && TWD_SK_AI::available(),
			'recipes'        => TWD_SK_Recipes::labels(),
			'canManage'      => $can_manage && ! $simple,
			'factsSaved'     => '' !== TWD_SK_Facts::get(),
			'words'          => TWD_SK_Mode::js_words(),
			'components'     => ( (bool) $context['is_kit_page'] && ! $simple ) ? TWD_SK_Recipes::parts_menu( TWD_SK_Embed::can_manage() && ! TWD_SK_Safe::on() ) : array(),
			// The saved embeds an administrator can add to a page from Add a section (0.15.0): names only, never the code.
			'embeds'         => ( (bool) $context['is_kit_page'] && ! $simple && TWD_SK_Embed::can_manage() && ! TWD_SK_Safe::on() ) ? TWD_SK_Embed::summaries() : array(),
			// Changing words and pictures in place (0.10.0): kit pages, outside safe mode.
			'inline'         => (bool) $context['is_kit_page'] && ! TWD_SK_Safe::on(),
			// The layouts and options the Visual editor's Look panel offers (0.15.0): labels and class names only.
			'look'           => ( (bool) $context['is_kit_page'] && ! TWD_SK_Safe::on() && ! $simple ) ? TWD_SK_Looks::config() : array(),
			// Uploading a picture from the computer while changing the page in place (0.10.1).
			'canUpload'      => current_user_can( 'upload_files' ),
			'mediaUrl'       => esc_url_raw( rest_url( 'wp/v2/media' ) ),
			'setupUrl'       => ( $can_manage && ! $simple ) ? esc_url_raw( TWD_SK_Build::admin_url() ) : '',
			// The editing tools switch (0.14.1): hides the launchers for this person until switched on again.
			'toolsOff'       => self::launchers_hidden(),
			// Where the Edit with AI window sits for this person (0.15.0): docked, free or centred, and the minimised pencil.
			'window'         => self::window_state(),
			// The one editing window (0.15.0): off means the classic pop-up and the separate Visual editor card, by this person's choice.
			'classic'        => ! $simple && self::classic_on(),
			// The Simple editor (session 5): on for everyone who is not an administrator, and for an administrator viewing
			// as the client. viewAs and canSwitchView are for administrators only, so a real client never sees the switch.
			'simple'         => $simple,
			'viewAs'         => $can_manage ? self::view_as() : '',
			'canSwitchView'  => $can_manage,
			// Whether the page is live, so Add a section can warn that its placeholder words would show (0.14.1).
			'pagePublished'  => $context['page_id'] > 0 && 'publish' === get_post_status( $context['page_id'] ),
		) );
		wp_enqueue_script( self::HANDLE );

		// Changing words and pictures in place: anyone who can edit this kit page, and not in safe mode.
		if ( ! TWD_SK_Safe::on() && $context['is_kit_page'] ) {
			wp_register_script( self::HANDLE_INLINE, $base . 'assets/twd-site-kit-inline.js', array( self::HANDLE ), $ver, true );
			wp_enqueue_script( self::HANDLE_INLINE );
			wp_enqueue_style( self::HANDLE_INLINE, $base . 'assets/twd-site-kit-inline.css', array(), $ver );
			// The media library picker, for choosing a picture (the Simple editor needs it too).
			if ( function_exists( 'wp_enqueue_media' ) && current_user_can( 'upload_files' ) ) {
				wp_enqueue_media();
			}
		}

		// The Search tab: anyone who can edit this kit page, and not in safe mode. Not in the Simple editor.
		if ( ! TWD_SK_Safe::on() && $context['is_kit_page'] && ! $simple ) {
			wp_register_script( self::HANDLE_SEO, $base . 'assets/twd-site-kit-editor-seo.js', array( self::HANDLE ), $ver, true );
			wp_enqueue_script( self::HANDLE_SEO );
		}

		// The Site tab: administrators only, not in safe mode and not while viewing as the client. Its own script, so nobody else loads it.
		if ( ! TWD_SK_Safe::on() && $can_manage && ! $simple ) {
			wp_register_script( self::HANDLE_SITE, $base . 'assets/twd-site-kit-editor-site.js', array( self::HANDLE ), $ver, true );
			wp_localize_script( self::HANDLE_SITE, 'TWD_SK_SITE', array( 'enabled' => true ) );
			wp_enqueue_script( self::HANDLE_SITE );
			// The media library picker, for choosing a logo.
			if ( function_exists( 'wp_enqueue_media' ) ) {
				wp_enqueue_media();
			}
			// Every bundled font, so a style can be previewed live.
			wp_add_inline_style( self::HANDLE, TWD_SK_Packs::all_fonts_css( $base . 'assets/fonts/' ) );
		}
	}

	/**
	 * The button, and a small version label in the bottom left corner for signed-in editors, so a phone showing a
	 * cached build is easy to spot. The button stays hidden until the script runs; the pop-up is built by the script.
	 */
	public static function print_root() {
		if ( null === self::context() ) {
			return;
		}
		echo '<div id="twd-sk-ed" class="twd-sk-ed"' . ( self::launchers_hidden() ? ' data-twd-sk-tools="off"' : '' ) . '><button type="button" id="twd-sk-ed-open" class="twd-sk-ed__open" hidden aria-haspopup="dialog">'
			. esc_html__( 'Edit with AI', 'twd-site-kit' )
			. '</button><span class="twd-sk-ed__version" title="TWD Site Kit version">v' . esc_html( defined( 'TWD_SK_VERSION' ) ? TWD_SK_VERSION : '' ) . '</span></div>';
	}
}
