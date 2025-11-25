<?php
/**
 * CWP Snippets Constants and Configuration Loader
 */

// Exit if accessed directly
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Retrieves the FileMaker connection configuration from WordPress options.
 *
 * @param string $type The type of configuration to retrieve ('live' or 'demo'). Defaults to 'live'.
 * @return array An associative array containing the FileMaker connection settings.
 */
function fmcwp_get_fm_config( string $type = 'live' ): array {
	$config = [
		'host'          => null,
		'database'      => null,
		'layout'        => null,
		'user'          => null,
		'password'      => null,
		'options'       => [ 'allowInsecure' => false ], // Default to secure
	];

	$prefix = ( $type === 'demo' ) ? '_demo' : '';

	$config['host']     = get_option( 'fmcwp_host' . $prefix );
	$config['database'] = get_option( 'fmcwp_database' . $prefix );
	$config['layout']   = get_option( 'fmcwp_layout' . $prefix );
	$config['user']     = get_option( 'fmcwp_user' . $prefix );
	$config['password'] = get_option( 'fmcwp_password' . $prefix );

	$insecure_option = get_option( 'fmcwp_insecure' . $prefix );
	if ( $insecure_option === 'true' ) {
		$config['options']['allowInsecure'] = true;
	}

	return $config;
}

/**
 * Defines the global FileMaker connection constants after options are loaded.
 *
 * This function fetches the configuration using fmcwp_get_fm_config()
 * and then defines the global constants (FM_HOST, FM_DATABASE, etc.)
 * that older parts of the code might rely on.
 */
function fmcwp_define_global_constants() {
	// Define Live Constants
	$live_config = fmcwp_get_fm_config('live');
	if ( ! defined( 'FM_HOST' ) ) {
		define( 'FM_HOST', $live_config['host'] );
	}
	if ( ! defined( 'FM_DATABASE' ) ) {
		define( 'FM_DATABASE', $live_config['database'] );
	}
	if ( ! defined( 'FM_LAYOUT' ) ) {
		define( 'FM_LAYOUT', $live_config['layout'] );
	}
	if ( ! defined( 'FM_USER' ) ) {
		define( 'FM_USER', $live_config['user'] );
	}
	if ( ! defined( 'FM_PASSWORD' ) ) {
		define( 'FM_PASSWORD', $live_config['password'] );
	}
	// IMPORTANT: Define FM_OPTIONS as an actual array, not a string.
	if ( ! defined( 'FM_OPTIONS' ) ) {
		define( 'FM_OPTIONS', $live_config['options'] );
	}

	// Define Demo Constants
	$demo_config = fmcwp_get_fm_config('demo');
	if ( ! defined( 'FM_HOST_DEMO' ) ) {
		define( 'FM_HOST_DEMO', $demo_config['host'] );
	}
	if ( ! defined( 'FM_DATABASE_DEMO' ) ) {
		define( 'FM_DATABASE_DEMO', $demo_config['database'] );
	}
	if ( ! defined( 'FM_LAYOUT_DEMO' ) ) {
		define( 'FM_LAYOUT_DEMO', $demo_config['layout'] );
	}
	if ( ! defined( 'FM_USER_DEMO' ) ) {
		define( 'FM_USER_DEMO', $demo_config['user'] );
	}
	if ( ! defined( 'FM_PASSWORD_DEMO' ) ) {
		define( 'FM_PASSWORD_DEMO', $demo_config['password'] );
	}
	// IMPORTANT: Define FM_OPTIONS_DEMO as an actual array, not a string.
	if ( ! defined( 'FM_OPTIONS_DEMO' ) ) {
		define( 'FM_OPTIONS_DEMO', $demo_config['options'] );
	}
}
// Hook the constant definition function into 'init' action hook.
// Priority 4 runs before the default priority 10 and potentially before snippet/shortcode init hooks (like priority 5 used elsewhere).
add_action( 'init', 'fmcwp_define_global_constants', 4 );

// --- Other constants that *can* be defined globally (if any) ---
// Example: define( 'MY_PLUGIN_SOME_STATIC_VALUE', '123' );
// Avoid defining constants based on get_option() here.
