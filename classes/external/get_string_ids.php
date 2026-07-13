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
 * External function: get_string_ids
 *
 * @package    local_langcrowd
 * @copyright  2026 Adam Jenkins <adam@wisecat.net>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_langcrowd\external;

use core_external\external_api;
use core_external\external_function_parameters;
use core_external\external_multiple_structure;
use core_external\external_single_structure;
use core_external\external_value;
use local_langcrowd\access;

/**
 * Registers page strings in the DB (if new) and returns IDs + user's votes.
 */
class get_string_ids extends external_api {
    /** Hard cap on strings accepted per call, mirroring the client-side page cap. */
    protected const MAX_STRINGS_PER_CALL = 5000;

    /**
     * Returns the parameter specification.
     *
     * @return external_function_parameters
     */
    public static function execute_parameters(): external_function_parameters {
        return new external_function_parameters([
            'strings' => new external_multiple_structure(
                new external_single_structure([
                    'component' => new external_value(PARAM_COMPONENT, 'Component name'),
                    'key'       => new external_value(PARAM_RAW, 'String identifier'),
                    'value'     => new external_value(PARAM_RAW, 'Rendered string value'),
                ])
            ),
            'lang' => new external_value(PARAM_LANG, 'Target language code'),
        ]);
    }

    /**
     * Registers page strings in the DB if new, then returns IDs and user vote state.
     *
     * @param array  $strings
     * @param string $lang
     * @return array
     */
    public static function execute(array $strings, string $lang): array {
        global $USER;

        $params = self::validate_parameters(self::execute_parameters(), [
            'strings' => $strings,
            'lang'    => $lang,
        ]);

        require_login();
        $context = \context_system::instance();
        self::validate_context($context);
        require_capability('local/langcrowd:vote', $context);
        access::require_can_participate($USER->id, $params['lang']);

        // Bound the number of strings registered per call to avoid unbounded writes.
        if (count($params['strings']) > self::MAX_STRINGS_PER_CALL) {
            $params['strings'] = array_slice($params['strings'], 0, self::MAX_STRINGS_PER_CALL);
        }

        // Drop any components the admin has excluded (defends the filter server-side).
        $params['strings'] = array_values(array_filter(
            $params['strings'],
            fn($s) => access::component_is_allowed($s['component'])
        ));

        $idmap          = self::ensure_strings_exist($params['strings'], $params['lang']);
        $votesbystringid = self::fetch_user_votes(array_values($idmap), $USER->id);

        return self::build_response($params['strings'], $idmap, $votesbystringid);
    }

    /**
     * Groups strings by component, fetches existing records, inserts missing ones.
     *
     * @param array  $strings
     * @param string $lang
     * @return array keyed by "component::key"
     */
    protected static function ensure_strings_exist(array $strings, string $lang): array {
        $bycomponent = self::group_by_component($strings);
        $existingmap = self::fetch_existing($bycomponent, $lang);
        return self::insert_missing($strings, $lang, $existingmap);
    }

    /**
     * Groups valid strings by their component name.
     *
     * @param array $strings
     * @return array
     */
    protected static function group_by_component(array $strings): array {
        $bycomponent = [];
        foreach ($strings as $strdata) {
            if (!preg_match('/^[a-zA-Z0-9_\-:]+$/', $strdata['key'])) {
                continue;
            }
            $bycomponent[$strdata['component']][] = $strdata;
        }
        return $bycomponent;
    }

    /**
     * Batch-fetches existing DB records grouped by component.
     *
     * @param array  $bycomponent
     * @param string $lang
     * @return array keyed by "component::key"
     */
    protected static function fetch_existing(array $bycomponent, string $lang): array {
        global $DB;

        $existingmap = [];
        foreach ($bycomponent as $comp => $compstrings) {
            $keys = array_column($compstrings, 'key');
            [$insql, $inparams] = $DB->get_in_or_equal($keys);
            $records = $DB->get_records_sql(
                "SELECT * FROM {local_langcrowd_strings}
                  WHERE component = ? AND lang = ? AND stringkey $insql",
                array_merge([$comp, $lang], $inparams)
            );
            foreach ($records as $rec) {
                $existingmap[$rec->component . '::' . $rec->stringkey] = $rec;
            }
        }
        return $existingmap;
    }

