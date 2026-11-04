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
 * @author Michael Campanis (mchampan) [cynnical@gmail.com], Valery Fremaux [valery.fremaux@gmail.com] > 1.8
 * @contributor Tatsuva Shirai 20090530
 * @date 2008/03/31
 * @license http://www.gnu.org/copyleft/gpl.html GNU Public License
 *
 * document handling for lesson activity module
 * This file contains the mapping between a lesson page and it's indexable counterpart,
 *
 * Functions for iterating and retrieving the necessary records are now also included
 * in this file, rather than mod/lesson/lib.php
 */
namespace local_search\document;

use context_course;
use context_module;
use local_search\document;
use moodle_url;
use StdClass;

defined('MOODLE_INTERNAL') || die();

require_once($CFG->dirroot.'/mod/lesson/lib.php');

/**
 * a class for representing searchable information
 *
 */
class lesson_document extends document {

    /**
     * constructor
     *
     */
    public function __construct(&$page, $lessonmoduleid, $courseid, $itemtype, $contextid) {

        // Generic information.
        $doc = new StdClass;
        $doc->docid        = $page['id'];
        $doc->documenttype = SEARCH_TYPE_LESSON;
        $doc->itemtype     = $itemtype;
        $doc->contextid    = $contextid;

        $doc->title        = $page['title'];

        $doc->author       = '';
        $doc->contents     = $page['contents'];
        $doc->date         = $page['timecreated'];
        $doc->url          = lesson_document_wrapper::make_link($lessonmoduleid, $itemtype, $page['id']);

        // Module specific information.
        $data = new StdClass;
        $data->lesson      = $page['lessonid'];

        parent::__construct($doc, $data, $courseid, 0, 0, 'mod/'.SEARCH_TYPE_LESSON);
    }
}
