# The Welcome screen's tutorial list

`help/twd-site-help.json` in this repo (the `main` branch) is read by every site's Welcome screen (Dashboard > Welcome), under the how-to cards. Changing it needs no plugin release: sites refresh it within 12 hours (a failed fetch is retried after an hour).

```json
{
	"schema": "twd-site-help/1",
	"items": [
		{ "title": "Change the words on a page", "summary": "A two minute walk through.", "url": "https://therapywebdesigns.co.uk/...", "kind": "video" },
		{ "title": "Adding a picture", "summary": "Choosing from the library.", "url": "https://therapywebdesigns.co.uk/...", "kind": "article" }
	]
}
```

- `kind` is `video` or `article` (anything else is an article). Twelve entries at most; the file must be under 50 KB.
- `url` must be https on `therapywebdesigns.co.uk` or one of its sub-domains, with no port or sign-in details. Anything else is dropped. The video itself plays on that page (hosted on the agency's own site), never inside a client's wp-admin.
- Titles are cut at 80 characters and summaries at 200, as plain text with no long dashes.
- Nothing about the client site or the person is sent. A site turns the list off with the filter `twd_sk_help_feed_on`. Safe mode never fetches.
- Videos: short H.264 MP4 files (720p is plenty) with the "fast start" flag, so they begin playing at once, on the agency's own hosting. Keep the master copies somewhere else too.
- This repo is public: no client names or details in any entry.
- The file is not part of the plugin zip. It only takes effect once it is on `main`.
