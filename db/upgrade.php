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
 * Database upgrade steps for local_langcrowd.
 *
 * @package    local_langcrowd
 * @copyright  2026 Adam Jenkins <adam@wisecat.net>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

/**
 * Upgrades the local_langcrowd plugin database schema.
 *
 * @param int $oldversion
 * @return bool
 */
function xmldb_local_langcrowd_upgrade($oldversion) {
    global $DB;

    $dbman = $DB->get_manager();

    if ($oldversion < 2026061601) {
        // Define table local_langcrowd_strings.
        $table = new xmldb_table('local_langcrowd_strings');
        $table->add_field('id', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, XMLDB_SEQUENCE);
        $table->add_field('component', XMLDB_TYPE_CHAR, '64', null, XMLDB_NOTNULL);
        $table->add_field('stringkey', XMLDB_TYPE_CHAR, '128', null, XMLDB_NOTNULL);
        $table->add_field('lang', XMLDB_TYPE_CHAR, '20', null, XMLDB_NOTNULL);
        $table->add_field('sourcevalue', XMLDB_TYPE_TEXT, null, null, null);
        $table->add_field('currentvalue', XMLDB_TYPE_TEXT, null, null, null);
        $table->add_field('votecount', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0');
        $table->add_field('status', XMLDB_TYPE_CHAR, '20', null, XMLDB_NOTNULL, null, 'pending');
        $table->add_field('timecreated', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0');
        $table->add_field('timemodified', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0');
        $table->add_key('primary', XMLDB_KEY_PRIMARY, ['id']);
        $table->add_index('component_key_lang', XMLDB_INDEX_UNIQUE, ['component', 'stringkey', 'lang']);
        $table->add_index('status', XMLDB_INDEX_NOTUNIQUE, ['status']);

        if (!$dbman->table_exists($table)) {
            $dbman->create_table($table);
        }

        // Define table local_langcrowd_votes.
        $table = new xmldb_table('local_langcrowd_votes');
        $table->add_field('id', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, XMLDB_SEQUENCE);
        $table->add_field('stringid', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL);
        $table->add_field('userid', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL);
        $table->add_field('vote', XMLDB_TYPE_INTEGER, '4', null, XMLDB_NOTNULL);
        $table->add_field('timecreated', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0');
        $table->add_key('primary', XMLDB_KEY_PRIMARY, ['id']);
        $table->add_key('fk_stringid', XMLDB_KEY_FOREIGN, ['stringid'], 'local_langcrowd_strings', ['id']);
        $table->add_key('fk_userid', XMLDB_KEY_FOREIGN, ['userid'], 'user', ['id']);
        $table->add_index('stringid_userid', XMLDB_INDEX_UNIQUE, ['stringid', 'userid']);

        if (!$dbman->table_exists($table)) {
            $dbman->create_table($table);
        }

        // Define table local_langcrowd_suggestions.
        $table = new xmldb_table('local_langcrowd_suggestions');
        $table->add_field('id', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, XMLDB_SEQUENCE);
        $table->add_field('stringid', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL);
        $table->add_field('userid', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL);
        $table->add_field('suggestion', XMLDB_TYPE_TEXT, null, null, null);
        $table->add_field('status', XMLDB_TYPE_CHAR, '20', null, XMLDB_NOTNULL, null, 'pending');
        $table->add_field('timecreated', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0');
        $table->add_field('timemodified', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0');
        $table->add_key('primary', XMLDB_KEY_PRIMARY, ['id']);
        $table->add_key('fk_stringid', XMLDB_KEY_FOREIGN, ['stringid'], 'local_langcrowd_strings', ['id']);
        $table->add_key('fk_userid', XMLDB_KEY_FOREIGN, ['userid'], 'user', ['id']);
        $table->add_index('status', XMLDB_INDEX_NOTUNIQUE, ['status']);

        if (!$dbman->table_exists($table)) {
            $dbman->create_table($table);
        }

        upgrade_plugin_savepoint(true, 2026061601, 'local', 'langcrowd');
    }

    if ($oldversion < 2026071400) {
        // Repair sourcevalue: it used to store the client-submitted value (the
        // string as rendered in the voter's language), so wherever the target
        // language already had a translation the "English source" was not
        // English. Recompute it from the English lang pack.
        $stringmanager = get_string_manager();
        $rs = $DB->get_recordset('local_langcrowd_strings', null, '', 'id, component, stringkey, sourcevalue');
        foreach ($rs as $rec) {
            if (!$stringmanager->string_exists($rec->stringkey, $rec->component)) {
                // Component uninstalled or string gone — nothing to recompute.
                continue;
            }
            $english = $stringmanager->get_string($rec->stringkey, $rec->component, null, 'en');
            if ($english !== $rec->sourcevalue) {
                $DB->set_field('local_langcrowd_strings', 'sourcevalue', $english, ['id' => $rec->id]);
            }
        }
        $rs->close();

        upgrade_plugin_savepoint(true, 2026071400, 'local', 'langcrowd');
    }

    return true;
}
