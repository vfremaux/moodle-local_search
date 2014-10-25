<?php
/**
 * Global Search Engine for Moodle
 *
 * @package search
 * @category core
 * @subpackage search_engine
 * @author Michael Champanis (mchampan) [cynnical@gmail.com], Valery Fremaux [valery.fremaux@club-internet.fr] > 1.8
 * @date 2008/03/31
 * @license http://www.gnu.org/copyleft/gpl.html GNU Public License
 *
 * Prints some basic statistics about the current index.
 * Does some diagnostics if you are logged in as an administrator.
 * 
 */

require('../../config.php');
require_once("{$CFG->dirroot}/local/search/lib.php");

$url = new moodle_url('/local/search/stats.php');
$PAGE->set_url($url);

$context = context_system::instance();
$PAGE->set_context($context);

/// checks global search is enabled

if ($CFG->forcelogin) {
    require_login();
}

if (empty($CFG->enableglobalsearch)) {
    error(get_string('globalsearchdisabled', 'local_search'));
}

/// check for php5, but don't die yet

require_once("{$CFG->dirroot}/local/search/indexlib.php");
    
$indexinfo = new IndexInfo();

if (!$site = get_site()) {
    redirect("index.php");
} 

$strsearch = get_string('search', 'local_search');
$strquery  = get_string('statistics', 'local_search'); 

$PAGE->set_heading("$site->fullname");
$PAGE->set_title("$strsearch");
$PAGE->set_pagelayout('admin');
$PAGE->navbar->add($strsearch, 'index.php');
$PAGE->navbar->add($strquery, 'stats.php');
$PAGE->navbar->add(get_string('runindexer','local_search'));

// keep things pretty, even if php5 isn't available

echo $OUTPUT->header();

echo $OUTPUT->box_start();
echo $OUTPUT->heading($strquery);

echo $OUTPUT->box_start();

$databasestr = get_string('database', 'local_search');
$documentsinindexstr = get_string('documentsinindex', 'local_search');
$deletionsinindexstr = get_string('deletionsinindex', 'local_search');
$documentsindatabasestr = get_string('documentsindatabase', 'local_search');
$databasestatestr = get_string('databasestate', 'local_search');

/// this table is only for admins, shows index directory size and location

if (has_capability('moodle/site:config', context_system::instance())) {
    $datadirectorystr = get_string('datadirectory', 'local_search');
    $inindexdirectorystr = get_string('filesinindexdirectory', 'local_search');
    $totalsizestr = get_string('totalsize', 'local_search');
    $errorsstr = get_string('errors', 'local_search');
    $solutionsstr = get_string('solutions', 'local_search');
    $checkdirstr = get_string('checkdir', 'local_search');
    $checkdbstr = get_string('checkdb', 'local_search');
    $checkdiradvicestr = get_string('checkdiradvice', 'local_search');
    $checkdbadvicestr = get_string('checkdbadvice', 'local_search');
    $runindexerteststr = get_string('runindexertest', 'local_search');
    $runindexerstr = get_string('runindexer', 'local_search');
    
    $admin_table = new html_table();
    $admin_table->tablealign = "center";
    $admin_table->align = array ("right", "left");
    $admin_table->wrap = array ("nowrap", "nowrap");
    $admin_table->cellpadding = 5;
    $admin_table->cellspacing = 0;
    $admin_table->width = '500';

    $admin_table->data[] = array("<strong>{$datadirectorystr}</strong>", '<em><strong>'.$indexinfo->path.'</strong></em>');
    $admin_table->data[] = array($inindexdirectorystr, $indexinfo->filecount);
    $admin_table->data[] = array($totalsizestr, $indexinfo->size);

    if ($indexinfo->time > 0) {
        $admin_table->data[] = array(get_string('createdon', 'local_search'), date('r', $indexinfo->time));
    } 
    else {
        $admin_table->data[] = array(get_string('createdon', 'local_search'), '-');
    } 

    if (!$indexinfo->valid($errors)) {
        $admin_table->data[] = array("<strong>{$errorsstr}</strong>", '&nbsp;');
        foreach ($errors as $key => $value) {
            $admin_table->data[] = array($key.' ... ', $value);
        } 
    }

    echo html_writer::table($admin_table);

    echo $OUTPUT->heading($solutionsstr);
    
    unset($admin_table->data);
    if (isset($errors['dir'])) {
        $admin_table->data[] = array($checkdirstr, $checkdiradvicestr);
    } 
    if (isset($errors['db'])) {
        $admin_table->data[] = array($checkdbstr, $checkdbadvicestr);
    } 
    
    $admin_table->data[] = array($runindexerteststr, '<a href="tests/index.php" target="_blank">tests/index.php</a>');
    $admin_table->data[] = array($runindexerstr, '<a href="indexersplash.php" target="_blank">indexersplash.php</a>');
    
    echo html_writer::table($admin_table);
} 

// this is the standard summary table for normal users, shows document counts

$table = new html_table;
$table->tablealign = "center";
$table->align = array ("right", "left");
$table->wrap = array ("nowrap", "nowrap");
$table->cellpadding = 5;
$table->cellspacing = 0;
$table->width = '500';

$table->data[] = array("<strong>{$databasestr}</strong>", "<em><strong>{$CFG->prefix}".SEARCH_DATABASE_TABLE.'</strong></em>');

// add extra fields if we're admin

if (has_capability('moodle/site:config', context_system::instance())) {
    //don't want to confuse users if the two totals don't match (hint: they should)
    $table->data[] = array($documentsinindexstr, $indexinfo->indexcount);
    
    //*cough* they should match if deletions were actually removed from the index,
    //as it turns out, they're only marked as deleted and not returned in search results
    $table->data[] = array($deletionsinindexstr, (int)$indexinfo->indexcount - (int)$indexinfo->dbcount);
} 

$table->data[] = array($documentsindatabasestr, $indexinfo->dbcount);

foreach ($indexinfo->types as $key => $value) {
    $table->data[] = array(get_string('documentsfor', 'local_search') . " '".get_string('modulenameplural', $key)."'", $value);
} 

echo $OUTPUT->heading($databasestatestr);
echo html_writer::table($table);

echo $OUTPUT->box_end();
echo $OUTPUT->box_end();
echo $OUTPUT->footer();
