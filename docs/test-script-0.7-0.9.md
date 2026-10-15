# Test script: 0.7.0 to 0.12.0 on a fresh site

Built fast at beta stage; none of this has been tried on a real site yet. Work through it on a **fresh install** first, never on a live client site. Riskiest first: if something in the first block goes wrong, stop and send the site report (Site tab, Site report, or `wp twd-sk doctor`).

Before you start: install Site Kit 0.12.0 (from the branch zip for now; Edit text is now called Visual editor, and Tell us is new) (Plugins, Add New, Upload, or the one-click update), plus the Articles plugin with a key if you want the AI parts. Turn on WP_DEBUG_LOG if you can.

## 1. Riskiest: things that send, publish or change many pages

1. **Enquiry form email.** Add the Enquiry form section to a page (Edit with AI, Add a section), publish it, open it in a private window and send a message. Does it arrive (check spam)? Does the reply go to the sender? Send six quickly from the same browser: the sixth should say "Too many messages". If nothing arrives at all, 20i may block PHP mail: install an SMTP plugin and try again.
2. **Build the site.** On the front page, as an admin, click **Set this site up**. Go through every step with a short brief or notes, a logo and two pictures, then **Build the site**. Every step should say Done. Open each page: it should be a draft, with your pictures where expected.
2b. **Choose the brief file (0.9.3).** On the Brief step, Choose the brief file and pick the .json file: it should fill the box and say Checked. Delete the last few lines in the box and Check: it should say the brief ends too early, and the Build step should have Build switched off.
2a. **A long brief (0.9.2).** Paste a full site brief and press "Turn my notes into a brief (AI)" by mistake: it should say the AI was not needed and check the brief instead. On Build, a Home page with more than six sections should still say Done (it is written in parts).
3. **Build again.** Press Build the site a second time. No new pages should appear; the same pages get a new version (or no change).
4. **Publish from the launch check.** Step 7 (Launch). Pages with things to replace cannot be ticked. Publish one ready page and check it shows to a visitor.
5. **Header and footer (Hello theme, no Elementor Pro).** After the build, open the front page as a visitor. Hello's own plain header and footer should be gone, and the kit header (logo, menu, button) and footer should show once each, at the top and bottom. Press Tab once: a "Skip to content" box should appear top left; Enter jumps past the menu. Check a page set to Elementor Full Width (header shows) and one set to Elementor Canvas (no header, by design). Site tab, Header and footer should say "Placed now by: Site Kit, on the Hello theme".
6. **Safe mode.** `wp twd-sk safe-mode on` (or the setting): the setup page disappears, the Enquiry form shows a plain sentence, pages still show, and Hello's own plain header and footer come back. Turn it off again.

## 2. Middle: wording, layout and pictures

7. **Business mode.** Site tab, Site type, Business, Save, reload. The Site tab should say Business facts, Owner or contact name, Area covered, and no job title box. Ask the AI to change a section: the wording should not mention therapists or clients.
8. **Pages step.** Add a page, change a section's layout, move sections, remove one. Try to put a text section above the hero: it should refuse and keep what you typed.
9. **New sections.** Add each of Pictures, Logos strip, Prices, Opening hours, Areas covered and Enquiry form. Check them on a phone. Sample wording ("Price here") should stop the page being published until replaced.
10. **Keep in view.** In Media, open a portrait, set Keep in view to Top, save. On a page that crops it (a hero), the face should stay in view.
11. **Colours from the logo.** Site tab, Style, Take colours from the logo. The page previews the colours; Undo puts them back; Save keeps them. Text on buttons and bands should stay easy to read.

## 2b. Editing (0.10.0 to 0.10.2)

12a. **Visual editor (was Edit text).** On a kit page, press **Visual editor** (bottom left, above Edit with AI). Dashed outlines show what can change. Change a heading, make a word bold, change a button's link (Link), and click a picture to swap it and give it a description. Save: the page reloads with the changes, and Edit with AI, History shows "Changed on the page". Try Cancel too: it asks, then puts the words back. The safety notice and the enquiry form should not be editable.
12b. **One box.** Edit with AI, Edit this page. Type a list, for example: "1. Change the first paragraph to ... 2. Add three boxes with glowing borders for garden parties, weddings and festivals. 3. Add these testimonials as a slider at the bottom: ...(paste two real ones)". Plan the changes: a checklist appears, plus anything it cannot do. Untick one, then Make these changes: each says Done, the preview opens, Apply saves one version. Check the testimonials are word for word.
12c. **The new looks.** The glow boxes' borders move slowly between two of the site's colours (and stay still if the phone or computer is set to reduce motion). The slider shows one quote at a time: swipe on a phone, arrows and dots on a computer, and nothing moves on its own.

12d. **Pictures in Edit text (0.10.1).** Find a page with a picture marker such as [PLACEHOLDER: image needed] (or ask Edit with AI to add a picture somewhere). Press Visual editor: the marker shows as a dashed **+ Add a picture** box. Click it, then **Upload from this computer** and pick a photo: it uploads, shows in place, and asks for a description. Click another picture, **Choose from the media library**, and pick one. Click a third picture, **Change the description** only. Save: all three stay after the reload, the uploaded photo is in Media, and a caption marker next to a picture is still words to type over. Try a PDF in Upload: it should be refused.
12e. **The Edit text card and admin bar (0.10.2).** Press Visual editor: the tools appear as a small card at the bottom left, not a full-width bar, and the New Article button (bottom right) stays clear. In the black admin bar at the top, Visual editor has become **Save changes** next to **Cancel**. Change a word: it reads Save changes (1); press it to save. On a phone the card spans the screen above other buttons.

