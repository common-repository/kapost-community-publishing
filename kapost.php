<?php
/*
	Plugin Name: Kapost Social Publishing
	Plugin URI: http://www.kapost.com/
	Description: Kapost Social Publishing
	Version: 2.0.1
	Author: Kapost
	Author URI: http://www.kapost.com
 */
global $wp_version;
if(empty($wp_version)) return;

// WordPress Version
define('KAPOST_WP_VERSION', $wp_version);

// Kapost Release Mode Flag (this will turn on Get Exceptional, etc)
define('KAPOST_RELEASE_MODE', (defined('WP_DEBUG') && WP_DEBUG === true) ? false : true);

// Detect if we are running PHP5 or 4 (we need at least 5.1.0 for Get Exceptional)
define('KAPOST_PHP5',(version_compare(PHP_VERSION, '5.1.0') >= 0) ? true : false);

// Kapost Version
define('KAPOST_VERSION', '2.0.1');

// Kapost Community Username / Nickname
define('KAPOST_COMMUNITY_USER', 'Community');

// Kapost Register URL
define('KAPOST_START_URL', 'http://create.kapost.com');

// Kapost Plugin Directory Name
define('KAPOST_PLUGIN_DIRNAME',basename(str_replace("/".basename(__FILE__),"",str_replace("/trunk/".basename(__FILE__),"",realpath(__FILE__)))));

// Global Kapost Settings Defaults
$KAPOST_DEFAULT_SETTINGS = array(	'username' 		=> '', 
									'password' 		=> '', 
									'url' 			=> '', 
									'contribute'	=> array
									(
										'fgcolor' 	=> '#FFFFFF',
										'ffamily' 	=> 'Verdana',
										'fsize' 	=> '11',
										'fbold'		=> '',
										'fitalic'	=> '',
										'brcolor'	=> '#CACACA',
										'bgcolor'	=> '#000000',
										'align'		=> 'left',
										'width'		=> '100',
										'custom'	=> 0
									),
									'attr_create_user'=>'',
									'sso'=>'');

$modules = array
(
	'kapost.inc',
	'widget.php',
	'settings.php',
	'attributions.php',
	'KapostSSO.php'
);

// Remove the widget if for some reason the WP_Widget
// wasn't defined, this could happen if the WordPress
// version `not supported` by our plugin
if(!class_exists('WP_Widget'))
{
	foreach($modules as $i=>$module)
	{
		if($module == "widget.php")
		{
			unset($modules[$i]);
			break;
		}
	}
}

// Guard Exceptional from PHP4
if(KAPOST_PHP5 === true && KAPOST_RELEASE_MODE)
{
	// Save the initial error reporting state
	define('KAPOST_ERROR_REPORTING_STATE',error_reporting(E_ALL & ~(E_NOTICE|E_WARNING)));

	// Exceptional API Key
	define('KAPOST_EXCEPTIONAL_API_KEY','0b7368015a63c4b6d44c38100d35badc4b41a982');

	// Include and initialize Exceptional early before doing any heavy-work ...
	require_once(dirname(__FILE__).'/modules/exceptional/exceptional.php');
	Exceptional::setup(KAPOST_EXCEPTIONAL_API_KEY);

	// Extra Exception Information
	$info = array
	(
		'plugin'=>KAPOST_VERSION,
		'wordpress'=>KAPOST_WP_VERSION,
		'php'=>PHP_VERSION,
		'webserver'=>$_SERVER['SERVER_SOFTWARE']	
	);
	Exceptional::context($info);

	// Handle exceptions coming from our files only, and skip
	// everything else, because otherwise we would be way too
	// verbose with all the exception handlers installed.
	$files = $modules;
	foreach($files as $i=>$file)
		$files[$i] = KAPOST_PLUGIN_DIRNAME."/modules/".$file;

	$files[] = KAPOST_PLUGIN_DIRNAME.'/kapost.php';

	Exceptional::files($files);

	define('KAPOST_EXCEPTIONAL', true);
}

// Include all modules
foreach($modules as $module) require_once(dirname(__FILE__).'/modules/'.$module);

// Restore the old error reporting state
if(KAPOST_PHP5 === true && KAPOST_RELEASE_MODE)
	error_reporting(KAPOST_ERROR_REPORTING_STATE);
?>
