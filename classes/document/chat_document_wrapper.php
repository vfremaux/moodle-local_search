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
 * @author Valery Fremaux [valery.fremaux@gmail.com] > 1.8
 * @contributor Tatsuva Shirai 20090530
 * @date 2008/03/31
 * @license http://www.gnu.org/copyleft/gpl.html GNU Public License
 *
 * document handling for chat activity module
 * This file contains the mapping between a chat history and it's indexable counterpart,
 *
 * Functions for iterating and retrieving the necessary records are now also included
 * in this file, rather than mod/chat/lib.php
 *
 */
namespace local_search\document;

use local_search\document_wrapper;
use moodle_url;
use StdClass;
use context_module;

/**
 * Document wrapper class.
 */
class chat_document_wrapper extends document_wrapper {
    /**
     * constructs a valid link to a chat content
     * @param cm_id the chat course module
     * @param start the start time of the session
     * @param end th end time of the session
     * @return a well formed link to session display
     */
    public static function make_link($instanceid, $contextid = null) {

        // Get an additional subentity id dynamically.
        $extravars = func_get_args();
        array_shift($extravars);
        $start = array_shift($extravars);
        $end = array_shift($extravars);

        return new moodle_url('/mod/chat/report.php', ['id' => $instanceid, 'start' => $start, 'end' => $end]);
    }

    /**
     * fetches all the records for a given session and assemble them as a unique track
     * we revamped here the code of report.php for making sessions, but without any output.
     * note that we should collect sessions "by groups" if groupmode() is SEPARATEGROUPS.
     * @param int $chatid the database
     * @param int $fromtime
     * @param int $totime
     * @uses CFG
     * @return an array of objects representing the chat sessions.
     */
    protected static function get_session_tracks($chatid, $fromtime = 0, $totime = 0) {
        global $CFG, $DB;

        $chat = $DB->get_record('chat', ['id' => $chatid]);
        $course = $DB->get_record('course', ['id' => $chat->course]);
        $coursemodule = $DB->get_field('modules', 'id', ['name' => 'chat']);
        $cm = $DB->get_record('course_modules', ['course' => $course->id, 'module' => $coursemodule, 'instance' => $chat->id]);
        if (empty($cm)) {
            // Shirai 20090530.
            mtrace("Missing this chat: Course=".$chat->course."/ ChatID=".$chatid);
            return [];
        }
        $groupmode = groups_get_activity_groupmode($cm, $course);

        $fromtimeclause = ($fromtime) ? "AND timestamp >= {$fromtime}" : '';
        $totimeclause = ($totime) ? "AND timestamp <= {$totime}" : '';
        $tracks = [];
        $select = " chatid = ? $fromtimeclause $totimeclause ";
        $messages = $DB->get_records_select('chat_messages', $select, [$chatid], "timestamp DESC");
        if ($messages) {
            // Splits discussions against groups.
            $groupedmessages = [];
            if ($groupmode != SEPARATEGROUPS) {
                foreach ($messages as $amessage) {
                    $groupedmessages[$amessage->groupid][] = $amessage;
                }
            } else {
                $groupedmessages[-1] = &$messages;
            }
            $sessiongap = 5 * 60;    // Keep 5 minutes silence means a new session.
            $sessionend = 0;
            $sessionstart = 0;
            $sessionusers = [];
            $lasttime = time();

            foreach ($groupedmessages as $groupid => $messages) {  // We are walking BACKWARDS through the messages.
                $messagesleft = count($messages);
                foreach ($messages as $message) {  // We are walking BACKWARDS through the messages.
                    $messagesleft --;              // Countdown.

                    if ($message->issystem) {
                        continue;
                    }

                    // We are within a session track.
                    if ((($lasttime - $message->timestamp) < $sessiongap) and $messagesleft) {  // Same session.
                        if (count($tracks) > 0) {
                            if ($message->userid) {       // Remember user and count messages.
                                $tracks[count($tracks) - 1]->sessionusers[$message->userid] = $message->userid;
                                // Update last track (if exists) record appending content (remember : we go backwards).
                            }
                            $tracks[count($tracks) - 1]->content .= ' '.$message->message;
                            $tracks[count($tracks) - 1]->sessionstart = $message->timestamp;
                        }
                    } else {
                        // We initiate a new session track (backwards).
                        $track = new StdClass();
                        $track->sessionend = $message->timestamp;
                        $track->sessionstart = $message->timestamp;
                        $track->content = $message->message;
                        // Reset the accumulator of users.
                        $track->sessionusers = [];
                        $track->sessionusers[$message->userid] = $message->userid;
                        $track->groupid = $groupid;
                        $tracks[] = $track;
                    }
                    $lasttime = $message->timestamp;
                }
            }
        }
        return $tracks;
    }

