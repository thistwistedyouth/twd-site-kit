<?php
// Delete the labelled words inside a custom section (12 Nov 2026, from the pilot site's Explore Further banner): a custom
// section used to be removable only as a whole, so a heading in a picture banner could be emptied but never deleted.
// Now the words its author labelled (data-twd-edit) can be deleted on their own, the wrapper they leave empty goes with
// them, and pictures, unlabelled pieces and the page's main heading stay out of reach. Kit sections are as before.
// Uses the helpers in test-custom.php.

function twd_sk_rc_css() {
	return ".c-explore { background: var(--twd-site-color-surface); }\n.c-explore__bg { border-radius: var(--twd-site-radius-image); }\n.c-explore__inner { padding: var(--twd-site-card-pad); }\n.c-explore__title { color: var(--twd-site-color-heading); }\n.c-explore__note { color: var(--twd-site-color-heading); }\n";
}

function twd_sk_rc_banner() {
	return '<section class="twd-sk-custom c-explore twd-sk-tone-surface"><img class="c-explore__bg" src="/wp-content/uploads/your-image.jpg" alt="A desert road" width="800" height="600" data-twd-pic="Background photo"><div class="c-explore__inner"><h2 class="c-explore__title" data-twd-edit="Heading">Explore Further</h2></div></section>';
}

twd_sk_test( 'remove in a custom section: a lone labelled heading is deletable, its empty wrapper goes with it, the background picture stays, and the save is one version that Undo brings back', function () {
	twd_sk_custom_page();
	TWD_SK_Css::save( twd_sk_rc_css(), 'x', 51 );
	$page   = TWD_SK_Sanitizer::clean( TWD_SK_Registry::get( 'hero' )['skeleton'] . twd_sk_rc_banner() );
	$marked = TWD_SK_Inline::mark( $page );
	twd_sk_true( 1 === preg_match( '/<h2[^>]*c-explore__title[^>]*data-twd-sk-r="(\d+)"/', $marked, $n ), 'the labelled heading is numbered as removable' );
	twd_sk_true( 1 !== preg_match( '/<img[^>]*c-explore__bg[^>]*data-twd-sk-r=/', $marked ), 'the picture is not' );
	twd_sk_true( 1 !== preg_match( '/<div[^>]*c-explore__inner[^>]*data-twd-sk-r=/', $marked ), 'nor is the wrapper on its own' );
	$out = TWD_SK_Inline::apply( $page, array(), array(), array(), array( (int) $n[1] ) );
	twd_sk_true( ! is_wp_error( $out ), is_wp_error( $out ) ? $out->get_error_message() : '' );
	twd_sk_eq( 1, $out['removed'] );
	twd_sk_hasnt( 'Explore Further', $out['html'], 'the words are gone' );
	twd_sk_hasnt( 'c-explore__inner', $out['html'], 'and so is the box they left empty' );
	twd_sk_has( 'c-explore__bg', $out['html'], 'the picture stays' );
	twd_sk_has( 'twd-sk-hero__title', $out['html'], 'and so does the page title' );
} );

twd_sk_test( 'remove in a custom section: other labelled words can go too, an unlabelled piece cannot, and the main heading of the page cannot', function () {
	twd_sk_custom_page();
	TWD_SK_Css::save( twd_sk_custom_css() . twd_sk_rc_css(), 'x', 51 );
	$custom = '<section class="twd-sk-custom"><div class="c-explore__inner"><h1 data-twd-edit="Title">Page title</h1><p>Not labelled</p><p class="c-explore__note" data-twd-edit="Note">A short note</p></div></section>';
	$page   = TWD_SK_Sanitizer::clean( TWD_SK_Replace::mark_custom( twd_sk_custom_section() ) . $custom );
	$marked = TWD_SK_Inline::mark( $page );
	twd_sk_true( 1 === preg_match( '/<p[^>]*data-twd-edit="Card one"[^>]*data-twd-sk-r="\d+"/', $marked ), 'a labelled card paragraph is removable' );
	twd_sk_true( 1 === preg_match( '/<p[^>]*c-explore__note[^>]*data-twd-sk-r="\d+"/', $marked ), 'so is a labelled note' );
	twd_sk_true( 1 !== preg_match( '/<p[^>]*data-twd-sk-r="\d+"[^>]*>Not labelled/', $marked ), 'an unlabelled paragraph is not' );
	twd_sk_true( 1 !== preg_match( '/<h1[^>]*data-twd-sk-r=/', $marked ), 'the main heading is not' );
	twd_sk_true( 1 !== preg_match( '/<img[^>]*c-intro__pic[^>]*data-twd-sk-r=/', $marked ), 'a labelled picture is not' );
	preg_match( '/<p[^>]*c-explore__note[^>]*data-twd-sk-r="(\d+)"/', $marked, $note );
	$out = TWD_SK_Inline::apply( $page, array(), array(), array(), array( (int) $note[1] ) );
	twd_sk_true( ! is_wp_error( $out ), is_wp_error( $out ) ? $out->get_error_message() : '' );
	twd_sk_hasnt( 'A short note', $out['html'] );
	twd_sk_has( 'Not labelled', $out['html'], 'the unlabelled words stay' );
	twd_sk_has( 'Page title', $out['html'], 'and the heading' );
	twd_sk_has( 'c-explore__inner', $out['html'], 'the box still holds something, so it stays' );
} );

twd_sk_test( 'remove in a custom section: a number that does not belong to a removable piece is refused (the picture, or a number past the end)', function () {
	twd_sk_custom_page();
	TWD_SK_Css::save( twd_sk_rc_css(), 'x', 51 );
	$page  = TWD_SK_Sanitizer::clean( TWD_SK_Registry::get( 'hero' )['skeleton'] . twd_sk_rc_banner() );
	$count = TWD_SK_Inline::count( $page );
	$out   = TWD_SK_Inline::apply( $page, array(), array(), array(), array( $count['removes'] + 5 ) );
	twd_sk_true( is_wp_error( $out ), 'a number past the end is refused' );
} );

twd_sk_test( 'remove in a custom section: kit sections number exactly what they did (the custom change touches no other section)', function () {
	twd_sk_custom_page();
	$kit = TWD_SK_Sanitizer::clean( TWD_SK_Registry::get( 'services' )['skeleton'] . TWD_SK_Registry::get( 'faq' )['skeleton'] );
	$before = TWD_SK_Inline::count( $kit );
	TWD_SK_Css::save( twd_sk_rc_css(), 'x', 51 );
	$with = TWD_SK_Inline::count( TWD_SK_Sanitizer::clean( TWD_SK_Registry::get( 'services' )['skeleton'] . TWD_SK_Registry::get( 'faq' )['skeleton'] . twd_sk_rc_banner() ) );
	// The banner adds exactly one removable piece (its heading) and one section to the kit sections' count.
	twd_sk_eq( $before['removes'] + 2, $with['removes'], 'the section and its heading' );
	twd_sk_eq( $before['text'] + 1, $with['text'], 'text pieces are counted as before' );
} );
