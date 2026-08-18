/**
 * Reusable emoji picker for admin text fields.
 *
 * @package AI_Chat_Search
 */

(function ($) {
    'use strict';

    var config = window.purioEmojiPickerConfig || {};
    var emojis = [
        '😀', '😃', '😊', '🙂', '😉', '😍',
        '👋', '👍', '👏', '🙏', '💬', '❓',
        '❤️', '⭐', '✨', '🎉', '🔥', '💡',
        '📍', '🏠', '🏨', '🍽️', '🛍️', '📅',
        '✅', '🎁', '🚀', '📞', '📧', '🔔'
    ];

    function escapeHtml(value) {
        return $('<div>').text(value == null ? '' : String(value)).html();
    }

    function buildTools(label) {
        var options = '';

        $.each(emojis, function (_, emoji) {
            options += '<button type="button" class="purio-emoji-option" data-emoji="' + escapeHtml(emoji) + '" title="' + escapeHtml(emoji) + '">' + escapeHtml(emoji) + '</button>';
        });

        return '<div class="purio-emoji-tools">' +
            '<button type="button" class="purio-emoji-toggle" title="' + escapeHtml(label) + '" aria-label="' + escapeHtml(label) + '" aria-expanded="false">😊</button>' +
            '<div class="purio-emoji-picker">' + options + '</div>' +
        '</div>';
    }

    function init(context) {
        var $context = context ? $(context) : $(document);
        var $fields = $context.is('.purio-emoji-field')
            ? $context
            : $context.find('.purio-emoji-field');

        $fields.each(function () {
            var $field = $(this);
            if ($field.find('.purio-emoji-tools').length) {
                return;
            }

            var label = $field.attr('data-purio-emoji-label') || config.chooseEmoji || '';
            $field.append(buildTools(label));
        });
    }

    function closePickers() {
        $('.purio-emoji-picker').removeClass('is-open');
        $('.purio-emoji-toggle').attr('aria-expanded', 'false');
    }

    function insertEmoji(field, emoji) {
        var start = typeof field.selectionStart === 'number'
            ? field.selectionStart
            : field.value.length;
        var end = typeof field.selectionEnd === 'number'
            ? field.selectionEnd
            : start;

        field.value = field.value.substring(0, start) + emoji + field.value.substring(end);
        field.focus();
        field.selectionStart = field.selectionEnd = start + emoji.length;
        $(field).trigger('input');
    }

    $(document).on('click', '.purio-emoji-toggle', function (event) {
        event.preventDefault();
        event.stopPropagation();

        var $toggle = $(this);
        var $picker = $toggle.next('.purio-emoji-picker');
        var open = !$picker.hasClass('is-open');

        closePickers();
        if (open) {
            $picker.addClass('is-open');
            $toggle.attr('aria-expanded', 'true');
        }
    });

    $(document).on('click', '.purio-emoji-option', function (event) {
        event.preventDefault();
        event.stopPropagation();

        var field = $(this).closest('.purio-emoji-field').find('textarea, input[type="text"]').first().get(0);
        if (field) {
            insertEmoji(field, $(this).attr('data-emoji') || '');
        }
        closePickers();
    });

    $(document).on('click', function (event) {
        if (!$(event.target).closest('.purio-emoji-tools').length) {
            closePickers();
        }
    });

    window.PurioEmojiPicker = {
        init: init
    };

    $(function () {
        init(document);
    });
})(jQuery);
