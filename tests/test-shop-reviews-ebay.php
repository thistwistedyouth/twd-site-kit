<?php
/**
 * Shop reviews, 0.21.0-test4: eBay-style feedback. Rated Positive, Neutral or Negative (a rating
 * kind of its own, never turned into stars), and dated only by year. Uses twd_rv_csv() from
 * test-shop-reviews.php. All reviews here are made up.
 */

twd_sk_test( 'shop reviews ebay: Positive, Neutral and Negative are kept as a rating, never as stars', function () {
	twd_sk_eq( 'positive', TWD_SK_Shop_Reviews::rating( 'Positive' ) );
	twd_sk_eq( 'neutral', TWD_SK_Shop_Reviews::rating( ' neutral ' ) );
	twd_sk_eq( 'negative', TWD_SK_Shop_Reviews::rating( 'Negative feedback' ) );
	twd_sk_eq( '', TWD_SK_Shop_Reviews::rating( 'Great' ) );
	$ok = TWD_SK_Shop_Reviews::check( array( 'platform' => 'eBay', 'name' => 'a***b', 'date' => '2026', 'stars' => 'Positive', 'text' => 'Lovely item, well packed.' ) );
	twd_sk_eq( 'positive', $ok['review']['rating'] );
	twd_sk_eq( 0, $ok['review']['stars'], 'no stars made up' );
	$bad = TWD_SK_Shop_Reviews::check( array( 'platform' => 'eBay', 'name' => 'a***b', 'date' => '2026', 'stars' => 'Great', 'text' => 'Fine.' ) );
	twd_sk_has( 'not a whole number from 1 to 5, or Positive, Neutral or Negative', $bad['error'] );
	$id = TWD_SK_Shop_Reviews::save( $ok['review'] );
	$r  = TWD_SK_Shop_Reviews::read( $id );
	twd_sk_eq( 'positive', $r['rating'] );
	twd_sk_eq( 0, $r['stars'] );
	$card = TWD_SK_Shop_Reviews_Show::card( $r );
	twd_sk_has( '<p class="twd-sk-reviews__rating">Positive feedback</p>', $card );
	twd_sk_hasnt( 'twd-sk-reviews__stars', $card, 'no stars on the card' );
	twd_sk_hasnt( 'out of 5', $card );
	twd_sk_has( '<span class="twd-sk-reviews__when">2026</span>', $card );
} );

twd_sk_test( 'shop reviews ebay: a year on its own is kept and sorts after that year\'s dated reviews; TO CONFIRM is refused', function () {
	twd_sk_eq( '2026', TWD_SK_Shop_Reviews::month( '2026' ) );
	twd_sk_eq( '', TWD_SK_Shop_Reviews::month( '1850' ) );
	twd_sk_eq( '2026', TWD_SK_Shop_Reviews::month_label( '2026' ) );
	$tc = TWD_SK_Shop_Reviews::check( array( 'platform' => 'eBay', 'name' => 'c***d', 'date' => 'TO CONFIRM', 'stars' => 'Positive', 'text' => 'Thanks.' ) );
	twd_sk_has( 'the date says TO CONFIRM', $tc['error'] );
	twd_sk_has( 'no date', TWD_SK_Shop_Reviews::check( array( 'platform' => 'eBay', 'name' => 'c***d', 'date' => '', 'stars' => 'Positive', 'text' => 'Thanks.' ) )['error'] );
	twd_sk_has( 'could not be read', TWD_SK_Shop_Reviews::check( array( 'platform' => 'eBay', 'name' => 'c***d', 'date' => 'Past year', 'stars' => 'Positive', 'text' => 'Thanks.' ) )['error'] );
	foreach ( array( array( 'Year only', '2026' ), array( 'Late', '2026-11' ), array( 'Early', '2026-01' ), array( 'Last year', '2025-12' ) ) as $row ) {
		$c = TWD_SK_Shop_Reviews::check( array( 'platform' => 'Etsy', 'name' => $row[0], 'date' => $row[1], 'stars' => '5', 'text' => 'Review ' . $row[0] ) );
		TWD_SK_Shop_Reviews::save( $c['review'] );
	}
	$order = array_map( function ( $r ) {
		return $r['name'];
	}, TWD_SK_Shop_Reviews::all() );
	twd_sk_eq( array( 'Late', 'Early', 'Year only', 'Last year' ), $order );
} );

twd_sk_test( 'shop reviews ebay: a feedback file imports with a report; TO CONFIRM rows are refused and nothing doubles up', function () {
	$csv = twd_rv_csv( array(
		'Platform,Name,Date,Stars,Review,Item,Link',
		'eBay,a***b,2026,Positive,"Lovely item, well packed.",Garnet ring,https://example.test/fb',
		'eBay,c***d,TO CONFIRM,Positive,Arrived quickly.,Locket,https://example.test/fb',
		'eBay,e***f,2026,Neutral,Slow post.,Brooch,https://example.test/fb',
	) );
	$r = TWD_SK_Shop_Reviews_Import::run( $csv, false );
	twd_sk_eq( array( 'added' => 2, 'updated' => 0, 'there' => 0, 'refused' => 1 ), $r['counts'] );
	twd_sk_eq( 3, $r['rows'][1][0] );
	twd_sk_has( 'TO CONFIRM', $r['rows'][1][3] );
	twd_sk_eq( 0, TWD_SK_Shop_Reviews_Import::run( $csv, false )['counts']['added'], 'importing again adds nothing' );
} );

twd_sk_test( 'shop reviews ebay: only positive feedback (or stars) is used for the strip, the single review and favourites', function () {
	foreach ( array( array( 'Neutral fan', 'Neutral', 'Neutral words.' ), array( 'Negative fan', 'Negative', 'Negative words.' ), array( 'Positive fan', 'Positive', 'Positive words.' ) ) as $i => $row ) {
		$c = TWD_SK_Shop_Reviews::check( array( 'platform' => 'eBay', 'name' => $row[0], 'date' => '2026-0' . ( 3 + $i ), 'stars' => $row[1], 'text' => $row[2], 'fav' => true ) );
		TWD_SK_Shop_Reviews::save( $c['review'] );
	}
	$strip = array_map( function ( $r ) {
		return $r['name'];
	}, TWD_SK_Shop_Reviews_Show::strip_reviews( 4 ) );
	twd_sk_eq( array( 'Positive fan' ), $strip );
	for ( $seed = 0; $seed < 3; $seed++ ) {
		twd_sk_eq( 'Positive fan', TWD_SK_Shop_Reviews_Show::single_review( $seed )['name'] );
	}
	foreach ( TWD_SK_Shop_Reviews::all() as $r ) {
		delete_post_meta( $r['id'], TWD_SK_Shop_Reviews::META . 'fav' );
	}
	twd_sk_eq( array( 'Positive fan' ), array_map( function ( $r ) {
		return $r['name'];
	}, TWD_SK_Shop_Reviews_Show::strip_reviews( 4 ) ), 'with no favourites, the newest positive only' );
	$wall = TWD_SK_Shop_Reviews_Show::wall_html( '', 24, '/' );
	twd_sk_has( 'Neutral feedback', $wall, 'the wall shows every visible review, as rated' );
	twd_sk_has( 'Negative feedback', $wall );
} );
