# Test script: 0.15.0 (branch `claude/twd-site-kit-0-15-0-mjih8y`)

0.15.0 so far: the Embed section, the editable "no articles" line, Bring pages up to date, the Look tool in the Visual editor, the picture look and question-group editing. Tested against the plugin's own test suite (972 tests) and, for the "no articles" line, the Look panel and the question groups, in a real browser with the real stylesheet. The Embed screens have not been tried on a real site. Work through this on a **hidden staging site** only. Riskiest first. If anything goes wrong: `wp twd-sk safe-mode on` (embeds print nothing in safe mode), then send `wp twd-sk doctor`.

This branch now also carries the studio build's pieces (Add section, Move up and Move down, the editing tools switch, the Elementor guard, the compact footer, Ink at 1140); their steps are in `docs/test-script-0.14.1.md`, run them after this script. One check that only exists in the combined build is at the end of this script (section 7).

Before you start: install the test zip `0.15.0-test3` by hand (Plugins, Add New, Upload Plugin, replace). Take a snapshot first (`wp twd-sk style snapshot "before 0.15"`) and keep the zip of the plugin as it was, so there is a way back.

## 1. The no-articles line

1. **Old pages are unchanged.** Open a Resources page built before this version, on a site with no articles in the grid. It shows the Articles plugin's own line exactly as before (the page has no new line yet).
2. **A new Resources section has its own line.** On a site with no published articles: Edit with AI, ask for a new Resources section at the end of a page (or build a new page with one). Apply. As a visitor the grid area shows "No articles yet. Please check back soon." once, in your page's text style, centred.
3. **Edit it.** Visual editor: a small blue label "Shown only when there are no articles yet" appears above a dashed box with the line. Click, type "Articles are coming this autumn.", Save. As a visitor the new words show, once. With the Visual editor closed there is no blue label.
4. **It steps aside when there are articles.** Publish one article, reload the page as a visitor: the grid shows the article and neither line shows. In the Visual editor the line is still reachable (it is only hidden to visitors).
5. **The Strip layout.** Add a Resources Strip section with a category that has no articles: the line shows left-aligned under the intro. With articles it disappears.
6. **A phone.** The line is centred (left-aligned in the Strip) and readable at 375 pixels.

## 2. The Embed section

Everything here is as an administrator on a page that uses the kit template.

7. **Set one up.** Site tab, Embeds, New embed. Name "Booking calendar", Kind "A shortcode", Code: a real shortcode from a plugin you use (or `[twd_articles count="3" columns="3"]` to try it safely). Save. It appears in the list as "Not on any page yet."
8. **Add it to a page.** Open the page you want, Site tab, Embeds, Add to this page. The Edit tab opens with a preview that ends in a new Embed section showing your shortcode's output, with a heading marker to write. Apply. (The note box is filled in for you.)
9. **Write the heading, or leave it out.** Visual editor: click the heading marker and type a heading, Save. The page can now be published. For no heading at all, Undo the last change, tick "When I add one to this page, leave out the heading" in the Embeds fold-out and use Add to this page again: the new section has only the embedded code and nothing to write.
10. **As a visitor.** The embed shows once, inside the page, inside the column. A phone shows it inside the screen with no sideways scroll (wide tables and frames shrink).
11. **HTML code.** New embed, Kind "HTML code", paste a snippet from a service you trust, Save, Add to this page. It shows as given. If your WordPress account cannot post raw HTML (a multisite administrator), the HTML kind refuses to save with a plain message and a shortcode still works.
12. **Change it.** Embeds, Change, edit the code, Save: every page that shows it changes at once, and the pages' history shows nothing new (the code is not part of the page).
13. **Keep it apart.** Edit with AI on that page, "Copy prompt for external AI": the prompt does not hold your code. Ask the AI (with a key) to "rewrite this page": the Embed section comes back untouched. `wp twd-sk get <page id>` shows only `[twd_embed id="N"]`.
14. **Only administrators.** Sign in as an editor: there is no Site tab; the Embed section is on the page and still shows; the Visual editor cannot select it; Remove can take the whole section out and Put back or Undo restores it.
15. **Delete.** Embeds, Delete on one used on a page: it asks first and names the page. Say yes: the page now shows a gap for editors (a short note) and nothing for visitors, `wp twd-sk check <page id>` and the launch check name it as must-fix ("embed missing"), and the page cannot be published until another embed is added.
16. **Safe mode.** `wp twd-sk safe-mode on`: the embed shows nothing and no raw `[twd_embed ...]` text appears. Off again, it returns.
17. **Command line.** `wp twd-sk embeds` lists them without code (add `--code` to show it); `wp twd-sk embed-set --label="Map" --kind=shortcode --code='[map]'` makes one; `wp twd-sk embed-delete <id>` removes one. `wp twd-sk doctor` has an Embeds section with counts and never a name or any code.
18. **Not offered to the AI, a brief or a plan.** A site brief or a starter set that lists an `embed` section is refused with a plain message. The one-box planner never offers to add one.

