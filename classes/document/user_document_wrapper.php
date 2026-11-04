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
 * @author Valery Fremaux [valery.fremaux@gmail.com] > 1.8
 * @contributor Tatsuva Shirai 20090530
 * @date 2008/03/31
 * @license http://www.gnu.org/copyleft/gpl.html GNU Public License
 *
 * special (EXTRA) document handling for user related data
 */
namespace local_search\document;

use context_course;
use context_module;
use context_system;
use context_user;
use local_search\document_wrapper;
use moodle_exception;
use moodle_url;
use StdClass;

defined('MOODLE_INTERNAL') || die();

require_once($CFG->dirroot.'/blog/lib.php');

class user_document_wrapper extends document_wrapper {

    /**
     * constructs a valid link to a user record
     * @param userid the user
     * @param itemtype
     * @return a well formed link to user information
     */
    public static function make_link($instanceid, $contextid = null) {

        // Get an additional subentity id dynamically.
        $extravars = func_get_args();
        array_shift($extravars);
        $itemtype = array_shift($extravars);

        if ($itemtype == 'user') {
            return new moodle_url('/user/view.php', ['id' => $instanceid]);
        } else if ($itemtype == 'post') {
            return new moodle_url('/blog/index.php', ['postid' => $instanceid]);
        } else if ($itemtype == 'attachment') {
            $post = $DB->get_record('post', ['id' => $instanceid]);
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
    public static function get_iterator() {
        global $DB;

        $users = $DB->get_records('user');
        return $users;
    }

    /**
     * part of search engine API
     * @uses CFG
     * @return an array of documents generated from data
     */
    public static function get_content_for_index(&$user) {
        global $CFG, $DB;

        $documents = [];
        $config = get_config('local_search');
        $fs = get_file_storage();

        $userhash = get_object_vars($user);
        $documents[] = new user_document($userhash, $user->id, null);

        if ($posts = $DB->get_records('post', ['userid' => $user->id], 'created')) {
            foreach ($posts as $post) {
                $texts = [];
                $texts[] = $post->subject;
                $texts[] = $post->summary;
                $texts[] = $post->content;
                $post->description = implode(' ', $texts);

                // Record the attachment if any and physical files can be indexed.
                if (@$config->enable_file_indexing) {
                    $contextid = context_user::instance($post->userid)->id;
                    $files = $fs->get_area_files($contextid, 'blog', 'attachement', $post->id, 'filename', true);
                    if ($post->attachment && !empty($files)) {
                        $file = array_pop($files);
                        search_get_physical_file($documents, $file, clone($post), $contextid, '\\local_search\\document\\user_blog_attachment_document', false);
                    }
                }

                $posthash = get_object_vars($post);
                $documents[] = new user_post_document($posthash, $post->userid, null);
            }
        }
        return $documents;
    }

    /**
     * returns a single user search document
     * @param composite $id a unique document id made with
     * @param itemtype the type of information (session is the only type)
     */
    public static function single_document($id, $itemtype) {
        global $DB;

        $config = get_config('local_search');

        if ($itemtype == 'user') {
            if ($user = $DB->get_record('user', ['id' => $id])) {
                $userhash = get_object_vars($user);
                return new user_document($userhash, $user->id, 'user', null);
            }
        } else if ($itemtype == 'post') {
            if ($post = $DB->get_record('post', ['id' => $id])) {
                $texts = [];
                $texts[] = $post->subject;
                $texts[] = $post->summary;
                $texts[] = $post->content;
                $post->description = implode(' ', $texts);
                $posthash = get_object_vars($post);
                return new user_post_document($posthash, $post->userid, 'post', null);
            }
        } else if ($itemtype == 'attachment' && $config->enable_file_indexing) {
            if ($post = $DB->get_records('post', ['id' => $id])) {
                $contextid = context_user::instance($post->userid)->id;
                if ($post->attachment) {
                    return search_get_physical_file($documents, $file, $post, $contextid, '\\local_search\\document\\user_blog_attachment_document', true);
                }
            }
        }
        return null;
    }

    /**
     * returns the var names needed to build a sql query for addition/deletions
     * attachments are indirect records, linked to its post
     * [primary id], [table name], [time created field name], [time modified field name] [itemtype]
     * [select restriction clause]
     */
    public static function db_names() {
        return [
            ['id', 'user', 'firstaccess', 'timemodified', 'user'],
            ['id', 'post', 'created', 'lastmodified', 'post'],
            ['id', 'post', 'created', 'lastmodified', 'attachment'],
        ];
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
    public static function check_text_access($path, $itemtype, $thisid, $user, $groupid, $contextid) {
        global $CFG, $DB;

        include_once("{$CFG->dirroot}/{$path}/lib.php");
        $config = get_config('local_search');

        if ($itemtype == 'user') {
            // Get the user.
            $userrecord = $DB->get_record('user', ['id' => $thisid]);

            // We cannot see nothing from unconfirmed users.
            if (!$userrecord->confirmed &&
                    !has_capability('moodle/site:config', context_system::instance())) {
                if (!empty($config->access_debug)) {
                    echo "search reject : unconfirmed user ";
                }
                return false;
            }
        } else if ($itemtype == 'post' || $itemtype == 'attachment') {
            // Get the post.
            $post = $DB->get_record('post', ['id' => $thisid]);
            $userrecord = $DB->get_record('user', ['id' => $post->userid]);

            // We can try using blog visibility check.
            return blog_user_can_view_user_post($user->id, $post);
        }
        $context = $DB->get_record('context', ['id' => $contextid]);

        return true;
    }
}
