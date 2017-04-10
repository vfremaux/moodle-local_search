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
 * @date 2008/03/31
 * @license http://www.gnu.org/copyleft/gpl.html GNU Public License
 *
 * document handling for glossary activity module
 * This file contains a mapping between a glossary entry and it's indexable counterpart,
 *
 * Functions for iterating and retrieving the necessary records are now also included
 * in this file, rather than mod/glossary/lib.php
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

/**
 * a class for representing searchable information
 *
 */
class GlossarySearchDocument extends SearchDocument {

    /**
     * document constructor
     *
     */
    public function __construct(&$entry, $courseid, $contextid) {
        global $DB;

        // Generic information; required.
        $doc = new StdClass;
        $doc->docid     = $entry['id'];
        $doc->documenttype  = SEARCH_TYPE_GLOSSARY;
        $doc->itemtype      = 'standard';
        $doc->contextid     = $contextid;

        $doc->title     = $entry['concept'];
        $doc->date      = $entry['timecreated'];

        if ($entry['userid']) {
            $user = $DB->get_record('user', array('id' => $entry['userid']));
        }
        $doc->author    = ($user ) ? $user->firstname.' '.$user->lastname : '';
        $doc->contents  = strip_tags($entry['definition']);
        $doc->url       = glossary_document_wrapper::make_link($entry['id']);

        // Module specific information; optional.
        $data = new StdClass;
        $data->glossary = $entry['glossaryid'];

        // Construct the parent class.
        parent::__construct($doc, $data, $courseid, -1, $entry['userid'], 'mod/'.SEARCH_TYPE_GLOSSARY);
    }
}

/**
 * a class for representing searchable information
 * comments are stroed into the gneeral mdl_comments table
 */
class GlossaryCommentSearchDocument extends SearchDocument {

    /**
     * document constructor
     */
    public function __construct(&$entry, $courseid, $contextid) {
        global $DB;

        // Generic information; required.
        $doc = new StdClass();
        $doc->docid         = $entry['id'];
        $doc->documenttype  = SEARCH_TYPE_GLOSSARY;
        $doc->itemtype      = 'comment';
        $doc->contextid     = $contextid;

        $doc->title     = get_string('commenton', 'search').' '.$entry['concept'];
        $doc->date      = $entry['timemodified'];

        if ($entry['userid']) {
            $user = $DB->get_record('user', array('id' => $entry['userid']));
        }
        $doc->author    = ($user) ? fullname($user) : '';
        $doc->contents  = strip_tags($entry['content']);
        $doc->url       = glossary_document_wrapper::make_link($entry['entryid']);

        // Module specific information; optional.
        $data = new Stdclass;
        $data->glossary = $entry['glossaryid'];
        $data->entryid = $entry['entryid'];

        // Construct the parent class.
        parent::__construct($doc, $data, $courseid, -1, $entry['userid'], 'mod/'.SEARCH_TYPE_GLOSSARY);
    }
}

class glossary_document_wrapper extends document_wrapper {

    /**
     * constructs valid access links to information
     * @param entry_id the id of the glossary entry
     * @return a full featured link element as a string
     */
    public static function make_link($instanceid) {
        return new moodle_url('/mod/glossary/showentry.php', array('eid' => $instanceid));
    }

    /**
     * part of search engine API
     *
     */
    public static function get_iterator() {
        global $DB;

        $glossaries = $DB->get_records('glossary');
        return $glossaries;
    }

