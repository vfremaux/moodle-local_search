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
 * @subpackage document_wrappers
 * @author Valery Fremaux [valery.fremaux@club-internet.fr] > 1.8
 * @date 2008/03/31
 * @license http://www.gnu.org/copyleft/gpl.html GNU Public License
 *
 * this is a format handler for getting text out of a proprietary binary format 
 * so it can be indexed by Lucene search engine
 */
defined('MOODLE_INTERNAL') || die();

/**
 * converts content to text
 * @param string $physicalfilepath
 * @return some raw text for indexation
 */
function get_text_for_indexing_htm($physicalfilepath) {
    global $CFG;

    // Just get text.
    $text = implode('', file($physicalfilepath));

    // Extract keywords and other interesting meta information and put it back as real content for indexing.
    if (preg_match('/(.*)<meta ([^>]*)>(.*)/is', $text, $matches)) {
        $prefix = $matches[1];
        $meta_attributes = $matches[2];
        $suffix = $matches[3];
        if (preg_match('/name="(keywords|description)"/i', $meta_attributes)) {
            preg_match('/content="([^"]+)"/i', $meta_attributes, $matches);
            $text = $prefix.' '.$matches[1].' '.$suffix;
        }
    }

    // Brutally filters all html tags.
    $text = preg_replace("/<[^>]*>/", '', $text);
    $text = preg_replace("/<!--[^>]*-->/", '', $text);
    $text = html_entity_decode($text, ENT_COMPAT, 'UTF-8');
    $text = mb_convert_encoding($text, 'UTF-8', 'auto');
    
    if (!empty($config->limit_index_body)) {
        $text = shorten_text($text, $config->limit_index_body);
    }
    return $text;
}
