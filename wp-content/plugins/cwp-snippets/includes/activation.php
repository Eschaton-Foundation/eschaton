<?php 

// Exit if accessed directly
if ( !defined( 'ABSPATH' ) ) exit;



// Add the version on activation
function fmcwp_set_plugin_version() {
    add_option('cwp_snippets_version', CWP_SNIPPETS_VERSION);
}



// *********************************************************************************************************************************
// Create WP Database Table

global $fmcwp_db_version;
$fmcwp_db_version = '1.0'; // Consider incrementing this later for updates

function fmcwp_install() {
    global $wpdb;
    global $fmcwp_db_version;

    $table_name = $wpdb->prefix . 'cwp_snippets';
    $charset_collate = $wpdb->get_charset_collate();

    // --- Add description column definition ---
    $sql = "CREATE TABLE $table_name (
        id mediumint(9) NOT NULL AUTO_INCREMENT,
        time datetime DEFAULT '0000-00-00 00:00:00' NOT NULL,
        modified_time datetime DEFAULT NULL,
        name tinytext NOT NULL,
        type tinytext NOT NULL,
        description text DEFAULT NULL,
        status TINYINT NOT NULL,
        shortcode tinytext NOT NULL,
        code mediumtext NOT NULL,
        css mediumtext NOT NULL,
        location TINYTEXT NOT NULL,
        priority INT NOT NULL DEFAULT 10,
        suppress_cache TINYINT NOT NULL DEFAULT 0,
        version VARCHAR(20) DEFAULT 1.0,
        PRIMARY KEY  (id)
    ) $charset_collate;";

    require_once ABSPATH . 'wp-admin/includes/upgrade.php';

    // --- START: Added Output Buffering around dbDelta ---
    ob_start();
    dbDelta( $sql );
    $dbdelta_output = ob_get_clean(); // Capture any output from dbDelta

    // Optionally log if dbDelta produced output (for debugging)
    if (!empty($dbdelta_output)) {
        cwp_snippets_conditional_log('Activation: dbDelta output: ' . $dbdelta_output);
    }
    // --- END: Added Output Buffering around dbDelta ---



    // Create a secondary table for our debug/error logging
    // Function Location: 'debug-log.php'
    fmcwp_check_and_create_log_table();

    add_option( 'fmcwp_db_version', $fmcwp_db_version );

    // Call the function to remove snippet directories
    fmcwp_remove_snippet_directories();
}



// *********************************************************************************************************************************
// Create Preview Page

function fm_cwp_create_preview_page() {
    ob_start(); // Start buffering
    $preview_page_title = 'CWP Snippet Preview';

    // Setup the arguments for WP_Query
    $args = array(
        'post_type'      => 'page',  // Only search among pages
        'posts_per_page' => 1,       // Limit the result to one page
        'post_status'    => 'publish', // Only search published pages
        'title'          => $preview_page_title // Title to search for
    );

    // Create a new WP_Query instance
    $query = new WP_Query($args);

    // Check if the query returns any posts
    if ($query->have_posts()) {
        $query->the_post();
        $preview_page_check = $query->post;
    } else {
        // Page does not exist, create it
        $preview_page_id = wp_insert_post(array(
            'post_title'    => $preview_page_title,
            'post_content'  => '[cwp_snippet_preview_content]', // Keep using the shortcode
            'post_status'   => 'publish',
            'post_type'     => 'page',
            'comment_status'=> 'closed',
            'ping_status'   => 'closed',
        ));

        if (is_wp_error($preview_page_id)) {
            cwp_snippets_conditional_log('Activation Error: Failed to create preview page: ' . $preview_page_id->get_error_message());
        } else {
            update_option('fm_cwp_preview_page_id', $preview_page_id);
        }
    }

    if (isset($preview_page_check) && $preview_page_check->post_status === 'trash') {
        // If page is in trash, restore it
        $result = wp_update_post(array(
            'ID'          => $preview_page_check->ID,
            'post_status' => 'publish'
        ), true);

        if (is_wp_error($result)) {
            cwp_snippets_conditional_log('Activation Error: Failed to restore preview page from trash: ' . $result->get_error_message());
        }
    }

    ob_end_clean(); // Clean buffer and discard
}



// *********************************************************************************************************************************
// Load Samples

