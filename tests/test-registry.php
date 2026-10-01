<?php
// Registry tests: the components as data, keyed on IDs, with variants.

twd_sk_test( 'registry: exactly the eleven expected component IDs, in page order', function () {
	twd_sk_eq(
		array( 'hero', 'text', 'image_text', 'quote', 'faq', 'services', 'steps', 'cta', 'contact', 'notice', 'resources' ),
		TWD_SK_Registry::ids()
	);
} );

twd_sk_test( 'registry: team is folded into image_text, no separate team component', function () {
	twd_sk_eq( null, TWD_SK_Registry::get( 'team' ) );
	twd_sk_true( isset( TWD_SK_Registry::get( 'image_text' )['variants']['twd-sk-image-text--badge'] ), 'badge variant covers a bio with a badge' );
} );

twd_sk_test( 'registry: every entry is keyed on its own id, not its label', function () {
	foreach ( TWD_SK_Registry::all() as $key => $c ) {
		twd_sk_eq( $key, $c['id'], 'key matches id' );
		twd_sk_true( 1 === preg_match( '/^[a-z_]+$/', $key ), 'id is a plain lowercase id: ' . $key );
		twd_sk_true( $key !== $c['label'], 'key is not the label: ' . $key );
		twd_sk_eq( null, TWD_SK_Registry::get( $c['label'] ), 'label does not resolve: ' . $c['label'] );
	}
} );

twd_sk_test( 'registry: every entry has id, label, description, skeleton, variants, classes and shortcodes', function () {
	foreach ( TWD_SK_Registry::all() as $c ) {
		foreach ( array( 'id', 'label', 'description', 'skeleton', 'variants', 'classes', 'shortcodes' ) as $field ) {
			twd_sk_true( array_key_exists( $field, $c ), $c['id'] . ' has ' . $field );
		}
		twd_sk_true( is_string( $c['label'] ) && '' !== $c['label'], $c['id'] . ' label' );
		twd_sk_true( is_string( $c['description'] ) && '' !== $c['description'], $c['id'] . ' description' );
		twd_sk_true( is_string( $c['skeleton'] ) && '' !== $c['skeleton'], $c['id'] . ' skeleton' );
		twd_sk_true( is_array( $c['classes'] ) && count( $c['classes'] ) > 0, $c['id'] . ' classes' );
	}
} );

twd_sk_test( 'registry: get() returns a component by id and null for unknown or non-string', function () {
	twd_sk_eq( 'faq', TWD_SK_Registry::get( 'faq' )['id'] );
	twd_sk_eq( null, TWD_SK_Registry::get( 'nope' ) );
	twd_sk_eq( null, TWD_SK_Registry::get( 5 ) );
} );

twd_sk_test( 'registry: every class uses the twd-sk- prefix', function () {
	foreach ( TWD_SK_Registry::allowed_classes() as $class => $yes ) {
		twd_sk_true( 0 === strpos( $class, 'twd-sk-' ), 'class prefix: ' . $class );
	}
} );

twd_sk_test( 'registry: allowed_classes() is the shared classes plus every component class', function () {
	$allowed = TWD_SK_Registry::allowed_classes();
	foreach ( TWD_SK_Registry::shared_classes() as $class ) {
		twd_sk_true( isset( $allowed[ $class ] ), 'shared in allowlist: ' . $class );
	}
	foreach ( TWD_SK_Registry::all() as $c ) {
		foreach ( $c['classes'] as $class ) {
			twd_sk_true( isset( $allowed[ $class ] ), 'in allowlist: ' . $class );
		}
	}
} );

twd_sk_test( 'registry: the wrapper class twd-sk-page is never an allowed class inside a page', function () {
	twd_sk_true( ! isset( TWD_SK_Registry::allowed_classes()['twd-sk-page'] ) );
} );

twd_sk_test( 'registry: only resources declares a shortcode, and it is twd_articles', function () {
	foreach ( TWD_SK_Registry::all() as $id => $c ) {
		if ( 'resources' === $id ) {
			twd_sk_eq( array( 'twd_articles' ), array_keys( $c['shortcodes'] ) );
		} else {
			twd_sk_eq( array(), $c['shortcodes'], $id . ' has no shortcodes' );
		}
	}
	twd_sk_eq( array( 'twd_articles' ), array_keys( TWD_SK_Registry::allowed_shortcodes() ) );
} );

