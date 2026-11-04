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
 * @author Michael Campanis (mchampan) [cynnical@gmail.com], Valery Fremaux [valery.fremaux@gmail.com] > 1.8
 * @contributor Tatsuva Shirai 20090530
 * @date 2008/03/31
 * @license http://www.gnu.org/copyleft/gpl.html GNU Public License
 *
 * document handling for wiki activity module
 * This file contains the mapping between a wiki page and it's indexable counterpart,
 * e.g. searchdocument->title = wikipage->pagename
 *
 * Functions for iterating and retrieving the necessary records are now also included
 * in this file, rather than mod/wiki/lib.php
 */
namespace local_search\document;

use context_course;
use context_module;
use local_search\document_wrapper;
use moodle_url;
use StdClass;

defined('MOODLE_INTERNAL') || die();

require_once($CFG->dirroot.'/mod/wiki/lib.php');
require_once($CFG->dirroot.'/mod/wiki/locallib.php');

class wiki_document_wrapper extends document_wrapper {
    /**
     * converts a page name to cope Wiki constraints. Transforms spaces in plus.
     * @param str the name to convert
     * @return the converted name
     */
    protected static function wiki_name_convert($str) {
        return str_replace(' ', '+', $str);
    }

    /**
     * constructs a valid link to a wiki content
     * @param int $instanceid
     * @param string $title
     * @param int $version
     * @return an url
     */
    public static function make_link($instanceid, $contextid = null) {

        // Get an additional subentity id dynamically.
        $extravars = func_get_args();
        array_shift($extravars);
        $title = array_shift($extravars);
        $version = array_shift($extravars);

        $params = ['wid' => $instanceid, 'page' => self::wiki_name_convert($title), 'version' => $version];
        return new moodle_url('/mod/wiki/view.php', $params);
    }

    /**
     * rescued and converted from ewikimoodlelib.php
     * retrieves latest version of a page
     * @param object $entry the wiki object as a reference
     * @param string $pagename the name of the page known by the wiki engine
     * @param int $version
     */
    protected static function get_latest_page(&$entry, $pagename, $version = 0) {
        global $DB;

        if ($version > 0 && is_int($version)) {
            $version = "AND (version = $version)";
        } else {
            $version = '';
        }

        $select = "(pagename = ?) AND wiki = ? $version ";
        $sort   = 'version DESC';

        // Change this to recordset_select, as per http://docs.moodle.org/en/Datalib_Notes.
        if ($resultarr = $DB->get_records_select('wiki_pages', $select, [$pagename, $entry->id], $sort, '*', 0, 1)) {
            foreach ($resultarr as $obj) {
                $resultobj = $obj;
            }
        }

        if (isset($resultobj)) {
            $resultobj->meta = @unserialize($resultobj->meta);
            return $resultobj;
        } else {
            return false;
        }
    }

    /**
     * fetches all pages, including old versions
     * @param object $entry the wiki object as a reference
     * @return an array of record objects that represents pages of this wiki object
     */
    protected static function get_pages(&$entry) {
        global $DB;

        return $DB->get_records('wiki_pages', ['wiki' => $entry->id]);
    }

    /**
     * fetches all the latest versions of all the pages
     * @param object $entry
     */
    protected static function get_latest_pages(&$entry) {
        global $DB;

        $pages = [];

        if ($ids = $DB->get_records('wiki_pages', ['subwikiid' => $entry->id], '', 'distinct pagename')) {
            if ($pagesets = $DB->get_records('wiki_pages', ['subwikiid' => $entry->id], '', 'distinct pagename')) {
                foreach ($pagesets as $apageset) {
                    $pages[] = self::get_latest_page($entry, $apageset->pagename);
                }
            } else {
                return false;
            }
        }
        return $pages;
    }

    /**
     * part of search engine API
     *
     */
    public static function get_iterator() {
        global $DB;

        $wikis = $DB->get_records('wiki');
        return $wikis;
    }