function fmcwp_load_samples() {
    global $wpdb;
    $table_name = $wpdb->prefix . 'cwp_snippets';

    $samples_file = plugin_dir_path(__FILE__) . '../assets/snippets/samples.json';

    if (!file_exists($samples_file)) {
        if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
            error_log( 'CWP Snippets Activation Error: The samples.json file does not exist at ' . $samples_file );
        }
        return;
    }

    $file_content = file_get_contents($samples_file);
    if ($file_content === false) {
        if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
            error_log( 'CWP Snippets Activation Error: Could not read the samples.json file at ' . $samples_file );
        }
        return;
    }
    $samples_data = json_decode($file_content, true);

    if (is_array($samples_data)) {
        foreach ($samples_data as $snippet) {
            $name = isset($snippet['name']) ? sanitize_text_field($snippet['name']) : 'Unnamed Sample';
            $shortcode = isset($snippet['shortcode']) ? sanitize_text_field($snippet['shortcode']) : '';
            $code = isset($snippet['code']) ? wp_unslash($snippet['code']) : '';
            $css = isset($snippet['css']) ? wp_unslash($snippet['css']) : '';
            $status = isset($snippet['status']) ? intval($snippet['status']) : 0;
            $location = isset($snippet['location']) ? sanitize_text_field($snippet['location']) : 'frontend';
            $priority = isset($snippet['priority']) ? intval($snippet['priority']) : 10;

            // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared --
            // $table_name is derived from $wpdb->prefix and is safe to include directly for table checks.
            $existing_snippet_id = $wpdb->get_var($wpdb->prepare(
                "SELECT id FROM $table_name WHERE name = %s AND type = %s",
                $name,
                'Sample'
            ));

            if (!$existing_snippet_id) {
                $inserted = $wpdb->insert(
                    $table_name,
                    array(
                        'name' => $name,
                        'shortcode' => $shortcode,
                        'code' => $code,
                        'css' => $css,
                        'type' => 'Sample',
                        'status' => $status,
                        'location' => $location,
                        'priority' => $priority,
                        'time' => current_time('mysql'),
                    )
                );
                      if ($inserted === false) {
                          if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
                                error_log( 'CWP Snippets Activation Error: Failed to insert sample snippet: ' . $name . ' - DB Error: ' . $wpdb->last_error );
                          }
                      }
            } else {
                // Update existing sample
                $wpdb->update(
                    $table_name,
                    array(
                        'name' => $name,
                        'shortcode' => $shortcode,
                        'code' => $code,
                        'css' => $css,
                        'status' => $status,
                        'location' => $location,
                        'priority' => $priority,
                        'modified_time' => current_time('mysql'),
                    ),
                    array('id' => $existing_snippet_id), // WHERE
                    array( // data formats
                        '%s', '%s', '%s', '%s', '%d', '%s', '%d', '%s'
                    ),
                    array('%d') // where format
                );
            }
        }
    } else {
        if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
            error_log( 'CWP Snippets Activation Error: Invalid samples.json format in file: ' . $samples_file . ' - JSON Error: ' . json_last_error_msg() );
        }
        return;
    }
}



// *********************************************************************************************************************************
// Load Templates

