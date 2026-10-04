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

	const MAX_TOPIC = 120;
	const MAX_NOTES = 1500;

	public static function types() {
		$starters = TWD_SK_Starters::pages();
		return array(
			'about'   => array(
				'label'       => 'About page',
				'title'       => 'About',
				'needs_topic' => false,
				'parts'       => $starters['about']['parts'],
				'intent'      => 'An About page. Who the therapist is, their story and what drives their work, how they work, what working with them is like, and a clear next step. Warm and human, in their own voice.',
			),
			'contact' => array(
				'label'       => 'Contact page',
				'title'       => 'Contact',
				'needs_topic' => false,
				'parts'       => $starters['contact']['parts'],
				'intent'      => 'A Contact page. How to get in touch, what to expect after getting in touch, where and when they work, and a few honest questions and answers. Use only contact details that appear in the facts.',
			),
			'home'    => array(
				'label'       => 'Home page',
				'title'       => 'Home',
				'needs_topic' => false,
				'parts'       => $starters['home']['parts'],
				'intent'      => 'The Home page. A clear opening that says who the therapist helps and how, what they offer, a short personal section, reassurance, and a clear next step.',
			),
			'service' => array(
				'label'       => 'Service or topic page (for example Working with anxiety)',
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
				'intent'      => 'A page about working with one topic or kind of work (given below). What it is and how it can feel, how this therapist works with it, what a first session is like, common questions, and a clear next step. Never promise outcomes, never make clinical or health claims, never diagnose. Use only what the facts say about how this therapist works.',
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
				'intent'      => 'A questions and answers page. The questions people ask most, answered in the therapist\'s own words, using only the facts.',
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
			return new WP_Error( 'twd_sk_bad_input', 'Say what the page is about, for example "Working with anxiety".' );
		}
		$title = TWD_SK_Profile::text( isset( $in['title'] ) ? $in['title'] : '', TWD_SK_Template::MAX_TITLE );
		if ( '' === $title ) {
			$title = '' !== $r['title'] ? $r['title'] : $topic;
		}
		$notes = TWD_SK_Profile::text( isset( $in['notes'] ) ? $in['notes'] : '', self::MAX_NOTES );
		return array( 'type' => $type, 'title' => $title, 'topic' => $topic, 'notes' => $notes );
	}
}
