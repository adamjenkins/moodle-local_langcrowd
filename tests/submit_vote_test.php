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
 * Tests for the submit_vote external function.
 *
 * @package    local_langcrowd
 * @copyright  2026 Adam Jenkins <adam@wisecat.net>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_langcrowd\external;

/**
 * Unit tests for the submit_vote external function.
 *
 * @group local_langcrowd
 * @covers \local_langcrowd\external\submit_vote
 */
final class submit_vote_test extends \advanced_testcase {
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
     * Executes submit_vote and returns the cleaned result.
     *
     * @param int $stringid
     * @param int $vote
     * @return array
     */
    private function call(int $stringid, int $vote): array {
        $result = submit_vote::execute($stringid, $vote);
        return (array)\core_external\external_api::clean_returnvalue(submit_vote::execute_returns(), $result);
    }

    /**
     * Enables the plugin with a vote threshold of 3.
     */
    protected function enable(): void {
        set_config('enabled', 1, 'local_langcrowd');
        set_config('threshold', 3, 'local_langcrowd');
    }

    public function test_first_approve_vote_recorded(): void {
        $this->resetAfterTest();
        $this->enable();
        $user = self::getDataGenerator()->create_user();
        $this->setUser($user);
        $sid = $this->make_string();

        $result = $this->call($sid, 1);

        $this->assertTrue($result['success']);
        $this->assertSame(1, $result['votecount']);
        $this->assertSame('pending', $result['status']);
    }

    public function test_threshold_locks_string(): void {
        global $DB;
        $this->resetAfterTest();
        $this->enable();
        $sid = $this->make_string();

        for ($i = 0; $i < 3; $i++) {
            $this->setUser(self::getDataGenerator()->create_user());
            $result = $this->call($sid, 1);
        }

        $this->assertSame('locked', $result['status']);
        $this->assertSame('locked', $DB->get_field('local_langcrowd_strings', 'status', ['id' => $sid]));
    }

    public function test_changing_vote_updates_not_duplicates(): void {
        global $DB;
        $this->resetAfterTest();
        $this->enable();
        $user = self::getDataGenerator()->create_user();
        $this->setUser($user);
        $sid = $this->make_string();

        $this->call($sid, 1);
        $this->call($sid, -1);

        $this->assertSame(1, $DB->count_records('local_langcrowd_votes', ['stringid' => $sid, 'userid' => $user->id]));
        $this->assertSame(0, (int)$DB->get_field('local_langcrowd_strings', 'votecount', ['id' => $sid]));
    }

    public function test_admin_vote_locks_immediately_when_enabled(): void {
        $this->resetAfterTest();
        $this->enable();
        set_config('adminvote_locks', 1, 'local_langcrowd');
        $this->setAdminUser();
        $sid = $this->make_string();

        $result = $this->call($sid, 1);

        $this->assertSame('locked', $result['status']);
    }

    public function test_vote_on_locked_string_is_rejected(): void {
        $this->resetAfterTest();
        $this->enable();
        $this->setUser(self::getDataGenerator()->create_user());
        $sid = $this->make_string(['status' => 'locked']);

        $result = $this->call($sid, 1);

        $this->assertFalse($result['success']);
    }

    public function test_pushed_status_preserved_below_threshold(): void {
        global $DB;
        $this->resetAfterTest();
        $this->enable();
        $this->setUser(self::getDataGenerator()->create_user());
        $sid = $this->make_string(['status' => 'pushed']);

        $result = $this->call($sid, 1);

        $this->assertSame('pushed', $result['status']);
        $this->assertSame('pushed', $DB->get_field('local_langcrowd_strings', 'status', ['id' => $sid]));
    }

    public function test_invalid_vote_value_rejected(): void {
        $this->resetAfterTest();
        $this->enable();
        $this->setUser(self::getDataGenerator()->create_user());
        $sid = $this->make_string();

        $this->expectException(\invalid_parameter_exception::class);
        $this->call($sid, 5);
    }

    public function test_role_restriction_blocks_direct_call(): void {
        $this->resetAfterTest();
        $this->enable();
        // Restrict to a role the user does not hold.
        set_config('allowed_roles', '999', 'local_langcrowd');
        $this->setUser(self::getDataGenerator()->create_user());
        $sid = $this->make_string();

        $this->expectException(\moodle_exception::class);
        $this->call($sid, 1);
    }
}
