<?php
/**
 * License Tab for Admin Interface
 *
 * Adds License tab to AI Chat & Search admin page
 * Handles license activation, deactivation, and display
 *
 * @package AI_Chat_Search_Pro
 * @since 1.0.0
 */

// Prevent direct access
if (!defined("ABSPATH")) {
    exit();
}

class AI_Chat_Search_Pro_Admin_License_Tab
{
    /**
     * Single instance
     */
    private static $instance = null;

    /**
     * License manager instance
     */
    private $license_manager;

    /**
     * Get instance
     */
    public static function get_instance()
    {
        if (null === self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    /**
     * Constructor
     */
    private function __construct()
    {
        // Use proxy-based license manager
        $this->license_manager = AI_Chat_Search_Pro_Proxy_License_Manager::get_instance();

        // Add License tab to navigation
        add_action(
            "listeo_ai_search_admin_nav_tabs",
            [$this, "add_license_tab"],
            10,
            1,
        );

        // Render License tab content
        add_action(
            "listeo_ai_search_admin_tab_content",
            [$this, "render_license_tab_content"],
            10,
            1,
        );

        // Handle AJAX requests
        add_action("wp_ajax_ai_chat_search_activate_license", [
            $this,
            "ajax_activate_license",
        ]);
        add_action("wp_ajax_ai_chat_search_deactivate_license", [
            $this,
            "ajax_deactivate_license",
        ]);
        add_action("wp_ajax_ai_chat_search_validate_license", [
            $this,
            "ajax_validate_license",
        ]);
    }

    /**
     * Add License tab to admin navigation
     *
     * @param string $active_tab Current active tab
     */
    public function add_license_tab($active_tab)
    {
        ?>
        <a href="?page=ai-chat-search&tab=license"
           class="nav-tab <?php echo $active_tab == "license"
               ? "nav-tab-active"
               : ""; ?>">
            <svg xmlns="http://www.w3.org/2000/svg" width="12.8" height="16" viewBox="0 0 16 20">
                <g transform="translate(-4 -2)">
                    <path d="M15,9c1.886,0,2.828,0,3.414.586S19,11.114,19,13v4c0,1.886,0,2.828-.586,3.414S16.886,21,15,21H9c-1.886,0-2.828,0-3.414-.586S5,18.886,5,17V13c0-1.886,0-2.828.586-3.414S7.114,9,9,9h6Z" fill="#6aa9ff" opacity="0.1"/>
                    <path d="M13,15a1,1,0,1,1-1-1A1,1,0,0,1,13,15Z" fill="none" stroke="#006aff" stroke-width="2"/>
                    <path d="M15,9c1.886,0,2.828,0,3.414.586S19,11.114,19,13v4c0,1.886,0,2.828-.586,3.414S16.886,21,15,21H9c-1.886,0-2.828,0-3.414-.586S5,18.886,5,17V13c0-1.886,0-2.828.586-3.414S7.114,9,9,9h6Z" fill="none" stroke="#006aff" stroke-linejoin="round" stroke-width="2"/>
                    <path d="M9,9V5a2,2,0,0,1,2-2h2.063A1.937,1.937,0,0,1,15,4.938h0V5" fill="none" stroke="#006aff" stroke-linecap="round" stroke-linejoin="round" stroke-width="2"/>
                </g>
            </svg>
            <?php _e("License", "ai-chat-search-pro"); ?>
        </a>
        <?php
    }

    /**
     * Render License tab content
     *
     * @param string $active_tab Current active tab
     */
    public function render_license_tab_content($active_tab)
    {
        if ($active_tab !== "license") {
            return;
        }

        $license_status = $this->license_manager->get_license_status();
        $license_key_masked = $this->license_manager->get_license_key_masked();
        $license_data = $this->license_manager->get_license_data();
        $last_check = $this->license_manager->get_last_check_time();
        $api_mode = "proxy"; // Always proxy mode now
        $instance_id = get_option(
            AI_Chat_Search_Pro_Proxy_License_Manager::OPTION_LICENSE_INSTANCE_ID,
            "",
        );
        ?>
        <div class="airs-tab-content airs-license-tab">

            <!-- License Status & Information (Combined) -->
            <?php if ($license_status === "valid" && !empty($license_data)): ?>
            <div class="airs-card airs-license-card">
                <div class="airs-card-header">
                    <h3><?php _e(
                        "License Status",
                        "ai-chat-search-pro",
                    ); ?></h3>
                    <p><?php _e(
                        "Your AI Chat & Search Pro license information.",
                        "ai-chat-search-pro",
                    ); ?></p>
                </div>
                <div class="airs-card-body">
                    <!-- Status Badge -->
                    <div class="license-status-badge status-valid">
                        <span class="status-icon">
                            <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                <path d="M22 11.08V12a10 10 0 1 1-5.93-9.14"/>
                                <polyline points="22 4 12 14.01 9 11.01"/>
                            </svg>
                        </span>
                        <div class="status-content">
                            <div class="status-title"><?php _e(
                                "License Active",
                                "ai-chat-search-pro",
                            ); ?></div>
                            <div class="status-message"><?php _e(
                                "All Pro features are unlocked",
                                "ai-chat-search-pro",
                            ); ?></div>
                        </div>
                    </div>

                    <!-- License Details -->
                    <div class="license-details">
                        <div class="license-detail-row">
                            <span class="detail-label"><?php _e(
                                "Product",
                                "ai-chat-search-pro",
                            ); ?></span>
                            <span class="detail-value"><?php echo esc_html(
                                $license_data["product"]["name"],
                            ); ?></span>
                        </div>

                        <div class="license-detail-row">
                            <span class="detail-label"><?php _e(
                                "License Key",
                                "ai-chat-search-pro",
                            ); ?></span>
                            <span class="detail-value"><code class="license-key-code"><?php echo esc_html(
                                $license_key_masked,
                            ); ?></code></span>
                        </div>

                        <div class="license-detail-row">
                            <span class="detail-label"><?php _e(
                                "Activated On",
                                "ai-chat-search-pro",
                            ); ?></span>
                            <span class="detail-value"><?php echo esc_html(
                                date_i18n(
                                    get_option("date_format"),
                                    strtotime($license_data["created_at"]),
                                ),
                            ); ?></span>
                        </div>

                        <?php if ($last_check > 0): ?>
                        <div class="license-detail-row">
                            <span class="detail-label"><?php _e(
                                "Last Validated",
                                "ai-chat-search-pro",
                            ); ?></span>
                            <span class="detail-value"><?php echo esc_html(
                                human_time_diff(
                                    $last_check,
                                    current_time("timestamp"),
                                ),
                            ); ?> <?php _e(
     "ago",
     "ai-chat-search-pro",
 ); ?></span>
                        </div>
                        <?php endif; ?>
                    </div>

                    <!-- Actions -->
                    <div class="license-actions">
                        <button type="button" class="airs-button airs-button-secondary" id="validate-license-btn">
                            <span class="dashicons dashicons-update"></span>
                            <?php _e(
                                "Validate License",
                                "ai-chat-search-pro",
                            ); ?>
                        </button>
                        <button type="button" class="airs-button airs-button-danger" id="deactivate-license-btn">
                            <span class="dashicons dashicons-dismiss"></span>
                            <?php _e(
                                "Deactivate License",
                                "ai-chat-search-pro",
                            ); ?>
                        </button>
                    </div>

                    <div id="license-action-message" style="margin-top: 15px;"></div>

                    <!-- Support Notice -->
                    <div class="airs-support-notice">
                        <p>
                            <?php _e(
                                "Need support? Contact us at",
                                "ai-chat-search-pro",
                            ); ?>
                            <a href="mailto:plugins@purethemes.net">plugins@purethemes.net</a>
                        </p>
                    </div>
                </div>
            </div>
            <?php endif; ?>

            <!-- License Activation Form -->
            <?php if (
                $license_status === "inactive" ||
                $license_status === "invalid"
            ): ?>
            <div class="airs-card airs-license-card">
                <div class="airs-card-header">
                    <h3><?php _e(
                        "Activate License",
                        "ai-chat-search-pro",
                    ); ?></h3>
                    <p><?php _e(
                        "Enter your license key to unlock all Pro features.",
                        "ai-chat-search-pro",
                    ); ?></p>
                </div>
                <div class="airs-card-body">
                    <!-- Status Badge -->
                    <?php if ($license_status === "invalid"): ?>
                    <div class="license-status-badge status-invalid">
                        <span class="status-icon">
                            <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                <circle cx="12" cy="12" r="10"/>
                                <line x1="15" y1="9" x2="9" y2="15"/>
                                <line x1="9" y1="9" x2="15" y2="15"/>
                            </svg>
                        </span>
                        <div class="status-content">
                            <div class="status-title"><?php _e(
                                "License Invalid",
                                "ai-chat-search-pro",
                            ); ?></div>
                            <div class="status-message"><?php _e(
                                "Please activate a valid license",
                                "ai-chat-search-pro",
                            ); ?></div>
                        </div>
                    </div>
                    <?php else: ?>
                    <div class="license-status-badge status-inactive">
                        <span class="status-icon">
                            <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                <path d="M10.29 3.86L1.82 18a2 2 0 0 0 1.71 3h16.94a2 2 0 0 0 1.71-3L13.71 3.86a2 2 0 0 0-3.42 0z"/>
                                <line x1="12" y1="9" x2="12" y2="13"/>
                                <line x1="12" y1="17" x2="12.01" y2="17"/>
                            </svg>
                        </span>
                        <div class="status-content">
                            <div class="status-title"><?php _e(
                                "No License Active",
                                "ai-chat-search-pro",
                            ); ?></div>
                            <div class="status-message"><?php _e(
                                "Activate your license to unlock Pro features",
                                "ai-chat-search-pro",
                            ); ?></div>
                        </div>
                    </div>
                    <?php endif; ?>

                    <form id="license-activation-form">
                        <div class="airs-form-group">
                            <input type="text"
                                   id="license_key"
                                   name="license_key"
                                   class="airs-input"
                                   placeholder="<?php esc_attr_e(
                                       "Enter your license key...",
                                       "ai-chat-search-pro",
                                   ); ?>"
                                   required>
                            <p class="airs-help-text">
                                <?php _e(
                                    "You can find your license key in your purchase confirmation email or account dashboard.",
                                    "ai-chat-search-pro",
                                ); ?>
                            </p>
                        </div>

                        <div class="airs-form-actions">
                            <button type="submit" class="airs-button airs-button-primary" id="activate-license-btn">
                                <span class="dashicons dashicons-yes"></span>
                                <?php _e(
                                    "Activate License",
                                    "ai-chat-search-pro",
                                ); ?>
                            </button>
                            <?php if ($license_status !== "inactive"): ?>
                            <button type="button" class="airs-button airs-button-danger" id="deactivate-license-btn-form">
                                <span class="dashicons dashicons-dismiss"></span>
                                <?php _e(
                                    "Deactivate License",
                                    "ai-chat-search-pro",
                                ); ?>
                            </button>
                            <?php endif; ?>
                        </div>

                        <div id="license-activation-message" style="margin-top: 15px;"></div>
                    </form>

                    <!-- Support Notice -->
                    <div class="airs-support-notice">
                        <p>
                            <?php _e(
                                "Need support? Contact us at",
                                "ai-chat-search-pro",
                            ); ?>
                            <a href="mailto:plugins@purethemes.net">plugins@purethemes.net</a>
                        </p>
                    </div>
                </div>
            </div>
            <?php endif; ?>

        </div>

        <script>
        jQuery(document).ready(function($) {
            // Activate License
            $('#license-activation-form').on('submit', function(e) {
                e.preventDefault();

                var $form = $(this);
                var $btn = $('#activate-license-btn');
                var $message = $('#license-activation-message');

                var licenseKey = $('#license_key').val().trim();

                if (!licenseKey) {
                    $message.html('<div class="notice notice-error"><p><?php _e(
                        "Please enter a license key.",
                        "ai-chat-search-pro",
                    ); ?></p></div>');
                    return;
                }

                $btn.prop('disabled', true).text('<?php _e(
                    "Activating...",
                    "ai-chat-search-pro",
                ); ?>');
                $message.html('');

                $.ajax({
                    url: ajaxurl,
                    type: 'POST',
                    data: {
                        action: 'ai_chat_search_activate_license',
                        nonce: '<?php echo wp_create_nonce(
                            "ai_chat_search_license_nonce",
                        ); ?>',
                        license_key: licenseKey
                    },
                    success: function(response) {
                        if (response.success) {
                            $message.html('<div class="notice notice-success"><p>' + response.data.message + '</p></div>');
                            setTimeout(function() {
                                location.reload();
                            }, 5000);
                        } else {
                            $message.html('<div class="notice notice-error"><p>' + response.data.message + '</p></div>');
                            $btn.prop('disabled', false).text('<?php _e(
                                "Activate License",
                                "ai-chat-search-pro",
                            ); ?>');
                        }
                    },
                    error: function() {
                        $message.html('<div class="notice notice-error"><p><?php _e(
                            "Connection error. Please try again.",
                            "ai-chat-search-pro",
                        ); ?></p></div>');
                        $btn.prop('disabled', false).text('<?php _e(
                            "Activate License",
                            "ai-chat-search-pro",
                        ); ?>');
                    }
                });
            });

            // Deactivate License (works for both buttons)
            $('#deactivate-license-btn, #deactivate-license-btn-form').on('click', function() {
                if (!confirm('<?php _e(
                    "Are you sure you want to deactivate this license? Pro features will be locked.",
                    "ai-chat-search-pro",
                ); ?>')) {
                    return;
                }

                var $btn = $(this);
                var $message = $('#license-action-message');

                $btn.prop('disabled', true).text('<?php _e(
                    "Deactivating...",
                    "ai-chat-search-pro",
                ); ?>');
                $message.html('');

                $.ajax({
                    url: ajaxurl,
                    type: 'POST',
                    data: {
                        action: 'ai_chat_search_deactivate_license',
                        nonce: '<?php echo wp_create_nonce(
                            "ai_chat_search_license_nonce",
                        ); ?>'
                    },
                    success: function(response) {
                        if (response.success) {
                            $message.html('<div class="notice notice-success"><p>' + response.data.message + '</p></div>');
                            setTimeout(function() {
                                location.reload();
                            }, 5000);
                        } else {
                            $message.html('<div class="notice notice-error"><p>' + response.data.message + '</p></div>');
                            $btn.prop('disabled', false).text('<?php _e(
                                "Deactivate License",
                                "ai-chat-search-pro",
                            ); ?>');
                        }
                    },
                    error: function() {
                        $message.html('<div class="notice notice-error"><p><?php _e(
                            "Connection error. Please try again.",
                            "ai-chat-search-pro",
                        ); ?></p></div>');
                        $btn.prop('disabled', false).text('<?php _e(
                            "Deactivate License",
                            "ai-chat-search-pro",
                        ); ?>');
                    }
                });
            });

            // Validate License
            $('#validate-license-btn').on('click', function() {
                var $btn = $(this);
                var $message = $('#license-action-message');

                $btn.prop('disabled', true).text('<?php _e(
                    "Validating...",
                    "ai-chat-search-pro",
                ); ?>');
                $message.html('');

                $.ajax({
                    url: ajaxurl,
                    type: 'POST',
                    data: {
                        action: 'ai_chat_search_validate_license',
                        nonce: '<?php echo wp_create_nonce(
                            "ai_chat_search_license_nonce",
                        ); ?>'
                    },
                    success: function(response) {
                        if (response.success) {
                            $message.html('<div class="notice notice-success"><p>' + response.data.message + '</p></div>');
                            if (response.data.reload) {
                                setTimeout(function() {
                                    location.reload();
                                }, 5000);
                            }
                        } else {
                            $message.html('<div class="notice notice-error"><p>' + response.data.message + '</p></div>');
                        }
                        $btn.prop('disabled', false).text('<?php _e(
                            "Validate License",
                            "ai-chat-search-pro",
                        ); ?>');
                    },
                    error: function() {
                        $message.html('<div class="notice notice-error"><p><?php _e(
                            "Connection error. Please try again.",
                            "ai-chat-search-pro",
                        ); ?></p></div>');
                        $btn.prop('disabled', false).text('<?php _e(
                            "Validate License",
                            "ai-chat-search-pro",
                        ); ?>');
                    }
                });
            });
        });
        </script>
        <?php
    }

    /**
     * AJAX: Activate License
     */
    public function ajax_activate_license()
    {
        check_ajax_referer("ai_chat_search_license_nonce", "nonce");

        if (!current_user_can("manage_options")) {
            wp_send_json_error([
                "message" => __("Permission denied.", "ai-chat-search-pro"),
            ]);
        }

        $license_key = isset($_POST["license_key"])
            ? sanitize_text_field($_POST["license_key"])
            : "";

        $result = $this->license_manager->activate_license($license_key);

        if ($result["success"]) {
            wp_send_json_success($result);
        } else {
            wp_send_json_error($result);
        }
    }

    /**
     * AJAX: Deactivate License
     */
    public function ajax_deactivate_license()
    {
        check_ajax_referer("ai_chat_search_license_nonce", "nonce");

        if (!current_user_can("manage_options")) {
            wp_send_json_error([
                "message" => __("Permission denied.", "ai-chat-search-pro"),
            ]);
        }

        $result = $this->license_manager->deactivate_license();

        if ($result["success"]) {
            wp_send_json_success($result);
        } else {
            wp_send_json_error($result);
        }
    }

    /**
     * AJAX: Validate License
     */
    public function ajax_validate_license()
    {
        check_ajax_referer("ai_chat_search_license_nonce", "nonce");

        if (!current_user_can("manage_options")) {
            wp_send_json_error([
                "message" => __("Permission denied.", "ai-chat-search-pro"),
            ]);
        }

        $is_valid = $this->license_manager->validate_license(true); // Force validation

        if ($is_valid) {
            wp_send_json_success([
                "message" => __(
                    "License validated successfully!",
                    "ai-chat-search-pro",
                ),
                "reload" => false,
            ]);
        } else {
            wp_send_json_error([
                "message" => __(
                    "License validation failed. Please check your license status.",
                    "ai-chat-search-pro",
                ),
                "reload" => true,
            ]);
        }
    }
}
