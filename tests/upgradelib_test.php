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
 * Tests for the upgrade helper repairing client-supplied currentvalues.
 *
 * @package    local_langcrowd
 * @copyright  2026 Adam Jenkins <adam@wisecat.net>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_langcrowd;

defined('MOODLE_INTERNAL') || die();

global $CFG;
require_once($CFG->dirroot . '/local/langcrowd/db/upgradelib.php');

/**
 * Unit tests for local_langcrowd_upgrade_repair_currentvalues().
 *
 * @group local_langcrowd
 * @covers ::local_langcrowd_upgrade_repair_currentvalues
 */
final class upgradelib_test extends \advanced_testcase {
    /**
     * Inserts a string record with the given fields and returns its id.
     *
     * @param array $fields
     * @return int
     */
    private function insert_string(array $fields): int {
        global $DB;
        $record = (object)array_merge([
            'component'    => 'mod_forum',
            'stringkey'    => 'modulename',
            'lang'         => 'en',
            'sourcevalue'  => 'Forum',
            'currentvalue' => 'Forum',
            'votecount'    => 0,
            'status'       => 'pending',
            'timecreated'  => time(),
            'timemodified' => time(),
        ], $fields);
        return (int)$DB->insert_record('local_langcrowd_strings', $record);
    }

    public function test_pending_rows_recomputed_from_lang_pack(): void {
        global $DB;
        $this->resetAfterTest();

        // A pending row holding a client-injected payload instead of the rendered value.
        $id = $this->insert_string([
            'currentvalue' => '<img src=x onerror=alert(document.cookie)>',
            'status'       => 'pending',
        ]);

        local_langcrowd_upgrade_repair_currentvalues();

        $this->assertSame('Forum', $DB->get_field('local_langcrowd_strings', 'currentvalue', ['id' => $id]));
    }

    public function test_locked_row_with_markup_recomputed(): void {
        global $DB;
        $this->resetAfterTest();

        $id = $this->insert_string([
            'currentvalue' => '<script>steal()</script>',
            'status'       => 'locked',
        ]);

        local_langcrowd_upgrade_repair_currentvalues();

        $this->assertSame('Forum', $DB->get_field('local_langcrowd_strings', 'currentvalue', ['id' => $id]));
    }

    public function test_clean_locked_and_pushed_rows_kept(): void {
        global $DB;
        $this->resetAfterTest();

        // A community-approved (locked) and an admin-pushed value must survive:
        // both are plain text and are the plugin's curated output.
        $locked = $this->insert_string([
            'stringkey'    => 'modulename',
            'lang'         => 'ja',
            'currentvalue' => 'フォーラム',
            'status'       => 'locked',
        ]);
        $pushed = $this->insert_string([
            'stringkey'    => 'replies',
            'lang'         => 'ja',
            'sourcevalue'  => 'Replies',
            'currentvalue' => '返信',
            'status'       => 'pushed',
        ]);

        local_langcrowd_upgrade_repair_currentvalues();

        $this->assertSame(
            'フォーラム',
            $DB->get_field('local_langcrowd_strings', 'currentvalue', ['id' => $locked])
        );
        $this->assertSame(
            '返信',
            $DB->get_field('local_langcrowd_strings', 'currentvalue', ['id' => $pushed])
        );
    }

    public function test_unknown_key_row_with_markup_deleted(): void {
        global $DB, $USER;
        $this->resetAfterTest();
        $this->setAdminUser();

        // Pre-0.3.1 rows could reference keys unknown to the en pack; if one holds
        // markup nothing can be recomputed, so the row (and its votes/suggestions)
        // must go.
        $id = $this->insert_string([
            'stringkey'    => 'nosuchstringkey',
            'currentvalue' => '<svg onload=alert(1)>',
            'status'       => 'locked',
        ]);
        $DB->insert_record('local_langcrowd_votes', (object)[
            'stringid' => $id, 'userid' => $USER->id, 'vote' => 1, 'timecreated' => time(),
        ]);
        $DB->insert_record('local_langcrowd_suggestions', (object)[
            'stringid' => $id, 'userid' => $USER->id, 'suggestion' => 'x',
            'status' => 'pending', 'timecreated' => time(), 'timemodified' => time(),
        ]);

        local_langcrowd_upgrade_repair_currentvalues();

        $this->assertFalse($DB->record_exists('local_langcrowd_strings', ['id' => $id]));
        $this->assertFalse($DB->record_exists('local_langcrowd_votes', ['stringid' => $id]));
        $this->assertFalse($DB->record_exists('local_langcrowd_suggestions', ['stringid' => $id]));
    }

    public function test_unknown_key_row_without_markup_kept(): void {
        global $DB;
        $this->resetAfterTest();

        $id = $this->insert_string([
            'stringkey'    => 'nosuchstringkey',
            'sourcevalue'  => 'Old value',
            'currentvalue' => 'Old value translated',
            'status'       => 'locked',
        ]);

        local_langcrowd_upgrade_repair_currentvalues();

        $this->assertSame(
            'Old value translated',
            $DB->get_field('local_langcrowd_strings', 'currentvalue', ['id' => $id])
        );
    }

    public function test_repair_sourcevalues_recomputes_from_english_pack(): void {
        global $DB;
        $this->resetAfterTest();

        // A row whose sourcevalue is not actually English (legacy 0.3.0 data).
        $id = $this->insert_string([
            'stringkey'   => 'modulename',
            'lang'        => 'ja',
            'sourcevalue' => 'フォーラム',
        ]);
        // A row whose key no longer exists — left untouched.
        $orphan = $this->insert_string([
            'stringkey'   => 'nosuchstringkey',
            'sourcevalue' => 'Whatever',
        ]);

        local_langcrowd_upgrade_repair_sourcevalues();

        $this->assertSame('Forum',
            $DB->get_field('local_langcrowd_strings', 'sourcevalue', ['id' => $id]));
        $this->assertSame('Whatever',
            $DB->get_field('local_langcrowd_strings', 'sourcevalue', ['id' => $orphan]));
    }
}
