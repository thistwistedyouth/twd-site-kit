<?php
// Style packs, tokens, bundled fonts and asset output.

function twd_sk_pack( $slug ) {
	return TWD_SK_Packs::read_pack_file( ABSPATH . 'packs/' . $slug . '.json' );
}
function twd_sk_good_tokens() {
	return twd_sk_pack( 'sage' )['tokens'];
}
function twd_sk_write_pack( $slug, $data ) {
	$dir = sys_get_temp_dir() . '/twdsk-packs-' . getmypid();
	if ( ! is_dir( $dir ) ) {
		mkdir( $dir );
	}
	$path = $dir . '/' . $slug . '.json';
	file_put_contents( $path, is_string( $data ) ? $data : json_encode( $data ) );
	return $path;
}

// -- the two shipped packs ---------------------------------------------------

twd_sk_test( 'packs: exactly two packs ship, sage and grove, and both are valid', function () {
	twd_sk_eq( array( 'grove', 'sage' ), array_keys( TWD_SK_Packs::packs() ) );
	twd_sk_true( is_array( twd_sk_pack( 'sage' ) ) );
	twd_sk_true( is_array( twd_sk_pack( 'grove' ) ) );
} );

twd_sk_test( 'packs: each pack defines every token in the schema and nothing else', function () {
	foreach ( array( 'sage', 'grove' ) as $slug ) {
		$raw = json_decode( file_get_contents( ABSPATH . 'packs/' . $slug . '.json' ), true );
		$have = array_keys( $raw['tokens'] );
		$want = TWD_SK_Packs::token_names();
		sort( $have );
		sort( $want );
		twd_sk_eq( $want, $have, $slug . ' defines exactly the schema tokens' );
	}
} );

twd_sk_test( 'packs: each pack has a slug matching its filename, a neutral name and a description', function () {
	foreach ( TWD_SK_Packs::packs() as $slug => $pack ) {
		twd_sk_eq( $slug, $pack['slug'] );
		twd_sk_true( '' !== $pack['name'] && '' !== $pack['description'], $slug );
	}
	twd_sk_eq( 'Sage', twd_sk_pack( 'sage' )['name'] );
	twd_sk_eq( 'Grove', twd_sk_pack( 'grove' )['name'] );
} );

twd_sk_test( 'packs: the two packs really differ in colours, fonts and shapes', function () {
	$a = twd_sk_pack( 'sage' )['tokens'];
	$b = twd_sk_pack( 'grove' )['tokens'];
	foreach ( array( 'color-primary', 'color-bg', 'font-heading', 'font-body', 'radius-btn', 'heading-align', 'heading-style' ) as $name ) {
		twd_sk_true( $a[ $name ] !== $b[ $name ], $name . ' differs' );
	}
} );

twd_sk_test( 'packs: sage matches the verified final look (palette, fonts, pill buttons, italic headings)', function () {
	$t = twd_sk_pack( 'sage' )['tokens'];
	twd_sk_eq( '#5F6F54', $t['color-primary'] );
	twd_sk_eq( '#B4C0A8', $t['color-tint'] );
	twd_sk_eq( '#FAF7F2', $t['color-bg'] );
	twd_sk_eq( '#C9A66B', $t['color-accent'] );
	twd_sk_eq( 'Cormorant Garamond', $t['font-heading'] );
	twd_sk_eq( 'Jost', $t['font-body'] );
	twd_sk_eq( '30px', $t['radius-btn'] );
	twd_sk_eq( 'italic', $t['heading-style'] );
	twd_sk_eq( $t['color-heading'], $t['color-accent-text'], 'the pack shows no second colour for the accent span' );
} );

