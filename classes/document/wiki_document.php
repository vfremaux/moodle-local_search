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
use local_search\document;
use moodle_url;
use StdClass;

defined('MOODLE_INTERNAL') || die();

require_once($CFG->dirroot.'/mod/wiki/lib.php');
require_once($CFG->dirroot.'/mod/wiki/locallib.php');

/**
 * All the $doc->___ fields are required by the base document class!
 * Each and every module that requires search functionality must correctly
 * map their internal fields to the five $doc fields (id, title, author, contents
 * and url). Any module specific data can be added to the $data object, which is
 * serialised into a binary field in the index.
 */
class wiki_document extends document {

    public function __construct(&$page, $wikiid, $courseid, $groupid, $userid, $contextid) {
        global $DB;

        // Generic information; required.
        $doc = new StdClass;
        $doc->docid         = $page['id'];
        $doc->documenttype  = SEARCH_TYPE_WIKI;
        $doc->itemtype      = 'standard';
        $doc->contextid     = $contextid;

        $doc->title     = $page['title'];
        $doc->date      = $page['timemodified'];
        // Remove '(ip.ip.ip.ip)' from wiki author field.
        // Remove '(ip.ip.ip.ip)' from data record author field.
        $user = $DB->get_record('user', ['id' => $page['userid']]);
        $doc->author = (isset($user)) ? $user->firstname.' '.$user->lastname : '';
        $doc->contents  = $page['cachedcontent'];
        $doc->url       = wiki_document_wrapper::make_link($wikiid, $page['title'], $page['version']);

        // Module specific information; optional.
        $data = new StdClass;
        $data->version  = $page['version'];
        $data->wiki     = $wikiid;

        // Construct the parent class.
        parent::__construct($doc, $data, $courseid, $groupid, $userid, 'mod/'.SEARCH_TYPE_WIKI);
    }
}
