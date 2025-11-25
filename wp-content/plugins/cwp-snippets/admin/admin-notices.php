<?php
/**
 * CWP Snippets - Admin Notices Functionality
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

// *********************************************************************************************************************************
// Display Pro Upsell Notice

/**
 * Displays an admin notice suggesting the Pro version if applicable.
 */
function cwp_pro_admin_notice() {
	// Only show if Pro is NOT active and user can manage options
	if ( function_exists('cwp_is_pro_active') && ! cwp_is_pro_active() && current_user_can('manage_options') ) {

		// Check if the notice has been dismissed temporarily
        $dismissed_until = get_user_meta( get_current_user_id(), 'cwp_dismiss_pro_notice_until', true );

        // If dismissed_until timestamp exists and is still in the future, hide the notice
        if ( $dismissed_until && time() < $dismissed_until ) {
             return;
        }

        // --- Notice display logic continues below ---

		$screen = get_current_screen();
		// Only show on admin pages
		if ( $screen && strpos($screen->id, 'fmcwp') !== false ) {

            // --- Enqueue and Localize Dismissal Script ---
            wp_enqueue_script(
                'cwp-snippets-notice-dismiss-js',
                FMCWP_PLUGIN_URL . 'admin/js/snippets-notices.js',
                array('jquery'),
                CWP_SNIPPETS_VERSION,
                true
            );
            $dismiss_nonce = wp_create_nonce("cwp_dismiss_pro_notice_nonce");
            $notice_data = array(
                'ajaxurl' => admin_url('admin-ajax.php'),
                'nonce'   => $dismiss_nonce
            );
            wp_localize_script(
                'cwp-snippets-notice-dismiss-js',
                'cwpNoticeData',
                $notice_data
            );

            // --- Display the Notice HTML ---
			?>
			<div class="notice notice-info is-dismissible cwp-pro-notice" style="margin-top: 15px; margin-bottom: 15px;">
				<p>
                    <?php
                    // Build the HTML pieces separately to keep translation clean and safe.
                    $strong_open  = '<strong>';
                    $strong_close = '</strong>';
                    $link_open    = '<a href="https://cwpsnippets.com" target="_blank" rel="noopener noreferrer">';
                    $link_close   = '</a>';

                    // translators: 1: strong open tag, 2: strong close tag, 3: link open tag, 4: link close tag
                    $message = sprintf(__('Supercharge your snippets! Check out %1$sCWP Snippets Pro%2$s for advanced features like functions, scripts and styles, import/export, shortcode attributes, and more. %3$sLearn More%4$s','cwp-snippets'),
                        $strong_open,
                        $strong_close,
                        $link_open,
                        $link_close
                    );

                    // Sanitize allowed HTML (strong and anchor tags are allowed by wp_kses_post)
                    echo wp_kses_post( $message );
                    ?>
				</p>
			</div>
			<?php

		}
	}
}

/**
 * Displays an admin notice if updates for bundled snippets are available.
 */
