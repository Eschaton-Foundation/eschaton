<?php get_header();
if (have_posts()) while (have_posts()) : the_post();
	$pID = $post->ID; ?>
	<?= FLEX()->flexRows($pID);
endwhile;
get_footer(); ?>