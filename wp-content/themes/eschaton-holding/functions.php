<?php
add_action( 'init', 'chipsbase_styles' );
function chipsbase_styles() {
	if (!is_admin()) {
		wp_enqueue_style( 'chips-styles', get_template_directory_uri() . '/css/styles.css?v=0.001' );
	}
}
function chipsScripts() {
	if (!is_admin()) {
		wp_deregister_script( 'jquery' );
		// wp_register_script( 
		// 	'jquery', 
		// 	'//code.jquery.com/jquery-3.3.1.min.js',
		// 	false, 
		// 	'3.3.1'
		// 	);
		// wp_enqueue_script( 'jquery' );
		// wp_register_script('scriptsJS',get_bloginfo('template_directory') . '/js/app.js?v=0.91');
		// wp_enqueue_script('scriptsJS');
	}
}
add_action('init', 'chipsScripts');

if(function_exists('acf_add_options_page')) {
	acf_add_options_page(array(
		'page_title' => 'Global Options',
		'icon_url' => 'dashicons-admin-generic',
	));
}