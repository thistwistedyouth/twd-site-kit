<?php
// Sanitiser tests: one or more tests for every rule.

function twd_sk_clean( $html ) {
	return TWD_SK_Sanitizer::clean( $html );
}
function twd_sk_rep( $html ) {
	return TWD_SK_Sanitizer::clean_with_report( $html );
}

// -- Basics ------------------------------------------------------------------

twd_sk_test( 'sanitiser: non-string and empty input give an empty string', function () {
	twd_sk_eq( '', twd_sk_clean( null ) );
	twd_sk_eq( '', twd_sk_clean( array( 'x' ) ) );
	twd_sk_eq( '', twd_sk_clean( '' ) );
	twd_sk_eq( '', twd_sk_clean( "  \n\t " ) );
} );

twd_sk_test( 'sanitiser: clean allowed markup passes through unchanged', function () {
	$in = '<p class="twd-sk-text__body">Hello <strong>there</strong> and <em>you</em>.</p>';
	twd_sk_eq( $in, twd_sk_clean( $in ) );
} );

twd_sk_test( 'sanitiser: loose text at the top level is wrapped in a paragraph so CSS can style it', function () {
	twd_sk_eq( '<p>Just words</p>', twd_sk_clean( 'Just words' ) );
	twd_sk_eq( '<p>Hello <b>there</b></p><section class="twd-sk-text">in</section><p>tail</p>', twd_sk_clean( 'Hello <b>there</b><section class="twd-sk-text">in</section>tail' ) );
	// Text inside an element, whitespace and a lone link are not touched.
	twd_sk_eq( '<p>in a p</p>', twd_sk_clean( '<p>in a p</p>' ) );
	twd_sk_eq( '<a href="#x">link</a>', twd_sk_clean( '<a href="#x">link</a>' ) );
	twd_sk_eq( "<p>a</p>\n<p>b</p>", twd_sk_clean( "<p>a</p>\n<p>b</p>" ) );
} );

twd_sk_test( 'sanitiser: [PLACEHOLDER] and [PLACEHOLDER: words] are kept as plain text, other brackets still go', function () {
	twd_sk_eq( '<p>[PLACEHOLDER] [PLACEHOLDER: registration number]</p>', twd_sk_clean( '<p>[PLACEHOLDER] [PLACEHOLDER: registration number]</p>' ) );
	$out = twd_sk_clean( '<p>[PLACEHOLDERX] [gallery ids="1"] [PLACEHOLDER: a [b] c]</p>' );
	twd_sk_hasnt( 'PLACEHOLDERX', $out );
	twd_sk_hasnt( 'gallery', $out );
	twd_sk_hasnt( '[b]', $out );
} );

twd_sk_test( 'sanitiser: clean() returns exactly the html from clean_with_report()', function () {
	$in = '<p onclick="x()">Hi</p><script>bad()</script>';
	twd_sk_eq( twd_sk_rep( $in )['html'], twd_sk_clean( $in ) );
} );

// -- Scripts and dangerous elements -----------------------------------------

twd_sk_test( 'sanitiser: script tags are removed with their contents', function () {
	$out = twd_sk_clean( '<p>Hi</p><script>alert(1)</script><p>Bye</p>' );
	twd_sk_eq( '<p>Hi</p><p>Bye</p>', $out );
} );

twd_sk_test( 'sanitiser: style, iframe, object, embed, form, input, button, svg are removed with contents', function () {
	foreach ( array(
		'<style>p{color:red}</style>',
		'<iframe src="https://evil.example"></iframe>',
		'<object data="x"></object>',
		'<embed src="x">',
		'<form action="/x"><input name="a"><button>Go</button></form>',
		'<svg onload="x()"><circle r="1"></circle></svg>',
		'<noscript>hidden</noscript>',
		'<template><p>x</p></template>',
		'<video src="x"></video>',
	) as $bad ) {
		$out = twd_sk_clean( '<p>Keep</p>' . $bad );
		twd_sk_eq( '<p>Keep</p>', $out, 'removed: ' . $bad );
	}
} );

twd_sk_test( 'sanitiser: html comments are removed', function () {
	twd_sk_eq( '<p>Hi</p>', twd_sk_clean( '<p>Hi</p><!-- secret --><!--[if IE]><script>x</script><![endif]-->' ) );
} );

twd_sk_test( 'sanitiser: unknown tags are unwrapped and their text is kept', function () {
	twd_sk_eq( '<p>Hello world</p>', twd_sk_clean( '<p><font color="red">Hello</font> <marquee>world</marquee></p>' ) );
} );

twd_sk_test( 'sanitiser: stray html, head and body tags in the input do not survive', function () {
	$out = twd_sk_clean( '<html><head><title>T</title></head><body onload="x()"><p>Hi</p></body></html>' );
	twd_sk_eq( '<p>Hi</p>', $out );
} );

