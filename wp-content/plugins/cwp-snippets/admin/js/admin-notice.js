jQuery(document).ready(function($) {
    // Use event delegation for potentially dynamically added notices
    $(document).on('click', '.cwp-pro-notice .notice-dismiss', function(event) {
        event.preventDefault();

        // Check if localized data is available
        if (typeof cwpNoticeData === 'undefined' || !cwpNoticeData.ajaxurl || !cwpNoticeData.nonce) {
            console.error('CWP Snippets Error: Notice dismissal data not found.');
            // Optionally, just hide the notice locally if AJAX isn't possible
            $(this).closest('.notice').fadeOut('slow');
            return;
        }

        // Perform the AJAX request
        $.post(cwpNoticeData.ajaxurl, {
            action: 'cwp_dismiss_pro_notice', // Matches the PHP AJAX hook
            _ajax_nonce: cwpNoticeData.nonce // Use the localized nonce
        });

        // Fade out the notice regardless of AJAX success for immediate feedback
        $(this).closest('.notice').fadeOut('slow');
    });
});
