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
 * Unit tests for the upgrade helpers in db/upgradelib.php.
 */
#[\PHPUnit\Framework\Attributes\Group('local_langcrowd')]
#[\PHPUnit\Framework\Attributes\CoversFunction('local_langcrowd_upgrade_repair_sourcevalues')]
#[\PHPUnit\Framework\Attributes\CoversFunction('local_langcrowd_upgrade_repair_currentvalues')]
#[\PHPUnit\Framework\Attributes\CoversFunction('local_langcrowd_upgrade_normalise_components')]
#[\PHPUnit\Framework\Attributes\CoversFunction('local_langcrowd_upgrade_normalise_promoted_values')]
#[\PHPUnit\Framework\Attributes\CoversFunction('local_langcrowd_upgrade_dedupe_pending_suggestions')]
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

        $this->assertSame('Forum', $DB->get_field('local_langcrowd_strings', 'sourcevalue', ['id' => $id]));
        $this->assertSame('Whatever', $DB->get_field('local_langcrowd_strings', 'sourcevalue', ['id' => $orphan]));
    }

    /**
     * Adds a vote row.
     *
     * @param int $stringid
     * @param int $userid
     * @param int $vote
     */
    private function vote(int $stringid, int $userid, int $vote = 1): void {
        global $DB;
        $DB->insert_record('local_langcrowd_votes', (object)[
            'stringid' => $stringid, 'userid' => $userid, 'vote' => $vote, 'timecreated' => time(),
        ]);
    }

    public function test_normalise_components_renames_unsplit_rows(): void {
        global $DB;
        $this->resetAfterTest();
        $id = $this->insert_string(['component' => 'forum']);
        $coreid = $this->insert_string(['component' => 'moodle', 'stringkey' => 'login', 'sourcevalue' => 'Log in']);

        local_langcrowd_upgrade_normalise_components();

        $this->assertSame('mod_forum', $DB->get_field('local_langcrowd_strings', 'component', ['id' => $id]));
        $this->assertSame('core', $DB->get_field('local_langcrowd_strings', 'component', ['id' => $coreid]));
    }

    public function test_normalise_components_merges_split_rows(): void {
        global $DB;
        $this->resetAfterTest();
        $u1 = self::getDataGenerator()->create_user()->id;
        $u2 = self::getDataGenerator()->create_user()->id;
        $u3 = self::getDataGenerator()->create_user()->id;

        // The same string recorded twice: the short spelling got further (pushed).
        $normal = $this->insert_string(['component' => 'mod_forum', 'status' => 'pending']);
        $short = $this->insert_string(['component' => 'forum', 'status' => 'pushed', 'currentvalue' => 'Board']);
        $this->vote($normal, $u1);
        $this->vote($normal, $u2, -1);
        $this->vote($short, $u2);
        $this->vote($short, $u3);
        $DB->insert_record('local_langcrowd_suggestions', (object)[
            'stringid' => $normal, 'userid' => $u1, 'suggestion' => 'Hall', 'status' => 'pending',
            'timecreated' => time(), 'timemodified' => time(),
        ]);

        local_langcrowd_upgrade_normalise_components();

        $rows = $DB->get_records('local_langcrowd_strings');
        $this->assertCount(1, $rows);
        $kept = reset($rows);
        $this->assertSame((int)$short, (int)$kept->id, 'the row further along the review cycle is kept');
        $this->assertSame('mod_forum', $kept->component);
        $this->assertSame('pushed', $kept->status);
        $this->assertSame('Board', $kept->currentvalue);
        // The first user's vote moved over; the second already voted on the kept row, so their other vote is dropped.
        $this->assertEqualsCanonicalizing(
            [$u1, $u2, $u3],
            $DB->get_fieldset_select('local_langcrowd_votes', 'userid', 'stringid = ?', [$kept->id])
        );
        $this->assertSame(1, (int)$DB->get_field('local_langcrowd_votes', 'vote', ['stringid' => $kept->id, 'userid' => $u2]));
        $this->assertSame(3, (int)$kept->votecount);
        $this->assertSame(3, $DB->count_records('local_langcrowd_votes'));
        $this->assertSame((int)$kept->id, (int)$DB->get_field('local_langcrowd_suggestions', 'stringid', ['userid' => $u1]));
    }

    public function test_normalise_components_normalises_the_setting(): void {
        $this->resetAfterTest();
        set_config('allowed_components', 'forum,mod_forum,moodle,block_html', 'local_langcrowd');

        local_langcrowd_upgrade_normalise_components();

        $this->assertSame('mod_forum,core,block_html', get_config('local_langcrowd', 'allowed_components'));
    }

    public function test_normalise_promoted_values(): void {
        global $DB;
        $this->resetAfterTest();
        $quoted = $this->insert_string(['status' => 'locked', 'currentvalue' => 'Don\'t "post"']);
        $pending = $this->insert_string(['stringkey' => 'forum', 'status' => 'pending', 'currentvalue' => 'It\'s']);
        $hopeless = $this->insert_string(['stringkey' => 'forumname', 'status' => 'pushed', 'currentvalue' => 'a\\b']);

        local_langcrowd_upgrade_normalise_promoted_values();

        $this->assertSame(
            "Don\u{2019}t \u{201C}post\u{201D}",
            $DB->get_field('local_langcrowd_strings', 'currentvalue', ['id' => $quoted])
        );
        $this->assertSame(
            "It's",
            $DB->get_field('local_langcrowd_strings', 'currentvalue', ['id' => $pending]),
            'pending rows untouched'
        );
        $this->assertSame('a\\b', $DB->get_field('local_langcrowd_strings', 'currentvalue', ['id' => $hopeless]));
    }

    public function test_dedupe_pending_suggestions_keeps_newest_per_user_and_string(): void {
        global $DB;
        $this->resetAfterTest();
        $sid = $this->insert_string([]);
        $other = $this->insert_string(['stringkey' => 'forum']);
        $u1 = self::getDataGenerator()->create_user()->id;
        $u2 = self::getDataGenerator()->create_user()->id;
        $add = function (int $stringid, int $userid, string $text, int $time, string $status = 'pending') use ($DB): int {
            return $DB->insert_record('local_langcrowd_suggestions', (object)[
                'stringid' => $stringid, 'userid' => $userid, 'suggestion' => $text, 'status' => $status,
                'timecreated' => $time, 'timemodified' => $time,
            ]);
        };
        $add($sid, $u1, 'old', 100);
        $newest = $add($sid, $u1, 'new', 200);
        $rejected = $add($sid, $u1, 'rejected', 50, 'rejected');
        $u2row = $add($sid, $u2, 'u2', 100);
        $otherrow = $add($other, $u1, 'other string', 100);

        local_langcrowd_upgrade_dedupe_pending_suggestions();

        $this->assertEqualsCanonicalizing(
            [$newest, $rejected, $u2row, $otherrow],
            array_map('intval', array_keys($DB->get_records('local_langcrowd_suggestions')))
        );
    }
}
