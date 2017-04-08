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
 * @contributor Tatsuva Shirai on UTF-8 multibyte fixing
 * @date 2008/03/31
 * @license http://www.gnu.org/copyleft/gpl.html GNU Public License
 *
 * Base search document from which other module/block types can
 * extend.
 */
namespace local_search;

defined('MOODLE_INTERNAL') || die();

interface indexable {
    /**
     * part of standard API. Provides the set of searchable instances.
     *
     */
    static function get_iterator();

    /**
     * Part of standard API
     * this function does not need a content iterator, returns all the info itself;
     * @param notneeded to comply API, remember to fake the iterator array though
     * @return an array of searchable documents
     */
    static function get_content_for_index(&$instance);

    /**
     * part of standard API.
     * returns a single resource search document based on a resource_entry id
     * @param id the id of the accessible document
     * @return a searchable object or null if failure
     */
    static function single_document($id, $itemtype);

    /**
     * returns the var names needed to build a sql query for addition/deletions
     * @return array with string definitions for (one per documenttype :
     * [primary id], [table name], [time created field name], [time modified field name],
     * [additional where conditions for sql]
     */
    static function db_names();

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
    static function check_text_access($path, $itemtype, $thisid, $user, $groupid, $contextid);

}

abstract class document_wrapper implements indexable {

    /**
     * If the searchable object is a course module, the module name here.
     */
    protected static $modname;

    /**
     * constructs valid access links to information
     * @param instanceid the of the instance
     * @return a full featured link element as a string
     */
    public static function make_link($instanceid) {
        if (!empty($modname)) {
            return new moodle_url('/mod/'.self::modname.'/view.php', array('id' => $instanceid));
        }
    }

    /**
     * dummy delete function that aggregates id with itemtype.
     * this was here for a reason, but I can't remember it at the moment.
     *
     */
    public static function delete($info, $itemtype) {
        $object = new StdClass;
        $object->id = $info;
        $object->itemtype = $itemtype;
        return $object;
    }

    /**
     * TODO : Check is deprecated.
     * post processes the url for cleaner output.
     * @param string $title
     */
    public static function link_post_processing($title) {

        setLocale(LC_TIME, substr(current_language(), 0, 2));
        $title = preg_replace('/TT_(.*)_TT/e', "userdate(\\1)", $title);

        $config = get_config('local_search');

        if (!$config->utf8dir) {
            return $title;
        }
        if ($config->utf8dir) {
            return mb_convert_encoding($title, 'UTF-8', 'auto');
        }
        return mb_convert_encoding($title, 'auto', 'UTF-8');
    }

}
