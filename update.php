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
 * Index asynchronous updator
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
$update_count = 0;
$indexdate = 0 + @$CFG->search_indexer_update_date;
$startupdatedate = time();

/// indexing changed resources

mtrace("Starting index update (updates)...\n");

if (!isset($config)) {
    $config = get_config('block_search');
}

if ($mods = search_collect_searchables(false, true)) {

    foreach ($mods as $mod) {

        $key = 'search_in_'.$mod->name;
        if (empty($config->$key)) {
            mtrace(" module $key has been administratively disabled. Skipping...\n");
            continue;
        }

        $class_file = $CFG->dirroot.'/local/search/documents/'.$mod->name.'_document.php';
        $get_document_function = $mod->name.'_single_document';
        $delete_function = $mod->name.'_delete';
        $db_names_function = $mod->name.'_db_names';
        $updates = array();
        
        if (file_exists($class_file)) {
            require_once($class_file);

            //if both required functions exist
            if (function_exists($delete_function) and function_exists($db_names_function) and function_exists($get_document_function)) {
                mtrace("Checking $mod->name module for updates.");
                $valuesArray = $db_names_function();
                if ($valuesArray){
                    foreach ($valuesArray as $values) {

                        $where = (!empty($values[5])) ? 'AND ('.$values[5].')' : '';
                        $joinextension = (!empty($values[6])) ? $values[6] : '';
                        $itemtypes = ($values[4] != '*' && $values[4] != 'any') ? " AND itemtype = '{$values[4]}' " : '' ;

                        //TODO: check 'in' syntax with other RDBMS' (add and update.php as well)
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
                        $docIds = $DB->get_records_sql_menu($query, array($mod->name));
                        $docIdList = ($docIds) ? implode("','", array_keys($docIds)) : '' ;

                        $sql = "
                            SELECT 
                                {$values[0]} as id,
                                {$values[0]} as docid
                            FROM 
                                {{$values[1]}}
                                $joinextension
                            WHERE
                                {$values[3]} > {$indexdate} AND 
                                {$values[0]} IN ('{$docIdList}')
                                $where
                        ";
                        echo $sql;
                        $records = $DB->get_records_sql($sql);
                        if (is_array($records)) {
                            foreach($records as $record) {
                                $updates[] = $delete_function($record->docid, $docIds[$record->docid]);
                            } 
                        } 
                    }
                    
                    foreach ($updates as $update) {
                        ++$update_count;
                        
                        //delete old document
                        $doc = $index->find("+docid:{$update->id} +doctype:{$mod->name} +itemtype:{$update->itemtype}");
                        
                        //get the record, should only be one
                        foreach ($doc as $thisdoc) {
                            mtrace("  Delete: $thisdoc->title (database id = $thisdoc->dbid, index id = $thisdoc->id, moodle instance id = $thisdoc->docid)");
                            $dbcontrol->delDocument($thisdoc);
                            $index->delete($thisdoc->id);
                        } 
                        
                        //add new modified document back into index
                        if (!$add = $get_document_function($update->id, $update->itemtype)){
                            // ignore on errors
                            continue;
                        }
                        
                        //object to insert into db
                        $dbid = $dbcontrol->addDocument($add);
                        
                        //synchronise db with index
                        $add->addField(Zend_Search_Lucene_Field::Keyword('dbid', $dbid));
                        mtrace("  Add: $add->title (database id = $add->dbid, moodle instance id = $add->docid)");
                        $index->addDocument($add);
                    } 
                }
                else{
                    mtrace("No types to update.\n");
                }
                mtrace("Finished $mod->name.\n");
            } 
        } 
    } 
} 

//commit changes
$index->commit();

//update index date
set_config("search_indexer_update_date", $startupdatedate);

mtrace("Finished $update_count updates");

