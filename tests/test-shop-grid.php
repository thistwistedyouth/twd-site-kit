<?php
/**
 * The Shop module, 0.21.0: the filter bar and the grid that updates in place ([twd_shop_grid], the
 * shop and category pages), and Popularity and Average rating leaving WooCommerce's sorts.
 * Uses get_terms() and the fake WooCommerce from test-shop.php. Every product here is made up.
 */

if ( ! class_exists( 'WP_Query' ) ) {
	/** A stand-in that filters $GLOBALS['twd_grid_products'] the way the grid's arguments ask. */
	class WP_Query { // phpcs:ignore
		public $query_vars    = array();
		public $posts         = array();
		public $found_posts   = 0;
		public $max_num_pages = 0;
		private $at           = 0;
		public function __construct( $args = array() ) {
			$this->query_vars               = $args;
			$GLOBALS['twd_grid_queries'][] = $args;
			$all                            = isset( $GLOBALS['twd_grid_products'] ) ? $GLOBALS['twd_grid_products'] : array();
			$hidden                         = array();
			$cat                            = null;
			foreach ( isset( $args['tax_query'] ) ? $args['tax_query'] : array() as $k => $t ) {
				if ( 'relation' === $k ) {
					continue;
				}
				if ( 'product_visibility' === $t['taxonomy'] ) {
					$hidden = $t['terms'];
				}
				if ( 'product_cat' === $t['taxonomy'] ) {
					$cat = $t['terms'][0];
				}
			}
			$keep = array();
			foreach ( $all as $id => $p ) {
				if ( in_array( 'exclude-from-catalog', $hidden, true ) && ! empty( $p['hidden'] ) ) {
					continue;
				}
				if ( in_array( 'outofstock', $hidden, true ) && ! empty( $p['sold'] ) ) {
					continue;
				}
				if ( null !== $cat && ! in_array( $cat, twd_grid_slugs_with_parents( $p['cats'] ), true ) ) {
					continue;
				}
				if ( ! empty( $args['s'] ) && false === stripos( $p['title'] . ' ' . $p['content'], $args['s'] ) ) {
					continue;
				}
				$keep[ $id ] = $p;
			}
			$order = isset( $args['orderby'] ) ? $args['orderby'] : '';
			uasort( $keep, function ( $a, $b ) use ( $order ) {
				if ( is_array( $order ) && isset( $order['meta_value_num'] ) ) {
					return 'ASC' === $order['meta_value_num'] ? $a['price'] - $b['price'] : $b['price'] - $a['price'];
				}
				return strcmp( $b['date'], $a['date'] );
			} );
			$this->found_posts = count( $keep );
			$per               = isset( $args['posts_per_page'] ) ? (int) $args['posts_per_page'] : 10;
			$ids               = array_keys( $keep );
			if ( $per > 0 ) {
				$page                = isset( $args['paged'] ) ? max( 1, (int) $args['paged'] ) : 1;
				$this->max_num_pages = (int) ceil( count( $ids ) / $per );
				$ids                 = array_slice( $ids, ( $page - 1 ) * $per, $per );
			}
			$this->posts = $ids;
		}
		public function have_posts() {
			return $this->at < count( $this->posts );
		}
		public function the_post() {
			$GLOBALS['twd_grid_current'] = $this->posts[ $this->at++ ];
		}
	}
}

/** A product's category slugs with their parents' (so a sub-category's product is in its parent too). */
function twd_grid_slugs_with_parents( $ids ) {
	$out = array();
	foreach ( $ids as $id ) {
		foreach ( array_merge( array( $id ), get_ancestors( $id, 'product_cat', 'taxonomy' ) ) as $tid ) {
			$out[] = sanitize_title( $GLOBALS['twd_fake_cats'][ $tid ] );
		}
	}
	return $out;
}

