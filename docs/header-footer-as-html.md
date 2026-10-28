# Header and footer as HTML parts (an idea to decide)

Status: raised by the owner on 28 Oct 2026 after a day of header changes on the pilot staging site. Not decided. Nothing is built. This file is the thinking so another session can pick it up.

## What prompted it

- One small request (a larger symbol, and a line under the name in the smaller header) took a new setting, new classes, a new words block, three changed height calculations (the bar spacer, the front page stack, the phone spacer), a change to the shrink script's guess of the compact row, the Site tab script, the brief, snapshots, the command line, the site report, tests and docs. In plain HTML on a page it would have been one line of words.
- A "header scrolls away" report on the front page turned out to be the fixed header setting being off. Many interacting switches make a missing switch look like a bug.
- Header and footer rules are the largest block in the stylesheet every visitor downloads. The stylesheet is 15 bytes under its 140,000 byte ceiling after that one change.
- A standing rule in CLAUDE.md is "data and tokens, not one-off CSS". Any answer has to keep that.

## The idea

The header and the footer become stored HTML parts, handled like pages: sanitised, versioned with Undo, changed by Preview then Apply, remixed by the AI like a section. Administrators only (as the Embed is), so clients need never see it. The fixed switches stay only for behaviours (fixed to the top, smaller when scrolled, transparent over a picture) and for look tokens (colours, fonts, sizes). The arrangement, the words and the order become HTML.

What this buys: a change of arrangement is an edit, not a feature. The words (name, descriptor) are ordinary text in the Visual editor, with no field behind them. A bespoke look for one site does not ship code to every site.

## What the sanitiser and store do today (real constraints)

- Allowed tags include `header`, `footer`, `div`, `span`, `a`, `img`, lists. NOT allowed: `nav` (unwrapped), `button`, `svg`, `form` controls, `style`, `data-*`. So the menu, its mobile toggle button and the skip link cannot be written as part HTML. They must be PHP printed after the sanitiser, as the enquiry form and the Embed are.
- `TWD_SK_Store` only accepts a WordPress `page` (`check_page`). A part needs a hidden post type or hidden pages, and the store, the REST guard and the Visual editor's page checks need to accept it.
- Pictures only through `img` (no inline SVG symbol).

## What stays live (short shortcodes inside the part)

- `[twd_menu]`: the menu from Site details, the current page mark, sub-menus, the mobile toggle, with a few validated options (marker, alignment). The only substantial live piece.
- Perhaps the year for the copyright. Keep the list very short.

## Where bespoke CSS lives (the real decision)

- A. A small shared vocabulary of layout pieces in the stylesheet (row, stack, spread, centred, gap and align steps, a brand lockup block, a strip, a button), sizes from tokens. A bespoke header is a different arrangement of the same pieces. Consistent with "data and tokens". Adds a little shared CSS once, then none per design. Recommended first.
- B. A per-site CSS field, administrators only, parsed strictly (selectors only under the chrome wrapper, a property allowlist, values like the token validators, no `url()`, no `@import`). Truly bespoke, but a CSS validator is a build of its own and a security surface. Only if A proves too tight.
- C. No custom CSS: the part may only use what exists. Cheapest, least flexible.

## Do this either way: measure, do not calculate

The fixed header's spacer is worked out from tokens (symbol size, menu size, paddings, with a separate formula for the front stack and a literal 77px on phones) and the shrink script guesses the compact row (56px). Every header change has had to touch these. A tiny script that measures the header and sets one CSS variable removes that class of bug for the settings header and for any HTML part. Small, safe, independent.

## Phases

0. Now, small: finish the pilot site's ask with the current settings (done in 0.15.0-test4, to be checked on the site). Then replace the token-formula spacers with a measured one.
1. Parts, medium (two to three focused sessions): hidden storage and the store accepting it; render the part in place of the settings header or footer when one exists and is valid, otherwise the settings one (byte-identical when no part exists; fail closed; safe mode prints the minimal header); `[twd_menu]`; the layout pieces (option A); starter parts that re-express the current wordmark header and the large and compact footers so today's looks can be reproduced and remixed; a Site tab section to paste, preview, apply and undo; the AI remix through the existing flow with a registry-generated style guide; a leftover check (must-fix text in a part keeps the settings one showing).
2. Later: the Visual editor on a part; a "convert my settings header to HTML" button; option B if needed.

Nothing is removed. Existing settings keep working. A site with no part is unchanged.

## Risks and open questions

- Accessibility: landmarks, focus order, the mobile menu. The menu shortcode owns these, with tests.
- AI-written parts still need a human to press Apply (the standing rule).
- Updates cannot improve a bespoke part. Starters can be re-issued, and a "bring pages up to date" style list can carry fixes.
- Elementor Pro's Theme Builder remains a third way (the original plan for header and footer).
- Is this for most clients or a few bespoke ones? If most are happy with the five packs and the settings, keep the settings as the default and offer a part for the few.
- The owner's wish, recorded: clients do not edit this part but can through the AI prompt; the AI should be able to make and adapt it on the fly for a site.