    /**
     * part of search engine API
     * @glossary a glossary instance
     * @return an array of searchable documents
     */
    public static function get_content_for_index(&$instance) {
        global $DB;

        // Get context.
        $coursemodule = $DB->get_field('modules', 'id', array('name' => 'glossary'));
        $params = array('course' => $instance->course, 'module' => $coursemodule, 'instance' => $instance->id);
        $cm = $DB->get_record('course_modules', $params);
        $context = context_module::instance($cm->id);

        $documents = array();
        $entryids = array();

        // Index entries.
        $entries = $DB->get_records('glossary_entries', array('glossaryid' => $instance->id));
        if ($entries) {
            foreach ($entries as $entry) {
                $concepts[$entry->id] = $entry->concept;
                if (strlen($entry->definition) > 0) {
                    $entryids[] = $entry->id;
                    $arr = get_object_vars($entry);
                    $documents[] = new GlossarySearchDocument($arr, $instance->course, $context->id);
                }
            }
        }

        // Index comments.
        $glossarycomments = search_get_comments('glossary', $instance->id);
        if (!empty($glossarycomments)) {
            foreach ($glossarycomments as $acomment) {
                if (strlen($acomment->content) > 0) {
                    $acomment->concept = $concepts[$acomment->itemid]; // Get info for title.
                    $acomment->glossaryid = $instance->id;
                    $acomment->entryid = $acomment->itemid;
                    $acomment->timemodified = $acomment->timecreated;
                    $vars = get_object_vars($acomment);
                    $documents[] = new GlossaryCommentSearchDocument($vars, $instance->course, $context->id);
                }
            }
        }
        return $documents;
    }

    /**
     * part of search engine API
     * @param id the glossary entry identifier
     * @itemtype the type of information
     * @return a single search document based on a glossary entry
     */
    public static function single_document($id, $itemtype) {
        global $DB;

        if ($itemtype == 'standard') {
            $entry = $DB->get_record('glossary_entries', array('id' => $id));
        } else if ($itemtype == 'comment') {
            $comment = $DB->get_record('comments', array('id' => $id));
            $entry = $DB->get_record('glossary_entries', array('id' => $comment->itemid));
        }
        $glossarycourse = $DB->get_field('glossary', 'course', array('id' => $entry->glossaryid));
        $coursemodule = $DB->get_field('modules', 'id', array('name' => 'glossary'));
        $params = array('course' => $glossarycourse, 'module' => $coursemodule, 'instance' => $entry->glossaryid);
        $cm = $DB->get_record('course_modules', $params);
        $context = context_module::instance($cm->id);
        if ($itemtype == 'standard') {
            $vars = get_object_vars($entry);
            return new GlossarySearchDocument($vars, $glossarycourse, $context->id);
        } else if ($itemtype == 'comment') {
            $vars = get_object_vars($comment);
            return new GlossaryCommentSearchDocument($vars, $glossarycourse, $context->id);
        }
    }

    /**
     * returns the var names needed to build a sql query for addition/deletions
     * [primary id], [table name], [time created field name], [time modified field name].
     */
    public static function db_names() {
        $commentwhere = '
            c.contextid = ctx.id AND
            c.itemid = ge.id AND
            ctx.instanceid = cm.id AND
            ctx.contextlevel = 80 AND
            ge.glossaryid = ctx.instanceid
        ';
        $commentjoinextension = ' c ,{glossary_entries} ge, {course_modules} cm, {context} ctx ';

        return array(
            array('id', 'glossary_entries', 'timecreated', 'timemodified', 'standard'),
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
     * @param string $path the access path to the module script code
     * @param string $itemtype the information subclassing (usefull for complex modules, defaults to 'standard')
     * @param int $thisid the item id within the information class denoted by itemtype. In glossaries, this id
     * points out the indexed glossary item.
     * @param object $user the user record denoting the user who searches
     * @param int $groupid the current group used by the user when searching
     * @param int $contextid the current group used by the user when searching
     * @return true if access is allowed, false elsewhere
     */
    public static function check_text_access($path, $itemtype, $thisid, $user, $groupid, $contextid) {
        global $DB;

        // Get the glossary object and all related stuff.
        $entry = $DB->get_record('glossary_entries', array('id' => $thisid));
        $context = $DB->get_record('context', array('id' => $contextid));
        $cm = $DB->get_record('course_modules', array('id' => $context->instanceid));

        if (!$cm->visible && !has_capability('moodle/course:viewhiddenactivities', $context)) {
            return false;
        }

        // Approval check : entries should be approved for being viewed, or belongs to the user unless the viewer can approve them or manage them.
        if (!$entry->approved &&
                $user != $entry->userid &&
                        !has_capability('mod/glossary:approve', $context) &&
                                !has_capability('mod/glossary:manageentries', $context)) {
            return false;
        }

        return true;
    }
}