<?php 

use PhpParser\ParserFactory; 
use PhpParser\Error;       

// Exit if accessed directly
if ( !defined( 'ABSPATH' ) ) exit;


/**
 * Checks if the Pro version features are active.
 *
 * @since 1.7.0 // Or your next version
 * @return bool True if Pro features should be active, false otherwise.
 */
function cwp_is_pro_active() {
    
    // Define the transient key
    $transient_key = 'cwp_license_status_cache';
    $status_to_check = 'inactive'; // Default to inactive
    $expiry_date_to_check = null;

    // Try to get the license data from the transient
    $cached_license_data = get_transient( $transient_key );

    if ( false !== $cached_license_data && is_array( $cached_license_data ) ) {
        // Use data from transient
        $status_to_check = isset( $cached_license_data['status'] ) ? $cached_license_data['status'] : 'inactive';
        $expiry_date_to_check = isset( $cached_license_data['expiry_date'] ) ? $cached_license_data['expiry_date'] : null;
    } else {
        // Transient is not set or expired, perform a live check if a license key exists.
        $current_option_data = get_option( 'cwp_snippets_license_data', array() );
        $license_key_from_option = isset( $current_option_data['license_key'] ) ? $current_option_data['license_key'] : '';
        $current_db_status = isset( $current_option_data['status'] ) ? $current_option_data['status'] : 'unknown';
        $current_db_expiry_date = isset( $current_option_data['expiry_date'] ) ? $current_option_data['expiry_date'] : null;

        if ( ! empty( $license_key_from_option ) ) {
            if (defined('WP_DEBUG') && WP_DEBUG) { // Only log this if WP_DEBUG is on
                error_log("CWP Snippets: cwp_is_pro_active - Transient stale, performing live server check for key: '$license_key_from_option'.");
            }
            // A license key exists, so perform a live check.
            // fmcwp_perform_license_server_request will update the option and set the transient.
            $check_result = fmcwp_perform_license_server_request( 'verify', $license_key_from_option, $current_db_status, $current_db_expiry_date );
            $status_to_check = $check_result['final_status'];
            $expiry_date_to_check = $check_result['final_expiry'];
        } else {
            // No license key in options, so it's definitely not active. Use defaults from initialization.
            // $status_to_check remains 'inactive', $expiry_date_to_check remains null.
        }
    }

    $is_status_active = ($status_to_check === 'active');
    $is_expiry_valid_or_empty = ( empty( $expiry_date_to_check ) || strtotime( $expiry_date_to_check . ' 23:59:59' ) >= time() );
    
    // Now, check the status and expiry date obtained from either transient or live check
    if ( $is_status_active && $is_expiry_valid_or_empty ) {
        return true;
    }
    return false;

}



// *********************************************************************************************************************************
// Prepare code for evaluation

function prepare_code_for_evaluation($code) {
    // Trim the code to remove any surrounding whitespace
    $code = trim($code);

    // Determine if the code starts with a PHP opening tag
    $startsWithPHPTag = substr($code, 0, 5) === '<?php';

    // Determine if the code ends with a PHP closing tag
    $endsWithPHPTag = substr($code, -2) === '?>';

    // Remove the initial PHP opening tag if present (for evaluation compatibility)
    if ($startsWithPHPTag) {
        $code = substr($code, 5);
    }

    // Remove the PHP closing tag at the end if present
    if ($endsWithPHPTag) {
        $code = substr($code, 0, -2);
    }

    // Determine if PHP code is left open without closure
    $opensPHP = $startsWithPHPTag && !$endsWithPHPTag;

    // Count PHP opening and closing tags to determine imbalance
    $openingTags = substr_count($code, '<?php');
    $closingTags = substr_count($code, '?>');

    // Add a closing PHP tag at the beginning if it doesn't start with a PHP tag
    if (!$startsWithPHPTag) {
        $code = '?>' . $code;
    }

    // If PHP opens and the number of opening tags exceeds closing tags, close PHP at the end
    if ($opensPHP && ($openingTags > $closingTags)) {
        $code .= ' <?php';
    }

    return $code;
}

// *********************************************************************************************************************************
// Validate CSS

function fm_cwp_is_valid_css($css) {
    // Basic check for balanced braces
    if (substr_count($css, '{') !== substr_count($css, '}')) {
        return false; // Unbalanced number of braces
    }
    return true; // Passes the basic check
}


