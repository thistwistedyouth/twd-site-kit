<?php
/**
 * TWD_SK_Updater: self-hosted update checker for a plugin that is not on WordPress.org.
 *
 * Same shape as the articles plugin's updater: it reads a small JSON file from
 * this plugin's own PUBLIC GitHub repo (no credentials), caches it for 12 hours,
 * and wires a newer version into the normal Plugins screen (update row, changelog,
 * one-click update, "Check for updates" link).
 *
 * Stricter than the articles plugin in four ways:
 *  - It only ever fetches two fixed https addresses (the JSON and the zip), both
 *    constants below. The package address is never read from the JSON. Redirects
 *    are not followed.
 *  - The JSON must carry a sha256 of the zip. The zip is downloaded here, hashed,
 *    and refused unless the hash matches. Any doubt fails closed: nothing is
 *    installed and a plain message says so.
 *  - An update with no usable checksum is never offered at all.
 *  - The unpacked package must have the single folder twd-site-kit.
 *
 * Admin only: nothing is hooked unless is_admin() is true.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class TWD_SK_Updater {

	const RAW_BASE      = 'https://raw.githubusercontent.com/thistwistedyouth/twd-site-kit/main/dist/';
	const JSON_URL      = self::RAW_BASE . 'twd-site-kit-update.json';
	const ZIP_URL       = self::RAW_BASE . 'twd-site-kit-latest.zip';
	const SOURCE_URL    = 'https://github.com/thistwistedyouth/twd-site-kit';
	const SLUG          = 'twd-site-kit';
	const CACHE_KEY     = 'twd_sk_update_check';
	const CACHE_SECONDS = 43200; // 12 hours.
	const MAX_ZIP_BYTES = 20 * 1024 * 1024; // 20 MB. The real zip is about 0.3 MB.
	const ACTION        = 'twd_sk_check_updates';

	public static function init() {
		if ( ! is_admin() ) {
			return;
		}
		add_filter( 'pre_set_site_transient_update_plugins', array( __CLASS__, 'check_for_update' ) );
		add_filter( 'plugins_api', array( __CLASS__, 'plugin_info' ), 20, 3 );
		add_filter( 'plugin_row_meta', array( __CLASS__, 'row_meta' ), 10, 2 );
		add_filter( 'plugin_action_links_' . self::basename(), array( __CLASS__, 'action_links' ) );
		add_filter( 'upgrader_pre_download', array( __CLASS__, 'verify_download' ), 10, 3 );
		add_filter( 'upgrader_source_selection', array( __CLASS__, 'check_source' ), 10, 4 );
		add_action( 'delete_site_transient_update_plugins', array( __CLASS__, 'clear_cache' ) );
		add_action( 'upgrader_process_complete', array( __CLASS__, 'clear_cache' ) );
		add_action( 'admin_post_' . self::ACTION, array( __CLASS__, 'handle_manual_check' ) );
		add_action( 'admin_notices', array( __CLASS__, 'render_plugins_screen_notice' ) );
	}

	public static function basename() {
		return plugin_basename( TWD_SK_PATH . 'twd-site-kit.php' );
	}

	// -- The update file -----------------------------------------------------

	/**
	 * Read and validate the update JSON. Returns array( manifest, error ): the
	 * cleaned manifest and an empty error, or null and a plain reason.
	 *
	 * The package address in the manifest is always the fixed constant. A JSON
	 * file that names any other address is rejected, not followed.
	 */
	public static function parse_manifest( $body ) {
		$data = json_decode( (string) $body, true );
		if ( ! is_array( $data ) || array_values( $data ) === $data ) {
			return array( null, 'The update file is not valid JSON.' );
		}

		$version = ( isset( $data['version'] ) && is_string( $data['version'] ) ) ? trim( $data['version'] ) : '';
		if ( ! preg_match( '/^\d{1,3}\.\d{1,3}\.\d{1,3}$/', $version ) ) {
			return array( null, 'The update file did not contain a valid version.' );
		}

		$sha = ( isset( $data['sha256'] ) && is_string( $data['sha256'] ) ) ? strtolower( trim( $data['sha256'] ) ) : '';
		if ( ! preg_match( '/^[a-f0-9]{64}$/', $sha ) ) {
			return array( null, 'The update file has no valid checksum, so the update was not offered.' );
		}

		if ( isset( $data['download_url'] ) && self::ZIP_URL !== $data['download_url'] ) {
			return array( null, 'The update file points at an unexpected address, so it was ignored.' );
		}

		$homepage = ( isset( $data['homepage'] ) && is_string( $data['homepage'] ) && 0 === strpos( $data['homepage'], 'https://' ) ) ? $data['homepage'] : 'https://therapywebdesigns.co.uk/';

		$manifest = array(
			'version'      => $version,
			'sha256'       => $sha,
			'download_url' => self::ZIP_URL,
			'homepage'     => $homepage,
			'requires'     => self::clean_version( $data, 'requires' ),
			'tested'       => self::clean_version( $data, 'tested' ),
			'requires_php' => self::clean_version( $data, 'requires_php' ),
			'last_updated' => ( isset( $data['last_updated'] ) && is_string( $data['last_updated'] ) && preg_match( '/^\d{4}-\d{2}-\d{2}$/', $data['last_updated'] ) ) ? $data['last_updated'] : '',
			'description'  => isset( $data['description'] ) && is_string( $data['description'] ) ? trim( strip_tags( $data['description'] ) ) : '',
			'changelog'    => isset( $data['changelog'] ) && is_string( $data['changelog'] ) ? self::clean_html( $data['changelog'] ) : '',
		);
		return array( $manifest, '' );
	}

	private static function clean_version( $data, $key ) {
		if ( isset( $data[ $key ] ) && is_string( $data[ $key ] ) && preg_match( '/^\d{1,3}(\.\d{1,3}){0,2}$/', $data[ $key ] ) ) {
			return $data[ $key ];
		}
		return '';
	}

	/** The changelog is shown in the "View details" window, so only simple tags are kept. */
	private static function clean_html( $html ) {
		$allowed = array(
			'h4'     => array(),
			'ul'     => array(),
			'li'     => array(),
			'p'      => array(),
			'strong' => array(),
			'em'     => array(),
			'code'   => array(),
			'br'     => array(),
		);
		if ( function_exists( 'wp_kses' ) ) {
			return wp_kses( $html, $allowed );
		}
		return strip_tags( $html, '<h4><ul><li><p><strong><em><code><br>' );
	}

	private static function set_last_error( $message ) {
		set_transient( self::CACHE_KEY . '_error', $message, DAY_IN_SECONDS );
	}

	public static function last_error() {
		$error = get_transient( self::CACHE_KEY . '_error' );
		return is_string( $error ) ? $error : '';
	}

	public static function clear_cache() {
		delete_transient( self::CACHE_KEY );
		delete_transient( self::CACHE_KEY . '_error' );
	}

	/**
	 * The cleaned update manifest, from the 12-hour cache or fresh from GitHub.
	 *
	 * @param bool $fresh Skip the cache (used right before installing).
	 * @return array|false
	 */
	public static function fetch_manifest( $fresh = false ) {
		if ( ! $fresh ) {
			$cached = get_transient( self::CACHE_KEY );
			if ( is_array( $cached ) && isset( $cached['version'], $cached['sha256'] ) ) {
				return $cached;
			}
		}

		// The one fixed address, and no redirects.
		$response = wp_safe_remote_get( self::JSON_URL, array( 'timeout' => 10, 'redirection' => 0 ) );
		if ( is_wp_error( $response ) ) {
			self::set_last_error( $response->get_error_message() );
			return false;
		}
		$code = (int) wp_remote_retrieve_response_code( $response );
		if ( 200 !== $code ) {
			self::set_last_error( 'GitHub returned HTTP ' . $code . '.' );
			return false;
		}

		list( $manifest, $error ) = self::parse_manifest( wp_remote_retrieve_body( $response ) );
		if ( null === $manifest ) {
			self::set_last_error( $error );
			return false;
		}

		delete_transient( self::CACHE_KEY . '_error' );
		set_transient( self::CACHE_KEY, $manifest, self::CACHE_SECONDS );
		return $manifest;
	}

	// -- Plugins screen: the update row and the details window ---------------

	public static function check_for_update( $transient ) {
		if ( ! is_object( $transient ) || empty( $transient->checked ) ) {
			return $transient;
		}
		$remote = self::fetch_manifest();
		if ( ! $remote ) {
			return $transient;
		}

		$basename = self::basename();
		if ( ! isset( $transient->response ) || ! is_array( $transient->response ) ) {
			$transient->response = array();
		}

		if ( version_compare( $remote['version'], TWD_SK_VERSION, '>' ) ) {
			$item               = new stdClass();
			$item->id           = self::SLUG;
			$item->slug         = self::SLUG;
			$item->plugin       = $basename;
			$item->new_version  = $remote['version'];
			$item->url          = $remote['homepage'];
			$item->package      = self::ZIP_URL;
			$item->tested       = $remote['tested'];
			$item->requires     = $remote['requires'];
			$item->requires_php = $remote['requires_php'];

			$transient->response[ $basename ] = $item;
			if ( isset( $transient->no_update ) && is_array( $transient->no_update ) ) {
				unset( $transient->no_update[ $basename ] );
			}
		} else {
			unset( $transient->response[ $basename ] );
		}
		return $transient;
	}

	public static function plugin_info( $result, $action, $args ) {
		if ( 'plugin_information' !== $action || empty( $args->slug ) || self::SLUG !== $args->slug ) {
			return $result;
		}
		$remote = self::fetch_manifest();
		if ( ! $remote ) {
			return $result;
		}

		$info                = new stdClass();
		$info->name          = 'TWD Site Kit';
		$info->slug          = self::SLUG;
		$info->version       = $remote['version'];
		$info->author        = '<a href="https://therapywebdesigns.co.uk/">Therapy Web Designs</a>';
		$info->homepage      = $remote['homepage'];
		$info->requires      = $remote['requires'];
		$info->tested        = $remote['tested'];
		$info->requires_php  = $remote['requires_php'];
		$info->download_link = self::ZIP_URL;
		$info->last_updated  = $remote['last_updated'];
		$info->sections      = array(
			'description' => $remote['description'],
			'changelog'   => $remote['changelog'],
		);
		return $info;
	}

	public static function row_meta( $links, $file ) {
		if ( $file === self::basename() ) {
			$links[] = '<a href="' . esc_url( self::SOURCE_URL ) . '" target="_blank" rel="noopener">' . esc_html__( 'View source', 'twd-site-kit' ) . '</a>';
		}
		return $links;
	}

	public static function action_links( $links ) {
		$url     = wp_nonce_url( admin_url( 'admin-post.php?action=' . self::ACTION ), self::ACTION );
		$links[] = '<a href="' . esc_url( $url ) . '">' . esc_html__( 'Check for updates', 'twd-site-kit' ) . '</a>';
		return $links;
	}

	// -- "Check for updates" -------------------------------------------------

	/**
	 * Clears our cache and core's update list, forces an immediate recheck (the
	 * same core function cron uses), then sends the admin back to the Plugins
	 * screen with the result in the address.
	 */
	public static function handle_manual_check() {
		if ( ! current_user_can( 'update_plugins' ) || ! check_admin_referer( self::ACTION ) ) {
			wp_die( esc_html__( 'You are not allowed to do that.', 'twd-site-kit' ) );
		}

		self::clear_cache();
		delete_site_transient( 'update_plugins' );
		wp_update_plugins();

		$remote = self::fetch_manifest();
		$latest = $remote ? $remote['version'] : '';

		wp_safe_redirect(
			add_query_arg(
				array(
					'twd_sk_checked' => 1,
					'twd_sk_latest'  => rawurlencode( $latest ),
				),
				admin_url( 'plugins.php' )
			)
		);
		exit;
	}

	public static function render_plugins_screen_notice() {
		$screen = function_exists( 'get_current_screen' ) ? get_current_screen() : null;
		if ( ! $screen || 'plugins' !== $screen->id ) {
			return;
		}
		self::render_checked_notice();
	}

	public static function render_checked_notice() {
		if ( empty( $_GET['twd_sk_checked'] ) ) {
			return;
		}
		$latest = isset( $_GET['twd_sk_latest'] ) ? sanitize_text_field( wp_unslash( $_GET['twd_sk_latest'] ) ) : '';
		if ( '' !== $latest && ! preg_match( '/^\d{1,3}\.\d{1,3}\.\d{1,3}$/', $latest ) ) {
			$latest = '';
		}

		if ( '' === $latest ) {
			$detail = self::last_error();
			echo '<div class="notice notice-warning is-dismissible"><p>' . esc_html__( 'Could not reach the update server just now.', 'twd-site-kit' ) . ( '' !== $detail ? ' <code>' . esc_html( $detail ) . '</code>' : '' ) . '</p></div>';
			return;
		}

		if ( version_compare( $latest, TWD_SK_VERSION, '>' ) ) {
			printf(
				'<div class="notice notice-info is-dismissible"><p>%s</p></div>',
				sprintf(
					/* translators: 1: installed version, 2: newer version available */
					esc_html__( 'A newer version of TWD Site Kit is available: %2$s (you have %1$s). See the update below.', 'twd-site-kit' ),
					esc_html( TWD_SK_VERSION ),
					esc_html( $latest )
				)
			);
		} else {
			echo '<div class="notice notice-success is-dismissible"><p>' . esc_html__( "TWD Site Kit is on the latest version.", 'twd-site-kit' ) . '</p></div>';
		}
	}

	// -- Installing: verify before anything is unpacked ----------------------

	/**
	 * WordPress filter upgrader_pre_download. For our own package only, download
	 * the zip here, check its sha256 against a freshly fetched update file, and
	 * hand WordPress the verified local file. Anything doubtful returns a plain
	 * error and installs nothing. Other plugins' packages are left alone.
	 *
	 * @return the unchanged $reply for any other package, a path to the verified
	 *         zip, or a WP_Error.
	 */
	public static function verify_download( $reply, $package, $upgrader = null ) {
		if ( self::ZIP_URL !== $package ) {
			return $reply;
		}

		$manifest = self::fetch_manifest( true );
		if ( ! $manifest ) {
			return new WP_Error( 'twd_sk_update_unverified', __( 'The update was not installed because its checksum could not be fetched. Nothing was changed. Try Check for updates again in a few minutes.', 'twd-site-kit' ) );
		}
		if ( ! version_compare( $manifest['version'], TWD_SK_VERSION, '>' ) ) {
			return new WP_Error( 'twd_sk_update_not_newer', __( 'The update was not installed because no newer version is available. Nothing was changed.', 'twd-site-kit' ) );
		}

		$tmp = wp_tempnam( 'twd-site-kit.zip' );
		if ( ! $tmp ) {
			return new WP_Error( 'twd_sk_update_tmp', __( 'The update was not installed because a temporary file could not be created. Nothing was changed.', 'twd-site-kit' ) );
		}

		$response = wp_safe_remote_get(
			self::ZIP_URL,
			array(
				'timeout'     => 300,
				'stream'      => true,
				'filename'    => $tmp,
				'redirection' => 0,
			)
		);
		if ( is_wp_error( $response ) || 200 !== (int) wp_remote_retrieve_response_code( $response ) ) {
			self::delete_file( $tmp );
			return new WP_Error( 'twd_sk_update_download', __( 'The update was not installed because the file could not be downloaded. Nothing was changed. Try again in a few minutes.', 'twd-site-kit' ) );
		}

		$size = is_file( $tmp ) ? (int) filesize( $tmp ) : 0;
		if ( $size < 1 || $size > self::MAX_ZIP_BYTES ) {
			self::delete_file( $tmp );
			return new WP_Error( 'twd_sk_update_size', __( 'The update was not installed because the downloaded file was not the expected size. Nothing was changed.', 'twd-site-kit' ) );
		}

		$actual = hash_file( 'sha256', $tmp );
		if ( ! is_string( $actual ) || ! hash_equals( $manifest['sha256'], $actual ) ) {
			self::delete_file( $tmp );
			return new WP_Error( 'twd_sk_update_checksum', __( 'The update was not installed because the downloaded file did not match its checksum. Nothing was changed. Try Check for updates, and if it happens again, tell Therapy Web Designs.', 'twd-site-kit' ) );
		}

		return $tmp;
	}

	/**
	 * WordPress filter upgrader_source_selection. After unpacking, make sure the
	 * package really has the single folder twd-site-kit with the plugin file in
	 * it, so an update can never land in a differently named folder.
	 */
	public static function check_source( $source, $remote_source = '', $upgrader = null, $hook_extra = array() ) {
		if ( ! is_array( $hook_extra ) || ! isset( $hook_extra['plugin'] ) || self::basename() !== $hook_extra['plugin'] ) {
			return $source;
		}
		$folder = basename( rtrim( (string) $source, '/\\' ) );
		if ( self::SLUG !== $folder || ! is_file( rtrim( (string) $source, '/\\' ) . '/twd-site-kit.php' ) ) {
			return new WP_Error( 'twd_sk_update_layout', __( 'The update was not installed because the package did not have the expected folder layout. Nothing was changed.', 'twd-site-kit' ) );
		}
		return $source;
	}

	private static function delete_file( $path ) {
		if ( function_exists( 'wp_delete_file' ) ) {
			wp_delete_file( $path );
		} elseif ( is_file( $path ) ) {
			unlink( $path );
		}
	}
}
