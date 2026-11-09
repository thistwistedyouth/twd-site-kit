<?php
/**
 * The Shop module, 0.21.0: shop reviews. The checks and the cleaner, the CSV import (template,
 * headings, refusals, no duplicates, Check saves nothing), the display (strip, wall, single,
 * summary line, names) and the rules (no structured data, scoped stylesheet, module only).
 * All reviews here are made up.
 */

if ( ! function_exists( 'sanitize_key' ) ) {
	function sanitize_key( $key ) {
		return preg_replace( '/[^a-z0-9_\-]/', '', strtolower( (string) $key ) );
	}
}

/** Save made-up reviews; each item is ( platform, name, date, stars, text, fav, hidden ). */
function twd_rv_seed( $rows ) {
	$ids = array();
	foreach ( $rows as $r ) {
		$c = TWD_SK_Shop_Reviews::check( array( 'platform' => $r[0], 'name' => $r[1], 'date' => $r[2], 'stars' => $r[3], 'text' => $r[4], 'fav' => ! empty( $r[5] ), 'hidden' => ! empty( $r[6] ) ) );
		twd_sk_true( isset( $c['review'] ), 'seed row ' . $r[1] );
		$ids[] = TWD_SK_Shop_Reviews::save( $c['review'] );
	}
	return $ids;
}

function twd_rv_csv( $lines ) {
	return implode( "\r\n", $lines ) . "\r\n";
}

// -- The checks ----------------------------------------------------------------------------------

twd_sk_test( 'shop reviews: the cleaner removes HTML tags and nothing else', function () {
	$in = "Lovely ring <3 and a < b. <b>Fast</b> post \xE2\x80\x94 thank you!<script>x()</script>\n\nSecond line,  two spaces.";
	twd_sk_eq( "Lovely ring <3 and a < b. Fast post \xE2\x80\x94 thank you!\n\nSecond line,  two spaces.", TWD_SK_Shop_Reviews::strip_html( $in ) );
	twd_sk_eq( "Caf\xC3\xA9", TWD_SK_Shop_Reviews::strip_html( "Caf\xE9" ), 'Windows encoding becomes UTF-8, same letter' );
} );

twd_sk_test( 'shop reviews: dates keep month and year; stars are whole numbers 1 to 5; never guessed', function () {
	foreach ( array( '2025-03', '2025-03-14', '14/03/2025', '03/2025', 'March 2025', 'Mar 2025', '14 March 2025', 'Mar 14, 2025', '14th March 2025', '2025-03-14 10:22:01' ) as $d ) {
		twd_sk_eq( '2025-03', TWD_SK_Shop_Reviews::month( $d ), $d );
	}
	twd_sk_eq( '2025-04', TWD_SK_Shop_Reviews::month( '03/04/2025' ), 'day first' );
	twd_sk_eq( '2024-09', TWD_SK_Shop_Reviews::month( 'Sept 2024' ) );
	foreach ( array( '', 'last week', '13/2025', 'Marchy 2025', '1850-01', '1850' ) as $bad ) {
		twd_sk_eq( '', TWD_SK_Shop_Reviews::month( $bad ), 'bad: ' . $bad );
	}
	twd_sk_eq( 'March 2025', TWD_SK_Shop_Reviews::month_label( '2025-03' ) );
	foreach ( array( '5' => 5, '5.0' => 5, '4/5' => 4, '3 stars' => 3, '1 star' => 1, "\u{2605}\u{2605}\u{2605}\u{2605}\u{2606}" => 4 ) as $in => $want ) {
		twd_sk_eq( $want, TWD_SK_Shop_Reviews::stars( (string) $in ), (string) $in );
	}
	foreach ( array( '', '0', '6', '4.5', 'great' ) as $bad ) {
		twd_sk_eq( 0, TWD_SK_Shop_Reviews::stars( $bad ), 'bad stars: ' . $bad );
	}
	twd_sk_eq( array( 'ebay', false ), TWD_SK_Shop_Reviews::platform( 'eBay' ) );
	twd_sk_eq( array( 'other', true ), TWD_SK_Shop_Reviews::platform( 'Facebook' ) );
	twd_sk_true( false === TWD_SK_Shop_Reviews::link( 'javascript:alert(1)' ) );
	twd_sk_eq( '', TWD_SK_Shop_Reviews::link( '' ) );
} );

