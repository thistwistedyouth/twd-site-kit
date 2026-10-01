<?php
// The Home, About and Contact starters and the setup that creates them.

require_once __DIR__ . '/stub-wpcli.php';
require_once dirname( __DIR__ ) . '/includes/class-twd-sk-cli.php';

function twd_sk_sp_texts( $html ) {
	$out = array();
	preg_match_all( '/>([^<>]+)</', $html, $m );
	foreach ( $m[1] as $t ) {
		if ( '' !== trim( $t ) && 1 === preg_match( '/[A-Za-z]/', $t ) ) {
			$out[] = trim( $t );
		}
	}
	return $out;
}

twd_sk_test( 'starters: three pages, Home, About and Contact, each built from registry components only', function () {
	$pages = TWD_SK_Starters::pages();
	twd_sk_eq( array( 'home', 'about', 'contact' ), array_keys( $pages ) );
	twd_sk_eq( array( 'Home', 'About', 'Contact' ), array_map( function ( $p ) {
		return $p['title'];
	}, array_values( $pages ) ) );
	foreach ( $pages as $key => $page ) {
		foreach ( $page['parts'] as $part ) {
			$c = TWD_SK_Registry::get( $part[0] );
			twd_sk_true( null !== $c, $key . ' uses a real component: ' . $part[0] );
			twd_sk_true( '' === $part[1] || isset( $c['variants'][ $part[1] ] ), $key . ' uses a real variant: ' . $part[1] );
		}
	}
} );

twd_sk_test( 'starters: every page passes the sanitiser with nothing removed or changed, and survives storing', function () {
	foreach ( array_keys( TWD_SK_Starters::pages() ) as $key ) {
		$html = TWD_SK_Starters::html( $key );
		twd_sk_true( strlen( $html ) > 1000, $key . ' has content' );
		$rep = TWD_SK_Sanitizer::clean_with_report( $html );
		twd_sk_eq( 0, $rep['report']['total'], $key . ' nothing removed: ' . json_encode( $rep['report']['removed'] ) );
		twd_sk_eq( trim( $html ), trim( $rep['html'] ), $key . ' comes out as it went in' );
		twd_stub_add_post( 70, 'page', '' );
		$saved = TWD_SK_Store::save( 70, $html );
		twd_sk_true( ! is_wp_error( $saved ), $key . ' stores' );
		twd_sk_eq( 0, $saved['report']['total'] );
	}
} );

twd_sk_test( 'starters: each page has exactly one h1, in its first hero, and unique ids', function () {
	foreach ( array_keys( TWD_SK_Starters::pages() ) as $key ) {
		$html = TWD_SK_Starters::html( $key );
		twd_sk_eq( 1, preg_match_all( '/<h1\\b/', $html ), $key . ' has one h1' );
		twd_sk_true( 1 === preg_match( '/^<section class="twd-sk-hero[^"]*">.*?<h1 class="twd-sk-hero__title">/s', $html ), $key . ' the h1 is the first hero title' );
		preg_match_all( '/ id="([^"]+)"/', $html, $m );
		twd_sk_eq( count( $m[1] ), count( array_unique( $m[1] ) ), $key . ' ids are unique' );
	}
} );

twd_sk_test( 'starters: every piece of sample text, picture description and link wording is a visible placeholder (the safety notice and shortcodes aside)', function () {
	foreach ( array_keys( TWD_SK_Starters::pages() ) as $key ) {
		$html = TWD_SK_Starters::html( $key );
		// Take the safety notice section out: its support-line text is real and must be verified separately.
		$body = preg_replace( '#<section class="twd-sk-notice.*$#s', '', $html ); // the notice and its pop-up are last
		foreach ( twd_sk_sp_texts( $body ) as $text ) {
			if ( 0 === strpos( $text, '[twd_' ) ) {
				continue;
			}
			twd_sk_true( false !== strpos( $text, '[PLACEHOLDER: ' ), $key . ' text is not a placeholder: ' . $text );
		}
		preg_match_all( '/ alt="([^"]*)"/', $body, $m );
		foreach ( $m[1] as $alt ) {
			twd_sk_true( '' === $alt || false !== strpos( $alt, '[PLACEHOLDER: ' ), $key . ' alt is not a placeholder: ' . $alt );
		}
	}
} );

