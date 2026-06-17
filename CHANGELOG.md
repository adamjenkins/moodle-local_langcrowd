# Changelog

All notable changes to this project will be documented in this file.

The format follows [Keep a Changelog](https://keepachangelog.com/en/1.1.0/).
Version numbers follow [Semantic Versioning](https://semver.org/).

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
