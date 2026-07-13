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
 * Tests for the privacy provider.
 *
 * @package    local_langcrowd
 * @copyright  2026 Adam Jenkins <adam@wisecat.net>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_langcrowd\privacy;

use core_privacy\local\request\approved_contextlist;
use core_privacy\local\request\approved_userlist;
use core_privacy\local\request\writer;

/**
 * Unit tests for the privacy provider.
 *
 * @group local_langcrowd
 * @covers \local_langcrowd\privacy\provider
 */
final class privacy_provider_test extends \core_privacy\tests\provider_testcase {
    /**
     * Seeds a string, a vote and a suggestion for the given user.
     *
     * @param int $userid
     * @return int string id
     */
    private function seed_for_user(int $userid): int {
        global $DB;
        static $counter = 0;
        $counter++;
        $now = time();
        // Unique stringkey per call so seeding multiple users doesn't collide on the unique index.
        $sid = $DB->insert_record('local_langcrowd_strings', (object)[
            'component' => 'mod_forum', 'stringkey' => 'modulename' . $counter, 'lang' => 'en',
            'sourcevalue' => 'Forum', 'currentvalue' => 'Forum', 'votecount' => 0,
            'status' => 'pending', 'timecreated' => $now, 'timemodified' => $now,
        ]);
        $DB->insert_record('local_langcrowd_votes', (object)[
            'stringid' => $sid, 'userid' => $userid, 'vote' => 1, 'timecreated' => $now,
        ]);
        $DB->insert_record('local_langcrowd_suggestions', (object)[
            'stringid' => $sid, 'userid' => $userid, 'suggestion' => 'Board',
            'status' => 'pending', 'timecreated' => $now, 'timemodified' => $now,
        ]);
        return $sid;
    }

    public function test_metadata(): void {
        $this->resetAfterTest();
        $collection = new \core_privacy\local\metadata\collection('local_langcrowd');
        $collection = provider::get_metadata($collection);
        $this->assertNotEmpty($collection->get_collection());
    }

    public function test_get_contexts_for_userid(): void {
        $this->resetAfterTest();
        $user = self::getDataGenerator()->create_user();
        $this->seed_for_user($user->id);

        $contextlist = provider::get_contexts_for_userid($user->id);
        $this->assertCount(1, $contextlist);
        $this->assertEquals(
            \context_system::instance()->id,
            $contextlist->get_contextids()[0]
        );
    }

    public function test_get_users_in_context(): void {
        $this->resetAfterTest();
        $user = self::getDataGenerator()->create_user();
        $this->seed_for_user($user->id);

        $userlist = new \core_privacy\local\request\userlist(\context_system::instance(), 'local_langcrowd');
        provider::get_users_in_context($userlist);
        $this->assertTrue(in_array($user->id, $userlist->get_userids()));
    }

    public function test_export_user_data(): void {
        $this->resetAfterTest();
        $user = self::getDataGenerator()->create_user();
        $this->seed_for_user($user->id);

        $contextlist = new approved_contextlist($user, 'local_langcrowd', [\context_system::instance()->id]);
        provider::export_user_data($contextlist);

        $writer = writer::with_context(\context_system::instance());
        $this->assertTrue($writer->has_any_data());
    }

    public function test_delete_data_for_user(): void {
        global $DB;
        $this->resetAfterTest();
        $user = self::getDataGenerator()->create_user();
        $other = self::getDataGenerator()->create_user();
        $this->seed_for_user($user->id);
        $this->seed_for_user($other->id);

        $contextlist = new approved_contextlist($user, 'local_langcrowd', [\context_system::instance()->id]);
        provider::delete_data_for_user($contextlist);

        $this->assertSame(0, $DB->count_records('local_langcrowd_votes', ['userid' => $user->id]));
        $this->assertSame(0, $DB->count_records('local_langcrowd_suggestions', ['userid' => $user->id]));
        $this->assertSame(1, $DB->count_records('local_langcrowd_votes', ['userid' => $other->id]));
    }

    public function test_delete_data_for_users(): void {
        global $DB;
        $this->resetAfterTest();
        $user = self::getDataGenerator()->create_user();
        $other = self::getDataGenerator()->create_user();
        $this->seed_for_user($user->id);
        $this->seed_for_user($other->id);

        $userlist = new approved_userlist(\context_system::instance(), 'local_langcrowd', [$user->id]);
        provider::delete_data_for_users($userlist);

        $this->assertSame(0, $DB->count_records('local_langcrowd_votes', ['userid' => $user->id]));
        $this->assertSame(1, $DB->count_records('local_langcrowd_votes', ['userid' => $other->id]));
    }

    public function test_delete_data_for_all_users_in_context(): void {
        global $DB;
        $this->resetAfterTest();
        $user = self::getDataGenerator()->create_user();
        $this->seed_for_user($user->id);

        provider::delete_data_for_all_users_in_context(\context_system::instance());

        $this->assertSame(0, $DB->count_records('local_langcrowd_votes'));
        $this->assertSame(0, $DB->count_records('local_langcrowd_suggestions'));
    }
}
