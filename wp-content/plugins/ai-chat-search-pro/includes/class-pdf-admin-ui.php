<?php
/**
 * Document Admin UI - PRO Feature
 *
 * Adds document upload interface to Universal Settings
 * Supports PDF, TXT, MD, XML, CSV files
 *
 * @package AI_Chat_Search_Pro
 * @since 2.0.0
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

class AI_Chat_Search_Pro_PDF_Admin_UI {

    /**
     * Single instance
     */
    private static $instance = null;

    /**
     * Get instance
     */
    public static function get_instance() {
        if (null === self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    /**
     * Constructor
     */
    private function __construct() {
        // Add PDF upload button to universal settings
        add_action('admin_enqueue_scripts', array($this, 'enqueue_admin_assets'));

        // Render PDF management modal (card is now rendered in FREE plugin)
        add_action('admin_footer', array($this, 'render_pdf_modal'));
    }

    /**
     * Enqueue admin assets
     */
    public function enqueue_admin_assets($hook) {
        // Only load on AI Chat Search admin page
        if ($hook !== 'toplevel_page_ai-chat-search') {
            return;
        }

        // Only load on database tab
        $active_tab = isset($_GET['tab']) ? sanitize_text_field(wp_unslash($_GET['tab'])) : 'settings';
        if ($active_tab !== 'database') {
            return;
        }

        wp_enqueue_style(
            'ai-chat-pro-pdf-admin',
            AI_CHAT_SEARCH_PRO_URL . 'assets/css/pdf-admin.css',
            array(),
            AI_CHAT_SEARCH_PRO_VERSION
        );

        wp_enqueue_script(
            'ai-chat-pro-pdf-admin',
            AI_CHAT_SEARCH_PRO_URL . 'assets/js/pdf-admin.js',
            array('jquery'),
            AI_CHAT_SEARCH_PRO_VERSION,
            true
        );

        wp_localize_script('ai-chat-pro-pdf-admin', 'aiChatProPdfConfig', array(
            'ajax_url' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('ai_chat_pro_pdf_upload'),
            'strings' => array(
                'uploading' => __('Uploading...', 'ai-chat-search-pro'),
                'processing' => __('Processing document...', 'ai-chat-search-pro'),
                'confirm_delete' => __('Are you sure you want to delete this document? All chunks and embeddings will be removed.', 'ai-chat-search-pro'),
                'delete_success' => __('Document deleted successfully', 'ai-chat-search-pro'),
                'upload_success' => __('Document uploaded successfully', 'ai-chat-search-pro'),
                'train_success' => __('Document queued for training', 'ai-chat-search-pro'),
                'error' => __('An error occurred', 'ai-chat-search-pro'),
            ),
        ));
    }

    /**
     * Render document management modal
     */
    public function render_pdf_modal() {
        $screen = get_current_screen();
        if (!$screen || $screen->id !== 'toplevel_page_ai-chat-search') {
            return;
        }

        $active_tab = isset($_GET['tab']) ? sanitize_text_field(wp_unslash($_GET['tab'])) : 'settings';
        if ($active_tab !== 'database') {
            return;
        }
        ?>
        <div id="pdf-upload-modal" class="listeo-ai-modal" style="display: none;">
            <div class="listeo-ai-modal-overlay"></div>
            <div class="listeo-ai-modal-content pdf-modal-content">
                <div class="listeo-ai-modal-header">
                    <h2><?php _e('Document Manager', 'ai-chat-search-pro'); ?></h2>
                    <button type="button" class="listeo-ai-modal-close">
                        <span class="dashicons dashicons-no-alt"></span>
                    </button>
                </div>

                <div class="listeo-ai-modal-body">
                    <!-- Upload Section -->
                    <div class="pdf-upload-section">
                        <h3><?php _e('Upload/Manage Documents', 'ai-chat-search-pro'); ?></h3>
                        <p class="description">
                            <?php _e('Upload documents to make their content searchable. Supported formats: <strong>PDF</strong>, <strong>TXT</strong>, <strong>MD</strong>, <strong>XML</strong>, <strong>CSV</strong>. Files will be automatically chunked and embedded for AI search.', 'ai-chat-search-pro'); ?>
                        </p>

                        <div class="pdf-upload-form">
                            <input type="file" id="pdf-file-input" accept=".pdf,.txt,.md,.xml,.csv" multiple style="display: none;">
                            <button type="button" class="button button-primary" id="pdf-select-btn">
                                <span class="dashicons dashicons-upload"></span>
                                <?php _e('Select Files', 'ai-chat-search-pro'); ?>
                            </button>
                            <span class="pdf-upload-status"></span>
                        </div>

                        <div class="pdf-upload-progress" style="display: none;">
                            <div class="progress-bar">
                                <div class="progress-fill"></div>
                            </div>
                            <p class="progress-text"></p>
                        </div>
                    </div>

                    <hr>

                    <!-- Manage Existing Documents Section -->
                    <div class="pdf-manage-section">
                        <h3 style="margin-bottom: 7px;"><?php _e('Uploaded Documents', 'ai-chat-search-pro'); ?></h3>
                        <span><?php _e('Click "Train Now" to generate embeddings for uploaded documents.', 'ai-chat-search-pro'); ?></span>

                        <div id="pdf-documents-list">
                            <p class="loading-message"><?php _e('Loading...', 'ai-chat-search-pro'); ?></p>
                        </div>
                    </div>
                </div>

                <div class="listeo-ai-modal-footer">
                    <button type="button" class="button" id="pdf-modal-close"><?php _e('Close', 'ai-chat-search-pro'); ?></button>
                </div>
            </div>
        </div>
        <?php
    }
}

// Initialize
AI_Chat_Search_Pro_PDF_Admin_UI::get_instance();
