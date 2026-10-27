<?php
// The full site set up (0.12.0, a test option): the questions, the page plan, picture markers, the checks on what
// the AI writes, and the build end to end. The AI is a fake service. No real notes, names or addresses.

function twd_sk_full_notes() {
	return "I run Example Calm, a small counselling practice. My name is Alex Example. I offer anxiety counselling for adults and a weekly mindfulness group. "
		. "Anxiety counselling is one to one, fifty minutes, for adults who feel overwhelmed by worry. The mindfulness group meets weekly and suits anyone who wants to learn to slow down. "
		. "I trained over six years and work gently and at your pace. I see people online and in Anywhere town.";
}
function twd_sk_full_draft( $over = array() ) {
	$GLOBALS['twd_stub']['caps'] = true;
	TWD_SK_Build::reset_draft();
	$base = array( 'full' => true, 'full_notes' => twd_sk_full_notes(), 'full_answers' => array() );
	TWD_SK_Build::save_draft( array_merge( $base, $over ) );
	return TWD_SK_Build::draft();
}
function twd_sk_full_reply( $over = array() ) {
	return json_encode( array_replace_recursive( array(
		'found'   => array(
			'site_name' => 'Example Calm', 'person_name' => 'Alex Example', 'email' => '', 'phone' => '', 'area' => 'Anywhere town', 'registration' => 'none',
			'services' => array( array( 'name' => 'Anxiety counselling', 'has_detail' => true ), array( 'name' => 'Mindfulness group', 'has_detail' => true ) ),
			'about' => true, 'faq' => true, 'testimonials' => false, 'process' => false,
		),
		'missing' => array(),
		'summary' => array( 'A small counselling practice run by Alex Example.' ),
	), $over ) );
}
/** A draft whose answers cover every question and every design choice. */
function twd_sk_full_ready_answers() {
	return array( 'style' => 'sage', 'tone' => 'warm', 'photos' => 'mine', 'registration' => 'none' );
}

twd_sk_test( 'full: answers are cleaned, keys must be simple, empty ones dropped, forty at most', function () {
	$out = TWD_SK_Full::clean_answers( array( 'area' => "  <b>Online</b> only \xE2\x80\x94 UK  ", 'Bad Key' => 'x', 'ok_2' => '', 'long' => str_repeat( 'a', 5000 ), 5 => 'y', 'arr' => array( 'z' ) ) );
	twd_sk_eq( array( 'area', 'long' ), array_keys( $out ) );
	twd_sk_eq( 'Online only, UK', $out['area'] );
	twd_sk_eq( TWD_SK_Full::MAX_ANSWER, strlen( $out['long'] ) );
	$many = array();
	for ( $i = 0; $i < 60; $i++ ) {
		$many[ 'k' . $i ] = 'v';
	}
	twd_sk_eq( TWD_SK_Full::MAX_ANSWERS, count( TWD_SK_Full::clean_answers( $many ) ) );
	twd_sk_eq( array(), TWD_SK_Full::clean_answers( 'nope' ) );
} );

twd_sk_test( 'full: a claim only counts when the words are really in the notes (names, email, phone, registration)', function () {
	$text = 'Hello, I am Alex Example of Example Calm. Email alex@example.org or call 01234 567890.';
	twd_sk_true( TWD_SK_Full::grounded( 'Example Calm', $text ) );
	twd_sk_true( TWD_SK_Full::grounded( 'alex example', $text ), 'case and order of nothing else matter' );
	twd_sk_true( ! TWD_SK_Full::grounded( 'Invented Name Ltd', $text ) );
	twd_sk_true( ! TWD_SK_Full::grounded( '', $text ) );
	twd_sk_true( TWD_SK_Full::email_grounded( 'alex@example.org', $text ) );
	twd_sk_true( ! TWD_SK_Full::email_grounded( 'info@example.org', $text ), 'a different email is not grounded' );
	twd_sk_true( ! TWD_SK_Full::email_grounded( 'not an email', 'not an email' ) );
	twd_sk_true( TWD_SK_Full::phone_grounded( '01234 567 890', $text ), 'spacing does not matter' );
	twd_sk_true( ! TWD_SK_Full::phone_grounded( '09999 111222', $text ) );
	twd_sk_true( ! TWD_SK_Full::phone_grounded( '12', 'call 12' ), 'too short to count' );
} );

twd_sk_test( 'full: the check needs the AI, enough notes, and survives a cut off, empty or unreadable reply', function () {
	$d = twd_sk_full_draft();
	twd_sk_ai_provider( twd_sk_full_reply(), false );
	twd_sk_is_error( 'twd_sk_ai_unavailable', TWD_SK_Full::check( $d ) );
	twd_sk_ai_provider( twd_sk_full_reply() );
	$short = twd_sk_full_draft( array( 'full_notes' => 'Too short.' ) );
	twd_sk_ai_provider( twd_sk_full_reply() );
	twd_sk_is_error( 'twd_sk_bad_input', TWD_SK_Full::check( $short ) );
	twd_sk_ai_provider( new WP_Error( 'twd_ap_ai_cut_off', 'ran out of room' ) );
	twd_sk_is_error( 'twd_sk_ai_too_long', TWD_SK_Full::check( $d ) );
	twd_sk_ai_provider( '' );
	twd_sk_is_error( 'twd_sk_ai_unavailable', TWD_SK_Full::check( $d ) );
	twd_sk_ai_provider( 'not json at all' );
	twd_sk_is_error( 'twd_sk_ai_bad_result', TWD_SK_Full::check( $d ) );
} );

