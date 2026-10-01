<?php
/**
 * TWD_SK_Registry: the components registry.
 *
 * Pure data. Nothing in here renders anything. It feeds three consumers:
 *  - TWD_SK_Sanitizer, which builds its class and shortcode allowlists from it
 *  - the AI style guide (later slice), via style_guide_data()
 *  - the gallery generator (bin/build-gallery.php), which lays out every
 *    component and variant in starters/_gallery.html
 *
 * Everything is keyed on component IDs, never display names. A label can be
 * reworded freely without breaking anything that looks a component up.
 *
 * Placeholder text in skeletons is plain words ("Heading here"). Never square
 * brackets: the sanitiser treats [anything like this] as a shortcode and
 * removes it.
 *
 * Classes may overlap between components. The allowlist is the union of every
 * component's classes plus the shared classes.
 *
 * Variants are modifier classes on a component's root element (for example
 * twd-sk-hero--image). Each variant carries its own complete skeleton.
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
	 * Classes usable on any component: containers, background tones, type,
	 * buttons and cards. They are not tied to one component.
	 */
	public static function shared_classes() {
		return array(
			'twd-sk-inner',
			'twd-sk-inner--narrow',
			'twd-sk-tone-base',
			'twd-sk-tone-surface',
			'twd-sk-tone-tint',
			'twd-sk-tone-band',
			'twd-sk-eyebrow',
			'twd-sk-title',
			'twd-sk-lead',
			'twd-sk-accent',
			'twd-sk-visually-hidden',
			'twd-sk-btn',
			'twd-sk-btn--primary',
			'twd-sk-btn--secondary',
			'twd-sk-btn--outline',
			'twd-sk-btn--light',
			'twd-sk-card',
			'twd-sk-card--accent',
			'twd-sk-card__title',
			'twd-sk-list',
			'twd-sk-gallery-label',
		);
	}

	// -- Skeleton builders (one per component, one branch per variant) ------

	private static function hero( $variant ) {
		if ( 'image' === $variant ) {
			return self::lines( array(
				'<section class="twd-sk-hero twd-sk-hero--image">',
				'<img class="twd-sk-hero__bg" src="/wp-content/uploads/your-image.jpg" alt="">',
				'<div class="twd-sk-hero__overlay"></div>',
				'<div class="twd-sk-hero__content">',
				'<span class="twd-sk-eyebrow">Short label here</span>',
				'<h1 class="twd-sk-hero__title">Main heading here</h1>',
				'<p class="twd-sk-hero__lead">A short line under the heading.</p>',
				'</div>',
				'</section>',
			) );
		}
		if ( 'compact' === $variant ) {
			return self::lines( array(
				'<section class="twd-sk-hero twd-sk-hero--compact twd-sk-tone-base">',
				'<div class="twd-sk-inner">',
				'<span class="twd-sk-eyebrow">Short label here</span>',
				'<h1 class="twd-sk-hero__title">Page heading here</h1>',
				'<p class="twd-sk-hero__lead">An optional short introduction.</p>',
				'</div>',
				'</section>',
			) );
		}
		return self::lines( array(
			'<section class="twd-sk-hero twd-sk-hero--split twd-sk-tone-base">',
			'<div class="twd-sk-inner">',
			'<div class="twd-sk-hero__grid">',
			'<div class="twd-sk-hero__text">',
			'<span class="twd-sk-eyebrow">Short label here</span>',
			'<h1 class="twd-sk-hero__title">Main heading here <span class="twd-sk-accent">with an accent</span></h1>',
			'<p class="twd-sk-hero__lead">One or two sentences introducing the page.</p>',
			'<div class="twd-sk-hero__actions">',
			'<a class="twd-sk-btn twd-sk-btn--primary" href="/contact">Contact me about a first session</a>',
			'<a class="twd-sk-btn twd-sk-btn--outline" href="#twd-sk-services">Find out about my services</a>',
			'</div>',
			'</div>',
			'<figure class="twd-sk-hero__media">',
			'<img class="twd-sk-hero__img" src="/wp-content/uploads/your-image.jpg" alt="Describe the image">',
			'</figure>',
			'</div>',
			'</div>',
			'</section>',
		) );
	}

	private static function text( $variant ) {
		if ( 'aside' === $variant ) {
			return self::lines( array(
				'<section class="twd-sk-text twd-sk-text--aside twd-sk-tone-surface">',
				'<div class="twd-sk-inner">',
				'<div class="twd-sk-text__grid">',
				'<div class="twd-sk-text__body">',
				'<h2 class="twd-sk-title">Section heading here</h2>',
				'<p>Paragraph text here.</p>',
				'<p>Another paragraph here.</p>',
				'</div>',
				'<aside class="twd-sk-text__aside">',
				'<div class="twd-sk-card twd-sk-card--accent">',
				'<h3 class="twd-sk-card__title">Side card heading</h3>',
				'<ul class="twd-sk-list">',
				'<li>First point</li>',
				'<li>Second point</li>',
				'</ul>',
				'</div>',
				'<div class="twd-sk-card">',
				'<h3 class="twd-sk-card__title">Another side card</h3>',
				'<p>Short text here.</p>',
				'</div>',
				'</aside>',
				'</div>',
				'</div>',
				'</section>',
			) );
		}
		return self::lines( array(
			'<section class="twd-sk-text twd-sk-tone-surface">',
			'<div class="twd-sk-inner twd-sk-inner--narrow">',
			'<span class="twd-sk-eyebrow">Short label here</span>',
			'<h2 class="twd-sk-title">Section heading here <span class="twd-sk-accent">with an accent</span></h2>',
			'<div class="twd-sk-text__body">',
			'<p>Paragraph text here.</p>',
			'<p>Another paragraph here.</p>',
			'</div>',
			'</div>',
			'</section>',
		) );
	}

	/**
	 * @param string $modifier '' for the plain row, or reverse, narrow, badge, band.
	 */
	private static function image_text( $modifier ) {
		if ( 'band' === $modifier ) {
			return self::lines( array(
				'<section class="twd-sk-image-text twd-sk-image-text--band">',
				'<img class="twd-sk-image-text__img" src="/wp-content/uploads/your-image.jpg" alt="">',
				'</section>',
			) );
		}
		$root  = 'twd-sk-image-text' . ( '' !== $modifier ? ' twd-sk-image-text--' . $modifier : '' ) . ' twd-sk-tone-surface';
		$badge = ( 'badge' === $modifier )
			? '<img class="twd-sk-image-text__badge" src="/wp-content/uploads/your-badge.png" alt="Describe the badge">'
			: '';
		$media = array(
			'<figure class="twd-sk-image-text__media">',
			'<img class="twd-sk-image-text__img" src="/wp-content/uploads/your-image.jpg" alt="Describe the image">',
		);
		if ( '' !== $badge ) {
			$media[] = $badge;
		}
		$media[] = '</figure>';

		return self::lines( array_merge(
			array(
				'<section class="' . $root . '">',
				'<div class="twd-sk-inner">',
				'<div class="twd-sk-image-text__grid">',
			),
			$media,
			array(
				'<div class="twd-sk-image-text__body">',
				'<span class="twd-sk-eyebrow">Short label here</span>',
				'<h2 class="twd-sk-title">Heading here</h2>',
				'<p>Paragraph text here.</p>',
				'<a class="twd-sk-btn twd-sk-btn--outline" href="/about">Read more about this approach</a>',
				'</div>',
				'</div>',
				'</div>',
				'</section>',
			)
		) );
	}

	private static function quote( $variant ) {
		if ( 'pullout' === $variant ) {
			return self::lines( array(
				'<section class="twd-sk-quote twd-sk-quote--pullout twd-sk-tone-base">',
				'<div class="twd-sk-inner">',
				'<blockquote class="twd-sk-quote__pullout">',
				'<p>A short statement in your own words.</p>',
				'</blockquote>',
				'</div>',
				'</section>',
			) );
		}
		return self::lines( array(
			'<section class="twd-sk-quote twd-sk-quote--cards twd-sk-tone-base">',
			'<div class="twd-sk-inner">',
			'<h2 class="twd-sk-title">What people say</h2>',
			'<div class="twd-sk-quote__grid">',
			'<figure class="twd-sk-quote__card">',
			'<blockquote class="twd-sk-quote__text">',
			'<p>Quote text here.</p>',
			'</blockquote>',
			'<figcaption class="twd-sk-quote__cite">Name and context</figcaption>',
			'</figure>',
			'<figure class="twd-sk-quote__card">',
			'<blockquote class="twd-sk-quote__text">',
			'<p>Another quote here.</p>',
			'</blockquote>',
			'<figcaption class="twd-sk-quote__cite">Name and context</figcaption>',
			'</figure>',
			'</div>',
			'<p class="twd-sk-quote__note">A short note about consent or anonymity.</p>',
			'</div>',
			'</section>',
		) );
	}

	private static function faq() {
		return self::lines( array(
			'<section class="twd-sk-faq twd-sk-tone-surface">',
			'<div class="twd-sk-inner twd-sk-inner--narrow">',
			'<h2 class="twd-sk-title">Frequently asked questions</h2>',
			'<div class="twd-sk-faq__list">',
			'<details class="twd-sk-faq__item">',
			'<summary class="twd-sk-faq__question">Question here?</summary>',
			'<div class="twd-sk-faq__answer">',
			'<p>Answer here.</p>',
			'</div>',
			'</details>',
			'<details class="twd-sk-faq__item">',
			'<summary class="twd-sk-faq__question">Another question here?</summary>',
			'<div class="twd-sk-faq__answer">',
			'<p>Another answer here.</p>',
			'</div>',
			'</details>',
			'</div>',
			'</div>',
			'</section>',
		) );
	}

	private static function services( $variant ) {
		if ( 'pills' === $variant ) {
			return self::lines( array(
				'<section class="twd-sk-services twd-sk-services--pills twd-sk-tone-base">',
				'<div class="twd-sk-inner twd-sk-inner--narrow">',
				'<span class="twd-sk-eyebrow">I can help with</span>',
				'<ul class="twd-sk-services__pills">',
				'<li class="twd-sk-services__pill">Topic one</li>',
				'<li class="twd-sk-services__pill">Topic two</li>',
				'<li class="twd-sk-services__pill">Topic three</li>',
				'<li class="twd-sk-services__pill">Topic four</li>',
				'<li class="twd-sk-services__pill">and more</li>',
				'</ul>',
				'</div>',
				'</section>',
			) );
		}
		$item = array(
			'<a class="twd-sk-card twd-sk-card--accent twd-sk-services__item" href="/service-%d">',
			'<h3 class="twd-sk-services__name">Service name here</h3>',
			'<p class="twd-sk-services__desc">Short description here.</p>',
			'</a>',
		);
		$items = array();
		for ( $i = 1; $i <= 4; $i++ ) {
			foreach ( $item as $line ) {
				$items[] = sprintf( $line, $i );
			}
		}
		return self::lines( array_merge(
			array(
				'<section class="twd-sk-services twd-sk-services--cards twd-sk-tone-tint" id="twd-sk-services">',
				'<div class="twd-sk-inner">',
				'<span class="twd-sk-eyebrow">Short label here</span>',
				'<h2 class="twd-sk-title">What I offer <span class="twd-sk-accent">to you</span></h2>',
				'<div class="twd-sk-services__list">',
			),
			$items,
			array(
				'</div>',
				'</div>',
				'</section>',
			)
		) );
	}

	private static function steps() {
		$items = array();
		foreach ( array( 'First', 'Second', 'Third' ) as $n ) {
			$items[] = '<li class="twd-sk-steps__item">';
			$items[] = '<h3 class="twd-sk-steps__name">' . $n . ' step</h3>';
			$items[] = '<p class="twd-sk-steps__text">Short description here.</p>';
			$items[] = '</li>';
		}
		return self::lines( array_merge(
			array(
				'<section class="twd-sk-steps twd-sk-tone-surface">',
				'<div class="twd-sk-inner">',
				'<span class="twd-sk-eyebrow">Getting started</span>',
				'<h2 class="twd-sk-title">How it <span class="twd-sk-accent">works</span></h2>',
				'<ol class="twd-sk-steps__list">',
			),
			$items,
			array(
				'</ol>',
				'</div>',
				'</section>',
			)
		) );
	}

	private static function cta( $variant ) {
		if ( 'strip' === $variant ) {
			return self::lines( array(
				'<section class="twd-sk-cta twd-sk-cta--strip twd-sk-tone-band">',
				'<div class="twd-sk-inner twd-sk-inner--narrow twd-sk-cta__inner">',
				'<p class="twd-sk-cta__text">A short invitation to get in touch.</p>',
				'<div class="twd-sk-cta__actions">',
				'<a class="twd-sk-btn twd-sk-btn--light" href="/contact">Contact me about a first session</a>',
				'</div>',
				'</div>',
				'</section>',
			) );
		}
		return self::lines( array(
			'<section class="twd-sk-cta twd-sk-cta--band">',
			'<img class="twd-sk-cta__bg" src="/wp-content/uploads/your-image.jpg" alt="">',
			'<div class="twd-sk-cta__overlay"></div>',
			'<div class="twd-sk-inner twd-sk-inner--narrow twd-sk-cta__inner">',
			'<h2 class="twd-sk-cta__title">Heading inviting contact <span class="twd-sk-accent">with an accent</span></h2>',
			'<p class="twd-sk-cta__text">One or two sentences inviting contact.</p>',
			'<div class="twd-sk-cta__actions">',
			'<a class="twd-sk-btn twd-sk-btn--primary" href="mailto:you@example.com">you@example.com</a>',
			'<a class="twd-sk-btn twd-sk-btn--light" href="tel:PHONE_NUMBER">Call me to arrange a first session</a>',
			'</div>',
			'<p class="twd-sk-cta__note">A short reassurance line.</p>',
			'</div>',
			'</section>',
		) );
	}

	private static function contact( $variant ) {
		$intro = array(
			'<div class="twd-sk-contact__intro">',
			'<span class="twd-sk-eyebrow">Get in touch</span>',
			'<h2 class="twd-sk-title">Reaching out is the <span class="twd-sk-accent">first step</span></h2>',
			'<p class="twd-sk-lead">A short, reassuring introduction here.</p>',
			'<div class="twd-sk-card twd-sk-contact__card">',
			'<dl class="twd-sk-contact__list">',
			'<div class="twd-sk-contact__row">',
			'<dt class="twd-sk-contact__label">Email</dt>',
			'<dd class="twd-sk-contact__value"><a href="mailto:you@example.com">you@example.com</a></dd>',
			'</div>',
			'<div class="twd-sk-contact__row">',
			'<dt class="twd-sk-contact__label">Phone</dt>',
			'<dd class="twd-sk-contact__value"><a href="tel:PHONE_NUMBER">Call me to arrange a first session</a></dd>',
			'</div>',
			'<div class="twd-sk-contact__row">',
			'<dt class="twd-sk-contact__label">Sessions</dt>',
			'<dd class="twd-sk-contact__value">Where and how sessions take place</dd>',
			'</div>',
			'</dl>',
			'</div>',
			'</div>',
		);
		if ( 'split' !== $variant ) {
			return self::lines( array_merge(
				array(
					'<section class="twd-sk-contact twd-sk-tone-base">',
					'<div class="twd-sk-inner">',
					'<div class="twd-sk-contact__grid">',
				),
				$intro,
				array(
					'</div>',
					'</div>',
					'</section>',
				)
			) );
		}
		return self::lines( array_merge(
			array(
				'<section class="twd-sk-contact twd-sk-contact--split twd-sk-tone-base">',
				'<div class="twd-sk-inner">',
				'<div class="twd-sk-contact__grid">',
			),
			$intro,
			array(
				'<div class="twd-sk-contact__aside">',
				'<figure class="twd-sk-contact__media">',
				'<img class="twd-sk-contact__img" src="/wp-content/uploads/your-image.jpg" alt="Describe the image">',
				'</figure>',
				'<div class="twd-sk-card twd-sk-contact__address">',
				'<h3 class="twd-sk-card__title">Address</h3>',
				'<p>Address line one<br>Address line two<br>Town or city<br>Postcode</p>',
				'<a class="twd-sk-btn twd-sk-btn--outline" href="https://www.example.com/directions">Get directions</a>',
				'</div>',
				'</div>',
				'</div>',
				'</div>',
				'</section>',
			)
		) );
	}

	/**
	 * Safety notice plus helpline modal. Pure CSS: the button is a link to
	 * #twd-sk-helplines and the modal shows with :target. No JavaScript.
	 *
	 * These national services are public, but check every number and opening
	 * time against the official sources before any site launches (see HISTORY.md).
	 */
	private static function notice() {
		return self::lines( array(
			'<section class="twd-sk-notice twd-sk-tone-tint" id="twd-sk-notice">',
			'<div class="twd-sk-inner twd-sk-inner--narrow twd-sk-notice__inner">',
			'<h2 class="twd-sk-notice__title">Before you reach out</h2>',
			'<p>This is not an emergency or crisis service. If you are in crisis or at risk of harm right now, please contact one of the services below. They are there around the clock.</p>',
			'<a class="twd-sk-btn twd-sk-btn--primary" href="#twd-sk-helplines">View UK support lines</a>',
			'</div>',
			'</section>',
			'<div class="twd-sk-modal" id="twd-sk-helplines">',
			'<a class="twd-sk-modal__overlay" href="#twd-sk-notice"><span class="twd-sk-visually-hidden">Close</span></a>',
			'<div class="twd-sk-modal__content">',
			'<a class="twd-sk-modal__close" href="#twd-sk-notice"><span class="twd-sk-visually-hidden">Close</span></a>',
			'<h2 class="twd-sk-modal__title">UK support lines</h2>',
			'<p class="twd-sk-modal__intro">If you are in immediate danger, please call 999.</p>',
			'<ul class="twd-sk-modal__list">',
			'<li class="twd-sk-modal__item">',
			'<h3 class="twd-sk-modal__name">Samaritans</h3>',
			'<p>Call <a href="tel:116123">116 123</a>, free, 24 hours a day, for anyone struggling to cope.</p>',
			'</li>',
			'<li class="twd-sk-modal__item">',
			'<h3 class="twd-sk-modal__name">Shout</h3>',
			'<p>Text SHOUT to 85258, free 24/7 text support for anyone in crisis.</p>',
			'</li>',
			'<li class="twd-sk-modal__item">',
			'<h3 class="twd-sk-modal__name">NHS 111</h3>',
			'<p>Call <a href="tel:111">111</a> and select the mental health option for urgent advice and support.</p>',
			'</li>',
			'<li class="twd-sk-modal__item">',
			'<h3 class="twd-sk-modal__name">CALM (Campaign Against Living Miserably)</h3>',
			'<p>Call <a href="tel:0800585858">0800 58 58 58</a>, 5pm to midnight, every day.</p>',
			'</li>',
			'<li class="twd-sk-modal__item">',
			'<h3 class="twd-sk-modal__name">Emergency services</h3>',
			'<p>Call <a href="tel:999">999</a> if you or someone else is in immediate danger.</p>',
			'</li>',
			'</ul>',
			'</div>',
			'</div>',
		) );
	}

	private static function resources() {
		return self::lines( array(
			'<section class="twd-sk-resources twd-sk-tone-base">',
			'<div class="twd-sk-inner">',
			'<span class="twd-sk-eyebrow">Resources</span>',
			'<h2 class="twd-sk-title">Latest articles</h2>',
			'<p class="twd-sk-lead">Short introduction here.</p>',
			'[twd_articles count="6" columns="3"]',
			'</div>',
			'</section>',
		) );
	}

	/**
	 * All components, keyed by ID, in page order.
	 *
	 * Each entry: id, label, description, skeleton (the default variant),
	 * variants (modifier class => label and skeleton, empty if the component
	 * has none), classes (every class the component may use, modifiers
	 * included), shortcodes.
	 *
	 * @return array
	 */
	public static function all() {
		static $components = null;
		if ( null !== $components ) {
			return $components;
		}

		$components = array(

			'hero'       => array(
				'id'          => 'hero',
				'label'       => 'Hero',
				'description' => 'The top of the page. The only place the page heading (h1) may appear, and only once.',
				'skeleton'    => self::hero( 'split' ),
				'variants'    => array(
					'twd-sk-hero--split'   => array(
						'label'    => 'Split: text with an image beside it',
						'skeleton' => self::hero( 'split' ),
					),
					'twd-sk-hero--image'   => array(
						'label'    => 'Image: full-width picture with centred text over it',
						'skeleton' => self::hero( 'image' ),
					),
					'twd-sk-hero--compact' => array(
						'label'    => 'Compact: a page title band, no image',
						'skeleton' => self::hero( 'compact' ),
					),
				),
				'classes'     => array(
					'twd-sk-hero',
					'twd-sk-hero--split',
					'twd-sk-hero--image',
					'twd-sk-hero--compact',
					'twd-sk-hero__grid',
					'twd-sk-hero__text',
					'twd-sk-hero__title',
					'twd-sk-hero__lead',
					'twd-sk-hero__actions',
					'twd-sk-hero__media',
					'twd-sk-hero__img',
					'twd-sk-hero__bg',
					'twd-sk-hero__overlay',
					'twd-sk-hero__content',
				),
				'shortcodes'  => array(),
			),

			'text'       => array(
				'id'          => 'text',
				'label'       => 'Text',
				'description' => 'A heading and paragraphs. The aside variant adds cards in a side column.',
				'skeleton'    => self::text( '' ),
				'variants'    => array(
					'twd-sk-text--aside' => array(
						'label'    => 'Aside: text with cards in a side column',
						'skeleton' => self::text( 'aside' ),
					),
				),
				'classes'     => array(
					'twd-sk-text',
					'twd-sk-text--aside',
					'twd-sk-text__grid',
					'twd-sk-text__body',
					'twd-sk-text__aside',
				),
				'shortcodes'  => array(),
			),

			'image_text' => array(
				'id'          => 'image_text',
				'label'       => 'Image and text',
				'description' => 'A picture beside a short piece of text. Also used for a personal bio. The band variant is a full-width picture with no text.',
				'skeleton'    => self::image_text( '' ),
				'variants'    => array(
					'twd-sk-image-text--reverse' => array(
						'label'    => 'Reverse: picture on the right',
						'skeleton' => self::image_text( 'reverse' ),
					),
					'twd-sk-image-text--narrow'  => array(
						'label'    => 'Narrow: a smaller picture column',
						'skeleton' => self::image_text( 'narrow' ),
					),
					'twd-sk-image-text--badge'   => array(
						'label'    => 'Badge: a small badge image under the picture',
						'skeleton' => self::image_text( 'badge' ),
					),
					'twd-sk-image-text--band'    => array(
						'label'    => 'Band: a full-width picture, no text',
						'skeleton' => self::image_text( 'band' ),
					),
				),
				'classes'     => array(
					'twd-sk-image-text',
					'twd-sk-image-text--reverse',
					'twd-sk-image-text--narrow',
					'twd-sk-image-text--badge',
					'twd-sk-image-text--band',
					'twd-sk-image-text__grid',
					'twd-sk-image-text__media',
					'twd-sk-image-text__img',
					'twd-sk-image-text__badge',
					'twd-sk-image-text__body',
				),
				'shortcodes'  => array(),
			),

			'quote'      => array(
				'id'          => 'quote',
				'label'       => 'Quote or testimonial',
				'description' => 'A group of quote cards, or one boxed statement. Only use real quotes, with permission.',
				'skeleton'    => self::quote( 'cards' ),
				'variants'    => array(
					'twd-sk-quote--cards'   => array(
						'label'    => 'Cards: a group of quote cards',
						'skeleton' => self::quote( 'cards' ),
					),
					'twd-sk-quote--pullout' => array(
						'label'    => 'Pullout: one boxed statement',
						'skeleton' => self::quote( 'pullout' ),
					),
				),
				'classes'     => array(
					'twd-sk-quote',
					'twd-sk-quote--cards',
					'twd-sk-quote--pullout',
					'twd-sk-quote__grid',
					'twd-sk-quote__card',
					'twd-sk-quote__text',
					'twd-sk-quote__cite',
					'twd-sk-quote__note',
					'twd-sk-quote__pullout',
				),
				'shortcodes'  => array(),
			),

			'faq'        => array(
				'id'          => 'faq',
				'label'       => 'FAQ',
				'description' => 'Questions that open and close. Pure HTML, no JavaScript.',
				'skeleton'    => self::faq(),
				'variants'    => array(),
				'classes'     => array(
					'twd-sk-faq',
					'twd-sk-faq__list',
					'twd-sk-faq__item',
					'twd-sk-faq__question',
					'twd-sk-faq__answer',
				),
				'shortcodes'  => array(),
			),

			'services'   => array(
				'id'          => 'services',
				'label'       => 'Services list',
				'description' => 'What is offered, as linked cards, or as a row of short topic pills.',
				'skeleton'    => self::services( 'cards' ),
				'variants'    => array(
					'twd-sk-services--cards' => array(
						'label'    => 'Cards: linked cards with a short description',
						'skeleton' => self::services( 'cards' ),
					),
					'twd-sk-services--pills' => array(
						'label'    => 'Pills: a row of short topics',
						'skeleton' => self::services( 'pills' ),
					),
				),
				'classes'     => array(
					'twd-sk-services',
					'twd-sk-services--cards',
					'twd-sk-services--pills',
					'twd-sk-services__list',
					'twd-sk-services__item',
					'twd-sk-services__name',
					'twd-sk-services__desc',
					'twd-sk-services__pills',
					'twd-sk-services__pill',
				),
				'shortcodes'  => array(),
			),

			'steps'      => array(
				'id'          => 'steps',
				'label'       => 'Steps',
				'description' => 'A short numbered list of how to get started. The numbers are added automatically.',
				'skeleton'    => self::steps(),
				'variants'    => array(),
				'classes'     => array(
					'twd-sk-steps',
					'twd-sk-steps__list',
					'twd-sk-steps__item',
					'twd-sk-steps__name',
					'twd-sk-steps__text',
				),
				'shortcodes'  => array(),
			),

			'cta'        => array(
				'id'          => 'cta',
				'label'       => 'Call to action',
				'description' => 'An invitation to get in touch. A centred band (optionally over a picture) or a slim strip with text and a button.',
				'skeleton'    => self::cta( 'band' ),
				'variants'    => array(
					'twd-sk-cta--band'  => array(
						'label'    => 'Band: centred heading and buttons, optional picture behind',
						'skeleton' => self::cta( 'band' ),
					),
					'twd-sk-cta--strip' => array(
						'label'    => 'Strip: one line of text and a button',
						'skeleton' => self::cta( 'strip' ),
					),
				),
				'classes'     => array(
					'twd-sk-cta',
					'twd-sk-cta--band',
					'twd-sk-cta--strip',
					'twd-sk-cta__bg',
					'twd-sk-cta__overlay',
					'twd-sk-cta__inner',
					'twd-sk-cta__title',
					'twd-sk-cta__text',
					'twd-sk-cta__actions',
					'twd-sk-cta__note',
				),
				'shortcodes'  => array(),
			),

			'contact'    => array(
				'id'          => 'contact',
				'label'       => 'Contact details',
				'description' => 'Contact details as label and value rows. The split variant adds a side column with a picture and an address. A contact form is not part of this: a form stays an Elementor widget placed beside the page content.',
				'skeleton'    => self::contact( '' ),
				'variants'    => array(
					'twd-sk-contact--split' => array(
						'label'    => 'Split: adds a side column with a picture and an address',
						'skeleton' => self::contact( 'split' ),
					),
				),
				'classes'     => array(
					'twd-sk-contact',
					'twd-sk-contact--split',
					'twd-sk-contact__grid',
					'twd-sk-contact__intro',
					'twd-sk-contact__card',
					'twd-sk-contact__list',
					'twd-sk-contact__row',
					'twd-sk-contact__label',
					'twd-sk-contact__value',
					'twd-sk-contact__aside',
					'twd-sk-contact__media',
					'twd-sk-contact__img',
					'twd-sk-contact__address',
				),
				'shortcodes'  => array(),
			),

			'notice'     => array(
				'id'          => 'notice',
				'label'       => 'Safety notice with support lines',
				'description' => 'A short note that the service is not an emergency one, with a button that opens a list of UK support lines. Pure CSS, no JavaScript. The helpline details must be checked against official sources before any site launches.',
				'skeleton'    => self::notice(),
				'variants'    => array(),
				'classes'     => array(
					'twd-sk-notice',
					'twd-sk-notice__inner',
					'twd-sk-notice__title',
					'twd-sk-modal',
					'twd-sk-modal__overlay',
					'twd-sk-modal__content',
					'twd-sk-modal__close',
					'twd-sk-modal__title',
					'twd-sk-modal__intro',
					'twd-sk-modal__list',
					'twd-sk-modal__item',
					'twd-sk-modal__name',
				),
				'shortcodes'  => array(),
			),

			'resources'  => array(
				'id'          => 'resources',
				'label'       => 'Resources',
				'description' => 'A heading above the articles grid. Only wraps the twd_articles shortcode.',
				'skeleton'    => self::resources(),
				'variants'    => array(),
				'classes'     => array(
					'twd-sk-resources',
				),
				'shortcodes'  => array(
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
	 * Every class a page may use, as class => true: shared classes plus every
	 * component's classes.
	 */
	public static function allowed_classes() {
		$out = array();
		foreach ( self::shared_classes() as $class ) {
			$out[ $class ] = true;
		}
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
	 * Plain data for the AI style guide (used from a later slice). Nothing rendered.
	 */
	public static function style_guide_data() {
		$components = array();
		foreach ( self::all() as $id => $component ) {
			$variants = array();
			foreach ( $component['variants'] as $class => $variant ) {
				$variants[ $class ] = array(
					'label'    => $variant['label'],
					'skeleton' => $variant['skeleton'],
				);
			}
			$components[ $id ] = array(
				'id'          => $component['id'],
				'label'       => $component['label'],
				'description' => $component['description'],
				'skeleton'    => $component['skeleton'],
				'variants'    => $variants,
				'classes'     => $component['classes'],
				'shortcodes'  => array_keys( $component['shortcodes'] ),
			);
		}
		return array(
			'components' => $components,
			'shared'     => self::shared_classes(),
		);
	}
}
