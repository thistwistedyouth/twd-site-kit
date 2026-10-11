# Client folder template

A generic starting point for every new client site. Copy this folder, rename the copy, and keep the copy **somewhere private** (never in this public repo). Nothing in here holds client data.

## Layout of a client folder

```
client-name/
  CLAUDE.md              the client brief for Claude (copy of CLAUDE-TEMPLATE.md, filled in)
  01-intake/             form export, call transcript (text), signed-off sheet
  02-photos/             original photos, plus a notes file saying who owns what
  03-brief/              facts, site brief JSON, gaps and questions
  04-site/               test log, launch check, notes from the build
  05-handover/           what the client was given, login details sent separately
```

## Order of work

1. Send the client the intake form link ([intake-form-fields.md](intake-form-fields.md)).
2. Hold the 30 minute call ([call-run-sheet.md](call-run-sheet.md)) and record it, with consent ([recording-and-privacy.md](recording-and-privacy.md)).
3. Transcribe the call to text, then delete the audio.
4. Ask the client for photos using the [photo shot list](photo-shot-list.md).
5. Build the facts file and the site brief from the form export and the transcript. Mark anything missing as a gap and ask the client.
6. Send the client a confirmation sheet of every fact and registration line. Nothing is published until they confirm it.
7. Build and test on the clean base site, then launch.

## Files in this folder

| File | What it is |
|---|---|
| `CLAUDE-TEMPLATE.md` | The brief Claude reads at the start of every session about one client |
| `intake-form-fields.md` | The field list for the one Elementor form |
| `questionnaire.md` | The questions for the call, with why each is asked |
| `call-run-sheet.md` | The 30 minute call, minute by minute |
| `photo-shot-list.md` | What photos to ask for and how |
| `recording-and-privacy.md` | Consent wording, storage, deletion |