twd_sk_test( 'packs: grove matches the verified final look (palette, fonts, small radii, centred headings)', function () {
	$t = twd_sk_pack( 'grove' )['tokens'];
	twd_sk_eq( '#2C3B35', $t['color-heading'] );
	twd_sk_eq( '#5A7352', $t['color-primary'] );
	twd_sk_eq( '#C9A468', $t['color-accent'] );
	twd_sk_eq( 'Lora', $t['font-heading'] );
	twd_sk_eq( 'Nunito', $t['font-body'] );
	twd_sk_eq( '4px', $t['radius-btn'] );
	twd_sk_eq( 'center', $t['heading-align'] );
} );

// -- validation rules ----------------------------------------------------------

twd_sk_test( 'tokens: valid colours are accepted (hex 3, 6, 8 digits, rgb, rgba, transparent)', function () {
	foreach ( array( '#fff', '#FFFFFF', '#ffffff80', 'rgb(1,2,3)', 'rgba(58, 31, 46, 0.55)', 'rgba(0,0,0,.5)', 'rgba(0,0,0,1)', 'transparent' ) as $v ) {
		twd_sk_true( TWD_SK_Packs::valid_token( 'color-bg', $v ), $v );
	}
} );

twd_sk_test( 'tokens: anything that is not a plain colour is refused', function () {
	foreach ( array( 'red', 'url(x)', '#12', '#12345', '#GGGGGG', 'rgb(256,0,0)', 'rgba(0,0,0,2)', 'rgb(0,0,0);}body{display:none', '#fff;', 'var(--x)', 'expression(alert(1))', '</style><script>', '', ' ', 'javascript:alert(1)', "#fff\n}", 'inherit' ) as $v ) {
		twd_sk_true( ! TWD_SK_Packs::valid_token( 'color-bg', $v ), 'refused: ' . json_encode( $v ) );
	}
} );

twd_sk_test( 'tokens: valid lengths are accepted', function () {
	foreach ( array( '0', '4px', '1.5px', '17px', '1.2rem', '0.12em', '100%', '1140px' ) as $v ) {
		twd_sk_true( TWD_SK_Packs::valid_token( 'radius-card', $v ), $v );
	}
} );

twd_sk_test( 'tokens: negative lengths, viewport units, functions and garbage are refused', function () {
	foreach ( array( '-1px', '-10px', '100vw', '50vh', 'calc(1px + 2px)', '10', '10 px', 'px', '1e3px', '5000px', 'auto', 'var(--x)', '10px;', '10px 20px', '10pt' ) as $v ) {
		twd_sk_true( ! TWD_SK_Packs::valid_token( 'radius-card', $v ), 'refused: ' . json_encode( $v ) );
	}
} );

twd_sk_test( 'tokens: weights, line heights and keyword tokens are strict', function () {
	twd_sk_true( TWD_SK_Packs::valid_token( 'heading-weight', '600' ) );
	twd_sk_true( TWD_SK_Packs::valid_token( 'heading-weight', '1000' ) );
	twd_sk_true( ! TWD_SK_Packs::valid_token( 'heading-weight', '650' ) );
	twd_sk_true( ! TWD_SK_Packs::valid_token( 'heading-weight', 'bold' ) );
	twd_sk_true( TWD_SK_Packs::valid_token( 'line-body', '1.6' ) );
	twd_sk_true( TWD_SK_Packs::valid_token( 'line-body', '2' ) );
	twd_sk_true( ! TWD_SK_Packs::valid_token( 'line-body', '9' ) );
	twd_sk_true( ! TWD_SK_Packs::valid_token( 'line-body', '1.6px' ) );
	twd_sk_true( TWD_SK_Packs::valid_token( 'heading-style', 'italic' ) );
	twd_sk_true( ! TWD_SK_Packs::valid_token( 'heading-style', 'oblique' ) );
	twd_sk_true( TWD_SK_Packs::valid_token( 'heading-align', 'center' ) );
	twd_sk_true( ! TWD_SK_Packs::valid_token( 'heading-align', 'justify' ) );
} );

