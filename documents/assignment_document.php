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

defined('MOODLE_INTERNAL') || die();

/**
 * Global Search Engine for Moodle
 *
 * @package local_search
 * @category local
 * @subpackage document_wrappers
 * @author Valery Fremaux [valery.fremaux@club-internet.fr] > 1.8
 * @contributor Tatsuva Shirai 20090530
 * @date 2008/03/31
 * @license http://www.gnu.org/copyleft/gpl.html GNU Public License
 *
 * document handling for assignment activity module
 */

require_once("$CFG->dirroot/local/search/documents/document.php");
require_once("$CFG->dirroot/mod/assignment/lib.php");

/**
 * a class for representing searchable information
 *
 */
class AssignmentSearchDocument extends SearchDocument {

    /**
    * constructor
    */
    public function __construct(&$assignmentitem, $assignment_module_id, $itemtype, $course_id, $owner_id, $context_id) {
        // Generic information; required.
        $doc = new StdClass;
        $doc->docid         = $assignmentitem['id'];
        $doc->documenttype  = SEARCH_TYPE_ASSIGNMENT;
        $doc->itemtype      = $itemtype;
        $doc->contextid     = $context_id;

        // We cannot call userdate with relevant locale at indexing time.
        $doc->title         = "{$itemtype}: {$assignmentitem['name']}";
        $doc->date          = $assignmentitem['date'];

        // Remove '(ip.ip.ip.ip)' from chat author list
        $doc->author        = $assignmentitem['authors'];
        $doc->contents      = $assignmentitem['description'];
        $doc->url           = assignment_make_link($assignment_module_id, $itemtype, $owner_id);

        // Module specific information; optional
        $data = new StdClass;
        $data->assignment         = $assignment_module_id;
        $data->assignmenttype         = $assignmentitem['assignmenttype'];
        
        // Construct the parent class
        parent::__construct($doc, $data, $course_id, 0, 0, 'mod/'.SEARCH_TYPE_ASSIGNMENT);
    }
}

/**
 * constructs a valid link to a chat content
 * @param cm_id the chat course module
 * @param start the start time of the session
 * @param end th end time of the session
 * @uses $CFG
 * @return a well formed link to session display
 */
function assignment_make_link($cm_id, $itemtype, $owner) {
    global $CFG;

    if ($itemtype == 'description') {
        return new moodle_url('/mod/assignment/view.php', array('id' => $cm_id));
    }
}

/**
 * part of search engine API
 *
 */
