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
 * @author Michael Campanis (mchampan) [cynnical@gmail.com], Valery Fremaux [valery.fremaux@gmail.com] > 1.8
 * @contributor Tatsuva Shirai on UTF-8 multibyte fixing
 * @date 2008/03/31
 * @license http://www.gnu.org/copyleft/gpl.html GNU Public License
 *
 * Base search document from which other module/block types can
 * extend.
 */
namespace local_search;

use StdClass;
use moodle_url;
use core_text;

/**
 * Base abstract class for all document wrappers.
 */
abstract class document_wrapper implements indexable {
    /**
     * If the searchable object is a course module, the module name here.
     */
    protected static $modname;

    /**
     * constructs valid access links to information
     * @param int $instanceid the of the instance
     * @param int $contextid the context
     * @return a full featured link element as a string
     */
    public static function make_link($instanceid, $contextid = null) {
        if (!empty(static::$modname)) {
            return new moodle_url('/mod/'.static::$modname.'/view.php', ['id' => $instanceid]);
        }
        return null;
    }

    /**
     * Dummy delete function that aggregates id with itemtype.
     * this was here for a reason, but I can't remember it at the moment.
     * @param object $info
     * @param string $itemtype
     */
    public static function delete($info, $itemtype) {
        $object = new StdClass();
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
        setlocale(LC_TIME, core_text::substr(current_language(), 0, 2));
        $title = preg_replace('/TT_(.*)_TT/', userdate("\\1"), $title);

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
