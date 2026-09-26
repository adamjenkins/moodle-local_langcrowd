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
 * Language pack exporter for local_langcrowd.
 *
 * @package    local_langcrowd
 * @copyright  2026 Adam Jenkins <adam@wisecat.net>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_langcrowd\local;

/**
 * Generates a downloadable zip containing Moodle-format language PHP files.
 */
class exporter {
    /**
     * Builds a zip archive of language files and returns its binary content.
     *
     * @param string $lang       Target language code.
     * @param array  $components Components to include (empty = all).
     * @param string $scope      'locked' for locked strings only, 'all' for all with a currentvalue.
     * @return string            Raw zip binary, or empty string if no data.
     */
    public static function export(string $lang, array $components, string $scope): string {
        return self::build_zip(self::fetch_records($lang, $components, $scope));
    }

    /**
     * Builds a zip archive covering every language that has string records.
     *
     * @param array  $components Components to include (empty = all).
     * @param string $scope      'locked' for locked strings only, 'all' for all with a currentvalue.
     * @return string            Raw zip binary, or empty string if no data.
     */
    public static function export_all_languages(array $components, string $scope): string {
        return self::build_zip(self::fetch_records(null, $components, $scope));
    }

    /**
     * Fetches exportable string records, optionally restricted to one language.
     *
     * @param string|null $lang       Language code, or null for all languages.
     * @param array       $components Components to include (empty = all).
     * @param string      $scope      'locked' or 'all'.
     * @return array
     */
    protected static function fetch_records(?string $lang, array $components, string $scope): array {
        global $DB;

        $where  = 'currentvalue IS NOT NULL AND ' . $DB->sql_isnotempty(
            'local_langcrowd_strings',
            'currentvalue',
            true,
            true
        );
        $sqlparams = [];

        if ($lang !== null) {
            $where .= ' AND lang = :lang';
            $sqlparams['lang'] = $lang;
        }

        if ($scope === 'locked') {
            $where .= " AND status = 'locked'";
        }

        if (!empty($components)) {
            [$insql, $inparams] = $DB->get_in_or_equal($components, SQL_PARAMS_NAMED, 'comp');
            $where     .= " AND component $insql";
            $sqlparams  = array_merge($sqlparams, $inparams);
        }

        return $DB->get_records_select(
            'local_langcrowd_strings',
            $where,
            $sqlparams,
            'lang ASC, component ASC, stringkey ASC'
        );
    }

    /**
     * Builds a language-pack zip from a flat list of string records.
     *
     * @param array $records
     * @return string Raw zip binary, or empty string if no data.
     */
    protected static function build_zip(array $records): string {
        if (empty($records)) {
            return '';
        }

        // Group by language then lang-pack file ('mod_forum' strings live in forum.php,
        // core strings in moodle.php), leaving out values that must not be installed.
        $bylang = [];
        foreach ($records as $rec) {
            if (!self::is_exportable($rec)) {
                continue;
            }
            $bylang[$rec->lang][components::lang_file_name($rec->component)][] = $rec;
        }
        if (empty($bylang)) {
            return '';
        }

        // Build the zip under Moodle's managed temp area; make_request_directory()
        // is auto-removed at the end of the request.
        $tmpfile = make_request_directory() . '/langcrowd_export.zip';
        $zip = new \ZipArchive();
        if ($zip->open($tmpfile, \ZipArchive::CREATE | \ZipArchive::OVERWRITE) !== true) {
            return '';
        }

        foreach ($bylang as $lang => $byfile) {
            foreach ($byfile as $file => $strings) {
                $content = self::generate_lang_file($strings[0]->component, $lang, $strings);
                $zip->addFromString($lang . '/' . $file . '.php', $content);
            }
        }

        $zip->close();
        $binary = file_get_contents($tmpfile);
        unlink($tmpfile);

        return $binary !== false ? $binary : '';
    }

    /**
     * Whether a row's value may go into an installable lang pack.
     *
     * An installed lang pack is served by core with no guard at all, so a value that
     * could break out of an HTML attribute or a JavaScript string (see text_safety) is
     * exported only when it is exactly what the installed lang pack already says. A
     * non-English row still holding the English source (left by older versions' revert)
     * is not exported when the installed pack has its own translation.
     *
     * @param \stdClass $rec
     * @return bool
     */
    protected static function is_exportable(\stdClass $rec): bool {
        $value = (string)$rec->currentvalue;
        $stringmanager = get_string_manager();
        $installed = $stringmanager->string_exists($rec->stringkey, $rec->component)
            ? $stringmanager->get_string($rec->stringkey, $rec->component, null, $rec->lang)
            : null;
        // Older versions reset a reverted row's value to the English source; never ship
        // English into another language's file where the installed pack says otherwise.
        if ($rec->lang !== 'en' && $value === (string)$rec->sourcevalue && $installed !== null && $installed !== $value) {
            return false;
        }
        if ($installed === $value) {
            return true;
        }
        return text_safety::is_safe($value) && text_safety::same_placeholders($value, (string)$rec->sourcevalue);
    }

    /**
     * Generates the content of a single Moodle language PHP file.
     *
     * @param string $component
     * @param string $lang
     * @param array  $strings
     * @return string
     */
    protected static function generate_lang_file(string $component, string $lang, array $strings): string {
        $lines   = [];
        $lines[] = '<?php';
        $lines[] = '// Generated by local_langcrowd on ' . date('Y-m-d H:i:s') . '.';
        $lines[] = '// Component: ' . $component . ' | Language: ' . $lang;
        $lines[] = '';
        $lines[] = 'defined(\'MOODLE_INTERNAL\') || die();';
        $lines[] = '';

        foreach ($strings as $rec) {
            // Use var_export so backslashes, quotes and control characters in
            // user-submitted translations are escaped correctly and the emitted
            // file is always valid, safe-to-load PHP.
            $lines[] = '$string[' . var_export((string)$rec->stringkey, true) . '] = '
                . var_export((string)$rec->currentvalue, true) . ';';
        }

        return implode("\n", $lines) . "\n";
    }

    /**
     * Returns a distinct sorted list of component names that have string records.
     *
     * @param string $lang
     * @return array
     */
    public static function get_components(string $lang): array {
        global $DB;
        $records = $DB->get_fieldset_select(
            'local_langcrowd_strings',
            'DISTINCT component',
            'lang = :lang',
            ['lang' => $lang],
            'component ASC'
        );
        return $records ?: [];
    }

    /**
     * Returns a distinct sorted list of every component that has string records,
     * across all languages. Used to populate the global component filter.
     *
     * @return array
     */
    public static function get_all_components(): array {
        global $DB;
        $records = $DB->get_fieldset_select(
            'local_langcrowd_strings',
            'DISTINCT component',
            '',
            [],
            'component ASC'
        );
        return $records ?: [];
    }

    /**
     * Returns a distinct sorted list of language codes that have string records.
     *
     * @return array
     */
    public static function get_languages(): array {
        global $DB;
        $records = $DB->get_fieldset_select(
            'local_langcrowd_strings',
            'DISTINCT lang',
            '',
            [],
            'lang ASC'
        );
        return $records ?: [];
    }
}
