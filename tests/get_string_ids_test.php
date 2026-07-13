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
 *
 * @group local_langcrowd
 * @covers \local_langcrowd\external\get_string_ids
 */
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

    public function test_registers_new_strings(): void {
        global $DB;
        $this->resetAfterTest();
        $this->enable();
        $this->setUser(self::getDataGenerator()->create_user());

        $result = $this->call([
            ['component' => 'mod_forum', 'key' => 'modulename', 'value' => 'Forum'],
            ['component' => 'mod_quiz', 'key' => 'modulename', 'value' => 'Quiz'],
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

        $first = $this->call([['component' => 'mod_forum', 'key' => 'modulename', 'value' => 'Forum']]);
        $second = $this->call([['component' => 'mod_forum', 'key' => 'modulename', 'value' => 'Forum']]);

        $this->assertSame(1, $DB->count_records('local_langcrowd_strings'));
        $this->assertSame($first[0]['stringid'], $second[0]['stringid']);
    }

    public function test_reports_existing_user_vote(): void {
        global $DB, $USER;
        $this->resetAfterTest();
        $this->enable();
        $this->setUser(self::getDataGenerator()->create_user());

        $first = $this->call([['component' => 'mod_forum', 'key' => 'modulename', 'value' => 'Forum']]);
        $sid = $first[0]['stringid'];
        $DB->insert_record('local_langcrowd_votes', (object)[
            'stringid' => $sid, 'userid' => $USER->id, 'vote' => 1, 'timecreated' => time(),
        ]);

        $second = $this->call([['component' => 'mod_forum', 'key' => 'modulename', 'value' => 'Forum']]);
        $this->assertTrue($second[0]['voted']);
        $this->assertSame(1, $second[0]['vote']);
    }

    public function test_invalid_keys_skipped(): void {
        global $DB;
        $this->resetAfterTest();
        $this->enable();
        $this->setUser(self::getDataGenerator()->create_user());

        $result = $this->call([
            ['component' => 'mod_forum', 'key' => 'good_key', 'value' => 'Good'],
            ['component' => 'mod_forum', 'key' => 'bad key with spaces!', 'value' => 'Bad'],
        ]);

        $this->assertCount(1, $result);
        $this->assertSame(1, $DB->count_records('local_langcrowd_strings'));
    }

    public function test_role_restriction_blocks_direct_call(): void {
        $this->resetAfterTest();
        $this->enable();
        set_config('allowed_roles', '999', 'local_langcrowd');
        $this->setUser(self::getDataGenerator()->create_user());

        $this->expectException(\moodle_exception::class);
        $this->call([['component' => 'mod_forum', 'key' => 'modulename', 'value' => 'Forum']]);
    }
}
