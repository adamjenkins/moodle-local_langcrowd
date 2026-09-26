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
 * Component-name helpers for local_langcrowd.
 *
 * @package    local_langcrowd
 * @copyright  2026 Adam Jenkins <adam@wisecat.net>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_langcrowd\local;

/**
 * Normalises component names and lists the components an admin can choose from.
 *
 * get_string() accepts several spellings of the same component ('moodle' and
 * 'core', 'forum' and 'mod_forum', 'admin' and 'core_admin'). Everything this
 * plugin stores, filters or compares uses the normalised frankenstyle name, so
 * one string never ends up split across two rows.
 */
class components {
    /**
     * Memoised normalisations; get_string() is called thousands of times per page.
     *
     * @var array
     */
    protected static array $normalised = [];

    /**
     * Returns the normalised frankenstyle name of a component ('' and 'moodle' become 'core').
     *
     * @param string $component
     * @return string
     */
    public static function normalise(string $component): string {
        if (!isset(self::$normalised[$component])) {
            self::$normalised[$component] = $component === ''
                ? 'core'
                : \core_component::normalize_componentname($component);
        }
        return self::$normalised[$component];
    }

    /**
     * Normalises each entry of a comma-separated list, dropping empties and duplicates.
     *
     * @param string $csv
     * @return string[]
     */
    public static function normalise_list(string $csv): array {
        $result = [];
        foreach (explode(',', $csv) as $component) {
            $component = trim($component);
            if ($component !== '') {
                $result[] = self::normalise($component);
            }
        }
        return array_values(array_unique($result));
    }

    /**
     * Every component whose strings could be crowdsourced: core, its subsystems and all
     * installed plugins. Used for the admin component filter, so a component can be
     * selected before any of its strings has been seen.
     *
     * @return string[] sorted normalised component names
     */
    public static function installed(): array {
        $result = ['core'];
        foreach (array_keys(\core_component::get_core_subsystems()) as $subsystem) {
            $result[] = 'core_' . $subsystem;
        }
        foreach (array_keys(\core_component::get_plugin_types()) as $type) {
            foreach (array_keys(\core_component::get_plugin_list($type)) as $name) {
                $result[] = $type . '_' . $name;
            }
        }
        $result = array_values(array_unique($result));
        sort($result);
        return $result;
    }

    /**
     * The lang-pack file name (without .php) that holds a component's strings.
     *
     * Mirrors core_string_manager_standard::load_component_strings(): core lives in
     * moodle.php, core subsystems and activity modules drop their prefix, every other
     * plugin type keeps its full frankenstyle name.
     *
     * @param string $component
     * @return string
     */
    public static function lang_file_name(string $component): string {
        [$type, $plugin] = \core_component::normalize_component(self::normalise($component));
        if ($type === 'core') {
            return $plugin ?? 'moodle';
        }
        if ($type === 'mod') {
            return $plugin;
        }
        return $type . '_' . $plugin;
    }
}