function fmcwp_display_bundled_snippet_update_notice() {
    // Only show to users who can manage options, on our plugin pages.
    if (!current_user_can('manage_options')) {
        return;
    }

    $screen = get_current_screen();
    if (!$screen || strpos($screen->id, 'fmcwp') === false) {
        return;
    }

    $update_messages = [];
    $snippet_types = [
        'samples'   => 'Samples',
        'templates' => 'Templates',
        'functions' => 'Functions',
        'scripts'   => 'Scripts',
        'styles'    => 'Styles',
    ];

    foreach ( $snippet_types as $type_key => $type_label ) {
        if ( get_transient( 'cwp_update_available_' . $type_key ) ) {
            $skip_url = wp_nonce_url(
                admin_url( 'admin.php?page=fmcwp-snippets&fmcwp_action=skip_bundled_update&type=' . rawurlencode( $type_key ) ),
                'fmcwp_skip_bundled_' . $type_key
            );

            /* translators: %s: lowercase type label (e.g. "samples") */
            $description = sprintf(__( 'A new version of the bundled %s is available.', 'cwp-snippets' ),
                esc_html( strtolower( $type_label ) )
            );

            $update_messages[] = '<li><strong>' . esc_html( $type_label ) . '</strong>: ' . esc_html( $description ) . ' <button type="button" class="button-link cwp-update-bundled-btn" data-type="' . esc_attr( $type_key ) . '" style="padding:0; vertical-align:baseline; border:none; box-shadow:none;">' . esc_html__( 'Update Now', 'cwp-snippets' ) . '</button> | <a href="' . esc_url( $skip_url ) . '" style="color:#a0a5aa;">' . esc_html__( 'Skip this update', 'cwp-snippets' ) . '</a></li>';
        }
    }

        if (!empty($update_messages)) {
                echo '<div class="notice notice-info is-dismissible">';
                echo '<p>' . esc_html__('Updates are available for bundled CWP Snippets:', 'cwp-snippets') . '</p>';
                echo '<ul style="list-style: disc; padding-left: 20px;">';
                echo wp_kses_post( implode( '', $update_messages ) ); // The messages already have <li> tags; no need to add more.  raw html formatted string
                echo '</ul>';
                $skip_all_url = wp_nonce_url(
                    admin_url('admin.php?page=fmcwp-snippets&fmcwp_action=skip_all_bundled'),
                    'fmcwp_skip_all_bundled_nonce'
                );
                echo '<p style="margin-top: 10px; margin-left: 20px;"><button type="button" id="cwp-update-all-bundled-btn" class="button button-primary">' . esc_html__('Update All', 'cwp-snippets') . '</button><a href="' . esc_url($skip_all_url) . '" class="button" style="margin-left: 10px;">' . esc_html__('Skip All', 'cwp-snippets') . '</a></p>';
                echo '</div>';
            }
        }
/**
 * Displays an admin notice if an update for the bundled demo database is available.
 */
function fmcwp_display_demo_db_update_notice() {
    // Only show to users who can manage options.
    if (!current_user_can('manage_options')) {
        return;
    }

    // Only show on CWP Snippets admin pages.
    $screen = get_current_screen();
    if (!$screen || strpos($screen->id, 'fmcwp') === false) {
        return;
    }

    if (get_transient('cwp_update_available_demo_db')) {
        // Button 1: URL to go to the Demo Setup page AND dismiss the notice.
        $setup_and_dismiss_url = wp_nonce_url(
            admin_url('admin.php?page=fmcwp-demo-setup&fmcwp_action=dismiss_demo_db_update_notice'),
            'fmcwp_dismiss_demo_db_notice_nonce'
        );

        // Button 2: URL to just dismiss the notice and stay on the current page.
        $dismiss_url = wp_nonce_url(
            add_query_arg('fmcwp_action', 'dismiss_demo_db_update_notice'),
            'fmcwp_dismiss_demo_db_notice_nonce'
        );

        echo '<div class="notice notice-info">';
        // Title: do not wrap HTML inside the translatable string.
        echo '<p><strong>' . esc_html__( 'CWP Snippets - Demo Database Update Available', 'cwp-snippets' ) . '</strong></p>';
        echo '<p>' . esc_html__( 'The bundled FileMaker demo database, used by the sample and template snippets, has been updated. Please visit the Demo Setup page to download and install the new version.', 'cwp-snippets' ) . '</p>';
        echo '<p>';
        echo '<a href="' . esc_url( $setup_and_dismiss_url ) . '" class="button button-primary">' . esc_html__( 'Go to Demo Setup', 'cwp-snippets' ) . '</a>';
        echo '<a href="' . esc_url( $dismiss_url ) . '" class="button" style="margin-left: 10px;">' . esc_html__( 'Dismiss', 'cwp-snippets' ) . '</a>';
        echo '</p>';
        echo '</div>';
    }
}

/**
 * Displays persistent admin notices for any snippets that were automatically
 * deactivated due to a fatal runtime error.
 */
