<?php
/**
* Global Search Engine for Moodle
*
* @package local_search
* @subpackage document_wrappers
* @author Valery Fremaux [valery.fremaux@club-internet.fr] > 1.8
* @contributor Tatsuva Shirai 20090530
* @date 2008/03/31
* @license http://www.gnu.org/copyleft/gpl.html GNU Public License
*
* this is a format handler for getting text out of a proprietary binary format 
* so it can be indexed by Lucene search engine
*/

/**
* MS Word extractor
* @param object $resource 
* @uses $CFG
*/
function get_text_for_indexing_doc($physicalfilepath) {
    global $CFG;

    $config = get_config('block_search');

    // SECURITY : do not allow non admin execute anything on system !!
    if (!has_capability('moodle/site:config', context_system::instance())) {
        return;
    }

    $moodleroot = (@$config->usemoodleroot) ? "{$CFG->dirroot}/" : '';

    // just call antiword over stdout and capture the output
    if (!empty($config->word_to_text_cmd)){
        // we need to remove any line command options...
        preg_match("/^\S+/", $config->word_to_text_cmd, $matches);
        if (!file_exists("{$moodleroot}{$matches[0]}")){
            mtrace('Error with MSWord to text converter command : executable not found at '.$moodleroot.$config->word_to_text_cmd);
        } else {

            $command = trim($config->word_to_text_cmd);
            $text_converter_cmd = "{$moodleroot}{$command} -m UTF-8.txt $physicalfilepath";
            if ($config->word_to_text_env){
                putenv($config->word_to_text_env);
            }
            mtrace("Executing : $text_converter_cmd");
            $result = shell_exec($text_converter_cmd);
            if ($result) {
                if (!empty($config->limit_index_body)) {
                    $result = shorten_text($result, $config->limit_index_body);
                }
                return mb_convert_encoding($result, 'UTF-8', 'auto');
            } else {
                mtrace('Error with MSWord to text converter command : execution failed. ');
                return '';
            }
        }
    } else {
        mtrace('Error with MSWord to text converter command : command not set up. Execute once search block configuration.');
        return '';
    }
}
