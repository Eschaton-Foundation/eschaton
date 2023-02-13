<?php
// Register Custom Taxonomy
function chips_taxonomies() {
	$labels_ExhibitionYear = array(
		'name'						=> _x( 'Year', 'Taxonomy General Name', 'text_domain' ),
		'singular_name'				=> _x( 'Year', 'Taxonomy Singular Name', 'text_domain' ),
		'menu_name'					=> __( 'Year', 'text_domain' ),
		'all_items'					=> __( 'All Year', 'text_domain' ),
		'parent_item'				=> __( 'Parent Item', 'text_domain' ),
		'parent_item_colon'			=> __( 'Parent Item:', 'text_domain' ),
		'new_item_name'				=> __( 'New Year', 'text_domain' ),
		'add_new_item'				=> __( 'Add Year', 'text_domain' ),
		'edit_item'					=> __( 'Edit Year', 'text_domain' ),
		'update_item'				=> __( 'Update Year', 'text_domain' ),
		'view_item'					=> __( 'View Year', 'text_domain' ),
		'separate_items_with_commas'	=> __( 'Separate items with commas', 'text_domain' ),
		'add_or_remove_items'		=> __( 'Add or remove items', 'text_domain' ),
		'choose_from_most_used'		=> __( 'Choose from the most used', 'text_domain' ),
		'popular_items'				=> __( 'Popular Items', 'text_domain' ),
		'search_items'				=> __( 'Search Items', 'text_domain' ),
		'not_found'					=> __( 'Not Found', 'text_domain' ),
		'no_terms'					=> __( 'No items', 'text_domain' ),
		'items_list'				=> __( 'Items list', 'text_domain' ),
		'items_list_navigation'		=> __( 'Items list navigation', 'text_domain' ),
	);
	$args_ExhibitionYear = array(
		'labels'					=> $labels_ExhibitionYear,
		'hierarchical'				=> true,
		'public'					=> true,
		'show_ui'					=> true,
		'show_admin_column'			=> true,
		'show_in_nav_menus'			=> true,
		'show_tagcloud'				=> false,
		'show_in_rest'				=> true,
		'rewrite'					=> array( 'slug' => 'year' )
	);
	register_taxonomy( 'exhyear', array( 'exhibitions' ), $args_ExhibitionYear );


	$labels_ExhibitionContinent = array(
		'name'						=> _x( 'Continent / City', 'Taxonomy General Name', 'text_domain' ),
		'singular_name'				=> _x( 'Continent / City', 'Taxonomy Singular Name', 'text_domain' ),
		'menu_name'					=> __( 'Continent / City', 'text_domain' ),
		'all_items'					=> __( 'All Continent', 'text_domain' ),
		'parent_item'				=> __( 'Parent Item', 'text_domain' ),
		'parent_item_colon'			=> __( 'Parent Item:', 'text_domain' ),
		'new_item_name'				=> __( 'New Continent', 'text_domain' ),
		'add_new_item'				=> __( 'Add Continent', 'text_domain' ),
		'edit_item'					=> __( 'Edit Continent', 'text_domain' ),
		'update_item'				=> __( 'Update Continent', 'text_domain' ),
		'view_item'					=> __( 'View Continent', 'text_domain' ),
		'separate_items_with_commas'	=> __( 'Separate items with commas', 'text_domain' ),
		'add_or_remove_items'		=> __( 'Add or remove items', 'text_domain' ),
		'choose_from_most_used'		=> __( 'Choose from the most used', 'text_domain' ),
		'popular_items'				=> __( 'Popular Items', 'text_domain' ),
		'search_items'				=> __( 'Search Items', 'text_domain' ),
		'not_found'					=> __( 'Not Found', 'text_domain' ),
		'no_terms'					=> __( 'No items', 'text_domain' ),
		'items_list'				=> __( 'Items list', 'text_domain' ),
		'items_list_navigation'		=> __( 'Items list navigation', 'text_domain' ),
	);
	$args_ExhibitionContinent = array(
		'labels'					=> $labels_ExhibitionContinent,
		'hierarchical'				=> true,
		'public'					=> true,
		'show_ui'					=> true,
		'show_admin_column'			=> true,
		'show_in_nav_menus'			=> true,
		'show_tagcloud'				=> false,
		'show_in_rest'				=> true,
		'rewrite'					=> array( 'slug' => 'continent' )
	);
	register_taxonomy( 'exhcontinent', array( 'exhibitions' ), $args_ExhibitionContinent );


	$labels_ExhibitionPermanent = array(
		'name'						=> _x( 'Permanent exhibition', 'Taxonomy General Name', 'text_domain' ),
		'singular_name'				=> _x( 'Permanent exhibition', 'Taxonomy Singular Name', 'text_domain' ),
		'menu_name'					=> __( 'Permanent exhibition', 'text_domain' ),
		'all_items'					=> __( 'All', 'text_domain' ),
	);
	$args_ExhibitionPermanent = array(
		'labels'					=> $labels_ExhibitionPermanent,
		'hierarchical'				=> true,
		'public'					=> true,
		'show_ui'					=> true,
		'show_admin_column'			=> true,
		'show_in_nav_menus'			=> true,
		'show_tagcloud'				=> false,
		'show_in_rest'				=> true,
	);
	register_taxonomy( 'exhpermanent', array( 'exhibitions' ), $args_ExhibitionPermanent );

}
add_action( 'init', 'chips_taxonomies', 0 );