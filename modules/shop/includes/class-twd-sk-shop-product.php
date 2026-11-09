<?php
/**
 * TWD_SK_Shop_Product: reads and writes the parts of a product the Edit product window looks after,
 * always through WooCommerce's own product objects (never raw meta), so WooCommerce's caches and
 * lookup tables stay right and the product shows normally with the module off.
 *
 * The fields: title, price, reduced price, stock number (SKU), categories, for sale or sold, the
 * detail rows (local product attributes) with their "show as a label" ticks, the short summary and
 * the sections (the description). Photos, shipping, tax and everything else are never touched.
 * Global (taxonomy) attributes are shown read-only and kept as they are.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class TWD_SK_Shop_Product {

	const LABELS_META = '_twd_sk_shop_labels';
	const MAX_TITLE   = 120;
	const MAX_SKU     = 60;
	const MAX_ROWS    = 20;
	const MAX_LABEL   = 40;
	const MAX_VALUE   = 200;
	const MAX_TICKS   = 4;
	const MAX_CATS    = 10;
	const MAX_PHOTOS  = 30;
	const NEW_TITLE   = '[PLACEHOLDER: name of the piece]';

	/** Labels offered in the Details tab (any other label may be typed). */
	public static function suggested_labels() {
		return array( 'Period', 'Date', 'Maker', 'Metal', 'Stones', 'Hallmarks', 'Marks', 'Size', 'Measurements', 'Weight', 'Origin', 'Box' );
	}

	public static function get( $id ) {
		if ( ! function_exists( 'wc_get_product' ) ) {
			return null;
		}
		$p = wc_get_product( (int) $id );
		return is_object( $p ) ? $p : null;
	}

	/** Only one-off (simple) products are edited here; the rest open read-only. */
	public static function editable( $product ) {
		return is_object( $product ) && 'simple' === $product->get_type();
	}

	// -- Reading -----------------------------------------------------------------

	/** The fields the window looks after, as they are now. The same shape is stored in each version. */
	public static function fields( $product ) {
		$id      = (int) $product->get_id();
		$ticks   = get_post_meta( $id, self::LABELS_META, true );
		$ticks   = is_array( $ticks ) ? $ticks : array();
		$details = array();
		foreach ( (array) $product->get_attributes() as $att ) {
			if ( ! is_object( $att ) || $att->is_taxonomy() ) {
				continue;
			}
			// Read through the same clean-up as typed words, so an untouched value never counts as a change.
			$name      = TWD_SK_Shop_Text::plain( (string) $att->get_name(), self::MAX_LABEL );
			$details[] = array(
				'label' => $name,
				'value' => TWD_SK_Shop_Text::plain( implode( ' / ', array_map( 'strval', (array) $att->get_options() ) ), self::MAX_VALUE ),
				'tag'   => in_array( $name, $ticks, true ),
			);
		}
		$cats = array_map( 'intval', (array) $product->get_category_ids() );
		sort( $cats );
		return array(
			'title'    => TWD_SK_Shop_Text::plain( (string) $product->get_name(), self::MAX_TITLE ),
			'price'    => (string) $product->get_regular_price(),
			'sale'     => (string) $product->get_sale_price(),
			'sku'      => TWD_SK_Shop_Text::plain( (string) $product->get_sku(), self::MAX_SKU ),
			'cats'     => $cats,
			'sold'     => 'outofstock' === $product->get_stock_status(),
			'details'  => $details,
			'summary'  => TWD_SK_Shop_Text::clean_summary( (string) $product->get_short_description() ),
			'sections' => TWD_SK_Shop_Text::split( (string) $product->get_description() ),
			// Photos (test build 2): the main photo and the gallery, as media library numbers.
			'image'    => (int) $product->get_image_id(),
			'gallery'  => array_values( array_map( 'intval', (array) $product->get_gallery_image_ids() ) ),
		);
	}

	/** What the window shows for each photo: a small picture and its description (alt text). */
	public static function photos( $fields ) {
		$ids = array_merge( array( (int) $fields['image'] ), (array) $fields['gallery'] );
		$out = array();
		foreach ( $ids as $pid ) {
			$pid = (int) $pid;
			if ( $pid <= 0 || isset( $out[ $pid ] ) ) {
				continue;
			}
			$thumb       = function_exists( 'wp_get_attachment_image_url' ) ? (string) wp_get_attachment_image_url( $pid, 'thumbnail' ) : '';
			$alt         = get_post_meta( $pid, '_wp_attachment_image_alt', true );
			$out[ $pid ] = array( 'thumb' => $thumb, 'alt' => is_string( $alt ) ? $alt : '' );
		}
		return (object) $out;
	}

	/** A media library picture number, or 0. */
	private static function picture_id( $value ) {
		$id = ( is_int( $value ) || ( is_string( $value ) && ctype_digit( $value ) ) ) ? (int) $value : 0;
		return ( $id > 0 && function_exists( 'wp_attachment_is_image' ) && wp_attachment_is_image( $id ) ) ? $id : 0;
	}

	public static function fingerprint( $fields ) {
		return md5( (string) wp_json_encode( $fields ) );
	}

	/** Rows from WooCommerce's global attributes: shown, never changed here. */
	public static function fixed_rows( $product ) {
		$rows = array();
		foreach ( (array) $product->get_attributes() as $att ) {
			if ( ! is_object( $att ) || ! $att->is_taxonomy() ) {
				continue;
			}
			$name   = (string) $att->get_name();
			$label  = function_exists( 'wc_attribute_label' ) ? wc_attribute_label( $name ) : $name;
			$values = function_exists( 'wc_get_product_terms' ) ? wc_get_product_terms( $product->get_id(), $name, array( 'fields' => 'names' ) ) : array();
			$rows[] = array( 'label' => (string) $label, 'value' => implode( ', ', array_map( 'strval', (array) $values ) ) );
		}
		return $rows;
	}

	/** Every product category, for the Basics tab: id, name, parent. */
	public static function categories() {
		if ( ! function_exists( 'get_terms' ) ) {
			return array();
		}
		$terms = get_terms( array( 'taxonomy' => 'product_cat', 'hide_empty' => false ) );
		$out   = array();
		if ( is_array( $terms ) ) {
			foreach ( $terms as $t ) {
				$out[] = array( 'id' => (int) $t->term_id, 'name' => html_entity_decode( (string) $t->name, ENT_QUOTES, 'UTF-8' ), 'parent' => (int) $t->parent );
			}
		}
		return $out;
	}

	/** Everything the window needs to open on a product. */
	public static function read( $id ) {
		$product = self::get( $id );
		if ( ! $product ) {
			return new WP_Error( 'twd_sk_shop_missing', 'That product was not found.', array( 'status' => 404 ) );
		}
		$fields = self::fields( $product );
		$status = (string) $product->get_status();
		return array(
			'id'          => (int) $id,
			'type'        => (string) $product->get_type(),
			'editable'    => self::editable( $product ),
			'status'      => $status,
			'fields'      => $fields,
			'fixed'       => self::fixed_rows( $product ),
			'categories'  => self::categories(),
			'suggested'   => self::suggested_labels(),
			'markers'     => TWD_SK_Shop_Text::markers( $fields ),
			'photos'      => self::photos( $fields ),
			'can_upload'  => current_user_can( 'upload_files' ),
			'base'        => self::fingerprint( $fields ),
			'versions'    => TWD_SK_Shop_History::summary( (int) $id ),
			'can_undo'    => null !== TWD_SK_Shop_History::undo_target( (int) $id, $fields ),
			'can_publish' => 'publish' !== $status && current_user_can( 'publish_products' ),
			'edit_url'    => function_exists( 'get_edit_post_link' ) ? (string) get_edit_post_link( (int) $id, 'raw' ) : '',
			'limits'      => array(
				'title'    => self::MAX_TITLE,
				'rows'     => self::MAX_ROWS,
				'label'    => self::MAX_LABEL,
				'value'    => self::MAX_VALUE,
				'ticks'    => self::MAX_TICKS,
				'sections' => TWD_SK_Shop_Text::MAX_SECTIONS,
				'section'  => TWD_SK_Shop_Text::MAX_SECTION,
				'summary'  => TWD_SK_Shop_Text::MAX_SUMMARY,
				'heading'  => TWD_SK_Shop_Text::MAX_HEADING,
			),
		);
	}

	// -- Checking what was sent --------------------------------------------------

	/** A price as typed ("1,660", "1660.00", "1660") as WooCommerce keeps it, '' for none, or false. */
	public static function price( $value ) {
		if ( is_int( $value ) || is_float( $value ) ) {
			$value = (string) $value;
		}
		if ( ! is_string( $value ) ) {
			return false;
		}
		$value = preg_replace( '/[\s,]|\x{00A3}/u', '', $value );
		if ( '' === $value ) {
			return '';
		}
		if ( ! preg_match( '/^\d{1,7}(\.\d{1,2})?$/', $value ) ) {
			return false;
		}
		return $value;
	}

	/**
	 * Check and clean what the window sent. Returns array( fields, notes ) or a WP_Error naming every
	 * problem in plain words.
	 */
	public static function validate( $in, $product ) {
		$errors = array();
		$notes  = array();
		$in     = is_array( $in ) ? $in : array();
		$f      = array();

		$f['title'] = TWD_SK_Shop_Text::plain( isset( $in['title'] ) ? $in['title'] : '', self::MAX_TITLE );
		if ( '' === $f['title'] ) {
			$errors[] = 'The title cannot be empty.';
		}

		$price = self::price( isset( $in['price'] ) ? $in['price'] : '' );
		$sale  = self::price( isset( $in['sale'] ) ? $in['sale'] : '' );
		if ( false === $price ) {
			$errors[] = 'The price must be a number in pounds, for example 1250 or 1250.50.';
		}
		if ( false === $sale ) {
			$errors[] = 'The reduced price must be a number in pounds, or left empty.';
		}
		if ( is_string( $price ) && is_string( $sale ) && '' !== $sale && ( '' === $price || (float) $sale >= (float) $price ) ) {
			$errors[] = 'The reduced price must be lower than the price.';
		}
		$f['price'] = is_string( $price ) ? $price : '';
		$f['sale']  = is_string( $sale ) ? $sale : '';

		$f['sku'] = TWD_SK_Shop_Text::plain( isset( $in['sku'] ) ? $in['sku'] : '', self::MAX_SKU );

		$known = array();
		foreach ( self::categories() as $c ) {
			$known[ $c['id'] ] = true;
		}
		$cats = array();
		foreach ( isset( $in['cats'] ) && is_array( $in['cats'] ) ? $in['cats'] : array() as $c ) {
			$c = (int) $c;
			if ( $c > 0 && isset( $known[ $c ] ) && ! in_array( $c, $cats, true ) ) {
				$cats[] = $c;
			}
		}
		if ( count( $cats ) > self::MAX_CATS ) {
			$errors[] = 'Choose ' . self::MAX_CATS . ' categories at most.';
		}
		sort( $cats );
		$f['cats'] = $cats;
		$f['sold'] = ! empty( $in['sold'] ) && 'false' !== $in['sold'] && '0' !== $in['sold'];

		// Detail rows.
		$fixed = array();
		foreach ( self::fixed_rows( $product ) as $r ) {
			$fixed[ strtolower( $r['label'] ) ] = true;
		}
		$rows  = array();
		$seen  = array();
		$ticks = 0;
		foreach ( isset( $in['details'] ) && is_array( $in['details'] ) ? $in['details'] : array() as $row ) {
			if ( ! is_array( $row ) ) {
				continue;
			}
			$label = TWD_SK_Shop_Text::plain( isset( $row['label'] ) ? $row['label'] : '', self::MAX_LABEL );
			$raw   = isset( $row['value'] ) && is_string( $row['value'] ) ? $row['value'] : '';
			if ( false !== strpos( $raw, '|' ) ) {
				$raw     = str_replace( '|', '/', $raw );
				$notes[] = 'A | in a detail was changed to / (WooCommerce uses | to split values).';
			}
			$value = TWD_SK_Shop_Text::plain( $raw, self::MAX_VALUE );
			if ( '' === $label && '' === $value ) {
				continue;
			}
			if ( '' === $label ) {
				$errors[] = 'Every detail needs a label (the value "' . $value . '" has none).';
				continue;
			}
			if ( '' === $value ) {
				$errors[] = 'The detail "' . $label . '" has no value. Fill it in or remove the row.';
				continue;
			}
			$key = strtolower( $label );
			if ( isset( $seen[ $key ] ) ) {
				$errors[] = 'The label "' . $label . '" is used twice.';
				continue;
			}
			if ( isset( $fixed[ $key ] ) ) {
				$errors[] = 'The label "' . $label . '" is already set in WooCommerce for this product.';
				continue;
			}
			$seen[ $key ] = true;
			$tag          = ! empty( $row['tag'] );
			if ( $tag && $ticks >= self::MAX_TICKS ) {
				$tag     = false;
				$notes[] = 'Only ' . self::MAX_TICKS . ' details can show as labels, so "' . $label . '" will not.';
			}
			if ( $tag ) {
				$ticks++;
			}
			$rows[] = array( 'label' => $label, 'value' => $value, 'tag' => $tag );
		}
		if ( count( $rows ) > self::MAX_ROWS ) {
			$errors[] = 'A product can have ' . self::MAX_ROWS . ' details at most.';
		}
		$f['details'] = $rows;

		$f['summary'] = TWD_SK_Shop_Text::clean_summary( isset( $in['summary'] ) && is_string( $in['summary'] ) ? $in['summary'] : '' );
		if ( TWD_SK_Shop_Text::length( $f['summary'] ) > TWD_SK_Shop_Text::MAX_SUMMARY ) {
			$errors[] = 'The short summary is too long (' . TWD_SK_Shop_Text::MAX_SUMMARY . ' letters at most).';
		}

		$sections = array();
		foreach ( isset( $in['sections'] ) && is_array( $in['sections'] ) ? $in['sections'] : array() as $s ) {
			if ( ! is_array( $s ) ) {
				continue;
			}
			$heading = TWD_SK_Shop_Text::plain( isset( $s['heading'] ) ? $s['heading'] : '', TWD_SK_Shop_Text::MAX_HEADING );
			$html    = TWD_SK_Shop_Text::clean_section( isset( $s['html'] ) && is_string( $s['html'] ) ? $s['html'] : '' );
			if ( '' === $html ) {
				continue;
			}
			if ( '' === $heading ) {
				$heading = 'More about this piece';
			}
			if ( TWD_SK_Shop_Text::length( $html ) > TWD_SK_Shop_Text::MAX_SECTION ) {
				$errors[] = 'The section "' . $heading . '" is too long (' . TWD_SK_Shop_Text::MAX_SECTION . ' letters at most).';
			}
			$sections[] = array( 'heading' => $heading, 'html' => $html );
		}
		if ( count( $sections ) > TWD_SK_Shop_Text::MAX_SECTIONS ) {
			$errors[] = 'A product can have ' . TWD_SK_Shop_Text::MAX_SECTIONS . ' sections at most.';
		}
		$f['sections'] = $sections;

		// Photos: pictures in the media library only; the main photo is never repeated in the gallery.
		// A photo the product already has is kept as it is, even if the media library cannot vouch for it.
		$had = array_merge( array( (int) $product->get_image_id() ), array_map( 'intval', (array) $product->get_gallery_image_ids() ) );
		$pick = function ( $value ) use ( $had ) {
			$id = ( is_int( $value ) || ( is_string( $value ) && ctype_digit( $value ) ) ) ? (int) $value : 0;
			return ( $id > 0 && in_array( $id, $had, true ) ) ? $id : self::picture_id( $value );
		};
		$f['image'] = $pick( isset( $in['image'] ) ? $in['image'] : 0 );
		if ( ! empty( $in['image'] ) && 0 === $f['image'] ) {
			$errors[] = 'The main photo must be a picture in the media library.';
		}
		$gallery = array();
		foreach ( isset( $in['gallery'] ) && is_array( $in['gallery'] ) ? $in['gallery'] : array() as $g ) {
			$g = $pick( $g );
			if ( $g > 0 && $g !== $f['image'] && ! in_array( $g, $gallery, true ) ) {
				$gallery[] = $g;
			}
		}
		if ( count( $gallery ) > self::MAX_PHOTOS ) {
			$errors[] = 'A product can have ' . self::MAX_PHOTOS . ' gallery photos at most.';
		}
		$f['gallery'] = $gallery;

		if ( $errors ) {
			return new WP_Error( 'twd_sk_shop_invalid', implode( ' ', $errors ), array( 'status' => 422, 'errors' => $errors ) );
		}
		return array( 'fields' => $f, 'notes' => array_values( array_unique( $notes ) ) );
	}

	// -- Writing -------------------------------------------------------------------

	/**
	 * Write the fields that differ from $old onto the product and save it once. Fields that did not
	 * change are not touched, so saving an unchanged tab never rewrites anything.
	 *
	 * @return true|WP_Error
	 */
	public static function write( $product, $old, $new ) {
		$id = (int) $product->get_id();
		try {
			if ( $new['title'] !== $old['title'] ) {
				$product->set_name( $new['title'] );
			}
			if ( $new['price'] !== $old['price'] ) {
				$product->set_regular_price( $new['price'] );
			}
			if ( $new['sale'] !== $old['sale'] ) {
				$product->set_sale_price( $new['sale'] );
			}
			if ( $new['sku'] !== $old['sku'] ) {
				$product->set_sku( $new['sku'] );
			}
			if ( $new['cats'] !== $old['cats'] ) {
				$known = array();
				foreach ( self::categories() as $c ) {
					$known[ $c['id'] ] = true;
				}
				$product->set_category_ids( array_values( array_filter( $new['cats'], function ( $c ) use ( $known ) {
					return isset( $known[ (int) $c ] );
				} ) ) );
			}
			if ( $new['sold'] !== $old['sold'] ) {
				// A one-off piece: stock 1 while for sale, 0 once sold, so a sale marks it sold by itself.
				$product->set_manage_stock( true );
				$product->set_sold_individually( true );
				$product->set_stock_quantity( $new['sold'] ? 0 : 1 );
				$product->set_stock_status( $new['sold'] ? 'outofstock' : 'instock' );
			}
			if ( self::rows_only( $new['details'] ) !== self::rows_only( $old['details'] ) ) {
				$product->set_attributes( self::attributes( $product, $new['details'] ) );
			}
			if ( $new['summary'] !== $old['summary'] ) {
				$product->set_short_description( $new['summary'] );
			}
			if ( $new['sections'] !== $old['sections'] ) {
				$product->set_description( TWD_SK_Shop_Text::join( $new['sections'] ) );
			}
			// A version kept before photos were part of the window has no photo fields: photos are then left alone.
			if ( array_key_exists( 'image', $new ) && ( ! isset( $old['image'] ) || $new['image'] !== $old['image'] ) ) {
				$product->set_image_id( (int) $new['image'] );
			}
			if ( array_key_exists( 'gallery', $new ) && ( ! isset( $old['gallery'] ) || $new['gallery'] !== $old['gallery'] ) ) {
				$product->set_gallery_image_ids( array_map( 'intval', (array) $new['gallery'] ) );
			}
			$product->save();
		} catch ( Exception $e ) {
			$words = 'WooCommerce refused the change: ' . TWD_SK_Shop_Text::plain( $e->getMessage(), 200 );
			if ( $new['sku'] !== $old['sku'] ) {
				$words = 'That stock number could not be used (it may belong to another product). Choose another, or leave it empty.';
			}
			return new WP_Error( 'twd_sk_shop_refused', $words, array( 'status' => 422 ) );
		}
		$ticks = array();
		foreach ( $new['details'] as $row ) {
			if ( ! empty( $row['tag'] ) ) {
				$ticks[] = $row['label'];
			}
		}
		$had = get_post_meta( $id, self::LABELS_META, true );
		if ( ( is_array( $had ) ? $had : array() ) !== $ticks ) {
			update_post_meta( $id, self::LABELS_META, wp_slash( $ticks ) );
		}
		return true;
	}

	private static function rows_only( $details ) {
		$out = array();
		foreach ( (array) $details as $r ) {
			$out[] = array( $r['label'], $r['value'] );
		}
		return $out;
	}

	/** The product's attributes with the local ones replaced by the rows; global ones kept as they were, first. */
	private static function attributes( $product, $rows ) {
		$out = array();
		$pos = 0;
		foreach ( (array) $product->get_attributes() as $key => $att ) {
			if ( is_object( $att ) && $att->is_taxonomy() ) {
				$out[ $key ] = $att;
				$pos         = max( $pos, (int) $att->get_position() + 1 );
			}
		}
		foreach ( $rows as $row ) {
			$att = new WC_Product_Attribute();
			$att->set_id( 0 );
			$att->set_name( $row['label'] );
			$att->set_options( array( $row['value'] ) );
			$att->set_position( $pos++ );
			$att->set_visible( true );
			$att->set_variation( false );
			$out[ sanitize_title( $row['label'] ) ] = $att;
		}
		return $out;
	}

	/** A new draft product for the window to open on. Returns the id or a WP_Error. */
	public static function create( $category = 0 ) {
		if ( ! class_exists( 'WC_Product_Simple' ) ) {
			return new WP_Error( 'twd_sk_shop_no_wc', 'WooCommerce is not available.', array( 'status' => 500 ) );
		}
		try {
			$p = new WC_Product_Simple();
			$p->set_name( self::NEW_TITLE );
			$p->set_status( 'draft' );
			$p->set_manage_stock( true );
			$p->set_stock_quantity( 1 );
			$p->set_stock_status( 'instock' );
			$p->set_sold_individually( true );
			$category = (int) $category;
			if ( $category > 0 ) {
				foreach ( self::categories() as $c ) {
					if ( $c['id'] === $category ) {
						$p->set_category_ids( array( $category ) );
					}
				}
			}
			$id = (int) $p->save();
		} catch ( Exception $e ) {
			return new WP_Error( 'twd_sk_shop_refused', 'WooCommerce could not make the product: ' . TWD_SK_Shop_Text::plain( $e->getMessage(), 200 ), array( 'status' => 500 ) );
		}
		if ( $id <= 0 ) {
			return new WP_Error( 'twd_sk_shop_refused', 'WooCommerce could not make the product.', array( 'status' => 500 ) );
		}
		return $id;
	}

	/** Publish a draft. Refused while anything still says TO CONFIRM or [PLACEHOLDER. */
	public static function publish( $id ) {
		$product = self::get( $id );
		if ( ! $product ) {
			return new WP_Error( 'twd_sk_shop_missing', 'That product was not found.', array( 'status' => 404 ) );
		}
		$marks = TWD_SK_Shop_Text::markers( self::fields( $product ) );
		if ( $marks ) {
			return new WP_Error( 'twd_sk_shop_unfinished', 'Not published yet: there is still something to fill in, in ' . implode( ', ', $marks ) . '.', array( 'status' => 409, 'where' => $marks ) );
		}
		try {
			$product->set_status( 'publish' );
			$product->save();
		} catch ( Exception $e ) {
			return new WP_Error( 'twd_sk_shop_refused', 'WooCommerce could not publish it: ' . TWD_SK_Shop_Text::plain( $e->getMessage(), 200 ), array( 'status' => 422 ) );
		}
		return true;
	}
}