if ( ! function_exists( 'wp_get_object_terms' ) ) {
	function wp_get_object_terms( $ids, $tax, $args = array() ) {
		$out = array();
		foreach ( (array) $ids as $id ) {
			foreach ( $GLOBALS['twd_grid_products'][ $id ]['cats'] as $tid ) {
				$out[] = (object) array( 'term_id' => $tid, 'object_id' => $id );
			}
		}
		return $out;
	}
}
if ( ! function_exists( 'get_ancestors' ) ) {
	function get_ancestors( $id, $tax = '', $type = '' ) {
		$out = array();
		while ( isset( $GLOBALS['twd_grid_parents'][ $id ] ) ) {
			$id    = $GLOBALS['twd_grid_parents'][ $id ];
			$out[] = $id;
		}
		return $out;
	}
}
if ( ! function_exists( 'get_term_link' ) ) {
	function get_term_link( $slug, $tax = '' ) {
		return 'https://example.test/product-category/' . $slug . '/';
	}
}
if ( ! function_exists( 'wc_get_page_permalink' ) ) {
	function wc_get_page_permalink( $page ) {
		return 'https://example.test/shop/';
	}
}
if ( ! function_exists( 'get_queried_object' ) ) {
	function get_queried_object() {
		return isset( $GLOBALS['twd_grid_term'] ) ? $GLOBALS['twd_grid_term'] : null;
	}
}
if ( ! function_exists( 'is_shop' ) ) {
	function is_shop() {
		return ! empty( $GLOBALS['twd_grid_where'] ) && 'shop' === $GLOBALS['twd_grid_where'];
	}
}
if ( ! function_exists( 'is_product_category' ) ) {
	function is_product_category() {
		return ! empty( $GLOBALS['twd_grid_where'] ) && 'category' === $GLOBALS['twd_grid_where'];
	}
}
if ( ! function_exists( 'wc_get_loop_prop' ) ) {
	function wc_get_loop_prop( $prop ) {
		return 'is_shortcode' === $prop && ! empty( $GLOBALS['twd_grid_in_shortcode'] );
	}
}
if ( ! function_exists( 'woocommerce_product_loop_start' ) ) {
	function woocommerce_product_loop_start() {
		echo '<ul class="products columns-4">';
	}
}
if ( ! function_exists( 'woocommerce_product_loop_end' ) ) {
	function woocommerce_product_loop_end() {
		echo '</ul>';
	}
}
if ( ! function_exists( 'wc_get_template_part' ) ) {
	function wc_get_template_part( $slug, $name = '' ) {
		$p = $GLOBALS['twd_grid_products'][ $GLOBALS['twd_grid_current'] ];
		echo '<li class="product">' . esc_html( $p['title'] ) . '</li>';
	}
}
if ( ! function_exists( 'wp_reset_postdata' ) ) {
	function wp_reset_postdata() {}
}
if ( ! function_exists( 'WC' ) ) {
	function WC() { // phpcs:ignore
		return isset( $GLOBALS['twd_fake_wc'] ) ? $GLOBALS['twd_fake_wc'] : null;
	}
}

/**
 * WooCommerce's ordering helper, recording what it was asked. Like the real one it splits "price-desc"
 * only when it reads the address itself: given one word it takes it whole, so "price-desc" matches
 * nothing and falls back to newest first.
 */
class Twd_Fake_Wc_Query {
	public $asked   = array();
	public $removed = 0;
	public $clauses = '';
	public function get_catalog_ordering_args( $orderby = '', $order = '' ) {
		$this->asked[] = array( $orderby, $order );
		$order         = 'DESC' === strtoupper( $order ) ? 'DESC' : 'ASC';
		if ( 'price' === $orderby ) {
			$this->clauses = 'DESC' === $order ? 'order_by_price_desc_post_clauses' : 'order_by_price_asc_post_clauses';
			return array( 'orderby' => 'price', 'order' => $order, 'meta_key' => '' );
		}
		return array( 'orderby' => 'date ID', 'order' => 'DESC', 'meta_key' => '' );
	}
	public function remove_ordering_args() {
		$this->removed++;
	}
}

