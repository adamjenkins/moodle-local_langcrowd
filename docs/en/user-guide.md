# Language Crowdsourcing — User Guide (English)

This guide explains how to use the **Language Crowdsourcing** plugin
(`local_langcrowd`) both as a contributor who votes on translations and as an
administrator who manages and exports them.

---

## 1. What the plugin does

Language Crowdsourcing lets your users improve Moodle's interface translations
without leaving Moodle. When it is switched on, a floating **Improve translations**
button appears. Turn it on and most short pieces of on-screen text gain two small
buttons — so nothing changes until you choose to help:

- **✓ (tick)** — "this translation is good." When enough people approve a string,
  it locks in and is served to everyone.
- **✗ (cross)** — "this could be better." You are asked to type a replacement,
  which is sent to an administrator for review.

The language you are helping with is simply your current Moodle interface
language. Switch your language and you switch which translation you are voting on.

---

## 2. For contributors

### Turning on translate mode

You need to be logged in (guests can't vote). Click the **Improve translations**
button in the bottom-right corner to switch translate mode on; click it again to
switch off. Your choice is remembered as you move between pages. If you don't see
the button at all, crowdsourcing may be limited to certain roles, languages or
components on your site — ask your administrator.

Once translate mode is on, the buttons appear when you move your mouse over a
piece of text (or all the time, if your site is set that way, and always on touch
screens).

### Approving a translation

Hover over a phrase and click the green **✓**. Its tooltip shows how close the
phrase is to being locked in (for example "5/10 approvals"). Your vote is recorded
and the buttons for that phrase disappear. Changed your mind? A short-lived
**Undo** link appears for a few seconds so you can withdraw the vote.

### Suggesting a better translation

Click the red **✗**. A dialog opens showing the English source and the current
text, with a box already filled in with the current translation — edit it and click
**Submit suggestion**. Some texts contain placeholders in curly brackets, such as
`{$a->days}`, where Moodle fills in a value (the number you see on the page); keep each
placeholder exactly as it is, in whatever position suits your language. Your suggestion goes to an administrator, and a "this needs
work" vote is recorded at the same time. Press **Esc** or **Cancel** to close
without submitting.

A few characters can't be used in a translation, because Moodle shows language
strings in places where they could break the page: angle brackets (`<` `>`),
backslashes, backticks and HTML codes such as `&quot;`. Ordinary quotes are fine —
straight quotes are turned into typographic ones for you (`'` becomes `’`). A
suggestion can be up to 4096 characters long. If you suggest again for the same
phrase before an administrator has looked at it, your new suggestion replaces the
earlier one.

### What happens next

- Approved strings that reach the site's vote threshold are **locked** and shown to
  everyone automatically.
- Your suggestions are reviewed by an administrator, who may accept them (making
  them live immediately) or decline them.

You never see the same phrase's buttons twice once you have voted on it.

---

## 3. For administrators

All administration lives under **Site administration → Plugins → Local plugins →
Language Crowdsourcing**. The reports and the exporter are available to anyone with
the *Manage Language Crowdsourcing* capability (managers by default); the settings
page needs full site administration rights.

### 3.1 First-time setup

1. Add one line to your `config.php` (before the final `require_once`):

   ```php
   $CFG->customstringmanager = '\local_langcrowd\string_manager';
   ```

   The settings page shows a green banner when this is active and a warning if it
   is missing.

2. Go to **Language Crowdsourcing Settings** and tick **Enable crowdsourcing**.

### 3.2 Settings reference

| Setting | What it does |
|---|---|
| Enable crowdsourcing | Master on/off switch. |
| Lock translate mode on | Hides the floating "Improve translations" toggle and keeps the overlay always on for everyone who can use it (instead of each user turning it on themselves). |
| Show admin link in navbar | Adds a shortcut to the plugin in the top navigation, for users who can manage Language Crowdsourcing. |
| Admin approve vote locks immediately | If on, an approve vote by a site admin locks the string at once. |
| Approval threshold | How many approve votes lock a string in (default 10). |
| Max strings per page | Upper limit on annotated strings per page (default 5000). |
| Button display mode | Show buttons on hover only, or always (touch devices are always "always"). |
| String highlight colour | Colour of the outline drawn around a phrase on hover. |
| Roles allowed to vote | Restrict voting to specific roles. Empty = all logged-in users. **Enforced server-side.** |
| Languages to enable crowdsourcing for | Restrict the overlay to specific languages. Empty = all. |
| Components to enable crowdsourcing for | Restrict the overlay to specific components (e.g. `mod_forum`, `core`). Empty = all. Every installed component is listed, including ones nobody has voted on yet. |

### 3.3 Overview

The **Overview** page is the plugin's dashboard. It shows how many strings are
pending, pushed and locked, how many strings are tracked in total, and how many
suggestions are waiting — plus the most-voted strings still open for voting and the
most recent suggestions, with quick links into the reports and the exporter.

### 3.4 Voting Report

Lists every string that has voting activity, across three statuses:

- **Pending** (grey) — open for voting, not yet promoted.
- **Pushed** (blue) — an accepted suggestion served live, still open for voting.
- **Locked** (green) — settled and served as the active translation.

The table shows the English source next to the current translation. You can filter
by language, component and status, tick **Include strings with no votes** to see
everything, and click any column header to sort. Each row has an action button
(with a confirmation prompt), and you can act on several rows at once by ticking
their checkboxes and using the action bar under the table:

- **Lock** — immediately lock a pending string without waiting for the threshold.
- **Remove** — revert a locked/pushed string to pending, resetting its votes.

### 3.5 User Suggestions

Lists pending suggestions from users, showing the English source, the current
translation, the suggestion and who submitted it. Each action can be applied per
row or in bulk (tick the checkboxes and use the action bar):

- **Approve** — make the suggestion the active translation and lock it (resets votes).
- **Push to language pack** — serve it live now but keep it open for community
  voting; it locks automatically once the threshold is reached.
- **Reject** — dismiss the suggestion, leaving the current translation unchanged.

A suggestion marked **Cannot be applied** contains characters that are not allowed in
a translation (it was submitted before these rules existed). Approve and Push skip it;
reject it instead. After a bulk action the message says how many rows were skipped.

### 3.6 Exporting a language pack

Open **Export Language Pack**, choose a single language or **All languages**,
optionally filter by component (the components chosen in the *Components to enable
crowdsourcing for* setting are pre-selected for you), choose **Locked strings only**
or **All strings with translations**, and click **Download language pack**. You
receive a `.zip` in Moodle's language-pack file format. It contains **only the
crowd-sourced strings**, so never copy it over an installed language pack. To use it on
another site, copy each `<lang>/<file>.php` into `moodledata/lang/<lang>_local/` (the
same place *Language customisation* saves to; merge with any file already there) and
purge caches; or contribute the translations upstream through AMOS
(lang.moodle.org). *Language packs* in site administration cannot install this zip. A
translation containing characters that are not allowed (see section 2)
is left out of the export unless it is exactly what the installed language pack
already says.

---

## 4. How locking and serving work

- Locked and pushed translations are served immediately by the plugin — you do not
  need to export or install a pack for them to take effect on your own site.
- Exporting is only needed if you want to move the translations to another site or
  contribute them upstream.

---

## 5. Privacy

The plugin stores, per user, the votes you cast and the suggestions you submit.
This data is covered by Moodle's privacy (GDPR) tools: it is included in data
exports and removed by data-deletion requests.

---

## 6. Uninstalling

Uninstall the plugin under **Site administration → Plugins → Plugins overview**, then
remove the `$CFG->customstringmanager` line from your `config.php`. Moodle keeps
working if the line is left behind (it falls back to its standard string manager
and logs a debugging notice), but the line points at a class that no longer exists
and should not stay in place. Translations served by the plugin stop appearing once
it is uninstalled; export them first if you want to keep them as a language pack.
