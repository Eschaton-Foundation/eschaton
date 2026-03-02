/**
 * AI Chat Floating Widget JavaScript
 *
 * Handles floating button toggle, welcome bubble dismissal, and popup management
 *
 * @package Listeo_AI_Search
 * @since 1.0.0
 */

(function($) {
    'use strict';

    /**
     * Debug logging helper - only logs when debug mode is enabled
     */
    const debugLog = function(...args) {
        if (typeof listeoAiChatConfig !== 'undefined' && listeoAiChatConfig.debugMode) {
            console.log('[AI Chat Widget]', ...args);
        }
    };

    /**
     * Floating Chat Widget Manager
     */
    class ListeoFloatingChatWidget {
        constructor() {
            this.button = $('#listeo-floating-chat-button');
            this.popup = $('#listeo-floating-chat-popup');
            this.welcomeBubble = $('#listeo-floating-welcome-bubble');
            this.iconOpen = $('.listeo-floating-icon-open');
            this.iconClose = $('.listeo-floating-icon-close');
            this.isOpen = false;
            this.chatInitialized = false;

            // LocalStorage keys
            this.STORAGE_KEY_BUBBLE_DISMISSED = 'listeo_floating_chat_bubble_dismissed';

            this.init();
        }

        /**
         * Initialize widget
         */
        init() {
            // Check if welcome bubble should be shown
            this.checkWelcomeBubbleStatus();

            // Bind events
            this.bindEvents();

            debugLog('Widget initialized');
        }

        /**
         * Check and show/hide welcome bubble based on localStorage
         */
        checkWelcomeBubbleStatus() {
            const bubbleDismissed = localStorage.getItem(this.STORAGE_KEY_BUBBLE_DISMISSED);

            if (bubbleDismissed === 'true') {
                this.welcomeBubble.addClass('hidden');
            } else {
                // Show welcome bubble with animation
                this.welcomeBubble.removeClass('hidden');
            }
        }

        /**
         * Bind event handlers
         */
        bindEvents() {
            // Toggle chat on button click
            this.button.on('click', (e) => {
                e.preventDefault();
                this.toggleChat();
            });

            // Dismiss welcome bubble when clicking anywhere on it
            this.welcomeBubble.on('click', (e) => {
                e.stopPropagation();
                this.dismissWelcomeBubble();
            });

            // Close popup on Escape key
            $(document).on('keydown', (e) => {
                if (e.key === 'Escape' && this.isOpen) {
                    this.closeChat();
                }
            });
        }

        /**
         * Toggle chat open/closed
         */
        toggleChat() {
            if (this.isOpen) {
                this.closeChat();
            } else {
                this.openChat();
            }
        }

        /**
         * Open chat popup
         */
        openChat() {
            // Hide welcome bubble and mark as dismissed
            this.dismissWelcomeBubble();

            // Resume animated background if present
            if (typeof ListeoSilkWave !== 'undefined') {
                ListeoSilkWave.start();
            }

            // Show popup
            this.popup.fadeIn(300, () => {
                // Trigger layout recalculation for themes with complex fixed containers
                window.dispatchEvent(new Event('resize'));

                // Scroll to bottom AFTER popup is fully visible
                this.scrollToBottom();
            });

            // Toggle icons
            this.iconOpen.hide();
            this.iconClose.show();

            // Update state
            this.isOpen = true;

            // Initialize chat if not already done
            if (!this.chatInitialized) {
                this.initializeChat();
                this.chatInitialized = true;
            }

            debugLog('[Floating Chat] Chat opened');
        }

        /**
         * Scroll chat messages to bottom
         */
        scrollToBottom() {
            const $messagesContainer = $('#listeo-floating-chat-instance-messages');
            if ($messagesContainer.length > 0 && $messagesContainer[0].scrollHeight) {
                $messagesContainer.scrollTop($messagesContainer[0].scrollHeight);
                debugLog('[Floating Chat] Scrolled to bottom');
            }
        }

        /**
         * Close chat popup
         */
        closeChat() {
            // Hide popup
            this.popup.fadeOut(300);

            // Pause animated background to save CPU
            if (typeof ListeoSilkWave !== 'undefined') {
                ListeoSilkWave.stop();
            }

            // Toggle icons
            this.iconClose.hide();
            this.iconOpen.show();

            // Update state
            this.isOpen = false;

            debugLog('[Floating Chat] Chat closed');
        }

        /**
         * Dismiss welcome bubble permanently
         */
        dismissWelcomeBubble() {
            this.welcomeBubble.fadeOut(200, () => {
                this.welcomeBubble.addClass('hidden');
            });

            // Save to localStorage
            localStorage.setItem(this.STORAGE_KEY_BUBBLE_DISMISSED, 'true');

            debugLog('[Floating Chat] Welcome bubble dismissed');
        }

        /**
         * Initialize chat functionality
         * This reuses the existing ai-chat-core.js logic but for the floating instance
         */
        initializeChat() {
            const chatId = 'listeo-floating-chat-instance';
            const messagesContainer = $(`#${chatId}-messages`);
            const inputField = $(`#${chatId}-input`);
            const sendButton = $(`#${chatId}-send`);
            const clearButton = $('.listeo-ai-chat-clear-btn', this.popup);

            // Check if ai-chat-core.js has initialized the chat instance
            // The ai-chat-core.js should auto-detect and initialize any .listeo-ai-chat-wrapper
            // If not, we need to manually trigger initialization

            // Add a small delay to ensure ai-chat-core.js has loaded
            setTimeout(() => {
                // Trigger a custom event that ai-chat-core.js can listen to
                $(document).trigger('listeo-floating-chat-ready', { chatId: chatId });

                debugLog('[Floating Chat] Chat instance initialized');
            }, 100);
        }
    }

    /**
     * Initialize on document ready
     */
    $(document).ready(function() {
        // Only initialize if widget exists on page
        if ($('#listeo-floating-chat-widget').length > 0) {
            new ListeoFloatingChatWidget();
        }
    });

})(jQuery);
