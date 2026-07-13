<?php
/**
 * Free Live Chat upgrade preview.
 *
 * @package Listeo_AI_Search
 */

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Adds an inert Live Chat preview when the Pro implementation is unavailable.
 */
class Listeo_AI_Search_Free_Live_Chat_Upgrade {

    /**
     * Register admin hooks.
     */
    public function __construct() {
        add_filter('listeo_ai_search_admin_sidebar_tabs', array($this, 'add_sidebar_tab'));
        add_action('listeo_ai_search_admin_nav_tabs', array($this, 'render_nav_tab'));
        add_action('listeo_ai_search_admin_tab_content', array($this, 'render_tab'));
        add_filter('listeo_ai_search_admin_tab_title', array($this, 'filter_tab_title'), 10, 2);
    }

    /**
     * Check whether Pro is providing the real Live Chat page.
     *
     * @return bool
     */
    private function has_live_chat() {
        return true === apply_filters('listeo_ai_search_live_chat_available', false);
    }

    /**
     * Add Live Chat immediately before License in the WordPress sidebar.
     *
     * @param array $tabs Existing sidebar tabs.
     * @return array
     */
    public function add_sidebar_tab($tabs) {
        if ($this->has_live_chat()) {
            return $tabs;
        }

        $live_chat_tab = array(
            'slug' => 'live-conversations',
            'label' => __('Live Chat', 'ai-chat-search'),
        );
        $license_index = array_search('license', wp_list_pluck($tabs, 'slug'), true);

        if (false === $license_index) {
            $tabs[] = $live_chat_tab;
        } else {
            array_splice($tabs, $license_index, 0, array($live_chat_tab));
        }

        return $tabs;
    }

    /**
     * Render the locked top navigation tab.
     *
     * @param string $active_tab Active admin tab.
     */
    public function render_nav_tab($active_tab) {
        if ($this->has_live_chat()) {
            return;
        }
        ?>
        <a href="<?php echo esc_url(admin_url('admin.php?page=ai-chat-search&tab=live-conversations')); ?>" class="nav-tab <?php echo 'live-conversations' === $active_tab ? 'nav-tab-active' : ''; ?>">
            <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 19.951 19.951" aria-hidden="true">
                <g transform="translate(-2.025 -2.025)">
                    <path d="M6.25 5.75h11.5A2.25 2.25 0 0 1 20 8v7.25a2.25 2.25 0 0 1-2.25 2.25H11l-4.25 3v-3H6.25A2.25 2.25 0 0 1 4 15.25V8a2.25 2.25 0 0 1 2.25-2.25Z" fill="#6aa9ff" opacity="0.1"></path>
                    <path d="M6.25 5.75h11.5A2.25 2.25 0 0 1 20 8v7.25a2.25 2.25 0 0 1-2.25 2.25H11l-4.25 3v-3H6.25A2.25 2.25 0 0 1 4 15.25V8a2.25 2.25 0 0 1 2.25-2.25Z" fill="none" stroke="#006aff" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"></path>
                    <path d="M8 10h8M8 13h5" fill="none" stroke="#006aff" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"></path>
                </g>
            </svg>
            <?php esc_html_e('Live Chat', 'ai-chat-search'); ?>
        </a>
        <?php
    }

