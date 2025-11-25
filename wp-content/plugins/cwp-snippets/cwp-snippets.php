<?php
/**
 * Plugin Name:       CWP Snippets
 * Plugin URI:        https://cwpsnippets.com
 * Description:       Provides a Custom Web Publishing environment for FileMaker Data API in WordPress
 * Version:           1.6.9
 * Requires at least: 5.2
 * Requires PHP:      7.2
 * Author:            RGC Data LLC
 * Author URI:        https://rgcdata.com/
 * License:           GPL v2 or later
 * License URI:       https://www.gnu.org/licenses/gpl-2.0.html
 */

// Exit if accessed directly
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}






// --- Define Core Plugin Constants ---
define( 'CWP_SNIPPETS_VERSION', '1.6.9' );
define( 'FMCWP_PLUGIN_FILE', __FILE__ ); // Useful for functions like plugin_dir_url
define( 'FMCWP_PLUGIN_PATH', plugin_dir_path( FMCWP_PLUGIN_FILE ) );
define( 'FMCWP_PLUGIN_URL', plugin_dir_url( FMCWP_PLUGIN_FILE ) );

// --- Load Core Functionality Files ---
require_once FMCWP_PLUGIN_PATH . 'includes/user-identification.php';
require_once FMCWP_PLUGIN_PATH . 'includes/license-functions.php';
require_once FMCWP_PLUGIN_PATH . 'includes/utilities.php';

require_once FMCWP_PLUGIN_PATH . 'includes/activation.php';
require_once FMCWP_PLUGIN_PATH . 'includes/deactivation.php';
require_once FMCWP_PLUGIN_PATH . 'includes/update.php';

require_once FMCWP_PLUGIN_PATH . 'includes/constants.php';
require_once FMCWP_PLUGIN_PATH . 'includes/snippets-functions.php';
require_once FMCWP_PLUGIN_PATH . 'includes/shortcodes.php';

// --- Load Third-Party Libraries ---
require_once FMCWP_PLUGIN_PATH . 'includes/libraries/parsedown/Parsedown.php';
require_once FMCWP_PLUGIN_PATH . 'includes/libraries/nikic/php-parser/lib/PhpParser/Parser.php';

// Custom autoloader for PhpParser classes
spl_autoload_register(function ($class) {
    // project-specific namespace prefix
    $prefix = 'PhpParser\\';

    // base directory for the namespace prefix
    $base_dir = FMCWP_PLUGIN_PATH . 'includes/libraries/nikic/php-parser/lib/PhpParser/';

    // does the class use the namespace prefix?
    $len = strlen($prefix);
    if (strncmp($prefix, $class, $len) !== 0) {
        // no, move to the next registered autoloader
        return;
    }

    // get the relative class name
    $relative_class = substr($class, $len);

    // replace the namespace prefix with the base directory, replace namespace
    // separators with directory separators in the relative class name, append
    // with .php
    $file = $base_dir . str_replace('\\', '/', $relative_class) . '.php';

    // if the file exists, require it
    if (file_exists($file)) {
        require_once $file;
    }
});


// --- Load Admin Functionality (Only when in the admin area) ---
if ( is_admin() ) {	
	require_once FMCWP_PLUGIN_PATH . 'admin/admin.php';
	require_once FMCWP_PLUGIN_PATH . 'admin/snippets.php';
	require_once FMCWP_PLUGIN_PATH . 'admin/settings.php';
	require_once FMCWP_PLUGIN_PATH . 'admin/demo-setup.php';
	require_once FMCWP_PLUGIN_PATH . 'admin/license.php';
	require_once FMCWP_PLUGIN_PATH . 'admin/add-ons.php';
	require_once FMCWP_PLUGIN_PATH . 'admin/import-export.php';
	require_once FMCWP_PLUGIN_PATH . 'admin/ajax-handlers.php';
	require_once FMCWP_PLUGIN_PATH . 'admin/debug-log.php';
	require_once FMCWP_PLUGIN_PATH . 'admin/admin-notices.php';
	require_once FMCWP_PLUGIN_PATH . 'admin/documentation.php';
	require_once FMCWP_PLUGIN_PATH . 'admin/update-checker.php';
	
}


// --- Load Classes ---
require_once FMCWP_PLUGIN_PATH . 'src/fmCWP.php';
if ( class_exists( 'fmCWP\\fmCWP' ) ) {
	class_alias( 'fmCWP\\fmCWP', 'fmCWP' );
}

// --- Define Custom Cron Hook Name ---
define( 'CWP_SNIPPETS_CRON_HOOK', 'cwp_snippets_daily_license_check_event' );

// --- Custom Cron Schedules ---
function cwp_snippets_custom_cron_schedules( $schedules ) {
	if ( ! isset( $schedules['cwp_daily'] ) ) {
		$schedules['cwp_daily'] = array(
			'interval' => DAY_IN_SECONDS,
			'display'  => __( 'Once Daily CWP', 'cwp-snippets' ),
		);
	}
	return $schedules;
}
add_filter( 'cron_schedules', 'cwp_snippets_custom_cron_schedules' );

// --- Register Activation Hooks ---
function cwp_snippets_activate_plugin() {
	fmcwp_install();
	fm_cwp_create_preview_page();

	// Only load bundled snippets on first activation
	if ( false === get_option( 'cwp_snippets_version' ) ) {
		fmcwp_load_samples();
		fmcwp_load_templates();
		fmcwp_load_functions();
		fmcwp_load_scripts();
		fmcwp_load_styles();
		fmcwp_store_all_bundled_hashes();
	}

	fmcwp_set_plugin_version();

	// Schedule the cron event if it's not already scheduled
	if ( ! wp_next_scheduled( CWP_SNIPPETS_CRON_HOOK ) ) {
		wp_schedule_event( time(), 'cwp_daily', CWP_SNIPPETS_CRON_HOOK );
	}
}
register_activation_hook( FMCWP_PLUGIN_FILE, 'cwp_snippets_activate_plugin' );

// --- Register Deactivation Hook ---
function cwp_snippets_deactivate_plugin() {
	fm_cwp_deactivate();
	// Clear the scheduled cron event
	wp_clear_scheduled_hook( CWP_SNIPPETS_CRON_HOOK );
}
register_deactivation_hook( FMCWP_PLUGIN_FILE, 'cwp_snippets_deactivate_plugin' );

// --- Register Update Check Hook ---
add_action( 'plugins_loaded', 'fmcwp_update_check' );

// --- Hook the cron action to its callback function (defined in license-functions.php) ---
add_action( CWP_SNIPPETS_CRON_HOOK, 'cwp_snippets_perform_scheduled_license_check' );
