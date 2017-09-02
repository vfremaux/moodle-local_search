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
 * @author Valery Fremaux [valery.fremaux@club-internet.fr] > 1.9
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
use \moodle_url;
use \context_module;
use \context_course;

defined('MOODLE_INTERNAL') || die();

require_once($CFG->dirroot.'/local/search/documents/document.php');
require_once($CFG->dirroot.'/local/search/documents/document_wrapper.class.php');
require_once($CFG->dirroot.'/mod/resource/lib.php');

/**
 * a class for representing searchable information
 *
 */
class LabelSearchDocument extends SearchDocument {

    public function __construct(&$label, $contextid) {

        // Generic information; required.
        $doc = new StdClass;
        $doc->docid     = $label['id'];
        $doc->documenttype = SEARCH_TYPE_LABEL;
        $doc->itemtype     = 'label';
        $doc->contextid    = $contextid;

        $doc->title     = strip_tags($label['name']);
        $doc->date      = $label['timemodified'];
        $doc->author    = '';
        $doc->contents  = strip_tags($label['name']);
        $doc->url       = label_document_wrapper::make_link($label['course']);

        // Module specific information; optional.
        $data = new StdClass;
        $data = array();

        // Construct the parent class.
        parent::__construct($doc, $data, $label['course'], 0, 0, 'mod/'.SEARCH_TYPE_LABEL);
    }
}

class label_document_wrapper extends document_wrapper {

    /**
     * constructs valid access links to information
     * @param resourceId the of the resource
     * @return a full featured link element as a string
     */
    public static function make_link($instanceid) {
        return new moodle_url('/course/view.php', array('id' => $instanceid));
    }

    /**
     * part of standard API
     *
     */
    public static function get_iterator() {
        global $DB;

        /* Trick to leave search indexer functionality intact, but allow
         * this document to only use the below function to return info
         * to be searched
         */
        $labels = $DB->get_records('label');
        return $labels;
    }

    /**
     * part of standard API
     * this function does not need a content iterator, returns all the info itself;
     * @param notneeded to comply API, remember to fake the iterator array though
     * @return an array of searchable documents
     */
    public static function get_content_for_index(&$label) {
        global $DB;

        // Starting with Moodle native resources.
        $documents = array();

        $coursemodule = $DB->get_field('modules', 'id', array('name' => 'label'));
        $params = array('course' => $label->course, 'module' => $coursemodule, 'instance' => $label->id);
        $cm = $DB->get_record('course_modules', $params);
        $context = context_module::instance($cm->id);

        $obj = get_object_vars($label);
        $documents[] = new LabelSearchDocument($obj, $context->id);

        mtrace("finished label {$label->id}");
        return $documents;
    }

    /**
     * part of standard API.
     * returns a single resource search document based on a label id
     * @param id the id of the accessible document
     * @return a searchable object or null if failure
     */
    public static function single_document($id, $itemtype) {
        global $DB;

        $label = $DB->get_record('label', array('id' => $id));

        if ($label) {
            $cm = $DB->get_record('course_modules', array('id' => $label->id));
            $context = context_module::instance($cm->id);
            $arr = get_object_vars($label);
            return new LabelSearchDocument($arr, $context->id);
        }
        return null;
    }

    /**
     * returns the var names needed to build a sql query for addition/deletions
     */
    public static function db_names() {
        /*
         * [primary id], [table name], [time created field name], [time modified field name], [docsubtype],
         * [additional where conditions for sql]
         */
        return array(array('id', 'label', 'timemodified', 'timemodified', 'label', ''));
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
        global $DB;

        $r = $DB->get_record('label', array('id' => $thisid));
        $modulecontext = $DB->get_record('context', array('id' => $contextid));
        $cm = $DB->get_record('course_modules', 'id', $modulecontext->instanceid);
        if (empty($cm)) {
            return false; // Shirai 20093005 - MDL19342 - course module might have been delete.
        }

        $coursecontext = context_course::instance($r->course);

        // Check if englobing course is visible.
        if (!has_capability('moodle/course:view', $coursecontext)) {
            return false;
        }

        // Check if found course module is visible.
        if (!$cm->visible && !has_capability('moodle/course:viewhiddenactivities', $modulecontext)) {
            return false;
        }

        return true;
    }
}