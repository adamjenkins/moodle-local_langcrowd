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
 * Voting report for local_langcrowd.
 *
 * @package    local_langcrowd
 * @copyright  2026 Adam Jenkins <adam@wisecat.net>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require(__DIR__ . '/../../config.php');

require_login();
$context = context_system::instance();
require_capability('local/langcrowd:admin', $context);

$lang      = optional_param('lang', '', PARAM_LANG);
$component = optional_param('component', '', PARAM_NOTAGS);
$status    = optional_param('status', '', PARAM_ALPHA);
$action    = optional_param('action', '', PARAM_ALPHA);
$stringid  = optional_param('stringid', 0, PARAM_INT);
$page      = optional_param('page', 0, PARAM_INT);
$sort      = optional_param('sort', 'component', PARAM_ALPHANUMEXT);
$dir       = optional_param('dir', 'ASC', PARAM_ALPHA);
$showzero  = optional_param('showzero', 0, PARAM_BOOL);
$perpage   = 50;

$allowedsorts = ['component', 'stringkey', 'lang', 'currentvalue', 'votecount', 'status'];
if (!in_array($sort, $allowedsorts, true)) {
    $sort = 'component';
}
$dir = strtoupper($dir) === 'DESC' ? 'DESC' : 'ASC';

// Normalise status to a valid value or empty.
$validstatuses = ['pending', 'locked', 'pushed'];
if (!in_array($status, $validstatuses, true)) {
    $status = '';
}

$filterparams = array_filter(['lang' => $lang, 'component' => $component, 'status' => $status, 'showzero' => $showzero ?: '']);
$sortparams   = ($sort !== 'component' || $dir !== 'ASC') ? ['sort' => $sort, 'dir' => $dir] : [];
$pageurl      = new moodle_url('/local/langcrowd/report_voting.php', $filterparams + $sortparams);

$PAGE->set_url(new moodle_url($pageurl, $page ? ['page' => $page] : []));
$PAGE->set_context($context);
$PAGE->set_title(get_string('report_voting', 'local_langcrowd'));
$PAGE->set_heading(get_string('report_voting', 'local_langcrowd'));
$PAGE->set_pagelayout('admin');

// Handle unlock action (locked/pushed → pending, reset vote cycle and value).
if ($action === 'unlock' && $stringid && confirm_sesskey()) {
    \local_langcrowd\manager::revert_string($stringid);
    redirect($pageurl, get_string('status_pending', 'local_langcrowd'), null, \core\output\notification::NOTIFY_SUCCESS);
}

// Handle lock action (pending → locked).
if ($action === 'lock' && $stringid && confirm_sesskey()) {
    \local_langcrowd\manager::lock_string($stringid);
    redirect($pageurl, get_string('status_locked', 'local_langcrowd'), null, \core\output\notification::NOTIFY_SUCCESS);
}

// Build WHERE clause.
$sqlparams = [];
if ($status) {
    $where = 'status = :status';
    $sqlparams['status'] = $status;
} else {
    $where = "status IN ('locked', 'pushed', 'pending')";
}
if ($lang) {
    $where .= ' AND lang = :lang';
    $sqlparams['lang'] = $lang;
}
if ($component) {
    $where .= ' AND component = :component';
    $sqlparams['component'] = $component;
}
if (!$showzero) {
    $where .= ' AND votecount != 0';
}

// Build ORDER BY with stable secondary sort.
$orderby = $sort . ' ' . $dir;
if ($sort !== 'component') {
    $orderby .= ', component ASC';
}
if ($sort !== 'stringkey') {
    $orderby .= ', stringkey ASC';
}

$total   = $DB->count_records_select('local_langcrowd_strings', $where, $sqlparams);
$records = $DB->get_records_select(
    'local_langcrowd_strings',
    $where,
    $sqlparams,
    $orderby,
    '*',
    $page * $perpage,
    $perpage
);

// Distinct values for filter dropdowns (all three statuses).
$langs = $DB->get_fieldset_sql(
    "SELECT DISTINCT lang FROM {local_langcrowd_strings} WHERE status IN ('locked','pushed','pending') ORDER BY lang"
);
$components = $DB->get_fieldset_sql(
    "SELECT DISTINCT component FROM {local_langcrowd_strings} WHERE status IN ('locked','pushed','pending') ORDER BY component"
);

// Returns a sortable column header link with directional arrow.
$sortheader = function (string $col, string $label) use ($sort, $dir, $pageurl): string {
    $newdir = ($sort === $col && $dir === 'ASC') ? 'DESC' : 'ASC';
    $url    = new moodle_url($pageurl, ['sort' => $col, 'dir' => $newdir, 'page' => 0]);
    $arrow  = $sort === $col ? ($dir === 'ASC' ? ' ▲' : ' ▼') : '';
    return html_writer::link($url, $label . $arrow, ['style' => 'white-space:nowrap']);
};

