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
 * Tests for the language pack exporter.
 *
 * @package    local_langcrowd
 * @copyright  2026 Adam Jenkins <adam@wisecat.net>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_langcrowd\local;

/**
 * Unit tests for the language pack exporter.
 */
#[\PHPUnit\Framework\Attributes\Group('local_langcrowd')]
#[\PHPUnit\Framework\Attributes\CoversClass(\local_langcrowd\local\exporter::class)]
final class exporter_test extends \advanced_testcase {
    /**
     * Inserts a string row directly.
     *
     * @param array $overrides
     * @return int
     */
    private function make_string(array $overrides): int {
        global $DB;
        $now = time();
        $record = (object)array_merge([
            'component'    => 'mod_forum',
            'stringkey'    => 'modulename',
            'lang'         => 'en',
            'sourcevalue'  => 'Forum',
            'currentvalue' => 'Forum',
            'votecount'    => 0,
            'status'       => 'locked',
            'timecreated'  => $now,
            'timemodified' => $now,
        ], $overrides);
        return $DB->insert_record('local_langcrowd_strings', $record);
    }

    /**
     * Opens a zip binary produced by the exporter and returns entry name => content.
     *
     * @param string $binary
     * @return array
     */
    private function zip_entries(string $binary): array {
        $path = make_request_directory() . '/export.zip';
        file_put_contents($path, $binary);
        $zip = new \ZipArchive();
        $this->assertTrue($zip->open($path) === true);
        $entries = [];
        for ($i = 0; $i < $zip->numFiles; $i++) {
            $entries[$zip->getNameIndex($i)] = $zip->getFromIndex($i);
        }
        $zip->close();
        return $entries;
    }

    /**
     * Loads a generated lang file the way Moodle would and returns its $string array.
     *
     * @param string $content
     * @return array
     */
    private function load_lang_file(string $content): array {
        $path = make_request_directory() . '/lang.php';
        file_put_contents($path, $content);
        $string = [];
        include($path);
        return $string;
    }

    public function test_export_produces_loadable_php(): void {
        $this->resetAfterTest();
        // Dollar signs and placeholders would be interpolated by naive double-quoted output.
        $tricky = 'Costs $5 for {$a} users; $x = 1;';
        $this->make_string(['stringkey' => 'modulename', 'currentvalue' => $tricky]);

        $entries = $this->zip_entries(exporter::export('en', [], 'all'));
        $string = $this->load_lang_file($entries['en/forum.php']);

        $this->assertSame($tricky, $string['modulename']);
    }

    public function test_unsafe_values_are_not_exported(): void {
        $this->resetAfterTest();
        // An installed lang pack is served by core with no guard, so a value that can break
        // out of an HTML attribute or a JavaScript string must never reach one.
        $this->make_string(['stringkey' => 'modulename', 'currentvalue' => 'Forum" onfocus="alert(1)']);
        $this->make_string(['stringkey' => 'modulenameplural', 'currentvalue' => "\\'; system('id'); \$x='"]);
        $this->make_string(['stringkey' => 'forum', 'currentvalue' => 'Discussion board']);

        $string = $this->load_lang_file($this->zip_entries(exporter::export('en', [], 'all'))['en/forum.php']);

        $this->assertSame(['forum' => 'Discussion board'], $string);
    }

    public function test_unsafe_value_identical_to_installed_pack_is_exported(): void {
        $this->resetAfterTest();
        // Find a real English string that contains a straight quote, e.g. an apostrophe.
        $key = null;
        foreach (get_string_manager()->load_component_strings('core', 'en') as $k => $v) {
            if (strpos($v, "'") !== false && strpos($v, '{') === false) {
                [$key, $value] = [$k, $v];
                break;
            }
        }
        $this->assertNotNull($key, 'core has English strings with apostrophes');
        $this->make_string(['component' => 'core', 'stringkey' => $key, 'sourcevalue' => $value, 'currentvalue' => $value]);

        $string = $this->load_lang_file($this->zip_entries(exporter::export('en', [], 'all'))['en/moodle.php']);

        $this->assertSame($value, $string[$key]);
    }

    public function test_files_are_named_like_lang_pack_files(): void {
        $this->resetAfterTest();
        $this->make_string(['component' => 'core', 'stringkey' => 'login', 'currentvalue' => 'Sign in']);
        $this->make_string(['component' => 'core_admin', 'stringkey' => 'x', 'currentvalue' => 'X']);
        $this->make_string(['component' => 'mod_forum', 'stringkey' => 'modulename', 'currentvalue' => 'Board']);
        $this->make_string(['component' => 'block_html', 'stringkey' => 'pluginname', 'currentvalue' => 'Text']);

        $names = array_keys($this->zip_entries(exporter::export('en', [], 'all')));

        $this->assertEqualsCanonicalizing(['en/moodle.php', 'en/admin.php', 'en/forum.php', 'en/block_html.php'], $names);
    }

