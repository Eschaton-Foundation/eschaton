<?php
/*
Plugin Name: Eschaton Plugin
Description: Add Custom Post type & ACF Fields
Author: Thomas Florentin
Version: 1.0
*/

// DEFINE PATHS
define('ABM_DIR', WP_PLUGIN_DIR.'/eschaton-plugin');
define('ABM_PATH', '/'.str_replace(ABSPATH, '', ABM_DIR));
define('ABM_URL', WP_PLUGIN_URL.'/eschaton-plugin');


// ACF
require_once(ABM_DIR.'/acf.php');


// REQUIRE CUSTOM POST TYPES 
require_once(ABM_DIR.'/cpt/cpt-bibliography.php');
