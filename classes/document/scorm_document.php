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
 * @author Valery Fremaux [valery.fremaux@gmail.com] > 2.0
 * @author Michael Campanis (mchampan) [cynnical@gmail.com], Valery Fremaux [valery.fremaux@gmail.com] > 1.8
 * @contributor Tatsuva Shirai 20090530
 * @date 2008/03/31
 * @license http://www.gnu.org/copyleft/gpl.html GNU Public License
 *
 * document handling for all resources
 * This file contains the mapping between a resource and it's indexable counterpart,
 *
 * Functions for iterating and retrieving the necessary records are now also included
 * in this file, rather than mod/resource/lib.php
 */
namespace local_search\document;

use context_course;
use context_module;
use local_search\document;
use moodle_exception;
use moodle_url;
use StdClass;

/**
 * a class for representing searchable information
 */
class scorm_document extends document {
    public function __construct(&$scorm, $contextid) {

        // Generic information; required.
        $doc = new StdClass;
        $doc->docid     = $scorm['trueid'];
        $doc->documenttype = SEARCH_TYPE_SCORM;
        $doc->itemtype     = '';
        $doc->contextid    = $contextid;

        $doc->title     = strip_tags($scorm['name']);
        $doc->date      = $scorm['timemodified'];
        $doc->author    = '';
        $doc->contents  = strip_tags($scorm['intro']).' '.strip_tags($scorm['alltext']);
        $doc->url       = scorm_document_wrapper::make_link($scorm['id']);

        if (!array_key_exists('alltext', $scorm)) {
            debugging("Empty text");
            throw new moodle_exception(print_r($scorm, true));
        }

        // Module specific information; optional.
        $data = new StdClass;
        $data = [];

        // Construct the parent class.
        parent::__construct($doc, $data, $scorm['course'], 0, 0, 'mod/'.SEARCH_TYPE_SCORM);
    }
}
