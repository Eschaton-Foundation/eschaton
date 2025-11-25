<?php
/**
 * Admin Footer View for CWP Snippets
 */
if ( ! defined( 'ABSPATH' ) ) exit;


?>
    </div>
    <div class="fmcwp-admin-footer-wrap">
        <div class="fmcwp-admin-footer">
            <div class="fmcwp-admin-footer-block">
                <p style="font-size: 16px;">CWP Snippets by:</p>
                <div style="display: flex;">
                <a class="cwpfm-link" href="https://rgcdata.com" target="_blank">
                    <img style="width:275px;" src="<?php echo esc_url( FMCWP_PLUGIN_URL . 'assets/images/RGCData.png' ); ?>" alt="RGC Data LLC">
                </a>
                </div>
                <p style="font-size: 14px;">RGC Data is your trusted partner in the world of FileMaker development and consulting. With a rich history of empowering businesses with data-driven solutions, we offer a comprehensive suite of services that harness the full potential of FileMaker technology.</p>
                <p><a href="https://rgcdata.com" target="_blank">More Info</a></p>
            </div>

            <div class="fmcwp-admin-footer-block">
                <p style="font-size: 16px;">Built on CWP Snippets:</p>
                <div style="display: flex;">
                <a class="cwpfm-link" href="https://kyfmp.com" target="_blank">
                    <img style="width:250px;" src="<?php echo esc_url( FMCWP_PLUGIN_URL . 'assets/images/KYFMP.jpg' ); ?>" alt="KYFMP">
                </a>
                </div>
                <p style="font-size: 14px;">Join Ron Glen Cates and other FileMaker developers every 4th Tuesday of the month The group convenes on Zoom, providing a virtual space where FileMaker enthusiasts can gather, share insights, exchange ideas, and explore the latest trends and developments in the realm of FileMaker development.</p>
                <p><a href="https://kyfmp.com" target="_blank">More Info</a></p>
            </div>

            <div class="fmcwp-admin-footer-block">
                <p style="font-size: 16px;">Built on CWP Snippets:</p>
                <div style="display: flex; margin: -5px 0 -7px 0;">
                <a class="cwpfm-link" href="https://fullaccess.us" target="_blank">
                    <img style="width:250px;" src="<?php echo esc_url( FMCWP_PLUGIN_URL . 'assets/images/fullaccess.png' ); ?>" alt="Full Access">
                </a>
                </div>
                <p style="font-size: 14px;">[Full Access] is a community-led, in-person celebration of Claris FileMaker and related technologies, designed to strengthen and empower developers in a durable way. By bringing like-minded people together, we’ll create an environment where we can support and uplift each other along our journey — fostering collaboration and growth within the community.</p>
                <p><a href="https://fullaccess.us" target="_blank">More Info</a></p>
            </div>        

        </div>
    </div>

    <style>
        #cwp-import-dialog-overlay {
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background-color: rgba(0, 0, 0, 0.6);
            z-index: 10000;
            display: flex;
            align-items: center;
            justify-content: center;
        }
        #cwp-import-dialog {
            background-color: #fff;
            padding: 20px;
            border-radius: 5px;
            box-shadow: 0 5px 15px rgba(0,0,0,0.3);
            width: 90%;
            max-width: 500px;
        }
        #cwp-import-dialog-title {
            margin-top: 0;
            border-bottom: 1px solid #ddd;
            padding-bottom: 10px;
        }
        #cwp-import-dialog-content {
            margin: 20px 0;
        }
        #cwp-import-conflict-list {
            list-style: none; /* Remove default list bullets */
            padding-left: 0;
            max-height: 150px;
            overflow-y: auto;
            background-color: #f9f9f9;
            border: 1px solid #ddd;
            padding: 10px;
            margin-top: 10px;
        }
        #cwp-import-conflict-list li {
            display: flex;
            align-items: center;
            padding: 5px 0;
        }
        #cwp-import-conflict-list label {
            margin-left: 10px;
        }
        #cwp-import-dialog-actions {
            text-align: right;
            border-top: 1px solid #ddd;
            padding-top: 15px;
            margin-top: 15px;
        }
        #cwp-import-dialog-actions .button {
            margin-left: 10px;
        }
    </style>
    <div id="cwp-import-dialog-overlay" style="display: none;">
        <div id="cwp-import-dialog">
            <h3 id="cwp-import-dialog-title">Confirm Snippet Import</h3>
            <div id="cwp-import-dialog-content">
                <p>The following snippets were found in the import file. Select the snippets you wish to add or update.</p>
                <ul id="cwp-import-conflict-list"></ul>
            </div>
            <div id="cwp-import-dialog-actions">
                <button id="cwp-import-action-cancel" class="button">Cancel</button>
                <button id="cwp-import-action-confirm" class="button button-primary">Confirm Import</button>
            </div>
        </div>
    </div>

    <!-- Hidden Import Form available on all CWP pages -->
    <form method="post" enctype="multipart/form-data" id="import-snippets-form" action="<?php echo esc_url(admin_url('admin-post.php')); ?>" style="display: none;">
        <input type="hidden" name="action" value="import_snippets">
        <?php
    // The filter_type is only relevant on the main snippets page, but we add it for consistency.
    // The JS will use the correct context regardless.
    // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- This value is a read-only context/display value used to populate a hidden form field. The form includes a nonce via wp_nonce_field('import_snippets_action','import_snippets_nonce') and the nonce is verified in the admin-post handler that processes the import_snippets action.
    $current_filter = isset($_GET['filter_type']) ? sanitize_text_field(wp_unslash($_GET['filter_type'])) : 'Snippet';
        ?>
        <input type="hidden" name="filter_type" value="<?php echo esc_attr($current_filter); ?>">
        <?php wp_nonce_field('import_snippets_action', 'import_snippets_nonce'); ?>
        <input type="file" id="snippets_import_file" name="snippets_import_file" accept=".json" style="display: none;">
    </form>
</div>