twd_sk_test( 'tokens: only bundled fonts or system stacks are accepted, never an arbitrary name', function () {
	foreach ( array( 'Cormorant Garamond', 'Lora', 'Jost', 'Nunito', 'System serif', 'System sans' ) as $v ) {
		twd_sk_true( TWD_SK_Packs::valid_token( 'font-body', $v ), $v );
	}
	foreach ( array( 'Comic Sans MS', 'Arial', 'x"; background:url(x)', 'Jost, serif', '', 'jost' ) as $v ) {
		twd_sk_true( ! TWD_SK_Packs::valid_token( 'font-body', $v ), 'refused: ' . $v );
	}
} );

twd_sk_test( 'tokens: shadows and image positions are strict', function () {
	foreach ( array( 'none', '0 4px 20px rgba(0,0,0,0.05)', '0 8px 24px 2px #00000033', '1px 1px 0 #fff' ) as $v ) {
		twd_sk_true( TWD_SK_Packs::valid_token( 'shadow-card', $v ), $v );
	}
	foreach ( array( '0 4px 20px red', 'inset 0 0 1px #000', '0 0 0 0 0 #000', 'url(x)', '0 4px 20px rgba(0,0,0,0.05);}', '' ) as $v ) {
		twd_sk_true( ! TWD_SK_Packs::valid_token( 'shadow-card', $v ), 'refused: ' . $v );
	}
	foreach ( array( 'center', 'top', 'center 30%', '50% 20%', 'left bottom' ) as $v ) {
		twd_sk_true( TWD_SK_Packs::valid_token( 'image-position', $v ), $v );
	}
	foreach ( array( 'center 300%', 'url(x)', 'middle', '10px 10px', 'center top bottom' ) as $v ) {
		twd_sk_true( ! TWD_SK_Packs::valid_token( 'image-position', $v ), 'refused: ' . $v );
	}
} );

twd_sk_test( 'tokens: unknown token names and non-string values are refused', function () {
	twd_sk_true( ! TWD_SK_Packs::valid_token( 'not-a-token', '#fff' ) );
	twd_sk_true( ! TWD_SK_Packs::valid_token( 'color-bg', array( '#fff' ) ) );
	twd_sk_true( ! TWD_SK_Packs::valid_token( 'color-bg', 12 ) );
} );

twd_sk_test( 'packs: validate_tokens reports unknown, invalid and (for a pack) missing tokens', function () {
	$r = TWD_SK_Packs::validate_tokens( array( 'color-bg' => '#fff', 'bogus' => '1', 'color-body' => 'red' ), true );
	twd_sk_eq( array( 'color-bg' => '#fff' ), $r['valid'] );
	twd_sk_true( in_array( 'unknown token: bogus', $r['errors'], true ) );
	twd_sk_true( in_array( 'invalid value for color-body', $r['errors'], true ) );
	twd_sk_true( in_array( 'missing token: color-surface', $r['errors'], true ) );
	$partial = TWD_SK_Packs::validate_tokens( array( 'color-bg' => '#fff' ), false );
	twd_sk_eq( array(), $partial['errors'] );
} );

twd_sk_test( 'packs: a pack file with a bad value, a missing token, an unknown token or a wrong slug is rejected', function () {
	$good = json_decode( file_get_contents( ABSPATH . 'packs/sage.json' ), true );

	$bad = $good; $bad['slug'] = 'badpack'; $bad['tokens']['color-bg'] = 'red';
	twd_sk_eq( null, TWD_SK_Packs::read_pack_file( twd_sk_write_pack( 'badpack', $bad ) ), 'bad value' );

	$bad = $good; $bad['slug'] = 'missing'; unset( $bad['tokens']['color-bg'] );
	twd_sk_eq( null, TWD_SK_Packs::read_pack_file( twd_sk_write_pack( 'missing', $bad ) ), 'missing token' );

	$bad = $good; $bad['slug'] = 'extra'; $bad['tokens']['extra-token'] = '1px';
	twd_sk_eq( null, TWD_SK_Packs::read_pack_file( twd_sk_write_pack( 'extra', $bad ) ), 'unknown token' );

	$bad = $good; $bad['slug'] = 'other';
	twd_sk_eq( null, TWD_SK_Packs::read_pack_file( twd_sk_write_pack( 'mismatch', $bad ) ), 'slug differs from filename' );

	twd_sk_eq( null, TWD_SK_Packs::read_pack_file( twd_sk_write_pack( 'broken', '{not json' ) ), 'broken JSON' );
	twd_sk_eq( null, TWD_SK_Packs::read_pack_file( '/no/such/pack.json' ), 'missing file' );

	$ok = $good; $ok['slug'] = 'fine';
	twd_sk_true( is_array( TWD_SK_Packs::read_pack_file( twd_sk_write_pack( 'fine', $ok ) ) ), 'a copy of a good pack is accepted' );
} );

