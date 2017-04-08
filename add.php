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
 * Asynchronous adder for new indexable contents
 *
 * Major chages in this review is passing the xxxx_db_names return to
 * multiple arity to handle multiple document types modules
 */

defined('MOODLE_INTERNAL') || die();

// Makes inclusions of the Zend Engine more reliable.
ini_set('include_path', $CFG->dirroot.DIRECTORY_SEPARATOR.'local'.DIRECTORY_SEPARATOR.'search'.PATH_SEPARATOR.ini_get('include_path'));

require_once($CFG->dirroot.'/local/search/lib.php');
require_once($CFG->dirroot.'/local/search/indexlib.php');

// Checks global search activation.

$config = get_config('local_search');

if (empty($config->enable)) {
    print_error('globalsearchdisabled', 'local_search');
}

try {
    $index = new Zend_Search_Lucene(SEARCH_INDEX_PATH);
} catch (LuceneException $e) {
    $message = 'Could not construct a valid index. Maybe the first indexation was never made, ';
    $message .= 'or files might be corrupted. Run complete indexation again.';
    mtrace($message);
    return;
}

$dbcontrol = new IndexDBControl();
$additioncount = 0;
$startindextime = time();

$indexdate = $config->rundate;

mtrace('Starting index update (additions)...');
mtrace('Index size before: '.$config->index_size."\n");

// Get all modules.
if ($mods = search_collect_searchables(false, true)) {

// Append virtual modules onto array.

    foreach ($mods as $mod) {

        $key = 'search_in_'.$mod->name;
        if (empty($config->$key)) {
            mtrace(" module $key has been administratively disabled. Skipping...\n");
            continue;
        }

        // Build include file and function names.
        $classfile = $CFG->dirroot.'/local/search/documents/'.$mod->name.'_document.php';
        $additions = array();

        if (file_exists($classfile)) {
            require_once($classfile);
            $wrapperclass = '\\local_search\\'.$mod->name.'_document_wrapper';

            // If both required functions exist.
            mtrace("Checking $mod->name module for additions.");

            $valuesarr = $wrapperclass::db_names();

            if ($valuesarr) {
                foreach ($valuesarr as $values) {
                    $where = (!empty($values[5])) ? 'AND ('.$values[5].')' : '';
                    $joinextension = (!empty($values[6])) ? $values[6] : '';
                    $itemtypes = ($values[4] != '*' && $values[4] != 'any') ? " AND itemtype = '{$values[4]}' " : '';

                    // Select records in MODULE table, but not in SEARCH_DATABASE_TABLE.
                    $table = SEARCH_DATABASE_TABLE;
                    $sql = "
                        SELECT
                            docid,
                            itemtype
                        FROM
                            {{$table}}
                        WHERE
                            doctype = '{$values[1]}'
                            $itemtypes
                    ";
                    $docids = $DB->get_records_sql_menu($sql, array($mod->name));
                    $docidlist = ($docids) ? implode("','", array_keys($docids)) : '';

                    $sql =  "
                        SELECT
                            {$values[0]} as id,
                            {$values[0]} as docid
                        FROM
                            {{$values[1]}}
                            $joinextension
                        WHERE
                            {$values[0]} NOT IN ('{$docidlist}') AND
                            {$values[2]} > {$indexdate}
                            $where
                    ";
                    $records = $DB->get_records_sql($sql);

                    // Foreach record, build a module specific search document using the get_document function.
                    if (is_array($records)) {
                        foreach ($records as $record) {
                            $add = $wrapperclass::single_document($record->docid, $values[4]);
                            // Some documents may not be indexable.
                            if ($add) {
                                $additions[] = $add;
                            }
                        }
                    }
                }

                // Foreach document, add it to the index and database table.
                foreach ($additions as $add) {
                    ++$additioncount;

                    // Object to insert into db.
                    $dbid = $dbcontrol->addDocument($add);

                    // Synchronise db with index.
                    $add->addField(Zend_Search_Lucene_Field::Keyword('dbid', $dbid));

                    mtrace("Add: $add->title (database id = $add->dbid, moodle instance id = $add->docid)");

                    $index->addDocument($add);
                }
            } else {
                mtrace("No types to add.\n");
            }
            mtrace("Finished $mod->name.\n");
        }
    }
}

// Commit changes.

$index->commit();

// Update index date and size.

set_config('run_date', $startindextime, 'local_search');
set_config('index_size', (int)$config->index_size + (int)$additioncount, 'local_search');

// Print some additional info.

mtrace("Added $additioncount documents.");
mtrace('Index size after: '.$index->count());