    /**
     * part of search engine API
     *
     */
    public static function get_iterator() {
        global $DB;

        $chatrooms = $DB->get_records('chat');
        return $chatrooms;
    }

    /**
     * part of search engine API
     *
     */
    public static function get_content_for_index(&$instance) {
        global $DB;

        $documents = [];
        $course = $DB->get_record('course', ['id' => $instance->course]);
        $coursemodule = $DB->get_field('modules', 'id', ['name' => 'chat']);
        $params = ['course' => $instance->course, 'module' => $coursemodule, 'instance' => $instance->id];
        $cm = $DB->get_record('course_modules', $params);
        if ($cm) {
            $context = context_module::instance($cm->id);

            // Getting records for indexing.
            $sessiontracks = self::get_session_tracks($instance->id);
            if ($sessiontracks) {
                foreach ($sessiontracks as $atrackid => $atrack) {
                    foreach ($atrack->sessionusers as $auserid) {
                        $user = $DB->get_record('user', ['id' => $auserid]);
                        $atrack->authors = ($user) ? fullname($user) : '';
                        $trackarr = get_object_vars($atrack);
                        $documents[] = new chat_track_document($trackarr, $instance->id, $cm->id, $instance->course, $atrack->groupid, $context->id);
                    }
                }
            }
            return $documents;
        }
        return [];
    }

    /**
     * returns a single data search document based on a chat_session id
     * chat session id is a text composite identifier made of :
     * - the chat id
     * - the timestamp when the session starts
     * - the timestamp when the session ends
     * @param id the multipart chat session id
     * @param itemtype the type of information (session is the only type)
     */
    public static function single_document($id, $itemtype) {
        global $DB;

        list($chatid, $sessionstart, $sessionend) = explode('-', $id);
        $chat = $DB->get_record('chat', ['id' => $chatid]);
        $course = $DB->get_record('course', ['id' => $chat->course]);
        $coursemodule = $DB->get_field('modules', 'id', ['name' => 'chat']);
        $params = ['course' => $course->id, 'module' => $coursemodule, 'instance' => $chat->id];
        $cm = $DB->get_record('course_modules', $params);
        if ($cm) {
            $context = context_module::instance($cm->id);

            // Should be only one.
            $tracks = chat_get_session_tracks($chat->id, $sessionstart, $sessionstart);
            if ($tracks) {
                $atrack = $tracks[0];
                $arr = get_object_vars($atrack);
                $document = new chat_track_document($arr, $chatid, $cm->id, $chat->course, $atrack->groupid, $context->id);
                return $document;
            }
        }
        return null;
    }

    /**
     * returns the var names needed to build a sql query for addition/deletions
     * // TODO chat indexable records are virtual. Should proceed in a special way
     * [primary id], [table name], [time created field name], [time modified field name]
     */
    public static function db_names() {
        return null;
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

        list($chatid, $sessionstart, $sessionend) = explode('-', $thisid);
        // Get the chat session and all related stuff.
        $chat = $DB->get_record('chat', ['id' => $chatid]);
        $context = $DB->get_record('context', ['id' => $contextid]);
        $cm = $DB->get_record('course_modules', ['id' => $context->instanceid]);
        if (empty($cm)) {
            return false; // Shirai 20090530 - MDL19342 - course module might have been delete.
        }

        if (!$cm->visible && !has_capability('moodle/course:viewhiddenactivities', $context)) {
            if (!empty($config->access_debug)) {
                echo "search reject : hidden chat ";
            }
            return false;
        }

        // Group consistency check : checks the following situations about groups.
        // Trap if user is not same group and groups are separated.
        $course = $DB->get_record('course', ['id' => $chat->course]);
        if ((groups_get_activity_groupmode($cm, $course) == SEPARATEGROUPS) &&
                !ismember($groupid) && !has_capability('moodle/site:accessallgroups', $context)) {
            if (!empty($config->access_debug)) {
                echo "search reject : chat element is in separated group ";
            }
            return false;
        }

        /*
         * ownership check : checks the following situations about user
         * trap if user is not owner and has cannot see other's entries
         */
        // TODO : typically may be stored into indexing cache.
        if (!has_capability('mod/chat:readlog', $context)) {
            if (!empty($config->access_debug)) {
                echo "search reject : cannot read past sessions ";
            }
            return false;
        }

        return true;
    }
}
