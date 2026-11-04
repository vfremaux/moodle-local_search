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
 * @date 2008/03/31
 * @license http://www.gnu.org/copyleft/gpl.html GNU Public License
 *
 * document handling for glossary activity module
 * This file contains a mapping between a glossary entry and it's indexable counterpart,
 *
 * Functions for iterating and retrieving the necessary records are now also included
 * in this file, rather than mod/glossary/lib.php
 *
 */
namespace local_search\document;

use context_course;
use context_module;
use local_search\document;
use moodle_url;
use StdClass;

/**
 * a class for representing searchable information
 * comments are stroed into the gneeral mdl_comments table
 */
class glossary_comment_document extends document {

    /**
     * document constructor
     */
    public function __construct(&$entry, $courseid, $contextid) {
        global $DB;

        // Generic information; required.
        $doc = new StdClass();
        $doc->docid         = $entry['id'];
        $doc->documenttype  = SEARCH_TYPE_GLOSSARY;
        $doc->itemtype      = 'comment';
        $doc->contextid     = $contextid;

        $doc->title     = get_string('commenton', 'search').' '.$entry['concept'];
        $doc->date      = $entry['timemodified'];

        if ($entry['userid']) {
            $user = $DB->get_record('user', ['id' => $entry['userid']]);
        }
        $doc->author    = ($user) ? fullname($user) : '';
        $doc->contents  = strip_tags($entry['content']);
        $doc->url       = glossary_document_wrapper::make_link($entry['entryid']);

        // Module specific information; optional.
        $data = new Stdclass;
        $data->glossary = $entry['glossaryid'];
        $data->entryid = $entry['entryid'];

        // Construct the parent class.
        parent::__construct($doc, $data, $courseid, -1, $entry['userid'], 'mod/'.SEARCH_TYPE_GLOSSARY);
    }
}
