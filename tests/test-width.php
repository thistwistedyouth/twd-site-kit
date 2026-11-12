<?php
// Width (12 Nov 2026, from the pilot site's Contact page): a Look choice on every section for how wide its words run, because a
// section built at the wide container sat out of line with the page title and the form built narrow. Narrow (the reading width a
// title or a form uses), as built, or Wide, from two shared classes acting on the section's content column. As built is no class,
// so no existing section changes.

twd_sk_test( 'width: every kind of section offers Width (as built, Narrow, Wide), and as built is no class', function () {
	$keys = array( '', 'twd-sk-width-narrow', 'twd-sk-width-wide' );
	$n    = 0;
	foreach ( TWD_SK_Registry::all() as $id => $c ) {
		$o = TWD_SK_Looks::all_options( $id );
		twd_sk_true( isset( $o['width'] ), $id . ' has Width' );
		twd_sk_eq( $keys, array_keys( $o['width']['choices'] ), $id );
		twd_sk_eq( 'choice', $o['width']['kind'], $id );
		$n++;
	}
	twd_sk_true( $n >= 10, 'checked every component (' . $n . ')' );
	twd_sk_eq( 'As built', TWD_SK_Looks::width_option()['choices'][''] );
	twd_sk_true( in_array( 'width', array_map( function ( $i ) {
		return $i['key'];
	}, TWD_SK_Looks::config()['shared'] ), true ), 'it goes to the browser once, with the other shared options' );
} );

twd_sk_test( 'width: the choice adds or takes away one shared class on the section and nothing else, and the sanitiser keeps it', function () {
	$text = array( 'twd-sk-text', 'twd-sk-text--prose', 'twd-sk-tone-base' );
	twd_sk_eq( array( 'twd-sk-text', 'twd-sk-text--prose', 'twd-sk-tone-base', 'twd-sk-width-narrow' ), TWD_SK_Looks::classes_for( 'text', $text, null, array( 'width' => 'twd-sk-width-narrow' ) ) );
	twd_sk_eq( array( 'twd-sk-text', 'twd-sk-text--prose', 'twd-sk-tone-base', 'twd-sk-width-wide' ), TWD_SK_Looks::classes_for( 'text', $text, null, array( 'width' => 'twd-sk-width-wide' ) ) );
	twd_sk_eq( $text, TWD_SK_Looks::classes_for( 'text', array_merge( $text, array( 'twd-sk-width-wide' ) ), null, array( 'width' => '' ) ), 'as built takes the class away' );
	$contact = array( 'twd-sk-contact', 'twd-sk-tone-tint' );
	twd_sk_eq( array( 'twd-sk-contact', 'twd-sk-tone-tint', 'twd-sk-width-narrow' ), TWD_SK_Looks::classes_for( 'contact', $contact, null, array( 'width' => 'twd-sk-width-narrow' ) ), 'a contact section too' );
	$shared = TWD_SK_Registry::shared_classes();
	twd_sk_true( in_array( 'twd-sk-width-narrow', $shared, true ) && in_array( 'twd-sk-width-wide', $shared, true ) );
	$out = TWD_SK_Sanitizer::clean( '<section class="twd-sk-text twd-sk-width-narrow"><div class="twd-sk-inner"><h2 class="twd-sk-title">Heading</h2></div></section>' );
	twd_sk_has( 'twd-sk-width-narrow', $out );
	$bad = TWD_SK_Looks::classes_for( 'text', $text, null, array( 'width' => 'twd-sk-width-huge' ) );
	twd_sk_true( is_wp_error( $bad ) || $bad === $text, 'a value that is not on the list adds nothing' );
} );

twd_sk_test( 'width css: from the container tokens, on the content column only, after the container rules, and the old rules are untouched', function () {
	$src = file_get_contents( ABSPATH . 'tools/kit.src.css' );
	twd_sk_has( '.twd-sk-width-narrow > .twd-sk-inner { max-width: $container-narrow; }', $src );
	twd_sk_has( '.twd-sk-width-wide > .twd-sk-inner { max-width: calc(#{$container} * 1.25); }', $src );
	twd_sk_true( strpos( $src, '.twd-sk-width-narrow >' ) > strpos( $src, '.twd-sk-inner--narrow { max-width: $container-narrow; }' ), 'after the container rules' );
	twd_sk_has( '.twd-sk-inner--narrow { max-width: $container-narrow; }', $src, 'the old narrow rule is as it was' );
	$css = file_get_contents( ABSPATH . 'assets/twd-site-kit.css' );
	twd_sk_has( 'max-width: var(--twd-site-container-narrow)', $css );
	twd_sk_has( 'max-width: calc(var(--twd-site-container) * 1.25)', $css );
} );
