<?php 

    if( get_field("formated_title") == '' ) {
        $title = get_the_title();
    }
    else {
        $title = get_field("formated_title");
    }
    ?>

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
        <?php if( get_field('link') !== '' ) : ?>
            <a href="<?php the_field('link'); ?>" target="_blank" class="exhibition_title">
                <h3 class=""><?php echo $title; ?></h3>
            </a>
        <?php else : ?>
            <h3 class="exhibition_title"><?php echo $title; ?></h3>
        <?php endif; ?>

        <div class="wyg">
            <div><?php the_field("exhibition_place"); ?></div>
            <div><?php the_content(); ?></div>

            <div class="exhibition-dates">
                <?php get_event_dates( get_the_ID()); ?>
            </div>
        </div>
    </div>
    

</article>