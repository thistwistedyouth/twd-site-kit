<?php
// Property checks across a set of hostile and messy inputs.

function twd_sk_hostile_inputs() {
	return array(
		'<p>plain</p>',
		'<img src=x onerror=alert(1)>',
		'<a href="javascript:alert(1)" onclick="x()">x</a>',
		'<svg><script>alert(1)</script></svg>',
		'<div style="background:url(javascript:alert(1))">x</div>',
		'<iframe srcdoc="<script>alert(1)</script>"></iframe>',
		'<p>[gallery][twd_page][embed]http://example.com[/embed]</p>',
		'<<script>script>alert(1)<</script>/script>',
		'<a href="&#106;&#97;&#118;&#97;&#115;&#99;&#114;&#105;&#112;&#116;&#58;alert(1)">x</a>',
		'<a href="//evil.example/x">x</a><img src="//evil.example/a.png">',
		'<math><mi xlink:href="javascript:alert(1)">x</mi></math>',
		'<body onload=alert(1)><p>x</p></body>',
		'<p class="twd-sk-text__body" data-x="1" style="x" id="y">mixed</p><!-- c -->',
		"<p>em \xE2\x80\x94 dash and en \xE2\x80\x93 dash</p>",
		'<details open ontoggle=alert(1)><summary>Q</summary>A</details>',
		'<form action="javascript:alert(1)"><button formaction="javascript:alert(1)">x</button></form>',
		'<a href="data:text/html;base64,PHNjcmlwdD5hbGVydCgxKTwvc2NyaXB0Pg==">x</a>',
		'<p>unclosed <strong>bold <em>both',
		'<table><tr><td onclick="x()">cell</td></tr></table>',
		"<a href=\"java\tscript:alert(1)\">x</a>",
	);
}

twd_sk_test( 'property: cleaning is idempotent, cleaning clean output changes nothing', function () {
	foreach ( twd_sk_hostile_inputs() as $in ) {
		$once  = TWD_SK_Sanitizer::clean( $in );
		$twice = TWD_SK_Sanitizer::clean( $once );
		twd_sk_eq( $once, $twice, 'idempotent for: ' . $in );
	}
} );

twd_sk_test( 'property: cleaning clean output reports nothing removed', function () {
	foreach ( twd_sk_hostile_inputs() as $in ) {
		$once = TWD_SK_Sanitizer::clean( $in );
		$r    = TWD_SK_Sanitizer::clean_with_report( $once );
		twd_sk_eq( 0, $r['report']['total'], 'second pass report for: ' . $in . ' => ' . json_encode( $r['report']['removed'] ) );
	}
} );

twd_sk_test( 'property: output never contains script, handlers, styles, javascript: or disallowed tags', function () {
	foreach ( twd_sk_hostile_inputs() as $in ) {
		$out = TWD_SK_Sanitizer::clean( $in );
		twd_sk_true( 0 === preg_match( '/<\s*(script|iframe|object|embed|form|input|button|svg|math|style|link|meta|table|td|tr)\b/i', $out ), 'bad tag in output of: ' . $in . ' => ' . $out );
		twd_sk_true( 0 === preg_match( '/\son[a-z]+\s*=/i', $out ), 'handler in output of: ' . $in . ' => ' . $out );
		twd_sk_true( 0 === preg_match( '/\sstyle\s*=/i', $out ), 'style in output of: ' . $in . ' => ' . $out );
		twd_sk_true( false === stripos( preg_replace( '/[\s\x00-\x1f]+/', '', $out ), 'javascript:' ), 'javascript: in output of: ' . $in . ' => ' . $out );
		twd_sk_true( 0 === preg_match( '/(?:href|src)\s*=\s*"\s*(?:\/\/|data:|vbscript:)/i', $out ), 'bad url in output of: ' . $in . ' => ' . $out );
		twd_sk_true( false === strpos( $out, "\xE2\x80\x94" ) && false === strpos( $out, "\xE2\x80\x93" ), 'dash in output of: ' . $in );
		twd_sk_true( false === strpos( $out, '[gallery' ) && false === strpos( $out, '[twd_page' ) && false === strpos( $out, '[embed' ), 'shortcode in output of: ' . $in );
	}
} );

twd_sk_test( 'property: output is always valid UTF-8', function () {
	foreach ( twd_sk_hostile_inputs() as $in ) {
		twd_sk_true( 1 === preg_match( '//u', TWD_SK_Sanitizer::clean( $in ) ), 'utf8 for: ' . $in );
	}
} );
