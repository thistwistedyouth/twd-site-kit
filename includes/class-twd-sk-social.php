<?php
/**
 * Social buttons (0.16.0).
 *
 * A social button in a page is an ordinary kit button link that also carries a network class, for example
 * <a class="twd-sk-btn twd-sk-btn--outline twd-sk-social twd-sk-social--whatsapp" href="https://www.example.com/...">WhatsApp</a>.
 * The page stores only that. When the page is shown, decorate() puts the network's icon in front of the words
 * and wraps the words in a span, so the stylesheet can show the words, or hide them for an icon-only button.
 * Nothing drawn here is ever stored, the sanitiser is untouched (it still strips svg from anything a person
 * pastes), and the icons live in this file as fixed strings: never from page HTML, a client upload or an address.
 *
 * Links must be https. A link on another host than the network's own, or one that is not https, gets no icon and
 * is named by the quality check, so a wrong or old address is seen before it is published.
 *
 * Checks nobody and writes nothing. Does nothing in safe mode (the buttons are still plain, working links).
 *
 * @package TWD_Site_Kit
 */

defined( 'ABSPATH' ) || exit;

class TWD_SK_Social {

	/**
	 * The networks: label, the hosts a link may use, and the icon as inner SVG markup in a 24 by 24 box.
	 * Strokes and fills use currentColor so the icon always has the colour (and the contrast) of the button's words.
	 */
	public static function networks() {
		return array(
			'whatsapp'  => array(
				'label' => 'WhatsApp',
				'hosts' => array( 'wa.me', 'whatsapp.com' ),
				'svg'   => '<path d="M12 3.2a8.8 8.8 0 0 0-7.6 13.2L3.2 20.8l4.5-1.2A8.8 8.8 0 1 0 12.0 3.2z" fill="none" stroke="currentColor" stroke-width="1.9" stroke-linejoin="round"/><path d="M9.1 7.9c.3-.5.7-.5 1-.1l.8 1.4c.2.3.1.6-.1.9l-.5.6c.7 1.3 1.7 2.2 3 2.9l.6-.6c.3-.3.6-.3.9-.1l1.4.8c.5.3.5.7.1 1.1-.7.9-1.8 1.2-2.9.8-2.6-1-4.4-2.9-5.2-5.3-.2-.9.1-1.7.9-2.4z" fill="currentColor"/>',
			),
			'facebook'  => array(
				'label' => 'Facebook',
				'hosts' => array( 'facebook.com', 'fb.com', 'fb.me', 'messenger.com', 'm.me' ),
				'svg'   => '<path d="M13.6 21v-7.6h2.6l.4-3h-3V8.6c0-.9.3-1.5 1.5-1.5h1.6V4.4c-.3 0-1.2-.1-2.3-.1-2.3 0-3.8 1.4-3.8 3.9v2.2H8v3h2.6V21h3z" fill="currentColor"/>',
			),
			'instagram' => array(
				'label' => 'Instagram',
				'hosts' => array( 'instagram.com', 'instagr.am' ),
				'svg'   => '<rect x="3.6" y="3.6" width="16.8" height="16.8" rx="5" fill="none" stroke="currentColor" stroke-width="1.9"/><circle cx="12" cy="12" r="3.9" fill="none" stroke="currentColor" stroke-width="1.9"/><circle cx="17.1" cy="6.9" r="1.2" fill="currentColor"/>',
			),
			'youtube'   => array(
				'label' => 'YouTube',
				'hosts' => array( 'youtube.com', 'youtu.be' ),
				'svg'   => '<path fill-rule="evenodd" clip-rule="evenodd" d="M21.6 7.2a2.5 2.5 0 0 0-1.8-1.8C18.2 5 12 5 12 5s-6.2 0-7.8.4A2.5 2.5 0 0 0 2.4 7.2C2 8.8 2 12 2 12s0 3.2.4 4.8a2.5 2.5 0 0 0 1.8 1.8C5.8 19 12 19 12 19s6.2 0 7.8-.4a2.5 2.5 0 0 0 1.8-1.8C22 15.2 22 12 22 12s0-3.2-.4-4.8zM10 15V9l5.2 3L10 15z" fill="currentColor"/>',
			),
		);
	}

