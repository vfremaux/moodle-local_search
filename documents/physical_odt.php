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

defined('MOODLE_INTERNAL') || die();

/**
 * Global Search Engine for Moodle
 *
 * @package local_search
 * @category local
 * @subpackage document_wrappers
 * @author Valery Fremaux [valery.fremaux@club-internet.fr] > 1.8
 * @contributor Tatsuva Shirai 20090530
 * @date 2008/03/31
 * @license http://www.gnu.org/copyleft/gpl.html GNU Public License
 *
 * this is a format handler for getting text out of the opensource ODT binary format 
 * so it can be indexed by Lucene search engine
 */

/**
 * OpenOffice Odt extractor
 * @param object $physicalfilepath
 * @return some raw text for indexation
 */
function get_text_for_indexing_odt($physicalfilepath){
    global $CFG;

    $config = get_config('local_search');

    // Adds moodle root switch if none was defined.
    if (!isset($config->usemoodleroot)) {
        set_config('usemoodleroot', 1, 'local_search');
        $config->usemoodleroot = 1;
    }

    $moodleroot = ($config->usemoodleroot) ? "{$CFG->dirroot}/local/search/" : '' ;

    // Just call pdftotext over stdout and capture the output.
    if (!empty($config->odt_to_text_cmd)) {
        // We need to remove any line command options...
        preg_match("/^\S+/", $config->odt_to_text_cmd, $matches);
        if (!file_exists("{$moodleroot}{$matches[0]}")) {
            mtrace('Error with OpenOffice ODT to text converter command : executable not found at '.$moodleroot.$CFG->block_search_odt_to_text_cmd);
        } else {
            $file = escapeshellarg($physicalfilepath);
            $command = trim($config->odt_to_text_cmd);
            $text_converter_cmd = "{$moodleroot}{$command} --encoding=UTF-8 $file";
            mtrace("Executing : $text_converter_cmd");
            $result = shell_exec($text_converter_cmd);
            if ($result) {
                if (!empty($config->limit_index_body)) {
                    $result = shorten_text($result, $config->limit_index_body);
                }
                return $result;
            } else {
                mtrace('Error with OpenOffice ODT to text converter command : execution failed. ');
                return '';
            }
        }
    } else {
        mtrace('Error with OpenOffice ODT to text converter command : command not set up. Execute once search block configuration.');
        return '';
    }
}
