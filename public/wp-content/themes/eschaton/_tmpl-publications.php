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

        <div class="listing_w_filters">

            <div class="page_filters">
                <?php FILTERS('All', '')->displayOutput(); ?>
                <?php FILTERS('', 'media_type', 'large', true )->displayOutput(); ?>
                <?php FILTERS('', 'publication_language')->displayOutput(); ?>
                <?php FILTERS('', 'publication_date', 'medium', true)->displayOutput(); ?>
            </div>

            <div class="outer_grid">
                <div id="grid" class="grid" data-posttype="publications" data-step="24">
                    
                    <?php
                        get_template_part('components/loops/loop', 'publications', array(
                            'term' => 'all',
                        ));
                    ?>
                </div>

                <div class="posts_navigation">
                    <button id="loadMore" class="mainBtn hidden">Load more</button>
                </div>
            </div>
		</div>


    </section>
<?php endwhile;
get_footer(); ?>