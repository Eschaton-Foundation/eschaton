<?php
/**
 * Admin functionality for CWP Snippets.
 * Handles menu registration, script/style enqueuing, admin notices,
 * and potentially update checks for self-hosted versions.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

// *********************************************************************************************************************************
// Add Admin Menu Items

function fmcwp_plugin_page() {
	// Add main menu page
	add_menu_page(
		'CWP Snippets',            // Page title
		'CWP Snippets',            // Menu title
		'manage_options',          // Capability required
		'fmcwp-snippets',          // Menu slug (unique identifier)
		'fmcwp_page_html',         // Function to display the page content
		'dashicons-editor-code',   // Icon URL or dashicon class
		6                          // Position in the menu order
	);

	// Add submenu page for Snippets (same as main page)
	add_submenu_page(
		'fmcwp-snippets',          // Parent slug
		'Snippets',                // Page title
		'Snippets',                // Menu title
		'manage_options',          // Capability
		'fmcwp-snippets',          // Menu slug (must match parent for the first item)
		'fmcwp_page_html'          // Function to display the page content
	);

	// Add submenu page for Settings
	add_submenu_page(
		'fmcwp-snippets',          // Parent slug
		'Settings',                // Page title
		'Settings',                // Menu title
		'manage_options',          // Capability
		'fmcwp-settings',          // Menu slug
		'fmcwp_settings_html'      // Function to display the page content
	);

	// Add the Demo Setup submenu

    add_submenu_page(
        'fmcwp-snippets',          // Parent slug
        'Demo Setup',              // Page title
        'Demo Setup',              // Menu title
        'manage_options',          // Capability
        'fmcwp-demo-setup',        // Menu slug
        'fmcwp_demo_setup_html'    // Function to display the page content
    );

    // Add submenu page for License
	add_submenu_page(
		'fmcwp-snippets',          // Parent slug
		'License',                 // Page title
		'License',                 // Menu title
		'manage_options',          // Capability
		'fmcwp-license',           // Menu slug
		'fmcwp_license_page_html'  // Function to display the page content
	);

    // Add submenu page for Debug Log
    add_submenu_page(
        'fmcwp-snippets',          // Parent slug
        'Debug Log',               // Page title
        'Debug Log',               // Menu title
        'manage_options',          // Capability
        'fmcwp-debug-log',         // Menu slug
        'fmcwp_debug_log_page_html'// Function to display the page content
    );

	// Add submenu page for Documentation
	add_submenu_page(
		'fmcwp-snippets',          // Parent slug
		'Documentation',    // Page title
		'Documentation',                    // Menu title
		'manage_options',          // Capability
		'cwp-snippets-documentation',       // Menu slug
		'cwp_snippets_documentation_page_html' // Function to display the page content
	);

}
add_action( 'admin_menu', 'fmcwp_plugin_page' );

/**
 * Adds the "Add-ons" submenu page.
 *
 * Hooked at priority 99 to appear before individual add-on pages.
 */
function cwp_snippets_add_addons_menu() {
    add_submenu_page(
        'fmcwp-snippets',               // Parent slug
        'CWP Snippets Add-ons',         // Page title
        'Add-ons',                      // Menu title
        'manage_options',               // Capability
        'cwp-snippets-addons',          // Menu slug
        'cwp_snippets_addons_page_html' // Function to display the page content
    );
}
add_action('admin_menu', 'cwp_snippets_add_addons_menu', 99);

/**
 * Reorders the CWP Snippets submenu to ensure add-on pages appear after the "Add-ons" link.
 *
 * This function runs late on the 'admin_menu' hook to inspect the global $submenu array
 * and rearrange it. It separates core plugin pages from pages added by add-on snippets,
 * placing the latter after the main "Add-ons" page.
 */
