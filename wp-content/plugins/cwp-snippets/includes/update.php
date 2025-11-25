<?php 

// Exit if accessed directly
if ( !defined( 'ABSPATH' ) ) exit;

/**
 * Check if the plugin has been updated and run any necessary update code.
 */
function fmcwp_update_check() {
    $current_version = CWP_SNIPPETS_VERSION;
    $stored_version = get_option('cwp_snippets_version');

    if ($current_version !== $stored_version) {
        // This is an update, so run any necessary update code here
        fmcwp_plugin_update($stored_version, $current_version); // Pass the old and new version numbers

        // Update the stored version
        update_option('cwp_snippets_version', $current_version);
    }
}

/**
 * Handle plugin updates.
 */
function fmcwp_plugin_update($old_version, $new_version) {

    // Perform database schema updates in a targeted, reliable way.
    fmcwp_add_suppress_cache_column();
    fmcwp_check_and_create_log_table(); // Ensure log table exists on update.
    fmcwp_check_for_bundled_snippet_updates();
    fmcwp_check_for_demo_db_update();

}

/**
 * Adds the suppress_cache column to the snippets table if it doesn't exist.
 * This is a more robust method than relying on dbDelta for updates.
 */
function fmcwp_add_suppress_cache_column() {
    global $wpdb;
    $table_name = $wpdb->prefix . 'cwp_snippets';
    // Use a sanitized column name. This is a fixed internal column we control.
    $column_name = sanitize_key( 'suppress_cache' );

    // Check if the column already exists
    $column = $wpdb->get_results( $wpdb->prepare(
        "SELECT * FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = %s AND TABLE_NAME = %s AND COLUMN_NAME = %s",
        DB_NAME, $table_name, $column_name
    ) );

    // If the column does not exist, add it.
    if ( empty( $column ) ) {
        // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- $table_name is derived from $wpdb->prefix and $column_name is a fixed, sanitized internal column name. Placeholders cannot be used for SQL identifiers (table/column names).
        // phpcs:ignore WordPress.DB.SchemaChange -- This ALTER TABLE is a controlled, one-time schema update run during plugin updates. It adds a single known column to the plugin's table; using dbDelta is more invasive for this small change.
        $wpdb->query( "ALTER TABLE `{$table_name}` ADD `{$column_name}` TINYINT NOT NULL DEFAULT 0" );
    }
}

/**
 * Checks if bundled snippet files (JSON) have changed since the last check.
 * If a file has changed, it sets a transient to flag that an update is available.
 */
function fmcwp_check_for_bundled_snippet_updates() {
    $snippet_files = [
        'samples'   => 'samples.json',
        'templates' => 'templates.json',
        'functions' => 'functions.json',
        'scripts'   => 'scripts.json',
        'styles'    => 'styles.json',
    ];

    $base_path = FMCWP_PLUGIN_PATH . 'assets/snippets/';

    foreach ($snippet_files as $type => $filename) {
        $file_path = $base_path . $filename;
        if (file_exists($file_path)) {
            $current_hash = md5_file($file_path);
            $stored_hash = get_option('cwp_snippets_' . $type . '_hash');

            if ($current_hash !== $stored_hash) {
                set_transient('cwp_update_available_' . $type, true, YEAR_IN_SECONDS);
            }
        }
    }
}

/**
 * Checks if the bundled demo database has changed since the last check.
 * If the file has changed, it sets a transient to flag that an update is available.
 */
function fmcwp_check_for_demo_db_update() {
    $demo_db_file = FMCWP_PLUGIN_PATH . 'assets/demo/CWP Snippets.fmp12';
    if (file_exists($demo_db_file)) {
        $current_hash = md5_file($demo_db_file);
        $stored_hash = get_option('cwp_snippets_demo_db_hash');

        if ($current_hash !== $stored_hash) {
            set_transient('cwp_update_available_demo_db', true, YEAR_IN_SECONDS);
        }
    }
}