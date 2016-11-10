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

defined('MOODLE_INTERNAL') || die();

/**
 * Global Search Engine for Moodle
 *
 * @package local_search
 * @author Michael Champanis (mchampan) [cynnical@gmail.com], Valery Fremaux [valery.fremaux@club-internet.fr] > 1.8
 * @date 2008/03/31
 * @license http://www.gnu.org/copyleft/gpl.html GNU Public License
 *
 * Index info class
 *
 * Used to retrieve information about an index.
 * Has methods to check for valid database and data directory,
 * and the index itself.
 */

require_once($CFG->dirroot.'/local/search/lib.php');
require_once($CFG->dirroot.'/local/search/Zend/Search/Lucene.php');

/**
 * main class for searchable information in the Lucene index 
 */
class IndexInfo {

    private $path,        //index data directory
            $size,        //size of directory (i.e. the whole index)
            $filecount,   //number of files
            $indexcount,  //number of docs in index
            $dbcount,     //number of docs in db
            $types,       //array of [document types => count]
            $complete,    //is index completely formed?
            $time;        //date index was generated

    public function __construct($path = SEARCH_INDEX_PATH) {
        global $CFG, $DB;

        $this->path = $path;

        // Test to see if there is a valid index on disk, at the specified path.

        $config = get_config('local_search');

        try {
            $test_index = new Zend_Search_Lucene($this->path, false);
            $validindex = true;
        } catch(Exception $e) {
            $validindex = false;
        }

        // Retrieve file system info about the index if it is valid.
        if ($validindex) {
            $this->size = display_size(get_directory_size($this->path));
            $index_dir  = get_directory_list($this->path, '', false, false);
            $this->filecount = count($index_dir);
            $this->indexcount = $test_index->count();
        } else {
            $this->size = 0;
            $this->filecount = 0;
            $this->indexcount = 0;
        }

        // Retrieve database information if it does.
        $db_exists = true;

        // Total documents.
        $this->dbcount = $DB->count_records(SEARCH_DATABASE_TABLE);

        // Individual document types.
        // $types = search_get_document_types();
        $types = search_collect_searchables(true, false);
        sort($types);

        foreach ($types as $type) {
            $c = $DB->count_records(SEARCH_DATABASE_TABLE, array('doctype' => $type));
            $this->types[$type] = (int)$c;
        }

        //check if the busy flag is set
        if (isset($config->indexer_busy) && $config->indexer_busy == '1') {
            $this->complete = false;
        } else {
            $this->complete = true;
        }

        //get the last run date for the indexer
        if ($this->valid() && $config->indexer_run_date) {
            $this->time = $config->indexer_run_date;
        } else {
          $this->time = 0;
        }
    }

    /**
     * returns false on error, and the error message via referenced variable $err
     * @param array $err array of errors
     */
    public function valid(&$err = null) {
        $err = array();
        $ret = true;

        if (!$this->is_valid_dir()) {
            $err['dir'] = get_string('invalidindexerror', 'local_search');
            $ret = false;
        }

        if (!$this->is_valid_db()) {
            $err['db'] = get_string('emptydatabaseerror', 'local_search');
            $ret = false;
        }

        if (!$this->complete) {
            $err['index'] = get_string('uncompleteindexingerror','local_search');
            $ret = false;
        }

        return $ret;
    }

    /**
     * is the index dir valid
     *
     */
    public function is_valid_dir() {
        if ($this->filecount > 0) {
            return true;
        } else {
            return false;
        }
    }

    /**
     * is the db table valid
     *
     */
    public function is_valid_db() {
        if ($this->dbcount > 0) {
            return true;
        } else {
            return false;
        }
    }

    /**
     * shorthand get method for the class variables
     * @param object $var
     */
    public function __get($var) {
        if (in_array($var, array_keys(get_class_vars(get_class($this))))) {
            return $this->$var;
        }
    }
}

/**
 * DB Index control class
 *
 * Used to control the search index database table
 */
class IndexDBControl {

    /**
     * add a document record to the table
     * @param document must be a Lucene SearchDocument instance
     * @uses db, CFG
     */
    public function addDocument($document=null) {
        global $DB, $CFG;

        if ($document == null) {
             return false;
        }

        // Object to insert into db.
        $doc = new StdClass;
        $doc->doctype   = $document->doctype;
        $doc->docid     = $document->docid;
        $doc->itemtype  = $document->itemtype;
        $doc->title     = $document->title;
        $doc->url       = ''.$document->url;
        $doc->updated   = time();
        $doc->docdate   = $document->date;
        $doc->courseid  = $document->course_id;
        $doc->groupid   = $document->group_id;

        if ($doc->groupid < 0) $doc->groupid = 0;

        //insert summary into db
        $id = $DB->insert_record(SEARCH_DATABASE_TABLE, $doc);

        return $id;
    }

    /**
     * remove a document record from the index
     * @param document must be a Lucene document instance, or at least a dbid enveloppe
     * @uses db
     */
    public function delDocument($document) {
        global $DB;

        $DB->delete_records(SEARCH_DATABASE_TABLE, array('id' => $document->dbid));
    }
}