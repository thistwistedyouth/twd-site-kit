<?php
/**
 * TWD_SK_Full: the full site set up (0.12.0, a test option).
 *
 * The person pastes everything they have into one box. The AI reads it and says what a complete site still
 * needs (questions, answered on the setup page), the code adds the design questions, and the build stays
 * switched off until nothing is missing. Then every page is written ONLY from the supplied facts, with no
 * [PLACEHOLDER] for text: whatever the facts do not support is left out. Pictures are the one exception:
 * each picture spot is a marker that says what photo is wanted and gives a Pexels search to start from, or,
 * with "no photos", the page layouts simply have no picture spots.
 *
 * What is promised, and what is checked rather than promised:
 *  - Questions: the AI may only ask; every claim it makes about what is in the notes (names, email, phone,
 *    registration) must appear in the notes or the answers, or it is ignored (so it cannot invent them).
 *  - Pages: the page list and each page's sections come from this class, not from the AI.
 *  - Writing: after each page is written, any [PLACEHOLDER] that is not a picture marker, and any email,
 *    phone number or price that is not in the supplied facts, is flagged for the person to look at.
 *  - Nothing is published. Pages are drafts the person reads first.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class TWD_SK_Full {

	const MAX_ANSWER   = 1500;
	const MAX_ANSWERS  = 40;
	const MAX_QUESTIONS = 8;
	const MAX_SERVICES = 8;
	const MIN_NOTES    = 40;
	const TOKENS_CHECK = 3000;
	const MARKER_MAX   = 60;

	/** Answers that are not shown to the AI as question and answer lines. */
	private static function special_keys() {
		return array( 'style', 'tone', 'photos', 'email', 'phone' );
	}

	// -- Plain data -------------------------------------------------------------------

	public static function tones() {
		return array(
			'warm'  => array( 'label' => 'Warm and gentle', 'text' => 'Warm, gentle and personal. Short, plain sentences. Speak to the reader as "you".' ),
			'calm'  => array( 'label' => 'Calm and professional', 'text' => 'Calm, clear and professional. Reassuring without being chatty.' ),
			'plain' => array( 'label' => 'Plain and direct', 'text' => 'Plain and direct. Say what is offered, who it is for and what to do next, with no flowery language.' ),
		);
	}

	public static function photo_choices() {
		return array(
			'mine' => array( 'label' => 'I will add my own photos', 'help' => 'Each place for a photo is marked with what is wanted and a Pexels search to start from. You upload them in the Visual editor.' ),
			'none' => array( 'label' => 'No photos for now', 'help' => 'The pages are built with layouts that have no photo spots. You can add photos later.' ),
		);
	}

	/** info@ plus the site's own domain, or '' when the site address cannot be read. */
	public static function default_email() {
		$host = function_exists( 'home_url' ) ? (string) parse_url( (string) home_url(), PHP_URL_HOST ) : '';
		$host = preg_replace( '/^www\./i', '', strtolower( $host ) );
		return ( '' !== $host && 1 === preg_match( '/^[a-z0-9.-]+\.[a-z]{2,}$/', $host ) ) ? 'info@' . $host : '';
	}

	// -- Cleaning ---------------------------------------------------------------------

	/** Plain text for storage: no tags, no control characters, no long dashes, cut to $max characters. */
	public static function clean_text( $text, $max ) {
		if ( ! is_string( $text ) ) {
			return '';
		}
		$text = wp_strip_all_tags( $text );
		$text = preg_replace( '/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F]/', '', $text );
		$text = preg_replace( '/\r\n?/', "\n", $text );
		$text = TWD_SK_Sanitizer::strip_dashes( $text );
		$text = preg_replace( "/[ \t]+\n/", "\n", $text );
		$text = preg_replace( "/\n{3,}/", "\n\n", $text );
		$text = trim( $text );
		return function_exists( 'mb_substr' ) ? mb_substr( $text, 0, $max, 'UTF-8' ) : substr( $text, 0, $max );
	}

	/** Answers as key => text. Bad keys and empty answers are dropped. */
	public static function clean_answers( $in ) {
		$out = array();
		if ( ! is_array( $in ) ) {
			return $out;
		}
		foreach ( $in as $key => $value ) {
			if ( ! is_string( $key ) || 1 !== preg_match( '/^[a-z][a-z0-9_]{0,29}$/', $key ) || ! is_string( $value ) ) {
				continue;
			}
			$value = self::clean_text( $value, self::MAX_ANSWER );
			if ( '' !== $value ) {
				$out[ $key ] = $value;
			}
			if ( count( $out ) >= self::MAX_ANSWERS ) {
				break;
			}
		}
		return $out;
	}

	private static function answered( $answers, $key ) {
		return isset( $answers[ $key ] ) && '' !== trim( (string) $answers[ $key ] );
	}

	// -- The text the AI reads ------------------------------------------------------------

	private static function key_label( $key ) {
		return ucfirst( str_replace( '_', ' ', $key ) );
	}

	/**
	 * The notes plus the answers, as one text. $final adds the contact lines the site will use (the assumed
	 * email when none was given) and the tone, for turning the notes into a brief.
	 */
	public static function notes_text( $draft, $final = false ) {
		$out     = trim( (string) $draft['full_notes'] );
		$answers = (array) $draft['full_answers'];
		$qtext   = isset( $draft['full_check']['questions'] ) && is_array( $draft['full_check']['questions'] ) ? $draft['full_check']['questions'] : array();
		$lines   = array();
		foreach ( $answers as $key => $value ) {
			if ( in_array( $key, self::special_keys(), true ) ) {
				continue;
			}
			$q       = isset( $qtext[ $key ] ) && is_string( $qtext[ $key ] ) ? $qtext[ $key ] : self::key_label( $key );
			$lines[] = $q . ' ' . $value;
		}
		if ( $lines ) {
			$out .= "\n\nAnswers to questions:\n" . implode( "\n", $lines );
		}
		$contact = array();
		if ( self::answered( $answers, 'email' ) ) {
			$contact[] = 'Email: ' . $answers['email'];
		}
		if ( self::answered( $answers, 'phone' ) ) {
			$contact[] = 'Phone: ' . $answers['phone'];
		}
		if ( $final ) {
			if ( ! self::answered( $answers, 'email' ) && isset( $draft['full_check']['contact']['email'] ) && '' !== $draft['full_check']['contact']['email'] ) {
				$contact[] = 'Email: ' . $draft['full_check']['contact']['email'];
			}
			$tones = self::tones();
			if ( self::answered( $answers, 'tone' ) && isset( $tones[ $answers['tone'] ] ) ) {
				$contact[] = 'Tone of voice: ' . $tones[ $answers['tone'] ]['text'];
			}
		}
		if ( $contact ) {
			$out .= "\n\nDetails given:\n" . implode( "\n", $contact );
		}
		return $out;
	}

	/** A short fingerprint of the notes and answers, so a prepare can tell they changed since the last check. */
	public static function inputs_hash( $draft ) {
		return md5( (string) $draft['full_notes'] . '|' . json_encode( (array) $draft['full_answers'] ) );
	}

	// -- Grounding: nothing the AI claims counts unless the words are really there -------------

	private static function words( $s ) {
		$s = function_exists( 'mb_strtolower' ) ? mb_strtolower( (string) $s, 'UTF-8' ) : strtolower( (string) $s );
		preg_match_all( '/[\p{L}\p{N}]+/u', $s, $m );
		return $m[0];
	}

	/** True when every word of $value appears somewhere in $text. */
	public static function grounded( $value, $text ) {
		$need = self::words( $value );
		if ( ! $need ) {
			return false;
		}
		$have = array_flip( self::words( $text ) );
		foreach ( $need as $w ) {
			if ( ! isset( $have[ $w ] ) ) {
				return false;
			}
		}
		return true;
	}

	public static function email_grounded( $value, $text ) {
		return is_string( $value ) && is_email( $value ) && false !== strpos( strtolower( $text ), strtolower( $value ) );
	}

	public static function phone_grounded( $value, $text ) {
		$d = preg_replace( '/\D+/', '', (string) $value );
		return strlen( $d ) >= 7 && false !== strpos( preg_replace( '/\D+/', '', $text ), $d );
	}

	// -- The check -------------------------------------------------------------------------

	private static function check_system_prompt( $mode_words ) {
		$out   = array();
		$out[] = 'You check whether some notes hold enough to write a complete ' . $mode_words['practice'] . ' website with no gaps, and you list what is still missing. You do not write the website.';
		$out[] = 'Reply with ONE JSON object only: no code fence, no commentary, in exactly this shape:';
		$out[] = '{ "found": { "site_name": "", "person_name": "", "email": "", "phone": "", "area": "", "registration": "", "services": [ { "name": "", "has_detail": false } ], "about": false, "faq": false, "testimonials": false, "process": false },';
		$out[] = '  "missing": [ { "key": "", "question": "", "why": "" } ],';
		$out[] = '  "summary": [ "" ] }';
		$out[] = '';
		$out[] = '## Rules';
		$out[] = '1. found: copy values exactly as written in the notes or the answers. Leave "" or false when something is not there. Never guess or invent. Give an email or phone only if it is written down.';
		$out[] = '2. services: every ' . $mode_words['service_kind'] . ' the notes name, one entry each. has_detail is true only when the notes say what it is and who it suits in at least a sentence.';
		$out[] = '3. about is true when the notes tell the ' . $mode_words['person'] . '\'s story, training or approach in at least a few sentences.';
		$out[] = '4. faq is true only when the notes contain at least three questions with answers. testimonials is true only when real quotes from clients are written in the notes. process is true only when the notes explain step by step what working together involves.';
		$out[] = '5. missing: ask only for what a complete site needs and the notes lack: the name of the ' . $mode_words['practice'] . '; the ' . $mode_words['person'] . '\'s own name; at least one ' . $mode_words['service_kind'] . '; what each ' . $mode_words['service_kind'] . ' without detail involves and who it suits; where they work or whether it is online only; their story if absent; any registration, accreditation or professional body with the number (an answer of "none" is fine). Do NOT ask for photos, colours, style, a phone number, an email address, testimonials or a step by step process.';
		$out[] = '6. An answer to an earlier question counts as given. "none", "not applicable" and "online only" are complete answers. Never ask again for anything already answered.';
		$out[] = '7. At most ' . self::MAX_QUESTIONS . ' questions. Each key is lowercase letters, digits and underscores, up to 30 characters, and the same from one round to the next (for example site_name, person_name, area, registration, about, service_2_detail).';
		$out[] = '8. Questions are in plain English, one thing each, with no jargon. Never use em dashes or en dashes. UK spelling. Give each a short why.';
		$out[] = '9. summary: up to 8 short bullet points of what you understood. Never give advice or judge anything.';
		return implode( "\n", $out ) . "\n";
	}

	/** The words the check prompt needs from the current mode. */
	private static function mode_words( $mode ) {
		return array(
			'practice'     => TWD_SK_Mode::w( 'practice', $mode ),
			'person'       => TWD_SK_Mode::w( 'person', $mode ),
			'service_kind' => TWD_SK_Mode::w( 'service_kind', $mode ),
		);
	}

	/**
	 * Ask the AI what is missing, check its claims against the text, add the design questions, and say
	 * whether the build may start. Saves nothing: the caller stores the result in the setup draft.
	 *
	 * @param array $draft TWD_SK_Build::draft().
	 * @return array|WP_Error
	 */
	public static function check( $draft ) {
		if ( ! TWD_SK_AI::available() ) {
			return new WP_Error( 'twd_sk_ai_unavailable', 'The full site set up needs the AI key from the Articles plugin, and none is set up on this site.' );
		}
		$notes = trim( (string) $draft['full_notes'] );
		if ( strlen( $notes ) < self::MIN_NOTES ) {
			return new WP_Error( 'twd_sk_bad_input', 'Paste what you have first: who you are, what you offer, who you help. A few sentences is enough to start.' );
		}
		$text  = self::notes_text( $draft, false );
		$reply = apply_filters( 'twd_ai_complete', null, self::check_system_prompt( self::mode_words( $draft['mode'] ) ), "The notes and answers:\n" . $text . "\n", self::TOKENS_CHECK );
		if ( TWD_SK_AI::cut_off( $reply ) ) {
			return new WP_Error( 'twd_sk_ai_too_long', 'The AI ran out of room reading the notes. Shorten them a little and check again.' );
		}
		if ( is_wp_error( $reply ) ) {
			return $reply;
		}
		if ( ! is_string( $reply ) || '' === trim( $reply ) ) {
			return new WP_Error( 'twd_sk_ai_unavailable', 'The AI did not answer. Nothing was changed. Try again in a moment.' );
		}
		$data = json_decode( TWD_SK_AI::strip_fence( $reply ), true );
		if ( ! is_array( $data ) || ! isset( $data['found'] ) || ! is_array( $data['found'] ) ) {
			return new WP_Error( 'twd_sk_ai_bad_result', 'The AI did not send back something that could be read. Nothing was changed. Try again.' );
		}
		return self::assemble( $draft, $data, $text );
	}

	/** Build the stored result from the AI's reply. Public so the rules can be tested without the AI. */
	public static function assemble( $draft, $data, $text ) {
		$answers = (array) $draft['full_answers'];
		$f       = $data['found'];
		$str     = function ( $key, $max ) use ( $f ) {
			return isset( $f[ $key ] ) && is_string( $f[ $key ] ) ? self::clean_text( $f[ $key ], $max ) : '';
		};
		$found = array(
			'site_name'    => $str( 'site_name', 80 ),
			'person_name'  => $str( 'person_name', 80 ),
			'area'         => $str( 'area', 120 ),
			'registration' => $str( 'registration', 160 ),
			'email'        => $str( 'email', 100 ),
			'phone'        => $str( 'phone', 40 ),
		);
		// Nothing the AI claims counts unless the words really are in the notes or the answers.
		foreach ( array( 'site_name', 'person_name', 'registration', 'area' ) as $k ) {
			if ( '' !== $found[ $k ] && ! self::grounded( $found[ $k ], $text ) ) {
				$found[ $k ] = '';
			}
		}
		if ( ! self::email_grounded( $found['email'], $text ) ) {
			$found['email'] = '';
		}
		if ( ! self::phone_grounded( $found['phone'], $text ) ) {
			$found['phone'] = '';
		}
		$services = array();
		$seen     = array();
		if ( isset( $f['services'] ) && is_array( $f['services'] ) ) {
			foreach ( $f['services'] as $s ) {
				$name = is_array( $s ) && isset( $s['name'] ) && is_string( $s['name'] ) ? self::clean_text( $s['name'], 80 ) : ( is_string( $s ) ? self::clean_text( $s, 80 ) : '' );
				$key  = strtolower( $name );
				if ( '' === $name || isset( $seen[ $key ] ) || count( $services ) >= self::MAX_SERVICES ) {
					continue;
				}
				$seen[ $key ] = true;
				$services[]   = array( 'name' => $name, 'has_detail' => is_array( $s ) && ! empty( $s['has_detail'] ) );
			}
		}
		$found['services']     = $services;
		$found['about']        = ! empty( $f['about'] );
		$found['faq']          = ! empty( $f['faq'] );
		// A quote needs real quotation marks in the text, and a step by step process needs the AI to say so.
		$found['testimonials'] = ! empty( $f['testimonials'] ) && 1 === preg_match( '/["\x{201C}\x{201D}]/u', $text );
		$found['process']      = ! empty( $f['process'] );

		// -- What is missing: the AI's list, then the floor the code insists on -----------------
		$missing   = array();
		$questions = array();
		$add       = function ( $key, $question, $why ) use ( &$missing, &$questions, $answers ) {
			if ( self::answered( $answers, $key ) || isset( $questions[ $key ] ) || 1 !== preg_match( '/^[a-z][a-z0-9_]{0,29}$/', $key ) ) {
				return;
			}
			$questions[ $key ] = $question;
			$missing[]         = array( 'key' => $key, 'question' => $question, 'why' => $why );
		};
		// The floor first, so the hard requirements are never pushed out by the AI's own questions.
		if ( '' === $found['site_name'] && ! self::answered( $answers, 'site_name' ) ) {
			$add( 'site_name', 'What is the name of your ' . TWD_SK_Mode::w( 'practice', $draft['mode'] ) . ', as you want it shown on the site?', 'It goes in the header, the footer and the search results.' );
		}
		if ( '' === $found['person_name'] && ! self::answered( $answers, 'person_name' ) ) {
			$add( 'person_name', 'What is your own name, and what do you do?', 'It goes on the About page.' );
		}
		if ( ! $services && ! self::answered( $answers, 'services' ) ) {
			$add( 'services', 'What do you offer? List each ' . TWD_SK_Mode::w( 'service_kind', $draft['mode'] ) . ' and say a little about it.', 'Each one gets its own page.' );
		}
		foreach ( $services as $i => $s ) {
			if ( ! $s['has_detail'] ) {
				$add( 'service_' . ( $i + 1 ) . '_detail', 'Tell me about "' . $s['name'] . '": what it involves and who it suits.', 'The page for it needs more than a name.' );
			}
		}
		if ( ! $found['about'] && ! self::answered( $answers, 'about' ) ) {
			$add( 'about', 'Tell me your story: your background, training and how you work.', 'It is the heart of the About page.' );
		}
		if ( '' === $found['area'] && ! self::answered( $answers, 'area' ) ) {
			$add( 'area', 'Where do you work? Give the area or address, or say "online only".', 'It goes on the Contact page.' );
		}
		if ( '' === $found['registration'] && ! self::answered( $answers, 'registration' ) ) {
			$add( 'registration', 'Are you registered with a professional body? Give its name and your number, or answer "none".', 'It is shown only if you give it.' );
		}
		if ( ! $found['faq'] && ! self::answered( $answers, 'faq' ) ) {
			$add( 'faq', 'Give me three to five questions people ask you, with your answers. Type "skip" to leave the questions page out.', 'It fills the Questions and answers page.' );
		}
		// Then whatever else the AI asked for.
		if ( isset( $data['missing'] ) && is_array( $data['missing'] ) ) {
			foreach ( $data['missing'] as $m ) {
				if ( ! is_array( $m ) || empty( $m['key'] ) || empty( $m['question'] ) || ! is_string( $m['key'] ) || ! is_string( $m['question'] ) ) {
					continue;
				}
				$why = isset( $m['why'] ) && is_string( $m['why'] ) ? self::clean_text( $m['why'], 160 ) : '';
				$add( strtolower( $m['key'] ), self::clean_text( $m['question'], 300 ), $why );
			}
		}
		$missing = array_slice( $missing, 0, self::MAX_QUESTIONS );
		foreach ( $questions as $key => $q ) {
			if ( ! in_array( $key, array_column( $missing, 'key' ), true ) ) {
				unset( $questions[ $key ] );
			}
		}
		// The text of questions already answered must survive, so the answers still read as question and answer.
		$old = isset( $draft['full_check']['questions'] ) && is_array( $draft['full_check']['questions'] ) ? $draft['full_check']['questions'] : array();
		foreach ( $old as $key => $q ) {
			if ( self::answered( $answers, $key ) && ! isset( $questions[ $key ] ) && is_string( $q ) ) {
				$questions[ $key ] = $q;
			}
		}

		// -- Contact: the email given, else info@ on the site's own domain (said so, never hidden) ------
		$email   = self::answered( $answers, 'email' ) && is_email( $answers['email'] ) ? $answers['email'] : $found['email'];
		$assumed = array();
		if ( '' === $email ) {
			$email = self::default_email();
			if ( '' !== $email ) {
				$assumed[] = array( 'key' => 'email', 'label' => 'Email address shown on the site', 'value' => $email );
			}
		}
		$phone = self::answered( $answers, 'phone' ) && self::phone_grounded( $answers['phone'], $answers['phone'] ) ? $answers['phone'] : $found['phone'];

		$design = self::design_questions( $answers );
		$left   = self::left_out( $found, $answers );

		return array(
			'at'        => gmdate( 'Y-m-d H:i:s' ),
			'hash'      => self::inputs_hash( $draft ),
			'ready'     => ! $missing && ! $design,
			'summary'   => self::summary_lines( isset( $data['summary'] ) ? $data['summary'] : array() ),
			'found'     => $found,
			'missing'   => $missing,
			'design'    => $design,
			'assumed'   => $assumed,
			'contact'   => array( 'email' => $email, 'phone' => $phone, 'email_assumed' => (bool) $assumed ),
			'left_out'  => $left,
			'questions' => $questions,
		);
	}

	private static function summary_lines( $lines ) {
		$out = array();
		if ( is_array( $lines ) ) {
			foreach ( $lines as $line ) {
				$line = is_string( $line ) ? self::clean_text( $line, 200 ) : '';
				if ( '' !== $line && count( $out ) < 8 ) {
					$out[] = $line;
				}
			}
		}
		return $out;
	}

	/** The design questions not answered yet. Always code, never the AI, so the options are real. */
	public static function design_questions( $answers ) {
		$out   = array();
		$packs = TWD_SK_Packs::packs();
		if ( ! self::answered( $answers, 'style' ) || ! isset( $packs[ $answers['style'] ] ) ) {
			$opts = array();
			foreach ( $packs as $slug => $p ) {
				$opts[] = array( 'value' => $slug, 'label' => $p['name'], 'help' => $p['description'] );
			}
			$out[] = array( 'key' => 'style', 'question' => 'Which look do you like best?', 'options' => $opts );
		}
		$tones = self::tones();
		if ( ! self::answered( $answers, 'tone' ) || ! isset( $tones[ $answers['tone'] ] ) ) {
			$opts = array();
			foreach ( $tones as $slug => $t ) {
				$opts[] = array( 'value' => $slug, 'label' => $t['label'], 'help' => '' );
			}
			$out[] = array( 'key' => 'tone', 'question' => 'How should the writing sound?', 'options' => $opts );
		}
		$photos = self::photo_choices();
		if ( ! self::answered( $answers, 'photos' ) || ! isset( $photos[ $answers['photos'] ] ) ) {
			$opts = array();
			foreach ( $photos as $slug => $p ) {
				$opts[] = array( 'value' => $slug, 'label' => $p['label'], 'help' => $p['help'] );
			}
			$out[] = array( 'key' => 'photos', 'question' => 'Photos?', 'options' => $opts );
		}
		return $out;
	}

	/** What will not be on the site because nothing was supplied for it, in plain words. */
	private static function left_out( $found, $answers ) {
		$out = array();
		if ( ! $found['testimonials'] ) {
			$out[] = 'Testimonials (none were pasted). Add them later with Add a section.';
		}
		if ( ! $found['process'] ) {
			$out[] = 'A step by step "how it works" section (the notes do not describe one).';
		}
		if ( self::skip_faq( $answers ) ) {
			$out[] = 'The Questions and answers page (you chose to skip it).';
		}
		if ( ! self::articles_available() ) {
			$out[] = 'The Resources page (the Articles plugin is not active on this site).';
		}
		return $out;
	}

	public static function skip_faq( $answers ) {
		return isset( $answers['faq'] ) && 1 === preg_match( '/^\s*skip\s*\.?\s*$/i', (string) $answers['faq'] );
	}

	public static function articles_available() {
		return function_exists( 'shortcode_exists' ) && shortcode_exists( 'twd_articles' );
	}

	// -- The page plan -------------------------------------------------------------------------

	/**
	 * The sections for one page type. Only sections the supplied facts can fill are included, and, with
	 * "no photos", only layouts without picture spots.
	 */
	public static function parts_for( $type, $found, $photos, $therapy, $articles ) {
		$hero    = array( 'hero', 'twd-sk-hero--compact' );
		$cta     = array( 'cta', 'twd-sk-cta--strip' );
		$notice  = $therapy ? array( array( 'notice', '' ) ) : array();
		$quote   = ! empty( $found['testimonials'] );
		$process = ! empty( $found['process'] );
		switch ( $type ) {
			case 'home':
				$parts = array( $photos ? array( 'hero', 'twd-sk-hero--split' ) : $hero );
				if ( ! empty( $found['services'] ) ) {
					$parts[] = array( 'services', 'twd-sk-services--cards' );
				}
				$parts[] = $photos ? array( 'image_text', '' ) : array( 'text', '' );
				if ( $quote ) {
					$parts[] = array( 'quote', 'twd-sk-quote--cards' );
				}
				if ( $articles ) {
					$parts[] = array( 'resources', '' );
				}
				$parts[] = $cta;
				return $parts;
			case 'about':
				$parts = array( $hero );
				if ( $photos ) {
					$parts[] = array( 'image_text', '' );
				}
				$parts[] = array( 'text', '' );
				if ( $process ) {
					$parts[] = array( 'steps', '' );
				}
				if ( $quote ) {
					$parts[] = array( 'quote', 'twd-sk-quote--pullout' );
				}
				$parts[] = $cta;
				return $parts;
			case 'contact':
				return array_merge( array( $hero, array( 'contact', $photos ? 'twd-sk-contact--split' : '' ) ), $notice );
			case 'service':
				$parts = array( $hero, array( 'text', '' ) );
				if ( $process ) {
					$parts[] = array( 'steps', '' );
				}
				return array_merge( $parts, $notice, array( $cta ) );
			case 'faq':
				return array( $hero, array( 'faq', '' ), $cta );
			case 'resources':
				return array( $hero, array( 'resources', '' ), $cta );
		}
		return array( $hero );
	}

	/** The SEO wording the brief drafted, matched to a page by type (single pages) or by title (services). */
	private static function seo_for( $brief_pages, $type, $title ) {
		foreach ( (array) $brief_pages as $bp ) {
			if ( ! is_array( $bp ) || empty( $bp['seo'] ) || ! is_array( $bp['seo'] ) ) {
				continue;
			}
			$same_title = isset( $bp['title'] ) && is_string( $bp['title'] ) && 0 === strcasecmp( trim( $bp['title'] ), trim( $title ) );
			$same_type  = isset( $bp['type'] ) && $bp['type'] === $type && 'service' !== $type;
			if ( $same_title || $same_type ) {
				return array_intersect_key( $bp['seo'], array_flip( array( 'title', 'description' ) ) );
			}
		}
		return array();
	}

	/**
	 * The list of pages for a build, from the check. Returns array of page inputs for TWD_SK_Build::clean_pages().
	 */
	public static function pages_for( $draft, $brief_pages ) {
		$check    = $draft['full_check'];
		$found    = $check['found'];
		$answers  = (array) $draft['full_answers'];
		$photos   = empty( $draft['no_photos'] );
		$therapy  = 'therapy' === $draft['mode'];
		$articles = self::articles_available();
		$pages    = array();
		$make     = function ( $type, $title, $topic ) use ( &$pages, $found, $photos, $therapy, $articles, $brief_pages ) {
			$pages[] = array(
				'type'  => $type,
				'title' => $title,
				'topic' => $topic,
				'notes' => '',
				'parts' => self::parts_for( $type, $found, $photos, $therapy, $articles ),
				'seo'   => self::seo_for( $brief_pages, $type, $title ),
			);
		};
		$make( 'home', 'Home', '' );
		$make( 'about', 'About', '' );
		foreach ( $found['services'] as $s ) {
			$make( 'service', $s['name'], $s['name'] );
		}
		if ( ! self::skip_faq( $answers ) ) {
			$make( 'faq', 'Questions and answers', '' );
		}
		if ( $articles ) {
			$make( 'resources', 'Resources', '' );
		}
		$make( 'contact', 'Contact', '' );
		return $pages;
	}

	/**
	 * Turn the checked notes into a brief and a page list, and store both in the setup draft.
	 * The brief comes from the same notes-to-brief path the Brief step uses; the code then makes sure
	 * nothing in it is invented (phone) or missing (email, tone, pack).
	 *
	 * @return array|WP_Error { brief, pages, left_out }
	 */
	public static function prepare( $draft ) {
		$check  = isset( $draft['full_check'] ) && is_array( $draft['full_check'] ) ? $draft['full_check'] : null;
		$anyway = ! empty( $draft['full_anyway'] );
		if ( ! $check ) {
			return new WP_Error( 'twd_sk_not_ready', 'Press Check first. The site cannot be built before the notes have been read.' );
		}
		if ( empty( $check['ready'] ) && ! $anyway ) {
			return new WP_Error( 'twd_sk_not_ready', 'Answer the questions first. The site cannot be built while anything is missing, unless you tick "Build a draft anyway".' );
		}
		if ( ! isset( $check['hash'] ) || $check['hash'] !== self::inputs_hash( $draft ) ) {
			return new WP_Error( 'twd_sk_not_ready', 'The notes or answers have changed since they were last checked. Press Check first.' );
		}
		// Building anyway (0.14.0): the design questions take the recipe's defaults, and every unanswered
		// question becomes a marker in the facts, which the pages carry where that fact would go.
		$markers = array();
		if ( empty( $check['ready'] ) && $anyway ) {
			foreach ( isset( $check['missing'] ) && is_array( $check['missing'] ) ? $check['missing'] : array() as $m ) {
				if ( ! empty( $m['key'] ) && ! self::answered( (array) $draft['full_answers'], $m['key'] ) ) {
					$markers[] = self::gap_marker( $m['key'], isset( $m['question'] ) ? $m['question'] : '' );
				}
			}
		}
		$text = self::notes_text( $draft, true );
		if ( $markers ) {
			$text .= "\n\nSTILL TO ANSWER (keep each marker exactly where that fact would go):\n" . implode( "\n", $markers );
		}
		// Read in the type chosen on the first step, which is not saved to the site until the build starts.
		$out  = TWD_SK_Mode::using( $draft['mode'], function () use ( $text ) {
			return TWD_SK_Brief::from_notes( $text );
		} );
		if ( is_wp_error( $out ) ) {
			return $out;
		}
		$brief = $out['brief'];
		$site  = isset( $brief['site'] ) && is_array( $brief['site'] ) ? $brief['site'] : array();
		$found = $check['found'];
		$site['email'] = $check['contact']['email'];
		$site['phone'] = $check['contact']['phone'];
		if ( empty( $site['name'] ) || ! self::grounded( $site['name'], $text ) ) {
			$site['name'] = $found['site_name'];
		}
		if ( ! empty( $site['person_name'] ) && ! self::grounded( $site['person_name'], $text ) ) {
			$site['person_name'] = $found['person_name'];
		}
		$brief['site'] = $site;
		// The facts carry the tone, so every page is written in it.
		$tones = self::tones();
		$tone  = isset( $draft['full_answers']['tone'], $tones[ $draft['full_answers']['tone'] ] ) ? $tones[ $draft['full_answers']['tone'] ]['text'] : '';
		if ( '' !== $tone ) {
			$brief['facts'] = rtrim( isset( $brief['facts'] ) && is_string( $brief['facts'] ) ? $brief['facts'] : '' ) . "\n\nTONE OF VOICE\n" . $tone;
		}
		if ( $markers ) {
			$brief['facts'] = rtrim( isset( $brief['facts'] ) && is_string( $brief['facts'] ) ? $brief['facts'] : '' ) . "\n\nSTILL TO ANSWER\n" . implode( "\n", $markers );
		}
		$pages = self::pages_for( $draft, isset( $brief['pages'] ) ? $brief['pages'] : array() );
		$brief['pages'] = array();
		return array( 'brief' => $brief, 'pages' => $pages, 'left_out' => $check['left_out'], 'markers' => $markers );
	}

	/** A marker for an unanswered question: its key (the same id the question list shows) and a few words of it. */
	public static function gap_marker( $key, $question ) {
		$key   = preg_replace( '/[^a-z0-9_]/', '', strtolower( (string) $key ) );
		$words = trim( preg_replace( '/[\[\]]+/', '', (string) $question ) );
		$room  = self::MARKER_MAX - strlen( $key ) - 1;
		if ( strlen( $words ) > $room ) {
			$words = self::cut( $words, max( 6, $room ) );
		}
		return '[PLACEHOLDER: ' . $key . ( '' !== $words ? ' ' . $words : '' ) . ']';
	}

	// -- After a page is written: picture spots and checks -------------------------------------------

	/** A marker that fits the 60 character limit of a [PLACEHOLDER: ...] token. */
	public static function marker( $what, $search ) {
		$what   = trim( preg_replace( '/[\[\]]+/', '', $what ) );
		$search = trim( preg_replace( '/[\[\]]+/', '', $search ) );
		$fixed  = strlen( 'photo: ' ) + strlen( '. Pexels: ' );
		$room   = self::MARKER_MAX - $fixed;
		if ( strlen( $what ) + strlen( $search ) > $room ) {
			$search = self::cut( $search, max( 8, (int) floor( $room * 0.45 ) ) );
			$what   = self::cut( $what, $room - strlen( $search ) );
		}
		return '[PLACEHOLDER: photo: ' . $what . '. Pexels: ' . $search . ']';
	}

	private static function cut( $s, $max ) {
		if ( strlen( $s ) <= $max ) {
			return $s;
		}
		$s = substr( $s, 0, $max );
		$p = strrpos( $s, ' ' );
		return rtrim( false !== $p && $p > 4 ? substr( $s, 0, $p ) : $s, ' ,.' );
	}

	/** What photo a picture spot wants, and a search to start from, by the picture's class and the page. */
	public static function photo_hint( $class, $page_type, $topic, $mode ) {
		$calm = TWD_SK_Mode::w( 'photo_calm', $mode );
		if ( false !== strpos( $class, 'hero__img' ) || false !== strpos( $class, 'hero__bg' ) ) {
			return array( 'wide photo for the top', TWD_SK_Mode::w( 'photo_top', $mode ) );
		}
		if ( false !== strpos( $class, 'image-text__badge' ) ) {
			return array( 'your logo or badge', 'logo' );
		}
		if ( false !== strpos( $class, 'image-text__img' ) ) {
			if ( 'about' === $page_type ) {
				return array( 'portrait of you', TWD_SK_Mode::w( 'photo_portrait', $mode ) );
			}
			return array( 'photo for this section', $calm );
		}
		if ( false !== strpos( $class, 'contact__img' ) ) {
			return array( 'your space or area', TWD_SK_Mode::w( 'photo_space', $mode ) );
		}
		if ( false !== strpos( $class, 'pictures__img' ) ) {
			return array( 'photo of your work', $calm );
		}
		return array( 'photo for this spot', '' !== $topic ? $topic : $calm );
	}

	/**
	 * Every sample picture left on the page becomes a visible marker that says what photo is wanted (or, with no
	 * photos, is removed). Anything else about the page is left exactly as written.
	 *
	 * @return array { html, spots (int) }
	 */
	public static function picture_spots( $html, $page_type, $topic, $mode, $photos ) {
		$count = 0;
		$html  = preg_replace_callback(
			'/<img\b[^>]*\bsrc\s*=\s*"[^"]*(?:your-image|your-badge)[^"]*"[^>]*>/i',
			function ( $m ) use ( &$count, $page_type, $topic, $mode, $photos ) {
				if ( ! $photos ) {
					return '';
				}
				$class = preg_match( '/\bclass\s*=\s*"([^"]*)"/i', $m[0], $c ) ? $c[1] : '';
				$hint  = self::photo_hint( $class, $page_type, $topic, $mode );
				$count++;
				return self::marker( $hint[0], $hint[1] );
			},
			(string) $html
		);
		return array( 'html' => $html, 'spots' => $count );
	}

	/** Things on a written page that the supplied facts do not back up. Plain sentences. */
	public static function unsupported( $html, $source ) {
		$flags = array();
		$plain = html_entity_decode( strip_tags( (string) $html ), ENT_QUOTES, 'UTF-8' );
		$all   = $plain . ' ' . implode( ' ', self::attr_values( $html, array( 'href' ) ) );
		$src   = strtolower( (string) $source );
		$srcd  = preg_replace( '/\D+/', '', (string) $source );
		$srcm  = preg_replace( '/[\s,]+/', '', $src );
		if ( preg_match_all( '/[A-Za-z0-9._%+-]+@[A-Za-z0-9.-]+\.[A-Za-z]{2,}/', $all, $m ) ) {
			foreach ( array_unique( array_map( 'strtolower', $m[0] ) ) as $email ) {
				if ( 'you@example.com' !== $email && false === strpos( $src, $email ) ) {
					$flags[] = 'The email address ' . $email . ' is not in your notes.';
				}
			}
		}
		if ( preg_match_all( '/(?<![\d])(?:\+\d|0\d)[\d\s().-]{6,}\d(?![\d])/', $plain, $m ) ) {
			foreach ( array_unique( $m[0] ) as $phone ) {
				$d = preg_replace( '/\D+/', '', $phone );
				if ( strlen( $d ) >= 7 && false === strpos( $srcd, $d ) ) {
					$flags[] = 'The number ' . trim( $phone ) . ' is not in your notes.';
				}
			}
		}
		if ( preg_match_all( '/(?:\x{00A3}|&pound;)\s?\d[\d,]*(?:\.\d+)?/u', $plain, $m ) ) {
			foreach ( array_unique( $m[0] ) as $price ) {
				$norm = preg_replace( '/[\s,]+/', '', strtolower( $price ) );
				if ( false === strpos( $srcm, $norm ) ) {
					$flags[] = 'The price ' . trim( $price ) . ' is not in your notes.';
				}
			}
		}
		return $flags;
	}

	private static function attr_values( $html, $names ) {
		$out = array();
		foreach ( $names as $n ) {
			if ( preg_match_all( '/\b' . preg_quote( $n, '/' ) . '\s*=\s*"([^"]*)"/i', (string) $html, $m ) ) {
				$out = array_merge( $out, $m[1] );
			}
		}
		return $out;
	}

	/** Count the [PLACEHOLDER ...] markers that are not picture markers. */
	public static function text_placeholders( $html ) {
		$n = 0;
		if ( preg_match_all( '/\[PLACEHOLDER[^\]]*\]/', (string) $html, $m ) ) {
			foreach ( $m[0] as $token ) {
				if ( ! TWD_SK_Inline::is_spot_text( $token ) ) {
					$n++;
				}
			}
		}
		return $n;
	}

	/**
	 * Everything done to a written page in the full set up, after the pictures have been placed.
	 *
	 * @return array { html, spots, flags[] }
	 */
	public static function finalise( $html, $pg, $draft ) {
		$photos = empty( $draft['no_photos'] );
		$out    = self::picture_spots( $html, $pg['type'], isset( $pg['topic'] ) ? $pg['topic'] : '', $draft['mode'], $photos );
		$flags  = array();
		$n      = self::text_placeholders( $out['html'] );
		if ( $n ) {
			$flags[] = $n . ' place' . ( 1 === $n ? '' : 's' ) . ' the facts did not cover ' . ( 1 === $n ? 'is' : 'are' ) . ' still marked [PLACEHOLDER]. Fill ' . ( 1 === $n ? 'it' : 'them' ) . ' in or delete ' . ( 1 === $n ? 'it' : 'them' ) . ' before publishing.';
		}
		$flags = array_merge( $flags, self::unsupported( $out['html'], self::notes_text( $draft, true ) ) );
		return array( 'html' => $out['html'], 'spots' => $out['spots'], 'flags' => $flags );
	}

	/** A few picture searches that would suit this kind of site, for the report when there are no photos. */
	public static function photo_ideas( $mode ) {
		return array_map( 'trim', explode( ',', TWD_SK_Mode::w( 'photo_ideas', $mode ) ) );
	}
}
