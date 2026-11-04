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
 * A class for representing searchable information in user metadata.
 */
class user_document extends document {

    /**
     * Constructor.
     */
    public function __construct(&$userhash, $userid, $contextid) {
        global $DB;

        // Generic information; required.
        $doc = new StdClass;
        $doc->docid         = $userhash['id'];
        $doc->documenttype  = SEARCH_TYPE_USER;
        $doc->itemtype      = 'user';
        $doc->contextid     = $contextid;

        $user = $DB->get_record('user', ['id' => $userid]);
        $doc->title         = get_string('user').': '.fullname($user);
        $doc->date          = ($userhash['lastaccess']) ? $userhash['lastaccess'] : time();

        // Remove '(ip.ip.ip.ip)' from chat author list.
        $doc->author        = $user->id;
        $doc->contents      = $userhash['description'];
        $doc->url           = user_document_wrapper::make_link($userid, 'user');

        // Module specific information; optional.

        /*
         * construct the parent class
         * Shirai : User pictures are not displayed in results of blogs (2009/05/29) MDL19341
         */
        parent::__construct($doc, $data, 0, 0, $userid, PATH_FOR_SEARCH_TYPE_USER);

        // Add extra fields.

        $encoding = 'UTF-8';

        $this->addField(\Zend_Search_Lucene_Field::Keyword('institution', $userhash['institution'], $encoding));
        $this->addField(\Zend_Search_Lucene_Field::Keyword('department', $userhash['department'], $encoding));
        $this->addField(\Zend_Search_Lucene_Field::Keyword('country', $userhash['country'], $encoding));

    }
}
