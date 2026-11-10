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
 * Event observers for local_langcrowd.
 *
 * @package    local_langcrowd
 * @copyright  2026 Adam Jenkins <adam@wisecat.net>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_langcrowd;

/**
 * Reacts to core events that affect the plugin's data.
 */
class observer {
    /**
     * Removes a deleted account's votes and suggestions and recounts the strings it voted on,
     * so its votes no longer count toward the lock threshold and its suggestions leave the
     * admin report. Translations already promoted from its suggestions are kept.
     *
     * @param \core\event\user_deleted $event
     */
    public static function user_deleted(\core\event\user_deleted $event): void {
        manager::delete_user_data([(int)$event->objectid]);
    }
}