twd_sk_test( 'registry: the page shortcode is never allowed inside a page', function () {
	twd_sk_true( ! isset( TWD_SK_Registry::allowed_shortcodes()['twd_page'] ) );
} );

twd_sk_test( 'registry: every skeleton and every variant skeleton passes the sanitiser with nothing removed', function () {
	foreach ( TWD_SK_Registry::all() as $id => $c ) {
		$skeletons = array( $id => $c['skeleton'] );
		foreach ( $c['variants'] as $class => $variant ) {
			$skeletons[ $id . ' ' . $class ] = $variant['skeleton'];
		}
		foreach ( $skeletons as $name => $skeleton ) {
			$r = TWD_SK_Sanitizer::clean_with_report( $skeleton );
			twd_sk_eq( 0, $r['report']['total'], $name . ' removals: ' . json_encode( $r['report']['removed'] ) );
			twd_sk_eq(
				preg_replace( '/>\s+</', '><', $skeleton ),
				preg_replace( '/>\s+</', '><', $r['html'] ),
				$name . ' unchanged by the sanitiser'
			);
		}
	}
} );

twd_sk_test( 'registry: every variant is a modifier class the component declares, and its skeleton uses it on the root', function () {
	foreach ( TWD_SK_Registry::all() as $id => $c ) {
		foreach ( $c['variants'] as $class => $variant ) {
			twd_sk_true( in_array( $class, $c['classes'], true ), $id . ' declares ' . $class );
			twd_sk_true( 1 === preg_match( '/^<section class="[^"]*\b' . preg_quote( $class, '/' ) . '\b/', $variant['skeleton'] ), $id . ' root carries ' . $class );
			twd_sk_true( is_string( $variant['label'] ) && '' !== $variant['label'], $id . ' ' . $class . ' label' );
		}
	}
} );

twd_sk_test( 'registry: the requested variants all exist', function () {
	$want = array(
		'hero'       => array( 'twd-sk-hero--split', 'twd-sk-hero--image', 'twd-sk-hero--compact' ),
		'cta'        => array( 'twd-sk-cta--band', 'twd-sk-cta--strip' ),
		'services'   => array( 'twd-sk-services--cards', 'twd-sk-services--pills' ),
		'quote'      => array( 'twd-sk-quote--cards', 'twd-sk-quote--pullout' ),
		'image_text' => array( 'twd-sk-image-text--band', 'twd-sk-image-text--narrow', 'twd-sk-image-text--badge' ),
	);
	foreach ( $want as $id => $classes ) {
		foreach ( $classes as $class ) {
			twd_sk_true( isset( TWD_SK_Registry::get( $id )['variants'][ $class ] ), $id . ' has ' . $class );
		}
	}
} );

twd_sk_test( 'registry: the only h1 in any skeleton is the hero title', function () {
	foreach ( TWD_SK_Registry::all() as $id => $c ) {
		$all = array( $c['skeleton'] );
		foreach ( $c['variants'] as $v ) {
			$all[] = $v['skeleton'];
		}
		foreach ( $all as $skeleton ) {
			if ( 'hero' === $id ) {
				twd_sk_eq( 1, substr_count( $skeleton, '<h1 ' ), 'hero skeleton has one h1' );
				twd_sk_has( '<h1 class="twd-sk-hero__title"', $skeleton );
			} else {
				twd_sk_eq( 0, substr_count( $skeleton, '<h1' ), $id . ' has no h1' );
			}
		}
	}
} );

twd_sk_test( 'registry: skeleton placeholder text has no square brackets (the sanitiser would remove them as shortcodes)', function () {
	foreach ( TWD_SK_Registry::all() as $id => $c ) {
		$text = $c['skeleton'];
		foreach ( $c['variants'] as $v ) {
			$text .= $v['skeleton'];
		}
		// The one bracket allowed is the registry's own shortcode.
		$text = preg_replace( '/\[twd_articles [^\]]*\]/', '', $text );
		twd_sk_hasnt( '[', $text, $id );
		twd_sk_hasnt( ']', $text, $id );
	}
} );

