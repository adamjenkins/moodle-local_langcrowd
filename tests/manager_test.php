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
 * Tests for the admin state-transition manager.
 *
 * @package    local_langcrowd
 * @copyright  2026 Adam Jenkins <adam@wisecat.net>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_langcrowd;

/**
 * Unit tests for the string/suggestion manager.
 *
 * @group local_langcrowd
 * @covers \local_langcrowd\manager
 */
final class manager_test extends \advanced_testcase {
    /**
     * Inserts a string row and returns its id.
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
     * Adds the given number of approve votes from fresh users.
     *
     * @param int $stringid
     * @param int $count
     */
    private function add_approves(int $stringid, int $count): void {
        global $DB;
        for ($i = 0; $i < $count; $i++) {
            $u = self::getDataGenerator()->create_user();
            $DB->insert_record('local_langcrowd_votes', (object)[
                'stringid' => $stringid, 'userid' => $u->id, 'vote' => 1, 'timecreated' => time(),
            ]);
        }
    }

    public function test_lock_string(): void {
        global $DB;
        $this->resetAfterTest();
        $sid = $this->make_string(['votecount' => 2]);

        manager::lock_string($sid);

        $rec = $DB->get_record('local_langcrowd_strings', ['id' => $sid]);
        $this->assertSame('locked', $rec->status);
        // Lock is an override; it must not disturb the vote tally.
        $this->assertSame(2, (int)$rec->votecount);
    }

    public function test_revert_string_deletes_votes_and_resets_value(): void {
        global $DB;
        $this->resetAfterTest();
        $sid = $this->make_string(['status' => 'pushed', 'currentvalue' => 'Board', 'votecount' => 3]);
        $this->add_approves($sid, 3);

        manager::revert_string($sid);

        $rec = $DB->get_record('local_langcrowd_strings', ['id' => $sid]);
        $this->assertSame('pending', $rec->status);
        $this->assertSame(0, (int)$rec->votecount);
        $this->assertSame('Forum', $rec->currentvalue, 'currentvalue should revert to the source');
        $this->assertSame(0, $DB->count_records('local_langcrowd_votes', ['stringid' => $sid]));
    }

    public function test_reverted_string_does_not_relock_on_aggregate(): void {
        global $DB;
        $this->resetAfterTest();
        set_config('threshold', 3, 'local_langcrowd');
        $sid = $this->make_string(['status' => 'locked', 'currentvalue' => 'Board', 'votecount' => 3]);
        $this->add_approves($sid, 3);

        // Revert, then run the aggregate task — the string must stay pending because
        // the stale votes were deleted. This is the regression guard for the unlock bug.
        manager::revert_string($sid);
        (new \local_langcrowd\task\aggregate_votes())->execute();

        $this->assertSame('pending', $DB->get_field('local_langcrowd_strings', 'status', ['id' => $sid]));
        $this->assertSame(0, (int)$DB->get_field('local_langcrowd_strings', 'votecount', ['id' => $sid]));
    }

    public function test_apply_suggestion_promote_locks(): void {
        global $DB;
        $this->resetAfterTest();
        $sid = $this->make_string();
        $this->add_approves($sid, 2);
        $sugid = $DB->insert_record('local_langcrowd_suggestions', (object)[
            'stringid' => $sid, 'userid' => self::getDataGenerator()->create_user()->id,
            'suggestion' => 'Discussion board', 'status' => 'pending',
            'timecreated' => time(), 'timemodified' => time(),
        ]);

        manager::apply_suggestion($sugid, true);

        $rec = $DB->get_record('local_langcrowd_strings', ['id' => $sid]);
        $this->assertSame('locked', $rec->status);
        $this->assertSame('Discussion board', $rec->currentvalue);
        $this->assertSame(0, (int)$rec->votecount);
        $this->assertSame(0, $DB->count_records('local_langcrowd_votes', ['stringid' => $sid]));
        $this->assertSame('promoted', $DB->get_field('local_langcrowd_suggestions', 'status', ['id' => $sugid]));
    }

    public function test_apply_suggestion_push_keeps_open(): void {
        global $DB;
        $this->resetAfterTest();
        $sid = $this->make_string();
        $sugid = $DB->insert_record('local_langcrowd_suggestions', (object)[
            'stringid' => $sid, 'userid' => self::getDataGenerator()->create_user()->id,
            'suggestion' => 'Discussion board', 'status' => 'pending',
            'timecreated' => time(), 'timemodified' => time(),
        ]);

        manager::apply_suggestion($sugid, false);

        $this->assertSame('pushed', $DB->get_field('local_langcrowd_strings', 'status', ['id' => $sid]));
    }

    public function test_reject_suggestion(): void {
        global $DB;
        $this->resetAfterTest();
        $sid = $this->make_string();
        $sugid = $DB->insert_record('local_langcrowd_suggestions', (object)[
            'stringid' => $sid, 'userid' => self::getDataGenerator()->create_user()->id,
            'suggestion' => 'Nope', 'status' => 'pending',
            'timecreated' => time(), 'timemodified' => time(),
        ]);

        manager::reject_suggestion($sugid);

        $this->assertSame('rejected', $DB->get_field('local_langcrowd_suggestions', 'status', ['id' => $sugid]));
        // The active translation is unchanged.
        $this->assertSame('Forum', $DB->get_field('local_langcrowd_strings', 'currentvalue', ['id' => $sid]));
    }

    public function test_bulk_lock_and_revert(): void {
        global $DB;
        $this->resetAfterTest();
        $a = $this->make_string(['stringkey' => 'a']);
        $b = $this->make_string(['stringkey' => 'b']);

        manager::lock_strings([$a, $b]);
        $this->assertSame('locked', $DB->get_field('local_langcrowd_strings', 'status', ['id' => $a]));
        $this->assertSame('locked', $DB->get_field('local_langcrowd_strings', 'status', ['id' => $b]));

        manager::revert_strings([$a, $b]);
        $this->assertSame('pending', $DB->get_field('local_langcrowd_strings', 'status', ['id' => $a]));
        $this->assertSame('pending', $DB->get_field('local_langcrowd_strings', 'status', ['id' => $b]));
    }

    public function test_bulk_reject_suggestions(): void {
        global $DB;
        $this->resetAfterTest();
        $sid = $this->make_string();
        $ids = [];
        foreach (['x', 'y'] as $s) {
            $ids[] = $DB->insert_record('local_langcrowd_suggestions', (object)[
                'stringid' => $sid, 'userid' => self::getDataGenerator()->create_user()->id,
                'suggestion' => $s, 'status' => 'pending',
                'timecreated' => time(), 'timemodified' => time(),
            ]);
        }

        manager::reject_suggestions($ids);

        foreach ($ids as $id) {
            $this->assertSame('rejected', $DB->get_field('local_langcrowd_suggestions', 'status', ['id' => $id]));
        }
    }
}
