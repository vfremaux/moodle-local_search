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
use local_search\document;

/**
 * a class for representing searchable information (comments on data records)
 *
 */
class data_comment_document extends document {
    /**
     * constructor
     */
    public function __construct(&$comment, $courseid, $contextid) {

        // Generic information; required.
        $doc = new StdClass;
        $doc->docid     = $comment['id'];
        $doc->documenttype  = SEARCH_TYPE_DATA;
        $doc->itemtype      = 'comment';
        $doc->contextid     = $contextid;

        $doc->title     = get_string('commenton', 'search').' '.$comment['title'];
        $doc->date      = $comment['modified'];
        // Remove '(ip.ip.ip.ip)' from data record author field.
        $doc->author    = preg_replace('/\(.*?\)/', '', $comment['author']);
        $doc->contents  = $comment['content'];
        $doc->url       = data_document_wrapper::make_link($comment['dataid'], $comment['recordid']);

        // Module specific information; optional.
        $data = new StdClass();
        $data->database = $comment['dataid'];
        $data->recordid = $comment['recordid'];

        // Construct the parent class.
        parent::__construct($doc, $data, $courseid, $comment['groupid'], $comment['userid'], 'mod/'.SEARCH_TYPE_DATA);
    }
}
