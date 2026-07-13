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
 * Tests for the submit_suggestion external function.
 *
 * @package    local_langcrowd
 * @copyright  2026 Adam Jenkins <adam@wisecat.net>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_langcrowd\external;

/**
 * Unit tests for the submit_suggestion external function.
 *
 * @group local_langcrowd
 * @covers \local_langcrowd\external\submit_suggestion
 */
final class submit_suggestion_test extends \advanced_testcase {
    /**
     * Inserts a pending string row and returns its id.
     *
     * @param array $overrides
     * @return int
     */
    private function make_string(array $overrides = []): int {
        global $DB;
        $now = time();
        return $DB->insert_record('local_langcrowd_strings', (object)array_merge([
            'component'    => 'mod_forum',
            'stringkey'    => 'modulename',
            'lang'         => 'en',
            'sourcevalue'  => 'Forum',
            'currentvalue' => 'Forum',
            'votecount'    => 0,
            'status'       => 'pending',
            'timecreated'  => $now,
            'timemodified' => $now,
        ], $overrides));
    }

    /**
     * Executes submit_suggestion and returns the cleaned result.
     *
     * @param int    $stringid
     * @param string $suggestion
     * @return array
     */
    private function call(int $stringid, string $suggestion): array {
        $result = submit_suggestion::execute($stringid, $suggestion);
        return (array)\core_external\external_api::clean_returnvalue(submit_suggestion::execute_returns(), $result);
    }

    /**
     * Enables the plugin with a vote threshold of 3.
     */
    protected function enable(): void {
        set_config('enabled', 1, 'local_langcrowd');
        set_config('threshold', 3, 'local_langcrowd');
    }

    public function test_suggestion_recorded_with_reject_vote(): void {
        global $DB;
        $this->resetAfterTest();
        $this->enable();
        $user = self::getDataGenerator()->create_user();
        $this->setUser($user);
        $sid = $this->make_string();

        $result = $this->call($sid, '  Better translation  ');

        $this->assertTrue($result['success']);
        $sug = $DB->get_record('local_langcrowd_suggestions', ['stringid' => $sid, 'userid' => $user->id], '*', MUST_EXIST);
        $this->assertSame('Better translation', $sug->suggestion);
        $this->assertSame('pending', $sug->status);
        // A reject vote is recorded alongside.
        $vote = $DB->get_record('local_langcrowd_votes', ['stringid' => $sid, 'userid' => $user->id], '*', MUST_EXIST);
        $this->assertSame(-1, (int)$vote->vote);
    }

    public function test_empty_suggestion_rejected(): void {
        $this->resetAfterTest();
        $this->enable();
        $this->setUser(self::getDataGenerator()->create_user());
        $sid = $this->make_string();

        $this->expectException(\invalid_parameter_exception::class);
        $this->call($sid, '   ');
    }

    public function test_suggestion_on_locked_string_is_refused(): void {
        global $DB;
        $this->resetAfterTest();
        $this->enable();
        $this->setUser(self::getDataGenerator()->create_user());
        $sid = $this->make_string(['status' => 'locked']);

        $result = $this->call($sid, 'nope');

        $this->assertFalse($result['success']);
        $this->assertSame(0, $DB->count_records('local_langcrowd_suggestions', ['stringid' => $sid]));
    }

    public function test_does_not_add_second_reject_vote(): void {
        global $DB;
        $this->resetAfterTest();
        $this->enable();
        $user = self::getDataGenerator()->create_user();
        $this->setUser($user);
        $sid = $this->make_string();

        // Pre-existing approve vote should not be duplicated by a suggestion.
        $DB->insert_record('local_langcrowd_votes', (object)[
            'stringid' => $sid, 'userid' => $user->id, 'vote' => 1, 'timecreated' => time(),
        ]);

        $this->call($sid, 'alt');

        $this->assertSame(1, $DB->count_records('local_langcrowd_votes', ['stringid' => $sid, 'userid' => $user->id]));
    }
}
