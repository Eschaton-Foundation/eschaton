<?php
/**
 * Free MCP upgrade preview.
 *
 * @package Listeo_AI_Search
 */

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Adds an inert MCP preview when the Pro implementation is unavailable.
 */
class Listeo_AI_Search_Free_MCP_Upgrade {

    /**
     * Register admin hooks.
     */
    public function __construct() {
        if ($this->has_mcp()) {
            return;
        }

        add_action('ai_chat_search_chat_sidebar_before_developer', array($this, 'render_sidebar_item'));
        add_action('ai_chat_search_chat_settings_sections', array($this, 'render_settings_section'));
    }

    /**
     * Check whether Pro is providing the real MCP settings.
     *
     * @return bool
     */
    private function has_mcp() {
        return true === apply_filters('listeo_ai_search_mcp_available', false);
    }

    /**
     * Render the locked MCP sidebar item.
     */
    public function render_sidebar_item() {
        if ($this->has_mcp()) {
            return;
        }
        ?>
        <button type="button" class="airs-chat-sidebar-item" data-target="mcp" role="tab" aria-selected="false">
            <span class="airs-chat-sidebar-icon purio-mcp-upgrade__sidebar-icon" aria-hidden="true">
                <?php $this->render_mcp_logo(); ?>
            </span>
            <span class="airs-chat-sidebar-label"><?php esc_html_e('MCP', 'ai-chat-search'); ?></span>
        </button>
        <?php
    }

    /**
     * Render the locked MCP settings preview.
     */
    public function render_settings_section() {
        if ($this->has_mcp() || !current_user_can('manage_options')) {
            return;
        }

        $upgrade_url = AI_Chat_Search_Pro_Manager::get_upgrade_url('mcp_connections');
        ?>
        <div class="airs-card" data-chat-section="mcp">
            <div class="airs-card-header airs-card-header-with-icon">
                <div class="airs-card-icon purio-mcp-upgrade__header-icon" aria-hidden="true">
                    <?php $this->render_mcp_logo(); ?>
                </div>
                <div class="airs-card-header-text">
                    <h3><?php esc_html_e('MCP connections', 'ai-chat-search'); ?></h3>
                    <p><?php esc_html_e('Connect an AI assistant to this website.', 'ai-chat-search'); ?></p>
                </div>
            </div>

            <div class="airs-card-body purio-mcp-upgrade__card-body">
                <div class="ai-chat-pro-feature-locked purio-mcp-upgrade">
                    <div class="preview-container preview-blurred purio-mcp-upgrade__preview" aria-hidden="true">
                        <div class="purio-mcp-upgrade__notice">
                            <?php $this->render_provider_logos(); ?>
                            <div class="listeo-ai-auto-config-copy">
                                <strong><?php esc_html_e('Edit WordPress from your AI chat', 'ai-chat-search'); ?></strong>
                                <span><?php esc_html_e('Connect ChatGPT, Claude, or another supported AI assistant. Manage pages, products, orders, and PurioChat simply by chatting.', 'ai-chat-search'); ?></span>
                            </div>
                        </div>

                        <div class="purio-mcp-upgrade__master">
                            <span class="purio-mcp-upgrade__checkbox"></span>
                            <span class="purio-mcp-upgrade__master-copy">
                                <strong><?php esc_html_e('Allow AI connections', 'ai-chat-search'); ?></strong>
                                <span class="purio-mcp-upgrade__status"><?php esc_html_e('Enabled', 'ai-chat-search'); ?></span>
                                <small><?php esc_html_e('Nothing can connect while this is switched off.', 'ai-chat-search'); ?></small>
                            </span>
                        </div>

                        <div class="purio-mcp-upgrade__connect">
                            <div class="purio-mcp-upgrade__section-heading">
                                <strong><?php esc_html_e('Connect your AI assistant', 'ai-chat-search'); ?></strong>
                                <small>
                                    <?php esc_html_e('Choose your assistant and copy its MCP server address.', 'ai-chat-search'); ?>
                                    <br><a href="https://docs.purethemes.net/puriochat/knowledge-base/mcp-connections-for-chatgpt-and-claude/" target="_blank" rel="noopener noreferrer" class="airs-guide-link purio-mcp-guide-link"><?php esc_html_e('Read Guide', 'ai-chat-search'); ?> &rarr;</a>
                                </small>
                            </div>

                            <div class="purio-mcp-upgrade__provider-grid">
                                <div class="purio-mcp-upgrade__provider purio-mcp-upgrade__provider--chatgpt">
                                    <div class="purio-mcp-upgrade__provider-header">
                                        <img src="<?php echo esc_url(LISTEO_AI_SEARCH_PLUGIN_URL . 'assets/provider-icons/openai.png'); ?>" alt="" />
                                        <div>
                                            <strong><?php esc_html_e('ChatGPT', 'ai-chat-search'); ?></strong>
                                            <ol>
                                                <li><?php esc_html_e('Settings → Security and Login → Enable Developer Mode', 'ai-chat-search'); ?></li>
                                                <li><?php esc_html_e('Plugins → Add Plugin', 'ai-chat-search'); ?></li>
                                            </ol>
                                        </div>
                                    </div>
                                    <div class="purio-mcp-upgrade__endpoint">
                                        <input type="text" value="https://your-site.com/wp-json/puriochat-mcp/v1/mcp" disabled />
                                        <button type="button" disabled><?php esc_html_e('Copy', 'ai-chat-search'); ?></button>
                                    </div>
                                </div>

                                <div class="purio-mcp-upgrade__provider purio-mcp-upgrade__provider--claude">
                                    <div class="purio-mcp-upgrade__provider-header">
                                        <img src="<?php echo esc_url(LISTEO_AI_SEARCH_PLUGIN_URL . 'assets/provider-icons/claude.svg'); ?>" alt="" />
                                        <div>
                                            <strong><?php esc_html_e('Claude', 'ai-chat-search'); ?></strong>
                                            <ol>
                                                <li><?php esc_html_e('Settings → Connectors', 'ai-chat-search'); ?></li>
                                                <li><?php esc_html_e('Click Add → Add Custom Connector', 'ai-chat-search'); ?></li>
                                            </ol>
                                        </div>
                                    </div>
                                    <div class="purio-mcp-upgrade__endpoint">
                                        <input type="text" value="https://your-site.com/wp-json/puriochat-mcp/v1/claude" disabled />
                                        <button type="button" disabled><?php esc_html_e('Copy', 'ai-chat-search'); ?></button>
                                    </div>
                                </div>
                            </div>

                            <span class="purio-mcp-upgrade__check"><?php esc_html_e('Ready to connect', 'ai-chat-search'); ?></span>

                            <p class="purio-mcp-upgrade__secure-note">
                                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" focusable="false">
                                    <rect x="3" y="11" width="18" height="11" rx="2"></rect>
                                    <path d="M7 11V7a5 5 0 0 1 10 0v4"></path>
                                </svg>
                                <span><?php esc_html_e('Only administrators can connect. Assistants sign in with OAuth, never receive your password, and are limited to the permissions enabled below.', 'ai-chat-search'); ?></span>
                            </p>
                        </div>

                        <div class="purio-mcp-upgrade__fields">
                            <label>
                                <span><?php esc_html_e('What can the assistant do?', 'ai-chat-search'); ?></span>
                                <span class="purio-mcp-upgrade__fake-select">
                                    <?php esc_html_e('Read only', 'ai-chat-search'); ?>
                                </span>
                                <small><?php esc_html_e('Can safely view public WordPress content. Cannot make changes.', 'ai-chat-search'); ?></small>
                            </label>
                        </div>

                        <div class="purio-mcp-upgrade__permissions">
                            <span>
                                <strong><?php esc_html_e('Customize permissions', 'ai-chat-search'); ?></strong>
                                <small><?php esc_html_e('Only needed when a preset is not enough', 'ai-chat-search'); ?></small>
                            </span>
                            <span class="purio-mcp-upgrade__permissions-meta"><?php esc_html_e('2 permissions', 'ai-chat-search'); ?></span>
                        </div>
                    </div>

                    <div class="lock-overlay" style="background: rgba(255, 255, 255, 0.55); backdrop-filter: blur(3px);">
                        <div class="lock-content">
                            <h3><?php esc_html_e('Manage WordPress from ChatGPT or Claude', 'ai-chat-search'); ?></h3>
                            <ul class="benefits-list">
                                <li><?php esc_html_e('Create and update posts, pages, and custom post types', 'ai-chat-search'); ?></li>
                                <li><?php esc_html_e('Manage WooCommerce products and orders', 'ai-chat-search'); ?></li>
                                <li><?php esc_html_e('Keep WordPress permissions and secure sign-in', 'ai-chat-search'); ?></li>
                            </ul>
                            <a href="<?php echo esc_url($upgrade_url); ?>" class="button button-primary button-hero" target="_blank" rel="noopener noreferrer">
                                <?php esc_html_e('Upgrade to Pro', 'ai-chat-search'); ?>
                            </a>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        <?php
    }

