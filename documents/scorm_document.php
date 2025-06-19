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
 * @author Valery Fremaux [valery.fremaux@gmail.com] > 2.0
 * @author Michael Campanis (mchampan) [cynnical@gmail.com], Valery Fremaux [valery.fremaux@gmail.com] > 1.8
 * @contributor Tatsuva Shirai 20090530
 * @date 2008/03/31
 * @license http://www.gnu.org/copyleft/gpl.html GNU Public License
 *
 * document handling for all resources
 * This file contains the mapping between a resource and it's indexable counterpart,
 *
 * Functions for iterating and retrieving the necessary records are now also included
 * in this file, rather than mod/resource/lib.php
 */
namespace local_search;

use \StdClass;
use \context_module;
use \context_course;
use \moodle_url;
use \moodle_exception;

defined('MOODLE_INTERNAL') || die();

require_once($CFG->dirroot.'/local/search/documents/document.php');
require_once($CFG->dirroot.'/local/search/documents/document_wrapper.class.php');

/**
 * a class for representing searchable information
 */
class ScormSearchDocument extends SearchDocument {
    public function __construct(&$scorm, $contextid) {

        // Generic information; required.
        $doc = new StdClass;
        $doc->docid     = $scorm['trueid'];
        $doc->documenttype = SEARCH_TYPE_SCORM;
        $doc->itemtype     = '';
        $doc->contextid    = $contextid;

        $doc->title     = strip_tags($scorm['name']);
        $doc->date      = $scorm['timemodified'];
        $doc->author    = '';
        $doc->contents  = strip_tags($scorm['intro']).' '.strip_tags($scorm['alltext']);
        $doc->url       = resource_document_wrapper::make_link($scorm['id']);

        if (!array_key_exists('alltext', $scorm)) {
            debugging("Empty text");
            throw new moodle_exception(print_r($scorm, true));
        }

        // Module specific information; optional.
        $data = new StdClass;
        $data = array();

        // Construct the parent class.
        parent::__construct($doc, $data, $scorm['course'], 0, 0, 'mod/'.SEARCH_TYPE_SCORM);
    }
}

class scorm_document_wrapper extends document_wrapper {

    /**
     * constructs valid access links to information
     * @param resourceId the of the resource
     * @return a full featured link element as a string
     */
    public static function make_link($instanceid, $contextid = null) {
        return new moodle_url('/mod/scorm/view.php', array('id' => $instanceid));
    }

    /**
     * part of standard API
     *
     */
    public static function get_iterator() {
        /*
         * trick to leave search indexer functionality intact, but allow
         * this document to only use the below function to return info
         * to be searched
         */
        return array(true);
    }

    /**
     * Part of standard API
     * this function does not need a content iterator, returns all the info itself;
     * @param notneeded to comply API, remember to fake the iterator array though
     * @uses CFG
     * @return an array of searchable documents
     */
    public static function get_content_for_index(&$instance) {
        global $DB;

        $config = get_config('local_search');

        // Starting with Moodle native resources.
        $documents = array();
        $sql = "
            SELECT
                id as trueid,
                s.*
            FROM
                {scorm} s
        ";
        if ($scorms = $DB->get_records_sql($sql)) {
            foreach ($scorms as $ascorm) {
                $ascorm->alltext = '';
                $coursemodule = $DB->get_field('modules', 'id', array('name' => 'resource'));
                $params = array('course' => $ascorm->course, 'module' => $coursemodule, 'instance' => $ascorm->id);
                if ($cm = $DB->get_record('course_modules', $params)) {
                    $context = context_module::instance($cm->id);
                    $ascorm->id = $cm->id;

                    $fs = get_file_storage();
                    $hasdocument = !$fs->is_area_empty($context->id, 'mod_scorm', 'content', 0, true);

                    if (empty($config->enable_file_indexing) || !$hasdocument) {
                        // Make a simple document only with DB data.
                        $vars = get_object_vars($ascorm);
                        $documents[] = new ScormSearchDocument($vars, $context->id);
                        mtrace("finished $ascorm->name");
                    } else {
                        $files = $fs->get_area_files($context->id, 'mod_scorm', 'content', 0, true);
                        $file = array_shift($files);
                        search_get_physical_file($documents, $file, $ascorm, $context->id, 'ScormSearchDocument');
                        mtrace("finished scorm content $ascorm->name");
                    }
                }
            }
        }
        return $documents;
    }

