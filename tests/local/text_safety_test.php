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
 * Tests for the translation text-safety rules.
 *
 * @package    local_langcrowd
 * @copyright  2026 Adam Jenkins <adam@wisecat.net>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_langcrowd\local;

/**
 * Unit tests for text_safety.
 */
#[\PHPUnit\Framework\Attributes\Group('local_langcrowd')]
#[\PHPUnit\Framework\Attributes\CoversClass(\local_langcrowd\local\text_safety::class)]
final class text_safety_test extends \advanced_testcase {
    public function test_normalise_converts_straight_quotes(): void {
        $this->assertSame("Don\u{2019}t", text_safety::normalise("  Don't "));
        $this->assertSame("\u{201C}a\u{201D} and \u{201C}b\u{201D}", text_safety::normalise('"a" and "b"'));
        $this->assertSame('フォーラム', text_safety::normalise('フォーラム'));
    }

    public function test_safe_text(): void {
        $this->assertTrue(text_safety::is_safe('Forum'));
        $this->assertTrue(text_safety::is_safe('フォーラム'));
        $this->assertTrue(text_safety::is_safe('Terms & conditions'));
        $this->assertTrue(text_safety::is_safe('Save & exit; now'));
        $this->assertTrue(text_safety::is_safe("Don\u{2019}t"));
        $this->assertTrue(text_safety::is_safe('100% {$a} done'));
    }

    public function test_unsafe_text(): void {
        $unsafe = [
            'a<b', 'a>b', 'say "hi"', "don't", 'back\\slash', "tick\x60",
            '&quot;', '&#34;', '&#x22;', '&amp;', 'x&quot);alert(1)//', '&amp', '&#34',
        ];
        foreach ($unsafe as $text) {
            $this->assertFalse(text_safety::is_safe($text), $text);
        }
    }

    public function test_normalised_quotes_are_safe(): void {
        $this->assertTrue(text_safety::is_safe(text_safety::normalise('Say "hi", don\'t')));
    }
}
