# TWD Site Kit

WordPress plugin for Therapy Web Designs sites. Stores a page as sanitised HTML with version history and shows it with the `[twd_page]` shortcode, styled by a style pack with bundled fonts. See CLAUDE.md for the rules and HISTORY.md for why.

**This repo must stay public** (the self-hosted updater needs that).

## Install

Upload `dist/twd-site-kit-latest.zip` in WordPress (Plugins, Add New, Upload Plugin), then Activate.

## Use (WP-CLI)

```
wp twd-sk save <page_id> <file.html> [--note="text"] [--base=<version>]
wp twd-sk get <page_id>
wp twd-sk versions <page_id>
wp twd-sk undo <page_id>
```

Switch the look with a style pack (sage or grove):

```
wp twd-sk pack            list packs and show the active one
wp twd-sk pack grove      switch
```

Put `[twd_page]` on the page (an Elementor Shortcode widget, or the normal editor) to show the stored content.

## Tests

```
php tests/run.php
```

## Try every component

`starters/_gallery.html` shows every component and variant with placeholder text. Load it into a test page:

```
wp twd-sk save <page_id> wp-content/plugins/twd-site-kit/starters/_gallery.html --note="gallery"
```