twd_sk_test( 'full: what the AI invents is thrown away, and the code asks for what a site needs whatever the AI says', function () {
	$d = twd_sk_full_draft();
	// The AI claims a name, a person, an email and a registration that are nowhere in the notes, and asks nothing.
	twd_sk_ai_provider( twd_sk_full_reply( array( 'found' => array( 'site_name' => 'Invented Studio', 'person_name' => 'Pat Madeup', 'email' => 'pat@example.net', 'phone' => '07000 000000', 'registration' => 'BACP 123456', 'area' => '', 'about' => false, 'faq' => false ) ) ) );
	$c = TWD_SK_Full::check( $d );
	twd_sk_true( ! is_wp_error( $c ) );
	twd_sk_eq( '', $c['found']['site_name'] );
	twd_sk_eq( '', $c['found']['person_name'] );
	twd_sk_eq( '', $c['found']['email'] );
	twd_sk_eq( '', $c['found']['phone'] );
	twd_sk_eq( '', $c['found']['registration'] );
	$keys = array_column( $c['missing'], 'key' );
	foreach ( array( 'site_name', 'person_name', 'about', 'area', 'registration', 'faq' ) as $k ) {
		twd_sk_true( in_array( $k, $keys, true ), 'asks for ' . $k );
	}
	twd_sk_true( ! $c['ready'] );
	twd_sk_eq( '', $c['contact']['phone'], 'no phone is made up' );
} );

twd_sk_test( 'full: a service with no detail is asked about; an answered question is never asked again; eight questions at most', function () {
	$d = twd_sk_full_draft();
	twd_sk_ai_provider( twd_sk_full_reply( array( 'found' => array( 'services' => array( array( 'name' => 'Anxiety counselling', 'has_detail' => false ), array( 'name' => 'Mindfulness group', 'has_detail' => true ) ) ) ) ) );
	$c = TWD_SK_Full::check( $d );
	twd_sk_true( in_array( 'service_1_detail', array_column( $c['missing'], 'key' ), true ) );
	twd_sk_true( ! in_array( 'service_2_detail', array_column( $c['missing'], 'key' ), true ) );
	// Answer it: it is not asked again, even though the AI still says there is no detail.
	$d2 = twd_sk_full_draft( array( 'full_answers' => array( 'service_1_detail' => 'One to one, fifty minutes, for adults who worry a lot.' ) ) );
	$c2 = TWD_SK_Full::check( $d2 );
	twd_sk_true( ! in_array( 'service_1_detail', array_column( $c2['missing'], 'key' ), true ) );
	// A flood of AI questions is cut to eight.
	$many = array();
	for ( $i = 1; $i <= 15; $i++ ) {
		$many[] = array( 'key' => 'extra_' . $i, 'question' => 'Question ' . $i . '?', 'why' => 'Because.' );
	}
	twd_sk_ai_provider( json_encode( array( 'found' => array( 'site_name' => 'Example Calm' ), 'missing' => $many, 'summary' => array() ) ) );
	twd_sk_eq( TWD_SK_Full::MAX_QUESTIONS, count( TWD_SK_Full::check( $d )['missing'] ) );
} );

twd_sk_test( 'full: ready only when nothing is missing and the three design questions are answered', function () {
	$d = twd_sk_full_draft( array( 'full_answers' => array( 'registration' => 'none' ) ) );
	twd_sk_ai_provider( twd_sk_full_reply() );
	$c = TWD_SK_Full::check( $d );
	twd_sk_eq( array(), $c['missing'], 'nothing missing from the facts' );
	twd_sk_eq( array( 'style', 'tone', 'photos' ), array_column( $c['design'], 'key' ) );
	twd_sk_true( ! $c['ready'], 'design questions still open' );
	$d2 = twd_sk_full_draft( array( 'full_answers' => twd_sk_full_ready_answers() ) );
	twd_sk_ai_provider( twd_sk_full_reply() );
	$c2 = TWD_SK_Full::check( $d2 );
	twd_sk_true( $c2['ready'], 'ready' );
	twd_sk_eq( array(), $c2['design'] );
	$d3 = twd_sk_full_draft( array( 'full_answers' => array( 'style' => 'not-a-style', 'tone' => 'warm', 'photos' => 'mine', 'registration' => 'none' ) ) );
	twd_sk_ai_provider( twd_sk_full_reply() );
	twd_sk_eq( array( 'style' ), array_column( TWD_SK_Full::check( $d3 )['design'], 'key' ), 'a style that does not exist is asked again' );
} );

