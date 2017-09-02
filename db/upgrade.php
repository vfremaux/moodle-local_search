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

defined('MOODLE_INTERNAL') || die();

function xmldb_local_search_upgrade($oldversion = 0) {

    $result = true;

    if ($oldversion >= 2016021300) {
        // Transfer all block settings to local scope.
        $blockconfig = get_config('block_search');
        foreach ($blockconfig as $key => $value) {
            if (!in_array($key, array('text', 'button'))) {
                set_config($key, $value, 'local_config');
                set_config($key, null, 'block_config');
            }
        }
    }

    return $result;
}
