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
 * Global Search Engine for Moodle
 *
 * @package local_search
 * @category local
 * @author Michael Champanis (mchampan) [cynnical@gmail.com], Valery Fremaux [valery.fremaux@club-internet.fr] > 1.8
 * @date 2008/03/31
 * @license http://www.gnu.org/copyleft/gpl.html GNU Public License
 *
 * Asynchronous index cleaner
 *
 * Major chages in this review is passing the xxxx_db_names return to
 * multiple arity to handle multiple document types modules
 */
defined('MOODLE_INTERNAL') || die();

// Makes inclusions of the Zend Engine more reliable.
$dirsep = DIRECTORY_SEPARATOR;
ini_set('include_path', $CFG->dirroot.$dirsep.'local'.$dirsep.'search'.PATH_SEPARATOR.ini_get('include_path'));

require_once($CFG->dirroot.'/local/search/lib.php');
require_once($CFG->dirroot.'/local/search/indexlib.php');

try {
    $index = new Zend_Search_Lucene(SEARCH_INDEX_PATH);
} catch (LuceneException $e) {
    $message = 'Could not construct a valid index. Maybe the first indexation was never made, ';
    $meesage .= 'or files might be corrupted. Run complete indexation again.';
    mtrace($message);
    return;
}

if (!isset($config)) {
    $config = get_config('local_search');
}

$dbcontrol = new IndexDBControl();
$deletioncount = 0;
$startcleantime = time();

mtrace('Starting clean-up of removed records...');
mtrace('Index size before: '.$config->index_size."\n");

// Check all modules.

if ($mods = search_collect_searchables(false, true)) {

    foreach ($mods as $mod) {

        $key = 'search_in_'.$mod->name;
        if (empty($config->$key)) {
            mtrace(" module $key has been administratively disabled. Skipping...\n");
            continue;
        }

        // Build function names.
        $classfile = $CFG->dirroot.'/local/search/documents/'.$mod->name.'_document.php';
        $deletions = array();

        if (file_exists($classfile)) {
            require_once($classfile);

            $wrapperclass = '\\local_search\\'.$mod->name.'_document_wrapper';

            // If both required functions exist.
            if (function_exists($deletefunction) && function_exists($dbnamesfunction)) {
                mtrace("Checking $mod->name module for deletions.");
                $valuesarr = $wrapperclass::db_names();
                if ($valuesarr) {
                    foreach ($valuesarr as $values) {
                        $where = (!empty($values[5])) ? 'WHERE '.$values[5] : '';
                        $joinextension = (!empty($values[6])) ? $values[6] : '';
                        $itemtypes = ($values[4] != '*' && $values[4] != 'any') ? " itemtype = '{$values[4]}' AND " : '';
                        $sql = "
                            SELECT
                                {$values[0]} as id,
                                {$values[0]} as docid
                            FROM
                                {{$values[1]}}
                                $joinextension
                                $where
                        ";
                        $docids = $DB->get_records_sql($sql);
                        $docidlist = ($docids) ? implode("','", array_keys($docids)) : '';

                        // Index records.
                        $table = SEARCH_DATABASE_TABLE;
                        $sql = "
                            SELECT
                                id,
                                docid
                            FROM
                                {{$table}}
                            WHERE
                                doctype = '{$mod->name}' AND
                                $itemtypes
                                docid not in ('{$docidlist}')
                        ";
                        $records = $DB->get_records_sql($sql);

                        // Build an array of all the deleted records.
                        if (is_array($records)) {
                            foreach ($records as $record) {
                                $deletions[] = $wrapperclass::delete($record->docid, $values[4]);
                            }
                        }
                    }

                    foreach ($deletions as $delete) {
                        // Find the specific document in the index, using it's docid and doctype as keys.
                        $doc = $index->find("+docid:{$delete->id} +doctype:$mod->name +itemtype:{$delete->itemtype}");

                        // Get the record, should only be one.
                        foreach ($doc as $thisdoc) {
                            ++$deletioncount;
                            $message = "  Delete: $thisdoc->title (database id = $thisdoc->dbid, ";
                            $message .= "index id = $thisdoc->id, moodle instance id = $thisdoc->docid)";
                            mtrace($message);

                            // Remove it from index and database table.
                            $dbcontrol->delete_document($thisdoc);
                            $index->delete($thisdoc->id);
                        }
                    }
                } else {
                    mtrace("No types to delete.\n");
                }
                mtrace("Finished $mod->name.\n");
            }
        }
    }
}

// Commit changes.

$index->commit();

// Update index date and index size.

set_config('cleanup_date', $startcleantime, 'local_search');
set_config('index_size', (int)$config->index_size - (int)$deletioncount, 'local_search');

mtrace("Finished $deletioncount removals.");
mtrace('Index size after: '.$index->count());
