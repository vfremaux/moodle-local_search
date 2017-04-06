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
 * document handling for all pages
 * This file contains the mapping between a page and it's indexable counterpart,
 *
 * Functions for iterating and retrieving the necessary records are now also included
 * in this file, rather than mod/page/lib.php
 */
defined('MOODLE_INTERNAL') || die();

require_once($CFG->dirroot.'/local/search/documents/document.php');

/**
 * a class for representing searchable information
 *
 */
class PageSearchDocument extends SearchDocument {

    public function __construct(&$page, $contextid) {

        // Generic information; required.
        $doc = new StdClass;
        $doc->docid         = $page['trueid'];
        $doc->documenttype  = SEARCH_TYPE_PAGE;
        $doc->itemtype      = 'page';
        $doc->contextid     = $contextid;

        $doc->title     = strip_tags($page['name']);
        $doc->date      = $page['timemodified'];
        $doc->author    = '';
        $doc->contents  = strip_tags($page['intro']).' '.strip_tags($page['content']);
        $doc->url       = page_make_link($page['id']);

        // Module specific information; optional.
        $data = new StdClass;
        $data = array();

        // Construct the parent class.
        parent::__construct($doc, $data, $page['course'], 0, 0, 'mod/'.SEARCH_TYPE_PAGE);
    }
}

/**
 * constructs valid access links to information
 * @param pageId the of the page
 * @return a full featured link element as a string
 */
function page_make_link($pageid) {

    return new moodle_url('/mod/page/view.php', array('id' => $pageid));
}

/**
 * part of standard API
 *
 */
function page_iterator() {
    global $DB;

    return $DB->get_records('page');
}

/**
 * part of standard API
 * this function does not need a content iterator, returns all the info itself;
 * @param notneeded to comply API, remember to fake the iterator array though
 * @return an array of searchable documents
 */
function page_get_content_for_index(&$notneeded) {
    global $CFG, $DB;

    // Starting with Moodle native pages.
    $documents = array();

    $sql = "
        SELECT
            id as trueid,
            p.*
        FROM
            {page} p
    ";
    if ($pages = $DB->get_records_sql($sql)) {
        foreach ($pages as $apage) {
            $coursemodule = $DB->get_field('modules', 'id', array('name' => 'page'));
            $params = array('course' => $apage->course, 'module' => $coursemodule, 'instance' => $apage->id);
            if ($cm = $DB->get_record('course_modules', $params)) {
                $context = context_module::instance($cm->id);
                $apage = new StdClass;
                $apage->id = $cm->id;
                $apage->alltext = '';
                $vars = get_object_vars($apage);
                $documents[] = new PageSearchDocument($vars, $context->id);
                mtrace("finished $apage->name");
            }
        }
    }
    return $documents;
}

/**
 * part of standard API.
 * returns a single page search document based on a page_entry id
 * @param id the id of the accessible document
 * @return a searchable object or null if failure
 */
function page_single_document($id, $itemtype) {
    global $CFG, $DB;

    // Rewriting with legacy moodle databse API.
    $sql = "
        SELECT
           r.id as trueid,
           cm.id as id,
           p.course as course,
           p.name as name,
           p.intro as intro,
           p.content as content,
           p.timemodified as timemodified
        FROM
            {page} p,
            {course_modules} cm,
            {modules} m
        WHERE
            cm.instance = p.id AND
            cm.course = p.course AND
            cm.module = m.id AND
            m.name = 'page' AND
            ((p.type != 'file' AND
            p.content != '' AND
            p.content != ' ' AND
            p.content != '&nbsp;') OR
            p.id = ?
    ";
    $page = $DB->get_record_sql($sql, array($id));

    if ($page) {
        $coursemodule = $DB->get_field('modules', 'id', array('name' => 'page'));
        $cm = $DB->get_record('course_modules', array('id' => $page->id));
        $context = context_module::instance($cm->id);
        $vars = get_object_vars($label);
        return new PageSearchDocument($vars, $context->id);
    }
    mtrace("no pages");
    return null;
}

/**
 * dummy delete function that aggregates id with itemtype.
 * this was here for a reason, but I can't remember it at the moment.
 *
 */
function page_delete($info, $itemtype) {
    $object = new StdClass;
    $object->id = $info;
    $object->itemtype = $itemtype;
    return $object;
}

/**
 * returns the var names needed to build a sql query for addition/deletions
 *
 */
function page_db_names() {
    /*
     * [primary id], [table name], [time created field name], [time modified field name],
     * [additional where conditions for sql]
     */
    $select = " (content != '' AND content != ' ' AND content != '&nbsp;') ";
    return array(array('id', 'page', 'timemodified', 'timemodified', 'any', $select));
}

/**
 * this function handles the access policy to contents indexed as searchable documents. If this
 * function does not exist, the search engine assumes access is allowed.
 * @param path the access path to the module script code
 * @param itemtype the information subclassing (usefull for complex modules, defaults to 'standard')
 * @param this_id the item id within the information class denoted by itemtype. In pages, this id
 * points to the page record and not to the module that shows it.
 * @param user the user record denoting the user who searches
 * @param group_id the current group used by the user when searching
 * @return true if access is allowed, false elsewhere
 */
function page_check_text_access($path, $itemtype, $thisid, $user, $groupid, $contextid) {
    global $CFG;

    include_once("{$CFG->dirroot}/{$path}/lib.php");

    $p = $DB->get_record('page', array('id' => $thisid));
    $modulecontext = $DB->get_record('context', array('id' => $contextid));
    $cm = $DB->get_record('course_modules', array('id' => $modulecontext->instanceid));

    if (empty($cm)) {
        return false; // Shirai 20090530 - MDL19342 - course module might have been deleted.
    }

    $coursecontext = context_course::instance($p->course);
    $course = $DB->get_record('course', array('id' => $p->course));

    // Check if course is visible.
    if (!$course->visible && !has_capability('moodle/course:viewhiddencourses', $coursecontext)) {
        return false;
    }

    // Check if user is registered in course or course is open to guests.
    if (!$course->guest && !has_capability('moodle/course:view', $coursecontext)) {
        return false;
    }

    // Check if found course module is visible.
    if (!$cm->visible && !has_capability('moodle/course:viewhiddenactivities', $modulecontext)) {
        return false;
    }

    return true;
}

/**
 * TODO : check if this is deprecated.
 * post processes the url for cleaner output.
 * @param string $title
 */
function page_link_post_processing($title) {
    global $CFG;

    if ($CFG->block_search_utf8dir) {
        return mb_convert_encoding($title, 'UTF-8', 'auto');
    }
    return mb_convert_encoding($title, 'auto', 'UTF-8');
}
