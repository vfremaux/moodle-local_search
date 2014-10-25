<?php
/**
* Global Search Engine for Moodle
*
* @package local_search
* @subpackage document_wrappers
* @author Valery Fremaux [valery.fremaux@club-internet.fr] > 1.8
* @date 2008/03/31
* @license http://www.gnu.org/copyleft/gpl.html GNU Public License
*
* this is a format handler for getting text out of a proprietary binary format 
* so it can be indexed by Lucene search engine
*/

/**
* @param object $resource
* @uses $CFG
*/
function get_text_for_indexing_txt($physicalfilepath){
    global $CFG;

    $config = get_config('block_search');

    // SECURITY : do not allow non admin execute anything on system !!
    if (!has_capability('moodle/site:config', context_system::instance())) {
        return;
    }

    // just try to get text empirically from ppt binary flow
    $text = implode('', file($physicalfilepath));

    if (!empty($config->limit_index_body)) {
        $text = shorten_text($text, $config->limit_index_body);
    }
    return $text;
}
