<?php
/**
 * TWD_SK_Template: the "TWD Kit Page" page template, new draft pages, publishing,
 * and switching a page to the kit template.
 *
 * A kit page needs no Elementor setup: the template calls get_header() and get_footer()
 * (so the theme's header and footer, including an Elementor Theme Builder header and
 * footer, work as normal) and prints the kit page in between. The only record of "this
 * page uses the template" is core's own page template meta.
 *
 * None of this writes page HTML. That only ever goes through TWD_SK_Store.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class TWD_SK_Template {

	const SLUG       = 'twd-site-kit-page.php';
	const LABEL      = 'TWD Kit Page';
	const META_KEY   = '_wp_page_template';
	const MAX_TITLE  = 120;

	public static function init() {
		add_filter( 'theme_page_templates', array( __CLASS__, 'register_template' ), 10, 4 );
		// Late, so a theme builder template (Elementor Pro's single page) cannot take over a kit page.
		add_filter( 'template_include', array( __CLASS__, 'maybe_use_template' ), 99 );
	}

	/** Add the template to the page template dropdown (pages only). */
	public static function register_template( $templates, $theme = null, $post = null, $post_type = null ) {
		if ( null === $post_type || 'page' === $post_type ) {
			$templates[ self::SLUG ] = self::LABEL;
		}
		return $templates;
	}

	/** Serve our file for a single page that chose the template. */
	public static function maybe_use_template( $template ) {
		if ( ! function_exists( 'is_singular' ) || ! is_singular( 'page' ) ) {
			return $template;
		}
		$post_id = function_exists( 'get_queried_object_id' ) ? (int) get_queried_object_id() : 0;
		if ( $post_id > 0 && self::uses_template( $post_id ) ) {
			return TWD_SK_PATH . 'templates/kit-page.php';
		}
		return $template;
	}

	public static function uses_template( $post_id ) {
		return self::SLUG === get_post_meta( (int) $post_id, self::META_KEY, true );
	}

	// -- New pages --------------------------------------------------------

	/** Starting layouts. Only a blank page for now; the starters arrive with their own release. */
	public static function starters() {
		return array( 'blank' => 'Blank page' );
	}

	/**
	 * Create a DRAFT page set up for the kit. Never published, never given content.
	 *
	 * @return int|WP_Error The new page ID.
	 */
	public static function create_page( $title, $starter ) {
		$title = trim( preg_replace( '/\s+/', ' ', wp_strip_all_tags( (string) $title ) ) );
		if ( '' === $title ) {
			return new WP_Error( 'twd_sk_bad_input', 'Give the page a title.' );
		}
		if ( function_exists( 'mb_strlen' ) ? mb_strlen( $title ) > self::MAX_TITLE : strlen( $title ) > self::MAX_TITLE ) {
			return new WP_Error( 'twd_sk_bad_input', 'That title is too long. Keep it under ' . self::MAX_TITLE . ' characters.' );
		}
		$title = TWD_SK_Sanitizer::strip_dashes( $title );
		if ( ! isset( self::starters()[ $starter ] ) ) {
			return new WP_Error( 'twd_sk_bad_input', 'Choose one of the starting layouts on offer.' );
		}

		$id = wp_insert_post( array(
			'post_type'     => 'page',
			'post_status'   => 'draft',
			'post_title'    => $title,
			'post_content'  => '',
			'post_author'   => (int) get_current_user_id(),
			'page_template' => self::SLUG,
		), true );
		if ( is_wp_error( $id ) ) {
			return $id;
		}
		// Belt and braces: some setups drop the template argument.
		if ( ! self::uses_template( $id ) ) {
			update_post_meta( $id, self::META_KEY, self::SLUG );
		}
		return (int) $id;
	}

	// -- Switching a page to the kit template -----------------------------

	/**
	 * Widget types on a page's Elementor layout other than the shortcode widget that holds
	 * [twd_page] (a form, a map, a button). The kit template shows only the kit page, so
	 * those would stop showing.
	 *
	 * @return string[]
	 */
	public static function other_elementor_content( $post_id ) {
		$raw = get_post_meta( (int) $post_id, '_elementor_data', true );
		if ( ! is_string( $raw ) || '' === $raw ) {
			return array();
		}
		$data = json_decode( $raw, true );
		if ( ! is_array( $data ) ) {
			return array();
		}
		$found = array();
		$walk  = function ( $elements ) use ( &$walk, &$found ) {
			foreach ( $elements as $el ) {
				if ( ! is_array( $el ) ) {
					continue;
				}
				if ( isset( $el['elType'] ) && 'widget' === $el['elType'] && isset( $el['widgetType'] ) ) {
					$type = (string) $el['widgetType'];
					if ( 'shortcode' !== $type && 'spacer' !== $type ) {
						$found[ $type ] = true;
					}
				}
				if ( ! empty( $el['elements'] ) && is_array( $el['elements'] ) ) {
					$walk( $el['elements'] );
				}
			}
		};
		$walk( $data );
		return array_slice( array_keys( $found ), 0, 8 );
	}

	/**
	 * Switch a kit page to or from the kit template.
	 *
	 * @param bool $use_kit             True for the kit template, false to go back to the theme's.
	 * @param bool $confirm_other       The caller accepts that other Elementor content stops showing.
	 * @return array|WP_Error array( uses_template, other_content )
	 */
	public static function switch_template( $post_id, $use_kit, $confirm_other ) {
		$post_id = (int) $post_id;
		if ( ! TWD_SK_Page::is_kit_page( $post_id ) ) {
			return new WP_Error( 'twd_sk_not_kit_page', 'This page does not use the kit yet, so there is nothing to switch.' );
		}
		$other = self::other_elementor_content( $post_id );
		if ( $use_kit ) {
			if ( $other && ! $confirm_other ) {
				return new WP_Error( 'twd_sk_other_content', 'This page also has other Elementor content (' . implode( ', ', $other ) . ') that would stop showing.', array( 'other_content' => $other ) );
			}
			update_post_meta( $post_id, self::META_KEY, self::SLUG );
			// The page's text now goes into its content too, so search and SEO tools can find it.
			if ( ! TWD_SK_Safe::on() && class_exists( 'TWD_SK_Mirror' ) ) {
				TWD_SK_Mirror::sync( $post_id );
			}
		} elseif ( self::uses_template( $post_id ) ) {
			update_post_meta( $post_id, self::META_KEY, 'default' );
		}
		return array( 'uses_template' => self::uses_template( $post_id ), 'other_content' => $other );
	}

	// -- Publishing -------------------------------------------------------

	/** True for the static front page or the posts page, which must stay published. */
	public static function is_site_page( $post_id ) {
		$post_id = (int) $post_id;
		return $post_id === (int) get_option( 'page_on_front' ) || $post_id === (int) get_option( 'page_for_posts' );
	}

	/**
	 * Publish or unpublish a kit page.
	 *
	 * Publishing is refused for an empty page and, unless overridden, while example text
	 * at the "must fix" level remains. Unpublishing sends the page back to draft.
	 *
	 * @return array|WP_Error array( status, levels )
	 */
	public static function set_status( $post_id, $status, $override_must_fix ) {
		$post_id = (int) $post_id;
		$post    = get_post( $post_id );
		if ( ! $post || 'page' !== $post->post_type ) {
			return new WP_Error( 'twd_sk_not_a_page', 'That page was not found.' );
		}
		if ( ! TWD_SK_Page::is_kit_page( $post_id ) ) {
			return new WP_Error( 'twd_sk_not_kit_page', 'Only pages that use the kit can be published from here.' );
		}
		$levels = TWD_SK_Store::get_leftover_levels( $post_id );

		if ( 'publish' === $status ) {
			if ( '' === TWD_SK_Store::get_current( $post_id ) ) {
				return new WP_Error( 'twd_sk_empty_page', 'This page is empty. Add content with Edit with AI before publishing.' );
			}
			if ( $levels['must'] && ! $override_must_fix ) {
				return new WP_Error( 'twd_sk_leftovers', 'This page still has example text that must be replaced before it is published.', array( 'levels' => $levels ) );
			}
		} elseif ( 'draft' === $status ) {
			if ( self::is_site_page( $post_id ) ) {
				return new WP_Error( 'twd_sk_site_page', 'This is your front page or posts page, so it cannot be unpublished from here.' );
			}
		} else {
			return new WP_Error( 'twd_sk_bad_input', 'The status must be publish or draft.' );
		}

		if ( $post->post_status !== $status ) {
			$result = wp_update_post( array( 'ID' => $post_id, 'post_status' => $status ), true );
			if ( is_wp_error( $result ) ) {
				return $result;
			}
		}
		return array( 'status' => $status, 'levels' => $levels );
	}
}
