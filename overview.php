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
 * Overview dashboard for local_langcrowd.
 *
 * @package    local_langcrowd
 * @copyright  2026 Adam Jenkins <adam@wisecat.net>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require(__DIR__ . '/../../config.php');

require_login();
$context = context_system::instance();
require_capability('local/langcrowd:admin', $context);

$PAGE->set_url('/local/langcrowd/overview.php');
$PAGE->set_context($context);
$PAGE->set_title(get_string('overview', 'local_langcrowd'));
$PAGE->set_heading(get_string('overview', 'local_langcrowd'));
$PAGE->set_pagelayout('admin');

$counts = [
    'pending'  => $DB->count_records('local_langcrowd_strings', ['status' => 'pending']),
    'pushed'   => $DB->count_records('local_langcrowd_strings', ['status' => 'pushed']),
    'locked'   => $DB->count_records('local_langcrowd_strings', ['status' => 'locked']),
    'total'    => $DB->count_records('local_langcrowd_strings'),
    'suggpend' => $DB->count_records('local_langcrowd_suggestions', ['status' => 'pending']),
];

echo $OUTPUT->header();
echo $OUTPUT->heading(get_string('overview', 'local_langcrowd'));

/**
 * Renders one statistics card.
 *
 * @param string $label
 * @param int    $value
 * @param string $bg    Bootstrap background class.
 * @return string
 */
function local_langcrowd_stat_card(string $label, int $value, string $bg): string {
    return html_writer::div(
        html_writer::div($value, 'fs-2 fw-bold') . html_writer::div($label, 'small'),
        "card $bg text-center p-3 flex-fill",
        ['style' => 'min-width:130px']
    );
}

echo html_writer::start_div('d-flex gap-3 flex-wrap mb-4');
echo local_langcrowd_stat_card(get_string('status_pending', 'local_langcrowd'), $counts['pending'], 'bg-body-secondary');
echo local_langcrowd_stat_card(get_string('status_pushed', 'local_langcrowd'), $counts['pushed'], 'text-bg-primary');
echo local_langcrowd_stat_card(get_string('status_locked', 'local_langcrowd'), $counts['locked'], 'text-bg-success');
echo local_langcrowd_stat_card(get_string('overview_totalstrings', 'local_langcrowd'), $counts['total'], 'bg-body-secondary');
echo local_langcrowd_stat_card(
    get_string('overview_pendingsuggestions', 'local_langcrowd'),
    $counts['suggpend'],
    'text-bg-warning'
);
echo html_writer::end_div();

if (!$counts['total']) {
    echo $OUTPUT->notification(get_string('overview_nostrings', 'local_langcrowd'), 'info');
    echo $OUTPUT->footer();
    exit;
}

// Quick links to the reports.
echo html_writer::start_div('mb-4 d-flex gap-2 flex-wrap');
echo html_writer::link(
    new moodle_url('/local/langcrowd/report_voting.php'),
    get_string('report_voting', 'local_langcrowd'),
    ['class' => 'btn btn-outline-secondary']
);
echo html_writer::link(
    new moodle_url('/local/langcrowd/report_suggestions.php'),
    get_string('report_suggestions', 'local_langcrowd'),
    ['class' => 'btn btn-outline-secondary']
);
echo html_writer::link(
    new moodle_url('/local/langcrowd/export.php'),
    get_string('export', 'local_langcrowd'),
    ['class' => 'btn btn-outline-secondary']
);
echo html_writer::end_div();

// Most-voted strings still open for voting.
$topvoted = $DB->get_records_select(
    'local_langcrowd_strings',
    "status IN ('pending', 'pushed') AND votecount > 0",
    [],
    'votecount DESC, timemodified DESC',
    'id, component, stringkey, lang, currentvalue, votecount, status',
    0,
    10
);

echo $OUTPUT->heading(get_string('overview_topvoted', 'local_langcrowd'), 3);
if (empty($topvoted)) {
    echo html_writer::tag('p', get_string('overview_notopvoted', 'local_langcrowd'), ['class' => 'text-muted']);
} else {
    $t = new html_table();
    $t->head = [
        get_string('col_component', 'local_langcrowd'),
        get_string('col_stringkey', 'local_langcrowd'),
        get_string('filter_language', 'local_langcrowd'),
        get_string('col_currentvalue', 'local_langcrowd'),
        get_string('col_votecount', 'local_langcrowd'),
    ];
    foreach ($topvoted as $rec) {
        $t->data[] = [s($rec->component), s($rec->stringkey), s($rec->lang), s($rec->currentvalue), $rec->votecount];
    }
    echo html_writer::table($t);
}

// Recent pending suggestions.
$recent = $DB->get_records_sql(
    "SELECT sug.id, sug.suggestion, sug.timecreated, str.component, str.stringkey, str.lang
       FROM {local_langcrowd_suggestions} sug
       JOIN {local_langcrowd_strings} str ON str.id = sug.stringid
      WHERE sug.status = 'pending'
   ORDER BY sug.timecreated DESC",
    [],
    0,
    10
);

echo $OUTPUT->heading(get_string('overview_recentsuggestions', 'local_langcrowd'), 3);
if (empty($recent)) {
    echo html_writer::tag('p', get_string('overview_norecentsuggestions', 'local_langcrowd'), ['class' => 'text-muted']);
} else {
    $t = new html_table();
    $t->head = [
        get_string('col_component', 'local_langcrowd'),
        get_string('col_stringkey', 'local_langcrowd'),
        get_string('filter_language', 'local_langcrowd'),
        get_string('col_suggestion', 'local_langcrowd'),
        get_string('col_date', 'local_langcrowd'),
    ];
    foreach ($recent as $rec) {
        $t->data[] = [s($rec->component), s($rec->stringkey), s($rec->lang), s($rec->suggestion),
            userdate($rec->timecreated)];
    }
    echo html_writer::table($t);
}

echo $OUTPUT->footer();
