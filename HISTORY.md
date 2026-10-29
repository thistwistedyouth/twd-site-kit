# History and decisions

Why things are the way they are. Newest first. Rules live in CLAUDE.md.

## 29 Oct 2026: pencils on the page, not in the pop-up (chunk 2g)

The plan asked for pencils "when Edit with AI is open", but the pop-up is a full-screen modal, so nothing under it can be clicked. The pencils therefore live on the page for a signed-in editor and open the pop-up at a one-section step; while the pop-up is open they hide. A movable or docked pop-up (the plan's chunk 2, not in Tier A) would let them stay. The pencil icon is drawn with CSS because the editor scripts may hold no web address, and an SVG namespace is one.

## 29 Oct 2026: one Your Edits menu instead of a stack of floating buttons (chunk 2d)

Three floating buttons plus a switch covered the bottom left of every page for an editor, over the words on a phone. The admin bar is where WordPress puts a signed-in person's tools, so the launchers moved there as one drop-down, with one floating button only where the admin bar hides its items. The menu is a small registry (each editor script adds its own item with an order number), so a new tool is one call, not a new button and a new CSS position. The old buttons remain in the markup, hidden, because the scripts use them as their open and focus-return targets; removing them would have meant touching every script for no visible gain.

## 29 Oct 2026: section spacing as Look choices, not per-site CSS (chunk 2e)

Space above and below are shared registry classes drawn from the section spacing tokens, placed last in the stylesheet so they win the paddings at equal specificity. Four steps (none, small, normal, large) rather than a free number: a person chooses in plain words, every pack keeps its rhythm, and a page without the classes prints exactly as before. The shared look options are sent to the browser once rather than per component, because two more options on eighteen components took the config past its 8 KB budget.

## 29 Oct 2026: the fixed header's spacer is measured, not calculated (chunk 1)

The spacer under a fixed header was a formula over the size tokens, with the contact strip hard-wired at 36px. Each new header option (the wordmark, the second line, the symbol sizes) added a term, and the formula was already 5px out on the pilot site. The header script now measures the header and sets the spacer; the formula stays only as the value a browser without script gets. The script is one that every page with the kit header already loads, so no visitor gains a request. A token for the strip thickness could then be added without touching the formula's accuracy.

The stylesheet ceiling went to 160,000 rather than the full 175,000 the owner allowed: one real duplicate was found, and a lower ceiling keeps the "say when it needs raising" check meaningful.

## 29 Oct 2026: every way out of the Visual editor reverts (chunks 2c and 2f)

The owner saw edits stay on the page after throwing them away. The cause was a name clash in the script (two `putBack` functions; JavaScript keeps the last declaration), introduced when Remove and Put back were added in 0.14 on top of the 0.10 revert code, and never caught because the browser checks drove looks and pictures, not words. Lesson recorded in the test: one function of that name, and the revert routine is named for what it does.

Undo in the Visual editor goes through the page history and reloads, like Add and Move, rather than redrawing in place: the Visual editor holds its originals as node copies tied to the page as drawn, so after a version change the safe thing is to draw again and reopen at the same section. The pop-up, which holds no such state, redraws in place.

## 29 Oct 2026: Undo that can be trusted (chunk 2i of the owner's plan)

What went wrong on the pilot site: Undo in the pop-up walked back one version per press, never redrew the page and never stopped at the blank starter outline, so a person trying to undo a move pressed it until two pages showed placeholder words. Page history and site settings were also separate: a footer change had no undo at all.

Decisions:
- Keep the walk-back rule (one more step per press) but add a guard: a version with must-fix placeholders is refused when the page now has none, with a plain question, and a second call with `allow_placeholders` goes ahead. A placeholder count is stored on each version at commit time so the guard costs nothing on read; older versions are counted when read.
- Redraw rather than reload: the editor fetches the page again and swaps the kit wrappers (page, header, footer) in place, flashing the sections whose markup changed. Scripts that ran on the old nodes (the quote slider arrows) are not run again on the swapped nodes until a reload; accepted for now.
- 20 versions, copies first: an undo or restore is a copy of another version, so when the cap is reached a copy whose words are still held elsewhere is dropped before any version holding real words. The newest version is never dropped.
- Site changes are a second history, kept apart from page history (an option, not post meta), recorded before each Site tab save with the whole settings state. Undo steps back one change and records the state it left as kind `undo`, so "forward again" is a Restore and the next Undo skips those entries (with n undo entries on top the target is entry 2n). The site history lives in `TWD_SK_Style` beside the style snapshots rather than a new class, to keep the class list and the loaders unchanged.
- Redo in the pop-up is a Restore of the version undo stepped back from: no new route.

## Open questions

### Card-type vocabulary overlap (parked, not solved)

Two different card vocabularies exist in the wider ecosystem and they overlap:

- The articles plugin's **Summary Book** (`TWD_AP_Summary_Book`): `text`, `quote`, `question`.
- **trd-resource-reader** (standalone plugin from the Therapy Resource Directory): `insight`, `question`, `quote`, `part`, `filing`, `image`, `link`, `video`, `qr`.

`quote` and `question` appear in both with possibly different meaning and markup. Nothing here depends on it yet. Decide before any Site Kit component or AI output starts to emit card types, so that one vocabulary (or an explicit mapping) is used everywhere. Not solved in slice 1 on purpose.

### Article Assist and `summary_book`

Checked 2026-09-30 against `15 TRD Article Assist.php` (therapy-resource-directory, last commit 73dcd45). It does NOT output `summary_book`. Its prompt asks for strict JSON with `title`, `seo_title`, `meta_description`, `category`, `tags`, `html` only, and the server handler passes through only those. The articles plugin's CLAUDE.md (v1.32.0) says the same ("Article Assist still doesn't produce the `summary_book` field yet"). The plugin side (`summary_book.slides` in pasted JSON) is ready and waiting.

## 0.15.0 (the Embed section and the editable no-articles line), branch `claude/twd-site-kit-0-15-0-mjih8y`, not released

Why: a test of 0.14.1 on a hidden staging site. The Resources section showed the Articles plugin's own "No articles yet. Please check back soon." line. That line is printed by the other plugin, so it is not in the page's stored HTML and the Visual editor cannot reach it. The owner removed the whole section with Remove (which also took the heading and the grid) and put the articles shortcode back in some other way. Two gaps showed: no proper place for a shortcode or outside code on a kit page, and a line that could not be edited. Nothing is released; the version number is the owner's to agree.

**The owner lifted the verify-first rule for a week (28 Oct 2026 to 4 Nov 2026):** new features may be started while earlier ones are still unverified, because every site is a staging site in this period. Each build still lists what needs checking in BUILD-LOG and a test script.

- **The no-articles line is the page's own words.** Both Resources layouts now end with `<p class="twd-sk-resources__none">No articles yet. Please check back soon.</p>` after the grid. It is hidden by the stylesheet until the Articles plugin's grid says it is empty (that plugin sets or clears the `hidden` attribute on its `twd-ap-articles-none` line, which also covers its script's states; a search with no matches uses another element and does not show it). Then it takes the plugin's line's place (the plugin's line is hidden only inside a Resources section that holds ours). Chosen over asking the Articles plugin for a filter, because that plugin is read only from here and the stylesheet already targets its documented classes. Each `:has()` rule is written on its own line, so a browser without `:has()` drops those rules, keeps our line hidden and shows the plugin's own line as before (checked in Chromium with an empty grid, a full grid and an old section without the line). Pages built before 0.15.0 have no such line and look exactly as they did; a new Resources section, or one the AI is asked to add, has it. The default wording is the plugin's own plain words, so the starter outline keeps it as written instead of turning it into a `[PLACEHOLDER]` that blocks publishing for a sentence nobody invented. The Visual editor shows it while editing (a small blue label says it is shown only when there are no articles), because otherwise it would be hidden and unreachable.
- **The Embed section keeps outside code apart from the page.** The page's stored HTML holds only `[twd_embed id="N"]`. The code (a shortcode, or raw HTML) is one record in the option `twd_sk_embeds` (not autoloaded), so the sanitiser's bans on scripts, frames and forms stand for everything a person or an AI writes, the code is not in the page's history, the plain-text search copy, the prompt, the site report or the list the pop-up gets, and the Visual editor cannot reach it. An option rather than a private post type: no `wp_insert_post` filtering of `post_content`, no revisions, ids that are never reused (a counter, so a deleted embed's number cannot suddenly show something else). Up to 40 embeds of up to 50 KB.
- **Who can do what.** Only an administrator creates, changes or deletes one (`manage_options`); raw HTML also needs `unfiltered_html`, which WordPress withholds on multisite and under `DISALLOW_UNFILTERED_HTML`, so an account that cannot paste a script into a post cannot paste one here either. A "shortcode" embed may hold no tags at all, so it cannot be used to slip HTML past the HTML check. Neither kind may contain `[twd_page]` or `[twd_embed]` (no loops), and a depth guard stops a hand-edited store from looping. The class itself checks nothing about the user (like the page store); the REST routes and the Site tab do. An editor can leave an existing embed section where it is; the AI is told never to add or change one, the section is a locked type (its words survive a remix), and a brief, a starter set, the setup page, the one-box planner and Add a section with AI all refuse it. An administrator adds one by hand: Site tab, Embeds, Add to this page (it builds a candidate page with the section at the end, naming the saved embed, through the normal preview and Apply).
- **Safe mode prints nothing.** Outside code is the first thing to switch off. The shortcode stays registered so a page never shows the raw `[twd_embed ...]` text.
- **A deleted or unset embed is a must-fix.** `[twd_embed id="0"]` (the sample) and a number that is not saved count as must-fix leftovers (through the `twd_sk_leftover_levels` filter, like the search wording), so the page cannot be published with a blank hole. Visitors see nothing in its place; someone who can edit pages sees a short note. The site report lists how many embeds exist and where, never a name or any code, and warns about pages that point at a missing one.
- **Not built, on purpose (0.15.0):** editing an embed from the page itself in the Visual editor; showing a live preview of the embedded code inside the Site tab; a picker inside the Edit with AI box (Add to this page is in the Site tab, which the admin has open anyway); restricting an embed to one page.
- **Bring pages up to date.** Stored pages are plain HTML, so a later version cannot reach pages built earlier by itself. `TWD_SK_Upgrade` is a short list of upgrades that only ADD what is missing (never a word the person wrote), are safe to repeat, and write each page through the store as its own version so Undo works. Chosen over rewriting pages whenever the plugin updates (that would change pages nobody asked to change) and over a migration run at activation (no way to look first). The scan is read only and shows in the launch check as one warning, so it is seen before launch without being pushed. The first upgrade is the no-articles line; a future one is one more entry.
- **Section looks: layouts only between layouts that use the same pieces.** The Visual editor's Look panel (`TWD_SK_Looks`) moves a section between layouts only when the two layouts' skeletons use exactly the same set of `twd-sk-NAME__` element classes. 0.14's command-line `swap` only checked that the new layout had a place for everything the section held, which allows a move to a layout that also needs a wrapper the section lacks (a slider's track, the groups' tab list) and leaves a broken section. Equal sets are stricter and computed from the registry, so it cannot drift; the cost is that pills, links, the slider, the groups and the hero layouts are not offered (they need real conversion, which is not built). The hero picture options (overlay, words on the left, parallax) are registry options that require the full-width picture hero, so parallax is no longer a "layout" (as a layout, switching to it would have dropped the picture class). The background choice is the three light tones only: the dark band changes the colour of text in a way not every section's rules cover. The safety notice is excluded: it is a locked section.
- **The same rules run in the browser and on the server, with a test between them.** Live feedback needs the class rules in the script (so a click shows at once) and safety needs them on the server (which never trusts the browser). Rather than a round trip per click, the script has a small copy (`lookClasses()`), and a test extracts it, runs it in node over a set of cases and compares the result with `TWD_SK_Looks::classes_for()`. If the two ever differ the test fails. The browser only offers what the server says fits (`data-twd-sk-lay`), and the server checks again.
- **Section looks are applied first, on the section numbers the page was drawn with.** A look only changes class names, so it cannot move any text number; applying it before the words, pictures and removals means removals (which do shift section numbers) cannot make a look land on the wrong section.
- **Picture look is a short list of ready-made choices, not a slider.** The `image-filter` token accepts up to three filter functions and is strictly validated; a free control would invite unreadable or heavy results and needs a new control kind. Six filters and five tints cover the use (soften, warm, black and white, a light tint) and every one is a value the token checker accepts (a test checks each). A value a style already has that is not on the list is kept as "The style's own setting" so opening the control loses nothing. The tint lays over pictures beside text only (the full-width hero has its own overlay), and the label says so.
- **Question groups: the tab link is the text, not its list entry.** In 0.14.1 the tab's `li` held only words and an inline link, so the Visual editor treated the whole `li` as one piece of text and typing over all of it deleted the link. The editor now treats the link as the piece (its address is never changed) and the entry as not text. A tab and its group heading carry the same words, so they are kept in step as you type (plain text only). Every group shows while editing because on a wide screen only one group shows at a time and the others could not be reached. Removing a group removes its tab and the reverse, and keeps one group as the default, because a tab with no group, or no default group, is a broken page.
- **Raw HTML in a page that comes through `the_content`.** The kit template prints the page directly. A page that still uses the old `[twd_page]` shortcode inside WordPress content is run through WordPress's own content filters (`wpautop`), which can add line breaks inside a script. Use the kit template for pages with an HTML embed.

