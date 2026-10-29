<?php
// WCAG contrast maths and the token checks behind the Site tab.

twd_sk_test( 'contrast: known ratios (black on white 21, white on white 1, a mid grey)', function () {
	twd_sk_eq( 21.0, round( TWD_SK_Contrast::ratio( '#000000', '#FFFFFF' ), 2 ) );
	twd_sk_eq( 1.0, round( TWD_SK_Contrast::ratio( '#FFFFFF', '#ffffff' ), 2 ) );
	twd_sk_eq( 4.48, round( TWD_SK_Contrast::ratio( '#777777', '#FFFFFF' ), 2 ) );
	twd_sk_eq( TWD_SK_Contrast::ratio( '#112233', '#FFEEDD' ), TWD_SK_Contrast::ratio( '#FFEEDD', '#112233' ), 'order does not matter' );
	twd_sk_eq( TWD_SK_Contrast::ratio( '#abc', '#fff' ), TWD_SK_Contrast::ratio( '#AABBCC', '#FFFFFF' ), 'three-digit hex' );
	twd_sk_eq( TWD_SK_Contrast::ratio( 'rgb(0, 0, 0)', '#fff' ), TWD_SK_Contrast::ratio( '#000', '#fff' ), 'rgb()' );
} );

twd_sk_test( 'contrast: transparency and odd forms cannot be judged and give null', function () {
	foreach ( array( 'transparent', 'rgba(0,0,0,0.5)', '#12345678', 'red', '', null, array(), 'var(--x)' ) as $v ) {
		twd_sk_eq( null, TWD_SK_Contrast::parse( $v ), json_encode( $v ) );
	}
	twd_sk_eq( null, TWD_SK_Contrast::ratio( '#000', 'rgba(0,0,0,.5)' ) );
} );

twd_sk_test( 'contrast: both shipped packs have no blocking pair and no warning', function () {
	foreach ( TWD_SK_Packs::packs() as $slug => $pack ) {
		$c = TWD_SK_Contrast::check( $pack['tokens'] );
		twd_sk_eq( array(), $c['blocking'], $slug . ' blocking' );
		twd_sk_eq( array(), $c['warnings'], $slug . ' warnings' );
	}
} );

twd_sk_test( 'contrast: the pairs list marks the button and band pairs as blocking and the rest as warnings', function () {
	$levels = array();
	foreach ( TWD_SK_Contrast::pairs() as $p ) {
		$levels[ $p[0] ] = $p[1];
	}
	foreach ( array( 'button_text', 'button_hover', 'light_button', 'tint_button', 'band_text', 'band_accent' ) as $id ) {
		twd_sk_eq( 'block', $levels[ $id ], $id );
	}
	foreach ( array( 'body_bg', 'body_surface', 'muted_surface', 'heading_bg', 'heading_surface', 'eyebrow_bg', 'accent_text_bg', 'title_tint', 'primary_bg', 'frame_text', 'title_surface', 'box_band', 'box_picture' ) as $id ) {
		twd_sk_eq( 'warn', $levels[ $id ], $id );
	}
} );

twd_sk_test( 'contrast: every colour token a pair uses can be edited, and every pair token exists in the packs', function () {
	$editable = TWD_SK_Site::editable_names();
	$tokens   = TWD_SK_Packs::packs()['sage']['tokens'];
	foreach ( TWD_SK_Contrast::pairs() as $p ) {
		twd_sk_true( isset( $tokens[ $p[3] ], $tokens[ $p[4] ] ), $p[0] . ' tokens exist' );
		twd_sk_true( in_array( $p[3], $editable, true ) || in_array( $p[4], $editable, true ), $p[0] . ' can be fixed from the Site tab' );
	}
} );

twd_sk_test( 'contrast: unchecked colours are listed, not blocked', function () {
	$tokens = TWD_SK_Packs::packs()['sage']['tokens'];
	$tokens['color-band'] = 'rgba(10,20,30,0.5)';
	$c = TWD_SK_Contrast::check( $tokens );
	twd_sk_eq( array(), $c['blocking'] );
	$ids = array_map( function ( $i ) {
		return $i['id'];
	}, $c['unchecked'] );
	twd_sk_true( in_array( 'band_text', $ids, true ) );
} );

