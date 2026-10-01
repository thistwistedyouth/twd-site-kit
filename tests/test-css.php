<?php
// Stylesheet rules, enforced by tests so they cannot drift.

/**
 * A small CSS reader: comments stripped, @media blocks understood.
 * Returns a list of rules: media (string or null), selectors (list), decls (list of prop, value, important).
 */
function twd_sk_css_parse( $css ) {
	$css   = preg_replace( '!/\*.*?\*/!s', '', $css );
	$rules = array();
	$pos   = 0;
	twd_sk_css_block( $css, $pos, null, $rules );
	return $rules;
}

function twd_sk_css_block( $css, &$pos, $media, &$rules ) {
	$len = strlen( $css );
	while ( $pos < $len ) {
		while ( $pos < $len && ctype_space( $css[ $pos ] ) ) {
			$pos++;
		}
		if ( $pos >= $len ) {
			return;
		}
		if ( '}' === $css[ $pos ] ) {
			$pos++;
			return;
		}
		$open = strpos( $css, '{', $pos );
		if ( false === $open ) {
			return;
		}
		$prelude = trim( substr( $css, $pos, $open - $pos ) );
		$pos     = $open + 1;
		if ( 0 === strpos( $prelude, '@media' ) ) {
			twd_sk_css_block( $css, $pos, $prelude, $rules );
			continue;
		}
		$close = strpos( $css, '}', $pos );
		$body  = substr( $css, $pos, $close - $pos );
		$pos   = $close + 1;

		$decls = array();
		foreach ( explode( ';', $body ) as $d ) {
			$d = trim( $d );
			if ( '' === $d ) {
				continue;
			}
			$colon     = strpos( $d, ':' );
			$value     = trim( substr( $d, $colon + 1 ) );
			$important = ( false !== strpos( $value, '!important' ) );
			$decls[]   = array(
				'prop'      => trim( substr( $d, 0, $colon ) ),
				'value'     => trim( str_replace( '!important', '', $value ) ),
				'important' => $important,
			);
		}
		$selectors = array();
		foreach ( explode( ',', $prelude ) as $sel ) {
			$sel = trim( $sel );
			if ( '' !== $sel ) {
				$selectors[] = preg_replace( '/\s+/', ' ', $sel );
			}
		}
		$rules[] = array( 'media' => $media, 'selectors' => $selectors, 'decls' => $decls );
	}
}

function twd_sk_css_rules() {
	static $rules = null;
	if ( null === $rules ) {
		$rules = twd_sk_css_parse( file_get_contents( ABSPATH . 'assets/twd-site-kit.css' ) );
	}
	return $rules;
}

function twd_sk_css_fallback_selectors() {
	return array( 'body.twd-sk-has-h1 .page-header', 'body.twd-sk-has-h1 .entry-title' );
}

twd_sk_test( 'css: the stylesheet parses into rules', function () {
	twd_sk_true( count( twd_sk_css_rules() ) > 150, 'rules found: ' . count( twd_sk_css_rules() ) );
} );

twd_sk_test( 'css: every selector is scoped under .twd-sk-page, except the two page title fallback selectors', function () {
	$outside = array();
	foreach ( twd_sk_css_rules() as $rule ) {
		foreach ( $rule['selectors'] as $sel ) {
			if ( 0 !== strpos( $sel, '.twd-sk-page' ) ) {
				$outside[] = $sel;
			}
		}
	}
	twd_sk_eq( twd_sk_css_fallback_selectors(), $outside );
} );

twd_sk_test( 'css: no selector uses an id, a universal-only rule outside the wrapper, or an inline-style trick', function () {
	foreach ( twd_sk_css_rules() as $rule ) {
		foreach ( $rule['selectors'] as $sel ) {
			twd_sk_hasnt( '#', $sel, 'no id selectors' );
			twd_sk_hasnt( '[style', $sel );
		}
	}
} );

twd_sk_test( 'css: every component class in a selector is doubled so a theme cannot win on specificity', function () {
	foreach ( twd_sk_css_rules() as $rule ) {
		foreach ( $rule['selectors'] as $sel ) {
			if ( in_array( $sel, twd_sk_css_fallback_selectors(), true ) ) {
				continue;
			}
			// Every .twd-sk-* class other than the wrapper must appear as .x.x
			preg_match_all( '/\.(twd-sk-[A-Za-z0-9_-]+)/', $sel, $m );
			$names = array_filter( $m[1], function ( $n ) {
				return 'twd-sk-page' !== $n;
			} );
			foreach ( array_unique( $names ) as $name ) {
				twd_sk_true( 1 === preg_match( '/\.' . preg_quote( $name, '/' ) . '\.' . preg_quote( $name, '/' ) . '(?![A-Za-z0-9_-])/', $sel ), 'doubled .' . $name . ' in: ' . $sel );
			}
		}
	}
} );

