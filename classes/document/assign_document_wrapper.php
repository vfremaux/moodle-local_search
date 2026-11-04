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
 * @author Valery Fremaux [valery.fremaux@gmail.com] > 1.8
 * @contributor Tatsuva Shirai 20090530
 * @license http://www.gnu.org/copyleft/gpl.html GNU Public License
 *
 * document handling for assignment activity module
 */
namespace local_search\document;

use local_search\document_wrapper;
use moodle_url;
use context_module;

/**
 * the document wrapper
 */
class assign_document_wrapper extends document_wrapper {
    /** @var string $modname */
    protected static $modname = 'assign';

    /**
     * constructs a valid link to an assign content
     * @param int instanceid this course module
     * @param contextid usually a context id, if link depends on context. Here used to transmit itemtype.
     * @return a well formed link to this search context display
     */
    public static function make_link($instanceid, $contextid = null) {

        $itemtype = $contextid;

        if ($itemtype == 'description') {
            return new moodle_url('/mod/assign/view.php', ['id' => $instanceid]);
        }
        if ($itemtype == 'submission') {
            return new moodle_url('/mod/assign/view.php', ['id' => $instanceid]);
        }
    }

    /**
     * part of search engine API
     * @return an array of instances
     */
    public static function get_iterator() {
        global $DB;

        if ($assignments = $DB->get_records('assign')) {
            return $assignments;
        } else {
            return [];
        }
    }

    /**
     * part of search engine API
     *
     */
    public static function get_content_for_index(&$instance) {
        global $CFG, $DB;

        $documents = [];
        $fs = get_file_storage();

        $coursemodule = $DB->get_field('modules', 'id', ['name' => 'assignment']);
        $params = ['course' => $instance->course, 'module' => $coursemodule, 'instance' => $instance->id];
        $cm = $DB->get_record('course_modules', $params);

        if ($cm) {
            $context = context_module::instance($cm->id);

            $instance->authors = '';
            $instance->date = $instance->timemodified;
            $arr = get_object_vars($instance);
            $documents[] = new assign_document($arr, $cm->id, 'description', $instance->course, null, $context->id);

            $submissions = assignment_get_all_submissions($instance);
            if ($submissions) {
                foreach ($submissions as $submission) {
                    $owner = $DB->get_record('user', ['id' => $submission->userid]);
                    $submission->authors = fullname($owner);
                    $submission->date = $submission->timemodified;
                    $submission->name = "submission:";
                    $submission->cmid = $cm->id;
                }
            }
            mtrace("finished assign {$instance->name}");
            return $documents;
        }
        return [];
    }

    /**
     * returns a single data search document based on an assignment
     * @param string $id the id of the searchable item
     * @param string $itemtype the type of information
     */
    public static function single_document($id, $itemtype) {
        global $DB;

        if ($itemtype == 'description') {
            if (!$assignment = $DB->get_record('assign', ['id' => $id])) {
                return null;
            }
        } else if ($itemtype == 'submission') {
            if ($submission = $DB->get_record('assign_submission', ['id' => $id])) {
                if (!$assignment = $DB->get_record('assign', ['id' => $submission->assignment])) {
                    return null;
                }
            } else {
                return null;
            }
        }
        $course = $DB->get_record('course', ['id' => $assignment->course]);
        $coursemodule = $DB->get_field('modules', 'id', ['name' => 'assignment']);
        $params = ['course' => $course->id, 'module' => $coursemodule, 'instance' => $assignment->id];
        $cm = $DB->get_record('course_modules', $params);

        if ($cm) {
            $context = context_module::instance($cm->id);

            // Should be only one.
            if ($itemtype == 'description') {
                $arr = get_object_vars($assignment);
                $document = new assign_document($arr, $cm->id, 'description', $assignment->course, null, $context->id);
                return $document;
            }
            if ($itemtype == 'submission') {
                $file = $fs->get_file_by_id($id);
                $void = [];
                $submission->courseid = $assignment->course;
                return search_get_physical_file(
                    $void,
                    $file,
                    $submission,
                    $context->id,
                    'assign_submission_document',
                    true
                );
            }
        }
        return null;
    }

    /**
     * returns the var names needed to build a sql query for addition/deletions
     * // TODO chat indexable records are virtual. Should proceed in a special way
     * // [primary id], [table name], [time created field name], [time modified field name], [itemtype]
     */
    public static function db_names() {
        return [
            ['id', 'assign', 'timemodified', 'timemodified', 'description'],
            ['id', 'assign_submission', 'timecreated', 'timemodified', 'submission'],
        ];
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
        global $CFG, $USER, $DB;

        include_once("{$CFG->dirroot}/{$path}/lib.php");
        $config = get_config('local_search');

        // Get the chat session and all related stuff.
        if ($itemtype == 'description') {
            $assignment = $DB->get_record('assign', ['id' => $thisid]);
        } else if ($itemtype == 'submitted') {
            $submission = $DB->get_record('assign_submissions', ['id' => $thisid]);
            $assignment = $DB->get_record('assign', ['id' => $submission->assignment]);
        }
        $context = $DB->get_record('context', ['id' => $contextid]);
        $cm = get_record('course_modules', 'id', $context->instanceid);

        if (empty($cm)) {
            return false; // Shirai 20090530 - MDL19342 - course module might have been delete.
        }

        if (!$cm->visible and !has_capability('moodle/course:viewhiddenactivities', $context)) {
            if (!empty($config->access_debug)) {
                echo "search reject : hidden assign ";
            }
            return false;
        }

        /*
         * group consistency check : checks the following situations about groups
         * trap if user is not same group and groups are separated
        $current_group = get_current_group($course->id);
        $course = get_record('course', 'id', $assignment->course);
        if ((groupmode($course, $cm) == SEPARATEGROUPS) &&
                !ismember($groupid) &&
                        !has_capability('moodle/site:accessallgroups', $context)){
            if (!empty($CFG->search_access_debug)) {
                echo "search reject : assignment element is in separated group ";
            }
            return false;
        }
        */

        // User ownership check :
        // Trap if user is not owner of the resource and the ressource is a submission/attachement.
        if ($itemtype == 'submission' && $USER->id != $submission->userid && !has_capability('mod/assign:view', $context)) {
            if (!empty($config->access_debug)) {
                echo "search reject : i'm not owner of this assign ";
            }
            return false;
        }

        // Date check : no submission may be viewed before duedate.
        if ($itemtype == 'submission' && $assignment->duedate < time()) {
            if (!empty($config->access_debug)) {
                echo "search reject : cannot read submissions before end of assign ";
            }
            return false;
        }

        // Ownership check : checks the following situations about user.
        // Trap if user is not owner and cannot see other's entries.
        // TODO : typically may be stored into indexing cache.
        if (!has_capability('mod/assign:view', $context)) {
            if (!empty($config->access_debug)) {
                echo "search reject : cannot read past sessions ";
            }
            return false;
        }

        return true;
    }

    /**
     * this call back is called when displaying the link for some last post processing
     *
     */
    public static function link_post_processing($title) {
        if (!function_exists('search_assignment_getstring')) {
            function search_assignment_getstring($matches) {
                return get_string($matches[1], 'assignment');
            }
        }

        $title = preg_replace_callback('/^(description|submitted)/', 'search_assignment_getstring', $title);

        return parent::link_post_processing($title);
    }
}
