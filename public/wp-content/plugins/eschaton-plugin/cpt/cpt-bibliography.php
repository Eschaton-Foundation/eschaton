<?php 

function bibliography_register_post_types() {
	
    // CPT Portfolio
    $labels = array(
        'name' => 'Bibliography',
        'all_items' => 'All items',  // affiché dans le sous menu
        'singular_name' => 'Item',
        'add_new_item' => 'Add item',
        'edit_item' => 'Modify Item',
        'menu_name' => 'Bibliography'
    );

	$args = array(
        'labels' => $labels,
        'public' => true,
        'show_in_rest' => true,
        'has_archive' => true,
        'supports' => array( 'title', 'editor','thumbnail','custom-fields','excerpt'),
        'taxonomies' => array('category', 'post_tag'),
        'rewrite' => array('slug' => 'bibliography','with_front' => true),
        'menu_position' => 5, 
        'menu_icon' => 'dashicons-book',
        'show_ui' => true,
        'show_in_menu' => true,
        'show_in_nav_menus' => true,
	);

	register_post_type( 'bibliography', $args );
}
add_action( 'init', 'bibliography_register_post_types' ); // Le hook init lance la fonction