twd_sk_test( 'sanitiser: an early closing div cannot push content outside the walk', function () {
	$out = twd_sk_clean( '</div><script>alert(1)</script><p onclick="x()">Hi</p>' );
	twd_sk_hasnt( '<script', $out );
	twd_sk_hasnt( 'onclick', $out );
	twd_sk_has( 'Hi', $out );
} );

// -- h1 demotion ------------------------------------------------------------

twd_sk_test( 'sanitiser: h1 is demoted to h2, keeping its text and allowed class', function () {
	twd_sk_eq(
		'<h2 class="twd-sk-hero__title">Welcome</h2>',
		twd_sk_clean( '<h1 class="twd-sk-hero__title">Welcome</h1>' )
	);
} );

twd_sk_test( 'sanitiser: a demoted h1 still has its bad attributes removed', function () {
	$out = twd_sk_clean( '<h1 onclick="x()" style="color:red">Hi</h1>' );
	twd_sk_eq( '<h2>Hi</h2>', $out );
} );

twd_sk_test( 'sanitiser: an h1 carrying odd attribute names does not break the demotion', function () {
	twd_sk_eq( '<h2 class="twd-sk-hero__title">Hi</h2>', twd_sk_clean( '<h1 class="twd-sk-hero__title" xlink:href="x" xmlns:a="y" 0bad="z">Hi</h1>' ) );
} );

twd_sk_test( 'sanitiser: h2 to h6 are kept as they are', function () {
	twd_sk_eq( '<h2>A</h2><h3>B</h3><h4>C</h4><h5>D</h5><h6>E</h6>', twd_sk_clean( '<h2>A</h2><h3>B</h3><h4>C</h4><h5>D</h5><h6>E</h6>' ) );
} );

// -- Attributes ---------------------------------------------------------------

twd_sk_test( 'sanitiser: event handler attributes are removed', function () {
	foreach ( array( 'onclick', 'onerror', 'onload', 'onmouseover', 'onfocus', 'ONCLICK' ) as $h ) {
		$out = twd_sk_clean( '<p ' . $h . '="alert(1)">Hi</p>' );
		twd_sk_eq( '<p>Hi</p>', $out, $h );
	}
	twd_sk_eq( '<img src="/a.png" alt="x">', twd_sk_clean( '<img src="/a.png" alt="x" onerror="alert(1)">' ) );
} );

twd_sk_test( 'sanitiser: inline style attributes are removed', function () {
	twd_sk_eq( '<p>Hi</p>', twd_sk_clean( '<p style="color:red;position:fixed">Hi</p>' ) );
	twd_sk_eq( '<p class="twd-sk-text__body">Hi</p>', twd_sk_clean( '<p class="twd-sk-text__body" style="x">Hi</p>' ) );
} );

twd_sk_test( 'sanitiser: unlisted attributes (data-, aria-, srcset, name, xlink:href) are removed', function () {
	$out = twd_sk_clean( '<p data-x="1" aria-label="a" role="b" contenteditable="true">Hi</p><img src="/a.png" alt="" srcset="x 2x" sizes="y">' );
	twd_sk_eq( '<p>Hi</p><img src="/a.png" alt="">', $out );
	twd_sk_hasnt( 'xlink', twd_sk_clean( '<a href="/x" xlink:href="javascript:alert(1)" xmlns:xlink="http://www.w3.org/1999/xlink">x</a>' ) );
} );

twd_sk_test( 'sanitiser: target other than _blank is removed; _blank forces rel noopener noreferrer', function () {
	twd_sk_eq( '<a href="/x">x</a>', twd_sk_clean( '<a href="/x" target="_top">x</a>' ) );
	twd_sk_eq( '<a href="/x" target="_blank" rel="noopener noreferrer">x</a>', twd_sk_clean( '<a href="/x" target="_blank">x</a>' ) );
} );

twd_sk_test( 'sanitiser: rel keeps only known tokens and always adds noopener noreferrer with _blank', function () {
	$out = twd_sk_clean( '<a href="/x" target="_blank" rel="nofollow evil">x</a>' );
	twd_sk_has( 'nofollow', $out );
	twd_sk_has( 'noopener', $out );
	twd_sk_has( 'noreferrer', $out );
	twd_sk_hasnt( 'evil', $out );
} );

twd_sk_test( 'sanitiser: width and height must be plain numbers', function () {
	twd_sk_eq( '<img src="/a.png" alt="" width="300" height="200">', twd_sk_clean( '<img src="/a.png" alt="" width="300" height="200">' ) );
	twd_sk_eq( '<img src="/a.png" alt="">', twd_sk_clean( '<img src="/a.png" alt="" width="100%" height="expression(alert(1))">' ) );
} );

