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
 * Tests for the event observers.
 *
 * @package    local_langcrowd
 * @copyright  2026 Adam Jenkins <adam@wisecat.net>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_langcrowd;

/**
 * Unit tests for the user_deleted observer and manager::delete_user_data().
 */
#[\PHPUnit\Framework\Attributes\Group('local_langcrowd')]
#[\PHPUnit\Framework\Attributes\CoversClass(\local_langcrowd\observer::class)]
#[\PHPUnit\Framework\Attributes\CoversClass(\local_langcrowd\manager::class)]
final class observer_test extends \advanced_testcase {
    /**
     * Inserts a string row and returns its id.
     *
     * @param string $key
     * @param int $votecount
     * @return int
     */
    private function make_string(string $key, int $votecount): int {
        global $DB;
        $now = time();
        return (int)$DB->insert_record('local_langcrowd_strings', (object)[
            'component' => 'mod_forum', 'stringkey' => $key, 'lang' => 'en',
            'sourcevalue' => 'Forum', 'currentvalue' => 'Forum', 'votecount' => $votecount,
            'status' => 'pending', 'timecreated' => $now, 'timemodified' => $now,
        ]);
    }

    /**
     * Gives the user an approve vote and a pending suggestion on the string.
     *
     * @param int $stringid
     * @param int $userid
     */
    private function vote_and_suggest(int $stringid, int $userid): void {
        global $DB;
        $now = time();
        $DB->insert_record('local_langcrowd_votes', (object)[
            'stringid' => $stringid, 'userid' => $userid, 'vote' => 1, 'timecreated' => $now,
        ]);
        $DB->insert_record('local_langcrowd_suggestions', (object)[
            'stringid' => $stringid, 'userid' => $userid, 'suggestion' => 'Board',
            'status' => 'pending', 'timecreated' => $now, 'timemodified' => $now,
        ]);
    }

    public function test_deleting_a_user_removes_their_votes_and_suggestions(): void {
        global $DB;
        $this->resetAfterTest();
        $sid = $this->make_string('modulename', 2);
        $keep = self::getDataGenerator()->create_user();
        $gone = self::getDataGenerator()->create_user();
        $this->vote_and_suggest($sid, $keep->id);
        $this->vote_and_suggest($sid, $gone->id);

        delete_user($gone);

        $this->assertFalse($DB->record_exists('local_langcrowd_votes', ['userid' => $gone->id]));
        $this->assertFalse($DB->record_exists('local_langcrowd_suggestions', ['userid' => $gone->id]));
        $this->assertTrue($DB->record_exists('local_langcrowd_votes', ['userid' => $keep->id]));
        $this->assertTrue($DB->record_exists('local_langcrowd_suggestions', ['userid' => $keep->id]));
        // The deleted account's vote no longer counts toward the lock threshold.
        $this->assertEquals(1, $DB->get_field('local_langcrowd_strings', 'votecount', ['id' => $sid]));
    }

    public function test_delete_user_data_recounts_only_affected_strings(): void {
        global $DB;
        $this->resetAfterTest();
        $voted = $this->make_string('modulename', 5);
        $untouched = $this->make_string('forum', 7);
        $user = self::getDataGenerator()->create_user();
        $this->vote_and_suggest($voted, $user->id);

        manager::delete_user_data([$user->id, $user->id]);
        manager::delete_user_data([]);

        $this->assertEquals(0, $DB->get_field('local_langcrowd_strings', 'votecount', ['id' => $voted]));
        $this->assertEquals(7, $DB->get_field('local_langcrowd_strings', 'votecount', ['id' => $untouched]));
        $this->assertEquals(0, $DB->count_records('local_langcrowd_suggestions'));
    }
}