twd_sk_test( 'contrast: the browser script computes the same ratios (skipped when node is not installed)', function () {
	if ( ! function_exists( 'shell_exec' ) || '' === trim( (string) shell_exec( 'command -v node 2>/dev/null' ) ) || ! file_exists( ABSPATH . 'assets/twd-site-kit-editor-site.js' ) ) {
		return;
	}
	$pairs = array( array( '#000000', '#FFFFFF' ), array( '#777777', '#FFFFFF' ), array( '#5F6F54', '#FFFFFF' ), array( '#7A5C1E', '#FFFFFF' ), array( '#112233', '#FFEEDD' ), array( '#abc', '#123' ) );
	$js    = 'const fs=require("fs");const src=fs.readFileSync(' . json_encode( ABSPATH . 'assets/twd-site-kit-editor-site.js' ) . ',"utf8");'
		. 'const m=src.match(/\\/\\* contrast:start \\*\\/([\\s\\S]*?)\\/\\* contrast:end \\*\\//);if(!m){console.log("NOFN");process.exit(0);}'
		. 'const f=new Function(m[1]+";return contrastRatio;")();'
		. 'console.log(JSON.stringify(' . json_encode( $pairs ) . '.map(p=>Math.round(f(p[0],p[1])*100)/100)));';
	$out = trim( (string) shell_exec( 'node -e ' . escapeshellarg( $js ) . ' 2>&1' ) );
	$got = json_decode( $out, true );
	twd_sk_true( is_array( $got ), 'script output: ' . $out );
	foreach ( $pairs as $i => $p ) {
		twd_sk_true( abs( round( TWD_SK_Contrast::ratio( $p[0], $p[1] ), 2 ) - $got[ $i ] ) < 0.001, $p[0] . ' on ' . $p[1] . ': php ' . round( TWD_SK_Contrast::ratio( $p[0], $p[1] ), 2 ) . ' js ' . $got[ $i ] );
	}
} );

twd_sk_test( 'contrast: a see-through colour is flattened over what lies under it, and the frame pair reads the words over the frame over the overlay over a photograph (0.15.0)', function () {
	twd_sk_eq( 'rgb(0,0,0)', TWD_SK_Contrast::flatten( '#000', '#fff' ), 'an opaque colour is itself' );
	twd_sk_eq( 'rgb(128,128,128)', TWD_SK_Contrast::flatten( 'rgba(0,0,0,0.5)', '#FFFFFF' ) );
	twd_sk_eq( 'rgb(255,255,255)', TWD_SK_Contrast::flatten( 'transparent', '#FFFFFF' ) );
	twd_sk_eq( 'rgb(179,179,179)', TWD_SK_Contrast::flatten( 'rgba(0,0,0,0.3)', '#FFFFFF' ) );
	twd_sk_eq( 'rgb(128,0,0)', TWD_SK_Contrast::flatten( '#ff000080', '#000000' ) );
	twd_sk_eq( null, TWD_SK_Contrast::flatten( 'rgba(0,0,0,0.5)', 'rgba(0,0,0,0.5)' ), 'the colour under must be opaque' );
	twd_sk_eq( null, TWD_SK_Contrast::flatten( 'nonsense', '#fff' ) );
	twd_sk_eq( 'rgb(64,64,64)', TWD_SK_Contrast::stack( array( '#808080', 'rgba(0,0,0,0.5)' ), array() ) );
	twd_sk_eq( 'rgb(64,64,64)', TWD_SK_Contrast::stack( array( '#808080', 'color-x' ), array( 'color-x' => 'rgba(0,0,0,0.5)' ) ) );
	// Every pack: white words on the 30 percent frame over its overlay over mid grey pass 4.5:1 (no warning).
	foreach ( TWD_SK_Packs::packs() as $slug => $pack ) {
		$c   = TWD_SK_Contrast::check( $pack['tokens'] );
		$ids = array_map( function ( $i ) {
			return $i['id'];
		}, array_merge( $c['blocking'], $c['warnings'], $c['unchecked'] ) );
		twd_sk_true( ! in_array( 'frame_text', $ids, true ), $slug . ': the frame pair passes and is checked' );
	}
	// A pale frame over a pale overlay fails as a warning, never a block.
	$t = TWD_SK_Packs::packs()['sage']['tokens'];
	$t['color-frame']       = 'rgba(255,255,255,0.8)';
	$t['color-overlay-mid'] = 'rgba(255,255,255,0.8)';
	$c = TWD_SK_Contrast::check( $t );
	twd_sk_eq( array(), $c['blocking'] );
	$warn = array_map( function ( $i ) {
		return $i['id'];
	}, $c['warnings'] );
	twd_sk_true( in_array( 'frame_text', $warn, true ), 'warned' );
	// The browser reads the same layers: the pair carries them, and the script flattens them the same way.
	$pairs = TWD_SK_Site::state()['pairs'];
	$frame = null;
	foreach ( $pairs as $p ) {
		if ( 'frame_text' === $p['id'] ) {
			$frame = $p;
		}
	}
	twd_sk_eq( array( '#808080', 'color-overlay-mid' ), $frame['under'] );
	$js = file_get_contents( ABSPATH . 'assets/twd-site-kit-editor-site.js' );
	twd_sk_has( 'function flattenColor', $js );
	twd_sk_has( 'p.under', $js );
	if ( function_exists( 'shell_exec' ) && '' !== trim( (string) shell_exec( 'command -v node 2>/dev/null' ) ) ) {
		$script = 'const fs=require("fs");const src=fs.readFileSync(' . json_encode( ABSPATH . 'assets/twd-site-kit-editor-site.js' ) . ',"utf8");'
			. 'const m1=/function parseColor\\([\\s\\S]*?\\n\\t}\\n/.exec(src);const m2=/function flattenColor\\([\\s\\S]*?\\n\\t}\\n/.exec(src);const m3=/function stackColors\\([\\s\\S]*?\\n\\t}\\n/.exec(src);'
			. 'const f=new Function(m1[0]+m2[0]+m3[0]+"return [flattenColor(\'rgba(0,0,0,0.3)\',\'#FFFFFF\'), stackColors([\'#808080\',\'rgba(0,0,0,0.5)\'],{}), flattenColor(\'#ff000080\',\'#000000\')];");'
			. 'console.log(JSON.stringify(f()));';
		$out = json_decode( trim( (string) shell_exec( 'node -e ' . escapeshellarg( $script ) . ' 2>&1' ) ), true );
		twd_sk_eq( array( 'rgb(179,179,179)', 'rgb(64,64,64)', 'rgb(128,0,0)' ), $out, 'node' );
	}
} );

