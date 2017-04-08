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
 * @contributor Tatsuva Shirai 20090530
 * @author Michael Campanis (mchampan) [cynnical@gmail.com], Valery Fremaux [valery.fremaux@club-internet.fr] > 1.8
 * @license http://www.gnu.org/copyleft/gpl.html GNU Public License
 *
 * document handling for forum activity module
 * This file contains the mapping between a forum post and it's indexable counterpart,
 *
 * Functions for iterating and retrieving the necessary records are now also included
 * in this file, rather than mod/forum/lib.php
 *
 */
namespace local_search;

use \StdClass;
use \context_module;
use \context_course;
use \context_system;
use \moodle_url;

defined('MOODLE_INTERNAL') || die();

require_once($CFG->dirroot.'/local/search/documents/document.php');
require_once($CFG->dirroot.'/local/search/documents/document_wrapper.class.php');
require_once($CFG->dirroot.'/mod/forum/lib.php');

/**
 * a class for representing searchable information
 *
 */
class ForumSearchDocument extends SearchDocument {

    /**
     * constructor
     */
    public function __construct(&$post, $forumid, $courseid, $itemtype, $contextid) {
        global $DB;

        // Generic information.
        $doc = new StdClass;
        $doc->docid        = $post['id'];
        $doc->documenttype = SEARCH_TYPE_FORUM;
        $doc->itemtype     = $itemtype;
        $doc->contextid    = $contextid;

        $doc->title        = $post['subject'];

        $user = $DB->get_record('user', array('id' => $post['userid']));
        $doc->author       = fullname($user);
        $doc->contents     = $post['message'];
        $doc->date         = $post['created'];
        $doc->url          = forum_document_wrapper::make_link($post['discussion'], $post['id']);

        // Module specific information.
        $data = new StdClass;
        $data->forum      = $forumid;
        $data->discussion = $post['discussion'];

        parent::__construct($doc, $data, $courseid, $post['groupid'], $post['userid'], 'mod/'.SEARCH_TYPE_FORUM);
    }
}

/**
 * a class for representing searchable information for attachments
 *
 */
class ForumAttachmentSearchDocument extends SearchDocument {

    /**
     * constructor
     */
    public function __construct(&$post, $forumid, $filearea, $itemid, $courseid, $itemtype, $contextid) {
        global $DB;

        // Generic information.
        $doc = new StdClass;
        $doc->docid        = $post['id'];
        $doc->documenttype = SEARCH_TYPE_FORUM;
        $doc->itemtype     = $itemtype;
        $doc->contextid    = $contextid;

        $doc->title        = $post['subject'];

        $user = $DB->get_record('user', array('id' => $post['userid']));
        $doc->author       = fullname($user);
        $doc->contents     = $post['message'];
        $doc->date         = $post['created'];
        $doc->url          = forum_document_wrapper::make_link($post['discussion'], $post['id']);

        // Module specific information.
        $data = new StdClass;
        $data->forum      = $forumid;
        $data->discussion = $post['discussion'];
        $data->filearea = $filearea;
        $data->itemid = $itemid;

        parent::__construct($doc, $data, $courseid, $post['groupid'], $post['userid'], 'mod/'.SEARCH_TYPE_FORUM);
    }
}

class forum_document_wrapper extends document_wrapper {

    /**
     * constructs a valid link to a chat content
     * @param discussion_id the discussion
     * @param post_id the id of a single post
     * @return a well formed link to forum message display
     */
    public static function make_link($discussionid) {
        // Get an additional subentity id dynamically.
        $extravars = func_get_args();
        array_shift($extravars);
        $postid = array_shift($extravars);

        return new moodle_url('/mod/forum/discuss.php', array('id' => $discussionid));
    }

    /**
     * search standard API
     *
     */
    public static function get_iterator() {
        global $DB;

        $forums = $DB->get_records('forum');
        return $forums;
    }

    /**
     * search standard API
     * @param forum a forum instance
     * @return an array of searchable documents
     */
    public static function get_content_for_index(&$forum) {
        global $DB;

        $fs = get_file_storage();

        $documents = array();
        if (!$forum) {
            return $documents;
        }

        $posts = self::get_discussions_fast($forum->id);
        mtrace("Found ".count($posts)." discussions to analyse in forum ".$forum->name);
        if (!$posts) {
            return $documents;
        }

        $coursemodule = $DB->get_field('modules', 'id', array('name' => 'forum'));
        $params = array('course' => $forum->course, 'module' => $coursemodule, 'instance' => $forum->id);
        $cm = $DB->get_record('course_modules', $params);
        $context = context_module::instance($cm->id);

        foreach ($posts as $apost) {
            $apost->itemtype = 'head';
            if ($apost) {
                if (!empty($apost->message)) {
                    echo "*";
                    $vars = get_object_vars($apost);
                    $documents[] = new ForumSearchDocument($vars, $forum->id, $forum->course, 'head', $context->id);
                }
                if ($children = self::get_child_posts_fast($apost->id, $forum->id)) {
                    foreach ($children as $achild) {
                        echo ".";
                        $achild->itemtype = 'post';
                        if (strlen($achild->message) > 0) {
                            $arr = get_object_vars($achild);
                            $documents[] = new ForumSearchDocument($arr, $forum->id, $forum->course, 'post', $context->id);
                        }
                    }
                    
                }
            }
        }
        mtrace("Finished discussion");
        return $documents;
    }