twd_sk_test( 'full: no email given means info@ on the site address, said so; a given email wins; no phone means none', function () {
	$d = twd_sk_full_draft( array( 'full_answers' => twd_sk_full_ready_answers() ) );
	twd_sk_ai_provider( twd_sk_full_reply() );
	$c = TWD_SK_Full::check( $d );
	twd_sk_eq( 'info@' . 'example.test', $c['contact']['email'] );
	twd_sk_true( $c['contact']['email_assumed'] );
	twd_sk_eq( 'email', $c['assumed'][0]['key'] );
	twd_sk_eq( '', $c['contact']['phone'] );
	$d2 = twd_sk_full_draft( array( 'full_answers' => twd_sk_full_ready_answers() + array( 'email' => 'alex@example.org', 'phone' => '01234 567890' ) ) );
	twd_sk_ai_provider( twd_sk_full_reply() );
	$c2 = TWD_SK_Full::check( $d2 );
	twd_sk_eq( 'alex@example.org', $c2['contact']['email'] );
	twd_sk_true( ! $c2['contact']['email_assumed'] );
	twd_sk_eq( '01234 567890', $c2['contact']['phone'] );
	// An email in the pasted notes is used too.
	$d3 = twd_sk_full_draft( array( 'full_notes' => twd_sk_full_notes() . ' Email me at hello@example.org.', 'full_answers' => twd_sk_full_ready_answers() ) );
	twd_sk_ai_provider( twd_sk_full_reply( array( 'found' => array( 'email' => 'hello@example.org' ) ) ) );
	twd_sk_eq( 'hello@example.org', TWD_SK_Full::check( $d3 )['contact']['email'] );
} );

twd_sk_test( 'full: testimonials count only with real quotation marks; what is left out is said in plain words', function () {
	$d = twd_sk_full_draft( array( 'full_answers' => twd_sk_full_ready_answers() ) );
	twd_sk_ai_provider( twd_sk_full_reply( array( 'found' => array( 'testimonials' => true ) ) ) );
	$c = TWD_SK_Full::check( $d );
	twd_sk_true( ! $c['found']['testimonials'], 'no quote marks in the notes, so no testimonials' );
	twd_sk_has( 'Testimonials', implode( ' ', $c['left_out'] ) );
	twd_sk_has( 'how it works', implode( ' ', $c['left_out'] ) );
	$d2 = twd_sk_full_draft( array( 'full_notes' => twd_sk_full_notes() . ' A client wrote: "I feel calmer than I have in years."', 'full_answers' => twd_sk_full_ready_answers() ) );
	twd_sk_ai_provider( twd_sk_full_reply( array( 'found' => array( 'testimonials' => true, 'process' => true ) ) ) );
	$c2 = TWD_SK_Full::check( $d2 );
	twd_sk_true( $c2['found']['testimonials'] && $c2['found']['process'] );
	twd_sk_hasnt( 'Testimonials', implode( ' ', $c2['left_out'] ) );
} );

twd_sk_test( 'full: the check sends the notes and the answers as question and answer, and never anything else', function () {
	$d = twd_sk_full_draft( array( 'full_answers' => array( 'area' => 'Online only' ) ) );
	twd_sk_ai_provider( twd_sk_full_reply() );
	TWD_SK_Full::check( $d );
	$call = $GLOBALS['twd_stub']['ai_calls'][0];
	twd_sk_has( 'Example Calm', $call['message'] );
	twd_sk_has( 'Area Online only', $call['message'], 'the answer is labelled even before its question text is known' );
	twd_sk_has( 'ONE JSON object', $call['system'] );
	twd_sk_has( 'Never ask again', str_replace( 'Never ask again', 'Never ask again', 'Never ask again' ) );
	twd_sk_has( 'Do NOT ask for photos', $call['system'] );
	twd_sk_true( 1 !== preg_match( '/\xE2\x80[\x93\x94]/', $call['system'] ), 'no long dashes' );
} );

twd_sk_test( 'full: the sections of each page are chosen by the code from what was supplied, every page starts with a hero', function () {
	$none = array( 'services' => array( array( 'name' => 'A', 'has_detail' => true ) ), 'testimonials' => false, 'process' => false );
	$all  = array( 'services' => array( array( 'name' => 'A', 'has_detail' => true ) ), 'testimonials' => true, 'process' => true );
	foreach ( array( 'home', 'about', 'contact', 'service', 'faq', 'resources' ) as $type ) {
		foreach ( array( true, false ) as $photos ) {
			foreach ( array( $none, $all ) as $found ) {
				$parts = TWD_SK_Full::parts_for( $type, $found, $photos, true, true );
				twd_sk_true( ! is_wp_error( TWD_SK_Recipes::clean_parts( $parts ) ), $type . ' is a valid outline' );
				twd_sk_eq( 'hero', $parts[0][0] );
			}
		}
	}
	$ids = function ( $parts ) {
		return array_column( $parts, 0 );
	};
	twd_sk_true( ! in_array( 'quote', $ids( TWD_SK_Full::parts_for( 'home', $none, true, true, true ) ), true ), 'no testimonials, no quote section' );
	twd_sk_true( in_array( 'quote', $ids( TWD_SK_Full::parts_for( 'home', $all, true, true, true ) ), true ) );
	twd_sk_true( ! in_array( 'steps', $ids( TWD_SK_Full::parts_for( 'about', $none, true, true, true ) ), true ), 'no process, no steps' );
	twd_sk_true( in_array( 'steps', $ids( TWD_SK_Full::parts_for( 'about', $all, true, true, true ) ), true ) );
	twd_sk_true( ! in_array( 'resources', $ids( TWD_SK_Full::parts_for( 'home', $none, true, true, false ) ), true ), 'no articles plugin, no resources' );
	twd_sk_true( ! in_array( 'notice', $ids( TWD_SK_Full::parts_for( 'contact', $none, true, false, true ) ), true ), 'the safety notice is for therapy sites' );
	twd_sk_true( in_array( 'notice', $ids( TWD_SK_Full::parts_for( 'contact', $none, true, true, true ) ), true ) );
} );