twd_sk_test( 'shop reviews: a row with no name, date or text is refused with the reasons', function () {
	$r = TWD_SK_Shop_Reviews::check( array( 'platform' => 'Etsy', 'name' => ' ', 'date' => '', 'stars' => '9', 'text' => '<p></p>' ) );
	twd_sk_has( 'no name', $r['error'] );
	twd_sk_has( 'no date', $r['error'] );
	twd_sk_has( 'no review text', $r['error'] );
	twd_sk_has( 'not a whole number from 1 to 5', $r['error'] );
	$ok = TWD_SK_Shop_Reviews::check( array( 'platform' => 'Etsy', 'name' => 'Ann Example', 'date' => 'May 2024', 'stars' => '5', 'text' => 'Beautiful.', 'link' => 'https://example.test/r/1' ) );
	twd_sk_eq( 'etsy', $ok['review']['platform'] );
	twd_sk_eq( '2024-05', $ok['review']['month'] );
	twd_sk_eq( TWD_SK_Shop_Reviews::key( 'etsy', 'ann example', '2024-05', "Beautiful. " ), $ok['review']['key'], 'name case and spacing do not change the key' );
} );

// -- The import ----------------------------------------------------------------------------------

twd_sk_test( 'shop reviews import: the template reads back; headings in any order and case; every row reported', function () {
	twd_sk_eq( "\xEF\xBB\xBFPlatform,Name,Date,Stars,Review,Item,Link\r\n", TWD_SK_Shop_Reviews_Import::template() );
	twd_sk_has( 'has headings but no reviews', TWD_SK_Shop_Reviews_Import::parse( TWD_SK_Shop_Reviews_Import::template() )['error'] );
	$csv = twd_rv_csv( array(
		"\xEF\xBB\xBFREVIEW,stars,Name,Date,Platform,Item,Link",
		'"Lovely, thank you",5,Ann Example,2024-05-02,Etsy,Garnet ring,https://example.test/r/1',
		"\"Two\n\nparagraphs\",4,Bob Example,June 2024,ebay,,",
		',5,Cat Example,2024-07,Etsy,,',
		'Fine,5,,2024-07,Etsy,,',
		'Fine,5,Dan Example,someday,Etsy,,',
		'"Lovely, thank you",5,Ann Example,May 2024,Etsy,,',
		'Odd,5,Eve Example,2024-08,Facebook,,',
		',,,,,,',
	) );
	$check = TWD_SK_Shop_Reviews_Import::run( $csv, true );
	twd_sk_eq( array( 'added' => 3, 'updated' => 0, 'there' => 1, 'refused' => 3 ), $check['counts'] );
	twd_sk_eq( array(), TWD_SK_Shop_Reviews::all(), 'Check saves nothing' );
	twd_sk_eq( 'ready', $check['rows'][0][1] );
	twd_sk_eq( 4, $check['rows'][2][0], 'row numbers match the spreadsheet, counting the header and the two-line cell' );
	twd_sk_has( 'no review text', $check['rows'][2][3] );
	twd_sk_has( 'no name', $check['rows'][3][3] );
	twd_sk_has( 'could not be read', $check['rows'][4][3] );
	twd_sk_eq( 'there', $check['rows'][5][1], 'the same review twice in one file' );
	twd_sk_has( 'kept as Other', $check['rows'][6][3] );
	$done = TWD_SK_Shop_Reviews_Import::run( $csv, false );
	twd_sk_eq( 3, $done['counts']['added'] );
	$all = TWD_SK_Shop_Reviews::all();
	twd_sk_eq( 3, count( $all ) );
	$bob = array_values( array_filter( $all, function ( $r ) {
		return 'Bob Example' === $r['name'];
	} ) )[0];
	twd_sk_eq( "Two\n\nparagraphs", $bob['text'], 'verbatim, line breaks kept' );
	twd_sk_eq( 'ebay', $bob['platform'] );
	$again = TWD_SK_Shop_Reviews_Import::run( $csv, false );
	twd_sk_eq( array( 'added' => 0, 'updated' => 0, 'there' => 4, 'refused' => 3 ), $again['counts'], 'importing again adds nothing' );
	twd_sk_eq( 3, count( TWD_SK_Shop_Reviews::all() ) );
	$html = TWD_SK_Shop_Reviews_Admin::report_html( $again );
	twd_sk_has( 'Imported: 0 added, 0 dates filled in, 4 already there, 3 refused.', $html );
} );

