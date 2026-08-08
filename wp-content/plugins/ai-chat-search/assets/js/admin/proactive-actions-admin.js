(function ($) {
    'use strict';

    var config = window.purioProactiveActionsAdmin || {};
    var strings = config.strings || {};
    var optionKey = config.optionKey || 'listeo_ai_proactive_actions';
    var ruleLimit = Math.max(1, parseInt(config.ruleLimit, 10) || 1);
    var searchTimer = null;
    var whitelistSearchTimer = null;
    var $colorDropdown = null;
    var collapsedActionsCookie = 'purio_proactive_collapsed_actions';
    var labelIcons = {
        trigger: '<svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" style="vertical-align: -2px; margin-right: 5px;" aria-hidden="true"><circle cx="12" cy="12" r="7"></circle><path d="M12 2v4"></path><path d="M12 18v4"></path><path d="M2 12h4"></path><path d="M18 12h4"></path><path d="M12 9v6"></path><path d="M9 12h6"></path></svg>',
        after: '<svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" style="vertical-align: -2px; margin-right: 5px;" aria-hidden="true"><circle cx="12" cy="12" r="9"></circle><path d="M12 7v5l3 2"></path></svg>',
        action: '<svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" style="vertical-align: -2px; margin-right: 5px;" aria-hidden="true"><path d="M13 2 3 14h9l-1 8 10-12h-9l1-8Z"></path></svg>',
        where: '<svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" style="vertical-align: -2px; margin-right: 5px;" aria-hidden="true"><circle cx="12" cy="12" r="9"></circle><path d="M3 12h18"></path><path d="M12 3a14 14 0 0 1 0 18"></path><path d="M12 3a14 14 0 0 0 0 18"></path></svg>',
        message: '<svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" style="vertical-align: -2px; margin-right: 5px;" aria-hidden="true"><path d="M21 15a4 4 0 0 1-4 4H8l-5 3V7a4 4 0 0 1 4-4h10a4 4 0 0 1 4 4Z"></path></svg>',
        buttons: '<svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" style="vertical-align: -2px; margin-right: 5px;" aria-hidden="true"><rect x="3" y="5" width="18" height="5" rx="2"></rect><rect x="3" y="14" width="18" height="5" rx="2"></rect></svg>'
    };

    function escapeHtml(value) {
        return $('<div>').text(value == null ? '' : String(value)).html();
    }

    function ruleField(index, field) {
        return optionKey + '[rules][' + index + '][' + field + ']';
    }

    function quickActionField(ruleIndex, quickActionIndex, field) {
        return ruleField(ruleIndex, 'quick_actions') + '[' + quickActionIndex + '][' + field + ']';
    }

    function getActionTitle(number) {
        return String(strings.actionTitle || 'Action %d').replace('%d', number);
    }

    function initEmojiPickers($context) {
        if (window.PurioEmojiPicker && typeof window.PurioEmojiPicker.init === 'function') {
            window.PurioEmojiPicker.init($context);
        }
    }

    function postTypeOptions() {
        var html = '<option value="">' + escapeHtml(strings.selectContentType) + '</option>';

        $.each(config.postTypes || {}, function (value, label) {
            html += '<option value="' + escapeHtml(value) + '">' + escapeHtml(label) + '</option>';
        });

        return html;
    }

    function buildRule(index) {
        var id = 'rule-' + Date.now() + '-' + Math.floor(Math.random() * 100000);

        return [
            '<div class="purio-proactive-rule" data-rule-index="' + index + '">',
            '<div class="purio-proactive-rule-title">',
            '<div class="purio-proactive-rule-name">',
            '<span class="purio-proactive-rule-title-text">' + escapeHtml(getActionTitle(index + 1)) + '</span>',
            '<input type="text" class="airs-input purio-proactive-rule-name-input" maxlength="80" name="' + ruleField(index, 'name') + '" value="" placeholder="' + escapeHtml(getActionTitle(index + 1)) + '" aria-label="' + escapeHtml(strings.actionName) + '">',
            '<button type="button" class="purio-proactive-edit-name" title="' + escapeHtml(strings.editActionName) + '" aria-label="' + escapeHtml(strings.editActionName) + '" aria-expanded="false"><svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M12 20h9"></path><path d="M16.5 3.5a2.1 2.1 0 0 1 3 3L7 19l-4 1 1-4Z"></path></svg></button>',
            '</div>',
            '<div class="purio-proactive-rule-title-actions"><label class="purio-proactive-priority" title="' + escapeHtml(strings.priorityHelp) + '"><span>' + escapeHtml(strings.priority) + '</span><input type="number" class="airs-input" min="1" max="100" name="' + ruleField(index, 'priority') + '" value="10"></label><button type="button" class="airs-button airs-button-secondary purio-proactive-toggle-rule" title="' + escapeHtml(strings.collapseAction) + '" aria-expanded="true"><svg class="toggle-icon" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="m18 15-6-6-6 6"></path></svg></button><button type="button" class="airs-button airs-button-secondary purio-proactive-remove-rule" title="' + escapeHtml(strings.removeRule || strings.remove) + '"><svg class="remove-icon" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" aria-hidden="true"><path d="M6 6l12 12"></path><path d="M18 6 6 18"></path></svg></button></div>',
            '</div>',
            '<div class="purio-proactive-rule-body">',
            '<input type="hidden" class="purio-proactive-rule-id" name="' + ruleField(index, 'id') + '" value="' + id + '">',
            '<div class="purio-proactive-rule-grid" style="display:flex;gap:16px;align-items:flex-end;flex-wrap:wrap;">',
            '<label style="flex:1;min-width:160px;"><span class="airs-label">' + labelIcons.trigger + escapeHtml(strings.trigger) + '</span>',
            '<select class="airs-input purio-proactive-trigger" name="' + ruleField(index, 'trigger') + '"><option value="time">' + escapeHtml(strings.triggerTime) + '</option><option value="scroll_depth">' + escapeHtml(strings.triggerScrollDepth) + '</option></select></label>',
            '<label class="purio-proactive-trigger-time" style="flex:1;min-width:150px;"><span class="airs-label">' + labelIcons.after + escapeHtml(strings.after) + '</span>',
            '<span style="display:flex;align-items:center;gap:8px;"><input type="number" class="airs-input" min="1" max="3600" name="' + ruleField(index, 'delay') + '" value="10"><span>' + escapeHtml(strings.seconds) + '</span></span></label>',
            '<label class="purio-proactive-trigger-scroll" style="display:none;flex:1;min-width:150px;"><span class="airs-label">' + escapeHtml(strings.scrollDepth) + '</span>',
            '<span style="display:flex;align-items:center;gap:8px;"><input type="number" class="airs-input" min="1" max="100" name="' + ruleField(index, 'scroll_depth') + '" value="50"><span>%</span></span></label>',
            '<label style="flex:1;min-width:180px;"><span class="airs-label">' + labelIcons.action + escapeHtml(strings.action) + '</span>',
            '<select class="airs-input purio-proactive-action" name="' + ruleField(index, 'action') + '"><option value="mini_chat">' + escapeHtml(strings.miniChat) + '</option><option value="show_bubble">' + escapeHtml(strings.showBubble) + '</option><option value="open_chat">' + escapeHtml(strings.openChat) + '</option></select></label>',
            '<label style="flex:1;min-width:180px;"><span class="airs-label">' + labelIcons.where + escapeHtml(strings.where) + '</span>',
            '<select class="airs-input purio-proactive-scope" name="' + ruleField(index, 'scope') + '"><option value="global">' + escapeHtml(strings.entireSite) + '</option><option value="post_type">' + escapeHtml(strings.contentType) + '</option><option value="specific">' + escapeHtml(strings.specificContent) + '</option></select></label>',
            '</div>',
            '<div class="purio-proactive-message-wrap" style="display:block;margin-top:14px;"><label class="airs-label" for="purio-proactive-message-' + index + '">' + labelIcons.message + escapeHtml(strings.message) + '</label><div class="purio-emoji-field" data-purio-emoji-label="' + escapeHtml(strings.chooseEmoji || '') + '"><textarea id="purio-proactive-message-' + index + '" class="airs-input" rows="2" maxlength="500" name="' + ruleField(index, 'message') + '">' + escapeHtml(strings.defaultMessage || 'Need help? Ask me anything.') + '</textarea></div></div>',
            '<div class="purio-proactive-quick-actions" style="display:none;"><div class="purio-proactive-quick-actions-header"><div><span class="airs-label">' + labelIcons.buttons + escapeHtml(strings.quickActions) + '</span><small style="display:block;color:#666;">' + escapeHtml(strings.quickActionsHelp) + '</small></div><button type="button" class="airs-button airs-button-secondary purio-proactive-add-quick-action"><svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" aria-hidden="true"><path d="M12 5v14"></path><path d="M5 12h14"></path></svg>' + escapeHtml(strings.addQuickAction) + '</button></div><div class="purio-proactive-quick-actions-list"></div></div>',
            '<div class="purio-proactive-post-type-wrap" style="display:none;margin-top:14px;"><label><span class="airs-label">' + escapeHtml(strings.contentType) + '</span><select class="airs-input" name="' + ruleField(index, 'post_type') + '">' + postTypeOptions() + '</select></label></div>',
            '<div class="purio-proactive-specific-wrap" style="display:none;margin-top:14px;"><label><span class="airs-label">' + escapeHtml(strings.specificContent) + '</span><input type="search" class="airs-input purio-proactive-content-search" autocomplete="off" placeholder="' + escapeHtml(strings.searchPlaceholder) + '"></label><div class="purio-proactive-search-results purio-post-reference-results" style="display:none;"></div><div class="purio-proactive-selected-content" style="display:flex;gap:6px;flex-wrap:wrap;margin-top:8px;"></div></div>',
            '</div>',
            '</div>'
        ].join('');
    }

    function updateRule($rule) {
        var trigger = $rule.find('.purio-proactive-trigger').val() || 'time';
        var action = $rule.find('.purio-proactive-action').val();
        var scope = $rule.find('.purio-proactive-scope').val();

        $rule.find('.purio-proactive-trigger-time').toggle(trigger === 'time');
        $rule.find('.purio-proactive-trigger-scroll').toggle(trigger === 'scroll_depth');
        $rule.find('.purio-proactive-quick-actions').toggle(action === 'mini_chat');
        $rule.find('.purio-proactive-post-type-wrap').toggle(scope === 'post_type');
        $rule.find('.purio-proactive-specific-wrap').toggle(scope === 'specific');
    }

    function nextQuickActionIndex($rule) {
        var maximum = -1;

        $rule.find('.purio-proactive-quick-action-row').each(function () {
            maximum = Math.max(maximum, parseInt($(this).attr('data-quick-action-index'), 10) || 0);
        });

        return maximum + 1;
    }

    function buildQuickActionRow(ruleIndex, quickActionIndex) {
        return [
            '<div class="purio-proactive-quick-action-row" data-quick-action-index="' + quickActionIndex + '">',
            '<div class="purio-proactive-quick-action-color-wrap"><button type="button" class="purio-proactive-quick-action-color-swatch" style="background-color:#ebebeb;" title="' + escapeHtml(strings.quickActionColor) + '"></button><input type="hidden" class="purio-proactive-quick-action-color" name="' + quickActionField(ruleIndex, quickActionIndex, 'color') + '" value=""></div>',
            '<input type="text" class="airs-input purio-proactive-quick-action-label" maxlength="80" name="' + quickActionField(ruleIndex, quickActionIndex, 'label') + '" placeholder="' + escapeHtml(strings.quickActionLabel) + '" aria-label="' + escapeHtml(strings.quickActionLabel) + '">',
            '<input type="text" class="airs-input purio-proactive-quick-action-message" maxlength="500" name="' + quickActionField(ruleIndex, quickActionIndex, 'message') + '" placeholder="' + escapeHtml(strings.quickActionMessage) + '" aria-label="' + escapeHtml(strings.quickActionMessage) + '">',
            '<button type="button" class="airs-button airs-button-secondary purio-proactive-remove-quick-action" title="' + escapeHtml(strings.removeQuickAction) + '"><svg class="remove-icon" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" aria-hidden="true"><path d="M6 6l12 12"></path><path d="M18 6 6 18"></path></svg></button>',
            '</div>'
        ].join('');
    }

    function closeQuickActionColorPicker() {
        if ($colorDropdown) {
            $colorDropdown.remove();
            $colorDropdown = null;
        }
        $('.purio-proactive-quick-action-color-swatch').removeClass('picker-open');
        $(document).off('mousedown.purioProactiveColor');
    }

    function initQuickActionColorPickers() {
        if (!$.fn.iris) {
            return;
        }

        $(document).on('click.purioProactiveColorSwatch', '.purio-proactive-quick-action-color-swatch', function (event) {
            event.preventDefault();
            event.stopPropagation();

            var $swatch = $(this);
            var $input = $swatch.siblings('.purio-proactive-quick-action-color');

            if ($swatch.hasClass('picker-open')) {
                closeQuickActionColorPicker();
                return;
            }

            closeQuickActionColorPicker();

            var currentColor = $input.val() || config.defaultColor || '#0073ee';
            var offset = $swatch.offset();
            $colorDropdown = $('<div class="purio-proactive-color-dropdown"></div>').appendTo('body');
            $colorDropdown.css({
                top: offset.top + $swatch.outerHeight() + 6,
                left: offset.left
            });

            var $picker = $('<input type="text">').val(currentColor).appendTo($colorDropdown).hide();
            $picker.iris({
                hide: false,
                width: 220,
                palettes: ['#000000', '#ffffff', '#dd3333', '#dd9933', '#eeee22', '#81d742', '#1e73be', '#8224e3'],
                change: function (pickerEvent, ui) {
                    var color = ui.color.toString();
                    $swatch.css('background-color', color);
                    $input.val(color);
                    $colorDropdown.find('.purio-proactive-color-hex').val(color);
                }
            });

            var $footer = $('<div class="purio-proactive-color-picker-footer"></div>');
            var $reset = $('<button type="button" class="button-link"></button>').text(strings.resetColor || 'Reset');
            var $hex = $('<input type="text" class="airs-input purio-proactive-color-hex" maxlength="7" placeholder="#000000">').val(currentColor);

            $footer.append($reset, $hex);
            $colorDropdown.append($footer);

            $hex.on('input', function () {
                var value = $(this).val();
                if (/^#[0-9a-fA-F]{6}$/.test(value)) {
                    $picker.iris('color', value);
                }
            });

            $reset.on('click', function () {
                $input.val('');
                $swatch.css('background-color', '#ebebeb');
                closeQuickActionColorPicker();
            });

            $swatch.addClass('picker-open');
            $(document).on('mousedown.purioProactiveColor', function (outsideEvent) {
                if (
                    !$(outsideEvent.target).closest('.purio-proactive-color-dropdown').length &&
                    !$(outsideEvent.target).closest('.purio-proactive-quick-action-color-swatch').length
                ) {
                    closeQuickActionColorPicker();
                }
            });
        });
    }

    function nextIndex() {
        var maximum = -1;

        $('.purio-proactive-rule').each(function () {
            maximum = Math.max(maximum, parseInt($(this).attr('data-rule-index'), 10) || 0);
        });

        return maximum + 1;
    }

    function updateRuleTitle($rule, position) {
        var fallbackTitle = getActionTitle(position + 1);
        var customName = $.trim($rule.find('.purio-proactive-rule-name-input').val());

        $rule.find('.purio-proactive-rule-title-text').text(customName || fallbackTitle);
        $rule.find('.purio-proactive-rule-name-input').attr('placeholder', fallbackTitle);
    }

    function renumberRules() {
        $('.purio-proactive-rule').each(function (position) {
            updateRuleTitle($(this), position);
        });
    }

    function finishNameEdit($input) {
        var $rule = $input.closest('.purio-proactive-rule');

        $input.closest('.purio-proactive-rule-name').removeClass('is-editing');
        $rule.find('.purio-proactive-edit-name').attr('aria-expanded', 'false');
        updateRuleTitle($rule, $('.purio-proactive-rule').index($rule));
    }

    function updateRuleLimit() {
        var atLimit = $('.purio-proactive-rule').length >= ruleLimit;
        $('#purio-proactive-add-rule').prop('disabled', atLimit).toggle(!atLimit);
        $('#purio-proactive-upgrade-action').toggle(atLimit);
    }

    function updatePriorityVisibility() {
        var showPriority = $('.purio-proactive-display-mode').val() === 'highest_priority';

        $('.airs-card[data-chat-section="proactive-actions"]').toggleClass(
            'purio-proactive-priority-enabled',
            showPriority
        );
    }

    function getCollapsedActionIds() {
        var prefix = collapsedActionsCookie + '=';
        var cookies = document.cookie ? document.cookie.split(';') : [];

        for (var i = 0; i < cookies.length; i++) {
            var cookie = $.trim(cookies[i]);
            if (cookie.indexOf(prefix) !== 0) continue;

            try {
                var ids = JSON.parse(decodeURIComponent(cookie.substring(prefix.length)));
                return Array.isArray(ids) ? ids.map(String) : [];
            } catch (error) {
                return [];
            }
        }

        return [];
    }

    function saveCollapsedActionIds(ids) {
        var uniqueIds = [];

        $.each(ids, function (_, id) {
            id = String(id || '');
            if (id && uniqueIds.indexOf(id) === -1) {
                uniqueIds.push(id);
            }
        });

        document.cookie = collapsedActionsCookie + '=' +
            encodeURIComponent(JSON.stringify(uniqueIds)) +
            '; max-age=31536000; path=/; SameSite=Lax' +
            (window.location.protocol === 'https:' ? '; Secure' : '');
    }

    function getRuleId($rule) {
        return String($rule.find('.purio-proactive-rule-id').val() || '');
    }

    function setRuleCollapsed($rule, collapsed, persist) {
        var $button = $rule.find('.purio-proactive-toggle-rule').first();

        $rule.toggleClass('is-collapsed', collapsed);
        $button
            .attr('aria-expanded', collapsed ? 'false' : 'true')
            .attr('title', collapsed ? strings.expandAction : strings.collapseAction);

        if (!persist) return;

        var ruleId = getRuleId($rule);
        var ids = getCollapsedActionIds().filter(function (id) {
            return id !== ruleId;
        });

        if (collapsed && ruleId) {
            ids.push(ruleId);
        }
        saveCollapsedActionIds(ids);
    }

    function restoreCollapsedActions() {
        var collapsedIds = getCollapsedActionIds();

        $('.purio-proactive-rule').each(function () {
            var $rule = $(this);
            setRuleCollapsed(
                $rule,
                collapsedIds.indexOf(getRuleId($rule)) !== -1,
                false
            );
        });
    }

    function addSelectedContent($rule, item) {
        var id = parseInt(item.id, 10);
        var index = $rule.attr('data-rule-index');
        var $selected = $rule.find('.purio-proactive-selected-content');

        if (!id || $selected.find('[data-id="' + id + '"]').length) {
            return;
        }

        $selected.append(
            '<span class="purio-proactive-content-chip purio-post-reference-selected-text" data-id="' + id + '">' +
                '<strong>' + escapeHtml(item.title) + '</strong>' +
                '<span style="color:#999;">(ID: ' + id + ')</span>' +
                '<span class="post-reference-remove purio-proactive-remove-content" style="cursor:pointer;color:#999;margin-left:4px;" title="' + escapeHtml(strings.remove) + '">×</span>' +
                '<input type="hidden" name="' + ruleField(index, 'content_ids') + '[' + id + ']" value="' + id + '">' +
            '</span>'
        );
    }

    function renderContentSearchResults($results, results, resultClass) {
        if (!results.length) {
            $results.html('<div style="padding:8px 10px;color:#666;">' + escapeHtml(strings.noResults) + '</div>').show();
            return;
        }

        var html = '';
        $.each(results, function (_, item) {
            var id = parseInt(item.id, 10);
            html += '<div class="post-reference-item ' + resultClass + '" data-id="' + id + '" data-title="' + escapeHtml(item.title) + '" style="display:flex;justify-content:space-between;align-items:center;padding:7px 10px;cursor:pointer;border-bottom:1px solid #f0f0f0;transition:background 0.15s;gap:10px;">';
            html += '<div style="display:flex;align-items:baseline;gap:6px;flex:1;min-width:0;">';
            html += '<div style="font-weight:500;font-size:14px;white-space:nowrap;overflow:hidden;text-overflow:ellipsis;">' + escapeHtml(item.title) + '</div>';
            html += '<div style="flex:none;font-size:12px;color:#999;white-space:nowrap;">ID: ' + id + '</div>';
            html += '</div>';
            html += '<span style="font-size:11px;background:#f5f5f5;color:#666;padding:2px 8px;border-radius:4px;white-space:nowrap;">' + escapeHtml(item.type) + '</span>';
            html += '</div>';
        });

        $results.html(html).show();
    }

    function renderSearchResults($input, results) {
        renderContentSearchResults(
            $input.closest('.purio-proactive-rule').find('.purio-proactive-search-results'),
            results,
            'purio-proactive-search-result'
        );
    }

    function addWhitelistedContent($wrap, item) {
        var id = parseInt(item.id, 10);
        var $selected = $wrap.find('.purio-floating-whitelist-selected');

        if (!id || $selected.find('[data-id="' + id + '"]').length) {
            return;
        }

        $selected.append(
            '<span class="purio-floating-whitelist-chip purio-post-reference-selected-text" data-id="' + id + '">' +
                '<strong>' + escapeHtml(item.title) + '</strong>' +
                '<span style="color:#999;">(ID: ' + id + ')</span>' +
                '<span class="post-reference-remove purio-floating-whitelist-remove" style="cursor:pointer;color:#999;margin-left:4px;" title="' + escapeHtml(strings.remove) + '">×</span>' +
                '<input type="hidden" name="listeo_ai_floating_whitelisted_pages[' + id + ']" value="' + id + '">' +
            '</span>'
        );
    }

    $(function () {
        $('.purio-proactive-rule').each(function () {
            updateRule($(this));
        });
        initEmojiPickers($('#purio-proactive-rules'));
        initQuickActionColorPickers();
        renumberRules();
        updateRuleLimit();
        updatePriorityVisibility();
        restoreCollapsedActions();

        $(document).on('change', '.purio-proactive-trigger, .purio-proactive-action, .purio-proactive-scope', function () {
            updateRule($(this).closest('.purio-proactive-rule'));
        });

        $(document).on('change', '.purio-proactive-display-mode', updatePriorityVisibility);

        $('#purio-proactive-add-rule').on('click', function () {
            if ($('.purio-proactive-rule').length >= ruleLimit) {
                return;
            }

            var $rule = $(buildRule(nextIndex()));
            $('#purio-proactive-rules').append($rule);
            updateRule($rule);
            initEmojiPickers($rule);
            renumberRules();
            updateRuleLimit();
        });

        $(document).on('click', '.purio-proactive-remove-rule', function () {
            closeQuickActionColorPicker();
            var $rule = $(this).closest('.purio-proactive-rule');
            setRuleCollapsed($rule, false, true);
            $rule.remove();
            renumberRules();
            updateRuleLimit();
        });

        $(document).on('click', '.purio-proactive-toggle-rule', function () {
            var $rule = $(this).closest('.purio-proactive-rule');
            var collapsed = !$rule.hasClass('is-collapsed');

            setRuleCollapsed($rule, collapsed, true);
        });

        $(document).on('click', '.purio-proactive-edit-name', function () {
            var $button = $(this);
            var $editor = $button.closest('.purio-proactive-rule-name');
            var $input = $editor.find('.purio-proactive-rule-name-input');

            $input.data('edit-start-value', $input.val());
            $editor.addClass('is-editing');
            $button.attr('aria-expanded', 'true');
            $input.trigger('focus');
            $input[0].select();
        });

        $(document).on('blur', '.purio-proactive-rule-name-input', function () {
            finishNameEdit($(this));
        });

        $(document).on('keydown', '.purio-proactive-rule-name-input', function (event) {
            if (event.key === 'Enter') {
                event.preventDefault();
                $(this).trigger('blur');
            } else if (event.key === 'Escape') {
                event.preventDefault();
                $(this).val($(this).data('edit-start-value'));
                $(this).trigger('blur');
            }
        });

        $(document).on('click', '.purio-proactive-remove-content', function () {
            $(this).closest('.purio-proactive-content-chip').remove();
        });

        $(document).on('click', '.purio-floating-whitelist-remove', function () {
            $(this).closest('.purio-floating-whitelist-chip').remove();
        });

        $(document).on('click', '.purio-proactive-add-quick-action', function () {
            var $rule = $(this).closest('.purio-proactive-rule');
            var ruleIndex = $rule.attr('data-rule-index');
            var quickActionIndex = nextQuickActionIndex($rule);
            $rule.find('.purio-proactive-quick-actions-list').append(
                buildQuickActionRow(ruleIndex, quickActionIndex)
            );
        });

        $(document).on('click', '.purio-proactive-remove-quick-action', function () {
            closeQuickActionColorPicker();
            $(this).closest('.purio-proactive-quick-action-row').remove();
        });

        $(document).on('input', '.purio-proactive-content-search', function () {
            var $input = $(this);
            var query = $.trim($input.val());

            clearTimeout(searchTimer);
            if (query.length < 2) {
                $input.closest('.purio-proactive-rule').find('.purio-proactive-search-results').hide().empty();
                return;
            }

            searchTimer = setTimeout(function () {
                $.get(config.ajaxUrl, {
                    action: 'listeo_ai_proactive_search_content',
                    nonce: config.nonce,
                    query: query
                }).done(function (response) {
                    renderSearchResults($input, response && response.success ? response.data : []);
                });
            }, 250);
        });

        $(document).on('click', '.purio-proactive-search-result', function () {
            var $result = $(this);
            var $rule = $result.closest('.purio-proactive-rule');

            addSelectedContent($rule, {
                id: $result.attr('data-id'),
                title: $result.attr('data-title')
            });
            $rule.find('.purio-proactive-content-search').val('');
            $rule.find('.purio-proactive-search-results').hide().empty();
        });

        $(document).on('input', '.purio-floating-whitelist-search', function () {
            var $input = $(this);
            var query = $.trim($input.val());
            var $results = $input.closest('.purio-floating-whitelist-wrap').find('.purio-floating-whitelist-results');

            clearTimeout(whitelistSearchTimer);
            if (query.length < 2) {
                $results.hide().empty();
                return;
            }

            whitelistSearchTimer = setTimeout(function () {
                $.get(config.ajaxUrl, {
                    action: 'listeo_ai_proactive_search_content',
                    nonce: config.nonce,
                    query: query
                }).done(function (response) {
                    renderContentSearchResults(
                        $results,
                        response && response.success ? response.data : [],
                        'purio-floating-whitelist-search-result'
                    );
                });
            }, 250);
        });

        $(document).on('click', '.purio-floating-whitelist-search-result', function () {
            var $result = $(this);
            var $wrap = $result.closest('.purio-floating-whitelist-wrap');

            addWhitelistedContent($wrap, {
                id: $result.attr('data-id'),
                title: $result.attr('data-title')
            });
            $wrap.find('.purio-floating-whitelist-search').val('');
            $wrap.find('.purio-floating-whitelist-results').hide().empty();
        });

        $(document).on('mouseenter', '.purio-proactive-search-result, .purio-floating-whitelist-search-result', function () {
            $(this).css('background', '#f7f7f7');
        }).on('mouseleave', '.purio-proactive-search-result, .purio-floating-whitelist-search-result', function () {
            $(this).css('background', '');
        });
    });
})(jQuery);
