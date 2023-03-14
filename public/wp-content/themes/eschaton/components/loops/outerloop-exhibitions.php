<?php 

    $periods = ['Present', 'Forthcoming', 'Past'];

    foreach( $periods as $period ) : 

        if( isset( $args['term'] ) ) {
            $args = array(
                'step'      => $args['step'],
                'period'    => $period,
                'taxonomy' 	=> $args['taxonomy'],
                'term' 		=> $args['term'],
                'termID' 	=> $args['termID'],
                'offset' 	=> $args['offset'],
            );
        }
        else {
            $args = array(
                'step'      => $args['step'],
                'period'    => $period,
            );
        }
            
        get_template_part('components/loops/loop', 'exhibitions', $args);
        
    endforeach; ?>


    <div class="posts_navigation">
        <button id="loadMore" class="mainBtn">Load more</button>
    </div>