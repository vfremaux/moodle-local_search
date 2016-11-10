<?php
/*
* Moodle global search engine
* This is a special externalized code for cron handling in PHP5.
* Should never be called by a php 4.3.0 implementation. 
*/
require('../../config.php');
require_once($CFG->dirroot.'/local/search/lib.php');

echo '<pre>';
echo "Moodle 2 Plain text indexer\n";
echo "V. 2.6 20141025\n";
echo "Cron differential Update\n";
echo "-------------------------------------\n";
echo "\n";

try{
    // overrides php limits
    $maxtimelimit = ini_get('max_execution_time');
    ini_set('max_execution_time', 1200);
    $maxmemoryamount = ini_get('memory_limit');
    ini_set('memory_limit', '1G');

    mtrace("\n--DELETE----");
    require($CFG->dirroot.'/local/search/delete.php');
    mtrace("--UPDATE----");
    require($CFG->dirroot.'/local/search/update.php');
    mtrace("--ADD-------");
    require($CFG->dirroot.'/local/search/add.php');
    mtrace("------------");
    //mtrace("cron finished.</pre>");
    mtrace('done');
} catch(Exception $ex) {
    mtrace('Fatal exception from Lucene subsystem. Search engine may not have been updated.');
    mtrace($ex);
}

echo '</pre>';
