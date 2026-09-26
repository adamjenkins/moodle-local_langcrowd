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
 * Tests for the submit_suggestion external function.
 *
 * @package    local_langcrowd
 * @copyright  2026 Adam Jenkins <adam@wisecat.net>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_langcrowd\external;

/**
 * Unit tests for the submit_suggestion external function.
 */
#[\PHPUnit\Framework\Attributes\Group('local_langcrowd')]
#[\PHPUnit\Framework\Attributes\CoversClass(\local_langcrowd\external\submit_suggestion::class)]
final class submit_suggestion_test extends \advanced_testcase {
    /**
     * Inserts a pending string row and returns its id.
     *
     * @param array $overrides
     * @return int
     */
    private function make_string(array $overrides = []): int {
        global $DB;
        $now = time();
        return $DB->insert_record('local_langcrowd_strings', (object)array_merge([
            'component'    => 'mod_forum',
            'stringkey'    => 'modulename',
            'lang'         => 'en',
            'sourcevalue'  => 'Forum',
            'currentvalue' => 'Forum',
            'votecount'    => 0,
            'status'       => 'pending',
            'timecreated'  => $now,
            'timemodified' => $now,
        ], $overrides));
    }

    /**
     * Executes submit_suggestion and returns the cleaned result.
     *
     * @param int    $stringid
     * @param string $suggestion
     * @return array
     */
    private function call(int $stringid, string $suggestion): array {
        $result = submit_suggestion::execute($stringid, $suggestion);
        return (array)\core_external\external_api::clean_returnvalue(submit_suggestion::execute_returns(), $result);
    }

    /**
     * Enables the plugin with a vote threshold of 3.
     */
    protected function enable(): void {
        set_config('enabled', 1, 'local_langcrowd');
        set_config('threshold', 3, 'local_langcrowd');
    }

    public function test_suggestion_recorded_with_reject_vote(): void {
        global $DB;
        $this->resetAfterTest();
        $this->enable();
        $user = self::getDataGenerator()->create_user();
        $this->setUser($user);
        $sid = $this->make_string();

        $result = $this->call($sid, '  Better translation  ');

        $this->assertTrue($result['success']);
        $sug = $DB->get_record('local_langcrowd_suggestions', ['stringid' => $sid, 'userid' => $user->id], '*', MUST_EXIST);
        $this->assertSame('Better translation', $sug->suggestion);
        $this->assertSame('pending', $sug->status);
        // A reject vote is recorded alongside.
        $vote = $DB->get_record('local_langcrowd_votes', ['stringid' => $sid, 'userid' => $user->id], '*', MUST_EXIST);
        $this->assertSame(-1, (int)$vote->vote);
    }

    public function test_empty_suggestion_rejected(): void {
        $this->resetAfterTest();
        $this->enable();
        $this->setUser(self::getDataGenerator()->create_user());
        $sid = $this->make_string();

        $this->expectException(\invalid_parameter_exception::class);
        $this->call($sid, '   ');
    }

    public function test_suggestion_on_locked_string_is_refused(): void {
        global $DB;
        $this->resetAfterTest();
        $this->enable();
        $this->setUser(self::getDataGenerator()->create_user());
        $sid = $this->make_string(['status' => 'locked']);

        $result = $this->call($sid, 'nope');

        $this->assertFalse($result['success']);
        $this->assertSame(0, $DB->count_records('local_langcrowd_suggestions', ['stringid' => $sid]));
    }

    public function test_does_not_add_second_reject_vote(): void {
        global $DB;
        $this->resetAfterTest();
        $this->enable();
        $user = self::getDataGenerator()->create_user();
        $this->setUser($user);
        $sid = $this->make_string();

        // Pre-existing approve vote should not be duplicated by a suggestion.
        $DB->insert_record('local_langcrowd_votes', (object)[
            'stringid' => $sid, 'userid' => $user->id, 'vote' => 1, 'timecreated' => time(),
        ]);

        $this->call($sid, 'alt');

        $this->assertSame(1, $DB->count_records('local_langcrowd_votes', ['stringid' => $sid, 'userid' => $user->id]));
    }

    public function test_straight_quotes_become_typographic(): void {
        global $DB;
        $this->resetAfterTest();
        $this->enable();
        $user = self::getDataGenerator()->create_user();
        $this->setUser($user);
        $sid = $this->make_string();

        $this->call($sid, 'Don\'t say "forum"');

        $this->assertSame(
            "Don\u{2019}t say \u{201C}forum\u{201D}",
            $DB->get_field('local_langcrowd_suggestions', 'suggestion', ['stringid' => $sid, 'userid' => $user->id])
        );
    }

    /**
     * Characters that could break out of an HTML attribute or a JavaScript string.
     *
     * @return array
     */
    public static function unsafe_suggestion_provider(): array {
        return [
            'angle bracket'       => ['a < b'],
            'backslash'           => ['path\\'],
            'backtick'            => ["\x60x\x60"],
            'named reference'     => ['Log in&quot; onfocus=&quot;x'],
            'numeric reference'   => ['Log in&#34; autofocus'],
        ];
    }

