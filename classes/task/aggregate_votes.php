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
 * Scheduled task: aggregate_votes
 *
 * @package    local_langcrowd
 * @copyright  2026 Adam Jenkins <adam@wisecat.net>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_langcrowd\task;

/**
 * Recalculates vote counts and applies the threshold lock as a safety net.
 */
class aggregate_votes extends \core\task\scheduled_task {
    /**
     * Returns the human-readable task name.
     *
     * @return string
     */
    public function get_name(): string {
        return get_string('task_aggregate_votes', 'local_langcrowd');
    }

    /**
     * Recalculates votecount for every string (so counts heal after votes are
     * deleted, e.g. by a privacy request) and, when a threshold is set, locks the
     * pending/pushed strings that have reached it.
     */
    public function execute(): void {
        global $DB;

        $threshold = (int)get_config('local_langcrowd', 'threshold');

        $now     = time();
        $strings = $DB->get_recordset_sql(
            "SELECT s.id, s.status, s.votecount, COALESCE(v.approves, 0) AS newvotecount
               FROM {local_langcrowd_strings} s
          LEFT JOIN (SELECT stringid, COUNT(*) AS approves
                       FROM {local_langcrowd_votes}
                      WHERE vote = 1
                   GROUP BY stringid) v ON v.stringid = s.id"
        );

        foreach ($strings as $str) {
            $votecount = (int)$str->newvotecount;

            // Only pending/pushed strings lock by threshold; 'pushed' is otherwise preserved
            // so the translation keeps being served, and 'locked' is never changed here.
            $locks     = $threshold > 0 && $votecount >= $threshold && in_array($str->status, ['pending', 'pushed'], true);
            $newstatus = $locks ? 'locked' : $str->status;

            if ($votecount !== (int)$str->votecount || $newstatus !== $str->status) {
                // Conditional on the status read above, so an admin action taken while the
                // task runs is not overwritten with this snapshot.
                $DB->execute(
                    "UPDATE {local_langcrowd_strings}
                        SET votecount = :votecount, status = :newstatus, timemodified = :now
                      WHERE id = :id AND status = :oldstatus",
                    [
                        'votecount' => $votecount,
                        'newstatus' => $newstatus,
                        'now'       => $now,
                        'id'        => $str->id,
                        'oldstatus' => $str->status,
                    ]
                );
            }
        }
        $strings->close();
    }
}
