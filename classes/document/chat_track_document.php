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
 * document handling for chat activity module
 * This file contains the mapping between a chat history and it's indexable counterpart,
 *
 * Functions for iterating and retrieving the necessary records are now also included
 * in this file, rather than mod/chat/lib.php
 *
 */
namespace local_search\document;

use StdClass;
use local_search\document;

defined('MOODLE_INTERNAL') || die();

require_once($CFG->dirroot.'/mod/chat/lib.php');

/**
 * a class for representing searchable information
 *
 */
class chat_track_document extends document {
    /**
     * constructor
     */
    public function __construct(&$chatsession, $chatid, $chatmoduleid, $courseid, $groupid, $contextid) {
        // Generic information; required.
        $doc = new StdClass();
        $doc->docid         = $chatid.'-'.$chatsession['sessionstart'].'-'.$chatsession['sessionend'];
        $doc->documenttype  = SEARCH_TYPE_CHAT;
        $doc->itemtype      = 'session';
        $doc->contextid     = $contextid;

        $duration           = $chatsession['sessionend'] - $chatsession['sessionstart'];
        // We cannot call userdate with relevant locale at indexing time.
        $doc->title         = get_string('chatreport', 'chat').' '.get_string('openedon', 'search');
        $doc->title         .= ' TT_'.$chatsession['sessionstart'].'_TT ('.get_string('duration', 'search');
        $doc->title         .= ' : '.get_string('numseconds', '', $duration).')';
        $doc->date          = $chatsession['sessionend'];

        // Remove '(ip.ip.ip.ip)' from chat author list.
        $doc->author        = preg_replace('/\(.*?\)/', '', $chatsession['authors']);
        $doc->contents      = $chatsession['content'];
        $doc->url           = chat_document_wrapper::make_link($chatmoduleid, $chatsession['sessionstart'],
                                                               $chatsession['sessionend']);

        // Module specific information; optional.
        $data = new StdClass();
        $data->chat         = $chatid;

        // Construct the parent class.
        parent::__construct($doc, $data, $courseid, $groupid, 0, 'mod/'.SEARCH_TYPE_CHAT);
    }
}

