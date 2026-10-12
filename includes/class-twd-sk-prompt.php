<?php
/**
 * The client AI prompt. Plain text a therapist (or business owner) pastes into an AI chat to edit a page.
 *
 * Built from three parts: fixed rules, a style guide generated from the registry
 * (every component, variant and class, with an example) and the page's current stored
 * HTML. Nothing here renders anything or touches the database.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class TWD_SK_Prompt {

	/**
	 * The fixed rules, one per line.
	 */
	public static function rules() {
		$who  = TWD_SK_Mode::w( 'person' );
		$whos = TWD_SK_Mode::w( 'persons' );
		return array(
			'Ask a question first only if the request is unclear or would remove content. Otherwise make the change, then say in one line what changed.',
			'Make one change at a time. Do not rewrite the whole page unless asked.',
			'Return the full page HTML in one code block, ready to paste back. Say nothing inside the code block except HTML.',
			'The page has exactly one h1, in the first hero, with the class twd-sk-hero__title. Every other heading is an h2 or lower, and headings stay in order (an h3 goes under an h2, never straight under the h1).',
			'Use only the components and classes listed in the style guide below. Do not invent classes.',
			'No scripts, no inline styles, no forms and no iframes. If the ' . $who . ' wants a map, a contact form or anything like that, tell them to ask their web designer.',
			'Images: keep every existing image address exactly as it is. For a new image, write [PLACEHOLDER: image needed] where the image goes, and ask the ' . $who . ' for the address after they have uploaded it. Never invent a file name.',
			'Every image needs descriptive alt text that says what the picture shows. Use empty alt text only for a purely decorative image.',
			'Link text must be meaningful on its own. Write "Read about anxiety support", never "click here" or "read more".',
			TWD_SK_Mode::w( 'rule_invent' ),
			TWD_SK_Mode::w( 'rule_policies' ),
			TWD_SK_Mode::w( 'rule_claims' ),
			TWD_SK_Mode::w( 'rule_privacy' ),
			TWD_SK_Mode::w( 'rule_notice' ),
			'Keep the ' . $whos . ' own voice. Reuse their wording where you can and do not make it sound generic.',
			'Do not use em dashes or en dashes. Use a comma, a full stop or a hyphen instead.',
		);
	}

	/**
	 * The style guide as text, generated from the registry.
	 */
	public static function style_guide() {
		$data = TWD_SK_Registry::style_guide_data();
		$out  = array();
		$out[] = 'A page is a list of sections, one component after another. Each section is a section element with the classes shown. Copy an example, then change the words. Every word in the examples is a sample: replace all of it with the ' . TWD_SK_Mode::w( 'persons' ) . ' own wording, including the words on buttons and links, and every address.';
		$out[] = '';
		foreach ( $data['components'] as $id => $c ) {
			$out[] = '### ' . $c['label'] . ' (' . $id . ')';
			$out[] = $c['description'];
			$out[] = 'Classes: ' . implode( ' ', $c['classes'] );
			if ( ! empty( $c['shortcodes'] ) ) {
				$out[] = 'Shortcodes allowed: [' . implode( '], [', $c['shortcodes'] ) . ']';
			}
			$out[] = 'Example:';
			$out[] = $c['skeleton'];
			foreach ( $c['variants'] as $class => $v ) {
				if ( $v['skeleton'] === $c['skeleton'] ) {
					$out[] = 'Variant ' . $class . ' (' . $v['label'] . '): same as the example above.';
					continue;
				}
				$out[] = 'Variant ' . $class . ' (' . $v['label'] . '):';
				$out[] = $v['skeleton'];
			}
			$out[] = '';
		}
		$out[] = '### Shared classes (any component may use these)';
		// The gallery label bar is for the gallery file only, never for a client page.
		$shared = array_diff( $data['shared'], array( 'twd-sk-gallery-label' ) );
		$out[]  = implode( ' ', $shared );
		return implode( "\n", $out );
	}

	/**
	 * The whole prompt.
	 *
	 * @param string $page_html The page's current stored HTML (may be empty).
	 * @param string $facts_block Practice facts to include (only for a person allowed to see them).
	 */
	public static function build( $page_html, $facts_block = '' ) {
		$out   = array();
		$out[] = 'You are helping ' . TWD_SK_Mode::w( 'a_person' ) . ' edit one page of their website. The page is HTML made from a fixed set of components.';
		$out[] = '';
		$out[] = '## Rules';
		foreach ( self::rules() as $i => $rule ) {
			$out[] = ( $i + 1 ) . '. ' . $rule;
		}
		$out[] = '';
		$out[] = '## Style guide';
		$out[] = self::style_guide();
		$out[] = '';
		if ( '' !== $facts_block ) {
			$out[] = '## Facts about the ' . TWD_SK_Mode::w( 'person' );
			$out[] = $facts_block;
			$out[] = '';
		}
		$out[] = '## The page as it is now';
		$page_html = trim( (string) $page_html );
		if ( '' === $page_html ) {
			$out[] = 'The page is empty so far.';
		} else {
			$out[] = '```html';
			$out[] = $page_html;
			$out[] = '```';
		}
		$out[] = '';
		$out[] = 'Start by asking the ' . TWD_SK_Mode::w( 'person' ) . ' what they would like to change.';
		return implode( "\n", $out ) . "\n";
	}
}
