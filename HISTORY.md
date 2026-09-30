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
Pop-ups, AI prompt, import endpoint, self-hosted updater, deploy workflow, component CSS. `dist/` is reserved for the updater and the install zip. The repo must stay public for the updater.
