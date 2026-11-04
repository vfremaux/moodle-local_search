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
 * document handling for all pages
 * This file contains the mapping between a page and it's indexable counterpart,
 *
 * Functions for iterating and retrieving the necessary records are now also included
 * in this file, rather than mod/page/lib.php
 */
namespace local_search\document;

use context_course;
use context_module;
use local_search\document;
use moodle_url;
use StdClass;

/**
 * a class for representing searchable information
 *
 */
class page_document extends document {

    public function __construct(&$page, $contextid) {

        // Generic information; required.
        $doc = new StdClass;
        $doc->docid         = $page['id'];
        $doc->documenttype  = SEARCH_TYPE_PAGE;
        $doc->itemtype      = 'page';
        $doc->contextid     = $contextid;

        $doc->title     = strip_tags($page['name']);
        $doc->date      = $page['timemodified'];
        $doc->author    = '';
        $doc->contents  = strip_tags($page['intro']).' '.strip_tags($page['content']);
        $doc->url       = page_document_wrapper::make_link($page['id']);

        // Module specific information; optional.
        $data = new StdClass;
        $data = [];

        // Construct the parent class.
        parent::__construct($doc, $data, $page['course'], 0, 0, 'mod/'.SEARCH_TYPE_PAGE);
    }
}