## 0.14.1 (the release that carries 0.14.0, the large footer and the text wordmark header)

The owner approved 0.14.1 on 27 Oct 2026. 0.14.0 was never released, so its notes are part of the 0.14.1 changelog and the update file's changelog starts from the 0.13.0 entry. It was published the same day: `main` was fast-forwarded to the release branch (`0f9cd1f`) and pushed (the zip and the update file travel together, in the one commit), and the update file and zip were downloaded back from the public address and matched. Next, in one site's Plugins screen use Check for updates and update that one site first. The way back, as always: safe mode (`wp twd-sk safe-mode on`), then a HIGHER version that carries the older good code (for example 0.14.2 built from the 0.13.0 code), or a manual reinstall of the 0.13.0 zip from commit `214ffcc` (`https://raw.githubusercontent.com/thistwistedyouth/twd-site-kit/214ffcc/dist/twd-site-kit-latest.zip`). Things that make this release safer than its size suggests: nothing in it is on by default, an update is a click an administrator makes (not automatic, unless a site has switched on automatic updates for the plugin), and the updater checks the zip's sha256 and refuses an older or unverified package. What it still carries is the "not on a real site" list in BUILD-LOG; the footer and the wordmark header were tried on one hidden staging site first.

## The text wordmark header, branch `footer-feature` (with the large footer)

