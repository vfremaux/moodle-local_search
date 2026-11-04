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

use context_course;
use moodle_url;
use stdClass;
use local_search\document_wrapper;

/**
 * Document wrapper implementation
 */
class course_document_wrapper extends document_wrapper {
    /**
     * constructs a valid link to the course
     * @param int $instanceid the instance id
     * @param int $contextid
     * @return a well formed link to session display
     */
    public static function make_link($instanceid, $contextid = null) {
        return new moodle_url('/course/view.php', ['id' => $instanceid]);
    }

    /**
     * part of search engine API
     */
    public static function get_iterator() {
        global $DB;

        $courses = $DB->get_records('course');
        return $courses;
    }

    /**
     * part of search engine API
     * @param object $instance
     */
    public static function get_content_for_index(&$instance) {
        global $DB;

        $documents = [];
        $course = $DB->get_record('course', ['id' => $instance->id]);
        $context = context_course::instance($course->id);

        $course->teachers = get_users_by_capability($context, 'moodle/course:manageactivities', 'u.id,u.firstname,u.lastname');
        if (!empty($teachers)) {
            $teacherkeystoexclude = array_keys($teachers);
        } else {
            $teacherkeystoexclude = [];
        }
        $course->graders = get_users_by_capability($context, 'moodle/course:grade', 'u.id,u.firstname,u.lastname', '', '', '', '', $teacherkeystoexclude);

        $documents[] = new course_document($course, $course->id, 0, $course, 0, $context->id);
        return $documents;
    }

    /**
     * returns a single data search document based on a course id
     * @param int $id the course id
     * @param string $itemtype the type of information (course is the only type)
     */
    public static function single_document($id, $itemtype) {
        global $DB;

        $course = $DB->get_record('course', ['id' => $id]);
        $context = context_course::instance($course->id);

        $course->teachers = get_users_by_capability($context, 'moodle/course:manageactivities', 'u.id,u.firstname,u.lastname');
        if (!empty($teachers)) {
            $teacherkeystoexclude = array_keys($teachers);
        } else {
            $teacherkeystoexclude = [];
        }
        $course->graders = get_users_by_capability($context, 'moodle/course:grade', 'u.id,u.firstname,u.lastname', '', '', '', '', $teacherkeystoexclude);

        $document = new course_document($course, $course->id, 0, $course, 0, $context->id);
        return $document;
    }

    /**
     * returns the var names needed to build a sql query for addition/deletions
     * // TODO chat indexable records are virtual. Should proceed in a special way
     * [primary id], [table name], [time created field name], [time modified field name]
     */
    public static function db_names() {
        return [
            ['id', 'course', 'timecreated', 'timemodified', 'coursedata'],
        ];
    }

    /**
     * this function handles the access policy to contents indexed as searchable documents. If this
     * function does not exist, the search engine assumes access is allowed.
     * When this point is reached, we already know that :
     * - user is legitimate in the surrounding context
     * - user may be guest and guest access is allowed to the module
     * - the function may perform local checks within the module information logic
     * @param stirng $path the access path to the module script code
     * @param string $itemtype the information subclassing (usefull for complex modules, defaults to 'standard')
     * @param int $thisid the item id within the information class denoted by entry_type. In chats, this id
     * points out a session history which is a close sequence of messages.
     * @param object $user the user record denoting the user who searches
     * @param int $groupid the current group used by the user when searching
     * @param int $contextid
     * @return true if access is allowed, false elsewhere
     */
    public static function check_text_access($path, $itemtype, $thisid, $user, $groupid, $contextid) {
        global $CFG, $DB;

        include_once("{$CFG->dirroot}/{$path}/lib.php");
        $config = get_config('local_search');

        // Get the chat session and all related stuff.
        $course = $DB->get_record('course', ['id' => $thisid]);
        $coursecat = $DB->get_record('course_categories', ['id' => $course->category]);
        $context = $DB->get_record('context', ['id' => $contextid]);

        if (!$course->visible && !has_capability('moodle/course:viewhiddencourses', $context)) {
            if (!empty($config->access_debug)) {
                echo "search reject : hidden course ";
            }
            return false;
        }

        return true;
    }
}
