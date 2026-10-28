# Test script: 0.15.0 (branch `claude/twd-site-kit-0-15-0-mjih8y`)

0.15.0 so far: the Embed section, the editable "no articles" line, Bring pages up to date, the Look tool in the Visual editor, the picture look and question-group editing. Tested against the plugin's own test suite (953 tests) and, for the "no articles" line, the Look panel and the question groups, in a real browser with the real stylesheet. The Embed screens have not been tried on a real site. Work through this on a **hidden staging site** only. Riskiest first. If anything goes wrong: `wp twd-sk safe-mode on` (embeds print nothing in safe mode), then send `wp twd-sk doctor`.

Before you start: install the test zip `0.15.0-test2` by hand (Plugins, Add New, Upload Plugin, replace). Take a snapshot first (`wp twd-sk style snapshot "before 0.15"`) and keep the zip of the plugin as it was, so there is a way back.

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
