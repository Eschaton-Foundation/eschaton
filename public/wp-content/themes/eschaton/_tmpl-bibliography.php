<?php
/* 
Template Name: Bibliography 
*/
get_header();
if (have_posts()) while (have_posts()) : the_post(); ?>
	<section class="section-exhibition-intro content-intro">
		
        <h2 class="tac"><?php the_title(); ?></h2>

		<article class="wyg">
			<?php the_content(); ?>
		</article>

		<article class="exhibitions-grid">
            
            <?php
				$args = array(
                    'post_type' => 'bibliography',
                    'post_status' => 'publish',
                    'posts_per_page' => -1,
                    'meta_key' => 'media_date',
                    'orderby' => 'meta_value',
                    'order' => 'DESC',
                );
                
                query_posts($args);
                if (have_posts()) :
                    while (have_posts()) : the_post();

                        get_template_part('components/blocs/bloc', 'bibliography');

			        endwhile;
                endif;
                wp_reset_query();

			?>
		</article>
	</section>
<?php endwhile;
get_footer(); ?>