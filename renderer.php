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
 * @author Michael Campanis (mchampan) [cynnical@gmail.com], Valery Fremaux [valery.fremaux@gmail.com] > 1.8
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
        global $OUTPUT;

        static $typestr;
        static $scorestr;
        static $authorstr;

        if (!isset($typestr)) {
            $typestr = get_string('type', 'local_search');
            $scorestr = get_string('score', 'local_search');
            $authorstr = get_string('author', 'local_search');
        }
        $template = new StdClass;
        $template->listingnumber = $listing->number + 1;
        $template->listingicon = $listing->icon;
        $template->listingtitle = $listing->title;
        $template->listingcourse = $listing->course;
        $template->listingdoctype = $listing->doctype;
        $template->listingscore = round($listing->score, 3);
        $template->listingauthor = $listing->author;
        $template->typestr = $typestr;
        $template->scorestr = $scorestr;
        $template->authorstr = $authorstr;
        $template->notauthorid = (!empty($listing->author) && !is_numeric($listing->author));
        $template->processedurl = str_replace('DEFAULT_POPUP_SETTINGS', DEFAULT_POPUP_SETTINGS, $listing->url);
        
        return $OUTPUT->render_from_template('local_search/search_result', $template);
    }

    public function simple_form($querystring) {
        global $OUTPUT;

        $template = new StdClass;
        $template->searchurl = new moodle_url('/local/search/query.php', array('a' => 1));
        $template->staturl = new moodle_url('/local/search/stats.php');
        $template->querystring = $querystring;

        return $OUTPUT->render_from_template('local_search/simple_form', $template);
    }

    public function advanced_form($adv, $moduletypes) {
        global $OUTPUT;
        
        $template = new StdClass;
        $template->mustappear = $adv->mustappear;
        $template->notappear = $adv->notappear;
        $template->canappear = $adv->canappear;
        $template->author = $adv->author;
        $template->title = $adv->title;
        $template->staturl = new moodle_url('/local/search/stats.php');
        $template->queryurl = new moodle_url('/local/search/query.php');
        foreach ($moduletypes as $mod) {
            if ($mod != 'all') {
                if ($mod != 'user' && $mod != 'course') {
                    $optionsmenu[$mod] = get_string('modulenameplural', $mod);
                } else if ($mod == 'course') {
                    $optionsmenu[$mod] = get_string('courses');
                } else {
                    $optionsmenu[$mod] = get_string('users');
                }
            } else {
                $optionsmenu[$mod] = get_string('all', 'local_search');
            }
        }
        $template->optionmenu = (html_writer::select($optionsmenu, 'module', $adv->module));
        
        return $OUTPUT->render_from_template('local_search/advanced_form', $template);
    }

    public function course_search_form($value) {
        $template = new StdClass;
        $params = [
            'a' => 1
        ];
        $template->coursesearchurl = new moodle_url('/local/search/query.php', $params);
        $template->querystring = $value;

        return $this->output->render_from_template('local_search/course_search_form', $template);
    }
}