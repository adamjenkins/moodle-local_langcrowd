# local_langcrowd — Language Crowdsourcing for Moodle

A Moodle 5.2+ local plugin that lets your users collaboratively build and vote on language pack translations directly inside the Moodle interface — no external tools required.

## How it works

When crowdsourcing is active, every language string rendered on screen gets two small inline badge buttons:

- **✓ (tick)** — the user approves the current translation. Once a string reaches the configured vote threshold it is locked and served immediately as the active translation.
- **✗ (cross)** — the user disagrees with the current translation and is prompted to type an alternative. Their suggestion is queued for admin review.

After voting on a string, the buttons disappear for that user so they are never shown the same string twice.

The target language is the user's current Moodle interface language. Switching interface language switches the translation target automatically.

Buttons can be restricted to specific roles and to specific installed language packs via admin settings.

---

## Requirements

- Moodle 5.2 or later (version ≥ 2026042000)
- PHP 8.2+
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
git clone https://github.com/yourorg/moodle-local_langcrowd langcrowd
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

---

## Admin settings

Navigate to **Site administration → Language → Language Crowdsourcing → Settings**.

| Setting | Description | Default |
|---|---|---|
| Enable crowdsourcing | Master on/off switch. | Off |
| Show admin link in navbar | Adds a "Language Crowdsourcing" link to the primary nav (admins only). | Off |
| Approval threshold | Approve-votes needed to lock a string in. | 10 |
| Max strings per page | Cap on annotated strings per page. Raise for complex admin pages. | 5000 |
| Button display mode | `Hover` hides buttons until the user mouses over a string; `Always` keeps them visible. | Hover |
| String highlight colour | Background colour applied to a string on button hover. | `#fff3cd` |
| Roles allowed to vote | Restrict voting to specific roles. Empty = all logged-in users. | (all) |
| Languages to enable crowdsourcing for | Restrict the overlay to specific installed language packs. Empty = all languages. | (all) |

---

## Admin reports

Accessible at **Site administration → Language → Language Crowdsourcing**.

### Voting Report

Shows all strings across the three active statuses in one view:

| Status | Badge | Meaning |
|---|---|---|
| Pending | grey | Seen by users and open for voting; not yet promoted. |
| Pushed | blue | Suggestion accepted by admin and served immediately; still open for community voting. |
| Locked | green | Locked in as the active translation (admin override or vote threshold reached). |

**Filters:** Language, Component, Status, and an *Include strings with no votes* checkbox (hidden by default so the view focuses on strings with voting activity).

**Sortable columns:** click any column header to sort; click again to reverse direction.

**Actions:**
- **Lock** (Pending rows) — admin override that locks the string immediately without waiting for the vote threshold.
- **Remove** (Locked / Pushed rows) — reverts the string to *Pending*, resets the vote count to zero, and stops serving it as the active translation.

Both locked and pushed strings are **served immediately** as the active translation without requiring an export step — the custom string manager intercepts `get_string()` and returns the stored value.

### User Suggestions

Lists all pending alternative translations submitted by users. Each row shows the current translation alongside the suggestion and who submitted it. Available actions:

- **Approve** — admin-locks the string immediately (*locked* status), resets the vote count to zero, and purges caches. Use when you are confident the suggestion is correct and want no further community input.
- **Push to language pack** — makes the suggestion live right now (*pushed* status) while keeping the string open for community voting. Votes reset to zero so users vote fresh on the new value. Once votes cross the threshold the string locks automatically. Use when you want the improvement served immediately but still want community validation.
- **Reject** — dismisses the suggestion without changing the active translation.

---

## Exporting language packs

Go to **Site administration → Language → Language Crowdsourcing → Export Language Pack**.

Select a language, optionally filter by component, choose whether to export locked strings only or all strings with translations, then click **Download language pack**. You receive a `.zip` file structured as a standard Moodle language pack:

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

---

## Architecture notes

- **Custom string manager** (`classes/string_manager.php`): extends `core_string_manager_standard`. Intercepts `get_string()` to (a) serve promoted/locked translations from DB without requiring an export, and (b) collect plain-text strings for the footer hook. Filters out parameterised strings, HTML-containing strings, strings with embedded newlines, and strings shorter than 3 characters.

- **Footer hook** (`classes/hook_callbacks.php`): injects `window.langcrowdInit` JSON and schedules the `local_langcrowd/voting` AMD call. Respects enabled/role/language filters before injecting.

- **AMD voting module** (`amd/src/voting.js`): DOM TreeWalker scans text nodes and calls `get_string_ids` web service. Strings inside `<a>` links are handled by wrapping the entire anchor element so buttons live outside it (avoids Moodle's capture-phase link navigation). A MutationObserver re-annotates content added by reactive frameworks. Activity names and form controls are excluded.

- **Web services**: three AJAX endpoints — `get_string_ids` (register and look up strings), `submit_vote`, `submit_suggestion` — all require login and appropriate capabilities.

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

---

## License

GNU General Public License v3 or later — see [COPYING](https://www.gnu.org/licenses/gpl-3.0.html).
