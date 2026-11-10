<?php
/**
 * TWD_SK_Shop_Grid (0.21.0): a filter bar above product grids, and a grid that updates in place.
 *
 * Where: the shop page and product category pages (WooCommerce's own loop, with the bar printed on
 * its hooks), and [twd_shop_grid limit="12" columns="4"] for any other page (on a Site Kit page, in
 * an Embed section, shortcode kind). The bar: category buttons (All, then each category that has
 * products a visitor can see, with counts), a sort (Latest, Price low to high, Price high to low)
 * and a search box that searches within the category shown.
 *
 * Without a script every control is a plain link or GET form, and the page reloads at
 * #twd-sk-shop-grid. With the script the same address is fetched and only the bar, the result
 * count, the grid and the pager are swapped, taken from the fetched page's own markup (never
 * innerHTML). The grid keeps WooCommerce's ul.products and li.product markup, so a theme's or the
 * site's own grid CSS still applies.
 *
 * The address uses the module's own parameters (shop_cat, shop_sort, shop_q, shop_page), never
 * WordPress's: ?orderby= or ?s= on a static front page turns it into the blog or search page.
 *
 * While the module is on, Popularity and Average rating leave WooCommerce's sort options site-wide,
 * and the default sort becomes Latest (a price sort chosen as the default is kept).
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class TWD_SK_Shop_Grid {

	const SHORTCODE    = 'twd_shop_grid';
	const HANDLE       = 'twd-site-kit-shop-grid';
	const ANCHOR       = 'twd-sk-shop-grid';
	const Q_CAT        = 'shop_cat';
	const Q_SORT       = 'shop_sort';
	const Q_SEARCH     = 'shop_q';
	const Q_PAGE       = 'shop_page';
	const MAX_SEARCH   = 80;
	const LATEST       = 'date';
	const MAX_LIMIT    = 48;
	const MAX_COLUMNS  = 6;
	const CACHE_PREFIX = 'twd_sk_shop_grid_';

	/** The bar is printed once per page; the archive's results wrapper is open between two hooks. */
	private static $printed = false;
	private static $open    = false;

	public static function init() {
		add_filter( 'woocommerce_catalog_orderby', array( __CLASS__, 'orderby_options' ), 20 );
		add_filter( 'woocommerce_default_catalog_orderby_options', array( __CLASS__, 'orderby_options' ), 20 );
		add_filter( 'woocommerce_default_catalog_orderby', array( __CLASS__, 'default_orderby' ), 20 );
		add_shortcode( self::SHORTCODE, array( __CLASS__, 'shortcode' ) );
		add_action( 'wp', array( __CLASS__, 'arrange' ) );
		add_action( 'woocommerce_product_query', array( __CLASS__, 'archive_search' ), 20, 1 );
		add_filter( 'woocommerce_pagination_args', array( __CLASS__, 'pagination_args' ) );
		add_action( 'wp_enqueue_scripts', array( __CLASS__, 'maybe_enqueue' ), 27 );
	}

	/** For tests: forget that the bar was printed. */
	public static function reset() {
		self::$printed = false;
		self::$open    = false;
	}

	// -- Sorting ------------------------------------------------------------------------------------

	/** The sorts offered, in WooCommerce's own values. */
	public static function sorts() {
		return array(
			'date'       => 'Latest',
			'price'      => 'Price low to high',
			'price-desc' => 'Price high to low',
		);
	}

	/** WooCommerce's sort lists (the shop's and the setting's) without Popularity and Average rating. */
	public static function orderby_options( $options ) {
		if ( ! TWD_SK_Shop_Gate::on() || ! is_array( $options ) ) {
			return $options;
		}
		unset( $options['popularity'], $options['rating'] );
		return $options;
	}

	/** The shop's default sort: the stored one when it is one of ours, else Latest. */
	public static function default_sort() {
		$v = (string) get_option( 'woocommerce_default_catalog_orderby', '' );
		return isset( self::sorts()[ $v ] ) ? $v : self::LATEST;
	}

	/**
	 * WooCommerce's default sort. On the shop and category pages the bar's choice (shop_sort) is
	 * given here, since WooCommerce uses the default whenever ?orderby= is absent.
	 */
	public static function default_orderby( $value ) {
		if ( ! TWD_SK_Shop_Gate::on() ) {
			return $value;
		}
		if ( self::archive_context() ) {
			$req = self::request();
			if ( '' !== $req['sort'] ) {
				return $req['sort'];
			}
		}
		return isset( self::sorts()[ (string) $value ] ) ? $value : self::LATEST;
	}

	/** Query arguments for a sort, used when WooCommerce's own ordering helper is not there. */
	public static function sort_args( $sort ) {
		if ( 'price' === $sort || 'price-desc' === $sort ) {
			$dir = 'price' === $sort ? 'ASC' : 'DESC';
			return array( 'meta_key' => '_price', 'orderby' => array( 'meta_value_num' => $dir, 'ID' => $dir ) ); // phpcs:ignore WordPress.DB.SlowDBQuery
		}
		return array( 'orderby' => 'date ID', 'order' => 'DESC' );
	}

	// -- The request ----------------------------------------------------------------------------------

	/** The bar's choices from the address: cat (a slug), sort ('' for the default), q, page. */
	public static function request( $get = null ) {
		$get = null === $get ? $_GET : $get; // phpcs:ignore WordPress.Security.NonceVerification -- a visitor's filter, read only.
		$str = function ( $k ) use ( $get ) {
			return isset( $get[ $k ] ) && is_string( $get[ $k ] ) ? (string) wp_unslash( $get[ $k ] ) : '';
		};
		$sort = $str( self::Q_SORT );
		$q    = trim( preg_replace( '/\s+/u', ' ', sanitize_text_field( $str( self::Q_SEARCH ) ) ) );
		if ( ( function_exists( 'mb_strlen' ) ? mb_strlen( $q, 'UTF-8' ) : strlen( $q ) ) > self::MAX_SEARCH ) {
			$q = trim( function_exists( 'mb_substr' ) ? mb_substr( $q, 0, self::MAX_SEARCH, 'UTF-8' ) : substr( $q, 0, self::MAX_SEARCH ) );
		}
		return array(
			'cat'  => sanitize_title( $str( self::Q_CAT ) ),
			'sort' => isset( self::sorts()[ $sort ] ) ? $sort : '',
			'q'    => $q,
			'page' => max( 1, absint( $str( self::Q_PAGE ) ) ),
		);
	}

	// -- Categories -------------------------------------------------------------------------------------

	/** A name shown in title case when it is stored all in lower case ("earrings" shows "Earrings"). */
	public static function label( $name ) {
		$name  = trim( html_entity_decode( (string) $name, ENT_QUOTES, 'UTF-8' ) );
		$lower = function_exists( 'mb_strtolower' ) ? mb_strtolower( $name, 'UTF-8' ) : strtolower( $name );
		if ( $lower !== $name ) {
			return $name;
		}
		$small = array( 'a', 'an', 'and', 'at', 'by', 'for', 'in', 'of', 'on', 'or', 'the', 'to', 'with' );
		$words = explode( ' ', $name );
		foreach ( $words as $i => $w ) {
			if ( '' === $w || ( $i > 0 && in_array( $w, $small, true ) ) ) {
				continue;
			}
			$first       = function_exists( 'mb_substr' ) ? mb_substr( $w, 0, 1, 'UTF-8' ) : substr( $w, 0, 1 );
			$rest        = function_exists( 'mb_substr' ) ? mb_substr( $w, 1, null, 'UTF-8' ) : substr( $w, 1 );
			$words[ $i ] = ( function_exists( 'mb_strtoupper' ) ? mb_strtoupper( $first, 'UTF-8' ) : strtoupper( $first ) ) . $rest;
		}
		return implode( ' ', $words );
	}

	/** Never a button: Uncategorised (WooCommerce's default category) and a category called Sold. */
	public static function hidden_term( $term ) {
		$slug = isset( $term->slug ) ? (string) $term->slug : '';
		$name = strtolower( trim( html_entity_decode( (string) $term->name, ENT_QUOTES, 'UTF-8' ) ) );
		if ( in_array( $slug, array( 'uncategorized', 'uncategorised', 'sold' ), true ) || in_array( $name, array( 'uncategorized', 'uncategorised', 'sold' ), true ) ) {
			return true;
		}
		$default = (int) get_option( 'default_product_cat', 0 );
		return $default > 0 && (int) $term->term_id === $default;
	}

	/** The tax query that keeps products a visitor cannot see out: hidden from the shop, and sold when the shop hides them. */
	public static function visibility() {
		$not = array( 'exclude-from-catalog' );
		if ( 'yes' === get_option( 'woocommerce_hide_out_of_stock_items', 'no' ) ) {
			$not[] = 'outofstock';
		}
		return array(
			array(
				'taxonomy' => 'product_visibility',
				'field'    => 'slug',
				'terms'    => $not,
				'operator' => 'NOT IN',
			),
		);
	}

	/** The numbers of the products a visitor can see. */
	public static function visible_ids() {
		$q = new WP_Query( array(
			'post_type'              => 'product',
			'post_status'            => 'publish',
			'posts_per_page'         => -1,
			'fields'                 => 'ids',
			'no_found_rows'          => true,
			'ignore_sticky_posts'    => true,
			'update_post_meta_cache' => false,
			'update_post_term_cache' => false,
			'tax_query'              => self::visibility(), // phpcs:ignore WordPress.DB.SlowDBQuery
		) );
		return array_map( 'intval', (array) $q->posts );
	}

	/** term id => how many visible products are in it (a product in a sub-category counts for its parents too). */
	public static function counts() {
		$key = '';
		if ( class_exists( 'WC_Cache_Helper' ) && method_exists( 'WC_Cache_Helper', 'get_transient_version' ) ) {
			$key    = self::CACHE_PREFIX . md5( WC_Cache_Helper::get_transient_version( 'product' ) . '|' . get_option( 'woocommerce_hide_out_of_stock_items', 'no' ) );
			$cached = get_transient( $key );
			if ( is_array( $cached ) ) {
				return $cached;
			}
		}
		$counts = array();
		$ids    = self::visible_ids();
		if ( $ids ) {
			$terms = wp_get_object_terms( $ids, 'product_cat', array( 'fields' => 'all_with_object_id' ) );
			$per   = array();
			foreach ( is_array( $terms ) ? $terms : array() as $t ) {
				$tids = array_merge( array( (int) $t->term_id ), array_map( 'intval', (array) get_ancestors( (int) $t->term_id, 'product_cat', 'taxonomy' ) ) );
				foreach ( $tids as $tid ) {
					$per[ (int) $t->object_id ][ $tid ] = true;
				}
			}
			foreach ( $per as $set ) {
				foreach ( array_keys( $set ) as $tid ) {
					$counts[ $tid ] = isset( $counts[ $tid ] ) ? $counts[ $tid ] + 1 : 1;
				}
			}
		}
		if ( '' !== $key ) {
			set_transient( $key, $counts, DAY_IN_SECONDS );
		}
		return $counts;
	}

	/** The category buttons after All: array of { id, slug, name, count }, in the shop's own category order. */
	public static function categories() {
		$counts = self::counts();
		$terms  = get_terms( array( 'taxonomy' => 'product_cat', 'hide_empty' => false ) );
		$out    = array();
		if ( ! is_array( $terms ) ) {
			return $out;
		}
		foreach ( $terms as $t ) {
			$id = (int) $t->term_id;
			if ( empty( $counts[ $id ] ) || self::hidden_term( $t ) ) {
				continue;
			}
			$out[] = array(
				'id'    => $id,
				'slug'  => isset( $t->slug ) ? (string) $t->slug : sanitize_title( $t->name ),
				'name'  => self::label( $t->name ),
				'count' => (int) $counts[ $id ],
			);
		}
		return $out;
	}

	// -- Where it applies -------------------------------------------------------------------------------

	/** The shop page or a product category page (not a search). */
	public static function archive_context() {
		if ( is_admin() || ( function_exists( 'is_search' ) && is_search() ) ) {
			return false;
		}
		return ( function_exists( 'is_shop' ) && is_shop() ) || ( function_exists( 'is_product_category' ) && is_product_category() );
	}

	/** Inside a product shortcode's loop ([products] and the like), where the bar never goes. */
	private static function in_shortcode_loop() {
		return function_exists( 'wc_get_loop_prop' ) && wc_get_loop_prop( 'is_shortcode' );
	}

	public static function shop_url() {
		$url = function_exists( 'wc_get_page_permalink' ) ? (string) wc_get_page_permalink( 'shop' ) : '';
		return '' !== $url ? $url : home_url( '/' );
	}

	/** A category's page on the shop, or the shop for All. */
	public static function listing_url( $slug ) {
		if ( '' !== $slug ) {
			$link = get_term_link( $slug, 'product_cat' );
			if ( is_string( $link ) && '' !== $link ) {
				return $link;
			}
		}
		return self::shop_url();
	}

	/** The page the shortcode is on. */
	public static function page_url() {
		$id = (int) get_queried_object_id();
		return $id > 0 && is_singular() ? (string) get_permalink( $id ) : home_url( '/' );
	}

	// -- Addresses -------------------------------------------------------------------------------------

	/** $url with the module's parameters replaced by $params (others kept), no fragment. */
	public static function with_params( $url, $params ) {
		$url   = (string) preg_replace( '/#.*$/s', '', (string) $url );
		$parts = explode( '?', $url, 2 );
		$query = array();
		if ( isset( $parts[1] ) ) {
			parse_str( $parts[1], $query );
		}
		foreach ( array( self::Q_CAT, self::Q_SORT, self::Q_SEARCH, self::Q_PAGE ) as $k ) {
			unset( $query[ $k ] );
		}
		$query = array_merge( $query, $params );
		return $parts[0] . ( $query ? '?' . http_build_query( $query, '', '&', PHP_QUERY_RFC3986 ) : '' );
	}

	/** The module's parameters for a state: the default sort, an empty search and page 1 are left out. */
	private static function params( $s ) {
		$p = array();
		if ( 'page' === $s['mode'] && '' !== $s['cat'] ) {
			$p[ self::Q_CAT ] = $s['cat'];
		}
		if ( '' !== $s['sort'] && self::default_sort() !== $s['sort'] ) {
			$p[ self::Q_SORT ] = $s['sort'];
		}
		if ( '' !== $s['q'] ) {
			$p[ self::Q_SEARCH ] = $s['q'];
		}
		if ( 'page' === $s['mode'] && (int) $s['page'] > 1 ) {
			$p[ self::Q_PAGE ] = (int) $s['page'];
		}
		return $p;
	}

	/** Where a state lives without a script: the category's page (shop) or this page (shortcode). */
	private static function base( $s ) {
		return 'archive' === $s['mode'] ? self::listing_url( $s['cat'] ) : $s['base'];
	}

	/** The address for a state with $over changed, landing on the bar. */
	public static function url_for( $state, $over = array() ) {
		$s = array_merge( $state, $over );
		return self::with_params( self::base( $s ), self::params( $s ) ) . '#' . self::ANCHOR;
	}

	// -- The bar ------------------------------------------------------------------------------------------

	/** The state on the shop or a category page. */
	public static function archive_state() {
		$req  = self::request();
		$slug = '';
		if ( function_exists( 'is_product_category' ) && is_product_category() ) {
			$term = get_queried_object();
			$slug = is_object( $term ) && isset( $term->slug ) ? (string) $term->slug : '';
		}
		return array(
			'mode' => 'archive',
			'base' => self::shop_url(),
			'cat'  => $slug,
			'sort' => '' !== $req['sort'] ? $req['sort'] : self::default_sort(),
			'q'    => $req['q'],
			'page' => 1,
		);
	}

	/** A GET form's opening tag and hidden fields: the base address's own query, then $keep. */
	private static function form_open( $class, $state, $keep, $role = '' ) {
		$base  = self::base( $state );
		$parts = explode( '?', (string) preg_replace( '/#.*$/s', '', $base ), 2 );
		$query = array();
		if ( isset( $parts[1] ) ) {
			parse_str( $parts[1], $query );
		}
		foreach ( array( self::Q_CAT, self::Q_SORT, self::Q_SEARCH, self::Q_PAGE ) as $k ) {
			unset( $query[ $k ] );
		}
		$out = '<form class="' . esc_attr( $class ) . '" method="get" action="' . esc_url( $parts[0] . '#' . self::ANCHOR ) . '"' . ( $role ? ' role="' . esc_attr( $role ) . '"' : '' ) . '>';
		foreach ( array_merge( $query, $keep ) as $k => $v ) {
			if ( is_scalar( $v ) && '' !== (string) $v ) {
				$out .= '<input type="hidden" name="' . esc_attr( $k ) . '" value="' . esc_attr( (string) $v ) . '">';
			}
		}
		return $out;
	}

	/** The filter bar for a state: categories, sort and search, plus the announcement line. */
	public static function bar_html( $state, $cats = null ) {
		$cats  = null === $cats ? self::categories() : $cats;
		$state = array_merge( array( 'q' => '', 'page' => 1 ), $state );
		$items = array( array( 'slug' => '', 'name' => 'All', 'count' => null ) );
		foreach ( $cats as $c ) {
			$items[] = $c;
		}
		$out = '<div id="' . self::ANCHOR . '" class="twd-sk-shop-grid"><div class="twd-sk-shop-grid__bar">'
			. '<nav class="twd-sk-shop-grid__cats" aria-label="Product categories"><ul class="twd-sk-shop-grid__list">';
		foreach ( $items as $c ) {
			$href    = self::url_for( $state, array( 'cat' => $c['slug'], 'q' => '', 'page' => 1 ) );
			$current = $c['slug'] === $state['cat'];
			$out    .= '<li><a class="twd-sk-shop-grid__cat" href="' . esc_url( $href ) . '" data-key="' . esc_attr( 'cat:' . $c['slug'] ) . '"' . ( $current ? ' aria-current="page"' : '' ) . '>'
				. esc_html( $c['name'] ) . ( null !== $c['count'] ? ' <span class="twd-sk-shop-grid__n">(' . (int) $c['count'] . ')</span>' : '' ) . '</a></li>';
		}
		$out .= '</ul></nav><div class="twd-sk-shop-grid__tools">';

		$keep = 'page' === $state['mode'] && '' !== $state['cat'] ? array( self::Q_CAT => $state['cat'] ) : array();
		$out .= self::form_open( 'twd-sk-shop-grid__sort', $state, $keep + array( self::Q_SEARCH => $state['q'] ) )
			. '<label class="twd-sk-shop-grid__sr" for="twd-sk-shop-grid-sort">Sort by</label>'
			. '<select id="twd-sk-shop-grid-sort" class="twd-sk-shop-grid__select" name="' . self::Q_SORT . '" data-key="sort">';
		foreach ( self::sorts() as $value => $label ) {
			$out .= '<option value="' . esc_attr( $value ) . '"' . ( $value === $state['sort'] ? ' selected' : '' ) . '>' . esc_html( $label ) . '</option>';
		}
		$out .= '</select><button type="submit" class="twd-sk-shop-grid__go" data-key="sort-go">Sort</button></form>';

		$sort  = self::default_sort() !== $state['sort'] ? array( self::Q_SORT => $state['sort'] ) : array();
		$out  .= self::form_open( 'twd-sk-shop-grid__search', $state, $keep + $sort, 'search' )
			. '<label class="twd-sk-shop-grid__sr" for="twd-sk-shop-grid-q">Search the shop</label>'
			. '<input type="search" id="twd-sk-shop-grid-q" class="twd-sk-shop-grid__input" name="' . self::Q_SEARCH . '" value="' . esc_attr( $state['q'] ) . '" maxlength="' . self::MAX_SEARCH . '" placeholder="Search" autocomplete="off" data-key="q">'
			. '<button type="submit" class="twd-sk-shop-grid__find" data-key="find"><span class="twd-sk-shop-grid__sr">Search</span>'
			. '<svg aria-hidden="true" focusable="false" width="16" height="16" viewBox="0 0 16 16"><circle cx="6.8" cy="6.8" r="5.3" fill="none" stroke="currentColor" stroke-width="1.5"/><path d="M10.7 10.7 15 15" stroke="currentColor" stroke-width="1.5" fill="none"/></svg></button></form>';

		return $out . '</div></div><p class="twd-sk-shop-grid__status twd-sk-shop-grid__sr" role="status" aria-live="polite"></p></div>';
	}

	// -- Results ------------------------------------------------------------------------------------------

	/** WooCommerce's wording for the count, without its dash. */
	public static function count_text( $total, $page, $per ) {
		if ( 1 === (int) $total ) {
			return 'Showing the single result';
		}
		if ( (int) $total <= (int) $per || -1 === (int) $per ) {
			return 'Showing all ' . (int) $total . ' results';
		}
		$first = ( (int) $per * ( (int) $page - 1 ) ) + 1;
		$last  = min( (int) $total, (int) $per * (int) $page );
		return 'Showing ' . $first . ' to ' . $last . ' of ' . (int) $total . ' results';
	}

	/** Page numbers to show: all up to 7, else the first, the last and two either side of this one (0 marks a gap). */
	public static function page_numbers( $page, $pages ) {
		if ( $pages <= 7 ) {
			return range( 1, max( 1, $pages ) );
		}
		$keep = array_unique( array_filter( array( 1, $page - 1, $page, $page + 1, $pages ), function ( $n ) use ( $pages ) {
			return $n >= 1 && $n <= $pages;
		} ) );
		sort( $keep );
		$out  = array();
		$prev = 0;
		foreach ( $keep as $n ) {
			if ( $prev && $n > $prev + 1 ) {
				$out[] = 0;
			}
			$out[] = $n;
			$prev  = $n;
		}
		return $out;
	}

	/** The shortcode's pager, in WooCommerce's own markup so the theme's pager style applies. */
	public static function pager_html( $page, $pages, $state ) {
		if ( $pages < 2 ) {
			return '';
		}
		$link  = function ( $n, $text, $class, $label = '' ) use ( $state ) {
			return '<a class="' . esc_attr( trim( $class . ' page-numbers' ) ) . '" href="' . esc_url( self::url_for( $state, array( 'page' => $n ) ) ) . '"' . ( $label ? ' aria-label="' . esc_attr( $label ) . '"' : '' ) . '>' . $text . '</a>';
		};
		$items = array();
		if ( $page > 1 ) {
			$items[] = $link( $page - 1, '&larr;', 'prev', 'Previous page' );
		}
		foreach ( self::page_numbers( $page, $pages ) as $n ) {
			if ( 0 === $n ) {
				$items[] = '<span class="page-numbers dots">&hellip;</span>';
			} elseif ( $n === $page ) {
				$items[] = '<span aria-current="page" class="page-numbers current">' . (int) $n . '</span>';
			} else {
				$items[] = $link( $n, (string) (int) $n, '', 'Page ' . (int) $n );
			}
		}
		if ( $page < $pages ) {
			$items[] = $link( $page + 1, '&rarr;', 'next', 'Next page' );
		}
		return '<nav class="woocommerce-pagination twd-sk-shop-grid__pager" aria-label="Product pages"><ul class="page-numbers"><li>' . implode( '</li><li>', $items ) . '</li></ul></nav>';
	}

	/** The query for the shortcode's grid (the sort is added when it runs). */
	public static function query_args( $state, $limit ) {
		$tax = self::visibility();
		if ( '' !== $state['cat'] ) {
			$tax[] = array(
				'taxonomy'         => 'product_cat',
				'field'            => 'slug',
				'terms'            => array( $state['cat'] ),
				'include_children' => true,
			);
		}
		$args = array(
			'post_type'           => 'product',
			'post_status'         => 'publish',
			'ignore_sticky_posts' => true,
			'posts_per_page'      => (int) $limit,
			'paged'               => max( 1, (int) $state['page'] ),
			'tax_query'           => array_merge( array( 'relation' => 'AND' ), $tax ), // phpcs:ignore WordPress.DB.SlowDBQuery
		);
		if ( '' !== $state['q'] ) {
			$args['s'] = $state['q'];
		}
		return $args;
	}

	/** WooCommerce's query helper, when there. */
	private static function wc_query() {
		if ( ! function_exists( 'WC' ) ) {
			return null;
		}
		$wc = WC();
		return is_object( $wc ) && isset( $wc->query ) && is_object( $wc->query ) && method_exists( $wc->query, 'get_catalog_ordering_args' ) ? $wc->query : null;
	}

	/** Run the grid's query with the sort: WooCommerce's own ordering (as its [products] does), else sort_args(). */
	public static function run_query( $args, $sort ) {
		$wcq = self::wc_query();
		if ( $wcq ) {
			$o               = $wcq->get_catalog_ordering_args( $sort );
			$args['orderby'] = $o['orderby'];
			$args['order']   = $o['order'];
			if ( ! empty( $o['meta_key'] ) ) {
				$args['meta_key'] = $o['meta_key']; // phpcs:ignore WordPress.DB.SlowDBQuery
			}
		} else {
			$args = array_merge( $args, self::sort_args( $sort ) );
		}
		$query = new WP_Query( $args );
		if ( $wcq && method_exists( $wcq, 'remove_ordering_args' ) ) {
			$wcq->remove_ordering_args();
		}
		return $query;
	}

	/** The products as WooCommerce's loop prints them: ul.products, li.product. */
	private static function loop_html( $query, $columns, $page, $limit ) {
		ob_start();
		if ( function_exists( 'wc_setup_loop' ) ) {
			wc_setup_loop( array(
				'columns'      => $columns,
				'name'         => self::SHORTCODE,
				'is_shortcode' => true,
				'is_search'    => false,
				'is_paginated' => true,
				'total'        => (int) $query->found_posts,
				'total_pages'  => (int) $query->max_num_pages,
				'per_page'     => $limit,
				'current_page' => $page,
			) );
		}
		woocommerce_product_loop_start();
		while ( $query->have_posts() ) {
			$query->the_post();
			wc_get_template_part( 'content', 'product' );
		}
		woocommerce_product_loop_end();
		wp_reset_postdata();
		if ( function_exists( 'wc_reset_loop' ) ) {
			wc_reset_loop();
		}
		return ob_get_clean();
	}

	/** The results wrapper's opening tag; data-total is what the script announces. */
	private static function results_open( $total ) {
		return '<div class="twd-sk-shop-grid__results" data-total="' . (int) $total . '" tabindex="-1">';
	}

	/** [twd_shop_grid limit="12" columns="4"]: the bar and the grid, once per page, only while the module is on. */
	public static function shortcode( $atts = array() ) {
		if ( ! TWD_SK_Shop_Gate::on() || self::$printed ) {
			return '';
		}
		$atts    = is_array( $atts ) ? $atts : array();
		$limit   = isset( $atts['limit'] ) ? (int) $atts['limit'] : 12;
		$limit   = $limit >= 1 ? min( self::MAX_LIMIT, $limit ) : 12;
		$columns = isset( $atts['columns'] ) ? (int) $atts['columns'] : 4;
		$columns = $columns >= 1 ? min( self::MAX_COLUMNS, $columns ) : 4;
		self::$printed = true;
		self::enqueue();

		$req   = self::request();
		$cats  = self::categories();
		$slugs = array_map( function ( $c ) {
			return $c['slug'];
		}, $cats );
		$state = array(
			'mode' => 'page',
			'base' => self::page_url(),
			'cat'  => in_array( $req['cat'], $slugs, true ) ? $req['cat'] : '',
			'sort' => '' !== $req['sort'] ? $req['sort'] : self::default_sort(),
			'q'    => $req['q'],
			'page' => $req['page'],
		);
		$query = self::run_query( self::query_args( $state, $limit ), $state['sort'] );
		$total = (int) $query->found_posts;
		$out   = '<div class="twd-sk-shop-grid-wrap">' . self::bar_html( $state, $cats ) . self::results_open( $total )
			. '<div class="woocommerce columns-' . $columns . '">';
		if ( $query->have_posts() ) {
			$out .= '<p class="woocommerce-result-count twd-sk-shop-grid__count">' . esc_html( self::count_text( $total, $state['page'], $limit ) ) . '</p>'
				. self::loop_html( $query, $columns, $state['page'], $limit )
				. self::pager_html( (int) $state['page'], (int) $query->max_num_pages, $state );
		} else {
			$out .= '<p class="woocommerce-info woocommerce-no-products-found">No products were found matching your selection.</p>';
		}
		return $out . '</div></div></div>';
	}

	// -- The shop and category pages ----------------------------------------------------------------------

	/** On the shop and category pages: the bar in place of WooCommerce's sort list, the results wrapped. */
	public static function arrange() {
		if ( ! TWD_SK_Shop_Gate::on() || ! self::archive_context() ) {
			return;
		}
		remove_action( 'woocommerce_before_shop_loop', 'woocommerce_catalog_ordering', 30 );
		add_action( 'woocommerce_before_shop_loop', array( __CLASS__, 'print_bar' ), 15 );
		add_action( 'woocommerce_before_shop_loop', array( __CLASS__, 'open_results' ), 16 );
		add_action( 'woocommerce_after_shop_loop', array( __CLASS__, 'close_results' ), 999 );
		// With nothing found WooCommerce skips the loop's hooks: the bar must stay so the visitor can change it.
		add_action( 'woocommerce_no_products_found', array( __CLASS__, 'print_bar' ), 5 );
		add_action( 'woocommerce_no_products_found', array( __CLASS__, 'open_results' ), 6 );
		add_action( 'woocommerce_no_products_found', array( __CLASS__, 'close_results' ), 999 );
	}

	public static function print_bar() {
		if ( self::$printed || self::in_shortcode_loop() ) {
			return;
		}
		self::$printed = true;
		echo self::bar_html( self::archive_state() ); // phpcs:ignore WordPress.Security.EscapeOutput -- built above, every value escaped.
	}

	public static function open_results() {
		if ( self::$open || self::in_shortcode_loop() ) {
			return;
		}
		self::$open = true;
		global $wp_query;
		$total = is_object( $wp_query ) && isset( $wp_query->found_posts ) ? (int) $wp_query->found_posts : 0;
		echo self::results_open( $total ); // phpcs:ignore WordPress.Security.EscapeOutput -- a number only.
	}

	public static function close_results() {
		if ( ! self::$open ) {
			return;
		}
		self::$open = false;
		echo '</div>';
	}

	/** The bar's search on the shop and category pages: WordPress's own search, within the listing shown. */
	public static function archive_search( $q ) {
		if ( ! TWD_SK_Shop_Gate::on() || ! is_object( $q ) || ( method_exists( $q, 'is_main_query' ) && ! $q->is_main_query() ) ) {
			return;
		}
		$req = self::request();
		if ( '' !== $req['q'] ) {
			$q->set( 's', $req['q'] );
		}
	}

	/** WooCommerce's pager on the shop and category pages lands on the bar too. */
	public static function pagination_args( $args ) {
		if ( TWD_SK_Shop_Gate::on() && is_array( $args ) && self::archive_context() ) {
			$args['add_fragment'] = '#' . self::ANCHOR;
		}
		return $args;
	}

	// -- Style and script ---------------------------------------------------------------------------------

	public static function maybe_enqueue() {
		if ( ! TWD_SK_Shop_Gate::on() ) {
			return;
		}
		if ( self::archive_context() ) {
			self::enqueue();
			return;
		}
		if ( function_exists( 'is_singular' ) && is_singular() ) {
			$id = (int) get_queried_object_id();
			if ( $id > 0 && TWD_SK_Shop_Layout::page_uses( $id, self::SHORTCODE ) ) {
				self::enqueue();
			}
		}
	}

	/** One small stylesheet and one small script (in the footer). Nothing from another site. */
	public static function enqueue() {
		if ( ! function_exists( 'wp_enqueue_style' ) || ( function_exists( 'wp_style_is' ) && wp_style_is( self::HANDLE, 'enqueued' ) ) ) {
			return;
		}
		$ver  = defined( 'TWD_SK_VERSION' ) ? TWD_SK_VERSION : false;
		$base = TWD_SK_URL . 'modules/shop/assets/';
		wp_enqueue_style( self::HANDLE, $base . 'twd-site-kit-shop-grid.css', array(), $ver );
		wp_add_inline_style( self::HANDLE, self::inline_css() );
		wp_enqueue_script( self::HANDLE, $base . 'twd-site-kit-shop-grid.js', array(), $ver, true );
	}

	/** The Shop colour, for pages outside the shop (the shop pages already have it). */
	public static function inline_css() {
		return '.twd-sk-shop-grid,.twd-sk-shop-grid__results{--twd-sk-sg-ink:' . TWD_SK_Shop_Layout::colour() . '}';
	}
}
