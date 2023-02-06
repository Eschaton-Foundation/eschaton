<?php

query_posts($loop_args);
if (have_posts()) :
    while (have_posts()) : the_post();

        get_template_part('components/blocs/bloc', 'bibliography');

    endwhile;
endif;
wp_reset_query();
