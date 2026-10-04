# History and decisions

Why things are the way they are. Newest first. Rules live in CLAUDE.md.

## Open questions

### Card-type vocabulary overlap (parked, not solved)

Two different card vocabularies exist in the wider ecosystem and they overlap:

- The articles plugin's **Summary Book** (`TWD_AP_Summary_Book`): `text`, `quote`, `question`.
- **trd-resource-reader** (standalone plugin from the Therapy Resource Directory): `insight`, `question`, `quote`, `part`, `filing`, `image`, `link`, `video`, `qr`.

`quote` and `question` appear in both with possibly different meaning and markup. Nothing here depends on it yet. Decide before any Site Kit component or AI output starts to emit card types, so that one vocabulary (or an explicit mapping) is used everywhere. Not solved in slice 1 on purpose.

### Article Assist and `summary_book`

Checked 2026-09-30 against `15 TRD Article Assist.php` (therapy-resource-directory, last commit 73dcd45). It does NOT output `summary_book`. Its prompt asks for strict JSON with `title`, `seo_title`, `meta_description`, `category`, `tags`, `html` only, and the server handler passes through only those. The articles plugin's CLAUDE.md (v1.32.0) says the same ("Article Assist still doesn't produce the `summary_book` field yet"). The plugin side (`summary_book.slides` in pasted JSON) is ready and waiting.

## v0.6.1 (the site brief)

- **The builder makes the first version, the client tweaks.** The initial site is built by the designer, from a conversation with the therapist, and the client (an editor) only adjusts afterwards. So the heavy input is one file, made outside the plugin, rather than a wizard clients would see.
- **The interview happens in a normal Claude chat,** not in the plugin. It costs nothing to build, the questions can be improved without a release, and the plugin stays free of an AI conversation loop. The prompt is generated from code (allowed layouts, packs and page types) so it cannot drift from what the importer accepts.
- **Check, then apply.** Pasting a file shows exactly what would happen, and filled-in details are kept unless overwrite is ticked. Anything not allowed is dropped with a sentence and nothing is trusted: the same validators the Site tab saves with decide.
- **Pages become draft outlines, then AI fills them one at a time.** Creating a page by AI inside one request would risk a timeout and cost for twenty pages. Outlines are instant and free, and each has a Fill with AI button.
- **No pictures in the file.** Pictures are media library items and cannot travel in JSON. They stay a manual checklist.

## v0.6.0 (a simpler pop-up)

- **The pop-up grew feature by feature and showed all of it to everyone.** The client (an editor) needs three things: change this page, make a page, set its search details. The builder (an administrator) needs the rest once. Detail is now folded away rather than removed, so nothing was deleted and nothing needs a new permission.
- **Fold-outs, not modes.** A Simple and Builder switch was proposed and dropped: the client is an editor, who already never sees the Site tab, so a mode switch would add a control without removing anything the client sees. The remaining clutter (history, save note, extra facts, the external AI route) is folded instead.
- **The save note moved into More options,** and a remix pre-fills it, so a client applies a change with one button. The builder can still say where the facts came from.

## v0.5.3 (AI suggestions for header, footer and style)

- **Structured values, not markup.** Header, footer and style are settings, not free content. Asking the AI for HTML or CSS here would risk the mobile menu, the legal links and readable contrast for no gain, so it answers with the same fields the Site tab edits and each one is validated by the code that saves it.
- **Fill the form, do not save.** A proposal is put into the boxes the person already uses, so it is visible, editable, covered by the unsaved prompt and undoable, and saving needs the normal button. This also removed the need for any new write path.
- **Where it lives.** It sits in the Site tab beside the thing it changes, not in the page editor. Header and footer changes are administrator work, so they live where administrators already are, and the page editor stays about one page.
- **Contact details and registration lines are out of reach** of the AI on purpose: they are facts the therapist must supply, and an invented phone number or membership line is the worst error the kit could make.

## v0.5.2 (practice facts and pages from them)

