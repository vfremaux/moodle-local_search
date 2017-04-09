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
 * @author Valery Fremaux [valery.fremaux@club-internet.fr] > 1.8
 * @contributor Tatsuva Shirai 20090530
 * @license http://www.gnu.org/copyleft/gpl.html GNU Public License
 *
 * document handling for assignment activity module
 */
namespace local_search;

use \StdClass;
use \context_module;
use \context_course;
use \moodle_url;

defined('MOODLE_INTERNAL') || die();

require_once($CFG->dirroot.'/local/search/documents/document.php');
require_once($CFG->dirroot.'/local/search/documents/document_wrapper.class.php');
require_once($CFG->dirroot.'/mod/assignment/lib.php');

/**
 * a class for representing searchable information
 *
 */
class AssignmentSearchDocument extends SearchDocument {

    /**
     * constructor
     */
    public function __construct(&$assignment, $assignmentmoduleid, $itemtype, $courseid, $ownerid, $contextid) {
        global $DB;

        // Generic information; required.
        $doc = new StdClass;
        $doc->docid         = $assignment['id'];
        $doc->documenttype  = SEARCH_TYPE_ASSIGNMENT;
        $doc->itemtype      = $itemtype;
        $doc->contextid     = $contextid;

        // We cannot call userdate with relevant locale at indexing time.
        $doc->title         = "{$itemtype}: {$assignment['name']}";
        $doc->date          = $assignment['datemodified'];

        // Remove '(ip.ip.ip.ip)' from chat author list.
        $owner = $DB->get_record('user', array('id' => $ownerid));
        $doc->author        = fullname($owner);
        $doc->contents      = $assignment['intro'];
        $doc->url           = assignment_document_wrapper::make_link($assignmentmoduleid, $itemtype, $ownerid);

        // Module specific information; optional.
        $data = new StdClass;
        $data->assignment         = $assignmentmoduleid;
        $data->assignmenttype     = $assignment['assignmenttype'];

        // Construct the parent class.
        parent::__construct($doc, $data, $courseid, 0, 0, 'mod/'.SEARCH_TYPE_ASSIGNMENT);
    }
}

/**
 * a class for representing searchable information
 *
 */
class AssignmentSubmissionSearchDocument extends SearchDocument {

    /**
     * constructor
     */
    public function __construct(&$submission, $contextid) {

        // Generic information; required.
        $doc = new StdClass;
        $doc->docid         = $submission['fileid'];
        $doc->documenttype  = SEARCH_TYPE_ASSIGNMENT;
        $doc->itemtype      = 'submission';
        $doc->contextid     = $contextid;

        // We cannot call userdate with relevant locale at indexing time.
        $doc->title         = "Submission: {$submission['assignmentname']}";
        $doc->date          = $submission['timecreated'];

        // Remove '(ip.ip.ip.ip)' from chat author list.
        $doc->author        = $submission['authors'];
        $doc->contents      = $submission['alltext'];
        $doc->url           = assignment_document_wrapper::make_link($submission['cmid'], 'submission');

        // Module specific information; optional.
        $data = new StdClass;
        $data->assignment         = $submission['cmid'];
        $data->submission         = $submission['id'];

        // Construct the parent class.
        parent::__construct($doc, $data, $submission['courseid'], 0, 0, 'mod/'.SEARCH_TYPE_ASSIGNMENT);
    }
}

class assignment_document_wrapper extends document_wrapper {

    protected static $modname = 'assignement';

    /**
     * constructs a valid link to a chat content
     * @param cm_id the chat course module
     * @param start the start time of the session
     * @param end th end time of the session
     * @return a well formed link to session display
     */
    public static function make_link($instanceid) {

        // Get an additional subentity id dynamically.
        $extravars = func_get_args();
        array_shift($extravars);
        $itemtype = array_shift($extravars);

        if ($itemtype == 'description') {
            return new moodle_url('/mod/assignment/view.php', array('id' => $instanceid));
        }
        if ($itemtype == 'submission') {
            return new moodle_url('/mod/assignment/view.php', array('id' => $instanceid));
        }
    }

    /**
     * part of search engine API
     * @return an array of instances
     */
    public static function get_iterator() {
        global $DB;

        if ($assignments = $DB->get_records('assignment')) {
            return $assignments;
        } else {
            return array();
        }
    }

