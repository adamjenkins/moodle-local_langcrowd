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
 * Tests for the language pack exporter.
 *
 * @package    local_langcrowd
 * @copyright  2026 Adam Jenkins <adam@wisecat.net>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_langcrowd\local;

/**
 * Unit tests for the language pack exporter.
 *
 * @group local_langcrowd
 * @covers \local_langcrowd\local\exporter
 */
final class exporter_test extends \advanced_testcase {
    /**
     * Inserts a string row directly.
     *
     * @param array $overrides
     * @return int
     */
    private function make_string(array $overrides): int {
        global $DB;
        $now = time();
        $record = (object)array_merge([
            'component'    => 'mod_forum',
            'stringkey'    => 'modulename',
            'lang'         => 'en',
            'sourcevalue'  => 'Forum',
            'currentvalue' => 'Forum',
            'votecount'    => 0,
            'status'       => 'locked',
            'timecreated'  => $now,
            'timemodified' => $now,
        ], $overrides);
        return $DB->insert_record('local_langcrowd_strings', $record);
    }

    /**
     * Extracts a single file from a zip binary produced by the exporter.
     *
     * @param string $binary
     * @param string $path
     * @return string
     */
    private function read_zip_entry(string $binary, string $path): string {
        $tmp = tempnam(sys_get_temp_dir(), 'lctest_');
        file_put_contents($tmp, $binary);
        $zip = new \ZipArchive();
        $this->assertTrue($zip->open($tmp) === true);
        $content = $zip->getFromName($path);
        $zip->close();
        unlink($tmp);
        $this->assertNotFalse($content, "Zip entry $path not found");
        return $content;
    }

    public function test_export_produces_loadable_php_for_hostile_values(): void {
        $this->resetAfterTest();

        // Values engineered to break naive quote-only escaping: trailing backslash,
        // embedded quotes, and a code-injection attempt.
        $hostile = "\\'; system('id'); \$x='";
        $this->make_string([
            'component' => 'mod_forum', 'stringkey' => 'evil', 'currentvalue' => $hostile,
        ]);
        $this->make_string([
            'component' => 'mod_forum', 'stringkey' => 'trailingslash', 'currentvalue' => 'ends with backslash\\',
        ]);
        $this->make_string([
            'component' => 'mod_forum', 'stringkey' => 'quote', 'currentvalue' => "O'Brien said \"hi\"",
        ]);

        $binary = exporter::export('en', [], 'all');
        $this->assertNotSame('', $binary);

        $content = $this->read_zip_entry($binary, 'en/mod_forum.php');

        // The generated file must be valid, safe-to-load PHP that reproduces the values.
        $tmp = tempnam(sys_get_temp_dir(), 'lclang_');
        file_put_contents($tmp, $content);
        $string = [];
        include($tmp);
        unlink($tmp);

        $this->assertSame($hostile, $string['evil']);
        $this->assertSame('ends with backslash\\', $string['trailingslash']);
        $this->assertSame("O'Brien said \"hi\"", $string['quote']);
    }

    public function test_scope_locked_excludes_pending_and_pushed(): void {
        $this->resetAfterTest();
        $this->make_string(['stringkey' => 'a', 'status' => 'locked', 'currentvalue' => 'Locked']);
        $this->make_string(['stringkey' => 'b', 'status' => 'pending', 'currentvalue' => 'Pending']);
        $this->make_string(['stringkey' => 'c', 'status' => 'pushed', 'currentvalue' => 'Pushed']);

        $content = $this->read_zip_entry(exporter::export('en', [], 'locked'), 'en/mod_forum.php');
        $string = [];
        $tmp = tempnam(sys_get_temp_dir(), 'lclang_');
        file_put_contents($tmp, $content);
        include($tmp);
        unlink($tmp);

        $this->assertArrayHasKey('a', $string);
        $this->assertArrayNotHasKey('b', $string);
        $this->assertArrayNotHasKey('c', $string);
    }

    public function test_scope_all_includes_translated_non_locked(): void {
        $this->resetAfterTest();
        $this->make_string(['stringkey' => 'a', 'status' => 'locked', 'currentvalue' => 'Locked']);
        $this->make_string(['stringkey' => 'b', 'status' => 'pushed', 'currentvalue' => 'Pushed']);

        $content = $this->read_zip_entry(exporter::export('en', [], 'all'), 'en/mod_forum.php');
        $string = [];
        $tmp = tempnam(sys_get_temp_dir(), 'lclang_');
        file_put_contents($tmp, $content);
        include($tmp);
        unlink($tmp);

        $this->assertArrayHasKey('a', $string);
        $this->assertArrayHasKey('b', $string);
    }

    public function test_component_filter(): void {
        $this->resetAfterTest();
        $this->make_string(['component' => 'mod_forum', 'stringkey' => 'a', 'currentvalue' => 'A']);
        $this->make_string(['component' => 'mod_quiz', 'stringkey' => 'b', 'currentvalue' => 'B']);

        $binary = exporter::export('en', ['mod_forum'], 'all');
        $tmp = tempnam(sys_get_temp_dir(), 'lczip_');
        file_put_contents($tmp, $binary);
        $zip = new \ZipArchive();
        $zip->open($tmp);
        $this->assertNotFalse($zip->getFromName('en/mod_forum.php'));
        $this->assertFalse($zip->getFromName('en/mod_quiz.php'));
        $zip->close();
        unlink($tmp);
    }

    public function test_export_returns_empty_when_no_data(): void {
        $this->resetAfterTest();
        $this->assertSame('', exporter::export('fr', [], 'all'));
    }

    public function test_get_languages_and_components(): void {
        $this->resetAfterTest();
        $this->make_string(['component' => 'mod_forum', 'lang' => 'en', 'stringkey' => 'a']);
        $this->make_string(['component' => 'mod_quiz', 'lang' => 'th', 'stringkey' => 'b']);

        $this->assertEqualsCanonicalizing(['en', 'th'], exporter::get_languages());
        $this->assertSame(['mod_forum'], array_values(exporter::get_components('en')));
    }
}
