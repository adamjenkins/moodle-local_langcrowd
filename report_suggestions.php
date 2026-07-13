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
 * @copyright  2026 Adam Jenkins <adam@wisecat.net>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require(__DIR__ . '/../../config.php');

require_login();
$context = context_system::instance();
require_capability('local/langcrowd:admin', $context);

$lang      = optional_param('lang', '', PARAM_LANG);
$component = optional_param('component', '', PARAM_NOTAGS);
$page      = optional_param('page', 0, PARAM_INT);
$perpage   = 50;

// Action params: an individual action ("action:id") or a bulk action over ids[].
$single     = optional_param('single', '', PARAM_RAW_TRIMMED);
$applybulk  = optional_param('applybulk', 0, PARAM_BOOL);
$bulkaction = optional_param('bulkaction', '', PARAM_ALPHA);
$ids        = optional_param_array('ids', [], PARAM_INT);

$baseurl = new moodle_url(
    '/local/langcrowd/report_suggestions.php',
    array_filter(['lang' => $lang, 'component' => $component])
);

$PAGE->set_url($baseurl);
$PAGE->set_context($context);
$PAGE->set_title(get_string('report_suggestions', 'local_langcrowd'));
$PAGE->set_heading(get_string('report_suggestions', 'local_langcrowd'));
$PAGE->set_pagelayout('admin');

/**
 * Applies a suggestion action and returns the success message, or '' if unknown.
 *
 * @param string $action promote|push|reject
 * @param int[]  $suggestionids
 * @return string
 */
function local_langcrowd_apply_suggestion_action(string $action, array $suggestionids): string {
    $count = count($suggestionids);
    if ($action === 'promote') {
        \local_langcrowd\manager::apply_suggestions($suggestionids, true);
        return get_string('bulk_approved', 'local_langcrowd', $count);
    } else if ($action === 'push') {
        \local_langcrowd\manager::apply_suggestions($suggestionids, false);
        return get_string('bulk_pushed', 'local_langcrowd', $count);
    } else if ($action === 'reject') {
        \local_langcrowd\manager::reject_suggestions($suggestionids);
        return get_string('bulk_rejected', 'local_langcrowd', $count);
    }
    return '';
}

// Handle individual or bulk actions.
if (($single !== '' || $applybulk) && confirm_sesskey()) {
    $message = '';
    if ($single !== '') {
        [$act, $sid] = array_pad(explode(':', $single, 2), 2, '');
        $sid = (int)$sid;
        if ($sid && $DB->record_exists('local_langcrowd_suggestions', ['id' => $sid])) {
            $message = local_langcrowd_apply_suggestion_action($act, [$sid]);
        }
    } else if ($applybulk && !empty($ids)) {
        $message = local_langcrowd_apply_suggestion_action($bulkaction, $ids);
    }
    redirect($baseurl, $message, null, \core\output\notification::NOTIFY_SUCCESS);
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
               str.component, str.stringkey, str.lang, str.sourcevalue, str.currentvalue,
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

// Toggle-all checkbox behaviour (Moodle-blessed inline AMD).
$PAGE->requires->js_amd_inline("
document.addEventListener('change', function(e) {
    if (e.target && e.target.id === 'langcrowd-selectall') {
        document.querySelectorAll('input[name=\"ids[]\"]').forEach(function(cb) { cb.checked = e.target.checked; });
    }
});
");

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
    echo html_writer::start_tag('form', [
        'method' => 'post',
        'action' => $baseurl->out_omit_querystring(),
    ]);
    foreach (['sesskey' => sesskey(), 'lang' => $lang, 'component' => $component, 'page' => $page] as $hn => $hv) {
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
        get_string('col_component', 'local_langcrowd'),
        get_string('col_stringkey', 'local_langcrowd'),
        get_string('filter_language', 'local_langcrowd'),
        get_string('col_sourcevalue', 'local_langcrowd'),
        get_string('col_currentvalue', 'local_langcrowd'),
        get_string('col_suggestion', 'local_langcrowd'),
        get_string('col_submittedby', 'local_langcrowd'),
        get_string('col_date', 'local_langcrowd'),
        get_string('col_actions', 'local_langcrowd'),
    ];
    $table->data  = [];

    foreach ($records as $rec) {
        $checkbox = html_writer::empty_tag('input', [
            'type' => 'checkbox', 'name' => 'ids[]', 'value' => $rec->id, 'class' => 'langcrowd-rowcheck',
        ]);

        $promote = html_writer::tag('button', get_string('action_promote', 'local_langcrowd'), [
            'type' => 'submit', 'name' => 'single', 'value' => 'promote:' . $rec->id,
            'class' => 'btn btn-sm btn-success',
            'onclick' => 'return confirm(' . json_encode(get_string('action_promote_confirm', 'local_langcrowd')) . ')',
        ]);
        $push = html_writer::tag('button', get_string('action_push', 'local_langcrowd'), [
            'type' => 'submit', 'name' => 'single', 'value' => 'push:' . $rec->id,
            'class' => 'btn btn-sm btn-primary',
            'onclick' => 'return confirm(' . json_encode(get_string('action_push_confirm', 'local_langcrowd')) . ')',
        ]);
        $reject = html_writer::tag('button', get_string('action_reject', 'local_langcrowd'), [
            'type' => 'submit', 'name' => 'single', 'value' => 'reject:' . $rec->id,
            'class' => 'btn btn-sm btn-outline-danger',
            'onclick' => 'return confirm(' . json_encode(get_string('action_reject_confirm', 'local_langcrowd')) . ')',
        ]);
        $actions = html_writer::div($promote . $push . $reject, 'd-flex gap-1 flex-wrap');

        $table->data[] = [
            $checkbox,
            s($rec->component),
            s($rec->stringkey),
            s($rec->lang),
            s($rec->sourcevalue),
            s($rec->currentvalue),
            s($rec->suggestion),
            s(fullname($rec)),
            userdate($rec->timecreated),
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
        'promote' => get_string('action_promote', 'local_langcrowd'),
        'push'    => get_string('action_push', 'local_langcrowd'),
        'reject'  => get_string('action_reject', 'local_langcrowd'),
    ], 'bulkaction', 'promote', false, ['id' => 'bulkaction', 'class' => 'form-select w-auto']);
    echo html_writer::tag('button', get_string('bulk_apply', 'local_langcrowd'), [
        'type' => 'submit', 'name' => 'applybulk', 'value' => '1', 'class' => 'btn btn-secondary',
        'onclick' => 'return confirm(' . json_encode(get_string('bulk_confirm', 'local_langcrowd')) . ')',
    ]);
    echo html_writer::end_div();

    echo html_writer::end_tag('form');
    echo $OUTPUT->paging_bar($total, $page, $perpage, $baseurl);
}

echo $OUTPUT->footer();
