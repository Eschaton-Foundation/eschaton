

// Confirm Snippet Deletion
function confirmSnippetDelete() {
    return confirm("Are you sure you want to delete this item?");
}

// Confirm Bulk Action Deletion
document.addEventListener('DOMContentLoaded', function() {
    const bulkForm = document.getElementById('bulk-action-form');
    if (bulkForm) {
        bulkForm.onsubmit = function() {
            const actionSelector = document.getElementById('bulk-action-selector');
            if (actionSelector && actionSelector.value === 'delete') {
                return confirm("Are you sure you want to delete the selected items?");
            }
            return true; // Allow form submission for other actions
        };
    }
});


document.addEventListener('DOMContentLoaded', function() {

    // Bulk select all functionality
    const selectAllCheckbox = document.getElementById('select_all');
    const bulkCheckboxes = document.querySelectorAll('.bulk-select');
    const bulkActionIdsInput = document.getElementById('bulk_action_ids');

    if (selectAllCheckbox) {
        selectAllCheckbox.addEventListener('click', function(event) {
            const isChecked = event.target.checked;
            bulkCheckboxes.forEach(function(checkbox) {
                checkbox.checked = isChecked;
            });
            updateBulkActionIds();
        });
    }

    bulkCheckboxes.forEach(function(checkbox) {
        checkbox.addEventListener('change', function() {
            updateBulkActionIds();
            // Uncheck "select all" if any individual box is unchecked
            if (!this.checked && selectAllCheckbox) {
                selectAllCheckbox.checked = false;
            }
            // Check "select all" if all individual boxes are checked
            else if (selectAllCheckbox) {
                 const allChecked = Array.from(bulkCheckboxes).every(cb => cb.checked);
                 selectAllCheckbox.checked = allChecked;
            }
        });
    });

    function updateBulkActionIds() {
        if (bulkActionIdsInput) {
            const selectedIds = Array.from(document.querySelectorAll('.bulk-select:checked')).map(cb => cb.value);
            bulkActionIdsInput.value = selectedIds.join(',');
        }
    }

    // Handle new button click
    const newButton = document.getElementById('new-button');
    // Use localized data (check if cwpAdminData exists)
    if (newButton && typeof cwpAdminData !== 'undefined') {
        newButton.addEventListener('click', function() {
            // Use template literal and localized data
            window.location.href = `?page=${cwpAdminData.page}&action=new&filter_type=${cwpAdminData.current_filter}`;
        });
    } else if (newButton) {
        console.error("CWP Admin Data not found for New Button."); // Add error handling
    }

    // Handle back button click (Top)
    const backButton = document.getElementById('back-button');
    // Use localized data (check if cwpAdminData exists)
    if (backButton && typeof cwpAdminData !== 'undefined') {
        backButton.addEventListener('click', function() {
            // Use template literal and localized data
            window.location.href = `?page=${cwpAdminData.page}&filter_type=${cwpAdminData.current_filter}`;
        });
    } else if (backButton) {
        console.error("CWP Admin Data not found for Back Button."); // Add error handling
    }

    // Handle back button click (Bottom)
    const backButton2 = document.getElementById('back-button-2');
    // Use localized data (check if cwpAdminData exists)
    if (backButton2 && typeof cwpAdminData !== 'undefined') {
        backButton2.addEventListener('click', function() {
            // Use template literal and localized data
            window.location.href = `?page=${cwpAdminData.page}&filter_type=${cwpAdminData.current_filter}`;
        });
    } else if (backButton2) {
        console.error("CWP Admin Data not found for Back Button 2."); // Add error handling
    }

    // Disable status toggle button form submission
    const statusToggleButtonsInList = document.querySelectorAll('.status-toggle-button:not(#data-form .status-toggle-button)');
    statusToggleButtonsInList.forEach(function(button) {
        button.addEventListener('click', function(event) {
            // Check if the button is inside the main data form - safety check
            if (this.closest('#data-form')) {
                return; // Do nothing if it's inside the edit form
            }

            event.preventDefault(); // Prevent default only for list toggles now
            const form = this.closest('form');
            if (form) {
                form.submit(); // Submit the mini-form in the list view
            }
        });
    });

}); // End DOMContentLoaded