/** A made-up shop: lower-case category names, a Sold category, a hidden piece, a sub-category. */
function twd_grid_setup( $hide_sold = true ) {
	TWD_SK_Shop_Gate::set( true );
	TWD_SK_Shop_Grid::reset();
	$_GET                              = array();
	$GLOBALS['twd_grid_queries']       = array();
	$GLOBALS['twd_grid_where']         = '';
	$GLOBALS['twd_grid_term']          = null;
	$GLOBALS['twd_grid_in_shortcode']  = false;
	$GLOBALS['twd_fake_wc']            = null;
	$GLOBALS['twd_fake_cats']          = array( 11 => 'earrings', 12 => 'ring', 13 => 'pendant', 14 => 'sold', 15 => 'Brooches', 16 => 'Uncategorized', 17 => 'mourning rings', 18 => 'Art Deco' );
	$GLOBALS['twd_grid_parents']       = array( 17 => 12 );
	$GLOBALS['twd_grid_products']      = array(
		101 => array( 'title' => 'Garnet cluster ring', 'content' => 'Rose gold, set with garnets.', 'cats' => array( 12 ), 'price' => 300, 'date' => '2026-01-05' ),
		102 => array( 'title' => 'Pearl drop earrings', 'content' => 'Seed pearls on gold hooks.', 'cats' => array( 11 ), 'price' => 120, 'date' => '2026-02-01' ),
		103 => array( 'title' => 'Silver locket', 'content' => 'A plain oval pendant.', 'cats' => array( 13 ), 'price' => 450, 'date' => '2026-03-01' ),
		104 => array( 'title' => 'Sapphire ring', 'content' => 'Sold last week.', 'cats' => array( 12, 14 ), 'price' => 900, 'date' => '2026-03-09', 'sold' => true ),
		105 => array( 'title' => 'Coral earrings', 'content' => 'Carved coral.', 'cats' => array( 11 ), 'price' => 80, 'date' => '2025-12-01' ),
		106 => array( 'title' => 'Hidden brooch', 'content' => 'Not in the shop.', 'cats' => array( 15 ), 'price' => 200, 'date' => '2026-02-11', 'hidden' => true ),
		107 => array( 'title' => 'Odd piece', 'content' => 'No category yet.', 'cats' => array( 16 ), 'price' => 50, 'date' => '2026-02-12' ),
		108 => array( 'title' => 'Jet mourning ring', 'content' => 'Whitby jet, gold band.', 'cats' => array( 17 ), 'price' => 200, 'date' => '2026-04-01' ),
	);
	update_option( 'woocommerce_hide_out_of_stock_items', $hide_sold ? 'yes' : 'no' );
	update_option( 'default_product_cat', 16 );
	$GLOBALS['twd_stub']['admin'] = false;
	twd_stub_add_post( 5, 'page', '[twd_shop_grid limit="2" columns="3"]' );
	$GLOBALS['twd_stub']['queried'] = 5;
}

function twd_grid_names( $cats ) {
	return implode( ', ', array_map( function ( $c ) {
		return $c['name'] . ' (' . $c['count'] . ')';
	}, $cats ) );
}

twd_sk_test( 'shop grid: category buttons count visible products, skip empty, Uncategorised and Sold, and show lower-case names in title case', function () {
	twd_grid_setup( true );
	twd_sk_eq( 'Earrings (2), Ring (2), Pendant (1), Mourning Rings (1)', twd_grid_names( TWD_SK_Shop_Grid::categories() ), 'sold pieces hidden: the sold ring is not counted, a sub-category counts for its parent' );
	twd_sk_eq( 'ring', TWD_SK_Shop_Grid::categories()[1]['slug'], 'the slug is kept as stored' );
	twd_sk_eq( 'ring', $GLOBALS['twd_fake_cats'][12], 'the term itself is never renamed' );
	update_option( 'woocommerce_hide_out_of_stock_items', 'no' );
	twd_sk_eq( 'Earrings (2), Ring (3), Pendant (1), Mourning Rings (1)', twd_grid_names( TWD_SK_Shop_Grid::categories() ), 'sold pieces shown: counted, but Sold is still never a button' );
	twd_sk_eq( 'Earrings', TWD_SK_Shop_Grid::label( 'earrings' ) );
	twd_sk_eq( 'Rings and Things', TWD_SK_Shop_Grid::label( 'rings and things' ) );
	twd_sk_eq( 'Art Deco', TWD_SK_Shop_Grid::label( 'Art Deco' ) );
	twd_sk_eq( 'eBay finds', TWD_SK_Shop_Grid::label( 'eBay finds' ), 'a name with any capital is shown as stored' );
	twd_sk_eq( 'Mourning & Jet', TWD_SK_Shop_Grid::label( 'mourning &amp; jet' ) );
} );

