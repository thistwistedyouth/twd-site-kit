<?php
/**
 * TWD_SK_SiteExport: the whole site, one page, or the header and footer, as one download an AI can read.
 *
 * Why: an AI that cannot reach a staging site can still check a site against someone's notes if it is handed the
 * site as files. The export holds the pages (one HTML file each, a comment line before every section giving its
 * number), the header and footer settings, the footer's own HTML when the footer is an embed, the style tokens,
 * the site's own stylesheet, the practice facts, the owner's notes, and a README that tells the AI what each file
 * is, the rules a change must follow, and the one reply format the site can read back (see TWD_SK_SiteImport).
 *
 * It is a zip when the server can make one, and one markdown file otherwise. Administrators only (the REST route
 * and the command line decide). The files hold the client's words and contact details: they are for the builder
 * and the builder's AI, never for this public repository. Nothing here writes anything.
 *
 * @package TWD_Site_Kit
 */

defined( 'ABSPATH' ) || exit;

class TWD_SK_SiteExport {

	const MAX_NOTES = 20000;
	const MAX_PAGES = 300;

	/** The reply format's name, shared with the importer. */
	const REPLY_SCHEMA = 'twd-site-changes/1';

	/** A page's file stem: its address slug, or page-ID. */
	public static function stem( $page_id ) {
		$post = get_post( (int) $page_id );
		$slug = $post && isset( $post->post_name ) ? (string) $post->post_name : '';
		$slug = '' !== $slug ? $slug : sanitize_title( (string) get_the_title( (int) $page_id ) );
		return '' !== $slug ? $slug : 'page-' . (int) $page_id;
	}

	/**
	 * A harmless worked example of a find and replace taken from the site's own words: the first short heading or
	 * paragraph that appears exactly once in its section, replaced by itself. Applying it changes nothing.
	 */
	public static function example_change( $stem, $number, $section ) {
		if ( ! preg_match_all( '#<(?:h[1-6]|p|li)\b[^>]*>([^<]{12,90})</(?:h[1-6]|p|li)>#', $section, $m ) ) {
			return null;
		}
		foreach ( $m[1] as $text ) {
			if ( 1 === preg_match( '/^[A-Za-z0-9 ,.\'?!-]+$/', $text ) && 1 === substr_count( $section, $text ) ) {
				return array( 'page' => $stem, 'section' => (int) $number, 'find' => $text, 'replace' => $text, 'why' => 'Example only: shows a find and replace. The new words equal the old ones.' );
			}
		}
		return null;
	}

	/** A page's social share picture: its address, or '' when it has none of its own. */
	private static function seo_picture( $id ) {
		if ( ! class_exists( 'TWD_SK_Seo' ) ) {
			return '';
		}
		$pid = (int) TWD_SK_Seo::values( (int) $id )['image_id'];
		$url = $pid > 0 && function_exists( 'wp_get_attachment_url' ) ? wp_get_attachment_url( $pid ) : '';
		return is_string( $url ) ? $url : '';
	}

	/** Search settings for the whole site: who prints the tags, whether engines are allowed, the default share picture, the structured data. */
	public static function seo_summary() {
		$det  = TWD_SK_Seo::detect();
		$p    = TWD_SK_Profile::get();
		$sid  = (int) $p['social_image_id'] > 0 ? (int) $p['social_image_id'] : (int) $p['logo_id'];
		$url  = $sid > 0 && function_exists( 'wp_get_attachment_url' ) ? wp_get_attachment_url( $sid ) : '';
		$nodes = class_exists( 'TWD_SK_Schema' ) ? TWD_SK_Schema::nodes( $p ) : array();
		return array(
			'search_tags_printed_by'   => 'none' === $det['mode'] ? 'Site Kit' : ( '' !== $det['name'] ? $det['name'] : $det['mode'] ),
			'search_engines_allowed'   => '1' === (string) get_option( 'blog_public', '1' ),
			'search_engines_note'      => '1' === (string) get_option( 'blog_public', '1' ) ? 'Search engines are allowed to index this site.' : 'Search engines are asked NOT to index this site (Settings, Reading). Expected on a staging site; turn it off before launch.',
			'site_name'                => function_exists( 'get_bloginfo' ) ? (string) get_bloginfo( 'name' ) : '',
			'default_social_picture'   => is_string( $url ) ? $url : '',
			'structured_data_on_the_front_page' => $nodes,
			'per_page'                 => 'Each page has its own search title, search description, social picture and keep-out-of-search setting in pages/_index.json (seo_title, seo_description, seo_social_picture, seo_noindex). A page with no social picture uses default_social_picture.',
		);
	}

