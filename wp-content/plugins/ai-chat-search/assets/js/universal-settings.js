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
                this.loadAllStats();
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

            // Individual checkbox change — sync to Set and enforce the Free limit.
            $(document).on(
                'change',
                '#modal-posts-list input[type="checkbox"]',
                this.handlePostSelectionChange.bind(this)
            );

            // Load More button
            $(document).on('click', '.load-more-btn', () => {
                this.syncCheckboxesToSet();
                this.loadModalPage(this._modalCurrentPage + 1, false);
            });

            $('#modal-save').on('click', this.saveSelection.bind(this));
            $('#modal-train-selected').on('click', this.trainSelected.bind(this));

            // Custom fields modal controls
            $('#configure-custom-fields-btn').on('click', this.openCustomFieldsModal.bind(this));
            $('.custom-fields-modal-close').on('click', this.closeCustomFieldsModal.bind(this));
            $('#custom-fields-post-type').on('change', this.loadCustomFieldsForPostType.bind(this));
            $('#custom-fields-refresh').on('click', this.loadCustomFieldsForPostType.bind(this));
            $('#custom-fields-select-all').on('click', this.selectAllCustomFields.bind(this));
            $('#custom-fields-deselect-all').on('click', this.deselectAllCustomFields.bind(this));
            $('#custom-fields-auto-detect').on('click', this.autoDetectCustomFields.bind(this));
            $('#custom-fields-save').on('click', this.saveCustomFieldsSelection.bind(this));
            $(document).on('click', '#custom-fields-toggle-listing-fields', this.toggleListingCustomFields.bind(this));
            $(document).on('change', '.custom-field-checkbox', this.handleCustomFieldSelectionChange.bind(this));

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

            // Lazy-load detected custom type counts when section is expanded
            if (targetId === 'custom-types-content' && !$content.data('stats-loaded')) {
                $content.data('stats-loaded', true);
                this.loadCustomTypeCounts();
            }
        },

        /**
         * Load counts for detected custom post types via AJAX
         */
        loadCustomTypeCounts: function() {
            const self = this;
            $('[data-custom-type]').each(function() {
                const $el = $(this);
                const postType = $el.data('custom-type');
                $.ajax({
                    url: listeoAiUniversalSettings.ajax_url,
                    type: 'POST',
                    data: {
                        action: 'listeo_ai_get_custom_type_count',
                        nonce: listeoAiUniversalSettings.nonce,
                        post_type: postType
                    },
                    success: function(response) {
                        if (response.success) {
                            const count = response.data.count;
                            $el.text(self.formatNumber(count));
                            $el.removeClass('loading');
                            $el.addClass(count > 0 ? 'has-content' : 'empty');
                        }
                    }
                });
            });
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
         * Custom field modal state
         */
        _customFieldsPostType: '',
        _customFieldsCurrentFields: [],
        _customFieldsHasManualConfig: false,
        _listingCustomFieldsVisible: false,

        /**
         * Open custom fields modal
         */
        openCustomFieldsModal: function(e) {
            if (e) {
                e.preventDefault();
            }

            const $modal = $('#custom-fields-modal');
            const $postTypeSelect = $('#custom-fields-post-type');

            if (!$modal.length || !$postTypeSelect.length) {
                return;
            }

            this._customFieldsPostType = $postTypeSelect.val();
            $modal.fadeIn(200);
            this.loadCustomFieldsForPostType();
        },

        /**
         * Close custom fields modal
         */
        closeCustomFieldsModal: function(e) {
            if (e) {
                e.preventDefault();
            }

            $('#custom-fields-modal').fadeOut(200);
        },

        /**
         * Load custom fields for selected post type
         */
        loadCustomFieldsForPostType: function(e) {
            if (e) {
                e.preventDefault();
            }

            const $list = $('#custom-fields-list');
            const postType = $('#custom-fields-post-type').val();

            if (!postType || !$list.length) {
                return;
            }

            this._customFieldsPostType = postType;
            this.setCustomFieldsAiStatus('');

            if (postType === 'listing') {
                this.setCustomFieldsActionButtonsDisabled(true);
                this.toggleCustomFieldsAiHelper(false);
                this._customFieldsCurrentFields = [];
                this._customFieldsHasManualConfig = false;
                this._listingCustomFieldsVisible = false;
                this.renderListingCustomFieldsInfo(!!listeoAiUniversalSettings.listing_custom_fields_enabled);
                return;
            }

            this._listingCustomFieldsVisible = false;
            this.toggleCustomFieldsAiHelper(true);
            this.setCustomFieldsActionButtonsDisabled(false);
            this.requestCustomFieldsForPostType(postType, $list);
        },

        /**
         * Request available custom fields and render them in the supplied container.
         */
        requestCustomFieldsForPostType: function(postType, $list) {
            $list = $list && $list.length ? $list : $('#custom-fields-list');
            $list.html('<p class="loading-message"><span class="airs-spinner" style="margin-right: 6px;"></span>' + listeoAiUniversalSettings.strings.loading_custom_fields + '</p>');
            $('#custom-fields-selection-count').text('');

            $.ajax({
                url: listeoAiUniversalSettings.ajax_url,
                type: 'POST',
                data: {
                    action: 'listeo_ai_get_custom_fields_for_post_type',
                    nonce: listeoAiUniversalSettings.nonce,
                    post_type: postType
                },
                success: (response) => {
                    if (response.success) {
                        this._customFieldsCurrentFields = response.data.fields || [];
                        this._customFieldsHasManualConfig = !!response.data.has_manual_config;
                        this.renderCustomFields(response.data.fields || [], response.data.has_manual_config, $list);
                    } else {
                        this._customFieldsCurrentFields = [];
                        const message = response.data || listeoAiUniversalSettings.strings.error;
                        $list.html('<p class="error-message">' + this.escapeHtml(message) + '</p>');
                    }
                },
                error: () => {
                    this._customFieldsCurrentFields = [];
                    $list.html('<p class="error-message">' + listeoAiUniversalSettings.strings.error + '</p>');
                }
            });
        },

        /**
         * Show the integrated listing fields notice and optional Pro disclosure.
         */
        renderListingCustomFieldsInfo: function(canConfigure, expanded) {
            const strings = listeoAiUniversalSettings.strings;
            const message = canConfigure
                ? (strings.listing_predefined_fields || 'Listings already include predefined fields automatically.')
                : (strings.listing_auto_fields || 'Listing fields are selected automatically through the Listeo integration. No action is needed.');
            let html = '<div class="custom-fields-integrated-notice"><p>' + this.escapeHtml(message) + '</p>';

            if (canConfigure) {
                const buttonText = expanded
                    ? (strings.listing_hide_all_fields || 'Hide custom fields')
                    : (strings.listing_show_all_fields || 'Show all custom fields');

                html += '<button type="button" id="custom-fields-toggle-listing-fields" class="button-link" aria-expanded="' + (expanded ? 'true' : 'false') + '">' +
                    this.escapeHtml(buttonText) +
                '</button>';

                if (expanded) {
                    html += '<div id="listing-custom-fields-results"></div>';
                }
            }

            html += '</div>';

            $('#custom-fields-list').html(html);

            const selected = (this._customFieldsCurrentFields || []).filter((field) => field.selected).length;
            $('#custom-fields-selection-count').text(selected ? selected + ' ' + strings.selected_fields : '');
        },

        /**
         * Reveal or hide all listing meta fields without clearing current checks.
         */
        toggleListingCustomFields: function(e) {
            if (e) {
                e.preventDefault();
            }

            if (!listeoAiUniversalSettings.listing_custom_fields_enabled) {
                return;
            }

            this._listingCustomFieldsVisible = !this._listingCustomFieldsVisible;
            this.renderListingCustomFieldsInfo(true, this._listingCustomFieldsVisible);
            this.setCustomFieldsActionButtonsDisabled(!this._listingCustomFieldsVisible);

            if (!this._listingCustomFieldsVisible) {
                $('#custom-fields-save').prop('disabled', !this._customFieldsCurrentFields.length);
                return;
            }

            const $results = $('#listing-custom-fields-results');
            if (this._customFieldsCurrentFields.length) {
                this.renderCustomFields(this._customFieldsCurrentFields, this._customFieldsHasManualConfig, $results);
                return;
            }

            this.requestCustomFieldsForPostType('listing', $results);
        },

        /**
         * Disable field selection actions for integrated content types.
         */
        setCustomFieldsActionButtonsDisabled: function(disabled) {
            $('#custom-fields-refresh, #custom-fields-select-all, #custom-fields-deselect-all, #custom-fields-auto-detect, #custom-fields-save').prop('disabled', disabled);
        },

        /**
         * Show or hide the AI helper section.
         */
        toggleCustomFieldsAiHelper: function(visible) {
            $('#custom-fields-ai-helper').toggle(!!visible);
        },

        /**
         * Update inline status for the AI helper.
         */
        setCustomFieldsAiStatus: function(message, type) {
            const $status = $('#custom-fields-auto-detect-status');

            $status
                .removeClass('is-success is-error')
                .text(message || '');

            if (message && type) {
                $status.addClass('is-' + type);
            }
        },

        /**
         * Render custom fields in the modal
         */
        renderCustomFields: function(fields, hasManualConfig, $target) {
            const $list = $target && $target.length ? $target : $('#custom-fields-list');
            this._customFieldsCurrentFields = fields || [];

            if (!fields.length) {
                $list.html('<p class="loading-message">' + listeoAiUniversalSettings.strings.no_custom_fields + '</p>');
                $('#custom-fields-selection-count').text('0 ' + listeoAiUniversalSettings.strings.selected_fields);
                $('#custom-fields-auto-detect').prop('disabled', true);
                return;
            }

            $('#custom-fields-auto-detect').prop('disabled', false);

            let html = '<div class="custom-fields-checkboxes">';
            fields.forEach((field) => {
                const metaKey = this.escapeHtml(field.meta_key || '');
                const sample = this.escapeHtml(field.sample || '');
                const type = this.escapeHtml(field.type || 'text');
                const usageCount = parseInt(field.usage_count || 0, 10);

                html += `
                    <label class="custom-field-checkbox-item">
                        <input type="checkbox" class="custom-field-checkbox" value="${metaKey}" ${field.selected ? 'checked' : ''}>
                        <span class="custom-field-info">
                            <span class="custom-field-title-row">
                                <code class="custom-field-meta-key">${metaKey}</code>
                                <span class="custom-field-type">${type}</span>
                                <span class="custom-field-usage">${this.formatNumber(usageCount)}</span>
                            </span>
                            ${sample ? '<span class="custom-field-sample">' + sample + '</span>' : ''}
                        </span>
                    </label>
                `;
            });
            html += '</div>';

            if (!hasManualConfig) {
                html += '<p class="description custom-fields-auto-note">' + (listeoAiUniversalSettings.strings.no_manual_custom_fields || 'No custom selection has been saved for this post type yet.') + '</p>';
            }

            $list.html(html);
            this.updateCustomFieldsSelectionCount();
        },

        /**
         * Select all custom fields in modal
         */
        selectAllCustomFields: function(e) {
            if (e) {
                e.preventDefault();
            }

            $('#custom-fields-list .custom-field-checkbox').prop('checked', true);
            this.syncCustomFieldsSelectionState();
            this.updateCustomFieldsSelectionCount();
        },

        /**
         * Deselect all custom fields in modal
         */
        deselectAllCustomFields: function(e) {
            if (e) {
                e.preventDefault();
            }

            $('#custom-fields-list .custom-field-checkbox').prop('checked', false);
            this.syncCustomFieldsSelectionState();
            this.updateCustomFieldsSelectionCount();
        },

        /**
         * Keep the cached field state in sync with rendered checkboxes.
         */
        syncCustomFieldsSelectionState: function() {
            const selected = new Set();
            $('#custom-fields-list .custom-field-checkbox:checked').each(function() {
                selected.add($(this).val());
            });

            (this._customFieldsCurrentFields || []).forEach((field) => {
                field.selected = selected.has(field.meta_key || '');
            });
        },

        /**
         * Handle an individual custom field selection change.
         */
        handleCustomFieldSelectionChange: function() {
            this.syncCustomFieldsSelectionState();
            this.updateCustomFieldsSelectionCount();
        },

        /**
         * Update selected custom field count
         */
        updateCustomFieldsSelectionCount: function() {
            const selected = $('#custom-fields-list .custom-field-checkbox:checked').length;
            $('#custom-fields-selection-count').text(selected + ' ' + listeoAiUniversalSettings.strings.selected_fields);
        },

        /**
         * Ask AI to suggest useful custom fields and mark them in the UI.
         */
        autoDetectCustomFields: function(e) {
            if (e) {
                e.preventDefault();
            }

            const postType = this._customFieldsPostType || $('#custom-fields-post-type').val();
            const fields = (this._customFieldsCurrentFields || []).map((field) => ({
                meta_key: field.meta_key || '',
                type: field.type || '',
                usage_count: field.usage_count || 0,
                sample: field.sample || ''
            })).filter((field) => field.meta_key);

            if (!postType || !fields.length) {
                return;
            }

            const $button = $('#custom-fields-auto-detect');
            const originalText = $button.text();
            $button.prop('disabled', true).text(listeoAiUniversalSettings.strings.auto_detecting_fields || 'Detecting fields...');
            this.setCustomFieldsAiStatus('');

            $.ajax({
                url: listeoAiUniversalSettings.ajax_url,
                type: 'POST',
                data: {
                    action: 'listeo_ai_suggest_custom_fields_for_post_type',
                    nonce: listeoAiUniversalSettings.nonce,
                    post_type: postType,
                    fields: JSON.stringify(fields)
                },
                success: (response) => {
                    if (response.success) {
                        const suggestedFields = response.data.suggested_fields || [];
                        const suggested = new Set(suggestedFields);

                        if (suggestedFields.length > 0) {
                            $('#custom-fields-list .custom-field-checkbox').each(function() {
                                $(this).prop('checked', suggested.has($(this).val()));
                            });

                            this.syncCustomFieldsSelectionState();
                            this.updateCustomFieldsSelectionCount();
                            this.setCustomFieldsAiStatus(listeoAiUniversalSettings.strings.auto_detected_fields_inline || 'Success. Suggestions applied.', 'success');
                            this.showNotice('success', listeoAiUniversalSettings.strings.auto_detected_fields);
                        } else {
                            this.setCustomFieldsAiStatus(listeoAiUniversalSettings.strings.no_suggested_fields, 'error');
                            this.showNotice('error', listeoAiUniversalSettings.strings.no_suggested_fields);
                        }
                    } else {
                        this.setCustomFieldsAiStatus(response.data || listeoAiUniversalSettings.strings.error, 'error');
                        this.showNotice('error', response.data || listeoAiUniversalSettings.strings.error);
                    }
                },
                error: () => {
                    this.setCustomFieldsAiStatus(listeoAiUniversalSettings.strings.error, 'error');
                    this.showNotice('error', listeoAiUniversalSettings.strings.error);
                },
                complete: () => {
                    $button.prop('disabled', false).text(originalText);
                }
            });
        },

        /**
         * Save selected custom fields
         */
        saveCustomFieldsSelection: function(e) {
            if (e) {
                e.preventDefault();
            }

            const postType = this._customFieldsPostType || $('#custom-fields-post-type').val();
            const selectedFields = [];
            const $button = $('#custom-fields-save');
            const originalText = $button.text();

            if (postType === 'listing' && !this._listingCustomFieldsVisible && this._customFieldsCurrentFields.length) {
                this._customFieldsCurrentFields.forEach((field) => {
                    if (field.selected && field.meta_key) {
                        selectedFields.push(field.meta_key);
                    }
                });
            } else {
                $('#custom-fields-list .custom-field-checkbox:checked').each(function() {
                    selectedFields.push($(this).val());
                });
            }

            $button.prop('disabled', true).text('Saving...');

            $.ajax({
                url: listeoAiUniversalSettings.ajax_url,
                type: 'POST',
                data: {
                    action: 'listeo_ai_save_custom_fields_for_post_type',
                    nonce: listeoAiUniversalSettings.nonce,
                    post_type: postType,
                    fields: selectedFields
                },
                success: (response) => {
                    if (response.success) {
                        this.showNotice('success', response.data.message || listeoAiUniversalSettings.strings.retrain_required);
                        this.closeCustomFieldsModal();
                    } else {
                        this.showNotice('error', response.data || listeoAiUniversalSettings.strings.error);
                    }
                },
                error: () => {
                    this.showNotice('error', listeoAiUniversalSettings.strings.error);
                },
                complete: () => {
                    $button.prop('disabled', false).text(originalText);
                }
            });
        },

        /**
         * Load stats for all post type cards and detected custom types on page load.
         * Uses a concurrency limit to avoid hammering the database.
         */
        loadAllStats: function() {
            const self = this;
            const maxConcurrent = 2;
            let running = 0;

            // Collect post type cards that need stats
            const queue = [];
            $('.post-type-card[data-post-type]').each(function() {
                queue.push({ type: 'card', postType: $(this).data('post-type') });
            });

            // Detected custom types are in a collapsed section and not activated
            // — skip loading their counts until user expands the section

            function processNext() {
                if (queue.length === 0) return;
                if (running >= maxConcurrent) return;

                const item = queue.shift();
                running++;

                self.refreshStats(item.postType, function() {
                    running--;
                    processNext();
                });
            }

            // Start initial batch
            for (let i = 0; i < maxConcurrent; i++) {
                processNext();
            }
        },

        /**
         * Refresh post type badge count and manual selection links
         */
        refreshStats: function(postType, onComplete) {
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
                        $badge.removeClass('has-content empty loading');
                        $badge.addClass(stats.total > 0 ? 'has-content' : 'empty');

                        // Update action links - Documents have special handling
                        const $actions = $card.find('.manual-selection-actions');

                        const s = listeoAiUniversalSettings.strings;
                        if (postType === 'ai_pdf_document') {
                            $actions.html(`
                                <a href="#" class="pdf-upload-link" id="upload-pdf-btn">
                                    <svg class="selection-action-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" focusable="false">
                                        <path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"></path>
                                        <path d="M14 2v6h6"></path>
                                        <path d="M12 18v-6"></path>
                                        <path d="m9 15 3-3 3 3"></path>
                                    </svg>
                                    ${s.upload_documents}
                                </a>
                            `);
                        } else if (postType === 'ai_external_page') {
                            $actions.html(`
                                <a href="#" class="external-pages-link" id="manage-external-pages-btn">
                                    <svg class="selection-action-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" focusable="false">
                                        <circle cx="12" cy="12" r="9"></circle>
                                        <path d="M3 12h18"></path>
                                        <path d="M12 3a14 14 0 0 1 0 18"></path>
                                        <path d="M12 3a14 14 0 0 0 0 18"></path>
                                    </svg>
                                    ${s.add_external_pages}
                                </a>
                            `);
                        } else if (stats.has_manual_selection) {
                            $actions.html(`
                                <a href="#" class="manual-selection-link active" data-post-type="${postType}">
                                    <svg class="selection-action-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" focusable="false">
                                        <circle cx="12" cy="12" r="9"></circle>
                                        <path d="m8 12 2.5 2.5L16 9"></path>
                                    </svg>
                                    ${s.manual_selection_active}
                                </a>
                                <a href="#" class="clear-selection-link" data-post-type="${postType}">
                                    ${s.clear}
                                </a>
                            `);
                        } else {
                            $actions.html(`
                                <a href="#" class="manual-selection-link" data-post-type="${postType}">
                                    <svg class="selection-action-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" focusable="false">
                                        <path d="M4 6h10"></path>
                                        <path d="M4 12h7"></path>
                                        <path d="M4 18h10"></path>
                                        <path d="M18 9v6"></path>
                                        <path d="M15 12h6"></path>
                                    </svg>
                                    ${s.manual_selection}
                                </a>
                            `);
                        }
                    }
                },
                complete: function() {
                    if (typeof onComplete === 'function') {
                        onComplete();
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
         * Escape HTML for template output
         */
        escapeHtml: function(value) {
            return String(value)
                .replace(/&/g, '&amp;')
                .replace(/</g, '&lt;')
                .replace(/>/g, '&gt;')
                .replace(/"/g, '&quot;')
                .replace(/'/g, '&#039;');
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
                        $countText.text(listeoAiUniversalSettings.strings.error_loading_count);
                    }
                },
                error: () => {
                    $countText.text(listeoAiUniversalSettings.strings.error_loading_count);
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

            if (!confirm(listeoAiUniversalSettings.strings.confirm_clear_selection)) {
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
         * Modal state — persists across pages
         */
        _modalSelectedIds: new Set(),
        _modalTotalPosts: 0,
        _modalCurrentPage: 1,
        _modalTotalPages: 1,
        _modalPostType: '',
        _modalSearch: '',
        _searchDebounce: null,
        _modalIsLimited: false,
        _modalSelectionLimit: 0,
        _modalHomepageId: 0,

        /**
         * Open manual selection modal
         */
        openModal: function(postType) {
            const $modal = $('#manual-selection-modal');
            $modal.data('post-type', postType);
            $modal.fadeIn(200);

            // Reset state
            this._modalSelectedIds = new Set();
            this._modalCurrentPage = 1;
            this._modalPostType = postType;
            this._modalSearch = '';
            this._modalIsLimited = false;
            this._modalSelectionLimit = 0;
            this._modalHomepageId = 0;
            $('#modal-search').val('');
            $('#modal-train-selected').removeClass('is-visible');
            this.hideSelectionLimitNotice();

            // Show/hide "Select Verified Only" button based on post type
            if (postType === 'listing') {
                $('#select-verified-posts').show();
            } else {
                $('#select-verified-posts').hide();
            }

            this.loadModalPage(1, true);
        },

        /**
         * Load a page of posts into the modal
         */
        loadModalPage: function(page, isFirstLoad) {
            const self = this;
            const $list = $('#modal-posts-list');

            if (isFirstLoad) {
                $list.html('<p class="loading-message"><span class="airs-spinner" style="margin-right: 6px;"></span>Loading posts...</p>');
            }

            $.ajax({
                url: listeoAiUniversalSettings.ajax_url,
                type: 'POST',
                data: {
                    action: 'listeo_ai_get_posts_for_selection',
                    nonce: listeoAiUniversalSettings.nonce,
                    post_type: this._modalPostType,
                    page: page,
                    per_page: 50,
                    search: this._modalSearch
                },
                success: (response) => {
                    if (response.success) {
                        const data = response.data;
                        this._modalCurrentPage = data.page;
                        this._modalTotalPages = data.total_pages;
                        this._modalTotalPosts = data.total;
                        this._modalIsLimited = Boolean(data.is_limited);
                        this._modalSelectionLimit = parseInt(data.selection_limit, 10) || 0;
                        this._modalHomepageId = parseInt(data.homepage_id, 10) || 0;
                        $('#modal-train-selected').toggleClass('is-visible', Boolean(data.can_train_selected));

                        // On first load, initialize selected IDs from saved selection
                        if (isFirstLoad) {
                            this._modalSelectedIds = new Set((data.selected_ids || []).map(id => parseInt(id)));
                            $('#modal-title').text(`${listeoAiUniversalSettings.strings.manual_selection} - ${data.post_type_label}`);
                        }

                        this.renderPostsPage(data.posts, isFirstLoad);
                        this.updateSelectionCount();
                    } else {
                        $list.html(`<p class="error-message">${response.data}</p>`);
                    }
                },
                error: () => {
                    $list.html('<p class="error-message">' + listeoAiUniversalSettings.strings.error_loading_posts + '</p>');
                }
            });
        },

        /**
         * Render a page of posts into the modal
         */
        renderPostsPage: function(posts, replace) {
            const $list = $('#modal-posts-list');
            let $container;

            if (replace) {
                $list.html('<div class="posts-checkboxes"></div>');
                $container = $list.find('.posts-checkboxes');
            } else {
                $container = $list.find('.posts-checkboxes');
                // Remove existing load more button
                $list.find('.load-more-container').remove();
            }

            let html = '';
            const s = listeoAiUniversalSettings.strings;
            posts.forEach((post) => {
                const postId = parseInt(post.ID);
                const isChecked = this._modalSelectedIds.has(postId);
                const hasEmbedding = parseInt(post.has_embedding) === 1;
                const isVerified = parseInt(post.is_verified) === 1;
                const isHomepage = postId === this._modalHomepageId;
                const statusClass = hasEmbedding ? 'has-embedding' : 'no-embedding';

                html += `
                    <label class="post-checkbox-item ${statusClass}" data-has-embedding="${post.has_embedding}" data-is-verified="${isVerified ? '1' : '0'}">
                        <input type="checkbox" value="${postId}" ${isChecked ? 'checked' : ''}>
                        <span class="post-title">${post.post_title}</span>
                        <span class="post-id">ID: ${postId}</span>
                        ${isHomepage ? '<span class="post-verified-badge">' + s.homepage + '</span>' : ''}
                        ${isVerified ? '<span class="post-verified-badge">✓ ' + s.verified + '</span>' : ''}
                        <span class="post-status">${hasEmbedding ? '✓ ' + s.indexed : s.pending}</span>
                    </label>
                `;
            });
            $container.append(html);

            // Add "Load More" button if there are more pages
            if (this._modalCurrentPage < this._modalTotalPages) {
                const showing = $container.find('.post-checkbox-item').length;
                $list.append(`
                    <div class="load-more-container" style="text-align: center; padding: 12px;">
                        <button type="button" class="button load-more-btn">
                            Load More (${showing} of ${this._modalTotalPosts})
                        </button>
                    </div>
                `);
            }
        },

        /**
         * Close modal
         */
        closeModal: function() {
            $('#manual-selection-modal').fadeOut(200);
            $('#modal-search').val('');
            this._modalSearch = '';
        },

        /**
         * Sync visible checkboxes → Set (call before any Set read after user interaction)
         */
        syncCheckboxesToSet: function() {
            const self = this;
            $('#modal-posts-list input[type="checkbox"]').each(function() {
                const id = parseInt($(this).val());
                if ($(this).is(':checked')) {
                    self._modalSelectedIds.add(id);
                } else {
                    self._modalSelectedIds.delete(id);
                }
            });
        },

        /**
         * Keep the Free selection at its limit while honoring the newest click.
         */
        handlePostSelectionChange: function(e) {
            const $checkbox = $(e.currentTarget);
            const id = parseInt($checkbox.val());

            if (
                $checkbox.is(':checked') &&
                this._modalIsLimited &&
                this.getCountedSelectionSize() >= this._modalSelectionLimit
            ) {
                const replaceableIds = Array.from(this._modalSelectedIds)
                    .filter(selectedId => selectedId !== id);
                const replacedId = replaceableIds.pop();

                if (replacedId) {
                    this._modalSelectedIds.delete(replacedId);
                    $(`#modal-posts-list input[value="${replacedId}"]`).prop('checked', false);
                    this._modalSelectedIds.add(id);
                } else {
                    $checkbox.prop('checked', false);
                }

                this.showSelectionLimitNotice();
                this.updateSelectionCount();
                return;
            }

            this.hideSelectionLimitNotice();
            if ($checkbox.is(':checked')) {
                this._modalSelectedIds.add(id);
            } else {
                this._modalSelectedIds.delete(id);
            }
            this.updateSelectionCount();
        },

        /**
         * Count selected quota-consuming items.
         */
        getCountedSelectionSize: function() {
            return this._modalSelectedIds.size;
        },

        /**
         * Show the Free limit inside the selection modal.
         */
        showSelectionLimitNotice: function() {
            $('#modal-selection-limit-notice')
                .html(listeoAiUniversalSettings.strings.free_training_limit_notice || '')
                .prop('hidden', false);
        },

        /**
         * Hide the inline selection-limit notice.
         */
        hideSelectionLimitNotice: function() {
            $('#modal-selection-limit-notice')
                .text('')
                .prop('hidden', true);
        },

        /**
         * Select all visible posts (only what's currently loaded/shown)
         */
        selectAllPosts: function() {
            const self = this;
            let limitReached = false;
            $('#modal-posts-list input[type="checkbox"]').each(function() {
                if ($(this).is(':disabled') || $(this).is(':checked')) {
                    return;
                }
                if (self._modalIsLimited && self.getCountedSelectionSize() >= self._modalSelectionLimit) {
                    limitReached = true;
                    return false;
                }
                $(this).prop('checked', true);
                self._modalSelectedIds.add(parseInt($(this).val()));
            });
            if (limitReached) {
                this.showSelectionLimitNotice();
            } else {
                this.hideSelectionLimitNotice();
            }
            this.updateSelectionCount();
        },

        /**
         * Deselect all visible posts
         */
        deselectAllPosts: function() {
            const self = this;
            $('#modal-posts-list input[type="checkbox"]').each(function() {
                if ($(this).is(':disabled')) {
                    return;
                }
                $(this).prop('checked', false);
                self._modalSelectedIds.delete(parseInt($(this).val()));
            });
            this.hideSelectionLimitNotice();
            this.updateSelectionCount();
        },

        /**
         * Select pending posts only (server-side)
         */
        selectPendingPosts: function() {
            const self = this;
            this._modalSelectedIds.clear();
            $.ajax({
                url: listeoAiUniversalSettings.ajax_url,
                type: 'POST',
                data: {
                    action: 'listeo_ai_get_bulk_post_ids',
                    nonce: listeoAiUniversalSettings.nonce,
                    post_type: this._modalPostType,
                    filter: 'pending'
                },
                success: (response) => {
                    if (response.success) {
                        response.data.ids.forEach(id => self._modalSelectedIds.add(id));
                        self.hideSelectionLimitNotice();
                        // Update visible checkboxes
                        $('#modal-posts-list .post-checkbox-item').each(function() {
                            const id = parseInt($(this).find('input').val());
                            $(this).find('input').prop('checked', self._modalSelectedIds.has(id));
                        });
                        self.updateSelectionCount();
                    }
                }
            });
        },

        /**
         * Select verified posts only (server-side)
         */
        selectVerifiedPosts: function() {
            const self = this;
            this._modalSelectedIds.clear();
            $.ajax({
                url: listeoAiUniversalSettings.ajax_url,
                type: 'POST',
                data: {
                    action: 'listeo_ai_get_bulk_post_ids',
                    nonce: listeoAiUniversalSettings.nonce,
                    post_type: this._modalPostType,
                    filter: 'verified'
                },
                success: (response) => {
                    if (response.success) {
                        response.data.ids.forEach(id => self._modalSelectedIds.add(id));
                        self.hideSelectionLimitNotice();
                        $('#modal-posts-list .post-checkbox-item').each(function() {
                            const id = parseInt($(this).find('input').val());
                            $(this).find('input').prop('checked', self._modalSelectedIds.has(id));
                        });
                        self.updateSelectionCount();
                    }
                }
            });
        },

        /**
         * Filter posts by search term (server-side, debounced)
         */
        filterPosts: function(e) {
            const searchTerm = $(e.currentTarget).val();
            clearTimeout(this._searchDebounce);

            this._searchDebounce = setTimeout(() => {
                // Sync current checkbox state before reloading
                this.syncCheckboxesToSet();
                this._modalSearch = searchTerm;
                this._modalCurrentPage = 1;
                this.loadModalPage(1, true);
            }, 300);
        },

        /**
         * Update selection count in modal footer
         */
        updateSelectionCount: function() {
            const selected = this._modalIsLimited
                ? this.getCountedSelectionSize()
                : this._modalSelectedIds.size;
            const total = this._modalTotalPosts;
            const s = listeoAiUniversalSettings.strings;
            if (this._modalIsLimited) {
                $('#modal-selection-count').text(
                    `${selected} ${s.selected_of} ${this._modalSelectionLimit} ${s.selected}`
                );
                return;
            }
            $('#modal-selection-count').text(`${selected} ${s.selected_of} ${total} ${s.selected}`);
        },

        /**
         * Save selection
         */
        saveSelection: function() {
            this.syncCheckboxesToSet();
            const $modal = $('#manual-selection-modal');
            const postType = $modal.data('post-type');
            const selectedIds = Array.from(this._modalSelectedIds);

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
         * Save and train the selected items without clearing existing embeddings.
         */
        trainSelected: function() {
            this.syncCheckboxesToSet();
            const $modal = $('#manual-selection-modal');
            const $button = $('#modal-train-selected');
            const postType = $modal.data('post-type');
            const selectedIds = Array.from(this._modalSelectedIds);
            const originalHtml = $button.html();
            const strings = listeoAiUniversalSettings.strings;

            if (selectedIds.length === 0) {
                this.showNotice('error', strings.select_item_to_train);
                return;
            }

            $button.prop('disabled', true).html(
                '<span class="airs-spinner"></span><span>' + strings.training_selected + '</span>'
            );

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
                    if (!response.success) {
                        this.showNotice('error', response.data || 'Error saving selection');
                        $button.prop('disabled', false).html(originalHtml);
                        return;
                    }

                    this.generateSelectedEmbeddings(selectedIds, $button, originalHtml, postType);
                },
                error: () => {
                    this.showNotice('error', strings.selected_training_incomplete);
                    $button.prop('disabled', false).html(originalHtml);
                }
            });
        },

        /**
         * Generate embeddings for selected items sequentially.
         */
        generateSelectedEmbeddings: function(postIds, $button, originalHtml, postType) {
            let failed = 0;
            const strings = listeoAiUniversalSettings.strings;

            const generateNext = (index) => {
                if (index >= postIds.length) {
                    this.showNotice(
                        failed === 0 ? 'success' : 'warning',
                        failed === 0
                            ? strings.selected_training_complete
                            : strings.selected_training_incomplete
                    );
                    $button.prop('disabled', false).html(originalHtml);
                    this.refreshStats(postType);
                    this.updateGenerationCount();
                    return;
                }

                const postId = postIds[index];
                $button.html(
                    '<span class="airs-spinner"></span><span>' +
                    strings.training_selected + ' ' + (index + 1) + '/' + postIds.length +
                    '</span>'
                );

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
                            this.updateSelectedPostStatus(postId);
                        } else {
                            failed++;
                        }
                        generateNext(index + 1);
                    },
                    error: () => {
                        failed++;
                        generateNext(index + 1);
                    }
                });
            };

            generateNext(0);
        },

        /**
         * Mark a trained item as indexed without changing the selection.
         */
        updateSelectedPostStatus: function(postId) {
            const $item = $(`#modal-posts-list input[value="${postId}"]`).closest('.post-checkbox-item');
            $item.removeClass('no-embedding').addClass('has-embedding');
            $item.attr('data-has-embedding', '1');
            $item.find('.post-status')
                .text('✓ ' + listeoAiUniversalSettings.strings.indexed)
                .removeClass('pending')
                .addClass('indexed');
        },

        /**
         * Initialize trial banner visibility based on localStorage
         */
        initTrialBanner: function() {
            var $banner = $('#airs-trial-banner');
            if (!$banner.length || !$banner.find('#airs-trial-close').length) {
                return;
            }
            if (localStorage.getItem('airs_trial_banner_dismissed') === 'true') {
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
            if (!$banner.find('#airs-trial-close').length) {
                return;
            }
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
