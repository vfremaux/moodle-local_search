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
* The indexer logic -
*
* Look through each installed module's or block's search document class file (/search/documents)
* for necessary search functions, and if they're present add the content to the index.
* Repeat this for blocks.
*
* Because the iterator/retrieval functions are now stored in /search/documents/<mod>_document.php,
* /mod/mod/lib.php doesn't have to be modified - and thus the search module becomes quite
* self-sufficient. URL's are now stored in the index, stopping us from needing to require
* the class files to generate a results page.
*
* Along with the index data, each document's summary gets stored in the database
* and synchronised to the index (flat file) via the primary key ('id') which is mapped
* to the 'dbid' field in the index
* */

//this'll take some time, set up the environment
@set_time_limit(0);

require('../../config.php');
require_once($CFG->dirroot.'/local/search/lib.php');
require_once($CFG->dirroot.'/local/search/indexlib.php');

$url = new moodle_url('/local/search/indexer.php');
$PAGE->set_url($url);

$context = context_system::instance();
$PAGE->set_context($context);

ini_set('include_path', $CFG->dirroot.DIRECTORY_SEPARATOR.'local'.DIRECTORY_SEPARATOR.'search'.PATH_SEPARATOR.ini_get('include_path'));

$config = get_config('local_search');

// Only administrators can index the moodle installation, because access to all pages is required.

require_login();
require_capability('moodle/site:config', context_system::instance());

if (empty($config->enable)) {
    print_error('globalsearchdisabled', 'local_search');
}

// confirmation flag to prevent accidental reindexing (indexersplash.php is the correct entry point)

$sure = strtolower(optional_param('areyousure', '', PARAM_ALPHA));

if ($sure != 'yes') {
    mtrace("<pre>Sorry, you need to confirm indexing via <a href='indexersplash.php'>indexersplash.php</a>"
          .". (<a href='index.php'>Back to query page</a>).</pre>");

    exit(0);
}

// check for php5 (lib.php)

mtrace('<html><head><meta http-equiv="content-type" content="text/html; charset=utf-8" /></head><body>');
mtrace('<pre>Server Time: '.date('r',time())."\n");

if (!empty($config->indexer_busy)) {
    //means indexing was not finished previously
    mtrace("Warning: Indexing was not successfully completed last time, restarting.\n");
}

/// turn on busy flag

set_config('indexer_busy', '1', 'local_search');

//paths
$index_path = SEARCH_INDEX_PATH;
$dbcontrol = new IndexDBControl();

/// setup directory in data root

if (!file_exists($index_path)) {
    mtrace("Data directory ($index_path) does not exist, attempting to create.");
    if (!mkdir($index_path, $CFG->directorypermissions)) {
        search_pexit("Error creating data directory at: $index_path. Please correct.");
    } 
    else {
        mtrace("Directory successfully created.");
    } 
} 
else {
    mtrace("Using {$index_path} as data directory.");
} 

Zend_Search_Lucene_Analysis_Analyzer::setDefault(new Zend_Search_Lucene_Analysis_Analyzer_Common_Utf8_CaseInsensitive());
$index = new Zend_Search_Lucene($index_path, true);

/*
OBSOLETE REGENERATION - DB installs with search block by now
if (!$dbcontrol->checkDB()) {
    search_pexit("Database error. Please check settings/files.");
}
*/

/// New regeneration

mtrace('Deleting old index entries.');
$DB->delete_records(SEARCH_DATABASE_TABLE);

/// begin timer

search_stopwatch();
mtrace("Starting activity modules\n");

//the presence of the required search functions -
// * mod_iterator
// * mod_get_content_for_index
//are the sole basis for including a module in the index at the moment.

$searchables = search_collect_searchables();

$config = get_config('block_search');

// Start indexation.

if ($searchables){
    foreach ($searchables as $mod) {

        echo "start {$mod->name}";

        $key = 'search_in_'.$mod->name;
        if (empty($config->$key)) {
            mtrace(" module $key has been administratively disabled. Skipping...\n");
            continue;
        }
    
        if ($mod->location == 'internal'){
            $class_file = $CFG->dirroot.'/local/search/documents/'.$mod->name.'_document.php';
        } else {
            $class_file = $CFG->dirroot.'/'.$mod->location.'/'.$mod->name.'/search_document.php';
        }
        
        /*
        if (!file_exists($class_file)){
            if (defined("PATH_FOR_SEARCH_TYPE_{$mod->name}")){
                eval("\$pluginpath = PATH_FOR_SEARCH_TYPE_{$mod->name}");
                $class_file = "{$CFG->dirroot}/{$pluginpath}/searchlib.php";
            } else {
               mtrace ("No search document found for plugin {$mod->name}. Ignoring.");
               continue;
            }
        }
        */

        if (file_exists($class_file)) {
            include_once($class_file);

            //build function names
            $iter_function = $mod->name.'_iterator';
            $index_function = $mod->name.'_get_content_for_index';
            $counter = 0;
            if (function_exists($index_function) && function_exists($iter_function)) {
                mtrace(" Processing module function $index_function ...");
                $sources = $iter_function();
                if ($sources){
                    foreach ($sources as $i) {
                        $documents = $index_function($i);

                        //begin transaction
                        if ($documents){
                            foreach($documents as $document) {
                                $counter++;

                                //object to insert into db
                                $dbid = $dbcontrol->addDocument($document);

                                //synchronise db with index
                                $document->addField(Zend_Search_Lucene_Field::Keyword('dbid', $dbid));

                                //add document to index
                                $index->addDocument($document);

                                //commit every x new documents, and print a status message
                                if (($counter % 2000) == 0) {
                                    $index->commit();
                                    mtrace(".. $counter");
                                }
                            }
                        }
                        //end transaction
                    }
                }
        
                //commit left over documents, and finish up
                $index->commit();
      
                mtrace("-- $counter documents indexed");
                mtrace("done.\n");
            }
        } else {
           mtrace (" No search document found for plugin {$mod->name}. Ignoring.");
        }
    }
}

// Finished modules.

mtrace('Finished activity modules');
search_stopwatch();
    
mtrace(".<br/><a href='index.php'>Back to query page</a>.");
mtrace('</pre>');

/// finished, turn busy flag off

set_config('search_indexer_busy', '0');

/// mark the time we last updated

set_config('search_indexer_run_date', time());

/// and the index size

set_config('search_index_size', (int)$index->count());