- **One master document, not many prompts.** Facts about a person arrived in chat messages and had to be re-pasted into every AI request. They now live once, in the site, and every AI request carries them. The AI may use only those facts and the real site details for anything new, and writes a visible placeholder where they are silent. This is how the kit avoids invented qualifications, fees and claims.
- **Administrators only, never published.** The document may hold fee and registration wording the person has not yet approved for the site, so it is not exposed to editors or visitors, and its text never goes to the browser through the page. Editors can still use it indirectly (the server adds it to their AI requests), which is the point.
- **Recipes are outlines, not prompts alone.** A page type is a list of kit components built from the registry skeletons, so a drafted page cannot use a component or class that does not exist, and every sample word is a placeholder until replaced.
- **A page is created only after the AI has answered with something usable,** so a failed request leaves no empty draft behind, and the draft is saved through the one write path as version 1.
- **The external AI route stays.** Without a key the same button copies a prompt (recipe plus facts for an administrator). Editors get the recipe without the facts.
- **0.5.1 fix:** a missing Site name used to make the header ignore the typed menu and list published pages instead, which looked like the menu not saving. It now uses the WordPress site title.

## v0.5.0 (Ask the AI)

- **Keyless on purpose.** The articles plugin already holds the client's key (its generator is article specific and its key lookup and Anthropic call are private). Site Kit asks for text through a filter, so the key lives in one place and Site Kit stays free of any AI service address. The two plugins agree on two filter names only.
- **A remix is a draft, not a save.** It fills the paste box and goes through the same preview and apply as a pasted result, so there is one write path, one cleaner and one history.
- **Sections by default.** The pop-up lists the page's sections and asks the AI to change only the ones ticked. The others are put back byte for byte (tested), which also makes calls cheaper and quicker. Whole page is available but not the default.
- **Locked wording.** A testimonial is a real client's words and the safety notice holds real helplines, so a remix may not change their wording unless the person says they are supplying it. Enforced after the reply by comparing text, not by trusting the prompt.
- **Not built, on purpose:** rewriting every page at once, free CSS, free HTML for the header and footer. Header, footer and style proposals are planned for 0.5.1, and a Voice and facts note for 0.5.2.

## v0.4.1 (edit pills and the unsaved prompt)

- **Pills only after Edit with AI is opened,** as the owner asked, and only for administrators (the Site script is admin only). They are placed by script over the header and footer, not printed into them, so a page cached for visitors never contains one and the Theme Builder layout cannot be disturbed.
- **Header and footer content lives in the site details,** so each pill opens the Site tab and puts the cursor in the matching box (menu, footer text) instead of building a second form. The layout options sit just below.
- **The prompt is inline,** like every other confirmation (no native dialogs). It also covers Escape and clicking outside the pop-up. Compared with a snapshot, so putting the text back by hand clears the warning.

## v0.4.0 (safe mode, Site tab, site profile, starters, SEO basics)

- **Safe mode first.** One switch (wp-config constant, option, or `wp twd-sk safe-mode`) turns off everything added in 0.4.0 and keeps 0.3.1 working. It is built before the modules so every module is added behind it. The template and Pages tab stay on in safe mode, because a page made with the template would otherwise show blank.
- **Site tab saves only what differs from the pack,** and blocks unreadable button and band text on the server, not only in the browser. Weaker pairs warn.
- **Header and footer through two shortcodes** in Elementor Theme Builder, so the plugin owns the markup and the pack owns the look. Four layouts each, a per-site override, sticky off by default. If the plugin is deactivated Elementor shows the raw shortcode text; nothing in the plugin can prevent that, so recovery is documented. Whether Elementor Pro 4.3.1 accepts the exported JSON was not verifiable here.
- **Starters are placeholders, never invented facts.** Every sample sentence, picture description and link wording becomes a visible `[PLACEHOLDER: ...]`, so the publish block holds the page back until a person replaces it. Setup creates drafts only and never publishes.
- **The mirror is a text copy, not the page HTML.** It exists because search, feeds and SEO plugins read post content and the kit stores HTML in meta. The text only enters content when content is empty or is our own earlier text. Finding: a page that still uses the `[twd_page]` shortcode cannot be mirrored into content without overwriting the shortcode, so such pages keep content and get only the meta copy.
- **SEO fields go to whichever plugin is in charge.** With Yoast we write Yoast's keys (so Yoast prints them), with none we print simple tags ourselves, with any other plugin we refuse and say where to edit. This avoids two sets of tags.
- **Structured data is small and honest.** Practice and person from the profile, front page only, nothing guessed, no registration numbers. It avoids duplicates by deferring to any SEO plugin. Yoast's `wpseo_schema_graph` filter is used from documentation; not verified against a live Yoast here.
- **Image attributes at display time,** so stored HTML stays clean and the hero is never lazy.

