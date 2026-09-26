# Changes since the last release (0.3.2 → 0.4.0)

This file summarises what changed relative to the most recent released version,
0.3.2. For the full history see [changelog.md](changelog.md).

## Security fixes

- **Promoted translations can no longer break out of HTML attributes or inline
  JavaScript.** Moodle prints language strings unescaped, including inside quoted
  HTML attributes (the login block's button, the site logo's alt text, some activity
  buttons) and inline scripts. The previous guard only refused HTML tags, so a
  crowd-sourced suggestion containing a quote could, once an administrator approved or
  pushed it, inject script into every page using that string. Suggestions are now
  checked on the server: straight quotes are converted to typographic ones (`'` → `’`,
  `"` → `“ ”`), and angle brackets, backslashes, backticks and HTML character
  references (with or without the closing `;`) are refused. The same rule applies when
  a suggestion is applied, when translations are served and when a language pack is
  exported.
- `local/langcrowd:admin` now declares `RISK_CONFIG | RISK_XSS`: approving a suggestion
  turns user-written text into a site-wide language string.
- The 4096-character suggestion limit is enforced on the server, and each user keeps at
  most one pending suggestion per string (a new one replaces the old).
- Votes and suggestions re-check the component allow-list.
- A vote or the hourly task can no longer overwrite an administrator's lock, push or
  remove that happens at the same moment.

## Bug fixes

- **Components never offered in the component filter** (for example
  `mod_rememberme`). The setting listed only components whose strings had already been
  recorded, and once any component was selected, no new ones were recorded — so a
  component not seen before could never be chosen. The setting now lists every
  installed component.
- **One string split across two records.** Strings were recorded under whatever
  component spelling a plugin used (`rememberme` / `mod_rememberme`, `moodle` / `core`),
  splitting votes and suggestions. Component names are now normalised everywhere.
- **Remove reset a translation to the English source,** which then went into that
  language's export. It now restores the installed language pack's value.
- Exported language packs use Moodle's real file names (`moodle.php`, `forum.php`,
  `admin.php`, `block_html.php`).
- The capability names were missing from the language packs.
- Managers (who have `local/langcrowd:admin` but not full site administration) can now
  reach the reports and the exporter from the admin menu; the navbar link uses the same
  capability.
- Approve/Push/Reject act only on pending suggestions; bulk actions are all-or-nothing
  and report skipped rows. Suggestions stored before the new text rules are marked
  *Cannot be applied*.
- Privacy: every exported field is declared, exports say which string each vote or
  suggestion is about, deletions stay within the system context, and vote counts are
  recomputed after a deletion. The hourly task keeps counts accurate even with the
  threshold at 0.

## Documentation

- Corrected the admin menu location: *Site administration → Plugins → Local plugins →
  Language Crowdsourcing*.
- New sections on the text rules for suggestions and on uninstalling (user guides in
  English, Japanese and Thai).
- How to use an exported pack: copy the files into `moodledata/lang/<lang>_local/`
  (the same place *Language customisation* saves to) or contribute via AMOS. The zip
  contains only the crowd-sourced strings; *Language packs* cannot install it.

## Upgrade notes

1. Deploy the code and run the Moodle upgrade. The upgrade step:
   - merges string records that were split by component spelling, keeping the one
     further along the review cycle and moving votes and suggestions onto it;
     normalises the *Components to enable crowdsourcing for* setting;
   - keeps only the newest pending suggestion per user and string;
   - converts straight quotes in locked/pushed translations so they keep being served;
   - recomputes pending records' current value from the language packs (repairs
     records that Remove had reset to English).
2. Suggestions stored earlier that contain now-refused characters stay in the
   User Suggestions report marked *Cannot be applied*; reject them.
3. Review the component setting once: it now lists every installed component.

## Metadata

- `release` → `0.4.0`, `version` → `2026092601`. No database schema changes.
- Tests: 68 → 116 PHPUnit tests; tests moved into namespace directories and use
  PHPUnit attributes.
