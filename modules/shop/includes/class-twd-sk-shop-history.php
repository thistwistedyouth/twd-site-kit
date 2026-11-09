<?php
/**
 * TWD_SK_Shop_History: up to ten versions of what the Edit product window looks after, kept in the
 * product's own meta, with Undo and Restore. Each version is a copy of the fields (the shape
 * TWD_SK_Shop_Product::fields() returns), never photos or anything else.
 *
 * Undo and Restore write an older copy back as a NEW version, so Undo can be undone. If the product
 * was changed somewhere else (the WooCommerce screen) since the last version, its state then is
 * recorded first, so nothing is lost.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class TWD_SK_Shop_History {

	const META = '_twd_sk_shop_versions';
	const MAX  = 10;

	public static function all( $id ) {
		$v = get_post_meta( (int) $id, self::META, true );
		return is_array( $v ) ? array_values( $v ) : array();
	}

	public static function latest( $id ) {
		$all = self::all( $id );
		return $all ? end( $all ) : null;
	}

	/** Add a version. Returns its number. */
	public static function record( $id, $fields, $note, $from = 0 ) {
		$list = self::all( $id );
		$next = 1;
		foreach ( $list as $item ) {
			$next = max( $next, (int) $item['v'] + 1 );
		}
		$list[] = array(
			'v'    => $next,
			'time' => time(),
			'user' => (int) get_current_user_id(),
			'note' => TWD_SK_Shop_Text::plain( (string) $note, 120 ),
			'from' => (int) $from,
			'fp'   => TWD_SK_Shop_Product::fingerprint( $fields ),
			'fields' => $fields,
		);
		while ( count( $list ) > self::MAX ) {
			array_shift( $list );
		}
		update_post_meta( (int) $id, self::META, wp_slash( $list ) );
		return $next;
	}

	/** When the product no longer matches the last version (or has none), keep its present state first. */
	public static function keep_live( $id, $live ) {
		$latest = self::latest( $id );
		if ( ! $latest ) {
			return self::record( $id, $live, 'As it was before the first change here' );
		}
		if ( $latest['fp'] !== TWD_SK_Shop_Product::fingerprint( $live ) ) {
			return self::record( $id, $live, 'Changed outside this window' );
		}
		return 0;
	}

	/**
	 * The version Undo goes back to, or null. Undo after Undo keeps walking back: a version that was
	 * itself a copy of version N counts as N.
	 */
	public static function undo_target( $id, $live = null ) {
		$list = self::all( $id );
		if ( ! $list ) {
			return null;
		}
		$cur = end( $list );
		// Changed outside the window since: Undo goes back to the last version made here.
		if ( null !== $live && $cur['fp'] !== TWD_SK_Shop_Product::fingerprint( $live ) ) {
			return $cur;
		}
		$at = count( $list ) - 1;
		if ( ! empty( $cur['from'] ) ) {
			foreach ( $list as $i => $item ) {
				if ( (int) $item['v'] === (int) $cur['from'] ) {
					$at = $i;
					break;
				}
			}
		}
		return $at > 0 ? $list[ $at - 1 ] : null;
	}

	public static function find( $id, $v ) {
		foreach ( self::all( $id ) as $item ) {
			if ( (int) $item['v'] === (int) $v ) {
				return $item;
			}
		}
		return null;
	}

	/** Newest first, without the fields: what the History list shows. */
	public static function summary( $id ) {
		$out = array();
		foreach ( array_reverse( self::all( $id ) ) as $item ) {
			$who = '';
			if ( ! empty( $item['user'] ) && function_exists( 'get_userdata' ) ) {
				$u   = get_userdata( (int) $item['user'] );
				$who = $u && isset( $u->display_name ) ? (string) $u->display_name : '';
			}
			$out[] = array( 'v' => (int) $item['v'], 'time' => (int) $item['time'], 'who' => $who, 'note' => (string) $item['note'] );
		}
		return $out;
	}
}
