<?php
// 0.10.0: changing words and pictures in place on the page.

/** A kit page 40 holding the given HTML (cleaned as the store would), and its stored copy. */
function twd_sk_inline_page( $html ) {
	twd_stub_add_post( 40, 'page', '[twd_page]' );
	$r = TWD_SK_Store::save( 40, $html );
	twd_sk_true( ! is_wp_error( $r ), 'page saved' );
	return TWD_SK_Store::get_current( 40 );
}
function twd_sk_inline_sample() {
	return '<section class="twd-sk-hero twd-sk-hero--split twd-sk-tone-base"><div class="twd-sk-inner">'
		. '<span class="twd-sk-eyebrow">Weekend van hire</span>'
		. '<h1 class="twd-sk-hero__title">A van <span class="twd-sk-accent">for your move</span></h1>'
		. '<p class="twd-sk-hero__lead">Booked and <strong>ready</strong> for you.</p>'
		. '<div class="twd-sk-hero__actions"><a class="twd-sk-btn twd-sk-btn--primary" href="/book">Check your date</a></div>'
		. '<img class="twd-sk-hero__img" src="/wp-content/uploads/a.jpg" alt="The van"></div></section>'
		. "\n" . TWD_SK_Registry::get( 'services' )['variants']['twd-sk-services--cards']['skeleton']
		. "\n" . TWD_SK_Registry::get( 'notice' )['skeleton']
		. "\n" . TWD_SK_Registry::get( 'enquiry' )['skeleton'];
}
/** The marked page and its numbered elements, as text => list of outer HTML snippets. */
function twd_sk_inline_marked( $html ) {
	$marked = TWD_SK_Inline::mark( $html );
	preg_match_all( '/<([a-z0-9]+)[^>]*data-twd-sk-t="(\d+)"[^>]*>(.*?)<\/\1>/s', $marked, $m, PREG_SET_ORDER );
	return array( 'html' => $marked, 'text' => $m );
}

twd_sk_test( 'inline: words are numbered in page order, buttons on their own, cards by their heading and text, pictures separately', function () {
	$stored = twd_sk_inline_page( twd_sk_inline_sample() );
	$m      = twd_sk_inline_marked( $stored );
	$texts  = array_map( function ( $x ) {
		return trim( strip_tags( $x[3] ) );
	}, $m['text'] );
	twd_sk_eq( 'Weekend van hire', $texts[0] );
	twd_sk_eq( 'A van for your move', $texts[1], 'the heading with its accent words is one piece' );
	twd_sk_eq( 'Booked and ready for you.', $texts[2] );
	twd_sk_eq( 'Check your date', $texts[3], 'a button is its own piece' );
	twd_sk_has( 'data-twd-sk-p="0"', $m['html'] );
	twd_sk_true( in_array( 'Service name here', $texts, true ), 'card headings inside a linked card are editable' );
	twd_sk_hasnt( 'twd-sk-services__item" data-twd-sk-t', $m['html'], 'the card link itself is not a text piece' );
	$numbers = array_map( function ( $x ) {
		return (int) $x[2];
	}, $m['text'] );
	twd_sk_eq( range( 0, count( $numbers ) - 1 ), $numbers, 'numbered 0, 1, 2 and so on in order' );
	twd_sk_eq( TWD_SK_Inline::count( $stored )['text'], count( $numbers ) );
} );

twd_sk_test( 'inline: the safety notice and the enquiry form are never editable in place', function () {
	$stored = twd_sk_inline_page( twd_sk_inline_sample() );
	$marked = TWD_SK_Inline::mark( $stored );
	preg_match( '/<section class="twd-sk-notice.*?<\/section>/s', $marked, $notice );
	twd_sk_true( isset( $notice[0] ) );
	twd_sk_hasnt( 'data-twd-sk-t', $notice[0], 'support lines stay as they are' );
	preg_match( '/<section class="twd-sk-enquiry.*?<\/section>/s', $marked, $enq );
	twd_sk_has( 'data-twd-sk-t', $enq[0], 'the heading and introduction are editable' );
	twd_sk_true( 1 !== preg_match( '/data-twd-sk-t="\d+"[^>]*>\s*\[twd_enquiry\]/', $enq[0] ), 'the form shortcode is not' );
} );

twd_sk_test( 'inline: numbering changes nothing else on the page', function () {
	$stored = twd_sk_inline_page( twd_sk_inline_sample() );
	$plain  = preg_replace( array( '/ data-twd-sk-(?:[tpr]|sec)="\d+"/', '/ data-twd-sk-lay="[^"]*"/' ), '', TWD_SK_Inline::mark( $stored ) );
	twd_sk_eq( $stored, $plain );
	twd_sk_eq( '', TWD_SK_Inline::mark( '' ) );
} );

