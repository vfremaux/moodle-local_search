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

require_once($CFG->dirroot.'/local/search/lib.php');

// settings default init
if (is_dir($CFG->dirroot.'/local/adminsettings')) {
    // Integration driven code 
    require_once($CFG->dirroot.'/local/adminsettings/lib.php');
    list($hasconfig, $hassiteconfig, $capability) = local_adminsettings_access();
} else {
    // Standard Moodle code
    $capability = 'moodle/site:config';
    $hasconfig = $hassiteconfig = has_capability($capability, context_system::instance());
}

if ($hassiteconfig) {

    $settings = new admin_settingpage('local_search', get_string('pluginname', 'local_search'));

    $defaultfiletypes = "PDF,TXT,HTML,PPT,XML,DOC,HTM";

    $convertoptions = array(
        '-1' => get_string('fromutf', 'local_search'),
        '0' => get_string('nochange', 'local_search'),
        '1' => get_string('toutf', 'local_search'),
    );
    $settings->add(new admin_setting_configselect('local_search/utf8dir', get_string('configutf8transcoding', 'local_search'), get_string('configutf8transcoding_desc', 'local_search'), 0, $convertoptions));

    $settings->add(new admin_setting_configcheckbox('local_search/softlock', get_string('configusingsoftlock', 'local_search'), get_string('configusingsoftlock_desc', 'local_search'), 0));

    $settings->add(new admin_setting_configcheckbox('local_search/enable_file_indexing', get_string('configenablefileindexing', 'local_search'), get_string('configenablefileindexing', 'local_search'), get_string('configenablefileindexing_desc', 'local_search'), 0));

    $settings->add(new admin_setting_configtext('local_search/filetypes', get_string('configfiletypes', 'local_search'), get_string('configfiletypes_desc', 'local_search'), $defaultfiletypes, PARAM_TEXT));

    $settings->add(new admin_setting_configcheckbox('local_search/usemoodleroot', get_string('usemoodleroot', 'local_search'), get_string('usemoodleroot_desc', 'local_search'), 1));

    $settings->add(new admin_setting_configtext('local_search/limit_index_body', get_string('configlimitindexbody', 'local_search'), get_string('configlimitindexbody_desc', 'local_search'), 0, PARAM_TEXT));

    $settings->add(new admin_setting_heading('head1', get_string('pdfhandling', 'local_search'), ''));

    if ($CFG->ostype == 'WINDOWS'){
        $default= "lib/xpdf/win32/pdftotext.exe -eol dos -enc UTF-8 -q";
    } else {
        $default = "lib/xpdf/linux/pdftotext -enc UTF-8 -eol unix -q";
    }

    $settings->add(new admin_setting_configtext('local_search/pdf_to_text_cmd', get_string('configpdftotextcmd', 'local_search'), get_string('configpdftotextcmd_desc', 'local_search'), $default, PARAM_TEXT));

    $settings->add(new admin_setting_heading('head2', get_string('wordhandling', 'local_search'), ''));

    if ($CFG->ostype == 'WINDOWS') {
        $default = "lib/antiword/win32/antiword/antiword.exe";
    } else {
        $default = "lib/antiword/linux/usr/bin/antiword";
    }

    $settings->add(new admin_setting_configtext('local_search/word_to_text_cmd', get_string('configwordtotextcmd', 'local_search'), get_string('configwordtotextcmd_desc', 'local_search'), $default, PARAM_TEXT));

    if ($CFG->ostype == 'WINDOWS') {
        $default = 'HOME='.str_replace('/', '\\', $CFG->dirroot).'\\local\\search\\lib\\antiword\\win32';
    } else {
        $default = "ANTIWORDHOME={$CFG->dirroot}/local/search/lib/antiword/linux/usr/share/antiword";
    }

    $settings->add(new admin_setting_configtext('local_search/word_to_text_env', get_string('configwordtotextenv', 'local_search'), get_string('configwordtotextenv_desc', 'local_search'), $default, PARAM_TEXT));

    $types = explode(',', @$CFG->local_search_filetypes);
    if (!empty($types)) {
        foreach ($types as $type) {
            $utype = strtoupper($type);
            $type = strtolower($type);
            $type = trim($type);
            if (preg_match("/\\b$type\\b/i", $defaultfiletypes)) {
                continue;
            }
    
            $settings->add(new admin_setting_configcheckbox('local_search/'.$type.'_to_text_cmd', get_string('configtypetotxtcmd', 'local_search'), get_string('configtypetotxtcmd_desc', 'local_search'), 0));
            $settings->add(new admin_setting_configcheckbox('local_search/'.$type.'_to_text_env', get_string('configtypetotxtenv', 'local_search'), get_string('configtypetotxtenv_desc', 'local_search'), 0));
        }
    }

    $searchnames = search_collect_searchables(true);
    $searchable_list = implode("','", $searchnames);

    $settings->add(new admin_setting_heading('head3', get_string('searchdiscovery', 'local_search'), '<pre>'.$searchable_list.'</pre>'));

    $settings->add(new admin_setting_heading('head4', get_string('coresearchswitches', 'local_search'), ''));

    $settings->add(new admin_setting_configcheckbox('local_search/search_in_user', get_string('users'), get_string('users'), 1));

    $settings->add(new admin_setting_heading('head5', get_string('modulessearchswitches', 'local_search'), ''));

    $i = 0;
    $found_searchable_modules = 0;
    if ($modules = $DB->get_records_select('modules', " name IN ('{$searchable_list}') ", array(), 'name', 'id,name')) {
        foreach($modules as $module) {
            $i++;
            $keyname = "local_search/search_in_{$module->name}";
            $settings->add(new admin_setting_configcheckbox($keyname, get_string('pluginname', $module->name), get_string('pluginname', $module->name), 1));
            $found_searchable_modules = 1;
        }
    }

    $settings->add(new admin_setting_heading('head6', get_string('blockssearchswitches', 'local_search'), ''));

    $i = 0;
    $found_searchable_blocks = 0;
    if ($blocks = $DB->get_records_select('block', " name IN ('{$searchable_list}') ", array(), 'name', 'id,name')) {
        foreach($blocks as $block) {
            $i++;
            $keyname = "local_search/search_in_{$block->name}";
            $settings->add(new admin_setting_configcheckbox($keyname, get_string('pluginname', $block->name), get_string('pluginname', $block->name), 1));
            $found_searchable_modules = 1;
        }
    }

    $settings->add(new admin_setting_heading('head6', get_string('configenableglobalsearch', 'local_search'), ''));

    $settings->add(new admin_setting_configcheckbox('local_search/enable', get_string('configenableglobalsearch', 'local_search'), get_string('configenableglobalsearch_desc', 'local_search'), 0));

    $ADMIN->add('localplugins', $settings);
}