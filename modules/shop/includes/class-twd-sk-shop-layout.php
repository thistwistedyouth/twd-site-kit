<?php
/**
 * TWD_SK_Shop_Layout: the tidier product page, switched on separately (option twd_sk_shop_layout).
 *
 * Built on WooCommerce's own hooks for the classic product page, never by copying its template
 * files, so a WooCommerce update cannot break it and plugins that hook into the page keep working.
 *
 * Top: the gallery (WooCommerce's), then the period line, title, small labels, price, short summary,
 * Add to basket and Ask about this piece. Below: the story (the description's first section) beside
 * a details card (the product's attributes, category and stock number), then the other sections
 * and Delivery and returns as sections that open (plain details and summary, no script).
 *
 * It stands aside on a product where an Elementor single template applies. Visitors load one small
 * stylesheet and one font on shop pages only. Colours come from the Shop colour setting.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class TWD_SK_Shop_Layout {

	const OPTION         = 'twd_sk_shop_layout';
	const COLOUR         = 'twd_sk_shop_colour';
	const ENQUIRY        = 'twd_sk_shop_enquiry_page';
	const DELIVERY       = 'twd_sk_shop_delivery';
	const HANDLE         = 'twd-site-kit-shop';
	const DEFAULT_COLOUR = '#1f3a33';

	/** Detail labels that give the period line above the title (first match wins). */
	public static function date_labels() {
		return array( 'period', 'date', 'age', 'era' );
	}

	public static function on() {
		$v = get_option( self::OPTION, '' );
		return 'yes' === $v || '1' === $v;
	}

	public static function init() {
		if ( ! self::on() ) {
			return;
		}
		add_filter( 'body_class', array( __CLASS__, 'body_class' ) );
		add_action( 'wp_enqueue_scripts', array( __CLASS__, 'enqueue' ), 25 );
		add_action( 'wp', array( __CLASS__, 'arrange' ) );
		add_filter( 'woocommerce_get_availability_text', array( __CLASS__, 'availability' ), 10, 2 );
		add_action( 'woocommerce_before_shop_loop_item_title', array( __CLASS__, 'loop_badge' ), 9 );
		add_filter( 'woocommerce_product_related_products_heading', array( __CLASS__, 'related_heading' ) );
	}

	// -- Where it applies --------------------------------------------------------------

	private static function is_product_page() {
		return function_exists( 'is_product' ) && is_product();
	}

	private static function is_shop_page() {
		if ( self::is_product_page() ) {
			return ! self::elementor_template();
		}
		return ( function_exists( 'is_shop' ) && is_shop() ) || ( function_exists( 'is_product_taxonomy' ) && is_product_taxonomy() );
	}

	/** True when an Elementor Pro single template draws this product: then the layout stands aside. */
	private static function elementor_template() {
		if ( ! function_exists( 'elementor_location_exits' ) ) {
			return false;
		}
		try {
			return (bool) elementor_location_exits( 'single', true );
		} catch ( Throwable $e ) {
			return false;
		}
	}

	public static function body_class( $classes ) {
		if ( is_array( $classes ) && self::is_shop_page() ) {
			$classes[] = 'twd-sk-shop-layout';
		}
		return $classes;
	}

	/** The Shop colour as #rrggbb, or the default. */
	public static function colour() {
		$v = (string) get_option( self::COLOUR, '' );
		return preg_match( '/^#[0-9a-fA-F]{6}$/', $v ) ? strtolower( $v ) : self::DEFAULT_COLOUR;
	}

	public static function enqueue() {
		if ( ! self::is_shop_page() ) {
			return;
		}
		$ver = defined( 'TWD_SK_VERSION' ) ? TWD_SK_VERSION : false;
		wp_enqueue_style( self::HANDLE, TWD_SK_URL . 'modules/shop/assets/twd-site-kit-shop.css', array(), $ver );
		wp_add_inline_style( self::HANDLE, self::inline_css( TWD_SK_URL . 'assets/fonts/' ) );
	}

	/** The serif (bundled with the kit, never fetched from outside) and the Shop colour. */
	public static function inline_css( $fonts ) {
		$css = '';
		foreach ( array( 'normal', 'italic' ) as $style ) {
			$css .= "@font-face{font-family:'twd-sk-shop-serif';src:url('" . esc_url( $fonts . 'cormorant-garamond-latin-wght-' . $style . '.woff2' ) . "') format('woff2');font-weight:300 700;font-style:" . $style . ';font-display:swap}';
		}
		return $css . 'body.twd-sk-shop-layout{--twd-sk-shop-ink:' . self::colour() . '}';
	}

	public static function arrange() {
		if ( ! self::is_product_page() || self::elementor_template() ) {
			return;
		}
		remove_action( 'woocommerce_single_product_summary', 'woocommerce_template_single_meta', 40 );
		remove_action( 'woocommerce_single_product_summary', 'woocommerce_template_single_excerpt', 20 );
		add_action( 'woocommerce_single_product_summary', array( __CLASS__, 'print_summary' ), 20 );
		remove_action( 'woocommerce_after_single_product_summary', 'woocommerce_output_product_data_tabs', 10 );
		add_action( 'woocommerce_single_product_summary', array( __CLASS__, 'print_era' ), 4 );
		add_action( 'woocommerce_single_product_summary', array( __CLASS__, 'print_labels' ), 7 );
		add_action( 'woocommerce_single_product_summary', array( __CLASS__, 'print_ask' ), 35 );
		add_action( 'woocommerce_after_single_product_summary', array( __CLASS__, 'print_piece' ), 10 );
	}

	// -- Wording ---------------------------------------------------------------------------

	public static function availability( $text, $product = null ) {
		if ( is_object( $product ) && 'outofstock' === $product->get_stock_status() ) {
			return 'Sold';
		}
		return $text;
	}

	public static function related_heading( $heading ) {
		return 'More from the collection';
	}

	public static function loop_badge() {
		global $product;
		if ( is_object( $product ) && 'outofstock' === $product->get_stock_status() ) {
			echo '<span class="twd-sk-shop-sold">Sold</span>';
		}
	}

	// -- The product's facts ------------------------------------------------------------------

	private static function current() {
		global $product;
		if ( is_object( $product ) ) {
			return $product;
		}
		return function_exists( 'wc_get_product' ) ? wc_get_product( get_the_ID() ) : null;
	}

	/**
	 * The product's visible attributes as rows, in WooCommerce's order: array of { key, label, value }.
	 * Local ones carry their own words; global ones their term names.
	 */
	public static function rows( $product ) {
		$rows = array();
		foreach ( (array) $product->get_attributes() as $att ) {
			if ( ! is_object( $att ) || ( method_exists( $att, 'get_visible' ) && ! $att->get_visible() ) ) {
				continue;
			}
			$name = (string) $att->get_name();
			if ( $att->is_taxonomy() ) {
				$label  = function_exists( 'wc_attribute_label' ) ? wc_attribute_label( $name ) : $name;
				$values = function_exists( 'wc_get_product_terms' ) ? wc_get_product_terms( $product->get_id(), $name, array( 'fields' => 'names' ) ) : array();
			} else {
				$label  = $name;
				$values = (array) $att->get_options();
			}
			$value = trim( implode( ', ', array_map( 'strval', (array) $values ) ) );
			if ( '' !== $value ) {
				$rows[] = array( 'key' => strtolower( trim( (string) $label ) ), 'label' => (string) $label, 'value' => $value );
			}
		}
		return $rows;
	}

	/** The row that says when the piece was made (Period, Date, Age or Era), or null. */
	public static function date_row( $rows ) {
		foreach ( self::date_labels() as $want ) {
			foreach ( $rows as $r ) {
				if ( $want === $r['key'] ) {
					return $r;
				}
			}
		}
		return null;
	}

	/** Small labels near the title: the ticked details (not the period line's), then Reduced and Sold. */
	public static function labels( $product ) {
		$ticks = get_post_meta( $product->get_id(), TWD_SK_Shop_Product::LABELS_META, true );
		$ticks = is_array( $ticks ) ? $ticks : array();
		$rows  = self::rows( $product );
		$date  = self::date_row( $rows );
		$out   = array();
		foreach ( $rows as $r ) {
			if ( in_array( $r['label'], $ticks, true ) && ( ! $date || $r['key'] !== $date['key'] ) ) {
				$out[] = array( 'text' => $r['value'], 'kind' => '' );
			}
		}
		if ( method_exists( $product, 'is_on_sale' ) && $product->is_on_sale() ) {
			$out[] = array( 'text' => 'Reduced', 'kind' => 'reduced' );
		}
		if ( 'outofstock' === $product->get_stock_status() ) {
			$out[] = array( 'text' => 'Sold', 'kind' => 'sold' );
		}
		return $out;
	}

	// -- Printing -------------------------------------------------------------------------------

	public static function print_era() {
		$product = self::current();
		if ( ! $product ) {
			return;
		}
		$date = self::date_row( self::rows( $product ) );
		if ( $date ) {
			echo '<p class="twd-sk-shop-era">' . esc_html( $date['value'] ) . '</p>';
		}
	}

	public static function print_labels() {
		$product = self::current();
		if ( ! $product ) {
			return;
		}
		$labels = self::labels( $product );
		if ( ! $labels ) {
			return;
		}
		echo '<ul class="twd-sk-shop-labels">';
		foreach ( $labels as $l ) {
			echo '<li' . ( $l['kind'] ? ' class="twd-sk-shop-labels__' . esc_attr( $l['kind'] ) . '"' : '' ) . '>' . esc_html( $l['text'] ) . '</li>';
		}
		echo '</ul>';
	}

	public static function print_ask() {
		$page = (int) get_option( self::ENQUIRY, 0 );
		if ( $page <= 0 || 'publish' !== get_post_status( $page ) ) {
			return;
		}
		$product = self::current();
		$sold    = $product && 'outofstock' === $product->get_stock_status();
		echo '<p class="twd-sk-shop-ask"><a class="twd-sk-shop-ask__link" href="' . esc_url( get_permalink( $page ) ) . '">' . esc_html( $sold ? 'Ask about similar pieces' : 'Ask about this piece' ) . '</a></p>';
	}

	/** A section's words as WordPress formats post text: paragraphs, shortcodes, responsive pictures. */
	private static function format( $html ) {
		if ( function_exists( 'wptexturize' ) ) {
			$html = wptexturize( $html );
		}
		if ( function_exists( 'wpautop' ) ) {
			$html = wpautop( $html );
		}
		if ( function_exists( 'shortcode_unautop' ) ) {
			$html = shortcode_unautop( $html );
		}
		if ( function_exists( 'do_shortcode' ) ) {
			$html = do_shortcode( $html );
		}
		if ( function_exists( 'wp_filter_content_tags' ) ) {
			$html = wp_filter_content_tags( $html );
		}
		return $html;
	}

	/** A short summary longer than this (in letters) shows as a lead near the price, in full lower down. */
	const LONG_SUMMARY = 450;
	const LEAD_LENGTH  = 320;

	public static function summary_is_long( $product ) {
		return TWD_SK_Shop_Text::length( (string) $product->get_short_description() ) > self::LONG_SUMMARY;
	}

	/**
	 * The lead for a long short summary: its first real paragraph (a short line in capitals, used as a
	 * heading, is skipped), cut at a word near LEAD_LENGTH letters.
	 */
	public static function lead( $html ) {
		$html = function_exists( 'wpautop' ) ? wpautop( (string) $html ) : (string) $html;
		preg_match_all( '#<p\b[^>]*>(.*?)</p>#is', $html, $m );
		$paras = $m[1] ? $m[1] : array( $html );
		foreach ( $paras as $p ) {
			$text = trim( preg_replace( '/\s+/u', ' ', html_entity_decode( wp_strip_all_tags( $p ), ENT_QUOTES, 'UTF-8' ) ) );
			$len  = function_exists( 'mb_strlen' ) ? mb_strlen( $text ) : strlen( $text );
			if ( '' === $text || ( $len < 70 && ( function_exists( 'mb_strtoupper' ) ? mb_strtoupper( $text ) : strtoupper( $text ) ) === $text ) ) {
				continue;
			}
			if ( $len > self::LEAD_LENGTH ) {
				$cut  = function_exists( 'mb_substr' ) ? mb_substr( $text, 0, self::LEAD_LENGTH ) : substr( $text, 0, self::LEAD_LENGTH );
				$text = preg_replace( '/\s+\S*$/u', '', $cut ) . '...';
			}
			return $text;
		}
		return '';
	}

	/** In place of WooCommerce's short description: in full when short, as a lead when long. */
	public static function print_summary() {
		$product = self::current();
		if ( ! $product || '' === trim( (string) $product->get_short_description() ) ) {
			return;
		}
		if ( ! self::summary_is_long( $product ) ) {
			echo '<div class="woocommerce-product-details__short-description">' . self::format( (string) $product->get_short_description() ) . '</div>'; // phpcs:ignore WordPress.Security.EscapeOutput -- the product's own text, formatted as WooCommerce does.
			return;
		}
		$lead = self::lead( (string) $product->get_short_description() );
		echo '<div class="woocommerce-product-details__short-description twd-sk-shop-lead">'
			. ( '' !== $lead ? '<p>' . esc_html( $lead ) . '</p>' : '' )
			. '<p class="twd-sk-shop-lead__more"><a href="#twd-sk-shop-piece">Read the full description</a></p></div>';
	}

	/** The story, the details card and the sections that open. */
	public static function piece_html( $product ) {
		$sections = TWD_SK_Shop_Text::split_raw( (string) $product->get_description() );
		$first    = $sections ? array_shift( $sections ) : null;
		// A long short summary is shown in full here, first, as part of the story.
		if ( self::summary_is_long( $product ) ) {
			$first = array(
				'heading' => TWD_SK_Shop_Text::FIRST,
				'html'    => (string) $product->get_short_description() . ( $first ? "\n\n" . ( TWD_SK_Shop_Text::FIRST === $first['heading'] ? '' : '<h3>' . esc_html( $first['heading'] ) . '</h3>' ) . $first['html'] : '' ),
			);
		}
		$rows     = self::rows( $product );
		$sku      = (string) $product->get_sku();
		$cats     = function_exists( 'wc_get_product_category_list' ) ? (string) wc_get_product_category_list( $product->get_id(), ', ' ) : '';
		$has_card = $rows || '' !== $cats || '' !== $sku;

		$out = '<div id="twd-sk-shop-piece" class="twd-sk-shop-piece' . ( $has_card ? '' : ' twd-sk-shop-piece--wide' ) . ( $first ? '' : ' twd-sk-shop-piece--nostory' ) . '">';
		if ( $first ) {
			$out .= '<div class="twd-sk-shop-piece__story"><h2 class="twd-sk-shop-piece__title">' . esc_html( $first['heading'] ) . '</h2>'
				. '<div class="twd-sk-shop-piece__text">' . self::format( $first['html'] ) . '</div></div>';
		}
		if ( $has_card ) {
			$out .= '<aside class="twd-sk-shop-piece__aside"><div class="twd-sk-shop-card"><div class="twd-sk-shop-card__in">'
				. '<div class="twd-sk-shop-card__head"><h2 class="twd-sk-shop-card__title">The details</h2>'
				. ( '' !== $sku ? '<span class="twd-sk-shop-card__no">No. ' . esc_html( $sku ) . '</span>' : '' ) . '</div><dl class="twd-sk-shop-card__rows">';
			foreach ( $rows as $r ) {
				$out .= '<div class="twd-sk-shop-card__row"><dt>' . esc_html( $r['label'] ) . '</dt><dd>' . esc_html( $r['value'] ) . '</dd></div>';
			}
			if ( '' !== $cats ) {
				$out .= '<div class="twd-sk-shop-card__row"><dt>Category</dt><dd>' . wp_kses_post( $cats ) . '</dd></div>';
			}
			$out .= '</dl></div></div></aside>';
		}
		$out .= '</div>';

		$delivery = trim( (string) get_option( self::DELIVERY, '' ) );
		if ( $sections || '' !== $delivery ) {
			$out .= '<div class="twd-sk-shop-folds">';
			foreach ( $sections as $i => $s ) {
				$out .= '<details class="twd-sk-shop-fold"' . ( 0 === $i ? ' open' : '' ) . '><summary>' . esc_html( $s['heading'] ) . '</summary>'
					. '<div class="twd-sk-shop-fold__body">' . self::format( $s['html'] ) . '</div></details>';
			}
			if ( '' !== $delivery ) {
				$out .= '<details class="twd-sk-shop-fold"><summary>Delivery and returns</summary><div class="twd-sk-shop-fold__body">'
					. wp_kses_post( function_exists( 'wpautop' ) ? wpautop( $delivery ) : $delivery ) . '</div></details>';
			}
			$out .= '</div>';
		}
		return $out;
	}

	public static function print_piece() {
		$product = self::current();
		if ( $product ) {
			echo self::piece_html( $product ); // phpcs:ignore WordPress.Security.EscapeOutput -- built above: every value escaped, the description formatted as WooCommerce does.
		}
	}
}