    /**
     * part of search engine API
     * @param wiki a wiki instance
     * @return an array of searchable deocuments
     */
    public static function get_content_for_index(&$wiki) {
        global $DB;

        $documents = [];
        $entries = wiki_get_page_list($wiki->id);
        if ($entries) {
            $coursemodule = $DB->get_field('modules', 'id', ['name' => 'wiki']);
            $params = ['course' => $wiki->course, 'module' => $coursemodule, 'instance' => $wiki->id];
            $cm = $DB->get_record('course_modules', $params);
            $context = context_module::instance($cm->id);
            foreach ($entries as $entry) {

                // Latest pages.
                $pages = self::get_latest_pages($entry);
                if (is_array($pages)) {
                    foreach ($pages as $page) {
                        if (strlen($page->content) > 0) {
                            $arr = get_object_vars($page);
                            $documents[] = new wiki_document($arr, $entry->wikiid, $entry->course, $entry->groupid,
                                                                  $page->userid, $context->id);
                        }
                    }
                }
            }
        }
        return $documents;
    }

    /**
     * returns a single wiki search document based on a wiki_entry id
     * @param id the id of the wiki
     * @param itemtype the type of information (standard)
     * @return a searchable document
     */
    public static function single_document($id, $itemtype) {
        global $DB;

        $page = $DB->get_record('wiki_pages', ['id' => $id]);
        $versions = $DB->get_records('wiki_versions', ['pageid' => $page->id], 'timecreated DESC');
        $version = array_shift($versions); // Use only most recent version. there should be one at least.
        $page->version = $version->version;
        $subwiki = $DB->get_record('wiki_subwikis', ['id' => $page->subwikiid]);
        $wiki = $DB->get_record('wiki', ['id' => $subwiki->wikiid]);
        $coursemodule = $DB->get_field('modules', 'id', ['name' => 'wiki']);
        $params = ['course' => $wiki->course, 'module' => $coursemodule, 'instance' => $subwiki->wikiid];
        $cm = $DB->get_record('course_modules', $params);
        $context = context_module::instance($cm->id);
        $vars = get_object_vars($page);
        return new wiki_document($vars, $subwiki->wikiid, $wiki->course, $subwiki->groupid, $page->userid, $context->id);
    }

    /**
     * Returns the var names needed to build a sql query for addition/deletions
     * [primary id], [table name], [time created field name], [time modified field name]
     */
    public static function db_names() {
        return [['id', 'wiki_pages', 'timecreated', 'timemodified', 'standard']];
    }

    /**
     * This function handles the access policy to contents indexed as searchable documents. If this
     * function does not exist, the search engine assumes access is allowed.
     * When this point is reached, we already know that :
     * - user is legitimate in the surrounding context
     * - user may be guest and guest access is allowed to the module
     * - the function may perform local checks within the module information logic
     * @param path the access path to the module script code
     * @param itemtype the information subclassing (usefull for complex modules, defaults to 'standard')
     * @param this_id the item id within the information class denoted by itemtype. In wikies, this id
     * points out the indexed wiki page.
     * @param user the user record denoting the user who searches
     * @param group_id the current group used by the user when searching
     * @return true if access is allowed, false elsewhere
     */
    public static function check_text_access($path, $itemtype, $thisid, $user, $groupid, $contextid) {
        global $CFG, $DB;

        // Get the wiki object and all related stuff.
        $page = $DB->get_record('wiki_pages', ['id' => $thisid]);
        $subwiki = $DB->get_record('wiki_subwikis', ['id' => $page->subwikiid]);
        $wiki = $DB->get_record('wiki', ['id' => $subwiki->wikiid]);
        $course = $DB->get_record('course', ['id' => $wiki->course]);
        $context = $DB->get_record('context', ['id' => $contextid]);
        $cm = $DB->get_record('course_modules', ['id' => $context->instanceid]);
        if (empty($cm)) {
            return false; // Shirai 20090530 - MDL19342 - course module might have been delete.
        }

        if (!$cm->visible and !has_capability('moodle/course:viewhiddenactivities', $context)) {
            if (!empty($CFG->search_access_debug)) {
                echo "search reject : hidden wiki ";
            }
            return false;
        }

        // Group consistency check : checks the following situations about groups.
        // Traps if user is not same group and groups are separated.
        if ((groups_get_activity_groupmode($cm) == SEPARATEGROUPS) &&
                !groups_is_member($groupid) &&
                        !has_capability('moodle/site:accessallgroups', $context)) {
            if (!empty($CFG->search_access_debug)) {
                echo "search reject : separated group owner wiki ";
            }
            return false;
        }
        return true;
    }
}
