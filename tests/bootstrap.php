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
		'post_status'  => 'publish',
		'post_title'   => 'Page ' . $id,
		'post_name'    => 'page-' . $id,
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
/** Pages (or any post) with a given meta key and value. Only the arguments the plugin uses. */
function get_posts( $args = array() ) {
	$out = array();
	foreach ( $GLOBALS['twd_stub']['posts'] as $id => $post ) {
		if ( isset( $args['post_type'] ) && $post->post_type !== $args['post_type'] ) {
			continue;
		}
		if ( isset( $args['meta_key'] ) && get_post_meta( $id, $args['meta_key'], true ) !== $args['meta_value'] ) {
			continue;
		}
		$out[] = $id;
	}
	return array_slice( $out, 0, isset( $args['numberposts'] ) ? $args['numberposts'] : 100 );
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
function is_front_page() {
	return ! empty( $GLOBALS['twd_stub']['front'] );
}
function wp_json_encode( $data, $flags = 0 ) {
	return json_encode( $data, $flags );
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
	if ( ! empty( $GLOBALS['twd_stub']['option_throws'] ) ) {
		throw new Exception( 'database unavailable' );
	}
	return array_key_exists( $name, $GLOBALS['twd_stub']['options'] ) ? $GLOBALS['twd_stub']['options'][ $name ] : $default;
}
function update_option( $name, $value ) {
	$GLOBALS['twd_stub']['options'][ $name ] = $value;
	return true;
}
function wp_register_style( $handle, $src, $deps = array(), $ver = false ) {
	$GLOBALS['twd_stub']['styles'][ $handle ] = array( 'src' => $src, 'ver' => $ver, 'enqueued' => false, 'inline' => '' );
}
function wp_enqueue_style( $handle, $src = '', $deps = array(), $ver = false ) {
	if ( ! isset( $GLOBALS['twd_stub']['styles'][ $handle ] ) ) {
		wp_register_style( $handle, $src, $deps, $ver );
	}
	$GLOBALS['twd_stub']['styles'][ $handle ]['enqueued'] = true;
}
function wp_add_inline_style( $handle, $css ) {
	if ( ! isset( $GLOBALS['twd_stub']['styles'][ $handle ] ) ) {
		wp_register_style( $handle, '' );
	}
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
		'nonce_ok'  => 'good-nonce',
		'scripts'   => array(),
		'routes'    => array(),
		'headers'   => array(),
		'users'     => array( 7 => 'Test Editor' ),
		'inserted'  => array(),
		'attachments' => array(),
		'media_urls'  => array(),
		'media_meta'  => array(),
		'option_throws' => false,
		'bloginfo'  => array(),
		'privacy_url' => '',
		'updated'   => array(),
		'insert_fails' => false,
		'loop_reset' => true,
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
// caps: true or false for every capability, or a list such as array( 'edit_pages', 'edit_post:12' ).
function current_user_can( $cap, $arg = null ) {
	$caps = $GLOBALS['twd_stub']['caps'];
	if ( ! is_array( $caps ) ) {
		return (bool) $caps;
	}
	return in_array( $cap, $caps, true ) || ( null !== $arg && in_array( $cap . ':' . $arg, $caps, true ) );
}
function is_user_logged_in() {
	return (int) $GLOBALS['twd_stub']['user'] > 0;
}
function wp_verify_nonce( $nonce, $action ) {
	return is_string( $nonce ) && $nonce === $GLOBALS['twd_stub']['nonce_ok'] && 'wp_rest' === $action ? 1 : false;
}
function wp_create_nonce( $action ) {
	return $GLOBALS['twd_stub']['nonce_ok'];
}
function apply_filters( $name, $value ) {
	$args = func_get_args();
	array_shift( $args );
	if ( ! empty( $GLOBALS['twd_stub']['hooks'][ $name ] ) ) {
		foreach ( $GLOBALS['twd_stub']['hooks'][ $name ] as $cb ) {
			$args[0] = call_user_func_array( $cb, $args );
		}
	}
	return $args[0];
}
function do_action( $name ) {
	$args = func_get_args();
	array_shift( $args );
	if ( ! empty( $GLOBALS['twd_stub']['hooks'][ $name ] ) ) {
		foreach ( $GLOBALS['twd_stub']['hooks'][ $name ] as $cb ) {
			call_user_func_array( $cb, $args );
		}
	}
}
function get_permalink( $id ) {
	return 'https://example.test/?page_id=' . (int) $id;
}
function get_userdata( $id ) {
	$users = $GLOBALS['twd_stub']['users'];
	return isset( $users[ (int) $id ] ) ? (object) array( 'ID' => (int) $id, 'display_name' => $users[ (int) $id ] ) : false;
}
function esc_url_raw( $url ) {
	return $url;
}
function rest_url( $path = '' ) {
	return 'https://example.test/wp-json/' . ltrim( $path, '/' );
}
function register_rest_route( $ns, $route, $args ) {
	$GLOBALS['twd_stub']['routes'][] = array( 'ns' => $ns, 'route' => $route, 'args' => $args );
}
function wp_register_script( $handle, $src, $deps = array(), $ver = false, $in_footer = false ) {
	$GLOBALS['twd_stub']['scripts'][ $handle ] = array( 'src' => $src, 'enqueued' => false, 'data' => null );
}
function wp_enqueue_script( $handle, $src = '', $deps = array(), $ver = false, $in_footer = false ) {
	if ( ! isset( $GLOBALS['twd_stub']['scripts'][ $handle ] ) ) {
		wp_register_script( $handle, $src, $deps, $ver, $in_footer );
	}
	$GLOBALS['twd_stub']['scripts'][ $handle ]['enqueued'] = true;
}
function wp_localize_script( $handle, $name, $data ) {
	$GLOBALS['twd_stub']['scripts'][ $handle ]['data'] = $data;
	return true;
}
function wp_enqueue_media() {
	$GLOBALS['twd_stub']['media_enqueued'] = true;
}
function nocache_headers() {
	$GLOBALS['twd_stub']['headers'][] = 'nocache';
}

/** Minimal stand-in for WP_REST_Request. */
class WP_REST_Request {
	private $params;
	private $headers;
	private $body;
	public function __construct( $params = array(), $headers = array(), $body = null ) {
		$this->params  = $params;
		$this->headers = array_change_key_case( $headers, CASE_LOWER );
		$this->body    = null === $body ? json_encode( $params ) : $body;
	}
	public function get_param( $key ) {
		return array_key_exists( $key, $this->params ) ? $this->params[ $key ] : null;
	}
	public function get_header( $name ) {
		$name = strtolower( str_replace( '_', '-', $name ) );
		return array_key_exists( $name, $this->headers ) ? $this->headers[ $name ] : null;
	}
	public function get_body() {
		return $this->body;
	}
}

/** Media library in the stand-in: url => array( id ), and id => metadata. */
function attachment_url_to_postid( $url ) {
	return isset( $GLOBALS['twd_stub']['media_urls'][ $url ] ) ? (int) $GLOBALS['twd_stub']['media_urls'][ $url ] : 0;
}
function wp_get_attachment_metadata( $id ) {
	return isset( $GLOBALS['twd_stub']['media_meta'][ (int) $id ] ) ? $GLOBALS['twd_stub']['media_meta'][ (int) $id ] : false;
}
function esc_attr( $text ) {
	return htmlspecialchars( (string) $text, ENT_QUOTES );
}
function home_url( $path = '' ) {
	return 'https://example.test' . $path;
}
function get_bloginfo( $what = 'name' ) {
	$info = isset( $GLOBALS['twd_stub']['bloginfo'] ) ? $GLOBALS['twd_stub']['bloginfo'] : array();
	return isset( $info[ $what ] ) ? $info[ $what ] : ( 'name' === $what ? 'Test Site' : '' );
}
/** Attachments in the stand-in: id => array( url, width, height, is_image ). */
function wp_get_attachment_image_src( $id, $size = 'thumbnail' ) {
	$a = isset( $GLOBALS['twd_stub']['attachments'][ (int) $id ] ) ? $GLOBALS['twd_stub']['attachments'][ (int) $id ] : null;
	return ( $a && ! empty( $a['is_image'] ) ) ? array( $a['url'], $a['w'], $a['h'], false ) : false;
}
function wp_attachment_is_image( $id ) {
	return ! empty( $GLOBALS['twd_stub']['attachments'][ (int) $id ]['is_image'] );
}
function wp_get_attachment_image_url( $id, $size = 'thumbnail' ) {
	$a = isset( $GLOBALS['twd_stub']['attachments'][ (int) $id ] ) ? $GLOBALS['twd_stub']['attachments'][ (int) $id ] : null;
	return ( $a && ! empty( $a['is_image'] ) ) ? $a['url'] : false;
}
function sanitize_title( $title ) {
	$t = strtolower( trim( preg_replace( '/[^a-z0-9]+/i', '-', (string) $title ), '-' ) );
	return $t;
}
function get_privacy_policy_url() {
	return isset( $GLOBALS['twd_stub']['privacy_url'] ) ? $GLOBALS['twd_stub']['privacy_url'] : '';
}
function get_pages( $args = array() ) {
	$out = array();
	foreach ( $GLOBALS['twd_stub']['posts'] as $post ) {
		if ( 'page' === $post->post_type && 'publish' === $post->post_status ) {
			$out[] = $post;
		}
	}
	return array_slice( $out, 0, isset( $args['number'] ) ? $args['number'] : 100 );
}
function wp_strip_all_tags( $text ) {
	return trim( strip_tags( (string) $text ) );
}
function get_the_title( $id = 0 ) {
	$post = get_post( $id ? $id : get_the_ID() );
	return $post ? $post->post_title : '';
}
/** Creates a post in the stand-in store. Mimics core: refuses a page template that is not registered. */
function wp_insert_post( $args, $wp_error = false ) {
	$GLOBALS['twd_stub']['inserted'][] = $args;
	$ids = array_keys( $GLOBALS['twd_stub']['posts'] );
	$id  = ( $ids ? max( $ids ) : 100 ) + 1;
	if ( ! empty( $GLOBALS['twd_stub']['insert_fails'] ) ) {
		return $wp_error ? new WP_Error( 'insert_failed', 'Could not create the page.' ) : 0;
	}
	twd_stub_add_post( $id, $args['post_type'], $args['post_content'] );
	$GLOBALS['twd_stub']['posts'][ $id ]->post_status = $args['post_status'];
	$GLOBALS['twd_stub']['posts'][ $id ]->post_title  = $args['post_title'];
	$GLOBALS['twd_stub']['posts'][ $id ]->post_author = $args['post_author'];
	if ( ! empty( $args['page_template'] ) ) {
		update_post_meta( $id, '_wp_page_template', $args['page_template'] );
	}
	return $id;
}
function wp_update_post( $args, $wp_error = false ) {
	$GLOBALS['twd_stub']['updated'][] = $args;
	$post = get_post( $args['ID'] );
	if ( ! $post ) {
		return $wp_error ? new WP_Error( 'invalid_post', 'Invalid post.' ) : 0;
	}
	foreach ( $args as $k => $v ) {
		$post->$k = $v;
	}
	return $args['ID'];
}
function have_posts() {
	static $done = false;
	if ( ! empty( $GLOBALS['twd_stub']['loop_reset'] ) ) {
		$done = false;
		$GLOBALS['twd_stub']['loop_reset'] = false;
	}
	if ( $done ) {
		return false;
	}
	$done = true;
	return true;
}
function the_post() {}
function get_header() {
	echo '[HEADER]';
}
function get_footer() {
	echo '[FOOTER]';
}
function get_page_template_slug() {
	return get_post_meta( get_queried_object_id(), '_wp_page_template', true );
}

function check_admin_referer( $action ) {
	return $GLOBALS['twd_stub']['referer_ok'];
}
function wp_die( $message = '', $title = '', $args = array() ) {
	$GLOBALS['twd_stub']['die_args'] = $args;
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

require_once ABSPATH . 'includes/class-twd-sk-safe.php';
require_once ABSPATH . 'includes/class-twd-sk-registry.php';
require_once ABSPATH . 'includes/class-twd-sk-sanitizer.php';
require_once ABSPATH . 'includes/class-twd-sk-store.php';
require_once ABSPATH . 'includes/class-twd-sk-prompt.php';
require_once ABSPATH . 'includes/class-twd-sk-page.php';
require_once ABSPATH . 'includes/class-twd-sk-packs.php';
require_once ABSPATH . 'includes/class-twd-sk-contrast.php';
require_once ABSPATH . 'includes/class-twd-sk-site.php';
require_once ABSPATH . 'includes/class-twd-sk-profile.php';
require_once ABSPATH . 'includes/class-twd-sk-chrome.php';
require_once ABSPATH . 'includes/class-twd-sk-elementor.php';
require_once ABSPATH . 'includes/class-twd-sk-starters.php';
require_once ABSPATH . 'includes/class-twd-sk-setup.php';
require_once ABSPATH . 'includes/class-twd-sk-mirror.php';
require_once ABSPATH . 'includes/class-twd-sk-images.php';
require_once ABSPATH . 'includes/class-twd-sk-quality.php';
require_once ABSPATH . 'includes/class-twd-sk-seo.php';
require_once ABSPATH . 'includes/class-twd-sk-schema.php';
require_once ABSPATH . 'includes/class-twd-sk-sections.php';
require_once ABSPATH . 'includes/class-twd-sk-ai.php';
require_once ABSPATH . 'includes/class-twd-sk-assets.php';
require_once ABSPATH . 'includes/class-twd-sk-updater.php';
require_once ABSPATH . 'includes/class-twd-sk-report.php';
require_once ABSPATH . 'includes/class-twd-sk-template.php';
require_once ABSPATH . 'includes/class-twd-sk-preview.php';
require_once ABSPATH . 'includes/class-twd-sk-rest.php';
require_once ABSPATH . 'includes/class-twd-sk-editor.php';
require_once ABSPATH . 'includes/class-twd-sk-modules.php';

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
