<?php
// Colours from the logo (0.8.2).

/** A test logo: a transparent background with blocks of colour. Returns the file path. */
function twd_sk_brand_png( $blocks ) {
	if ( ! function_exists( 'imagecreatetruecolor' ) ) {
		return '';
	}
	$img = imagecreatetruecolor( 120, 40 );
	imagealphablending( $img, false );
	imagesavealpha( $img, true );
	imagefill( $img, 0, 0, imagecolorallocatealpha( $img, 0, 0, 0, 127 ) );
	$x = 0;
	foreach ( $blocks as $b ) {
		list( $hex, $w ) = $b;
		$c = TWD_SK_Brand::rgb( $hex );
		imagefilledrectangle( $img, $x, 0, $x + $w - 1, 39, imagecolorallocatealpha( $img, $c[0], $c[1], $c[2], 0 ) );
		$x += $w;
	}
	$file = tempnam( sys_get_temp_dir(), 'twdsk' ) . '.png';
	imagepng( $img, $file );
	return $file;
}
function twd_sk_brand_logo( $file ) {
	$GLOBALS['twd_stub']['attachments'][50] = array( 'is_image' => true, 'url' => 'https://example.test/wp-content/uploads/logo.png', 'file' => $file );
	TWD_SK_Profile::save( array( 'logo_id' => 50 ) );
}

twd_sk_test( 'brand: the main colours of a picture, transparent and white areas ignored, the biggest colourful one first', function () {
	$file = twd_sk_brand_png( array( array( '#1E6E8C', 70 ), array( '#FFFFFF', 20 ), array( '#E07A2E', 30 ) ) );
	if ( '' === $file ) {
		twd_sk_note( 'Logo colour tests skipped: GD is not available here.' );
		return;
	}
	$c = TWD_SK_Brand::colours_of_file( $file );
	twd_sk_true( is_array( $c ) && count( $c ) >= 2 );
	$first = TWD_SK_Brand::rgb( $c[0]['hex'] );
	twd_sk_true( $first[2] > $first[0], 'blue first: ' . $c[0]['hex'] );
	foreach ( $c as $one ) {
		twd_sk_true( min( TWD_SK_Brand::rgb( $one['hex'] ) ) <= 235, 'no white' );
	}
	unlink( $file );
	$blank = twd_sk_brand_png( array() );
	twd_sk_is_error( 'twd_sk_bad_input', TWD_SK_Brand::colours_of_file( $blank ) );
	unlink( $blank );
} );

twd_sk_test( 'brand: an SVG logo gives the colours written in it', function () {
	$c = TWD_SK_Brand::colours_of_svg( '<svg><path fill="#0B5" /><path fill="#00BB55"/><rect style="fill:#c0392b"/><path fill="#FFFFFF"/></svg>' );
	twd_sk_eq( '#00BB55', $c[0]['hex'], 'short and long forms counted together' );
	twd_sk_eq( '#C0392B', $c[1]['hex'] );
	twd_sk_eq( 2, count( $c ) );
	twd_sk_is_error( 'twd_sk_bad_input', TWD_SK_Brand::colours_of_svg( '<svg></svg>' ) );
} );

twd_sk_test( 'brand: every proposed colour passes the same contrast check the Site tab saves with, light or dark logos alike', function () {
	$base = TWD_SK_Packs::active_tokens();
	foreach ( array( '#FFE14D', '#1E6E8C', '#000000', '#7FD3FF', '#C0392B', '#BBBBBB' ) as $hex ) {
		$p = TWD_SK_Brand::propose( array( array( 'hex' => $hex, 'share' => 1 ), array( 'hex' => '#E07A2E', 'share' => 0.2 ) ), $base );
		$check = TWD_SK_Contrast::check( array_merge( $base, $p['tokens'] ) );
		twd_sk_eq( array(), $check['blocking'], 'logo ' . $hex );
		twd_sk_true( isset( $p['tokens']['color-primary'] ), 'a button colour for ' . $hex );
		foreach ( $p['tokens'] as $name => $v ) {
			twd_sk_true( 1 === preg_match( '/^#[0-9A-F]{6}$/', $v ), $name . ' is a plain colour' );
			twd_sk_true( in_array( $name, TWD_SK_Site::editable_names(), true ), $name . ' is a token the Site tab can change' );
		}
		$heading = TWD_SK_Contrast::ratio( $p['tokens']['color-heading'], $base['color-bg'] );
		twd_sk_true( $heading >= 4.5, 'headings readable for ' . $hex . ' (' . $heading . ')' );
	}
} );

twd_sk_test( 'brand rest and cli: proposed from the logo, never saved by the route; the command saves only with --apply', function () {
	$file = twd_sk_brand_png( array( array( '#1E6E8C', 100 ) ) );
	if ( '' === $file ) {
		return;
	}
	twd_sk_site_caps();
	$e = TWD_SK_REST::get_site_brand( twd_sk_rest_req() );
	twd_sk_has( 'There is no logo yet', $e->get_error_message() );
	twd_sk_brand_logo( $file );
	$before = TWD_SK_Packs::overrides();
	$r      = TWD_SK_REST::get_site_brand( twd_sk_rest_req() );
	twd_sk_true( isset( $r['tokens']['color-primary'] ) );
	twd_sk_eq( $before, TWD_SK_Packs::overrides(), 'nothing saved' );
	require_once __DIR__ . '/stub-wpcli.php';
	WP_CLI::reset();
	( new TWD_SK_CLI() )->brand( array(), array() );
	twd_sk_has( 'Main colours:', WP_CLI::all() );
	twd_sk_eq( $before, TWD_SK_Packs::overrides() );
	( new TWD_SK_CLI() )->brand( array(), array( 'apply' => true ) );
	twd_sk_eq( $r['tokens']['color-primary'], TWD_SK_Packs::overrides()['color-primary'] );
	twd_sk_has( 'Take colours from the logo', file_get_contents( ABSPATH . 'assets/twd-site-kit-editor-site.js' ) );
	twd_sk_has( "api('GET', '/site/brand')", file_get_contents( ABSPATH . 'assets/twd-site-kit-editor-site.js' ) );
	unlink( $file );
} );

twd_sk_test( 'brand in the setup build: colours from the logo slot are applied when ticked, and not otherwise', function () {
	$file = twd_sk_brand_png( array( array( '#1E6E8C', 100 ) ) );
	if ( '' === $file ) {
		return;
	}
	$GLOBALS['twd_stub']['attachments'][50] = array( 'is_image' => true, 'url' => 'https://example.test/wp-content/uploads/logo.png', 'file' => $file );
	TWD_SK_Build::save_draft( array( 'pictures' => array( 'slots' => array( 'logo' => array( 'id' => 50 ) ) ) ) );
	TWD_SK_Build::run_step( 'settings' );
	twd_sk_eq( array(), TWD_SK_Packs::overrides(), 'not ticked: the style is untouched' );
	TWD_SK_Build::save_draft( array( 'brand' => true ) );
	twd_sk_true( TWD_SK_Build::draft()['brand'] );
	$r = TWD_SK_Build::run_step( 'settings' );
	twd_sk_has( 'Colours taken from the logo', implode( ' ', $r['report'] ) );
	twd_sk_true( isset( TWD_SK_Packs::overrides()['color-primary'] ) );
	twd_sk_has( "brand: !!(ui.brand && ui.brand.checked)", file_get_contents( ABSPATH . 'assets/twd-site-kit-setup.js' ) );
	unlink( $file );
} );
