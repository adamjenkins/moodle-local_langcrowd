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
 * Tests for the component-name helpers.
 *
 * @package    local_langcrowd
 * @copyright  2026 Adam Jenkins <adam@wisecat.net>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_langcrowd\local;

/**
 * Unit tests for components.
 */
#[\PHPUnit\Framework\Attributes\Group('local_langcrowd')]
#[\PHPUnit\Framework\Attributes\CoversClass(\local_langcrowd\local\components::class)]
final class components_test extends \advanced_testcase {
    public function test_normalise(): void {
        $this->assertSame('core', components::normalise(''));
        $this->assertSame('core', components::normalise('moodle'));
        $this->assertSame('core', components::normalise('core'));
        $this->assertSame('core_admin', components::normalise('admin'));
        $this->assertSame('mod_forum', components::normalise('forum'));
        $this->assertSame('mod_forum', components::normalise('mod_forum'));
        $this->assertSame('block_html', components::normalise('block_html'));
    }

    public function test_normalise_list(): void {
        $this->assertSame(['mod_forum', 'core'], components::normalise_list('forum, mod_forum,,moodle'));
    }

    public function test_installed_lists_components_never_seen(): void {
        $installed = components::installed();
        // The component filter must offer components before any of their strings is recorded.
        $this->assertContains('core', $installed);
        $this->assertContains('core_admin', $installed);
        $this->assertContains('mod_forum', $installed);
        $this->assertContains('local_langcrowd', $installed);
        $sorted = $installed;
        sort($sorted);
        $this->assertSame($sorted, $installed);
        $this->assertSame(array_values(array_unique($installed)), $installed);
    }

    public function test_lang_file_name(): void {
        $this->assertSame('moodle', components::lang_file_name('core'));
        $this->assertSame('moodle', components::lang_file_name('moodle'));
        $this->assertSame('admin', components::lang_file_name('core_admin'));
        $this->assertSame('forum', components::lang_file_name('mod_forum'));
        $this->assertSame('forum', components::lang_file_name('forum'));
        $this->assertSame('block_html', components::lang_file_name('block_html'));
        $this->assertSame('local_langcrowd', components::lang_file_name('local_langcrowd'));
    }
}
