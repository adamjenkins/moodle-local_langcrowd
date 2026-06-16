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
 * Language pack export page for local_langcrowd.
 *
 * @package    local_langcrowd
 * @copyright  2026 hama.history@gmail.com
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require(__DIR__ . '/../../config.php');

require_login();
$context = context_system::instance();
require_capability('local/langcrowd:admin', $context);

$PAGE->set_url('/local/langcrowd/export.php');
$PAGE->set_context($context);
$PAGE->set_title(get_string('export', 'local_langcrowd'));
$PAGE->set_heading(get_string('export', 'local_langcrowd'));
$PAGE->set_pagelayout('admin');

$availablelangs = \local_langcrowd\local\exporter::get_languages();

// Handle form submission.
if (optional_param('download', 0, PARAM_BOOL) && confirm_sesskey()) {
    $lang       = required_param('lang', PARAM_LANG);
    $scope      = optional_param('scope', 'locked', PARAM_ALPHA);
    $components = optional_param_array('components', [], PARAM_NOTAGS);

    $binary = \local_langcrowd\local\exporter::export($lang, $components, $scope);

    if ($binary === '') {
        redirect($PAGE->url, get_string('export_nodata', 'local_langcrowd'), null, \core\output\notification::NOTIFY_WARNING);
    }

    $filename = 'langpack_' . $lang . '_' . date('Ymd_His') . '.zip';
    header('Content-Type: application/zip');
    header('Content-Disposition: attachment; filename="' . $filename . '"');
    header('Content-Length: ' . strlen($binary));
    header('Cache-Control: no-cache, no-store, must-revalidate');
    echo $binary;
    exit;
}

echo $OUTPUT->header();
echo $OUTPUT->heading(get_string('export', 'local_langcrowd'));

if (empty($availablelangs)) {
    echo $OUTPUT->notification(get_string('export_nodata', 'local_langcrowd'), 'info');
    echo $OUTPUT->footer();
    exit;
}

// Export form.
echo html_writer::start_tag('form', ['method' => 'post', 'action' => $PAGE->url]);
echo html_writer::empty_tag('input', ['type' => 'hidden', 'name' => 'sesskey', 'value' => sesskey()]);
echo html_writer::empty_tag('input', ['type' => 'hidden', 'name' => 'download', 'value' => '1']);

echo html_writer::start_div('mb-3');
echo html_writer::label(get_string('export_language', 'local_langcrowd'), 'exportlang', true, ['class' => 'form-label fw-bold']);
$langopts = [];
foreach ($availablelangs as $l) {
    $langopts[$l] = $l;
}
echo html_writer::select($langopts, 'lang', reset($availablelangs), false, ['id' => 'exportlang', 'class' => 'form-select w-auto']);
echo html_writer::end_div();

echo html_writer::start_div('mb-3');
echo html_writer::tag('label', get_string('export_scope', 'local_langcrowd'), ['class' => 'form-label fw-bold d-block']);

echo html_writer::start_div('form-check');
echo html_writer::empty_tag('input', [
    'type' => 'radio', 'name' => 'scope', 'id' => 'scope_locked',
    'value' => 'locked', 'checked' => 'checked', 'class' => 'form-check-input',
]);
echo html_writer::label(
    get_string('export_scope_locked', 'local_langcrowd'),
    'scope_locked',
    true,
    ['class' => 'form-check-label']
);
echo html_writer::end_div();

echo html_writer::start_div('form-check');
echo html_writer::empty_tag('input', [
    'type' => 'radio', 'name' => 'scope', 'id' => 'scope_all', 'value' => 'all', 'class' => 'form-check-input',
]);
echo html_writer::label(get_string('export_scope_all', 'local_langcrowd'), 'scope_all', true, ['class' => 'form-check-label']);
echo html_writer::end_div();
echo html_writer::end_div();

// Component list (populated by JS based on lang selection, or show all).
$alllangcomponents = [];
foreach ($availablelangs as $l) {
    $alllangcomponents[$l] = \local_langcrowd\local\exporter::get_components($l);
}

echo html_writer::start_div('mb-3');
echo html_writer::label(
    get_string('export_components', 'local_langcrowd') . ' ' .
    html_writer::tag('small', get_string('export_components_desc', 'local_langcrowd'), ['class' => 'text-muted fw-normal']),
    'exportcomponents',
    false,
    ['class' => 'form-label fw-bold']
);

$firstlang = reset($availablelangs);
$comps     = $alllangcomponents[$firstlang] ?? [];
$compopts  = [];
foreach ($comps as $c) {
    $compopts[$c] = $c;
}
echo html_writer::select($compopts, 'components[]', [], false, [
    'id'       => 'exportcomponents',
    'class'    => 'form-select',
    'multiple' => 'multiple',
    'size'     => min(10, max(4, count($compopts))),
]);
echo html_writer::end_div();

echo html_writer::tag(
    'button',
    get_string('export_download', 'local_langcrowd'),
    ['type' => 'submit', 'class' => 'btn btn-primary']
);

echo html_writer::end_tag('form');
echo $OUTPUT->footer();
