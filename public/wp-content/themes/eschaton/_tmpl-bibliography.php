<?php
/* 
Template Name: Bibliography 
*/
get_header();
if (have_posts()) while (have_posts()) : the_post(); ?>
	<article class="section-bibliography-intro content-intro">
		
        <h2 class="tac"><?php the_title(); ?></h2>

		<section class="wyg">
			<?php the_content(); ?>
		</section>

        <section>
            <?php FILTERS('All types', 'media_type')->displayOutput(); ?>
        </section>

		<section id="grid" class="grid" data-posttype="bibliography">
            
            <?php

                get_template_part('components/loops/loop', 'bibliography', array(
                    'term' => 'all',
                ));

			?>
		</section>

    </article>
<?php endwhile;
get_footer(); ?>