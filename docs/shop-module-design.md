# Shop module design (for discussion, no code yet)

Status: DRAFT for the owner, 9 Nov 2026. Nothing is built. Building waits for the owner's answers to the questions at the end and his go.

This repo is public. Everything here is generic: the examples are made-up antique jewellery pieces, never a real shop, product or wording.

## The design in one paragraph

An optional **Shop** module inside Site Kit, for a WooCommerce shop on the Hello theme. It only wakes up when WooCommerce is active AND an administrator has ticked "Shop tools" for that site, so a therapy site carries nothing extra. A signed-in shop manager gets an **Edit product** button on a product page. It opens a pop-up with five tabs: **Basics** (title, price, category, for sale or sold), **Details** (rows of label and value, for example "Period: Victorian, about 1870"), **Story and condition** (long text sections), **Search** (search title, description, sharing picture) and **AI help** (copy a prompt into Claude, paste the reply back, check, preview, Save, Undo). Everything is saved as normal WooCommerce data, so a product still shows properly if the module is switched off. With no SEO plugin, Site Kit prints the search and sharing tags for products, product categories and the shop page; with Yoast active it writes into Yoast's fields and prints nothing. That is what lets the shop switch Yoast off later. A tidier product page layout (gallery, title, price, small labels, summary, Add to basket, a details table, then sections you click to open) is a second tick, built on WooCommerce's own hooks, with no Elementor. Photos stay in the normal WooCommerce screen for version one.

## 1. Isolation: nothing for sites that do not use it

**The switch.** One option, `twd_sk_shop` (autoloaded, `'1'` or absent), set by an administrator:
- in the Site tab, a fold-out **Shop** that only appears when WooCommerce is active (tick "Shop tools for WooCommerce", and a second tick "Tidy product page layout");
- or `wp twd-sk shop on|off|status` (and `layout on|off`).

A second option `twd_sk_shop_layout` controls the layout part on its own, so the pop-up and search tags can run while the page look stays as it is (useful if an Elementor Pro product template is still in charge).

**The tiny check.** The main plugin file gains one `require_once` for `includes/class-twd-sk-shop-gate.php` (about 40 lines, one class `TWD_SK_Shop_Gate`). Its `on()` is true only when all of these hold:
- safe mode is off (`TWD_SK_Safe::on()` is false);
- the option is `'1'`;
- WooCommerce is loaded (`class_exists( 'WooCommerce' )`) and is version 8.0 or newer.

WooCommerce loads after Site Kit (plugins load in name order), so the check runs on `plugins_loaded` at priority 20, never when the main file is read. Only when `on()` is true does the gate `require` the module's own loader. Any fault while loading is caught and the module stays off (fails closed, a line in the site report).

