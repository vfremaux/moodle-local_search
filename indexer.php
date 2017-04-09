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
 *
 */
require('../../config.php');
require_once($CFG->dirroot.'/local/search/lib.php');
require_once($CFG->dirroot.'/local/search/indexlib.php');

@set_time_limit(0);

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

// Confirmation flag to prevent accidental reindexing (indexersplash.php is the correct entry point).

$sure = strtolower(optional_param('areyousure', '', PARAM_ALPHA));

if ($sure != 'yes') {
    $message = "<pre>Sorry, you need to confirm indexing via <a href='indexersplash.php'>indexersplash.php</a> ";
    $message .= "(<a href='index.php'>Back to query page</a>).</pre>";
    mtrace($message);

    die;
}

// check for php5 (lib.php)

mtrace('<html><head><meta http-equiv="content-type" content="text/html; charset=utf-8" /></head><body>');
mtrace('<pre>Server Time: '.date('r',time())."\n");

if (!empty($config->indexer_busy)) {
    // Means indexing was not finished previously.
    mtrace("Warning: Indexing was not successfully completed last time, restarting.\n");
}

// Turn on busy flag.

set_config('indexer_busy', '1', 'local_search');

// Paths.
$indexpath = SEARCH_INDEX_PATH;
$dbcontrol = new IndexDBControl();

// Setup directory in data root.

if (!file_exists($indexpath)) {
    mtrace("Data directory ($indexpath) does not exist, attempting to create.");
    if (!mkdir($indexpath, $CFG->directorypermissions)) {
        search_pexit("Error creating data directory at: $indexpath. Please correct.");
    } else {
        mtrace("Directory successfully created.");
    }
} else {
    mtrace("Using {$indexpath} as data directory.");
}

Zend_Search_Lucene_Analysis_Analyzer::setDefault(new Zend_Search_Lucene_Analysis_Analyzer_Common_Utf8_CaseInsensitive());
$index = new Zend_Search_Lucene($indexpath, true);

// New regeneration.

mtrace('Deleting old index entries.');
$DB->delete_records(SEARCH_DATABASE_TABLE);

// Begin timer.

search_stopwatch();
mtrace("Starting activity modules\n");

/*
 * the presence of the required search functions -
 * mod_iterator
 * mod_get_content_for_index
 * are the only basis for including a module in the index at the moment.
 */

$searchables = search_collect_searchables();

// Start indexation.

if ($searchables) {
    foreach ($searchables as $mod) {

        mtrace("start {$mod->name}");

        $key = 'search_in_'.$mod->name;
        if (empty($config->$key)) {
            mtrace(" module $key has been administratively disabled. Skipping...\n");
            continue;
        }
    
        if ($mod->location == 'internal'){
            $classfile = $CFG->dirroot.'/local/search/documents/'.$mod->name.'_document.php';
        } else {
            $classfile = $CFG->dirroot.'/'.$mod->location.'/'.$mod->name.'/search_document.php';
        }

        if (file_exists($classfile)) {

            include_once($classfile);

            $wrapperclass = '\\local_search\\'.$mod->name.'_document_wrapper';

            $counter = 0;
            mtrace(" Processing module ...");
            $sources = $wrapperclass::get_iterator();
            if ($sources) {
                foreach ($sources as $i) {
                    $documents = $wrapperclass::get_content_for_index($i);

                    // Begin transaction.
                    if ($documents) {
                        foreach ($documents as $document) {
                            $counter++;

                            // Object to insert into db.
                            $dbid = $dbcontrol->addDocument($document);

                            // Synchronise db with index.
                            $document->addField(Zend_Search_Lucene_Field::Keyword('dbid', $dbid));

                            // Add document to index.
                            $index->addDocument($document);

                            // Commit every x new documents, and print a status message.
                            if (($counter % 2000) == 0) {
                                $index->commit();
                                mtrace(".. $counter");
                            }
                        }
                    }
                    // End transaction.
                }

                // Commit left over documents, and finish up.
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

// Finished, turn busy flag off.

set_config('indexer_busy', '0', 'local_search');

// Mark the time we last updated.

set_config('indexer_run_date', time(), 'local_search');

// And the index size.

set_config('index_size', (int)$index->count(), 'local_search');

