# local_langcrowd — Language Crowdsourcing for Moodle

A Moodle 5.2+ local plugin that lets your users collaboratively build and vote on language pack translations directly inside the Moodle interface — no external tools required.

## How it works

When crowdsourcing is enabled, logged-in users see a floating **Improve translations** button. Turning it on ("translate mode") annotates the language strings on screen with two small inline badge buttons — so the buttons only appear when the user opts in, not on every page automatically. The choice is remembered for the rest of the session.

- **✓ (tick)** — the user approves the current translation. Its tooltip shows the progress towards the lock threshold (e.g. "5/10 approvals"). Once a string reaches the configured vote threshold it is locked and served immediately as the active translation.
- **✗ (cross)** — the user disagrees with the current translation and is prompted (in a themed dialog showing the English source) to type an alternative. Their suggestion is queued for admin review.

After voting on a string the buttons disappear, but a short-lived **Undo** link lets the user withdraw the vote. On touch devices (which have no hover) the buttons are always visible.

The target language is the user's current Moodle interface language. Switching interface language switches the translation target automatically.

The overlay can be restricted to specific roles, specific installed language packs, and specific components via admin settings.

---

## Requirements

- Moodle 5.2 or later (version ≥ 2026042000)
- PHP 8.3+ (Moodle 5.2 requires PHP 8.3)
- A writable `config.php` (one line must be added — see Installation)

---

## Installation

### 1. Copy the plugin

```bash
cp -r moodle-local_langcrowd /path/to/moodle/local/langcrowd
```

Or clone directly:

```bash
cd /path/to/moodle/local
git clone https://github.com/adamjenkins/moodle-local_langcrowd langcrowd
```

