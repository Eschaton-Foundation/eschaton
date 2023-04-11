<?php 

$fullgrid = true;

if( !isset($_POST['loadmore']) && $_POST['loadmore'] !== NULL ) {
    $fullgrid = false;
} elseif( $_POST['loadmore'] === "true" ) {
    $fullgrid = false;
} ?>



    <?php

        if( isset($args['period']) ) {
            $period = $args['period'];
        }
        else {
            $period = $_POST['period'];
        }

        $today = date('Ymd');

        $query_args = array(
			'post_type' => 'exhibitions',
		    'post_status' => 'publish',
			'posts_per_page' => 24,
			'meta_key' => 'date_start',
			'orderby' => 'meta_value',
			'order' => 'DESC',
		);

        if(  array_key_exists('taxonomy', $args) && $args['term'] !== "all" && $args['term'] !== "null" ) {
            $query_args['tax_query'] = array(
                'relation' => 'OR',
                array(
                    'taxonomy' => $args['taxonomy'],
                    'field'    => 'term_id',
                    'terms'    => array( $args['termID'] ),
                )
            );
        }
        else {
            $query_args['tax_query'] = array();
        }

        if( !isset( $args['step'] ) ) {
            $step = $_POST['step'];
        }
        else {
            $step = $args['step'];
        }

        if( isset( $args['offset'] ) ) {
            $query_args['offset'] = $args['offset'];
        }

        $query_args['posts_per_page'] = intval ($step);

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
                    ),
                );



            }
            else if( $period === 'Past' ) {
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

            <?php if( $fullgrid ) : ?>
                <div class="exhibitions-stage">
                    <h3 class="stage_title"><?php echo $period; ?></h3>
                <?php endif; ?>

                <?php 
                if( $period === 'Forthcoming' ) {
                    $gridClasses = "gridCount-" . $the_query->found_posts;
                } 
                else if ( $period === 'Past' ) {
                    $gridClasses = 'exhibitions-grid-past gridCount-3';
                }
                ?>

                <?php if( $fullgrid ) : ?>
                    <div class="exhibitions-grid <?php echo $gridClasses; ?>" data-step="<?php echo $step; ?>" data-posttype="exhibitions">
                <?php endif; ?>

                    <?php while ( $the_query->have_posts()) : 
                        $the_query->the_post();
                        get_template_part('components/blocs/bloc', 'exhibition');                    
                    endwhile; ?>

                <?php if( $fullgrid ) : ?>
                    </div>
                </div>
            <?php endif; ?>


		<?php endif; 
	wp_reset_query(); ?>


