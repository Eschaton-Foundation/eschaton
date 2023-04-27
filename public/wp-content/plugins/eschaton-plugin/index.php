<?php
/*
Plugin Name: Eschaton Plugin
Description: Add Custom Post type & ACF Fields
Author: Thomas Florentin
Version: 1.0
*/

// DEFINE PATHS
define('ESC_DIR', WP_PLUGIN_DIR.'/eschaton-plugin');
define('ESC_PATH', '/'.str_replace(ABSPATH, '', ESC_DIR));
define('ESC_URL', WP_PLUGIN_URL.'/eschaton-plugin');


// ACF
require_once(ESC_DIR.'/acf.php');

// COOKIES
// https://tarteaucitron.io/fr/install/
require_once(ESC_DIR.'/cookies.php');

// REQUIRE CUSTOM POST TYPES 
require_once(ESC_DIR.'/cpt/cpt-publication.php');
require_once(ESC_DIR.'/cpt/cpt-studio.php');