twd_sk_test( 'sanitiser: id is kept only with the twd-sk- prefix and a safe shape', function () {
	twd_sk_eq( '<div id="twd-sk-faq">x</div>', twd_sk_clean( '<div id="twd-sk-faq">x</div>' ) );
	twd_sk_eq( '<div>x</div>', twd_sk_clean( '<div id="location">x</div>' ) );
	twd_sk_eq( '<div>x</div>', twd_sk_clean( '<div id="twd-sk-A B">x</div>' ) );
} );

twd_sk_test( 'sanitiser: details open attribute is kept', function () {
	twd_sk_eq( '<details open=""><summary>Q</summary>A</details>', twd_sk_clean( '<details open><summary>Q</summary>A</details>' ) );
} );

// -- Classes ------------------------------------------------------------------

twd_sk_test( 'sanitiser: unknown classes are removed, known classes kept', function () {
	twd_sk_eq(
		'<p class="twd-sk-text__body">Hi</p>',
		twd_sk_clean( '<p class="twd-sk-text__body elementor-widget evil">Hi</p>' )
	);
} );

twd_sk_test( 'sanitiser: class attribute is removed when no class survives', function () {
	twd_sk_eq( '<p>Hi</p>', twd_sk_clean( '<p class="nope also-nope">Hi</p>' ) );
} );

twd_sk_test( 'sanitiser: class matching is exact and case-sensitive', function () {
	twd_sk_eq( '<p>Hi</p>', twd_sk_clean( '<p class="TWD-SK-TEXT__BODY">Hi</p>' ) );
	twd_sk_eq( '<p>Hi</p>', twd_sk_clean( '<p class="twd-sk-text__body-extra">Hi</p>' ) );
} );

twd_sk_test( 'sanitiser: duplicate classes collapse to one', function () {
	twd_sk_eq( '<p class="twd-sk-text__body">Hi</p>', twd_sk_clean( '<p class="twd-sk-text__body twd-sk-text__body">Hi</p>' ) );
} );

twd_sk_test( 'sanitiser: the class allowlist is generated from the registry', function () {
	foreach ( TWD_SK_Registry::allowed_classes() as $class => $yes ) {
		twd_sk_eq( '<p class="' . $class . '">x</p>', twd_sk_clean( '<p class="' . $class . '">x</p>' ), $class );
	}
} );

// -- URLs ---------------------------------------------------------------------

twd_sk_test( 'sanitiser: javascript:, data:, vbscript:, file: links are stripped', function () {
	foreach ( array(
		'javascript:alert(1)',
		'JaVaScRiPt:alert(1)',
		'  javascript:alert(1)',
		"java\tscript:alert(1)",
		"java\nscript:alert(1)",
		'java&#x09;script:alert(1)',
		'&#106;avascript:alert(1)',
		'data:text/html,<script>alert(1)</script>',
		'vbscript:msgbox(1)',
		'file:///etc/passwd',
		"\xC2\xA0javascript:alert(1)",
		"java\xE2\x80\x8Bscript:alert(1)",
		'ftp://example.com/x',
		'1abc:thing',
	) as $url ) {
		$out = twd_sk_clean( '<a href="' . $url . '">x</a>' );
		twd_sk_eq( '<a>x</a>', $out, 'blocked: ' . $url );
	}
} );

twd_sk_test( 'sanitiser: HTML5 named entities the parser does not decode cannot turn into a scheme', function () {
	// libxml leaves &colon; and &Tab; as literal text, and the output escapes the ampersand,
	// so a browser sees a harmless relative URL, never javascript:
	foreach ( array( 'javascript&colon;alert(1)', 'java&Tab;script:alert(1)', 'java&NewLine;script:alert(1)' ) as $url ) {
		$out = twd_sk_clean( '<a href="' . $url . '">x</a>' );
		twd_sk_hasnt( 'javascript:', $out, $url );
		twd_sk_hasnt( 'script:alert', $out, $url );
	}
} );

twd_sk_test( 'sanitiser: http, https, mailto, tel links are kept', function () {
	foreach ( array(
		'https://example.com/page?a=1&b=2',
		'http://example.com',
		'mailto:someone@example.com',
		'tel:+441234567890',
		'HTTPS://EXAMPLE.COM',
	) as $url ) {
		$out = twd_sk_clean( '<a href="' . htmlspecialchars( $url ) . '">x</a>' );
		twd_sk_has( 'href=', $out, 'kept: ' . $url );
	}
} );

twd_sk_test( 'sanitiser: relative paths and #anchors are kept', function () {
	foreach ( array( '/contact', '/a/b?x=1#y', '#faq', '#twd-sk-services', '?page=2', 'contact', './x', '../x', '/wp-content/uploads/a.jpg' ) as $url ) {
		twd_sk_eq( '<a href="' . $url . '">x</a>', twd_sk_clean( '<a href="' . $url . '">x</a>' ), 'kept: ' . $url );
	}
} );

