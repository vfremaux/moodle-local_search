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
 * @author Michael Champanis (mchampan) [cynnical@gmail.com], Valery Fremaux [valery.fremaux@club-internet.fr] > 1.8
 * @date 2008/03/31
 * @license http://www.gnu.org/copyleft/gpl.html GNU Public License
 *
 * General function library
 *
 * This file must not contain any PHP 5, because it is used to test for PHP 5
 * itself, and needs to be able to be executed on PHP 4 installations.
 */
defined('MOODLE_INTERNAL') || die();

define('SEARCH_INDEX_PATH', "{$CFG->dataroot}/search");
define('SEARCH_DATABASE_TABLE', 'local_search_documents');

require_once($CFG->dirroot.'/local/search/extra/lib.php');

// Get document types.
require_once($CFG->dirroot.'/local/search/searchtypes.php');

/**
 * collects all searchable items identities
 * @param boolean $namelist if true, only returns list of names of searchable items
 * @param boolean $verbose if true, prints a discovering status
 * @return an array of names or an array of type descriptors
 */
function search_collect_searchables($namelist = false, $verbose = false) {
    global $CFG, $DB;

    $searchables = array();
    $searchablesnames = array();

    // Get all installed modules.
    if ($mods = $DB->get_records('modules', array(), 'name', 'id,name')) {

        $searchabletypes = array_values(search_get_document_types());

        foreach ($mods as $mod) {
            if (in_array($mod->name, $searchabletypes)) {
                $mod->location = 'internal';
                $searchables[$mod->name] = $mod;
                $searchablesnames[] = $mod->name;
            } else {
                $documentfile = $CFG->dirroot."/mod/{$mod->name}/search_document.php";
                $mod->location = 'mod';
                if (file_exists($documentfile)) {
                    $searchables[$mod->name] = $mod;
                    $searchablesnames[] = $mod->name;
                }
            }
        }
        if ($verbose) {
            mtrace(count($searchables).' modules to search in / '.count($mods).' modules found.');
        }
    }

    // Collects blocks as indexable information may be found in blocks either.
    if ($blocks = $DB->get_records('block', array(), 'name', 'id,name')) {
        $blockssearchables = array();

        // Prepend the "block_" prefix to discriminate document type plugins.
        foreach ($blocks as $block) {
            $block->dirname = $block->name;
            $block->name = 'block_'.$block->name;
            if (in_array('SEARCH_TYPE_'.strtoupper($block->name), $searchabletypes)) {
                $mod->location = 'internal';
                $blockssearchables[] = $block;
                $searchablesnames[] = $block->name;
            } else {
                $documentfile = $CFG->dirroot.'/blocks/'.$block->dirname.'/search_document.php';
                if (file_exists($documentfile)) {
                    $mod->location = 'blocks';
                    $blockssearchables[$block->name] = $block;
                    $searchablesnames[] = $block->name;
                }
            }
        }

        if ($verbose) {
            mtrace(count($blockssearchables).' blocks to search in / '.count($blocks).' blocks found.');
        }

        $searchables = array_merge($searchables, $blockssearchables);
    }

    // Collects indexable information that may be found in local components.
    if ($locals = glob($CFG->dirroot.'/local/*')) {
        $localsearchables = array();

        // Prepend the "block_" prefix to discriminate document type plugins.
        foreach ($locals as $localpath) {
            $component = basename($localpath);
            $local = new StdClass;
            $local->dirname = $component;
            $local->name = 'local_'.$component;
            if (in_array('SEARCH_TYPE_'.strtoupper($component), $searchabletypes)) {
                $mod->location = 'internal';
                $localsearchables[] = $local;
                $searchablesnames[] = $local->name;
            } else {
                $documentfile = $CFG->dirroot."/local/{$component}/search_document.php";
                if (file_exists($documentfile)) {
                    $local->location = 'local';
                    $localsearchables[$local->name] = $local;
                    $searchablesnames[] = $local->name;
                }
            }
        }
        if ($verbose) {
            mtrace(count($localsearchables).' local to search in / '.count($locals).' plugins found.');
        }
        $searchables = array_merge($searchables, $localsearchables);
    }

    // Add virtual modules onto the back of the array.

    $additional = search_get_additional_modules();
    if (!empty($additional)) {
        if ($verbose) {
            mtrace(count($additional).' additional to search in.');
        }
        $searchables = array_merge($searchables, $additional);
    }

    if ($namelist) {
        return $searchablesnames;
    }
    return $searchables;
}

/**
 * returns all the document type constants that are known in core implementation
 * @param prefix a pattern for recognizing constants
 * @return an array of type labels
 */
function search_get_document_types($prefix = 'SEARCH_TYPE_') {
    $ret = array();
    foreach (get_defined_constants() as $key => $value) {
        if (preg_match("/^{$prefix}/", $key)) {
            $ret[$key] = $value;
        }
    }
    sort($ret);
    return $ret;
}