function cwp_snippets_reorder_submenu() {
    global $submenu;
    $parent_slug = 'fmcwp-snippets';

    // Check if our submenu exists
    if ( ! isset( $submenu[ $parent_slug ] ) ) {
        return;
    }

    // Define the slugs of core plugin pages and the main add-ons page
    $core_slugs = [
        'fmcwp-snippets', 'fmcwp-settings', 'fmcwp-demo-setup', 'fmcwp-license', 'fmcwp-debug-log', 'cwp-snippets-documentation',
    ];
    $addons_page_slug = 'cwp-snippets-addons';

    $core_items       = [];
    $addons_page_item = [];
    $addon_items      = [];

    // Iterate through the existing submenu items and sort them into buckets
    foreach ( $submenu[ $parent_slug ] as $item ) {
        $item_slug = $item[2]; // The menu_slug is at index 2

        if ( $item_slug === $addons_page_slug ) {
            $addons_page_item[] = $item;
        } elseif ( in_array( $item_slug, $core_slugs, true ) ) {
            $core_items[] = $item;
        } else {
            // Anything else is considered an add-on
            $addon_items[] = $item;
        }
    }

    // Rebuild the submenu array in the correct order: Core pages, the "Add-ons" page, then all other add-on pages.
    $submenu[ $parent_slug ] = array_merge( $core_items, $addons_page_item, $addon_items );
}
// Use a high priority (e.g., 101) to run after all other menu items have been added.
add_action( 'admin_menu', 'cwp_snippets_reorder_submenu', 101 );

// *********************************************************************************************************************************
// Enqueue Admin Scripts and Styles

