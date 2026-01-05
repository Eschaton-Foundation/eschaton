<?php

/**
 * CWP Editor Toolbar - 6 Action Buttons + Draggable Handle
 * Created: 12/11/25
 * Version: 3.1.0
 * 
 * Floating toolbar with 6 icon buttons and draggable handle
 * Each button force-clicks corresponding page element
 * Drag handle in corner for repositioning toolbar
 * 
 * Buttons (in order):
 * 1. Scroll to Top (↑)     ctrl+Up Arrow
 * 2. Save (💾)             ctrl+S
 * 3. Preview (👁)          ctrl+i ~or~ ctrl+alt+p
 * 4. Undo (↶)              ctrl+Z
 * 5. Redo (↷)              ctrl+Y
 * 6. Scroll to Bottom (↓)  ctrl+Down Arrow
 * 
 * Plus: Drag Handle (⋮⋮) - green circle to reposition toolbar
 * Hide toolbar: CTRL+X keyboard shortcut
 */

if (!function_exists('cwp_toolbar')) {
    /**
     * Render CWP editor toolbar with 6 action buttons and hide toggle
     * 
     * @return void Outputs HTML and CSS/JS
     */
    function cwp_toolbar() {
        ?>
<style>
/* CWP Toolbar Container - Floating centered */
.cwp-toolbar-container {
    position: fixed;
    bottom: 40%;
    right: 1%;
    display: flex;
    flex-direction: column;
    gap: 0;
    z-index: 100;
    opacity: 1;
    visibility: visible;
    transition: opacity 0.3s ease, visibility 0.3s ease;
    width: fit-content;
    user-select: none;
}

.cwp-toolbar-container.hidden {
    opacity: 0;
    visibility: hidden;
    pointer-events: none;
}

.cwp-toolbar-container.dragging {
    opacity: 0.5;
}

/* Toolbar Buttons - 40px × 40px */
.cwp-toolbar-btn {
    width: 40px;
    height: 40px;
    padding: 0;
    background-color: #00ab83;
    color: white;
    border: 1px solid rgba(0, 0, 0, 0.1);
    border-radius: 0;
    font-weight: 600;
    font-size: 18px;
    cursor: pointer;
    display: flex;
    align-items: center;
    justify-content: center;
    line-height: 1;
    box-shadow: 0 2px 8px rgba(0, 0, 0, 0.15);
    transition: background-color 0.2s ease;
}

.cwp-toolbar-btn:hover {
    background-color: #8f9193;
    box-shadow: 0 4px 12px rgba(0, 0, 0, 0.2);
}

.cwp-toolbar-btn:active {
    transform: scale(0.95);
}

/* First button - rounded top */
.cwp-toolbar-btn:first-of-type {
    border-radius: 4px 4px 0 0;
}

/* Last button - rounded bottom */
.cwp-toolbar-btn:last-of-type {
    border-radius: 0 0 4px 4px;
}

/* Hide Toggle Button - Drag Handle Circle */
.cwp-toolbar-hide-btn {
    position: absolute;
    top: -12px;
    right: -12px;
    width: 20px;
    height: 20px;
    padding: 0;
    background-color: #00ab83;
    color: white;
    border: 1px solid #000;
    border-radius: 50%;
    font-size: 10px;
    font-weight: bold;
    cursor: grab;
    display: flex;
    align-items: center;
    justify-content: center;
    line-height: 1;
    box-shadow: 0 2px 6px rgba(0, 0, 0, 0.1);
    transition: all 0.3s ease;
    z-index: 101;
    user-select: none;
    letter-spacing: 2px;
}

.cwp-toolbar-hide-btn:hover {
    background-color: #008f6e;
    box-shadow: 0 4px 10px rgba(0, 0, 0, 0.2);
}

.cwp-toolbar-hide-btn.dragging {
    cursor: grabbing;
}

/* Save Notice - Floating tooltip */
.cwp-save-notice {
    position: fixed;
    bottom: calc(40% - 30px);
    right: 1%;
    background-color: rgba(204, 204, 204, 0.7);
    color: #000;
    padding: 8px 12px;
    border-radius: 4px;
    font-size: 12px;
    white-space: nowrap;
    box-shadow: 0 2px 8px rgba(0, 0, 0, 0.2);
    opacity: 0;
    visibility: hidden;
    pointer-events: none;
    transition: opacity 0.3s ease, visibility 0.3s ease;
    z-index: 102;
}

.cwp-save-notice.show {
    opacity: 1;
    visibility: visible;
}
</style>

<div class="cwp-toolbar-container" id="cwp-toolbar">
    <button 
        id="cwp-toolbar-btn-scroll-top" 
        class="cwp-toolbar-btn" 
        type="button" 
        aria-label="Scroll to top">
        ↑
    </button>
    
    <button 
        id="cwp-toolbar-btn-save" 
        class="cwp-toolbar-btn" 
        type="button" 
        aria-label="Save">
        ✔
    </button>
    
    <button 
        id="cwp-toolbar-btn-preview" 
        class="cwp-toolbar-btn" 
        type="button" 
        aria-label="Preview">
        👁
    </button>
    
    <button 
        id="cwp-toolbar-btn-undo" 
        class="cwp-toolbar-btn" 
        type="button" 
        aria-label="Undo">
        ↶
    </button>
    
    <button 
        id="cwp-toolbar-btn-redo" 
        class="cwp-toolbar-btn" 
        type="button" 
        aria-label="Redo">
        ↷
    </button>
    
    <button 
        id="cwp-toolbar-btn-scroll-bottom" 
        class="cwp-toolbar-btn" 
        type="button" 
        aria-label="Scroll to bottom">
        ↓
    </button>
    
    <button 
        id="cwp-toolbar-hide" 
        class="cwp-toolbar-hide-btn" 
        type="button" 
        aria-label="Drag toolbar">
        ⋮⋮
    </button>
</div>

<div class="cwp-save-notice" id="cwp-save-notice">
    Oops! You must make changes before you can save.
</div>

<script>
document.addEventListener("DOMContentLoaded", function() {
    const toolbar = document.getElementById("cwp-toolbar");
    const hideBtn = document.getElementById("cwp-toolbar-hide");
    const saveNotice = document.getElementById("cwp-save-notice");
    let saveNoticeTimeout = null;
    
    // Toolbar enabled/disabled state
    let toolbarEnabled = true;
    
    // ===== DRAG AND DROP FUNCTIONALITY =====
    let isDragging = false;
    let offsetX = 0;
    let offsetY = 0;
    let startX = 0;
    let startY = 0;
    
    // Load toolbar position from localStorage
    function loadToolbarPosition() {
        const savedPosition = localStorage.getItem('cwp-toolbar-position');
        if (savedPosition) {
            try {
                const position = JSON.parse(savedPosition);
                toolbar.style.bottom = position.bottom;
                toolbar.style.right = position.right;
            } catch (e) {
                console.log('CWP Toolbar -> Invalid saved position, using defaults');
                // If localStorage is corrupt, let CSS defaults apply (don't set inline styles)
            }
        }
        // If no saved position, let CSS defaults apply (bottom: 40%, right: 1%)
    }
    
    // Save toolbar position to localStorage
    function saveToolbarPosition(bottom, right) {
        const position = {
            bottom: bottom,
            right: right
        };
        localStorage.setItem('cwp-toolbar-position', JSON.stringify(position));
    }
    
    // Load saved position on init
    loadToolbarPosition();
    
    // Mouse down - start dragging (only from hide button)
    hideBtn.addEventListener("mousedown", function(e) {
        e.preventDefault();
        isDragging = true;
        hideBtn.classList.add('dragging');
        toolbar.classList.add('dragging');
        
        // Disable transition during drag for instant response
        toolbar.style.transition = 'none';
        
        const rect = toolbar.getBoundingClientRect();
        startX = rect.left;
        startY = rect.top;
        offsetX = e.clientX - rect.left;
        offsetY = e.clientY - rect.top;
    });
    
    // Mouse move - drag toolbar
    document.addEventListener("mousemove", function(e) {
        if (!isDragging) return;
        
        // Calculate movement from starting position
        const moveX = e.clientX - (startX + offsetX);
        const moveY = e.clientY - (startY + offsetY);
        
        // Use transform for smooth dragging (better performance)
        toolbar.style.transform = `translate(${moveX}px, ${moveY}px)`;
    });
    
    // Mouse up - stop dragging
    document.addEventListener("mouseup", function(e) {
        if (!isDragging) return;
        
        isDragging = false;
        hideBtn.classList.remove('dragging');
        toolbar.classList.remove('dragging');
        
        // Re-enable transition
        toolbar.style.transition = '';
        
        // Get the final visual position after transform
        const rect = toolbar.getBoundingClientRect();
        const viewportHeight = window.innerHeight;
        const viewportWidth = window.innerWidth;
        
        // Calculate new position from viewport edges
        const newBottom = viewportHeight - rect.bottom;
        const newRight = viewportWidth - rect.right;
        
        // Remove transform first
        toolbar.style.transform = 'none';
        
        // Clear all position properties to start fresh
        toolbar.style.top = '';
        toolbar.style.left = '';
        toolbar.style.bottom = '';
        toolbar.style.right = '';
        
        // Set only bottom and right (what we want)
        toolbar.style.bottom = newBottom + 'px';
        toolbar.style.right = newRight + 'px';
        
        // Save position to localStorage
        saveToolbarPosition(newBottom + 'px', newRight + 'px');
        
        // Snap to position - trigger a reflow to finalize
        toolbar.offsetHeight;
    });
    
    // Hide button toggle - removed since button is now only for dragging
    // To hide toolbar, users can now use CTRL+X keyboard shortcut
    
    // Show save notice for 1.5 seconds
    function showSaveNotice() {
        clearTimeout(saveNoticeTimeout);
        saveNotice.classList.add("show");
        saveNoticeTimeout = setTimeout(() => {
            saveNotice.classList.remove("show");
        }, 1500);
    }
    
    // Keyboard Shortcuts
    document.addEventListener("keydown", function(e) {
        // CTRL+X - Toggle toolbar enabled/disabled
        if ((e.ctrlKey || e.metaKey) && e.shiftKey && e.key.toLowerCase() === 'x') {
            e.preventDefault();
            toolbarEnabled = !toolbarEnabled;
            toolbar.classList.toggle("hidden");
            return;
        }
        
        // If toolbar is disabled, don't process other shortcuts (except toggle)
        if (!toolbarEnabled) return;
        
        // CTRL+S - Check save button state and show notice if disabled
        if ((e.ctrlKey || e.metaKey) && e.key.toLowerCase() === 's') {
            e.preventDefault();
            const submitButton = document.querySelector('.cwp-form-actions-group input[type="submit"]');
            if (submitButton && submitButton.disabled) {
                showSaveNotice();
            }
            // Note: Let the plugin handle actual form submission
            return;
        }
        
        // CTRL+Alt+P - Open preview
        if ((e.ctrlKey || e.metaKey) && e.altKey && e.key.toLowerCase() === 'p') {
            e.preventDefault();
            const formActionsGroup = document.querySelector('.cwp-form-actions-group');
            if (formActionsGroup) {
                const previewButton = formActionsGroup.querySelector('#preview-button');
                if (previewButton) {
                    const onclickAttr = previewButton.getAttribute('onclick');
                    if (onclickAttr) {
                        // Safely extract URL from window.open() call
                        const urlMatch = onclickAttr.match(/window\.open\('([^']+)'/);
                        if (urlMatch && urlMatch[1]) {
                            window.open(urlMatch[1], '_blank');
                        }
                    } else {
                        previewButton.click();
                    }
                }
            }
            return;
        }

        // CTRL+i - Open preview
        if ((e.ctrlKey || e.metaKey) && e.key.toLowerCase() === 'i') {
            e.preventDefault();
            const formActionsGroup = document.querySelector('.cwp-form-actions-group');
            if (formActionsGroup) {
                const previewButton = formActionsGroup.querySelector('#preview-button');
                if (previewButton) {
                    const onclickAttr = previewButton.getAttribute('onclick');
                    if (onclickAttr) {
                        // Safely extract URL from window.open() call
                        const urlMatch = onclickAttr.match(/window\.open\('([^']+)'/);
                        if (urlMatch && urlMatch[1]) {
                            window.open(urlMatch[1], '_blank');
                        }
                    } else {
                        previewButton.click();
                    }
                }
            }
            return;
        }

        
        // CTRL+Up Arrow - Scroll to top
        if ((e.ctrlKey || e.metaKey) && e.key === 'ArrowUp') {
            e.preventDefault();
            window.scrollTo({
                top: 0,
                behavior: "smooth"
            });
            return;
        }
        
        // CTRL+Down Arrow - Scroll to bottom of fmcwp-admin-footer-wrap
        if ((e.ctrlKey || e.metaKey) && e.key === 'ArrowDown') {
            e.preventDefault();
            const footerWrap = document.querySelector('.fmcwp-admin-footer-wrap');
            if (footerWrap) {
                footerWrap.scrollIntoView({
                    behavior: "smooth",
                    block: "end"
                });
            }
            return;
        }
    });
    
    // Scroll to top button
    document.getElementById("cwp-toolbar-btn-scroll-top").addEventListener("click", function(e) {
        e.preventDefault();
        if (!toolbarEnabled) return;
        
        window.scrollTo({
            top: 0,
            behavior: "smooth"
        });
    });
    
    // Save button - show notice if submit button is disabled
    // Note: The plugin handles actual form submission via CTRL+S
    // This just provides visual feedback if they can't save
    document.getElementById("cwp-toolbar-btn-save").addEventListener("click", function(e) {
        e.preventDefault();
        if (!toolbarEnabled) return;
        
        // Check if the submit button on the page is disabled
        const submitButton = document.querySelector('.cwp-form-actions-group input[type="submit"]');
        
        if (submitButton && submitButton.disabled) {
            // Button is disabled - show the notice instead
            showSaveNotice();
            return;
        }
    });
    
    // Preview button - click the preview button on page
    document.getElementById("cwp-toolbar-btn-preview").addEventListener("click", function(e) {
        e.preventDefault();
        if (!toolbarEnabled) return;
        
        const formActionsGroup = document.querySelector('.cwp-form-actions-group');
        if (formActionsGroup) {
            const previewButton = formActionsGroup.querySelector('#preview-button');
            if (previewButton) {
                const onclickAttr = previewButton.getAttribute('onclick');
                if (onclickAttr) {
                    // Safely extract URL from window.open() call
                    const urlMatch = onclickAttr.match(/window\.open\('([^']+)'/);
                    if (urlMatch && urlMatch[1]) {
                        window.open(urlMatch[1], '_blank');
                    }
                } else {
                    previewButton.click();
                }
            }
        }
    });
    
    // Undo button - trigger CodeMirror undo on active editor
    document.getElementById("cwp-toolbar-btn-undo").addEventListener("click", function(e) {
        e.preventDefault();
        if (!toolbarEnabled) return;
        
        // Check which editor is active and perform undo
        const activeEditor = document.getElementById('active_editor')?.value || 'code';
        
        if (activeEditor === 'code' && window.codeEditorInstance && window.codeEditorInstance.codemirror) {
            window.codeEditorInstance.codemirror.undo();
        } else if (activeEditor === 'css' && window.cssEditorInstance && window.cssEditorInstance.codemirror) {
            window.cssEditorInstance.codemirror.undo();
        }
    });
    
    // Redo button - trigger CodeMirror redo on active editor
    document.getElementById("cwp-toolbar-btn-redo").addEventListener("click", function(e) {
        e.preventDefault();
        if (!toolbarEnabled) return;
        
        // Check which editor is active and perform redo
        const activeEditor = document.getElementById('active_editor')?.value || 'code';
        
        if (activeEditor === 'code' && window.codeEditorInstance && window.codeEditorInstance.codemirror) {
            window.codeEditorInstance.codemirror.redo();
        } else if (activeEditor === 'css' && window.cssEditorInstance && window.cssEditorInstance.codemirror) {
            window.cssEditorInstance.codemirror.redo();
        }
    });
    
    // Scroll to bottom button - scroll to bottom of fmcwp-admin-footer-wrap
    document.getElementById("cwp-toolbar-btn-scroll-bottom").addEventListener("click", function(e) {
        e.preventDefault();
        if (!toolbarEnabled) return;
        
        const footerWrap = document.querySelector('.fmcwp-admin-footer-wrap');
        if (footerWrap) {
            footerWrap.scrollIntoView({
                behavior: "smooth",
                block: "end"
            });
        }
    });
});
</script>
        <?php
    }    // end function
} // end if function exists