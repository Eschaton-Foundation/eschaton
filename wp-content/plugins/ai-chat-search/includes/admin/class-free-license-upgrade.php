<?php
/**
 * Free License Upgrade Tab
 *
 * Provides a Free-to-Pro upgrade path from the PurioChat admin screen.
 *
 * @package Listeo_AI_Search
 */

// Prevent direct access
if (!defined("ABSPATH")) {
    exit();
}

class Listeo_AI_Search_Free_License_Upgrade
{
    const AJAX_ACTION = "ai_chat_search_free_pro_upgrade";
    const NONCE_ACTION = "ai_chat_search_free_pro_upgrade_nonce";
    const PRO_PLUGIN_FILE = "ai-chat-search-pro/ai-chat-search-pro.php";
    const PRO_PACKAGE_SLUG = "ai-chat-search-pro";
    const PRO_MANIFEST_URL = "https://purethemes.net/license/plugins/ai-chat-search-pro-updates.json";
    const PROTECTED_UPDATE_CHECK_URL = "https://purethemes.net/wp-json/purethemes-license-proxy/v1/check-plugin-update";

    /**
     * Constructor.
     */
    public function __construct()
    {
        add_filter("listeo_ai_search_admin_sidebar_tabs", [
            $this,
            "add_sidebar_tab",
        ]);
        add_action("listeo_ai_search_admin_nav_tabs", [
            $this,
            "add_license_tab",
        ]);
        add_action("listeo_ai_search_admin_tab_content", [
            $this,
            "render_license_tab_content",
        ]);
        add_action("wp_ajax_" . self::AJAX_ACTION, [
            $this,
            "ajax_install_and_activate",
        ]);
    }

    /**
     * Add the License sidebar item while Pro is not providing its own tab.
     *
     * @param array $tabs Existing admin sidebar tabs.
     * @return array
     */
    public function add_sidebar_tab($tabs)
    {
        if ($this->has_pro_license_tab()) {
            return $tabs;
        }

        $tabs[] = [
            "slug" => "license",
            "label" => __("License", "ai-chat-search"),
        ];

        return $tabs;
    }

    /**
     * Add the top License tab while Pro is not providing its own tab.
     *
     * @param string $active_tab Current active tab.
     */
    public function add_license_tab($active_tab)
    {
        if ($this->has_pro_license_tab()) {
            return;
        }
        ?>
        <a href="<?php echo esc_url(admin_url("admin.php?page=ai-chat-search&tab=license")); ?>"
           class="nav-tab <?php echo $active_tab === "license" ? "nav-tab-active" : ""; ?>">
            <svg xmlns="http://www.w3.org/2000/svg" width="12.8" height="16" viewBox="0 0 16 20" aria-hidden="true">
                <g transform="translate(-4 -2)">
                    <path d="M15,9c1.886,0,2.828,0,3.414.586S19,11.114,19,13v4c0,1.886,0,2.828-.586,3.414S16.886,21,15,21H9c-1.886,0-2.828,0-3.414-.586S5,18.886,5,17V13c0-1.886,0-2.828.586-3.414S7.114,9,9,9h6Z" fill="#6aa9ff" opacity="0.1"/>
                    <path d="M13,15a1,1,0,1,1-1-1A1,1,0,0,1,13,15Z" fill="none" stroke="#006aff" stroke-width="2"/>
                    <path d="M15,9c1.886,0,2.828,0,3.414.586S19,11.114,19,13v4c0,1.886,0,2.828-.586,3.414S16.886,21,15,21H9c-1.886,0-2.828,0-3.414-.586S5,18.886,5,17V13c0-1.886,0-2.828.586-3.414S7.114,9,9,9h6Z" fill="none" stroke="#006aff" stroke-linejoin="round" stroke-width="2"/>
                    <path d="M9,9V5a2,2,0,0,1,2-2h2.063A1.937,1.937,0,0,1,15,4.938h0V5" fill="none" stroke="#006aff" stroke-linecap="round" stroke-linejoin="round" stroke-width="2"/>
                </g>
            </svg>
            <?php _e("License", "ai-chat-search"); ?>
        </a>
        <?php
    }

