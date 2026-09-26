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
 * Tests for the get_string_ids external function.
 *
 * @package    local_langcrowd
 * @copyright  2026 Adam Jenkins <adam@wisecat.net>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_langcrowd\external;

/**
 * Unit tests for the get_string_ids external function.
 */
#[\PHPUnit\Framework\Attributes\Group('local_langcrowd')]
#[\PHPUnit\Framework\Attributes\CoversClass(\local_langcrowd\external\get_string_ids::class)]
final class get_string_ids_test extends \advanced_testcase {
    /**
     * Executes get_string_ids and returns the cleaned result.
     *
     * @param array  $strings
     * @param string $lang
     * @return array
     */
    private function call(array $strings, string $lang = 'en'): array {
        $result = get_string_ids::execute($strings, $lang);
        return (array)\core_external\external_api::clean_returnvalue(get_string_ids::execute_returns(), $result);
    }

    /**
     * Enables the plugin for the current test.
     */
    protected function enable(): void {
        set_config('enabled', 1, 'local_langcrowd');
    }

    /**
     * Installs a minimal 'ja' lang pack in the test dataroot.
     *
     * @param array $forumstrings Optional mod_forum strings to translate.
     */
    protected function install_ja_pack(array $forumstrings = []): void {
        global $CFG;
        make_writable_directory($CFG->dataroot . '/lang/ja');
        file_put_contents(
            $CFG->dataroot . '/lang/ja/langconfig.php',
            "<?php\n\$string['thislanguage'] = 'Japanese';\n"
        );
        if ($forumstrings) {
            $content = "<?php\n";
            foreach ($forumstrings as $key => $value) {
                $content .= '$string[' . var_export($key, true) . '] = ' . var_export($value, true) . ";\n";
            }
            file_put_contents($CFG->dataroot . '/lang/ja/forum.php', $content);
        }
        get_string_manager()->reset_caches();
    }

    public function test_registers_new_strings(): void {
        global $DB;
        $this->resetAfterTest();
        $this->enable();
        $this->setUser(self::getDataGenerator()->create_user());

        $result = $this->call([
            ['component' => 'mod_forum', 'key' => 'modulename'],
            ['component' => 'mod_quiz', 'key' => 'modulename'],
        ]);

        $this->assertCount(2, $result);
        $this->assertSame(2, $DB->count_records('local_langcrowd_strings'));
        foreach ($result as $item) {
            $this->assertGreaterThan(0, $item['stringid']);
            $this->assertSame('pending', $item['status']);
            $this->assertFalse($item['voted']);
        }
    }

    public function test_idempotent_for_existing_strings(): void {
        global $DB;
        $this->resetAfterTest();
        $this->enable();
        $this->setUser(self::getDataGenerator()->create_user());

        $first = $this->call([['component' => 'mod_forum', 'key' => 'modulename']]);
        $second = $this->call([['component' => 'mod_forum', 'key' => 'modulename']]);

        $this->assertSame(1, $DB->count_records('local_langcrowd_strings'));
        $this->assertSame($first[0]['stringid'], $second[0]['stringid']);
    }

    public function test_reports_existing_user_vote(): void {
        global $DB, $USER;
        $this->resetAfterTest();
        $this->enable();
        $this->setUser(self::getDataGenerator()->create_user());

        $first = $this->call([['component' => 'mod_forum', 'key' => 'modulename']]);
        $sid = $first[0]['stringid'];
        $DB->insert_record('local_langcrowd_votes', (object)[
            'stringid' => $sid, 'userid' => $USER->id, 'vote' => 1, 'timecreated' => time(),
        ]);

        $second = $this->call([['component' => 'mod_forum', 'key' => 'modulename']]);
        $this->assertTrue($second[0]['voted']);
        $this->assertSame(1, $second[0]['vote']);
    }

    public function test_invalid_keys_skipped(): void {
        global $DB;
        $this->resetAfterTest();
        $this->enable();
        $this->setUser(self::getDataGenerator()->create_user());

        $result = $this->call([
            ['component' => 'mod_forum', 'key' => 'replies'],
            ['component' => 'mod_forum', 'key' => 'bad key with spaces!'],
        ]);

        $this->assertCount(1, $result);
        $this->assertSame(1, $DB->count_records('local_langcrowd_strings'));
    }

    public function test_source_value_resolved_from_english_pack(): void {
        global $DB;
        $this->resetAfterTest();
        $this->enable();
        $this->setUser(self::getDataGenerator()->create_user());
        $this->install_ja_pack(['modulename' => 'フォーラム']);

        $result = $this->call([
            ['component' => 'mod_forum', 'key' => 'modulename'],
        ], 'ja');

        $rec = $DB->get_record(
            'local_langcrowd_strings',
            ['component' => 'mod_forum', 'stringkey' => 'modulename'],
            '*',
            MUST_EXIST
        );
        $this->assertSame('ja', $rec->lang);
        $this->assertSame('Forum', $rec->sourcevalue);
        $this->assertSame('Forum', $result[0]['source']);
    }