twd_sk_test( 'starters: the leftover check flags every starter, at must-fix level, and a stored starter blocks publishing', function () {
	foreach ( array_keys( TWD_SK_Starters::pages() ) as $key ) {
		$by = TWD_SK_Sanitizer::find_leftovers_by_level( TWD_SK_Starters::html( $key ) );
		twd_sk_true( count( $by['must'] ) >= 3, $key . ' has must-fix markers' );
		twd_sk_true( isset( $by['must']['[PLACEHOLDER'] ), $key . ' flags the placeholder marker' );
	}
	foreach ( array( 'home', 'about', 'contact' ) as $key ) {
		twd_stub_add_post( 71, 'page', '[twd_page]' );
		TWD_SK_Store::save( 71, TWD_SK_Starters::html( $key ), array() );
		get_post( 71 )->post_status = 'draft';
		twd_sk_is_error( 'twd_sk_leftovers', TWD_SK_Template::set_status( 71, 'publish', false ), $key . ' cannot be published as it stands' );
		$GLOBALS['twd_stub']['meta'] = array();
	}
} );

twd_sk_test( 'starters: they invent nothing (no real-looking email, phone number, address, credential or client word)', function () {
	foreach ( array_keys( TWD_SK_Starters::pages() ) as $key ) {
		$html = preg_replace( '#<section class="twd-sk-notice.*$#s', '', TWD_SK_Starters::html( $key ) ); // the notice holds real support lines
		preg_match_all( '/[A-Za-z0-9._%+-]+@[A-Za-z0-9.-]+\\.[A-Za-z]{2,}/', $html, $m );
		foreach ( $m[0] as $email ) {
			twd_sk_eq( 'you@example.com', $email, $key . ' only the example email' );
		}
		twd_sk_true( 1 !== preg_match( '/\\b0\\d{3,4}[ ]?\\d{3,4}[ ]?\\d{3,4}\\b|\\+44/', $html ), $key . ' no phone number' );
		foreach ( array( 'MBACP', 'BACP', 'UKCP', 'HCPC', 'DBS', 'accredited', 'registered with' ) as $claim ) {
			twd_sk_hasnt( $claim, $html, $key );
		}
		twd_sk_hasnt( "\xE2\x80\x94", $html );
		preg_match_all( '#https?://([^/"\s]+)#', $html, $hosts );
		foreach ( $hosts[1] as $host ) {
			twd_sk_eq( 'www.example.com', $host, $key . ' only the example address' );
		}
	}
} );

twd_sk_test( 'starters: the committed files are exactly what the generator builds', function () {
	foreach ( TWD_SK_Starters::files() as $name => $html ) {
		twd_sk_true( file_exists( ABSPATH . 'starters/' . $name ), $name . ' exists: run php bin/build-starters.php' );
		twd_sk_eq( $html, file_get_contents( ABSPATH . 'starters/' . $name ), $name );
	}
	twd_sk_eq( array( 'page-home.html', 'page-about.html', 'page-contact.html' ), array_keys( TWD_SK_Starters::files() ) );
} );

twd_sk_test( 'starters: text longer than a placeholder allows is shortened, brackets cannot nest, and shortcodes are left alone', function () {
	$out = TWD_SK_Starters::placeholderise( '<p>' . str_repeat( 'word ', 30 ) . '</p><p>Has [brackets] inside</p><p>[twd_articles count="3"]</p><p>12</p><img alt="A [bad] alt" src="/x.jpg"><img alt="" src="/y.jpg">' );
	preg_match( '/\\[PLACEHOLDER: ([^\\]]+)\\]/', $out, $m );
	twd_sk_true( strlen( $m[1] ) <= 60 && '...' === substr( $m[1], -3 ) );
	twd_sk_has( '<p>Has [brackets] inside</p>', $out, 'text that already has brackets is left alone' );
	twd_sk_has( '[twd_articles count="3"]', $out );
	twd_sk_has( '<p>12</p>', $out, 'no letters, no marker' );
	twd_sk_has( 'alt="[PLACEHOLDER: A bad alt]"', $out );
	twd_sk_has( 'alt=""', $out );
	twd_sk_eq( $out, TWD_SK_Starters::placeholderise( $out ), 'running it again changes nothing' );
} );

