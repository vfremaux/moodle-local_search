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

<<<<<<< HEAD
    public function advanced_form($adv) {
=======
    public function advanced_form($adv, $moduletypes) {
>>>>>>> MOODLE_34_STABLE
        $str = '';

        $str .= '<input type="hidden" name="a" value="1"/>';

        $str .= '<table border="0" cellpadding="3" cellspacing="3">';

        $str .= '<tr>';
        $str .= '<td width="240">'.get_string('thesewordsmustappear', 'local_search').':</td>';
        $str .= '<td><input type="text" name="mustappear" length="50" value="'.$adv->mustappear.'" /></td>';
        $str .= '</tr>';

        $str .= '<tr>';
        $str .= '  <td>'.get_string('thesewordsmustnotappear', 'local_search').':</td>';
        $str .= '  <td><input type="text" name="notappear" length="50" value="'.$adv->notappear.'" /></td>';
        $str .= '</tr>';

        $str .= '<tr>';
        $str .= '  <td>'.get_string('thesewordshelpimproverank', 'local_search').':</td>';
        $str .= '  <td><input type="text" name="canappear" length="50" value="'.$adv->canappear.'" /></td>';
        $str .= '</tr>';

        $str .= '<tr>';
        $str .= '  <td>'.get_string('whichmodulestosearch', 'local_search').':</td>';
        $str .= '  <td>';
<<<<<<< HEAD
        foreach ($moduletypes as $mod) {
            if ($mod != 'all') {
                $optionsmenu[$mod] = get_string('modulenameplural', $mod);
=======
        $optionsmenu = array();
        foreach ($moduletypes as $mod) {
            if ($mod != 'all') {
                if ($mod != 'user') {
                    $optionsmenu[$mod] = get_string('modulenameplural', $mod);
                } else {
                    $optionsmenu[$mod] = get_string('users');
                }
>>>>>>> MOODLE_34_STABLE
            } else {
                $optionsmenu[$mod] = get_string('all', 'local_search');
            }
        }
        $str .= html_writer::select($optionsmenu, 'module', $adv->module);
        $str .= '  </td>';
        $str .= '</tr>';

        $str .= '<tr>';
        $str .= '  <td>'.get_string('wordsintitle', 'local_search').':</td>';
        $str .= '  <td><input type="text" name="title" length="50" value="'.$adv->title.'" /></td>';
        $str .= '</tr>';

        $str .= '<tr>';
        $str .= '  <td>'.get_string('authorname', 'local_search').':</td>';
        $str .= '  <td><input type="text" name="author" length="50" value="'.$adv->author.'" /></td>';
        $str .= '</tr>';

        $str .= '<tr>';
        $str .= '  <td colspan="3" align="center"><br />';
        $str .= '<input type="submit" value="'.get_string('search', 'local_search').'" />';
        $str .= '</td>';
        $str .= '</tr>';

        $str .= '<tr>';
        $str .= '<td colspan="3" align="center">';
        $str .= '<table border="0" cellpadding="0" cellspacing="0">';
        $str .= '<tr>';
        $qurl = new moodle_url('/local/search/query.php');
        $str .= '<td><a href="'.$qurl.'">'.get_string('normalsearch', 'local_search').'</a> |</td>';
        $surl = new moodle_url('/local/search/stats.php');
        $str .= '<td>&nbsp;<a href="'.$surl.'">'.get_string('statistics', 'local_search').'</a></td>';
        $str .= '</tr>';
        $str .= '</table>';
        $str .= '  </td>';
        $str .= '</tr>';
        $str .= '</table>';

        return $str;
    }
}