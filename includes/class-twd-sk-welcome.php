<?php
/**
 * TWD_SK_Welcome: the Welcome screen for clients and editors (0.22.0, stage 2).
 *
 * A page in wp-admin (Dashboard > Welcome) with big plain-word buttons, "How do I...?" cards that open a
 * small pop-up of numbered steps, and a row of useful links. It reads and changes nothing on any page.
 * Started from TWD_SK_Modules (so off in safe mode). Its script and stylesheet load on this one screen only.
 *
 * The how-tos are data (howtos()), one entry each, so adding one is a new entry, never new code.
 * The pop-ups are printed by PHP, hidden, and the script only shows and hides them (no HTML is built in the browser).
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class TWD_SK_Welcome {

	const SLUG   = 'twd-sk-welcome';
	const HANDLE = 'twd-site-kit-welcome';

	const ACTION   = 'twd_sk_welcome_save';
	const OPT_GO   = 'twd_sk_welcome_redirect';
	const OPT_NOTE = 'twd_sk_welcome_note';
	const OPT_HELP = 'twd_sk_welcome_contact';

	public static function init() {
		// The sign-in page is not an admin screen, so this one hook is added everywhere (it does nothing while the switch is off).
		add_filter( 'login_redirect', array( __CLASS__, 'login_redirect' ), 10, 3 );
		if ( ! is_admin() ) {
			return;
		}
		add_action( 'admin_post_' . self::ACTION, array( __CLASS__, 'save' ) );
		add_action( 'admin_menu', array( __CLASS__, 'admin_menu' ) );
		add_action( 'admin_enqueue_scripts', array( __CLASS__, 'admin_assets' ) );
	}

	public static function admin_url() {
		return admin_url( 'index.php?page=' . self::SLUG );
	}

	public static function admin_menu() {
		if ( TWD_SK_Safe::on() ) {
			return;
		}
		add_submenu_page( 'index.php', 'Welcome', 'Welcome', 'edit_pages', self::SLUG, array( __CLASS__, 'render' ) );
	}

	/** The how-to cards: id => title, one line, numbered steps. Plain words, no names, nothing client-specific. */
	public static function howtos() {
		$list = array(
			'words'   => array(
				'title'   => 'Change the words on a page',
				'summary' => 'Click the words, type, save.',
				'steps'   => array(
					'Sign in, then open the page on your website.',
					'Click Visual editor (the button at the bottom left of the page).',
					'Click the words you want to change and type over them.',
					'Click Save. Your change goes live straight away.',
				),
			),
			'picture' => array(
				'title'   => 'Change a picture',
				'summary' => 'Choose a new one from your library or upload it.',
				'steps'   => array(
					'Open the page and click Visual editor.',
					'Click the picture.',
					'Choose Choose from the library, or Upload from this computer.',
					'Check the short description of the picture, then Save.',
				),
			),
			'section' => array(
				'title'   => 'Add, move or remove a block',
				'summary' => 'Pages are made of blocks you can add and reorder.',
				'steps'   => array(
					'Open the page and click Visual editor, then click inside the block you want to work on.',
					'Use Add section to put a new block after it, or Move up and Move down to reorder.',
					'Replace the sample words in a new block with your own, then Save.',
				),
			),
			'undo'    => array(
				'title'   => 'Undo a mistake',
				'summary' => 'Every save is kept, so you can go back.',
				'steps'   => array(
					'Open the page and click Visual editor.',
					'Choose Undo to take back your last save, or History to see earlier versions.',
					'Pick the version you want and choose Go back to this.',
				),
			),
			'tell'    => array(
				'title'   => 'Something is wrong, or you would like something changed',
				'summary' => 'Send a note to the person who looks after your site.',
				'steps'   => array(
					'On the page concerned, click Tell us (above the Visual editor button).',
					'Write what you saw or what you would like, in your own words.',
					'Click Send. The page is added for you.',
				),
			),
		);
		$extra = apply_filters( 'twd_sk_welcome_howtos', array() );
		if ( is_array( $extra ) ) {
			foreach ( $extra as $id => $h ) {
				$id = is_string( $id ) ? preg_replace( '/[^a-z0-9-]/', '', strtolower( $id ) ) : '';
				if ( '' === $id || isset( $list[ $id ] ) || ! is_array( $h ) || empty( $h['title'] ) || empty( $h['steps'] ) || ! is_array( $h['steps'] ) ) {
					continue;
				}
				$list[ $id ] = array(
					'title'   => (string) $h['title'],
					'summary' => isset( $h['summary'] ) ? (string) $h['summary'] : '',
					'steps'   => array_values( array_map( 'strval', $h['steps'] ) ),
				);
			}
		}
		return $list;
	}

	/** The big buttons: label, one line, address. Administrators get one more. */
	public static function actions() {
		$list = array(
			array( 'label' => 'Edit my website', 'text' => 'Open your site with the editing tools ready.', 'url' => home_url( '/?twd_sk_edit=1&twd_sk_at=0' ), 'primary' => true ),
			array( 'label' => 'See my pages', 'text' => 'A list of every page on your site.', 'url' => admin_url( 'edit.php?post_type=page' ), 'primary' => false ),
			array( 'label' => 'Visit my website', 'text' => 'See it the way visitors do.', 'url' => home_url( '/' ), 'primary' => false ),
		);
		if ( current_user_can( 'manage_options' ) ) {
			$list[] = array( 'label' => 'Set up and tools', 'text' => 'Site details, style and the launch check.', 'url' => admin_url( 'options-general.php?page=' . TWD_SK_Build::PAGE_SLUG ), 'primary' => false );
		}
		return $list;
	}

	/** After sign-in, people who edit pages (never administrators) land here, unless they asked for another place. Off by default. */
	public static function login_redirect( $to, $requested = '', $user = null ) {
		if ( TWD_SK_Safe::on() || 'yes' !== get_option( self::OPT_GO, 'no' ) ) {
			return $to;
		}
		if ( ! is_object( $user ) || ! isset( $user->ID ) || ! user_can( $user, 'edit_pages' ) || user_can( $user, 'manage_options' ) ) {
			return $to;
		}
		// WordPress passes the dashboard as the default; any other requested address is the person's own choice.
		if ( '' !== (string) $requested && admin_url() !== (string) $requested ) {
			return $to;
		}
		return self::admin_url();
	}

	public static function note() {
		return (string) get_option( self::OPT_NOTE, '' );
	}

	public static function contact() {
		return (string) get_option( self::OPT_HELP, '' );
	}

	/** Plain text only, no tags, no long dashes, cut to a length. */
	public static function clean( $text, $max ) {
		$text = sanitize_text_field( (string) $text );
		$text = preg_replace( '/\s*[\x{2013}\x{2014}]\s*/u', ', ', $text );
		return function_exists( 'mb_substr' ) ? mb_substr( $text, 0, $max ) : substr( $text, 0, $max );
	}

	/** Administrators only: the two switches and two lines of words. */
	public static function save() {
		if ( ! check_admin_referer( self::ACTION ) || ! current_user_can( 'manage_options' ) || TWD_SK_Safe::on() ) {
			wp_die( esc_html__( 'You cannot change these settings.', 'twd-site-kit' ) );
		}
		$in = wp_unslash( $_POST ); // phpcs:ignore WordPress.Security.NonceVerification.Missing
		TWD_SK_Login::set( ! empty( $in['login'] ) );
		update_option( self::OPT_GO, ! empty( $in['go'] ) ? 'yes' : 'no' );
		update_option( self::OPT_NOTE, self::clean( isset( $in['note'] ) ? $in['note'] : '', 500 ) );
		update_option( self::OPT_HELP, self::clean( isset( $in['contact'] ) ? $in['contact'] : '', 200 ) );
		wp_safe_redirect( add_query_arg( array( 'twd_sk_saved' => '1' ), self::admin_url() ) );
		exit;
	}

	private static function render_settings() {
		echo '<h2>' . esc_html( 'For the person who looks after this site' ) . '</h2>';
		echo '<form method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '" class="twd-sk-welcome__settings">';
		echo '<input type="hidden" name="action" value="' . esc_attr( self::ACTION ) . '">';
		wp_nonce_field( self::ACTION );
		echo '<p><label><input type="checkbox" name="login" value="1"' . ( TWD_SK_Login::on() ? ' checked' : '' ) . '> ' . esc_html( 'Use the site\'s own look on the sign-in page' ) . '</label></p>';
		echo '<p><label><input type="checkbox" name="go" value="1"' . ( 'yes' === get_option( self::OPT_GO, 'no' ) ? ' checked' : '' ) . '> ' . esc_html( 'Send editors to this Welcome screen after they sign in (administrators go to the Dashboard as usual)' ) . '</label></p>';
		echo '<p><label for="twd-sk-welcome-note">' . esc_html( 'A short welcome note for your client (shown at the top, 500 characters at most)' ) . '</label><br><textarea id="twd-sk-welcome-note" name="note" rows="3" maxlength="500" class="large-text">' . esc_textarea( self::note() ) . '</textarea></p>';
		echo '<p><label for="twd-sk-welcome-contact">' . esc_html( 'Who to contact for help (one line, for example how to reach you)' ) . '</label><br><input type="text" id="twd-sk-welcome-contact" name="contact" maxlength="200" class="large-text" value="' . esc_attr( self::contact() ) . '"></p>';
		echo '<p><button type="submit" class="button button-primary">' . esc_html( 'Save' ) . '</button></p></form>';
	}

	public static function render() {
		if ( ! current_user_can( 'edit_pages' ) ) {
			return;
		}
		$name = TWD_SK_Login::site_name();
		echo '<div class="wrap twd-sk-welcome">';
		echo '<header class="twd-sk-welcome__head">';
		$logo = TWD_SK_Login::logo_url();
		if ( '' !== $logo ) {
			echo '<img class="twd-sk-welcome__logo" src="' . esc_url( $logo ) . '" alt="' . esc_attr( $name ) . '">';
		}
		echo '<h1>' . esc_html( 'Welcome' . ( '' !== $name ? ', ' . $name : '' ) ) . '</h1>';
		echo '<p class="twd-sk-welcome__lead">' . esc_html( 'Everything you need to look after your website is here. Pick a button to start, or open a short guide.' ) . '</p></header>';

		if ( '' !== self::note() ) {
			echo '<p class="twd-sk-welcome__note">' . esc_html( self::note() ) . '</p>';
		}
		if ( ! empty( $_GET['twd_sk_saved'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended
			echo '<div class="notice notice-success"><p>' . esc_html( 'Saved.' ) . '</p></div>';
		}

		echo '<h2>' . esc_html( 'Start here' ) . '</h2><ul class="twd-sk-welcome__actions">';
		foreach ( self::actions() as $a ) {
			echo '<li><a class="twd-sk-welcome__action' . ( $a['primary'] ? ' is-primary' : '' ) . '" href="' . esc_url( $a['url'] ) . '"><strong>' . esc_html( $a['label'] ) . '</strong><span>' . esc_html( $a['text'] ) . '</span></a></li>';
		}
		echo '</ul>';

		echo '<h2>' . esc_html( 'How do I...?' ) . '</h2><ul class="twd-sk-welcome__cards">';
		$dialogs = '';
		foreach ( self::howtos() as $id => $h ) {
			$did = 'twd-sk-howto-' . $id;
			echo '<li><button type="button" class="twd-sk-welcome__card" data-twd-sk-open="' . esc_attr( $did ) . '" aria-haspopup="dialog"><strong>' . esc_html( $h['title'] ) . '</strong>';
			if ( '' !== $h['summary'] ) {
				echo '<span>' . esc_html( $h['summary'] ) . '</span>';
			}
			echo '</button></li>';
			$dialogs .= '<div class="twd-sk-welcome__dialog" id="' . esc_attr( $did ) . '" role="dialog" aria-modal="true" aria-labelledby="' . esc_attr( $did ) . '-t" hidden>'
				. '<div class="twd-sk-welcome__panel"><h2 id="' . esc_attr( $did ) . '-t">' . esc_html( $h['title'] ) . '</h2><ol>';
			foreach ( $h['steps'] as $s ) {
				$dialogs .= '<li>' . esc_html( $s ) . '</li>';
			}
			$dialogs .= '</ol><p><button type="button" class="button button-primary" data-twd-sk-close>' . esc_html( 'Got it' ) . '</button></p></div></div>';
		}
		echo '</ul>' . $dialogs; // phpcs:ignore -- every part above is escaped as it is built

		$help = TWD_SK_Help::items();
		if ( $help ) {
			echo '<h2>' . esc_html( 'Guides and videos' ) . '</h2><ul class="twd-sk-welcome__cards">';
			foreach ( $help as $h ) {
				echo '<li><a class="twd-sk-welcome__card twd-sk-welcome__guide" href="' . esc_url( $h['url'] ) . '" target="_blank" rel="noopener noreferrer"><em class="twd-sk-welcome__kind">' . esc_html( 'video' === $h['kind'] ? 'Video' : 'Article' ) . '</em><strong>' . esc_html( $h['title'] ) . '</strong>';
				if ( '' !== $h['summary'] ) {
					echo '<span>' . esc_html( $h['summary'] ) . '</span>';
				}
				echo '</a></li>';
			}
			echo '</ul>';
		}

		if ( '' !== self::contact() ) {
			echo '<h2>' . esc_html( 'Need a hand?' ) . '</h2><p class="twd-sk-welcome__help">' . esc_html( self::contact() ) . '</p>';
		}
		if ( current_user_can( 'manage_options' ) && ! TWD_SK_Safe::on() ) {
			self::render_settings();
		}

		echo '</div>';
	}

	public static function admin_assets( $hook ) {
		if ( 'dashboard_page_' . self::SLUG !== $hook || TWD_SK_Safe::on() || ! current_user_can( 'edit_pages' ) ) {
			return;
		}
		$ver = defined( 'TWD_SK_VERSION' ) ? TWD_SK_VERSION : false;
		wp_enqueue_style( self::HANDLE, TWD_SK_URL . 'assets/twd-site-kit-welcome.css', array(), $ver );
		// The site's own look: the active style's tokens and the two fonts it uses (nothing is loaded from outside).
		$tokens = TWD_SK_Packs::active_tokens();
		wp_add_inline_style( self::HANDLE, TWD_SK_Packs::font_face_css( $tokens, TWD_SK_URL . 'assets/fonts/' ) . TWD_SK_Packs::tokens_css( $tokens ) );
		wp_enqueue_script( self::HANDLE, TWD_SK_URL . 'assets/twd-site-kit-welcome.js', array(), $ver, true );
	}
}
