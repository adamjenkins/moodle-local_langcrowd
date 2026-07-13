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
 * External function: submit_vote
 *
 * @package    local_langcrowd
 * @copyright  2026 Adam Jenkins <adam@wisecat.net>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_langcrowd\external;

use core_external\external_api;
use core_external\external_function_parameters;
use core_external\external_single_structure;
use core_external\external_value;
use local_langcrowd\access;

/**
 * Records an approve (1) or reject (-1) vote for a language string.
 */
class submit_vote extends external_api {
    /**
     * Returns the parameter specification.
     *
     * @return external_function_parameters
     */
    public static function execute_parameters(): external_function_parameters {
        return new external_function_parameters([
            'stringid' => new external_value(PARAM_INT, 'The string DB ID'),
            'vote'     => new external_value(PARAM_INT, 'Vote value: 1 to approve, -1 to reject, 0 to withdraw'),
        ]);
    }

    /**
     * Records an approve or reject vote for the given string.
     *
     * @param int $stringid
     * @param int $vote
     * @return array
     */
    public static function execute(int $stringid, int $vote): array {
        global $DB, $USER;

        $params = self::validate_parameters(self::execute_parameters(), [
            'stringid' => $stringid,
            'vote'     => $vote,
        ]);

        require_login();
        $context = \context_system::instance();
        self::validate_context($context);
        require_capability('local/langcrowd:vote', $context);

        if (!in_array($params['vote'], [1, -1, 0], true)) {
            throw new \invalid_parameter_exception('Vote must be 1, -1 or 0.');
        }

        $strrecord = $DB->get_record('local_langcrowd_strings', ['id' => $params['stringid']], '*', MUST_EXIST);

        // Enforce the enabled/role/language gate server-side using the string's language.
        access::require_can_participate($USER->id, $strrecord->lang);

        if ($strrecord->status === 'locked') {
            return [
                'success'   => false,
                'votecount' => (int)$strrecord->votecount,
                'status'    => $strrecord->status,
            ];
        }

        $now = time();

        if ($params['vote'] === 0) {
            // Withdraw: remove this user's vote entirely (the undo affordance).
            $DB->delete_records('local_langcrowd_votes', [
                'stringid' => $params['stringid'],
                'userid'   => $USER->id,
            ]);
        } else {
            // Upsert the vote record.
            $existing = $DB->get_record('local_langcrowd_votes', [
                'stringid' => $params['stringid'],
                'userid'   => $USER->id,
            ]);

            if ($existing) {
                $DB->set_field('local_langcrowd_votes', 'vote', $params['vote'], ['id' => $existing->id]);
            } else {
                $voterow              = new \stdClass();
                $voterow->stringid   = $params['stringid'];
                $voterow->userid     = $USER->id;
                $voterow->vote       = $params['vote'];
                $voterow->timecreated = $now;
                try {
                    $DB->insert_record('local_langcrowd_votes', $voterow);
                } catch (\dml_write_exception $e) {
                    // A concurrent vote from the same user won the unique index race; update it.
                    $DB->set_field(
                        'local_langcrowd_votes',
                        'vote',
                        $params['vote'],
                        ['stringid' => $params['stringid'], 'userid' => $USER->id]
                    );
                }
            }
        }

        // Recalculate approve count.
        $votecount = (int)$DB->count_records_select(
            'local_langcrowd_votes',
            'stringid = ? AND vote = 1',
            [$params['stringid']]
        );

        $newstatus = self::resolve_status($strrecord->status, $params['vote'], $votecount);

        $DB->update_record('local_langcrowd_strings', (object)[
            'id'           => $params['stringid'],
            'votecount'    => $votecount,
            'status'       => $newstatus,
            'timemodified' => $now,
        ]);

        return [
            'success'   => true,
            'votecount' => $votecount,
            'status'    => $newstatus,
        ];
    }

    /**
     * Decides the new string status after a vote.
     *
     * An approve vote from a site admin locks immediately when that setting is on;
     * otherwise the string locks once approve votes reach the threshold; otherwise
     * a 'pushed' string keeps being served while a 'pending' string stays pending.
     *
     * @param string $currentstatus Existing status of the string.
     * @param int    $vote          The vote just cast (1 or -1).
     * @param int    $votecount     Current approve-vote count.
     * @return string
     */
    protected static function resolve_status(string $currentstatus, int $vote, int $votecount): string {
        $threshold  = (int)get_config('local_langcrowd', 'threshold');
        $adminlocks = (bool)get_config('local_langcrowd', 'adminvote_locks');

        if ($vote === 1 && $adminlocks && is_siteadmin()) {
            return 'locked';
        }
        if ($threshold > 0 && $votecount >= $threshold) {
            return 'locked';
        }
        // Preserve 'pushed' status so the translation keeps being served while voting continues.
        return ($currentstatus === 'pushed') ? 'pushed' : 'pending';
    }

    /**
     * Returns the return value specification.
     *
     * @return external_single_structure
     */
    public static function execute_returns(): external_single_structure {
        return new external_single_structure([
            'success'   => new external_value(PARAM_BOOL, 'Whether the vote was recorded'),
            'votecount' => new external_value(PARAM_INT, 'Updated approve vote count'),
            'status'    => new external_value(PARAM_ALPHA, 'Updated string status'),
        ]);
    }
}