twd_sk_test( 'sanitiser: protocol-relative URLs are blocked', function () {
	foreach ( array( '//evil.example', '//evil.example/path', '/\\evil.example', '\\/evil.example', '\\\\evil.example', "/\t/evil.example", " //evil.example", "\n//evil.example" ) as $url ) {
		twd_sk_eq( '<a>x</a>', twd_sk_clean( '<a href="' . htmlspecialchars( $url ) . '">x</a>' ), 'blocked: ' . json_encode( $url ) );
	}
	twd_sk_eq( '<img alt="">', '<img alt="">' ); // sanity, keeps the next test honest
	twd_sk_eq( '', twd_sk_clean( '<img src="//evil.example/a.png" alt="">' ), 'img src protocol-relative' );
} );

twd_sk_test( 'sanitiser: http(s) URLs need a real host and no user info', function () {
	foreach ( array( 'http://', 'https:///x', 'https:example.com', 'http:\\\\example.com', 'https://user@evil.example/', 'https://:80/x', 'https://[::1]/' ) as $url ) {
		twd_sk_eq( '<a>x</a>', twd_sk_clean( '<a href="' . htmlspecialchars( $url ) . '">x</a>' ), 'blocked: ' . $url );
	}
} );

twd_sk_test( 'sanitiser: mailto and tel need something after the colon', function () {
	twd_sk_eq( '<a>x</a>', twd_sk_clean( '<a href="mailto:">x</a>' ) );
	twd_sk_eq( '<a>x</a>', twd_sk_clean( '<a href="tel:">x</a>' ) );
} );

twd_sk_test( 'sanitiser: an empty href is removed', function () {
	twd_sk_eq( '<a>x</a>', twd_sk_clean( '<a href="">x</a>' ) );
	twd_sk_eq( '<a>x</a>', twd_sk_clean( '<a href="   ">x</a>' ) );
} );

twd_sk_test( 'sanitiser: image src allows http, https and relative, nothing else', function () {
	twd_sk_eq( '<img src="https://example.com/a.png" alt="">', twd_sk_clean( '<img src="https://example.com/a.png" alt="">' ) );
	twd_sk_eq( '<img src="http://example.com/a.png" alt="">', twd_sk_clean( '<img src="http://example.com/a.png" alt="">' ) );
	twd_sk_eq( '<img src="/wp-content/uploads/a.png" alt="">', twd_sk_clean( '<img src="/wp-content/uploads/a.png" alt="">' ) );
	foreach ( array( 'javascript:alert(1)', 'data:image/svg+xml,<svg onload=alert(1)>', 'mailto:a@example.com', 'tel:123', 'ftp://x/y.png' ) as $src ) {
		twd_sk_eq( '', twd_sk_clean( '<img src="' . htmlspecialchars( $src ) . '" alt="x">' ), 'blocked: ' . $src );
	}
} );

twd_sk_test( 'sanitiser: an image with no src at all is dropped', function () {
	twd_sk_eq( '<p>Hi</p>', twd_sk_clean( '<p>Hi</p><img alt="x">' ) );
} );

twd_sk_test( 'sanitiser: URLs with square brackets are refused', function () {
	twd_sk_eq( '<a>x</a>', twd_sk_clean( '<a href="/x?a=[gallery]">x</a>' ) );
} );

// -- Shortcodes ---------------------------------------------------------------

twd_sk_test( 'sanitiser: shortcodes not in the registry are removed from text', function () {
	twd_sk_eq( '<p>Before  after</p>', twd_sk_clean( '<p>Before [gallery ids="1,2"] after</p>' ) );
	twd_sk_eq( '<p>ab</p>', twd_sk_clean( '<p>a[contact-form-7 id="5"]b</p>' ) );
	twd_sk_eq( '<p>ab</p>', twd_sk_clean( '<p>a[embed]b</p>' ) );
} );

twd_sk_test( 'sanitiser: closing tags and self-closing shortcodes are removed', function () {
	twd_sk_eq( '<p>ab</p>', twd_sk_clean( '<p>a[caption]b[/caption]</p>' ) );
	twd_sk_eq( '<p>ab</p>', twd_sk_clean( '<p>a[foo /]b</p>' ) );
	twd_sk_eq( '<p>ab</p>', twd_sk_clean( '<p>a[foo="x"]b</p>' ) );
} );

twd_sk_test( 'sanitiser: escaped [[shortcode]] forms are removed', function () {
	twd_sk_eq( '<p></p>', twd_sk_clean( '<p>[[gallery]]</p>' ) );
	twd_sk_eq( '<p></p>', twd_sk_clean( '<p>[[twd_articles]]</p>' ) );
} );

twd_sk_test( 'sanitiser: [twd_page] is never allowed inside a page', function () {
	twd_sk_eq( '<p></p>', twd_sk_clean( '<p>[twd_page]</p>' ) );
} );