twd_sk_test( 'shop grid: Popularity and Average rating leave the sorts, the default becomes Latest, and nothing changes with the module off', function () {
	twd_grid_setup();
	$wc = array( 'menu_order' => 'Default sorting', 'popularity' => 'Sort by popularity', 'rating' => 'Sort by average rating', 'date' => 'Sort by latest', 'price' => 'Sort by price: low to high', 'price-desc' => 'Sort by price: high to low' );
	twd_sk_eq( array( 'menu_order', 'date', 'price', 'price-desc' ), array_keys( TWD_SK_Shop_Grid::orderby_options( $wc ) ) );
	foreach ( array( 'popularity', 'rating', 'menu_order', '' ) as $v ) {
		twd_sk_eq( 'date', TWD_SK_Shop_Grid::default_orderby( $v ), $v );
	}
	twd_sk_eq( 'price', TWD_SK_Shop_Grid::default_orderby( 'price' ), 'a price default is kept' );
	update_option( 'woocommerce_default_catalog_orderby', 'popularity' );
	twd_sk_eq( 'date', TWD_SK_Shop_Grid::default_sort() );
	$GLOBALS['twd_grid_where'] = 'shop';
	$_GET                      = array( 'shop_sort' => 'price-desc' );
	twd_sk_eq( 'price-desc', TWD_SK_Shop_Grid::default_orderby( 'popularity' ), 'on the shop page the bar\'s sort is what WooCommerce sorts by' );
	$_GET = array( 'shop_sort' => 'popularity' );
	twd_sk_eq( 'date', TWD_SK_Shop_Grid::default_orderby( 'popularity' ), 'an unknown sort is ignored' );

	TWD_SK_Shop_Gate::set( false );
	twd_sk_eq( $wc, TWD_SK_Shop_Grid::orderby_options( $wc ), 'module off: untouched' );
	twd_sk_eq( 'popularity', TWD_SK_Shop_Grid::default_orderby( 'popularity' ) );
	$_GET = array();
} );

twd_sk_test( 'shop grid: sort arguments, and WooCommerce\'s own ordering used and cleared when it is there', function () {
	twd_grid_setup();
	twd_sk_eq( array( 'orderby' => 'date ID', 'order' => 'DESC' ), TWD_SK_Shop_Grid::sort_args( 'date' ) );
	twd_sk_eq( array( 'meta_key' => '_price', 'orderby' => array( 'meta_value_num' => 'ASC', 'ID' => 'ASC' ) ), TWD_SK_Shop_Grid::sort_args( 'price' ) );
	twd_sk_eq( array( 'meta_key' => '_price', 'orderby' => array( 'meta_value_num' => 'DESC', 'ID' => 'DESC' ) ), TWD_SK_Shop_Grid::sort_args( 'price-desc' ) );
	twd_sk_eq( TWD_SK_Shop_Grid::sort_args( 'date' ), TWD_SK_Shop_Grid::sort_args( 'popularity' ), 'anything else is Latest' );

	$_GET = array( 'shop_sort' => 'price' );
	$html = TWD_SK_Shop_Grid::shortcode( array( 'limit' => '12' ) );
	$q    = end( $GLOBALS['twd_grid_queries'] );
	twd_sk_eq( array( 'meta_value_num' => 'ASC', 'ID' => 'ASC' ), $q['orderby'] );
	twd_sk_true( strpos( $html, 'Coral earrings' ) < strpos( $html, 'Silver locket' ), 'cheapest first' );

	TWD_SK_Shop_Grid::reset();
	$wcq                    = new Twd_Fake_Wc_Query();
	$GLOBALS['twd_fake_wc'] = (object) array( 'query' => $wcq );
	$_GET                   = array( 'shop_sort' => 'price-desc' );
	TWD_SK_Shop_Grid::shortcode( array() );
	$q = end( $GLOBALS['twd_grid_queries'] );
	twd_sk_eq( array( array( 'price', 'DESC' ) ), $wcq->asked, 'WooCommerce is given the sort and the direction apart (0.21.0-test8)' );
	twd_sk_eq( 'order_by_price_desc_post_clauses', $wcq->clauses, 'so its highest-price-first clauses are used' );
	twd_sk_eq( 'price', $q['orderby'] );
	twd_sk_eq( 'DESC', $q['order'] );
	twd_sk_eq( 1, $wcq->removed, 'WooCommerce\'s price clauses are taken off again straight after' );

	foreach ( array( 'price' => array( 'price', '' ), 'price-desc' => array( 'price', 'DESC' ), 'date' => array( 'date', '' ) ) as $sort => $want ) {
		twd_sk_eq( $want, TWD_SK_Shop_Grid::wc_order( $sort ), $sort );
	}
	TWD_SK_Shop_Grid::reset();
	$wcq->clauses = '';
	$_GET         = array( 'shop_sort' => 'price' );
	TWD_SK_Shop_Grid::shortcode( array() );
	twd_sk_eq( 'order_by_price_asc_post_clauses', $wcq->clauses, 'lowest price first' );
	$_GET = array();
} );

