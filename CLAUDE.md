# TWD Site Kit

A WordPress plugin, sibling to the Articles & Resource Production Plugin (`twd-article-publisher`, in `thistwistedyouth/therapy-web-designs`). It lets a whole therapist site (its pages and styles) be built and remixed with AI, using copy and paste, without an API key on the client site. This file is the short version: rules, not stories. **HISTORY.md** has the reasoning behind each decision and the open questions.

Target site: Hello theme + Elementor (header and footer only) + Articles plugin + Site Kit.

## Status

- Slice 1 (v0.1.0): plugin skeleton, components registry, sanitiser, page store with versions, `[twd_page]` shortcode, WP-CLI commands, tests.
- Slice 2a (v0.2.0): the one-h1 rule and title hiding, 11 components with variants, component CSS, design tokens and style packs ("sage" and "grove"), bundled fonts, `starters/_gallery.html`, privacy guard.

Not built yet: slice 2b (the Home, About and Contact starter layouts), the Edit with AI pop-up and prompt, installer command, REST and import endpoint, self-hosted updater, deploy workflow, the safety-notice enhancement script (Esc and focus handling), header and footer template export.

## Names (never rename)

- Folder, main file, text domain: `twd-site-kit`
- Class prefix: `TWD_SK_`. REST namespace (later): `twd-site-kit/v1`. CSS classes: `twd-sk-`. Options and meta: `twd_sk_` (post meta keys start with an underscore: `_twd_sk_html`, `_twd_sk_versions`). CSS variables (later): `--twd-site-*`.
- Minimum: PHP 7.4, WordPress 6.0. No PHP 8-only syntax or functions in plugin code (a test checks).

## Style

Vanilla JS, no build step, no Composer, tabs, snake_case, same style as the articles plugin. No em dashes or en dashes anywhere, including comments (use hyphens). A test checks source files.

## Architecture rules

- **A page is sanitised HTML using allowed classes.** It is not a typed component tree and not a custom post type. The HTML and its history live in post meta on the WordPress page that holds `[twd_page]`.
- **`TWD_SK_Registry`** is data only. One entry per component, keyed on its ID, never on the display label: `hero`, `text`, `image_text`, `quote`, `faq`, `services`, `steps`, `cta`, `contact`, `notice`, `resources`. Each has `id`, `label`, `description`, `skeleton` (the default variant), `variants` (modifier class => label and full skeleton), `classes` (every class it may use, modifiers included) and `shortcodes`. `shared_classes()` holds the classes any component may use (containers, tones, type, buttons, cards). It renders nothing. It feeds the sanitiser allowlists, the gallery generator and (later) the AI style guide. To add a component or variant, add it to the registry and write its CSS; tests check every skeleton survives the sanitiser untouched, every class is styled, and the gallery is regenerated.
  - Placeholder text in skeletons is plain words, never square brackets (the sanitiser removes `[like this]` as a shortcode) and never real details. Emails use `you@example.com`, phones `PHONE_NUMBER`, pictures `/wp-content/uploads/your-image.jpg`.
  - Variants: hero split, image, compact; text aside; image_text reverse, narrow, badge, band; quote cards, pullout; services cards, pills; cta band, strip; contact split. `team` was folded into `image_text` (badge variant). A contact form is never part of the page HTML.
  - **`notice`** is a safety note plus a helpline modal, pure CSS with `:target` (a link to `#twd-sk-helplines`, a close link back to `#twd-sk-notice`). No JavaScript. Text-message numbers are shown as plain text, never `sms:` links. The helpline list must be verified against official sources before any site launches.
