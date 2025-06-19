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
namespace local_search;

use \StdClass;
use \context_module;
use \context_course;
use \moodle_url;

defined('MOODLE_INTERNAL') || die();

require_once($CFG->dirroot.'/local/search/documents/document.php');
require_once($CFG->dirroot.'/local/search/documents/document_wrapper.class.php');
require_once($CFG->dirroot.'/mod/chat/lib.php');

/**
 * a class for representing searchable information for searching courses
 *
 */
class CourseSearchDocument extends SearchDocument {

    /**
     * constructor
     */
    public function __construct(&$courseobj, $courseid, $unusedmodid, $unusedcourseid, $unusedgroupid, $contextid) {

        // Generic information; required.
        $doc = new StdClass;
        $doc->docid         = $courseid;
        $doc->documenttype  = SEARCH_TYPE_COURSE;
        $doc->itemtype      = 'coursedata';
        $doc->contextid     = $contextid;

        // We cannot call userdate with relevant locale at indexing time.
        $doc->title         = $courseobj->fullname;
        $doc->date          = $courseobj->timecreated;
        $doc->author        = '';

        if (!empty($courseobj->teachers)) {
            foreach ($courseobj->teachers as $t) {
                $teachersstrs[] = $t->firstname.' '.$t->lastname;
            }
            $doc->author = implode(', ', $teachersstrs);
        }

        // Remove '(ip.ip.ip.ip)' from chat author list.
        $doc->contents      = $courseobj->summary;
        $doc->url           = course_document_wrapper::make_link($courseid);

        // course specific information; optional.
        $data = new StdClass;
        $data->graders      = '';
        if (!empty($courseobj->graders)) {
            foreach ($courseobj->graders as $g) {
                $gradersstrs[] = $g->firstname.' '.$g->lastname;
            }
            $data->graders = implode(', ', $gradersstrs);
        }
        $data->course        = $courseid;
        $data->start         = $courseobj->startdate;
        $data->end           = $courseobj->enddate;

        // Construct the parent class.
        parent::__construct($doc, $data, $courseid, 0, 0, SEARCH_TYPE_COURSE);
    }
}

class course_document_wrapper extends document_wrapper {

    /**
     * constructs a valid link to a chat content
     * @param cm_id the chat course module
     * @param start the start time of the session
     * @param end th end time of the session
     * @return a well formed link to session display
     */
    public static function make_link($instanceid, $contextid = null) {
        return new moodle_url('/course/view.php', array('id' => $instanceid));
    }

    /**
     * part of search engine API
     *
     */
    public static function get_iterator() {
        global $DB;

        $courses = $DB->get_records('course');
        return $courses;
    }

    /**
     * part of search engine API
     *
     */
    public static function get_content_for_index(&$instance) {
        global $DB;

        $documents = array();
        $course = $DB->get_record('course', array('id' => $instance->id));
        $context = context_course::instance($course->id);

        $course->teachers = get_users_by_capability($context, 'moodle/course:manageactivities', 'u.id,u.firstname,u.lastname');
        if (!empty($teachers)) {
            $teacherkeystoexclude = array_keys($teachers);
        } else {
            $teacherkeystoexclude = [];
        }
        $course->graders = get_users_by_capability($context, 'moodle/course:grade', 'u.id,u.firstname,u.lastname', '', '', '', '', $teacherkeystoexclude);

        $documents[] = new CourseSearchDocument($course, $course->id, 0, $course, 0, $context->id);
        return $documents;
    }

    /**
     * returns a single data search document based on a course id
     * @param id the course id
     * @param itemtype the type of information (course is the only type)
     */
    public static function single_document($id, $itemtype) {
        global $DB;

        $course = $DB->get_record('course', array('id' => $id));
        $context = context_course::instance($course->id);

        $course->teachers = get_users_by_capability($context, 'moodle/course:manageactivities', 'u.id,u.firstname,u.lastname');
        if (!empty($teachers)) {
            $teacherkeystoexclude = array_keys($teachers);
        } else {
            $teacherkeystoexclude = [];
        }
        $course->graders = get_users_by_capability($context, 'moodle/course:grade', 'u.id,u.firstname,u.lastname', '', '', '', '', $teacherkeystoexclude);

        $document = new CourseSearchDocument($course, $course->id, 0, $course, 0, $context->id);
        return $document;
    }

    /**
     * returns the var names needed to build a sql query for addition/deletions
     * // TODO chat indexable records are virtual. Should proceed in a special way
     * [primary id], [table name], [time created field name], [time modified field name]
     */
    public static function db_names() {
        return array(
            array('id', 'course', 'timecreated', 'timemodified', 'coursedata'),
        );
    }

    /**
     * this function handles the access policy to contents indexed as searchable documents. If this
     * function does not exist, the search engine assumes access is allowed.
     * When this point is reached, we already know that :
     * - user is legitimate in the surrounding context
     * - user may be guest and guest access is allowed to the module
     * - the function may perform local checks within the module information logic
     * @param path the access path to the module script code
     * @param itemtype the information subclassing (usefull for complex modules, defaults to 'standard')
     * @param this_id the item id within the information class denoted by entry_type. In chats, this id
     * points out a session history which is a close sequence of messages.
     * @param user the user record denoting the user who searches
     * @param group_id the current group used by the user when searching
     * @uses CFG
     * @return true if access is allowed, false elsewhere
     */
    public static function check_text_access($path, $itemtype, $thisid, $user, $groupid, $contextid) {
        global $CFG, $DB;

        include_once("{$CFG->dirroot}/{$path}/lib.php");
        $config = get_config('local_search');

        // Get the chat session and all related stuff.
        $course = $DB->get_record('course', array('id' => $thisid));
        $coursecat = $DB->get_record('course_categories', array('id' => $course->category));
        $context = $DB->get_record('context', array('id' => $contextid));

        if (!$course->visible && !has_capability('moodle/course:viewhiddencourses', $context)) {
            if (!empty($config->access_debug)) {
                echo "search reject : hidden course ";
            }
            return false;
        }

        return true;
    }
}