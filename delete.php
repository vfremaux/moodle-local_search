<?php
/**
 * Global Search Engine for Moodle
 *
 * @package local_search
 * @subpackage search_engine
 * @author Michael Champanis (mchampan) [cynnical@gmail.com], Valery Fremaux [valery.fremaux@club-internet.fr] > 1.8
 * @date 2008/03/31
 * @license http://www.gnu.org/copyleft/gpl.html GNU Public License
 *
 * Asynchronous index cleaner
 *
 * Major chages in this review is passing the xxxx_db_names return to
 * multiple arity to handle multiple document types modules
 */

if (!defined('MOODLE_INTERNAL')) {
    die('Direct access to this script is forbidden.');    ///  It must be included from the cron script
}

/// makes inclusions of the Zend Engine more reliable
ini_set('include_path', $CFG->dirroot.DIRECTORY_SEPARATOR.'local'.DIRECTORY_SEPARATOR.'search'.PATH_SEPARATOR.ini_get('include_path'));

require_once($CFG->dirroot.'/local/search/lib.php');
require_once($CFG->dirroot.'/local/search/indexlib.php');        

try {
    $index = new Zend_Search_Lucene(SEARCH_INDEX_PATH);
} catch(LuceneException $e) {
    mtrace("Could not construct a valid index. Maybe the first indexation was never made, or files might be corrupted. Run complete indexation again.");
    return;
}

$dbcontrol = new IndexDBControl();
$deletion_count = 0;
$startcleantime = time();

mtrace('Starting clean-up of removed records...');
mtrace('Index size before: '.$CFG->search_index_size."\n");

if (!isset($config)) {
    $config = get_config('block_search');
}

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
            if (function_exists($delete_function) and function_exists($db_names_function)) {
                mtrace("Checking $mod->name module for deletions.");
                $valuesArray = $db_names_function();
                if ($valuesArray) {
                    foreach($valuesArray as $values) {
                       $where = (!empty($values[5])) ? 'WHERE '.$values[5] : '';
                       $joinextension = (!empty($values[6])) ? $values[6] : '';
                       $itemtypes = ($values[4] != '*' && $values[4] != 'any') ? " itemtype = '{$values[4]}' AND " : '' ;
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
                        $docIdList = ($docIds) ? implode("','", array_keys($docIds)) : '' ;

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

// commit changes.

$index->commit();

// update index date and index size.

set_config('search_indexer_cleanup_date', $startcleantime);
set_config('search_index_size', (int)$CFG->search_index_size - (int)$deletion_count);

mtrace("Finished $deletion_count removals.");
mtrace('Index size after: '.$index->count());