## 3. Bring pages up to date

19. **See what is waiting.** On a site with a Resources section built before 0.15.0 (it has no editable line): Site tab, "Bring pages up to date" lists the page and says what would be added, in plain words. The launch check has one warning for it. `wp twd-sk upgrade` lists the same and changes nothing.
20. **Do it.** The button asks first; say yes. The page now has the line (as a visitor with no articles: your wording, once; with articles: nothing extra). The history shows a new version "Brought up to date: resources_none". Reload the page and open the Visual editor: the line is editable. Undo in the history puts the old page back exactly. Run it again: "Every page is up to date."

## 4. The Look tool (Visual editor)

21. **Open it.** Visual editor, click inside a section, press **Look** in the toolbar. A panel says "Look of this section: ..." and offers only what fits. Click inside the safety notice and press Look: it says there is nothing to choose.
22. **Layout.** On a services section with cards: choose Glow; the boxes change at once, your words stay. On a standard FAQ: choose Two columns. On an image-and-text section: choose Reverse. A section with no twin (pills, a call-to-action strip, a hero with a picture beside the text) offers no layout list. Changed sections get a green outline until you save.
23. **Hero picture options.** On the full-width picture hero: Picture overlay (Normal, Lighter, Stronger), Words on the left, and Picture moves a little as you scroll. Switch each and look. A hero with the picture beside the text shows none of these.
24. **Background.** On a section with a plain, soft or tinted background choose another; text stays readable. The dark band sections do not offer it.
25. **Save, Cancel and Undo.** Press Save: the page reloads with the look kept and the history shows "N section looks". Undo restores it. Make a change and press Cancel, Throw them away: the look goes back. A look and some changed words save as one version.

## 5. Picture look

26. **Choose.** Site tab, Style, "Pictures": try Black and white and a Warm tint, watch the page change at once, press Save style. Reload: kept. Pictures beside text get the tint; the full-width hero does not. Choose "As they are" and "None": the page looks as it did. Reset also puts it back. The ember style shows "The style's own setting" for its filter.

## 6. Question groups

27. **Editing.** On a page with the Groups layout: Visual editor. All groups show, stacked. Type new words in a tab: its group heading changes with it; type in a heading: its tab follows. Save: the page shows the new tab words and each tab still opens its own group. Press Link with the cursor in a tab: it says a tab's address cannot be changed.
28. **Removing.** Click a group's tab entry and press Remove: the tab and its group go together; if it was the first group, the next one is shown first on a wide screen. Put back before saving restores both.

## 7. The combined toolbar (Look beside Add section, Move up and Move down)

1. **One card, every tool.** Open a page with the Visual editor, click inside a section. The card at the bottom left shows B, I, Link, Look, Remove and Put back, then Add section, Move up and Move down, then Save changes and Cancel. On a phone the tools wrap onto more rows and nothing is cut off at the right edge.
2. **One panel at a time.** Press Add section (a panel opens with the kind and layout choices), then press Look: the Add panel closes and the Look panel opens. Press Add section again and the Look panel closes.
3. **No Embed in Add section.** The list of kinds has no Embed (as an administrator too). Embeds are added from the Site tab (Embeds, Add to this page).
4. **A move keeps the look.** Change a section's layout with Look and Save. Press Move down on that section: it moves and keeps the new layout.
5. **Panels stay inside the card.** Open the Add section panel and look at the two drop-down lists: they sit inside the card and do not run past its right edge, at desktop and phone width.

## 8. The Snag button (administrators only)

