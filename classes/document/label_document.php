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
 * @author Valery Fremaux [valery.fremaux@gmail.com] > 1.9
 * @contributor Tatsuva Shirai 20090530
 * @date 2008/03/31
 * @license http://www.gnu.org/copyleft/gpl.html GNU Public License
 *
 * document handling for labels
 * This file contains the mapping between a label and it's indexable counterpart.
 */
namespace local_search\document;

use local_search\document;
use StdClass;

/**
 * a class for representing searchable information
 *
 */
class label_document extends document {
    /**
     * Constructor.
     * @param array $label the label record as an array
     * @param int $contextid the module context id of the label
     */
    public function __construct(&$label, $contextid) {

        // Generic information; required.
        $doc = new StdClass();
        $doc->docid     = $label['id'];
        $doc->documenttype = SEARCH_TYPE_LABEL;
        $doc->itemtype     = 'label';
        $doc->contextid    = $contextid;

        $doc->title     = strip_tags($label['name']);
        $doc->date      = $label['timemodified'];
        $doc->author    = '';
        $doc->contents  = strip_tags($label['name'].' '.$label['intro']);
        $doc->url       = label_document_wrapper::make_link($label['course'], $contextid);

        // Module specific information; optional.
        $data = new StdClass();
        $data->label = $label['id'];

        // Construct the parent class.
        parent::__construct($doc, $data, $label['course'], 0, 0, 'mod/'.SEARCH_TYPE_LABEL);
    }
}