twd_sk_test( 'inline: changes go into their own places; bold and italic kept, anything else unwrapped; the sanitiser still has the last word', function () {
	$stored = twd_sk_inline_page( twd_sk_inline_sample() );
	$out    = TWD_SK_Inline::apply( $stored, array(
		array( 'i' => 1, 'html' => 'Moving day <span class="twd-sk-accent">made easy</span>' ),
		array( 'i' => 2, 'html' => 'We <b>deliver</b>, <i>set up</i> and&nbsp;collect.<div onclick="x()">More</div><script>alert(1)</script>' ),
		array( 'i' => 3, 'html' => 'Book <em>now</em>', 'href' => '/book-now' ),
	), array() );
	twd_sk_true( ! is_wp_error( $out ), is_wp_error( $out ) ? $out->get_error_message() : '' );
	twd_sk_eq( 3, $out['changed'] );
	twd_sk_has( '<h1 class="twd-sk-hero__title">Moving day <span class="twd-sk-accent">made easy</span></h1>', $out['html'] );
	twd_sk_has( 'We <strong>deliver</strong>, <em>set up</em> and collect.More', $out['html'] );
	twd_sk_hasnt( 'onclick', $out['html'] );
	twd_sk_hasnt( '<div onclick', $out['html'] );
	twd_sk_has( '<a class="twd-sk-btn twd-sk-btn--primary" href="/book-now">Book now</a>', $out['html'], 'a button keeps plain words, and its new address' );
	twd_sk_has( 'Weekend van hire', $out['html'], 'untouched pieces stay' );
	$clean = TWD_SK_Sanitizer::clean( $out['html'] );
	twd_sk_hasnt( '<script', $clean );
	twd_sk_hasnt( 'alert(1)', TWD_SK_Store::save( 40, $out['html'], array( 'base_version' => TWD_SK_Store::get_current_version_id( 40 ) ) ) ? TWD_SK_Store::get_current( 40 ) : '' );
} );

twd_sk_test( 'inline: unsafe links are refused or dropped, out of step numbers are a conflict, and limits hold', function () {
	$stored = twd_sk_inline_page( twd_sk_inline_sample() );
	twd_sk_is_error( 'twd_sk_bad_input', TWD_SK_Inline::apply( $stored, array( array( 'i' => 3, 'html' => 'Go', 'href' => 'javascript:alert(1)' ) ), array() ) );
	$out = TWD_SK_Inline::apply( $stored, array( array( 'i' => 2, 'html' => 'See <a href="javascript:alert(1)">this</a> and <a href="/faq">that</a>' ) ), array() );
	twd_sk_has( 'See this and <a href="/faq">that</a>', $out['html'], 'a bad link keeps its words only' );
	twd_sk_is_error( 'twd_sk_conflict', TWD_SK_Inline::apply( $stored, array( array( 'i' => 999, 'html' => 'x' ) ), array() ) );
	twd_sk_is_error( 'twd_sk_bad_input', TWD_SK_Inline::apply( $stored, array(), array() ) );
	twd_sk_is_error( 'twd_sk_bad_input', TWD_SK_Inline::apply( $stored, array( array( 'i' => 0 ) ), array() ) );
	twd_sk_is_error( 'twd_sk_bad_input', TWD_SK_Inline::apply( $stored, array( array( 'i' => 0, 'html' => str_repeat( 'x', TWD_SK_Inline::MAX_TEXT + 1 ) ) ), array() ) );
	$many = array();
	for ( $i = 0; $i <= TWD_SK_Inline::MAX_EDITS; $i++ ) {
		$many[] = array( 'i' => 0, 'html' => 'x' );
	}
	twd_sk_is_error( 'twd_sk_bad_input', TWD_SK_Inline::apply( $stored, $many, array() ) );
} );

twd_sk_test( 'inline: a picture is swapped for one from the media library, with its new description', function () {
	$stored = twd_sk_inline_page( twd_sk_inline_sample() );
	$GLOBALS['twd_stub']['attachments'][77] = array( 'is_image' => true, 'url' => 'https://example.test/wp-content/uploads/friends.jpg', 'w' => 1200, 'h' => 800 );
	$GLOBALS['twd_stub']['attachments'][78] = array( 'is_image' => false, 'url' => 'https://example.test/wp-content/uploads/file.pdf' );
	$out = TWD_SK_Inline::apply( $stored, array(), array( array( 'i' => 0, 'id' => 77, 'alt' => 'Friends outside the <b>van</b>' ) ) );
	twd_sk_has( 'src="https://example.test/wp-content/uploads/friends.jpg"', $out['html'] );
	twd_sk_has( 'alt="Friends outside the van"', $out['html'] );
	twd_sk_is_error( 'twd_sk_bad_input', TWD_SK_Inline::apply( $stored, array(), array( array( 'i' => 0, 'id' => 78 ) ) ) );
	twd_sk_is_error( 'twd_sk_conflict', TWD_SK_Inline::apply( $stored, array(), array( array( 'i' => 5, 'id' => 77 ) ) ) );
} );

