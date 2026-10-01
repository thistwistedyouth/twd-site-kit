<?php
/**
 * TWD_SK_Seo: the search engine details of a kit page: title, description, social picture,
 * keep-out-of-search, and the page's address (slug).
 *
 * If Yoast SEO is active these are written to Yoast's own fields, so Yoast prints them. If no
 * SEO plugin is active the plugin keeps them in its own fields and prints the matching tags
 * itself. If another SEO plugin is active the plugin does not touch the title, description,
 * picture or keep-out setting at all (the person edits those in that plugin) and never prints a
 * second set of tags.
 *
 * Every value is plain text, cut to a limit, free of long dashes. The social picture must be an
 * image in the media library. Nothing here calls out to any service.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class TWD_SK_Seo {

	const K_TITLE   = '_twd_sk_seo_title';
	const K_DESC    = '_twd_sk_seo_desc';
	const K_IMAGE   = '_twd_sk_seo_image';
	const K_NOINDEX = '_twd_sk_seo_noindex';

	const Y_TITLE    = '_yoast_wpseo_title';
	const Y_DESC     = '_yoast_wpseo_metadesc';
	const Y_OG_URL   = '_yoast_wpseo_opengraph-image';
	const Y_OG_ID    = '_yoast_wpseo_opengraph-image-id';
	const Y_TW_URL   = '_yoast_wpseo_twitter-image';
	const Y_TW_ID    = '_yoast_wpseo_twitter-image-id';
	const Y_NOINDEX  = '_yoast_wpseo_meta-robots-noindex';

	const MAX_TITLE = 120;
	const MAX_DESC  = 300;

	public static function init() {
		add_filter( 'pre_get_document_title', array( __CLASS__, 'filter_title' ), 20 );
		add_action( 'wp_head', array( __CLASS__, 'print_head' ), 1 );
		add_filter( 'wp_robots', array( __CLASS__, 'filter_robots' ) );
		add_filter( 'twd_sk_leftover_levels', array( __CLASS__, 'filter_levels' ), 10, 2 );
	}

	// -- Which plugin is in charge --------------------------------------------

	/**
	 * @return array { mode: yoast|other|none, name: string }
	 */
	public static function detect() {
		$found = array( 'mode' => 'none', 'name' => '' );
		if ( defined( 'WPSEO_VERSION' ) ) {
			$found = array( 'mode' => 'yoast', 'name' => 'Yoast SEO' );
		} else {
			foreach ( array( 'RANK_MATH_VERSION' => 'Rank Math', 'AIOSEO_VERSION' => 'All in One SEO', 'SEOPRESS_VERSION' => 'SEOPress', 'THE_SEO_FRAMEWORK_VERSION' => 'The SEO Framework' ) as $const => $name ) {
				if ( defined( $const ) ) {
					$found = array( 'mode' => 'other', 'name' => $name );
					break;
				}
			}
		}
		return apply_filters( 'twd_sk_seo_plugin', $found );
	}

	// -- Reading ----------------------------------------------------------

	private static function meta( $id, $key ) {
		$v = get_post_meta( (int) $id, $key, true );
		return is_string( $v ) ? $v : '';
	}

	/** The kit's own fields, whatever the mode (used for the fallback and to prefill Yoast). */
	private static function own( $id ) {
		return array(
			'title'       => self::meta( $id, self::K_TITLE ),
			'description' => self::meta( $id, self::K_DESC ),
			'image_id'    => (int) self::meta( $id, self::K_IMAGE ),
			'noindex'     => '1' === self::meta( $id, self::K_NOINDEX ),
		);
	}

	/** The current values for a page, from wherever they live. */
	public static function values( $id ) {
		$id   = (int) $id;
		$mode = self::detect()['mode'];
		$own  = self::own( $id );
		if ( 'yoast' !== $mode ) {
			return $own;
		}
		return array(
			'title'       => '' !== self::meta( $id, self::Y_TITLE ) ? self::meta( $id, self::Y_TITLE ) : $own['title'],
			'description' => '' !== self::meta( $id, self::Y_DESC ) ? self::meta( $id, self::Y_DESC ) : $own['description'],
			'image_id'    => (int) self::meta( $id, self::Y_OG_ID ) ? (int) self::meta( $id, self::Y_OG_ID ) : $own['image_id'],
			'noindex'     => '1' === self::meta( $id, self::Y_NOINDEX ) || ( '' === self::meta( $id, self::Y_NOINDEX ) && $own['noindex'] ),
		);
	}

	public static function read( $id ) {
		$id     = (int) $id;
		$det    = self::detect();
		$post   = get_post( $id );
		$vals   = self::values( $id );
		$image  = '';
		if ( $vals['image_id'] && function_exists( 'wp_get_attachment_image_url' ) ) {
			$image = (string) wp_get_attachment_image_url( $vals['image_id'], 'medium' );
		}
		$levels = TWD_SK_Sanitizer::find_leftovers_by_level( $vals['title'] . "\n" . $vals['description'] );
		return array(
			'mode'        => $det['mode'],
			'plugin'      => $det['name'],
			'title'       => $vals['title'],
			'description' => $vals['description'],
			'image_id'    => $vals['image_id'],
			'image_url'   => $image,
			'noindex'     => $vals['noindex'],
			'slug'        => $post ? (string) $post->post_name : '',
			'status'      => $post ? (string) $post->post_status : '',
			'permalink'   => (string) get_permalink( $id ),
			'is_site_page' => TWD_SK_Template::is_site_page( $id ),
			'site_name'   => function_exists( 'get_bloginfo' ) ? (string) get_bloginfo( 'name' ) : '',
			'leftovers'   => TWD_SK_Report::describe_leftovers( $levels['must'] + $levels['check'] ),
			'quality'     => TWD_SK_Quality::check( TWD_SK_Store::get_current( $id ) ),
			'max_title'   => self::MAX_TITLE,
			'max_desc'    => self::MAX_DESC,
		);
	}

	// -- Writing ----------------------------------------------------------

	/**
	 * Validate the fields that were sent (any subset). Returns array( valid, errors ).
	 */
	public static function validate( $input ) {
		$out = array( 'valid' => array(), 'errors' => array() );
		if ( ! is_array( $input ) ) {
			$out['errors'][] = 'Send the search details as a set of fields.';
			return $out;
		}
		foreach ( $input as $key => $value ) {
			switch ( $key ) {
				case 'title':
					if ( ! is_string( $value ) ) {
						$out['errors'][] = 'The title must be text.';
					} else {
						$out['valid']['title'] = TWD_SK_Profile::text( $value, self::MAX_TITLE );
					}
					break;
				case 'description':
					if ( ! is_string( $value ) ) {
						$out['errors'][] = 'The description must be text.';
					} else {
						$out['valid']['description'] = TWD_SK_Profile::text( $value, self::MAX_DESC );
					}
					break;
				case 'image_id':
					if ( '' === $value || null === $value || 0 === $value || '0' === $value ) {
						$out['valid']['image_id'] = 0;
					} elseif ( ( is_int( $value ) || ( is_string( $value ) && ctype_digit( $value ) ) ) && (int) $value > 0 && function_exists( 'wp_attachment_is_image' ) && wp_attachment_is_image( (int) $value ) ) {
						$out['valid']['image_id'] = (int) $value;
					} else {
						$out['errors'][] = 'The social picture must be the number of a picture in the media library.';
					}
					break;
				case 'noindex':
					if ( is_bool( $value ) || 0 === $value || 1 === $value || '0' === $value || '1' === $value ) {
						$out['valid']['noindex'] = (bool) $value;
					} else {
						$out['errors'][] = 'Keeping a page out of search must be on or off.';
					}
					break;
				case 'slug':
					if ( ! is_string( $value ) || '' === sanitize_title( $value ) ) {
						$out['errors'][] = 'The address ending must be letters, numbers and dashes, and not empty.';
					} else {
						$out['valid']['slug'] = sanitize_title( $value );
					}
					break;
				case 'confirm_slug_change':
					break; // handled by write()
				default:
					$out['errors'][] = 'Unknown field: ' . ( is_string( $key ) ? $key : '?' ) . '.';
			}
		}
		return $out;
	}

	/**
	 * Save the fields that were sent.
	 *
	 * @param int   $id
	 * @param array $input title, description, image_id, noindex, slug, confirm_slug_change
	 * @return array|WP_Error The page's values afterwards.
	 */
	public static function write( $id, $input ) {
		$id    = (int) $id;
		$check = self::validate( $input );
		if ( $check['errors'] ) {
			return new WP_Error( 'twd_sk_bad_seo', implode( ' ', $check['errors'] ) );
		}
		$v   = $check['valid'];
		$det = self::detect();

		$fields = array_intersect_key( $v, array_flip( array( 'title', 'description', 'image_id', 'noindex' ) ) );
		if ( $fields && 'other' === $det['mode'] ) {
			return new WP_Error( 'twd_sk_seo_other_plugin', $det['name'] . ' looks after the search details on this site. Change the title, description, picture and keep-out setting there.' );
		}

		$post = get_post( $id );
		if ( isset( $v['slug'] ) && $post && $v['slug'] !== $post->post_name ) {
			if ( TWD_SK_Template::is_site_page( $id ) ) {
				return new WP_Error( 'twd_sk_seo_front_page', 'This is your front page or posts page. Its address cannot be changed from here.' );
			}
			$confirm = isset( $input['confirm_slug_change'] ) && ( true === $input['confirm_slug_change'] || '1' === $input['confirm_slug_change'] || 1 === $input['confirm_slug_change'] );
			if ( 'publish' === $post->post_status && ! $confirm ) {
				return new WP_Error( 'twd_sk_slug_confirm', 'This page is live. Changing its address changes its link, and anyone with the old link is sent to the new one. Please confirm.', array( 'current' => $post->post_name ) );
			}
		}

		$touched = false;
		if ( isset( $v['slug'] ) && $post && $v['slug'] !== $post->post_name ) {
			$r = wp_update_post( array( 'ID' => $id, 'post_name' => $v['slug'] ), true );
			if ( is_wp_error( $r ) ) {
				return $r;
			}
			$touched = true;
		}

		if ( $fields ) {
			if ( 'yoast' === $det['mode'] ) {
				self::write_yoast( $id, $fields );
				if ( ! $touched ) {
					// Re-save the page so Yoast refreshes what it has stored about it.
					wp_update_post( array( 'ID' => $id ) );
				}
			} else {
				self::write_own( $id, $fields );
			}
		}
		return self::read( $id );
	}

	private static function write_own( $id, $f ) {
		if ( isset( $f['title'] ) ) {
			update_post_meta( $id, self::K_TITLE, $f['title'] );
		}
		if ( isset( $f['description'] ) ) {
			update_post_meta( $id, self::K_DESC, $f['description'] );
		}
		if ( isset( $f['image_id'] ) ) {
			update_post_meta( $id, self::K_IMAGE, (string) $f['image_id'] );
		}
		if ( isset( $f['noindex'] ) ) {
			update_post_meta( $id, self::K_NOINDEX, $f['noindex'] ? '1' : '0' );
		}
	}

	private static function write_yoast( $id, $f ) {
		if ( isset( $f['title'] ) ) {
			update_post_meta( $id, self::Y_TITLE, $f['title'] );
		}
		if ( isset( $f['description'] ) ) {
			update_post_meta( $id, self::Y_DESC, $f['description'] );
		}
		if ( isset( $f['image_id'] ) ) {
			$url = ( $f['image_id'] && function_exists( 'wp_get_attachment_image_url' ) ) ? (string) wp_get_attachment_image_url( $f['image_id'], 'full' ) : '';
			update_post_meta( $id, self::Y_OG_URL, $url );
			update_post_meta( $id, self::Y_OG_ID, $f['image_id'] ? (string) $f['image_id'] : '' );
			update_post_meta( $id, self::Y_TW_URL, $url );
			update_post_meta( $id, self::Y_TW_ID, $f['image_id'] ? (string) $f['image_id'] : '' );
		}
		if ( isset( $f['noindex'] ) ) {
			update_post_meta( $id, self::Y_NOINDEX, $f['noindex'] ? '1' : '0' );
		}
	}

	// -- Printing (only when no SEO plugin is active) ------------------------

	private static function printing_for() {
		if ( 'none' !== self::detect()['mode'] || TWD_SK_Safe::on() ) {
			return 0;
		}
		if ( function_exists( 'is_singular' ) && ! is_singular( 'page' ) ) {
			return 0;
		}
		return function_exists( 'get_queried_object_id' ) ? (int) get_queried_object_id() : 0;
	}

	public static function filter_title( $title ) {
		$id = self::printing_for();
		if ( $id <= 0 ) {
			return $title;
		}
		$own = self::own( $id );
		return '' !== $own['title'] ? $own['title'] : $title;
	}

	public static function filter_robots( $robots ) {
		$id = self::printing_for();
		if ( $id > 0 && self::own( $id )['noindex'] ) {
			$robots['noindex'] = true;
		}
		return $robots;
	}

	public static function print_head() {
		$id = self::printing_for();
		if ( $id <= 0 ) {
			return;
		}
		$own = self::own( $id );
		if ( '' === $own['title'] && '' === $own['description'] && ! $own['image_id'] ) {
			return;
		}
		$title = '' !== $own['title'] ? $own['title'] : (string) get_the_title( $id );
		$out   = array();
		if ( '' !== $own['description'] ) {
			$out[] = '<meta name="description" content="' . esc_attr( $own['description'] ) . '">';
		}
		$out[] = '<meta property="og:type" content="website">';
		$out[] = '<meta property="og:title" content="' . esc_attr( $title ) . '">';
		if ( '' !== $own['description'] ) {
			$out[] = '<meta property="og:description" content="' . esc_attr( $own['description'] ) . '">';
		}
		$out[] = '<meta property="og:url" content="' . esc_url( get_permalink( $id ) ) . '">';
		$name  = function_exists( 'get_bloginfo' ) ? (string) get_bloginfo( 'name' ) : '';
		if ( '' !== $name ) {
			$out[] = '<meta property="og:site_name" content="' . esc_attr( $name ) . '">';
		}
		$image = ( $own['image_id'] && function_exists( 'wp_get_attachment_image_url' ) ) ? (string) wp_get_attachment_image_url( $own['image_id'], 'large' ) : '';
		if ( '' !== $image ) {
			$out[] = '<meta property="og:image" content="' . esc_url( $image ) . '">';
		}
		$out[] = '<meta name="twitter:card" content="' . ( '' !== $image ? 'summary_large_image' : 'summary' ) . '">';
		$out[] = '<meta name="twitter:title" content="' . esc_attr( $title ) . '">';
		if ( '' !== $own['description'] ) {
			$out[] = '<meta name="twitter:description" content="' . esc_attr( $own['description'] ) . '">';
		}
		if ( '' !== $image ) {
			$out[] = '<meta name="twitter:image" content="' . esc_url( $image ) . '">';
		}
		echo implode( "\n", $out ) . "\n";
	}

	// -- The leftover check ---------------------------------------------------

	/** Adds example text found in the title and description to a page's leftover levels. */
	public static function filter_levels( $levels, $post_id ) {
		$vals = self::values( (int) $post_id );
		$more = TWD_SK_Sanitizer::find_leftovers_by_level( $vals['title'] . "\n" . $vals['description'] );
		foreach ( array( 'must', 'check' ) as $level ) {
			foreach ( $more[ $level ] as $marker => $count ) {
				$levels[ $level ][ $marker ] = ( isset( $levels[ $level ][ $marker ] ) ? $levels[ $level ][ $marker ] : 0 ) + $count;
			}
		}
		return $levels;
	}
}
