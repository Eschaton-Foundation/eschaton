<?php 

function publication_register_post_types() {
	
    // CPT Portfolio
    $labels = array(
		'name'					=> 'Publications',
		'singular_name'			=> 'Publication',
		'menu_name'				=> 'Publications',
		'name_admin_bar'		=> 'Publications',
		'archives'				=> 'Publications Archives',
		'attributes'			=> 'Publications Attributes',
		'parent_item_colon'		=> 'Parent Item:',
		'all_items'				=> 'All item',
		'add_new_item'			=> 'Add New Publication item',
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
        'rewrite' => array('slug' => 'publications','with_front' => true),
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

	register_post_type( 'publication', $args );
}
add_action( 'init', 'publication_register_post_types' ); // Le hook init lance la fonction




// Register Custom Taxonomy
function publication_taxonomies() {

	$labels = array(
		'name'                       => _x( 'Media Types', 'Taxonomy General Name', 'text_domain' ),
		'singular_name'              => _x( 'Media Type', 'Taxonomy Singular Name', 'text_domain' ),
		'menu_name'                  => __( 'Media Types', 'text_domain' ),
		'all_items'                  => __( 'All Items', 'text_domain' ),
		'parent_item'                => __( 'Parent Item', 'text_domain' ),
		'parent_item_colon'          => __( 'Parent Item:', 'text_domain' ),
		'new_item_name'              => __( 'New Item Name', 'text_domain' ),
		'add_new_item'               => __( 'Add New Item', 'text_domain' ),
		'edit_item'                  => __( 'Edit Item', 'text_domain' ),
		'update_item'                => __( 'Update Item', 'text_domain' ),
		'view_item'                  => __( 'View Item', 'text_domain' ),
		'separate_items_with_commas' => __( 'Separate items with commas', 'text_domain' ),
		'add_or_remove_items'        => __( 'Add or remove items', 'text_domain' ),
		'choose_from_most_used'      => __( 'Choose from the most used', 'text_domain' ),
		'popular_items'              => __( 'Popular Items', 'text_domain' ),
		'search_items'               => __( 'Search Items', 'text_domain' ),
		'not_found'                  => __( 'Not Found', 'text_domain' ),
		'no_terms'                   => __( 'No items', 'text_domain' ),
		'items_list'                 => __( 'Items list', 'text_domain' ),
		'items_list_navigation'      => __( 'Items list navigation', 'text_domain' ),
	);
	$args = array(
		'labels'                     => $labels,
		'hierarchical'               => true,
		'public'                     => true,
		'show_ui'                    => true,
		'show_admin_column'          => true,
		'show_in_nav_menus'          => true,
		'show_tagcloud'              => true,
	);
	register_taxonomy( 'media_type', array( 'publication' ), $args );


	$labels = array(
		'name'                       => _x( 'Publication language', 'Taxonomy General Name', 'text_domain' ),
		'singular_name'              => _x( 'Publication language', 'Taxonomy Singular Name', 'text_domain' ),
		'menu_name'                  => __( 'Publication languages', 'text_domain' ),
		'all_items'                  => __( 'All languages', 'text_domain' ),
		'parent_item'                => __( 'languages Item', 'text_domain' ),
		'parent_item_colon'          => __( 'languages Item:', 'text_domain' ),
		'new_item_name'              => __( 'New language Name', 'text_domain' ),
		'add_new_item'               => __( 'Add New language', 'text_domain' ),
		'edit_item'                  => __( 'Edit language', 'text_domain' ),
		'update_item'                => __( 'Update language', 'text_domain' ),
		'view_item'                  => __( 'View languages', 'text_domain' ),
		'separate_items_with_commas' => __( 'Separate languages with commas', 'text_domain' ),
		'add_or_remove_items'        => __( 'Add or remove items', 'text_domain' ),
		'choose_from_most_used'      => __( 'Choose from the most used', 'text_domain' ),
		'popular_items'              => __( 'Popular Items', 'text_domain' ),
		'search_items'               => __( 'Search Items', 'text_domain' ),
		'not_found'                  => __( 'Not Found', 'text_domain' ),
		'no_terms'                   => __( 'No items', 'text_domain' ),
		'items_list'                 => __( 'Items list', 'text_domain' ),
		'items_list_navigation'      => __( 'Items list navigation', 'text_domain' ),
	);
	$args = array(
		'labels'                     => $labels,
		'hierarchical'               => true,
		'public'                     => true,
		'show_ui'                    => true,
		'show_admin_column'          => true,
		'show_in_nav_menus'          => true,
		'show_tagcloud'              => true,
	);
	register_taxonomy( 'publication_language', array( 'publication' ), $args );


	$labels = array(
		'name'                       => _x( 'Publication date', 'Taxonomy General Name', 'text_domain' ),
		'singular_name'              => _x( 'Publication date', 'Taxonomy Singular Name', 'text_domain' ),
		'menu_name'                  => __( 'Publication date', 'text_domain' ),
		'all_items'                  => __( 'All dates', 'text_domain' ),
		'parent_item'                => __( 'date Item', 'text_domain' ),
		'parent_item_colon'          => __( 'dates Item:', 'text_domain' ),
		'new_item_name'              => __( 'New date Name', 'text_domain' ),
		'add_new_item'               => __( 'Add New date', 'text_domain' ),
		'edit_item'                  => __( 'Edit date', 'text_domain' ),
		'update_item'                => __( 'Update dates', 'text_domain' ),
		'view_item'                  => __( 'View date', 'text_domain' ),
		'separate_items_with_commas' => __( 'Separate dates with commas', 'text_domain' ),
		'add_or_remove_items'        => __( 'Add or remove items', 'text_domain' ),
		'choose_from_most_used'      => __( 'Choose from the most used', 'text_domain' ),
		'popular_items'              => __( 'Popular Items', 'text_domain' ),
		'search_items'               => __( 'Search Items', 'text_domain' ),
		'not_found'                  => __( 'Not Found', 'text_domain' ),
		'no_terms'                   => __( 'No items', 'text_domain' ),
		'items_list'                 => __( 'Items list', 'text_domain' ),
		'items_list_navigation'      => __( 'Items list navigation', 'text_domain' ),
	);
	$args = array(
		'labels'                     => $labels,
		'hierarchical'               => true,
		'public'                     => true,
		'show_ui'                    => true,
		'show_admin_column'          => true,
		'show_in_nav_menus'          => true,
		'show_tagcloud'              => true,
	);
	register_taxonomy( 'publication_date', array( 'publication' ), $args );



	$labels = array(
		'name'                       => _x( 'Group / Solo ?', 'Taxonomy General Name', 'text_domain' ),
		'singular_name'              => _x( 'Group / Solo ?', 'Taxonomy Singular Name', 'text_domain' ),
		'menu_name'                  => __( 'Group / Solo ?', 'text_domain' ),
	);
	$args = array(
		'labels'                     => $labels,
		'hierarchical'               => true,
		'public'                     => true,
		'show_ui'                    => true,
		'show_admin_column'          => true,
		'show_in_nav_menus'          => true,
		'show_tagcloud'              => true,
	);
	register_taxonomy( 'publication_groupesolo', array( 'publication' ), $args );

}
add_action( 'init', 'publication_taxonomies', 0 );