twd_sk_test( 'shop reviews import: missing columns are named; other common headings and a platform for the whole file', function () {
	$bad = TWD_SK_Shop_Reviews_Import::parse( twd_rv_csv( array( 'Who,When,Words', 'Ann,2024-01,Hi' ) ) );
	twd_sk_has( 'These columns were not found: Name, Date, Stars, Review', $bad['error'] );
	twd_sk_has( 'The first row has: Who, When, Words', $bad['error'] );
	$csv = twd_rv_csv( array( 'Reviewer;Date Reviewed;Star Rating;Message', "Ann Example;14/03/2025;5;Gorgeous \x96 thank you" ) );
	$r   = TWD_SK_Shop_Reviews_Import::run( $csv, false, 'etsy' );
	twd_sk_eq( 1, $r['counts']['added'] );
	$one = TWD_SK_Shop_Reviews::all()[0];
	twd_sk_eq( 'etsy', $one['platform'] );
	twd_sk_eq( "Gorgeous \xE2\x80\x93 thank you", $one['text'], 'semicolons, Windows encoding converted, the dash kept as written' );
	twd_sk_has( 'larger than 2 MB', TWD_SK_Shop_Reviews_Import::parse( str_repeat( 'x', TWD_SK_Shop_Reviews_Import::MAX_BYTES + 1 ) )['error'] );
} );

// -- Showing them --------------------------------------------------------------------------------

twd_sk_test( 'shop reviews: the strip shows favourites (or the newest), never hidden ones, with the Read all link', function () {
	twd_rv_seed( array(
		array( 'Etsy', 'Ann Example', '2024-01', 5, 'Old favourite.', true ),
		array( 'eBay', 'Bob Example', '2024-06', 5, 'Newer favourite.', true ),
		array( 'Etsy', 'Cat Example', '2024-09', 5, 'Hidden favourite.', true, true ),
		array( 'Etsy', 'Dan Example', '2024-12', 4, 'Not a favourite.' ),
	) );
	$names = array_map( function ( $r ) {
		return $r['name'];
	}, TWD_SK_Shop_Layout_Rv::strip() );
	twd_sk_eq( array( 'Bob Example', 'Ann Example' ), $names );
	twd_stub_add_post( 300, 'page' );
	$GLOBALS['twd_stub']['posts'][300]->post_status = 'publish';
	update_option( TWD_SK_Shop_Reviews::OPT_PAGE, 300 );
	$html = TWD_SK_Shop_Reviews_Show::strip_html( 9 );
	twd_sk_has( 'Read all reviews', $html );
	twd_sk_has( '?page_id=300', $html );
	twd_sk_hasnt( 'Hidden favourite', $html );
	twd_sk_hasnt( 'Not a favourite', $html );
	twd_sk_has( 'aria-label="5 out of 5 stars"', $html );
	twd_sk_has( '<span class="twd-sk-reviews__platform">eBay</span>', $html );
	twd_sk_has( 'January 2024', $html );
	foreach ( TWD_SK_Shop_Reviews::all() as $r ) {
		delete_post_meta( $r['id'], TWD_SK_Shop_Reviews::META . 'fav' );
	}
	twd_sk_eq( 3, count( TWD_SK_Shop_Reviews_Show::strip_reviews( 3 ) ), 'no favourites: the newest visible' );
	twd_sk_eq( 3, count( TWD_SK_Shop_Reviews_Show::strip_reviews( 9 ) ), 'four at most, three visible here' );
} );