## 2e. Shrink on scroll (0.13.0)
1. Site tab, Header and footer: tick "Keep the header fixed" and "Make the fixed header smaller when scrolling", Save, reload a long page.
2. Scroll down a little (about 60px): the header should get smaller (logo and spacing) and the page must not jump. Scroll back to the very top: it returns to full size. Slowly wobble around 20 to 60px: it must not flicker.
3. On a phone width: open the menu button before and after shrinking; the menu panel should still sit directly under the header. Tab to the skip link and the header button in both sizes.
4. Untick Shrink, Save, reload: the header stays one size. Safe mode on: no change in size.

## 2c. Tell us (0.11.0)

13a. **Switch it on.** Site tab, Tell us notes: tick Show the Tell us button, leave Keep notes for at 12 months, Save. Reload. A **Tell us** button should sit directly above **Visual editor** (bottom left). On a page with no Visual editor button it takes that button's place. Switch safe mode on: the button disappears. Switch it off again.
13a2. **Version label.** Signed in as an Editor, look at the very bottom left corner of any page: a small "v0.11.0" label sits below the buttons and never covers one, on a computer and on a phone. Signed out (private window) it must not appear.
13b. **Send a note as a client.** Sign in as someone who can edit pages but is not an administrator (an Editor). Press Tell us. It says "What would you like changed?", shows the page name, a text box with a count, and a short privacy line saying how many months it is kept. Write "Please change the heading on this page", press Send. It says thank you. The note appears under Your earlier notes.
13c. **Web addresses.** Send a note that includes a made-up address such as www.example.org. It is saved as "(web address removed)".
13d. **Delete.** Press Delete on your note: it says "Sure? Delete" first. Press again: it goes. Send three more and check the eleventh in an hour is refused politely.
13e. **The administrator panel.** Sign in as an administrator, press Tell us. You see every note with who sent it, a status box on each, Add an idea, and **Copy new notes as markdown**. Change a status to Done and the count on the button goes down. Press the copy button and paste into a text file: the notes are there, with no web addresses. Ideas you add must NOT appear for the Editor (check in a private window signed in as the Editor).
13f. **Email.** In the Site tab turn on Email me, give an address, Save, send a note as the Editor. An email arrives saying a note has arrived and naming the page. It must NOT contain what the note says. Check spam too.
13g. **Over SSH.** `wp twd-sk snags --status=new --format=markdown`, then `wp twd-sk snags` (JSON), then `wp twd-sk snag-status <id> done`.
13h. **Privacy.** Tools, Export Personal Data for the Editor's email: the notes are in the export. Erase Personal Data: they are gone.

## 2d. Full site set up (test) (0.12.0)

Needs the AI key from the Articles plugin. Use a fresh site and made-up details (a pretend practice) so nothing real is involved.

14a. **Switch it on.** Settings, Set up site. On the first step, tick **Full site set up (test)** and press Save and continue. The steps become Site type, Full set up, Pictures, Build, Launch.
14b. **Paste and check.** Paste a few paragraphs about a pretend practice: its name, who you are, two things you offer, a short story. Press **Check what is missing**. You should get a list of what it understood, a list of questions with a box under each, contact details (the email filled in as info@ plus your site address and said to be assumed, the phone empty), and three design questions (look, tone, photos). The Build must not be possible yet.
14c. **Nothing invented.** Read the questions: each should ask for something your notes really lack, in plain words. Check that it did not claim a name, email, phone or registration you never gave.
14d. **Answer and check again.** Answer everything, choose a look, a tone and Photos, then **Check again**. Repeat until the box says Ready. Then change one word in the notes: the Looks right, continue button should switch off and ask you to Check again.
14e. **Continue.** Press **Looks right, continue**. You land on Pictures. Build must still say it is not ready if you go to Build before this.
14f. **Build with photos.** Choose "I will add my own photos", build. Every step should say Done. Open the pages: Home, About, one per thing you offer, Questions and answers, Resources (only if the Articles plugin is on), Contact. Read every page. Nothing should be marked [PLACEHOLDER] except photo spots, and each spot should say what photo is wanted and a Pexels search. Nothing should be invented: no phone number you did not give, no prices, no qualifications.
14g. **The checks.** The build report lists anything on a page that was not in your notes (an email, phone or price). If you add a made up phone number to the notes then remove it again and rebuild, the flag should appear and go. The last report line says what was left out (for example testimonials) and what to do about photos.
14h. **Photo spots.** Open a page, press **Visual editor**, click a photo spot: choose or upload a photo. The marker goes and the photo shows. Try publishing a page that still has a spot: it should be refused.
14i. **No photos.** Start again, choose "No photos for now", build. No page should have a photo spot, and the layouts should still look right on a computer and a phone (a plain top band, text sections instead of image and text, a contact section without a picture). The report suggests a few Pexels searches.
14j. **Safe mode and the ordinary set up.** With safe mode on the setup page is unavailable. With the tick box off, the old Style, Brief and Pages steps and the old build behave as before.

## 3. Lowest: reports and command line (needs SSH)

12. `wp twd-sk build brief.json --dry-run` shows the plan and changes nothing.
13. `wp twd-sk launch-check` lists the same as the Launch step.
14. `wp twd-sk brand` prints the logo colours.
15. `wp twd-sk mode business` then `wp twd-sk mode`.
16. The site report has Site setup (with the build log) and Launch check sections, and never shows an email address or a message.

## If something breaks

- Send the site report text (it holds no keys or personal details).
- Safe mode switches off everything new while you wait for a fix.
- The previous version can be reinstalled from the zip in the repository's history if needed.