Brought into the branch on 4 Nov 2026 from the code that ran as test build 65 on the pilot staging site. Nobody but an administrator is given it. Do this as an administrator on a kit page, then repeat step 5 as an editor-role user.

1. **Three ways in.** Open Your Edits (the admin bar menu): it lists "Snag (Alt+Shift+S)". Open the editing window: its header has a Snag button. Press Alt+Shift+S anywhere on the page, even with the cursor in a text box: the Snag box opens with the cursor in the line.
2. **What goes with it is shown first.** Under "Saved with it, on this site only" the box lists the page and its version, the section, the view, the browser and size, the script errors (a count) and how many editor events are attached. It says plainly what is never saved.
3. **Save a fault.** Before pressing Snag, do a few things (open a tab, click a section pencil, open the Look panel). Press Snag, type "The Look panel does not close.", leave Fault ticked, Save. The box says "Saved as snag 1. It is in Settings, Snags." and the line is cleared. Escape closes the box and puts focus back where it was.
4. **A line that is too short.** Type two letters and Save: "Write a line about what went wrong or what you want." and nothing is saved.
5. **Clients cannot see it.** As an editor-role user there is no Snag item, no Snag button, and Alt+Shift+S does nothing. In the browser's network panel nothing is requested from the snag script. (An administrator who switches to View as Client still has it, by design, and the Snags list tab is not shown in that view.)
6. **The list.** Settings, Snags: "1 snag (1 open, 1 not yet exported; the list keeps the newest 200)". The row reads date, page, "Fault: The Look panel does not close." and "Snag 1, Open, not yet exported, build ..., N events". Detail opens a read-only box with the context and the lead-up (the presses, tabs and routes before Snag, oldest first, seconds before). Mark done, Won't do and Reopen change the status and the count. The Show list filters Open, Done, Won't do and All.
7. **Included texts.** Preview a change in the AI tab first. Press Snag, tick "Include the last Preview and What changed note", Save. In the list the row says "with the preview texts" and Detail shows the note and the HTML and CSS.
8. **Nothing secret goes in.** Type "my password: hunter2 and Authorization: Bearer abc123" as the line and Save. Open Detail: both secrets read "(removed)". The record never holds the words typed on the page (only button names, routes and status codes) and the address in the context is the path only, with no query string.
9. **Export from the laptop.** `wp twd-sk snags list` prints the open snags as JSON. `wp twd-sk snags show 1 --format=md` prints one with its detail. `wp twd-sk snags export --site=client-name` prints the snags.md lines (a short snag with its detail indented under it, a long one with a pointer to clients/client-name/snags/ID.md followed by the file's text after a marker line); the same with `--mark` marks them exported, and a second export says "Nothing to export." `wp twd-sk snags` with no first word is still the Tell us command.
10. **Safe mode.** `wp twd-sk safe-mode on`: no Snag item, no button, no shortcut, and the /site/snagbook routes are gone (the command line still works); the saved snags are still in the database. Turn safe mode off and the list is back.

## 9. Delete section in the administrator's section row

Added on 4 Nov 2026 at the owner's request. As an administrator on a kit page with at least two sections besides the title.

1. **One click away.** Open the editing window (On page edits) and click inside an ordinary section, such as a text or services section. The section row reads Describe a change (if the AI is on), Replace this section, Import a page, **Delete section**, Advanced. Delete section is not hidden behind Advanced. Press Advanced: Look, Move up, Move down, Add section after and Export are there, and Delete section is not repeated.
2. **It asks once.** Press Delete section: a line asks "Delete section N (name)? You can bring it back with Put back before saving, or with Undo after." with Delete it and Keep it. Keep it closes the question and changes nothing.
3. **Delete it, then change your mind.** Press Delete section, then Delete it. The section disappears from the page, the status says it was deleted, and Put back appears. Press Put back: the section is back where it was. Delete it again and press Save changes: the page reloads without the section; Your Edits, Undo the last page change (or History and undo) brings it back.
4. **Never the title section or the safety notice.** Click inside the first section (the one with the page's main heading) and then inside the safety notice (if the page has one): the section row shows Describe a change, Replace, Import a page and Advanced, and no Delete section.
5. **Nothing before a click.** With the window open and nothing clicked, there is no section row and so no Delete section. After you delete a section (and nothing is selected), it is hidden again.
6. **Other people are not changed.** As an editor-role user (the Simple editor) there is still only the pen, Save, Undo, Redo, History and Done: no delete of any kind. They can ask for one with Tell us. In the classic editor (Your Edits, Use the classic editor) the bar still has Remove as it did.

## 10. The compact, grouped Your Edits window (step 1)

Added on 4 Nov 2026 at the owner's request. Nothing here changes what a tool does; it changes how the window looks and is grouped. As an administrator, then as an editor-role user. Look at a computer width, then at a phone width (or the browser's phone size).

1. **One slim header.** Open Your Edits, Edit this page. The header is one row: the title and six small icons (a flag for Snag, an eye for View as Client, a window for Window, corners for Full height, a bar for Minimise, a cross for Close). Hover each one: its words show as a tooltip. Tab to each one with the keyboard: a screen reader says its name.
2. **The window menu still works.** Press the window icon: the menu still offers Dock on the left, Dock on the right, Floating and Centred; its tooltip now reads "Window: floating" (or the mode you chose). Press the corners icon: the window goes full height; press it again to go back (its tooltip changes between Full height and Normal height).
3. **Groups.** With nothing clicked the window shows "This page" with Add a section at the end, Import a page and Export this page. Click in some words: "This text" shows B, I and a link icon; "This section" shows Look, up, down and plus icons, and a bin icon for a section that can be deleted; under them Describe a change (if the AI is on), Replace this section, Import into this section and Export section. There is no Advanced button.
4. **More.** Press More: Quote line, Note box, Heading above and a bin for "Delete this text" show, then Text colour, Alignment and Size side by side (two to a row), then View at.
5. **Delete section.** Click in an ordinary section: the bin is in the "This section" icons. Click in the first section (the one with the page's main heading) or the safety notice: no bin. Press the bin: it asks "Delete section N (name)?" with Delete it and Keep it. Keep it changes nothing.
6. **Bottom row.** Undo, Save changes and Cancel stay in one slim row at the bottom, and Save changes is the coloured one.
7. **A phone.** At a phone width the tool buttons show their words beside the icons and are easy to tap (about 48 pixels tall); the header shows View as Client, Minimise and Close as icons (Snag, Window and Full height are hidden on a phone as before); the window is a sheet at the bottom.
8. **Clients unchanged.** As an editor-role user (the Simple editor): the pen, Save, Undo, Redo, History, Done and Tell us as before, no icons, no groups.
9. **Without icons.** If an icon ever does not draw, the button still shows its words (the page can be checked with the browser's developer tools by looking at a header button: it holds an svg and a span with the words).

## 11. The window follows what was clicked (step 2)

Added on 4 Nov 2026. As an administrator, at a computer width, then at a phone width. Nothing here changes what a tool does.

1. **Nothing clicked.** Open Your Edits, Edit this page. You see the short hint line, the list of sections and "This page" (Add a section at the end, Import a page, Export this page). There is no Page tools link.
2. **Click some words.** The hint, the list and "This page" go; the window reads "Words in Section N: ..." with a "Page tools" link, "This text" (B, I, link), "This section" (Look, up, down, plus, bin) and More. The window is shorter than before.
3. **Page tools.** Press Page tools: the hint, the list and "This page" come back, and the link now reads "Hide page tools". Press it: they go again. Click different words: they go away on their own.
4. **More.** With words clicked, Describe a change (if the AI is on), Replace this section, Import into this section and Export section are not shown. Press More: they show, with the rest of More. Press Fewer: they go.
5. **A section, not words.** Press a pencil on the page (a phone: pick a section from the list): the section's icons and those four actions show straight away, the "This text" icons do not, and an All sections link shows. Press All sections: the hint, the list and "This page" come back. Pick a section from the list: they go again.
6. **Delete section is still one click.** With words clicked in an ordinary section the bin is in "This section". In the first section (the page's main heading) and the safety notice: no bin.
7. **A phone.** Same rules; the tool words show beside the icons and the window is a sheet at the bottom. The page behind the sheet is dimmed, so pick what to change from the list of sections.
8. **Clients unchanged.** As an editor-role user (the Simple editor) there is no Page tools link and nothing else has moved.
9. **The label.** The small version label under the footer is now hidden unless the editor shows it; the version is still the last line of the Your Edits menu.
