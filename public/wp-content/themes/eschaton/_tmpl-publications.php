<?php
/* 
Template Name: Publications 
*/
get_header();
if (have_posts()) while (have_posts()) : the_post(); ?>
	<section class="section-publication-intro content-intro">
		
        <h2 class="tac"><?php the_title(); ?></h2>

		<div class="wyg">
			<?php the_content(); ?>
		</div>

        <div class="page_filters">
            <?php FILTERS('All types', 'media_type')->displayOutput(); ?>
            <?php FILTERS('All languages', 'language')->displayOutput(); ?>
        </div>

		<div id="grid" class="grid" data-posttype="publications">
            
            <?php

                get_template_part('components/loops/loop', 'publications', array(
                    'term' => 'all',
                ));

			?>
		</div>

    </section>
<?php endwhile;
get_footer(); ?>