- **`TWD_SK_Sanitizer`** is the only path HTML takes into storage. Fully independent of the articles plugin: its own `strip_dashes()`, no calls into any `TWD_AP_*` code (a test checks). No WordPress functions inside it, so it runs in plain PHP. `clean()` returns HTML, `clean_with_report()` also returns what was removed.
  - Removed with contents: script, style, iframe, object, embed, form controls, svg, math, video and similar. Unknown tags are unwrapped.
  - **One h1 per page.** An `h1` stays only if it has the class `twd-sk-hero__title`, sits inside an element with the class `twd-sk-hero`, and is the first such h1 in the page. Every other `h1` becomes `h2` (and is reported). The check happens after class filtering, so a made-up class cannot earn it. The allowance resets on every `clean()` call.
  - Attributes kept: `class`, `id` (only `twd-sk-` prefixed), `href`/`target`/`rel`/`title` on links, `src`/`alt`/`width`/`height`/`title` on images, `open` on details. Everything else goes (every `on*`, `style`, `data-*`).
  - Classes: only those in the registry. Case-sensitive, exact.
  - Links and images: http, https, mailto (links only), tel (links only), relative paths, `#anchors`. Protocol-relative (`//host`, `/\host`) is refused. An image with no valid src is dropped.
  - Shortcodes: all removed except those in the registry (`twd_articles`), which are rebuilt with validated attributes only. `[twd_page]` is never allowed.
  - Dashes: em and en dashes become a comma, or are dropped before closing punctuation. Applied to text and `alt`/`title`, not to URLs.
  - The parser needs the UTF-8 meta tag in the wrapper or it mangles accents and emoji (tests cover curly quotes, pound sign, accents, emoji). The serialiser percent-encodes non-ASCII in `href`/`src`, which is the same URL.
  - Elements are rebuilt from their surviving attributes. `removeAttribute()` cannot remove attributes with a colon in the name, so never switch back to removing in place.
- **`TWD_SK_Store`** is the only writer of the two meta keys. Append-only, cap of 10 versions per page, 200 KB per page. Restore and undo write a new version. Identical HTML is a no-op. Empty result is refused. Optional `base_version` refuses a write if the page has moved on (for the deploy workflow later). Always `wp_slash()` before `update_post_meta()`. No capability checks in the store; the REST layer and WP-CLI decide who may call it. There is no draft row: the human gate is Preview then Apply with Undo (later slice), built on this API.
- **Title hiding.** If a page's stored HTML contains an h1, `TWD_SK_Page` hides the theme's own page title: the Hello filter `hello_elementor_page_title` returns false (server side, no flash), and the body class `twd-sk-has-h1` is added for other themes. Only single pages are affected, and only when an h1 is stored, so a page never has zero h1s and never two. The stylesheet's last rule hides `.page-header` and `.entry-title` under that body class: the one rule allowed outside the `.twd-sk-page` wrapper.
- **`TWD_SK_Packs`** (style packs and tokens). A pack is a JSON file in `packs/` holding design tokens only (colours, two fonts, radii, spacing, button style, image position), never CSS or markup. Every value is validated against a strict pattern (hex or rgb colours, plain non-negative lengths with px, rem, em or %, known weights, bundled fonts only, strict shadows and positions), so a pack or an override cannot inject anything else. A pack must define every token. Tokens print as `:root{--twd-site-*:...}` (inert on their own); all rules live in the stylesheet. Active pack: option `twd_sk_pack` (default `sage`). Per-site overrides: option `twd_sk_tokens`, validated the same way, invalid entries ignored. Switch with `wp twd-sk pack <slug>`.
  - Packs are built from the final cascaded look of the reference sites, not the first rules. "sage" shows no second colour on the heading accent span (accent colour equals heading colour); "grove" shows a sage italic accent.
  - Several colour pairs in the packs are below WCAG AA by design (faithful to the reference looks). A test pins them at their current levels so they cannot get worse, and HISTORY.md records them.
