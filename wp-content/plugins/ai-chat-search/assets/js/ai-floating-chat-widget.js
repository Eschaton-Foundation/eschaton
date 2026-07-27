(function () {
    'use strict';

    function getStorageNamespace() {
        var floatingConfig = (typeof listeoAiFloatingChatConfig !== 'undefined')
            ? listeoAiFloatingChatConfig
            : {};
        var chatConfig = (typeof listeoAiChatConfig !== 'undefined')
            ? listeoAiChatConfig
            : {};
        var namespace = chatConfig.storageNamespace || floatingConfig.storageNamespace || '';

        return namespace ? String(namespace) : '';
    }

    function getScopedStorageKey(key) {
        var namespace = getStorageNamespace();
        return namespace ? key + '_' + namespace : key;
    }

    var STORAGE_KEY_BUBBLE_DISMISSED = getScopedStorageKey(
        'listeo_floating_chat_bubble_dismissed'
    );
    var STORAGE_KEY_CHAT_OPENED = getScopedStorageKey('listeo_floating_chat_opened');
    var STORAGE_KEY_USER_INTERACTED = getScopedStorageKey(
        'listeo_floating_chat_interacted'
    );
    var STORAGE_KEY_PROACTIVE_COOLDOWN = getScopedStorageKey(
        'listeo_floating_proactive_cooldown'
    );
    var FADE_DURATION_MS = 300;
    var MINI_CHAT_ANIMATION_MS = 220;

    function debugLog() {
        if (typeof listeoAiChatConfig !== 'undefined' && listeoAiChatConfig.debugMode) {
            var args = Array.prototype.slice.call(arguments);
            args.unshift('[AI Chat Widget]');
            console.log.apply(console, args);
        }
    }

    function dispatchReady(chatId) {
        if (typeof window.jQuery !== 'undefined') {
            try {
                window.jQuery(document).trigger('listeo-floating-chat-ready', { chatId: chatId });
            } catch (e) {}
        }
        try {
            document.dispatchEvent(new CustomEvent('listeo-floating-chat-ready', {
                detail: { chatId: chatId }
            }));
        } catch (e) {}
    }

    function dispatchWidgetState(popup, eventName, chatId) {
        if (!popup) return;
        try {
            popup.dispatchEvent(new CustomEvent(eventName, {
                bubbles: true,
                detail: { chatId: chatId }
            }));
        } catch (e) {}
    }

    function ListeoFloatingChatWidget() {
        this.button = document.getElementById('listeo-floating-chat-button');
        this.popup = document.getElementById('listeo-floating-chat-popup');
        this.bubbleStack = document.getElementById('listeo-floating-bubble-stack');
        this.welcomeBubble = document.getElementById('listeo-floating-welcome-bubble');
        this.iconOpen = document.querySelector('.listeo-floating-icon-open');
        this.iconClose = document.querySelector('.listeo-floating-icon-close');
        this.isOpen = false;
        this.chatInitialized = false;
        this.scriptsLoaded = false;
        this.closeTimeoutId = null;
        this.proactiveTimeoutIds = [];
        this.proactiveScrollActions = [];
        this.proactiveScrollHandler = null;
        this.proactiveActionQueue = [];
        this.proactiveQueueSequence = 0;
        this.proactiveQueueProcessTimeoutId = null;
        this.activeProactiveActionId = '';
        this.activeProactiveActionType = '';
        this.pendingProactiveMessages = [];
        this.proactiveMessageTimeoutId = null;
        this.proactiveMessageAttempts = 0;
        this.miniChat = null;
        this.miniChatMessage = null;
        this.miniChatQuickActions = null;
        this.miniChatInput = null;
        this.miniChatCloseTimeoutId = null;
        this.pendingMagicQuestion = null;
        this.magicQuestionTimeoutId = null;
        this.magicQuestionAttempts = 0;

        var cfg = (typeof listeoAiFloatingChatConfig !== 'undefined') ? listeoAiFloatingChatConfig : {};
        this.lazyScripts = (cfg && cfg.lazyScripts) ? cfg.lazyScripts : [];
        this.scriptVersion = (cfg && cfg.scriptVersion) ? cfg.scriptVersion : '';
        this.proactiveActions = (cfg && Array.isArray(cfg.proactiveActions))
            ? cfg.proactiveActions
            : ((cfg && cfg.proactiveAction) ? [cfg.proactiveAction] : []);
        this.proactiveStrings = (cfg && cfg.proactiveStrings) ? cfg.proactiveStrings : {};
        var proactiveSettings = (cfg && cfg.proactiveSettings)
            ? cfg.proactiveSettings
            : {};
        var configuredCooldown = proactiveSettings.interaction_cooldown ||
            cfg.proactiveCooldown;
        var configuredDisplayMode = proactiveSettings.display_mode ||
            cfg.proactiveDisplayMode;
        this.proactiveCooldown = [
            'none',
            'session',
            '30_minutes',
            '1_hour',
            '24_hours',
            '7_days'
        ].indexOf(String(configuredCooldown || '')) !== -1
            ? String(configuredCooldown)
            : 'session';
        this.proactiveDisplayMode = String(configuredDisplayMode || '') === 'highest_priority'
            ? 'highest_priority'
            : 'all';
        this.hasUserInteracted = this.isProactiveCooldownActive();

        this.init();
    }

    ListeoFloatingChatWidget.prototype.init = function () {
        this.checkWelcomeBubbleStatus();
        this.bindEvents();
        this.bindProactiveStorageSync();
        this.restoreChatState();
        this.scheduleProactiveActions();
        debugLog('Widget initialized', this.lazyScripts.length > 0 ? '(lazy load enabled)' : '');
    };

    ListeoFloatingChatWidget.prototype.checkWelcomeBubbleStatus = function () {
        if (!this.welcomeBubble) return;
        var dismissed = localStorage.getItem(STORAGE_KEY_BUBBLE_DISMISSED);
        if (dismissed === 'true') {
            this.welcomeBubble.classList.add('hidden');
        } else {
            this.welcomeBubble.classList.remove('hidden');
        }
    };

    ListeoFloatingChatWidget.prototype.restoreChatState = function () {
        var cfg = (typeof listeoAiFloatingChatConfig !== 'undefined') ? listeoAiFloatingChatConfig : {};
        if (!cfg.keepChatOpened) return;

        var wasOpen = localStorage.getItem(STORAGE_KEY_CHAT_OPENED);
        if (wasOpen === 'true') {
            if (this.popup) {
                this.popup.classList.add('listeo-no-animation');
            }
            this.openChat({ restore: true });
            debugLog('Restored chat open state from localStorage');
        }
    };

    ListeoFloatingChatWidget.prototype.bindEvents = function () {
        var self = this;

        if (this.button) {
            this.button.addEventListener('click', function (e) {
                e.preventDefault();
                self.toggleChat();
            });
        }

        if (this.bubbleStack) {
            this.bubbleStack.addEventListener('click', function (e) {
                if (!e.target || typeof e.target.closest !== 'function') return;

                var bubble = e.target.closest('.listeo-floating-welcome-bubble');
                if (!bubble || !self.bubbleStack.contains(bubble)) return;

                e.stopPropagation();
                var openProactiveBubble = bubble.classList.contains(
                    'listeo-floating-proactive-bubble'
                );
                self.dismissWelcomeBubble();
                if (openProactiveBubble) {
                    self.openChat();
                }
            });
        }

        document.addEventListener('click', function (e) {
            if (!e.target || typeof e.target.closest !== 'function') return;

            var magicLink = e.target.closest('[data-chat-magic-link]');
            if (!magicLink) return;

            e.preventDefault();
            self.activateMagicLink(magicLink);
        });

        document.addEventListener('listeo-floating-chat-ready', function () {
            self.sendPendingMagicQuestion();
            self.showPendingProactiveMessages();
        });

        document.addEventListener('listeo-ai-chat-user-message-sent', function (e) {
            if (self.popup && e.target && self.popup.contains(e.target)) {
                self.markUserInteracted();
            }
        });

        document.addEventListener('keydown', function (e) {
            if (e.key === 'Escape' && self.isOpen) {
                self.closeChat();
            } else if (
                e.key === 'Escape' &&
                self.miniChat &&
                self.miniChat.classList.contains('is-visible')
            ) {
                self.dismissActiveMiniChat();
            }
        });
    };

    ListeoFloatingChatWidget.prototype.toggleChat = function () {
        if (this.isOpen) {
            this.closeChat();
        } else {
            if (this.popup) {
                this.popup.classList.remove('listeo-no-animation');
            }
            this.openChat();
        }
    };

    ListeoFloatingChatWidget.prototype.activateMagicLink = function (magicLink) {
        var question = (magicLink.getAttribute('data-chat-magic-link') || '').trim();

        this.pendingMagicQuestion = question ? question.substring(0, 1000) : null;
        this.magicQuestionAttempts = 0;

        if (this.magicQuestionTimeoutId) {
            clearTimeout(this.magicQuestionTimeoutId);
            this.magicQuestionTimeoutId = null;
        }

        if (!this.isOpen) {
            this.openChat();
        }

        if (question) {
            this.sendPendingMagicQuestion();
        }
    };

    ListeoFloatingChatWidget.prototype.sendPendingMagicQuestion = function () {
        var self = this;
        if (!this.pendingMagicQuestion) return;

        var chatWrapper = document.getElementById('listeo-floating-chat-instance');
        var chatInstance = null;

        if (chatWrapper && typeof window.jQuery !== 'undefined') {
            chatInstance = window.jQuery(chatWrapper).data('listeo-ai-chat-instance');
        }

        if (chatInstance && typeof chatInstance.sendMagicMessage === 'function') {
            var question = this.pendingMagicQuestion;
            this.pendingMagicQuestion = null;
            this.magicQuestionAttempts = 0;

            if (this.magicQuestionTimeoutId) {
                clearTimeout(this.magicQuestionTimeoutId);
                this.magicQuestionTimeoutId = null;
            }

            chatInstance.sendMagicMessage(question);
            return;
        }

        if (!this.magicQuestionTimeoutId && this.magicQuestionAttempts < 100) {
            this.magicQuestionAttempts++;
            this.magicQuestionTimeoutId = setTimeout(function () {
                self.magicQuestionTimeoutId = null;
                self.sendPendingMagicQuestion();
            }, 50);
        }
    };

    ListeoFloatingChatWidget.prototype.openChat = function (options) {
        var self = this;
        options = options || {};

        this.hideMiniChat(true);
        this.clearPendingProactiveActions();
        this.activeProactiveActionId = '';
        this.activeProactiveActionType = '';

        if (options.proactive && options.message) {
            this.queueProactiveMessage(options.actionId, options.message);
        }
        this.dismissWelcomeBubble();

        if (typeof ListeoSilkWave !== 'undefined') {
            ListeoSilkWave.start();
        }

        if (this.closeTimeoutId) {
            clearTimeout(this.closeTimeoutId);
            this.closeTimeoutId = null;
        }

        if (this.popup) {
            this.popup.style.opacity = '';
            this.popup.style.transition = '';
            this.popup.style.display = 'block';
            setTimeout(function () { self.scrollToBottom(); }, FADE_DURATION_MS);
        }

        if (this.iconOpen) this.iconOpen.style.display = 'none';
        if (this.iconClose) this.iconClose.style.display = '';

        this.isOpen = true;
        dispatchWidgetState(this.popup, 'listeo-floating-chat-opened', 'listeo-floating-chat-instance');

        if (!this.chatInitialized) {
            this.chatInitialized = true;
            if (this.lazyScripts.length > 0 && !this.scriptsLoaded) {
                this.lazyLoadAndInit();
            } else {
                this.initializeChat();
            }
        }

        if (options.proactive && options.message) {
            this.showPendingProactiveMessages();
        }

        try {
            localStorage.setItem(STORAGE_KEY_CHAT_OPENED, 'true');
        } catch (e) {}

        debugLog('Chat opened');
    };

    ListeoFloatingChatWidget.prototype.scrollToBottom = function () {
        var messagesContainer = document.getElementById('listeo-floating-chat-instance-messages');
        if (messagesContainer && messagesContainer.scrollHeight) {
            messagesContainer.scrollTop = messagesContainer.scrollHeight;
        }
    };

    ListeoFloatingChatWidget.prototype.closeChat = function () {
        var self = this;
        this.isOpen = false;
        dispatchWidgetState(this.popup, 'listeo-floating-chat-closed', 'listeo-floating-chat-instance');

        if (this.popup) {
            this.popup.style.transition = 'opacity ' + FADE_DURATION_MS + 'ms';
            this.popup.style.opacity = '0';

            this.closeTimeoutId = setTimeout(function () {
                // reopen guard
                if (!self.isOpen && self.popup) {
                    self.popup.style.display = 'none';
                    self.popup.style.opacity = '';
                    self.popup.style.transition = '';
                }
                self.closeTimeoutId = null;

                if (!self.isOpen && !self.hasUserInteracted) {
                    if (self.bubbleStack) {
                        var proactiveBubbles = self.bubbleStack.querySelectorAll(
                            '.listeo-floating-proactive-bubble'
                        );
                        Array.prototype.forEach.call(proactiveBubbles, function (bubble) {
                            bubble.remove();
                        });
                    }
                    self.scheduleProactiveActions();
                }
            }, FADE_DURATION_MS);
        }

        if (typeof ListeoSilkWave !== 'undefined') {
            ListeoSilkWave.stop();
        }

        if (this.iconClose) this.iconClose.style.display = 'none';
        if (this.iconOpen) this.iconOpen.style.display = '';

        try {
            localStorage.removeItem(STORAGE_KEY_CHAT_OPENED);
        } catch (e) {}

        debugLog('Chat closed');
    };

    ListeoFloatingChatWidget.prototype.dismissWelcomeBubble = function () {
        var bubbles = this.bubbleStack
            ? this.bubbleStack.querySelectorAll('.listeo-floating-welcome-bubble')
            : (this.welcomeBubble ? [this.welcomeBubble] : []);

        Array.prototype.forEach.call(bubbles, function (bubble) {
            if (bubble.classList.contains('hidden')) return;

            bubble.style.transition = 'opacity 200ms';
            bubble.style.opacity = '0';

            if (bubble.listeoDismissTimeoutId) {
                clearTimeout(bubble.listeoDismissTimeoutId);
            }
            bubble.listeoDismissTimeoutId = setTimeout(function () {
                bubble.classList.add('hidden');
                bubble.style.opacity = '';
                bubble.style.transition = '';
                bubble.listeoDismissTimeoutId = null;
            }, 200);
        });

        try {
            localStorage.setItem(STORAGE_KEY_BUBBLE_DISMISSED, 'true');
        } catch (e) {}
    };

    ListeoFloatingChatWidget.prototype.getProactiveCooldownDuration = function () {
        var durations = {
            '30_minutes': 30 * 60 * 1000,
            '1_hour': 60 * 60 * 1000,
            '24_hours': 24 * 60 * 60 * 1000,
            '7_days': 7 * 24 * 60 * 60 * 1000
        };

        return durations[this.proactiveCooldown] || 0;
    };

    ListeoFloatingChatWidget.prototype.getProactiveActionCooldownKey = function (actionId) {
        return getScopedStorageKey(
            'listeo_floating_proactive_action_cooldown_' + String(actionId || '')
        );
    };

    ListeoFloatingChatWidget.prototype.isProactiveCooldownKeyActive = function (key) {
        if (this.proactiveCooldown === 'none') return false;

        if (this.proactiveCooldown === 'session') {
            try {
                return sessionStorage.getItem(key) === 'true';
            } catch (e) {
                return false;
            }
        }

        try {
            var expiresAt = parseInt(localStorage.getItem(key), 10);
            if (Number.isFinite(expiresAt) && expiresAt > Date.now()) {
                return true;
            }
            localStorage.removeItem(key);
        } catch (e) {}

        return false;
    };

    ListeoFloatingChatWidget.prototype.isProactiveCooldownActive = function () {
        var key = this.proactiveCooldown === 'session'
            ? STORAGE_KEY_USER_INTERACTED
            : STORAGE_KEY_PROACTIVE_COOLDOWN;

        return this.isProactiveCooldownKeyActive(key);
    };

    ListeoFloatingChatWidget.prototype.isProactiveActionCooldownActive = function (actionId) {
        return this.isProactiveCooldownKeyActive(
            this.getProactiveActionCooldownKey(actionId)
        );
    };

    ListeoFloatingChatWidget.prototype.setProactiveCooldownKey = function (key) {
        if (this.proactiveCooldown === 'none') return;

        if (this.proactiveCooldown === 'session') {
            try {
                sessionStorage.setItem(key, 'true');
            } catch (e) {}
            return;
        }

        var duration = this.getProactiveCooldownDuration();
        if (!duration) return;

        try {
            localStorage.setItem(key, String(Date.now() + duration));
        } catch (e) {}
    };

    ListeoFloatingChatWidget.prototype.clearPendingProactiveActions = function () {
        this.proactiveTimeoutIds.forEach(function (timeoutId) {
            clearTimeout(timeoutId);
        });
        this.proactiveTimeoutIds = [];
        this.proactiveScrollActions = [];
        this.proactiveActionQueue = [];
        if (this.proactiveQueueProcessTimeoutId) {
            clearTimeout(this.proactiveQueueProcessTimeoutId);
            this.proactiveQueueProcessTimeoutId = null;
        }
        this.removeProactiveScrollListener();
    };

    ListeoFloatingChatWidget.prototype.markUserInteracted = function () {
        this.hasUserInteracted = true;
        this.clearPendingProactiveActions();
        this.dismissWelcomeBubble();
        this.setProactiveCooldownKey(
            this.proactiveCooldown === 'session'
                ? STORAGE_KEY_USER_INTERACTED
                : STORAGE_KEY_PROACTIVE_COOLDOWN
        );
    };

    ListeoFloatingChatWidget.prototype.dismissActiveMiniChat = function () {
        var actionId = this.activeProactiveActionId;

        if (actionId) {
            this.setProactiveCooldownKey(
                this.getProactiveActionCooldownKey(actionId)
            );
        }

        this.activeProactiveActionId = '';
        this.activeProactiveActionType = '';
        this.hideMiniChat(true, true);
        this.processProactiveActionQueue();
    };

    ListeoFloatingChatWidget.prototype.bindProactiveStorageSync = function () {
        var self = this;
        if (!this.getProactiveCooldownDuration()) return;

        window.addEventListener('storage', function (event) {
            if (!event.key) return;

            if (
                event.key === STORAGE_KEY_PROACTIVE_COOLDOWN &&
                self.isProactiveCooldownActive()
            ) {
                self.hasUserInteracted = true;
                self.clearPendingProactiveActions();
                if (self.activeProactiveActionType === 'mini_chat') {
                    self.activeProactiveActionId = '';
                    self.activeProactiveActionType = '';
                    self.hideMiniChat(true);
                }
                self.dismissWelcomeBubble();
                return;
            }

            var activeActionId = self.activeProactiveActionId;
            if (
                activeActionId &&
                event.key === self.getProactiveActionCooldownKey(activeActionId) &&
                self.isProactiveActionCooldownActive(activeActionId)
            ) {
                self.activeProactiveActionId = '';
                self.activeProactiveActionType = '';
                self.hideMiniChat(true);
                self.processProactiveActionQueue();
            }

            if (event.newValue) {
                self.proactiveActionQueue = self.proactiveActionQueue.filter(function (item) {
                    return event.key !== self.getProactiveActionCooldownKey(item.actionId);
                });
            }
        });
    };

    ListeoFloatingChatWidget.prototype.scheduleProactiveActions = function () {
        var self = this;

        if (this.hasUserInteracted || this.isOpen) return;

        var actions = this.proactiveActions.filter(function (action) {
            return action &&
                !self.isProactiveActionCooldownActive(String(action.id || '').trim());
        });
        if (this.proactiveDisplayMode === 'highest_priority') {
            actions.sort(function (a, b) {
                return self.getProactiveActionPriority(a) -
                    self.getProactiveActionPriority(b);
            });
            actions = actions.slice(0, 1);
        }

        actions.forEach(function (action) {
            self.scheduleProactiveAction(action);
        });
    };

    ListeoFloatingChatWidget.prototype.scheduleProactiveAction = function (action) {
        var self = this;

        if (!action || this.hasUserInteracted) return;

        var actionId = String(action.id || '').trim();
        var actionType = String(action.action || '');
        var trigger = String(action.trigger || '') === 'scroll_depth'
            ? 'scroll_depth'
            : 'time';

        if (
            !actionId ||
            (
                actionType !== 'show_bubble' &&
                actionType !== 'open_chat' &&
                actionType !== 'mini_chat'
            )
        ) {
            return;
        }
        if (this.isProactiveActionCooldownActive(actionId)) return;

        var firedKey = getScopedStorageKey(
            'listeo_floating_proactive_action_' + actionId
        );

        try {
            var storedStatus = sessionStorage.getItem(firedKey);
            if (storedStatus === 'shown' || storedStatus === 'true') {
                sessionStorage.removeItem(firedKey);
            }
        } catch (e) {}

        if (trigger === 'scroll_depth') {
            var scrollDepth = parseInt(action.scroll_depth, 10);
            if (!Number.isFinite(scrollDepth) || scrollDepth < 1 || scrollDepth > 100) {
                return;
            }

            this.proactiveScrollActions.push({
                action: action,
                actionId: actionId,
                actionType: actionType,
                scrollDepth: scrollDepth
            });
            this.addProactiveScrollListener();
            this.checkProactiveScrollActions();
            return;
        }

        var delay = parseInt(action.delay, 10);
        if (!Number.isFinite(delay) || delay < 0) return;

        var timeoutId = setTimeout(function () {
            self.proactiveTimeoutIds = self.proactiveTimeoutIds.filter(function (id) {
                return id !== timeoutId;
            });
            if (self.hasUserInteracted) return;

            self.handleReadyProactiveAction(action, actionId, actionType);
        }, delay * 1000);

        this.proactiveTimeoutIds.push(timeoutId);
    };

    ListeoFloatingChatWidget.prototype.getProactiveActionPriority = function (action) {
        var priority = parseInt(action && action.priority, 10);
        return Number.isFinite(priority) ? priority : 10;
    };

    ListeoFloatingChatWidget.prototype.handleReadyProactiveAction = function (
        action,
        actionId,
        actionType
    ) {
        if (
            this.hasUserInteracted ||
            this.isProactiveActionCooldownActive(actionId)
        ) {
            return;
        }

        if (actionType === 'show_bubble') {
            this.executeProactiveAction(action, actionId, actionType);
            return;
        }

        if (this.proactiveActionQueue.some(function (item) {
            return item.actionId === actionId;
        })) {
            return;
        }

        this.proactiveActionQueue.push({
            action: action,
            actionId: actionId,
            actionType: actionType,
            priority: this.getProactiveActionPriority(action),
            sequence: this.proactiveQueueSequence++
        });
        this.proactiveActionQueue.sort(function (a, b) {
            return a.priority === b.priority
                ? a.sequence - b.sequence
                : a.priority - b.priority;
        });
        this.scheduleProactiveActionQueue();
    };

    ListeoFloatingChatWidget.prototype.scheduleProactiveActionQueue = function () {
        var self = this;
        if (this.proactiveQueueProcessTimeoutId) return;

        this.proactiveQueueProcessTimeoutId = setTimeout(function () {
            self.proactiveQueueProcessTimeoutId = null;
            self.processProactiveActionQueue();
        }, 0);
    };

    ListeoFloatingChatWidget.prototype.processProactiveActionQueue = function () {
        if (this.hasUserInteracted || this.isOpen) {
            this.proactiveActionQueue = [];
            return;
        }
        if (
            this.activeProactiveActionType === 'mini_chat' &&
            this.miniChat &&
            this.miniChat.classList.contains('is-visible')
        ) {
            return;
        }

        var item = null;
        while (this.proactiveActionQueue.length && !item) {
            var candidate = this.proactiveActionQueue.shift();
            if (!this.isProactiveActionCooldownActive(candidate.actionId)) {
                item = candidate;
            }
        }
        if (!item) return;

        this.executeProactiveAction(item.action, item.actionId, item.actionType);
    };

    ListeoFloatingChatWidget.prototype.executeProactiveAction = function (
        action,
        actionId,
        actionType
    ) {
        if (
            this.hasUserInteracted ||
            this.isProactiveActionCooldownActive(actionId)
        ) {
            return;
        }

        if (actionType === 'open_chat') {
            this.openChat({
                proactive: true,
                actionId: actionId,
                message: String(action.message || '')
            });
            return;
        }

        if (actionType === 'mini_chat') {
            if (this.showMiniChat(
                actionId,
                String(action.message || ''),
                action.quick_actions
            )) {
                this.activeProactiveActionId = actionId;
                this.activeProactiveActionType = 'mini_chat';
            } else {
                this.processProactiveActionQueue();
            }
            return;
        }

        this.showProactiveBubble(
            actionId,
            String(action.message || ''),
            this.getProactiveActionPriority(action)
        );
    };

    ListeoFloatingChatWidget.prototype.addProactiveScrollListener = function () {
        var self = this;
        if (this.proactiveScrollHandler) return;

        this.proactiveScrollHandler = function () {
            self.checkProactiveScrollActions();
        };
        window.addEventListener('scroll', this.proactiveScrollHandler, { passive: true });
    };

    ListeoFloatingChatWidget.prototype.removeProactiveScrollListener = function () {
        if (!this.proactiveScrollHandler) return;

        window.removeEventListener('scroll', this.proactiveScrollHandler);
        this.proactiveScrollHandler = null;
    };

    ListeoFloatingChatWidget.prototype.getPageScrollPercentage = function () {
        var documentElement = document.documentElement;
        var body = document.body;
        var scrollTop = window.pageYOffset ||
            (documentElement ? documentElement.scrollTop : 0) ||
            (body ? body.scrollTop : 0);
        var viewportHeight = window.innerHeight ||
            (documentElement ? documentElement.clientHeight : 0);
        var scrollHeight = Math.max(
            body ? body.scrollHeight : 0,
            body ? body.offsetHeight : 0,
            documentElement ? documentElement.clientHeight : 0,
            documentElement ? documentElement.scrollHeight : 0,
            documentElement ? documentElement.offsetHeight : 0
        );
        var scrollableHeight = scrollHeight - viewportHeight;

        if (scrollableHeight <= 0) return 0;

        return Math.min(100, Math.max(0, (scrollTop / scrollableHeight) * 100));
    };

    ListeoFloatingChatWidget.prototype.checkProactiveScrollActions = function () {
        var self = this;

        if (this.hasUserInteracted) {
            this.proactiveScrollActions = [];
            this.removeProactiveScrollListener();
            return;
        }

        var scrollPercentage = this.getPageScrollPercentage();
        var readyActions = [];

        this.proactiveScrollActions = this.proactiveScrollActions.filter(function (item) {
            if (scrollPercentage >= item.scrollDepth) {
                readyActions.push(item);
                return false;
            }
            return true;
        });

        if (!this.proactiveScrollActions.length) {
            this.removeProactiveScrollListener();
        }

        readyActions.forEach(function (item) {
            self.handleReadyProactiveAction(item.action, item.actionId, item.actionType);
        });
    };

    ListeoFloatingChatWidget.prototype.queueProactiveMessage = function (actionId, message) {
        actionId = String(actionId || '').trim();
        message = String(message || '').trim();

        if (!actionId || !message) return;

        this.pendingProactiveMessages.push({
            id: actionId,
            message: message
        });
        this.proactiveMessageAttempts = 0;
    };

    ListeoFloatingChatWidget.prototype.showPendingProactiveMessages = function () {
        var self = this;
        if (!this.pendingProactiveMessages.length) return;

        var chatWrapper = document.getElementById('listeo-floating-chat-instance');
        var messagesContainer = document.getElementById('listeo-floating-chat-instance-messages');
        var chatInstance = null;

        if (chatWrapper && typeof window.jQuery !== 'undefined') {
            chatInstance = window.jQuery(chatWrapper).data('listeo-ai-chat-instance');
        }

        if (!messagesContainer || !chatInstance || typeof chatInstance.addMessage !== 'function') {
            if (!this.proactiveMessageTimeoutId && this.proactiveMessageAttempts < 100) {
                this.proactiveMessageAttempts++;
                this.proactiveMessageTimeoutId = setTimeout(function () {
                    self.proactiveMessageTimeoutId = null;
                    self.showPendingProactiveMessages();
                }, 50);
            }
            return;
        }

        var normalizeText = function (value) {
            return String(value || '').replace(/\s+/g, ' ').trim();
        };
        var chatConfig = (typeof listeoAiChatConfig !== 'undefined') ? listeoAiChatConfig : {};
        var welcomeMessage = chatConfig.strings ? chatConfig.strings.welcomeMessage : '';
        var welcomeHolder = document.createElement('div');
        welcomeHolder.innerHTML = welcomeMessage;
        var welcomeText = normalizeText(welcomeHolder.textContent);
        var messagesChanged = false;

        this.pendingProactiveMessages.forEach(function (item) {
            var messageId = 'listeo-proactive-message-' + item.id;
            if (document.getElementById(messageId)) return;

            var hasConversation = messagesContainer.querySelector(
                '.listeo-ai-chat-message-user, .listeo-ai-chat-message-assistant'
            );
            var hasProactiveMessage = messagesContainer.querySelector(
                '[data-proactive-action-id]'
            );
            var systemMessages = messagesContainer.querySelectorAll(
                '.listeo-ai-chat-message-system:not([data-proactive-action-id])'
            );
            var defaultMessage = null;

            Array.prototype.some.call(systemMessages, function (systemMessage) {
                var systemContent = systemMessage.querySelector(
                    '.listeo-ai-chat-message-content'
                );
                if (
                    systemContent &&
                    welcomeText &&
                    normalizeText(systemContent.textContent) === welcomeText
                ) {
                    defaultMessage = systemMessage;
                    return true;
                }
                return false;
            });

            if (!hasConversation && !hasProactiveMessage && defaultMessage) {
                var content = defaultMessage.querySelector('.listeo-ai-chat-message-content');
                if (content) {
                    content.textContent = item.message;
                    defaultMessage.id = messageId;
                    defaultMessage.setAttribute('data-proactive-action-id', item.id);
                    messagesChanged = true;
                    return;
                }
            }

            var imageWelcome = messagesContainer.querySelector('.chat-image-bg-welcome-text');
            if (!hasConversation && !hasProactiveMessage && imageWelcome) {
                imageWelcome.remove();
                messagesChanged = true;
            }

            chatInstance.addMessage('system', item.message, messageId, true);
            messagesChanged = true;
            var addedMessage = document.getElementById(messageId);
            if (addedMessage) {
                addedMessage.setAttribute('data-proactive-action-id', item.id);
            }
        });

        if (messagesChanged && typeof chatInstance.saveConversation === 'function') {
            chatInstance.saveConversation();
        }

        this.pendingProactiveMessages = [];
        this.proactiveMessageAttempts = 0;
    };

    ListeoFloatingChatWidget.prototype.showProactiveBubble = function (
        actionId,
        message,
        priority
    ) {
        actionId = String(actionId || '').trim();
        message = String(message || '').trim();
        priority = Number.isFinite(priority) ? priority : 10;

        if (!this.bubbleStack || !actionId || !message) return;

        var bubbleId = 'listeo-floating-proactive-bubble-' + actionId;
        if (document.getElementById(bubbleId)) return;

        var bubble = document.createElement('div');
        bubble.className = 'listeo-floating-welcome-bubble listeo-floating-proactive-bubble';
        bubble.id = bubbleId;
        bubble.setAttribute('data-proactive-action-id', actionId);
        bubble.setAttribute('data-proactive-priority', String(priority));

        var content = document.createElement('div');
        content.className = 'listeo-floating-welcome-bubble-content';
        content.textContent = message;

        var arrow = document.createElement('div');
        arrow.className = 'listeo-floating-welcome-bubble-arrow';

        bubble.appendChild(content);
        bubble.appendChild(arrow);

        var inserted = false;
        var existingBubbles = this.bubbleStack.querySelectorAll(
            '.listeo-floating-proactive-bubble'
        );
        Array.prototype.some.call(existingBubbles, function (existingBubble) {
            var existingPriority = parseInt(
                existingBubble.getAttribute('data-proactive-priority'),
                10
            );
            if (!Number.isFinite(existingPriority)) existingPriority = 10;

            if (existingPriority > priority) {
                existingBubble.parentNode.insertBefore(bubble, existingBubble);
                inserted = true;
                return true;
            }
            return false;
        });

        if (!inserted) {
            this.bubbleStack.appendChild(bubble);
        }
    };

    ListeoFloatingChatWidget.prototype.createMiniChat = function () {
        var self = this;
        var widget = document.getElementById('listeo-floating-chat-widget');
        if (!widget || this.miniChat) return;

        var panel = document.createElement('div');
        panel.className = 'listeo-floating-mini-chat';
        panel.setAttribute('role', 'dialog');
        panel.setAttribute('aria-live', 'polite');

        var closeButton = document.createElement('button');
        closeButton.type = 'button';
        closeButton.className = 'listeo-floating-mini-chat-close';
        closeButton.setAttribute(
            'aria-label',
            String(this.proactiveStrings.closeMiniChat || '')
        );
        closeButton.innerHTML = '<svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" aria-hidden="true"><path d="M6 6l12 12"></path><path d="M18 6 6 18"></path></svg>';

        var message = document.createElement('div');
        message.className = 'listeo-floating-mini-chat-message';

        var quickActions = document.createElement('div');
        quickActions.className = 'listeo-floating-mini-chat-quick-actions';
        quickActions.hidden = true;

        var form = document.createElement('form');
        form.className = 'listeo-floating-mini-chat-form';

        var input = document.createElement('input');
        input.type = 'text';
        input.className = 'listeo-floating-mini-chat-input';
        input.maxLength = 1000;
        input.placeholder = String(this.proactiveStrings.miniPlaceholder || '');
        input.setAttribute(
            'aria-label',
            String(this.proactiveStrings.miniPlaceholder || '')
        );

        var sendButton = document.createElement('button');
        sendButton.type = 'submit';
        sendButton.className = 'listeo-floating-mini-chat-send';
        sendButton.setAttribute(
            'aria-label',
            String(this.proactiveStrings.sendMessage || '')
        );
        sendButton.innerHTML = '<svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M12 19V5"></path><path d="m5 12 7-7 7 7"></path></svg>';

        form.appendChild(input);
        form.appendChild(sendButton);
        panel.appendChild(closeButton);
        panel.appendChild(message);
        panel.appendChild(quickActions);
        panel.appendChild(form);
        widget.appendChild(panel);

        closeButton.addEventListener('click', function () {
            self.dismissActiveMiniChat();
        });

        quickActions.addEventListener('click', function (event) {
            if (!event.target || typeof event.target.closest !== 'function') return;

            var button = event.target.closest('.listeo-floating-mini-chat-quick-action');
            if (!button || !quickActions.contains(button)) return;

            self.sendMiniChatQuestion(button.getAttribute('data-message') || '');
        });

        form.addEventListener('submit', function (event) {
            event.preventDefault();
            self.sendMiniChatQuestion(input.value);
        });

        this.miniChat = panel;
        this.miniChatMessage = message;
        this.miniChatQuickActions = quickActions;
        this.miniChatInput = input;
    };

    ListeoFloatingChatWidget.prototype.sendMiniChatQuestion = function (question) {
        question = String(question || '').trim().substring(0, 1000);
        if (!question) return;

        this.pendingMagicQuestion = question;
        this.magicQuestionAttempts = 0;
        if (this.magicQuestionTimeoutId) {
            clearTimeout(this.magicQuestionTimeoutId);
            this.magicQuestionTimeoutId = null;
        }

        this.hideMiniChat(true, true);
        this.openChat();
        this.sendPendingMagicQuestion();
    };

    ListeoFloatingChatWidget.prototype.renderMiniChatQuickActions = function (quickActions) {
        var container = this.miniChatQuickActions;
        if (!container) return;

        container.textContent = '';

        if (!Array.isArray(quickActions)) {
            container.hidden = true;
            return;
        }

        quickActions.forEach(function (quickAction) {
            if (!quickAction || typeof quickAction !== 'object') return;

            var label = String(quickAction.label || '').trim();
            var message = String(quickAction.message || '').trim().substring(0, 1000);
            if (!label || !message) return;

            var button = document.createElement('button');
            button.type = 'button';
            button.className = 'listeo-floating-mini-chat-quick-action';
            button.textContent = label;
            button.setAttribute('data-message', message);

            var color = String(quickAction.color || '').trim();
            if (/^#[0-9a-f]{3}$/i.test(color)) {
                color = '#' +
                    color.charAt(1) + color.charAt(1) +
                    color.charAt(2) + color.charAt(2) +
                    color.charAt(3) + color.charAt(3);
            }
            if (/^#[0-9a-f]{6}$/i.test(color)) {
                var red = parseInt(color.substring(1, 3), 16);
                var green = parseInt(color.substring(3, 5), 16);
                var blue = parseInt(color.substring(5, 7), 16);

                button.classList.add(
                    'listeo-floating-mini-chat-quick-action-custom'
                );
                button.style.setProperty('--quick-btn-color', color);
                button.style.setProperty(
                    '--quick-btn-color-light',
                    'rgba(' + red + ', ' + green + ', ' + blue + ', 0.1)'
                );
            }

            container.appendChild(button);
        });

        container.hidden = !container.children.length;
    };

    ListeoFloatingChatWidget.prototype.showMiniChat = function (actionId, message, quickActions) {
        actionId = String(actionId || '').trim();
        message = String(message || '').trim();

        if (!actionId || !message || this.isOpen) return false;

        this.createMiniChat();
        if (!this.miniChat) return false;

        this.miniChat.setAttribute('data-proactive-action-id', actionId);
        this.miniChat.setAttribute('aria-label', message);
        this.miniChatMessage.textContent = message;
        this.miniChatInput.value = '';
        this.renderMiniChatQuickActions(quickActions);

        if (this.miniChatCloseTimeoutId) {
            clearTimeout(this.miniChatCloseTimeoutId);
            this.miniChatCloseTimeoutId = null;
        }

        void this.miniChat.offsetWidth;
        this.miniChat.classList.add('is-visible');

        if (this.button) {
            this.button.style.display = 'none';
        }
        if (this.bubbleStack) {
            this.bubbleStack.style.display = 'none';
        }

        return true;
    };

    ListeoFloatingChatWidget.prototype.hideMiniChat = function (restoreLauncher, animateLauncher) {
        var self = this;
        if (!this.miniChat || !this.miniChat.classList.contains('is-visible')) return;

        this.miniChat.classList.remove('is-visible');
        if (!restoreLauncher) return;

        if (this.miniChatCloseTimeoutId) {
            clearTimeout(this.miniChatCloseTimeoutId);
        }

        this.miniChatCloseTimeoutId = setTimeout(function () {
            self.miniChatCloseTimeoutId = null;
            if (self.miniChat.classList.contains('is-visible')) return;

            if (self.button) {
                self.button.style.display = '';
                if (animateLauncher) {
                    self.button.classList.remove('listeo-mini-chat-launcher-return');
                    void self.button.offsetWidth;
                    self.button.classList.add('listeo-mini-chat-launcher-return');
                    setTimeout(function () {
                        if (self.button) {
                            self.button.classList.remove('listeo-mini-chat-launcher-return');
                        }
                    }, MINI_CHAT_ANIMATION_MS);
                }
            }
            if (self.bubbleStack) {
                self.bubbleStack.style.display = '';
            }
        }, MINI_CHAT_ANIMATION_MS);
    };

    ListeoFloatingChatWidget.prototype.lazyLoadAndInit = function () {
        var self = this;
        var chatWrapper = document.getElementById('listeo-floating-chat-instance');

        // shortcode on same page may have already loaded core
        if (document.querySelector('script[src*="ai-chat-core"]')) {
            this.scriptsLoaded = true;
            this.initializeChat();
            return;
        }

        if (chatWrapper) {
            chatWrapper.classList.add('listeo-ai-chat-lazy-state');
        }

        var ver = this.scriptVersion;
        var urls = this.lazyScripts.map(function (url) {
            return url + (url.indexOf('?') === -1 ? '?' : '&') + 'ver=' + ver;
        });

        this.loadScriptsSequential(urls, 0, function () {
            self.scriptsLoaded = true;
            // let chatbot-core's ready handler settle
            setTimeout(function () {
                if (chatWrapper) {
                    chatWrapper.classList.remove('listeo-ai-chat-lazy-state');
                }
                self.initializeChat();
            }, 50);
        });
    };

    ListeoFloatingChatWidget.prototype.loadScriptsSequential = function (urls, index, callback) {
        var self = this;
        if (index >= urls.length) {
            callback();
            return;
        }

        var script = document.createElement('script');
        script.src = urls[index];
        script.onload = function () {
            self.loadScriptsSequential(urls, index + 1, callback);
        };
        script.onerror = function () {
            console.error('[AI Chat] Failed to load:', urls[index]);
            self.loadScriptsSequential(urls, index + 1, callback);
        };
        document.body.appendChild(script);
    };

    ListeoFloatingChatWidget.prototype.initializeChat = function () {
        setTimeout(function () {
            dispatchReady('listeo-floating-chat-instance');
        }, 100);
    };

    function boot() {
        if (document.getElementById('listeo-floating-chat-widget')) {
            new ListeoFloatingChatWidget();
        }
    }

    // works in head (lazy mode, defer) or footer
    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', boot);
    } else {
        boot();
    }
})();