twd_sk_test( 'shop grid: the search box searches within the category shown; long or spaced searches are tidied', function () {
	twd_grid_setup();
	$_GET = array( 'shop_q' => '  gold   ', 'shop_cat' => 'ring' );
	$html = TWD_SK_Shop_Grid::shortcode( array() );
	$q    = end( $GLOBALS['twd_grid_queries'] );
	twd_sk_eq( 'gold', $q['s'] );
	twd_sk_has( 'Garnet cluster ring', $html );
	twd_sk_has( 'Jet mourning ring', $html, 'a sub-category is within its parent' );
	twd_sk_hasnt( 'Pearl drop earrings', $html, 'gold, but not a ring' );
	twd_sk_has( 'value="gold"', $html, 'the box keeps the words' );
	twd_sk_eq( 80, strlen( TWD_SK_Shop_Grid::request( array( 'shop_q' => str_repeat( 'a', 200 ) ) )['q'] ) );
	twd_sk_eq( 'two words', TWD_SK_Shop_Grid::request( array( 'shop_q' => "two \n  words" ) )['q'] );
	twd_sk_eq( '', TWD_SK_Shop_Grid::request( array( 'shop_q' => array( 'x' ) ) )['q'], 'an array is ignored' );

	// The shop and category pages: WordPress's own search on the main query only.
	$main = new class() {
		public $set = array();
		public $main = true;
		public function set( $k, $v ) {
			$this->set[ $k ] = $v;
		}
		public function is_main_query() {
			return $this->main;
		}
	};
	$_GET = array( 'shop_q' => 'locket' );
	TWD_SK_Shop_Grid::archive_search( $main );
	twd_sk_eq( array( 's' => 'locket' ), $main->set );
	$other       = clone $main;
	$other->set  = array();
	$other->main = false;
	TWD_SK_Shop_Grid::archive_search( $other );
	twd_sk_eq( array(), $other->set, 'another query on the page is left alone' );
	$_GET = array();
} );

twd_sk_test( 'shop grid: without a script every control is a plain link or GET form that lands on the bar', function () {
	twd_grid_setup();
	// A page (the shortcode): its own parameters on the page's address, never WordPress's.
	$page = array( 'mode' => 'page', 'base' => 'https://example.test/?page_id=5', 'cat' => '', 'sort' => 'date', 'q' => 'gold', 'page' => 2 );
	twd_sk_eq( 'https://example.test/?page_id=5&shop_cat=ring#twd-sk-shop-grid', TWD_SK_Shop_Grid::url_for( $page, array( 'cat' => 'ring', 'q' => '', 'page' => 1 ) ), 'a category drops the search and the page; Latest is the default, so left out' );
	twd_sk_eq( 'https://example.test/?page_id=5&shop_sort=price&shop_q=gold%20ring&shop_page=3#twd-sk-shop-grid', TWD_SK_Shop_Grid::url_for( $page, array( 'sort' => 'price', 'q' => 'gold ring', 'page' => 3 ) ) );
	twd_sk_eq( 'https://example.test/?page_id=5#twd-sk-shop-grid', TWD_SK_Shop_Grid::url_for( $page, array( 'q' => '', 'page' => 1 ) ) );
	twd_sk_eq( 'https://example.test/home/?utm=x&shop_cat=ring', TWD_SK_Shop_Grid::with_params( 'https://example.test/home/?shop_cat=old&utm=x#top', array( 'shop_cat' => 'ring' ) ), 'old values replaced, other parameters kept' );
	foreach ( array( 'orderby=', 's=', 'paged=', 'product_cat=' ) as $wp ) {
		twd_sk_hasnt( $wp, TWD_SK_Shop_Grid::url_for( $page, array( 'cat' => 'ring', 'sort' => 'price-desc', 'page' => 4 ) ), $wp );
	}

	// The shop and category pages: a category is its own page on the shop.
	$shop = array( 'mode' => 'archive', 'base' => 'https://example.test/shop/', 'cat' => '', 'sort' => 'price', 'q' => 'gold', 'page' => 1 );
	twd_sk_eq( 'https://example.test/product-category/ring/?shop_sort=price#twd-sk-shop-grid', TWD_SK_Shop_Grid::url_for( $shop, array( 'cat' => 'ring', 'q' => '' ) ) );
	twd_sk_eq( 'https://example.test/shop/?shop_sort=price#twd-sk-shop-grid', TWD_SK_Shop_Grid::url_for( array_merge( $shop, array( 'cat' => 'ring' ) ), array( 'cat' => '', 'q' => '' ) ), 'All is the shop' );

	// The forms: GET, the action lands on the bar, the page's own address kept as a hidden field.
	$bar = TWD_SK_Shop_Grid::bar_html( array_merge( $page, array( 'cat' => 'ring', 'page' => 1 ) ) );
	twd_sk_has( '<form class="twd-sk-shop-grid__sort" method="get" action="https://example.test/#twd-sk-shop-grid">', $bar );
	twd_sk_has( '<input type="hidden" name="page_id" value="5">', $bar );
	twd_sk_has( '<input type="hidden" name="shop_cat" value="ring">', $bar, 'search and sort stay within the category' );
	twd_sk_has( '<input type="hidden" name="shop_q" value="gold">', $bar, 'the sort keeps the search' );
	twd_sk_has( '<select id="twd-sk-shop-grid-sort" class="twd-sk-shop-grid__select" name="shop_sort"', $bar );
	twd_sk_has( '<button type="submit" class="twd-sk-shop-grid__go"', $bar, 'a Sort button for when there is no script' );
	twd_sk_has( 'role="search"', $bar );
	twd_sk_has( 'id="twd-sk-shop-grid"', $bar );
	twd_sk_has( 'role="status" aria-live="polite"', $bar, 'the count is announced' );
	twd_sk_has( '<option value="date" selected>Latest</option><option value="price">Price low to high</option><option value="price-desc">Price high to low</option>', $bar );
	twd_sk_hasnt( 'opularity', $bar );
	twd_sk_hasnt( 'rating', $bar );
	twd_sk_true( 1 === substr_count( $bar, 'aria-current="page"' ) && false !== strpos( $bar, 'data-key="cat:ring" aria-current="page">Ring <span' ), 'the category shown is marked' );
	twd_sk_has( '>All</a>', $bar );
	twd_sk_true( ! preg_match( '/<[^>]+\sstyle=/', $bar ) && ! preg_match( '/\son[a-z]+=/', $bar ), 'no inline styles or handlers' );
	$_GET = array();
} );

