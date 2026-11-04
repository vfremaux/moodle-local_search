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
 * document handling for data activity module
 * This file contains the mapping between a database object and it's indexable counterpart,
 *
 * Functions for iterating and retrieving the necessary records are now also included
 * in this file, rather than mod/data/lib.php
 *
 */
namespace local_search\document;

use stdCLass;
use moodle_url;
use context_module;
use local_search\document_wrapper;

/**
 * Document wrapper class.
 */
class data_document_wrapper extends document_wrapper {
    /**
     * constructs a valid link to a data record content
     * @param int $instanceid the database reference
     * @param int $contextid
     * @return a valid url top access the information as a string
     */
    public static function make_link($instanceid, $contextid = null) {
        // Get an additional subentity id dynamically.
        $extravars = func_get_args();
        array_shift($extravars);
        $recordid = array_shift($extravars);

        return new moodle_url('/mod/data/view.php', ['d' => $instanceid, 'rid' => $recordid]);
    }

    /**
     * fetches all the records for a given database
     * @param int $databaseid the database
     * @param string $typematch a comma separated list of types that should be considered for searching or *
     * @param $int $recordid
     * @return an array of objects representing the data records.
     */
    public static function get_data_records($databaseid, $typematch = '*', $recordid = 0) {
        global $DB;

        $fieldset = $DB->get_records('data_fields', ['dataid' => $databaseid]);
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
        $data = $DB->get_records_sql($query, [$databaseid]);
        $records = [];
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
     * @param object $instance database the database instance
     * @return an array of searchable documents
     */
    public static function get_content_for_index(&$instance) {
        global $DB;

        $documents = [];
        $recordtitles = [];
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
                $recordmetadata = $DB->get_record('data_records', ['id' => $arecordid]);
                $recordmetadata->title = $first;
                $recordtitles[$arecordid] = $first;
                $recordmetadata->content = $content;
                $vars = get_object_vars($recordmetadata);
                $documents[] = new data_document($vars, $instance->course, $context->id);
            }
        }