    /**
     * Render the official MCP mark.
     */
    private function render_mcp_logo() {
        ?>
        <svg viewBox="0 0 180 180" fill="none" stroke="currentColor" xmlns="http://www.w3.org/2000/svg" focusable="false">
            <path d="M18 84.8528L85.8822 16.9706C95.2548 7.59798 110.451 7.59798 119.823 16.9706C129.196 26.3431 129.196 41.5391 119.823 50.9117L68.5581 102.177" stroke-width="12" stroke-linecap="round"></path>
            <path d="M69.2652 101.47L119.823 50.9117C129.196 41.5391 144.392 41.5391 153.765 50.9117L154.118 51.2652C163.491 60.6378 163.491 75.8338 154.118 85.2063L92.7248 146.6C89.6006 149.724 89.6006 154.789 92.7248 157.913L105.331 170.52" stroke-width="12" stroke-linecap="round"></path>
            <path d="M102.853 33.9411L52.6482 84.1457C43.2756 93.5183 43.2756 108.714 52.6482 118.087C62.0208 127.459 77.2167 127.459 86.5893 118.087L136.794 67.8822" stroke-width="12" stroke-linecap="round"></path>
        </svg>
        <?php
    }

    /**
     * Render AI provider logos only in the information notice.
     */
    private function render_provider_logos() {
        $providers = array(
            'assets/provider-icons/openai.png',
            'assets/provider-icons/claude.svg',
        );
        ?>
        <div class="purio-mcp-upgrade__provider-stack" aria-hidden="true">
            <?php foreach ($providers as $provider) : ?>
                <span class="purio-mcp-upgrade__provider-logo">
                    <img src="<?php echo esc_url(LISTEO_AI_SEARCH_PLUGIN_URL . $provider); ?>" alt="" />
                </span>
            <?php endforeach; ?>
        </div>
        <?php
    }
}