    /**
     * Unsafe text is refused before anything is stored.
     *
     * @param string $suggestion
     */
    #[\PHPUnit\Framework\Attributes\DataProvider('unsafe_suggestion_provider')]
    public function test_unsafe_suggestion_refused(string $suggestion): void {
        global $DB;
        $this->resetAfterTest();
        $this->enable();
        $this->setUser(self::getDataGenerator()->create_user());
        $sid = $this->make_string();

        try {
            $this->call($sid, $suggestion);
            $this->fail('An unsafe suggestion must be refused');
        } catch (\moodle_exception $e) {
            $this->assertSame('suggestion_unsafe', $e->errorcode);
        }
        $this->assertSame(0, $DB->count_records('local_langcrowd_suggestions'));
    }

    public function test_overlong_suggestion_refused_server_side(): void {
        global $DB;
        $this->resetAfterTest();
        $this->enable();
        $this->setUser(self::getDataGenerator()->create_user());
        $sid = $this->make_string();

        // Exactly at the limit is fine (multibyte: the limit counts characters, not bytes).
        $this->call($sid, str_repeat('あ', \local_langcrowd\local\text_safety::MAX_SUGGESTION_LENGTH));

        try {
            $this->call($sid, str_repeat('あ', \local_langcrowd\local\text_safety::MAX_SUGGESTION_LENGTH + 1));
            $this->fail('An over-long suggestion must be refused');
        } catch (\moodle_exception $e) {
            $this->assertSame('suggestion_toolong', $e->errorcode);
        }
        // Only the at-limit suggestion was stored.
        $stored = $DB->get_field('local_langcrowd_suggestions', 'suggestion', ['stringid' => $sid]);
        $this->assertSame(\local_langcrowd\local\text_safety::MAX_SUGGESTION_LENGTH, \core_text::strlen($stored));
    }

    public function test_repeat_suggestion_replaces_pending_one(): void {
        global $DB;
        $this->resetAfterTest();
        $this->enable();
        $user = self::getDataGenerator()->create_user();
        $this->setUser($user);
        $sid = $this->make_string();

        $this->call($sid, 'first');
        $this->call($sid, 'second');
        $this->call($sid, 'third');

        $rows = $DB->get_records('local_langcrowd_suggestions', ['stringid' => $sid, 'userid' => $user->id]);
        $this->assertCount(1, $rows);
        $this->assertSame('third', reset($rows)->suggestion);
    }

    public function test_capability_required(): void {
        global $CFG;
        $this->resetAfterTest();
        $this->enable();
        $this->setUser(self::getDataGenerator()->create_user());
        $sid = $this->make_string();
        assign_capability('local/langcrowd:suggest', CAP_PROHIBIT, $CFG->defaultuserroleid, \context_system::instance(), true);
        accesslib_clear_all_caches_for_unit_testing();

        $this->expectException(\required_capability_exception::class);
        $this->call($sid, 'alt');
    }

    public function test_role_restriction_blocks_direct_call(): void {
        global $DB;
        $this->resetAfterTest();
        $this->enable();
        set_config('allowed_roles', '999', 'local_langcrowd');
        $this->setUser(self::getDataGenerator()->create_user());
        $sid = $this->make_string();

        try {
            $this->call($sid, 'alt');
            $this->fail('A user without an allowed role must be refused');
        } catch (\moodle_exception $e) {
            $this->assertSame('nopermissions', $e->errorcode);
        }
        $this->assertSame(0, $DB->count_records('local_langcrowd_suggestions'));
    }

    public function test_excluded_component_refused(): void {
        global $DB;
        $this->resetAfterTest();
        $this->enable();
        set_config('allowed_components', 'mod_quiz', 'local_langcrowd');
        $this->setUser(self::getDataGenerator()->create_user());
        $sid = $this->make_string(['component' => 'mod_forum']);

        try {
            $this->call($sid, 'alt');
            $this->fail('A string of an excluded component must be refused');
        } catch (\moodle_exception $e) {
            $this->assertSame('nopermissions', $e->errorcode);
        }
        $this->assertSame(0, $DB->count_records('local_langcrowd_suggestions'));
    }

    public function test_placeholders_must_be_kept(): void {
        global $DB;
        $this->resetAfterTest();
        $this->enable();
        $this->setUser(self::getDataGenerator()->create_user());
        $sid = $this->make_string([
            'component' => 'core', 'stringkey' => 'numdays', 'sourcevalue' => '{$a} days', 'currentvalue' => '{$a} days',
        ]);

        try {
            $this->call($sid, '3 jours');
            $this->fail('A suggestion that drops a placeholder must be refused');
        } catch (\moodle_exception $e) {
            $this->assertSame('suggestion_placeholders', $e->errorcode);
        }
        $this->assertSame(0, $DB->count_records('local_langcrowd_suggestions'));

        $this->assertTrue($this->call($sid, '{$a} jours')['success']);
        $this->assertSame('{$a} jours', $DB->get_field('local_langcrowd_suggestions', 'suggestion', ['stringid' => $sid]));
    }
}
