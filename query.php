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

$page_number  = optional_param('page', -1, PARAM_INT);
$pages        = ($page_number == -1) ? false : true;
$advanced     = (optional_param('a', '0', PARAM_INT) == '1') ? true : false;
$query_string = optional_param('query_string', '', PARAM_CLEAN);

$url = new moodle_url('/local/search/query.php', array('query_string' => $query_string));
$PAGE->set_url($url);

$context = context_system::instance();
$PAGE->set_context($context);

if ($CFG->forcelogin) {
    require_login();
}

if (empty($config->enable)) {
    print_error('globalsearchdisabled', 'local_search');
}

$adv = new Object();

// Discard harmfull searches.

if (preg_match("/^[\*\?]+$/", $query_string)) {
    $query_string = '';
    $error = get_string('fullwildcardquery','local_search');
}

if ($pages && isset($_SESSION['search_advanced_query'])) {
    // if both are set, then we are busy browsing through the result pages of an advanced query
    $adv = unserialize($_SESSION['search_advanced_query']);
} elseif ($advanced) {

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
    //parse the advanced variables into a query string
    //TODO: move out to external query class (QueryParse?)

    $query_string = '';

    // Get all available module types adding third party modules.
    $module_types = array_merge(array('all'), array_values(search_get_document_types()));
    $module_types = array_merge($module_types, array_values(search_get_document_types('X_SEARCH_TYPE')));
    $adv->module = in_array($adv->module, $module_types) ? $adv->module : 'all';

    // Convert '1 2' into '+1 +2' for required words field.
    if (strlen(trim($adv->mustappear)) > 0) {
        $query_string  = ' +'.implode(' +', preg_split("/[\s,;]+/", $adv->mustappear));
    }

    // Convert '1 2' into '-1 -2' for not wanted words field.
    if (strlen(trim($adv->notappear)) > 0) {
        $query_string .= ' -'.implode(' -', preg_split("/[\s,;]+/", $adv->notappear));
    }

    // This field is left untouched, apart from whitespace being stripped.
    if (strlen(trim($adv->canappear)) > 0) {
        $query_string .= ' '.implode(' ', preg_split("/[\s,;]+/", $adv->canappear));
    }

    // Add module restriction.
    $doctypestr = 'doctype';
    $titlestr = 'title';
    $authorstr = 'author';
    if ($adv->module != 'all') {
        $query_string .= " +{$doctypestr}:".$adv->module;
    }

    // Create title search string.
    if (strlen(trim($adv->title)) > 0) {
        $query_string .= " +{$titlestr}:".implode(" +{$titlestr}:", preg_split("/[\s,;]+/", $adv->title));
    }

    // Create author search string.
    if (strlen(trim($adv->author)) > 0) {
        $query_string .= " +{$authorstr}:".implode(" +{$authorstr}:", preg_split("/[\s,;]+/", $adv->author));
    }

    // Save our options if the query is valid.
    if (!empty($query_string)) {
        $_SESSION['search_advanced_query'] = serialize($adv);
    }
}

// Normalise page number.
if ($page_number < 1) {
    $page_number = 1;
}

// Run the query against the index ensuring internal coding works in UTF-8.
Zend_Search_Lucene_Analysis_Analyzer::setDefault(new Zend_Search_Lucene_Analysis_Analyzer_Common_Utf8_CaseInsensitive());
$sq = new SearchQuery($query_string, $page_number, 10, false);

if (!$site = get_site()) {
    redirect($CFG->wwwroot);
}

$strsearch = get_string('search', 'local_search');
$strquery  = get_string('enteryoursearchquery', 'local_search');

$PAGE->set_title("$site->fullname");
$PAGE->set_heading("$site->shortname: $strsearch: $strquery");
$PAGE->navbar->add($strsearch, new moodle_url('/local/search/index.php'));
$PAGE->navbar->add($strquery);

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
        // htmlentities breaks non-ascii chars
        $adv->key = stripslashes($value);
        //$adv->$key = stripslashes(htmlentities($value));
    }
}

$url = new moodle_url('/local/sharedresources/query.php');
echo '<form id="query" method="get" action="'.$url.'">';

