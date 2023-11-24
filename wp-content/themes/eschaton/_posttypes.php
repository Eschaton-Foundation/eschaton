<?php
function chips_post_types()
{
	$labels_Exhibitions = array(
		'name'					=> 'Exhibitions',
		'singular_name'			=> 'Exhibition',
		'menu_name'				=> 'Exhibitions',
		'name_admin_bar'		=> 'Exhibition',
		'archives'				=> 'Exhibition Archives',
		'attributes'			=> 'Exhibition Attributes',
		'parent_item_colon'		=> 'Parent Item:',
		'all_items'				=> 'Exhibitions',
		'add_new_item'			=> 'Add New Exhibition',
		'add_new'				=> 'Add Exhibition',
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
	$args_Exhibitions = array(
		'label'					=> 'Exhibitions',
		'description'			=> 'Exhibition Records',
		'labels'				=> $labels_Exhibitions,
		'supports'				=> array('title', 'editor', 'thumbnail'),
		'taxonomies'			=> array('exhyear'),
		'hierarchical'			=> true,
		'public'				=> true,
		'show_in_rest'			=> true,
		'show_ui'				=> true,
		'show_in_menu'			=> true,
		'menu_position'			=> 20,
		'menu_icon'				=> 'dashicons-text-page',
		'show_in_admin_bar'		=> true,
		'show_in_nav_menus'		=> true,
		'can_export'			=> true,
		'has_archive'			=> false,
		'exclude_from_search'	=> false,
		'publicly_queryable'	=> true,
		'capability_type'		=> 'page',
		'rewrite' => true
	);
	register_post_type('exhibitions', $args_Exhibitions);


	$labels_Artists = array(
		'name'					=> 'Artists',
		'singular_name'			=> 'Artist',
		'menu_name'				=> 'Artists',
		'name_admin_bar'		=> 'Artist',
		'archives'				=> 'Artist Archives',
		'attributes'			=> 'Artist Attributes',
		'parent_item_colon'		=> 'Parent Item:',
		'all_items'				=> 'Artists',
		'add_new_item'			=> 'Add New Artist',
		'add_new'				=> 'Add Artist',
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
	$args_Artists = array(
		'label'					=> 'Artists',
		'description'			=> 'Artist Records',
		'labels'				=> $labels_Artists,
		'supports'				=> array('title', 'excerpt', 'thumbnail'),
		'taxonomies'			=> array('exhyear'),
		'hierarchical'			=> true,
		'public'				=> true,
		'show_in_rest'			=> true,
		'show_ui'				=> true,
		'show_in_menu'			=> true,
		'menu_position'			=> 20,
		'menu_icon'				=> 'dashicons-groups',
		'show_in_admin_bar'		=> true,
		'show_in_nav_menus'		=> true,
		'can_export'			=> true,
		'has_archive'			=> false,
		'exclude_from_search'	=> false,
		'publicly_queryable'	=> true,
		'capability_type'		=> 'page',
		'rewrite' => true
	);
	register_post_type('artists', $args_Artists);

	$labels_Tours = array(
		'name'					=> 'Tours',
		'singular_name'			=> 'Tour',
		'menu_name'				=> 'Tours',
		'name_admin_bar'		=> 'Tour',
		'archives'				=> 'Tour Archives',
		'attributes'			=> 'Tour Attributes',
		'parent_item_colon'		=> 'Parent Item:',
		'all_items'				=> 'Tours',
		'add_new_item'			=> 'Add New Tour',
		'add_new'				=> 'Add Tour',
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
	$args_Tours = array(
		'label'					=> 'Tours',
		'description'			=> 'Tour Records',
		'labels'				=> $labels_Tours,
		'supports'				=> array('title'),
		'taxonomies'			=> array(),
		'hierarchical'			=> true,
		'public'				=> true,
		'show_in_rest'			=> true,
		'show_ui'				=> true,
		'show_in_menu'			=> true,
		'menu_position'			=> 20,
		'menu_icon'				=> 'dashicons-calendar',
		'show_in_admin_bar'		=> true,
		'show_in_nav_menus'		=> true,
		'can_export'			=> true,
		'has_archive'			=> false,
		'exclude_from_search'	=> false,
		'publicly_queryable'	=> true,
		'capability_type'		=> 'page',
		'rewrite' => true
	);
	register_post_type('tours', $args_Tours);

	$labels_homepages = array(
		'name'					=> 'Homepages',
		'singular_name'			=> 'Homepage',
		'menu_name'				=> 'Homepages',
		'name_admin_bar'		=> 'Homepages',
		'archives'				=> 'Homepage Archives',
		'attributes'			=> 'Homepage Attributes',
		'parent_item_colon'		=> 'Parent Item:',
		'all_items'				=> 'All Homepages',
		'add_new_item'			=> 'Add New Homepage',
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
	$args_homepages = array(
		'label'					=> 'Homepages',
		'description'			=> 'Homepage Records',
		'labels'				=> $labels_homepages,
		'supports'				=> array('title', 'revisions'),
		'taxonomies'			=> array(),
		'hierarchical'			=> true,
		'public'				=> true,
		'show_in_rest'			=> true,
		'show_ui'				=> true,
		'show_in_menu'			=> true,
		'menu_position'			=> 4,
		'menu_icon'				=> 'dashicons-feedback',
		'show_in_admin_bar'		=> true,
		'show_in_nav_menus'		=> false,
		'can_export'			=> true,
		'has_archive'			=> false,
		'exclude_from_search'	=> false,
		'publicly_queryable'	=> true,
		'capability_type'		=> 'page',
		'rewrite'				=> false
	);
	register_post_type('homepages', $args_homepages);

	$labels_Emails = array(
		'name'					=> 'Emails',
		'singular_name'			=> 'Email',
		'menu_name'				=> 'Email Creator',
		'name_admin_bar'		=> 'Emails',
		'archives'				=> 'Email Archives',
		'attributes'			=> 'Email Attributes',
		'parent_item_colon'		=> 'Parent Item:',
		'all_items'				=> 'All Emails',
		'add_new_item'			=> 'Add New Email',
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
	$args_Emails = array(
		'label'					=> 'Emails',
		'description'			=> 'Email Records',
		'labels'				=> $labels_Emails,
		'supports'				=> array('title', 'revisions'),
		'taxonomies'			=> array(),
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
		'has_archive'			=> false,
		'exclude_from_search'	=> false,
		'publicly_queryable'	=> true,
		'capability_type'		=> 'page',
		'rewrite'				=> false
	);
	register_post_type('emails', $args_Emails);
}
add_action('init', 'chips_post_types', 0);

// add_filter( 'manage_works_posts_columns', 'set_custom_edit_works_columns' );
// function set_custom_edit_works_columns($columns) {
// 	unset( $columns['worktype'] );
// 	unset( $columns['date'] );
// 	$columns['works_exh'] = __( 'Exhibition', 'kmayerson' );
// 	// $columns['publisher'] = __( 'Publisher', 'kmayerson' );

// 	return $columns;
// }

// // Add the data to the custom columns for the works post type:
// add_action( 'manage_works_posts_custom_column' , 'custom_works_column', 10, 2 );
// function custom_works_column( $column, $post_id ) {
//     switch ( $column ) {

//         case 'works_exh' :
//             the_field("notes_admin",$post_id);

//         case 'publisher' :
//             echo get_post_meta( $post_id , 'publisher' , true ); 
//             break;

//     }
// }