## v0.3.1 (slice 3b, release 2)

- **Page template, not Elementor.** A kit page needs no Elementor setup: a plugin template calls `get_header()` and `get_footer()`. Checked by reading Hello 3.5.1: `header.php` and `footer.php` call `elementor_theme_do_location('header' / 'footer')` and fall back to Hello's own parts, so an Elementor Theme Builder header and footer still apply. Priority 99 on `template_include` is there so an Elementor Pro single-page template does not win. The Theme Builder state on David's site was not reported (the message arrived with the placeholder text unfilled); the template was built assuming none, and the one thing to confirm on a real site is that the header and footer appear.
- **Switching can hide content.** A page that holds a form or map in Elementor beside the shortcode loses them on the kit template, so the switch asks first and lists them.
- **Two-level leftover check** (decided by the owner): must fix blocks publishing (override is explicit), check never blocks. The owner's list named some markers; the others (sample headings, labels, paragraphs, image descriptions, `[PLACEHOLDER`) were put at "must fix" because they are plainly sample text and an earlier decision was to block publishing while placeholders remain.
- **The editor button is on every front-end page for editors** (it was only on kit pages in 0.3.0), because the New page tab works anywhere. The Edit tab still needs a kit page.
- **No mirror yet.** The SEO plan's HTML mirror (page HTML copied into the page content for search and Yoast) was proposed but not approved, so it is not in 0.3.1.
- **Prompt and checker fixes found from the 0.3.0 prompt output.** The style guide's example link text is now meaningful ("Contact me about a first session", "Find out about my services", "Read more about this approach", "Call me to arrange a first session") so it no longer contradicts the link text rule, and a test forbids vague example link text. A new rule says never to state policies (confidentiality, safeguarding, cancellation, refunds) or DBS, accreditation or registration status without the therapist's wording, using `[PLACEHOLDER: policy wording needed]`. The style guide also says every word in the examples is a sample.
- **Leftover markers extended** (see CLAUDE.md). Beyond the list asked for, markers were added for the testimonial, pull-out, FAQ, topic, description and introduction examples, because a test showed those examples slipped through the check. "First step" was left out on purpose: it is ordinary prose and would warn on real pages.
- **Example link wording is check level:** a page that keeps "Contact me about a first session" gets a warning but is never blocked from publishing.
- **Prompt size:** about 22.6 KB (was about 22 KB).

## v0.3.0 (slice 3b, release 1)

- **Why the pop-up never calls an AI.** The client site holds no AI key and makes no outbound call. The round trip stays copy, paste, preview, apply, so the therapist reads and approves everything before it is saved.
- **Preview uses the real page, not a stand-in.** A frame showing a kit-only copy would hide theme and Elementor interference, which is exactly what went wrong before. The server keeps the cleaned draft in a short-lived transient and the page's own address renders it for its owner only. Trade-off: the articles grid renders, but its live loading depends on the articles plugin's own script.
- **Apply needs an exact preview.** The Apply button is enabled only for the text that was previewed; changing the text disables it again. The server cleans the HTML again on apply, so the browser is never trusted.
- **Apply allowed with leftover example text** (decision): a banner shows the count and says publishing is blocked until they are replaced (the block arrives with publishing in 0.3.1).
- **"Where did these facts come from?"** is optional but prompted, and saved as the version note. Notes are limited to 200 characters by the store.
- **Nonce checked twice.** Core already treats a request with a bad nonce as signed out; each permission callback also checks the nonce itself so a missing one is refused even where core's check is absent (and in tests).
- **Editor loads nowhere it should not.** Not for visitors, wp-admin, the preview frame (stylesheet only, for the bar), Elementor's editor or the customizer.
- **Not verifiable here:** no real WordPress could be run in the build container, so the route wiring, the nonce flow, the preview address in a draft and the interaction with Elementor and caching are covered by tests against stand-ins and a headless browser against a mock server, and need a real-site check.

