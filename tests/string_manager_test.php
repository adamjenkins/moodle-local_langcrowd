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
 * Unit tests for the custom string manager's serving guard and component handling.
 */
#[\PHPUnit\Framework\Attributes\Group('local_langcrowd')]
#[\PHPUnit\Framework\Attributes\CoversClass(\local_langcrowd\string_manager::class)]
final class string_manager_test extends \advanced_testcase {
    /**
     * Resets the manager's per-request static state so tests cannot leak into each other.
     */
    protected function tearDown(): void {
        $defaults = ['servecontext' => null, 'collectcontext' => null, 'promotedstrings' => null, 'pagestrings' => []];
        foreach ($defaults as $name => $value) {
            (new \ReflectionProperty(string_manager::class, $name))->setValue(null, $value);
        }
        parent::tearDown();
    }

    /**
     * Returns a fresh langcrowd string manager that serves promoted rows as on a web request.
     *
     * CLI_SCRIPT is true under PHPUnit, which would disable serving entirely and make
     * every serving assertion vacuous, so the serving context is forced on.
     *
     * @return string_manager
     */
    private function serving_manager(): string_manager {
        global $CFG;
        (new \ReflectionProperty(string_manager::class, 'servecontext'))->setValue(null, true);
        (new \ReflectionProperty(string_manager::class, 'collectcontext'))->setValue(null, false);
        (new \ReflectionProperty(string_manager::class, 'promotedstrings'))->setValue(null, null);
        return new string_manager($CFG->langotherroot, $CFG->langlocalroot, []);
    }

    /**
     * Inserts a promoted row for the given string in the current language (en under PHPUnit).
     *
     * @param string $component
     * @param string $key
     * @param string $value
     * @param string $status
     */
    private function promote(string $component, string $key, string $value, string $status = 'locked'): void {
        global $DB;
        $DB->insert_record('local_langcrowd_strings', (object)[
            'component'    => $component,
            'stringkey'    => $key,
            'lang'         => current_language(),
            'sourcevalue'  => get_string($key, $component),
            'currentvalue' => $value,
            'votecount'    => 0,
            'status'       => $status,
            'timecreated'  => time(),
            'timemodified' => time(),
        ]);
    }
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

    public function test_never_serves_attribute_or_script_breakout(): void {
        // Core concatenates get_string() output into quoted HTML attributes and inline
        // JavaScript, so anything that can close a quote or a JS string is refused too.
        $this->assertFalse($this->should_promote('Log in" onfocus="alert(1)" autofocus x="', 'Log in'));
        $this->assertFalse($this->should_promote("Continue' onmouseover='alert(1)", 'Continue'));
        $this->assertFalse($this->should_promote('Next\\', 'Next'));
        $this->assertFalse($this->should_promote("Next\x60", 'Next'));
        $this->assertFalse($this->should_promote('Log in&quot; onfocus=&quot;alert(1)', 'Log in'));
        $this->assertFalse($this->should_promote('Log in&#34;', 'Log in'));
        // Typographic quotes and a bare ampersand are ordinary text.
        $this->assertTrue($this->should_promote("Don\u{2019}t \u{201C}save\u{201D} & quit", 'Save'));
    }

    public function test_get_string_serves_safe_promoted_value(): void {
        $this->resetAfterTest();
        $this->promote('core', 'login', 'Sign in now');

        $this->assertSame('Sign in now', $this->serving_manager()->get_string('login'));
    }

    public function test_get_string_refuses_attribute_breakout_payload(): void {
        $this->resetAfterTest();
        // The login block renders value="' . get_string('login') . '" unescaped.
        $payload = 'Log in" onfocus="alert(document.cookie)" autofocus x="';
        $this->promote('core', 'login', $payload);

        $served = $this->serving_manager()->get_string('login');

        $this->assertStringNotContainsString('"', $served);
        $this->assertSame(get_string('login'), $served, 'falls back to the lang pack value');
    }

    public function test_get_string_serves_row_whatever_the_component_spelling(): void {
        $this->resetAfterTest();
        // Stored under the normalised name, requested under both spellings.
        $this->promote('mod_forum', 'modulename', 'Discussion board');
        $manager = $this->serving_manager();

        $this->assertSame('Discussion board', $manager->get_string('modulename', 'forum'));
        $this->assertSame('Discussion board', $manager->get_string('modulename', 'mod_forum'));
    }

    public function test_parameterised_strings_are_tracked_with_their_rendered_value(): void {
        $this->resetAfterTest();
        $manager = $this->serving_manager();
        // Collect as on a full page request (CLI_SCRIPT would otherwise switch collection off).
        (new \ReflectionProperty(string_manager::class, 'collectcontext'))->setValue(null, true);

        $manager->get_string('numdays', '', 3);
        $manager->get_string('numdays', 'moodle', 5);
        $manager->get_string('and', 'core', ['one' => 'Tea', 'two' => 'Cake']);

        $tracked = array_map(fn($s) => $s['component'] . '/' . $s['key'] . '=' . $s['value'], string_manager::get_page_strings());
        $this->assertEqualsCanonicalizing(['core/numdays=3 days', 'core/numdays=5 days', 'core/and=Tea and Cake'], $tracked);
    }

    public function test_promoted_template_is_served_with_values_filled_in(): void {
        $this->resetAfterTest();
        $this->promote('core', 'numdays', '{$a} jours');
        $this->promote('core', 'and', '{$a->two} und {$a->one}');
        $manager = $this->serving_manager();

        $this->assertSame('5 jours', $manager->get_string('numdays', '', 5));
        $this->assertSame('Cake und Tea', $manager->get_string('and', 'core', (object)['one' => 'Tea', 'two' => 'Cake']));
    }

    public function test_promoted_template_with_wrong_placeholders_is_not_served(): void {
        $this->resetAfterTest();
        // A dropped or unknown placeholder would lose a value or print it raw.
        $this->promote('core', 'numdays', 'quelques jours');
        $this->promote('core', 'and', '{$a->one} und {$a->three}');
        $manager = $this->serving_manager();

        $this->assertSame('5 days', $manager->get_string('numdays', '', 5));
        $this->assertSame('Tea and Cake', $manager->get_string('and', 'core', ['one' => 'Tea', 'two' => 'Cake']));
    }
}
