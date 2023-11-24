<?php
/* 
Template name: Contact
*/
get_header();
if (have_posts()) while (have_posts()) : the_post(); ?>
	<section class="section-manualtitle">
		<h2 class="tac"><?php echo get_the_title(); ?></h2>
	</section>


	<section class="section-text wyg">
		<?php the_content(); ?>
	</section>
<?php
	echo FLEX()->flexRows($post->ID);
endwhile;
get_footer(); ?>