Why: a staging review of a real site. In the `bar` header on the front page the stacked logo was centred but the menu started under the logo's left edge and ran right, about 245px off centre; the menu text inherited the 19px body size and looked heavy; the current page was marked by an underline that looked dated; and the logo was a picture with its words baked in, so the name was about 7px high and unreadable (worse on a phone). Nothing is released; the version number is the owner's to agree.

**The design changed once.** First built as a centred stack on every page. After the owner reviewed a live header the brief became: the centred stack on the front page only, the existing one-row bar on every other page (the symbol and the name set in type), a compact bar when scrolled, the menu smaller with a look of its own, and a setting for the name when scrolled. The first version is still in the history (commit `7b253fd`); the later commit replaces it.

- **A setting, off by default, that replaces the layout's classes.** With `wordmark` on, the header prints `twd-sk-header--wordmark` instead of its layout class, plus `--wm-front` on the front page (the stack) or `--bar` on every other page, so none of the four layouts' rules apply and none of them changed. With it off, 768 combinations of layout, fixed, shrink, strip, front page logo, front page and button, over three profiles, print the same bytes as before (compared on a test server, before and after each of the commits). Safe mode and an empty menu still print the plain header.
- **Live text, not a picture.** The mark is the small symbol (`logo_mark_id`) with an empty description, because the name is right beside it; the name is live text in the heading font, capitals and spacing done by the stylesheet (so a screen reader reads normal words); the optional second line is a profile field `logo_line` (40 characters, empty by default, never filled by the starter) printed on the front page only. The link says the site name once (plus the second line on the front page). With no mark, only the words show; the full logo picture is never used in this header.
- **The name when scrolled is hidden from the eye, not from a screen reader.** Scrolled on the front page the default is the small symbol directly left of the menu and no name, as the owner asked. The name stays in the page, clipped to one pixel, so the link still has a name. The setting `wordmark_scrolled_name` (off by default) brings it back: symbol and name at the left, menu at the right.
- **The menu has its own look, in tokens.** `menu-font` (a pack that does not say takes its own body font), `menu-size`, `menu-weight`, `menu-spacing`, `menu-transform` (none or uppercase) and `menu-gap`, in every pack and as defaults. They apply only inside this header, and the sub-menu links (a drop-down on a computer, listed under their parent on a phone) take the same look. Two candidates were compared: (a) capitals in the body font, 13px, 0.12em spacing, regular; (b) the heading font at about 17px, no capitals. **Recommended: (a).** It reads clearly in all five packs, matches the capitals and spacing of the name, and keeps the menu clearly smaller than the name; (b) is thin and soft in a light serif such as Cormorant Garamond, and is a set of token values (`menu-font` the heading font, `menu-size` 17px, `menu-spacing` 0.01em, `menu-transform` none), so a restyle can choose it per site. Menu text is 13px, below the plugin's 14px floor for small text; the floors only cover the tokens they name, the owner asked for 12.5 to 13px, and capitals with spacing help. The second line is 12px for the same reason.
- **Sizes are tokens, in every pack.** Front stack: symbol 72px, name 26px, second line 12px; on a phone 44, 20 and 12. Bar and the scrolled symbol: symbol 36px, name 18px; on a phone 32 and 18. Spacings 0.18em (name) and 0.32em (second line). Phone names are never under 18px.
- **The marker for the current page is a setting with a quiet default.** `menu_marker`: `dot` (default), `color` (accent colour and a little bolder), `bar`, `none`. The dot and the bar take the link's own colour, so they follow the transparent and the scrolled states; the accent colour is used only on `color`, hover and the button. A dot is a shape, so the page is not marked by colour alone, and it is the quietest. `aria-current` stays whatever the marker.
- **The fixed header's spacer is worked out from the tokens.** The stack is tall (about 229 to 234px on the front page, 127px on a phone), the bar about 66px (worked out, not measured), so the 76px spacer cannot be used. CSS custom properties on the wrapper (`--twd-sk-wm-*`, set by the classes `--wordmark`, `--wm-front`, `--noline` and `--strip`) feed one `calc()` for the stack, and the bar's is the taller of the symbol and a menu link plus its paddings. The header button is held to the menu row's height (and its padding trimmed) so the row is the same with or without it. In a browser the spacer matched the header exactly in all five packs and both menu looks. Known limit: a name or second line long enough to wrap (for example a 19 letter name on a 320px phone) makes the header one line taller than the spacer, so it covers a line at the top of the page; a menu that wraps to two rows does the same. With seven menu items, a header button and a short name the bar fits on one row at 1200px in all five packs; with eight items and a long name it wraps its menu onto a second row rather than overflowing.
- **Compact when scrolled, without a blank band.** The 0.13.0 script shrinks the header after 60px and its spacer keeps the tall height. For the stack that left about 180px of empty space under a 55px row until the page scrolled further, so the script now waits until the spacer has scrolled up to the compact row (the spacer's height minus 56px, never under 60px); every other header still shrinks at 60px. Back at the top (under 20px) it grows again.
- **The reduced-motion rule did not work.** It sat before the transition it was meant to cancel and lost the tie, so the scroll size change still animated. The same rule now also follows the transitions. This affects every fixed shrinking header, only for people who ask for less motion.
- **Transparent over a picture** keeps working: the name and second line take the on-image colour and turn back when scrolled. A dark mark disappears on a dark picture, so a site using both needs a light mark.
- **Where it can be set.** Site tab (two tick boxes, a choice for the marker, the second line in Site details), a style kit's `chrome` (`wordmark`, `wordmark_scrolled_name`, `menu_marker`; the menu look by tokens; the second line is content, so it is not in a kit), the site brief (`header.wordmark`, `header.wordmark_scrolled_name`, `header.menu_marker`, `header.logo_line`), and `wp twd-sk style apply --wordmark --scrolled-name --menu-marker=dot` (works with no pack name, like `--footer`). The snapshot a restyle keeps now also holds `overlay`, `wordmark`, `wordmark_scrolled_name` and `menu_marker` (the first was missing, so a revert did not restore it).
- **Stylesheet ceiling raised to 130 KB** (about 127 KB now).
- **Proposed version for the footer and the header together: 0.14.1** if 0.14.0 is already installed on a site (the updater never installs a version that is not newer); otherwise fold both into 0.14.0 before it is merged. Not applied; a bump needs the owner's say-so. A test build hand-installed on a staging site must carry its own label (for example `0.14.1-test1` in the main file's version line and `TWD_SK_VERSION`), because the stylesheet and scripts are cached by that label: a build that keeps `0.14.0` keeps the old stylesheet in the browser until a hard refresh.
- **Not verified on a real site:** a real mark and a real name at these sizes, the dot and the compact row on a real theme and phone, an Elementor Pro header template taking over, a very long name on a 320px phone, the transparent header with a dark mark.