    public function test_currentvalue_resolved_server_side_from_lang_pack(): void {
        global $DB;
        $this->resetAfterTest();
        $this->enable();
        $this->setUser(self::getDataGenerator()->create_user());
        $this->install_ja_pack(['modulename' => 'フォーラム']);

        $this->call([
            ['component' => 'mod_forum', 'key' => 'modulename'],
        ], 'ja');

        $rec = $DB->get_record(
            'local_langcrowd_strings',
            ['component' => 'mod_forum', 'stringkey' => 'modulename'],
            '*',
            MUST_EXIST
        );
        $this->assertSame('フォーラム', $rec->currentvalue);
    }

    public function test_currentvalue_falls_back_to_english_when_untranslated(): void {
        global $DB;
        $this->resetAfterTest();
        $this->enable();
        $this->setUser(self::getDataGenerator()->create_user());
        $this->install_ja_pack();

        $this->call([
            ['component' => 'mod_quiz', 'key' => 'modulename'],
        ], 'ja');

        $rec = $DB->get_record(
            'local_langcrowd_strings',
            ['component' => 'mod_quiz', 'stringkey' => 'modulename'],
            '*',
            MUST_EXIST
        );
        $this->assertSame('Quiz', $rec->currentvalue);
    }

    public function test_client_supplied_value_rejected(): void {
        $this->resetAfterTest();
        $this->enable();
        $this->setUser(self::getDataGenerator()->create_user());

        // The 'value' field was removed from the service: a client (or attacker)
        // sending one must fail parameter validation, never reach the DB.
        $this->expectException(\invalid_parameter_exception::class);
        $this->call([
            ['component' => 'mod_forum', 'key' => 'modulename',
             'value' => '<img src=x onerror=alert(document.cookie)>'],
        ]);
    }

    public function test_strings_unknown_to_english_pack_skipped(): void {
        global $DB;
        $this->resetAfterTest();
        $this->enable();
        $this->setUser(self::getDataGenerator()->create_user());

        $result = $this->call([
            ['component' => 'mod_forum', 'key' => 'nosuchstringkey'],
        ]);

        $this->assertCount(0, $result);
        $this->assertSame(0, $DB->count_records('local_langcrowd_strings'));
    }

    public function test_role_restriction_blocks_direct_call(): void {
        $this->resetAfterTest();
        $this->enable();
        set_config('allowed_roles', '999', 'local_langcrowd');
        $this->setUser(self::getDataGenerator()->create_user());

        $this->expectException(\moodle_exception::class);
        $this->call([['component' => 'mod_forum', 'key' => 'modulename']]);
    }

    public function test_capability_required(): void {
        global $CFG;
        $this->resetAfterTest();
        $this->enable();
        $this->setUser(self::getDataGenerator()->create_user());
        assign_capability('local/langcrowd:vote', CAP_PROHIBIT, $CFG->defaultuserroleid, \context_system::instance(), true);
        accesslib_clear_all_caches_for_unit_testing();

        $this->expectException(\required_capability_exception::class);
        $this->call([['component' => 'mod_forum', 'key' => 'modulename']]);
    }

    public function test_component_spellings_share_one_row(): void {
        global $DB;
        $this->resetAfterTest();
        $this->enable();
        $this->setUser(self::getDataGenerator()->create_user());

        // Plugins such as mod_rememberme call get_string() with both 'forum' and 'mod_forum',
        // and core strings are requested as both 'moodle' and 'core'.
        $result = $this->call([
            ['component' => 'forum', 'key' => 'modulename'],
            ['component' => 'mod_forum', 'key' => 'modulename'],
            ['component' => 'moodle', 'key' => 'login'],
        ]);

        $this->assertSame(1, $DB->count_records('local_langcrowd_strings', ['component' => 'mod_forum']));
        $this->assertSame(1, $DB->count_records('local_langcrowd_strings', ['component' => 'core', 'stringkey' => 'login']));
        $this->assertSame(2, $DB->count_records('local_langcrowd_strings'));
        $this->assertSame($result[0]['stringid'], $result[1]['stringid']);
        $this->assertSame('mod_forum', $result[0]['component']);
    }

    public function test_component_filter_matches_any_spelling(): void {
        global $DB;
        $this->resetAfterTest();
        $this->enable();
        set_config('allowed_components', 'mod_forum', 'local_langcrowd');
        $this->setUser(self::getDataGenerator()->create_user());

        $this->call([
            ['component' => 'forum', 'key' => 'modulename'],
            ['component' => 'quiz', 'key' => 'modulename'],
        ]);

        $this->assertSame(['mod_forum'], array_values($DB->get_fieldset_select('local_langcrowd_strings', 'component', '1 = 1')));
    }
}
