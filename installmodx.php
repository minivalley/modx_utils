#!/usr/bin/php -q
<?php
/**
 * Install MODX via CLI
 *
 * This script downloads latest version of MODX and installs it to the directory you 
 * specify.  The script prompts you for your login details.  
 * Currently, this supports only installation to web root (i.e. not to a sub-dir).
 *
 * WARNING: this script may fail if there is poor network connectivity or if the 
 * MODX site is unavailable.
 * 
 * PARAMETERS
 *	--config specifies an XML configuration file to use (path is relative to PWD)
 *	--zip specifies a local MODX zip file  (path is relative to PWD)
 *	--target specifies where to extract the zip file, e.g. public_html/ 
 *			(path is relative to PWD).
 *
 * USAGE:
 * 		php installmodx.php
 * 		php installmodx.php --config=myconfig.php
 * 		php installmodx.php --zip=modx-2.2.5-pl.zip
 *
 * See http://youtu.be/-FR10DR16CE for an example video of this in action.
 *
 * AUTHOR:
 * Everett Griffiths (everett@craftsmancoding.com)
 *
 * LAST UPDATED:
 * March 12, 2013
 *
 * SEE ALSO
 * http://rtfm.modx.com/display/revolution20/Command+Line+Installation
 * http://objectmix.com/php/503559-cli-spinner-processing.html
 * http://patorjk.com/software/taag/
 */
 
//------------------------------------------------------------------------------
//! CONFIG (Devs only)
//------------------------------------------------------------------------------
// shows the most current version
define('INFO_PAGE', 'http://modx.com/download/'); 
// append the modx version, e.g. modx-2.2.6.zip
define('DOWNLOAD_PAGE', 'http://modx.com/download/direct/');
define('ESC', 27);
// version of PHP this script needs to run.
define('PHP_REQ_VER', '5.3.0');
define('THIS_VERSION', '1.0');
define('THIS_AUTHOR', 'Everett Griffiths (everett@craftsmancoding.com)');
define('DIR_PERMS', 0777); // for cache, etc.
//------------------------------------------------------------------------------
//! Functions
//------------------------------------------------------------------------------
/**
 * Our quitting function...
 */
function abort($msg) {
	print PHP_EOL.'FATAL ERROR! '.$msg . PHP_EOL;
	print 'Aborting.'. PHP_EOL.PHP_EOL;
	exit;
}

/**
 * How to function
 */
