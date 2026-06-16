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
 * User suggestions report for local_langcrowd.
 *
 * @package    local_langcrowd
 * @copyright  2026 hama.history@gmail.com
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require(__DIR__ . '/../../config.php');

require_login();
$context = context_system::instance();
require_capability('local/langcrowd:admin', $context);

$lang         = optional_param('lang', '', PARAM_LANG);
$component    = optional_param('component', '', PARAM_NOTAGS);
$action       = optional_param('action', '', PARAM_ALPHA);
$suggestionid = optional_param('suggestionid', 0, PARAM_INT);
$page         = optional_param('page', 0, PARAM_INT);
$perpage      = 50;

$baseurl = new moodle_url(
    '/local/langcrowd/report_suggestions.php',
    array_filter(['lang' => $lang, 'component' => $component])
);

$PAGE->set_url($baseurl);
$PAGE->set_context($context);
$PAGE->set_title(get_string('report_suggestions', 'local_langcrowd'));
$PAGE->set_heading(get_string('report_suggestions', 'local_langcrowd'));
$PAGE->set_pagelayout('admin');

// Handle promote / push / reject actions.
if ($suggestionid && confirm_sesskey()) {
    $suggestion = $DB->get_record('local_langcrowd_suggestions', ['id' => $suggestionid], '*', MUST_EXIST);
    $now        = time();

    if ($action === 'promote') {
        // Admin-approve: replace currentvalue, lock the string, reset the vote cycle.
        $DB->update_record('local_langcrowd_strings', (object)[
            'id'           => $suggestion->stringid,
            'currentvalue' => $suggestion->suggestion,
            'votecount'    => 0,
            'status'       => 'locked',
            'timemodified' => $now,
        ]);
        // Clear all existing votes so users can re-vote if the string is ever unlocked.
        $DB->delete_records('local_langcrowd_votes', ['stringid' => $suggestion->stringid]);
        $DB->set_field('local_langcrowd_suggestions', 'status', 'promoted', ['id' => $suggestionid]);
        // Purge string caches so the new value is served immediately.
        get_string_manager()->reset_caches();
        redirect($baseurl, get_string('status_promoted', 'local_langcrowd'), null, \core\output\notification::NOTIFY_SUCCESS);
    } else if ($action === 'push') {
        // Push: serve the suggestion immediately but keep the string open for voting.
        // Votes are reset so users cast fresh votes on the new value.
        $DB->update_record('local_langcrowd_strings', (object)[
            'id'           => $suggestion->stringid,
            'currentvalue' => $suggestion->suggestion,
            'votecount'    => 0,
            'status'       => 'pushed',
            'timemodified' => $now,
        ]);
        $DB->delete_records('local_langcrowd_votes', ['stringid' => $suggestion->stringid]);
        $DB->set_field('local_langcrowd_suggestions', 'status', 'promoted', ['id' => $suggestionid]);
        get_string_manager()->reset_caches();
        redirect($baseurl, get_string('status_pushed', 'local_langcrowd'), null, \core\output\notification::NOTIFY_SUCCESS);
    } else if ($action === 'reject') {
        $DB->set_field('local_langcrowd_suggestions', 'status', 'rejected', ['id' => $suggestionid]);
        $DB->set_field('local_langcrowd_suggestions', 'timemodified', $now, ['id' => $suggestionid]);
        redirect($baseurl, get_string('status_rejected', 'local_langcrowd'), null, \core\output\notification::NOTIFY_SUCCESS);
    }
}

// Build query joining suggestions to strings and users.
$where     = "sug.status = 'pending'";
$sqlparams = [];
if ($lang) {
    $where .= ' AND str.lang = :lang';
    $sqlparams['lang'] = $lang;
}
if ($component) {
    $where .= ' AND str.component = :component';
    $sqlparams['component'] = $component;
}

$countsql = "SELECT COUNT(sug.id)
               FROM {local_langcrowd_suggestions} sug
               JOIN {local_langcrowd_strings} str ON str.id = sug.stringid
              WHERE $where";
$total = $DB->count_records_sql($countsql, $sqlparams);

$sql = "SELECT sug.id, sug.stringid, sug.suggestion, sug.timecreated,
               str.component, str.stringkey, str.lang, str.currentvalue,
               u.username, u.firstname, u.lastname,
               u.firstnamephonetic, u.lastnamephonetic, u.middlename, u.alternatename
          FROM {local_langcrowd_suggestions} sug
          JOIN {local_langcrowd_strings} str ON str.id = sug.stringid
          JOIN {user} u ON u.id = sug.userid
         WHERE $where
         ORDER BY sug.timecreated DESC";

$records = $DB->get_records_sql($sql, $sqlparams, $page * $perpage, $perpage);