- **Fonts** are bundled in `assets/fonts` (Cormorant Garamond, Lora, Jost, Nunito: variable woff2, Latin subset, SIL Open Font Licence, licence text beside the files) so client sites make no requests to Google Fonts. Only the two fonts the active pack uses are declared with `@font-face` (`font-display: swap`). A pack may instead use "System serif" or "System sans" and ship no font files. Adding a font means adding the file, its licence and an entry in `TWD_SK_Packs::fonts()`.
- **Stylesheet** (`assets/twd-site-kit.css`, loaded on every front-end page because Elementor hides shortcodes from `has_shortcode()`). Rules enforced by `tests/test-css.php`: every selector scoped under `.twd-sk-page` (one exception, the title fallback); every component class doubled in its selector so a theme cannot win on specificity; `!important` on every visual property (colour, background, font, border, padding, margin, radius, shadow, text) but never on layout, so the unconditional `[hidden]` rule always wins; no colour or font literals (tokens only); no `100vw`, no negative margins, no `overflow: hidden` (the wrapper uses `overflow-x: clip`); every registry class is styled and no unregistered class is. Image rows are CSS Grid with `align-items: stretch`, images `width: 100%; height: 100%; object-fit: cover; object-position: var(--twd-site-image-position)`, never flex. A page sits in a full-width Elementor container with no padding. A contact form is an Elementor widget placed in a container that has the CSS class `twd-sk-page`; the "form skin" rules at the end of the stylesheet then skin it with the pack. The stylesheet was written in a compact source form and expanded by a one-off script; edit the committed file directly and let the tests keep it honest.
- **Gallery.** `starters/_gallery.html` shows every component and variant once. It is generated from the registry by `php bin/build-gallery.php` and a test fails if it differs. It must pass the sanitiser with nothing removed. Later hero variants use an h2 title because a page has one h1.
- **Privacy guard** (`tests/test-privacy.php`). This repo is public. Generic checks always run (real-looking emails, phone numbers, web addresses, upload paths, long digit runs; other agency repos and client site repos are never named). A private list of client words kept OUTSIDE the repo is checked when present: set `TWD_SK_PRIVACY_DENYLIST` to a file with one word per line, or put it at `~/.twd-sk-privacy-denylist.txt`. Run the suite with the list before every push.
- **`[twd_page]`** takes no attributes and no id, renders the current page's stored HTML in one `div.twd-sk-page` wrapper. Only registry shortcodes run at render time, every other bracket is turned into an entity. It never relies on `has_shortcode()` (it cannot see inside Elementor's JSON), it uses a render-time flag.
- **WP-CLI** (`wp twd-sk save|get|versions|undo|pack`) is loaded only when `WP_CLI` is defined. The page commands go through the store API only; `pack` goes through `TWD_SK_Packs`. It is not the import endpoint.

## CSS rules

See the Stylesheet bullet above. In short: one wrapper class, doubled component classes, `!important` on visual properties only, an unconditional `[hidden]` rule, tokens only, no viewport-width or negative-margin tricks, `overflow-x: clip` not hidden. Restyle means design tokens only, in the `--twd-site-*` namespace.

## Tests

`php tests/run.php` runs everything. Hand-rolled, zero dependency, one file per area (`tests/test-*.php`), WordPress stubbed in `tests/bootstrap.php`. Exit code is non-zero on any failure. Every sanitiser rule has at least one test. Also run `php -l` on every PHP file before pushing.

## Releases

```
bash bin/build-zip.sh
```

Builds `dist/twd-site-kit-latest.zip` with `cp -r` (not rsync) and one top-level `twd-site-kit/` folder holding only `twd-site-kit.php`, `includes/`, `assets/` (stylesheet, placeholder pictures, fonts), `packs/` and `starters/`. No CLAUDE.md, HISTORY.md, README, tests, bin, dist or .git files go in. It then unzips the result and diffs every file against the source, and fails if the file lists differ or anything that must not ship is inside. Run the tests and `php -l` first, then commit the zip with the code. Download link: `https://raw.githubusercontent.com/thistwistedyouth/twd-site-kit/main/dist/twd-site-kit-latest.zip`. Bump the version in `twd-site-kit.php` (header and `TWD_SK_VERSION`) before building a new release.

## Repo rules

- **This repo must stay public.** The self-hosted updater (later) reads releases and `dist/` anonymously. Never put credentials, keys, or client content in it.
- `dist/` holds the install zip (`twd-site-kit-latest.zip`) and, later, the updater manifest. The zip contains only the plugin files (no tests, no docs).
- Client site content lives in separate private repos (one per client), never here.
- Do not invent credentials, registration bodies, numbers, fees or claims in any site content. Anything not in the client's own words is a question.
