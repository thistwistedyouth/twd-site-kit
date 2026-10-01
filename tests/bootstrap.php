<?php
/**
 * Test bootstrap: minimal WordPress stubs, then the plugin classes.
 * No Composer, no PHPUnit, no WordPress needed.
 */

define( 'ABSPATH', dirname( __DIR__ ) . '/' );

class WP_Error {
	private $code;
	private $message;
	private $data;

	public function __construct( $code = '', $message = '', $data = '' ) {
		$this->code    = $code;
		$this->message = $message;
		$this->data    = $data;
	}
	public function get_error_code() {
		return $this->code;
	}
	public function get_error_message() {
		return $this->message;
	}
	public function get_error_data() {
		return $this->data;
	}
}

function is_wp_error( $thing ) {
	return $thing instanceof WP_Error;
}

function plugin_dir_path( $file ) {
	return dirname( $file ) . '/';
}

define( 'TWD_SK_PATH', ABSPATH );
define( 'TWD_SK_URL', 'https://example.test/wp-content/plugins/twd-site-kit/' );
define( 'TWD_SK_VERSION', '1.0.0' );

$GLOBALS['twd_stub'] = array();

function twd_stub_reset() {
	$GLOBALS['twd_stub'] = array(
		'meta'       => array(),
		'posts'      => array(),
		'user'       => 7,
		'shortcodes' => array(),
		'post_id'    => 0,
		'queried'    => 0,
		'singular'   => true,
		'options'    => array(),
		'hooks'      => array(),
		'styles'     => array(),
	) + twd_sk_stub_defaults();
}
twd_stub_reset();

function twd_stub_add_post( $id, $type = 'page', $content = '' ) {
	$GLOBALS['twd_stub']['posts'][ $id ] = (object) array(
		'ID'           => $id,
		'post_type'    => $type,
		'post_content' => $content,
	);
}

// WordPress adds slashes to request data and update_post_meta() removes them
// again. These mimic that so a missing wp_slash() in the store shows up here.
function wp_slash( $value ) {
	if ( is_array( $value ) ) {
		return array_map( 'wp_slash', $value );
	}
	return is_string( $value ) ? addslashes( $value ) : $value;
}
function wp_unslash( $value ) {
	if ( is_array( $value ) ) {
		return array_map( 'wp_unslash', $value );
	}
	return is_string( $value ) ? stripslashes( $value ) : $value;
}

// Values are serialised on the way in and out, like the real postmeta table.
function update_post_meta( $id, $key, $value ) {
	$GLOBALS['twd_stub']['meta'][ $id ][ $key ] = serialize( wp_unslash( $value ) );
	return true;
}
function get_post_meta( $id, $key, $single = false ) {
	if ( ! isset( $GLOBALS['twd_stub']['meta'][ $id ][ $key ] ) ) {
		return $single ? '' : array();
	}
	$value = unserialize( $GLOBALS['twd_stub']['meta'][ $id ][ $key ] );
	return $single ? $value : array( $value );
}
function twd_stub_meta_keys( $id ) {
	return isset( $GLOBALS['twd_stub']['meta'][ $id ] ) ? array_keys( $GLOBALS['twd_stub']['meta'][ $id ] ) : array();
}

