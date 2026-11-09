<?php
/**
 * The Shop module, test build 2: photos in the Edit product window, and the tidier product page.
 * Uses the fake WooCommerce product and helpers in test-shop.php (every test file loads before any test runs).
 */

// -- Photos ----------------------------------------------------------------------------------

twd_sk_test( 'shop photos: media library pictures only, the main photo never repeated in the gallery, photos the product has are kept', function () {
	$p = twd_shop_setup( array( 'image_id' => 70, 'gallery_image_ids' => array( 71 ) ) );
	$GLOBALS['twd_stub']['attachments'][501] = array( 'is_image' => true, 'url' => 'https://example.test/a.jpg' );
	$GLOBALS['twd_stub']['attachments'][502] = array( 'is_image' => true, 'url' => 'https://example.test/b.jpg' );
	$GLOBALS['twd_stub']['attachments'][503] = array( 'is_image' => false, 'url' => 'https://example.test/c.pdf' );
	$ok = TWD_SK_Shop_Product::validate( twd_shop_input( array( 'image' => 501, 'gallery' => array( 501, 502, 503, 71, 999 ) ) ), $p );
	twd_sk_eq( 501, $ok['fields']['image'] );
	twd_sk_eq( array( 502, 71 ), $ok['fields']['gallery'], 'main photo, a PDF and an unknown number left out; the old gallery photo kept' );
	twd_sk_is_error( 'twd_sk_shop_invalid', TWD_SK_Shop_Product::validate( twd_shop_input( array( 'image' => 503 ) ), $p ), 'a PDF as main photo' );
	$kept = TWD_SK_Shop_Product::validate( twd_shop_input( array( 'image' => 70 ) ), $p );
	twd_sk_eq( 70, $kept['fields']['image'], 'the photo it already has' );
	TWD_SK_Shop_Rest::save( 42, twd_shop_input( array( 'image' => 501, 'gallery' => array( 70, 502 ) ) ), twd_shop_base( $p ) );
	twd_sk_eq( 501, $p->d['image_id'] );
	twd_sk_eq( array( 70, 502 ), $p->d['gallery_image_ids'] );
	TWD_SK_Shop_Rest::undo( 42 );
	twd_sk_eq( 70, $p->d['image_id'], 'Undo brings the old main photo back' );
	twd_sk_eq( array( 71 ), $p->d['gallery_image_ids'] );
} );

twd_sk_test( 'shop photos: a version kept before photos were in the window leaves the photos alone', function () {
	$p   = twd_shop_setup( array( 'image_id' => 70 ) );
	$old = TWD_SK_Shop_Product::fields( $p );
	unset( $old['image'], $old['gallery'] );
	$old['price'] = '300';
	TWD_SK_Shop_History::record( 42, $old, 'From test build 1' );
	TWD_SK_Shop_Rest::restore( 42, 1 );
	twd_sk_eq( '300', $p->d['regular_price'] );
	twd_sk_eq( 70, $p->d['image_id'] );
	twd_sk_true( ! in_array( 'image_id', $p->calls, true ) && ! in_array( 'gallery_image_ids', $p->calls, true ) );
	$read = TWD_SK_Shop_Product::read( 42 );
	twd_sk_true( is_object( $read['photos'] ) );
} );

// -- The product page layout -------------------------------------------------------------------

function twd_shop_layout_product() {
	$period = new WC_Product_Attribute();
	$period->set_name( 'Period' );
	$period->set_options( array( 'Victorian, about 1870' ) );
	$metal = new WC_Product_Attribute();
	$metal->set_name( 'Metal' );
	$metal->set_options( array( '15ct gold' ) );
	$hidden = new WC_Product_Attribute();
	$hidden->set_name( 'Note' );
	$hidden->set_options( array( 'Not shown' ) );
	$hidden->set_visible( false );
	$p = twd_shop_setup( array(
		'attributes'  => array( 'period' => $period, 'metal' => $metal, 'note' => $hidden ),
		'sku'         => 'GJ-12',
		'description' => '<p>The story <img src="https://example.test/x.jpg" alt="x"></p><h2>Condition</h2><p>Light wear.</p><h3>History</h3><p>From a collection.</p>',
	) );
	update_post_meta( 42, '_twd_sk_shop_labels', array( 'Period', 'Metal' ) );
	return $p;
}

twd_sk_test( 'shop layout: off unless ticked; the colour is a #rrggbb or the default; the serif is the kit own file', function () {
	twd_sk_true( false === TWD_SK_Shop_Layout::on() );
	update_option( 'twd_sk_shop_layout', 'yes' );
	twd_sk_true( TWD_SK_Shop_Layout::on() );
	twd_sk_eq( '#1f3a33', TWD_SK_Shop_Layout::colour() );
	update_option( 'twd_sk_shop_colour', '#442E54' );
	twd_sk_eq( '#442e54', TWD_SK_Shop_Layout::colour() );
	update_option( 'twd_sk_shop_colour', 'red;}body{display:none' );
	twd_sk_eq( '#1f3a33', TWD_SK_Shop_Layout::colour(), 'nothing but a colour gets in' );
	$css = TWD_SK_Shop_Layout::inline_css( 'https://example.test/fonts/' );
	twd_sk_has( 'cormorant-garamond-latin-wght-normal.woff2', $css );
	twd_sk_has( '--twd-sk-shop-ink:#1f3a33', $css );
	twd_sk_hasnt( 'googleapis', $css );
} );

