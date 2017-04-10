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
 * @author Michael Champanis (mchampan) [cynnical@gmail.com], Valery Fremaux [valery.fremaux@club-internet.fr] > 1.8
 * @date 2008/03/31
 * @license http://www.gnu.org/copyleft/gpl.html GNU Public License
 *
 * The query page - accepts a user-entered query string and returns results.
 *
 * Queries are boolean-aware, e.g.:
 *
 * '+'      term required
 * '-'      term must not be present
 * ''       (no modifier) term's presence increases rank, but isn't required
 * 'field:' search this field
 *
 * Examples:
 *
 * 'earthquake +author:michael'
 *   Searches for documents written by 'michael' that contain 'earthquake'
 *
 * 'earthquake +doctype:wiki'
 *   Search all wiki pages for 'earthquake'
 *
 * '+author:helen +author:foster'
 *   All articles written by Helen Foster
 *
 */
require('../../config.php');
require_once($CFG->dirroot.'/local/search/lib.php');
require_once($CFG->dirroot.'/local/search/querylib.php');

$pagenum  = optional_param('page', -1, PARAM_INT);
$pages        = ($pagenum == -1) ? false : true;
$advanced     = (optional_param('a', '0', PARAM_INT) == '1') ? true : false;
$querystring = optional_param('query_string', '', PARAM_CLEAN);

$url = new moodle_url('/local/search/query.php', array('query_string' => $querystring));
$PAGE->set_url($url);

$context = context_system::instance();
$PAGE->set_context($context);

if ($CFG->forcelogin) {
    require_login();
}

$config = get_config('local_search');

if (empty($config->enable)) {
    print_error('globalsearchdisabled', 'local_search');
}

$adv = new StdClass();

// Discard harmfull searches.

if (preg_match("/^[\*\?]+$/", $querystring)) {
    $querystring = '';
    $error = get_string('fullwildcardquery', 'local_search');
}

if ($pages && isset($_SESSION['search_advanced_query'])) {
    // If both are set, then we are busy browsing through the result pages of an advanced query.
    $adv = unserialize($_SESSION['search_advanced_query']);
} else if ($advanced) {

    // Otherwise we are dealing with a new advanced query.
    unset($_SESSION['search_advanced_query']);

    // Chars to strip from strings (whitespace).
    $chars = " \t\n\r\0\x0B,-+";

    // Retrieve advanced query variables.
    $adv->mustappear  = trim(optional_param('mustappear', '', PARAM_TEXT), $chars);
    $adv->notappear   = trim(optional_param('notappear', '', PARAM_TEXT), $chars);
    $adv->canappear   = trim(optional_param('canappear', '', PARAM_TEXT), $chars);
    $adv->module      = optional_param('module', '', PARAM_TEXT);
    $adv->title       = trim(optional_param('title', '', PARAM_TEXT), $chars);
    $adv->author      = trim(optional_param('author', '', PARAM_TEXT), $chars);
}

if ($advanced) {
    // Parse the advanced variables into a query string.
    // TODO: move out to external query class (QueryParse?).

    $querystring = '';

    // Get all available module types adding third party modules.
    $moduletypes = array_merge(array('all'), array_values(search_get_document_types()));
    $moduletypes = array_merge($moduletypes, array_values(search_get_document_types('X_SEARCH_TYPE')));
    $adv->module = in_array($adv->module, $moduletypes) ? $adv->module : 'all';

    // Convert '1 2' into '+1 +2' for required words field.
    if (strlen(trim($adv->mustappear)) > 0) {
        $querystring  = ' +'.implode(' +', preg_split("/[\s,;]+/", $adv->mustappear));
    }

    // Convert '1 2' into '-1 -2' for not wanted words field.
    if (strlen(trim($adv->notappear)) > 0) {
        $querystring .= ' -'.implode(' -', preg_split("/[\s,;]+/", $adv->notappear));
    }

    // This field is left untouched, apart from whitespace being stripped.
    if (strlen(trim($adv->canappear)) > 0) {
        $querystring .= ' '.implode(' ', preg_split("/[\s,;]+/", $adv->canappear));
    }

    // Add module restriction.
    $doctypestr = 'doctype';
    $titlestr = 'title';
    $authorstr = 'author';
    if ($adv->module != 'all') {
        $querystring .= " +{$doctypestr}:".$adv->module;
    }

    // Create title search string.
    if (strlen(trim($adv->title)) > 0) {
        $querystring .= " +{$titlestr}:".implode(" +{$titlestr}:", preg_split("/[\s,;]+/", $adv->title));
    }

    // Create author search string.
    if (strlen(trim($adv->author)) > 0) {
        $querystring .= " +{$authorstr}:".implode(" +{$authorstr}:", preg_split("/[\s,;]+/", $adv->author));
    }

    // Save our options if the query is valid.
    if (!empty($querystring)) {
        $_SESSION['search_advanced_query'] = serialize($adv);
    }
}

