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

/**
 * Normalises stored component names, merging rows that were split by spelling.
 *
 * Until version 2026092600 a string was recorded under whatever component name its
 * caller passed to get_string(), so 'forum' and 'mod_forum' (or 'moodle' and 'core')
 * produced separate rows with separate votes for the same string. Each such pair is
 * merged into one row under the normalised name: the row further along the review
 * cycle (locked > pushed > pending) is kept, the other's suggestions move over, its
 * votes move over unless that user already voted on the kept row, and the kept row's
 * approve count is recomputed. The allowed_components setting is normalised too.
 */
function local_langcrowd_upgrade_normalise_components(): void {
    global $DB;

    $torename = [];
    $rs = $DB->get_recordset('local_langcrowd_strings', null, 'id', 'id, component');
    foreach ($rs as $rec) {
        $normalised = local_langcrowd_upgrade_normalise_component($rec->component);
        if ($normalised !== $rec->component) {
            $torename[$rec->id] = $normalised;
        }
    }
    $rs->close();

    foreach ($torename as $id => $normalised) {
        $row = $DB->get_record('local_langcrowd_strings', ['id' => $id]);
        if (!$row) {
            continue;
        }
        $target = $DB->get_record('local_langcrowd_strings', [
            'component' => $normalised, 'stringkey' => $row->stringkey, 'lang' => $row->lang,
        ]);
        if (!$target) {
            $DB->set_field('local_langcrowd_strings', 'component', $normalised, ['id' => $row->id]);
            continue;
        }

        local_langcrowd_upgrade_merge_split_rows($row, $target, $normalised);
    }

    $allowed = get_config('local_langcrowd', 'allowed_components');
    if (!empty($allowed)) {
        $names = array_filter(array_map('trim', explode(',', $allowed)), 'strlen');
        $names = array_unique(array_map('local_langcrowd_upgrade_normalise_component', $names));
        set_config('allowed_components', implode(',', $names), 'local_langcrowd');
    }
}

/**
 * Merges two rows recording the same string under different component spellings.
 *
 * The row further along the review cycle (locked > pushed > pending) is kept under the
 * normalised name; the other row's suggestions move over, its votes move over unless
 * that user already voted on the kept row, and the kept row's approve count is recomputed.
 *
 * @param stdClass $row    the row with the unnormalised component
 * @param stdClass $target the existing row with the normalised component
 * @param string $normalised
 */
function local_langcrowd_upgrade_merge_split_rows(stdClass $row, stdClass $target, string $normalised): void {
    global $DB;

    $rank = ['pending' => 0, 'pushed' => 1, 'locked' => 2];
    [$keeper, $loser] = ($rank[$row->status] ?? 0) > ($rank[$target->status] ?? 0) ? [$row, $target] : [$target, $row];

    $keepervoters = $DB->get_fieldset_select('local_langcrowd_votes', 'userid', 'stringid = ?', [$keeper->id]);
    foreach ($DB->get_records('local_langcrowd_votes', ['stringid' => $loser->id]) as $vote) {
        if (in_array($vote->userid, $keepervoters)) {
            $DB->delete_records('local_langcrowd_votes', ['id' => $vote->id]);
        } else {
            $DB->set_field('local_langcrowd_votes', 'stringid', $keeper->id, ['id' => $vote->id]);
        }
    }
    $DB->set_field('local_langcrowd_suggestions', 'stringid', $keeper->id, ['stringid' => $loser->id]);
    $DB->delete_records('local_langcrowd_strings', ['id' => $loser->id]);
    if ($keeper->component !== $normalised) {
        $DB->set_field('local_langcrowd_strings', 'component', $normalised, ['id' => $keeper->id]);
    }
    $DB->set_field(
        'local_langcrowd_strings',
        'votecount',
        $DB->count_records('local_langcrowd_votes', ['stringid' => $keeper->id, 'vote' => 1]),
        ['id' => $keeper->id]
    );
}

/**
 * Keeps only the newest pending suggestion per user and string.
 *
 * Until version 2026092600 every submission inserted a new pending row, and merging
 * split rows can put two pending suggestions of one user on the same string. From
 * 2026092600 a new submission replaces the user's pending one, so older duplicates
 * are removed here to make that rule hold for existing data too.
 */
function local_langcrowd_upgrade_dedupe_pending_suggestions(): void {
    global $DB;

    $rs = $DB->get_recordset(
        'local_langcrowd_suggestions',
        ['status' => 'pending'],
        'stringid, userid, timemodified DESC, id DESC',
        'id, stringid, userid'
    );
    $seen = [];
    $delete = [];
    foreach ($rs as $rec) {
        $key = $rec->stringid . ':' . $rec->userid;
        if (isset($seen[$key])) {
            $delete[] = $rec->id;
        } else {
            $seen[$key] = true;
        }
    }
    $rs->close();
    if ($delete) {
        $DB->delete_records_list('local_langcrowd_suggestions', 'id', $delete);
    }
}

/**
 * Converts straight quotes in promoted values that the stricter serving guard now refuses.
 *
 * From version 2026092600 a promoted value is served only if it cannot break out of an
 * HTML attribute or a JavaScript string (see text_safety). Curated translations that
 * merely contain straight quotes are converted to typographic quotes so they keep being
 * served; values that equal the installed lang pack are left alone (the lang pack serves
 * them anyway), and anything still unsafe after conversion stays unserved.
 */
function local_langcrowd_upgrade_normalise_promoted_values(): void {
    global $DB;

    $stringmanager = get_string_manager();
    $rs = $DB->get_recordset_select(
        'local_langcrowd_strings',
        "status IN ('locked', 'pushed')",
        null,
        'id',
        'id, component, stringkey, lang, currentvalue'
    );
    foreach ($rs as $rec) {
        $current = (string)$rec->currentvalue;
        if (local_langcrowd_upgrade_is_safe($current)) {
            continue;
        }
        if (
            $stringmanager->string_exists($rec->stringkey, $rec->component)
            && $stringmanager->get_string($rec->stringkey, $rec->component, null, $rec->lang) === $current
        ) {
            continue;
        }
        $normalised = local_langcrowd_upgrade_normalise_quotes($current);
        if ($normalised !== $current && local_langcrowd_upgrade_is_safe($normalised)) {
            $DB->set_field('local_langcrowd_strings', 'currentvalue', $normalised, ['id' => $rec->id]);
        }
    }
    $rs->close();
}

/**
 * Frozen copy of the 2026092600 component normalisation (upgrade code must not change
 * behaviour when the plugin's own classes do later).
 *
 * @param string $component
 * @return string
 */
function local_langcrowd_upgrade_normalise_component(string $component): string {
    return $component === '' ? 'core' : \core_component::normalize_componentname($component);
}

/**
 * Frozen copy of the 2026092600 text_safety::is_safe() rule.
 *
 * @param string $text
 * @return bool
 */
function local_langcrowd_upgrade_is_safe(string $text): bool {
    foreach (['<', '>', '"', "'", '\\', "\x60"] as $char) {
        if (strpos($text, $char) !== false) {
            return false;
        }
    }
    return !preg_match('/&[#a-z0-9]/i', $text);
}

/**
 * Frozen copy of the 2026092600 text_safety::normalise() quote conversion.
 *
 * @param string $text
 * @return string
 */
function local_langcrowd_upgrade_normalise_quotes(string $text): string {
    $text = str_replace("'", "\u{2019}", trim($text));
    $open = true;
    return preg_replace_callback('/"/', function () use (&$open) {
        $quote = $open ? "\u{201C}" : "\u{201D}";
        $open = !$open;
        return $quote;
    }, $text);
}