function assignment_iterator() {
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
function assignment_get_content_for_index(&$assignment) {
    global $CFG, $DB;

    $documents = array();
    $course = $DB->get_record('course', array('id' => $assignment->course));
    $coursemodule = $DB->get_field('modules', 'id', array('name' => 'assignment'));
    $cm = $DB->get_record('course_modules', array('course' => $assignment->course, 'module' => $coursemodule, 'instance' => $assignment->id));

    if ($cm) {
        $context = context_module::instance($cm->id);

        $assignment->authors = '';
        $assignment->date = $assignment->timemodified;
        $arr = get_object_vars($assignment);
        $documents[] = new AssignmentSearchDocument($arr, $cm->id, 'description', $assignment->course, null, $context->id);
            
        $submissions = assignment_get_all_submissions($assignment);
        if ($submissions) {
            foreach ($submissions as $submission) {
                $owner = $DB->get_record('user', array('id' => $submission->userid));
                $submission->authors = fullname($owner);
                $submission->assignmenttype = $assignment->assignmenttype;
                $submission->date = $submission->timemodified;
                $submission->name = "submission:";
                if (file_exists($CFG->dirroot.'/mod/assignment/type/'.$assignment->assignmenttype.'/searchlib.php')) {
                    include_once($CFG->dirroot.'/mod/assignment/type/'.$assignment->assignmenttype.'/searchlib.php');
                    if (function_exists('assignment_get_submission_location')) {
                        $submitted = assignment_get_submission_location($assignment, $submission);
                    }
                }
                if (empty($submitted)) {
                    // this is for moodle legacy types that would need not to be patched for searchlib.php
                    switch ($assignment->assignmenttype) {
                        case 'online':
                            $submitted->source = 'text';
                            $submitted->data = $submission->data1;
                            break;

                        case 'uploadsingle' : 
                        case 'upload':
                            $submitted->source = 'files';
                            $submitted->data = "{$assignment->course}/moddata/assignment/{$assignment->id}/{$submission->userid}";
                            break;

                        case 'offline' : continue; // cannot index, no content in Moodle !!
                    }
                }
                if (empty($submitted)) continue; // ignoring

                if ($submitted->source = 'text') {
                    $submission->description = $submitted->data;
                    $submission->description = preg_replace("/<[^>]*>/", '', $submission->description); // stip all tags
                    $arr = get_object_vars($submission);
                    $documents[] = new AssignmentSearchDocument($arr, $cm->id, 'submission', $assignment->course, $submission->userid, $context->id);
                    mtrace("finished online submission for {$submission->authors} in assignement {$assignment->name}");
                } elseif ($submitted->source = 'files') {
                    $SUBMITTED = opendir($submitted->path);
                    while ($entry = readdir($SUBMITTED)) {
                        if (preg_match("/^\./", $entry)) continue; // exclude hidden and dirs . and ..
                        $path = "{$submitted->path}/{$entry}";
                        $documents[] = assignment_get_physical_file($submission, $assignment, $cm, $path, $context_id, $documents);
                        mtrace("finished attachement $path for {$submission->authors} in assignement {$assignment->name}");
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
 * get text from a physical file in an assignment submission 
 * @param object $submission a submission for which to fetch some representative text
 * @param object $assignment the relevant assignment as a context
 * @param object $cm the corresponding coursemodule
 * @param string $path a file from which to fetch some representative text
 * @param int $contextid the moodle context if needed
 * @param documents the array of documents, by ref, where to add the new document.
 * @return a search document when unique or false.
 */
function assignment_get_physical_file(&$submission, &$assignment, &$cm, $path, $context_id, &$documents = null){
    global $CFG, $DB;

    $fileparts = pathinfo($path);

    // Cannot index unknown or masked types.
    if (empty($fileparts['extension'])) {
        mtrace("Cannot index without explicit extension.");
        return false;
    }

    $ext = strtolower($fileparts['extension']);

    // Cannot index unallowed or unhandled types.
    if (!preg_match("/\b$ext\b/i", $CFG->block_search_filetypes)) {
        mtrace($fileparts['extension'] . ' is not an allowed extension for indexing');
        return false;
    }

    if (file_exists($CFG->dirroot.'/search/documents/physical_'.$ext.'.php')) {
        include_once($CFG->dirroot.'/search/documents/physical_'.$ext.'.php');
        $function_name = 'get_text_for_indexing_'.$ext;
        $submission->description = $function_name(null, $path);

        // Get authors.
        $user = $DB->get_record('user', array('id' => $submission->userid));
        $submission->authors = fullname($user);

        // We need a real id on file.
        $submission->id = "{$submission->id}/{$path}";
        
        if (!empty($submission->description)) {
            if ($getsingle) {
                $arr = get_object_vars($submission);
                $single = new AssignmentSearchDocument($arr, $cm->id, 'submitted', $assignment->course, $submission->userid, $context_id);
                mtrace("finished submission file from {$submission->authors}");
                return $single;
            } else {
                $arr = get_object_vars($submission);
                $documents[] = new AssignmentSearchDocument($arr, $cm->id, 'submitted', $assignment->course, $submission->userid, $context_id);
            }
            mtrace("finished submission file from {$submission->authors}");
        }
    } else {
        mtrace("fulltext handler not found for $ext type");
    }
    return false;
}

/**
 * returns a single data search document based on an assignment
 * @param string $id the id of the searchable item
 * @param string $itemtype the type of information
 */
function assignment_single_document($id, $itemtype) {
    global $DB;

    if ($itemtype == 'requirement') {
        if (!$assignment = $DB->get_record('assignment', array('id' => $id))){
            return null;
        }
    } elseif ($itemtype == 'submission') {
        if ($submission = $DB->get_record('assignment_submissions', array('id' => $id))){
            if (!$assignment = $DB->get_record('assignment', array('id' => $submission->assignment))){
                return null;
            }
        } else {
            return null;
        }
    }
    $course = $DB->get_record('course', array('id' => $assignment->course));
    $coursemodule = $DB->get_field('modules', 'id', array('name' => 'assignment'));
    $cm = $DB->get_record('course_modules', array('course' => $course->id, 'module' => $coursemodule, 'instance' => $assignment->id));
    if ($cm) {
        $context = context_module::instance($cm->id);

        // Should be only one. 
        if ($itemtype == 'description') {
            $arr = get_object_vars($assignment);
            $document = new AssignmentSearchDocument($arr, $cm->id, 'description', $assignment->course, null, $context->id);
            return $document;
        }
        if ($itemtype == 'submittted') {
            $arr = get_object_vars($submission);
            $document = new AssignmentSearchDocument($arr, $cm->id, 'submitted', $assignment->course, null, $context->id);
            return $document;
        }
    }
    return null;
}

/**
 * dummy delete function that packs id with itemtype.
 * this was here for a reason, but I can't remember it at the moment.
 *
 */
function assignment_delete($info, $itemtype) {
    $object->id = $info;
    $object->itemtype = $itemtype;
    return $object;
}

/**
* returns the var names needed to build a sql query for addition/deletions
* // TODO chat indexable records are virtual. Should proceed in a special way 
*/
function assignment_db_names() {
    //[primary id], [table name], [time created field name], [time modified field name]
    return array(
        array('id', 'assignment', 'timemodified', 'timemodified', 'description'),
        array('id', 'assignment_submissions', 'timecreated', 'timemodified', 'submitted')
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
function assignment_check_text_access($path, $itemtype, $this_id, $user, $group_id, $context_id) {
    global $CFG, $USER, $DB;

    include_once("{$CFG->dirroot}/{$path}/lib.php");

    // get the chat session and all related stuff
    if ($itemtype == 'description') {
        $assignment = $DB->get_record('assignment', array('id' => $this_id));
    } elseif ($itemtype == 'submitted') {
        $submission = $DB->get_record('assignment_submissions', array('id' => $this_id));
        $assignment = $DB->get_record('assignment', array('id' => $submission->assignment));
    }
    $context = $DB->get_record('context', array('id' => $context_id));
    $cm = get_record('course_modules', 'id', $context->instanceid);

    if (empty($cm)) {
        return false; // Shirai 20090530 - MDL19342 - course module might have been delete
    }

    if (!$cm->visible and !has_capability('moodle/course:viewhiddenactivities', $context)) {
        if (!empty($CFG->search_access_debug)) {
            echo "search reject : hidden assignment ";
        }
        return false;
    }

    /*
     * group consistency check : checks the following situations about groups
     * trap if user is not same group and groups are separated
    $current_group = get_current_group($course->id);
    $course = get_record('course', 'id', $assignment->course);
    if ((groupmode($course, $cm) == SEPARATEGROUPS) && !ismember($group_id) && !has_capability('moodle/site:accessallgroups', $context)){ 
        if (!empty($CFG->search_access_debug)) echo "search reject : assignment element is in separated group ";
        return false;
    }
    */

    //user ownership check :
    // trap if user is not owner of the resource and the ressource is a submission/attachement
    if ($itemtype == 'submitted' && $USER->id != $submission->userid && !has_capability('mod/assignment:view', $context)) {
        if (!empty($CFG->search_access_debug)) echo "search reject : i'm not owner of this assignment ";
        return false;
    }

    //date check : no submission may be viewed before timedue
    if ($itemtype == 'submitted' && $assignment->timedue < time()) {
        if (!empty($CFG->search_access_debug)) echo "search reject : cannot read submissions before end of assignment ";
        return false;
    }

    //ownership check : checks the following situations about user
    // trap if user is not owner and cannot see other's entries
    // TODO : typically may be stored into indexing cache
    if (!has_capability('mod/assignment:view', $context)) {
        if (!empty($CFG->search_access_debug)) echo "search reject : cannot read past sessions ";
        return false;
    }

    return true;
}

/**
* this call back is called when displaying the link for some last post processing
*
*/
function assignment_link_post_processing($title) {
    global $CFG;

     if (!function_exists('search_assignment_getstring')) {
         function search_assignment_getstring($matches) {
            return get_string($matches[1], 'assignment');
         }
     }

    $title = preg_replace_callback('/^(description|submitted)/', 'search_assignment_getstring', $title);
    
    if ($CFG->block_search_utf8dir) {
        return mb_convert_encoding($title, 'UTF-8', 'auto');
    }
    return mb_convert_encoding($title, 'auto', 'UTF-8');
}
