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
        var preview = document.getElementById('airs-floating-avatar-preview');

        function createAnimatedButtonPreview(size, compact) {
            var wrapper = document.createElement('span');
            var iconSource = document.getElementById('listeo-animated-icon-source');
            var icon = document.createElement('img');
            var hasCustomIcon = Boolean($('#listeo_ai_floating_animated_icon').val());
            var configuredSize = parseInt($('#listeo_ai_floating_animated_icon_size').val(), 10) || 28;
            var iconSize = hasCustomIcon
                ? Math.min(size * 0.63, configuredSize * size / 60)
                : (compact ? 16 : 28);

            wrapper.className = 'airs-animated-button-preview' + (compact ? ' is-compact' : '');
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
            if (!preview || typeof window.PurioAvatar === 'undefined') return;

            preview.innerHTML = '';
            preview.appendChild(createAnimatedButtonPreview(60, false));

            var togglePreview = document.querySelector('.airs-floating-button-preview-animated');
            if (togglePreview) {
                togglePreview.innerHTML = '';
                togglePreview.appendChild(createAnimatedButtonPreview(32, true));
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
                animate ? hidePanel($simplePanel) : $simplePanel.hide();
                animate ? revealPanel($animatedPanel) : $animatedPanel.show();
            } else {
                animate ? hidePanel($animatedPanel) : $animatedPanel.hide();
                animate ? revealPanel($simplePanel) : $simplePanel.show();
            }
        }

        function updateSimplePreview() {
            var color = $buttonColorInput.val() || '#222222';
            $('.airs-floating-button-preview-simple, #listeo-custom-icon-preview .airs-media-placeholder')
                .css('background-color', color);
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
        $(document).on('purio-floating-icon-changed', renderAvatarPreview);

        showPanel($styleInput.val() || 'simple', false);
        updateSimplePreview();
    }

    $(document).ready(init);
})(jQuery);
