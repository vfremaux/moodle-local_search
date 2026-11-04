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
use local_search\document;
use moodle_exception;
use moodle_url;
use StdClass;

defined('MOODLE_INTERNAL') || die();

require_once($CFG->dirroot.'/blog/lib.php');

/**
 * a class for representing searchable information in user metadata
 */
class user_blog_attachment_document extends document {

    /**
     * constructor
     */
    public function __construct(&$post, $contextid) {
        global $DB;

        // Generic information; required.
        $doc = new StdClass;
        $doc->docid         = $post['id'];
        $doc->documenttype  = SEARCH_TYPE_USER;
        $doc->itemtype      = 'attachment';
        $doc->contextid     = $contextid;

        $user = $DB->get_record('user', ['id' => $post['userid']]);

        // We cannot call userdate with relevant locale at indexing time.
        $doc->title         = get_string('file').' : '.$post['subject'];
        $doc->date          = $post['created'];

        // Remove '(ip.ip.ip.ip)' from chat author list.
        $doc->author        = fullname($user);
        $doc->contents      = $post['alltext'];
        $doc->url           = user_document_wrapper::make_link($post['id'], 'attachment');

        // Module specific information; optional.

        // Construct the parent class.
        parent::__construct($doc, $data, 0, 0, $post['userid'], PATH_FOR_SEARCH_TYPE_USER);
    }
}