function show_help() {

	print "
----------------------------------------------
MODX Installer Utility
----------------------------------------------
This utility is designed to let you quickly install MODX Revolution (http://modx.com/) 
to your server via the command-line. 

----------------------------------------------
PARAMETERS:
----------------------------------------------
--config : path to an XML file, containing site info. See http://bit.ly/pvVcHw
--zip : a MODX zip file, downloaded from ".DOWNLOAD_PAGE."
--target : the root of your MODX install, relative to the current dir. E.g. public_html/
--help : displays this help page.

----------------------------------------------
USAGE EXAMPLES:
----------------------------------------------
php ".basename(__FILE__)."

    This is the most basic invocation. The user will be prompted for all info.

php ".basename(__FILE__)." --zip=modx-2.2.5-pl

    --zip tells the script to extract an existing local zip file instead of
    downloading a new one. Path is relative.

php ".basename(__FILE__)." --config=myconfig.xml

	The --config option specifies a MODX XML configuration.  This file contains 
	your database login, your MODX username, and other important data required 
	to install MODX.  This file will be copied to setup/config.xml. If you are 
	doing a lot of installs, keep a copy of an XML config file. Path is relative.

php ".basename(__FILE__)." --target=public_html

	The --target option specifies where to deploy MODX. No intermediate
	directories will be created: the contents of the zip file will go to the 
	target. Path is relative.

----------------------------------------------
BUGS and FEATURE SUGGESTIONS
----------------------------------------------
Please direct feedback about this script to https://github.com/craftsmancoding/modx_utils

";
	exit;
}

/**
 * Strip the front off the dir name to make for cleaner zipfile extraction.
 * Converts something like myzipdir/path/to/file.txt
 * to path/to/file.txt
 *
 * Yes, this is some childish tricks here using string reversal, but we 
 * get the biggest bang for our buck using dirname().
 * @param string $path
 * @return string 
 */
function strip_first_dir($path) {
	$path = strrev($path);
	$path = dirname($path);
	$path = strrev($path);
	return $path;
}

/**
 * Performs checks prior to running the script.
 *
 */
function preflight() {
	error_reporting(E_ALL);
	// Test PHP version.
	if (version_compare(phpversion(),PHP_REQ_VER,'<')) { 
		abort(sprintf("Sorry, this script requires PHP version %s or greater to run.", PHP_REQ_VER));
	}
	if (!extension_loaded('curl')) {
		abort("Sorry, this script requires the curl extension for PHP.");
	}
	if (!class_exists('ZipArchive')) {
		abort("Sorry, this script requires the ZipArchive classes for PHP.");
	}
	// timezone
	if (!ini_get('date.timezone')) {
		abort("You must set the date.timezone setting in your php.ini. Please set it to a proper timezone before proceeding.");
	}
}

/** 
 * Eye Candy
 *
 */
function print_banner() {
	printf( "%c[2J", ESC ); //clear screen
	print "
 .----------------.  .----------------.  .----------------.  .----------------. 
| .--------------. || .--------------. || .--------------. || .--------------. |
| | ____    ____ | || |     ____     | || |  ________    | || |  ____  ____  | |
| ||_   \  /   _|| || |   .'    `.   | || | |_   ___ `.  | || | |_  _||_  _| | |
| |  |   \/   |  | || |  /  .--.  \  | || |   | |   `. \ | || |   \ \  / /   | |
| |  | |\  /| |  | || |  | |    | |  | || |   | |    | | | || |    > `' <    | |
| | _| |_\/_| |_ | || |  \  `--'  /  | || |  _| |___.' / | || |  _/ /'`\ \_  | |
| ||_____||_____|| || |   `.____.'   | || | |________.'  | || | |____||____| | |
| |              | || |              | || |              | || |              | |
| '--------------' || '--------------' || '--------------' || '--------------' |
 '----------------'  '----------------'  '----------------'  '----------------

           ,--.                ,--.          ,--.,--.               
           |  |,--,--,  ,---.,-'  '-. ,--,--.|  ||  | ,---. ,--.--. 
           |  ||      \(  .-''-.  .-'' ,-.  ||  ||  || .-. :|  .--' 
           |  ||  ||  |.-'  `) |  |  \ '-'  ||  ||  |\   --.|  |    
           `--'`--''--'`----'  `--'   `--`--'`--'`--' `----'`--'    

                                                       
";
	print 'Version '.THIS_VERSION . str_repeat(' ', 15).'by '. THIS_AUTHOR . PHP_EOL;
	print str_repeat(PHP_EOL,2);
}

/**
 * Get and vet command line arguments
 * @return array
 */
function get_args() {
	$shortopts  = '';
	$shortopts .= 'c::'; // Optional value
	$shortopts .= 'z::'; // Optional value
	$shortopts .= 't::'; // Optional value
	$shortopts .= 'h::'; // Optional value
	
	$longopts  = array(
	    'config::',    // Optional value
	    'zip::',    // Optional value
	    'target::',    // Optional value
	   	'help::',    // Optional value
	);
	
	$opts = getopt($shortopts, $longopts);
	
	if (isset($opts['help'])) {
		show_help();
	}
	
	if (isset($opts['config']) && !file_exists($opts['config'])) {
		abort('XML configuration file not found. ' . $opts['config']);
	}
	else {
		$opts['config'] = false;
	}
	if (isset($opts['zip']) && !file_exists($opts['zip'])) {
		abort('Zip file not found. ' . $opts['zip']);
	}
	if (!isset($opts['target'])) {
		$opts['target'] = null;
	}

	return $opts;
}

