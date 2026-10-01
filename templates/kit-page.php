<?php
/**
 * The TWD Kit Page template.
 *
 * Full width, no theme padding, no page title of its own when the kit page has a hero h1.
 * It calls get_header() and get_footer(), so the theme's header and footer (including an
 * Elementor Theme Builder header and footer) work as normal. The page body comes from the
 * kit, not from Elementor and not from the page's own content.
 */
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

get_header();

while ( have_posts() ) :
	the_post();
	$twd_sk_id = (int) get_the_ID();
	?>
<main id="content">
	<?php
	if ( ! TWD_SK_Page::page_has_h1( $twd_sk_id ) ) {
		// A page with no hero h1 still needs exactly one h1: show the page title in a compact hero.
		echo '<div class="twd-sk-page"><section class="twd-sk-hero twd-sk-hero--compact twd-sk-tone-base"><div class="twd-sk-inner"><h1 class="twd-sk-hero__title">' . esc_html( get_the_title() ) . '</h1></div></section></div>';
	}
	echo TWD_SK_Page::render(); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- built from sanitised kit HTML.
	?>
</main>
	<?php
endwhile;

get_footer();