    /**
     * Inserts strings that are not yet in the DB and returns a complete id map.
     *
     * @param array  $strings
     * @param string $lang
     * @param array  $existingmap
     * @return array keyed by "component::key"
     */
    protected static function insert_missing(array $strings, string $lang, array $existingmap): array {
        global $DB;

        $now   = time();
        $idmap = [];

        foreach ($strings as $strdata) {
            $comp = $strdata['component'];
            $key  = $strdata['key'];
            if (!preg_match('/^[a-zA-Z0-9_\-:]+$/', $key)) {
                continue;
            }
            $cachekey = $comp . '::' . $key;

            if (isset($existingmap[$cachekey])) {
                $idmap[$cachekey] = $existingmap[$cachekey];
                continue;
            }

            $record               = new \stdClass();
            $record->component    = $comp;
            $record->stringkey    = $key;
            $record->lang         = $lang;
            $record->sourcevalue  = $strdata['value'];
            $record->currentvalue = $strdata['value'];
            $record->votecount    = 0;
            $record->status       = 'pending';
            $record->timecreated  = $now;
            $record->timemodified = $now;

            try {
                $record->id       = $DB->insert_record('local_langcrowd_strings', $record);
                $idmap[$cachekey] = $record;
            } catch (\dml_exception $e) {
                $rec = $DB->get_record('local_langcrowd_strings', [
                    'component' => $comp, 'stringkey' => $key, 'lang' => $lang,
                ]);
                if ($rec) {
                    $idmap[$cachekey] = $rec;
                }
            }
        }
        return $idmap;
    }

    /**
     * Batch-fetches the given user's votes for a list of string records.
     *
     * @param array $records flat list of DB records (each must have ->id)
     * @param int   $userid
     * @return array vote value keyed by integer string ID
     */
    protected static function fetch_user_votes(array $records, int $userid): array {
        global $DB;

        $allids = array_map(fn($r) => (int)$r->id, $records);
        if (empty($allids)) {
            return [];
        }

        [$insql, $inparams] = $DB->get_in_or_equal($allids);
        $votes = $DB->get_records_sql(
            "SELECT stringid, vote FROM {local_langcrowd_votes}
              WHERE userid = ? AND stringid $insql",
            array_merge([$userid], $inparams)
        );

        $map = [];
        foreach ($votes as $v) {
            $map[(int)$v->stringid] = (int)$v->vote;
        }
        return $map;
    }

    /**
     * Assembles the final response array from the resolved id map and vote map.
     *
     * @param array $strings        original input strings
     * @param array $idmap          keyed by "component::key"
     * @param array $votesbystringid vote value keyed by string ID
     * @return array
     */
    protected static function build_response(array $strings, array $idmap, array $votesbystringid): array {
        $result = [];
        foreach ($strings as $strdata) {
            $cachekey = $strdata['component'] . '::' . $strdata['key'];
            if (!isset($idmap[$cachekey])) {
                continue;
            }
            $rec   = $idmap[$cachekey];
            $sid   = (int)$rec->id;
            $voted = isset($votesbystringid[$sid]);
            $result[] = [
                'stringid'  => $sid,
                'component' => $strdata['component'],
                'key'       => $strdata['key'],
                'status'    => $rec->status,
                'votecount' => (int)$rec->votecount,
                'voted'     => $voted,
                'vote'      => $voted ? $votesbystringid[$sid] : 0,
                'source'    => (string)$rec->sourcevalue,
            ];
        }
        return $result;
    }

    /**
     * Returns the return value specification.
     *
     * @return external_multiple_structure
     */
    public static function execute_returns(): external_multiple_structure {
        return new external_multiple_structure(
            new external_single_structure([
                'stringid'  => new external_value(PARAM_INT, 'DB ID of the string record'),
                'component' => new external_value(PARAM_COMPONENT, 'Component name'),
                'key'       => new external_value(PARAM_RAW, 'String identifier'),
                'status'    => new external_value(PARAM_ALPHA, 'String status: pending or locked'),
                'votecount' => new external_value(PARAM_INT, 'Current approve vote count'),
                'voted'     => new external_value(PARAM_BOOL, 'Whether the current user has already voted'),
                'vote'      => new external_value(PARAM_INT, 'User vote: 1, -1, or 0 if not voted'),
                'source'    => new external_value(PARAM_RAW, 'The English source value for this string'),
            ])
        );
    }
}