twd_sk_test( 'shop grid: the shortcode draws the bar and WooCommerce\'s grid markup with a count and a pager, once per page', function () {
	twd_grid_setup();
	$html = TWD_SK_Shop_Grid::shortcode( array( 'limit' => '2', 'columns' => '3' ) );
	twd_sk_has( '<div class="twd-sk-shop-grid-wrap"><div id="twd-sk-shop-grid" class="twd-sk-shop-grid">', $html );
	twd_sk_has( '<div class="twd-sk-shop-grid__results" data-total="6" tabindex="-1"><div class="woocommerce columns-3">', $html, 'the hidden brooch and the sold ring are left out' );
	twd_sk_has( '<ul class="products', $html );
	twd_sk_eq( 2, substr_count( $html, '<li class="product">' ) );
	twd_sk_has( 'Showing 1 to 2 of 6 results', $html );
	twd_sk_has( 'Jet mourning ring', $html, 'newest first' );
	twd_sk_has( '<nav class="woocommerce-pagination twd-sk-shop-grid__pager" aria-label="Product pages">', $html );
	twd_sk_has( 'href="https://example.test/?page_id=5&amp;shop_page=2#twd-sk-shop-grid"', $html );
	twd_sk_has( '<span aria-current="page" class="page-numbers current">1</span>', $html );
	twd_sk_has( 'Earrings <span class="twd-sk-shop-grid__n">(2)</span>', $html );
	twd_sk_hasnt( '>Sold', $html );
	twd_sk_hasnt( 'Brooches', $html );
	twd_sk_hasnt( 'Uncategorized', $html );
	twd_sk_eq( '', TWD_SK_Shop_Grid::shortcode( array() ), 'only the first on a page draws' );
	twd_sk_true( ! empty( $GLOBALS['twd_stub']['styles']['twd-site-kit-shop-grid']['enqueued'] ), 'its stylesheet' );
	twd_sk_true( ! empty( $GLOBALS['twd_stub']['scripts']['twd-site-kit-shop-grid']['enqueued'] ), 'and its script' );
	twd_sk_has( '--twd-sk-sg-ink:#1f3a33', $GLOBALS['twd_stub']['styles']['twd-site-kit-shop-grid']['inline'] );

	TWD_SK_Shop_Grid::reset();
	$_GET = array( 'shop_cat' => 'not-a-category', 'shop_page' => '2' );
	$html = TWD_SK_Shop_Grid::shortcode( array( 'limit' => '500', 'columns' => '0' ) );
	twd_sk_true( false !== strpos( $html, 'data-key="cat:" aria-current="page">All' ), 'an unknown category shows All' );
	twd_sk_eq( 48, end( $GLOBALS['twd_grid_queries'] )['posts_per_page'], 'the limit is capped' );
	twd_sk_has( 'columns-4', $html, 'a bad column count falls back to 4' );

	TWD_SK_Shop_Grid::reset();
	$_GET = array( 'shop_q' => 'nothing like this' );
	$html = TWD_SK_Shop_Grid::shortcode( array() );
	twd_sk_has( 'data-total="0"', $html );
	twd_sk_has( 'woocommerce-no-products-found', $html );
	twd_sk_has( 'value="nothing like this"', $html, 'the bar stays so the visitor can change the search' );
	$_GET = array();

	twd_sk_eq( array( 1, 2, 3, 4, 5, 6, 7 ), TWD_SK_Shop_Grid::page_numbers( 4, 7 ) );
	twd_sk_eq( array( 1, 0, 5, 6, 7, 0, 20 ), TWD_SK_Shop_Grid::page_numbers( 6, 20 ) );
	twd_sk_eq( array( 1, 2, 0, 20 ), TWD_SK_Shop_Grid::page_numbers( 1, 20 ) );
	twd_sk_eq( 'Showing the single result', TWD_SK_Shop_Grid::count_text( 1, 1, 12 ) );
	twd_sk_eq( 'Showing all 5 results', TWD_SK_Shop_Grid::count_text( 5, 1, 12 ) );
	twd_sk_eq( 'Showing 13 to 20 of 20 results', TWD_SK_Shop_Grid::count_text( 20, 2, 12 ) );
} );

