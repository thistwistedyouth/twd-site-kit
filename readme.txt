=== TWD Site Kit ===
Contributors: therapywebdesigns
Tags: pages, design, styles, therapist
Requires at least: 6.0
Tested up to: 6.6
Requires PHP: 7.4
Stable tag: 0.5.3
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

= 0.5.3 =
* New: Ask the AI in the Site tab. Describe a change to the header, the footer or the style and the AI suggests values, which are filled into the boxes you already use. Nothing is saved until you press the normal Save buttons, colours always stay easy to read, and phone numbers, emails, addresses and registration lines can never be changed by the AI.

= 0.5.2 =
* New: Practice facts. One master document about the person and their practice, in the Site tab (administrators only, never shown on the site). The AI uses only this and the site details for anything new it writes.
* New: New page from your practice facts (Pages tab). Draft an About, Contact, Home, service or topic page (for example Working with anxiety) or questions and answers page. It is saved as a draft, nothing is published, and anything the facts do not say stays a visible placeholder. Without an AI key it copies a prompt for an external AI instead.
* New: a quick option to update an existing page from the practice facts.

= 0.5.1 =
* Fixed: the header no longer drops your typed menu when the Site name box is empty. It uses the WordPress site title instead. The footer likewise.

= 0.5.0 =
* New: Ask the AI. When the site has an AI key (set in the Articles plugin), the Edit this page tab can change the page for you. Choose the sections to change, or the whole page, say what you want, and see a preview before anything is saved. Sections you did not choose stay exactly as they are.
* New: testimonials and the safety notice are locked. The AI can change their layout but not their words, unless you say you are supplying new wording.
* Changed: the copy and paste way is still there, under "Use an external AI instead", and its button is now "Copy prompt for external AI". With no AI key, the tab looks as before.
* This plugin does not hold an AI key or contact any AI service. It asks the Articles plugin to do that.

= 0.4.1 =
* New: "Edit header" and "Edit footer" buttons. After you click Edit with AI, small buttons appear on the header and footer of the page. Each opens the pop-up on the Site tab at the right box. They appear for administrators only, never for visitors, and only where the kit header and footer are used.
* New: closing the pop-up with unsaved changes (pasted page text, style, site details, header and footer settings, or search details) now asks first. Keep editing, or close and lose the changes. This applies to the Close button, the Escape key and clicking outside the pop-up.

= 0.4.0 =
* New: Site tab for administrators. Switch the style pack, change colours, fonts and corner roundness with a live preview, and reset. Text that would be hard to read is refused.
* New: site details (name, logo, menu, contact lines, footer text, legal links) with a header and a footer, four layouts each, a mobile menu, and two Elementor Theme Builder templates to import. Each uses one shortcode: [twd_header] and [twd_footer].
* New: Home, About and Contact starter pages and a setup command (wp twd-sk setup, or a button in the Site tab). Everything is a visible placeholder and nothing is published.
* New: Search tab. Search title, description, sharing picture, keep out of search, and the page address, with page checks. Works with Yoast SEO, or prints its own tags when there is no SEO plugin.
* New: a plain-text copy of each page so site search and SEO tools can read it, picture sizes and lazy loading, and structured data for the practice and therapist on the front page.
* New: safe mode (define TWD_SK_SAFE_MODE in wp-config, or wp twd-sk safe-mode on) switches the new features off and keeps the 0.3.1 ones working.

= 0.3.1 =
* New: the "TWD Kit Page" page template. Full width, no theme padding, theme header and footer kept (including an Elementor Theme Builder header and footer), no page builder needed for the page body.
* New: Pages tab in the pop-up. New page makes a draft already set up for the kit. This page shows status and template, with Publish and Unpublish (each asks for a confirmation) and "Switch this page to the kit template" for pages that use the [twd_page] shortcode.
* New: publishing is blocked while "must fix" example text remains (example addresses, sample picture names, sample testimonials and so on) unless you tick an explicit override. Example button wording is only a "check" warning and never blocks. Both levels show in the pop-up and in wp twd-sk check.
* Improved: example link wording in the style guide is meaningful, a new rule stops the AI stating policies or registration status without the therapist's wording, and the leftover check covers more sample text.
* The Edit with AI button now shows on every front-end page for editors (the Pages tab works anywhere); the Edit tab still needs a kit page.

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
