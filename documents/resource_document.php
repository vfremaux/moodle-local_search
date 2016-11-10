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
 * @author Michael Campanis (mchampan) [cynnical@gmail.com], Valery Fremaux [valery.fremaux@club-internet.fr] > 1.8
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

require_once($CFG->dirroot.'/local/search/documents/document.php');
require_once($CFG->dirroot.'/mod/resource/lib.php');

/**
 * a class for representing searchable information
 */
class ResourceSearchDocument extends SearchDocument {
    public function __construct(&$resource, $context_id) {

        // Generic information; required.
        $doc = new StdClass;
        $doc->docid     = $resource['trueid'];
        $doc->documenttype = SEARCH_TYPE_RESOURCE;
        $doc->itemtype     = 'file';
        $doc->contextid    = $context_id;

        $doc->title     = strip_tags($resource['name']);
        $doc->date      = $resource['timemodified'];
        $doc->author    = '';
        $doc->contents  = strip_tags($resource['intro']).' '.strip_tags($resource['alltext']);
        $doc->url       = resource_make_link($resource['id']);

        // Module specific information; optional.
        $data = new StdClass;
        $data = array();

        // Construct the parent class.
        parent::__construct($doc, $data, $resource['course'], 0, 0, 'mod/'.SEARCH_TYPE_RESOURCE);
    }
}

/**
 * constructs valid access links to information
 * @param resourceId the of the resource 
 * @return a full featured link element as a string
 */
function resource_make_link($resource_id) {
    global $CFG;

    return new moodle_url('/mod/resource/view.php', array('id' => $resource_id));
}

/**
 * part of standard API
 *
 */
function resource_iterator() {
    // trick to leave search indexer functionality intact, but allow
    // this document to only use the below function to return info
    // to be searched
    return array(true);
}

/**
 * Part of standard API
 * this function does not need a content iterator, returns all the info
 * itself;
 * @param notneeded to comply API, remember to fake the iterator array though
 * @uses CFG
 * @return an array of searchable documents
 */
function resource_get_content_for_index(&$notneeded) {
    global $CFG, $DB;

    $config = get_config('local_search');

    // Starting with Moodle native resources.
    $documents = array();
    $query = "
        SELECT 
            id as trueid,
            r.*
        FROM 
            {resource} r
    ";
    if ($resources = $DB->get_records_sql($query)) {
        foreach ($resources as $aResource) {
            $coursemodule = $DB->get_field('modules', 'id', array('name' => 'resource'));
            if ($cm = $DB->get_record('course_modules', array('course' => $aResource->course, 'module' => $coursemodule, 'instance' => $aResource->id))) {
                $context = context_module::instance($cm->id);
                $aResource->id = $cm->id;
                $aResource->alltext = '';

                $fs = get_file_storage();
                $hasdocument = !$fs->is_area_empty($context->id, 'mod_resource', 'content', 0, true);

                if (empty($config->enable_file_indexing) || !$hasdocument) {
                    // make a simple document only with DB data
                    $vars = get_object_vars($aResource);
                    $documents[] = new ResourceSearchDocument($vars, $context->id);
                    mtrace("finished $aResource->name");
                } else {
                    $files = $fs->get_area_files($context->id, 'mod_resource', 'content', 0, true);
                    $file = array_shift($files);
                    search_get_physical_file($documents, $file, $aResource, $context->id, 'ResourceSearchDocument');
                    mtrace("finished physical $aResource->name");
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
function resource_single_document($id, $itemtype) {
    global $CFG, $DB;

    $config = get_config('local_search');

    // Rewriting with legacy moodle databse API.
    $query = "
        SELECT 
           r.id as trueid,
           cm.id as id,
           r.course as course,
           r.name as name,
           r.intro as intro,
           r.timemodified as timemodified
        FROM 
            {resource} r,
            {course_modules} cm,
            {modules} m
        WHERE 
            cm.instance = r.id AND
            cm.course = r.course AND
            cm.module = m.id AND
            m.name = 'resource' AND
            r.id = ?
    ";
    $resource = $DB->get_record_sql($query, array($id));

    if ($resource) {
        $coursemodule = $DB->get_field('modules', 'id', array('name' => 'resource'));
        $cm = $DB->get_record('course_modules', array('id' => $resource->id));
        $context = context_module::instance($cm->id);

        $fs = get_file_storage();

        $hasdocument = !$fs->is_area_empty($context->id, 'mod_resource', 'content', 0, true);
        $documents = array(); // foo array

        if ($hasdocument && @$config->enable_file_indexing) {
            $files = $fs->get_area_files($context->id, 'mod_resource', 'content', 0, true);
            $file = array_shift($files);
            $document = search_get_physical_file($documents, $file, $resource, 'ResourceSearchDocument');
            if (!$document) {
                mtrace("Warning : this document {$resource->name} will not be indexed");
            }
            return $document;
        } else {
            $vars = get_object_vars($resource);
            return new ResourceSearchDocument($vars, $context->id);
        }
    }
    mtrace("null resource");
    return null;
}

/**
 * dummy delete function that aggregates id with itemtype.
 * this was here for a reason, but I can't remember it at the moment.
 *
 */
function resource_delete($info, $itemtype) {
    $object->id = $info;
    $object->itemtype = $itemtype;
    return $object;
}

/**
 * returns the var names needed to build a sql query for addition/deletions
 *
 */
function resource_db_names() {
    //[primary id], [table name], [time created field name], [time modified field name], [additional where conditions for sql]
    return array(array('id', 'resource', 'timemodified', 'timemodified', ''));
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
function resource_check_text_access($path, $itemtype, $this_id, $user, $group_id, $context_id) {
    global $CFG;

    include_once("{$CFG->dirroot}/{$path}/lib.php");

    $r = $DB->get_record('resource', array('id' => $this_id));
    $module_context = $DB->get_record('context', array('id' => $context_id));
    $cm = $DB->get_record('course_modules', array('id' => $module_context->instanceid));
    if (empty($cm)) {
        return false; // Shirai 20090530 - MDL19342 - course module might have been delete
    }
    $course_context = context_course::instance($r->course);
    $course = $DB->get_record('course', array('id' => $r->course));

    // Check if course is visible.
    if (!$course->visible && !has_capability('moodle/course:viewhiddencourses', $course_context)) {
        return false;
    }

    // Check if user is registered in course or course is open to guests.
    if (!$course->guest && !has_capability('moodle/course:view', $course_context)) {
        return false;
    }

    // Check if found course module is visible.
    if (!$cm->visible && !has_capability('moodle/course:viewhiddenactivities', $module_context)){
        return false;
    }

    return true;
}

/**
 * post processes the url for cleaner output.
 * @param string $title
 */
function resource_link_post_processing($title) {
    global $CFG;
    
    return $title;

    /*
    $conf = get_config('local_search');
    
    if (!$conf->utf8dir) return $title;
    
    if ($conf->utf8dir > 0) {
        return mb_convert_encoding($title, 'UTF-8', 'auto');
    }
    return mb_convert_encoding($title, 'auto', 'UTF-8');
    */
}
