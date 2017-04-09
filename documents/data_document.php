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
 * @date 2008/03/31
 * @license http://www.gnu.org/copyleft/gpl.html GNU Public License
 *
 * document handling for data activity module
 * This file contains the mapping between a database object and it's indexable counterpart,
 *
 * Functions for iterating and retrieving the necessary records are now also included
 * in this file, rather than mod/data/lib.php
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
require_once($CFG->dirroot.'/mod/data/lib.php');

/**
 * a class for representing searchable information (data records)
 *
 */
class DataSearchDocument extends SearchDocument {

    /**
     * constructor
     */
    public function __construct(&$record, $courseid, $contextid) {
        global $DB;

        // Generic information; required.
        $doc = new StdClass;
        $doc->docid     = $record['id'];
        $doc->documenttype  = SEARCH_TYPE_DATA;
        $doc->itemtype      = 'record';
        $doc->contextid     = $contextid;

        $doc->title     = $record['title'];
        $doc->date      = $record['timemodified'];
        // Remove '(ip.ip.ip.ip)' from data record author field.
        if ($record['userid']) {
            $user = $DB->get_record('user', array('id' => $record['userid']));
        }
        $doc->author = (isset($user)) ? $user->firstname.' '.$user->lastname : '';
        $doc->contents  = $record['content'];
        $doc->url       = data_document_wrapper::make_link($record['dataid'], $record['id']);

        // Module specific information; optional.
        $data = new StdClass;
        $data->database = $record['dataid'];

        // Construct the parent class.
        parent::__construct($doc, $data, $courseid, $record['groupid'], $record['userid'], 'mod/'.SEARCH_TYPE_DATA);
    }
}

/**
 * a class for representing searchable information (comments on data records)
 *
 */
class DataCommentSearchDocument extends SearchDocument {

    /**
     * constructor
     */
    public function __construct(&$comment, $courseid, $contextid) {

        // Generic information; required.
        $doc->docid     = $comment['id'];
        $doc->documenttype  = SEARCH_TYPE_DATA;
        $doc->itemtype      = 'comment';
        $doc->contextid     = $contextid;

        $doc->title     = get_string('commenton', 'search').' '.$comment['title'];
        $doc->date      = $comment['modified'];
        // Remove '(ip.ip.ip.ip)' from data record author field.
        $doc->author    = preg_replace('/\(.*?\)/', '', $comment['author']);
        $doc->contents  = $comment['content'];
        $doc->url       = data_document_wrapper::make_link($comment['dataid'], $comment['recordid']);

        // Module specific information; optional.
        $data = new StdClass();
        $data->database = $comment['dataid'];
        $data->recordid = $comment['recordid'];

        // Construct the parent class.
        parent::__construct($doc, $data, $courseid, $comment['groupid'], $comment['userid'], 'mod/'.SEARCH_TYPE_DATA);
    }
}

class data_document_wrapper extends document_wrapper {

    /**
     * constructs a valid link to a data record content
     * @param database_id the database reference
     * @param record_id the record reference
     * @return a valid url top access the information as a string
     */
    public static function make_link($instanceid) {

        // Get an additional subentity id dynamically.
        $extravars = func_get_args();
        array_shift($extravars);
        $recordid = array_shift($extravars);

        return new moodle_url('/mod/data/view.php', array('d' => $instanceid, 'rid' => $recordid));
    }

    /**
     * fetches all the records for a given database
     * @param database_id the database
     * @param typematch a comma separated list of types that should be considered for searching or *
     * @return an array of objects representing the data records.
     */
    public static function get_data_records($databaseid, $typematch = '*', $recordid = 0) {
        global $DB;

        $fieldset = $DB->get_records('data_fields', array('dataid' => $databaseid));
        $uniquerecordclause = ($recordid > 0) ? " AND c.recordid = $recordid " : '';
        $query = "
            SELECT
               c.*
            FROM
                {data_content} as c,
                {data_records} as r
            WHERE
                c.recordid = r.id AND
                r.dataid = ? 
                $uniquerecordclause
            ORDER BY
                c.fieldid
        ";
        $data = $DB->get_records_sql($query, array($databaseid));
        $records = array();
        if ($data) {
            foreach ($data as $adatum) {
                if ($typematch == '*' ||
                        preg_match("/\\b{$fieldset[$adatum->fieldid]->type}\\b/", $typematch)) {
                    if (!isset($records[$adatum->recordid])) {
                        $records[$adatum->recordid]['_first'] = $adatum->content.' '.$adatum->content1.' '.$adatum->content2;
                        $records[$adatum->recordid]['_first'] .= ' '.$adatum->content3.' '.$adatum->content4.' ';
                    } else {
                        $records[$adatum->recordid][$fieldset[$adatum->fieldid]->name] = $adatum->content.' '.$adatum->content1;
                        $records[$adatum->recordid][$fieldset[$adatum->fieldid]->name] .= ' '.$adatum->content2;
                        $records[$adatum->recordid][$fieldset[$adatum->fieldid]->name] .= ' '.$adatum->content3;
                        $records[$adatum->recordid][$fieldset[$adatum->fieldid]->name] .= ' '.$adatum->content4.' ';
                    }
                }
            }
        }
        return $records;
    }

