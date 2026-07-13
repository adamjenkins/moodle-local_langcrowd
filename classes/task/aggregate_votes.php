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
     * Recalculates votecount for every pending string and locks those that
     * have reached the configured threshold.
     */
    public function execute(): void {
        global $DB;

        $threshold = (int)get_config('local_langcrowd', 'threshold');
        if ($threshold <= 0) {
            return;
        }

        $now     = time();
        $strings = $DB->get_records_sql(
            "SELECT s.id, s.status, s.votecount, COALESCE(v.approves, 0) AS newvotecount
               FROM {local_langcrowd_strings} s
          LEFT JOIN (SELECT stringid, COUNT(*) AS approves
                       FROM {local_langcrowd_votes}
                      WHERE vote = 1
                   GROUP BY stringid) v ON v.stringid = s.id
              WHERE s.status IN ('pending', 'pushed')"
        );

        foreach ($strings as $str) {
            $votecount = (int)$str->newvotecount;

            // Preserve 'pushed' status when below threshold so the translation keeps being served.
            $newstatus = ($votecount >= $threshold) ? 'locked' : $str->status;

            if ($votecount !== (int)$str->votecount || $newstatus !== $str->status) {
                $DB->update_record('local_langcrowd_strings', (object)[
                    'id'           => $str->id,
                    'votecount'    => $votecount,
                    'status'       => $newstatus,
                    'timemodified' => $now,
                ]);
            }
        }
    }
}
