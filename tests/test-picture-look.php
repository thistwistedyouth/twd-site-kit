<?php
// The picture look (0.15.0): ready-made choices for how pictures are coloured and tinted, set in the Site tab.

twd_sk_test( 'picture look: every ready-made choice is a value the token checker accepts, the first is the do-nothing default, and the tokens are editable', function () {
	$presets = TWD_SK_Site::picture_presets();
	twd_sk_eq( array( 'image-filter', 'image-tint', 'color-frame' ), array_keys( $presets ), 'the frame tint joined in 0.15.0' );
	foreach ( $presets as $token => $list ) {
		twd_sk_true( count( $list ) >= 4, $token . ' has a few choices' );
		foreach ( $list as $p ) {
			twd_sk_true( TWD_SK_Packs::valid_token( $token, $p[0] ), $token . ': ' . $p[0] );
			twd_sk_true( '' !== $p[1] && false === strpos( $p[1], '--' ), 'a plain label for ' . $p[0] );
		}
		twd_sk_eq( TWD_SK_Packs::defaults()[ $token ], $list[0][0], $token . ' starts with the default' );
		twd_sk_eq( count( $list ), count( array_unique( array_map( function ( $p ) {
			return $p[0];
		}, $list ) ) ), 'no choice twice' );
	}
	$names = TWD_SK_Site::editable_names();
	twd_sk_true( in_array( 'image-filter', $names, true ) && in_array( 'image-tint', $names, true ) );
	$kinds = array();
	foreach ( TWD_SK_Site::state()['editable'] as $e ) {
		if ( 'pictures' === $e['group'] ) {
			$kinds[ $e['token'] ] = $e['kind'];
		}
	}
	twd_sk_eq( array( 'image-filter' => 'choice', 'image-tint' => 'choice', 'color-frame' => 'choice' ), $kinds );
	twd_sk_eq( $presets, TWD_SK_Site::state()['presets'], 'the Site tab is given the same list' );
} );

twd_sk_test( 'picture look: saving a choice stores it as an override, prints it, and Reset puts the style\'s own look back; every pack looks as before until a choice is made', function () {
	foreach ( TWD_SK_Packs::packs() as $slug => $pack ) {
		twd_sk_true( isset( $pack['tokens']['image-filter'], $pack['tokens']['image-tint'] ), $slug );
	}
	$before = TWD_SK_Packs::tokens_css( TWD_SK_Packs::active_tokens() );
	$r = TWD_SK_Site::apply( null, array( 'image-filter' => 'grayscale(1)', 'image-tint' => 'rgba(40,70,90,0.12)' ) );
	twd_sk_true( ! is_wp_error( $r ), is_wp_error( $r ) ? $r->get_error_message() : '' );
	twd_sk_eq( array( 'image-filter' => 'grayscale(1)', 'image-tint' => 'rgba(40,70,90,0.12)' ), (array) $r['overrides'] );
	$css = TWD_SK_Packs::tokens_css( TWD_SK_Packs::active_tokens() );
	twd_sk_has( '--twd-site-image-filter:grayscale(1);', $css );
	twd_sk_has( '--twd-site-image-tint:rgba(40,70,90,0.12);', $css );
	// Choosing what the style already has stores nothing.
	TWD_SK_Site::reset();
	$same = TWD_SK_Site::apply( null, array( 'image-filter' => 'none', 'image-tint' => 'transparent' ) );
	twd_sk_eq( array(), (array) $same['overrides'] );
	twd_sk_eq( $before, TWD_SK_Packs::tokens_css( TWD_SK_Packs::active_tokens() ), 'printed exactly as before' );
	TWD_SK_Site::apply( null, array( 'image-filter' => 'sepia(0.25) saturate(0.9)' ) );
	TWD_SK_Site::reset();
	twd_sk_eq( $before, TWD_SK_Packs::tokens_css( TWD_SK_Packs::active_tokens() ) );
} );

twd_sk_test( 'picture look: anything that is not a ready-made kind of value is refused and nothing is saved', function () {
	foreach ( array( 'blur(5px)', 'url(x)', 'grayscale(1) sepia(1) contrast(1) brightness(1)', 'none; color: red', 'drop-shadow(0 0 4px red)' ) as $bad ) {
		$r = TWD_SK_Site::apply( null, array( 'image-filter' => $bad ) );
		twd_sk_is_error( 'twd_sk_bad_tokens', $r, $bad );
	}
	foreach ( array( 'url(x)', 'red; x', 'rgba(300,0,0,0.5)', 'javascript:1' ) as $bad ) {
		twd_sk_is_error( 'twd_sk_bad_tokens', TWD_SK_Site::apply( null, array( 'image-tint' => $bad ) ), $bad );
	}
	twd_sk_eq( array(), TWD_SK_Packs::overrides() );
} );

twd_sk_test( 'picture look: the Site tab script draws the choices from the list it is given, keeps a value the style has that is not on the list, and previews live', function () {
	$js = file_get_contents( ABSPATH . 'assets/twd-site-kit-editor-site.js' );
	foreach ( array( 'function choiceControl(', "item.kind === 'choice' ? choiceControl(item, value)", "pictures: 'Pictures'", 'S.data.presets', "The style\\'s own setting" ) as $needle ) {
		twd_sk_has( $needle, $js );
	}
	twd_sk_hasnt( 'innerHTML', $js );
	// The stylesheet already reads both tokens; nothing new is needed for a visitor.
	$css = file_get_contents( ABSPATH . 'assets/twd-site-kit.css' );
	twd_sk_has( 'filter: var(--twd-site-image-filter);', $css );
	twd_sk_has( 'background-color: var(--twd-site-image-tint)', $css );
} );