twd_sk_test( 'inline: the page is numbered only for someone who can edit it, never for visitors or in safe mode', function () {
	twd_sk_inline_page( twd_sk_inline_sample() );
	$GLOBALS['twd_stub']['admin']   = false;
	$GLOBALS['twd_stub']['queried'] = 40;
	$GLOBALS['twd_stub']['post_id'] = 40;
	$GLOBALS['twd_stub']['caps']    = array( 'edit_pages', 'edit_post:40' );
	twd_sk_has( 'data-twd-sk-t="0"', TWD_SK_Page::render() );
	$GLOBALS['twd_stub']['caps'] = array();
	twd_sk_hasnt( 'data-twd-sk', TWD_SK_Page::render(), 'a visitor gets no numbers' );
	$GLOBALS['twd_stub']['caps'] = array( 'edit_pages', 'edit_post:40' );
	TWD_SK_Safe::set( true );
	twd_sk_hasnt( 'data-twd-sk', TWD_SK_Page::render(), 'safe mode switches it off' );
} );

twd_sk_test( 'inline: the save route needs the version the page was drawn from, saves one new version, and can be undone', function () {
	twd_sk_rest_setup( array( 'edit_pages', 'edit_post:12' ) );
	TWD_SK_Store::save( 12, twd_sk_inline_sample() );
	$base = TWD_SK_Store::get_current_version_id( 12 );
	$out  = TWD_SK_REST::post_inline( twd_sk_rest_req( array( 'base_version' => $base, 'texts' => array( array( 'i' => 0, 'html' => 'Van hire' ) ) ) ) );
	twd_sk_true( is_array( $out ), is_wp_error( $out ) ? $out->get_error_message() : '' );
	twd_sk_has( '<span class="twd-sk-eyebrow">Van hire</span>', TWD_SK_Store::get_current( 12 ) );
	twd_sk_eq( 'Changed on the page (1 piece)', TWD_SK_Store::list_versions( 12 )[0]['note'] );
	$stale = TWD_SK_REST::post_inline( twd_sk_rest_req( array( 'base_version' => $base, 'texts' => array( array( 'i' => 0, 'html' => 'Again' ) ) ) ) );
	twd_sk_eq( 409, twd_sk_status( $stale ) );
	TWD_SK_Store::undo( 12, array( 'base_version' => TWD_SK_Store::get_current_version_id( 12 ) ) );
	twd_sk_has( 'Weekend van hire', TWD_SK_Store::get_current( 12 ) );
} );

twd_sk_test( 'inline js: never builds or writes HTML from text, talks only to its own route and the media upload, no native dialogs or web addresses', function () {
	$js = file_get_contents( ABSPATH . 'assets/twd-site-kit-inline.js' );
	foreach ( array( 'innerHTML', 'outerHTML', 'insertAdjacentHTML', 'eval(', 'new Function', 'document.write', 'XMLHttpRequest', 'localStorage', 'sendBeacon', 'onclick' ) as $bad ) {
		twd_sk_hasnt( $bad, $js );
	}
	twd_sk_true( 1 !== preg_match( '/(^|[^.\w])(alert|confirm|prompt)\s*\(/m', $js ), 'no native dialogs' );
	twd_sk_true( 1 !== preg_match( '#https?://#i', $js ), 'no web addresses' );
	twd_sk_has( "ED.api('POST', '/pages/' + cfg.pageId + '/inline', body)", $js );
	twd_sk_has( 'base_version: cfg.currentVersion', $js, 'the version the page was drawn from' );
	twd_sk_has( 'new XMLSerializer()', $js );
	twd_sk_has( "getData('text/plain')", $js, 'pasting brings words only' );
	twd_sk_has( "e.key === 'Escape'", $js );
	twd_sk_has( 'window.wp && window.wp.media', $js );
	// 0.10.1: the only other request is the upload, to WordPress's own media route, with the nonce.
	twd_sk_eq( 1, substr_count( $js, 'fetch(' ), 'one direct request' );
	twd_sk_has( "fetch(cfg.mediaUrl, { method: 'POST', credentials: 'same-origin', headers: { 'X-WP-Nonce': cfg.nonce }, body: form })", $js );
	twd_sk_has( 'Choose from the media library', $js );
	twd_sk_has( 'Upload from this computer', $js );
	twd_sk_has( 'Change the description', $js );
	twd_sk_has( 'spots: []', $js );
	twd_sk_has( 'PICTURE_TYPES.test(', $js, 'only picture files are uploaded' );
} );

