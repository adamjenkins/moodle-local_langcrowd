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
 * Tests for the custom string manager's promoted-string guard.
 *
 * @package    local_langcrowd
 * @copyright  2026 Adam Jenkins <adam@wisecat.net>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_langcrowd;

/**
 * Unit tests for string_manager::should_promote().
 *
 * @group local_langcrowd
 * @covers \local_langcrowd\string_manager
 */
final class string_manager_test extends \advanced_testcase {
    /**
     * Invokes the protected should_promote() guard.
     *
     * @param string $currentvalue
     * @param string $sourcevalue
     * @return bool
     */
    private function should_promote(string $currentvalue, string $sourcevalue): bool {
        $method = new \ReflectionMethod(string_manager::class, 'should_promote');
        return $method->invoke(null, (object)[
            'currentvalue' => $currentvalue,
            'sourcevalue'  => $sourcevalue,
        ]);
    }

    public function test_promotes_plain_text_translation(): void {
        $this->assertTrue($this->should_promote('フォーラム', 'Forum'));
    }

    public function test_promotes_short_cjk_translation(): void {
        // Legitimate CJK translations can be only one or two characters long.
        $this->assertTrue($this->should_promote('はい', 'Yes'));
    }

    public function test_skips_value_identical_to_source(): void {
        $this->assertFalse($this->should_promote('Forum', 'Forum'));
    }

    public function test_never_serves_markup(): void {
        // Defence in depth: currentvalue is server-derived plain text, so a row
        // containing markup can only be tampered/legacy data — never serve it.
        $this->assertFalse($this->should_promote('<img src=x onerror=alert(1)>', 'OK'));
        $this->assertFalse($this->should_promote('<script>steal()</script>', 'OK'));
        $this->assertFalse($this->should_promote('Save<b>!</b>', 'Save'));
    }
}