function fmcwp_load_templates() {
    global $wpdb;
    $table_name = $wpdb->prefix . 'cwp_snippets';

    $templates_file = plugin_dir_path(__FILE__) . '../assets/snippets/templates.json';

    if (!file_exists($templates_file)) {
        if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
            error_log( 'CWP Snippets Activation Error: The templates.json file does not exist at ' . $templates_file );
        }
        return;
    }

    $file_content = file_get_contents($templates_file);
    if ($file_content === false) {
        if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
            error_log( 'CWP Snippets Activation Error: Could not read the templates.json file at ' . $templates_file );
        }
        return;
    }
    $templates_data = json_decode($file_content, true);

    if (is_array($templates_data)) {
        foreach ($templates_data as $snippet) {
            $name = isset($snippet['name']) ? sanitize_text_field($snippet['name']) : 'Unnamed Template';
            $shortcode = isset($snippet['shortcode']) ? sanitize_text_field($snippet['shortcode']) : '';
            $code = isset($snippet['code']) ? wp_unslash($snippet['code']) : '';
            $css = isset($snippet['css']) ? wp_unslash($snippet['css']) : '';
            $status = isset($snippet['status']) ? intval($snippet['status']) : 0;
            $location = isset($snippet['location']) ? sanitize_text_field($snippet['location']) : 'frontend';
            $priority = isset($snippet['priority']) ? intval($snippet['priority']) : 10;

            // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared --
            // $table_name is derived from $wpdb->prefix and is safe to include directly for table checks.
            $existing_snippet_id = $wpdb->get_var($wpdb->prepare(
                "SELECT id FROM $table_name WHERE name = %s AND type = %s",
                $name,
                'Template'
            ));

            if (!$existing_snippet_id) {
                $inserted = $wpdb->insert(
                    $table_name,
                    array(
                        'name' => $name,
                        'shortcode' => $shortcode,
                        'code' => $code,
                        'css' => $css,
                        'type' => 'Template',
                        'status' => $status,
                        'location' => $location,
                        'priority' => $priority,
                        'time' => current_time('mysql'),
                    )
                );
                      if ($inserted === false) {
                          if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
                                error_log( 'CWP Snippets Activation Error: Failed to insert template snippet: ' . $name . ' - DB Error: ' . $wpdb->last_error );
                          }
                      }
            } else {
                // Update existing template
                $wpdb->update(
                    $table_name,
                    array(
                        'name' => $name,
                        'shortcode' => $shortcode,
                        'code' => $code,
                        'css' => $css,
                        'status' => $status,
                        'location' => $location,
                        'priority' => $priority,
                        'modified_time' => current_time('mysql'),
                    ),
                    array('id' => $existing_snippet_id), // WHERE
                    array( // data formats
                        '%s', '%s', '%s', '%s', '%d', '%s', '%d', '%s'
                    ),
                    array('%d') // where format
                );
            }
        }
    } else {
        if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
            error_log( 'CWP Snippets Activation Error: Invalid templates.json format in file: ' . $templates_file . ' - JSON Error: ' . json_last_error_msg() );
        }
        return;
    }
}


// *********************************************************************************************************************************
// Load Functions

function fmcwp_load_functions() {
    global $wpdb;
    $table_name = $wpdb->prefix . 'cwp_snippets';

    $functions_file = plugin_dir_path(__FILE__) . '../assets/snippets/functions.json';

    if (!file_exists($functions_file)) {
        if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
            error_log( 'CWP Snippets Activation Error: The functions.json file does not exist at ' . $functions_file );
        }
        return;
    }

    $file_content = file_get_contents($functions_file);
    if ($file_content === false) {
        if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
            error_log( 'CWP Snippets Activation Error: Could not read the functions.json file at ' . $functions_file );
        }
        return;
    }
    $functions_data = json_decode($file_content, true);

    if (is_array($functions_data)) {
        foreach ($functions_data as $snippet) {
            $name = isset($snippet['name']) ? sanitize_text_field($snippet['name']) : 'Unnamed Function';
            // Functions do not have shortcodes
            $code = isset($snippet['code']) ? wp_unslash($snippet['code']) : '';
            $css = isset($snippet['css']) ? wp_unslash($snippet['css']) : ''; // Typically empty for functions
            $status = isset($snippet['status']) ? intval($snippet['status']) : 0; // Default to inactive for functions
            $location = isset($snippet['location']) ? sanitize_text_field($snippet['location']) : 'frontend';
            $priority = isset($snippet['priority']) ? intval($snippet['priority']) : 10;
            $description = isset($snippet['description']) ? wp_kses_post(wp_unslash($snippet['description'])) : '';

            // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared --
            // $table_name is derived from $wpdb->prefix and is safe to include directly for table checks.
            $existing_snippet_id = $wpdb->get_var($wpdb->prepare(
                "SELECT id FROM $table_name WHERE name = %s AND type = %s",
                $name,
                'Function' // Set type to 'Function'
            ));

            if (!$existing_snippet_id) {
                $inserted = $wpdb->insert(
                    $table_name,
                    array(
                        'name' => $name,
                        'shortcode' => '', // No shortcode for functions
                        'code' => $code,
                        'css' => $css,
                        'type' => 'Function', // Set type to 'Function'
                        'status' => $status,
                        'location' => $location,
                        'priority' => $priority,
                        'description' => $description,
                        'time' => current_time('mysql'),
                    )
                );
                      if ($inserted === false) {
                          if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
                                error_log( 'CWP Snippets Activation Error: Failed to insert function snippet: ' . $name . ' - DB Error: ' . $wpdb->last_error );
                          }
                      }
            } else {
                // Update existing function
                $wpdb->update(
                    $table_name,
                    array(
                        'name' => $name,
                        'shortcode' => '', // No shortcode for functions
                        'code' => $code,
                        'css' => $css,
                        'status' => $status,
                        'location' => $location,
                        'priority' => $priority,
                        'description' => $description,
                        'modified_time' => current_time('mysql'),
                    ),
                    array('id' => $existing_snippet_id), // WHERE
                    array( // data formats
                        '%s', '%s', '%s', '%s', '%d', '%s', '%d', '%s', '%s'
                    ),
                    array('%d') // where format
                );
            }
        }
    } else {
        if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
            error_log( 'CWP Snippets Activation Error: Invalid functions.json format in file: ' . $functions_file . ' - JSON Error: ' . json_last_error_msg() );
        }
        return;
    }
}

