<?php 
    $periods = ['Present', 'Forthcoming', 'Passed'];

    foreach( $periods as $period ) : 

?>



<div class="exhibitions-stage">
			
    <?php
        $today = date('Ymd');

        $query_args = array(
			'post_type' => 'exhibitions',
		    'post_status' => 'publish',
			'posts_per_page' => -1,
			'meta_key' => 'date_start',
			'orderby' => 'meta_value',
			'order' => 'DESC',
		);

        if(  array_key_exists('taxonomy', $args) && $args['term'] != "all" ) {
            $query_args['tax_query'] = array(
                array(
                    'taxonomy' => $args['taxonomy'],
                    'field'    => 'term_id',
                    'terms'    => array( $args['termID'] ),
                )
            );
        }

            if( $period === 'Present') {
                $query_args['meta_query'] = array(
                    array(
                        'key'     => 'date_start',
                        'compare' => '<=',
                        'value'   => $today,
                    ),
                    array(
                        'key'     => 'date_end',
                        'compare' => '>=',
                        'value'   => $today,
                    )
                );
            }
            else if( $period === 'Passed' ) {
                $query_args['meta_query'] = array(
                    array(
                        'key'     => 'date_start',
                        'compare' => '<=',
                        'value'   => $today,
                    ),
                    array(
                        'key'     => 'date_end',
                        'compare' => '<',
                        'value'   => $today,
                    )
                );
            }
            else if( $period === 'Forthcoming' ) {
                $query_args['meta_query'] = array(
                    array(
                        'key'     => 'date_start',
                        'compare' => '>=',
                        'value'   => $today,
                    ),
                    array(
                        'key'     => 'date_end',
                        'compare' => '>=',
                        'value'   => $today,
                    )
                );
            }
		

        $the_query = new WP_Query( 
            $query_args
        );

		if ( $the_query->have_posts()) : ?>

            <h3 class="stage_title"><?php echo $period; ?></h3>

            <div class="exhibitions-grid <?php  echo $period === 'Passed' ? 'passed' : '' ?>">
                <?php while ( $the_query->have_posts()) : 
                    $the_query->the_post();

                    get_template_part('components/blocs/bloc', 'exhibition');
                    
                endwhile; ?>
            </div>
            
            
            <div class="posts_navigation">
                <button id="loadMore" class="mainBtn hidden">Load more</button>
            </div>

		<?php endif; 
	wp_reset_query(); ?>
</div>


<?php endforeach; ?>