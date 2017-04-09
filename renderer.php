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
 * @author Michael Campanis (mchampan) [cynnical@gmail.com], Valery Fremaux [valery.fremaux@club-internet.fr] > 1.8
 * @contributor Tatsuva Shirai 20090530
 * @license http://www.gnu.org/copyleft/gpl.html GNU Public License
 *
 * document handling for lesson activity module
 * This file contains the mapping between a lesson page and it's indexable counterpart,
 *
 * Functions for iterating and retrieving the necessary records are now also included
 * in this file, rather than mod/lesson/lib.php
 */
defined('MOODLE_INTERNAL') || die();

class local_search_renderer extends plugin_renderer_base {

    public function search_result($listing) {
        static $typestr;
        static $scorestr;
        static $authorstr;

        if (!isset($typestr)) {
            $typestr = get_string('type', 'local_search');
            $scorestr = get_string('score', 'local_search');
            $authorstr = get_string('author', 'local_search');
        }

        $str = '';

        $str .= '<li class="search-result" value="'.($listing->number + 1).'">';
        $processedurl = str_replace('DEFAULT_POPUP_SETTINGS', DEFAULT_POPUP_SETTINGS, $listing->url);
        $str .= '<a href="'.$processedurl.'">'.$listing->icon.' '.$listing->title.'</a> '.$listing->course;
        $str .= '<div class="search-result-attributes">';
        $str .= $typestr.': '.$listing->doctype.', '.$scorestr.': '.round($listing->score, 3);
        $str .= '</div>';
        if (!empty($listing->author) && !is_numeric($listing->author)) {
            $str .= '<div class="search-result-attributes">';
            $str .= $authorstr.': '.$listing->author;
            $str .= '</div>';
        }
        $str .= '</li>';

        return $str;
    }

    public function simple_form($querystring) {
        $str = '';

        $str .= '<input type="text" name="query_string" length="50" value="'.$querystring.'" />';
        $str .= '&nbsp;<input type="submit" value="'.get_string('search', 'local_search').'" /> &nbsp;';
        $url = new moodle_url('/local/search/query.php', array('a' => 1));
        $str .= '<a href="'.$url.'">'.get_string('advancedsearch', 'local_search').'</a> |';
        $url = new moodle_url('/local/search/stats.php');
        $str .= '<a href="'.$url.'">'.get_string('statistics', 'local_search').'</a>';

        return $str;
    }
}