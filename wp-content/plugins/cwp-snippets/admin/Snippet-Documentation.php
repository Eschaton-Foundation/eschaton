<?php
?>

<style>
    .cwp-doc-parent > a.parent-link {
        font-weight: normal;
        cursor: pointer;
        color: #23282d;
        text-decoration: none;
        display: block;
        position: relative;
        padding-left: 1.2em;
        transition: background-color 0.2s ease, color 0.2s ease;
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
        transition: background-color 0.2s ease, color 0.2s ease;
    }
    .cwp-doc-submenu li a.submenu-link.current {
        color: #0073aa;
        font-weight: bold;
    }
    /* Hover state for parent and child links */
    .cwp-doc-parent > a.parent-link:hover,
    .cwp-doc-submenu li a.submenu-link:hover {
        background: #CCC;
        color: #000;
    }
    .cwp-doc-parent > a.parent-link.current {
        color: #0073aa;
    }
    /* Highlight selected parent or child item */
    .cwp-doc-parent.current > a.parent-link,
    .cwp-doc-submenu li.current > a.submenu-link,
    .cwp-doc-submenu li > a.submenu-link.current {
        background: #CCC;
        color: #000;
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
// --- CWP Snippets Documentation Viewer ---
if (!class_exists('Parsedown')) {
    echo '<p>Error: Parsedown library not found. Please ensure Composer dependencies are installed.</p>';
    return;
}
$doc_path = WP_PLUGIN_DIR . '/cwp-snippets/documentation/';
$doc_files = glob($doc_path . '*.md');
$query_args = [];
if ( isset( $_GET['fm_cwp_data_id'] ) ) {
    $query_args['fm_cwp_data_id'] = sanitize_text_field( $_GET['fm_cwp_data_id'] );
}
$base_url = strtok(get_permalink(), '?');

// Active tab from query string (needed during menu rendering)
$active_tab = isset($_GET['doc_page']) ? sanitize_key($_GET['doc_page']) : '';

// Parent/child grouping logic from documentation.php
$parent_menu_items = [];
$child_menu_items = [];
foreach ($doc_files as $file_path) {
    $filename = basename($file_path);
    if (preg_match('/^(\d{2})-([a-z0-9-]+)\.md$/i', $filename, $matches) && strpos($filename, 'CHILD-') !== 0) {
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
foreach ($doc_files as $file_path) {
    $filename = basename($file_path);
    if (preg_match('/^CHILD-(12)-(\d{2})-([a-z0-9-]+)\.md$/i', $filename, $matches)) {
        $parent_num = $matches[1];
        $order_num = $matches[2];
        $child_slug = $matches[3];
        $tab_id = 'CHILD_' . $parent_num . '_' . $order_num . '_' . str_replace('-', '_', $child_slug);
        $title = ucwords(str_replace('-', ' ', $child_slug));
        $child_menu_items[$parent_num][] = [
            'file' => $filename,
            'parent_num' => $parent_num,
            'tab_id' => $tab_id,
            'title' => $title
        ];
    }
}
?>
<div class="cwp-documentation-viewer">
    <nav class="cwp-doc-nav">
        <ul class="cwp-doc-menu" style="white-space: nowrap;">
            <?php
            // Render parent menu items and their children as accordion
            foreach ($parent_menu_items as $parent) {
                $has_children = !empty($child_menu_items[$parent['num']]);
                $parent_class = $has_children ? 'has-children' : '';
                // Check if this parent should be open (active) because a child is active
                $is_child_active = false;
                if ($has_children) {
                    foreach ($child_menu_items[$parent['num']] as $child) {
                        if (strtolower($child['tab_id']) === strtolower($active_tab)) {
                            $is_child_active = true;
                            break;
                        }
                    }
                }
                if ($is_child_active) {
                    $parent_class .= ' parent-active';
                }
                // Determine if this parent is the active item itself or one of its children is active
                $parent_current = (strtolower($parent['tab_id']) === strtolower($active_tab)) || $is_child_active;
                if ($parent_current) {
                    $parent_class .= ' current';
                }
                echo '<li class="cwp-doc-parent ' . $parent_class . '" data-parent="' . esc_attr($parent['tab_id']) . '">';
                $nav_args = array_merge($query_args, ['doc_page' => $parent['tab_id']]);
                $url = esc_url(add_query_arg($nav_args, $base_url));
                $parent_link_class = 'parent-link' . ($parent_current ? ' current' : '');
                echo '<a href="' . $url . '" class="' . $parent_link_class . '">' . esc_html($parent['title']) . '</a>';
                if ($has_children) {
                    echo '<ul class="cwp-doc-submenu" style="border-radius:0;">';
                    foreach ($child_menu_items[$parent['num']] as $child) {
                        $nav_args = array_merge($query_args, ['doc_page' => $child['tab_id']]);
                        $url = esc_url(add_query_arg($nav_args, $base_url));
                        $child_current = (strtolower($child['tab_id']) === strtolower($active_tab));
                        $li_current_attr = $child_current ? ' class="current"' : '';
                        $a_class = 'submenu-link' . ($child_current ? ' current' : '');
                        echo '<li' . $li_current_attr . ' style="padding:0px; border-bottom:1px solid #CCC; border-right:0; border-left:0; background:#fff;">'
                            . '<a href="' . $url . '" class="' . $a_class . '" style="width:100%;height:100%;padding:9px;">'
                            . '<span style="color:#b7b7b7; font-weight:bold; margin-right:6px;">- </span>' . esc_html($child['title']) . '</a>'
                            . '</li>';
                    }
                    echo '</ul>';
                }
                echo '</li>';
            }
            ?>
        </ul>
    </nav>
    <div class="cwp-doc-content">
        <?php
        // Load the content of the selected tab's markdown file
        $active_tab = isset($_GET['doc_page']) ? sanitize_key($_GET['doc_page']) : '';
        $file_to_load = '';

        foreach ($parent_menu_items as $parent) {
            if (strtolower($parent['tab_id']) === strtolower($active_tab)) {
                $file_to_load = $doc_path . $parent['file'];
                break;
            }
            if (!empty($child_menu_items[$parent['num']])) {
                foreach ($child_menu_items[$parent['num']] as $child) {
                    if (strtolower($child['tab_id']) === strtolower($active_tab)) {
                        $file_to_load = $doc_path . $child['file'];
                        break 2;
                    }
                }
            }
        }
        if ($file_to_load && file_exists($file_to_load)) {
            $markdown_content = file_get_contents($file_to_load);
            $parsedown = new Parsedown();
            echo $parsedown->text($markdown_content);
        } else {
            echo '<p>Sorry, the requested documentation could not be found.</p>';
        }
        ?>
    </div>
</div>