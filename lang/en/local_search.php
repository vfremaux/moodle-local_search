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

$string['pluginname'] = 'Local Global Search';

// Privacy
$string['privacy:metadata'] = 'The Local Search plugin, although indexing personnal information for search purpose,  does not store itself any personal data about the users.';

$string['advancedsearch'] = 'Advanced search';
$string['all'] = 'All';
$string['asynchronous'] = 'Asynchronous (Ajax)';
$string['author'] = 'Author';
$string['authorname'] = 'Author name';
$string['back'] = 'Back';
$string['beadmin'] = 'You need to be an admin user to use this page.';
$string['blockssearchmnetfeatures'] = 'Moodle Network Search';
$string['blockssearchswitches'] = 'Indexer activation for blocks';
$string['bytes'] = ' bytes (0 stands for no limits)';
$string['checkdb'] = 'Check Database';
$string['checkdbadvice'] = 'Check your database for any problems.';
$string['checkdir'] = 'Check dir';
$string['checkdiradvice'] = 'Ensure the data directory exists and is writable.';
$string['commenton'] = 'Comment on ';
$string['configbuttonlabel'] = 'Button label';
$string['configdocxtotextcmd'] = 'Docx converter tool path';
$string['configenablefileindexing'] = 'Physical File Indexing';
$string['configenableglobalsearch'] = 'Global activation';
$string['configfiletypes'] = 'File types handled';
$string['configlimitindexbody'] = 'Indexed body size limitation';
$string['configpdftotextcmd'] = 'pdftotext path';
$string['configsearchtext'] = 'Search text';
$string['configtypetotxtcmd'] = 'Command line';
$string['configtypetotxtenv'] = 'Environment for converter';
$string['configusingsoftlock'] = 'Software interlocking';
$string['configutf8transcoding'] = 'Results UTF8 transcoding';
$string['configwordtotextcmd'] = 'Path to doctotext';
$string['configwordtotextenv'] = 'Environment setting for the MSWord converter';
$string['coresearchswitches'] = 'Core searchable elements';
$string['createanindex'] = 'create an index';
$string['createdon'] = 'Created on';
$string['database'] = 'Database';
$string['databasestate'] = 'Indexing database state';
$string['datadirectory'] = 'Data directory';
$string['deletionsinindex'] = 'Deletions in index';
$string['disabled'] = 'Disabled';
$string['disabledsearch'] = 'Search is disabled';
$string['disabledsearch'] = 'The global search engine is off. Inform your administrator.';
$string['doctype'] = 'Doctype';
$string['documents'] = 'documents';
$string['documentsfor'] = 'Documents for ';
$string['documentsindatabase'] = 'Documents in database';
$string['documentsinindex'] = 'Documents in index';
$string['duration'] = 'Duration';
$string['emptydatabaseerror'] = 'Database table is not present, or contains no index records.';
$string['enabled'] = 'Enabled';
$string['enteryoursearchquery'] = 'Enter your search query';
$string['errors'] = 'Errors ';
$string['filesinindexdirectory'] = 'Files in index directory';
$string['fromutf'] = 'From utf8';
$string['globalsearchdisabled'] = 'Global searching is not enabled.';
$string['go'] = 'Go!';
$string['handlingfor'] = 'Extra handling for';
$string['invalidindexerror'] = 'Index directory either contains an invalid index, or nothing at all.';
$string['ittook'] = 'It took';
$string['modulessearchswitches'] = 'Indexer activation for modules';
$string['next'] = 'Next';
$string['nochange'] = 'No conversion';
$string['noindexmessage'] = 'Admin: There appears to be no search index. Please';
$string['normalsearch'] = 'Normal search';
$string['nosearchableblocks'] = 'No searchable blocks';
$string['nosearchablemodules'] = 'No searchable modules';
$string['openedon'] = 'opened on';
$string['pdfhandling'] = 'Acrobat PDF handling';
$string['resultsreturnedfor'] = ' results returned for ';
$string['runindexer'] = 'Run indexer (real)';
$string['runindexertest'] = 'Run indexer test';
$string['score'] = 'Score';
$string['search'] = 'Search';
$string['searchdiscovery'] = 'Searchable items discovery';
$string['searching'] = 'Searching in ...';
$string['searchmoodle'] = 'Search Moodle';
$string['seconds'] = ' seconds ';
$string['solutions'] = 'Solutions';
$string['statistics'] = 'Statistics';
$string['synchronous'] = 'Synchronous';
$string['thesewordshelpimproverank'] = 'These words help improve rank';
$string['thesewordsmustappear'] = 'These words must appear';
$string['thesewordsmustnotappear'] = 'These words must not appear';
$string['tofetchtheseresults'] = 'to fetch these results';
$string['totalsize'] = 'Total Size ';
$string['toutf'] = 'To utf8';
$string['type'] = 'Type';
$string['uncompleteindexingerror'] = 'Indexing was not successfully completed, please restart it.';
$string['usemoodleroot'] = 'Use moodle root';
$string['usemoodleroot_desc'] = 'Use moodle root ase base root for external converters';
$string['versiontoolow'] = 'Sorry, global search requires PHP 5.0.0 or later';
$string['whichmodulestosearch'] = 'Which modules to search?';
$string['wordhandling'] = 'Microsoft Word handling';
$string['wordsintitle'] = 'Words in title';

$string['configbuttonlabel_desc'] = 'Label that will appear on the search form';

$string['configdocxtotextcmd_desc'] = 'Path to the MSWord DOCX converter';

$string['configenablefileindexing_desc'] = 'If enabled, physical files attached to moodle content or resources will be
plain text indexed';

$string['configenableglobalsearch_desc'] = 'If disabled, will avoid indexing operations and hide everything related to global search';

$string['configfiletypes_desc'] = 'List of all file extensions being handled for text conversion';

$string['configlimitindexbody_desc'] = 'Limits the size of content that will be actually indexed. This may save performance for the
indexing process, but may loose some search capabilities.';

$string['configpdftotextcmd_desc'] = 'Path to command pdftotext';

$string['configsearchtext_desc'] = 'Search text';

$string['configtypetotxtcmd_desc'] = 'Converter\'s command line for the type';

$string['configtypetotxtenv_desc'] = 'A definition of environment variable to set for the converter if any.';

$string['configusingsoftlock_desc'] = 'Switch this on if indexing might be operated on several clusters and stored on remotely mounted
NFS volume. soft interlocking is less secured as not avoiding any possibility of writing collision on the index files, but it the only
way to work from clusters.';

$string['configutf8transcoding_desc'] = 'This should be not needed any more';

$string['configwordtotextcmd_desc'] = 'Path to the Word doctotext converter command';

$string['configwordtotextenv_desc'] = 'Environment setting for the MSWord converter';
