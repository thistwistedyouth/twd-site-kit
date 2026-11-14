---
description: Tidy the session sidebar - rename, tag, list what waits for Clive, propose archives
---
Tidy Clive's session list so he can see what is active. Plain words, no em dashes.

1. `list_sessions` with `mine: true` (page with `after_id` until `has_more` is false).
2. Rename any session whose title does not fit `PROJECT | Topic | brief NN` with `set_session_title`. Prefixes: QB (Qrious Bee), QT (Qrious Therapy), Sites (TRD, TWD, client sites, Site Kit, Articles), Other. Never rename a session that is RUNNING.
3. `set_session_tags`: add `waiting-on-clive` to sessions whose status_bucket is BLOCKED; remove it from any that no longer are. Add `qrious-bee`, `qrious-therapy` or `sites` by project. Pins are handled in 3b.
3b. Pins: the `ui:pinned` tag is the sidebar pin. Add it to every RUNNING session and every session tagged `waiting-on-clive`. Remove it from sessions that are completed and not waiting. Leave Clive's hand-pinned sessions alone unless they are completed with nothing pending, and then list them in the report instead of unpinning.
4. List sessions that are completed or idle with nothing pending and older than two days. Show them to Clive as a numbered list and ask which to archive. Archive (`archive_session`) ONLY after he says yes. Never archive pinned, running or blocked sessions.
5. Report in under 15 lines: running now, waiting on Clive (one line each with the question), renamed count, proposed archives.
Change no repo files.
