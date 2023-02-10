<?php
/* 
Template Name: Studios / Ateliers
 
*/
get_header();
if (have_posts()) while (have_posts()) : the_post(); ?>
	<section class="section-studio content-intro">
		
        <h2 class="tac"><?php the_title(); ?></h2>

		<article class="wyg">
			<?php the_content(); ?>
		</article>

		<article class="studios-grid">
            
            <?php
				$args = array(
                    'post_type' => 'studio',
                    'post_status' => 'publish',
                    'posts_per_page' => -1,
                    'meta_key' => 'date_start',
                    'orderby' => 'meta_value',
                    'order' => 'ASC',
                );
                
                query_posts($args);
                if (have_posts()) :
                    while (have_posts()) : the_post();

                        get_template_part('components/blocs/bloc', 'studio');

			        endwhile;
                endif;
                wp_reset_query();

			?>
		</article>
	</section>
<?php endwhile;
get_footer(); ?>