// Filter options.
$langs = $DB->get_fieldset_sql(
    "SELECT DISTINCT lang FROM {local_langcrowd_strings} ORDER BY lang"
);
$components = $DB->get_fieldset_sql(
    "SELECT DISTINCT component FROM {local_langcrowd_strings} ORDER BY component"
);

echo $OUTPUT->header();
echo $OUTPUT->heading(get_string('report_suggestions', 'local_langcrowd'));

// Filter form.
echo html_writer::start_tag('form', ['method' => 'get', 'action' => $baseurl->out_omit_querystring(), 'class' => 'mb-3']);
echo html_writer::start_div('d-flex gap-2 align-items-end');

echo html_writer::start_div();
echo html_writer::label(get_string('filter_language', 'local_langcrowd'), 'filterlang', true, ['class' => 'form-label']);
$langopts = ['' => get_string('filter_all', 'local_langcrowd')];
foreach ($langs as $l) {
    $langopts[$l] = $l;
}
echo html_writer::select($langopts, 'lang', $lang, false, ['id' => 'filterlang', 'class' => 'form-select']);
echo html_writer::end_div();

echo html_writer::start_div();
echo html_writer::label(get_string('filter_component', 'local_langcrowd'), 'filtercomp', true, ['class' => 'form-label']);
$compopts = ['' => get_string('filter_all', 'local_langcrowd')];
foreach ($components as $c) {
    $compopts[$c] = $c;
}
echo html_writer::select($compopts, 'component', $component, false, ['id' => 'filtercomp', 'class' => 'form-select']);
echo html_writer::end_div();

echo html_writer::tag(
    'div',
    html_writer::empty_tag('input', [
        'type' => 'submit', 'value' => get_string('filter_apply', 'local_langcrowd'), 'class' => 'btn btn-secondary',
    ]),
    ['class' => 'align-self-end']
);
echo html_writer::end_div();
echo html_writer::end_tag('form');

if (empty($records)) {
    echo $OUTPUT->notification(get_string('export_nodata', 'local_langcrowd'), 'info');
} else {
    $table        = new html_table();
    $table->head  = [
        get_string('col_component', 'local_langcrowd'),
        get_string('col_stringkey', 'local_langcrowd'),
        get_string('filter_language', 'local_langcrowd'),
        get_string('col_currentvalue', 'local_langcrowd'),
        get_string('col_suggestion', 'local_langcrowd'),
        get_string('col_submittedby', 'local_langcrowd'),
        get_string('col_date', 'local_langcrowd'),
        get_string('col_actions', 'local_langcrowd'),
    ];
    $table->data  = [];

    foreach ($records as $rec) {
        $promoteurl = new moodle_url('/local/langcrowd/report_suggestions.php', [
            'action'       => 'promote',
            'suggestionid' => $rec->id,
            'sesskey'      => sesskey(),
            'lang'         => $lang,
            'component'    => $component,
        ]);
        $pushurl = new moodle_url('/local/langcrowd/report_suggestions.php', [
            'action'       => 'push',
            'suggestionid' => $rec->id,
            'sesskey'      => sesskey(),
            'lang'         => $lang,
            'component'    => $component,
        ]);
        $rejecturl = new moodle_url('/local/langcrowd/report_suggestions.php', [
            'action'       => 'reject',
            'suggestionid' => $rec->id,
            'sesskey'      => sesskey(),
            'lang'         => $lang,
            'component'    => $component,
        ]);

        $actions = html_writer::link(
            $promoteurl,
            get_string('action_promote', 'local_langcrowd'),
            ['class' => 'btn btn-sm btn-success me-1',
            'onclick' => 'return confirm(' . json_encode(get_string(
                'action_promote_confirm',
                'local_langcrowd'
            )) . ')']
        ) .
            html_writer::link(
                $pushurl,
                get_string('action_push', 'local_langcrowd'),
                ['class' => 'btn btn-sm btn-primary me-1',
                'onclick' => 'return confirm(' . json_encode(get_string(
                    'action_push_confirm',
                    'local_langcrowd'
                )) . ')']
            ) .
            html_writer::link(
                $rejecturl,
                get_string('action_reject', 'local_langcrowd'),
                ['class' => 'btn btn-sm btn-outline-danger',
                'onclick' => 'return confirm(' . json_encode(get_string(
                    'action_reject_confirm',
                    'local_langcrowd'
                )) . ')']
            );

        $table->data[] = [
            s($rec->component),
            s($rec->stringkey),
            s($rec->lang),
            s($rec->currentvalue),
            s($rec->suggestion),
            s(fullname($rec)),
            userdate($rec->timecreated),
            $actions,
        ];
    }

    echo html_writer::table($table);
    echo $OUTPUT->paging_bar($total, $page, $perpage, $baseurl);
}

echo $OUTPUT->footer();