twd_sk_test( 'packs: a pack cannot smuggle CSS rules or markup, only validated token values', function () {
	$good = json_decode( file_get_contents( ABSPATH . 'packs/sage.json' ), true );
	foreach ( array( 'color-bg' => '#fff;}body{display:none}', 'font-body' => 'Jost"}body{x:y', 'size-body' => '17px;}a{color:red', 'shadow-card' => '0 0 0 #000;}' ) as $name => $value ) {
		$bad = $good; $bad['slug'] = 'inject'; $bad['tokens'][ $name ] = $value;
		twd_sk_eq( null, TWD_SK_Packs::read_pack_file( twd_sk_write_pack( 'inject', $bad ) ), $name );
	}
} );

// -- active pack and overrides -------------------------------------------------

twd_sk_test( 'packs: sage is the default active pack', function () {
	twd_sk_eq( 'sage', TWD_SK_Packs::active_slug() );
} );

twd_sk_test( 'packs: set_active switches pack, active_slug follows, an unknown name is refused', function () {
	twd_sk_eq( true, TWD_SK_Packs::set_active( 'grove' ) );
	twd_sk_eq( 'grove', TWD_SK_Packs::active_slug() );
	twd_sk_is_error( 'twd_sk_unknown_pack', TWD_SK_Packs::set_active( 'nope' ) );
	twd_sk_eq( 'grove', TWD_SK_Packs::active_slug(), 'unchanged after a refused switch' );
	twd_sk_is_error( 'twd_sk_unknown_pack', TWD_SK_Packs::set_active( array( 'x' ) ) );
} );

twd_sk_test( 'packs: a bad stored pack name falls back to the default instead of breaking the site', function () {
	update_option( 'twd_sk_pack', 'deleted-pack' );
	twd_sk_eq( 'sage', TWD_SK_Packs::active_slug() );
	update_option( 'twd_sk_pack', array( 'x' ) );
	twd_sk_eq( 'sage', TWD_SK_Packs::active_slug() );
} );

twd_sk_test( 'packs: active_tokens are the active pack, with valid per-site overrides on top and invalid ones ignored', function () {
	update_option( 'twd_sk_pack', 'grove' );
	update_option( 'twd_sk_tokens', array( 'color-primary' => '#112233', 'color-bg' => 'not a colour', 'bogus' => 'x' ) );
	$t = TWD_SK_Packs::active_tokens();
	twd_sk_eq( '#112233', $t['color-primary'] );
	twd_sk_eq( '#FBF8F3', $t['color-bg'], 'invalid override ignored, pack value kept' );
	twd_sk_true( ! isset( $t['bogus'] ) );
	twd_sk_eq( count( TWD_SK_Packs::token_names() ), count( $t ), 'every token still present' );
} );

// -- CSS output ---------------------------------------------------------------

