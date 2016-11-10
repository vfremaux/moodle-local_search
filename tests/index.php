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

/* Used to test if modules/blocks are ready to included in the search index.
 * Carries out some basic function/file existence tests - the search module
 * is expected to exist, along with the db schema files and the search data
 * directory.
 */

@set_time_limit(0);
@ob_implicit_flush(true);
@ob_end_flush();

require_once('../../../config.php');
require_once($CFG->dirroot.'/local/search/lib.php');

/// makes inclusions of the Zend Engine more reliable
ini_set('include_path', $CFG->dirroot.DIRECTORY_SEPARATOR.'search'.PATH_SEPARATOR.ini_get('include_path'));

$config = get_config('local_search');

require_login();
require_capability('moodle/site:config', context_system::instance());

if (empty($config->enable)) {
   print_error('globalsearchdisabled', 'local_search');
}

mtrace('<pre>Server Time: '.date('r',time()));
mtrace("Testing global search capabilities:\n");

$phpversion = phpversion();

// Fix paths for testing.
set_include_path(get_include_path().":../");
require_once($CFG->dirroot.'/local/search/Zend/Search/Lucene.php');

mtrace("Checking activity modules:\n");

//the presence of the required search functions -
// * mod_iterator
// * mod_get_content_for_index
//are the sole basis for including a module in the index at the moment.

/// get all installed modules
if ($mods = $DB->get_records('modules', array(), 'name', 'id,name')) {

    $searchabletypes = array_values(search_get_document_types());

    foreach ($mods as $mod) {
        if (in_array($mod->name, $searchabletypes)) {
            $mod->location = 'internal';
            $searchables[] = $mod;
        } else {
            $documentfile = $CFG->dirroot."/mod/{$mod->name}/search_document.php";
            $mod->location = 'mod';
            if (file_exists($documentfile)){
                $searchables[] = $mod;
            }
        }
    }
    mtrace(count($searchables).' modules to search in / '.count($mods).' modules found.');
}

// Collects blocks as indexable information may be found in blocks either.
if ($blocks = $DB->get_records('block', array(), 'name', 'id,name')) {
    $blocks_searchables = array();
    // prepend the "block_" prefix to discriminate document type plugins
    foreach ($blocks as $block) {
        $block->dirname = $block->name;
        $block->name = 'block_'.$block->name;
        if (in_array('SEARCH_TYPE_'.strtoupper($block->name), $searchabletypes)) {
            $mod->location = 'internal';
            $blocks_searchables[] = $block;
        } else {
            $documentfile = $CFG->dirroot."/blocks/{$block->dirname}/search_document.php";
            if (file_exists($documentfile)){
                $mod->location = 'blocks';
                $blocks_searchables[] = $block;
            }
        }
    }
    mtrace(count($blocks_searchables).' blocks to search in / '.count($blocks).' blocks found.');
    $searchables = array_merge($searchables, $blocks_searchables);
}

// Add virtual modules onto the back of the array.

$additional = search_get_additional_modules();
mtrace(count($additional).' additional to search in.');
$searchables = array_merge($searchables, $additional);

foreach ($searchables as $mod) {

    $key = 'search_in_'.$mod->name;
    if (isset($CFG->$key) && !$CFG->$key) {
        mtrace("module $key has been administratively disabled. Skipping...\n");
        continue;
    }

    if ($mod->location == 'internal') {
        $class_file = $CFG->dirroot.'/local/search/documents/'.$mod->name.'_document.php';
    } else {
        $class_file = $CFG->dirroot.'/'.$mod->location.'/'.$mod->name.'/search_document.php';
    }

    if (file_exists($class_file)) {
        include_once($class_file);

        if ($mod->location != 'internal' && !defined('X_SEARCH_TYPE_'.strtoupper($mod->name))) {
            mtrace("ERROR: Constant 'X_SEARCH_TYPE_".strtoupper($mod->name)."' is not defined in search/searchtypes.php or in module");
            continue;
        }

        $iter_function = $mod->name.'_iterator';
        $index_function = $mod->name.'_get_content_for_index';

        if (function_exists($index_function) && function_exists($iter_function)) {
            $entries = $iter_function();
            if (!empty($entries)) {
                $entry = array_pop($entries);
                $documents = $index_function($entry);
        
                if (is_array($documents)) {
                    mtrace("Success: '$mod->name' module seems to be ready for indexing.");
                } else {
                    mtrace("ERROR: $index_function() doesn't seem to be returning an array.");
                } 
            } else {
                mtrace("Success : '$mod->name' has nothing to index.");
            } 
        } else {
            mtrace("ERROR: $iter_function() and/or $index_function() does not exist in $class_file");
        } 
    } else {
        mtrace("Notice: $class_file does not exist, this module will not be indexed.");
    } 
} 

// Finished modules.
mtrace("\nFinished checking activity modules.");

// Now blocks...
//

mtrace("<br/><a href='../index.php'>Back to query page</a> or <a href='../indexersplash.php'>Start indexing</a>.");
mtrace('</pre>');