function fmcwp_display_fatal_error_notices() {

    // Only show to users who can manage options.
    if (!current_user_can('manage_options')) {
        return;
    }

    global $wpdb;
    // Find all our fatal error transients
    $transient_prefix = '_transient_cwp_fatal_error_';
    // Build the query using a variable for the table name and then prepare it. This keeps the SQL construction explicit
    // while still using $wpdb->prepare for the placeholder values.
    $options_table = $wpdb->options;
    $error_transients = $wpdb->get_results( $wpdb->prepare( 'SELECT option_name, option_value FROM ' . $options_table . ' WHERE option_name LIKE %s', $wpdb->esc_like( $transient_prefix ) . '%' ) );

    // If none found, exit early
    if (empty($error_transients)) {
        return;
    }

    foreach ($error_transients as $transient) {
        $notice_data = maybe_unserialize($transient->option_value);
        if (is_array($notice_data) && isset($notice_data['id'], $notice_data['name'], $notice_data['message'])) {
            $snippet_id = intval($notice_data['id']);
            $snippet_name = esc_html($notice_data['name']);
            $error_message = esc_html($notice_data['message']);
            
            // We need to find the snippet type to build the correct edit URL
            $snippet_type_table_name = $wpdb->prefix . 'cwp_snippets';
            // Prepare and fetch the snippet type using the table name
            $snippet_type = $wpdb->get_var( $wpdb->prepare( 'SELECT type FROM ' . $snippet_type_table_name . ' WHERE id = %d', $snippet_id ) );
            if (!$snippet_type) {
                $snippet_type = 'Function'; // Fallback
            }

            // Create the URL for the "Review Snippet" button, which will dismiss the notice and then redirect to the edit page.
            $review_and_dismiss_url = add_query_arg([
                'fmcwp_action' => 'dismiss_fatal_error',
                'snippet_id'   => $snippet_id,
                'review'       => '1',
                'snippet_type' => $snippet_type,
            ]);
            $review_and_dismiss_url = wp_nonce_url($review_and_dismiss_url, 'fmcwp_dismiss_fatal_' . $snippet_id, '_cwp_nonce_dismiss_fatal');

            // Create the URL for the "Dismiss This Notice" button, which just dismisses the notice.
            $dismiss_url = add_query_arg([
                'fmcwp_action' => 'dismiss_fatal_error',
                'snippet_id'   => $snippet_id,
            ]);
            $dismiss_url = wp_nonce_url($dismiss_url, 'fmcwp_dismiss_fatal_' . $snippet_id, '_cwp_nonce_dismiss_fatal');

            // Display the notice
            echo '<div class="notice notice-error" style="border-left-color: #d63638;">'; // Not dismissible by default, stronger red color

            /* translators: 1: snippet name, 2: snippet ID */
            $notice_title = sprintf(__(
                    '<strong>CWP SNIPPETS - CRITICAL ERROR:</strong> The snippet "<strong>%1$s</strong>" (ID: %2$d) caused a fatal error and has been automatically deactivated for site safety.',
                    'cwp-snippets'
                ),
                $snippet_name,
                $snippet_id
            );

            // Sanitize allowed HTML - better safe than sorry ;P
            echo '<p style="font-size: 1.1em;">' . wp_kses_post( $notice_title ) . '</p>';
            echo '<p><strong>' . esc_html__( 'Error Message:', 'cwp-snippets' ) . '</strong> <code>' . esc_html($error_message) . '</code></p>';
            echo '<p><a href="' . esc_url( $review_and_dismiss_url ) . '" class="button button-primary">' . esc_html__( 'Review & Dismiss', 'cwp-snippets' ) . '</a> ';
            echo '<a href="' . esc_url( $dismiss_url ) . '" class="button">' . esc_html__( 'Dismiss This Notice', 'cwp-snippets' ) . '</a></p>';
            echo '</div>';
        }
    }
}
add_action('admin_notices', 'fmcwp_display_fatal_error_notices');

// *********************************************************************************************************************************
// Handle AJAX Request for Notice Dismissal

add_action( 'wp_ajax_cwp_dismiss_pro_notice', 'cwp_handle_dismiss_pro_notice' );
function cwp_handle_dismiss_pro_notice() {
    // Verify nonce
	if ( ! check_ajax_referer( 'cwp_dismiss_pro_notice_nonce', '_ajax_nonce', false ) ) {
		wp_send_json_error( 'Invalid nonce' );
	}
    // Check capability
	if ( ! current_user_can( 'manage_options' ) ) {
		wp_send_json_error( 'Permission denied' );
	}

    // Calculate timestamp for one day from now
    $dismiss_duration = DAY_IN_SECONDS;
    $dismiss_until_timestamp = time() + $dismiss_duration;

    // Update user meta with the future timestamp
	update_user_meta( get_current_user_id(), 'cwp_dismiss_pro_notice_until', $dismiss_until_timestamp );

    // Delete the old boolean flag if it exists
    delete_user_meta( get_current_user_id(), 'cwp_dismissed_pro_notice' );

	wp_send_json_success();
}