twd_sk_test( 'output: tokens_css is one :root block of --twd-site-* properties and nothing else', function () {
	foreach ( array( 'sage', 'grove' ) as $slug ) {
		$css = TWD_SK_Packs::tokens_css( twd_sk_pack( $slug )['tokens'] );
		twd_sk_true( 0 === strpos( $css, ':root{--twd-site-' ), $slug . ' starts with :root' );
		twd_sk_eq( 1, substr_count( $css, '{' ) );
		twd_sk_eq( 1, substr_count( $css, '}' ) );
		preg_match_all( '/--twd-site-([a-z0-9-]+):([^;]*);/', $css, $m );
		twd_sk_eq( count( TWD_SK_Packs::token_names() ), count( $m[1] ), $slug . ' all tokens printed' );
		twd_sk_eq( TWD_SK_Packs::token_names(), $m[1], $slug . ' in schema order' );
	}
} );

twd_sk_test( 'output: font tokens print the font name first, then a fallback stack; system fonts print only the stack', function () {
	$t   = twd_sk_pack( 'sage' )['tokens'];
	$css = TWD_SK_Packs::tokens_css( $t );
	twd_sk_has( '--twd-site-font-heading:"Cormorant Garamond", Georgia', $css );
	twd_sk_has( '--twd-site-font-body:"Jost", "Helvetica Neue", Arial, sans-serif;', $css );
	$t['font-body'] = 'System sans';
	twd_sk_hasnt( '"System sans"', TWD_SK_Packs::tokens_css( $t ) );
	twd_sk_has( '--twd-site-font-body:-apple-system', TWD_SK_Packs::tokens_css( $t ) );
} );

twd_sk_test( 'output: tokens_css skips any value that fails validation, so nothing unsafe can be printed', function () {
	$t = twd_sk_good_tokens();
	$t['color-bg']   = 'red;}body{display:none';
	$t['size-body']  = 'calc(1px)';
	$css = TWD_SK_Packs::tokens_css( $t );
	twd_sk_hasnt( 'display:none', $css );
	twd_sk_hasnt( '--twd-site-color-bg', $css );
	twd_sk_hasnt( '--twd-site-size-body', $css );
} );

twd_sk_test( 'output: font_face_css declares only the fonts the pack uses, with swap and a latin range', function () {
	$base = 'https://example.test/fonts/';
	$css  = TWD_SK_Packs::font_face_css( twd_sk_pack( 'sage' )['tokens'], $base );
	twd_sk_has( 'font-family:"Cormorant Garamond"', $css );
	twd_sk_has( 'font-family:"Jost"', $css );
	twd_sk_hasnt( 'Lora', $css );
	twd_sk_hasnt( 'Nunito', $css );
	twd_sk_eq( 3, substr_count( $css, '@font-face' ), 'two Cormorant files plus one Jost file' );
	twd_sk_eq( 3, substr_count( $css, 'font-display:swap' ) );
	twd_sk_has( 'url("' . $base . 'jost-latin-wght-normal.woff2") format("woff2")', $css );
	twd_sk_has( 'unicode-range:U+0000-00FF', $css );
	$css = TWD_SK_Packs::font_face_css( twd_sk_pack( 'grove' )['tokens'], $base );
	twd_sk_has( 'font-family:"Lora"', $css );
	twd_sk_has( 'font-family:"Nunito"', $css );
	twd_sk_hasnt( 'Jost', $css );
} );

twd_sk_test( 'output: system fonts need no @font-face at all', function () {
	$t               = twd_sk_good_tokens();
	$t['font-heading'] = 'System serif';
	$t['font-body']    = 'System sans';
	twd_sk_eq( '', TWD_SK_Packs::font_face_css( $t, 'https://example.test/' ) );
} );

twd_sk_test( 'output: the assets class enqueues the kit stylesheet and attaches fonts and tokens inline', function () {
	update_option( 'twd_sk_pack', 'grove' );
	TWD_SK_Assets::enqueue();
	$s = $GLOBALS['twd_stub']['styles']['twd-site-kit'];
	twd_sk_true( $s['enqueued'] );
	twd_sk_eq( TWD_SK_URL . 'assets/twd-site-kit.css', $s['src'] );
	twd_sk_eq( TWD_SK_VERSION, $s['ver'] );
	twd_sk_has( '@font-face{font-family:"Lora"', $s['inline'] );
	twd_sk_has( TWD_SK_URL . 'assets/fonts/lora-latin-wght-normal.woff2', $s['inline'] );
	twd_sk_has( '--twd-site-color-heading:#2C3B35;', $s['inline'] );
} );