twd_sk_test( 'inline 0.10.2: the editing card sits bottom left as a compact card; the admin bar can start, save and cancel', function () {
	$js = file_get_contents( ABSPATH . 'assets/twd-site-kit-inline.js' );
	twd_sk_has( "getElementById('wp-admin-bar-root-default')", $js, 'only adds to the admin bar when there is one' );
	twd_sk_has( "'twd-sk-text'", $js );
	twd_sk_has( "'twd-sk-text-cancel'", $js );
	twd_sk_has( "'Save changes (' + n + ')'", $js );
	twd_sk_has( 'if (S.saving) {', $js, 'a second press of Save does nothing while saving' );
	$css = file_get_contents( ABSPATH . 'tools/editor.src.css' );
	preg_match( '/\.twd-sk-ed__inlinebar \{([^}]*)\}/', $css, $m );
	twd_sk_true( isset( $m[1] ) );
	twd_sk_has( 'left: 24px; bottom: 24px;', $m[1], 'bottom left, clear of buttons on the right' );
	twd_sk_has( 'width: 460px;', $m[1] );
	twd_sk_hasnt( 'right: 0', $m[1], 'no longer the full width of the screen on a computer' );
} );

twd_sk_test( 'inline css: every rule applies only while editing', function () {
	$rules = twd_sk_css_parse( file_get_contents( ABSPATH . 'assets/twd-site-kit-inline.css' ) );
	twd_sk_true( count( $rules ) >= 5 );
	foreach ( $rules as $rule ) {
		foreach ( $rule['selectors'] as $sel ) {
			twd_sk_true( 0 === strpos( $sel, 'body.twd-sk-inline-on' ), $sel );
		}
	}
} );

twd_sk_test( 'inline: the script and its marks load for an editor on a kit page, not on other pages and not in safe mode', function () {
	twd_sk_ed_setup();
	TWD_SK_Store::save( 12, '<section class="twd-sk-text"><div class="twd-sk-inner"><p>Hello</p></div></section>' );
	twd_sk_true( twd_sk_ed_loaded() );
	twd_sk_true( ! empty( $GLOBALS['twd_stub']['scripts']['twd-site-kit-inline']['enqueued'] ) );
	twd_sk_true( true === $GLOBALS['twd_stub']['scripts']['twd-site-kit-editor']['data']['inline'] );
	twd_stub_reset();
	twd_sk_ed_setup();
	$GLOBALS['twd_stub']['queried'] = 20;
	$GLOBALS['twd_stub']['post_id'] = 20;
	twd_sk_ed_loaded();
	twd_sk_true( empty( $GLOBALS['twd_stub']['scripts']['twd-site-kit-inline']['enqueued'] ), 'not a kit page' );
	twd_stub_reset();
	twd_sk_ed_setup();
	TWD_SK_Safe::set( true );
	twd_sk_ed_loaded();
	twd_sk_true( empty( $GLOBALS['twd_stub']['scripts']['twd-site-kit-inline']['enqueued'] ), 'safe mode' );
	twd_sk_true( false === $GLOBALS['twd_stub']['scripts']['twd-site-kit-editor']['data']['inline'] );
} );

// 0.10.1: places marked for a picture, pictures uploaded from the computer, and descriptions on their own.

function twd_sk_spot_sample() {
	return '<section class="twd-sk-pictures twd-sk-tone-base"><div class="twd-sk-inner"><h2 class="twd-sk-title">A look inside</h2><div class="twd-sk-pictures__grid">'
		. '<figure class="twd-sk-pictures__item"><img class="twd-sk-pictures__img" src="/wp-content/uploads/a.jpg" alt="The bench"><figcaption class="twd-sk-pictures__caption">Timber and warmth</figcaption></figure>'
		. '<figure class="twd-sk-pictures__item"><p>[PLACEHOLDER: image needed]</p><figcaption class="twd-sk-pictures__caption">[PLACEHOLDER: caption for the third picture]</figcaption></figure>'
		. '<figure class="twd-sk-pictures__item">[PLACEHOLDER: picture of the steps]<figcaption class="twd-sk-pictures__caption">Easy to reach</figcaption></figure>'
		. '</div></div></section>'
		. '<section class="twd-sk-image-text twd-sk-tone-surface"><div class="twd-sk-inner"><div class="twd-sk-image-text__grid">'
		. '<figure class="twd-sk-image-text__media">[PLACEHOLDER: photo of the van on the drive]</figure>'
		. '<div class="twd-sk-image-text__body"><h2 class="twd-sk-title">Delivered to you</h2><p>[PLACEHOLDER: describe the picture above]</p></div>'
		. '</div></div></section>';
}

