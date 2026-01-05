<?php
// Exit if accessed directly
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Renders the CWP Snippets Documentation page.
 */
function cwp_snippets_documentation_page_html() {
    // Check user capabilities
    if ( ! current_user_can( 'manage_options' ) ) {
        return;
    }

    // Dynamically generate documentation tabs from markdown files
    $doc_tabs = [];
    $doc_path = FMCWP_PLUGIN_PATH . 'documentation/';
    $doc_files = glob($doc_path . '*.md');

    // Group files by parent (e.g., 12-developer-tools-and-helpers.md and its children)
    $groups = [];
    foreach ($doc_files as $file_path) {
        $filename = basename($file_path);
        // Match parent: 12-developer-tools-and-helpers.md
        if (preg_match('/^(\d{2}-developer-tools-and-helpers)\.md$/i', $filename, $matches)) {
            $parent_key = $matches[1];
            $groups[$parent_key] = [
                'parent' => [
                    'file' => $filename,
                    'title' => 'Developer Tools and Helpers',
                    'tab_id' => str_replace(['-', '.'], '_', $parent_key),
                ],
                'children' => []
            ];
        } elseif (preg_match('/^(\d{2}-[a-z0-9-]+)\.md$/i', $filename, $matches)) {
            // All other parents
            $parent_key = $matches[1];
            $groups[$parent_key] = [
                'parent' => [
                    'file' => $filename,
                    'title' => ucwords(str_replace(['-', '_'], ' ', preg_replace('/^\d{2}-/', '', $parent_key))),
                    'tab_id' => str_replace(['-', '.'], '_', $parent_key),
                ],
                'children' => []
            ];
        }
    }
    // Now add children: 12-developer-tools-helpers-XX-section.md (should be children of 12-developer-tools-and-helpers)
    foreach ($doc_files as $file_path) {
        $filename = basename($file_path);
        if (preg_match('/^(12-developer-tools-helpers)-(\d{2})-([a-z0-9-]+)\.md$/i', $filename, $matches)) {
            $parent_key = '12-developer-tools-and-helpers';
            if (isset($groups[$parent_key])) {
                $child_title = ucwords(str_replace(['-', '_'], ' ', $matches[3]));
                $groups[$parent_key]['children'][] = [
                    'file' => $filename,
                    'title' => $child_title,
                    'tab_id' => str_replace(['-', '.'], '_', $parent_key . '-' . $matches[2] . '-' . $matches[3]),
                ];
            }
        }
    }

    // Step 1: Collect parent menu items (NN-*.md, not CHILD-*.md)
    $parent_menu_items = [];
    foreach ($doc_files as $file_path) {
        $filename = basename($file_path);
        // Match parent: NN-something.md (not CHILD-*.md)
        if (preg_match('/^(\d+)-([a-z0-9-]+)\.md$/i', $filename, $matches) && strpos($filename, 'CHILD-') !== 0) {
            $num = $matches[1];
            $slug = $matches[2];
            $tab_id = $num . '_' . str_replace('-', '_', $slug);
            $title = ucwords(str_replace('-', ' ', $slug));
            $parent_menu_items[$num] = [
                'file' => $filename,
                'tab_id' => $tab_id,
                'title' => $title,
                'num' => $num
            ];
        }
    }
    // Step 2: Collect children using CHILD-XX-name.md
    $child_menu_items = [];
    foreach ($doc_files as $file_path) {
        $filename = basename($file_path);
        if (preg_match('/^CHILD-(\d+)-([a-z0-9-]+)\.md$/i', $filename, $matches)) {
            $parent_num = $matches[1];
            $child_slug = $matches[2];
            $tab_id = 'CHILD_' . $parent_num . '_' . str_replace('-', '_', $child_slug);
            // Remove order prefix from display title if present (e.g., 01-admin-debug-display => Admin Debug Display)
            $title = preg_replace('/^\d{2}-/', '', $child_slug);
            $title = ucwords(str_replace('-', ' ', $title));
            $child_menu_items[$parent_num][] = [
                'file' => $filename,
                'parent_num' => $parent_num,
                'tab_id' => $tab_id,
                'title' => $title
            ];
        }
    }
    // Build $doc_tabs with all parent and child tab_ids
    $doc_tabs = [];
    foreach ($parent_menu_items as $parent) {
        $doc_tabs[$parent['tab_id']] = [
            'file' => $parent['file'],
            'tab_id' => $parent['tab_id'],
            'title' => $parent['title'],
        ];
        if (!empty($child_menu_items[$parent['num']])) {
            foreach ($child_menu_items[$parent['num']] as $child) {
                $doc_tabs[$child['tab_id']] = [
                    'file' => $child['file'],
                    'tab_id' => $child['tab_id'],
                    'title' => $child['title'],
                ];
            }
        }
    }

    // Determine the active tab, default to the first tab in the array
    $tab_keys = array_keys( $doc_tabs );
    $default_tab = ! empty( $tab_keys ) ? $tab_keys[0] : '';

    // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- This is a read-only display parameter used to select which documentation tab to show. Nonce checks are applied on state-changing endpoints/AJAX handlers elsewhere.
    $active_tab = isset( $_GET['tab'] ) ? wp_unslash( $_GET['tab'] ) : $default_tab;
    // Ensure the computed tab actually exists in the documentation tabs; fall back to default if not.
    if ( ! array_key_exists( $active_tab, $doc_tabs ) ) {
        $active_tab = $default_tab;
    }

    // Build a set of all child tab_ids to skip them as top-level parents
    $child_tab_ids = [];
    foreach ($groups as $parent) {
        foreach ($parent['children'] as $child) {
            $child_tab_ids[$child['tab_id']] = true;
        }
    }

    // Remove debug output
    // Render parent menu items and their children as accordion
    ?>
    <div class="wrap cwp-snippets-help-wrap">
        <h1><?php esc_html_e( 'CWP Snippets Documentation', 'cwp-snippets' ); ?></h1>
        <div class="cwp-doc-container">
            <div class="cwp-doc-sidebar">
                <ul class="cwp-doc-menu" style="white-space: nowrap;">
                    <?php
                    foreach ($parent_menu_items as $parent) {
                        $has_children = !empty($child_menu_items[$parent['num']]);
                        $parent_class = $has_children ? 'has-children' : '';
                        echo '<li class="cwp-doc-parent ' . $parent_class . '" data-parent="' . esc_attr($parent['tab_id']) . '">';
                        echo '<a href="?page=cwp-snippets-documentation&tab=' . esc_attr($parent['tab_id']) . '" class="parent-link">' . esc_html($parent['title']) . '</a>';
                        if ($has_children) {
                            echo '<ul class="cwp-doc-submenu">';
                            foreach ($child_menu_items[$parent['num']] as $child) {
                                echo '<li><a href="?page=cwp-snippets-documentation&tab=' . esc_attr($child['tab_id']) . '" class="submenu-link">' . esc_html($child['title']) . '</a></li>';
                            }
                            echo '</ul>';
                        }
                        echo '</li>';
                    }
                    ?>
                </ul>
            </div>
            <div class="cwp-doc-content">
                <?php
                // Load the content of the active tab's markdown file
                $file_to_load = $doc_tabs[$active_tab]['file'];
                if ($file_to_load) {
                    $file_path = FMCWP_PLUGIN_PATH . 'documentation/' . $file_to_load;
                    if ( class_exists( 'Parsedown' ) && file_exists( $file_path ) ) {
                        $parsedown = new Parsedown();
                        $markdown_content = file_get_contents( $file_path );
                        echo wp_kses_post( $parsedown->text( $markdown_content ) );
                    } elseif ( ! class_exists( 'Parsedown' ) ) {
                        echo '<p>' . esc_html__( 'Error: Parsedown library not found. Please run `composer install`.', 'cwp-snippets' ) . '</p>';
                    } else {
                        echo '<p>' . esc_html__( 'Documentation file not found.', 'cwp-snippets' ) . '</p>';
                    }
                } else {
                    echo '<p>' . esc_html__( 'Please select a documentation topic from the menu.', 'cwp-snippets' ) . '</p>';
                }


                ?>
            </div>
        </div>
    </div>
    <style>
    .cwp-doc-parent > a.parent-link {
        font-weight: normal;
        cursor: pointer;
        color: #23282d;
        text-decoration: none;
        display: block;
        position: relative;
        padding-left: 1.2em;
    }
    .cwp-doc-parent.has-children > a.parent-link:before {
        content: '\25B6'; /* ► */
        position: absolute;
        left: 0.2em;
        top: 50%;
        transform: translateY(-50%) rotate(0deg);
        font-size: 0.9em;
        transition: transform 0.2s;
        color: #888;
    }
    .cwp-doc-parent.parent-active > a.parent-link:before {
        content: '\25BC'; /* ▼ */
        transform: translateY(-50%) rotate(0deg);
        color: #0073aa;
    }
    .cwp-doc-parent.has-children > a.parent-link {
        font-weight: normal;
    }
    .cwp-doc-parent.parent-active > a.parent-link {
        font-weight: bold;
        color: #0073aa;
    }
    .cwp-doc-submenu {
        margin-left: 1em;
        border-left: 2px solid #eee;
        padding-left: 0.5em;
        list-style: disc inside;
        overflow: hidden;
        max-height: 0;
        transition: max-height 0.3s cubic-bezier(0.4,0,0.2,1);
        display: block;
    }
    .cwp-doc-parent.parent-active > .cwp-doc-submenu {
        max-height: 500px; /* enough for most submenus */
        transition: max-height 0.4s cubic-bezier(0.4,0,0.2,1);
    }
    .cwp-doc-submenu li a.submenu-link {
        font-weight: normal;
        color: #23282d;
        padding: 5px 0px;
        display: inline-block;
        width:100%;
        text-decoration: none;
        box-sizing: border-box;
    }
    .cwp-doc-submenu li a.submenu-link.current {
        color: #0073aa;
        font-weight: bold;
    }
    .cwp-doc-parent > a.parent-link.current {
        color: #0073aa;
    }
    </style>
    <script>
    jQuery(document).ready(function($){
        // Set initial max-height for open submenu
        $('.cwp-doc-parent.parent-active > .cwp-doc-submenu').each(function(){
            $(this).css('max-height', this.scrollHeight + 'px');
        });
        $('.cwp-doc-parent.has-children > a.parent-link').on('click', function(e){
            var $parent = $(this).closest('.cwp-doc-parent');
            var $submenu = $parent.children('.cwp-doc-submenu');
            if ($submenu.length) {
                e.preventDefault();
                var isOpen = $parent.hasClass('parent-active');
                // Close all
                $('.cwp-doc-parent.has-children').removeClass('parent-active');
                $('.cwp-doc-submenu').css('max-height', 0);
                if (!isOpen) {
                    $parent.addClass('parent-active');
                    $submenu.css('max-height', $submenu[0].scrollHeight + 'px');
                }
            }
        });
    });
    </script>
    <?php
}

