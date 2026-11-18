# The Welcome screen's Guides and videos

Every site's Welcome screen (Dashboard > Welcome), under the how-to cards, lists the posts in one category on the agency's own site.

**To add a guide:** write a normal post there with the WordPress editor (text, pictures, the video), put it in the category `client-help`, and give it a short excerpt (the one-line summary shown on the card). Tag it `video` to show it as a video. Sites pick it up within 12 hours (a failed fetch is retried after an hour). No plugin release is needed.

- Read from `/wp-json/wp/v2/categories?slug=client-help` (to find the category) and then its posts (12 newest at most; title, excerpt, link and class list only). Both are public WordPress feeds on one fixed host.
- A link must be https on `therapywebdesigns.co.uk` or a sub-domain, with no port or sign-in details; anything else is dropped. The guide opens in a new tab on the agency's site, so the video plays there, never inside a client's wp-admin.
- Titles are cut at 80 characters and summaries at 200, as plain text with no long dashes.
- Nothing about the client site or the person is sent. A site turns the list off with the filter `twd_sk_help_feed_on`. Safe mode never fetches. Editors only.
- Videos: short H.264 MP4 files (720p is plenty) with the "fast start" flag, hosted on the agency's own hosting. Keep the master copies somewhere else too.
- Only published posts in that category appear, so a draft stays private until published. The posts are public web pages: no client names or details in any of them.