twd_sk_test( 'contrast: every box, border, quote line, note box and frame style reads at 4.5:1 on every section tone in every pack, and the stylesheet draws those pairs (18a)', function () {
	$pairs = TWD_SK_Contrast::style_pairs();
	twd_sk_true( count( $pairs ) >= 30, 'pairs: ' . count( $pairs ) );
	$styles = array();
	$tones  = array();
	foreach ( $pairs as $p ) {
		$styles[ $p[0] ] = true;
		$tones[ $p[1] ]  = true;
	}
	// Every style the Look panel or the Visual editor offers, on every tone a section can have, plus the picture layout.
	foreach ( array( 'twd-sk-notebox', 'twd-sk-quoteline', 'twd-sk-box-card', 'twd-sk-box-outline', 'twd-sk-border-thin', 'twd-sk-border-accent', 'twd-sk-frame', 'twd-sk-frame--light', 'twd-sk-frame--strong' ) as $style ) {
		twd_sk_true( isset( $styles[ $style ] ), 'style listed: ' . $style );
	}
	foreach ( array_keys( TWD_SK_Looks::tones() ) as $tone ) {
		twd_sk_true( isset( $tones[ $tone ] ), 'tone listed: ' . $tone );
	}
	twd_sk_true( isset( $tones['twd-sk-tone-band'], $tones['twd-sk-text--picture'] ) );
	foreach ( TWD_SK_Packs::packs() as $slug => $pack ) {
		foreach ( $pairs as $p ) {
			$ratio = TWD_SK_Contrast::ratio_for( $p, $pack['tokens'] );
			twd_sk_true( null !== $ratio, $slug . ': ' . $p[0] . ' on ' . $p[1] . ' readable' );
			twd_sk_true( $ratio >= TWD_SK_Contrast::MIN, $slug . ': ' . $p[0] . ' on ' . $p[1] . ' is ' . round( $ratio, 2 ) );
		}
	}
	// The stylesheet carries the tone rules the pairs describe (the band and the picture frame take the frame tint and the
	// tone's light text; the soft tone takes the page colour).
	$css = file_get_contents( ABSPATH . 'assets/twd-site-kit.css' );
	foreach ( array(
		'.twd-sk-page .twd-sk-tone-band.twd-sk-tone-band .twd-sk-notebox.twd-sk-notebox {',
		'.twd-sk-page .twd-sk-text--picture.twd-sk-text--picture .twd-sk-notebox.twd-sk-notebox {',
		'.twd-sk-page .twd-sk-tone-surface.twd-sk-tone-surface .twd-sk-notebox.twd-sk-notebox {',
		'.twd-sk-page .twd-sk-tone-band.twd-sk-tone-band.twd-sk-box-card.twd-sk-box-card > .twd-sk-inner.twd-sk-inner,',
		'.twd-sk-page .twd-sk-tone-surface.twd-sk-tone-surface.twd-sk-box-card.twd-sk-box-card > .twd-sk-inner.twd-sk-inner {',
		'.twd-sk-page .twd-sk-tone-band.twd-sk-tone-band .twd-sk-quoteline.twd-sk-quoteline {',
	) as $needle ) {
		twd_sk_has( $needle, $css );
	}
	$band = substr( $css, strpos( $css, '.twd-sk-page .twd-sk-tone-band.twd-sk-tone-band .twd-sk-notebox.twd-sk-notebox {' ) );
	$band = substr( $band, 0, strpos( $band, '}' ) );
	twd_sk_has( 'background-color: var(--twd-site-color-frame) !important', $band );
	twd_sk_has( 'color: var(--twd-site-color-on-band) !important', $band );
	// The fade helper scales the alpha; an unreadable colour gives null.
	twd_sk_eq( 'rgba(0,0,0,0.18)', TWD_SK_Contrast::with_alpha( 'rgba(0,0,0,0.3)', 0.6 ) );
	twd_sk_eq( 'rgba(255,255,255,0.5)', TWD_SK_Contrast::with_alpha( '#ffffff', 0.5 ) );
	twd_sk_eq( null, TWD_SK_Contrast::with_alpha( 'transparent-ish', 0.5 ) );
} );