class TWD_SK_Shop_Layout_Rv {
	public static function strip() {
		return TWD_SK_Shop_Reviews_Show::strip_reviews( 4 );
	}
}

twd_sk_test( 'shop reviews: words are escaped, a long review folds and its two parts are the whole review', function () {
	$long = str_repeat( 'The brooch arrived beautifully wrapped and is even lovelier in person. ', 8 );
	twd_rv_seed( array( array( 'Etsy', 'Ann <b>Example</b>', '2024-01', 5, 'Fish & chips <3 "quoted"', true ), array( 'Etsy', 'Bob Example', '2024-02', 5, $long ) ) );
	$all  = TWD_SK_Shop_Reviews::all();
	$card = TWD_SK_Shop_Reviews_Show::card( $all[1] );
	twd_sk_has( 'Fish &amp; chips &lt;3 &quot;quoted&quot;', $card );
	twd_sk_has( '>Ann Example<', $card );
	twd_sk_hasnt( 'style=', $card );
	$html = TWD_SK_Shop_Reviews_Show::words_html( $all[0]['text'] );
	twd_sk_has( '<details class="twd-sk-reviews__more"><summary>Read more</summary>', $html );
	$plain = html_entity_decode( strip_tags( str_replace( 'Read more', '', $html ) ), ENT_QUOTES );
	twd_sk_eq( preg_replace( '/\s+/', '', trim( $long ) ), preg_replace( '/\s+/', '', $plain ), 'nothing lost or added' );
	update_option( TWD_SK_Shop_Reviews::OPT_NAMES, 'initials' );
	twd_sk_eq( 'Sarah K.', TWD_SK_Shop_Reviews::shown_name( 'Sarah Kent' ) );
	twd_sk_eq( 'jewel_fan99', TWD_SK_Shop_Reviews::shown_name( 'jewel_fan99' ), 'one word stays as it is' );
} );

twd_sk_test( 'shop reviews: the wall shows 24 at a time with Show more and platform filter links', function () {
	$rows = array();
	for ( $i = 1; $i <= 30; $i++ ) {
		$rows[] = array( $i <= 20 ? 'Etsy' : 'eBay', 'Person ' . $i, sprintf( '2024-%02d', 1 + ( $i % 12 ) ), 5, 'Review number ' . $i . '.' );
	}
	$rows[] = array( 'Etsy', 'Hidden Person', '2024-01', 5, 'Never shown.', false, true );
	twd_rv_seed( $rows );
	$base = 'https://example.test/reviews/';
	$html = TWD_SK_Shop_Reviews_Show::wall_html( '', 0, $base );
	twd_sk_eq( 24, substr_count( $html, 'class="twd-sk-reviews__card"' ) );
	twd_sk_has( 'Showing 24 of 30', $html );
	twd_sk_has( 'twd_reviews_show=48#twd-sk-review-25', $html );
	twd_sk_has( 'All (30)', $html );
	twd_sk_has( 'Etsy (20)', $html );
	twd_sk_has( 'twd_reviews_platform=ebay#twd-sk-reviews-wall', $html );
	twd_sk_hasnt( 'Never shown', $html );
	$more = TWD_SK_Shop_Reviews_Show::wall_html( '', 48, $base );
	twd_sk_eq( 30, substr_count( $more, 'class="twd-sk-reviews__card"' ) );
	twd_sk_hasnt( 'Show more', $more );
	$ebay = TWD_SK_Shop_Reviews_Show::wall_html( 'ebay', 24, $base );
	twd_sk_eq( 10, substr_count( $ebay, 'class="twd-sk-reviews__card"' ) );
	twd_sk_has( 'aria-current="true">eBay (10)', $ebay );
	twd_sk_eq( 24, substr_count( TWD_SK_Shop_Reviews_Show::wall_html( 'nonsense', 24, $base ), 'class="twd-sk-reviews__card"' ), 'an unknown platform shows all' );
} );

