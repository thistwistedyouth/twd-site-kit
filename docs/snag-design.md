# Design: the "Tell us" snag module

Status: BUILT as version 0.11.0 on a branch, not yet verified on a real site. The owner lifted the verify-first rule for this feature, so it is tested together with 0.7.0 to 0.10.2 (see `docs/test-script-0.7-0.9.md`). This file is public: invented examples only.

## What it is

A module inside Site Kit, with an on/off setting, that lets a client tell the site owner what they would like changed, from the front of their own site. It is not a separate plugin. Notes are kept on the client's own site. No outside service, no GitHub token, nothing sent anywhere by the plugin.

## Decisions

| Question | Decision |
|---|---|
| Who can send a note | Any logged-in user who can edit pages |
| Photos | None in version one. Text only, with the page filled in automatically |
| Retention | 12 months by default, changeable per site in the Site tab |
| Email | Off by default. One address. A notification only, with no message text |
| Ideas | Admin-only. Same record with a type field. Never shown to clients |
| Web addresses | None anywhere: not stored, not shown, not in exports |
| Button label | "Tell us", with the subtitle "What would you like changed?" |
| Markdown | A "Copy new notes as markdown" button in the admin panel |
| Safe mode | Switches the module off |

## What a client sees

A small "Tell us" button, placed clear of the Edit buttons and of other plugins' floating buttons. It opens a pop-up with:

- The title "Tell us" and the subtitle "What would you like changed?".
- The page, filled in automatically from where they are (title only, shown as plain text).
- One text box for the message, with a character limit (about 2,000) and a count.
- A short privacy line: what is kept (the message, the page, the date, who sent it), for how long (the site's retention, 12 months unless changed), and that they can delete it.
- A Send button, then a calm confirmation.
- A short list of their own earlier notes, each with a Delete button (two steps).

Plain words only. No jargon. Nothing flashes.

## What the site owner sees

An administrator-only panel (Snag and Idea) in the same pop-up:

- A list of notes, newest first, with date, page, who sent it and status (new, seen, done, parked).
- A type on each record: `snag` (from a client) or `idea` (added by an administrator). Ideas are never shown to clients, never counted in what clients see, and never included in a client's own list or export.
- Change status, add an idea, delete a note.
- A **Copy new notes as markdown** button: copies every note with status new, as plain markdown text, to the clipboard (with a selected-text-box fallback, as the Edit pop-up already does). It does not change any status by itself.

## Where notes live

A private custom post type, `twd_sk_snag`: not public, no archive, no web address. Each record holds:

- date
- page (ID and title)
- message (plain text)
- type (`snag` or `idea`)
- status
- user ID of the sender

No photo, no web address, no IP address, no email address, no name copied in.

## How the owner gets the notes

- Over SSH with WP-CLI: `wp twd-sk snags [--status=new] [--type=snag] [--format=json|markdown]`, and `wp twd-sk snag-status <id> <status>`. Output holds no web addresses (the page is its title and ID).
- The admin panel button above, for copy and paste.
- Optional email, off by default: one address, one short line per note saying that a note arrived on the site and its page title. It never contains the message text.

## Safety

- Sending needs a signed-in user with `edit_pages`, a valid REST nonce checked again in our own permission callback, and a page the user can edit.
- Rate limit per user (about 10 notes an hour), and a site-wide daily ceiling.
- Message cleaned of tags, control characters and long dashes, and capped in length. A request body size limit like the other routes.
- Written only by one class that owns the post type, the same pattern as `TWD_SK_Store`. The REST class never touches the database directly.
- Safe mode: the module is started from `TWD_SK_Modules`, so safe mode switches it off. The button disappears. Existing notes stay in the database untouched and come back when safe mode is turned off.
- The module setting (`twd_sk_snags`) is off by default.

## Privacy

- The form says what is stored and for how long, in plain words.
- Retention: 12 months by default. A setting in the Site tab changes it per site. A daily self-healing sweep (no wp-cron, a check on a normal page load) removes older notes.
- A client can delete their own notes. An administrator can delete any.
- Notes are included in WordPress's own personal data export and erase tools.
- No secrets on the client site. Nothing leaves the site except the optional notification email.
- The admin panel and the CLI never print a web address.

## Version star

A small, quiet line in the pop-up and the admin panel showing the plugin version and build, so the owner can tell whether a phone is seeing the new build or a cached one. Text only, no animation.

## Not in version one

Photos or any attachment, reply-by-link, status shown back to the client, push notifications, a separate plugin, any external service.

## Done and still to do (definition of done applies)

Done on the branch: the code, the tests (they pass, including the PHP 7.4 syntax and privacy checks), the version bump to 0.11.0, the changelog, BUILD-LOG, CLAUDE.md and HISTORY.

Still to do: a real-site test by a human (the Tell us section of `docs/test-script-0.7-0.9.md`), then merge to main when the owner says so. Until then the feature is labelled not yet verified.
