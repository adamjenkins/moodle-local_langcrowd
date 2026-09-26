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
 * Safety rules for crowd-sourced translation text.
 *
 * @package    local_langcrowd
 * @copyright  2026 Adam Jenkins <adam@wisecat.net>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_langcrowd\local;

/**
 * Decides which user-written text may be served as a language string.
 *
 * Core emits get_string() output unescaped, not only as element content but
 * inside double- and single-quoted HTML attributes (e.g. core_renderer's logo
 * alt text, block_login's submit value, mod_lesson's buttons) and in inline
 * JavaScript. Blocking tags alone is therefore not enough: a quote breaks out
 * of an attribute, a backslash or backtick out of a JavaScript string, and a
 * character reference turns into a quote inside an event-handler attribute. A
 * translation never needs those, so they are refused outright; the straight
 * quotes people type naturally are converted to typographic ones.
 */
class text_safety {
    /** Maximum length of a suggestion, in characters (mirrors the textarea maxlength in voting.js). */
    public const MAX_SUGGESTION_LENGTH = 4096;

    /** A string placeholder as core's get_string() substitutes it. */
    public const PLACEHOLDER_PATTERN = '/\\{\\$a(?:->[A-Za-z0-9_]+)?\\}/';

    /** Characters that can break out of an HTML attribute or a JavaScript string ("\x60" is the backtick). */
    protected const UNSAFE_CHARS = ['<', '>', '"', "'", '\\', "\x60"];

    /**
     * Converts straight quotes to typographic ones and trims the text.
     *
     * Apostrophes become U+2019; double quotes alternate between U+201C and U+201D.
     *
     * @param string $text
     * @return string
     */
    public static function normalise(string $text): string {
        $text = str_replace("'", "\u{2019}", trim($text));
        $open = true;
        return preg_replace_callback('/"/', function () use (&$open) {
            $quote = $open ? "\u{201C}" : "\u{201D}";
            $open = !$open;
            return $quote;
        }, $text);
    }

    /**
     * Whether the text may be served through get_string() or exported to a lang pack.
     *
     * @param string $text
     * @return bool
     */
    public static function is_safe(string $text): bool {
        // Placeholders ({$a}, {$a->name}) are filled in by the string manager, never printed
        // as-is; the '>' of '->' is not a breakout character there.
        $text = preg_replace(self::PLACEHOLDER_PATTERN, '', $text);
        foreach (self::UNSAFE_CHARS as $char) {
            if (strpos($text, $char) !== false) {
                return false;
            }
        }
        // A character reference is decoded before an event-handler attribute's JavaScript
        // runs (&#39; becomes a quote there), and browsers still decode legacy named
        // references written without ';' (&quot), so refuse any ampersand followed by '#'
        // or an alphanumeric. A plain ampersand ("Terms & conditions") is fine.
        return !preg_match('/&[#a-z0-9]/i', $text);
    }

    /**
     * The placeholders ({$a} and {$a->name}) a string template uses, sorted, with repeats.
     *
     * @param string $text
     * @return string[]
     */
    public static function placeholders(string $text): array {
        preg_match_all(self::PLACEHOLDER_PATTERN, $text, $matches);
        $result = $matches[0];
        sort($result);
        return $result;
    }

    /**
     * Whether a translation uses the same placeholders as its source (order may differ).
     *
     * A translation without {$a->days} would silently drop that value; one with an unknown
     * placeholder would print it raw.
     *
     * @param string $translation
     * @param string $source
     * @return bool
     */
    public static function same_placeholders(string $translation, string $source): bool {
        return array_values(array_unique(self::placeholders($translation)))
            === array_values(array_unique(self::placeholders($source)));
    }
}