    /**
     * Render the locked Live Chat preview.
     *
     * @param string $active_tab Active admin tab.
     */
    public function render_tab($active_tab) {
        if ('live-conversations' !== $active_tab || $this->has_live_chat() || !current_user_can('manage_options')) {
            return;
        }

        $upgrade_url = AI_Chat_Search_Pro_Manager::get_upgrade_url('free_live_chat');
        ?>
        <div class="airs-tab-content airs-live-chat-upgrade-tab">
            <div class="airs-card airs-card-full-width">
                <div class="airs-card-header airs-card-header-with-icon">
                    <div class="airs-card-icon airs-card-icon-indigo">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" focusable="false">
                            <rect x="3" y="4" width="18" height="16" rx="3"></rect>
                            <path d="M3 14h4.5l2 3h5l2-3H21"></path>
                        </svg>
                    </div>
                    <div class="airs-card-header-text">
                        <h3><?php esc_html_e('Inbox', 'ai-chat-search'); ?></h3>
                        <p><?php esc_html_e('Monitor waiting, active, and recently active AI conversations.', 'ai-chat-search'); ?></p>
                    </div>
                </div>
                <div class="airs-card-body purio-live-chat-upgrade__card-body">
                    <div class="ai-chat-pro-feature-locked purio-live-chat-upgrade">
                        <div class="preview-container preview-blurred purio-live-chat-upgrade__preview" aria-hidden="true">
                            <aside class="purio-live-chat-upgrade__queue">
                                <section>
                                    <h3><?php esc_html_e('Waiting for human', 'ai-chat-search'); ?> <span class="purio-live-chat-upgrade__count">2</span></h3>
                                    <div class="purio-live-chat-upgrade__thread-list">
                                        <div class="purio-live-chat-upgrade__thread">
                                            <span class="purio-live-chat-upgrade__avatar is-waiting">M</span>
                                            <span><strong><?php esc_html_e('Michael Brown', 'ai-chat-search'); ?></strong><small><?php esc_html_e('Can someone check my order?', 'ai-chat-search'); ?></small></span>
                                        </div>
                                        <div class="purio-live-chat-upgrade__thread">
                                            <span class="purio-live-chat-upgrade__avatar is-waiting">A</span>
                                            <span><strong><?php esc_html_e('Anna Kowalska', 'ai-chat-search'); ?></strong><small><?php esc_html_e('I need help with my booking.', 'ai-chat-search'); ?></small></span>
                                        </div>
                                    </div>
                                </section>
                                <section>
                                    <h3><?php esc_html_e('Active', 'ai-chat-search'); ?> <span class="purio-live-chat-upgrade__count">1</span></h3>
                                    <div class="purio-live-chat-upgrade__thread-list">
                                        <div class="purio-live-chat-upgrade__thread is-selected">
                                            <span class="purio-live-chat-upgrade__avatar is-active">S</span>
                                            <span><strong><?php esc_html_e('Sophia Martinez', 'ai-chat-search'); ?></strong><small><?php esc_html_e('Thanks, that solved it.', 'ai-chat-search'); ?></small></span>
                                        </div>
                                    </div>
                                </section>
                            </aside>
                            <section class="purio-live-chat-upgrade__conversation">
                                <header>
                                    <span class="purio-live-chat-upgrade__avatar is-active">S</span>
                                    <div class="purio-live-chat-upgrade__visitor-details">
                                        <h2><?php esc_html_e('Sophia Martinez', 'ai-chat-search'); ?></h2>
                                        <p><span class="purio-live-chat-upgrade__status-badge"><?php esc_html_e('Human active', 'ai-chat-search'); ?></span></p>
                                        <div class="purio-live-chat-upgrade__visitor-meta">
                                            <span class="purio-live-chat-upgrade__meta-chip">🇺🇸 <?php esc_html_e('United States', 'ai-chat-search'); ?></span>
                                            <span class="purio-live-chat-upgrade__meta-chip">@ sophia@example.com</span>
                                            <span class="purio-live-chat-upgrade__meta-chip">◎ IP 203.0.113.42</span>
                                        </div>
                                    </div>
                                </header>
                                <div class="purio-live-chat-upgrade__messages">
                                    <p class="is-visitor"><?php esc_html_e('Could you help me update the delivery address?', 'ai-chat-search'); ?></p>
                                    <p class="is-agent"><?php esc_html_e('Of course. I am checking that for you now.', 'ai-chat-search'); ?></p>
                                </div>
                                <div class="purio-live-chat-upgrade__composer"><?php esc_html_e('Type a message…', 'ai-chat-search'); ?></div>
                            </section>
                        </div>

                        <div class="lock-overlay">
                            <div class="lock-content">
                                <span class="dashicons dashicons-format-chat" aria-hidden="true"></span>
                                <h3><?php esc_html_e('Add Human Live Chat', 'ai-chat-search'); ?></h3>
                                <p><?php esc_html_e('Let visitors request a person, allow AI to escalate, and reply from a shared WordPress inbox.', 'ai-chat-search'); ?></p>
                                <ul class="benefits-list">
                                    <li><?php esc_html_e('Browser, sound, and offline email alerts', 'ai-chat-search'); ?></li>
                                    <li><?php esc_html_e('Manual takeover with complete conversation history', 'ai-chat-search'); ?></li>
                                    <li><?php esc_html_e('Access for selected WordPress users', 'ai-chat-search'); ?></li>
                                </ul>
                                <a href="<?php echo esc_url($upgrade_url); ?>" class="button button-primary button-hero" target="_blank" rel="noopener noreferrer">
                                    <?php esc_html_e('Upgrade to Pro', 'ai-chat-search'); ?>
                                </a>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        <?php
    }

    /**
     * Use the Live Chat page title for the locked preview.
     *
     * @param string $title Current title.
     * @param string $active_tab Active admin tab.
     * @return string
     */
    public function filter_tab_title($title, $active_tab) {
        if ('live-conversations' === $active_tab && !$this->has_live_chat()) {
            return __('Live Chat', 'ai-chat-search');
        }
        return $title;
    }
}
