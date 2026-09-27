# Changes since the last release (0.4.0 → 0.4.1)

This file summarises what changed relative to the most recent released version,
0.4.0. For the full history see [changelog.md](changelog.md).

## Unreleased

- Declare Moodle 5.3 support (`$plugin->supported` is now `[502, 503]`).

## New features

- **Strings with placeholders can now be voted on and translated.** Strings that
  Moodle fills in with values, such as mod_rememberme's grading explanation ("…study on
  {$a->days} different days each week…") or progress labels like "Today: 0 of 3
  questions", previously had no tick/cross buttons, because only strings without
  parameters were recorded. They are now recorded as they appear on the page (the same
  string may appear several times with different values), and an approved or pushed
  translation is shown with its values filled in exactly as Moodle core does it.
- **The suggestion dialog starts pre-filled** with the current translation, placeholders
  included, so a suggestion is an edit of what is already there. Strings with
  placeholders show a hint to keep them; a suggestion that drops or changes a placeholder
  gets an inline message before anything is sent. Submitting the unedited text does
  nothing.

## Rules

- A translation must keep exactly the placeholders of the English source (their order
  may change). This is enforced when a suggestion is submitted, when it is approved or
  pushed, when translations are served, and when a language pack is exported.

## Bug fixes

- The suggestion dialog no longer shows tick/cross buttons on its own copy of the
  current translation.

## Upgrade notes

- No database changes and no upgrade step. Deploy the code and purge caches (the
  JavaScript and language strings changed).

## Metadata

- `release` → `0.4.1`, `version` → `2026092602`.
- The `local_langcrowd_get_string_ids` web service also returns `current`, the current
  translation template.
- Tests: 116 → 125 PHPUnit tests.