twd_sk_test( 'inline spots: a picture marker is a place for a picture; caption and description markers stay words', function () {
	twd_sk_true( TWD_SK_Inline::is_spot_text( '[PLACEHOLDER: image needed]' ) );
	twd_sk_true( TWD_SK_Inline::is_spot_text( '  [PLACEHOLDER: photo of the van on the drive] ' ) );
	twd_sk_true( TWD_SK_Inline::is_spot_text( '[PLACEHOLDER: pictures]' ) );
	twd_sk_true( ! TWD_SK_Inline::is_spot_text( '[PLACEHOLDER: caption for the third picture]' ) );
	twd_sk_true( ! TWD_SK_Inline::is_spot_text( '[PLACEHOLDER: describe the picture above]' ) );
	twd_sk_true( ! TWD_SK_Inline::is_spot_text( '[PLACEHOLDER: phone number]' ) );
	twd_sk_true( ! TWD_SK_Inline::is_spot_text( 'See the image [PLACEHOLDER: image needed]' ), 'only a marker on its own' );
	twd_sk_true( ! TWD_SK_Inline::is_spot_text( '[PLACEHOLDER: picnic area]' ), 'a whole word only' );

	$stored = twd_sk_inline_page( twd_sk_spot_sample() );
	$marked = TWD_SK_Inline::mark( $stored );
	twd_sk_has( '<p data-twd-sk-s="0">[PLACEHOLDER: image needed]</p>', $marked );
	twd_sk_has( '<figure class="twd-sk-pictures__item" data-twd-sk-r="3"><span data-twd-sk-s="1">[PLACEHOLDER: picture of the steps]</span><figcaption', $marked, 'a bare marker beside a caption is wrapped for the editor' );
	twd_sk_has( '<figure class="twd-sk-image-text__media" data-twd-sk-s="2">[PLACEHOLDER: photo of the van on the drive]</figure>', $marked, 'a figure holding only a marker is the place itself' );
	twd_sk_true( 1 === preg_match( '/<figcaption class="twd-sk-pictures__caption" data-twd-sk-t="\d+">\[PLACEHOLDER: caption/', $marked ), 'the caption marker is still words to type over' );
	twd_sk_true( 1 === preg_match( '/<p data-twd-sk-t="\d+">\[PLACEHOLDER: describe the picture above\]<\/p>/', $marked ) );
	twd_sk_eq( array( 'text' => 6, 'pics' => 1, 'spots' => 3, 'removes' => 5 ), TWD_SK_Inline::count( $stored ) );
	$plain = preg_replace( array( '/ data-twd-sk-(?:[tpsr]|sec)="\d+"/', '/ data-twd-sk-lay="[^"]*"/', '/<span>(\[PLACEHOLDER[^<]*)<\/span>/' ), array( '', '', '$1' ), $marked );
	twd_sk_eq( $stored, $plain, 'numbering changes nothing else' );
} );

twd_sk_test( 'inline spots: a chosen picture takes the place, with the class the section uses and its description', function () {
	$stored = twd_sk_inline_page( twd_sk_spot_sample() );
	$GLOBALS['twd_stub']['attachments'][77] = array( 'is_image' => true, 'url' => 'https://example.test/wp-content/uploads/stove.jpg', 'w' => 1200, 'h' => 800 );
	$GLOBALS['twd_stub']['attachments'][79] = array( 'is_image' => true, 'url' => 'https://example.test/wp-content/uploads/drive.jpg', 'w' => 1200, 'h' => 800 );
	$out = TWD_SK_Inline::apply( $stored, array(), array(), array(
		array( 'i' => 0, 'id' => 77, 'alt' => 'The stove glowing' ),
		array( 'i' => 1, 'id' => 79, 'alt' => 'The steps' ),
		array( 'i' => 2, 'id' => 79, 'alt' => 'The van on a drive' ),
	) );
	twd_sk_true( ! is_wp_error( $out ), is_wp_error( $out ) ? $out->get_error_message() : '' );
	twd_sk_eq( 3, $out['changed'] );
	twd_sk_has( '<figure class="twd-sk-pictures__item"><img class="twd-sk-pictures__img" src="https://example.test/wp-content/uploads/drive.jpg" alt="The steps"><figcaption class="twd-sk-pictures__caption">Easy to reach</figcaption>', $out['html'], 'a bare marker is replaced' );
	twd_sk_has( '<figure class="twd-sk-pictures__item"><img class="twd-sk-pictures__img" src="https://example.test/wp-content/uploads/stove.jpg" alt="The stove glowing"><figcaption', $out['html'], 'the paragraph is replaced by the picture' );
	twd_sk_has( '<figure class="twd-sk-image-text__media"><img class="twd-sk-image-text__img" src="https://example.test/wp-content/uploads/drive.jpg" alt="The van on a drive"></figure>', $out['html'], 'a figure keeps its place and gets the picture inside' );
	twd_sk_hasnt( 'image needed', $out['html'] );
	twd_sk_hasnt( 'photo of the van', $out['html'] );
	twd_sk_has( '[PLACEHOLDER: caption for the third picture]', $out['html'], 'the caption marker is left for the person to fill in' );
	$saved = TWD_SK_Store::save( 40, $out['html'], array( 'base_version' => TWD_SK_Store::get_current_version_id( 40 ) ) );
	twd_sk_true( ! is_wp_error( $saved ), 'the sanitiser accepts the page' );
	twd_sk_has( 'class="twd-sk-image-text__img"', TWD_SK_Store::get_current( 40 ) );
} );