	/** Where this site's uploads live: array( base address, folder ), or null. */
	private static function uploads() {
		if ( ! function_exists( 'wp_upload_dir' ) ) {
			return null;
		}
		$u = wp_upload_dir();
		if ( ! is_array( $u ) || empty( $u['baseurl'] ) || empty( $u['basedir'] ) ) {
			return null;
		}
		return array( rtrim( (string) $u['baseurl'], '/' ), rtrim( (string) $u['basedir'], '/\\' ) );
	}

	/**
	 * Every picture the exported pages, the footer and the site details use: where it is used, its description, its
	 * size, and whether the file is there. Pictures outside this site's media library are listed as such.
	 *
	 * @return array { list[], notes[] }
	 */
	public static function pictures( $sections_by_page, $footer_html = '', $with_site = true ) {
		$found = array();
		$add   = function ( $src, $alt, $where, $role ) use ( &$found ) {
			$src = html_entity_decode( (string) $src, ENT_QUOTES, 'UTF-8' );
			if ( '' === $src || 0 === strpos( $src, 'data:' ) ) {
				return;
			}
			// Only an address WRITTEN as a full staging address counts (a path such as /wp-content/uploads/your-image.jpg is fine
			// anywhere), and never the media library's own address for a picture in the site details.
			$written = 'site' !== $role && 1 === preg_match( '#^https?://([^/?\#]+)#i', $src, $hm ) && TWD_SK_HouseRules::is_staging( $hm[1] );
			if ( '/' === substr( $src, 0, 1 ) && '/' !== substr( $src, 0, 2 ) && function_exists( 'home_url' ) ) {
				$src = rtrim( home_url( '/' ), '/' ) . $src;
			}
			if ( ! isset( $found[ $src ] ) ) {
				$found[ $src ] = array( 'src' => $src, 'alt' => null, 'roles' => array(), 'used_in' => array(), 'staging' => false );
			}
			if ( $written ) {
				$found[ $src ]['staging'] = true;
			}
			if ( null !== $alt && ( null === $found[ $src ]['alt'] || '' === $found[ $src ]['alt'] ) ) {
				$found[ $src ]['alt'] = html_entity_decode( (string) $alt, ENT_QUOTES, 'UTF-8' );
			}
			$found[ $src ]['roles'][ $role ]   = true;
			$found[ $src ]['used_in'][ $where ] = true;
		};
		$scan = function ( $html, $where, $role ) use ( $add ) {
			if ( preg_match_all( '/<img\b[^>]*>/i', (string) $html, $tags ) ) {
				foreach ( $tags[0] as $tag ) {
					if ( ! preg_match( '/\ssrc\s*=\s*"([^"]*)"/i', $tag, $s ) ) {
						continue;
					}
					$alt = preg_match( '/\salt\s*=\s*"([^"]*)"/i', $tag, $a ) ? $a[1] : null;
					$add( $s[1], $alt, $where, $role );
				}
			}
		};
		foreach ( $sections_by_page as $stem => $sections ) {
			foreach ( $sections as $i => $section ) {
				$scan( $section, $stem . ' section ' . ( $i + 1 ), 'page' );
			}
		}
		if ( '' !== $footer_html ) {
			$scan( $footer_html, 'the footer', 'footer' );
		}
		if ( $with_site ) {
			$p = TWD_SK_Profile::get();
			foreach ( array( 'logo_id' => 'the logo', 'logo_mark_id' => 'the small logo mark', 'social_image_id' => 'the default social picture' ) as $key => $label ) {
				$aid = isset( $p[ $key ] ) ? (int) $p[ $key ] : 0;
				$url = $aid > 0 && function_exists( 'wp_get_attachment_url' ) ? wp_get_attachment_url( $aid ) : '';
				if ( is_string( $url ) && '' !== $url ) {
					$alt = function_exists( 'get_post_meta' ) ? (string) get_post_meta( $aid, '_wp_attachment_image_alt', true ) : '';
					$add( $url, $alt, 'site details: ' . $label, 'site' );
				}
			}
		}
		$up   = self::uploads();
		$list = array();
		foreach ( $found as $src => $f ) {
			$entry = array(
				'file'     => basename( (string) parse_url( $src, PHP_URL_PATH ) ),
				'src'      => $src,
				'alt'      => $f['alt'],
				'roles'    => array_keys( $f['roles'] ),
				'used_in'  => array_keys( $f['used_in'] ),
				'in_media_library' => null,
				'file_found'       => null,
				'bytes'            => null,
				'pixels'           => null,
				'issues'           => array(),
			);
			$path = '';
			if ( $up && 0 === strpos( $src, $up[0] . '/' ) ) {
				$entry['in_media_library'] = true;
				$path = $up[1] . '/' . rawurldecode( substr( strtok( $src, '?' ), strlen( $up[0] ) + 1 ) );
			} elseif ( $up ) {
				$entry['in_media_library'] = false;
				$entry['issues'][] = 'This picture is not in this site\'s media library (it loads from another address), so it cannot be checked here.';
			}
			if ( '' !== $path ) {
				$entry['file_found'] = is_file( $path );
				if ( $entry['file_found'] ) {
					$entry['bytes'] = (int) filesize( $path );
					$dim = function_exists( 'getimagesize' ) && 1 !== preg_match( '/\.svg$/i', $path ) ? @getimagesize( $path ) : false;
					if ( is_array( $dim ) ) {
						$entry['pixels'] = $dim[0] . 'x' . $dim[1];
						if ( $dim[0] > 2400 ) {
							$entry['issues'][] = 'Wider than 2400 pixels: larger than any screen needs.';
						}
					}
					if ( $entry['bytes'] > 600 * 1000 ) {
						$entry['issues'][] = 'Over 600 KB: slow on a phone. A smaller file would do.';
					}
				} else {
					$entry['issues'][] = 'The file is not on the server, so the picture will not show.';
				}
			}
			if ( null === $entry['alt'] ) {
				$entry['issues'][] = 'No alt attribute at all: a screen reader cannot say what it shows.';
			} elseif ( '' === trim( $entry['alt'] ) && ! in_array( 'site', $entry['roles'], true ) ) {
				$entry['issues'][] = 'Empty description. Fine only if the picture is purely decoration.';
			}
			if ( ! empty( $f['staging'] ) ) {
				$entry['issues'][] = 'The page writes this picture\'s full address on a staging site. Change it to the path (the wording and address check can do that) before launch.';
			}
			$list[] = $entry;
		}
		$notes = array( 'A picture used in a page is listed once with every place it is used. in_media_library false means the picture loads from another web address.' );
		return array( 'list' => $list, 'notes' => $notes );
	}

