<?php 

// Exit if accessed directly
if ( !defined( 'ABSPATH' ) ) exit;

// *********************************************************************************************************************************
// Check For Updated Version

add_filter('plugins_api', 'cwp_snippets_plugin_info', 20, 3);
add_filter('site_transient_update_plugins', 'cwp_snippets_check_for_update');

function cwp_snippets_plugin_info($res, $action, $args) {
    if ($action !== 'plugin_information' || $args->slug !== 'cwp-snippets') {
        return $res;
    }

    // Get the cached version information
    $cached_info = get_transient('cwp_snippets_update_info');

    if (false === $cached_info) {
        $remote = wp_remote_get('https://rgcdata.com/wp-content/uploads/rgcdata/cwp-snippets/version.json');
        if (is_wp_error($remote) || wp_remote_retrieve_response_code($remote) !== 200) {
            // Cache a temporary error response for 1 hour to prevent repeated failed requests
            set_transient('cwp_snippets_update_info', ['error' => true], HOUR_IN_SECONDS);
            return $res; // Return original response on error
        }
        $cached_info = wp_remote_retrieve_body($remote);
        set_transient('cwp_snippets_update_info', $cached_info, 12 * HOUR_IN_SECONDS);
    }

    // If the cache contains an error marker, do nothing further
    if (is_array($cached_info) && isset($cached_info['error'])) {
        return $res; // Return original response if cache has error
    }

    $remote = json_decode($cached_info);
    
    // Check if decoding was successful and the required properties exist
    if (!$remote || !isset($remote->version) || !isset($remote->download_url) || !isset($remote->changelog)) {
        return $res;
    }

    $res = new stdClass();
    $res->name = 'CWP Snippets';
    $res->slug = 'cwp-snippets';
    $res->version = $remote->version;
    $res->tested = '6.8'; // WordPress version compatibility - Consider making this dynamic or removing if not needed
    $res->requires = '7.2'; // PHP version compatibility - Consider making this dynamic or removing if not needed
    $res->author = 'Ron Glen Cates';
    $res->download_link = $remote->download_url;
    $res->sections = ['changelog' => $remote->changelog]; // Assumes your JSON has 'changelog'

    // error_log(print_r($res, true)); // Debug the plugins_api response
    return $res;
}


function cwp_snippets_check_for_update($transient) {
    if (empty($transient->checked)) {
        return $transient;
    }

    // Get the cached version information
    $cached_info = get_transient('cwp_snippets_update_info');

    if (false === $cached_info) {
        $remote = wp_remote_get('https://rgcdata.com/wp-content/uploads/rgcdata/cwp-snippets/version.json');
        if (is_wp_error($remote) || wp_remote_retrieve_response_code($remote) !== 200) {
            // Cache a temporary error response for 1 hour to prevent repeated failed requests
            set_transient('cwp_snippets_update_info', ['error' => true], HOUR_IN_SECONDS);
            return $transient;
        }
        $cached_info = wp_remote_retrieve_body($remote);
        set_transient('cwp_snippets_update_info', $cached_info, 12 * HOUR_IN_SECONDS);
    }
    
    // If the cache contains an error marker, do nothing further
    if (is_array($cached_info) && isset($cached_info['error'])) {
        return $transient;
    }

    $remote = json_decode($cached_info);

    // Make sure $remote and $remote->version exist before comparing
    if ($remote && isset($remote->version) && version_compare($remote->version, CWP_SNIPPETS_VERSION, '>')) {
        $plugin_file = 'cwp-snippets/cwp-snippets.php'; // Make sure this matches your plugin's main file path relative to wp-content/plugins/
        // Ensure download_url exists in the remote JSON
        if (isset($remote->download_url)) {
            $transient->response[$plugin_file] = (object)[
                'slug' => 'cwp-snippets',
                'plugin' => $plugin_file,
                'new_version' => $remote->version,
                'package' => $remote->download_url,
                // Optional: Add icons/banners if your JSON provides them
                // 'icons' => ['default' => $remote->icon_url],
                // 'banners' => ['low' => $remote->banner_low_url, 'high' => $remote->banner_high_url]
            ];
        } else {
             cwp_snippets_conditional_log('Update Check Error: Missing download_url in remote JSON.');
        }
    }

    // error_log(print_r($transient, true)); // Debug the transient to see what is being added
    return $transient;
}