twd_sk_test( 'shop reviews: the summary line uses only the typed totals and links; nothing typed, no line', function () {
	twd_sk_eq( '', TWD_SK_Shop_Reviews_Show::summary_html() );
	update_option( 'twd_sk_shop_reviews_etsy_total', '250' );
	update_option( 'twd_sk_shop_reviews_etsy_link', 'https://example.test/shop/reviews' );
	update_option( 'twd_sk_shop_reviews_ebay_total', '62' );
	update_option( 'twd_sk_shop_reviews_ebay_link', 'javascript:alert(1)' );
	update_option( TWD_SK_Shop_Reviews::OPT_WORDS, 'five-star' );
	$line = TWD_SK_Shop_Reviews_Show::summary_html();
	twd_sk_has( '<strong>312</strong> five-star reviews on <a href="https://example.test/shop/reviews" rel="nofollow noopener">Etsy</a> and eBay', $line );
	twd_sk_hasnt( 'javascript', $line );
	update_option( 'twd_sk_shop_reviews_ebay_total', 'lots' );
	twd_sk_has( '<strong>250</strong> five-star reviews on <a', TWD_SK_Shop_Reviews_Show::summary_html() );
} );

twd_sk_test( 'shop reviews: single picks a short favourite by product number; the product page setting is on by default', function () {
	twd_rv_seed( array(
		array( 'Etsy', 'Ann Example', '2024-01', 5, 'Short one.', true ),
		array( 'Etsy', 'Bob Example', '2024-02', 5, 'Short two.', true ),
		array( 'Etsy', 'Cat Example', '2024-03', 5, str_repeat( 'Long favourite. ', 30 ), true ),
		array( 'Etsy', 'Dan Example', '2024-04', 5, 'Short, not a favourite.' ),
	) );
	$a = TWD_SK_Shop_Reviews_Show::single_review( 10 );
	$b = TWD_SK_Shop_Reviews_Show::single_review( 11 );
	twd_sk_true( $a['name'] !== $b['name'], 'different pieces, different reviews' );
	twd_sk_true( in_array( $a['name'], array( 'Ann Example', 'Bob Example' ), true ) );
	twd_sk_eq( $a, TWD_SK_Shop_Reviews_Show::single_review( 12 ), 'the same number always gives the same review' );
	global $product;
	$product = new Twd_Fake_Product( 10 );
	ob_start();
	TWD_SK_Shop_Reviews_Show::product_review();
	twd_sk_has( 'twd-sk-reviews--single', ob_get_clean(), 'on by default' );
	update_option( TWD_SK_Shop_Reviews::OPT_PRODUCT, 'no' );
	ob_start();
	TWD_SK_Shop_Reviews_Show::product_review();
	twd_sk_eq( '', ob_get_clean() );
	$product = null;
} );

twd_sk_test( 'shop reviews: the shortcode picks the style and loads the stylesheet with the shop colour', function () {
	twd_rv_seed( array( array( 'Etsy', 'Ann Example', '2024-01', 5, 'Short one.', true ) ) );
	update_option( 'twd_sk_shop_colour', '#442e54' );
	twd_sk_has( 'twd-sk-reviews--strip', TWD_SK_Shop_Reviews_Show::shortcode( array() ) );
	twd_sk_has( 'twd-sk-reviews--single', TWD_SK_Shop_Reviews_Show::shortcode( array( 'style' => 'single' ) ) );
	twd_sk_has( 'twd-sk-reviews--wall', TWD_SK_Shop_Reviews_Show::shortcode( array( 'style' => 'wall' ) ) );
	twd_sk_true( ! empty( $GLOBALS['twd_stub']['styles']['twd-site-kit-reviews']['enqueued'] ) );
	twd_sk_has( '.twd-sk-reviews{--twd-sk-rv-ink:#442e54}', TWD_SK_Shop_Reviews_Show::inline_css( 'https://example.test/f/' ) );
	twd_sk_has( "font-family:'twd-sk-shop-serif'", TWD_SK_Shop_Reviews_Show::inline_css( 'https://example.test/f/' ) );
	twd_stub_add_post( 400, 'page', 'Hello [twd_reviews style="wall"]' );
	twd_sk_true( TWD_SK_Shop_Reviews_Show::page_uses( 400 ) );
	twd_stub_add_post( 401, 'page', 'Hello' );
	twd_sk_true( ! TWD_SK_Shop_Reviews_Show::page_uses( 401 ) );
} );

