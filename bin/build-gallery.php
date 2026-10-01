<?php
/**
 * Builds starters/_gallery.html from the components registry.
 *
 * The gallery shows every component and every variant once, with a small label
 * above each, so a page's look can be checked in one go. The registry is the
 * single source of truth: do not edit the gallery by hand. A test fails if the
 * committed file differs from this script's output.
 *
 *   php bin/build-gallery.php            writes starters/_gallery.html
 *   php bin/build-gallery.php --stdout   prints it instead
 *
 * A real page has one h1 (the first hero). Later hero variants in the gallery
 * use an h2 for their title, exactly as the sanitiser would demote them.
 */

if ( ! defined( 'ABSPATH' ) ) {
	define( 'ABSPATH', dirname( __DIR__ ) . '/' );
}
require_once ABSPATH . 'includes/class-twd-sk-registry.php';

function twd_sk_build_gallery() {
	$out        = array();
	$hero_count = 0;

	$out[] = twd_sk_gallery_label( 'Gallery', 'Every component and variant, with placeholder text. Not a real page.' );

	foreach ( TWD_SK_Registry::all() as $id => $component ) {
		$entries = array();
		foreach ( $component['variants'] as $class => $variant ) {
			$entries[] = array( $id . ' / ' . $class, $variant['skeleton'] );
		}
		// The default skeleton, when it is not already one of the variants.
		$default_in_variants = false;
		foreach ( $component['variants'] as $variant ) {
			if ( $variant['skeleton'] === $component['skeleton'] ) {
				$default_in_variants = true;
			}
		}
		if ( ! $default_in_variants ) {
			array_unshift( $entries, array( $id . ' / default', $component['skeleton'] ) );
		}

		foreach ( $entries as $entry ) {
			list( $label, $skeleton ) = $entry;
			if ( 'hero' === $id ) {
				$hero_count++;
				if ( $hero_count > 1 ) {
					$skeleton = str_replace( array( '<h1 ', '</h1>' ), array( '<h2 ', '</h2>' ), $skeleton );
				}
			}
			$out[] = twd_sk_gallery_label( $label, $component['description'] );
			$out[] = twd_sk_gallery_placeholders( $skeleton );
		}
	}

	return implode( "\n", $out ) . "\n";
}

/**
 * In the gallery only, the example image names point at two neutral pictures
 * bundled with the plugin, so the page shows pictures instead of broken images.
 * The registry skeletons keep the your-image names the AI is told to replace.
 */
function twd_sk_gallery_placeholders( $skeleton ) {
	return str_replace(
		array( '/wp-content/uploads/your-image.jpg', '/wp-content/uploads/your-badge.png' ),
		array( '/wp-content/plugins/twd-site-kit/assets/placeholder-image.svg', '/wp-content/plugins/twd-site-kit/assets/placeholder-badge.svg' ),
		$skeleton
	);
}

function twd_sk_gallery_label( $title, $note ) {
	return implode( "\n", array(
		'<div class="twd-sk-gallery-label">',
		'<div class="twd-sk-inner">',
		'<p><strong>' . htmlspecialchars( $title, ENT_NOQUOTES ) . '</strong>: ' . htmlspecialchars( $note, ENT_NOQUOTES ) . '</p>',
		'</div>',
		'</div>',
	) );
}

if ( PHP_SAPI === 'cli' && isset( $argv[0] ) && realpath( $argv[0] ) === realpath( __FILE__ ) ) {
	$html = twd_sk_build_gallery();
	if ( in_array( '--stdout', $argv, true ) ) {
		echo $html;
	} else {
		file_put_contents( ABSPATH . 'starters/_gallery.html', $html );
		echo 'Wrote starters/_gallery.html (' . strlen( $html ) . " bytes)\n";
	}
}