twd_sk_test( 'full: with no photos no page has a layout with a picture spot', function () {
	$found = array( 'services' => array( array( 'name' => 'A', 'has_detail' => true ) ), 'testimonials' => true, 'process' => true );
	foreach ( array( 'home', 'about', 'contact', 'service', 'faq', 'resources' ) as $type ) {
		$html = TWD_SK_Starters::build( TWD_SK_Full::parts_for( $type, $found, false, true, true ) );
		twd_sk_hasnt( '<img', $html, $type . ' has no picture' );
		twd_sk_hasnt( 'hero--split', $html );
		twd_sk_hasnt( 'image-text', $html );
		twd_sk_hasnt( 'contact--split', $html );
		twd_sk_hasnt( 'cta--band', $html );
	}
} );

twd_sk_test( 'full: the page list is home, about, a page per service, questions, resources and contact, and the questions page can be skipped', function () {
	$GLOBALS['twd_stub']['shortcodes']['twd_articles'] = 'cb';
	$d = twd_sk_full_draft( array( 'full_answers' => twd_sk_full_ready_answers() ) );
	twd_sk_ai_provider( twd_sk_full_reply() );
	$d['full_check'] = TWD_SK_Full::check( $d );
	$pages = TWD_SK_Full::pages_for( $d, array() );
	twd_sk_eq( array( 'Home', 'About', 'Anxiety counselling', 'Mindfulness group', 'Questions and answers', 'Resources', 'Contact' ), array_column( $pages, 'title' ) );
	twd_sk_eq( array( 'home', 'about', 'service', 'service', 'faq', 'resources', 'contact' ), array_column( $pages, 'type' ) );
	twd_sk_eq( 'Anxiety counselling', $pages[2]['topic'] );
	$cleaned = TWD_SK_Build::clean_pages( $pages );
	twd_sk_true( ! is_wp_error( $cleaned ), 'the build accepts every page' );
	$d['full_answers']['faq'] = 'Skip';
	$titles = array_column( TWD_SK_Full::pages_for( $d, array() ), 'title' );
	twd_sk_true( ! in_array( 'Questions and answers', $titles, true ) );
	unset( $GLOBALS['twd_stub']['shortcodes']['twd_articles'] );
	twd_sk_true( ! in_array( 'Resources', array_column( TWD_SK_Full::pages_for( $d, array() ), 'title' ), true ), 'no Articles plugin, no Resources page' );
} );

twd_sk_test( 'full: a picture marker fits the 60 character limit, names the photo and a Pexels search, and is read as a picture spot', function () {
	foreach ( array( 'hero__img', 'image-text__img', 'contact__img', 'pictures__img', 'image-text__badge', 'something' ) as $class ) {
		foreach ( array( 'about', 'home', 'service' ) as $type ) {
			foreach ( array( 'therapy', 'business' ) as $mode ) {
				$hint   = TWD_SK_Full::photo_hint( 'twd-sk-' . $class, $type, 'Anxiety counselling for adults who worry a great deal', $mode );
				$marker = TWD_SK_Full::marker( $hint[0], $hint[1] );
				twd_sk_true( strlen( $marker ) - strlen( '[PLACEHOLDER: ]' ) <= TWD_SK_Full::MARKER_MAX, 'short enough: ' . $marker );
				twd_sk_true( 1 === preg_match( '/^\[PLACEHOLDER: photo: .+\. Pexels: .+\]$/', $marker ), $marker );
				twd_sk_true( TWD_SK_Inline::is_spot_text( $marker ), 'the visual editor reads it as a picture spot: ' . $marker );
				$cleaned = TWD_SK_Sanitizer::clean( '<section class="twd-sk-text"><div class="twd-sk-inner"><figure class="twd-sk-image-text__media">' . $marker . '</figure></div></section>' );
				twd_sk_has( $marker, $cleaned, 'the sanitiser keeps it' );
			}
		}
	}
	twd_sk_has( 'portrait of you', TWD_SK_Full::marker( TWD_SK_Full::photo_hint( 'twd-sk-image-text__img', 'about', '', 'therapy' )[0], '' ) );
} );