function get_post( $id ) {
	return isset( $GLOBALS['twd_stub']['posts'][ $id ] ) ? $GLOBALS['twd_stub']['posts'][ $id ] : null;
}
function get_post_field( $field, $id ) {
	$post = get_post( $id );
	return $post && isset( $post->$field ) ? $post->$field : '';
}
function get_current_user_id() {
	return $GLOBALS['twd_stub']['user'];
}
function get_the_ID() {
	return $GLOBALS['twd_stub']['post_id'];
}
function get_queried_object_id() {
	return $GLOBALS['twd_stub']['queried'];
}
function is_singular( $type = '' ) {
	return $GLOBALS['twd_stub']['singular'];
}
function add_filter( $name, $callback ) {
	$GLOBALS['twd_stub']['hooks'][ $name ][] = $callback;
}
function add_action( $name, $callback ) {
	add_filter( $name, $callback );
}
function get_option( $name, $default = false ) {
	return array_key_exists( $name, $GLOBALS['twd_stub']['options'] ) ? $GLOBALS['twd_stub']['options'][ $name ] : $default;
}
function update_option( $name, $value ) {
	$GLOBALS['twd_stub']['options'][ $name ] = $value;
	return true;
}
function wp_register_style( $handle, $src, $deps = array(), $ver = false ) {
	$GLOBALS['twd_stub']['styles'][ $handle ] = array( 'src' => $src, 'ver' => $ver, 'enqueued' => false, 'inline' => '' );
}
function wp_enqueue_style( $handle ) {
	$GLOBALS['twd_stub']['styles'][ $handle ]['enqueued'] = true;
}
function wp_add_inline_style( $handle, $css ) {
	$GLOBALS['twd_stub']['styles'][ $handle ]['inline'] .= $css;
}
function add_shortcode( $tag, $callback ) {
	$GLOBALS['twd_stub']['shortcodes'][ $tag ] = $callback;
}
// Stand-in for WordPress: turns ANY [tag ...] into a visible marker, so a test
// can tell whether a shortcode was run.
function do_shortcode( $content ) {
	return preg_replace_callback(
		'/\[(\w+)([^\]]*)\]/',
		function ( $m ) {
			return '<RUN:' . $m[1] . $m[2] . '>';
		},
		$content
	);
}


// -- Updater stubs ------------------------------------------------------------

define( 'DAY_IN_SECONDS', 86400 );
define( 'HOUR_IN_SECONDS', 3600 );

class TWD_SK_Stub_Redirect extends Exception {}
class TWD_SK_Stub_Die extends Exception {}

function twd_sk_stub_defaults() {
	return array(
		'admin'      => true,
		'caps'       => true,
		'referer_ok' => true,
		'screen'     => 'plugins',
		'http'       => array(),
		'requests'   => array(),
		'transients' => array(),
		'site_transients_deleted' => array(),
		'update_plugins_called'   => 0,
		'deleted_files'           => array(),
	);
}

function is_admin() {
	return $GLOBALS['twd_stub']['admin'];
}
function plugin_basename( $file ) {
	return 'twd-site-kit/' . basename( $file );
}
function plugin_dir_url( $file ) {
	return 'https://example.test/wp-content/plugins/twd-site-kit/';
}
function get_transient( $key ) {
	return array_key_exists( $key, $GLOBALS['twd_stub']['transients'] ) ? $GLOBALS['twd_stub']['transients'][ $key ]['value'] : false;
}
function set_transient( $key, $value, $seconds = 0 ) {
	$GLOBALS['twd_stub']['transients'][ $key ] = array( 'value' => $value, 'seconds' => $seconds );
	return true;
}
function delete_transient( $key ) {
	unset( $GLOBALS['twd_stub']['transients'][ $key ] );
	return true;
}
function delete_site_transient( $key ) {
	$GLOBALS['twd_stub']['site_transients_deleted'][] = $key;
	return true;
}
function wp_update_plugins() {
	$GLOBALS['twd_stub']['update_plugins_called']++;
}
// A stand-in for WordPress HTTP: answers from a table of url => array( code, body ) or a WP_Error.
function wp_safe_remote_get( $url, $args = array() ) {
	$GLOBALS['twd_stub']['requests'][] = array( 'url' => $url, 'args' => $args );
	if ( ! isset( $GLOBALS['twd_stub']['http'][ $url ] ) ) {
		return new WP_Error( 'http_no_route', 'No route to ' . $url );
	}
	$answer = $GLOBALS['twd_stub']['http'][ $url ];
	if ( $answer instanceof WP_Error ) {
		return $answer;
	}
	if ( ! empty( $args['stream'] ) && ! empty( $args['filename'] ) ) {
		file_put_contents( $args['filename'], $answer['body'] );
	}
	return $answer;
}
function wp_remote_get( $url, $args = array() ) {
	return wp_safe_remote_get( $url, $args );
}
function wp_remote_retrieve_response_code( $response ) {
	return $response['code'];
}
function wp_remote_retrieve_body( $response ) {
	return $response['body'];
}
function wp_tempnam( $name = '' ) {
	return tempnam( sys_get_temp_dir(), 'twdsk' );
}
function wp_delete_file( $path ) {
	$GLOBALS['twd_stub']['deleted_files'][] = $path;
	if ( is_file( $path ) ) {
		unlink( $path );
	}
}
function wp_nonce_url( $url, $action ) {
	return $url . '&_wpnonce=NONCE_' . $action;
}
function admin_url( $path = '' ) {
	return 'https://example.test/wp-admin/' . $path;
}
function add_query_arg( $args, $url ) {
	return $url . ( false === strpos( $url, '?' ) ? '?' : '&' ) . http_build_query( $args );
}
function esc_url( $url ) {
	return htmlspecialchars( $url, ENT_QUOTES );
}
function esc_html( $text ) {
	return htmlspecialchars( $text, ENT_QUOTES );
}
function esc_html__( $text, $domain = '' ) {
	return esc_html( $text );
}
function __( $text, $domain = '' ) {
	return $text;
}
function sanitize_text_field( $text ) {
	return trim( strip_tags( $text ) );
}
function current_user_can( $cap ) {
	return $GLOBALS['twd_stub']['caps'];
}
function check_admin_referer( $action ) {
	return $GLOBALS['twd_stub']['referer_ok'];
}
function wp_die( $message = '' ) {
	throw new TWD_SK_Stub_Die( $message );
}
function wp_safe_redirect( $url ) {
	throw new TWD_SK_Stub_Redirect( $url );
}
function get_current_screen() {
	return (object) array( 'id' => $GLOBALS['twd_stub']['screen'] );
}
// Keeps only the allowed tags and removes every attribute, like the real wp_kses with bare tag rules.
function wp_kses( $html, $allowed ) {
	$html = strip_tags( $html, '<' . implode( '><', array_keys( $allowed ) ) . '>' );
	return preg_replace( '/<([a-z0-9]+)\s[^>]*>/i', '<$1>', $html );
}

