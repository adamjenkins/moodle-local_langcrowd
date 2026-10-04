# Changes since the last release (0.4.1 → 0.4.2)

This file summarises what changed relative to the most recent released version,
0.4.1. For the full history see [changelog.md](changelog.md).

## Unreleased

- The Japanese language pack (lang/ja) is no longer included: releases ship the English strings
  only, as the Moodle Plugins directory expects. Japanese is provided through Moodle's language
  packs.
- Choosing "Authenticated user" under "Roles allowed to vote" now lets every logged-in user (not the
  guest) vote and suggest. It used to let nobody, because Moodle stores no role assignment for that
  role.

## v0.4.2

- Declare Moodle 5.3 support (`$plugin->supported` is now `[502, 503]`).
