<?php
// Registry tests: the components as data, keyed on IDs, with variants.

twd_sk_test( 'registry: exactly the seventeen expected component IDs, in page order', function () {
	twd_sk_eq(
		array( 'hero', 'text', 'image_text', 'pictures', 'quote', 'logos', 'faq', 'services', 'pricing', 'steps', 'cta', 'contact', 'hours', 'areas', 'enquiry', 'notice', 'resources' ),
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

twd_sk_test( 'registry: only resources (twd_articles) and enquiry (twd_enquiry, no attributes) declare a shortcode', function () {
	foreach ( TWD_SK_Registry::all() as $id => $c ) {
		if ( 'resources' === $id ) {
			twd_sk_eq( array( 'twd_articles' ), array_keys( $c['shortcodes'] ) );
		} elseif ( 'enquiry' === $id ) {
			twd_sk_eq( array( 'twd_enquiry' => array() ), $c['shortcodes'] );
		} else {
			twd_sk_eq( array(), $c['shortcodes'], $id . ' has no shortcodes' );
		}
	}
	$tags = array_keys( TWD_SK_Registry::allowed_shortcodes() );
	sort( $tags );
	twd_sk_eq( array( 'twd_articles', 'twd_enquiry' ), $tags );
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
		// The only brackets allowed are the registry's own shortcodes.
		$text = preg_replace( '/\[twd_articles [^\]]*\]|\[twd_enquiry\]/', '', $text );
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

// -- 0.14.0: question groups --------------------------------------------------------------

twd_sk_test( 'registry: the faq groups layout is pure HTML: a row of in-page links, groups with twd-sk- ids, a default group, questions as details, one h1 nowhere', function () {
	$g = TWD_SK_Registry::get( 'faq' )['variants']['twd-sk-faq--groups']['skeleton'];
	twd_sk_has( 'class="twd-sk-faq twd-sk-faq--groups', $g );
	twd_sk_eq( 3, substr_count( $g, 'class="twd-sk-faq__tablink" href="#twd-sk-questions-' ) );
	twd_sk_eq( 3, substr_count( $g, ' id="twd-sk-questions-' ) );
	twd_sk_eq( 1, substr_count( $g, 'twd-sk-faq__group--default' ), 'one default group' );
	twd_sk_eq( 3, substr_count( $g, '<details class="twd-sk-faq__item">' ) );
	twd_sk_hasnt( '<script', $g );
	twd_sk_hasnt( '<button', $g );
	twd_sk_hasnt( 'data-', $g );
	preg_match_all( '/ id="([^"]+)"/', $g, $ids );
	foreach ( $ids[1] as $id ) {
		twd_sk_true( 0 === strpos( $id, 'twd-sk-' ), 'id prefix: ' . $id );
	}
	// The sanitiser keeps it whole, and the sample group heading is flagged as leftover text.
	$r = TWD_SK_Sanitizer::clean_with_report( $g );
	twd_sk_eq( 0, $r['report']['total'] );
	twd_sk_true( isset( TWD_SK_Sanitizer::find_leftovers( $g )['Heading here'] ) );
	// The stylesheet shows one group at a time only on a wide screen, with no script, via :target and :has.
	$raw = file_get_contents( ABSPATH . 'assets/twd-site-kit.css' );
	twd_sk_true( 1 === preg_match( '/@media \(min-width: 769px\)[^@]*twd-sk-faq__group\.twd-sk-faq__group:not\(:target\) \{[^}]*display: none/', $raw ), 'wide screens show the linked group' );
	twd_sk_true( 1 === preg_match( '/@media \(min-width: 769px\)[^@]*:not\(:has\([^)]*:target\)\)[^{]*twd-sk-faq__group--default[^{]*\{[^}]*display: none/', $raw ), 'with no link followed, the default group shows' );
	twd_sk_has( 'scroll-margin-top', $raw );
	// It is offered as a layout in the Visual editor's section menu.
	twd_sk_true( isset( TWD_SK_Recipes::parts_menu()['faq']['variants']['twd-sk-faq--groups'] ) );
} );

twd_sk_test( 'compatibility: a page stored by 0.13 passes through the 0.14 sanitiser byte for byte', function () {
	$stored = file_get_contents( ABSPATH . 'tests/fixtures/stored-page-0-13.html' );
	twd_sk_true( strlen( $stored ) > 5000 );
	twd_sk_eq( $stored, TWD_SK_Sanitizer::clean( $stored ), 'an old page is not changed by the new code' );
	twd_sk_eq( 0, TWD_SK_Sanitizer::clean_with_report( $stored )['report']['total'] );
	// Saved through the store it is a no-op second time round, so no version is written for nothing.
	twd_stub_add_post( 913 );
	$first = TWD_SK_Store::save( 913, $stored );
	twd_sk_eq( 1, $first['version'] );
	$again = TWD_SK_Store::save( 913, $stored );
	twd_sk_true( ! empty( $again['unchanged'] ) );
} );

twd_sk_test( 'registry: the prose text layout is a narrow left-aligned reading column (0.14.0)', function () {
	$v = TWD_SK_Registry::get( 'text' )['variants']['twd-sk-text--prose'];
	twd_sk_has( 'class="twd-sk-text twd-sk-text--prose', $v['skeleton'] );
	twd_sk_has( 'twd-sk-inner--narrow', $v['skeleton'] );
	$raw = file_get_contents( ABSPATH . 'assets/twd-site-kit.css' );
	twd_sk_true( 1 === preg_match( '/twd-sk-text--prose\.twd-sk-text--prose \.twd-sk-text__body\.twd-sk-text__body \{[^}]*text-align: left/', $raw ), 'left aligned' );
	twd_sk_true( 1 === preg_match( '/twd-sk-text\.twd-sk-text:not\(\.twd-sk-text--aside\.twd-sk-text--aside\):not\(\.twd-sk-text--prose\.twd-sk-text--prose\)/', $raw ), 'the centred rule leaves prose alone' );
} );

twd_sk_test( 'registry: the articles strip is a few articles from one category beside an introduction; its sample category is flagged; the grid controls are hidden in it (0.14.0)', function () {
	$v = TWD_SK_Registry::get( 'resources' )['variants']['twd-sk-resources--strip'];
	twd_sk_has( 'class="twd-sk-resources twd-sk-resources--strip', $v['skeleton'] );
	twd_sk_has( '[twd_articles category="your-topic" count="3" columns="3"]', $v['skeleton'] );
	twd_sk_has( 'twd-sk-resources__intro', $v['skeleton'] );
	twd_sk_has( 'twd-sk-resources__grid', $v['skeleton'] );
	twd_sk_hasnt( '<h1', $v['skeleton'] );
	// The sanitiser keeps it whole, the shortcode included, and the sample category is must-fix.
	twd_sk_eq( $v['skeleton'], TWD_SK_Sanitizer::clean( $v['skeleton'] ) );
	$left = TWD_SK_Sanitizer::find_leftovers( $v['skeleton'] );
	twd_sk_true( isset( $left['your-topic'] ) );
	twd_sk_eq( 'must', TWD_SK_Sanitizer::leftover_level( 'your-topic' ) );
	// A real category is not flagged.
	twd_sk_true( ! isset( TWD_SK_Sanitizer::find_leftovers( str_replace( 'your-topic', 'five-keys', $v['skeleton'] ) )['your-topic'] ) );
	$raw = file_get_contents( ABSPATH . 'assets/twd-site-kit.css' );
	twd_sk_true( 1 === preg_match( '/twd-sk-resources__row\.twd-sk-resources__row \{[^}]*grid-template-columns: 1fr 2fr/', $raw ), 'side by side on a wide screen' );
	twd_sk_true( 1 === preg_match( '/twd-sk-resources__grid\.twd-sk-resources__grid \.twd-ap-articles-toolbar[^{]*\{[^}]*display: none/', $raw ), 'search box and category list hidden' );
	twd_sk_has( '.twd-ap-articles-loadmore-row', $raw );
	// Offered as a layout in the pop-up's Add a section menu and accepted as a page part.
	$parts = TWD_SK_Recipes::clean_parts( array( array( 'hero', '' ), array( 'resources', 'twd-sk-resources--strip' ) ) );
	twd_sk_eq( 'twd-sk-resources--strip', $parts[1][1] );
} );