/** 
 * Finds the name of the lastest stable version of MODX
 * by scraping the MODX website.  Prints some messaging...
 *
 * @return string
 */
function get_latest_modx_version() {
	print "Finding most recent version of MODX...";
	$contents = file_get_contents(INFO_PAGE);
	preg_match('#'.preg_quote('<h3>MODX Revolution ').'(.*)'. preg_quote('</h3>','/').'#msU',$contents,$m1);
	if (!isset($m1[1])) {
	    abort('Version could not be detected on '. INFO_PAGE);
	}
	print $m1[1] . PHP_EOL;
	return $m1[1];
}

/**
 * A simple cli spinner... doesn't show progress, but it lets the user know 
 * something is happening.
 */
function progress_indicator($ch,$str) {
	global $cursorArray;
	global $i;
	global $zip_url;
	//restore cursor position and print
	printf("%c8Downloading $zip_url... (".$cursorArray[ (($i++ > 7) ? ($i = 1) : ($i % 8)) ].")", ESC); 
}

/**
 *
 * When finished, you should have a modx-x.x.x.zip file locally on your system.
 * @param string $modx_version e.g. modx-2.2.6-pl.zip
 */
function download_modx($modx_zip) {
	global $zip_url;
	$zip_url = DOWNLOAD_PAGE.$modx_zip;
	$local_file = $modx_zip; // TODO: different location?
	print "Downloading $zip_url".PHP_EOL;
	printf( "%c[2J", ESC ); //clear screen
	
	$fp = fopen($local_file, 'w');
	$ch = curl_init();
	curl_setopt($ch, CURLOPT_URL, $zip_url);
	curl_setopt($ch, CURLOPT_FOLLOWLOCATION, TRUE);
	curl_setopt($ch, CURLOPT_FILE, $fp);
	curl_setopt($ch, CURLOPT_NOPROGRESS, false); // req'd to allow callback
	curl_setopt($ch, CURLOPT_PROGRESSFUNCTION, 'progress_indicator');
	curl_setopt($ch, CURLOPT_BUFFERSIZE, 128); // bigger = fewer callbacks
	if (curl_exec($ch) === false) {
		abort("There was a problem downloading the zip file: " .curl_error($ch));
	}
	else {
		print PHP_EOL;
		print "Zip file downloaded to $local_file".PHP_EOL;
	}
	curl_close($ch);
	fclose($fp);	
}

/**
 * ZipArchive::extractTo did not do what I wanted, and it had errors. Boo.
 * The trick is to shift the "modx-2.2.6-pl" off from the front of the 
 * extraction. Instead of extracting to public_html/modx-2.2.6-pl/ we want
 * to extract straight to public_html/
 * I couldn't find any other examples that did quite what I wanted.
 *
 * See http://stackoverflow.com/questions/5256551/unzip-the-file-using-php-collapses-the-zip-file-into-one-folder
 *
 * @param string $zipfile (relative to this script, e.g. myfile.zip)
 * @param string $target path where we want to setup MODX, e.g. public_html/
 */
function extract_zip($zipfile,$target) {
	
	$z = zip_open($zipfile) or die("can't open $zipfile: $php_errormsg");
	while ($entry = zip_read($z)) {
		
		$entry_name = zip_entry_name($entry);

		// only proceed if the file is not 0 bytes long
		if (zip_entry_filesize($entry)) {
			// Put this in our own directory
			$entry_name = $target . strip_first_dir($entry_name);
			print 'inflating: '. $entry_name .PHP_EOL;
			$dir = dirname($entry_name);
			// make all necessary directories in the file's path
			if (!is_dir($dir)) { 
				@mkdir($dir,0777,true); 
			}
				
			$file = basename($entry_name);
			
			if (zip_entry_open($z,$entry)) {
				if ($fh = fopen($dir.'/'.$file,'w')) {
					// write the entire file
					fwrite($fh,
					zip_entry_read($entry,zip_entry_filesize($entry)))
					or error_log("can't write: $php_errormsg");
					fclose($fh) or error_log("can't close: $php_errormsg");
				} 
				else {
					print "Can't open $dir/$file".PHP_EOL;
				}
				zip_entry_close($entry);
			} 
			else {
				print "Can't open entry $entry_name" . PHP_EOL;
			}
		}
	}
	
	print 'Extraction complete.'.PHP_EOL.PHP_EOL;
}