// -- The rules -----------------------------------------------------------------------------------

twd_sk_test( 'shop reviews rules: no structured data, a scoped stylesheet with no web address, module only', function () {
	foreach ( glob( ABSPATH . 'modules/shop/includes/class-twd-sk-shop-reviews*.php' ) as $file ) {
		$src = file_get_contents( $file );
		foreach ( array( 'ld+json', 'schema.org', 'itemprop', 'itemtype', 'aggregateRating', 'wp_remote_', 'curl_' ) as $bad ) {
			twd_sk_hasnt( $bad, $src, basename( $file ) . ': ' . $bad );
		}
		twd_sk_true( 0 === preg_match( '#<[a-z][^>]*\sstyle=#i', $src ), basename( $file ) . ': no style attribute in any tag' );
		twd_sk_hasnt( "\xE2\x80\x94", $src );
	}
	$css  = file_get_contents( ABSPATH . 'modules/shop/assets/twd-site-kit-reviews.css' );
	twd_sk_hasnt( 'http', $css );
	twd_sk_hasnt( '@import', $css );
	$body = preg_replace( '#/\*.*?\*/#s', '', $css );
	$body = preg_replace( '#@media[^{]+\{#', '', $body );
	preg_match_all( '#([^{}]+)\{#', $body, $m );
	foreach ( $m[1] as $sel ) {
		foreach ( explode( ',', $sel ) as $one ) {
			$one = trim( $one );
			if ( '' !== $one ) {
				twd_sk_true( 0 === strpos( $one, '.twd-sk-reviews' ), 'scoped: ' . $one );
			}
		}
	}
	// Nothing outside the module knows about reviews, so a site without the module has none.
	foreach ( array_merge( glob( ABSPATH . 'includes/*.php' ), array( ABSPATH . 'twd-site-kit.php' ) ) as $file ) {
		twd_sk_hasnt( 'twd_reviews', file_get_contents( $file ), basename( $file ) );
		twd_sk_hasnt( 'twd_sk_review', file_get_contents( $file ), basename( $file ) );
	}
	twd_sk_hasnt( 'twd_reviews', implode( ',', array_keys( TWD_SK_Registry::allowed_shortcodes() ) ), 'kit pages use it through an Embed section' );
	$load = file_get_contents( ABSPATH . 'modules/shop/load.php' );
	twd_sk_has( 'TWD_SK_Shop_Reviews_Show::init();', $load );
} );

twd_sk_test( 'shop reviews settings: added to the Site Kit shop tools section only', function () {
	twd_sk_eq( array( 'x' ), TWD_SK_Shop_Reviews_Admin::settings( array( 'x' ), 'general' ) );
	$ours = TWD_SK_Shop_Reviews_Admin::settings( array( array( 'id' => 'a' ) ), 'twd_sk_shop' );
	$ids  = array_map( function ( $s ) {
		return isset( $s['id'] ) ? $s['id'] : '';
	}, $ours );
	foreach ( array( 'twd_sk_shop_reviews_page', 'twd_sk_shop_reviews_names', 'twd_sk_shop_reviews_product', 'twd_sk_shop_reviews_etsy_total', 'twd_sk_shop_reviews_ebay_link', 'twd_sk_shop_reviews_words' ) as $want ) {
		twd_sk_true( in_array( $want, $ids, true ), $want );
	}
	twd_sk_eq( 'a', $ids[0], 'the shop tools settings stay first' );
	$product = array_values( array_filter( $ours, function ( $s ) {
		return isset( $s['id'] ) && 'twd_sk_shop_reviews_product' === $s['id'];
	} ) )[0];
	twd_sk_eq( 'yes', $product['default'] );
} );