twd_sk_test( 'shop grid: on the shop and category pages the bar replaces WooCommerce\'s sort list and wraps the results', function () {
	twd_grid_setup();
	TWD_SK_Shop_Grid::arrange();
	twd_sk_true( empty( $GLOBALS['twd_stub']['hooks']['woocommerce_before_shop_loop'] ), 'not on other pages' );

	$GLOBALS['twd_grid_where'] = 'category';
	$GLOBALS['twd_grid_term']  = (object) array( 'slug' => 'ring', 'term_id' => 12 );
	$_GET                      = array( 'shop_q' => 'jet' );
	TWD_SK_Shop_Grid::arrange();
	twd_sk_eq( 'woocommerce_catalog_ordering', $GLOBALS['twd_stub']['removed_actions'][0]['callback'] );
	twd_sk_eq( 2, count( $GLOBALS['twd_stub']['hooks']['woocommerce_before_shop_loop'] ) );
	twd_sk_eq( 3, count( $GLOBALS['twd_stub']['hooks']['woocommerce_no_products_found'] ), 'with nothing found the bar still shows' );

	$GLOBALS['wp_query'] = (object) array( 'found_posts' => 1 );
	ob_start();
	TWD_SK_Shop_Grid::print_bar();
	TWD_SK_Shop_Grid::open_results();
	echo '<ul class="products"></ul>';
	TWD_SK_Shop_Grid::close_results();
	TWD_SK_Shop_Grid::print_bar();
	TWD_SK_Shop_Grid::close_results();
	$out = ob_get_clean();
	twd_sk_eq( 1, substr_count( $out, 'id="twd-sk-shop-grid"' ), 'printed once' );
	twd_sk_has( '<div class="twd-sk-shop-grid__results" data-total="1" tabindex="-1"><ul class="products"></ul></div>', $out );
	twd_sk_eq( substr_count( $out, '<div' ), substr_count( $out, '</div>' ), 'every wrapper closed once' );
	twd_sk_has( 'data-key="cat:ring" aria-current="page"', $out );
	twd_sk_has( 'href="https://example.test/shop/#twd-sk-shop-grid"', $out, 'All goes to the shop' );
	twd_sk_has( 'action="https://example.test/product-category/ring/#twd-sk-shop-grid"', $out, 'searching stays in the category' );
	twd_sk_has( 'value="jet"', $out );
	twd_sk_eq( array( 'add_fragment' => '#twd-sk-shop-grid' ), TWD_SK_Shop_Grid::pagination_args( array() ), 'WooCommerce\'s pager lands on the bar' );

	TWD_SK_Shop_Grid::reset();
	$GLOBALS['twd_grid_in_shortcode'] = true;
	ob_start();
	TWD_SK_Shop_Grid::print_bar();
	TWD_SK_Shop_Grid::open_results();
	TWD_SK_Shop_Grid::close_results();
	twd_sk_eq( '', ob_get_clean(), 'never inside a [products] grid on the page' );
	$_GET = array();
	unset( $GLOBALS['wp_query'] );
} );

