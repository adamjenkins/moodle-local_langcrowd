<?php
// This file is part of Moodle - http://moodle.org/
//
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.
//
// Moodle is distributed in the hope that it will be useful,
// but WITHOUT ANY WARRANTY; without even the implied warranty of
// MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
// GNU General Public License for more details.
//
// You should have received a copy of the GNU General Public License
// along with Moodle.  If not, see <http://www.gnu.org/licenses/>.

/**
 * English language strings for local_langcrowd.
 *
 * @package    local_langcrowd
 * @copyright  2026 hama.history@gmail.com
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

// Core.
$string['pluginname'] = 'Language Crowdsourcing';

// Privacy.
$string['privacy:metadata'] = 'The Language Crowdsourcing plugin stores votes and suggestions submitted by users to help build community language packs.';
$string['privacy:metadata:local_langcrowd_votes'] = 'Records each user\'s vote (approve or reject) for individual language strings.';
$string['privacy:metadata:local_langcrowd_votes:userid'] = 'The ID of the user who cast the vote.';
$string['privacy:metadata:local_langcrowd_votes:vote'] = 'The vote value: 1 for approve, -1 for reject.';
$string['privacy:metadata:local_langcrowd_votes:timecreated'] = 'The time the vote was recorded.';
$string['privacy:metadata:local_langcrowd_suggestions'] = 'Records alternative translations suggested by users.';
$string['privacy:metadata:local_langcrowd_suggestions:userid'] = 'The ID of the user who submitted the suggestion.';
$string['privacy:metadata:local_langcrowd_suggestions:suggestion'] = 'The suggested alternative translation text.';
$string['privacy:metadata:local_langcrowd_suggestions:timecreated'] = 'The time the suggestion was submitted.';

// Admin settings.
$string['settings'] = 'Language Crowdsourcing Settings';
$string['settings_enabled'] = 'Enable crowdsourcing';
$string['settings_enabled_desc'] = 'Enable or disable the crowdsourcing overlay on all Moodle pages. Note: the custom string manager must also be configured in config.php for string annotation to work.';
$string['settings_threshold'] = 'Approval threshold';
$string['settings_threshold_desc'] = 'Number of approve votes required to lock in a translated string.';
$string['settings_maxstrings'] = 'Max strings per page';
$string['settings_maxstrings_desc'] = 'Maximum number of strings that can have voting buttons on a single page. Increase for complex admin pages; decrease to limit UI clutter and payload size. Default: 5000.';
$string['settings_showmode'] = 'Button display mode';
$string['settings_showmode_desc'] = 'Controls when the tick/cross voting buttons are visible beside translated strings.';
$string['settings_showmode_hover'] = 'Show on mouseover only';
$string['settings_showmode_always'] = 'Always visible';
$string['settings_allowed_roles'] = 'Roles allowed to vote';
$string['settings_allowed_roles_desc'] = 'Select which roles can see voting buttons and submit votes or suggestions. Leave empty to allow all authenticated users. A user needs the role in any context (system, category, course, etc.) to qualify.';
$string['settings_showadminlink'] = 'Show admin link in navbar';
$string['settings_showadminlink_desc'] = 'When enabled, a "Language Crowdsourcing" link appears in the primary navigation bar to the right of "Site administration", visible to site administrators only.';
$string['settings_highlightcolor'] = 'String highlight colour';
$string['settings_highlightcolor_desc'] = 'Background colour applied to a string when hovering over its voting buttons. Use any valid hex colour value.';
$string['settings_allowed_langs'] = 'Languages to enable crowdsourcing for';
$string['settings_allowed_langs_desc'] = 'Select which installed language packs the voting overlay should be active for. Leave empty to enable for all languages. Users whose interface language is not in this list will not see voting buttons.';
$string['settings_configphp_notice'] = 'To activate string annotation, add the following line to your config.php file:';
$string['settings_stringmanager_warning'] = 'Warning: the custom string manager is not active. String annotation will not work until the config.php change is applied.';
$string['settings_stringmanager_active'] = 'The custom string manager is active.';

// Navigation / report links.
$string['report_approved'] = 'Approved Strings';
$string['report_suggestions'] = 'User Suggestions';
$string['export'] = 'Export Language Pack';

// Report table columns.
$string['col_component'] = 'Component';
$string['col_stringkey'] = 'String key';
$string['col_sourcevalue'] = 'English source';
$string['col_currentvalue'] = 'Translation';
$string['col_votecount'] = 'Votes';
$string['col_datelocked'] = 'Date locked';
$string['col_submittedby'] = 'Submitted by';
$string['col_date'] = 'Date';
$string['col_actions'] = 'Actions';
$string['col_suggestion'] = 'Suggested translation';

// Report actions.
$string['action_unlock'] = 'Unlock';
$string['action_promote'] = 'Approve';
$string['action_reject'] = 'Reject';
$string['action_unlock_confirm'] = 'Are you sure you want to unlock this string and reset its vote count?';
$string['action_promote_confirm'] = 'Are you sure you want to approve this suggestion? It will become the active translation immediately and reset votes to zero.';
$string['action_reject_confirm'] = 'Are you sure you want to reject this suggestion?';

// Export page.
$string['export_language'] = 'Language';
$string['export_components'] = 'Components';
$string['export_components_desc'] = 'Select which components to export. Leave blank to export all.';
$string['export_scope'] = 'Scope';
$string['export_scope_locked'] = 'Locked strings only';
$string['export_scope_all'] = 'All strings with translations';
$string['export_download'] = 'Download language pack';
$string['export_nodata'] = 'No strings match the selected criteria.';

// Voting buttons (loaded via JS strings_for_js).
$string['btn_approve'] = 'Approve this translation';
$string['btn_suggest'] = 'Suggest an alternative';
$string['modal_suggest_title'] = 'Suggest an alternative translation';
$string['modal_original_label'] = 'Current translation';
$string['modal_suggestion_label'] = 'Your suggested translation';
$string['modal_submit'] = 'Submit suggestion';
$string['modal_cancel'] = 'Cancel';
$string['vote_thanks'] = 'Thank you for your vote.';
$string['suggestion_thanks'] = 'Thank you for your suggestion.';

// Status labels.
$string['status_pending'] = 'Pending';
$string['status_locked'] = 'Locked';
$string['status_promoted'] = 'Promoted';
$string['status_rejected'] = 'Rejected';

// Filters.
$string['filter_language'] = 'Language';
$string['filter_component'] = 'Component';
$string['filter_apply'] = 'Apply filters';
$string['filter_all'] = 'All';

// Scheduled task.
$string['task_aggregate_votes'] = 'Aggregate crowdsourced votes';
