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
 * Shared participation-gate helpers for local_langcrowd.
 *
 * @package    local_langcrowd
 * @copyright  2026 Adam Jenkins <adam@wisecat.net>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_langcrowd;

/**
 * Central place for the "may this user take part in crowdsourcing?" decision.
 *
 * The footer hook uses these to decide whether to inject the overlay, and the
 * external functions use them to enforce the same restrictions server-side so
 * the "allowed roles" / "allowed languages" settings cannot be bypassed by
 * calling the web services directly.
 */
class access {
    /**
     * Whether the crowdsourcing overlay is globally enabled.
     *
     * @return bool
     */
    public static function is_enabled(): bool {
        return (bool)get_config('local_langcrowd', 'enabled');
    }

    /**
     * Whether the given user is allowed to participate under the role restriction.
     *
     * Site admins always qualify (they have no role_assignments rows). When no
     * roles are configured, all authenticated non-guest users qualify.
     *
     * @param int $userid
     * @return bool
     */
    public static function user_has_allowed_role(int $userid): bool {
        global $DB;

        if (is_siteadmin($userid)) {
            return true;
        }

        $allowedroles = get_config('local_langcrowd', 'allowed_roles');
        if (empty($allowedroles)) {
            return true;
        }

        $roleids = array_filter(array_map('intval', explode(',', $allowedroles)));
        if (empty($roleids)) {
            return true;
        }

        [$insql, $params] = $DB->get_in_or_equal($roleids, SQL_PARAMS_NAMED);
        $params['userid'] = $userid;
        return $DB->record_exists_sql(
            "SELECT 1 FROM {role_assignments} WHERE userid = :userid AND roleid $insql",
            $params
        );
    }

    /**
     * Whether crowdsourcing is enabled for the given language.
     *
     * @param string $lang
     * @return bool
     */
    public static function lang_is_allowed(string $lang): bool {
        $allowedlangs = get_config('local_langcrowd', 'allowed_langs');
        if (empty($allowedlangs)) {
            return true;
        }
        return in_array($lang, explode(',', $allowedlangs), true);
    }

    /**
     * Full participation gate for the current user in the current language.
     *
     * @param int    $userid
     * @param string $lang
     * @return bool
     */
    public static function user_can_participate(int $userid, string $lang): bool {
        return self::is_enabled()
            && self::user_has_allowed_role($userid)
            && self::lang_is_allowed($lang);
    }

    /**
     * Throws if the current user may not participate; call from external functions.
     *
     * @param int    $userid
     * @param string $lang
     * @throws \moodle_exception
     */
    public static function require_can_participate(int $userid, string $lang): void {
        if (!self::user_can_participate($userid, $lang)) {
            throw new \moodle_exception('nopermissions', 'error', '', get_string('pluginname', 'local_langcrowd'));
        }
    }
}
