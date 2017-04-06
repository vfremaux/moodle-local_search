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
defined('MOODLE_INTERNAL') || die();

require_once($CFG->dirroot.'/local/search/documents/document.php');

/**
 * a class for representing searchable information
 *
 */
class BookPageSearchDocument extends SearchDocument {

    /**
     * document constructor
     *
     */
    public function __construct(&$entry, $courseid, $contextid) {
        global $DB;

        // Generic information; required.
        $doc = new StdClass;
        $doc->docid     = $entry['id'];
        $doc->documenttype  = SEARCH_TYPE_BOOK;
        $doc->itemtype      = 'standard';
        $doc->contextid     = $contextid;

        $doc->title     = $entry['title'];
        $doc->date      = $entry['timecreated'];

        $doc->author    = '';
        $doc->contents  = strip_tags($entry['content']);
        $doc->url       = book_make_link($entry['id'], $entry['bookid']);

        // Module specific information; optional.
        $data = new StdClass;
        $data->book = $entry['bookid'];

        // Construct the parent class.
        parent::__construct($doc, $data, $courseid, -1, 0, 'mod/'.SEARCH_TYPE_BOOK);
    }
}

/**
 * constructs valid access links to information
 * @param entry_id the id of the book entry
 * @return a full featured link element as a string
 */
function book_make_link($entryid, $bookid) {
    global $CFG;

    //links directly to entry
    // return $CFG->wwwroot.'/mod/glossary/showentry.php?eid='.$entryid;

    // TOO LONG URL
    // Suggestion : bounce on popup within the glossarie's showentry page
    // preserve glossary pop-up, be careful where you place your ' and "s
    //this function is meant to return a url that is placed between href='[url here]'
    return new moodle_url('/mod/book/showentry.php', array('chapterid' => $entryid, 'bid' => $bookid));
}

/**
 * part of search engine API
 *
 */
function book_iterator() {
    global $DB;

    $books = $DB->get_records('book');
    return $books;
}

/**
 * part of search engine API
 * @book a glossary instance
 * @return an array of searchable documents
 */
function book_get_content_for_index(&$book) {
    global $DB;

    // Get context.
    $coursemodule = $DB->get_field('modules', 'id', array('name' => 'book'));
    $params = array('course' => $book->course, 'module' => $coursemodule, 'instance' => $book->id);
    $cm = $DB->get_record('course_modules', $params);
    $context = context_module::instance($cm->id);

    $documents = array();
    $entryids = array();

    // Index entries.
    $entries = $DB->get_records('book_chapters', array('bookid' => $book->id));
    if ($entries) {
        foreach ($entries as $entry) {
            if (strlen($entry->content) > 0) {
                $arr = get_object_vars($entry);
                $documents[] = new BookPageSearchDocument($arr, $book->course, $context->id);
            } 
        } 
    }

    return $documents;
}

/**
 * part of search engine API
 * @param id the book entry identifier
 * @itemtype the type of information
 * @return a single search document based on a glossary entry
 */
function book_single_document($id, $itemtype) {
    global $DB;

    $entry = $DB->get_record('book_chapter', array('id' => $id));
    $book_course = $DB->get_field('book', 'course', array('id' => $entry->bookid));
    $coursemodule = $DB->get_field('modules', 'id', array('name' => 'book'));
    $params = array('course' => $book_course, 'module' => $coursemodule, 'instance' => $entry->bookid);
    $cm = $DB->get_record('course_modules', $params);
    $context = context_module::instance($cm->id);
    $vars = get_object_vars($entry);
    return new BookPageSearchDocument($vars, $book_course, $context->id);
}

/**
 * dummy delete function that packs id with itemtype.
 * this was here for a reason, but I can't remember it at the moment.
 *
 */
function book_delete($info, $itemtype) {
    $object->id = $info;
    $object->itemtype = $itemtype;
    return $object;
}

/**
 * returns the var names needed to build a sql query for addition/deletions
 *
 */
function book_db_names() {
    //[primary id], [table name], [time created field name], [time modified field name]
    // Getting comments is a complex join extracting info from the global mdl_comments table

    return array(
        array('id', 'book_chapters', 'timecreated', 'timemodified', 'standard'),
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
function book_check_text_access($path, $itemtype, $thisid, $user, $groupid, $contextid) {
    global $CFG, $DB;

    // Get the glossary object and all related stuff.
    $entry = $DB->get_record('glossary_entries', array('id' => $thisid));
    $glossary = $DB->get_record('glossary', array('id' => $entry->bookid));
    $context = $DB->get_record('context', array('id' => $contextid));
    $cm = $DB->get_record('course_modules', array('id' => $context->instanceid));

    if (!$cm->visible && !has_capability('moodle/course:viewhiddenactivities', $context)) {
        return false;
    }

    return true;
}

/**
 * post processes the url for cleaner output.
 * @param string $title
 */
function book_link_post_processing($title) {
    global $CFG;

    return $title;
}
