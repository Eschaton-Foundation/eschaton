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
                                    <li>
                                        <p class="lp-puriochat-tabs__feature-text">
                                            <?php esc_html_e('Get alerts via', 'ai-chat-search'); ?>
                                            <strong><?php esc_html_e('browser, email, or Telegram', 'ai-chat-search'); ?></strong>.<br>
                                            <?php esc_html_e('Reply from', 'ai-chat-search'); ?>
                                            <svg width="18" height="18" viewBox="0 0 24 24" aria-hidden="true" focusable="false">
                                                <path fill="#111111" d="M12 0C5.383 0 0 5.383 0 12s5.383 12 12 12 12-5.383 12-12S18.617 0 12 0ZM1.211 12c0-1.564.334-3.05.935-4.39l5.145 14.1A10.794 10.794 0 0 1 1.211 12Zm10.789 10.789c-1.06 0-2.084-.155-3.051-.44l3.237-9.406 3.315 9.083c.021.053.047.102.075.148A10.74 10.74 0 0 1 12 22.789Zm1.488-15.847c.65-.034 1.236-.103 1.236-.103.582-.069.514-.925-.068-.891 0 0-1.75.137-2.88.137-1.063 0-2.846-.137-2.846-.137-.583-.034-.651.856-.069.891 0 0 .55.069 1.133.103l1.684 4.614-2.366 7.097-3.937-11.71c.651-.034 1.236-.103 1.236-.103.582-.069.514-.925-.068-.891 0 0-1.75.137-2.88.137-.203 0-.442-.005-.696-.013A10.78 10.78 0 0 1 12 1.211c2.8 0 5.35 1.069 7.267 2.821-.046-.003-.091-.009-.139-.009-1.062 0-1.815.925-1.815 1.918 0 .89.514 1.644 1.062 2.534.411.719.89 1.644.89 2.98 0 .925-.356 1.986-.822 3.482l-1.078 3.604-3.877-11.599Zm3.928 14.384 3.295-9.528c.617-1.541.822-2.774.822-3.87 0-.397-.026-.767-.073-1.112A10.73 10.73 0 0 1 22.789 12c0 3.98-2.16 7.46-5.373 9.326Z"></path>
                                            </svg>
                                            WordPress <?php esc_html_e('or', 'ai-chat-search'); ?>
                                            <svg width="18" height="18" viewBox="0 0 24 24" aria-hidden="true" focusable="false">
                                                <path fill="#229ED9" d="M23.953 4.57a.91.91 0 0 0-1.282-.813L.548 12.286c-.702.278-.697.687-.128.862l5.677 1.773 2.186 6.835c.265.733.135 1.022.904 1.022.594 0 .856-.271 1.188-.594l2.85-2.77 5.928 4.376c1.091.602 1.88.29 2.153-1.012L23.953 4.57ZM8.11 14.513l11.093-6.998c.554-.336 1.063-.156.646.214l-9.151 8.255-.357 3.819-2.231-5.29Z"></path>
                                            </svg>
                                            Telegram.
                                        </p>
                                    </li>
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
