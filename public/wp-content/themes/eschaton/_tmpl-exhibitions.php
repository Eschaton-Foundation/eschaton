<?php
/* 
Template Name: Exhibitions 
*/
get_header();
if (have_posts()) while (have_posts()) : the_post(); ?>
	<article class="section-exhibition-intro content-intro">
		<h2 class="tac"><?php the_title(); ?></h2>
		<section class="wyg">
			<?php the_content(); ?>
		</section>


		<section>
			<div class="filters_group">
                <button class="active" data-taxonomy="exhyear" data-term="all">All dates</button>
                <?php 
                $types = get_terms( array(
                    'taxonomy' => 'exhyear',
                    'hide_empty' => false
                ) );
                
                if ( !empty($types) ) :
                    foreach( $types as $term ) {

                        $output = '<button class="" data-taxonomy="exhyear" data-term="' . $term->slug . '" data-termID="' . $term->term_id . '">';
                        $output.= esc_attr( $term->name );
                        $output.='</button>';
                        echo $output;
                    }
                endif; ?>
            </div>
		</section>

		<section id="grid" data-posttype="exhibition">
			<h2>Present</h2>
			<?php
			get_template_part('components/loops/loop', 'exhibitions', array('period' => 'present')); ?>

			<h2>Forthcoming</h2>
			<?php 
			get_template_part('components/loops/loop', 'exhibitions', array('period' => 'forthcoming')); ?>

			<h2>Passed</h2>
			<?php 
			get_template_part('components/loops/loop', 'exhibitions', array('period' => 'passed')); ?>
		</section>

	</article>

<?php endwhile;
get_footer(); ?>