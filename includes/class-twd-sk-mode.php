<?php
/**
 * TWD_SK_Mode: therapy mode or business mode, and every word that changes with it.
 *
 * One admin setting (option twd_sk_mode). Therapy is the default, so a site that has never set it
 * behaves exactly as before. Safe mode always reads as therapy, so a fault here can be switched off.
 *
 * Every mode-dependent word, label, rule and AI instruction lives in words() below. No other file
 * hard-codes "therapist", "practice" or "business" in anything a person or the AI reads; they ask
 * this class. Adding a mode means adding one list here (or through the twd_sk_modes and
 * twd_sk_mode_words filters); a test checks every list has exactly the same keys.
 *
 * Switching mode changes wording, the structured data type and which fields show. It never rewrites
 * page content.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class TWD_SK_Mode {

	const OPTION       = 'twd_sk_mode';
	const DEFAULT_MODE = 'therapy';

	/** slug => { label, help }. */
	public static function modes() {
		$modes = array(
			'therapy'  => array(
				'label' => 'Therapy',
				'help'  => 'For therapists, counsellors, psychotherapists and coaches. Careful wording, registration lines, and nothing about the people they treat.',
			),
			'business' => array(
				'label' => 'Business',
				'help'  => 'For any other small business. Customers instead of clients, business facts, prices and booking, and the local business search details.',
			),
		);
		$filtered = function_exists( 'apply_filters' ) ? apply_filters( 'twd_sk_modes', $modes ) : $modes;
		if ( ! is_array( $filtered ) ) {
			return $modes;
		}
		$out = array();
		foreach ( $filtered as $slug => $m ) {
			if ( is_string( $slug ) && 1 === preg_match( '/^[a-z][a-z0-9-]{1,30}$/', $slug ) && is_array( $m ) && isset( $m['label'] ) && is_string( $m['label'] ) ) {
				$out[ $slug ] = array( 'label' => $m['label'], 'help' => isset( $m['help'] ) && is_string( $m['help'] ) ? $m['help'] : '' );
			}
		}
		// Therapy is always there: it is the default and what safe mode uses.
		return isset( $out['therapy'] ) ? $out : $modes;
	}

	/** The stored setting, ignoring safe mode. */
	public static function stored() {
		try {
			$v = function_exists( 'get_option' ) ? get_option( self::OPTION, self::DEFAULT_MODE ) : self::DEFAULT_MODE;
		} catch ( \Throwable $e ) {
			// A database fault must never stop a page, a prompt or the site report: fall back to therapy.
			return self::DEFAULT_MODE;
		}
		return is_string( $v ) && isset( self::modes()[ $v ] ) ? $v : self::DEFAULT_MODE;
	}

	/** Set only while using() runs: the mode to read instead of the stored one. */
	private static $forced = null;

	/**
	 * Run something with another mode in use, without saving anything (the full site set up reads the notes in
	 * the type chosen on its first step before that type is saved to the site). The stored mode is back afterwards,
	 * even if the function throws.
	 */
	public static function using( $mode, $fn ) {
		$before       = self::$forced;
		self::$forced = ( is_string( $mode ) && isset( self::modes()[ $mode ] ) ) ? $mode : null;
		try {
			return call_user_func( $fn );
		} finally {
			self::$forced = $before;
		}
	}

	/** The mode in use: therapy in safe mode, otherwise the stored setting. */
	public static function get() {
		try {
			if ( class_exists( 'TWD_SK_Safe' ) && TWD_SK_Safe::on() ) {
				return self::DEFAULT_MODE;
			}
		} catch ( \Throwable $e ) {
			return self::DEFAULT_MODE;
		}
		return null !== self::$forced ? self::$forced : self::stored();
	}

	public static function is( $mode ) {
		return self::get() === $mode;
	}

	/** @return string|WP_Error the saved mode. */
	public static function set( $mode ) {
		if ( ! is_string( $mode ) || ! isset( self::modes()[ $mode ] ) ) {
			return new WP_Error( 'twd_sk_bad_mode', 'Choose one of: ' . implode( ', ', array_keys( self::modes() ) ) . '.' );
		}
		update_option( self::OPTION, $mode );
		return $mode;
	}

	/** One word or sentence for the mode in use (or a named mode). Unknown keys return an empty string. */
	public static function w( $key, $mode = null ) {
		$words = self::words( $mode );
		return isset( $words[ $key ] ) ? $words[ $key ] : '';
	}

	/** The full list for a mode. A filtered-in mode starts from the business list and overrides keys. */
	public static function words( $mode = null ) {
		$mode = null === $mode ? self::get() : $mode;
		$all  = self::base_words();
		$list = isset( $all[ $mode ] ) ? $all[ $mode ] : $all['business'];
		if ( function_exists( 'apply_filters' ) ) {
			$extra = apply_filters( 'twd_sk_mode_words', array(), $mode );
			if ( is_array( $extra ) ) {
				foreach ( $extra as $k => $v ) {
					if ( isset( $list[ $k ] ) && gettype( $v ) === gettype( $list[ $k ] ) ) {
						$list[ $k ] = $v;
					}
				}
			}
		}
		return $list;
	}

	/** The few words the browser editor needs. */
	public static function js_words() {
		$keys = array( 'person', 'a_person', 'facts', 'Facts', 'practice', 'person_name_label', 'person_job_label', 'registration_label', 'registration_help', 'address_help', 'area_label', 'facts_help', 'show_person_job', 'topic_example' );
		$out  = array();
		foreach ( $keys as $k ) {
			$out[ $k ] = self::w( $k );
		}
		$out['mode']  = self::get();
		$out['modes'] = self::modes();
		return $out;
	}

	/** The headings of the facts outline for a mode (lines written all in capitals). */
	public static function facts_headings( $mode = null ) {
		$out = array();
		foreach ( self::w( 'facts_template', $mode ) as $line ) {
			if ( '' !== $line && strtoupper( $line ) === $line ) {
				$out[] = $line;
			}
		}
		return $out;
	}

	/**
	 * The word lists. Therapy reproduces the wording used before modes existed, exactly.
	 * Keys must match across every mode (a test checks).
	 */
	private static function base_words() {
		return array(
			'therapy'  => array(
				// Short words.
				'person'             => 'therapist',
				'a_person'           => 'a therapist',
				'persons'            => 'therapist\'s',
				'people'             => 'clients',
				'facts'              => 'practice facts',
				'Facts'              => 'Practice facts',
				'practice'           => 'practice',
				// Field labels.
				'site_name_label'    => 'Practice name',
				'person_name_label'  => 'Therapist name',
				'person_job_label'   => 'Therapist job title',
				'area_label'         => 'Where they work',
				'registration_label' => 'Registration lines',
				'registration_line'  => 'Registration or membership line (use exactly): ',
				'registration_help'  => 'Only exactly what the therapist has supplied. One line each, up to four.',
				'address_help'       => 'Up to three lines. Leave empty for an online practice.',
				'facts_help'         => 'One master document about the person and their practice: who they are, who they help, what they offer, their approach, qualifications and registrations in exactly the wording they supplied, fees, where and when, questions people ask, and words they like or never use. The AI uses only this, and the site details above, for anything new it writes. Only administrators can see it. It is never shown on the site. Write about the practice only, never about the people they work with.',
				'show_person_job'    => true,
				// Placeholders in a fresh profile.
				'ph_site_name'       => '[PLACEHOLDER: practice name]',
				'ph_address'         => '[PLACEHOLDER: address line, or delete for an online practice]',
				'ph_footer_text'     => '[PLACEHOLDER: one or two sentences about your practice]',
				'ph_registration'    => '[PLACEHOLDER: registration or membership line, supplied by the therapist]',
				'ph_person_name'     => '[PLACEHOLDER: therapist name]',
				// Structured data.
				'schema_type'        => 'ProfessionalService',
				'schema_person'      => true,
				// Rules shared by the chat prompt and the AI.
				'rule_invent'        => 'Never invent credentials, registration numbers, fees, testimonials or contact details. Where a detail is missing, write [PLACEHOLDER] or [PLACEHOLDER: what is needed] and ask the therapist for it.',
				'rule_policies'      => 'Never state policies (confidentiality, safeguarding, cancellation, refunds) or DBS, accreditation or registration status unless the therapist has supplied the wording. Where it is missing, write [PLACEHOLDER: policy wording needed] and ask for it.',
				'rule_claims'        => 'Never promise outcomes and never make health claims. Stay within the advertising guidance of the therapist\'s professional body.',
				'rule_privacy'       => 'Keep client confidentiality. Use composites only. Never write a real client story or anything that could identify a person.',
				'rule_notice'        => 'Keep any safety notice on the page. Do not remove or shorten it. Never change the helpline numbers or their wording.',
				'invent_list'        => 'qualification, registration, fee, policy, contact detail, testimonial or claim',
				// Page recipes.
				'recipe_about'       => 'An About page. Who the therapist is, their story and what drives their work, how they work, what working with them is like, and a clear next step. Warm and human, in their own voice.',
				'recipe_contact'     => 'A Contact page. How to get in touch, what to expect after getting in touch, where and when they work, and a few honest questions and answers. Use only contact details that appear in the facts.',
				'recipe_home'        => 'The Home page. A clear opening that says who the therapist helps and how, what they offer, a short personal section, reassurance, and a clear next step.',
				'recipe_service'     => 'A page about working with one topic or kind of work (given below). What it is and how it can feel, how this therapist works with it, what a first session is like, common questions, and a clear next step. Never promise outcomes, never make clinical or health claims, never diagnose. Use only what the facts say about how this therapist works.',
				'recipe_faq'         => 'A questions and answers page. The questions people ask most, answered in the therapist\'s own words, using only the facts.',
				'recipe_service_label' => 'Service or topic page (for example Working with anxiety)',
				'topic_example'      => 'Working with anxiety',
				'service_kind'       => 'kind of work or topic',
				// Picture search ideas for the full site set up (0.12.0): short, so a marker stays under 60 characters.
				'photo_calm'         => 'calm counselling room',
				'photo_top'          => 'calm natural light room',
				'photo_portrait'     => 'calm therapist portrait',
				'photo_space'        => 'welcoming reception',
				'photo_ideas'        => 'calm counselling room, soft natural light, welcoming reception',
				'form_help'          => 'Please do not include anything private or urgent. If you need help now, call 999 in an emergency or use the support lines on this site.',
				// The facts outline.
				'facts_template'     => array(
					'WHO I AM',
					'Your name, how you like to be described, and your story in a few lines.',
					'',
					'WHO I HELP',
					'The people you work with, and what they usually come to you for.',
					'',
					'WHAT I OFFER',
					'Each kind of work you do, one line each: therapy, coaching, training, groups, anything else.',
					'',
					'MY APPROACH',
					'How you work, in your own words.',
					'',
					'QUALIFICATIONS, REGISTRATIONS AND MEMBERSHIPS',
					'Write exactly what you are entitled to say, in the wording you want used. Leave this empty if there is nothing.',
					'',
					'FEES, SESSIONS AND HOW IT WORKS',
					'Session length, fees, how a first session goes, cancellations. Only what you want stated.',
					'',
					'WHERE AND WHEN',
					'Where you work, online or in person, and your usual days and times.',
					'',
					'HOW TO CONTACT ME',
					'How people should get in touch.',
					'',
					'QUESTIONS PEOPLE ASK',
					'The questions you hear most, with your answers.',
					'',
					'WORDS AND PHRASES I LIKE',
					'Wording that sounds like you.',
					'',
					'THINGS I NEVER SAY OR PROMISE',
					'Claims, words or promises that must not appear.',
				),
				// The interview prompt.
				'interview_intro'    => 'You are helping me gather everything needed to build a therapist\'s website. I am a web designer. You will interview the therapist (or me, answering for them), then produce ONE JSON file that I can paste into their website.',
				'interview_topics'   => '- Topics, in this order: (1) who they are and how they like to be described; (2) who they help; (3) what they offer; (4) how they work, in their own words; (5) qualifications, registrations and memberships: ask for the exact wording they want shown, and say it can be left empty; (6) fees, sessions and how a first session goes; (7) where and when they work, online or in person; (8) how people should contact them: phone, email, address only if it is public; (9) the questions people ask most, with their answers; (10) their voice: words they like, words they never use, anything they never promise; (11) the feel of the site, for example calm, earthy, bright, formal, and which pages they want in the menu; (12) which pages to create (About, Contact, Home, a page for each kind of work or topic, a questions and answers page), with a one line note for each; (13) a search title and short description for each page: offer to draft them from the answers for approval.',
				'interview_invent'   => '- Never invent anything. If they do not know or do not want to say, leave it empty. Never guess a qualification, registration, fee, policy, contact detail or testimonial.',
				'interview_privacy'  => '- Practice and project facts only. Nothing about the people they treat. Never ask for or include passwords, keys or private client details.',
			),
			'business' => array(
				'person'             => 'business owner',
				'a_person'           => 'a business owner',
				'persons'            => 'business owner\'s',
				'people'             => 'customers',
				'facts'              => 'business facts',
				'Facts'              => 'Business facts',
				'practice'           => 'business',
				'site_name_label'    => 'Business name',
				'person_name_label'  => 'Owner or contact name',
				'person_job_label'   => 'Owner job title',
				'area_label'         => 'Area covered',
				'registration_label' => 'Accreditation lines',
				'registration_line'  => 'Accreditation or membership line (use exactly): ',
				'registration_help'  => 'Optional. Only exactly what the business has supplied, for example a trade body membership. One line each, up to four.',
				'address_help'       => 'Up to three lines. Leave empty if the business has no public address.',
				'facts_help'         => 'One master document about the business: who is behind it, what it offers and what is included, who it is for, the area covered and opening times, prices and booking, how it works, accreditations in exactly the wording supplied, questions customers ask, and words they like or never use. The AI uses only this, and the site details above, for anything new it writes. Only administrators can see it. It is never shown on the site. Nothing personal about customers.',
				'show_person_job'    => false,
				'ph_site_name'       => '[PLACEHOLDER: business name]',
				'ph_address'         => '[PLACEHOLDER: address line, or delete if there is no public address]',
				'ph_footer_text'     => '[PLACEHOLDER: one or two sentences about your business]',
				'ph_registration'    => '[PLACEHOLDER: accreditation or membership line, supplied by the business, or delete]',
				'ph_person_name'     => '[PLACEHOLDER: owner or contact name]',
				'schema_type'        => 'LocalBusiness',
				'schema_person'      => false,
				'rule_invent'        => 'Never invent qualifications, accreditations, prices, reviews, testimonials or contact details. Where a detail is missing, write [PLACEHOLDER] or [PLACEHOLDER: what is needed] and ask the business owner for it.',
				'rule_policies'      => 'Never state policies (cancellation, refunds, deposits, delivery, safety, insurance) or accreditation or registration status unless the business owner has supplied the wording. Where it is missing, write [PLACEHOLDER: policy wording needed] and ask for it.',
				'rule_claims'        => 'Never promise results and never make health or safety claims. Keep to honest advertising: nothing exaggerated, nothing that cannot be backed up.',
				'rule_privacy'       => 'Respect customer privacy. Never name a customer or write a customer story or review unless the business owner supplied it.',
				'rule_notice'        => 'Keep any notice on the page, for example safety guidance or booking terms. Do not remove or shorten it.',
				'invent_list'        => 'qualification, accreditation, price, policy, contact detail, review, testimonial or claim',
				'recipe_about'       => 'An About page. Who is behind the business, how it started and what they care about, how they work, what customers can expect, and a clear next step. Warm and human, in their own voice.',
				'recipe_contact'     => 'A Contact page. How to get in touch or book, what happens next, the area covered and opening times, and a few honest questions and answers. Use only contact details that appear in the facts.',
				'recipe_home'        => 'The Home page. A clear opening that says what the business offers and who it is for, the main products or services, why choose them, how booking or buying works, and a clear next step.',
				'recipe_service'     => 'A page about one product or service (given below). What it is and who it is for, what is included, how booking or buying works, common questions, and a clear next step. Never promise results, never make health claims. Use only what the facts say about this product or service.',
				'recipe_faq'         => 'A questions and answers page. The questions customers ask most, answered in the business owner\'s own words, using only the facts.',
				'recipe_service_label' => 'Product or service page (for example Weekend hire)',
				'topic_example'      => 'Weekend hire',
				'service_kind'       => 'product or service',
				'photo_calm'         => 'small business at work',
				'photo_top'          => 'friendly small business',
				'photo_portrait'     => 'business owner portrait',
				'photo_space'        => 'shop front',
				'photo_ideas'        => 'small business at work, friendly shop front, hands at work',
				'form_help'          => 'We will reply as soon as we can. Please do not include payment details.',
				'facts_template'     => array(
					'WHO WE ARE',
					'The business name, who is behind it, and how it started, in a few lines.',
					'',
					'WHAT WE OFFER',
					'Each product or service, one line each, with what is included.',
					'',
					'WHO IT IS FOR',
					'The customers you serve and what they usually come to you for.',
					'',
					'AREA COVERED AND OPENING TIMES',
					'Where you work or deliver, and your usual days and times.',
					'',
					'PRICES AND BOOKING',
					'Prices, deposits and how booking works. Only what you want stated.',
					'',
					'HOW IT WORKS',
					'What happens from first contact to the end, step by step.',
					'',
					'ACCREDITATIONS, INSURANCE AND MEMBERSHIPS',
					'Write exactly what you are entitled to say, in the wording you want used. Leave this empty if there is nothing.',
					'',
					'HOW TO CONTACT US',
					'How people should get in touch or book.',
					'',
					'QUESTIONS PEOPLE ASK',
					'The questions you hear most, with your answers.',
					'',
					'WORDS AND PHRASES WE LIKE',
					'Wording that sounds like you.',
					'',
					'THINGS WE NEVER SAY OR PROMISE',
					'Claims, words or promises that must not appear.',
				),
				'interview_intro'    => 'You are helping me gather everything needed to build a small business website. I am a web designer. You will interview the business owner (or me, answering for them), then produce ONE JSON file that I can paste into their website.',
				'interview_topics'   => '- Topics, in this order: (1) the business and who is behind it; (2) what it offers, each product or service with what is included; (3) who the customers are; (4) the area covered and opening times; (5) prices, deposits and how booking works: only what they want stated; (6) how it works from first contact to the end; (7) accreditations, insurance and memberships: ask for the exact wording they want shown, and say it can be left empty; (8) how people should contact them: phone, email, address only if it is public; (9) the questions customers ask most, with their answers; (10) their voice: words they like, words they never use, anything they never promise; (11) the feel of the site, for example bold, calm, earthy, bright, and which pages they want in the menu; (12) which pages to create (Home, About, Contact, a page for each main product or service, a questions and answers page), with a one line note for each; (13) a search title and short description for each page: offer to draft them from the answers for approval.',
				'interview_invent'   => '- Never invent anything. If they do not know or do not want to say, leave it empty. Never guess a qualification, accreditation, price, policy, contact detail, review or testimonial.',
				'interview_privacy'  => '- Business and project facts only. Nothing personal about customers. Never ask for or include passwords, keys or private customer details.',
			),
		);
	}
}