twd_sk_test( 'sanitiser: [twd_articles] is kept, rebuilt with only valid attributes', function () {
	twd_sk_eq( '<p>[twd_articles]</p>', twd_sk_clean( '<p>[twd_articles]</p>' ) );
	twd_sk_eq(
		'<div>[twd_articles category="Anxiety" count="6" columns="3"]</div>',
		twd_sk_clean( '<div>[twd_articles columns="3" count="6" category="Anxiety"]</div>' )
	);
	twd_sk_eq( '<div>[twd_articles tag="sleep"]</div>', twd_sk_clean( "<div>[twd_articles tag='sleep']</div>" ) );
} );

twd_sk_test( 'sanitiser: [twd_articles] unknown or invalid attributes are dropped and reported', function () {
	$r = twd_sk_rep( '<div>[twd_articles count="6" onclick="x" columns="9" category="a&lt;b"]</div>' );
	twd_sk_eq( '<div>[twd_articles count="6"]</div>', $r['html'] );
	twd_sk_true( $r['report']['counts']['shortcodes'] >= 2, 'reported' );
} );

twd_sk_test( 'sanitiser: [twd_articles] closing tag is removed', function () {
	twd_sk_eq( '<div>[twd_articles]</div>', twd_sk_clean( '<div>[twd_articles][/twd_articles]</div>' ) );
} );

twd_sk_test( 'sanitiser: shortcodes inside alt and title attributes are removed', function () {
	$out = twd_sk_clean( '<img src="/a.png" alt="x [gallery] y" title="[twd_page]">' );
	twd_sk_hasnt( '[', $out );
} );

twd_sk_test( 'sanitiser: ordinary square brackets that are not shortcodes are left alone', function () {
	twd_sk_eq( '<p>See note [1] and [2].</p>', twd_sk_clean( '<p>See note [1] and [2].</p>' ) );
} );

// -- Dashes -------------------------------------------------------------------

twd_sk_test( 'sanitiser: em and en dashes become a comma and a single space', function () {
	twd_sk_eq( '<p>one, two</p>', twd_sk_clean( "<p>one \u{2014} two</p>" ) );
	twd_sk_eq( '<p>one, two</p>', twd_sk_clean( "<p>one\u{2013}two</p>" ) );
	twd_sk_eq( '<p>one, two</p>', twd_sk_clean( '<p>one &mdash; two</p>' ) );
	twd_sk_eq( '<p>one, two</p>', twd_sk_clean( '<p>one &ndash; two</p>' ) );
	twd_sk_eq( '<p>one, two</p>', twd_sk_clean( '<p>one &#8212; two</p>' ) );
	twd_sk_eq( '<p>one, two</p>', twd_sk_clean( '<p>one &#x2013; two</p>' ) );
} );

twd_sk_test( 'sanitiser: a dash right before closing punctuation is dropped', function () {
	twd_sk_eq( '<p>Wait.</p>', twd_sk_clean( "<p>Wait \u{2014}.</p>" ) );
	twd_sk_eq( '<p>Well, yes</p>', twd_sk_clean( "<p>Well \u{2014}, yes</p>" ) );
} );

twd_sk_test( 'sanitiser: dashes in alt and title attributes are stripped too', function () {
	twd_sk_eq( '<img src="/a.png" alt="a, b" title="c, d">', twd_sk_clean( "<img src=\"/a.png\" alt=\"a \u{2014} b\" title=\"c \u{2013} d\">" ) );
} );

twd_sk_test( 'sanitiser: dashes inside URLs are never turned into commas', function () {
	// The serialiser percent-encodes non-ASCII bytes in href, which means the same URL.
	$out = twd_sk_clean( "<a href=\"/a\u{2013}b\">x</a>" );
	twd_sk_eq( '<a href="/a%E2%80%93b">x</a>', $out );
	twd_sk_eq( $out, twd_sk_clean( $out ), 'encoded form is stable when cleaned again' );
} );

twd_sk_test( 'sanitiser: ordinary hyphens are untouched', function () {
	twd_sk_eq( '<p>well-known 9-5</p>', twd_sk_clean( '<p>well-known 9-5</p>' ) );
} );

// -- Report -------------------------------------------------------------------

twd_sk_test( 'report: has the documented shape and a clean input reports nothing', function () {
	$r = twd_sk_rep( '<p class="twd-sk-text__body">Fine</p>' );
	twd_sk_eq( 0, $r['report']['total'] );
	twd_sk_eq( array( 'classes', 'tags', 'attributes', 'urls', 'shortcodes', 'dashes' ), array_keys( $r['report']['counts'] ) );
	twd_sk_eq( array( 'classes', 'tags', 'attributes', 'urls', 'shortcodes', 'dashes' ), array_keys( $r['report']['removed'] ) );
} );

twd_sk_test( 'report: lists removed classes', function () {
	$r = twd_sk_rep( '<p class="twd-sk-text__body foo bar">x</p>' );
	twd_sk_eq( array( 'foo', 'bar' ), $r['report']['removed']['classes'] );
	twd_sk_eq( 2, $r['report']['counts']['classes'] );
} );