        // Getting comments for indexing.
        $recordscomments = search_get_comments('data', $instance->id);
        if ($recordscomments) {
            foreach ($recordscomments as $acomment) {
                $acomment->title = $recordtitles[$acomment->itemid]; // The comment title is given as the record's title.
                $authoruser = $DB->get_record('user', ['id' => $acomment->userid]);
                $acomment->author = fullname($authoruser);
                $acomment->recordid = $acomment->itemid;
                $acomment->modified = $acomment->timecreated;
                $acomment->dataid = $instance->id;
                $acomment->groupid = 0;
                $acomment->userid = $acomment->userid;
                $vars = get_object_vars($acomment);
                $documents[] = new data_comment_document($vars, $instance->course, $context->id);
            }
        }
        return $documents;
    }

    /**
     * returns a single data search document based on a data entry id
     * @param int $id the id of the record
     * @param string $itemtype the type of the information
     * @return a single searchable document
     */
    public static function single_document($id, $itemtype) {
        global $DB;

        if ($itemtype == 'record') {
            // Get main record.
            $recordmetadata = $DB->get_record('data_records', ['id' => $id]);

            // Get context.
            $recordcourse = $DB->get_field('data', 'course', ['id' => $recordmetadata->dataid]);
            $coursemodule = $DB->get_field('modules', 'id', ['name' => 'data']);
            $params = ['course' => $recordcourse, 'module' => $coursemodule, 'instance' => $recordmetadata->dataid];
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
                // Refresh from database.
                unset($recordmetadata);
                $recordmetadata = $DB->get_record('data_records', ['id' => $id]);
                $recordmetadata->title = $first;
                $recordmetadata->content = $content;
                $arr = get_object_vars($recordmetadata);
                return new data_document($arr, $recordcourse, $context->id);
            }
        } else if ($itemtype == 'comment') {
            // Get main records.
            $comment = $DB->get_record('comments', ['id' => $id]);
            $record = $DB->get_record('data_records', ['id' => $comment->itemid]);

            // Get context.
            $recordcourse = $DB->get_field('data', 'course', ['id' => $record->dataid]);
            $coursemodule = $DB->get_field('modules', 'id', ['name' => 'data']);
            $params = ['course' => $recordcourse, 'module' => $coursemodule, 'instance' => $recordmetadata->dataid];
            $cm = $DB->get_record('course_modules', $params);
            $context = context_module::instance($cm->id);

            // Add extra fields.
            $comment->title = $DB->get_field('search_document', 'title', ['docid' => $record->id, 'itemtype' => 'record']);
            $comment->dataid = $record->dataid;
            $comment->groupid = $record->groupid;
            $authoruser = $DB->get_record('user', ['id' => $comment->userid]);
            $comment->author = fullname($authoruser);
            $comment->recordid = $record->id;

            // Make document.
            $vars = get_object_vars($comment);
            return new data_comment_document($vars, $recordcourse, $context->id);
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

        return [
            ['id', 'data_records', 'timecreated', 'timemodified', 'record'],
            ['c.id', 'comments', 'c.timecreated', 'c.timecreated', 'comment', $commentwhere, $commentjoinextension],
        ];
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
     * @param int $thisid the item id within the information class denoted by itemtype. In databases, this id
     * points out an indexed data record page.
     * @param object $user the user record denoting the user who searches
     * @param int $groupid the current group used by the user when searching
     * @param int $contextid
     * @return true if access is allowed, false elsewhere
     */
    public static function check_text_access($path, $itemtype, $thisid, $user, $groupid, $contextid) {
        global $DB;

        $config = get_config('local_search');

        // Get the database object and all related stuff.
        if ($itemtype == 'record') {
            $record = get_record('data_records', ['id' => $thisid]);
        } else if ($itemtype == 'comment') {
            $comment = $DB->get_record('comments', ['id' => $thisid]);
            $record = $DB->get_record('data_records', ['id' => $comment->recordid]);
        } else {
            // We do not know what type of information is required.
            return false;
        }
        $data = $DB->get_record('data', ['id' => $record->dataid]);
        $context = $DB->get_record('context', ['id' => $contextid]);
        $cm = $DB->get_record('course_modules', ['id' => $context->instanceid]);
        if (empty($cm)) {
            return false; // Shirai 20093005 - MDL19342 - course module might have been delete.
        }

        if (!$cm->visible && !has_capability('moodle/course:viewhiddenactivities', $context)) {
            if (!empty($config->access_debug)) {
                echo "search reject : hidden database ";
            }
            return false;
        }

        /*
         * Group consistency check : checks the following situations about groups
         * trap if user is not same group and groups are separated
         */
        $course = $DB->get_record('course', ['id' => $data->course]);
        if (
            (groups_get_activity_groupmode($cm) == SEPARATEGROUPS) &&
            !groups_is_member($groupid) &&
            !has_capability('moodle/site:accessallgroups', $context)
        ) {
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
            if (
                $user->id != $record->userid &&
                !has_capability('mod/data:viewentry', $context) &&
                !has_capability('mod/data:manageentries', $context)
            ) {
                if (!empty($config->access_debug)) {
                    echo "search reject : not owned resource ";
                }
                return false;
            }
        }

        // Approval check.
        // Trap if unapproved and has not approval capabilities.
        // TODO : report a potential capability lack of : mod/data:approve.
        $approval = $DB->get_field('data_records', 'approved', ['id' => $record->id]);
        if (
            !$approval &&
            !has_capability('mod/data:manageentries', $context)
        ) {
            if (!empty($config->access_debug)) {
                echo "search reject : unapproved resource ";
            }
            return false;
        }

        // Minimum records to view check.
        // Trap if too few records.
        // TODO : report a potential capability lack of : mod/data:viewhiddenentries.
        $recordsamount = $DB->count_records('data_records', ['dataid' => $data->id]);
        if (
            $data->requiredentriestoview > $recordsamount &&
            !has_capability('mod/data:manageentries', $context)
        ) {
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