	/**
	 * Small copies of the pictures for an AI that can look at images: at most 600 pixels wide, at most 40 pictures,
	 * nothing over 12 MB read. Returns file name => bytes.
	 */
	public static function picture_copies( $list ) {
		$up  = self::uploads();
		$out = array();
		if ( ! $up ) {
			return $out;
		}
		$total = 0;
		foreach ( $list as $entry ) {
			if ( count( $out ) >= 40 || $total > 9 * 1000 * 1000 ) {
				break;
			}
			if ( empty( $entry['file_found'] ) || 0 !== strpos( $entry['src'], $up[0] . '/' ) ) {
				continue;
			}
			$path = $up[1] . '/' . rawurldecode( substr( strtok( $entry['src'], '?' ), strlen( $up[0] ) + 1 ) );
			if ( ! is_file( $path ) || filesize( $path ) > 12 * 1000 * 1000 ) {
				continue;
			}
			$name  = 'pictures/' . preg_replace( '/[^A-Za-z0-9._-]/', '-', $entry['file'] );
			$base  = $name;
			$n     = 2;
			while ( isset( $out[ $name ] ) ) {
				$name = preg_replace( '/(\.[^.]*)?$/', '-' . $n++ . '$1', $base, 1 );
			}
			$bytes = '';
			if ( function_exists( 'wp_get_image_editor' ) && 1 !== preg_match( '/\.svg$/i', $path ) ) {
				$ed = wp_get_image_editor( $path );
				if ( ! is_wp_error( $ed ) ) {
					$ed->resize( 600, 600, false );
					$tmp = tempnam( sys_get_temp_dir(), 'twdp' );
					$ext = strtolower( pathinfo( $path, PATHINFO_EXTENSION ) );
					$saved = $tmp ? $ed->save( $tmp . '.' . $ext ) : null;
					if ( is_array( $saved ) && ! empty( $saved['path'] ) && is_file( $saved['path'] ) ) {
						$bytes = (string) file_get_contents( $saved['path'] );
						@unlink( $saved['path'] );
					}
					if ( $tmp ) {
						@unlink( $tmp );
					}
				}
			}
			if ( '' === $bytes && filesize( $path ) <= 300 * 1000 ) {
				$bytes = (string) file_get_contents( $path );
			}
			if ( '' !== $bytes ) {
				$out[ $name ] = $bytes;
				$total       += strlen( $bytes );
			}
		}
		return $out;
	}

