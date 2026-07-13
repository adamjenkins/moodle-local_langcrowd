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
 * String-status operations for local_langcrowd admin actions.
 *
 * @package    local_langcrowd
 * @copyright  2026 Adam Jenkins <adam@wisecat.net>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_langcrowd;

/**
 * Centralises the admin state transitions for strings and suggestions.
 *
 * Keeping these together (rather than inline in the report scripts) removes
 * duplication and, crucially, guarantees that every "reset the vote cycle"
 * transition also deletes the underlying vote rows — otherwise a reset string
 * re-locks as soon as the aggregate task recounts the stale votes.
 */
class manager {
    /**
     * Admin override: lock a string immediately without changing its votes.
     *
     * @param int $stringid
     */
    public static function lock_string(int $stringid): void {
        global $DB;
        $DB->update_record('local_langcrowd_strings', (object)[
            'id'           => $stringid,
            'status'       => 'locked',
            'timemodified' => time(),
        ]);
        get_string_manager()->reset_caches();
    }

    /**
     * Revert a locked/pushed string to pending: reset the value to the source,
     * clear the vote count, and delete the vote rows so it does not re-lock.
     *
     * @param int $stringid
     */
    public static function revert_string(int $stringid): void {
        global $DB;
        $string = $DB->get_record('local_langcrowd_strings', ['id' => $stringid], 'id, sourcevalue', MUST_EXIST);
        $DB->update_record('local_langcrowd_strings', (object)[
            'id'           => $stringid,
            'status'       => 'pending',
            'votecount'    => 0,
            'currentvalue' => $string->sourcevalue,
            'timemodified' => time(),
        ]);
        // Delete the votes too, otherwise the aggregate task (or the next vote)
        // recounts them and immediately re-locks the string.
        $DB->delete_records('local_langcrowd_votes', ['stringid' => $stringid]);
        get_string_manager()->reset_caches();
    }

    /**
     * Apply a user suggestion as the string's active translation.
     *
     * @param int  $suggestionid
     * @param bool $lock true to lock immediately (Approve), false to serve while voting continues (Push).
     * @return \stdClass the suggestion record that was applied
     */
    public static function apply_suggestion(int $suggestionid, bool $lock): \stdClass {
        global $DB;
        $suggestion = $DB->get_record('local_langcrowd_suggestions', ['id' => $suggestionid], '*', MUST_EXIST);
        $DB->update_record('local_langcrowd_strings', (object)[
            'id'           => $suggestion->stringid,
            'currentvalue' => $suggestion->suggestion,
            'votecount'    => 0,
            'status'       => $lock ? 'locked' : 'pushed',
            'timemodified' => time(),
        ]);
        // Reset the vote cycle so votes are cast fresh on the new value.
        $DB->delete_records('local_langcrowd_votes', ['stringid' => $suggestion->stringid]);
        $DB->set_field('local_langcrowd_suggestions', 'status', 'promoted', ['id' => $suggestionid]);
        get_string_manager()->reset_caches();
        return $suggestion;
    }

    /**
     * Reject a suggestion, leaving the active translation unchanged.
     *
     * @param int $suggestionid
     */
    public static function reject_suggestion(int $suggestionid): void {
        global $DB;
        $DB->update_record('local_langcrowd_suggestions', (object)[
            'id'           => $suggestionid,
            'status'       => 'rejected',
            'timemodified' => time(),
        ]);
    }

    /**
     * Locks each of the given strings (admin override).
     *
     * @param int[] $stringids
     */
    public static function lock_strings(array $stringids): void {
        foreach ($stringids as $id) {
            self::lock_string((int)$id);
        }
    }

    /**
     * Reverts each of the given strings to pending.
     *
     * @param int[] $stringids
     */
    public static function revert_strings(array $stringids): void {
        foreach ($stringids as $id) {
            self::revert_string((int)$id);
        }
    }

    /**
     * Applies each of the given suggestions (bulk Approve or Push).
     *
     * @param int[] $suggestionids
     * @param bool  $lock
     */
    public static function apply_suggestions(array $suggestionids, bool $lock): void {
        foreach ($suggestionids as $id) {
            self::apply_suggestion((int)$id, $lock);
        }
    }

    /**
     * Rejects each of the given suggestions.
     *
     * @param int[] $suggestionids
     */
    public static function reject_suggestions(array $suggestionids): void {
        foreach ($suggestionids as $id) {
            self::reject_suggestion((int)$id);
        }
    }
}
