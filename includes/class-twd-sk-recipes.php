<?php
/**
 * TWD_SK_Recipes: the page types that can be drafted from the practice facts.
 *
 * A recipe is a fixed outline of kit components plus one plain sentence on what the page is for.
 * The AI fills the outline from the practice facts and never invents anything: where the facts do
 * not say, the outline keeps a visible [PLACEHOLDER], and the publish check holds the page back.
 * The outline is built from the registry's own skeletons, so a drafted page can only use real
 * components.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class TWD_SK_Recipes {

	const MAX_TOPIC    = 120;
	const MAX_NOTES    = 1500;
	const MAX_SECTIONS = 12;

	public static function types() {
		$starters = TWD_SK_Starters::pages();
		return array(
			'about'   => array(
				'label'       => 'About page',
				'title'       => 'About',
				'needs_topic' => false,
				'parts'       => $starters['about']['parts'],
				'intent'      => TWD_SK_Mode::w( 'recipe_about' ),
			),
			'contact' => array(
				'label'       => 'Contact page',
				'title'       => 'Contact',
				'needs_topic' => false,
				'parts'       => $starters['contact']['parts'],
				'intent'      => TWD_SK_Mode::w( 'recipe_contact' ),
			),
			'home'    => array(
				'label'       => 'Home page',
				'title'       => 'Home',
				'needs_topic' => false,
				'parts'       => $starters['home']['parts'],
				'intent'      => TWD_SK_Mode::w( 'recipe_home' ),
			),
			'service' => array(
				'label'       => TWD_SK_Mode::w( 'recipe_service_label' ),
				'title'       => '',
				'needs_topic' => true,
				'parts'       => array(
					array( 'hero', 'twd-sk-hero--compact' ),
					array( 'text', '' ),
					array( 'steps', '' ),
					array( 'faq', '' ),
					array( 'notice', '' ),
					array( 'cta', 'twd-sk-cta--strip' ),
				),
				'intent'      => TWD_SK_Mode::w( 'recipe_service' ),
			),
			'faq'     => array(
				'label'       => 'Questions and answers page',
				'title'       => 'Questions and answers',
				'needs_topic' => false,
				'parts'       => array(
					array( 'hero', 'twd-sk-hero--compact' ),
					array( 'faq', '' ),
					array( 'cta', 'twd-sk-cta--strip' ),
				),
				'intent'      => TWD_SK_Mode::w( 'recipe_faq' ),
			),
		);
	}

	/** key => label, for the pop-up. */
	public static function labels() {
		$out = array();
		foreach ( self::types() as $key => $r ) {
			$out[ $key ] = $r['label'];
		}
		return $out;
	}

	public static function get( $key ) {
		$all = self::types();
		return isset( $all[ $key ] ) ? $all[ $key ] : null;
	}

	/** The component outline for a type, with sample wording as visible markers. */
	public static function skeleton( $key ) {
		$r = self::get( $key );
		return $r ? TWD_SK_Starters::build( $r['parts'] ) : '';
	}

	/** The outline for checked inputs: their own sections when given, otherwise the type's. */
	public static function skeleton_for( $in ) {
		if ( ! empty( $in['parts'] ) ) {
			return TWD_SK_Starters::build( $in['parts'] );
		}
		return self::skeleton( isset( $in['type'] ) ? $in['type'] : '' );
	}

	/** Every component and its layouts, for choosing sections: id => { label, description, variants: class => label }. */
	public static function parts_menu() {
		$out = array();
		foreach ( TWD_SK_Registry::all() as $id => $c ) {
			$variants = array( '' => 'Standard' );
			foreach ( $c['variants'] as $class => $v ) {
				$variants[ $class ] = $v['label'];
			}
			$out[ $id ] = array( 'label' => $c['label'], 'description' => $c['description'], 'variants' => $variants );
		}
		return $out;
	}

	/**
	 * Check a list of sections. Each is array( component id, variant class ) or
	 * { component, variant }. The first must be a hero: it holds the page's one main heading.
	 *
	 * @return array|WP_Error list of array( id, variant ).
	 */
	public static function clean_parts( $list ) {
		if ( ! is_array( $list ) || ! $list ) {
			return new WP_Error( 'twd_sk_bad_input', 'A page needs at least one section.' );
		}
		if ( count( $list ) > self::MAX_SECTIONS ) {
			return new WP_Error( 'twd_sk_bad_input', 'A page can have up to ' . self::MAX_SECTIONS . ' sections.' );
		}
		$out = array();
		foreach ( array_values( $list ) as $i => $item ) {
			$id      = '';
			$variant = '';
			if ( is_array( $item ) && isset( $item['component'] ) ) {
				$id      = is_string( $item['component'] ) ? $item['component'] : '';
				$variant = isset( $item['variant'] ) && is_string( $item['variant'] ) ? $item['variant'] : '';
			} elseif ( is_array( $item ) && isset( $item[0] ) ) {
				$id      = is_string( $item[0] ) ? $item[0] : '';
				$variant = isset( $item[1] ) && is_string( $item[1] ) ? $item[1] : '';
			} elseif ( is_string( $item ) ) {
				$id = $item;
			}
			$c = TWD_SK_Registry::get( $id );
			if ( ! $c ) {
				return new WP_Error( 'twd_sk_bad_input', 'Section ' . ( $i + 1 ) . ' is not one of the kit\'s sections.' );
			}
			if ( '' !== $variant && ! isset( $c['variants'][ $variant ] ) ) {
				return new WP_Error( 'twd_sk_bad_input', 'Section ' . ( $i + 1 ) . ' (' . $c['label'] . ') does not have that layout.' );
			}
			$out[] = array( $id, $variant );
		}
		if ( 'hero' !== $out[0][0] ) {
			return new WP_Error( 'twd_sk_bad_input', 'A page starts with a hero section: it holds the page\'s main heading.' );
		}
		return $out;
	}

	/** Sections as plain data for the browser or a brief: list of { component, variant }. */
	public static function parts_data( $parts ) {
		return array_map( function ( $p ) {
			return array( 'component' => $p[0], 'variant' => $p[1] );
		}, (array) $parts );
	}

	/**
	 * Clean and check the inputs for a new page.
	 *
	 * @return array|WP_Error { type, title, topic, notes }
	 */
	public static function inputs( $in ) {
		$type = isset( $in['type'] ) && is_string( $in['type'] ) ? $in['type'] : '';
		$r    = self::get( $type );
		if ( ! $r ) {
			return new WP_Error( 'twd_sk_bad_input', 'Choose one of the page types on offer.' );
		}
		$topic = TWD_SK_Profile::text( isset( $in['topic'] ) ? $in['topic'] : '', self::MAX_TOPIC );
		if ( $r['needs_topic'] && '' === $topic ) {
			return new WP_Error( 'twd_sk_bad_input', 'Say what the page is about, for example "' . TWD_SK_Mode::w( 'topic_example' ) . '".' );
		}
		$title = TWD_SK_Profile::text( isset( $in['title'] ) ? $in['title'] : '', TWD_SK_Template::MAX_TITLE );
		if ( '' === $title ) {
			$title = '' !== $r['title'] ? $r['title'] : $topic;
		}
		$notes = TWD_SK_Profile::text( isset( $in['notes'] ) ? $in['notes'] : '', self::MAX_NOTES );
		// Its own sections (from the setup page's plan, or "sections" in a brief), otherwise the type's.
		$parts = $r['parts'];
		$given = isset( $in['parts'] ) ? $in['parts'] : ( isset( $in['sections'] ) ? $in['sections'] : null );
		if ( null !== $given ) {
			$parts = self::clean_parts( $given );
			if ( is_wp_error( $parts ) ) {
				return $parts;
			}
		}
		return array( 'type' => $type, 'title' => $title, 'topic' => $topic, 'notes' => $notes, 'parts' => $parts );
	}
}