## The large footer (`feature`), branch `footer-feature`, on top of `studio-0.14`

Why: the four footers were a small logo, a line of text and a list of links with a lot of empty space, and a client's brief asked for the full logo lockup, the registration line and the contact email in the footer of every page. The owner's review asked for a stronger layout, so a fifth footer layout was added. Nothing is released; the version number is the owner's to agree.

- **Its own logo tokens.** The brief named `footer-logo-height` for the large logo, but every pack sets that to 56 to 72 pixels for the other four footers, so reusing it would either leave the new footer small or change how the old ones look (they must not change). `footer-logo-height-feature` (140px) and `footer-logo-height-feature-sm` (150px, used up to 900px wide) are new tokens with defaults, in every pack and the Site tab, so an older pack still reads. The owner can say he would rather share the old token.
- **The logo is asked for at the `large` size.** The other footers ask for `medium` (300px), which would be stretched and soft at 140 to 150px high. Only the large footer asks for more.
- **The footer text is a heading by look, not by tag.** It is a paragraph styled in the heading font at the h2 step (and the smaller h2 step on phones). A real h2 would join the page's heading outline from the footer of every page, and the page-title step is too big for one or two sentences. Changing the size is a one-line change in the stylesheet source.
- **No new colour.** The strip uses the band colour with the on-band text and the accent-on-dark links, which the contrast check already refuses to save below 4.5:1 (the Site tab and style kits both go through it); the part above it uses the surface colour with title, body and accent text. A test lists the colours the large footer's rules use and fails on any other. Every shipped pack is checked for these pairs.
- **Two blocks, always.** `footer_columns` is ignored (as the centred and simple layouts ignore it); with no menu there is one block. The email and the registration lines stay in the main area, never the strip, because the client's brief wants them there on every page.
- **Two link columns by CSS, not by markup.** `columns: 140px 2`, so a narrow phone drops to one column rather than squeezing; the list reads down each column.
- **Stylesheet ceiling raised to 120 KB** (about 110 KB now). Each rule is written with doubled class names, which is most of the weight.
- **`wp twd-sk style apply --footer=feature`** now works with no pack name: only the layouts change and the current look stays (a dry run first lists the change).
- **The older layouts are byte for byte the same.** The four older footers and the header were rendered for two profiles on a test server with the code before and after this work and compared: identical. The generated stylesheet only gained rules.
- **Not verified on a real site:** how a real lockup looks at 140 to 150px high, the two link columns on a real theme, the strip on a real browser, and with an Elementor Pro footer template.

## The second line in the smaller header, and a larger symbol (28 Oct 2026, 0.15.0 branch)

The owner asked for the pilot site's collapsed header and the header on every other page to be a little bigger (the logo about 50% larger) and to show the descriptor under the name ("Psychotherapy") on all pages in the smaller header. Decisions:

- **A new setting, `wordmark_small_line`, off by default.** The brief said the established look must not change, so nothing moves for any site until it is ticked. With it off every header prints the same bytes as before (a test compares). It does nothing without the text wordmark header.
- **It uses the existing second-line field (`logo_line`), not a new one.** The descriptor is already what the front page stack shows. One field keeps one place to change the wording. It is limited to 40 characters and is meant to be short; on one row, a long line is cut at the edge of the space rather than wrapping, because a wrapped line would make the fixed header taller than its spacer and cover the top of the page.
- **Ticking it also keeps the name beside the symbol when the front page is scrolled** (`wm_name` is true when either setting is on). The line sits under the name, so a scrolled header with a line and no name would make no sense. `wordmark_scrolled_name` stays its own setting for people who want the name without the line.
- **Name and line are one block** (`span.twd-sk-header__words`, printed only with the setting on and a line to show), so they sit together beside the symbol. On the large front page stack the block is just the name and line one under the other and looks the same as before (checked against the old render).
- **The larger symbol is a Site tab choice, not a pack change.** `wordmark-symbol` and `wordmark-symbol-sm` joined the editable logo sizes. The pack defaults (36px and 32px) are unchanged, and a test pins them, so no other site changes until someone sets a size. For the pilot site 54px and 48px (50% and 50% larger).
- **Spacers follow the size.** The bar's spacer already came from the symbol token, so it grows with it; with the line on it is the taller of the symbol, a menu link and the name plus line. The phone spacer was a fixed 77px (the 48px menu button plus 29px of padding); it is now `max(48px, phone symbol) + 29px`, which is still 77px with the standard symbol and grows with a larger one. The shrink script's idea of the compact row (56px) is now the symbol plus 20px (also 56px with the standard symbol), so a larger symbol does not leave a blank band or shrink too early.
- **Stylesheet weight.** The change adds about 2.4 KB to the raw stylesheet (a few doubled-class selectors); it is now 139,985 bytes, 15 bytes under the 140,000 ceiling. The next stylesheet change needs the ceiling raised or something trimmed first.

## The large footer, adjusted at the owner's request (28 Oct 2026, 0.15.0 branch)

The first design of the large footer kept the email and the registration lines in the main area, never in the strip, and put the links beside the logo. After seeing it on a real page the owner asked for the registration back in the dark strip and the links level with the footer text, because the logo made the links sit far above the words they belong with and the registration read as part of the contact details.

- **Registration in the strip.** Same markup as the compact footer's bar (`div.twd-sk-footer__meta` with `span.twd-sk-footer__reg-line`), so one stylesheet rule set serves both. The email stays in the main area under a small Contact label, because that layout's purpose is a visible way to get in touch.
- **Links level with the footer text, with no new markup.** The left block is turned into `display: contents` on a wide screen so the logo, the footer text and the contact group become rows of the same grid as the links; the links start on the footer text's row. The first link is lined up with the first line of the footer text by working out half the difference of the two line boxes from the size and line-height tokens, so it holds under any pack. A phone resets to the old stacked blocks. The alternative, a fixed top offset on the links column, was rejected: it depends on the logo height and goes wrong when there is no logo.
- **This changes an existing layout.** The direction rule says an existing look does not change without an option. The owner asked for this one directly, and only one site uses the layout, so it is changed in place and recorded here rather than put behind a switch. If another site needs the old arrangement, it would be a new layout name, not a setting.

