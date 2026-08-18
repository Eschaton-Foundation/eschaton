/**
 * Floating button style controls and animated avatar preview.
 */
(function ($) {
    'use strict';

    function init() {
        var $styleToggle = $('.airs-floating-button-style-toggle');
        if (!$styleToggle.length) return;

        var $styleButtons = $styleToggle.find('.airs-floating-button-style-btn');
        var $styleInput = $('#listeo_ai_floating_button_style');
        var $simplePanel = $('#airs-floating-button-simple-panel');
        var $animatedPanel = $('#airs-floating-button-animated-panel');
        var $avatarStyleButtons = $('.airs-floating-avatar-style-toggle')
            .not('.airs-floating-speed-toggle')
            .find('.airs-floating-avatar-style-btn');
        var $avatarStyleInput = $('#listeo_ai_floating_animated_avatar_style');
        var $avatarColorInput = $('#listeo_ai_floating_animated_avatar_color');
        var $speedButtons = $('.airs-floating-speed-btn');
        var $speedInput = $('#listeo_ai_floating_animated_speed');
        var $buttonColorInput = $('#listeo_ai_floating_button_color');
        var $customIconSizeInput = $('#listeo_ai_floating_custom_icon_size');
        var $welcomeBubbleInput = $('#listeo_ai_floating_welcome_bubble');
        var $welcomeBubblePreview = $('#airs-floating-welcome-bubble-preview');
        var $welcomeSimplePreview = $('.airs-floating-welcome-simple-button-preview');
        var $welcomePreviewStage = $('.airs-floating-widget-welcome-preview-stage');
        var $widgetEnabledInput = $('input[name="listeo_ai_floating_chat_enabled"]');
        var $widgetPositionInput = $('#listeo_ai_floating_position');
        var preview = document.getElementById('airs-floating-avatar-preview');
        var welcomeAnimatedPreview = document.getElementById('airs-floating-welcome-animated-button-preview');

        function createAnimatedButtonPreview(size) {
            var wrapper = document.createElement('span');
            var iconSource = document.getElementById('listeo-animated-icon-source');
            var icon = document.createElement('img');
            var hasCustomIcon = Boolean($('#listeo_ai_floating_animated_icon').val());
            var configuredSize = parseInt($('#listeo_ai_floating_animated_icon_size').val(), 10) || 28;
            var iconSize = hasCustomIcon
                ? Math.min(size * 0.63, configuredSize * size / 60)
                : 28;

            wrapper.className = 'airs-animated-button-preview';
            wrapper.style.width = size + 'px';
            wrapper.style.height = size + 'px';
            wrapper.appendChild(window.PurioAvatar.create({
                style: $avatarStyleInput.val() || 'flare',
                speed: $speedInput.val() || 'normal',
                color: $avatarColorInput.val() || '#006aff',
                size: size
            }));

            if (iconSource) {
                icon.src = iconSource.src;
                icon.alt = '';
                icon.className = 'airs-animated-button-preview-icon';
                icon.style.width = iconSize + 'px';
                icon.style.height = iconSize + 'px';
                wrapper.appendChild(icon);
            }

            return wrapper;
        }

        function renderAvatarPreview() {
            if (typeof window.PurioAvatar === 'undefined') return;

            if (preview) {
                preview.innerHTML = '';
                preview.appendChild(createAnimatedButtonPreview(60));
            }

            if (welcomeAnimatedPreview) {
                welcomeAnimatedPreview.innerHTML = '';
                welcomeAnimatedPreview.appendChild(createAnimatedButtonPreview(60));
            }

        }

        function showPanel(value, animate) {
            $simplePanel.stop(true, true);
            $animatedPanel.stop(true, true);

            function hidePanel($panel) {
                $panel.animate({ opacity: 0 }, { duration: 200, queue: false });
                $panel.slideUp(200);
            }

            function revealPanel($panel) {
                $panel.css('opacity', 0).slideDown(200);
                $panel.animate({ opacity: 1 }, { duration: 200, queue: false });
            }

            if (value === 'animated') {
                renderAvatarPreview();
                $welcomeSimplePreview.hide();
                $(welcomeAnimatedPreview).show();
                animate ? hidePanel($simplePanel) : $simplePanel.hide();
                animate ? revealPanel($animatedPanel) : $animatedPanel.show();
            } else {
                $(welcomeAnimatedPreview).hide();
                $welcomeSimplePreview.show();
                animate ? hidePanel($animatedPanel) : $animatedPanel.hide();
                animate ? revealPanel($simplePanel) : $simplePanel.show();
            }
        }

        function updateSimplePreview() {
            var color = $buttonColorInput.val() || '#222222';
            $('#listeo-custom-icon-preview .airs-media-placeholder')
                .css('background-color', color);
            $welcomeSimplePreview.css('background-color', color);

            var $sourceIcon = $('#listeo-custom-icon-preview .airs-media-placeholder').children().first();
            if ($sourceIcon.length) {
                $welcomeSimplePreview.empty().append($sourceIcon.clone().removeAttr('id'));
            }
        }

        function updateWelcomeBubblePreview() {
            var value = $welcomeBubbleInput.val() || '';
            $welcomeBubblePreview.toggleClass('is-empty', $.trim(value) === '');
            $welcomeBubblePreview.find('.listeo-floating-welcome-bubble-content').text(value);
        }

        function updateWidgetPreview() {
            var position = $widgetPositionInput.val() === 'left' ? 'left' : 'right';

            $welcomePreviewStage
                .toggle($widgetEnabledInput.is(':checked'))
                .toggleClass('is-left', position === 'left')
                .toggleClass('is-right', position === 'right');
        }

        $styleButtons.on('click', function (event) {
            event.preventDefault();
            var value = $(this).data('value');

            $styleButtons.removeClass('active');
            $(this).addClass('active');
            $styleInput.val(value);
            showPanel(value, true);
        });

        $avatarStyleButtons.on('click', function (event) {
            event.preventDefault();
            $avatarStyleButtons.removeClass('active');
            $(this).addClass('active');
            $avatarStyleInput.val($(this).data('value'));
            renderAvatarPreview();
        });

        $speedButtons.on('click', function (event) {
            event.preventDefault();
            $speedButtons.removeClass('active');
            $(this).addClass('active');
            $speedInput.val($(this).data('value'));
            renderAvatarPreview();
        });

        $avatarColorInput.on('input change colorpickerchange', renderAvatarPreview);
        $buttonColorInput.on('input change colorpickerchange', updateSimplePreview);
        $customIconSizeInput.on('input change', updateSimplePreview);
        $welcomeBubbleInput.on('input change', updateWelcomeBubblePreview);
        $widgetEnabledInput.on('change', updateWidgetPreview);
        $widgetPositionInput.on('change', updateWidgetPreview);
        $(document).on('purio-floating-icon-changed', function () {
            renderAvatarPreview();
            updateSimplePreview();
        });

        showPanel($styleInput.val() || 'simple', false);
        updateSimplePreview();
        updateWelcomeBubblePreview();
        updateWidgetPreview();
    }

    $(document).ready(init);
})(jQuery);
