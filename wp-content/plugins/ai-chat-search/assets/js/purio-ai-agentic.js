/**
 * Optional backend-agentic transport for PurioChat.
 *
 * Loaded only when Agentic Mode is enabled. The core chat script owns shared
 * UI state and legacy behavior; this module owns agent requests, progress
 * polling, cancellation, and agent artifact rendering.
 */
(function ($) {
  "use strict";

  var states = new WeakMap();

  var debugLog = function () {
    if (
      typeof listeoAiChatConfig === "undefined" ||
      !listeoAiChatConfig.debugMode
    ) {
      return;
    }

    var args = Array.prototype.slice.call(arguments);
    args.unshift("[AI Chat Agentic]");
    console.log.apply(console, args);
  };

  var debugError = function () {
    if (
      typeof listeoAiChatConfig === "undefined" ||
      !listeoAiChatConfig.debugMode
    ) {
      return;
    }

    var args = Array.prototype.slice.call(arguments);
    args.unshift("[AI Chat Agentic ERROR]");
    console.error.apply(console, args);
  };

  var generateRequestId = function () {
    if (
      window.crypto &&
      typeof window.crypto.randomUUID === "function"
    ) {
      return window.crypto.randomUUID();
    }

    if (
      window.crypto &&
      typeof window.crypto.getRandomValues === "function"
    ) {
      var randomValues = new Uint32Array(4);
      window.crypto.getRandomValues(randomValues);
      return Array.prototype.map
        .call(randomValues, function (value) {
          return value.toString(16).padStart(8, "0");
        })
        .join("");
    }

    return (
      Date.now().toString(36) +
      Math.random().toString(36).substring(2) +
      Math.random().toString(36).substring(2)
    );
  };

  var isEnabled = function (chat) {
    if (!chat || !chat.chatConfig) {
      return false;
    }

    var enabled = chat.chatConfig.agenticMode;
    return enabled === true || enabled === 1 || enabled === "1";
  };

  var getPhaseLabel = function (phase) {
    if (phase === "searching") {
      return (
        listeoAiChatConfig.strings.agentSearching ||
        listeoAiChatConfig.strings.searchingDatabase
      );
    }

    if (phase === "analyzing") {
      return (
        listeoAiChatConfig.strings.agentAnalyzing ||
        listeoAiChatConfig.strings.analyzingResults
      );
    }

    return (
      listeoAiChatConfig.strings.agentThinking ||
      listeoAiChatConfig.strings.loading
    );
  };

  var releaseState = function (chat, state) {
    if (states.get(chat) === state) {
      states.delete(chat);
    }
    if (chat.activeMessageTransport === transport) {
      chat.activeMessageTransport = null;
    }
  };

  var syncCartArtifacts = function (chat, items) {
    var seenActions = {};

    items.forEach(function (item) {
      if (!item || item.success === false) {
        return;
      }

      var productId = parseInt(item.product_id, 10) || 0;
      var quantity = Math.max(1, parseInt(item.quantity, 10) || 1);
      var cartCount = parseInt(item.cart_count, 10);
      var actionKey = productId + ":" + quantity + ":" + cartCount;

      if (seenActions[actionKey]) {
        return;
      }
      seenActions[actionKey] = true;

      if (!isNaN(cartCount)) {
        var $badges = $(".listeo-ai-cart-badge");
        if (cartCount > 0) {
          $badges.text(cartCount).show();
        } else {
          $badges.hide();
        }
      }

      if (
        productId &&
        chat.sessionId &&
        listeoAiChatConfig.wooCartEnabled
      ) {
        $.ajax({
          url: listeoAiChatConfig.ajaxUrl,
          method: "POST",
          data: {
            action: "listeo_ai_log_cart_event",
            nonce: listeoAiChatConfig.cartNonce,
            conversation_id: chat.sessionId,
            product_id: productId,
            product_name: "",
            quantity: quantity,
          },
        });
      }
    });
  };

  var renderResponse = function (chat, answer, artifacts) {
    var artifactHtml = [];
    var safeArtifacts = Array.isArray(artifacts) ? artifacts : [];

    safeArtifacts.forEach(function (artifact) {
      var type =
        artifact && typeof artifact.type === "string"
          ? artifact.type.toLowerCase()
          : "";
      var items =
        artifact && Array.isArray(artifact.items) ? artifact.items : [];
      var html = "";

      if (type === "cart") {
        syncCartArtifacts(chat, items);
        return;
      }

      if (!items.length) {
        return;
      }

      if (
        (type === "products" || type === "listings") &&
        artifact.refined !== true
      ) {
        return;
      }

      if (type === "products") {
        html = chat.formatProductsGrid(items);
      } else if (type === "listings") {
        html = chat.formatListingsGrid(items);
      }

      if (!html) {
        return;
      }

      artifactHtml.push(html);
    });

    var resultOrder =
      listeoAiChatConfig.resultOrder === "answer_first"
        ? "answer_first"
        : "cards_first";
    var addArtifacts = function () {
      artifactHtml.forEach(function (html) {
        chat.addMessage("assistant", html);
      });
    };

    if (resultOrder === "cards_first") {
      addArtifacts();
    }

    if (answer) {
      chat.addMessage("assistant", answer);
    }

    if (resultOrder === "answer_first") {
      addArtifacts();
    }
  };

  var send = function (chat, userMessage, helpers) {
    var contextMultipliers = { short: 1, normal: 2, long: 6 };
    var ctxMul =
      contextMultipliers[
        (listeoAiChatConfig && listeoAiChatConfig.contextLength) || "normal"
      ] || 3;
    // Remove progress bubbles stored by earlier Agentic Mode versions.
    chat.conversationHistory = chat.conversationHistory.filter(
      function (message) {
        return !message.purio_agent_progress;
      },
    );
    var recentHistory = chat.getValidHistorySlice(12 * ctxMul);
    var requestId = generateRequestId();
    var loadingId = "loading-" + Date.now();
    var requestHeaders = $.extend(
      {},
      helpers.getRequestHeaders(),
      {
        "X-Session-ID": chat.sessionId,
      },
      window.PurioChatLiveHandoff &&
        typeof window.PurioChatLiveHandoff.getHeaders === "function"
        ? window.PurioChatLiveHandoff.getHeaders(chat)
        : {},
      chat.getPreChatHeaders(),
    );
    var payload = {
      messages: recentHistory.concat([
        { role: "user", content: userMessage },
      ]),
      request_id: requestId,
    };
    var state = {
      helpers: helpers,
      loadingId: loadingId,
      progressCursor: 0,
      progressRequest: null,
      progressStopped: false,
      progressTimer: null,
      requestHeaders: requestHeaders,
      requestId: requestId,
      seenProgress: {},
      stopProgressPolling: null,
    };
    states.set(chat, state);

    chat.addMessage(
      "assistant",
      helpers.generateLoaderHTML(
        listeoAiChatConfig.strings.agentThinking ||
          listeoAiChatConfig.strings.loading,
      ),
      loadingId,
    );

    if (chat.$wrapper.data("speech-pending")) {
      payload.is_transcribed = true;
      chat.$wrapper.data("speech-pending", false);
    }

    if (chat.loadedListing && chat.loadedListing.id) {
      payload.listing_context_id = chat.loadedListing.id;
    }

    if (chat.loadedProduct && chat.loadedProduct.id) {
      payload.product_context_id = chat.loadedProduct.id;
    }

    var stopProgressPolling = function () {
      state.progressStopped = true;

      if (state.progressTimer) {
        window.clearTimeout(state.progressTimer);
        state.progressTimer = null;
      }

      if (
        state.progressRequest &&
        typeof state.progressRequest.abort === "function"
      ) {
        state.progressRequest.abort();
      }
      state.progressRequest = null;
    };
    state.stopProgressPolling = stopProgressPolling;

    var updatePhase = function (phase, detail) {
      var phaseLabel = getPhaseLabel(phase);
      var statusDetail =
        typeof detail === "string"
          ? Array.from(detail.trim()).slice(0, 50).join("")
          : "";
      var loaderText = phaseLabel;

      if (statusDetail) {
        loaderText = statusDetail.replace(/[.!?…]+$/, "");
        loaderText =
          Array.from(loaderText).slice(0, 47).join("") + "...";
      }
      var $loadingMessage = chat.$messages.find("#" + loadingId);

      if (!$loadingMessage.length) {
        chat.addMessage(
          "assistant",
          helpers.generateLoaderHTML(chat.escapeHtml(loaderText)),
          loadingId,
          true,
        );
        $loadingMessage = chat.$messages.find("#" + loadingId);
      }

      $loadingMessage
        .find(".listeo-ai-chat-message-content")
        .html(helpers.generateLoaderHTML(chat.escapeHtml(loaderText)));
    };

    var renderProgress = function (progressMessages) {
      (Array.isArray(progressMessages) ? progressMessages : []).forEach(
        function (progress) {
          var sequence =
            progress && progress.sequence !== undefined
              ? String(progress.sequence)
              : "";

          if (sequence && state.seenProgress[sequence]) {
            return;
          }

          if (sequence) {
            state.seenProgress[sequence] = true;
          }

          if (progress && progress.type === "status") {
            updatePhase(progress.phase, progress.detail);
          }
        },
      );
    };

    var scheduleProgressPoll = function () {
      if (state.progressStopped) {
        return;
      }

      state.progressTimer = window.setTimeout(function () {
        if (state.progressStopped) {
          return;
        }

        state.progressRequest = $.ajax({
          url: listeoAiChatConfig.apiBase + "/agent-progress",
          method: "GET",
          headers: requestHeaders,
          data: {
            request_id: requestId,
            after: state.progressCursor,
          },
          success: function (response) {
            if (state.progressStopped || !response) {
              return;
            }

            if (response.success === false) {
              debugError("Progress endpoint returned an error");
              stopProgressPolling();
              return;
            }

            renderProgress(
              Array.isArray(response.messages) ? response.messages : [],
            );

            if (response.cursor !== undefined && response.cursor !== null) {
              state.progressCursor = response.cursor;
            }
          },
          error: function (xhr, statusText) {
            if (!state.progressStopped && statusText !== "abort") {
              debugError("Progress poll failed:", xhr.status);
              stopProgressPolling();
            }
          },
          complete: function () {
            state.progressRequest = null;
            scheduleProgressPoll();
          },
        });
      }, 350);
    };

    scheduleProgressPoll();

    if (typeof helpers.logModelDebug === "function") {
      helpers.logModelDebug(payload, "Backend Agentic", chat.chatConfig);
    }
    if (typeof helpers.logApiRequest === "function") {
      helpers.logApiRequest(payload, chat.chatConfig.model);
    }
    debugLog("Starting backend agent request");

    chat.activeChatRequest = $.ajax({
      url: listeoAiChatConfig.apiBase + "/agent-chat",
      method: "POST",
      headers: requestHeaders,
      data: JSON.stringify(payload),
      success: function (data) {
        stopProgressPolling();
        chat.activeChatRequest = null;

        if (data && data.success !== false) {
          renderProgress(data.progress_events);
        }

        releaseState(chat, state);

        if (
          window.PurioChatLiveHandoff &&
          typeof window.PurioChatLiveHandoff.handleAIResponse === "function" &&
          window.PurioChatLiveHandoff.handleAIResponse(
            chat,
            data,
            loadingId,
            "agent-chat",
            userMessage,
          )
        ) {
          return;
        }

        if (!data || data.success === false) {
          var responseError =
            data && data.error && data.error.message
              ? data.error.message
              : listeoAiChatConfig.strings.errorGeneral;
          chat.$messages.find("#" + loadingId).remove();
          chat.addMessage("system", responseError);
          chat.isProcessing = false;
          chat.$sendBtn.prop("disabled", false);
          return;
        }

        chat.$messages.find("#" + loadingId).remove();
        renderResponse(chat, data.answer, data.artifacts);

        chat.conversationHistory.push({
          role: "user",
          content: userMessage,
        });
        chat.conversationHistory.push({
          role: "assistant",
          content: data.answer || "",
        });
        chat.saveConversation();

        chat.isProcessing = false;
        chat.$sendBtn.prop("disabled", false);
      },
      error: function (xhr, statusText) {
        stopProgressPolling();
        chat.activeChatRequest = null;
        releaseState(chat, state);

        if (statusText === "abort") {
          return;
        }

        if (
          window.PurioChatLiveHandoff &&
          typeof window.PurioChatLiveHandoff.handleAIError === "function" &&
          window.PurioChatLiveHandoff.handleAIError(chat, xhr, loadingId)
        ) {
          return;
        }

        var errorInfo = helpers.analyzeError(xhr, "agent-chat");
        chat.$messages.find("#" + loadingId).remove();
        chat.addMessage("system", errorInfo.userMessage);
        chat.isProcessing = false;
        chat.$sendBtn.prop("disabled", false);
      },
    });
  };

  var cancel = function (chat) {
    var state = states.get(chat);
    if (!state) {
      return;
    }

    if (typeof state.stopProgressPolling === "function") {
      state.stopProgressPolling();
    }

    $.ajax({
      url: listeoAiChatConfig.apiBase + "/agent-cancel",
      method: "POST",
      headers: $.extend({}, state.helpers.getRequestHeaders(), {
        "X-Session-ID": chat.sessionId,
      }),
      data: JSON.stringify({
        request_id: state.requestId,
      }),
    });

    states.delete(chat);
  };

  var reset = function (chat, helpers) {
    var state = states.get(chat);
    if (state) {
      cancel(chat);
      return;
    }

    $.ajax({
      url: listeoAiChatConfig.apiBase + "/agent-cancel",
      method: "POST",
      headers: $.extend({}, helpers.getRequestHeaders(), {
        "X-Session-ID": chat.sessionId,
      }),
      data: JSON.stringify({
        request_id: "",
      }),
    });
  };

  var transport = {
    canHandle: isEnabled,
    cancel: cancel,
    reset: reset,
    send: send,
  };

  window.PurioChatMessageTransport = transport;
})(jQuery);