twd_sk_test( 'inline spots: a section with no known box copies the class of its other pictures, else none', function () {
	$GLOBALS['twd_stub']['attachments'][77] = array( 'is_image' => true, 'url' => 'https://example.test/wp-content/uploads/stove.jpg', 'w' => 1200, 'h' => 800 );
	$stored = twd_sk_inline_page( '<section class="twd-sk-text twd-sk-tone-base"><div class="twd-sk-inner"><p>[PLACEHOLDER: image needed]</p><p>Words.</p></div></section>' );
	$out    = TWD_SK_Inline::apply( $stored, array(), array(), array( array( 'i' => 0, 'id' => 77, 'alt' => 'Stove' ) ) );
	twd_sk_has( '<div class="twd-sk-inner"><img src="https://example.test/wp-content/uploads/stove.jpg" alt="Stove"><p>Words.</p>', $out['html'] );
} );

twd_sk_test( 'inline spots: wrong pictures, out of step numbers and missing details are refused', function () {
	$stored = twd_sk_inline_page( twd_sk_spot_sample() );
	$GLOBALS['twd_stub']['attachments'][77] = array( 'is_image' => true, 'url' => 'https://example.test/wp-content/uploads/stove.jpg', 'w' => 1200, 'h' => 800 );
	$GLOBALS['twd_stub']['attachments'][78] = array( 'is_image' => false, 'url' => 'https://example.test/wp-content/uploads/file.pdf' );
	twd_sk_is_error( 'twd_sk_bad_input', TWD_SK_Inline::apply( $stored, array(), array(), array( array( 'i' => 0, 'id' => 78 ) ) ), 'not a picture' );
	twd_sk_is_error( 'twd_sk_bad_input', TWD_SK_Inline::apply( $stored, array(), array(), array( array( 'i' => 0, 'id' => 0 ) ) ), 'a spot needs a picture' );
	twd_sk_is_error( 'twd_sk_bad_input', TWD_SK_Inline::apply( $stored, array(), array(), array( array( 'i' => 0 ) ) ) );
	twd_sk_is_error( 'twd_sk_conflict', TWD_SK_Inline::apply( $stored, array(), array(), array( array( 'i' => 9, 'id' => 77 ) ) ) );
} );

twd_sk_test( 'inline: a description can change on its own, keeping the picture', function () {
	$stored = twd_sk_inline_page( twd_sk_inline_sample() );
	$out    = TWD_SK_Inline::apply( $stored, array(), array( array( 'i' => 0, 'id' => 0, 'alt' => 'The van, packed and ready' ) ) );
	twd_sk_true( ! is_wp_error( $out ), is_wp_error( $out ) ? $out->get_error_message() : '' );
	twd_sk_has( '<img class="twd-sk-hero__img" src="/wp-content/uploads/a.jpg" alt="The van, packed and ready">', $out['html'] );
	twd_sk_eq( 1, $out['changed'] );
	twd_sk_is_error( 'twd_sk_bad_input', TWD_SK_Inline::apply( $stored, array(), array( array( 'i' => 0, 'id' => 0 ) ) ), 'a description change needs the description' );
} );

twd_sk_test( 'inline spots: the save route takes spots, and the editor knows whether this person may upload', function () {
	twd_sk_rest_setup( array( 'edit_pages', 'edit_post:12' ) );
	TWD_SK_Store::save( 12, twd_sk_spot_sample() );
	$GLOBALS['twd_stub']['attachments'][77] = array( 'is_image' => true, 'url' => 'https://example.test/wp-content/uploads/stove.jpg', 'w' => 1200, 'h' => 800 );
	$out = TWD_SK_REST::post_inline( twd_sk_rest_req( array(
		'base_version' => TWD_SK_Store::get_current_version_id( 12 ),
		'spots'        => array( array( 'i' => 0, 'id' => 77, 'alt' => 'The stove' ) ),
	) ) );
	twd_sk_true( is_array( $out ), is_wp_error( $out ) ? $out->get_error_message() : '' );
	twd_sk_has( 'alt="The stove"', TWD_SK_Store::get_current( 12 ) );
	twd_sk_eq( 'Changed on the page (1 piece)', TWD_SK_Store::list_versions( 12 )[0]['note'] );

	twd_stub_reset();
	twd_sk_ed_setup();
	TWD_SK_Store::save( 12, '<section class="twd-sk-text"><div class="twd-sk-inner"><p>Hello</p></div></section>' );
	twd_sk_ed_loaded();
	$data = $GLOBALS['twd_stub']['scripts']['twd-site-kit-editor']['data'];
	twd_sk_true( isset( $data['canUpload'], $data['mediaUrl'] ) );
	twd_sk_has( 'wp/v2/media', $data['mediaUrl'] );
} );

// -- 0.14.0: Remove this --------------------------------------------------------------------

