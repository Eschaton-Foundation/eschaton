document.addEventListener('DOMContentLoaded', function() {
    const importForm = document.getElementById('import-snippets-form');
    const fileInput = document.getElementById('snippets_import_file');

    // Dialog elements
    const dialogOverlay = document.getElementById('cwp-import-dialog-overlay');
    const importList = document.getElementById('cwp-import-conflict-list'); // Reusing this list element
    const btnConfirm = document.getElementById('cwp-import-action-confirm');
    const btnCancel = document.getElementById('cwp-import-action-cancel');

    if (!importForm || !fileInput || !dialogOverlay || !importList || !btnConfirm || !btnCancel) {
        return;
    }

    // We control the form submission manually
    importForm.addEventListener('submit', function(event) {
        if (!fileInput.dataset.readyToSubmit) {
            event.preventDefault();
        }
    });

    // --- FILE INPUT TRIGGER ---
    fileInput.addEventListener('change', function(event) {
        event.preventDefault();
        if (this.files.length === 0) {
            return;
        }

        const file = this.files[0];
        const reader = new FileReader();

        reader.onload = function(e) {
            let allSnippets;
            try {
                allSnippets = JSON.parse(e.target.result);
                if (!Array.isArray(allSnippets)) {
                    throw new Error('Import file is not a valid JSON array.');
                }
            } catch (error) {
                alert('Error reading import file: ' + error.message);
                fileInput.value = '';
                return;
            }
            triggerImportProcess(allSnippets, null);
        };
        reader.readAsText(file);
    });

    // --- BUNDLED UPDATE TRIGGER ---
    document.querySelectorAll('.cwp-update-bundled-btn').forEach(button => {
        button.addEventListener('click', function() {
            const type = this.dataset.type;
            const nonce = importForm.querySelector('input[name="import_snippets_nonce"]').value;

            const fetchData = new FormData();
            fetchData.append('action', 'fmcwp_fetch_bundled_snippets');
            fetchData.append('nonce', nonce);
            fetchData.append('type', type);

            fetch(ajaxurl, { method: 'POST', body: fetchData })
            .then(response => response.json())
            .then(data => {
                if (data.success && data.data.snippets) {
                    triggerImportProcess(data.data.snippets, type);
                } else {
                    alert('Error fetching bundled snippets: ' + (data.data.message || 'Unknown error.'));
                }
            }).catch(error => {
                alert('An error occurred while fetching bundled snippets.');
            });
        });
    });

    // --- BUNDLED UPDATE ALL TRIGGER ---
    const updateAllBtn = document.getElementById('cwp-update-all-bundled-btn');
    if (updateAllBtn) {
        updateAllBtn.addEventListener('click', function() {
            const nonce = importForm.querySelector('input[name="import_snippets_nonce"]').value;

            const fetchData = new FormData();
            fetchData.append('action', 'fmcwp_fetch_all_bundled_updates');
            fetchData.append('nonce', nonce);

            fetch(ajaxurl, { method: 'POST', body: fetchData })
            .then(response => response.json())
            .then(data => {
                if (data.success && data.data.snippets) {
                    triggerImportProcess(data.data.snippets, 'all');
                } else {
                    alert('Error fetching bundled snippets: ' + (data.data.message || 'Unknown error.'));
                }
            }).catch(error => {
                alert('An error occurred while fetching all bundled snippets.');
            });
        });
    }

    // --- BUNDLED RELOAD TRIGGER (for Samples/Templates pages) ---
    document.querySelectorAll('.cwp-reload-bundled-btn').forEach(button => {
        button.addEventListener('click', function() {
            const type = this.dataset.type;
            const nonce = importForm.querySelector('input[name="import_snippets_nonce"]').value;

            const fetchData = new FormData();
            fetchData.append('action', 'fmcwp_fetch_bundled_snippets');
            fetchData.append('nonce', nonce);
            fetchData.append('type', type);

            fetch(ajaxurl, { method: 'POST', body: fetchData })
            .then(response => response.json())
            .then(data => {
                if (data.success && data.data.snippets) {
                    // Pass the type, and a flag indicating this is a reload, not an update.
                    triggerImportProcess(data.data.snippets, type, true);
                } else {
                    alert('Error fetching bundled snippets: ' + (data.data.message || 'Unknown error.'));
                }
            }).catch(error => {
                alert('An error occurred while fetching bundled snippets.');
            });
        });
    });

    // --- CORE IMPORT LOGIC ---
    function triggerImportProcess(allSnippets, bundledType = null, isReload = false) {
        const snippetNames = allSnippets.map(snippet => snippet.name).filter(name => name);
        if (snippetNames.length === 0) {
            alert('No snippets with names found in the import file.');
            fileInput.value = '';
            return;
        }

        const nonce = importForm.querySelector('input[name="import_snippets_nonce"]').value;

        const checkData = new FormData();
        checkData.append('action', 'fmcwp_check_import_conflicts');
        checkData.append('nonce', nonce);

        allSnippets.forEach((snippet, index) => {
            checkData.append(`snippets[${index}][name]`, snippet.name);
            checkData.append(`snippets[${index}][type]`, snippet.type || 'Snippet');
        });

        fetch(ajaxurl, { method: 'POST', body: checkData })
        .then(response => response.json())
        .then(data => {
            if (!data.success) {
                alert('Error checking for conflicts: ' + (data.data.message || 'Unknown error.'));
                fileInput.value = '';
                return;
            }
            
            showImportDialog(allSnippets, data.data.conflicts || [], bundledType, isReload);
        }).catch(error => {
            alert('An error occurred while checking for conflicts.');
            fileInput.value = '';
        });
    }

    function showImportDialog(allSnippets, conflictingNames, bundledType = null, isReload = false) {
        // Populate the list of all snippets
        importList.innerHTML = '';
        allSnippets.forEach((snippet, index) => {
            const isConflict = conflictingNames.includes(snippet.name);
            const snippetType = snippet.type || 'Snippet'; // Default to 'Snippet' if type is missing
            const actionText = isConflict ? 'Update' : 'New';
            const uniqueId = `cwp-import-item-${index}`;

            const li = document.createElement('li');
            
            const checkbox = document.createElement('input');
            checkbox.type = 'checkbox';
            checkbox.id = uniqueId;
            checkbox.value = snippet.name;
            checkbox.checked = true; // Default to checked
            checkbox.dataset.isConflict = isConflict;

            const label = document.createElement('label');
            label.htmlFor = uniqueId;
            label.textContent = `${snippet.name} (${snippetType}) - ${actionText}`;

            li.appendChild(checkbox);
            li.appendChild(label);
            importList.appendChild(li);
        });

        // Show the dialog
        dialogOverlay.style.display = 'flex';

        // Add event listeners for the dialog buttons
        btnConfirm.onclick = () => {
            const snippetsToAdd = [];
            const snippetsToUpdate = [];

            importList.querySelectorAll('input[type="checkbox"]').forEach(checkbox => {
                if (checkbox.checked) {
                    if (checkbox.dataset.isConflict === 'true') {
                        snippetsToUpdate.push(checkbox.value);
                    } else {
                        snippetsToAdd.push(checkbox.value);
                    }
                }
            });

            dialogOverlay.style.display = 'none';
            
            if (snippetsToAdd.length === 0 && snippetsToUpdate.length === 0) {
                // User deselected everything, so cancel the import.
                fileInput.value = '';
                return;
            }

            submitForm(snippetsToAdd, snippetsToUpdate, bundledType, isReload);
        };
        
        btnCancel.onclick = () => {
            dialogOverlay.style.display = 'none';
            fileInput.value = ''; // Reset file input on cancel
        };
    }

    function submitForm(snippetsToAdd, snippetsToUpdate, bundledType = null, isReload = false) {
        // Remove any previous hidden inputs we might have added
        importForm.querySelectorAll('input[name="snippets_to_add[]"], input[name="snippets_to_update[]"], input[name="bundled_update_type"], input[name="is_reload"]').forEach(el => el.remove());

        // Add new hidden inputs for the selected snippets
        snippetsToAdd.forEach(name => {
            const hiddenInput = document.createElement('input');
            hiddenInput.type = 'hidden';
            hiddenInput.name = 'snippets_to_add[]';
            hiddenInput.value = name;
            importForm.appendChild(hiddenInput);
        });

        snippetsToUpdate.forEach(name => {
            const hiddenInput = document.createElement('input');
            hiddenInput.type = 'hidden';
            hiddenInput.name = 'snippets_to_update[]';
            hiddenInput.value = name;
            importForm.appendChild(hiddenInput);
        });

        // Add hidden input for bundled type if applicable
        if (bundledType) {
            const hiddenInput = document.createElement('input');
            hiddenInput.type = 'hidden';
            hiddenInput.name = 'bundled_update_type';
            hiddenInput.value = bundledType;
            importForm.appendChild(hiddenInput);
        }

        // Add hidden input for reload flag if applicable
        if (isReload) {
            const hiddenInput = document.createElement('input');
            hiddenInput.type = 'hidden';
            hiddenInput.name = 'is_reload';
            hiddenInput.value = '1';
            importForm.appendChild(hiddenInput);
        }

        fileInput.dataset.readyToSubmit = 'true';
        importForm.submit();
    }
});
