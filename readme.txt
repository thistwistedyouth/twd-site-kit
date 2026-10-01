=== TWD Site Kit ===
Contributors: therapywebdesigns
Tags: pages, design, styles, therapist
Requires at least: 6.0
Tested up to: 6.6
Requires PHP: 7.4
Stable tag: 0.3.0
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Pages for therapist sites built from sanitised HTML and a fixed set of components, with version history, style packs and bundled fonts.

== Description ==

TWD Site Kit stores a page as cleaned HTML made from a fixed set of components, keeps the last ten versions of each page, and shows it with the [twd_page] shortcode. Two style packs (sage and grove) set the colours, fonts and shapes. Fonts are bundled, so a site makes no requests to Google Fonts.

It is a sibling to the Articles and Resource Production Plugin, and is updated from the plugin's own public repository on GitHub, not from WordPress.org. Every update is checked against a sha256 checksum before it is installed.

== Installation ==

1. In WordPress go to Plugins, Add New Plugin, Upload Plugin.
2. Choose the zip and click Install Now, then Activate Plugin.

== Changelog ==

= 0.3.0 =
* New: "Edit with AI" on the front end for signed-in editors. A button (bottom left) opens a pop-up: copy the prompt, paste the AI's result, see what the cleaner changed and any leftover example text, preview it on the real page (nothing is saved), then apply it as a new version. Undo and restore from the last 10 versions. Every apply asks where the facts came from and saves the answer with the version.
* New: secure routes under twd-site-kit/v1 (sign-in nonce, edit permission on the page, size limits and rate limits). No AI key and no outbound calls from the site.

= 0.2.6 =
* New: wp twd-sk check <page_id> lists leftover example text. Saving a page warns about it (nothing is removed).
* Improved: the client AI prompt (image, alt text, heading order, link text, no outcome promises, helplines untouched; examples printed once).

= 0.2.5 =
* New: wp twd-sk prompt <page_id> prints the client AI prompt (rules, style guide, the page's HTML).
* New: loose content directly in a page gets a safe width and side padding. Loose text is wrapped in a paragraph. [PLACEHOLDER] text is kept.

= 0.2.4 =
* New: the resources component styles the articles plugin's grid from the active pack (heading font and title colour on card titles, accent on Read more, body font at 14px or more). No effect when the articles plugin is absent.

= 0.2.3 =
* Fix: body copy at least 16px, eyebrows, small text and buttons at least 14px. Text on bands and buttons reaches 4.5:1 contrast in both packs.

= 0.2.2 =
* Fix: image and text, text with aside and split layouts now share the full container width with fractional columns; the narrow text layout is one centred column.

= 0.2.1 =
* New: self-hosted updater with a checksum check. A "Check for updates" link on the Plugins screen row.

= 0.2.0 =
* New: the one-h1 rule and theme title hiding, eleven components with variants, component styles, style packs, bundled fonts, and the component gallery.

= 0.1.0 =
* First release: page store with version history, the sanitiser, the [twd_page] shortcode and WP-CLI commands.