## Merging the studio branch into 0.15.0 (28 Oct 2026)

Why: two builds were both labelled 0.14.1. `main` had the released one; the branch `studio-0.14` (six commits from a different working session, branched from an older point) had Add section, Move up and Move down, the editing tools switch, the Elementor guard, the compact footer and Ink at 1140, with its own zip and update file. A staging site could have been running either. The owner said yes to combining them in the 0.15.0 branch.

- **Combined in the 0.15.0 branch, never into `main`.** Merging that branch into `main` would have replaced the released update zip and file with different contents under the same version number. The 0.15.0 branch is where both sets of features meet, and the version bump (to 0.15.0, so the label stops being shared) happens only at a release the owner approves.
- **`dist/` and `readme.txt` are exactly as on `main`.** Only the release process touches them.
- **One numbering for sections: `data-twd-sk-sec`.** The Look tool had numbered sections `data-twd-sk-o`; the studio branch's Add and Move numbered them `data-twd-sk-sec`, counted the same way (top-level sections, as `TWD_SK_Sections` counts). Two attributes for one count would have been two things to keep in step, so the Look tool and its tests moved to the studio branch's name. The layouts a section can move to stay in `data-twd-sk-lay`.
- **Both footers stay.** `feature` (the large footer, on `main`) and `compact` (studio branch) are separate layouts; the footer test and variant lists name both. The compact footer's centred tagline was a bare class rule, which would have centred the large footer's tagline too, so it is now scoped to the compact footer.
- **Add section does not offer Embed.** An embed needs one of the saved embeds, and the administrators' list of kinds (`parts_menu( true )`) includes it. The panel leaves it out and `TWD_SK_Structure::add()` refuses it with a plain message pointing to the Site tab (Embeds, Add to this page), so there is one way to add one and it always names a saved embed.
- **The toolbar wraps, the panels stay inside the card.** Six tools in one row did not fit a phone, so the tool group may wrap; a panel in the card has `min-width: 0` so a long choice in a drop-down cannot make it wider than the card.

## The studio branch's 0.14.1 pieces (branch `studio-0.14`, carried into 0.15.0)

Asked for by the owner after the first look at the pilot staging site (28 Oct 2026). The generic reasons:

- **Add and move save at once, one version each.** The text numbering shifts when a section moves, so staging an add or a move together with unsaved words would need ordering rules between them. One version per structural action keeps the history readable ("Moved a pricing section down") and Undo simple, and the reload gives fresh numbering. The cost is that unsaved changes must be saved or cancelled first; the buttons say so and never lose them.
- **The page reloads back into the editor.** After an add or a move the address carries `twd_sk_edit=1` and `twd_sk_at=N`; the script opens the editor at that section and cleans the address. It splits the query string rather than using one regex: the first version of the cleaning left `?twd_sk_at=3` behind (a regex that swallows the next separator), found by running the real script in a browser. A draft page's address is `?page_id=N`, which must survive.
- **Placeholder words for a new section, and a warning on a live page.** The existing add-section engine returns the kit outline with `[PLACEHOLDER]` markers; that is what the Visual editor already turns into editable text, and the publish check holds a page back until they are gone. On a live page visitors would see those words, so the editor asks first.
- **No banner from Add; the heading section stays.** A page has one main heading, in its first hero. Add never offers a hero and the server refuses one; Move refuses to move the section holding the h1 or to move anything into its place.
- **The editing tools preference is user meta, not browser storage.** A test bans `localStorage` in the editor scripts (the editor stores nothing in the browser). User meta also follows the person between devices. It is printed on the wrappers by PHP so nothing flashes, and saved by a small route that is registered in safe mode too (a personal preference, not a site setting). It hides launchers only, so an open pop-up or editing card is never hidden.
- **Admin bar first, a floating switch as the fallback.** The switch sits after the WordPress logo, top left, where the owner asked. WordPress hides most left-hand admin bar items on a phone and some people have the bar off, so a small floating switch appears there; with the buttons gone it drops to the corner.
- **Elementor: the one door.** Checked against the Elementor 4.3.5 source: every route into the editor passes `post.php?action=elementor`, which fires `admin_action_elementor` (Elementor hooks it at priority 10, we use 1). `elementor/utils/is_post_support` only drives a body class and the files manager, and the admin bar item is built by JavaScript from documents Elementor drew on the page, which the kit template never does. So the guard replaces the editor with a plain card (response 200) and offers administrators one more click with a nonce for that page. The row action key (`edit_with_elementor`, added at 11) and the classic edit screen button (`print_switch_mode_button` on `Plugin::$instance->admin`) are removed for non-administrators on kit pages only. The real risk it closes: Elementor's Page Layout setting swaps the kit template out and the page goes blank. The words are never lost, they sit in the plugin's last ten versions. Not yet seen against a live Elementor.
- **The compact footer is a new layout.** Every release reaches every site, so the four existing layouts are untouched. The text-led pack `ink` prefers it; a site with its own footer override keeps that until it is changed.
- **Ink at 1140.** The same as sage and grove. The header and footer already share one container, so their widths agree; if they look different on a site, something else is shaping them.
- **Left open:** the narrow reading column (`container-narrow`, 680px in ink). The owner suggested about 900px. At a body size of 16 to 18px, 900px is roughly 110 characters a line, long for a site whose writing is the product; renders at about 760, 800 and 900 are to be shown before a number is chosen.

## v0.14.0 (the studio build, branch `studio-0.14`)

**The owner's answers (27 Oct 2026, evening):** the Five Keys stay articles in the Articles plugin; a JPEG logo is a warning in the studio's readiness check, not a gap; a restyle must stay reversible (it is: snapshot then apply, revert puts it back); packs keep `ember`, and `slate` became `midnight`; the SSH alias and WordPress folder are recorded once per client in the private repo, never a key.

Built in one overnight session on a branch, from the owner's "Site Kit Studio" brief, so that his own production process (private, in the clients repository) has what it needs from the plugin. The rule that every feature is verified on a real site before the next one was lifted for this build by the owner; the risk is carried as the "not on a real site" column in BUILD-LOG and the test script in `docs/test-script-0.14.md`.

