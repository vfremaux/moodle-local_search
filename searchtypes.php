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

/**
 * Global Search Engine for Moodle
 *
 * @package local_search
 * @category local
 * @author Michael Champanis (mchampan) [cynnical@gmail.com], Valery Fremaux [valery.fremaux@club-internet.fr] > 1.8
 * @date 2008/03/31
 * @license http://www.gnu.org/copyleft/gpl.html GNU Public License
 *
 * Searcheable types
 * to disable a type, just comment the two declaration lines for that type
 */

//document types that can be searched
//define('SEARCH_TYPE_NONE', 'none');
define('SEARCH_TYPE_ASSIGNMENT', 'assignment');
define('SEARCH_TYPE_BOOK', 'book');
define('SEARCH_TYPE_CHAT', 'chat');
define('SEARCH_TYPE_DATA', 'data');
define('SEARCH_TYPE_FORUM', 'forum');
define('SEARCH_TYPE_GLOSSARY', 'glossary');
define('SEARCH_TYPE_LABEL', 'label');
define('SEARCH_TYPE_LESSON', 'lesson');
define('SEARCH_TYPE_PAGE', 'page');
define('SEARCH_TYPE_RESOURCE', 'resource');
define('SEARCH_TYPE_WIKI', 'wiki');

define('SEARCH_EXTRAS', 'user');
define('SEARCH_TYPE_USER', 'user');
define('PATH_FOR_SEARCH_TYPE_USER', 'user');
