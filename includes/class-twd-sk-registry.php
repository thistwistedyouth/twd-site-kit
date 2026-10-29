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

	/** The line shown while the articles grid is empty (0.15.0): the articles plugin's own plain words. */
	const NO_ARTICLES_LINE = '<p class="twd-sk-resources__none">No articles yet. Please check back soon.</p>';


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
			// 0.15.0: space above and below a section (Look panel; no class is the normal spacing).
			'twd-sk-space-top-0',
			'twd-sk-space-top-sm',
			'twd-sk-space-top-lg',
			'twd-sk-space-bottom-0',
			'twd-sk-space-bottom-sm',
			'twd-sk-space-bottom-lg',
			// 0.15.0: a quote line (accent rule on the left) on any paragraph, toggled in the Visual editor.
			'twd-sk-quoteline',
			// 0.15.0: words in a frame over a picture (the picture text layout and the call to action band):
			// the frame and its tint, its padding, the overlay over the picture, and larger body text.
			'twd-sk-frame',
			'twd-sk-frame--light',
			'twd-sk-frame--strong',
			'twd-sk-frame--pad-sm',
			'twd-sk-frame--pad-lg',
			'twd-sk-overlay-light',
			'twd-sk-overlay-strong',
			'twd-sk-overlay-fade',
			'twd-sk-text-lg',
			// 0.15.0: a box round a section's content (a soft card or an outline), a border, and the padding inside.
			'twd-sk-box-card',
			'twd-sk-box-outline',
			'twd-sk-border-thin',
			'twd-sk-border-accent',
			'twd-sk-pad-compact',
			'twd-sk-pad-roomy',
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
		if ( 'image' === $variant || 'parallax' === $variant ) {
			// Parallax (0.14.0): the same full-width picture, moving a little slower than the page when a
			// small script is loaded; still without it, on phones, and for visitors who ask for less motion.
			return self::lines( array(
				'<section class="twd-sk-hero twd-sk-hero--image' . ( 'parallax' === $variant ? ' twd-sk-hero--parallax' : '' ) . '">',
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
		if ( 'picture' === $variant ) {
			// 0.15.0: a picture behind the whole section (covering it, never repeating) under a graduated
			// overlay, with the words in a centred frame (twd-sk-frame: a dark tint, a padding choice).
			return self::lines( array(
				'<section class="twd-sk-text twd-sk-text--picture twd-sk-frame">',
				'<img class="twd-sk-text__bg" src="/wp-content/uploads/your-image.jpg" alt="">',
				'<div class="twd-sk-text__overlay"></div>',
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
		if ( 'prose' === $variant ) {
			// 0.14.0: a reading column: narrow, left aligned, room between paragraphs, for pages where the writing leads.
			return self::lines( array(
				'<section class="twd-sk-text twd-sk-text--prose twd-sk-tone-base">',
				'<div class="twd-sk-inner twd-sk-inner--narrow">',
				'<h2 class="twd-sk-title">Section heading here</h2>',
				'<div class="twd-sk-text__body">',
				'<p>Paragraph text here.</p>',
				'<p>Another paragraph here.</p>',
				'</div>',
				'</div>',
				'</section>',
			) );
		}
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
		if ( 'slider' === $variant ) {
			$cards = array();
			foreach ( array( 'Quote text here.', 'Another quote here.', 'A third quote here.' ) as $q ) {
				$cards[] = '<figure class="twd-sk-quote__card">';
				$cards[] = '<blockquote class="twd-sk-quote__text">';
				$cards[] = '<p>' . $q . '</p>';
				$cards[] = '</blockquote>';
				$cards[] = '<figcaption class="twd-sk-quote__cite">Name and context</figcaption>';
				$cards[] = '</figure>';
			}
			// One quote at a time: swipe on a phone; arrows and dots are added by twd-site-kit-slider.js.
			return self::lines( array_merge(
				array(
					'<section class="twd-sk-quote twd-sk-quote--slider twd-sk-tone-base">',
					'<div class="twd-sk-inner">',
					'<h2 class="twd-sk-title">What people say</h2>',
					'<div class="twd-sk-quote__track">',
				),
				$cards,
				array(
					'</div>',
					'<p class="twd-sk-quote__note">A short note about consent or anonymity.</p>',
					'</div>',
					'</section>',
				)
			) );
		}
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

	/**
	 * @param string $variant '' for one list of questions, 'groups' for grouped questions (0.14.0): a row of
	 *                        links to each group, one group shown at a time on a wide screen (pure CSS, :target,
	 *                        the group marked --default shows first), every group stacked on a phone.
	 */
	private static function faq( $variant = '' ) {
		if ( 'groups' === $variant ) {
			$tabs   = array();
			$groups = array();
			foreach ( array( 1, 2, 3 ) as $n ) {
				$tabs[]   = '<li class="twd-sk-faq__tab"><a class="twd-sk-faq__tablink" href="#twd-sk-questions-' . $n . '">Group heading here</a></li>';
				$groups[] = '<div class="twd-sk-faq__group' . ( 1 === $n ? ' twd-sk-faq__group--default' : '' ) . '" id="twd-sk-questions-' . $n . '">';
				$groups[] = '<h3 class="twd-sk-faq__groupname">Group heading here</h3>';
				$groups[] = '<div class="twd-sk-faq__list">';
				$groups[] = '<details class="twd-sk-faq__item">';
				$groups[] = '<summary class="twd-sk-faq__question">Question here?</summary>';
				$groups[] = '<div class="twd-sk-faq__answer">';
				$groups[] = '<p>Answer here.</p>';
				$groups[] = '</div>';
				$groups[] = '</details>';
				$groups[] = '</div>';
				$groups[] = '</div>';
			}
			return self::lines( array_merge(
				array(
					'<section class="twd-sk-faq twd-sk-faq--groups twd-sk-tone-surface" id="twd-sk-questions">',
					'<div class="twd-sk-inner twd-sk-inner--narrow">',
					'<h2 class="twd-sk-title">Frequently asked questions</h2>',
					'<ul class="twd-sk-faq__tabs">',
				),
				$tabs,
				array(
					'</ul>',
					'<div class="twd-sk-faq__groups">',
				),
				$groups,
				array(
					'</div>',
					'</div>',
					'</section>',
				)
			) );
		}
		return self::lines( array(
			'<section class="twd-sk-faq' . ( 'two' === $variant ? ' twd-sk-faq--two' : '' ) . ' twd-sk-tone-surface">',
			'<div class="twd-sk-inner' . ( 'two' === $variant ? '' : ' twd-sk-inner--narrow' ) . '">',
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
		if ( 'glow' === $variant ) {
			$items = array();
			foreach ( array( 1, 2, 3 ) as $i ) {
				$items[] = '<div class="twd-sk-card twd-sk-services__item">';
				$items[] = '<h3 class="twd-sk-services__name">Service name here</h3>';
				$items[] = '<p class="twd-sk-services__desc">Short description here.</p>';
				$items[] = '</div>';
			}
			return self::lines( array_merge(
				array(
					'<section class="twd-sk-services twd-sk-services--glow twd-sk-tone-base">',
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
		if ( 'links' === $variant ) {
			// Links (0.14.0): a plain list of linked rows, one per page, for a site with many services.
			$rows = array();
			for ( $i = 1; $i <= 4; $i++ ) {
				$rows[] = '<li class="twd-sk-services__row"><a class="twd-sk-services__link" href="/service-' . $i . '">';
				$rows[] = '<span class="twd-sk-services__name">Service name here</span>';
				$rows[] = '<span class="twd-sk-services__desc">Short description here.</span>';
				$rows[] = '</a></li>';
			}
			return self::lines( array_merge(
				array(
					'<section class="twd-sk-services twd-sk-services--links twd-sk-tone-base">',
					'<div class="twd-sk-inner twd-sk-inner--narrow">',
					'<span class="twd-sk-eyebrow">Short label here</span>',
					'<h2 class="twd-sk-title">What I offer <span class="twd-sk-accent">to you</span></h2>',
					'<ul class="twd-sk-services__links">',
				),
				$rows,
				array(
					'</ul>',
					'</div>',
					'</section>',
				)
			) );
		}
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

	private static function steps( $variant = '' ) {
		$items = array();
		foreach ( array( 'First', 'Second', 'Third' ) as $n ) {
			$items[] = '<li class="twd-sk-steps__item">';
			$items[] = '<h3 class="twd-sk-steps__name">' . $n . ' step</h3>';
			$items[] = '<p class="twd-sk-steps__text">Short description here.</p>';
			$items[] = '</li>';
		}
		return self::lines( array_merge(
			array(
				'<section class="twd-sk-steps' . ( 'timeline' === $variant ? ' twd-sk-steps--timeline' : '' ) . ' twd-sk-tone-surface">',
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

	/**
	 * A grid of pictures with short captions (0.8.0).
	 *
	 * @param string $variant '' for three across, 'two' for two large pictures across.
	 */
	private static function pictures( $variant ) {
		$root  = 'twd-sk-pictures' . ( 'two' === $variant ? ' twd-sk-pictures--two' : '' ) . ' twd-sk-tone-base';
		$count = 'two' === $variant ? 2 : 3;
		$items = array();
		for ( $i = 0; $i < $count; $i++ ) {
			$items[] = '<figure class="twd-sk-pictures__item">';
			$items[] = '<img class="twd-sk-pictures__img" src="/wp-content/uploads/your-image.jpg" alt="Describe the image">';
			$items[] = '<figcaption class="twd-sk-pictures__caption">Caption here</figcaption>';
			$items[] = '</figure>';
		}
		return self::lines( array_merge(
			array(
				'<section class="' . $root . '">',
				'<div class="twd-sk-inner">',
				'<h2 class="twd-sk-title">Heading here</h2>',
				'<div class="twd-sk-pictures__grid">',
			),
			$items,
			array(
				'</div>',
				'</div>',
				'</section>',
			)
		) );
	}

	/** A strip of small logos: accreditations, memberships, partners (0.8.0). */
	private static function logos( $variant = '' ) {
		$items = array();
		for ( $i = 0; $i < 4; $i++ ) {
			$items[] = '<li class="twd-sk-logos__item"><img class="twd-sk-logos__img" src="/wp-content/uploads/your-logo.png" alt="Describe the logo"></li>';
		}
		return self::lines( array_merge(
			array(
				'<section class="twd-sk-logos' . ( 'grid' === $variant ? ' twd-sk-logos--grid' : '' ) . ' twd-sk-tone-surface">',
				'<div class="twd-sk-inner">',
				'<span class="twd-sk-eyebrow">Short label here</span>',
				'<ul class="twd-sk-logos__list">',
			),
			$items,
			array(
				'</ul>',
				'</div>',
				'</section>',
			)
		) );
	}

	/**
	 * Prices (0.8.0).
	 *
	 * @param string $variant '' for cards, 'list' for a simple list of names and prices.
	 */
	private static function pricing( $variant ) {
		if ( 'list' === $variant ) {
			$rows = array();
			for ( $i = 0; $i < 3; $i++ ) {
				$rows[] = '<div class="twd-sk-pricing__row"><dt class="twd-sk-pricing__name">Service name here</dt><dd class="twd-sk-pricing__amount">Price here</dd></div>';
			}
			return self::lines( array_merge(
				array(
					'<section class="twd-sk-pricing twd-sk-pricing--list twd-sk-tone-surface">',
					'<div class="twd-sk-inner twd-sk-inner--narrow">',
					'<h2 class="twd-sk-title">Heading here</h2>',
					'<dl class="twd-sk-pricing__list">',
				),
				$rows,
				array(
					'</dl>',
					'<p class="twd-sk-pricing__note">Short note here.</p>',
					'</div>',
					'</section>',
				)
			) );
		}
		$cards = array();
		for ( $i = 0; $i < 3; $i++ ) {
			$cards[] = '<div class="twd-sk-card' . ( 1 === $i ? ' twd-sk-card--accent' : '' ) . ' twd-sk-pricing__card">';
			$cards[] = '<h3 class="twd-sk-card__title">Service name here</h3>';
			$cards[] = '<p class="twd-sk-pricing__price">Price here</p>';
			$cards[] = '<p>Short description here.</p>';
			$cards[] = '<ul class="twd-sk-list">';
			$cards[] = '<li>First point</li>';
			$cards[] = '<li>Second point</li>';
			$cards[] = '</ul>';
			$cards[] = '<a class="twd-sk-btn twd-sk-btn--outline" href="/contact">Ask about this</a>';
			$cards[] = '</div>';
		}
		return self::lines( array_merge(
			array(
				'<section class="twd-sk-pricing twd-sk-tone-surface">',
				'<div class="twd-sk-inner">',
				'<span class="twd-sk-eyebrow">Short label here</span>',
				'<h2 class="twd-sk-title">Heading here</h2>',
				'<div class="twd-sk-pricing__grid">',
			),
			$cards,
			array(
				'</div>',
				'<p class="twd-sk-pricing__note">Short note here.</p>',
				'</div>',
				'</section>',
			)
		) );
	}

	/** Opening hours as day and time rows (0.8.0). */
	private static function hours( $variant = '' ) {
		$rows = array();
		for ( $i = 0; $i < 3; $i++ ) {
			$rows[] = '<div class="twd-sk-hours__row"><dt class="twd-sk-hours__day">Day here</dt><dd class="twd-sk-hours__time">Times here</dd></div>';
		}
		return self::lines( array_merge(
			array(
				'<section class="twd-sk-hours' . ( 'card' === $variant ? ' twd-sk-hours--card twd-sk-tone-tint' : ' twd-sk-tone-base' ) . '">',
				'<div class="twd-sk-inner twd-sk-inner--narrow">',
				'<h2 class="twd-sk-title">Heading here</h2>',
				'<dl class="twd-sk-hours__list">',
			),
			$rows,
			array(
				'</dl>',
				'<p class="twd-sk-hours__note">Short note here.</p>',
				'</div>',
				'</section>',
			)
		) );
	}

	/** Areas covered, with a link to a map (no embedded map: no iframes) (0.8.0). */
	private static function areas( $variant = '' ) {
		$items = array();
		for ( $i = 0; $i < 4; $i++ ) {
			$items[] = '<li class="twd-sk-areas__item">Area name here</li>';
		}
		return self::lines( array_merge(
			array(
				'<section class="twd-sk-areas' . ( 'split' === $variant ? ' twd-sk-areas--split' : '' ) . ' twd-sk-tone-tint">',
				'<div class="twd-sk-inner">',
				'<h2 class="twd-sk-title">Heading here</h2>',
				'<p class="twd-sk-lead">Short introduction here.</p>',
				'<ul class="twd-sk-areas__list">',
			),
			$items,
			array(
				'</ul>',
				'<div class="twd-sk-areas__actions">',
				'<a class="twd-sk-btn twd-sk-btn--outline" href="https://www.example.com/map">See the area on a map</a>',
				'</div>',
				'</div>',
				'</section>',
			)
		) );
	}

	/** An enquiry form: a heading, a line of introduction and the [twd_enquiry] shortcode (0.8.1). */
	private static function enquiry() {
		return self::lines( array(
			'<section class="twd-sk-enquiry twd-sk-tone-surface">',
			'<div class="twd-sk-inner twd-sk-inner--narrow">',
			'<h2 class="twd-sk-title">Heading here</h2>',
			'<p class="twd-sk-lead">Short introduction here.</p>',
			'[twd_enquiry]',
			'</div>',
			'</section>',
		) );
	}

	/**
	 * An embed: a heading above, or nothing but, the code an administrator saved apart from the page
	 * and named by number (0.15.0). The number 0 is the sample: it is flagged until a real embed is chosen.
	 */
	private static function embed( $variant = '' ) {
		if ( 'twd-sk-embed--bare' === $variant ) {
			return self::lines( array(
				'<section class="twd-sk-embed twd-sk-embed--bare twd-sk-tone-base">',
				'<div class="twd-sk-inner">',
				'[twd_embed id="0"]',
				'</div>',
				'</section>',
			) );
		}
		return self::lines( array(
			'<section class="twd-sk-embed twd-sk-tone-base">',
			'<div class="twd-sk-inner">',
			'<h2 class="twd-sk-title">Heading here</h2>',
			'[twd_embed id="0"]',
			'</div>',
			'</section>',
		) );
	}

	private static function resources( $variant = '' ) {
		if ( 'strip' === $variant ) {
			// A few articles from one category beside a short introduction (0.14.0). The grid's own
			// search box, category list and Load more button are hidden by the stylesheet in this layout.
			return self::lines( array(
				'<section class="twd-sk-resources twd-sk-resources--strip twd-sk-tone-tint">',
				'<div class="twd-sk-inner">',
				'<div class="twd-sk-resources__row">',
				'<div class="twd-sk-resources__intro">',
				'<span class="twd-sk-eyebrow">Short label here</span>',
				'<h2 class="twd-sk-title">Heading here</h2>',
				'<p class="twd-sk-lead">Short introduction here.</p>',
				'<a class="twd-sk-btn twd-sk-btn--outline" href="/resources">Read more about this approach</a>',
				'</div>',
				'<div class="twd-sk-resources__grid">',
				'[twd_articles category="your-topic" count="3" columns="3"]',
				self::NO_ARTICLES_LINE,
				'</div>',
				'</div>',
				'</div>',
				'</section>',
			) );
		}
		return self::lines( array(
			'<section class="twd-sk-resources twd-sk-tone-base">',
			'<div class="twd-sk-inner">',
			'<span class="twd-sk-eyebrow">Resources</span>',
			'<h2 class="twd-sk-title">Latest articles</h2>',
			'<p class="twd-sk-lead">Short introduction here.</p>',
			'[twd_articles count="6" columns="3"]',
			self::NO_ARTICLES_LINE,
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
				'description' => 'The top of the page. The only place the page heading (h1) may appear, and only once. On the image layout the extra classes twd-sk-hero--overlay-light, twd-sk-hero--overlay-strong and twd-sk-hero--left change the overlay strength and set the words to the left.',
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
					'twd-sk-hero--parallax' => array(
						'label'    => 'Parallax: the full-width picture moves a little slower than the page (off on phones and for visitors who ask for less motion)',
						'skeleton' => self::hero( 'parallax' ),
					),
				),
				'classes'     => array(
					'twd-sk-hero',
					'twd-sk-hero--split',
					'twd-sk-hero--image',
					'twd-sk-hero--compact',
					// Options on the image hero (0.14.0), added beside twd-sk-hero--image: a lighter or a
					// stronger overlay over the picture, and the words set to the left instead of centred.
					'twd-sk-hero--parallax',
					'twd-sk-hero--overlay-light',
					'twd-sk-hero--overlay-strong',
					'twd-sk-hero--left',
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
				'description' => 'A heading and paragraphs. The aside variant adds cards in a side column; the prose variant is a left-aligned reading column for longer writing; the picture variant puts a picture behind the whole section with the words in a frame (the extra classes twd-sk-overlay-light, twd-sk-overlay-strong and twd-sk-overlay-fade change the overlay, twd-sk-frame--light, twd-sk-frame--strong, twd-sk-frame--pad-sm and twd-sk-frame--pad-lg the frame). A long run of paragraphs may be broken up with subheadings inside twd-sk-text__body: an h2 (or an h3 when the section has an h2 title) with the class twd-sk-text__subheading, before the paragraph it introduces.',
				'skeleton'    => self::text( '' ),
				'variants'    => array(
					'twd-sk-text--aside' => array(
						'label'    => 'Aside: text with cards in a side column',
						'skeleton' => self::text( 'aside' ),
					),
					'twd-sk-text--prose' => array(
						'label'    => 'Prose: a narrow, left-aligned reading column for longer writing',
						'skeleton' => self::text( 'prose' ),
					),
					'twd-sk-text--picture' => array(
						'label'    => 'Picture: the words in a frame over a picture behind the whole section',
						'skeleton' => self::text( 'picture' ),
					),
				),
				'classes'     => array(
					'twd-sk-text',
					'twd-sk-text--aside',
					'twd-sk-text--prose',
					// 0.15.0: a picture behind the section (twd-sk-text__bg, covering it) under an overlay.
					'twd-sk-text--picture',
					'twd-sk-text__grid',
					'twd-sk-text__body',
					'twd-sk-text__aside',
					'twd-sk-text__bg',
					'twd-sk-text__overlay',
					// 0.15.0: a subheading inside the body (an h2, or an h3 when the section has an h2 title).
					'twd-sk-text__subheading',
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
					// 0.15.0: the band at the tall height (a Look choice beside the band layout).
					'twd-sk-image-text--band-tall',
					'twd-sk-image-text__grid',
					'twd-sk-image-text__media',
					'twd-sk-image-text__img',
					'twd-sk-image-text__badge',
					'twd-sk-image-text__body',
				),
				'shortcodes'  => array(),
			),

			'pictures'   => array(
				'id'          => 'pictures',
				'label'       => 'Pictures',
				'description' => 'A grid of pictures, each with a short caption. Every picture needs alt text that says what it shows.',
				'skeleton'    => self::pictures( '' ),
				'variants'    => array(
					'twd-sk-pictures--two' => array(
						'label'    => 'Two: two large pictures across',
						'skeleton' => self::pictures( 'two' ),
					),
				),
				'classes'     => array(
					'twd-sk-pictures',
					'twd-sk-pictures--two',
					'twd-sk-pictures__grid',
					'twd-sk-pictures__item',
					'twd-sk-pictures__img',
					'twd-sk-pictures__caption',
				),
				'shortcodes'  => array(),
			),

			'quote'      => array(
				'id'          => 'quote',
				'label'       => 'Quote or testimonial',
				'description' => 'A group of quote cards, a slider showing one quote at a time, or one boxed statement. Only use real quotes, with permission, word for word.',
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
					'twd-sk-quote--slider'  => array(
						'label'    => 'Slider: one quote at a time, swipe or use the arrows',
						'skeleton' => self::quote( 'slider' ),
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
					// The slider (0.10.0). The controls are added by twd-site-kit-slider.js, never stored.
					'twd-sk-quote--slider',
					'twd-sk-quote__track',
					'twd-sk-quote__controls',
					'twd-sk-quote__arrow',
					'twd-sk-quote__dots',
					'twd-sk-quote__dot',
				),
				'shortcodes'  => array(),
			),

			'logos'      => array(
				'id'          => 'logos',
				'label'       => 'Logos strip',
				'description' => 'A row of small logos, for example accreditations, memberships or partners. Use only logos you are entitled to show, each with alt text naming it.',
				'skeleton'    => self::logos(),
				'variants'    => array(
					'twd-sk-logos--grid' => array(
						'label'    => 'Grid: each logo in its own bordered box',
						'skeleton' => self::logos( 'grid' ),
					),
				),
				'classes'     => array(
					'twd-sk-logos',
					'twd-sk-logos--grid',
					'twd-sk-logos__list',
					'twd-sk-logos__item',
					'twd-sk-logos__img',
				),
				'shortcodes'  => array(),
			),

			'faq'        => array(
				'id'          => 'faq',
				'label'       => 'FAQ',
				'description' => 'Questions that open and close. Pure HTML, no JavaScript. The groups layout puts the questions in named groups with a link to each.',
				'skeleton'    => self::faq(),
				'variants'    => array(
					'twd-sk-faq--groups' => array(
						'label'    => 'Groups: questions in named groups, one group shown at a time on a wide screen, each with its own link',
						'skeleton' => self::faq( 'groups' ),
					),
					'twd-sk-faq--two' => array(
						'label'    => 'Two columns: the questions in two columns on a wide screen, for a long list',
						'skeleton' => self::faq( 'two' ),
					),
				),
				'classes'     => array(
					'twd-sk-faq',
					'twd-sk-faq--two',
					'twd-sk-faq__list',
					'twd-sk-faq__item',
					'twd-sk-faq__question',
					'twd-sk-faq__answer',
					// Question groups (0.14.0).
					'twd-sk-faq--groups',
					'twd-sk-faq__tabs',
					'twd-sk-faq__tab',
					'twd-sk-faq__tablink',
					'twd-sk-faq__groups',
					'twd-sk-faq__group',
					'twd-sk-faq__group--default',
					'twd-sk-faq__groupname',
					// 0.15.0: set by the tiny groups script only (never stored): the chosen tab.
					'twd-sk-faq__tab--on',
				),
				'shortcodes'  => array(),
			),

			'services'   => array(
				'id'          => 'services',
				'label'       => 'Services list',
				'description' => 'What is offered, as linked cards, boxes with a softly moving border, a row of short topic pills, or a plain list of linked rows.',
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
					'twd-sk-services--glow'  => array(
						'label'    => 'Glow: boxes with a softly moving border in the site\'s colours',
						'skeleton' => self::services( 'glow' ),
					),
					'twd-sk-services--links' => array(
						'label'    => 'Links: a plain list of linked rows, one per page, for many services',
						'skeleton' => self::services( 'links' ),
					),
				),
				'classes'     => array(
					'twd-sk-services',
					'twd-sk-services--cards',
					'twd-sk-services--pills',
					'twd-sk-services--glow',
					'twd-sk-services--links',
					'twd-sk-services__links',
					'twd-sk-services__row',
					'twd-sk-services__link',
					'twd-sk-services__list',
					'twd-sk-services__item',
					'twd-sk-services__name',
					'twd-sk-services__desc',
					'twd-sk-services__pills',
					'twd-sk-services__pill',
				),
				'shortcodes'  => array(),
			),

			'pricing'    => array(
				'id'          => 'pricing',
				'label'       => 'Prices',
				'description' => 'Prices as cards (a name, the price, a short description, what is included and a button) or as a simple list of names and prices. Only prices that have been supplied, never invented.',
				'skeleton'    => self::pricing( '' ),
				'variants'    => array(
					'twd-sk-pricing--list' => array(
						'label'    => 'List: names and prices in rows',
						'skeleton' => self::pricing( 'list' ),
					),
				),
				'classes'     => array(
					'twd-sk-pricing',
					'twd-sk-pricing--list',
					'twd-sk-pricing__grid',
					'twd-sk-pricing__card',
					'twd-sk-pricing__price',
					'twd-sk-pricing__note',
					'twd-sk-pricing__list',
					'twd-sk-pricing__row',
					'twd-sk-pricing__name',
					'twd-sk-pricing__amount',
				),
				'shortcodes'  => array(),
			),

			'steps'      => array(
				'id'          => 'steps',
				'label'       => 'Steps',
				'description' => 'A short numbered list of how to get started. The numbers are added automatically.',
				'skeleton'    => self::steps(),
				'variants'    => array(
					'twd-sk-steps--timeline' => array(
						'label'    => 'Timeline: the steps down the page on a line, one under the other',
						'skeleton' => self::steps( 'timeline' ),
					),
				),
				'classes'     => array(
					'twd-sk-steps',
					'twd-sk-steps--timeline',
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
				'description' => 'Contact details as label and value rows. The split variant adds a side column with a picture and an address. A contact form is not part of this: add the Enquiry form section, or keep an Elementor widget placed beside the page content.',
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

			'hours'      => array(
				'id'          => 'hours',
				'label'       => 'Opening hours',
				'description' => 'Days and times as rows, with a short note underneath, for example about bank holidays.',
				'skeleton'    => self::hours(),
				'variants'    => array(
					'twd-sk-hours--card' => array(
						'label'    => 'Card: the hours in a card on a tinted band',
						'skeleton' => self::hours( 'card' ),
					),
				),
				'classes'     => array(
					'twd-sk-hours',
					'twd-sk-hours--card',
					'twd-sk-hours__list',
					'twd-sk-hours__row',
					'twd-sk-hours__day',
					'twd-sk-hours__time',
					'twd-sk-hours__note',
				),
				'shortcodes'  => array(),
			),

			'areas'      => array(
				'id'          => 'areas',
				'label'       => 'Areas covered',
				'description' => 'The towns or areas served as a list, with a button that opens a map in a new page. No map is embedded in the page.',
				'skeleton'    => self::areas(),
				'variants'    => array(
					'twd-sk-areas--split' => array(
						'label'    => 'Split: the introduction on the left, the areas and the map link on the right',
						'skeleton' => self::areas( 'split' ),
					),
				),
				'classes'     => array(
					'twd-sk-areas',
					'twd-sk-areas--split',
					'twd-sk-areas__list',
					'twd-sk-areas__item',
					'twd-sk-areas__actions',
				),
				'shortcodes'  => array(),
			),

			'enquiry'    => array(
				'id'          => 'enquiry',
				'label'       => 'Enquiry form',
				'description' => 'A short heading and introduction above a simple enquiry form (name, email, phone, message and consent). The form itself is the twd_enquiry shortcode: it is built by the plugin when the page is shown, sends an email to the site\'s address and stores nothing.',
				'skeleton'    => self::enquiry(),
				'variants'    => array(),
				'classes'     => array(
					'twd-sk-enquiry',
					'twd-sk-form',
					'twd-sk-form__field',
					'twd-sk-form__label',
					'twd-sk-form__input',
					'twd-sk-form__textarea',
					'twd-sk-form__check',
					'twd-sk-form__hp',
					'twd-sk-form__actions',
					'twd-sk-form__status',
					'twd-sk-form__status--ok',
					'twd-sk-form__status--error',
					'twd-sk-form__help',
				),
				'shortcodes'  => array(
					'twd_enquiry' => array(),
				),
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
				'description' => 'A heading above the articles grid. Only wraps the twd_articles shortcode, followed by a short line (twd-sk-resources__none) that shows only while there are no articles yet, in place of the articles plugin\'s own line, so its words can be changed. The strip layout shows a few articles from one category beside a short introduction, for the foot of a service page.',
				'skeleton'    => self::resources(),
				'variants'    => array(
					'twd-sk-resources--strip' => array(
						'label'    => 'Strip: a few articles from one category beside a short introduction and a link to the full list',
						'skeleton' => self::resources( 'strip' ),
					),
				),
				'classes'     => array(
					'twd-sk-resources',
					// The strip layout (0.14.0).
					'twd-sk-resources--strip',
					'twd-sk-resources__row',
					'twd-sk-resources__intro',
					'twd-sk-resources__grid',
					// The site's own line for a grid with no articles yet (0.15.0).
					'twd-sk-resources__none',
					// 0.15.0: the whole section (heading included) hides while its category has no articles.
					'twd-sk-resources--hide-empty',
				),
				'shortcodes'  => array(
					'twd_articles' => self::articles_shortcode_attrs(),
				),
			),

			'embed'      => array(
				'id'          => 'embed',
				'label'       => 'Embed (set up by an administrator)',
				'description' => 'A section that shows a shortcode or a piece of HTML code an administrator saved apart from the page, such as a booking calendar, a map or a form from another plugin. The page itself holds only the twd_embed shortcode with the number of the saved embed. The code is never part of the page: it is not written, changed or seen by the AI, and only an administrator can create or change it. Keep an embed section exactly as it is.',
				'skeleton'    => self::embed(),
				'variants'    => array(
					'twd-sk-embed--bare' => array(
						'label'    => 'No heading: only the embedded code',
						'skeleton' => self::embed( 'twd-sk-embed--bare' ),
					),
				),
				'classes'     => array(
					'twd-sk-embed',
					'twd-sk-embed--bare',
					'twd-sk-embed__content',
					'twd-sk-embed__missing',
				),
				'shortcodes'  => array(
					'twd_embed' => array( 'id' => '/^\d{1,9}$/' ),
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
