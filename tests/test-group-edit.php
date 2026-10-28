<?php
// Question groups in the Visual editor (0.15.0): tab words editable (never their address), a tab and its group
// move together, and the tab's list entry is no longer one piece of text that typing could empty.

function twd_sk_ge_page() {
	return TWD_SK_Sanitizer::clean( TWD_SK_Starters::build( array( array( 'hero', 'twd-sk-hero--compact' ), array( 'faq', 'twd-sk-faq--groups' ) ) ) );
}

/** The editor's number for the nth tab link, group name or tab entry, from the page as drawn for an editor. */
function twd_sk_ge_numbers( $stored, $class, $attr ) {
	$marked = TWD_SK_Inline::mark( $stored );
	preg_match_all( '/<[a-z0-9]+ [^>]*class="' . preg_quote( $class, '/' ) . '(?: [^"]*)?"[^>]*>/i', $marked, $m );
	$out = array();
	foreach ( $m[0] as $tag ) {
		if ( preg_match( '/' . preg_quote( $attr, '/' ) . '="(\d+)"/', $tag, $n ) ) {
			$out[] = (int) $n[1];
		}
	}
	return $out;
}

twd_sk_test( 'groups edit: the tab link and the group heading are pieces of text; the tab\'s list entry is not (it holds only the link)', function () {
	$stored = twd_sk_ge_page();
	twd_sk_eq( 3, count( twd_sk_ge_numbers( $stored, 'twd-sk-faq__tablink', 'data-twd-sk-t' ) ), 'three tab links' );
	twd_sk_eq( 3, count( twd_sk_ge_numbers( $stored, 'twd-sk-faq__groupname', 'data-twd-sk-t' ) ), 'three group headings' );
	twd_sk_eq( array(), twd_sk_ge_numbers( $stored, 'twd-sk-faq__tab', 'data-twd-sk-t' ), 'the entry around a tab is not text' );
	// Each tab entry and each group can still be removed.
	twd_sk_eq( 3, count( twd_sk_ge_numbers( $stored, 'twd-sk-faq__tab', 'data-twd-sk-r' ) ) );
	// The first group carries the default class, so it is not one of several alike: its tab entry is how it goes.
	twd_sk_eq( 2, count( twd_sk_ge_numbers( $stored, 'twd-sk-faq__group', 'data-twd-sk-r' ) ) );
} );

twd_sk_test( 'groups edit: changing a tab\'s words keeps its address; a new address is refused; the sanitiser keeps the result', function () {
	$stored = twd_sk_ge_page();
	$tabs   = twd_sk_ge_numbers( $stored, 'twd-sk-faq__tablink', 'data-twd-sk-t' );
	$out    = TWD_SK_Inline::apply( $stored, array( array( 'i' => $tabs[1], 'html' => 'Booking and fees' ) ), array() );
	twd_sk_true( ! is_wp_error( $out ), is_wp_error( $out ) ? $out->get_error_message() : '' );
	twd_sk_has( '<a class="twd-sk-faq__tablink" href="#twd-sk-questions-2">Booking and fees</a>', $out['html'] );
	twd_sk_has( '<a class="twd-sk-faq__tablink" href="#twd-sk-questions-1">', $out['html'], 'the others are as they were' );
	twd_sk_eq( $out['html'], TWD_SK_Sanitizer::clean( $out['html'] ) );
	$bad = TWD_SK_Inline::apply( $stored, array( array( 'i' => $tabs[1], 'html' => 'x', 'href' => '/elsewhere' ) ), array() );
	twd_sk_is_error( 'twd_sk_bad_input', $bad );
	twd_sk_has( 'its own group', $bad->get_error_message() );
	// Bold and the like cannot be put in a tab: tags are reduced to words, the link stays.
	$tag = TWD_SK_Inline::apply( $stored, array( array( 'i' => $tabs[0], 'html' => 'Fees <b>and</b> <script>x</script>booking' ) ), array() );
	twd_sk_has( 'href="#twd-sk-questions-1">Fees and x' . 'booking</a>', preg_replace( '/<script>.*?<\/script>/', '', $tag['html'] ) !== $tag['html'] ? $tag['html'] : $tag['html'] );
	twd_sk_hasnt( '<script', $tag['html'] );
	twd_sk_hasnt( '<b>', $tag['html'] );
} );

