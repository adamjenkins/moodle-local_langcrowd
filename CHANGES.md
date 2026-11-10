# Changes since the last release (0.4.3 → 0.4.4)

This file summarises what changed relative to the most recent released version,
0.4.3. For the full history see [changelog.md](changelog.md).

## v0.4.4

- Approving or pushing a suggestion now always applies the text the admin saw in the
  suggestions report. Previously, if the author resubmitted the suggestion after the report
  was loaded, the newer text was applied instead. A resubmitted suggestion now shows up as a
  new row, and an action on the old row is counted as skipped.
- When a user account is deleted, that user's votes and suggestions are now removed and the
  vote counts of the strings they voted on are recalculated, so a deleted account no longer
  counts toward locking a translation. The upgrade also cleans up the votes and suggestions
  left by accounts deleted earlier. Translations already in use are kept.
- Line breaks in a suggested translation are now turned into a single space, and text that
  still contains a line break (or is not valid UTF-8) is refused, so a translation can no
  longer break a page's JavaScript.
- The plugin now has its own icon on the Plugins overview page (Site administration > Plugins).
- The camp listing now shows eight screenshots.

The upgrade runs one cleanup step; no table or capability changes.
