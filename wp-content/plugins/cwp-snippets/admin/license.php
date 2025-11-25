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
//Register License Settings fields

function cwp_snippets_register_license_settings() {
    register_setting(
        'cwp_snippets_license_options_group', // Option group
        'cwp_snippets_license_data',          // Option name
        'cwp_snippets_sanitize_license_data'  // Sanitization callback (we'll define this later)
    );
}
add_action( 'admin_init', 'cwp_snippets_register_license_settings' );

/**
 * Sanitizes the license data option.
 *
 * @param array $input The input data from the form.
 * @return array The sanitized data.
 */
function cwp_snippets_sanitize_license_data( $input ) {
    // Get the currently stored license data
    $current_options = get_option( 'cwp_snippets_license_data', array() );

    // Ensure $input is an array, even if nothing was submitted (though logs show it's populated)
    if ( ! is_array( $input ) ) {
        $input = array();
    }

    // Ensure $current_options is an array if get_option returns something unexpected (though our default is an array)
    if ( ! is_array( $current_options ) ) {
        $current_options = array();
    }



    $new_options = array();

    // License Key: Prioritize input, then current from DB, then empty
    if ( isset( $input['license_key'] ) ) {
        $new_options['license_key'] = sanitize_text_field( $input['license_key'] );
    } elseif ( isset( $current_options['license_key'] ) ) {
        $new_options['license_key'] = $current_options['license_key']; // Assumed already sanitized
    } else {
        $new_options['license_key'] = '';
    }

    // Status: Prioritize input, then current from DB, then 'inactive'
    if ( isset( $input['status'] ) ) {
        $new_options['status'] = sanitize_text_field( $input['status'] );
    } elseif ( isset( $current_options['status'] ) ) {
        $new_options['status'] = $current_options['status']; // Assumed already sanitized
    } else {
        $new_options['status'] = 'inactive';
    }

    // Expiry Date: Prioritize input, then current from DB, then null
    if ( isset( $input['expiry_date'] ) ) {
        $expiry_date_input = sanitize_text_field( $input['expiry_date'] );
        // Allow empty string or a valid date format
        $new_options['expiry_date'] = ( empty($expiry_date_input) || (bool)strtotime($expiry_date_input) ) ? $expiry_date_input : null;
    } elseif ( isset( $current_options['expiry_date'] ) ) {
        $new_options['expiry_date'] = $current_options['expiry_date']; // Assumed already sanitized
    } else {
        $new_options['expiry_date'] = null;
    }

    // Site URL: Prioritize input, then current from DB, then null
    if ( isset( $input['site_url'] ) ) {
        $new_options['site_url'] = esc_url_raw( $input['site_url'] );
    } elseif ( isset( $current_options['site_url'] ) ) {
        $new_options['site_url'] = $current_options['site_url']; // Assumed already sanitized
    } else {
        $new_options['site_url'] = null;
    }

    return $new_options;
}

// *********************************************************************************************************************************
// Callback function to display the content of license page

function fmcwp_license_page_html () {

fmcwp_header();


// Retrieve the license data
$license_data = get_option('cwp_snippets_license_data');
$license_key = '';
$license_status = 'inactive'; // Default status
$license_expiry_date = null; // Initialize $license_expiry_date
$isExpired = false; // Initialize $isExpired

if ( ! empty( $license_data ) && is_array( $license_data ) ) {
    $license_key = isset( $license_data['license_key'] ) ? $license_data['license_key'] : '';
    $license_status = isset( $license_data['status'] ) ? $license_data['status'] : 'inactive';
    $license_expiry_date = isset( $license_data['expiry_date'] ) ? $license_data['expiry_date'] : null; // Correctly assign $license_expiry_date
    $isExpired = (!empty($license_expiry_date) && strtotime($license_expiry_date . ' 23:59:59') < time());
}

// Define user-friendly status texts
$status_texts = array(
    'active' => __( 'License Active', 'cwp-snippets' ),
    'inactive' => __( 'License Inactive', 'cwp-snippets' ),
    'invalid_key' => __( 'Invalid License Key', 'cwp-snippets' ),
    'limit_reached' => __( 'Activation Limit Reached', 'cwp-snippets' ),
    'expired' => __( 'License Expired', 'cwp-snippets' ),
    'unknown' => __( 'Unknown', 'cwp-snippets' )
);
$status_text_css_class = 'cwp-status-' . $license_status; // Default class based on DB status

if ($isExpired && $license_status === 'active') {
    $current_status_text = __('License Expired', 'cwp-snippets');
    $status_text_css_class = 'cwp-status-expired'; // Specific class for expired state
} else {
    $current_status_text = isset( $status_texts[$license_status] ) ? $status_texts[$license_status] : $status_texts['unknown'];
}
// The echo $current_status_text; line was here for debugging, ensure it's removed for production.

?>
<div class="content" style=" padding-left: 15px;">
    <div style="width: 750px;">
        <h1 style="margin-bottom: 40px;"><?php esc_html_e( 'License Management', 'cwp-snippets' ); ?></h1>

        <form method="post" action="options.php">
            <?php settings_fields( 'cwp_snippets_license_options_group' ); ?>
            <p>
                <label for="cwp_snippets_license_key" style="display: block; margin-bottom: 5px;"><?php esc_html_e( 'License Key', 'cwp-snippets' ); ?>:</label>
                <input type="text" id="cwp_snippets_license_key" name="cwp_snippets_license_data[license_key]" value="<?php echo esc_attr( $license_key ); ?>" class="regular-text" style="width: 350px;"/>
            </p>
            <p class="description" style="margin-top: -5px; margin-bottom: 15px; font-size: small;">
                <?php esc_html_e( 'Enter your license key to activate Pro features.', 'cwp-snippets' ); ?>
            </p>
            <div id="cwp_license_status_area" style="margin-bottom: 15px;">
                <p><strong><?php esc_html_e( 'Current Status:', 'cwp-snippets' ); ?></strong> <span id="cwp_license_status_text" class="<?php echo esc_attr($status_text_css_class); ?>"><?php echo esc_html( $current_status_text ); ?></span></p>
            </div>
            
            <?php
            // submit_button( __( 'Activate License', 'cwp-snippets' ), 'primary', 'cwp_snippets_activate_license' );
            // We will replace this with custom buttons later to handle API calls, not direct form submission to options.php for activation.
            ?>
            <p> <?php /* This parent <p> already has its margin/padding reset */ ?>
                <input type="button" name="cwp_snippets_activate_license" id="cwp_snippets_activate_license" class="button button-primary" value="<?php esc_attr_e( 'Activate License', 'cwp-snippets' ); ?>" style="margin-left: 0;"> <?php /* This is the targeted change */ ?>
                <input type="button" name="cwp_snippets_deactivate_license" id="cwp_snippets_deactivate_license" class="button" value="<?php esc_attr_e( 'Deactivate License', 'cwp-snippets' ); ?>" style="margin-left: 10px; display: none; /* Initially hidden */">
                <input type="button" name="cwp_snippets_check_license" id="cwp_snippets_check_license" class="button" value="<?php esc_attr_e( 'Check License Status', 'cwp-snippets' ); ?>" style="margin-left: 10px;">
            </p>
        </form>
    </div>
</div>
<?php

fmcwp_footer();

}