twd_sk_test( 'inline remove: sections (not the one with the h1, not the notice), buttons and one-of-several items are numbered; nothing inside a piece of text or a shortcode holder', function () {
	$stored = twd_sk_inline_page( twd_sk_inline_sample() );
	$marked = TWD_SK_Inline::mark( $stored );
	preg_match_all( '/<([a-z0-9]+)[^>]*data-twd-sk-r="(\d+)"/', $marked, $m, PREG_SET_ORDER );
	$by = array();
	foreach ( $m as $hit ) {
		$by[ (int) $hit[2] ] = $hit[1];
	}
	twd_sk_eq( range( 0, count( $by ) - 1 ), array_keys( $by ), 'numbered in order from 0' );
	// The hero holds the h1, so it is not removable; its button is. The services section and its four cards are.
	twd_sk_true( 1 !== preg_match( '/<section class="twd-sk-hero[^"]*" data-twd-sk-r=/', $marked ), 'the hero with the h1 stays' );
	twd_sk_true( 1 === preg_match( '/<a class="twd-sk-btn twd-sk-btn--primary"[^>]*data-twd-sk-r="0"/', $marked ), 'the hero button is the first removable' );
	twd_sk_true( 1 === preg_match( '/<section class="twd-sk-services[^"]*"[^>]*data-twd-sk-r="1"/', $marked ), 'the services section is next' );
	twd_sk_eq( 4, preg_match_all( '/<a class="twd-sk-card twd-sk-card--accent twd-sk-services__item" href="\/service-\d"[^>]*data-twd-sk-r=/', $marked ), 'each card' );
	twd_sk_true( 1 !== preg_match( '/<section class="twd-sk-notice[^"]*" data-twd-sk-r=/', $marked ), 'the safety notice never' );
	twd_sk_true( 1 !== preg_match( '/twd-sk-notice__[^"]*"[^>]*data-twd-sk-r=/', $marked ), 'nor anything inside it' );
	twd_sk_true( 1 === preg_match( '/<section class="twd-sk-enquiry[^"]*" data-twd-sk-r=/', $marked ), 'the enquiry section can go as a whole' );
	twd_sk_true( 1 !== preg_match( '/<div class="twd-sk-inner"[^>]*data-twd-sk-r=/', $marked ), 'a lone wrapper is not one of several' );
	twd_sk_eq( count( $by ), TWD_SK_Inline::count( $stored )['removes'] );
	// Visitors never get the numbers: mark() is only run for an editor (tested below with the page render).
	twd_sk_hasnt( 'data-twd-sk-r', $stored );
	// Words inside a paragraph are never removable on their own, and a single list entry is not either.
	$one = twd_sk_inline_page( '<section class="twd-sk-text twd-sk-tone-base"><div class="twd-sk-inner"><h2 class="twd-sk-title">One</h2><p>Some <strong>bold</strong> and <em>more</em> words.</p><ul class="twd-sk-list"><li>Only entry</li></ul></div></section>' );
	twd_sk_eq( 1, TWD_SK_Inline::count( $one )['removes'], 'only the section itself' );
} );