	/** The network a class list names, or '' (the first one found). */
	public static function network_of( $classes ) {
		foreach ( array_keys( self::networks() ) as $key ) {
			if ( 1 === preg_match( '/(?:^|\s)twd-sk-social--' . $key . '(?:\s|$)/', $classes ) ) {
				return $key;
			}
		}
		return '';
	}

	/** Does this web address belong to the network? https only; the host, or a sub-domain of one of its hosts. */
	public static function link_fits( $network, $href ) {
		$nets = self::networks();
		if ( ! isset( $nets[ $network ] ) || ! is_string( $href ) ) {
			return false;
		}
		$href = html_entity_decode( trim( $href ), ENT_QUOTES, 'UTF-8' );
		$parts = parse_url( $href );
		if ( ! is_array( $parts ) || empty( $parts['host'] ) || empty( $parts['scheme'] ) || 'https' !== strtolower( $parts['scheme'] ) || isset( $parts['user'] ) ) {
			return false;
		}
		$host = strtolower( $parts['host'] );
		foreach ( $nets[ $network ]['hosts'] as $h ) {
			if ( $host === $h || ( strlen( $host ) > strlen( $h ) && '.' . $h === substr( $host, -strlen( $h ) - 1 ) ) ) {
				return true;
			}
		}
		return false;
	}

	/** The icon element for a network (a span holding an svg, hidden from screen readers), or ''. */
	public static function icon( $network ) {
		$nets = self::networks();
		if ( ! isset( $nets[ $network ] ) ) {
			return '';
		}
		return '<span class="twd-sk-social__icon" aria-hidden="true"><svg viewBox="0 0 24 24" focusable="false">' . $nets[ $network ]['svg'] . '</svg></span>';
	}

	/**
	 * Put the icons in. Called when a page is drawn for display, never on stored HTML. A link that is already
	 * decorated, has no network class, is not https or sits on another host is left exactly as it is.
	 */
	public static function decorate( $html ) {
		if ( ! is_string( $html ) || false === strpos( $html, 'twd-sk-social--' ) ) {
			return $html;
		}
		if ( class_exists( 'TWD_SK_Safe' ) && TWD_SK_Safe::on() ) {
			return $html;
		}
		return preg_replace_callback(
			'/<a\b([^>]*)>(.*?)<\/a>/is',
			function ( $m ) {
				if ( 1 !== preg_match( '/\sclass="([^"]*)"/', $m[1], $c ) ) {
					return $m[0];
				}
				$network = self::network_of( $c[1] );
				if ( '' === $network || false !== strpos( $m[2], 'twd-sk-social__icon' ) ) {
					return $m[0];
				}
				if ( 1 !== preg_match( '/\shref="([^"]*)"/', $m[1], $h ) || ! self::link_fits( $network, $h[1] ) ) {
					return $m[0];
				}
				return '<a' . $m[1] . '>' . self::icon( $network ) . '<span class="twd-sk-social__label">' . $m[2] . '</span></a>';
			},
			$html
		);
	}

	/**
	 * Social buttons whose link will not show an icon, in plain words (for the quality check).
	 *
	 * @return string[] one sentence each, at most 5
	 */
	public static function problems( $html ) {
		$out = array();
		if ( ! is_string( $html ) || false === strpos( $html, 'twd-sk-social--' ) ) {
			return $out;
		}
		$nets = self::networks();
		if ( preg_match_all( '/<a\b([^>]*)>/i', $html, $links ) ) {
			foreach ( $links[1] as $attrs ) {
				if ( 1 !== preg_match( '/\sclass="([^"]*)"/', $attrs, $c ) ) {
					continue;
				}
				$network = self::network_of( $c[1] );
				if ( '' === $network ) {
					continue;
				}
				$href = preg_match( '/\shref="([^"]*)"/', $attrs, $h ) ? $h[1] : '';
				if ( ! self::link_fits( $network, $href ) ) {
					$label = $nets[ $network ]['label'];
					$out[] = 'A ' . $label . ' button does not link to ' . $label . ' over https, so it shows no icon. Use the full https:// address of the page.';
				}
			}
		}
		return array_slice( array_values( array_unique( $out ) ), 0, 5 );
	}
}
