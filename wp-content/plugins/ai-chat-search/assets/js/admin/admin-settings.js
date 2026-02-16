/**
 * AI Chat Search - Admin Settings Module
 *
 * Handles provider selection, API key testing, and form submissions.
 *
 * @package AI_Chat_Search
 * @since 1.0.0
 */

(function($) {
    'use strict';

    var AIRS = window.AIRS || {};
    var i18n = window.listeo_ai_search_i18n || {};

    /**
     * Provider Toggle Handler
     * Shows/hides API key fields and updates model dropdowns based on selected provider
     */
    function initProviderToggle() {
        var $providerSelect = $('#listeo_ai_search_provider');

        // Handle select change
        $providerSelect.on('change', function() {
            var provider = $(this).val();
            updateProviderUI(provider);
        });

        // Store original value for provider switch detection
        $providerSelect.data('original-value', $providerSelect.val());

        // Handle toggle switch clicks
        $('.ai-provider-option').on('click', function() {
            var selectedValue = $(this).data('value');
            var currentValue = $providerSelect.val();
            var originalProvider = $providerSelect.data('original-value');

            // If clicking on already selected provider, do nothing
            if (selectedValue === currentValue) {
                return;
            }

            // Check if switching requires clearing embeddings
            var totalEmbeddings = AIRS.getTotalEmbeddings();

            if (originalProvider && selectedValue !== originalProvider && totalEmbeddings >= 2) {
                // Show confirmation modal
                $('#provider-change-modal').fadeIn(200);
                $('#provider-change-modal').data('pending-provider', selectedValue);
            } else {
                // Allow immediate change
                applyProviderChange(selectedValue);
            }
        });

        // Prevent slider from being clickable
        $('.ai-provider-slider').on('click', function(e) {
            e.stopPropagation();
        });

        // Modal cancel button
        $('#modal-cancel-btn, .airs-modal-overlay').on('click', function() {
            $('#provider-change-modal').fadeOut(200);
            var currentValue = $providerSelect.val();
            $('.ai-provider-toggle').attr('data-selected', currentValue);
            $('#provider-change-modal').removeData('pending-provider');
        });

        // Modal confirm button
        $('#modal-confirm-btn').on('click', function() {
            var $button = $(this);
            var pendingProvider = $('#provider-change-modal').data('pending-provider');

            if (!pendingProvider) return;

            AIRS.setButtonState($button, 'loading');

            AIRS.ajax({
                action: 'listeo_ai_clear_embeddings_for_provider_switch',
                data: { nonce: window.listeo_ai_search_ajax.clear_embeddings_nonce },
                success: function(response) {
                    if (response.success) {
                        applyProviderChange(pendingProvider);
                        $providerSelect.data('original-value', pendingProvider);
                        $('#provider-change-modal').fadeOut(200);
                        $('#provider-retrain-notice').remove();

                        // Add warning notice
                        var $form = $('.airs-ajax-form[data-section="api-config"]');
                        var $formActions = $form.find('.airs-form-actions');
                        var $notice = $('<div id="provider-retrain-notice" class="provider-retrain-notice">' +
                            '<span class="notice-emoji">\u26a0\ufe0f</span> ' +
                            (i18n.providerRetrainNotice || 'Click Save and go to Data Training tab and start retraining after changing provider.') +
                            '</div>');
                        $formActions.before($notice);
                        $notice[0].scrollIntoView({ behavior: 'smooth', block: 'center' });
                    } else {
                        alert(i18n.errorClearingEmbeddings || 'Error clearing embeddings. Please try again.');
                    }
                },
                error: function() {
                    alert(i18n.ajaxError || 'An error occurred. Please try again.');
                },
                complete: function() {
                    AIRS.setButtonState($button, 'reset');
                }
            });
        });
    }

    /**
     * Apply provider change to UI
     */
    function applyProviderChange(provider) {
        var $toggle = $('.ai-provider-toggle');
        $toggle.attr('data-selected', provider);
        $('#listeo_ai_search_provider').val(provider).trigger('change');
    }

    /**
     * Update UI based on provider selection
     */
    function updateProviderUI(provider) {
        // Hide all provider fields and model groups
        $('.provider-field').hide();
        $('.model-group-openai, .model-group-gemini, .model-group-mistral').hide();

        var models = {
            openai: {
                class: 'provider-openai',
                modelGroup: 'model-group-openai',
                label: i18n.openaiModel || 'OpenAI Model',
                help: i18n.openaiModelHelp || 'Select the OpenAI model for chat responses.',
                models: ['gpt-4o-mini', 'gpt-4o', 'gpt-4.1-nano', 'gpt-4.1-mini', 'gpt-4.1', 'gpt-5-mini', 'gpt-5-chat-latest', 'gpt-5.1', 'gpt-5.2'],
                default: 'gpt-5.1'
            },
            gemini: {
                class: 'provider-gemini',
                modelGroup: 'model-group-gemini',
                label: i18n.geminiModel || 'Gemini Model',
                help: i18n.geminiModelHelp || 'Select the Gemini model for chat responses.',
                models: ['gemini-2.5-flash', 'gemini-2.5-pro', 'gemini-3-pro-preview', 'gemini-3-flash-preview'],
                default: 'gemini-3-flash-preview'
            },
            mistral: {
                class: 'provider-mistral',
                modelGroup: 'model-group-mistral',
                label: i18n.mistralModel || 'Mistral Model',
                help: i18n.mistralModelHelp || 'Select the Mistral model for chat responses.',
                models: ['mistral-small-latest', 'mistral-medium-latest', 'mistral-large-latest'],
                default: 'mistral-large-latest'
            }
        };

        if (models[provider]) {
            var config = models[provider];
            $('.' + config.class).show();
            $('.' + config.modelGroup).show();
            $('#model-label-text').text(config.label);
            $('#model-help-text').text(config.help);

            // Auto-select default model if current is not valid for this provider
            var currentModel = $('#listeo_ai_chat_model').val();
            if (config.models.indexOf(currentModel) === -1) {
                $('#listeo_ai_chat_model').val(config.default).trigger('change');
            }
        }
    }

    /**
     * Model change handler for Gemini 3 warning
     */
    function initModelChangeHandler() {
        $('#listeo_ai_chat_model').on('change', function() {
            if ($(this).val() === 'gemini-3-pro-preview') {
                $('#gemini-3-warning').show();
            } else {
                $('#gemini-3-warning').hide();
            }
        });
    }

    /**
     * AJAX Form Handler
     * Generic handler for settings forms with section-based saving
     */
    function initAjaxFormHandler() {
        $('.airs-ajax-form').on('submit', function(e) {
            e.preventDefault();

            var $form = $(this);
            var $button = $form.find('button[type="submit"]');
            var $message = $form.find('.airs-form-message');
            var section = $form.data('section');

            AIRS.setButtonState($button, 'loading');
            $message.hide();

            // Collect form values
            var formData = collectFormData($form);
            formData.action = 'listeo_ai_save_settings';
            formData.nonce = window.listeo_ai_search_ajax.nonce;
            formData.section = section;


            $.post(window.listeo_ai_search_ajax.ajax_url, formData)
                .done(function(response) {

                    if (response.success) {
                        AIRS.showMessage($message, 'success',
                            '<strong>\u2713 ' + (i18n.success || 'Success!') + '</strong> ' + response.data.message,
                            3000
                        );

                        // Update hidden fields in other forms
                        $.each(formData, function(fieldName, fieldValue) {
                            if (fieldName !== 'action' && fieldName !== 'nonce' && fieldName !== 'section') {
                                $('input[type="hidden"][name="' + fieldName + '"]').val(fieldValue);
                            }
                        });
                    } else {
                        AIRS.showMessage($message, 'error',
                            '<strong>\u2717 ' + (i18n.error || 'Error!') + '</strong> ' + (response.data.message || 'Unknown error')
                        );
                    }
                })
                .fail(function(xhr, status, error) {
                    console.error('AJAX Error:', xhr, status, error);
                    AIRS.showMessage($message, 'error',
                        '<strong>\u2717 ' + (i18n.error || 'Error!') + '</strong> ' + (i18n.connectionFailed || 'Connection failed:') + ' ' + error
                    );
                })
                .always(function() {
                    AIRS.setButtonState($button, 'reset');
                });
        });
    }

    /**
     * Collect form data including checkboxes properly
     */
    function collectFormData($form) {
        var formData = {};

        $form.find('input, textarea, select').each(function() {
            var $input = $(this);
            var name = $input.attr('name');

            if (!name) return;

            if ($input.attr('type') === 'checkbox') {
                if (name.endsWith('[]')) {
                    // Array checkbox
                    var baseName = name.replace('[]', '');
                    if ($input.is(':checked')) {
                        if (!formData[baseName]) formData[baseName] = [];
                        formData[baseName].push($input.val());
                    } else if (!formData[baseName]) {
                        formData[baseName] = [];
                    }
                } else {
                    // Regular checkbox
                    formData[name] = $input.is(':checked') ? '1' : '0';
                }
            } else if ($input.attr('type') === 'radio') {
                if ($input.is(':checked')) {
                    formData[name] = $input.val();
                }
            } else {
                formData[name] = $input.val();
            }
        });

        return formData;
    }

    /**
     * API Key Test Handlers
     */
    function initApiKeyTests() {
        // OpenAI
        $('#test-api-key').on('click', function(e) {
            e.preventDefault();
            testApiKey('openai', $(this), '#api-test-result', '#listeo_ai_search_api_key');
        });

        // Gemini
        $('#test-gemini-api-key').on('click', function(e) {
            e.preventDefault();
            testApiKey('gemini', $(this), '#gemini-api-test-result', '#listeo_ai_search_gemini_api_key');
        });

        // Mistral
        $('#test-mistral-api-key').on('click', function(e) {
            e.preventDefault();
            testApiKey('mistral', $(this), '#mistral-api-test-result', '#listeo_ai_search_mistral_api_key');
        });
    }

    /**
     * Test API key for a provider
     */
    function testApiKey(provider, $button, resultSelector, keySelector) {
        var $result = $(resultSelector);
        var apiKey = $(keySelector).val().trim();

        if (!apiKey) {
            $result.removeClass('airs-api-test-success').addClass('airs-api-test-error')
                .html('\u274c ' + (i18n.enterApiKeyFirst || 'Please enter an API key first.'))
                .show();
            return;
        }

        AIRS.setButtonState($button, 'loading');
        $result.removeClass('airs-api-test-success airs-api-test-error')
            .html(i18n.testingConnection || 'Testing API connection...')
            .show();

        var action = provider === 'openai' ? 'listeo_ai_test_api_key' :
                     provider === 'gemini' ? 'listeo_ai_test_gemini_api_key' :
                     'listeo_ai_test_mistral_api_key';

        AIRS.ajax({
            action: action,
            data: { api_key: apiKey },
            success: function(response) {

                if (response.success) {
                    $result.removeClass('airs-api-test-error').addClass('airs-api-test-success')
                        .html(response.data.message)
                        .show();

                    if (response.data.details) {
                        $result.append('<br><small>' + response.data.details + '</small>');
                    }
                } else {
                    $result.removeClass('airs-api-test-success').addClass('airs-api-test-error')
                        .html(response.data.message || i18n.testFailed || 'Test failed')
                        .show();
                }
            },
            error: function(xhr, status, error) {
                $result.removeClass('airs-api-test-success').addClass('airs-api-test-error')
                    .html('\u274c ' + (i18n.connectionFailed || 'Connection failed:') + ' ' + error)
                    .show();
            },
            complete: function() {
                AIRS.setButtonState($button, 'reset');
            }
        });
    }

    /**
     * Clear Cache Handler
     */
    function initClearCache() {
        $('#listeo-clear-cache-btn').on('click', function(e) {
            e.preventDefault();

            var $button = $(this);
            var $status = $('#listeo-clear-cache-status');
            var originalHtml = $button.html();

            $button.prop('disabled', true)
                .html('<span class="airs-spinner" style="margin-right: 5px;"></span>' + (i18n.clearing || 'Clearing...'));
            $status.html('').removeClass('success error');

            AIRS.ajax({
                action: 'listeo_ai_clear_cache',
                success: function(response) {
                    if (response.success) {
                        $status.html(response.data.message).addClass('success').css('color', '#46b450');
                    } else {
                        $status.html(response.data.message || i18n.clearCacheFailed || 'Clear cache failed')
                            .addClass('error').css('color', '#dc3232');
                    }
                },
                error: function(xhr, status, error) {
                    $status.html('\u274c ' + (i18n.connectionFailed || 'Connection failed:') + ' ' + error)
                        .addClass('error').css('color', '#dc3232');
                },
                complete: function() {
                    setTimeout(function() {
                        $button.prop('disabled', false).html(originalHtml);
                        $status.fadeOut(3000);
                    }, 2000);
                }
            });
        });
    }

    /**
     * Clear IP Rate Limits Handler
     */
    function initClearRateLimits() {
        $('#clear-ip-rate-limits').on('click', function(e) {
            e.preventDefault();

            var $link = $(this);
            var originalText = $link.text();

            $link.text(i18n.clearing || 'Clearing...');

            AIRS.ajax({
                action: 'listeo_ai_clear_ip_rate_limits',
                success: function(response) {
                    if (response.success) {
                        $link.text(response.data.message).css('color', '#46b450');
                    } else {
                        $link.text(response.data.message || i18n.failed || 'Failed');
                    }
                },
                error: function() {
                    $link.text(i18n.connectionFailed || 'Connection failed');
                },
                complete: function() {
                    setTimeout(function() {
                        $link.text(originalText).css('color', '#dc3545');
                    }, 3000);
                }
            });
        });
    }

    /**
     * Create Missing Tables Handler
     */
    function initCreateTables() {
        $('#airs-create-missing-tables').on('click', function(e) {
            e.preventDefault();

            var $button = $(this);
            var $status = $('#airs-create-tables-status');
            var originalHtml = $button.html();

            $button.prop('disabled', true)
                .html('<span class="airs-spinner" style="margin-right: 5px;"></span>' + (i18n.creating || 'Creating...'));
            $status.html('').css('color', '');

            AIRS.ajax({
                action: 'listeo_ai_create_missing_tables',
                success: function(response) {
                    if (response.success) {
                        $status.html(response.data.message).css('color', '#46b450');
                        setTimeout(function() {
                            location.reload();
                        }, 2000);
                    } else {
                        $status.html(response.data.message || i18n.failedCreateTables || 'Failed to create tables')
                            .css('color', '#dc3232');
                    }
                },
                error: function(xhr, status, error) {
                    $status.html('\u274c ' + (i18n.connectionFailed || 'Connection failed:') + ' ' + error)
                        .css('color', '#dc3232');
                },
                complete: function() {
                    $button.prop('disabled', false).html(originalHtml);
                }
            });
        });
    }

    /**
     * Initialize all settings handlers
     */
    function init() {
        if (typeof window.listeo_ai_search_ajax === 'undefined') {
            console.error('AIRS Settings: AJAX variables not loaded');
            return;
        }

        initProviderToggle();
        initModelChangeHandler();
        initAjaxFormHandler();
        initApiKeyTests();
        initClearCache();
        initClearRateLimits();
        initCreateTables();

    }

    // Initialize on document ready
    $(document).ready(init);

})(jQuery);
