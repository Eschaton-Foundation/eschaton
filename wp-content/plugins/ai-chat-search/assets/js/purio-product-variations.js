/**
 * On-demand variable product picker for PurioChat.
 */
(function ($) {
  "use strict";

  function escapeHtml(value) {
    return String(value)
      .replace(/&/g, "&amp;")
      .replace(/</g, "&lt;")
      .replace(/>/g, "&gt;")
      .replace(/"/g, "&quot;")
      .replace(/'/g, "&#039;");
  }

  function updateCartBadge(count) {
    var $badges = $(".listeo-ai-cart-badge");
    if (count > 0) {
      $badges.text(count).show();
    } else {
      $badges.hide();
    }
  }

  function logCartEvent($overlay, productId) {
    var conversationId = $overlay.data("conversation-id") || "";
    if (!conversationId) return;

    $.ajax({
      url: listeoAiChatConfig.ajaxUrl,
      method: "POST",
      data: {
        action: "listeo_ai_log_cart_event",
        nonce: listeoAiChatConfig.cartNonce,
        conversation_id: conversationId,
        product_id: productId,
        product_name: $overlay.data("product-name") || "",
        quantity: 1,
      },
    });
  }

  function renderError($content, message) {
    $content.html(
      '<div class="listeo-ai-variation-message error">' +
      escapeHtml(message || listeoAiChatConfig.strings.cartErrorAdd || "Could not load product options.") +
      "</div>"
    );
  }

  function renderPicker($overlay, data) {
    var html = '<div class="listeo-ai-variation-product">';
    html += '<img class="listeo-ai-variation-image" src="' + escapeHtml(data.image || "") + '" alt="' + escapeHtml(data.title || "") + '">';
    html += '<div class="listeo-ai-variation-summary">';
    html += '<div class="listeo-ai-variation-title">' + escapeHtml(data.title || "") + "</div>";
    html += '<div class="listeo-ai-variation-price">' + (data.price_html || "") + "</div>";
    html += "</div></div>";
    html += '<div class="listeo-ai-variation-fields">';

    (data.attributes || []).forEach(function (attribute) {
      html += '<label class="listeo-ai-variation-field">';
      html += '<span>' + escapeHtml(attribute.label || "") + "</span>";
      html += '<span class="listeo-ai-variation-select-wrap">';
      html += '<select class="listeo-ai-variation-select" data-attribute-key="' + escapeHtml(attribute.key || "") + '">';
      html += '<option value="" selected>' + escapeHtml(listeoAiChatConfig.strings.chooseOption || "Choose an option") + "</option>";
      (attribute.options || []).forEach(function (option) {
        html += '<option value="' + escapeHtml(option.value || "") + '">' + escapeHtml(option.label || option.value || "") + "</option>";
      });
      html += "</select></span></label>";
    });

    html += "</div>";
    html += '<button type="button" class="listeo-ai-variation-add-btn" disabled>' + escapeHtml(listeoAiChatConfig.strings.addToCart || "Add to Cart") + "</button>";

    $overlay.data("variation-data", data).removeData("variation-id");
    $overlay.find(".listeo-ai-variation-content").html(html);
  }

  function loadProduct($overlay, productId) {
    var $content = $overlay.find(".listeo-ai-variation-content");
    $overlay.removeData("variation-id").data("product-id", productId);
    $content.html(
      '<div class="listeo-ai-cart-loading"><svg class="listeo-ai-cart-spinner" width="18" height="18" viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg"><circle cx="12" cy="12" r="10" fill="none" stroke="currentColor" stroke-width="2.5" stroke-dasharray="47" stroke-dashoffset="15" stroke-linecap="round"/></svg></div>'
    );

    $.ajax({
      url: listeoAiChatConfig.ajaxUrl,
      method: "POST",
      data: {
        action: "listeo_ai_get_product_variations",
        nonce: listeoAiChatConfig.cartNonce,
        product_id: productId,
      },
      success: function (response) {
        if (response.success) {
          renderPicker($overlay, response.data);
        } else {
          renderError($content, response.data?.message);
        }
      },
      error: function () {
        renderError($content);
      },
    });
  }

  function updateSelection($overlay) {
    var data = $overlay.data("variation-data") || {};
    var selected = {};
    var allSelected = true;

    $overlay.find(".listeo-ai-variation-select").each(function () {
      var value = $(this).val();
      selected[$(this).attr("data-attribute-key")] = value;
      if (!value) allSelected = false;
    });

    var matchingVariation = null;
    if (allSelected) {
      (data.variations || []).some(function (variation) {
        var matches = Object.keys(selected).every(function (key) {
          var expected = variation.attributes?.[key] || "";
          return !expected || expected === selected[key];
        });
        if (matches) matchingVariation = variation;
        return matches;
      });
    }

    var $button = $overlay.find(".listeo-ai-variation-add-btn");
    var $price = $overlay.find(".listeo-ai-variation-price");
    var $image = $overlay.find(".listeo-ai-variation-image");

    if (!allSelected) {
      $overlay.removeData("variation-id");
      $button.prop("disabled", true);
      $price.html(data.price_html || "");
      $image.attr("src", data.image || "");
      return;
    }

    if (!matchingVariation || !matchingVariation.in_stock || !matchingVariation.purchasable) {
      $overlay.removeData("variation-id");
      $button.prop("disabled", true);
      return;
    }

    $overlay.data("variation-id", matchingVariation.id);
    $button.prop("disabled", false);
    $price.html(matchingVariation.price_html || data.price_html || "");
    if (matchingVariation.image) $image.attr("src", matchingVariation.image);
  }

  function addSelectedVariation($button) {
    var $overlay = $button.closest(".listeo-ai-variation-overlay");
    var variationId = parseInt($overlay.data("variation-id"), 10) || 0;
    var productId = parseInt($overlay.data("product-id"), 10) || 0;
    if ($button.prop("disabled") || !variationId || !productId) return;

    var variationAttributes = {};
    $overlay.find(".listeo-ai-variation-select").each(function () {
      variationAttributes[$(this).attr("data-attribute-key")] = $(this).val();
    });

    var originalText = listeoAiChatConfig.strings.addToCart || "Add to Cart";
    $button.prop("disabled", true).addClass("loading").text(listeoAiChatConfig.strings.addingToCart || "Adding...");

    $.ajax({
      url: listeoAiChatConfig.ajaxUrl,
      method: "POST",
      data: {
        action: "listeo_ai_add_to_cart",
        nonce: listeoAiChatConfig.cartNonce,
        product_id: productId,
        variation_id: variationId,
        variation: variationAttributes,
        quantity: 1,
      },
      success: function (response) {
        if (response.success) {
          $button.removeClass("loading").addClass("added").text(listeoAiChatConfig.strings.addedToCart || "Added!");
          updateCartBadge(response.data.cart_count);
          logCartEvent($overlay, productId);
          setTimeout(function () {
            $overlay.fadeOut(200);
            $button.removeClass("added").text(originalText);
          }, 900);
        } else {
          $button.removeClass("loading").prop("disabled", false).text(response.data?.message || listeoAiChatConfig.strings.cartErrorAdd || "Error");
          setTimeout(function () { $button.text(originalText); }, 2000);
        }
      },
      error: function () {
        $button.removeClass("loading").prop("disabled", false).text(listeoAiChatConfig.strings.cartErrorAdd || "Error");
        setTimeout(function () { $button.text(originalText); }, 2000);
      },
    });
  }

  $(document).on("change", ".listeo-ai-variation-select", function () {
    updateSelection($(this).closest(".listeo-ai-variation-overlay"));
  });

  $(document).on("click", ".listeo-ai-variation-add-btn", function (event) {
    event.preventDefault();
    addSelectedVariation($(this));
  });

  window.PurioProductVariations = {
    open: function ($button) {
      var url = $button.data("url");
      var productId = parseInt($button.data("product-id"), 10) || 0;
      var $chatWrapper = $button.closest(".listeo-ai-chat-wrapper");
      var $overlay = $chatWrapper.find(".listeo-ai-variation-overlay").first();

      if (!$overlay.length || !productId) {
        if (url) window.location.href = url;
        return;
      }

      $overlay
        .data("product-name", $button.closest(".listeo-ai-listing-item").find(".listeo-ai-listing-title").text().trim())
        .data("conversation-id", $chatWrapper.data("session-id") || "")
        .fadeIn(200);

      loadProduct($overlay, productId);
    },
  };
})(jQuery);