require_once ABSPATH . 'includes/class-twd-sk-registry.php';
require_once ABSPATH . 'includes/class-twd-sk-sanitizer.php';
require_once ABSPATH . 'includes/class-twd-sk-store.php';
require_once ABSPATH . 'includes/class-twd-sk-page.php';
require_once ABSPATH . 'includes/class-twd-sk-packs.php';
require_once ABSPATH . 'includes/class-twd-sk-assets.php';
require_once ABSPATH . 'includes/class-twd-sk-updater.php';

// Tiny assertion helpers ---------------------------------------------------

class TWD_SK_Test_Failure extends Exception {}

$GLOBALS['twd_sk_tests'] = array();
$GLOBALS['twd_sk_notes'] = array();

// A line printed after the run, for facts worth seeing that are not failures.
function twd_sk_note( $msg ) {
	$GLOBALS['twd_sk_notes'][ $msg ] = $msg;
}

function twd_sk_test( $name, $fn ) {
	$GLOBALS['twd_sk_tests'][] = array( $name, $fn );
}
function twd_sk_fail( $msg ) {
	throw new TWD_SK_Test_Failure( $msg );
}
function twd_sk_eq( $expected, $actual, $what = '' ) {
	if ( $expected !== $actual ) {
		twd_sk_fail( ( $what ? $what . ': ' : '' ) . 'expected ' . var_export( $expected, true ) . ' got ' . var_export( $actual, true ) );
	}
}
function twd_sk_true( $cond, $what = '' ) {
	if ( true !== $cond ) {
		twd_sk_fail( ( $what ? $what . ': ' : '' ) . 'expected true' );
	}
}
function twd_sk_has( $needle, $haystack, $what = '' ) {
	if ( false === strpos( $haystack, $needle ) ) {
		twd_sk_fail( ( $what ? $what . ': ' : '' ) . 'expected to find ' . var_export( $needle, true ) . ' in ' . var_export( $haystack, true ) );
	}
}
function twd_sk_hasnt( $needle, $haystack, $what = '' ) {
	if ( false !== strpos( $haystack, $needle ) ) {
		twd_sk_fail( ( $what ? $what . ': ' : '' ) . 'expected NOT to find ' . var_export( $needle, true ) . ' in ' . var_export( $haystack, true ) );
	}
}
function twd_sk_is_error( $code, $thing, $what = '' ) {
	if ( ! is_wp_error( $thing ) ) {
		twd_sk_fail( ( $what ? $what . ': ' : '' ) . 'expected a WP_Error (' . $code . ')' );
	}
	twd_sk_eq( $code, $thing->get_error_code(), $what . ' error code' );
}
