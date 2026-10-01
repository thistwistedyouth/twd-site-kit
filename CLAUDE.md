# TWD Site Kit

A WordPress plugin, sibling to the Articles & Resource Production Plugin (`twd-article-publisher`, in `thistwistedyouth/therapy-web-designs`). It lets a whole therapist site (its pages and styles) be built and remixed with AI, using copy and paste, without an API key on the client site. This file is the short version: rules, not stories. **HISTORY.md** has the reasoning behind each decision and the open questions.

Target site: Hello theme + Elementor (header and footer only) + Articles plugin + Site Kit.

## Status

- Slice 1 (v0.1.0): plugin skeleton, components registry, sanitiser, page store with versions, `[twd_page]` shortcode, WP-CLI commands, tests.
- Slice 2a (v0.2.0): the one-h1 rule and title hiding, 11 components with variants, component CSS, design tokens and style packs ("sage" and "grove"), bundled fonts, `starters/_gallery.html`, privacy guard.

- Slice 2c (v0.2.1): the self-hosted updater with a sha256 check, `readme.txt`, release scripts and release consistency tests.

Not built yet: slice 2b (the Home, About and Contact starter layouts), the Edit with AI pop-up and prompt, installer command, REST and import endpoint, deploy workflow, the safety-notice enhancement script (Esc and focus handling), header and footer template export.

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
- **`TWD_SK_Updater`** (self-hosted updates, admin only). Reads `dist/twd-site-kit-update.json` from this PUBLIC repo on `main` (raw.githubusercontent.com, no credentials), caches the cleaned result for 12 hours in the `twd_sk_update_check` transient, and shows a normal update row, "View details" changelog and one-click update. A "Check for updates" link on the plugin's row on the Plugins screen clears the cache, forces core to recheck, and shows the result. Stricter than the articles plugin's updater:
  - Only two addresses are ever fetched, both fixed https constants in the class (`JSON_URL`, `ZIP_URL`), with no redirects followed. The package address is never read from the JSON: a JSON that names any other address is ignored.
  - The JSON must carry a `sha256` of the zip. An update with no valid checksum is never offered. At install time (`upgrader_pre_download`) the JSON is fetched fresh, the zip is downloaded by the updater, hashed, and refused unless it matches. Any doubt fails closed with a plain message ending "Nothing was changed". A version that is not newer than the installed one is refused, so there is no silent downgrade.
  - After unpacking (`upgrader_source_selection`) the package must be the single folder `twd-site-kit` holding the plugin file.
  - Nothing is hooked unless `is_admin()`.
  - The checksum protects against a corrupted, partial or stale download and a tampered zip. It is not a signature: anyone who can push to this repo can change both the zip and the JSON, so keep two-factor authentication on the GitHub account.
- **Gallery.** `starters/_gallery.html` shows every component and variant once. It is generated from the registry by `php bin/build-gallery.php` and a test fails if it differs. It must pass the sanitiser with nothing removed. Later hero variants use an h2 title because a page has one h1.
- **Privacy guard** (`tests/test-privacy.php`). This repo is public. Generic checks always run (real-looking emails, phone numbers, web addresses, upload paths, long digit runs; other agency repos and client site repos are never named). A private list of client words kept OUTSIDE the repo is checked when present: set `TWD_SK_PRIVACY_DENYLIST` to a file with one word per line, or put it at `~/.twd-sk-privacy-denylist.txt`. Run the suite with the list before every push.
- **`[twd_page]`** takes no attributes and no id, renders the current page's stored HTML in one `div.twd-sk-page` wrapper. Only registry shortcodes run at render time, every other bracket is turned into an entity. It never relies on `has_shortcode()` (it cannot see inside Elementor's JSON), it uses a render-time flag.
- **WP-CLI** (`wp twd-sk save|get|versions|undo|pack`) is loaded only when `WP_CLI` is defined. The page commands go through the store API only; `pack` goes through `TWD_SK_Packs`. It is not the import endpoint.

## CSS rules

See the Stylesheet bullet above. In short: one wrapper class, doubled component classes, `!important` on visual properties only, an unconditional `[hidden]` rule, tokens only, no viewport-width or negative-margin tricks, `overflow-x: clip` not hidden. Restyle means design tokens only, in the `--twd-site-*` namespace.

