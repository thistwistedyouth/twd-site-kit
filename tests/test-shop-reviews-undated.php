<?php
/**
 * Shop reviews, 0.21.0-test5: "Undated" reviews (the platform gives no date and nobody can say),
 * and a later file that finds the date fills it in instead of adding a second review. Uses
 * twd_rv_csv() from test-shop-reviews.php. All reviews here are made up.
 */

twd_sk_test( 'shop reviews undated: Undated is kept with no date, shown with none, sorted last; blank and TO CONFIRM still refused', function () {
	foreach ( array( 'Undated', 'undated', ' UNDATED ' ) as $word ) {
		$c = TWD_SK_Shop_Reviews::check( array( 'platform' => 'eBay', 'name' => 'g***h', 'date' => $word, 'stars' => 'Positive', 'text' => 'Well packed.' ) );
		twd_sk_true( isset( $c['review'] ), $word );
		twd_sk_true( true === $c['review']['undated'] && '' === $c['review']['month'], $word );
	}
	twd_sk_has( 'no date', TWD_SK_Shop_Reviews::check( array( 'platform' => 'eBay', 'name' => 'g***h', 'date' => ' ', 'stars' => 'Positive', 'text' => 'x' ) )['error'] );
	twd_sk_has( 'TO CONFIRM', TWD_SK_Shop_Reviews::check( array( 'platform' => 'eBay', 'name' => 'g***h', 'date' => 'TO CONFIRM', 'stars' => 'Positive', 'text' => 'x' ) )['error'] );
	foreach ( array( array( 'Undated one', 'Undated' ), array( 'Old', '2019-02' ), array( 'Year', '2026' ), array( 'New', '2026-05' ) ) as $row ) {
		$c = TWD_SK_Shop_Reviews::check( array( 'platform' => 'eBay', 'name' => $row[0], 'date' => $row[1], 'stars' => 'Positive', 'text' => 'Words of ' . $row[0], 'fav' => true ) );
		TWD_SK_Shop_Reviews::save( $c['review'] );
	}
	$all = TWD_SK_Shop_Reviews::all();
	twd_sk_eq( array( 'New', 'Year', 'Old', 'Undated one' ), array_map( function ( $r ) {
		return $r['name'];
	}, $all ) );
	$undated = $all[3];
	twd_sk_true( $undated['undated'] );
	$card = TWD_SK_Shop_Reviews_Show::card( $undated );
	twd_sk_hasnt( 'twd-sk-reviews__when', $card, 'no date on the card' );
	twd_sk_has( 'Words of Undated one', $card );
	twd_sk_has( 'Undated one', implode( ',', array_map( function ( $r ) {
		return $r['name'];
	}, TWD_SK_Shop_Reviews_Show::strip_reviews( 4 ) ) ), 'an undated favourite can be in the strip' );
} );

twd_sk_test( 'shop reviews undated: an undated review can be the single review on a product page', function () {
	$c = TWD_SK_Shop_Reviews::check( array( 'platform' => 'eBay', 'name' => 'i***j', 'date' => 'Undated', 'stars' => 'Positive', 'text' => 'Beautiful, thank you.', 'fav' => true ) );
	TWD_SK_Shop_Reviews::save( $c['review'] );
	twd_sk_eq( 'i***j', TWD_SK_Shop_Reviews_Show::single_review( 7 )['name'] );
} );

twd_sk_test( 'shop reviews undated: a later file with the date fills it in, keeping the ticks; nothing is added twice', function () {
	$first = twd_rv_csv( array(
		'Platform,Name,Date,Stars,Review,Item,Link',
		'eBay,k***l,Undated,Positive,"Gorgeous ring, thank you.",,',
		'eBay,m***n,Undated,Positive,Quick post.,,',
		'eBay,k***l,undated,Positive,"Gorgeous ring,  thank you.",,',
	) );
	$r = TWD_SK_Shop_Reviews_Import::run( $first, false );
	twd_sk_eq( array( 'added' => 2, 'updated' => 0, 'there' => 1, 'refused' => 0 ), $r['counts'], 'the same undated review twice in one file' );
	$k = array_values( array_filter( TWD_SK_Shop_Reviews::all(), function ( $x ) {
		return 'k***l' === $x['name'];
	} ) )[0];
	update_post_meta( $k['id'], TWD_SK_Shop_Reviews::META . 'fav', 'yes' );
	twd_sk_eq( array( 'added' => 0, 'updated' => 0, 'there' => 3, 'refused' => 0 ), TWD_SK_Shop_Reviews_Import::run( $first, false )['counts'], 'the undated file again: nothing added' );

	$later = twd_rv_csv( array(
		'Platform,Name,Date,Stars,Review,Item,Link',
		'eBay,k***l,2025,Positive,"Gorgeous ring, thank you.",,',
		'eBay,m***n,Undated,Positive,Quick post.,,',
		'eBay,o***p,2026-02,Positive,New one.,,',
	) );
	$check = TWD_SK_Shop_Reviews_Import::run( $later, true );
	twd_sk_eq( array( 'added' => 1, 'updated' => 1, 'there' => 1, 'refused' => 0 ), $check['counts'] );
	twd_sk_eq( 'ready-date', $check['rows'][0][1] );
	twd_sk_true( TWD_SK_Shop_Reviews::read( $k['id'] )['undated'], 'Check saves nothing' );
	$done = TWD_SK_Shop_Reviews_Import::run( $later, false );
	twd_sk_eq( array( 'added' => 1, 'updated' => 1, 'there' => 1, 'refused' => 0 ), $done['counts'] );
	twd_sk_has( 'Date filled in on the undated review already kept: 2025.', $done['rows'][0][3] );
	$after = TWD_SK_Shop_Reviews::read( $k['id'] );
	twd_sk_eq( '2025', $after['month'] );
	twd_sk_true( false === $after['undated'] );
	twd_sk_true( $after['fav'], 'the favourite tick is kept' );
	twd_sk_eq( 3, count( TWD_SK_Shop_Reviews::all() ), 'filled in, not added' );
	$again = TWD_SK_Shop_Reviews_Import::run( $later, false );
	twd_sk_eq( array( 'added' => 0, 'updated' => 0, 'there' => 3, 'refused' => 0 ), $again['counts'], 'the dated file again: nothing changes' );
	twd_sk_eq( 0, TWD_SK_Shop_Reviews_Import::run( $first, false )['counts']['added'], 'the old undated file again: no undated copy comes back' );
	twd_sk_has( 'Imported: 0 added, 0 dates filled in', TWD_SK_Shop_Reviews_Admin::report_html( $again ) );
} );

twd_sk_test( 'shop reviews undated: dated reviews keep the old duplicate rule (a different date is a different review)', function () {
	$csv = twd_rv_csv( array(
		'Platform,Name,Date,Stars,Review,Item,Link',
		'Etsy,Ann Example,2024-05,5,Lovely.,,',
		'Etsy,Ann Example,2025-07,5,Lovely.,,',
		'Etsy,Ann Example,May 2024,5,Lovely.,,',
	) );
	twd_sk_eq( array( 'added' => 2, 'updated' => 0, 'there' => 1, 'refused' => 0 ), TWD_SK_Shop_Reviews_Import::run( $csv, false )['counts'] );
} );