// *********************************************************************************************************************************
// Check For Function Conflicts
function fmcwp_check_code_conflicts($code, $snippet_id = 0, $location = 'everywhere') {
    global $wpdb;
    $table_name = $wpdb->prefix . 'cwp_snippets';

    // Sanitize location to be safe, default to 'everywhere' for max protection
    $location = in_array($location, ['frontend', 'admin', 'everywhere']) ? $location : 'everywhere';

    // 1. Check for function conflicts
    if (preg_match_all('/^\s*function\s+([a-zA-Z_\x7f-\xff][a-zA-Z0-9_\x7f-\xff]*)\s*\(/mi', $code, $matches)) {
        foreach ($matches[1] as $function_name) {
            // Check against other active snippets in the database, considering location.
            $pattern = 'function[[:space:]]+' . preg_quote($function_name) . '[[:space:]]*\(';
            // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared --
            // $table_name is derived from $wpdb->prefix and safe to include for table identifiers.
            $sql = $wpdb->prepare(
                "SELECT id FROM $table_name WHERE status = 1 AND type = 'Function' AND id != %d AND code RLIKE %s AND (location = 'everywhere' OR %s = 'everywhere' OR location = %s)",
                $snippet_id,
                $pattern,
                $location,
                $location
            );
            if ($wpdb->get_var($sql)) {
                return ['conflict' => true, 'name' => $function_name, 'type' => 'function'];
            }

            // Check against the live admin environment, but only if the snippet is meant to run here.
            if (in_array($location, ['admin', 'everywhere'])) {
                if (function_exists($function_name)) {
                    $is_current_snippet_active = false;
                    if ($snippet_id > 0) {
                        // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- $table_name is derived from $wpdb->prefix and safe to include for table identifiers.
                        $current_status = $wpdb->get_var($wpdb->prepare("SELECT status FROM $table_name WHERE id = %d", $snippet_id));
                        if ($current_status == 1) {
                            $is_current_snippet_active = true;
                        }
                    }
                    if (!$is_current_snippet_active) {
                        return ['conflict' => true, 'name' => $function_name, 'type' => 'function'];
                    }
                }
            }
        }
    }

    // 2. Check for class conflicts (similar logic)
    if (preg_match_all('/^\s*class\s+([a-zA-Z_\x7f-\xff][a-zA-Z0-9_\x7f-\xff]*)/mi', $code, $matches)) {
        if (count($matches[1]) !== count(array_unique($matches[1]))) {
            $duplicates = array_diff_key($matches[1], array_unique($matches[1]));
            return ['conflict' => true, 'name' => reset($duplicates), 'type' => 'duplicate_class_in_snippet'];
        }

        foreach ($matches[1] as $class_name) {
            // Check against other active snippets, considering location.
            $pattern = 'class[[:space:]]+' . preg_quote($class_name) . '([[:space:]]+|\{)';
            // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared --
            // $table_name is derived from $wpdb->prefix and safe to include for table identifiers.
            $sql = $wpdb->prepare(
                "SELECT id FROM $table_name WHERE status = 1 AND type = 'Function' AND id != %d AND code RLIKE %s AND (location = 'everywhere' OR %s = 'everywhere' OR location = %s)",
                $snippet_id,
                $pattern,
                $location,
                $location
            );
            if ($wpdb->get_var($sql)) {
                return ['conflict' => true, 'name' => $class_name, 'type' => 'class'];
            }

            // Check against the live admin environment, but only if the snippet is meant to run here.
            if (in_array($location, ['admin', 'everywhere'])) {
                if (class_exists($class_name, false)) {
                    $is_current_snippet_active = false;
                    if ($snippet_id > 0) {
                        // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- $table_name is derived from $wpdb->prefix and safe to include for table identifiers.
                        $current_status = $wpdb->get_var($wpdb->prepare("SELECT status FROM $table_name WHERE id = %d", $snippet_id));
                        if ($current_status == 1) {
                            $is_current_snippet_active = true;
                        }
                    }
                    if (!$is_current_snippet_active) {
                        return ['conflict' => true, 'name' => $class_name, 'type' => 'class'];
                    }
                }
            }
        }
    }

    return array('conflict' => false, 'name' => '', 'type' => '');
}

/**
 * Safely checks PHP code for syntax errors without executing it.
 *
 * @param string $code The PHP code to check.
 * @return array An array with 'error' => boolean and 'message' => string.
 */
