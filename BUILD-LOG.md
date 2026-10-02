# Build log

The one place to see where this project is. Newest at the top. Rules live in CLAUDE.md, reasons in HISTORY.md. Update this file at the end of every piece of work, in the same commit.

## Where we are (last updated at release 0.5.0)

**Released and live in the update file:** 0.5.0. Sites update from Plugins, "Check for updates", "update now".

| Version | What it added |
|---|---|
| 0.5.0 | Ask the AI in the Edit this page tab (by section or whole page, preview before saving, testimonials and the safety notice locked), external AI copy and paste tucked under a toggle |
| 0.4.1 | Edit header and Edit footer pills (after Edit with AI is opened), prompt before closing with unsaved changes |
| 0.4.0 | Safe mode, Site tab, site details with header and footer (Theme Builder JSON), Home, About and Contact starters and the setup command, Search tab, plain-text copy of pages, structured data, image attributes |
| 0.3.1 | Kit page template, Pages tab (new page, publish, switch template), two-level leftover check |
| 0.3.0 | The Edit with AI pop-up: prompt, preview, apply, history |

**Next, in order (agreed with the owner):**
1. 0.5.1: header, footer and style proposals from the AI (structured values, never free HTML or CSS), reusing the site details check, the contrast check and the live preview.
2. 0.5.2: a Voice and facts note in the Site tab (administrators only), added to every AI request; and Add a section.
- Not planned, on purpose: rewriting every page at once, free CSS, free HTML for the header and footer.

**Waiting on other projects (cross-project contract):**
- The Articles & Resource Production plugin (`twd-article-publisher`, repo `therapy-web-designs`) must offer two filters before Ask the AI can appear: `twd_ai_is_configured` (true when a key is saved) and `twd_ai_complete( null, $system, $message, $max_tokens )` (returns text, a WP_Error, or null). Site Kit holds no key and calls no AI service. Status when last checked: not yet added (that plugin was at 1.33.0). The filter names were chosen here; if the other project picks different ones, change them in `class-twd-sk-ai.php`.
- Nothing else is waiting.

**Not yet verified on a real site (needs a human):** importing the two Theme Builder templates on Elementor Pro 4.3.1 and their display condition, real Yoast behaviour (writing its fields, the schema graph filter), the media picker, how a page behaves if the plugin is deactivated, caching on the host, and Ask the AI with a real key. The test plan is in the release notes of each version in the conversation that built it; the 0.4.0 steps are in the section below.

**Known limitation:** a page that still uses the old `[twd_page]` shortcode cannot be mirrored into its content for site search until its content is cleared once after switching it to the kit template.

**Working rules between conversations:** one code conversation per repository. A change that belongs in another repository is written as a short brief, copied to that repository's conversation, and listed above under "Waiting on other projects" until it is done. Private details (client names, copy, contact details, credentials) never go in this public repository.

## 0.4.0 build notes

Piece 2 notes:
- Variants were chosen from the two reference sites (pulled fresh; no new reference folders had been added). Both have a logo on the left with a menu and a button on the right (one has a one-level dropdown, one a text logo with a small subtitle), a menu button on small screens, and a footer of up to three columns (brand and short text, links, contact) over a bottom bar with the copyright and legal links; one has a fixed header. That gave the `bar` header and `columns` footer as the defaults, with `centered`, `split`, `minimal`, `band`, `centered` and `simple` as the other layouts. No names, copy, labels, numbers or paths from the references are in the plugin.
- The Theme Builder templates are generated JSON (`starters/elementor/`). Whether Elementor Pro 4.3.1 accepts them on import, and whether the display condition must be set by hand afterwards, is NOT verified here.
- If the plugin is deactivated, an Elementor Shortcode widget shows its raw `[twd_header]` text (nothing in the plugin can prevent that). Recovery is in the section above (set the template's display condition to nothing or delete it).

Open notes:
- The Theme Builder state on David's site was not reported (the brief had an unfilled placeholder). Assumed none.
- 0.3.1 had not been reported as tested when 0.4.0 work started.

## Safe mode and recovery

Safe mode switches off the 0.4.0 modules and keeps everything from 0.3.1 and earlier working (the Edit with AI tab, preview, apply, history, the page template and Pages tab, packs, the updater, WP-CLI). `[twd_header]` and `[twd_footer]` stay registered and print a plain minimal header and footer, so a Theme Builder header never shows a raw shortcode.

If something looks wrong after the update, in this order:

1. **Switch safe mode on.** Any one of these:
   - Add `define( 'TWD_SK_SAFE_MODE', true );` to `wp-config.php`, above the line that says "That's all, stop editing". Works even when wp-admin is broken. Remove the line to switch it off.
   - Over SSH: `wp twd-sk safe-mode on` (and `off` to switch it back).
   - If the plugin itself will not load: `wp option update twd_sk_safe_mode 1`.
2. **A page made with the kit template shows blank?** Open the page in WordPress admin, change Template to the theme's default, and Update. Or over SSH: `wp post meta update PAGE_ID _wp_page_template default`.
3. **The header or footer looks wrong?** In Elementor, Templates, Theme Builder, set the header or footer template's display condition to nothing (or delete it). The theme's own header and footer return.
4. **Roll back the plugin.** Reinstall the previous zip from the public repo: 0.3.1 is `https://raw.githubusercontent.com/thistwistedyouth/twd-site-kit/5005215a9c09677c3a5de731897b64c6ec1b9c75/dist/twd-site-kit-latest.zip`, 0.3.0 is `https://raw.githubusercontent.com/thistwistedyouth/twd-site-kit/80a40383772989857b355de6a12a1365985c8200/dist/twd-site-kit-latest.zip`. Download it, then Plugins, Add New, Upload Plugin, and replace. (The updater will offer the newer version again; leave it until the cause is fixed.)
5. **Plugin screen unreachable?** Rename `wp-content/plugins/twd-site-kit` to `twd-site-kit-off` over SFTP. Nothing is lost: the page content and history live in the database and come back when the folder is renamed back.

## Earlier releases

See HISTORY.md and the changelog in readme.txt.
