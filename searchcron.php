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
 * @package local_search
 * @category local
 * @author Michael Champanis (mchampan) [cynnical@gmail.com], Valery Fremaux [valery.fremaux@club-internet.fr] > 1.8
 * @date 2008/03/31
 * @license http://www.gnu.org/copyleft/gpl.html GNU Public License
 * Moodle global search engine
 */
require('../../config.php');
require_once($CFG->dirroot.'/local/search/lib.php');

echo '<pre>';
echo "Moodle 2 Plain text indexer\n";
echo "V. 2.6 20141025\n";
echo "Cron differential Update\n";
echo "-------------------------------------\n";
echo "\n";

try {
    // Overrides php limits.
    $maxtimelimit = ini_get('max_execution_time');
    ini_set('max_execution_time', 1200);
    $maxmemoryamount = ini_get('memory_limit');
    ini_set('memory_limit', '1G');

    mtrace("\n--DELETE----");
    require($CFG->dirroot.'/local/search/delete.php');
    mtrace("--UPDATE----");
    require_once($CFG->dirroot.'/local/search/update.php');
    mtrace("--ADD-------");
    require_once($CFG->dirroot.'/local/search/add.php');
    mtrace("------------");
    //mtrace("cron finished.</pre>");
    mtrace('done');
} catch(Exception $ex) {
    mtrace('Fatal exception from Lucene subsystem. Search engine may not have been updated.');
    mtrace($ex);
}

echo '</pre>';