/**
 * Prompt the user for the deets. Some of this we can detect already.
 *
 * @return array
 */
function get_data($data) {

	print '-----------------------------------------' . PHP_EOL;
	print 'Provide your configuration details below.'.PHP_EOL;
	print '(you can review this before you install).'.PHP_EOL;
	print '-----------------------------------------' . PHP_EOL.PHP_EOL;
	
	foreach($data as $k => $v) {
		$default_label = ''; // with [brackets]
		if (!empty($v)) {
			$default_label = " [$v]";
		}
		print $k . $default_label.': ';
		
		$input = trim(fgets(STDIN));
		if (!empty($input)) {
			$data[$k] = $input;
		}
		else {
			$data[$k] = $v;
		}
	}

	return $data;
}

/**
 * Prints data so the user can review it.
 * @param array $data
 */
function print_review_data($data) {
	printf( "%c[2J", ESC ); //clear screen
	print '-----------------------------------------' . PHP_EOL;
	print 'Review your configuration details.'.PHP_EOL;
	print '-----------------------------------------' . PHP_EOL.PHP_EOL;
	
	foreach ($data as $k => $v) {
		printf( "%' -24s", $k.':');
		print $v . PHP_EOL;
	}
}

/**
 * Get XML configuration file that MODX will recognize.
 * This is streamlined for the most common options.
 *
 * @param array $data
 */
function get_xml($data) {	
	
	// Write XML File
	$xml = '
<!--
Configuration file for MODX Revolution

Created by the modxinstaller.php script.
https://github.com/craftsmancoding/modx_utils
-->
<modx>
	<database_type>mysql</database_type>
    <database_server>localhost</database_server>
    <database>'.$data['Database Name'].'</database>
    <database_user>'.$data['Database User'].'</database_user>
    <database_password>'.$data['Database Password'].'</database_password>
    <database_connection_charset>utf8</database_connection_charset>
    <database_charset>utf8</database_charset>
    <database_collation>utf8_general_ci</database_collation>
    <table_prefix>modx_</table_prefix>
    <https_port>443</https_port>
    <http_host>localhost</http_host>
    <cache_disabled>0</cache_disabled>

    <!-- Set this to 1 if you are using MODX from Git or extracted it from the full MODX package to the server prior
         to installation. -->
    <inplace>0</inplace>
    
    <!-- Set this to 1 if you have manually extracted the core package from the file core/packages/core.transport.zip.
         This will reduce the time it takes for the installation process on systems that do not allow the PHP time_limit
         and Apache script execution time settings to be altered. -->
    <unpacked>0</unpacked>

    <!-- The language to install MODX for. This will set the default manager language to this. Use IANA codes. -->
    <language>en</language>

    <!-- Information for your administrator account -->
    <cmsadmin>'.$data['MODX Admin Username'].'</cmsadmin>
    <cmspassword>'.$data['MODX Admin Password'].'</cmspassword>
    <cmsadminemail>'.$data['MODX Admin Email'].'</cmsadminemail>

    <!-- Paths for your MODX core directory -->
    <core_path>'.$data['core_path'].'</core_path>

    <!-- Paths for the default contexts that are installed. -->
    <context_mgr_path>'.$data['mgr_path'].'</context_mgr_path>
    <context_mgr_url>'.$data['mgr_url'].'</context_mgr_url>
    <context_connectors_path>'.$data['connectors_path'].'</context_connectors_path>
    <context_connectors_url>'.$data['connectors_url'].'</context_connectors_url>
    <context_web_path>'.$data['base_path'].'</context_web_path>
    <context_web_url>'.$data['base_url'].'</context_web_url>

    <!-- Whether or not to remove the setup/ directory after installation. -->
    <remove_setup_directory>1</remove_setup_directory>
</modx>';
	
	return $xml;
}

