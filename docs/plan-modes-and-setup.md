# Plan: therapy and business mode, the setup page, and what comes after

Status: agreed with the owner on 12 Oct 2026. Build in the order below. Nothing here holds client data.

---

## 1. What was agreed

| Topic | Decision |
|---|---|
| Modes | One admin setting: **therapy** or **business**. Existing sites stay in therapy mode and nothing on them changes. |
| AI key | Comes from the Articles plugin, as now. The owner gives a business its own key on subscription, or it uses its own. Site Kit still holds no key. |
| Start point | On a fresh install, a **Set this site up** button on the front page, visible only to an admin, until the site is set up. |
| Setup page | A proper admin page (Settings > Set up site), not a front-end pop-up, so the media uploader works and nothing is lost by clicking away. Also reachable from the Site tab. |
| Steps | Mode, then style, then brief, then pictures, then build. Any step can be revisited before Build. |
| Brief | A site brief JSON (works with no AI) or plain text (needs the key, and the brief made from it is shown before anything is saved). |
| Pictures | Uploaded to the media library. Either into a **slot** (logo, home main picture, about photo) or as a **custom picture** with a short name, a description and where to use it. |
| Pages | Built as **drafts**. Pages that already exist with the same title are skipped and listed, never overwritten. |
| Mode switch later | Changes wording, schema and which fields show. Never rewrites page content. A one-line warning says so. |

---

## 2. What AI website builders offer now (quick look, Oct 2026)

| Builder | What it does well | What it lacks for us |
|---|---|---|
| **Elementor AI Site Planner** | Chat that produces a brief, then a sitemap, then wireframes with starter copy, all editable before building. | Hands off to drag-and-drop editing; the client still needs Elementor skills. |
| **Wix (Harmony, AI Site Planner)** | Full site from a description; conversational edits afterwards. | Locked to Wix; reviewers report frustration for general users. |
| **10Web** | Real WordPress sites built in Elementor from a short brief; an AI co-pilot rewrites copy, makes images and adds sections. | Its own hosting; sites can still be generic. |
| **Framer** | Polished design from a prompt. | Output needs a lot of manual refinement; bland colours. |
| **Durable** | Fast for small businesses: site, simple CRM, invoices, blog. | Little design depth. |

**What we take from them:**
1. **Brief, then plan, then build.** The plan step (a sitemap and an outline of sections per page you can change before anything is built) is what makes Elementor's planner good. We add it in 0.7.2.
2. **Chat edits after building.** We already have this: Ask the AI on any page or section.
3. **Add a section by asking.** Our parked "Add a section" item becomes part of the roadmap.

**Where Site Kit is already ahead, and must stay ahead:**
- **Nothing invented.** Every page is written from the facts the owner gave. Unknowns stay as marked gaps, never made up. No AI builder above promises this, and therapists need it.
- **Real WordPress, no lock-in.** Plain pages on any host. Turning the plugin off never breaks a site.
- **Safe for clients to edit.** Sanitised HTML, one main heading per page, versions with undo, locked sections, contrast checks on style.
- **Drafts first.** Nothing goes live without a person choosing to publish.

---

## 3. Release 0.7.0: therapy and business mode

**Setting.** Option `twd_sk_mode`, value `therapy` (the default when missing) or `business`. Set by an admin in the Site tab, in step 1 of the setup page, or with `wp twd-sk mode business`. A new class `TWD_SK_Mode` is the only place that reads it.

**One word list per mode.** `TWD_SK_Mode::words()` returns every mode-dependent label and phrase, so no other file hard-codes "therapist" or "practice". Adding a third mode later means adding one list plus its tests.

| Area | Therapy | Business |
|---|---|---|
| Facts box | Practice facts | Business facts |
| Fact headings | Who I am, who I help, what I offer, how to contact me | Who we are, what we offer, area covered, how to book, prices |
| AI prompts | Therapist, clients, gentle and careful | Business owner, customers, tone taken from the facts |
| Privacy rule in prompts | Nothing about people the practitioner treats | Nothing personal about customers |
| Site details fields | Job title and registration lines shown | Both hidden; stored values kept |
| Recipes | Home, About, Contact, Service, FAQ | Home, About, Contact, Product or service, FAQ |
| Search schema | ProfessionalService and Person | LocalBusiness |
| Interview prompt | Therapy questions | Business questions |
| Doctor report | Shows the mode | Shows the mode |

**Safe mode.** Safe mode ignores the setting and behaves as therapy mode, so a fault in the new code can always be switched off.

**Tests.** Every word list has the same keys; no file outside `TWD_SK_Mode` contains mode words; default is therapy; schema type follows the mode; hidden fields keep their stored values; the brief accepts a `mode` key.

---

## 4. Release 0.7.1: the setup page

### The button
- Shows on the front end only to a user who can `manage_options`, only while the site is **not set up** (option `twd_sk_setup_done` not set and no kit pages exist).
- Small, fixed, bottom corner: "Set this site up". Links to the setup page. No visitor ever sees it.

### The setup page (Settings > Set up site)
One page, five steps, with a progress line at the top. Progress is saved after each step in option `twd_sk_setup_draft`, so leaving and coming back resumes where you were.

