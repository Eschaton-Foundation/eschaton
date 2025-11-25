/**
 * CWP Snippets - Admin Edit/New Snippet JS
 */
jQuery(document).ready(function($) {

    // Loads only the editor history from localStorage
    function loadEditorState(editor, key) {
        var snippetId = $('#snippet_id').val() || 'new';
        var history = localStorage.getItem(snippetId + '_' + key + '_History');

        if (history) {
            try {
                editor.codemirror.setHistory(JSON.parse(history));
            } catch (e) {
                console.error('Error parsing editor history from localStorage:', e);
                localStorage.removeItem(snippetId + '_' + key + '_History');
            }
        }
    }

    // Saves only the editor history to localStorage, with a cap
    function saveEditorState(editor, key) {
        var snippetId = $('#snippet_id').val() || 'new';
        try {
            const historyCap = 25;
            let history = editor.codemirror.getHistory();

            if (history.done.length > historyCap) {
                // Keep only the most recent 'historyCap' changes
                history.done = history.done.slice(history.done.length - historyCap);
                // The redo stack is now invalid, so clear it
                history.undone = [];
            }

            localStorage.setItem(snippetId + '_' + key + '_History', JSON.stringify(history));
        } catch (e) {
            console.error('Error saving editor history to localStorage:', e);
        }
    }

    // Renames localStorage keys from 'new' to the actual snippet ID after creation
    function migrateLocalStorage(oldId, newId) {
        const keysToMigrate = ['codeEditor_History', 'cssEditor_History'];

        keysToMigrate.forEach(keySuffix => {
            const oldKey = oldId + '_' + keySuffix;
            const newKey = newId + '_' + keySuffix;
            const data = localStorage.getItem(oldKey);

            if (data !== null) {
                localStorage.setItem(newKey, data);
                localStorage.removeItem(oldKey);
            }
        });
    }

    // Check for localStorage Migration Trigger on Page Load
    const urlParams = new URLSearchParams(window.location.search);
    const migrateFromId = urlParams.get('migrate_ls');
    const currentSnippetId = $('#snippet_id').val();

    if (migrateFromId && currentSnippetId) {
        migrateLocalStorage(migrateFromId, currentSnippetId);
    }

    // --- Configuration & Initialization ---
    var codeEditorInstance = null;
    var cssEditorInstance = null;
    var submitButtons = $('#data-form input[type="submit"]');
    var nameField = $('#name_field');
    var typeSelector = $('#type_selector');
    var locationSelector = $('#location_selector');
    var priorityField = $('#priority_field');
    var descriptionTextarea = $('#description_textarea'); // Selector for description field
    var errorMessageDiv = $('#error_message');
    var snippetIdInput = $('#snippet_id');
    var typeHiddenInput = $('#type_hidden');
    var statusToggleButtons = $('.status-toggle-button');
    var statusHiddenInput = $('#snippet_status_hidden');
    var statusHiddenInput2 = $('#snippet_status_hidden-2');
    var statusLabel = $('#status-toggle-label');
    var statusLabel2 = $('#status-toggle-label-2');

    // Store initial form values
    var initialName = nameField.val();
    var initialType = typeSelector.is(':visible') ? typeSelector.val() : typeHiddenInput.val();
    var initialLocation = locationSelector.val();
    var initialPriority = priorityField.val();
    var initialDescription = descriptionTextarea.val();
    var initialStatus = statusHiddenInput.val();
    var initialCode = ''; // Will be set after editor initialization
    var initialCss = ''; // Will be set after editor initialization

    // --- check for failed update
    const queryString = window.location.search;
    const fatalParams = new URLSearchParams(queryString);
    var fatalNotice = fatalParams.get('fmcwp_notice');

    // --- Form Change Detection ---
    function checkFormChanges() {
        var currentName = nameField.val();
        var currentType = typeSelector.is(':visible') ? typeSelector.val() : typeHiddenInput.val();
        var currentLocation = locationSelector.val();
        var currentPriority = priorityField.val();
        var currentDescription = descriptionTextarea.val();
        var currentStatus = statusHiddenInput.val();
        var currentCode = codeEditorInstance ? codeEditorInstance.codemirror.getValue() : '';
        var currentCss = cssEditorInstance ? cssEditorInstance.codemirror.getValue() : '';

        return (
            currentName !== initialName ||
            currentType !== initialType ||
            currentLocation !== initialLocation ||
            currentPriority !== initialPriority ||
            currentDescription !== initialDescription ||
            currentStatus !== initialStatus ||
            (codeEditorInstance && currentCode !== initialCode) ||
            (cssEditorInstance && currentCss !== initialCss)
        );
    }

    // --- Submit Button State Management ---
    function updateSubmitButtonState() {
        var hasChanges = checkFormChanges();
        var hasError = errorMessageDiv.is(':visible') && errorMessageDiv.text() !== '';
        var isNameEmpty = nameField.val().trim().length === 0;

        if (hasError || isNameEmpty || !hasChanges) {
            submitButtons.prop('disabled', true);
        } else {
            submitButtons.prop('disabled', false);
        }

        // overwrite if there was a fatal error.
        if (fatalNotice == "fatal_update_error") {
            submitButtons.prop('disabled', false);    
        }
        
    }


    // Updates status display (icon, label) and hidden inputs in the form
    function updateStatusDisplay(newStatus) {
        var isActive = (newStatus == 1);
        var iconClass = isActive ? 'fa-toggle-on' : 'fa-toggle-off';
        var iconColor = isActive ? 'green' : '#bbb';
        var labelText = isActive ? 'Active' : 'Inactive';
        var labelStyle = isActive ? '' : 'color: #bbb; font-style: italic;';

        statusHiddenInput.val(newStatus);
        statusHiddenInput2.val(newStatus);

        $('#data-form .status-toggle-button').find('i').removeClass('fa-toggle-on fa-toggle-off').addClass(iconClass).css('color', iconColor);
        statusLabel.text(labelText).attr('style', labelStyle);
        statusLabel2.text(labelText).attr('style', labelStyle);
    }

    // Initializes a CodeMirror editor instance
    function initializeCodeMirror(textareaId, mode) {
        var editor = wp.codeEditor.initialize(textareaId, {
            ...cm_settings.codeEditor,
            mode: mode
        });

        editor.codemirror.on('keydown', function(cm, event) {
            // Handle Ctrl+S / Cmd+S for saving
            if ((event.ctrlKey || event.metaKey) && event.key === 's') {
                // Prevent the browser's default save action
                event.preventDefault();
                // Trigger the form submission
                $('#data-form').submit();
            }
        });

        editor.codemirror.on('change', function() {
            editor.codemirror.save(); // Keep textarea updated
            updateSubmitButtonState(); // Update button state on editor change
        });

        return editor;
    }

    // Initialize Code Editor & Load History
    if ($('#code_editor_textarea').length) {
        codeEditorInstance = initializeCodeMirror('code_editor_textarea', 'text/x-php');
        if (codeEditorInstance) {
            loadEditorState(codeEditorInstance, 'codeEditor');
            initialCode = codeEditorInstance.codemirror.getValue(); // Store initial code
        }
    }

    // Initialize CSS Editor & Load History
    if ($('#css_editor_textarea').length) {
        cssEditorInstance = initializeCodeMirror('css_editor_textarea', 'text/css');
        if (cssEditorInstance) {
            loadEditorState(cssEditorInstance, 'cssEditor');
            initialCss = cssEditorInstance.codemirror.getValue(); // Store initial css
        }
    }

    // --- Editor Switching Logic ---
    $('#show-code-editor').on('click', function() {
        $('#css-editor-container').css({ 'visibility': 'hidden', 'position': 'absolute' });
        $('#code-editor-container').css({ 'visibility': 'visible', 'position': 'relative' });
        $('#show-css-editor').removeClass('button-primary');
        $(this).addClass('button-primary');
        $('#active_editor').val('code');
        if (codeEditorInstance) codeEditorInstance.codemirror.refresh();
    });

    $('#show-css-editor').on('click', function() {
        $('#code-editor-container').css({ 'visibility': 'hidden', 'position': 'absolute' });
        $('#css-editor-container').css({ 'visibility': 'visible', 'position': 'relative' });
        $('#show-code-editor').removeClass('button-primary');
        $(this).addClass('button-primary');
        $('#active_editor').val('css');
        if (cssEditorInstance) cssEditorInstance.codemirror.refresh();
    });

    // Set initial editor visibility based on PHP variable
    if (typeof cwpEditData !== 'undefined' && cwpEditData.activeEditor === 'css') {
        $('#show-css-editor').trigger('click');
    } else {
        $('#show-code-editor').trigger('click');
    }

    // --- Name & Type Uniqueness Check ---
    var uniquenessCheckTimeout;

    // Performs the AJAX uniqueness check
    function performUniquenessCheck() {
        var name = nameField.val().trim();
        var type = typeSelector.is(':visible') ? typeSelector.val() : typeHiddenInput.val();
        var snippetId = snippetIdInput.val(); // Get the current snippet ID (will be empty if new)

        errorMessageDiv.hide().text(''); // Clear previous errors

        // Don't check if name is empty, but update button state
        if (name.length === 0) {
            updateSubmitButtonState();
            return;
        }

        // Don't check if name hasn't changed from initial (unless type changed)
        if (name === initialName && type === initialType && snippetId) {
             updateSubmitButtonState(); // Ensure button state is correct even if no check needed
             return;
        }


        if (typeof cwpEditData === 'undefined' || !cwpEditData.ajaxurl || !cwpEditData.uniquenessNonce) {
            console.error("CWP Edit Data (AJAX URL or Nonce) not found for uniqueness check.");
            errorMessageDiv.text('Error: Cannot check name uniqueness. Configuration missing.').show();
            updateSubmitButtonState(); // Update button state after showing error
            return;
        }

        // Temporarily disable button during check? Optional, but good UX.
        // submitButtons.prop('disabled', true);

        $.ajax({
            url: cwpEditData.ajaxurl,
            type: 'POST',
            data: {
                action: 'check_snippet_uniqueness',
                name: name,
                type: type,
                nonce: cwpEditData.uniquenessNonce,
                snippet_id: snippetId || 0 // Send current snippet ID, or 0 if new
            },
            success: function(response) {
                if (response.error) {
                    errorMessageDiv.text('Error: ' + response.error).show();
                } else if (response.exists) {
                    errorMessageDiv.text('Error: A ' + type + ' with this name already exists.').show();
                } else {
                    errorMessageDiv.hide().text('');
                }
            },
            error: function() {
                errorMessageDiv.text('Error checking name uniqueness.').show();
            },
            complete: function() {
                // Always update button state after check completes (success or error)
                updateSubmitButtonState();
            }
        });
    }

    // Listener for Name field input (debounced check)
    nameField.on('input', function() {
        clearTimeout(uniquenessCheckTimeout);
        updateSubmitButtonState(); // Update immediately for responsiveness
        uniquenessCheckTimeout = setTimeout(performUniquenessCheck, 500);
    });

    // Listener for Type selector change (immediate check)
    typeSelector.on('change', function() {
        clearTimeout(uniquenessCheckTimeout);
        updateSubmitButtonState(); // Update immediately
        performUniquenessCheck();
    });

    // --- Status Toggle Logic (Form only) ---
    $('#data-form .status-toggle-button').on('click', function() {
        var currentStatus = parseInt(statusHiddenInput.val(), 10);
        var newStatus = (currentStatus === 1) ? 0 : 1;
        updateStatusDisplay(newStatus);
        updateSubmitButtonState(); // Update button state after status change
    });

    // --- Enable Submit on Other Field Changes ---
    locationSelector.on('change', updateSubmitButtonState);
    priorityField.on('input', updateSubmitButtonState);
    // descriptionTextarea input handled by auto-resize function below

    // --- Back Button Logic ---
    $('#back-button, #back-button-2').on('click', function() {
        if (typeof cwpAdminData === 'undefined' || !cwpAdminData.page || !cwpAdminData.current_filter) {
             console.error("CWP Admin Data not found for Back Button.");
             window.location.href = window.location.pathname + '?page=fmcwp-snippets'; // Fallback
             return;
        }
        var listUrl = window.location.pathname + '?page=' + cwpAdminData.page + '&filter_type=' + cwpAdminData.current_filter;
        window.location.href = listUrl;
    });

    // --- New Button Logic (Clear 'new' history) ---
    $('#new-button').on('click', function() {
        try {
            localStorage.removeItem('new_codeEditor_History');
            localStorage.removeItem('new_cssEditor_History');
        } catch (e) {
            console.error("Error clearing 'new' snippet history:", e);
        }

        if (typeof cwpAdminData !== 'undefined' && cwpAdminData.page && cwpAdminData.current_filter) {
            window.location.href = window.location.pathname + '?page=' + cwpAdminData.page + '&action=new&filter_type=' + cwpAdminData.current_filter;
        } else {
            console.error("CWP Admin Data not found for New Button.");
            window.location.href = window.location.pathname + '?page=fmcwp-snippets&action=new'; // Fallback
        }
    });

    // --- Auto-Resize Description Textarea ---
    function autoResizeTextarea() {
        // Check if the element exists before trying to manipulate it
        if (!descriptionTextarea.length) return;

        descriptionTextarea.css('height', 'auto'); // Temporarily shrink
        var scrollHeight = descriptionTextarea.prop('scrollHeight');
        descriptionTextarea.css('height', scrollHeight + 2 + 'px'); // Set to content height + buffer
    }

    // Add listener for input events on description textarea
    descriptionTextarea.on('input', function() {
        autoResizeTextarea();
        updateSubmitButtonState(); // Also update submit when description changes
    });

    // Trigger resize on initial load
    setTimeout(autoResizeTextarea, 10);
    // --- End Auto-Resize ---

    // --- Save State Before Form Submission ---
    $('#data-form').on('submit', function() {
        // Re-check state just before submission as a final guard
        updateSubmitButtonState();

        // Prevent submission if submit button is disabled
        if (submitButtons.is(':disabled')) {
            // If it's disabled because of an error, ensure the error is shown
            if (errorMessageDiv.is(':visible') && errorMessageDiv.text() !== '') {
                 // Error already visible
            }
            // If it's disabled because name is empty
            else if (nameField.val().trim().length === 0) {
                 errorMessageDiv.text('Error: Snippet name cannot be empty.').show();
            }
            // If it's disabled because no changes were made
            else if (!checkFormChanges()) {
                 // Maybe show a less alarming message or just prevent submission silently
                 // console.log("Submission prevented: No changes detected.");
            }
            // Otherwise, show a generic error if somehow disabled without a clear reason
            else if (!errorMessageDiv.is(':visible') || errorMessageDiv.text() === '') {
                 errorMessageDiv.text('Cannot save. Please resolve errors or make changes.').show();
            }
            return false; // Prevent form submission
        }

        // Save history if submission is allowed
        if (codeEditorInstance) {
            saveEditorState(codeEditorInstance, 'codeEditor');
        }
        if (cssEditorInstance) {
            saveEditorState(cssEditorInstance, 'cssEditor');
        }
        return true; // Allow form submission
    });

    // --- Initial State ---
    // Disable button initially, unless we're returning from a conflict where PHP has already enabled it.
    // --- DEBUGGING ---
    console.log('Checking button state. cwpEnableFormOnLoad is:', typeof cwpEnableFormOnLoad !== 'undefined' ? cwpEnableFormOnLoad : 'undefined');
    // --- END DEBUGGING ---
    if (typeof cwpEnableFormOnLoad === 'undefined' || !cwpEnableFormOnLoad) {
        submitButtons.prop('disabled', true);
    }

    // Trigger initial uniqueness check if editing an existing snippet with a name
    if (snippetIdInput.val() && nameField.val().trim().length > 0) {
        performUniquenessCheck(); // This will call updateSubmitButtonState in its 'complete' callback
    } else {
        // For new snippets or existing snippets with empty names, just update the button state
        updateSubmitButtonState();
    }

}); // End jQuery(document).ready()
