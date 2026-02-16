/**
 * Universal Settings Admin JavaScript
 * Handles post type toggles, reindexing, and AJAX interactions
 *
 * @package Listeo_AI_Search
 * @since 1.6.0
 */

(function($) {
    'use strict';

    const UniversalSettings = {
        /**
         * Initialize
         */
        init: function() {
            this.bindEvents();
            setTimeout(() => {
                this.updateGenerationCount();
            }, 300);
        },

        /**
         * Bind event handlers
         */
        bindEvents: function() {
            // Post type toggle switches
            $('.post-type-toggle').on('change', this.handlePostTypeToggle.bind(this));

            // Add custom post types button
            $('#add-custom-post-types-btn').on('click', this.handleAddCustomPostTypes.bind(this));

            // Manual selection links
            $(document).on('click', '.manual-selection-link', this.handleManualSelection.bind(this));
            $(document).on('click', '.clear-selection-link', this.handleClearSelection.bind(this));

            // Modal controls
            $('.listeo-ai-modal-close, #modal-cancel, .listeo-ai-modal-overlay').on('click', this.closeModal.bind(this));
            $('#select-all-posts').on('click', this.selectAllPosts.bind(this));
            $('#deselect-all-posts').on('click', this.deselectAllPosts.bind(this));
            $('#select-pending-posts').on('click', this.selectPendingPosts.bind(this));
            $('#select-verified-posts').on('click', this.selectVerifiedPosts.bind(this));
            $('#modal-search').on('keyup', this.filterPosts.bind(this));

            // Individual checkbox change - update count
            $(document).on('change', '#modal-posts-list input[type="checkbox"]', this.updateSelectionCount.bind(this));
            $('#modal-save').on('click', this.saveSelection.bind(this));
            $('#modal-train-now').on('click', this.trainNow.bind(this));

            // Bulk actions
            $('#reindex-all-enabled').on('click', this.handleBulkReindex.bind(this));
            $('#clear-all-embeddings').on('click', this.handleClearEmbeddings.bind(this));

            // Save custom meta fields
            $('#save-custom-meta').on('click', this.handleSaveCustomMeta.bind(this));

            // Collapsible headers
            $('.collapsible-header').on('click', this.handleCollapsibleToggle.bind(this));

            // Delete custom post type
            $(document).on('click', '.delete-custom-type', this.handleDeleteCustomType.bind(this));

            // Trial banner close
            this.initTrialBanner();
            $('#airs-trial-close').on('click', this.handleTrialBannerClose.bind(this));
        },

        /**
         * Handle collapsible section toggle
         */
        handleCollapsibleToggle: function(e) {
            const $header = $(e.currentTarget);
            const targetId = $header.data('toggle');
            const $content = $('#' + targetId);

            // Toggle header active class
            $header.toggleClass('active');

            // Toggle content visibility with slide animation
            $content.slideToggle(300);
        },

        /**
         * Handle delete custom post type
         */
        handleDeleteCustomType: function(e) {
            e.preventDefault();
            e.stopPropagation();

            const $button = $(e.currentTarget);
            const $card = $button.closest('.post-type-card');
            const postType = $button.data('post-type');
            const postTypeLabel = $card.find('.post-type-info h3').first().text().trim();

            if (!confirm(`Remove "${postTypeLabel}" from training? This will disable it and remove all its embeddings.`)) {
                return;
            }

            // Disable button during processing
            $button.prop('disabled', true);
            $button.css('opacity', '0.5');

            $.ajax({
                url: listeoAiUniversalSettings.ajax_url,
                type: 'POST',
                data: {
                    action: 'listeo_ai_remove_custom_post_type',
                    nonce: listeoAiUniversalSettings.nonce,
                    post_type: postType
                },
                success: (response) => {
                    if (response.success) {
                        this.showNotice('success', response.data.message);
                        // Fade out and remove card
                        $card.fadeOut(300, function() {
                            $(this).remove();
                            // Update generation count
                            UniversalSettings.updateGenerationCount();
                        });
                    } else {
                        this.showNotice('error', response.data || 'Error removing post type');
                        $button.prop('disabled', false);
                        $button.css('opacity', '1');
                    }
                },
                error: () => {
                    this.showNotice('error', 'Error removing post type');
                    $button.prop('disabled', false);
                    $button.css('opacity', '1');
                }
            });
        },

        /**
         * Handle add custom post types button click
         */
        handleAddCustomPostTypes: function(e) {
            const $button = $(e.currentTarget);

            // Check if button is already disabled (locked in FREE version)
            if ($button.prop('disabled')) {
                return;
            }

            const selectedTypes = [];

            // Collect selected checkboxes
            $('.custom-post-type-checkbox:checked').each(function() {
                selectedTypes.push($(this).val());
            });

            if (selectedTypes.length === 0) {
                alert('Please select at least one post type to add.');
                return;
            }

            // Disable button during processing
            $button.prop('disabled', true);
            const originalText = $button.text();
            $button.text('Adding...');

            $.ajax({
                url: listeoAiUniversalSettings.ajax_url,
                type: 'POST',
                data: {
                    action: 'listeo_ai_add_custom_post_types',
                    nonce: listeoAiUniversalSettings.nonce,
                    post_types: selectedTypes
                },
                success: (response) => {
                    if (response.success) {
                        this.showNotice('success', response.data.message);
                        // Reload page to show new cards
                        setTimeout(() => location.reload(), 1000);
                    } else {
                        this.showNotice('error', response.data || 'Error adding post types');
                        $button.prop('disabled', false);
                        $button.text(originalText);
                    }
                },
                error: () => {
                    this.showNotice('error', 'Error adding post types');
                    $button.prop('disabled', false);
                    $button.text(originalText);
                }
            });
        },

        /**
         * Handle post type toggle
         */
        handlePostTypeToggle: function(e) {
            const $toggle = $(e.currentTarget);
            const $card = $toggle.closest('.post-type-card');
            const $toggleSwitch = $toggle.closest('.toggle-switch');
            const postType = $card.data('post-type');
            const enabled = $toggle.is(':checked');

            // Disable toggle and show loading indicator
            $toggle.prop('disabled', true);
            $toggleSwitch.addClass('is-loading');

            // Add spinner if not already present
            if (!$toggleSwitch.find('.toggle-spinner').length) {
                $toggleSwitch.append('<span class="toggle-spinner"></span>');
            }

            $.ajax({
                url: listeoAiUniversalSettings.ajax_url,
                type: 'POST',
                data: {
                    action: 'listeo_ai_toggle_post_type',
                    nonce: listeoAiUniversalSettings.nonce,
                    post_type: postType,
                    enabled: enabled
                },
                success: (response) => {
                    if (response.success) {
                        // Update card state
                        if (enabled) {
                            $card.addClass('enabled');
                        } else {
                            $card.removeClass('enabled');
                        }

                        // Update external pages link active state
                        if (postType === 'ai_external_page') {
                            const $link = $card.find('.external-pages-link');
                            if (enabled) {
                                $link.addClass('active');
                            } else {
                                $link.removeClass('active');
                            }
                        }

                        // Show success message
                        this.showNotice('success', response.data.message);

                        // Refresh badge count for this card
                        this.refreshStats(postType);

                        // Update generation section count
                        this.updateGenerationCount();
                    } else {
                        // Revert toggle on error
                        $toggle.prop('checked', !enabled);

                        // Show error message (check both data and data.message)
                        const errorMsg = response.data?.message || response.data || listeoAiUniversalSettings.strings.error;
                        this.showNotice('error', errorMsg);
                    }
                },
                error: () => {
                    // Revert toggle
                    $toggle.prop('checked', !enabled);
                    this.showNotice('error', listeoAiUniversalSettings.strings.error);
                },
                complete: () => {
                    // Remove loading state and re-enable toggle
                    $toggle.prop('disabled', false);
                    $toggleSwitch.removeClass('is-loading');
                    $toggleSwitch.find('.toggle-spinner').remove();
                }
            });
        },

        /**
         * Handle reindex button click
         */
        handleReindex: function(e) {
            const $button = $(e.currentTarget);
            const postType = $button.data('post-type');

            if (!confirm(listeoAiUniversalSettings.strings.confirm_reindex)) {
                return;
            }

            // Disable button and show loading
            $button.prop('disabled', true);
            const originalText = $button.html();
            $button.html('<span class="airs-spinner"></span> ' + listeoAiUniversalSettings.strings.reindexing);

            $.ajax({
                url: listeoAiUniversalSettings.ajax_url,
                type: 'POST',
                data: {
                    action: 'listeo_ai_bulk_reindex_post_type',
                    nonce: listeoAiUniversalSettings.nonce,
                    post_type: postType
                },
                success: (response) => {
                    if (response.success) {
                        this.showNotice('success', response.data.message);
                        // Refresh stats after delay
                        setTimeout(() => {
                            this.refreshStats(postType);
                            this.updateGenerationCount();
                        }, 2000);
                    } else {
                        this.showNotice('error', response.data || listeoAiUniversalSettings.strings.error);
                    }
                },
                error: () => {
                    this.showNotice('error', listeoAiUniversalSettings.strings.error);
                },
                complete: () => {
                    $button.prop('disabled', false);
                    $button.html(originalText);
                }
            });
        },

        /**
         * Handle bulk reindex
         */
        handleBulkReindex: function() {
            if (!confirm('Reindex all enabled post types? This may take a while.')) {
                return;
            }

            const $progress = $('#bulk-progress');
            const $progressBar = $('#bulk-progress-bar');
            const $progressStatus = $('#bulk-progress-status');

            $progress.show();
            $progressBar.val(0);
            $progressStatus.text('Starting bulk reindex...');

            // Get all enabled post types
            const $enabledCards = $('.post-type-card.enabled');
            const totalTypes = $enabledCards.length;
            let completedTypes = 0;

            $enabledCards.each((index, card) => {
                const postType = $(card).data('post-type');

                $.ajax({
                    url: listeoAiUniversalSettings.ajax_url,
                    type: 'POST',
                    data: {
                        action: 'listeo_ai_bulk_reindex_post_type',
                        nonce: listeoAiUniversalSettings.nonce,
                        post_type: postType
                    },
                    success: (response) => {
                        completedTypes++;
                        const percentage = Math.round((completedTypes / totalTypes) * 100);
                        $progressBar.val(percentage);
                        $progressStatus.text(`Processing: ${completedTypes} / ${totalTypes} types completed`);

                        if (completedTypes === totalTypes) {
                            setTimeout(() => {
                                $progress.hide();
                                this.showNotice('success', 'Bulk reindex completed!');
                                location.reload();
                            }, 2000);
                        }
                    }
                });
            });
        },

        /**
         * Handle clear all embeddings
         */
        handleClearEmbeddings: function() {
            if (!confirm('WARNING: This will delete ALL embeddings. This action cannot be undone. Continue?')) {
                return;
            }

            // TODO: Implement clear embeddings AJAX call
            alert('Clear embeddings functionality - to be implemented');
        },

        /**
         * Handle save custom meta fields
         */
        handleSaveCustomMeta: function() {
            const $button = $('#save-custom-meta');
            const $textarea = $('#custom-meta-fields');
            const metaJson = $textarea.val();

            // Validate JSON
            try {
                const parsed = JSON.parse(metaJson || '{}');

                // Save via options API
                $.ajax({
                    url: listeoAiUniversalSettings.ajax_url,
                    type: 'POST',
                    data: {
                        action: 'update_option',
                        option: 'listeo_ai_search_custom_meta_fields',
                        value: JSON.stringify(parsed),
                        _ajax_nonce: listeoAiUniversalSettings.nonce
                    },
                    success: () => {
                        this.showNotice('success', 'Custom meta fields saved!');
                    },
                    error: () => {
                        this.showNotice('error', 'Failed to save custom meta fields');
                    }
                });
            } catch (e) {
                alert('Invalid JSON format. Please check your syntax.');
                $textarea.focus();
            }
        },

        /**
         * Refresh post type badge count and manual selection links
         */
        refreshStats: function(postType) {
            $.ajax({
                url: listeoAiUniversalSettings.ajax_url,
                type: 'POST',
                data: {
                    action: 'listeo_ai_get_post_type_stats',
                    nonce: listeoAiUniversalSettings.nonce,
                    post_type: postType
                },
                success: (response) => {
                    if (response.success) {
                        const stats = response.data;
                        const $card = $(`.post-type-card[data-post-type="${postType}"]`);

                        // Update badge count
                        const $badge = $card.find('.custom-type-badge');
                        $badge.text(this.formatNumber(stats.total));

                        // Update badge class
                        $badge.removeClass('has-content empty');
                        $badge.addClass(stats.total > 0 ? 'has-content' : 'empty');

                        // Update action links - Documents have special handling
                        const $actions = $card.find('.manual-selection-actions');

                        if (postType === 'ai_pdf_document') {
                            // Documents show Upload button instead of Manual selection
                            $actions.html(`
                                <a href="#" class="pdf-upload-link" id="upload-pdf-btn">
                                    <span class="dashicons dashicons-upload"></span>
                                    Upload documents
                                </a>
                            `);
                        } else if (postType === 'ai_external_page') {
                            // External Pages show Add button instead of Manual selection
                            $actions.html(`
                                <a href="#" class="external-pages-link" id="manage-external-pages-btn">
                                    <span class="dashicons dashicons-admin-site-alt3"></span>
                                    Add external pages
                                </a>
                            `);
                        } else if (stats.has_manual_selection) {
                            // Show active state with clear link
                            $actions.html(`
                                <a href="#" class="manual-selection-link active" data-post-type="${postType}">
                                    <span class="dashicons dashicons-yes"></span>
                                    Manual selection active
                                </a>
                                <a href="#" class="clear-selection-link" data-post-type="${postType}">
                                    Clear
                                </a>
                            `);
                        } else {
                            // Show normal state
                            $actions.html(`
                                <a href="#" class="manual-selection-link" data-post-type="${postType}">
                                    <span class="dashicons dashicons-admin-generic"></span>
                                    Manual selection
                                </a>
                            `);
                        }
                    }
                }
            });
        },

        /**
         * Show admin notice
         */
        showNotice: function(type, message) {
            const noticeClass = type === 'success' ? 'notice-success' : 'notice-error';
            const $notice = $(`
                <div class="notice ${noticeClass} is-dismissible">
                    <p>${message}</p>
                </div>
            `);

            $('.listeo-ai-universal-settings h1').after($notice);

            // Auto-dismiss after 5 seconds
            setTimeout(() => {
                $notice.fadeOut(() => $notice.remove());
            }, 5000);
        },

        /**
         * Format number with thousand separators
         */
        formatNumber: function(num) {
            return num.toString().replace(/\B(?=(\d{3})+(?!\d))/g, ",");
        },

        /**
         * Update generation section count
         */
        updateGenerationCount: function() {
            const $countText = $('#listing-count-text');

            // Check if element exists (it's only on database tab)
            if ($countText.length === 0) {
                return;
            }

            // Show loading state
            $countText.text('Loading...');

            $.ajax({
                url: listeoAiUniversalSettings.ajax_url,
                type: 'POST',
                data: {
                    action: 'listeo_ai_get_total_count',
                    nonce: listeoAiUniversalSettings.nonce
                },
                success: (response) => {
                    if (response.success) {
                        const data = response.data;

                        // Debug log
                        console.log('[AI Chat] Count update:', data);

                        // Build count message with list format
                        let html = '';
                        if (data.enabled_count === 0) {
                            html = '<span class="listing-count-empty">No content types enabled. Please enable at least one type above.</span>';
                        } else if (data.total === 0) {
                            html = '<span class="listing-count-empty">No published content found for enabled types.</span>';
                        } else {
                            // Build list of post types with check emojis
                            html = '<div class="listing-type-grid">';

                            if (data.type_breakdown) {
                                data.type_breakdown.forEach((typeData) => {
                                    html += `
                                        <div class="listing-type-item">
                                            <span class="listing-type-check">✓</span>
                                            <span class="listing-type-label">${typeData.label}:</span>
                                            <span class="listing-type-total">${this.formatNumber(typeData.total)}</span>
                                        </div>
                                    `;
                                });
                            } else {
                                // Fallback if no breakdown
                                data.enabled_types.forEach((type) => {
                                    html += `
                                        <div class="listing-type-item">
                                            <span class="listing-type-check">✓</span>
                                            <span class="listing-type-total">${type}</span>
                                        </div>
                                    `;
                                });
                            }

                            html += '</div>';

                            // Total summary line
                            html += `
                                <div class="listing-summary">
                                    Selected: ${this.formatNumber(data.total)} items
                                </div>
                            `;
                        }

                        $countText.html(html);
                    } else {
                        $countText.text('Error loading content count');
                    }
                },
                error: () => {
                    $countText.text('Error loading content count');
                }
            });
        },

        /**
         * Handle manual selection link click
         */
        handleManualSelection: function(e) {
            e.preventDefault();
            const $link = $(e.currentTarget);
            const postType = $link.data('post-type');

            this.openModal(postType);
        },

        /**
         * Handle clear selection link click
         */
        handleClearSelection: function(e) {
            e.preventDefault();
            const $link = $(e.currentTarget);
            const postType = $link.data('post-type');

            if (!confirm('Clear manual selection? All posts of this type will be included in generation.')) {
                return;
            }

            // Save empty selection with clear flag
            $.ajax({
                url: listeoAiUniversalSettings.ajax_url,
                type: 'POST',
                data: {
                    action: 'listeo_ai_generate_selected_posts',
                    nonce: listeoAiUniversalSettings.nonce,
                    post_type: postType,
                    post_ids: [],
                    clear: 'true'
                },
                success: (response) => {
                    if (response.success) {
                        this.showNotice('success', response.data.message);
                        this.refreshStats(postType);
                        this.updateGenerationCount();
                    } else {
                        this.showNotice('error', response.data || 'Error clearing selection');
                    }
                }
            });
        },

        /**
         * Open manual selection modal
         */
        openModal: function(postType) {
            const $modal = $('#manual-selection-modal');
            $modal.data('post-type', postType);
            $modal.fadeIn(200);

            // Load posts for this type
            $.ajax({
                url: listeoAiUniversalSettings.ajax_url,
                type: 'POST',
                data: {
                    action: 'listeo_ai_get_posts_for_selection',
                    nonce: listeoAiUniversalSettings.nonce,
                    post_type: postType
                },
                success: (response) => {
                    if (response.success) {
                        this.renderPostsList(response.data);
                    } else {
                        $('#modal-posts-list').html(`<p class="error-message">${response.data}</p>`);
                    }
                },
                error: () => {
                    $('#modal-posts-list').html('<p class="error-message">Error loading posts</p>');
                }
            });
        },

        /**
         * Close modal
         */
        closeModal: function() {
            $('#manual-selection-modal').fadeOut(200);
            $('#modal-search').val('');
        },

        /**
         * Render posts list in modal
         */
        renderPostsList: function(data) {
            const posts = data.posts;
            const selectedIds = data.selected_ids || [];
            const postType = data.post_type;

            $('#modal-title').text(`Manual Selection - ${data.post_type_label}`);

            // Show/hide "Select Verified Only" button based on post type
            if (postType === 'listing') {
                $('#select-verified-posts').show();
            } else {
                $('#select-verified-posts').hide();
            }

            let html = '<div class="posts-checkboxes">';
            posts.forEach((post) => {
                const isChecked = selectedIds.includes(parseInt(post.ID));
                const hasEmbedding = parseInt(post.has_embedding) === 1;
                const isVerified = parseInt(post.is_verified) === 1;
                const statusClass = hasEmbedding ? 'has-embedding' : 'no-embedding';

                html += `
                    <label class="post-checkbox-item ${statusClass}" data-has-embedding="${post.has_embedding}" data-is-verified="${isVerified ? '1' : '0'}">
                        <input type="checkbox" value="${post.ID}" ${isChecked ? 'checked' : ''}>
                        <span class="post-title">${post.post_title}</span>
                        <span class="post-id">ID: ${post.ID}</span>
                        ${isVerified ? '<span class="post-verified-badge">✓ Verified</span>' : ''}
                        <span class="post-status">${hasEmbedding ? '✓ Indexed' : 'Pending'}</span>
                    </label>
                `;
            });
            html += '</div>';

            $('#modal-posts-list').html(html);
            this.updateSelectionCount();
        },

        /**
         * Select all posts
         */
        selectAllPosts: function() {
            $('#modal-posts-list input[type="checkbox"]').prop('checked', true);
            this.updateSelectionCount();
        },

        /**
         * Deselect all posts
         */
        deselectAllPosts: function() {
            $('#modal-posts-list input[type="checkbox"]').prop('checked', false);
            this.updateSelectionCount();
        },

        /**
         * Select pending posts only
         */
        selectPendingPosts: function() {
            $('#modal-posts-list .post-checkbox-item').each(function() {
                const hasEmbedding = $(this).data('has-embedding') == '1';
                $(this).find('input[type="checkbox"]').prop('checked', !hasEmbedding);
            });
            this.updateSelectionCount();
        },

        /**
         * Select verified posts only
         */
        selectVerifiedPosts: function() {
            $('#modal-posts-list .post-checkbox-item').each(function() {
                const isVerified = $(this).data('is-verified') == '1';
                $(this).find('input[type="checkbox"]').prop('checked', isVerified);
            });
            this.updateSelectionCount();
        },

        /**
         * Filter posts by search term
         */
        filterPosts: function(e) {
            const searchTerm = $(e.currentTarget).val().toLowerCase();

            $('#modal-posts-list .post-checkbox-item').each(function() {
                const postTitle = $(this).find('.post-title').text().toLowerCase();
                if (postTitle.includes(searchTerm)) {
                    $(this).show();
                } else {
                    $(this).hide();
                }
            });
        },

        /**
         * Update selection count in modal footer
         */
        updateSelectionCount: function() {
            const total = $('#modal-posts-list input[type="checkbox"]').length;
            const selected = $('#modal-posts-list input[type="checkbox"]:checked').length;
            $('#modal-selection-count').text(`${selected} of ${total} selected`);
        },

        /**
         * Save selection
         */
        saveSelection: function() {
            const $modal = $('#manual-selection-modal');
            const postType = $modal.data('post-type');
            const selectedIds = [];

            $('#modal-posts-list input[type="checkbox"]:checked').each(function() {
                selectedIds.push(parseInt($(this).val()));
            });

            $.ajax({
                url: listeoAiUniversalSettings.ajax_url,
                type: 'POST',
                data: {
                    action: 'listeo_ai_generate_selected_posts',
                    nonce: listeoAiUniversalSettings.nonce,
                    post_type: postType,
                    post_ids: selectedIds
                },
                success: (response) => {
                    if (response.success) {
                        this.showNotice('success', response.data.message);
                        this.closeModal();
                        this.refreshStats(postType);
                        this.updateGenerationCount();
                    } else {
                        this.showNotice('error', response.data || 'Error saving selection');
                    }
                }
            });
        },

        /**
         * Train Now - Immediately generate embeddings for selected posts
         */
        trainNow: function() {
            const $modal = $('#manual-selection-modal');
            const $button = $('#modal-train-now');
            const postType = $modal.data('post-type');
            const selectedIds = [];

            $('#modal-posts-list input[type="checkbox"]:checked').each(function() {
                selectedIds.push(parseInt($(this).val()));
            });

            if (selectedIds.length === 0) {
                this.showNotice('error', 'Please select at least one item to train');
                return;
            }

            // Disable button and show loading state
            $button.prop('disabled', true);
            const originalHtml = $button.html();
            $button.html('<span class="airs-spinner"></span> Training...');

            // First, save the selection
            $.ajax({
                url: listeoAiUniversalSettings.ajax_url,
                type: 'POST',
                data: {
                    action: 'listeo_ai_generate_selected_posts',
                    nonce: listeoAiUniversalSettings.nonce,
                    post_type: postType,
                    post_ids: selectedIds
                },
                success: (response) => {
                    if (response.success) {
                        // Now generate embeddings for each selected post
                        this.generateEmbeddingsForPosts(selectedIds, $button, originalHtml, postType);
                    } else {
                        this.showNotice('error', response.data || 'Error saving selection');
                        $button.prop('disabled', false);
                        $button.html(originalHtml);
                    }
                },
                error: (xhr, status, error) => {
                    this.showNotice('error', 'Failed to save selection: ' + error);
                    $button.prop('disabled', false);
                    $button.html(originalHtml);
                }
            });
        },

        /**
         * Generate embeddings for multiple posts sequentially
         */
        generateEmbeddingsForPosts: function(postIds, $button, originalHtml, postType) {
            let completed = 0;
            let failed = 0;
            const total = postIds.length;

            const generateNext = (index) => {
                if (index >= total) {
                    // All done
                    const message = failed === 0
                        ? `Successfully trained ${completed} item(s)!`
                        : `Trained ${completed} item(s), ${failed} failed`;

                    this.showNotice(failed === 0 ? 'success' : 'warning', message);
                    this.refreshStats(postType);
                    this.updateGenerationCount();

                    $button.prop('disabled', false);
                    $button.html(originalHtml);

                    // Update selection count since we unchecked items
                    this.updateSelectionCount();
                    return;
                }

                const postId = postIds[index];
                const progress = `${index + 1}/${total}`;
                $button.html(`<span class="airs-spinner"></span> Training ${progress}...`);

                $.ajax({
                    url: listeoAiUniversalSettings.ajax_url,
                    type: 'POST',
                    data: {
                        action: 'listeo_ai_manage_database',
                        database_action: 'generate_single',
                        listing_id: postId,
                        nonce: listeoAiUniversalSettings.database_nonce
                    },
                    success: (response) => {
                        if (response.success) {
                            completed++;
                            // Update the post status in the modal
                            this.updatePostStatus(postId, 'indexed');
                        } else {
                            failed++;
                        }
                        // Continue to next post
                        generateNext(index + 1);
                    },
                    error: () => {
                        failed++;
                        // Continue to next post even on error
                        generateNext(index + 1);
                    }
                });
            };

            // Start generating from first post
            generateNext(0);
        },

        /**
         * Update post status in the modal list
         */
        updatePostStatus: function(postId, status) {
            const $checkbox = $(`#modal-posts-list input[value="${postId}"]`);
            const $item = $checkbox.closest('.post-checkbox-item');
            const $statusSpan = $item.find('.post-status');

            if (status === 'indexed') {
                // Update status text and styling
                $statusSpan.text('Indexed')
                    .removeClass('pending')
                    .addClass('indexed');

                // Update item class
                $item.removeClass('no-embedding')
                    .addClass('has-embedding');

                // Uncheck the checkbox
                $checkbox.prop('checked', false);
            }
        },

        /**
         * Initialize trial banner visibility based on localStorage
         */
        initTrialBanner: function() {
            var $banner = $('#airs-trial-banner');
            if ($banner.length && localStorage.getItem('airs_trial_banner_dismissed') === 'true') {
                $banner.hide();
            }
        },

        /**
         * Handle trial banner close button click
         */
        handleTrialBannerClose: function(e) {
            e.preventDefault();
            e.stopPropagation();

            var $banner = $('#airs-trial-banner');
            $banner.fadeOut(200, function() {
                localStorage.setItem('airs_trial_banner_dismissed', 'true');
            });
        }
    };

    // Initialize when DOM is ready
    $(document).ready(function() {
        UniversalSettings.init();
    });

})(jQuery);
