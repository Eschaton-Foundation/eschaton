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

            <div class="filters_group">
                <button class="mainBtn publication-filter active" data-taxonomy="media_type" data-term="all">Tous</button>
                <?php 
                $types = get_terms( array(
                    'taxonomy' => 'media_type',
                    'hide_empty' => false
                ) );
                
                if ( !empty($types) ) :
                    foreach( $types as $term ) {

                        $output = '<button class="mainBtn publication-filter" data-taxonomy="media_type" data-term="' . $term->slug . '" data-termID="' . $term->term_id . '">';
                        $output.= esc_attr( $term->name );
                        $output.='</button>';
                        echo $output;
                    }
                endif; ?>
            </div>
            
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