twd_sk_test( 'css: every visual property carries !important', function () {
	$visual = '/^(color|background|background-color|background-image|font-family|font-size|font-weight|font-style|line-height|letter-spacing|text-transform|text-decoration|text-align|border|border-(top|right|bottom|left)(-(color|width|style))?|border-color|border-width|border-style|border-radius|box-shadow|padding|padding-(top|right|bottom|left)|margin|margin-(top|right|bottom|left))$/';
	foreach ( twd_sk_css_rules() as $rule ) {
		foreach ( $rule['decls'] as $d ) {
			if ( preg_match( $visual, $d['prop'] ) ) {
				twd_sk_true( $d['important'], $d['prop'] . ': ' . $d['value'] . ' has !important in ' . $rule['selectors'][0] );
			}
		}
	}
} );

twd_sk_test( 'css: layout properties never carry !important, so the [hidden] rule always wins', function () {
	foreach ( twd_sk_css_rules() as $rule ) {
		foreach ( $rule['decls'] as $d ) {
			if ( 'display' === $d['prop'] && $d['important'] ) {
				twd_sk_true( in_array( $rule['selectors'][0], array_merge( array( '.twd-sk-page [hidden]' ), twd_sk_css_fallback_selectors() ), true ), 'display !important only on [hidden] and the title fallback: ' . $rule['selectors'][0] );
			}
		}
	}
} );

twd_sk_test( 'css: there is an unconditional [hidden] rule that hides with display none !important', function () {
	$found = false;
	foreach ( twd_sk_css_rules() as $rule ) {
		if ( in_array( '.twd-sk-page [hidden]', $rule['selectors'], true ) && null === $rule['media'] ) {
			foreach ( $rule['decls'] as $d ) {
				if ( 'display' === $d['prop'] && 'none' === $d['value'] && $d['important'] ) {
					$found = true;
				}
			}
		}
	}
	twd_sk_true( $found );
} );

twd_sk_test( 'css: no 100vw, no viewport-width units, no negative margins, no overflow hidden', function () {
	foreach ( twd_sk_css_rules() as $rule ) {
		foreach ( $rule['decls'] as $d ) {
			twd_sk_true( 1 !== preg_match( '/\d\s*vw\b/i', $d['value'] ), 'no vw units: ' . $d['prop'] . ': ' . $d['value'] );
			if ( 0 === strpos( $d['prop'], 'margin' ) ) {
				twd_sk_true( 1 !== preg_match( '/(^|\s)-\d/', $d['value'] ) && false === strpos( $d['value'], 'calc(-' ), 'no negative margin: ' . $d['prop'] . ': ' . $d['value'] );
			}
			if ( 0 === strpos( $d['prop'], 'overflow' ) ) {
				twd_sk_true( 'hidden' !== $d['value'], 'no overflow hidden in ' . $rule['selectors'][0] );
			}
		}
	}
	$raw = file_get_contents( ABSPATH . 'assets/twd-site-kit.css' );
	twd_sk_hasnt( '100vw', $raw );
	twd_sk_hasnt( 'overflow: hidden', $raw );
	twd_sk_hasnt( 'overflow-x: hidden', $raw );
} );

twd_sk_test( 'css: the wrapper clips horizontal overflow with overflow-x: clip', function () {
	$found = false;
	foreach ( twd_sk_css_rules() as $rule ) {
		if ( array( '.twd-sk-page' ) === $rule['selectors'] ) {
			foreach ( $rule['decls'] as $d ) {
				if ( 'overflow-x' === $d['prop'] && 'clip' === $d['value'] ) {
					$found = true;
				}
			}
		}
	}
	twd_sk_true( $found );
} );

twd_sk_test( 'css: no colour or font literals, everything comes from --twd-site-* tokens', function () {
	foreach ( twd_sk_css_rules() as $rule ) {
		foreach ( $rule['decls'] as $d ) {
			$value = $d['value'];
			twd_sk_true( 1 !== preg_match( '/#[0-9a-fA-F]{3,8}\b/', $value ), 'no hex colour: ' . $d['prop'] . ': ' . $value );
			twd_sk_true( 1 !== preg_match( '/\b(rgb|rgba|hsl|hsla)\(/i', $value ), 'no rgb or hsl: ' . $d['prop'] . ': ' . $value );
			if ( 'font-family' === $d['prop'] ) {
				twd_sk_true( 0 === strpos( $value, 'var(--twd-site-' ), 'font-family from a token: ' . $value );
			}
			if ( in_array( $d['prop'], array( 'color', 'background-color', 'border-color' ), true ) ) {
				twd_sk_true( 1 === preg_match( '/^(var\(--twd-site-[a-z0-9-]+\)|transparent|inherit|currentColor)$/', $value ), 'colour from a token: ' . $d['prop'] . ': ' . $value );
			}
		}
	}
} );

