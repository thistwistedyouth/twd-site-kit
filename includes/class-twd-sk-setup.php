<?php
/**
 * TWD_SK_Setup: set a new site up from the starters.
 *
 * Creates Home, About and Contact as DRAFT pages ready for the kit, optionally switches the
 * style pack, fills a BLANK site profile with placeholders (never overwriting one that has
 * anything in it), and makes Home the front page. Safe to run twice: pages that already exist
 * are skipped. Nothing is published, and every page's text is a [PLACEHOLDER: ...] marker that
 * the leftover check flags and the publish block refuses.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class TWD_SK_Setup {

	/** The page already made for a starter key, or 0. */
	public static function existing( $key ) {
		$found = get_posts( array(
			'post_type'   => 'page',
			'post_status' => 'any',
			'meta_key'    => TWD_SK_Starters::META_KEY,
			'meta_value'  => $key,
			'numberposts' => 1,
			'fields'      => 'ids',
		) );
		return $found ? (int) $found[0] : 0;
	}

	/**
	 * What running setup would do right now, in plain sentences. Changes nothing.
	 */
	public static function plan( $options = array() ) {
		$front = ! isset( $options['front_page'] ) || $options['front_page'];
		$lines = array();
		foreach ( TWD_SK_Starters::pages() as $key => $page ) {
			$lines[] = self::existing( $key ) ? $page['title'] . ' already exists, so it is left alone.' : 'Create a draft page called ' . $page['title'] . ' with the starter layout.';
		}
		if ( ! empty( $options['pack'] ) ) {
			$lines[] = 'Switch the style to ' . $options['pack'] . '.';
		}
		$lines[] = TWD_SK_Profile::is_empty() ? 'Fill the empty site details with placeholders.' : 'Leave the site details as they are (they are not empty).';
		if ( $front ) {
			$lines[] = self::front_page_blocked() ? 'Leave the front page as it is (a published front page is already set).' : 'Make Home the front page. Visitors will see an error there until Home is published.';
		}
		return $lines;
	}

	/** True when a published page is already the static front page. */
	private static function front_page_blocked() {
		if ( 'page' !== get_option( 'show_on_front' ) ) {
			return false;
		}
		$id   = (int) get_option( 'page_on_front' );
		$post = $id ? get_post( $id ) : null;
		return $post && 'page' === $post->post_type && 'publish' === $post->post_status;
	}

	/**
	 * Run the setup.
	 *
	 * @param array $options pack (slug or ''), front_page (bool, default true)
	 * @return array|WP_Error report: created, skipped, pack, profile, front_page, notes
	 */
	public static function run( $options = array() ) {
		$front  = ! isset( $options['front_page'] ) || $options['front_page'];
		$pack   = isset( $options['pack'] ) && is_string( $options['pack'] ) ? $options['pack'] : '';
		$report = array( 'created' => array(), 'skipped' => array(), 'pack' => '', 'profile' => '', 'front_page' => '', 'notes' => array() );

		if ( '' !== $pack && ! isset( TWD_SK_Packs::packs()[ $pack ] ) ) {
			return new WP_Error( 'twd_sk_unknown_pack', 'There is no style pack called "' . $pack . '".' );
		}

		$ids = array();
		foreach ( TWD_SK_Starters::pages() as $key => $page ) {
			$have = self::existing( $key );
			if ( $have ) {
				$ids[ $key ] = $have;
				$report['skipped'][] = array( 'key' => $key, 'id' => $have, 'title' => $page['title'] );
				continue;
			}
			$id = TWD_SK_Template::create_page( $page['title'], 'blank' );
			if ( is_wp_error( $id ) ) {
				return $id;
			}
			update_post_meta( $id, TWD_SK_Starters::META_KEY, $key );
			$saved = TWD_SK_Store::save( $id, TWD_SK_Starters::html( $key ), array( 'note' => 'Starter layout' ) );
			if ( is_wp_error( $saved ) ) {
				return $saved;
			}
			$ids[ $key ]         = (int) $id;
			$report['created'][] = array( 'key' => $key, 'id' => (int) $id, 'title' => $page['title'] );
		}

		if ( '' !== $pack ) {
			TWD_SK_Packs::set_active( $pack );
			$report['pack'] = $pack;
		}

		if ( TWD_SK_Profile::is_empty() ) {
			$profile                  = TWD_SK_Profile::starter();
			$profile['about_page_id'] = $ids['about'];
			$saved                    = TWD_SK_Profile::save( $profile );
			if ( is_wp_error( $saved ) ) {
				return $saved;
			}
			$report['profile'] = 'filled';
		} else {
			$report['profile'] = 'kept';
			if ( ! TWD_SK_Profile::get()['about_page_id'] ) {
				TWD_SK_Profile::save( array( 'about_page_id' => $ids['about'] ) );
			}
			$report['notes'][] = 'The site details already had content, so they were not changed.';
		}

		if ( $front ) {
			if ( self::front_page_blocked() ) {
				$report['front_page'] = 'kept';
				$report['notes'][]    = 'A published front page is already set, so it was not changed.';
			} else {
				update_option( 'show_on_front', 'page' );
				update_option( 'page_on_front', $ids['home'] );
				$report['front_page'] = 'set';
				$report['notes'][]    = 'Home is the front page but is still a draft, so visitors will see an error there until it is published.';
			}
		}
		return $report;
	}
}