// -- Setup ------------------------------------------------------------------------------

function twd_sk_setup_ids() {
	$out = array();
	foreach ( array( 'home', 'about', 'contact' ) as $k ) {
		$out[ $k ] = TWD_SK_Setup::existing( $k );
	}
	return $out;
}

twd_sk_test( 'setup: creates Home, About and Contact as DRAFT kit pages with the starter HTML, fills a blank profile, sets the front page', function () {
	$r = TWD_SK_Setup::run( array() );
	twd_sk_true( ! is_wp_error( $r ) );
	twd_sk_eq( 3, count( $r['created'] ) );
	twd_sk_eq( 0, count( $r['skipped'] ) );
	$ids = twd_sk_setup_ids();
	foreach ( $ids as $key => $id ) {
		twd_sk_true( $id > 0, $key );
		$post = get_post( $id );
		twd_sk_eq( 'draft', $post->post_status, $key . ' is a draft' );
		twd_sk_eq( 'page', $post->post_type );
		twd_sk_true( TWD_SK_Template::uses_template( $id ), $key . ' uses the kit template' );
		twd_sk_eq( trim( TWD_SK_Sanitizer::clean( TWD_SK_Starters::html( $key ) ) ), trim( TWD_SK_Store::get_current( $id ) ), $key . ' holds its starter' );
		twd_sk_eq( 1, TWD_SK_Store::get_current_version_id( $id ) );
		twd_sk_eq( 'Starter layout', TWD_SK_Store::get_version( $id, 1 )['note'] );
	}
	twd_sk_eq( 'filled', $r['profile'] );
	$p = TWD_SK_Profile::get();
	twd_sk_eq( '[PLACEHOLDER: practice name]', $p['site_name'] );
	twd_sk_eq( $ids['about'], $p['about_page_id'] );
	twd_sk_eq( 'set', $r['front_page'] );
	twd_sk_eq( 'page', get_option( 'show_on_front' ) );
	twd_sk_eq( $ids['home'], get_option( 'page_on_front' ) );
	twd_sk_true( count( $GLOBALS['twd_stub']['updated'] ) === 0, 'nothing was published' );
	foreach ( $GLOBALS['twd_stub']['inserted'] as $args ) {
		twd_sk_eq( 'draft', $args['post_status'] );
	}
} );

twd_sk_test( 'setup: running it twice creates nothing new and changes nothing', function () {
	TWD_SK_Setup::run( array() );
	$ids    = twd_sk_setup_ids();
	$before = count( $GLOBALS['twd_stub']['inserted'] );
	$r      = TWD_SK_Setup::run( array() );
	twd_sk_eq( 0, count( $r['created'] ) );
	twd_sk_eq( 3, count( $r['skipped'] ) );
	twd_sk_eq( $before, count( $GLOBALS['twd_stub']['inserted'] ) );
	twd_sk_eq( $ids, twd_sk_setup_ids() );
	twd_sk_eq( 'kept', $r['profile'] );
	foreach ( $ids as $id ) {
		twd_sk_eq( 1, TWD_SK_Store::get_current_version_id( $id ), 'no new version' );
	}
} );