## v0.2.6

- Prompt rules tightened after a real run on a test page: ask only when unclear or removing content, images keep their addresses, new images are a visible placeholder (not an invented file name), alt text, heading order, meaningful link text, no outcome promises or health claims, helplines never altered, "ask their web designer" for anything needing the page builder.
- **Image placeholder is text, not an address.** `[PLACEHOLDER: image needed]` cannot be an image `src` (the sanitiser refuses addresses with square brackets and drops an image with no valid address), so the prompt puts it where the picture goes as plain text. The leftover check flags it.
- **Leftover example text is a warning only.** Added `your-image.jpg` to the owner's list, since it is the registry's example picture address.

## v0.2.5

- **Prompt command.** `wp twd-sk prompt <page_id>` prints the client prompt from `TWD_SK_Prompt`. It carries the owner's rules verbatim in intent (ask first, one change at a time, full HTML in one code block, one h1, no scripts or inline styles or forms or iframes, never invent credentials or details, confidentiality, keep the safety notice, the therapist's voice, no em dashes). The prompt is long because it carries every skeleton; trim it if a chat window struggles.
- **Placeholder conflict found and fixed.** The prompt tells the AI to write `[PLACEHOLDER]`, but the sanitiser removed any bare bracket word as a shortcode. The sanitiser now keeps exactly `[PLACEHOLDER]` and `[PLACEHOLDER: words]` as text. Registry skeletons still avoid brackets.
- **Loose text wrapped.** Text sitting directly in the page was left bare by slice 1 (an old test pinned that). CSS cannot style a bare text node, so the sanitiser now wraps each run of loose text in a `p`; a lone link or whitespace is left alone. The stray-content CSS rule then gives direct non-section children a max-width and side padding.
- **Stylesheet source in the repo.** `tools/kit.src.css` and `tools/build-css.py` are now committed; a test fails if the generated file drifts from the source. Before this the source lived only in a session scratch folder.
- **Release tests no longer hold a version number.**
- **Column fix still unconfirmed.** The half-empty image rows seen on a real site could not be reproduced in a browser harness with Elementor-like wrappers. The CSS was made stricter anyway (plain fr columns, full width, children can shrink). Needs screenshots and computed styles from the real site.

## Slice 2c (v0.2.1)

### The self-hosted updater, and how it differs from the articles plugin's
It copies the pattern (a small JSON in the public repo, a 12 hour transient, a "Check for updates" link on the plugin row, the normal update row and details window) with these deliberate differences:
- **Fixed addresses only.** The articles updater takes the package address from the JSON. This one ignores any address in the JSON, rejects a JSON that names another, uses two fixed https constants, and follows no redirects.
- **A sha256 of the zip in the JSON, verified before install.** The updater downloads the zip itself, hashes it, and refuses unless it matches a freshly fetched JSON. Fail closed, with a plain message.
- **No checksum, no offer.** An update file without a valid sha256 never produces an update row.
- **No silent downgrade.** At install time the JSON version must still be newer than the installed one.
- **Folder check.** After unpacking, the package must be the single folder `twd-site-kit` with the plugin file in it.
- Static methods and `is_admin()` gating instead of a singleton created in the main file. The manual check needs the `update_plugins` permission rather than `manage_options`. The check link lives on the Plugins row only (there is no settings page). The cached value is the cleaned manifest, never the raw response, and the changelog is reduced to simple tags before it is shown.