twd_sk_test( 'report: lists removed tags, including demoted h1 and comments', function () {
	$r = twd_sk_rep( '<h1>A</h1><script>x</script><font>y</font><!-- c -->' );
	twd_sk_eq( array( 'h1 (demoted to h2)', 'script', 'font', 'comment' ), $r['report']['removed']['tags'] );
} );

twd_sk_test( 'report: lists removed attributes by name and tag', function () {
	$r = twd_sk_rep( '<p onclick="x" style="y" data-a="1">x</p>' );
	twd_sk_eq( array( 'onclick on <p>', 'style on <p>', 'data-a on <p>' ), $r['report']['removed']['attributes'] );
} );

twd_sk_test( 'report: lists bad URLs', function () {
	$r = twd_sk_rep( '<a href="javascript:alert(1)">x</a><img src="data:x" alt="">' );
	twd_sk_eq( array( 'href: javascript:alert(1)', 'src: data:x' ), $r['report']['removed']['urls'] );
} );

twd_sk_test( 'report: long URLs are shortened in the report', function () {
	$r = twd_sk_rep( '<a href="javascript:' . str_repeat( 'a', 300 ) . '">x</a>' );
	twd_sk_true( strlen( $r['report']['removed']['urls'][0] ) < 100 );
} );

twd_sk_test( 'report: lists removed shortcodes', function () {
	$r = twd_sk_rep( '<p>[gallery ids="1"] [contact-form-7 id="2"]</p>' );
	twd_sk_eq( 2, $r['report']['counts']['shortcodes'] );
	twd_sk_has( 'gallery', $r['report']['removed']['shortcodes'][0] );
} );

twd_sk_test( 'report: counts every dash with a little context', function () {
	$r = twd_sk_rep( "<p>one \u{2014} two and three \u{2013} four</p>" );
	twd_sk_eq( 2, $r['report']['counts']['dashes'] );
	twd_sk_has( 'one', $r['report']['removed']['dashes'][0] );
} );

twd_sk_test( 'report: total is the sum of the category counts', function () {
	$r = twd_sk_rep( '<p class="x" onclick="y">[gallery]<script>z</script></p>' );
	twd_sk_eq( array_sum( $r['report']['counts'] ), $r['report']['total'] );
	twd_sk_true( $r['report']['total'] >= 4 );
} );

// -- UTF-8 (DOMDocument must not mangle these) --------------------------------

twd_sk_test( 'utf8: curly quotes survive byte for byte', function () {
	$in = "<p>\u{201C}Hello,\u{201D} she said. It\u{2019}s \u{2018}fine\u{2019}.</p>";
	twd_sk_eq( $in, twd_sk_clean( $in ) );
} );

twd_sk_test( 'utf8: the pound sign survives', function () {
	$in = "<p>Sessions from \u{00A3}50.</p>";
	twd_sk_eq( $in, twd_sk_clean( $in ) );
} );

twd_sk_test( 'utf8: accented letters survive', function () {
	$in = "<p>caf\u{00E9} na\u{00EF}ve \u{00FC}ber se\u{00F1}or \u{00C5}ngstr\u{00F6}m \u{0141}\u{00F3}d\u{017A}</p>";
	twd_sk_eq( $in, twd_sk_clean( $in ) );
} );

twd_sk_test( 'utf8: a four-byte emoji survives', function () {
	$in = "<p>Well done \u{1F600} and \u{1F44D}</p>";
	twd_sk_eq( $in, twd_sk_clean( $in ) );
} );

twd_sk_test( 'utf8: everything together, in text and in alt and title attributes', function () {
	$mix = "\u{201C}Caf\u{00E9}\u{201D} \u{00A3}5 \u{1F600}";
	$in  = '<img src="/a.png" alt="' . $mix . '" title="' . $mix . '"><p>' . $mix . '</p>';
	twd_sk_eq( $in, twd_sk_clean( $in ) );
} );

twd_sk_test( 'utf8: no character is turned into an html entity on the way out', function () {
	$out = twd_sk_clean( "<p>\u{201C}caf\u{00E9}\u{201D} \u{00A3}5 \u{1F600}</p>" );
	twd_sk_true( 0 === preg_match( '/&(#\d+|#x[0-9a-f]+|[a-z]+);/i', $out ), 'no entities in: ' . $out );
} );

twd_sk_test( 'utf8: an emoji in a removed-dash sentence still survives next to the change', function () {
	$out = twd_sk_clean( "<p>Great \u{2014} \u{1F600}</p>" );
	twd_sk_eq( "<p>Great, \u{1F600}</p>", $out );
} );

