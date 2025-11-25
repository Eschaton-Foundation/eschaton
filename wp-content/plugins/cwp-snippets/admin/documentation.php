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
    $doc_files = glob($doc_path . '[0-9][0-9]-*.md');

    if ($doc_files) {
        // Sort files alphabetically by filename, which respects the numerical prefixes
        sort($doc_files);

        foreach ($doc_files as $file_path) {
            $filename = basename($file_path);

            // Extract title and tab ID from filename (e.g., "01-introduction.md")
            if (preg_match('/^\d{2}-(.+)\.md$/', $filename, $matches)) {
                $slug = $matches[1]; // "introduction" or "connecting-to-filemaker"
                $tab_id = str_replace('-', '_', $slug);
                $title = ucwords(str_replace('-', ' ', $slug));

                $doc_tabs[$tab_id] = [
                    // $title is dynamic (derived from filename) and must not be passed to __()
                    'title' => $title,
                    'file'  => $filename,
                ];
            }
        }
    }

    // Determine the active tab, default to the first tab in the array
    $tab_keys = array_keys( $doc_tabs );
    $default_tab = ! empty( $tab_keys ) ? $tab_keys[0] : '';

    // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- This is a read-only display parameter used to select which documentation tab to show. Nonce checks are applied on state-changing endpoints/AJAX handlers elsewhere.
    $active_tab = isset( $_GET['tab'] ) ? sanitize_key( wp_unslash( $_GET['tab'] ) ) : $default_tab;
    // Ensure the computed tab actually exists in the documentation tabs; fall back to default if not.
    if ( ! array_key_exists( $active_tab, $doc_tabs ) ) {
        $active_tab = $default_tab;
    }

    ?>
    <div class="wrap cwp-snippets-help-wrap">
        <h1><?php esc_html_e( 'CWP Snippets Documentation', 'cwp-snippets' ); ?></h1>

        <div class="cwp-doc-container">
            <div class="cwp-doc-sidebar">
                <ul class="cwp-doc-menu" style="white-space: nowrap;">
                    <?php
                    foreach ( $doc_tabs as $tab_id => $tab_data ) {
                        $class = ( $active_tab === $tab_id ) ? 'current' : '';
                        echo '<li><a href="?page=cwp-snippets-documentation&tab=' . esc_attr( $tab_id ) . '" class="' . esc_attr( $class ) . '">' . esc_html( $tab_data['title'] ) . '</a></li>';
                    }
                    ?>
                </ul>
            </div>
            <div class="cwp-doc-content">
                <?php
                // Load the content of the active tab's markdown file
                if (isset($doc_tabs[$active_tab])) {
                    $file_path = FMCWP_PLUGIN_PATH . 'documentation/' . $doc_tabs[ $active_tab ]['file'];

                    if ( class_exists( 'Parsedown' ) && file_exists( $file_path ) ) {
                        // Instantiate Parsedown
                        $parsedown = new Parsedown();
                        // Read the markdown file content
                        $markdown_content = file_get_contents( $file_path );
                        // Convert markdown to HTML and output it (sanitize allowed post HTML)
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