    /**
     * Render the Free upgrade License tab.
     *
     * @param string $active_tab Current active tab.
     */
    public function render_license_tab_content($active_tab)
    {
        if ($active_tab !== "license" || $this->has_pro_license_tab()) {
            return;
        }

        $pro_plugin_file = $this->find_pro_plugin_file();
        $pro_installed = !empty($pro_plugin_file);
        $upgrade_url = AI_Chat_Search_Pro_Manager::get_upgrade_url(
            "free_license_tab"
        );
        $can_upgrade = $pro_installed
            ? current_user_can("activate_plugins")
            : current_user_can("install_plugins") &&
                current_user_can("activate_plugins");
        $permission_message = $pro_installed
            ? __(
                "You need permission to activate plugins to use automatic Pro activation.",
                "ai-chat-search"
            )
            : __(
                "You need permission to install and activate plugins to use automatic Pro upgrade.",
                "ai-chat-search"
            );
        $card_title = $pro_installed
            ? __("Activate PurioChat Pro", "ai-chat-search")
            : __("Upgrade to PurioChat Pro", "ai-chat-search");
        $card_description = $pro_installed
            ? __(
                "PurioChat Pro is already installed. Enter your license key to activate it and finish setup.",
                "ai-chat-search"
            )
            : __(
                "Paste your license key below — Pro installs and activates automatically.",
                "ai-chat-search"
            );
        $status_title = $pro_installed
            ? __("Pro Installed, Activation Needed", "ai-chat-search")
            : __("One-click upgrade", "ai-chat-search");
        $status_message = $pro_installed
            ? __(
                "No download is needed. Your license key will activate the installed Pro plugin.",
                "ai-chat-search"
            )
            : __(
                "Enter your key to install Pro automatically. Settings and trained data stay intact.",
                "ai-chat-search"
            );
        $button_text = $pro_installed
            ? __("Activate Installed Pro", "ai-chat-search")
            : __("Install & Activate Pro", "ai-chat-search");
        $busy_text = $pro_installed
            ? __("Activating Pro...", "ai-chat-search")
            : __("Installing Pro...", "ai-chat-search");
        ?>
        <div class="airs-tab-content airs-license-tab airs-free-license-upgrade">
            <div class="airs-card airs-license-card">
                <div class="airs-card-header">
                    <h3><?php echo esc_html($card_title); ?></h3>
                    <p><?php echo esc_html($card_description); ?></p>
                </div>
                <div class="airs-card-body">
                    <div class="license-status-badge status-inactive">
                        <span class="status-icon">
                            <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                                <path d="M21 16V8a2 2 0 0 0-1-1.73l-7-4a2 2 0 0 0-2 0l-7 4A2 2 0 0 0 3 8v8a2 2 0 0 0 1 1.73l7 4a2 2 0 0 0 2 0l7-4A2 2 0 0 0 21 16Z"/>
                                <path d="M12 22V12"/>
                                <path d="m3.3 7 8.7 5 8.7-5"/>
                            </svg>
                        </span>
                        <div class="status-content">
                            <div class="status-title">
                                <?php echo esc_html($status_title); ?>
                            </div>
                            <div class="status-message">
                                <?php echo esc_html($status_message); ?>
                            </div>
                        </div>
                    </div>

                    <?php if (!$can_upgrade): ?>
                        <div class="notice notice-error inline">
                            <p><?php echo esc_html($permission_message); ?></p>
                        </div>
                    <?php else: ?>
                        <form id="free-license-upgrade-form" method="post">
                            <div class="airs-form-group">
                                <input type="text"
                                       id="free_license_key"
                                       name="license_key"
                                       class="airs-input"
                                       autocomplete="off"
                                       placeholder="<?php esc_attr_e("Enter your license key...", "ai-chat-search"); ?>"
                                       required>
                                <p class="airs-help-text">
                                    <?php _e("Your license key is in the purchase confirmation email.", "ai-chat-search"); ?>
                                </p>
                            </div>

                            <div class="airs-form-actions license-actions">
                                <button type="submit"
                                        class="airs-button airs-free-license-primary"
                                        id="free-license-upgrade-btn"
                                        data-busy-text="<?php echo esc_attr($busy_text); ?>">
                                    <svg class="airs-button-icon" xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" aria-hidden="true">
                                        <path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
                                        <path d="M7 10l5 5 5-5" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
                                        <path d="M12 15V3" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
                                    </svg>
                                    <?php echo esc_html($button_text); ?>
                                </button>
                            </div>

                            <div id="free-license-upgrade-message" class="airs-free-license-message"></div>
                        </form>
                        <p class="airs-license-purchase-link">
                            <?php esc_html_e("Don't have a license yet?", "ai-chat-search"); ?>
                            <a href="<?php echo esc_url($upgrade_url); ?>" target="_blank" rel="noopener">
                                <?php esc_html_e("Get PurioChat Pro →", "ai-chat-search"); ?>
                            </a>
                        </p>
                    <?php endif; ?>

                    <div class="airs-support-notice airs-blue">
                        <p>
                            <?php _e("Questions about your license or activation?", "ai-chat-search"); ?>
                            <a href="mailto:plugins@purethemes.net">plugins@purethemes.net</a>
                        </p>
                    </div>
                </div>
            </div>
        </div>

        <script>
        jQuery(function($) {
            var $form = $('#free-license-upgrade-form');
            if (!$form.length) {
                return;
            }

            function showMessage(type, message) {
                var $message = $('#free-license-upgrade-message');
                var noticeClass = type === 'success' ? 'notice-success' : 'notice-error';
                var $notice = $('<div/>', { 'class': 'notice ' + noticeClass + ' inline' });
                $notice.append($('<p/>').text(message));
                $message.empty().append($notice);
            }

            $form.on('submit', function(e) {
                e.preventDefault();

                var $btn = $('#free-license-upgrade-btn');
                var defaultHtml = $btn.html();
                var licenseKey = $('#free_license_key').val().trim();

                if (!licenseKey) {
                    showMessage('error', '<?php echo esc_js(__("Please enter a license key.", "ai-chat-search")); ?>');
                    return;
                }

                $btn.prop('disabled', true).text($btn.data('busy-text'));
                $('#free-license-upgrade-message').empty();

                $.ajax({
                    url: ajaxurl,
                    method: 'POST',
                    data: {
                        action: '<?php echo esc_js(self::AJAX_ACTION); ?>',
                        nonce: '<?php echo esc_js(wp_create_nonce(self::NONCE_ACTION)); ?>',
                        license_key: licenseKey
                    }
                }).done(function(response) {
                    var data = response && response.data ? response.data : {};
                    var message = data.message || '<?php echo esc_js(__("Unexpected response from WordPress.", "ai-chat-search")); ?>';

                    if (response && response.success) {
                        showMessage('success', message);
                        if (data.redirect_url) {
                            setTimeout(function() {
                                window.location.href = data.redirect_url;
                            }, 1200);
                        }
                        return;
                    }

                    showMessage('error', message);
                    if (data.redirect_url) {
                        setTimeout(function() {
                            window.location.href = data.redirect_url;
                        }, 2200);
                    }
                    $btn.prop('disabled', false).html(defaultHtml);
                }).fail(function() {
                    showMessage('error', '<?php echo esc_js(__("Connection error. Please try again.", "ai-chat-search")); ?>');
                    $btn.prop('disabled', false).html(defaultHtml);
                });
            });
        });
        </script>
        <?php
    }