/**
 * Write XML data to a file.
 * @param string $contents
 * @param string $xml_path where we write this
 */
function write_xml($contents,$xml_path) {
	if($fh = @fopen($xml_path, 'w')) {
		fwrite($fh, $contents);
		fclose($fh);
		
		print 'config.xml file written at '.$xml_path.PHP_EOL;
	}
	else {
		print 'There was a problem opening '.$xml_path.' for writing.'.PHP_EOL;
		print 'You can paste the following contents into '.$xml_path.PHP_EOL;
		print 'and then run:  php ./index.php --installmode=new'. PHP_EOL;
		print 'or navigate to your site via a browser and do a normal installation.'.PHP_EOL.PHP_EOL;
		print $contents;
		print PHP_EOL.PHP_EOL;
		exit;
	}
}

/**
 * Set up a few things in MODX...
 *
 * @param string $target
 */
function prepare_modx($target) {
	$base_path = getcwd().DIRECTORY_SEPARATOR.$target;
	$core_path = $base_path . 'core/';
	// Check that core/cache/ exists and is writeable
	if (!file_exists($core_path.'cache')) {
		@mkdir($core_path.'cache',0777,true); 
	}
	if (!is_writable($core_path.'cache')) {
		chmod($core_path.'cache', DIR_PERMS);
	}
	
	// Check that core/components/ exists and is writeable
	if (!file_exists($core_path.'components')) {
		@mkdir($core_path.'components',0777,true); 
	}
	if (!is_writable($core_path.'components')) {
		chmod($core_path.'components', DIR_PERMS);
	}
	
	// Check that assets/components/ exists and is writeable
	if (!file_exists($base_path.'assets/components')) {
		@mkdir($base_path.'assets/components',0777,true); 
	}
	if (!is_writable($base_path.'assets/components')) {
		chmod($base_path.'assets/components', DIR_PERMS);
	}

	// Check that core/export/ exists and is writable
	if (!file_exists($core_path.'export')) {
		@mkdir($core_path.'export',0777,true); 
	}
	if (!is_writable($core_path.'export')) {
		chmod($core_path.'export', DIR_PERMS);
	}
	
	// touch the config file
	if (!file_exists($core_path.'config/config.inc.php')) {
		@mkdir($core_path.'config',0777,true); 
		touch($core_path.'config/config.inc.php');
	}
	if (!is_writable($core_path.'config/config.inc.php')) {
		chmod($core_path.'config/config.inc.php', DIR_PERMS);
	}
}

//------------------------------------------------------------------------------
//! Vars
//------------------------------------------------------------------------------
// Each spot in the array is a "frame" in our spinner animation
$cursorArray = array('/','-','\\','|','/','-','\\','|'); 
$i = 0; // for spinner iterations
// declared here so we can use it in the progress indicator.
$zip_url = '';

//------------------------------------------------------------------------------
//! MAIN
//------------------------------------------------------------------------------
// check php version, is cli?, can we write to the local dir?, etc...
preflight();

// Read and validate any command-line arguments
$args = get_args();

// Some eye-candy...
print_banner();

// Last chance to bail...
print 'This script installs the MODX Content Management System (http://modx.com/)'.PHP_EOL;
print 'You need a dedicated database with a username/password handy and your user'.PHP_EOL;
print 'must have the proper write permissions for this script to work properly.'.PHP_EOL.PHP_EOL;
print 'Are you ready to continue? (y/n) [n] > ';
$yn = strtolower(trim(fgets(STDIN)));
if ($yn!='y') {
	print 'Catch you next time.' .PHP_EOL.PHP_EOL;
	exit;
}
print PHP_EOL;

