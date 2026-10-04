# Changes since the last release (0.4.2 → 0.4.3)

This file summarises what changed relative to the most recent released version,
0.4.2. For the full history see [changelog.md](changelog.md).

## v0.4.3

- The Japanese and Thai language packs (lang/ja, lang/th) are no longer included: releases ship the English strings
  only, as the Moodle Plugins directory expects. Those languages are provided through Moodle's language
  packs.
- Choosing "Authenticated user" under "Roles allowed to vote" now lets every logged-in user (not the
  guest) vote and suggest. It used to let nobody, because Moodle stores no role assignment for that
  role.
- Continuous integration now tests against the released Moodle 5.3 (MOODLE_503_STABLE) instead of
  Moodle's development branch.