twd_sk_test( 'shared text styles (18b): in every pack each colour is offered on a tone exactly when it reads at 4.5:1 there; Light is offered on a band and in the picture frame and never on the plain tone; Accent and Dark on the plain tone; Dark never on a band', function () {
	$colors = TWD_SK_Looks::text_colors();
	foreach ( TWD_SK_Packs::packs() as $slug => $pack ) {
		$allow = TWD_SK_Looks::text_colors_allowed( $pack['tokens'] );
		foreach ( TWD_SK_Looks::tone_backgrounds() as $tone => $bg ) {
			foreach ( $colors as $name => $c ) {
				$ratio = TWD_SK_Contrast::ratio_for( array( $name, $tone, $c[1], $bg[0], $bg[1] ), $pack['tokens'] );
				twd_sk_true( null !== $ratio, $slug . ' ' . $tone . ' ' . $name . ' judged' );
				twd_sk_eq( $ratio >= TWD_SK_Contrast::MIN, in_array( $name, $allow[ $tone ], true ), $slug . ' ' . $tone . ' ' . $name . ' at ' . round( $ratio, 2 ) );
			}
		}
		twd_sk_eq( array( 'light' ), $allow['twd-sk-tone-band'], $slug . ' band' );
		twd_sk_eq( array( 'light' ), $allow['twd-sk-text--picture'], $slug . ' picture' );
		twd_sk_true( ! in_array( 'light', $allow['twd-sk-tone-base'], true ), $slug . ' no light on plain' );
		twd_sk_true( in_array( 'accent', $allow['twd-sk-tone-base'], true ) && in_array( 'dark', $allow['twd-sk-tone-base'], true ), $slug . ' accent and dark on plain' );
		twd_sk_true( ! in_array( 'dark', $allow['twd-sk-tone-band'], true ), $slug . ' no dark on a band' );
	}
	// The sentence the editor shows names the colour.
	twd_sk_has( 'Light would not be readable', TWD_SK_Looks::text_color_problem( 'twd-sk-c-light', 'twd-sk-tone-base', TWD_SK_Packs::packs()['sage']['tokens'] ) );
	twd_sk_eq( '', TWD_SK_Looks::text_color_problem( 'twd-sk-c-light', 'twd-sk-tone-band', TWD_SK_Packs::packs()['sage']['tokens'] ) );
	twd_sk_eq( '', TWD_SK_Looks::text_color_problem( 'twd-sk-al-left', 'twd-sk-tone-base' ), 'not a colour: nothing to say' );
} );