    /**
     * AJAX: verify license, install Pro, activate Pro, and activate license.
     */
    public function ajax_install_and_activate()
    {
        if (
            !check_ajax_referer(
                self::NONCE_ACTION,
                "nonce",
                false
            )
        ) {
            wp_send_json_error([
                "message" => __("Security check failed.", "ai-chat-search"),
            ]);
        }

        $pro_plugin_file = $this->find_pro_plugin_file();
        $pro_installed = !empty($pro_plugin_file);

        if (!$pro_installed && !current_user_can("install_plugins")) {
            wp_send_json_error([
                "message" => __(
                    "You do not have permission to install plugins.",
                    "ai-chat-search"
                ),
            ]);
        }

        if (!current_user_can("activate_plugins")) {
            wp_send_json_error([
                "message" => __(
                    "You do not have permission to activate plugins.",
                    "ai-chat-search"
                ),
            ]);
        }

        $license_key = isset($_POST["license_key"])
            ? sanitize_text_field(wp_unslash($_POST["license_key"]))
            : "";

        if (empty($license_key)) {
            wp_send_json_error([
                "message" => __("License key is required.", "ai-chat-search"),
            ]);
        }

        $install_result = ["plugin_file" => $pro_plugin_file];
        if (!$pro_installed) {
            $manifest = $this->fetch_pro_manifest();
            if (is_wp_error($manifest)) {
                $this->send_upgrade_error($manifest);
            }

            $requirements = $this->validate_manifest_requirements($manifest);
            if (is_wp_error($requirements)) {
                $this->send_upgrade_error($requirements);
            }

            $download_url = $this->resolve_protected_download_url(
                $manifest["download_url"],
                $manifest["version"],
                $license_key
            );
            if (is_wp_error($download_url)) {
                $this->send_upgrade_error($download_url);
            }

            $install_result = $this->install_pro_plugin($download_url);
            if (is_wp_error($install_result)) {
                $this->send_upgrade_error($install_result);
            }
        }

        $pro_was_active = $this->is_plugin_active_file(
            $install_result["plugin_file"]
        );
        $activation = $this->activate_pro_plugin($install_result["plugin_file"]);
        if (is_wp_error($activation)) {
            $this->send_upgrade_error($activation);
        }

        $license_activation = $this->activate_pro_license($license_key);
        if (is_wp_error($license_activation)) {
            delete_transient("ai_chat_search_pro_activated");

            wp_send_json_error([
                "message" => sprintf(
                    /* translators: %s: license activation error message. */
                    $this->get_license_activation_failure_message(
                        $pro_installed,
                        $pro_was_active
                    ),
                    $license_activation->get_error_message()
                ),
                "redirect_url" => $this->get_license_tab_refresh_url(),
            ]);
        }

        wp_send_json_success([
            "message" => $this->get_license_activation_success_message(
                $pro_installed,
                $pro_was_active
            ),
            "redirect_url" => $this->get_license_tab_refresh_url(),
        ]);
    }