// *********************************************************************************************************************************
// Load Scripts

function fmcwp_load_scripts() {
    global $wpdb;
    $table_name = $wpdb->prefix . 'cwp_snippets';

    $scripts_file = plugin_dir_path(__FILE__) . '../assets/snippets/scripts.json';

    if (!file_exists($scripts_file)) {
        if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
            error_log( 'CWP Snippets Activation Error: The scripts.json file does not exist at ' . $scripts_file );
        }
        return;
    }

    $file_content = file_get_contents($scripts_file);
    if ($file_content === false) {
        if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
            error_log( 'CWP Snippets Activation Error: Could not read the scripts.json file at ' . $scripts_file );
        }
        return;
    }
    $scripts_data = json_decode($file_content, true);

    if (is_array($scripts_data)) {
        foreach ($scripts_data as $snippet) {
            $name = isset($snippet['name']) ? sanitize_text_field($snippet['name']) : 'Unnamed Script';
            // Scripts do not have shortcodes
            $code = isset($snippet['code']) ? wp_unslash($snippet['code']) : ''; // The script code itself
            $css = isset($snippet['css']) ? wp_unslash($snippet['css']) : ''; // Typically empty for scripts
            $status = isset($snippet['status']) ? intval($snippet['status']) : 0; // Default to inactive
            $location = isset($snippet['location']) ? sanitize_text_field($snippet['location']) : 'frontend';
            $priority = isset($snippet['priority']) ? intval($snippet['priority']) : 10;
            $description = isset($snippet['description']) ? wp_kses_post(wp_unslash($snippet['description'])) : '';

            // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared --
            // $table_name is derived from $wpdb->prefix and is safe to include directly for table checks.
            $existing_snippet_id = $wpdb->get_var($wpdb->prepare(
                "SELECT id FROM $table_name WHERE name = %s AND type = %s",
                $name,
                'Script' // Set type to 'Script'
            ));

            if (!$existing_snippet_id) {
                $inserted = $wpdb->insert(
                    $table_name,
                    array(
                        'name' => $name,
                        'shortcode' => '', // No shortcode for scripts
                        'code' => $code,
                        'css' => $css,
                        'type' => 'Script', // Set type to 'Script'
                        'status' => $status,
                        'location' => $location,
                        'priority' => $priority,
                        'description' => $description,
                        'time' => current_time('mysql'),
                    )
                );
                      if ($inserted === false) {
                          if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
                                error_log( 'CWP Snippets Activation Error: Failed to insert script snippet: ' . $name . ' - DB Error: ' . $wpdb->last_error );
                          }
                      }
            } else {
                // Update existing script
                $wpdb->update(
                    $table_name,
                    array(
                        'name' => $name,
                        'shortcode' => '', // No shortcode for scripts
                        'code' => $code,
                        'css' => $css,
                        'status' => $status,
                        'location' => $location,
                        'priority' => $priority,
                        'description' => $description,
                        'modified_time' => current_time('mysql'),
                    ),
                    array('id' => $existing_snippet_id), // WHERE
                    array( // data formats
                        '%s', '%s', '%s', '%s', '%d', '%s', '%d', '%s', '%s'
                    ),
                    array('%d') // where format
                );
            }
        }
    } else {
        if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
            error_log( 'CWP Snippets Activation Error: Invalid scripts.json format in file: ' . $scripts_file . ' - JSON Error: ' . json_last_error_msg() );
        }
        return;
    }
}

