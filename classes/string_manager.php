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
 * @copyright  2026 hama.history@gmail.com
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
     * Tri-state: null = not yet determined, false = not a web request, true = web request.
     *
     * @var bool|null
     */
    protected static ?bool $webcontext = null;

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
        if (!$this->is_web_context()) {
            return;
        }
        global $DB;
        try {
            $records = $DB->get_records(
                'local_langcrowd_strings',
                ['lang' => current_language(), 'status' => 'locked'],
                '',
                'component, stringkey, currentvalue, sourcevalue'
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

        // Serve a promoted (locked) translation when one exists and no substitution is needed.
        if ($a === null && $lang === null && isset(self::$promotedstrings[$cachekey])) {
            $result = self::$promotedstrings[$cachekey];
            // Track the promoted value so DOM text nodes (which show the promoted text) match.
            if (
                $this->is_web_context()
                    && count(self::$pagestrings) < (int)(get_config('local_langcrowd', 'maxstrings') ?: self::MAX_PAGE_STRINGS)
                    && $comp !== 'local_langcrowd'
                    && mb_strlen($result) >= 3
                    && $result === strip_tags($result)
                    && strpos($result, "\n") === false
                    && strpos($result, "\r") === false
            ) {
                if (!isset(self::$pagestrings[$cachekey])) {
                    self::$pagestrings[$cachekey] = [
                        'component' => $comp,
                        'key'       => $identifier,
                        'value'     => $result,
                    ];
                }
            }
            return $result;
        }

        $result = parent::get_string($identifier, $component, $a, $lang);

        // Only track static strings (no parameter substitution) in web contexts.
        $maxstrings = (int)(get_config('local_langcrowd', 'maxstrings') ?: self::MAX_PAGE_STRINGS);
        if (
            $a === null
                && count(self::$pagestrings) < $maxstrings
                && $this->is_web_context()
        ) {
            $comp = empty($component) ? 'moodle' : $component;

            // Skip the plugin's own strings to avoid noise in the UI.
            if ($comp === 'local_langcrowd') {
                return $result;
            }

            // Skip very short strings — they are usually punctuation or single letters.
            if (mb_strlen($result) < 3) {
                return $result;
            }

            // Skip strings containing HTML markup — they can't match DOM text nodes.
            if ($result !== strip_tags($result)) {
                return $result;
            }

            // Skip strings with embedded newlines — they break text-node matching.
            if (strpos($result, "\n") !== false || strpos($result, "\r") !== false) {
                return $result;
            }

            $cachekey = $comp . '::' . $identifier;
            if (!isset(self::$pagestrings[$cachekey])) {
                self::$pagestrings[$cachekey] = [
                    'component' => $comp,
                    'key'       => $identifier,
                    'value'     => $result,
                ];
            }
        }

        return $result;
    }

    /**
     * Returns true only for normal HTTP page requests, not CLI/cron/AJAX.
     *
     * @return bool
     */
    protected function is_web_context(): bool {
        if (self::$webcontext !== null) {
            return self::$webcontext;
        }
        // Moodle's setup.php always defines these constants (as false for web, true for CLI/AJAX).
        // We must check their value, not just whether they are defined.
        self::$webcontext = (!defined('CLI_SCRIPT') || !CLI_SCRIPT)
            && (!defined('AJAX_SCRIPT') || !AJAX_SCRIPT)
            && (!defined('CRON_SCRIPT') || !CRON_SCRIPT);
        return self::$webcontext;
    }
}
