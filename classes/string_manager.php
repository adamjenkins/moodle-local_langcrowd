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
 * Custom string manager for local_langcrowd.
 *
 * Records every get_string() call made during a web request so the footer
 * hook can pass the list to the voting AMD module. No HTML wrapping is done
 * here — all DOM manipulation happens client-side — so Mustache templates
 * that escape their output are unaffected.
 *
 * @package    local_langcrowd
 * @copyright  2026 Adam Jenkins <adam@wisecat.net>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_langcrowd;

/**
 * Extends the standard string manager to track string usage on each page.
 */
class string_manager extends \core_string_manager_standard {
    /** Default maximum unique strings to track per page (overridden by admin setting). */
    protected const MAX_PAGE_STRINGS = 5000;

    /**
     * Memoised: true when this request collects page strings (full HTML page, not AJAX/CLI/cron).
     *
     * @var bool|null
     */
    protected static ?bool $collectcontext = null;

    /**
     * Memoised: true when this request should serve promoted translations (any non-CLI request,
     * including AJAX, so locked translations appear in dynamically rendered content too).
     *
     * @var bool|null
     */
    protected static ?bool $servecontext = null;

    /**
     * Accumulated {component, key, value} tuples for this request.
     *
     * @var array
     */
    protected static array $pagestrings = [];

    /**
     * Cache of promoted/locked strings keyed by "component::key", loaded once per request.
     * Null means not yet loaded; empty array means none exist.
     *
     * @var array|null
     */
    protected static ?array $promotedstrings = null;

    /**
     * Returns all strings tracked during this request.
     *
     * @return array
     */
    public static function get_page_strings(): array {
        return array_values(self::$pagestrings);
    }

    /**
     * Loads all locked strings for the current language into a static cache.
     * Runs at most once per request; silently does nothing if the DB is not available.
     */
    protected function load_promoted_strings(): void {
        if (self::$promotedstrings !== null) {
            return;
        }
        self::$promotedstrings = [];
        if (!$this->is_serving_context()) {
            return;
        }
        global $DB;
        try {
            $records = $DB->get_records_sql(
                "SELECT id, component, stringkey, currentvalue, sourcevalue
                   FROM {local_langcrowd_strings}
                  WHERE lang = ? AND status IN ('locked', 'pushed')",
                [current_language()]
            );
            foreach ($records as $rec) {
                // Only override if the currentvalue actually differs from the source.
                if ($rec->currentvalue !== $rec->sourcevalue) {
                    self::$promotedstrings[$rec->component . '::' . $rec->stringkey] = $rec->currentvalue;
                }
            }
        } catch (\Throwable $e) {
            debugging('local_langcrowd: failed to load promoted strings: ' . $e->getMessage(), DEBUG_DEVELOPER);
        }
    }

    /**
     * Overrides get_string() to record each call in web contexts.
     *
     * @param string $identifier
     * @param string $component
     * @param mixed  $a
     * @param string $lang
     * @return string
     */
    public function get_string($identifier, $component = '', $a = null, $lang = null) {
        $this->load_promoted_strings();

        $comp     = empty($component) ? 'moodle' : $component;
        $cachekey = $comp . '::' . $identifier;

        // Serve a promoted (locked/pushed) translation when one exists and no substitution is needed.
        if ($a === null && $lang === null && isset(self::$promotedstrings[$cachekey])) {
            $result = self::$promotedstrings[$cachekey];
            // Track the promoted value so DOM text nodes (which show the promoted text) match.
            $this->maybe_track($comp, $identifier, $result);
            return $result;
        }

        $result = parent::get_string($identifier, $component, $a, $lang);

        // Only track static strings (no parameter substitution).
        if ($a === null) {
            $this->maybe_track($comp, $identifier, $result);
        }

        return $result;
    }

    /**
     * Records a string for the footer overlay if it is trackable in this context.
     *
     * Skips the plugin's own strings, very short strings, strings containing HTML,
     * and strings with embedded newlines — none of which can match a DOM text node.
     *
     * @param string $comp       Resolved component name.
     * @param string $identifier String key.
     * @param string $value      Rendered value.
     */
    protected function maybe_track(string $comp, string $identifier, string $value): void {
        if (!$this->is_collecting_context() || $comp === 'local_langcrowd') {
            return;
        }
        // Respect the admin's component filter (empty = all components).
        if (!access::component_is_allowed($comp)) {
            return;
        }
        $maxstrings = (int)(get_config('local_langcrowd', 'maxstrings') ?: self::MAX_PAGE_STRINGS);
        if (count(self::$pagestrings) >= $maxstrings) {
            return;
        }
        if (!self::is_trackable_value($value)) {
            return;
        }
        $cachekey = $comp . '::' . $identifier;
        if (!isset(self::$pagestrings[$cachekey])) {
            self::$pagestrings[$cachekey] = [
                'component' => $comp,
                'key'       => $identifier,
                'value'     => $value,
            ];
        }
    }

    /**
     * Whether a rendered value can be matched to a DOM text node and is worth tracking.
     *
     * Rejects very short strings (usually punctuation), strings containing HTML markup,
     * and strings with embedded newlines — none of which match a plain text node.
     *
     * @param string $value
     * @return bool
     */
    protected static function is_trackable_value(string $value): bool {
        return mb_strlen($value) >= 3
            && $value === strip_tags($value)
            && strpos($value, "\n") === false
            && strpos($value, "\r") === false;
    }

    /**
     * Returns true only for normal HTTP page requests where the overlay is rendered
     * (not CLI, cron or AJAX). This is when page strings are collected for the footer.
     *
     * @return bool
     */
    protected function is_collecting_context(): bool {
        if (self::$collectcontext !== null) {
            return self::$collectcontext;
        }
        // Moodle's setup.php always defines these constants (true for CLI/AJAX/cron).
        self::$collectcontext = self::is_serving_context()
            && (!defined('AJAX_SCRIPT') || !AJAX_SCRIPT);
        return self::$collectcontext;
    }

    /**
     * Returns true for any request that renders content to a user (web or AJAX), so
     * promoted (locked/pushed) translations are served everywhere they can appear.
     * CLI and cron are excluded — they don't render and shouldn't pay the DB cost.
     *
     * @return bool
     */
    protected function is_serving_context(): bool {
        if (self::$servecontext !== null) {
            return self::$servecontext;
        }
        self::$servecontext = (!defined('CLI_SCRIPT') || !CLI_SCRIPT)
            && (!defined('CRON_SCRIPT') || !CRON_SCRIPT);
        return self::$servecontext;
    }
}
