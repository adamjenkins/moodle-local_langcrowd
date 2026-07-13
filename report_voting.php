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
$page      = optional_param('page', 0, PARAM_INT);
$sort      = optional_param('sort', 'component', PARAM_ALPHANUMEXT);
$dir       = optional_param('dir', 'ASC', PARAM_ALPHA);
$showzero  = optional_param('showzero', 0, PARAM_BOOL);
$perpage   = 50;

// Action params: an individual action ("action:id") or a bulk action over ids[].
$single     = optional_param('single', '', PARAM_RAW_TRIMMED);
$applybulk  = optional_param('applybulk', 0, PARAM_BOOL);
$bulkaction = optional_param('bulkaction', '', PARAM_ALPHA);
$ids        = optional_param_array('ids', [], PARAM_INT);

$allowedsorts = ['component', 'stringkey', 'lang', 'sourcevalue', 'currentvalue', 'votecount', 'status'];
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

// Handle individual or bulk state changes.
if (($single !== '' || $applybulk) && confirm_sesskey()) {
    $message = '';
    if ($single !== '') {
        [$act, $sid] = array_pad(explode(':', $single, 2), 2, '');
        $sid = (int)$sid;
        if ($sid && $act === 'lock') {
            \local_langcrowd\manager::lock_string($sid);
            $message = get_string('status_locked', 'local_langcrowd');
        } else if ($sid && $act === 'unlock') {
            \local_langcrowd\manager::revert_string($sid);
            $message = get_string('status_pending', 'local_langcrowd');
        }
    } else if ($applybulk && !empty($ids)) {
        if ($bulkaction === 'lock') {
            \local_langcrowd\manager::lock_strings($ids);
            $message = get_string('bulk_locked', 'local_langcrowd', count($ids));
        } else if ($bulkaction === 'unlock') {
            \local_langcrowd\manager::revert_strings($ids);
            $message = get_string('bulk_removed', 'local_langcrowd', count($ids));
        }
    }
    redirect($pageurl, $message, null, \core\output\notification::NOTIFY_SUCCESS);
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

// Toggle-all checkbox behaviour (Moodle-blessed inline AMD).
$PAGE->requires->js_amd_inline("
document.addEventListener('change', function(e) {
    if (e.target && e.target.id === 'langcrowd-selectall') {
        document.querySelectorAll('input[name=\"ids[]\"]').forEach(function(cb) { cb.checked = e.target.checked; });
    }
});
");

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
    // Everything below (checkboxes, per-row buttons, bulk bar) lives in one POST form.
    echo html_writer::start_tag('form', [
        'method' => 'post',
        'action' => (new moodle_url('/local/langcrowd/report_voting.php'))->out(false),
    ]);
    foreach (
        ['sesskey' => sesskey(), 'lang' => $lang, 'component' => $component, 'status' => $status,
              'sort' => $sort, 'dir' => $dir, 'showzero' => $showzero ?: '', 'page' => $page] as $hn => $hv
    ) {
        echo html_writer::empty_tag('input', ['type' => 'hidden', 'name' => $hn, 'value' => $hv]);
    }

    $selectall = html_writer::empty_tag('input', [
        'type' => 'checkbox', 'id' => 'langcrowd-selectall',
        'title' => get_string('select_all', 'local_langcrowd'),
        'aria-label' => get_string('select_all', 'local_langcrowd'),
    ]);

    $table        = new html_table();
    $table->head  = [
        $selectall,
        $sortheader('component', get_string('col_component', 'local_langcrowd')),
        $sortheader('stringkey', get_string('col_stringkey', 'local_langcrowd')),
        $sortheader('lang', get_string('filter_language', 'local_langcrowd')),
        $sortheader('sourcevalue', get_string('col_sourcevalue', 'local_langcrowd')),
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

        $checkbox = html_writer::empty_tag('input', [
            'type' => 'checkbox', 'name' => 'ids[]', 'value' => $rec->id, 'class' => 'langcrowd-rowcheck',
        ]);

        if (in_array($rec->status, ['locked', 'pushed'], true)) {
            $actions = html_writer::tag('button', get_string('action_unlock', 'local_langcrowd'), [
                'type' => 'submit', 'name' => 'single', 'value' => 'unlock:' . $rec->id,
                'class' => 'btn btn-sm btn-outline-secondary',
                'onclick' => 'return confirm(' . json_encode(get_string('action_unlock_confirm', 'local_langcrowd')) . ')',
            ]);
        } else {
            $actions = html_writer::tag('button', get_string('action_lock', 'local_langcrowd'), [
                'type' => 'submit', 'name' => 'single', 'value' => 'lock:' . $rec->id,
                'class' => 'btn btn-sm btn-outline-success',
                'onclick' => 'return confirm(' . json_encode(get_string('action_lock_confirm', 'local_langcrowd')) . ')',
            ]);
        }

        $table->data[] = [
            $checkbox,
            s($rec->component),
            s($rec->stringkey),
            s($rec->lang),
            s($rec->sourcevalue),
            s($rec->currentvalue),
            $rec->votecount,
            $statusbadge,
            $actions,
        ];
    }

    echo html_writer::table($table);

    // Bulk action bar.
    echo html_writer::start_div('d-flex gap-2 align-items-center mb-3');
    echo html_writer::label(
        get_string('bulk_with_selected', 'local_langcrowd'),
        'bulkaction',
        true,
        ['class' => 'form-label mb-0']
    );
    echo html_writer::select([
        'lock'   => get_string('action_lock', 'local_langcrowd'),
        'unlock' => get_string('action_unlock', 'local_langcrowd'),
    ], 'bulkaction', 'lock', false, ['id' => 'bulkaction', 'class' => 'form-select w-auto']);
    echo html_writer::tag('button', get_string('bulk_apply', 'local_langcrowd'), [
        'type' => 'submit', 'name' => 'applybulk', 'value' => '1', 'class' => 'btn btn-secondary',
        'onclick' => 'return confirm(' . json_encode(get_string('bulk_confirm', 'local_langcrowd')) . ')',
    ]);
    echo html_writer::end_div();

    echo html_writer::end_tag('form');
    echo $OUTPUT->paging_bar($total, $page, $perpage, $pageurl);
}

echo $OUTPUT->footer();
