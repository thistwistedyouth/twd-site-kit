<?php
// 0.19.1: three colours of the site's own, usable by name in the site stylesheet.

twd_sk_test( 'extra colours: every pack defines them, the Site tab lists them, and the site stylesheet may use them by name but still never a typed colour', function () {
	foreach ( array( 'color-extra-1', 'color-extra-2', 'color-extra-3' ) as $name ) {
		twd_sk_true( in_array( $name, TWD_SK_Packs::token_names(), true ), 'in the schema: ' . $name );
		twd_sk_true( in_array( $name, TWD_SK_Site::editable_names(), true ), 'editable: ' . $name );
	}
	$ok = TWD_SK_Css::check( ".c-x-lane {\n\tbackground: var(--twd-site-color-extra-1);\n\tborder: 1px solid var(--twd-site-color-extra-3);\n}\n" );
	twd_sk_true( ! empty( $ok['ok'] ), implode( ' ', $ok['errors'] ) );
	$bad = TWD_SK_Css::check( ".c-x-lane {\n\tbackground: #B77F1F;\n}\n" );
	twd_sk_true( empty( $bad['ok'] ) && ! empty( $bad['errors'] ), 'a typed colour is still refused' );
	$tok = TWD_SK_Packs::validate_tokens( array( 'color-extra-1' => '#B77F1F' ), false );
	twd_sk_eq( array( 'color-extra-1' => '#B77F1F' ), $tok['valid'], 'a hex value is accepted for the token' );
} );