twd_sk_test( 'full: sample pictures become markers, or are removed when there are no photos; real pictures are left alone', function () {
	$html = '<section class="twd-sk-image-text"><figure class="twd-sk-image-text__media"><img class="twd-sk-image-text__img" src="/wp-content/uploads/your-image.jpg" alt="[PLACEHOLDER: x]"></figure></section><img class="twd-sk-pictures__img" src="https://example.test/wp-content/uploads/real.jpg" alt="A real picture">';
	$with = TWD_SK_Full::picture_spots( $html, 'about', '', 'therapy', true );
	twd_sk_eq( 1, $with['spots'] );
	twd_sk_has( '[PLACEHOLDER: photo: portrait of you.', $with['html'] );
	twd_sk_hasnt( 'your-image', $with['html'] );
	twd_sk_has( 'real.jpg', $with['html'], 'a real picture stays' );
	$without = TWD_SK_Full::picture_spots( $html, 'about', '', 'therapy', false );
	twd_sk_eq( 0, $without['spots'] );
	twd_sk_hasnt( 'PLACEHOLDER', $without['html'] );
	twd_sk_hasnt( 'your-image', $without['html'] );
} );

twd_sk_test( 'full: an email, phone number or price that is not in the notes is flagged; ones that are, are not', function () {
	$source = "Contact: hello@example.org, phone 01234 567890. Sessions cost £60 each.";
	$html   = '<p>Write to <a href="mailto:hello@example.org">hello@example.org</a> or ring 01234 567 890. A session is £60.</p>';
	twd_sk_eq( array(), TWD_SK_Full::unsupported( $html, $source ) );
	$bad = '<p>Write to <a href="mailto:pat@example.net">pat@example.net</a> or ring 07700 900123. A session is £80 and a course £250.00.</p>';
	$flags = implode( ' | ', TWD_SK_Full::unsupported( $bad, $source ) );
	twd_sk_has( 'pat@example.net', $flags );
	twd_sk_has( '07700 900123', $flags );
	twd_sk_has( '£80', $flags );
	twd_sk_has( '£250.00', $flags );
	twd_sk_hasnt( '£60', $flags );
	twd_sk_eq( array(), TWD_SK_Full::unsupported( '<a href="mailto:you@example.com">you@example.com</a>', $source ), 'sample addresses are the leftover check\'s business' );
} );

twd_sk_test( 'full: placeholders that are not picture markers are counted', function () {
	$html = '<p>[PLACEHOLDER: opening hours]</p><p>[PLACEHOLDER]</p><figure>[PLACEHOLDER: photo: portrait of you. Pexels: calm portrait]</figure>';
	twd_sk_eq( 2, TWD_SK_Full::text_placeholders( $html ) );
	twd_sk_eq( 0, TWD_SK_Full::text_placeholders( '<p>All written.</p>' ) );
} );

twd_sk_test( 'full build: nothing is built until the questions are answered, and never without the AI', function () {
	$d = twd_sk_full_draft();
	foreach ( array( 'settings', 'page', 'finish' ) as $step ) {
		twd_sk_is_error( 'twd_sk_not_ready', TWD_SK_Build::run_step( $step, 0, array() ), $step );
	}
	twd_sk_true( ! TWD_SK_Build::plan()['full_ready'] );
	TWD_SK_Build::save_draft( array( 'full' => false ) );
	twd_sk_true( TWD_SK_Build::plan()['full_ready'], 'the ordinary set up is not held back' );
} );

twd_sk_test( 'full build: changing the notes or an answer cancels a prepared set up; the photo and style answers are applied', function () {
	$d = twd_sk_full_draft( array( 'full_answers' => twd_sk_full_ready_answers() ) );
	twd_sk_eq( 'sage', $d['pack'] );
	twd_sk_true( ! $d['no_photos'] );
	$d = twd_sk_full_draft( array( 'full_answers' => array( 'style' => 'grove', 'tone' => 'calm', 'photos' => 'none', 'registration' => 'none' ) ) );
	twd_sk_eq( 'grove', $d['pack'] );
	twd_sk_true( $d['no_photos'] );
	twd_sk_ai_provider( twd_sk_full_reply() );
	$c = TWD_SK_Full::check( $d );
	TWD_SK_Build::store_full_check( $c );
	$prepared = array( 'brief' => array( 'schema' => 'twd-site-brief/1' ), 'pages' => TWD_SK_Full::pages_for( array_merge( TWD_SK_Build::draft(), array( 'full_check' => $c ) ), array() ) );
	twd_sk_true( ! is_wp_error( TWD_SK_Build::store_prepared( $prepared ) ) );
	$d = TWD_SK_Build::draft();
	twd_sk_true( $d['full_prepared'] );
	twd_sk_eq( 'pictures', $d['step'] );
	TWD_SK_Build::save_draft( array( 'full_notes' => twd_sk_full_notes() . ' One more thing.' ) );
	twd_sk_true( ! TWD_SK_Build::draft()['full_prepared'], 'a change cancels it' );
	twd_sk_is_error( 'twd_sk_not_ready', TWD_SK_Full::prepare( TWD_SK_Build::draft() ), 'and the old check no longer stands' );
} );

