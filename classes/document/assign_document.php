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
 * @subpackage document_wrappers
 * @author Valery Fremaux [valery.fremaux@gmail.com] > 1.8
 * @contributor Tatsuva Shirai 20090530
 * @license http://www.gnu.org/copyleft/gpl.html GNU Public License
 *
 * document handling for assignment activity module
 */
namespace local_search\document;

defined('MOODLE_INTERNAL') || die();

use StdClass;
use context_module;
use context_course;
use moodle_url;

use local_search\document;
use local_search\document_wrapper;

require_once($CFG->dirroot.'/mod/assign/lib.php');

/**
 * a class for representing searchable information
 *
 */
class assign_document extends document {
    /**
     * constructor
     * @param object $assignment
     * @param int $assignmentmoduleid
     * @param string $itemtype
     * @param string $courseid
     * @param string $ownerid
     * @param string $contextid
     */
    public function __construct($assignment, $assignmentmoduleid, $itemtype, $courseid, $ownerid, $contextid) {
        global $DB;

        // Generic information; required.
        $doc = new StdClass;
        $doc->docid         = $assignment['id'];
        $doc->documenttype  = SEARCH_TYPE_ASSIGN;
        $doc->itemtype      = $itemtype;
        $doc->contextid     = $contextid;

        // We cannot call userdate with relevant locale at indexing time.
        $doc->title         = "{$itemtype}: {$assignment['name']}";
        $doc->date          = $assignment['datemodified'];

        // Remove '(ip.ip.ip.ip)' from chat author list.
        $owner = $DB->get_record('user', ['id' => $ownerid]);
        $doc->author        = fullname($owner);
        $doc->contents      = $assignment['intro'];
        $doc->url           = assign_document_wrapper::make_link($assignmentmoduleid, $itemtype, $ownerid);

        // Module specific information; optional.
        $data = new StdClass;
        $data->assignment         = $assignmentmoduleid;

        // Construct the parent class.
        parent::__construct($doc, $data, $courseid, 0, 0, 'mod/'.SEARCH_TYPE_ASSIGN);
    }
}
