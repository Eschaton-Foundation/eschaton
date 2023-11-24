<?php

$loop_args = array( 
    'post_type' => 'publication',
    'orderby' => 'date',
    'order' => 'ASC',
);
// $loop_args['paged'] = get_query_var( 'paged' ) 
// ? get_query_var( 'paged' ) 
// : 1;

if( $args['term'] != "all" && $args['term'] != "null" ) {
    $loop_args['tax_query'] = array(
        array(
            'taxonomy' => $_POST['taxonomy'],
            'field'    => 'term_id',
            'terms'    => array( $args['termID'] ),
        )
    );
    $loop_args['tax_query'] = $args['tax_query'];

}
else {
    $loop_args['tax_query'] = array();
}



if( isset( $args['offset'] ) ) {
    $loop_args['offset'] = $args['offset'];
}
if( !isset( $args['step'] ) ) {
    $loop_args['posts_per_page'] = $_POST['step'];
}
else {
    $loop_args['posts_per_page'] = $args['step'];
}

$the_query = new WP_Query( 
    $loop_args
);

if ( $the_query->have_posts() ) :
    while ( $the_query->have_posts() ) :
        $the_query->the_post();

        get_template_part('components/blocs/bloc', 'publication');

    endwhile;

else : 
    echo '<p>...</p>';

endif;
wp_reset_query(); ?>