twd_sk_test( 'groups edit: taking out a tab takes its group, taking out a group takes its tab, and one group stays the one shown first', function () {
	$stored = twd_sk_ge_page();
	// Remove the second tab entry.
	$tabs = twd_sk_ge_numbers( $stored, 'twd-sk-faq__tab', 'data-twd-sk-r' );
	$a    = TWD_SK_Inline::apply( $stored, array(), array(), array(), array( $tabs[1] ) );
	twd_sk_true( ! is_wp_error( $a ), is_wp_error( $a ) ? $a->get_error_message() : '' );
	twd_sk_hasnt( 'twd-sk-questions-2', $a['html'], 'the tab and its group are both gone' );
	twd_sk_eq( 2, substr_count( $a['html'], 'class="twd-sk-faq__tablink"' ) );
	twd_sk_eq( 2, preg_match_all( '/<div class="twd-sk-faq__group(?: twd-sk-faq__group--default)?" id=/', $a['html'] ) );
	// Remove the third group: its tab goes too.
	$groups = twd_sk_ge_numbers( $stored, 'twd-sk-faq__group', 'data-twd-sk-r' );
	$b      = TWD_SK_Inline::apply( $stored, array(), array(), array(), array( $groups[1] ) );
	twd_sk_hasnt( 'twd-sk-questions-3', $b['html'] );
	twd_sk_eq( 2, substr_count( $b['html'], 'class="twd-sk-faq__tablink"' ) );
	// Remove the default (first) group, by its tab: the next one becomes the default, so something still shows on a wide screen.
	$c = TWD_SK_Inline::apply( $stored, array(), array(), array(), array( $tabs[0] ) );
	twd_sk_hasnt( 'twd-sk-questions-1', $c['html'] );
	twd_sk_eq( 1, substr_count( $c['html'], 'twd-sk-faq__group--default' ), 'one default' );
	twd_sk_true( 1 === preg_match( '/class="twd-sk-faq__group twd-sk-faq__group--default" id="twd-sk-questions-2"/', $c['html'] ), 'the next group is the default' );
	// Both halves named together is not an error, and the default stays one.
	$d = TWD_SK_Inline::apply( $stored, array(), array(), array(), array( $tabs[1], $groups[0] ) );
	twd_sk_true( ! is_wp_error( $d ) );
	twd_sk_hasnt( 'twd-sk-questions-2', $d['html'] );
	twd_sk_eq( 1, substr_count( $d['html'], 'twd-sk-faq__group--default' ) );
	twd_sk_eq( $d['html'], TWD_SK_Sanitizer::clean( $d['html'] ) );
	// A page that is not a groups page is removed from exactly as before.
	$plain = TWD_SK_Sanitizer::clean( TWD_SK_Starters::build( array( array( 'hero', 'twd-sk-hero--compact' ), array( 'faq', '' ) ) ) );
	$items = twd_sk_ge_numbers( $plain, 'twd-sk-faq__item', 'data-twd-sk-r' );
	twd_sk_true( count( $items ) >= 2 );
	$e = TWD_SK_Inline::apply( $plain, array(), array(), array(), array( $items[0] ) );
	twd_sk_eq( count( $items ) - 1, substr_count( $e['html'], 'class="twd-sk-faq__item"' ) );
} );

twd_sk_test( 'groups edit: the script keeps a tab and its heading in step, refuses a new address for a tab, and the page shows every group while editing', function () {
	$js = file_get_contents( ABSPATH . 'assets/twd-site-kit-inline.js' );
	foreach ( array( 'function groupPair(', 'function syncGroup(', 'syncGroup(t);', 'A group tab always opens its own group' ) as $needle ) {
		twd_sk_has( $needle, $js );
	}
	twd_sk_hasnt( 'innerHTML', $js );
	$css = file_get_contents( ABSPATH . 'assets/twd-site-kit-inline.css' );
	twd_sk_has( 'body.twd-sk-inline-on .twd-sk-faq__groups .twd-sk-faq__group { display: block !important; }', $css );
	// Every rule is still under the editing class (visitors are not affected).
	foreach ( explode( "\n", $css ) as $line ) {
		if ( false !== strpos( $line, 'twd-sk-faq__group' ) && false !== strpos( $line, '{' ) ) {
			twd_sk_true( 0 === strpos( $line, 'body.twd-sk-inline-on' ), $line );
		}
	}
} );