twd_sk_test( 'inline remove: a list entry, a button (and the wrapper it leaves empty) and a whole section are removed; words elsewhere untouched; unknown numbers are a conflict; limits hold', function () {
	$stored = twd_sk_inline_page( '<section class="twd-sk-hero twd-sk-hero--split twd-sk-tone-base"><div class="twd-sk-inner"><h1 class="twd-sk-hero__title">Hire</h1></div></section>'
		. '<section class="twd-sk-areas twd-sk-tone-tint"><div class="twd-sk-inner"><h2 class="twd-sk-title">Where we go</h2><ul class="twd-sk-areas__list"><li class="twd-sk-areas__item">Northvale</li><li class="twd-sk-areas__item">Elmbridge</li><li class="twd-sk-areas__item">Area name here</li></ul><div class="twd-sk-areas__actions"><a class="twd-sk-btn twd-sk-btn--outline" href="https://www.example.com/map">See the area on a map</a></div></div></section>'
		. '<section class="twd-sk-text twd-sk-tone-base"><div class="twd-sk-inner"><h2 class="twd-sk-title">Old words</h2><p>To go.</p></div></section>' );
	$marked = TWD_SK_Inline::mark( $stored );
	preg_match( '/<li class="twd-sk-areas__item"[^>]*data-twd-sk-r="(\d+)"[^>]*>Area name here/', $marked, $li );
	preg_match( '/<a class="twd-sk-btn twd-sk-btn--outline"[^>]*data-twd-sk-r="(\d+)"/', $marked, $btn );
	preg_match( '/<section class="twd-sk-text twd-sk-tone-base" data-twd-sk-r="(\d+)"/', $marked, $sec );
	twd_sk_true( $li && $btn && $sec, 'all three numbered' );
	// Only removals, nothing else: allowed.
	$out = TWD_SK_Inline::apply( $stored, array(), array(), array(), array( (int) $li[1], (int) $btn[1], (int) $sec[1] ) );
	twd_sk_true( ! is_wp_error( $out ), is_wp_error( $out ) ? $out->get_error_message() : '' );
	twd_sk_eq( 3, $out['removed'] );
	twd_sk_eq( 0, $out['changed'] );
	twd_sk_hasnt( 'Area name here', $out['html'] );
	twd_sk_has( '<li class="twd-sk-areas__item">Elmbridge</li></ul>', $out['html'] );
	twd_sk_hasnt( 'See the area on a map', $out['html'] );
	twd_sk_hasnt( 'twd-sk-areas__actions', $out['html'], 'the wrapper the button left empty goes too' );
	twd_sk_hasnt( 'Old words', $out['html'] );
	twd_sk_has( '<h1 class="twd-sk-hero__title">Hire</h1>', $out['html'] );
	twd_sk_has( 'Where we go', $out['html'] );
	twd_sk_eq( 2, substr_count( $out['html'], '<section' ) );
	twd_sk_eq( TWD_SK_Sanitizer::clean( $out['html'] ), $out['html'], 'still clean' );
	// A text change and a removal together; the removal numbers are those of the page as drawn.
	$out = TWD_SK_Inline::apply( $stored, array( array( 'i' => 1, 'html' => 'Where we travel' ) ), array(), array(), array( (int) $li[1] ) );
	twd_sk_has( 'Where we travel', $out['html'] );
	twd_sk_eq( 1, $out['removed'] );
	twd_sk_eq( 1, $out['changed'] );
	// Removing a section and an item inside it: the item is simply gone with it.
	preg_match( '/<section class="twd-sk-areas twd-sk-tone-tint" data-twd-sk-r="(\d+)"/', $marked, $areas );
	$out = TWD_SK_Inline::apply( $stored, array(), array(), array(), array( (int) $li[1], (int) $areas[1] ) );
	twd_sk_eq( 2, $out['removed'] );
	twd_sk_hasnt( 'twd-sk-areas', $out['html'] );
	// Refusals.
	twd_sk_is_error( 'twd_sk_conflict', TWD_SK_Inline::apply( $stored, array(), array(), array(), array( 999 ) ) );
	twd_sk_is_error( 'twd_sk_bad_input', TWD_SK_Inline::apply( $stored, array(), array(), array(), array( 'x' ) ) );
	twd_sk_is_error( 'twd_sk_bad_input', TWD_SK_Inline::apply( $stored, array(), array(), array(), range( 0, TWD_SK_Inline::MAX_REMOVES ) ) );
	// The page can never lose its h1 section (never numbered) and so never every section.
	twd_sk_true( 1 !== preg_match( '/<section class="twd-sk-hero[^"]*" data-twd-sk-r=/', $marked ) );
} );

twd_sk_test( 'inline remove: the save route takes removes and says so in the note; undo puts it back; the script and the marks know about it', function () {
	twd_sk_rest_setup( array( 'edit_pages', 'edit_post:12' ) );
	TWD_SK_Store::save( 12, twd_sk_inline_sample() );
	$base = TWD_SK_Store::get_current_version_id( 12 );
	$out  = TWD_SK_REST::post_inline( twd_sk_rest_req( array( 'base_version' => $base, 'removes' => array( 0 ) ) ) );
	twd_sk_true( is_array( $out ), is_wp_error( $out ) ? $out->get_error_message() : '' );
	twd_sk_hasnt( 'Check your date', TWD_SK_Store::get_current( 12 ), 'the hero button is gone' );
	twd_sk_hasnt( 'twd-sk-hero__actions', TWD_SK_Store::get_current( 12 ), 'and its empty wrapper' );
	twd_sk_eq( 'Changed on the page (1 removed)', TWD_SK_Store::list_versions( 12 )[0]['note'] );
	TWD_SK_Store::undo( 12, array( 'base_version' => TWD_SK_Store::get_current_version_id( 12 ) ) );
	twd_sk_has( 'Check your date', TWD_SK_Store::get_current( 12 ), 'undo brings it back' );
	$base = TWD_SK_Store::get_current_version_id( 12 );
	$out  = TWD_SK_REST::post_inline( twd_sk_rest_req( array( 'base_version' => $base, 'texts' => array( array( 'i' => 0, 'html' => 'Van hire' ) ), 'removes' => array( 0 ) ) ) );
	twd_sk_eq( 'Changed on the page (1 piece, 1 removed)', TWD_SK_Store::list_versions( 12 )[0]['note'] );
	$js = file_get_contents( ABSPATH . 'assets/twd-site-kit-inline.js' );
	twd_sk_has( "toolButton('Remove'", $js );
	twd_sk_has( "toolButton('Put back'", $js );
	twd_sk_has( "closest('[data-twd-sk-r]')", $js );
	twd_sk_has( "node.setAttribute('hidden', '')", $js, 'hidden on screen until Save' );
	twd_sk_has( 'body.removes = Object.keys(S.removes)', $js );
	twd_sk_has( 'S.removes[k].removeAttribute(\'hidden\')', $js, 'Cancel puts everything back' );
	twd_sk_has( 'body.twd-sk-inline-on [data-twd-sk-r][hidden] { display: none !important; }', file_get_contents( ABSPATH . 'assets/twd-site-kit-inline.css' ) );
} );