twd_sk_test( 'shop grid: with the module off there is no bar, no grid, no files and no change to the sorts', function () {
	twd_grid_setup();
	TWD_SK_Shop_Gate::set( false );
	twd_sk_eq( '', TWD_SK_Shop_Grid::shortcode( array( 'limit' => '12' ) ) );
	$GLOBALS['twd_grid_where'] = 'shop';
	TWD_SK_Shop_Grid::arrange();
	TWD_SK_Shop_Grid::maybe_enqueue();
	twd_sk_true( empty( $GLOBALS['twd_stub']['hooks']['woocommerce_before_shop_loop'] ) );
	twd_sk_true( empty( $GLOBALS['twd_stub']['styles']['twd-site-kit-shop-grid'] ) && empty( $GLOBALS['twd_stub']['scripts']['twd-site-kit-shop-grid'] ) );
	twd_sk_eq( array(), TWD_SK_Shop_Grid::pagination_args( array() ) );
	$q = new class() {
		public $set = array();
		public function set( $k, $v ) {
			$this->set[ $k ] = $v;
		}
		public function is_main_query() {
			return true;
		}
	};
	$_GET = array( 'shop_q' => 'ring' );
	TWD_SK_Shop_Grid::archive_search( $q );
	twd_sk_eq( array(), $q->set );
	$_GET = array();

	TWD_SK_Shop_Gate::set( true );
	TWD_SK_Shop_Grid::maybe_enqueue();
	twd_sk_true( ! empty( $GLOBALS['twd_stub']['styles']['twd-site-kit-shop-grid']['enqueued'] ), 'on: the shop page loads it' );
	$GLOBALS['twd_grid_where'] = '';
	$GLOBALS['twd_stub']['styles'] = array();
	TWD_SK_Shop_Grid::maybe_enqueue();
	twd_sk_true( ! empty( $GLOBALS['twd_stub']['styles']['twd-site-kit-shop-grid']['enqueued'] ), 'on: a page holding the shortcode loads it' );
	twd_stub_add_post( 5, 'page', 'Just words.' );
	$GLOBALS['twd_stub']['styles'] = array();
	TWD_SK_Shop_Grid::maybe_enqueue();
	twd_sk_true( empty( $GLOBALS['twd_stub']['styles']['twd-site-kit-shop-grid'] ), 'other pages load nothing' );
} );

twd_sk_test( 'shop grid: the script swaps markup without innerHTML; the stylesheet stays under its own classes and fetches nothing', function () {
	$js = file_get_contents( ABSPATH . 'modules/shop/assets/twd-site-kit-shop-grid.js' );
	foreach ( array( 'innerHTML', 'outerHTML', 'insertAdjacentHTML', 'document.write', 'eval(', 'new Function' ) as $bad ) {
		twd_sk_hasnt( $bad, $js, $bad );
	}
	foreach ( array( 'DOMParser', 'importNode', 'pushState', 'popstate', 'aria-busy', 'textContent', 'prefers-reduced-motion', 'credentials: \'same-origin\'' ) as $good ) {
		twd_sk_has( $good, $js, $good );
	}
	twd_sk_true( ! preg_match( '#https?://#', $js ), 'no other site' );

	$css = file_get_contents( ABSPATH . 'modules/shop/assets/twd-site-kit-shop-grid.css' );
	twd_sk_true( ! preg_match( '#https?://|@import|url\(#', $css ), 'nothing fetched' );
	$body = preg_replace( '#/\*.*?\*/#s', '', $css );
	$body = preg_replace( '#@media[^{]*\{#', '', $body );
	preg_match_all( '#([^{}]+)\{#', $body, $m );
	foreach ( $m[1] as $sel ) {
		foreach ( explode( ',', $sel ) as $one ) {
			$one = trim( $one );
			if ( '' === $one ) {
				continue;
			}
			twd_sk_true( 0 === strpos( $one, '.twd-sk-shop-grid' ), 'scoped: ' . $one );
		}
	}
	twd_sk_true( ! preg_match( '#border-radius:\s*[1-9]#', $css ), 'square corners' );
	twd_sk_has( 'overflow-x: auto', $css, 'phones: categories scroll sideways' );
	twd_sk_has( 'grid-template-columns: minmax(0, 1fr) minmax(0, 1fr)', $css, 'phones: sort and search side by side' );
} );
