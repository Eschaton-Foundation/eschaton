<section class="exhibitions-grid">
			
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

        if( $args['period'] === 'present') {
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
        else if( $args['period'] === 'passed' ) {
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
        else if( $args['period'] === 'forthcoming' ) {
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
		
		query_posts($query_args);

		if (have_posts()) :
			while (have_posts()) : the_post();

				get_template_part('components/blocs/bloc', 'exhibition');

			endwhile;

        else : 
            echo 'No Exhibition';
            
		endif; 
	wp_reset_query(); ?>
</section>