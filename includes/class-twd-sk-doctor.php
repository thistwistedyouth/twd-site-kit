<?php
/**
 * TWD_SK_Doctor: a plain-text report of the site's state, safe to paste into a message.
 *
 * Read only. It changes nothing and calls nothing outside the site. It states what is filled in
 * and what is empty, never the contents of the practice facts, the site details, a key or a
 * password. Page titles, picture file names and picture addresses are included because the report is
 * for fixing problems with the site's pages and pictures.
 *
 * Every section is read on its own, so one thing that cannot be read never stops the rest.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class TWD_SK_Doctor {

	const MAX_PAGES    = 80;
	const MAX_PICTURES = 200;

	/** One line of safe text: no tags, no line breaks, no control characters, no long dashes, cut to a length. */
	public static function t( $value, $max = 120 ) {
		$v = preg_replace( '/\s+/u', ' ', wp_strip_all_tags( (string) $value ) );
		$v = preg_replace( '/[\x00-\x1F\x7F]/', '', $v );
		$v = TWD_SK_Sanitizer::strip_dashes( trim( $v ) );
		if ( function_exists( 'mb_strlen' ) && mb_strlen( $v, 'UTF-8' ) > $max ) {
			return mb_substr( $v, 0, $max - 3, 'UTF-8' ) . '...';
		}
		return strlen( $v ) > $max && ! function_exists( 'mb_strlen' ) ? substr( $v, 0, $max - 3 ) . '...' : $v;
	}

	private static function yes( $b ) {
		return $b ? 'yes' : 'no';
	}

	private static function filled( $v ) {
		if ( is_array( $v ) ) {
			return $v ? count( $v ) . ' item' . ( 1 === count( $v ) ? '' : 's' ) : 'empty';
		}
		if ( ! is_string( $v ) || '' === trim( $v ) ) {
			return 'empty';
		}
		return false !== stripos( $v, '[PLACEHOLDER' ) || false !== stripos( $v, 'PHONE_NUMBER' ) ? 'placeholder text still there' : 'filled';
	}

	private static function kb( $bytes ) {
		return $bytes >= 1024 * 1024 ? round( $bytes / ( 1024 * 1024 ), 1 ) . ' MB' : max( 1, (int) round( $bytes / 1024 ) ) . ' KB';
	}

	/** Run one section, never letting it stop the report. Returns its lines. */
	private static function section( $title, $fn ) {
		$lines = array( '', strtoupper( $title ) );
		try {
			$got = call_user_func( $fn );
			foreach ( (array) $got as $l ) {
				$lines[] = $l;
			}
		} catch ( \Throwable $e ) {
			$lines[] = 'Could not read this section (' . self::t( get_class( $e ), 60 ) . ').';
		}
		return $lines;
	}

	public static function report() {
		$out   = array();
		$out[] = 'TWD Site Kit report';
		$out[] = 'Made: ' . gmdate( 'Y-m-d H:i' ) . ' UTC';
		$out[] = 'It holds no keys, no passwords and none of the text of the practice facts or site details. It is safe to paste into a message.';
		$parts = array(
			array( 'Plugin and site', array( __CLASS__, 'plugin' ) ),
			array( 'Style', array( __CLASS__, 'style' ) ),
			array( 'Front page', array( __CLASS__, 'front_page' ) ),
			array( 'Elementor Theme Builder', array( __CLASS__, 'theme_builder' ) ),
			array( 'Site details and practice facts', array( __CLASS__, 'details' ) ),
			array( 'Pages', array( __CLASS__, 'pages' ) ),
			array( 'Pictures in the Media Library', array( __CLASS__, 'pictures' ) ),
		);
		foreach ( $parts as $p ) {
			$out = array_merge( $out, self::section( $p[0], $p[1] ) );
		}
		return implode( "\n", $out ) . "\n";
	}

	// -- Sections ---------------------------------------------------------------

	public static function plugin() {
		$l   = array();
		$l[] = 'Site Kit version: ' . ( defined( 'TWD_SK_VERSION' ) ? self::t( TWD_SK_VERSION, 20 ) : 'unknown' );
		$l[] = 'Safe mode: ' . ( TWD_SK_Safe::on() ? 'ON (set by ' . TWD_SK_Safe::source() . ')' : 'off' );
		$l[] = 'AI available to Site Kit (another plugin offers the two AI filters and has a key saved): ' . self::yes( TWD_SK_AI::available() );
		$seo = TWD_SK_Seo::detect();
		$l[] = 'SEO plugin: ' . ( 'none' === $seo['mode'] ? 'none (Site Kit prints the search tags itself)' : self::t( $seo['name'], 40 ) . ( 'other' === $seo['mode'] ? ' (Site Kit does not write its fields)' : '' ) );
		$l[] = 'Elementor: ' . ( defined( 'ELEMENTOR_VERSION' ) ? self::t( ELEMENTOR_VERSION, 20 ) : 'not found' ) . '; Elementor Pro: ' . ( defined( 'ELEMENTOR_PRO_VERSION' ) ? self::t( ELEMENTOR_PRO_VERSION, 20 ) : 'not found' );
		$theme = function_exists( 'wp_get_theme' ) ? wp_get_theme() : null;
		$l[] = 'Theme: ' . ( $theme && is_object( $theme ) && method_exists( $theme, 'get' ) ? self::t( $theme->get( 'Name' ), 60 ) . ' ' . self::t( $theme->get( 'Version' ), 20 ) : 'unknown' );
		$l[] = 'Active plugins: ' . self::active_plugins();
		$l[] = 'PHP: ' . PHP_VERSION;
		$l[] = 'WordPress: ' . ( function_exists( 'get_bloginfo' ) ? self::t( get_bloginfo( 'version' ), 20 ) : 'unknown' );
		return $l;
	}

	/** Active plugins as "name version", names only (no keys, no settings). */
	private static function active_plugins() {
		if ( ! function_exists( 'get_plugins' ) && defined( 'ABSPATH' ) && file_exists( ABSPATH . 'wp-admin/includes/plugin.php' ) ) {
			require_once ABSPATH . 'wp-admin/includes/plugin.php';
		}
		if ( ! function_exists( 'get_plugins' ) ) {
			return 'could not be read';
		}
		$active = (array) get_option( 'active_plugins', array() );
		$all    = (array) get_plugins();
		$names  = array();
		foreach ( $active as $file ) {
			if ( isset( $all[ $file ] ) ) {
				$names[] = self::t( $all[ $file ]['Name'], 50 ) . ' ' . self::t( $all[ $file ]['Version'], 15 );
			}
		}
		sort( $names );
		return $names ? implode( '; ', $names ) : 'none found';
	}

	public static function style() {
		$o   = TWD_SK_Packs::overrides();
		$l   = array();
		$l[] = 'Active pack: ' . self::t( TWD_SK_Packs::active_slug(), 40 );
		$l[] = 'Colour, font and corner changes on top of it: ' . count( $o ) . ( $o ? ' (' . implode( ', ', array_map( function ( $k ) {
			return self::t( $k, 30 );
		}, array_keys( $o ) ) ) . ')' : '' );
		$c   = TWD_SK_Chrome::effective();
		$l[] = 'Header layout: ' . $c['header'] . '; footer layout: ' . $c['footer'] . '; fixed header: ' . self::yes( $c['sticky'] ) . '; header button: ' . self::yes( $c['button'] ) . '; contact strip: ' . self::yes( $c['strip'] ) . '; footer columns: ' . (int) $c['columns'];
		return $l;
	}

	private static function page_line( $id ) {
		$post = get_post( (int) $id );
		return $post ? 'ID ' . (int) $id . ', "' . self::t( $post->post_title, 60 ) . '", ' . self::t( $post->post_status, 20 ) : 'ID ' . (int) $id . ' (not found)';
	}

	public static function front_page() {
		$show = function_exists( 'get_option' ) ? (string) get_option( 'show_on_front', 'posts' ) : 'posts';
		$l    = array();
		if ( 'page' !== $show ) {
			$l[] = 'Reading setting: your latest posts (no static front page)';
			return $l;
		}
		$front = (int) get_option( 'page_on_front' );
		$posts = (int) get_option( 'page_for_posts' );
		$l[]   = 'Reading setting: a static page';
		$l[]   = 'Front page: ' . ( $front ? self::page_line( $front ) : 'not chosen' );
		$l[]   = 'Posts page: ' . ( $posts ? self::page_line( $posts ) : 'not chosen' );
		if ( $front ) {
			$post = get_post( $front );
			if ( $post && 'publish' !== $post->post_status ) {
				$l[] = 'WARNING: the front page is not published, so visitors will see an error.';
			}
		}
		return $l;
	}

	public static function theme_builder() {
		if ( ! defined( 'ELEMENTOR_VERSION' ) ) {
			return array( 'Elementor is not active, so the Theme Builder was not checked.' );
		}
		$ids  = get_posts( array( 'post_type' => 'elementor_library', 'post_status' => 'any', 'numberposts' => 100, 'fields' => 'ids' ) );
		$by   = array( 'header' => array(), 'footer' => array() );
		foreach ( (array) $ids as $id ) {
			$type = (string) get_post_meta( (int) $id, '_elementor_template_type', true );
			if ( ! isset( $by[ $type ] ) ) {
				continue;
			}
			$cond = get_post_meta( (int) $id, '_elementor_conditions', true );
			$cond = is_array( $cond ) ? implode( ', ', array_map( function ( $c ) {
				return self::t( $c, 60 );
			}, $cond ) ) : self::t( $cond, 120 );
			$data = get_post_meta( (int) $id, '_elementor_data', true );
			$data = is_string( $data ) ? $data : '';
			$code = 'header' === $type ? '[twd_header]' : '[twd_footer]';
			$by[ $type ][] = self::page_line( $id ) . '; display conditions: ' . ( '' === $cond ? 'none set' : $cond ) . '; holds ' . $code . ': ' . self::yes( false !== stripos( $data, trim( $code, '[]' ) ) );
		}
		$l = array();
		foreach ( array( 'header', 'footer' ) as $type ) {
			if ( ! $by[ $type ] ) {
				$l[] = ucfirst( $type ) . ' template: none found';
				continue;
			}
			foreach ( $by[ $type ] as $row ) {
				$l[] = ucfirst( $type ) . ' template: ' . $row;
			}
		}
		$l[] = 'A template needs a display condition of the entire site (include/general) to show.';
		return $l;
	}

	public static function details() {
		$p   = TWD_SK_Profile::get();
		$l   = array();
		$map = array(
			'site_name' => 'Site name', 'menu' => 'Menu', 'cta_label' => 'Header button text', 'cta_url' => 'Header button link', 'phone' => 'Phone', 'email' => 'Email',
			'address' => 'Address lines', 'area_served' => 'Where they work', 'footer_text' => 'Footer text', 'legal' => 'Legal links', 'registration' => 'Registration lines',
			'person_name' => 'Therapist name', 'person_job' => 'Therapist job title', 'same_as' => 'Profile links',
		);
		foreach ( $map as $key => $label ) {
			$l[] = $label . ': ' . self::filled( $p[ $key ] );
		}
		$l[] = 'Logo picture: ' . ( $p['logo_id'] ? 'set (picture ' . (int) $p['logo_id'] . ')' : 'not set' );
		$facts = TWD_SK_Facts::get();
		$len   = function_exists( 'mb_strlen' ) ? mb_strlen( $facts, 'UTF-8' ) : strlen( $facts );
		$when  = TWD_SK_Facts::updated();
		$l[]   = 'Practice facts: ' . ( '' === $facts ? 'empty' : 'filled (' . $len . ' characters' . ( '' !== $when ? ', saved ' . self::t( $when, 30 ) : '' ) . ')' );
		$left  = TWD_SK_Profile::leftovers( $p );
		$l[]   = 'Example text still in the site details: must fix ' . TWD_SK_Report::leftover_total( $left['must'] ) . ', check ' . TWD_SK_Report::leftover_total( $left['check'] );
		return $l;
	}

	public static function pages() {
		$ids   = get_posts( array( 'post_type' => 'page', 'post_status' => 'any', 'numberposts' => 300, 'fields' => 'ids', 'orderby' => 'ID', 'order' => 'ASC' ) );
		$rows  = array();
		$count = array( 'kit' => 0, 'other' => 0, 'publish' => 0, 'draft' => 0, 'rest' => 0 );
		foreach ( (array) $ids as $id ) {
			$post = get_post( (int) $id );
			if ( ! $post ) {
				continue;
			}
			$kit = TWD_SK_Page::is_kit_page( (int) $id );
			$kit ? $count['kit']++ : $count['other']++;
			if ( 'publish' === $post->post_status ) {
				$count['publish']++;
			} elseif ( 'draft' === $post->post_status ) {
				$count['draft']++;
			} else {
				$count['rest']++;
			}
			$tpl = $kit ? ( TWD_SK_Template::uses_template( (int) $id ) ? 'kit template' : 'kit content, theme template' ) : 'not a kit page';
			$row = 'ID ' . (int) $id . ' | ' . self::t( $post->post_status, 12 ) . ' | ' . $tpl;
			if ( $kit ) {
				$lv   = TWD_SK_Store::get_leftover_levels( (int) $id );
				$row .= ' | must fix ' . TWD_SK_Report::leftover_total( $lv['must'] ) . ' | check ' . TWD_SK_Report::leftover_total( $lv['check'] );
			}
			$rows[] = $row . ' | "' . self::t( $post->post_title, 50 ) . '"';
		}
		$l   = array();
		$l[] = 'Pages: ' . ( $count['kit'] + $count['other'] ) . ' (' . $count['kit'] . ' use the kit, ' . $count['other'] . ' do not); published ' . $count['publish'] . ', drafts ' . $count['draft'] . ', other ' . $count['rest'];
		foreach ( array_slice( $rows, 0, self::MAX_PAGES ) as $r ) {
			$l[] = $r;
		}
		if ( count( $rows ) > self::MAX_PAGES ) {
			$l[] = '... and ' . ( count( $rows ) - self::MAX_PAGES ) . ' more pages not listed.';
		}
		return $l;
	}

	public static function pictures() {
		$ids = get_posts( array( 'post_type' => 'attachment', 'post_status' => 'inherit', 'post_mime_type' => 'image', 'numberposts' => self::MAX_PICTURES + 1, 'fields' => 'ids', 'orderby' => 'ID', 'order' => 'DESC' ) );
		$ids = array_values( array_filter( (array) $ids, function ( $id ) {
			return function_exists( 'wp_attachment_is_image' ) ? wp_attachment_is_image( (int) $id ) : true;
		} ) );
		if ( ! $ids ) {
			return array( 'No pictures in the Media Library.' );
		}
		$more   = count( $ids ) > self::MAX_PICTURES;
		$ids    = array_slice( $ids, 0, self::MAX_PICTURES );
		$rows   = array();
		$alt_ok = 0;
		foreach ( $ids as $id ) {
			$file = function_exists( 'get_attached_file' ) ? (string) get_attached_file( (int) $id ) : '';
			$name = '' !== $file ? basename( $file ) : 'unknown';
			$url  = function_exists( 'wp_get_attachment_url' ) ? (string) wp_get_attachment_url( (int) $id ) : '';
			$alt  = trim( (string) get_post_meta( (int) $id, '_wp_attachment_image_alt', true ) );
			$meta = function_exists( 'wp_get_attachment_metadata' ) ? wp_get_attachment_metadata( (int) $id ) : array();
			$dim  = is_array( $meta ) && ! empty( $meta['width'] ) && ! empty( $meta['height'] ) ? (int) $meta['width'] . 'x' . (int) $meta['height'] : 'size unknown';
			$size = '' !== $file && @file_exists( $file ) ? self::kb( (int) @filesize( $file ) ) : 'file size unknown';
			if ( '' !== $alt ) {
				$alt_ok++;
			}
			$rows[] = 'ID ' . (int) $id . ' | ' . self::t( $name, 60 ) . ' | alt text: ' . ( '' !== $alt ? 'present' : 'missing' ) . ' | ' . $dim . ' | ' . $size . ' | ' . self::t( $url, 200 );
		}
		$l   = array();
		$l[] = count( $ids ) . ( $more ? ' or more' : '' ) . ' pictures; alt text present on ' . $alt_ok . ', missing on ' . ( count( $ids ) - $alt_ok ) . '. (A picture that is only decoration may correctly have no alt text.)';
		foreach ( $rows as $r ) {
			$l[] = $r;
		}
		if ( $more ) {
			$l[] = '... more pictures exist and are not listed.';
		}
		return $l;
	}
}