// *********************************************************************************************************************************
// Load Styles

function fmcwp_load_styles() {
    global $wpdb;
    $table_name = $wpdb->prefix . 'cwp_snippets';

    $styles_file = plugin_dir_path(__FILE__) . '../assets/snippets/styles.json';

    if (!file_exists($styles_file)) {
        if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
            error_log( 'CWP Snippets Activation Error: The styles.json file does not exist at ' . $styles_file );
        }
        return;
    }

    $file_content = file_get_contents($styles_file);
    if ($file_content === false) {
        if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
            error_log( 'CWP Snippets Activation Error: Could not read the styles.json file at ' . $styles_file );
        }
        return;
    }
    $styles_data = json_decode($file_content, true);

    if (is_array($styles_data)) {
        foreach ($styles_data as $snippet) {
            $name = isset($snippet['name']) ? sanitize_text_field($snippet['name']) : 'Unnamed Style';
            // Styles do not have shortcodes
            $code = isset($snippet['code']) ? wp_unslash($snippet['code']) : ''; // Typically empty for styles
            $css = isset($snippet['css']) ? wp_unslash($snippet['css']) : ''; // The CSS code itself
            $status = isset($snippet['status']) ? intval($snippet['status']) : 0; // Default to inactive
            $location = isset($snippet['location']) ? sanitize_text_field($snippet['location']) : 'frontend';
            $priority = isset($snippet['priority']) ? intval($snippet['priority']) : 10;
            $description = isset($snippet['description']) ? wp_kses_post(wp_unslash($snippet['description'])) : '';

            // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared --
            // $table_name is derived from $wpdb->prefix and is safe to include directly for table checks.
            $existing_snippet_id = $wpdb->get_var($wpdb->prepare(
                "SELECT id FROM $table_name WHERE name = %s AND type = %s",
                $name,
                'Style' // Set type to 'Style'
            ));

            if (!$existing_snippet_id) {
                $inserted = $wpdb->insert(
                    $table_name,
                    array(
                        'name' => $name,
                        'shortcode' => '', // No shortcode for styles
                        'code' => $code,
                        'css' => $css,
                        'type' => 'Style', // Set type to 'Style'
                        'status' => $status,
                        'location' => $location,
                        'priority' => $priority,
                        'description' => $description,
                        'time' => current_time('mysql'),
                    )
                );
                      if ($inserted === false) {
                          if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
                                error_log( 'CWP Snippets Activation Error: Failed to insert style snippet: ' . $name . ' - DB Error: ' . $wpdb->last_error );
                          }
                      }
            } else {
                // Update existing style
                $wpdb->update(
                    $table_name,
                    array(
                        'name' => $name,
                        'shortcode' => '', // No shortcode for styles
                        'code' => $code,
                        'css' => $css,
                        'status' => $status,
                        'location' => $location,
                        'priority' => $priority,
                        'description' => $description,
                        'modified_time' => current_time('mysql'),
                    ),
                    array('id' => $existing_snippet_id), // WHERE
                    array( // data formats
                        '%s', '%s', '%s', '%s', '%d', '%s', '%d', '%s', '%s'
                    ),
                    array('%d') // where format
                );
            }
        }
    } else {
        if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
            error_log( 'CWP Snippets Activation Error: Invalid styles.json format in file: ' . $styles_file . ' - JSON Error: ' . json_last_error_msg() );
        }
        return;
    }
}

// *********************************************************************************************************************************
// Remove Snippet Directories

function fmcwp_remove_snippet_directories() {
    $snippets_dir = plugin_dir_path(__FILE__) . 'snippets';

    // Directories to check and remove
    $directories_to_remove = ['templates', 'samples'];

    foreach ($directories_to_remove as $dir_name) {
        $directory_path = trailingslashit($snippets_dir) . $dir_name;

        if (is_dir($directory_path)) {
            fmcwp_delete_directory($directory_path);
        }
    }
}

/**
 * Recursive function to delete a directory and all its contents
 *
 * @param string $dir The directory path
 */
