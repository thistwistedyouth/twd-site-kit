# The site brief

One JSON file that describes a site. You make it once, paste it into the Site tab, check what it would do, and apply it. You can export the current site as a brief at any time and add to it later.

The format is `twd-site-brief/1`. See `site-brief.example.json` for a complete example with made-up values.

## How to make one

1. In the Site tab, open **Site brief** and click **Copy the interview prompt**.
2. Paste it into a normal Claude conversation. It interviews the therapist (or you, answering for them) a few questions at a time and ends by producing the JSON. It is told never to invent anything.
3. Paste the JSON into the box in **Site brief** and click **Check it**. Nothing changes yet.
4. Read what it would do. Tick **Overwrite** only if you want filled-in details replaced. Tick **Create the pages as drafts** to make an outline page for each entry in `pages`.
5. Click **Apply**. Pages are drafts, never published. Use **Fill with AI** beside each page (needs an AI key) to write it from the practice facts.

## Building a whole site from a brief (0.7.1)

On a fresh site, an administrator sees a **Set this site up** link on the front page. It opens **Settings > Set up site**: choose the site type and style, paste the brief (or plain notes, which the AI turns into a brief for you to check), choose pictures, then press **Build the site**. Each page is made one at a time as a draft, written by AI when a key is available, otherwise as an outline. The same build runs from the command line with `wp twd-sk build brief.json`.

Pictures are chosen from the media library. The logo, the Home page's main picture and the About photo go to fixed places. Named pictures (a short name such as `front-view`, a description used as alt text, and the page they may go on) are offered to the AI, which places them where they fit.

## Rules

- Nothing in a brief is trusted. Each value goes through the same checks the Site tab saves with. A bad value is dropped with a plain sentence, an unknown key is ignored and reported.
- Details that are already filled in are kept unless you tick Overwrite. Header and footer layout settings are settings, not content, and are always applied.
- No HTML, no credentials, no keys, no pictures. Pictures live in the media library and are added by hand.
- A page that already exists (same title) is not created again.
- Style changes that would make text hard to read are refused.
- The brief holds facts about the practice only, never about the people a practitioner treats.

## Fields

| Section | Field | Notes |
|---|---|---|
| `mode` | `therapy` or `business` | Optional. Sets the site type before anything else, so labels and AI wording follow it |
| `site` | `name`, `person_name`, `person_job`, `phone`, `email`, `address` (list), `area_served`, `registration` (list), `same_as` (list of https links) | Registration lines only exactly as the person supplied them |
| `header` | `menu` (list of `label`, `url`, `children`), `cta_label`, `cta_url`, `layout`, `sticky`, `show_button`, `show_strip` | Up to 8 menu items, one level of children |
| `footer` | `text`, `legal` (list of `label`, `url`), `layout`, `columns` (1 to 3) | |
| `style` | `pack`, `tokens` | Leave `tokens` empty unless colours were asked for |
| `facts` | text with capital-letter headings | The practice facts, up to 20000 characters |
| `pages` | list of `type`, `title`, `topic`, `notes`, `seo` (`title`, `description`) | Types: about, contact, home, service, faq. A service page needs a `topic` |