## Tests

`php tests/run.php` runs everything. Hand-rolled, zero dependency, one file per area (`tests/test-*.php`), WordPress stubbed in `tests/bootstrap.php`. Exit code is non-zero on any failure. Every sanitiser rule has at least one test. Also run `php -l` on every PHP file before pushing.

## Releases & self-hosted updates

The plugin is not on WordPress.org, so it updates itself (see `TWD_SK_Updater` above). Every client site checks `dist/twd-site-kit-update.json` in **this repo** (must stay **public**: sites fetch it with no credentials) and, if its `version` is newer than the installed one, shows the normal "update available" row in wp-admin Plugins with a one-click update. Checked every 12 hours, or straight away with the "Check for updates" link on the plugin row.

To ship a new version to every site running the plugin:

```bash
# 1. Bump the version in THREE places, all the same: the Version line in the
#    twd-site-kit.php header, the TWD_SK_VERSION constant in the same file, and
#    Stable tag in readme.txt (also add a changelog entry there).

# 2. Run the tests and lint. Every test must pass.
php tests/run.php
for f in twd-site-kit.php includes/*.php bin/*.php tests/*.php; do php -l "$f"; done

# 3. Build the distributable zip and verify it. It must contain a single
#    top-level twd-site-kit/ folder (WordPress's plugin upgrader requires that
#    shape; a zip of the folder's contents silently misinstalls). The script uses
#    cp -r, not rsync, builds fresh, unzips the result, diffs every file against
#    the source, checks the file lists match, and runs the release check.
bash bin/build-zip.sh
# (tests/test-release.php holds the current version in a few fixtures; bump those too)

# 4. Update the JSON: version, changelog entry, the fixed download address, the
#    sha256 of the zip just built, and today's date. The changelog must start
#    with <h4>VERSION</h4>.
php bin/update-json.php --changelog="<h4>0.2.2</h4><ul><li>What changed.</li></ul>"

# 5. Strict release check. It must print OK: header Version, TWD_SK_VERSION and
#    readme.txt Stable tag agree with the JSON version, the zip holds the same
#    version in one twd-site-kit folder, and the JSON sha256 matches the zip.
php bin/check-release.php

# 6. Commit and push the code, the zip and the JSON together to main.
```

Rules:
- **The zip and the JSON always travel together.** Rebuilding the zip changes its sha256 (zip timestamps), so after any rebuild run step 4 again (for the same version it only refreshes the sha256 and date) and step 5. A test fails if the JSON checksum does not match `dist/twd-site-kit-latest.zip`, so a forgotten step cannot be committed quietly.
- `download_url` in the JSON must be exactly the fixed address, and `dist/twd-site-kit-latest.zip` is a stable path that never changes.
- **No git tags.** The git proxy used in cloud sessions refuses tag pushes. The JSON (version, date, changelog, checksum) is the release record. `git log -- dist/twd-site-kit-update.json` finds the commit for any version.
- **GitHub's raw address caches for a few minutes.** Right after a push the JSON and the zip can briefly be out of step. The updater then fails closed with a checksum message and installs nothing. Wait about five minutes and use "Check for updates" again.
- **Rolling back.** The updater never installs a version that is not newer, so a bad release is fixed by releasing a HIGHER version that carries the older, good code (for example 0.2.3 built from the 0.2.1 code).
- The repo must stay public, and credentials never go in it.

For a one-off manual install (skipping the updater, for example trying a change on a single site): upload `dist/twd-site-kit-latest.zip` via **Plugins > Add New > Upload Plugin**. WordPress offers to replace the installed version.

## Repo rules

- **This repo must stay public.** The self-hosted updater (later) reads releases and `dist/` anonymously. Never put credentials, keys, or client content in it.
- `dist/` holds the install zip (`twd-site-kit-latest.zip`) and the updater's `twd-site-kit-update.json`. The zip contains only the plugin files (no tests, no docs).
- Client site content lives in separate private repos (one per client), never here.
- Do not invent credentials, registration bodies, numbers, fees or claims in any site content. Anything not in the client's own words is a question.
