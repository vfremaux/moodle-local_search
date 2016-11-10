<?php
/*
* Moodle global search engine
* This is a special externalized code for cron handling in PHP5.
* Should never be called by a php 4.3.0 implementation. 
*/
define('CLI_SCRIPT', true);

$CLI_VMOODLE_PRECHECK = true; // force first config to be minimal

require(dirname(dirname(dirname(dirname(__FILE__)))).'/config.php'); // Global moodle config file.
require_once($CFG->dirroot.'/lib/clilib.php');

list($options, $unrecognized) = cli_get_params(
    array('help' => false, 'host' => false),
    array('h' => 'help', 'H' => 'host')
);

if ($unrecognized) {
    $unrecognized = implode("\n  ", $unrecognized);
    cli_error(get_string('cliunknowoption', 'admin', $unrecognized));
}

if ($options['help']) {
    $help =
"Scheduled cron tasks.

Options:
-H, --host            Virtual root to run for
-h, --help            Print out this help

Example:

Master or single Moodle
\$sudo -u www-data /usr/bin/php local/search/cli/make_index.php

Virtual Moodle
\$sudo -u www-data /usr/bin/php local/search/cli/make_index.php --host=http://vmoodle1.mydomain.fr

";
    echo $help;
    die;
}

if (!empty($options['host'])) {
    // Arms the vmoodle switching.
    echo('Arming for '.$options['host']."\n"); // mtrace not yet available.
    define('CLI_VMOODLE_OVERRIDE', $options['host']);
}

// Replay full config whenever. If vmoodle switch is armed, will switch now config.

require(dirname(dirname(dirname(dirname(__FILE__)))).'/config.php'); // Global moodle config file.
echo('Config check : playing for '.$CFG->wwwroot."\n");
require_once($CFG->dirroot.'/lib/cronlib.php');
require_once($CFG->dirroot.'/local/search/lib.php');

try {
    mtrace("\n--DELETE----");
    require($CFG->dirroot.'/local/search/delete.php');
    mtrace("--UPDATE----");
    require($CFG->dirroot.'/local/search/update.php');
    mtrace("--ADD-------");
    require($CFG->dirroot.'/local/search/add.php');
    mtrace("------------");
    //mtrace("cron finished.</pre>");
    mtrace('done');

    // set back normal values for php limits
} catch(Exception $ex) {
    mtrace('Fatal exception from Lucene subsystem. Search engine may not have been updated.');
    mtrace($ex);
}