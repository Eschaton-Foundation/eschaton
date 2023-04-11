<?php
/* 
Template Name: Publications 
*/
get_header();

$step = 24;

if (have_posts()) while (have_posts()) : the_post(); ?>
	<section class="section-publication-intro content-intro section-w-filters">
		
        <h2 class="tac"><?php the_title(); ?></h2>

		<div class="wyg">
			<?php the_content(); ?>
		</div>

        <div class="listing_w_filters">

            <div class="page_filters">
                <?php FILTERS('All', '')->displayOutput(); ?>
                <?php FILTERS('', 'media_type', 'large', true )->displayOutput(); ?>
                <?php FILTERS('Langue', 'publication_language')->displayOutput(); ?>
                <?php FILTERS('Date de publication', 'publication_date', 'medium', true)->displayOutput(); ?>
            </div>

            <div class="outer_grid">
                <div id="grid" class="grid" data-posttype="publications" data-step="<?php echo $step; ?>">
                    
                    <?php
                        get_template_part('components/loops/loop', 'publications', array(
                            'term' => 'all',
                            'step'  => $step
                        ));
                    ?>
                </div>

                <div class="posts_navigation">
                    <button id="loadMore" class="mainBtn">Load more</button>
                </div>
            </div>
		</div>


    </section>
<?php endwhile;
get_footer(); ?>