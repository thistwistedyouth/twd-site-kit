<?php
/**
 * TWD_SK_Registry: the components registry.
 *
 * Pure data. Nothing in here renders anything. It feeds two consumers:
 *  - TWD_SK_Sanitizer, which builds its class and shortcode allowlists from it
 *  - the AI style guide (slice 2), via style_guide_data()
 *
 * Everything is keyed on component IDs, never display names. A label can be
 * reworded freely without breaking anything that looks a component up.
 *
 * Classes may overlap between components (for example the button classes are
 * used by both the hero and the call to action). The allowlist is the union.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class TWD_SK_Registry {

	/**
	 * Attribute rules for the one shortcode a page may contain.
	 * Each value is a regex the attribute value must match in full.
	 */
	private static function articles_shortcode_attrs() {
		return array(
			'category' => '/^[\p{L}\p{N}\s,_-]{1,100}$/u',
			'tag'      => '/^[\p{L}\p{N}\s,_-]{1,100}$/u',
			'count'    => '/^\d{1,2}$/',
			'columns'  => '/^[1-4]$/',
		);
	}

	private static function lines( $lines ) {
		return implode( "\n", $lines );
	}

	/**
	 * All components, keyed by ID.
	 *
	 * @return array id => array( id, label, skeleton, classes, shortcodes )
	 */
	public static function all() {
		static $components = null;
		if ( null !== $components ) {
			return $components;
		}

		$components = array(

			'hero' => array(
				'id'         => 'hero',
				'label'      => 'Hero',
				'skeleton'   => self::lines( array(
					'<section class="twd-sk-hero">',
					'<div class="twd-sk-hero__inner">',
					'<h2 class="twd-sk-hero__title">Main heading here</h2>',
					'<p class="twd-sk-hero__lead">One or two sentences introducing the page.</p>',
					'<div class="twd-sk-hero__actions">',
					'<a class="twd-sk-btn twd-sk-btn--primary" href="/contact">Get in touch</a>',
					'<a class="twd-sk-btn twd-sk-btn--secondary" href="#twd-sk-services">Find out more</a>',
					'</div>',
					'</div>',
					'</section>',
				) ),
				'classes'    => array(
					'twd-sk-hero',
					'twd-sk-hero__inner',
					'twd-sk-hero__title',
					'twd-sk-hero__lead',
					'twd-sk-hero__actions',
					'twd-sk-btn',
					'twd-sk-btn--primary',
					'twd-sk-btn--secondary',
				),
				'shortcodes' => array(),
			),

			'text' => array(
				'id'         => 'text',
				'label'      => 'Text',
				'skeleton'   => self::lines( array(
					'<section class="twd-sk-text">',
					'<div class="twd-sk-text__inner">',
					'<h2 class="twd-sk-text__title">Section heading here</h2>',
					'<div class="twd-sk-text__body">',
					'<p>Paragraph text here.</p>',
					'<p>Another paragraph here.</p>',
					'</div>',
					'</div>',
					'</section>',
				) ),
				'classes'    => array(
					'twd-sk-text',
					'twd-sk-text__inner',
					'twd-sk-text__title',
					'twd-sk-text__body',
				),
				'shortcodes' => array(),
			),

			'image_text' => array(
				'id'         => 'image_text',
				'label'      => 'Image and text',
				'skeleton'   => self::lines( array(
					'<section class="twd-sk-image-text">',
					'<figure class="twd-sk-image-text__media">',
					'<img class="twd-sk-image-text__img" src="/wp-content/uploads/your-image.jpg" alt="Describe the image">',
					'</figure>',
					'<div class="twd-sk-image-text__body">',
					'<h2 class="twd-sk-image-text__title">Heading here</h2>',
					'<p>Paragraph text here.</p>',
					'</div>',
					'</section>',
				) ),
				'classes'    => array(
					'twd-sk-image-text',
					'twd-sk-image-text--reverse',
					'twd-sk-image-text__media',
					'twd-sk-image-text__img',
					'twd-sk-image-text__body',
					'twd-sk-image-text__title',
				),
				'shortcodes' => array(),
			),

			'quote' => array(
				'id'         => 'quote',
				'label'      => 'Quote or testimonial',
				'skeleton'   => self::lines( array(
					'<figure class="twd-sk-quote">',
					'<blockquote class="twd-sk-quote__text">',
					'<p>Quote text here.</p>',
					'</blockquote>',
					'<figcaption class="twd-sk-quote__cite">Name and context</figcaption>',
					'</figure>',
				) ),
				'classes'    => array(
					'twd-sk-quote',
					'twd-sk-quote__text',
					'twd-sk-quote__cite',
				),
				'shortcodes' => array(),
			),

			'faq' => array(
				'id'         => 'faq',
				'label'      => 'FAQ',
				'skeleton'   => self::lines( array(
					'<section class="twd-sk-faq">',
					'<h2 class="twd-sk-faq__title">Frequently asked questions</h2>',
					'<div class="twd-sk-faq__list">',
					'<details class="twd-sk-faq__item">',
					'<summary class="twd-sk-faq__question">Question here?</summary>',
					'<div class="twd-sk-faq__answer">',
					'<p>Answer here.</p>',
					'</div>',
					'</details>',
					'</div>',
					'</section>',
				) ),
				'classes'    => array(
					'twd-sk-faq',
					'twd-sk-faq__title',
					'twd-sk-faq__list',
					'twd-sk-faq__item',
					'twd-sk-faq__question',
					'twd-sk-faq__answer',
				),
				'shortcodes' => array(),
			),

			'services' => array(
				'id'         => 'services',
				'label'      => 'Services list',
				'skeleton'   => self::lines( array(
					'<section class="twd-sk-services" id="twd-sk-services">',
					'<h2 class="twd-sk-services__title">What I offer</h2>',
					'<p class="twd-sk-services__intro">Short introduction here.</p>',
					'<div class="twd-sk-services__list">',
					'<div class="twd-sk-services__item">',
					'<h3 class="twd-sk-services__name">Service name here</h3>',
					'<p class="twd-sk-services__desc">Short description here.</p>',
					'</div>',
					'</div>',
					'</section>',
				) ),
				'classes'    => array(
					'twd-sk-services',
					'twd-sk-services__title',
					'twd-sk-services__intro',
					'twd-sk-services__list',
					'twd-sk-services__item',
					'twd-sk-services__name',
					'twd-sk-services__desc',
				),
				'shortcodes' => array(),
			),

			'team' => array(
				'id'         => 'team',
				'label'      => 'Team or bio',
				'skeleton'   => self::lines( array(
					'<section class="twd-sk-team">',
					'<div class="twd-sk-team__item">',
					'<figure class="twd-sk-team__photo">',
					'<img class="twd-sk-team__img" src="/wp-content/uploads/your-photo.jpg" alt="Name of person">',
					'</figure>',
					'<div class="twd-sk-team__body">',
					'<h3 class="twd-sk-team__name">Name here</h3>',
					'<p class="twd-sk-team__role">Role here</p>',
					'<p class="twd-sk-team__bio">Short bio here.</p>',
					'</div>',
					'</div>',
					'</section>',
				) ),
				'classes'    => array(
					'twd-sk-team',
					'twd-sk-team__item',
					'twd-sk-team__photo',
					'twd-sk-team__img',
					'twd-sk-team__body',
					'twd-sk-team__name',
					'twd-sk-team__role',
					'twd-sk-team__bio',
				),
				'shortcodes' => array(),
			),

			'cta' => array(
				'id'         => 'cta',
				'label'      => 'Call to action and contact',
				'skeleton'   => self::lines( array(
					'<section class="twd-sk-cta">',
					'<div class="twd-sk-cta__inner">',
					'<h2 class="twd-sk-cta__title">Heading here</h2>',
					'<p class="twd-sk-cta__text">One or two sentences inviting contact.</p>',
					'<div class="twd-sk-cta__actions">',
					'<a class="twd-sk-btn twd-sk-btn--primary" href="mailto:EMAIL_ADDRESS">Email</a>',
					'<a class="twd-sk-btn twd-sk-btn--secondary" href="tel:PHONE_NUMBER">Call</a>',
					'</div>',
					'</div>',
					'</section>',
				) ),
				'classes'    => array(
					'twd-sk-cta',
					'twd-sk-cta__inner',
					'twd-sk-cta__title',
					'twd-sk-cta__text',
					'twd-sk-cta__actions',
					'twd-sk-btn',
					'twd-sk-btn--primary',
					'twd-sk-btn--secondary',
				),
				'shortcodes' => array(),
			),

			'resources' => array(
				'id'         => 'resources',
				'label'      => 'Resources',
				'skeleton'   => self::lines( array(
					'<section class="twd-sk-resources">',
					'<h2 class="twd-sk-resources__title">Resources</h2>',
					'<p class="twd-sk-resources__intro">Short introduction here.</p>',
					'[twd_articles count="6" columns="3"]',
					'</section>',
				) ),
				'classes'    => array(
					'twd-sk-resources',
					'twd-sk-resources__title',
					'twd-sk-resources__intro',
				),
				'shortcodes' => array(
					'twd_articles' => self::articles_shortcode_attrs(),
				),
			),

		);

		return $components;
	}

	/**
	 * One component by ID, or null. Never looks anything up by label.
	 */
	public static function get( $id ) {
		$all = self::all();
		return ( is_string( $id ) && isset( $all[ $id ] ) ) ? $all[ $id ] : null;
	}

	public static function ids() {
		return array_keys( self::all() );
	}

	/**
	 * Every class any component may use, as class => true.
	 */
	public static function allowed_classes() {
		$out = array();
		foreach ( self::all() as $component ) {
			foreach ( $component['classes'] as $class ) {
				$out[ $class ] = true;
			}
		}
		return $out;
	}

	/**
	 * Every shortcode a page may contain, as tag => attribute rules.
	 */
	public static function allowed_shortcodes() {
		$out = array();
		foreach ( self::all() as $component ) {
			foreach ( $component['shortcodes'] as $tag => $attrs ) {
				$out[ $tag ] = $attrs;
			}
		}
		return $out;
	}

	/**
	 * Plain data for the AI style guide (used from slice 2). Nothing rendered.
	 */
	public static function style_guide_data() {
		$out = array();
		foreach ( self::all() as $id => $component ) {
			$out[ $id ] = array(
				'id'         => $component['id'],
				'label'      => $component['label'],
				'skeleton'   => $component['skeleton'],
				'classes'    => $component['classes'],
				'shortcodes' => array_keys( $component['shortcodes'] ),
			);
		}
		return $out;
	}
}