twd_sk_test( 'setup: a profile that already has content is never overwritten', function () {
	TWD_SK_Profile::save( array( 'site_name' => 'Real Practice', 'phone' => '01234 567890' ) );
	$r = TWD_SK_Setup::run( array() );
	twd_sk_eq( 'kept', $r['profile'] );
	$p = TWD_SK_Profile::get();
	twd_sk_eq( 'Real Practice', $p['site_name'] );
	twd_sk_eq( '01234 567890', $p['phone'] );
	twd_sk_eq( array(), $p['menu'], 'nothing filled in around it' );
	twd_sk_true( $p['about_page_id'] > 0, 'the About page is still linked' );
	twd_sk_true( in_array( 'The site details already had content, so they were not changed.', $r['notes'], true ) );
} );

twd_sk_test( 'setup: an existing page is skipped even when it is not a draft, and a published front page is left alone', function () {
	twd_stub_add_post( 80, 'page', '' );
	update_post_meta( 80, TWD_SK_Starters::META_KEY, 'home' );
	get_post( 80 )->post_status = 'publish';
	$GLOBALS['twd_stub']['options']['show_on_front'] = 'page';
	$GLOBALS['twd_stub']['options']['page_on_front'] = 80;
	$r = TWD_SK_Setup::run( array() );
	twd_sk_eq( 2, count( $r['created'] ) );
	twd_sk_eq( 'home', $r['skipped'][0]['key'] );
	twd_sk_eq( 80, $r['skipped'][0]['id'] );
	twd_sk_eq( 'kept', $r['front_page'] );
	twd_sk_eq( 80, get_option( 'page_on_front' ) );
	twd_sk_eq( 'publish', get_post( 80 )->post_status, 'not touched' );
} );

twd_sk_test( 'setup: the pack can be switched, an unknown one is refused before anything is created, and the front page can be skipped', function () {
	$e = TWD_SK_Setup::run( array( 'pack' => 'nope' ) );
	twd_sk_is_error( 'twd_sk_unknown_pack', $e );
	twd_sk_eq( array( 'home' => 0, 'about' => 0, 'contact' => 0 ), twd_sk_setup_ids(), 'nothing created' );
	$r = TWD_SK_Setup::run( array( 'pack' => 'grove', 'front_page' => false ) );
	twd_sk_eq( 'grove', TWD_SK_Packs::active_slug() );
	twd_sk_eq( 'grove', $r['pack'] );
	twd_sk_eq( '', $r['front_page'] );
	twd_sk_true( false === get_option( 'show_on_front' ) || 'page' !== get_option( 'show_on_front' ) );
} );

twd_sk_test( 'setup: plan() describes what would happen and changes nothing', function () {
	$lines = TWD_SK_Setup::plan( array( 'pack' => 'grove' ) );
	$text  = implode( ' | ', $lines );
	twd_sk_has( 'Create a draft page called Home', $text );
	twd_sk_has( 'Switch the style to grove', $text );
	twd_sk_has( 'Fill the empty site details with placeholders', $text );
	twd_sk_has( 'Make Home the front page', $text );
	twd_sk_has( 'error there until Home is published', $text );
	twd_sk_eq( array( 'home' => 0, 'about' => 0, 'contact' => 0 ), twd_sk_setup_ids() );
	twd_sk_eq( array(), $GLOBALS['twd_stub']['inserted'] );
	TWD_SK_Setup::run( array() );
	twd_sk_has( 'Home already exists', implode( ' | ', TWD_SK_Setup::plan() ) );
} );

twd_sk_test( 'setup: a failed page creation stops with the error and does not claim success', function () {
	$GLOBALS['twd_stub']['insert_fails'] = true;
	twd_sk_is_error( 'insert_failed', TWD_SK_Setup::run( array() ) );
} );

