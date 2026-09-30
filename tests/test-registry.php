<?php
// Registry tests: the 9 components as data, keyed on IDs.

twd_sk_test( 'registry: exactly the nine expected component IDs, in order', function () {
	twd_sk_eq(
		array( 'hero', 'text', 'image_text', 'quote', 'faq', 'services', 'team', 'cta', 'resources' ),
		TWD_SK_Registry::ids()
	);
} );

twd_sk_test( 'registry: every entry is keyed on its own id, not its label', function () {
	foreach ( TWD_SK_Registry::all() as $key => $c ) {
		twd_sk_eq( $key, $c['id'], 'key matches id' );
		twd_sk_true( 1 === preg_match( '/^[a-z_]+$/', $key ), 'id is a plain lowercase id: ' . $key );
		twd_sk_true( $key !== $c['label'], 'key is not the label: ' . $key );
		twd_sk_eq( null, TWD_SK_Registry::get( $c['label'] ), 'label does not resolve: ' . $c['label'] );
	}
} );

twd_sk_test( 'registry: every entry has id, label, skeleton, classes and shortcodes', function () {
	foreach ( TWD_SK_Registry::all() as $c ) {
		foreach ( array( 'id', 'label', 'skeleton', 'classes', 'shortcodes' ) as $field ) {
			twd_sk_true( array_key_exists( $field, $c ), $c['id'] . ' has ' . $field );
		}
		twd_sk_true( is_string( $c['label'] ) && '' !== $c['label'], $c['id'] . ' label' );
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

twd_sk_test( 'registry: allowed_classes() is the union of every component class', function () {
	$allowed = TWD_SK_Registry::allowed_classes();
	foreach ( TWD_SK_Registry::all() as $c ) {
		foreach ( $c['classes'] as $class ) {
			twd_sk_true( isset( $allowed[ $class ] ), 'in allowlist: ' . $class );
		}
	}
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

twd_sk_test( 'registry: every skeleton passes through the sanitiser with nothing removed', function () {
	foreach ( TWD_SK_Registry::all() as $id => $c ) {
		$r = TWD_SK_Sanitizer::clean_with_report( $c['skeleton'] );
		twd_sk_eq( 0, $r['report']['total'], $id . ' skeleton removals: ' . json_encode( $r['report']['removed'] ) );
		twd_sk_eq(
			preg_replace( '/>\s+</', '><', $c['skeleton'] ),
			preg_replace( '/>\s+</', '><', $r['html'] ),
			$id . ' skeleton unchanged'
		);
	}
} );

twd_sk_test( 'registry: style_guide_data() is plain data keyed on id', function () {
	$g = TWD_SK_Registry::style_guide_data();
	twd_sk_eq( TWD_SK_Registry::ids(), array_keys( $g ) );
	twd_sk_eq( array( 'twd_articles' ), $g['resources']['shortcodes'] );
	twd_sk_true( is_string( $g['hero']['skeleton'] ) );
} );

twd_sk_test( 'registry: holds data only, nothing renders', function () {
	foreach ( get_class_methods( 'TWD_SK_Registry' ) as $method ) {
		twd_sk_true( 0 === preg_match( '/render|output|print|echo/i', $method ), 'no render method: ' . $method );
	}
} );

twd_sk_test( 'registry: the resources skeleton wraps the articles shortcode', function () {
	twd_sk_has( '[twd_articles', TWD_SK_Registry::get( 'resources' )['skeleton'] );
} );
