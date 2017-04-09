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
 * @author Michael Campanis (mchampan) [cynnical@gmail.com], Valery Fremaux [valery.fremaux@club-internet.fr] > 1.8
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
namespace local_search;

use \StdClass;
use \context_module;
use \context_course;
use \moodle_url;

defined('MOODLE_INTERNAL') || die();

require_once($CFG->dirroot.'/local/search/documents/document.php');
require_once($CFG->dirroot.'/local/search/documents/document_wrapper.class.php');
require_once($CFG->dirroot.'/mod/lesson/lib.php');

/**
 * a class for representing searchable information
 *
 */
class LessonPageSearchDocument extends SearchDocument {

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

class lesson_document_wrapper extends document_wrapper {

    /**
     * constructs a valid link to a chat content
     * @param int $lessonid the lesson module
     * @param int $itemid the id of a single page
     * @return a well formed link to lesson page
     */
    public static function make_link($instanceid) {

        // Get an additional subentity id dynamically.
        $extravars = func_get_args();
        array_shift($extravars);
        $itemtype = array_shift($extravars);

        if ($itemtype == 'page') {

            $itemid = array_shift($extravars);
            return new moodle_url('/mod/lesson/view.php', array('id' => $instanceid, 'pageid' => $itemid));
        }
        return new moodle_url('/mod/lesson/view.php', array('id' => $instanceid));
    }

    /**
     * search standard API
     *
     */
    public static function get_iterator() {
        global $DB;

        if ($lessons = $DB->get_records('lesson')) {
            return $lessons;
        } else {
            return array();
        }
    }

    /**
     * search standard API
     * @param object $lesson a lesson instance (by ref)
     * @return an array of searchable documents
     */
    public static function get_content_for_index(&$instance) {
        global $DB;

        $documents = array();
        if (!$instance) {
            return $documents;
        }

        $pages = $DB->get_records('lesson_pages', array('lessonid' => $instance->id));
        if ($pages) {
            $coursemodule = $DB->get_field('modules', 'id', array('name' => 'lesson'));
            $params = array('course' => $instance->course, 'module' => $coursemodule, 'instance' => $instance->id);
            $cm = $DB->get_record('course_modules', $params);
            $context = context_module::instance($cm->id);
            foreach ($pages as $apage) {
                $arr = get_object_vars($apage);
                $documents[] = new LessonPageSearchDocument($arr, $cm->id, $instance->course, 'page', $context->id);
            }
        }

        return $documents;
    }

    /**
     * returns a single lesson search document based on a lesson page id
     * @param int $id an id for a single information item
     * @param string $itemtype the type of information
     */
    public static function single_document($id, $itemtype) {
        global $DB;

        // Only page is known yet.
        $page = $DB->get_record('lesson_pages', array('id' => $id));
        $lesson = $DB->get_record('lesson', array('id' => $page->lessonid));
        $coursemodule = $DB->get_field('modules', 'id', array('name' => 'lesson'));
        $params = array('course' => $lesson->course, 'module' => $coursemodule, 'instance' => $page->lessonid);
        $cm = $DB->get_record('course_modules', $params);
        if ($cm) {
            $context = context_module::instance($cm->id);
            $lesson->groupid = 0;
            $arr = get_object_vars($page);
            return new LessonPageSearchDocument($arr, $cm->id, $lesson->course, $itemtype, $context->id);
        }
        return null;
    }

    /**
     * returns the var names needed to build a sql query for addition/deletions
     *
     */
    public static function db_names() {
        /*
         * [primary id], [table name], [time created field name], [time modified field name] [itemtype]
         * [select for getting itemtype]
         */
        return array(array('id', 'lesson_pages', 'timecreated', 'timemodified', 'page'));
    }

    /**
     * this function handles the access policy to contents indexed as searchable documents. If this
     * function does not exist, the search engine assumes access is allowed.
     * When this point is reached, we already know that :
     * - user is legitimate in the surrounding context
     * - user may be guest and guest access is allowed to the module
     * - the function may perform local checks within the module information logic
     * @param string $path the access path to the module script code
     * @param string $itemtype the information subclassing (usefull for complex modules, defaults to 'standard')
     * @param int $thisid the item id within the information class denoted by itemtype. In lessons, this id
     * points out the individual page.
     * @param object $user the user record denoting the user who searches
     * @param int $groupid the current group used by the user when searching
     * @param int $contextid the id of the context used when indexing
     * @uses CFG, USER
     * @return true if access is allowed, false elsewhere
     */
    public static function check_text_access($path, $itemtype, $thisid, $user, $groupid, $contextid) {
        global $CFG, $USER, $DB;

        include_once("{$CFG->dirroot}/{$path}/lib.php");

        // Get the lesson page.
        $page = $DB->get_record('lesson_pages', array('id' => $thisid));
        $lesson = $DB->get_record('lesson', array('id' => $page->lessonid));
        $context = $DB->get_record('context', array('id' => $contextid));
        $cm = $DB->get_record('course_modules', array('id' => $context->instanceid));
        if (empty($cm)) {
            return false; // Shirai 20093005 - MDL19342 - course module might have been delete.
        }

        if (!$cm->visible and !has_capability('moodle/course:viewhiddenactivities', $context)) {
            if (!empty($CFG->search_access_debug)) {
                echo "search reject : hidden lesson ";
            }
            return false;
        }

        $lessonsuperuser = has_capability('mod/lesson:edit', $context) || has_capability('mod/lesson:manage', $context);
        // Approval check : entries should be approved for being viewed, or belongs to the user.
        if (time() < $lesson->available && !$lessonsuperuser) {
            if (!empty($CFG->search_access_debug)) {
                echo "search reject : lesson is not available ";
            }
            return false;
        }

        if ($lesson->usepassword && !$lessonsuperuser) {
            if (!empty($CFG->search_access_debug)) {
                echo "search reject : password required, cannot output in searches ";
            }
            return false;
        }

        // The user have it seen yet ? did he tried one time at least.
        $params = array('lessonid' => $lesson->id, 'pageid' => $page->id, 'userid' => $USER->id);
        $attempt = $DB->get_record('lesson_attempts', $params);
        if (!$attempt && !$lessonsuperuser) {
            if (!empty($CFG->search_access_debug)) {
                echo "search reject : never tried this lesson ";
            }
            return false;
        }

        if ($attempt && !$attempt->correct && !$lessonsuperuser && !$lesson->retake) {
            if (!empty($CFG->search_access_debug)) {
                echo "search reject : one try only, still not good ";
            }
            return false;
        }

        return true;
    }

}