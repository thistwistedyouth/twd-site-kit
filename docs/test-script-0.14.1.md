# Test script: 0.14.1 (branch `studio-0.14`)

Built on the same branch as 0.14.0 and tested only against the plugin's own test suite (908 tests), a stub page in a real browser for the editor parts, and offline screenshots. Nothing here has been tried on a real site. Work through it on the **staging site** first. Riskiest first: if something in the first block goes wrong, stop, switch safe mode on (`wp twd-sk safe-mode on`) and send the site report (`wp twd-sk doctor`).

## Update

1. Get `dist/twd-site-kit-latest.zip` from the `studio-0.14` branch (version 0.14.1). Check its fingerprint matches the file beside it: `sha256sum twd-site-kit-latest.zip` must equal the `sha256` in `dist/twd-site-kit-update.json`.
2. WordPress, Plugins, Add New, Upload Plugin, choose the zip, "Replace current with uploaded". Then `wp plugin get twd-site-kit --field=version` should print `0.14.1`.
3. Clear any page cache.

## 1. Riskiest: Elementor

You need two logins for this: an administrator, and a test login with the Editor role (make one, then delete it afterwards; never keep or share it).

1. As the **editor** login, open `.../wp-admin/post.php?post=PAGE_ID&action=elementor` with the ID of a kit page (the Home page). You should see a plain card: "This page is edited on the page itself", with an Open the page button. No way on.
2. As the **administrator**, open the same address. The same card, plus "Administrators only: open Elementor anyway". Press it: Elementor opens. Do not change anything; leave with Elementor's exit button. The page must look exactly as before.
3. As the administrator, open an ordinary page that is **not** a kit page (the Sample Page, if it is still there) the same way: Elementor opens with no card.
4. As the editor, open Pages in wp-admin. Hover a kit page: there should be no "Edit with Elementor" link. As the administrator it may show if the page was ever built with Elementor.
5. If the WordPress classic editor is in use: open a kit page's edit screen as the editor. No big "Edit with Elementor" button. (With the block editor there is a different button we do not hide; the card in step 1 still stops it.)

## 2. The editing tools switch

6. Signed in, on any page, look at the top left of the WordPress bar: after the W logo, "Editing tools: on". Press it: the floating buttons, Tell us, the Edit header and footer pills and the small version label all disappear, and it says "Editing tools: off". Reload: still off. Press again: they come back.
7. Open the site in a different browser, signed in as the same person: it is off or on to match, because it is kept for the person.
8. On a phone: the WordPress bar hides the switch; a small round switch "Editing tools" sits at the bottom left. Press it: the buttons go and the switch drops to the corner. Press again to bring them back.
9. Turn it off, then open the Visual editor from the Edit with AI pop-up (or turn tools on, open the Visual editor, then turn tools off): the editing card stays; only the launcher buttons hide.

## 3. Add and move sections

Use a page that is still a draft first.

10. Open the Visual editor. Click inside a section in the middle of the page, then press **Move down**. The page reloads straight back into the Visual editor with that section lower and in view, and the cursor in it. Edit with AI, History and undo should list "Moved a ... section down" as its own version. Press Move up to put it back.
11. Click inside the first section (the one with the page's main heading) and press Move down. You get a plain message that the main heading stays at the top. Click inside the second section and press Move up: the same message.
12. Click inside a section, press **Add section**. A panel says where it will go. Choose a kind (for example Text) and a layout, press Add it. The page reloads in the Visual editor at the new section, whose words are `[PLACEHOLDER: ...]`. Click in a marker: the whole marker is selected; type your words; press Save changes.
13. Change a word in the page, then press Add section or Move down without saving. You get a message to save or cancel your change first, and nothing is sent. Save, then try again.
14. On a **published** page, press Add section, choose something, Add it: a card says the page is live and visitors would see the placeholder words. "Not yet" does nothing; "Add it anyway" adds it. (Remove it with Remove and Save, or use History and undo.)
15. Try the same on a phone: the buttons wrap onto lines and stay easy to press.

## 4. Header, width and footer

16. Front page, desktop, with the fixed header and shrink on: scroll a little. The header becomes the small mark **with the site name to its right**, then the menu, the same as other pages. Scroll back up: the full logo returns, with no name beside it.
17. The Ink style's page width is now 1140. Open a page: the header content, the page content and the footer content should share the same left and right edges.
18. Footer: Site tab, Header and footer, Footer layout, choose **Compact: tagline on top, logo left, links right, and a dark bar**, Save. Open a page. You should see: the tagline centred, the logo on the left and the menu links on the right in rows of three, and one dark bar with the copyright, the registration line, the email and the privacy link. It should be much shorter than the columns footer. On a phone it stacks: tagline, logo, links, then the bar.
19. Change the footer back and forth between layouts: the other four look exactly as they did.

## 5. What this version does not do

- No place to put a shortcode or raw HTML in a section yet, and the "No articles yet" line is still the Articles plugin's own wording. Both are the next release (0.15.0).
- The narrow reading column (the About page's text) is unchanged at 680px. The owner is to choose a width from renders.
- The header and footer on a site that looks different from what the plugin draws (capital letters in the menu, no header button, no "Explore" heading) need that site's report first: `wp twd-sk doctor`.

## Rolling back

The updater never installs an older version, so go back by installing a higher version that carries the old code, or switch safe mode on (`wp twd-sk safe-mode on`), which keeps pages showing without any of the 0.14.1 additions. 0.14.0 is the commit before this one on the branch.
