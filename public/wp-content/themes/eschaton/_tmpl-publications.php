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
            <?php FILTERS('All languages', 'publication_language')->displayOutput(); ?>
            <?php FILTERS('All', 'publication_groupesolo')->displayOutput(); ?>
            <?php FILTERS('All dates', 'publication_date', 'inline')->displayOutput(); ?>
        </div>

		<div id="grid" class="grid" data-posttype="publications" data-step="2">
            
            <?php

                get_template_part('components/loops/loop', 'publications', array(
                    'term' => 'all',
                ));

			?>
		</div>

        <div class="posts_navigation">
            <button id="loadMore" class="mainBtn hidden">Load more</button>
        </div>


    </section>
<?php endwhile;
get_footer(); ?>