# Changes since the last release (0.3.0 → 0.3.1)

This file summarises what changed relative to the most recent released version,
0.3.0. For the full history see [changelog.md](changelog.md).

## Bug fixes

- **The "English source" column now really shows the English source.** String records
  used to store the client-submitted value — the string as rendered in the voter's
  language — as the source. Wherever the target language pack (or a promoted
  translation) already translated a string, the "English source" shown in the Voting
  and Suggestions reports and in the suggestion dialog was therefore not English.
  The English value is now resolved server-side from the English language pack when
  a string is registered, and an upgrade step recomputes the source for all existing
  records.
- As part of the same fix, the `get_string_ids` web service no longer registers
  string keys that do not exist in the English language pack.

## Upgrade notes

1. Deploy the new code and run the Moodle upgrade. The upgrade step rewrites the
   stored English source of existing string records from the English language pack;
   no configuration changes are required.

## Metadata

- `release` → `0.3.1`, `version` → `2026071400`. No database schema changes.
