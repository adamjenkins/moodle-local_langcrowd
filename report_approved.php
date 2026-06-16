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
 * Approved strings report for local_langcrowd.
 *
 * @package    local_langcrowd
 * @copyright  2026 hama.history@gmail.com
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require(__DIR__ . '/../../config.php');

require_login();
$context = context_system::instance();
require_capability('local/langcrowd:admin', $context);

$lang      = optional_param('lang', '', PARAM_LANG);
$component = optional_param('component', '', PARAM_NOTAGS);
$action    = optional_param('action', '', PARAM_ALPHA);
$stringid  = optional_param('stringid', 0, PARAM_INT);
$page      = optional_param('page', 0, PARAM_INT);
$perpage   = 50;

$baseurl = new moodle_url(
    '/local/langcrowd/report_approved.php',
    array_filter(['lang' => $lang, 'component' => $component])
);

$PAGE->set_url($baseurl);
$PAGE->set_context($context);
$PAGE->set_title(get_string('report_approved', 'local_langcrowd'));
$PAGE->set_heading(get_string('report_approved', 'local_langcrowd'));
$PAGE->set_pagelayout('admin');

// Handle unlock action.
if ($action === 'unlock' && $stringid && confirm_sesskey()) {
    $now = time();
    $DB->update_record('local_langcrowd_strings', (object)[
        'id'           => $stringid,
        'status'       => 'pending',
        'votecount'    => 0,
        'timemodified' => $now,
    ]);
    redirect($baseurl, get_string('status_pending', 'local_langcrowd'), null, \core\output\notification::NOTIFY_SUCCESS);
}

// Build filter.
$where     = "status = 'locked'";
$sqlparams = [];
if ($lang) {
    $where .= ' AND lang = :lang';
    $sqlparams['lang'] = $lang;
}
if ($component) {
    $where .= ' AND component = :component';
    $sqlparams['component'] = $component;
}

$total   = $DB->count_records_select('local_langcrowd_strings', $where, $sqlparams);
$records = $DB->get_records_select(
    'local_langcrowd_strings',
    $where,
    $sqlparams,
    'component ASC, stringkey ASC',
    '*',
    $page * $perpage,
    $perpage
);

// Filter form options.
$langs = $DB->get_fieldset_sql(
    "SELECT DISTINCT lang FROM {local_langcrowd_strings} ORDER BY lang"
);
$components = $DB->get_fieldset_sql(
    "SELECT DISTINCT component FROM {local_langcrowd_strings} ORDER BY component"
);

echo $OUTPUT->header();
echo $OUTPUT->heading(get_string('report_approved', 'local_langcrowd'));

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
        get_string('col_votecount', 'local_langcrowd'),
        get_string('col_actions', 'local_langcrowd'),
    ];
    $table->data  = [];

    foreach ($records as $rec) {
        $unlockurl = new moodle_url('/local/langcrowd/report_approved.php', [
            'action'   => 'unlock',
            'stringid' => $rec->id,
            'sesskey'  => sesskey(),
            'lang'     => $lang,
            'component' => $component,
        ]);
        $table->data[] = [
            s($rec->component),
            s($rec->stringkey),
            s($rec->lang),
            s($rec->currentvalue),
            $rec->votecount,
            html_writer::link(
                $unlockurl,
                get_string('action_unlock', 'local_langcrowd'),
                ['class' => 'btn btn-sm btn-outline-secondary']
            ),
        ];
    }

    echo html_writer::table($table);
    echo $OUTPUT->paging_bar($total, $page, $perpage, $baseurl);
}

echo $OUTPUT->footer();