- **Everything additive, nothing on by default.** Every new token has a default, every new layout is a modifier class on the same pieces, every setting is off until ticked, safe mode switches it all off. A fixture proves a 0.13 page passes the 0.14 sanitiser byte for byte. This is what lets the branch be installed on one staging site without fear for the sites on `main`.
- **Second layouts are modifier classes, not new components.** So a restyle can swap them (`TWD_SK_Style::swap`) with the words untouched, and the Visual editor, the sanitiser, the leftover check and the AI style guide need no new rules. The cost is that a layout can only rearrange the pieces a component already has.
- **Restyle is tokens and layouts only.** `TWD_SK_Style` changes the pack, the overrides and the header and footer settings and never reads page meta (a test checks). The snapshot is taken before any change, so there is always a way back without the page history.
- **Hero options as extra classes.** Overlay strength and alignment are plain classes beside `twd-sk-hero--image` rather than variants, because a variant is one skeleton and these combine. The pop-up does not offer them yet.
- **The transparent header prints only over a picture.** White words on a page that opens with a plain section would be unreadable, so the option is applied per page by looking at the page's first section, never blindly. The open phone menu gets a solid panel for the same reason.
- **The parallax hero never needs its script.** The picture stays still without it, on phones and for reduced motion; the script only sets one CSS variable. No library, no scroll hijack.
- **Picture treatment tokens stay out of the Site tab for now.** `image-filter` is a strict pattern, not a colour or a size, and the Site tab has no control kind for it. Packs and style kits set it.
- **Remove this.** Removable things are numbered the same way as editable text, so Save sends numbers, not markup. A wrapper left empty goes with its item (the map button case from a client site), but a section is never emptied and the first hero and the safety notice can never be removed.
- **Launch check and the Articles plugin.** Site Kit reads only the plugin header for the version, never its code. The warning threshold is 1.42.0 because that is where the grid started drawing its first cards on the server.
- **Fonts.** Eight more families (all SIL Open Font Licence, variable, Latin subset) so that recipes can give each client a different pairing without a request to any font service. Only the active pack's two are ever loaded.

## v0.13.0 (Shrink on scroll)
- Setting `shrink` in `twd_sk_chrome` (default off), effective only with the fixed header (so never the centred layout, never safe mode, where the plain header prints). Also in the brief as `header.shrink`.
- A second small visitor script, `twd-site-kit-header-shrink.js`, separate from the mobile menu script so a site without the setting loads nothing extra. One class, `twd-sk-header--shrunk`, added past 60px of scroll and removed under 20px (the gap stops flicker). The server never prints `--shrunk`.
- No layout shift: the fixed header's spacer (`twd-sk-chrome--sticky`, 76px or 112px with the strip) is untouched; only the header inside it gets shorter (padding 14px to 6px, logo 48px to 32px).
- Logo size: the owner's SK-002 note was not found in this repo. The logo max height now reads the variable `--twd-sk-logo-h` (default 48px), so a future logo size setting only has to set that. No setting was added for it.
- Theme Builder: a Pro template that contains our `[twd_header]` shortcode gets the same behaviour (same output); a Pro header that does not use our shortcode is never touched because we print nothing there.

## v0.12.0 (Full site set up)

- **The ordinary build fills gaps with placeholders, so the owner has to hunt for them, and an AI left to fill a gap can invent.** The owner asked for an option that does not build until enough is known, and leaves only photos as places to fill.
- **The AI only asks; the code decides.** What counts as "enough" is a floor in code (name, person, something offered with detail, story, where they work, registration or none, questions or skip) plus whatever the AI also asks for. Every claim the AI makes about the notes must be backed by the words really being there, or it is ignored, so a made up name, email or phone cannot get through.
- **The page list and each page's sections are chosen by code**, from what was supplied, so a testimonials section is never written when no testimonials were given, and a phone row is never made when there is no number.
- **Email defaults to info@ on the site's own address** (the owner's rule), said so on the screen. A phone is never defaulted.
- **A page the AI cannot write is not saved as an outline.** An outline full of placeholders is exactly what this option exists to avoid.
- **Photos are the one thing left open**, as a marker that says what photo is wanted and a Pexels search to start from, in the shape the Visual editor already understands. The marker is capped at 60 characters because the sanitiser treats longer bracketed text as a shortcode and removes it. "No photos" builds layouts without picture spots instead (hero compact, text instead of image and text, a plain contact section).
- **After writing, the code checks the page, because telling the AI not to invent is not proof.** Leftover placeholders, and any email, phone number or price not in the supplied facts, are listed. This cannot prove every sentence true, so pages stay drafts.
- **A test option, off by default**, on the first step of the setup page. It needs the AI key.
- **Deferred:** a Visual editor button to switch a photo spot to a layout without a photo after the build (needs a section by section conversion).

## v0.11.0 (Tell us)

- **Clients need a way to say what they want changed without a phone call or a wp-admin visit.** The owner asked for a "Tell us" button on the front of the site, notes kept on the client's own site, and a way to pull them over SSH. No GitHub token or outside service on a client site, so nothing can leak and nothing needs to be rotated.
- **Text only in version one.** Photos would land in the public-by-address media library and add upload limits and clean-up. A note names the page automatically instead.
- **No web addresses anywhere.** They are removed from a message when saved, so no export, email, panel or command can ever print one, and a client's pasted link cannot end up in a file the owner keeps.
- **Ideas share the record with a type field** so the owner has one list; they are never shown to clients or counted for them.
- **The email is a notification only** (a note arrived, and the page), never the message, so a note about something private never travels by email.
- **Retention is a setting, 12 months by default**, and the sweep runs once a day on a normal page load without wp-cron, even when the module is off, so a site that switches it off still forgets old notes.
- **The module is a normal Site Kit module, not a separate plugin**, so it ships, updates and is switched off by safe mode like everything else.
- **Edit text became Visual editor** at the owner's request. Nothing else about it changed.
- **The verify-first rule was lifted for this one feature** by the owner on 14 Oct 2026, so it is tested together with 0.7.0 to 0.10.2.

## v0.10.2 (the Edit text card)

- **The toolbar clashed with the Articles plugin's New Article button**, which sits bottom right. The full-width bar became a compact card at the bottom left, where the Edit text and Edit with AI buttons already are (they hide while it is open). On a phone there is no room for both, so the card spans the screen and sits above other floating buttons while editing.
- **The admin bar too**, as the owner suggested: Edit text there starts editing, then the same item becomes Save changes with a count, next to Cancel. On a computer, saving is always at the top of the screen whatever else sits at the bottom. WordPress hides these items on phones, where the card does the job.

## v0.10.1 (pictures in Edit text)

- **The owner found a spot the AI had marked "[PLACEHOLDER: image needed]"** on a page and could not put a picture there with Edit text, since it was words, not a picture. Markers like that now show as Add a picture boxes while editing, and choosing a picture puts a real one in its place with the look of the section's other pictures. Caption and description markers stay as words to type over.
- **Upload from this computer, as well as the media library.** The media library window can already upload, but that is easy to miss. A clear second button opens the computer's own file picker and puts the file into the media library through WordPress's own upload route (so WordPress checks permission, file type and size as usual), then uses it.
- **Descriptions on their own.** A picture's description (alt text) can now be fixed without swapping the picture, since setup-written descriptions are guesses until checked.

## v0.10.0 (the editing side, rebuilt)

