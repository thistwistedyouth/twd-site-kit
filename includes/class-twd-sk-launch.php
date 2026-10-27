<?php
/**
 * TWD_SK_Launch: the launch check (0.9.0), and publishing the pages that are ready.
 *
 * check() reads the whole site and changes nothing: every kit page (example text still to replace,
 * missing descriptions, heading order, link wording, search wording), the front page, the privacy
 * policy, the site details, whether the contact details appear on a page, whether search engines
 * may index the site, https, the search details, the header and footer, the enquiry form's
 * address, and the support lines to check by hand. Each item is ok, warn (worth a look) or fail
 * (fix before launch).
 *
 * publish() publishes only pages with nothing marked "must fix", through the same rule as the
 * Pages tab (TWD_SK_Template::set_status). Nothing is forced.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class TWD_SK_Launch {

	const MAX_PAGES = 300;

	/** IDs of every page that uses the kit, any status. */
	public static function kit_pages() {
		$out = array();
		foreach ( (array) get_posts( array( 'post_type' => 'page', 'post_status' => 'any', 'numberposts' => self::MAX_PAGES, 'fields' => 'ids', 'orderby' => 'menu_order', 'order' => 'ASC' ) ) as $id ) {
			$post = get_post( (int) $id );
			if ( $post && 'trash' !== $post->post_status && TWD_SK_Page::is_kit_page( (int) $id ) ) {
				$out[] = (int) $id;
			}
		}
		return $out;
	}

	/** One page's state for the launch check. */
	public static function page_report( $id ) {
		$id      = (int) $id;
		$post    = get_post( $id );
		$html    = TWD_SK_Store::get_current( $id );
		$levels  = TWD_SK_Store::get_leftover_levels( $id );
		$quality = array();
		foreach ( TWD_SK_Quality::check( $html ) as $q ) {
			$quality[] = $q['label'];
		}
		$seo     = TWD_SK_Seo::values( $id );
		$missing = array();
		if ( '' === trim( (string) $seo['title'] ) ) {
			$missing[] = 'search title';
		}
		if ( '' === trim( (string) $seo['description'] ) ) {
			$missing[] = 'search description';
		}
		$must  = TWD_SK_Report::leftover_total( $levels['must'] );
		$empty = '' === $html;
		return array(
			'id'       => $id,
			'title'    => $post ? (string) $post->post_title : '',
			'status'   => $post ? (string) $post->post_status : '',
			'url'      => (string) get_permalink( $id ),
			'must'     => $must,
			'check'    => TWD_SK_Report::leftover_total( $levels['check'] ),
			'leftovers' => TWD_SK_Report::describe_leftovers( $levels['must'] ),
			'quality'  => $quality,
			'seo'      => $missing,
			'ready'    => ! $empty && 0 === $must,
			'reason'   => $empty ? 'The page is empty.' : ( $must ? $must . ' thing' . ( 1 === $must ? '' : 's' ) . ' still to replace (example text or [PLACEHOLDER]).' : '' ),
		);
	}

	private static function item( $id, $status, $label, $detail = '' ) {
		return array( 'id' => $id, 'status' => $status, 'label' => $label, 'detail' => $detail );
	}

	private static function real( $v ) {
		return is_string( $v ) && '' !== trim( $v ) && false === stripos( $v, '[PLACEHOLDER' ) && false === stripos( $v, 'example.com' ) && false === strpos( $v, 'PHONE_NUMBER' );
	}

	/**
	 * The whole check. Read only.
	 *
	 * @return array { items[], pages[], ready (no fail items), counts { fail, warn, ok } }
	 */
	public static function check() {
		$items = array();
		$ids   = self::kit_pages();
		$pages = array_map( array( __CLASS__, 'page_report' ), $ids );
		$all   = '';
		foreach ( $ids as $id ) {
			$all .= "\n" . TWD_SK_Store::get_current( $id );
		}

		// Safe mode.
		$items[] = TWD_SK_Safe::on() ? self::item( 'safe', 'fail', 'Safe mode is on', 'The newest features are switched off. Turn safe mode off when the problem it was turned on for is fixed.' ) : self::item( 'safe', 'ok', 'Safe mode is off' );

		// Pages.
		if ( ! $pages ) {
			$items[] = self::item( 'pages', 'fail', 'No pages use the kit yet', 'Build the site from Settings, Set up site, or make a page in the Pages tab.' );
		} else {
			$not = array_filter( $pages, function ( $p ) {
				return ! $p['ready'];
			} );
			$items[] = $not ? self::item( 'pages', 'fail', count( $not ) . ' of ' . count( $pages ) . ' pages still have things to replace', implode( '; ', array_map( function ( $p ) {
				return '"' . $p['title'] . '": ' . $p['reason'];
			}, $not ) ) ) : self::item( 'pages', 'ok', 'All ' . count( $pages ) . ' pages are free of example text' );
			$q = array_filter( $pages, function ( $p ) {
				return (bool) $p['quality'];
			} );
			$items[] = $q ? self::item( 'quality', 'warn', count( $q ) . ' page' . ( 1 === count( $q ) ? ' has' : 's have' ) . ' pictures, headings or links to look at', implode( '; ', array_map( function ( $p ) {
				return '"' . $p['title'] . '": ' . implode( ' ', $p['quality'] );
			}, $q ) ) ) : self::item( 'quality', 'ok', 'Pictures, headings and links look right' );
			$s = array_filter( $pages, function ( $p ) {
				return (bool) $p['seo'];
			} );
			$items[] = $s ? self::item( 'search', 'warn', count( $s ) . ' page' . ( 1 === count( $s ) ? ' has' : 's have' ) . ' no search wording', 'Add a search title and description in the Search tab: ' . implode( ', ', array_map( function ( $p ) {
				return '"' . $p['title'] . '"';
			}, $s ) ) . '.' ) : self::item( 'search', 'ok', 'Every page has a search title and description' );
		}

		// Front page.
		$front = 'page' === get_option( 'show_on_front' ) ? (int) get_option( 'page_on_front' ) : 0;
		if ( ! $front || ! in_array( $front, $ids, true ) ) {
			$items[] = self::item( 'front', 'fail', 'The front page is not a kit page', 'Set the Home page as the front page (Settings, Reading), or build the site from the setup page.' );
		} else {
			$fp      = get_post( $front );
			$items[] = $fp && 'publish' === $fp->post_status ? self::item( 'front', 'ok', 'The front page is set and published' ) : self::item( 'front', 'warn', 'The front page is set but still a draft', 'Publish it below once it is ready. Until then visitors see nothing there.' );
		}

		// Privacy policy.
		$privacy = function_exists( 'get_privacy_policy_url' ) ? (string) get_privacy_policy_url() : '';
		$items[] = '' === $privacy ? self::item( 'privacy', 'fail', 'There is no published privacy policy', 'Write one and set it in Settings, Privacy. It is needed by law, and the footer links to it.' ) : self::item( 'privacy', 'ok', 'A privacy policy is published' );

		// Site details.
		$p      = TWD_SK_Profile::get();
		$left   = TWD_SK_Profile::leftovers( $p );
		$must   = TWD_SK_Report::leftover_total( $left['must'] );
		$reach  = self::real( $p['phone'] ) || self::real( $p['email'] );
		if ( $must ) {
			$items[] = self::item( 'details', 'fail', 'The site details still have ' . $must . ' thing' . ( 1 === $must ? '' : 's' ) . ' to replace', 'Open the Site tab, Site details.' );
		} elseif ( ! $reach || ! self::real( $p['site_name'] ) ) {
			$items[] = self::item( 'details', 'fail', 'The site details need a name and a phone number or email address', 'Open the Site tab, Site details.' );
		} else {
			$items[] = self::item( 'details', 'ok', 'The site details are filled in' );
		}

		// Contact details shown on a page.
		if ( $reach && $pages ) {
			$digits = preg_replace( '/\D/', '', $all );
			$shown  = ( self::real( $p['email'] ) && false !== stripos( $all, trim( $p['email'] ) ) ) || ( self::real( $p['phone'] ) && strlen( preg_replace( '/\D/', '', $p['phone'] ) ) >= 6 && false !== strpos( $digits, preg_replace( '/\D/', '', $p['phone'] ) ) );
			$items[] = $shown ? self::item( 'contact', 'ok', 'The contact details appear on a page' ) : self::item( 'contact', 'warn', 'The phone number and email in the site details do not appear on any page', 'They show in the header and footer; check the Contact page says the same.' );
		}

		// The enquiry form needs somewhere to send to.
		if ( false !== strpos( $all, '[twd_enquiry]' ) ) {
			$items[] = '' === TWD_SK_Enquiry::recipient() ? self::item( 'enquiry', 'fail', 'The enquiry form has no valid email address to send to', 'Add the email address in the Site tab, Site details.' ) : self::item( 'enquiry', 'warn', 'Send yourself a test message through the enquiry form', 'Some hosts block email from websites. If it does not arrive (check the spam folder), set up an SMTP plugin.' );
		}

		// The support lines are public information but must be checked by a person before launch.
		if ( false !== strpos( $all, 'twd-sk-notice' ) ) {
			$items[] = self::item( 'notice', 'warn', 'Check the support line numbers and times by hand', 'The safety notice lists national support lines. Check each one against its official website before launch.' );
		}

		// Search engines, https, search details.
		$items[] = '0' === (string) get_option( 'blog_public', '1' ) ? self::item( 'visible', 'fail', 'Search engines are asked not to index this site', 'Untick "Discourage search engines from indexing this site" in Settings, Reading when you launch.' ) : self::item( 'visible', 'ok', 'Search engines may index the site' );
		$home    = function_exists( 'home_url' ) ? (string) home_url( '/' ) : '';
		$items[] = 0 === strpos( $home, 'https://' ) ? self::item( 'https', 'ok', 'The site uses https' ) : self::item( 'https', 'warn', 'The site address does not start with https', 'Ask the host to turn on SSL, then change the site address in Settings, General.' );
		$items[] = TWD_SK_Schema::nodes() ? self::item( 'schema', 'ok', 'Search engines get the ' . TWD_SK_Mode::w( 'practice' ) . ' details' ) : self::item( 'schema', 'warn', 'Search engines get no ' . TWD_SK_Mode::w( 'practice' ) . ' details', 'Fill in the site name in Site details.' );

		// The header and footer: Site Kit places them on the Hello theme (0.9.1), or Elementor Pro templates do.
		if ( 'kit' === TWD_SK_Chrome::settings()['placement'] ) {
			$items[] = TWD_SK_Chrome::hello_active() ? self::item( 'chrome', 'ok', 'Site Kit places the header and footer (Hello theme)' ) : self::item( 'chrome', 'warn', 'The kit header and footer are not showing', 'Site Kit places them only on the Hello theme. Switch to Hello in Appearance, Themes, or set Who places them to Elementor in the Site tab, Header and footer.' );
		} elseif ( defined( 'ELEMENTOR_VERSION' ) ) {
			$found = array( 'header' => false, 'footer' => false );
			foreach ( (array) get_posts( array( 'post_type' => 'elementor_library', 'post_status' => 'any', 'numberposts' => 100, 'fields' => 'ids' ) ) as $tid ) {
				$type = (string) get_post_meta( (int) $tid, '_elementor_template_type', true );
				$data = (string) get_post_meta( (int) $tid, '_elementor_data', true );
				if ( isset( $found[ $type ] ) && false !== stripos( $data, 'twd_' . $type ) ) {
					$found[ $type ] = true;
				}
			}
			$items[] = $found['header'] && $found['footer'] ? self::item( 'chrome', 'ok', 'The header and footer templates are in place' ) : self::item( 'chrome', 'warn', 'The kit header or footer template is missing', 'Import them from the Site tab, Header and footer, and set their display condition to the entire site. Or let Site Kit place them on the Hello theme.' );
		}

		// Every menu link reaches a page (0.14.0).
		$menu = self::menu_links( $p['menu'] );
		if ( $menu['checked'] ) {
			if ( $menu['missing'] ) {
				$items[] = self::item( 'menu', 'fail', count( $menu['missing'] ) . ' menu link' . ( 1 === count( $menu['missing'] ) ? ' goes' : 's go' ) . ' nowhere', 'No page answers to: ' . implode( ', ', $menu['missing'] ) . '. Make the page, or take the link out of the menu in the Site tab.' );
			} elseif ( $menu['drafts'] ) {
				$items[] = self::item( 'menu', 'warn', count( $menu['drafts'] ) . ' menu link' . ( 1 === count( $menu['drafts'] ) ? ' points' : 's point' ) . ' at a draft', 'Fine once published: ' . implode( ', ', $menu['drafts'] ) . '.' );
			} else {
				$items[] = self::item( 'menu', 'ok', 'Every menu link reaches a page' );
			}
		}

		// WordPress's own leftovers (0.14.0): the sample page, the first post and the suggested privacy text.
		$wp = self::wp_leftovers();
		if ( $wp['sample'] || $wp['hello'] ) {
			$what = array();
			if ( $wp['sample'] ) {
				$what[] = 'the "Sample Page"';
			}
			if ( $wp['hello'] ) {
				$what[] = 'the "Hello world!" post';
			}
			$items[] = self::item( 'wp_leftovers', 'fail', 'WordPress\'s own ' . implode( ' and ', $what ) . ( 1 === count( $what ) ? ' is' : ' are' ) . ' still live', 'Move them to the bin (the Tidy up button here, or wp twd-sk launch-check --tidy).' );
		} else {
			$items[] = self::item( 'wp_leftovers', 'ok', 'No sample page or first post left over' );
		}
		if ( $wp['privacy_default'] ) {
			$items[] = self::item( 'privacy_text', 'fail', 'The privacy policy is still WordPress\'s suggested text', 'Replace it with the site\'s own policy before launch (Settings, Privacy, then edit the page).' );
		}

		$counts = array( 'fail' => 0, 'warn' => 0, 'ok' => 0 );
		foreach ( $items as $i ) {
			$counts[ $i['status'] ]++;
		}
		return array( 'items' => $items, 'pages' => $pages, 'ready' => 0 === $counts['fail'], 'counts' => $counts );
	}

	/**
	 * Check every menu link (0.14.0): a link to a page on this site must reach a page; a draft is fine once
	 * published; an address elsewhere is asked for with one HEAD request when the site can make one.
	 *
	 * @return array { checked: int, missing: [label (url)], drafts: [label], external_bad: [label] }
	 */
	public static function menu_links( $menu ) {
		$out  = array( 'checked' => 0, 'missing' => array(), 'drafts' => array(), 'external_bad' => array() );
		$home = function_exists( 'home_url' ) ? (string) home_url( '/' ) : '';
		$flat = array();
		foreach ( (array) $menu as $item ) {
			$flat[] = $item;
			foreach ( isset( $item['children'] ) && is_array( $item['children'] ) ? $item['children'] : array() as $child ) {
				$flat[] = $child;
			}
		}
		foreach ( $flat as $item ) {
			$url   = isset( $item['url'] ) ? (string) $item['url'] : '';
			$label = isset( $item['label'] ) ? (string) $item['label'] : $url;
			if ( '' === $url || '#' === $url[0] || 0 === strpos( $url, 'mailto:' ) || 0 === strpos( $url, 'tel:' ) ) {
				continue;
			}
			$path = $url;
			if ( '' !== $home && 0 === strpos( $url, $home ) ) {
				$path = '/' . ltrim( substr( $url, strlen( $home ) ), '/' );
			}
			if ( '/' === $path || 0 === strpos( $path, '/#' ) ) {
				continue; // The home page.
			}
			$out['checked']++;
			if ( preg_match( '#^https?://#i', $path ) ) {
				if ( function_exists( 'wp_safe_remote_head' ) ) {
					$r    = wp_safe_remote_head( $path, array( 'timeout' => 5, 'redirection' => 3 ) );
					$code = is_wp_error( $r ) ? 0 : (int) wp_remote_retrieve_response_code( $r );
					if ( $code < 200 || $code >= 400 ) {
						$out['external_bad'][] = $label . ' (' . $path . ')';
						$out['missing'][]      = $label . ' (' . $path . ')';
					}
				}
				continue;
			}
			$slug = trim( (string) parse_url( $path, PHP_URL_PATH ), '/' );
			if ( '' === $slug ) {
				continue; // The home page.
			}
			$post = self::page_by_slug( $slug );
			if ( ! $post ) {
				$out['missing'][] = $label . ' (' . $path . ')';
			} elseif ( 'publish' !== $post->post_status ) {
				$out['drafts'][] = $label;
			}
		}
		return $out;
	}

	/** A page or post by its slug (the last part of a path), any status but the bin, or null. */
	private static function page_by_slug( $slug ) {
		$last = basename( $slug );
		foreach ( array( 'page', 'post' ) as $type ) {
			foreach ( (array) get_posts( array( 'post_type' => $type, 'post_status' => 'any', 'numberposts' => self::MAX_PAGES, 'fields' => 'ids' ) ) as $id ) {
				$post = get_post( (int) $id );
				if ( $post && 'trash' !== $post->post_status && isset( $post->post_name ) && ( $post->post_name === $slug || $post->post_name === $last ) ) {
					return $post;
				}
			}
		}
		return null;
	}

	/** WordPress's own leftovers (0.14.0): the live sample page, the live first post, the suggested privacy text. */
	public static function wp_leftovers() {
		$out = array( 'sample' => 0, 'hello' => 0, 'privacy_default' => false );
		foreach ( (array) get_posts( array( 'post_type' => 'page', 'post_status' => 'any', 'numberposts' => self::MAX_PAGES, 'fields' => 'ids' ) ) as $id ) {
			$post = get_post( (int) $id );
			if ( $post && 'publish' === $post->post_status && isset( $post->post_name ) && 'sample-page' === $post->post_name && ! TWD_SK_Page::is_kit_page( (int) $id ) ) {
				$out['sample'] = (int) $id;
			}
		}
		foreach ( (array) get_posts( array( 'post_type' => 'post', 'post_status' => 'any', 'numberposts' => self::MAX_PAGES, 'fields' => 'ids' ) ) as $id ) {
			$post = get_post( (int) $id );
			if ( $post && 'publish' === $post->post_status && isset( $post->post_name ) && 'hello-world' === $post->post_name ) {
				$out['hello'] = (int) $id;
			}
		}
		$privacy_id = (int) get_option( 'wp_page_for_privacy_policy', 0 );
		if ( $privacy_id > 0 ) {
			$content = (string) get_post_field( 'post_content', $privacy_id );
			if ( false !== stripos( $content, 'Suggested text' ) || false !== stripos( $content, 'suggested-text' ) ) {
				$out['privacy_default'] = true;
			}
		}
		return $out;
	}

	/**
	 * Move the sample page and the first post to the bin (never a permanent delete). Needs delete rights.
	 *
	 * @return array { trashed: [title], refused: [reason] }
	 */
	public static function tidy() {
		$out = array( 'trashed' => array(), 'refused' => array() );
		$wp  = self::wp_leftovers();
		foreach ( array( 'sample' => 'delete_pages', 'hello' => 'delete_posts' ) as $key => $cap ) {
			$id = (int) $wp[ $key ];
			if ( ! $id ) {
				continue;
			}
			$title = (string) get_post( $id )->post_title;
			if ( ! current_user_can( $cap ) || ! current_user_can( 'delete_post', $id ) ) {
				$out['refused'][] = 'You cannot move "' . $title . '" to the bin.';
				continue;
			}
			if ( function_exists( 'wp_trash_post' ) && wp_trash_post( $id ) ) {
				$out['trashed'][] = $title;
			} else {
				$out['refused'][] = '"' . $title . '" could not be moved to the bin.';
			}
		}
		return $out;
	}

	/**
	 * Publish the chosen pages that are ready. A page with anything marked "must fix" is refused.
	 *
	 * @param int[] $ids
	 * @return array { published[], refused[] } each { id, title, reason? }
	 */
	public static function publish( $ids ) {
		$out = array( 'published' => array(), 'refused' => array() );
		foreach ( array_unique( array_map( 'intval', (array) $ids ) ) as $id ) {
			$post  = get_post( $id );
			$title = $post ? (string) $post->post_title : 'Page ' . $id;
			if ( ! current_user_can( 'publish_pages' ) || ! current_user_can( 'publish_post', $id ) ) {
				$out['refused'][] = array( 'id' => $id, 'title' => $title, 'reason' => 'You cannot publish this page.' );
				continue;
			}
			$r = TWD_SK_Template::set_status( $id, 'publish', false );
			if ( is_wp_error( $r ) ) {
				$out['refused'][] = array( 'id' => $id, 'title' => $title, 'reason' => $r->get_error_message() );
				continue;
			}
			$out['published'][] = array( 'id' => $id, 'title' => $title, 'url' => (string) get_permalink( $id ) );
		}
		return $out;
	}

	/** Plain-text lines for the command line and the site report. */
	public static function lines( $check = null ) {
		$check = null === $check ? self::check() : $check;
		$names = array( 'fail' => 'FIX  ', 'warn' => 'LOOK ', 'ok' => 'OK   ' );
		$out   = array( 'Launch check: ' . $check['counts']['fail'] . ' to fix, ' . $check['counts']['warn'] . ' to look at, ' . $check['counts']['ok'] . ' fine.' );
		foreach ( $check['items'] as $i ) {
			$out[] = $names[ $i['status'] ] . $i['label'] . ( '' !== $i['detail'] && 'ok' !== $i['status'] ? ' (' . $i['detail'] . ')' : '' );
		}
		return $out;
	}
}