twd_sk_test( 'utf8: html special characters are escaped correctly and not double-escaped', function () {
	twd_sk_eq( '<p>Fish &amp; chips &lt;3</p>', twd_sk_clean( '<p>Fish &amp; chips &lt;3</p>' ) );
	twd_sk_eq( '<p>Fish &amp; chips</p>', twd_sk_clean( '<p>Fish & chips</p>' ) );
} );

twd_sk_test( 'utf8: invalid UTF-8 bytes do not crash and valid text around them is kept', function () {
	$out = twd_sk_clean( "<p>ok \xFF\xFE bytes</p>" );
	twd_sk_has( 'ok', $out );
	twd_sk_has( 'bytes', $out );
	twd_sk_true( 1 === preg_match( '//u', $out ), 'output is valid UTF-8' );
} );

twd_sk_test( 'utf8: a byte order mark and control characters are stripped', function () {
	twd_sk_eq( '<p>Hi</p>', twd_sk_clean( "\xEF\xBB\xBF<p>H\x00i\x08</p>" ) );
} );

// -- Independence and house rules ---------------------------------------------

twd_sk_test( 'independence: the sanitiser never references the articles plugin', function () {
	$src = file_get_contents( dirname( __DIR__ ) . '/includes/class-twd-sk-sanitizer.php' );
	twd_sk_true( 0 === preg_match( '/TWD_AP_|twd-publisher|twd_ap_/', $src ), 'no TWD_AP_ references' );
	twd_sk_true( ! class_exists( 'TWD_AP_Sanitizer', false ), 'articles sanitiser is not even loaded' );
} );

twd_sk_test( 'independence: no file in the plugin references TWD_AP_ code', function () {
	foreach ( glob( dirname( __DIR__ ) . '/includes/*.php' ) as $file ) {
		$src = file_get_contents( $file );
		twd_sk_true( 0 === preg_match( '/TWD_AP_/', $src ), basename( $file ) . ' has no TWD_AP_' );
	}
} );

twd_sk_test( 'independence: the sanitiser has its own strip_dashes()', function () {
	$m = new ReflectionMethod( 'TWD_SK_Sanitizer', 'strip_dashes' );
	twd_sk_true( $m->isPublic() && $m->isStatic() );
	twd_sk_eq( 'a, b', TWD_SK_Sanitizer::strip_dashes( "a \u{2014} b" ) );
} );

twd_sk_test( 'house rules: no em or en dash appears anywhere in the source files', function () {
	$root = dirname( __DIR__ );
	$iter = new RecursiveIteratorIterator( new RecursiveDirectoryIterator( $root, FilesystemIterator::SKIP_DOTS ) );
	foreach ( $iter as $file ) {
		$path = $file->getPathname();
		if ( false !== strpos( $path, '/.git/' ) || false !== strpos( $path, '/dist/' ) ) {
			continue;
		}
		if ( ! preg_match( '/\.(php|md|txt|json|yml|yaml)$/', $path ) ) {
			continue;
		}
		$src = file_get_contents( $path );
		twd_sk_true( false === strpos( $src, "\xE2\x80\x94" ) && false === strpos( $src, "\xE2\x80\x93" ), 'no em or en dash in ' . substr( $path, strlen( $root ) + 1 ) );
	}
} );

twd_sk_test( 'leftovers: every example marker is found, case-insensitively, and counted', function () {
	$html = '<p>Heading here</p><p>paragraph TEXT here</p><a href="mailto:you@example.com">x</a><p>Mail Example.org</p><p>or example.net</p><p>Quote text here, Another quote here</p><p>Name and context</p><a href="tel:PHONE_NUMBER">y</a><span>Short label here</span>'
		. '<img src="/wp-content/uploads/your-image.jpg" alt="Describe the image"><img src="/wp-content/uploads/your-badge.png" alt="x"><a href="/service-2">s</a>'
		. '<a href="/contact">CONTACT me about a first session</a><a href="/s">Find out about my services</a><a href="/a">Read more about this approach</a><a href="tel:1">Call me to arrange a first session</a>'
		. '<p>Question here?</p><p>Answer here.</p><p>Topic one</p><p>Short description here.</p><p>Short introduction here.</p><h3>Service name here</h3><p>A short statement in your own words.</p>'
		. '<p>[PLACEHOLDER: fee]</p><p>[PLACEHOLDER]</p>';
	$found = TWD_SK_Sanitizer::find_leftovers( $html );
	foreach ( TWD_SK_Sanitizer::leftover_markers() as $marker ) {
		twd_sk_true( isset( $found[ $marker ] ), 'marker not found: ' . $marker );
	}
	twd_sk_eq( 2, $found['[PLACEHOLDER'] );
	foreach ( array( 'example.com', 'PHONE_NUMBER', 'Short label here', 'Heading here', 'Paragraph text here', 'Describe the image', 'Quote text here', 'Another quote here', 'Name and context', 'Question here', 'Answer here', 'Topic one', 'Short description here', 'Short introduction here', 'Service name here', 'A short statement in your own words', 'your-image', 'your-badge', '/service-N', 'Contact me about a first session', 'Find out about my services', 'Read more about this approach', 'Call me to arrange a first session' ) as $must ) {
		twd_sk_true( in_array( $must, TWD_SK_Sanitizer::leftover_markers(), true ), 'required marker: ' . $must );
	}
	twd_sk_eq( array(), TWD_SK_Sanitizer::find_leftovers( '<p>Real words about a real practice.</p>' ) );
	twd_sk_eq( array(), TWD_SK_Sanitizer::find_leftovers( '' ) );
} );