    /**
     * part of search engine API
     *
     */
    public static function get_iterator() {
        global $DB;

        $databases = $DB->get_records('data');
        return $databases;
    }

    /**
     * part of search engine API
     * @param database the database instance
     * @return an array of searchable documents
     */
    public static function get_content_for_index(&$instance) {
        global $DB;

        $documents = array();
        $recordtitles = array();
        $cm = get_coursemodule_from_instance('data', $instance->id);
        $context = context_module::instance($cm->id);

        // Getting records for indexing. Each record makes a document.
        $recordscontent = self::get_data_records($instance->id, 'text,textarea');
        if ($recordscontent) {
            foreach (array_keys($recordscontent) as $arecordid) {

                // Extract title as first record in order.
                $first = $recordscontent[$arecordid]['_first'];
                unset($recordscontent[$arecordid]['_first']);

                // Concatenates all other texts.
                $content = '';
                foreach ($recordscontent[$arecordid] as $afield) {
                    $content = @$content.' '.$afield;
                }
                unset($recordmetadata);
                $recordmetadata = $DB->get_record('data_records', array('id' => $arecordid));
                $recordmetadata->title = $first;
                $recordtitles[$arecordid] = $first;
                $recordmetadata->content = $content;
                $vars = get_object_vars($recordmetadata);
                $documents[] = new DataSearchDocument($vars, $instance->course, $context->id);
            }
        }

        // Getting comments for indexing.
        $recordscomments = search_get_comments('data', $instance->id);
        if ($recordscomments) {
            foreach ($recordscomments as $acomment) {
                $acomment->title = $recordtitles[$acomment->itemid]; // The comment title is given as the record's title.
                $authoruser = $DB->get_record('user', array('id' => $acomment->userid));
                $acomment->author = fullname($authoruser);
                $acomment->recordid = $acomment->itemid;
                $vars = get_object_vars($acomment);
                $documents[] = new DataCommentSearchDocument($vars, $instance->course, $context->id);
            }
        }
        return $documents;
    }

    /**
     * returns a single data search document based on a data entry id
     * @param id the id of the record
     * @param the type of the information
     * @return a single searchable document
     */
    public static function single_document($id, $itemtype) {
        global $DB;

        if ($itemtype == 'record') {

            // Get main record.
            $recordmetadata = $DB->get_record('data_records', array('id' => $id));

            // Get context.
            $recordcourse = $DB->get_field('data', 'course', array('id' => $recordmetadata->dataid));
            $coursemodule = $DB->get_field('modules', 'id', array('name' => 'data'));
            $params = array('course' => $recordcourse, 'module' => $coursemodule, 'instance' => $recordmetadata->dataid);
            $cm = $DB->get_record('course_modules', $params);
            $context = context_module::instance($cm->id);

            // Get text fields ids in this data (computable fields).
            // Compute text.
            $recorddata = self::get_data_records($recordmetadata->dataid, 'text,textarea', $id);
            if ($recorddata) {
                $dataarr = array_values($recorddata);
                $recordcontent = $dataarr[0]; // We cannot have more than one record here.

                // Extract title as first record in order.
                $first = $recordcontent['_first'];
                unset($recordcontent['_first']);

                // Concatenates all other texts.
                $content = '';
                foreach ($recordcontent as $afield) {
                    $content = @$content.' '.$afield;
                }
                unset($recordmetadata);
                $recordmetadata = $DB->get_record('data_records', array('id' => $arecordid));
                $recordmetadata->title = $first;
                $recordmetadata->content = $content;
                $arr = get_object_vars($recordmetadata);
                return new DataSearchDocument($arr, $recordcourse, $context->id);
            }
        } else if ($itemtype == 'comment') {

            // Get main records.
            $comment = $DB->get_record('comments', array('id' => $id));
            $record = $DB->get_record('data_records', array('id' => $comment->itemid));

            // Get context.
            $recordcourse = $DB->get_field('data', 'course', array('id' => $record->dataid));
            $coursemodule = $DB->get_field('modules', 'id', array('name' => 'data'));
            $params = array('course' => $recordcourse, 'module' => $coursemodule, 'instance' => $recordmetadata->dataid);
            $cm = $DB->get_record('course_modules', $params);
            $context = context_module::instance($cm->id);

            // Add extra fields.
            $comment->title = $DB->get_field('search_document', 'title', array('docid' => $record->id, 'itemtype' => 'record'));
            $comment->dataid = $record->dataid;
            $comment->groupid = $record->groupid;
            $authoruser = $DB->get_record('user', array('id' => $comment->userid));
            $comment->author = fullname($authoruser);
            $comment->recordid = $record->id;

            // Make document.
            $vars = get_object_vars($comment);
            return new DataCommentSearchDocument($vars, $recordcourse, $context->id);
        } else {
            mtrace('Error : bad or missing item type');
            return null;
        }
    }