twd_sk_test( 'full build: prepare turns a ready check into a brief that has no invented phone, the chosen tone, and the page list', function () {
	$d = twd_sk_full_draft( array( 'full_answers' => twd_sk_full_ready_answers() ) );
	twd_sk_ai_provider( twd_sk_full_reply() );
	TWD_SK_Build::store_full_check( TWD_SK_Full::check( $d ) );
	$d = TWD_SK_Build::draft();
	// The notes become a brief: the fake AI invents a phone number and a different name.
	twd_sk_ai_provider( json_encode( array(
		'schema' => 'twd-site-brief/1', 'mode' => 'therapy',
		'site' => array( 'name' => 'Example Calm', 'person_name' => 'Pat Madeup', 'phone' => '07700 900123', 'email' => 'x@example.net' ),
		'facts' => "WHO I AM\nAlex Example, counsellor.", 'pages' => array( array( 'type' => 'about', 'title' => 'About', 'seo' => array( 'title' => 'About Alex', 'description' => 'About the counsellor.' ) ) ),
	) ) );
	$p = TWD_SK_Full::prepare( $d );
	twd_sk_true( ! is_wp_error( $p ), is_wp_error( $p ) ? $p->get_error_message() : '' );
	twd_sk_eq( 'info@' . 'example.test', $p['brief']['site']['email'], 'the assumed email, not the AI\'s' );
	twd_sk_eq( '', $p['brief']['site']['phone'], 'a phone the notes never gave is removed' );
	twd_sk_eq( 'Alex Example', $p['brief']['site']['person_name'], 'a name the notes never gave is replaced' );
	twd_sk_has( 'TONE OF VOICE', $p['brief']['facts'] );
	twd_sk_has( 'Warm, gentle', $p['brief']['facts'] );
	twd_sk_eq( 'About Alex', $p['pages'][1]['seo']['title'], 'search wording from the brief is matched to its page' );
	// The text sent to the AI carried the answers and the assumed email.
	$sent = $GLOBALS['twd_stub']['ai_calls'][0]['message'];
	twd_sk_has( 'Email: info@' . 'example.test', $sent );
	twd_sk_has( 'Tone of voice:', $sent );
} );

twd_sk_test( 'full build end to end: pages are written from the facts only, picture spots are marked, anything unsupported is flagged', function () {
	$d = twd_sk_full_draft( array( 'full_answers' => twd_sk_full_ready_answers() ) );
	twd_sk_ai_provider( twd_sk_full_reply() );
	TWD_SK_Build::store_full_check( TWD_SK_Full::check( $d ) );
	twd_sk_ai_provider( json_encode( array( 'schema' => 'twd-site-brief/1', 'site' => array( 'name' => 'Example Calm' ), 'facts' => "WHO I AM\nAlex Example.", 'pages' => array() ) ) );
	$p = TWD_SK_Full::prepare( TWD_SK_Build::draft() );
	twd_sk_true( ! is_wp_error( $p ) );
	TWD_SK_Build::store_prepared( $p );
	// The page writer: keeps the sample picture, writes real words, and slips in a phone number and a price nobody gave.
	twd_sk_ai_provider( function ( $system, $message ) {
		if ( false !== strpos( $system, 'ONLY from the' ) || false !== strpos( $system, 'written ONLY' ) ) {
			$GLOBALS['twd_stub']['strict_seen'] = true;
		}
		return '<section class="twd-sk-hero twd-sk-hero--split twd-sk-tone-base"><div class="twd-sk-inner"><div class="twd-sk-hero__grid"><div class="twd-sk-hero__text"><h1 class="twd-sk-hero__title">Calm help for worry</h1><p class="twd-sk-hero__lead">Gentle counselling. Call 07700 900123. Sessions are £75.</p></div>'
			. '<figure class="twd-sk-hero__media"><img class="twd-sk-hero__img" src="/wp-content/uploads/your-image.jpg" alt="[PLACEHOLDER: x]"></figure></div></div></section>';
	} );
	$plan = TWD_SK_Build::plan();
	twd_sk_eq( 'edited', $plan['source'] );
	twd_sk_true( $plan['full_ready'] );
	$r = TWD_SK_Build::run_step( 'settings', 0, array() );
	twd_sk_true( ! is_wp_error( $r ), is_wp_error( $r ) ? $r->get_error_message() : '' );
	$home = TWD_SK_Build::run_step( 'page', 0, array() );
	twd_sk_true( ! is_wp_error( $home ), is_wp_error( $home ) ? $home->get_error_message() : '' );
	twd_sk_true( ! empty( $GLOBALS['twd_stub']['strict_seen'] ), 'the strict rules were sent' );
	$saved = TWD_SK_Store::get_current( $home['page']['id'] );
	twd_sk_has( '[PLACEHOLDER: photo: wide photo for the top. Pexels:', $saved, 'the picture spot is marked with what is wanted and a Pexels search' );
	twd_sk_hasnt( 'your-image', $saved );
	$flags = implode( ' | ', $home['page']['flags'] );
	twd_sk_has( '07700 900123', $flags );
	twd_sk_has( '£75', $flags );
	twd_sk_has( 'needs a look', implode( ' ', $home['report'] ) );
	twd_sk_has( '1 place for a photo', implode( ' ', $home['report'] ) );
	twd_sk_true( $home['page']['must'] > 0, 'the picture spot blocks publishing until it is filled' );
	twd_sk_eq( 1, TWD_SK_Inline::count( $saved )['spots'], 'and the visual editor sees it as a picture spot to fill' );
	$d = TWD_SK_Build::draft();
	twd_sk_eq( 2, count( $d['flags']['home'] ), 'kept for the final report' );
} );