Or install with Composer (the [`moodle/composer-installer`](https://github.com/micaherne/moodle-composer-installer)
places it under `local/langcrowd` automatically):

```bash
composer require adamjenkins/moodle-local_langcrowd
```

### 2. Add the custom string manager to config.php

Open your Moodle `config.php` and add the following line **before** the `require_once` at the end of the file:

```php
$CFG->customstringmanager = '\local_langcrowd\string_manager';
```

This intercepts every `get_string()` call during web requests so the footer hook knows which strings appear on each page. The plugin displays a warning on its settings page if this line is missing.

### 3. Run the Moodle upgrade

```bash
sudo -u www-data php admin/cli/upgrade.php --non-interactive
```

Or visit **Site administration → Notifications** in your browser.

### 4. Enable the plugin

Go to **Site administration → Language → Language Crowdsourcing** and tick **Enable crowdsourcing**.

### Uninstalling

When uninstalling the plugin, also remove the `$CFG->customstringmanager` line
from `config.php`. Moodle degrades gracefully if it is left behind (it logs a
debugging notice and falls back to the standard string manager), but the stale
line references a class that no longer exists and should not stay in place.

---

## Admin settings

Navigate to **Site administration → Language → Language Crowdsourcing → Settings**.

| Setting | Description | Default |
|---|---|---|
| Enable crowdsourcing | Master on/off switch. | Off |
| Lock translate mode on | Hides the floating "Improve translations" toggle and keeps the overlay always on for everyone who can use it. | Off |
| Show admin link in navbar | Adds a "Language Crowdsourcing" link to the primary nav (admins only). | Off |
| Admin approve vote locks immediately | When enabled, an approve vote from a site administrator locks the string at once, bypassing the vote threshold. Reject votes and non-admin voters are unaffected. | Off |
| Approval threshold | Approve-votes needed to lock a string in. | 10 |
| Max strings per page | Cap on annotated strings per page. Raise for complex admin pages. | 5000 |
| Button display mode | `Hover` hides buttons until the user mouses over a string; `Always` keeps them visible. Ignored on touch devices, where buttons are always visible. | Hover |
| String highlight colour | Colour of the outline drawn around a string on button hover. | `#fff3cd` |
| Roles allowed to vote | Restrict voting to specific roles. Empty = all logged-in users. | (all) |
| Languages to enable crowdsourcing for | Restrict the overlay to specific installed language packs. Empty = all languages. | (all) |
| Components to enable crowdsourcing for | Restrict the overlay to specific components (e.g. `mod_forum`, `core`). Empty = all. The list is built from strings seen so far; clear it to let new components be discovered again. | (all) |

> The overlay is only shown to users who hold the `local/langcrowd:vote` capability, and the role/language/component restrictions are enforced both in the UI and in the web services.

---

## Admin reports

Accessible at **Site administration → Language → Language Crowdsourcing**.

### Overview

The landing page. Shows at-a-glance counts (Pending / Pushed / Locked / total strings tracked / pending suggestions), the most-voted strings still open for voting, and the most recent suggestions, with quick links to the reports and the exporter.

### Voting Report

Shows all strings across the three active statuses in one view:

| Status | Badge | Meaning |
|---|---|---|
| Pending | grey | Seen by users and open for voting; not yet promoted. |
| Pushed | blue | Suggestion accepted by admin and served immediately; still open for community voting. |
| Locked | green | Locked in as the active translation (admin override or vote threshold reached). |

**Filters:** Language, Component, Status, and an *Include strings with no votes* checkbox (hidden by default so the view focuses on strings with voting activity).

**Columns:** the English source is shown alongside the current translation. Click any column header to sort; click again to reverse direction.

**Actions** (per row, or in bulk by ticking the checkboxes and using the action bar below the table):
- **Lock** (Pending rows) — admin override that locks the string immediately without waiting for the vote threshold.
- **Remove** (Locked / Pushed rows) — reverts the string to *Pending*, resets the vote count to zero, deletes the accumulated votes, and stops serving it as the active translation.

Both locked and pushed strings are **served immediately** as the active translation without requiring an export step — the custom string manager intercepts `get_string()` and returns the stored value.

### User Suggestions

Lists all pending alternative translations submitted by users. Each row shows the English source and the current translation alongside the suggestion and who submitted it. Actions are available per row or in bulk (tick the checkboxes and use the action bar):

- **Approve** — admin-locks the string immediately (*locked* status), resets the vote count to zero, and purges caches. Use when you are confident the suggestion is correct and want no further community input.
- **Push to language pack** — makes the suggestion live right now (*pushed* status) while keeping the string open for community voting. Votes reset to zero so users vote fresh on the new value. Once votes cross the threshold the string locks automatically. Use when you want the improvement served immediately but still want community validation.
- **Reject** — dismisses the suggestion without changing the active translation.

---

## Exporting language packs

Go to **Site administration → Language → Language Crowdsourcing → Export Language Pack**.

Select a language (or **All languages** to export every language in one archive), optionally filter by component — the components chosen in the *Components to enable crowdsourcing for* setting are pre-selected by default — choose whether to export locked strings only or all strings with translations, then click **Download language pack**. You receive a `.zip` file structured as a standard Moodle language pack:

```
{lang}/
  {component}.php
  ...
```

Install the zip via **Site administration → Language → Language packs → Install / update** or unzip it into your Moodle `lang/` directory.

---

## Capabilities

| Capability | Default role | Description |
|---|---|---|
| `local/langcrowd:vote` | Authenticated user | Cast approve/reject votes |
| `local/langcrowd:suggest` | Authenticated user | Submit alternative translations |
| `local/langcrowd:admin` | Manager | Access reports and export |

---

## Scheduled task

A task named **Aggregate crowdsourced votes** runs hourly to recalculate vote totals and apply the threshold lock. It can be triggered manually:

```bash
sudo -u www-data php admin/cli/scheduled_task.php \
    --execute='\local_langcrowd\task\aggregate_votes'
```

---

## Shipped language packs

The plugin ships with translations for:

| Language | Code |
|---|---|
| English | `en` |
| Thai | `th` |
| Japanese | `ja` |

---

## Architecture notes

- **Custom string manager** (`classes/string_manager.php`): extends `core_string_manager_standard`. Intercepts `get_string()` to (a) serve promoted/locked translations from DB without requiring an export (in web *and* AJAX contexts), and (b) collect plain-text strings for the footer hook. Filters out parameterised strings, HTML-containing strings, strings with embedded newlines, strings shorter than 3 characters, and components excluded by the component filter.

- **Access gate** (`classes/access.php`): the single source of truth for the enabled / role / language / component checks, used by both the footer hook and the web services so the restrictions can't be bypassed by calling the services directly.

- **Footer hook** (`classes/hook_callbacks.php`): injects `window.langcrowdInit` JSON and schedules the `local_langcrowd/voting` AMD call. Only runs when the user passes the access gate and holds `local/langcrowd:vote`.

- **AMD voting module** (`amd/src/voting.js`): renders the opt-in "translate mode" toggle. On activation it calls the `get_string_ids` web service, then a DOM TreeWalker scans text nodes and annotates matches. Strings inside `<a>` links are handled by wrapping the entire anchor element so buttons live outside it (avoids Moodle's capture-phase link navigation). A MutationObserver re-annotates content added by reactive frameworks. Activity names, form controls and fixed/sticky regions (navbars, drawers, footers) are excluded. The suggestion dialog uses `core/modal` so it inherits the site theme, dark mode and RTL.

- **Manager** (`classes/manager.php`): centralises the admin state transitions (lock / revert / apply-suggestion / reject, plus bulk variants) so the report scripts stay thin and every "reset" also clears the underlying vote rows.

- **Web services**: three AJAX endpoints — `get_string_ids` (register and look up strings), `submit_vote` (approve `1`, reject `-1`, withdraw `0`), `submit_suggestion` — all require login and the appropriate capability, and re-check the access gate.

---

## Development

### Running CI checks

```bash
cd /path/to/plugin/repo
MOODLE_DIR=/path/to/moodle

../moodle-plugin-ci/bin/moodle-plugin-ci phplint ./
../moodle-plugin-ci/bin/moodle-plugin-ci validate -m "$MOODLE_DIR" ./
../moodle-plugin-ci/bin/moodle-plugin-ci codechecker ./
../moodle-plugin-ci/bin/moodle-plugin-ci phpmd ./
../moodle-plugin-ci/bin/moodle-plugin-ci savepoints ./
```

### Running unit tests

```bash
cd "$MOODLE_DIR"
sudo -u www-data php admin/cli/phpunit.php --init
sudo -u www-data vendor/bin/phpunit --testsuite local_langcrowd
```

---

## Security

- All admin actions are CSRF-protected via Moodle's `confirm_sesskey()`.
- All SQL uses parameterised queries via the Moodle DML API.
- All user-supplied content displayed in reports is escaped with `s()`.
- External functions validate parameters with Moodle type constants and require appropriate capabilities.
- Suggestion text is cleaned as `PARAM_TEXT` (strips HTML tags) before storage.
- Stored string values (`sourcevalue` and `currentvalue`) are resolved server-side
  from the language packs — clients cannot submit string values. As defence in
  depth, the string manager never serves a promoted value containing HTML markup.

---

## Documentation

End-user guides are provided in the [`docs/`](docs/) folder:

- English — [`docs/en/user-guide.md`](docs/en/user-guide.md)
- ไทย (Thai) — [`docs/th/user-guide.md`](docs/th/user-guide.md)
- 日本語 (Japanese) — [`docs/ja/user-guide.md`](docs/ja/user-guide.md)

Developer/maintenance notes for this repository (review reports, UI/UX
recommendations) live outside the repo, in the workspace `dev-docs/` tree.

## Testing

```bash
cd /path/to/moodle
php public/admin/tool/phpunit/cli/init.php
vendor/bin/phpunit --testsuite local_langcrowd_testsuite
```

The plugin ships a full PHPUnit suite covering the exporter, vote thresholding and
status transitions, the scheduled task, the web services, the participation gate, and
the privacy provider.

## License

GNU General Public License v3 or later — see [COPYING](https://www.gnu.org/licenses/gpl-3.0.html).