twd_sk_test( 'registry: skeleton placeholders hold no real-looking contact details', function () {
	foreach ( TWD_SK_Registry::all() as $id => $c ) {
		$text = $c['skeleton'];
		foreach ( $c['variants'] as $v ) {
			$text .= $v['skeleton'];
		}
		preg_match_all( '/[A-Za-z0-9._%+-]+@[A-Za-z0-9.-]+/', $text, $emails );
		foreach ( $emails[0] as $email ) {
			twd_sk_true( 1 === preg_match( '/@example\.(com|org|net)$/', $email ), $id . ' email is not an example address: ' . $email );
		}
	}
} );

twd_sk_test( 'registry: style_guide_data() is plain data keyed on id, with variants and the shared classes', function () {
	$g = TWD_SK_Registry::style_guide_data();
	twd_sk_eq( array( 'components', 'shared' ), array_keys( $g ) );
	twd_sk_eq( TWD_SK_Registry::ids(), array_keys( $g['components'] ) );
	twd_sk_eq( array( 'twd_articles' ), $g['components']['resources']['shortcodes'] );
	twd_sk_true( is_string( $g['components']['hero']['skeleton'] ) );
	twd_sk_eq( array( 'twd-sk-hero--split', 'twd-sk-hero--image', 'twd-sk-hero--compact' ), array_keys( $g['components']['hero']['variants'] ) );
	twd_sk_eq( TWD_SK_Registry::shared_classes(), $g['shared'] );
} );

twd_sk_test( 'registry: holds data only, nothing renders', function () {
	foreach ( get_class_methods( 'TWD_SK_Registry' ) as $method ) {
		twd_sk_true( 0 === preg_match( '/render|output|print|echo/i', $method ), 'no render method: ' . $method );
	}
} );

twd_sk_test( 'registry: the resources skeleton wraps the articles shortcode', function () {
	twd_sk_has( '[twd_articles', TWD_SK_Registry::get( 'resources' )['skeleton'] );
} );

twd_sk_test( 'registry: the notice component is pure HTML for CSS: links to anchors, ids with the twd-sk- prefix, no buttons or scripts', function () {
	$n = TWD_SK_Registry::get( 'notice' )['skeleton'];
	twd_sk_has( 'href="#twd-sk-helplines"', $n );
	twd_sk_has( 'id="twd-sk-helplines"', $n );
	twd_sk_has( 'id="twd-sk-notice"', $n );
	twd_sk_hasnt( '<button', $n );
	twd_sk_hasnt( '<script', $n );
	twd_sk_hasnt( 'onclick', $n );
	twd_sk_hasnt( 'sms:', $n );
} );

twd_sk_test( 'registry: the notice lists the national support lines, and they are plain tel links or plain text', function () {
	$n = TWD_SK_Registry::get( 'notice' )['skeleton'];
	foreach ( array( 'Samaritans', 'Shout', 'NHS 111', 'CALM', 'Emergency services' ) as $name ) {
		twd_sk_has( $name, $n );
	}
	twd_sk_has( 'Text SHOUT to 85258', $n );
	preg_match_all( '/href="([^"]*)"/', $n, $m );
	foreach ( $m[1] as $href ) {
		twd_sk_true( 0 === strpos( $href, 'tel:' ) || 0 === strpos( $href, '#' ), 'only tel: and #anchor links: ' . $href );
	}
} );

twd_sk_test( 'registry: steps numbers come from CSS, so the skeleton holds no hand-typed numerals', function () {
	twd_sk_true( 0 === preg_match( '/>\s*\d+\s*</', TWD_SK_Registry::get( 'steps' )['skeleton'] ) );
} );

twd_sk_test( 'registry: the contact component holds no form, and says so', function () {
	$c = TWD_SK_Registry::get( 'contact' );
	twd_sk_hasnt( '<form', $c['skeleton'] );
	twd_sk_has( 'Elementor widget', $c['description'] );
} );
