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

use StdClass;
use local_search\document;

/**
 * a class for representing searchable information
 *
 */
class assign_submission_document extends document {
    /**
     * constructor
     * @param object $submission
     * @param int $contextid
     */
    public function __construct($submission, $contextid) {

        // Generic information; required.
        $doc = new StdClass;
        $doc->docid         = $submission['fileid'];
        $doc->documenttype  = SEARCH_TYPE_ASSIGN;
        $doc->itemtype      = 'submission';
        $doc->contextid     = $contextid;

        // We cannot call userdate with relevant locale at indexing time.
        $doc->title         = "Submission: {$submission['assignmentname']}";
        $doc->date          = $submission['timecreated'];

        // Remove '(ip.ip.ip.ip)' from chat author list.
        $doc->author        = $submission['authors'];
        $doc->contents      = $submission['alltext'];
        $doc->url           = assign_document_wrapper::make_link($submission['cmid'], 'submission');

        // Module specific information; optional.
        $data = new StdClass;
        $data->assignment         = $submission['cmid'];
        $data->submission         = $submission['id'];

        // Construct the parent class.
        parent::__construct($doc, $data, $submission['courseid'], 0, 0, 'mod/'.SEARCH_TYPE_ASSIGN);
    }
}