function cwp_snippets_admin_enqueue_assets($hook_suffix) {

	// --- Custom Admin CSS ---
	wp_enqueue_style(
		'cwp-snippets-admin-css',
		FMCWP_PLUGIN_URL . 'admin/css/snippets-admin.css',
		array(),
		CWP_SNIPPETS_VERSION
	);


    // --- Check if on the correct page ---
    $main_page_hook = 'toplevel_page_fmcwp-snippets';
    $submenu_page_hook = 'cwp-snippets_page_fmcwp-snippets';
    $license_page_hook = 'cwp-snippets_page_fmcwp-license'; // Hook for the license page

    if ( $hook_suffix === $main_page_hook || $hook_suffix === $submenu_page_hook ) {

		// --- Enqueue Scripts for List View ---
		wp_enqueue_script(
            'cwp-snippets-admin-js',
            FMCWP_PLUGIN_URL . 'admin/js/admin.js',
            array('jquery'),
            CWP_SNIPPETS_VERSION,
            true
        );

        

        // --- Localize Data for List View Script (cwpAdminData) ---
    // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- These are read-only GET parameters used for display/localization only and are not processing form submissions. Nonce checks are applied on the actual form/AJAX endpoints where required.
    $page_slug = isset($_GET['page']) ? sanitize_text_field(wp_unslash($_GET['page'])) : 'fmcwp-snippets';
    // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- See above. This is a display filter value used only to adjust UI; actual actions validate nonces elsewhere.
    $current_filter = isset($_GET['filter_type']) ? sanitize_text_field(wp_unslash($_GET['filter_type'])) : 'Snippet';

        $list_data_to_pass = array(
            'page'           => $page_slug,
            'current_filter' => $current_filter,
        );

        wp_localize_script(
            'cwp-snippets-admin-js',
            'cwpAdminData',
            $list_data_to_pass
        );


        // --- Conditionally Enqueue Scripts/Data for Edit/New View ---
    // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- This inspects display action parameters only (edit/new) and does not perform state-changing operations here.
    $is_edit_or_new = ( isset($_GET['action']) && ($_GET['action'] === 'edit' || $_GET['action'] === 'new') );

        if ( $is_edit_or_new ) {

            // Enqueue the editor script
            wp_enqueue_script(
                'cwp-snippets-edit-js',
                FMCWP_PLUGIN_URL . 'admin/js/snippets-edit.js',
                array('jquery', 'wp-theme-plugin-editor'),
                CWP_SNIPPETS_VERSION,
                true
            );

            // Localize data specifically for the editor script (cwpEditData)
            $transient_key = 'fmcwp_active_editor';
            $active_editor = ($current_filter === 'Style') ? 'css' : (get_transient($transient_key) ? get_transient($transient_key) : 'code');
            $uniqueness_nonce = wp_create_nonce('check_snippet_uniqueness_action');

            $edit_data_to_pass = array(
                'ajaxurl'        => admin_url('admin-ajax.php'),
                'uniquenessNonce' => $uniqueness_nonce,
                'activeEditor'   => $active_editor,
                'current_filter' => $current_filter
            );

            wp_localize_script(
                'cwp-snippets-edit-js',
                'cwpEditData',
                $edit_data_to_pass
            );
        }
    }

    // --- Enqueue Scripts for Snippet Import on ALL CWP pages ---
    // This script handles the import dialog, which can be triggered by the bundled update notice on any page.
    $screen = get_current_screen();
    if ($screen && strpos($screen->id, 'fmcwp') !== false) {
        wp_enqueue_script(
            'cwp-snippets-import-js',
            FMCWP_PLUGIN_URL . 'admin/js/snippets-import.js',
            array('jquery'),
            CWP_SNIPPETS_VERSION,
            true
        );
    }

    // --- Enqueue Scripts for License Page ---
    if ( $hook_suffix === $license_page_hook ) {
        wp_enqueue_script(
            'cwp-snippets-license-admin-js',
            FMCWP_PLUGIN_URL . 'admin/js/license-admin.js',
            array('jquery'),
            CWP_SNIPPETS_VERSION,
            true
        );

        // Define user-friendly status texts (mirroring those in license.php for consistency)
        $status_texts = array(
            'active'        => __( 'License Active', 'cwp-snippets' ),
            'inactive'      => __( 'License Inactive', 'cwp-snippets' ),
            'invalid_key'   => __( 'Invalid License Key', 'cwp-snippets' ),
            'limit_reached' => __( 'Activation Limit Reached', 'cwp-snippets' ),
            'expired'       => __( 'License Expired', 'cwp-snippets' ),
            'unknown'       => __( 'Unknown', 'cwp-snippets' )
        );

        // Localize data for the license script
        wp_localize_script(
            'cwp-snippets-license-admin-js',
            'cwpLicenseAdminData',
            array(
                'ajaxurl'      => admin_url('admin-ajax.php'),
                'nonce'        => wp_create_nonce('cwp_license_ajax_nonce'), // Nonce for license actions
                'status_texts' => $status_texts
            )
        );
    }
}
add_action('admin_enqueue_scripts', 'cwp_snippets_admin_enqueue_assets');




// *********************************************************************************************************************************
// Admin Header and Footer Functions

/**
 * Includes the admin header.
 */
function fmcwp_header() {
    
    $header_path = FMCWP_PLUGIN_PATH . 'admin/views/admin-header.php';
    if ( file_exists( $header_path ) ) {
        require_once $header_path;
        } else {
        echo '<div class="wrap"><h2>CWP Snippets</h2></div>';
        if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
            error_log('CWP Snippets Error: admin-header.php view file not found.');
        }
    }

    // --- Call the function to display the admin notice (if applicable) ---
    if ( function_exists('cwp_pro_admin_notice') ) {
        cwp_pro_admin_notice();
    }
    fmcwp_display_bundled_snippet_update_notice();
    fmcwp_display_demo_db_update_notice();
    fmcwp_display_admin_notices();

}


/**
 * Includes the admin footer view file.
 */
function fmcwp_footer() {
    // Ensure the path is correct relative to the main plugin file constant
    $footer_path = FMCWP_PLUGIN_PATH . 'admin/views/admin-footer.php';
    if ( file_exists( $footer_path ) ) {
        require_once $footer_path;
    } else {
        // Optional: Add error logging or display a simple fallback footer
        if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
            error_log('CWP Snippets Error: admin-footer.php view file not found.');
        }
    }
}
