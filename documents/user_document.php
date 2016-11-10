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
 * special (EXTRA) document handling for user related data
 */

require_once($CFG->dirroot.'/local/search/documents/document.php');
require_once($CFG->dirroot.'/blog/lib.php');

/**
 * A class for representing searchable information in user metadata.
 */
class UserSearchDocument extends SearchDocument {

    /**
    * Constructor.
    */
    public function __construct(&$userhash, $user_id, $context_id) {
        global $DB;

        // Generic information; required.
        $doc = new StdClass;
        $doc->docid         = $userhash['id'];
        $doc->documenttype  = SEARCH_TYPE_USER;
        $doc->itemtype      = 'user';
        $doc->contextid     = $context_id;

        $user = $DB->get_record('user', array('id' => $user_id));
        $doc->title         = get_string('user').': '.fullname($user);
        $doc->date          = ($userhash['lastaccess']) ? $userhash['lastaccess'] : time() ;

        // Remove '(ip.ip.ip.ip)' from chat author list.
        $doc->author        = $user->id;
        $doc->contents      = $userhash['description'];
        $doc->url           = user_make_link($user_id, 'user');

        // Module specific information; optional.

        /* construct the parent class
         * Shirai : User pictures are not displayed in results of blogs (2009/05/29) MDL19341
         */
        parent::__construct($doc, $data, 0, 0, $user_id, PATH_FOR_SEARCH_TYPE_USER);
    }
}

/**
 * A class for representing searchable information in user metadata
 *
 */
class UserPostSearchDocument extends SearchDocument {

    /**
     * Constructor.
     */
    public function __construct(&$post, $user_id, $context_id) {
        global $DB;

        // Generic information; required.
        $doc = new StdClass;
        $doc->docid         = $post['id'];
        $doc->documenttype  = SEARCH_TYPE_USER;
        $doc->itemtype      = 'post';
        $doc->contextid     = $context_id;

        $user = $DB->get_record('user', array('id' => $user_id));

        // We cannot call userdate with relevant locale at indexing time.
        // $doc->title         = get_string('post').': '.fullname($user);
        $doc->title         = $post['subject'];
        $doc->date          = $post['created'];

        // Remove '(ip.ip.ip.ip)' from chat author list.
        $doc->author        = fullname($user);
        $doc->contents      = $post['description'];
        // $doc->url           = user_make_link($user_id, 'post');
        $doc->url           = user_make_link($post['id'], 'post');

        // Module specific information; optional.

        // Construct the parent class.
        parent::__construct($doc, $data, 0, 0, $user_id, PATH_FOR_SEARCH_TYPE_USER);
    } 
}

/**
 * a class for representing searchable information in user metadata
 */
class UserBlogAttachmentSearchDocument extends SearchDocument {

    /**
     * constructor
     */
    public function __construct(&$post, $context_id) {
        global $DB;

        // Generic information; required.
        $doc = new StdClass;
        $doc->docid         = $post['id'];
        $doc->documenttype  = SEARCH_TYPE_USER;
        $doc->itemtype      = 'attachment';
        $doc->contextid     = $context_id;

        $user = $DB->get_record('user', array('id' => $post['userid']));

        // We cannot call userdate with relevant locale at indexing time.
        $doc->title         = get_string('file').' : '.$post['subject'];
        $doc->date          = $post['created'];

        // Remove '(ip.ip.ip.ip)' from chat author list.
        $doc->author        = fullname($user);
        $doc->contents      = $post['alltext'];
        $doc->url           = user_make_link($post['id'], 'attachment');

        // Module specific information; optional.

        // Construct the parent class.
        parent::__construct($doc, $data, 0, 0, $post['userid'], PATH_FOR_SEARCH_TYPE_USER);
    } 
}


/**
 * constructs a valid link to a user record
 * @param userid the user
 * @param itemtype 
 * @uses CFG
 * @return a well formed link to user information
 */
function user_make_link($itemid, $itemtype) {
    global $CFG, $DB;

    if ($itemtype == 'user') {
        return new moodle_url('/user/view.php', array('id' => $itemid));
    } elseif ($itemtype == 'post') {
        return new moodle_url('/blog/index.php', array('postid' => $itemid));
    } elseif ($itemtype == 'attachment') {
        $post = $DB->get_record('post', array('id' => $itemid));
        // TODO : change to pluginfile
        /*
        if (!$CFG->slasharguments){
            return $CFG->wwwroot."/file.php?file=/blog/attachments/{$post->id}/{$post->attachment}";
        } else {
            return $CFG->wwwroot."/file.php/blog/attachments/{$post->id}/{$post->attachment}";
        }
        */
    } else {
        return null;
    }
}

/**
 * part of search engine API
 *
 */
function user_iterator() {
    global $DB;

    $users = $DB->get_records('user');
    return $users;
}

/**
 * part of search engine API
 * @uses CFG
 * @return an array of documents generated from data
 */
function user_get_content_for_index(&$user) {
    global $CFG, $DB;

    $documents = array();
    $config = get_config('local_search');

    $userhash = get_object_vars($user);
    $documents[] = new UserSearchDocument($userhash, $user->id, null);

    if ($posts = $DB->get_records('post', array('userid' => $user->id), 'created')) {
        foreach ($posts as $post) {
            $texts = array();
            $texts[] = $post->subject;
            $texts[] = $post->summary;
            $texts[] = $post->content;
            $post->description = implode(" ", $texts);

            // record the attachment if any and physical files can be indexed
            if (@$config->enable_file_indexing) {
                if ($post->attachment) {
                    user_get_physical_file($post, null, false, $documents);
                }
            }

            $posthash = get_object_vars($post);
            $documents[] = new UserPostSearchDocument($posthash, $user->id, null);
        }
    }
    return $documents;
}

