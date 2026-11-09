<?php
/**
 * Shop reviews, 0.21.0-test6: for undated reviews the item is part of the match, because the same
 * buyer can leave the same words for two pieces. Uses twd_rv_csv() from test-shop-reviews.php.
 * All reviews here are made up.
 */

twd_sk_test( 'shop reviews item: two undated rows with the same buyer and words but different items are both kept', function () {
	$csv = twd_rv_csv( array(
		'Platform,Name,Date,Stars,Review,Item,Link',
		'eBay,q***r,Undated,Positive,Great seller A+++,Gold pendant,',
		'eBay,q***r,Undated,Positive,Great seller A+++,Silver bracelet,',
		'eBay,q***r,Undated,Positive,Great seller A+++,gold  PENDANT,',
		'eBay,s***t,Undated,Positive,Thank you,,',
		'eBay,s***t,Undated,Positive,Thank you,,',
	) );
	$r = TWD_SK_Shop_Reviews_Import::run( $csv, false );
	twd_sk_eq( array( 'added' => 3, 'updated' => 0, 'there' => 2, 'refused' => 0 ), $r['counts'], 'item case and spacing do not matter; with no item, as before' );
	twd_sk_eq( 'there', $r['rows'][2][1] );
	twd_sk_eq( 'there', $r['rows'][4][1] );
	twd_sk_eq( array( 'added' => 0, 'updated' => 0, 'there' => 5, 'refused' => 0 ), TWD_SK_Shop_Reviews_Import::run( $csv, false )['counts'], 'importing again adds nothing' );
} );

twd_sk_test( 'shop reviews item: a later dated row fills in the date on the undated review for the same item only', function () {
	TWD_SK_Shop_Reviews_Import::run( twd_rv_csv( array(
		'Platform,Name,Date,Stars,Review,Item,Link',
		'eBay,q***r,Undated,Positive,Great seller A+++,Gold pendant,',
		'eBay,q***r,Undated,Positive,Great seller A+++,Silver bracelet,',
	) ), false );
	$later = twd_rv_csv( array(
		'Platform,Name,Date,Stars,Review,Item,Link',
		'eBay,q***r,2025,Positive,Great seller A+++,Silver bracelet,',
	) );
	twd_sk_eq( array( 'added' => 0, 'updated' => 1, 'there' => 0, 'refused' => 0 ), TWD_SK_Shop_Reviews_Import::run( $later, false )['counts'] );
	$by = array();
	foreach ( TWD_SK_Shop_Reviews::all() as $x ) {
		$by[ $x['item'] ] = $x;
	}
	twd_sk_eq( '2025', $by['Silver bracelet']['month'] );
	twd_sk_true( $by['Gold pendant']['undated'], 'the pendant stays undated' );
	twd_sk_eq( 2, count( $by ) );
	$noitem = twd_rv_csv( array(
		'Platform,Name,Date,Stars,Review,Item,Link',
		'eBay,q***r,2024,Positive,Great seller A+++,,',
	) );
	$res = TWD_SK_Shop_Reviews_Import::run( $noitem, true );
	twd_sk_eq( 0, $res['counts']['updated'], 'a dated row with no item does not guess which piece it was' );
} );

twd_sk_test( 'shop reviews item: the item counts only for undated reviews; dated keys are as before', function () {
	$a = TWD_SK_Shop_Reviews::check( array( 'platform' => 'Etsy', 'name' => 'Ann Example', 'date' => '2024-05', 'stars' => '5', 'text' => 'Lovely.', 'item' => 'Ring' ) );
	twd_sk_eq( TWD_SK_Shop_Reviews::key( 'etsy', 'Ann Example', '2024-05', 'Lovely.' ), $a['review']['key'] );
	$u = TWD_SK_Shop_Reviews::check( array( 'platform' => 'eBay', 'name' => 'q***r', 'date' => 'Undated', 'stars' => 'Positive', 'text' => 'Lovely.', 'item' => 'Ring' ) );
	twd_sk_true( TWD_SK_Shop_Reviews::key( 'ebay', 'q***r', '', 'Lovely.' ) !== $u['review']['key'] );
	$n = TWD_SK_Shop_Reviews::check( array( 'platform' => 'eBay', 'name' => 'q***r', 'date' => 'Undated', 'stars' => 'Positive', 'text' => 'Lovely.' ) );
	twd_sk_eq( TWD_SK_Shop_Reviews::key( 'ebay', 'q***r', '', 'Lovely.' ), $n['review']['key'], 'no item: the key as before' );
} );