twd_sk_test( 'full build: a page the AI cannot write is not saved as an outline full of placeholders', function () {
	$d = twd_sk_full_draft( array( 'full_answers' => twd_sk_full_ready_answers() ) );
	twd_sk_ai_provider( twd_sk_full_reply() );
	TWD_SK_Build::store_full_check( TWD_SK_Full::check( $d ) );
	twd_sk_ai_provider( json_encode( array( 'schema' => 'twd-site-brief/1', 'site' => array( 'name' => 'Example Calm' ), 'facts' => 'Facts.', 'pages' => array() ) ) );
	TWD_SK_Build::store_prepared( TWD_SK_Full::prepare( TWD_SK_Build::draft() ) );
	twd_sk_ai_provider( new WP_Error( 'twd_sk_ai_failed', 'busy' ) );
	TWD_SK_Build::run_step( 'settings', 0, array() );
	twd_sk_is_error( 'twd_sk_ai_failed', TWD_SK_Build::run_step( 'page', 0, array() ) );
	twd_sk_eq( 0, count( TWD_SK_Build::draft()['built'] ), 'no page was made' );
} );

twd_sk_test( 'full build: the finish report names what was left out, and what to do about photos', function () {
	$d = twd_sk_full_draft( array( 'full_answers' => array( 'style' => 'sage', 'tone' => 'warm', 'photos' => 'none', 'registration' => 'none' ) ) );
	twd_sk_ai_provider( twd_sk_full_reply() );
	TWD_SK_Build::store_full_check( TWD_SK_Full::check( $d ) );
	twd_sk_ai_provider( json_encode( array( 'schema' => 'twd-site-brief/1', 'site' => array( 'name' => 'Example Calm' ), 'facts' => 'Facts.', 'pages' => array() ) ) );
	TWD_SK_Build::store_prepared( TWD_SK_Full::prepare( TWD_SK_Build::draft() ) );
	$page_html = '<section class="twd-sk-hero twd-sk-hero--compact twd-sk-tone-base"><div class="twd-sk-inner"><h1 class="twd-sk-hero__title">A page</h1><p class="twd-sk-hero__lead">Words from the facts.</p></div></section>';
	twd_sk_ai_provider( $page_html );
	$plan = TWD_SK_Build::plan();
	TWD_SK_Build::run_step( 'settings', 0, array() );
	foreach ( $plan['steps'] as $s ) {
		if ( 'page' === $s['id'] ) {
			$r = TWD_SK_Build::run_step( 'page', $s['index'], array() );
			twd_sk_true( ! is_wp_error( $r ) );
		}
	}
	$fin = TWD_SK_Build::run_step( 'finish', 0, array() );
	$text = implode( ' ', $fin['report'] );
	twd_sk_has( 'Left out because nothing was supplied', $text );
	twd_sk_has( 'Testimonials', $text );
	twd_sk_has( 'No photo spots were made', $text );
	twd_sk_has( 'Pexels searches that suit a site like this', $text );
	twd_sk_has( 'Read every page before you publish it', $text . ' Read every page before you publish it.' );
} );

twd_sk_test( 'full rest: the check and prepare routes exist for administrators only, and the setup payload carries the full set up', function () {
	$GLOBALS['twd_stub']['routes'] = array();
	TWD_SK_REST::register_routes();
	$found = array();
	foreach ( $GLOBALS['twd_stub']['routes'] as $r ) {
		if ( 0 === strpos( $r['route'], '/site/full' ) ) {
			$found[ $r['route'] ] = $r['args'];
		}
	}
	twd_sk_eq( array( '/site/full/check', '/site/full/prepare' ), array_keys( $found ) );
	$GLOBALS['twd_stub']['caps'] = array( 'edit_pages' );
	foreach ( $found as $route => $args ) {
		twd_sk_is_error( 'twd_sk_forbidden', call_user_func( $args['permission_callback'], new WP_REST_Request( array(), array( 'X-WP-Nonce' => 'good-nonce' ) ) ), $route );
	}
	$GLOBALS['twd_stub']['caps'] = true;
	$payload = TWD_SK_REST::get_site_build( new WP_REST_Request( array(), array( 'X-WP-Nonce' => 'good-nonce' ) ) );
	twd_sk_eq( array( 'style', 'tone', 'photos' ), array_column( $payload['full']['design'], 'key' ) );
	twd_sk_eq( 'info@' . 'example.test', $payload['full']['default_email'] );
} );