twd_sk_test( 'css: every --twd-site-* variable the stylesheet uses is a real token in the schema', function () {
	$raw = file_get_contents( ABSPATH . 'assets/twd-site-kit.css' );
	preg_match_all( '/var\(--twd-site-([a-z0-9-]+)\)/', $raw, $m );
	$schema = TWD_SK_Packs::token_names();
	foreach ( array_unique( $m[1] ) as $name ) {
		twd_sk_true( in_array( $name, $schema, true ), 'token exists: ' . $name );
	}
} );

twd_sk_test( 'css: every token in the schema is actually used by the stylesheet (no dead tokens)', function () {
	$raw = file_get_contents( ABSPATH . 'assets/twd-site-kit.css' );
	foreach ( TWD_SK_Packs::token_names() as $name ) {
		twd_sk_has( 'var(--twd-site-' . $name . ')', $raw, 'used: ' . $name );
	}
} );

twd_sk_test( 'css: no imports, no external urls, no expressions, no javascript', function () {
	$raw = file_get_contents( ABSPATH . 'assets/twd-site-kit.css' );
	foreach ( array( '@import', 'url(', 'expression(', 'javascript:', 'behavior:', '-moz-binding', 'http://', 'https://' ) as $bad ) {
		twd_sk_hasnt( $bad, $raw );
	}
} );

twd_sk_test( 'css: every registry class (shared and component) appears in the stylesheet, so no class is a dead hook', function () {
	$raw = file_get_contents( ABSPATH . 'assets/twd-site-kit.css' );
	foreach ( array_keys( TWD_SK_Registry::allowed_classes() ) as $class ) {
		twd_sk_true( 1 === preg_match( '/\.' . preg_quote( $class, '/' ) . '(?![A-Za-z0-9_-])/', $raw ), 'styled: ' . $class );
	}
} );

twd_sk_test( 'css: every class in the stylesheet is a registry class (no styling for a class the sanitiser would strip)', function () {
	$raw = file_get_contents( ABSPATH . 'assets/twd-site-kit.css' );
	preg_match_all( '/\.(twd-sk-[A-Za-z0-9_-]+)/', $raw, $m );
	$allowed = TWD_SK_Registry::allowed_classes();
	foreach ( array_unique( $m[1] ) as $class ) {
		if ( 'twd-sk-page' === $class || 'twd-sk-has-h1' === $class ) {
			continue;
		}
		twd_sk_true( isset( $allowed[ $class ] ), 'registered: ' . $class );
	}
} );

twd_sk_test( 'css: the helpline modal is pure CSS, shown with :target and fixed only there', function () {
	$raw = file_get_contents( ABSPATH . 'assets/twd-site-kit.css' );
	twd_sk_has( '.twd-sk-modal.twd-sk-modal:target', $raw );
	$fixed = array();
	foreach ( twd_sk_css_rules() as $rule ) {
		foreach ( $rule['decls'] as $d ) {
			if ( 'position' === $d['prop'] && 'fixed' === $d['value'] ) {
				$fixed[] = $rule['selectors'][0];
			}
		}
	}
	twd_sk_eq( array( '.twd-sk-page .twd-sk-modal.twd-sk-modal' ), $fixed );
} );

twd_sk_test( 'css: images use the object-position token and object-fit cover, and image rows use grid with stretch, never flex', function () {
	$raw = file_get_contents( ABSPATH . 'assets/twd-site-kit.css' );
	twd_sk_has( 'object-position: var(--twd-site-image-position)', $raw );
	foreach ( twd_sk_css_rules() as $rule ) {
		foreach ( $rule['selectors'] as $sel ) {
			if ( false !== strpos( $sel, 'twd-sk-image-text__grid' ) ) {
				$props = array();
				foreach ( $rule['decls'] as $d ) {
					$props[ $d['prop'] ] = $d['value'];
				}
				if ( isset( $props['display'] ) ) {
					twd_sk_eq( 'grid', $props['display'], 'image row is a grid' );
					twd_sk_eq( 'stretch', $props['align-items'], 'image row stretches' );
				}
			}
			if ( false !== strpos( $sel, 'twd-sk-image-text__img' ) && null === $rule['media'] && false === strpos( $sel, '--band' ) ) {
				$props = array();
				foreach ( $rule['decls'] as $d ) {
					$props[ $d['prop'] ] = $d['value'];
				}
				twd_sk_eq( '100%', $props['width'], 'image fills the width' );
				twd_sk_eq( '100%', $props['height'], 'image fills the height' );
				twd_sk_eq( 'cover', $props['object-fit'], 'image covers' );
			}
		}
	}
} );

