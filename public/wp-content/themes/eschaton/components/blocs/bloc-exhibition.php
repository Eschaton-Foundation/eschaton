

<article class="exhibition-single">

    <?php if (has_post_thumbnail()) {
        echo '<div class="img-wrap">';
            echo wp_get_attachment_image(get_post_thumbnail_id(get_the_ID()), 'thumbnail', false);
            echo "<span class='exhibtion_caption'>";
                the_post_thumbnail_caption( );
            echo "</span>";
        echo '</div>';
    } ?>

    <div class="txt-wrap">
        <a href="<?php the_field('link'); ?>" target="_blank" class="exhibition_title">
            <h3 class=""><?php the_title(); ?></h3>
        </a>

        <div class="wyg">
            <div><?php the_field("exhibition_place"); ?></div>
            <div><?php the_content(); ?></div>

            <div class="exhibition-dates">
                <span class="exhibition-start">
                    <?php the_field("date_start"); ?>
                </span>
                
                <span class="exhibition-end">
                    <?php 
                        if( get_field('permanent')) {
                            echo ' -  Ongoing (Permanent exhibition)';
                        }
                        else {
                            echo " - " . get_field("date_end"); 
                        }
                    ?>
                </span>
            </div>
        </div>
    </div>
    

</article>