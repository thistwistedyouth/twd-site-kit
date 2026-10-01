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

### Accessibility note (known, pinned by a test)
Being faithful to the two reference looks means some colour pairs are below WCAG AA (4.5 for normal text, 3 for large text):
- "sage": heading on cream about 2.8, heading on white 3.0, eyebrow (gold) on cream 2.15, body text on the tinted background 2.8, white label on the primary button 3.0, and white on the hover colour 2.3.
- "grove": white label on the primary button 3.3, eyebrow on cream 3.1, primary-colour links on cream 3.1, and white on the hover colour 2.3.
Body text and card titles pass 4.5 in both packs. The stylesheet already switches headings, eyebrows and the primary button to the dark title colour on the tinted background, because mid-green on light green is nearly unreadable. Decision for the owner: either keep the packs faithful, or add darker "accessible" values for the failing tokens (a one-line change per token in each pack file). A test pins the current ratios so they cannot get worse unnoticed.

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
