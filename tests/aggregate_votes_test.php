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
 * Tests for the aggregate_votes scheduled task.
 *
 * @package    local_langcrowd
 * @copyright  2026 Adam Jenkins <adam@wisecat.net>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_langcrowd\task;

/**
 * Unit tests for the aggregate_votes scheduled task.
 *
 * @group local_langcrowd
 * @covers \local_langcrowd\task\aggregate_votes
 */
final class aggregate_votes_test extends \advanced_testcase {
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
     * @param int $count number of approve votes to add
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

    public function test_locks_when_threshold_reached(): void {
        global $DB;
        $this->resetAfterTest();
        set_config('threshold', 3, 'local_langcrowd');
        $sid = $this->make_string();
        $this->add_approves($sid, 3);

        (new aggregate_votes())->execute();

        $rec = $DB->get_record('local_langcrowd_strings', ['id' => $sid]);
        $this->assertSame('locked', $rec->status);
        $this->assertSame(3, (int)$rec->votecount);
    }

    public function test_recounts_but_keeps_pending_below_threshold(): void {
        global $DB;
        $this->resetAfterTest();
        set_config('threshold', 5, 'local_langcrowd');
        $sid = $this->make_string();
        $this->add_approves($sid, 2);

        (new aggregate_votes())->execute();

        $rec = $DB->get_record('local_langcrowd_strings', ['id' => $sid]);
        $this->assertSame('pending', $rec->status);
        $this->assertSame(2, (int)$rec->votecount);
    }

    public function test_pushed_preserved_below_threshold(): void {
        global $DB;
        $this->resetAfterTest();
        set_config('threshold', 5, 'local_langcrowd');
        $sid = $this->make_string(['status' => 'pushed']);
        $this->add_approves($sid, 2);

        (new aggregate_votes())->execute();

        $this->assertSame('pushed', $DB->get_field('local_langcrowd_strings', 'status', ['id' => $sid]));
    }

    public function test_pushed_locks_at_threshold(): void {
        global $DB;
        $this->resetAfterTest();
        set_config('threshold', 2, 'local_langcrowd');
        $sid = $this->make_string(['status' => 'pushed']);
        $this->add_approves($sid, 2);

        (new aggregate_votes())->execute();

        $this->assertSame('locked', $DB->get_field('local_langcrowd_strings', 'status', ['id' => $sid]));
    }

    public function test_reject_votes_do_not_count(): void {
        global $DB;
        $this->resetAfterTest();
        set_config('threshold', 1, 'local_langcrowd');
        $sid = $this->make_string();
        $u = self::getDataGenerator()->create_user();
        $DB->insert_record('local_langcrowd_votes', (object)[
            'stringid' => $sid, 'userid' => $u->id, 'vote' => -1, 'timecreated' => time(),
        ]);

        (new aggregate_votes())->execute();

        $rec = $DB->get_record('local_langcrowd_strings', ['id' => $sid]);
        $this->assertSame('pending', $rec->status);
        $this->assertSame(0, (int)$rec->votecount);
    }

    public function test_noop_when_threshold_zero(): void {
        global $DB;
        $this->resetAfterTest();
        set_config('threshold', 0, 'local_langcrowd');
        $sid = $this->make_string();
        $this->add_approves($sid, 3);

        (new aggregate_votes())->execute();

        // With threshold disabled the task returns early; votecount stays as seeded (0).
        $this->assertSame('pending', $DB->get_field('local_langcrowd_strings', 'status', ['id' => $sid]));
    }
}
