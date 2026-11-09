<?php
/**
 * TWD_SK_Shop_Rest: the Edit product window's routes, under twd-site-kit/v1/shop/. They exist only
 * while the Shop module is on. Every route goes through the kit's one guard (nonce, sign-in,
 * capability, size, rate limit), then checks the product itself.
 *
 *   GET  shop/products/{id}           what the window opens on
 *   POST shop/products/{id}           save (fields, base)
 *   POST shop/products/{id}/undo      one step back
 *   POST shop/products/{id}/restore   a chosen version (v)
 *   POST shop/products/{id}/status    publish a draft (status publish, confirm)
 *   POST shop/products                a new draft (category)
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class TWD_SK_Shop_Rest {

	public static function init() {
		add_action( 'rest_api_init', array( __CLASS__, 'register_routes' ) );
	}

	public static function register_routes() {
		$ns = TWD_SK_REST::ROUTE_NS;
		$id = '/shop/products/(?P<id>\d+)';
		register_rest_route( $ns, $id, array(
			array(
				'methods'             => 'GET',
				'callback'            => array( __CLASS__, 'get_product' ),
				'permission_callback' => array( __CLASS__, 'can_read' ),
			),
			array(
				'methods'             => 'POST',
				'callback'            => array( __CLASS__, 'post_product' ),
				'permission_callback' => array( __CLASS__, 'can_write' ),
			),
		) );
		register_rest_route( $ns, $id . '/undo', array(
			'methods'             => 'POST',
			'callback'            => array( __CLASS__, 'post_undo' ),
			'permission_callback' => array( __CLASS__, 'can_write' ),
		) );
		register_rest_route( $ns, $id . '/restore', array(
			'methods'             => 'POST',
			'callback'            => array( __CLASS__, 'post_restore' ),
			'permission_callback' => array( __CLASS__, 'can_write' ),
		) );
		register_rest_route( $ns, $id . '/status', array(
			'methods'             => 'POST',
			'callback'            => array( __CLASS__, 'post_status' ),
			'permission_callback' => array( __CLASS__, 'can_publish' ),
		) );
		register_rest_route( $ns, '/shop/products', array(
			'methods'             => 'POST',
			'callback'            => array( __CLASS__, 'post_create' ),
			'permission_callback' => array( __CLASS__, 'can_create' ),
		) );
	}

	// -- Who may ---------------------------------------------------------------

	private static function product_check( $request ) {
		$id = (int) $request->get_param( 'id' );
		if ( $id <= 0 || ! current_user_can( 'edit_post', $id ) ) {
			return new WP_Error( 'twd_sk_forbidden', 'You do not have permission to edit this product.', array( 'status' => 403 ) );
		}
		$post = get_post( $id );
		if ( ! $post || 'product' !== $post->post_type ) {
			return new WP_Error( 'twd_sk_shop_missing', 'That product was not found.', array( 'status' => 404 ) );
		}
		return true;
	}

	public static function can_read( $request ) {
		$ok = TWD_SK_REST::gate( $request, 'read', 'edit_products' );
		return true === $ok ? self::product_check( $request ) : $ok;
	}

	public static function can_write( $request ) {
		$ok = TWD_SK_REST::gate( $request, 'write', 'edit_products' );
		return true === $ok ? self::product_check( $request ) : $ok;
	}

	public static function can_publish( $request ) {
		$ok = TWD_SK_REST::gate( $request, 'write', 'publish_products' );
		return true === $ok ? self::product_check( $request ) : $ok;
	}

	public static function can_create( $request ) {
		return TWD_SK_REST::gate( $request, 'create', 'edit_products' );
	}

	// -- Doing ---------------------------------------------------------------------

	private static function editable_product( $id ) {
		$product = TWD_SK_Shop_Product::get( $id );
		if ( ! $product ) {
			return new WP_Error( 'twd_sk_shop_missing', 'That product was not found.', array( 'status' => 404 ) );
		}
		if ( ! TWD_SK_Shop_Product::editable( $product ) ) {
			return new WP_Error( 'twd_sk_shop_not_simple', 'This kind of product (' . $product->get_type() . ') is edited in WooCommerce, not here.', array( 'status' => 422 ) );
		}
		return $product;
	}

	/** Save what the window sent. $base is the fingerprint the window opened on. */
	public static function save( $id, $input, $base ) {
		$product = self::editable_product( $id );
		if ( is_wp_error( $product ) ) {
			return $product;
		}
		$live = TWD_SK_Shop_Product::fields( $product );
		if ( ! is_string( $base ) || TWD_SK_Shop_Product::fingerprint( $live ) !== $base ) {
			return new WP_Error( 'twd_sk_conflict', 'This product was changed somewhere else since you opened it. Reload the page to see the latest, then make your change again.', array( 'status' => 409 ) );
		}
		$checked = TWD_SK_Shop_Product::validate( $input, $product );
		if ( is_wp_error( $checked ) ) {
			return $checked;
		}
		$new = $checked['fields'];
		if ( TWD_SK_Shop_Product::fingerprint( $new ) === TWD_SK_Shop_Product::fingerprint( $live ) ) {
			return array( 'saved' => false, 'notes' => array( 'Nothing had changed, so nothing was saved.' ) );
		}
		TWD_SK_Shop_History::keep_live( $id, $live );
		$done = TWD_SK_Shop_Product::write( $product, $live, $new );
		if ( is_wp_error( $done ) ) {
			return $done;
		}
		$after = TWD_SK_Shop_Product::get( $id );
		TWD_SK_Shop_History::record( $id, TWD_SK_Shop_Product::fields( $after ), 'Saved in the Edit product window' );
		return array( 'saved' => true, 'notes' => $checked['notes'] );
	}

	/** Put an older version back, as a new version. */
	private static function put_back( $id, $target, $note ) {
		$product = self::editable_product( $id );
		if ( is_wp_error( $product ) ) {
			return $product;
		}
		$live = TWD_SK_Shop_Product::fields( $product );
		$done = TWD_SK_Shop_Product::write( $product, $live, $target['fields'] );
		if ( is_wp_error( $done ) ) {
			return $done;
		}
		$after = TWD_SK_Shop_Product::get( $id );
		TWD_SK_Shop_History::record( $id, TWD_SK_Shop_Product::fields( $after ), $note, (int) $target['v'] );
		return array( 'saved' => true, 'notes' => array() );
	}

	public static function undo( $id ) {
		$product = self::editable_product( $id );
		if ( is_wp_error( $product ) ) {
			return $product;
		}
		$live   = TWD_SK_Shop_Product::fields( $product );
		$target = TWD_SK_Shop_History::undo_target( $id, $live );
		if ( ! $target ) {
			return new WP_Error( 'twd_sk_no_undo', 'There is no earlier version to go back to.', array( 'status' => 409 ) );
		}
		TWD_SK_Shop_History::keep_live( $id, $live );
		return self::put_back( $id, $target, 'Undo: back to version ' . (int) $target['v'] );
	}

	public static function restore( $id, $v ) {
		$target = TWD_SK_Shop_History::find( $id, $v );
		if ( ! $target ) {
			return new WP_Error( 'twd_sk_shop_no_version', 'That version is not kept any more.', array( 'status' => 404 ) );
		}
		$product = self::editable_product( $id );
		if ( is_wp_error( $product ) ) {
			return $product;
		}
		TWD_SK_Shop_History::keep_live( $id, TWD_SK_Shop_Product::fields( $product ) );
		return self::put_back( $id, $target, 'Restored version ' . (int) $v );
	}

	// -- Route callbacks -------------------------------------------------------------

	private static function respond( $id, $result ) {
		if ( is_wp_error( $result ) ) {
			return $result;
		}
		$data          = TWD_SK_Shop_Product::read( $id );
		$data['saved'] = ! empty( $result['saved'] );
		$data['notes'] = isset( $result['notes'] ) ? $result['notes'] : array();
		return $data;
	}

	public static function get_product( $request ) {
		return TWD_SK_Shop_Product::read( (int) $request->get_param( 'id' ) );
	}

	public static function post_product( $request ) {
		$id = (int) $request->get_param( 'id' );
		return self::respond( $id, self::save( $id, $request->get_param( 'fields' ), $request->get_param( 'base' ) ) );
	}

	public static function post_undo( $request ) {
		$id = (int) $request->get_param( 'id' );
		return self::respond( $id, self::undo( $id ) );
	}

	public static function post_restore( $request ) {
		$id = (int) $request->get_param( 'id' );
		return self::respond( $id, self::restore( $id, (int) $request->get_param( 'v' ) ) );
	}

	public static function post_status( $request ) {
		$id = (int) $request->get_param( 'id' );
		if ( 'publish' !== $request->get_param( 'status' ) || true !== (bool) $request->get_param( 'confirm' ) ) {
			return new WP_Error( 'twd_sk_bad_input', 'Say publish, and confirm.', array( 'status' => 400 ) );
		}
		$done = TWD_SK_Shop_Product::publish( $id );
		return self::respond( $id, is_wp_error( $done ) ? $done : array( 'saved' => true, 'notes' => array( 'Published. It is live in the shop now.' ) ) );
	}

	public static function post_create( $request ) {
		$id = TWD_SK_Shop_Product::create( (int) $request->get_param( 'category' ) );
		if ( is_wp_error( $id ) ) {
			return $id;
		}
		$url = function_exists( 'get_preview_post_link' ) ? (string) get_preview_post_link( $id ) : '';
		return array( 'id' => $id, 'url' => $url );
	}
}