    /**
     * returns a single forum search document based on a forum entry id
     * @param id an id for a single information stub
     * @param itemtype the type of information
     */
    public static function single_document($id, $itemtype) {
        global $DB;

        // Both known item types are posts so get them the same way.
        $post = $DB->get_record('forum_posts', array('id' => $id));
        $discussion = $DB->get_record('forum_discussions', array('id' => $post->discussion));
        $coursemodule = $DB->get_field('modules', 'id', array('name' => 'forum'));
        $params = array('course' => $discussion->course, 'module' => $coursemodule, 'instance' => $discussion->forum);
        $cm = $DB->get_record('course_modules', $params);
        if ($cm) {
            $context = context_module::instance($cm->id);
            $post->groupid = $discussion->groupid;
            $arr = get_object_vars($post);
            return new ForumSearchDocument($arr, $discussion->forum, $discussion->course, $itemtype, $context->id);
        }
        return null;
    }

    /**
     * returns the var names needed to build a sql query for addition/deletions
     * [primary id], [table name], [time created field name], [time modified field name]
     *
     */
    public static function db_names() {
        return array(
            array('id', 'forum_posts', 'created', 'modified', 'head', 'parent = 0'),
            array('id', 'forum_posts', 'created', 'modified', 'post', 'parent != 0')
        );
    }

    /**
     * reworked faster version from /mod/forum/lib.php
     * @param forum_id a forum identifier
     * @uses CFG, USER
     * @return an array of posts
     */
    public static function get_discussions_fast($forumid) {
        global $CFG, $USER, $DB;

        $timelimit = '';
        if (!empty($CFG->forum_enabletimedposts)) {
            $cm = get_coursemodule_from_instance('forum', $forumid);
            $context = context_module::instance($cm->id);
            $isteacher = has_capability('mod/forum:deleteanypost', $context);
            if (!((has_capability('moodle/site:config', context_system::instance()) && !empty($CFG->admineditalways)) || $isteacher)) {
                $now = time();
                $timelimit = " AND ((d.timestart = 0 OR d.timestart <= '$now') AND (d.timeend = 0 OR d.timeend > '$now')";
                if (!empty($USER->id)) {
                    $timelimit .= " OR d.userid = '$USER->id'";
                }
                $timelimit .= ')';
            }
        }
    
        $sql = "
            SELECT
                p.id,
                p.subject,
                p.discussion,
                p.message,
                p.created,
                d.groupid,
                p.userid,
                u.firstname,
                u.lastname
            FROM
                {forum_discussions} d
            JOIN
                {forum_posts} p 
            ON
                p.discussion = d.id
            JOIN
                {user} u 
            ON
                p.userid = u.id
            WHERE
                d.forum = ? AND
                p.parent = 0
                $timelimit
            ORDER BY
                d.timemodified DESC
        ";
        return $DB->get_records_sql($sql, array($forumid));
    }

    /**
     * reworked faster version from /mod/forum/lib.php
     * @param parent the id of the first post within the discussion
     * @param forum_id the forum identifier
     * @uses CFG
     * @return an array of posts
     */
    protected static function get_child_posts_fast($parent, $forumid) {
        global $CFG, $DB;

        $sql = "
            SELECT
                p.id,
                p.subject,
                p.discussion,
                p.message,
                p.created,
                {$forumid} AS forum,
                p.userid,
                d.groupid,
                u.firstname, 
                u.lastname
            FROM
                {forum_discussions} d
            JOIN
                {forum_posts} p
            ON
                p.discussion = d.id
            JOIN
                {user} u
            ON
                p.userid = u.id
            WHERE
                p.parent = ?
            ORDER BY
                p.created ASC
        ";
        return $DB->get_records_sql($sql, array($parent));
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
     * @param this_id the item id within the information class denoted by itemtype. In forums, this id
     * points out the individual post.
     * @param user the user record denoting the user who searches
     * @param group_id the current group used by the user when searching
     * @uses CFG, USER
     * @return true if access is allowed, false elsewhere
     */
    public static function check_text_access($path, $itemtype, $thisid, $user, $groupid, $contextid) {
        global $CFG, $USER, $DB;

        $config = get_config('local_search');

        include_once("{$CFG->dirroot}/{$path}/lib.php");

        // Get the forum post and all related stuff.
        $post = $DB->get_record('forum_posts', array('id' => $thisid));
        $discussion = $DB->get_record('forum_discussions', array('id' => $post->discussion));
        $context = $DB->get_record('context', array('id' => $contextid));
        $cm = $DB->get_record('course_modules', array('id' => $context->instanceid));
        if (empty($cm)) {
            return false; // Shirai 20093005 - MDL19342 - course module might have been delete.
        }

        if (!$cm->visible and !has_capability('moodle/course:viewhiddenactivities', $context)) {
            if (!empty($config->access_debug)) echo "search reject : hidden forum resource ";
            return false;
        }

        // Approval check : entries should be approved for being viewed, or belongs to the user.
        if (($post->userid != $USER->id) &&
                !$post->mailed &&
                        !has_capability('mod/forum:viewhiddentimeposts', $context)) {
            if (!empty($config->access_debug)) {
                echo "search reject : time hidden forum item";
            }
            return false;
        }

        // Group check : entries should be in accessible groups.
        $course = $DB->get_record('course', array('id' => $discussion->course));
        if ($groupid >= 0 &&
                (groups_get_activity_groupmode($cm)  == SEPARATEGROUPS) &&
                        (groups_is_member($groupid)) &&
                                !has_capability('mod/forum:viewdiscussionsfromallgroups', $context)) {
            if (!empty($config->access_debug)) {
                echo "search reject : separated grouped forum item";
            }
            return false;
        }

        return true;
    }
}