    /**
     * Get the License tab URL with a one-time cache-busting parameter.
     *
     * @return string
     */
    private function get_license_tab_refresh_url()
    {
        return add_query_arg(
            "airs_license_refresh",
            time(),
            admin_url("admin.php?page=ai-chat-search&tab=license")
        );
    }

    /**
     * Get copy for failed Pro license activation after install/activation work.
     *
     * @param bool $pro_installed Whether Pro was installed before this request.
     * @param bool $pro_was_active Whether Pro was already active before this request.
     * @return string
     */
    private function get_license_activation_failure_message(
        $pro_installed,
        $pro_was_active
    ) {
        if ($pro_was_active) {
            return __(
                /* translators: %s: license activation error message. */
                "PurioChat Pro is active, but license activation failed: %s",
                "ai-chat-search"
            );
        }

        if ($pro_installed) {
            return __(
                /* translators: %s: license activation error message. */
                "PurioChat Pro was activated, but license activation failed: %s",
                "ai-chat-search"
            );
        }

        return __(
            /* translators: %s: license activation error message. */
            "PurioChat Pro was installed and activated, but license activation failed: %s",
            "ai-chat-search"
        );
    }

    /**
     * Get copy for successful Pro install/activation/license flow.
     *
     * @param bool $pro_installed Whether Pro was installed before this request.
     * @param bool $pro_was_active Whether Pro was already active before this request.
     * @return string
     */
    private function get_license_activation_success_message(
        $pro_installed,
        $pro_was_active
    ) {
        if ($pro_was_active) {
            return __(
                "PurioChat Pro licensed successfully.",
                "ai-chat-search"
            );
        }

        if ($pro_installed) {
            return __(
                "PurioChat Pro activated and licensed successfully.",
                "ai-chat-search"
            );
        }

        return __(
            "PurioChat Pro installed, activated, and licensed successfully.",
            "ai-chat-search"
        );
    }

    /**
     * Fetch the Pro update manifest.
     *
     * @return array|WP_Error
     */
    private function fetch_pro_manifest()
    {
        $manifest_url = apply_filters(
            "ai_chat_search_free_pro_manifest_url",
            self::PRO_MANIFEST_URL
        );

        $response = wp_remote_get($manifest_url, [
            "headers" => [
                "Accept" => "application/json",
            ],
            "timeout" => 10,
            "sslverify" => true,
        ]);

        if (is_wp_error($response)) {
            return new WP_Error(
                "pro_manifest_request_failed",
                $response->get_error_message()
            );
        }

        if ((int) wp_remote_retrieve_response_code($response) !== 200) {
            return new WP_Error(
                "pro_manifest_unavailable",
                __(
                    "Could not load the Pro package manifest. Please try again later.",
                    "ai-chat-search"
                )
            );
        }

        $data = json_decode(wp_remote_retrieve_body($response), true);
        if (
            !is_array($data) ||
            empty($data["version"]) ||
            empty($data["download_url"])
        ) {
            return new WP_Error(
                "pro_manifest_invalid",
                __(
                    "The Pro package manifest is invalid. Please contact PureThemes support.",
                    "ai-chat-search"
                )
            );
        }

        return [
            "version" => sanitize_text_field($data["version"]),
            "download_url" => esc_url_raw($data["download_url"]),
            "requires" => isset($data["requires"])
                ? sanitize_text_field($data["requires"])
                : "",
            "requires_php" => isset($data["requires_php"])
                ? sanitize_text_field($data["requires_php"])
                : "",
        ];
    }