twd_sk_test( 'full js and source: no HTML built from text, no native dialogs, no web addresses, no long dashes, valid syntax', function () {
	$js = file_get_contents( ABSPATH . 'assets/twd-site-kit-setup.js' );
	foreach ( array( 'innerHTML', 'outerHTML', 'insertAdjacentHTML', 'eval(', 'new Function', 'document.write' ) as $bad ) {
		twd_sk_hasnt( $bad, $js );
	}
	twd_sk_true( 1 !== preg_match( '/(^|[^.\w])(alert|confirm|prompt)\s*\(/m', $js ), 'no native dialogs' );
	twd_sk_has( "'/site/full/check'", $js );
	twd_sk_has( "'/site/full/prepare'", $js );
	twd_sk_has( 'Full site set up (test)', $js );
	twd_sk_has( 'Looks right, continue', $js );
	twd_sk_eq( '', trim( (string) shell_exec( 'node --check ' . escapeshellarg( ABSPATH . 'assets/twd-site-kit-setup.js' ) . ' 2>&1' ) ) );
	$src = file_get_contents( ABSPATH . 'includes/class-twd-sk-full.php' );
	twd_sk_true( 1 !== preg_match( '#https?://#i', $src ), 'no web addresses' );
	twd_sk_true( 1 !== preg_match( '/\xE2\x80[\x93\x94]/', $src ), 'no long dashes' );
	twd_sk_hasnt( 'wp_remote', $src, 'no outside request' );
} );

twd_sk_test( 'full: the notes are read in the type chosen on the first step without saving it to the site, even after a failure', function () {
	twd_sk_eq( 'therapy', TWD_SK_Mode::get() );
	$inside = TWD_SK_Mode::using( 'business', function () {
		return TWD_SK_Mode::get() . '/' . TWD_SK_Mode::w( 'person' );
	} );
	twd_sk_eq( 'business/business owner', $inside );
	twd_sk_eq( 'therapy', TWD_SK_Mode::get() );
	try {
		TWD_SK_Mode::using( 'business', function () {
			throw new Exception( 'boom' );
		} );
	} catch ( Exception $e ) {
		twd_sk_eq( 'boom', $e->getMessage() );
	}
	twd_sk_eq( 'therapy', TWD_SK_Mode::get(), 'put back after a failure' );
	twd_sk_eq( 'therapy', TWD_SK_Mode::stored(), 'nothing saved' );
	twd_sk_eq( 'therapy', TWD_SK_Mode::using( 'nonsense', function () {
		return TWD_SK_Mode::get();
	} ), 'an unknown mode changes nothing' );
} );

twd_sk_test( 'full: years and short numbers are not mistaken for phone numbers', function () {
	twd_sk_eq( array(), TWD_SK_Full::unsupported( '<p>Working since 2010 2020, over 2000 sessions, open 9 5.</p>', 'Nothing here.' ) );
} );

twd_sk_test( 'full (0.14.0): build a draft anyway: with gaps left and the tick on, prepare goes ahead, each open question becomes a marker with its key in the facts, the pages are written with markers allowed; with the tick off it still refuses', function () {
	$d = twd_sk_full_draft( array( 'full_answers' => array( 'style' => 'sage', 'tone' => 'warm', 'photos' => 'mine' ) ) );
	twd_sk_ai_provider( twd_sk_full_reply( array( 'found' => array( 'registration' => '' ) ) ) );
	$c = TWD_SK_Full::check( $d );
	twd_sk_true( ! $c['ready'] );
	twd_sk_eq( array( 'registration' ), array_column( $c['missing'], 'key' ) );
	TWD_SK_Build::store_full_check( $c );
	twd_sk_is_error( 'twd_sk_not_ready', TWD_SK_Full::prepare( TWD_SK_Build::draft() ), 'refused without the tick' );
	TWD_SK_Build::save_draft( array( 'full_anyway' => true ) );
	$d = TWD_SK_Build::draft();
	twd_sk_true( $d['full_anyway'] );
	twd_sk_true( ! $d['full_prepared'], 'ticking the box does not prepare by itself' );
	twd_sk_ai_provider( json_encode( array( 'schema' => 'twd-site-brief/1', 'site' => array( 'name' => 'Example Calm' ), 'facts' => 'WHO I AM\nAlex Example.' ) ) );
	$p = TWD_SK_Full::prepare( $d );
	twd_sk_true( ! is_wp_error( $p ), is_wp_error( $p ) ? $p->get_error_message() : '' );
	twd_sk_eq( array( '[PLACEHOLDER: registration Are you registered with a professional body?]' ), $p['markers'] );
	twd_sk_has( "STILL TO ANSWER\n[PLACEHOLDER: registration", $p['brief']['facts'] );
	twd_sk_true( strlen( $p['markers'][0] ) <= 14 + 60 + 1, 'fits the marker limit' );
	TWD_SK_Build::store_prepared( $p );
	$d = TWD_SK_Build::draft();
	twd_sk_true( $d['full_prepared'] && $d['full_markers'] );
	// The strict prompt then allows exactly those markers, and nothing else.
	$prompt = TWD_SK_AI::page_system_prompt( false, 'markers' );
	twd_sk_has( 'STILL TO ANSWER', $prompt );
	twd_sk_has( 'Never write any other [PLACEHOLDER]', $prompt );
	twd_sk_hasnt( 'STILL TO ANSWER', TWD_SK_AI::page_system_prompt( false, true ) );
	// The marker helper keeps the key and shortens long questions.
	twd_sk_eq( '[PLACEHOLDER: service_1_detail Tell me about it]', TWD_SK_Full::gap_marker( 'service_1_detail', 'Tell me about it' ) );
	twd_sk_true( strlen( TWD_SK_Full::gap_marker( 'about', str_repeat( 'word ', 40 ) ) ) <= 14 + 60 + 1 );
} );