twd_sk_test( 'shop layout: the period line, labels (not the period twice, then Reduced and Sold) and rows only from visible details', function () {
	$p    = twd_shop_layout_product();
	$rows = TWD_SK_Shop_Layout::rows( $p );
	twd_sk_eq( array( 'Period', 'Metal' ), array_column( $rows, 'label' ) );
	twd_sk_eq( 'Victorian, about 1870', TWD_SK_Shop_Layout::date_row( $rows )['value'] );
	twd_sk_eq( array( '15ct gold' ), array_column( TWD_SK_Shop_Layout::labels( $p ), 'text' ) );
	$p->d['stock_status'] = 'outofstock';
	$p->d['sale_price']   = '300';
	twd_sk_eq( array( '15ct gold', 'Reduced', 'Sold' ), array_column( TWD_SK_Shop_Layout::labels( $p ), 'text' ) );
	twd_sk_eq( 'Sold', TWD_SK_Shop_Layout::availability( 'Out of stock', $p ) );
	$p->d['stock_status'] = 'instock';
	twd_sk_eq( 'In stock', TWD_SK_Shop_Layout::availability( 'In stock', $p ) );
} );

twd_sk_test( 'shop layout: the story keeps pictures, the card holds the details and the stock number, later sections and delivery open below', function () {
	$p = twd_shop_layout_product();
	update_option( 'twd_sk_shop_delivery', 'Sent insured. <script>x()</script>' );
	$html = TWD_SK_Shop_Layout::piece_html( $p );
	twd_sk_has( 'id="twd-sk-shop-piece"', $html );
	twd_sk_has( '<h2 class="twd-sk-shop-piece__title">About this piece</h2>', $html );
	twd_sk_has( '<img src="https://example.test/x.jpg"', $html, 'the story is shown as written' );
	twd_sk_has( 'No. GJ-12', $html );
	twd_sk_has( '<dt>Period</dt><dd>Victorian, about 1870</dd>', $html );
	twd_sk_hasnt( 'Not shown', $html );
	twd_sk_has( '<details class="twd-sk-shop-fold" open><summary>Condition</summary>', $html );
	twd_sk_has( '<summary>History</summary>', $html );
	twd_sk_has( '<summary>Delivery and returns</summary>', $html );
	twd_sk_has( 'Sent insured.', $html );
	twd_sk_hasnt( '<script', $html );
	twd_sk_eq( array(), TWD_SK_Shop_Text::split_raw( '' ) );
} );

twd_sk_test( 'shop layout: a long short summary shows as a lead near the price and in full in the story', function () {
	$long = '<p>DESCRIPTION AND CONDITION</p><p>' . str_repeat( 'A fine and rare piece with its original box and a good story to tell. ', 10 ) . '</p>';
	$p    = twd_shop_setup( array( 'short_description' => $long, 'description' => '<h2>History</h2><p>Old.</p>' ) );
	twd_sk_true( TWD_SK_Shop_Layout::summary_is_long( $p ) );
	$lead = TWD_SK_Shop_Layout::lead( $long );
	twd_sk_true( 0 === strpos( $lead, 'A fine and rare piece' ), 'the capitals heading line is skipped' );
	twd_sk_true( strlen( $lead ) <= TWD_SK_Shop_Layout::LEAD_LENGTH + 3 );
	twd_sk_has( '...', $lead );
	$html = TWD_SK_Shop_Layout::piece_html( $p );
	twd_sk_has( 'DESCRIPTION AND CONDITION', $html );
	twd_sk_has( '<h3>History</h3>', $html, 'the description follows under its own heading' );
	$short = twd_shop_setup( array( 'short_description' => '<p>Short.</p>' ) );
	twd_sk_true( false === TWD_SK_Shop_Layout::summary_is_long( $short ) );
} );

twd_sk_test( 'shop layout: the stylesheet stays under its body class, with no web address and no long number', function () {
	$css = file_get_contents( ABSPATH . 'modules/shop/assets/twd-site-kit-shop.css' );
	twd_sk_hasnt( 'http', $css );
	twd_sk_hasnt( '@import', $css );
	$body = preg_replace( '#/\*.*?\*/#s', '', $css );
	$body = preg_replace( '#@media[^{]+\{#', '', $body );
	preg_match_all( '#([^{}]+)\{#', $body, $m );
	foreach ( $m[1] as $sel ) {
		foreach ( explode( ',', $sel ) as $one ) {
			$one = trim( $one );
			if ( '' === $one ) {
				continue;
			}
			twd_sk_true( 0 === strpos( $one, 'body.twd-sk-shop-layout' ), 'scoped: ' . $one );
		}
	}
} );