function fmcwp_check_php_syntax($code, $snippet_name = null, $snippet_id = null) {

    // if user submitted a snippet/code (should always be true) then parse, otherwise return as an empty array with no errors.
    // no code == no errors ;)
    // all trimming and php truncation will be done in cwp_snippets_check_php_syntax expression for simplicity
    if($code) { 

        $cwpSyntaxResults = cwp_snippets_check_php_syntax($code); 

        if( false == $cwpSyntaxResults['error'] ) {

            // no error - all good!
            return ['error' => false, 'message' => ''];

        } 

            // There was an error - our function should return the nature of that error and the
            // specificity of the issue
            // Translators: 1: error message, 2: line number
            $errMessage = sprintf(__('%1$s. Error occurred on line %2$d.', 'cwp-snippets'),
            $cwpSyntaxResults['error_message'],
            $cwpSyntaxResults['starting_line']
        );

            // Include the error type for more detail, if available.
            if (!empty($cwpSyntaxResults['error_type'])) {
                // Translators: 1: error type, 2: error message with line number
                $errorMessage = sprintf(__('[CWP Snippets %1$s] %2$s', 'cwp-snippets'),
                    $cwpSyntaxResults['error_type'],
                    $errMessage
                );
            }

            // pass info to our debug log, IF and only IF its enabled.
            $is_log_enabled = get_option('fmcwp_enable_custom_log');
            $syntax_timestamp = current_time('mysql');
            
            if ( $is_log_enabled ) {
                $r = fmcwp_log_syntax_error( $cwpSyntaxResults, $errorMessage, $snippet_name, $snippet_id, $syntax_timestamp );
                // if $r = false / 0, something is messed up with database
                if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
                    error_log( "There was an error logging this syntax error to the database.  Please check your MySQL structure to ensure the CWP Snippets Log table is correct" );
                }
            }

            return ['error' => true, 'type' => $cwpSyntaxResults['err'], 'error_message' => $cwpSyntaxResults['error_message'], 'error_type' => $cwpSyntaxResults['error_type'], 'starting_line' => $cwpSyntaxResults['starting_line'], 'message' => $errMessage ];

    } else {
    
        // no code was submitted to begin with.  cant fix what doesnt exist.
        return ['error' => false, 'message' => ''];
    }
    
}

// *********************************************************************************************************************************
// CWP Syntax Checker
//   - FOR: PHP ONLY
//   - @str $userCode User submitted snippet/code to check.

function cwp_snippets_check_php_syntax($userCode) {

	// Initialize our inherant php parsing
    $parser = (new ParserFactory)->createForHostVersion();

    // init our parsing results as an array
    $cwpSyntaxResults = [];

    // populate our return with default values
    $cwpSyntaxResults['error']         = false;
	$cwpSyntaxResults['error_type']    = '';
	$cwpSyntaxResults['error_message'] = '';
    $cwpSyntaxResults['starting_line'] = '';
    $cwpSyntaxResults['err']           = 'warning';

    // Prepare the code for parsing ---
	// we'll go ahead and truncate white space/wrap in a php tag if not already wrapped for testing and safety
    $tempCode = ltrim($userCode);
    $userCode = (strpos($tempCode, '<?php') === 0) ? $tempCode : '<?php ' . $tempCode;

    try {

        $ast = $parser->parse($userCode); // run through our parser
        


    } catch (Error $e) { // Catches PHP syntax errors found by the parser

        $cwpSyntaxResults['error']         = true;
		$cwpSyntaxResults['error_type']    = 'Syntax Error';
        $cwpSyntaxResults['error_message'] = $e->getMessage();
        $cwpSyntaxResults['starting_line'] = $e->getStartLine();
        $cwpSyntaxResults['err']          = 'warning';

    } catch (Throwable $e) { // Catches any other unexpected runtime errors during parsing

        $cwpSyntaxResults['error']         = true;
		$cwpSyntaxResults['error_type']    = 'Runtime / Fatal Error';		
        $cwpSyntaxResults['error_message'] = 'An unexpected error occurred during parsing: ' . $e->getMessage();
        $cwpSyntaxResults['starting_line'] = $e->getLine(); // on throwable errors GetLine method pulls the problematic line vs. getStartLine
        $cwpSyntaxResults['err']          = 'fatal';

    }

    return $cwpSyntaxResults;
}


// *********************************************************************************************************************************
// Show Formatted Response

