<?php

$loop_args = array( 
    'post_type' => 'publication',
    'posts_per_page' => 2,
    'meta_key' => 'publication_date',
    'orderby' => 'meta_value',
    'order' => 'DESC',
);
$loop_args['paged'] = get_query_var( 'paged' ) 
? get_query_var( 'paged' ) 
: 1;

if( $args['term'] != "all" ) {
    $loop_args['tax_query'] = array(
        array(
            'taxonomy' => $args['taxonomy'],
            'field'    => 'term_id',
            'terms'    => array( $args['termID'] ),
        )
    );
}

$the_query = new WP_Query( 
    $loop_args
);

if ( $the_query->have_posts() ) :
    while ( $the_query->have_posts() ) :
        $the_query->the_post();

        get_template_part('components/blocs/bloc', 'publication');

    endwhile;
endif;
wp_reset_query(); ?>


<div id="posts_nav" class="posts_navigation">
    <div><?php previous_posts_link( 'Previous' ); ?></div>
    <div><?php next_posts_link( 'Next', $the_query->max_num_pages ); ?></div>
</div>

<button id="loadMore" class="mainBtn hidden">Load more</button>
