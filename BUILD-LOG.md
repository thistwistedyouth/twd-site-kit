# Build log

The one place to see where this project is. Newest at the top. Rules live in CLAUDE.md, reasons in HISTORY.md. Update this file at the end of every piece of work, in the same commit.

## Where we are (branch `studio-0.14`, 27 Oct 2026, work in progress)

**On the branch `studio-0.14`, NOT merged, NOT in the update file:** 0.14.0, built overnight for the studio route (the owner's own production process, which lives in the private clients repository) and the first pilot rebuild on a staging site. Nothing on this branch reaches any site until the owner installs the branch zip by hand and merges. `main` still carries 0.13.0.

**The owner lifted the verify-first rule for this build (27 Oct 2026):** he has tested most of 0.7.0 onward on a real site and reports they work well, without listing which. So 0.14.0 is built on top of 0.7.0 to 0.13.0 as they stand. The "Not yet verified" lists below still stand as the risk list until he confirms each item.

| 0.14.0 piece | What it adds | State |
|---|---|---|
| Fonts | EB Garamond and Inter bundled (variable latin woff2, normal and italic, SIL licence texts) and registered in `TWD_SK_Packs::fonts()` | Built, tested, not on a real site |
| Pack `ink` | Text-led: off-white and ink-black, one deep blue-green for buttons and links, EB Garamond and Inter, small corners, no shadows. Every pair passes 4.5:1 | Built, tested |
| Logo sizes | Four tokens (`logo-height`, `logo-height-sm`, `logo-height-front`, `footer-logo-height`) with defaults, so a pack written before them still reads; the stylesheet reads them; editable in the Site tab under Logo sizes; a site can override them like any token | Built, tested |
| Front page logo | Setting `front_logo` in `twd_sk_chrome` (off by default; brief key `header.front_logo`; Site tab tick box): on the front page the full logo prints larger and centred above the menu; a small logo mark (`logo_mark_id` in the profile, the `logo_mark` picture slot) prints with the site name on other pages and takes the full logo's place when the header shrinks | Built, tested, not on a real site |
| Question groups | `twd-sk-faq--groups`: questions in named groups with a row of links, one group at a time on a wide screen (pure CSS, `:target` and `:has()`, a default group), all groups stacked on a phone or an older browser. No script | Built, tested |
| Prose text | `twd-sk-text--prose`: a narrow, left-aligned reading column with styled sub-headings, lists and quotes | Built, tested |
| Unknown layouts | A recipe or brief that names a layout this version does not know falls back to the standard layout with a plain warning instead of dropping the page | Built, tested |
| `wp twd-sk save --pictures` | Places `src="pic:NAME"` pictures through the same engine as the build before a page is cleaned and saved | Built, tested |
| Old pages | A page stored by 0.13 passes through the 0.14 sanitiser byte for byte (test with a fixture) | Tested |
| One skip link | Hello's own skip link is switched off (filter `hello_elementor_enable_skip_link`, written from memory, so check on a real Hello install) while Site Kit places the header, so a page has one | Built, tested, hook name unverified |
| Marker fragments | Half a marker left by a hand edit (the word without its brackets, an opener with no closer, a stray closing bracket) and the studio's `TO CONFIRM` are must-fix leftovers, so they block publishing and show in the launch check; in the Visual editor a marker is selected as one unit when the cursor lands in it | Built, tested (the editor part not on a real browser) |
| Menu links | The launch check follows every menu link: a page on this site must exist (a draft only warns), an address elsewhere gets one HEAD request | Built, tested |
| Publish guard | `TWD_SK_Guard`: publishing a kit page from wp-admin (editor, Quick Edit, bulk) while must-fix text remains keeps it a draft with a notice and a one-click "publish it anyway"; the kit's own routes are unchanged; a notice on a kit page's edit screen says it is edited on the site | Built, tested, not on a real site |
| WordPress leftovers | The launch check flags the live Sample Page, the Hello world post and the suggested privacy text; Tidy up (setup page button, `POST /site/launch/tidy`, `wp twd-sk launch-check --tidy`) moves the first two to the bin, never deleting | Built, tested |
| Build a draft anyway | Full site set up: with questions still open, a tick box prepares the draft anyway; each open question becomes `[PLACEHOLDER: key words of the question]` in the facts and the strict prompt tells the AI to copy it where that fact belongs; `full_anyway` and `full_markers` in the draft | Built, tested, needs a real key to see end to end |
| Style kits (restyle) | `TWD_SK_Style`: a kit is pack, tokens and header and footer layouts (schema `twd-style-kit/1`, never CSS or markup). `apply` lists every change, runs the contrast check, takes a snapshot of the current look first (five kept, option `twd_sk_style_snapshots`) and then changes the pack, the overrides and the layouts; `--dry-run` lists and saves nothing; `revert` puts the newest snapshot back. Pages are never touched (a test proves the stored HTML is byte for byte the same). `wp twd-sk style status\|snapshot\|apply <pack> [--header=] [--footer=] [--dry-run]\|revert\|swap` | Built, tested, not on a real site |
| Layout swap (level 2) | `TWD_SK_Style::swap( html, component, from, to )` changes the layout class of a kind of section when the new layout has a place for every piece it holds; a section it cannot hold keeps its layout and is named. Words and pictures unchanged. CLI only for now (`wp twd-sk style swap`) | Built, tested |
| Style preview | `?twd_sk_style=PACK` on any front-end page shows that pack to a signed-in administrator only (never cached, ignored for visitors, in wp-admin, for an unknown pack and in safe mode). Nothing is saved | Built, tested, not on a real site |
| Six more fonts | Source Serif 4, Work Sans, Nunito Sans, DM Sans, Fraunces, Playfair Display (variable latin woff2, normal and italic, SIL licence texts), so the two packs still to come (`ember`, `slate`) and the Site tab have a wider choice. Only the two fonts the active pack uses are ever loaded | Built, tested |

**Decisions taken while the owner was away (27 Oct 2026):** see the end of this section once the night's work is done; the full list is kept in the private clients repository's studio folder and copied here in generic form.

**Waiting on other projects (new, 27 Oct 2026):** nothing new yet from this branch.

## Where we are at release 0.13.0 (15 Oct 2026)

**Released and live in the update file:** 0.11.0 (merged to `main` on 14 Oct 2026). **0.12.0 (Full site set up, a test option) is committed on `main` locally and is not pushed until the owner has seen the diff.** The owner lifted the verify-first rule for Tell us (0.11.0) and for the Full site set up, so both are tested together with 0.7.0 to 0.10.2 (see `docs/test-script-0.7-0.9.md`). Everything from 0.7.0 to 0.12.0 is NOT yet tried on a real site: work through that script on a fresh install first. Sites update from Plugins, "Check for updates", "update now".

| Version | What it added |
|---|---|
| 0.13.0 | Shrink on scroll: a Site tab tick box (off by default) so the fixed header gets smaller after a short scroll and returns at the top; a tiny separate script loaded only when it is on; no page shift. NOT YET VERIFIED on a real site |
| 0.12.0 | Full site set up (test option): paste everything in one box, the AI lists what is missing and asks questions on the setup page, the build waits until nothing is missing, pages written only from the facts, photo spots marked with what is wanted and a Pexels search, or no photos. NOT YET VERIFIED on a real site |
| 0.11.0 | Tell us: a button for anyone who can edit pages to send a note; notes kept on the site as private text (12 months by default), administrator panel with statuses, ideas and Copy new notes as markdown, a small version label bottom left for signed-in editors, `wp twd-sk snags`, optional notification email with no message text; Edit text renamed Visual editor. NOT YET VERIFIED on a real site |
| 0.10.2 | Edit text toolbar as a compact bottom-left card, clear of the New Article button; Edit text, Save changes and Cancel in the WordPress admin bar |
| 0.10.1 | Edit text pictures: Add a picture boxes where the AI left a picture marker, Upload from this computer beside the media library, descriptions changed on their own |
| 0.10.0 | Editing rebuilt: Edit text in place (words, links, pictures), one box in Edit with AI that plans a checklist and runs it, glow boxes and a quote slider |
| 0.9.3 | Briefs read reliably (facts as lines, Choose the brief file, errors that say where), Build refuses an unreadable brief, building again refreshes the setup's own menu and button and lists old pages |
| 0.9.2 | No page or brief too long for the AI: long pages written a few sections at a time, cut-off replies split and retried, a pasted brief checked with no AI, long notes kept as the facts |
| 0.9.1 | Header and footer placed by Site Kit on the Hello theme, no Elementor Pro needed (Site tab "Who places them"; Pro templates still win where they apply) |
| 0.9.0 | The launch check: every page and the site-wide things to fix or look at before going live, with publishing of ready pages (setup page Launch step, Site tab, `wp twd-sk launch-check`) |
| 0.8.4 | Starter sets: a starting point per kind of site (counselling, coaching, mobile hire, trades, studio or classes), chosen on the setup page or with `wp twd-sk build --set` |
| 0.8.3 | Keep in view: choose which part of each picture stays visible when a page crops it (media library or setup page) |
| 0.8.2 | Colours from the logo: a Site tab button (previewed until saved), a setup page tick box, and `wp twd-sk brand`; every colour checked for contrast |
| 0.8.1 | The Enquiry form section: name, email, phone, message and consent, emailed to the site address; spam trap, signed token and limits; nothing stored |
| 0.8.0 | Five new sections: Pictures (a grid with captions), Logos strip, Prices (cards or a list), Opening hours, Areas covered (with a map link) |
| 0.7.3 | Add a section in the Edit with AI pop-up: choose where, the kind and layout, and (with a key) let the AI write it from the facts; preview, then apply |
| 0.7.2 | The Pages step in the setup page: the list of pages and the sections on each, changed before anything is built; `sections` in a brief's pages |
| 0.7.1 | The setup page (Settings > Set up site, and a Set this site up link on a fresh site's front page): site type, style, brief or plain notes, pictures (slots and named pictures the AI can place), then a step-by-step build of every page as a draft. `wp twd-sk build` runs the same build. The build log shows in the site report |
| 0.7.0 | Therapy or business mode: one admin switch (Site tab, `wp twd-sk mode`, or `mode` in a site brief) that changes the editor and AI wording, the facts outline, page types, the interview prompt and the search details type. Therapy wording unchanged |
| 0.6.2 | `wp twd-sk doctor` and the Site tab's Site report: a plain-text, paste-safe report of the site's state |
| 0.6.1 | Site brief: one JSON file for a whole site, interview prompt for a Claude chat, check then apply, draft outline pages with Fill with AI |
| 0.6.0 | A simpler pop-up: Site tab sections, history, extras and the external AI route are closed fold-outs, the Edit screen is one clear step |
| 0.5.3 | Ask the AI in the Site tab for the header, footer and style (structured values filled into the boxes, never saved by themselves, contrast still enforced) |
| 0.5.2 | Practice facts (admin only master document) and drafting new pages from them (About, Contact, Home, service or topic, Q and A), by AI or with a prompt for an external AI |
| 0.5.1 | Fix: a missing Site name no longer makes the header ignore the typed menu (it uses the WordPress site title) |
| 0.5.0 | Ask the AI in the Edit this page tab (by section or whole page, preview before saving, testimonials and the safety notice locked), external AI copy and paste tucked under a toggle |
| 0.4.1 | Edit header and Edit footer pills (after Edit with AI is opened), prompt before closing with unsaved changes |
| 0.4.0 | Safe mode, Site tab, site details with header and footer (Theme Builder JSON), Home, About and Contact starters and the setup command, Search tab, plain-text copy of pages, structured data, image attributes |
| 0.3.1 | Kit page template, Pages tab (new page, publish, switch template), two-level leftover check |
| 0.3.0 | The Edit with AI pop-up: prompt, preview, apply, history |

**Next, in order (agreed with the owner, 12 Oct 2026; full spec in `docs/plan-modes-and-setup.md`):**
- A. DONE in 0.7.0: therapy and business mode (`TWD_SK_Mode`, option `twd_sk_mode`, default therapy, one word list per mode).
- B. DONE in 0.7.1: the setup page (Settings > Set up site), the admin-only front-page button on a fresh install, picture slots and named custom pictures, one build engine (`TWD_SK_Build`) shared with `wp twd-sk build`, all pages as drafts.
- C. After that (the owner said on 12 Oct: build all of these now, beta stage, test afterwards): DONE in 0.7.2 the plan step (sitemap and section outline before build), DONE in 0.7.3 add a section by asking, 0.8.x more section types, brand from logo, picture help, starter sets per business type, 0.9.0 launch check. ALL DONE (0.8.0 sections, 0.8.1 enquiry form, 0.8.2 colours from the logo, 0.8.3 keep in view, 0.8.4 starter sets, 0.9.0 launch check).
- D. Next: the owner tests 0.7.0 to 0.11.0 on a fresh install (`docs/test-script-0.7-0.9.md`); fixes from that come first. Then the earlier list below.

**Earlier list (4 Oct 2026), still standing after A and B:**
1. DONE in 0.6.2: the doctor report.
2. Owner approves the plans for items 3 to 6 below, answering the seven decisions in the 4 Oct plan (verified registration definition, inline fact tags, publish blocking scope, image matching, typed-details form, confirmation sheet, hosting SSH). PLANNED, NOT APPROVED: do not build them until the owner says so.
3. Site brief v2 (later, version to be decided): image slots, and a source tag on every fact and registration line; the publish check counts unverified registration lines as must fix.
4. Interview prompt v2 (later, version to be decided): transcript in; facts, brief, gaps and questions out, with a quote behind each fact; a client confirmation sheet; a typed-details form.
5. DONE (no release): generic client-folder template in `docs/client-folder/` (layout, client brief template, intake form field list for ONE Elementor Pro form on one page with steps, call questions, 30 minute run sheet, photo shot list, recording and privacy notes). The owner chose one form, not a form per stage; Elementor forms cannot save and resume, so the form is kept short and photos are optional. Interview prompt v2 (item 4) will read the form export plus the transcript, which removes the separate typed-details form from its plan.
6. Plan only, until a second-site timing test: base site and provisioning.
7. In parallel, owner: verify 0.4.0 to 0.6.2 on the pilot site (lists below), including Ask the AI now that the articles plugin has its filters.
- Not planned, on purpose: rewriting every page at once, free CSS, free HTML for the header and footer.

**Ideas for later (saved 15 Oct 2026, not started, owner to decide):**
- **Standalone HTML export, to show clients are not tied to the plugin.** A "Download as a standalone site" button (Site tab) and `wp twd-sk export`: a zip with one HTML file per page (the stored page inside its `.twd-sk-page` wrapper, with the header, footer and form written out as plain HTML), one stylesheet (the kit CSS with the chosen style's variables and the font declarations built in, so it works alone), the font files and the pictures, and a README with paste steps for a plain WordPress site (Custom HTML block). Pages are already plain `twd-sk-` classed HTML with no scripts or builder markup, so the page content travels as it is; what depends on the plugin is the stylesheet and variables, `[twd_header]`, `[twd_footer]`, `[twd_enquiry]`, `[twd_articles]` (the Articles plugin) and two tiny scripts (mobile menu, testimonial slider; pages work without them). Open questions: leave the enquiry form out or write a plain form; leave the Resources grid out or export the current articles as plain links; likely 0.13.0 after 0.12.0 is tested. Clients keep their words, pictures and layout but lose the Visual editor and the AI tools, and the README would say so.
- **Visual editor "No photo here" button:** switch a photo spot's section to a layout without a picture after the build (needs a section by section conversion).

**Ideas that need another project (not started, owner to decide):**
- AI alt text from the picture itself: the Articles plugin would need to offer a third filter that can send a picture to the AI (for example `twd_ai_describe_image( null, $attachment_id )` returning text or a WP_Error). Site Kit would then offer a suggestion beside each picture's description, shown for approval, never saved by itself.

**Waiting on other projects (cross-project contract):**
- Nothing. The Articles & Resource Production plugin (`twd-article-publisher`, repo `therapy-web-designs`) now offers the two filters Site Kit uses, from its version 1.34.0 (checked 4 Oct 2026; re-checked 11 Oct 2026, the filters are unchanged, that plugin is now at 1.37.1 on a branch not yet merged to its main): `twd_ai_is_configured` (true when a key is saved) and `twd_ai_complete( null, $system, $message, $max_tokens )` (returns text, a WP_Error, or null; max tokens clamped to 256 to 8000). The names match what Site Kit calls. Site Kit holds no key and calls no AI service. Ask the AI, drafting pages with AI, Fill with AI and the Site tab suggestions appear on a site only when that plugin is at 1.34.0 or later with a key saved.

**Not yet verified on a real site (needs a human):** importing the two Theme Builder templates on Elementor Pro 4.3.1 and their display condition, real Yoast behaviour (writing its fields, the schema graph filter), the media picker, how a page behaves if the plugin is deactivated, caching on the host, Ask the AI, drafting pages and the Site tab suggestions with a real key (the articles plugin side is now in place), importing a real site brief, the fold-outs on a real phone, (0.7.0) switching a fresh site to business mode and checking the Site tab labels, a drafted page's wording and the LocalBusiness search details, and (0.7.1) the whole setup page on a fresh install: the front-page link, the real media library picker and uploads, notes to a brief and a full build with a real key, the pictures landing where expected, the menu links working once the pages are published, and `wp twd-sk build` over SSH, and (0.8.0/0.8.1) the new sections on a real theme and phone, and the enquiry form actually delivering email from 20i (check the spam folder, and set up an SMTP plugin if mail does not arrive), and (0.9.1) the header and footer placed by Site Kit on a real Hello install: the hook names `hello_elementor_header_footer` and Elementor Pro's `elementor_location_exits` were written from memory, so check Hello's own header really goes, ours shows once at the top and bottom of every page (including Elementor Full Width pages, and none on Canvas), and an Elementor Pro template, if present, takes over. The test plan is in the release notes of each version in the conversation that built it; the 0.4.0 steps are in the section below.

**Not yet verified (0.12.0):** the whole Full site set up on a real site with a real AI key: how well the AI finds what is missing in real notes, that the questions are plain and sensible, that every page really is written without invented facts, the Pexels notes in the photo spots in the Visual editor, the no photos layouts on a phone, the info@ default, the Resources page with the Articles plugin, and the report at the end. Not built yet: the Visual editor button that switches a photo spot to a layout without a photo.

**Not yet verified (0.11.0):** the Tell us button position above the Visual editor button on a real theme and phone, the pop-up, sending and deleting a note as an editor, the administrator panel and Copy new notes as markdown (clipboard on a real browser), the Site tab section, the notification email actually arriving, the daily retention sweep, WordPress's personal data export and erase tools, `wp twd-sk snags` over SSH, safe mode hiding the button, and the version label bottom left (clear of every floating button on a phone, readable on a dark page).

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
