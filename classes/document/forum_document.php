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
 * @author Michael Campanis (mchampan) [cynnical@gmail.com], Valery Fremaux [valery.fremaux@gmail.com] > 1.8
 * @license http://www.gnu.org/copyleft/gpl.html GNU Public License
 *
 * document handling for forum activity module
 * This file contains the mapping between a forum post and it's indexable counterpart,
 *
 * Functions for iterating and retrieving the necessary records are now also included
 * in this file, rather than mod/forum/lib.php
 *
 */
namespace local_search\document;

use context_course;
use context_module;
use context_system;
use local_search\document;
use moodle_url;
use StdClass;

defined('MOODLE_INTERNAL') || die();

require_once($CFG->dirroot.'/mod/forum/lib.php');

/**
 * a class for representing searchable information
 *
 */
class forum_document extends document {

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

        $user = $DB->get_record('user', ['id' => $post['userid']]);
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
