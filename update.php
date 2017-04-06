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
 * Index asynchronous updator
 *
 * Major chages in this review is passing the xxxx_db_names return to
 * multiple arity to handle multiple document types modules
 */
defined('MOODLE_INTERNAL') || die();


// Makes inclusions of the Zend Engine more reliable.

ini_set('include_path', $CFG->dirroot.DIRECTORY_SEPARATOR.'local'.DIRECTORY_SEPARATOR.'search'.PATH_SEPARATOR.ini_get('include_path'));

require_once($CFG->dirroot.'/local/search/lib.php');
require_once($CFG->dirroot.'/local/search/indexlib.php');

try {
    $index = new Zend_Search_Lucene(SEARCH_INDEX_PATH);
} catch (LuceneException $e) {
    mtrace("Could not construct a valid index. Maybe the first indexation was never made, or files might be corrupted. Run complete indexation again.");
    return;
}

if (!isset($config)) {
    $config = get_config('local_search');
}

$dbcontrol = new IndexDBControl();
$update_count = 0;
$indexdate = 0 + @$config->update_date;
$startupdatedate = time();

// Indexing changed resources.

mtrace("Starting index update (updates)...\n");

if ($mods = search_collect_searchables(false, true)) {

    foreach ($mods as $mod) {

        $key = 'search_in_'.$mod->name;
        if (empty($config->$key)) {
            mtrace(" module $key has been administratively disabled. Skipping...\n");
            continue;
        }

        $classfile = $CFG->dirroot.'/local/search/documents/'.$mod->name.'_document.php';
        $getdocumentfunction = $mod->name.'_single_document';
        $deletefunction = $mod->name.'_delete';
        $dbnamesfunction = $mod->name.'_db_names';
        $updates = array();

        if (file_exists($classfile)) {
            require_once($classfile);

            // If both required functions exist.
            if (function_exists($deletefunction) && function_exists($dbnamesfunction) && function_exists($getdocumentfunction)) {
                mtrace("Checking $mod->name module for updates.");
                $valuesarray = $dbnamesfunction();
                if ($valuesarray){
                    foreach ($valuesarray as $values) {

                        $where = (!empty($values[5])) ? 'AND ('.$values[5].')' : '';
                        $joinextension = (!empty($values[6])) ? $values[6] : '';
                        $itemtypes = ($values[4] != '*' && $values[4] != 'any') ? " AND itemtype = '{$values[4]}' " : '';

                        // TODO: check 'in' syntax with other RDBMS' (add and update.php as well).
                        $table = SEARCH_DATABASE_TABLE;
                        $query = "
                            SELECT
                                docid,
                                itemtype
                            FROM
                                {{$table}}
                            WHERE
                                doctype = '{$values[1]}'
                                $itemtypes
                        ";
                        $docids = $DB->get_records_sql_menu($query, array($mod->name));
                        $docidlist = ($docids) ? implode("','", array_keys($docids)) : '';

                        $sql = "
                            SELECT
                                {$values[0]} as id,
                                {$values[0]} as docid
                            FROM
                                {{$values[1]}}
                                $joinextension
                            WHERE
                                {$values[3]} > {$indexdate} AND
                                {$values[0]} IN ('{$docidlist}')
                                $where
                        ";
                        echo $sql;
                        $records = $DB->get_records_sql($sql);
                        if (is_array($records)) {
                            foreach ($records as $record) {
                                $updates[] = $deletefunction($record->docid, $docids[$record->docid]);
                            }
                        }
                    }

                    foreach ($updates as $update) {
                        ++$update_count;

                        // Delete old document.
                        $doc = $index->find("+docid:{$update->id} +doctype:{$mod->name} +itemtype:{$update->itemtype}");

                        // Get the record, should only be one.
                        foreach ($doc as $thisdoc) {
                            $message = " Delete: $thisdoc->title (database id = $thisdoc->dbid, index id = $thisdoc->id, ";
                            $message .= " moodle instance id = $thisdoc->docid)";
                            mtrace($message);
                            $dbcontrol->delDocument($thisdoc);
                            $index->delete($thisdoc->id);
                        }

                        // Add new modified document back into index.
                        if (!$add = $getdocumentfunction($update->id, $update->itemtype)) {
                            // Ignore on errors.
                            continue;
                        }

                        // Object to insert into db.
                        $dbid = $dbcontrol->addDocument($add);

                        // Synchronise db with index.
                        $add->addField(Zend_Search_Lucene_Field::Keyword('dbid', $dbid));
                        mtrace("  Add: $add->title (database id = $add->dbid, moodle instance id = $add->docid)");
                        $index->addDocument($add);
                    }
                } else {
                    mtrace("No types to update.\n");
                }
                mtrace("Finished $mod->name.\n");
            }
        }
    }
}

// Commit changes.
$index->commit();

// Update index date.
set_config('update_date', $startupdatedate, 'local_search');

mtrace("Finished $update_count updates");

