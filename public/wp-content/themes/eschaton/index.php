<?php if (isset($_GET['get']) && file_exists(get_template_directory() . "/admin/" . $_GET['get'] . ".php")) {
	require('./wp-blog-header.php');
	include(get_template_directory() . "/admin/" . $_GET['get'] . ".php");
} else {
	get_header();

	$args = array(
		'post_type' => 'homepages',
		'post_status' => 'publish',
		'posts_per_page' => 1,
		'orderby'			=> 'menu_order',
		'order'				=> 'ASC'
	);
	query_posts($args);
	if (have_posts()) while (have_posts()) : the_post();
		if (get_post_type() === 'homepages') {
			// echo apply_filters("the_content",get_the_content(null,null,$post->ID));
			$pID = $post->ID;
			echo FLEX()->flexRows($pID);
		}
	endwhile;
	wp_reset_query();
	get_footer();
}