### What the checksum does and does not do
It guards against a corrupted or partial download, a stale or mixed-up copy served from GitHub's cache, and a tampered zip. It is not a signature: the checksum lives in the same repo as the zip, so anyone able to push to the repo could change both. The protection for that is two-factor authentication on the GitHub account.

### Release records without tags
The cloud session's git proxy refuses tag pushes, so there are no tags. The update JSON is the release record. A test makes the JSON checksum, version, changelog entry and download address mismatch impossible to commit unnoticed.

### Known limits
- GitHub's raw address caches for a few minutes, so right after a release the JSON and zip can briefly disagree. The updater then refuses (safe). Wait and check again.
- The updater was tested against a stand-in for WordPress. 0.2.4 was installed by hand on a real site; 0.2.5 is the first real one-click update.

## Slice 2a (v0.2.0)

### One h1, in the hero only
A page has exactly one h1: the first `twd-sk-hero__title` inside a `twd-sk-hero`. The sanitiser keeps that one and demotes every other h1 to h2. The theme's own page title is hidden when the stored page has an h1 (Hello filter `hello_elementor_page_title`, verified against Hello 3.5.1, plus a body-class fallback for other themes). Both depend on there being an h1 in the stored HTML, so a page can never end up with none.

### Components: eleven, from two reference sites
The reference pages (two finished client sites, kept in a private repo and never copied here) were read for their section patterns. Only generic structure was kept. The registry grew variants rather than new components wherever two sections were the same idea with a different look: hero (split, image, compact), cta (band, strip), services (cards, pills), quote (cards, pullout), image_text (reverse, narrow, badge, band), text (aside), contact (split). New components: `steps` (numbered, numbers from CSS), `contact` (label and value rows) and `notice` (safety note and helpline modal). `team` was folded into `image_text`. The full-width picture band became `image_text--band`.

### What the reference sites do that the kit deliberately does not
- Full-bleed sections with a `100vw` and negative margin trick: the kit puts the page in a full-width Elementor container and gives each section its own background.
- Page headings written as `p` and `span`: the kit uses real h1, h2 and h3.
- ID-prefixed per-page CSS and `!important` sprinkled per page: one shared stylesheet, doubled classes, `!important` on visual properties only.
- Inline `onclick` scripts for the helpline modal: pure CSS `:target`.
- A contact form built into the page: the form stays an Elementor widget (the sanitiser removes form elements), skinned by the pack through a container class.

### Style packs
Packs are token files, not CSS. They were built from the final cascaded look of each reference site, not the first rules. For one of them the last heading rule in the cascade made every heading italic and the same colour as the accent, so the pack shows no second colour on the accent span. The span class stays in the registry and each pack decides.

### Bundled fonts
Four open-licence variable fonts (Latin subset, woff2) are bundled so client sites make no requests to Google Fonts. The reference pages did not load their named fonts through any link or `@font-face` that was saved, so it is unverified whether those live sites actually serve them.

### Safety notice: verify before launch
The default support-line list in the `notice` component (Samaritans, Shout, NHS 111, CALM, emergency services) was taken from a reference site. They are public national services, but **verify every name, number, opening time and wording against the official sources before any site launches**, and keep them under review. Text-message numbers are shown as plain text because the `sms:` scheme is not allowed in links. A small plugin-provided script for Esc to close and focus handling is a later slice; until then the modal closes by its close link or by clicking outside it.

### Accessibility (resolved in 0.2.3, articles grid in 0.2.4)

Both packs now meet 4.5:1 for the pairs the components use: button labels on primary and on hover, text on the band, the accent on the band, headings, accent text and eyebrows on the page and card backgrounds, and the dark title on the tint. To get there Sage's green was darkened (primary, headings, band) and its gold eyebrow and hover darkened; Grove's green and band were darkened and its hover changed to a darker gold. On bands and cta sections the primary button is a light button with the dark title colour so it never blends into the band. On the tint tone the body copy uses the dark title colour. The articles plugin's grid is restyled from the pack (0.2.4) so its text meets the same sizes and its accent is the accessible one. A test checks every one of these pairs. Text size floors are tested too: body and lead 16px, small text, eyebrows and buttons 14px; pack validation rejects values below those.