function fmcwp_delete_directory($dir) {
    if (!is_dir($dir)) {
        return;
    }

    $items = scandir($dir);

    foreach ($items as $item) {
        if ($item === '.' || $item === '..') {
            continue;
        }

        $item_path = $dir . DIRECTORY_SEPARATOR . $item;

        if (is_dir($item_path)) {
            fmcwp_delete_directory($item_path);
        } else {
            // Use WP helper for deleting files so PHPCS and WP filesystem expectations are met
            @wp_delete_file( $item_path ); // Use @ to suppress potential warnings if file is already gone
        }
    }

    // Try WP_Filesystem() first and fall back to rmdir() if it isn't available or fails.
    // This uses the WP API when possible but preserves current behavior as a fallback.

    // Load the necessary file for WP_Filesystem testing
    require_once ABSPATH . 'wp-admin/includes/file.php';

    // Initialize the WP filesystem, no credentials needed for local files
    if ( WP_Filesystem() ) {
        
        // Use the WP Filesystem API to remove the directory
        global $wp_filesystem;
        if ( is_object( $wp_filesystem ) && method_exists( $wp_filesystem, 'rmdir' ) ) {
            // second arg true -> recursive removal (driver-dependent)
            $wp_filesystem->rmdir( $dir, true );
        } else {
            // only as a fallback
            // dir is contained and pulled from plugin, so should be safe
            @rmdir( $dir );
        }
    } else {
        // only as a fallback
        // dir is contained and pulled from plugin, so should be safe
        @rmdir( $dir );
    }
}

/**
 * Calculates and stores the MD5 hash for each bundled snippet JSON file.
 * This is used to detect when bundled snippets have been updated.
 */
function fmcwp_store_all_bundled_hashes() {
    $snippet_files = [
        'samples'   => 'samples.json',
        'templates' => 'templates.json',
        'functions' => 'functions.json',
        'scripts'   => 'scripts.json',
        'styles'    => 'styles.json',
    ];

    // Use the constant for the plugin path
    $base_path = FMCWP_PLUGIN_PATH . 'assets/snippets/';

    foreach ($snippet_files as $type => $filename) {
        $file_path = $base_path . $filename;
        if (file_exists($file_path)) {
            $hash = md5_file($file_path);
            update_option('cwp_snippets_' . $type . '_hash', $hash);
        }
    }

    // Store the demo database file hash
    $demo_db_file = FMCWP_PLUGIN_PATH . 'assets/demo/CWP Snippets.fmp12';
    if (file_exists($demo_db_file)) {
        $demo_hash = md5_file($demo_db_file);
        update_option('cwp_snippets_demo_db_hash', $demo_hash);
    }
}

/**
 * Checks if the custom error log table exists, and creates it if it doesn't.
 * Used both on activation and toggle on/off
 * Does *not* log data / no ARGS[]
 */
function fmcwp_check_and_create_log_table() {
    global $wpdb;

    $table_name = $wpdb->prefix . 'cwp_error_log';

    // Check if cwp_error_log table exists
    // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared -- $table_name is derived from $wpdb->prefix and safe for table name checks
    if ( $wpdb->get_var( $wpdb->prepare( "SHOW TABLES LIKE %s", $table_name ) ) != $table_name ) {
        
        // Table does not exist, so let's create it.
        $charset_collate = $wpdb->get_charset_collate();

        $sql = "CREATE TABLE $table_name (
            id bigint(20) NOT NULL AUTO_INCREMENT,
            timestamp datetime NOT NULL,
            error_issue varchar(255) NOT NULL,
            snippet_name varchar(255) NOT NULL,
            snippet_id bigint(20) NOT NULL,
            error longtext NOT NULL,
            error_line mediumint(9) NOT NULL,
            PRIMARY KEY  (id)
        ) $charset_collate;";

        require_once ABSPATH . 'wp-admin/includes/upgrade.php';

        // use output buffering to prevent potential header issues from deltadb
        // fixed alex v1.6.2
        ob_start();    
        dbDelta($sql);
        $dbdelta_output = ob_get_clean();

        if (!empty($dbdelta_output)) {
            if ( !empty( $dbdelta_output ) ) {
                cwp_snippets_conditional_log('Activation: Log table creation/update output: ' . $dbdelta_output);
            }
        }

    }
}
