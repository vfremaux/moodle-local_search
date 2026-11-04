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
use moodle_url;
use StdClass;

defined('MOODLE_INTERNAL') || die();

require_once($CFG->dirroot.'/mod/resource/lib.php');

/**
 * a class for representing searchable information
 */
class resource_document extends document {
    public function __construct(&$resource, $contextid) {

        // Generic information; required.
        $doc = new StdClass;
        $doc->docid     = $resource['trueid'];
        $doc->documenttype = SEARCH_TYPE_RESOURCE;
        $doc->itemtype     = 'file';
        $doc->contextid    = $contextid;

        $doc->title     = strip_tags($resource['name']);
        $doc->date      = $resource['timemodified'];
        $doc->author    = '';
        $doc->contents  = strip_tags($resource['intro']).' '.strip_tags(@$resource['alltext']);
        $doc->url       = resource_document_wrapper::make_link($resource['id']);

        // Module specific information; optional.
        $data = new StdClass;
        $data = [];

        // Construct the parent class.
        parent::__construct($doc, $data, $resource['course'], 0, 0, 'mod/'.SEARCH_TYPE_RESOURCE);
    }
}
