<?php
// This file keeps track of upgrades to 
// the search block
//
// Sometimes, changes between versions involve
// alterations to database structures and other
// major things that may break installations.
//
// The upgrade function in this file will attempt
// to perform all the necessary actions to upgrade
// your older installtion to the current version.
//
// If there's something it cannot do itself, it
// will tell you what you need to do.
//
// The commands in here will all be database-neutral,
// using the functions defined in lib/ddllib.php

defined('MOODLE_INTERNAL') || die();

function xmldb_local_search_upgrade($oldversion=0) {

    global $CFG, $THEME, $db;

    $result = true;

    if ($oldversion >= 2016021300) {
        $blockconfig = get_config('block_search');
        foreach($blockconfig as $key => $value) {
            if (!in_array($key, array('text', 'button'))) {
                set_config($key, $value, 'local_config');
                set_config($key, null, 'block_config');
            }
        }
    }

    return $result;
}