    /**
     * Validate WordPress and PHP requirements from the manifest.
     *
     * @param array $manifest Manifest data.
     * @return true|WP_Error
     */
    private function validate_manifest_requirements($manifest)
    {
        if (
            !empty($manifest["requires_php"]) &&
            version_compare(PHP_VERSION, $manifest["requires_php"], "<")
        ) {
            return new WP_Error(
                "php_version_too_low",
                sprintf(
                    /* translators: %s: required PHP version. */
                    __(
                        "PurioChat Pro requires PHP %s or newer.",
                        "ai-chat-search"
                    ),
                    $manifest["requires_php"]
                )
            );
        }

        if (
            !empty($manifest["requires"]) &&
            version_compare(get_bloginfo("version"), $manifest["requires"], "<")
        ) {
            return new WP_Error(
                "wp_version_too_low",
                sprintf(
                    /* translators: %s: required WordPress version. */
                    __(
                        "PurioChat Pro requires WordPress %s or newer.",
                        "ai-chat-search"
                    ),
                    $manifest["requires"]
                )
            );
        }

        return true;
    }

    /**
     * Resolve the protected package URL to a short-lived signed ZIP URL.
     *
     * @param string $manifest_download_url Protected manifest download URL.
     * @param string $version Manifest version.
     * @param string $license_key License key.
     * @return string|WP_Error
     */
    private function resolve_protected_download_url(
        $manifest_download_url,
        $version,
        $license_key
    ) {
        $package = $this->parse_protected_download_url($manifest_download_url);
        if (
            empty($package["package"]) ||
            $package["package"] !== self::PRO_PACKAGE_SLUG ||
            $package["version"] !== $version
        ) {
            return new WP_Error(
                "invalid_manifest_download_url",
                __(
                    "The Pro package is not configured for secure download. Please contact PureThemes support.",
                    "ai-chat-search"
                )
            );
        }

        $endpoint = apply_filters(
            "ai_chat_search_free_protected_update_check_url",
            self::PROTECTED_UPDATE_CHECK_URL
        );

        $response = wp_remote_post($endpoint, [
            "headers" => [
                "Accept" => "application/json",
                "Content-Type" => "application/json",
            ],
            "body" => wp_json_encode([
                "package" => $package["package"],
                "version" => $version,
                "manifest_download_url" => $manifest_download_url,
                "license_key" => $license_key,
            ]),
            "timeout" => 20,
            "sslverify" => true,
        ]);

        if (is_wp_error($response)) {
            return new WP_Error(
                "protected_download_check_failed",
                $response->get_error_message()
            );
        }

        $body = json_decode(wp_remote_retrieve_body($response), true);
        $response_code = (int) wp_remote_retrieve_response_code($response);

        if ($response_code !== 200) {
            $code = is_array($body) && !empty($body["code"])
                ? sanitize_key($body["code"])
                : "protected_download_check_failed";

            return new WP_Error(
                $code,
                $this->get_public_upgrade_error_message($code)
            );
        }

        if (
            !is_array($body) ||
            empty($body["remote_updates_allowed"]) ||
            empty($body["download_url"])
        ) {
            $code = is_array($body) && !empty($body["code"])
                ? sanitize_key($body["code"])
                : "update_package_unavailable";

            return new WP_Error(
                $code,
                $this->get_public_upgrade_error_message($code)
            );
        }

        return esc_url_raw($body["download_url"]);
    }

