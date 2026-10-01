=== TWD Site Kit ===
Contributors: therapywebdesigns
Tags: pages, design, styles, therapist
Requires at least: 6.0
Tested up to: 6.6
Requires PHP: 7.4
Stable tag: 0.2.3
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