	/** What the site's own launch check says, as plain markdown, so an AI need not find the same things again. */
	public static function checks_markdown() {
		$mark  = array( 'fail' => 'FIX', 'warn' => 'LOOK', 'ok' => 'OK' );
		$check = TWD_SK_Launch::check();
		$l     = array( '# What the site\'s own checks say', '', 'Made when the export was made. FIX means it blocks launch, LOOK is worth a look, OK needs nothing. Some items (the enquiry form email, whether search engines are allowed) are expected to say FIX or LOOK on a staging site.', '' );
		foreach ( $check['items'] as $it ) {
			$l[] = '- ' . ( isset( $mark[ $it['status'] ] ) ? $mark[ $it['status'] ] : $it['status'] ) . ': ' . $it['label'] . ( '' !== $it['detail'] ? ' (' . $it['detail'] . ')' : '' );
		}
		$l[] = '';
		return implode( "\n", $l );
	}

	/** The kit pages, for choosing one to export: id, the name a reply uses, title, status. */
	public static function page_list() {
		$out = array();
		foreach ( array_slice( TWD_SK_Launch::kit_pages(), 0, self::MAX_PAGES ) as $id ) {
			$post  = get_post( (int) $id );
			$out[] = array(
				'id'     => (int) $id,
				'page'   => self::stem( $id ),
				'title'  => trim( (string) get_the_title( (int) $id ) ),
				'status' => $post && isset( $post->post_status ) ? (string) $post->post_status : '',
			);
		}
		return $out;
	}

	/** The notes saved on the site, or ''. */
	public static function saved_notes() {
		return TWD_SK_ExportNotes::get();
	}

	/** Plain text for the owner's notes: no tags or control characters, line breaks kept, capped. */
	public static function clean_notes( $text ) {
		if ( ! is_string( $text ) ) {
			return '';
		}
		$text = str_replace( array( "\r\n", "\r" ), "\n", wp_strip_all_tags( $text ) );
		$text = preg_replace( '/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F]/', '', $text );
		$text = trim( $text );
		return strlen( $text ) > self::MAX_NOTES ? rtrim( substr( $text, 0, self::MAX_NOTES ) ) : $text;
	}