if (!$advanced) {
    echo '<input type="text" name="query_string" length="50" value="'.$query_string.'" />';
    echo '&nbsp;<input type="submit" value="'.get_string('search', 'local_search').'" /> &nbsp;';
    $url = new moodle_url('/local/sharedresources/query.php', array('a' => 1));
    echo '<a href="'.$url.'">'.get_string('advancedsearch', 'local_search').'</a> |';
    $url = new moodle_url('/local/sharedresources/stats.php');
    echo '<a href="'.$url.'">'.get_string('statistics', 'local_search').'</a>';
} else {
    echo $OUTPUT->box_start();
  ?>
    <input type="hidden" name="a" value="<?php p($advanced); ?>"/>

    <table border="0" cellpadding="3" cellspacing="3">

    <tr>
      <td width="240"><?php print_string('thesewordsmustappear', 'local_search') ?>:</td>
      <td><input type="text" name="mustappear" length="50" value="<?php p($adv->mustappear); ?>" /></td>
    </tr>

    <tr>
      <td><?php print_string('thesewordsmustnotappear', 'local_search') ?>:</td>
      <td><input type="text" name="notappear" length="50" value="<?php p($adv->notappear); ?>" /></td>
    </tr>

    <tr>
      <td><?php print_string('thesewordshelpimproverank', 'local_search') ?>:</td>
      <td><input type="text" name="canappear" length="50" value="<?php p($adv->canappear); ?>" /></td>
    </tr>

    <tr>
      <td><?php print_string('whichmodulestosearch', 'local_search') ?>:</td>
      <td>
        <select name="module">
<?php 
    foreach ($module_types as $mod) {
        if ($mod == $adv->module) {
            if ($mod != 'all'){
                print '<option value="'.$mod.'" selected="selected">'.get_string('modulenameplural', $mod).'</option>'."\n";
            } else {
                print '<option value="'.$mod.'" selected="selected">'.get_string('all', 'local_search').'</option>'."\n";
            }
        } else {
            if ($mod != 'all'){
                print "<option value='$mod'>".get_string('modulenameplural', $mod)."</option>\n";
            } else {
                print "<option value='$mod'>".get_string('all', 'local_search')."</option>\n";
            }
        }
    }
?>
        </select>
      </td>
    </tr>

    <tr>
      <td><?php print_string('wordsintitle', 'local_search') ?>:</td>
      <td><input type="text" name="title" length="50" value="<?php p($adv->title); ?>" /></td>
    </tr>

    <tr>
      <td><?php print_string('authorname', 'local_search') ?>:</td>
      <td><input type="text" name="author" length="50" value="<?php p($adv->author); ?>" /></td>
    </tr>

    <tr>
      <td colspan="3" align="center"><br /><input type="submit" value="<?php p(get_string('search', 'local_search')) ?>" /></td>
    </tr>

    <tr>
      <td colspan="3" align="center">
        <table border="0" cellpadding="0" cellspacing="0">
          <tr>
            <td><a href="query.php"><?php print_string('normalsearch', 'local_search') ?></a> |</td>
            <td>&nbsp;<a href="stats.php"><?php print_string('statistics', 'local_search') ?></a></td>
          </tr>
        </table>
      </td>
    </tr>
    </table>
<?php
    echo $OUTPUT->box_end();
}
?>
</form>
<br/>

<div align="center">
<?php
print_string('searching', 'local_search').': ';

if ($sq->is_valid_index()) {
    //use cached variable to show up-to-date index size (takes deletions into account)
    print 0 + @$config->index_size;
} else {
    print "0";
} 

print ' ';
print_string('documents', 'local_search');
print '.';

if (!$sq->is_valid_index() && has_capability('moodle/site:config', context_system::instance())) {
    print '<p>' . get_string('noindexmessage', 'local_search') . '<a href="indexersplash.php">' . get_string('createanindex', 'local_search').'</a></p>'."\n";
} 

?>
</div>
<?php
echo $OUTPUT->box_end();

// Prints all the results in a box.

if ($sq->is_valid()) {
    echo $OUTPUT->box_start();

    search_stopwatch();
    $hit_count = $sq->count();

    print "<br />";

    print $hit_count.' '.get_string('resultsreturnedfor', 'local_search') . " '".s($query_string)."'.";
    print '<br />';

    if ($hit_count > 0) {
        $page_links = $sq->page_numbers();
        $hits = $sq->results();

        if ($advanced) {
            // if in advanced mode, search options are saved in the session, so
            // we can remove the query string var from the page links, and replace
            // it with a=1 (Advanced = on) instead
            $page_links = preg_replace("/query_string=[^&]+/", 'a=1', $page_links);
        }

        print "<ol>";

        $typestr = get_string('type', 'local_search');
        $scorestr = get_string('score', 'local_search');
        $authorstr = get_string('author', 'local_search');

        $searchables = search_collect_searchables(false, false);

        foreach ($hits as $listing) {

            if ($listing->doctype == 'user') {
                // A special handle for users.
                $user = $DB->get_record('user', array('id' => $listing->userid));
                $icon = $OUTPUT->user_picture($user) ;
            } else {
                $iconpath = $OUTPUT->pix_url($listing->doctype.'/icon');
                $icon = '<img align="top" src="'.$iconpath.'" class="activityicon" alt=""/>';
            }
            $coursename = $DB->get_field('course', 'fullname', array('id' => $listing->courseid));
            $courseword = mb_convert_case(get_string('course', 'moodle'), MB_CASE_LOWER, 'UTF-8');
            $course = ($listing->doctype != 'user') ? '<strong> ('.$courseword.': \''.$coursename.'\')</strong>' : '';

            $title_post_processing_function = $listing->doctype.'_link_post_processing';
            $searchable_instance = $searchables[$listing->doctype];
            if ($searchable_instance->location == 'internal') {
                require_once $CFG->dirroot.'/local/search/documents/'.$listing->doctype.'_document.php';
            } else {
                require_once $CFG->dirroot.'/'.$searchable_instance->location.'/'.$listing->doctype.'/search_document.php';
            }
            if (function_exists($title_post_processing_function)) {
                $listing->title = $title_post_processing_function($listing->title);
            }

            echo '<li value="'.($listing->number + 1).'">';
            $prcoessedurl = str_replace('DEFAULT_POPUP_SETTINGS', DEFAULT_POPUP_SETTINGS, $listing->url);
            echo '<a href="'.$processedurl.'">'.$icon.' '.$listing->title.'</a> '.$course.'<br />'."\n";
            echo "{$typestr}: " . $listing->doctype . ", {$scorestr}: " . round($listing->score, 3);
            if (!empty($listing->author) && !is_numeric($listing->author)) {
                echo ", {$authorstr}: ".$listing->author."\n"
                    .'</li>'."\n";
            }
        }
        echo '</ol>';
        echo $page_links;
    }
    echo $OUTPUT->box_end();
?>
<div align="center">
<?php 
    print_string('ittook', 'local_search');
    search_stopwatch(); 
    print_string('tofetchtheseresults', 'local_search');
?>.
</div>

<?php
}
echo $OUTPUT->box_end();
echo $OUTPUT->footer();