### A privacy slip, found and fixed
One slice 1 commit on the public branch (9531fe2) named a client's private content repo in CLAUDE.md as an example. It was removed in slice 2a, but it remains in the git history. Cleaning history needs a force push and is the owner's call. A privacy guard test now fails if a client repo name, email, phone number, web address, upload path or long number appears, and optionally checks a private list of client words kept outside the repo.

### Other decisions
- `sms:` stays out of the link schemes (http, https, mailto, tel only).
- Tokens are defined on `:root` (inert on their own) so the header and footer, which sit outside the page wrapper, can share the palette; every rule stays under `.twd-sk-page`.
- Overlay, spacing, minimum hero height and gap tokens that the reference sites do not define were chosen to suit the pack and are not verified from a reference.

### Out of slice 2a
The Home, About and Contact starter layouts (slice 2b), the Edit with AI pop-up, installer command, import endpoint, updater, deploy workflow, and header and footer template export.

## Slice 1 (v0.1.0)

### Pages are HTML, not a component tree
A page is sanitised HTML using allowed classes. A typed tree was considered and dropped: the AI round trip is copy and paste, and HTML is what the AI writes naturally and what a person can read and fix. The components registry therefore renders nothing. It defines, per component, the ID, label, HTML skeleton and allowed classes, and feeds the sanitiser allowlist and the AI style guide.

### No custom post type
The page HTML and its history are post meta on the ordinary WordPress page that holds `[twd_page]`. Elementor keeps the header, footer and page setup. The shortcode needs no id because it renders the page it sits on.

### Versions: append-only, cap 10, no draft row
Restore and undo write a NEW version, so nothing is ever lost and the history is honest. Identical HTML is not recorded twice. There are no separate draft and published rows: the human gate is Preview then Apply with Undo (later slice). The store API is small so a draft concept can be added later without touching callers. `base_version` is in from the start because the later deploy workflow must refuse a push when the live page has moved on.

Undo walks the timeline backwards skipping versions equal to the current HTML, and carries on from where the last undo went, so pressing it twice goes further back instead of flipping between two versions.

### Sanitiser independence
The sanitiser has its own `strip_dashes()` and calls nothing from the articles plugin, so either plugin can be installed alone and either can change without breaking the other. One deliberate difference from the articles plugin: dash stripping runs on parsed text and `alt`/`title`, not on the raw HTML string, so URLs are never altered.

### Things found while building
- `removeAttribute()` silently fails on attributes with a colon in the name (`xmlns:xlink`, `xlink:href`). Elements are rebuilt from the surviving attributes instead.
- Without the UTF-8 meta tag in the parser wrapper, libxml mangles accents and emoji.
- libxml leaves HTML5 named entities such as `&colon;` undecoded and escapes the ampersand on output, so they cannot be used to smuggle a scheme. A test documents this.
- libxml percent-encodes non-ASCII bytes in `href` and `src` on output. Same URL, stable when cleaned again.
- `update_post_meta()` strips slashes. The store slashes first or backslashes in content would be lost.

### Decisions taken with the brief
- `h1` demoted to `h2` (the theme shows the page title as the one h1).
- Relative paths and `#anchors` allowed, protocol-relative URLs refused.
- `id` allowed only with a `twd-sk-` prefix, so `#anchor` links work without opening DOM clobbering.
- 200 KB cap per page. PHP 7.4 and WordPress 6.0 minimum (the articles plugin header sets no minimum of its own).
- Meta keys `_twd_sk_html` and `_twd_sk_versions`, hidden from the Custom Fields box.

### Out of slice 1
Pop-ups, AI prompt, import endpoint, self-hosted updater, deploy workflow, component CSS (added in slice 2a). `dist/` is reserved for the updater and the install zip. The repo must stay public for the updater.
