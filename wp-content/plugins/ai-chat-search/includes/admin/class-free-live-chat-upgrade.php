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
                                    <li class="purio-live-chat-upgrade__alerts-benefit">
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
                                    <li class="purio-live-chat-upgrade__platform-benefit">
                                        <span class="airs-label purio-live-chat-upgrade__telegram-label">
                                            <svg class="purio-live-handoff__label-icon" width="14" height="14" viewBox="0 0 24 24" fill="currentColor" aria-hidden="true"><path d="M21.6 2.4a1.5 1.5 0 0 0-1.52-.18L2.93 9.4a1.5 1.5 0 0 0 .1 2.8l4.33 1.53 1.72 5.24a1.5 1.5 0 0 0 2.55.52l2.42-2.48 4.25 3.12a1.5 1.5 0 0 0 2.36-.92l2.87-15.4a1.5 1.5 0 0 0-.93-1.41ZM9.2 13.02l8.58-6.2-6.67 7.18-.78 2.7-1.13-3.68Z"></path></svg>
                                            <?php esc_html_e('Telegram app notifications:', 'ai-chat-search'); ?>
                                        </span>
                                        <ul class="purio-live-handoff__telegram-benefits">
                                            <li>
                                                <svg class="purio-live-handoff__platform-icon is-windows" viewBox="0 0 24 24" fill="currentColor" aria-hidden="true"><path d="M2 4.2 10.6 3v8H2V4.2Zm9.8-1.4L22 1.4V11H11.8V2.8ZM2 12.2h8.6v8L2 19v-6.8Zm9.8 0H22v9.6l-10.2-1.4v-8.2Z"></path></svg>
                                                <span><?php esc_html_e('Windows alerts', 'ai-chat-search'); ?></span>
                                            </li>
                                            <li>
                                                <svg class="purio-live-handoff__platform-icon is-apple" viewBox="0 0 384 512" fill="currentColor" aria-hidden="true"><path d="M318.7 268.7c-.2-36.7 16.4-64.4 50-84.8-18.8-26.9-47.2-41.7-84.7-44.6-35.5-2.8-74.3 20.7-88.5 20.7-15 0-49.4-19.7-76.4-19.7C63.3 141.2 4 184.8 4 273.5q0 39.3 14.4 81.2c12.8 36.7 59 126.7 107.2 125.2 25.2-.6 43-17.9 75.8-17.9 31.8 0 48.3 17.9 76.4 17.9 48.6-.7 90.4-82.5 102.6-119.3-65.2-30.7-61.7-90-61.7-91.9Zm-56.6-164.2c27.3-32.4 24.8-61.9 24-72.5-24.1 1.4-52 16.4-67.9 34.9-17.5 19.8-27.8 44.3-25.6 71.9 26.1 2 49.9-11.4 69.5-34.3Z"></path></svg>
                                                <span><?php esc_html_e('macOS alerts', 'ai-chat-search'); ?></span>
                                            </li>
                                            <li>
                                                <svg class="purio-live-handoff__platform-icon is-iphone" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><rect x="6.5" y="2" width="11" height="20" rx="2.5"></rect><path d="M10 5h4M11 19h2"></path></svg>
                                                <span><?php esc_html_e('iPhone alerts', 'ai-chat-search'); ?></span>
                                            </li>
                                            <li>
                                                <svg class="purio-live-handoff__platform-icon is-android" viewBox="0 0 24 24" fill="currentColor" aria-hidden="true"><path d="m17.6 9.48 1.84-3.18a.63.63 0 0 0-1.09-.63l-1.86 3.22A11.43 11.43 0 0 0 12 8c-1.59 0-3.11.31-4.49.88L5.65 5.67a.63.63 0 0 0-1.09.63L6.4 9.48C3.3 11.17 1.15 14.31 1 18h22c-.15-3.69-2.3-6.83-5.4-8.52ZM7 15.25a1.25 1.25 0 1 1 0-2.5 1.25 1.25 0 0 1 0 2.5Zm10 0a1.25 1.25 0 1 1 0-2.5 1.25 1.25 0 0 1 0 2.5Z"></path></svg>
                                                <span><?php esc_html_e('Android alerts', 'ai-chat-search'); ?></span>
                                            </li>
                                            <li class="is-wide">
                                                <svg class="purio-live-handoff__telegram-benefit-icon" viewBox="0 0 24 24" fill="currentColor" aria-hidden="true"><path d="M21.6 2.4a1.5 1.5 0 0 0-1.52-.18L2.93 9.4a1.5 1.5 0 0 0 .1 2.8l4.33 1.53 1.72 5.24a1.5 1.5 0 0 0 2.55.52l2.42-2.48 4.25 3.12a1.5 1.5 0 0 0 2.36-.92l2.87-15.4a1.5 1.5 0 0 0-.93-1.41ZM9.2 13.02l8.58-6.2-6.67 7.18-.78 2.7-1.13-3.68Z"></path></svg>
                                                <span><?php esc_html_e('Reply directly from the Telegram app', 'ai-chat-search'); ?></span>
                                            </li>
                                        </ul>
                                    </li>
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