    /**
     * part of standard API.
     * returns a single resource search document based on a resource_entry id
     * @param id the id of the accessible document
     * @return a searchable object or null if failure
     */
    public static function single_document($id, $itemtype) {
        global $DB;

        $config = get_config('local_search');

        // Rewriting with legacy moodle databse API.
        $sql = "
            SELECT
               s.id as trueid,
               cm.id as id,
               s.course as course,
               s.name as name,
               s.intro as intro,
               s.timemodified as timemodified
            FROM
                {scorm} s,
                {course_modules} cm,
                {modules} m
            WHERE
                cm.instance = s.id AND
                cm.course = s.course AND
                cm.module = m.id AND
                m.name = 'scorm' AND
                s.id = ?
        ";
        $scorm = $DB->get_record_sql($sql, array($id));

        if ($scorm) {
            $scorm->alltext = '';
            $cm = $DB->get_record('course_modules', array('id' => $scorm->id));
            $context = context_module::instance($cm->id);

            $fs = get_file_storage();

            $hasdocument = !$fs->is_area_empty($context->id, 'mod_scorm', 'content', 0, true);
            $documents = array(); // Foo array.

            if ($hasdocument && @$config->enable_file_indexing) {
                $files = $fs->get_area_files($context->id, 'mod_scorm', 'content', 0, true);
                $file = array_shift($files);
                $void = array();
                $document = search_get_physical_file($void, $file, $scorm, $context->id, 'ScormSearchDocument');
                if (!$document) {
                    mtrace("Warning : this document {$resource->name} will not be indexed");
                }
                return $document;
            } else {
                $vars = get_object_vars($scorm);
                return new ScormSearchDocument($vars, $context->id);
            }
        }
        mtrace("null scorm");
        return null;
    }

    /**
     * returns the var names needed to build a sql query for addition/deletions
     * [primary id], [table name], [time created field name], [time modified field name],
     * [additional where conditions for sql]
     *
     */
    public static function db_names() {
        return array(array('id', 'scorm', 'timemodified', 'timemodified', ''));
    }

    /**
     * this function handles the access policy to contents indexed as searchable documents. If this
     * function does not exist, the search engine assumes access is allowed.
     * @param path the access path to the module script code
     * @param itemtype the information subclassing (usefull for complex modules, defaults to 'standard')
     * @param this_id the item id within the information class denoted by itemtype. In resources, this id
     * points to the resource record and not to the module that shows it.
     * @param user the user record denoting the user who searches
     * @param group_id the current group used by the user when searching
     * @return true if access is allowed, false elsewhere
     */
    public static function check_text_access($path, $itemtype, $thisid, $user, $groupid, $contextid) {
        global $CFG;

        include_once("{$CFG->dirroot}/{$path}/lib.php");

        $r = $DB->get_record('scorm', array('id' => $thisid));
        $modulecontext = $DB->get_record('context', array('id' => $contextid));
        $cm = $DB->get_record('course_modules', array('id' => $modulecontext->instanceid));
        if (empty($cm)) {
            return false; // Shirai 20090530 - MDL19342 - course module might have been delete.
        }
        $coursecontext = context_course::instance($r->course);
        $course = $DB->get_record('course', array('id' => $r->course));

        // Check if course is visible.
        if (!$course->visible && !has_capability('moodle/course:viewhiddencourses', $coursecontext)) {
            return false;
        }

        // Check if user is registered in course or course is open to guests.
        if (!$course->guest && !has_capability('moodle/course:view', $coursecontext)) {
            return false;
        }

        // Check if found course module is visible.
        if (!$cm->visible &&
                !has_capability('moodle/course:viewhiddenactivities', $modulecontext)) {
            return false;
        }

        return true;
    }
}