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
 * @author Valery Fremaux [valery.fremaux@gmail.com] > 1.8
 * @contributor Tatsuva Shirai 20090530
 * @date 2008/03/31
 * @license http://www.gnu.org/copyleft/gpl.html GNU Public License
 *
 * document handling for chat activity module
 * This file contains the mapping between a chat history and it's indexable counterpart,
 *
 * Functions for iterating and retrieving the necessary records are now also included
 * in this file, rather than mod/chat/lib.php
 *
 */
namespace local_search\document;

use StdClass;
use local_search\document;

/**
 * a class for representing searchable information for searching courses
 */
class course_document extends document {

    /**
     * constructor
     * @param object $courseobj
     * @param int $courseid
     * @param int $unusedmodid
     * @param int $unusedcourseid
     * @param int $unusedgroupid
     * @param $contextid
     * @SuppressWarnings(PHPMD.UnusedFormalParameter)
     */
    public function __construct($courseobj, $courseid, $unusedmodid, $unusedcourseid, $unusedgroupid, $contextid) {
        // Generic information; required.
        $doc = new StdClass;
        $doc->docid = $courseid;
        $doc->documenttype = SEARCH_TYPE_COURSE;
        $doc->itemtype = 'coursedata';
        $doc->contextid = $contextid;

        // We cannot call userdate with relevant locale at indexing time.
        $doc->title = $courseobj->fullname;
        $doc->date = $courseobj->timecreated;
        $doc->author = '';

        if (!empty($courseobj->teachers)) {
            foreach ($courseobj->teachers as $t) {
                $teachersstrs[] = $t->firstname.' '.$t->lastname;
            }
            $doc->author = implode(', ', $teachersstrs);
        }

        // Remove '(ip.ip.ip.ip)' from chat author list.
        $doc->contents = $courseobj->summary;
        $doc->url = course_document_wrapper::make_link($courseid);

        // course specific information; optional.
        $data = new StdClass();
        $data->graders = '';
        if (!empty($courseobj->graders)) {
            foreach ($courseobj->graders as $g) {
                $gradersstrs[] = $g->firstname.' '.$g->lastname;
            }
            $data->graders = implode(', ', $gradersstrs);
        }
        $data->course = $courseid;
        $data->start = $courseobj->startdate;
        $data->end = $courseobj->enddate;

        // Construct the parent class.
        parent::__construct($doc, $data, $courseid, 0, 0, SEARCH_TYPE_COURSE);
    }
}
