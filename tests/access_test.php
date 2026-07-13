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
 * Tests for the participation gate.
 *
 * @package    local_langcrowd
 * @copyright  2026 Adam Jenkins <adam@wisecat.net>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_langcrowd;

/**
 * Unit tests for the participation gate.
 *
 * @group local_langcrowd
 * @covers \local_langcrowd\access
 */
final class access_test extends \advanced_testcase {
    public function test_is_enabled_reflects_config(): void {
        $this->resetAfterTest();
        $this->assertFalse(access::is_enabled());
        set_config('enabled', 1, 'local_langcrowd');
        $this->assertTrue(access::is_enabled());
    }

    public function test_no_role_restriction_allows_everyone(): void {
        $this->resetAfterTest();
        $user = self::getDataGenerator()->create_user();
        set_config('allowed_roles', '', 'local_langcrowd');
        $this->assertTrue(access::user_has_allowed_role($user->id));
    }

    public function test_role_restriction_blocks_users_without_the_role(): void {
        global $DB;
        $this->resetAfterTest();

        $user = self::getDataGenerator()->create_user();
        $course = self::getDataGenerator()->create_course();
        $teacherrole = $DB->get_record('role', ['shortname' => 'editingteacher'], '*', MUST_EXIST);
        $studentrole = $DB->get_record('role', ['shortname' => 'student'], '*', MUST_EXIST);

        // Only the teacher role is allowed.
        set_config('allowed_roles', (string)$teacherrole->id, 'local_langcrowd');

        // User has the student role in a course: not allowed.
        self::getDataGenerator()->enrol_user($user->id, $course->id, $studentrole->id);
        $this->assertFalse(access::user_has_allowed_role($user->id));

        // Grant the teacher role somewhere: now allowed.
        self::getDataGenerator()->enrol_user($user->id, $course->id, $teacherrole->id);
        $this->assertTrue(access::user_has_allowed_role($user->id));
    }

    public function test_site_admin_always_allowed(): void {
        global $USER;
        $this->resetAfterTest();
        $this->setAdminUser();
        set_config('allowed_roles', '999', 'local_langcrowd');
        $this->assertTrue(access::user_has_allowed_role($USER->id));
    }

    public function test_lang_restriction(): void {
        $this->resetAfterTest();
        $this->assertTrue(access::lang_is_allowed('en'));
        set_config('allowed_langs', 'th,fr', 'local_langcrowd');
        $this->assertFalse(access::lang_is_allowed('en'));
        $this->assertTrue(access::lang_is_allowed('th'));
    }

    public function test_require_can_participate_throws_when_disabled(): void {
        $this->resetAfterTest();
        $user = self::getDataGenerator()->create_user();
        // Not enabled by default.
        $this->expectException(\moodle_exception::class);
        access::require_can_participate($user->id, 'en');
    }
}
