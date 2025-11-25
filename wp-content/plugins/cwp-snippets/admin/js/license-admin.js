// admin/js/license-admin.js
(function($) {
    'use strict';

    $(function() {
        // console.log('CWP Snippets License Admin JS Loaded');

        var $activateButton = $('#cwp_snippets_activate_license');
        var $deactivateButton = $('#cwp_snippets_deactivate_license');
        var $checkButton = $('#cwp_snippets_check_license');
        var $descriptionText = $('p.description'); // More specific selector for the description paragraph
        var $licenseKeyInput = $('#cwp_snippets_license_key');
        var $statusText = $('#cwp_license_status_text');
        var $licenseKeyRow = $licenseKeyInput.closest('p'); // Get the parent <p> of the license key input
        var $licenseStatusArea = $('#cwp_license_status_area'); // For adding messages
        var initialStatusFromServer = $statusText.text().trim(); // Get initial status text from PHP render

        // Function to update the UI based on license status
        function updateLicenseUI(newStatusKey, message, isError) {
            var statusMessage = cwpLicenseAdminData.status_texts[newStatusKey] || cwpLicenseAdminData.status_texts['unknown'];
            $statusText.text(statusMessage);

            $licenseStatusArea.find('.cwp-ajax-message').remove(); // Clear previous messages
            if (message) {
                var messageClass = isError ? 'notice notice-error is-dismissible' : 'notice notice-success is-dismissible';
                // WordPress notices are typically added directly under the h1, or use a simpler p tag here.
                // For simplicity here, using a p tag. For full WP styling, you might need to adjust placement.
                $licenseStatusArea.append('<p class="cwp-ajax-message ' + (isError ? 'error-message" style="color: red;"' : 'success-message" style="color: green;"') + '">' + message + '</p>');
            }

            // Determine if the status is one where the license key input should be hidden and Deactivate/Check buttons shown
            // This includes 'active' and 'expired' based on the desired UI behavior.
            var isLicenseKeyHiddenState = (newStatusKey === 'active' || newStatusKey === 'expired');

            var licenseKeyPresent = $licenseKeyInput.val().trim().length > 0;

            // Set visibility and enabled state for buttons
            $activateButton.toggle(!isLicenseKeyHiddenState).prop('disabled', false); // Show Activate if status is NOT hidden state
            $deactivateButton.toggle(isLicenseKeyHiddenState).prop('disabled', false); // Show Deactivate if status IS hidden state
            
            // Check button: show if status is hidden state OR if a key is present (and status is not hidden state)
            var showCheckButton = isLicenseKeyHiddenState || (!isLicenseKeyHiddenState && licenseKeyPresent);
            $checkButton.toggle(showCheckButton).prop('disabled', false);

            // UI elements related to license input (description, key row)
            // Show these if status is NOT hidden state (e.g. inactive, invalid)
            var showLicenseInputElements = !isLicenseKeyHiddenState;
            $descriptionText.toggle(showLicenseInputElements);
            $licenseKeyRow.toggle(showLicenseInputElements);
            $licenseKeyInput.prop('disabled', isLicenseKeyHiddenState); // Disable input if status is hidden state

            // If active, and key input is now disabled, ensure it has the current key
            // This part might need adjustment based on how license_key is passed back from server on activation
            // For now, we assume the key input field already holds the correct key.
        }

        $activateButton.on('click', function() {
            var licenseKey = $licenseKeyInput.val().trim();

            if (!licenseKey) {
                alert('Please enter a license key.');
                return;
            }

            // Add a processing message
            $licenseStatusArea.find('.cwp-ajax-message').remove(); // Remove previous messages
            $activateButton.prop('disabled', true);
            $deactivateButton.prop('disabled', true);
            $checkButton.prop('disabled', true);
            $licenseStatusArea.append('<p class="cwp-ajax-message" style="color: blue;"><em>Processing activation...</em></p>');

            $.post(cwpLicenseAdminData.ajaxurl, {
                action: 'cwp_activate_license', // PHP AJAX action hook
                license_key: licenseKey,
                _ajax_nonce: cwpLicenseAdminData.nonce
            }, function(response) {
                $licenseStatusArea.find('.cwp-ajax-message').remove();

                if (response.success && response.data && response.data.new_status) {
                    // If server returns the license key (e.g., canonical version), update the input field
                    if (typeof response.data.license_key !== 'undefined') {
                        $licenseKeyInput.val(response.data.license_key);
                    }
                    // Hide the "Go Pro" notice immediately on successful activation
                    $('.cwp-pro-notice').slideUp(300, function() {
                        $(this).remove(); // Remove from DOM after sliding up
                    });
                    updateLicenseUI(response.data.new_status, response.data.message, false);
                } else {
                    // Attempt to find a status key that matches the initial server-rendered status text
                    var currentStatusKey = 'inactive'; // Default if no match
                    for (var key in cwpLicenseAdminData.status_texts) {
                        if (cwpLicenseAdminData.status_texts[key] === initialStatusFromServer) {
                            currentStatusKey = key;
                            break;
                        }
                    }
                    var errorMessage = response.data && response.data.message ? response.data.message : 'Activation failed. Please try again.';
                    updateLicenseUI(currentStatusKey, errorMessage, true);
                }
            }).fail(function() {
                $licenseStatusArea.find('.cwp-ajax-message').remove();
                // Attempt to find a status key that matches the initial server-rendered status text
                var currentStatusKey = 'inactive'; // Default if no match
                for (var key in cwpLicenseAdminData.status_texts) {
                    if (cwpLicenseAdminData.status_texts[key] === initialStatusFromServer) {
                        currentStatusKey = key;
                        break;
                    }
                }
                updateLicenseUI(currentStatusKey, 'An error occurred during activation. Please try again.', true);
            });
        });

        $deactivateButton.on('click', function() {
            var licenseKey = $licenseKeyInput.val().trim(); // Key should still be there, though disabled

            if (!licenseKey) {
                // This case should ideally not happen if deactivation button is only shown when active
                alert('License key is missing. Cannot deactivate.');
                return;
            }

            // Add a processing message
            $licenseStatusArea.find('.cwp-ajax-message').remove(); // Remove previous messages
            $activateButton.prop('disabled', true);
            $deactivateButton.prop('disabled', true);
            $checkButton.prop('disabled', true);
            $licenseStatusArea.append('<p class="cwp-ajax-message" style="color: blue;"><em>Processing deactivation...</em></p>');

            $.post(cwpLicenseAdminData.ajaxurl, {
                action: 'cwp_deactivate_license', // PHP AJAX action hook for deactivation
                license_key: licenseKey,
                _ajax_nonce: cwpLicenseAdminData.nonce
            }, function(response) {
                $licenseStatusArea.find('.cwp-ajax-message').remove();

                if (response.success) {
                    // Server reported success. Assume deactivation happened.
                    $licenseKeyInput.val(''); // Clear the input field on successful deactivation
                    var newStatus = (response.data && response.data.new_status) ? response.data.new_status : 'inactive'; // Use server status if available, else assume inactive
                    var messageToShow = (response.data && response.data.message) ? response.data.message : (cwpLicenseAdminData.status_texts[newStatus] || 'License deactivated.');
                    updateLicenseUI(newStatus, messageToShow, false); // Not an error from server perspective
                } else {
                    // Server reported failure.
                    var errorMessage = (response.data && response.data.message) ? response.data.message : 'Deactivation failed. Please try again.';
                    updateLicenseUI('active', errorMessage, true); // Show error, keep status as active
                }
            }).fail(function() {
                $licenseStatusArea.find('.cwp-ajax-message').remove();
                // On AJAX failure, assume license is still active (or previous state)
                updateLicenseUI('active', 'An error occurred during deactivation. Please try again.', true);
            });
        });

        $checkButton.on('click', function() {
            var licenseKey = $licenseKeyInput.val().trim();

            // If the license is active, the key input is disabled but should contain the key.
            // If not active and key input is enabled, a key should be present.
            if (!licenseKey && !$licenseKeyInput.prop('disabled')) {
                alert('Please enter a license key to check.');
                return;
            }

            $licenseStatusArea.find('.cwp-ajax-message').remove(); // Clear previous messages
            $activateButton.prop('disabled', true);
            $deactivateButton.prop('disabled', true);
            $checkButton.prop('disabled', true);
            $licenseStatusArea.append('<p class="cwp-ajax-message" style="color: blue;"><em>Verifying license status...</em></p>');

            $.post(cwpLicenseAdminData.ajaxurl, {
                action: 'cwp_check_license', // PHP AJAX action hook for checking status
                license_key: licenseKey,
                _ajax_nonce: cwpLicenseAdminData.nonce
            }, function(response) {
                $licenseStatusArea.find('.cwp-ajax-message').remove();

                if (response.success && response.data && response.data.new_status) {
                    updateLicenseUI(response.data.new_status, response.data.message || 'License status verified.', false);
                } else {
                    // Attempt to find a status key that matches the current status text
                    var currentStatusKey = 'unknown'; // Default if no match
                    for (var key in cwpLicenseAdminData.status_texts) {
                        if (cwpLicenseAdminData.status_texts[key] === $statusText.text().trim()) {
                            currentStatusKey = key;
                            break;
                        }
                    }
                    var errorMessage = response.data && response.data.message ? response.data.message : 'Verification failed. Please try again.';
                    updateLicenseUI(currentStatusKey, errorMessage, true);
                }
            }).fail(function() {
                $licenseStatusArea.find('.cwp-ajax-message').remove();
                var currentStatusKey = 'unknown'; // Default on AJAX error
                 for (var key in cwpLicenseAdminData.status_texts) {
                    if (cwpLicenseAdminData.status_texts[key] === $statusText.text().trim()) {
                        currentStatusKey = key;
                        break;
                    }
                }
                updateLicenseUI(currentStatusKey, 'An error occurred during verification. Please try again.', true);
            });
        });

        // Initial UI setup based on PHP-rendered status
        // Find the key for the initial status text to correctly set button visibility
        var initialKey = 'inactive'; // Default
        for (var key in cwpLicenseAdminData.status_texts) {
            if (cwpLicenseAdminData.status_texts[key] === initialStatusFromServer) {
                initialKey = key;
                break;
            }
        }
        updateLicenseUI(initialKey, null, false); // Call to set up initial UI state

        // Also, ensure check button visibility is correct on page load based on key input
        $licenseKeyInput.on('input keyup', function() {
            // This is a simplified update; ideally, call updateLicenseUI or a part of it
            $checkButton.toggle( ($statusText.text().trim() === cwpLicenseAdminData.status_texts['active']) || $(this).val().trim().length > 0);
        }).trigger('keyup'); // Trigger to set initial state based on pre-filled key
    });

})(jQuery);