	/**
	 * The files to export.
	 *
	 * @param array $opts scope ('all', 'page', 'chrome'), page (an id, for 'page'), notes (text)
	 * @return array|WP_Error { files: name => content, pages: [ { id, stem, title, status, sections } ], scope }
	 */
	public static function files( $opts = array() ) {
		$scope = isset( $opts['scope'] ) ? (string) $opts['scope'] : 'all';
		if ( ! in_array( $scope, array( 'all', 'page', 'chrome' ), true ) ) {
			return new WP_Error( 'twd_sk_bad_input', 'Choose the whole site, one page, or the header and footer.' );
		}
		// Notes sent with the request win; with none sent (null), the notes kept on the site are used.
		$notes = array_key_exists( 'notes', $opts ) && null !== $opts['notes'] ? self::clean_notes( $opts['notes'] ) : self::saved_notes();

		$ids = array();
		if ( 'all' === $scope ) {
			$ids = array_slice( TWD_SK_Launch::kit_pages(), 0, self::MAX_PAGES );
		} elseif ( 'page' === $scope ) {
			$id = isset( $opts['page'] ) ? (int) $opts['page'] : 0;
			if ( $id <= 0 || ! TWD_SK_Page::is_kit_page( $id ) ) {
				return new WP_Error( 'twd_sk_not_a_page', 'That page was not found, or it is not a Site Kit page.' );
			}
			$ids = array( $id );
		}

		$files  = array();
		$pages  = array();
		$stems  = array();
		$kit_html = '';
		$types    = array();
		$example  = null;
		$sections_by_page = array();
		foreach ( $ids as $id ) {
			$html = TWD_SK_Store::get_current( (int) $id );
			if ( '' === trim( (string) $html ) ) {
				continue;
			}
			$stem = self::stem( $id );
			$base = $stem;
			$n    = 2;
			while ( isset( $stems[ $stem ] ) ) {
				$stem = $base . '-' . $n++;
			}
			$stems[ $stem ] = true;
			$post  = get_post( (int) $id );
			$parts = TWD_SK_Sections::split( $html );
			$body  = '';
			foreach ( $parts['sections'] as $i => $section ) {
				$label = TWD_SK_Sections::label_of( $section );
				$body .= '<!-- twd-section ' . ( $i + 1 ) . ': ' . TWD_SK_Sections::type_of( $section ) . ( '' !== $label ? ', ' . str_replace( '--', '- -', preg_replace( '/[\r\n]+/', ' ', $label ) ) : '' ) . " -->\n" . trim( $section ) . "\n\n";
			}
			$files[ 'pages/' . $stem . '.html' ] = $body;
			$kit_html .= $html;
			$sections_by_page[ $stem ] = $parts['sections'];
			foreach ( $parts['sections'] as $i => $section ) {
				$types[ str_replace( '-', '_', TWD_SK_Sections::type_of( $section ) ) ] = true;
				if ( null === $example ) {
					$example = self::example_change( $stem, $i + 1, $section );
				}
			}
			$live = $post && isset( $post->post_status ) && 'publish' === $post->post_status;
			$path = $live && function_exists( 'wp_parse_url' ) ? (string) wp_parse_url( get_permalink( (int) $id ), PHP_URL_PATH ) : '';
			$pages[] = array(
				'id'       => (int) $id,
				'page'     => $stem,
				'title'    => trim( (string) get_the_title( (int) $id ) ),
				'address'  => $path,
				'status'   => $post && isset( $post->post_status ) ? (string) $post->post_status : '',
				'seo_title'       => class_exists( 'TWD_SK_Seo' ) ? (string) TWD_SK_Seo::details( (int) $id )['title'] : '',
				'seo_description' => class_exists( 'TWD_SK_Seo' ) ? (string) TWD_SK_Seo::details( (int) $id )['description'] : '',
				'seo_social_picture' => self::seo_picture( (int) $id ),
				'seo_noindex'     => class_exists( 'TWD_SK_Seo' ) ? (bool) TWD_SK_Seo::values( (int) $id )['noindex'] : false,
				'file'     => 'pages/' . $stem . '.html',
				'sections' => count( $parts['sections'] ),
			);
		}
		if ( 'chrome' !== $scope ) {
			$files['pages/_index.json'] = self::json( array( 'pages' => $pages ) );
		}

		// The site-wide parts: the header and footer for the whole site or the header and footer scope.
		if ( 'page' !== $scope ) {
			$brief = TWD_SK_Brief::export();
			$files['site/header-and-footer.json'] = self::json( array(
				'site'   => $brief['site'],
				'header' => $brief['header'],
				'footer' => $brief['footer'],
			) );
			$footer = TWD_SK_Chrome::footer_embed();
			if ( $footer ) {
				$files['site/footer.html'] = trim( $footer['code'] ) . "\n";
			}
		}
		// Search settings for the whole site, the pictures used, and what the site's own checks say.
		if ( 'page' !== $scope ) {
			$files['site/seo.json'] = self::json( self::seo_summary() );
		}
		if ( 'chrome' !== $scope ) {
			$footer_code = ( 'page' !== $scope && isset( $files['site/footer.html'] ) ) ? $files['site/footer.html'] : '';
			$pics = self::pictures( $sections_by_page, $footer_code, 'page' !== $scope );
			$files['site/pictures.json'] = self::json( array( 'pictures' => $pics['list'], 'notes' => $pics['notes'] ) );
			if ( ! empty( $opts['pictures'] ) ) {
				foreach ( self::picture_copies( $pics['list'] ) as $name => $bytes ) {
					$files[ $name ] = $bytes;
				}
			}
		}
		$files['site/site-checks.md'] = self::checks_markdown();
		// Style and stylesheet go with every scope: a page cannot be judged without them.
		$tokens = TWD_SK_Packs::active_tokens();
		$files['site/style-tokens.json'] = self::json( array(
			'style'  => TWD_SK_Packs::active_slug(),
			'tokens' => TWD_SK_Export::token_groups( $tokens ),
		) );
		$kit_types = array_values( array_diff( array_keys( $types ), array( 'custom', 'section' ) ) );
		if ( $kit_types ) {
			$files['site/kit-sections-guide.md'] = "# The kit sections these pages use\n\nEvery kit section is a section element with the classes shown. When you change one, keep this structure and these classes. The words in the examples below are samples only: the real words are in the page files.\n\n" . TWD_SK_Prompt::style_guide( $kit_types ) . "\n";
		}
		if ( is_array( $example ) ) {
			$files['reply-example.json'] = self::json( array(
				'schema'       => self::REPLY_SCHEMA,
				'what_changed' => 'EXAMPLE ONLY, to show the format. This one changes nothing: the new words are the same as the old.',
				'changes'      => array( $example ),
			) );
		}
		if ( $kit_html !== '' ) {
			$kit = trim( TWD_SK_Export::kit_rules_for( $kit_html, TWD_SK_Export::kit_css() ) );
			$files['site/kit-stylesheet-used.css'] = "/* The plugin's own rules for the twd-sk- classes these pages use (every rule is scoped under .twd-sk-page). Colours, fonts and sizes are var(--twd-site-NAME): see style-tokens.json for the values. Read-only: the site stylesheet below is where a change to a custom section's look goes. */\n" . $kit . "\n";
		}
		$css = class_exists( 'TWD_SK_Css' ) ? trim( TWD_SK_Css::current()['source'] ) : '';
		$files['site/site-stylesheet.css'] = '' !== $css ? $css . "\n" : "/* The site has no stylesheet of its own yet. */\n";

		$facts = trim( (string) TWD_SK_Facts::get() );
		$files['notes/practice-facts.md'] = '' !== $facts ? $facts . "\n" : "(Nothing is saved on the site for this.)\n";
		$files['notes/your-notes.md']     = '' !== $notes ? $notes . "\n" : "(No notes were added with this export.)\n";

		$files = array_merge( array( 'README-FOR-AI.md' => self::readme( $scope, $pages, '' !== $notes ) ), $files );
		return array( 'files' => $files, 'pages' => $pages, 'scope' => $scope );
	}