/**
 * Enqueues scripts and styles for the documentation page, and initializes CodeMirror.
 */
function cwp_documentation_admin_scripts() {
    // Check if we are on the documentation page by looking at the URL query parameter
    // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Using the page query here is purely to detect the current admin screen for enqueueing; it does not perform state changes.
    $page = isset( $_GET['page'] ) ? sanitize_text_field( wp_unslash( $_GET['page'] ) ) : '';
    if ( 'cwp-snippets-documentation' !== $page ) {
        return;
    }

    // Enqueue the stylesheet for the CodeMirror blocks
    wp_enqueue_style(
        'cwp-doc-admin-css',
        FMCWP_PLUGIN_URL . 'admin/css/documentation-admin.css',
        [],
        CWP_SNIPPETS_VERSION
    );

    // Enqueue the WordPress code editor. We enqueue a generic type, the JS will handle specifics.
    wp_enqueue_code_editor( [ 'type' => 'text/html' ] );

    // JavaScript to replace <pre> blocks and initialize CodeMirror
    ?>
    <script>
        jQuery(document).ready(function($) {
            if (typeof wp === 'undefined' || typeof wp.codeEditor === 'undefined') {
                console.warn('CWP Snippets: wp.codeEditor not available.');
                return;
            }

            // Map language classes to CodeMirror modes
            const langMap = {
                'language-php': 'text/x-php',
                'language-js': 'text/javascript',
                'language-css': 'text/css',
                'language-html': 'text/html',
                'language-shell': 'text/x-sh',
                'language-bash': 'text/x-sh',
                'language-sql': 'text/x-sql',
                'language-json': 'application/json',
                'language-xml': 'application/xml'
            };

            $('.cwp-doc-content pre > code').each(function() {
                const $code = $(this);
                const $pre = $code.parent();
                // Decode HTML entities from Parsedown's output before sending to editor
                const content = $('<div/>').html($code.html()).text();

                let mode = 'text/plain';
                const classList = $code.attr('class');
                if (classList) {
                    const classes = classList.split(' ');
                    for (const cls of classes) {
                        if (langMap[cls]) {
                            mode = langMap[cls];
                            break;
                        }
                    }
                }

                const $textarea = $('<textarea>').val(content);
                $pre.replaceWith($textarea);

                const editorSettings = wp.codeEditor.defaultSettings ? _.clone(wp.codeEditor.defaultSettings) : {};
                editorSettings.codemirror = _.extend(
                    {},
                    editorSettings.codemirror,
                    {
                        readOnly: true,
                        mode: mode,
                        lineNumbers: true,
                        theme: 'default',
                        indentUnit: 4,
                        tabSize: 4,
                        indentWithTabs: false,
                        lineWrapping: true
                    }
                );

                wp.codeEditor.initialize($textarea, editorSettings);
            });
        });
    </script>
    <?php
}
add_action( 'admin_head', 'cwp_documentation_admin_scripts' );
