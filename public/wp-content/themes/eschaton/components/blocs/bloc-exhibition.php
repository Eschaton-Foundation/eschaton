

<article class="exhibition-single">

    <?php if (has_post_thumbnail()) { ?>
        <div class="img-wrap">
            <div class="ratio">
                <div class="content">
                    <?php echo wp_get_attachment_image(get_post_thumbnail_id(get_the_ID()), 'thumbnail', false); ?>
                </div>
            </div>

            <div class='exhibtion_caption'>
                <?php the_post_thumbnail_caption( ); ?>
            </div>
        </div>
    <?php } ?>

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
                            echo ' -  Ongoing <br>(Permanent exhibition)';
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