	private static function json( $data ) {
		$flags = defined( 'JSON_PRETTY_PRINT' ) ? JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE : 0;
		return json_encode( $data, $flags ) . "\n";
	}

	/**
	 * What to download: a zip when ZipArchive works, otherwise one markdown file holding every file in turn.
	 *
	 * @return array|WP_Error { name, base64, bytes, kind, files, pages }
	 */
	public static function build( $opts = array() ) {
		$set = self::files( $opts );
		if ( is_wp_error( $set ) ) {
			return $set;
		}
		$stamp = gmdate( 'Y-m-d' );
		$tag   = 'all' === $set['scope'] ? 'site' : ( 'chrome' === $set['scope'] ? 'header-footer' : ( isset( $set['pages'][0] ) ? $set['pages'][0]['page'] : 'page' ) );
		$bytes = self::zip( $set['files'] );
		if ( '' !== $bytes ) {
			return array( 'name' => 'export-' . $tag . '-' . $stamp . '.zip', 'base64' => base64_encode( $bytes ), 'bytes' => strlen( $bytes ), 'kind' => 'zip', 'files' => array_keys( $set['files'] ), 'pages' => count( $set['pages'] ) );
		}
		$md = self::as_markdown( $set['files'] );
		return array( 'name' => 'export-' . $tag . '-' . $stamp . '.md', 'base64' => base64_encode( $md ), 'bytes' => strlen( $md ), 'kind' => 'markdown', 'files' => array_keys( $set['files'] ), 'pages' => count( $set['pages'] ) );
	}

	/** A zip of the files, or '' when this server cannot make one. */
	public static function zip( $files ) {
		if ( ! class_exists( 'ZipArchive' ) ) {
			return '';
		}
		$tmp = tempnam( sys_get_temp_dir(), 'twdsk' );
		if ( false === $tmp ) {
			return '';
		}
		$zip = new ZipArchive();
		if ( true !== $zip->open( $tmp, ZipArchive::OVERWRITE ) ) {
			@unlink( $tmp );
			return '';
		}
		foreach ( $files as $name => $content ) {
			$zip->addFromString( $name, $content );
		}
		$ok = $zip->close();
		$out = $ok ? (string) file_get_contents( $tmp ) : '';
		@unlink( $tmp );
		return $out;
	}

	/** Every file in one markdown document, each under a heading with its name. */
	public static function as_markdown( $files ) {
		$out = "# Site export\n\nOne file standing for a folder: each part below begins with a line `=== FILE: name ===`. Read README-FOR-AI.md first.\n\n";
		foreach ( $files as $name => $content ) {
			if ( 0 === strpos( $name, 'pictures/' ) ) {
				continue; // Pictures are binary: only in a zip.
			}
			$out .= '=== FILE: ' . $name . " ===\n" . $content . ( "\n" === substr( $content, -1 ) ? '' : "\n" ) . "\n";
		}
		return $out;
	}