- **The owner asked for a simpler editing side** after the first real site was built from a brief: one place to ask for changes, a way to fix words directly, a list of edits in one go, and two new looks (boxes with moving borders, a testimonial slideshow). Agreed with recommendations: swipe and arrows only (no autoplay), colours from the site palette only, and in-place editing for everyone who can already edit the page.
- **Edit text in place, not a page builder.** A full WYSIWYG editor would let anyone add stray colours, sizes and headings, which is what keeps these pages clean, readable on phones and accessible. Changing words, links and pictures where they sit covers most corrections with none of that risk, needs no AI and costs nothing.
- **Only the changed pieces travel.** The page is numbered when drawn for an editor and renumbered the same way when saving, so the browser never sends back a whole page, and the server version check means a stale screen cannot overwrite newer work.
- **One box, then a checklist.** Asking the AI for a short, checked plan first (rather than a rewritten page) means each change touches only its own sections, nothing runs out of room, and the person can untick anything before a word changes. Things the kit cannot do are said plainly with the nearest option, rather than half done.
- **The request is the only new source of facts.** Every step gets the whole request as the facts the person supplied, so testimonials and names they paste are used word for word and nothing else new is allowed.
- **New looks are kit parts.** The glow and the slider are registry variants with tests, contrast-safe colours and reduced-motion support, not styling the AI writes. The glow animates the outline colour because the kit's visual properties are `!important`, which an animation cannot override.

## v0.9.3 (reading the brief reliably, and building again)

- **A copied brief arrived broken and the build went ahead anyway.** The brief's facts were one line of over 12,000 characters; copying the file from a viewer most likely cut that line, so the JSON no longer read. The build only warned, then made the starter set's pages with no facts, a menu and a button for them.
- **Facts can be a list of lines**, so no line is long enough to be cut, and the brief is easier to read and edit by hand. A single string still works.
- **Choose the brief file** reads the file itself: no copy and paste at all.
- **An unreadable brief says where**: the line and the text there, that it ends too early, or that it has curly quotes. And the build refuses to run until it reads, because building without it does more harm than good.
- **Building again puts things right.** The menu and header button the setup filled in itself are filled in again from the new plan; anything a person typed or changed is kept (a fingerprint from now on, and a careful match for earlier builds). Pages from the earlier plan are listed, never deleted, and the about link moves to the new about page. A page moved to the bin is made again rather than rebuilt in the bin.
- **The logo slot** now says it is the header and footer logo, not a banner photo, and its preview shows the whole logo instead of a square crop.

## v0.9.2 (no page or brief too long for the AI)

- **The first real brief hit the ceiling.** Pasting the first real client brief and pressing "Turn my notes into a brief (AI)" sent the whole 24 KB brief to the AI to retype, and the Articles plugin stops every reply at 8000 tokens ("The AI ran out of room before finishing"). The owner asked for no limit on admin.
- **The ceiling lives in the Articles plugin**, which this project does not change. So Site Kit works within it instead of asking for more: smaller replies are faster too, and a very long single reply also risks the 120 second request timeout.
- **A pasted brief needs no AI.** The notes button checks it as a brief (in the browser and on the server), so it cannot be sent to the AI by mistake.
- **Long notes are kept as the facts word for word.** The AI then writes only the site details, menu and pages, which stay small. Short notes still get tidied under the fact headings as before.
- **Long pages are written in parts.** More than six sections means groups of up to four, joined in order. Any part that still runs out of room is halved and tried again, so in practice no page is too long. Later parts are told not to write a main heading (and one is demoted if they do) and which pictures are already used.
- **Six sections or fewer is unchanged**: one call, the same prompt, so existing sites behave exactly as before.

## v0.9.1 (Site Kit places the header and footer)

- **Elementor Pro made optional.** The owner asked to keep setup as simple as possible and to keep Elementor Pro off unless needed. On the Hello theme Site Kit now switches off Hello's own header and footer and prints the kit ones itself, so a fresh site needs no Theme Builder import.
- **Hello only, on purpose.** The hooks used (`hello_elementor_header_footer`, `wp_body_open`, `get_footer`) are Hello's; on another theme Site Kit does nothing and the launch check says so, rather than guessing where a header belongs.
- **Elementor Pro still wins where it applies.** If a Theme Builder header or footer covers a page, Site Kit stands aside for that part, so an existing Pro site keeps working, and the setting can hand the job back to Elementor entirely.
- **Fails towards Hello.** Safe mode, an unreadable setting or the Canvas template all leave Hello's own header and footer (or none on Canvas), never a broken page.
- **A skip link comes first.** Printed inside our header so it is styled by the kit and is the first thing a keyboard user reaches. Hello's own skip link still prints after the header; two skip links do no harm and hiding Hello's would mean styling a theme class.
- **Hook names used from memory.** `hello_elementor_header_footer` and `elementor_location_exits` were not checked against the current Hello and Elementor Pro code from here; the test script asks for a real-site check.

## v0.9.0 (the launch check)

- **One place to see if a site is ready.** The checks already existed in pieces (leftovers, quality, search wording, the site report); the launch check gathers them with the site-wide things people forget: a privacy policy, the front page, "discourage search engines" left on, https, the header and footer templates, and the support lines.
- **Fix, look or fine.** Only things that would embarrass or break a launch are "fix"; reminders that need a person (send a test enquiry, check the support lines) are "look".
- **Publishing stays strict.** The launch step only publishes pages with nothing left to replace, through the same rule as the Pages tab; there is no override here. The confirmation is inline, never a browser pop-up.
- **The whole roadmap was built in one day at the owner's request (beta).** None of 0.7.0 to 0.9.0 has been tried on a real site; `docs/test-script-0.7-0.9.md` lists what to try, riskiest first.

## v0.8.4 (starter sets)

- **Different kinds of business need different first pages.** A hire business needs prices, areas and booking; a trade needs accreditations and a quote form; a counsellor needs fees and questions. A starter set is a ready list of pages and sections for one kind of site.
- **Data, not code.** Each set is one JSON file checked like a brief, so a new kind of site is a new file. A broken file is skipped and named in the site report rather than half used.
- **The brief still wins.** A set is only a starting point when the brief does not list pages, and the Pages step can change everything before building.

## v0.8.3 (keep in view)

- **Crops were cutting off faces.** Hero, picture grid and image-and-text layouts crop to fill their space; a person's head near the top of a portrait got cut. A choice of nine positions per picture, stored with the picture rather than in each page, fixes every page that uses it.
- **Chosen in WordPress's own media screen** as well as on the setup page, so it is found where pictures are managed. Added at display time only, so stored page HTML still never holds a style.
- **No AI alt text yet.** The AI filters Site Kit shares with the Articles plugin take text only, so the AI cannot look at a picture. Suggesting alt text needs a third filter in that plugin (a cross-project change, listed in BUILD-LOG); until then the description typed on the setup page is the alt text, and the Search tab and site report flag pictures without one.

## v0.8.2 (colours from the logo)