    public function test_scope_locked_excludes_pending_and_pushed(): void {
        $this->resetAfterTest();
        $this->make_string(['stringkey' => 'a', 'status' => 'locked', 'currentvalue' => 'Locked']);
        $this->make_string(['stringkey' => 'b', 'status' => 'pending', 'currentvalue' => 'Pending']);
        $this->make_string(['stringkey' => 'c', 'status' => 'pushed', 'currentvalue' => 'Pushed']);

        $string = $this->load_lang_file($this->zip_entries(exporter::export('en', [], 'locked'))['en/forum.php']);

        $this->assertArrayHasKey('a', $string);
        $this->assertArrayNotHasKey('b', $string);
        $this->assertArrayNotHasKey('c', $string);
    }

    public function test_scope_all_includes_translated_non_locked(): void {
        $this->resetAfterTest();
        $this->make_string(['stringkey' => 'a', 'status' => 'locked', 'currentvalue' => 'Locked']);
        $this->make_string(['stringkey' => 'b', 'status' => 'pushed', 'currentvalue' => 'Pushed']);

        $string = $this->load_lang_file($this->zip_entries(exporter::export('en', [], 'all'))['en/forum.php']);

        $this->assertArrayHasKey('a', $string);
        $this->assertArrayHasKey('b', $string);
    }

    public function test_component_filter(): void {
        $this->resetAfterTest();
        $this->make_string(['component' => 'mod_forum', 'stringkey' => 'a', 'currentvalue' => 'A']);
        $this->make_string(['component' => 'mod_quiz', 'stringkey' => 'b', 'currentvalue' => 'B']);

        $entries = $this->zip_entries(exporter::export('en', ['mod_forum'], 'all'));

        $this->assertSame(['en/forum.php'], array_keys($entries));
    }

    public function test_export_returns_empty_when_no_data(): void {
        $this->resetAfterTest();
        $this->assertSame('', exporter::export('fr', [], 'all'));
    }

    public function test_export_returns_empty_when_everything_is_unsafe(): void {
        $this->resetAfterTest();
        $this->make_string(['stringkey' => 'evil', 'currentvalue' => 'x" onfocus="y']);
        $this->assertSame('', exporter::export('en', [], 'all'));
    }

    public function test_get_languages_and_components(): void {
        $this->resetAfterTest();
        $this->make_string(['component' => 'mod_forum', 'lang' => 'en', 'stringkey' => 'a']);
        $this->make_string(['component' => 'mod_quiz', 'lang' => 'th', 'stringkey' => 'b']);

        $this->assertEqualsCanonicalizing(['en', 'th'], exporter::get_languages());
        $this->assertSame(['mod_forum'], array_values(exporter::get_components('en')));
        $this->assertEqualsCanonicalizing(['mod_forum', 'mod_quiz'], exporter::get_all_components());
    }

    public function test_export_all_languages_includes_every_language(): void {
        $this->resetAfterTest();
        $this->make_string(['component' => 'mod_forum', 'lang' => 'en', 'stringkey' => 'a', 'currentvalue' => 'Forum']);
        $this->make_string(['component' => 'mod_forum', 'lang' => 'th', 'stringkey' => 'a', 'currentvalue' => 'กระดาน']);

        $entries = $this->zip_entries(exporter::export_all_languages([], 'all'));

        $this->assertStringContainsString('Forum', $entries['en/forum.php']);
        $this->assertStringContainsString('กระดาน', $entries['th/forum.php']);
    }

    /**
     * Installs a minimal 'ja' lang pack in the test dataroot with the given mod_forum strings.
     *
     * @param array $forumstrings
     */
    private function install_ja_pack(array $forumstrings): void {
        global $CFG;
        make_writable_directory($CFG->dataroot . '/lang/ja');
        file_put_contents($CFG->dataroot . '/lang/ja/langconfig.php', "<?php\n\$string['thislanguage'] = 'Japanese';\n");
        $content = "<?php\n";
        foreach ($forumstrings as $key => $value) {
            $content .= '$string[' . var_export($key, true) . '] = ' . var_export($value, true) . ";\n";
        }
        file_put_contents($CFG->dataroot . '/lang/ja/forum.php', $content);
        get_string_manager()->reset_caches();
    }

    public function test_english_left_by_old_revert_is_not_exported_over_a_translation(): void {
        $this->resetAfterTest();
        $this->install_ja_pack(['modulename' => 'フォーラム']);
        // A ja row whose value an older version's Remove reset to the English source.
        $this->make_string(['lang' => 'ja', 'stringkey' => 'modulename', 'currentvalue' => 'Forum', 'status' => 'locked']);
        // A ja string the pack does not translate: English is what the pack serves, so it may go out.
        $this->make_string(['lang' => 'ja', 'stringkey' => 'modulenameplural', 'sourcevalue' => 'Forums',
            'currentvalue' => 'Forums', 'status' => 'locked']);

        $string = $this->load_lang_file($this->zip_entries(exporter::export('ja', [], 'all'))['ja/forum.php']);

        $this->assertSame(['modulenameplural' => 'Forums'], $string);
    }
}
