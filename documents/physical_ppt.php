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
 * @subpackage document_wrappers
 * @author Valery Fremaux [valery.fremaux@club-internet.fr] > 1.8
 * @contributor Tatsuva Shirai 20090530
 * @date 2008/03/31
 * @license http://www.gnu.org/copyleft/gpl.html GNU Public License
 *
 * this is a format handler for getting text out of a proprietary binary format
 * so it can be indexed by Lucene search engine
 * first implementation is a trivial heuristic based on ppt character stream :
 * text sequence always starts with a 00 9F 0F 04 sequence followed by a 15 bytes
 * sequence
 * In this sequence is a A8 0F or A0 0F or AA 0F followed by a little-indian encoding of text buffer size
 *  A8 0F denotes for ASCII text (local system monobyte encoding)
 *  A0 0F denotes for UTF-16 encoding
 *  AA 0F are non textual sequences
 * texts are either in ASCII or UTF-16
 * text ends on a new sequence start, or on a 00 00 NULL UTF-16 end of stream
 *
 * based on these following rules, here is a little empiric texte extractor for PPT
 */
defined('MOODLE_INTERNAL') || die();

/**
 * @param string $physicalfilepath a physical path
 * @return some raw text for indexation
 */
function get_text_for_indexing_ppt($physicalfilepath) {

    $indextext = null;

    $config = get_config('local_search');

    $text = implode('', file($physicalfilepath));

    $remains = $text;
    $fragments = array();
    while (preg_match('/\x00\x9F\x0F\x04.{9}(......)(.*)/s', $remains, $matches)) {
        $unpacked = unpack("ncode/Llength", $matches[1]);
        $sequencecode = $unpacked['code'];
        $length = $unpacked['length'];
        $followup = $matches[2];
        // Local system encoding sequence.
        if ($sequencecode == 0xA80F) {
            $afragment = substr($followup, 0, $length);
            $remains = substr($followup, $length);
            $fragments[] = $afragment;
        } else if ($sequencecode == 0xA00F) {
            // Denotes unicode encoded sequence.
            $afragment = substr($followup, 0, $length);
            $afragment = preg_replace('/\xA0\x00\x19\x20/s', "'", $afragment); // Some quotes.
            $afragment = preg_replace('/\x00/s', "", $afragment);
            $remains = substr($followup, $length);
            $fragments[] = $afragment;
        } else {
            $remains = $followup;
        }
    }
    $indextext = implode(' ', $fragments);
    $indextext = preg_replace('/\x19\x20/', "'", $indextext); // Some quotes.
    $indextext = preg_replace('/\x09/', '', $indextext); // Some extra chars.
    $indextext = preg_replace('/\x0D/', "\n", $indextext); // Some quotes.
    $indextext = preg_replace('/\x0A/', "\n", $indextext); // Some quotes.
    $indextextprint = implode('<hr/>', $fragments);

    if (!empty($config->limit_index_body)) {
        $indextext = shorten_text($text, $config->limit_index_body);
    }

    $indextext = mb_convert_encoding($indextext, 'UTF-8', 'auto'); // Shirai 20090530 - MDL19342.
    return $indextext;
}