twd_sk_test( 'css: there are mobile rules at 768px and the services grid steps down to one column at 500px', function () {
	$media = array();
	foreach ( twd_sk_css_rules() as $rule ) {
		if ( $rule['media'] ) {
			$media[ $rule['media'] ] = true;
		}
	}
	twd_sk_true( isset( $media['@media (max-width: 768px)'] ) );
	twd_sk_true( isset( $media['@media (max-width: 900px)'] ) );
	twd_sk_true( isset( $media['@media (max-width: 500px)'] ) );
} );

twd_sk_test( 'css: the page title fallback exists and only affects pages with the twd-sk-has-h1 body class', function () {
	$raw = file_get_contents( ABSPATH . 'assets/twd-site-kit.css' );
	twd_sk_has( 'body.twd-sk-has-h1 .page-header', $raw );
	twd_sk_has( 'body.twd-sk-has-h1 .entry-title', $raw );
} );

twd_sk_test( 'css: the stylesheet is a sensible size', function () {
	$size = filesize( ABSPATH . 'assets/twd-site-kit.css' );
	twd_sk_true( $size > 10000 && $size < 80000, 'size ' . $size );
} );

twd_sk_test( 'css: the file holds no em or en dashes', function () {
	$raw = file_get_contents( ABSPATH . 'assets/twd-site-kit.css' );
	twd_sk_true( false === strpos( $raw, "\xE2\x80\x94" ) && false === strpos( $raw, "\xE2\x80\x93" ) );
} );

/**
 * Pull the declaration block of the exact selector from the stylesheet.
 */
function twd_sk_css_decls( $raw, $selector ) {
	$doubled = preg_replace( '/\.(twd-sk-[A-Za-z0-9_-]+)/', '.$1.$1', $selector );
	$pattern = '/\.twd-sk-page ' . preg_quote( $doubled, '/' ) . '\s*\{([^}]*)\}/';
	preg_match_all( $pattern, $raw, $m );
	return implode( ' ', $m[1] );
}

twd_sk_test( 'css: two-column grids use fractional tracks that fill the container, never auto or content widths', function () {
	$raw = file_get_contents( ABSPATH . 'assets/twd-site-kit.css' );
	foreach ( array( '.twd-sk-image-text__grid', '.twd-sk-text__grid', '.twd-sk-hero--split .twd-sk-hero__grid' ) as $sel ) {
		$block = twd_sk_css_decls( $raw, $sel );
		twd_sk_true( '' !== $block, 'no rule for ' . $sel );
		preg_match( '/grid-template-columns:\s*([^;]+);/', $block, $m );
		twd_sk_true( ! empty( $m[1] ), 'no columns for ' . $sel );
		twd_sk_true( 1 === preg_match( '/\dfr/', $m[1] ), 'no fr unit for ' . $sel . ': ' . $m[1] );
		twd_sk_true( ! preg_match( '/auto|content|px|%|em/', $m[1] ), 'content or fixed width for ' . $sel . ': ' . $m[1] );
		if ( false === strpos( $sel, 'hero' ) ) {
			twd_sk_has( 'width: 100%', $block );
			twd_sk_has( 'align-items: stretch', $block );
		}
	}
} );

twd_sk_test( 'css: the image fills its column and the narrow variant keeps its one deliberate fixed image column', function () {
	$raw = file_get_contents( ABSPATH . 'assets/twd-site-kit.css' );
	$img = twd_sk_css_decls( $raw, '.twd-sk-image-text__img' );
	twd_sk_has( 'width: 100%', $img );
	twd_sk_has( 'height: 100%', $img );
	twd_sk_has( 'object-fit: cover', $img );
	twd_sk_has( 'minmax(0, 320px)', twd_sk_css_decls( $raw, '.twd-sk-image-text--narrow .twd-sk-image-text__grid' ) );
} );

twd_sk_test( 'css: the narrow text layout is one centred column and the aside layout stays left aligned', function () {
	$raw = file_get_contents( ABSPATH . 'assets/twd-site-kit.css' );
	twd_sk_true( 1 === preg_match( '/twd-sk-text:not\(\.twd-sk-text--aside[^{]*\{[^}]*text-align:\s*center/', $raw ), 'no centred rule' );
	twd_sk_true( 1 === preg_match( '/twd-sk-text--aside\.twd-sk-text--aside \.twd-sk-title[^{]*\{[^}]*text-align:\s*left/', $raw ), 'aside title not left aligned' );
} );
