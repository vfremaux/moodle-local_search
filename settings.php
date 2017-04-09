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

// Settings default init.
if (is_dir($CFG->dirroot.'/local/adminsettings')) {
    // Integration driven code.
    require_once($CFG->dirroot.'/local/adminsettings/lib.php');
    list($hasconfig, $hassiteconfig, $capability) = local_adminsettings_access();
} else {
    // Standard Moodle code.
    $capability = 'moodle/site:config';
    $hasconfig = $hassiteconfig = has_capability($capability, context_system::instance());
}

if ($hassiteconfig) {
    require_once($CFG->dirroot.'/local/search/lib.php');

    $settings = new admin_settingpage('local_search', get_string('pluginname', 'local_search'));
    $ADMIN->add('searchplugins', $settings);

    $defaultfiletypes = "PDF,TXT,HTML,PPT,XML,DOC,HTM,DOCX";

    $convertoptions = array(
        '-1' => get_string('fromutf', 'local_search'),
        '0' => get_string('nochange', 'local_search'),
        '1' => get_string('toutf', 'local_search'),
    );
    $key = 'local_search/utf8dir';
    $label = get_string('configutf8transcoding', 'local_search');
    $desc = get_string('configutf8transcoding_desc', 'local_search');
    $settings->add(new admin_setting_configselect($key, $label, $desc, 0, $convertoptions));

    $key = 'local_search/softlock';
    $label = get_string('configusingsoftlock', 'local_search');
    $desc = get_string('configusingsoftlock_desc', 'local_search');
    $settings->add(new admin_setting_configcheckbox($key, $label, $desc, 0));

    $key = 'local_search/enable_file_indexing';
    $label = get_string('configenablefileindexing', 'local_search');
    $desc = get_string('configenablefileindexing_desc', 'local_search');
    $settings->add(new admin_setting_configcheckbox($key, $label, $desc, 0));

    $key = 'local_search/filetypes';
    $label = get_string('configfiletypes', 'local_search');
    $desc = get_string('configfiletypes_desc', 'local_search');
    $settings->add(new admin_setting_configtext($key, $label, $desc, $defaultfiletypes, PARAM_TEXT));

    $key = 'local_search/usemoodleroot';
    $label = get_string('usemoodleroot', 'local_search');
    $desc = get_string('usemoodleroot_desc', 'local_search');
    $settings->add(new admin_setting_configcheckbox($key, $label, $desc, 1));

    $key = 'local_search/limit_index_body';
    $label = get_string('configlimitindexbody', 'local_search');
    $desc = get_string('configlimitindexbody_desc', 'local_search');
    $settings->add(new admin_setting_configtext($key, $label, $desc, 0, PARAM_TEXT));

    $settings->add(new admin_setting_heading('head1', get_string('pdfhandling', 'local_search'), ''));

    if ($CFG->ostype == 'WINDOWS') {
        $default = "xpdf/win32/pdftotext.exe -eol dos -enc UTF-8 -q";
    } else {
        $default = "xpdf/linux/bin64/pdftotext -enc UTF-8 -eol unix -q";
    }

    $key = 'local_search/pdf_to_text_cmd';
    $label = get_string('configpdftotextcmd', 'local_search');
    $desc = get_string('configpdftotextcmd_desc', 'local_search');
    $settings->add(new admin_setting_configtext($key, $label, $desc, $default, PARAM_TEXT));

    $settings->add(new admin_setting_heading('head2', get_string('wordhandling', 'local_search'), ''));

    if ($CFG->ostype == 'WINDOWS') {
        $default = "antiword/win32/antiword/antiword.exe";
    } else {
        $default = "antiword/linux/usr/bin/antiword";
    }

    $key = 'local_search/word_to_text_cmd';
    $label = get_string('configwordtotextcmd', 'local_search');
    $desc = get_string('configwordtotextcmd_desc', 'local_search');
    $settings->add(new admin_setting_configtext($key, $label, $desc, $default, PARAM_TEXT));

    if ($CFG->ostype == 'WINDOWS') {
        $default = 'HOME='.str_replace('/', '\\', $CFG->dirroot).'\\local\\search\\lib\\antiword\\win32';
    } else {
        $default = "ANTIWORDHOME={$CFG->dirroot}/local/search/lib/antiword/linux/usr/share/antiword";
    }

    $key = 'local_search/word_to_text_env';
    $label = get_string('configwordtotextenv', 'local_search');
    $desc = get_string('configwordtotextenv_desc', 'local_search');
    $settings->add(new admin_setting_configtext($key, $label, $desc, $default, PARAM_TEXT));

    if ($CFG->ostype == 'WINDOWS') {
        $default = "{No cenverter supported}";
    } else {
        $default = "antiword-xp-rb/antiword.rb";
    }

    $key = 'local_search/docx_to_text_cmd';
    $label = get_string('configdocxtotextcmd', 'local_search');
    $desc = get_string('configdocxtotextcmd_desc', 'local_search');
    $settings->add(new admin_setting_configtext($key, $label, $desc, $default, PARAM_TEXT));

    // Ensures it comes from real DB.
    $filetypes = $DB->get_field('config_plugins', 'value', array('plugin' => 'local_search', 'name' => 'filetypes'));
    $types = explode(',', $filetypes);
    if (!empty($types)) {
        foreach ($types as $type) {
            $utype = strtoupper($type);
            $type = strtolower($type);
            $type = trim($type);
            if (preg_match("/\\b$type\\b/i", $defaultfiletypes)) {
                continue;
            }

            $key = 'local_search/'.$type.'_to_text_cmd';
            $label = get_string('configtypetotxtcmd', 'local_search');
            $desc = get_string('configtypetotxtcmd_desc', 'local_search');
            $settings->add(new admin_setting_configtext($key, $label, $desc, 0));

            $key = 'local_search/'.$type.'_to_text_env';
            $label = get_string('configtypetotxtenv', 'local_search');
            $desc = get_string('configtypetotxtenv_desc', 'local_search');
            $settings->add(new admin_setting_configtext($key, $label, $desc, 0));
        }
    }

    $searchnames = search_collect_searchables(true);
    $searchablelist = implode("','", $searchnames);

    $html = '<pre>'.$searchablelist.'</pre>';
    $settings->add(new admin_setting_heading('head3', get_string('searchdiscovery', 'local_search'), $html));

    $settings->add(new admin_setting_heading('head4', get_string('coresearchswitches', 'local_search'), ''));

    $key = 'local_search/search_in_user';
    $label = get_string('users');
    $desc = get_string('users');
    $settings->add(new admin_setting_configcheckbox($key, $label, $desc, 1));

    $settings->add(new admin_setting_heading('head5', get_string('modulessearchswitches', 'local_search'), ''));

    $i = 0;
    $foundsearchablemodules = 0;
    if ($modules = $DB->get_records_select('modules', " name IN ('{$searchablelist}') ", array(), 'name', 'id,name')) {
        foreach ($modules as $module) {
            $i++;
            $key = "local_search/search_in_{$module->name}";
            $label = get_string('pluginname', $module->name);
            $desc = get_string('pluginname', $module->name);
            $settings->add(new admin_setting_configcheckbox($key, $label, $desc, 1));
            $foundsearchablemodules = 1;
        }
    }

    $settings->add(new admin_setting_heading('head6', get_string('blockssearchswitches', 'local_search'), ''));

    $i = 0;
    $foundsearchableblocks = 0;
    if ($blocks = $DB->get_records_select('block', " name IN ('{$searchablelist}') ", array(), 'name', 'id,name')) {
        foreach ($blocks as $block) {
            $i++;
            $key = "local_search/search_in_{$block->name}";
            $label = get_string('pluginname', $block->name);
            $desc = get_string('pluginname', $block->name);
            $settings->add(new admin_setting_configcheckbox($key, $label, $desc, 1));
            $foundsearchablemodules = 1;
        }
    }

    $settings->add(new admin_setting_heading('head6', get_string('configenableglobalsearch', 'local_search'), ''));

    $key = 'local_search/enable';
    $label = get_string('configenableglobalsearch', 'local_search');
    $desc = get_string('configenableglobalsearch_desc', 'local_search');
    $settings->add(new admin_setting_configcheckbox($key, $label, $desc, 0));
}