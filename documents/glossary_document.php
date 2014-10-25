<?php
/**
 * Global Search Engine for Moodle
 *
 * @package search
 * @category core
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

require_once($CFG->dirroot.'/local/search/documents/document.php');

/**
* a class for representing searchable information
* 
*/
class GlossarySearchDocument extends SearchDocument {
    
    /**
    * document constructor
    *
    */
    public function __construct(&$entry, $course_id, $context_id) {
        global $DB;

        // generic information; required
        $doc = new StdClass;
        $doc->docid     = $entry['id'];
        $doc->documenttype  = SEARCH_TYPE_GLOSSARY;
        $doc->itemtype      = 'standard';
        $doc->contextid     = $context_id;

        $doc->title     = $entry['concept'];
        $doc->date      = $entry['timecreated'];

        if ($entry['userid'])
            $user = $DB->get_record('user', array('id' => $entry['userid']));
        $doc->author    = ($user ) ? $user->firstname.' '.$user->lastname : '';
        $doc->contents  = strip_tags($entry['definition']);
        $doc->url       = glossary_make_link($entry['id']);
        
        // module specific information; optional
        $data = new StdClass;
        $data->glossary = $entry['glossaryid'];

        // construct the parent class
        parent::__construct($doc, $data, $course_id, -1, $entry['userid'], 'mod/'.SEARCH_TYPE_GLOSSARY);
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
    public function __construct(&$entry, $course_id, $context_id) {
        global $DB;
        
        // generic information; required
        $doc->docid     = $entry['id'];
        $doc->documenttype  = SEARCH_TYPE_GLOSSARY;
        $doc->itemtype      = 'comment';
        $doc->contextid     = $context_id;

        $doc->title     = get_string('commenton', 'search') . ' ' . $entry['concept'];
        $doc->date      = $entry['timemodified'];

        if ($entry['userid']) {
            $user = $DB->get_record('user', array('id' => $entry['userid']));
        }
        $doc->author    = ($user) ? fullname($user) : '';
        $doc->contents  = strip_tags($entry['content']);
        $doc->url       = glossary_make_link($entry['entryid']);

        // module specific information; optional
        $data = new Stdclass;
        $data->glossary = $entry['glossaryid'];
        $data->entryid = $entry['entryid'];
        
        // construct the parent class
        parent::__construct($doc, $data, $course_id, -1, $entry['userid'], 'mod/'.SEARCH_TYPE_GLOSSARY);
    } 
}
  
/**
* constructs valid access links to information
* @param entry_id the id of the glossary entry
* @return a full featured link element as a string
*/
function glossary_make_link($entry_id) {
    global $CFG;

    //links directly to entry
    // return $CFG->wwwroot.'/mod/glossary/showentry.php?eid='.$entry_id;

    // TOO LONG URL
    // Suggestion : bounce on popup within the glossarie's showentry page
    // preserve glossary pop-up, be careful where you place your ' and "s
    //this function is meant to return a url that is placed between href='[url here]'
    return new moodle_url('/mod/glossary/showentry.php', array('eid' => $entry_id));
} 

/**
* part of search engine API
*
*/
function glossary_iterator() {
    global $DB;
    $glossaries = $DB->get_records('glossary');
    return $glossaries;
}

/**
* part of search engine API
* @glossary a glossary instance
* @return an array of searchable documents
*/
function glossary_get_content_for_index(&$glossary) {
    // global $DB;

    // get context
    $coursemodule = $DB->get_field('modules', 'id', array('name' => 'glossary'));
    $cm = $DB->get_record('course_modules', array('course' => $glossary->course, 'module' => $coursemodule, 'instance' => $glossary->id));
    $context = context_module::instance($cm->id);

    $documents = array();
    $entryIds = array();
    // index entries
    $entries = $DB->get_records('glossary_entries', array('glossaryid' => $glossary->id));
    if ($entries){
        foreach ($entries as $entry) {
            $concepts[$entry->id] = $entry->concept;
            if (strlen($entry->definition) > 0) {
                $entryIds[] = $entry->id;
                $documents[] = new GlossarySearchDocument(get_object_vars($entry), $glossary->course, $context->id);
            } 
        } 
    }
    
    // index comments
    $glossary_comments = search_get_comments('glossary', $glossaryid);
    if (!empty($glossary_comments)) {
        foreach ($comments as $aComment) {
            if (strlen($aComment->content) > 0) {
                $aComment->concept = $concepts[$comment->itemid]; // get info for title
                $aComment->glossaryid = $glossaryid;
                $aComment->entryid = $comment->itemid;
                $vars = get_object_vars($comment);
                $documents[] = new GlossaryCommentSearchDocument($vars, $glossary->course, $context->id);
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
function glossary_single_document($id, $itemtype) {
    global $DB;
    
    if ($itemtype == 'standard') {
        $entry = $DB->get_record('glossary_entries', array('id' => $id));
    } elseif ($itemtype == 'comment') {
        $comment = $DB->get_record('comments', array('id' => $id));
        $entry = $DB->get_record('glossary_entries', array('id' => $comment->itemid));
    }
    $glossary_course = $DB->get_field('glossary', 'course', array('id' => $entry->glossaryid));
    $coursemodule = $DB->get_field('modules', 'id', array('name' => 'glossary'));
    $cm = $DB->get_record('course_modules', array('course' => $glossary_course, 'module' => $coursemodule, 'instance' => $entry->glossaryid));
    $context = context_module::instance($cm->id);
    if ($itemtype == 'standard') {
        $vars = get_object_vars($entry);
        return new GlossarySearchDocument($vars, $glossary_course, $context->id);
    }
    elseif ($itemtype == 'comment') {
        $vars = get_object_vars($comment);
        return new GlossaryCommentSearchDocument($vars, $glossary_course, $context->id);
    }
}

/**
* dummy delete function that packs id with itemtype.
* this was here for a reason, but I can't remember it at the moment.
*
*/
function glossary_delete($info, $itemtype) {
    $object->id = $info;
    $object->itemtype = $itemtype;
    return $object;
}

/**
* returns the var names needed to build a sql query for addition/deletions
*
*/
function glossary_db_names() {
    //[primary id], [table name], [time created field name], [time modified field name]
    // Getting comments is a complex join extracting info from the global mdl_comments table
    $commentwhere = ' c.contextid = ctx.id AND c.itemid = ge.id AND ctx.instanceid = cm.id AND ctx.contextlevel = 80 AND ge.glossaryid = ctx.instanceid ';
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
* @param int $this_id the item id within the information class denoted by itemtype. In glossaries, this id 
* points out the indexed glossary item.
* @param object $user the user record denoting the user who searches
* @param int $group_id the current group used by the user when searching
* @param int $context_id the current group used by the user when searching
* @return true if access is allowed, false elsewhere
*/
function glossary_check_text_access($path, $itemtype, $this_id, $user, $group_id, $context_id){
    global $CFG, $DB;
    
    // get the glossary object and all related stuff
    $entry = $DB->get_record('glossary_entries', array('id' => $this_id));
    $glossary = $DB->get_record('glossary', array('id' => $entry->glossaryid));
    $context = $DB->get_record('context', array('id' => $context_id));
    $cm = $DB->get_record('course_modules', array('id' => $context->instanceid));
    // $cm = get_coursemodule_from_instance('glossary', $glossary->id, $glossary->course);
    // $context = get_context_instance(CONTEXT_MODULE, $cm->id);

    if (!$cm->visible && !has_capability('moodle/course:viewhiddenactivities', $context)) {
        return false;
    }
    
    //approval check : entries should be approved for being viewed, or belongs to the user unless the viewer can approve them or manage them 
    if (!$entry->approved && $user != $entry->userid && !has_capability('mod/glossary:approve', $context) && !has_capability('mod/glossary:manageentries', $context)) {
        return false;
    }
    
    return true;
}

/**
* post processes the url for cleaner output.
* @param string $title
*/
function glossary_link_post_processing($title){
    global $CFG;

    $config = get_config('block_search');
    
    if (!$config->utf8dir) return $title;

    if ($config->utf8dir > 0) {
        return mb_convert_encoding($title, 'UTF-8', 'auto');
    }
    return mb_convert_encoding($title, 'auto', 'UTF-8');
}