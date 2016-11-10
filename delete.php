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

// makes inclusions of the Zend Engine more reliable
ini_set('include_path', $CFG->dirroot.DIRECTORY_SEPARATOR.'local'.DIRECTORY_SEPARATOR.'search'.PATH_SEPARATOR.ini_get('include_path'));

require_once($CFG->dirroot.'/local/search/lib.php');
require_once($CFG->dirroot.'/local/search/indexlib.php');

try {
    $index = new Zend_Search_Lucene(SEARCH_INDEX_PATH);
} catch(LuceneException $e) {
    mtrace("Could not construct a valid index. Maybe the first indexation was never made, or files might be corrupted. Run complete indexation again.");
    return;
}

if (!isset($config)) {
    $config = get_config('local_search');
}

$dbcontrol = new IndexDBControl();
$deletion_count = 0;
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
        $class_file = $CFG->dirroot.'/local/search/documents/'.$mod->name.'_document.php';
        $delete_function = $mod->name.'_delete';
        $db_names_function = $mod->name.'_db_names';
        $deletions = array();

        if (file_exists($class_file)) {
            require_once($class_file);

            // If both required functions exist.
            if (function_exists($delete_function) && function_exists($db_names_function)) {
                mtrace("Checking $mod->name module for deletions.");
                $valuesArray = $db_names_function();
                if ($valuesArray) {
                    foreach ($valuesArray as $values) {
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
                        echo "sql $sql ";
                        $docIds = $DB->get_records_sql($sql);
                        $docIdList = ($docIds) ? implode("','", array_keys($docIds)) : '';

                        // Index records
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
                                docid not in ('{$docIdList}')
                        ";
                        $records = $DB->get_records_sql($sql);

                        // Build an array of all the deleted records.
                        if (is_array($records)) {
                            foreach ($records as $record) {
                                $deletions[] = $delete_function($record->docid, $values[4]);
                            }
                        }
                    }

                    foreach ($deletions as $delete) {
                        // find the specific document in the index, using it's docid and doctype as keys
                        $doc = $index->find("+docid:{$delete->id} +doctype:$mod->name +itemtype:{$delete->itemtype}");

                        // Get the record, should only be one.
                        foreach ($doc as $thisdoc) {
                            ++$deletion_count;
                            mtrace("  Delete: $thisdoc->title (database id = $thisdoc->dbid, index id = $thisdoc->id, moodle instance id = $thisdoc->docid)");

                            // Remove it from index and database table.
                            $dbcontrol->delDocument($thisdoc);
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
set_config('index_size', (int)$config->index_size - (int)$deletion_count, 'local_search');

mtrace("Finished $deletion_count removals.");
mtrace('Index size after: '.$index->count());