    /**
     * returns the var names needed to build a sql query for addition/deletions
     * [primary id], [table name], [time created field name], [time modified field name]
     * Getting comments is a complex join extracting info from the global mdl_comments table.
     */
    public static function db_names() {
        $commentwhere = '
            c.contextid = ctx.id AND
            c.itemid = dr.id AND
            ctx.instanceid = cm.id AND
            ctx.contextlevel = 80 AND
            dr.dataid = ctx.instanceid ';
        $commentjoinextension = ' c ,{data_records} dr, {course_modules} cm, {context} ctx ';

        return array(
            array('id', 'data_records', 'timecreated', 'timemodified', 'record'),
            array('c.id', 'comments', 'c.timecreated', 'c.timecreated', 'comment', $commentwhere, $commentjoinextension)
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
     * @param this_id the item id within the information class denoted by itemtype. In databases, this id
     * points out an indexed data record page.
     * @param user the user record denoting the user who searches
     * @param group_id the current group used by the user when searching
     * @return true if access is allowed, false elsewhere
     */
    public static function check_text_access($path, $itemtype, $thisid, $user, $groupid, $contextid) {
        global $DB;

        $config = get_config('local_search');

        // Get the database object and all related stuff.
        if ($itemtype == 'record') {
            $record = get_record('data_records', array('id' => $thisid));
        } else if ($itemtype == 'comment') {
            $comment = $DB->get_record('comments', array('id' => $thisid));
            $record = $DB->get_record('data_records', array('id' => $comment->recordid));
        } else {
          // We do not know what type of information is required.
          return false;
        }
        $data = $DB->get_record('data', array('id' => $record->dataid));
        $context = $DB->get_record('context', array('id' => $contextid));
        $cm = $DB->get_record('course_modules', array('id' => $context->instanceid));
        if (empty($cm)) {
            return false; // Shirai 20093005 - MDL19342 - course module might have been delete.
        }

        if (!$cm->visible and !has_capability('moodle/course:viewhiddenactivities', $context)) {
            if (!empty($config->access_debug)) {
                echo "search reject : hidden database ";
            }
            return false;
        }

        /*
         * Group consistency check : checks the following situations about groups
         * trap if user is not same group and groups are separated
         */
        $course = $DB->get_record('course', array('id' => $data->course));
        if ((groups_get_activity_groupmode($cm) == SEPARATEGROUPS) &&
                !groups_is_member($groupid) &&
                        !has_capability('moodle/site:accessallgroups', $context)) {
            if (!empty($config->access_debug)) {
                echo "search reject : separated group owned resource ";
            }
            return false;
        }

        /*
         * Pwnership check : checks the following situations about user
         * trap if user is not owner and has cannot see other's entries
         */
        if ($itemtype == 'record') {
            if ($user->id != $record->userid &&
                    !has_capability('mod/data:viewentry', $context) &&
                            !has_capability('mod/data:manageentries', $context)) {
                if (!empty($config->access_debug)) {
                    echo "search reject : not owned resource ";
                }
                return false;
            }
        }

        // Approval check.
        // Trap if unapproved and has not approval capabilities.
        // TODO : report a potential capability lack of : mod/data:approve.
        $approval = $DB->get_field('data_records', 'approved', array('id' => $record->id));
        if (!$approval && !has_capability('mod/data:manageentries', $context)) {
            if (!empty($config->access_debug)) {
                echo "search reject : unapproved resource ";
            }
            return false;
        }

        // Minimum records to view check.
        // Trap if too few records.
        // TODO : report a potential capability lack of : mod/data:viewhiddenentries.
        $recordsamount = $DB->count_records('data_records', array('dataid' => $data->id));
        if ($data->requiredentriestoview > $recordsamount && !has_capability('mod/data:manageentries', $context)) {
            if (!empty($config->access_debug)) {
                echo "search reject : not enough records to view ";
            }
            return false;
        }

        // Opening periods check.
        // Trap if user has not capability to see hidden records and date is out of opening range.
        // TODO : report a potential capability lack of : mod/data:viewhiddenentries.
        $now = usertime(time());
        if ($data->timeviewfrom > 0) {
            if ($now < $data->timeviewfrom && !has_capability('mod/data:manageentries', $context)) {
                if (!empty($config->access_debug)) {
                    echo "search reject : still not open activity ";
                }
                return false;
            }
        }
        if ($data->timeviewto > 0) {
            if ($now > $data->timeviewto && !has_capability('mod/data:manageentries', $context)) {
                if (!empty($config->access_debug)) {
                    echo "search reject : closed activity ";
                }
                return false;
            }
        }

        return true;
    }
}