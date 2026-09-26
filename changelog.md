# Changelog

All notable changes to this project will be documented in this file.

The format follows [Keep a Changelog](https://keepachangelog.com/en/1.1.0/).
Version numbers follow [Semantic Versioning](https://semver.org/).

---

## [Unreleased]

### Added

- **Strings with placeholders can now be voted on and translated.** Strings such as
  mod_rememberme's grading explanation ("…study on {$a->days} different days…") were
  never annotated, because only strings without parameters were recorded. They are now
  recorded with the text as it appears on the page (the same string can appear several
  times with different values), and a promoted translation is served with its
  placeholders filled in exactly as core does. A translation must keep exactly the
  source's placeholders; otherwise it is refused at submission, skipped by Approve/Push,
  not served and not exported.
- The suggestion dialog starts pre-filled with the current translation (placeholders
  included) so a suggestion is an edit of what is there; strings with placeholders show
  a hint to keep them. Submitting the unedited text does nothing.
- `get_string_ids` also returns the current translation template (`current`).

## [0.4.0] — 2026-09-26

Version `2026092601`; the upgrade step runs at `2026092600` (see Upgrade notes in CHANGES.md).

### Security

- **Promoted translations can no longer break out of HTML attributes or inline
  JavaScript.** The serving guard only refused values containing tags, but Moodle
  core also prints `get_string()` output unescaped inside quoted attributes (e.g. the
  login block's submit button, the site logo's `alt`, mod_lesson's buttons) and in
  inline scripts. A crowd-sourced suggestion such as `Log in" onfocus="…"`, once an
  admin approved or pushed it, became stored XSS on every page using that string.
  Suggestions are now checked on the server: straight quotes are converted to
  typographic ones, and angle brackets, backslashes, backticks and HTML character
  references (with or without the closing `;`) are refused. The same rule is enforced where promoted values are served
  and where suggestions are applied, and the exporter never writes such a value into
  an installable language pack unless it is identical to the installed pack. An
  upgrade step converts straight quotes in existing promoted values so they keep
  being served.
- `local/langcrowd:admin` now declares `RISK_CONFIG | RISK_XSS`, like core's
  language customisation capability.
- Suggestion length (4096 characters) is enforced on the server, not only by the
  browser, and each user keeps at most one pending suggestion per string (an upgrade
  step removes older duplicates, keeping the newest).
- Votes and suggestions re-check the component allow-list, so excluding a component
  also freezes the rows already recorded for it.

### Fixed

- **Components never offered in the component filter** (e.g. `mod_rememberme`). The
  option list was built from strings already recorded, and once any component was
  selected, strings of unselected components were no longer recorded — so a component
  not seen before the setting was saved could never be chosen. The list now offers
  every installed component.
- **One string split across two rows.** Strings were recorded under whatever component
  spelling the caller passed to `get_string()` (`rememberme` vs `mod_rememberme`,
  `moodle` vs `core`), splitting votes and suggestions and defeating the component
  filter. Component names are now normalised everywhere; an upgrade step merges split
  rows (keeping the one further along the review cycle, moving votes and suggestions)
  and normalises the saved filter.
- Exported language packs now use real lang-pack file names (`moodle.php`,
  `forum.php`, `admin.php`, `block_html.php`) instead of `core.php` / `mod_forum.php`.
- **Remove on the Voting Report reset a translation to the English source.** The
  stored value then went into exports for that language (and could overwrite the real
  translation when installed). It now resets to the installed language pack's value; an
  upgrade step repairs pending rows, and the exporter skips English left in another
  language's row where the installed pack has its own translation.
- The documented way to install an exported pack was wrong (*Language packs* cannot
  install an uploaded zip, and unzipping into the code tree's `lang/` would have replaced
  whole pack files). The docs now describe `moodledata/lang/<lang>_local/` overrides and AMOS.
- Missing capability names (`langcrowd:vote`, `:suggest`, `:admin`) in all three
  language packs; role-editing screens and permission errors showed placeholders.
- The reports and the exporter were only reachable in the admin tree with
  `moodle/site:config`, although they require `local/langcrowd:admin`; managers can
  now reach them. The navbar link is gated on the same capability.
- A vote or the hourly task could overwrite an admin lock/push/revert that landed
  between reading and writing a string; status writes are now conditional on the
  status that was read.
- Approve/Push/Reject act only on suggestions that are still pending, bulk actions
  run in one transaction, and the result message reports skipped rows. Suggestions
  stored before the new text rules are marked *Cannot be applied*.
- Vote counts are recomputed after a privacy deletion, and the hourly task now keeps
  counts accurate even when the threshold is 0 or the string is locked.
- Privacy provider: the metadata declares every exported field, the export names the
  string each vote/suggestion is about (with readable dates), and deletions ignore
  contexts other than the system context.

### Changed

- Removed seven unused language strings; documented uninstalling in the user guides;
  corrected the admin menu location in the documentation (*Plugins → Local plugins*).
- Tests moved to directories matching their namespaces, use PHPUnit attributes, and
  cover the capability checks, the participation gate for suggestions, the serving
  path of the guard, and the new upgrade steps (68 → 116 tests).

## [0.3.2] — 2026-07-18

### Security

- **Stored XSS closed: string values are now resolved entirely server-side.** The
  `get_string_ids` web service accepted the rendered string value from the client
  (`PARAM_RAW`) and stored it as `currentvalue`, which the custom string manager
  serves verbatim through `get_string()` once a string is locked or pushed —
  and Moodle emits language strings unescaped. Any authenticated user with the
  vote capability could therefore seed a script payload against a real string key
  (e.g. `core/ok`) and have it execute for every user, administrators included,
  once the string was promoted. The `value` field has been removed from the web
  service and `currentvalue` is now resolved from the installed language packs on
  the server, exactly as `sourcevalue` already was. An upgrade step recomputes
  `currentvalue` for all pending records and for any locked/pushed record
  containing markup, and deletes unrecoverable rows (unknown key + markup)
  together with their votes and suggestions. As defence in depth, the string
  manager now refuses to serve any promoted value containing HTML markup.
  (Reported by MDL Shield review, 2026-07-17.)

### Fixed

- The language-pack exporter builds its temporary zip inside Moodle's managed
  temp area (`make_request_directory()`) instead of the operating-system temp
  directory, respecting `$CFG` temp configuration on clustered/containerised
  deployments.

### Added

- README *Uninstalling* section: remove the `$CFG->customstringmanager` line from
  `config.php` when uninstalling the plugin.

---

## [0.3.1] — 2026-07-14

### Fixed

- **The "English source" column now really shows the English source.** When a string
  was first registered, the plugin stored the client-submitted value — the string as
  rendered in the voter's current language — as `sourcevalue`. Wherever the target
  language pack (or a promoted translation) already translated the string, the
  "English source" shown in the Voting and Suggestions reports and in the suggestion
  dialog was therefore not English. The English value is now resolved server-side
  from the English language pack at registration time, and an upgrade step
  recomputes `sourcevalue` for all existing records.
- The `get_string_ids` web service no longer registers string keys that do not exist
  in the English language pack (previously any well-formed key sent by a client was
  inserted verbatim).

---

## [0.3.0] — 2026-07-13

Hardening and quality release following a full code review. No database schema
changes; `version.php` is bumped so existing installs receive the update.

### Fixed

- **"Remove" on the Voting Report now actually reverts a string durably.** Previously it
  reset the vote count to zero and the status to *Pending* but left the underlying vote
  rows in place, so the hourly aggregate task (or the next vote) recounted them and
  immediately re-locked the string. Remove now deletes the votes and resets the value to
  the source translation, matching the Approve/Push actions. A regression test asserts a
  reverted string stays pending after the aggregate task runs.

### Security

- **Exporter now emits language files with `var_export()`** instead of hand-rolled
  quote escaping. User-submitted translations containing backslashes or quotes could
  previously produce a corrupt — and potentially code-injecting — `.php` language file.
  Exported packs are now always valid, safe-to-load PHP.
- **`get_string_ids` web service now requires the `local/langcrowd:vote` capability**
  and enforces the enabled/role/language gate. Previously any authenticated user could
  register arbitrary string rows by calling the service directly, regardless of the
  "allowed roles" setting. The number of strings accepted per call is now bounded.
- **"Allowed roles" and "Allowed languages" are now enforced server-side** in all three
  web services, not just used to hide the overlay. The restrictions can no longer be
  bypassed by calling the services directly.
- **Admin report actions (Lock, Remove, Approve, Push, Reject) now use POST buttons**
  with a confirmation dialog instead of GET links, following Moodle convention for
  state-changing requests.

### Changed

- Locked and pushed translations are now also served in **AJAX-rendered content**, not
  only full page loads, so promoted translations appear consistently everywhere.
- Suggestions can no longer be submitted against an already-locked string.
- The suggestion modal is now keyboard-accessible: Escape closes it, Tab focus is
  trapped inside it, and focus returns to the triggering button on close.

### Added

- **Component filter setting** ("Components to enable crowdsourcing for"). Restricts the
  overlay to selected components (empty = all), enforced server-side. Selected components
  are pre-selected by default on the Export page.
- **Opt-in "translate mode".** A floating toggle turns the voting buttons on only when the
  user wants them, instead of annotating every page automatically. Off by default; the
  choice persists across pages. Strings are only registered when the user activates it.
- **"Lock translate mode on" setting** — hides the toggle and keeps the overlay always on
  for everyone who can use it (for sites that want crowdsourcing always active).
- **Touch support.** On touch devices (no hover) the buttons default to always-visible.
- **Undo a vote.** After voting, a short-lived "Undo" affordance withdraws the vote
  (`submit_vote` now accepts `0` to withdraw).
- **Approval progress hint.** The approve button's tooltip shows the current votes vs the
  threshold (e.g. "5/10 approvals").
- **English source shown** alongside the current translation in the suggestion dialog and
  in both admin reports (new "English source" column).
- **Bulk actions** in both reports: select rows and Lock/Remove (Voting) or
  Approve/Push/Reject (Suggestions) in one go.
- **Overview dashboard** (`overview.php`): pending/pushed/locked/total counts, pending
  suggestions, most-voted strings and recent suggestions; it is the plugin's landing page.
- **Export all languages** in one zip (an "All languages" option on the Export page).
- **Full PHPUnit test suite** (`tests/`): exporter (incl. a hostile-value regression
  test), vote thresholding and status transitions, the aggregate task, string
  registration, suggestions, the participation gate, the manager, and the privacy provider.
- `composer.json` for Packagist / Composer installation.
- `.gitignore`.
- User documentation under `docs/` in English and Thai.

### Changed (UI/UX)

- The suggestion dialog now uses Moodle's `core/modal`, so it matches the site theme,
  dark mode and RTL, with a maintained focus trap.
- The hover highlight is drawn as an **outline** rather than a background fill, so it never
  reduces the contrast of the text it surrounds.
- The overlay no longer annotates fixed/sticky regions (navbars, drawers, footers) where
  the buttons used to collide with page chrome.
- The overlay is hidden from users who lack the `local/langcrowd:vote` capability (they
  would previously have hit an error on the first page scan).

### Internal

- `$plugin->release` corrected to `0.3.0`, `$plugin->version` bumped, `$plugin->supported`
  set to `[502, 502]`, maturity raised to `MATURITY_BETA`.
- Report state-transitions consolidated into a tested `\local_langcrowd\manager` class.
- Copyright headers updated to `Adam Jenkins <adam@wisecat.net>`.
- CI matrix aligned to the declared Moodle 5.2 support (PHP 8.3/8.4 — 5.2 requires PHP 8.3).
- Vote-status and string-tracking logic refactored into helper methods (lower
  cyclomatic complexity); all CI checks pass locally (phplint, phpcs, phpdoc, phpmd,
  validate, savepoints, mustache, grunt/eslint, phpunit).

---

## [0.2.1] — 2026-06-17

### Added

- **Admin approve vote locks immediately** setting (off by default). When enabled, an approve vote cast by a site administrator locks the string on the spot, bypassing the configured vote threshold. Reject votes and non-admin users are unaffected.

---

## [0.2.0] — 2026-06-17

### Added

- **Voting Report** (`report_voting.php`) replaces the Approved Strings report. Shows strings across all three statuses — *Pending*, *Locked*, and *Pushed* — in a single view.
- **Sortable columns** on the Voting Report: click any column header to sort ascending; click again to reverse. Current sort direction is indicated by ▲/▼. Sort is preserved when changing filters.
- **Status filter** on the Voting Report: filter to Pending, Locked, or Pushed, or view all at once.
- **"Include strings with no votes" checkbox** on the Voting Report. By default, strings with `votecount = 0` are hidden to keep the view focused on active voting activity. Check the box to show all strings.
- **Lock action** on the Voting Report: admins can manually lock any *Pending* string without waiting for the vote threshold, giving an immediate admin override.
- **Thai translations** for all new strings: `action_lock`, `filter_showzero`, `filter_status`, `report_voting`.

### Changed

- **"Approved Strings" report renamed to "Voting Report"** in the admin menu. The old URL (`report_approved.php`) redirects automatically.
- **"Unlock" button renamed to "Remove"** on the Voting Report to better describe reverting a string to *Pending*.

### Fixed

- **Duplicate-component error** (`Did you remember to make the first column something unique?`) when two or more strings from the same component were locked or pushed. The `load_promoted_strings` query now selects `id` as the first column so Moodle keys the result array by primary key rather than by `component`.

### Internal

- **Eliminated N+1 query in `aggregate_votes` task.** Previously the task ran one `count_records_select` per pending/pushed string (up to thousands per cron run). Now a single LEFT JOIN with `GROUP BY` fetches all vote counts in one query.
- **Lang string files sorted alphabetically** in both `en` and `th`; new strings (`filter_showzero`, `filter_status`, `report_voting`) inserted in correct position.
- All CI checks pass: `phplint`, `validate`, `codechecker` (0 errors), `phpmd`.

---

## [0.1.1] — 2026-06-16

### Added

- **Push to language pack** action on the User Suggestions report. Clicking Push makes the suggestion the active translation immediately (served by the custom string manager, same as a locked string) while leaving the string open for community voting. Votes reset to zero so users cast fresh votes on the new value. Once the vote threshold is reached the string locks automatically — no further admin action needed.
- **Status column** on the Approved Strings report with colour-coded badges: green *Locked* (admin-approved or threshold reached) and blue *Pushed* (live but still accumulating votes). The report now lists both statuses in one view.

### Changed

- **Approve** action on the User Suggestions report now sets `status = 'locked'` directly (previously set to `pending` and relied on voting to lock). This makes Approve a true admin override that bypasses the vote threshold.
- **Unlock** action on the Approved Strings report now purges string caches so the string stops being served immediately.

### Internal

- `string_manager::load_promoted_strings()` fetches `status IN ('locked', 'pushed')` instead of only `locked`.
- `submit_vote` and `aggregate_votes` preserve `pushed` status when votes are below threshold, preventing accidental reversion to `pending`.

---

## [0.1.0] — 2026-06-16

Initial release.

### Added

**Core voting UI**
- Inline tick/cross badge buttons on every plain-text string rendered on screen.
- Buttons hidden on hover (configurable to always-visible via admin setting).
- Configurable highlight colour applied to the string's background on button hover.
- MutationObserver re-annotates content added by reactive frameworks without a full page reload.
- Strings inside `<a>` links are handled by wrapping the entire anchor element so buttons live outside it, preventing Moodle's capture-phase link navigation from firing on button click.

**Suggestion modal**
- Cross button opens an inline modal with a textarea for submitting an alternative translation.
- Submitting a suggestion records a reject vote for the current value simultaneously.

**Vote thresholding**
- Configurable approve-vote threshold; strings that reach it are locked automatically.
- Locked strings are served immediately as the active translation via the custom string manager — no export step needed.
- Aggregate-votes scheduled task runs hourly as a safety net recalculation.

**Admin settings** (`Site administration → Language → Language Crowdsourcing`)
- Master enable/disable switch.
- Optional primary-nav link for site administrators.
- Approval threshold (votes to lock).
- Max strings per page (default 5000; prevents annotation overload on complex pages).
- Button display mode: hover-only or always-visible.
- String highlight colour (colour picker).
- Roles allowed to vote (multiselect; empty = all authenticated users).
- Languages to enable crowdsourcing for (multiselect of installed language packs; empty = all).

**Admin reports**
- *Approved Strings* report — lists locked strings with Unlock action; filterable by language and component.
- *User Suggestions* report — lists pending suggestions with Approve and Reject actions; approving a suggestion immediately replaces the current translation and purges string caches.
- *Export Language Pack* — downloads a `.zip` structured as a standard Moodle language pack, filterable by language, component, and scope (locked-only or all translated).

**String exclusions**
- Buttons excluded from activity names, course index, timeline/overview blocks, buttons, inputs, and other form controls to avoid UI noise.

**Language packs**
- Shipped with full English (`en`) and Thai (`th`) translations for all plugin strings.

**Security**
- All admin actions CSRF-protected via `confirm_sesskey()`.
- All SQL uses Moodle DML parameterised queries.
- All user content in reports escaped with `s()`.
- External functions require login and validate capabilities (`local/langcrowd:vote`, `local/langcrowd:suggest`, `local/langcrowd:admin`).
- Suggestion text cleaned as `PARAM_TEXT` (strips HTML) before storage.

**CI**
- GitHub Actions workflow targeting Moodle 5.1 and 5.2 on PHP 8.2 and 8.3 with PostgreSQL.
- Runs: phplint, validate, codechecker, phpmd, savepoints, phpunit.
