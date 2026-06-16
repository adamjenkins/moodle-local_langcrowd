# local_langcrowd — Language Crowdsourcing for Moodle

A Moodle 5.x local plugin that lets your users collaboratively build and vote on language pack translations directly inside the Moodle interface.

## How it works

When crowdsourcing is active, every language string rendered on screen is annotated with two small inline buttons:

- **✓ (tick)** — the user approves the current translation. Once a string reaches the configured vote threshold it is locked and exported as part of the language pack.
- **✗ (cross)** — the user disagrees with the current translation and is prompted to type an alternative. Their suggestion is queued for admin review.

Buttons disappear for a given string once a user has voted on it, so they are never shown the same string twice.

The language being built is determined by the Moodle session language — switching the interface language switches the target translation language.

---

## Requirements

- Moodle 5.2 or later (version ≥ 2026042000)
- PHP 8.2+
- A writable `config.php` (one line must be added — see Installation)

---

## Installation

### 1. Copy the plugin

```bash
cp -r local_langcrowd /path/to/moodle/public/local/langcrowd
```

Or clone directly:

```bash
cd /path/to/moodle/public/local
git clone https://github.com/yourorg/moodle-local_langcrowd langcrowd
```

### 2. Add the custom string manager to config.php

Open your Moodle `config.php` and add the following line **before** the `require_once` at the end of the file:

```php
$CFG->customstringmanager = '\local_langcrowd\string_manager';
```

This is the mechanism that annotates rendered strings with the data attributes the JavaScript needs to attach voting buttons. The plugin will display a warning on its settings page if this line is missing.

### 3. Run the Moodle upgrade

```bash
sudo -u www-data php admin/cli/upgrade.php --non-interactive
```

Or visit **Site administration → Notifications** in your browser.

### 4. Enable the plugin

Go to **Site administration → Language → Language Crowdsourcing** and tick **Enable crowdsourcing**.

---

## Admin settings

| Setting | Description | Default |
|---|---|---|
| Enable crowdsourcing | Master on/off switch. Disabling hides all buttons and stops string annotation without removing the config.php line. | Off |
| Approval threshold | Number of approve votes required to lock a string into the language pack. | 10 |

---

## Admin reports

Both reports are linked from the settings page and are accessible at **Site administration → Language → Language Crowdsourcing**.

### Approved Strings

Lists all strings whose vote count has reached the threshold (status: *locked*). Filterable by component and language. Each row has an **Unlock** action to reset the string back to *pending* if a correction is needed.

### User Suggestions

Lists all pending alternative translations submitted by users. Each row shows the current translation alongside the suggestion and who submitted it. Available actions:

- **Promote to active** — replaces the string's current translation with the suggestion and resets the vote count to zero so the community can re-vote on the improved text.
- **Reject** — dismisses the suggestion.

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

A task named **Aggregate crowdsourced votes** runs hourly to recalculate vote totals and apply the threshold lock as a safety net. It can be triggered manually:

```bash
sudo -u www-data php admin/cli/scheduled_task.php \
    --execute='\local_langcrowd\task\aggregate_votes'
```

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
../moodle-plugin-ci/bin/moodle-plugin-ci savepoints -m "$MOODLE_DIR" ./  # if db/upgrade.php exists
```

### Running unit tests

```bash
sudo -u www-data php admin/cli/phpunit.php --init
sudo -u www-data vendor/bin/phpunit --testsuite local_langcrowd
```

---

## License

GNU General Public License v3 or later — see [COPYING](https://www.gnu.org/licenses/gpl-3.0.html).