// Skip downloading if we've already got a zip file
if (isset($args['zip']) && !empty($args['zip'])) {
	print 'Using existing zip file: '.$args['zip'] . PHP_EOL;
}
else {
	// get the latest MODX version (scrape the info page)
	$modx_version = get_latest_modx_version();
	$modx_zip = 'modx-'.$modx_version.'.zip';
	
	// If we already have the file downloaded, can we use the existing zip?
	if (file_exists($modx_zip)) { 
		print $modx_zip .' was detected locally on the filesystem.'.PHP_EOL.PHP_EOL;
		print 'Would you like to use that zip file? (y/n) [y] > ';
		$yn = strtolower(trim(fgets(STDIN)));
		if ($yn != 'y') {
			download_modx($modx_zip);
		}
	}
	else {
		download_modx($modx_zip);
	}
	// At this point, behavior is as if we had specified the zip file verbosely.
	$args['zip'] = $modx_zip;
}

// Prompt the user for target
if (!$args['target']) {
	// Default
	$args['target'] = pathinfo($args['zip'],PATHINFO_FILENAME).DIRECTORY_SEPARATOR;
	print PHP_EOL."Where should this be extracted? [".$args['target']."] > ";
	$target_path = trim(fgets(STDIN));
	if (!empty($target_path)) {
		$args['target'] = $target_path;
	}
}

// make sure we have a trailing slash on the target dir
$target = basename($args['target']).DIRECTORY_SEPARATOR; 

extract_zip($args['zip'],$target);

// We can skip a lot of stuff if the user supplied an XML config...
// otherwise we have to ask them a bunch of stuff.
// Yes, and we even have a GOTO statement.
$xml_path = $target.'setup/config.xml';

if (!$args['config']) {	

	$data = array();
	
	// Put anything here that you want to prompt the user about.
	// Put default values here.
	$data['Database Name'] = '';
	$data['Database User'] = '';
	$data['Database Password'] = '';
	
	$data['MODX Admin Username'] = '';
	$data['MODX Admin Email'] = '';
	$data['MODX Admin Password'] = '';
	
	ENTERDATA:
	$data = get_data($data);
	print_review_data($data);
	
	print PHP_EOL. "Is this correct? (y/n) [n] >";
	$yn = strtolower(trim(fgets(STDIN)));
	if ($yn != 'y') {
		goto ENTERDATA; // yeah... 1980 called and wants their code back.
	}	
	
	// Some defaults here.
	// TODO: allow for installation in sub-directory
	$data['core_path'] = getcwd().DIRECTORY_SEPARATOR.$target.'core/';
	
	$data['mgr_path'] = getcwd().DIRECTORY_SEPARATOR.$target.'manager/';
	$data['mgr_url'] = '/manager/';
	
	$data['connectors_path'] = getcwd().DIRECTORY_SEPARATOR.$target.'connectors/';
	$data['connectors_url'] = '/connectors/';
	
	$data['base_path'] = getcwd().DIRECTORY_SEPARATOR.$target;
	$data['base_url'] = '/';

	$xml = get_xml($data);

}
else {
	// Get XML from config file
	$xml = file_get_contents($args['config']);
}

write_xml($xml, $xml_path);

// Test Database Connection?  We can't do this unless the user provided data.

// Check that core/cache exists and is writeable
prepare_modx($target);

// Run install
print 'Off we go... installing MODX...'.PHP_EOL.PHP_EOL;
// Via command line, we'd do this:
// php setup/index.php --installmode=new --config=/path/to/config.xml
// but here, we fake it.
unset($argv);
$argv[1] = '--installmode=new';
include($target.'setup/index.php');

print PHP_EOL;
print 'You may now log into your MODX installation.'.PHP_EOL;
print 'Thanks for using the MODX installer!'.PHP_EOL.PHP_EOL;
/*EOF*/