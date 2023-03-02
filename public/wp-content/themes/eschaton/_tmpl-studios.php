<?php
/* 
Template Name: Studios / Ateliers
 
*/
get_header();
if (have_posts()) while (have_posts()) : the_post(); ?>
	<section class="section-studio content-intro">
		

        <div class="listing_w_filters">

            <div>
                <h2 class="tac"><?php the_title(); ?></h2>

                <div class="wyg">
                    <?php the_content(); ?>
                </div>
                
                <div class="studios-grid">
                    
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
                </div>
            </div>

        </div>
	</section>
<?php endwhile;
get_footer(); ?>