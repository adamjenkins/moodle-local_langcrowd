# Changes since the last release (0.3.1 → 0.3.2)

This file summarises what changed relative to the most recent released version,
0.3.1. For the full history see [changelog.md](changelog.md).

## Security fixes

- **Stored XSS closed: string values are now resolved entirely server-side.**
  The `get_string_ids` web service used to accept the rendered string value from
  the client (`PARAM_RAW`) and store it as the record's current value. Because the
  custom string manager serves that value verbatim through `get_string()` once a
  string is locked or pushed — and Moodle emits language strings unescaped — any
  authenticated user with the vote capability could seed a script payload against
  a real string key and have it execute site-wide (administrators included) once
  the string was promoted. The `value` field has been removed from the web service;
  the current value is now resolved from the installed language packs on the server,
  exactly as the English source already was. An upgrade step repairs existing
  records (see upgrade notes), and as defence in depth the string manager now
  refuses to serve any promoted value containing HTML markup.

## Bug fixes

- The language-pack exporter now builds its temporary zip inside Moodle's managed
  temp area (`make_request_directory()`) instead of the operating-system temp
  directory, so it respects the site's temp configuration on clustered or
  containerised deployments.

## Documentation

- README: new *Uninstalling* section — remove the `$CFG->customstringmanager`
  line from `config.php` when uninstalling the plugin.

## Upgrade notes

1. Deploy the new code and run the Moodle upgrade. The upgrade step recomputes
   the stored current value from the language packs for all pending records and
   for any locked/pushed record containing HTML markup (which can only be
   injected data — curated values are always plain text). Records whose string
   key is unknown to the English pack and whose value contains markup are
   deleted together with their votes and suggestions.
2. No configuration changes are required. Locked/pushed plain-text translations
   (community-approved or admin-pushed) are preserved unchanged.

## Metadata

- `release` → `0.3.2`, `version` → `2026071700`. No database schema changes.
