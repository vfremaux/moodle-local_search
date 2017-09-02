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
 * @author Michael Champanis (mchampan) [cynnical@gmail.com], Valery Fremaux [valery.fremaux@club-internet.fr] > 1.8
 * @date 2008/03/31
 * @license http://www.gnu.org/copyleft/gpl.html GNU Public License
 *
 * Prints some basic statistics about the current index.
 * Does some diagnostics if you are logged in as an administrator.
 */

require('../../config.php');
require_once($CFG->dirroot.'/local/search/lib.php');

$url = new moodle_url('/local/search/stats.php');
$PAGE->set_url($url);

$context = context_system::instance();
$PAGE->set_context($context);

// Checks global search is enabled.

$config = get_config('local_search');

if ($CFG->forcelogin) {
    require_login();
}

if (empty($config->enable)) {
    print_error('globalsearchdisabled', 'local_search');
}

// Check for php5, but don't die yet.

require_once($CFG->dirroot.'/local/search/indexlib.php');

$indexinfo = new IndexInfo();

if (!$site = get_site()) {
    redirect(new moodle_url('/local/search/index.php'));
}

$strsearch = get_string('search', 'local_search');
$strquery  = get_string('statistics', 'local_search');

$PAGE->set_heading($site->fullname);
$PAGE->set_title("$strsearch");
$PAGE->set_pagelayout('admin');
$PAGE->navbar->add($strsearch, 'index.php');
$PAGE->navbar->add($strquery, 'stats.php');
$PAGE->navbar->add(get_string('runindexer', 'local_search'));

// Keep things pretty, even if php5 isn't available.

echo $OUTPUT->header();

echo $OUTPUT->box_start();
echo $OUTPUT->heading($strquery);

echo $OUTPUT->box_start();

$databasestr = get_string('database', 'local_search');
$documentsinindexstr = get_string('documentsinindex', 'local_search');
$deletionsinindexstr = get_string('deletionsinindex', 'local_search');
$documentsindatabasestr = get_string('documentsindatabase', 'local_search');
$databasestatestr = get_string('databasestate', 'local_search');

// This table is only for admins, shows index directory size and location.

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

    $admintable = new html_table();
    $admintable->tablealign = "center";
    $admintable->align = array ("right", "left");
    $admintable->wrap = array ("nowrap", "nowrap");
    $admintable->cellpadding = 5;
    $admintable->cellspacing = 0;
    $admintable->width = '500';

    $admintable->data[] = array("<strong>{$datadirectorystr}</strong>", '<em><strong>'.$indexinfo->path.'</strong></em>');
    $admintable->data[] = array($inindexdirectorystr, $indexinfo->filecount);
    $admintable->data[] = array($totalsizestr, $indexinfo->size);

    if ($indexinfo->time > 0) {
        $admintable->data[] = array(get_string('createdon', 'local_search'), date('r', $indexinfo->time));
    } else {
        $admintable->data[] = array(get_string('createdon', 'local_search'), '-');
    }

    if (!$indexinfo->valid($errors)) {
        $admintable->data[] = array("<strong>{$errorsstr}</strong>", '&nbsp;');
        foreach ($errors as $key => $value) {
            $admintable->data[] = array($key.' ... ', $value);
        }
    }

    echo html_writer::table($admintable);

    echo $OUTPUT->heading($solutionsstr);

    unset($admintable->data);
    if (isset($errors['dir'])) {
        $admintable->data[] = array($checkdirstr, $checkdiradvicestr);
    }
    if (isset($errors['db'])) {
        $admintable->data[] = array($checkdbstr, $checkdbadvicestr);
    }

    $url = new moodle_url('/local/search/tests/index.php');
    $admintable->data[] = array($runindexerteststr, '<a href="'.$url.'" target="_blank">tests/index.php</a>');
    $url = new moodle_url('/local/search/indexersplash.php');
    $admintable->data[] = array($runindexerstr, '<a href="'.$url.'" target="_blank">indexersplash.php</a>');

    echo html_writer::table($admintable);
}

// This is the standard summary table for normal users, shows document counts.

$table = new html_table;
$table->tablealign = 'center';
$table->align = array ('right', 'left');
$table->wrap = array ('nowrap', 'nowrap');
$table->cellpadding = 5;
$table->cellspacing = 0;
$table->width = '500';

$table->data[] = array("<strong>{$databasestr}</strong>", "<em><strong>{$CFG->prefix}".SEARCH_DATABASE_TABLE.'</strong></em>');

// Add extra fields if we're admin.

if (has_capability('moodle/site:config', context_system::instance())) {

    // Don't want to confuse users if the two totals don't match (hint: they should).
    $table->data[] = array($documentsinindexstr, $indexinfo->indexcount);

    /*
     * *cough* they should match if deletions were actually removed from the index,
     * as it turns out, they're only marked as deleted and not returned in search results
     */
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
