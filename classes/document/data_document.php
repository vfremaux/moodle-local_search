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
 * document handling for data activity module
 * This file contains the mapping between a database object and it's indexable counterpart,
 *
 * Functions for iterating and retrieving the necessary records are now also included
 * in this file, rather than mod/data/lib.php
 *
 */
namespace local_search\document;

use StdClass;
use moodle_url;
use local_search\document;

defined('MOODLE_INTERNAL') || die();

require_once($CFG->dirroot.'/mod/data/lib.php');

/**
 * a class for representing searchable information (data records)
 *
 */
class data_document extends document {
    /**
     * constructor
     */
    public function __construct(&$record, $courseid, $contextid) {
        global $DB;

        // Generic information; required.
        $doc = new StdClass;
        $doc->docid     = $record['id'];
        $doc->documenttype  = SEARCH_TYPE_DATA;
        $doc->itemtype      = 'record';
        $doc->contextid     = $contextid;

        $doc->title     = $record['title'];
        $doc->date      = $record['timemodified'];
        // Remove '(ip.ip.ip.ip)' from data record author field.
        if ($record['userid']) {
            $user = $DB->get_record('user', ['id' => $record['userid']]);
        }
        $doc->author = (isset($user)) ? $user->firstname.' '.$user->lastname : '';
        $doc->contents  = $record['content'];
        $doc->url       = data_document_wrapper::make_link($record['dataid'], $record['id']);

        // Module specific information; optional.
        $data = new StdClass;
        $data->database = $record['dataid'];

        // Construct the parent class.
        parent::__construct($doc, $data, $courseid, $record['groupid'], $record['userid'], 'mod/'.SEARCH_TYPE_DATA);
    }
}


