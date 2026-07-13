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
 * Admin settings for local_langcrowd.
 *
 * @package    local_langcrowd
 * @copyright  2026 Adam Jenkins <adam@wisecat.net>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

if ($hassiteconfig) {
    $category = new admin_category('local_langcrowd_cat', get_string('pluginname', 'local_langcrowd'));
    $ADMIN->add('localplugins', $category);

    $settings = new admin_settingpage('local_langcrowd', get_string('settings', 'local_langcrowd'));
    $ADMIN->add('local_langcrowd_cat', $settings);

    $ADMIN->add('local_langcrowd_cat', new admin_externalpage(
        'local_langcrowd_overview',
        get_string('overview', 'local_langcrowd'),
        new moodle_url('/local/langcrowd/overview.php'),
        'local/langcrowd:admin'
    ));
    $ADMIN->add('local_langcrowd_cat', new admin_externalpage(
        'local_langcrowd_report_voting',
        get_string('report_voting', 'local_langcrowd'),
        new moodle_url('/local/langcrowd/report_voting.php'),
        'local/langcrowd:admin'
    ));
    $ADMIN->add('local_langcrowd_cat', new admin_externalpage(
        'local_langcrowd_report_suggestions',
        get_string('report_suggestions', 'local_langcrowd'),
        new moodle_url('/local/langcrowd/report_suggestions.php'),
        'local/langcrowd:admin'
    ));
    $ADMIN->add('local_langcrowd_cat', new admin_externalpage(
        'local_langcrowd_export',
        get_string('export', 'local_langcrowd'),
        new moodle_url('/local/langcrowd/export.php'),
        'local/langcrowd:admin'
    ));

    if ($ADMIN->fulltree) {
        // Show whether the custom string manager is wired up.
        $manager = get_string_manager();
        if ($manager instanceof \local_langcrowd\string_manager) {
            $statushtml = html_writer::tag(
                'div',
                get_string('settings_stringmanager_active', 'local_langcrowd'),
                ['class' => 'alert alert-success']
            );
        } else {
            $configline = "\$CFG-&gt;customstringmanager = '\\local_langcrowd\\string_manager';";
            $statushtml = html_writer::tag(
                'div',
                html_writer::tag('p', get_string('settings_stringmanager_warning', 'local_langcrowd')) .
                html_writer::tag('p', get_string('settings_configphp_notice', 'local_langcrowd')) .
                html_writer::tag('pre', html_writer::tag('code', $configline)),
                ['class' => 'alert alert-warning']
            );
        }
        $settings->add(new admin_setting_heading('local_langcrowd/managerstatus', '', $statushtml));

        $settings->add(new admin_setting_configcheckbox(
            'local_langcrowd/enabled',
            get_string('settings_enabled', 'local_langcrowd'),
            get_string('settings_enabled_desc', 'local_langcrowd'),
            0
        ));

        $settings->add(new admin_setting_configcheckbox(
            'local_langcrowd/showadminlink',
            get_string('settings_showadminlink', 'local_langcrowd'),
            get_string('settings_showadminlink_desc', 'local_langcrowd'),
            0
        ));

        $settings->add(new admin_setting_configcheckbox(
            'local_langcrowd/adminvote_locks',
            get_string('settings_adminvote_locks', 'local_langcrowd'),
            get_string('settings_adminvote_locks_desc', 'local_langcrowd'),
            0
        ));

        $settings->add(new admin_setting_configtext(
            'local_langcrowd/threshold',
            get_string('settings_threshold', 'local_langcrowd'),
            get_string('settings_threshold_desc', 'local_langcrowd'),
            10,
            PARAM_INT
        ));

        $settings->add(new admin_setting_configtext(
            'local_langcrowd/maxstrings',
            get_string('settings_maxstrings', 'local_langcrowd'),
            get_string('settings_maxstrings_desc', 'local_langcrowd'),
            5000,
            PARAM_INT
        ));

        $settings->add(new admin_setting_configcheckbox(
            'local_langcrowd/forcetranslatemode',
            get_string('settings_forcetranslate', 'local_langcrowd'),
            get_string('settings_forcetranslate_desc', 'local_langcrowd'),
            0
        ));

        $settings->add(new admin_setting_configselect(
            'local_langcrowd/showmode',
            get_string('settings_showmode', 'local_langcrowd'),
            get_string('settings_showmode_desc', 'local_langcrowd'),
            'hover',
            [
                'hover'  => get_string('settings_showmode_hover', 'local_langcrowd'),
                'always' => get_string('settings_showmode_always', 'local_langcrowd'),
            ]
        ));

        $settings->add(new admin_setting_configcolourpicker(
            'local_langcrowd/highlightcolor',
            get_string('settings_highlightcolor', 'local_langcrowd'),
            get_string('settings_highlightcolor_desc', 'local_langcrowd'),
            '#fff3cd'
        ));

        $allroles = role_fix_names(get_all_roles(), context_system::instance(), ROLENAME_ORIGINAL);
        $roleoptions = [];
        foreach ($allroles as $role) {
            $roleoptions[$role->id] = $role->localname;
        }
        $settings->add(new admin_setting_configmultiselect(
            'local_langcrowd/allowed_roles',
            get_string('settings_allowed_roles', 'local_langcrowd'),
            get_string('settings_allowed_roles_desc', 'local_langcrowd'),
            [],
            $roleoptions
        ));

        $langoptions = get_string_manager()->get_list_of_translations();
        $settings->add(new admin_setting_configmultiselect(
            'local_langcrowd/allowed_langs',
            get_string('settings_allowed_langs', 'local_langcrowd'),
            get_string('settings_allowed_langs_desc', 'local_langcrowd'),
            [],
            $langoptions
        ));

        // Components are discovered from the strings seen so far. Include any currently
        // selected values that are no longer in that set so a saved selection persists.
        $componentoptions = [];
        foreach (\local_langcrowd\local\exporter::get_all_components() as $c) {
            $componentoptions[$c] = $c;
        }
        $selectedcomponents = get_config('local_langcrowd', 'allowed_components');
        if (!empty($selectedcomponents)) {
            foreach (explode(',', $selectedcomponents) as $c) {
                $componentoptions[$c] = $c;
            }
            ksort($componentoptions);
        }
        $settings->add(new admin_setting_configmultiselect(
            'local_langcrowd/allowed_components',
            get_string('settings_allowed_components', 'local_langcrowd'),
            get_string('settings_allowed_components_desc', 'local_langcrowd'),
            [],
            $componentoptions
        ));
    }
}
