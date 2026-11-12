<?php
// A small picture under the words on phones (12 Nov 2026, from the pilot site's About page): on a phone the picture of the person
// filled the width and sat above the first words. A Look choice on the picture and text layouts makes it about half the column,
// centred, after the words. Phones only; no class is exactly as built. Not offered on the picture band, which has no words beside it.

twd_sk_test( 'phone under: a Look choice on the picture and text layouts, not on the band, one shared class the sanitiser keeps', function () {
	$o = TWD_SK_Looks::options( 'image_text' );
	twd_sk_true( isset( $o['phone_under'] ) );
	twd_sk_eq( array( '', 'twd-sk-phone-under' ), array_keys( $o['phone_under']['choices'] ) );
	twd_sk_eq( 'As built', $o['phone_under']['choices'][''] );
	twd_sk_eq( 'twd-sk-image-text--band', $o['phone_under']['excludes'] );
	twd_sk_true( in_array( 'twd-sk-phone-under', TWD_SK_Registry::shared_classes(), true ) );
	$narrow = array( 'twd-sk-image-text', 'twd-sk-image-text--narrow', 'twd-sk-tone-surface' );
	$on     = TWD_SK_Looks::classes_for( 'image_text', $narrow, null, array( 'phone_under' => 'twd-sk-phone-under' ) );
	twd_sk_true( in_array( 'twd-sk-phone-under', $on, true ) && in_array( 'twd-sk-image-text--narrow', $on, true ), 'added, the layout stays' );
	twd_sk_eq( $narrow, TWD_SK_Looks::classes_for( 'image_text', $on, null, array( 'phone_under' => '' ) ), 'as built takes it away and leaves the rest exactly' );
	$band = array( 'twd-sk-image-text', 'twd-sk-image-text--band' );
	twd_sk_eq( $band, TWD_SK_Looks::classes_for( 'image_text', $band, null, array( 'phone_under' => 'twd-sk-phone-under' ) ), 'not on a picture band' );
	twd_sk_is_error( 'twd_sk_bad_input', TWD_SK_Looks::classes_for( 'image_text', $narrow, null, array( 'phone_under' => 'twd-sk-phone-nowhere' ) ), 'a made-up choice is refused' );
	$html = '<section class="twd-sk-image-text twd-sk-image-text--narrow twd-sk-phone-under"><div class="twd-sk-inner"><div class="twd-sk-image-text__grid"><figure class="twd-sk-image-text__media"><img class="twd-sk-image-text__img" src="/wp-content/uploads/a.jpg" alt="A person"></figure><div class="twd-sk-image-text__body"><p>Words.</p></div></div></div></section>';
	twd_sk_has( 'twd-sk-phone-under', TWD_SK_Sanitizer::clean( $html ) );
} );

twd_sk_test( 'phone under: the browser is told what the option excludes, and hides it on a band', function () {
	$cfg  = TWD_SK_Looks::config();
	$item = null;
	foreach ( $cfg['components']['image_text']['options'] as $opt ) {
		if ( 'phone_under' === $opt['key'] ) {
			$item = $opt;
		}
	}
	twd_sk_true( null !== $item, 'it is in what editors are given' );
	twd_sk_eq( 'twd-sk-image-text--band', $item['excludes'] );
	$js = str_replace( "\r", '', file_get_contents( ABSPATH . 'assets/twd-site-kit-inline.js' ) );
	twd_sk_has( "if (o.excludes && classes.indexOf(o.excludes) > -1) {\n\t\t\treturn false;", $js, 'the Look panel leaves it out of a section that has the excluded class' );
} );

twd_sk_test( 'phone under css: phones only, after the words, about half the column and centred, and nothing above the phone width', function () {
	$src  = file_get_contents( ABSPATH . 'tools/kit.src.css' );
	$at   = strpos( $src, '/* Picture and text, small picture under the words on phones (12 Nov 2026)' );
	twd_sk_true( $at > strpos( $src, '/* Picture band, whole picture on phones (12 Nov 2026)' ), 'at the end, so it wins over the layout rules' );
	$tail = str_replace( "\r", '', substr( $src, $at ) );
	twd_sk_has( "@media (max-width: 768px) {\n  .twd-sk-phone-under .twd-sk-image-text__media { order: 2; width: 60%; margin: 0 auto; }\n}", $tail );
	twd_sk_hasnt( 'vw', $tail, 'no viewport-width units' );
	twd_sk_hasnt( '@media (min-width', $tail );
	twd_sk_has( 'twd-sk-phone-under', file_get_contents( ABSPATH . 'assets/twd-site-kit.css' ) );
} );
