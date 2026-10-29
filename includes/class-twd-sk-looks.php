<?php
/**
 * TWD_SK_Looks: the look of one section, chosen in the Visual editor (0.15.0).
 *
 * A section's look is a few classes on its <section> element: its LAYOUT (one modifier such as
 * twd-sk-faq--two) and its OPTIONS (extra classes such as twd-sk-hero--left, or one of three light
 * backgrounds). The words, pictures and structure inside are never touched. All the rules live here and
 * in the registry, so a new layout or option is data, not new code in the browser.
 *
 *  - Layouts. A section may move only between layouts that use exactly the same pieces (the same
 *    twd-sk-NAME__ element classes in their skeletons), so every word and picture it holds still has its
 *    place. Layouts that need a different structure (a hero with the picture beside the text, or behind
 *    it) are not offered. Computed from the registry skeletons, so it cannot drift.
 *  - Options. Defined below: a toggle (one class) or a choice (one class out of a few). An option can
 *    require another class (the picture options need the full-width picture hero); when the layout moves
 *    so that the requirement is gone, its class is dropped.
 *  - The dark band background is not offered for any section: not every section's text is made for it.
 *
 * apply() works on the stored HTML as text, changes only the class attribute of top-level <section>
 * elements, and checks every class against what the registry allows, so nothing outside the registry can
 * be added. Saving goes through TWD_SK_Store (sanitiser, version, Undo), like every other change.
 *
 *  - Layers (0.15.0). A layout may draw a picture behind the whole section (the text layout "Picture"). Its
 *    picture and overlay are a layer, not pieces, so a plain text section may move to it: on entering, a
 *    picture spot (a marker the Visual editor shows as "Add a picture") and the overlay are put in; on
 *    leaving, the picture and overlay are taken out (Undo brings them back). That is the one change
 *    apply() makes beyond class names.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class TWD_SK_Looks {

	const MAX_LOOKS = 60;
	const STANDARD  = '_';

	// -- What can be chosen --------------------------------------------------------------

	/** The light backgrounds a section may swap between. class => plain label. */
	public static function tones() {
		return array(
			'twd-sk-tone-base'    => 'Plain',
			'twd-sk-tone-surface' => 'Soft',
			'twd-sk-tone-tint'    => 'Tinted',
		);
	}

	/**
	 * The options of one component, in the order they are shown.
	 *
	 * Each: key => array( label, kind (toggle or choice), class (toggle) or choices (class => label, '' = none),
	 * requires (a class that must be on the section), help ).
	 */
	public static function options( $component ) {
		$all = array(
			'text' => self::picture_options( 'twd-sk-text--picture' ),
			'cta'  => self::picture_options( 'twd-sk-cta--band' ),
			'hero' => array(
				'overlay'  => array(
					'label'    => 'Picture overlay',
					'kind'     => 'choice',
					'choices'  => array(
						''                            => 'Normal',
						'twd-sk-hero--overlay-light'  => 'Lighter',
						'twd-sk-hero--overlay-strong' => 'Stronger',
					),
					'requires' => 'twd-sk-hero--image',
					'help'     => 'How much the picture is darkened behind the words.',
				),
				'left'     => array(
					'label'    => 'Words on the left',
					'kind'     => 'toggle',
					'class'    => 'twd-sk-hero--left',
					'requires' => 'twd-sk-hero--image',
				),
				'parallax' => array(
					'label'    => 'Picture moves a little as you scroll',
					'kind'     => 'toggle',
					'class'    => 'twd-sk-hero--parallax',
					'requires' => 'twd-sk-hero--image',
					'help'     => 'Off on phones and for visitors who ask for less motion.',
				),
			),
		);
		return isset( $all[ $component ] ) ? $all[ $component ] : array();
	}

	/**
	 * Words over a picture (0.15.0): the options of the text layout "Picture" and of the call to action band.
	 * The overlay over the picture, a frame behind the words (its tint and its padding), and larger text.
	 * All shared registry classes on the section, styled from tokens.
	 *
	 * @param string $layout The class that puts a picture behind the section (what the options need).
	 */
	private static function picture_options( $layout ) {
		return array(
			'overlay'    => array(
				'label'    => 'Picture overlay',
				'kind'     => 'choice',
				'choices'  => array(
					''                      => 'Normal',
					'twd-sk-overlay-light'  => 'Lighter',
					'twd-sk-overlay-strong' => 'Stronger',
					'twd-sk-overlay-fade'   => 'Fades in from the top',
				),
				'requires' => $layout,
				'help'     => 'How the picture is darkened behind the words.',
			),
			'frame'      => array(
				'label'    => 'Frame behind the words',
				'kind'     => 'toggle',
				'class'    => 'twd-sk-frame',
				'requires' => $layout,
				'help'     => 'A centred panel with a dark tint, so the words read over any picture.',
			),
			'frame_tint' => array(
				'label'    => 'Frame tint',
				'kind'     => 'choice',
				'choices'  => array(
					''                     => 'Normal',
					'twd-sk-frame--light'  => 'Lighter',
					'twd-sk-frame--strong' => 'Stronger',
				),
				'requires' => 'twd-sk-frame',
			),
			'frame_pad'  => array(
				'label'    => 'Frame padding',
				'kind'     => 'choice',
				'choices'  => array(
					''                     => 'Medium',
					'twd-sk-frame--pad-sm' => 'Small',
					'twd-sk-frame--pad-lg' => 'Large',
				),
				'requires' => 'twd-sk-frame',
			),
			'text_size'  => array(
				'label' => 'Larger text',
				'kind'  => 'toggle',
				'class' => 'twd-sk-text-lg',
				'help'  => 'The paragraphs a size up, like an introduction.',
			),
		);
	}

	/**
	 * Layers (0.15.0): what a layout draws behind the whole section, which is not a piece of the words.
	 * component => layout class => array( pieces (the element classes), insert (what a section gets on entering) ).
	 */
	private static function layers( $component ) {
		$all = array(
			'text' => array(
				'twd-sk-text--picture' => array(
					'pieces' => array( 'twd-sk-text__bg', 'twd-sk-text__overlay' ),
					'insert' => "<p>[PLACEHOLDER: image needed]</p>\n<div class=\"twd-sk-text__overlay\"></div>\n",
				),
			),
		);
		return isset( $all[ $component ] ) ? $all[ $component ] : array();
	}

	/** Classes a section gets when it enters a layout (its natural look), unless already there. */
	private static function layout_adds( $component, $layout ) {
		$all = array( 'text' => array( 'twd-sk-text--picture' => array( 'twd-sk-frame' ) ) );
		return isset( $all[ $component ][ $layout ] ) ? $all[ $component ][ $layout ] : array();
	}

	/**
	 * The nearest Look choices for a request the AI cannot do (0.15.0). Each: words that may appear in a
	 * request, and the plain sentence to show. The browser gets the list too (cfg.look.hints).
	 */
	public static function hints() {
		return array(
			array(
				'words' => array( 'picture behind', 'image behind', 'photo behind', 'background picture', 'background image', 'background photo', 'over a picture', 'over the picture', 'over an image', 'over the image', 'over a photo', 'over the photo', 'on top of the picture', 'on top of the image' ),
				'say'   => 'Words over a picture: in the Visual editor click inside the text section, press Look and set Layout to Picture, then click the picture box to choose the picture. A Call to action section on its Band layout has a picture behind it too.',
			),
			array(
				'words' => array( 'overlay', 'darken', 'gradient', 'graduated', 'fade', 'dim the picture', 'dim the image' ),
				'say'   => 'The darkening over a picture is the Look choice "Picture overlay" (Normal, Lighter, Stronger, Fades in from the top) on a Picture text section or a Band call to action.',
			),
			array(
				'words' => array( 'frame', 'panel', 'box behind', 'opacity', 'transparent', 'translucent', 'tint' ),
				'say'   => 'A panel behind the words is the Look choice "Frame behind the words", with "Frame tint" (Normal, Lighter, Stronger) and "Frame padding" (Small, Medium, Large).',
			),
			array(
				'words' => array( 'padding', 'space around', 'more space', 'less space', 'spacing', 'margin', 'gap above', 'gap below' ),
				'say'   => 'Space around the words is "Frame padding" on a frame, and "Space above" and "Space below" in the Look panel of any section.',
			),
			array(
				'words' => array( 'larger text', 'bigger text', 'text larger', 'text bigger', 'text a little larger', 'font size', 'text size', 'smaller text', 'font bigger', 'font larger' ),
				'say'   => 'Text size is the Look choice "Larger text" on a text or call to action section. The sizes themselves are set in the Site tab under Style.',
			),
			array(
				'words' => array( 'background colour', 'background color', 'coloured background', 'colored background', 'tinted background', 'shaded background', 'grey background', 'gray background' ),
				'say'   => 'The background of a section is the Look choice "Background" (Plain, Soft, Tinted).',
			),
			array(
				'words' => array( 'no repeat', 'not repeat', 'without repeating', 'cover the section', 'covering the section', 'stretch', 'fill the section', 'full width' ),
				'say'   => 'A picture behind a section always covers it without repeating; there is nothing to set.',
			),
			array(
				'words' => array( 'heading', 'headings', 'subheading', 'sub-heading', 'title above', 'titles above' ),
				'say'   => 'A heading inside a text section is an h2 or h3 with the class twd-sk-text__subheading before the paragraph it introduces; the Visual editor adds one with "Heading above".',
			),
		);
	}

	/** The hint sentences that match a request, in order, at most three. */
	public static function hints_for( $text ) {
		$text = strtolower( (string) $text );
		$out  = array();
		if ( '' === trim( $text ) ) {
			return $out;
		}
		foreach ( self::hints() as $h ) {
			foreach ( $h['words'] as $w ) {
				if ( false !== strpos( $text, $w ) ) {
					$out[] = $h['say'];
					break;
				}
			}
			if ( count( $out ) >= 3 ) {
				break;
			}
		}
		return $out;
	}

	/** The background option every section built on a light background has. */
	private static function background_option() {
		return array(
			'label'        => 'Background',
			'kind'         => 'choice',
			'choices'      => self::tones(),
			'requires_any' => array_keys( self::tones() ),
		);
	}

	/** Space above or below any section (0.15.0): none, small, as before, large. The classes are shared registry classes. */
	public static function space_option( $where ) {
		$top = 'top' === $where;
		return array(
			'label'   => $top ? 'Space above' : 'Space below',
			'kind'    => 'choice',
			'choices' => array(
				''                                                 => 'Normal',
				'twd-sk-space-' . ( $top ? 'top' : 'bottom' ) . '-0'  => 'None',
				'twd-sk-space-' . ( $top ? 'top' : 'bottom' ) . '-sm' => 'Small',
				'twd-sk-space-' . ( $top ? 'top' : 'bottom' ) . '-lg' => 'Large',
			),
			'help'    => $top ? 'Nudge this section up or down the page without code.' : '',
		);
	}

	/** All options for a component: its own, then space above and below, background last. key => option. */
	public static function all_options( $component ) {
		return self::options( $component ) + array(
			'space_top'    => self::space_option( 'top' ),
			'space_bottom' => self::space_option( 'bottom' ),
			'background'   => self::background_option(),
		);
	}

	/** Every class an option may put on a section. */
	private static function option_classes( $component ) {
		$out = array();
		foreach ( self::all_options( $component ) as $o ) {
			if ( 'toggle' === $o['kind'] ) {
				$out[] = $o['class'];
			} else {
				foreach ( array_keys( $o['choices'] ) as $c ) {
					if ( '' !== $c ) {
						$out[] = $c;
					}
				}
			}
		}
		return $out;
	}

	private static function root( $component ) {
		return 'twd-sk-' . str_replace( '_', '-', $component );
	}

	/** The element classes (twd-sk-NAME__...) a skeleton uses, sorted. */
	private static function elements_of( $html, $component ) {
		$root = self::root( $component ) . '__';
		preg_match_all( '/class="([^"]*)"/', (string) $html, $m );
		$layer = array();
		foreach ( self::layers( $component ) as $l ) {
			$layer = array_merge( $layer, $l['pieces'] );
		}
		$out = array();
		foreach ( $m[1] as $list ) {
			foreach ( preg_split( '/\s+/', trim( $list ) ) as $c ) {
				if ( 0 === strpos( $c, $root ) && ! in_array( $c, $layer, true ) ) {
					$out[ $c ] = true;
				}
			}
		}
		$keys = array_keys( $out );
		sort( $keys );
		return $keys;
	}

	/**
	 * The layouts of a component, each with the pieces it uses.
	 *
	 * @return array layout class ('' for standard) => array( label, group (the pieces, as a string) )
	 */
	private static function layouts( $component ) {
		$c = TWD_SK_Registry::get( $component );
		if ( ! $c ) {
			return array();
		}
		$claimed = self::option_classes( $component );
		$out     = array();
		$std     = $c['skeleton'];
		$named   = array();
		foreach ( $c['variants'] as $class => $v ) {
			if ( in_array( $class, $claimed, true ) ) {
				continue;
			}
			$named[ $class ] = $v;
		}
		// Standard is left out when a named layout is the very same skeleton (the hero's split).
		$std_is_named = false;
		foreach ( $named as $v ) {
			if ( $v['skeleton'] === $std ) {
				$std_is_named = true;
			}
		}
		if ( ! $std_is_named ) {
			$out[''] = array( 'label' => 'Standard', 'group' => implode( ',', self::elements_of( $std, $component ) ) );
		}
		foreach ( $named as $class => $v ) {
			$out[ $class ] = array( 'label' => trim( strtok( $v['label'], ':' ) ), 'group' => implode( ',', self::elements_of( $v['skeleton'], $component ) ) );
		}
		return $out;
	}

	/** The layouts a section on layout $current can move to (including $current), or none when it has no choice. */
	public static function compatible( $component, $current ) {
		$layouts = self::layouts( $component );
		if ( ! isset( $layouts[ $current ] ) ) {
			return array();
		}
		$out = array();
		foreach ( $layouts as $class => $l ) {
			if ( $l['group'] === $layouts[ $current ]['group'] ) {
				$out[] = $class;
			}
		}
		return count( $out ) > 1 ? $out : array();
	}

	/** Sections whose look is never changed here: the safety notice is kept exactly as it is. */
	private static function excluded() {
		return array( 'notice' );
	}

	/** The component id for a section's HTML, or '' when it is not a registry component or its look is not changed here. */
	public static function component_of( $piece ) {
		$id = str_replace( '-', '_', TWD_SK_Sections::type_of( $piece ) );
		return ( TWD_SK_Registry::get( $id ) && ! in_array( $id, self::excluded(), true ) ) ? $id : '';
	}

	/** The classes on a section's own element, or null. */
	private static function classes_of( $piece ) {
		if ( preg_match( '/^<section\b[^>]*\sclass="([^"]*)"/i', $piece, $m ) ) {
			return array_values( array_filter( preg_split( '/\s+/', trim( $m[1] ) ), 'strlen' ) );
		}
		return null;
	}

	/** The layout class on a section ('' for standard), given its classes. */
	private static function current_layout( $component, $classes ) {
		$layouts = self::layouts( $component );
		foreach ( $classes as $cls ) {
			if ( '' !== $cls && isset( $layouts[ $cls ] ) ) {
				return $cls;
			}
		}
		// A hero whose standard layout is the named split has no empty layout.
		return isset( $layouts[''] ) ? '' : null;
	}

	// -- For the browser -----------------------------------------------------------------

	/**
	 * What the Visual editor needs to draw its Look panel: for each component with a choice, its layouts
	 * (short labels) and options. Small and static.
	 */
	public static function config() {
		$out = array( 'components' => array() );
		foreach ( TWD_SK_Registry::all() as $id => $c ) {
			if ( in_array( $id, self::excluded(), true ) ) {
				continue;
			}
			$layouts = array();
			foreach ( self::layouts( $id ) as $class => $l ) {
				$item = array( '' === $class ? self::STANDARD : $class, $l['label'] );
				$adds = self::layout_adds( $id, $class );
				if ( $adds ) {
					$item[] = $adds;
				}
				$layouts[] = $item;
			}
			$options = array();
			foreach ( self::options( $id ) as $key => $o ) {
				$options[] = self::option_item( $key, $o );
			}
			$out['components'][ $id ] = array( 'label' => $c['label'], 'layouts' => $layouts, 'options' => $options );
		}
		// The options every section has (space above and below, background) go once; the browser adds them to each component.
		$out['shared'] = array();
		foreach ( array_diff_key( self::all_options( 'text' ), self::options( 'text' ) ) as $key => $o ) {
			$out['shared'][] = self::option_item( $key, $o );
		}
		$out['standard'] = self::STANDARD;
		$out['hints']    = self::hints();
		return $out;
	}

	private static function option_item( $key, $o ) {
		$item = array( 'key' => $key, 'label' => $o['label'], 'kind' => $o['kind'] );
		if ( 'toggle' === $o['kind'] ) {
			$item['class'] = $o['class'];
		} else {
			$item['choices'] = array();
			foreach ( $o['choices'] as $class => $label ) {
				$item['choices'][] = array( '' === $class ? self::STANDARD : $class, $label );
			}
		}
		if ( isset( $o['requires'] ) ) {
			$item['requires'] = $o['requires'];
		}
		if ( isset( $o['requires_any'] ) ) {
			$item['requires_any'] = $o['requires_any'];
		}
		if ( ! empty( $o['help'] ) ) {
			$item['help'] = $o['help'];
		}
		return $item;
	}

	/**
	 * What to put on a section's element for the browser: the layouts it can move to (a space-separated list,
	 * "_" for standard), or '' when it has no choice. Used by TWD_SK_Inline::mark().
	 */
	public static function layouts_attr( $classes ) {
		$component = '';
		foreach ( $classes as $cls ) {
			if ( 0 === strpos( $cls, 'twd-sk-' ) && false === strpos( $cls, '--' ) && false === strpos( $cls, '__' ) && 0 !== strpos( $cls, 'twd-sk-tone-' ) ) {
				$component = str_replace( '-', '_', substr( $cls, 7 ) );
				break;
			}
		}
		if ( '' === $component || ! TWD_SK_Registry::get( $component ) || in_array( $component, self::excluded(), true ) ) {
			return '';
		}
		$current = self::current_layout( $component, $classes );
		if ( null === $current ) {
			return '';
		}
		$list = self::compatible( $component, $current );
		return implode( ' ', array_map( function ( $c ) {
			return '' === $c ? TWD_SK_Looks::STANDARD : $c;
		}, $list ) );
	}

	// -- Doing it ------------------------------------------------------------------------

	private static function requirement_met( $option, $classes ) {
		if ( isset( $option['requires'] ) && ! in_array( $option['requires'], $classes, true ) ) {
			return false;
		}
		if ( isset( $option['requires_any'] ) && ! array_intersect( $option['requires_any'], $classes ) ) {
			return false;
		}
		return true;
	}

	/**
	 * The classes a section ends up with.
	 *
	 * @param string     $component
	 * @param array      $classes The section's classes now.
	 * @param string|null $layout A layout class, STANDARD, or null to leave the layout as it is.
	 * @param array      $values  option key => class, '' / false for none, true for a toggle that is on.
	 * @return array|WP_Error
	 */
	public static function classes_for( $component, $classes, $layout, $values ) {
		$layouts = self::layouts( $component );
		$current = self::current_layout( $component, $classes );
		$new     = $classes;
		if ( null !== $layout ) {
			$want = self::STANDARD === $layout ? '' : $layout;
			if ( $want !== $current ) {
				if ( ! in_array( $want, self::compatible( $component, (string) $current ), true ) ) {
					return new WP_Error( 'twd_sk_bad_input', 'This section cannot move to that layout, because the layout uses different pieces from the ones it holds.' );
				}
				$new = array_values( array_filter( $new, function ( $cls ) use ( $layouts ) {
					return '' === $cls || ! isset( $layouts[ $cls ] );
				} ) );
				if ( '' !== $want ) {
					array_splice( $new, 1, 0, array( $want ) );
				}
				foreach ( self::layout_adds( $component, $want ) as $cls ) {
					if ( ! in_array( $cls, $new, true ) ) {
						$new[] = $cls;
					}
				}
			}
		}
		$options = self::all_options( $component );
		foreach ( (array) $values as $key => $value ) {
			if ( ! isset( $options[ $key ] ) ) {
				return new WP_Error( 'twd_sk_bad_input', 'That option is not one this section has.' );
			}
			$o = $options[ $key ];
			if ( 'toggle' === $o['kind'] ) {
				$on  = true === $value || 1 === $value || '1' === $value || 'true' === $value || $o['class'] === $value;
				$new = array_values( array_diff( $new, array( $o['class'] ) ) );
				if ( $on && self::requirement_met( $o, $new ) ) {
					$new[] = $o['class'];
				}
				continue;
			}
			$value = self::STANDARD === $value ? '' : $value;
			if ( ! is_string( $value ) || ! array_key_exists( $value, $o['choices'] ) ) {
				return new WP_Error( 'twd_sk_bad_input', 'That choice is not one this option has.' );
			}
			if ( ! self::requirement_met( $o, $new ) ) {
				continue;
			}
			$new = array_values( array_diff( $new, array_keys( $o['choices'] ) ) );
			if ( '' !== $value ) {
				$new[] = $value;
			}
		}
		// An option whose requirement went away with the layout goes with it.
		foreach ( $options as $o ) {
			if ( ! isset( $o['requires'] ) ) {
				continue;
			}
			if ( ! in_array( $o['requires'], $new, true ) ) {
				$drop = 'toggle' === $o['kind'] ? array( $o['class'] ) : array_keys( $o['choices'] );
				$new  = array_values( array_diff( $new, $drop ) );
			}
		}
		return $new;
	}

	/**
	 * Put looks into stored HTML.
	 *
	 * @param string $html
	 * @param array  $looks List of { i (section number), layout? (class or "_"), options? (key => value) }.
	 * @return array|WP_Error { html, changed (sections whose classes are different now) }
	 */
	public static function apply( $html, $looks ) {
		if ( ! is_array( $looks ) || ! $looks ) {
			return array( 'html' => (string) $html, 'changed' => 0 );
		}
		if ( count( $looks ) > self::MAX_LOOKS ) {
			return new WP_Error( 'twd_sk_bad_input', 'That is too many sections to change at once. Save some, then carry on.' );
		}
		$parts   = TWD_SK_Sections::split( (string) $html );
		$changed = 0;
		$spots   = array();
		foreach ( array_values( $looks ) as $look ) {
			if ( ! is_array( $look ) || ! isset( $look['i'] ) || ! is_numeric( $look['i'] ) ) {
				return new WP_Error( 'twd_sk_bad_input', 'A change to a section could not be read. Reload the page and try again.' );
			}
			$i = (int) $look['i'];
			if ( ! isset( $parts['sections'][ $i ] ) ) {
				return new WP_Error( 'twd_sk_conflict', 'The page changed since it was opened, so the section could not be matched. Reload the page and make the change again.' );
			}
			$piece     = $parts['sections'][ $i ];
			$component = self::component_of( $piece );
			$classes   = self::classes_of( $piece );
			if ( '' === $component || null === $classes ) {
				return new WP_Error( 'twd_sk_bad_input', 'Section ' . ( $i + 1 ) . ' is not one whose look can be changed.' );
			}
			$layout = isset( $look['layout'] ) && is_string( $look['layout'] ) ? $look['layout'] : null;
			$values = isset( $look['options'] ) && is_array( $look['options'] ) ? $look['options'] : array();
			$new    = self::classes_for( $component, $classes, $layout, $values );
			if ( is_wp_error( $new ) ) {
				return new WP_Error( $new->get_error_code(), 'Section ' . ( $i + 1 ) . ': ' . $new->get_error_message() );
			}
			if ( $new === $classes ) {
				continue;
			}
			// Every class on the section must be one the registry knows (nothing else can get in this way).
			$allowed = TWD_SK_Registry::allowed_classes();
			foreach ( $new as $cls ) {
				if ( ! in_array( $cls, $classes, true ) && ! isset( $allowed[ $cls ] ) ) {
					return new WP_Error( 'twd_sk_bad_input', 'Section ' . ( $i + 1 ) . ': that look is not one the kit has.' );
				}
			}
			$piece = preg_replace( '/^(<section\b[^>]*\sclass=")[^"]*(")/i', '${1}' . implode( ' ', $new ) . '${2}', $piece, 1 );
			// A layer (a picture behind the section) comes in with its layout and goes out with it.
			$layers = self::layers( $component );
			$was    = self::current_layout( $component, $classes );
			$now    = self::current_layout( $component, $new );
			if ( isset( $layers[ $now ] ) && ! isset( $layers[ $was ] ) && ! self::has_layer( $piece, $layers[ $now ] ) ) {
				$piece   = preg_replace( '/^(<section\b[^>]*>\s*)/i', '${1}' . $layers[ $now ]['insert'], $piece, 1 );
				$spots[] = $i;
			} elseif ( isset( $layers[ $was ] ) && ! isset( $layers[ $now ] ) ) {
				$piece = self::strip_layer( $piece, $layers[ $was ] );
			}
			$parts['sections'][ $i ] = $piece;
			$changed++;
		}
		return array( 'html' => TWD_SK_Sections::join( $parts['gaps'], $parts['sections'] ), 'changed' => $changed, 'spots' => $spots );
	}

	/** True when the section already holds a layer piece or the picture spot the layer puts in. */
	private static function has_layer( $piece, $layer ) {
		foreach ( $layer['pieces'] as $cls ) {
			if ( false !== strpos( $piece, $cls ) ) {
				return true;
			}
		}
		return false !== strpos( $piece, '[PLACEHOLDER: image needed]' );
	}

	/** Take a layer's elements (the picture or its spot, the overlay) out of a section. */
	private static function strip_layer( $piece, $layer ) {
		foreach ( $layer['pieces'] as $cls ) {
			$q     = preg_quote( $cls, '/' );
			$piece = preg_replace( '/<img\b[^>]*\bclass="[^"]*\b' . $q . '\b[^"]*"[^>]*>\s*/i', '', $piece );
			$piece = preg_replace( '/<(div|figure)\b[^>]*\bclass="[^"]*\b' . $q . '\b[^"]*"[^>]*>\s*<\/\1>\s*/i', '', $piece );
		}
		return preg_replace( '/<(p|span|div|figure)>\s*\[PLACEHOLDER: image needed\]\s*<\/\1>\s*/i', '', $piece );
	}
}