- **The fastest route to an on-brand first look.** Most small businesses have a logo and nothing else of a brand; their colours set the buttons, headings and bands.
- **Readable beats exact.** A logo's own colour is often too light for white button text or headings. The colour is darkened step by step only as far as needed, and anything that still fails the 4.5:1 check is left as the style's own and named, so the site never becomes hard to read to match a logo.
- **No new library.** GD ships with almost every PHP host; SVG logos are read from the colours written in them. Without GD the button says so rather than guessing.
- **Preview first, as with AI suggestions.** Nothing is saved until Save style; Undo puts the saved colours back.

## v0.8.1 (the enquiry form)

- **Forms stay out of page HTML.** Everything a person or an AI writes is still sanitised with forms banned; the form is built by the plugin from a shortcode at display time, so nobody can inject one.
- **No JavaScript, cache-safe.** 20i caches pages, so a WordPress nonce in a cached page would go stale and break the form. A signed time-and-page token with a 30-day window does the same job (proves the form came from this site, slows bots with a 3-second minimum) and survives caching.
- **Store nothing.** A submissions table would be personal data held on every client site, possibly special-category for therapists. The message goes by email only; the site keeps counts for the report.
- **Limits per visitor and per site** so a bot cannot use the form to flood the owner's inbox, and a filled honeypot gets a fake success so it learns nothing.
- **The help line follows the mode:** therapy sites say not to include anything urgent and point to 999 and the support lines; business sites say a reply is coming and not to include payment details.

## v0.8.0 (five new sections)

- **What business sites (and many therapy sites) were missing:** a picture grid, a strip of accreditation or partner logos, prices, opening hours, and the areas covered. They are ordinary registry components, so the sanitiser, the AI style guide, the Pages step, Add a section and the gallery all pick them up with no other change.
- **A map is a link, not an embed.** Iframes stay banned; the areas section has a button to a map instead.
- **No team component.** Kept folded into image_text, as decided in 0.2.0.
- **Every new sample word is a leftover marker,** so a price, a day, a time or an area that was never replaced blocks publishing ("Price here" is described as "never use an invented one"). The two sample button texts are "check" level, like the others.
- **The enquiry form is its own release (0.8.1)** because it sends email and needs its own protections.
- **Headings follow the style's alignment,** so the area chips and the price note line up with the heading rather than being centred under a left-aligned one. Seen in the screenshots.

## v0.7.3 (add a section)

- **Parked since 0.6.0, now built on the same rails as everything else.** A new section is a candidate page that goes through the same preview, apply and history as a pasted or remixed one, so there is no new way to save anything.
- **Works without a key too.** Without AI it adds the outline with [PLACEHOLDER] markers, which the publish check holds back until they are filled in.
- **The AI writes only the new section,** and must send back one section of the kind chosen; anything else is refused, so it cannot rewrite the rest of the page on the way.
- **No heroes in the list.** A page has one hero at the top; a second one is rarely wanted from this control and would complicate the one-h1 rule. The outline builder now gives any later hero an h2 title anyway.

## v0.7.2 (the Pages step)

- **Fix the plan before spending anything.** The best idea in the AI site builders looked at (Elementor's Site Planner) is a sitemap and section outline you can change before anything is generated. Here that is a page list with sections, before Pictures (so picture "use on" lists the final pages) and before Build.
- **Sections are data, checked like everything else.** Only real components and layouts, the first a hero (the one h1 rule), at most twelve. The same check runs whether the list came from the setup page or from `sections` in a brief, and again at build time in case the rules changed.
- **An edited list replaces the brief's, explicitly.** Mixing the two would be confusing; a notice says the edited list is in use and one button goes back to the brief's.
- **Pages are only sent when changed,** so walking through the step without touching it never freezes the brief's list.

## v0.7.1 (the setup page)

- **A normal WordPress admin page, not a front-end pop-up.** The media uploader works best there, it looks like the rest of WordPress so there is nothing to style per site, and leaving the page loses nothing because each step is saved. The front page only carries a small link to it, for administrators, until the site is set up.
- **One request per step.** A whole site in one request would time out on shared hosting as soon as AI writes the pages. Each page is its own request, one failure never stops the others, and any step can be run again: the page ID is recorded the moment it is made, so a repeat gives a new version instead of a second page.
- **Pictures by name, placed before cleaning.** The AI cannot know media library addresses and must not invent them, so it is given short names (`pic:front-view`) and the real addresses are put in by code. That has to happen before the sanitiser, which rightly drops an unknown address scheme. A name that is not on the list is never trusted.
- **Notes to a brief, shown before saving.** Typing JSON is not reasonable for most people, so notes can be turned into a brief by the AI, with the same never-invent rules as the interview. The result goes back into the box to be read and corrected; nothing is saved until Check and Build.
- **A basic menu and button when there are none.** A fresh site needs navigation to be usable at all; it is built from the planned pages and never replaces a menu that exists.
- **The build log in the site report.** When something goes wrong on a client site, the report shows what each step did without anyone describing it.
- **WordPress's `.button` beats the `hidden` attribute,** so the setup stylesheet hides `[hidden]` explicitly. Found in the browser check.

## v0.7.0 (therapy or business mode)

- **The plugin is now used for non-therapy sites too.** Rather than a fork or a second plugin, one setting picks the words. Therapy stays the default and its wording is byte-for-byte what it was (checked by diffing every prompt before and after), so no existing site changes.
- **One word list, in one file.** Mode words were scattered through prompts, recipes, the facts outline, the brief and the Site tab. They now all come from `TWD_SK_Mode`, and a tokenizer test fails if another PHP file hard-codes therapist wording. Future modes (coach, trades) are data, not code.
- **Registration lines stay in business mode, relabelled.** The plan said hide them; trades and many businesses have accreditation or trade body lines that matter just as much, so the field shows as accreditation lines instead. The job title is hidden because it rarely means anything for a business.
- **Safe mode reads as therapy.** It is the wording every earlier release used, so it is the safest fallback. A database fault also falls back to therapy rather than stopping a page or the report.
- **The schema id stays `#practice` in both modes.** It is only an identifier; keeping it means the Yoast duplicate check is unchanged.
- **Built ahead of real-site checks, at the owner's request (beta stage).** 0.4.0 to 0.6.2 are still unverified on a real site; the owner asked for the whole roadmap to be built and tested afterwards.

## v0.6.2 (the site report)

- **Problems were being diagnosed from descriptions and screenshots.** The report states the facts instead: versions, safe mode, the Theme Builder templates and their display conditions (the thing most often wrong on a new site), which pages use the kit and what is left to fix, and the pictures with their alt text.
- **Paste-safe by construction.** It prints whether something is filled in, never what it says, and every value is stripped of markup and control characters, so a page title or a file name cannot break the layout or carry code. A test checks that no email, phone, address, registration line, fact text or key can appear.
- **The command line version works in safe mode,** because safe mode is when it is most needed. The Site tab button follows the rest of the Site tab and is off in safe mode.
- **Site Kit reports the articles plugin through the active plugin list,** not by reading its constants, so the rule that this plugin never references that plugin's code still holds.

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
