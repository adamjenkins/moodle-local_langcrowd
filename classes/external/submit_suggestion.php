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
 * External function: submit_suggestion
 *
 * @package    local_langcrowd
 * @copyright  2026 hama.history@gmail.com
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_langcrowd\external;

use core_external\external_api;
use core_external\external_function_parameters;
use core_external\external_single_structure;
use core_external\external_value;

/**
 * Stores an alternative translation suggestion and records a reject vote.
 */
class submit_suggestion extends external_api {
    /**
     * Returns the parameter specification.
     *
     * @return external_function_parameters
     */
    public static function execute_parameters(): external_function_parameters {
        return new external_function_parameters([
            'stringid'   => new external_value(PARAM_INT, 'The string DB ID'),
            'suggestion' => new external_value(PARAM_TEXT, 'The suggested alternative translation'),
        ]);
    }

    /**
     * Stores an alternative translation suggestion and records a reject vote.
     *
     * @param int    $stringid
     * @param string $suggestion
     * @return array
     */
    public static function execute(int $stringid, string $suggestion): array {
        global $DB, $USER;

        $params = self::validate_parameters(self::execute_parameters(), [
            'stringid'   => $stringid,
            'suggestion' => $suggestion,
        ]);

        require_login();
        $context = \context_system::instance();
        self::validate_context($context);
        require_capability('local/langcrowd:suggest', $context);

        $suggestion = trim($params['suggestion']);
        if ($suggestion === '') {
            throw new \invalid_parameter_exception('Suggestion cannot be empty.');
        }

        // Verify the string record exists.
        $DB->get_record('local_langcrowd_strings', ['id' => $params['stringid']], 'id', MUST_EXIST);

        $now = time();

        $row               = new \stdClass();
        $row->stringid     = $params['stringid'];
        $row->userid       = $USER->id;
        $row->suggestion   = $suggestion;
        $row->status       = 'pending';
        $row->timecreated  = $now;
        $row->timemodified = $now;
        $DB->insert_record('local_langcrowd_suggestions', $row);

        // Record a reject vote if the user has not yet voted on this string.
        if (!$DB->record_exists('local_langcrowd_votes', ['stringid' => $params['stringid'], 'userid' => $USER->id])) {
            $vote              = new \stdClass();
            $vote->stringid    = $params['stringid'];
            $vote->userid      = $USER->id;
            $vote->vote        = -1;
            $vote->timecreated = $now;
            $DB->insert_record('local_langcrowd_votes', $vote);
        }

        return ['success' => true];
    }

    /**
     * Returns the return value specification.
     *
     * @return external_single_structure
     */
    public static function execute_returns(): external_single_structure {
        return new external_single_structure([
            'success' => new external_value(PARAM_BOOL, 'Whether the suggestion was recorded'),
        ]);
    }
}
