# Changes since the last release (0.2.1 → 0.3.0)

This file summarises what changed relative to the most recent released version,
0.2.1. For the full history see [changelog.md](changelog.md).

There are **no database schema changes** in this release. `version.php` is bumped
purely so existing sites are offered the update; no upgrade step is required.

## Security fixes

| Area | Before (0.2.1) | After (0.3.0) |
|---|---|---|
| Language-pack export | Escaped only single quotes when writing `.php` files; a translation containing a backslash or crafted quotes could produce a corrupt or code-injecting language file. | Uses `var_export()` — output is always valid, safe-to-load PHP. |
| `get_string_ids` web service | Required login only; any authenticated user could register arbitrary string rows, ignoring the "allowed roles" setting; unbounded number of strings per call. | Requires the `local/langcrowd:vote` capability, enforces the role/language gate, and bounds the strings per call. |
| Role / language restriction | Only hid the overlay; the web services did not re-check it. | Enforced server-side in all three web services. |
| Admin report actions | Performed via GET links. | Performed via POST buttons with a confirmation dialog. |

## Bug fixes

- **"Remove" on the Voting Report is now durable.** It previously left the vote rows in
  place, so a removed string re-locked within the hour (or on the next vote). Remove now
  deletes the votes and resets the value to the source, like Approve/Push already did.
  The report action logic was consolidated into a tested `\local_langcrowd\manager` class.

## Behavioural changes

- The overlay is now **opt-in**: a floating "Improve translations" toggle turns the
  buttons on only when the user asks, instead of annotating every page automatically.
- Locked/pushed translations are now served in AJAX-rendered content too, not just full page loads.
- Suggestions can no longer be submitted against a locked string.
- The suggestion dialog now uses `core/modal` (theme / dark mode / RTL aware) and shows the English source.
- The hover highlight is drawn as an outline (contrast-safe) instead of a background fill.
- The overlay skips fixed/sticky regions (navbars, drawers, footers) and is hidden from users without `local/langcrowd:vote`.

## New in this release

- **Component filter** setting — restrict the overlay to selected components (empty = all),
  enforced server-side; the Export page pre-selects those components.
- **Undo a vote** — a short-lived undo affordance after voting (`submit_vote` accepts `0` to withdraw).
- **Approval progress** shown in the approve button's tooltip (e.g. "5/10 approvals").
- **Touch support** — always-visible buttons on devices without hover.
- **English source column** in both admin reports, and **bulk actions** (select rows +
  Lock/Remove or Approve/Push/Reject).
- **Overview dashboard** (`overview.php`) with status counts, most-voted strings and recent suggestions.
- **Export all languages** in one zip.
- A full PHPUnit test suite under `tests/`.
- `composer.json` (Packagist / Composer install) and `.gitignore`.
- User documentation under `docs/` in English (`en`) and Thai (`th`).

## Metadata

- `release` → `0.3.0`, `version` → `2026071300`, `supported` → `[502, 502]`, maturity → `BETA`.
- Copyright headers updated to `Adam Jenkins <adam@wisecat.net>`.
- CI matrix corrected to Moodle 5.2 on PHP 8.3 / 8.4 (5.2 requires PHP 8.3).

## Upgrade notes

1. Deploy the new code and run the Moodle upgrade (picks up the new version number).
2. No configuration changes are required. The `$CFG->customstringmanager` line remains
   as documented in the README.
3. If you have scripted calls to the `local_langcrowd_get_string_ids` web service, note
   that callers now need the `local/langcrowd:vote` capability.
