# Build log

What is done and what is next, newest at the top. Rules live in CLAUDE.md, reasons in HISTORY.md.

## 0.4.0 (in progress)

Order of work. Each piece is its own commit on `main`; the version number, zip and update JSON change only at the end.

| # | Piece | State |
|---|-------|-------|
| 0 | Foundation: this log, safe mode (`TWD_SK_SAFE_MODE` or `wp twd-sk safe-mode`) | done |
| 1 | Site tab: pack switch, colour, font and radius overrides, contrast blocking, live preview, reset | next |
| 2 | Header, footer and site profile: variants, `[twd_header]` and `[twd_footer]`, mobile menu, Theme Builder JSON export | to do |
| 3 | Starter pages (Home, About, Contact) and the setup command | to do |
| 4 | SEO: search mirror, SEO tab, structured data, image attributes | to do |
| 5 | Release 0.4.0: version, zip, update JSON, docs, test script for David's site | to do |

Already shipped before this release (0.3.1, the commit titled "0.3.1: TWD Kit Page template..."): the TWD Kit Page template, the Pages tab (New page, Publish and Unpublish with confirmation, Switch this page to the kit template), the two-level leftover check and the publish block, the prompt and checker fixes. They were listed again in the 0.4.0 brief and were not rebuilt.

Open notes:
- The Theme Builder state on David's site was not reported (the brief had an unfilled placeholder). Assumed none.
- 0.3.1 had not been reported as tested when 0.4.0 work started.

## Safe mode and recovery

Safe mode switches off the 0.4.0 modules and keeps everything from 0.3.1 and earlier working (the Edit with AI tab, preview, apply, history, the page template and Pages tab, packs, the updater, WP-CLI). `[twd_header]` and `[twd_footer]` stay registered and print a plain minimal header and footer, so a Theme Builder header never shows a raw shortcode.

If something looks wrong after the update, in this order:

1. **Switch safe mode on.** Any one of these:
   - Add `define( 'TWD_SK_SAFE_MODE', true );` to `wp-config.php`, above the line that says "That's all, stop editing". Works even when wp-admin is broken. Remove the line to switch it off.
   - Over SSH: `wp twd-sk safe-mode on` (and `off` to switch it back).
   - If the plugin itself will not load: `wp option update twd_sk_safe_mode 1`.
2. **A page made with the kit template shows blank?** Open the page in WordPress admin, change Template to the theme's default, and Update. Or over SSH: `wp post meta update PAGE_ID _wp_page_template default`.
3. **The header or footer looks wrong?** In Elementor, Templates, Theme Builder, set the header or footer template's display condition to nothing (or delete it). The theme's own header and footer return.
4. **Roll back the plugin.** Reinstall the previous zip from the public repo: 0.3.1 is `https://raw.githubusercontent.com/thistwistedyouth/twd-site-kit/5005215a9c09677c3a5de731897b64c6ec1b9c75/dist/twd-site-kit-latest.zip`, 0.3.0 is `https://raw.githubusercontent.com/thistwistedyouth/twd-site-kit/80a40383772989857b355de6a12a1365985c8200/dist/twd-site-kit-latest.zip`. Download it, then Plugins, Add New, Upload Plugin, and replace. (The updater will offer the newer version again; leave it until the cause is fixed.)
5. **Plugin screen unreachable?** Rename `wp-content/plugins/twd-site-kit` to `twd-site-kit-off` over SFTP. Nothing is lost: the page content and history live in the database and come back when the folder is renamed back.

## Earlier releases

See HISTORY.md and the changelog in readme.txt.
