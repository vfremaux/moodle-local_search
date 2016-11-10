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
 * @subpackage document_wrappers
 * @author Valery Fremaux [valery.fremaux@club-internet.fr] > 1.8
 * @date 2008/03/31
 * @license http://www.gnu.org/copyleft/gpl.html GNU Public License
 *
 * this is a format handler for getting text out of a proprietary binary format 
 * so it can be indexed by Lucene search engine
 */

/**
 * @param string $physicalfilepath
 * @return some raw text for indexation
 */
function get_text_for_indexing_pdf($physicalfilepath) {
    global $CFG;

    $config = get_config('local_search');

    // Adds moodle root switch if none was defined.
    if (!isset($config->usemoodleroot)) {
        set_config('usemoodleroot', 1, 'local_search');
        $config->usemoodleroot = 1;
    }

    $moodleroot = ($config->usemoodleroot) ? "{$CFG->dirroot}/local/search/" : '';

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