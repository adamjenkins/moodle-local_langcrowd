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
 * Privacy provider for local_langcrowd.
 *
 * @package    local_langcrowd
 * @copyright  2026 Adam Jenkins <adam@wisecat.net>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_langcrowd\privacy;

use core_privacy\local\metadata\collection;
use core_privacy\local\request\approved_contextlist;
use core_privacy\local\request\approved_userlist;
use core_privacy\local\request\contextlist;
use core_privacy\local\request\userlist;
use core_privacy\local\request\transform;
use core_privacy\local\request\writer;
use local_langcrowd\manager;

/**
 * GDPR privacy provider — declares and handles user data in votes and suggestions tables.
 */
class provider implements
    \core_privacy\local\metadata\provider,
    \core_privacy\local\request\core_userlist_provider,
    \core_privacy\local\request\plugin\provider {
    /**
     * Declares the personal data this plugin stores.
     *
     * @param collection $collection
     * @return collection
     */
    public static function get_metadata(collection $collection): collection {
        $collection->add_database_table(
            'local_langcrowd_votes',
            [
                'stringid'    => 'privacy:metadata:local_langcrowd_votes:stringid',
                'userid'      => 'privacy:metadata:local_langcrowd_votes:userid',
                'vote'        => 'privacy:metadata:local_langcrowd_votes:vote',
                'timecreated' => 'privacy:metadata:local_langcrowd_votes:timecreated',
            ],
            'privacy:metadata:local_langcrowd_votes'
        );
        $collection->add_database_table(
            'local_langcrowd_suggestions',
            [
                'stringid'     => 'privacy:metadata:local_langcrowd_suggestions:stringid',
                'userid'       => 'privacy:metadata:local_langcrowd_suggestions:userid',
                'suggestion'   => 'privacy:metadata:local_langcrowd_suggestions:suggestion',
                'status'       => 'privacy:metadata:local_langcrowd_suggestions:status',
                'timecreated'  => 'privacy:metadata:local_langcrowd_suggestions:timecreated',
                'timemodified' => 'privacy:metadata:local_langcrowd_suggestions:timemodified',
            ],
            'privacy:metadata:local_langcrowd_suggestions'
        );
        return $collection;
    }

    /**
     * Returns the contexts that contain personal data for the given user.
     *
     * @param int $userid
     * @return contextlist
     */
    public static function get_contexts_for_userid(int $userid): contextlist {
        $contextlist = new contextlist();
        $sql = 'SELECT cx.id
                  FROM {context} cx
                 WHERE cx.contextlevel = :contextlevel
                   AND (EXISTS (SELECT 1 FROM {local_langcrowd_votes} v WHERE v.userid = :uid1)
                        OR EXISTS (SELECT 1 FROM {local_langcrowd_suggestions} s WHERE s.userid = :uid2))';
        $contextlist->add_from_sql($sql, [
            'contextlevel' => CONTEXT_SYSTEM,
            'uid1'         => $userid,
            'uid2'         => $userid,
        ]);
        return $contextlist;
    }

    /**
     * Returns all users who have personal data in the given context.
     *
     * @param \core_privacy\local\request\userlist $userlist
     */
    public static function get_users_in_context(userlist $userlist): void {
        if ($userlist->get_context()->contextlevel !== CONTEXT_SYSTEM) {
            return;
        }
        $userlist->add_from_sql('userid', 'SELECT DISTINCT userid FROM {local_langcrowd_votes}', []);
        $userlist->add_from_sql('userid', 'SELECT DISTINCT userid FROM {local_langcrowd_suggestions}', []);
    }

    /**
     * Exports personal data for the given user in the given contexts.
     *
     * @param approved_contextlist $contextlist
     */
    public static function export_user_data(approved_contextlist $contextlist): void {
        global $DB;

        if (!self::includes_system_context($contextlist)) {
            return;
        }
        $userid  = $contextlist->get_user()->id;
        $context = \context_system::instance();

        // Export which string each row is about, not just an internal id.
        $votes = $DB->get_records_sql(
            "SELECT v.id, s.component, s.stringkey, s.lang, v.vote, v.timecreated
               FROM {local_langcrowd_votes} v
               JOIN {local_langcrowd_strings} s ON s.id = v.stringid
              WHERE v.userid = ?
           ORDER BY v.timecreated, v.id",
            [$userid]
        );
        if ($votes) {
            $data = [];
            foreach ($votes as $vote) {
                $data[] = (object)[
                    'component'   => $vote->component,
                    'stringkey'   => $vote->stringkey,
                    'lang'        => $vote->lang,
                    'vote'        => (int)$vote->vote,
                    'timecreated' => transform::datetime($vote->timecreated),
                ];
            }
            writer::with_context($context)->export_data(
                [get_string('pluginname', 'local_langcrowd'), 'votes'],
                (object)['votes' => $data]
            );
        }

        $suggestions = $DB->get_records_sql(
            "SELECT g.id, s.component, s.stringkey, s.lang, g.suggestion, g.status, g.timecreated, g.timemodified
               FROM {local_langcrowd_suggestions} g
               JOIN {local_langcrowd_strings} s ON s.id = g.stringid
              WHERE g.userid = ?
           ORDER BY g.timecreated, g.id",
            [$userid]
        );
        if ($suggestions) {
            $data = [];
            foreach ($suggestions as $suggestion) {
                $data[] = (object)[
                    'component'    => $suggestion->component,
                    'stringkey'    => $suggestion->stringkey,
                    'lang'         => $suggestion->lang,
                    'suggestion'   => $suggestion->suggestion,
                    'status'       => $suggestion->status,
                    'timecreated'  => transform::datetime($suggestion->timecreated),
                    'timemodified' => transform::datetime($suggestion->timemodified),
                ];
            }
            writer::with_context($context)->export_data(
                [get_string('pluginname', 'local_langcrowd'), 'suggestions'],
                (object)['suggestions' => $data]
            );
        }
    }

    /**
     * Deletes all personal data for the given user in the given contexts.
     *
     * @param approved_contextlist $contextlist
     */
    public static function delete_data_for_user(approved_contextlist $contextlist): void {
        if (!self::includes_system_context($contextlist)) {
            return;
        }
        self::delete_user_rows([(int)$contextlist->get_user()->id]);
    }

    /**
     * Deletes personal data for the given users in the given context.
     *
     * @param approved_userlist $userlist
     */
    public static function delete_data_for_users(approved_userlist $userlist): void {
        if ($userlist->get_context()->contextlevel !== CONTEXT_SYSTEM) {
            return;
        }
        self::delete_user_rows(array_map('intval', $userlist->get_userids()));
    }

    /**
     * Whether an approved context list covers the system context, where all this plugin's data lives.
     *
     * @param approved_contextlist $contextlist
     * @return bool
     */
    protected static function includes_system_context(approved_contextlist $contextlist): bool {
        foreach ($contextlist->get_contexts() as $context) {
            if ($context->contextlevel == CONTEXT_SYSTEM) {
                return true;
            }
        }
        return false;
    }

    /**
     * Deletes the votes and suggestions of the given users, then recounts the approve
     * totals of the strings they had voted on so no stale count survives them.
     *
     * @param int[] $userids
     */
    protected static function delete_user_rows(array $userids): void {
        global $DB;

        if (empty($userids)) {
            return;
        }
        [$insql, $params] = $DB->get_in_or_equal($userids, SQL_PARAMS_NAMED);
        $stringids = $DB->get_fieldset_select('local_langcrowd_votes', 'DISTINCT stringid', "userid $insql", $params);
        $DB->delete_records_select('local_langcrowd_votes', "userid $insql", $params);
        $DB->delete_records_select('local_langcrowd_suggestions', "userid $insql", $params);
        manager::recount_votes($stringids);
    }

    /**
     * Deletes all personal data for all users in the specified context.
     *
     * @param \context $context
     */
    public static function delete_data_for_all_users_in_context(\context $context): void {
        global $DB;

        if ($context->contextlevel !== CONTEXT_SYSTEM) {
            return;
        }
        $DB->delete_records('local_langcrowd_votes');
        $DB->delete_records('local_langcrowd_suggestions');
        $DB->set_field('local_langcrowd_strings', 'votecount', 0);
    }
}