twd_sk_test( 'output: the assets class hooks wp_enqueue_scripts on every front-end page, not only pages with the shortcode', function () {
	TWD_SK_Assets::init();
	twd_sk_true( isset( $GLOBALS['twd_stub']['hooks']['wp_enqueue_scripts'] ) );
	$src = file_get_contents( ABSPATH . 'includes/class-twd-sk-assets.php' );
	twd_sk_hasnt( 'has_shortcode', $src );
} );

// -- bundled fonts on disk ---------------------------------------------------------

twd_sk_test( 'fonts: every font file in the registry exists, is a woff2 and is a sensible size', function () {
	foreach ( TWD_SK_Packs::fonts() as $family => $font ) {
		foreach ( $font['files'] as $f ) {
			$path = ABSPATH . 'assets/fonts/' . $f['file'];
			twd_sk_true( is_file( $path ), $family . ' file exists: ' . $f['file'] );
			twd_sk_eq( 'wOF2', substr( file_get_contents( $path, false, null, 0, 4 ), 0, 4 ), $f['file'] . ' is woff2' );
			twd_sk_true( filesize( $path ) > 10000 && filesize( $path ) < 80000, $f['file'] . ' size ' . filesize( $path ) );
			twd_sk_true( in_array( $f['style'], array( 'normal', 'italic' ), true ) );
			twd_sk_true( 1 === preg_match( '/^\d{3,4} \d{3,4}$/', $f['weight'] ), 'variable weight range' );
		}
	}
} );

twd_sk_test( 'fonts: no stray font files on disk that the registry does not know about', function () {
	$known = array();
	foreach ( TWD_SK_Packs::fonts() as $font ) {
		foreach ( $font['files'] as $f ) {
			$known[] = $f['file'];
		}
	}
	foreach ( glob( ABSPATH . 'assets/fonts/*.woff2' ) as $path ) {
		twd_sk_true( in_array( basename( $path ), $known, true ), 'registered: ' . basename( $path ) );
	}
} );

twd_sk_test( 'fonts: each bundled family ships with its open font licence', function () {
	foreach ( array( 'cormorant-garamond', 'lora', 'jost', 'nunito' ) as $slug ) {
		$path = ABSPATH . 'assets/fonts/OFL-' . $slug . '.txt';
		twd_sk_true( is_file( $path ), 'licence for ' . $slug );
		twd_sk_has( 'SIL OPEN FONT LICENSE', strtoupper( file_get_contents( $path ) ) );
	}
} );

twd_sk_test( 'fonts: no Google Fonts or other external font host is referenced anywhere in the plugin', function () {
	foreach ( array_merge( array( ABSPATH . 'twd-site-kit.php', ABSPATH . 'assets/twd-site-kit.css' ), glob( ABSPATH . 'includes/*.php' ), glob( ABSPATH . 'packs/*.json' ) ) as $file ) {
		$src = file_get_contents( $file );
		twd_sk_hasnt( 'fonts.googleapis', $src, basename( $file ) );
		twd_sk_hasnt( 'fonts.gstatic', $src, basename( $file ) );
	}
} );

// -- contrast, pinned ---------------------------------------------------------------

function twd_sk_luminance( $hex ) {
	$hex = ltrim( $hex, '#' );
	$c   = array( hexdec( substr( $hex, 0, 2 ) ), hexdec( substr( $hex, 2, 2 ) ), hexdec( substr( $hex, 4, 2 ) ) );
	foreach ( $c as &$v ) {
		$v /= 255;
		$v  = ( $v <= 0.03928 ) ? $v / 12.92 : pow( ( $v + 0.055 ) / 1.055, 2.4 );
	}
	return 0.2126 * $c[0] + 0.7152 * $c[1] + 0.0722 * $c[2];
}
function twd_sk_contrast( $a, $b ) {
	$x = twd_sk_luminance( $a );
	$y = twd_sk_luminance( $b );
	if ( $x < $y ) {
		list( $x, $y ) = array( $y, $x );
	}
	return ( $x + 0.05 ) / ( $y + 0.05 );
}

