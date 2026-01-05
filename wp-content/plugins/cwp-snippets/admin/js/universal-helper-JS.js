// --- CWP Snippets Admin Globals
// --- JavaScript for Floating Admin Toggle Options Notifications  */

function cwp_show_floating_notification(message, isSuccess) {
    isSuccess = isSuccess !== false; // Default to true
    var bgColor = isSuccess ? '#007cba' : '#a61c3d'; // Blue for success, maroon for error

    // Check if a .cwp-floating-notification div already exists
    var $notification = jQuery('.cwp-floating-notification');
    if ($notification.length === 0) {
        $notification = jQuery('<div class="cwp-floating-notification"></div>');
        jQuery('body').append($notification);
    }
    $notification.text(message);
    $notification.css('background-color', bgColor);
    $notification.show();

    setTimeout(function() {
        $notification.fadeOut(300, function() { jQuery(this).remove(); });
    }, 3000);
}

// =================================================================================
// Universal JS Helper Functions for CWP Snippets
// =================================================================================

/**
 * Show an element by ID or class
 * @param {string} selector - CSS selector (ID or class)
 */
function cwpShowElement(selector) {
    var el = document.querySelector(selector);
    if (el) el.style.display = '';
}

/**
 * Hide an element by ID or class
 * @param {string} selector - CSS selector (ID or class)
 */
function cwpHideElement(selector) {
    var el = document.querySelector(selector);
    if (el) el.style.display = 'none';
}

/**
 * Copy text to clipboard
 * @param {string} text - Text to copy
 */
function cwpCopyToClipboard(text) {
    if (navigator.clipboard) {
        navigator.clipboard.writeText(text);
    } else {
        var temp = document.createElement('textarea');
        temp.value = text;
        document.body.appendChild(temp);
        temp.select();
        document.execCommand('copy');
        document.body.removeChild(temp);
    }
}

/**
 * Show a simple notification/toast
 * @param {string} message - Message to display
 * @param {number} duration - Duration in ms (default: 2000)
 * @param {string} bgColor - Optional background color
 * @param {string} textColor - Optional text color
 */
function cwpShowToast(message, duration, bgColor, textColor) {
    duration = duration || 2000;
    var toast = document.createElement('div');
    toast.textContent = message;
    toast.className = 'cwp-toast-notification';
    toast.style.position = 'fixed';
    toast.style.bottom = '30px';
    toast.style.left = '50%';
    toast.style.transform = 'translateX(-50%)';
    toast.style.background = bgColor || '#222';
    toast.style.color = textColor || '#fff';
    toast.style.padding = '10px 20px';
    toast.style.borderRadius = '6px';
    toast.style.zIndex = 9999;
    toast.style.fontSize = '16px';
    document.body.appendChild(toast);
    setTimeout(function() {
        document.body.removeChild(toast);
    }, duration);
}

/**
 * Toggle visibility of an element by selector
 * @param {string} selector - CSS selector (ID or class)
 * Usage: cwpToggleElement('#myDiv');
 */
function cwpToggleElement(selector) {
    var el = document.querySelector(selector);
    if (el) {
        el.style.display = (el.style.display === 'none' ? '' : 'none');
    }
}

/**
 * Smoothly scroll to an element on the page
 * @param {string} selector - CSS selector (ID or class)
 * Usage: cwpScrollTo('#section2');
 */
function cwpScrollTo(selector) {
    var el = document.querySelector(selector);
    if (el) {
        el.scrollIntoView({ behavior: 'smooth', block: 'start' });
    }
}

/**
 * Get a URL query parameter value
 * @param {string} param - The parameter name
 * @returns {string|null} The value or null if not found
 * Usage: cwpGetQueryParam('id');
 */
function cwpGetQueryParam(param) {
    var urlParams = new URLSearchParams(window.location.search);
    return urlParams.get(param);
}

/**
 * Returns true if the user is on a mobile device
 * Usage: if (cwpIsMobile()) { ... }
 */
function cwpIsMobile() {
    return /Mobi|Android|iPhone|iPad|iPod|BlackBerry|IEMobile|Opera Mini/i.test(navigator.userAgent);
}

/**
 * Show a confirmation dialog and run a callback if confirmed
 * @param {string} message - The confirmation message
 * @param {function} callback - Function to run if confirmed
 * Usage: cwpConfirm('Are you sure?', function() { ... });
 */
function cwpConfirm(message, callback) {
    if (window.confirm(message)) {
        if (typeof callback === 'function') {
            callback();
        }
    }
}

/**
 * Copy the text content of an element to clipboard by selector
 * @param {string} selector - CSS selector (ID or class)
 * Usage: cwpSetClipboardFromSelector('#myInput');
 */
function cwpSetClipboardFromSelector(selector) {
    var el = document.querySelector(selector);
    if (el) {
        cwpCopyToClipboard(el.value || el.textContent || '');
    }
}

/**
 * Show a universal spinner overlay (pinwheel style)
 * @param {Object} options - Optional settings: { message, target, size, color }
 * Usage: cwpShowSpinner();
 *        cwpShowSpinner({ message: 'Loading...', size: 60, color: '#ff6600' });
 */
function cwpShowSpinner(options) {
    options = options || {};
    var overlayId = 'cwp-spinner-overlay';
    var spinnerId = 'cwp-spinner';
    var existing = document.getElementById(overlayId);
    if (existing) {
        existing.classList.remove('cwp-spinner-hidden');
        return;
    }
    var overlay = document.createElement('div');
    overlay.id = overlayId;
    overlay.className = 'cwp-spinner-overlay';
    if (options.target) {
        var target = document.querySelector(options.target);
        if (target) {
            overlay.style.position = 'absolute';
            overlay.style.width = target.offsetWidth + 'px';
            overlay.style.height = target.offsetHeight + 'px';
            overlay.style.top = target.offsetTop + 'px';
            overlay.style.left = target.offsetLeft + 'px';
            target.style.position = 'relative';
            target.appendChild(overlay);
        } else {
            document.body.appendChild(overlay);
        }
    } else {
        document.body.appendChild(overlay);
    }
    var spinner = document.createElement('div');
    spinner.id = spinnerId;
    spinner.className = 'cwp-spinner';
    if (options.size) {
        spinner.style.width = spinner.style.height = options.size + 'px';
        spinner.style.borderWidth = (options.size/10) + 'px';
    }
    if (options.color) {
        spinner.style.borderTopColor = options.color;
    }
    overlay.appendChild(spinner);
    if (options.message) {
        var msg = document.createElement('div');
        msg.textContent = options.message;
        msg.style.marginTop = '16px';
        msg.style.color = '#222';
        msg.style.fontSize = '16px';
        msg.style.textAlign = 'center';
        overlay.appendChild(msg);
    }
}

/**
 * Hide the universal spinner overlay
 * Usage: cwpHideSpinner();
 */
function cwpHideSpinner() {
    var overlay = document.getElementById('cwp-spinner-overlay');
    if (overlay) {
        overlay.classList.add('cwp-spinner-hidden');
        setTimeout(function() {
            if (overlay.parentNode) overlay.parentNode.removeChild(overlay);
        }, 300);
    }
}

// -----------------------------------------------------------------------------
// Usage Examples for Spinner Helpers
// -----------------------------------------------------------------------------
// Show default spinner overlay:
//   cwpShowSpinner();
//
// Show spinner with custom message, size, and color:
//   cwpShowSpinner({ message: 'Saving...', size: 60, color: '#ff6600' });
//
// Hide spinner overlay:
//   cwpHideSpinner();