function fmcwpShowResponse($response) {

    echo'
    <div style="max-width:850px; margin: 10px; overflow: hidden; padding: 10px; border-radius: 5px; background-color: black; color: white; line-height: 1.1;">
    <pre><code style="font-family: sans-serif; background-color: black; color: white; font-size: 12px;">';
    // Escape the printed response to avoid raw output and development-function warnings.
    echo esc_html( print_r( $response, true ) );
    echo '</code></pre>
    </div>
    
    ';
    
    }

 // *********************************************************************************************************************************
 /**
 * Logs a syntax error to the custom CWP error database table.
 *
 * @param array  $cwpSyntaxResults The array containing detailed syntax error information.
 * @param string $errorMessage     A general error message (optional, as details are in $cwpSyntaxResults).
 * @param string $snippet_name     The name of the snippet where the error occurred.
 * @param int    $snippet_id       The ID of the snippet.
 * @param string $syntax_timestamp The timestamp of the error.
 */
function fmcwp_log_syntax_error($cwpSyntaxResults, $errorMessage, $snippet_name, $snippet_id, $syntax_timestamp) {
    global $wpdb;

    // Set the table name with the WordPress database prefix.
    $table_name = $wpdb->prefix . 'cwp_error_log';

    // Extract relevant data from the syntax results array.
    $error_issue_type = isset($cwpSyntaxResults['error_type']) ? sanitize_text_field($cwpSyntaxResults['error_type']) : 'Unknown Error';
    $error_message    = isset($cwpSyntaxResults['error_message']) ? sanitize_textarea_field($cwpSyntaxResults['error_message']) : 'No message available.';
    $error_line       = isset($cwpSyntaxResults['starting_line']) ? absint($cwpSyntaxResults['starting_line']) : 0;
    
    // Prepare the data to be inserted into the database.
    $data = array(
        'timestamp'      => $syntax_timestamp,
        'error_issue'    => $error_issue_type,
        'snippet_name'   => sanitize_text_field($snippet_name),
        'snippet_id'     => absint($snippet_id),
        'error'          => $error_message,
        'error_line'     => $error_line,
    );

    // Prepare the format of the data for security.
    $format = array(
        '%s', // timestamp
        '%s', // error_issue
        '%s', // snippet_name
        '%d', // snippet_id
        '%s', // error
        '%d', // error_line
    );

    // Insert the data into the database table.
    $r = $wpdb->insert($table_name, $data, $format);
    
    return $r === 1;
}

/**
 * Adds a log entry to the CWP Snippets custom log table.
 *
 * @param string $error_issue Short description of the issue.
 * @param string $snippet_name Name of the snippet (optional).
 * @param int $snippet_id ID of the snippet (optional).
 * @param string $error Full error message or details.
 * @param int $error_line Line number (optional).
 */
function cwp_snippets_add_custom_log_entry($error_issue, $snippet_name = '', $snippet_id = 0, $error = '', $error_line = 0) {
    global $wpdb;
    $table_name = $wpdb->prefix . 'cwp_error_log';
    $wpdb->insert(
        $table_name,
        array(
            'timestamp'    => current_time('mysql'),
            'error_issue'  => $error_issue,
            'snippet_name' => $snippet_name,
            'snippet_id'   => intval($snippet_id),
            'error'        => $error,
            'error_line'   => intval($error_line)
        ),
        array(
            '%s', '%s', '%s', '%d', '%s', '%d'
        )
    );
}

/**
 * Conditionally log to custom log or WP error log.
 * Usage: cwp_snippets_conditional_log($error_issue, $snippet_name, $snippet_id, $error, $error_line);
 */
function cwp_snippets_conditional_log($error_issue, $snippet_name = '', $snippet_id = 0, $error = '', $error_line = 0) {
    $custom_log_enabled = get_option('fmcwp_enable_custom_log');
    $is_pro = function_exists('cwp_is_pro_active') && cwp_is_pro_active();
    if ($is_pro && $custom_log_enabled) {
        cwp_snippets_add_custom_log_entry($error_issue, $snippet_name, $snippet_id, $error, $error_line);
    } else {
        $msg = "CWP Snippets: $error_issue";
        if ($snippet_name) {
            $msg .= " in Snippet '$snippet_name'";
        }
        if ($snippet_id) {
            $msg .= " (ID: $snippet_id)";
        }
        if ($error_line) {
            $msg .= ", Line $error_line";
        }
        if ($error) {
            $msg .= ": $error";
        }
        error_log($msg);
    }
}