    /**
     * Parse protected package details from a manifest URL.
     *
     * @param string $url Download URL.
     * @return array|null
     */
    private function parse_protected_download_url($url)
    {
        $paths = [];
        $path = wp_parse_url($url, PHP_URL_PATH);
        if (!empty($path)) {
            $paths[] = $path;
        }

        $query = wp_parse_url($url, PHP_URL_QUERY);
        if (!empty($query)) {
            parse_str($query, $query_args);
            if (!empty($query_args["rest_route"])) {
                $paths[] = $query_args["rest_route"];
            }
        }

        foreach ($paths as $candidate_path) {
            if (
                preg_match(
                    "~/purethemes-license-proxy/v1/download-package/([a-z0-9-]+)/([^/?#]+)~",
                    $candidate_path,
                    $matches
                )
            ) {
                return [
                    "package" => sanitize_key(rawurldecode($matches[1])),
                    "version" => sanitize_text_field(
                        rawurldecode($matches[2])
                    ),
                ];
            }
        }

        return null;
    }

    /**
     * Install the Pro plugin if it is not already installed.
     *
     * @param string $download_url Signed ZIP URL.
     * @return array|WP_Error
     */
    private function install_pro_plugin($download_url)
    {
        $plugin_file = $this->find_pro_plugin_file();
        if (!empty($plugin_file)) {
            return ["plugin_file" => $plugin_file];
        }

        $this->load_plugin_admin_files();

        if (!class_exists("Plugin_Upgrader")) {
            return new WP_Error(
                "plugin_upgrader_missing",
                __(
                    "WordPress plugin installer is unavailable.",
                    "ai-chat-search"
                )
            );
        }

        $skin = new Automatic_Upgrader_Skin();
        $upgrader = new Plugin_Upgrader($skin);
        $result = $upgrader->install($download_url);

        if (is_wp_error($result)) {
            return $result;
        }

        if (!$result) {
            if (isset($skin->result) && is_wp_error($skin->result)) {
                return $skin->result;
            }

            return new WP_Error(
                "pro_install_failed",
                __(
                    "WordPress could not install the Pro package.",
                    "ai-chat-search"
                )
            );
        }

        wp_clean_plugins_cache(true);

        $plugin_file = $this->find_pro_plugin_file(true);
        if (empty($plugin_file)) {
            return new WP_Error(
                "pro_plugin_not_found",
                __(
                    "The Pro package installed, but WordPress could not find the Pro plugin file.",
                    "ai-chat-search"
                )
            );
        }

        return ["plugin_file" => $plugin_file];
    }

    /**
     * Activate the Pro plugin.
     *
     * @param string $plugin_file Plugin file relative to plugins directory.
     * @return true|WP_Error
     */
    private function activate_pro_plugin($plugin_file)
    {
        $this->load_plugin_admin_files();

        if ($this->is_plugin_active_file($plugin_file)) {
            return true;
        }

        $result = activate_plugin($plugin_file);
        if (is_wp_error($result)) {
            return $result;
        }

        wp_clean_plugins_cache(true);

        return true;
    }

    /**
     * Activate the license through Pro's own license manager.
     *
     * @param string $license_key License key.
     * @return true|WP_Error
     */
    private function activate_pro_license($license_key)
    {
        $plugin_file = $this->find_pro_plugin_file(true);
        if (empty($plugin_file)) {
            return new WP_Error(
                "pro_plugin_not_found",
                __(
                    "PurioChat Pro is active, but its plugin file could not be located.",
                    "ai-chat-search"
                )
            );
        }

        $plugin_path = trailingslashit(WP_PLUGIN_DIR) . $plugin_file;
        $pro_dir = plugin_dir_path($plugin_path);
        $sync_file = $pro_dir . "includes/class-sync.php";
        $license_manager_file =
            $pro_dir . "includes/class-proxy-license-manager.php";

        if (
            !class_exists("AI_Chat_Search_Pro_Sync_Handler") &&
            file_exists($sync_file)
        ) {
            require_once $sync_file;
        }

        if (
            !class_exists("AI_Chat_Search_Pro_Proxy_License_Manager") &&
            file_exists($license_manager_file)
        ) {
            require_once $license_manager_file;
        }

        if (!class_exists("AI_Chat_Search_Pro_Proxy_License_Manager")) {
            return new WP_Error(
                "pro_license_manager_missing",
                __(
                    "PurioChat Pro license manager could not be loaded.",
                    "ai-chat-search"
                )
            );
        }

        $result = AI_Chat_Search_Pro_Proxy_License_Manager::get_instance()->activate_license(
            $license_key
        );

        if (empty($result["success"])) {
            return new WP_Error(
                "pro_license_activation_failed",
                isset($result["message"])
                    ? wp_strip_all_tags($result["message"])
                    : __(
                        "License activation failed. Please try again.",
                        "ai-chat-search"
                    )
            );
        }

        return true;
    }