/**
 * get text from a physical file 
 * @param object $post a post to whech the file is attached to 
 * @param boolean $context_id if in future we need recording a context along with the search document, pass it here
 * @param boolean $getsingle if true, returns a single search document, elsewhere return the array
 * given as documents increased by one
 * @param array $documents the array of documents, by ref, where to add the new document.
 * @return a search document when unique or false.
 */
function user_get_physical_file(&$post, $context_id, $getsingle, &$documents = null) {
    global $CFG;

    $config = get_config('local_search');

    // Cannot index empty references.
    if (empty($post->attachment)) {
        mtrace("Cannot index, empty reference.");
        return false;
    }

    $fileparts = pathinfo($post->attachment);
    // Cannot index unknown or masked types.
    if (empty($fileparts['extension'])) {
        mtrace("Cannot index without explicit extension.");
        return false;
    }

    // Cannot index non existent file.
    $file = "{$CFG->dataroot}/blog/attachments/{$post->id}/{$post->attachment}";
    if (!file_exists($file)){
        mtrace("Missing attachment file $file : will not be indexed.");
        return false;
    }

    $ext = strtolower($fileparts['extension']);

    // Cannot index unallowed or unhandled types.
    if (!preg_match("/\b$ext\b/i", $config->filetypes)) {
        mtrace($fileparts['extension'] . ' is not an allowed extension for indexing');
        return false;
    }

    if (file_exists($CFG->dirroot.'/search/documents/physical_'.$ext.'.php')) {
        include_once($CFG->dirroot.'/search/documents/physical_'.$ext.'.php');
        $function_name = 'get_text_for_indexing_'.$ext;
        $directfile = "blog/attachments/{$post->id}/{$post->attachment}";
        $post->alltext = $function_name($post, $directfile);
        if (!empty($post->alltext)){
            if ($getsingle){
                $posthash = get_object_vars($post);
                $single = new UserBlogAttachmentSearchDocument($posthash, $context_id);
                mtrace("finished attachment {$post->attachment} in {$post->title}");
                return $single;
            } else {
                $posthash = get_object_vars($post);
                $documents[] = new UserBlogAttachmentSearchDocument($posthash, $context_id);
            }
            mtrace("finished attachment {$post->attachment} in {$post->subject}");
        }
    } else {
        mtrace("fulltext handler not found for $ext type");
    }
    return false;
}

/**
 * returns a single user search document 
 * @param composite $id a unique document id made with 
 * @param itemtype the type of information (session is the only type)
 */
function user_single_document($id, $itemtype) {
    global $DB;

    $config = get_config('local_search');

    if ($itemtype == 'user') {
        if ($user = $DB->get_record('user', array('id' =>$id))){
            $userhash = get_object_vars($user);
            return new UserSearchDocument($userhash, $user->id, 'user', null);
        }
    } elseif ($itemtype == 'post') {
        if ($post = $DB->get_records('post', array('id' => $id))){
            $texts = array();
            $texts[] = $post->subject;
            $texts[] = $post->summary;
            $texts[] = $post->content;
            $post->description = implode(" ", $texts);
            $posthash = get_object_vars($post);
            return new UserPostSearchDocument($posthash, $user->id, 'post', null);
        }
    } elseif ($itemtype == 'attachment' && $config->enable_file_indexing) {
        if ($post = $DB->get_records('post', array('id' => $id))){
            if ($post->attachment){
                return user_get_physical_file($post, null, true);
            }
        }
    }
    return null;
}

/**
 * dummy delete function that packs id with itemtype.
 * this was here for a reason, but I can't remember it at the moment.
 */
function user_delete($info, $itemtype) {
    $object->id = $info;
    $object->itemtype = $itemtype;
    return $object;
}

/**
 * returns the var names needed to build a sql query for addition/deletions
 * attachments are indirect records, linked to its post
 */
function user_db_names() {
    //[primary id], [table name], [time created field name], [time modified field name] [itemtype] [select restriction clause]
    return array(
        array('id', 'user', 'firstaccess', 'timemodified', 'user'),
        array('id', 'post', 'created', 'lastmodified', 'post'),
        array('id', 'post', 'created', 'lastmodified', 'attachment')
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
function user_check_text_access($path, $itemtype, $this_id, $user, $group_id, $context_id) {
    global $CFG, $DB;

    include_once("{$CFG->dirroot}/{$path}/lib.php");

    if ($itemtype == 'user') {
        // Get the user. 
        $userrecord = $DB->get_record('user', array('id' => $this_id));

        // We cannot see nothing from unconfirmed users.
        if (!$userrecord->confirmed and !has_capability('moodle/site:config', context_system::instance())) {
            if (!empty($CFG->search_access_debug)) echo "search reject : unconfirmed user ";
            return false;
        }
    } elseif ($itemtype == 'post' || $itemtype == 'attachment') {
        // Get the post.
        $post = $DB->get_record('post', array('id' => $this_id));
        $userrecord = $DB->get_record('user', array('id' => $post->userid));

        // we can try using blog visibility check
        return blog_user_can_view_user_post($user->id, $post);
    }
    $context = $DB->get_record('context', array('id' => $context_id));

    return true;
}

/**
 * this call back is called when displaying the link for some last post processing
 */
function user_link_post_processing($title) {

    $config = get_config();

    if ($config->utf8dir) {
        return mb_convert_encoding($title, 'UTF-8', 'auto');
    }
    return mb_convert_encoding($title, 'auto', 'UTF-8');
}
