# Our ethos

These rules guide every decision about Site Kit. When a feature idea conflicts with one of them, the idea changes, not the rule. This file is public: it holds principles only, never client details.

## The eight rules

1. **Clients own their sites.** They should never need wp-admin or a terminal. Everything they do happens in front-end pop-ups.
2. **No hourly charging after launch.** Each site should need about 1 hour a year of unpaid support, so a feature must reduce support time, not add to it.
3. **Safe by default.** Backups, versions, undo, safe mode and plain-English messages.
4. **Privacy first.** Collect the minimum, say what is stored and for how long, and make it deletable. No secrets on client sites.
5. **Human in the loop.** AI drafts, a person approves. Nothing AI-written is published or applied until a human clicks Publish or Apply.
6. **Calm and plain.** No jargon in anything a client sees.
7. **One toolkit, many sites.** Fix once, improve everywhere, versioned and self-updating.
8. **Honest about what is verified.** A feature is marked "not yet verified" until it has been tried on a real site.

## How we work, in short

- Discuss complex or risky changes first, in plain words, and get a clear yes before building.
- Small, reversible steps. Verify on a real site before building the next feature.
- No em dashes or en dashes anywhere, including code comments. Use a comma, a full stop or a hyphen.
- This repository is public. Use invented examples only. Never put client names, copy, photos, numbers, image paths, passwords or keys in any file, commit message or issue.

## Definition of done

A feature is done only when every box can be ticked.

- [ ] It does what the one-line "done means" sentence says, in terms of what a person can do and see.
- [ ] `php tests/run.php` passes, including the privacy test (run it with the private client-word list when you have one).
- [ ] `php -l` is clean on every PHP file, and nothing needs PHP newer than 7.4.
- [ ] Privacy check: what is stored, where, and for how long is written down, a person can delete it, and no secret or client detail is in the repo.
- [ ] It is off or harmless in safe mode, and there is a way back (undo, version or rollback).
- [ ] Any wording a client sees is plain and calm.
- [ ] Tested on a real site by a human. Until then it is labelled "not yet verified" in BUILD-LOG.md.
- [ ] The version is bumped in all three places (plugin header, `TWD_SK_VERSION`, readme.txt Stable tag) and the changelog is updated.
- [ ] BUILD-LOG.md is updated (where we are, next, newly verified or found broken) and the Status list in CLAUDE.md, in the same commit as the work.
- [ ] The diff and commit message were shown to the owner before anything was pushed.
