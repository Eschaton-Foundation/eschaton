<?php

$offset = $_POST['offset'];

$loop_args = array( 
    'post_type' => 'publication',
    'posts_per_page' => 20,
    // 'meta_key' => 'publication_date',
    // 'orderby' => 'meta_value',
    'orderby' => 'date',
    'order' => 'ASC',
);
$loop_args['paged'] = get_query_var( 'paged' ) 
? get_query_var( 'paged' ) 
: 1;

if( $args['term'] != "all" ) {
    $loop_args['tax_query'] = array(
        array(
            'taxonomy' => $_POST['taxonomy'],
            'field'    => 'term_id',
            'terms'    => array( $args['termID'] ),
        )
    );
}

if( isset( $offset ) ) {
    $loop_args['offset'] = $offset;
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

<?php if( $args['term'] === "all" ) : ?>


<div id="posts_nav" class="posts_navigation">
    <div><?php previous_posts_link( 'Previous' ); ?></div>
    <div><?php next_posts_link( 'Next', $the_query->max_num_pages ); ?></div>
</div>

<?php endif; ?>
