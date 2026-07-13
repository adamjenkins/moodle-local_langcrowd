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
 * Hook callback handlers for local_langcrowd.
 *
 * @package    local_langcrowd
 * @copyright  2026 Adam Jenkins <adam@wisecat.net>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_langcrowd;

/**
 * Handles Moodle output hooks for the crowdsourcing UI.
 */
class hook_callbacks {
    /**
     * Injects the voting AMD module and page-string data into the footer.
     *
     * @param \core\hook\output\before_footer_html_generation $hook
     */
    public static function before_footer(\core\hook\output\before_footer_html_generation $hook): void {
        global $PAGE;

        if (!self::should_annotate()) {
            return;
        }

        $strings = string_manager::get_page_strings();
        if (empty($strings)) {
            return;
        }

        $uistrings = [
            'btn_approve'          => get_string('btn_approve', 'local_langcrowd'),
            'btn_suggest'          => get_string('btn_suggest', 'local_langcrowd'),
            'modal_suggest_title'  => get_string('modal_suggest_title', 'local_langcrowd'),
            'modal_original_label' => get_string('modal_original_label', 'local_langcrowd'),
            'modal_source_label'   => get_string('modal_source_label', 'local_langcrowd'),
            'modal_suggestion_label' => get_string('modal_suggestion_label', 'local_langcrowd'),
            'modal_submit'         => get_string('modal_submit', 'local_langcrowd'),
            'modal_cancel'         => get_string('modal_cancel', 'local_langcrowd'),
            'toggle_label'         => get_string('overlay_toggle', 'local_langcrowd'),
            'undo'                 => get_string('overlay_undo', 'local_langcrowd'),
            'progress_label'       => get_string('overlay_progress_label', 'local_langcrowd'),
        ];

        $data = json_encode([
            'strings'        => array_values($strings),
            'lang'           => current_language(),
            'uistrings'      => $uistrings,
            'showmode'       => get_config('local_langcrowd', 'showmode') ?: 'hover',
            'highlightcolor' => get_config('local_langcrowd', 'highlightcolor') ?: '#fff3cd',
            'threshold'      => (int)get_config('local_langcrowd', 'threshold'),
        ], JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP);

        // Set the global before RequireJS loads (timing-safe: assignment needs no AMD).
        $hook->add_html('<script>window.langcrowdInit = ' . $data . ';</script>');

        // Schedule the AMD call via requires so it runs after RequireJS is set up.
        $PAGE->requires->js_call_amd('local_langcrowd/voting', 'init');
    }

    /**
     * Whether the overlay should be injected for the current request and user.
     *
     * @return bool
     */
    protected static function should_annotate(): bool {
        global $PAGE, $USER;

        if (!access::is_enabled() || !isloggedin() || isguestuser()) {
            return false;
        }
        // Enforce the same role/language gate the web services enforce server-side.
        if (!access::user_can_participate($USER->id, current_language())) {
            return false;
        }
        // The overlay calls a service that needs this capability; don't show buttons
        // to users who lack it (otherwise their first page scan would just error).
        if (!has_capability('local/langcrowd:vote', \context_system::instance())) {
            return false;
        }
        // Don't annotate the plugin's own admin pages.
        return strpos($PAGE->url->get_path(), '/local/langcrowd/') === false;
    }

    /**
     * Adds a primary navigation link to the plugin's admin category page.
     *
     * @param \core\hook\navigation\primary_extend $hook
     */
    public static function extend_primary_navigation(\core\hook\navigation\primary_extend $hook): void {
        if (!get_config('local_langcrowd', 'showadminlink')) {
            return;
        }
        if (!has_capability('moodle/site:config', \context_system::instance())) {
            return;
        }
        $hook->get_primaryview()->add(
            get_string('pluginname', 'local_langcrowd'),
            new \moodle_url('/local/langcrowd/overview.php'),
            \navigation_node::TYPE_CUSTOM,
            null,
            'langcrowd_admin',
            new \pix_icon('i/settings', '')
        );
    }
}
