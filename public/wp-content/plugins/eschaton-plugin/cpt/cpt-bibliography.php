<?php 

function bibliography_register_post_types() {
	
    // CPT Portfolio
    $labels = array(
		'name'					=> 'Bibliography',
		'singular_name'			=> 'Bibliography',
		'menu_name'				=> 'Bibliography Creator',
		'name_admin_bar'		=> 'Bibliography',
		'archives'				=> 'Bibliography Archives',
		'attributes'			=> 'Bibliography Attributes',
		'parent_item_colon'		=> 'Parent Item:',
		'all_items'				=> 'All item',
		'add_new_item'			=> 'Add New Bibliography item',
		'add_new'				=> 'Add New',
		'new_item'				=> 'New Item',
		'edit_item'				=> 'Edit Item',
		'update_item'			=> 'Update Item',
		'view_item'				=> 'View Item',
		'view_items'			=> 'View Items',
		'search_items'			=> 'Search Item',
		'not_found'				=> 'Not found',
		'not_found_in_trash'	=> 'Not found in Trash',
		'featured_image'		=> 'Featured Image',
		'set_featured_image'	=> 'Set featured image',
		'remove_featured_image'	=> 'Remove featured image',
		'use_featured_image'	=> 'Use as featured image',
		'insert_into_item'		=> 'Insert into item',
		'uploaded_to_this_item'	=> 'Uploaded to this item',
		'items_list'			=> 'Items list',
		'items_list_navigation'	=> 'Items list navigation',
		'filter_items_list'		=> 'Filter items list',
    );

	$args = array(
        'labels' => $labels,
        'has_archive' => true,
        'supports' => array( 'title', 'editor','thumbnail','custom-fields','excerpt'),
        'taxonomies' => array(),
        'rewrite' => array('slug' => 'bibliography','with_front' => true),
		'hierarchical'			=> true,
		'public'				=> true,
		'show_in_rest'			=> true,
		'show_ui'				=> true,
		'show_in_menu'			=> true,
		'menu_position'			=> 24,
		'menu_icon'				=> 'dashicons-email',
		'show_in_admin_bar'		=> true,
		'show_in_nav_menus'		=> false,
		'can_export'			=> true,
		'exclude_from_search'	=> false,
		'publicly_queryable'	=> true,
		'capability_type'		=> 'page',
		'rewrite'				=> false
	);

	register_post_type( 'bibliography', $args );
}
add_action( 'init', 'bibliography_register_post_types' ); // Le hook init lance la fonction