twd_sk_test( 'contrast: body text and card titles meet WCAG AA (4.5) on their backgrounds in both packs', function () {
	foreach ( array( 'sage', 'grove' ) as $slug ) {
		$t = twd_sk_pack( $slug )['tokens'];
		twd_sk_true( twd_sk_contrast( $t['color-body'], $t['color-bg'] ) >= 4.5, $slug . ' body on bg' );
		twd_sk_true( twd_sk_contrast( $t['color-body'], $t['color-surface'] ) >= 4.5, $slug . ' body on surface' );
		twd_sk_true( twd_sk_contrast( $t['color-title'], $t['color-surface'] ) >= 4.5, $slug . ' title on surface' );
	}
} );

twd_sk_test( 'contrast: grove headings meet AA on the page and card backgrounds', function () {
	$t = twd_sk_pack( 'grove' )['tokens'];
	twd_sk_true( twd_sk_contrast( $t['color-heading'], $t['color-bg'] ) >= 4.5 );
	twd_sk_true( twd_sk_contrast( $t['color-heading'], $t['color-surface'] ) >= 4.5 );
} );

twd_sk_test( 'contrast: text pairs on bands, buttons, headings and eyebrows reach 4.5 in both packs', function () {
	$pairs = array(
		array( 'color-on-primary', 'color-primary' ),
		array( 'color-on-primary', 'color-primary-hover' ),
		array( 'color-on-band', 'color-band' ),
		array( 'color-accent-on-dark', 'color-band' ),
		array( 'color-title', 'color-surface' ),
		array( 'color-title', 'color-tint' ),
		array( 'color-title', 'color-bg' ),
		array( 'color-heading', 'color-bg' ),
		array( 'color-heading', 'color-surface' ),
		array( 'color-accent-text', 'color-bg' ),
		array( 'color-eyebrow', 'color-bg' ),
		array( 'color-eyebrow', 'color-surface' ),
		array( 'color-primary', 'color-bg' ),
	);
	foreach ( array( 'sage', 'grove' ) as $slug ) {
		$t = twd_sk_pack( $slug )['tokens'];
		foreach ( $pairs as $p ) {
			$ratio = twd_sk_contrast( $t[ $p[0] ], $t[ $p[1] ] );
			twd_sk_true( $ratio >= 4.5, $slug . ' ' . $p[0] . ' on ' . $p[1] . ' is ' . round( $ratio, 2 ) );
		}
	}
} );

twd_sk_test( 'sizes: packs keep body and lead at 16px or more and small text, eyebrows and buttons at 14px or more', function () {
	$floors = array( 'size-body' => 16, 'size-lead' => 16, 'size-small' => 14, 'eyebrow-size' => 14, 'btn-size' => 14 );
	foreach ( array( 'sage', 'grove' ) as $slug ) {
		$t = twd_sk_pack( $slug )['tokens'];
		foreach ( $floors as $name => $min ) {
			twd_sk_true( (float) $t[ $name ] >= $min, $slug . ' ' . $name . ' is ' . $t[ $name ] );
		}
	}
	twd_sk_true( ! TWD_SK_Packs::valid_token( 'size-body', '15px' ) );
	twd_sk_true( ! TWD_SK_Packs::valid_token( 'eyebrow-size', '11px' ) );
	twd_sk_true( ! TWD_SK_Packs::valid_token( 'size-small', '13px' ) );
	twd_sk_true( TWD_SK_Packs::valid_token( 'eyebrow-size', '14px' ) );
} );