/**
 * additional virtual modules to index
 *
 * By adding 'moo' to the extras array, an additional document type
 * documents/moo_document.php will be indexed - this allows for
 * virtual modules to be added to the index, i.e. non-module specific
 * information.
 */
function search_get_additional_modules() {
    $extras = array(/* additional keywords go here */);
    if (defined('SEARCH_EXTRAS')) {
        $extras = explode(',', SEARCH_EXTRAS);
    }

    $ret = array();
    $temp = new StdClass;
    foreach ($extras as $extra) {
        $temp->name = $extra;
        $temp->location = 'internal';
        $ret[$temp->name] = clone($temp);
    }
    return $ret;
}

/**
 * shortens a url so it can fit on the results page
 * @param url the url
 * @param length the size limit we want
 */
function search_shorten_url($url, $length = 30) {
    return substr($url, 0, $length).'...';
}

/**
 * a local function for escaping
 * @param str the string to escape
 * @return the escaped string
 */
function search_escape_string($str) {
    global $CFG;

    switch ($CFG->dbfamily) {
        case 'mysql': {
            $s = mysql_real_escape_string($str);
            break;
        }
        case 'postgres': {
            $s = pg_escape_string($str);
            break;
        }

        default:
            $s = addslashes($str);
    }
    return $s;
}

/**
 * simple timer function, on first call, records a current microtime stamp, outputs result on 2nd call
 * @param cli an output formatting switch
 * @return void
 */
function search_stopwatch($cli = false) {
    static $searchscriptstarttime = null;

    if (!is_null($searchscriptstarttime)) {
        if (!$cli) {
            print '<em>';
        }
        echo round(microtime(true) - $searchscriptstarttime, 6).' '.get_string('seconds', 'local_search');
        if (!$cli) {
            print '</em>';
        }
        unset($searchscriptstarttime);
    } else {
        $searchscriptstarttime = microtime(true);
    }
}

/**
 * get text from a physical file
 * @param arrayref &$documents an eventual document array to feed with resulting pseudo_documents
 * @param objectref &$file the moodle file record to index
 * @param objectref &$object the initial object the document is linked to
 * @param string $objectdocumentclass the document class name that must be instanciated
 * @param string $getsingle if true, returns the single pseudo_document. If false, will add the document to the document array.
 * @return a search document when unique or false if failure.
 */
function search_get_physical_file(&$documents, &$file, &$object, $contextid, $objectdocumentclass, $getsingle = false) {
    global $CFG;

    $config = get_config('local_search');

    if (strpos($objectdocumentclass, '\\local_search\\') === false) {
        $objectdocumentclass = '\\local_search\\'.$objectdocumentclass;
    }

    // Cannot index missing file.
    if ($file->is_directory()) {
        return;
    }

    $contenthash = $file->get_contenthash();
    $l1 = $contenthash[0].$contenthash[1];
    $l2 = $contenthash[2].$contenthash[3];
    $physicalfilepath = $CFG->dataroot.'/filedir/'.$l1.'/'.$l2.'/'.$contenthash;

    if (!file_exists($physicalfilepath)) {
        mtrace("Missing file at $physicalfilepath : will not be indexed.");
        return false;
    }

    $filename = $file->get_filename();
    if (!preg_match('/\.([^\.]+)$/', $filename, $matches)) {
        mtrace("Undeterminable extension for file {$filename}.");
        return false;
    }
    $ext = $matches[1];

    // Cannot index unallowed or unhandled types.
    if (!preg_match("/\b$ext\b/i", $config->filetypes)) {
        mtrace($ext.' is not an allowed extension for indexing');
        return false;
    }

    if (file_exists($CFG->dirroot.'/local/search/documents/physical_'.$ext.'.php')) {
        include_once($CFG->dirroot.'/local/search/documents/physical_'.$ext.'.php');
        $functionname = 'get_text_for_indexing_'.$ext;
        $object->alltext = $functionname($physicalfilepath);

        if (!empty($object->alltext)) {
            if ($getsingle) {
                $vars = get_object_vars($object);
                $single = new $objectdocumentclass($vars, $contextid);
                mtrace("finished file $object->name ");
                return $single;
            } else {
                $vars = get_object_vars($object);
                $document = new $objectdocumentclass($vars, $contextid);
                $documents[] = $document;
            }
            mtrace("finished file $object->name");
        }
    } else {
        mtrace("fulltext handler not found for $ext type");
    }
    return false;
}

/**
 * fetches all the comments in the comment table for a particular itemid
 * Each comment will be recorded as a single document, but will link back to the instance record
 * @param database_id the database
 * @uses CFG
 * @return an array of objects representing the data record comments.
 */
function search_get_comments($pluginname, $instanceid) {
    global $CFG, $DB;

    $cm = get_coursemodule_from_instance($pluginname, $instanceid);
    $context = context_module::instance($cm->id);

    $sql = "
       SELECT
            *
       FROM
          {comments} as c
       WHERE
          c.contextid = ?
    ";
    $comments = $DB->get_records_sql($sql, array($context->id));
    return $comments;
}
