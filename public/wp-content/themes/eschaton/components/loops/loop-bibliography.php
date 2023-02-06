<?php

$loop_args = array( 
    'post_type' => 'bibliography',
    'posts_per_page' => -1,
    'meta_key' => 'media_date',
    'orderby' => 'meta_value',
    'order' => 'DESC',
);

if( $args['term'] != "all" ) {
    $loop_args['tax_query'] = array(
        array(
            'taxonomy' => $args['taxonomy'],
            'field'    => 'term_id',
            'terms'    => array( $args['termID'] ),
        )
    );
}

query_posts($loop_args);

if (have_posts()) :
    while (have_posts()) : the_post();

        get_template_part('components/blocs/bloc', 'bibliography');

    endwhile;
endif;
wp_reset_query();