    /**
     * Find the installed Pro plugin file.
     *
     * @param bool $force_refresh Whether to refresh plugin cache.
     * @return string
     */
    private function find_pro_plugin_file($force_refresh = false)
    {
        $this->load_plugin_admin_files();

        if ($force_refresh && function_exists("wp_clean_plugins_cache")) {
            wp_clean_plugins_cache(true);
        }

        if (file_exists(trailingslashit(WP_PLUGIN_DIR) . self::PRO_PLUGIN_FILE)) {
            return self::PRO_PLUGIN_FILE;
        }

        if (!function_exists("get_plugins")) {
            return "";
        }

        $plugins = get_plugins();
        foreach ($plugins as $plugin_path => $plugin_data) {
            $plugin_name = isset($plugin_data["Name"])
                ? $plugin_data["Name"]
                : "";

            if (
                strpos($plugin_path, self::PRO_PACKAGE_SLUG . "/") === 0 ||
                stripos($plugin_name, "PurioChat Pro") !== false ||
                stripos($plugin_name, "AI Chat & Search Pro") !== false
            ) {
                return $plugin_path;
            }
        }

        return "";
    }

    /**
     * Check active plugin status for a plugin file.
     *
     * @param string $plugin_file Plugin file relative to plugins directory.
     * @return bool
     */
    private function is_plugin_active_file($plugin_file)
    {
        $this->load_plugin_admin_files();

        if (!function_exists("is_plugin_active")) {
            return false;
        }

        return is_plugin_active($plugin_file) ||
            (function_exists("is_plugin_active_for_network") &&
                is_plugin_active_for_network($plugin_file));
    }

    /**
     * Whether Pro is already providing the real License tab.
     *
     * @return bool
     */
    private function has_pro_license_tab()
    {
        return class_exists("AI_Chat_Search_Pro_Admin_License_Tab");
    }

    /**
     * Include WordPress plugin installer dependencies.
     */
    private function load_plugin_admin_files()
    {
        if (!function_exists("get_plugins")) {
            require_once ABSPATH . "wp-admin/includes/plugin.php";
        }

        if (!function_exists("download_url")) {
            require_once ABSPATH . "wp-admin/includes/file.php";
        }

        if (!class_exists("Plugin_Upgrader")) {
            require_once ABSPATH . "wp-admin/includes/class-wp-upgrader.php";
        }
    }

    /**
     * Map protected download errors to safe user-facing messages.
     *
     * @param string $code Error code.
     * @return string
     */
    private function get_public_upgrade_error_message($code)
    {
        $code = is_scalar($code) ? sanitize_key((string) $code) : "";

        switch ($code) {
            case "license_missing":
                return __("Please enter your PurioChat Pro license key.", "ai-chat-search");

            case "license_not_found":
                return __(
                    "Your license could not be verified. Please check your license key or contact PureThemes support.",
                    "ai-chat-search"
                );

            case "license_inactive":
                return __(
                    "Your license is not active. Please reactivate your license or contact PureThemes support.",
                    "ai-chat-search"
                );

            case "product_mismatch":
                return __(
                    "This license is not valid for PurioChat Pro. Please use the correct license key or contact PureThemes support.",
                    "ai-chat-search"
                );

            case "rate_limit_exceeded":
                return __(
                    "Too many license checks. Please wait about one hour and try again.",
                    "ai-chat-search"
                );

            case "invalid_download_token":
            case "download_token_expired":
            case "rest_missing_callback_param":
                return __(
                    "The secure download link expired. Please try again.",
                    "ai-chat-search"
                );

            default:
                return __(
                    "The Pro package is temporarily unavailable. Please contact PureThemes support.",
                    "ai-chat-search"
                );
        }
    }

    /**
     * Send a normalized AJAX error.
     *
     * @param WP_Error $error Error object.
     */
    private function send_upgrade_error($error)
    {
        wp_send_json_error([
            "message" => $error->get_error_message(),
        ]);
    }
}
