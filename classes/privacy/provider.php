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
 * @copyright  2026 hama.history@gmail.com
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_langcrowd\privacy;

use core_privacy\local\metadata\collection;
use core_privacy\local\request\approved_contextlist;
use core_privacy\local\request\approved_userlist;
use core_privacy\local\request\contextlist;
use core_privacy\local\request\userlist;
use core_privacy\local\request\writer;

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
                'userid'      => 'privacy:metadata:local_langcrowd_votes:userid',
                'vote'        => 'privacy:metadata:local_langcrowd_votes:vote',
                'timecreated' => 'privacy:metadata:local_langcrowd_votes:timecreated',
            ],
            'privacy:metadata:local_langcrowd_votes'
        );
        $collection->add_database_table(
            'local_langcrowd_suggestions',
            [
                'userid'      => 'privacy:metadata:local_langcrowd_suggestions:userid',
                'suggestion'  => 'privacy:metadata:local_langcrowd_suggestions:suggestion',
                'timecreated' => 'privacy:metadata:local_langcrowd_suggestions:timecreated',
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

        $userid  = $contextlist->get_user()->id;
        $context = \context_system::instance();

        $votes = $DB->get_records('local_langcrowd_votes', ['userid' => $userid]);
        if ($votes) {
            writer::with_context($context)->export_data(
                [get_string('pluginname', 'local_langcrowd'), 'votes'],
                (object)['votes' => array_values($votes)]
            );
        }

        $suggestions = $DB->get_records('local_langcrowd_suggestions', ['userid' => $userid]);
        if ($suggestions) {
            writer::with_context($context)->export_data(
                [get_string('pluginname', 'local_langcrowd'), 'suggestions'],
                (object)['suggestions' => array_values($suggestions)]
            );
        }
    }

    /**
     * Deletes all personal data for the given user in the given contexts.
     *
     * @param approved_contextlist $contextlist
     */
    public static function delete_data_for_user(approved_contextlist $contextlist): void {
        global $DB;

        $userid = $contextlist->get_user()->id;
        $DB->delete_records('local_langcrowd_votes', ['userid' => $userid]);
        $DB->delete_records('local_langcrowd_suggestions', ['userid' => $userid]);
    }

    /**
     * Deletes personal data for the given users in the given context.
     *
     * @param approved_userlist $userlist
     */
    public static function delete_data_for_users(approved_userlist $userlist): void {
        global $DB;

        foreach ($userlist->get_userids() as $userid) {
            $DB->delete_records('local_langcrowd_votes', ['userid' => $userid]);
            $DB->delete_records('local_langcrowd_suggestions', ['userid' => $userid]);
        }
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
    }
}