echo $OUTPUT->header();
echo $OUTPUT->heading(get_string('report_voting', 'local_langcrowd'));

// Filter form — hidden sort/dir inputs preserve sort when re-filtering.
echo html_writer::start_tag('form', [
    'method' => 'get',
    'action' => (new moodle_url('/local/langcrowd/report_voting.php'))->out(false),
    'class'  => 'mb-3',
]);
echo html_writer::empty_tag('input', ['type' => 'hidden', 'name' => 'sort', 'value' => $sort]);
echo html_writer::empty_tag('input', ['type' => 'hidden', 'name' => 'dir', 'value' => $dir]);
echo html_writer::start_div('d-flex gap-2 align-items-end flex-wrap');

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

echo html_writer::start_div();
echo html_writer::label(get_string('filter_status', 'local_langcrowd'), 'filterstatus', true, ['class' => 'form-label']);
$statusopts = [
    ''        => get_string('filter_all', 'local_langcrowd'),
    'pending' => get_string('status_pending', 'local_langcrowd'),
    'locked'  => get_string('status_locked', 'local_langcrowd'),
    'pushed'  => get_string('status_pushed', 'local_langcrowd'),
];
echo html_writer::select($statusopts, 'status', $status, false, ['id' => 'filterstatus', 'class' => 'form-select']);
echo html_writer::end_div();

$showzeroattrs = [
    'type' => 'checkbox', 'name' => 'showzero', 'id' => 'filtershowzero', 'value' => '1', 'class' => 'form-check-input',
];
if ($showzero) {
    $showzeroattrs['checked'] = 'checked';
}
echo html_writer::start_div('d-flex flex-column justify-content-end pb-1');
echo html_writer::start_div('form-check');
echo html_writer::empty_tag('input', $showzeroattrs);
echo html_writer::label(
    get_string('filter_showzero', 'local_langcrowd'),
    'filtershowzero',
    true,
    ['class' => 'form-check-label']
);
echo html_writer::end_div();
echo html_writer::end_div();

echo html_writer::tag(
    'div',
    html_writer::empty_tag('input', [
        'type'  => 'submit',
        'value' => get_string('filter_apply', 'local_langcrowd'),
        'class' => 'btn btn-secondary',
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
        $sortheader('component', get_string('col_component', 'local_langcrowd')),
        $sortheader('stringkey', get_string('col_stringkey', 'local_langcrowd')),
        $sortheader('lang', get_string('filter_language', 'local_langcrowd')),
        $sortheader('currentvalue', get_string('col_currentvalue', 'local_langcrowd')),
        $sortheader('votecount', get_string('col_votecount', 'local_langcrowd')),
        $sortheader('status', get_string('col_status', 'local_langcrowd')),
        get_string('col_actions', 'local_langcrowd'),
    ];
    $table->data = [];

    foreach ($records as $rec) {
        switch ($rec->status) {
            case 'pushed':
                $statusbadge = html_writer::tag(
                    'span',
                    get_string('status_pushed', 'local_langcrowd'),
                    ['class' => 'badge bg-primary']
                );
                break;
            case 'locked':
                $statusbadge = html_writer::tag(
                    'span',
                    get_string('status_locked', 'local_langcrowd'),
                    ['class' => 'badge bg-success']
                );
                break;
            default:
                $statusbadge = html_writer::tag(
                    'span',
                    get_string('status_pending', 'local_langcrowd'),
                    ['class' => 'badge bg-secondary']
                );
                break;
        }

        // State changes go through POST buttons (single_button adds the sesskey).
        $actionparams = [
            'stringid'  => $rec->id,
            'lang'      => $lang,
            'component' => $component,
            'status'    => $status,
            'sort'      => $sort,
            'dir'       => $dir,
        ];
        if (in_array($rec->status, ['locked', 'pushed'], true)) {
            $button = new \core\output\single_button(
                new moodle_url('/local/langcrowd/report_voting.php', $actionparams + ['action' => 'unlock']),
                get_string('action_unlock', 'local_langcrowd'),
                'post',
                \core\output\single_button::BUTTON_SECONDARY
            );
            $button->add_confirm_action(get_string('action_unlock_confirm', 'local_langcrowd'));
        } else {
            $button = new \core\output\single_button(
                new moodle_url('/local/langcrowd/report_voting.php', $actionparams + ['action' => 'lock']),
                get_string('action_lock', 'local_langcrowd'),
                'post',
                \core\output\single_button::BUTTON_SUCCESS
            );
            $button->add_confirm_action(get_string('action_lock_confirm', 'local_langcrowd'));
        }
        $actions = $OUTPUT->render($button);

        $table->data[] = [
            s($rec->component),
            s($rec->stringkey),
            s($rec->lang),
            s($rec->currentvalue),
            $rec->votecount,
            $statusbadge,
            $actions,
        ];
    }

    echo html_writer::table($table);
    echo $OUTPUT->paging_bar($total, $page, $perpage, $pageurl);
}

echo $OUTPUT->footer();