**Its own folder.** Everything lives in `modules/shop/`:
- `modules/shop/load.php` (requires the classes below, calls each `init()`);
- `modules/shop/includes/class-twd-sk-shop.php` (hooks, who may edit), `-shop-product.php` (read and write a product through WooCommerce's own product objects), `-shop-history.php` (versions and Undo), `-shop-text.php` (the product text cleaner), `-shop-seo.php` (product and category search tags), `-shop-layout.php` (the product page), `-shop-ai.php` (the prompt and reading the reply), `-shop-rest.php` (routes), `-shop-cli.php` (only under WP-CLI);
- `modules/shop/assets/twd-site-kit-shop.css` (visitors, product pages only, generated from `tools/shop.src.css`), `twd-site-kit-shop-editor.js` and `-editor.css` (signed-in shop managers only).

The existing test that every `includes/class-twd-sk-*.php` is loaded by the main file stays true (the gate is the only new file there). A new test checks every file in `modules/shop/includes/` is in `load.php`, and that nothing outside `modules/shop/` refers to a shop class except the gate, the Site tab fold-out and `wp twd-sk shop`.

**What a site with the switch off gets.** No shop PHP classes beyond the gate, no shop CSS, no shop JS, no shop routes (they are registered by the module, so they do not exist), no change to the head of any page. A test draws a page with the module off and compares the printed bytes with today's.

**The sanitiser is untouched.** `TWD_SK_Sanitizer` and the registry's class list do not change. Product text has no classes at all, so it does not need the allowlist. `TWD_SK_Shop_Text::clean()` calls the existing sanitiser and then narrows the result further (see section 3). A test checks `TWD_SK_Registry::allowed_classes()` is identical with the module on and off, and that no `twd-sk-shop` class ever reaches the registry.

**Weight for visitors with the switch on.** Product pages, the shop page and product category pages load one small stylesheet (aim: under 6 KB raw), only when the layout tick is on. No visitor script: the sections that open and close are plain `details` and `summary`. WooCommerce's own gallery scripts are left as they are.

## 2. The Edit product pop-up

**Who sees it.** A signed-in user who can edit that product (`edit_product` on its id, which WooCommerce gives administrators and shop managers), on the front end, outside the preview frame, the customizer and Elementor's editor. A floating **Edit product** button on a single product page, and an admin bar item. On the shop page and category pages, **Add a product** (makes a draft in that category and opens the pop-up on it).

**Scripts.** Recommended: the shop script depends on the existing editor script and stylesheet, which already load for every signed-in editor on every front-end page. That gives the same look, focus trap, Escape, copy-with-fallback and fold-outs for free, and a shop manager loads one more small file (aim: under 30 KB). The same browser rules apply and the same tests run on it: no `innerHTML`, no `srcdoc`, no `eval`, no browser storage, no web address in the script, a focus trap, full screen under 700px. The product's data is fetched when the pop-up opens, never printed into the page.

**Product types.** Version one edits **simple products** only (one-off pieces). A variable, grouped or external product opens read-only with "Edit this one in WooCommerce" and a link to its edit screen.

**Tab 1: Basics**
- Title.
- Price (pounds and pence, checked as a number). Optional "Reduced price". Optional "Price on request" tick (see question 2).
- Stock number (WooCommerce's SKU), optional. WooCommerce refuses a duplicate; the message says so in plain words.
- Category: a list of the shop's product categories (main one, plus "also in" ticks). No new categories from the pop-up in version one.
- **For sale / Sold** (two big buttons). For sale = in stock, stock managed, quantity 1, sold one at a time. Sold = out of stock. Because the quantity is 1, WooCommerce marks a piece as sold by itself when someone buys it.
- Status line: Draft or Live. A draft shows **Publish** (a person clicks it; refused while any `TO CONFIRM` or `[PLACEHOLDER` is left in the text, the same rule as kit pages).

**Tab 2: Details** (repeatable rows)
- Each row is a label and a value, for example "Period" and "Victorian, about 1870", "Metal" and "15ct gold", "Stones" and "Garnets and seed pearls", "Size" and "UK ring size N", "Measurements" and "22 mm by 18 mm".
- Add row, remove row, move up, move down. A short list of suggested labels to pick from (question 4), or type your own.
- Each row has a small tick **Show as a label near the title** (up to four), for example Period and Metal.
- Limits: 20 rows, label 40 characters, value 200 characters, plain text only. WooCommerce uses `|` to split values, so a `|` typed in a value becomes `/` with a note.
- Rows already on a product that come from WooCommerce's global attributes are shown read-only ("set in WooCommerce") and are never changed.

**Tab 3: Story and condition**
- **Short summary** (a few lines shown near the price; WooCommerce's short description).
- Sections, each a heading and its text: **About this piece**, **Condition**, **History** by default, plus Add a section (for example "Hallmarks", "Box and packaging"), up to 8. Each text box has Bold, Italic, Bullet list and Link, the same as the Visual editor (no pictures, no headings inside a section).
- **Delivery and returns** is one text for the whole shop (question 3), shown on every product; a link here opens it in the Site tab for an administrator.

**Tab 4: Search** (section 4 has the detail)
- Search title and description with character counts and a search result preview (the same pieces as the page Search tab).
- Sharing picture: the main product photo by default; a different one can be chosen from the media library.
- Keep this product out of search (off by default).
- With Yoast active this tab writes into Yoast's own fields; with another SEO plugin it says where to edit and shows nothing to change.

**Tab 5: AI help** (section 6 has the detail)
- A box: "What you know about this piece" (your notes).
- **Copy the prompt**, then paste Claude's reply, **Check and preview**, **Use these words**, then the normal **Save**.

**Saving, versions and Undo**
- One **Save** button for all tabs. Nothing is saved until it is pressed; closing with unsaved changes asks first (the existing close prompt).
- Every save keeps a snapshot of what the pop-up looks after (title, prices, stock number, categories, for sale or sold, the detail rows and their label ticks, the summary, the sections, the search fields). Up to **10 snapshots per product** in the product's own meta (`_twd_sk_shop_versions`, not autoloaded).
- **Undo** puts the previous snapshot back as a new version (so Undo can be undone). **History** lists the versions with who and when, each with Restore.
- **Someone changed it elsewhere.** The pop-up sends the fingerprint of the product as it loaded. If the product was changed in another window or in the WooCommerce screen since, Save is refused (409) with "This product was changed somewhere else since you opened it. Reload to see the latest." Undo, when the live product no longer matches the last snapshot, first records the live state as a version ("Changed in WooCommerce") so nothing is lost.
- Photos, shipping class, tax, and anything else the pop-up does not show are never touched by Save or Undo.

**Routes** (only exist when the module is on), under `twd-site-kit/v1/shop/`: `GET products/{id}`, `POST products/{id}` (save, with the fingerprint), `POST products/{id}/undo`, `GET products/{id}/versions`, `POST products/{id}/restore`, `POST products/{id}/status` (publish, needs `confirm`), `POST products` (new draft), `GET products/{id}/prompt`, `POST products/{id}/ai-check` (reads a pasted reply, saves nothing). Every route goes through the same guard as today's routes (nonce, signed in, capability, size, rate limit). Recommended small change: give `TWD_SK_REST` one public way into its guard with a capability and no page (`edit_products`), so there is one guard, not two copies; the shop route then checks `edit_post` on the product itself.

## 3. Where the data lives (so products still show with the module off)

| In the pop-up | Stored as | With the module switched off |
|---|---|---|
| Title, price, reduced price, stock number | WooCommerce's own product fields, set through its product objects (`wc_get_product()`, `set_regular_price()`, `save()`), never raw meta | Shown by WooCommerce as normal |
| Category | WooCommerce product categories | As normal |
| For sale / Sold | WooCommerce stock status, stock 1, sold individually | "Out of stock" instead of "Sold" |
| Detail rows | WooCommerce product attributes, local to the product, visible, not for variations | Shown in WooCommerce's "Additional information" table |
| Short summary | WooCommerce short description | As normal |
| Sections | The product description, each section as an `h3` heading followed by its text | Shown in WooCommerce's "Description" tab as headed paragraphs |
| Label ticks | Product meta `_twd_sk_shop_labels` (a list of row labels) | Not shown (harmless) |
| Delivery and returns | One option `twd_sk_shop_delivery` | Not shown |
| Search fields | Yoast's fields when Yoast is active, else `_twd_sk_seo_*` on the product (the same keys kit pages use) | Not printed without Yoast or the module |
| Versions | Product meta `_twd_sk_shop_versions` | Ignored |

**Why sections are headings in the description, not a separate store.** One place for the words: someone who edits the description in the WooCommerce screen is editing the same thing the pop-up edits, and there is nothing to drift. The pop-up reads the description back by its headings: text before the first heading is "About this piece", each `h2` or `h3` starts a section (saved back as `h3`, which sits correctly under WooCommerce's own "Description" heading). A product written before the module existed simply opens with all its text in "About this piece".

**The product text cleaner** (`TWD_SK_Shop_Text`, in the module folder): runs the existing sanitiser (unchanged), then keeps only `p`, `br`, `strong`, `em`, `ul`, `ol`, `li` and `a` (http, https, mailto, tel) inside a section, plain text with bold and italic in the summary, and drops every class and id. Long dashes become commas, as everywhere else. Section limit 6,000 characters each; summary 600.

## 4. Search and sharing tags for products, without Yoast

**What happens today.** `TWD_SK_Seo` prints the title, description, Open Graph and Twitter tags only on `is_singular( 'page' )` and only when no SEO plugin is active. With Yoast it writes Yoast's fields and Yoast prints. WordPress core prints the canonical link on single posts, pages and products. WooCommerce prints its own Product structured data on product pages. Since 1.50.0 the Articles plugin prints the same kind of tags for single blog posts (section 7).

**Who prints what, with the Shop module on:**

| Site has | Product, product category, shop page | Kit pages | Blog posts |
|---|---|---|---|
| Yoast | Yoast prints. The pop-up's Search tab writes Yoast's fields for products (the same `_yoast_wpseo_*` keys pages use). Site Kit prints nothing. | As today | Articles plugin writes Yoast's fields |
| Another SEO plugin | That plugin prints. The Search tab says where to edit. | As today | As today |
| None | **Site Kit's shop module prints, once** | As today | Articles plugin prints |

**How it avoids a second set.**
- The module's printer runs only when `TWD_SK_Seo::detect()` says no SEO plugin, only on `is_product()`, `is_product_category()` and `is_shop()`, and never in safe mode.
- `TWD_SK_Seo` gains a "printed already" flag (like `TWD_SK_Schema` has) that both printers set and check, so a request can never get two sets even if a shop page were also seen as a page.
- The tag building in `TWD_SK_Seo::print_head()` is moved into one function that takes the values and returns the lines, used by both printers. A test checks a kit page prints exactly the same bytes as before the move.
- **The Hello theme prints its own `<meta name="description">` from the excerpt on any single item that has one** (`hello_elementor_add_description_meta_tag`). Products nearly always have a short description, so on a product page that would be a second description tag. When the module prints a description it turns Hello's off for that request (`hello_elementor_description_meta_tag` returns false), as the Articles plugin already does for posts.
- The Search tab's existing "check this page" button gains a count of `og:title`, `meta description`, canonical links and Product structured data blocks on the open page, so a doubled tag shows up as a plain line.

**What a product page prints** (no SEO plugin):
- Title: the search title if typed, else WordPress's own title ("Product name - Site name").
- `meta description`: the search description, else the short summary as plain text cut at a word near 155 characters, else none.
- `og:type` `product`, `og:title`, `og:description`, `og:site_name`, `og:url`; when there is a price, `product:price:amount` and `product:price:currency`; `product:availability` (in stock or out of stock).
- Sharing picture (`og:image` with width and height, and `twitter:image`): the product's own sharing picture if chosen, else **the main product photo**, else the first gallery photo, else the site default sharing picture. Through the existing `TWD_SK_Seo::share_image()` (full size, the large size for a file over about 4 MB).
- `twitter:card` (large picture when there is one), `twitter:title`, `twitter:description`.
- `robots` noindex only if ticked.

**Canonical-safe.** `og:url` uses `wp_get_canonical_url()`, the same address WordPress prints as the canonical, so the two never disagree. The module never prints a canonical on a product (WordPress already does). On product category pages and the shop page, which WordPress gives no canonical, the module prints one: the category's own address, with the page number kept on page 2 onwards and every query string dropped (sorting, filters, `add-to-cart`), and `og:url` the same.

**Product category pages and the shop page.**
- Category: search title, description and sharing picture stored as term meta (`_twd_sk_seo_title`, `_twd_sk_seo_desc`, `_twd_sk_seo_image`). Fallbacks: the category name; the category's own description as plain text cut near 155 characters; the category's own thumbnail (WooCommerce's), else the site default. `og:type` `website`.
- Where they are edited: a small **Edit category** pop-up on the category page with just those three fields (question 8). With Yoast active it says category search wording is edited in Yoast, because Yoast keeps category fields in its own option, not in term meta, and writing into that is not worth the risk while the goal is to switch Yoast off.
- Shop page: uses the Shop page's own search fields (the WooCommerce shop page is a normal page, so the page Search tab already holds them).

**Structured data.** WooCommerce prints its own Product block (name, price, availability, pictures) and its breadcrumb block. The module **never prints Product, Offer or BreadcrumbList**, and a test checks that the module's code has no such type. Site Kit's own structured data stays on the front page only, as today. Possible later, off by default and only through WooCommerce's own `woocommerce_structured_data_product` filter (so still one block): `itemCondition` UsedCondition for antique pieces (question 7). Worth checking on the live site now: Yoast's graph has its own breadcrumb list, so with Yoast on there may already be two breadcrumb blocks on product pages; switching Yoast off would end that.

**Things WordPress and WooCommerce already do without Yoast.** The sitemap (WordPress's own, which switches on when Yoast is off, and lists products and product categories), the canonical on single items, robots, and WooCommerce keeps the basket, checkout and account pages out of search itself.

## 5. A tidier product page (the second tick)

Built on WooCommerce's own hooks for the classic product page, not by copying WooCommerce's template files, so a WooCommerce update cannot break it and payment buttons and other plugins that hook into the page keep working. Works on the Hello theme with no Elementor.

**Order on a wide screen** (two columns, the gallery left, the rest right):
1. Gallery: WooCommerce's own (zoom, lightbox, thumbnails as the theme allows).
2. Title (the page's one `h1`, WooCommerce's).
3. Small labels: the ticked detail rows' values, for example "Victorian" and "15ct gold", plus "Sold" or "Reduced" when true.
4. Price, or "Sold" in place of the price for a sold piece (WooCommerce's "Out of stock" wording becomes "Sold", through its own `woocommerce_get_availability_text` filter).
5. Short summary.
6. Add to basket (hidden by WooCommerce for a sold piece; question 1 asks about an "Ask about similar pieces" link there).
7. Below both columns: the **details table** (the detail rows as a two-column table, the label column narrow), then the sections **that you click to open**: About this piece (open to start), Condition, History, any added sections, then Delivery and returns. Plain `details` and `summary`, so they work with no script, with the keyboard and with screen readers.
8. Related products, as WooCommerce prints them.

On a phone everything stacks in the same order.

**What it removes:** WooCommerce's tabs (their contents now live in the table and the sections), and the category and stock number line under the button (moved into the details table as "Stock number"; question 5).

**Shop and category grids:** a small "Sold" badge on a sold piece's card. Nothing else changes in the grid in version one.

**Style.** The stylesheet follows the kit's rules: generated from `tools/shop.src.css` by the same build script, everything scoped under one body class the module adds on these pages (`twd-sk-shop`), our own classes doubled, `!important` on visual properties only, tokens only (`--twd-site-*`, so the shop follows the site's style pack and its extra colours), no colour or font literals, a size ceiling test. It styles our own pieces (labels, table, sections, badge) and only the layout of WooCommerce's two product columns.

**Standing aside.** If an Elementor Pro single product template applies to a product (the same `elementor_location_exits()` check the header and footer use), the layout part does nothing for that product; the pop-up and search tags still work. Question 11 asks whether the live shop has one.

## 6. AI help, copy and paste, and the firm rule

No AI key on the site and no call to any AI service. The pop-up builds a prompt; the person pastes it into Claude and pastes the reply back. A person presses Save.

**The prompt** (built from the product as it is in the pop-up, unsaved changes included):

> You are helping the owner of an antique jewellery shop write the description of one piece. Use only the facts given below: the product as it is now, and the owner's notes.
>
> **Firm rule. Never invent, change, round or "correct" any of these: dates and periods, carats and metal fineness, hallmarks and makers' marks, origin and maker, stones and their number, measurements, weights and sizes, and condition, wear, damage or repairs. Copy them exactly as given. You may reword the sentences around them. If something seems missing, unclear or contradictory, do not fill it in: list it under "to_confirm", and where it would go in the text write TO CONFIRM.**
>
> Do not add claims of value, rarity, investment or provenance that are not in the facts. No hype words. UK spelling. Never use an em dash or an en dash; use a comma or a full stop. Keep the price, stock, category and photos out of your reply: they are not yours to change.
>
> Text rules: sections are plain paragraphs and bullet lists with bold and italic; no headings inside a section, no pictures, no styles or classes.
>
> Reply with ONE JSON object only, in exactly this shape: `{ "schema": "twd-shop-product/1", "title", "short_summary", "details": [ { "label", "value" } ], "sections": [ { "heading", "html" } ], "seo": { "title", "description" }, "to_confirm": [ "..." ], "what_changed": "two or three sentences" }`
>
> Then the product as JSON, and the owner's notes.

**Checking the reply** (on the server, saves nothing; `POST .../ai-check`):
1. It must be that JSON (a code fence or a sentence around it is tolerated). Anything else is refused with a plain sentence saying what was wrong.
2. Price, stock, status, category and photos are never read from a reply. If the reply has them, one line says they were ignored.
3. Every text goes through the product text cleaner; titles and labels are plain text; lengths are cut with a warning.
4. **The facts check, in code, behind the prompt's rule.** The code lists the "protected" things in the source (the product as it was plus the notes) and in the reply: every number with its unit or neighbour word (years, "15ct", "9 carat", "22 mm", "4.2 g", ring sizes, "about 1870"), and words from a generic list of periods (Georgian, Regency, Victorian, Edwardian, Art Nouveau, Art Deco and so on), metals, stones, maker and hallmark words and condition words (worn, repaired, replaced, chipped, crack, restored and so on). Then:
   - something in the reply that is not in the source is flagged **"New: check this"**;
   - something in the source that the reply dropped is flagged **"Missing: check this"**;
   - a detail row whose value has a number must keep the value exactly; if the AI changed it, the original value is kept and the change is shown as a flag, not used;
   - a new detail row is kept only if its value is in the source, else it is shown as a suggestion, not used.
5. The `to_confirm` list and any TO CONFIRM in the text are shown at the top in amber.

**Preview and Use.** The panel shows each field before and after (the sections and the details table drawn as they will look, built as nodes from the cleaned text, never `innerHTML`), the flags first, and the "what changed" note. **Use these words** fills the pop-up's tabs, not the product, with a bar "Use my original instead / Use the AI version" (the same pattern as the Articles plugin's remix design), and a tick "I have checked the flagged items" before Save is enabled while flags remain. Then the normal Save, and one-click Undo after it. A product with TO CONFIRM left in it can be saved as a draft or kept live, but cannot be published from draft until it is gone.

The same tab works for a new product: empty product, the notes box does the work.

## 7. Blog posts

Covered already, by the Articles plugin (1.50.0 and newer): with no SEO plugin it prints the title, description, Open Graph and Twitter tags for single posts, and turns the Hello theme's description tag off; with Yoast it writes Yoast's fields. So **the Shop module does not touch posts**, and posts stay in the Articles plugin where their editor is. Small differences to line up there, later, as a brief for that repo (not for this module): its sharing picture is the featured picture at the large size with no width and height and no site default picture to fall back on, and its title adds " - Site name" where Site Kit uses the typed title as the whole title. Switching Yoast off on a site means the Articles plugin starts reading its own fields, so any Yoast wording on a post has to be copied across first (section 8).

## 8. Switching Yoast off later (a checklist, not code)

Do this on a staging copy first, then live with the owner's yes:
1. Shop module on and looked at: a product, a sold product, a category, the shop page, a kit page and a blog post each print one title, one description, one set of sharing tags, one canonical and (products) one Product block. Use the Search tab check and the page source.
2. Copy what Yoast holds into the new fields: on a site that holds almost nothing in Yoast, by hand is quickest (each title and description into the page Search tab or the article's own fields). Yoast's titles may hold its variables (`%%title%%`, `%%sep%%`, `%%sitename%%`), which must be written out as words. A command (`wp twd-sk shop seo-from-yoast --dry-run`) is only worth building for a site with many.
3. Deactivate Yoast (do not delete it; its data stays and it can be switched back on).
4. Check the sitemap at the site's `/wp-sitemap.xml` lists products and categories, and resubmit it in Search Console (the owner does this).
5. Check nothing used Yoast's breadcrumbs or other Yoast pieces in a header, footer or Elementor template.
6. A week later, check Search Console for new problems.

## 9. Build plan (small slices, each tried on a staging copy first)

1. **The gate and the switch.** `TWD_SK_Shop_Gate`, the option, the Site tab Shop fold-out (only with WooCommerce), `wp twd-sk shop`, an empty `modules/shop/load.php`. Tests: off means byte-identical output and no shop classes; no WooCommerce means off; safe mode means off; the class lists.
2. **Search tags for products, categories and the shop page.** The shared tag builder (byte-identical test for kit pages), the printed-once flag, the Hello description switch, canonicals on category and shop pages, the Search tab check counts. Tests with WooCommerce stubbed.
3. **The pop-up: Basics, Details, Story and condition.** Reading and writing through WooCommerce's product objects, the text cleaner, the snapshot history, Undo, Restore, the fingerprint check, a new draft product, Publish with the TO CONFIRM rule.
4. **Search tab and AI help.** The prompt, the reply check, the facts check, the preview, Use these words.
5. **The tidy product page.** Hooks, the stylesheet, the Sold badge and wording, standing aside for an Elementor template. Looked at at desktop and phone width.
6. **The Yoast checklist on the staging copy** (section 8), then live only with the owner's yes.

Version: these would be 0.20.0 (slices can be test builds `0.20.0-testN` on a staging copy). Released through the updater only at the owner's yes, as always. Before slice 1, add the shop's own name words to the private client-word list so the privacy test guards this public repo.

## 10. Open questions for the owner

1. **Sold pieces:** keep them on the site with "Sold" (recommended: they show the range, keep their search places and links) or hide them from the shop and categories? Is a "Reserved" state needed as well? Should a sold piece show an "Ask about similar pieces" link to the contact page?
2. **Price on request:** needed for some pieces (no price, no basket button, an enquiry link instead)?
3. **Delivery and returns:** one text for the whole shop (recommended) or a version per product?
4. **Details:** start with free labels per product (recommended for version one), with a suggested list (Period, Metal, Stones, Hallmarks, Maker, Size, Measurements, Weight)? Later the main ones could become WooCommerce global attributes so visitors can filter by period or metal.
5. **Labels near the title:** a tick per detail row (recommended), or always the same two (for example Period and Metal)? And keep the stock number visible in the details table?
6. **The pop-up script:** build on the existing editor script (recommended: same look, less code, nothing extra for visitors) or stand alone?
7. **Product structured data:** leave WooCommerce's exactly as it is (recommended for version one), or later add "used condition" for antiques through WooCommerce's own filter?
8. **Category pages:** include search wording for categories in version one (recommended, small), edited from a small Edit category pop-up on the category page?
9. **Front page structured data:** the shop would run in business mode (LocalBusiness). Is a "Store" or "Jewelry store" type wanted instead? That would be a small site-wide setting, useful to other shops too.
10. **Photos:** version one keeps photos in the WooCommerce screen (a link from the pop-up). Should version 1.1 let the pop-up choose the main photo and gallery from the media library?
11. **The live shop now:** is an Elementor Pro single product template in use? (If so the layout tick stands aside until it is removed.) Is there a staging copy of the shop on the staging domain to build on? This needs checking by the session that looks after the live site, not from here.
12. **Titles:** use the typed search title as the whole title (as kit pages do, recommended), or add " - Site name" (as the Articles plugin does)?
13. **New products from the pop-up:** in version one (recommended: Add a product makes a draft, you fill it in, add photos in WooCommerce, then Publish)?

## 11. Found while reading (follow-ups, not part of this module)

- **Hello's description tag on kit pages.** `TWD_SK_Seo` does not turn off the Hello theme's own description tag, so a kit page that has an excerpt gets two descriptions. Kit pages rarely have an excerpt, so this is small, but the fix (the same filter as section 4) belongs in `TWD_SK_Seo` for every site.
- **The same tag with Yoast on the live shop.** Hello prints its tag whether or not Yoast is active, so the live shop's products may already have two description tags. Worth a look in a product page's source by the live-site session.
- **The Articles plugin's sharing tags** (section 7): a brief for that repo.
