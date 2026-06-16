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
 * External function definitions for local_langcrowd.
 *
 * @package    local_langcrowd
 * @copyright  2026 hama.history@gmail.com
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

$functions = [
    'local_langcrowd_get_string_ids' => [
        'classname'    => \local_langcrowd\external\get_string_ids::class,
        'description'  => 'Register page strings and return their DB IDs plus the user\'s existing votes.',
        'type'         => 'write',
        'ajax'         => true,
        'loginrequired' => true,
    ],
    'local_langcrowd_submit_vote' => [
        'classname'    => \local_langcrowd\external\submit_vote::class,
        'description'  => 'Submit an approve or reject vote for a language string.',
        'type'         => 'write',
        'ajax'         => true,
        'loginrequired' => true,
    ],
    'local_langcrowd_submit_suggestion' => [
        'classname'    => \local_langcrowd\external\submit_suggestion::class,
        'description'  => 'Submit an alternative translation suggestion for a language string.',
        'type'         => 'write',
        'ajax'         => true,
        'loginrequired' => true,
    ],
];