	/** The README an AI reads first. */
	public static function readme( $scope, $pages, $has_notes ) {
		$name  = function_exists( 'get_bloginfo' ) ? trim( (string) get_bloginfo( 'name' ) ) : '';
		$l     = array();
		$l[]   = '# Read this first';
		$l[]   = '';
		$l[]   = 'This is an export of ' . ( '' !== $name ? $name : 'a website' ) . ' made on ' . gmdate( 'Y-m-d' ) . ' by TWD Site Kit (a WordPress plugin). You cannot see the live site, so these files stand for it. Your job is to read them, compare them with the notes, and reply with changes the site can apply exactly as you write them.';
		$l[]   = '';
		$l[]   = '## The files';
		$l[]   = '';
		$l[]   = '- `notes/your-notes.md`: ' . ( $has_notes ? 'what the owner wants checked or changed. Start here.' : 'empty on this export.' );
		$l[]   = '- `notes/practice-facts.md`: the facts about the person and practice. These are the ONLY facts you may use.';
		if ( 'chrome' === $scope ) {
			$l[] = '- This export holds no pages, only the header and footer.';
		} else {
			$l[] = '- `pages/_index.json`: every page with the name to use in a reply (`page`), its title, address, status, number of sections, and its search title and description (`seo_title`, `seo_description`, `seo_social_picture`, `seo_noindex`: check them here, they are not in the page HTML).';
			$l[] = '- `pages/NAME.html`: one page. A comment line `<!-- twd-section N: type, label -->` comes before each top-level section. N is the section number to use in a reply. Those comment lines are only for you: never put one in a reply.';
		}
		if ( 'page' !== $scope ) {
			$l[] = '- `site/header-and-footer.json`: the site details, menu, header button, footer words and legal links, and the header and footer settings.';
			$l[] = '- `site/footer.html`: the footer\'s own HTML, only when the footer is a saved embed.';
		}
		$l[] = '- `reply-example.json`: a worked example of a reply taken from this site\'s own words. It changes nothing. Copy its shape.';
		if ( 'chrome' !== $scope ) {
			$l[] = '- `site/kit-sections-guide.md`: every kit section these pages use, with its classes and example markup, so you reuse real markup instead of inventing classes.';
		}
		if ( 'page' !== $scope ) {
			$l[] = '- `site/seo.json`: search settings for the whole site: whether search engines are allowed, the default social share picture, the structured data printed on the front page.';
		}
		if ( 'chrome' !== $scope ) {
			$l[] = '- `site/pictures.json`: every picture used, where it is used, its description, size, whether the file is on the server, and any problem found. A folder `pictures/` (when present) holds small copies you can look at: match them by file name.';
		}
		$l[] = '- `site/site-checks.md`: what the site\'s own launch check found. Start from it rather than finding the same things again.';
		$l[] = '- `site/style-tokens.json`: the colours, fonts and sizes the design uses, with their values. A stylesheet refers to them as `var(--twd-site-NAME)`.';
		$l[] = '- `site/site-stylesheet.css`: the site\'s own rules, for sections that are custom HTML (classes starting `c-`).';
		$l[] = '- `site/kit-stylesheet-used.css`: the plugin\'s own rules for the kit classes (starting `twd-sk-`) these pages use, so you can read how a kit section looks. Do not write rules for those classes.';
		$l[] = '';
		$l[] = '## What you can and cannot judge';
		$l[] = '';
		$l[] = '- You are reading code, not looking at a rendered page. You can judge wording, structure, headings, links and where they go, labels, alt text, what a control does (tabs, accordions, buttons) and, from the stylesheets and tokens, colours, fonts, sizes, spacing and how a layout will behave on a phone. Say plainly when something can only be judged by looking at it.';
		$l[] = '- The header is not exported as HTML: `site/header-and-footer.json` gives its menu, button and layout settings.';
		$l[] = '- Pages marked `draft` in `pages/_index.json` are not live. Ignore them unless the notes ask about them.';
		$l[] = '- Many pages are ONE large custom section (class `twd-sk-custom`), so a section change on them replaces the whole page. For wording, prefer a find and replace, and keep every `c-` class and every `data-twd-edit` label as it is.';
		$l[] = '';
		$l[] = '## How to answer';
		$l[] = '';
		$l[] = 'Reply with ONE JSON object and nothing else (a fenced json block is fine). Plain sentences for a person go in `what_changed` and in each change\'s `why`.';
		$l[] = '';
		$l[] = '```json';
		$l[] = '{';
		$l[] = '  "schema": "' . self::REPLY_SCHEMA . '",';
		$l[] = '  "what_changed": "Two or three plain sentences.",';
		$l[] = '  "css": "optional: rules to add or change, or leave this out",';
		$l[] = '  "changes": [';
		$l[] = '    { "page": "about", "section": 3, "find": "the exact old words", "replace": "the new words", "why": "short reason" },';
		$l[] = '    { "page": "about", "section": 2, "html": "<section>...the whole new section...</section>", "why": "short reason" },';
		$l[] = '    { "page": "contact", "html": "<section>...every section of the page...</section>", "why": "only when the page is rebuilt" },';
		$l[] = '    { "footer_html": "<footer>...the whole new footer...</footer>", "why": "short reason" }';
		$l[] = '  ]';
		$l[] = '}';
		$l[] = '```';
		$l[] = '';
		$l[] = '- A **find and replace** change is for a wording fix. `find` must appear exactly once inside that section\'s HTML (copy it from the file, including any tags it sits among). Prefer this for small corrections: it cannot damage anything around it.';
		$l[] = '- A **section** change replaces section N with the HTML you give (one or more complete `<section>` elements).';
		$l[] = '- A **page** change (no `section`) replaces the whole page. Use it rarely.';
		$l[] = '- A **footer_html** change replaces the footer, only if `site/footer.html` exists.';
		$l[] = '- `page` is the name from `pages/_index.json`. Section numbers refer to the files as exported. Several changes to one page are fine: they are all applied to the page as exported.';
		$l[] = '- Do not change the site details, menu or settings with this format: write those as a plain list for a person to apply.';
		$l[] = '';
		$l[] = '## Never touch these';
		$l[] = '';
		$l[] = '- Every `c-` class and every `data-twd-edit` and `data-twd-pic` label: they are how the site styles and edits the text.';
		$l[] = '- Shortcodes such as `[twd_enquiry]`, `[twd_articles]` and `[twd_embed id="N"]`.';
		$l[] = '- The safety notice section, and any section the notes say to leave alone.';
		$l[] = '- Link addresses and picture addresses, unless the notes ask for that exact change.';
		$l[] = '- The one `<h1>` on each page (its words may change if the notes say so, but there must be exactly one).';
		$l[] = '- Anything the notes list under LEAVE ALONE.';
		$l[] = '';
		$l[] = '## Before you reply, check';
		$l[] = '';
		$l[] = '- Every `find` is copied exactly from the file, and appears once in that section.';
		$l[] = '- Every section number is the one in the comment line of the file you were given.';
		$l[] = '- No new fact: everything comes from `notes/practice-facts.md` or `notes/your-notes.md`.';
		$l[] = '- The words and spelling rules in the notes are followed in every change you write.';
		$l[] = '- No long dashes, no scripts, no style attributes.';
		$l[] = '- The reply is ONE JSON object with the `schema` line, nothing around it.';
		$l[] = '- Put the most important changes first. Send at most about 25 changes in one reply; say in `what_changed` what is left for a next reply.';
		$l[] = '- If a point in the notes cannot be judged from code (a photo, how something looks), list it in `what_changed` as "needs a person to look" and leave it out of `changes`.';
		$l[] = '';
		$l[] = '## Rules every change must follow';
		$l[] = '';
		foreach ( TWD_SK_Export::rules( 'c-page-' ) as $rule ) {
			$l[] = '- ' . $rule;
		}
		$l[] = '- When you change a kit section (its classes start with twd-sk-), keep its structure and classes and change only the words, links and picture descriptions, unless the notes ask for a new layout.';
		$l[] = '- Never use the practice\'s facts for anything but what they say. If the notes ask for something the facts do not support, put `[PLACEHOLDER: what is needed]` in the text and say so in `why`.';
		$l[] = '- The site shows what you write after a person has previewed it and agreed, so be exact. If you are unsure, say so in `what_changed` and leave the change out.';
		$l[] = '';
		$l[] = 'These files hold a client\'s words and contact details. Keep them private.';
		return implode( "\n", $l ) . "\n";
	}
}
