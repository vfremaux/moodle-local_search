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
 * General function library
 *
 * This file must not contain any PHP 5, because it is used to test for PHP 5
 * itself, and needs to be able to be executed on PHP 4 installations.
 *
 */

/**
 * Constants
 */
define('SEARCH_INDEX_PATH', "{$CFG->dataroot}/search");
define('SEARCH_DATABASE_TABLE', 'local_search_documents');

// Get document types.
include "{$CFG->dirroot}/local/search/searchtypes.php";

/**
 * collects all searchable items identities
 * @param boolean $namelist if true, only returns list of names of searchable items
 * @param boolean $verbose if true, prints a discovering status
 * @return an array of names or an array of type descriptors
 */
function search_collect_searchables($namelist=false, $verbose=false) {
    global $CFG, $DB;

    $searchables = array();
    $searchables_names = array();

    // Get all installed modules.
    if ($mods = $DB->get_records('modules', array(), 'name', 'id,name')) {

        $searchabletypes = array_values(search_get_document_types());

        foreach ($mods as $mod) {
            if (in_array($mod->name, $searchabletypes)) {
                $mod->location = 'internal';
                $searchables[$mod->name] = $mod;
                $searchables_names[] = $mod->name;
            } else {
                $documentfile = $CFG->dirroot."/mod/{$mod->name}/search_document.php";
                $mod->location = 'mod';
                if (file_exists($documentfile)){
                    $searchables[$mod->name] = $mod;
                    $searchables_names[] = $mod->name;
                }
            }
        }
        if ($verbose) mtrace(count($searchables).' modules to search in / '.count($mods).' modules found.');
    }

    // Collects blocks as indexable information may be found in blocks either.
    if ($blocks = $DB->get_records('block', array(), 'name', 'id,name')) {
        $blocks_searchables = array();

        // Prepend the "block_" prefix to discriminate document type plugins.
        foreach($blocks as $block){
            $block->dirname = $block->name;
            $block->name = 'block_'.$block->name;
            if (in_array('SEARCH_TYPE_'.strtoupper($block->name), $searchabletypes)){
                $mod->location = 'internal';
                $blocks_searchables[] = $block;
                $searchables_names[] = $block->name;
            } else {
                $documentfile = $CFG->dirroot."/blocks/{$block->dirname}/search_document.php";
                if (file_exists($documentfile)){
                    $mod->location = 'blocks';
                    $blocks_searchables[$block->name] = $block;
                    $searchables_names[] = $block->name;
                }
            }
        }
        if ($verbose) mtrace(count($blocks_searchables).' blocks to search in / '.count($blocks).' blocks found.');
        $searchables = array_merge($searchables, $blocks_searchables);
    }

    // Add virtual modules onto the back of the array.

    $additional = search_get_additional_modules();
    if (!empty($additional)) {
        if ($verbose) mtrace(count($additional).' additional to search in.');
        $searchables = array_merge($searchables, $additional);
    }

    if ($namelist) {
        return $searchables_names;
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
function search_shorten_url($url, $length=30) {
    return substr($url, 0, $length)."...";
}

/**
 * a local function for escaping
 * @param str the string to escape
 * @return the escaped string
 */
function search_escape_string($str) {
    global $CFG;

    switch ($CFG->dbfamily) {
        case 'mysql':
            $s = mysql_real_escape_string($str);
            break;
        case 'postgres':
            $s = pg_escape_string($str);
            break;
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
    if (!empty($GLOBALS['search_script_start_time'])) {
        if (!$cli) {
            print '<em>';
        }
        print round(microtime(true) - $GLOBALS['search_script_start_time'], 6).' '.get_string('seconds', 'local_search');
        if (!$cli) {
            print '</em>';
        }
        unset($GLOBALS['search_script_start_time']);
    } else {
        $GLOBALS['search_script_start_time'] = microtime(true);
    }
}

/**
 * print and exit (for debugging)
 * @param str a variable to explore
 * @return void
 */
function search_pexit($str = '') {
    if (is_array($str) or is_object($str)) {
        print_r($str);
    } elseif ($str) {
        print $str."<br/>";
    }
    exit(0);
}

/**
 * get text from a physical file 
 * @param array $documents an eventual document array to feed
 * @param object $file the moodle file record to index
 * @param object $object the initial object the document is linked to
 * @param string $objectdocumentclass the document class name that must be instanciated
 * @return a search document when unique or false if failure.
 */
function search_get_physical_file(&$documents, &$file, &$object, $contextid, $objectdocumentclass, $getsingle = false) {
    global $CFG;

    $config = get_config('block_search');

    // Cannot index missing file.
    if ($file->is_directory()) {
        continue;
    }

    $contenthash = $file->get_contenthash();
    $l1 = $contenthash[0].$contenthash[1];
    $l2 = $contenthash[2].$contenthash[3];
    $physicalfilepath = $CFG->dataroot.'/filedir/'.$l1.'/'.$l2.'/'.$contenthash;

    if (!file_exists($physicalfilepath)){
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
        mtrace($ext . ' is not an allowed extension for indexing');
        return false;
    }

    if (file_exists($CFG->dirroot.'/local/search/documents/physical_'.$ext.'.php')){
        include_once($CFG->dirroot.'/local/search/documents/physical_'.$ext.'.php');
        $function_name = 'get_text_for_indexing_'.$ext;
        $object->alltext = $function_name($physicalfilepath);

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
function data_get_comments($pluginname, $instanceid) {
    global $CFG, $DB;

    $cm = get_coursemodule_from_instance($pluginname, $instanceid);
    $context = context_instance::module($cm->id);

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
