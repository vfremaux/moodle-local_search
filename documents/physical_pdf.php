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
function get_text_for_indexing_pdf($physicalfilepath) {
    global $CFG;

    $config = get_config('block_search');

    // SECURITY : do not allow non admin execute anything on system !!
    if (!has_capability('moodle/site:config', context_system::instance())) {
        return;
    }

    // adds moodle root switch if none was defined
    if (!isset($config->usemoodleroot)) {
        set_config('usemoodleroot', 1, 'block_search');
    }

    $moodleroot = ($config->usemoodleroot) ? "{$CFG->dirroot}/" : '';

    // just call pdftotext over stdout and capture the output
    if (!empty($config->pdf_to_text_cmd)) {
        // we need to remove any line command options...
        preg_match("/^\S+/", $config->pdf_to_text_cmd, $matches);
        if (!file_exists("{$moodleroot}{$matches[0]}")) {
            mtrace('Error with pdf to text converter command : executable not found at '.$moodleroot.$matches[0]);
        } else {
            $file = escapeshellarg($physicalfilepath);
            $command = trim($config->pdf_to_text_cmd);
            $text_converter_cmd = "{$moodleroot}{$command} $file -";
            $result = shell_exec($text_converter_cmd);
            if ($result) {
                if (!empty($config->limit_index_body)) {
                    $result = shorten_text($result, $config->limit_index_body);
                }
                return $result;
            } else {
                mtrace('Error with pdf to text converter command : execution failed for '.$text_converter_cmd.'. Check for execution permission on pdf converter executable.');
                return '';
            }
        }
    } else {
        mtrace('Error with pdf to text converter command : command not set up. Execute once search block configuration.');
        return '';
    }
}