twd_sk_test( 'setup rest: the plan route reads, the run route needs a confirmation, both need administrator rights', function () {
	twd_sk_site_caps();
	$plan = TWD_SK_REST::get_site_setup( twd_sk_rest_req() );
	twd_sk_true( count( $plan['plan'] ) >= 4 );
	twd_sk_eq( array( 'grove', 'sage' ), $plan['packs'] );
	twd_sk_eq( array( 'home' => 0, 'about' => 0, 'contact' => 0 ), twd_sk_setup_ids(), 'reading the plan creates nothing' );
	twd_sk_is_error( 'twd_sk_confirm_needed', TWD_SK_REST::post_site_setup( twd_sk_rest_req() ) );
	twd_sk_is_error( 'twd_sk_confirm_needed', TWD_SK_REST::post_site_setup( twd_sk_rest_req( array( 'confirm' => false ) ) ) );
	twd_sk_eq( array( 'home' => 0, 'about' => 0, 'contact' => 0 ), twd_sk_setup_ids(), 'no confirmation, nothing created' );
	$out = TWD_SK_REST::post_site_setup( twd_sk_rest_req( array( 'confirm' => true, 'pack' => 'grove' ) ) );
	twd_sk_eq( 3, count( $out['created'] ) );
	twd_sk_eq( 'grove', $out['pack'] );
	twd_sk_true( isset( $out['profile_state']['profile'] ) );
	twd_sk_is_error( 'twd_sk_bad_input', TWD_SK_REST::post_site_setup( twd_sk_rest_req( array( 'confirm' => true, 'pack' => array( 'x' ) ) ) ) );
	twd_sk_is_error( 'twd_sk_unknown_pack', TWD_SK_REST::post_site_setup( twd_sk_rest_req( array( 'confirm' => true, 'pack' => 'nope' ) ) ) );
	foreach ( twd_sk_site_routes() as $r ) {
		if ( '/site/setup' === $r['route'] ) {
			$GLOBALS['twd_stub']['caps'] = array( 'edit_pages', 'edit_post:12' );
			twd_sk_is_error( 'twd_sk_forbidden', call_user_func( $r['args']['permission_callback'], twd_sk_rest_req() ), 'editor refused on setup' );
		}
	}
} );

twd_sk_test( 'setup cli: creates the pages, reports what it did, and skips the front page when asked', function () {
	WP_CLI::reset();
	( new TWD_SK_CLI() )->setup( array(), array( 'pack' => 'sage', 'skip-front-page' => true ) );
	$all = WP_CLI::all();
	twd_sk_has( 'Created draft page "Home"', $all );
	twd_sk_has( 'Created draft page "About"', $all );
	twd_sk_has( 'Created draft page "Contact"', $all );
	twd_sk_has( 'Style pack is now sage', $all );
	twd_sk_has( 'Filled the empty site details with placeholders', $all );
	twd_sk_has( 'replace every [PLACEHOLDER]', $all );
	twd_sk_hasnt( 'front page', $all );
	WP_CLI::reset();
	( new TWD_SK_CLI() )->setup( array(), array() );
	twd_sk_has( 'Skipped "Home"', WP_CLI::all() );
	twd_sk_has( 'Home is the front page but is still a draft', WP_CLI::all() );
	WP_CLI::reset();
	twd_sk_has( 'no style pack', strtolower( twd_sk_cli_fails( function () {
		( new TWD_SK_CLI() )->setup( array(), array( 'pack' => 'nope' ) );
	} ) ) );
} );

twd_sk_test( 'setup: the classes write pages only through the template class and the store, and never touch the network', function () {
	foreach ( array( 'class-twd-sk-setup.php', 'class-twd-sk-starters.php' ) as $f ) {
		$src = file_get_contents( ABSPATH . 'includes/' . $f );
		foreach ( array( 'wp_insert_post', 'wp_update_post', 'update_post_meta( $id, \'_twd_sk_html', 'wp_remote_', 'file_put_contents', 'eval(' ) as $bad ) {
			twd_sk_hasnt( $bad, $src, $f );
		}
		twd_sk_hasnt( "\xE2\x80\x94", $src );
	}
	twd_sk_has( 'TWD_SK_Store::save', file_get_contents( ABSPATH . 'includes/class-twd-sk-setup.php' ) );
	twd_sk_has( 'TWD_SK_Template::create_page', file_get_contents( ABSPATH . 'includes/class-twd-sk-setup.php' ) );
} );
