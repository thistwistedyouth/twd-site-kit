# TWD Site Kit

A WordPress plugin, sibling to the Articles & Resource Production Plugin (`twd-article-publisher`, in `thistwistedyouth/therapy-web-designs`). It lets a whole therapist site (its pages and styles) be built and remixed with AI, using copy and paste, without an API key on the client site. This file is the short version: rules, not stories. **HISTORY.md** has the reasoning behind each decision and the open questions.

Target site: Hello theme + Elementor (header and footer only) + Articles plugin + Site Kit.

## Status

Slice 1 (v0.1.0): plugin skeleton, components registry, sanitiser, page store with versions, `[twd_page]` shortcode, WP-CLI commands, tests.

Not built yet (later slices): pop-ups, the AI prompt and style guide output, REST and import endpoint, self-hosted updater, deploy workflow, component CSS and design tokens.

## Names (never rename)

- Folder, main file, text domain: `twd-site-kit`
- Class prefix: `TWD_SK_`. REST namespace (later): `twd-site-kit/v1`. CSS classes: `twd-sk-`. Options and meta: `twd_sk_` (post meta keys start with an underscore: `_twd_sk_html`, `_twd_sk_versions`). CSS variables (later): `--twd-site-*`.
- Minimum: PHP 7.4, WordPress 6.0. No PHP 8-only syntax or functions in plugin code (a test checks).

## Style

Vanilla JS, no build step, no Composer, tabs, snake_case, same style as the articles plugin. No em dashes or en dashes anywhere, including comments (use hyphens). A test checks source files.

## Architecture rules

- **A page is sanitised HTML using allowed classes.** It is not a typed component tree and not a custom post type. The HTML and its history live in post meta on the WordPress page that holds `[twd_page]`.
- **`TWD_SK_Registry`** is data only. One entry per component, keyed on its ID (`hero`, `text`, `image_text`, `quote`, `faq`, `services`, `team`, `cta`, `resources`), never on the display label. Each has `id`, `label`, `skeleton` (HTML), `classes`, `shortcodes`. It renders nothing. It feeds the sanitiser allowlists and (later) the AI style guide. To add a component, add an entry; a test checks its skeleton survives the sanitiser untouched.
- **`TWD_SK_Sanitizer`** is the only path HTML takes into storage. Fully independent of the articles plugin: its own `strip_dashes()`, no calls into any `TWD_AP_*` code (a test checks). No WordPress functions inside it, so it runs in plain PHP. `clean()` returns HTML, `clean_with_report()` also returns what was removed.
  - Removed with contents: script, style, iframe, object, embed, form controls, svg, math, video and similar. Unknown tags are unwrapped. `h1` becomes `h2`.
  - Attributes kept: `class`, `id` (only `twd-sk-` prefixed), `href`/`target`/`rel`/`title` on links, `src`/`alt`/`width`/`height`/`title` on images, `open` on details. Everything else goes (every `on*`, `style`, `data-*`).
  - Classes: only those in the registry. Case-sensitive, exact.
  - Links and images: http, https, mailto (links only), tel (links only), relative paths, `#anchors`. Protocol-relative (`//host`, `/\host`) is refused. An image with no valid src is dropped.
  - Shortcodes: all removed except those in the registry (`twd_articles`), which are rebuilt with validated attributes only. `[twd_page]` is never allowed.
  - Dashes: em and en dashes become a comma, or are dropped before closing punctuation. Applied to text and `alt`/`title`, not to URLs.
  - The parser needs the UTF-8 meta tag in the wrapper or it mangles accents and emoji (tests cover curly quotes, pound sign, accents, emoji). The serialiser percent-encodes non-ASCII in `href`/`src`, which is the same URL.
  - Elements are rebuilt from their surviving attributes. `removeAttribute()` cannot remove attributes with a colon in the name, so never switch back to removing in place.
- **`TWD_SK_Store`** is the only writer of the two meta keys. Append-only, cap of 10 versions per page, 200 KB per page. Restore and undo write a new version. Identical HTML is a no-op. Empty result is refused. Optional `base_version` refuses a write if the page has moved on (for the deploy workflow later). Always `wp_slash()` before `update_post_meta()`. No capability checks in the store; the REST layer and WP-CLI decide who may call it. There is no draft row: the human gate is Preview then Apply with Undo (later slice), built on this API.
- **`[twd_page]`** takes no attributes and no id, renders the current page's stored HTML in one `div.twd-sk-page` wrapper. Only registry shortcodes run at render time, every other bracket is turned into an entity. It never relies on `has_shortcode()` (it cannot see inside Elementor's JSON), it uses a render-time flag.
- **WP-CLI** (`wp twd-sk save|get|versions|undo`) is loaded only when `WP_CLI` is defined and goes through the store API only. It is not the import endpoint.

## CSS rules (for when component CSS is added)

All CSS under one wrapper class (`.twd-sk-page`). `!important` on visual properties. Double the class (`.twd-sk-x.twd-sk-x`) where Elementor can win on specificity. Anything toggled with `hidden` gets an unconditional `[hidden] { display: none !important; }`. Restyle means design tokens only (colours, two fonts, radius, spacing) in the `--twd-site-*` namespace, scoped to `[twd_page]` content.

## Tests

`php tests/run.php` runs everything. Hand-rolled, zero dependency, one file per area (`tests/test-*.php`), WordPress stubbed in `tests/bootstrap.php`. Exit code is non-zero on any failure. Every sanitiser rule has at least one test. Also run `php -l` on every PHP file before pushing.

## Releases

```
bash bin/build-zip.sh
```

Builds `dist/twd-site-kit-latest.zip` with `cp -r` (not rsync) and one top-level `twd-site-kit/` folder holding only `twd-site-kit.php` and `includes/`. No CLAUDE.md, HISTORY.md, README, tests, bin, dist or .git files go in. It then unzips the result and diffs every file against the source, and fails if the file lists differ or anything that must not ship is inside. Run the tests and `php -l` first, then commit the zip with the code. Download link: `https://raw.githubusercontent.com/thistwistedyouth/twd-site-kit/main/dist/twd-site-kit-latest.zip`. Bump the version in `twd-site-kit.php` (header and `TWD_SK_VERSION`) before building a new release.

## Repo rules

- **This repo must stay public.** The self-hosted updater (later) reads releases and `dist/` anonymously. Never put credentials, keys, or client content in it.
- `dist/` holds the install zip (`twd-site-kit-latest.zip`) and, later, the updater manifest. The zip contains only the plugin files (no tests, no docs).
- Client site content lives in separate private repos (for example `thistwistedyouth/davidpowelltherapy-site`), never here.
- Do not invent credentials, registration bodies, numbers, fees or claims in any site content. Anything not in the client's own words is a question.