1. **Mode.** Therapy or business, with one sentence on what each changes.
2. **Style.** Choose a style pack with a small preview of each.
3. **Brief.** One box. Paste a site brief JSON, or plain text.
   - JSON: checked with the existing brief checks; you see the plan of what it will do.
   - Plain text (needs the key): the AI turns it into a brief using the same rules as the interview prompt (never invent; gaps listed). You see and can edit the brief before carrying on.
4. **Pictures.**
   - **Slots:** Logo, Home main picture, About photo. Each has a "Choose or upload" button using the normal WordPress media uploader.
   - **Custom pictures:** "Add a picture" any number of times (up to 20). Each has:
     - **Name**, short and unique (for example `van-side`), letters, numbers and dashes only.
     - **Description**, which becomes the alt text unless you write one.
     - **Use on**: any page, or one page from the brief.
   - The AI is given only this list and may only place pictures from it. A name it uses that is not on the list is removed and reported.
5. **Build.** One button. A tick list fills in as each step finishes:
   1. Save mode, style, site details, menu, facts.
   2. Create each page in the brief as a draft (skipping existing titles).
   3. Write each page with AI one at a time (or leave the outline if there is no key). One page failing never stops the others; it shows "try again" beside it.
   4. Put the slot pictures in place and swap any `pic:name` references for the real picture.
   5. Set Home as the front page.
   6. Run the page check on every new page and show must-fix counts.
   7. Mark the site as set up, which hides the front-page button.

   At the end: a link to each page, its check result, and "Open in the editor".

### Safety
- Admin only, every route through the existing `guard()` (nonce, capability, size limit, rate limit). Building uses the `ai` rate bucket for AI calls.
- Pictures are chosen through WordPress itself; Site Kit never handles an upload. Only attachment IDs that are real images are accepted.
- Picture names and descriptions are plain text with limits; every page still goes through the sanitiser.
- Nothing published. Nothing overwritten.
- The setup page is a module that safe mode switches off.

### Command line
- `wp twd-sk build <brief-file> [--mode=business] [--pack=sage] [--no-ai]`: the same build as step 5, for when you have SSH.

---

## 5. After 0.7.1: the road to incredible, flexible sites

Each line is a release. Order can change; each still needs the owner's go.

| Release | What | Why |
|---|---|---|
| **0.7.2 Plan step** | Between brief and build: a sitemap you can reorder, add to and remove from, and per page an outline of sections (hero, text and picture, steps, FAQ, call to action) you can change. Build follows the plan exactly. | The single best idea from the market. Fixes the "AI picked a layout I did not want" problem before it happens. |
| **0.7.3 Add a section by asking** | In the edit pop-up: "Add a section here" with a choice of section type and an optional instruction. | Already parked; the plan step needs the same parts. |
| **0.8.0 More section types** | Gallery, pricing table, opening hours, areas covered, simple enquiry form (sending to the site owner's email), map link, team, logos strip. Each in the registry with variants, tests and contrast checks. | Business sites need these; therapy sites benefit too. |
| **0.8.1 Brand from logo** | Read the logo's main colours and propose style tokens, checked for contrast before saving. | Fast, on-brand first look. |
| **0.8.2 Picture help** | Focal point per picture so crops keep faces; with a key, suggested alt text from the picture, shown for approval. | Better pictures on phones; accessibility. |
| **0.8.3 Starter sets per business type** | Data files, not code: for example "mobile hire", "trades", "coach". Each gives suggested pages, sections and fact headings. | Flexibility without code changes per client. |
| **0.9.0 Launch check** | One screen: every page checked, privacy policy present, contact details consistent, search settings, schema valid, pictures with alt text, nothing left as a placeholder. Then "Publish these pages". | Safe, confident go-live. |
| **Later** | Beta update channel; base site and provisioning; Site brief v2 and interview prompt v2 (see BUILD-LOG). | Already planned. |

**How it stays flexible as it grows:**
- **Data, not code, for anything per business type.** Modes, word lists, starter sets and section variants are lists the code reads.
- **One build engine.** The setup page, the command line and later the plan step all call the same `TWD_SK_Build` class, so a fix lands everywhere.
- **Everything checkable.** Every new feature adds tests, works in the privacy check, and appears in the doctor report.
- **The AI only ever proposes.** It writes inside the kit's sections and a person accepts. The sanitiser and checks have the last word.

---

## 6. How each release is done

Same drill as every release: new behaviour in a module safe mode can switch off; tests; privacy check with the private word list; PHP lint and PHP 7.4 check; zip built and verified; a test script for the owner ordered by risk; update through the one-click updater; BUILD-LOG.md and CLAUDE.md updated in the same commit.

**Test on a fresh install first.** Every release is checked on a fresh site (setup button, full build, editing as an Editor) before it goes to a live client site.

---

## 7. Sources for section 2

The market look was a quick web search on 12 Oct 2026 (Elementor's own Site Planner pages, a TechRadar piece on 10Web, and 2026 review roundups). Links are kept out of this public repo by the privacy check; they are in the conversation that wrote this plan. Details change often; check a vendor's own site before relying on a feature or price.
