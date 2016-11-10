<?php

////////////////////////////////////////////////////////////////////////////////
//  Code fragment to define the search version etc.
//  This fragment is called by /admin/index.php
//  
//  Consider it is not handled by Moodle core upgrade code. 
//  Just for identification
////////////////////////////////////////////////////////////////////////////////

defined('MOODLE_INTERNAL') || die;

$plugin->version  = 2015072400;
$plugin->requires = 2012120304;  // Requires this Moodle version
$plugin->component = 'local_search';

// Non moodle attributes
$plugin->codeincrement = '2.7.0000';
