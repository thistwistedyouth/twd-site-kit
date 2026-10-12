<?php
/**
 * TWD_SK_Pictures: the pictures chosen while setting a site up, and putting them into pages.
 *
 * Pictures are chosen through the WordPress media library (Site Kit never handles an upload); only
 * the attachment ID is kept. There are two kinds:
 *
 *  - Slots, a fixed list: the logo, the Home page's main picture, the About page photo.
 *  - Named pictures, up to MAX_CUSTOM: a short name (letters, numbers and dashes), a description
 *    (used as the alt text) and where to use it (any page, or one page by its slug).
 *
 * The AI is shown the named pictures for a page and may write src="pic:NAME" to place one. Every
 * pic: reference is swapped for the real address here, BEFORE the page goes through the sanitiser
 * (which would otherwise drop the unknown scheme). A name that is not on the list is never trusted:
 * it goes back to the sample address, which the leftover check flags. Sample picture spots that are
 * still empty are then filled in order: the slot picture first, then named pictures for this page,
 * then named pictures for any page that have not been used yet.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class TWD_SK_Pictures {

	const MAX_CUSTOM = 20;
	const MAX_NAME   = 40;
	const MAX_DESC   = 200;
	const SAMPLE_SRC = '/wp-content/uploads/your-image.jpg';

	/** slot => label, help, the page type it belongs to ('' for the logo, which goes in the site details). */
	public static function slots() {
		return array(
			'logo'        => array( 'label' => 'Logo', 'help' => 'Shown in the header. A wide picture with a clear or plain background works best.', 'page' => '' ),
			'home_main'   => array( 'label' => 'Home page main picture', 'help' => 'The first picture on the Home page.', 'page' => 'home' ),
			'about_photo' => array( 'label' => 'About page photo', 'help' => 'Usually a friendly photo of the person behind the site.', 'page' => 'about' ),
		);
	}

	public static function empty_set() {
		return array( 'slots' => array(), 'custom' => array() );
	}

	private static function is_image( $id ) {
		$id = (int) $id;
		return $id > 0 && function_exists( 'wp_attachment_is_image' ) && wp_attachment_is_image( $id );
	}

	/** A short name from any text: lowercase letters, numbers and single dashes. */
	public static function slug( $text ) {
		$s = strtolower( (string) $text );
		$s = preg_replace( '/[^a-z0-9]+/', '-', $s );
		$s = trim( preg_replace( '/-+/', '-', $s ), '-' );
		return substr( $s, 0, self::MAX_NAME );
	}

	/**
	 * Check a set of pictures. Nothing is trusted: an ID must be a real image, a name must be short
	 * and unique, a description is plain text.
	 *
	 * @return array { valid: { slots, custom }, errors[] }
	 */
	public static function validate( $in ) {
		$out    = self::empty_set();
		$errors = array();
		if ( ! is_array( $in ) ) {
			return array( 'valid' => $out, 'errors' => $in ? array( 'The pictures must be a list.' ) : array() );
		}
		$slots = self::slots();
		if ( isset( $in['slots'] ) && is_array( $in['slots'] ) ) {
			foreach ( $in['slots'] as $slot => $pic ) {
				if ( ! isset( $slots[ $slot ] ) ) {
					$errors[] = 'There is no picture slot called "' . TWD_SK_Profile::text( $slot, 40 ) . '".';
					continue;
				}
				$id   = is_array( $pic ) && isset( $pic['id'] ) ? (int) $pic['id'] : (int) $pic;
				$desc = is_array( $pic ) && isset( $pic['description'] ) ? TWD_SK_Profile::text( $pic['description'], self::MAX_DESC ) : '';
				if ( 0 === $id ) {
					continue;
				}
				if ( ! self::is_image( $id ) ) {
					$errors[] = $slots[ $slot ]['label'] . ': that is not a picture in the media library.';
					continue;
				}
				$out['slots'][ $slot ] = array( 'id' => $id, 'description' => $desc );
			}
		}
		if ( isset( $in['custom'] ) && is_array( $in['custom'] ) ) {
			$seen = array();
			foreach ( $in['custom'] as $i => $pic ) {
				if ( count( $out['custom'] ) >= self::MAX_CUSTOM ) {
					$errors[] = 'Only the first ' . self::MAX_CUSTOM . ' pictures were kept.';
					break;
				}
				$n = $i + 1;
				if ( ! is_array( $pic ) ) {
					$errors[] = 'Picture ' . $n . ' was not understood.';
					continue;
				}
				$id = isset( $pic['id'] ) ? (int) $pic['id'] : 0;
				if ( ! self::is_image( $id ) ) {
					$errors[] = 'Picture ' . $n . ': that is not a picture in the media library.';
					continue;
				}
				$name = isset( $pic['name'] ) && is_string( $pic['name'] ) ? trim( $pic['name'] ) : '';
				if ( 1 !== preg_match( '/^[a-z0-9]+(-[a-z0-9]+)*$/', $name ) || strlen( $name ) > self::MAX_NAME ) {
					$errors[] = 'Picture ' . $n . ': the name must be lowercase letters, numbers and dashes, for example front-view.';
					continue;
				}
				if ( isset( $seen[ $name ] ) ) {
					$errors[] = 'Picture ' . $n . ': the name "' . $name . '" is used twice. Each picture needs its own name.';
					continue;
				}
				$use = isset( $pic['use_on'] ) && is_string( $pic['use_on'] ) && '' !== $pic['use_on'] ? self::slug( $pic['use_on'] ) : 'any';
				$seen[ $name ]   = true;
				$out['custom'][] = array(
					'name'        => $name,
					'id'          => $id,
					'description' => isset( $pic['description'] ) ? TWD_SK_Profile::text( $pic['description'], self::MAX_DESC ) : '',
					'use_on'      => '' === $use ? 'any' : $use,
				);
			}
		}
		return array( 'valid' => $out, 'errors' => $errors );
	}

	/** The address of a picture, or ''. */
	public static function url( $id ) {
		$u = function_exists( 'wp_get_attachment_url' ) ? wp_get_attachment_url( (int) $id ) : '';
		return is_string( $u ) ? $u : '';
	}

	/** Alt text: the description given, else the media library's own alt text, else ''. */
	public static function alt( $id, $description ) {
		if ( '' !== trim( (string) $description ) ) {
			return trim( (string) $description );
		}
		$meta = function_exists( 'get_post_meta' ) ? get_post_meta( (int) $id, '_wp_attachment_image_alt', true ) : '';
		return is_string( $meta ) ? TWD_SK_Profile::text( $meta, self::MAX_DESC ) : '';
	}

	/** The named pictures that may go on a page (its own, then any page). */
	public static function for_page( $set, $page_slug ) {
		$own = array();
		$any = array();
		foreach ( (array) $set['custom'] as $pic ) {
			if ( $page_slug === $pic['use_on'] ) {
				$own[] = $pic;
			} elseif ( 'any' === $pic['use_on'] ) {
				$any[] = $pic;
			}
		}
		return array_merge( $own, $any );
	}

	/** The block of text that tells the AI which pictures it may place on this page. '' when none. */
	public static function prompt_block( $set, $page_slug ) {
		$list = self::for_page( $set, $page_slug );
		if ( ! $list ) {
			return '';
		}
		$out   = array();
		$out[] = 'Pictures you may use on this page (write the address exactly as shown, each picture once at most):';
		foreach ( $list as $pic ) {
			$out[] = '- pic:' . $pic['name'] . ( '' !== $pic['description'] ? ' (' . $pic['description'] . ')' : '' );
		}
		return implode( "\n", $out );
	}

	/**
	 * Put the pictures into one page's HTML.
	 *
	 * @param string $html      The page HTML, before it is saved (and so before the sanitiser).
	 * @param array  $set       A validated set (validate()['valid']).
	 * @param string $page_slug The page's slug, matched against "use on".
	 * @param string $page_type The recipe type: home and about take their slot picture.
	 * @param array  $used      Names already placed on other pages (updated).
	 * @return array { html, report[] }
	 */
	public static function place( $html, $set, $page_slug, $page_type, &$used ) {
		$report  = array();
		$by_name = array();
		foreach ( self::for_page( $set, $page_slug ) as $pic ) {
			$by_name[ $pic['name'] ] = $pic;
		}
		$slot = '';
		foreach ( self::slots() as $key => $s ) {
			if ( '' !== $s['page'] && $page_type === $s['page'] && isset( $set['slots'][ $key ] ) ) {
				$slot = $key;
			}
		}
		$queue = array();
		foreach ( self::for_page( $set, $page_slug ) as $pic ) {
			if ( ! in_array( $pic['name'], $used, true ) ) {
				$queue[] = $pic;
			}
		}

		$html = preg_replace_callback(
			'/<img\b[^>]*>/i',
			function ( $m ) use ( &$report, &$used, &$queue, &$slot, $by_name, $set ) {
				$tag = $m[0];
				$src = preg_match( '/\ssrc\s*=\s*"([^"]*)"/i', $tag, $s ) ? $s[1] : '';
				$pic = null;
				if ( 0 === strpos( $src, 'pic:' ) ) {
					$name = substr( $src, 4 );
					if ( isset( $by_name[ $name ] ) && ! in_array( $name, $used, true ) ) {
						$pic = $by_name[ $name ];
					} else {
						$report[] = 'The AI asked for a picture called "' . TWD_SK_Profile::text( $name, 40 ) . '" that is not on this page\'s list (or was already used). The sample picture was kept there.';
						return self::set_src( $tag, self::SAMPLE_SRC, null );
					}
				} elseif ( false !== strpos( $src, 'your-image' ) ) {
					if ( '' !== $slot ) {
						$s    = $set['slots'][ $slot ];
						$pic  = array( 'name' => '', 'id' => $s['id'], 'description' => $s['description'] );
						$slot = '';
					} elseif ( $queue ) {
						$pic = array_shift( $queue );
					} else {
						return $tag;
					}
				} else {
					return $tag;
				}
				$url = self::url( $pic['id'] );
				if ( '' === $url ) {
					$report[] = 'A picture could not be found in the media library, so the sample picture was kept.';
					return self::set_src( $tag, self::SAMPLE_SRC, null );
				}
				if ( '' !== $pic['name'] ) {
					$used[] = $pic['name'];
					$queue  = array_values( array_filter( $queue, function ( $q ) use ( $pic ) {
						return $q['name'] !== $pic['name'];
					} ) );
				}
				$alt = self::alt( $pic['id'], $pic['description'] );
				return self::set_src( $tag, $url, '' === $alt ? null : $alt );
			},
			(string) $html
		);
		return array( 'html' => $html, 'report' => $report );
	}

	/** Set the src (and the alt, unless $alt is null) on one img tag. Values are literal, never patterns. */
	private static function set_src( $tag, $src, $alt ) {
		$tag = self::set_attr( $tag, 'src', str_replace( '"', '', $src ) );
		if ( null !== $alt ) {
			$tag = self::set_attr( $tag, 'alt', htmlspecialchars( $alt, ENT_QUOTES, 'UTF-8' ) );
		}
		return $tag;
	}

	private static function set_attr( $tag, $name, $value ) {
		$pattern = '/\s' . $name . '\s*=\s*"[^"]*"/i';
		if ( preg_match( $pattern, $tag ) ) {
			return preg_replace_callback( $pattern, function () use ( $name, $value ) {
				return ' ' . $name . '="' . $value . '"';
			}, $tag, 1 );
		}
		return '<img ' . $name . '="' . $value . '"' . substr( $tag, 4 );
	}
}