    /**
     * part of search engine API
     *
     */
    public static function get_content_for_index(&$instance) {
        global $CFG, $DB;

        $documents = array();

        $course = $DB->get_record('course', array('id' => $instance->course));
        $coursemodule = $DB->get_field('modules', 'id', array('name' => 'assignment'));
        $params = array('course' => $instance->course, 'module' => $coursemodule, 'instance' => $instance->id);
        $cm = $DB->get_record('course_modules', $params);

        if ($cm) {
            $context = context_module::instance($cm->id);

            $assignment->authors = '';
            $assignment->date = $assignment->timemodified;
            $arr = get_object_vars($assignment);
            $documents[] = new AssignmentSearchDocument($arr, $cm->id, 'description', $instance->course, null, $context->id);

            $submissions = assignment_get_all_submissions($instance);
            if ($submissions) {
                foreach ($submissions as $submission) {
                    $owner = $DB->get_record('user', array('id' => $submission->userid));
                    $submission->authors = fullname($owner);
                    $submission->date = $submission->timemodified;
                    $submission->name = "submission:";
                    $submission->cmid = $cm->id;
                    if (file_exists($CFG->dirroot.'/mod/assignment/type/'.$assignment->assignmenttype.'/searchlib.php')) {
                        include_once($CFG->dirroot.'/mod/assignment/type/'.$assignment->assignmenttype.'/searchlib.php');
                        if (function_exists('assignment_get_submission_location')) {
                            $submitted = assignment_get_submission_location($instance, $submission);
                        }
                    }
                    if (empty($submitted)) {

                        // This is for moodle legacy types that would need not to be patched for searchlib.php.
                        switch ($instance->assignmenttype) {
                            case 'online': {
                                $submitted->source = 'text';
                                $submitted->data = $submission->data1;
                                break;
                            }

                            case 'uploadsingle':
                            case 'upload': {
                                $submitted->source = 'files';
                                break;
                            }

                            case 'offline': {
                                continue; // Cannot index, no content in Moodle !!
                            }
                        }
                    }
                    if (empty($submitted)) {
                        continue; // Ignoring.
                    }

                    if ($submitted->source = 'text') {
                        $submission->description = $submitted->data;
                        $submission->description = preg_replace("/<[^>]*>/", '', $submission->description); // Strip all tags.
                        $arr = get_object_vars($submission);
                        $documents[] = new AssignmentSearchDocument($arr, $cm->id, 'submission', $assignment->course,
                                                                    $submission->userid, $context->id);
                        mtrace("finished online submission for {$submission->authors} in assignement {$assignment->name}");
                    } else if ($submitted->source = 'files') {
                        if ($files = $fs->get_area_files($context->id, 'mod_assignment', 'submission', $submission->id,
                                                         'filepath,filename', true)) {
                            foreach ($files as $file) {
                                $submission->fileid = $file->id; // Registers unique id in index.
                                $submission->courseid = $instance->course;
                                search_get_physical_file($documents, $file, $submission, $context->id,
                                                         'AssignmentSubmissionSearchDocument', false);
                                $message = "finished submission {$submission->id} by {$submission->authors}";
                                $message .= " in assignement {$assignment->name}";
                                mtrace($message);
                            }
                        }
                        closedir($submission->path);
                    }
                }
            }
            mtrace("finished assignment {$assignment->name}");
            return $documents;
        }
        return array();
    }

    /**
     * returns a single data search document based on an assignment
     * @param string $id the id of the searchable item
     * @param string $itemtype the type of information
     */
    public static function single_document($id, $itemtype) {
        global $DB;

        if ($itemtype == 'requirement') {
            if (!$assignment = $DB->get_record('assignment', array('id' => $id))) {
                return null;
            }
        } else if ($itemtype == 'submission') {
            if ($submission = $DB->get_record('assignment_submissions', array('id' => $id))) {
                if (!$assignment = $DB->get_record('assignment', array('id' => $submission->assignment))) {
                    return null;
                }
            } else {
                return null;
            }
        }
        $course = $DB->get_record('course', array('id' => $assignment->course));
        $coursemodule = $DB->get_field('modules', 'id', array('name' => 'assignment'));
        $params = array('course' => $course->id, 'module' => $coursemodule, 'instance' => $assignment->id);
        $cm = $DB->get_record('course_modules', $params);

        if ($cm) {
            $context = context_module::instance($cm->id);

            // Should be only one.
            if ($itemtype == 'description') {
                $arr = get_object_vars($assignment);
                $document = new AssignmentSearchDocument($arr, $cm->id, 'description', $assignment->course, null, $context->id);
                return $document;
            }
            if ($itemtype == 'submission') {
                $file = $fs->get_file_by_id($id);
                $void = array();
                $submission->courseid = $assigment->course;
                return search_get_physical_file($void, $file, $submission, $context->id,
                                                'AssignmentSubmissionSearchDocument', true);
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
        return array(
            array('id', 'assignment', 'timemodified', 'timemodified', 'description'),
            array('id', 'assignment_submissions', 'timecreated', 'timemodified', 'submission')
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
        global $CFG, $USER, $DB;

        include_once("{$CFG->dirroot}/{$path}/lib.php");
        $config = get_config('local_search');

        // Get the chat session and all related stuff.
        if ($itemtype == 'description') {
            $assignment = $DB->get_record('assignment', array('id' => $thisid));
        } else if ($itemtype == 'submitted') {
            $submission = $DB->get_record('assignment_submissions', array('id' => $thisid));
            $assignment = $DB->get_record('assignment', array('id' => $submission->assignment));
        }
        $context = $DB->get_record('context', array('id' => $contextid));
        $cm = get_record('course_modules', 'id', $context->instanceid);

        if (empty($cm)) {
            return false; // Shirai 20090530 - MDL19342 - course module might have been delete.
        }

        if (!$cm->visible and !has_capability('moodle/course:viewhiddenactivities', $context)) {
            if (!empty($config->access_debug)) {
                echo "search reject : hidden assignment ";
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
        if ($itemtype == 'submitted' && $USER->id != $submission->userid && !has_capability('mod/assignment:view', $context)) {
            if (!empty($config->access_debug)) {
                echo "search reject : i'm not owner of this assignment ";
            }
            return false;
        }

        // Date check : no submission may be viewed before timedue.
        if ($itemtype == 'submitted' && $assignment->timedue < time()) {
            if (!empty($config->access_debug)) {
                echo "search reject : cannot read submissions before end of assignment ";
            }
            return false;
        }

        // Ownership check : checks the following situations about user.
        // Trap if user is not owner and cannot see other's entries.
        // TODO : typically may be stored into indexing cache.
        if (!has_capability('mod/assignment:view', $context)) {
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