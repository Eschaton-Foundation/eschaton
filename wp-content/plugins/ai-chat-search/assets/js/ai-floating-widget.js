/**
 * AI Chat Floating Widget JavaScript
 *
 * Handles floating button toggle, welcome bubble dismissal, popup management,
 * and optional lazy loading of chatbot scripts.
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
            this.scriptsLoaded = false;

            // Lazy load config from PHP
            this.lazyScripts = (typeof listeoAiFloatingChatConfig !== 'undefined' && listeoAiFloatingChatConfig.lazyScripts) ? listeoAiFloatingChatConfig.lazyScripts : [];
            this.scriptVersion = (typeof listeoAiFloatingChatConfig !== 'undefined' && listeoAiFloatingChatConfig.scriptVersion) ? listeoAiFloatingChatConfig.scriptVersion : '';

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

            debugLog('Widget initialized', this.lazyScripts.length > 0 ? '(lazy load enabled)' : '');
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
                this.chatInitialized = true;

                if (this.lazyScripts.length > 0 && !this.scriptsLoaded) {
                    this.lazyLoadAndInit();
                } else {
                    this.initializeChat();
                }
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
         * Lazy load chatbot scripts then initialize
         * Shows a loading indicator while scripts are being fetched
         */
        lazyLoadAndInit() {
            var self = this;
            var chatWrapper = document.getElementById('listeo-floating-chat-instance');

            // Check if chatbot core was already loaded (e.g. shortcode on same page)
            if (document.querySelector('script[src*="ai-chatbot-core"]')) {
                debugLog('[Floating Chat] Scripts already loaded, skipping lazy load');
                this.scriptsLoaded = true;
                this.initializeChat();
                return;
            }

            // Hide all chat content and show only loading indicator
            if (chatWrapper) {
                chatWrapper.classList.add('listeo-ai-chat-lazy-state');
            }

            debugLog('[Floating Chat] Lazy loading scripts:', this.lazyScripts);

            // Build URLs with version parameter
            var ver = this.scriptVersion;
            var urls = this.lazyScripts.map(function(url) {
                return url + (url.indexOf('?') === -1 ? '?' : '&') + 'ver=' + ver;
            });

            // Load scripts sequentially (core must load first)
            this.loadScriptsSequential(urls, 0, function() {
                self.scriptsLoaded = true;
                debugLog('[Floating Chat] All scripts loaded');

                // ai-chatbot-core.js uses $(document).ready() which fires on next tick
                // since DOM is already ready — give jQuery a moment to process
                setTimeout(function() {
                    if (chatWrapper) {
                        chatWrapper.classList.remove('listeo-ai-chat-lazy-state');
                    }
                    self.initializeChat();
                }, 50);
            });
        }

        /**
         * Load an array of script URLs one after another
         */
        loadScriptsSequential(urls, index, callback) {
            if (index >= urls.length) {
                callback();
                return;
            }

            var script = document.createElement('script');
            script.src = urls[index];
            script.onload = () => {
                debugLog('[Floating Chat] Loaded:', urls[index]);
                this.loadScriptsSequential(urls, index + 1, callback);
            };
            script.onerror = () => {
                console.error('[AI Chat] Failed to load:', urls[index]);
                this.loadScriptsSequential(urls, index + 1, callback);
            };
            document.body.appendChild(script);
        }

        /**
         * Initialize chat functionality
         * This reuses the existing ai-chatbot-core.js logic but for the floating instance
         */
        initializeChat() {
            const chatId = 'listeo-floating-chat-instance';

            // Add a small delay to ensure ai-chatbot-core.js has loaded
            setTimeout(() => {
                // Trigger a custom event that ai-chatbot-core.js can listen to
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
