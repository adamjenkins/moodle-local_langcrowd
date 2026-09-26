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

use local_langcrowd\local\text_safety;

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
     * Revert a locked/pushed string to pending: reset the value to what the installed
     * language pack says for the row's language, clear the vote count, and delete the
     * vote rows so it does not re-lock.
     *
     * @param int $stringid
     */
    public static function revert_string(int $stringid): void {
        global $DB;
        $string = $DB->get_record(
            'local_langcrowd_strings',
            ['id' => $stringid],
            'id, component, stringkey, lang, sourcevalue',
            MUST_EXIST
        );
        // Not sourcevalue: that is the English text, which would then be exported into
        // (and could overwrite) the target language's pack.
        $stringmanager = get_string_manager();
        $value = $stringmanager->string_exists($string->stringkey, $string->component)
            ? $stringmanager->get_string($string->stringkey, $string->component, null, $string->lang)
            : $string->sourcevalue;
        $DB->update_record('local_langcrowd_strings', (object)[
            'id'           => $stringid,
            'status'       => 'pending',
            'votecount'    => 0,
            'currentvalue' => $value,
            'timemodified' => time(),
        ]);
        // Delete the votes too, otherwise the aggregate task (or the next vote)
        // recounts them and immediately re-locks the string.
        $DB->delete_records('local_langcrowd_votes', ['stringid' => $stringid]);
        get_string_manager()->reset_caches();
    }

    /**
     * Apply a pending user suggestion as the string's active translation.
     *
     * Suggestions that are no longer pending (already applied or rejected) are left
     * alone, and so is text that is not safe to serve or does not keep the source's
     * placeholders: the text is normalised and re-checked here because rows stored by
     * older versions never passed the intake rules (see text_safety).
     *
     * @param int  $suggestionid
     * @param bool $lock true to lock immediately (Approve), false to serve while voting continues (Push).
     * @return \stdClass|null the suggestion record that was applied, or null if it was skipped
     */
    public static function apply_suggestion(int $suggestionid, bool $lock): ?\stdClass {
        global $DB;
        $suggestion = $DB->get_record('local_langcrowd_suggestions', ['id' => $suggestionid], '*', MUST_EXIST);
        if ($suggestion->status !== 'pending') {
            return null;
        }
        $value = text_safety::normalise((string)$suggestion->suggestion);
        $source = (string)$DB->get_field('local_langcrowd_strings', 'sourcevalue', ['id' => $suggestion->stringid]);
        if ($value === '' || !text_safety::is_safe($value) || !text_safety::same_placeholders($value, $source)) {
            return null;
        }
        $DB->update_record('local_langcrowd_strings', (object)[
            'id'           => $suggestion->stringid,
            'currentvalue' => $value,
            'votecount'    => 0,
            'status'       => $lock ? 'locked' : 'pushed',
            'timemodified' => time(),
        ]);
        // Reset the vote cycle so votes are cast fresh on the new value.
        $DB->delete_records('local_langcrowd_votes', ['stringid' => $suggestion->stringid]);
        $DB->update_record('local_langcrowd_suggestions', (object)[
            'id'           => $suggestionid,
            'status'       => 'promoted',
            'timemodified' => time(),
        ]);
        get_string_manager()->reset_caches();
        return $suggestion;
    }

    /**
     * Reject a pending suggestion, leaving the active translation unchanged.
     *
     * @param int $suggestionid
     * @return bool whether the suggestion was pending and is now rejected
     */
    public static function reject_suggestion(int $suggestionid): bool {
        global $DB;
        if (!$DB->record_exists('local_langcrowd_suggestions', ['id' => $suggestionid, 'status' => 'pending'])) {
            return false;
        }
        $DB->update_record('local_langcrowd_suggestions', (object)[
            'id'           => $suggestionid,
            'status'       => 'rejected',
            'timemodified' => time(),
        ]);
        return true;
    }

    /**
     * Locks each of the given strings (admin override), all or nothing.
     *
     * @param int[] $stringids
     */
    public static function lock_strings(array $stringids): void {
        global $DB;
        $transaction = $DB->start_delegated_transaction();
        foreach ($stringids as $id) {
            self::lock_string((int)$id);
        }
        $transaction->allow_commit();
    }

    /**
     * Reverts each of the given strings to pending, all or nothing.
     *
     * @param int[] $stringids
     */
    public static function revert_strings(array $stringids): void {
        global $DB;
        $transaction = $DB->start_delegated_transaction();
        foreach ($stringids as $id) {
            self::revert_string((int)$id);
        }
        $transaction->allow_commit();
    }

    /**
     * Applies each of the given suggestions (bulk Approve or Push), all or nothing.
     *
     * @param int[] $suggestionids
     * @param bool  $lock
     * @return int how many were applied (skipped ones are not pending or not safe to serve)
     */
    public static function apply_suggestions(array $suggestionids, bool $lock): int {
        global $DB;
        $applied = 0;
        $transaction = $DB->start_delegated_transaction();
        foreach ($suggestionids as $id) {
            if (self::apply_suggestion((int)$id, $lock)) {
                $applied++;
            }
        }
        $transaction->allow_commit();
        return $applied;
    }

    /**
     * Rejects each of the given suggestions, all or nothing.
     *
     * @param int[] $suggestionids
     * @return int how many were rejected
     */
    public static function reject_suggestions(array $suggestionids): int {
        global $DB;
        $rejected = 0;
        $transaction = $DB->start_delegated_transaction();
        foreach ($suggestionids as $id) {
            if (self::reject_suggestion((int)$id)) {
                $rejected++;
            }
        }
        $transaction->allow_commit();
        return $rejected;
    }

    /**
     * Recomputes the stored approve count of the given strings from their vote rows,
     * e.g. after votes were deleted by a privacy request. Status is left unchanged.
     *
     * @param int[] $stringids
     */
    public static function recount_votes(array $stringids): void {
        global $DB;
        foreach (array_unique(array_map('intval', $stringids)) as $id) {
            $count = $DB->count_records('local_langcrowd_votes', ['stringid' => $id, 'vote' => 1]);
            $DB->set_field('local_langcrowd_strings', 'votecount', $count, ['id' => $id]);
        }
    }
}