// Normalise page number.
if ($pagenum < 1) {
    $pagenum = 1;
}

// Run the query against the index ensuring internal coding works in UTF-8.
Zend_Search_Lucene_Analysis_Analyzer::setDefault(new Zend_Search_Lucene_Analysis_Analyzer_Common_Utf8_CaseInsensitive());
$sq = new SearchQuery($querystring, $pagenum, 10, false);

if (!$site = get_site()) {
    redirect($CFG->wwwroot);
}

$strsearch = get_string('search', 'local_search');
$strquery  = get_string('enteryoursearchquery', 'local_search');

$PAGE->set_title("$site->fullname");
$PAGE->set_heading("$site->shortname: $strsearch: $strquery");
$PAGE->navbar->add($strsearch, new moodle_url('/local/search/index.php'));
$PAGE->navbar->add($strquery);

$renderer = $PAGE->get_renderer('local_search');

echo $OUTPUT->header();

if (!empty($error)) {
    echo $OUTPUT->notification($error);
}

echo $OUTPUT->box_start();
echo $OUTPUT->heading($strquery);

echo $OUTPUT->box_start();

$vars = get_object_vars($adv);

if (isset($vars)) {
    foreach ($vars as $key => $value) {
        // Htmlentities breaks non-ascii chars.
        $adv->key = stripslashes($value);
    }
}

$url = new moodle_url('/local/search/query.php');
echo '<form id="query" method="get" action="'.$url.'">';

if (!$advanced) {
    echo $renderer->simple_form($querystring);
} else {
    echo $OUTPUT->box_start();

    echo $renderer->advanced_form($adv);
    echo $OUTPUT->box_end();
}

echo '</form>';
echo '<br/>';

echo '<div align="center">';
print_string('searching', 'local_search').': ';

if ($sq->is_valid_index()) {
    // Use cached variable to show up-to-date index size (takes deletions into account).
    echo 0 + @$config->index_size;
} else {
    print '0';
}

echo ' ';
print_string('documents', 'local_search');
echo '.';

if (!$sq->is_valid_index() && has_capability('moodle/site:config', context_system::instance())) {
    echo '<p>'.get_string('noindexmessage', 'local_search').'<a href="indexersplash.php"> ';
    echo get_string('createanindex', 'local_search').'</a></p>'."\n";
}

echo '</div>';

echo $OUTPUT->box_end();

// Prints all the results in a box.

if ($sq->is_valid()) {
    echo $OUTPUT->box_start();

    search_stopwatch();
    $hitcount = $sq->count();

    echo "<br />";

    echo $hitcount.' '.get_string('resultsreturnedfor', 'local_search') . " '".s($querystring)."'.";
    echo '<br />';

    if ($hitcount > 0) {
        $pagelinks = $sq->page_numbers();
        $hits = $sq->results();

        if ($advanced) {
            /*
             * if in advanced mode, search options are saved in the session, so
             * we can remove the query string var from the page links, and replace
             * it with a=1 (Advanced = on) instead
             */
            $pagelinks = preg_replace("/query_string=[^&]+/", 'a=1', $pagelinks);
        }

        echo '<ol>';

        $searchables = search_collect_searchables(false, false);

        foreach ($hits as $listing) {

            if ($listing->doctype == 'user') {
                // A special handle for users.
                $user = $DB->get_record('user', array('id' => $listing->userid));
                $listing->icon = $OUTPUT->user_picture($user);
            } else {
                $iconpath = $OUTPUT->pix_url('icon', $listing->doctype);
                $listing->icon = '<img align="top" src="'.$iconpath.'" class="activityicon" alt=""/>';
            }
            $coursename = $DB->get_field('course', 'fullname', array('id' => $listing->courseid));
            $courseword = mb_convert_case(get_string('course', 'moodle'), MB_CASE_LOWER, 'UTF-8');
            $listing->course = ($listing->doctype != 'user') ? '<strong> ('.$courseword.': \''.$coursename.'\')</strong>' : '';

            $searchableinstance = $searchables[$listing->doctype];
            if ($searchableinstance->location == 'internal') {
                include_once($CFG->dirroot.'/local/search/documents/'.$listing->doctype.'_document.php');
            } else {
                include_once($CFG->dirroot.'/'.$searchableinstance->location.'/'.$listing->doctype.'/search_document.php');
            }
            $wrapperclass = $listing->doctype.'_document_wrapper';
            $listing->title = $wrapperclass->link_post_processing($listing->title);

            echo $renderer->search_result($listing);
        }
        echo '</ol>';
        echo $pagelinks;
    }
    echo $OUTPUT->box_end();

    echo '<div align="center">';

    print_string('ittook', 'local_search');
    search_stopwatch();
    print_string('tofetchtheseresults', 'local_search');
    echo '.';
    echo '</div>';

}
echo $OUTPUT->box_end();

echo $OUTPUT->footer();