twd_sk_test( 'leftovers: example.com is caught in any form (email, www, path, case), your-image and your-badge with any extension', function () {
	foreach ( array( 'you@example.com', 'https://example.com', 'https://www.example.com/directions', 'EXAMPLE.COM', 'Visit example.com today' ) as $form ) {
		twd_sk_true( isset( TWD_SK_Sanitizer::find_leftovers( '<p>' . $form . '</p>' )['example.com'] ), $form );
	}
	foreach ( array( '/wp-content/uploads/your-image.jpg', '/x/your-image-2.png', '/x/YOUR-IMAGE.webp' ) as $src ) {
		twd_sk_true( isset( TWD_SK_Sanitizer::find_leftovers( '<img src="' . $src . '" alt="x">' )['your-image'] ), $src );
	}
	foreach ( array( '/x/your-badge.png', '/x/your-badge.svg' ) as $src ) {
		twd_sk_true( isset( TWD_SK_Sanitizer::find_leftovers( '<img src="' . $src . '" alt="x">' )['your-badge'] ), $src );
	}
} );

twd_sk_test( 'leftovers: only sample /service-1 style links are caught, a real page such as /service-anxiety is not', function () {
	foreach ( array( '/service-1', '/service-12', '/service-3/' ) as $href ) {
		twd_sk_eq( array( '/service-N' => 1 ), TWD_SK_Sanitizer::find_leftovers( '<a href="' . $href . '">x</a>' ), $href );
	}
	twd_sk_eq( array( '/service-N' => 2 ), TWD_SK_Sanitizer::find_leftovers( '<a href="/service-1">a</a><a href="/service-2">b</a>' ) );
	foreach ( array( '/service-anxiety', '/services', '/my-service-1x', '/service' ) as $href ) {
		twd_sk_eq( array(), TWD_SK_Sanitizer::find_leftovers( '<a href="' . $href . '">x</a>' ), $href );
	}
	twd_sk_eq( array(), TWD_SK_Sanitizer::find_leftovers( '<p>See /service-1 in the text.</p>' ), 'only an href counts' );
} );

twd_sk_test( 'leftovers: every example in every registry skeleton is flagged, and a page of the therapist\'s own words is not', function () {
	foreach ( TWD_SK_Registry::style_guide_data()['components'] as $c ) {
		if ( 'notice' === $c['id'] ) {
			continue; // The safety notice holds real support-line text that must stay on a page.
		}
		$skeletons = array( $c['skeleton'] );
		foreach ( $c['variants'] as $v ) {
			$skeletons[] = $v['skeleton'];
		}
		foreach ( $skeletons as $html ) {
			twd_sk_true( TWD_SK_Sanitizer::find_leftovers( $html ) !== array(), $c['id'] . ' example is flagged as example text' );
		}
	}
	$own = '<section class="twd-sk-text twd-sk-tone-surface"><div class="twd-sk-inner"><h2 class="twd-sk-title">How I work</h2><p>Plain, honest words.</p><a class="twd-sk-btn twd-sk-btn--primary" href="/service-anxiety">Anxiety support</a></div></section>';
	twd_sk_eq( array(), TWD_SK_Sanitizer::find_leftovers( $own ) );
} );

twd_sk_test( 'leftovers: the cleaning report warns about them but keeps the text and does not count them as removals', function () {
	$in  = '<p>Heading here and [PLACEHOLDER: fee]</p>';
	$rep = twd_sk_rep( $in );
	twd_sk_eq( $in, $rep['html'] );
	twd_sk_eq( 0, $rep['report']['total'] );
	twd_sk_eq( array( 'Heading here' => 1, '[PLACEHOLDER' => 1 ), $rep['report']['leftovers'] );
	twd_sk_eq( array(), twd_sk_rep( '<p>All real words.</p>' )['report']['leftovers'] );
} );

twd_sk_test( 'leftovers: the whole gallery carries example text, so it warns, and every registry skeleton warns or is a notice', function () {
	$gallery = file_get_contents( ABSPATH . 'starters/_gallery.html' );
	$rep     = twd_sk_rep( $gallery );
	twd_sk_eq( 0, $rep['report']['total'], 'nothing removed' );
	twd_sk_true( count( $rep['report']['leftovers'] ) >= 5, 'gallery warns about its example text' );
} );
