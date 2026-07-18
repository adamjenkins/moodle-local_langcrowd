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
 * Upgrade helper functions for local_langcrowd.
 *
 * @package    local_langcrowd
 * @copyright  2026 Adam Jenkins <adam@wisecat.net>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

/**
 * Recomputes sourcevalue from the English language pack for existing rows.
 *
 * Until version 2026071400 sourcevalue stored the client-submitted value (the
 * string as rendered in the voter's language), so wherever the target language
 * already had a translation the "English source" was not English. Rows whose
 * key no longer exists are left untouched.
 */
function local_langcrowd_upgrade_repair_sourcevalues(): void {
    global $DB;

    $stringmanager = get_string_manager();
    $rs = $DB->get_recordset('local_langcrowd_strings', null, '', 'id, component, stringkey, sourcevalue');
    foreach ($rs as $rec) {
        if (!$stringmanager->string_exists($rec->stringkey, $rec->component)) {
            continue;
        }
        $english = $stringmanager->get_string($rec->stringkey, $rec->component, null, 'en');
        if ($english !== $rec->sourcevalue) {
            $DB->set_field('local_langcrowd_strings', 'sourcevalue', $english, ['id' => $rec->id]);
        }
    }
    $rs->close();
}

/**
 * Repairs currentvalue for rows written while the field was client-supplied.
 *
 * Until version 2026071700 the get_string_ids web service persisted the
 * client-submitted rendered value as currentvalue, which the custom string
 * manager later serves verbatim through get_string() once a row is promoted
 * (stored-XSS vector). currentvalue is now resolved server-side; this helper
 * brings existing rows in line:
 *
 * - pending rows are recomputed from the lang pack (their value is display-only
 *   and must match what a voter actually sees);
 * - locked/pushed rows containing markup are recomputed too — curated values
 *   are plain text by construction, so markup can only be injected data;
 * - rows whose key is unknown to the en pack cannot be recomputed: those with
 *   markup are deleted together with their votes and suggestions, clean ones
 *   are kept.
 */
function local_langcrowd_upgrade_repair_currentvalues(): void {
    global $DB;

    $stringmanager = get_string_manager();
    $rs = $DB->get_recordset('local_langcrowd_strings');
    foreach ($rs as $rec) {
        $current = (string)$rec->currentvalue;
        $hasmarkup = $current !== strip_tags($current);

        if (!$stringmanager->string_exists($rec->stringkey, $rec->component)) {
            if ($hasmarkup) {
                $DB->delete_records('local_langcrowd_votes', ['stringid' => $rec->id]);
                $DB->delete_records('local_langcrowd_suggestions', ['stringid' => $rec->id]);
                $DB->delete_records('local_langcrowd_strings', ['id' => $rec->id]);
            }
            continue;
        }

        if ($rec->status !== 'pending' && !$hasmarkup) {
            continue;
        }

        $resolved = $stringmanager->get_string($rec->stringkey, $rec->component, null, $rec->lang);
        if ($resolved !== $rec->currentvalue) {
            $DB->set_field('local_langcrowd_strings', 'currentvalue', $resolved, ['id' => $rec->id]);
        }
    }
    $rs->close();
}
