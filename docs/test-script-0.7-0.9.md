# Test script: 0.7.0 to 0.9.0 on a fresh site

Built fast at beta stage; none of this has been tried on a real site yet. Work through it on a **fresh install** first, never on a live client site. Riskiest first: if something in the first block goes wrong, stop and send the site report (Site tab, Site report, or `wp twd-sk doctor`).

Before you start: install Site Kit 0.9.0 (Plugins, Add New, Upload, or the one-click update), plus the Articles plugin with a key if you want the AI parts. Turn on WP_DEBUG_LOG if you can.

## 1. Riskiest: things that send, publish or change many pages

1. **Enquiry form email.** Add the Enquiry form section to a page (Edit with AI, Add a section), publish it, open it in a private window and send a message. Does it arrive (check spam)? Does the reply go to the sender? Send six quickly from the same browser: the sixth should say "Too many messages". If nothing arrives at all, 20i may block PHP mail: install an SMTP plugin and try again.
2. **Build the site.** On the front page, as an admin, click **Set this site up**. Go through every step with a short brief or notes, a logo and two pictures, then **Build the site**. Every step should say Done. Open each page: it should be a draft, with your pictures where expected.
3. **Build again.** Press Build the site a second time. No new pages should appear; the same pages get a new version (or no change).
4. **Publish from the launch check.** Step 7 (Launch). Pages with things to replace cannot be ticked. Publish one ready page and check it shows to a visitor.
5. **Safe mode.** `wp twd-sk safe-mode on` (or the setting): the setup page disappears, the Enquiry form shows a plain sentence, pages still show. Turn it off again.

## 2. Middle: wording, layout and pictures

6. **Business mode.** Site tab, Site type, Business, Save, reload. The Site tab should say Business facts, Owner or contact name, Area covered, and no job title box. Ask the AI to change a section: the wording should not mention therapists or clients.
7. **Pages step.** Add a page, change a section's layout, move sections, remove one. Try to put a text section above the hero: it should refuse and keep what you typed.
8. **New sections.** Add each of Pictures, Logos strip, Prices, Opening hours, Areas covered and Enquiry form. Check them on a phone. Sample wording ("Price here") should stop the page being published until replaced.
9. **Keep in view.** In Media, open a portrait, set Keep in view to Top, save. On a page that crops it (a hero), the face should stay in view.
10. **Colours from the logo.** Site tab, Style, Take colours from the logo. The page previews the colours; Undo puts them back; Save keeps them. Text on buttons and bands should stay easy to read.

## 3. Lowest: reports and command line (needs SSH)

11. `wp twd-sk build brief.json --dry-run` shows the plan and changes nothing.
12. `wp twd-sk launch-check` lists the same as the Launch step.
13. `wp twd-sk brand` prints the logo colours.
14. `wp twd-sk mode business` then `wp twd-sk mode`.
15. The site report has Site setup (with the build log) and Launch check sections, and never shows an email address or a message.

## If something breaks

- Send the site report text (it holds no keys or personal details).
- Safe mode switches off everything new while you wait for a fix.
- The previous version can be reinstalled from the zip in the repository's history if needed.
